<?php
require_once ROOT . '/app/workflow.php';
if (!is_post()) redirect('/');
$act = (string)post('act');
$id = (int)post('id');
$backTo = (string)post('back', '');
$done = function (string $msg, string $type = 'ok') use ($backTo, $id) {
    flash($msg, $type);
    if ($backTo && str_starts_with($backTo, '/')) { header('Location: ' . $backTo); exit; }
    redirect('request', ['id' => $id]);
};

/* ---------- item-level actions (client / vendor responses) ---------- */
if (in_array($act, ['hold_respond', 'concession'], true)) {
    $it = one('SELECT * FROM qc_items WHERE id=?', [(int)post('item_id')]);
    $job = $it ? job_load((int)$it['job_id']) : null;
    if (!$job || !can_see_job($job)) { http_response_code(403); exit; }
    $decision = post('decision') === 'APPROVE' ? 'APPROVED' : 'REJECTED';
    $remark = trim((string)post('remark'));
    $who = user()['name'] . ' · ' . (role() === 'client' ? $job['client_name'] : (role() === 'vendor' ? $job['vendor_name'] : 'JEWELSETU'));
    if ($act === 'hold_respond') {
        if ($it['hold_status'] !== 'PENDING' || (role() === 'client' && $it['hold_for'] !== 'client') || (role() === 'vendor' && $it['hold_for'] !== 'vendor')) { flash('This hold is not waiting for you.', 'err'); redirect('approvals'); }
        update('qc_items', ['hold_status' => $decision, 'hold_response' => $remark, 'hold_resp_at' => now(), 'status' => 'IN_PROGRESS',
            'hold_by' => $it['hold_by'] . ' | answered by ' . $who], 'id=?', [$it['id']]);
        audit('item', (int)$it['id'], 'HOLD_' . $decision, 'hold', 'PENDING', $decision . ($remark ? ": $remark" : ''));
        notify((int)$job['inspector_id'], "Hold on {$it['sku']} " . strtolower($decision) . " by " . ucfirst(role()) . ($remark ? " — $remark" : '') . ' · resume inspection', 'app/inspect?item=' . $it['id']);
        notify_role('qc_manager', "SKU hold answered: {$it['sku']} ($decision)", 'request?id=' . $job['id']);
        flash('Your decision was sent to the inspector — inspection can resume.'); redirect('approvals');
    }
    // concession
    if (role() !== 'client' && !is_role('super_admin')) { http_response_code(403); exit; }
    if ($it['concession_status'] !== 'PENDING') { flash('Already decided.', 'warn'); redirect('approvals'); }
    update('qc_items', ['concession_status' => $decision, 'concession_remark' => $remark, 'concession_by' => $who, 'concession_at' => now()], 'id=?', [$it['id']]);
    audit('item', (int)$it['id'], 'CONCESSION_' . $decision, 'Client decision', 'PENDING', $decision);
    item_pdf((int)$it['id'], true); // decision, name & time printed on the report
    if ($job['po_pdf']) job_pdf((int)$job['id'], true);
    notify_role('qc_manager', "Client {$decision} deviation · {$it['report_no']}", 'report?id=' . $it['id']);
    notify_role('vendor', "Client {$decision} deviation on {$it['report_no']}" . ($decision === 'REJECTED' ? ' — rework required' : ''), 'reports', ['vendor_id' => $job['vendor_id']]);
    flash('Decision recorded and printed on the report.'); redirect('approvals');
}

$job = job_load($id);
if (!$job || !can_see_job($job)) { http_response_code(404); exit('Not found'); }

switch ($act) {
    case 'schedule':
        if (!is_role('super_admin', 'back_office')) break;
        $d = ['scheduled_date' => post('date'), 'time_slot' => post('time') ? date('h:i A', strtotime((string)post('time'))) : null, 'priority' => post('priority', 'Normal'),
            'manager_id' => (int)post('manager_id') ?: null, 'location' => post('location', $job['location']), 'scheduled_at' => now()];
        if (!$job['job_no']) $d['job_no'] = next_job_no();
        if (in_array($job['status'], ['REQUESTED', 'DRAFT'], true)) $d['status'] = 'SCHEDULED';
        update('qc_jobs', $d, 'id=?', [$id]); audit('job', $id, 'SCHEDULED', 'scheduled_date', $job['scheduled_date'], $d['scheduled_date']);
        notify_job_parties($job, "QC scheduled · PO {$job['po_no']} on " . fdate($d['scheduled_date']) . ' ' . $d['time_slot'], 'request?id=' . $id);
        if ($d['manager_id']) notify($d['manager_id'], "New QC job to assign · {$job['client_code']} – {$job['vendor_name']}", 'jobs?id=' . $id);
        $done('Scheduled — job ' . ($d['job_no'] ?? $job['job_no']) . ' created; vendor & client notified.');
    case 'assign':
        if (!is_role('super_admin', 'qc_manager', 'back_office')) break;
        if (!in_array($job['status'], ['REQUESTED', 'SCHEDULED', 'ASSIGNED', 'IN_PROGRESS'], true)) $done('Job cannot be (re)assigned in status ' . $job['status'], 'err');
        $ins = (int)post('inspector_id');
        if ($ins && !one("SELECT id FROM users WHERE id=? AND role='inspector'", [$ins])) $done('Invalid inspector', 'err');
        $d = ['inspector_id' => $ins ?: null, 'inspector2_id' => (int)post('inspector2_id') ?: null, 'assigned_at' => now(), 'manager_id' => $job['manager_id'] ?: user()['id']];
        if (post('date')) $d['scheduled_date'] = post('date');
        if (!$job['job_no']) $d['job_no'] = next_job_no();
        if (!$job['scheduled_date'] && !post('date')) $d['scheduled_date'] = $job['requested_date'] ?: today();
        if ($ins && in_array($job['status'], ['REQUESTED', 'SCHEDULED'], true)) $d['status'] = 'ASSIGNED';
        if (!$ins && $job['status'] === 'ASSIGNED') $d['status'] = 'SCHEDULED';
        update('qc_jobs', $d, 'id=?', [$id]);
        audit('job', $id, 'ASSIGNED', 'inspector', $job['inspector_code'], $ins ? val('SELECT emp_code FROM users WHERE id=?', [$ins]) : null);
        if ($ins) {
            $when = fdate($d['scheduled_date'] ?? $job['scheduled_date']);
            notify($ins, "New QC assigned · {$job['client_code']} – {$job['vendor_name']} · $when", 'app/job?id=' . $id);
            if ($d['inspector2_id'] ?? null) notify($d['inspector2_id'], "New QC assigned (split) · {$job['client_code']} – {$job['vendor_name']} · $when", 'app/job?id=' . $id);
            $ip = one('SELECT name, emp_code, phone FROM users WHERE id=?', [$ins]);
            if (post('notify_vendor')) notify_role('vendor', "Inspector {$ip['emp_code']} · {$ip['name']} assigned for PO {$job['po_no']} on $when — please arrange gate entry", 'schedule', ['vendor_id' => $job['vendor_id']]);
        }
        $done($ins ? 'Inspector assigned — notifications sent.' : 'Inspector removed.');
    case 'cancel':
        if (!is_role('super_admin', 'back_office') && !(in_array(role(), ['vendor', 'client'], true) && in_array($job['status'], ['DRAFT', 'REQUESTED'], true) && $job['created_by'] == user()['id'])) break;
        if (!in_array($job['status'], ['DRAFT', 'REQUESTED', 'SCHEDULED', 'ASSIGNED'], true)) $done('Only jobs not yet started can be cancelled.', 'err');
        q("UPDATE qc_jobs SET status='CANCELLED' WHERE id=?", [$id]); audit('job', $id, 'STATUS', 'status', $job['status'], 'CANCELLED');
        if ($job['inspector_id']) notify((int)$job['inspector_id'], "QC job cancelled · {$job['job_no']}", 'app');
        $done('Request cancelled.');
    case 'close':
        if (!is_internal()) break;
        if ($job['status'] !== 'EMAILED') $done('Only reported jobs can be closed.', 'err');
        q("UPDATE qc_jobs SET status='CLOSED', closed_at=? WHERE id=?", [now(), $id]); audit('job', $id, 'STATUS', 'status', 'EMAILED', 'CLOSED');
        $done('Job closed.');
    case 'finalize':
        if (!is_role('super_admin', 'qc_manager')) break;
        refresh_job_status($id); finalize_job_if_ready($id);
        $done('PO report generated and emailed.');
    case 'email_job':
        if (!is_internal()) break;
        [, , $file] = job_pdf($id, true);
        $r = email_report($job, null, $file, (string)post('subject', "QC Inspection Report | {$job['client_code']} | PO {$job['po_no']}"), (string)post('message', 'Please find attached the QC inspection report.'));
        $done('PO report emailed: ' . implode(', ', array_map(fn($k, $v) => "$k $v", array_keys($r), $r)));
    case 'email_item':
        if (!is_internal()) break;
        $it = one('SELECT * FROM qc_items WHERE id=? AND job_id=?', [(int)post('item_id'), $id]);
        if (!$it || !$it['locked']) $done('Report not approved yet.', 'err');
        $file = $it['pdf_path'] && is_file(UPLOAD_DIR . '/' . $it['pdf_path']) ? UPLOAD_DIR . '/' . $it['pdf_path'] : item_pdf((int)$it['id'], true)[2];
        $r = email_report($job, $it, $file, (string)post('subject'), (string)post('message'));
        flash('Report sent: ' . implode(', ', array_map(fn($k, $v) => "$k $v", array_keys($r), $r)));
        redirect('report', ['id' => $it['id']]);
}
http_response_code(403); exit('Not allowed');
