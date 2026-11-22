<?php
/* ---------- jobs, schedule, review, reports, approvals, analytics ---------- */

api('GET', 'jobs', R_INT, function () {
    $f = (string)in_('f', 'ALL');
    $groups = ['ASSIGNED' => ['ASSIGNED'], 'IN_PROGRESS' => ['IN_PROGRESS'], 'REVIEW' => ['SUBMITTED', 'UNDER_REVIEW', 'RETURNED'], 'OPEN' => ['PARTIAL'], 'CLOSED' => ['APPROVED', 'EMAILED', 'CLOSED']];
    $qs = trim((string)in_('q')); $bp = [];
    $base = "FROM qc_jobs j JOIN clients c ON c.id=j.client_id JOIN vendors v ON v.id=j.vendor_id LEFT JOIN users u ON u.id=j.inspector_id WHERE j.job_no IS NOT NULL AND j.status<>'CANCELLED'";
    if ($qs !== '') { $base .= ' AND (j.po_no LIKE ? OR j.job_no LIKE ? OR v.name LIKE ? OR EXISTS (SELECT 1 FROM qc_items qi WHERE qi.job_id=j.id AND qi.sku LIKE ?))'; $bp = ["%$qs%", "%$qs%", "%$qs%", "%$qs%"]; }
    if ($ins = (int)in_('insp')) { $base .= ' AND j.inspector_id=?'; $bp[] = $ins; }
    $counts = ['ALL' => (int)val("SELECT COUNT(*) $base", $bp), 'UNASSIGNED' => (int)val("SELECT COUNT(*) $base AND j.inspector_id IS NULL", $bp)];
    foreach ($groups as $k => $g) $counts[$k] = (int)val("SELECT COUNT(*) $base AND j.status IN ('" . implode("','", $g) . "')", $bp);
    $w = $base;
    if ($f === 'UNASSIGNED') $w .= ' AND j.inspector_id IS NULL'; elseif (isset($groups[$f])) $w .= " AND j.status IN ('" . implode("','", $groups[$f]) . "')";
    $rows = all("SELECT j.*, " . img_job_sql() . " img, c.code client_code, v.name vendor_name, u.emp_code insp_code, u.name insp_name, (SELECT COUNT(*) FROM qc_items i WHERE i.job_id=j.id AND i.parent_id IS NULL) skus,
        (SELECT MIN(i.sku) FROM qc_items i WHERE i.job_id=j.id) sku1 $w ORDER BY " . ($f === 'OPEN' ? 'j.po_no, j.id' : 'j.scheduled_date DESC, j.id DESC') . " LIMIT 500", $bp);
    // order vs cumulative accepted vs balance for open / partial / reviewed job cards
    foreach ($rows as &$r) {
        if (!in_array($r['status'], ['PARTIAL', 'APPROVED', 'IN_PROGRESS', 'ASSIGNED', 'SUBMITTED', 'UNDER_REVIEW', 'RETURNED'], true)) continue;
        $o = $a = 0; $lot = null;
        foreach (all('SELECT id FROM qc_items WHERE job_id=? AND parent_id IS NULL', [$r['id']]) as $x) { $L = line_stats($r, (int)$x['id']); $o += $L['order']; $a += $L['accepted']; if ($L['can_add_lot'] && !$lot) $lot = ['item_id' => $L['root'], 'sku' => $L['sku'], 'pending' => $L['pending']]; }
        $r['bal'] = ['order' => $o, 'accepted' => $a, 'pending' => max(0, $o - $a), 'lot' => $lot];
    }
    unset($r);
    return ['rows' => $rows, 'counts' => $counts, 'inspectors' => all("SELECT id, emp_code, name FROM users WHERE role='inspector' AND status='ACTIVE' ORDER BY emp_code")];
});
api('GET', 'jobs/assign', R_INT, function () {
    $job = job_load((int)in_('id')); if (!$job) fail('Not found', 404);
    $date = $job['scheduled_date'] ?: ($job['requested_date'] ?: today());
    $team = all("SELECT u.id, u.name, u.emp_code, u.location, (SELECT COUNT(*) FROM qc_jobs j WHERE j.inspector_id=u.id AND j.scheduled_date=? AND j.status IN ('ASSIGNED','IN_PROGRESS') AND j.id<>?) busy FROM users u WHERE u.role='inspector' AND u.status='ACTIVE' ORDER BY u.emp_code", [$date, $job['id']]);
    foreach ($team as &$t) { $t['near'] = $t['location'] && stripos($job['vendor_address'] . ' ' . $job['location'], (string)$t['location']) !== false; $t['st'] = insp_status((int)$t['id'], $date, (int)$job['id']); }
    unset($t);
    usort($team, fn($a, $b) => (['AVAILABLE' => 0, 'ASSIGNED' => 1, 'ONSITE' => 2, 'LEAVE' => 3][$a['st']['code']] <=> ['AVAILABLE' => 0, 'ASSIGNED' => 1, 'ONSITE' => 2, 'LEAVE' => 3][$b['st']['code']]) ?: strcmp((string)$a['emp_code'], (string)$b['emp_code']));
    return ['job' => $job, 'date' => $date, 'team' => $team, 'unassign_ok' => $job['inspector_id'] ? unassign_block($job) === null : true, 'is_sa' => is_role('super_admin'), 'skus' => (int)val('SELECT COUNT(*) FROM qc_items WHERE job_id=?', [$job['id']]), 'split' => flag('allow_job_split')];
});

api('GET', 'schedule', [...R_INT, 'vendor', 'client'], function () {
    [$sc, $sp] = job_scope('j');
    if (!is_internal()) {
        return ['upcoming' => all("SELECT j.id, j.req_no, j.po_no, j.status, j.scheduled_date, j.requested_date, j.time_slot, j.location, c.code client_code, v.name vendor_name, u.emp_code insp_code, u.name insp_name, u.phone insp_phone
            FROM qc_jobs j JOIN clients c ON c.id=j.client_id JOIN vendors v ON v.id=j.vendor_id LEFT JOIN users u ON u.id=j.inspector_id
            WHERE $sc AND j.status IN ('REQUESTED','SCHEDULED','ASSIGNED','IN_PROGRESS') ORDER BY COALESCE(j.scheduled_date,j.requested_date)", $sp)];
    }
    $start = in_('w') ? date('Y-m-d', strtotime('monday this week', strtotime((string)in_('w')))) : date('Y-m-d', strtotime('monday this week'));
    $days = []; for ($k = 0; $k < 7; $k++) $days[] = date('Y-m-d', strtotime("$start +$k days"));
    $sql = "SELECT j.id, j.job_no, j.req_no, j.status, j.inspector_id, j.time_slot, COALESCE(j.scheduled_date, j.requested_date) d, c.code client_code, v.name vendor_name,
        (SELECT COUNT(*) FROM qc_items i WHERE i.job_id=j.id) skus FROM qc_jobs j JOIN clients c ON c.id=j.client_id JOIN vendors v ON v.id=j.vendor_id
        WHERE j.status NOT IN ('CANCELLED','DRAFT') AND COALESCE(j.scheduled_date, j.requested_date) BETWEEN ? AND ?"; $p = [$start, end($days)];
    if ($cf = (int)in_('client')) { $sql .= ' AND j.client_id=?'; $p[] = $cf; }
    return ['start' => $start, 'days' => $days, 'today' => today(), 'jobs' => all($sql . ' ORDER BY j.time_slot', $p), 'can_drag' => is_role('super_admin', 'qc_manager'),
        'inspectors' => all("SELECT id, name, emp_code, location FROM users WHERE role='inspector' AND status='ACTIVE' ORDER BY emp_code")];
});

/* ----- review ----- */
api('GET', 'review', ['super_admin', 'qc_manager'], function () {
    return ['rows' => all("SELECT " . img_item_sql() . " img, i.id, i.sku, i.status, i.system_result, i.inspected_qty, i.rejected_qty, i.submitted_at, i.return_count, j.job_no, j.po_no, j.id jid, c.code client_code, v.name vendor_name, u.emp_code insp_code,
        (SELECT COUNT(*) FROM item_photos p WHERE p.item_id=i.id) nph FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id JOIN clients c ON c.id=j.client_id JOIN vendors v ON v.id=j.vendor_id
        LEFT JOIN users u ON u.id=j.inspector_id WHERE i.status IN ('SUBMITTED','UNDER_REVIEW','RETURNED') ORDER BY CASE i.status WHEN 'RETURNED' THEN 1 ELSE 0 END, i.submitted_at"),
        'concessions' => all("SELECT i.id, i.report_no, i.sku, i.concession_due, c.code client_code, v.name vendor_name FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id JOIN clients c ON c.id=j.client_id JOIN vendors v ON v.id=j.vendor_id WHERE i.concession_status='PENDING'")];
});
function review_payload(int $id): array {
    $it = one('SELECT * FROM qc_items WHERE id=?', [$id]); if (!$it) fail('Not found', 404); $it['img'] = item_img($id);
    $job = item_job(job_load((int)$it['job_id']), $it); $job['scope_label'] = scope_label($job); $job['rule'] = result_rule_text($job);
    $def = all('SELECT * FROM item_defects WHERE item_id=?', [$id]);
    $photos = array_map(fn($p) => $p + ['url' => url('file', ['p' => $p['path']])], all('SELECT * FROM item_photos WHERE item_id=? ORDER BY id', [$id]));
    $checks = jdec($it['checks_json']); $meas = jdec($it['meas_json']); $tests = jdec($it['tests_json']);
    $sev = ['CRITICAL' => 0, 'MAJOR' => 0, 'MINOR' => 0]; foreach ($def as $d) $sev[$d['severity']] += (int)$d['qty']; $dq = array_sum($sev);
    $comp = ['General info' => (bool)$it['insp_date'], 'Quantity' => $it['inspected_qty'] > 0 && $it['accepted_qty'] + $it['rejected_qty'] == $it['inspected_qty'],
        'Quality checks' => $checks && !array_filter($checks, fn($c) => ($c['result'] ?? '') === ''), 'Measurements' => (bool)array_filter($meas, fn($m) => ($m['actual'] ?? '') !== ''),
        'On-site tests' => (bool)$tests, 'Defects' => $it['rejected_qty'] == 0 || $dq >= $it['rejected_qty'], 'Photos' => !$dq || (bool)array_filter($photos, fn($p) => $p['kind'] === 'defect'), 'Final result' => (bool)$it['inspector_result']];
    return ['item' => $it, 'job' => $job, 'defects' => $def, 'photos' => $photos, 'checks' => $checks, 'meas' => $meas, 'tests' => $tests, 'sev' => $sev, 'complete' => $comp,
        'steps' => array_values(STEPS), 'can_review' => in_array($it['status'], ['UNDER_REVIEW', 'SUBMITTED'], true), 'can_revise' => is_role('super_admin') && (bool)$it['locked'],
        'approver' => $it['approved_by'] ? val('SELECT name FROM users WHERE id=?', [$it['approved_by']]) : null];
}
api('GET', 'review/item', ['super_admin', 'qc_manager'], function () {
    $id = (int)in_('id'); $it = one('SELECT * FROM qc_items WHERE id=?', [$id]); if (!$it) fail('Not found', 404);
    if ($it['status'] === 'SUBMITTED') { q("UPDATE qc_items SET status='UNDER_REVIEW' WHERE id=?", [$id]); audit('item', $id, 'UNDER_REVIEW'); refresh_job_status((int)$it['job_id']); }
    return review_payload($id);
});
api('POST', 'review/action', ['super_admin', 'qc_manager'], function () {
    require_once ROOT . '/app/workflow.php';
    $it = one('SELECT * FROM qc_items WHERE id=?', [(int)in_('id')]); if (!$it) fail('Not found', 404);
    $job = job_load((int)$it['job_id']); $a = (string)in_('act');
    if ($a === 'return') {
        if (!in_array($it['status'], ['SUBMITTED', 'UNDER_REVIEW'], true)) fail('Not under review');
        $secs = array_values(array_intersect((array)in_('sections', []), STEPS)); $reason = trim((string)in_('reason'));
        if (!$secs || !$reason) fail('Select the section(s) to correct and write a reason.');
        update('qc_items', ['status' => 'RETURNED', 'return_sections' => implode(', ', $secs), 'return_reason' => $reason, 'return_count' => (int)$it['return_count'] + 1], 'id=?', [$it['id']]);
        audit('item', (int)$it['id'], 'RETURNED', implode(', ', $secs), null, $reason);
        notify((int)$job['inspector_id'], "Inspection returned for correction · {$job['job_no']} · {$it['sku']} — $reason", 'app/inspect/' . $it['id']);
        refresh_job_status((int)$job['id']);
        return ['ok' => true, 'message' => 'Returned to inspector with remarks.'];
    }
    if ($a === 'approve') {
        if (!in_array($it['status'], ['SUBMITTED', 'UNDER_REVIEW'], true)) fail('Not under review');
        $final = in_array(in_('final'), ['PASS', 'FAIL', 'CONDITIONAL'], true) ? in_('final') : null;
        if (!$final) fail('Choose the final decision (PASS / FAIL / CONDITIONAL PASS). PENDING cannot be approved.');
        $note = trim((string)in_('note'));
        if ($final !== $it['system_result'] && $note === '') fail('Override of the system result needs a manager note.');
        $dev = array_values(array_filter((array)in_('deviation', []), fn($d) => trim((string)($d['param'] ?? '')) !== ''));
        if ($final === 'CONDITIONAL' && !$dev) fail('Conditional pass needs at least one deviation line for the client.');
        $r = approve_item((int)$it['id'], $final, $note, $dev);
        return ['ok' => true, 'message' => "Approved — locked PDF {$r['report_no']} generated." . ($final === 'CONDITIONAL' ? ' Sent to client for concession.' : '')];
    }
    if ($a === 'revise') {
        if (!is_role('super_admin') || !$it['locked']) fail('Only Super Admin can revise a locked report.', 403);
        $reason = trim((string)in_('reason')); if ($reason === '') fail('Unlock needs a reason.');
        $rev = (int)$it['rev'] + 1; $chg = [];
        foreach (['inspected_qty', 'accepted_qty', 'rejected_qty', 'final_result', 'remark', 'action_required', 'manager_note'] as $f) { $nv = in_($f); if ($nv !== null && (string)$nv !== (string)$it[$f]) $chg[$f] = $nv; }
        $meas = jdec($it['meas_json']); $mch = false;
        foreach ((array)in_('m_actual', []) as $k => $v) if (isset($meas[$k]) && (string)$v !== (string)$meas[$k]['actual']) {
            audit('item', (int)$it['id'], 'REVISION', 'Measurement · ' . $meas[$k]['param'], $meas[$k]['actual'], $v, $rev);
            $meas[$k]['actual'] = $v; $meas[$k]['result'] = abs((float)$v - (float)$meas[$k]['spec']) <= (float)$meas[$k]['tol'] + 1e-9 ? 'PASS' : 'FAIL'; $mch = true; }
        $i2 = (int)($chg['inspected_qty'] ?? $it['inspected_qty']); $a2 = (int)($chg['accepted_qty'] ?? $it['accepted_qty']); $r2 = (int)($chg['rejected_qty'] ?? $it['rejected_qty']);
        if ($a2 + $r2 !== $i2) fail('Accepted + Rejected must equal Inspected.');
        if (!$chg && !$mch) fail('No changes made.');
        foreach ($chg as $f => $v) audit('item', (int)$it['id'], 'REVISION', ucwords(str_replace('_', ' ', $f)), $it[$f], $v, $rev);
        audit('item', (int)$it['id'], 'UNLOCK', 'Reason', null, $reason, $rev);
        update('qc_items', $chg + ['rev' => $rev, 'meas_json' => json_encode($meas), 'updated_at' => now()], 'id=?', [$it['id']]);
        [, , $file] = item_pdf((int)$it['id'], true);
        email_report($job, one('SELECT * FROM qc_items WHERE id=?', [$it['id']]), $file, 'REVISED QC Report ' . sprintf('REV %02d', $rev) . " | {$it['report_no']}",
            "Dear Sir/Madam,\n\nThe QC report {$it['report_no']} has been revised (Revision " . sprintf('%02d', $rev) . ").\nReason: $reason\n\nPlease refer to the attached revised report.");
        if ($job['po_pdf']) job_pdf((int)$job['id'], true);
        return ['ok' => true, 'message' => 'Revision ' . sprintf('%02d', $rev) . ' saved, new PDF generated and re-sent. Old PDF kept.'];
    }
    fail('Unknown action');
});

/* ----- reports ----- */
api('GET', 'reports', [...R_INT, 'client', 'vendor'], function () {
    [$sc, $sp] = job_scope('j');
    $F = ['client' => (int)in_('client'), 'vendor' => (int)in_('vendor'), 'insp' => (int)in_('insp'), 'q' => trim((string)in_('q')), 'res' => (string)in_('res'),
        'from' => (string)in_('from', date('Y-m-01', strtotime('-2 months'))), 'to' => (string)in_('to', today())];
    $sql = "SELECT " . img_item_sql() . " img, i.id, i.report_no, i.rev, i.sku, i.final_result, i.concession_status, i.approved_at, i.inspected_qty, i.rejected_qty, j.po_no, j.job_no, j.id jid, j.status jstatus, c.code client_code, v.name vendor_name, u.emp_code insp_code,
        (SELECT COUNT(*) FROM email_log e WHERE e.item_id=i.id OR (e.job_id=j.id AND e.item_id IS NULL)) emails, (SELECT COUNT(*) FROM qc_items x WHERE x.job_id=j.id) nsku
        FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id JOIN clients c ON c.id=j.client_id JOIN vendors v ON v.id=j.vendor_id LEFT JOIN users u ON u.id=j.inspector_id WHERE i.status='APPROVED' AND $sc";
    $p = $sp;
    if ($F['client']) { $sql .= ' AND j.client_id=?'; $p[] = $F['client']; }
    if ($F['vendor']) { $sql .= ' AND j.vendor_id=?'; $p[] = $F['vendor']; }
    if ($F['insp']) { $sql .= ' AND j.inspector_id=?'; $p[] = $F['insp']; }
    if ($F['res'] === 'NOTPASS') $sql .= " AND i.final_result IN ('FAIL','CONDITIONAL')"; elseif ($F['res']) { $sql .= ' AND i.final_result=?'; $p[] = $F['res']; }
    if ($F['q']) { $sql .= ' AND (j.po_no LIKE ? OR i.sku LIKE ? OR i.report_no LIKE ?)'; array_push($p, "%{$F['q']}%", "%{$F['q']}%", "%{$F['q']}%"); }
    if ($F['from']) { $sql .= ' AND i.approved_at>=?'; $p[] = $F['from'] . ' 00:00:00'; }
    if ($F['to']) { $sql .= ' AND i.approved_at<=?'; $p[] = $F['to'] . ' 23:59:59'; }
    $rows = all($sql . ' ORDER BY i.approved_at DESC LIMIT 5000', $p);
    $s = ['n' => count($rows), 'pass' => 0, 'fail' => 0, 'cond' => 0, 'insp' => 0, 'rej' => 0];
    foreach ($rows as $r) { $k = ['PASS' => 'pass', 'FAIL' => 'fail'][$r['final_result']] ?? 'cond'; $s[$k]++; $s['insp'] += $r['inspected_qty']; $s['rej'] += $r['rejected_qty']; }
    $s['rate'] = $s['insp'] ? $s['rej'] * 100 / $s['insp'] : 0;
    $jids = array_unique(array_map('intval', array_column($rows, 'jid')));
    return ['rows' => $rows, 'filters' => $F, 'stats' => $s, 'emails' => $jids ? (int)val('SELECT COUNT(*) FROM email_log WHERE job_id IN (' . implode(',', $jids) . ')') : 0, 'pos' => count($jids),
        'revisions' => is_internal() ? all("SELECT a.*, i.report_no, u.name uname FROM audit_log a JOIN qc_items i ON i.id=a.entity_id LEFT JOIN users u ON u.id=a.user_id WHERE a.entity='item' AND a.action IN ('REVISION','UNLOCK') ORDER BY a.id DESC LIMIT 8") : []];
});
api('GET', 'report', [...R_INT, 'client', 'vendor'], function () {
    $it = one('SELECT * FROM qc_items WHERE id=?', [(int)in_('id')]); $job = $it ? job_load((int)$it['job_id']) : null;
    if (!$job || !can_see_job($job) || $it['status'] !== 'APPROVED') fail('Report not available', 404);
    $it['img'] = item_img((int)$it['id']);
    $rej = (int)$it['rejected_qty'];
    $versions = is_internal() ? array_map('basename', glob(UPLOAD_DIR . '/reports/' . file_safe((string)$it['report_no']) . '*.pdf') ?: []) : [];
    return ['item' => $it, 'job' => $job, 'admin_email' => setting('admin_email'), 'mail_off' => setting('mail_transport', 'none') === 'none',
        'emails' => is_internal() ? all('SELECT * FROM email_log WHERE item_id=? OR (job_id=? AND item_id IS NULL) ORDER BY id DESC', [$it['id'], $job['id']]) : [],
        'audit' => is_internal() ? all("SELECT a.*, u.name uname FROM audit_log a LEFT JOIN users u ON u.id=a.user_id WHERE a.entity='item' AND a.entity_id=? ORDER BY a.id", [$it['id']]) : [],
        'versions' => $versions, 'po_pdf' => in_array($job['status'], ['EMAILED', 'CLOSED', 'APPROVED'], true),
        'subject' => "QC Inspection Report | {$job['client_code']} | PO {$job['po_no']} | {$it['sku']}",
        'message' => "Dear Sir/Madam,\n\nPlease find attached the QC inspection report {$it['report_no']} for PO {$job['po_no']}.\nResult: " . ($it['final_result'] === 'CONDITIONAL' ? 'CONDITIONAL PASS' : $it['final_result']) . ($rej ? " ($rej pcs rejected/repair)" : '') . '.'];
});

/* ----- approvals (SKU holds + concessions) ----- */
api('GET', 'approvals', ['client', 'vendor', 'super_admin', 'qc_manager'], function () {
    [$sc, $sp] = job_scope('j'); $r = role();
    $hw = $r === 'client' ? " AND i.hold_for='client'" : ($r === 'vendor' ? " AND i.hold_for='vendor'" : '');
    $holds = all("SELECT " . img_item_sql() . " img, i.id, i.sku, i.product, i.hold_for, i.hold_reason, i.hold_by, i.hold_at, j.po_no, j.job_no, j.id jid, c.code client_code, v.name vendor_name FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id JOIN clients c ON c.id=j.client_id JOIN vendors v ON v.id=j.vendor_id
        WHERE $sc AND i.hold_status='PENDING' $hw ORDER BY i.hold_at", $sp);
    foreach ($holds as &$h) $h['photos'] = array_map(fn($p) => url('file', ['p' => $p['path']]), all('SELECT path FROM item_photos WHERE item_id=? ORDER BY id DESC LIMIT 4', [$h['id']]));
    unset($h);
    $conc = $r === 'vendor' ? [] : all("SELECT " . img_item_sql() . " img, i.id, i.report_no, i.sku, i.inspected_qty, i.manager_note, i.deviation_json, i.approved_at, i.concession_due, j.po_no, c.code client_code, v.name vendor_name, u.name mgr FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id JOIN clients c ON c.id=j.client_id JOIN vendors v ON v.id=j.vendor_id LEFT JOIN users u ON u.id=i.approved_by
        WHERE $sc AND i.concession_status='PENDING' ORDER BY i.approved_at", $sp);
    foreach ($conc as &$c) $c['deviation'] = jdec($c['deviation_json']);
    unset($c);
    $hist = all("SELECT i.id, i.report_no, i.sku, i.hold_for, i.hold_status, i.hold_response, i.hold_resp_at, i.concession_status, i.concession_remark, i.concession_at, v.name vendor_name, c.code client_code
        FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id JOIN vendors v ON v.id=j.vendor_id JOIN clients c ON c.id=j.client_id
        WHERE $sc AND ((i.hold_status IN ('APPROVED','REJECTED')$hw) OR (i.concession_status IN ('APPROVED','REJECTED') " . ($r === 'vendor' ? 'AND 1=0' : '') . ")) ORDER BY COALESCE(i.concession_at, i.hold_resp_at) DESC LIMIT 20", $sp);
    return ['holds' => $holds, 'concessions' => $conc, 'history' => $hist, 'me' => user()['name']];
});

/* ----- analytics ----- */
function analytics_data(): array {
    $vf = (int)in_('vendor'); $cf = role() === 'client' ? (int)user()['client_id'] : (int)in_('client');
    $period = (string)in_('period', date('Y-m'));
    $from = $period === 'all' ? null : $period . '-01'; $to = $period === 'all' ? null : date('Y-m-t', strtotime($period . '-01'));
    $all = approved_items(array_filter(['client_id' => $cf ?: null]));
    $inP = fn($i) => !$from || ($i['approved_at'] >= $from && $i['approved_at'] <= $to . ' 23:59:59');
    $items = array_values(array_filter($all, fn($i) => (!$vf || $i['vendor_id'] == $vf) && $inP($i)));
    $periodItems = array_values(array_filter($all, $inP));
    $prev = [];
    if ($from) { $pf = date('Y-m-01', strtotime($from . ' -1 month')); foreach (vendor_rows(array_values(array_filter($all, fn($i) => $i['approved_at'] >= $pf && $i['approved_at'] < $from))) as $r) $prev[$r['vendor_id']] = $r['rate']; }
    $vrows = vendor_rows($periodItems); foreach ($vrows as &$r) { $r['prev'] = $prev[$r['vendor_id']] ?? null; } unset($r);
    $months = [['v' => 'all', 'l' => 'All time']]; for ($k = 0; $k < 12; $k++) { $m = date('Y-m', strtotime(date('Y-m-01') . " -$k months")); $months[] = ['v' => $m, 'l' => date('F Y', strtotime("$m-01"))]; }
    $o = ['period' => $period, 'months' => $months, 'stats' => sum_items($items), 'pareto' => defect_pareto(array_column($items, 'id')),
        'trend' => month_trend(array_values($vf ? array_filter($all, fn($i) => $i['vendor_id'] == $vf) : $all)), 'vendors' => $vrows,
        'vendor_opts' => role() === 'client' ? all('SELECT v.id, v.name FROM vendors v JOIN client_vendor m ON m.vendor_id=v.id WHERE m.client_id=?', [$cf]) : all('SELECT id, name FROM vendors ORDER BY name')];
    $o['skus'] = array_map(fn($i) => ['id' => (int)$i['id'], 'po' => $i['po_no'], 'job_no' => $i['job_no'], 'job_id' => (int)$i['job_id'], 'sku' => $i['sku'], 'lot' => (int)($i['lot_seq'] ?: 1), 'vendor' => $i['vendor_name'], 'client' => $i['client_code'],
        'date' => substr((string)$i['approved_at'], 0, 10), 'ready' => (int)$i['ready_qty'], 'insp' => (int)$i['inspected_qty'], 'acc' => (int)$i['accepted_qty'], 'rej' => (int)$i['rejected_qty'], 'result' => $i['final_result'], 'report_no' => $i['report_no']], array_reverse($items));
    $rj = array_values(array_filter($items, fn($i) => (int)$i['rejected_qty'] > 0)); $dmap = [];
    if ($rj) foreach (all('SELECT item_id, type, severity, qty FROM item_defects WHERE item_id IN (' . implode(',', array_map(fn($i) => (int)$i['id'], $rj)) . ')') as $d) $dmap[$d['item_id']][] = $d['type'] . ' ×' . $d['qty'] . ' (' . strtolower($d['severity']) . ')';
    $o['rejects'] = array_map(fn($i) => ['id' => (int)$i['id'], 'po' => $i['po_no'], 'sku' => $i['sku'], 'vendor' => $i['vendor_name'], 'insp' => (int)$i['inspected_qty'], 'rej' => (int)$i['rejected_qty'], 'result' => $i['final_result'], 'report_no' => $i['report_no'],
        'defects' => implode(', ', $dmap[$i['id']] ?? []) ?: '—'], array_reverse($rj));
    if (is_internal()) { $o['inspectors'] = []; foreach (all("SELECT id, name, emp_code FROM users WHERE role='inspector' AND status='ACTIVE' ORDER BY emp_code") as $u) $o['inspectors'][] = $u + inspector_kpis((int)$u['id']); }
    return $o;
}
api('GET', 'analytics', [...R_INT, 'client'], fn() => analytics_data());
api('GET', 'analytics/export', [...R_INT, 'client'], function () {
    $d = analytics_data();
    header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="vendor_quality_' . $d['period'] . '.csv"');
    $o = fopen('php://output', 'w'); fputcsv($o, ['Vendor', 'Client(s)', 'Inspections', 'Pass', 'Fail', 'Conditional', 'Inspected pcs', 'Rejected pcs', 'Rejection %', 'Top defect'], ',', '"', '\\');
    foreach ($d['vendors'] as $r) fputcsv($o, [$r['vendor'], $r['clients'], $r['n'], $r['pass'], $r['fail'], $r['cond'], $r['insp'], $r['rej'], round($r['rate'], 2), $r['top']], ',', '"', '\\');
    exit;
});

/* ----- bulk assign (single-design job cards from Excel) ----- */
api('POST', 'jobs/bulk_assign', ['super_admin', 'qc_manager', 'back_office'], function () {
    $ids = array_values(array_unique(array_map('intval', (array)in_('ids', []))));
    $ins = (int)in_('inspector_id'); $date = in_('date') ?: null;
    if (!$ids) fail('Select at least one job.');
    if (in_('unassign')) {   // bulk: move selected jobs back to the Unassigned pool
        $done = 0; $skip = [];
        foreach ($ids as $id) { $job = job_load($id); if (!$job || !$job['inspector_id']) continue;
            if ($why = unassign_block($job, (bool)in_('force'))) { $skip[] = $job['job_no']; continue; }
            unassign_job($job); $done++; }
        return ['ok' => true, 'message' => "$done job(s) moved to Unassigned" . ($skip ? ' · not changed (inspection started / submitted): ' . implode(', ', array_slice($skip, 0, 10)) . (is_role('super_admin') ? ' — use force to override' : '') : '') . '.'];
    }
    if (!$ins) fail('Select an inspector (or use Unassign selected).');
    if ($ins && ($lv = on_leave($ins, (string)($date ?: today())))) fail("This inspector is on leave that day ($lv).");
    $ip = $ins ? one("SELECT id, name, emp_code FROM users WHERE id=? AND role='inspector' AND status='ACTIVE'", [$ins]) : null;
    if ($ins && !$ip) fail('Invalid inspector');
    $done = 0; $skip = []; $pos = [];
    foreach ($ids as $id) {
        $job = job_load($id);
        if (!$job || !in_array($job['status'], ['REQUESTED', 'SCHEDULED', 'ASSIGNED', 'IN_PROGRESS', 'PARTIAL'], true)) { $skip[] = $job['job_no'] ?? $id; continue; }
        $d = ['inspector_id' => $ins ?: null, 'assigned_at' => now(), 'manager_id' => $job['manager_id'] ?: (mgr_on() ? null : user()['id'])];
        if ($date) $d['scheduled_date'] = $date; elseif (!$job['scheduled_date']) $d['scheduled_date'] = $job['requested_date'] ?: today();
        if (!$job['job_no']) $d['job_no'] = next_job_no();
        if ($ins && in_array($job['status'], ['REQUESTED', 'SCHEDULED'], true)) $d['status'] = 'ASSIGNED';
        if (!$ins && $job['status'] === 'ASSIGNED') $d['status'] = 'SCHEDULED';
        update('qc_jobs', $d, 'id=?', [$id]);
        audit('job', $id, 'ASSIGNED', 'inspector', $job['inspector_code'], $ip['emp_code'] ?? null);
        $done++; $pos[$job['po_no']] = true;
    }
    if ($ip && $done) notify($ins, "$done QC job(s) assigned to you · PO " . implode(', ', array_keys($pos)) . ($date ? ' · ' . fdate($date) : ''), 'app');
    return ['ok' => true, 'message' => "$done job(s) " . ($ip ? "assigned to {$ip['emp_code']} · {$ip['name']}" : 'unassigned') . ($skip ? ' · skipped (closed/under review): ' . implode(', ', array_slice($skip, 0, 8)) : '') . '.'];
});


/** Super Admin: permanently delete job card(s) — designs, inspection data, photos, defects, files and PDFs. Reason is kept in the audit log. */
function delete_job(int $id, string $reason): ?string {
    $job = job_load($id); if (!$job) return null;
    $items = all('SELECT id, pdf_path, report_no FROM qc_items WHERE job_id=?', [$id]); $iids = array_map(fn($i) => (int)$i['id'], $items);
    // burn the numbers so they are never issued again
    if ($job['job_no'] && preg_match('/^JS-(\d{4})-(\d+)$/', $job['job_no'], $m)) set_setting('seq_job_' . $m[1], (string)max((int)setting('seq_job_' . $m[1], '0'), (int)$m[2]));
    if ($job['req_no'] && preg_match('/(\d+)$/', $job['req_no'], $m)) set_setting('seq_req', (string)max((int)setting('seq_req', '0'), (int)$m[1]));
    foreach ($items as $i) if ($i['report_no'] && preg_match('#^(.*/)(\d+)$#', $i['report_no'], $m)) set_setting('seq_rep_' . md5($m[1]), (string)max((int)setting('seq_rep_' . md5($m[1]), '0'), (int)$m[2]));
    $files = [];
    if ($iids) {
        $in = implode(',', $iids);
        foreach (all("SELECT path FROM item_photos WHERE item_id IN ($in)") as $p) $files[] = $p['path'];
        q("DELETE FROM item_photos WHERE item_id IN ($in)"); q("DELETE FROM item_defects WHERE item_id IN ($in)");
        foreach ($items as $i) if ($i['pdf_path']) $files[] = $i['pdf_path'];
    }
    foreach (all('SELECT path FROM attachments WHERE job_id=?', [$id]) as $a) $files[] = $a['path'];
    if (!empty($job['po_pdf'])) $files[] = $job['po_pdf'];
    q('DELETE FROM attachments WHERE job_id=?', [$id]); q('DELETE FROM qc_items WHERE job_id=?', [$id]); q('DELETE FROM manual_acks WHERE job_id=?', [$id]);
    q('UPDATE checkins SET out_at=COALESCE(out_at, ?) WHERE job_id=?', [now(), $id]);   // visit history stays, open check-in on this job is closed
    q("DELETE FROM notifications WHERE link IN (?, ?, ?)", ['request/' . $id, 'app/job/' . $id, 'jobs?id=' . $id]);
    if ($iids) q('DELETE FROM notifications WHERE ' . implode(' OR ', array_map(fn($x) => "link='report/$x' OR link='review/$x' OR link='app/inspect/$x'", $iids)));
    q('DELETE FROM qc_jobs WHERE id=?', [$id]);
    foreach (array_unique(array_filter($files)) as $f) {   // remove files no other record still uses
        if (str_contains($f, '..')) continue;
        if (!val('SELECT COUNT(*) FROM item_photos WHERE path=?', [$f]) && !val('SELECT COUNT(*) FROM attachments WHERE path=?', [$f]) && is_file(UPLOAD_DIR . '/' . $f)) @unlink(UPLOAD_DIR . '/' . $f);
    }
    $label = ($job['job_no'] ?: $job['req_no']) . " · PO {$job['po_no']} · {$job['client_code']} – {$job['vendor_name']} · " . count($iids) . ' design(s) · status ' . $job['status'];
    audit('job', $id, 'DELETED', $label, null, $reason);
    if ($job['inspector_id'] && !in_array($job['status'], ['APPROVED', 'EMAILED', 'CLOSED', 'CANCELLED'], true)) notify((int)$job['inspector_id'], 'Job card ' . ($job['job_no'] ?: $job['req_no']) . ' was deleted by Super Admin', 'app');
    return $job['job_no'] ?: $job['req_no'];
}
api('POST', 'jobs/delete', ['super_admin'], function () {
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)(in_('ids') ?: [in_('id')])))));
    $reason = trim((string)in_('reason'));
    if (!$ids) fail('Select at least one job card.');
    if (mb_strlen($reason) < 3) fail('Write a reason for deleting (kept in the audit log).');
    $done = [];
    db()->beginTransaction();
    try { foreach ($ids as $id) if ($n = delete_job($id, $reason)) $done[] = $n; db()->commit(); }
    catch (Throwable $e) { db()->rollBack(); throw $e; }
    if (!$done) fail('Job card not found.', 404);
    return ['ok' => true, 'deleted' => $done, 'message' => count($done) . ' job card(s) deleted permanently: ' . implode(', ', $done) . '.'];
});
