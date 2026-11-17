<?php
$rows = all("SELECT i.*, j.job_no, j.po_no, j.id jid, c.code client_code, v.name vendor_name, u.emp_code insp_code, u.name insp_name,
    (SELECT COUNT(*) FROM item_photos p WHERE p.item_id=i.id) nph FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id JOIN clients c ON c.id=j.client_id JOIN vendors v ON v.id=j.vendor_id
    LEFT JOIN users u ON u.id=j.inspector_id WHERE i.status IN ('SUBMITTED','UNDER_REVIEW','RETURNED') ORDER BY CASE i.status WHEN 'RETURNED' THEN 1 ELSE 0 END, i.submitted_at");
$conc = all("SELECT i.*, j.po_no, c.code client_code, v.name vendor_name FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id JOIN clients c ON c.id=j.client_id JOIN vendors v ON v.id=j.vendor_id WHERE i.concession_status='PENDING'");
layout_start('Pending Review', 'review', '', 'Submitted inspections waiting for QC manager review, approval or return');
?>
<div class="card">
<?php if (!$rows): echo empty_state('Nothing waiting for review. 🎉'); else: ?>
<div class="tbl-wrap"><table class="t"><tr><th>Job</th><th>SKU</th><th>Client</th><th>Vendor</th><th>Inspector</th><th class="num">Inspected</th><th class="num">Rejected</th><th>System</th><th>Photos</th><th>Submitted</th><th>Status</th><th></th></tr>
<?php foreach ($rows as $r): ?><tr><td class="mono"><a href="<?= url('request', ['id' => $r['jid']]) ?>"><?= h($r['job_no']) ?></a></td><td class="mono strong"><?= h($r['sku']) ?></td><td><?= h($r['client_code']) ?></td><td><?= h($r['vendor_name']) ?></td>
  <td><?= h($r['insp_code']) ?></td><td class="num"><?= num($r['inspected_qty']) ?></td><td class="num"><?= num($r['rejected_qty']) ?> <small>(<?= pct($r['rejected_qty'], $r['inspected_qty']) ?>)</small></td>
  <td><?= badge($r['system_result'] ?: 'PENDING') ?></td><td><?= (int)$r['nph'] ?></td><td><?= fdt($r['submitted_at']) ?></td><td><?= badge($r['status']) ?><?= $r['return_count'] ? ' <small>×' . $r['return_count'] . '</small>' : '' ?></td>
  <td><?php if ($r['status'] !== 'RETURNED'): ?><a class="btn sm pri" href="<?= url('review-item', ['id' => $r['id']]) ?>">Review</a><?php else: ?><a class="btn sm" href="<?= url('review-item', ['id' => $r['id']]) ?>">View</a><?php endif ?></td></tr><?php endforeach ?>
</table></div><?php endif ?>
</div>
<?php if ($conc): ?><div class="card"><h2 class="mb">Waiting for client concession decision</h2><table class="t"><tr><th>Report</th><th>Client</th><th>Vendor</th><th>SKU</th><th>Reply by</th></tr>
<?php foreach ($conc as $c): ?><tr><td><a class="mono" href="<?= url('report', ['id' => $c['id']]) ?>"><?= h($c['report_no']) ?></a></td><td><?= h($c['client_code']) ?></td><td><?= h($c['vendor_name']) ?></td><td class="mono"><?= h($c['sku']) ?></td><td><?= fdate($c['concession_due']) ?></td></tr><?php endforeach ?></table></div><?php endif ?>
<?php layout_end();
