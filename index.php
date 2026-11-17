<?php
/** JEWELSETU Q-TRACK — front controller */
require __DIR__ . '/app/bootstrap.php';

try {
    if (!db_ready()) {
        require __DIR__ . '/app/seed.php';
        db_install();
        seed_all($CFG['seed_demo']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo '<h2 style="font-family:sans-serif">Database connection failed</h2><p style="font-family:sans-serif">Check config.local.php / environment variables.</p>';
    if ($CFG['debug']) echo '<pre>' . h($e->getMessage()) . '</pre>';
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (base() !== '' && str_starts_with($path, base())) $path = substr($path, strlen(base()));
$path = trim($path, '/');
if ($path === 'index.php') $path = '';
if (isset($_GET['r'])) $path = trim((string)$_GET['r'], '/'); // fallback when mod_rewrite is off

$I = INTERNAL_ROLES;
$ALL = array_keys(ROLES);
// path => [page file, allowed roles]
$routes = [
    'login'            => ['login', null],
    'logout'           => ['logout', null],
    ''                 => ['home', $ALL],
    'dashboard'        => ['dashboard', $I],
    'clients'          => ['clients', ['super_admin', 'back_office']],
    'vendors'          => ['vendors', [...$I, 'client']],
    'mapping'          => ['mapping', ['super_admin']],
    'requests'         => ['requests', [...$I, 'client', 'vendor']],
    'request'          => ['request_view', [...$I, 'client', 'vendor']],
    'request-new'      => ['request_form', ['super_admin', 'back_office', 'client', 'vendor']],
    'import'           => ['import', ['super_admin', 'back_office', 'vendor']],
    'import-template'  => ['import_template', ['super_admin', 'back_office', 'vendor']],
    'jobs'             => ['jobs', $I],
    'schedule'         => ['schedule', [...$I, 'vendor', 'client']],
    'review'           => ['review_list', ['super_admin', 'qc_manager']],
    'review-item'      => ['review_item', ['super_admin', 'qc_manager']],
    'reports'          => ['reports', [...$I, 'client', 'vendor']],
    'report'           => ['report_view', [...$I, 'client', 'vendor']],
    'pdf'              => ['pdf', [...$I, 'client', 'vendor', 'inspector']],
    'approvals'        => ['approvals', ['client', 'vendor', 'super_admin', 'qc_manager']],
    'analytics'        => ['analytics', [...$I, 'client']],
    'team'             => ['team', ['super_admin', 'qc_manager', 'back_office']],
    'settings'         => ['settings', ['super_admin', 'back_office', 'qc_manager']],
    'client'           => ['client_dash', ['client']],
    'vendor'           => ['vendor_dash', ['vendor']],
    'notifications'    => ['notifications', $ALL],
    'profile'          => ['profile', $ALL],
    'file'             => ['file', $ALL],
    'job-action'       => ['job_action', $ALL],
    // inspector app (PWA)
    'app'              => ['app_home', ['inspector']],
    'app/job'          => ['app_job', ['inspector']],
    'app/inspect'      => ['app_inspect', ['inspector']],
    'app/history'      => ['app_history', ['inspector']],
    'app/alerts'       => ['app_alerts', ['inspector']],
    'app/profile'      => ['app_profile', ['inspector']],
    'api/inspect'      => ['api_inspect', ['inspector']],
];

if (!isset($routes[$path])) { http_response_code(404); require_login(); render('404', ['title' => 'Not found']); exit; }
[$page, $roles] = $routes[$path];
if ($roles !== null) require_role(...$roles);
if (is_post() && !in_array($path, ['login'], true)) csrf_check();
require __DIR__ . '/app/pages/' . $page . '.php';
