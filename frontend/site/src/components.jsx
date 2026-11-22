import { useEffect, useState } from 'react';
import { Link, NavLink, useLocation } from 'react-router-dom';
import { ArrowRight, CalendarPlus, Clock, LayoutGrid, Flame, Gem, ShieldCheck, LogIn, Mail, MapPin, Menu, MessageCircle, Phone, X } from 'lucide-react';
import { useSite, waLink, telLink, monYear, media } from './site';
import { Logo } from './icons';

export const NAV = [['/', 'Home'], ['/services', 'Quality Services'], ['/q-track', 'Q-Track Portal'], ['/blog', 'Knowledge Hub'], ['/about', 'About Founder']];
export const ERP_LOGIN = (import.meta.env.BASE_URL || '/') + 'erp/login';

export function Header() {
  const { C, openBook } = useSite();
  const [open, setOpen] = useState(false);
  const loc = useLocation();
  useEffect(() => setOpen(false), [loc.pathname]);
  useEffect(() => { document.body.style.overflow = open ? 'hidden' : ''; }, [open]);
  const isOn = (to) => (to === '/' ? loc.pathname === '/' : loc.pathname.startsWith(to));
  return <>
    <header className="hdr">
      <div className="wrap">
        <Link to="/" aria-label="Home"><Logo C={C} /></Link>
        <nav className="nav">{NAV.map(([to, l]) => <NavLink key={to} to={to} className={isOn(to) ? 'on' : ''}>{l}</NavLink>)}</nav>
        <div className="hdr-cta">
          <a className="btn btn-gline btn-sm" href={ERP_LOGIN}><LogIn />Q-Track Login</a>
          <button className="btn btn-gold btn-sm" onClick={() => openBook()}><CalendarPlus />Book an Audit</button>
        </div>
        <button className="burger" aria-label="Menu" onClick={() => setOpen(true)}><Menu /></button>
      </div>
    </header>
    {open && <div className="mmenu">
      <div className="top"><Logo C={C} /><button aria-label="Close" onClick={() => setOpen(false)}><X size={26} /></button></div>
      <nav>{NAV.map(([to, l], i) => <Link key={to} to={to} className={isOn(to) ? 'on' : ''}>{l}<small>0{i + 1}</small></Link>)}</nav>
      <div className="acts">
        <button className="btn btn-gold btn-lg" onClick={() => { setOpen(false); openBook(); }}><CalendarPlus />Book an Audit</button>
        <a className="btn btn-gline btn-lg" href={ERP_LOGIN}><LogIn />Q-Track Login</a>
      </div>
      <div className="ct">
        <a href={telLink(C.brand.phone)}><Phone />{C.brand.phone}</a>
        <a href={'mailto:' + C.brand.email}><Mail />{C.brand.email}</a>
      </div>
    </div>}
  </>;
}

export function Footer() {
  const { C, openBook } = useSite(); const b = C.brand;
  return <footer className="ftr">
    <div className="wrap">
      <div className="cols">
        <div><Logo C={C} light /><p>{b.footer_about}</p></div>
        <div><h4>SERVICES</h4><ul>
          <li><Link to="/services#tpq">TPQ Inspections</Link></li><li><Link to="/services#audits">Factory Audits</Link></li>
          <li><Link to="/services#qms">Lean QMS Retainers</Link></li><li><Link to="/services#training">Staff Training</Link></li></ul></div>
        <div><h4>COMPANY</h4><ul>
          <li><Link to="/about">About Founder</Link></li><li><Link to="/blog">Knowledge Hub</Link></li><li><Link to="/q-track">Q-Track Portal</Link></li>
          <li><a href="#" onClick={(e) => { e.preventDefault(); openBook(); }}>Book an Audit</a></li><li><Link to="/verify">Verify a certificate</Link></li></ul></div>
        <div><h4>CONTACT</h4><ul>
          {b.phone && <li><Phone /><a href={telLink(b.phone)}>{b.phone}</a></li>}
          {b.email && <li><Mail /><a href={'mailto:' + b.email}>{b.email}</a></li>}
          {b.email2 && <li><Mail /><a href={'mailto:' + b.email2}>{b.email2}</a></li>}
          {b.city && <li><MapPin />{b.city}</li>}</ul></div>
      </div>
      <div className="bottom"><span>© {new Date().getFullYear()} {b.name} {b.tagline}. All rights reserved.</span><span>{(b.cities || []).join(' · ')}</span></div>
    </div>
  </footer>;
}

export function WhatsAppFab() {
  const { C } = useSite();
  if (!C.brand.whatsapp) return null;
  return <a className="wa-fab" href={waLink(C)} target="_blank" rel="noopener" aria-label="WhatsApp us"><WaIcon /></a>;
}
export const WaIcon = (p) => <svg viewBox="0 0 24 24" fill="currentColor" {...p}><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91C21.95 6.45 17.5 2 12.04 2zm5.8 14.03c-.25.69-1.44 1.32-1.98 1.37-.51.05-.99.24-3.33-.69-2.82-1.11-4.6-3.99-4.74-4.18-.14-.18-1.13-1.5-1.13-2.87 0-1.36.71-2.03.97-2.31.25-.28.55-.35.74-.35l.53.01c.17.01.4-.06.62.48.23.55.78 1.91.85 2.05.07.14.11.3.02.48-.09.18-.14.3-.28.46-.14.16-.29.36-.42.48-.14.14-.28.29-.12.57.16.28.72 1.19 1.55 1.93 1.07.95 1.97 1.25 2.25 1.39.28.14.44.12.6-.07.16-.18.69-.81.88-1.09.18-.28.37-.23.62-.14.25.09 1.6.76 1.87.9.28.14.46.21.53.32.07.12.07.67-.18 1.36z" /></svg>;

export function CtaBand({ title, text }) {
  const { C, openBook } = useSite();
  return <div className="cta-band">
    <div><h2>{title}</h2><p>{text}</p></div>
    <div className="acts">
      <button className="btn btn-navy" onClick={() => openBook()}><CalendarPlus />Book an Audit</button>
      <a className="btn btn-line" href={waLink(C)} target="_blank" rel="noopener"><MessageCircle />WhatsApp us</a>
    </div>
  </div>;
}

const CAT_ICON = (cat = '') => { const c = cat.toLowerCase();
  if (c.includes('aql') || c.includes('sampl')) return [LayoutGrid, 'cv-0'];
  if (c.includes('cast') || c.includes('manuf')) return [Flame, 'cv-1'];
  if (c.includes('diamond') || c.includes('gem')) return [Gem, 'cv-2'];
  return [ShieldCheck, 'cv-3']; };
export function PostCard({ p, i = 0, small }) {
  const [I, cls] = CAT_ICON(p.category);
  return <Link to={'/blog/' + p.slug} className="post">
    <div className={'cv ' + cls}>
      {p.cover ? <img src={media(p.cover)} alt="" loading="lazy" /> : <I />}
      {!small && p.category && <span className={'cat' + (i === 0 ? ' gold' : '')}>{p.category}</span>}
    </div>
    <div className="bd">
      {small && p.category && <div className="kicker">{p.category}</div>}
      <h3>{p.title}</h3>
      <div className="meta"><Clock />{p.read_min} min read{!small && p.published_at ? ' · ' + monYear(p.published_at) : ''}</div>
    </div>
  </Link>;
}
export const Spinner = () => <div className="spin"><i /></div>;
export const ArrowLink = ({ to, children, onClick }) => (to ? <Link className="link-gold" to={to}>{children}<ArrowRight /></Link> : <button className="link-gold" style={{ background: 'none', border: 0, padding: 0 }} onClick={onClick}>{children}<ArrowRight /></button>);

/** scroll to #hash after route change */
export function useHashScroll(dep) {
  const loc = useLocation();
  useEffect(() => {
    if (loc.hash) { const el = document.getElementById(loc.hash.slice(1)); if (el) setTimeout(() => el.scrollIntoView({ behavior: 'smooth' }), 60); }
    else window.scrollTo(0, 0);
  }, [loc.pathname, loc.hash, dep]);
}
