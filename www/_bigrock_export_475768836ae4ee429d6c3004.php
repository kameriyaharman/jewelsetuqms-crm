<?php
// TEMPORARY one-time export for the Railway -> BigRock move. Delete after migration.
// Only answers requests coming from the BigRock cPanel server.
$allowed = ['103.50.160.254'];
$xff = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
$ip = $xff ? end($xff) : '';
$real = $_SERVER['HTTP_X_REAL_IP'] ?? '';
if (!array_intersect($allowed, array_filter([$ip, $real]))) { http_response_code(403); echo 'forbidden ' . $ip . ' | ' . $real; exit; }
set_time_limit(600);
$CFG = require __DIR__ . '/server/config.php';
$what = $_GET['what'] ?? '';
if ($what === 'uploads') {
    header('Content-Type: application/gzip');
    passthru('tar -czf - -C ' . escapeshellarg(__DIR__ . '/server/uploads') . ' .');
    exit;
}
if ($what !== 'db') { echo 'ok'; exit; }
$dsn = "mysql:host={$CFG['db_host']};port={$CFG['db_port']};dbname={$CFG['db_name']};charset=utf8mb4";
$pdo = new PDO($dsn, $CFG['db_user'], $CFG['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false]);
$meta = new PDO($dsn, $CFG['db_user'], $CFG['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
header('Content-Type: text/plain; charset=utf-8');
echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
$tables = $meta->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
foreach ($tables as $row) {
    $t = $row[0];
    $create = $meta->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM)[1];
    $create = str_replace(['utf8mb4_0900_ai_ci', 'utf8mb4_0900_as_cs', 'utf8mb4_0900_bin'], ['utf8mb4_unicode_ci', 'utf8mb4_unicode_ci', 'utf8mb4_bin'], $create);
    echo "\nDROP TABLE IF EXISTS `$t`;\n$create;\n";
    $st = $pdo->query("SELECT * FROM `$t`");
    $rows = 0; $buf = [];
    while ($r = $st->fetch(PDO::FETCH_NUM)) {
        $buf[] = '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : $meta->quote((string)$v), $r)) . ')';
        $rows++;
        if (count($buf) >= 200) { echo "INSERT INTO `$t` VALUES " . implode(",\n", $buf) . ";\n"; $buf = []; }
    }
    if ($buf) echo "INSERT INTO `$t` VALUES " . implode(",\n", $buf) . ";\n";
    echo "-- $t: $rows rows\n";
    flush();
}
echo "SET FOREIGN_KEY_CHECKS=1;\n-- EXPORT COMPLETE\n";
