<?php
/* ---------- QC requests, request form, job actions, Excel import ---------- */

api('GET', 'requests', [...R_INT, 'client', 'vendor'], function () {
    [$sc, $sp] = job_scope('j');
    $st = (string)in_('st', 'ALL'); $src = (string)in_('src'); $cf = (int)in_('client'); $vf = (int)in_('vendor'); $qs = trim((string)in_('q'));
    $base = "FROM qc_jobs j JOIN clients c ON c.id=j.client_id JOIN vendors v ON v.id=j.vendor_id WHERE $sc"; $p = $sp;
    if (in_array(role(), ['client', 'vendor'], true)) { $base .= " AND (j.status<>'DRAFT' OR j.created_by=?)"; $p[] = user()['id']; }
    $counts = []; foreach (all("SELECT j.status, COUNT(*) n $base GROUP BY j.status", $p) as $r) $counts[$r['status']] = (int)$r['n'];
    $w = $base;
    if ($st !== 'ALL') { $w .= ' AND j.status=?'; $p[] = $st; }
    if ($src) { $w .= ' AND j.source=?'; $p[] = $src; }
    if ($cf) { $w .= ' AND j.client_id=?'; $p[] = $cf; }
    if ($vf) { $w .= ' AND j.vendor_id=?'; $p[] = $vf; }
    if ($qs) { $w .= ' AND (j.po_no LIKE ? OR j.req_no LIKE ? OR j.job_no LIKE ? OR j.id IN (SELECT job_id FROM qc_items WHERE sku LIKE ?))'; array_push($p, "%$qs%", "%$qs%", "%$qs%", "%$qs%"); }
    $rows = all("SELECT j.*, " . img_job_sql() . " img, c.code client_code, v.name vendor_name, (SELECT COUNT(*) FROM qc_items i WHERE i.job_id=j.id) skus,
        (SELECT SUM(ready_qty) FROM qc_items i WHERE i.job_id=j.id) qty $w ORDER BY CASE j.status WHEN 'REQUESTED' THEN 0 WHEN 'DRAFT' THEN 1 ELSE 2 END, j.id DESC LIMIT 300", $p);
    foreach ($rows as &$r) $r['scope_label'] = scope_label($r);
    return ['rows' => $rows, 'counts' => $counts, 'total' => array_sum($counts)];
});

api('GET', 'request', [...R_INT, 'client', 'vendor'], function () {
    $job = job_load((int)in_('id'));
    if (!$job || !can_see_job($job)) fail('Request not found', 404);
    $job['scope_label'] = scope_label($job);
    $items = all('SELECT i.*, ' . img_item_sql() . ' img FROM qc_items i WHERE i.job_id=? ORDER BY COALESCE(i.parent_id, i.id), COALESCE(i.lot_seq, 1), i.id', [$job['id']]);
    $openJob = !in_array($job['status'], ['CLOSED', 'CANCELLED'], true);
    foreach ($items as &$i) {
        if (!$i['parent_id']) $i['line'] = line_stats($job, (int)$i['id']);
        $e = one("SELECT a.created_at, u.name FROM audit_log a LEFT JOIN users u ON u.id=a.user_id WHERE a.entity='item' AND a.entity_id=? AND a.action IN ('EDIT','ADMIN_EDIT','UPDATE') ORDER BY a.id DESC LIMIT 1", [$i['id']]);
        $i['edited'] = $e ? ['by' => $e['name'] ?: 'System', 'at' => $e['created_at']] : null;
        $i['scope_label'] = $i['i_scope'] ? scope_label(item_job($job, $i)) : null;
        $i['can_remove'] = $openJob && $i['status'] === 'PENDING' && !$i['started_at'] && !(int)$i['locked'];
    }
    unset($i);
    $ids = array_map('intval', array_column($items, 'id'));
    $log = all('SELECT a.*, u.name uname FROM audit_log a LEFT JOIN users u ON u.id=a.user_id WHERE (a.entity=? AND a.entity_id=?) ' .
        ($ids ? "OR (a.entity='item' AND a.entity_id IN (" . implode(',', $ids) . '))' : '') . ' ORDER BY a.id DESC LIMIT 40', ['job', $job['id']]);
    $r = role(); $cv = in_array($r, ['client', 'vendor'], true);
    $mine = $job['created_by'] == user()['id'];
    $can = [
        'edit' => (in_array($job['status'], ['DRAFT', 'REQUESTED'], true) && (is_role('super_admin', 'back_office') || ($cv && $mine))) || (is_role('super_admin', 'back_office') && $job['status'] === 'SCHEDULED'),
        'schedule' => is_role('super_admin', 'back_office') && in_array($job['status'], ['REQUESTED', 'DRAFT', 'SCHEDULED'], true),
        'assign' => is_role('super_admin', 'qc_manager', 'back_office') && in_array($job['status'], ['REQUESTED', 'SCHEDULED', 'ASSIGNED', 'IN_PROGRESS'], true),
        'cancel' => (is_role('super_admin', 'back_office') && in_array($job['status'], ['DRAFT', 'REQUESTED', 'SCHEDULED', 'ASSIGNED'], true)) || ($cv && $mine && in_array($job['status'], ['DRAFT', 'REQUESTED'], true)),
        'report_actions' => is_internal() && in_array($job['status'], ['EMAILED', 'APPROVED'], true),
        'finalize' => is_role('super_admin', 'qc_manager') && $job['status'] === 'APPROVED', 'close' => is_internal() && $job['status'] === 'EMAILED',
        'po_pdf' => in_array($job['status'], ['EMAILED', 'CLOSED', 'APPROVED'], true), 'review' => is_role('super_admin', 'qc_manager'), 'split' => flag('allow_job_split') && count($items) > 1,
        'edit_design' => is_role('super_admin', 'back_office', 'qc_manager') && $openJob, 'unassign' => is_internal() && $job['inspector_id'] && unassign_block($job) === null, 'add_lot' => is_internal() && !in_array($job['status'], ['CLOSED', 'CANCELLED', 'EMAILED'], true),
        'force_close' => is_role('super_admin', 'qc_manager') && !in_array($job['status'], ['CLOSED', 'CANCELLED', 'DRAFT', 'REQUESTED'], true)];
    // hide unpublished results from clients/vendors
    if ($cv) foreach ($items as &$i) if ($i['status'] !== 'APPROVED') { $i['final_result'] = null; $i['system_result'] = null; }
    unset($i);
    return ['job' => $job, 'items' => $items, 'attachments' => all('SELECT * FROM attachments WHERE job_id=? ORDER BY id', [$job['id']]), 'log' => $log,
        'emails' => $cv ? [] : all('SELECT * FROM email_log WHERE job_id=? ORDER BY id DESC LIMIT 20', [$job['id']]), 'can' => $can,
        'checkins' => all('SELECT c.at, c.out_at, c.verified, c.dist_m, u.emp_code, u.name FROM checkins c JOIN users u ON u.id=c.user_id WHERE c.job_id=? ORDER BY c.at DESC', [$job['id']])];
});

/* ----- request form ----- */
function req_allowed(): void {
    if (role() === 'vendor' && !flag('enable_vendor_qc_request')) fail('Self-service QC requests are not enabled yet. Please contact JEWELSETU.', 403);
    if (role() === 'client' && !flag('enable_client_qc_request')) fail('Self-service QC requests are not enabled yet. Please contact JEWELSETU.', 403);
}
api('GET', 'request/form', ['super_admin', 'back_office', 'client', 'vendor'], function () {
    req_allowed();
    $job = null; $items = []; $atts = [];
    if ($id = (int)in_('id')) {
        $job = job_load($id);
        if (!$job || !can_see_job($job) || !in_array($job['status'], ['DRAFT', 'REQUESTED', 'SCHEDULED'], true) || (in_array(role(), ['client', 'vendor'], true) && !in_array($job['status'], ['DRAFT', 'REQUESTED'], true))) fail('This request can no longer be edited.', 409);
        $items = all('SELECT i.*, ' . img_item_sql() . ' img FROM qc_items i WHERE i.job_id=? ORDER BY i.id', [$id]); $atts = all('SELECT * FROM attachments WHERE job_id=?', [$id]);
    }
    return ['job' => $job, 'items' => $items, 'attachments' => $atts, 'next_req_no' => next_req_no()];
});
api('POST', 'request/save', ['super_admin', 'back_office', 'client', 'vendor'], function () {
    req_allowed();
    $u = user(); $r = $u['role'];
    $D = jdec((string)in_('data'));     // JSON payload (multipart for files)
    $job = null;
    if ($id = (int)($D['id'] ?? 0)) {
        $job = job_load($id);
        if (!$job || !can_see_job($job) || !in_array($job['status'], ['DRAFT', 'REQUESTED', 'SCHEDULED'], true)) fail('This request can no longer be edited.', 409);
    }
    $cid = $r === 'client' ? (int)$u['client_id'] : (int)($D['client_id'] ?? 0);
    $vid = $r === 'vendor' ? (int)$u['vendor_id'] : (int)($D['vendor_id'] ?? 0);
    $err = [];
    if (!one('SELECT 1 FROM client_vendor WHERE client_id=? AND vendor_id=?', [$cid, $vid])) $err[] = 'Selected vendor is not mapped to this client.';
    if (!trim((string)($D['po_no'] ?? ''))) $err[] = 'PO number is required.';
    $scope = ($D['scope'] ?? '') === 'SAMPLE' ? 'SAMPLE' : 'BULK';
    $mode = in_array($D['bulk_mode'] ?? '', ['100', 'AQL', 'DEFINED'], true) ? $D['bulk_mode'] : '100';
    $excess = !empty($D['allow_excess']) ? 1 : 0; $defined = (int)($D['defined_qty'] ?? 0);
    $rows = [];
    foreach ((array)($D['items'] ?? []) as $k => $it) {
        $sku = strtoupper(trim((string)($it['sku'] ?? ''))); if ($sku === '') continue;
        $oq = (int)($it['order_qty'] ?? 0); $rq = (int)($it['ready_qty'] ?? 0);
        if ($rq > $oq && !$excess) $err[] = "SKU $sku: Ready qty ($rq) is more than Order qty ($oq). Tick “Allow excess quantity” if intended.";
        if ($rq <= 0) $err[] = "SKU $sku: Ready qty must be more than 0.";
        if ($scope === 'BULK' && $mode === 'DEFINED' && $defined > $rq) $err[] = "SKU $sku: Defined sample qty is more than Ready qty.";
        $row = ['k' => $it['key'] ?? $k, 'id' => (int)($it['id'] ?? 0), 'sku' => $sku, 'order_qty' => $oq, 'ready_qty' => $rq];
        foreach (['product', 'style_ref', 'metal', 'plating', 'stone_setting', 'target_weight', 'length', 'lock_type', 'stamping'] as $f) $row[$f] = trim((string)($it[$f] ?? ''));
        $rows[] = $row;
    }
    if (!$rows) $err[] = 'Add at least one SKU / design.';
    $skus = array_column($rows, 'sku'); if (count($skus) !== count(array_unique($skus))) $err[] = 'Duplicate SKU in this request.';
    if ($err) json_out(['error' => implode("\n", $err), 'errors' => $err], 422);
    $submit = ($D['action'] ?? '') === 'submit';
    $data = ['client_id' => $cid, 'vendor_id' => $vid, 'po_no' => trim((string)$D['po_no']), 'qc_type' => $D['qc_type'] ?? 'Pre-shipment', 'location' => $D['location'] ?? '',
        'requested_date' => ($D['requested_date'] ?? '') ?: null, 'scope' => $scope, 'bulk_mode' => $mode, 'insp_level' => $D['insp_level'] ?? 'II',
        'aql_critical' => (float)($D['aql_critical'] ?? 0), 'aql_major' => (float)($D['aql_major'] ?? 2.5), 'aql_minor' => (float)($D['aql_minor'] ?? 4.0),
        'defined_qty' => $defined, 'allow_excess' => $excess, 'specs' => $D['specs'] ?? '', 'special_req' => $D['special_req'] ?? ''];
    if ($job) { if ($job['status'] === 'DRAFT' && $submit) $data['status'] = 'REQUESTED'; update('qc_jobs', $data, 'id=?', [$job['id']]); $jid = (int)$job['id']; audit('job', $jid, 'UPDATE'); }
    else {
        $data += ['req_no' => next_req_no(), 'source' => match ($r) { 'vendor' => 'Vendor portal', 'client' => 'Client portal', default => 'Back Office' },
            'status' => $submit ? 'REQUESTED' : 'DRAFT', 'created_by' => $u['id'], 'created_at' => now()];
        $jid = insert('qc_jobs', $data); audit('job', $jid, 'CREATE', 'status', null, $data['status']);
    }
    $jobRow = one('SELECT * FROM qc_jobs WHERE id=?', [$jid]); $keep = [];
    foreach ($rows as $row) {
        $plan = item_plan($jobRow, $row['ready_qty']);
        $d = array_diff_key($row, ['k' => 1, 'id' => 1]) + ['insp_qty' => $plan['insp_qty'], 'plan_json' => json_encode($plan['plan']), 'updated_at' => now()];
        if ($row['id'] && one('SELECT id FROM qc_items WHERE id=? AND job_id=?', [$row['id'], $jid])) { update('qc_items', $d, 'id=?', [$row['id']]); $iid = $row['id']; }
        else $iid = insert('qc_items', $d + ['job_id' => $jid, 'status' => 'PENDING', 'master_sample' => 'Approved Master Sample Available']);
        $keep[] = $iid;
        $fk = 'row_file_' . preg_replace('/[^A-Za-z0-9_]/', '', (string)$row['k']);
        if (!empty($_FILES[$fk]) && ($s = save_upload($_FILES[$fk], 'specs'))) insert('attachments', ['job_id' => $jid, 'item_id' => $iid, 'path' => $s['path'], 'name' => $s['name'], 'created_at' => now()]);
    }
    q('DELETE FROM qc_items WHERE job_id=? AND id NOT IN (' . implode(',', array_map('intval', $keep)) . ')', [$jid]);
    foreach (files_array('files') as $f) if ($s = save_upload($f, 'specs')) insert('attachments', ['job_id' => $jid, 'item_id' => null, 'path' => $s['path'], 'name' => $s['name'], 'created_at' => now()]);
    $J = job_load($jid);
    if ($submit) {
        notify_role('back_office', "New QC request {$J['req_no']} · {$J['client_code']} – {$J['vendor_name']} · PO {$J['po_no']}", 'request/' . $jid);
        notify_role('super_admin', "New QC request {$J['req_no']} from {$J['source']}", 'request/' . $jid);
        if ($r !== 'client') notify_role('client', "{$J['vendor_name']} QC request {$J['req_no']} · PO {$J['po_no']}", 'request/' . $jid, ['client_id' => $cid]);
        if ($r !== 'vendor') notify_role('vendor', "QC request {$J['req_no']} raised for PO {$J['po_no']}", 'request/' . $jid, ['vendor_id' => $vid]);
        return ['ok' => true, 'id' => $jid, 'message' => "QC request {$J['req_no']} submitted — status REQUESTED."];
    }
    return ['ok' => true, 'id' => $jid, 'message' => "Draft {$J['req_no']} saved."];
});

/* ----- job workflow actions ----- */
api('POST', 'job/action', R_ALL, function () {
    require_once ROOT . '/app/workflow.php';
    $act = (string)in_('act');
    if (in_array($act, ['hold_respond', 'concession'], true)) {
        $it = one('SELECT * FROM qc_items WHERE id=?', [(int)in_('item_id')]);
        $job = $it ? job_load((int)$it['job_id']) : null;
        if (!$job || !can_see_job($job)) fail('Forbidden', 403);
        $decision = in_('decision') === 'APPROVE' ? 'APPROVED' : 'REJECTED'; $remark = trim((string)in_('remark'));
        $who = user()['name'] . ' · ' . (role() === 'client' ? $job['client_name'] : (role() === 'vendor' ? $job['vendor_name'] : 'JEWELSETU'));
        if ($act === 'hold_respond') {
            if ($it['hold_status'] !== 'PENDING' || (role() === 'client' && $it['hold_for'] !== 'client') || (role() === 'vendor' && $it['hold_for'] !== 'vendor') || !in_array(role(), ['client', 'vendor', 'super_admin'], true)) fail('This hold is not waiting for you.');
            update('qc_items', ['hold_status' => $decision, 'hold_response' => $remark, 'hold_resp_at' => now(), 'status' => 'IN_PROGRESS', 'hold_by' => $it['hold_by'] . ' | answered by ' . $who], 'id=?', [$it['id']]);
            audit('item', (int)$it['id'], 'HOLD_' . $decision, 'hold', 'PENDING', $decision . ($remark ? ": $remark" : ''));
            notify((int)$job['inspector_id'], "Hold on {$it['sku']} " . strtolower($decision) . ' by ' . ucfirst(role()) . ($remark ? " — $remark" : '') . ' · resume inspection', 'app/inspect/' . $it['id']);
            notify_managers($job, "SKU hold answered: {$it['sku']} ($decision)", 'request/' . $job['id']);
            return ['ok' => true, 'message' => 'Your decision was sent to the inspector — inspection can resume.'];
        }
        if (role() !== 'client' && !is_role('super_admin')) fail('Forbidden', 403);
        if ($it['concession_status'] !== 'PENDING') fail('Already decided.');
        update('qc_items', ['concession_status' => $decision, 'concession_remark' => $remark, 'concession_by' => $who, 'concession_at' => now()], 'id=?', [$it['id']]);
        audit('item', (int)$it['id'], 'CONCESSION_' . $decision, 'Client decision', 'PENDING', $decision);
        item_pdf((int)$it['id'], true); if ($job['po_pdf']) job_pdf((int)$job['id'], true);
        notify_managers($job, "Client {$decision} deviation · {$it['report_no']}", 'report/' . $it['id']);
        notify_role('vendor', "Client {$decision} deviation on {$it['report_no']}" . ($decision === 'REJECTED' ? ' — rework required' : ''), 'reports', ['vendor_id' => $job['vendor_id']]);
        return ['ok' => true, 'message' => 'Decision recorded and printed on the report.'];
    }
    $id = (int)in_('id'); $job = job_load($id);
    if (!$job || !can_see_job($job)) fail('Not found', 404);
    switch ($act) {
        case 'schedule':
            if (!is_role('super_admin', 'back_office')) break;
            if (!in_('date')) fail('Choose the confirmed QC date.');
            $d = ['scheduled_date' => in_('date'), 'time_slot' => in_('time') ? date('h:i A', strtotime((string)in_('time'))) : null, 'priority' => in_('priority', 'Normal'),
                'manager_id' => mgr_on() ? ((int)in_('manager_id') ?: ((int)val('SELECT qc_manager_id FROM clients WHERE id=?', [$job['client_id']]) ?: null)) : null, 'location' => in_('location', $job['location']), 'scheduled_at' => now()];
            if (!$job['job_no']) $d['job_no'] = next_job_no();
            if (in_array($job['status'], ['REQUESTED', 'DRAFT'], true)) $d['status'] = 'SCHEDULED';
            update('qc_jobs', $d, 'id=?', [$id]); audit('job', $id, 'SCHEDULED', 'scheduled_date', $job['scheduled_date'], $d['scheduled_date']);
            notify_job_parties($job, "QC scheduled · PO {$job['po_no']} on " . fdate($d['scheduled_date']) . ' ' . $d['time_slot'], 'request/' . $id);
            notify_managers(['manager_id' => $d['manager_id']] + $job, "New QC job to assign · {$job['client_code']} – {$job['vendor_name']}", 'jobs');
            return ['ok' => true, 'message' => 'Scheduled — job ' . ($d['job_no'] ?? $job['job_no']) . ' created; vendor & client notified.'];
        case 'assign':
            if (!is_role('super_admin', 'qc_manager', 'back_office')) break;
            if (!in_array($job['status'], ['REQUESTED', 'SCHEDULED', 'ASSIGNED', 'IN_PROGRESS'], true)) fail('Job cannot be (re)assigned in status ' . $job['status']);
            $ins = (int)in_('inspector_id');
            if (!$ins) {   // -- Unassigned (move to pending pool) --
                if (!$job['inspector_id']) return ['ok' => true, 'message' => 'Job is already unassigned.'];
                if ($why = unassign_block($job, (bool)in_('force'))) fail($why);
                unassign_job($job);
                return ['ok' => true, 'message' => "{$job['job_no']} moved to the Unassigned pool — removed from the inspector's app."];
            }
            if (!one("SELECT id FROM users WHERE id=? AND role='inspector'", [$ins])) fail('Invalid inspector');
            if ($lv = on_leave($ins, (string)(in_('date') ?: ($job['scheduled_date'] ?: today())))) fail("This inspector is on leave that day ($lv).");
            $d = ['inspector_id' => $ins ?: null, 'inspector2_id' => (int)in_('inspector2_id') ?: null, 'assigned_at' => now(), 'manager_id' => $job['manager_id'] ?: user()['id']];
            if (in_('date')) $d['scheduled_date'] = in_('date');
            if (!$job['job_no']) $d['job_no'] = next_job_no();
            if (!$job['scheduled_date'] && !in_('date')) $d['scheduled_date'] = $job['requested_date'] ?: today();
            if ($ins && in_array($job['status'], ['REQUESTED', 'SCHEDULED'], true)) $d['status'] = 'ASSIGNED';
            if (!$ins && $job['status'] === 'ASSIGNED') $d['status'] = 'SCHEDULED';
            update('qc_jobs', $d, 'id=?', [$id]);
            audit('job', $id, 'ASSIGNED', 'inspector', $job['inspector_code'], $ins ? val('SELECT emp_code FROM users WHERE id=?', [$ins]) : null);
            if ($ins) {
                $when = fdate($d['scheduled_date'] ?? $job['scheduled_date']);
                notify($ins, "New QC assigned · {$job['client_code']} – {$job['vendor_name']} · $when", 'app/job/' . $id);
                if (!empty($d['inspector2_id'])) notify($d['inspector2_id'], "New QC assigned (split) · {$job['client_code']} – {$job['vendor_name']} · $when", 'app/job/' . $id);
                $ip = one('SELECT name, emp_code FROM users WHERE id=?', [$ins]);
                if (in_('notify_vendor')) notify_role('vendor', "Inspector {$ip['emp_code']} · {$ip['name']} assigned for PO {$job['po_no']} on $when — please arrange gate entry", 'schedule', ['vendor_id' => $job['vendor_id']]);
            }
            return ['ok' => true, 'message' => $ins ? 'Inspector assigned — notifications sent.' : 'Inspector removed.'];
        case 'cancel':
            $cv = in_array(role(), ['vendor', 'client'], true) && in_array($job['status'], ['DRAFT', 'REQUESTED'], true) && $job['created_by'] == user()['id'];
            if (!is_role('super_admin', 'back_office') && !$cv) break;
            if (!in_array($job['status'], ['DRAFT', 'REQUESTED', 'SCHEDULED', 'ASSIGNED'], true)) fail('Only jobs not yet started can be cancelled.');
            q("UPDATE qc_jobs SET status='CANCELLED' WHERE id=?", [$id]); audit('job', $id, 'STATUS', 'status', $job['status'], 'CANCELLED');
            if ($job['inspector_id']) notify((int)$job['inspector_id'], "QC job cancelled · {$job['job_no']}", 'app');
            return ['ok' => true, 'message' => 'Request cancelled.'];
        case 'close':
            if (!is_internal()) break;
            if ($job['status'] !== 'EMAILED') fail('Only reported jobs can be closed.');
            q("UPDATE qc_jobs SET status='CLOSED', closed_at=? WHERE id=?", [now(), $id]); audit('job', $id, 'STATUS', 'status', 'EMAILED', 'CLOSED');
            return ['ok' => true, 'message' => 'Job closed.'];
        case 'add_lot':
            if (!is_internal()) break;
            $nid = add_lot((int)in_('item_id'), (int)in_('ready_qty'), in_('date') ?: null);
            if ((int)in_('inspector_id') && is_role('super_admin', 'qc_manager', 'back_office')) { $ins = (int)in_('inspector_id'); q('UPDATE qc_jobs SET inspector_id=? WHERE id=?', [$ins, $id]); notify($ins, "QC lot added for you · {$job['job_no']} · PO {$job['po_no']}", 'app/job/' . $id); }
            return ['ok' => true, 'item_id' => $nid, 'message' => 'Next QC lot added — job is open for inspection again.'];
        case 'force_close':
            if (!is_role('super_admin', 'qc_manager')) break;
            $note = trim((string)in_('note')); if ($note === '') fail('Enter a reason (e.g. client accepted short shipment).');
            if (in_array($job['status'], ['CLOSED', 'CANCELLED'], true)) fail('Job is already closed.');
            $only = (int)in_('item_id'); $items = all('SELECT * FROM qc_items WHERE job_id=?', [$id]);
            $roots = array_values(array_filter($items, fn($i) => !$i['parent_id'] && (!$only || (int)$i['id'] === $only || (int)$i['id'] === (int)val('SELECT parent_id FROM qc_items WHERE id=?', [$only]))));
            $rootIds = array_map(fn($r) => (int)$r['id'], $roots);
            foreach ($items as $i) {
                if (!in_array(line_root_id($i), $rootIds, true) || $i['status'] === 'APPROVED') continue;
                if ($i['parent_id'] && $i['status'] === 'PENDING' && !$i['started_at']) { q('DELETE FROM qc_items WHERE id=?', [$i['id']]); q('DELETE FROM item_photos WHERE item_id=?', [$i['id']]); q('DELETE FROM attachments WHERE item_id=?', [$i['id']]); audit('item', (int)$i['id'], 'LOT_REMOVED', 'Lot', null, 'Removed at force close'); continue; }
                fail("SKU {$i['sku']} (lot " . (int)($i['lot_seq'] ?: 1) . ") is " . strtolower(ITEM_STATUS[$i['status']] ?? $i['status']) . ' — approve it, or remove an un-inspected design via Edit design, before force close.');
            }
            foreach ($roots as $r) { update('qc_items', ['line_closed' => 1, 'close_note' => $note], 'id=?', [$r['id']]); audit('item', (int)$r['id'], 'FORCE_CLOSE', 'SKU line', null, $note); }
            refresh_job_status($id);
            $now = one('SELECT status FROM qc_jobs WHERE id=?', [$id])['status'];
            if ($now === 'APPROVED') {
                require_once ROOT . '/app/workflow.php'; finalize_job_if_ready($id);
                if (!$only) { q("UPDATE qc_jobs SET status='CLOSED', closed_at=? WHERE id=?", [now(), $id]); audit('job', $id, 'FORCE_CLOSE', 'status', 'EMAILED', 'CLOSED'); audit('job', $id, 'FORCE_CLOSE', 'Reason', null, $note); }
                return ['ok' => true, 'message' => $only ? 'SKU closed — all SKUs done, PO report generated.' : 'Job force-closed. Consolidated PO report generated' . (setting('auto_email_on_approve', '1') === '1' ? ' and emailed.' : '.')];
            }
            return ['ok' => true, 'message' => 'SKU line closed at ' . ($only ? 'current' : 'cumulative') . ' accepted qty.'];
        case 'finalize':
            if (!is_role('super_admin', 'qc_manager')) break;
            refresh_job_status($id); finalize_job_if_ready($id);
            return ['ok' => true, 'message' => 'PO report generated and emailed.'];
        case 'email_job':
            if (!is_internal()) break;
            [, , $file] = job_pdf($id, true);
            $r = email_report($job, null, $file, (string)in_('subject', "QC Inspection Report | {$job['client_code']} | PO {$job['po_no']}"), (string)in_('message', 'Please find attached the QC inspection report.'));
            return ['ok' => true, 'message' => 'PO report emailed: ' . implode(', ', array_map(fn($k, $v) => "$k $v", array_keys($r), $r))];
        case 'email_item':
            if (!is_internal()) break;
            $it = one('SELECT * FROM qc_items WHERE id=? AND job_id=?', [(int)in_('item_id'), $id]);
            if (!$it || !$it['locked']) fail('Report not approved yet.');
            $file = $it['pdf_path'] && is_file(UPLOAD_DIR . '/' . $it['pdf_path']) ? UPLOAD_DIR . '/' . $it['pdf_path'] : item_pdf((int)$it['id'], true)[2];
            $r = email_report($job, $it, $file, (string)in_('subject'), (string)in_('message'));
            return ['ok' => true, 'message' => 'Report sent: ' . implode(', ', array_map(fn($k, $v) => "$k $v", array_keys($r), $r))];
    }
    fail('Not allowed', 403);
});

/* ----- Excel import ----- */
function parse_scope(string $s): ?array {
    $s = strtoupper(trim($s));
    if ($s === '') return null;
    if (str_contains($s, 'SAMPLE')) return ['scope' => 'SAMPLE', 'bulk_mode' => '100', 'aql_major' => 2.5, 'defined_qty' => 0, 'label' => 'Sample'];
    if (preg_match('/AQL[^0-9]*([0-9.]+)/', $s, $m)) return ['scope' => 'BULK', 'bulk_mode' => 'AQL', 'aql_major' => (float)$m[1], 'defined_qty' => 0, 'label' => 'AQL-' . $m[1]];
    if (str_contains($s, 'AQL')) return ['scope' => 'BULK', 'bulk_mode' => 'AQL', 'aql_major' => 2.5, 'defined_qty' => 0, 'label' => 'AQL-2.5'];
    if (preg_match('/DEFINED[^0-9]*([0-9]+)/', $s, $m)) return ['scope' => 'BULK', 'bulk_mode' => 'DEFINED', 'aql_major' => 2.5, 'defined_qty' => (int)$m[1], 'label' => 'Defined ' . $m[1]];
    if (str_contains($s, '100') || str_contains($s, 'BULK')) return ['scope' => 'BULK', 'bulk_mode' => '100', 'aql_major' => 2.5, 'defined_qty' => 0, 'label' => 'Bulk 100%'];
    return null;
}
function import_allowed(): bool {
    $isV = role() === 'vendor';
    if ($isV && !flag('enable_vendor_qc_request')) fail('Bulk Excel requests are not enabled yet.', 403);
    return $isV;
}
api('GET', 'import', ['super_admin', 'back_office', 'vendor'], function () {
    $isV = import_allowed();
    $clients = $isV ? all("SELECT c.id, c.code, c.name FROM clients c JOIN client_vendor m ON m.client_id=c.id WHERE m.vendor_id=? AND c.status='ACTIVE' ORDER BY c.code", [user()['vendor_id']])
        : all("SELECT id, code, name FROM clients WHERE status='ACTIVE' ORDER BY code");
    require_once ROOT . '/app/xlsx.php';
    $vend = $isV ? [] : all("SELECT m.client_id, v.id, v.name, v.code FROM client_vendor m JOIN vendors v ON v.id=m.vendor_id WHERE v.status='ACTIVE' ORDER BY v.name");
    return ['clients' => $clients, 'vendors' => $vend, 'import' => $_SESSION['import'] ?? null, 'columns' => IMPORT_COLS, 'vendor_mode' => $isV];
});
api('POST', 'import/reset', ['super_admin', 'back_office', 'vendor'], function () { unset($_SESSION['import']); return ['ok' => true]; });
/** Save a picture taken out of an uploaded Excel file; returns its upload path. */
function import_img_save(array $img): ?string {
    $dir = UPLOAD_DIR . '/imports/img'; if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $name = date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $img['ext'];
    return @file_put_contents("$dir/$name", $img['data']) ? "imports/img/$name" : null;
}
/** Product category from words in the sheet or the SKU prefix. */
function guess_product(string $sku, array $words): string {
    $cats = ['bracelet' => 'Bracelet', 'bangle' => 'Bangle', 'pendant' => 'Pendant', 'earring' => 'Earring', 'ring' => 'Ring', 'necklace' => 'Necklace',
        'nackla' => 'Necklace', 'chain' => 'Chain', 'anklet' => 'Anklet', 'set' => 'Set', 'stud' => 'Earring', 'hoop' => 'Earring'];
    foreach ($words as $w) { $lw = strtolower(trim($w)); foreach ($cats as $k => $v) if ($lw === $k || $lw === $k . 's') return $v; }
    $pre = ['SET' => 'Set', 'BR' => 'Bracelet', 'BG' => 'Bangle', 'PD' => 'Pendant', 'PN' => 'Pendant', 'ER' => 'Earring', 'EA' => 'Earring', 'RG' => 'Ring', 'RNG' => 'Ring',
        'KNK' => 'Necklace', 'NK' => 'Necklace', 'NCK' => 'Necklace', 'CH' => 'Chain', 'AN' => 'Anklet', 'KBR' => 'Bracelet', 'KPD' => 'Pendant', 'KER' => 'Earring'];
    uksort($pre, fn($x, $y) => strlen($y) - strlen($x));
    foreach ($pre as $k => $v) if (str_starts_with(strtoupper($sku), $k)) return $v;
    return 'Jewellery';
}
function nfmt($v, int $dp = 3): string { return is_numeric($v) ? rtrim(rtrim(number_format((float)$v, $dp, '.', ''), '0'), '.') : (string)$v; }
/**
 * Client "tech sheet" layout (e.g. C-142): one block of rows per design with the picture in the IMAGE column,
 * metal / gross weight on the first row, stone rows below and plating, size, lock written under the design no.
 */
function parse_techsheet(array $rows, array $images, int $hdrRow, array $col): array {
    $nums = array_keys($rows); $last = max($nums);
    $g = fn($rn, $k) => ($col[$k] ?? null) === null ? '' : trim((string)($rows[$rn][$col[$k]] ?? ''));
    $starts = [];
    foreach ($nums as $rn) if ($rn > $hdrRow && $g($rn, 'design') !== '' && ($g($rn, 'metal') !== '' || $g($rn, 'qty') !== '' || $g($rn, 'gross') !== '')) $starts[] = $rn;
    $out = []; $used = [];
    foreach ($starts as $k => $st) {
        $end = ($starts[$k + 1] ?? ($last + 1)) - 1;
        $sku = strtoupper(preg_replace('/\s+/', '', $g($st, 'design')));
        $notes = []; $stones = []; $codes = [];
        for ($rn = $st; $rn <= $end; $rn++) {
            if (!isset($rows[$rn])) continue;
            if ($rn > $st && ($d = $g($rn, 'design')) !== '') { if (preg_match('/^[A-Z]{1,6}[-]?\d{2,}[A-Z0-9\-]*$/i', $d) && !preg_match('/["”]|MM\b/i', $d)) $codes[] = strtoupper($d); else $notes[] = $d; }
            $stone = $g($rn, 'stone');
            if ($stone === '') continue;
            // a stone line has a shape/size or a setting; a total row (pcs + weight sums only) or RHODIUM / CHAIN text is a note
            if ($g($rn, 'shape') === '' && $g($rn, 'setting') === '' && $g($rn, 'stype') === '') { $notes[] = $stone; continue; }
            $set = implode(' / ', array_filter([$g($rn, 'stype'), $g($rn, 'setting')]));
            $tw = $g($rn, 'tw');
            $stones[] = ['name' => $stone, 'setting' => $set, 'shape' => $g($rn, 'shape'), 'pcs' => $g($rn, 'pcs'), 'ct' => $tw];
        }
        $notes = array_values(array_unique(array_map('trim', $notes))); $plating = ''; $length = []; $lock = ''; $words = [];
        foreach ($notes as $n) {
            $ln = strtolower($n);
            if (str_contains($ln, 'rhodium') || str_contains($ln, 'plating') || preg_match('/\b(gold|rose|black)\s*plat/i', $n)) { $plating = $plating ?: ucwords($ln); continue; }
            if (preg_match('/lock|clasp|push\s*post|screw|latch|hook|lever/i', $n)) { $lock = $lock ?: $n; continue; }
            if (preg_match('/["”]|inch|\bcm\b|\bmm\b|\bcl\s*\d|chain/i', $n)) { $length[] = $n; continue; }
            $words[] = $n;
        }
        $product = guess_product($sku, $words);
        $words = array_values(array_filter($words, fn($w) => guess_product('', [$w]) === 'Jewellery'));
        $gross = $g($st, 'gross'); $mw = $g($st, 'mweight');
        $weight = trim(($gross !== '' ? 'Gross ' . nfmt($gross) . ' g' : '') . ($mw !== '' ? ' · Metal ' . nfmt($mw) . ' g' : ''), ' ·');
        $sl = array_map(fn($x) => trim($x['name'] . ($x['setting'] ? ' · ' . $x['setting'] : '') . ($x['shape'] ? ' · ' . $x['shape'] : '') . ($x['pcs'] !== '' ? ' × ' . nfmt($x['pcs']) . ' pcs' : '') . ($x['ct'] !== '' ? ' = ' . nfmt($x['ct']) . ' ct' : '')), $stones);
        $main = $stones[0] ?? null;
        $stoneShort = $main ? ucwords(strtolower($main['name'])) . ($main['setting'] ? ' / ' . $main['setting'] : '') . (count($stones) > 1 ? ' +' . (count($stones) - 1) . ' more' : '') : '';
        $totPcs = array_sum(array_map(fn($x) => (float)$x['pcs'], $stones)); $totCt = array_sum(array_map(fn($x) => (float)$x['ct'], $stones));
        $detail = array_merge($sl ? ['Stones:'] : [], array_map(fn($x) => '• ' . $x, $sl),
            $stones ? ['Total: ' . nfmt($totPcs) . ' pcs · ' . nfmt($totCt) . ' ct'] : [], $codes ? ['Stone layout / ref: ' . implode(', ', $codes)] : [], $words ? ['Notes: ' . implode(', ', $words)] : []);
        // the picture whose top-left lies inside this block (or the row just above it, as Excel often anchors there)
        $lo = $st - 1; $hi = isset($starts[$k + 1]) ? $starts[$k + 1] - 2 : $end;
        $img = null; foreach ($images as $ii => $im) if (empty($used[$ii]) && $im['row'] >= $lo && $im['row'] <= $hi) { $img = $im; $used[$ii] = true; break; }
        if (!$img) foreach ($images as $ii => $im) if (empty($used[$ii]) && $im['row'] <= $st && $im['row2'] >= $st) { $img = $im; $used[$ii] = true; break; }
        $qty = (int)round((float)($g($st, 'qty') ?: 1)); if ($qty <= 0) $qty = 1;
        $out[] = ['n' => $st, 'sku' => $sku, 'product' => $product, 'metal' => $g($st, 'metal'), 'plating' => $plating, 'weight' => $weight,
            'length' => implode(' · ', $length), 'lock' => $lock, 'stamping' => '', 'style' => mb_substr(implode(', ', $codes), 0, 250), 'stone' => mb_substr($stoneShort, 0, 250),
            'detail' => implode("\n", $detail), 'instructions' => $g($st, 'comments'), 'qty' => $qty, 'img' => $img];
    }
    return $out;
}
api('POST', 'import/upload', ['super_admin', 'back_office', 'vendor'], function () {
    require_once ROOT . '/app/xlsx.php';
    $isV = import_allowed();
    $cid = (int)in_('client_id'); $client = one('SELECT * FROM clients WHERE id=?', [$cid]);
    if ($isV && !one('SELECT 1 FROM client_vendor WHERE client_id=? AND vendor_id=?', [$cid, user()['vendor_id']])) fail('Client not mapped to you.');
    $f = $_FILES['file'] ?? null;
    if (!$client || !$f || $f['error'] !== UPLOAD_ERR_OK) fail('Select a client and an Excel file.');
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    try {
        if ($ext === 'csv') { $book = ['rows' => [], 'images' => []]; foreach (csv_read($f['tmp_name']) as $i => $r) $book['rows'][$i + 1] = $r; }
        else $book = xlsx_read_full($f['tmp_name']);
    } catch (Throwable $e) { fail('Could not read file: ' . $e->getMessage() . ' (use .xlsx or .csv)'); }
    $all = $book['rows']; $images = $book['images'];
    if (count($all) < 2) fail('The file has no data rows.');
    $norm = fn($x) => strtolower(preg_replace('/[^a-z]/i', '', (string)$x));
    // header = first row (within the first 15) that names a SKU / design column
    $hdrRow = null; $hdr = [];
    foreach (array_slice($all, 0, 15, true) as $rn => $r) { $h = array_map($norm, $r); if (array_intersect(['sku', 'design', 'designno', 'skudesignno', 'styleno', 'itemsku'], $h)) { $hdrRow = $rn; $hdr = $h; break; } }
    if ($hdrRow === null) { $hdrRow = array_key_first($all); $hdr = array_map($norm, $all[$hdrRow]); }
    $find = function (array $names) use ($hdr) { foreach ($names as $n) { $k = array_search($n, $hdr, true); if ($k !== false) return $k; } return null; };
    $map = [];
    foreach (['vendor' => ['vendor', 'vendorname', 'factory'], 'po' => ['pono', 'po', 'ponumber'], 'sku' => ['sku', 'designno', 'skudesignno', 'design', 'itemsku', 'styleno'],
        'style' => ['clientstyleref', 'styleref', 'clientstyle', 'refno'], 'stone' => ['stonesetting', 'stone', 'settingtype'],
        'product' => ['product', 'category', 'item'], 'order' => ['orderqty', 'orderquantity', 'order'], 'ready' => ['readyqty', 'readyquantity', 'ready', 'qty', 'quantity'],
        'scope' => ['qcscope', 'scope'], 'metal' => ['metal'], 'plating' => ['plating'], 'weight' => ['weight', 'targetweight', 'gross'], 'length' => ['length'],
        'lock' => ['lock', 'locktype'], 'stamping' => ['stamping'], 'date' => ['requesteddate', 'qcdate', 'date'], 'spec' => ['specfile', 'spec', 'attachment'],
        'image' => ['image', 'picture', 'photo', 'productimage']] as $k => $names) $map[$k] = $find($names);
    // defaults from the form, used when the sheet has no such column
    $fVendor = (int)in_('vendor_id'); $fPo = trim((string)in_('po_no')); $fScope = trim((string)in_('scope'));
    $tech = $map['po'] === null && $map['vendor'] === null && $find(['design', 'designno']) !== null && ($map['image'] !== null || $map['metal'] !== null);
    $vendors = []; foreach (all('SELECT * FROM vendors') as $v) { $vendors[strtolower($v['name'])] = $v; $vendors[strtolower($v['code'])] = $v; }
    $mapped = array_column(all('SELECT vendor_id FROM client_vendor WHERE client_id=?', [$cid]), 'vendor_id');
    $existing = []; foreach (all("SELECT j.po_no, i.sku FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id WHERE j.client_id=? AND j.status NOT IN ('CANCELLED')", [$cid]) as $e) $existing[strtoupper($e['po_no'] . '|' . $e['sku'])] = true;
    $formVendor = $isV ? one('SELECT * FROM vendors WHERE id=?', [user()['vendor_id']]) : ($fVendor ? one('SELECT * FROM vendors WHERE id=?', [$fVendor]) : null);
    $fileStem = trim(preg_replace('/[_]+/', ' ', pathinfo($f['name'], PATHINFO_FILENAME)));
    $seen = []; $rows = []; $nimg = 0;
    $check = function (array $x, ?array $v, string $po, array $sc, int $oq, int $rq, array $w) use (&$seen, $existing, $mapped, $isV) {
        $e = [];
        if (!$v) $e[] = $isV ? 'Unknown vendor' : 'Select vendor'; elseif (!in_array($v['id'], $mapped)) $e[] = 'Vendor not mapped to client';
        if ($po === '') $e[] = 'PO missing'; if ($x['sku'] === '') $e[] = 'SKU missing';
        if ($rq <= 0) $e[] = 'Ready qty missing'; if ($oq && $rq > $oq) $e[] = 'Ready > Order';
        if ($sc['bulk_mode'] === 'DEFINED' && $sc['defined_qty'] > $rq) $e[] = 'Defined qty > Ready';
        $key = strtoupper($po . '|' . $x['sku']);
        if (isset($seen[$key]) || isset($existing[$key])) $e[] = 'Duplicate SKU'; $seen[$key] = true;
        return $e;
    };
    $scDefault = parse_scope($fScope) ?? null;
    if ($tech) {
        $col = ['design' => $find(['design', 'designno']), 'image' => $map['image'], 'metal' => $map['metal'], 'gross' => $find(['gross', 'grossweight', 'grosswt']),
            'mweight' => $find(['metalweight', 'metalwt', 'netweight']), 'stone' => $find(['diamond', 'stone', 'stones', 'gemstone']), 'setting' => $find(['setting']),
            'shape' => $find(['shapesize', 'shape', 'size']), 'pcs' => $find(['noofpcs', 'pcs', 'nopcs']), 'tw' => $find(['totaldiamondweight', 'totalweight', 'totalcts', 'totalct']),
            'qty' => $find(['qty', 'quantity', 'orderqty']), 'comments' => $find(['comments', 'comment', 'remarks', 'remark', 'instructions'])];
        // unnamed column right after the stone column holds Prong / Bezel
        $col['stype'] = ($col['stone'] !== null && ($hdr[$col['stone'] + 1] ?? 'x') === '' ) ? $col['stone'] + 1 : null;
        $sc = $scDefault ?? ['scope' => 'SAMPLE', 'bulk_mode' => '100', 'aql_major' => 2.5, 'defined_qty' => 0, 'label' => 'Sample'];
        $po = $fPo !== '' ? $fPo : mb_substr($fileStem, 0, 60);
        foreach (parse_techsheet($all, $images, $hdrRow, $col) as $x) {
            $w = []; if ($fPo === '') $w[] = 'PO taken from file name'; if (!$x['img']) $w[] = 'No image';
            $img = $x['img'] ? import_img_save($x['img']) : null; if ($img) $nimg++;
            $e = $check($x, $formVendor, $po, $sc, $x['qty'], $x['qty'], $w);
            $rows[] = array_merge($x, ['vendor_id' => $formVendor['id'] ?? null, 'vendor' => $formVendor['name'] ?? '—', 'po' => $po, 'order' => $x['qty'], 'ready' => $x['qty'], 'scope' => $sc,
                'date' => null, 'spec' => '', 'errors' => $e, 'warnings' => $w, 'img' => $img,
                'raw' => [$formVendor['name'] ?? '', $po, $x['sku'], $img ? 'yes' : '', $x['style'], $x['stone'], $x['product'], $x['qty'], $x['qty'], $sc['label'], $x['metal'], $x['plating'], $x['weight'], $x['length'], $x['lock'], '', '', '']]);
        }
        if (!$rows) fail('No designs found in this sheet. Each design needs its design no. and metal on the first row.');
    } else {
        foreach (($isV ? ['sku', 'ready'] : ['sku', 'ready']) as $req) if ($map[$req] === null) fail("Column “$req” not found. Download the template to see the expected columns.");
        if ($map['po'] === null && $fPo === '') fail('Column “PO No” not found — add it to the sheet or type the PO no. in the form.');
        if (!$isV && $map['vendor'] === null && !$formVendor) fail('Column “Vendor” not found — add it to the sheet or select the vendor in the form.');
        foreach ($all as $rn => $r) {
            if ($rn <= $hdrRow) continue;
            $g = fn($k) => $map[$k] === null ? '' : trim((string)($r[$map[$k]] ?? ''));
            if (!array_filter($r, fn($x) => trim((string)$x) !== '')) continue;
            $w = [];
            $v = $isV ? $formVendor : ($map['vendor'] !== null && $g('vendor') !== '' ? ($vendors[strtolower($g('vendor'))] ?? null) : $formVendor);
            if (!$v && !$isV && $g('vendor') !== '') foreach ($vendors as $key => $vv) if (str_starts_with($key, strtolower($g('vendor')))) { $v = $vv; break; }
            $po = $g('po') !== '' ? $g('po') : $fPo; $sku = strtoupper($g('sku'));
            $oq = (int)preg_replace('/[^0-9]/', '', $g('order')); $rq = (int)preg_replace('/[^0-9]/', '', $g('ready'));
            $sc = parse_scope($g('scope')) ?? $scDefault ?? ['scope' => 'BULK', 'bulk_mode' => '100', 'aql_major' => 2.5, 'defined_qty' => 0, 'label' => 'Bulk 100%'];
            if ($g('scope') === '' && !$scDefault) $w[] = 'Scope blank → Bulk 100%';
            if ($g('spec') === '') $w[] = 'Spec file missing';
            $im = null; foreach ($images as $x) if ($x['row'] === $rn) { $im = $x; break; }
            if (!$im) foreach ($images as $x) if ($x['row'] < $rn && $x['row2'] >= $rn) { $im = $x; break; }
            $img = $im ? import_img_save($im) : null; if ($img) $nimg++;
            $x = ['sku' => $sku];
            $e = $check($x, $v, $po, $sc, $oq, $rq, $w);
            $rows[] = ['n' => $rn, 'vendor_id' => $v['id'] ?? null, 'vendor' => $v['name'] ?? $g('vendor'), 'po' => $po, 'sku' => $sku, 'product' => $g('product') ?: 'Necklace',
                'order' => $oq ?: $rq, 'ready' => $rq, 'scope' => $sc, 'metal' => $g('metal'), 'plating' => $g('plating'), 'weight' => $g('weight'), 'length' => $g('length'),
                'lock' => $g('lock'), 'stamping' => $g('stamping'), 'style' => $g('style'), 'stone' => $g('stone'), 'date' => excel_date($g('date')), 'spec' => $g('spec'),
                'detail' => '', 'instructions' => '', 'img' => $img, 'errors' => $e, 'warnings' => $w,
                'raw' => array_map(fn($c) => $c === 'image' ? ($img ? 'yes' : '') : ($map[$c] === null ? '' : ($r[$map[$c]] ?? '')), ['vendor', 'po', 'sku', 'image', 'style', 'stone', 'product', 'order', 'ready', 'scope', 'metal', 'plating', 'weight', 'length', 'lock', 'stamping', 'date', 'spec'])];
        }
    }
    $saved = save_upload($f, 'imports', ['xlsx', 'csv', 'xls']);
    $_SESSION['import'] = ['client_id' => $cid, 'client' => $client['code'], 'file' => $f['name'], 'size' => $f['size'], 'rows' => $rows, 'path' => $saved['path'] ?? null,
        'format' => $tech ? 'techsheet' : 'table', 'images' => $nimg];
    return ['ok' => true, 'import' => $_SESSION['import']];
});
api('POST', 'import/create', ['super_admin', 'back_office', 'vendor'], function () {
    $isV = import_allowed(); $imp = $_SESSION['import'] ?? null;
    if (!$imp) fail('Upload a file first.');
    $status = in_('mode') === 'draft' ? 'DRAFT' : 'REQUESTED';
    // 1 design (SKU) = 1 job card (setting); the PO no. stays common so the PO-wise report merges them again
    $one = flag('import_one_job_per_design');
    $groups = []; foreach ($imp['rows'] as $k => $r) { if ($r['errors']) continue; $groups[$one ? 'r' . $k : $r['vendor_id'] . '|' . $r['po'] . '|' . $r['scope']['label']][] = $r; }
    $made = []; $jobs = []; $byPo = [];
    foreach ($groups as $g) {
        $f = $g[0]; $sc = $f['scope'];
        $job = ['req_no' => next_req_no(), 'source' => $isV ? 'Vendor portal' : 'Excel import', 'client_id' => $imp['client_id'], 'vendor_id' => $f['vendor_id'], 'po_no' => $f['po'],
            'qc_type' => $sc['scope'] === 'SAMPLE' ? 'Pre-production' : 'Pre-shipment', 'location' => explode(',', (string)val('SELECT location FROM vendors WHERE id=?', [$f['vendor_id']]))[0],
            'requested_date' => $f['date'] ?: date('Y-m-d', strtotime('+2 days')), 'scope' => $sc['scope'], 'bulk_mode' => $sc['bulk_mode'], 'insp_level' => 'II',
            'aql_critical' => 0, 'aql_major' => $sc['aql_major'], 'aql_minor' => 4.0, 'defined_qty' => $sc['defined_qty'], 'allow_excess' => 0,
            'specs' => 'Imported from ' . $imp['file'], 'status' => $status, 'created_by' => user()['id'], 'created_at' => now()];
        if ($status === 'REQUESTED' && $one) $job['job_no'] = next_job_no();
        $jid = insert('qc_jobs', $job); $job['id'] = $jid;
        foreach ($g as $r) {
            $plan = item_plan($job, $r['ready']);
            insert('qc_items', ['job_id' => $jid, 'sku' => $r['sku'], 'product' => $r['product'], 'metal' => $r['metal'], 'plating' => $r['plating'], 'target_weight' => $r['weight'],
                'length' => $r['length'], 'lock_type' => $r['lock'], 'stamping' => $r['stamping'], 'style_ref' => $r['style'], 'stone_setting' => $r['stone'], 'spec_detail' => $r['detail'] ?? '', 'instructions' => $r['instructions'] ?? '', 'master_sample' => 'Approved Master Sample Available',
                'order_qty' => $r['order'], 'ready_qty' => $r['ready'], 'insp_qty' => $plan['insp_qty'], 'plan_json' => json_encode($plan['plan']), 'lot_seq' => 1, 'status' => 'PENDING', 'updated_at' => now()]);
            $iid = (int)db()->lastInsertId();
            if (!empty($r['img'])) insert('item_photos', ['item_id' => $iid, 'kind' => 'ref', 'path' => $r['img'], 'remark' => 'From Excel', 'created_at' => now()]);
        }
        if ($imp['path']) insert('attachments', ['job_id' => $jid, 'item_id' => null, 'path' => $imp['path'], 'name' => $imp['file'], 'created_at' => now()]);
        audit('job', $jid, 'CREATE', 'source', null, 'Excel import');
        $made[] = $job['job_no'] ?? $job['req_no']; $byPo[$job['po_no']][] = $jid;
    }
    // one notification per PO instead of one per design
    if ($status === 'REQUESTED') foreach ($byPo as $po => $ids) {
        $j1 = job_load($ids[0]); $n = count($ids);
        notify_job_parties($j1, ($n > 1 ? "$n QC job cards" : "QC request {$j1['req_no']}") . " created from Excel · PO $po", $n > 1 ? 'jobs?q=' . rawurlencode($po) : 'request/' . $ids[0]);
        if ($isV) notify_role('back_office', "Vendor bulk request · $n design(s) · PO $po", 'jobs?q=' . rawurlencode($po));
    }
    unset($_SESSION['import']);
    $show = count($made) > 6 ? implode(', ', array_slice($made, 0, 3)) . ' … ' . end($made) : implode(', ', $made);
    return ['ok' => true, 'created' => $made, 'po' => array_keys($byPo), 'message' => count($made) . ($one ? ' job card(s) — one per design — ' : ' QC request(s) ') . "created ($show) — status $status."];
});
api('GET', 'import/template', ['super_admin', 'back_office', 'vendor'], function () {
    require_once ROOT . '/app/xlsx.php';
    if (in_('errors') && !empty($_SESSION['import'])) {
        $rows = [array_merge(['Row'], IMPORT_COLS, ['Issues'])];
        foreach ($_SESSION['import']['rows'] as $r) if ($r['errors'] || $r['warnings']) $rows[] = array_merge([$r['n']], $r['raw'], [implode('; ', array_merge($r['errors'], $r['warnings']))]);
        $name = 'import_errors_' . date('Ymd_His') . '.xlsx';
    } else {
        $v = role() === 'vendor' ? val('SELECT name FROM vendors WHERE id=?', [user()['vendor_id']]) : 'Ashok Jewels';
        $d = fn($n) => date('d-m-Y', strtotime("+$n days"));
        $rows = [IMPORT_COLS,
            [$v, '81966', 'KNK01268FS0033', '', 'KP-FS0033', 'CZ / Prong', 'Necklace', 1150, 1150, 'Bulk 100%', '925 Silver', 'White rhodium', '2.75 g', '16+2 inch', 'Lobster lock', '925 + ARCL + LGDS', $d(3), 'Spec.pdf'],
            [$v, '81966', 'RNG00451', '', 'KP-RG451', 'CZ / Prong', 'Ring', 500, 500, 'Bulk 100%', '925 Silver', 'White rhodium', '2.40 g', 'Size 6', '', '925', $d(3), 'CAD.jpg'],
            ['Nirvana', '82001', 'ABC001', '', 'KP-ABC1', 'MOP / Bezel', 'Pendant', 700, 700, 'AQL-1.5', '925 Silver', 'White rhodium', '1.20 g', '20 mm', '', '925', $d(4), ''],
            ['Nirvana', '82002', 'ABC002', '', 'KP-ABC2', '—', 'Chain', 10, 10, 'Sample', '925 Silver', '', '4.00 g', '20 inch', 'Lobster lock', '925', $d(4), ''],
            [$v, '81968', 'KNK00511BR0007', '', 'KP-BR0007', 'Marquise CZ / Prong', 'Bracelet', 300, 300, 'Defined 50', '925 Silver', 'Rose gold', '6.20 g', '7 inch', 'Box clasp', '925 + ARCL', $d(5), '']];
        $name = 'QTRACK_QC_REQUIREMENT_TEMPLATE.xlsx';
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    echo xlsx_write($rows, 'QC Requirement'); exit;
});

/* ----- design-wise edit inside a generated job card (Super Admin / Back Office / QC Manager) ----- */
const DESIGN_FIELDS = ['sku', 'product', 'metal', 'plating', 'target_weight', 'length', 'width', 'thickness', 'lock_type', 'stamping', 'style_ref', 'stone_setting', 'spec_detail', 'instructions', 'master_sample'];
api('POST', 'item/save', ['super_admin', 'back_office', 'qc_manager'], function () {
    $D = json_decode((string)($_POST['data'] ?? ''), true) ?: inp();
    $id = (int)($D['id'] ?? 0);
    $it = $id ? one('SELECT * FROM qc_items WHERE id=?', [$id]) : null;
    $job = job_load($it ? (int)$it['job_id'] : (int)($D['job_id'] ?? 0));
    if (!$job || ($id && !$it)) fail('Not found', 404);
    if (in_array($job['status'], ['CLOSED', 'CANCELLED'], true)) fail('Job card is closed — editing is not allowed.');
    $up = [];
    foreach (DESIGN_FIELDS as $k) if (array_key_exists($k, $D)) { $v = $k === 'sku' ? strtoupper(trim((string)$D[$k])) : trim((string)$D[$k]); if (!$it || (string)$v !== (string)$it[$k]) $up[$k] = $v; }
    if (array_key_exists('sku', $up) && $up['sku'] === '') fail('SKU / design no. is required.');
    $sku = $up['sku'] ?? $it['sku'] ?? '';
    if (val('SELECT id FROM qc_items WHERE job_id=? AND sku=? AND parent_id IS NULL AND id<>?', [$job['id'], $sku, $it ? line_root_id($it) : 0])) fail("SKU $sku is already in this job card.");
    $locked = $it && ((int)$it['locked'] || in_array($it['status'], ['SUBMITTED', 'UNDER_REVIEW', 'APPROVED'], true));
    // quantities and scope: only while the design is not yet inspected/approved (order qty can change anytime the job is open)
    $oq = isset($D['order_qty']) ? max(0, (int)$D['order_qty']) : null; $rq = isset($D['ready_qty']) ? max(0, (int)$D['ready_qty']) : null;
    if ($oq !== null && (!$it || $oq !== (int)$it['order_qty'])) { if ($it && $it['parent_id']) fail('Change order qty on the first lot of this SKU.'); $up['order_qty'] = $oq; }
    $sc = array_key_exists('scope', $D) ? (string)$D['scope'] : null;
    $scopeMap = ['' => [null, null, null, null], 'JOB' => [null, null, null, null], 'BULK100' => ['BULK', '100', null, null], 'SAMPLE' => ['SAMPLE', '100', null, (int)($D['defined_qty'] ?? 0) ?: null],
        'AQL1.5' => ['BULK', 'AQL', '1.5', null], 'AQL2.5' => ['BULK', 'AQL', '2.5', null], 'AQL4.0' => ['BULK', 'AQL', '4.0', null], 'DEFINED' => ['BULK', 'DEFINED', null, max(1, (int)($D['defined_qty'] ?? 0))]];
    if ($sc !== null && isset($scopeMap[$sc])) { [$a, $b2, $c, $dq] = $scopeMap[$sc]; foreach (['i_scope' => $a, 'i_bulk_mode' => $b2, 'i_aql_major' => $c, 'i_defined_qty' => $dq] as $k => $v) if (!$it || (string)$it[$k] !== (string)$v) $up[$k] = $v; }
    if ($rq !== null && (!$it || $rq !== (int)$it['ready_qty'])) $up['ready_qty'] = $rq;
    if ($locked && array_intersect_key($up, array_flip(['ready_qty', 'i_scope', 'i_bulk_mode', 'i_aql_major', 'i_defined_qty']))) fail('This design is already submitted/approved — ready qty and QC scope cannot change. Use Edit full report (Super Admin) or add a new QC lot.');
    $order = $up['order_qty'] ?? (int)($it['order_qty'] ?? 0); $ready = $up['ready_qty'] ?? (int)($it['ready_qty'] ?? 0);
    if (!$it && $ready <= 0) fail('Ready qty is required.');
    if ($order && $ready > $order && !(int)$job['allow_excess'] && (!$it || !$it['parent_id'])) fail("Ready qty ($ready) is more than order qty ($order).");
    if (isset($up['ready_qty']) || array_intersect_key($up, array_flip(['i_scope', 'i_bulk_mode', 'i_aql_major', 'i_defined_qty']))) {
        $plan = item_plan(item_job($job, array_merge($it ?: [], $up)), $ready); $up['insp_qty'] = $plan['insp_qty']; $up['plan_json'] = json_encode($plan['plan']);
    }
    if (!$it) {
        if (!isset($up['order_qty'])) $up['order_qty'] = $ready;
        $id = insert('qc_items', $up + ['job_id' => $job['id'], 'product' => $up['product'] ?? 'Jewellery', 'master_sample' => $up['master_sample'] ?? 'Approved Master Sample Available', 'lot_seq' => 1, 'status' => 'PENDING', 'updated_at' => now()]);
        audit('item', $id, 'EDIT', 'Design added', null, $sku); audit('job', (int)$job['id'], 'EDIT', 'Design added', null, $sku);
        if (in_array($job['status'], ['PARTIAL', 'APPROVED'], true)) { q("UPDATE qc_jobs SET status='ASSIGNED' WHERE id=?", [$job['id']]); audit('job', (int)$job['id'], 'STATUS', 'status', $job['status'], 'ASSIGNED'); }
        if ($job['inspector_id']) notify((int)$job['inspector_id'], "New design $sku added to {$job['job_no']}", 'app/job/' . $job['id']);
    } else {
        foreach ($up as $k => $v) if (!in_array($k, ['plan_json'], true)) audit('item', $id, 'EDIT', $k, $it[$k], $v, (int)$it['rev']);
        if ($up) update('qc_items', $up + ['updated_at' => now()], 'id=?', [$id]);
        // spec changes on the first lot flow to later lots of the same SKU that are not inspected yet
        $flow = array_intersect_key($up, array_flip(array_merge(DESIGN_FIELDS, ['order_qty'])));
        if ($flow && !$it['parent_id']) foreach (all("SELECT id FROM qc_items WHERE parent_id=? AND status='PENDING'", [$id]) as $c) update('qc_items', $flow, 'id=?', [$c['id']]);
    }
    // reference / CAD image (replaces the previous reference picture)
    $f = $_FILES['ref'] ?? null;
    if ($f && $f['error'] === UPLOAD_ERR_OK) {
        $sv = save_upload($f, 'photos', ['jpg', 'jpeg', 'png', 'webp']); if (!$sv) fail('Reference image must be JPG, PNG or WEBP.');
        q("DELETE FROM item_photos WHERE item_id=? AND kind='ref'", [$id]);
        insert('item_photos', ['item_id' => $id, 'kind' => 'ref', 'path' => $sv['path'], 'remark' => 'Reference', 'created_at' => now()]);
        audit('item', $id, 'EDIT', 'Reference image', null, $sv['name']);
        $root = $it && $it['parent_id'] ? (int)$it['parent_id'] : $id;
        foreach (all("SELECT id FROM qc_items WHERE (parent_id=? OR id=?) AND id<>? AND status<>'APPROVED'", [$root, $root, $id]) as $c) { q("DELETE FROM item_photos WHERE item_id=? AND kind='ref'", [$c['id']]); insert('item_photos', ['item_id' => $c['id'], 'kind' => 'ref', 'path' => $sv['path'], 'remark' => 'Reference', 'created_at' => now()]); }
    }
    if ($it) refresh_job_status((int)$job['id']); else refresh_job_status((int)$job['id']);
    return ['ok' => true, 'id' => $id, 'message' => $it ? 'Design updated.' : "Design $sku added to the job card."];
});
api('POST', 'item/delete', ['super_admin', 'back_office', 'qc_manager'], function () {
    $it = one('SELECT * FROM qc_items WHERE id=?', [(int)in_('id')]); if (!$it) fail('Not found', 404);
    $job = job_load((int)$it['job_id']);
    if (in_array($job['status'], ['CLOSED', 'CANCELLED'], true)) fail('Job card is closed.');
    if ($it['status'] !== 'PENDING' || $it['started_at'] || (int)$it['locked']) fail('Only un-inspected designs can be removed.');
    if (!$it['parent_id'] && val('SELECT COUNT(*) FROM qc_items WHERE parent_id=?', [$it['id']])) fail('This SKU has later QC lots — it cannot be removed.');
    if ((int)val('SELECT COUNT(*) FROM qc_items WHERE job_id=?', [$job['id']]) <= 1) fail('A job card needs at least one design — cancel the job instead.');
    q('DELETE FROM item_photos WHERE item_id=?', [$it['id']]); q('DELETE FROM attachments WHERE item_id=?', [$it['id']]); q('DELETE FROM qc_items WHERE id=?', [$it['id']]);
    audit('job', (int)$job['id'], 'EDIT', 'Design removed', $it['sku'] . ($it['parent_id'] ? ' lot ' . $it['lot_seq'] : ''), null);
    refresh_job_status((int)$job['id']);
    require_once ROOT . '/app/workflow.php'; finalize_job_if_ready((int)$job['id']);
    return ['ok' => true, 'message' => "Design {$it['sku']} removed."];
});
