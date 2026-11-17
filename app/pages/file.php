<?php
// Serves uploaded files after checking the user may see the related job.
$p = (string)get('p');
if (!preg_match('#^[a-z0-9_\-/]+\.[a-z0-9]+$#i', $p) || str_contains($p, '..')) { http_response_code(404); exit; }
$full = UPLOAD_DIR . '/' . $p;
if (!is_file($full)) { http_response_code(404); exit('Not found'); }
$jobId = null;
if ($r = one('SELECT j.* FROM attachments a JOIN qc_jobs j ON j.id=a.job_id WHERE a.path=?', [$p])) $jobId = $r;
elseif ($r = one('SELECT j.* FROM item_photos ph JOIN qc_items i ON i.id=ph.item_id JOIN qc_jobs j ON j.id=i.job_id WHERE ph.path=?', [$p])) $jobId = $r;
if ($jobId && !can_see_job($jobId)) { http_response_code(403); exit; }
$mime = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'csv' => 'text/csv'][strtolower(pathinfo($full, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
header('Content-Type: ' . $mime); header('Cache-Control: private, max-age=86400');
if (get('dl')) header('Content-Disposition: attachment; filename="' . basename($full) . '"');
readfile($full);
