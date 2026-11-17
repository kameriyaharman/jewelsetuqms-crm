<?php
$sa = is_role('super_admin');
$tab = (string)get('tab', 'toggles');
$TOG = ['enable_vendor_qc_request' => 'Vendors can raise QC requests from their portal', 'enable_client_qc_request' => 'Clients can raise QC requests from their portal',
    'require_defect_photo' => 'At least 1 defect photo when defect qty > 0', 'gps_on_inspection_start' => 'Log GPS + timestamp when inspection starts',
    'allow_job_split' => 'Split a large job across two inspectors', 'block_qty_mismatch' => 'Block save if Accepted + Rejected ≠ Inspected',
    'auto_email_on_approve' => 'Auto-email PO report to vendor, client HO & JEWELSETU when all SKUs are approved'];
if (is_post() && $sa) {
    $a = (string)post('a');
    if ($a === 'toggles') { foreach ($TOG as $k => $_) set_setting($k, post($k) ? '1' : '0'); audit('settings', 0, 'TOGGLES'); }
    if ($a === 'email') foreach (['mail_transport', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_secure', 'mail_from', 'mail_from_name', 'admin_email'] as $k) set_setting($k, trim((string)post($k)));
    if ($a === 'email' && post('smtp_pass') !== '') set_setting('smtp_pass', (string)post('smtp_pass'));
    if ($a === 'testmail') { require_once ROOT . '/app/mailer.php'; [$st, $er] = send_mail((string)post('to'), 'Q-TRACK test email', '<p>SMTP settings work ✓</p>'); flash("Test email: $st " . ($er ?? ''), $st === 'SENT' ? 'ok' : 'err'); redirect('settings', ['tab' => 'email']); }
    if ($a === 'template') foreach (['company_name', 'report_header', 'report_footer'] as $k) set_setting($k, (string)post($k));
    if ($a === 'add_defect') { $cid = (int)post('category_id'); if (post('new_cat')) $cid = insert('defect_categories', ['name' => trim((string)post('new_cat')), 'sort' => 99]);
        if ($cid && trim((string)post('name'))) insert('defect_master', ['category_id' => $cid, 'name' => trim((string)post('name')), 'severity' => post('severity', 'MAJOR')]); }
    if ($a === 'del_defect') q('DELETE FROM defect_master WHERE id=?', [(int)post('id')]);
    if ($a === 'sev_defect') q('UPDATE defect_master SET severity=? WHERE id=?', [post('severity'), (int)post('id')]);
    if ($a === 'add_check' && trim((string)post('name'))) insert('checkpoints', ['client_id' => (int)post('client_id') ?: null, 'category' => post('category') ?: null, 'name' => trim((string)post('name')), 'sort' => 50]);
    if ($a === 'del_check') q('DELETE FROM checkpoints WHERE id=?', [(int)post('id')]);
    if ($a === 'add_test' && trim((string)post('name'))) insert('onsite_tests', ['name' => trim((string)post('name')), 'sort' => 50]);
    if ($a === 'del_test') q('DELETE FROM onsite_tests WHERE id=?', [(int)post('id')]);
    if ($a === 'add_cat' && trim((string)post('name'))) insert('product_categories', ['name' => trim((string)post('name'))]);
    if ($a === 'del_cat') q('DELETE FROM product_categories WHERE id=?', [(int)post('id')]);
    if ($a === 'add_type' && trim((string)post('name'))) insert('qc_types', ['name' => trim((string)post('name'))]);
    if ($a === 'del_type') q('DELETE FROM qc_types WHERE id=?', [(int)post('id')]);
    flash('Saved.'); redirect('settings', ['tab' => $tab, 'dc' => get('dc')]);
} elseif (is_post()) { flash('Only Super Admin can change settings.', 'err'); redirect('settings', ['tab' => $tab]); }
$tabs = ['toggles' => 'Feature toggles', 'defects' => 'Defect master', 'checkpoints' => 'Quality checkpoints', 'tests' => 'On-site tests', 'masters' => 'Categories & QC types',
    'aql' => 'AQL tables', 'template' => 'Report template', 'email' => 'Email (SMTP)', 'roles' => 'Role permissions'];
$ro = $sa ? '' : 'disabled';
layout_start('Master Settings', 'settings', '', 'Masters, standard defect list, checkpoints, AQL tables, report template, email, feature toggles and role permissions' . ($sa ? '' : ' · view only'));
?>
<div class="grid" style="grid-template-columns:220px minmax(0,1fr)">
<div class="card" style="padding:10px;align-self:start"><nav class="stack" style="--g:2px">
  <?php foreach (['clients' => 'Client master', 'vendors' => 'Vendor master', 'mapping' => 'Client–vendor mapping'] as $k => $l): ?><a class="btn ghost block" style="justify-content:flex-start;border:0" href="<?= url($k) ?>"><?= h($l) ?> ↗</a><?php endforeach ?>
  <?php foreach ($tabs as $k => $l): ?><a class="btn block <?= $tab === $k ? 'pri' : 'ghost' ?>" style="justify-content:flex-start;<?= $tab === $k ? '' : 'border:0' ?>" href="<?= url('settings', ['tab' => $k]) ?>"><?= h($l) ?></a><?php endforeach ?>
</nav></div>
<div>
<?php if ($tab === 'toggles'): ?>
<div class="card"><div class="card-h"><h2>Feature toggles</h2><span class="pill">No code deploy needed</span></div>
<form method="post"><?= csrf_field() ?><input type="hidden" name="a" value="toggles">
<?php foreach ($TOG as $k => $d): ?><label class="chk" style="padding:10px 0;border-bottom:1px solid var(--line);justify-content:space-between"><span><b class="mono"><?= $k ?></b><br><small><?= h($d) ?></small></span><input type="checkbox" name="<?= $k ?>" value="1" <?= flag($k) ? 'checked' : '' ?> <?= $ro ?> style="width:20px;height:20px"></label><?php endforeach ?>
<p class="note mt">Phase 1: vendor &amp; client request flags OFF — Back Office creates requests. Switch ON in Phase 2.</p>
<?php if ($sa): ?><button class="btn pri">Save toggles</button><?php endif ?></form></div>
<div class="card"><h2 class="mb">Automation rules (built in)</h2><ul style="margin:0;padding-left:18px;line-height:1.9">
  <li>Report no. format <b class="mono">JS_{YYYY}_{MM}_{CLIENT}_{SERIAL}</b> — e.g. <?= h(next_report_no('KP')) ?></li><li>Block submit if Accepted + Rejected ≠ Inspected</li>
  <li>Defect photo mandatory when defect qty &gt; 0 (toggle above)</li><li>System suggests PASS/FAIL from AQL Ac/Re or client major-defect limit; manager decides (override needs a note)</li>
  <li>Report locked after approval &amp; PDF — edits by Super Admin create Revision 01, 02… with audit trail; old PDF kept</li></ul></div>

<?php elseif ($tab === 'defects'): $cats = all('SELECT * FROM defect_categories ORDER BY sort, name'); $dc = (int)get('dc', $cats[0]['id'] ?? 0); ?>
<div class="card"><div class="card-h"><h2>Defect master — <?= h(val('SELECT name FROM defect_categories WHERE id=?', [$dc]) ?? '') ?></h2></div>
  <div class="tabs"><?php foreach ($cats as $c): ?><a class="<?= $dc == $c['id'] ? 'on' : '' ?>" href="<?= url('settings', ['tab' => 'defects', 'dc' => $c['id']]) ?>"><?= h($c['name']) ?></a><?php endforeach ?></div>
  <table class="t"><tr><th>Defect</th><th>Severity</th><th></th></tr>
  <?php foreach (all('SELECT * FROM defect_master WHERE category_id=? ORDER BY name', [$dc]) as $d): ?><tr><td><?= h($d['name']) ?></td>
    <td><?php if ($sa): ?><form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="a" value="sev_defect"><input type="hidden" name="id" value="<?= $d['id'] ?>"><?= sel('severity', ['CRITICAL' => 'CRITICAL', 'MAJOR' => 'MAJOR', 'MINOR' => 'MINOR'], $d['severity'], 'data-auto style="width:auto;padding:4px 8px"') ?></form><?php else: ?><?= badge($d['severity']) ?><?php endif ?></td>
    <td><?php if ($sa): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="a" value="del_defect"><input type="hidden" name="id" value="<?= $d['id'] ?>"><button class="btn sm ghost" data-confirm="Remove defect?">✕</button></form><?php endif ?></td></tr><?php endforeach ?></table>
  <p class="muted" style="font-size:12px">Standard defect list — every inspector picks from the same names, so analytics stay clean.</p>
  <?php if ($sa): ?><form method="post" class="filters mt" action="<?= url('settings', ['tab' => 'defects', 'dc' => $dc]) ?>"><?= csrf_field() ?><input type="hidden" name="a" value="add_defect">
    <?= sel('category_id', array_column($cats, 'name', 'id'), $dc) ?><input type="text" name="new_cat" placeholder="…or new category"><input type="text" name="name" placeholder="Defect name" required><?= sel('severity', ['CRITICAL' => 'CRITICAL', 'MAJOR' => 'MAJOR', 'MINOR' => 'MINOR'], 'MAJOR') ?><button class="btn pri">Add defect</button></form><?php endif ?>
</div>

<?php elseif ($tab === 'checkpoints'): ?>
<div class="card"><h2 class="mb">Quality checkpoints</h2><p class="muted" style="margin-top:-6px;font-size:12.5px">Global checkpoints apply to every inspection; add client- or product-specific ones as needed.</p>
<table class="t"><tr><th>Checkpoint</th><th>Client</th><th>Product</th><th></th></tr>
<?php foreach (all('SELECT k.*, c.code FROM checkpoints k LEFT JOIN clients c ON c.id=k.client_id ORDER BY k.client_id, k.sort, k.id') as $k): ?><tr><td><?= h($k['name']) ?></td><td><?= h($k['code'] ?: 'All') ?></td><td><?= h($k['category'] ?: 'All') ?></td>
  <td><?php if ($sa): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="a" value="del_check"><input type="hidden" name="id" value="<?= $k['id'] ?>"><button class="btn sm ghost">✕</button></form><?php endif ?></td></tr><?php endforeach ?></table>
<?php if ($sa): ?><form method="post" class="filters mt"><?= csrf_field() ?><input type="hidden" name="a" value="add_check"><input type="text" name="name" placeholder="Checkpoint" required>
  <?= sel('client_id', array_column(all('SELECT id, code FROM clients ORDER BY code'), 'code', 'id'), '', '', 'All clients') ?><?= sel('category', array_combine($c = array_column(all('SELECT name FROM product_categories ORDER BY name'), 'name'), $c), '', '', 'All products') ?><button class="btn pri">Add checkpoint</button></form><?php endif ?></div>

<?php elseif ($tab === 'tests'): ?>
<div class="card"><h2 class="mb">On-site test master</h2><table class="t"><tr><th>Test</th><th></th></tr>
<?php foreach (all('SELECT * FROM onsite_tests ORDER BY sort, id') as $t): ?><tr><td><?= h($t['name']) ?></td><td><?php if ($sa): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="a" value="del_test"><input type="hidden" name="id" value="<?= $t['id'] ?>"><button class="btn sm ghost">✕</button></form><?php endif ?></td></tr><?php endforeach ?></table>
<?php if ($sa): ?><form method="post" class="filters mt"><?= csrf_field() ?><input type="hidden" name="a" value="add_test"><input type="text" name="name" placeholder="Test name" required><button class="btn pri">Add test</button></form><?php endif ?></div>

<?php elseif ($tab === 'masters'): ?>
<div class="grid g2">
<div class="card" style="margin:0"><h2 class="mb">Product categories</h2><table class="t"><?php foreach (all('SELECT * FROM product_categories ORDER BY name') as $c): ?><tr><td><?= h($c['name']) ?></td><td><?php if ($sa): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="a" value="del_cat"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="btn sm ghost">✕</button></form><?php endif ?></td></tr><?php endforeach ?></table>
<?php if ($sa): ?><form method="post" class="filters mt"><?= csrf_field() ?><input type="hidden" name="a" value="add_cat"><input type="text" name="name" required placeholder="New category"><button class="btn pri">Add</button></form><?php endif ?></div>
<div class="card" style="margin:0"><h2 class="mb">QC types</h2><table class="t"><?php foreach (all('SELECT * FROM qc_types ORDER BY id') as $c): ?><tr><td><?= h($c['name']) ?></td><td><?php if ($sa): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="a" value="del_type"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="btn sm ghost">✕</button></form><?php endif ?></td></tr><?php endforeach ?></table>
<?php if ($sa): ?><form method="post" class="filters mt"><?= csrf_field() ?><input type="hidden" name="a" value="add_type"><input type="text" name="name" required placeholder="New QC type"><button class="btn pri">Add</button></form><?php endif ?></div>
</div>

<?php elseif ($tab === 'aql'): $lv = (string)get('lv', 'II'); $A = [0.65, 1.0, 1.5, 2.5, 4.0, 6.5]; ?>
<div class="card"><div class="card-h"><h2>AQL lookup — ISO 2859-1 (single sampling, normal inspection)</h2>
  <form><input type="hidden" name="tab" value="aql"><?= sel('lv', ['I' => 'General I', 'II' => 'General II', 'III' => 'General III', 'S1' => 'S-1', 'S2' => 'S-2', 'S3' => 'S-3', 'S4' => 'S-4'], $lv, 'data-auto style="width:auto"') ?></form></div>
<div class="tbl-wrap"><table class="t"><tr><th>Lot size</th><th>Code</th><th class="num">Sample</th><?php foreach ($A as $a): ?><th class="num">AQL <?= $a ?> Ac/Re</th><?php endforeach ?></tr>
<?php foreach (AQL_CODE_TABLE as [$lo, $hi]): if ($lo > 500000) $hi = 999999; $L = aql_code_letter($lo, $lv); $n = AQL_SAMPLE[array_search($L, AQL_LETTERS, true)]; ?><tr>
  <td><?= num($lo) ?> – <?= $hi >= 999999 ? 'over' : num($hi) ?></td><td><b><?= $L ?></b></td><td class="num"><?= $n ?></td>
  <?php foreach ($A as $a): $p = aql_single($L, $a); ?><td class="num"><?= $p ? $p['ac'] . ' / ' . $p['re'] . ($p['sample'] !== $n ? ' <small>(n=' . $p['sample'] . ')</small>' : '') : '—' ?></td><?php endforeach ?></tr><?php endforeach ?></table></div>
<p class="muted" style="font-size:12px">Arrows of the ISO master table are resolved automatically — when the plan uses a different sample size it is shown as (n=…). Values to be verified by JEWELSETU before go-live.</p></div>

<?php elseif ($tab === 'template'): ?>
<div class="card"><h2 class="mb">PDF report header / footer template</h2>
<form method="post"><?= csrf_field() ?><input type="hidden" name="a" value="template">
  <div class="fr f"><div><label>Header</label><input type="text" readonly value="JEWELSETU logo + report no."></div><div><label>Numbering</label><input type="text" readonly value="JS_{YYYY}_{MM}_{CLIENT}_{SERIAL}"></div></div>
  <div class="f"><label>Company name (footer)</label><input type="text" name="company_name" value="<?= h(setting('company_name')) ?>" <?= $ro ?>></div>
  <div class="f"><label>Report title</label><input type="text" name="report_header" value="<?= h(setting('report_header')) ?>" <?= $ro ?>></div>
  <div class="f"><label>Disclaimer / footer text</label><textarea name="report_footer" <?= $ro ?>><?= h(setting('report_footer')) ?></textarea></div>
  <p class="muted" style="font-size:12px">Footer always shows generated time · page x of y.</p>
  <?php if ($sa): ?><button class="btn pri">Save template</button><?php endif ?></form></div>

<?php elseif ($tab === 'email'): ?>
<div class="card"><h2 class="mb">Email (SMTP) settings</h2>
<form method="post"><?= csrf_field() ?><input type="hidden" name="a" value="email">
  <div class="f"><label>Transport</label><?= sel('mail_transport', ['none' => 'Off — log only (reports still published on portals)', 'phpmail' => 'PHP mail() — shared hosting default', 'smtp' => 'SMTP (Gmail, SES, SendGrid, cPanel mailbox)'], setting('mail_transport', 'none'), $ro) ?></div>
  <div class="fr f"><div><label>SMTP host</label><input type="text" name="smtp_host" value="<?= h(setting('smtp_host')) ?>" placeholder="mail.jewelsetu.com" <?= $ro ?>></div><div><label>Port</label><input type="text" name="smtp_port" value="<?= h(setting('smtp_port')) ?>" <?= $ro ?>></div>
    <div><label>Security</label><?= sel('smtp_secure', ['tls' => 'TLS (587)', 'ssl' => 'SSL (465)', 'none' => 'None'], setting('smtp_secure', 'tls'), $ro) ?></div></div>
  <div class="fr f"><div><label>SMTP user</label><input type="text" name="smtp_user" value="<?= h(setting('smtp_user')) ?>" <?= $ro ?>></div><div><label>SMTP password <?= setting('smtp_pass') ? '(saved — leave blank to keep)' : '' ?></label><input type="password" name="smtp_pass" <?= $ro ?>></div></div>
  <div class="fr f"><div><label>From email</label><input type="email" name="mail_from" value="<?= h(setting('mail_from')) ?>" <?= $ro ?>></div><div><label>From name</label><input type="text" name="mail_from_name" value="<?= h(setting('mail_from_name')) ?>" <?= $ro ?>></div></div>
  <div class="f"><label>JEWELSETU admin copy (CC on every report)</label><input type="email" name="admin_email" value="<?= h(setting('admin_email')) ?>" <?= $ro ?>></div>
  <?php if ($sa): ?><button class="btn pri">Save email settings</button><?php endif ?></form>
<?php if ($sa): ?><div class="hr"></div><form method="post" class="filters"><?= csrf_field() ?><input type="hidden" name="a" value="testmail"><input type="email" name="to" placeholder="Send test to…" required><button class="btn">Send test email</button></form><?php endif ?>
<h2 class="mb mt">Email history</h2>
<table class="t"><tr><th>Recipient</th><th>Email</th><th>Subject</th><th>Sent</th><th>Status</th></tr><?php foreach (all('SELECT * FROM email_log ORDER BY id DESC LIMIT 25') as $e): ?><tr><td><?= h($e['recipient_type']) ?></td><td><?= h($e['email']) ?></td><td><?= h(mb_strimwidth((string)$e['subject'], 0, 60, '…')) ?></td><td><?= fdt($e['sent_at']) ?></td><td><?= badge($e['status']) ?></td></tr><?php endforeach ?></table></div>

<?php elseif ($tab === 'roles'): $M = [
    'QC requests' => ['Full', 'Full', 'View', '—', 'View / raise*', 'View / raise*'], 'Excel import' => ['Full', 'Full', '—', '—', '—', 'Template'],
    'Schedule requests' => ['Full', 'Full', 'View', '—', 'View', 'View'], 'Assign inspector' => ['Full', 'Full', 'Full', '—', '—', '—'],
    'Inspection (app)' => ['—', '—', '—', 'Full', '—', '—'], 'SKU hold approval' => ['Full', '—', 'View', 'Raise', 'Respond (client holds)', 'Respond (vendor holds)'],
    'Review / approve / return' => ['Full', '—', 'Full', '—', '—', '—'], 'Concession decision' => ['Full', '—', 'Raise', '—', 'Approve / reject', '—'],
    'Reports / PDF' => ['Full + revise', 'View', 'Full', 'Own', 'View (own client)', 'View (own vendor)'], 'Analytics' => ['Full', 'View', 'Full', '—', 'View (mapped vendors)', '—'],
    'Masters & settings' => ['Full', 'View', 'View', '—', '—', '—'], 'Team & logins' => ['Full', 'View', 'View', '—', '—', '—']]; ?>
<div class="card"><h2 class="mb">Role permissions (6 roles)</h2>
<div class="tbl-wrap"><table class="t"><tr><th>Module</th><?php foreach (ROLES as $r): ?><th><?= h($r) ?></th><?php endforeach ?></tr>
<?php foreach ($M as $m => $v): ?><tr><td class="strong"><?= h($m) ?></td><?php foreach ($v as $x): ?><td><?= $x === '—' ? '<span class="muted">—</span>' : h($x) ?></td><?php endforeach ?></tr><?php endforeach ?></table></div>
<p class="muted" style="font-size:12px">* when the matching feature toggle is ON. Every record carries client_id + vendor_id; client and vendor logins only ever see their own rows.</p></div>
<?php endif ?>
</div></div>
<?php layout_end();
