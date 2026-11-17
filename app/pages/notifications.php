<?php
$uid = user()['id'];
$list = all('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 100', [$uid]);
q('UPDATE notifications SET is_read=1 WHERE user_id=?', [$uid]);
layout_start('Notifications', 'notifications'); ?>
<div class="card"><?php if (!$list) echo empty_state('No notifications yet.'); ?>
<div class="mlist"><?php foreach ($list as $n): ?>
  <a href="<?= $n['link'] ? url($n['link']) : '#' ?>"><span><b style="<?= $n['is_read'] ? 'font-weight:500' : '' ?>"><?= h($n['text']) ?></b></span><small><?= fdt($n['created_at']) ?></small></a>
<?php endforeach ?></div></div>
<?php layout_end();
