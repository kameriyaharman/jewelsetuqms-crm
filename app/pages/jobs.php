<?php
$f = (string)get('f', 'ALL');
$groups = ['ALL' => null, 'UNASSIGNED' => null, 'ASSIGNED' => ['ASSIGNED'], 'IN_PROGRESS' => ['IN_PROGRESS'], 'REVIEW' => ['SUBMITTED', 'UNDER_REVIEW', 'RETURNED', 'APPROVED'], 'CLOSED' => ['EMAILED', 'CLOSED']];
$base = "FROM qc_jobs j JOIN clients c ON c.id=j.client_id JOIN vendors v ON v.id=j.vendor_id LEFT JOIN users u ON u.id=j.inspector_id WHERE j.job_no IS NOT NULL AND j.status<>'CANCELLED'";
$count = function ($k) use ($base, $groups) {
    if ($k === 'ALL') return (int)val("SELECT COUNT(*) $base");
    if ($k === 'UNASSIGNED') return (int)val("SELECT COUNT(*) $base AND j.inspector_id IS NULL");
    return (int)val("SELECT COUNT(*) $base AND j.status IN ('" . implode("','", $groups[$k]) . "')");
};
$w = $base;
if ($f === 'UNASSIGNED') $w .= ' AND j.inspector_id IS NULL'; elseif ($f !== 'ALL' && isset($groups[$f])) $w .= " AND j.status IN ('" . implode("','", $groups[$f]) . "')";
$rows = all("SELECT j.*, c.code client_code, v.name vendor_name, u.emp_code insp_code, u.name insp_name, (SELECT COUNT(*) FROM qc_items i WHERE i.job_id=j.id) skus $w ORDER BY j.scheduled_date DESC, j.id DESC LIMIT 300");
$sel = (int)get('id') ? job_load((int)get('id')) : null;
if (!$sel) foreach ($rows as $r) if (!$r['inspector_id'] && in_array($r['status'], ['SCHEDULED', 'REQUESTED'], true)) { $sel = job_load((int)$r['id']); break; }
$canAssign = is_role('super_admin', 'qc_manager');
layout_start('QC Jobs', 'jobs', is_role('super_admin', 'qc_manager') ? btn(icon('cal') . ' Week schedule', url('schedule')) : '', 'All jobs by status; assign or reassign an inspector with availability and notifications');
$tab = fn($k, $l) => '<a class="' . ($f === $k ? 'on' : '') . '" href="' . url('jobs', ['f' => $k]) . '">' . $l . ' <b>' . $count($k) . '</b></a>';
?>
<div class="grid <?= $canAssign && $sel ? 'g-82' : '' ?>">
<div class="card">
  <div class="tabs"><?= $tab('ALL', 'All') ?><?= $tab('UNASSIGNED', 'Unassigned') ?><?= $tab('ASSIGNED', 'Assigned') ?><?= $tab('IN_PROGRESS', 'In progress') ?><?= $tab('REVIEW', 'Review') ?><?= $tab('CLOSED', 'Reported / closed') ?></div>
  <?php if (!$rows): echo empty_state('No jobs.'); else: ?>
  <div class="tbl-wrap"><table class="t"><tr><th>QC job no.</th><th>Client</th><th>Vendor</th><th>PO</th><th>SKUs</th><th>QC date</th><th>Inspector</th><th>Status</th></tr>
  <?php foreach ($rows as $j): ?><tr class="<?= $sel && $sel['id'] == $j['id'] ? 'sel' : '' ?>">
    <td><a class="strong mono" href="<?= url('request', ['id' => $j['id']]) ?>"><?= h($j['job_no']) ?></a></td><td><?= h($j['client_code']) ?></td><td><?= h($j['vendor_name']) ?></td><td class="mono"><?= h($j['po_no']) ?></td><td><?= (int)$j['skus'] ?></td>
    <td><?= fdate($j['scheduled_date'], 'd M') ?> <small><?= h($j['time_slot']) ?></small></td>
    <td><?= $j['insp_code'] ? h($j['insp_code'] . ' · ' . explode(' ', $j['insp_name'])[0]) : '—' ?></td>
    <td><?= !$j['inspector_id'] && in_array($j['status'], ['SCHEDULED', 'REQUESTED'], true) ? badge('UNASSIGNED') : badge($j['status']) ?>
      <?php if ($canAssign && in_array($j['status'], ['SCHEDULED', 'ASSIGNED', 'REQUESTED', 'IN_PROGRESS'], true)): ?> <a class="btn sm" href="<?= url('jobs', ['f' => $f, 'id' => $j['id']]) ?>"><?= $j['inspector_id'] ? 'Reassign' : 'Assign' ?></a><?php endif ?></td></tr>
  <?php endforeach ?></table></div><?php endif ?>
  <p class="muted" style="font-size:12px;margin-top:12px">Lifecycle: Requested → Scheduled → Assigned → In progress → Submitted → Under review → Approved → PDF generated → Emailed → Closed</p>
</div>
<?php if ($canAssign && $sel && in_array($sel['status'], ['SCHEDULED', 'ASSIGNED', 'REQUESTED', 'IN_PROGRESS'], true)):
  $date = $sel['scheduled_date'] ?: today(); $nsku = (int)val('SELECT COUNT(*) FROM qc_items WHERE job_id=?', [$sel['id']]);
  $team = all("SELECT u.*, (SELECT COUNT(*) FROM qc_jobs j WHERE j.inspector_id=u.id AND j.scheduled_date=? AND j.status IN ('ASSIGNED','IN_PROGRESS') AND j.id<>?) busy FROM users u WHERE u.role='inspector' AND u.status='ACTIVE' ORDER BY u.emp_code", [$date, $sel['id']]); ?>
<div class="card"><h2 class="mb">Assign inspector</h2>
  <div class="kv mb"><div><small>QC job</small><b><?= h($sel['job_no'] ?: $sel['req_no']) ?></b></div><div><small>Client</small><b><?= h($sel['client_code']) ?></b></div>
    <div><small>Vendor</small><b><?= h($sel['vendor_name']) ?></b></div><div><small>QC date</small><b><?= fdate($date, 'd-m-Y') ?></b></div></div>
  <form method="post" action="<?= url('job-action') ?>"><?= csrf_field() ?><input type="hidden" name="act" value="assign"><input type="hidden" name="id" value="<?= $sel['id'] ?>">
    <input type="hidden" name="back" value="<?= h(url('jobs', ['f' => $f])) ?>"><input type="hidden" name="date" value="<?= h($date) ?>">
    <label>Available team</label>
    <div class="stack f">
    <?php foreach ($team as $t): $loc = stripos($sel['vendor_address'] . ' ' . $sel['location'], (string)$t['location']) !== false; ?>
      <label class="rc"><input type="radio" name="inspector_id" value="<?= $t['id'] ?>" <?= $sel['inspector_id'] == $t['id'] ? 'checked' : '' ?> required>
        <b><?= h($t['emp_code'] . ' · ' . $t['name']) ?></b> <span style="float:right"><?= $t['busy'] ? badge('BUSY') : badge('AVAILABLE') ?></span>
        <small><?= h($t['location']) ?> · <?= $t['busy'] ? $t['busy'] . ' job(s) that day' : 'free' ?><?= $loc ? ' · near vendor' : '' ?></small></label>
    <?php endforeach ?></div>
    <?php if (flag('allow_job_split') && $nsku > 1): ?><div class="f"><label>Split job across 2 inspectors (<?= $nsku ?> SKUs) — second inspector</label><?= sel('inspector2_id', user_opts('inspector'), $sel['inspector2_id'], '', '— no split —') ?></div><?php endif ?>
    <label>Notify</label>
    <label class="chk">✓ Inspector — app notification</label>
    <label class="chk f"><input type="checkbox" name="notify_vendor" value="1" checked> Vendor — gate entry notice</label>
    <button class="btn pri block">ASSIGN INSPECTOR</button>
  </form></div>
<?php endif ?>
</div>
<?php layout_end();
