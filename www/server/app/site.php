<?php
/* ---------- public website (CMS) helpers: content, posts, sanitising, media ---------- */

const SITE_ADMIN = ['super_admin', 'back_office'];
const LEAD_STATUS = ['NEW' => 'New', 'QUOTED' => 'Quoted', 'WON' => 'Won', 'LOST' => 'Lost'];

function site_defaults(): array { static $d = null; return $d ??= require __DIR__ . '/site_defaults.php'; }

/** All page content: saved sections override the defaults (keys missing in a saved section fall back to the default). */
function site_content(): array {
    static $c = null; if ($c !== null) return $c;
    $c = site_defaults();
    try { foreach (all('SELECT k, v FROM site_content') as $r) { $v = jdec($r['v'], null); if (is_array($v) && isset($c[$r['k']])) $c[$r['k']] = array_replace($c[$r['k']], $v); } }
    catch (Throwable $e) {}
    return $c;
}
function site_media_url(?string $p): string {
    if (!$p) return '';
    if (preg_match('#^(https?:)?//#', $p) || str_starts_with($p, '/')) return $p;
    return site_root() . '/api/site/pub/media?p=' . rawurlencode($p);
}
/** path prefix where the site is installed ('' at domain root) */
function site_root(): string {
    $s = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $d = rtrim(dirname($s), '/');
    if (str_ends_with($d, '/api')) $d = substr($d, 0, -4);
    return $d === '.' ? '' : $d;
}
function site_origin(): string {
    global $CFG; if (!empty($CFG['base_url'])) return preg_replace('#^(https?://[^/]+).*$#', '$1', $CFG['base_url']);
    $https = ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    return ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost');
}
function site_abs(string $u): string { return $u === '' ? '' : (preg_match('#^https?://#', $u) ? $u : site_origin() . $u); }

function slugify(string $s): string {
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s), '-'));
    return substr($s ?: 'article', 0, 120);
}
function read_minutes(string $html): int { return max(1, (int)ceil(str_word_count(strip_tags($html)) / 200)); }
function excerpt_of(string $html, int $len = 180): string {
    $t = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', $html)), ENT_QUOTES, 'UTF-8')));
    return mb_strlen($t) > $len ? rtrim(mb_substr($t, 0, $len - 1)) . '…' : $t;
}

/** Whitelist sanitiser for article HTML written in the admin editor. */
function clean_html(string $html): string {
    if (trim($html) === '') return '';
    $ok = ['p' => [], 'br' => [], 'h2' => ['id'], 'h3' => ['id'], 'h4' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [], 'mark' => [],
        'ul' => [], 'ol' => [], 'li' => [], 'blockquote' => [], 'pre' => [], 'code' => [], 'hr' => [], 'span' => [], 'div' => [], 'figure' => [], 'figcaption' => [],
        'a' => ['href', 'target', 'rel'], 'img' => ['src', 'alt', 'width', 'height'], 'table' => [], 'thead' => [], 'tbody' => [], 'tr' => ['class'], 'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'], 'sup' => [], 'sub' => []];
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="__r">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $root = $doc->getElementById('__r'); if (!$root) return '';
    $walk = function (DOMNode $n) use (&$walk, $ok, $doc) {
        for ($i = $n->childNodes->length - 1; $i >= 0; $i--) {
            $c = $n->childNodes->item($i);
            if ($c instanceof DOMComment) { $n->removeChild($c); continue; }
            if (!$c instanceof DOMElement) continue;
            $tag = strtolower($c->tagName);
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'svg', 'math', 'link', 'meta'], true)) { $n->removeChild($c); continue; }
            $walk($c);
            if (!isset($ok[$tag])) { while ($c->firstChild) $n->insertBefore($c->firstChild, $c); $n->removeChild($c); continue; }
            foreach (iterator_to_array($c->attributes) as $a) {
                $an = strtolower($a->name); $v = trim($a->value);
                if (!in_array($an, $ok[$tag], true)) { $c->removeAttribute($a->name); continue; }
                if (in_array($an, ['href', 'src'], true) && !preg_match('#^(https?:|mailto:|tel:|/|\#|api/)#i', $v)) $c->removeAttribute($a->name);
                if ($an === 'class' && $v !== 'hl') $c->removeAttribute($a->name);
            }
            if ($tag === 'a' && $c->getAttribute('target') === '_blank') $c->setAttribute('rel', 'noopener');
        }
    };
    $walk($root);
    $out = ''; foreach ($root->childNodes as $c) $out .= $doc->saveHTML($c);
    return $out;
}

function post_card(array $p): array {
    return ['id' => (int)$p['id'], 'slug' => $p['slug'], 'title' => $p['title'], 'excerpt' => $p['excerpt'] ?: '', 'cover' => site_media_url($p['cover'] ?? ''),
        'category' => $p['category'] ?: '', 'tags' => array_values(array_filter(array_map('trim', explode(',', (string)$p['tags'])))), 'author' => $p['author'] ?: '',
        'read_min' => (int)$p['read_min'], 'featured' => (int)$p['featured'] === 1, 'published_at' => $p['published_at'], 'status' => $p['status']];
}
function site_post_by_slug(string $slug): ?array {
    try { return one("SELECT * FROM site_posts WHERE slug=? AND status='PUBLISHED'", [$slug]); } catch (Throwable $e) { return null; }
}
function site_key(): string {
    $k = setting('site_key', ''); if ($k === '') { $k = bin2hex(random_bytes(16)); set_setting('site_key', $k); }
    return $k;
}
function client_ip(): string {
    $f = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''; if ($f) return trim(explode(',', $f)[0]);
    return $_SERVER['REMOTE_ADDR'] ?? '';
}
function stream_file(string $full, string $name, bool $inline = true): never {
    $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
    $mime = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif', 'svg' => 'image/svg+xml',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'][$ext] ?? 'application/octet-stream';
    header('Content-Type: ' . $mime); header('Content-Length: ' . filesize($full)); header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace('"', '', $name) . '"');
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) header('Cache-Control: public, max-age=2592000');
    readfile($full); exit;
}

/** Starter Knowledge Hub articles (from the website design) so the hub is not empty on day one; all editable / deletable in /admin. */
function site_seed(): void {
    if ((int)val('SELECT COUNT(*) FROM site_posts') > 0) return;
    $a = site_defaults()['about'];
    $mag = (int)val('SELECT COUNT(*) FROM site_magnets') ? (int)val('SELECT id FROM site_magnets ORDER BY id LIMIT 1')
        : insert('site_magnets', ['title' => 'Jewelry AQL Sampling & Defect Tolerance Chart', 'description' => 'The one-page reference used by our field auditors.', 'active' => 1, 'created_at' => now()]);
    $posts = [
        ['read-ansi-asq-z14-table-jewellery-lot', 'How to read an ANSI/ASQ Z1.4 table for a 1,200-piece jewellery lot', 'AQL & Sampling', 'AQL,ISO9001', 0, '2026-11-14 10:00:00',
         '<p>Most buyer disputes on Indian jewellery shipments begin with a sampling plan nobody fully understood. Here is the exact way our field auditors pick the sample size and accept/reject limits for a typical lot.</p>
<h2>Step 1 — Find the sample code letter</h2><p>Start with the lot size and the inspection level. For routine pre-shipment work we use <strong>General Inspection Level II</strong>. A lot of 1,200 pieces falls in the 501–1,200 band, which gives code letter <strong>J</strong>.</p>
<table><thead><tr><th>Lot size</th><th>Level II code</th><th>Sample size</th><th>Ac / Re (AQL 1.0)</th></tr></thead><tbody><tr><td>281 – 500</td><td>H</td><td>50</td><td>1 / 2</td></tr><tr class="hl"><td>501 – 1,200 ← your lot</td><td>J</td><td>80</td><td>2 / 3</td></tr><tr><td>1,201 – 3,200</td><td>K</td><td>125</td><td>3 / 4</td></tr></tbody></table>
{{magnet}}
<h2>Step 2 — Choose AQL per defect class</h2><p>Jewellery buyers usually set separate limits per defect class: <strong>critical 0</strong> (sharp edges, wrong metal), <strong>major 1.0</strong> (loose stone, lifted prong, purity below stamp) and <strong>minor 2.5</strong> (fine scratches, light tarnish on findings). Agree these in the PO before the inspection, not on the factory floor.</p>
<h2>Step 3 — Accept / reject decision</h2><p>Pick 80 pieces at random from all cartons. Count majors and minors separately. With code J, AQL 1.0 majors accept at 2 and reject at 3; AQL 2.5 minors accept at 5 and reject at 6. The lot passes only if <em>both</em> classes are within their accept numbers.</p>
<h2>Common mistakes in jewellery lots</h2><ul><li>Sampling only from the top carton — always draw across the whole lot.</li><li>Counting one piece with two defects as two defective pieces without agreeing the rule first.</li><li>Using Level I to save time on a high-value diamond order.</li><li>Re-inspecting a failed lot with the same sample instead of a fresh draw after rework.</li></ul>'],
        ['porosity-gold-casting-root-causes', 'Porosity in gold casting: 7 root causes and the stage-gate fix', 'Casting & Manufacturing', 'CAPA', 0, '2026-10-24 10:00:00',
         '<p>Porosity is the single biggest cause of rejected castings in Indian gold units. In this guide we break down the seven root causes we see most often on factory audits — and the stage-gate check that catches each one.</p>
<h2>1. Incorrect flask temperature</h2><ul><li>Flask too cold → shrinkage porosity near the sprue</li><li>Flask too hot → gas porosity and rough surface</li></ul>
<h2>2. Poor sprue design</h2><p>Sprues that are too thin freeze before the piece is fully fed. Feed the heaviest section and keep sprue length short.</p>
<h2>3. Contaminated or over-recycled metal</h2><p>Limit re-used metal to 50% and clean it. Oxides and investment residue turn into gas porosity.</p>
<h2>4. Investment not burnt out completely</h2><p>Follow the full burnout cycle; residual wax carbon creates gas pockets.</p>
<h2>5. Casting temperature too high</h2><p>Overheated metal absorbs more gas. Cast at the alloy maker’s recommended range plus a small margin only.</p>
<h2>6. Vacuum or pressure failure</h2><p>Log vacuum reading for every flask — a weak seal is an invisible cause.</p>
<h2>7. Tree layout and weight</h2><p>Overloaded trees cool unevenly. Keep pieces of similar weight on one tree.</p>
<h2>The stage-gate fix</h2><p>At <strong>Gate 04 · Casting</strong>, check porosity, shrinkage, metal fill and sprue marks against a physical boundary sample before release to filing. Rejecting here costs grams; rejecting after setting costs stones and labour.</p>
{{magnet}}'],
        ['lab-grown-vs-natural-intake-qc', 'Lab-grown vs natural: what your QC team must check at intake', 'Diamond & Gemstone Grading', 'LabGrown', 0, '2026-10-10 10:00:00',
         '<p>Lab-grown diamonds are now in most Indian factories, often on the same bench as natural stones. Mixing is the risk your buyer will not forgive. This is the intake routine we train QC teams on.</p>
<h2>Separate before you grade</h2><p>Receive natural and lab-grown parcels in different areas, with different packet colours and separate registers.</p>
<h2>Screen every parcel</h2><p>Use a screening device for every melee parcel and send any referrals to a lab. Never rely on supplier paperwork alone.</p>
<h2>Check the disclosure on the job card</h2><p>The job card, the stamp and the invoice must all say the same thing — “lab-grown” or “natural”.</p>
<h2>Record and retain</h2><p>Log screening results against the lot number so the final inspection report can show the evidence.</p>
{{magnet}}'],
        ['rjc-code-of-practices-30-day-gap-closure', 'RJC Code of Practices: a 30-day gap-closure plan for Indian exporters', 'Export Compliance', 'ISO9001,CAPA', 1, '2026-11-18 10:00:00',
         '<p>What auditors actually check, the documents you need and the common reasons exporters fail — organised as a practical 30-day plan.</p>
<h2>Week 1 — Gap analysis</h2><p>Map your current policies against the Code of Practices provisions that apply to your scope: business ethics, human rights, labour, health & safety, environment and product disclosure.</p>
<h2>Week 2 — Policies and records</h2><p>Write the missing policies, appoint owners and start the records auditors ask for: training logs, grievance register, supplier due-diligence files.</p>
<h2>Week 3 — Shop-floor controls</h2><p>Fix the visible items: PPE, fire exits, chemical storage, working hours and wage records.</p>
<h2>Week 4 — Internal audit and CAPA</h2><p>Run an internal audit with the same checklist the certification auditor will use, then close every finding with a dated CAPA.</p>
<h2>Why exporters fail</h2><ul><li>Policies exist on paper but staff cannot explain them.</li><li>No evidence of supplier due diligence for gold and diamonds.</li><li>Overtime records that do not match payroll.</li></ul>
{{magnet}}'],
    ];
    foreach ($posts as [$slug, $title, $cat, $tags, $feat, $at, $body]) {
        $first = preg_match('#<p>(.*?)</p>#s', $body, $fm) ? $fm[1] : $body;
        insert('site_posts', ['slug' => $slug, 'title' => $title, 'excerpt' => excerpt_of($first, 200), 'body' => $body, 'category' => $cat, 'tags' => $tags,
            'author' => $a['author_short'], 'author_title' => $a['author_title'], 'status' => 'PUBLISHED', 'featured' => $feat, 'magnet_id' => $mag,
            'meta_title' => '', 'meta_desc' => excerpt_of($first, 158), 'read_min' => max(5, read_minutes($body)),
            'published_at' => $at, 'created_at' => now(), 'updated_at' => now()]);
    }
}
