<?php
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    global $CFG;
    if ($CFG['db_driver'] === 'sqlite') {
        $pdo = new PDO('sqlite:' . $CFG['sqlite_path']);
        $pdo->exec('PRAGMA foreign_keys=ON; PRAGMA journal_mode=WAL;');
    } else {
        $dsn = "mysql:host={$CFG['db_host']};port={$CFG['db_port']};dbname={$CFG['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $CFG['db_user'], $CFG['db_pass'], [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, time_zone='+05:30'"]);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}
function is_sqlite(): bool { global $CFG; return $CFG['db_driver'] === 'sqlite'; }
function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute(array_values($p)); return $s; }
function all(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function one(string $sql, array $p = []): ?array { $r = q($sql, $p)->fetch(); return $r ?: null; }
function val(string $sql, array $p = []) { $r = q($sql, $p)->fetchColumn(); return $r === false ? null : $r; }
function insert(string $t, array $d): int {
    $cols = array_keys($d);
    q("INSERT INTO $t (" . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($d));
    return (int)db()->lastInsertId();
}
function update(string $t, array $d, string $where, array $wp = []): void {
    $set = implode(',', array_map(fn($c) => "$c=?", array_keys($d)));
    q("UPDATE $t SET $set WHERE $where", array_merge(array_values($d), $wp));
}

/** Creates tables on first run (works on shared hosting without shell access). */
function db_install(): void {
    $pk = is_sqlite() ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
    $engine = is_sqlite() ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $T = 'VARCHAR(255)'; $TXT = is_sqlite() ? 'TEXT' : 'MEDIUMTEXT'; $DT = is_sqlite() ? 'TEXT' : 'DATETIME'; $D = is_sqlite() ? 'TEXT' : 'DATE';
    $tables = [
        'settings' => "k VARCHAR(100) PRIMARY KEY, v TEXT",
        'clients' => "id $pk, code VARCHAR(20) NOT NULL UNIQUE, name $T NOT NULL, contact $T, email $T, phone VARCHAR(40), ho_email $T,
            address TEXT, report_format VARCHAR(100), aql_default VARCHAR(20) DEFAULT '2.5', major_limit DECIMAL(6,2) DEFAULT 2.5, fail_threshold DECIMAL(6,2) DEFAULT 80, status VARCHAR(20) DEFAULT 'ACTIVE', created_at $DT, qc_manager_id INT NULL",
        'vendors' => "id $pk, code VARCHAR(30) NOT NULL UNIQUE, short_code VARCHAR(20), name $T NOT NULL, contact $T, email $T, phone VARCHAR(40), ho_email $T,
            address TEXT, location $T, status VARCHAR(20) DEFAULT 'ACTIVE', created_at $DT, geo_lat VARCHAR(20) NULL, geo_lng VARCHAR(20) NULL, geo_radius INT NULL, geo_pending_lat VARCHAR(20) NULL, geo_pending_lng VARCHAR(20) NULL, geo_pending_by INT NULL, geo_pending_at DATETIME NULL",
        'client_vendor' => "client_id INT NOT NULL, vendor_id INT NOT NULL, PRIMARY KEY (client_id, vendor_id)",
        'users' => "id $pk, name $T NOT NULL, email $T, emp_code VARCHAR(40), password_hash $T NOT NULL, pw_enc TEXT NULL, role VARCHAR(30) NOT NULL,
            client_id INT NULL, vendor_id INT NULL, phone VARCHAR(40), designation $T NULL, location $T, area $T, joined_on $D NULL,
            status VARCHAR(20) DEFAULT 'ACTIVE', last_login $DT NULL, created_at $DT",
        'product_categories' => "id $pk, name $T NOT NULL",
        'qc_types' => "id $pk, name $T NOT NULL",
        'defect_categories' => "id $pk, name $T NOT NULL, sort INT DEFAULT 0",
        'defect_master' => "id $pk, category_id INT NOT NULL, name $T NOT NULL, severity VARCHAR(20) DEFAULT 'MAJOR'",
        'checkpoints' => "id $pk, client_id INT NULL, category $T NULL, name $T NOT NULL, sort INT DEFAULT 0, pass_remark $T NULL, fail_remark $T NULL, na_remark $T NULL",
        'onsite_tests' => "id $pk, name $T NOT NULL, sort INT DEFAULT 0, method $T NULL",
        'qc_jobs' => "id $pk, req_no VARCHAR(30) NOT NULL, job_no VARCHAR(30) NULL, source VARCHAR(30) DEFAULT 'Back Office',
            client_id INT NOT NULL, vendor_id INT NOT NULL, po_no VARCHAR(60) NOT NULL, qc_type $T, location $T,
            requested_date $D NULL, scheduled_date $D NULL, time_slot VARCHAR(20), priority VARCHAR(20) DEFAULT 'Normal',
            scope VARCHAR(10) DEFAULT 'BULK', bulk_mode VARCHAR(10) DEFAULT '100', insp_level VARCHAR(5) DEFAULT 'II',
            aql_critical DECIMAL(6,3) DEFAULT 0, aql_major DECIMAL(6,3) DEFAULT 2.5, aql_minor DECIMAL(6,3) DEFAULT 4.0,
            defined_qty INT DEFAULT 0, allow_excess INT DEFAULT 0, specs TEXT, special_req TEXT,
            status VARCHAR(20) DEFAULT 'REQUESTED', inspector_id INT NULL, inspector2_id INT NULL, manager_id INT NULL,
            created_by INT NULL, created_at $DT, scheduled_at $DT NULL, assigned_at $DT NULL, started_at $DT NULL,
            submitted_at $DT NULL, approved_at $DT NULL, emailed_at $DT NULL, closed_at $DT NULL, po_pdf $T NULL",
        'qc_items' => "id $pk, job_id INT NOT NULL, sku VARCHAR(80) NOT NULL, product $T, metal $T, plating $T, target_weight $T,
            length $T, lock_type $T, stamping $T, width $T, thickness $T, style_ref $T NULL, stone_setting $T NULL, spec_detail TEXT NULL, instructions TEXT NULL, parent_id INT NULL, lot_seq INT NULL, line_closed INT NULL, close_note TEXT NULL, i_scope VARCHAR(10) NULL, i_bulk_mode VARCHAR(10) NULL, i_aql_major VARCHAR(10) NULL, i_defined_qty INT NULL, spec_check VARCHAR(10) NULL, spec_remark TEXT NULL, factory_rep $T NULL, insp_type VARCHAR(30) NULL, master_sample $T NULL, lot_no $T NULL, xrf_machine $T NULL, factory_coop VARCHAR(20) NULL, factory_line VARCHAR(20) NULL, factory_ready VARCHAR(20) NULL, order_qty INT DEFAULT 0, ready_qty INT DEFAULT 0,
            insp_qty INT DEFAULT 0, plan_json TEXT, status VARCHAR(20) DEFAULT 'PENDING', step INT DEFAULT 0,
            inspected_qty INT DEFAULT 0, accepted_qty INT DEFAULT 0, rejected_qty INT DEFAULT 0, reinspection INT DEFAULT 0,
            gps $T NULL, insp_date $D NULL, checks_json $TXT, meas_json $TXT, tests_json $TXT,
            remark TEXT, action_required TEXT, system_result VARCHAR(20), inspector_result VARCHAR(20), final_result VARCHAR(20),
            manager_note TEXT, deviation_json TEXT, concession_status VARCHAR(20) NULL, concession_remark TEXT,
            concession_by $T NULL, concession_at $DT NULL, concession_due $D NULL,
            hold_for VARCHAR(10) NULL, hold_reason TEXT, hold_status VARCHAR(20) NULL, hold_response TEXT, hold_by $T NULL, hold_at $DT NULL, hold_resp_at $DT NULL,
            return_sections $T NULL, return_reason TEXT, return_count INT DEFAULT 0,
            report_no VARCHAR(60) NULL, rev INT DEFAULT 0, locked INT DEFAULT 0, pdf_path $T NULL,
            submitted_at $DT NULL, approved_at $DT NULL, approved_by INT NULL, started_at $DT NULL, updated_at $DT NULL",
        'checkins' => "id $pk, job_id INT, user_id INT NOT NULL, vendor_id INT, lat VARCHAR(20), lng VARCHAR(20), acc INT, dist_m INT, verified INT DEFAULT 0, note $T, at DATETIME, out_at DATETIME NULL",
        'leaves' => "id $pk, user_id INT NOT NULL, from_date DATE, to_date DATE, note $T, created_by INT, created_at DATETIME",
        'item_defects' => "id $pk, item_id INT NOT NULL, category $T, type $T, severity VARCHAR(20), qty INT DEFAULT 0, remark TEXT",
        'item_photos' => "id $pk, item_id INT NOT NULL, kind VARCHAR(30), path $T NOT NULL, remark $T, created_at $DT",
        'attachments' => "id $pk, job_id INT NOT NULL, item_id INT NULL, path $T NOT NULL, name $T, created_at $DT",
        'audit_log' => "id $pk, entity VARCHAR(20), entity_id INT, user_id INT NULL, action VARCHAR(40), field $T NULL,
            old_val TEXT, new_val TEXT, rev INT DEFAULT 0, created_at $DT",
        'email_log' => "id $pk, job_id INT NULL, item_id INT NULL, recipient_type VARCHAR(40), email $T, subject $T,
            status VARCHAR(40), error TEXT, sent_at $DT",
        'notifications' => "id $pk, user_id INT NOT NULL, text TEXT, link $T, is_read INT DEFAULT 0, created_at $DT",
    ];
    foreach ($tables as $name => $cols) db()->exec("CREATE TABLE IF NOT EXISTS $name ($cols)$engine");
    $idx = ['qc_jobs' => ['client_id', 'vendor_id', 'inspector_id', 'status'], 'qc_items' => ['job_id', 'status', 'report_no'],
            'item_defects' => ['item_id'], 'item_photos' => ['item_id'], 'notifications' => ['user_id'], 'audit_log' => ['entity_id']];
    foreach ($idx as $t => $cs) foreach ($cs as $c) {
        try { db()->exec(is_sqlite() ? "CREATE INDEX IF NOT EXISTS ix_{$t}_$c ON $t($c)" : "CREATE INDEX ix_{$t}_$c ON $t($c)"); } catch (Throwable $e) {}
    }
}
/** Additive migrations for databases created by an earlier version. */
function db_migrate(): void {
    $ver = (int)setting('schema_version', '1');
    if ($ver >= 10) return;
    if ($ver === 9) { db_migrate_v10(); return; }
    if ($ver === 8) { db_migrate_v9(); return; }
    if ($ver === 7) { db_migrate_v8(); return; }
    if ($ver === 6) { db_migrate_v7(); return; }
    if ($ver === 5) { db_migrate_v6(); return; }
    if ($ver === 4) { db_migrate_v5(); return; }
    if ($ver === 3) { db_migrate_v4(); return; }
    if ($ver === 2) { db_migrate_v3(); return; }
    $T = 'VARCHAR(255)';
    $cols = ['clients' => ['fail_threshold' => 'DECIMAL(6,2) DEFAULT 80'], 'vendors' => ['short_code' => 'VARCHAR(20)'], 'users' => ['designation' => "$T NULL"],
        'checkpoints' => ['pass_remark' => "$T NULL", 'fail_remark' => "$T NULL", 'na_remark' => "$T NULL"], 'onsite_tests' => ['method' => "$T NULL"],
        'qc_items' => ['style_ref' => "$T NULL", 'stone_setting' => "$T NULL", 'master_sample' => "$T NULL", 'lot_no' => "$T NULL", 'xrf_machine' => "$T NULL",
                       'factory_coop' => 'VARCHAR(20) NULL', 'factory_line' => 'VARCHAR(20) NULL', 'factory_ready' => 'VARCHAR(20) NULL']];
    foreach ($cols as $t => $cs) foreach ($cs as $c => $def) {
        try { db()->query("SELECT $c FROM $t LIMIT 1"); } catch (Throwable $e) { db()->exec("ALTER TABLE $t ADD COLUMN $c $def"); }
    }
    require_once __DIR__ . '/seed.php';
    seed_masters_v2(true);
    set_setting('schema_version', '2');
    db_migrate_v3();
}
/** v3: full spec breakdown (stones etc.) and client instructions per SKU, imported from tech sheets. */
function db_migrate_v3(): void {
    foreach (['spec_detail' => 'TEXT NULL', 'instructions' => 'TEXT NULL'] as $c => $def) {
        try { db()->query("SELECT $c FROM qc_items LIMIT 1"); } catch (Throwable $e) { db()->exec("ALTER TABLE qc_items ADD COLUMN $c $def"); }
    }
    set_setting('schema_version', '3');
    db_migrate_v4();
}
/** v4: optional QC-manager mapping per client, sample-report fields, new feature flags. */
function db_migrate_v4(): void {
    $add = ['clients' => ['qc_manager_id' => 'INT NULL'],
        'qc_items' => ['spec_check' => 'VARCHAR(10) NULL', 'spec_remark' => 'TEXT NULL', 'factory_rep' => 'VARCHAR(255) NULL', 'insp_type' => 'VARCHAR(30) NULL']];
    foreach ($add as $t => $cs) foreach ($cs as $c => $def) {
        try { db()->query("SELECT $c FROM $t LIMIT 1"); } catch (Throwable $e) { db()->exec("ALTER TABLE $t ADD COLUMN $c $def"); }
    }
    foreach (['qc_manager_role' => '0', 'inspector_new_job' => '1', 'inspector_edit_after_submit' => '1'] as $k => $v) if (setting($k, '') === '') set_setting($k, $v);
    set_setting('schema_version', '4');
    db_migrate_v5();
}
/** v5: QC lots / re-inspection sessions per SKU, per-design scope, reference image kind, new defaults. */
function db_migrate_v5(): void {
    $add = ['parent_id' => 'INT NULL', 'lot_seq' => 'INT NULL', 'line_closed' => 'INT NULL', 'close_note' => 'TEXT NULL',
        'i_scope' => 'VARCHAR(10) NULL', 'i_bulk_mode' => 'VARCHAR(10) NULL', 'i_aql_major' => 'VARCHAR(10) NULL', 'i_defined_qty' => 'INT NULL'];
    foreach ($add as $c => $def) { try { db()->query("SELECT $c FROM qc_items LIMIT 1"); } catch (Throwable $e) { db()->exec("ALTER TABLE qc_items ADD COLUMN $c $def"); } }
    try { db()->exec('CREATE INDEX ' . (is_sqlite() ? 'IF NOT EXISTS ' : '') . 'ix_qc_items_parent_id ON qc_items(parent_id)'); } catch (Throwable $e) {}
    // master / reference image (from Excel, request form or on-site creation) is its own kind, so inspectors cannot replace or delete it
    q("UPDATE item_photos SET kind='ref' WHERE kind='product' AND remark IN ('From Excel', 'On-site', 'Reference')");
    set_setting('lock_inspected_quantity', '0');
    set_setting('import_one_job_per_design', '1');
    set_setting('require_defect_photo', '0');
    set_setting('schema_version', '5');
    db_migrate_v6();
}
/** v6: encrypted copy of each password so the Super Admin can view it; fill it for users still on a known default password. */
function db_migrate_v6(): void {
    try { db()->query('SELECT pw_enc FROM users LIMIT 1'); } catch (Throwable $e) { db()->exec('ALTER TABLE users ADD COLUMN pw_enc TEXT NULL'); }
    foreach (all('SELECT id, password_hash FROM users WHERE pw_enc IS NULL') as $u)
        foreach (['Admin@123', 'Demo@123'] as $p) if (password_verify($p, $u['password_hash'])) { q('UPDATE users SET pw_enc=? WHERE id=?', [pw_enc($p), $u['id']]); break; }
    set_setting('schema_version', '6');
    db_migrate_v7();
}
/** v7: geo-fenced factory check-in (vendor GPS pin, check-ins) and inspector leave. */
function db_migrate_v7(): void {
    $pk = is_sqlite() ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY'; $T = is_sqlite() ? 'TEXT' : 'VARCHAR(255)';
    $tables = ['checkins' => "id $pk, job_id INT, user_id INT NOT NULL, vendor_id INT, lat VARCHAR(20), lng VARCHAR(20), acc INT, dist_m INT, verified INT DEFAULT 0, note $T, at DATETIME, out_at DATETIME NULL",
        'leaves' => "id $pk, user_id INT NOT NULL, from_date DATE, to_date DATE, note $T, created_by INT, created_at DATETIME"];
    foreach ($tables as $t => $cols) db()->exec("CREATE TABLE IF NOT EXISTS $t ($cols)");
    foreach (['geo_lat' => 'VARCHAR(20) NULL', 'geo_lng' => 'VARCHAR(20) NULL', 'geo_radius' => 'INT NULL', 'geo_pending_lat' => 'VARCHAR(20) NULL', 'geo_pending_lng' => 'VARCHAR(20) NULL', 'geo_pending_by' => 'INT NULL', 'geo_pending_at' => 'DATETIME NULL'] as $c => $def) {
        try { db()->query("SELECT $c FROM vendors LIMIT 1"); } catch (Throwable $e) { db()->exec("ALTER TABLE vendors ADD COLUMN $c $def"); }
    }
    if (setting('geo_radius', '') === '') set_setting('geo_radius', '250');
    if (setting('geo_checkin_required', '') === '') set_setting('geo_checkin_required', '0');
    set_setting('schema_version', '7');
    db_migrate_v8();
}
/** v8: client / vendor QC manuals & instructions (versioned), inspector acknowledgements, daily EOD summary; check-in first. */
function db_migrate_v8(): void {
    $pk = is_sqlite() ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY'; $T = is_sqlite() ? 'TEXT' : 'VARCHAR(255)';
    db()->exec("CREATE TABLE IF NOT EXISTS manuals (id $pk, group_id INT, client_id INT NOT NULL, vendor_id INT NULL, po_no $T NULL, scope VARCHAR(10) DEFAULT 'ALL', title $T, note TEXT NULL,
        path $T NULL, file_name $T NULL, version VARCHAR(10) DEFAULT '1.0', priority VARCHAR(10) DEFAULT 'NORMAL', uploader_role VARCHAR(20), uploaded_by INT, status VARCHAR(10) DEFAULT 'ACTIVE', created_at DATETIME)");
    db()->exec("CREATE TABLE IF NOT EXISTS manual_acks (id $pk, manual_id INT, user_id INT, job_id INT, at DATETIME)");
    foreach (['geo_checkin_required' => '1', 'daily_summary_on' => '1', 'daily_summary_time' => '19:30', 'daily_summary_cc' => 'jewelsetu.jqc@gmail.com'] as $k => $v) set_setting($k, $v);
    if (setting('cron_key', '') === '') set_setting('cron_key', bin2hex(random_bytes(12)));
    set_setting('schema_version', '8');
    db_migrate_v9();
}
/** v9: profile photo for every login. */
function db_migrate_v9(): void {
    try { db()->query('SELECT photo FROM users LIMIT 1'); } catch (Throwable $e) { db()->exec('ALTER TABLE users ADD COLUMN photo ' . (is_sqlite() ? 'TEXT' : 'VARCHAR(255)') . ' NULL'); }
    set_setting('schema_version', '9');
    db_migrate_v10();
}
/** v10: check-out GPS + missed check-out flag / manager fix, for the attendance & visit log. */
function db_migrate_v10(): void {
    $T = is_sqlite() ? 'TEXT' : 'VARCHAR(40)';
    foreach (['out_lat' => $T, 'out_lng' => $T, 'out_dist' => 'INT', 'out_verified' => 'INT', 'auto_flag' => 'VARCHAR(20)', 'out_note' => is_sqlite() ? 'TEXT' : 'VARCHAR(255)', 'out_by' => 'INT', 'reminded' => 'INT'] as $c => $t) {
        try { db()->query("SELECT $c FROM checkins LIMIT 1"); } catch (Throwable $e) { db()->exec("ALTER TABLE checkins ADD COLUMN $c $t NULL"); }
    }
    if (setting('att_late_after', '') === '') set_setting('att_late_after', '10:30');
    if (setting('att_remind_at', '') === '') set_setting('att_remind_at', '20:00');
    set_setting('schema_version', '10');
}
function db_ready(): bool {
    try { db()->query('SELECT 1 FROM settings LIMIT 1'); return true; } catch (Throwable $e) { return false; }
}
