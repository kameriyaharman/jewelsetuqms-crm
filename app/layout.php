<?php
function menu_for(string $role): array {
    $pending = 0; $conc = 0; $holds = 0;
    try {
        if (in_array($role, ['super_admin', 'qc_manager'], true)) $pending = (int)val("SELECT COUNT(*) FROM qc_items WHERE status IN ('SUBMITTED','UNDER_REVIEW')");
        if ($role === 'client') {
            $cid = user()['client_id'];
            $conc = (int)val("SELECT COUNT(*) FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id WHERE j.client_id=? AND i.concession_status='PENDING'", [$cid]);
            $holds = (int)val("SELECT COUNT(*) FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id WHERE j.client_id=? AND i.hold_status='PENDING' AND i.hold_for='client'", [$cid]);
        }
        if ($role === 'vendor') $holds = (int)val("SELECT COUNT(*) FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id WHERE j.vendor_id=? AND i.hold_status='PENDING' AND i.hold_for='vendor'", [user()['vendor_id']]);
    } catch (Throwable $e) {}
    $req = (int)val("SELECT COUNT(*) FROM qc_jobs WHERE status='REQUESTED'");
    $ic = fn($n) => $n;
    return match ($role) {
        'super_admin' => [
            ['dashboard', 'Dashboard', 'grid'], ['clients', 'Clients', 'building'], ['vendors', 'Vendors', 'factory'], ['mapping', 'Client–Vendor Mapping', 'link'],
            ['_', 'QC Management'], ['requests', 'QC Requests', 'inbox', $req], ['import', 'Import Excel', 'upload'], ['jobs', 'QC Jobs', 'clip'],
            ['schedule', 'QC Schedule', 'cal'], ['review', 'Pending Review', 'eye', $pending],
            ['_', 'Insight'], ['reports', 'Reports', 'file'], ['analytics', 'Analytics', 'chart'], ['approvals', 'Holds & Concessions', 'pause'],
            ['_', 'Admin'], ['team', 'Team', 'users'], ['settings', 'Settings', 'cog']],
        'back_office' => [
            ['dashboard', 'Dashboard', 'grid'], ['clients', 'Clients', 'building'], ['vendors', 'Vendors', 'factory'],
            ['_', 'QC Management'], ['requests', 'QC Requests', 'inbox', $req], ['request-new', 'New QC Request', 'plus'], ['import', 'Import Excel', 'upload'],
            ['jobs', 'QC Jobs', 'clip'], ['schedule', 'QC Schedule', 'cal'],
            ['_', 'Data'], ['reports', 'Reports', 'file'], ['analytics', 'Analytics', 'chart'], ['team', 'Team', 'users'], ['settings', 'Masters', 'cog']],
        'qc_manager' => [
            ['dashboard', 'Dashboard', 'grid'], ['vendors', 'Vendors', 'factory'],
            ['_', 'QC Management'], ['requests', 'QC Requests', 'inbox'], ['jobs', 'QC Jobs & Assign', 'clip'], ['schedule', 'QC Schedule', 'cal'],
            ['review', 'Pending Review', 'eye', $pending], ['approvals', 'Holds & Concessions', 'pause'],
            ['_', 'Insight'], ['reports', 'Reports', 'file'], ['analytics', 'Analytics', 'chart'], ['team', 'Team', 'users'], ['settings', 'Masters', 'cog']],
        'client' => [
            ['client', 'Dashboard', 'grid'], ['requests', 'QC Requests', 'inbox'],
            ...(flag('enable_client_qc_request') ? [['request-new', 'Request QC', 'plus']] : []),
            ['vendors', 'My Vendors', 'factory'], ['schedule', 'QC Schedule', 'cal'], ['approvals', 'Approvals', 'check', $conc + $holds],
            ['reports', 'Reports', 'file'], ['analytics', 'Analytics', 'chart']],
        'vendor' => [
            ['vendor', 'Dashboard', 'grid'],
            ...(flag('enable_vendor_qc_request') ? [['request-new', 'New QC Request', 'plus'], ['import', 'Bulk Excel Request', 'upload']] : []),
            ['requests', 'My QC Requests', 'inbox'], ['schedule', 'Schedule', 'cal'], ['approvals', 'Hold Approvals', 'check', $holds],
            ['reports', 'Reports', 'file'], ['profile', 'Profile', 'user']],
        default => [],
    };
}
function icon(string $n): string {
    $p = [
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'building' => '<path d="M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"/><path d="M16 9h2a2 2 0 0 1 2 2v10"/><path d="M8 7h4M8 11h4M8 15h4M3 21h18"/>',
        'factory' => '<path d="M3 21V10l6 4V10l6 4V6l6 3v12z"/><path d="M3 21h18"/>',
        'link' => '<path d="M10 14a4 4 0 0 0 5.66 0l3-3a4 4 0 0 0-5.66-5.66l-1 1"/><path d="M14 10a4 4 0 0 0-5.66 0l-3 3a4 4 0 0 0 5.66 5.66l1-1"/>',
        'inbox' => '<path d="M4 13h4l2 3h4l2-3h4"/><path d="M5 5h14l2 8v6H3v-6z"/>',
        'upload' => '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v4h16v-4"/>',
        'clip' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V2h6v2M9 10h6M9 14h6M9 18h3"/>',
        'cal' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'eye' => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'file' => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/>',
        'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20c0-3.5 3-6 7-6s7 2.5 7 6"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M22 20c0-3-2-5.2-5-5.8"/>',
        'cog' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'pause' => '<rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/>',
        'bell' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'home' => '<path d="M3 11 12 3l9 8v10h-6v-6H9v6H3z"/>',
        'scan' => '<path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2M7 12h10"/>',
        'drafts' => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M14 6l4 4"/>',
        'menu' => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'box' => '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/>',
        'x' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'alert' => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'download' => '<path d="M12 4v12M7 11l5 5 5-5M4 20h16"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'camera' => '<path d="M3 8a2 2 0 0 1 2-2h2l2-2h6l2 2h2a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><circle cx="12" cy="13" r="4"/>',
    ][$n] ?? '';
    return '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $p . '</svg>';
}

function layout_start(string $title, string $active = '', string $actions = '', string $sub = ''): void {
    $u = user(); $role = $u['role'];
    $portal = match ($role) { 'client' => 'CLIENT PORTAL', 'vendor' => 'VENDOR PORTAL', default => 'INTERNAL PORTAL' };
    $who = match ($role) {
        'client' => val('SELECT name FROM clients WHERE id=?', [$u['client_id']]),
        'vendor' => val('SELECT name FROM vendors WHERE id=?', [$u['vendor_id']]),
        default => 'JEWELSETU HQ' };
    $unread = (int)val('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0', [$u['id']]);
    ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($title) ?> · JEWELSETU Q-TRACK</title>
<link rel="icon" href="<?= asset('img/icon-192.png') ?>">
<meta name="theme-color" content="#2b1a1d">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head><body>
<div class="shell">
<aside class="side" id="side">
  <a class="brand" href="<?= url('/') ?>"><img src="<?= asset('img/mark-t.png') ?>" alt=""><span><b>JEWELSETU</b><small>Q-TRACK · QC SYSTEM</small></span></a>
  <div class="portal-tag"><?= $portal ?></div>
  <nav>
  <?php foreach (menu_for($role) as $m): if ($m[0] === '_'): ?>
    <div class="nav-h"><?= h($m[1]) ?></div>
  <?php else: ?>
    <a href="<?= url($m[0]) ?>" class="<?= $active === $m[0] ? 'on' : '' ?>"><?= icon($m[2]) ?><span><?= h($m[1]) ?></span><?php if (!empty($m[3])): ?><em><?= (int)$m[3] ?></em><?php endif ?></a>
  <?php endif; endforeach ?>
  </nav>
  <div class="me">
    <div class="av"><?= h(initials($u['name'])) ?></div>
    <div><b><?= h($u['name']) ?></b><small><?= h(ROLES[$role]) ?> · <?= h($who) ?></small></div>
    <a href="<?= url('logout') ?>" title="Logout"><?= icon('logout') ?></a>
  </div>
</aside>
<main class="main">
  <header class="top">
    <button class="burger" onclick="document.body.classList.toggle('nav-open')"><?= icon('menu') ?></button>
    <div><h1><?= h($title) ?></h1><?php if ($sub): ?><p class="sub"><?= $sub ?></p><?php endif ?></div>
    <div class="top-r"><?= $actions ?>
      <a class="bell" href="<?= url('notifications') ?>"><?= icon('bell') ?><?php if ($unread): ?><i><?= $unread ?></i><?php endif ?></a>
    </div>
  </header>
  <div class="content">
  <?php foreach (flashes() as [$t, $m]): ?><div class="flash f-<?= h($t) ?>"><?= h($m) ?></div><?php endforeach ?>
<?php }

function layout_end(): void { ?>
  </div>
</main>
</div>
<div class="scrim" onclick="document.body.classList.remove('nav-open')"></div>
<script>window.QT={base:<?= json_encode(base()) ?>,csrf:<?= json_encode(csrf_token()) ?>};</script>
<script src="<?= asset('js/app.js') ?>"></script>
</body></html>
<?php }

/** Inspector mobile app shell */
function app_start(string $title, ?string $backUrl = null, string $right = ''): void { ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= h($title) ?> · Q-TRACK Inspector</title>
<link rel="manifest" href="<?= url('manifest.webmanifest') ?>">
<link rel="icon" href="<?= asset('img/icon-192.png') ?>"><link rel="apple-touch-icon" href="<?= asset('img/icon-192.png') ?>">
<meta name="theme-color" content="#2b1a1d"><meta name="apple-mobile-web-app-capable" content="yes">
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head><body class="mob">
<div class="mapp">
<header class="mtop">
  <?php if ($backUrl): ?><a class="mback" href="<?= h($backUrl) ?>">‹</a><?php else: ?><img src="<?= asset('img/mark-t.png') ?>" class="mlogo" alt=""><?php endif ?>
  <div class="mtitle"><?= h($title) ?></div>
  <div class="mright"><?= $right ?><span class="net" id="net">●</span></div>
</header>
<div class="mbody">
<?php foreach (flashes() as [$t, $m]): ?><div class="flash f-<?= h($t) ?>"><?= h($m) ?></div><?php endforeach ?>
<?php }
function app_end(string $tab = 'home'): void {
    $unread = (int)val('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0', [user()['id']]); ?>
</div>
<nav class="mtabs">
  <a href="<?= url('app') ?>" class="<?= $tab === 'home' ? 'on' : '' ?>"><?= icon('home') ?>Home</a>
  <a href="<?= url('app/history') ?>" class="<?= $tab === 'history' ? 'on' : '' ?>"><?= icon('clip') ?>My QC</a>
  <a href="<?= url('app', ['scan' => 1]) ?>" class="scanbtn"><?= icon('scan') ?>Scan</a>
  <a href="<?= url('app/alerts') ?>" class="<?= $tab === 'alerts' ? 'on' : '' ?>"><?= icon('bell') ?>Alerts<?= $unread ? "<i>$unread</i>" : '' ?></a>
  <a href="<?= url('app/profile') ?>" class="<?= $tab === 'profile' ? 'on' : '' ?>"><?= icon('user') ?>Profile</a>
</nav>
</div>
<script>window.QT={base:<?= json_encode(base()) ?>,csrf:<?= json_encode(csrf_token()) ?>};</script>
<script src="<?= asset('js/app.js') ?>"></script>
<script>if('serviceWorker' in navigator){navigator.serviceWorker.register(QT.base+'/sw.js',{scope:QT.base+'/'}).catch(()=>{});}</script>
</body></html>
<?php }

/* small UI helpers */
function kpi(string $label, $value, string $sub = '', string $tone = '', string $ic = 'box'): string {
    return '<div class="kpi ' . $tone . '"><div class="kic">' . icon($ic) . '</div><div><small>' . h($label) . '</small><b>' . $value . '</b>' . ($sub ? '<span>' . $sub . '</span>' : '') . '</div></div>';
}
function btn(string $label, string $href, string $cls = ''): string { return '<a class="btn ' . $cls . '" href="' . h($href) . '">' . $label . '</a>'; }
function sel(string $name, array $opts, $cur = null, string $attrs = '', ?string $blank = null): string {
    $o = '<select name="' . h($name) . '" ' . $attrs . '>' . ($blank !== null ? '<option value="">' . h($blank) . '</option>' : '');
    foreach ($opts as $k => $v) $o .= '<option value="' . h($k) . '"' . ((string)$cur === (string)$k ? ' selected' : '') . '>' . h($v) . '</option>';
    return $o . '</select>';
}
function bars(array $data, string $suffix = '%'): string {
    $max = max(array_merge([0.0001], array_values($data)));
    $o = '<div class="vbars">';
    foreach ($data as $k => $v) $o .= '<div class="vb"><div class="vbar" style="height:' . max(3, round($v / $max * 100)) . '%"><span>' . h(is_float($v) ? number_format($v, 1) : num($v)) . $suffix . '</span></div><small>' . h($k) . '</small></div>';
    return $o . '</div>';
}
function hbars(array $data, string $suffix = ''): string {
    $max = max(array_merge([0.0001], array_values($data)));
    $o = '<div class="hbars">';
    foreach ($data as $k => $v) $o .= '<div class="hb"><span>' . h($k) . '</span><div><i style="width:' . max(2, round($v / $max * 100)) . '%"></i></div><b>' . (is_float($v) ? number_format($v, 1) : num($v)) . $suffix . '</b></div>';
    return $o . '</div>';
}
function empty_state(string $msg): string { return '<div class="empty">' . h($msg) . '</div>'; }
