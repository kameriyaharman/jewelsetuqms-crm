<?php
/** Masters + (optionally) demo data. Runs once, when the database is empty. */
function seed_all(bool $demo): void {
    $now = now();
    $settings = [
        'enable_vendor_qc_request' => '0', 'enable_client_qc_request' => '0', 'require_defect_photo' => '1',
        'gps_on_inspection_start' => '1', 'allow_job_split' => '1', 'block_qty_mismatch' => '1', 'auto_email_on_approve' => '1',
        'company_name' => 'JEWELSETU Quality Services', 'admin_email' => 'jewelsetu.jqc@gmail.com',
        'report_header' => 'PRE-SHIPMENT INSPECTION REPORT', 'report_footer' => 'This report reflects the condition of the goods inspected on the date and location mentioned. JEWELSETU Quality Services.',
        'mail_transport' => 'none', 'smtp_host' => '', 'smtp_port' => '587', 'smtp_user' => '', 'smtp_pass' => '', 'smtp_secure' => 'tls',
        'mail_from' => 'reports@jewelsetu.com', 'mail_from_name' => 'JEWELSETU Q-TRACK',
    ];
    foreach ($settings as $k => $v) insert('settings', ['k' => $k, 'v' => $v]);

    foreach (['Necklace', 'Ring', 'Bracelet', 'Earrings', 'Pendant', 'Bangle', 'Chain', 'Anklet', 'Mangalsutra'] as $n) insert('product_categories', ['name' => $n]);
    foreach (['Pre-production', 'Inline', 'Pre-shipment', 'Re-inspection', 'Final random'] as $n) insert('qc_types', ['name' => $n]);
    seed_masters_v2(false);
    set_setting('schema_version', '2');

    $pw = password_hash('Admin@123', PASSWORD_DEFAULT);
    insert('users', ['name' => 'Super Admin', 'email' => 'admin@jewelsetu.com', 'emp_code' => 'JS-001', 'password_hash' => $pw,
        'role' => 'super_admin', 'location' => 'HQ', 'designation' => 'Principal Consultant', 'status' => 'ACTIVE', 'created_at' => $now]);

    if ($demo) seed_demo();
}

/** Report-sample masters: 12-point checkpoints, 8 on-site tests, defect categories, report terms. */
function seed_masters_v2(bool $replace): void {
    if ($replace) { foreach (['checkpoints', 'onsite_tests', 'defect_master', 'defect_categories'] as $t) db()->exec("DELETE FROM $t"); }
    $ok = 'Verified OK as per Approved Sample & Spec'; $tol = 'Weight / Dimension within tolerance (±3% / ±0.2mm)'; $na = 'Not Applicable for this product style (N/A)';
    $cps = [
        ['Design & Style Accuracy as per Approved Sample / CAD', $ok, 'Design / style deviation from approved sample / CAD'],
        ['Offered Quantity vs. PO Quantity Verification', $ok, 'Offered quantity does not match PO quantity'],
        ['Dimension, Shape & Profile Accuracy', $tol, 'Dimension / shape out of tolerance'],
        ['Stone Security, Prong / Bezel Setting Check', $ok, 'Loose stone / Prong or Bezel setting gap observed'],
        ['Plating Thickness Check (XRF Micron Report)', 'XRF micron within specification', 'XRF Micron below spec / Plating off observed'],
        ['Surface Quality Check (Scratches / Pits / Polish)', $ok, 'Surface scratches / Casting pits / Dull polish'],
        ['Structural Integrity & Breakage Check', $ok, 'Breakage / weak joint observed'],
        ['Plating Color Fastness & Batch Shade Matching', $ok, 'Shade variation vs approved master sample'],
        ['Piece Weight Verification', $tol, 'Piece weight out of tolerance'],
        ['Chain / Earring Drop Length Verification', $tol, 'Drop length out of tolerance'],
        ['Polybag, Barcode & Packaging Compliance', 'Polybag, Barcode & Tagging verified OK', 'Packaging / barcode / tagging non-compliance'],
        ['925 Stamping / Hallmarking & Logo Clarity', '925 Stamp / Hallmark clear & verified', 'Stamping / hallmark missing or unclear'],
    ];
    foreach ($cps as $i => [$n, $p, $f]) insert('checkpoints', ['client_id' => null, 'category' => null, 'name' => $n, 'sort' => $i, 'pass_remark' => $p, 'fail_remark' => $f, 'na_remark' => $na]);
    foreach ([['Function & Lock Mechanism Test', 'Push-back / clasp tension checked'], ['Assembly & Soldering Strength Test', 'Post & setting joint integrity'],
        ['Rub / Abrasion Test (Plating Adhesion)', 'Eraser / cloth rub test'], ['Tape Adhesion Test (3M / Scotch)', 'No plating peel-off observed'],
        ['Drop Test (Standard Height)', 'No stone loosening or breakage'], ['Hardness Test (Pencil Method)', 'Lacquer / anti-tarnish hardness'],
        ['Magnetic Test (Base Metal Check)', 'Non-magnetic 925 silver base'], ['Tunch / Purity & XRF Micron Check', 'XRF micron / purity reading']] as $i => [$n, $m])
        insert('onsite_tests', ['name' => $n, 'sort' => $i, 'method' => $m]);
    $defects = [
        'Plating / Micron Issue' => [['Micron off / uneven plating thickness', 'CRITICAL'], ['Plating peel-off', 'MAJOR'], ['Plating shade mismatch', 'MAJOR'], ['Uneven plating', 'MINOR']],
        'Metal / Casting Issue' => [['Surface pits / casting porosity', 'MAJOR'], ['Crack in metal', 'CRITICAL'], ['Wrong metal purity', 'CRITICAL'], ['Dent / deformation', 'MAJOR'], ['Bezel finish rough', 'MINOR']],
        'Stone / Setting Issue' => [['Stone missing', 'CRITICAL'], ['Loose stone / uneven bezel setting gap', 'CRITICAL'], ['Stone broken / chipped', 'MAJOR'], ['Wrong stone', 'CRITICAL'], ['Diamond cut mix', 'MAJOR'], ['Stone colour mismatch', 'MINOR']],
        'Polish / Surface Finish Issue' => [['Micro-scratches on surface', 'MAJOR'], ['Dull polish', 'MINOR'], ['Polish marks', 'MINOR']],
        'Soldering / Linking Issue' => [['Weak solder joint', 'MAJOR'], ['Solder mark visible', 'MINOR'], ['Jump ring open', 'MAJOR']],
        'Finding / Lock / Assembly Issue' => [['Clasp lock tension tight / loose', 'MAJOR'], ['Lock not working', 'CRITICAL'], ['Chain kink', 'MINOR']],
        'Stamping / Hallmarking Issue' => [['Stamping missing', 'MAJOR'], ['Wrong stamping', 'MAJOR'], ['Stamping unclear', 'MINOR']],
        'Dimension / Weight Issue' => [['Length short', 'MAJOR'], ['Weight out of tolerance', 'MAJOR'], ['Size mismatch', 'MAJOR']],
        'Packaging / Tagging Issue' => [['Polybag / barcode wrong', 'MINOR'], ['Packaging damaged', 'MINOR']],
    ];
    $k = 0;
    foreach ($defects as $cat => $list) { $cid = insert('defect_categories', ['name' => $cat, 'sort' => $k++]);
        foreach ($list as [$n, $sev]) insert('defect_master', ['category_id' => $cid, 'name' => $n, 'severity' => $sev]); }
    $S = ['report_header' => 'PRE-SHIPMENT INSPECTION (PSI) REPORT', 'company_name' => 'JEWELSETU QUALITY SERVICES', 'company_web' => 'www.jewelsetuqms.in',
        'company_tagline' => 'Independent Jewellery Quality Assurance, Auditing & Process Consultancy',
        'company_contact' => 'Web: www.jewelsetuqms.in | Email: info@jewelsetuqms.in / jewelsetu.jqc@gmail.com | Tel: +91 80059 33787 (Jaipur, India)',
        'report_terms' => "1. 100% Piece-by-Piece Inspection: Conducted strictly against Client Approved Master Sample, CAD & PO Spec.\n2. 80%–100% Failure Threshold: A design lot is marked FAIL if ≥80% pieces require repair/rejection; below 80%, defective pieces are segregated for rework and balance lot passes.\n3. Liability & Impartiality: Report reflects physical condition at the time/place of inspection only. Re-inspection after rectification is mandatory for failed lots.\n4. Strict Confidentiality & IPR: Unauthorized editing, forgery, or circulation of this report is unlawful and strictly prohibited.",
        'report_portfolio' => "• Third-Party Pre-Shipment (PSI) & Inline Inspections • XRF Micron & Purity Verification Audits\n• Vendor Factory QMS Audits & ISO 9001 / 17020 Setup • Lean Six Sigma Process & Casting/Setting Yield Improvement\nPARTNER WITH JEWELSETU FOR ZERO-DEFECT EXPORTS & DOMESTIC RETAIL:\nFounder & Principal Consultant: Sanket Shrivastava (20+ Yrs Global QA Leadership | GIA Diamond Graduate | Lean Six Sigma Black Belt)",
        'report_footer' => 'Report reflects the condition of goods at the time and place of inspection.'];
    foreach ($S as $kk => $v) set_setting($kk, $v);
    if ($replace) {
        q('UPDATE clients SET fail_threshold=80 WHERE fail_threshold IS NULL');
        foreach (all('SELECT id, code FROM vendors WHERE short_code IS NULL') as $v) q('UPDATE vendors SET short_code=? WHERE id=?', [vendor_short_default($v['code']), $v['id']]);
        q("UPDATE users SET designation='Principal Consultant' WHERE role='super_admin' AND designation IS NULL");
        q("UPDATE users SET designation='QC Manager' WHERE role='qc_manager' AND designation IS NULL");
    }
}
function vendor_short_default(string $code): string { $c = preg_replace('/^VND-?/i', '', strtoupper($code)); return substr(preg_replace('/[^A-Z0-9]/', '', $c), 0, 4) ?: 'V'; }

function seed_demo(): void {
    $now = now(); $pw = password_hash('Demo@123', PASSWORD_DEFAULT);
    $C = [];
    foreach ([
        ['KP', 'KP Jewels Pvt Ltd', 'Rajiv Sharma', 'rajiv@kpjewels.com', 'qc@kpjewels.com', 'KP Standard v2', '2.5', 'ACTIVE'],
        ['XYZ', 'XYZ Jewels', 'Neha Jain', 'neha@xyzjewels.com', 'qc@xyzjewels.com', 'XYZ Standard', '2.5', 'ACTIVE'],
        ['ABC', 'ABC Exports', 'Manish Gupta', 'manish@abcexports.com', 'qc@abcexports.com', 'ABC Export v1', '1.5', 'ACTIVE'],
        ['SR', 'Shree Retail', 'Pooja Mehta', 'pooja@shreeretail.in', 'qc@shreeretail.in', 'Standard', '2.5', 'INACTIVE'],
    ] as [$code, $name, $ct, $em, $ho, $fmt, $aql, $st])
        $C[$code] = insert('clients', ['code' => $code, 'name' => $name, 'contact' => $ct, 'email' => $em, 'phone' => '+91 98290 1' . rand(1000, 9999),
            'ho_email' => $ho, 'report_format' => $fmt, 'aql_default' => $aql, 'major_limit' => 2.5, 'fail_threshold' => 80, 'status' => $st, 'created_at' => $now]);
    $V = [];
    foreach ([
        ['VND-ASHOK', 'Ashok Jewels', 'Ashok Soni', 'vendor@ashokjewels.com', 'ho@ashokjewels.in', 'Plot 21, Sitapura Industrial Area, Jaipur', 'Sitapura, Jaipur'],
        ['VND-NIRVANA', 'Nirvana', 'Vikas Jain', 'qc@nirvanajewels.com', 'ho@nirvanajewels.com', '14, Mansarovar Industrial Area, Jaipur', 'Mansarovar, Jaipur'],
        ['VND-ABC', 'ABC Factory', 'Suresh Patel', 'qc@abcfactory.in', 'ho@abcfactory.in', 'Unit 7, SEEPZ, Andheri East, Mumbai', 'Andheri, Mumbai'],
        ['VND-SHREE', 'Shree Gems', 'Mahesh Shah', 'qc@shreegems.com', 'ho@shreegems.com', '2nd floor, Zaveri Bazaar, Mumbai', 'Zaveri Bazaar, Mumbai'],
        ['VND-LAXMI', 'Laxmi Arts', 'Ketan Patel', 'qc@laxmiarts.com', 'ho@laxmiarts.com', 'GIDC Metoda, Rajkot', 'Rajkot'],
    ] as [$code, $name, $ct, $em, $ho, $addr, $loc])
        $V[$name] = insert('vendors', ['code' => $code, 'short_code' => ['VND-ASHOK' => 'AJ', 'VND-NIRVANA' => 'NV', 'VND-ABC' => 'ABC', 'VND-SHREE' => 'SG', 'VND-LAXMI' => 'LA'][$code] ?? vendor_short_default($code), 'name' => $name, 'contact' => $ct, 'email' => $em, 'phone' => '+91 98' . rand(10000000, 99999999),
            'ho_email' => $ho, 'address' => $addr, 'location' => $loc, 'status' => 'ACTIVE', 'created_at' => $now]);
    foreach ([['KP', 'Ashok Jewels'], ['KP', 'Nirvana'], ['KP', 'Shree Gems'], ['XYZ', 'Ashok Jewels'], ['XYZ', 'ABC Factory'], ['XYZ', 'Laxmi Arts'],
              ['ABC', 'Shree Gems'], ['ABC', 'Laxmi Arts'], ['SR', 'Nirvana']] as [$c, $v])
        insert('client_vendor', ['client_id' => $C[$c], 'vendor_id' => $V[$v]]);

    $U = [];
    $users = [
        ['bo', 'Sonal Verma', 'sonal@jewelsetu.com', 'JS-002', 'back_office', null, null, 'HQ'],
        ['ravi', 'Ravi Kumar', 'ravi@jewelsetu.com', 'JS-012', 'qc_manager', null, null, 'Jaipur'],
        ['rahul', 'Rahul Sharma', 'rahul@jewelsetu.com', 'JS-010', 'inspector', null, null, 'Jaipur'],
        ['amit', 'Amit Desai', 'amit@jewelsetu.com', 'JS-011', 'inspector', null, null, 'Mumbai'],
        ['kunal', 'Kunal Mehra', 'kunal@jewelsetu.com', 'JS-013', 'inspector', null, null, 'Jaipur'],
        ['kp', 'Rajiv Sharma', 'rajiv@kpjewels.com', null, 'client', $C['KP'], null, 'Jaipur'],
        ['xyz', 'Neha Jain', 'neha@xyzjewels.com', null, 'client', $C['XYZ'], null, 'Mumbai'],
        ['ashok', 'Ashok Jewels', 'vendor@ashokjewels.com', 'VND-ASHOK', 'vendor', null, $V['Ashok Jewels'], 'Jaipur'],
        ['nirvana', 'Nirvana', 'qc@nirvanajewels.com', 'VND-NIRVANA', 'vendor', null, $V['Nirvana'], 'Jaipur'],
        ['abcv', 'ABC Factory', 'qc@abcfactory.in', 'VND-ABC', 'vendor', null, $V['ABC Factory'], 'Mumbai'],
        ['shree', 'Shree Gems', 'qc@shreegems.com', 'VND-SHREE', 'vendor', null, $V['Shree Gems'], 'Mumbai'],
    ];
    foreach ($users as [$k, $n, $e, $code, $r, $cid, $vid, $loc])
        $U[$k] = insert('users', ['name' => $n, 'email' => $e, 'emp_code' => $code, 'password_hash' => $pw, 'role' => $r, 'client_id' => $cid,
            'vendor_id' => $vid, 'phone' => '+91 98' . rand(10000000, 99999999), 'location' => $loc,
            'area' => $r === 'inspector' ? ($loc === 'Jaipur' ? 'Sitapura, Mansarovar' : 'Andheri, Zaveri Bazaar') : null,
            'joined_on' => '2024-03-12', 'designation' => $r === 'qc_manager' ? 'QC Manager' : null, 'status' => 'ACTIVE', 'created_at' => $now]);

    $prod = [
        ['KNK01268FS0033', 'Necklace', '925 Silver', 'White rhodium', '2.75 g', '16+2 inch', 'Lobster lock', '925 + ARCL + LGDS'],
        ['KNK00982RG0011', 'Ring', '925 Silver', 'Yellow gold', '3.10 g', 'Size 7', '—', '925'],
        ['KNK00511BR0007', 'Bracelet', '925 Silver', 'Rose gold', '6.20 g', '7 inch', 'Box clasp', '925 + ARCL'],
        ['KNK01302ER0021', 'Earrings', '925 Silver', 'White rhodium', '1.80 g', '12 mm', 'Push back', '925'],
        ['KNK01422NK0019', 'Necklace', '14K Gold', '—', '2.75 g', '18 inch', 'Spring ring', '14K + LGDS'],
        ['RNG00451', 'Ring', '925 Silver', 'White rhodium', '2.40 g', 'Size 6', '—', '925'],
        ['BRC00218', 'Bracelet', '925 Silver', 'Yellow gold', '5.50 g', '7.5 inch', 'Lobster lock', '925'],
        ['ABC001', 'Pendant', '925 Silver', 'White rhodium', '1.20 g', '20 mm', '—', '925'],
        ['ABC002', 'Chain', '925 Silver', 'White rhodium', '4.00 g', '20 inch', 'Lobster lock', '925'],
        ['XYZ0441', 'Bangle', '18K Gold', '—', '8.40 g', '2.6', 'Screw', '18K'],
        ['SG0119', 'Earrings', '14K Gold', '—', '2.10 g', '15 mm', 'Screw back', '14K'],
        ['LA0072', 'Anklet', '925 Silver', 'Oxidised', '7.80 g', '10 inch', 'S-hook', '925'],
    ];
    $P = []; foreach ($prod as $p) $P[$p[0]] = $p;

    // [client, vendor, po, scope, mode, status, dayOffset, inspector, skus=>[sku, order, ready], result plan]
    $jobs = [
        ['KP', 'Ashok Jewels', '81890', 'SAMPLE', '100', 'CLOSED', -88, 'rahul', [['KNK00511BR0007', 10, 10]], 'PASS'],
        ['XYZ', 'ABC Factory', '81702', 'BULK', 'AQL', 'CLOSED', -75, 'amit', [['ABC002', 2000, 2000]], 'FAIL'],
        ['KP', 'Nirvana', '81711', 'BULK', '100', 'CLOSED', -62, 'kunal', [['ABC001', 600, 600]], 'PASS'],
        ['ABC', 'Shree Gems', '81755', 'BULK', 'AQL', 'CLOSED', -50, 'amit', [['SG0119', 1500, 1500]], 'PASS'],
        ['XYZ', 'Laxmi Arts', '81790', 'BULK', '100', 'CLOSED', -40, 'amit', [['LA0072', 400, 400]], 'FAIL'],
        ['KP', 'Ashok Jewels', '81802', 'BULK', '100', 'EMAILED', -30, 'rahul', [['KNK00982RG0011', 800, 800], ['RNG00451', 500, 500]], 'PASS'],
        ['XYZ', 'Ashok Jewels', '81810', 'BULK', '100', 'EMAILED', -24, 'rahul', [['KNK01302ER0021', 900, 880]], 'FAIL'],
        ['KP', 'Ashok Jewels', '81967', 'BULK', '100', 'EMAILED', -12, 'rahul', [['KNK00511BR0007', 500, 500]], 'PASS'],
        ['KP', 'Shree Gems', '81940', 'BULK', 'AQL', 'EMAILED', -6, 'ravi', [['KNK01422NK0019', 900, 900]], 'CONDITIONAL'],
        ['KP', 'Ashok Jewels', '81966', 'BULK', '100', 'SUBMITTED', -1, 'rahul', [['KNK01268FS0033', 1150, 1150]], 'FAIL_SUB'],
        ['KP', 'Ashok Jewels', '82050', 'BULK', '100', 'IN_PROGRESS', 0, 'rahul', [['KNK01268FS0033', 1150, 1150], ['KNK00982RG0011', 500, 500], ['KNK00511BR0007', 300, 300], ['KNK01302ER0021', 800, 800]], 'PROG'],
        ['KP', 'Nirvana', '82002', 'BULK', 'AQL', 'ASSIGNED', 1, 'kunal', [['ABC002', 700, 700]], null],
        ['XYZ', 'ABC Factory', '82005', 'BULK', 'DEFINED', 'SCHEDULED', 2, null, [['ABC001', 500, 500]], null],
        ['XYZ', 'Ashok Jewels', '82012', 'BULK', 'AQL', 'SCHEDULED', 2, null, [['KNK01302ER0021', 1200, 1200]], null],
        ['KP', 'Ashok Jewels', '82044', 'SAMPLE', '100', 'REQUESTED', 5, null, [['KNK01268FS0033', 10, 10], ['RNG00451', 5, 5], ['BRC00218', 5, 5]], null],
        ['KP', 'Nirvana', '82001', 'SAMPLE', '100', 'REQUESTED', 3, null, [['ABC001', 10, 10]], null],
        ['KP', 'Ashok Jewels', '82060', 'BULK', 'AQL', 'DRAFT', 6, null, [['BRC00218', 300, 280]], null],
    ];
    $sources = ['Back Office', 'Excel import', 'Vendor portal', 'Client portal'];
    $checks = all('SELECT * FROM checkpoints ORDER BY sort');
    $tests = all('SELECT * FROM onsite_tests ORDER BY sort');
    $n = 101; $jn = 1;
    foreach ($jobs as $idx => [$c, $v, $po, $scope, $mode, $status, $off, $insp, $skus, $res]) {
        $d = date('Y-m-d', strtotime("$off days"));
        $hasJob = !in_array($status, ['DRAFT', 'REQUESTED'], true);
        $job = ['req_no' => 'REQ-' . $n++, 'job_no' => $hasJob ? sprintf('JS-%s-%04d', date('Y'), $jn++) : null,
            'source' => $sources[$idx % 4], 'client_id' => $C[$c], 'vendor_id' => $V[$v], 'po_no' => $po, 'qc_type' => $scope === 'SAMPLE' ? 'Pre-production' : 'Pre-shipment',
            'location' => explode(',', one('SELECT location FROM vendors WHERE id=?', [$V[$v]])['location'])[0] . ' Unit 1',
            'requested_date' => $d, 'scheduled_date' => $hasJob ? $d : null, 'time_slot' => $hasJob ? '10:30 AM' : null, 'priority' => 'Normal',
            'scope' => $scope, 'bulk_mode' => $mode, 'insp_level' => 'II', 'aql_critical' => 0, 'aql_major' => 1.5, 'aql_minor' => 4.0,
            'defined_qty' => $mode === 'DEFINED' ? 200 : ($scope === 'SAMPLE' ? 5 : 0), 'allow_excess' => 0,
            'specs' => '925 Silver, White Rhodium, 16+2" Lobster lock, stamping 925 + ARCL + LGDS', 'special_req' => 'Check stone setting on centre motif',
            'status' => $status, 'inspector_id' => $insp ? $U[$insp === 'ravi' ? 'rahul' : $insp] : null, 'manager_id' => $hasJob ? $U['ravi'] : null,
            'created_by' => $U['bo'], 'created_at' => date('Y-m-d H:i:s', strtotime(($off - 4) . ' days')),
            'scheduled_at' => $hasJob ? date('Y-m-d H:i:s', strtotime(($off - 3) . ' days')) : null,
            'assigned_at' => $insp ? date('Y-m-d H:i:s', strtotime(($off - 2) . ' days')) : null];
        if (in_array($status, ['EMAILED', 'CLOSED'], true)) { $job['approved_at'] = $job['emailed_at'] = date('Y-m-d 16:10:00', strtotime("$off days")); }
        if ($status === 'CLOSED') $job['closed_at'] = date('Y-m-d 18:00:00', strtotime(($off + 1) . ' days'));
        $jid = insert('qc_jobs', $job);
        $job['id'] = $jid;
        foreach ($skus as $k => [$sku, $oq, $rq]) {
            $p = $P[$sku]; $plan = item_plan($job, $rq);
            $item = ['job_id' => $jid, 'sku' => $sku, 'product' => $p[1], 'metal' => $p[2], 'plating' => $p[3], 'target_weight' => $p[4], 'length' => $p[5],
                'lock_type' => $p[6], 'stamping' => $p[7], 'width' => '4 mm', 'thickness' => '1.2 mm',
                'style_ref' => $c . '-' . substr($sku, -6), 'stone_setting' => in_array($p[1], ['Ring', 'Earrings', 'Pendant', 'Necklace'], true) ? 'CZ / Prong setting' : '—',
                'master_sample' => 'Approved Master Sample Available', 'order_qty' => $oq, 'ready_qty' => $rq,
                'insp_qty' => $plan['insp_qty'], 'plan_json' => json_encode($plan['plan']), 'status' => 'PENDING', 'updated_at' => $now];
            $iid = insert('qc_items', $item);
            $mode2 = $res;
            if ($res === 'PROG') $mode2 = [0 => 'PROGDONE', 1 => 'HOLD', 2 => 'PENDING', 3 => 'PENDING'][$k];
            if ($res && $mode2 !== 'PENDING') seed_inspection($iid, $job, $item, $mode2, $off, $checks, $tests, $U);
        }
    }
    // notifications & email log for nicer demo
    notify($U['rahul'], 'New QC assigned · KP – Nirvana · PO 82002', 'app');
    notify($U['ravi'], 'JS-' . date('Y') . '-0010 submitted for review', 'review');
}

function seed_inspection(int $iid, array $job, array $item, string $res, int $off, array $checks, array $tests, array $U): void {
    $insp = (int)$item['insp_qty']; $dt = date('Y-m-d 15:42:00', strtotime("$off days"));
    $rej = match ($res) { 'PASS' => (int)floor($insp * 0.008), 'FAIL', 'FAIL_SUB' => max(3, (int)round($insp * 0.86)), 'CONDITIONAL' => 0, 'PROGDONE' => 12, default => 0 };
    if (in_array($res, ['HOLD'], true)) {
        update('qc_items', ['status' => 'HOLD', 'step' => 3, 'hold_for' => 'client', 'hold_status' => 'PENDING',
            'hold_reason' => 'Centre stone shade differs from approved sample (lighter blue). Please confirm whether to proceed.',
            'hold_by' => 'JS-010 · Rahul Sharma', 'hold_at' => now(), 'started_at' => now()], 'id=?', [$iid]);
        return;
    }
    $fail = in_array($res, ['FAIL', 'FAIL_SUB'], true);
    $C = []; foreach ($checks as $i => $c) {
        $r = ($fail && in_array($i, [3, 5], true)) ? 'FAIL' : ($i === 9 && !in_array($item['product'], ['Necklace', 'Chain', 'Earrings'], true) ? 'NA' : 'PASS');
        $C[] = ['name' => $c['name'], 'result' => $r, 'remark' => $r === 'FAIL' ? $c['fail_remark'] : ($r === 'NA' ? $c['na_remark'] : $c['pass_remark'])]; }
    $T = []; foreach ($tests as $i => $t) { $r = str_starts_with($t['name'], 'Magnetic') && str_contains((string)$item['metal'], 'Gold') ? 'NA' : ($fail && $i === 7 ? 'FAIL' : 'PASS');
        $T[] = ['name' => $t['name'], 'qty' => $r === 'NA' ? 'NA' : 2, 'result' => $r, 'remark' => $r === 'FAIL' ? 'XRF micron reading below 1.0 µm on spots' : $t['method']]; }
    $M = [['param' => 'Weight', 'spec' => '2.75', 'tol' => '0.05', 'unit' => 'g', 'qty' => 2, 'actual' => '2.76', 'result' => 'PASS'],
          ['param' => 'Length', 'spec' => '18', 'tol' => '0.2', 'unit' => 'in', 'qty' => 2, 'actual' => $res === 'CONDITIONAL' ? '17.6' : '18.1', 'result' => $res === 'CONDITIONAL' ? 'FAIL' : 'PASS'],
          ['param' => 'Width', 'spec' => '4', 'tol' => '0.2', 'unit' => 'mm', 'qty' => 2, 'actual' => '4.0', 'result' => 'PASS'],
          ['param' => 'Thickness', 'spec' => '1.2', 'tol' => '0.1', 'unit' => 'mm', 'qty' => 2, 'actual' => '1.1', 'result' => 'PASS']];
    $defs = [];
    if ($rej > 0) {
        if ($fail) {
            $a = (int)round($rej * 0.55); $b = (int)round($rej * 0.3); $c = $rej - $a - $b;
            $defs[] = ['category' => 'Plating / Micron Issue', 'type' => 'Micron off / uneven plating thickness', 'severity' => 'CRITICAL', 'qty' => $a];
            $defs[] = ['category' => 'Stone / Setting Issue', 'type' => 'Loose stone / uneven bezel setting gap', 'severity' => 'MAJOR', 'qty' => $b];
            if ($c > 0) $defs[] = ['category' => 'Polish / Surface Finish Issue', 'type' => 'Micro-scratches on surface', 'severity' => 'MINOR', 'qty' => $c];
        } else {
            $pool = [['Polish / Surface Finish Issue', 'Polish marks', 'MINOR'], ['Finding / Lock / Assembly Issue', 'Clasp lock tension tight / loose', 'MAJOR'], ['Stamping / Hallmarking Issue', 'Stamping unclear', 'MINOR']];
            $pick = $pool[$iid % 3]; $defs[] = ['category' => $pick[0], 'type' => $pick[1], 'severity' => $pick[2], 'qty' => $rej];
        }
    }
    foreach ($defs as $d) insert('item_defects', ['item_id' => $iid] + $d + ['remark' => '']);
    $status = match ($res) { 'FAIL_SUB' => 'SUBMITTED', 'PROGDONE' => 'COMPLETED', default => 'APPROVED' };
    $final = $res === 'FAIL_SUB' || $res === 'PROGDONE' ? null : $res;
    $data = ['status' => $status, 'step' => 8, 'inspected_qty' => $insp, 'accepted_qty' => $insp - $rej, 'rejected_qty' => $rej, 'insp_date' => substr($dt, 0, 10),
        'gps' => '26.7838, 75.8216 · ' . date('h:i A', strtotime($dt)), 'lot_no' => 'LOT-' . strtoupper(date('M-d', strtotime($dt))) . 'A', 'xrf_machine' => 'Goldscope SD 550',
        'factory_coop' => 'GOOD', 'factory_line' => $fail ? 'AVERAGE' : 'GOOD', 'factory_ready' => $fail ? 'AVERAGE' : 'GOOD', 'checks_json' => json_encode($C), 'meas_json' => json_encode($M), 'tests_json' => json_encode($T),
        'remark' => $fail ? "$rej pcs failed due to micron thickness below spec on XRF and bezel setting gaps." : ($rej ? "$rej pcs " . strtolower($defs[0]['type']) . ' segregated for rework; balance lot passes.' : 'All pieces passed 100% check.'),
        'action_required' => $fail ? 'Factory advised to strip, re-polish, rectify setting and re-plate to ≥1.0 micron before offering for re-inspection.' : ($rej ? 'Rework segregated pieces before dispatch.' : ''),
        'system_result' => $res === 'CONDITIONAL' ? 'FAIL' : (in_array($res, ['FAIL', 'FAIL_SUB'], true) ? 'FAIL' : 'PASS'),
        'inspector_result' => $res === 'CONDITIONAL' ? 'CONDITIONAL' : (in_array($res, ['FAIL', 'FAIL_SUB'], true) ? 'FAIL' : 'PASS'),
        'final_result' => $final, 'submitted_at' => $dt, 'started_at' => date('Y-m-d 10:34:00', strtotime("$off days"))];
    if ($status === 'APPROVED') {
        $code = val('SELECT code FROM clients WHERE id=?', [$job['client_id']]);
        $pre = 'JS/' . date('Y/m', strtotime($dt)) . '/' . $code . '_' . vendor_short($job) . '/';
        $data += ['report_no' => $pre . sprintf('%03d', (int)val('SELECT COUNT(*) FROM qc_items WHERE report_no LIKE ?', [$pre . '%']) + 1),
            'locked' => 1, 'approved_at' => date('Y-m-d 16:05:00', strtotime("$off days")), 'approved_by' => $U['ravi']];
        if ($res === 'CONDITIONAL') {
            $data['manager_note'] = 'All other checks PASS. Length short by 0.4" across the lot; chain extender can compensate. Recommend conditional acceptance.';
            $data['deviation_json'] = json_encode([['param' => 'Length', 'spec' => '18.0"', 'actual' => '17.6"', 'dev' => '−0.4"', 'pcs' => 900], ['param' => 'Weight', 'spec' => '2.75 g', 'actual' => '2.71 g', 'dev' => '−1.5%', 'pcs' => 900]]);
            $data['concession_status'] = 'PENDING'; $data['concession_due'] = date('Y-m-d', strtotime('+2 days'));
        }
    }
    update('qc_items', $data, 'id=?', [$iid]);
    if ($status === 'APPROVED') {
        insert('audit_log', ['entity' => 'item', 'entity_id' => $iid, 'user_id' => null, 'action' => 'REPORT_GENERATED', 'field' => 'Report generated', 'old_val' => null, 'new_val' => null, 'rev' => 0, 'created_at' => $data['approved_at']]);
        $em = ['vendor' => val('SELECT email FROM vendors WHERE id=?', [$job['vendor_id']]), 'client' => val('SELECT ho_email FROM clients WHERE id=?', [$job['client_id']]), 'admin' => 'jewelsetu.jqc@gmail.com'];
        foreach ([['Vendor', 'vendor'], ['Client head office', 'client'], ['JEWELSETU admin', 'admin']] as [$t, $k])
            insert('email_log', ['job_id' => $job['id'], 'item_id' => $iid, 'recipient_type' => $t, 'email' => $em[$k],
                'subject' => 'QC Inspection Report | ' . $data['report_no'], 'status' => 'SENT', 'error' => null, 'sent_at' => date('Y-m-d 16:10:00', strtotime("$off days"))]);
    }
}
