<?php
/* ---------- inspector app (PWA) ---------- */
function my_job(array $job): bool { $me = user(); return (int)$job['inspector_id'] === (int)$me['id'] || (int)$job['inspector2_id'] === (int)$me['id']; }

api('GET', 'app/home', ['inspector'], function () {
    $me = user();
    $jobs = all("SELECT j.id, j.job_no, j.po_no, j.status, j.scheduled_date, j.time_slot, j.location, c.code client_code, v.name vendor_name, (SELECT COUNT(*) FROM qc_items i WHERE i.job_id=j.id) skus,
       (SELECT COUNT(*) FROM qc_items i WHERE i.job_id=j.id AND i.status IN ('SUBMITTED','UNDER_REVIEW','APPROVED')) done,
       (SELECT COUNT(*) FROM qc_items i WHERE i.job_id=j.id AND i.status='RETURNED') ret, (SELECT COUNT(*) FROM qc_items i WHERE i.job_id=j.id AND i.status='HOLD') hold
       FROM qc_jobs j JOIN clients c ON c.id=j.client_id JOIN vendors v ON v.id=j.vendor_id
       WHERE (j.inspector_id=? OR j.inspector2_id=?) AND j.status IN ('ASSIGNED','IN_PROGRESS','SUBMITTED','UNDER_REVIEW','RETURNED')
       ORDER BY CASE WHEN j.status='RETURNED' THEN 0 WHEN j.status='IN_PROGRESS' THEN 1 ELSE 2 END, j.scheduled_date", [$me['id'], $me['id']]);
    $skus = all("SELECT i.id, i.sku, i.product, i.ready_qty, i.status, j.job_no FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id WHERE (j.inspector_id=? OR j.inspector2_id=?) AND j.status IN ('ASSIGNED','IN_PROGRESS','SUBMITTED','UNDER_REVIEW','RETURNED')", [$me['id'], $me['id']]);
    return ['jobs' => $jobs, 'skus' => $skus, 'today' => count(array_filter($jobs, fn($j) => $j['scheduled_date'] === today())),
        'pending' => (int)val("SELECT COUNT(*) FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id WHERE (j.inspector_id=? OR j.inspector2_id=?) AND i.status IN ('PENDING','IN_PROGRESS','HOLD','RETURNED','COMPLETED') AND j.status NOT IN ('CANCELLED','CLOSED')", [$me['id'], $me['id']]),
        'completed' => (int)val("SELECT COUNT(*) FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id WHERE (j.inspector_id=? OR j.inspector2_id=?) AND i.status IN ('SUBMITTED','UNDER_REVIEW','APPROVED')", [$me['id'], $me['id']])];
});
api('GET', 'app/job', ['inspector'], function () {
    $job = job_load((int)in_('id')); if (!$job || !my_job($job)) fail('Job not found', 404);
    $job['scope_label'] = scope_label($job);
    return ['job' => $job, 'items' => all('SELECT id, sku, product, ready_qty, insp_qty, order_qty, status FROM qc_items WHERE job_id=? ORDER BY id', [$job['id']]),
        'attachments' => array_map(fn($a) => $a + ['url' => url('file', ['p' => $a['path']])], all('SELECT * FROM attachments WHERE job_id=?', [$job['id']]))];
});
api('GET', 'app/inspect', ['inspector'], function () {
    $it = one('SELECT * FROM qc_items WHERE id=?', [(int)in_('item')]); $job = $it ? job_load((int)$it['job_id']) : null;
    if (!$job || !my_job($job)) fail('SKU not found in your jobs', 404);
    $job['scope_label'] = scope_label($job);
    $editable = in_array($it['status'], ['PENDING', 'IN_PROGRESS', 'COMPLETED', 'RETURNED'], true) && in_array($job['status'], ['ASSIGNED', 'IN_PROGRESS', 'SUBMITTED', 'UNDER_REVIEW', 'RETURNED'], true);
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
    return ['item' => $it, 'job' => $job, 'editable' => $editable, 'checks' => $checks, 'tests' => $tests, 'meas' => $meas, 'remarks' => $remarks,
        'defects' => all('SELECT category, type, severity, qty, remark FROM item_defects WHERE item_id=?', [$it['id']]),
        'photos' => array_map(fn($p) => ['id' => (int)$p['id'], 'kind' => $p['kind'], 'remark' => $p['remark'], 'url' => url('file', ['p' => $p['path']])], all('SELECT * FROM item_photos WHERE item_id=? ORDER BY id', [$it['id']])),
        'dm' => $dm, 'plan' => jdec($it['plan_json']), 'steps' => array_values(STEPS), 'threshold' => client_threshold((int)$job['client_id']),
        'aql' => $job['scope'] === 'BULK' && $job['bulk_mode'] === 'AQL', 'locked_qty' => $job['scope'] !== 'SAMPLE',
        'need_photo' => flag('require_defect_photo'), 'gps' => flag('gps_on_inspection_start'),
        'attachments' => array_map(fn($a) => $a + ['url' => url('file', ['p' => $a['path']])], all('SELECT * FROM attachments WHERE job_id=? AND (item_id IS NULL OR item_id=?)', [$job['id'], $it['id']])),
        'siblings' => all('SELECT id, sku, status FROM qc_items WHERE job_id=? ORDER BY id', [$job['id']])];
});
api('GET', 'app/history', ['inspector'], function () {
    $me = user(); $f = (string)in_('f', 'ALL'); $map = ['SUBMITTED' => ['SUBMITTED', 'UNDER_REVIEW'], 'RETURNED' => ['RETURNED'], 'APPROVED' => ['APPROVED']];
    $sql = "SELECT i.id, i.sku, i.status, i.report_no, i.approved_at, i.submitted_at, j.job_no, v.name vendor_name, c.code client_code FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id JOIN vendors v ON v.id=j.vendor_id JOIN clients c ON c.id=j.client_id
        WHERE (j.inspector_id=? OR j.inspector2_id=?) AND i.status IN ('SUBMITTED','UNDER_REVIEW','RETURNED','APPROVED')";
    if (isset($map[$f])) $sql .= " AND i.status IN ('" . implode("','", $map[$f]) . "')";
    return ['rows' => all($sql . ' ORDER BY COALESCE(i.approved_at, i.submitted_at) DESC LIMIT 100', [$me['id'], $me['id']])];
});

api('POST', 'app/inspect', ['inspector'], function () {
$in = inp();
$act = (string)($in['action'] ?? '');
$it = one('SELECT * FROM qc_items WHERE id=?', [(int)($in['item_id'] ?? 0)]);
if (!$it) fail('Item not found', 404);
$job = job_load((int)$it['job_id']);
$me = user();
if (!my_job($job)) fail('Not your job', 403);
if (!in_array($job['status'], ['ASSIGNED', 'IN_PROGRESS', 'SUBMITTED', 'UNDER_REVIEW', 'RETURNED'], true)) fail('Job is not open for inspection (' . $job['status'] . ')', 409);
$editable = in_array($it['status'], ['PENDING', 'IN_PROGRESS', 'COMPLETED', 'RETURNED'], true);

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
    if ($s === 1) { $up['reinspection'] = !empty($D['reinspection']) ? 1 : 0; if (!empty($D['insp_date'])) $up['insp_date'] = $D['insp_date']; if (!empty($D['gps'])) $up['gps'] = mb_substr((string)$D['gps'], 0, 120);
        foreach (['lot_no', 'xrf_machine', 'master_sample'] as $k) if (isset($D[$k])) $up[$k] = mb_substr(trim((string)$D[$k]), 0, 120); }
    if ($s === 2) {
        $insp = (int)($D['inspected_qty'] ?? 0); $acc = (int)($D['accepted_qty'] ?? 0); $rej = (int)($D['rejected_qty'] ?? 0);
        $locked = !($job['scope'] === 'SAMPLE');
        if ($locked) $insp = (int)$it['insp_qty'];
        if ($insp <= 0) json_out(['error' => 'Inspection quantity must be more than 0'], 422);
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
        foreach (['factory_coop', 'factory_line', 'factory_ready'] as $k) $up[$k] = in_array($D[$k] ?? '', ['GOOD', 'AVERAGE', 'POOR'], true) ? $D[$k] : null; }
    update('qc_items', $up, 'id=?', [$it['id']]);
    $fresh = one('SELECT * FROM qc_items WHERE id=?', [$it['id']]);
    $sys = system_result($job + ['client_fail_threshold' => client_threshold((int)$job['client_id'])], $fresh, all('SELECT * FROM item_defects WHERE item_id=?', [$it['id']]), jdec($fresh['checks_json']), jdec($fresh['meas_json']), jdec($fresh['tests_json']));
    q('UPDATE qc_items SET system_result=? WHERE id=?', [$sys, $it['id']]);
    json_out(['ok' => true, 'system_result' => $sys, 'saved_at' => date('h:i A')]);

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
    if ($ph) { @unlink(UPLOAD_DIR . '/' . $ph['path']); q('DELETE FROM item_photos WHERE id=?', [$ph['id']]); }
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
    notify_role('qc_manager', "Inspector put {$it['sku']} on hold for $for approval", 'request/' . $job['id']);
    json_out(['ok' => true]);

case 'submit':
    if (!$editable) json_out(['error' => 'Already submitted'], 409);
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
    $wasReturned = $f['status'] === 'RETURNED';
    update('qc_items', ['status' => 'SUBMITTED', 'submitted_at' => now(), 'return_sections' => null], 'id=?', [$it['id']]);
    audit('item', (int)$it['id'], $wasReturned ? 'RESUBMITTED' : 'SUBMITTED', 'status', $f['status'], 'SUBMITTED');
    refresh_job_status((int)$job['id']);
    if (!val('SELECT submitted_at FROM qc_jobs WHERE id=?', [$job['id']])) q('UPDATE qc_jobs SET submitted_at=? WHERE id=?', [now(), $job['id']]);
    $mgr = $job['manager_id'] ? [(int)$job['manager_id']] : array_column(all("SELECT id FROM users WHERE role='qc_manager' AND status='ACTIVE'"), 'id');
    foreach ($mgr as $m) notify($m, ($wasReturned ? 'Resubmitted' : 'Submitted') . " for review · {$it['sku']} · {$job['job_no']} ({$me['emp_code']})", 'review/' . $it['id']);
    $next = val("SELECT id FROM qc_items WHERE job_id=? AND status IN ('PENDING','IN_PROGRESS','RETURNED','COMPLETED') AND id<>? ORDER BY id LIMIT 1", [$job['id'], $it['id']]);
    json_out(['ok' => true, 'next' => $next ? (int)$next : null]);
}
fail('Unknown action', 400);

});
