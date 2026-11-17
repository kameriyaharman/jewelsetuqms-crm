<?php
$canEdit = is_role('super_admin', 'back_office');
if (is_post() && $canEdit) {
    $id = (int)post('id');
    $d = ['code' => strtoupper(trim((string)post('code'))), 'name' => trim((string)post('name')), 'contact' => post('contact'), 'email' => post('email'),
        'phone' => post('phone'), 'ho_email' => post('ho_email'), 'address' => post('address'), 'location' => post('location'), 'status' => post('status', 'ACTIVE')];
    if (!$d['code'] || !$d['name']) { flash('Vendor code and name are required.', 'err'); back(); }
    if (one('SELECT id FROM vendors WHERE code=? AND id<>?', [$d['code'], $id])) { flash('Vendor code already exists.', 'err'); back(); }
    if ($id) update('vendors', $d, 'id=?', [$id]); else { $d['created_at'] = now(); $id = insert('vendors', $d); }
    audit('vendor', $id, $id ? 'UPDATE' : 'CREATE');
    q('DELETE FROM client_vendor WHERE vendor_id=?', [$id]);
    foreach ((array)post('clients', []) as $cid) insert('client_vendor', ['client_id' => (int)$cid, 'vendor_id' => $id]);
    if (post('create_login') && !one('SELECT id FROM users WHERE UPPER(emp_code)=?', [$d['code']])) {
        $pw = (string)(post('login_pw') ?: 'Vendor@' . rand(1000, 9999));
        insert('users', ['name' => $d['name'], 'email' => $d['email'], 'emp_code' => $d['code'], 'password_hash' => password_hash($pw, PASSWORD_DEFAULT),
            'role' => 'vendor', 'vendor_id' => $id, 'status' => 'ACTIVE', 'created_at' => now()]);
        flash("Vendor portal login created: {$d['code']} / $pw");
    }
    flash('Vendor saved.'); redirect('vendors', ['id' => $id]);
}
$qs = trim((string)get('q')); $cf = (int)get('client');
$where = []; $p = [];
if (role() === 'client') { $where[] = 'v.id IN (SELECT vendor_id FROM client_vendor WHERE client_id=?)'; $p[] = user()['client_id']; }
if ($cf) { $where[] = 'v.id IN (SELECT vendor_id FROM client_vendor WHERE client_id=?)'; $p[] = $cf; }
if ($qs) { $where[] = '(v.name LIKE ? OR v.code LIKE ?)'; $p[] = "%$qs%"; $p[] = "%$qs%"; }
$rows = all('SELECT v.* FROM vendors v' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY v.name', $p);
$vstat = [];
foreach (approved_items() as $i) { $vstat[$i['vendor_id']][] = $i; }
$sel = get('id') ? one('SELECT * FROM vendors WHERE id=?', [(int)get('id')]) : null;
if ($sel && role() === 'client' && !one('SELECT 1 FROM client_vendor WHERE client_id=? AND vendor_id=?', [user()['client_id'], $sel['id']])) $sel = null;
$isNew = get('new') !== null && $canEdit;
$clients = all('SELECT id, code, name FROM clients ORDER BY code');
layout_start(role() === 'client' ? 'My Vendors' : 'Vendor Management', 'vendors', $canEdit ? btn(icon('plus') . ' Add Vendor', url('vendors', ['new' => 1]), 'pri') : '');
?>
<div class="grid g-82">
<div class="card">
  <form class="filters"><input type="search" name="q" placeholder="Search vendor…" value="<?= h($qs) ?>">
  <?php if (role() !== 'client'): ?><?= sel('client', array_column($clients, 'code', 'id'), $cf, 'onchange="this.form.submit()"', 'Client: All') ?><?php endif ?><button class="btn sm">Search</button></form>
  <div class="tbl-wrap"><table class="t"><tr><th>Vendor</th><th>Mapped clients</th><th>Location</th><th class="num">Total QC</th><th class="num">Rejection %</th><th>Status</th></tr>
  <?php foreach ($rows as $v): $s = sum_items($vstat[$v['id']] ?? []);
    $mc = role() === 'client' ? [val('SELECT code FROM clients WHERE id=?', [user()['client_id']])] : array_column(all('SELECT c.code FROM client_vendor m JOIN clients c ON c.id=m.client_id WHERE m.vendor_id=?', [$v['id']]), 'code'); ?>
  <tr class="<?= $sel && $sel['id'] == $v['id'] ? 'sel' : '' ?>"><td><span class="avatar"><?= h(substr($v['name'], 0, 1)) ?></span><a class="strong" href="<?= url('vendors', ['id' => $v['id']]) ?>"><?= h($v['name']) ?></a></td>
    <td><?= h(implode(', ', $mc)) ?></td><td><?= h($v['location']) ?></td><td class="num"><?= $s['n'] ?></td>
    <td class="num <?= $s['rate'] > 5 ? 'bad-t' : '' ?>"><?= number_format($s['rate'], 1) ?>%</td><td><?= badge($v['status']) ?></td></tr><?php endforeach ?>
  </table></div>
</div>
<div>
<?php if ($sel && !get('edit')):
  $items = $vstat[$sel['id']] ?? [];
  if (role() === 'client') $items = array_filter($items, fn($i) => $i['client_id'] == user()['client_id']);
  $s = sum_items($items); $recent = array_slice(array_reverse($items), 0, 5); ?>
<div class="card">
  <div class="card-h"><h2><span class="avatar"><?= h(substr($sel['name'], 0, 1)) ?></span><?= h($sel['name']) ?></h2><?= badge($sel['status']) ?></div>
  <p class="muted" style="margin-top:-6px">Mapped clients: <?= h(implode(', ', array_column(all('SELECT c.code FROM client_vendor m JOIN clients c ON c.id=m.client_id WHERE m.vendor_id=?', [$sel['id']]), 'code'))) ?> · <?= h($sel['location']) ?></p>
  <div class="kv mb"><div><small>Factory address</small><b><?= h($sel['address']) ?></b></div><div><small>Contact person</small><b><?= h($sel['contact']) ?></b></div>
    <div><small>Email</small><b><?= h($sel['email']) ?></b></div><div><small>Head office email</small><b><?= h($sel['ho_email']) ?></b></div></div>
  <div class="grid g4 mb" style="gap:8px;text-align:center"><div class="card" style="padding:10px"><small>Total QC</small><div class="big"><?= $s['n'] ?></div></div>
    <div class="card" style="padding:10px;margin:0"><small>Pass</small><div class="big ok-t"><?= $s['pass'] ?></div></div><div class="card" style="padding:10px;margin:0"><small>Fail</small><div class="big bad-t"><?= $s['fail'] ?></div></div>
    <div class="card" style="padding:10px;margin:0"><small>Rejection</small><div class="big"><?= number_format($s['rate'], 1) ?>%</div></div></div>
  <div class="actions mb"><?= btn('View QC history', url('reports', ['vendor' => $sel['id']])) ?><?= btn('Defect analysis', url('analytics', ['vendor' => $sel['id']])) ?>
    <?php if ($canEdit): ?><?= btn('Edit / portal login', url('vendors', ['id' => $sel['id'], 'edit' => 1]), 'pri') ?><?php endif ?></div>
  <h2 class="mb">Recent QC</h2>
  <?php if (!$recent): echo empty_state('No approved reports yet.'); else: ?>
  <table class="t"><tr><th>Report</th><th>Date</th><th>SKU</th><th>Result</th></tr>
  <?php foreach ($recent as $i): ?><tr><td><a class="mono" href="<?= url('report', ['id' => $i['id']]) ?>"><?= h($i['report_no']) ?></a></td><td><?= fdate($i['approved_at'], 'd M') ?></td><td class="mono"><?= h($i['sku']) ?></td><td><?= badge($i['final_result']) ?></td></tr><?php endforeach ?></table><?php endif ?>
</div>
<?php elseif ($canEdit && ($isNew || ($sel && get('edit')))): $v = $sel ?? []; $mc = $sel ? array_column(all('SELECT client_id FROM client_vendor WHERE vendor_id=?', [$sel['id']]), 'client_id') : [];
  $hasLogin = $sel ? one('SELECT * FROM users WHERE vendor_id=?', [$sel['id']]) : null; ?>
<div class="card"><h2 class="mb"><?= $sel ? 'Edit vendor' : 'Add vendor' ?></h2>
<form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($v['id'] ?? 0) ?>">
  <div class="fr f"><div><label>Vendor code (portal login)</label><input type="text" name="code" required value="<?= h($v['code'] ?? 'VND-') ?>"></div><div><label>Status</label><?= sel('status', ['ACTIVE' => 'Active', 'INACTIVE' => 'Inactive'], $v['status'] ?? 'ACTIVE') ?></div></div>
  <div class="f"><label>Vendor / factory name</label><input type="text" name="name" required value="<?= h($v['name'] ?? '') ?>"></div>
  <div class="f"><label>Contact person</label><input type="text" name="contact" value="<?= h($v['contact'] ?? '') ?>"></div>
  <div class="fr f"><div><label>Email (reports go here)</label><input type="email" name="email" value="<?= h($v['email'] ?? '') ?>"></div><div><label>Phone</label><input type="text" name="phone" value="<?= h($v['phone'] ?? '') ?>"></div></div>
  <div class="f"><label>Head office email</label><input type="email" name="ho_email" value="<?= h($v['ho_email'] ?? '') ?>"></div>
  <div class="f"><label>Factory address</label><textarea name="address"><?= h($v['address'] ?? '') ?></textarea></div>
  <div class="f"><label>Location (area, city)</label><input type="text" name="location" value="<?= h($v['location'] ?? '') ?>"></div>
  <div class="f"><label>Mapped clients</label><select name="clients[]" multiple size="4"><?php foreach ($clients as $c): ?><option value="<?= $c['id'] ?>" <?= in_array($c['id'], $mc) ? 'selected' : '' ?>><?= h($c['code'] . ' — ' . $c['name']) ?></option><?php endforeach ?></select></div>
  <?php if ($hasLogin): ?><div class="note rose f">Portal login exists: <b><?= h($hasLogin['emp_code']) ?></b> (<?= h($hasLogin['status']) ?>). Manage in Team.</div>
  <?php else: ?><label class="chk f"><input type="checkbox" name="create_login" value="1"> Create vendor portal login (login = vendor code)</label>
  <div class="f"><label>Initial password (blank = auto)</label><input type="text" name="login_pw"></div><?php endif ?>
  <button class="btn pri block">SAVE VENDOR</button>
</form></div>
<?php else: ?><div class="card"><?= empty_state('Select a vendor to see its profile and quality history.') ?></div><?php endif ?>
</div></div>
<?php layout_end();
