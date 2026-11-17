<?php
$me = user();
$list = all('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 60', [$me['id']]);
q('UPDATE notifications SET is_read=1 WHERE user_id=?', [$me['id']]);
$ret = all("SELECT i.*, j.job_no FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id WHERE (j.inspector_id=? OR j.inspector2_id=?) AND i.status='RETURNED'", [$me['id'], $me['id']]);
app_start('Notifications');
foreach ($ret as $r): ?>
<div class="mcard" style="border-color:#efc3ca"><b style="color:var(--ruby)">Inspection returned for correction</b><p style="margin:6px 0;font-size:13px"><?= h($r['job_no']) ?> · <?= h($r['sku']) ?> — <?= h($r['return_reason']) ?></p>
  <p style="font-size:12px;margin:0 0 8px"><?php foreach (explode(',', (string)$r['return_sections']) as $s) if (trim($s)) echo '<span class="badge b-returned">RETURNED</span> ' . h(trim($s)) . '<br>'; ?></p>
  <a class="bigbtn ruby" href="<?= url('app/inspect', ['item' => $r['id']]) ?>">FIX &amp; RESUBMIT</a></div>
<?php endforeach ?>
<div class="mcard"><div class="mlist"><?php if (!$list) echo empty_state('No notifications.'); foreach ($list as $n): ?>
<a href="<?= $n['link'] ? url($n['link']) : '#' ?>"><span><b style="font-weight:<?= $n['is_read'] ? 500 : 700 ?>"><?= h($n['text']) ?></b><small><?= fdt($n['created_at']) ?></small></span></a><?php endforeach ?></div></div>
<?php app_end('alerts');
