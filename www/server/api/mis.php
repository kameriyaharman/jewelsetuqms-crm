<?php
/* =============================================================================
 * MIS & Business Intelligence hub (Analytics): global filters, 5 views, exports.
 * One row = one inspected QC lot (SKU / design session). Date = inspection date.
 * QC visit = unique (date + vendor + client + inspector) — 25 SKUs of one PO on one
 * day at one factory by one inspector is 1 visit.
 * ========================================================================== */

const MIS_SCOPES = ['SAMPLE' => 'Sample QC', 'BULK100' => 'Bulk 100%', 'AQL' => 'Bulk AQL', 'DEFINED' => 'Bulk defined qty', 'REINSP' => 'Re-inspection'];

/** Filters from the query string; client logins are locked to their own client. */
function mis_filters(): array {
    $p = (string)in_('preset', 'this');
    if ($p === 'last') { $from = date('Y-m-01', strtotime('first day of last month')); $to = date('Y-m-t', strtotime('last day of last month')); }
    elseif ($p === 'custom' && in_('from')) { $from = (string)in_('from'); $to = (string)(in_('to') ?: today()); if ($to < $from) [$from, $to] = [$to, $from]; }
    elseif ($p === 'week') { $from = date('Y-m-d', strtotime('monday this week')); $to = today(); }
    elseif ($p === 'ytd') { $from = date('Y-01-01'); $to = today(); }
    else { $p = 'this'; $from = date('Y-m-01'); $to = date('Y-m-t'); }
    $F = ['preset' => $p, 'from' => $from, 'to' => $to, 'client' => (int)in_('client'), 'vendor' => (int)in_('vendor'), 'insp' => (int)in_('insp'),
        'scope' => (string)in_('scope'), 'q' => trim((string)in_('q'))];
    if (role() === 'client') $F['client'] = (int)user()['client_id'];
    return $F;
}
function mis_label(array $F): string {
    $parts = [fdate($F['from'], 'd M Y') . ' – ' . fdate($F['to'], 'd M Y')];
    if ($F['client']) $parts[] = 'Client ' . val('SELECT code FROM clients WHERE id=?', [$F['client']]);
    if ($F['vendor']) $parts[] = 'Vendor ' . val('SELECT name FROM vendors WHERE id=?', [$F['vendor']]);
    if ($F['insp']) $parts[] = 'Inspector ' . val('SELECT emp_code FROM users WHERE id=?', [$F['insp']]);
    if ($F['scope']) $parts[] = MIS_SCOPES[$F['scope']] ?? $F['scope'];
    if ($F['q'] !== '') $parts[] = '“' . $F['q'] . '”';
    return implode(' · ', $parts);
}
function mis_scope_key(array $r): string {
    if ($r['parent_id'] || (int)$r['reinspection'] === 1) return 'REINSP';
    $j = item_job($r, $r);
    if ($j['scope'] === 'SAMPLE') return 'SAMPLE';
    return $j['bulk_mode'] === 'AQL' ? 'AQL' : ($j['bulk_mode'] === 'DEFINED' ? 'DEFINED' : 'BULK100');
}
/** Inspected lots in range matching the filters. */
function mis_rows(array $F, ?string $from = null, ?string $to = null): array {
    $D = "COALESCE(i.insp_date, SUBSTR(i.submitted_at,1,10), SUBSTR(i.approved_at,1,10))";
    $sql = "SELECT i.*, $D d, j.job_no, j.po_no, j.client_id, j.vendor_id, j.inspector_id, j.scope, j.bulk_mode, j.aql_major, j.defined_qty, j.insp_level,
        c.code client_code, c.name client_name, v.name vendor_name, u.emp_code insp_code, u.name insp_name
        FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id JOIN clients c ON c.id=j.client_id JOIN vendors v ON v.id=j.vendor_id LEFT JOIN users u ON u.id=j.inspector_id
        WHERE i.status IN ('SUBMITTED','UNDER_REVIEW','RETURNED','APPROVED') AND j.status<>'CANCELLED' AND $D BETWEEN ? AND ?";
    $p = [$from ?? $F['from'], $to ?? $F['to']];
    foreach (['client' => 'j.client_id', 'vendor' => 'j.vendor_id', 'insp' => 'j.inspector_id'] as $k => $col) if ($F[$k]) { $sql .= " AND $col=?"; $p[] = $F[$k]; }
    if ($F['q'] !== '') { $sql .= ' AND (i.sku LIKE ? OR j.po_no LIKE ? OR j.job_no LIKE ? OR i.report_no LIKE ?)'; array_push($p, "%{$F['q']}%", "%{$F['q']}%", "%{$F['q']}%", "%{$F['q']}%"); }
    $rows = all($sql . ' ORDER BY d, j.id, i.id', $p);
    foreach ($rows as &$r) { $r['sk'] = mis_scope_key($r); $r['scope_name'] = MIS_SCOPES[$r['sk']]; }
    unset($r);
    if ($F['scope']) $rows = array_values(array_filter($rows, fn($r) => $r['sk'] === $F['scope']));
    return $rows;
}
function mis_pct($a, $b): float { return $b > 0 ? round($a * 100 / $b, 2) : 0.0; }
/** Totals for any group of lots. */
function mis_sum(array $rows): array {
    $s = ['lots' => count($rows), 'designs' => count(array_unique(array_column($rows, 'sku'))), 'visits' => count(array_unique(array_map(fn($r) => $r['d'] . '|' . $r['vendor_id'] . '|' . $r['client_id'] . '|' . $r['inspector_id'], $rows))),
        'offered' => 0, 'insp' => 0, 'acc' => 0, 'rej' => 0, 'pass' => 0, 'fail' => 0, 'cond' => 0, 'fp_i' => 0, 'fp_a' => 0, 'days' => count(array_unique(array_column($rows, 'd')))];
    foreach ($rows as $r) {
        $s['offered'] += (int)$r['ready_qty']; $s['insp'] += (int)$r['inspected_qty']; $s['acc'] += (int)$r['accepted_qty']; $s['rej'] += (int)$r['rejected_qty'];
        $res = $r['status'] === 'APPROVED' ? $r['final_result'] : null;
        if ($res === 'PASS') $s['pass']++; elseif ($res === 'FAIL') $s['fail']++; elseif ($res === 'CONDITIONAL') $s['cond']++;
        if (!$r['parent_id'] && (int)$r['return_count'] === 0) { $s['fp_i'] += (int)$r['inspected_qty']; $s['fp_a'] += (int)$r['accepted_qty']; }
    }
    $s['rate'] = mis_pct($s['rej'], $s['insp']); $s['fpy'] = mis_pct($s['fp_a'], $s['fp_i']);
    return $s;
}
function mis_group(array $rows, string $key): array { $g = []; foreach ($rows as $r) $g[$r[$key]][] = $r; return $g; }

function mis_data(): array {
    $F = mis_filters(); $rows = mis_rows($F);
    $ids = array_map(fn($r) => (int)$r['id'], $rows);
    $defs = $ids ? all('SELECT item_id, category, type, severity, qty FROM item_defects WHERE item_id IN (' . implode(',', $ids) . ')') : [];
    $byItem = mis_group($defs, 'item_id'); $rowById = array_column($rows, null, 'id');
    $topDef = function (array $rs) use ($byItem) { $c = []; foreach ($rs as $r) foreach ($byItem[$r['id']] ?? [] as $d) $c[$d['type']] = ($c[$d['type']] ?? 0) + (int)$d['qty']; arsort($c); return $c ? array_key_first($c) . ' (' . reset($c) . ')' : '—'; };

    // previous period of the same length, for vendor trend
    $len = (strtotime($F['to']) - strtotime($F['from'])) / 86400 + 1;
    $pf = date('Y-m-d', strtotime($F['from'] . ' -' . (int)$len . ' days')); $pt = date('Y-m-d', strtotime($F['from'] . ' -1 day'));
    $prevV = []; foreach (mis_group(mis_rows($F, $pf, $pt), 'vendor_id') as $vid => $rs) $prevV[$vid] = mis_sum($rs)['rate'];

    // 1. client-wise MIS & billing summary
    $clients = [];
    foreach (mis_group($rows, 'client_id') as $cid => $rs) { $s = mis_sum($rs);
        $clients[] = ['id' => $cid, 'code' => $rs[0]['client_code'], 'name' => $rs[0]['client_name'], 'vendors' => count(array_unique(array_column($rs, 'vendor_id'))), 'vendor_names' => implode(', ', array_unique(array_column($rs, 'vendor_name')))] + $s; }
    usort($clients, fn($a, $b) => $b['insp'] <=> $a['insp']);
    // 2. vendor-wise performance
    $mapped = []; foreach (all('SELECT m.vendor_id, c.code FROM client_vendor m JOIN clients c ON c.id=m.client_id') as $m) $mapped[$m['vendor_id']][] = $m['code'];
    $vendors = [];
    foreach (mis_group($rows, 'vendor_id') as $vid => $rs) { $s = mis_sum($rs); $prev = $prevV[$vid] ?? null;
        $vendors[] = ['id' => $vid, 'name' => $rs[0]['vendor_name'], 'clients' => implode(', ', $mapped[$vid] ?? []), 'top' => $topDef($rs), 'prev_rate' => $prev,
            'trend' => $prev === null ? 'new' : ($s['rate'] < $prev - 0.5 ? 'better' : ($s['rate'] > $prev + 0.5 ? 'worse' : 'same'))] + $s; }
    usort($vendors, fn($a, $b) => $b['rate'] <=> $a['rate']);
    // 3. inspector productivity + daily activity
    $insp = [];
    foreach (mis_group(array_filter($rows, fn($r) => $r['inspector_id']), 'inspector_id') as $uid => $rs) { $s = mis_sum($rs);
        $tt = []; foreach ($rs as $r) if ($r['started_at'] && $r['submitted_at']) $tt[] = (strtotime($r['submitted_at']) - strtotime($r['started_at'])) / 3600;
        $appr = array_filter($rs, fn($r) => $r['status'] === 'APPROVED');
        $insp[] = ['id' => $uid, 'code' => $rs[0]['insp_code'], 'name' => $rs[0]['insp_name'], 'avg_day' => $s['days'] ? round($s['insp'] / $s['days'], 1) : 0,
            'tat' => $tt ? round(array_sum($tt) / count($tt), 1) : null, 'returned' => count(array_filter($rs, fn($r) => (int)$r['return_count'] > 0)),
            'fta' => mis_pct(count(array_filter($appr, fn($r) => (int)$r['return_count'] === 0)), count($appr))] + $s; }
    usort($insp, fn($a, $b) => $b['insp'] <=> $a['insp']);
    $daily = [];
    foreach ($rows as $r) { $k = $r['d'] . '|' . $r['inspector_id'] . '|' . $r['vendor_id'] . '|' . $r['client_id'];
        $daily[$k] ??= ['d' => $r['d'], 'insp' => trim(($r['insp_code'] ?? '—') . ' · ' . ($r['insp_name'] ?? '')), 'vendor' => $r['vendor_name'], 'client' => $r['client_code'], 'pos' => [], 'skus' => [], 'pcs' => 0, 'acc' => 0, 'rej' => 0];
        $daily[$k]['pos'][$r['po_no']] = 1; $daily[$k]['skus'][$r['sku']] = 1; $daily[$k]['pcs'] += (int)$r['inspected_qty']; $daily[$k]['acc'] += (int)$r['accepted_qty']; $daily[$k]['rej'] += (int)$r['rejected_qty']; }
    $daily = array_values(array_map(fn($x) => array_merge($x, ['pos' => implode(', ', array_keys($x['pos'])), 'skus' => count($x['skus'])]), $daily));
    usort($daily, fn($a, $b) => strcmp($b['d'], $a['d']) ?: strcmp($a['insp'], $b['insp']));
    // 4. design / SKU master register
    $register = array_map(fn($r) => ['id' => (int)$r['id'], 'job_id' => (int)$r['job_id'], 'd' => $r['d'], 'job_no' => $r['job_no'], 'report_no' => $r['report_no'], 'client' => $r['client_code'], 'vendor' => $r['vendor_name'],
        'po' => $r['po_no'], 'sku' => $r['sku'], 'lot' => (int)($r['lot_seq'] ?: 1), 'product' => $r['product'], 'scope' => $r['scope_name'], 'order' => (int)$r['order_qty'], 'offered' => (int)$r['ready_qty'],
        'insp' => (int)$r['inspected_qty'], 'acc' => (int)$r['accepted_qty'], 'rej' => (int)$r['rejected_qty'], 'result' => $r['status'] === 'APPROVED' ? $r['final_result'] : $r['status'],
        'inspector' => $r['insp_code'] ? $r['insp_code'] . ' · ' . $r['insp_name'] : '—', 'defects' => implode(', ', array_map(fn($d) => $d['type'] . ' ×' . $d['qty'], $byItem[$r['id']] ?? []))], $rows);
    // 5. defect pareto & root cause
    $types = []; $cats = []; $vxc = []; $pxc = [];
    foreach ($defs as $d) { $r = $rowById[$d['item_id']]; $q = (int)$d['qty']; $sev = strtoupper((string)$d['severity']) ?: 'MINOR'; $cat = $d['category'] ?: 'Other';
        $k = $cat . '|' . $d['type'];
        $types[$k] ??= ['category' => $cat, 'type' => $d['type'], 'CRITICAL' => 0, 'MAJOR' => 0, 'MINOR' => 0, 'total' => 0, 'vendors' => [], 'products' => []];
        $types[$k][$sev] = ($types[$k][$sev] ?? 0) + $q; $types[$k]['total'] += $q; $types[$k]['vendors'][$r['vendor_name']] = ($types[$k]['vendors'][$r['vendor_name']] ?? 0) + $q; $types[$k]['products'][$r['product']] = ($types[$k]['products'][$r['product']] ?? 0) + $q;
        $cats[$cat] = ($cats[$cat] ?? 0) + $q;
        $vxc[$r['vendor_name']][$cat] = ($vxc[$r['vendor_name']][$cat] ?? 0) + $q;
        $pxc[$r['product'] ?: '—'][$cat] = ($pxc[$r['product'] ?: '—'][$cat] ?? 0) + $q; }
    $types = array_values($types); usort($types, fn($a, $b) => $b['total'] <=> $a['total']);
    $tot = array_sum(array_column($types, 'total')); $cum = 0;
    foreach ($types as &$t) { $cum += $t['total']; $t['pct'] = mis_pct($t['total'], $tot); $t['cum'] = mis_pct($cum, $tot);
        arsort($t['vendors']); arsort($t['products']); $t['top_vendor'] = array_key_first($t['vendors']); $t['top_product'] = array_key_first($t['products']); unset($t['vendors'], $t['products']); }
    unset($t); arsort($cats);
    $catList = array_keys($cats);
    $mx = fn($m) => array_map(fn($name, $row) => ['name' => $name, 'total' => array_sum($row)] + array_combine($catList, array_map(fn($c) => $row[$c] ?? 0, $catList)), array_keys($m), $m);
    $byVendor = $mx($vxc); usort($byVendor, fn($a, $b) => $b['total'] <=> $a['total']);
    $byProduct = $mx($pxc); usort($byProduct, fn($a, $b) => $b['total'] <=> $a['total']);

    $opts = ['clients' => role() === 'client' ? [] : all("SELECT id, code, name FROM clients ORDER BY code"), 'vendors' => all("SELECT id, name FROM vendors ORDER BY name"),
        'inspectors' => is_internal() ? all("SELECT id, emp_code, name FROM users WHERE role='inspector' ORDER BY emp_code") : [], 'scopes' => MIS_SCOPES];
    return ['f' => $F, 'label' => mis_label($F), 'kpi' => mis_sum($rows), 'clients' => $clients, 'vendors' => $vendors, 'inspectors' => is_internal() ? $insp : [], 'daily' => is_internal() ? $daily : [],
        'register' => $register, 'defects' => ['types' => $types, 'cats' => $catList, 'cat_totals' => $cats, 'by_vendor' => $byVendor, 'by_product' => $byProduct, 'total' => $tot], 'opts' => $opts,
        'company' => setting('company_name', 'JEWELSETU Quality Services')];
}
api('GET', 'mis', [...R_INT, 'client'], fn() => mis_data());

/* ---------- export: sheets ---------- */
function mis_sheets(array $d): array {
    $k = $d['kpi'];
    $S = [];
    $S['Client Summary'] = array_merge([['Client', 'Client name', 'Active vendors', 'QC visits (man-days)', 'Designs / SKUs', 'Offered qty', 'Inspected pcs', 'Accepted pcs', 'Rejected / repair pcs', 'Rejection %', 'FPY %']],
        array_map(fn($r) => [$r['code'], $r['name'], $r['vendors'], $r['visits'], $r['designs'], $r['offered'], $r['insp'], $r['acc'], $r['rej'], $r['rate'], $r['fpy']], $d['clients']),
        [['TOTAL', '', '', $k['visits'], $k['designs'], $k['offered'], $k['insp'], $k['acc'], $k['rej'], $k['rate'], $k['fpy']]]);
    $S['Vendor Performance'] = array_merge([['Vendor', 'Mapped clients', 'QC visits', 'Unique designs', 'Inspected pcs', 'Pass lots', 'Fail lots', 'Conditional lots', 'Rejected / repair pcs', 'Rejection %', 'Prev. period %', 'Top recurring defect', 'Trend']],
        array_map(fn($r) => [$r['name'], $r['clients'], $r['visits'], $r['designs'], $r['insp'], $r['pass'], $r['fail'], $r['cond'], $r['rej'], $r['rate'], $r['prev_rate'] ?? '—', $r['top'], ucfirst($r['trend'])], $d['vendors']));
    if ($d['inspectors'] || $d['daily']) {
        $S['Team Productivity'] = array_merge([['Inspector ID', 'Name', 'Factory visits', 'Designs / SKUs', 'Pcs checked', 'Avg pcs / day', 'Avg report TAT (h)', 'Reports returned', 'First-time approval %']],
            array_map(fn($r) => [$r['code'], $r['name'], $r['visits'], $r['designs'], $r['insp'], $r['avg_day'], $r['tat'] ?? '—', $r['returned'], $r['fta']], $d['inspectors']),
            [[]], [['DAILY ACTIVITY LOG'], ['Date', 'Inspector', 'Vendor', 'Client', 'PO(s)', 'Designs', 'Pcs checked', 'Accepted', 'Rejected / repair']],
            array_map(fn($r) => [$r['d'], $r['insp'], $r['vendor'], $r['client'], $r['pos'], $r['skus'], $r['pcs'], $r['acc'], $r['rej']], $d['daily']));
    }
    $R = $d['register'];
    $S['SKU Register'] = array_merge([['Date', 'Job card no.', 'Report no.', 'Client', 'Vendor', 'PO no.', 'SKU / design no.', 'Lot', 'Product', 'QC scope', 'Order qty', 'Offered qty', 'Inspected pcs', 'Accepted pcs', 'Rejected / repair pcs', 'Result', 'Inspector', 'Defects']],
        array_map(fn($r) => [$r['d'], $r['job_no'], $r['report_no'] ?? '', $r['client'], $r['vendor'], $r['po'], $r['sku'], $r['lot'], $r['product'], $r['scope'], $r['order'], $r['offered'], $r['insp'], $r['acc'], $r['rej'], $r['result'], $r['inspector'], $r['defects']], $R),
        [['GRAND TOTAL', count($R) . ' lots', '', '', '', '', count(array_unique(array_column($R, 'sku'))) . ' designs', '', '', '', array_sum(array_column($R, 'order')), array_sum(array_column($R, 'offered')), array_sum(array_column($R, 'insp')), array_sum(array_column($R, 'acc')), array_sum(array_column($R, 'rej'))]]);
    $D = $d['defects'];
    $S['Defect Analysis'] = array_merge([['Defect category', 'Defect type', 'Critical', 'Major', 'Minor', 'Total pcs', '% of defects', 'Cumulative %', 'Most in vendor', 'Most in product']],
        array_map(fn($t) => [$t['category'], $t['type'], $t['CRITICAL'], $t['MAJOR'], $t['MINOR'], $t['total'], $t['pct'], $t['cum'], $t['top_vendor'], $t['top_product']], $D['types']),
        [[]], [['VENDOR × DEFECT CATEGORY'], array_merge(['Vendor'], $D['cats'], ['Total'])], array_map(fn($r) => array_merge([$r['name']], array_map(fn($c) => $r[$c], $D['cats']), [$r['total']]), $D['by_vendor']),
        [[]], [['PRODUCT CATEGORY × DEFECT CATEGORY'], array_merge(['Product'], $D['cats'], ['Total'])], array_map(fn($r) => array_merge([$r['name']], array_map(fn($c) => $r[$c], $D['cats']), [$r['total']]), $D['by_product']));
    return $S;
}
api('GET', 'mis/export', [...R_INT, 'client'], function () {
    require_once ROOT . '/app/xlsx.php';
    $d = mis_data(); $kind = (string)in_('kind', 'view');
    $stamp = $d['f']['from'] . '_to_' . $d['f']['to'];
    if ($kind === 'pdf') {
        require_once ROOT . '/app/pdf.php';
        [$pdf] = mis_pdf($d);
        header('Content-Type: application/pdf'); header('Content-Disposition: attachment; filename="QTRACK_MIS_Executive_Summary_' . $stamp . '.pdf"'); echo $pdf; exit;
    }
    $sheets = mis_sheets($d);
    if ($kind === 'view') {
        $map = ['client' => 'Client Summary', 'vendor' => 'Vendor Performance', 'team' => 'Team Productivity', 'sku' => 'SKU Register', 'defects' => 'Defect Analysis'];
        $name = $map[(string)in_('tab', 'client')] ?? 'Client Summary';
        $sheets = [$name => $sheets[$name] ?? [['No data']]];
        $file = 'QTRACK_' . str_replace(' ', '_', $name) . '_' . $stamp . '.xlsx';
    } else $file = 'QTRACK_Monthly_MIS_' . $stamp . '.xlsx';
    $title = 'JEWELSETU Q-TRACK · ' . $d['label'];
    foreach ($sheets as $n => $rows) $sheets[$n] = array_merge([[$title], []], $rows);
    $bin = xlsx_book($sheets, 2);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'); header('Content-Disposition: attachment; filename="' . $file . '"'); echo $bin; exit;
});

/** Branded executive summary PDF (A4 portrait). */
function mis_pdf(array $d): array {
    $k = $d['kpi']; $D = $d['defects'];
    $logo = is_file(ROOT . '/../img/logo.png') ? 'data:image/png;base64,' . base64_encode(file_get_contents(ROOT . '/../img/logo.png')) : null;
    ob_start(); ?>
<html><head><meta charset="utf-8"><style>
@page{margin:16mm 13mm 16mm 13mm} body{font-family:DejaVu Sans,sans-serif;font-size:9px;color:#1d2b33}
.hd{background:#0f3d45;color:#fff;padding:12px 14px;border-bottom:3px solid #c9a24b} .hd td{color:#fff;vertical-align:middle} .hd h1{margin:0;font-size:15px;letter-spacing:1px} .hd .s{color:#e7d4a2;font-size:9px}
h2{font-size:11px;color:#0f3d45;border-bottom:1.5px solid #c9a24b;padding-bottom:2px;margin:14px 0 6px}
table.k{width:100%;border-collapse:separate;border-spacing:5px} table.k td{background:#f4f5f8;border:1px solid #d8dde4;border-radius:6px;padding:7px;text-align:center;width:16%} table.k b{display:block;font-size:14px;color:#0f3d45} table.k span{font-size:7.5px;color:#6b7780;text-transform:uppercase}
table.t{width:100%;border-collapse:collapse} table.t th{background:#0f3d45;color:#fff;font-size:7.5px;padding:4px;text-align:left} table.t td{border-bottom:1px solid #e6e9ee;padding:4px;font-size:8px} table.t tr:nth-child(even) td{background:#f8fafb}
.n{text-align:right} .tot td{font-weight:bold;background:#f6eed9 !important} .bad{color:#b0303f;font-weight:bold} .ok{color:#1d7a4b;font-weight:bold}
.ft{position:fixed;bottom:-10mm;left:0;right:0;text-align:center;color:#6b7780;font-size:7.5px}
</style></head><body>
<div class="hd"><table width="100%"><tr><?php if ($logo): ?><td width="44"><img src="<?= $logo ?>" style="height:36px;background:#fff;border-radius:6px;padding:2px"></td><?php endif; ?>
<td><h1>MONTHLY QC MIS — EXECUTIVE SUMMARY</h1><div class="s"><?= h($d['company']) ?> · Q-TRACK QC System</div></td><td style="text-align:right" class="s"><?= h($d['label']) ?><br>Generated <?= date('d M Y, h:i A') ?></td></tr></table></div>
<h2>KEY NUMBERS</h2>
<table class="k"><tr><td><b><?= num($k['visits']) ?></b><span>QC visits</span></td><td><b><?= num($k['designs']) ?></b><span>Designs / SKUs</span></td><td><b><?= num($k['lots']) ?></b><span>QC lots</span></td>
<td><b><?= num($k['insp']) ?></b><span>Inspected pcs</span></td><td><b><?= num($k['rej']) ?></b><span>Rejected / repair</span></td><td><b><?= number_format($k['rate'], 2) ?>%</b><span>Rejection %</span></td></tr>
<tr><td><b><?= num($k['offered']) ?></b><span>Offered qty</span></td><td><b><?= num($k['acc']) ?></b><span>Accepted pcs</span></td><td><b><?= $k['pass'] ?></b><span>Pass lots</span></td><td><b><?= $k['fail'] ?></b><span>Fail lots</span></td><td><b><?= $k['cond'] ?></b><span>Conditional</span></td><td><b><?= number_format($k['fpy'], 1) ?>%</b><span>First pass yield</span></td></tr></table>
<h2>CLIENT-WISE SUMMARY</h2>
<table class="t"><tr><th>Client</th><th class="n">Vendors</th><th class="n">QC visits</th><th class="n">Designs</th><th class="n">Offered</th><th class="n">Inspected</th><th class="n">Accepted</th><th class="n">Rej / repair</th><th class="n">Rej %</th><th class="n">FPY %</th></tr>
<?php foreach ($d['clients'] as $r): ?><tr><td><b><?= h($r['code']) ?></b> <?= h($r['name']) ?></td><td class="n"><?= $r['vendors'] ?></td><td class="n"><?= $r['visits'] ?></td><td class="n"><?= $r['designs'] ?></td><td class="n"><?= num($r['offered']) ?></td><td class="n"><?= num($r['insp']) ?></td><td class="n"><?= num($r['acc']) ?></td><td class="n"><?= num($r['rej']) ?></td><td class="n"><?= number_format($r['rate'], 2) ?></td><td class="n"><?= number_format($r['fpy'], 1) ?></td></tr><?php endforeach ?>
<tr class="tot"><td>TOTAL</td><td></td><td class="n"><?= $k['visits'] ?></td><td class="n"><?= $k['designs'] ?></td><td class="n"><?= num($k['offered']) ?></td><td class="n"><?= num($k['insp']) ?></td><td class="n"><?= num($k['acc']) ?></td><td class="n"><?= num($k['rej']) ?></td><td class="n"><?= number_format($k['rate'], 2) ?></td><td class="n"><?= number_format($k['fpy'], 1) ?></td></tr></table>
<h2>VENDOR PERFORMANCE</h2>
<table class="t"><tr><th>Vendor</th><th class="n">Visits</th><th class="n">Designs</th><th class="n">Inspected</th><th class="n">Pass</th><th class="n">Fail</th><th class="n">Cond.</th><th class="n">Rej pcs</th><th class="n">Rej %</th><th>Top recurring defect</th><th>Trend</th></tr>
<?php foreach ($d['vendors'] as $r): ?><tr><td><b><?= h($r['name']) ?></b></td><td class="n"><?= $r['visits'] ?></td><td class="n"><?= $r['designs'] ?></td><td class="n"><?= num($r['insp']) ?></td><td class="n"><?= $r['pass'] ?></td><td class="n"><?= $r['fail'] ?></td><td class="n"><?= $r['cond'] ?></td><td class="n"><?= num($r['rej']) ?></td>
<td class="n <?= $r['rate'] > 5 ? 'bad' : '' ?>"><?= number_format($r['rate'], 2) ?></td><td><?= h($r['top']) ?></td><td class="<?= $r['trend'] === 'better' ? 'ok' : ($r['trend'] === 'worse' ? 'bad' : '') ?>"><?= ['better' => '▼ Improving', 'worse' => '▲ Worse', 'same' => '= Stable', 'new' => 'New'][$r['trend']] ?></td></tr><?php endforeach ?></table>
<h2>TOP DEFECTS (PARETO)</h2>
<?php if (!$D['types']): ?><p>No defects recorded in this period.</p><?php else: ?>
<table class="t"><tr><th>#</th><th>Category</th><th>Defect</th><th class="n">Critical</th><th class="n">Major</th><th class="n">Minor</th><th class="n">Total pcs</th><th class="n">%</th><th class="n">Cum %</th><th>Most in vendor</th><th>Most in product</th></tr>
<?php foreach (array_slice($D['types'], 0, 12) as $n => $t): ?><tr><td><?= $n + 1 ?></td><td><?= h($t['category']) ?></td><td><b><?= h($t['type']) ?></b></td><td class="n"><?= $t['CRITICAL'] ?></td><td class="n"><?= $t['MAJOR'] ?></td><td class="n"><?= $t['MINOR'] ?></td><td class="n"><b><?= $t['total'] ?></b></td><td class="n"><?= number_format($t['pct'], 1) ?></td><td class="n"><?= number_format($t['cum'], 1) ?></td><td><?= h($t['top_vendor']) ?></td><td><?= h($t['top_product']) ?></td></tr><?php endforeach ?></table>
<?php endif; ?>
<p style="margin-top:12px;color:#6b7780;font-size:7.5px">QC visit = unique date + vendor + client + inspector. Rejection % = rejected / repair pcs ÷ inspected pcs. First pass yield = accepted ÷ inspected on first-time lots (no re-inspection, not returned). Detailed SKU register and defect matrix: Monthly MIS workbook (Excel).</p>
<div class="ft"><?= h($d['company']) ?> · Confidential — prepared for client review</div>
</body></html>
<?php
    $html = ob_get_clean();
    $dp = new Dompdf\Dompdf(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
    $dp->loadHtml($html); $dp->setPaper('A4', 'portrait'); $dp->render();
    $dp->getCanvas()->page_text(520, 815, 'Page {PAGE_NUM} of {PAGE_COUNT}', null, 7, [0.42, 0.47, 0.5]);
    return [$dp->output()];
}
