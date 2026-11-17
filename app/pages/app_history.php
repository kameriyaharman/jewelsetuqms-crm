<?php
$me = user(); $f = (string)get('f', 'ALL');
$map = ['SUBMITTED' => ['SUBMITTED', 'UNDER_REVIEW'], 'RETURNED' => ['RETURNED'], 'APPROVED' => ['APPROVED']];
$sql = "SELECT i.*, j.job_no, j.po_no, v.name vendor_name, c.code client_code FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id JOIN vendors v ON v.id=j.vendor_id JOIN clients c ON c.id=j.client_id
    WHERE (j.inspector_id=? OR j.inspector2_id=?) AND i.status IN ('SUBMITTED','UNDER_REVIEW','RETURNED','APPROVED')";
$p = [$me['id'], $me['id']];
if (isset($map[$f])) $sql .= " AND i.status IN ('" . implode("','", $map[$f]) . "')";
$rows = all($sql . ' ORDER BY COALESCE(i.approved_at, i.submitted_at) DESC LIMIT 100', $p);
app_start('My QC history');
?>
<div class="tabs"><?php foreach (['ALL' => 'All', 'SUBMITTED' => 'Submitted', 'RETURNED' => 'Returned', 'APPROVED' => 'Approved'] as $k => $l): ?><a class="<?= $f === $k ? 'on' : '' ?>" href="<?= url('app/history', ['f' => $k]) ?>"><?= $l ?></a><?php endforeach ?></div>
<div class="mcard"><div class="mlist">
<?php if (!$rows) echo empty_state('Nothing here yet.'); foreach ($rows as $r): ?>
  <a href="<?= $r['status'] === 'APPROVED' ? url('pdf', ['item' => $r['id']]) : url('app/inspect', ['item' => $r['id']]) ?>"><span><b class="mono"><?= h($r['report_no'] ?: $r['sku']) ?></b><small><?= h($r['vendor_name']) ?> · <?= h($r['client_code']) ?> · <?= fdate($r['approved_at'] ?: $r['submitted_at'], 'd M') ?> · <?= h($r['sku']) ?></small></span><?= badge($r['status']) ?></a>
<?php endforeach ?></div></div>
<?php app_end('history');
