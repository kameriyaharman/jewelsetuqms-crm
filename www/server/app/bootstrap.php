<?php
declare(strict_types=1);

$CFG = require dirname(__DIR__) . '/config.php';
date_default_timezone_set($CFG['timezone']);
define('ROOT', dirname(__DIR__));
define('UPLOAD_DIR', ROOT . '/uploads');
define('STORAGE_DIR', ROOT . '/storage');

ini_set('display_errors', $CFG['debug'] ? '1' : '0');
error_reporting(E_ALL);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('QTRACKSESS');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

require __DIR__ . '/db.php';
require __DIR__ . '/aql.php';
require __DIR__ . '/stats.php';

/* ---------------- constants ---------------- */
const ROLES = [
    'super_admin' => 'Super Admin',
    'back_office' => 'Back Office',
    'qc_manager'  => 'QC Manager',
    'inspector'   => 'QC Inspector',
    'client'      => 'Client User',
    'vendor'      => 'Vendor User',
];
const INTERNAL_ROLES = ['super_admin', 'back_office', 'qc_manager'];

const JOB_STATUS = [
    'DRAFT' => 'Draft', 'REQUESTED' => 'Requested', 'SCHEDULED' => 'Scheduled', 'ASSIGNED' => 'Assigned',
    'IN_PROGRESS' => 'In progress', 'SUBMITTED' => 'Submitted', 'UNDER_REVIEW' => 'Under review',
    'RETURNED' => 'Returned', 'APPROVED' => 'Approved', 'EMAILED' => 'PDF & emailed', 'CLOSED' => 'Closed',
    'CANCELLED' => 'Cancelled',
];
const ITEM_STATUS = [
    'PENDING' => 'Pending', 'IN_PROGRESS' => 'In progress', 'HOLD' => 'On hold', 'COMPLETED' => 'Completed',
    'SUBMITTED' => 'Submitted', 'UNDER_REVIEW' => 'Under review', 'RETURNED' => 'Returned', 'APPROVED' => 'Approved',
];
const STEPS = [1 => 'General info', 2 => 'Quantity', 3 => 'Quality checks', 4 => 'Measurements',
               5 => 'On-site tests', 6 => 'Defects', 7 => 'Photos', 8 => 'Final result'];

/* ---------------- helpers ---------------- */
function h($s): string { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
function base(): string {
    static $b = null;
    if ($b === null) {
        $d = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        $b = $d === '.' ? '' : $d;
    }
    return $b;
}
function url(string $path = '', array $q = []): string {
    $u = base() . '/' . ltrim($path, '/');
    return $q ? $u . '?' . http_build_query($q) : $u;
}
function asset(string $p): string {
    $f = ROOT . '/assets/' . $p;
    return url('assets/' . $p) . (file_exists($f) ? '?v=' . filemtime($f) : '');
}
function redirect(string $path, array $q = []): never { header('Location: ' . url($path, $q)); exit; }
function back(): never {
    $r = $_SERVER['HTTP_REFERER'] ?? url('/');
    header('Location: ' . $r); exit;
}
function flash(string $msg, string $type = 'ok'): void { $_SESSION['flash'][] = [$type, $msg]; }
function flashes(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }
function post(string $k, $d = null) { return $_POST[$k] ?? $d; }
function get(string $k, $d = null) { return $_GET[$k] ?? $d; }
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }
function now(): string { return date('Y-m-d H:i:s'); }
function today(): string { return date('Y-m-d'); }
function fdate($d, string $f = 'd M Y'): string { return $d ? date($f, strtotime((string)$d)) : '—'; }
function fdt($d): string { return $d ? date('d M · h:i A', strtotime((string)$d)) : '—'; }
function num($n): string {
    // Indian digit grouping: 1,25,000
    $n = (int)round((float)$n); $neg = $n < 0; $s = (string)abs($n);
    if (strlen($s) > 3) { $last = substr($s, -3); $rest = substr($s, 0, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest); $s = $rest . ',' . $last; }
    return ($neg ? '-' : '') . $s;
}
function pct($a, $b, int $dp = 1): string { return $b > 0 ? number_format($a * 100 / $b, $dp) . '%' : '0%'; }
function initials(string $n): string {
    $p = preg_split('/\s+/', trim($n)); $i = strtoupper(substr($p[0] ?? '', 0, 1) . substr($p[1] ?? '', 0, 1));
    return $i ?: '?';
}
function json_out($data, int $code = 200): never {
    http_response_code($code); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); exit;
}
/** API error helper */
function fail(string $msg, int $code = 422): never { json_out(['error' => $msg], $code); }
/** merged request input: query + form + JSON body */
function inp(): array {
    static $in = null;
    if ($in === null) {
        $in = $_GET;
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($ct, 'application/json')) $in = array_merge($in, jdec(file_get_contents('php://input')));
        else $in = array_merge($in, $_POST);
    }
    return $in;
}
function in_(string $k, $d = null) { $i = inp(); return $i[$k] ?? $d; }
function jdec($s, $d = []) { $v = json_decode((string)$s, true); return is_array($v) ? $v : $d; }

function badge(string $status, ?string $label = null): string {
    $cls = strtolower(str_replace(['_', ' ', '&'], '-', $status));
    $label ??= JOB_STATUS[$status] ?? ITEM_STATUS[$status] ?? ucwords(strtolower(str_replace('_', ' ', $status)));
    return '<span class="badge b-' . h($cls) . '">' . h(strtoupper($label)) . '</span>';
}

/* ---------------- CSRF ---------------- */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">'; }
function csrf_check(): void {
    $t = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '';
    if (!hash_equals(csrf_token(), (string)$t)) json_out(['error' => 'Session expired — please reload the page.'], 419);
}

/* ---------------- settings ---------------- */
function setting(string $k, $d = null) {
    static $cache = null;
    if ($cache === null) { $cache = []; foreach (all('SELECT k, v FROM settings') as $r) $cache[$r['k']] = $r['v']; }
    return $cache[$k] ?? $d;
}
function set_setting(string $k, $v): void {
    if (one('SELECT k FROM settings WHERE k=?', [$k])) q('UPDATE settings SET v=? WHERE k=?', [(string)$v, $k]);
    else insert('settings', ['k' => $k, 'v' => (string)$v]);
}
function flag(string $k): bool { return setting($k, '0') === '1'; }

/* ---------------- auth ---------------- */
function user(): ?array {
    static $u = false;
    if ($u === false) {
        $u = null;
        if (!empty($_SESSION['uid'])) $u = one('SELECT * FROM users WHERE id=? AND status=?', [$_SESSION['uid'], 'ACTIVE']);
    }
    return $u;
}
function role(): string { return user()['role'] ?? ''; }
function is_role(string ...$r): bool { return in_array(role(), $r, true); }
function is_internal(): bool { return is_role(...INTERNAL_ROLES); }
function require_login(): void { if (!user()) json_out(['error' => 'Please log in', 'auth' => false], 401); }
function require_role(string ...$roles): void {
    require_login();
    if (!in_array(role(), $roles, true)) json_out(['error' => 'Access denied'], 403);
}
function home_for(string $role): string {
    return match ($role) { 'inspector' => 'app', 'client' => 'client', 'vendor' => 'vendor', default => 'dashboard' };
}

/** Row-level security: SQL fragment + params restricting qc_jobs (alias j) to what this user may see */
function job_scope(string $a = 'j'): array {
    $u = user();
    return match ($u['role'] ?? '') {
        'client'    => ["$a.client_id = ?", [(int)$u['client_id']]],
        'vendor'    => ["$a.vendor_id = ?", [(int)$u['vendor_id']]],
        'inspector' => ["$a.inspector_id = ?", [(int)$u['id']]],
        default     => ['1=1', []],
    };
}
function can_see_job(array $job): bool {
    $u = user();
    return match ($u['role']) {
        'client' => (int)$job['client_id'] === (int)$u['client_id'],
        'vendor' => (int)$job['vendor_id'] === (int)$u['vendor_id'],
        'inspector' => (int)$job['inspector_id'] === (int)$u['id'],
        default => true,
    };
}

/* ---------------- audit & notifications ---------------- */
function audit(string $entity, int $id, string $action, ?string $field = null, $old = null, $new = null, int $rev = 0): void {
    insert('audit_log', ['entity' => $entity, 'entity_id' => $id, 'user_id' => user()['id'] ?? null,
        'action' => $action, 'field' => $field, 'old_val' => is_scalar($old) || $old === null ? $old : json_encode($old),
        'new_val' => is_scalar($new) || $new === null ? $new : json_encode($new), 'rev' => $rev, 'created_at' => now()]);
}
function notify(int $userId, string $text, string $link = ''): void {
    insert('notifications', ['user_id' => $userId, 'text' => $text, 'link' => $link, 'is_read' => 0, 'created_at' => now()]);
}
function notify_role(string $role, string $text, string $link = '', array $where = []): void {
    $sql = 'SELECT id FROM users WHERE role=? AND status=?'; $p = [$role, 'ACTIVE'];
    foreach ($where as $k => $v) { $sql .= " AND $k=?"; $p[] = $v; }
    foreach (all($sql, $p) as $r) notify((int)$r['id'], $text, $link);
}
function notify_job_parties(array $job, string $text, string $link = ''): void {
    notify_role('client', $text, $link, ['client_id' => $job['client_id']]);
    notify_role('vendor', $text, $link, ['vendor_id' => $job['vendor_id']]);
}

/* ---------------- domain helpers ---------------- */
function job_load(int $id): ?array {
    return one('SELECT j.*, c.code client_code, c.name client_name, c.ho_email client_ho_email, c.email client_email,
        v.name vendor_name, v.code vendor_code, v.email vendor_email, v.ho_email vendor_ho_email, v.address vendor_address,
        i.name inspector_name, i.emp_code inspector_code, m.name manager_name
        FROM qc_jobs j JOIN clients c ON c.id=j.client_id JOIN vendors v ON v.id=j.vendor_id
        LEFT JOIN users i ON i.id=j.inspector_id LEFT JOIN users m ON m.id=j.manager_id WHERE j.id=?', [$id]);
}
function scope_label(array $j): string {
    if ($j['scope'] === 'SAMPLE') return 'Sample QC';
    return match ($j['bulk_mode']) {
        'AQL' => 'Bulk · AQL ' . ($j['insp_level'] ?: 'II') . ' ' . rtrim(rtrim((string)$j['aql_major'], '0'), '.'),
        'DEFINED' => 'Bulk · Defined ' . (int)$j['defined_qty'],
        default => 'Bulk · 100%',
    };
}
function next_req_no(): string {
    $n = (int)val('SELECT COUNT(*) FROM qc_jobs') + 101;
    while (val('SELECT id FROM qc_jobs WHERE req_no=?', ['REQ-' . $n])) $n++;
    return 'REQ-' . $n;
}
function next_job_no(): string {
    $y = date('Y'); $n = (int)val("SELECT COUNT(*) FROM qc_jobs WHERE job_no LIKE ?", ["JS-$y-%"]) + 1;
    while (val('SELECT id FROM qc_jobs WHERE job_no=?', [sprintf('JS-%s-%04d', $y, $n)])) $n++;
    return sprintf('JS-%s-%04d', $y, $n);
}
/** Report no. as in sample: JS/{YYYY}/{MM}/{CLIENT}_{VENDOR}/{SERIAL} — serial per client+vendor per month */
function next_report_no(array $job): string {
    $p = 'JS/' . date('Y') . '/' . date('m') . '/' . strtoupper($job['client_code']) . '_' . vendor_short($job) . '/';
    $n = (int)val("SELECT COUNT(*) FROM qc_items WHERE report_no LIKE ? AND report_no NOT LIKE ?", [$p . '%', $p . 'PO-%']) + 1;
    while (val('SELECT id FROM qc_items WHERE report_no=?', [$p . sprintf('%03d', $n)])) $n++;
    return $p . sprintf('%03d', $n);
}
function po_report_no(array $job): string {
    $d = $job['approved_at'] ?: now();
    return 'JS/' . date('Y', strtotime($d)) . '/' . date('m', strtotime($d)) . '/' . strtoupper($job['client_code']) . '_' . vendor_short($job) . '/PO-' . preg_replace('/[^A-Za-z0-9\-]/', '', (string)$job['po_no']);
}
function file_safe(string $s): string { return preg_replace('/[^A-Za-z0-9_\-]/', '_', $s); }
/** sampling plan for one SKU given the job scope */
function item_plan(array $job, int $ready): array {
    if ($job['scope'] === 'SAMPLE') return ['insp_qty' => min($ready, max(1, (int)($job['defined_qty'] ?: $ready))), 'plan' => null];
    if ($job['bulk_mode'] === 'AQL') {
        $plan = aql_plan($ready, $job['insp_level'] ?: 'II', (float)$job['aql_critical'], (float)$job['aql_major'], (float)$job['aql_minor']);
        return ['insp_qty' => $plan['sample'], 'plan' => $plan];
    }
    if ($job['bulk_mode'] === 'DEFINED') return ['insp_qty' => min($ready, (int)$job['defined_qty']), 'plan' => null];
    return ['insp_qty' => $ready, 'plan' => null];
}
/**
 * System recommendation PASS / FAIL.
 * AQL jobs: ISO 2859-1 Ac/Re per defect class.
 * 100% / defined / sample jobs: client threshold rule — FAIL if Reject/Repair % >= client fail_threshold (default 80%).
 */
function system_result(array $job, array $item, array $defects, array $checks = [], array $meas = [], array $tests = []): string {
    $sev = ['CRITICAL' => 0, 'MAJOR' => 0, 'MINOR' => 0];
    foreach ($defects as $d) $sev[$d['severity']] = ($sev[$d['severity']] ?? 0) + (int)$d['qty'];
    $plan = jdec($item['plan_json'] ?? '', []);
    if ($job['scope'] === 'BULK' && $job['bulk_mode'] === 'AQL' && $plan) {
        foreach (['CRITICAL' => 'critical', 'MAJOR' => 'major', 'MINOR' => 'minor'] as $S => $k)
            if (isset($plan[$k]['re']) && $sev[$S] >= $plan[$k]['re']) return 'FAIL';
        return 'PASS';
    }
    $insp = max(1, (int)$item['inspected_qty']);
    $thr = (float)($job['client_fail_threshold'] ?? 80) ?: 80;
    return ((int)$item['rejected_qty'] * 100 / $insp) >= $thr ? 'FAIL' : 'PASS';
}
function client_threshold(int $clientId): float { return (float)(val('SELECT fail_threshold FROM clients WHERE id=?', [$clientId]) ?: 80); }
function result_rule_text(array $job): string {
    if ($job['scope'] === 'BULK' && $job['bulk_mode'] === 'AQL') return 'AQL ' . rtrim(rtrim((string)$job['aql_major'], '0'), '.') . ' (ISO 2859-1 Ac/Re)';
    return 'Fail if >=' . rtrim(rtrim(number_format(client_threshold((int)$job['client_id']), 1), '0'), '.') . '% Rej/Repair';
}
function vendor_short(array $job): string {
    $s = val('SELECT short_code FROM vendors WHERE id=?', [$job['vendor_id']]);
    return strtoupper($s ?: preg_replace('/[^A-Z0-9]/', '', preg_replace('/^VND-?/i', '', strtoupper((string)val('SELECT code FROM vendors WHERE id=?', [$job['vendor_id']])))));
}
/** Report title as in the JEWELSETU sample, e.g. "100% PRE-SHIPMENT INSPECTION (PSI) REPORT" */
function report_title(array $job): string {
    $mode = $job['scope'] === 'SAMPLE' ? 'SAMPLE' : match ($job['bulk_mode']) { 'AQL' => 'AQL', 'DEFINED' => 'DEFINED SAMPLE', default => '100%' };
    $t = strtolower((string)$job['qc_type']);
    $type = match (true) { str_contains($t, 'inline') => 'INLINE INSPECTION (DUPRO)', str_contains($t, 'pre-production') => 'PRE-PRODUCTION INSPECTION (PPI)',
        str_contains($t, 're-insp') => 'RE-INSPECTION', str_contains($t, 'final') => 'FINAL RANDOM INSPECTION (FRI)', default => 'PRE-SHIPMENT INSPECTION (PSI)' };
    return "$mode $type REPORT";
}
function refresh_job_status(int $jobId): void {
    $items = all('SELECT status FROM qc_items WHERE job_id=?', [$jobId]);
    $job = one('SELECT status FROM qc_jobs WHERE id=?', [$jobId]);
    if (!$items || in_array($job['status'], ['EMAILED', 'CLOSED'], true)) return;
    $s = array_count_values(array_column($items, 'status')); $n = count($items);
    $new = $job['status'];
    if (($s['APPROVED'] ?? 0) === $n) $new = 'APPROVED';
    elseif (($s['RETURNED'] ?? 0) > 0) $new = 'RETURNED';
    elseif (($s['UNDER_REVIEW'] ?? 0) > 0) $new = 'UNDER_REVIEW';
    elseif ((($s['SUBMITTED'] ?? 0) + ($s['APPROVED'] ?? 0)) === $n) $new = 'SUBMITTED';
    elseif (($s['IN_PROGRESS'] ?? 0) + ($s['HOLD'] ?? 0) + ($s['COMPLETED'] ?? 0) + ($s['SUBMITTED'] ?? 0) > 0) $new = 'IN_PROGRESS';
    if ($new !== $job['status']) { q('UPDATE qc_jobs SET status=? WHERE id=?', [$new, $jobId]); audit('job', $jobId, 'STATUS', 'status', $job['status'], $new); }
}

/* ---------------- uploads ---------------- */
function save_upload(array $f, string $sub, array $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'xlsx', 'xls', 'csv', 'webp']): ?array {
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) return null;
    $dir = UPLOAD_DIR . '/' . $sub; if (!is_dir($dir)) mkdir($dir, 0775, true);
    $name = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], "$dir/$name")) return null;
    return ['path' => "$sub/$name", 'name' => basename($f['name'])];
}
function files_array(string $field): array {
    $out = []; if (empty($_FILES[$field])) return $out; $F = $_FILES[$field];
    if (!is_array($F['name'])) return [$F];
    foreach ($F['name'] as $i => $n) $out[] = ['name' => $n, 'type' => $F['type'][$i], 'tmp_name' => $F['tmp_name'][$i], 'error' => $F['error'][$i], 'size' => $F['size'][$i]];
    return $out;
}

/* ---------------- views ---------------- */


function user_opts(string $role): array {
    $o = []; foreach (all('SELECT id, name, emp_code FROM users WHERE role=? AND status=? ORDER BY emp_code, name', [$role, 'ACTIVE']) as $u)
        $o[$u['id']] = ($u['emp_code'] ? $u['emp_code'] . ' · ' : '') . $u['name'];
    return $o;
}

/* ---------------- product images ---------------- */
/** SQL expression: path of an item's product picture (product photo, else an image attached to the SKU). */
function img_item_sql(string $a = 'i'): string {
    return "COALESCE((SELECT ph.path FROM item_photos ph WHERE ph.item_id=$a.id AND ph.kind='product' ORDER BY ph.id LIMIT 1),"
        . "(SELECT ax.path FROM attachments ax WHERE ax.item_id=$a.id AND (LOWER(ax.path) LIKE '%.jpg' OR LOWER(ax.path) LIKE '%.jpeg' OR LOWER(ax.path) LIKE '%.png' OR LOWER(ax.path) LIKE '%.webp') ORDER BY ax.id LIMIT 1))";
}
/** SQL expression: first product picture of a job (for job lists / cards). */
function img_job_sql(string $a = 'j'): string {
    return "(SELECT ph.path FROM item_photos ph JOIN qc_items ii ON ii.id=ph.item_id WHERE ii.job_id=$a.id AND ph.kind='product' ORDER BY ii.id, ph.id LIMIT 1)";
}
function item_img(int $id): ?string { return val('SELECT ' . img_item_sql('i') . ' FROM qc_items i WHERE i.id=?', [$id]) ?: null; }
