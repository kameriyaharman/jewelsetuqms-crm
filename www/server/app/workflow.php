<?php
require_once __DIR__ . '/pdf.php';
require_once __DIR__ . '/mailer.php';

/** QC manager approval: lock, number, generate immutable PDF. */
function approve_item(int $itemId, string $final, string $note, array $deviation = []): array {
    $it = one('SELECT * FROM qc_items WHERE id=?', [$itemId]); $job = job_load((int)$it['job_id']);
    $no = $it['report_no'] ?: next_report_no($job);
    $d = ['status' => 'APPROVED', 'final_result' => $final, 'manager_note' => $note, 'report_no' => $no, 'locked' => 1,
        'approved_at' => now(), 'approved_by' => user()['id'], 'return_sections' => null];
    if ($final === 'CONDITIONAL') {
        $d['deviation_json'] = json_encode(array_values(array_filter($deviation, fn($x) => trim((string)($x['param'] ?? '')) !== '')));
        $d['concession_status'] = 'PENDING'; $d['concession_due'] = date('Y-m-d', strtotime('+2 days'));
    }
    update('qc_items', $d, 'id=?', [$itemId]);
    audit('item', $itemId, 'APPROVED', 'final_result', $it['system_result'], $final);
    audit('item', $itemId, 'REPORT_GENERATED', 'Report generated', null, $no);
    item_pdf($itemId, true);
    notify((int)$job['inspector_id'], "Report approved · $no", 'app/history');
    if ($final === 'CONDITIONAL') {
        notify_role('client', "Concession approval needed · $no · {$job['vendor_name']}", 'approvals', ['client_id' => $job['client_id']]);
        audit('item', $itemId, 'CONCESSION_SENT', 'Sent to client for concession');
    }
    refresh_job_status((int)$job['id']);
    finalize_job_if_ready((int)$job['id']);
    return ['report_no' => $no];
}

/** When every SKU is approved: PO-wise PDF, auto email, publish → EMAILED. */
function finalize_job_if_ready(int $jobId): void {
    $job = job_load($jobId);
    if ($job['status'] !== 'APPROVED') return;
    if (!$job['approved_at']) q('UPDATE qc_jobs SET approved_at=? WHERE id=?', [now(), $jobId]);
    [, $name, $file] = job_pdf($jobId, true);
    // PO split into several job cards (one per design): email the consolidated PO report once, when the last card is complete
    $gids = po_group_ids($job); $in = implode(',', $gids);
    if ((int)val("SELECT COUNT(*) FROM qc_jobs WHERE id IN ($in) AND status NOT IN ('APPROVED','EMAILED','CLOSED')")) {
        audit('job', $jobId, 'QC_COMPLETE', 'PO report', null, 'Waiting for other job cards of PO ' . $job['po_no']);
        return;
    }
    if (count($gids) > 1 && (int)val("SELECT COUNT(*) FROM qc_jobs WHERE id IN ($in) AND emailed_at IS NOT NULL AND status IN ('EMAILED','CLOSED')") === count($gids)) return;
    if (setting('auto_email_on_approve', '1') === '1') {
        $items = all("SELECT * FROM qc_items WHERE job_id IN ($in)");
        $res = array_column($items, 'final_result');
        $overall = in_array('FAIL', $res, true) ? 'FAIL' : (in_array('CONDITIONAL', $res, true) ? 'CONDITIONAL PASS' : 'PASS');
        $rej = array_sum(array_column($items, 'rejected_qty'));
        $subject = "QC Inspection Report | {$job['client_code']} | PO {$job['po_no']} | " . (count($items) === 1 ? $items[0]['sku'] : count($items) . ' designs');
        $msg = "Dear Sir/Madam,\n\nPlease find attached the QC inspection report for PO {$job['po_no']} ({$job['vendor_name']}).\nResult: $overall" . ($rej ? " ($rej pcs rejected)" : '') . ".\n\nReport numbers: " . implode(', ', array_column($items, 'report_no'));
        email_report($job, null, $file, $subject, $msg);
    }
    foreach (all("SELECT id FROM qc_jobs WHERE id IN ($in) AND status='APPROVED'") as $g) { q("UPDATE qc_jobs SET status='EMAILED', emailed_at=? WHERE id=?", [now(), $g['id']]); audit('job', (int)$g['id'], 'STATUS', 'status', 'APPROVED', 'EMAILED'); }
    notify_job_parties($job, "QC report published · PO {$job['po_no']} · " . ($job['job_no'] ?? ''), 'reports');
}
