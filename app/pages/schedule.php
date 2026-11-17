<?php
$start = get('w') ? date('Y-m-d', strtotime('monday this week', strtotime((string)get('w')))) : date('Y-m-d', strtotime('monday this week'));
$days = []; for ($k = 0; $k < 7; $k++) $days[] = date('Y-m-d', strtotime("$start +$k days"));
$end = end($days);
[$sc, $sp] = job_scope('j');
$cf = (int)get('client');
$sql = "SELECT j.*, c.code client_code, v.name vendor_name, v.location vloc, u.emp_code insp_code, u.name insp_name, u.phone insp_phone,
    (SELECT COUNT(*) FROM qc_items i WHERE i.job_id=j.id) skus FROM qc_jobs j JOIN clients c ON c.id=j.client_id JOIN vendors v ON v.id=j.vendor_id
    LEFT JOIN users u ON u.id=j.inspector_id WHERE $sc AND j.status NOT IN ('CANCELLED','DRAFT') AND COALESCE(j.scheduled_date, j.requested_date) BETWEEN ? AND ?";
$p = array_merge($sp, [$start, $end]);
if ($cf) { $sql .= ' AND j.client_id=?'; $p[] = $cf; }
$jobs = all($sql . ' ORDER BY j.time_slot', $p);
$nav = '<div class="actions">' . btn('‹', url('schedule', ['w' => date('Y-m-d', strtotime("$start -7 days")), 'client' => $cf ?: null]), 'sm') .
    '<b style="padding:0 6px">' . date('d', strtotime($start)) . ' – ' . date('d M Y', strtotime($end)) . '</b>' .
    btn('›', url('schedule', ['w' => date('Y-m-d', strtotime("$start +7 days")), 'client' => $cf ?: null]), 'sm') . btn('Today', url('schedule'), 'sm') . '</div>';
$canDrag = is_role('super_admin', 'qc_manager');

if (!is_internal()) {
    layout_start(role() === 'vendor' ? 'Inspection schedule' : 'QC Schedule', 'schedule', $nav, 'Confirmed inspection dates and assigned inspectors');
    $up = all("SELECT j.*, c.code client_code, v.name vendor_name, u.emp_code insp_code, u.name insp_name, u.phone insp_phone FROM qc_jobs j JOIN clients c ON c.id=j.client_id JOIN vendors v ON v.id=j.vendor_id LEFT JOIN users u ON u.id=j.inspector_id
        WHERE $sc AND j.status IN ('REQUESTED','SCHEDULED','ASSIGNED','IN_PROGRESS') ORDER BY COALESCE(j.scheduled_date,j.requested_date)", $sp); ?>
    <div class="card"><h2 class="mb">Upcoming inspections</h2>
    <?php if (!$up): echo empty_state('No upcoming inspections.'); else: ?>
    <div class="tbl-wrap"><table class="t"><tr><th>Request</th><th><?= role() === 'vendor' ? 'Client' : 'Vendor' ?></th><th>PO</th><th>Date</th><th>Time</th><th>Inspector</th><th>Location</th><th>Status</th></tr>
    <?php foreach ($up as $j): ?><tr><td><a class="strong" href="<?= url('request', ['id' => $j['id']]) ?>"><?= h($j['req_no']) ?></a></td><td><?= h(role() === 'vendor' ? $j['client_code'] : $j['vendor_name']) ?></td><td class="mono"><?= h($j['po_no']) ?></td>
      <td><?= $j['scheduled_date'] ? fdate($j['scheduled_date']) : fdate($j['requested_date']) . ' <small>(requested)</small>' ?></td><td><?= h($j['time_slot'] ?: '—') ?></td>
      <td><?= $j['insp_code'] ? h($j['insp_code'] . ' · ' . $j['insp_name']) . (role() === 'vendor' && $j['insp_phone'] ? '<br><small>' . h($j['insp_phone']) . '</small>' : '') : '—' ?></td><td><?= h($j['location']) ?></td><td><?= badge($j['status']) ?></td></tr><?php endforeach ?></table></div>
    <?php if (role() === 'vendor'): ?><p class="note mt">Gate-entry details are sent on assignment. Please keep the ready quantity, spec sheets and approved sample available on the inspection date.</p><?php endif; endif ?></div>
<?php layout_end(); return; }

$inspectors = all("SELECT * FROM users WHERE role='inspector' AND status='ACTIVE' ORDER BY emp_code");
$grid = []; $un = [];
foreach ($jobs as $j) { $d = $j['scheduled_date'] ?: $j['requested_date']; if ($j['inspector_id']) $grid[$j['inspector_id']][$d][] = $j; else $un[$d][] = $j; }
$chip = function ($j) use ($canDrag) {
    $cls = !$j['inspector_id'] ? 'un' : (in_array($j['status'], ['SUBMITTED', 'UNDER_REVIEW', 'APPROVED', 'EMAILED', 'CLOSED'], true) ? 'done' : '');
    $drag = $canDrag && in_array($j['status'], ['REQUESTED', 'SCHEDULED', 'ASSIGNED'], true);
    return '<a class="chipjob ' . $cls . '" ' . ($drag ? 'draggable="true" data-job="' . $j['id'] . '"' : '') . ' href="' . url('request', ['id' => $j['id']]) . '"><b>' . h($j['job_no'] ?: $j['req_no']) . '</b><small>' . h($j['vendor_name']) . ' (' . h($j['client_code']) . ') · ' . $j['skus'] . ' SKU' . ($j['time_slot'] ? ' · ' . h($j['time_slot']) : '') . '</small></a>';
};
layout_start('QC Schedule', 'schedule', sel('client', array_column(all('SELECT id, code FROM clients ORDER BY code'), 'code', 'id'), $cf, 'onchange="location.href=\'?w=' . $start . '&client=\'+this.value"', 'Client: All') . $nav, 'Every inspector’s QC jobs by day; unassigned jobs highlighted' . ($canDrag ? ' and assigned by drag &amp; drop' : ''));
?>
<div class="week">
  <div class="wh"></div>
  <?php foreach ($days as $d): ?><div class="wh <?= $d === today() ? 'today' : '' ?>"><?= date('D d', strtotime($d)) ?></div><?php endforeach ?>
  <?php foreach ($inspectors as $u): ?>
    <div class="wi"><span class="avatar"><?= h(substr($u['name'], 0, 1)) ?></span><span><?= h(explode(' ', $u['name'])[0]) ?><br><small><?= h($u['emp_code']) ?> · <?= h($u['location']) ?></small></span></div>
    <?php foreach ($days as $d): ?><div class="slot" data-insp="<?= $u['id'] ?>" data-date="<?= $d ?>"><?php foreach ($grid[$u['id']][$d] ?? [] as $j) echo $chip($j) ?></div><?php endforeach ?>
  <?php endforeach ?>
  <div class="wi" style="color:var(--ruby)"><span class="avatar" style="background:var(--ruby-l);color:var(--ruby)">!</span>Unassigned</div>
  <?php foreach ($days as $d): ?><div class="slot" style="background:#fffafa"><?php foreach ($un[$d] ?? [] as $j) echo $chip($j) ?></div><?php endforeach ?>
</div>
<?php if ($canDrag): ?><p class="muted" style="font-size:12.5px">Drag a job from “Unassigned” (or another inspector) onto an inspector row &amp; day to assign it — notification goes out automatically.</p><?php endif ?>
<?php layout_end();
