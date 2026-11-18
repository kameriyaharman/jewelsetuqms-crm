<?php
/* ---------- inspector app (PWA) ---------- */
function my_job(array $job): bool { $me = user(); return (int)$job['inspector_id'] === (int)$me['id'] || (int)$job['inspector2_id'] === (int)$me['id']; }
/** Can the current user change this SKU's inspection data? Super Admin: always. Inspector: while open, and after submit (until approved) when allowed. */
function item_editable(array $it, array $job): bool {
    if (is_role('super_admin')) return true;
    $st = ['PENDING', 'IN_PROGRESS', 'COMPLETED', 'RETURNED'];
    if (flag('inspector_edit_after_submit')) array_push($st, 'SUBMITTED', 'UNDER_REVIEW');
    return in_array($it['status'], $st, true) && in_array($job['status'], ['ASSIGNED', 'IN_PROGRESS', 'SUBMITTED', 'UNDER_REVIEW', 'RETURNED'], true);
}
const ITEM_INFO_FIELDS = ['sku', 'product', 'metal', 'plating', 'target_weight', 'length', 'lock_type', 'stamping', 'width', 'thickness', 'style_ref', 'stone_setting', 'spec_detail', 'instructions'];

api('GET', 'app/home', ['inspector'], function () {
    $me = user();
    $jobs = all("SELECT " . img_job_sql() . " img, j.id, j.job_no, j.po_no, j.status, j.scheduled_date, j.time_slot, j.location, c.code client_code, v.name vendor_name, (SELECT COUNT(*) FROM qc_items i WHERE i.job_id=j.id) skus,
       (SELECT COUNT(*) FROM qc_items i WHERE i.job_id=j.id AND i.status IN ('SUBMITTED','UNDER_REVIEW','APPROVED')) done,
       (SELECT COUNT(*) FROM qc_items i WHERE i.job_id=j.id AND i.status='RETURNED') ret, (SELECT COUNT(*) FROM qc_items i WHERE i.job_id=j.id AND i.status='HOLD') hold
       FROM qc_jobs j JOIN clients c ON c.id=j.client_id JOIN vendors v ON v.id=j.vendor_id
       WHERE (j.inspector_id=? OR j.inspector2_id=?) AND j.status IN ('ASSIGNED','IN_PROGRESS','SUBMITTED','UNDER_REVIEW','RETURNED','PARTIAL')
       ORDER BY CASE WHEN j.status='RETURNED' THEN 0 WHEN j.status='IN_PROGRESS' THEN 1 WHEN j.status='PARTIAL' THEN 3 ELSE 2 END, j.scheduled_date", [$me['id'], $me['id']]);
    $skus = all("SELECT " . img_item_sql() . " img, i.id, i.sku, i.product, i.ready_qty, i.status, j.job_no FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id WHERE (j.inspector_id=? OR j.inspector2_id=?) AND j.status IN ('ASSIGNED','IN_PROGRESS','SUBMITTED','UNDER_REVIEW','RETURNED')", [$me['id'], $me['id']]);
    return ['jobs' => $jobs, 'skus' => $skus, 'can_new' => flag('inspector_new_job'), 'today' => count(array_filter($jobs, fn($j) => $j['scheduled_date'] === today())),
        'pending' => (int)val("SELECT COUNT(*) FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id WHERE (j.inspector_id=? OR j.inspector2_id=?) AND i.status IN ('PENDING','IN_PROGRESS','HOLD','RETURNED','COMPLETED') AND j.status NOT IN ('CANCELLED','CLOSED')", [$me['id'], $me['id']]),
        'completed' => (int)val("SELECT COUNT(*) FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id WHERE (j.inspector_id=? OR j.inspector2_id=?) AND i.status IN ('SUBMITTED','UNDER_REVIEW','APPROVED')", [$me['id'], $me['id']])];
});
api('GET', 'app/job', ['inspector'], function () {
    $job = job_load((int)in_('id')); if (!$job || !my_job($job)) fail('Job not found', 404);
    $job['scope_label'] = scope_label($job);
    $lines = []; foreach (all('SELECT id FROM qc_items WHERE job_id=? AND parent_id IS NULL', [$job['id']]) as $x) { $L = line_stats($job, (int)$x['id']); unset($L['log']); $lines[$x['id']] = $L; }
    return ['job' => $job, 'lines' => $lines, 'can_lot' => flag('inspector_new_job') && !in_array($job['status'], ['CLOSED', 'CANCELLED', 'EMAILED'], true),
        'items' => all('SELECT ' . img_item_sql() . ' img, i.id, i.sku, i.product, i.ready_qty, i.insp_qty, i.order_qty, i.status, i.instructions, i.parent_id, i.lot_seq FROM qc_items i WHERE i.job_id=? ORDER BY COALESCE(i.parent_id, i.id), COALESCE(i.lot_seq, 1), i.id', [$job['id']]),
        'attachments' => array_map(fn($a) => $a + ['url' => url('file', ['p' => $a['path']])], all('SELECT * FROM attachments WHERE job_id=?', [$job['id']])),
        'can_add' => flag('inspector_new_job') && in_array($job['status'], ['ASSIGNED', 'IN_PROGRESS', 'SUBMITTED', 'UNDER_REVIEW', 'RETURNED'], true)];
});
api('GET', 'app/inspect', ['inspector', 'super_admin'], function () {
    $it = one('SELECT * FROM qc_items WHERE id=?', [(int)in_('item')]); $job = $it ? job_load((int)$it['job_id']) : null;
    $admin = is_role('super_admin');
    if (!$job || (!$admin && !my_job($job))) fail('SKU not found in your jobs', 404);
    $job = item_job($job, $it); $job['scope_label'] = scope_label($job);
    $editable = item_editable($it, $job);
    $cpm = all("SELECT * FROM checkpoints WHERE (client_id IS NULL OR client_id=?) AND (category IS NULL OR category=? OR category='') ORDER BY sort, id", [$job['client_id'], $it['product']]);
    $remarks = []; foreach ($cpm as $c) $remarks[$c['name']] = ['PASS' => $c['pass_remark'], 'FAIL' => $c['fail_remark'], 'NA' => $c['na_remark']];
    $checks = jdec($it['checks_json']); if (!$checks) foreach ($cpm as $c) $checks[] = ['name' => $c['name'], 'result' => '', 'remark' => ''];
    $tests = jdec($it['tests_json']); if (!$tests) foreach (all('SELECT name, method FROM onsite_tests ORDER BY sort, id') as $t) $tests[] = ['name' => $t['name'], 'qty' => 2, 'result' => '', 'remark' => (string)$t['method']];
    $meas = jdec($it['meas_json']);
    if (!$meas) { $n = fn($s) => preg_replace('/[^0-9.]/', '', explode('+', (string)$s)[0]) ?: '';
        $meas = [['param' => 'Weight', 'spec' => $n($it['target_weight']), 'tol' => '0.05', 'unit' => 'g', 'qty' => 2, 'actual' => '', 'result' => ''],
            ['param' => 'Length', 'spec' => $n($it['length']), 'tol' => '0.2', 'unit' => str_contains((string)$it['length'], 'mm') ? 'mm' : 'in', 'qty' => 2, 'actual' => '', 'result' => ''],
            ['param' => 'Width', 'spec' => $n($it['width']), 'tol' => '0.2', 'unit' => 'mm', 'qty' => 2, 'actual' => '', 'result' => ''],
            ['param' => 'Thickness', 'spec' => $n($it['thickness']), 'tol' => '0.1', 'unit' => 'mm', 'qty' => 2, 'actual' => '', 'result' => '']]; }
    $dm = []; foreach (all('SELECT d.name, d.severity, c.name cat FROM defect_master d JOIN defect_categories c ON c.id=d.category_id ORDER BY c.sort, d.name') as $d) $dm[$d['cat']][] = ['n' => $d['name'], 's' => $d['severity']];
    $it['img'] = item_img((int)$it['id']);
    $line = line_stats(job_load((int)$it['job_id']), line_root_id($it));
    return ['item' => $it, 'line' => $line, 'job' => $job, 'editable' => $editable, 'checks' => $checks, 'tests' => $tests, 'meas' => $meas, 'remarks' => $remarks,
        'defects' => all('SELECT category, type, severity, qty, remark FROM item_defects WHERE item_id=?', [$it['id']]),
        'photos' => array_map(fn($p) => ['id' => (int)$p['id'], 'kind' => $p['kind'], 'remark' => $p['remark'], 'url' => url('file', ['p' => $p['path']])], all("SELECT * FROM item_photos WHERE kind<>'ref' AND item_id=? ORDER BY id", [$it['id']])),
        'dm' => $dm, 'plan' => jdec($it['plan_json']), 'steps' => array_values(STEPS), 'threshold' => client_threshold((int)$job['client_id']),
        'aql' => $job['scope'] === 'BULK' && $job['bulk_mode'] === 'AQL', 'locked_qty' => flag('lock_inspected_quantity') && $job['scope'] !== 'SAMPLE' && !$admin,
        'need_photo' => flag('require_defect_photo'), 'gps' => flag('gps_on_inspection_start'),
        'attachments' => array_map(fn($a) => $a + ['url' => url('file', ['p' => $a['path']])], all('SELECT * FROM attachments WHERE job_id=? AND (item_id IS NULL OR item_id=?)', [$job['id'], $it['id']])),
        'siblings' => all('SELECT ' . img_item_sql() . ' img, i.id, i.sku, i.status FROM qc_items i WHERE i.job_id=? ORDER BY i.id', [$job['id']]),
        'admin' => $admin, 'can_info' => $admin || ($editable && $job['source'] === 'Inspector on-site'), 'after_submit' => in_array($it['status'], ['SUBMITTED', 'UNDER_REVIEW'], true),
        'approved' => $it['status'] === 'APPROVED', 'insp_types' => ['REGULAR', 'RE-INSPECTION', 'INLINE', 'PRE-PRODUCTION', 'FINAL / PRE-SHIPMENT', 'SPECIAL']];
});
api('GET', 'app/history', ['inspector'], function () {
    $me = user(); $f = (string)in_('f', 'ALL'); $map = ['SUBMITTED' => ['SUBMITTED', 'UNDER_REVIEW'], 'RETURNED' => ['RETURNED'], 'APPROVED' => ['APPROVED']];
    $sql = "SELECT " . img_item_sql() . " img, i.id, i.sku, i.status, i.report_no, i.approved_at, i.submitted_at, j.job_no, v.name vendor_name, c.code client_code FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id JOIN vendors v ON v.id=j.vendor_id JOIN clients c ON c.id=j.client_id
        WHERE (j.inspector_id=? OR j.inspector2_id=?) AND i.status IN ('SUBMITTED','UNDER_REVIEW','RETURNED','APPROVED')";
    if (isset($map[$f])) $sql .= " AND i.status IN ('" . implode("','", $map[$f]) . "')";
    return ['rows' => all($sql . ' ORDER BY COALESCE(i.approved_at, i.submitted_at) DESC LIMIT 100', [$me['id'], $me['id']])];
});

api('POST', 'app/inspect', ['inspector', 'super_admin'], function () {
$in = inp();
$act = (string)($in['action'] ?? '');
$it = one('SELECT * FROM qc_items WHERE id=?', [(int)($in['item_id'] ?? 0)]);
if (!$it) fail('Item not found', 404);
$job = item_job(job_load((int)$it['job_id']), $it);
$me = user(); $admin = is_role('super_admin');
if (!$admin && !my_job($job)) fail('Not your job', 403);
if (!$admin && !in_array($job['status'], ['ASSIGNED', 'IN_PROGRESS', 'SUBMITTED', 'UNDER_REVIEW', 'RETURNED'], true)) fail('Job is not open for inspection (' . $job['status'] . ')', 409);
$editable = item_editable($it, $job);
$after = in_array($it['status'], ['SUBMITTED', 'UNDER_REVIEW', 'APPROVED'], true);

switch ($act) {
case 'start':
    if (!$editable) json_out(['error' => 'Read-only'], 409);
    $d = ['updated_at' => now()];
    if (!$it['started_at']) $d['started_at'] = now();
    if ($it['status'] === 'PENDING') $d['status'] = 'IN_PROGRESS';
    if (!empty($in['gps'])) $d['gps'] = mb_substr((string)$in['gps'], 0, 120);
    if (!$it['insp_date']) $d['insp_date'] = today();
    update('qc_items', $d, 'id=?', [$it['id']]);
    if (in_array($job['status'], ['ASSIGNED'], true)) { q("UPDATE qc_jobs SET status='IN_PROGRESS', started_at=? WHERE id=?", [now(), $job['id']]); audit('job', (int)$job['id'], 'STATUS', 'status', 'ASSIGNED', 'IN_PROGRESS'); }
    json_out(['ok' => true]);

case 'save':
    if (!$editable) json_out(['error' => 'This SKU is locked (' . $it['status'] . ')'], 409);
    $s = (int)($in['step'] ?? 0); $D = (array)($in['data'] ?? []);
    $up = ['updated_at' => now(), 'step' => max((int)$it['step'], $s)];
    if ($it['status'] === 'PENDING') $up['status'] = 'IN_PROGRESS';
    if ($after) audit('item', (int)$it['id'], $admin ? 'ADMIN_EDIT' : 'EDIT_AFTER_SUBMIT', 'step', null, STEPS[$s] ?? (string)$s, (int)$it['rev']);
    if ($s === 1) { $up['reinspection'] = !empty($D['reinspection']) ? 1 : 0; if (isset($D['insp_type'])) $up['insp_type'] = mb_substr((string)$D['insp_type'], 0, 30); if (!empty($D['insp_date'])) $up['insp_date'] = $D['insp_date']; if (!empty($D['gps'])) $up['gps'] = mb_substr((string)$D['gps'], 0, 120);
        foreach (['lot_no', 'xrf_machine', 'master_sample'] as $k) if (isset($D[$k])) $up[$k] = mb_substr(trim((string)$D[$k]), 0, 120); }
    if ($s === 2) {
        $insp = (int)($D['inspected_qty'] ?? 0); $acc = (int)($D['accepted_qty'] ?? 0); $rej = (int)($D['rejected_qty'] ?? 0);
        $locked = flag('lock_inspected_quantity') && $job['scope'] !== 'SAMPLE';
        if ($locked && !$admin) $insp = (int)$it['insp_qty'];
        if ($insp <= 0) json_out(['error' => 'Inspection quantity must be more than 0'], 422);
        if ($insp !== (int)$it['inspected_qty'] && $insp !== (int)$it['insp_qty']) audit('item', (int)$it['id'], 'UPDATE', 'Inspected qty', $it['inspected_qty'] ?: $it['insp_qty'], $insp, (int)$it['rev']);
        if ($insp > (int)$it['ready_qty'] && !$job['allow_excess']) json_out(['error' => 'Inspection qty cannot exceed Ready qty'], 422);
        if (setting('block_qty_mismatch', '1') === '1' && $acc + $rej !== $insp) json_out(['error' => "Accepted + Rejected (" . ($acc + $rej) . ") ≠ Inspected ($insp). Please check before proceeding."], 422);
        $up += ['inspected_qty' => $insp, 'accepted_qty' => $acc, 'rejected_qty' => $rej];
    }
    if ($s === 3) $up['checks_json'] = json_encode(array_values(array_map(fn($c) => ['name' => (string)$c['name'], 'result' => in_array($c['result'] ?? '', ['PASS', 'FAIL', 'NA'], true) ? $c['result'] : '', 'remark' => (string)($c['remark'] ?? '')], (array)($D['checks'] ?? []))));
    if ($s === 4) {
        $m = [];
        foreach ((array)($D['meas'] ?? []) as $x) {
            if (trim((string)($x['param'] ?? '')) === '') continue;
            $spec = (float)preg_replace('/[^0-9.\-]/', '', (string)$x['spec']); $tol = abs((float)preg_replace('/[^0-9.]/', '', (string)$x['tol'])); $act2 = trim((string)$x['actual']);
            $res = $act2 === '' ? '' : (abs((float)$act2 - $spec) <= $tol + 1e-9 ? 'PASS' : 'FAIL');
            $m[] = ['param' => (string)$x['param'], 'spec' => (string)$x['spec'], 'tol' => (string)$x['tol'], 'unit' => (string)($x['unit'] ?? ''), 'qty' => (string)($x['qty'] ?? 2), 'actual' => $act2, 'result' => $res];
        }
        $up['meas_json'] = json_encode($m);
    }
    if ($s === 5) $up['tests_json'] = json_encode(array_values(array_map(fn($c) => ['name' => (string)$c['name'], 'qty' => (string)($c['qty'] ?? ''), 'result' => in_array($c['result'] ?? '', ['PASS', 'FAIL', 'NA'], true) ? $c['result'] : '', 'remark' => (string)($c['remark'] ?? '')], (array)($D['tests'] ?? []))));
    if ($s === 6) {
        q('DELETE FROM item_defects WHERE item_id=?', [$it['id']]);
        foreach ((array)($D['defects'] ?? []) as $d) if ((int)($d['qty'] ?? 0) > 0 && trim((string)($d['type'] ?? '')) !== '')
            insert('item_defects', ['item_id' => $it['id'], 'category' => (string)$d['category'], 'type' => (string)$d['type'],
                'severity' => in_array($d['severity'] ?? '', ['CRITICAL', 'MAJOR', 'MINOR'], true) ? $d['severity'] : 'MAJOR', 'qty' => (int)$d['qty'], 'remark' => (string)($d['remark'] ?? '')]);
    }
    if ($s === 8) { $up['inspector_result'] = in_array($D['result'] ?? '', ['PASS', 'FAIL', 'PENDING', 'CONDITIONAL'], true) ? $D['result'] : null; $up['remark'] = (string)($D['remark'] ?? ''); $up['action_required'] = (string)($D['action_required'] ?? '');
        foreach (['factory_coop', 'factory_line', 'factory_ready'] as $k) $up[$k] = in_array($D[$k] ?? '', ['GOOD', 'AVERAGE', 'POOR'], true) ? $D[$k] : null;
        $up['spec_check'] = in_array($D['spec_check'] ?? '', ['OK', 'NOT OK', 'NA'], true) ? $D['spec_check'] : null; $up['spec_remark'] = (string)($D['spec_remark'] ?? '');
        $up['factory_rep'] = mb_substr(trim((string)($D['factory_rep'] ?? '')), 0, 200); }
    update('qc_items', $up, 'id=?', [$it['id']]);
    $fresh = one('SELECT * FROM qc_items WHERE id=?', [$it['id']]);
    $sys = system_result($job + ['client_fail_threshold' => client_threshold((int)$job['client_id'])], $fresh, all('SELECT * FROM item_defects WHERE item_id=?', [$it['id']]), jdec($fresh['checks_json']), jdec($fresh['meas_json']), jdec($fresh['tests_json']));
    q('UPDATE qc_items SET system_result=? WHERE id=?', [$sys, $it['id']]);
    json_out(['ok' => true, 'system_result' => $sys, 'saved_at' => date('h:i A'), 'regen' => $admin && (int)$it['locked'] === 1]);

case 'item_info':
    $canInfo = $admin || ($editable && $job['source'] === 'Inspector on-site');
    if (!$canInfo) json_out(['error' => 'Only Super Admin can change product details'], 403);
    $D = (array)($in['data'] ?? []); $up = [];
    foreach (ITEM_INFO_FIELDS as $k) if (array_key_exists($k, $D) && (string)$D[$k] !== (string)$it[$k]) $up[$k] = $k === 'sku' ? strtoupper(trim((string)$D[$k])) : (string)$D[$k];
    if (isset($up['sku']) && $up['sku'] === '') json_out(['error' => 'SKU cannot be empty'], 422);
    foreach (['order_qty', 'ready_qty'] as $k) if (isset($D[$k]) && (int)$D[$k] !== (int)$it[$k]) $up[$k] = max(0, (int)$D[$k]);
    if (isset($up['ready_qty']) && $job['scope'] !== 'SAMPLE') { $plan = item_plan($job, (int)$up['ready_qty']); $up['insp_qty'] = $plan['insp_qty']; $up['plan_json'] = json_encode($plan['plan']); }
    foreach ($up as $k => $v) audit('item', (int)$it['id'], $admin ? 'ADMIN_EDIT' : 'UPDATE', $k, $it[$k], $v, (int)$it['rev']);
    if ($up) update('qc_items', $up + ['updated_at' => now()], 'id=?', [$it['id']]);
    $jup = [];
    foreach (['po_no', 'qc_type', 'location'] as $k) if ($admin && isset($D['job_' . $k]) && trim((string)$D['job_' . $k]) !== '' && (string)$D['job_' . $k] !== (string)$job[$k]) $jup[$k] = trim((string)$D['job_' . $k]);
    foreach ($jup as $k => $v) audit('job', (int)$job['id'], 'ADMIN_EDIT', $k, $job[$k], $v);
    if ($jup) update('qc_jobs', $jup, 'id=?', [$job['id']]);
    json_out(['ok' => true, 'changed' => count($up) + count($jup), 'regen' => $admin && (int)$it['locked'] === 1]);

case 'regen':
    if (!$admin) json_out(['error' => 'Only Super Admin'], 403);
    if (!(int)$it['locked']) json_out(['error' => 'Report not approved yet — nothing to regenerate'], 409);
    require_once ROOT . '/app/workflow.php'; require_once ROOT . '/app/pdf.php'; require_once ROOT . '/app/mailer.php';
    $reason = trim((string)($in['reason'] ?? '')) ?: 'Corrected by Super Admin';
    if (in_array($in['final'] ?? '', ['PASS', 'FAIL', 'CONDITIONAL'], true) && $in['final'] !== $it['final_result']) { audit('item', (int)$it['id'], 'REVISION', 'Final Result', $it['final_result'], $in['final'], (int)$it['rev'] + 1); q('UPDATE qc_items SET final_result=? WHERE id=?', [$in['final'], $it['id']]); }
    $rev = (int)$it['rev'] + 1;
    update('qc_items', ['rev' => $rev, 'updated_at' => now()], 'id=?', [$it['id']]);
    audit('item', (int)$it['id'], 'UNLOCK', 'Reason', null, $reason, $rev);
    [, , $file] = item_pdf((int)$it['id'], true);
    if (!empty($in['email'])) email_report($job, one('SELECT * FROM qc_items WHERE id=?', [$it['id']]), $file, 'REVISED QC Report ' . sprintf('REV %02d', $rev) . " | {$it['report_no']}",
        "Dear Sir/Madam,\n\nThe QC report {$it['report_no']} has been revised (Revision " . sprintf('%02d', $rev) . ").\nReason: $reason\n\nPlease refer to the attached revised report.");
    if ($job['po_pdf'] ?? null) job_pdf((int)$job['id'], true);
    json_out(['ok' => true, 'message' => 'Revision ' . sprintf('%02d', $rev) . ' saved — new PDF generated' . (!empty($in['email']) ? ' and re-sent.' : '.') . ' Old PDF kept.']);

case 'photo':
    if (!$editable) json_out(['error' => 'Read-only'], 409);
    $data = (string)($in['data'] ?? '');
    if (!preg_match('#^data:image/(jpeg|png|webp);base64,#', $data, $m)) json_out(['error' => 'Bad image'], 422);
    $bin = base64_decode(substr($data, strpos($data, ',') + 1));
    if (!$bin || strlen($bin) > 8 * 1024 * 1024) json_out(['error' => 'Image too large'], 422);
    $dir = UPLOAD_DIR . '/photos/' . date('Ym'); if (!is_dir($dir)) mkdir($dir, 0775, true);
    $name = 'photos/' . date('Ym') . '/' . $it['id'] . '_' . bin2hex(random_bytes(5)) . '.' . ($m[1] === 'jpeg' ? 'jpg' : $m[1]);
    file_put_contents(UPLOAD_DIR . '/' . $name, $bin);
    $pid = insert('item_photos', ['item_id' => $it['id'], 'kind' => in_array($in['kind'] ?? '', ['defect', 'product', 'xrf', 'weight', 'dimension', 'packaging', 'spec', 'other'], true) ? $in['kind'] : 'other',
        'path' => $name, 'remark' => mb_substr((string)($in['remark'] ?? ''), 0, 200), 'created_at' => now()]);
    json_out(['ok' => true, 'id' => $pid, 'url' => url('file', ['p' => $name])]);

case 'photo_delete':
    if (!$editable) json_out(['error' => 'Read-only'], 409);
    $ph = one('SELECT * FROM item_photos WHERE id=? AND item_id=?', [(int)($in['photo_id'] ?? 0), $it['id']]);
    if ($ph && $ph['kind'] === 'ref') json_out(['error' => 'The reference / master product image is read-only for inspection. Back Office, QC Manager or Super Admin can change it via Edit design.'], 403);
    if ($ph) { if (!(int)val('SELECT COUNT(*) FROM item_photos WHERE path=? AND id<>?', [$ph['path'], $ph['id']])) @unlink(UPLOAD_DIR . '/' . $ph['path']); q('DELETE FROM item_photos WHERE id=?', [$ph['id']]); }
    json_out(['ok' => true]);

case 'hold':
    if (!$editable) json_out(['error' => 'Read-only'], 409);
    $for = ($in['hold_for'] ?? '') === 'vendor' ? 'vendor' : 'client';
    $reason = trim((string)($in['reason'] ?? ''));
    if ($reason === '') json_out(['error' => 'Please write what needs approval'], 422);
    update('qc_items', ['status' => 'HOLD', 'hold_for' => $for, 'hold_reason' => $reason, 'hold_status' => 'PENDING', 'hold_response' => null,
        'hold_by' => $me['emp_code'] . ' · ' . $me['name'], 'hold_at' => now(), 'hold_resp_at' => null], 'id=?', [$it['id']]);
    audit('item', (int)$it['id'], 'HOLD_FOR_APPROVAL', 'hold_for', null, "$for: $reason");
    $msg = "SKU {$it['sku']} on HOLD for your approval · PO {$job['po_no']} — $reason";
    if ($for === 'client') notify_role('client', $msg, 'approvals', ['client_id' => $job['client_id']]); else notify_role('vendor', $msg, 'approvals', ['vendor_id' => $job['vendor_id']]);
    notify_managers($job, "Inspector put {$it['sku']} on hold for $for approval", 'request/' . $job['id']);
    json_out(['ok' => true]);

case 'submit':
    if (!$editable || $it['status'] === 'APPROVED') json_out(['error' => $it['status'] === 'APPROVED' ? 'Report already approved — use Regenerate PDF' : 'Already submitted'], 409);
    $f = one('SELECT * FROM qc_items WHERE id=?', [$it['id']]);
    $def = all('SELECT * FROM item_defects WHERE item_id=?', [$it['id']]);
    $miss = [];
    if ((int)$f['inspected_qty'] <= 0) $miss[] = 'Quantity';
    if ((int)$f['accepted_qty'] + (int)$f['rejected_qty'] !== (int)$f['inspected_qty']) $miss[] = 'Quantity balance (Accepted + Rejected ≠ Inspected)';
    foreach (jdec($f['checks_json']) as $c) if (($c['result'] ?? '') === '') { $miss[] = 'Quality checks'; break; }
    if (!jdec($f['checks_json'])) $miss[] = 'Quality checks';
    if (!jdec($f['tests_json'])) $miss[] = 'On-site tests';
    $dq = array_sum(array_column($def, 'qty'));
    if ((int)$f['rejected_qty'] > 0 && !$def) $miss[] = 'Defect details (rejected qty > 0)';
    if (flag('require_defect_photo') && $dq > 0 && !val("SELECT COUNT(*) FROM item_photos WHERE item_id=? AND kind='defect'", [$it['id']])) $miss[] = 'At least 1 defect photo';
    if (!$f['inspector_result']) $miss[] = 'Final result';
    if ($miss) json_out(['error' => 'Cannot submit — complete: ' . implode(', ', array_unique($miss))], 422);
    $wasReturned = in_array($f['status'], ['RETURNED', 'SUBMITTED', 'UNDER_REVIEW'], true);
    update('qc_items', ['status' => 'SUBMITTED', 'submitted_at' => now(), 'return_sections' => null], 'id=?', [$it['id']]);
    audit('item', (int)$it['id'], $wasReturned ? 'RESUBMITTED' : 'SUBMITTED', 'status', $f['status'], 'SUBMITTED');
    refresh_job_status((int)$job['id']);
    if (!val('SELECT submitted_at FROM qc_jobs WHERE id=?', [$job['id']])) q('UPDATE qc_jobs SET submitted_at=? WHERE id=?', [now(), $job['id']]);
    foreach (manager_ids($job) as $m) notify($m, ($wasReturned ? 'Resubmitted' : 'Submitted') . " for review · {$it['sku']} · {$job['job_no']} ({$me['emp_code']})", 'review/' . $it['id']);
    $next = val("SELECT id FROM qc_items WHERE job_id=? AND status IN ('PENDING','IN_PROGRESS','RETURNED','COMPLETED') AND id<>? ORDER BY id LIMIT 1", [$job['id'], $it['id']]);
    json_out(['ok' => true, 'next' => $next ? (int)$next : null]);
}
fail('Unknown action', 400);

});

/* ----- on-site job creation by the inspector (vendor hands over files at the factory) ----- */
function save_data_image(string $data, string $sub): ?string {
    if (!preg_match('#^data:image/(jpeg|png|webp);base64,#', $data, $m)) return null;
    $bin = base64_decode(substr($data, strpos($data, ',') + 1));
    if (!$bin || strlen($bin) > 8 * 1024 * 1024) return null;
    $dir = UPLOAD_DIR . "/$sub/" . date('Ym'); if (!is_dir($dir)) mkdir($dir, 0775, true);
    $name = "$sub/" . date('Ym') . '/' . bin2hex(random_bytes(6)) . '.' . ($m[1] === 'jpeg' ? 'jpg' : $m[1]);
    return file_put_contents(UPLOAD_DIR . '/' . $name, $bin) ? $name : null;
}
/** Insert SKUs (with optional product photo as data URL) into a job. Returns ids. */
function add_onsite_items(array $job, array $rows): array {
    $ids = []; $seen = array_map('strtoupper', array_column(all('SELECT sku FROM qc_items WHERE job_id=?', [$job['id']]), 'sku'));
    foreach ($rows as $r) {
        $sku = strtoupper(trim((string)($r['sku'] ?? ''))); if ($sku === '') continue;
        if (in_array($sku, $seen, true)) fail("SKU $sku is already in this job.");
        $seen[] = $sku;
        $ready = max(1, (int)($r['ready_qty'] ?? 0)); $order = max($ready, (int)($r['order_qty'] ?? 0));
        $plan = item_plan($job, $ready);
        $iid = insert('qc_items', ['job_id' => $job['id'], 'sku' => $sku, 'product' => (string)($r['product'] ?? 'Jewellery'), 'metal' => (string)($r['metal'] ?? ''),
            'plating' => (string)($r['plating'] ?? ''), 'target_weight' => (string)($r['target_weight'] ?? ''), 'length' => (string)($r['length'] ?? ''), 'lock_type' => (string)($r['lock_type'] ?? ''),
            'stamping' => (string)($r['stamping'] ?? ''), 'style_ref' => (string)($r['style_ref'] ?? ''), 'stone_setting' => (string)($r['stone_setting'] ?? ''), 'instructions' => (string)($r['instructions'] ?? ''),
            'master_sample' => 'Approved Master Sample Available', 'order_qty' => $order, 'ready_qty' => $ready, 'insp_qty' => $plan['insp_qty'], 'plan_json' => json_encode($plan['plan']),
            'status' => 'PENDING', 'updated_at' => now()]);
        if (!empty($r['img']) && ($p = save_data_image((string)$r['img'], 'photos'))) insert('item_photos', ['item_id' => $iid, 'kind' => 'ref', 'path' => $p, 'remark' => 'On-site', 'created_at' => now()]);
        audit('item', $iid, 'CREATE', 'source', null, 'Inspector on-site');
        $ids[] = $iid;
    }
    if (!$ids) fail('Add at least one SKU / design no.');
    return $ids;
}
api('GET', 'app/newjob', ['inspector'], function () {
    if (!flag('inspector_new_job')) fail('Creating jobs on site is switched off. Ask Super Admin.', 403);
    return ['clients' => all("SELECT id, code, name FROM clients WHERE status='ACTIVE' ORDER BY code"),
        'vendors' => all("SELECT m.client_id, v.id, v.name, v.location FROM client_vendor m JOIN vendors v ON v.id=m.vendor_id WHERE v.status='ACTIVE' ORDER BY v.name"),
        'qc_types' => array_column(all('SELECT name FROM qc_types ORDER BY id'), 'name'), 'products' => array_column(all('SELECT name FROM product_categories ORDER BY name'), 'name')];
});
api('POST', 'app/newjob', ['inspector'], function () {
    if (!flag('inspector_new_job')) fail('Creating jobs on site is switched off. Ask Super Admin.', 403);
    $me = user(); $in = inp();
    // next QC lot / re-inspection of an existing SKU line in my job
    if ($lot = (int)($in['lot_of'] ?? 0)) {
        $r = one('SELECT * FROM qc_items WHERE id=?', [$lot]); $job = $r ? job_load((int)$r['job_id']) : null;
        if (!$job || !my_job($job)) fail('SKU not found in your jobs', 404);
        $nid = add_lot($lot, (int)($in['ready_qty'] ?? 0), today());
        notify_managers($job, "QC lot added on site by {$me['emp_code']} · {$r['sku']} · {$job['job_no']}", 'request/' . $job['id']);
        return ['ok' => true, 'job_id' => (int)$job['id'], 'first' => $nid, 'message' => 'New QC lot created — specs copied, enter today\'s quantities.'];
    }
    // add SKUs to one of my open jobs
    if ($jid = (int)($in['job_id'] ?? 0)) {
        $job = job_load($jid); if (!$job || !my_job($job)) fail('Job not found', 404);
        if (!in_array($job['status'], ['ASSIGNED', 'IN_PROGRESS', 'SUBMITTED', 'UNDER_REVIEW', 'RETURNED'], true)) fail('This job is closed for new SKUs.');
        $ids = add_onsite_items($job, (array)($in['items'] ?? []));
        if ($job['status'] !== 'ASSIGNED') refresh_job_status($jid);
        notify_managers($job, count($ids) . " SKU(s) added on site by {$me['emp_code']} · {$job['job_no']} · PO {$job['po_no']}", 'request/' . $jid);
        return ['ok' => true, 'job_id' => $jid, 'first' => $ids[0], 'message' => count($ids) . ' SKU(s) added.'];
    }
    $cid = (int)($in['client_id'] ?? 0); $vid = (int)($in['vendor_id'] ?? 0); $po = trim((string)($in['po_no'] ?? ''));
    if (!$cid || !$vid || $po === '') fail('Select client, vendor and enter PO no.');
    if (!one('SELECT 1 FROM client_vendor WHERE client_id=? AND vendor_id=?', [$cid, $vid])) fail('This vendor is not mapped to the client.');
    $sc = parse_scope((string)($in['scope'] ?? '')) ?? ['scope' => 'BULK', 'bulk_mode' => '100', 'aql_major' => 2.5, 'defined_qty' => 0, 'label' => 'Bulk 100%'];
    $loc = trim((string)($in['location'] ?? '')) ?: explode(',', (string)val('SELECT location FROM vendors WHERE id=?', [$vid]))[0];
    $job = ['req_no' => next_req_no(), 'job_no' => next_job_no(), 'source' => 'Inspector on-site', 'client_id' => $cid, 'vendor_id' => $vid, 'po_no' => $po,
        'qc_type' => (string)($in['qc_type'] ?? 'Pre-shipment'), 'location' => $loc, 'requested_date' => today(), 'scheduled_date' => today(), 'time_slot' => date('h:i A'),
        'scope' => $sc['scope'], 'bulk_mode' => $sc['bulk_mode'], 'insp_level' => 'II', 'aql_critical' => 0, 'aql_major' => $sc['aql_major'], 'aql_minor' => 4.0,
        'defined_qty' => $sc['defined_qty'], 'allow_excess' => 0, 'specs' => trim((string)($in['specs'] ?? '')) ?: 'Created on site by inspector', 'special_req' => trim((string)($in['special_req'] ?? '')),
        'status' => 'ASSIGNED', 'inspector_id' => $me['id'], 'assigned_at' => now(), 'scheduled_at' => now(), 'created_by' => $me['id'], 'created_at' => now()];
    $jid = insert('qc_jobs', $job); $job['id'] = $jid;
    audit('job', $jid, 'CREATE', 'source', null, 'Inspector on-site');
    foreach ((array)($in['files'] ?? []) as $f) if (!empty($f['data']) && ($p = save_data_image((string)$f['data'], 'attachments'))) insert('attachments', ['job_id' => $jid, 'item_id' => null, 'path' => $p, 'name' => mb_substr((string)($f['name'] ?? 'Spec photo'), 0, 120), 'created_at' => now()]);
    $ids = add_onsite_items($job, (array)($in['items'] ?? []));
    $full = job_load($jid);
    notify_managers($full, "On-site QC job {$job['job_no']} created by {$me['emp_code']} · {$full['client_code']} – {$full['vendor_name']} · PO $po", 'request/' . $jid);
    notify_role('back_office', "On-site QC job {$job['job_no']} created by {$me['emp_code']} · PO $po", 'request/' . $jid);
    return ['ok' => true, 'job_id' => $jid, 'first' => $ids[0], 'message' => "Job {$job['job_no']} created — start inspection."];
});
