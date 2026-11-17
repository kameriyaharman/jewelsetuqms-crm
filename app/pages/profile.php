<?php
$u = user();
if (is_post()) {
    $cur = (string)post('current'); $new = (string)post('new');
    if (!password_verify($cur, $u['password_hash'])) flash('Current password is wrong.', 'err');
    elseif (strlen($new) < 6) flash('New password must be at least 6 characters.', 'err');
    else { q('UPDATE users SET password_hash=? WHERE id=?', [password_hash($new, PASSWORD_DEFAULT), $u['id']]); flash('Password changed.'); }
    redirect(role() === 'inspector' ? 'app/profile' : 'profile');
}
$vendor = $u['vendor_id'] ? one('SELECT * FROM vendors WHERE id=?', [$u['vendor_id']]) : null;
$client = $u['client_id'] ? one('SELECT * FROM clients WHERE id=?', [$u['client_id']]) : null;
layout_start('Profile', 'profile'); ?>
<div class="grid g2">
<div class="card"><h2 class="mb">My account</h2>
  <div class="kv"><div><small>Name</small><b><?= h($u['name']) ?></b></div><div><small>Role</small><b><?= h(ROLES[$u['role']]) ?></b></div>
  <div><small>Email</small><b><?= h($u['email']) ?></b></div><div><small>Login code</small><b><?= h($u['emp_code'] ?: '—') ?></b></div></div>
  <?php if ($vendor): ?><div class="hr"></div><h2 class="mb">Factory</h2>
  <div class="kv"><div><small>Vendor</small><b><?= h($vendor['name']) ?></b></div><div><small>Address</small><b><?= h($vendor['address']) ?></b></div>
  <div><small>Contact</small><b><?= h($vendor['contact']) ?></b></div><div><small>Mapped clients</small><b><?= h(implode(', ', array_column(all('SELECT c.code FROM client_vendor m JOIN clients c ON c.id=m.client_id WHERE m.vendor_id=?', [$vendor['id']]), 'code'))) ?></b></div></div><?php endif ?>
  <?php if ($client): ?><div class="hr"></div><h2 class="mb">Company</h2>
  <div class="kv"><div><small>Client</small><b><?= h($client['name']) ?></b></div><div><small>Head office email</small><b><?= h($client['ho_email']) ?></b></div></div><?php endif ?>
</div>
<div class="card"><h2 class="mb">Change password</h2>
<form method="post"><?= csrf_field() ?>
  <div class="f"><label>Current password</label><input type="password" name="current" required></div>
  <div class="f"><label>New password</label><input type="password" name="new" required minlength="6"></div>
  <button class="btn pri">Update password</button></form></div>
</div>
<?php layout_end();
