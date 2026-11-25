/* Home page content — replicated from the approved Gemini Canvas design (Nov 2026). */
export const TOPBAR = { left: 'Independent Third-Party Jewelry QA • India', cities: 'Jaipur • Mumbai (SEEPZ) • Surat • Delhi NCR' };

export const GNAV = [
  ['/', 'Home'], ['/#advantages', 'Why Jewelsetu'], ['/#impact', 'Client Impact'], ['/#services', 'Services'], ['/#manuals', 'Manuals & PDFs'],
  ['/#qtrack', 'Q-Track Portal', true], ['/#faqs', 'FAQs'], ['/#about', 'About'],
];

export const HERO = {
  pill: 'Independent Third-Party Jewelry QA in India (Jaipur | Mumbai | Delhi | Surat)',
  t1: 'Protect Your Brand Reputation with ', t2: 'Independent Jewelry Inspections', t3: ' & Factory Audits.',
  checks: ['Same-Day Digital Reports', '100% Unbiased & Conflict-Free', 'GPS-Verified Floor Auditors'],
};

export const CERTS = [
  ['gem', 'GIA Diamond Graduate'], ['award', 'Lean Six Sigma Black & Green Belt (Otifas)'],
  ['usercheck', 'GJSCI Certified Assessor'], ['badgecheck', 'Total Quality Management (TQM) & AQL Z1.4'],
];

export const CASES = [
  { tag: '>40% → 5% Defects', tone: 'green', loc: 'Jaipur, India', title: 'Leading Export Manufacturing Unit', sub: '6-Month Floor-to-Floor QMS & Process Engineering Retainer',
    body: ['Implemented Lean Six Sigma casting & wax-tree stage gates, cutting casting porosity and surface rework defects from ', '>40% down to 5%', ' within Phase 1.'], foot: 'Phase 1 Verified Outcome' },
  { tag: 'SOP Overhaul', tone: 'amber', loc: 'Jaipur / Global', title: 'Global TV & Omnichannel Jewelry Retailer', sub: 'End-to-End QMS & Job Master Card Standardization',
    body: ['Engineered standardized porosity acceptance criteria, visual limit samples, and stage-wise checkpoints—eliminating post-production repair loops.'], foot: 'Zero-Loop Production Flow' },
  { tag: '100% Transparency', tone: 'blue', loc: 'HK • Dongguan • Jaipur', title: 'European Luxury Jewelry Sourcing Office', sub: 'Independent Third-Party Inspections (TPQ) & Vendor Audits',
    body: ['Delivered unbiased, macro-photo-logged ANSI/ASQ Z1.4 pre-shipment inspection reports and vendor risk scorecards prior to overseas export dispatch.'], foot: 'Cross-Border Buyer Protection' },
  { tag: 'Team Calibration', tone: 'purple', loc: 'Pan-India', title: 'Multi-Unit Fine Jewelry Manufacturer', sub: 'QC Inspector Calibration & Acceptance Alignment',
    body: ['Aligned floor QC inspectors, bench supervisors, and COO leadership on uniform 10x loupe grading and zero-defect acceptance thresholds across plants.'], foot: 'Unified Multi-Plant Standard' },
];

export const TESTIMONIALS = [
  { q: 'Implementing structured Job Master Cards and stage-wise QC checkpoints with Jewelsetu transformed our floor accountability. Rework rates dropped dramatically within the first two months.', who: 'Chief Operating Officer (COO)', org: 'Fine Jewelry Export House (Jaipur)' },
  { q: 'Having an independent, GIA-trained quality partner on the ground in Jaipur gives our international merchandising team complete peace of mind before releasing final vendor payments.', who: 'Head of Global Sourcing', org: 'European Jewelry Brand' },
  { q: 'Their Lean Six Sigma approach to casting and setting defect analysis helped us cut rejection rates from over 40% to under 5% without slowing down delivery timelines.', who: 'Managing Director', org: 'Export Manufacturing Unit' },
];

export const SERVICES = [
  { icon: 'package', badge: 'Most Popular', gold: true, title: 'Pre-Shipment Inspection (PSI)', when: 'When 80–100% of your order is finished and packed, before releasing final balance payment to your vendor.',
    l2: 'What we check', check: 'ANSI/ASQ Z1.4 AQL sampling, metal purity (XRF/hallmark), stone setting security, plating thickness, clasp tension, weight & barcode accuracy.',
    deliv: 'Same-day watermarked digital report with macro defect photos & Pass/Fail sign-off on Q-TRACK.', foot: 'Sample Report', footLink: true, cta: 'Book PSI', svc: 'TPQ Inspection' },
  { icon: 'cogs', badge: 'In-Line Control', title: 'During Production Inspection (DUPRO)', when: 'When 20–40% of pieces are cast or set, catching systemic defects early before the entire batch is ruined.',
    l2: 'What we check', check: 'Casting porosity, wax/mould shrinkage, calibrated gemstone shade matching, prong seat depth, and production schedule adherence.',
    deliv: 'In-line corrective action log (CAPA) and stage-wise rework tracking to prevent shipment delays.', foot: 'Stops Rework Early', cta: 'Book DUPRO', svc: 'TPQ Inspection' },
  { icon: 'search', badge: 'High-Value Lots', title: '100% Piece-by-Piece Inspection', when: 'For high-value diamond/emerald lines, bridal collections, or vendors with prior quality issues requiring zero tolerance.',
    l2: 'What we check', check: 'Every single SKU inspected under 10x triplet loupe & D65 daylight, individual weight logging, and tamper-evident parcel sealing.',
    deliv: 'Accepted vs. Rejected piece segregation, SKU-level defect tagging, and sealed carton verification.', foot: 'Zero-Defect Assurance', cta: 'Request 100% QC', svc: 'TPQ Inspection' },
  { icon: 'factory', badge: 'Vendor Onboarding', title: 'Factory & Supplier Audits', when: 'Before onboarding a new Indian jewelry manufacturer or verifying ISO 9001 / RJC compliance readiness.',
    l2: 'What we check', check: 'Actual production capacity, internal QC lab tools, gold/diamond vault security, subcontracting risks, and ethical workplace compliance.',
    deliv: 'Weighted Vendor Risk Scorecard (0–100%), facility photo documentation, and 60-day CAPA improvement plan.', foot: 'ISO 9001 • RJC Ready', cta: 'Book Factory Audit', svc: 'Factory Audit' },
  { icon: 'network', badge: 'For Factories', title: 'QMS Retainer & Defect Reduction', when: 'When your factory suffers from high casting/polishing rework, overseas buyer rejections, or gold loss inefficiencies.',
    l2: 'What we implement', check: 'Lean Six Sigma stage gates (Model → Mould → Wax → Casting → Filing → Setting → Polish) and weekly Pareto root-cause analysis.',
    deliv: 'Custom bilingual factory SOPs, visual limit samples (Pass/Fail boards), and up to 85% drop in rework rates.', foot: 'Lean Six Sigma Black Belt', cta: 'Consult Principal', svc: 'QMS Retainer' },
  { icon: 'teach', badge: 'Bilingual Modules', title: 'Technical QC Training & Assessment', when: 'To align your internal QC team, bench supervisors, and karigars with international export quality benchmarks.',
    l2: 'What we teach', check: '10x loupe diamond & colored stone grading, practical AQL table execution, defect classification, and first-time-right mindset.',
    deliv: 'On-site Hindi-English workshops, hands-on loupe calibration tests, and GJSCI-aligned competency scorecards.', foot: 'On-Site & Virtual', cta: 'Request Syllabus', svc: 'Staff Training' },
];

export const METHOD = [
  ['Consultation', 'Analyzing your current QC gaps, PO specifications, AQL tolerances, and buyer compliance requirements.'],
  ['Audit / Test', 'Executing deep-dive factory audits, XRF purity checks, or 100% / AQL item inspections on the shop floor.'],
  ['Report', 'Delivering comprehensive, tamper-proof digital certification with macro defect evidence via Q-TRACK.'],
  ['Certified', 'Eliminating rejects before shipment and securing the bridge of trust with your international customers.'],
];

export const PILLARS = [
  ['System Excellence & Compliance', 'Dedicated to helping you achieve and maintain global compliance through continuous Lean Six Sigma improvement.'],
  ['Unwavering Integrity & Ethics', 'Conducting all business with fairness, honesty, and transparency. Our loyalty remains exclusively with the client.'],
  ['Professional Accountability', 'Upholding all laws, hallmarking regulations, and ethical codes governing the jewelry profession with absolute responsibility.'],
  ['Innovation for Competitive Edge', 'Embracing digital Q-TRACK traceability and modern testing adaptation to ensure our clients retain a sharp global edge.'],
  ['Empowerment & Cultural Spirit', 'Fostering personal accountability and shop-floor empowerment to sustain the highest standards of professional conduct.'],
  ['Independence & Objectivity', 'Providing unbiased, third-party perspectives to ensure data integrity and transparent reporting for every inspection.'],
];

export const QSTEPS = [
  ['Client Raises Request', 'Upload PO specs, multi-design SKU lists, and target AQL levels directly on the Q-Track Client Dashboard.'],
  ['GPS Floor Check-In', 'Jewelsetu auditors check in on-site with geo-verification, scanning SKU barcodes and logging stage defects live.'],
  ['Automated Defect Pareto', 'System auto-calculates Critical, Major, and Minor counts against ANSI/ASQ Z1.4 acceptance thresholds.'],
  ['Instant PO Certificate', 'Download tamper-proof PDF certificates with macro photo evidence and approve shipments immediately.'],
];
export const PORTALS = [['For Overseas Buyers & Brands', 'Client Portal Login'], ['For Export Manufacturers', 'Vendor CAPA Dashboard'], ['For Quality Engineers', 'Field Inspector Portal']];

export const DOC_TABS = ['All Documents', 'Quality Manuals', 'AQL & Standards', 'QC Checklists', 'Sample Reports'];
export const DOCS = [
  { cat: 'Quality Manuals', meta: 'Rev 2.0 • 14 Pages • 1.4 MB PDF', title: 'Jewelsetu Corporate Quality Manual & TPQ Protocol (v2.0)',
    text: 'Master governance manual outlining Jewelsetu third-party inspection ethics, conflict-free auditing rules, XRF assay calibration, and Q-TRACK reporting standards.' },
  { cat: 'AQL & Standards', meta: 'Standard Edition • 4 Pages • 820 KB PDF', title: 'ANSI/ASQ Z1.4 Jewelry AQL Sampling & Defect Classification Chart',
    text: 'Quick-reference factory & buyer desk chart mapping lot sizes (91 to 10,000+ pcs) to sample codes and Critical (0.0), Major (1.5), and Minor (4.0) defect limits.' },
  { cat: 'QC Checklists', meta: 'SOP v1.8 • 8 Pages • 1.1 MB PDF', title: 'Floor-to-Floor Jewelry Manufacturing QC Checklist (Casting to Setting)',
    text: 'Stage-gate quality verification checklist covering Master Model, Rubber Mould, Wax Tree, Raw Casting, Pre-Polish Filing, Stone Setting, and Final Rhodium Finish.' },
  { cat: 'Sample Reports', meta: '2026 Template • 6 Pages • 1.6 MB PDF', title: 'Sample Pre-Shipment Inspection (PSI) Certificate & Defect Report', sample: true,
    text: 'Annotated example of an official Jewelsetu Q-TRACK Pre-Shipment Inspection Certificate with XRF purity logs, AQL counts, and macro defect evidence.' },
];

export const POST_TABS = ['All Articles', 'AQL & Sampling', 'Casting & Defects', 'Export Compliance'];
export const FALLBACK_POSTS = [
  { slug: '', category: 'AQL & Sampling', read_min: 5, title: 'How International Buying Houses Set AQL Standards for Fine Gold & Diamond Jewelry', excerpt: 'A practical breakdown of Critical (0.0), Major (1.5), and Minor (4.0) defect classifications under ANSI/ASQ Z1.4 to prevent overseas lot rejections.' },
  { slug: '', category: 'Casting & Defects', read_min: 6, title: 'Eliminating Casting Porosity & Prong Breakage Before the Polishing Bench', excerpt: 'Why 70% of surface pitting originates during wax injection and burnout—and how Lean Six Sigma stage gates eliminate costly gold rework.' },
  { slug: '', category: 'Export Compliance', read_min: 4, title: 'Why Overseas Brands Need Independent Third-Party QC in Jaipur & Mumbai', excerpt: 'How unbiased floor verification and GPS-verified Q-TRACK reporting protect global buyers from stone substitutions and delayed shipments.' },
];

export const FOUNDER = {
  name: 'Sanket Shrivastava', role: 'Founder & Principal Consultant', place: 'Jaipur, Rajasthan, India',
  quote: 'True quality assurance in fine jewelry is never about catching defects at the packing table—it is about engineering prevention into every step from CAD and casting to final stone setting.',
  timeline: [
    ['June 2025–Present', 'Founder & Principal Consultant — Jewelsetu', 'Independent Third-Party Audits, QMS Consulting & Q-Track', true],
    ['2020–2025', 'Former Head of India QC — Juwelo TV (Germany)', 'Led export quality assurance & vendor audits across India'],
    ['Prior', 'QA Manager at Melorra & 13 Years at GemsTV UK (Gemporia)', 'High-volume retail & global television jewelry QA leadership'],
  ],
  creds: [
    ['award', 'Lean Six Sigma Black & Green Belt', 'Otifas Institute • Root-Cause Defect Elimination'],
    ['gem', 'GIA Diamond Graduate', 'Gemological Institute of America Grading Standards'],
    ['usercheck', 'GJSCI Certified Assessor', 'Gem & Jewellery Skill Council of India'],
    ['shield', 'Total Quality Management (TQM)', 'ISO 9001 & Export Compliance Frameworks'],
  ],
};

export const FAQS = [
  ['Who is the best third-party jewellery quality inspection agency in Jaipur, India?', 'Jewelsetu (Jewelsetu Quality Management Services), founded by Sanket Shrivastava, is a leading independent third-party jewellery quality inspection and TQM consultancy in Jaipur. With over 20 years of international QA leadership experience (including GemsTV UK, Juwelo TV Germany, and Melorra), Jewelsetu provides ISO 2859 AQL sampling, 100% pre-shipment inspections, and supplier audits for domestic and global jewellery brands.'],
  ['What stages of jewellery manufacturing does Jewelsetu inspect?', 'Jewelsetu conducts comprehensive floor-to-floor quality checks across every production stage—including CAD/wax review, casting porosity evaluation, filing, stone sorting and treatment detection, micro-pavé and prong diamond setting, polishing, micron plating, and final assembly—ensuring zero-defect shipments.'],
  ['How does Jewelsetu help jewellery manufacturers reduce casting defects and repair rates?', 'Led by Lean Six Sigma Black Belt and GIA Diamond Graduate Sanket Shrivastava, Jewelsetu implements customized Standard Operating Procedures (SOPs), Job Master Cards, porosity acceptance benchmarks, and Root Cause Analysis (RCA/CAPA). Our structured QMS retainers have successfully reduced factory casting defect rates from over 40% to below 5%.'],
  ['How do international buyers track inspections remotely in Jaipur?', 'International brands and buying houses get access to Jewelsetu Q-TRACK, our proprietary digital QC portal. Clients can view live inspector check-in/check-out logs, review defective SKU photos via hold-for-approval workflows, and download standardized single- or multi-design inspection reports in real time.'],
  ['Does Jewelsetu assist with BIS, RJC, and international compliance audits?', 'Yes. Jewelsetu prepares jewellery factories and exporters for BIS Hallmarking standards, Responsible Jewellery Council (RJC) frameworks, and international buyer technical audits across North America, Europe, the UK, and India.'],
];

/* ANSI/ASQ Z1.4 — General Inspection Level II, single sampling, normal inspection */
export const LOTS = [['2 to 8', 'A'], ['9 to 15', 'B'], ['16 to 25', 'C'], ['26 to 50', 'D'], ['51 to 90', 'E'], ['91 to 150', 'F'], ['151 to 280', 'G'],
  ['281 to 500', 'H'], ['501 to 1,200', 'J'], ['1,201 to 3,200', 'K'], ['3,201 to 10,000', 'L'], ['10,001 to 35,000', 'M']];
export const AQLS = [['0.65', 'AQL 0.65 (High-Value Diamond Lines)', 5], ['1.0', 'AQL 1.0 (Fine Gold & Bridal)', 4], ['1.5', 'AQL 1.5 (Standard Export Jewelry)', 3],
  ['2.5', 'AQL 2.5 (Fashion & Silver Jewelry)', 2], ['4.0', 'AQL 4.0 (Minor Defects)', 1]];
const N = [2, 3, 5, 8, 13, 20, 32, 50, 80, 125, 200, 315];
const AC = [1, 2, 3, 5, 7, 10, 14, 21];
/** returns { letter, n, ac, re } — arrows in the Z1.4 table resolved to the plan they point to */
export function aqlPlan(lotIdx, zero) {
  const letter = LOTS[lotIdx][1];
  let i = lotIdx;
  if (i <= zero || i === zero + 1) return { letter, n: N[zero], ac: 0, re: 1 };
  if (i === zero + 2) i = zero + 3;
  const ac = AC[Math.min(i - zero - 3, AC.length - 1)];
  return { letter, n: N[i], ac, re: ac + 1 };
}

export const ADV = [
  ['zap', 'Efficient', 'Optimizing production workflows and real-time communication to ensure faster shipment delivery without compromising excellence.'],
  ['wand', 'Painless', 'A hassle-free digital quality support system via Q-TRACK designed to remove complexity and friction from your daily operations.'],
  ['micro', 'Thorough', 'Deep-dive 10x loupe inspections, XRF assays, and data-backed root cause analysis to eliminate defects at every stage of manufacturing.'],
  ['trend', 'Cost Effective', 'Protecting your margins by reducing rework, gold dust loss, and overseas recalls through early-stage quality intervention and Lean practices.'],
];

export const STATS = [['down', '85', '%', 'Defect Rate Gap Reduction'], ['up', '12', '×', 'Digital Audit Efficiency'], ['', '99.9', '%', 'XRF & Hallmark Purity Assurance'], ['', '500', '+', 'Compliance & Lot Audits']];
