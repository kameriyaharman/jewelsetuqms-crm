<?php
$mStart = date('Y-m-01');
$month = sum_items(approved_items(['from' => $mStart]));
$prev = sum_items(approved_items(['from' => date('Y-m-01', strtotime('-1 month', strtotime($mStart))), 'to' => date('Y-m-t', strtotime('-1 month', strtotime($mStart)))]));
$pending = (int)val("SELECT COUNT(*) FROM qc_items WHERE status IN ('SUBMITTED','UNDER_REVIEW','RETURNED')");
$review = (int)val("SELECT COUNT(*) FROM qc_items WHERE status IN ('SUBMITTED','UNDER_REVIEW')");
$all = approved_items();
$trend = month_trend($all);
$todayJobs = all("SELECT j.*, c.code client_code, v.name vendor_name, u.name insp_name, u.emp_code insp_code, (SELECT COUNT(*) FROM qc_items WHERE job_id=j.id) skus
    FROM qc_jobs j JOIN clients c ON c.id=j.client_id JOIN vendors v ON v.id=j.vendor_id LEFT JOIN users u ON u.id=j.inspector_id
    WHERE j.scheduled_date=? ORDER BY j.time_slot", [today()]);
$upcoming = all("SELECT j.*, c.code client_code, v.name vendor_name FROM qc_jobs j JOIN clients c ON c.id=j.client_id JOIN vendors v ON v.id=j.vendor_id
    WHERE (j.scheduled_date>? OR (j.scheduled_date IS NULL AND j.requested_date>=?)) AND j.status IN ('REQUESTED','SCHEDULED','ASSIGNED') ORDER BY COALESCE(j.scheduled_date,j.requested_date) LIMIT 6", [today(), today()]);
$diff = $prev['rate'] ? $month['rate'] - $prev['rate'] : 0;
$canCreate = is_role('super_admin', 'back_office');
layout_start('Dashboard', 'dashboard', $canCreate ? btn(icon('upload') . ' Import Excel', url('import'), 'gold') : '', 'Overview for ' . date('F Y'));
?>
<div class="grid g4 mb">
  <?= kpi('Total QC reports', num($month['n']), 'This month', '', 'clip') ?>
  <?= kpi('Pass', num($month['pass']), pct($month['pass'], $month['n'], 0), 'ok', 'check') ?>
  <?= kpi('Fail', num($month['fail']), pct($month['fail'], $month['n'], 0) . ($month['cond'] ? ' · ' . $month['cond'] . ' conditional' : ''), 'bad', 'x') ?>
  <?= kpi('Pending', num($pending), $review . ' under review', 'warn', 'clock') ?>
</div>
<div class="grid g3 mb">
  <?= kpi('Total inspected pcs', num($month['insp']), 'This month', 'info', 'box') ?>
  <?= kpi('Total rejected', num($month['rej']), 'pcs', 'bad', 'alert') ?>
  <?= kpi('Rejection %', number_format($month['rate'], 1) . '%', $prev['rate'] ? (($diff <= 0 ? '↓ ' : '↑ ') . number_format(abs($diff), 1) . '% vs ' . date('M', strtotime('-1 month', strtotime($mStart)))) : '', 'gold', 'chart') ?>
</div>
<div class="grid g-73 mb">
  <div class="card"><div class="card-h"><h2>Today's QC · <?= date('d M') ?></h2><a href="<?= url('schedule') ?>">View schedule</a></div>
    <?php if (!$todayJobs): echo empty_state('No QC scheduled for today.'); else: ?>
    <div class="tbl-wrap"><table class="t"><tr><th>Job</th><th>Vendor</th><th>Client</th><th>Inspector</th><th>SKUs</th><th>Status</th></tr>
    <?php foreach ($todayJobs as $j): ?><tr><td><a class="strong" href="<?= url('request', ['id' => $j['id']]) ?>"><?= h($j['job_no'] ?: $j['req_no']) ?></a></td>
      <td><?= h($j['vendor_name']) ?></td><td><?= h($j['client_code']) ?></td><td><?= $j['insp_code'] ? h($j['insp_code'] . ' · ' . explode(' ', $j['insp_name'])[0]) : '<span class="muted">—</span>' ?></td>
      <td><?= (int)$j['skus'] ?> SKU</td><td><?= badge($j['status']) ?></td></tr><?php endforeach ?></table></div><?php endif ?>
  </div>
  <div class="card"><div class="card-h"><h2>Upcoming QC</h2><a href="<?= url('jobs') ?>">All</a></div>
    <?php if (!$upcoming): echo empty_state('Nothing upcoming.'); else: ?>
    <table class="t"><tr><th>Date</th><th>Vendor</th><th>Client</th><th>Status</th></tr>
    <?php foreach ($upcoming as $j): ?><tr><td><?= fdate($j['scheduled_date'] ?: $j['requested_date'], 'd M') ?></td><td><a href="<?= url('request', ['id' => $j['id']]) ?>"><?= h($j['vendor_name']) ?></a></td><td><?= h($j['client_code']) ?></td>
      <td><?= $j['status'] === 'SCHEDULED' && !$j['inspector_id'] ? badge('UNASSIGNED') : badge($j['status']) ?></td></tr><?php endforeach ?></table><?php endif ?>
  </div>
</div>
<div class="grid g-73">
  <div class="card"><div class="card-h"><h2>Rejection % — last 6 months</h2></div><?= bars($trend) ?></div>
  <div class="card"><div class="card-h"><h2>Quick actions</h2></div>
    <div class="grid g2">
      <?php if ($canCreate): ?><?= btn(icon('plus') . ' New QC Request', url('request-new')) ?><?= btn(icon('upload') . ' Import Excel', url('import')) ?><?php endif ?>
      <?php if (is_role('super_admin', 'qc_manager')): ?><?= btn(icon('users') . ' Assign QC', url('jobs', ['f' => 'UNASSIGNED'])) ?><?= btn(icon('eye') . ' Pending review (' . $review . ')', url('review')) ?><?php endif ?>
      <?= btn(icon('file') . ' View Reports', url('reports')) ?><?= btn(icon('chart') . ' Analytics', url('analytics')) ?>
    </div>
    <div class="note mt">Phase flags — vendor requests: <b><?= flag('enable_vendor_qc_request') ? 'ON' : 'OFF' ?></b> · client requests: <b><?= flag('enable_client_qc_request') ? 'ON' : 'OFF' ?></b><?php if (is_role('super_admin')): ?> · <a href="<?= url('settings', ['tab' => 'toggles']) ?>">change</a><?php endif ?></div>
  </div>
</div>
<?php layout_end();
