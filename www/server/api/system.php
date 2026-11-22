<?php
/* ---------- Super Admin: full backup download + "clear ERP test data" ---------- */

const ERP_DATA_TABLES = ['qc_jobs', 'qc_items', 'item_defects', 'item_photos', 'attachments', 'checkins', 'leaves', 'manuals', 'manual_acks', 'notifications', 'email_log', 'audit_log'];
const ERP_UPLOAD_DIRS = ['photos', 'reports', 'imports', 'specs', 'manuals'];   // kept: avatars (profile photos), site*, magnets (website)

function sys_tables(): array {
    return is_sqlite() ? array_column(all("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"), 'name')
        : array_map(fn($r) => array_values($r)[0], all('SHOW TABLES'));
}
/** Writes the whole database (schema + rows) and every uploaded file into one ZIP. */
function backup_to_file(string $file): array {
    if (!class_exists('ZipArchive')) fail('PHP zip extension is missing on this server.');
    @set_time_limit(0); @ini_set('memory_limit', '512M');
    $z = new ZipArchive(); if ($z->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) fail('Could not create the backup file.');
    $meta = ['app' => 'jewelsetu', 'at' => date('c'), 'schema' => setting('schema_version'), 'tables' => []]; $tmps = [];
    foreach (sys_tables() as $t) {
        if (!is_sqlite()) $z->addFromString("db/$t.sql", (string)(one("SHOW CREATE TABLE `$t`")['Create Table'] ?? ''));
        $tmp = STORAGE_DIR . "/bk-$t-" . bin2hex(random_bytes(4)) . '.ndjson'; $fh = fopen($tmp, 'w'); $n = 0;
        $st = db()->query("SELECT * FROM $t");
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) { fwrite($fh, json_encode($r, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n"); $n++; }
        fclose($fh); $z->addFile($tmp, "db/$t.ndjson"); $meta['tables'][$t] = $n; $tmps[] = $tmp;
    }
    $files = 0;
    if (is_dir(UPLOAD_DIR)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(UPLOAD_DIR, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) { if (!$f->isFile() || $f->getFilename() === '.keep') continue;
            $rel = substr(str_replace('\\', '/', $f->getPathname()), strlen(str_replace('\\', '/', UPLOAD_DIR)) + 1);
            $z->addFile($f->getPathname(), 'uploads/' . $rel); $files++; }
    }
    $meta['files'] = $files;
    $z->addFromString('backup.json', json_encode($meta, JSON_PRETTY_PRINT));
    $z->close(); foreach ($tmps as $t) @unlink($t);
    return $meta;
}
function rrmdir(string $d): int {
    if (!is_dir($d)) return 0; $n = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
        if ($f->isDir()) @rmdir($f->getPathname()); elseif (@unlink($f->getPathname())) $n++;
    }
    @rmdir($d); return $n;
}

api('GET', 'sys/backup', ['super_admin'], function () {
    $file = STORAGE_DIR . '/download-' . bin2hex(random_bytes(6)) . '.zip';
    backup_to_file($file);
    header('Content-Type: application/zip'); header('Content-Length: ' . filesize($file)); header('Cache-Control: no-store');
    header('Content-Disposition: attachment; filename="jewelsetu-backup-' . date('Ymd-His') . '.zip"');
    $fp = fopen($file, 'rb'); while (!feof($fp)) { echo fread($fp, 1 << 20); flush(); } fclose($fp);
    @unlink($file); exit;
});

api('GET', 'sys/data-counts', ['super_admin'], function () {
    $c = []; foreach (ERP_DATA_TABLES as $t) { try { $c[$t] = (int)val("SELECT COUNT(*) FROM $t"); } catch (Throwable $e) { $c[$t] = 0; } }
    $keep = []; foreach (['users', 'clients', 'vendors', 'client_vendor', 'site_posts', 'site_leads'] as $t) { try { $keep[$t] = (int)val("SELECT COUNT(*) FROM $t"); } catch (Throwable $e) {} }
    $bk = array_map(fn($f) => ['name' => basename($f), 'size' => filesize($f), 'at' => date('Y-m-d H:i', filemtime($f))], glob(STORAGE_DIR . '/backups/*.zip') ?: []);
    return ['clear' => $c, 'keep' => $keep, 'backups' => array_reverse($bk)];
});

/** Deletes all ERP transactions (jobs, inspections, reports, photos, attendance, logs) after saving a full backup on the server.
 *  Keeps: every login / emp ID, clients, vendors, mapping, masters, settings, the website + admin data. */
api('POST', 'sys/clear-test-data', ['super_admin'], function () {
    if (in_('confirm') !== 'DELETE') fail('Type DELETE to confirm.');
    @mkdir(STORAGE_DIR . '/backups', 0775, true);
    $bk = STORAGE_DIR . '/backups/before-clear-' . date('Ymd-His') . '.zip';
    backup_to_file($bk);
    if (!is_file($bk) || filesize($bk) < 100) fail('Backup could not be saved — nothing was deleted.');
    $done = [];
    foreach (ERP_DATA_TABLES as $t) { try { $done[$t] = (int)val("SELECT COUNT(*) FROM $t"); db()->exec(is_sqlite() ? "DELETE FROM $t" : "TRUNCATE TABLE $t"); } catch (Throwable $e) {} }
    q("DELETE FROM settings WHERE k LIKE 'seq\\_%'");   // request / job / report numbering starts again
    foreach (['daily_summary_last'] as $k) q('DELETE FROM settings WHERE k=?', [$k]);
    $files = 0; foreach (ERP_UPLOAD_DIRS as $d) $files += rrmdir(UPLOAD_DIR . '/' . $d);
    $uid = (int)user()['id'];
    insert('audit_log', ['entity' => 'system', 'entity_id' => 0, 'user_id' => $uid, 'action' => 'CLEAR_TEST_DATA', 'field' => null, 'old_val' => json_encode($done), 'new_val' => basename($bk), 'rev' => 0, 'created_at' => now()]);
    return ['ok' => true, 'deleted' => $done, 'files' => $files, 'backup' => basename($bk),
        'message' => 'Test data cleared: ' . array_sum($done) . ' records and ' . $files . ' files. Backup saved on the server as ' . basename($bk) . '.'];
});
