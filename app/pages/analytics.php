<?php
$vf = (int)get('vendor'); $cf = role() === 'client' ? (int)user()['client_id'] : (int)get('client');
$period = (string)get('period', date('Y-m'));
$from = $period === 'all' ? null : $period . '-01'; $to = $period === 'all' ? null : date('Y-m-t', strtotime($period . '-01'));
$allItems = approved_items(array_filter(['client_id' => $cf ?: null]));
$items = array_values(array_filter($allItems, fn($i) => (!$vf || $i['vendor_id'] == $vf) && (!$from || ($i['approved_at'] >= $from && $i['approved_at'] <= $to . ' 23:59:59'))));
$s = sum_items($items);
$pareto = defect_pareto(array_column($items, 'id'));
$trendSrc = $vf ? array_filter($allItems, fn($i) => $i['vendor_id'] == $vf) : $allItems;
$trend = month_trend(array_values($trendSrc));
$periodItems = array_values(array_filter($allItems, fn($i) => !$from || ($i['approved_at'] >= $from && $i['approved_at'] <= $to . ' 23:59:59')));
$vrows = vendor_rows($periodItems);
// trend arrow per vendor: this period vs previous month
$prevFrom = $from ? date('Y-m-01', strtotime($from . ' -1 month')) : null;
$prevRates = []; if ($prevFrom) foreach (vendor_rows(array_values(array_filter($allItems, fn($i) => $i['approved_at'] >= $prevFrom && $i['approved_at'] < $from))) as $r) $prevRates[$r['vendor_id']] = $r['rate'];
$months = ['all' => 'All time']; for ($k = 0; $k < 12; $k++) { $m = date('Y-m', strtotime(date('Y-m-01') . " -$k months")); $months[$m] = date('F Y', strtotime("$m-01")); }
$vendorOpts = role() === 'client' ? array_column(all('SELECT v.id, v.name FROM vendors v JOIN client_vendor m ON m.vendor_id=v.id WHERE m.client_id=?', [$cf]), 'name', 'id') : array_column(all('SELECT id, name FROM vendors ORDER BY name'), 'name', 'id');
if (get('export')) {
    header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="vendor_quality_' . $period . '.csv"');
    $o = fopen('php://output', 'w'); fputcsv($o, ['Vendor', 'Client(s)', 'Inspections', 'Pass', 'Fail', 'Conditional', 'Inspected pcs', 'Rejected pcs', 'Rejection %', 'Top defect']);
    foreach ($vrows as $r) fputcsv($o, [$r['vendor'], $r['clients'], $r['n'], $r['pass'], $r['fail'], $r['cond'], $r['insp'], $r['rej'], round($r['rate'], 2), $r['top']]);
    exit;
}
layout_start('Vendor Quality Analytics', 'analytics', btn(icon('download') . ' Export', url('analytics', ['vendor' => $vf ?: null, 'client' => $cf ?: null, 'period' => $period, 'export' => 1])), 'Vendor-wise inspections, rejection rate, top defects, trend and vendor comparison');
?>
<form class="filters card mb" style="padding:12px 16px">
  <div><label>Vendor</label><?= sel('vendor', $vendorOpts, $vf, 'data-auto', 'All vendors') ?></div>
  <?php if (role() !== 'client'): ?><div><label>Client</label><?= sel('client', array_column(all('SELECT id, code FROM clients ORDER BY code'), 'code', 'id'), $cf, 'data-auto', 'All clients') ?></div><?php endif ?>
  <div><label>Period</label><?= sel('period', $months, $period, 'data-auto') ?></div>
</form>
<div class="grid g5 mb">
  <?= kpi('Total inspections', $s['n'], '', '', 'clip') ?><?= kpi('Pass', $s['pass'], pct($s['pass'], $s['n'], 0), 'ok', 'check') ?><?= kpi('Fail', $s['fail'], $s['cond'] . ' conditional', 'bad', 'x') ?>
  <?= kpi('Total inspected', num($s['insp']) . ' <small style="font-size:13px">pcs</small>', num($s['rej']) . ' rejected', 'info', 'box') ?><?= kpi('Rejection rate', number_format($s['rate'], 1) . '%', '', 'gold', 'chart') ?>
</div>
<div class="grid g2 mb">
  <div class="card" style="margin:0"><div class="card-h"><h2>Top defects (Pareto)<?= $vf ? ' — ' . h($vendorOpts[$vf] ?? '') : '' ?> · <?= h($months[$period] ?? $period) ?></h2><span class="muted"><?= num(array_sum($pareto)) ?> pcs</span></div>
    <?= $pareto ? hbars(array_slice($pareto, 0, 7)) : empty_state('No defects recorded in this period.') ?>
    <?php if ($pareto): $cum = 0; $tot = array_sum($pareto); ?><p class="muted" style="font-size:12px">Cumulative: <?php foreach (array_slice($pareto, 0, 4) as $k => $v) { $cum += $v; echo h($k) . ' ' . round($cum * 100 / $tot) . '% · '; } ?></p><?php endif ?></div>
  <div class="card" style="margin:0"><div class="card-h"><h2>Rejection % trend</h2></div><?= bars($trend) ?></div>
</div>
<div class="card"><div class="card-h"><h2>Vendor comparison — <?= h($months[$period] ?? $period) ?></h2></div>
<?php if (!$vrows): echo empty_state('No approved inspections in this period.'); else: ?>
<div class="tbl-wrap"><table class="t"><tr><th>Vendor</th><th>Client</th><th class="num">Inspections</th><th class="num">Inspected pcs</th><th class="num">Rejected</th><th class="num">Rejection %</th><th>Top defect</th><th>Trend</th></tr>
<?php foreach ($vrows as $r): $pr = $prevRates[$r['vendor_id']] ?? null; ?><tr><td class="strong"><a href="<?= url('analytics', ['vendor' => $r['vendor_id'], 'period' => $period, 'client' => $cf ?: null]) ?>"><?= h($r['vendor']) ?></a></td><td><?= h($r['clients']) ?></td>
  <td class="num"><?= $r['n'] ?></td><td class="num"><?= num($r['insp']) ?></td><td class="num"><?= num($r['rej']) ?></td><td class="num <?= $r['rate'] > 5 ? 'bad-t' : '' ?>"><b><?= number_format($r['rate'], 1) ?>%</b></td><td><?= h($r['top']) ?></td>
  <td style="font-size:16px"><?= $pr === null ? '→' : ($r['rate'] > $pr + 0.1 ? '<span class="bad-t">↑</span>' : ($r['rate'] < $pr - 0.1 ? '<span class="ok-t">↓</span>' : '→')) ?></td></tr><?php endforeach ?></table></div><?php endif ?>
</div>
<?php if (is_internal()): $ins = all("SELECT * FROM users WHERE role='inspector' AND status='ACTIVE' ORDER BY emp_code"); ?>
<div class="card"><div class="card-h"><h2>Inspector performance</h2><a href="<?= url('team') ?>">Team</a></div>
<table class="t"><tr><th>Inspector</th><th class="num">Assigned SKUs</th><th class="num">Done</th><th class="num">Avg time</th><th class="num">Return %</th><th class="num">1st-time approval</th><th class="num">Pending</th></tr>
<?php foreach ($ins as $u): $k = inspector_kpis((int)$u['id']); ?><tr><td class="strong"><?= h($u['emp_code'] . ' ' . $u['name']) ?></td><td class="num"><?= $k['assigned'] ?></td><td class="num"><?= $k['done'] ?></td><td class="num"><?= number_format($k['avg'], 1) ?> h</td><td class="num"><?= round($k['ret']) ?>%</td><td class="num"><?= round($k['first']) ?>%</td><td class="num"><?= $k['pending'] ?></td></tr><?php endforeach ?></table></div><?php endif ?>
<?php layout_end();
