<?php
if (user()) redirect(home_for(role()));
$err = '';
if (is_post()) {
    $id = trim((string)post('login')); $pw = (string)post('password');
    $u = one('SELECT * FROM users WHERE (LOWER(email)=LOWER(?) OR UPPER(emp_code)=UPPER(?)) AND status=?', [$id, $id, 'ACTIVE']);
    if ($u && password_verify($pw, $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['uid'] = $u['id'];
        q('UPDATE users SET last_login=? WHERE id=?', [now(), $u['id']]);
        redirect(home_for($u['role']));
    }
    $err = 'Invalid credentials or inactive account.';
}
$demo = $GLOBALS['CFG']['seed_demo'];
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login · JEWELSETU Q-TRACK</title><link rel="icon" href="<?= asset('img/icon-192.png') ?>"><link rel="manifest" href="<?= url('manifest.webmanifest') ?>">
<meta name="theme-color" content="#2b1a1d"><link rel="stylesheet" href="<?= asset('css/app.css') ?>"></head><body>
<div class="login">
  <div class="hero">
    <div>
      <div class="lg"><img src="<?= asset('img/logo.png') ?>" alt="Jewelsetu Quality Services"></div>
      <h2>QC Inspection &amp;<br>Reporting System<br><em>From Inspection to Insight</em></h2>
      <p style="color:#c9b0a4;max-width:460px">One platform for JEWELSETU, brands and factories — QC requests, on-site inspection, locked PDF reports and vendor quality analytics.</p>
    </div>
    <div class="feats">
      <div>Excel → QC jobs in minutes</div><div>On-site inspection with photos</div>
      <div>Auto PDF report &amp; email</div><div>Vendor quality analytics</div>
    </div>
  </div>
  <div class="form"><div class="box">
    <h1>Welcome back</h1><p class="muted" style="margin-top:0">Sign in with your assigned credentials</p>
    <?php if ($err): ?><div class="flash f-err"><?= h($err) ?></div><?php endif ?>
    <form method="post" autocomplete="on">
      <div class="f"><label>Email / Employee ID / Vendor code</label><input type="text" name="login" id="lg" required value="<?= h(post('login')) ?>" autofocus></div>
      <div class="f"><label>Password</label><input type="password" name="password" id="pw" required></div>
      <div class="actions" style="justify-content:space-between;margin:6px 0 16px"><label class="chk"><input type="checkbox" checked> Remember me</label>
        <a href="#" onclick="alert('Please contact JEWELSETU admin to reset your password.');return false">Forgot password?</a></div>
      <button class="btn pri block" style="padding:12px">LOGIN</button>
    </form>
    <p class="muted" style="font-size:12px;margin-top:14px">One login · 6 roles · routes to Internal, Client, Vendor portal or the Inspector app</p>
    <div class="roles"><span>SUPER ADMIN</span><span>BACK OFFICE</span><span>QC MANAGER</span><span>INSPECTOR</span><span>CLIENT</span><span>VENDOR</span></div>
    <?php if ($demo): ?>
    <div class="demo"><b>Demo logins</b> (click to fill)<br>
      <button onclick="f('admin@jewelsetu.com','Admin@123')">Super Admin</button>
      <button onclick="f('sonal@jewelsetu.com','Demo@123')">Back Office</button>
      <button onclick="f('ravi@jewelsetu.com','Demo@123')">QC Manager</button>
      <button onclick="f('JS-010','Demo@123')">Inspector (JS-010)</button>
      <button onclick="f('rajiv@kpjewels.com','Demo@123')">Client · KP</button>
      <button onclick="f('VND-ASHOK','Demo@123')">Vendor · Ashok</button>
    </div>
    <script>function f(a,b){lg.value=a;pw.value=b;}</script>
    <?php endif ?>
  </div></div>
</div></body></html>
