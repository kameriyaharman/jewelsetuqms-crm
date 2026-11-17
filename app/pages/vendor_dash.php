<?php
$vid = (int)user()['vendor_id'];
$vendor = one('SELECT * FROM vendors WHERE id=?', [$vid]);
$c = fn(array $st) => (int)val("SELECT COUNT(*) FROM qc_jobs WHERE vendor_id=? AND status IN ('" . implode("','", $st) . "')", [$vid]);
$jobs = all("SELECT j.*, c.code client_code, (SELECT CASE WHEN SUM(CASE WHEN final_result='FAIL' THEN 1 ELSE 0 END)>0 THEN 'FAIL' WHEN SUM(CASE WHEN final_result='CONDITIONAL' THEN 1 ELSE 0 END)>0 THEN 'CONDITIONAL' ELSE 'PASS' END FROM qc_items i WHERE i.job_id=j.id) res
   FROM qc_jobs j JOIN clients c ON c.id=j.client_id WHERE j.vendor_id=? AND (j.status<>'DRAFT' OR j.created_by=?) AND j.status<>'CANCELLED' ORDER BY j.id DESC LIMIT 12", [$vid, user()['id']]);
$next = one("SELECT j.*, c.code client_code, u.emp_code, u.name iname, u.phone FROM qc_jobs j JOIN clients c ON c.id=j.client_id LEFT JOIN users u ON u.id=j.inspector_id
   WHERE j.vendor_id=? AND j.status IN ('SCHEDULED','ASSIGNED') AND j.scheduled_date>=? ORDER BY j.scheduled_date LIMIT 1", [$vid, today()]);
$latest = one("SELECT i.*, j.po_no FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id WHERE j.vendor_id=? AND i.status='APPROVED' ORDER BY i.approved_at DESC LIMIT 1", [$vid]);
$holds = (int)val("SELECT COUNT(*) FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id WHERE j.vendor_id=? AND i.hold_status='PENDING' AND i.hold_for='vendor'", [$vid]);
$s = sum_items(approved_items());
$canReq = flag('enable_vendor_qc_request');
layout_start('Vendor Portal · ' . $vendor['name'], 'vendor', $canReq ? btn(icon('plus') . ' New QC Request', url('request-new'), 'pri') : '', 'Raise QC requests, track inspection dates and download your reports');
?>
<div class="grid g4 mb">
  <?= kpi('Submitted', sprintf('%02d', $c(['REQUESTED'])), 'waiting for schedule', 'warn', 'inbox') ?><?= kpi('Scheduled', sprintf('%02d', $c(['SCHEDULED', 'ASSIGNED'])), '', 'info', 'cal') ?>
  <?= kpi('In progress', sprintf('%02d', $c(['IN_PROGRESS', 'SUBMITTED', 'UNDER_REVIEW', 'RETURNED'])), '', 'gold', 'clock') ?><?= kpi('Completed', $c(['EMAILED', 'CLOSED']), number_format($s['rate'], 1) . '% rejection', 'ok', 'check') ?>
</div>
<?php if ($holds): ?><div class="note ruby mb"><b><?= $holds ?> design(s) held for your confirmation</b> — the inspector is waiting. <a href="<?= url('approvals') ?>">Respond now →</a></div><?php endif ?>
<div class="grid g-73">
<div class="card"><div class="card-h"><h2>My QC requests</h2><div class="actions"><?= $canReq ? btn('Bulk Excel', url('import'), 'sm') : '' ?><a href="<?= url('requests') ?>">All</a></div></div>
<?php if (!$jobs): echo empty_state('No QC requests yet.'); else: ?>
<div class="tbl-wrap"><table class="t"><tr><th>Req ID</th><th>Client</th><th>PO no.</th><th>QC scope</th><th>Req. date</th><th>Status</th></tr>
<?php foreach ($jobs as $j): $done = in_array($j['status'], ['EMAILED', 'CLOSED'], true); ?><tr><td><a class="strong" href="<?= url('request', ['id' => $j['id']]) ?>"><?= h($done ? ($j['job_no'] ?: $j['req_no']) : $j['req_no']) ?></a></td><td><?= h($j['client_code']) ?></td><td class="mono"><?= h($j['po_no']) ?></td>
  <td><?= h(scope_label($j)) ?></td><td><?= fdate($j['requested_date'], 'd M') ?></td><td><?= $done ? badge($j['res']) . ' <a class="btn sm" target="_blank" href="' . url('pdf', ['job' => $j['id']]) . '">PDF</a>' : badge($j['status']) ?></td></tr><?php endforeach ?></table></div><?php endif ?>
<p class="muted" style="font-size:12px;margin-bottom:0">You see only your own requests. <?= $canReq ? '' : '“New QC request” appears when JEWELSETU enables self-service requests — until then JEWELSETU Back Office creates them for you.' ?></p>
</div>
<div>
<div class="card"><h2 class="mb">Upcoming inspection</h2><?php if (!$next): echo empty_state('Nothing scheduled.'); else: ?>
  <div class="kv"><div><small>Request</small><b><?= h($next['req_no'] . ' · ' . $next['client_code']) ?></b></div><div><small>Date</small><b><?= fdate($next['scheduled_date']) ?> · <?= h($next['time_slot']) ?></b></div>
  <div><small>Inspector</small><b><?= $next['emp_code'] ? h($next['emp_code'] . ' · ' . $next['iname']) : 'To be assigned' ?></b></div><div><small>Location</small><b><?= h($next['location']) ?></b></div></div>
  <?php if ($next['emp_code']): ?><p class="note mt" style="font-size:12px">Gate-entry details sent on notification. Inspector mobile: <?= h($next['phone']) ?></p><?php endif; endif ?></div>
<div class="card"><h2 class="mb">Latest result</h2><?php if (!$latest): echo empty_state('No reports yet.'); else: ?>
  <div style="display:flex;gap:12px;align-items:center"><?= badge($latest['final_result']) ?><div><b class="mono"><?= h($latest['report_no']) ?></b><br><small><?= num($latest['rejected_qty']) ?> pcs rejected<?php $d = one('SELECT category FROM item_defects WHERE item_id=? ORDER BY qty DESC', [$latest['id']]); echo $d ? ' · ' . h($d['category']) : '' ?></small></div>
  <a class="btn sm right" target="_blank" href="<?= url('pdf', ['item' => $latest['id']]) ?>">PDF</a></div><?php endif ?></div>
</div></div>
<?php layout_end();
