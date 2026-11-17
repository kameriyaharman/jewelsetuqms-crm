<?php
[$sc, $sp] = job_scope('j');
$F = ['client' => (int)get('client'), 'vendor' => (int)get('vendor'), 'insp' => (int)get('insp'), 'q' => trim((string)get('q')), 'res' => (string)get('res'),
      'from' => (string)get('from', date('Y-m-01', strtotime('-2 months'))), 'to' => (string)get('to', today())];
$sql = "SELECT i.*, j.po_no, j.job_no, j.id jid, j.status jstatus, c.code client_code, v.name vendor_name, u.emp_code insp_code,
    (SELECT COUNT(*) FROM email_log e WHERE e.item_id=i.id OR (e.job_id=j.id AND e.item_id IS NULL)) emails,
    (SELECT COUNT(*) FROM qc_items x WHERE x.job_id=j.id) nsku
    FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id JOIN clients c ON c.id=j.client_id JOIN vendors v ON v.id=j.vendor_id LEFT JOIN users u ON u.id=j.inspector_id
    WHERE i.status='APPROVED' AND $sc";
$p = $sp;
if ($F['client']) { $sql .= ' AND j.client_id=?'; $p[] = $F['client']; }
if ($F['vendor']) { $sql .= ' AND j.vendor_id=?'; $p[] = $F['vendor']; }
if ($F['insp']) { $sql .= ' AND j.inspector_id=?'; $p[] = $F['insp']; }
if ($F['res']) { $sql .= ' AND i.final_result=?'; $p[] = $F['res']; }
if ($F['q']) { $sql .= ' AND (j.po_no LIKE ? OR i.sku LIKE ? OR i.report_no LIKE ?)'; array_push($p, "%{$F['q']}%", "%{$F['q']}%", "%{$F['q']}%"); }
if ($F['from']) { $sql .= ' AND i.approved_at>=?'; $p[] = $F['from'] . ' 00:00:00'; }
if ($F['to']) { $sql .= ' AND i.approved_at<=?'; $p[] = $F['to'] . ' 23:59:59'; }
$rows = all($sql . ' ORDER BY i.approved_at DESC LIMIT 500', $p);
$s = sum_items($rows);
$emails = $rows ? (int)val('SELECT COUNT(*) FROM email_log WHERE job_id IN (' . implode(',', array_unique(array_map('intval', array_column($rows, 'jid')))) . ')') : 0;
$clients = role() === 'client' ? [] : (role() === 'vendor' ? array_column(all('SELECT c.id, c.code FROM clients c JOIN client_vendor m ON m.client_id=c.id WHERE m.vendor_id=?', [user()['vendor_id']]), 'code', 'id') : array_column(all('SELECT id, code FROM clients ORDER BY code'), 'code', 'id'));
$vendors = role() === 'vendor' ? [] : (role() === 'client' ? array_column(all('SELECT v.id, v.name FROM vendors v JOIN client_vendor m ON m.vendor_id=v.id WHERE m.client_id=?', [user()['client_id']]), 'name', 'id') : array_column(all('SELECT id, name FROM vendors ORDER BY name'), 'name', 'id'));
$revs = is_internal() ? all("SELECT a.*, i.report_no, u.name uname FROM audit_log a JOIN qc_items i ON i.id=a.entity_id LEFT JOIN users u ON u.id=a.user_id WHERE a.entity='item' AND a.action IN ('REVISION','UNLOCK') ORDER BY a.id DESC LIMIT 8") : [];
layout_start('Reports', 'reports', '', 'Search and filter every report by client, vendor, inspector, result and date — view, download or re-email');
?>
<div class="card">
<form class="filters">
  <?php if ($clients): ?><div><label>Client</label><?= sel('client', $clients, $F['client'], '', 'All') ?></div><?php endif ?>
  <?php if ($vendors): ?><div><label>Vendor</label><?= sel('vendor', $vendors, $F['vendor'], '', 'All') ?></div><?php endif ?>
  <?php if (is_internal()): ?><div><label>Inspector</label><?= sel('insp', user_opts('inspector'), $F['insp'], '', 'All') ?></div><?php endif ?>
  <div><label>PO / SKU / report</label><input type="search" name="q" value="<?= h($F['q']) ?>" placeholder="Any"></div>
  <div><label>Result</label><?= sel('res', ['PASS' => 'Pass', 'FAIL' => 'Fail', 'CONDITIONAL' => 'Conditional'], $F['res'], '', 'All') ?></div>
  <div><label>From</label><input type="date" name="from" value="<?= h($F['from']) ?>"></div><div><label>To</label><input type="date" name="to" value="<?= h($F['to']) ?>"></div>
  <button class="btn pri">Search</button>
</form>
<?php if (!$rows): echo empty_state('No approved reports for these filters.'); else: ?>
<div class="tbl-wrap"><table class="t"><tr><th>Report no.</th><th>Date</th><?php if (role() !== 'client'): ?><th>Client</th><?php endif ?><?php if (role() !== 'vendor'): ?><th>Vendor</th><?php endif ?><th>PO</th><th>SKU</th><?php if (is_internal()): ?><th>Inspector</th><?php endif ?><th>Result</th><?php if (is_internal()): ?><th>Email</th><?php endif ?><th>Download</th></tr>
<?php foreach ($rows as $r): ?><tr><td><a class="mono strong" href="<?= url('report', ['id' => $r['id']]) ?>"><?= h($r['report_no']) ?></a><?= $r['rev'] ? ' <span class="badge b-conditional">REV ' . sprintf('%02d', $r['rev']) . '</span>' : '' ?></td>
  <td><?= fdate($r['approved_at'], 'd M') ?></td><?php if (role() !== 'client'): ?><td><?= h($r['client_code']) ?></td><?php endif ?><?php if (role() !== 'vendor'): ?><td><?= h($r['vendor_name']) ?></td><?php endif ?>
  <td class="mono"><?= h($r['po_no']) ?></td><td class="mono"><?= h($r['sku']) ?></td><?php if (is_internal()): ?><td><?= h($r['insp_code']) ?></td><?php endif ?>
  <td><?= badge($r['final_result']) ?><?= $r['concession_status'] ? '<br><small>concession ' . strtolower($r['concession_status']) . '</small>' : '' ?></td>
  <?php if (is_internal()): ?><td><?= $r['emails'] ? badge('SENT') : '<span class="muted">—</span>' ?></td><?php endif ?>
  <td class="actions" style="flex-wrap:nowrap"><a class="btn sm" target="_blank" href="<?= url('pdf', ['item' => $r['id']]) ?>" title="Single design PDF">Design PDF</a>
    <?php if (in_array($r['jstatus'], ['EMAILED', 'CLOSED', 'APPROVED'], true)): ?><a class="btn sm" target="_blank" href="<?= url('pdf', ['job' => $r['jid']]) ?>" title="PO-wise consolidated PDF">PO PDF<?= $r['nsku'] > 1 ? ' (' . $r['nsku'] . ')' : '' ?></a><?php endif ?></td></tr>
<?php endforeach ?></table></div><?php endif ?>
</div>
<div class="grid <?= $revs ? 'g-73' : '' ?> mt">
<?php if ($revs): ?><div class="card" style="margin:0"><h2 class="mb">Revision &amp; audit (latest)</h2><table class="t"><tr><th>Report</th><th>Rev</th><th>Changed by</th><th>Field</th><th>Old → New</th><th>Time</th></tr>
<?php foreach ($revs as $a): ?><tr><td class="mono"><?= h($a['report_no']) ?></td><td><?= sprintf('%02d', $a['rev']) ?></td><td><?= h($a['uname'] ?: 'System') ?></td><td><?= h($a['field']) ?></td><td><?= h(($a['old_val'] ?? '—') . ' → ' . ($a['new_val'] ?? '')) ?></td><td><?= fdt($a['created_at']) ?></td></tr><?php endforeach ?></table>
<p class="muted" style="font-size:12px">Unlock needs Super Admin + reason; old PDF kept, new PDF re-sent.</p></div><?php endif ?>
<div class="grid g2" style="gap:12px;align-content:start">
  <?= kpi('Reports in range', $s['n'], '', '', 'file') ?><?= kpi('Pass', $s['pass'], pct($s['pass'], $s['n'], 0), 'ok', 'check') ?>
  <?= kpi('Fail', $s['fail'], $s['cond'] . ' conditional', 'bad', 'x') ?><?php if (is_internal()): ?><?= kpi('Emails sent', $emails, $s['n'] ? round($emails / max(1, count(array_unique(array_column($rows, 'jid')))), 1) . ' per PO' : '', 'gold', 'mail') ?><?php else: ?><?= kpi('Rejection %', number_format($s['rate'], 1) . '%', num($s['rej']) . ' pcs', 'gold', 'chart') ?><?php endif ?>
</div></div>
<?php layout_end();
