<?php
/* ---------- dashboards, clients, vendors, mapping, team, settings ---------- */

api('GET', 'dashboard', R_INT, function () {
    $mStart = date('Y-m-01'); $pm = strtotime('-1 month', strtotime($mStart));
    $month = sum_items(approved_items(['from' => $mStart]));
    $prev = sum_items(approved_items(['from' => date('Y-m-01', $pm), 'to' => date('Y-m-t', $pm)]));
    $today = all("SELECT " . img_job_sql() . " img, j.id, j.job_no, j.req_no, j.status, j.time_slot, c.code client_code, v.name vendor_name, u.name insp_name, u.emp_code insp_code, (SELECT COUNT(*) FROM qc_items WHERE job_id=j.id) skus
        FROM qc_jobs j JOIN clients c ON c.id=j.client_id JOIN vendors v ON v.id=j.vendor_id LEFT JOIN users u ON u.id=j.inspector_id WHERE j.scheduled_date=? AND j.status<>'CANCELLED' ORDER BY j.time_slot", [today()]);
    $up = all("SELECT j.id, j.job_no, j.req_no, j.po_no, j.status, j.inspector_id, COALESCE(j.scheduled_date, j.requested_date) d, c.code client_code, v.name vendor_name FROM qc_jobs j JOIN clients c ON c.id=j.client_id JOIN vendors v ON v.id=j.vendor_id
        WHERE (j.scheduled_date>? OR (j.scheduled_date IS NULL AND j.requested_date>=?)) AND j.status IN ('REQUESTED','SCHEDULED','ASSIGNED') ORDER BY COALESCE(j.scheduled_date,j.requested_date) LIMIT 6", [today(), today()]);
    return ['month' => $month, 'prev' => $prev, 'prev_label' => date('M', $pm), 'month_label' => date('F Y'),
        'pending' => (int)val("SELECT COUNT(*) FROM qc_items WHERE status IN ('SUBMITTED','UNDER_REVIEW','RETURNED')"),
        'review' => (int)val("SELECT COUNT(*) FROM qc_items WHERE status IN ('SUBMITTED','UNDER_REVIEW')"),
        'open_jobs' => (int)val("SELECT COUNT(*) FROM qc_jobs WHERE status='PARTIAL'"),
        'team_live' => array_map(fn($u) => $u + ['st' => insp_status((int)$u['id'], today())], all("SELECT id, emp_code, name FROM users WHERE role='inspector' AND status='ACTIVE' ORDER BY emp_code")),
        'open_bal' => array_sum(array_map(function ($j) { $p = 0; foreach (all('SELECT id FROM qc_items WHERE job_id=? AND parent_id IS NULL', [$j['id']]) as $x) $p += line_stats($j, (int)$x['id'])['pending']; return $p; }, all("SELECT * FROM qc_jobs WHERE status='PARTIAL'"))),
        'trend' => month_trend(approved_items()), 'today' => $today, 'upcoming' => $up, 'by_vendor' => dash_vendor_groups()];
});
/** Dashboard "Vendor-wise view": open job cards grouped per vendor + client with inspectors, open / inspected-today / pending designs and live status. */
function dash_vendor_groups(): array {
    $jobs = all("SELECT " . img_job_sql() . " img, j.id, j.job_no, j.req_no, j.po_no, j.status, j.scheduled_date, j.vendor_id, j.client_id, j.inspector_id, c.code client_code, v.name vendor_name, v.location vendor_loc, u.name insp_name, u.emp_code insp_code,
        (SELECT MIN(sku) FROM qc_items WHERE job_id=j.id) sku, (SELECT COUNT(*) FROM qc_items WHERE job_id=j.id AND parent_id IS NULL) designs,
        (SELECT COUNT(*) FROM qc_items WHERE job_id=j.id AND status NOT IN ('SUBMITTED','UNDER_REVIEW','APPROVED')) open_items,
        (SELECT COUNT(*) FROM qc_items WHERE job_id=j.id AND insp_date=? AND status IN ('SUBMITTED','UNDER_REVIEW','APPROVED')) today_done
        FROM qc_jobs j JOIN clients c ON c.id=j.client_id JOIN vendors v ON v.id=j.vendor_id LEFT JOIN users u ON u.id=j.inspector_id
        WHERE j.status IN ('SCHEDULED','ASSIGNED','IN_PROGRESS','PARTIAL','RETURNED','SUBMITTED','UNDER_REVIEW') OR (j.scheduled_date=? AND j.status<>'CANCELLED')
        ORDER BY j.scheduled_date, j.id", [today(), today()]);
    $g = []; $live = [];
    foreach ($jobs as $j) {
        $k = $j['vendor_id'] . '-' . $j['client_id'];
        $g[$k] = $g[$k] ?? ['key' => $k, 'vendor_id' => (int)$j['vendor_id'], 'vendor_name' => $j['vendor_name'], 'client_code' => $j['client_code'], 'location' => explode(',', (string)$j['vendor_loc'])[0], 'img' => $j['img'],
            'jobs' => [], 'designs' => 0, 'open' => 0, 'today' => 0, 'insp' => [], 'live' => null];
        $x = &$g[$k];
        $x['jobs'][] = $j; $x['designs'] += (int)$j['designs']; $x['open'] += (int)$j['open_items']; $x['today'] += (int)$j['today_done'];
        if (!$x['img'] && $j['img']) $x['img'] = $j['img'];
        if ($j['inspector_id']) $x['insp'][$j['inspector_id']] = $j['insp_code'] . ' ' . explode(' ', (string)$j['insp_name'])[0];
        unset($x);
    }
    foreach ($g as &$x) {
        foreach (array_keys($x['insp']) as $uid) {
            $live[$uid] = $live[$uid] ?? open_checkin((int)$uid);
            if ($live[$uid] && (int)$live[$uid]['vendor_id'] === $x['vendor_id']) $x['live'] = ['code' => 'ONSITE', 'label' => 'ON-SITE · ' . date('h:i A', strtotime($live[$uid]['at']))];
        }
        if (!$x['live']) $x['live'] = $x['insp'] ? ['code' => 'ASSIGNED', 'label' => 'NOT CHECKED-IN'] : ['code' => 'PENDING', 'label' => 'UNASSIGNED'];
        $x['insp'] = array_values($x['insp']);
    }
    unset($x);
    $g = array_values($g);
    usort($g, fn($a, $b) => [$b['live']['code'] === 'ONSITE', $b['open']] <=> [$a['live']['code'] === 'ONSITE', $a['open']]);
    return $g;
}

/* ----- clients ----- */
api('GET', 'clients', ['super_admin', 'back_office'], function () {
    $qs = trim((string)in_('q'));
    $rows = all("SELECT c.*, (SELECT COUNT(*) FROM client_vendor m WHERE m.client_id=c.id) nv, (SELECT COUNT(*) FROM qc_jobs j WHERE j.client_id=c.id) nq,
        (SELECT COUNT(*) FROM users u WHERE u.client_id=c.id AND u.status='ACTIVE') nl FROM clients c" . ($qs ? ' WHERE c.name LIKE ? OR c.code LIKE ?' : '') . ' ORDER BY c.status, c.code', $qs ? ["%$qs%", "%$qs%"] : []);
    $sel = null;
    if ($id = (int)in_('id')) {
        $sel = one('SELECT * FROM clients WHERE id=?', [$id]);
        if ($sel) { $sel['vendor_ids'] = array_map('intval', array_column(all('SELECT vendor_id FROM client_vendor WHERE client_id=?', [$id]), 'vendor_id'));
            $sel['vendor_names'] = array_column(all('SELECT v.name FROM client_vendor m JOIN vendors v ON v.id=m.vendor_id WHERE m.client_id=?', [$id]), 'name');
            $sel['month'] = sum_items(approved_items(['client_id' => $id, 'from' => date('Y-m-01')])); $sel['all'] = sum_items(approved_items(['client_id' => $id])); }
    }
    return ['rows' => $rows, 'sel' => $sel, 'vendors' => all('SELECT id, name FROM vendors ORDER BY name')];
});
api('POST', 'clients/save', ['super_admin', 'back_office'], function () {
    $id = (int)in_('id');
    $d = ['code' => strtoupper(trim((string)in_('code'))), 'name' => trim((string)in_('name')), 'contact' => in_('contact'), 'email' => in_('email'), 'phone' => in_('phone'),
        'ho_email' => in_('ho_email'), 'address' => in_('address'), 'report_format' => in_('report_format'), 'aql_default' => in_('aql_default') ?: '2.5',
        'fail_threshold' => (float)(in_('fail_threshold') ?: 80), 'status' => in_('status', 'ACTIVE')];
    if (!$d['code'] || !$d['name']) fail('Code and name are required.');
    if (one('SELECT id FROM clients WHERE code=? AND id<>?', [$d['code'], $id])) fail('Client code already exists.');
    if ($id) update('clients', $d, 'id=?', [$id]); else { $d['created_at'] = now(); $id = insert('clients', $d); }
    audit('client', $id, 'SAVE');
    q('DELETE FROM client_vendor WHERE client_id=?', [$id]);
    foreach ((array)in_('vendor_ids', []) as $vid) insert('client_vendor', ['client_id' => $id, 'vendor_id' => (int)$vid]);
    $msg = 'Client saved.';
    if (in_('create_login') && $d['email'] && !one('SELECT id FROM users WHERE LOWER(email)=LOWER(?)', [$d['email']])) {
        $pw = (string)(in_('login_pw') ?: 'Client@' . rand(1000, 9999));
        insert('users', ['name' => $d['contact'] ?: $d['name'], 'email' => $d['email'], 'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'pw_enc' => pw_enc($pw), 'role' => 'client', 'client_id' => $id, 'status' => 'ACTIVE', 'created_at' => now()]);
        $msg .= " Portal login: {$d['email']} / $pw";
    }
    return ['ok' => true, 'id' => $id, 'message' => $msg];
});

/* ----- vendors ----- */
api('GET', 'vendors', [...R_INT, 'client'], function () {
    $qs = trim((string)in_('q')); $cf = (int)in_('client'); $where = []; $p = [];
    if (role() === 'client') { $where[] = 'v.id IN (SELECT vendor_id FROM client_vendor WHERE client_id=?)'; $p[] = user()['client_id']; }
    if ($cf) { $where[] = 'v.id IN (SELECT vendor_id FROM client_vendor WHERE client_id=?)'; $p[] = $cf; }
    if ($qs) { $where[] = '(v.name LIKE ? OR v.code LIKE ?)'; array_push($p, "%$qs%", "%$qs%"); }
    $rows = all('SELECT v.* FROM vendors v' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY v.name', $p);
    $vs = []; foreach (approved_items() as $i) $vs[$i['vendor_id']][] = $i;
    foreach ($rows as &$v) { $s = sum_items($vs[$v['id']] ?? []); $v['n'] = $s['n']; $v['rate'] = round($s['rate'], 2);
        $v['clients'] = role() === 'client' ? [val('SELECT code FROM clients WHERE id=?', [user()['client_id']])] : array_column(all('SELECT c.code FROM client_vendor m JOIN clients c ON c.id=m.client_id WHERE m.vendor_id=?', [$v['id']]), 'code'); }
    $sel = null;
    if ($id = (int)in_('id')) {
        $sel = one('SELECT * FROM vendors WHERE id=?', [$id]);
        if ($sel && role() === 'client' && !one('SELECT 1 FROM client_vendor WHERE client_id=? AND vendor_id=?', [user()['client_id'], $id])) $sel = null;
        if ($sel && $sel['geo_pending_by']) $sel['geo_pending_by_code'] = val('SELECT emp_code FROM users WHERE id=?', [$sel['geo_pending_by']]);
        if ($sel) {
            $items = $vs[$id] ?? []; if (role() === 'client') $items = array_values(array_filter($items, fn($i) => $i['client_id'] == user()['client_id']));
            $sel['stats'] = sum_items($items);
            $sel['recent'] = array_map(fn($i) => ['id' => $i['id'], 'report_no' => $i['report_no'], 'approved_at' => $i['approved_at'], 'sku' => $i['sku'], 'final_result' => $i['final_result']], array_slice(array_reverse($items), 0, 6));
            $sel['client_ids'] = array_map('intval', array_column(all('SELECT client_id FROM client_vendor WHERE vendor_id=?', [$id]), 'client_id'));
            $sel['clients'] = array_column(all('SELECT c.code FROM client_vendor m JOIN clients c ON c.id=m.client_id WHERE m.vendor_id=?', [$id]), 'code');
            $sel['login'] = is_internal() ? one('SELECT emp_code, status FROM users WHERE vendor_id=? LIMIT 1', [$id]) : null;
        }
    }
    return ['rows' => $rows, 'sel' => $sel, 'clients' => is_internal() ? all('SELECT id, code, name FROM clients ORDER BY code') : []];
});
api('POST', 'vendors/save', ['super_admin', 'back_office'], function () {
    $id = (int)in_('id');
    $d = ['code' => strtoupper(trim((string)in_('code'))), 'short_code' => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)in_('short_code'))) ?: null, 'name' => trim((string)in_('name')),
        'contact' => in_('contact'), 'email' => in_('email'), 'phone' => in_('phone'), 'ho_email' => in_('ho_email'), 'address' => in_('address'), 'location' => in_('location'), 'status' => in_('status', 'ACTIVE')];
    if (in_('geo_lat') !== null) {
        $la = trim((string)in_('geo_lat')); $lo = trim((string)in_('geo_lng'));
        if (($la !== '' || $lo !== '') && (!is_numeric($la) || !is_numeric($lo) || abs((float)$la) > 90 || abs((float)$lo) > 180)) fail('Factory GPS pin: enter valid latitude and longitude (e.g. 26.8467, 75.8022).');
        $d['geo_lat'] = $la !== '' ? $la : null; $d['geo_lng'] = $lo !== '' ? $lo : null; $d['geo_radius'] = (int)in_('geo_radius') ?: null;
    }
    if (!$d['code'] || !$d['name']) fail('Vendor code and name are required.');
    if (one('SELECT id FROM vendors WHERE code=? AND id<>?', [$d['code'], $id])) fail('Vendor code already exists.');
    if ($id) update('vendors', $d, 'id=?', [$id]); else { $d['created_at'] = now(); $id = insert('vendors', $d); }
    audit('vendor', $id, 'SAVE');
    q('DELETE FROM client_vendor WHERE vendor_id=?', [$id]);
    foreach ((array)in_('client_ids', []) as $cid) insert('client_vendor', ['client_id' => (int)$cid, 'vendor_id' => $id]);
    $msg = 'Vendor saved.';
    if (in_('create_login') && !one('SELECT id FROM users WHERE UPPER(emp_code)=?', [$d['code']])) {
        $pw = (string)(in_('login_pw') ?: 'Vendor@' . rand(1000, 9999));
        insert('users', ['name' => $d['name'], 'email' => $d['email'], 'emp_code' => $d['code'], 'password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'pw_enc' => pw_enc($pw), 'role' => 'vendor', 'vendor_id' => $id, 'status' => 'ACTIVE', 'created_at' => now()]);
        $msg .= " Portal login: {$d['code']} / $pw";
    }
    return ['ok' => true, 'id' => $id, 'message' => $msg];
});

/* ----- mapping ----- */
api('GET', 'mapping', ['super_admin'], function () {
    $demo = one("SELECT j.*, c.code cc, v.name vn FROM qc_jobs j JOIN clients c ON c.id=j.client_id JOIN vendors v ON v.id=j.vendor_id WHERE j.status='REQUESTED' ORDER BY j.id DESC LIMIT 1");
    return ['clients' => all('SELECT id, code, name, status, qc_manager_id FROM clients ORDER BY status, code'), 'vendors' => all('SELECT id, name FROM vendors ORDER BY name'),
        'mgr_on' => mgr_on(), 'managers' => all("SELECT id, name, emp_code, designation FROM users WHERE role='qc_manager' AND status='ACTIVE' ORDER BY name"),
        'map' => array_map(fn($m) => $m['client_id'] . '_' . $m['vendor_id'], all('SELECT * FROM client_vendor')),
        'logins' => all("SELECT u.id, u.email, u.emp_code, u.role, u.status, u.last_login, c.code ccode, v.name vname FROM users u LEFT JOIN clients c ON c.id=u.client_id LEFT JOIN vendors v ON v.id=u.vendor_id WHERE u.role IN ('client','vendor') ORDER BY u.role, u.name"),
        'demo' => $demo ? ['req_no' => $demo['req_no'], 'status' => $demo['status'], 'client' => $demo['cc'], 'vendor' => $demo['vn'],
            'others' => array_column(all('SELECT c.code FROM client_vendor m JOIN clients c ON c.id=m.client_id WHERE m.vendor_id=? AND m.client_id<>?', [$demo['vendor_id'], $demo['client_id']]), 'code')] : null];
});
api('POST', 'mapping/save', ['super_admin'], function () {
    q('DELETE FROM client_vendor');
    foreach ((array)in_('pairs', []) as $k) { [$c, $v] = array_map('intval', explode('_', (string)$k)); if ($c && $v) insert('client_vendor', ['client_id' => $c, 'vendor_id' => $v]); }
    if (in_('mgr_on') !== null) { $on = in_('mgr_on') ? '1' : '0'; if ($on !== setting('qc_manager_role', '0')) audit('settings', 0, 'QC_MANAGER_ROLE', 'qc_manager_role', setting('qc_manager_role', '0'), $on); set_setting('qc_manager_role', $on); }
    foreach ((array)in_('managers', []) as $cid => $uid) q('UPDATE clients SET qc_manager_id=? WHERE id=?', [(int)$uid ?: null, (int)$cid]);
    audit('mapping', 0, 'UPDATE');
    return ['ok' => true, 'message' => 'Mapping saved.'];
});

/* ----- team ----- */
api('GET', 'team', ['super_admin', 'qc_manager', 'back_office'], function () {
    $f = (string)in_('f', 'internal');
    $where = match ($f) { 'inspector' => "role='inspector'", 'qc_manager' => "role='qc_manager'", 'back_office' => "role='back_office'", 'portal' => "role IN ('client','vendor')", default => "role IN ('super_admin','back_office','qc_manager','inspector')" };
    $rows = all("SELECT u.id, u.name, u.email, u.emp_code, u.role, u.location, u.status, u.last_login, u.photo, c.code ccode, v.name vname FROM users u LEFT JOIN clients c ON c.id=u.client_id LEFT JOIN vendors v ON v.id=u.vendor_id WHERE $where ORDER BY u.role, u.emp_code, u.name");
    foreach ($rows as &$r) { $r['photo'] = $r['photo'] ? url('file', ['p' => $r['photo']]) : null; } unset($r);
    $perf = []; foreach (all("SELECT id, name, emp_code FROM users WHERE role='inspector' AND status='ACTIVE' ORDER BY emp_code") as $u) $perf[] = $u + inspector_kpis((int)$u['id'], date('Y-m-01'));
    $sel = null;
    if ($id = (int)in_('id')) { $sel = one('SELECT id, name, email, emp_code, role, client_id, vendor_id, phone, designation, location, area, joined_on, status FROM users WHERE id=?', [$id]);
        if ($sel && $sel['role'] === 'inspector') { $sel['kpi'] = inspector_kpis($id); $sel['leaves'] = all('SELECT * FROM leaves WHERE user_id=? AND to_date>=? ORDER BY from_date', [$id, date('Y-m-d', strtotime('-30 days'))]); } }
    foreach ($rows as &$r) if ($r['role'] === 'inspector' && $r['status'] === 'ACTIVE') $r['live'] = insp_status((int)$r['id'], today()); unset($r);
    if (is_role('super_admin')) {
        $enc = array_column(all('SELECT id, pw_enc FROM users'), 'pw_enc', 'id');
        foreach ($rows as &$r) $r['password'] = pw_dec($enc[$r['id']] ?? null); unset($r);
        if ($sel) $sel['password'] = pw_dec($enc[$sel['id']] ?? null);
    }
    return ['rows' => $rows, 'perf' => $perf, 'sel' => $sel, 'roles' => ROLES, 'show_pw' => is_role('super_admin'), 'login_url' => (isset($_SERVER['HTTP_HOST']) ? ((($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' || !empty($_SERVER['HTTPS'])) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] : '') . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/index.php')), '/') . '/login'];
});
api('POST', 'team/save', ['super_admin'], function () {
    $id = (int)in_('id'); $role = (string)in_('role');
    if (!isset(ROLES[$role])) fail('Invalid role');
    $d = ['name' => trim((string)in_('name')), 'email' => trim((string)in_('email')) ?: null, 'emp_code' => strtoupper(trim((string)in_('emp_code'))) ?: null, 'role' => $role,
        'phone' => in_('phone'), 'designation' => in_('designation') ?: null, 'location' => in_('location'), 'area' => in_('area'), 'joined_on' => in_('joined_on') ?: null, 'status' => in_('status', 'ACTIVE'),
        'client_id' => $role === 'client' ? ((int)in_('client_id') ?: null) : null, 'vendor_id' => $role === 'vendor' ? ((int)in_('vendor_id') ?: null) : null];
    if (!$d['name'] || (!$d['email'] && !$d['emp_code'])) fail('Name and email or login code are required.');
    if ($role === 'client' && !$d['client_id']) fail('Select the client for this login.');
    if ($role === 'vendor' && !$d['vendor_id']) fail('Select the vendor for this login.');
    if ($d['email'] && one('SELECT id FROM users WHERE LOWER(email)=LOWER(?) AND id<>?', [$d['email'], $id])) fail('Email already used.');
    if ($d['emp_code'] && one('SELECT id FROM users WHERE UPPER(emp_code)=? AND id<>?', [$d['emp_code'], $id])) fail('Login code already used.');
    if ($id === (int)user()['id'] && ($d['status'] !== 'ACTIVE' || $role !== 'super_admin')) fail('You cannot deactivate or demote yourself.');
    $msg = 'Team member saved.';
    if (in_('password')) $d = pw_fields((string)in_('password')) + $d;
    if ($id) update('users', $d, 'id=?', [$id]);
    else { if (empty($d['password_hash'])) { $pw = 'Js@' . rand(10000, 99999); $d = pw_fields($pw) + $d; $msg .= " Initial password: $pw"; } $d['created_at'] = now(); $id = insert('users', $d); }
    audit('user', $id, 'SAVE');
    return ['ok' => true, 'id' => $id, 'message' => $msg];
});

/* ----- settings / masters ----- */
const TOGGLES = ['enable_vendor_qc_request' => 'Vendors can raise QC requests from their portal', 'enable_client_qc_request' => 'Clients can raise QC requests from their portal',
    'require_defect_photo' => 'At least 1 defect photo when defect qty > 0', 'gps_on_inspection_start' => 'Log GPS + timestamp when inspection starts',
    'allow_job_split' => 'Split a large job across two inspectors', 'block_qty_mismatch' => 'Block save if Accepted + Rejected ≠ Inspected',
    'auto_email_on_approve' => 'Auto-email PO report to vendor, client HO & JEWELSETU when all SKUs are approved',
    'qc_manager_role' => 'QC Manager / HOD role active (off = Super Admin reviews, approves and gets manager alerts)',
    'inspector_new_job' => 'Inspectors can create a new QC job / add SKUs on site',
    'geo_checkin_required' => 'Inspector must GPS check-in at the vendor factory before starting inspection', 'import_one_job_per_design' => 'Excel import: 1 design (SKU) = 1 job card (PO no. common, PO report merges them)', 'lock_inspected_quantity' => 'Lock inspected qty for inspectors (off = inspector can edit it; Accepted + Rejected must still equal Inspected)', 'inspector_edit_after_submit' => 'Inspector can edit a report after submit (until approved)'];
api('GET', 'settings', R_INT, function () {
    $t = []; foreach (TOGGLES as $k => $d) $t[] = ['key' => $k, 'label' => $d, 'on' => flag($k)];
    $cats = all('SELECT * FROM defect_categories ORDER BY sort, name');
    $aql = []; $A = [0.65, 1.0, 1.5, 2.5, 4.0, 6.5]; $lv = (string)in_('lv', 'II');
    foreach (AQL_CODE_TABLE as [$lo, $hi]) { $L = aql_code_letter($lo, $lv); $n = AQL_SAMPLE[array_search($L, AQL_LETTERS, true)];
        $row = ['lo' => $lo, 'hi' => $hi >= 500001 ? null : $hi, 'letter' => $L, 'sample' => $n, 'plans' => []];
        foreach ($A as $a) { $p = aql_single($L, $a); $row['plans'][] = $p ? ['ac' => $p['ac'], 're' => $p['re'], 'n' => $p['sample']] : null; }
        $aql[] = $row; }
    $S = []; foreach (['company_name', 'company_web', 'company_tagline', 'company_contact', 'report_terms', 'report_portfolio', 'mail_transport', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_secure', 'mail_from', 'mail_from_name', 'admin_email'] as $k) $S[$k] = setting($k, '');
    $S['smtp_pass_set'] = setting('smtp_pass') ? true : false; $S['geo_radius'] = setting('geo_radius', '250');
    foreach (['daily_summary_on', 'daily_summary_time', 'daily_summary_cc', 'daily_summary_last'] as $k) $S[$k] = setting($k, '');
    $S['cron_url'] = ((($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' || !empty($_SERVER['HTTPS'])) ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/index.php'), '/') . '/cron/daily?key=' . setting('cron_key', '');
    return ['can_edit' => is_role('super_admin'), 'toggles' => $t, 'settings' => $S, 'categories' => $cats,
        'defects' => all('SELECT d.*, c.name category FROM defect_master d JOIN defect_categories c ON c.id=d.category_id ORDER BY c.sort, d.name'),
        'checkpoints' => all('SELECT k.*, c.code client_code FROM checkpoints k LEFT JOIN clients c ON c.id=k.client_id ORDER BY k.client_id, k.sort, k.id'),
        'tests' => all('SELECT * FROM onsite_tests ORDER BY sort, id'), 'product_categories' => all('SELECT * FROM product_categories ORDER BY name'), 'qc_types' => all('SELECT * FROM qc_types ORDER BY id'),
        'aql' => ['level' => $lv, 'aqls' => $A, 'rows' => $aql], 'emails' => all('SELECT * FROM email_log ORDER BY id DESC LIMIT 25'),
        'clients' => all('SELECT id, code FROM clients ORDER BY code'), 'example_report_no' => 'JS/' . date('Y/m') . '/KP_AJ/001'];
});
api('POST', 'settings/save', ['super_admin'], function () {
    $a = (string)in_('a');
    switch ($a) {
        case 'toggles': foreach (TOGGLES as $k => $_) set_setting($k, in_($k) ? '1' : '0'); if ((int)in_('geo_radius') > 0) set_setting('geo_radius', (string)max(50, min(2000, (int)in_('geo_radius')))); audit('settings', 0, 'TOGGLES'); break;
        case 'email': foreach (['mail_transport', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_secure', 'mail_from', 'mail_from_name', 'admin_email'] as $k) set_setting($k, trim((string)in_($k, '')));
            if ((string)in_('smtp_pass', '') !== '') set_setting('smtp_pass', (string)in_('smtp_pass')); break;
        case 'daily': set_setting('daily_summary_on', in_('daily_summary_on') ? '1' : '0');
            $t = (string)in_('daily_summary_time', '19:30'); if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t)) fail('Time like 19:30'); set_setting('daily_summary_time', $t);
            set_setting('daily_summary_cc', trim((string)in_('daily_summary_cc', ''))); audit('settings', 0, 'DAILY_SUMMARY'); break;
        case 'testmail': require_once ROOT . '/app/mailer.php'; [$st, $er] = send_mail((string)in_('to'), 'Q-TRACK test email', '<p>Email settings work ✓</p>');
            return ['ok' => $st === 'SENT', 'message' => "Test email: $st " . ($er ?? '')];
        case 'template': foreach (['company_name', 'company_web', 'company_tagline', 'company_contact', 'report_terms', 'report_portfolio'] as $k) set_setting($k, (string)in_($k, '')); break;
        case 'add_defect': $cid = (int)in_('category_id'); if (trim((string)in_('new_cat'))) $cid = insert('defect_categories', ['name' => trim((string)in_('new_cat')), 'sort' => 99]);
            if (!$cid || !trim((string)in_('name'))) fail('Category and defect name required');
            insert('defect_master', ['category_id' => $cid, 'name' => trim((string)in_('name')), 'severity' => in_('severity', 'MAJOR')]); break;
        case 'del_defect': q('DELETE FROM defect_master WHERE id=?', [(int)in_('id')]); break;
        case 'sev_defect': q('UPDATE defect_master SET severity=? WHERE id=?', [in_('severity'), (int)in_('id')]); break;
        case 'add_check': if (!trim((string)in_('name'))) fail('Checkpoint name required');
            insert('checkpoints', ['client_id' => (int)in_('client_id') ?: null, 'category' => in_('category') ?: null, 'name' => trim((string)in_('name')), 'sort' => 50,
                'pass_remark' => in_('pass_remark') ?: 'Verified OK as per Approved Sample & Spec', 'fail_remark' => in_('fail_remark') ?: null, 'na_remark' => 'Not Applicable for this product style (N/A)']); break;
        case 'edit_check': q('UPDATE checkpoints SET pass_remark=?, fail_remark=?, na_remark=? WHERE id=?', [in_('pass_remark'), in_('fail_remark'), in_('na_remark'), (int)in_('id')]); break;
        case 'del_check': q('DELETE FROM checkpoints WHERE id=?', [(int)in_('id')]); break;
        case 'add_test': if (!trim((string)in_('name'))) fail('Test name required'); insert('onsite_tests', ['name' => trim((string)in_('name')), 'sort' => 50, 'method' => in_('method')]); break;
        case 'edit_test': q('UPDATE onsite_tests SET method=? WHERE id=?', [in_('method'), (int)in_('id')]); break;
        case 'del_test': q('DELETE FROM onsite_tests WHERE id=?', [(int)in_('id')]); break;
        case 'add_cat': if (trim((string)in_('name'))) insert('product_categories', ['name' => trim((string)in_('name'))]); break;
        case 'del_cat': q('DELETE FROM product_categories WHERE id=?', [(int)in_('id')]); break;
        case 'add_type': if (trim((string)in_('name'))) insert('qc_types', ['name' => trim((string)in_('name'))]); break;
        case 'del_type': q('DELETE FROM qc_types WHERE id=?', [(int)in_('id')]); break;
        default: fail('Unknown action');
    }
    return ['ok' => true, 'message' => 'Saved.'];
});

/* ----- portal dashboards ----- */
api('GET', 'client/dash', ['client'], function () {
    $cid = (int)user()['client_id']; $items = approved_items(); $s = sum_items($items);
    $month = array_values(array_filter($items, fn($i) => $i['approved_at'] >= date('Y-m-01')));
    return ['client' => one('SELECT name, code FROM clients WHERE id=?', [$cid]),
        'vendors' => (int)val('SELECT COUNT(*) FROM client_vendor WHERE client_id=?', [$cid]),
        'active' => (int)val("SELECT COUNT(*) FROM qc_jobs WHERE client_id=? AND status IN ('REQUESTED','SCHEDULED','ASSIGNED','IN_PROGRESS','SUBMITTED','UNDER_REVIEW','RETURNED')", [$cid]),
        'stats' => $s, 'concessions' => array_map(fn($c) => $c + ['dev' => jdec($c['deviation_json'])], all("SELECT i.id, i.report_no, i.inspected_qty, i.deviation_json, v.name vendor_name FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id JOIN vendors v ON v.id=j.vendor_id WHERE j.client_id=? AND i.concession_status='PENDING'", [$cid])),
        'holds' => (int)val("SELECT COUNT(*) FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id WHERE j.client_id=? AND i.hold_status='PENDING' AND i.hold_for='client'", [$cid]),
        'jobs' => job_rows_with_result("j.client_id=? AND j.status<>'DRAFT'", [$cid], 10), 'vendor_rates' => array_map(fn($r) => ['vendor' => $r['vendor'], 'rate' => round($r['rate'], 1)], vendor_rows($month ?: $items)),
        'rates_label' => $month ? date('M') : 'all time'];
});
api('GET', 'vendor/dash', ['vendor'], function () {
    $vid = (int)user()['vendor_id'];
    $c = fn(array $st) => (int)val("SELECT COUNT(*) FROM qc_jobs WHERE vendor_id=? AND status IN ('" . implode("','", $st) . "')", [$vid]);
    $next = one("SELECT j.id, j.req_no, j.scheduled_date, j.time_slot, j.location, c.code client_code, u.emp_code, u.name iname, u.phone FROM qc_jobs j JOIN clients c ON c.id=j.client_id LEFT JOIN users u ON u.id=j.inspector_id
       WHERE j.vendor_id=? AND j.status IN ('SCHEDULED','ASSIGNED') AND j.scheduled_date>=? ORDER BY j.scheduled_date LIMIT 1", [$vid, today()]);
    $latest = one("SELECT i.id, i.report_no, i.final_result, i.rejected_qty, (SELECT category FROM item_defects d WHERE d.item_id=i.id ORDER BY qty DESC LIMIT 1) top FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id WHERE j.vendor_id=? AND i.status='APPROVED' ORDER BY i.approved_at DESC LIMIT 1", [$vid]);
    return ['vendor' => one('SELECT name FROM vendors WHERE id=?', [$vid]), 'counts' => ['submitted' => $c(['REQUESTED']), 'scheduled' => $c(['SCHEDULED', 'ASSIGNED']),
        'progress' => $c(['IN_PROGRESS', 'SUBMITTED', 'UNDER_REVIEW', 'RETURNED']), 'completed' => $c(['EMAILED', 'CLOSED'])], 'rate' => sum_items(approved_items())['rate'],
        'holds' => (int)val("SELECT COUNT(*) FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id WHERE j.vendor_id=? AND i.hold_status='PENDING' AND i.hold_for='vendor'", [$vid]),
        'jobs' => job_rows_with_result('j.vendor_id=? AND (j.status<>\'DRAFT\' OR j.created_by=?)', [$vid, user()['id']], 12), 'next' => $next, 'latest' => $latest,
        'arrivals' => all("SELECT c.at, c.out_at, c.verified, c.dist_m, u.emp_code, u.name, u.phone, j.po_no, j.job_no FROM checkins c JOIN users u ON u.id=c.user_id LEFT JOIN qc_jobs j ON j.id=c.job_id WHERE c.vendor_id=? AND c.at>=? ORDER BY c.at DESC", [$vid, today() . ' 00:00:00'])];
});
function job_rows_with_result(string $where, array $p, int $limit): array {
    $rows = all("SELECT j.*, " . img_job_sql() . " img, c.code client_code, v.name vendor_name, (SELECT SUM(ready_qty) FROM qc_items i WHERE i.job_id=j.id) qty,
       (SELECT CASE WHEN SUM(CASE WHEN final_result='FAIL' THEN 1 ELSE 0 END)>0 THEN 'FAIL' WHEN SUM(CASE WHEN final_result='CONDITIONAL' THEN 1 ELSE 0 END)>0 THEN 'CONDITIONAL' ELSE 'PASS' END FROM qc_items i WHERE i.job_id=j.id) res
       FROM qc_jobs j JOIN clients c ON c.id=j.client_id JOIN vendors v ON v.id=j.vendor_id WHERE $where AND j.status NOT IN ('CANCELLED') ORDER BY j.id DESC LIMIT $limit", $p);
    foreach ($rows as &$r) $r['scope_label'] = scope_label($r);
    return $rows;
}

/* ----- vendor factory GPS pin captured by an inspector: Admin verifies ----- */
api('POST', 'vendors/geo_verify', ['super_admin', 'back_office', 'qc_manager'], function () {
    $v = one('SELECT * FROM vendors WHERE id=?', [(int)in_('id')]); if (!$v || !$v['geo_pending_lat']) fail('No pending factory location for this vendor.');
    if (in_('accept')) { update('vendors', ['geo_lat' => $v['geo_pending_lat'], 'geo_lng' => $v['geo_pending_lng'], 'geo_pending_lat' => null, 'geo_pending_lng' => null], 'id=?', [$v['id']]); audit('vendor', (int)$v['id'], 'GEO_PIN', 'factory pin', $v['geo_lat'] ? "{$v['geo_lat']},{$v['geo_lng']}" : null, "{$v['geo_pending_lat']},{$v['geo_pending_lng']}"); return ['ok' => true, 'message' => 'Factory location verified and saved.']; }
    update('vendors', ['geo_pending_lat' => null, 'geo_pending_lng' => null], 'id=?', [$v['id']]);
    return ['ok' => true, 'message' => 'Captured location rejected.'];
});
/* ----- inspector leave (blocks assignment, shows OFF / ON LEAVE) ----- */
api('POST', 'team/leave', ['super_admin', 'qc_manager', 'back_office'], function () {
    if ($del = (int)in_('delete')) { q('DELETE FROM leaves WHERE id=?', [$del]); audit('user', 0, 'LEAVE_REMOVED', 'leave', (string)$del, null); return ['ok' => true, 'message' => 'Leave removed.']; }
    $uid = (int)in_('user_id'); $f = (string)in_('from'); $t = (string)(in_('to') ?: $f);
    if (!$uid || !$f) fail('Select inspector and leave date.'); if ($t < $f) fail('To date is before from date.');
    $id = insert('leaves', ['user_id' => $uid, 'from_date' => $f, 'to_date' => $t, 'note' => trim((string)in_('note')), 'created_by' => user()['id'], 'created_at' => now()]);
    audit('user', $uid, 'LEAVE', 'leave', null, "$f to $t");
    $clash = all("SELECT job_no FROM qc_jobs WHERE (inspector_id=? OR inspector2_id=?) AND scheduled_date BETWEEN ? AND ? AND status IN ('ASSIGNED','IN_PROGRESS')", [$uid, $uid, $f, $t]);
    return ['ok' => true, 'id' => $id, 'message' => 'Leave saved.' . ($clash ? ' Note: already assigned on those days — ' . implode(', ', array_column($clash, 'job_no')) . '. Reassign or unassign them.' : '')];
});
