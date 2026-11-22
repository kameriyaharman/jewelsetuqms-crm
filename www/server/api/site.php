<?php
/* ---------- public website (jewelsetuqms.in) + /admin CMS ---------- */
require_once ROOT . '/app/site.php';

/* ================= public ================= */

api('GET', 'site/pub/content', null, function () {
    $c = site_content();
    return ['content' => $c, 'turnstile' => setting('turnstile_site', '') ?: null,
        'magnets' => all('SELECT id, title, description FROM site_magnets WHERE active=1 ORDER BY id')];
});

api('GET', 'site/pub/posts', null, function () {
    $w = ["status='PUBLISHED'"]; $p = [];
    if ($c = trim((string)in_('cat'))) { $w[] = 'category=?'; $p[] = $c; }
    if ($t = trim((string)in_('tag'))) { $w[] = 'tags LIKE ?'; $p[] = '%' . $t . '%'; }
    if ($s = trim((string)in_('q'))) { $w[] = '(title LIKE ? OR excerpt LIKE ? OR tags LIKE ? OR category LIKE ?)'; array_push($p, "%$s%", "%$s%", "%$s%", "%$s%"); }
    $lim = max(1, min(500, (int)in_('limit', 30)));
    $rows = all('SELECT id, slug, title, excerpt, cover, category, tags, author, read_min, featured, published_at, status FROM site_posts WHERE ' . implode(' AND ', $w) . " ORDER BY published_at DESC, id DESC LIMIT $lim", $p);
    return ['posts' => array_map('post_card', $rows)];
});

api('GET', 'site/pub/post', null, function () {
    $slug = (string)in_('slug');
    $p = site_post_by_slug($slug);
    if (!$p && ($id = (int)in_('preview')) && user() && is_role(...SITE_ADMIN)) $p = one('SELECT * FROM site_posts WHERE id=?', [$id]);
    if (!$p) fail('Article not found', 404);
    if ($p['status'] === 'PUBLISHED') q('UPDATE site_posts SET views=views+1 WHERE id=?', [$p['id']]);
    $mag = $p['magnet_id'] ? one('SELECT id, title, description FROM site_magnets WHERE id=? AND active=1', [$p['magnet_id']]) : null;
    $rel = all("SELECT id, slug, title, excerpt, cover, category, tags, author, read_min, featured, published_at, status FROM site_posts WHERE status='PUBLISHED' AND id<>? ORDER BY (category=?) DESC, published_at DESC LIMIT 3", [$p['id'], $p['category']]);
    return ['post' => post_card($p) + ['body' => clean_html((string)$p['body']) , 'author_title' => $p['author_title'], 'meta_title' => $p['meta_title'], 'meta_desc' => $p['meta_desc']],
        'magnet' => $mag, 'related' => array_map('post_card', $rel)];
});

api('GET', 'site/pub/media', null, function () {
    $p = (string)in_('p');
    if (!preg_match('#^site/[a-z0-9_\-/]+\.(jpg|jpeg|png|webp|gif|pdf)$#i', $p) || str_contains($p, '..')) fail('Not found', 404);
    $full = UPLOAD_DIR . '/' . $p; if (!is_file($full)) fail('Not found', 404);
    stream_file($full, basename($p));
});

function verify_turnstile(): void {
    $secret = setting('turnstile_secret', ''); if ($secret === '') return;
    $tok = (string)in_('cf_token');
    if ($tok === '') fail('Please complete the security check.');
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 8, 'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query(['secret' => $secret, 'response' => $tok, 'remoteip' => client_ip()])]]);
    $r = jdec(@file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $ctx));
    if (empty($r['success'])) fail('Security check failed — please try again.');
}
function lead_rate_check(string $table): void {
    if (trim((string)in_('website')) !== '') fail('Thanks!', 200);   // honeypot field
    $n = (int)val("SELECT COUNT(*) FROM $table WHERE ip=? AND created_at>=?", [client_ip(), date('Y-m-d H:i:s', time() - 3600)]);
    if ($n >= 12) fail('Too many requests from your network — please email us instead.', 429);
}

/** 3-step "Book an Audit" form */
api('POST', 'site/pub/lead', null, function () {
    lead_rate_check('site_leads');
    $name = trim((string)in_('name')); $email = trim((string)in_('email')); $phone = trim((string)in_('phone'));
    if ($name === '' || ($email === '' && $phone === '')) fail('Please enter your name and an email or phone number.');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Please enter a valid email address.');
    verify_turnstile();
    $file = null;
    if (!empty($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['file']['size'] > 10 * 1024 * 1024) fail('File must be under 10 MB.');
        $file = save_upload($_FILES['file'], 'site-leads', ['pdf', 'xlsx', 'xls', 'docx', 'doc', 'jpg', 'jpeg', 'png', 'csv']);
        if (!$file) fail('Only PDF, Excel, Word or image files are allowed.');
    }
    $cut = fn($k, $n = 250) => mb_substr(trim((string)in_($k)), 0, $n);
    $cats = in_('categories'); if (is_array($cats)) $cats = implode(', ', $cats);
    $d = ['name' => mb_substr($name, 0, 120), 'company' => $cut('company'), 'email' => mb_substr($email, 0, 190), 'phone' => mb_substr($phone, 0, 50),
        'service' => $cut('service', 60), 'categories' => mb_substr((string)$cats, 0, 250), 'batch_qty' => $cut('batch_qty', 60), 'hub' => $cut('hub', 80),
        'pref_date' => $cut('pref_date', 20), 'message' => $cut('message', 3000), 'file_path' => $file['path'] ?? null, 'file_name' => $file['name'] ?? null,
        'source' => $cut('source', 80) ?: 'Website', 'status' => 'NEW', 'ip' => client_ip(), 'created_at' => now(), 'updated_at' => now()];
    $id = insert('site_leads', $d);
    after_response(function () use ($d, $id) { lead_emails($d, $id); });
    return ['ok' => true, 'id' => $id];
});
function lead_emails(array $d, int $id): void {
    require_once ROOT . '/app/mailer.php';
    $c = site_content(); $bk = $c['booking']; $brand = $c['brand'];
    $to = trim((string)($bk['notify_email'] ?? '')) ?: setting('admin_email', '');
    $rows = ''; foreach (['Name' => 'name', 'Company' => 'company', 'Email' => 'email', 'Phone / WhatsApp' => 'phone', 'Service' => 'service', 'Product' => 'categories',
        'Batch / staff' => 'batch_qty', 'Hub' => 'hub', 'Preferred date' => 'pref_date', 'Message' => 'message', 'File' => 'file_name'] as $l => $k)
        if (!empty($d[$k])) $rows .= '<tr><td style="padding:6px 10px;color:#6b7280;border-bottom:1px solid #eee">' . h($l) . '</td><td style="padding:6px 10px;border-bottom:1px solid #eee"><b>' . h($d[$k]) . '</b></td></tr>';
    $wrap = fn($t, $b) => '<div style="font-family:Arial,sans-serif;color:#0d1b2e;max-width:620px"><div style="background:#0d1b2e;color:#fff;padding:14px 18px;border-bottom:3px solid #c9a24a"><b style="font-size:16px;letter-spacing:2px">' . h($brand['name']) . '</b><br><span style="color:#e2c46b;font-size:11px;letter-spacing:1px">' . h(strtoupper($brand['tagline'])) . '</span></div><div style="padding:16px 4px"><h3 style="margin:0 0 10px">' . h($t) . '</h3>' . $b . '</div></div>';
    if ($to) {
        [$st, $er] = send_mail($to, 'New audit request — ' . ($d['service'] ?: 'Website') . ' · ' . ($d['company'] ?: $d['name']), $wrap('New audit request #' . $id, '<table style="border-collapse:collapse;font-size:14px">' . $rows . '</table><p style="font-size:12px;color:#6b7280">Open /admin → Audit leads to update status' . ($d['file_name'] ? ' and download the file' : '') . '.</p>'));
        insert('email_log', ['job_id' => null, 'item_id' => null, 'recipient_type' => 'Website lead alert', 'email' => $to, 'subject' => 'Audit request #' . $id, 'status' => $st, 'error' => $er, 'sent_at' => now()]);
    }
    if (($bk['ack_on'] ?? '1') === '1' && $d['email']) {
        [$st, $er] = send_mail($d['email'], 'We received your ' . ($d['service'] ?: 'audit') . ' request — ' . $brand['name'], $wrap('Thank you, ' . $d['name'] . '.',
            '<p style="font-size:14px;line-height:1.6">We have received your request and ' . h(lcfirst(rtrim($bk['reply_note'] ?? 'We reply within 4 working hours.', '.'))) . '.</p><table style="border-collapse:collapse;font-size:14px">' . $rows . '</table><p style="font-size:14px">Questions? Call or WhatsApp ' . h($brand['phone']) . ' · ' . h($brand['email']) . '</p>'));
        insert('email_log', ['job_id' => null, 'item_id' => null, 'recipient_type' => 'Website lead acknowledgement', 'email' => $d['email'], 'subject' => 'Audit request #' . $id, 'status' => $st, 'error' => $er, 'sent_at' => now()]);
    }
}

/** Lead magnet (gated PDF): capture name / email, return a signed link */
api('POST', 'site/pub/download', null, function () {
    lead_rate_check('site_downloads');
    $m = one('SELECT * FROM site_magnets WHERE id=? AND active=1', [(int)in_('magnet_id')]);
    if (!$m) fail('This download is not available right now.', 404);
    $name = trim((string)in_('name')); $email = trim((string)in_('email'));
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Please enter your name and a valid work email.');
    $id = insert('site_downloads', ['magnet_id' => $m['id'], 'post_id' => (int)in_('post_id') ?: null, 'name' => mb_substr($name, 0, 120), 'company' => mb_substr(trim((string)in_('company')), 0, 190),
        'email' => mb_substr($email, 0, 190), 'ip' => client_ip(), 'created_at' => now()]);
    if (!$m['file_path']) return ['ok' => true, 'url' => null, 'message' => 'Thank you! We will email the chart to you shortly.'];
    $t = substr(hash_hmac('sha256', 'dl' . $id, site_key()), 0, 24);
    return ['ok' => true, 'url' => site_root() . '/api/site/pub/magnet?d=' . $id . '&t=' . $t];
});
api('GET', 'site/pub/magnet', null, function () {
    $id = (int)in_('d');
    if (!hash_equals(substr(hash_hmac('sha256', 'dl' . $id, site_key()), 0, 24), (string)in_('t'))) fail('Link expired', 403);
    $m = one('SELECT m.* FROM site_downloads d JOIN site_magnets m ON m.id=d.magnet_id WHERE d.id=?', [$id]);
    if (!$m || !$m['file_path'] || !is_file(UPLOAD_DIR . '/' . $m['file_path'])) fail('File not found', 404);
    stream_file(UPLOAD_DIR . '/' . $m['file_path'], $m['file_name'] ?: 'download.pdf', false);
});

/** Public certificate verification by report number (minimal details only). */
api('GET', 'site/pub/verify', null, function () {
    $no = trim((string)in_('no'));
    if (strlen($no) < 4) fail('Enter the certificate / report number printed on the report.');
    $it = one("SELECT i.report_no, i.rev, i.sku, i.product, i.insp_date, i.final_result, i.status, i.approved_at, j.qc_type FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id
        WHERE UPPER(i.report_no)=UPPER(?) AND i.status='APPROVED' ORDER BY i.rev DESC LIMIT 1", [$no]);
    if (!$it) return ['found' => false];
    return ['found' => true, 'report' => ['report_no' => $it['report_no'], 'rev' => (int)$it['rev'], 'sku' => $it['sku'], 'product' => $it['product'], 'qc_type' => $it['qc_type'],
        'date' => fdate($it['insp_date'] ?: $it['approved_at']), 'issued' => fdate($it['approved_at']), 'result' => $it['final_result'] ?: '—']];
});

/* ================= admin (/admin) ================= */

api('GET', 'site/admin/dash', SITE_ADMIN, function () {
    $mon = preg_match('/^\d{4}-\d{2}$/', (string)in_('month')) ? (string)in_('month') : date('Y-m');
    $from = "$mon-01 00:00:00"; $to = date('Y-m-d H:i:s', strtotime("$from +1 month"));
    $pf = date('Y-m-d H:i:s', strtotime("$from -1 month"));
    $cnt = fn($t, $a, $b, $extra = '') => (int)val("SELECT COUNT(*) FROM $t WHERE created_at>=? AND created_at<? $extra", [$a, $b]);
    $leads = $cnt('site_leads', $from, $to); $leadsP = $cnt('site_leads', $pf, $from);
    $dl = $cnt('site_downloads', $from, $to); $dlP = $cnt('site_downloads', $pf, $from);
    $won = $cnt('site_leads', $from, $to, "AND status='WON'");
    $chg = fn($a, $b) => $b > 0 ? (int)round(($a - $b) * 100 / $b) : null;
    return ['month' => $mon, 'leads' => $leads, 'leads_chg' => $chg($leads, $leadsP), 'downloads' => $dl, 'downloads_chg' => $chg($dl, $dlP),
        'published' => (int)val("SELECT COUNT(*) FROM site_posts WHERE status='PUBLISHED'"), 'drafts' => (int)val("SELECT COUNT(*) FROM site_posts WHERE status<>'PUBLISHED'"),
        'won' => $won, 'conversion' => $leads ? (int)round($won * 100 / $leads) : 0, 'new_leads' => (int)val("SELECT COUNT(*) FROM site_leads WHERE status='NEW'"),
        'recent' => all('SELECT * FROM site_leads ORDER BY id DESC LIMIT 6'),
        'top_posts' => all("SELECT id, title, slug, views FROM site_posts WHERE status='PUBLISHED' ORDER BY views DESC LIMIT 5"),
        'mail' => setting('mail_transport', 'none')];
});

/* ---- articles ---- */
api('GET', 'site/admin/posts', SITE_ADMIN, function () {
    return ['posts' => all('SELECT id, slug, title, category, status, featured, views, read_min, published_at, updated_at, cover FROM site_posts ORDER BY COALESCE(published_at, updated_at) DESC, id DESC'),
        'categories' => site_content()['blog']['categories'], 'magnets' => all('SELECT id, title FROM site_magnets ORDER BY id')];
});
api('GET', 'site/admin/post', SITE_ADMIN, function () {
    $p = one('SELECT * FROM site_posts WHERE id=?', [(int)in_('id')]); if (!$p) fail('Not found', 404);
    $p['cover_url'] = site_media_url($p['cover']);
    return ['post' => $p];
});
api('POST', 'site/admin/post/save', SITE_ADMIN, function () {
    $id = (int)in_('id'); $title = trim((string)in_('title'));
    if ($title === '') fail('Article title is required.');
    $body = clean_html((string)in_('body'));
    $slug = slugify((string)(in_('slug') ?: $title));
    $base = $slug; $n = 2; while (one('SELECT id FROM site_posts WHERE slug=? AND id<>?', [$slug, $id])) $slug = $base . '-' . $n++;
    $status = in_array(in_('status'), ['DRAFT', 'PUBLISHED'], true) ? in_('status') : 'DRAFT';
    $cur = $id ? one('SELECT * FROM site_posts WHERE id=?', [$id]) : null;
    if ($id && !$cur) fail('Not found', 404);
    $a = site_content()['about'];
    $d = ['slug' => $slug, 'title' => mb_substr($title, 0, 250), 'body' => $body, 'excerpt' => trim((string)in_('excerpt')) ?: excerpt_of(str_replace('{{magnet}}', '', $body)),
        'cover' => (string)in_('cover') ?: null, 'category' => (string)in_('category'), 'tags' => mb_substr(trim((string)in_('tags')), 0, 250),
        'author' => trim((string)in_('author')) ?: $a['author_short'], 'author_title' => trim((string)in_('author_title')) ?: $a['author_title'],
        'featured' => in_('featured') ? 1 : 0, 'magnet_id' => (int)in_('magnet_id') ?: null, 'meta_title' => mb_substr(trim((string)in_('meta_title')), 0, 250),
        'meta_desc' => trim((string)in_('meta_desc')), 'read_min' => read_minutes($body), 'status' => $status, 'updated_at' => now()];
    if ($status === 'PUBLISHED' && (!$cur || !$cur['published_at'])) $d['published_at'] = trim((string)in_('published_at')) ?: now();
    elseif (trim((string)in_('published_at'))) $d['published_at'] = (string)in_('published_at');
    if ($d['featured']) q('UPDATE site_posts SET featured=0 WHERE id<>?', [$id]);
    if ($id) update('site_posts', $d, 'id=?', [$id]); else { $d['created_at'] = now(); $id = insert('site_posts', $d); }
    return ['ok' => true, 'id' => $id, 'slug' => $slug, 'read_min' => $d['read_min'], 'message' => $status === 'PUBLISHED' ? 'Article published.' : 'Draft saved.'];
});
api('POST', 'site/admin/post/delete', ['super_admin'], function () { q('DELETE FROM site_posts WHERE id=?', [(int)in_('id')]); return ['ok' => true, 'message' => 'Article deleted.']; });

/** image / PDF upload for covers, article images, founder photo, logo, sample report */
api('POST', 'site/admin/upload', SITE_ADMIN, function () {
    $f = $_FILES['file'] ?? null; if (!$f || $f['error'] !== UPLOAD_ERR_OK) fail('Choose a file to upload.');
    if ($f['size'] > 15 * 1024 * 1024) fail('File must be under 15 MB.');
    $s = save_upload($f, 'site/' . date('Y-m'), in_('pdf') ? ['pdf'] : ['jpg', 'jpeg', 'png', 'webp', 'gif']);
    if (!$s) fail(in_('pdf') ? 'Only PDF files are allowed here.' : 'Only JPG, PNG, WEBP or GIF images are allowed.');
    return ['ok' => true, 'path' => $s['path'], 'name' => $s['name'], 'url' => site_media_url($s['path'])];
});

/* ---- leads ---- */
function leads_where(): array {
    $w = ['1=1']; $p = [];
    if (isset(LEAD_STATUS[(string)in_('status')])) { $w[] = 'status=?'; $p[] = in_('status'); }
    if ($s = trim((string)in_('q'))) { $w[] = '(company LIKE ? OR name LIKE ? OR email LIKE ? OR phone LIKE ?)'; array_push($p, "%$s%", "%$s%", "%$s%", "%$s%"); }
    if (preg_match('/^\d{4}-\d{2}$/', (string)in_('month'))) { $w[] = 'created_at>=? AND created_at<?'; $f = in_('month') . '-01 00:00:00'; array_push($p, $f, date('Y-m-d H:i:s', strtotime("$f +1 month"))); }
    return [implode(' AND ', $w), $p];
}
api('GET', 'site/admin/leads', SITE_ADMIN, function () {
    [$w, $p] = leads_where();
    $rows = all("SELECT * FROM site_leads WHERE $w ORDER BY id DESC LIMIT 5000", $p);
    foreach ($rows as &$r) $r['file_url'] = $r['file_path'] ? site_root() . '/api/file?p=' . rawurlencode($r['file_path']) . '&dl=1' : null;
    $counts = []; foreach (all('SELECT status, COUNT(*) n FROM site_leads GROUP BY status') as $c) $counts[$c['status']] = (int)$c['n'];
    return ['leads' => $rows, 'counts' => $counts, 'statuses' => LEAD_STATUS];
});
api('POST', 'site/admin/lead/update', SITE_ADMIN, function () {
    $id = (int)in_('id'); if (!one('SELECT id FROM site_leads WHERE id=?', [$id])) fail('Not found', 404);
    $d = ['updated_at' => now()];
    if (isset(LEAD_STATUS[(string)in_('status')])) $d['status'] = in_('status');
    if (in_('notes') !== null) $d['notes'] = (string)in_('notes');
    update('site_leads', $d, 'id=?', [$id]);
    return ['ok' => true, 'message' => 'Lead updated.'];
});
api('POST', 'site/admin/lead/delete', ['super_admin'], function () { q('DELETE FROM site_leads WHERE id=?', [(int)in_('id')]); return ['ok' => true, 'message' => 'Lead deleted.']; });
function csv_out(string $name, array $head, array $rows): never {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="' . $name . '"');
    $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF"); fputcsv($o, $head);
    foreach ($rows as $r) fputcsv($o, array_values($r));
    fclose($o); exit;
}
api('GET', 'site/admin/leads.csv', SITE_ADMIN, function () {
    [$w, $p] = leads_where();
    csv_out('audit-leads-' . date('Ymd') . '.csv', ['ID', 'Received', 'Name', 'Company', 'Email', 'Phone', 'Service', 'Product', 'Batch', 'Hub', 'Preferred date', 'Status', 'Message', 'Notes', 'File'],
        all("SELECT id, created_at, name, company, email, phone, service, categories, batch_qty, hub, pref_date, status, message, notes, file_name FROM site_leads WHERE $w ORDER BY id DESC", $p));
});

/* ---- downloads & lead magnets ---- */
api('GET', 'site/admin/downloads', SITE_ADMIN, function () {
    return ['rows' => all('SELECT d.*, m.title magnet, p.title post FROM site_downloads d LEFT JOIN site_magnets m ON m.id=d.magnet_id LEFT JOIN site_posts p ON p.id=d.post_id ORDER BY d.id DESC LIMIT 5000')];
});
api('GET', 'site/admin/downloads.csv', SITE_ADMIN, function () {
    csv_out('pdf-downloads-' . date('Ymd') . '.csv', ['ID', 'Date', 'Name', 'Company', 'Email', 'Lead magnet', 'From article'],
        all('SELECT d.id, d.created_at, d.name, d.company, d.email, m.title, p.title pt FROM site_downloads d LEFT JOIN site_magnets m ON m.id=d.magnet_id LEFT JOIN site_posts p ON p.id=d.post_id ORDER BY d.id DESC'));
});
api('GET', 'site/admin/magnets', SITE_ADMIN, function () {
    $rows = all('SELECT m.*, (SELECT COUNT(*) FROM site_downloads d WHERE d.magnet_id=m.id) downloads, (SELECT COUNT(*) FROM site_posts p WHERE p.magnet_id=m.id) posts FROM site_magnets m ORDER BY m.id');
    foreach ($rows as &$r) $r['file_url'] = $r['file_path'] ? site_root() . '/api/site/admin/magnet/file?id=' . $r['id'] : null;
    return ['rows' => $rows];
});
api('POST', 'site/admin/magnet/save', SITE_ADMIN, function () {
    $id = (int)in_('id'); $title = trim((string)in_('title')); if ($title === '') fail('Title is required.');
    $d = ['title' => mb_substr($title, 0, 250), 'description' => trim((string)in_('description')), 'active' => in_('active') ? 1 : 0];
    if (!empty($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $s = save_upload($_FILES['file'], 'magnets', ['pdf', 'xlsx', 'docx', 'zip']); if (!$s) fail('Upload a PDF (or XLSX / DOCX / ZIP).');
        $d['file_path'] = $s['path']; $d['file_name'] = $s['name'];
    }
    if ($id) update('site_magnets', $d, 'id=?', [$id]); else { $d['created_at'] = now(); $id = insert('site_magnets', $d); }
    return ['ok' => true, 'id' => $id, 'message' => 'Lead magnet saved.'];
});
api('POST', 'site/admin/magnet/delete', ['super_admin'], function () {
    $id = (int)in_('id'); q('UPDATE site_posts SET magnet_id=NULL WHERE magnet_id=?', [$id]); q('DELETE FROM site_magnets WHERE id=?', [$id]);
    return ['ok' => true, 'message' => 'Lead magnet deleted.'];
});
// magnet files live outside site/ (not public) — admin preview via the normal /api/file endpoint
api('GET', 'site/admin/magnet/file', SITE_ADMIN, function () {
    $m = one('SELECT * FROM site_magnets WHERE id=?', [(int)in_('id')]);
    if (!$m || !$m['file_path'] || !is_file(UPLOAD_DIR . '/' . $m['file_path'])) fail('No file uploaded', 404);
    stream_file(UPLOAD_DIR . '/' . $m['file_path'], $m['file_name'] ?: 'file.pdf');
});

/* ---- page content & settings ---- */
api('GET', 'site/admin/content', SITE_ADMIN, function () {
    $saved = []; foreach (all('SELECT k, updated_at FROM site_content') as $r) $saved[$r['k']] = $r['updated_at'];
    return ['content' => site_content(), 'defaults' => site_defaults(), 'saved' => $saved];
});
api('POST', 'site/admin/content/save', SITE_ADMIN, function () {
    $k = (string)in_('key'); $v = in_('value');
    if (!isset(site_defaults()[$k]) || !is_array($v)) fail('Unknown section');
    $j = json_encode($v, JSON_UNESCAPED_UNICODE);
    if (one('SELECT k FROM site_content WHERE k=?', [$k])) q('UPDATE site_content SET v=?, updated_at=? WHERE k=?', [$j, now(), $k]);
    else insert('site_content', ['k' => $k, 'v' => $j, 'updated_at' => now()]);
    return ['ok' => true, 'message' => 'Saved — the website shows it now.'];
});
api('POST', 'site/admin/content/reset', ['super_admin'], function () {
    q('DELETE FROM site_content WHERE k=?', [(string)in_('key')]);
    return ['ok' => true, 'message' => 'Section reset to the original design text.'];
});
api('GET', 'site/admin/settings', SITE_ADMIN, function () {
    return ['turnstile_site' => setting('turnstile_site', ''), 'turnstile_secret_set' => setting('turnstile_secret', '') !== '', 'mail_transport' => setting('mail_transport', 'none'),
        'mail_from' => setting('mail_from', '')];
});
api('POST', 'site/admin/settings', ['super_admin'], function () {
    set_setting('turnstile_site', trim((string)in_('turnstile_site')));
    if (in_('turnstile_secret') !== null && in_('turnstile_secret') !== '') set_setting('turnstile_secret', trim((string)in_('turnstile_secret')));
    if (in_('clear_turnstile')) { set_setting('turnstile_site', ''); set_setting('turnstile_secret', ''); }
    return ['ok' => true, 'message' => 'Settings saved.'];
});
