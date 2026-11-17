<?php
$it = one('SELECT * FROM qc_items WHERE id=?', [(int)get('id')]);
$job = $it ? job_load((int)$it['job_id']) : null;
if (!$job || !can_see_job($job) || (!is_internal() && $it['status'] !== 'APPROVED')) { flash('Report not available.', 'err'); redirect('reports'); }
if ($it['status'] !== 'APPROVED') redirect('review-item', ['id' => $it['id']]);
$emails = all('SELECT * FROM email_log WHERE item_id=? OR (job_id=? AND item_id IS NULL) ORDER BY id DESC', [$it['id'], $job['id']]);
$revs = all("SELECT a.*, u.name uname FROM audit_log a LEFT JOIN users u ON u.id=a.user_id WHERE a.entity='item' AND a.entity_id=? ORDER BY a.id", [$it['id']]);
$oldPdfs = is_internal() ? glob(UPLOAD_DIR . '/reports/' . $it['report_no'] . '*.pdf') : [];
$rej = (int)$it['rejected_qty'];
$subject = "QC Inspection Report | {$job['client_code']} | PO {$job['po_no']} | {$it['sku']}";
$msg = "Dear Sir/Madam,\n\nPlease find attached the QC inspection report for PO {$job['po_no']}.\nResult: " . ($it['final_result'] === 'CONDITIONAL' ? 'CONDITIONAL PASS' : $it['final_result']) . ($rej ? " ($rej pcs rejected)" : '') . '.';
layout_start('Report ' . $it['report_no'], 'reports', btn(icon('download') . ' Design PDF', url('pdf', ['item' => $it['id'], 'dl' => 1]), 'gold') . (in_array($job['status'], ['EMAILED', 'CLOSED', 'APPROVED'], true) ? btn('PO-wise PDF', url('pdf', ['job' => $job['id']])) : ''), h($job['client_name'] . ' · ' . $job['vendor_name'] . ' · PO ' . $job['po_no'] . ' · ' . $it['sku']));
?>
<div class="grid g-73">
<div class="card" style="padding:0;overflow:hidden"><div class="card-h" style="padding:14px 18px 0"><h2>Report preview · <?= h($it['report_no']) ?></h2><a target="_blank" href="<?= url('pdf', ['item' => $it['id']]) ?>">Open full screen</a></div>
  <iframe src="<?= url('pdf', ['item' => $it['id']]) ?>#view=FitH" style="width:100%;height:78vh;border:0;background:#eee"></iframe></div>
<div>
<div class="card"><h2 class="mb">Report generated successfully</h2>
  <p class="muted" style="margin-top:-6px"><?= h($it['report_no']) ?>.pdf · approved <?= fdt($it['approved_at']) ?></p>
  <div class="actions mb"><?= badge('APPROVED') ?><?= badge('PDF GENERATED') ?><?= badge($it['final_result']) ?><span class="right">🔒 Locked · Rev <?= sprintf('%02d', $it['rev']) ?></span></div>
  <div class="actions"><a class="btn" target="_blank" href="<?= url('pdf', ['item' => $it['id']]) ?>">View PDF</a><a class="btn" href="<?= url('pdf', ['item' => $it['id'], 'dl' => 1]) ?>">Download</a>
  <?php if (is_role('super_admin')): ?><a class="btn" href="<?= url('review-item', ['id' => $it['id'], 'revise' => 1]) ?>">Unlock / revise</a><?php endif ?></div>
</div>
<?php if ($it['final_result'] === 'CONDITIONAL'): ?><div class="card"><h2 class="mb">Concession</h2>
  <div class="note"><?= h($it['manager_note']) ?></div>
  <p>Client decision: <?= badge($it['concession_status'] ?: 'PENDING') ?> <?= $it['concession_by'] ? '· ' . h($it['concession_by']) . ' · ' . fdt($it['concession_at']) : '' ?></p>
  <?php if ($it['concession_remark']): ?><p class="muted">“<?= h($it['concession_remark']) ?>”</p><?php endif ?>
  <?php if (role() === 'client' && $it['concession_status'] === 'PENDING'): ?><?= btn('Respond now', url('approvals'), 'pri') ?><?php endif ?></div><?php endif ?>
<?php if (is_internal()): ?>
<div class="card"><h2 class="mb">Send QC report</h2>
<form method="post" action="<?= url('job-action') ?>"><?= csrf_field() ?><input type="hidden" name="act" value="email_item"><input type="hidden" name="id" value="<?= $job['id'] ?>"><input type="hidden" name="item_id" value="<?= $it['id'] ?>">
  <div class="f"><label>To</label><input type="text" readonly value="Vendor · <?= h($job['vendor_email']) ?>"></div>
  <div class="f"><label>CC</label><input type="text" readonly value="Client HO · <?= h($job['client_ho_email']) ?> ; JEWELSETU · <?= h(setting('admin_email')) ?>"></div>
  <div class="f"><label>Subject</label><input type="text" name="subject" value="<?= h($subject) ?>"></div>
  <div class="f"><label>Message</label><textarea name="message" rows="5"><?= h($msg) ?></textarea></div>
  <p class="muted" style="font-size:12px">📎 <?= h($it['report_no']) ?>.pdf · already published to Client portal (<?= h($job['client_code']) ?>) &amp; Vendor portal (<?= h($job['vendor_name']) ?>)</p>
  <?php if (setting('mail_transport', 'none') === 'none'): ?><div class="note f" style="font-size:12px">Email transport is off — sends are logged only. Configure in Settings → Email.</div><?php endif ?>
  <button class="btn pri block">SEND REPORT</button></form></div>
<div class="card"><h2 class="mb">Email history</h2><?php if (!$emails): echo empty_state('Not emailed yet.'); else: ?>
<table class="t"><tr><th>Recipient</th><th>Sent</th><th>Status</th></tr><?php foreach ($emails as $e): ?><tr><td><?= h($e['recipient_type']) ?><br><small><?= h($e['email']) ?></small></td><td><?= fdt($e['sent_at']) ?></td><td><?= badge($e['status']) ?><?= $e['error'] ? '<br><small title="' . h($e['error']) . '">' . h(mb_strimwidth($e['error'], 0, 40, '…')) . '</small>' : '' ?></td></tr><?php endforeach ?></table><?php endif ?></div>
<div class="card"><h2 class="mb">Revision &amp; audit</h2>
<table class="t"><tr><th>Rev</th><th>By</th><th>Field</th><th>Old → New</th><th>Time</th></tr>
<?php foreach ($revs as $a): ?><tr><td><?= sprintf('%02d', $a['rev']) ?></td><td><?= h($a['uname'] ?: 'System') ?></td><td><?= h($a['field'] ?: str_replace('_', ' ', $a['action'])) ?></td><td><?= h(($a['old_val'] ?? '—') . ' → ' . ($a['new_val'] ?? '')) ?></td><td><?= fdt($a['created_at']) ?></td></tr><?php endforeach ?></table>
<?php if (count($oldPdfs) > 1): ?><p style="font-size:12px">Kept versions: <?php foreach ($oldPdfs as $f): ?><a class="pill" target="_blank" href="<?= url('pdf', ['file' => 'reports/' . basename($f)]) ?>"><?= h(basename($f)) ?></a><?php endforeach ?></p><?php endif ?></div>
<?php endif ?>
</div></div>
<?php layout_end();
