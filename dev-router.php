<?php
// Local dev only: php -S localhost:8090 -t www dev-router.php
$p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/server(/|$)#', $p)) { http_response_code(403); exit; }
if (preg_match('#^/api(/|$)#', $p)) { $_SERVER['SCRIPT_NAME'] = '/api/index.php'; require __DIR__ . '/www/api/index.php'; return true; }
if ($p !== '/' && is_file(__DIR__ . '/www' . $p)) return false;
readfile(__DIR__ . '/www/index.html');
