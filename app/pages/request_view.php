<?php
$job = job_load((int)get('id'));
if (!$job || !can_see_job($job)) { flash('Request not found.', 'err'); redirect('requests'); }
$items = all('SELECT * FROM qc_items WHERE job_id=? ORDER BY id', [$job['id']]);
$atts = all('SELECT * FROM attachments WHERE job_id=? ORDER BY id', [$job['id']]);
$log = all('SELECT a.*, u.name uname FROM audit_log a LEFT JOIN users u ON u.id=a.user_id WHERE (a.entity=? AND a.entity_id=?) ' .
    ($items ? 'OR (a.entity=\'item\' AND a.entity_id IN (' . implode(',', array_map('intval', array_column($items, 'id'))) . '))' : '') . ' ORDER BY a.id DESC LIMIT 40', ['job', $job['id']]);
$emails = all('SELECT * FROM email_log WHERE job_id=? ORDER BY id DESC LIMIT 20', [$job['id']]);
$flow = ['REQUESTED', 'SCHEDULED', 'ASSIGNED', 'IN_PROGRESS', 'SUBMITTED', 'UNDER_REVIEW', 'APPROVED', 'EMAILED', 'CLOSED'];
$cur = $job['status'] === 'RETURNED' ? 'UNDER_REVIEW' : ($job['status'] === 'DRAFT' ? 'REQUESTED' : $job['status']);
$ci = array_search($cur, $flow, true);
$isClientVendor = in_array(role(), ['client', 'vendor'], true);
$editable = in_array($job['status'], ['DRAFT', 'REQUESTED'], true) && (is_role('super_admin', 'back_office') || ($isClientVendor && $job['created_by'] == user()['id'])) || (is_role('super_admin', 'back_office') && $job['status'] === 'SCHEDULED');
$acts = '';
if ($editable) $acts .= btn('Edit', url('request-new', ['id' => $job['id']]));
if (is_role('super_admin', 'back_office') && in_array($job['status'], ['REQUESTED', 'DRAFT'], true)) $acts .= btn('Schedule', url('requests', ['id' => $job['id']]), 'pri');
if (in_array($job['status'], ['EMAILED', 'CLOSED', 'APPROVED'], true)) $acts .= btn(icon('download') . ' PO-wise PDF', url('pdf', ['job' => $job['id']]), 'gold');
layout_start(($job['job_no'] ?: $job['req_no']) . ' · PO ' . $job['po_no'], 'requests', $acts, h($job['client_name'] . ' — ' . $job['vendor_name']) . ' · ' . h($job['source']) . ' · created ' . fdt($job['created_at']));
?>
<div class="card mb"><div class="flow">
<?php foreach ($flow as $k => $s): ?><span class="<?= $job['status'] === 'CANCELLED' ? '' : ($k < $ci ? 'done' : ($k === $ci ? 'cur' : '')) ?>"><?= $k + 1 ?>. <?= h(JOB_STATUS[$s]) ?></span><?php endforeach ?>
<?php if ($job['status'] === 'RETURNED'): ?><span class="cur" style="background:var(--ruby)">Returned for correction</span><?php endif ?>
<?php if ($job['status'] === 'CANCELLED'): ?><span class="cur" style="background:var(--ruby)">CANCELLED</span><?php endif ?>
</div></div>
<div class="grid g-73">
<div>
<div class="card">
  <div class="card-h"><h2>Request details</h2><?= badge($job['status']) ?></div>
  <div class="kv">
    <div><small>Request no.</small><b><?= h($job['req_no']) ?></b></div><div><small>QC job no.</small><b><?= h($job['job_no'] ?: '—') ?></b></div>
    <div><small>Client</small><b><?= h($job['client_code'] . ' — ' . $job['client_name']) ?></b></div><div><small>Vendor</small><b><?= h($job['vendor_name']) ?></b></div>
    <div><small>PO number</small><b><?= h($job['po_no']) ?></b></div><div><small>QC type</small><b><?= h($job['qc_type']) ?></b></div>
    <div><small>QC scope</small><b><?= h(scope_label($job)) ?></b></div><div><small>Location</small><b><?= h($job['location']) ?></b></div>
    <div><small>Requested date</small><b><?= fdate($job['requested_date']) ?></b></div><div><small>Scheduled</small><b><?= $job['scheduled_date'] ? fdate($job['scheduled_date']) . ' · ' . h($job['time_slot']) : '—' ?></b></div>
    <div><small>Inspector</small><b><?= $job['inspector_id'] ? h($job['inspector_code'] . ' · ' . $job['inspector_name']) : '—' ?></b></div><div><small>QC manager</small><b><?= h($job['manager_name'] ?: '—') ?></b></div>
  </div>
  <?php if ($job['specs'] || $job['special_req']): ?><div class="note mt"><?= $job['specs'] ? '<b>Specs:</b> ' . h($job['specs']) . '<br>' : '' ?><?= $job['special_req'] ? '<b>Special requirement:</b> ' . h($job['special_req']) : '' ?></div><?php endif ?>
  <?php if ($atts): ?><div class="actions mt"><?php foreach ($atts as $a): ?><a class="pill" target="_blank" href="<?= url('file', ['p' => $a['path']]) ?>">📎 <?= h($a['name']) ?></a><?php endforeach ?></div><?php endif ?>
</div>
<div class="card">
  <div class="card-h"><h2>SKUs / designs (<?= count($items) ?>)</h2></div>
  <div class="tbl-wrap"><table class="t"><tr><th>SKU</th><th>Product</th><th>Metal / plating</th><th class="num">Order</th><th class="num">Ready</th><th class="num">Insp. qty</th><th class="num">Rejected</th><th>Status</th><th>Result</th><th></th></tr>
  <?php foreach ($items as $i): $pub = $i['status'] === 'APPROVED'; ?><tr>
    <td class="mono strong"><?= h($i['sku']) ?></td><td><?= h($i['product']) ?></td><td><?= h($i['metal']) ?><br><small><?= h($i['plating']) ?></small></td>
    <td class="num"><?= num($i['order_qty']) ?></td><td class="num"><?= num($i['ready_qty']) ?></td><td class="num"><?= num($i['insp_qty']) ?></td>
    <td class="num"><?= in_array($i['status'], ['PENDING'], true) ? '—' : num($i['rejected_qty']) ?></td>
    <td><?= badge($i['status']) ?><?= $i['hold_status'] === 'PENDING' ? '<br><small>waiting ' . h($i['hold_for']) . '</small>' : '' ?></td>
    <td><?= $pub || is_internal() ? ($i['final_result'] ? badge($i['final_result'] === 'CONDITIONAL' ? 'CONDITIONAL' : $i['final_result']) : ($i['system_result'] && is_internal() ? '<small>sys: ' . h($i['system_result']) . '</small>' : '—')) : '—' ?></td>
    <td><?php if ($pub): ?><a class="btn sm" href="<?= url('report', ['id' => $i['id']]) ?>">Report</a><?php elseif (is_role('super_admin', 'qc_manager') && in_array($i['status'], ['SUBMITTED', 'UNDER_REVIEW'], true)): ?><a class="btn sm pri" href="<?= url('review-item', ['id' => $i['id']]) ?>">Review</a><?php elseif ($i['hold_status'] === 'PENDING' && role() === $i['hold_for']): ?><a class="btn sm pri" href="<?= url('approvals') ?>">Respond</a><?php endif ?></td>
  </tr><?php endforeach ?></table></div>
</div>
<?php if ($emails && !$isClientVendor): ?><div class="card"><h2 class="mb">Email history</h2><table class="t"><tr><th>Recipient</th><th>Email</th><th>Subject</th><th>Sent</th><th>Status</th></tr>
<?php foreach ($emails as $e): ?><tr><td><?= h($e['recipient_type']) ?></td><td><?= h($e['email']) ?></td><td><?= h($e['subject']) ?></td><td><?= fdt($e['sent_at']) ?></td><td><?= badge($e['status']) ?></td></tr><?php endforeach ?></table></div><?php endif ?>
</div>
<div>
<?php if (is_role('super_admin', 'qc_manager', 'back_office') && in_array($job['status'], ['REQUESTED', 'SCHEDULED', 'ASSIGNED', 'IN_PROGRESS'], true)): ?>
<div class="card"><h2 class="mb"><?= $job['inspector_id'] ? 'Reassign inspector' : 'Assign inspector' ?></h2>
<form method="post" action="<?= url('job-action') ?>"><?= csrf_field() ?><input type="hidden" name="act" value="assign"><input type="hidden" name="id" value="<?= $job['id'] ?>">
  <div class="f"><label>Inspector</label><?= sel('inspector_id', user_opts('inspector'), $job['inspector_id'], 'required', 'Select inspector') ?></div>
  <?php if (flag('allow_job_split') && count($items) > 1): ?><div class="f"><label>Second inspector (split large job)</label><?= sel('inspector2_id', user_opts('inspector'), $job['inspector2_id'], '', '—') ?></div><?php endif ?>
  <div class="f"><label>QC date</label><input type="date" name="date" value="<?= h($job['scheduled_date'] ?: $job['requested_date']) ?>"></div>
  <label class="chk f"><input type="checkbox" name="notify_vendor" value="1" checked> Notify vendor for gate entry</label>
  <button class="btn pri block">ASSIGN INSPECTOR</button></form></div>
<?php endif ?>
<?php if (is_internal() && in_array($job['status'], ['EMAILED', 'APPROVED'], true)): ?>
<div class="card"><h2 class="mb">Report actions</h2><div class="stack">
  <form method="post" action="<?= url('job-action') ?>"><?= csrf_field() ?><input type="hidden" name="act" value="email_job"><input type="hidden" name="id" value="<?= $job['id'] ?>"><button class="btn block"><?= icon('mail') ?> Re-send PO-wise report</button></form>
  <?php if ($job['status'] === 'APPROVED' && is_role('super_admin', 'qc_manager')): ?><form method="post" action="<?= url('job-action') ?>"><?= csrf_field() ?><input type="hidden" name="act" value="finalize"><input type="hidden" name="id" value="<?= $job['id'] ?>"><button class="btn gold block">Generate PDF &amp; email</button></form><?php endif ?>
  <?php if ($job['status'] === 'EMAILED'): ?><form method="post" action="<?= url('job-action') ?>"><?= csrf_field() ?><input type="hidden" name="act" value="close"><input type="hidden" name="id" value="<?= $job['id'] ?>"><button class="btn pri block">Close job</button></form><?php endif ?>
</div></div><?php endif ?>
<?php if ((is_role('super_admin', 'back_office') && in_array($job['status'], ['DRAFT', 'REQUESTED', 'SCHEDULED', 'ASSIGNED'], true)) || ($isClientVendor && in_array($job['status'], ['DRAFT', 'REQUESTED'], true) && $job['created_by'] == user()['id'])): ?>
<div class="card"><form method="post" action="<?= url('job-action') ?>"><?= csrf_field() ?><input type="hidden" name="act" value="cancel"><input type="hidden" name="id" value="<?= $job['id'] ?>">
  <button class="btn block" data-confirm="Cancel this QC request?" style="color:var(--ruby)">Cancel request</button></form></div><?php endif ?>
<div class="card"><h2 class="mb">Activity</h2>
  <?php if (!$log) echo empty_state('No activity yet.'); ?>
  <div class="timeline"><?php foreach ($log as $l): ?><div><b><?= h(str_replace('_', ' ', $l['action'])) ?></b> <?= $l['new_val'] !== null && $l['new_val'] !== '' ? '· ' . h(mb_strimwidth((string)$l['new_val'], 0, 60, '…')) : '' ?><small><?= fdt($l['created_at']) ?></small><br><span class="muted" style="font-size:12px"><?= h($l['uname'] ?: 'System') ?></span></div><?php endforeach ?></div>
</div>
</div>
</div>
<?php layout_end();
