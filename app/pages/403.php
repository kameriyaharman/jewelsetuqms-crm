<?php layout_start('Access denied'); ?>
<div class="card"><div class="empty">You don't have permission to open this page.<br><br><?= btn('Go to dashboard', url('/'), 'pri') ?></div></div>
<?php layout_end();
