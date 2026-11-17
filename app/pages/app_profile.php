<?php
require_once ROOT . '/app/stats.php';
$me = user(); $k = inspector_kpis((int)$me['id']);
app_start('Profile');
?>
<div class="mcard" style="text-align:center"><div class="avatar" style="width:64px;height:64px;font-size:22px;margin:0 auto 8px;display:grid"><?= h(initials($me['name'])) ?></div>
  <b style="font-size:17px"><?= h($me['name']) ?></b><p class="muted" style="margin:2px 0 0"><?= h($me['emp_code']) ?> · Inspector · <?= h($me['location']) ?></p>
  <div class="mstats"><div><b><?= $k['assigned'] ?></b><small>Assigned</small></div><div><b><?= $k['done'] ?></b><small>Completed</small></div><div><b><?= round($k['first']) ?>%</b><small>Approval</small></div></div></div>
<div class="mcard"><b>Offline data &amp; sync</b><p class="muted" style="font-size:12.5px">Pending uploads: <b id="qc2">0</b></p><button class="bigbtn alt" onclick="qtFlush().then(()=>location.reload())">SYNC NOW</button>
<button class="bigbtn alt mt" onclick="if(confirm('Clear local drafts on this phone?')){Object.keys(localStorage).filter(k=>k.startsWith('qt_draft_')).forEach(k=>localStorage.removeItem(k));alert('Cleared');}">Clear local drafts</button></div>
<div class="mcard"><b>Change password</b>
<form method="post" action="<?= url('profile') ?>" class="mt"><?= csrf_field() ?><div class="f"><input type="password" name="current" placeholder="Current password" required></div><div class="f"><input type="password" name="new" placeholder="New password" minlength="6" required></div><button class="bigbtn alt">Update</button></form></div>
<a class="bigbtn ruby" href="<?= url('logout') ?>">Logout</a>
<script>try{document.getElementById('qc2').textContent=JSON.parse(localStorage.getItem('qt_queue_v1')||'[]').length}catch(e){}</script>
<?php app_end('profile');
