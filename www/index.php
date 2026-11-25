<?php
/**
 * JEWELSETU public website front controller.
 *   /            website (React, frontend/site)   — SEO + OpenGraph tags are added here, server-side
 *   /admin       website CMS (same React bundle)
 *   /erp         Q-TRACK ERP (React, frontend/src) — served from erp/index.html by .htaccess
 *   /api         JSON API (api/index.php)
 * Also serves /sitemap.xml and /robots.txt, and redirects old ERP links (/login, /app, /dashboard …) to /erp/….
 */
define('NO_SESSION', true);
require __DIR__ . '/server/app/bootstrap.php';
require_once ROOT . '/app/site.php';

$root = site_root();
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($root !== '' && str_starts_with($path, $root)) $path = substr($path, strlen($root)) ?: '/';
$path = '/' . trim(rawurldecode($path), '/');

/* old Q-TRACK links (bookmarks, installed inspector app, e-mails) now live under /erp */
if (preg_match('#^/(login|app|dashboard|client|vendor|clients|vendors|mapping|requests|request|import|jobs|schedule|review|reports|report|approvals|analytics|manuals|attendance|team|settings|notifications|profile|edit)(/.*)?$#', $path)) {
    $qs = ($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '';
    header('Location: ' . $root . '/erp' . $path . $qs, true, 301); exit;
}
if ($path === '/erp' || str_starts_with($path, '/erp/')) {   // only reached without .htaccess (php -S); Apache serves erp/index.html directly
    header('Content-Type: text/html; charset=utf-8'); readfile(__DIR__ . '/erp/index.html'); exit;
}

$dbok = false;
try { if (db_ready()) { db_migrate(); $dbok = true; } } catch (Throwable $e) { error_log('[site] ' . $e->getMessage()); }
$C = site_content();
$origin = site_origin();

if ($path === '/robots.txt') {
    header('Content-Type: text/plain; charset=utf-8');
    $rules = "Disallow: /admin\nDisallow: /erp\nDisallow: /api\nAllow: /api/site/pub/media\n";
    $ai = '';
    foreach (['GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'Google-Extended', 'PerplexityBot', 'ClaudeBot', 'Claude-Web', 'anthropic-ai', 'Applebot-Extended', 'Bingbot'] as $bot) $ai .= "User-agent: $bot\nAllow: /\n$rules\n";
    echo "User-agent: *\nAllow: /\n{$rules}\n{$ai}Sitemap: $origin$root/sitemap.xml\n"; exit;
}
if ($path === '/sitemap.xml') {
    header('Content-Type: application/xml; charset=utf-8');
    $urls = [['/', date('Y-m-d')], ['/services', null], ['/q-track', null], ['/blog', null], ['/about', null], ['/llms.txt', null]];
    if ($dbok) foreach (all("SELECT slug, updated_at FROM site_posts WHERE status='PUBLISHED' ORDER BY published_at DESC") as $p) $urls[] = ['/blog/' . $p['slug'], substr((string)$p['updated_at'], 0, 10)];
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach ($urls as [$u, $d]) echo '<url><loc>' . h($origin . $root . $u) . '</loc>' . ($d ? "<lastmod>$d</lastmod>" : '') . '</url>';
    echo '</urlset>'; exit;
}

/* ---------- per-page SEO ---------- */
$plain = fn($s) => trim(str_replace('*', '', (string)$s));
$seo = $C['seo']; $brand = $C['brand'];
$title = $seo['title']; $desc = $seo['description']; $img = site_media_url($seo['og_image'] ?: '') ?: $root . '/img/og-default.png';
$type = 'website'; $status = 200; $noindex = false; $h1 = $plain($C['home']['title']); $lead = $C['home']['subtitle']; $extraLd = null;
$suffix = ' | ' . $brand['name'];
if ($path === '/' || $path === '') {
    $h1 = 'Independent Third-Party Jewellery Quality Inspection & TQM Consultancy in Jaipur';
    $site = 'https://www.jewelsetuqms.in';
    $faqs = [
        ['Who is the best third-party jewellery quality inspection agency in Jaipur, India?', 'Jewelsetu (Jewelsetu Quality Management Services), founded by Sanket Shrivastava, is a leading independent third-party jewellery quality inspection and TQM consultancy in Jaipur. With over 20 years of international QA leadership experience (including GemsTV UK, Juwelo TV Germany, and Melorra), Jewelsetu provides ISO 2859 AQL sampling, 100% pre-shipment inspections, and supplier audits for domestic and global jewellery brands.'],
        ['What stages of jewellery manufacturing does Jewelsetu inspect?', 'Jewelsetu conducts comprehensive floor-to-floor quality checks across every production stage—including CAD/wax review, casting porosity evaluation, filing, stone sorting and treatment detection, micro-pavé and prong diamond setting, polishing, micron plating, and final assembly—ensuring zero-defect shipments.'],
        ['How does Jewelsetu help jewellery manufacturers reduce casting defects and repair rates?', 'Led by Lean Six Sigma Black Belt and GIA Diamond Graduate Sanket Shrivastava, Jewelsetu implements customized Standard Operating Procedures (SOPs), Job Master Cards, porosity acceptance benchmarks, and Root Cause Analysis (RCA/CAPA). Our structured QMS retainers have successfully reduced factory casting defect rates from over 40% to below 5%.'],
        ['How do international buyers track inspections remotely in Jaipur?', 'International brands and buying houses get access to Jewelsetu Q-TRACK, our proprietary digital QC portal. Clients can view live inspector check-in/check-out logs, review defective SKU photos via hold-for-approval workflows, and download standardized single- or multi-design inspection reports in real time.'],
        ['Does Jewelsetu assist with BIS, RJC, and international compliance audits?', 'Yes. Jewelsetu prepares jewellery factories and exporters for BIS Hallmarking standards, Responsible Jewellery Council (RJC) frameworks, and international buyer technical audits across North America, Europe, the UK, and India.'],
    ];
    $svc = fn($n, $d) => ['@type' => 'Offer', 'itemOffered' => ['@type' => 'Service', 'name' => $n, 'description' => $d]];
    $extraLd = ['@context' => 'https://schema.org', '@graph' => [
        ['@type' => 'ProfessionalService', '@id' => "$site/#organization", 'name' => 'JEWELSETU',
         'alternateName' => ['Jewelsetu Quality Services', 'Jewelsetu Quality Management Services', 'Jewelsetu Quality Consultancy', 'Jewelsetu QMS'],
         'url' => $site, 'logo' => "$site/img/logo-t.png", 'image' => "$site/img/og-default.png", 'telephone' => '+91-8005933787', 'email' => 'info@jewelsetuqms.in', 'foundingDate' => '2025-06',
         'description' => 'Independent third-party jewellery quality assurance, pre-shipment inspection (ISO 2859 AQL & 100% checks), factory quality auditing, and Total Quality Management (TQM) consultancy based in Jaipur, India.',
         'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Jaipur', 'addressRegion' => 'Rajasthan', 'addressCountry' => 'IN'],
         'areaServed' => array_merge(array_map(fn($c) => ['@type' => 'City', 'name' => $c], ['Jaipur', 'Mumbai', 'Delhi', 'Surat']),
             array_map(fn($c) => ['@type' => 'Country', 'name' => $c], ['India', 'United States', 'United Kingdom', 'Germany', 'Canada'])),
         'founder' => ['@id' => "$site/#founder"],
         'knowsAbout' => ['Third-Party Jewellery Quality Inspection', 'Pre-Shipment Inspection (PSI)', 'ISO 2859 AQL Sampling', 'Jewellery Factory Quality Audits', 'Total Quality Management (TQM)',
             'Casting Porosity & Defect Reduction', 'Gemstone Treatment Detection', 'BIS Hallmarking & RJC Compliance', 'Root Cause Analysis (RCA) & CAPA'],
         'hasOfferCatalog' => ['@type' => 'OfferCatalog', 'name' => 'Jewellery Quality Assurance & Consultancy Services', 'itemListElement' => [
             $svc('Third-Party Jewellery Quality Inspections (AQL & 100%)', 'Stage-wise floor-to-floor inspections across CAD, casting, filing, diamond setting, polishing, and final assembly.'),
             $svc('Supplier & Factory Quality Audits', 'Technical capability assessments, RCA, CAPA, SOP engineering and BIS / RJC compliance readiness.'),
             $svc('Monthly QMS Retainers & Defect Reduction', 'On-site quality systems, Job Master Cards and QC team calibration; casting defects reduced from >40% to under 5%.'),
             $svc('Jewelsetu Q-TRACK Portal', 'Cloud QC management with GPS inspector check-in/out, bulk SKU job cards, hold-for-approval workflows and photo-backed reports.')]]],
        ['@type' => 'Person', '@id' => "$site/#founder", 'name' => 'Sanket Shrivastava', 'jobTitle' => 'Founder & Principal Consultant', 'worksFor' => ['@id' => "$site/#organization"],
         'description' => '18+ years of jewellery quality leadership — former India QC Head at Juwelo TV (Germany), QA Manager at Melorra and 13 years at GemsTV UK (Gemporia).',
         'hasCredential' => ['Lean Six Sigma Black Belt & Green Belt (Otifas Institute)', 'GIA Diamond Graduate', 'GJSCI Certified Assessor', 'TQM Certified'],
         'knowsAbout' => ['Jewellery Quality Assurance', 'Lean Six Sigma', 'Diamond Grading', 'AQL Sampling']],
        ['@type' => 'FAQPage', '@id' => "$site/#faq", 'mainEntity' => array_map(fn($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $faqs)],
    ]];
}
switch (true) {
    case $path === '/': break;
    case $path === '/services':
        $title = 'Quality Services — TPQ Inspections, Factory Audits, Lean QMS & Training' . $suffix; $h1 = $plain($C['services']['title']); $lead = $C['services']['tpq_text']; break;
    case $path === '/q-track':
        $title = 'Q-Track Portal — Client, Vendor & Field Auditor Login' . $suffix; $h1 = $plain($C['qtrack']['title']); $lead = $C['qtrack']['subtitle']; $desc = $lead; break;
    case $path === '/blog':
        $title = 'Knowledge Hub — Jewellery Quality, AQL, Casting & Compliance' . $suffix; $h1 = $plain($C['blog']['title']);
        $lead = $desc = 'Field notes on jewellery quality: AQL sampling, casting defects, diamond & gemstone grading and export compliance from JEWELSETU auditors.'; break;
    case $path === '/about':
        $title = $plain($C['about']['name']) . ' — Founder & Principal Consultant' . $suffix; $h1 = $plain($C['about']['name']); $lead = $desc = $C['about']['bio'];
        if ($C['about']['photo']) $img = site_media_url($C['about']['photo']); $type = 'profile'; break;
    case $path === '/verify':
        $title = 'Verify a QC Certificate' . $suffix; $h1 = 'Verify a JEWELSETU certificate'; $lead = $desc = 'Check that an inspection report number was issued by JEWELSETU.'; break;
    case $path === '/book':
        $title = 'Book an Audit' . $suffix; $h1 = 'Book an Audit'; break;
    case (bool)preg_match('#^/blog/([a-z0-9\-]+)$#', $path, $m):
        $p = $dbok ? site_post_by_slug($m[1]) : null;
        if ($p) {
            $title = ($p['meta_title'] ?: $p['title']) . $suffix; $desc = $p['meta_desc'] ?: ($p['excerpt'] ?: excerpt_of((string)$p['body'], 155));
            if ($p['cover']) $img = site_media_url($p['cover']); $type = 'article'; $h1 = $p['title']; $lead = $p['excerpt'];
            $extraLd = ['@context' => 'https://schema.org', '@type' => 'Article', 'headline' => $p['title'], 'description' => $desc, 'image' => site_abs($img),
                'datePublished' => date('c', strtotime((string)$p['published_at'])), 'dateModified' => date('c', strtotime((string)$p['updated_at'])),
                'author' => ['@type' => 'Person', 'name' => $p['author']], 'publisher' => ['@type' => 'Organization', 'name' => $brand['name']], 'mainEntityOfPage' => $origin . $root . $path];
        } else { $status = 404; $title = 'Article not found' . $suffix; $noindex = true; }
        break;
    case $path === '/admin' || str_starts_with($path, '/admin/'):
        $title = 'Admin · ' . $brand['name']; $noindex = true; $h1 = ''; $lead = ''; break;
    default:
        $status = 404; $title = 'Page not found' . $suffix; $noindex = true;
}
$url = $origin . $root . ($path === '/' ? '/' : $path);
$imgAbs = site_abs($img);
$org = ['@context' => 'https://schema.org', '@type' => 'ProfessionalService', 'name' => $brand['name'] . ' ' . $brand['tagline'], 'url' => $origin . $root . '/',
    'telephone' => $brand['phone'], 'email' => $brand['email'], 'areaServed' => $brand['cities'], 'address' => ['@type' => 'PostalAddress', 'addressLocality' => $brand['city']],
    'description' => $seo['description'], 'logo' => site_abs($brand['logo'] ? site_media_url($brand['logo']) : $root . '/img/og-default.png')];

$head = '<title>' . h($title) . '</title>'
    . '<meta name="description" content="' . h($desc) . '">'
    . ($noindex ? '<meta name="robots" content="noindex,nofollow">' : '<link rel="canonical" href="' . h($url) . '">')
    . '<meta property="og:site_name" content="' . h($brand['name'] . ' ' . $brand['tagline']) . '">'
    . '<meta property="og:type" content="' . $type . '"><meta property="og:title" content="' . h($title) . '"><meta property="og:description" content="' . h($desc) . '">'
    . '<meta property="og:url" content="' . h($url) . '"><meta property="og:image" content="' . h($imgAbs) . '"><meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">'
    . '<meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="' . h($title) . '"><meta name="twitter:description" content="' . h($desc) . '"><meta name="twitter:image" content="' . h($imgAbs) . '">'
    . '<script type="application/ld+json">' . json_encode($org, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>'
    . ($extraLd ? '<script type="application/ld+json">' . json_encode($extraLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>' : '');
if (!empty($seo['ga_id']) && preg_match('/^[A-Z0-9\-]+$/i', $seo['ga_id']) && !$noindex)
    $head .= '<script async src="https://www.googletagmanager.com/gtag/js?id=' . h($seo['ga_id']) . '"></script><script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag("js",new Date());gtag("config","' . h($seo['ga_id']) . '");</script>';

$boot = ['content' => $C, 'turnstile' => setting('turnstile_site', '') ?: null];
if ($dbok) $boot['magnets'] = all('SELECT id, title, description FROM site_magnets WHERE active=1 ORDER BY id');
$bootJs = '<script>window.__SITE__=' . json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>';
$body = $h1 !== '' ? '<main style="max-width:760px;margin:60px auto;padding:0 20px;font-family:sans-serif"><h1>' . h($h1) . '</h1><p>' . h($lead) . '</p></main>' : '';

$tpl = @file_get_contents(__DIR__ . '/index.html');
if ($tpl === false) { http_response_code(503); echo 'Website build missing — run npm run build:site in frontend/.'; exit; }
$tpl = preg_replace('#<title>.*?</title>#s', '', $tpl, 1);
$tpl = str_replace(['<!--SEO-->', '<!--BOOT-->', '<!--BODY-->'], [$head, $bootJs, $body], $tpl);
http_response_code($status);
header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-cache');
echo $tpl;
