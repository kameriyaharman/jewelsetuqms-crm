<?php if (role() === 'inspector') { app_start('Not found', url('app')); echo empty_state('Page not found.'); app_end(); return; }
layout_start('Page not found'); ?>
<div class="card"><div class="empty">This page does not exist.<br><br><?= btn('Go to dashboard', url('/'), 'pri') ?></div></div>
<?php layout_end();
