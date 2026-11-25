import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  Award, BadgeCheck, Box, CheckCircle2, ChevronDown, ClipboardList, Cog, Download, ExternalLink, Eye, Factory, FileText, Gem, LogIn, MapPin,
  Network, Presentation, ArrowDown, ArrowUp, Zap, WandSparkles, Microscope, TrendingUp, Quote, Search, ShieldCheck, Sparkle, Star, UserCheck, Clock,
} from 'lucide-react';
import { get } from '../api';
import { useSite, waLink } from '../site';
import { WaIcon } from '../components';
import { CountUp } from '../motion';
import { ERP_LOGIN } from '../gx';
import {
  HERO, CERTS, CASES, TESTIMONIALS, SERVICES, METHOD, PILLARS, QSTEPS, PORTALS, DOC_TABS, DOCS, POST_TABS, FALLBACK_POSTS, FOUNDER, FAQS, LOTS, AQLS, aqlPlan, ADV, STATS,
} from '../gx-content';

const ICO = { gem: Gem, award: Award, usercheck: UserCheck, badgecheck: BadgeCheck, shield: ShieldCheck, package: Box, cogs: Cog, search: Search, factory: Factory, network: Network, teach: Presentation, zap: Zap, wand: WandSparkles, micro: Microscope, trend: TrendingUp };
const I = ({ n, ...p }) => { const C = ICO[n] || Sparkle; return <C {...p} />; };
const pad = (i) => String(i + 1).padStart(2, '0');

/* ---------- hero orbit (animated) ---------- */
function Orbit() {
  return <div className="gx-orbit" aria-hidden="true">
    <div className="ring r1 spin-cw"><i className="dot gold" style={{ top: -6, left: '50%' }} /><i className="dot green" style={{ top: '50%', left: -6 }} /><i className="dot dark" style={{ bottom: -6, left: '50%' }} /></div>
    <div className="ring r2 spin-ccw"><i className="dot gold sm" style={{ top: '50%', right: -5 }} /></div>
    <div className="core">
      <svg className="dia float" viewBox="0 0 64 56"><defs><linearGradient id="gxg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stopColor="#F2DE97" /><stop offset=".55" stopColor="#D4B34F" /><stop offset="1" stopColor="#A67F1A" /></linearGradient></defs>
        <path d="M2 18 14 3h36l12 15-30 35z" fill="url(#gxg)" /><path d="M2 18h60M14 3l8 15 10-15 10 15 8-15M22 18l10 35 10-35" fill="none" stroke="#7a5c12" strokeOpacity=".45" strokeWidth="1" /></svg>
      <b>Jewelsetu</b><small>Zero-Defect Standard</small>
    </div>
    <span className="ochip c1"><i className="g" />Real-Time Compliance</span>
    <span className="ochip c2 dark"><Award />Six Sigma &amp; GIA</span>
    <span className="ochip c3">99.9% Purity Assay</span>
  </div>;
}

/* ---------- AQL calculator ---------- */
function AqlCalc() {
  const [lot, setLot] = useState(8); const [aql, setAql] = useState(2);
  const r = aqlPlan(lot, AQLS[aql][2]);
  return <div className="gx-card gx-aql">
    <div className="hd"><div><span className="gx-eb">Interactive Quality Tool</span><h3>Jewelry AQL Sample Size Calculator</h3></div><span className="tagdark">ANSI/ASQ Z1.4</span></div>
    <div className="sel">
      <label><span>1. Select Shipment Lot Size (Pieces)</span><select value={lot} onChange={(e) => setLot(+e.target.value)}>{LOTS.map(([l], i) => <option key={l} value={i}>{l} Pieces</option>)}</select></label>
      <label><span>2. Select Major Defect AQL Limit</span><select value={aql} onChange={(e) => setAql(+e.target.value)}>{AQLS.map(([, l], i) => <option key={l} value={i}>{l}</option>)}</select></label>
    </div>
    <div className="res">
      <div><small>Code Letter</small><b className="gold">{r.letter}</b></div>
      <div><small>Sample Size</small><b>{r.n} Pcs</b></div>
      <div><small>Accept Lot (Ac)</small><b className="ok">≤{r.ac}</b></div>
      <div><small>Reject Lot (Re)</small><b className="bad">≥{r.re}</b></div>
    </div>
    <p className="note">*Based on General Inspection Level II. Critical defects (wrong karatage, sharp edges, missing hallmark) always carry AQL 0.0 (Zero Acceptance).</p>
  </div>;
}

export default function Home() {
  const { C, openBook, setReport } = useSite();
  const [docTab, setDocTab] = useState(0);
  const [postTab, setPostTab] = useState(0); const [q, setQ] = useState('');
  const [posts, setPosts] = useState(null);
  const [faq, setFaq] = useState(() => FAQS.map(() => true));
  useEffect(() => { get('site/pub/posts', { limit: 30 }).then((r) => setPosts(r.posts || [])).catch(() => setPosts([])); }, []);

  const list = useMemo(() => {
    const src = posts && posts.length ? posts : FALLBACK_POSTS;
    const key = ['', 'aql|sampl', 'cast|defect|manuf', 'export|compl'][postTab];
    return src.filter((p) => (!key || new RegExp(key, 'i').test(p.category || ''))
      && (!q || (p.title + ' ' + (p.excerpt || '')).toLowerCase().includes(q.toLowerCase()))).slice(0, 3);
  }, [posts, postTab, q]);
  const docs = DOCS.filter((d) => docTab === 0 || d.cat === DOC_TABS[docTab]);
  const pdfAsk = (d) => { if (d.sample) return setReport(true); window.open(waLink(C, 'Hi Jewelsetu, please share the PDF: ' + d.title), '_blank', 'noopener'); };

  return <div className="gx">
    {/* HERO */}
    <section className="gx-hero jx-pattern" id="home">
      <div className="gx-wrap grid">
        <div className="txt">
          <span className="gx-pill" data-reveal><i />{HERO.pill}</span>
          <h1 data-reveal>{HERO.t1}<em>{HERO.t2}</em>{HERO.t3}</h1>
          <p className="lead" data-reveal>On-site quality control, <b>ANSI/ASQ Z1.4 AQL sampling</b>, GIA-standard gemstone &amp; diamond verification, and real-time digital reporting via <b>Q-TRACK</b> before your shipment leaves the factory.</p>
          <div className="ctas" data-reveal>
            <button className="gx-btn gx-gold lg" onClick={() => openBook()}><ClipboardList />Request Inspection Quote</button>
            <button className="gx-btn gx-out lg" onClick={() => setReport(true)}><FileText />View Sample QC Report</button>
          </div>
          <div className="checks" data-reveal>{HERO.checks.map((c) => <span key={c}><CheckCircle2 />{c}</span>)}</div>
        </div>
        <Orbit />
      </div>
    </section>

    {/* STATS BAND */}
    <section className="gx-stats">
      <div className="gx-wrap">{STATS.map(([dir, v, suf, l]) => <div key={l} data-reveal>
        <b>{dir === 'down' ? <ArrowDown /> : dir === 'up' ? <ArrowUp /> : null}<CountUp value={v} />{suf}</b><small>{l}</small></div>)}</div>
    </section>

    {/* 4 CORE ADVANTAGE PILLARS */}
    <section className="gx-sec white" id="advantages">
      <div className="gx-wrap">
        <div className="gx-head center" data-reveal><span className="gx-eb">Transforming Quality into Competitive Advantage</span><h2>The 4 Core Advantage Pillars of Jewelsetu</h2>
          <p>Engineered to make third-party inspection and factory quality management seamless for global buyers and manufacturers.</p></div>
        <div className="gx-adv">{ADV.map(([ic, t, d], i) => <div key={t} className={'gx-card adv' + (i % 2 ? ' hl' : '')} data-reveal>
          <span className="ic"><I n={ic} /></span><small>Pillar {pad(i)}</small><h3>{t}</h3><p>{d}</p></div>)}</div>
      </div>
    </section>

    {/* CERTIFICATIONS */}
    <section className="gx-sec gx-certsec">
      <div className="gx-wrap"><div className="gx-card gx-certs" data-reveal>
        <span className="gx-eb">Certified Global Standards &amp; Accreditations</span>
        <div className="row">{CERTS.map(([ic, t]) => <div key={t}><I n={ic} />{t}</div>)}</div>
      </div></div>
    </section>

    {/* TRACK RECORD */}
    <section className="gx-sec" id="impact">
      <div className="gx-wrap">
        <div className="gx-head split" data-reveal><div><span className="gx-eb">NDA-Compliant Track Record</span><h2>Client Credentials &amp; Verified Quality Impact</h2></div>
          <span className="nda"><ShieldCheck />Client identities protected under strict B2B NDAs</span></div>
        <div className="gx-cases">{CASES.map((c, i) => <div key={i} className="gx-card case" data-reveal style={{ '--d': i }}>
          <div className="top"><span className={'tag ' + c.tone}>{c.tag}</span><small>{c.loc}</small></div>
          <h3>{c.title}</h3><p className="sub">{c.sub}</p>
          <p>{c.body.map((t, j) => (j === 1 ? <b key={j}>{t}</b> : t))}</p>
          <div className="ft">{c.foot}</div>
        </div>)}</div>
        <div className="gx-tests">{TESTIMONIALS.map((t, i) => <figure key={i} className="test" data-reveal style={{ '--d': i }}>
          <div className="top"><Quote className="qm" /><span className="stars">{[0, 1, 2, 3, 4].map((s) => <Star key={s} />)}</span></div>
          <blockquote>“{t.q}”</blockquote>
          <figcaption><b>{t.who}</b><small>{t.org}</small></figcaption>
        </figure>)}</div>
      </div>
    </section>

    {/* SERVICES */}
    <section className="gx-sec white" id="services">
      <div className="gx-wrap">
        <div className="gx-head" data-reveal><span className="gx-eb">Comprehensive Quality Solutions</span><h2>Third-Party Jewelry Inspections, Audits &amp; QMS Consulting</h2>
          <p>Transparent, stage-specific quality interventions designed for international buying houses, export manufacturers, and retail jewelry brands.</p></div>
        <div className="gx-svcs">{SERVICES.map((s, i) => <div key={i} className="gx-card svc" data-reveal style={{ '--d': i % 3 }}>
          <div className="top"><span className="ic"><I n={s.icon} /></span><span className={'badge' + (s.gold ? ' gold' : '')}>{s.badge}</span></div>
          <h3>{s.title}</h3>
          <div className="when"><small>When you need it:</small>{s.when}</div>
          <small className="lbl">{s.l2}:</small><p>{s.check}</p>
          <small className="lbl">Deliverables:</small><p>{s.deliv}</p>
          <div className="ft">{s.footLink ? <button className="ul" onClick={() => setReport(true)}>{s.foot}</button> : <span>{s.foot}</span>}
            <button className="gx-btn gx-dark xs" onClick={() => openBook({ service: s.svc })}>{s.cta} →</button></div>
        </div>)}</div>
      </div>
    </section>

    {/* METHOD */}
    <section className="gx-sec">
      <div className="gx-wrap">
        <div className="gx-head center" data-reveal><span className="gx-eb">Our Signature Methodology</span><h2>The Path to Perfection</h2>
          <p>Solving the trust deficit in global jewelry sourcing through a structured 4-step verification lifecycle.</p></div>
        <div className="gx-steps">{METHOD.map(([t, d], i) => <div key={t} className="gx-card kstep" data-reveal style={{ '--d': i }}><span className="n">{pad(i)}</span><h3>{t}</h3><p>{d}</p></div>)}</div>
        <div className="gx-card gx-phil" data-reveal>
          <span className="gx-eb">The Setu Philosophy</span><h2>The Foundation of Jewelry Quality Control</h2>
          <p className="intro">Six non-negotiable governance pillars that protect every client, shipment, and heritage brand we serve.</p>
          <div className="grid">{PILLARS.map(([t, d], i) => <div key={t} className="pl" data-reveal style={{ '--d': i % 3 }}><h4><Sparkle />{t}</h4><p>{d}</p></div>)}</div>
        </div>
      </div>
    </section>

    {/* Q-TRACK */}
    <section className="gx-q jx-pattern-dark" id="qtrack">
      <div className="gx-wrap">
        <div className="hd" data-reveal>
          <div><span className="gx-pill dark"><i />Proprietary Digital Quality Platform</span>
            <h2>Beyond Static PDFs: Live Traceability with <em>Jewelsetu Q‑TRACK</em></h2>
            <p>While traditional inspection agencies send static emails days later, Jewelsetu gives international buyers and factories real-time visibility into every PO, SKU, and inspection stage.</p></div>
          <a className="gx-btn gx-gold" href={ERP_LOGIN}><LogIn />Launch Q-Track Portal</a>
        </div>
        <div className="qsteps">{QSTEPS.map(([t, d], i) => <div key={t} className="qs" data-reveal style={{ '--d': i }}><span className="n">{pad(i)}</span><h3>{t}</h3><p>{d}</p></div>)}</div>
        <div className="portals">{PORTALS.map(([k, t]) => <a key={t} href={ERP_LOGIN} data-reveal><div><small>{k}</small><b>{t}</b></div><ExternalLink /></a>)}</div>
      </div>
    </section>

    {/* AQL + SAMPLE */}
    <section className="gx-sec" id="aql">
      <div className="gx-wrap gx-two">
        <div data-reveal><AqlCalc /></div>
        <div className="sample" data-reveal>
          <span className="gx-eb">See Our Documentation Standard</span>
          <h2>Inspect Before You Book: Review a Real Sample QC Report</h2>
          <p>Unsure what an independent Jewelsetu inspection covers? Review our standardized Pre-Shipment Inspection Certificate featuring XRF assay readings, 10x loupe prong checks, weight tolerances, and defect severity logs.</p>
          <button className="gx-btn gx-dark" onClick={() => setReport(true)}><FileText />Open Sample QC Certificate</button>
        </div>
      </div>
    </section>

    {/* MANUALS */}
    <section className="gx-sec white" id="manuals">
      <div className="gx-wrap">
        <div className="gx-head" data-reveal><span className="gx-eb">Standard Operating Procedures &amp; Reference Library</span><h2>Quality Manuals &amp; PDF Downloads Center</h2>
          <p>Preview and download Jewelsetu corporate quality manuals, ANSI/ASQ Z1.4 AQL reference charts, floor-to-floor QC checklists, and sample inspection reports.</p></div>
        <div className="gx-tabs" data-reveal>{DOC_TABS.map((t, i) => <button key={t} className={i === docTab ? 'on' : ''} onClick={() => setDocTab(i)}>{t}</button>)}</div>
        <div className="gx-docs">{docs.map((d) => <div key={d.title} className="gx-card doc" data-reveal>
          <div className="top"><span className="pdf"><FileText /><small>PDF</small></span><div><span className="chipc">{d.cat}</span><small>{d.meta}</small></div></div>
          <h3>{d.title}</h3><p>{d.text}</p>
          <div className="ft"><button className="gx-btn gx-white xs" onClick={() => pdfAsk(d)}><Eye />View PDF</button>
            <button className="gx-btn gx-gold xs" onClick={() => pdfAsk(d)}><Download />Download PDF</button></div>
        </div>)}</div>
      </div>
    </section>

    {/* KNOWLEDGE HUB */}
    <section className="gx-sec white bt" id="insights">
      <div className="gx-wrap">
        <div className="gx-head" data-reveal><span className="gx-eb">Quality Intelligence &amp; Technical Guides</span><h2>Jewelsetu Knowledge Hub</h2>
          <p>In-depth articles on AQL sampling, casting defect root-cause analysis, and export compliance for jewelry buyers and manufacturers.</p></div>
        <div className="gx-tabs between" data-reveal>
          <div>{POST_TABS.map((t, i) => <button key={t} className={i === postTab ? 'on' : ''} onClick={() => setPostTab(i)}>{t}</button>)}</div>
          <label className="srch"><Search /><input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search articles..." /></label>
        </div>
        <div className="gx-posts">{list.map((p, i) => <Link key={p.slug || i} to={p.slug ? '/blog/' + p.slug : '/blog'} className="gx-card kpost" data-reveal style={{ '--d': i % 3 }}>
          <div className="top"><span className="chipc">{p.category}</span><small><Clock />{p.read_min || 5} Min Read</small></div>
          <h3>{p.title}</h3><p>{p.excerpt}</p>
          <div className="ft"><small>By {p.author || 'Sanket Shrivastava'}</small><b>Read Guide →</b></div>
        </Link>)}
          {posts && !list.length && <p className="gx-empty">No articles match your search.</p>}</div>
      </div>
    </section>

    {/* ABOUT */}
    <section className="gx-sec" id="about">
      <div className="gx-wrap gx-about">
        <div className="fcard jx-pattern-dark" data-reveal>
          <div className="who"><span className="av">SS</span><div><small>{FOUNDER.role}</small><b>{FOUNDER.name}</b><span><MapPin />{FOUNDER.place}</span></div></div>
          <blockquote>“{FOUNDER.quote}”</blockquote>
          <ul>{FOUNDER.timeline.map(([y, t, s, hi]) => <li key={y}><span className={'y' + (hi ? ' hi' : '')}>{y}</span><div><b>{t}</b><small>{s}</small></div></li>)}</ul>
        </div>
        <div className="txt" data-reveal>
          <span className="gx-eb">Leadership &amp; Global Pedigree</span>
          <h2>20+ Years of International Jewelry QA &amp; Vendor Governance</h2>
          <p>With over two decades heading quality operations for leading UK, German, and Indian omnichannel jewelry retailers, <b>Sanket Shrivastava</b> established <b>Jewelsetu</b> in Jaipur in June 2025. Our mission is to provide international buying houses and export manufacturers with transparent, conflict-free quality verification and Lean Six Sigma process excellence.</p>
          <div className="creds">{FOUNDER.creds.map(([ic, t, s]) => <div key={t} className="gx-card cr"><span className="ic"><I n={ic} /></span><div><b>{t}</b><small>{s}</small></div></div>)}</div>
          <div className="ctas"><button className="gx-btn gx-gold" onClick={() => openBook()}>Schedule a Consultation Call</button>
            <a className="gx-btn gx-wa" href={waLink(C)} target="_blank" rel="noopener"><WaIcon />Direct WhatsApp</a></div>
        </div>
      </div>
    </section>

    {/* FAQ */}
    <section className="gx-sec white" id="faqs">
      <div className="gx-wrap narrow">
        <div className="gx-head center" data-reveal><span className="gx-eb">Buyer &amp; Manufacturer Guidance</span><h2>Frequently Asked Questions (FAQs)</h2>
          <p>Direct answers on third-party jewellery inspections, AQL sampling, defect reduction, and compliance audits in Jaipur, India.</p></div>
        <div className="gx-faqs">{FAQS.map(([qq, a], i) => <div key={i} className={'gx-faq' + (faq[i] ? ' open' : '')} data-reveal>
          <button onClick={() => setFaq(faq.map((v, j) => (j === i ? !v : v)))} aria-expanded={faq[i]}><h3>{i + 1}. {qq}</h3><span><ChevronDown /></span></button>
          <div className="a"><p>{a}</p></div>
        </div>)}</div>
      </div>
    </section>
  </div>;
}
