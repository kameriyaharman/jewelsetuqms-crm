<?php
$canEdit = is_role('super_admin');
if (is_post() && $canEdit) {
    $id = (int)post('id');
    $role = (string)post('role');
    if (!isset(ROLES[$role])) { flash('Invalid role', 'err'); back(); }
    $d = ['name' => trim((string)post('name')), 'email' => trim((string)post('email')) ?: null, 'emp_code' => strtoupper(trim((string)post('emp_code'))) ?: null, 'role' => $role,
        'phone' => post('phone'), 'location' => post('location'), 'area' => post('area'), 'joined_on' => post('joined_on') ?: null, 'status' => post('status', 'ACTIVE'),
        'client_id' => $role === 'client' ? ((int)post('client_id') ?: null) : null, 'vendor_id' => $role === 'vendor' ? ((int)post('vendor_id') ?: null) : null];
    if (!$d['name'] || (!$d['email'] && !$d['emp_code'])) { flash('Name and email or login code are required.', 'err'); back(); }
    if ($role === 'client' && !$d['client_id']) { flash('Select the client for this login.', 'err'); back(); }
    if ($role === 'vendor' && !$d['vendor_id']) { flash('Select the vendor for this login.', 'err'); back(); }
    if ($d['email'] && one('SELECT id FROM users WHERE LOWER(email)=LOWER(?) AND id<>?', [$d['email'], $id])) { flash('Email already used.', 'err'); back(); }
    if ($d['emp_code'] && one('SELECT id FROM users WHERE UPPER(emp_code)=? AND id<>?', [$d['emp_code'], $id])) { flash('Login code already used.', 'err'); back(); }
    if ($id === (int)user()['id'] && ($d['status'] !== 'ACTIVE' || $role !== 'super_admin')) { flash('You cannot deactivate or demote yourself.', 'err'); back(); }
    if (post('password')) $d['password_hash'] = password_hash((string)post('password'), PASSWORD_DEFAULT);
    if ($id) update('users', $d, 'id=?', [$id]);
    else { if (empty($d['password_hash'])) { $pw = 'Js@' . rand(10000, 99999); $d['password_hash'] = password_hash($pw, PASSWORD_DEFAULT); flash("Initial password: $pw"); } $d['created_at'] = now(); $id = insert('users', $d); }
    audit('user', $id, 'SAVE');
    flash('Team member saved.'); redirect('team', ['id' => $id]);
}
$f = (string)get('f', 'internal');
$where = match ($f) { 'inspector' => "role='inspector'", 'qc_manager' => "role='qc_manager'", 'back_office' => "role='back_office'", 'portal' => "role IN ('client','vendor')", 'all' => '1=1', default => "role IN ('super_admin','back_office','qc_manager','inspector')" };
$rows = all("SELECT u.*, c.code ccode, v.name vname FROM users u LEFT JOIN clients c ON c.id=u.client_id LEFT JOIN vendors v ON v.id=u.vendor_id WHERE $where ORDER BY u.role, u.emp_code, u.name");
$sel = get('id') ? one('SELECT * FROM users WHERE id=?', [(int)get('id')]) : null;
$isNew = get('new') !== null && $canEdit;
layout_start('Team Management', 'team', $canEdit ? btn(icon('plus') . ' Add team member', url('team', ['new' => 1]), 'pri') : '', 'Add team members with roles and areas; track completed jobs, report time, returns and approval rate');
$tab = fn($k, $l) => '<a class="' . ($f === $k ? 'on' : '') . '" href="' . url('team', ['f' => $k]) . '">' . $l . '</a>';
?>
<div class="grid g-73">
<div class="card">
  <div class="tabs"><?= $tab('internal', 'All staff') ?><?= $tab('inspector', 'Inspectors') ?><?= $tab('qc_manager', 'Managers') ?><?= $tab('back_office', 'Back office') ?><?= $tab('portal', 'Client / vendor logins') ?></div>
  <div class="tbl-wrap"><table class="t"><tr><th>Employee ID / login</th><th>Name</th><th>Role</th><th>Location / link</th><th>Last login</th><th>Status</th></tr>
  <?php foreach ($rows as $u): ?><tr class="<?= $sel && $sel['id'] == $u['id'] ? 'sel' : '' ?>"><td class="mono"><a href="<?= url('team', ['f' => $f, 'id' => $u['id']]) ?>"><?= h($u['emp_code'] ?: $u['email']) ?></a></td>
    <td><span class="avatar"><?= h(initials($u['name'])) ?></span><?= h($u['name']) ?></td><td><?= badge(strtoupper(ROLES[$u['role']]), strtoupper(ROLES[$u['role']])) ?></td>
    <td><?= h($u['ccode'] ?: ($u['vname'] ?: $u['location'])) ?></td><td><?= $u['last_login'] ? fdt($u['last_login']) : '—' ?></td><td><?= badge($u['status']) ?></td></tr><?php endforeach ?></table></div>
  <?php $ins = all("SELECT * FROM users WHERE role='inspector' AND status='ACTIVE' ORDER BY emp_code"); if ($ins): ?>
  <h2 class="mb mt">Team this month</h2>
  <table class="t"><tr><th>Inspector</th><th class="num">Done</th><th class="num">Avg time</th><th class="num">Return %</th><th class="num">1st-time approval</th></tr>
  <?php foreach ($ins as $u): $k = inspector_kpis((int)$u['id'], date('Y-m-01')); ?><tr><td><?= h($u['emp_code'] . ' ' . explode(' ', $u['name'])[0]) ?></td><td class="num"><?= $k['done'] ?></td><td class="num"><?= number_format($k['avg'], 1) ?> h</td><td class="num"><?= round($k['ret']) ?>%</td><td class="num"><?= round($k['first']) ?>%</td></tr><?php endforeach ?></table><?php endif ?>
</div>
<div>
<?php if ($sel && $sel['role'] === 'inspector' && !get('edit')): $k = inspector_kpis((int)$sel['id']); ?>
<div class="card"><div class="card-h"><h2>Inspector performance</h2><span><?= h($sel['emp_code']) ?></span></div>
  <div class="grid g3" style="gap:8px;text-align:center">
    <div><small>Assigned QC</small><div class="big"><?= $k['assigned'] ?></div></div><div><small>Completed</small><div class="big"><?= $k['done'] ?></div></div><div><small>Avg turnaround</small><div class="big"><?= number_format($k['avg'], 1) ?> h</div></div>
    <div><small>Return rate</small><div class="big"><?= round($k['ret']) ?>%</div></div><div><small>First-time approval</small><div class="big ok-t"><?= round($k['first']) ?>%</div></div><div><small>Pending</small><div class="big"><?= $k['pending'] ?></div></div>
  </div></div><?php endif ?>
<?php if ($sel && !get('edit')): ?>
<div class="card"><div class="card-h"><h2>Profile — <?= h($sel['emp_code'] ?: $sel['name']) ?></h2><?php if ($canEdit): ?><a href="<?= url('team', ['f' => $f, 'id' => $sel['id'], 'edit' => 1]) ?>">Edit</a><?php endif ?></div>
  <div class="kv"><div><small>Name</small><b><?= h($sel['name']) ?></b></div><div><small>Mobile</small><b><?= h($sel['phone'] ?: '—') ?></b></div><div><small>Email</small><b><?= h($sel['email'] ?: '—') ?></b></div>
  <div><small>Role</small><b><?= h(ROLES[$sel['role']]) ?></b></div><div><small>Location</small><b><?= h($sel['location'] ?: '—') ?></b></div><div><small>Joining date</small><b><?= fdate($sel['joined_on']) ?></b></div>
  <div><small>Assigned area</small><b><?= h($sel['area'] ?: '—') ?></b></div><div><small>Status</small><b><?= badge($sel['status']) ?></b></div></div></div>
<?php elseif ($canEdit && ($isNew || ($sel && get('edit')))): $u = $sel ?? ['role' => get('role', 'inspector')]; ?>
<div class="card"><h2 class="mb"><?= $sel ? 'Edit member' : 'Add team member / portal login' ?></h2>
<form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($u['id'] ?? 0) ?>">
  <div class="f"><label>Role</label><?= sel('role', ROLES, $u['role'], 'id="rolesel" onchange="tg()"') ?></div>
  <div class="f"><label>Name</label><input type="text" name="name" required value="<?= h($u['name'] ?? '') ?>"></div>
  <div class="fr f"><div><label>Employee ID / login code</label><input type="text" name="emp_code" value="<?= h($u['emp_code'] ?? '') ?>" placeholder="JS-014 / VND-XYZ"></div><div><label>Email</label><input type="email" name="email" value="<?= h($u['email'] ?? '') ?>"></div></div>
  <div class="f" id="cl"><label>Client (for client login)</label><?= sel('client_id', array_column(all('SELECT id, code FROM clients ORDER BY code'), 'code', 'id'), $u['client_id'] ?? '', '', 'Select') ?></div>
  <div class="f" id="vl"><label>Vendor (for vendor login)</label><?= sel('vendor_id', array_column(all('SELECT id, name FROM vendors ORDER BY name'), 'name', 'id'), $u['vendor_id'] ?? '', '', 'Select') ?></div>
  <div class="fr f"><div><label>Mobile</label><input type="text" name="phone" value="<?= h($u['phone'] ?? '') ?>"></div><div><label>Location</label><input type="text" name="location" value="<?= h($u['location'] ?? '') ?>"></div></div>
  <div class="fr f"><div><label>Assigned area</label><input type="text" name="area" value="<?= h($u['area'] ?? '') ?>"></div><div><label>Joining date</label><input type="date" name="joined_on" value="<?= h($u['joined_on'] ?? '') ?>"></div></div>
  <div class="fr f"><div><label>Status</label><?= sel('status', ['ACTIVE' => 'Active', 'INACTIVE' => 'Inactive'], $u['status'] ?? 'ACTIVE') ?></div><div><label><?= $sel ? 'Reset password' : 'Password (blank = auto)' ?></label><input type="text" name="password"></div></div>
  <button class="btn pri block">SAVE</button></form>
<script>function tg(){const r=document.getElementById('rolesel').value;document.getElementById('cl').style.display=r==='client'?'':'none';document.getElementById('vl').style.display=r==='vendor'?'':'none';}tg();</script></div>
<?php else: ?><div class="card"><?= empty_state('Select a team member to see profile and performance.') ?></div><?php endif ?>
</div></div>
<?php layout_end();
