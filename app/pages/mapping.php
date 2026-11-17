<?php
$clients = all("SELECT * FROM clients ORDER BY status, code");
$vendors = all("SELECT * FROM vendors ORDER BY name");
if (is_post()) {
    q('DELETE FROM client_vendor');
    foreach ((array)post('m', []) as $key => $_) { [$c, $v] = array_map('intval', explode('_', $key)); insert('client_vendor', ['client_id' => $c, 'vendor_id' => $v]); }
    audit('mapping', 0, 'UPDATE');
    flash('Client–vendor mapping saved.'); redirect('mapping');
}
$map = []; foreach (all('SELECT * FROM client_vendor') as $m) $map[$m['client_id'] . '_' . $m['vendor_id']] = true;
$logins = all("SELECT u.*, c.code ccode, v.name vname FROM users u LEFT JOIN clients c ON c.id=u.client_id LEFT JOIN vendors v ON v.id=u.vendor_id WHERE u.role IN ('client','vendor') ORDER BY u.role, u.name");
$demo = one("SELECT j.*, c.code cc, v.name vn FROM qc_jobs j JOIN clients c ON c.id=j.client_id JOIN vendors v ON v.id=j.vendor_id WHERE j.status='REQUESTED' ORDER BY j.id DESC LIMIT 1");
layout_start('Client – Vendor Mapping', 'mapping', '', 'One vendor can work for many clients; each client sees only its own requests and reports');
?>
<div class="grid g2">
<div class="card"><div class="card-h"><h2>Client – Vendor mapping (many-to-many)</h2></div>
<form method="post"><?= csrf_field() ?>
<div class="tbl-wrap"><table class="t"><tr><th>Vendor \ Client</th><?php foreach ($clients as $c): ?><th style="text-align:center"><?= h($c['code']) ?></th><?php endforeach ?></tr>
<?php foreach ($vendors as $v): ?><tr><td class="strong"><?= h($v['name']) ?></td>
  <?php foreach ($clients as $c): $k = $c['id'] . '_' . $v['id']; ?><td style="text-align:center"><label class="chk" style="justify-content:center"><input type="checkbox" name="m[<?= $k ?>]" <?= isset($map[$k]) ? 'checked' : '' ?>></label></td><?php endforeach ?></tr><?php endforeach ?>
</table></div>
<div class="actions mt"><button class="btn pri">Save mapping</button></div></form>
<?php if ($demo): ?>
<div class="hr"></div><h2 class="mb">Visibility check — request <?= h($demo['req_no']) ?></h2>
<p class="muted" style="margin-top:-6px"><?= h($demo['vn']) ?> raised this for <?= h($demo['cc']) ?>. Row-level security on client_id + vendor_id:</p>
<div class="grid g2" style="gap:8px">
  <div class="note rose"><b>JEWELSETU Back Office</b><br>Queue · <?= h($demo['status']) ?></div>
  <div class="note rose"><b>Client <?= h($demo['cc']) ?></b><br>Visible in <?= h($demo['cc']) ?> portal</div>
  <?php foreach (all('SELECT c.code FROM client_vendor m JOIN clients c ON c.id=m.client_id WHERE m.vendor_id=? AND m.client_id<>?', [$demo['vendor_id'], $demo['client_id']]) as $o): ?>
  <div class="note ruby"><b>Client <?= h($o['code']) ?></b> (also maps <?= h($demo['vn']) ?>)<br>Not visible</div><?php endforeach ?>
  <div class="note ruby"><b>Other vendors</b><br>Not visible</div>
</div><?php endif ?>
</div>
<div class="card"><div class="card-h"><h2>Portal logins</h2><a href="<?= url('team', ['new' => 1]) ?>">Create login</a></div>
<div class="tbl-wrap"><table class="t"><tr><th>User</th><th>Portal</th><th>Linked to</th><th>Last login</th><th>Status</th></tr>
<?php foreach ($logins as $u): ?><tr><td class="mono"><?= h($u['role'] === 'vendor' ? $u['emp_code'] : $u['email']) ?></td><td><?= ucfirst($u['role']) ?></td>
  <td><?= h($u['ccode'] ?: $u['vname']) ?></td><td><?= $u['last_login'] ? fdt($u['last_login']) : '<span class="muted">never</span>' ?></td><td><?= badge($u['status']) ?></td></tr><?php endforeach ?>
</table></div></div>
</div>
<?php layout_end();
