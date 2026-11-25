import { useEffect, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { ArrowRight, Globe, Lock, Mail, MapPin, Menu, Phone, X } from 'lucide-react';
import { useSite, telLink } from './site';
import { GNAV, TOPBAR } from './gx-content';

export const ERP_LOGIN = (import.meta.env.BASE_URL || '/') + 'erp/login';
const IMG = (import.meta.env.BASE_URL || '/') + 'img/';

/** The client's own logo (Jewelsetu · Quality Services) */
export function BrandLogo({ light }) {
  return <span className={'gx-logo' + (light ? ' light' : '')}><img src={IMG + 'logo-t.png'} alt="Jewelsetu Quality Services" width="1100" height="266" /></span>;
}

function NavItem({ to, label, dot, onClick }) {
  const loc = useLocation();
  const on = to === '/' ? loc.pathname === '/' && !loc.hash : to.startsWith('/#') ? loc.pathname === '/' && loc.hash === to.slice(1) : false;
  return <Link to={to} className={on ? 'on' : ''} onClick={onClick}>{dot && <i className="gx-dot" />}{label}</Link>;
}

export function GxHeader() {
  const { C, openBook } = useSite(); const b = C.brand;
  const [open, setOpen] = useState(false);
  const [scrolled, setScrolled] = useState(false);
  const loc = useLocation();
  useEffect(() => setOpen(false), [loc.pathname, loc.hash]);
  useEffect(() => { document.body.style.overflow = open ? 'hidden' : ''; }, [open]);
  useEffect(() => { const f = () => setScrolled(window.scrollY > 10); f(); window.addEventListener('scroll', f, { passive: true }); return () => window.removeEventListener('scroll', f); }, []);
  return <>
    <div className="gx-top">
      <div className="gx-wrap">
        <span className="l"><b>{TOPBAR.left}</b><span className="sep" /><MapPin />{TOPBAR.cities}</span>
        <span className="r"><a href={'mailto:' + b.email}><Mail />{b.email}</a><a href={telLink(b.phone)}><Phone />{b.phone}</a></span>
      </div>
    </div>
    <header className={'gx-hdr' + (scrolled ? ' sc' : '')}>
      <div className="gx-wrap">
        <Link to="/" aria-label="Jewelsetu home"><BrandLogo /></Link>
        <nav className="gx-nav">{GNAV.map(([to, l, dot]) => <NavItem key={to} to={to} label={l} dot={dot} />)}</nav>
        <div className="gx-hcta">
          <a className="gx-btn gx-out sm" href={ERP_LOGIN}><Lock />Q-Track Login</a>
          <button className="gx-btn gx-gold sm" onClick={() => openBook()}>Book an Audit<ArrowRight /></button>
        </div>
        <button className="gx-burger" aria-label="Menu" onClick={() => setOpen(true)}><Menu /></button>
      </div>
    </header>
    {open && <div className="gx-mmenu">
      <div className="top"><BrandLogo /><button aria-label="Close" onClick={() => setOpen(false)}><X size={26} /></button></div>
      <nav>{GNAV.map(([to, l, dot]) => <NavItem key={to} to={to} label={l} dot={dot} onClick={() => setOpen(false)} />)}</nav>
      <div className="acts">
        <button className="gx-btn gx-gold" onClick={() => { setOpen(false); openBook(); }}>Book an Audit<ArrowRight /></button>
        <a className="gx-btn gx-out" href={ERP_LOGIN}><Lock />Q-Track Login</a>
      </div>
      <div className="ct"><a href={telLink(b.phone)}><Phone />{b.phone}</a><a href={'mailto:' + b.email}><Mail />{b.email}</a></div>
    </div>}
  </>;
}

export function GxFooter() {
  const { C, setReport } = useSite(); const b = C.brand;
  return <footer className="gx-ftr jx-pattern-dark">
    <div className="gx-wrap">
      <div className="cols">
        <div className="about"><BrandLogo light />
          <p>Independent Third-Party Jewelry Inspections, Factory Compliance Audits, Lean QMS Consulting, and Digital Q-Track Traceability across India's major jewelry hubs.</p></div>
        <div><h4>Inspection &amp; Audit Services</h4><ul>
          <li><Link to="/#services">Pre-Shipment Inspection (PSI)</Link></li><li><Link to="/#services">During Production Inspection (DUPRO)</Link></li>
          <li><Link to="/#services">100% Piece-by-Piece Inspection</Link></li><li><Link to="/#services">Factory &amp; ISO/RJC Compliance Audits</Link></li>
          <li><Link to="/#services">Lean Six Sigma QMS Retainers</Link></li></ul></div>
        <div><h4>Client Resources</h4><ul>
          <li><a href={ERP_LOGIN}>Jewelsetu Q-Track Portal Login</a></li><li><Link to="/#manuals">Quality Manuals &amp; PDF Downloads</Link></li>
          <li><button onClick={() => setReport(true)}>Sample Pre-Shipment QC Report</button></li><li><Link to="/#aql">Jewelry AQL Sample Size Calculator</Link></li>
          <li><Link to="/blog">Technical Quality Knowledge Hub</Link></li></ul></div>
        <div><h4>Headquarters &amp; Contact</h4><ul className="ct">
          <li><MapPin /><span>Jaipur, Rajasthan, India (Serving Jaipur, Mumbai, Surat &amp; Delhi NCR)</span></li>
          <li><Phone /><a href={telLink(b.phone)}>{b.phone}</a></li>
          <li><Mail /><a href={'mailto:' + b.email}>{b.email}</a></li>
          <li><Globe /><a href="/">www.jewelsetuqms.in</a></li></ul></div>
      </div>
      <div className="bottom">
        <span>© {new Date().getFullYear()} Jewelsetu Quality Services. All Rights Reserved.</span>
        <span>Corporate B2B Edition • Powered by Jewelsetu Q-TRACK</span>
        <span className="credit">Designed &amp; Developed by <a href={b.credit_url || 'https://customesolution.com'} target="_blank" rel="noopener">{b.credit_text || 'CustomE Solution'}</a></span>
      </div>
    </div>
  </footer>;
}
