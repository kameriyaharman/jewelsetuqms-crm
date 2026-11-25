<?php
declare(strict_types=1);

$CFG = require dirname(__DIR__) . '/config.php';
date_default_timezone_set($CFG['timezone']);
define('ROOT', dirname(__DIR__));
define('UPLOAD_DIR', ROOT . '/uploads');
define('STORAGE_DIR', ROOT . '/storage');

ini_set('display_errors', $CFG['debug'] ? '1' : '0');
error_reporting(E_ALL);

if (!defined('NO_SESSION') && session_status() !== PHP_SESSION_ACTIVE) {
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
    'RETURNED' => 'Returned', 'APPROVED' => 'Approved', 'PARTIAL' => 'Partial QC done (open)', 'EMAILED' => 'PDF & emailed', 'CLOSED' => 'Closed',
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
    $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if (empty($GLOBALS['__after'])) { echo $body; exit; }
    // finish the response first, then run background work (e.g. the daily summary) without making the user wait
    ignore_user_abort(true); header('Content-Length: ' . strlen($body)); header('Connection: close');
    echo $body; while (ob_get_level() > 0) @ob_end_flush(); flush();
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    if (function_exists('session_write_close')) @session_write_close();
    foreach ($GLOBALS['__after'] as $f) { try { $f(); } catch (Throwable $e) { error_log('[qtrack] after: ' . $e->getMessage()); } }
    exit;
}
function after_response(callable $f): void { $GLOBALS['__after'][] = $f; }
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
/** Numbers never repeat, even after a job is deleted: next = max(last issued, highest in table) + 1. */
function seq_next(string $key, int $highest): int {
    $n = max((int)setting('seq_' . $key, '0'), $highest) + 1; set_setting('seq_' . $key, (string)$n); return $n;
}
function seq_tail(array $vals): int { $m = 0; foreach ($vals as $v) if (preg_match('/(\d+)$/', (string)$v, $x)) $m = max($m, (int)$x[1]); return $m; }
function next_req_no(): string {
    $n = seq_next('req', max(100, seq_tail(array_column(all("SELECT req_no FROM qc_jobs WHERE req_no LIKE 'REQ-%'"), 'req_no'))));
    while (val('SELECT id FROM qc_jobs WHERE req_no=?', ['REQ-' . $n])) $n = seq_next('req', $n);
    return 'REQ-' . $n;
}
function next_job_no(): string {
    $y = date('Y'); $n = seq_next("job_$y", seq_tail(array_column(all('SELECT job_no FROM qc_jobs WHERE job_no LIKE ?', ["JS-$y-%"]), 'job_no')));
    while (val('SELECT id FROM qc_jobs WHERE job_no=?', [sprintf('JS-%s-%04d', $y, $n)])) $n = seq_next("job_$y", $n);
    return sprintf('JS-%s-%04d', $y, $n);
}
/** Report no. as in sample: JS/{YYYY}/{MM}/{CLIENT}_{VENDOR}/{SERIAL} — serial per client+vendor per month */
function next_report_no(array $job): string {
    $p = 'JS/' . date('Y') . '/' . date('m') . '/' . strtoupper($job['client_code']) . '_' . vendor_short($job) . '/';
    $n = seq_next('rep_' . md5($p), seq_tail(array_column(all('SELECT report_no FROM qc_items WHERE report_no LIKE ? AND report_no NOT LIKE ?', [$p . '%', $p . 'PO-%']), 'report_no')));
    while (val('SELECT id FROM qc_items WHERE report_no=?', [$p . sprintf('%03d', $n)])) $n = seq_next('rep_' . md5($p), $n);
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
    if (!$items || in_array($job['status'], ['EMAILED', 'CLOSED', 'CANCELLED'], true)) return;
    $s = array_count_values(array_column($items, 'status')); $n = count($items);
    $new = $job['status'];
    // all lots approved: close only when every SKU's cumulative accepted qty reached its order qty, else stay open as PARTIAL QC DONE
    if (($s['APPROVED'] ?? 0) === $n) $new = job_lines_complete($jobId) ? 'APPROVED' : 'PARTIAL';
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
    return "COALESCE((SELECT ph.path FROM item_photos ph WHERE ph.item_id=$a.id AND ph.kind='ref' ORDER BY ph.id DESC LIMIT 1),(SELECT ph.path FROM item_photos ph WHERE ph.item_id=$a.id AND ph.kind='product' ORDER BY ph.id LIMIT 1),"
        . "(SELECT ax.path FROM attachments ax WHERE ax.item_id=$a.id AND (LOWER(ax.path) LIKE '%.jpg' OR LOWER(ax.path) LIKE '%.jpeg' OR LOWER(ax.path) LIKE '%.png' OR LOWER(ax.path) LIKE '%.webp') ORDER BY ax.id LIMIT 1))";
}
/** SQL expression: first product picture of a job (for job lists / cards). */
function img_job_sql(string $a = 'j'): string {
    return "(SELECT ph.path FROM item_photos ph JOIN qc_items ii ON ii.id=ph.item_id WHERE ii.job_id=$a.id AND ph.kind IN ('ref','product') ORDER BY ii.id, ph.kind DESC, ph.id LIMIT 1)";
}
function item_img(int $id): ?string { return val('SELECT ' . img_item_sql('i') . ' FROM qc_items i WHERE i.id=?', [$id]) ?: null; }

/* ---------------- QC manager routing ---------------- */
/** QC Manager / HOD role switched on in Mapping. When off, Super Admin does all manager work. */
function mgr_on(): bool { return flag('qc_manager_role'); }
/** Users who should get manager-level notifications for a job. */
function manager_ids(array $job): array {
    if (!mgr_on()) return array_map('intval', array_column(all("SELECT id FROM users WHERE role='super_admin' AND status='ACTIVE'"), 'id'));
    if (!empty($job['manager_id'])) return [(int)$job['manager_id']];
    $m = (int)val('SELECT qc_manager_id FROM clients WHERE id=?', [$job['client_id']]);
    if ($m) return [$m];
    $ids = array_map('intval', array_column(all("SELECT id FROM users WHERE role='qc_manager' AND status='ACTIVE'"), 'id'));
    return $ids ?: array_map('intval', array_column(all("SELECT id FROM users WHERE role='super_admin' AND status='ACTIVE'"), 'id'));
}
function notify_managers(array $job, string $msg, string $link): void { foreach (manager_ids($job) as $u) notify($u, $msg, $link); }

/* ---------------- per-design scope & QC lots (partial QC) ---------------- */
/** Job row with this design's own QC scope applied (a design can override the job scope). */
function item_job(array $job, array $it): array {
    if (!empty($it['i_scope'])) {
        $job['scope'] = $it['i_scope']; $job['bulk_mode'] = $it['i_bulk_mode'] ?: '100';
        if (($it['i_aql_major'] ?? '') !== '' && $it['i_aql_major'] !== null) $job['aql_major'] = (float)$it['i_aql_major'];
        $job['defined_qty'] = (int)($it['i_defined_qty'] ?? 0);
    }
    return $job;
}
function line_root_id(array $it): int { return (int)($it['parent_id'] ?: $it['id']); }
/** Pieces of one approved lot that count toward the order qty. */
function lot_accepted(array $job, array $it): int {
    if ($it['status'] !== 'APPROVED') return 0;
    $j = item_job($job, $it);
    if ($j['scope'] === 'SAMPLE') return (int)$it['order_qty'];
    if ((int)$it['inspected_qty'] >= (int)$it['ready_qty']) return (int)$it['accepted_qty'];
    return $it['final_result'] === 'FAIL' ? 0 : max(0, (int)$it['ready_qty'] - (int)$it['rejected_qty']); // sampled lot: passed lot counts
}
/** Running numbers of one SKU line (original + all QC lots): order, cumulative accepted, pending, lot log. */
function line_stats(array $job, int $root): array {
    $lots = all('SELECT * FROM qc_items WHERE id=? OR parent_id=? ORDER BY COALESCE(lot_seq,1), id', [$root, $root]);
    $r = null; foreach ($lots as $l) if ((int)$l['id'] === $root) $r = $l;
    $r = $r ?: $lots[0];
    $order = (int)$r['order_qty']; $acc = 0; $rej = 0; $open = 0; $log = [];
    foreach ($lots as $l) {
        $a = lot_accepted($job, $l); $acc += $a; if ($l['status'] === 'APPROVED') $rej += (int)$l['rejected_qty']; else $open++;
        $log[] = ['id' => (int)$l['id'], 'lot' => (int)($l['lot_seq'] ?: 1), 'date' => $l['insp_date'] ?: substr((string)$l['updated_at'], 0, 10), 'ready' => (int)$l['ready_qty'],
            'inspected' => (int)$l['inspected_qty'], 'accepted' => (int)$l['accepted_qty'], 'rejected' => (int)$l['rejected_qty'], 'counted' => $a,
            'status' => $l['status'], 'final' => $l['final_result'], 'report_no' => $l['report_no']];
    }
    $acc = $order > 0 ? min($acc, $order) : $acc;
    $closed = (int)$r['line_closed'] === 1;
    $complete = $closed || ($order > 0 ? $acc >= $order : !$open);
    return ['root' => (int)$r['id'], 'sku' => $r['sku'], 'order' => $order, 'accepted' => $acc, 'pending' => max(0, $order - $acc), 'rejected' => $rej,
        'lots' => count($lots), 'open_lots' => $open, 'closed' => $closed, 'close_note' => $r['close_note'], 'complete' => $complete,
        'can_add_lot' => !$complete && !$open, 'log' => $log];
}
/** Every SKU line of the job has reached its order qty (or was force-closed). */
function job_lines_complete(int $jobId): bool {
    $job = one('SELECT * FROM qc_jobs WHERE id=?', [$jobId]);
    foreach (all('SELECT id FROM qc_items WHERE job_id=? AND parent_id IS NULL', [$jobId]) as $r) if (!line_stats($job, (int)$r['id'])['complete']) return false;
    return true;
}
/**
 * New QC lot / re-inspection session for an existing SKU line: copies specs, reference image and attachments,
 * keeps the original order qty (repaired pieces never add to it) and re-opens the job.
 */
function add_lot(int $rootId, int $ready, ?string $date = null): int {
    $root = one('SELECT * FROM qc_items WHERE id=?', [$rootId]);
    if (!$root) fail('SKU not found', 404);
    if ($root['parent_id']) $root = one('SELECT * FROM qc_items WHERE id=?', [(int)$root['parent_id']]);
    $job = job_load((int)$root['job_id']);
    if (in_array($job['status'], ['CLOSED', 'CANCELLED', 'EMAILED'], true)) fail('Job is closed — reopen not allowed.');
    $L = line_stats($job, (int)$root['id']);
    if ($L['complete']) fail("SKU {$root['sku']} is complete ({$L['accepted']}/{$L['order']} accepted).");
    if ($L['open_lots']) fail("Finish the current QC lot of {$root['sku']} first (it is not approved yet).");
    if ($ready <= 0) fail('Enter the ready / presented qty for this lot.');
    if ($ready > $L['pending'] && !(int)$job['allow_excess']) fail("Presented qty ($ready) is more than pending qty ({$L['pending']}). Repaired pieces are part of the pending qty.");
    $copy = ['job_id', 'sku', 'product', 'metal', 'plating', 'target_weight', 'length', 'lock_type', 'stamping', 'width', 'thickness', 'style_ref', 'stone_setting',
        'spec_detail', 'instructions', 'master_sample', 'xrf_machine', 'order_qty', 'i_scope', 'i_bulk_mode', 'i_aql_major', 'i_defined_qty'];
    $d = array_intersect_key($root, array_flip($copy));
    // keep measurement specs, clear last session's actual values
    $meas = jdec($root['meas_json'] ?? '', []);
    if ($meas) { foreach ($meas as &$m) { $m['actual'] = ''; unset($m['result']); } unset($m); $d['meas_json'] = json_encode($meas); }
    $plan = item_plan(item_job($job, $root), $ready);
    $seq = (int)val('SELECT MAX(COALESCE(lot_seq,1)) FROM qc_items WHERE id=? OR parent_id=?', [$root['id'], $root['id']]) + 1;
    $id = insert('qc_items', $d + ['parent_id' => $root['id'], 'lot_seq' => $seq, 'ready_qty' => $ready, 'insp_qty' => $plan['insp_qty'], 'plan_json' => json_encode($plan['plan']),
        'reinspection' => 1, 'insp_type' => 'RE-INSPECTION', 'insp_date' => $date ?: today(), 'status' => 'PENDING', 'updated_at' => now()]);
    if ((int)$root['lot_seq'] !== 1) q('UPDATE qc_items SET lot_seq=1 WHERE id=?', [$root['id']]);
    foreach (all("SELECT * FROM item_photos WHERE item_id=? AND kind='ref'", [$root['id']]) as $p) insert('item_photos', ['item_id' => $id, 'kind' => 'ref', 'path' => $p['path'], 'remark' => 'Reference', 'created_at' => now()]);
    foreach (all('SELECT * FROM attachments WHERE item_id=?', [$root['id']]) as $a) insert('attachments', ['job_id' => $a['job_id'], 'item_id' => $id, 'path' => $a['path'], 'name' => $a['name'], 'created_at' => now()]);
    audit('item', $id, 'LOT_ADDED', 'Lot', null, "Lot $seq · ready $ready · pending before {$L['pending']}");
    audit('job', (int)$job['id'], 'LOT_ADDED', $root['sku'], null, "Lot $seq ($ready pcs)");
    if (in_array($job['status'], ['PARTIAL', 'APPROVED'], true)) { q("UPDATE qc_jobs SET status='ASSIGNED', scheduled_date=? WHERE id=?", [$date ?: today(), $job['id']]); audit('job', (int)$job['id'], 'STATUS', 'status', $job['status'], 'ASSIGNED'); }
    if ($job['inspector_id'] && (int)$job['inspector_id'] !== (int)(user()['id'] ?? 0)) notify((int)$job['inspector_id'], "QC lot $seq added · {$root['sku']} · {$job['job_no']} ($ready pcs)", 'app/job/' . $job['id']);
    return $id;
}
/** Job cards that share the same client, vendor and PO no. (Excel import makes one card per design; the PO report merges them). */
function po_group_ids(array $job): array {
    return array_map('intval', array_column(all("SELECT id FROM qc_jobs WHERE client_id=? AND vendor_id=? AND po_no=? AND status<>'CANCELLED' ORDER BY id", [$job['client_id'], $job['vendor_id'], $job['po_no']]), 'id')) ?: [(int)$job['id']];
}

/* ---------------- viewable passwords for Super Admin ----------------
 * Login still checks the one-way hash. A second, encrypted copy (AES-256-GCM) is kept so the
 * Super Admin can see the current password in Team. Key: APP_KEY env var, else a random key in settings. */
function pw_key(): string {
    static $key = null;
    if ($key !== null) return $key;
    $k = getenv('APP_KEY') ?: (string)val("SELECT v FROM settings WHERE k='app_key'");
    if ($k === '') { $k = base64_encode(random_bytes(32)); set_setting('app_key', $k); }
    return $key = hash('sha256', $k, true);
}
function pw_enc(string $plain): string {
    $iv = random_bytes(12); $tag = '';
    $c = openssl_encrypt($plain, 'aes-256-gcm', pw_key(), OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $c);
}
function pw_dec(?string $enc): ?string {
    if (!$enc) return null; $b = base64_decode($enc, true); if ($b === false || strlen($b) < 29) return null;
    $p = openssl_decrypt(substr($b, 28), 'aes-256-gcm', pw_key(), OPENSSL_RAW_DATA, substr($b, 0, 12), substr($b, 12, 16));
    return $p === false ? null : $p;
}
/** Columns to store when a password is set. */
function pw_fields(string $plain): array { return ['password_hash' => password_hash($plain, PASSWORD_DEFAULT), 'pw_enc' => pw_enc($plain)]; }

/**
 * Can this job lose its inspector? Only while ASSIGNED / SCHEDULED and nothing has been inspected yet.
 * Super Admin may force it (with warning) when inspection already started. Returns null when OK, else the reason.
 */
function unassign_block(array $job, bool $force = false): ?string {
    if (in_array($job['status'], ['REQUESTED', 'SCHEDULED', 'ASSIGNED'], true)) {
        $started = (int)val("SELECT COUNT(*) FROM qc_items WHERE job_id=? AND (started_at IS NOT NULL OR status NOT IN ('PENDING'))", [$job['id']]);
        if (!$started) return null;
    }
    $why = in_array($job['status'], ['IN_PROGRESS', 'ASSIGNED'], true) ? 'inspection has already started (IN PROGRESS)' : 'status is ' . $job['status'];
    if ($force && is_role('super_admin') && in_array($job['status'], ['ASSIGNED', 'IN_PROGRESS', 'RETURNED', 'PARTIAL'], true)) return null;
    return "Cannot unassign {$job['job_no']} — $why." . (is_role('super_admin') ? ' Tick "force" to unassign anyway (saved data stays on the job).' : ' Only Super Admin can force this.');
}
function unassign_job(array $job): void {
    $old = $job['inspector_id'];
    $st = in_array($job['status'], ['ASSIGNED', 'IN_PROGRESS'], true) ? 'SCHEDULED' : $job['status'];
    update('qc_jobs', ['inspector_id' => null, 'inspector2_id' => null, 'status' => $st], 'id=?', [$job['id']]);
    audit('job', (int)$job['id'], 'UNASSIGNED', 'inspector', $job['inspector_code'] ?? null, null);
    if ($st !== $job['status']) audit('job', (int)$job['id'], 'STATUS', 'status', $job['status'], $st);
    if ($old) notify((int)$old, "QC job {$job['job_no']} removed from your list (unassigned) · {$job['vendor_name']}", 'app');
}

/* ---------------- geo-fenced check-in & inspector live status ---------------- */
function geo_dist(float $la1, float $lo1, float $la2, float $lo2): int {
    $r = 6371000; $p1 = deg2rad($la1); $p2 = deg2rad($la2); $dp = deg2rad($la2 - $la1); $dl = deg2rad($lo2 - $lo1);
    $a = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;
    return (int)round(2 * $r * asin(min(1, sqrt($a))));
}
function geo_fmt(int $m): string { return $m >= 1000 ? number_format($m / 1000, 1) . ' km' : $m . ' m'; }
/** Leave note if the user is on leave that date, else null. */
function on_leave(int $uid, string $date): ?string {
    $l = one('SELECT note, from_date, to_date FROM leaves WHERE user_id=? AND from_date<=? AND to_date>=? ORDER BY id DESC LIMIT 1', [$uid, $date, $date]);
    return $l ? ($l['note'] ?: 'leave ' . fdate($l['from_date'], 'd M') . ($l['to_date'] !== $l['from_date'] ? '–' . fdate($l['to_date'], 'd M') : '')) : null;
}
/** Today's open check-in of an inspector (at a factory, not checked out). */
/** HR day maths from one inspector's check-in sessions of one day:
 *  net shift = first check-in → last check-out, on-site = sum of sessions, transit = gaps between sessions. Minutes. */
function att_day_calc(array $ses): array {
    if (!$ses) return ['first_in' => null, 'last_out' => null, 'net' => 0, 'active' => 0, 'transit' => 0, 'open' => false];
    $first = null; $last = null; $active = 0; $open = false;
    foreach ($ses as $s) {
        $a = strtotime($s['at']); $isToday = substr($s['at'], 0, 10) === date('Y-m-d');
        $b = $s['out_at'] ? strtotime($s['out_at']) : ($isToday ? time() : null);
        if (!$s['out_at']) $open = true;
        if ($first === null || $a < $first) $first = $a;
        if ($b !== null) { $active += max(0, $b - $a); if ($last === null || $b > $last) $last = $b; }
    }
    $net = $last !== null ? max(0, $last - $first) : 0; $active = min($active, $net ?: $active);
    return ['first_in' => date('Y-m-d H:i:s', $first), 'last_out' => $last !== null ? date('Y-m-d H:i:s', $last) : null,
        'net' => (int)round($net / 60), 'active' => (int)round($active / 60), 'transit' => (int)round(max(0, $net - $active) / 60), 'open' => $open];
}
function open_checkin(int $uid, ?string $date = null): ?array {
    $d = $date ?: today();
    return one("SELECT c.*, COALESCE(v.name, c.place) vendor_name FROM checkins c LEFT JOIN vendors v ON v.id=c.vendor_id WHERE c.user_id=? AND c.out_at IS NULL AND c.at>=? AND c.at<? ORDER BY c.id DESC LIMIT 1", [$uid, "$d 00:00:00", date('Y-m-d', strtotime("$d +1 day")) . ' 00:00:00']) ?: null;
}
/** 4-state live status of an inspector for a date: AVAILABLE / ASSIGNED (en-route) / ONSITE / LEAVE. */
function insp_status(int $uid, string $date, int $excludeJob = 0): array {
    if ($lv = on_leave($uid, $date)) return ['code' => 'LEAVE', 'label' => 'OFF / ON LEAVE', 'sub' => $lv, 'jobs' => 0];
    $jobs = (int)val("SELECT COUNT(*) FROM qc_jobs WHERE (inspector_id=? OR inspector2_id=?) AND scheduled_date=? AND status IN ('ASSIGNED','IN_PROGRESS') AND id<>?", [$uid, $uid, $date, $excludeJob]);
    if ($date === today() && ($c = open_checkin($uid))) return ['code' => 'ONSITE', 'label' => 'ON-SITE', 'sub' => 'At ' . ($c['vendor_name'] ?: 'factory') . ' · since ' . date('h:i A', strtotime($c['at'])) . ($c['verified'] ? '' : ' (GPS not verified)'), 'jobs' => $jobs, 'vendor_id' => (int)$c['vendor_id']];
    if ($jobs) return ['code' => 'ASSIGNED', 'label' => $date === today() ? 'ASSIGNED · EN-ROUTE' : 'ASSIGNED', 'sub' => "$jobs job(s) that day", 'jobs' => $jobs];
    return ['code' => 'AVAILABLE', 'label' => 'AVAILABLE', 'sub' => 'free', 'jobs' => 0];
}
/** Stone and setting arrive in two template columns; one stored field keeps reports and edits unchanged ("CZ / Prong"). */
function stone_join(string $stone, string $setting): string {
    $clean = fn($x) => in_array(trim($x), ['', '-', '—'], true) ? '' : trim($x);
    $a = $clean($stone); $b = $clean($setting);
    if ($a === '' && $b === '') return trim($stone) !== '' || trim($setting) !== '' ? '—' : '';
    return $a !== '' && $b !== '' ? "$a / $b" : ($a ?: $b);
}
