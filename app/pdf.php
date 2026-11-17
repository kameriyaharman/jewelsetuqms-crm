<?php
require_once ROOT . '/lib/dompdf/autoload.inc.php';

function pdf_css(): string {
    return '<style>
    @page{margin:92px 30px 46px 30px}
    body{font-family:"DejaVu Sans",sans-serif;font-size:9px;color:#2b2224}
    header{position:fixed;top:-78px;left:0;right:0;height:62px;background:#2a191c;color:#fff;padding:8px 14px}
    header img{height:44px;float:left;background:#fffaf5;border-radius:5px;padding:2px 6px}
    header .r{float:right;text-align:right}
    header .r b{font-size:12px;letter-spacing:1px;color:#f0d9c9}
    header .r span{font-size:8.5px;color:#d7bfa6}
    footer{position:fixed;bottom:-32px;left:0;right:0;height:20px;font-size:7.5px;color:#85767a;border-top:1px solid #e0d2c6;padding-top:4px}
    
    h3{font-size:10px;color:#94604a;margin:12px 0 5px;text-transform:uppercase;letter-spacing:.6px;border-bottom:1px solid #ecdcd0;padding-bottom:3px}
    table{width:100%;border-collapse:collapse}
    td,th{padding:4px 5px;border:1px solid #ece2d9;vertical-align:top}
    th{background:#faf3ee;color:#6a5558;font-size:7.5px;text-transform:uppercase;letter-spacing:.4px;text-align:left}
    .kv td{border:0;padding:3px 4px;width:25%}.kv small{color:#85767a;font-size:7.5px}.kv b{font-size:9.5px}
    .res{border:2px solid #9b1c2e;border-radius:6px;padding:6px 10px;margin-bottom:6px}
    .res.PASS{border-color:#2f7d55}.res.CONDITIONAL{border-color:#b7791f}
    .big{font-size:20px;font-weight:bold}
    .PASS-t{color:#2f7d55}.FAIL-t{color:#9b1c2e}.CONDITIONAL-t{color:#b7791f}.NA-t{color:#85767a}
    .tag{font-weight:bold;font-size:8px;padding:1px 4px;border-radius:3px}
    .tag.PASS{background:#e2f2e9;color:#2f7d55}.tag.FAIL{background:#f9e3e6;color:#9b1c2e}.tag.NA{background:#eee;color:#777}.tag.CONDITIONAL{background:#fbe6d4;color:#a2501c}
    .ph{display:inline-block;width:30%;margin:0 1% 8px 0;vertical-align:top;border:1px solid #ece2d9;border-radius:4px;overflow:hidden}
    .ph img{width:100%;height:120px}
    .ph div{padding:3px 5px;font-size:7.5px;color:#6a5558}
    .note{background:#fbf6e8;border:1px solid #e8d9ab;padding:6px 8px;border-radius:4px}
    .num{text-align:right}
    .pb{page-break-before:always}
    .wm{position:fixed;top:300px;left:110px;font-size:70px;color:rgba(155,28,46,.06);transform:rotate(-30deg)}
    </style>';
}
function pdf_header(string $title, string $no, int $rev = 0): string {
    $logo = ROOT . '/assets/img/logo-pdf.jpg';
    return '<header><img src="' . $logo . '"><div class="r"><b>' . h($title) . '</b><br><span>Report No. ' . h($no) . ($rev ? ' · REVISION ' . sprintf('%02d', $rev) : ' · Rev 00') . '</span><br><span>Generated ' . date('d-m-Y h:i A') . '</span></div></header>
    <footer><span>' . h(setting('company_name')) . ' · Q-TRACK · ' . h($no) . '</span></footer>';
}
function pass_tag(?string $r): string { $r = $r ?: 'NA'; return '<span class="tag ' . h($r) . '">' . h($r) . '</span>'; }

/** HTML body of one SKU inspection sheet (used for single report and inside PO report). */
function item_sheet(array $job, array $it): string {
    $def = all('SELECT * FROM item_defects WHERE item_id=?', [$it['id']]);
    $photos = all('SELECT * FROM item_photos WHERE item_id=? ORDER BY id', [$it['id']]);
    $checks = jdec($it['checks_json']); $meas = jdec($it['meas_json']); $tests = jdec($it['tests_json']);
    $sev = ['CRITICAL' => 0, 'MAJOR' => 0, 'MINOR' => 0]; foreach ($def as $d) $sev[$d['severity']] += (int)$d['qty'];
    $insp = max(1, (int)$it['inspected_qty']); $res = $it['final_result'] ?: 'PENDING';
    $plan = jdec($it['plan_json']);
    ob_start(); ?>
    <table style="margin-bottom:4px"><tr><th rowspan="2" style="width:34%;background:#fff;border:2px solid <?= $res === 'PASS' ? '#2f7d55' : ($res === 'CONDITIONAL' ? '#b7791f' : '#9b1c2e') ?>"><small>OVERALL RESULT</small><br><span class="big <?= h($res) ?>-t"><?= h($res === 'CONDITIONAL' ? 'CONDITIONAL PASS' : $res) ?></span></th>
      <th>Inspected</th><th>Accepted</th><th>Rejected</th><th>Accepted %</th><th>Rejected %</th></tr>
      <tr><td class="num"><b><?= num($it['inspected_qty']) ?></b></td><td class="num"><?= num($it['accepted_qty']) ?></td><td class="num"><?= num($it['rejected_qty']) ?></td>
      <td class="num"><?= pct($it['accepted_qty'], $insp, 2) ?></td><td class="num"><?= pct($it['rejected_qty'], $insp, 2) ?></td></tr></table>
    <h3>1. General information</h3>
    <table class="kv"><tr><td><small>Client</small><br><b><?= h($job['client_name']) ?></b></td><td><small>Vendor</small><br><b><?= h($job['vendor_name']) ?></b></td><td><small>PO No.</small><br><b><?= h($job['po_no']) ?></b></td><td><small>QC Job</small><br><b><?= h($job['job_no']) ?></b></td></tr>
    <tr><td><small>QC type</small><br><b><?= h($job['qc_type']) ?></b></td><td><small>Inspection date</small><br><b><?= fdate($it['insp_date'] ?: $job['scheduled_date'], 'd-m-Y') ?></b></td><td><small>Inspector</small><br><b><?= h(($job['inspector_code'] ?? '') . ' · ' . ($job['inspector_name'] ?? '')) ?></b></td><td><small>QC scope</small><br><b><?= h(scope_label($job)) ?></b></td></tr>
    <tr><td colspan="2"><small>Location</small><br><b><?= h($job['location']) ?> — <?= h($job['vendor_address']) ?></b></td><td><small>GPS / start</small><br><b><?= h($it['gps'] ?: '—') ?></b></td><td><small>Re-inspection</small><br><b><?= $it['reinspection'] ? 'Yes' : 'No' ?></b></td></tr></table>
    <h3>2. Product details</h3>
    <table class="kv"><tr><td><small>SKU / Design no.</small><br><b><?= h($it['sku']) ?></b></td><td><small>Product</small><br><b><?= h($it['product']) ?></b></td><td><small>Metal</small><br><b><?= h($it['metal']) ?></b></td><td><small>Plating</small><br><b><?= h($it['plating']) ?></b></td></tr>
    <tr><td><small>Target weight</small><br><b><?= h($it['target_weight']) ?></b></td><td><small>Length</small><br><b><?= h($it['length']) ?></b></td><td><small>Lock</small><br><b><?= h($it['lock_type']) ?></b></td><td><small>Stamping</small><br><b><?= h($it['stamping']) ?></b></td></tr></table>
    <h3>3. Quantity inspection</h3>
    <table><tr><th>Order qty</th><th>Ready for QC</th><th>Inspection qty</th><th>Accepted</th><th>Rejected</th><?php if ($plan): ?><th>AQL plan (<?= h($plan['level'] ?? 'II') ?> · <?= h($plan['letter'] ?? '') ?>)</th><?php endif ?></tr>
    <tr><td class="num"><?= num($it['order_qty']) ?></td><td class="num"><?= num($it['ready_qty']) ?></td><td class="num"><?= num($it['inspected_qty']) ?></td><td class="num"><?= num($it['accepted_qty']) ?></td><td class="num"><?= num($it['rejected_qty']) ?></td>
    <?php if ($plan): ?><td><?php foreach (['critical', 'major', 'minor'] as $k) if (!empty($plan[$k]['aql'])) echo ucfirst($k) . ' ' . h($plan[$k]['aql']) . ': Ac ' . $plan[$k]['ac'] . ' / Re ' . $plan[$k]['re'] . '<br>'; ?></td><?php endif ?></tr></table>
    <h3>4. Quality checkpoints</h3>
    <table><tr><th>Checkpoint</th><th style="width:60px">Result</th><th>Remarks</th></tr>
    <?php foreach ($checks as $c): ?><tr><td><?= h($c['name']) ?></td><td><?= pass_tag($c['result'] ?? null) ?></td><td><?= h($c['remark'] ?? '') ?></td></tr><?php endforeach ?></table>
    <h3>5. Measurements</h3>
    <table><tr><th>Parameter</th><th>Specification</th><th>Tolerance</th><th>Actual</th><th style="width:60px">Result</th></tr>
    <?php foreach ($meas as $m): ?><tr><td><?= h($m['param']) ?></td><td><?= h($m['spec'] . ' ' . ($m['unit'] ?? '')) ?></td><td>± <?= h($m['tol']) ?></td><td><?= h($m['actual']) ?></td><td><?= pass_tag($m['result'] ?? null) ?></td></tr><?php endforeach ?></table>
    <h3>6. On-site tests</h3>
    <table><tr><th>Test</th><th>Qty tested</th><th style="width:60px">Result</th><th>Remarks</th></tr>
    <?php foreach ($tests as $t): ?><tr><td><?= h($t['name']) ?></td><td><?= h($t['qty'] ?? '') ?></td><td><?= pass_tag($t['result'] ?? null) ?></td><td><?= h($t['remark'] ?? '') ?></td></tr><?php endforeach ?></table>
    <h3>7. Defect details</h3>
    <?php if (!$def): ?><p>No defects recorded.</p><?php else: ?>
    <table><tr><th>Category</th><th>Defect type</th><th>Severity</th><th class="num">Qty</th><th>Remark</th></tr>
    <?php foreach ($def as $d): ?><tr><td><?= h($d['category']) ?></td><td><?= h($d['type']) ?></td><td><?= h($d['severity']) ?></td><td class="num"><?= num($d['qty']) ?></td><td><?= h($d['remark']) ?></td></tr><?php endforeach ?>
    <tr><td colspan="3"><b>Total defects</b> — Critical <?= $sev['CRITICAL'] ?> · Major <?= $sev['MAJOR'] ?> · Minor <?= $sev['MINOR'] ?></td><td class="num"><b><?= num(array_sum($sev)) ?></b></td><td></td></tr></table><?php endif ?>
    <h3>8. Inspector remarks &amp; action required</h3>
    <div class="note"><b>Remark:</b> <?= h($it['remark'] ?: '—') ?><br><b>Action required:</b> <?= h($it['action_required'] ?: '—') ?></div>
    <?php if ($it['hold_status']): ?><h3>SKU hold for <?= h($it['hold_for']) ?> approval</h3>
    <div class="note"><b>Reason:</b> <?= h($it['hold_reason']) ?><br><b>Response (<?= h($it['hold_status']) ?>):</b> <?= h($it['hold_response'] ?: '—') ?> <?= $it['hold_resp_at'] ? '· ' . fdt($it['hold_resp_at']) : '' ?></div><?php endif ?>
    <?php if ($it['final_result'] === 'CONDITIONAL'): $dev = jdec($it['deviation_json']); ?>
    <h3>Deviation / concession</h3>
    <?php if ($dev): ?><table><tr><th>Parameter</th><th>Spec</th><th>Actual</th><th>Deviation</th><th>Pcs</th></tr><?php foreach ($dev as $d): ?><tr><td><?= h($d['param']) ?></td><td><?= h($d['spec']) ?></td><td><?= h($d['actual']) ?></td><td><?= h($d['dev']) ?></td><td><?= h($d['pcs']) ?></td></tr><?php endforeach ?></table><?php endif ?>
    <div class="note" style="margin-top:4px"><b>QC manager note:</b> <?= h($it['manager_note']) ?><br>
    <b>Client decision:</b> <?= h($it['concession_status'] === 'APPROVED' ? 'DEVIATION APPROVED (concession)' : ($it['concession_status'] === 'REJECTED' ? 'REJECTED — rework required' : 'Pending')) ?>
    <?= $it['concession_by'] ? ' · ' . h($it['concession_by']) . ' · ' . fdt($it['concession_at']) : '' ?><?= $it['concession_remark'] ? '<br><b>Client remark:</b> ' . h($it['concession_remark']) : '' ?></div><?php endif ?>
    <?php if ($photos): ?><h3>9. Photographs</h3>
    <?php foreach ($photos as $p): $f = UPLOAD_DIR . '/' . $p['path']; if (!is_file($f)) continue; ?><div class="ph"><img src="<?= h($f) ?>"><div><b><?= h(ucfirst($p['kind'])) ?></b> <?= h($p['remark']) ?></div></div><?php endforeach ?><?php endif ?>
    <h3>10. Approvals</h3>
    <table class="kv"><tr><td><small>Inspected by</small><br><b><?= h(($job['inspector_name'] ?? '') . ' · ' . ($job['inspector_code'] ?? '')) ?></b><br><small><?= fdt($it['submitted_at']) ?></small></td>
    <td><small>Reviewed &amp; approved by</small><br><b><?= h(val('SELECT name FROM users WHERE id=?', [$it['approved_by']]) ?: '—') ?> · QC Manager</b><br><small><?= fdt($it['approved_at']) ?></small></td>
    <td><small>Distribution</small><br><b>Vendor · Client HO · JEWELSETU</b></td></tr></table>
    <?php if ($it['manager_note'] && $it['final_result'] !== 'CONDITIONAL'): ?><p><b>Manager note:</b> <?= h($it['manager_note']) ?></p><?php endif ?>
    <?php return ob_get_clean();
}

function pdf_render(string $html, ?string $saveTo = null): string {
    $o = new Dompdf\Options(); $o->set('isRemoteEnabled', false); $o->set('chroot', ROOT); $o->set('defaultFont', 'DejaVu Sans');
    $o->set('tempDir', STORAGE_DIR); $o->set('fontCache', STORAGE_DIR);
    $d = new Dompdf\Dompdf($o); $d->loadHtml($html); $d->setPaper('A4'); $d->render();
    $cv = $d->getCanvas(); $fm = $d->getFontMetrics();
    $cv->page_text($cv->get_width() - 95, $cv->get_height() - 30, 'Page {PAGE_NUM} of {PAGE_COUNT}', $fm->getFont('DejaVu Sans'), 7, [0.52, 0.46, 0.48]);
    $out = $d->output();
    if ($saveTo) { @mkdir(dirname($saveTo), 0775, true); file_put_contents($saveTo, $out); }
    return $out;
}

/** Single design / SKU report */
function item_pdf(int $itemId, bool $save = false): array {
    $it = one('SELECT * FROM qc_items WHERE id=?', [$itemId]); $job = job_load((int)$it['job_id']);
    $no = $it['report_no'] ?: ('DRAFT-' . $it['sku']);
    $html = '<html><head><meta charset="utf-8">' . pdf_css() . '</head><body>' . pdf_header(setting('report_header', 'INSPECTION REPORT'), $no, (int)$it['rev'])
        . (!$it['locked'] ? '<div class="wm">DRAFT</div>' : '') . item_sheet($job, $it)
        . '<p style="margin-top:10px;font-size:7.5px;color:#85767a">' . h(setting('report_footer')) . '</p></body></html>';
    $file = $save ? UPLOAD_DIR . '/reports/' . $no . ($it['rev'] ? sprintf('_R%02d', $it['rev']) : '') . '.pdf' : null;
    $pdf = pdf_render($html, $file);
    if ($file) q('UPDATE qc_items SET pdf_path=? WHERE id=?', ['reports/' . basename($file), $itemId]);
    return [$pdf, $no . ($it['rev'] ? sprintf('_R%02d', $it['rev']) : '') . '.pdf', $file];
}

/** Multi-design PO-wise consolidated report */
function job_pdf(int $jobId, bool $save = false): array {
    $job = job_load($jobId); $items = all('SELECT * FROM qc_items WHERE job_id=? ORDER BY id', [$jobId]);
    $tot = ['order' => 0, 'insp' => 0, 'acc' => 0, 'rej' => 0];
    foreach ($items as $i) { $tot['order'] += $i['order_qty']; $tot['insp'] += $i['inspected_qty']; $tot['acc'] += $i['accepted_qty']; $tot['rej'] += $i['rejected_qty']; }
    $results = array_column($items, 'final_result');
    $overall = in_array('FAIL', $results, true) ? 'FAIL' : (in_array('CONDITIONAL', $results, true) ? 'CONDITIONAL' : (count(array_filter($results)) === count($items) ? 'PASS' : 'PENDING'));
    $no = 'PO_' . $job['po_no'] . '_' . ($job['job_no'] ?: $job['req_no']);
    ob_start(); ?>
    <table><tr><td style="border:2px solid <?= $overall === 'PASS' ? '#2f7d55' : ($overall === 'CONDITIONAL' ? '#b7791f' : '#9b1c2e') ?>;padding:8px 10px"><small>OVERALL PO RESULT</small><br><span class="big <?= $overall ?>-t"><?= $overall === 'CONDITIONAL' ? 'CONDITIONAL PASS' : $overall ?></span></td></tr></table>
    <h3>PO executive summary</h3>
    <table class="kv"><tr><td><small>Client</small><br><b><?= h($job['client_name']) ?></b></td><td><small>Vendor</small><br><b><?= h($job['vendor_name']) ?></b></td><td><small>PO No.</small><br><b><?= h($job['po_no']) ?></b></td><td><small>QC Job</small><br><b><?= h($job['job_no']) ?></b></td></tr>
    <tr><td><small>QC date</small><br><b><?= fdate($job['scheduled_date'], 'd-m-Y') ?></b></td><td><small>Inspector</small><br><b><?= h($job['inspector_code'] . ' · ' . $job['inspector_name']) ?></b></td><td><small>Scope</small><br><b><?= h(scope_label($job)) ?></b></td><td><small>Designs</small><br><b><?= count($items) ?></b></td></tr></table>
    <table style="margin-top:6px"><tr><th>Total PO qty</th><th>Inspected</th><th>Accepted</th><th>Rejected</th><th>Rejection %</th><th>Overall PO result</th></tr>
    <tr><td class="num"><b><?= num($tot['order']) ?></b></td><td class="num"><?= num($tot['insp']) ?></td><td class="num"><?= num($tot['acc']) ?></td><td class="num"><?= num($tot['rej']) ?></td><td class="num"><?= pct($tot['rej'], $tot['insp'], 2) ?></td><td><b class="<?= $overall ?>-t"><?= $overall ?></b></td></tr></table>
    <h3>Design-wise results</h3>
    <table><tr><th>#</th><th>Report no.</th><th>SKU / Design</th><th>Product</th><th class="num">Order</th><th class="num">Inspected</th><th class="num">Accepted</th><th class="num">Rejected</th><th>Result</th></tr>
    <?php foreach ($items as $k => $i): ?><tr><td><?= $k + 1 ?></td><td><?= h($i['report_no'] ?: '—') ?></td><td><?= h($i['sku']) ?></td><td><?= h($i['product']) ?></td><td class="num"><?= num($i['order_qty']) ?></td><td class="num"><?= num($i['inspected_qty']) ?></td><td class="num"><?= num($i['accepted_qty']) ?></td><td class="num"><?= num($i['rejected_qty']) ?></td><td><?= pass_tag($i['final_result']) ?></td></tr><?php endforeach ?></table>
    <?php $body = ob_get_clean();
    foreach ($items as $i) $body .= '<div class="pb"></div><h3 style="font-size:12px;border:0">Design sheet — ' . h($i['sku']) . ' · ' . h($i['report_no'] ?: 'not approved') . '</h3>' . item_sheet($job, $i);
    $html = '<html><head><meta charset="utf-8">' . pdf_css() . '</head><body>' . pdf_header('PO-WISE CONSOLIDATED QC REPORT', $no) . $body . '</body></html>';
    $file = $save ? UPLOAD_DIR . '/reports/' . $no . '.pdf' : null;
    $pdf = pdf_render($html, $file);
    if ($file) q('UPDATE qc_jobs SET po_pdf=? WHERE id=?', ['reports/' . basename($file), $jobId]);
    return [$pdf, $no . '.pdf', $file];
}
