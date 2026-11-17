<?php
require_once ROOT . '/app/pdf.php';
if ($iid = (int)get('item')) {
    $it = one('SELECT * FROM qc_items WHERE id=?', [$iid]); $job = $it ? job_load((int)$it['job_id']) : null;
    if (!$job || !can_see_job($job)) { http_response_code(404); exit('Not found'); }
    if (!is_internal() && $it['status'] !== 'APPROVED') { http_response_code(403); exit('Report not published yet'); }
    $stored = $it['pdf_path'] ? UPLOAD_DIR . '/' . $it['pdf_path'] : null;
    if ($it['locked'] && $stored && is_file($stored)) { $pdf = file_get_contents($stored); $name = basename($stored); }
    else [$pdf, $name] = item_pdf($iid, (bool)$it['locked']);
} elseif ($jid = (int)get('job')) {
    $job = job_load($jid);
    if (!$job || !can_see_job($job)) { http_response_code(404); exit('Not found'); }
    if (!is_internal() && !in_array($job['status'], ['EMAILED', 'CLOSED', 'APPROVED'], true)) { http_response_code(403); exit('Report not published yet'); }
    $stored = $job['po_pdf'] ? UPLOAD_DIR . '/' . $job['po_pdf'] : null;
    if ($stored && is_file($stored) && !get('fresh')) { $pdf = file_get_contents($stored); $name = basename($stored); }
    else [$pdf, $name] = job_pdf($jid, in_array($job['status'], ['EMAILED', 'CLOSED', 'APPROVED'], true));
} elseif ($old = (string)get('file')) {
    // older revision kept on disk
    if (!preg_match('#^reports/[A-Za-z0-9_\-]+\.pdf$#', $old) || !is_internal()) { http_response_code(404); exit; }
    $pdf = (string)@file_get_contents(UPLOAD_DIR . '/' . $old); $name = basename($old);
} else { http_response_code(400); exit; }
header('Content-Type: application/pdf');
header('Content-Disposition: ' . (get('dl') ? 'attachment' : 'inline') . '; filename="' . $name . '"');
echo $pdf;
