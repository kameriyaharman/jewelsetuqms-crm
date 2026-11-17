<?php
/** JSON API for the inspector app (also replays offline-queued saves). */
if (!is_post()) json_out(['error' => 'POST only'], 405);
$in = jdec(file_get_contents('php://input'));
$tok = $_SERVER['HTTP_X_CSRF'] ?? '';
if (!hash_equals(csrf_token(), (string)$tok)) json_out(['error' => 'Session expired — please log in again.'], 419);
$act = (string)($in['action'] ?? '');
$it = one('SELECT * FROM qc_items WHERE id=?', [(int)($in['item_id'] ?? 0)]);
if (!$it) json_out(['error' => 'Item not found'], 404);
$job = job_load((int)$it['job_id']);
$me = user();
if ((int)$job['inspector_id'] !== (int)$me['id'] && (int)$job['inspector2_id'] !== (int)$me['id']) json_out(['error' => 'Not your job'], 403);
if (!in_array($job['status'], ['ASSIGNED', 'IN_PROGRESS', 'SUBMITTED', 'UNDER_REVIEW', 'RETURNED'], true)) json_out(['error' => 'Job is not open for inspection (' . $job['status'] . ')'], 409);
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
    if ($s === 1) { $up['reinspection'] = !empty($D['reinspection']) ? 1 : 0; if (!empty($D['insp_date'])) $up['insp_date'] = $D['insp_date']; if (!empty($D['gps'])) $up['gps'] = mb_substr((string)$D['gps'], 0, 120); }
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
            $m[] = ['param' => (string)$x['param'], 'spec' => (string)$x['spec'], 'tol' => (string)$x['tol'], 'unit' => (string)($x['unit'] ?? ''), 'actual' => $act2, 'result' => $res];
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
    if ($s === 8) { $up['inspector_result'] = in_array($D['result'] ?? '', ['PASS', 'FAIL', 'PENDING', 'CONDITIONAL'], true) ? $D['result'] : null; $up['remark'] = (string)($D['remark'] ?? ''); $up['action_required'] = (string)($D['action_required'] ?? ''); }
    update('qc_items', $up, 'id=?', [$it['id']]);
    $fresh = one('SELECT * FROM qc_items WHERE id=?', [$it['id']]);
    $sys = system_result($job + ['client_major_limit' => val('SELECT major_limit FROM clients WHERE id=?', [$job['client_id']])], $fresh, all('SELECT * FROM item_defects WHERE item_id=?', [$it['id']]), jdec($fresh['checks_json']), jdec($fresh['meas_json']), jdec($fresh['tests_json']));
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
    $pid = insert('item_photos', ['item_id' => $it['id'], 'kind' => in_array($in['kind'] ?? '', ['defect', 'product', 'dimension', 'packaging', 'spec', 'other'], true) ? $in['kind'] : 'other',
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
    notify_role('qc_manager', "Inspector put {$it['sku']} on hold for $for approval", 'request?id=' . $job['id']);
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
    foreach ($mgr as $m) notify($m, ($wasReturned ? 'Resubmitted' : 'Submitted') . " for review · {$it['sku']} · {$job['job_no']} ({$me['emp_code']})", 'review-item?id=' . $it['id']);
    $next = val("SELECT id FROM qc_items WHERE job_id=? AND status IN ('PENDING','IN_PROGRESS','RETURNED','COMPLETED') AND id<>? ORDER BY id LIMIT 1", [$job['id'], $it['id']]);
    json_out(['ok' => true, 'next' => $next ? (int)$next : null]);
}
json_out(['error' => 'Unknown action'], 400);
