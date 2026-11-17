<?php
/** Aggregation helpers (done in PHP so the SQL stays portable between MySQL and SQLite). */
function approved_items(array $f = []): array {
    [$sc, $sp] = job_scope('j');
    $sql = "SELECT i.*, j.client_id, j.vendor_id, j.inspector_id, j.po_no, j.job_no, c.code client_code, v.name vendor_name, u.emp_code insp_code, u.name insp_name
            FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id JOIN clients c ON c.id=j.client_id JOIN vendors v ON v.id=j.vendor_id
            LEFT JOIN users u ON u.id=j.inspector_id WHERE i.status='APPROVED' AND $sc";
    $p = $sp;
    foreach (['client_id', 'vendor_id', 'inspector_id'] as $k) if (!empty($f[$k])) { $sql .= " AND j.$k=?"; $p[] = $f[$k]; }
    if (!empty($f['from'])) { $sql .= ' AND i.approved_at >= ?'; $p[] = $f['from'] . ' 00:00:00'; }
    if (!empty($f['to'])) { $sql .= ' AND i.approved_at <= ?'; $p[] = $f['to'] . ' 23:59:59'; }
    return all($sql . ' ORDER BY i.approved_at', $p);
}
function sum_items(array $items): array {
    $o = ['n' => count($items), 'pass' => 0, 'fail' => 0, 'cond' => 0, 'insp' => 0, 'rej' => 0];
    foreach ($items as $i) {
        $r = $i['final_result']; if ($r === 'PASS') $o['pass']++; elseif ($r === 'FAIL') $o['fail']++; else $o['cond']++;
        $o['insp'] += (int)$i['inspected_qty']; $o['rej'] += (int)$i['rejected_qty'];
    }
    $o['rate'] = $o['insp'] ? $o['rej'] * 100 / $o['insp'] : 0.0;
    return $o;
}
function month_trend(array $items, int $months = 6): array {
    $out = [];
    for ($k = $months - 1; $k >= 0; $k--) { $m = date('Y-m', strtotime(date('Y-m-01') . " -$k months")); $out[$m] = ['insp' => 0, 'rej' => 0]; }
    foreach ($items as $i) { $m = substr((string)$i['approved_at'], 0, 7); if (isset($out[$m])) { $out[$m]['insp'] += $i['inspected_qty']; $out[$m]['rej'] += $i['rejected_qty']; } }
    $r = []; foreach ($out as $m => $v) $r[date('M', strtotime("$m-01"))] = $v['insp'] ? round($v['rej'] * 100 / $v['insp'], 1) : 0.0;
    return $r;
}
function defect_pareto(array $itemIds): array {
    if (!$itemIds) return [];
    $in = implode(',', array_map('intval', $itemIds));
    $r = [];
    foreach (all("SELECT category, SUM(qty) q FROM item_defects WHERE item_id IN ($in) GROUP BY category ORDER BY q DESC") as $x) $r[$x['category']] = (int)$x['q'];
    return $r;
}
function vendor_rows(array $items): array {
    $by = [];
    foreach ($items as $i) $by[$i['vendor_id']][] = $i;
    $rows = [];
    foreach ($by as $vid => $list) {
        $s = sum_items($list); $par = defect_pareto(array_column($list, 'id'));
        $rows[] = ['vendor_id' => $vid, 'vendor' => $list[0]['vendor_name'], 'clients' => implode(', ', array_unique(array_column($list, 'client_code'))),
            'top' => $par ? array_key_first($par) : '—'] + $s;
    }
    usort($rows, fn($a, $b) => $b['rate'] <=> $a['rate']);
    return $rows;
}
function inspector_kpis(int $uid, ?string $from = null): array {
    $p = [$uid]; $w = '';
    if ($from) { $w = ' AND j.assigned_at >= ?'; $p[] = $from; }
    $assigned = (int)val("SELECT COUNT(*) FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id WHERE j.inspector_id=?$w", $p);
    $done = all("SELECT i.* FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id WHERE j.inspector_id=? AND i.status IN ('SUBMITTED','UNDER_REVIEW','APPROVED','RETURNED')$w", $p);
    $appr = array_filter($done, fn($i) => $i['status'] === 'APPROVED');
    $ret = array_filter($done, fn($i) => (int)$i['return_count'] > 0);
    $first = array_filter($appr, fn($i) => (int)$i['return_count'] === 0);
    $tt = []; foreach ($done as $i) if ($i['started_at'] && $i['submitted_at']) $tt[] = (strtotime($i['submitted_at']) - strtotime($i['started_at'])) / 3600;
    return ['assigned' => $assigned, 'done' => count($done), 'avg' => $tt ? array_sum($tt) / count($tt) : 0,
        'ret' => count($done) ? count($ret) * 100 / count($done) : 0, 'first' => count($appr) ? count($first) * 100 / count($appr) : 0,
        'pending' => (int)val("SELECT COUNT(*) FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id WHERE j.inspector_id=? AND i.status IN ('PENDING','IN_PROGRESS','HOLD','COMPLETED','RETURNED')", [$uid])];
}
