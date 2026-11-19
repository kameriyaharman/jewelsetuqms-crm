<?php
/* =============================================================================
 * QC Manuals & Instructions: client brand manuals / SOPs / defect boundary charts /
 * special PO notes, and vendor factory spec sheets — versioned, scoped to all vendors,
 * one vendor or one PO, shown on job cards and in the inspector app (with acknowledgement).
 * ========================================================================== */

const MANUAL_EXT = ['pdf', 'xlsx', 'xls', 'docx', 'doc', 'jpg', 'jpeg', 'png', 'webp'];

/** SQL condition + params for manuals the current user may see. */
function manual_scope_sql(string $a = 'm'): array {
    $u = user();
    if (role() === 'client') return ["$a.client_id=?", [(int)$u['client_id']]];
    if (role() === 'vendor') return ["$a.client_id IN (SELECT client_id FROM client_vendor WHERE vendor_id=?) AND ($a.vendor_id IS NULL OR $a.vendor_id=?)", [(int)$u['vendor_id'], (int)$u['vendor_id']]];
    if (role() === 'inspector') return ["EXISTS (SELECT 1 FROM qc_jobs jj WHERE (jj.inspector_id=? OR jj.inspector2_id=?) AND jj.client_id=$a.client_id AND ($a.vendor_id IS NULL OR $a.vendor_id=jj.vendor_id))", [(int)$u['id'], (int)$u['id']]];
    return ['1=1', []];
}
function manual_can_edit(array $m): bool {
    if (is_role('super_admin', 'back_office', 'qc_manager')) return true;
    if (role() === 'client') return $m['uploader_role'] === 'client' && (int)$m['client_id'] === (int)user()['client_id'];
    if (role() === 'vendor') return $m['uploader_role'] === 'vendor' && (int)$m['vendor_id'] === (int)user()['vendor_id'];
    return false;
}
function manual_row(array $m): array {
    $age = (time() - strtotime((string)$m['created_at'])) / 86400;
    $ext = strtolower(pathinfo((string)$m['file_name'], PATHINFO_EXTENSION));
    return $m + ['badge' => $age <= 7 ? ((float)$m['version'] > 1 ? 'UPDATED' : 'NEW') : null, 'ext' => $ext, 'can_edit' => manual_can_edit($m),
        'scope_label' => $m['scope'] === 'VENDOR' ? 'Only ' . ($m['vendor_name'] ?? 'vendor') : ($m['scope'] === 'PO' ? 'PO ' . $m['po_no'] . ($m['vendor_name'] ? ' · ' . $m['vendor_name'] : '') : 'All vendors (master manual)')];
}
/** Active manuals that apply to a job (client + vendor + PO). */
function job_manuals(array $job): array {
    $rows = all("SELECT m.*, c.code client_code, v.name vendor_name, u.name uploader FROM manuals m JOIN clients c ON c.id=m.client_id LEFT JOIN vendors v ON v.id=m.vendor_id LEFT JOIN users u ON u.id=m.uploaded_by
        WHERE m.status='ACTIVE' AND m.client_id=? AND (m.vendor_id IS NULL OR m.vendor_id=?) AND (m.scope<>'PO' OR m.po_no=?) ORDER BY CASE WHEN m.priority='HIGH' THEN 0 ELSE 1 END, m.created_at DESC",
        [$job['client_id'], $job['vendor_id'], $job['po_no']]);
    return array_map('manual_row', $rows);
}
function manual_acked(int $jobId, int $uid, array $mans): bool {
    $high = array_filter($mans, fn($m) => $m['priority'] === 'HIGH'); if (!$high) return true;
    $ids = implode(',', array_map(fn($m) => (int)$m['id'], $high));
    return (int)val("SELECT COUNT(DISTINCT manual_id) FROM manual_acks WHERE user_id=? AND job_id=? AND manual_id IN ($ids)", [$uid, $jobId]) === count($high);
}

api('GET', 'manuals', R_ALL, function () {
    [$w, $p] = manual_scope_sql();
    $cf = (int)in_('client'); if ($cf) { $w .= ' AND m.client_id=?'; $p[] = $cf; }
    $vf = (int)in_('vendor'); if ($vf) { $w .= ' AND (m.vendor_id IS NULL OR m.vendor_id=?)'; $p[] = $vf; }
    $all = all("SELECT m.*, c.code client_code, c.name client_name, v.name vendor_name, u.name uploader FROM manuals m JOIN clients c ON c.id=m.client_id LEFT JOIN vendors v ON v.id=m.vendor_id
        LEFT JOIN users u ON u.id=m.uploaded_by WHERE m.status IN ('ACTIVE','ARCHIVED') AND $w ORDER BY m.created_at DESC", $p);
    $active = array_values(array_filter($all, fn($m) => $m['status'] === 'ACTIVE'));
    $hist = []; foreach ($all as $m) if ($m['status'] === 'ARCHIVED') $hist[$m['group_id']][] = manual_row($m);
    $rows = array_map(fn($m) => manual_row($m) + ['history' => $hist[$m['group_id']] ?? []], $active);
    $u = user(); $opts = [];
    if (role() === 'client') $opts = ['clients' => all('SELECT id, code, name FROM clients WHERE id=?', [$u['client_id']]), 'vendors' => all('SELECT v.id, v.name, m.client_id FROM client_vendor m JOIN vendors v ON v.id=m.vendor_id WHERE m.client_id=? ORDER BY v.name', [$u['client_id']])];
    elseif (role() === 'vendor') $opts = ['clients' => all('SELECT c.id, c.code, c.name FROM client_vendor m JOIN clients c ON c.id=m.client_id WHERE m.vendor_id=? ORDER BY c.code', [$u['vendor_id']]), 'vendors' => []];
    elseif (is_internal()) $opts = ['clients' => all('SELECT id, code, name FROM clients ORDER BY code'), 'vendors' => all('SELECT v.id, v.name, m.client_id FROM client_vendor m JOIN vendors v ON v.id=m.vendor_id ORDER BY v.name')];
    return ['rows' => $rows, 'opts' => $opts, 'can_upload' => in_array(role(), ['client', 'vendor', 'super_admin', 'back_office', 'qc_manager'], true), 'role' => role()];
});

api('POST', 'manuals/save', ['client', 'vendor', 'super_admin', 'back_office', 'qc_manager'], function () {
    $D = json_decode((string)($_POST['data'] ?? ''), true) ?: inp(); $u = user();
    $old = !empty($D['id']) ? one('SELECT * FROM manuals WHERE id=?', [(int)$D['id']]) : null;
    if (!empty($D['id']) && (!$old || !manual_can_edit($old))) fail('Not allowed', 403);
    $cid = role() === 'client' ? (int)$u['client_id'] : (int)($D['client_id'] ?? ($old['client_id'] ?? 0));
    if (!$cid) fail('Select the client this manual / instruction is for.');
    if (role() === 'vendor' && !one('SELECT 1 FROM client_vendor WHERE client_id=? AND vendor_id=?', [$cid, $u['vendor_id']])) fail('This client is not mapped to you.');
    $scope = (string)($D['scope'] ?? 'ALL'); if (!in_array($scope, ['ALL', 'VENDOR', 'PO'], true)) $scope = 'ALL';
    $vid = role() === 'vendor' ? (int)$u['vendor_id'] : ($scope === 'ALL' ? null : ((int)($D['vendor_id'] ?? 0) ?: null));
    if (role() === 'vendor' && $scope === 'ALL') $scope = 'VENDOR';
    if ($scope === 'VENDOR' && !$vid) fail('Select the vendor.');
    $po = $scope === 'PO' ? trim((string)($D['po_no'] ?? '')) : null; if ($scope === 'PO' && $po === '') fail('Enter the PO no.');
    $title = trim((string)($D['title'] ?? '')); $note = trim((string)($D['note'] ?? ''));
    $f = $_FILES['file'] ?? null; $has = $f && $f['error'] === UPLOAD_ERR_OK;
    if ($title === '') fail('Give the manual / instruction a title.');
    if (!$has && $note === '' && !($old && $old['path'])) fail('Upload a file or type the instruction text.');
    $path = $old['path'] ?? null; $fname = $old['file_name'] ?? null;
    if ($has) { $sv = save_upload($f, 'manuals', MANUAL_EXT); if (!$sv) fail('Allowed files: PDF, Excel, Word, JPG / PNG.'); $path = $sv['path']; $fname = $f['name']; }
    $row = ['client_id' => $cid, 'vendor_id' => $vid, 'po_no' => $po, 'scope' => $scope, 'title' => $title, 'note' => $note, 'path' => $path, 'file_name' => $fname,
        'priority' => ($D['priority'] ?? '') === 'HIGH' ? 'HIGH' : 'NORMAL', 'uploader_role' => in_array(role(), ['client', 'vendor'], true) ? role() : ($old['uploader_role'] ?? 'client'),
        'uploaded_by' => $u['id'], 'status' => 'ACTIVE', 'created_at' => now()];
    if ($old) {   // new version, old one archived (kept in history)
        q("UPDATE manuals SET status='ARCHIVED' WHERE id=?", [$old['id']]);
        $v = (float)$old['version']; $row['version'] = number_format($v + 0.1, 1); $row['group_id'] = $old['group_id'];
        $id = insert('manuals', $row); audit('manual', $id, 'UPDATE', 'version', $old['version'], $row['version']);
    } else { $row['version'] = '1.0'; $id = insert('manuals', $row); q('UPDATE manuals SET group_id=? WHERE id=?', [$id, $id]); audit('manual', $id, 'CREATE', 'title', null, $title); }
    // tell the other side + inspectors with open jobs for this client
    $who = role() === 'vendor' ? val('SELECT name FROM vendors WHERE id=?', [$u['vendor_id']]) : val('SELECT code FROM clients WHERE id=?', [$cid]);
    $msg = '📘 QC manual ' . ($old ? 'updated (v' . $row['version'] . ')' : 'added') . " by $who: $title";
    if (role() !== 'vendor') foreach (all('SELECT u.id FROM users u JOIN client_vendor m ON m.vendor_id=u.vendor_id WHERE u.role=\'vendor\' AND m.client_id=?' . ($vid ? ' AND u.vendor_id=' . (int)$vid : ''), [$cid]) as $x) notify((int)$x['id'], $msg, 'manuals');
    if (role() !== 'client') notify_role('client', $msg, 'manuals', ['client_id' => $cid]);
    foreach (all("SELECT DISTINCT inspector_id FROM qc_jobs WHERE client_id=? AND inspector_id IS NOT NULL AND status IN ('ASSIGNED','IN_PROGRESS','PARTIAL')" . ($vid ? ' AND vendor_id=' . (int)$vid : ''), [$cid]) as $x) notify((int)$x['inspector_id'], $msg, 'app');
    notify_role('back_office', $msg, 'manuals');
    return ['ok' => true, 'id' => $id, 'message' => $old ? "Updated — now version {$row['version']} (old version archived)." : 'Saved and shared.'];
});
api('POST', 'manuals/delete', ['client', 'vendor', 'super_admin', 'back_office', 'qc_manager'], function () {
    $m = one('SELECT * FROM manuals WHERE id=?', [(int)in_('id')]); if (!$m || !manual_can_edit($m)) fail('Not allowed', 403);
    q("UPDATE manuals SET status='DELETED' WHERE group_id=?", [$m['group_id']]); audit('manual', (int)$m['id'], 'DELETE', 'title', $m['title'], null);
    return ['ok' => true, 'message' => 'Removed.'];
});
api('GET', 'manuals/job', R_ALL, function () {
    $job = job_load((int)in_('id')); if (!$job || !can_see_job($job)) fail('Not found', 404);
    $m = job_manuals($job);
    return ['rows' => $m, 'acked' => manual_acked((int)$job['id'], (int)user()['id'], $m)];
});
api('POST', 'manuals/ack', ['inspector'], function () {
    $job = job_load((int)in_('job_id')); if (!$job || !can_see_job($job)) fail('Not found', 404);
    foreach (job_manuals($job) as $m) if ($m['priority'] === 'HIGH') insert('manual_acks', ['manual_id' => $m['id'], 'user_id' => user()['id'], 'job_id' => $job['id'], 'at' => now()]);
    audit('job', (int)$job['id'], 'MANUAL_ACK', 'QC manual', null, 'Read & acknowledged by ' . user()['emp_code']);
    return ['ok' => true, 'message' => 'Thanks — acknowledgement saved.'];
});
