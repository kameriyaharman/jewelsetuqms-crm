<?php
/**
 * PDF reports in the JEWELSETU sample format:
 *  - Single Design Report (A4 portrait)  — item_pdf()
 *  - Multi-Design PO Report (A4 landscape) — job_pdf()
 */
require_once ROOT . '/lib/dompdf/autoload.inc.php';

const R_NAVY = '#1b2a4a';
const R_GOLD = '#c9a227';

function rpt_css(bool $land): string {
    return '<style>
    @page{margin:' . ($land ? '96px 26px 40px 26px' : '104px 26px 42px 26px') . '}
    body{font-family:"DejaVu Sans",sans-serif;font-size:7.7px;color:#1f2937}
    #hdr{position:fixed;top:' . ($land ? '-82px' : '-90px') . ';left:0;right:0}
    #ftr{position:fixed;bottom:-26px;left:0;right:0;font-size:6.6px;color:#4b5563;border-top:1px solid #cbd5e1;padding-top:3px}
    table{width:100%;border-collapse:collapse}
    td,th{border:1px solid #d5dde8;padding:3px 5px;vertical-align:middle}
    .hd td{border:0;padding:0}
    .bar td{background:' . R_NAVY . ';color:#fff;font-weight:bold;font-size:8.4px;padding:5px 7px;border:0}
    .sec{margin-top:7px;page-break-inside:avoid}
    .lab{background:#dde5ed;font-weight:bold;color:#1f2937;width:17%}
    .val{color:#1f5aa6;font-weight:bold}
    .th th{background:#e8eef5;color:#1f2937;font-weight:bold;text-align:center;font-size:7.2px}
    .c{text-align:center}.r{text-align:right}
    .ok{color:#15803d;font-weight:bold}.nok{color:#b91c1c;font-weight:bold}.na{color:#6b7280;font-weight:bold}.cond{color:#b45309;font-weight:bold}
    .zebra tr:nth-child(even) td{background:#f8fafc}
    .big{font-size:12px;font-weight:bold}
    .ph{border:1px solid #d5dde8;text-align:center;vertical-align:top;padding:4px}
    .ph img{width:100%;height:' . ($land ? '120px' : '112px') . '}
    .phc{background:#e8eef5;font-weight:bold;font-size:7px;padding:3px;text-align:center}
    .empty{color:#9ca3af;font-style:italic}
    .terms td{font-size:6.4px;line-height:1.35;vertical-align:top}
    .tot td{background:#e8eef5;font-weight:bold}
    .wm{position:fixed;top:330px;left:120px;font-size:64px;color:rgba(185,28,28,.07);transform:rotate(-28deg)}
    </style>';
}
function rpt_status_css(?string $s): string { return match ($s) { 'PASS', 'OK' => 'ok', 'FAIL', 'NOT OK' => 'nok', 'CONDITIONAL', 'CONDITIONAL PASS', 'PARTIAL / HOLD', 'PENDING' => 'cond', default => 'na' }; }
function okn(?string $r): string { return match ($r) { 'PASS' => 'OK', 'FAIL' => 'NOT OK', 'NA' => 'NA', default => '—' }; }
function rpt_header(string $title, string $mode, string $no, string $state): string {
    $logo = ROOT . '/assets/img/logo-pdf.jpg';
    $nv = R_NAVY; $gd = R_GOLD;
    return '<div id="hdr"><table class="hd"><tr>
      <td style="width:22%;vertical-align:middle;padding-right:6px"><img src="' . $logo . '" style="width:150px"></td>
      <td style="width:50%"><table><tr><td style="background:' . $nv . ';color:#fff;text-align:center;font-weight:bold;font-size:10.5px;padding:6px;border:0">' . h(setting('company_name', 'JEWELSETU QUALITY SERVICES')) . '</td></tr>
        <tr><td style="background:' . $nv . ';color:' . $gd . ';text-align:center;font-weight:bold;font-size:8.6px;padding:8px 6px;border:2px solid ' . $gd . '">' . h($title) . '</td></tr></table></td>
      <td style="width:28%;padding-left:3px"><table><tr><td style="background:' . $nv . ';color:' . $gd . ';text-align:center;font-size:6.8px;padding:4px;border:0">' . h($state) . '</td></tr>
        <tr><td style="background:' . $nv . ';color:#fff;text-align:center;font-size:7px;padding:4px;border:0;border-top:1px solid #34476e">' . h($mode) . '</td></tr>
        <tr><td style="background:' . $nv . ';color:#fff;text-align:center;font-size:8.2px;font-weight:bold;padding:5px;border:0;border-top:1px solid #34476e">' . h($no) . '</td></tr></table></td>
    </tr></table></div>
    <div id="ftr">' . h(ucwords(strtolower(setting('company_name', 'JEWELSETU Quality Services')))) . ' (' . h(setting('company_web', 'www.jewelsetuqms.in')) . ') — ' . h(setting('company_tagline', '')) . '</div>';
}
function bar(string $t): string { return '<table class="bar"><tr><td>' . h($t) . '</td></tr></table>'; }
function img_src(?string $path): ?string { if (!$path) return null; $f = UPLOAD_DIR . '/' . $path; return is_file($f) && preg_match('/\.(jpe?g|png)$/i', $f) ? $f : null; }
function product_image(array $it): ?string {
    $p = val("SELECT path FROM item_photos WHERE item_id=? AND kind='product' ORDER BY id LIMIT 1", [$it['id']]);
    if ($f = img_src($p)) return $f;
    foreach (all('SELECT path FROM attachments WHERE item_id=? OR (job_id=? AND item_id IS NULL) ORDER BY item_id DESC, id', [$it['id'], $it['job_id']]) as $a) if ($f = img_src($a['path'])) return $f;
    return null;
}
function approver(?int $uid): string {
    if (!$uid) return '—';
    $u = one('SELECT name, designation FROM users WHERE id=?', [$uid]);
    return $u ? $u['name'] . ($u['designation'] ? ' (' . $u['designation'] . ')' : '') : '—';
}
function item_stats(array $it): array {
    $def = all('SELECT * FROM item_defects WHERE item_id=? ORDER BY qty DESC', [$it['id']]);
    $sev = ['CRITICAL' => 0, 'MAJOR' => 0, 'MINOR' => 0]; foreach ($def as $d) $sev[$d['severity']] += (int)$d['qty'];
    $checks = jdec($it['checks_json']); $tests = jdec($it['tests_json']); $meas = jdec($it['meas_json']);
    $cnt = fn($arr, $r) => count(array_filter($arr, fn($x) => ($x['result'] ?? '') === $r));
    $types = array_values(array_unique(array_column($def, 'type')));
    $insp = max(1, (int)$it['inspected_qty']);
    $xrf = null; foreach ($tests as $t) if (stripos($t['name'], 'xrf') !== false || stripos($t['name'], 'micron') !== false) $xrf = okn($t['result'] ?? null);
    foreach ($checks as $c) if (stripos($c['name'], 'xrf') !== false && ($c['result'] ?? '') === 'FAIL') $xrf = 'NOT OK';
    return ['def' => $def, 'sev' => $sev, 'checks' => $checks, 'tests' => $tests, 'meas' => $meas,
        'cf' => $cnt($checks, 'FAIL'), 'cp' => $cnt($checks, 'PASS'), 'cn' => $cnt($checks, 'NA'), 'tf' => $cnt($tests, 'FAIL'), 'mf' => $cnt($meas, 'FAIL'),
        'rate' => (int)$it['rejected_qty'] * 100 / $insp, 'primary' => $types ? implode(' & ', array_slice($types, 0, 2)) : 'No defect observed',
        'xrf' => ($xrf && $xrf !== '—') ? $xrf : 'OK'];
}
function photo_grid(array $photos, int $cols, string $empty, int $min = 0): string {
    $cells = [];
    foreach ($photos as $p) { $f = img_src($p['path']); if (!$f) continue;
        $cells[] = ['<td class="phc">' . h($p['caption']) . '</td>', '<td class="ph"><img src="' . h($f) . '"></td>']; }
    $k = 1; while (count($cells) < $min) $cells[] = ['<td class="phc">' . h($empty) . ' ' . $k++ . '</td>', '<td class="ph empty" style="height:50px"><br>Not captured</td>'];
    if (!$cells) return '<table><tr><td class="c empty" style="padding:8px">No photos captured</td></tr></table>';
    $o = '<table style="table-layout:fixed">';
    foreach (array_chunk($cells, $cols) as $row) {
        $row = array_pad($row, $cols, ['<td style="border:0"></td>', '<td style="border:0"></td>']);
        $o .= '<tr>' . implode('', array_column($row, 0)) . '</tr><tr>' . implode('', array_column($row, 1)) . '</tr>';
    }
    return $o . '</table>';
}
function terms_block(): string {
    return '<div class="sec">' . bar(strtoupper(setting('company_name', 'JEWELSETU QUALITY SERVICES')) . ' — INSPECTION GOVERNANCE, TERMS & CONSULTANCY PORTFOLIO') .
      '<table class="terms"><tr><td style="width:52%"><b>INSPECTION RULES &amp; TERMS OF ACCEPTANCE:</b><br>' . nl2br(h(setting('report_terms', ''))) . '</td>
      <td><b>JEWELSETU QMS — SPECIALIZED JEWELLERY QUALITY SOLUTIONS:</b><br>' . nl2br(h(setting('report_portfolio', ''))) . '<br><b>' . h(setting('company_contact', '')) . '</b></td></tr>
      <tr><td colspan="2" style="text-align:center">© ' . h(ucwords(strtolower(setting('company_name', '')))) . ' (' . h(setting('company_web', '')) . ') — ' . h(setting('company_tagline', '')) . '</td></tr></table></div>';
}
function factory_eval(array $v): string {
    $cell = fn($x) => '<td class="c ' . ($x === 'GOOD' ? 'ok' : ($x === 'POOR' ? 'nok' : ($x ? 'cond' : 'na'))) . '">' . h($x ?: '—') . '</td>';
    return '<table class="th"><tr><th>Factory Cooperation</th><th>Factory Line Organization</th><th>Overall Factory QC Readiness</th></tr><tr>' . $cell($v[0]) . $cell($v[1]) . $cell($v[2]) . '</tr></table>';
}
function rpt_state(array $it): string {
    if (!$it['locked']) return 'DRAFT — NOT APPROVED';
    return 'APPROVED · LOCKED' . ($it['rev'] ? ' · REVISION ' . sprintf('%02d', $it['rev']) : ' · REV 00');
}
function spec_cell(string $metal, array $job): string {
    return '<table><tr><td class="c val" style="padding:6px">' . h(strtoupper($metal)) .
        ($job['specs'] ? '<br><span style="color:#1f2937;font-weight:normal">' . h($job['specs']) . '</span>' : '') .
        ($job['special_req'] ? '<br><span style="color:#1f2937;font-weight:normal">Special: ' . h($job['special_req']) . '</span>' : '') . '</td></tr></table>';
}
function special_photos(array $photos, bool $withSku): array {
    $spec = [];
    foreach (['xrf' => 'XRF Micron Test Report', 'weight' => 'Digital Weight Scale Reading', 'packaging' => '925 Stamping / Barcode & Packaging'] as $k => $l)
        foreach ($photos as $p) if ($p['kind'] === $k) { $spec[] = $p + ['caption' => 'Special Photo: ' . $l . ($withSku ? ' (' . $p['sku'] . ')' : '')]; break; }
    foreach ($photos as $p) if (in_array($p['kind'], ['dimension', 'other', 'spec'], true) && count($spec) < 6) $spec[] = $p + ['caption' => ucfirst($p['kind']) . ($p['remark'] ? ': ' . $p['remark'] : '')];
    return $spec;
}
function pdf_render(string $html, bool $land, ?string $saveTo = null): string {
    $o = new Dompdf\Options(); $o->set('isRemoteEnabled', false); $o->set('chroot', ROOT); $o->set('defaultFont', 'DejaVu Sans');
    $o->set('tempDir', STORAGE_DIR); $o->set('fontCache', STORAGE_DIR);
    $d = new Dompdf\Dompdf($o); $d->loadHtml($html); $d->setPaper('A4', $land ? 'landscape' : 'portrait'); $d->render();
    $cv = $d->getCanvas(); $fm = $d->getFontMetrics();
    $cv->page_text($cv->get_width() - 70, $cv->get_height() - 32, 'Page {PAGE_NUM} of {PAGE_COUNT}', $fm->getFont('DejaVu Sans'), 6.6, [0.29, 0.33, 0.39]);
    $out = $d->output();
    if ($saveTo) { @mkdir(dirname($saveTo), 0775, true); file_put_contents($saveTo, $out); }
    return $out;
}

/* ===================================================================== SINGLE DESIGN */
function item_pdf(int $itemId, bool $save = false): array {
    $it = one('SELECT * FROM qc_items WHERE id=?', [$itemId]); $job = job_load((int)$it['job_id']);
    $S = item_stats($it); $res = $it['final_result'] ?: ($it['system_result'] ?: 'PENDING');
    $thr = client_threshold((int)$job['client_id']); $thrT = rtrim(rtrim(number_format($thr, 1), '0'), '.');
    $aql = $job['scope'] === 'BULK' && $job['bulk_mode'] === 'AQL'; $plan = jdec($it['plan_json']);
    $no = $it['report_no'] ?: 'DRAFT/' . $it['sku'];
    $insp = (int)$it['inspected_qty']; $rate = $S['rate'];
    $decision = match ($res) {
        'FAIL' => $aql ? 'LOT FAILED (AQL Ac/Re exceeded) - Full Lot Hold & CAPA Required' : "LOT FAILED (>={$thrT}% Rej/Repair) - Full Lot Hold & CAPA Required",
        'PASS' => (int)$it['rejected_qty'] > 0 ? ($aql ? 'LOT ACCEPTED (within AQL) - Defective pcs replaced / reworked' : "LOT PASSED (<{$thrT}% Rej/Repair) - Defective pcs segregated for rework") : 'LOT PASSED - All inspected pieces accepted',
        'CONDITIONAL' => 'CONDITIONAL PASS - Deviation sent to client for concession', default => 'PENDING - QC manager review' };
    $qtyRow = $aql ? 'AQL Acceptance (ISO 2859-1, Level ' . h($plan['level'] ?? 'II') . ')' : "100% Quantity &amp; Rejection Threshold ({$thrT}%)";
    $qtyOk = $aql ? (system_result($job, $it, $S['def']) === 'PASS') : $rate < $thr;
    $qtyRem = $aql ? implode(' · ', array_map(fn($k) => ucfirst($k) . ' ' . $S['sev'][strtoupper($k)] . ' / Re ' . ($plan[$k]['re'] ?? '-'), array_filter(['critical', 'major', 'minor'], fn($k) => !empty($plan[$k]['aql']))))
        : ($qtyOk ? 'PASSED: ' : 'FAILED: ') . number_format($rate, 1) . '% Rejected/Repair (' . ($qtyOk ? '<' : '>=') . $thrT . '% limit)';
    $specOk = $res !== 'FAIL';
    $metal = trim($it['metal'] . ($it['plating'] && $it['plating'] !== '—' ? ' + ' . $it['plating'] : ''));
    $img = product_image($it);
    $ph = array_map(fn($p) => $p + ['sku' => $it['sku']], all('SELECT * FROM item_photos WHERE item_id=? ORDER BY id', [$it['id']]));
    $defPh = []; $n = 1; foreach ($ph as $p) if ($p['kind'] === 'defect') $defPh[] = $p + ['caption' => 'Defect Photo ' . $n++ . ': ' . ($p['remark'] ?: 'Defect evidence')];
    $spec = special_photos($ph, false);
    $cats = array_column(all('SELECT name FROM defect_categories ORDER BY sort'), 'name');
    ob_start(); ?>
<html><head><meta charset="utf-8"><?= rpt_css(false) ?></head><body>
<?= rpt_header(report_title($job), 'SINGLE DESIGN REPORT', $no, rpt_state($it)) ?>
<?= !$it['locked'] ? '<div class="wm">DRAFT</div>' : '' ?>
<div><?= bar('1. GENERAL INFORMATION & PRODUCT IMAGE') ?>
<table>
 <tr><td class="lab">Inspection Date</td><td class="val"><?= fdate($it['insp_date'] ?: $job['scheduled_date'], 'd-M-Y') ?></td><td class="lab">Inspection Type</td><td class="val"><?= $it['reinspection'] ? 'RE-INSPECTION' : 'REGULAR' ?></td>
   <td rowspan="11" style="width:24%;text-align:center;vertical-align:top;padding:0"><div style="background:#e8eef5;font-weight:bold;padding:4px">ACTUAL PRODUCT IMAGE</div>
   <?= $img ? '<img src="' . h($img) . '" style="width:150px;max-height:150px;margin-top:6px">' : '<div class="empty" style="padding-top:50px;padding-bottom:50px">No product image</div>' ?>
   <div class="val" style="padding:4px"><?= h($it['sku']) ?></div></td></tr>
 <tr><td class="lab">Client Name</td><td class="val"><?= h(strtoupper($job['client_name'])) ?></td><td class="lab">Re-Inspection</td><td class="val"><?= $it['reinspection'] ? 'YES' : 'NO' ?></td></tr>
 <tr><td class="lab">Vendor / Factory Unit</td><td class="val"><?= h(strtoupper($job['vendor_name'])) ?></td><td class="lab">Factory / City Location</td><td class="val"><?= h(strtoupper((string)val('SELECT location FROM vendors WHERE id=?', [$job['vendor_id']]))) ?></td></tr>
 <tr><td class="lab">PO Number</td><td class="val"><?= h($job['po_no']) ?></td><td class="lab">Client Style / Ref No.</td><td class="val"><?= h($it['style_ref'] ?: '—') ?></td></tr>
 <tr><td class="lab">Design No. / SKU</td><td class="val"><?= h($it['sku']) ?></td><td class="lab">Product Category</td><td class="val"><?= h($it['product']) ?></td></tr>
 <tr><td class="lab">Total Order Qty (Pcs)</td><td class="val"><?= num($it['order_qty']) ?></td><td class="lab">Qty Offered</td><td class="val"><?= num($it['ready_qty']) ?></td></tr>
 <tr><td class="lab">Metal &amp; Plating Spec</td><td class="val"><?= h($metal) ?></td><td class="lab">Master Sample Ref.</td><td class="val"><?= h($it['master_sample'] ?: 'Approved Master Sample Available') ?></td></tr>
 <tr><td class="lab">Target Piece Weight (g)</td><td class="val"><?= h($it['target_weight'] ?: '—') ?></td><td class="lab">Stone / Setting Type</td><td class="val"><?= h($it['stone_setting'] ?: '—') ?></td></tr>
 <tr><td class="lab">QC Inspector Name / ID</td><td class="val"><?= h($job['inspector_code'] . ' · ' . $job['inspector_name']) ?></td><td class="lab">Pass / Fail Rule</td><td class="val" style="color:#1f2937"><?= h(result_rule_text($job)) ?></td></tr>
 <tr><td class="lab">Production Batch / Lot No.</td><td class="val"><?= h($it['lot_no'] ?: '—') ?></td><td class="lab">XRF Machine Used</td><td class="val"><?= h($it['xrf_machine'] ?: '—') ?></td></tr>
 <tr><td class="lab">QC Job No.</td><td class="val"><?= h($job['job_no']) ?></td><td class="lab">GPS / Start Time</td><td class="val" style="font-weight:normal"><?= h($it['gps'] ?: '—') ?></td></tr>
<?php if (trim((string)($it['spec_detail'] ?? '')) !== ''): ?> <tr><td class="lab">Stone / Spec Detail</td><td class="val" colspan="3" style="font-weight:normal"><?= nl2br(h($it['spec_detail'])) ?></td></tr><?php endif; ?>
<?php if (trim((string)($it['instructions'] ?? '')) !== ''): ?> <tr><td class="lab">Client Instructions</td><td class="val" colspan="3" style="font-weight:normal"><?= nl2br(h($it['instructions'])) ?></td></tr><?php endif; ?>
</table></div>

<div class="sec"><?= bar($aql ? '2. INSPECTION RESULT SUMMARY (AQL ISO 2859-1 LOGIC)' : "2. INSPECTION RESULT SUMMARY ({$thrT}% REJECTION / REPAIR THRESHOLD LOGIC)") ?>
<table><tr><td class="lab" style="width:34%;font-size:8.4px">OVERALL LOT INSPECTION DECISION</td><td class="c big <?= rpt_status_css($res) ?>" style="width:22%"><?= h($res === 'CONDITIONAL' ? 'CONDITIONAL PASS' : $res) ?></td><td class="c" style="font-weight:bold"><?= h($decision) ?></td></tr></table>
<table class="th zebra"><tr><th style="width:34%">Evaluation Parameter</th><th style="width:16%">Status</th><th>Automated Summary Remarks</th></tr>
 <tr><td><?= $qtyRow ?></td><td class="c <?= $qtyOk ? 'ok' : 'nok' ?>"><?= $qtyOk ? 'OK' : 'NOT OK' ?></td><td><?= h($qtyRem) ?></td></tr>
 <tr><td>On-Site Physical &amp; Lab Tests</td><td class="c <?= $S['tf'] ? 'nok' : 'ok' ?>"><?= $S['tf'] ? 'NOT OK' : 'OK' ?></td><td><?= $S['tf'] ? $S['tf'] . ' Test observation(s) noted - see Section 4' : 'All on-site tests satisfactory' ?></td></tr>
 <tr><td>Data Measurement (Weight &amp; Size)</td><td class="c <?= $S['mf'] ? 'nok' : 'ok' ?>"><?= $S['mf'] ? 'NOT OK' : 'OK' ?></td><td><?= $S['mf'] ? $S['mf'] . ' parameter(s) out of tolerance - see Section 5' : 'Dimensions &amp; weight within tolerance' ?></td></tr>
 <tr><td><?= count($S['checks']) ?>-Point Critical Quality Checkpoints</td><td class="c <?= $S['cf'] ? 'nok' : 'ok' ?>"><?= $S['cf'] ? 'NOT OK' : 'OK' ?></td><td><?= $S['cf'] ?> Failed, <?= $S['cp'] ?> Passed, <?= $S['cn'] ?> N/A</td></tr>
 <tr><td>Client Technical Specifications</td><td class="c <?= $specOk ? 'ok' : 'nok' ?>"><?= $specOk ? 'OK' : 'NOT OK' ?></td><td>Verified against PO &amp; Client Spec Sheet</td></tr>
</table></div>

<div class="sec"><?= bar($aql ? '3. QUANTITY INSPECTION & AQL STATUS' : "3. QUANTITY INSPECTION & {$thrT}% THRESHOLD STATUS") ?>
<table class="th"><tr><th>Inspection Date</th><th>Design No.</th><th>Order Qty</th><th><?= $aql ? 'Sample Inspected' : ($job['scope'] === 'BULK' && $job['bulk_mode'] === '100' ? '100% Inspected' : 'Inspected Qty') ?></th><th>Accepted Qty</th><th>Reject / Repair Qty</th><th>Rej / Repair %</th><th>Primary Defect Observed</th><th>Product Status</th></tr>
 <tr><td class="c"><?= fdate($it['insp_date'] ?: $job['scheduled_date'], 'd-M-Y') ?></td><td class="c"><b><?= h($it['sku']) ?></b></td><td class="c"><?= num($it['order_qty']) ?></td><td class="c"><?= num($insp) ?></td><td class="c"><?= num($it['accepted_qty']) ?></td><td class="c"><b><?= num($it['rejected_qty']) ?></b></td>
   <td class="c"><b><?= number_format($rate, 1) ?>%</b></td><td class="c"><?= h($S['primary']) ?></td><td class="c <?= rpt_status_css($res) ?>"><?= h($res === 'CONDITIONAL' ? 'COND. PASS' : $res) ?></td></tr>
</table>
<?php if ($aql && $plan): ?><div style="padding:3px 2px;color:#4b5563">AQL plan: lot <?= num($plan['lot'] ?? $it['ready_qty']) ?> · Level <?= h($plan['level']) ?> · code <?= h($plan['letter']) ?> · sample <?= (int)$plan['sample'] ?><?php foreach (['critical', 'major', 'minor'] as $k) if (!empty($plan[$k]['aql'])) echo ' · ' . ucfirst($k) . ' AQL ' . h($plan[$k]['aql']) . ' Ac ' . $plan[$k]['ac'] . ' / Re ' . $plan[$k]['re']; ?></div><?php endif ?>
</div>

<div class="sec"><?= bar('4. ON-SITE PHYSICAL & RELIABILITY TESTS') ?>
<table class="th zebra"><tr><th style="width:38%">Test Parameter</th><th>Sample Tested (Pcs)</th><th>Test Result</th><th style="width:36%">Method / QC Remarks</th></tr>
<?php foreach ($S['tests'] as $t): $r = okn($t['result'] ?? null); ?><tr><td><?= h($t['name']) ?></td><td class="c"><?= h($r === 'NA' ? 'NA' : ($t['qty'] ?? '')) ?></td><td class="c <?= rpt_status_css($r) ?>"><?= $r ?></td><td><?= h($t['remark'] ?? '') ?></td></tr><?php endforeach ?>
<?php if (!$S['tests']): ?><tr><td colspan="4" class="c na">No tests recorded</td></tr><?php endif ?>
</table></div>

<div class="sec"><?= bar('5. DIMENSIONAL & WEIGHT MEASUREMENT') ?>
<table class="th zebra"><tr><th style="width:38%">Measurement Parameter</th><th>Sample Tested (Pcs)</th><th>Result</th><th style="width:36%">Recorded Values / Tolerance</th></tr>
<?php $anyM = false; foreach ($S['meas'] as $m): if (($m['actual'] ?? '') === '' && ($m['spec'] ?? '') === '') continue; $anyM = true; $r = okn($m['result'] ?: 'NA'); ?>
 <tr><td><?= h($m['param']) ?> (<?= strtolower($m['param']) === 'weight' ? 'Digital scale' : 'Digital Vernier' ?>)</td><td class="c"><?= h($m['qty'] ?? 2) ?></td><td class="c <?= rpt_status_css($r) ?>"><?= $r ?></td>
   <td><?= ($m['actual'] ?? '') !== '' ? 'Actual ' . h($m['actual'] . ' ' . ($m['unit'] ?? '')) . ' · ' : '' ?>Spec <?= h($m['spec'] . ' ' . ($m['unit'] ?? '')) ?> (±<?= h($m['tol']) ?>)</td></tr><?php endforeach ?>
<?php if (!$anyM): ?><tr><td colspan="4" class="c na">No measurements recorded</td></tr><?php endif ?>
</table></div>

<div class="sec"><?= bar('6. DEFECTS LOGGED DURING INSPECTION') ?>
<table class="th zebra"><tr><th style="width:26%">Defect Category</th><th>Specific Defect Description</th><th style="width:9%">Critical</th><th style="width:9%">Major</th><th style="width:9%">Minor</th><th style="width:10%">Total Defects</th></tr>
<?php $by = [];
  foreach ($S['def'] as $d) { $by[$d['category']]['t'][] = $d['type'] . ($d['remark'] ? ' (' . $d['remark'] . ')' : ''); $by[$d['category']][$d['severity']] = ($by[$d['category']][$d['severity']] ?? 0) + $d['qty']; }
  foreach (array_unique(array_merge(array_keys($by), array_slice($cats, 0, 6))) as $cat): $b = $by[$cat] ?? []; $tt = ($b['CRITICAL'] ?? 0) + ($b['MAJOR'] ?? 0) + ($b['MINOR'] ?? 0); ?>
 <tr><td><?= h($cat) ?></td><td><?= h(implode('; ', array_unique($b['t'] ?? []))) ?></td><td class="c"><?= $b['CRITICAL'] ?? '' ?></td><td class="c"><?= $b['MAJOR'] ?? '' ?></td><td class="c"><?= $b['MINOR'] ?? '' ?></td><td class="c"><b><?= $tt ?></b></td></tr><?php endforeach ?>
 <tr class="tot"><td colspan="2" class="r">TOTAL DEFECTS OBSERVED</td><td class="c"><?= $S['sev']['CRITICAL'] ?></td><td class="c"><?= $S['sev']['MAJOR'] ?></td><td class="c"><?= $S['sev']['MINOR'] ?></td><td class="c"><?= array_sum($S['sev']) ?></td></tr>
</table></div>

<div class="sec"><?= bar('7. ' . count($S['checks']) . '-POINT CRITICAL QUALITY CHECKPOINTS OVERVIEW') ?>
<table class="th zebra"><tr><th style="width:46%">Critical Quality Checkpoint</th><th style="width:16%">Status (PASS / FAIL / NA)</th><th>QC Inspector Remarks</th></tr>
<?php foreach ($S['checks'] as $i => $c): ?><tr><td><?= $i + 1 ?>. <?= h($c['name']) ?></td><td class="c <?= rpt_status_css($c['result'] ?? '') ?>"><?= h(($c['result'] ?? '') ?: '—') ?></td><td><?= h($c['remark'] ?? '') ?></td></tr><?php endforeach ?>
</table></div>

<div class="sec"><?= bar('8. CLIENT TECHNICAL SPECIFICATIONS & SPECIAL REQUIREMENTS') ?><?= spec_cell($metal, $job) ?></div>
<div class="sec"><?= bar('9. DEFECT EVIDENCE PHOTO LOG') ?><?= photo_grid($defPh, 3, 'Defect Photo', $defPh ? 0 : 3) ?></div>
<div class="sec"><?= bar('10. SPECIAL & OTHER IMPORTANT PICTURES (XRF REPORT, WEIGHT SCALE, STAMPING & PACKAGING)') ?><?= photo_grid($spec, 3, 'Special Photo', 3) ?></div>

<?php if ($it['hold_status']): ?><div class="sec"><?= bar('SKU HOLD FOR ' . strtoupper((string)$it['hold_for']) . ' APPROVAL') ?>
<table><tr><td class="lab">Question</td><td><?= h($it['hold_reason']) ?> <i>(<?= h($it['hold_by']) ?>, <?= fdt($it['hold_at']) ?>)</i></td></tr>
<tr><td class="lab">Response</td><td><b class="<?= $it['hold_status'] === 'APPROVED' ? 'ok' : ($it['hold_status'] === 'PENDING' ? 'cond' : 'nok') ?>"><?= h($it['hold_status']) ?></b> — <?= h($it['hold_response'] ?: '—') ?> <?= $it['hold_resp_at'] ? '(' . fdt($it['hold_resp_at']) . ')' : '' ?></td></tr></table></div><?php endif ?>

<?php if ($it['final_result'] === 'CONDITIONAL'): $dev = jdec($it['deviation_json']); ?><div class="sec"><?= bar('DEVIATION / CONCESSION') ?>
<?php if ($dev): ?><table class="th"><tr><th>Parameter</th><th>Spec</th><th>Actual</th><th>Deviation</th><th>Pcs</th></tr><?php foreach ($dev as $d): ?><tr><td><?= h($d['param']) ?></td><td class="c"><?= h($d['spec']) ?></td><td class="c"><?= h($d['actual']) ?></td><td class="c nok"><?= h($d['dev']) ?></td><td class="c"><?= h($d['pcs']) ?></td></tr><?php endforeach ?></table><?php endif ?>
<table><tr><td class="lab">QC manager note</td><td><?= h($it['manager_note']) ?></td></tr>
<tr><td class="lab">Client decision</td><td><b class="<?= $it['concession_status'] === 'APPROVED' ? 'ok' : ($it['concession_status'] === 'REJECTED' ? 'nok' : 'cond') ?>"><?= h($it['concession_status'] === 'APPROVED' ? 'DEVIATION APPROVED (CONCESSION)' : ($it['concession_status'] === 'REJECTED' ? 'REJECTED — REWORK REQUIRED' : 'PENDING')) ?></b>
<?= $it['concession_by'] ? ' · ' . h($it['concession_by']) . ' · ' . fdt($it['concession_at']) : '' ?><?= $it['concession_remark'] ? '<br>Remark: ' . h($it['concession_remark']) : '' ?></td></tr></table></div><?php endif ?>

<div class="sec"><?= bar('11. INSPECTOR REMARKS, CORRECTIVE ACTION (CAPA) & FACTORY EVALUATION') ?>
<table><tr><td style="padding:5px"><b>QC Remarks &amp; Action Plan:</b> <?= h(trim(($it['remark'] ?: '') . ' ' . ($it['action_required'] ?: ''))) ?: '—' ?><?= $it['manager_note'] && $it['final_result'] !== 'CONDITIONAL' ? '<br><b>Reviewer note:</b> ' . h($it['manager_note']) : '' ?></td></tr></table>
<?= factory_eval([$it['factory_coop'], $it['factory_line'], $it['factory_ready']]) ?>
<table><tr><td style="width:33%;height:34px;vertical-align:top"><b>Inspected By (JEWELSETU QC):</b> <?= h($job['inspector_code'] . ' · ' . $job['inspector_name']) ?><br><span class="na"><?= fdt($it['submitted_at']) ?></span></td>
<td style="width:34%;vertical-align:top"><b>Reviewed By:</b> <?= h(approver($it['approved_by'] ? (int)$it['approved_by'] : null)) ?><br><span class="na"><?= fdt($it['approved_at']) ?></span></td>
<td style="vertical-align:top"><b>Factory Representative Sign &amp; Stamp:</b></td></tr></table></div>
<?= terms_block() ?>
</body></html>
<?php
    $html = ob_get_clean();
    $fname = file_safe($no) . ($it['rev'] ? sprintf('_R%02d', $it['rev']) : '') . '.pdf';
    $file = $save ? UPLOAD_DIR . '/reports/' . $fname : null;
    $pdf = pdf_render($html, false, $file);
    if ($file) q('UPDATE qc_items SET pdf_path=? WHERE id=?', ['reports/' . $fname, $itemId]);
    return [$pdf, $fname, $file];
}

/* ===================================================================== MULTI-DESIGN PO */
function job_pdf(int $jobId, bool $save = false): array {
    $job = job_load($jobId); $items = all('SELECT * FROM qc_items WHERE job_id=? ORDER BY id', [$jobId]);
    $thr = client_threshold((int)$job['client_id']); $thrT = rtrim(rtrim(number_format($thr, 1), '0'), '.');
    $aql = $job['scope'] === 'BULK' && $job['bulk_mode'] === 'AQL';
    $no = po_report_no($job);
    $T = ['po' => 0, 'off' => 0, 'insp' => 0, 'acc' => 0, 'rej' => 0, 'C' => 0, 'M' => 0, 'm' => 0]; $st = []; $pass = $fail = $cond = 0;
    foreach ($items as $i) { $s = item_stats($i); $st[$i['id']] = $s;
        $T['po'] += $i['order_qty']; $T['off'] += $i['ready_qty']; $T['insp'] += $i['inspected_qty']; $T['acc'] += $i['accepted_qty']; $T['rej'] += $i['rejected_qty'];
        $T['C'] += $s['sev']['CRITICAL']; $T['M'] += $s['sev']['MAJOR']; $T['m'] += $s['sev']['MINOR'];
        $r = $i['final_result'] ?: $i['system_result']; if ($r === 'PASS') $pass++; elseif ($r === 'FAIL') $fail++; elseif ($r === 'CONDITIONAL') $cond++; }
    $n = count($items); $rate = $T['insp'] ? $T['rej'] * 100 / $T['insp'] : 0;
    $overall = $n && $fail === $n ? 'FAIL' : ($fail ? 'PARTIAL / HOLD' : ($cond ? 'CONDITIONAL PASS' : ($n && $pass === $n ? 'PASS' : 'PENDING')));
    $failLbl = $aql ? 'AQL exceeded' : ">={$thrT}% Rej/Repair"; $passLbl = $aql ? 'within AQL' : "<{$thrT}% Rej/Repair";
    $agg = function (string $key) use ($items, $st) {
        $o = [];
        foreach ($items as $i) foreach ($st[$i['id']][$key] as $t) {
            $nm = $t['name'] ?? $t['param']; $r = $t['result'] ?? '';
            if (!isset($o[$nm])) $o[$nm] = ['name' => $nm, 'qty' => 0, 'res' => [], 'remark' => '', 'fails' => []];
            $o[$nm]['qty'] += is_numeric($t['qty'] ?? null) ? (int)$t['qty'] : 0; $o[$nm]['res'][] = $r;
            if ($r === 'FAIL') $o[$nm]['fails'][] = $i['sku'] . ': ' . (($t['remark'] ?? '') ?: ('Actual ' . ($t['actual'] ?? '')));
            elseif (!$o[$nm]['remark']) $o[$nm]['remark'] = $t['remark'] ?? (isset($t['spec']) ? 'Spec ' . $t['spec'] . ' ' . ($t['unit'] ?? '') . ' (±' . $t['tol'] . ')' : '');
        }
        foreach ($o as &$x) { $x['result'] = in_array('FAIL', $x['res'], true) ? 'FAIL' : (in_array('PASS', $x['res'], true) ? 'PASS' : (in_array('NA', $x['res'], true) ? 'NA' : ''));
            if ($x['fails']) $x['remark'] = implode('; ', $x['fails']); }
        return array_values($o);
    };
    $tests = $agg('tests'); $meas = $agg('meas'); $checks = $agg('checks');
    $tf = count(array_filter($tests, fn($x) => $x['result'] === 'FAIL')); $mf = count(array_filter($meas, fn($x) => $x['result'] === 'FAIL'));
    $cf = count(array_filter($checks, fn($x) => $x['result'] === 'FAIL')); $cp = count(array_filter($checks, fn($x) => $x['result'] === 'PASS')); $cn = count($checks) - $cf - $cp;
    $first = $items[0] ?? [];
    $metal = trim(($first['metal'] ?? '') . (!empty($first['plating']) && $first['plating'] !== '—' ? ' + ' . $first['plating'] : ''));
    $worst = function ($k) use ($items) { $o = ['POOR' => 3, 'AVERAGE' => 2, 'GOOD' => 1]; $w = null; foreach ($items as $i) if (!empty($i[$k]) && (!$w || $o[$i[$k]] > $o[$w])) $w = $i[$k]; return $w; };
    $photos = all('SELECT p.*, i.sku FROM item_photos p JOIN qc_items i ON i.id=p.item_id WHERE i.job_id=? ORDER BY p.id', [$jobId]);
    $defPh = []; $k = 1; foreach ($photos as $p) if ($p['kind'] === 'defect' && count($defPh) < 9) $defPh[] = $p + ['caption' => 'Defect Photo ' . $k++ . ': ' . $p['sku'] . ($p['remark'] ? ' ' . $p['remark'] : '')];
    $spec = special_photos($photos, true);
    $dates = array_filter(array_column($items, 'insp_date')); $insDate = $dates ? max($dates) : $job['scheduled_date'];
    $approvedBy = (int)max(array_map(fn($i) => (int)$i['approved_by'], $items) ?: [0]);
    $remarks = []; foreach ($items as $i) { $s = $st[$i['id']]; $r = $i['final_result'] ?: ($i['system_result'] ?: 'PENDING');
        $remarks[] = $i['sku'] . ' (' . number_format($s['rate'], 1) . '% rework' . ($i['rejected_qty'] ? ', ' . strtolower($s['primary']) : '') . ') — ' . ($r === 'CONDITIONAL' ? 'CONDITIONAL PASS' . ($i['concession_status'] ? ' / client ' . strtolower($i['concession_status']) : '') : $r); }
    $xrfAll = in_array('NOT OK', array_column($st, 'xrf'), true) ? 'NOT OK' : 'OK';
    ob_start(); ?>
<html><head><meta charset="utf-8"><?= rpt_css(true) ?></head><body>
<?= rpt_header(report_title($job), 'MULTI-DESIGN PO REPORT', $no, in_array($job['status'], ['APPROVED', 'EMAILED', 'CLOSED'], true) ? 'APPROVED · ALL DESIGNS LOCKED' : 'DRAFT — REVIEW IN PROGRESS') ?>
<div><?= bar('1. PURCHASE ORDER (PO) & INSPECTION HEADER DETAILS') ?>
<table>
 <tr><td class="lab">Inspection Date</td><td class="val"><?= fdate($insDate, 'd-M-Y') ?></td><td class="lab">Inspection Type</td><td class="val"><?= in_array(1, array_map('intval', array_column($items, 'reinspection')), true) ? 'RE-INSPECTION' : 'REGULAR' ?></td><td class="lab">QC Job No.</td><td class="val"><?= h($job['job_no']) ?></td></tr>
 <tr><td class="lab">Client Name</td><td class="val"><?= h(strtoupper($job['client_name'])) ?></td><td class="lab">Vendor / Factory Unit</td><td class="val"><?= h(strtoupper($job['vendor_name'])) ?></td><td class="lab">Factory Location</td><td class="val"><?= h(strtoupper((string)val('SELECT location FROM vendors WHERE id=?', [$job['vendor_id']]))) ?></td></tr>
 <tr><td class="lab">PO Number</td><td class="val"><?= h($job['po_no']) ?></td><td class="lab">Metal &amp; Plating Spec</td><td class="val"><?= h($metal) ?></td><td class="lab">Master Sample Ref.</td><td class="val"><?= h(($first['master_sample'] ?? '') ?: 'Approved Master Sample Available') ?></td></tr>
 <tr><td class="lab">Total Designs in PO</td><td class="val"><?= $n ?></td><td class="lab">QC Inspector ID</td><td class="val"><?= h($job['inspector_code'] . ' · ' . $job['inspector_name']) ?></td><td class="lab">Product Fail Rule</td><td class="val" style="color:#1f2937"><?= h(result_rule_text($job)) ?></td></tr>
</table></div>

<div class="sec"><?= bar('2. PO INSPECTION RESULT SUMMARY & EXECUTIVE KPIs') ?>
<table><tr><td class="lab" style="width:24%;font-size:8.4px">OVERALL PO INSPECTION DECISION</td><td class="c big <?= rpt_status_css($overall) ?>" style="width:18%"><?= h($overall) ?></td>
 <td class="c" style="font-weight:bold"><?= $pass + $cond ?> Design(s) PASSED (<?= h($passLbl) ?>)<?= $cond ? " incl. $cond conditional" : '' ?> | <?= $fail ?> Design(s) FAILED (<?= h($failLbl) ?>)</td></tr></table>
<table class="th"><tr><th>Total PO Qty</th><th>Offered Qty</th><th>Inspected Qty</th><th>Total Accepted Qty</th><th>Reject / Repair Qty</th><th>Overall Rej/Repair %</th><th>Designs Passed</th><th>Designs Failed</th></tr>
 <tr><td class="c big"><?= num($T['po']) ?></td><td class="c big"><?= num($T['off']) ?></td><td class="c big"><?= num($T['insp']) ?></td><td class="c big ok"><?= num($T['acc']) ?></td><td class="c big nok"><?= num($T['rej']) ?></td><td class="c big"><?= number_format($rate, 1) ?>%</td><td class="c big ok"><?= $pass + $cond ?></td><td class="c big nok"><?= $fail ?></td></tr></table>
<table class="zebra">
 <tr><td style="width:34%"><?= $aql ? 'AQL Acceptance (ISO 2859-1)' : "100% Quantity &amp; {$thrT}% Rejection Rule" ?></td><td class="c <?= $fail ? 'nok' : 'ok' ?>" style="width:12%"><?= $fail ? 'NOT OK' : 'OK' ?></td><td><?= $fail ? $fail . ' Design(s) breached ' . h($failLbl) . ' fail limit' : 'All designs within limit' ?></td></tr>
 <tr><td>On-Site Physical &amp; Lab Tests (PO Lot)</td><td class="c <?= $tf ? 'nok' : 'ok' ?>"><?= $tf ? 'NOT OK' : 'OK' ?></td><td><?= $tf ? $tf . ' On-site test observation(s) noted' : 'All on-site tests satisfactory' ?></td></tr>
 <tr><td>Data Measurement (Weight &amp; Size)</td><td class="c <?= $mf ? 'nok' : 'ok' ?>"><?= $mf ? 'NOT OK' : 'OK' ?></td><td><?= $mf ? $mf . ' parameter(s) out of tolerance' : 'Dimensions &amp; weight within tolerance' ?></td></tr>
 <tr><td><?= count($checks) ?>-Point Critical Quality Checkpoints</td><td class="c <?= $cf ? 'nok' : 'ok' ?>"><?= $cf ? 'NOT OK' : 'OK' ?></td><td><?= $cf ?> Failed, <?= $cp ?> Passed, <?= $cn ?> N/A</td></tr>
 <tr><td>Client Technical Specifications</td><td class="c <?= $fail ? 'nok' : 'ok' ?>"><?= $fail ? 'NOT OK' : 'OK' ?></td><td>Verified against PO &amp; Master Spec</td></tr>
</table></div>

<div class="sec" style="page-break-inside:auto"><?= bar('3. DESIGN-WISE INSPECTION BREAKDOWN (WITH ACTUAL PRODUCT IMAGE & ' . ($aql ? 'AQL RULE' : $thrT . '% FAIL RULE') . ')') ?>
<table class="th zebra"><tr><th>Sr.</th><th style="width:62px">Actual Product Image</th><th>Design No. / SKU</th><th>Category</th><th>PO Qty</th><th>Offered Qty</th><th>Inspected</th><th>Accepted Qty</th><th>Reject / Repair</th><th>Rej/Rep %</th><th>Critical</th><th>Major</th><th>Minor</th><th>XRF Micron</th><th style="width:20%">Primary Defect / QC Remarks</th><th>Product Status</th></tr>
<?php foreach ($items as $x => $i): $s = $st[$i['id']]; $r = $i['final_result'] ?: ($i['system_result'] ?: 'PENDING'); $img = product_image($i); ?>
 <tr><td class="c"><?= $x + 1 ?></td><td class="c" style="padding:2px"><?= $img ? '<img src="' . h($img) . '" style="width:56px;height:44px">' : '<span class="na">—</span>' ?></td><td class="c"><b><?= h($i['sku']) ?></b><?= $i['style_ref'] ? '<br><span class="na" style="font-weight:normal">' . h($i['style_ref']) . '</span>' : '' ?></td>
  <td class="c"><?= h($i['product']) ?></td><td class="c"><?= num($i['order_qty']) ?></td><td class="c"><?= num($i['ready_qty']) ?></td><td class="c"><?= num($i['inspected_qty']) ?></td><td class="c"><?= num($i['accepted_qty']) ?></td><td class="c"><b><?= num($i['rejected_qty']) ?></b></td>
  <td class="c"><b><?= number_format($s['rate'], 1) ?>%</b></td><td class="c"><?= $s['sev']['CRITICAL'] ?></td><td class="c"><?= $s['sev']['MAJOR'] ?></td><td class="c"><?= $s['sev']['MINOR'] ?></td>
  <td class="c <?= rpt_status_css($s['xrf']) ?>"><?= $s['xrf'] ?></td><td><?= h($i['rejected_qty'] ? $s['primary'] . ' (' . $i['rejected_qty'] . ' pcs)' : 'All pcs passed check') ?><?= $r === 'CONDITIONAL' && $i['concession_status'] ? '<br>Concession: ' . h($i['concession_status']) : '' ?></td>
  <td class="c <?= rpt_status_css($r) ?>"><?= h($r === 'CONDITIONAL' ? 'COND. PASS' : $r) ?></td></tr>
<?php endforeach ?>
 <tr class="tot"><td colspan="4" class="r">PO GRAND TOTAL (ALL DESIGNS)</td><td class="c"><?= num($T['po']) ?></td><td class="c"><?= num($T['off']) ?></td><td class="c"><?= num($T['insp']) ?></td><td class="c"><?= num($T['acc']) ?></td><td class="c"><?= num($T['rej']) ?></td><td class="c"><?= number_format($rate, 1) ?>%</td>
  <td class="c"><?= $T['C'] ?></td><td class="c"><?= $T['M'] ?></td><td class="c"><?= $T['m'] ?></td><td class="c <?= rpt_status_css($xrfAll) ?>"><?= $xrfAll ?></td>
  <td><?= $pass + $cond ?> Passed / <?= $fail ?> Failed (<?= $aql ? 'AQL' : '>=' . $thrT . '% rule' ?>)</td><td class="c <?= rpt_status_css($fail ? 'FAIL' : 'PASS') ?>"><?= $fail ? 'FAIL' : 'PASS' ?></td></tr>
</table></div>

<div class="sec"><?= bar('4. ON-SITE PHYSICAL & RELIABILITY TESTS (PO BATCH LEVEL)') ?>
<table class="th zebra"><tr><th style="width:34%">Test Parameter</th><th>Sample Tested (Pcs)</th><th>Test Result</th><th style="width:42%">Method / QC Remarks</th></tr>
<?php foreach ($tests as $t): $r = okn($t['result']); ?><tr><td><?= h($t['name']) ?></td><td class="c"><?= $r === 'NA' ? 'NA' : (int)$t['qty'] ?></td><td class="c <?= rpt_status_css($r) ?>"><?= $r ?></td><td><?= h($t['remark']) ?></td></tr><?php endforeach ?>
</table></div>

<div class="sec"><?= bar('5. DIMENSIONAL & WEIGHT MEASUREMENT') ?>
<table class="th zebra"><tr><th style="width:34%">Measurement Parameter</th><th>Sample Tested (Pcs)</th><th>Result</th><th style="width:42%">Recorded Values / Tolerance</th></tr>
<?php foreach ($meas as $m): $r = okn($m['result'] ?: 'NA'); ?><tr><td><?= h($m['name']) ?> (<?= strtolower($m['name']) === 'weight' ? 'Digital scale' : 'Digital Vernier' ?>)</td><td class="c"><?= (int)$m['qty'] ?></td><td class="c <?= rpt_status_css($r) ?>"><?= $r ?></td><td><?= h($m['remark']) ?></td></tr><?php endforeach ?>
</table></div>

<div class="sec"><?= bar('6. CONSOLIDATED DEFECTS LOGGED ACROSS PO') ?>
<table class="th zebra"><tr><th style="width:24%">Defect Category</th><th>Specific Defect Description &amp; Affected SKUs</th><th style="width:8%">Critical</th><th style="width:8%">Major</th><th style="width:8%">Minor</th><th style="width:9%">Total Defects</th></tr>
<?php $by = []; foreach ($items as $i) foreach ($st[$i['id']]['def'] as $d) { $kk = $i['sku'] . ': ' . $d['type']; $by[$d['category']]['t'][$kk] = ($by[$d['category']]['t'][$kk] ?? 0) + $d['qty']; $by[$d['category']][$d['severity']] = ($by[$d['category']][$d['severity']] ?? 0) + $d['qty']; }
  foreach ($by as $cat => $b): $tt = ($b['CRITICAL'] ?? 0) + ($b['MAJOR'] ?? 0) + ($b['MINOR'] ?? 0); ?>
 <tr><td><?= h($cat) ?></td><td><?= h(implode('; ', array_map(fn($kx, $v) => "$kx ($v pcs)", array_keys($b['t']), $b['t']))) ?></td><td class="c"><?= $b['CRITICAL'] ?? '' ?></td><td class="c"><?= $b['MAJOR'] ?? '' ?></td><td class="c"><?= $b['MINOR'] ?? '' ?></td><td class="c"><b><?= $tt ?></b></td></tr><?php endforeach ?>
<?php if (!$by): ?><tr><td colspan="6" class="c na">No defects logged</td></tr><?php endif ?>
 <tr class="tot"><td colspan="2" class="r">TOTAL PO DEFECTS OBSERVED</td><td class="c"><?= $T['C'] ?></td><td class="c"><?= $T['M'] ?></td><td class="c"><?= $T['m'] ?></td><td class="c"><?= $T['C'] + $T['M'] + $T['m'] ?></td></tr>
</table></div>

<div class="sec"><?= bar('7. ' . count($checks) . '-POINT CRITICAL QUALITY CHECKPOINTS OVERVIEW (PO LEVEL)') ?>
<table class="th zebra"><tr><th style="width:44%">Critical Quality Checkpoint</th><th style="width:14%">Status (PASS / FAIL / NA)</th><th>QC Inspector Remarks</th></tr>
<?php foreach ($checks as $x => $c): ?><tr><td><?= $x + 1 ?>. <?= h($c['name']) ?></td><td class="c <?= rpt_status_css($c['result']) ?>"><?= h($c['result'] ?: '—') ?></td><td><?= h($c['remark']) ?></td></tr><?php endforeach ?>
</table></div>

<div class="sec"><?= bar('8. CLIENT TECHNICAL SPECIFICATIONS & SPECIAL REQUIREMENTS') ?><?= spec_cell($metal, $job) ?></div>
<div class="sec"><?= bar('9. DEFECT EVIDENCE PHOTO LOG (MULTI-DESIGN DEFECT GRID)') ?><?= photo_grid($defPh, 3, 'Defect Photo', $defPh ? 0 : 3) ?></div>
<div class="sec"><?= bar('10. SPECIAL & OTHER IMPORTANT PICTURES (XRF REPORT, WEIGHT SCALE, STAMPING & PACKAGING)') ?><?= photo_grid($spec, 3, 'Special Photo', 3) ?></div>

<div class="sec"><?= bar('11. INSPECTOR REMARKS, CORRECTIVE ACTION (CAPA) & FACTORY EVALUATION') ?>
<table><tr><td style="padding:5px"><b>PO Summary Remarks:</b> <?= $aql ? 'As per AQL (ISO 2859-1) plan' : "As per {$thrT}% rejection/repair threshold rule" ?>: <?= h(implode('; ', $remarks)) ?>.
<?php foreach ($items as $i) if ($i['action_required'] || $i['manager_note']) echo '<br><b>' . h($i['sku']) . ':</b> ' . h(trim($i['action_required'] . ' ' . $i['manager_note'])); ?></td></tr></table>
<?= factory_eval([$worst('factory_coop'), $worst('factory_line'), $worst('factory_ready')]) ?>
<table><tr><td style="width:33%;height:34px;vertical-align:top"><b>Inspected By (JEWELSETU QC):</b> <?= h($job['inspector_code'] . ' · ' . $job['inspector_name']) ?></td>
<td style="width:34%;vertical-align:top"><b>Approved By:</b> <?= h(approver($approvedBy ?: null)) ?></td><td style="vertical-align:top"><b>Vendor / Factory Acknowledgement:</b></td></tr></table></div>
<?= terms_block() ?>
</body></html>
<?php
    $html = ob_get_clean();
    $fname = file_safe($no) . '.pdf';
    $file = $save ? UPLOAD_DIR . '/reports/' . $fname : null;
    $pdf = pdf_render($html, true, $file);
    if ($file) q('UPDATE qc_jobs SET po_pdf=? WHERE id=?', ['reports/' . $fname, $jobId]);
    return [$pdf, $fname, $file];
}
