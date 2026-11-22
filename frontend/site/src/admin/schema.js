// Editable website sections → fields. Types: text, area, image, pdf, tags, list(of), icon, toggle, number, select
const t = (k, label, help) => ({ k, type: 'text', label, help });
const a = (k, label, help) => ({ k, type: 'area', label, help });
const G = 'Wrap words in *asterisks* to show them in gold italic.';
const ICO = { k: 'icon', type: 'icon', label: 'Icon' };

export const SECTIONS = [
  { key: 'brand', label: 'Brand & contact', page: '/', fields: [
    t('name', 'Brand name'), t('tagline', 'Tagline under logo'),
    { k: 'logo', type: 'image', label: 'Logo image', help: 'Optional — leave empty to use the gold diamond + brand name.' },
    t('phone', 'Phone (shown on site)'), t('whatsapp', 'WhatsApp number', 'Digits with country code, e.g. 918005933787'), a('whatsapp_text', 'WhatsApp pre-filled message'),
    t('email', 'Main email'), t('email2', 'Second email'), t('city', 'City / address line'),
    { k: 'cities', type: 'tags', label: 'Cities served', help: 'Shown in hero, footer and CTA cards.' },
    a('footer_about', 'Footer description'), t('linkedin', 'Company LinkedIn URL') ] },
  { key: 'seo', label: 'SEO & sharing', page: '/', fields: [
    t('title', 'Home page title (Google)', 'Around 50–60 characters.'), a('description', 'Default meta description', 'Around 150–160 characters.'),
    { k: 'og_image', type: 'image', label: 'Default share image', help: '1200 × 630 px — used on LinkedIn / WhatsApp when a page has no image.' },
    t('ga_id', 'Google Analytics ID', 'e.g. G-XXXXXXX — leave empty to disable.') ] },
  { key: 'home', label: 'Home · hero', page: '/', fields: [
    t('eyebrow', 'Small heading above title'), a('title', 'Hero title', G), a('subtitle', 'Hero text'), t('cta_primary', 'Gold button text'), t('cta_secondary', 'Second button text (opens sample report)'),
    { k: 'badges', type: 'list', label: 'Floating badges', max: 4, of: [t('label', 'Label'), t('value', 'Value')] },
    { k: 'stats', type: 'list', label: 'Stats bar', max: 4, of: [t('value', 'Number / text'), t('suffix', 'Suffix (+, %)'), t('label', 'Label')] } ] },
  { key: 'pillars', label: 'Home · four pillars', page: '/#services', fields: [
    t('eyebrow', 'Small heading'), t('title', 'Title', G), a('intro', 'Intro text'),
    { k: 'items', type: 'list', label: 'Service cards', of: [ICO, t('title', 'Title'), a('text', 'Text'), { k: 'points', type: 'tags', label: 'Check points' },
      { k: 'anchor', type: 'select', label: 'Explore link → Services section', options: ['tpq', 'audits', 'qms', 'training'] }] } ] },
  { key: 'trace', label: 'Home · Q-Track traceability', page: '/', fields: [
    t('eyebrow', 'Small heading'), t('title', 'Title', G),
    { k: 'steps', type: 'list', label: 'Steps', of: [ICO, t('title', 'Step'), t('text', 'Text')] },
    t('lot_code', 'Live lot label'), t('lot_product', 'Lot product'), t('lot_sample', 'Sample size'), t('lot_majmin', 'Major / Minor'), t('lot_status', 'Status chip'),
    { k: 'lot_progress', type: 'number', label: 'Progress bar %' } ] },
  { key: 'audiences', label: 'Home · who we serve', page: '/', fields: [{ k: 'items', type: 'list', label: 'Audience cards', of: [ICO, t('title', 'Title'), a('text', 'Text')] }] },
  { key: 'cta', label: 'Gold CTA banners', page: '/', fields: [t('title', 'Home banner title'), t('text', 'Home banner text'), t('services_title', 'Services / About banner title'), t('services_text', 'Services / About banner text')] },
  { key: 'services', label: 'Quality Services page', page: '/services', fields: [
    t('title', 'Page title', G),
    t('tpq_title', 'Service 01 title', G), a('tpq_text', 'Service 01 text'),
    { k: 'tpq_cards', type: 'list', label: 'Inspection types', of: [t('code', 'Code'), ICO, t('title', 'Title'), a('text', 'Text')] },
    { k: 'deliverables', type: 'list', label: '“What you receive” list', of: [ICO, t('title', 'Title'), t('text', 'Text')] },
    t('audit_title', 'Service 02 title', G), { k: 'audit_points', type: 'tags', label: 'Audit check points' },
    t('scorecard_grade', 'Scorecard grade chip'), { k: 'scorecard', type: 'list', label: 'Vendor scorecard rows', of: [t('label', 'Area'), t('weight', 'Weight'), { k: 'score', type: 'number', label: 'Score (0–100)' }] },
    t('qms_eyebrow', 'Service 03 small heading'), t('qms_title', 'Service 03 title', G), a('qms_text', 'Service 03 text'),
    { k: 'gates', type: 'list', label: 'Stage gates', of: [ICO, t('name', 'Gate name'), a('note', 'Checkpoint note')] },
    { k: 'gate_active', type: 'number', label: 'Gate highlighted first (0 = first gate)' },
    t('training_title', 'Service 04 title', G), a('training_text', 'Service 04 text'),
    { k: 'training_cards', type: 'list', label: 'Training cards', of: [ICO, t('title', 'Title'), t('text', 'Text')] } ] },
  { key: 'qtrack', label: 'Q-Track Portal page', page: '/q-track', fields: [
    t('badge', 'Badge'), t('title', 'Title', G), t('subtitle', 'Subtitle'),
    { k: 'roles', type: 'list', label: 'Role cards (buttons open Q-Track login)', of: [ICO, t('title', 'Role'), t('sub', 'Sub-title'), { k: 'points', type: 'tags', label: 'Points' }, t('button', 'Button text')] },
    t('footnote', 'Security note') ] },
  { key: 'about', label: 'About Founder page', page: '/about', fields: [
    t('eyebrow', 'Small heading'), t('name', 'Name', G), a('bio', 'Bio'), { k: 'photo', type: 'image', label: 'Portrait photo', help: 'Portrait (approx. 4:5). Also used as article author photo.' },
    t('badge', 'Gold badge on photo'), t('linkedin', 'LinkedIn profile URL'),
    { k: 'timeline', type: 'list', label: 'Career pedigree', of: [t('period', 'Period'), t('role', 'Role · Company'), { k: 'current', type: 'toggle', label: 'Current' }] },
    { k: 'certs', type: 'list', label: 'Certifications', of: [ICO, t('title', 'Certification'), t('issuer', 'Issuer')] },
    t('author_short', 'Default article author name'), t('author_title', 'Default author title') ] },
  { key: 'report', label: 'Sample QC certificate', page: '/?preview=qc-report', fields: [
    t('cert_no', 'Certificate no.'), t('standard', 'Standard line'), t('title', 'Certificate title'),
    t('lot_size', 'Lot size'), t('sample_code', 'Sample code'), t('aql', 'AQL (Maj / Min)'), t('accept_reject', 'Accept / Reject'),
    t('product', 'Product'), t('style_po', 'Style / PO'), t('site', 'Inspection site'), t('date', 'Date line'),
    { k: 'rows', type: 'list', label: 'Checkpoints', of: [t('checkpoint', 'Checkpoint'), t('method', 'Method'), t('finding', 'Finding'), { k: 'result', type: 'select', label: 'Result', options: ['PASS', 'FAIL'] }] },
    t('photos_more', 'Extra photos label'), t('signatory', 'Signatory'), t('signatory_title', 'Signatory title'),
    { k: 'status', type: 'select', label: 'Lot status stamp', options: ['PASS', 'FAIL'] },
    { k: 'sample_pdf', type: 'pdf', label: 'Sample report PDF (download button)', help: 'Optional — without it the button prints the certificate.' } ] },
  { key: 'booking', label: 'Book-an-Audit form', page: '/book', fields: [
    t('notify_email', 'Lead alert email', 'New requests are emailed here.'), { k: 'ack_on', type: 'toggle', label: 'Send acknowledgement email to the client' },
    t('reply_note', 'Reply-time promise'),
    { k: 'services', type: 'list', label: 'Step 1 · services', of: [t('key', 'Service name'), ICO, t('sub', 'Sub-text')] },
    { k: 'categories', type: 'tags', label: 'Product categories' }, { k: 'quantities', type: 'tags', label: 'Batch quantity buckets' },
    { k: 'staff', type: 'tags', label: 'Team size options (training)' }, { k: 'hubs', type: 'tags', label: 'Manufacturing hubs' } ] },
  { key: 'blog', label: 'Knowledge Hub', page: '/blog', fields: [
    t('eyebrow', 'Small heading'), t('title', 'Hub page title', G), t('home_title', 'Home section title', G),
    { k: 'categories', type: 'tags', label: 'Article categories' }, { k: 'topics', type: 'tags', label: 'Popular topics (hashtags)' } ] },
];
