<?php
$canEdit = is_role('super_admin', 'back_office');
if (is_post() && $canEdit) {
    $id = (int)post('id');
    $d = ['code' => strtoupper(trim((string)post('code'))), 'name' => trim((string)post('name')), 'contact' => post('contact'), 'email' => post('email'),
        'phone' => post('phone'), 'ho_email' => post('ho_email'), 'address' => post('address'), 'report_format' => post('report_format'),
        'aql_default' => post('aql_default') ?: '2.5', 'major_limit' => (float)(post('major_limit') ?: 2.5), 'status' => post('status', 'ACTIVE')];
    if (!$d['code'] || !$d['name']) { flash('Code and name are required.', 'err'); back(); }
    if (one('SELECT id FROM clients WHERE code=? AND id<>?', [$d['code'], $id])) { flash('Client code already exists.', 'err'); back(); }
    if ($id) { update('clients', $d, 'id=?', [$id]); audit('client', $id, 'UPDATE'); }
    else { $d['created_at'] = now(); $id = insert('clients', $d); audit('client', $id, 'CREATE'); }
    q('DELETE FROM client_vendor WHERE client_id=?', [$id]);
    foreach ((array)post('vendors', []) as $vid) insert('client_vendor', ['client_id' => $id, 'vendor_id' => (int)$vid]);
    if (post('create_login') && $d['email'] && !one('SELECT id FROM users WHERE LOWER(email)=LOWER(?)', [$d['email']])) {
        $pw = (string)(post('login_pw') ?: 'Client@' . rand(1000, 9999));
        insert('users', ['name' => $d['contact'] ?: $d['name'], 'email' => $d['email'], 'password_hash' => password_hash($pw, PASSWORD_DEFAULT),
            'role' => 'client', 'client_id' => $id, 'status' => 'ACTIVE', 'created_at' => now()]);
        flash("Portal login created: {$d['email']} / $pw");
    }
    flash('Client saved.');
    redirect('clients', ['id' => $id]);
}
$qs = trim((string)get('q'));
$rows = all("SELECT c.*, (SELECT COUNT(*) FROM client_vendor m WHERE m.client_id=c.id) nv, (SELECT COUNT(*) FROM qc_jobs j WHERE j.client_id=c.id) nq,
    (SELECT COUNT(*) FROM users u WHERE u.client_id=c.id AND u.status='ACTIVE') nl FROM clients c" . ($qs ? ' WHERE c.name LIKE ? OR c.code LIKE ?' : '') . ' ORDER BY c.status, c.code', $qs ? ["%$qs%", "%$qs%"] : []);
$sel = get('id') ? one('SELECT * FROM clients WHERE id=?', [(int)get('id')]) : null;
$isNew = get('new') !== null;
$vendors = all('SELECT id, name FROM vendors ORDER BY name');
$mapped = $sel ? array_column(all('SELECT vendor_id FROM client_vendor WHERE client_id=?', [$sel['id']]), 'vendor_id') : [];
layout_start('Client Management', 'clients', $canEdit ? btn(icon('plus') . ' Add Client', url('clients', ['new' => 1]), 'pri') : '');
?>
<div class="grid g-82">
<div>
<div class="card">
  <form class="filters"><input type="search" name="q" placeholder="Search client…" value="<?= h($qs) ?>"><button class="btn sm">Search</button></form>
  <div class="tbl-wrap"><table class="t"><tr><th>Client</th><th>Company</th><th>Contact</th><th class="num">Mapped vendors</th><th class="num">Total QC</th><th>Portal login</th><th>Status</th></tr>
  <?php foreach ($rows as $c): ?><tr class="<?= $sel && $sel['id'] == $c['id'] ? 'sel' : '' ?>">
    <td><span class="avatar"><?= h(substr($c['code'], 0, 3)) ?></span><a class="strong" href="<?= url('clients', ['id' => $c['id']]) ?>"><?= h($c['code']) ?></a></td>
    <td><?= h($c['name']) ?></td><td><?= h($c['contact']) ?></td><td class="num"><?= (int)$c['nv'] ?></td><td class="num"><?= (int)$c['nq'] ?></td>
    <td><?= $c['nl'] ? badge('ACTIVE') : '<span class="muted">—</span>' ?></td><td><?= badge($c['status']) ?></td></tr><?php endforeach ?>
  </table></div>
</div>
<?php if ($sel):
  $s = sum_items(approved_items(['client_id' => $sel['id'], 'from' => date('Y-m-01')]));
  $all = sum_items(approved_items(['client_id' => $sel['id']])); ?>
<div class="card"><div class="card-h"><h2>Client profile — <?= h($sel['code']) ?></h2><a href="<?= url('requests', ['client' => $sel['id']]) ?>">Open QC requests</a></div>
  <div class="kv"><div><small>Head office email (auto-CC)</small><b><?= h($sel['ho_email']) ?></b></div><div><small>Report format</small><b><?= h($sel['report_format']) ?></b></div>
  <div><small>Default QC criteria</small><b>AQL <?= h($sel['aql_default']) ?> · Major ≤ <?= h($sel['major_limit']) ?>%</b></div>
  <div><small>Vendors</small><b><?= h(implode(', ', array_column(all('SELECT v.name FROM client_vendor m JOIN vendors v ON v.id=m.vendor_id WHERE m.client_id=?', [$sel['id']]), 'name')) ?: '—') ?></b></div>
  <div><small>QC this month</small><b><?= $s['n'] ?></b></div><div><small>Rejection % (all time)</small><b><?= number_format($all['rate'], 1) ?>%</b></div></div>
</div><?php endif ?>
</div>
<?php if ($canEdit && ($sel || $isNew)): $c = $sel ?? []; ?>
<div class="card"><h2 class="mb"><?= $sel ? 'Edit client' : 'Add client' ?></h2>
<form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($c['id'] ?? 0) ?>">
  <div class="f"><label>Client short code</label><input type="text" name="code" required value="<?= h($c['code'] ?? '') ?>" placeholder="KP"></div>
  <div class="f"><label>Company name</label><input type="text" name="name" required value="<?= h($c['name'] ?? '') ?>"></div>
  <div class="f"><label>Contact person</label><input type="text" name="contact" value="<?= h($c['contact'] ?? '') ?>"></div>
  <div class="fr f"><div><label>Email</label><input type="email" name="email" value="<?= h($c['email'] ?? '') ?>"></div><div><label>Phone</label><input type="text" name="phone" value="<?= h($c['phone'] ?? '') ?>"></div></div>
  <div class="f"><label>Head office email (auto-CC on reports)</label><input type="email" name="ho_email" value="<?= h($c['ho_email'] ?? '') ?>"></div>
  <div class="f"><label>Address</label><textarea name="address"><?= h($c['address'] ?? '') ?></textarea></div>
  <div class="fr f"><div><label>Report format</label><input type="text" name="report_format" value="<?= h($c['report_format'] ?? 'Standard') ?>"></div>
    <div><label>Default AQL</label><input type="text" name="aql_default" value="<?= h($c['aql_default'] ?? '2.5') ?>"></div></div>
  <div class="fr f"><div><label>Major defect limit % (non-AQL)</label><input type="number" step="0.1" name="major_limit" value="<?= h($c['major_limit'] ?? '2.5') ?>"></div>
    <div><label>Status</label><?= sel('status', ['ACTIVE' => 'Active', 'INACTIVE' => 'Inactive'], $c['status'] ?? 'ACTIVE') ?></div></div>
  <div class="f"><label>Mapped vendors</label><select name="vendors[]" multiple size="5"><?php foreach ($vendors as $v): ?><option value="<?= $v['id'] ?>" <?= in_array($v['id'], $mapped) ? 'selected' : '' ?>><?= h($v['name']) ?></option><?php endforeach ?></select><small>Ctrl/Cmd-click to select several</small></div>
  <label class="chk f"><input type="checkbox" name="create_login" value="1" onchange="lp.style.display=this.checked?'block':'none'"> Create client portal login (uses email above)</label>
  <div class="f" id="lp" style="display:none"><label>Initial password (blank = auto)</label><input type="text" name="login_pw"></div>
  <button class="btn pri block">SAVE CLIENT</button>
</form></div>
<?php endif ?>
</div>
<?php layout_end();
