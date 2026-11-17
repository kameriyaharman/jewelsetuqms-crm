<?php
$cid = (int)user()['client_id'];
$client = one('SELECT * FROM clients WHERE id=?', [$cid]);
$vendors = (int)val('SELECT COUNT(*) FROM client_vendor WHERE client_id=?', [$cid]);
$active = (int)val("SELECT COUNT(*) FROM qc_jobs WHERE client_id=? AND status IN ('REQUESTED','SCHEDULED','ASSIGNED','IN_PROGRESS','SUBMITTED','UNDER_REVIEW','RETURNED')", [$cid]);
$items = approved_items(); $s = sum_items($items);
$conc = all("SELECT i.*, v.name vendor_name FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id JOIN vendors v ON v.id=j.vendor_id WHERE j.client_id=? AND i.concession_status='PENDING'", [$cid]);
$holds = (int)val("SELECT COUNT(*) FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id WHERE j.client_id=? AND i.hold_status='PENDING' AND i.hold_for='client'", [$cid]);
$jobs = all("SELECT j.*, v.name vendor_name, (SELECT SUM(ready_qty) FROM qc_items i WHERE i.job_id=j.id) qty,
   (SELECT CASE WHEN SUM(CASE WHEN final_result='FAIL' THEN 1 ELSE 0 END)>0 THEN 'FAIL' WHEN SUM(CASE WHEN final_result='CONDITIONAL' THEN 1 ELSE 0 END)>0 THEN 'CONDITIONAL' ELSE 'PASS' END FROM qc_items i WHERE i.job_id=j.id) res
   FROM qc_jobs j JOIN vendors v ON v.id=j.vendor_id WHERE j.client_id=? AND j.status NOT IN ('DRAFT','CANCELLED') ORDER BY j.id DESC LIMIT 10", [$cid]);
$month = array_values(array_filter($items, fn($i) => $i['approved_at'] >= date('Y-m-01')));
$vr = vendor_rows($month ?: $items);
layout_start('Client Portal · ' . $client['name'], 'client', flag('enable_client_qc_request') ? btn(icon('plus') . ' Request QC', url('request-new'), 'pri') : '', 'Live QC across all your mapped vendors');
?>
<div class="grid g4 mb">
  <?= kpi('Mapped vendors', sprintf('%02d', $vendors), '', '', 'factory') ?><?= kpi('Active QC', sprintf('%02d', $active), 'requests in progress', 'info', 'clip') ?>
  <?= kpi('Avg rejection', number_format($s['rate'], 1) . '%', num($s['insp']) . ' pcs inspected', 'gold', 'chart') ?><?= kpi('Approvals pending', sprintf('%02d', count($conc) + $holds), count($conc) . ' concession · ' . $holds . ' SKU hold', count($conc) + $holds ? 'bad' : 'ok', 'check') ?>
</div>
<div class="grid g-73">
<div class="card"><div class="card-h"><h2>QC requests across my vendors</h2><a href="<?= url('requests') ?>">All</a></div>
<?php if (!$jobs): echo empty_state('No QC requests yet.'); else: ?>
<div class="tbl-wrap"><table class="t"><tr><th>Job / Req</th><th>Vendor</th><th>PO no.</th><th>Scope / qty</th><th>QC date</th><th>Status</th></tr>
<?php foreach ($jobs as $j): $done = in_array($j['status'], ['EMAILED', 'CLOSED'], true); ?><tr><td><a class="strong" href="<?= url('request', ['id' => $j['id']]) ?>"><?= h($j['job_no'] ?: $j['req_no']) ?></a></td><td><?= h($j['vendor_name']) ?></td><td class="mono"><?= h($j['po_no']) ?></td>
  <td><?= h($j['scope'] === 'SAMPLE' ? 'Sample' : ($j['bulk_mode'] === 'AQL' ? 'Bulk AQL' : 'Bulk')) ?> / <?= num($j['qty']) ?></td><td><?= fdate($j['scheduled_date'] ?: $j['requested_date'], 'd M') ?></td>
  <td><?= $done ? badge($j['res']) . ' <a class="btn sm" href="' . url('pdf', ['job' => $j['id']]) . '" target="_blank">PDF</a>' : badge($j['status']) ?></td></tr><?php endforeach ?></table></div><?php endif ?>
</div>
<div>
<?php if ($conc): $c = $conc[0]; $dev = jdec($c['deviation_json']); ?><div class="card" style="border-color:#efc3ca"><div class="card-h"><h2>Concession approval needed</h2><?= badge('CONDITIONAL') ?></div>
  <div class="kv"><div><small>Report</small><b class="mono"><?= h($c['report_no']) ?></b></div><div><small>Vendor</small><b><?= h($c['vendor_name']) ?></b></div>
  <div><small>Issue</small><b><?= h($dev ? $dev[0]['param'] . ' ' . $dev[0]['actual'] . ' vs ' . $dev[0]['spec'] . ' (' . $dev[0]['dev'] . ')' : 'Deviation') ?></b></div><div><small>Qty</small><b><?= num($c['inspected_qty']) ?> pcs</b></div></div>
  <div class="actions mt"><?= btn('Review &amp; decide', url('approvals', ['id' => $c['id']]), 'pri') ?><?= count($conc) > 1 ? '<span class="muted">+' . (count($conc) - 1) . ' more</span>' : '' ?></div></div><?php endif ?>
<?php if ($holds): ?><div class="card"><div class="note rose"><b><?= $holds ?> SKU(s) on hold</b> — the inspector needs your confirmation before continuing. <a href="<?= url('approvals') ?>">Respond →</a></div></div><?php endif ?>
<div class="card"><div class="card-h"><h2>Vendor rejection % (<?= $month ? date('M') : 'all time' ?>)</h2><a href="<?= url('analytics') ?>">Analytics</a></div>
  <?= $vr ? hbars(array_column(array_map(fn($r) => ['k' => $r['vendor'], 'v' => round($r['rate'], 1)], $vr), 'v', 'k'), '%') : empty_state('No reports yet.') ?></div>
</div></div>
<?php layout_end();
