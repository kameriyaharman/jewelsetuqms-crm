<?php
// Local dev only: php -S localhost:8090 -t www dev-router.php   (mirrors www/.htaccess)
$p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/server(/|$)#', $p)) { http_response_code(403); exit; }
if (preg_match('#^/api(/|$)#', $p)) { $_SERVER['SCRIPT_NAME'] = '/api/index.php'; require __DIR__ . '/www/api/index.php'; return true; }
if ($p !== '/' && is_file(__DIR__ . '/www' . $p)) return false;
if (preg_match('#^/erp(/|$)#', $p)) { readfile(__DIR__ . '/www/erp/index.html'); return true; }
$_SERVER['SCRIPT_NAME'] = '/index.php'; require __DIR__ . '/www/index.php'; return true;
