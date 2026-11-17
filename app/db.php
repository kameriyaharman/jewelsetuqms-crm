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
            address TEXT, report_format VARCHAR(100), aql_default VARCHAR(20) DEFAULT '2.5', major_limit DECIMAL(6,2) DEFAULT 2.5, status VARCHAR(20) DEFAULT 'ACTIVE', created_at $DT",
        'vendors' => "id $pk, code VARCHAR(30) NOT NULL UNIQUE, name $T NOT NULL, contact $T, email $T, phone VARCHAR(40), ho_email $T,
            address TEXT, location $T, status VARCHAR(20) DEFAULT 'ACTIVE', created_at $DT",
        'client_vendor' => "client_id INT NOT NULL, vendor_id INT NOT NULL, PRIMARY KEY (client_id, vendor_id)",
        'users' => "id $pk, name $T NOT NULL, email $T, emp_code VARCHAR(40), password_hash $T NOT NULL, role VARCHAR(30) NOT NULL,
            client_id INT NULL, vendor_id INT NULL, phone VARCHAR(40), location $T, area $T, joined_on $D NULL,
            status VARCHAR(20) DEFAULT 'ACTIVE', last_login $DT NULL, created_at $DT",
        'product_categories' => "id $pk, name $T NOT NULL",
        'qc_types' => "id $pk, name $T NOT NULL",
        'defect_categories' => "id $pk, name $T NOT NULL, sort INT DEFAULT 0",
        'defect_master' => "id $pk, category_id INT NOT NULL, name $T NOT NULL, severity VARCHAR(20) DEFAULT 'MAJOR'",
        'checkpoints' => "id $pk, client_id INT NULL, category $T NULL, name $T NOT NULL, sort INT DEFAULT 0",
        'onsite_tests' => "id $pk, name $T NOT NULL, sort INT DEFAULT 0",
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
            length $T, lock_type $T, stamping $T, width $T, thickness $T, order_qty INT DEFAULT 0, ready_qty INT DEFAULT 0,
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
function db_ready(): bool {
    try { db()->query('SELECT 1 FROM settings LIMIT 1'); return true; } catch (Throwable $e) { return false; }
}
