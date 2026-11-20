<?php
/** JEWELSETU Q-TRACK — JSON API front controller (/api/...) */
require dirname(__DIR__) . '/server/app/bootstrap.php';

try {
    if (($rd = getenv('RESET_DEMO')) && db_ready() && setting('demo_reset_token') !== $rd) {
        foreach (['settings', 'clients', 'vendors', 'client_vendor', 'users', 'product_categories', 'qc_types', 'defect_categories', 'defect_master', 'checkpoints', 'onsite_tests',
                  'qc_jobs', 'qc_items', 'item_defects', 'item_photos', 'attachments', 'audit_log', 'email_log', 'notifications'] as $t) db()->exec("DROP TABLE IF EXISTS $t");
        require_once ROOT . '/app/seed.php'; db_install(); seed_all(true); set_setting('demo_reset_token', $rd);
    }
    if (!db_ready()) { require_once ROOT . '/app/seed.php'; db_install(); seed_all($CFG['seed_demo']); }
    db_migrate();
} catch (Throwable $e) {
    json_out(['error' => 'Database connection failed — check server/config.local.php' . ($CFG['debug'] ? ': ' . $e->getMessage() : '')], 500);
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = preg_replace('#^.*?/api/?#', '', $path);
if (isset($_GET['r'])) $path = (string)$_GET['r'];
$path = trim($path, '/');
if ($path === '' || $path === 'index.php') $path = 'me';

$API = [];
/** register an endpoint: api('GET', 'dashboard', [roles], fn) — roles null = public */
function api(string $method, string $name, ?array $roles, callable $fn): void { global $API; $API["$method $name"] = [$roles, $fn]; }
const R_INT = ['super_admin', 'back_office', 'qc_manager'];
const R_ALL = ['super_admin', 'back_office', 'qc_manager', 'inspector', 'client', 'vendor'];

foreach (glob(ROOT . '/api/*.php') as $f) require $f;

$m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$key = "$m $path";
if (!isset($API[$key])) json_out(['error' => 'Not found: ' . $path], 404);
[$roles, $fn] = $API[$key];
if ($roles !== null) require_role(...$roles);
if ($m === 'POST' && $path !== 'auth/login') csrf_check();
if ($path !== 'cron/daily' && function_exists('daily_due') && daily_due()) after_response('daily_run_auto');
if ($path !== 'cron/daily' && function_exists('att_due') && att_due()) after_response('att_tick');
try {
    $out = $fn();
    json_out($out ?? ['ok' => true]);
} catch (Throwable $e) {
    error_log('[qtrack] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    json_out(['error' => $CFG['debug'] ? $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() : 'Server error — please try again.'], 500);
}
