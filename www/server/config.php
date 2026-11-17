<?php
/**
 * JEWELSETU Q-TRACK — configuration
 *
 * On Linux shared hosting (cPanel / Hostinger): copy config.local.example.php to
 * config.local.php and fill in your MySQL details. On Railway / Docker the same
 * values come from environment variables.
 */
$local = __DIR__ . '/config.local.php';
$L = file_exists($local) ? (require $local) : [];

function cfg_env(array $L, string $key, $default = null) {
    if (array_key_exists($key, $L)) return $L[$key];
    $v = getenv($key);
    return ($v === false || $v === '') ? $default : $v;
}

// Railway MySQL plugin exposes MYSQLHOST / MYSQLPORT / MYSQLUSER / MYSQLPASSWORD / MYSQLDATABASE
return [
    'app_name'   => 'JEWELSETU Q-TRACK',
    'base_url'   => rtrim((string)cfg_env($L, 'APP_URL', ''), '/'),
    'db_driver'  => cfg_env($L, 'DB_DRIVER', 'mysql'),          // mysql | sqlite (local testing)
    'db_host'    => cfg_env($L, 'DB_HOST', cfg_env($L, 'MYSQLHOST', '127.0.0.1')),
    'db_port'    => cfg_env($L, 'DB_PORT', cfg_env($L, 'MYSQLPORT', '3306')),
    'db_name'    => cfg_env($L, 'DB_NAME', cfg_env($L, 'MYSQLDATABASE', 'qtrack')),
    'db_user'    => cfg_env($L, 'DB_USER', cfg_env($L, 'MYSQLUSER', 'root')),
    'db_pass'    => cfg_env($L, 'DB_PASS', cfg_env($L, 'MYSQLPASSWORD', '')),
    'sqlite_path'=> cfg_env($L, 'SQLITE_PATH', __DIR__ . '/storage/qtrack.sqlite'),
    'seed_demo'  => cfg_env($L, 'SEED_DEMO', '1') === '1',
    'timezone'   => cfg_env($L, 'APP_TZ', 'Asia/Kolkata'),
    'debug'      => cfg_env($L, 'APP_DEBUG', '0') === '1',
];
