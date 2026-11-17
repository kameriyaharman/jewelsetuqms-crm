<?php
[$sc, $sp] = job_scope('j');
$st = (string)get('st', 'ALL'); $src = (string)get('src'); $cf = (int)get('client'); $vf = (int)get('vendor'); $qs = trim((string)get('q'));
$base = "FROM qc_jobs j JOIN clients c ON c.id=j.client_id JOIN vendors v ON v.id=j.vendor_id WHERE $sc";
$p = $sp;
if (in_array(role(), ['client', 'vendor'], true)) { /* clients/vendors don't see other parties' drafts */ $base .= " AND (j.status<>'DRAFT' OR j.created_by=?)"; $p[] = user()['id']; }
$counts = []; foreach (all("SELECT j.status, COUNT(*) n $base GROUP BY j.status", $p) as $r) $counts[$r['status']] = (int)$r['n'];
$w = $base;
if ($st !== 'ALL') { $w .= ' AND j.status=?'; $p[] = $st; }
if ($src) { $w .= ' AND j.source=?'; $p[] = $src; }
if ($cf) { $w .= ' AND j.client_id=?'; $p[] = $cf; }
if ($vf) { $w .= ' AND j.vendor_id=?'; $p[] = $vf; }
if ($qs) { $w .= ' AND (j.po_no LIKE ? OR j.req_no LIKE ? OR j.job_no LIKE ? OR j.id IN (SELECT job_id FROM qc_items WHERE sku LIKE ?))'; array_push($p, "%$qs%", "%$qs%", "%$qs%", "%$qs%"); }
$rows = all("SELECT j.*, c.code client_code, v.name vendor_name, (SELECT COUNT(*) FROM qc_items i WHERE i.job_id=j.id) skus,
    (SELECT SUM(ready_qty) FROM qc_items i WHERE i.job_id=j.id) qty $w ORDER BY CASE j.status WHEN 'REQUESTED' THEN 0 WHEN 'DRAFT' THEN 1 ELSE 2 END, j.id DESC LIMIT 300", $p);
$sel = (int)get('id') ? job_load((int)get('id')) : null;
if ($sel && !can_see_job($sel)) $sel = null;
$canNew = is_role('super_admin', 'back_office') || (role() === 'vendor' && flag('enable_vendor_qc_request')) || (role() === 'client' && flag('enable_client_qc_request'));
$acts = (is_role('super_admin', 'back_office') ? btn(icon('upload') . ' Import Excel', url('import')) : '') . ($canNew ? btn(icon('plus') . ' New QC Request', url('request-new'), 'pri') : '');
$title = match (role()) { 'vendor' => 'My QC Requests', 'client' => 'QC requests across my vendors', default => 'QC Requests' };
layout_start($title, 'requests', $acts, is_internal() ? 'Requests from Back Office, Excel, Vendor portal and Client portal land in one queue; Back Office confirms date &amp; scope' : '');
$tab = fn($k, $l) => '<a class="' . ($st === $k ? 'on' : '') . '" href="' . url('requests', array_filter(['st' => $k, 'src' => $src, 'client' => $cf ?: null])) . '">' . $l . ' <b>' . ($k === 'ALL' ? array_sum($counts) : ($counts[$k] ?? 0)) . '</b></a>';
$schedulable = $sel && is_role('super_admin', 'back_office') && in_array($sel['status'], ['REQUESTED', 'DRAFT', 'SCHEDULED'], true);
?>
<div class="grid <?= $schedulable ? 'g-82' : '' ?>">
<div class="card">
  <div class="tabs"><?= $tab('ALL', 'All') ?><?= $tab('REQUESTED', 'Requested') ?><?= $tab('DRAFT', 'Draft') ?><?= $tab('SCHEDULED', 'Scheduled') ?><?= $tab('ASSIGNED', 'Assigned') ?><?= $tab('IN_PROGRESS', 'In progress') ?><?= $tab('SUBMITTED', 'Submitted') ?><?= $tab('APPROVED', 'Approved') ?><?= $tab('EMAILED', 'Reported') ?><?= $tab('CLOSED', 'Closed') ?></div>
  <form class="filters"><input type="hidden" name="st" value="<?= h($st) ?>"><input type="search" name="q" value="<?= h($qs) ?>" placeholder="PO / REQ / SKU…">
    <?php if (is_internal()): ?><?= sel('src', ['Back Office' => 'Back Office', 'Excel import' => 'Excel import', 'Vendor portal' => 'Vendor portal', 'Client portal' => 'Client portal'], $src, 'data-auto', 'Source: All') ?>
    <?= sel('client', array_column(all('SELECT id, code FROM clients ORDER BY code'), 'code', 'id'), $cf, 'data-auto', 'Client: All') ?><?php endif ?>
    <?php if (role() === 'client'): ?><?= sel('vendor', array_column(all('SELECT v.id, v.name FROM vendors v JOIN client_vendor m ON m.vendor_id=v.id WHERE m.client_id=?', [user()['client_id']]), 'name', 'id'), $vf, 'data-auto', 'Vendor: All mapped') ?><?php endif ?>
    <?php if (role() === 'vendor'): ?><?= sel('client', array_column(all('SELECT c.id, c.code FROM clients c JOIN client_vendor m ON m.client_id=c.id WHERE m.vendor_id=?', [user()['vendor_id']]), 'code', 'id'), $cf, 'data-auto', 'Client: All') ?><?php endif ?>
    <button class="btn sm">Filter</button></form>
  <?php if (!$rows): echo empty_state('No QC requests match.'); else: ?>
  <div class="tbl-wrap"><table class="t"><tr><th>Req / Job</th><?php if (is_internal()): ?><th>Source</th><?php endif ?><?php if (role() !== 'client'): ?><th>Client</th><?php endif ?><?php if (role() !== 'vendor'): ?><th>Vendor</th><?php endif ?><th>PO</th><th>Scope</th><th class="num">SKUs / Qty</th><th>QC date</th><th>Status</th></tr>
  <?php foreach ($rows as $j): ?><tr class="<?= $sel && $sel['id'] == $j['id'] ? 'sel' : '' ?>">
    <td><a class="strong" href="<?= url('request', ['id' => $j['id']]) ?>"><?= h($j['req_no']) ?></a><?php if ($j['job_no']): ?><br><small class="mono"><?= h($j['job_no']) ?></small><?php endif ?></td>
    <?php if (is_internal()): ?><td><?= h($j['source']) ?></td><?php endif ?><?php if (role() !== 'client'): ?><td><?= h($j['client_code']) ?></td><?php endif ?><?php if (role() !== 'vendor'): ?><td><?= h($j['vendor_name']) ?></td><?php endif ?>
    <td class="mono"><?= h($j['po_no']) ?></td><td><?= h(scope_label($j)) ?></td><td class="num"><?= (int)$j['skus'] ?> / <?= num($j['qty']) ?></td>
    <td><?= fdate($j['scheduled_date'] ?: $j['requested_date'], 'd M') ?><?= !$j['scheduled_date'] && $j['requested_date'] ? ' <small>(req.)</small>' : '' ?></td>
    <td><?= badge($j['status']) ?><?php if (is_role('super_admin', 'back_office') && in_array($j['status'], ['REQUESTED', 'DRAFT'], true)): ?> <a class="btn sm" href="<?= url('requests', ['id' => $j['id'], 'st' => $st]) ?>">Schedule</a><?php endif ?></td></tr>
  <?php endforeach ?></table></div><?php endif ?>
</div>
<?php if ($schedulable): $atts = all('SELECT * FROM attachments WHERE job_id=?', [$sel['id']]); $nsku = (int)val('SELECT COUNT(*) FROM qc_items WHERE job_id=?', [$sel['id']]); ?>
<div class="card"><div class="card-h"><h2>Schedule request <?= h($sel['req_no']) ?></h2><span class="pill">From <?= h(strtolower($sel['source'])) ?></span></div>
  <div class="kv mb"><div><small>Client</small><b><?= h($sel['client_code']) ?></b></div><div><small>Vendor</small><b><?= h($sel['vendor_name']) ?></b></div>
    <div><small>PO</small><b><?= h($sel['po_no']) ?></b></div><div><small>Scope</small><b><?= h(scope_label($sel)) ?> · <?= $nsku ?> SKUs</b></div>
    <div><small>Requested date</small><b><?= fdate($sel['requested_date']) ?></b></div><div><small>Attachments</small><b><?= $atts ? h(implode(', ', array_column($atts, 'name'))) : '—' ?></b></div></div>
  <form method="post" action="<?= url('job-action') ?>"><?= csrf_field() ?><input type="hidden" name="act" value="schedule"><input type="hidden" name="id" value="<?= $sel['id'] ?>">
    <div class="fr f"><div><label>Confirmed QC date</label><input type="date" name="date" required value="<?= h($sel['scheduled_date'] ?: $sel['requested_date']) ?>"></div>
      <div><label>Time slot</label><input type="time" name="time" value="10:30"></div></div>
    <div class="fr f"><div><label>QC manager</label><?= sel('manager_id', user_opts('qc_manager'), $sel['manager_id']) ?></div>
      <div><label>Priority</label><?= sel('priority', ['Normal' => 'Normal', 'High' => 'High', 'Urgent' => 'Urgent'], $sel['priority']) ?></div></div>
    <div class="f"><label>QC location</label><input type="text" name="location" value="<?= h($sel['location']) ?>"></div>
    <button class="btn pri block">CONFIRM &amp; SCHEDULE</button>
    <p class="muted" style="font-size:12px;text-align:center">Status → SCHEDULED · vendor &amp; client notified</p>
  </form>
  <div class="actions"><?= btn('Open full request', url('request', ['id' => $sel['id']])) ?><?= btn('Edit', url('request-new', ['id' => $sel['id']])) ?></div>
</div><?php endif ?>
</div>
<?php layout_end();
