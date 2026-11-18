<?php
/* ---------- auth, session, shared lookups, files ---------- */

function me_payload(): array {
    $u = user();
    if (!$u) return ['auth' => false, 'csrf' => csrf_token()];
    $org = match ($u['role']) {
        'client' => val('SELECT name FROM clients WHERE id=?', [$u['client_id']]),
        'vendor' => val('SELECT name FROM vendors WHERE id=?', [$u['vendor_id']]),
        default => 'JEWELSETU HQ' };
    $flags = []; foreach (['enable_vendor_qc_request', 'enable_client_qc_request', 'require_defect_photo', 'gps_on_inspection_start', 'allow_job_split', 'block_qty_mismatch', 'qc_manager_role', 'inspector_new_job', 'inspector_edit_after_submit'] as $k) $flags[$k] = flag($k);
    return ['auth' => true, 'csrf' => csrf_token(), 'demo' => $GLOBALS['CFG']['seed_demo'], 'flags' => $flags, 'counts' => nav_counts(),
        'user' => ['id' => (int)$u['id'], 'name' => $u['name'], 'role' => $u['role'], 'role_label' => ROLES[$u['role']], 'emp_code' => $u['emp_code'], 'email' => $u['email'],
            'client_id' => $u['client_id'] ? (int)$u['client_id'] : null, 'vendor_id' => $u['vendor_id'] ? (int)$u['vendor_id'] : null, 'org' => $org, 'location' => $u['location'], 'phone' => $u['phone']]];
}
function nav_counts(): array {
    $u = user(); $r = $u['role']; $c = ['unread' => (int)val('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0', [$u['id']])];
    if (in_array($r, R_INT, true)) { $c['requests'] = (int)val("SELECT COUNT(*) FROM qc_jobs WHERE status='REQUESTED'"); $c['review'] = (int)val("SELECT COUNT(*) FROM qc_items WHERE status IN ('SUBMITTED','UNDER_REVIEW')"); }
    if ($r === 'client') $c['approvals'] = (int)val("SELECT COUNT(*) FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id WHERE j.client_id=? AND (i.concession_status='PENDING' OR (i.hold_status='PENDING' AND i.hold_for='client'))", [$u['client_id']]);
    if ($r === 'vendor') $c['approvals'] = (int)val("SELECT COUNT(*) FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id WHERE j.vendor_id=? AND i.hold_status='PENDING' AND i.hold_for='vendor'", [$u['vendor_id']]);
    if (in_array($r, ['super_admin', 'qc_manager'], true)) $c['approvals'] = (int)val("SELECT COUNT(*) FROM qc_items WHERE concession_status='PENDING' OR hold_status='PENDING'");
    return $c;
}

api('GET', 'me', null, fn() => me_payload());

api('POST', 'auth/login', null, function () {
    $id = trim((string)in_('login')); $pw = (string)in_('password');
    $u = one('SELECT * FROM users WHERE (LOWER(email)=LOWER(?) OR UPPER(emp_code)=UPPER(?)) AND status=?', [$id, $id, 'ACTIVE']);
    if (!$u || !password_verify($pw, $u['password_hash'])) fail('Invalid credentials or inactive account.', 401);
    session_regenerate_id(true); $_SESSION['uid'] = $u['id']; unset($_SESSION['csrf']);
    q('UPDATE users SET last_login=? WHERE id=?', [now(), $u['id']]);
    return me_payload();
});
api('POST', 'auth/logout', null, function () { $_SESSION = []; session_destroy(); return ['ok' => true]; });

api('GET', 'notifications', R_ALL, function () {
    $uid = user()['id'];
    $list = all('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 100', [$uid]);
    q('UPDATE notifications SET is_read=1 WHERE user_id=?', [$uid]);
    $ret = [];
    if (role() === 'inspector') $ret = all("SELECT i.id, i.sku, i.return_reason, i.return_sections, j.job_no FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id WHERE (j.inspector_id=? OR j.inspector2_id=?) AND i.status='RETURNED'", [$uid, $uid]);
    return ['items' => $list, 'returned' => $ret];
});

api('GET', 'profile', R_ALL, function () {
    $u = user(); $o = ['user' => me_payload()['user']];
    if ($u['vendor_id']) { $o['vendor'] = one('SELECT * FROM vendors WHERE id=?', [$u['vendor_id']]);
        $o['vendor']['clients'] = array_column(all('SELECT c.code FROM client_vendor m JOIN clients c ON c.id=m.client_id WHERE m.vendor_id=?', [$u['vendor_id']]), 'code'); }
    if ($u['client_id']) $o['client'] = one('SELECT * FROM clients WHERE id=?', [$u['client_id']]);
    if ($u['role'] === 'inspector') $o['kpi'] = inspector_kpis((int)$u['id']);
    return $o;
});
api('POST', 'profile/password', R_ALL, function () {
    $u = user();
    if (!password_verify((string)in_('current'), $u['password_hash'])) fail('Current password is wrong.');
    if (strlen((string)in_('new')) < 6) fail('New password must be at least 6 characters.');
    q('UPDATE users SET password_hash=? WHERE id=?', [password_hash((string)in_('new'), PASSWORD_DEFAULT), $u['id']]);
    return ['ok' => true, 'message' => 'Password changed.'];
});

/** dropdown data, scoped to the caller */
api('GET', 'options', R_ALL, function () {
    $u = user(); $r = $u['role'];
    if ($r === 'vendor') $clients = all("SELECT c.id, c.code, c.name FROM clients c JOIN client_vendor m ON m.client_id=c.id WHERE m.vendor_id=? AND c.status='ACTIVE' ORDER BY c.code", [$u['vendor_id']]);
    elseif ($r === 'client') $clients = all('SELECT id, code, name FROM clients WHERE id=?', [$u['client_id']]);
    else $clients = all('SELECT id, code, name, status FROM clients ORDER BY code');
    if ($r === 'client') $vendors = all("SELECT v.id, v.name, v.code, v.location FROM vendors v JOIN client_vendor m ON m.vendor_id=v.id WHERE m.client_id=? ORDER BY v.name", [$u['client_id']]);
    elseif ($r === 'vendor') $vendors = all('SELECT id, name, code, location FROM vendors WHERE id=?', [$u['vendor_id']]);
    else $vendors = all('SELECT id, name, code, location, status FROM vendors ORDER BY name');
    $o = ['clients' => $clients, 'vendors' => $vendors,
        'mapping' => array_map(fn($m) => ['client_id' => (int)$m['client_id'], 'vendor_id' => (int)$m['vendor_id']], all('SELECT * FROM client_vendor')),
        'categories' => array_column(all('SELECT name FROM product_categories ORDER BY name'), 'name'),
        'qc_types' => array_column(all('SELECT name FROM qc_types ORDER BY id'), 'name')];
    if (in_array($r, R_INT, true)) { $o['inspectors'] = opt_list(user_opts('inspector')); $o['managers'] = mgr_on() ? opt_list(user_opts('qc_manager')) : []; $o['mgr_on'] = mgr_on(); }
    return $o;
});
function opt_list(array $kv): array { $o = []; foreach ($kv as $k => $v) $o[] = ['id' => (int)$k, 'label' => $v]; return $o; }

/* ---------- binary downloads (session-authenticated) ---------- */
api('GET', 'file', R_ALL, function () {
    $p = (string)in_('p');
    if (!preg_match('#^[a-z0-9_\-/]+\.[a-z0-9]+$#i', $p) || str_contains($p, '..')) fail('Not found', 404);
    $full = UPLOAD_DIR . '/' . $p;
    if (!is_file($full)) fail('Not found', 404);
    $job = one('SELECT j.* FROM attachments a JOIN qc_jobs j ON j.id=a.job_id WHERE a.path=?', [$p])
        ?? one('SELECT j.* FROM item_photos ph JOIN qc_items i ON i.id=ph.item_id JOIN qc_jobs j ON j.id=i.job_id WHERE ph.path=?', [$p]);
    if ($job && !can_see_job($job)) fail('Forbidden', 403);
    $mine = str_starts_with($p, 'imports/img/') && in_array($p, array_column($_SESSION['import']['rows'] ?? [], 'img'), true);
    if (!$job && !is_internal() && !$mine) fail('Forbidden', 403);
    $mime = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'csv' => 'text/csv'][strtolower(pathinfo($full, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
    header('Content-Type: ' . $mime); header('Cache-Control: private, max-age=86400');
    if (in_('dl')) header('Content-Disposition: attachment; filename="' . basename($full) . '"');
    readfile($full); exit;
});

api('GET', 'pdf', R_ALL, function () {
    require_once ROOT . '/app/pdf.php';
    if ($iid = (int)in_('item')) {
        $it = one('SELECT * FROM qc_items WHERE id=?', [$iid]); $job = $it ? job_load((int)$it['job_id']) : null;
        if (!$job || !can_see_job($job)) fail('Not found', 404);
        if (!is_internal() && $it['status'] !== 'APPROVED') fail('Report not published yet', 403);
        $stored = $it['pdf_path'] ? UPLOAD_DIR . '/' . $it['pdf_path'] : null;
        if ($it['locked'] && $stored && is_file($stored)) { $pdf = file_get_contents($stored); $name = basename($stored); }
        else [$pdf, $name] = item_pdf($iid, (bool)$it['locked']);
    } elseif ($jid = (int)in_('job')) {
        $job = job_load($jid);
        if (!$job || !can_see_job($job)) fail('Not found', 404);
        $pub = in_array($job['status'], ['EMAILED', 'CLOSED', 'APPROVED'], true);
        if (!is_internal() && !$pub) fail('Report not published yet', 403);
        $stored = $job['po_pdf'] ? UPLOAD_DIR . '/' . $job['po_pdf'] : null;
        if ($stored && is_file($stored) && !in_('fresh')) { $pdf = file_get_contents($stored); $name = basename($stored); }
        else [$pdf, $name] = job_pdf($jid, $pub);
    } elseif ($old = (string)in_('file')) {
        if (!preg_match('#^reports/[A-Za-z0-9_\-]+\.pdf$#', $old) || !is_internal()) fail('Not found', 404);
        $pdf = (string)@file_get_contents(UPLOAD_DIR . '/' . $old); $name = basename($old);
    } else fail('Bad request', 400);
    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . (in_('dl') ? 'attachment' : 'inline') . '; filename="' . $name . '"');
    echo $pdf; exit;
});
