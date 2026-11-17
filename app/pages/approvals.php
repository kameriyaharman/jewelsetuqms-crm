<?php
[$sc, $sp] = job_scope('j');
$r = role();
$holdWhere = $r === 'client' ? " AND i.hold_for='client'" : ($r === 'vendor' ? " AND i.hold_for='vendor'" : '');
$holds = all("SELECT i.*, j.po_no, j.job_no, j.id jid, c.code client_code, c.name client_name, v.name vendor_name FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id JOIN clients c ON c.id=j.client_id JOIN vendors v ON v.id=j.vendor_id
    WHERE $sc AND i.hold_status='PENDING' $holdWhere ORDER BY i.hold_at", $sp);
$conc = $r === 'vendor' ? [] : all("SELECT i.*, j.po_no, j.id jid, c.code client_code, v.name vendor_name, u.name mgr FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id JOIN clients c ON c.id=j.client_id JOIN vendors v ON v.id=j.vendor_id LEFT JOIN users u ON u.id=i.approved_by
    WHERE $sc AND i.concession_status='PENDING' ORDER BY i.approved_at", $sp);
$hist = all("SELECT i.*, j.po_no, v.name vendor_name, c.code client_code FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id JOIN vendors v ON v.id=j.vendor_id JOIN clients c ON c.id=j.client_id
    WHERE $sc AND ((i.hold_status IN ('APPROVED','REJECTED')$holdWhere) OR (i.concession_status IN ('APPROVED','REJECTED') " . ($r === 'vendor' ? 'AND 1=0' : '') . ")) ORDER BY COALESCE(i.concession_at, i.hold_resp_at) DESC LIMIT 20", $sp);
$sel = (int)get('id');
$title = match ($r) { 'client' => 'Approvals — holds & concessions', 'vendor' => 'Designs held for your approval', default => 'Holds & Concessions' };
layout_start($title, 'approvals', '', $r === 'vendor' ? 'Inspector paused these SKUs and needs your confirmation before continuing' : 'SKUs held by inspectors for your decision, and CONDITIONAL lots waiting for deviation approval');
$canAct = in_array($r, ['client', 'vendor', 'super_admin'], true);
?>
<div class="grid <?= $conc ? 'g2' : '' ?>">
<div class="card"><div class="card-h"><h2>⏸ SKU hold for approval (<?= count($holds) ?>)</h2></div>
<?php if (!$holds) echo empty_state('No SKU is waiting for ' . ($r === 'vendor' || $r === 'client' ? 'your' : 'a') . ' decision.'); ?>
<?php foreach ($holds as $h): ?>
  <div class="note rose" style="margin-bottom:12px;color:var(--tx)">
    <div style="display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap"><b class="mono"><?= h($h['sku']) ?> · <?= h($h['product']) ?></b><span><?= badge('HOLD') ?> <small>for <?= h($h['hold_for']) ?></small></span></div>
    <p style="margin:6px 0;font-size:12.5px" class="muted"><?= h($r === 'vendor' ? $h['client_code'] : $h['vendor_name']) ?> · PO <?= h($h['po_no']) ?> · <?= h($h['job_no']) ?> · held by <?= h($h['hold_by']) ?> · <?= fdt($h['hold_at']) ?></p>
    <p style="margin:6px 0 10px;font-size:14px">“<?= h($h['hold_reason']) ?>”</p>
    <?php $ph = all('SELECT * FROM item_photos WHERE item_id=? ORDER BY id DESC LIMIT 4', [$h['id']]); if ($ph): ?><div class="photos" style="grid-template-columns:repeat(4,1fr);margin-bottom:10px"><?php foreach ($ph as $p): ?><figure><a target="_blank" href="<?= url('file', ['p' => $p['path']]) ?>"><img src="<?= url('file', ['p' => $p['path']]) ?>" style="height:70px"></a></figure><?php endforeach ?></div><?php endif ?>
    <?php if ($canAct && ($r === $h['hold_for'] || $r === 'super_admin')): ?>
    <form method="post" action="<?= url('job-action') ?>"><?= csrf_field() ?><input type="hidden" name="act" value="hold_respond"><input type="hidden" name="item_id" value="<?= $h['id'] ?>">
      <div class="f"><textarea name="remark" rows="2" placeholder="Your instruction to the inspector (e.g. Accept shade — proceed with QC)"></textarea></div>
      <div class="actions"><button class="btn pri" name="decision" value="APPROVE">✓ Approve — continue QC</button><button class="btn ruby" name="decision" value="REJECT">✕ Reject</button></div>
    </form><?php else: ?><small class="muted">Waiting for <?= h($h['hold_for']) ?> response.</small><?php endif ?>
  </div>
<?php endforeach ?>
</div>
<?php if ($conc): ?>
<div class="card"><div class="card-h"><h2>Concession approval needed (<?= count($conc) ?>)</h2></div>
<?php foreach ($conc as $c): $dev = jdec($c['deviation_json']); $open = !$sel || $sel == $c['id']; ?>
  <div class="card" style="margin:0 0 12px;box-shadow:none">
    <div class="card-h"><h2>Deviation request — <span class="mono"><?= h($c['report_no']) ?></span></h2><?= badge('CONDITIONAL') ?></div>
    <div class="kv mb"><div><small>Vendor</small><b><?= h($c['vendor_name']) ?></b></div><div><small>PO / SKU</small><b><?= h($c['po_no'] . ' · ' . $c['sku']) ?></b></div>
      <div><small>Inspected</small><b><?= num($c['inspected_qty']) ?> pcs</b></div><div><small>Raised by</small><b><?= h($c['mgr'] ?: 'QC Manager') ?></b></div>
      <div><small>Raised on</small><b><?= fdate($c['approved_at']) ?></b></div><div><small>Reply needed by</small><b><?= fdate($c['concession_due']) ?></b></div></div>
    <?php if ($dev): ?><label>What is outside specification</label><table class="t mb"><tr><th>Parameter</th><th>Spec</th><th>Actual</th><th>Deviation</th><th class="num">Pcs</th></tr>
      <?php foreach ($dev as $d): ?><tr><td><?= h($d['param']) ?></td><td><?= h($d['spec']) ?></td><td><?= h($d['actual']) ?></td><td class="bad-t"><?= h($d['dev']) ?></td><td class="num"><?= h($d['pcs']) ?></td></tr><?php endforeach ?></table><?php endif ?>
    <div class="note mb"><b>QC manager note:</b> <?= h($c['manager_note']) ?></div>
    <div class="actions mb"><?= btn('View report PDF', url('pdf', ['item' => $c['id']])) ?></div>
    <?php if (in_array($r, ['client', 'super_admin'], true)): ?>
    <form method="post" action="<?= url('job-action') ?>"><?= csrf_field() ?><input type="hidden" name="act" value="concession"><input type="hidden" name="item_id" value="<?= $c['id'] ?>">
      <div class="decision"><label><input type="radio" name="decision" value="APPROVE" checked> Approve deviation (concession)</label><label><input type="radio" name="decision" value="REJECT"> Reject — rework required</label></div>
      <div class="f"><label>Client remark</label><textarea name="remark" placeholder="Accept with 2″ extender added before dispatch."></textarea></div>
      <div class="f"><label>Approved by</label><input type="text" readonly value="<?= h(user()['name']) ?>"></div>
      <button class="btn pri block">SUBMIT DECISION</button>
      <p class="muted" style="font-size:12px">Decision, name &amp; time are printed on the PDF report and stored in the audit trail.</p></form><?php endif ?>
  </div>
<?php endforeach ?></div><?php endif ?>
</div>
<?php if ($hist): ?><div class="card mt"><h2 class="mb">Recent decisions</h2><table class="t"><tr><th>SKU / report</th><th><?= $r === 'vendor' ? 'Client' : 'Vendor' ?></th><th>Type</th><th>Decision</th><th>Remark</th><th>When</th></tr>
<?php foreach ($hist as $x): $isC = $x['concession_status'] && $x['concession_status'] !== 'PENDING'; ?><tr><td class="mono"><?= h($x['report_no'] ?: $x['sku']) ?></td><td><?= h($r === 'vendor' ? $x['client_code'] : $x['vendor_name']) ?></td><td><?= $isC ? 'Concession' : 'SKU hold (' . h($x['hold_for']) . ')' ?></td>
  <td><?= badge($isC ? $x['concession_status'] : $x['hold_status']) ?></td><td><?= h($isC ? $x['concession_remark'] : $x['hold_response']) ?></td><td><?= fdt($isC ? $x['concession_at'] : $x['hold_resp_at']) ?></td></tr><?php endforeach ?></table></div><?php endif ?>
<?php layout_end();
