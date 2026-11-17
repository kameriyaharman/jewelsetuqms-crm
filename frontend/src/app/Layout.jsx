import { useEffect, useState } from 'react';
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from './auth';
import { Icon } from './ui';
import { initials } from './util';
import { flushQueue, queue } from './api';

function menuFor(role, flags, c) {
  switch (role) {
    case 'super_admin': return [['dashboard', 'Dashboard', 'grid'], ['clients', 'Clients', 'building'], ['vendors', 'Vendors', 'factory'], ['mapping', 'Client–Vendor Mapping', 'link'],
      ['_', 'QC Management'], ['requests', 'QC Requests', 'inbox', c.requests], ['import', 'Import Excel', 'upload'], ['jobs', 'QC Jobs', 'clip'], ['schedule', 'QC Schedule', 'cal'], ['review', 'Pending Review', 'eye', c.review],
      ['_', 'Insight'], ['reports', 'Reports', 'file'], ['analytics', 'Analytics', 'chart'], ['approvals', 'Holds & Concessions', 'pause', c.approvals],
      ['_', 'Admin'], ['team', 'Team', 'users'], ['settings', 'Settings', 'cog']];
    case 'back_office': return [['dashboard', 'Dashboard', 'grid'], ['clients', 'Clients', 'building'], ['vendors', 'Vendors', 'factory'],
      ['_', 'QC Management'], ['requests', 'QC Requests', 'inbox', c.requests], ['request/new', 'New QC Request', 'plus'], ['import', 'Import Excel', 'upload'], ['jobs', 'QC Jobs', 'clip'], ['schedule', 'QC Schedule', 'cal'],
      ['_', 'Data'], ['reports', 'Reports', 'file'], ['analytics', 'Analytics', 'chart'], ['team', 'Team', 'users'], ['settings', 'Masters', 'cog']];
    case 'qc_manager': return [['dashboard', 'Dashboard', 'grid'], ['vendors', 'Vendors', 'factory'],
      ['_', 'QC Management'], ['requests', 'QC Requests', 'inbox'], ['jobs', 'QC Jobs & Assign', 'clip'], ['schedule', 'QC Schedule', 'cal'], ['review', 'Pending Review', 'eye', c.review], ['approvals', 'Holds & Concessions', 'pause', c.approvals],
      ['_', 'Insight'], ['reports', 'Reports', 'file'], ['analytics', 'Analytics', 'chart'], ['team', 'Team', 'users'], ['settings', 'Masters', 'cog']];
    case 'client': return [['client', 'Dashboard', 'grid'], ['requests', 'QC Requests', 'inbox'], ...(flags.enable_client_qc_request ? [['request/new', 'Request QC', 'plus']] : []),
      ['vendors', 'My Vendors', 'factory'], ['schedule', 'QC Schedule', 'cal'], ['approvals', 'Approvals', 'check', c.approvals], ['reports', 'Reports', 'file'], ['analytics', 'Analytics', 'chart']];
    case 'vendor': return [['vendor', 'Dashboard', 'grid'], ...(flags.enable_vendor_qc_request ? [['request/new', 'New QC Request', 'plus'], ['import', 'Bulk Excel Request', 'upload']] : []),
      ['requests', 'My QC Requests', 'inbox'], ['schedule', 'Schedule', 'cal'], ['approvals', 'Hold Approvals', 'check', c.approvals], ['reports', 'Reports', 'file'], ['profile', 'Profile', 'user']];
    default: return [];
  }
}

export function Shell() {
  const { me, logout } = useAuth(); const u = me.user; const loc = useLocation(); const nav = useNavigate();
  const [open, setOpen] = useState(false);
  useEffect(() => { setOpen(false); }, [loc.pathname]);
  useEffect(() => { document.body.classList.toggle('nav-open', open); }, [open]);
  const portal = u.role === 'client' ? 'CLIENT PORTAL' : u.role === 'vendor' ? 'VENDOR PORTAL' : 'INTERNAL PORTAL';
  return <div className="shell">
    <aside className="side">
      <Link className="brand" to="/"><img src={import.meta.env.BASE_URL + 'img/mark-t.png'} alt="" /><span><b>JEWELSETU</b><small>Q-TRACK · QC SYSTEM</small></span></Link>
      <div className="portal-tag">{portal}</div>
      <nav>{menuFor(u.role, me.flags, me.counts || {}).map((m, i) => m[0] === '_' ? <div key={i} className="nav-h">{m[1]}</div> :
        <NavLink key={m[0]} to={'/' + m[0]} className={({ isActive }) => (isActive ? 'on' : '')}><Icon n={m[2]} /><span>{m[1]}</span>{m[3] ? <em>{m[3]}</em> : null}</NavLink>)}</nav>
      <div className="me"><div className="av">{initials(u.name)}</div><div><b>{u.name}</b><small>{u.role_label} · {u.org}</small></div>
        <a href="#" title="Logout" onClick={async (e) => { e.preventDefault(); await logout(); nav('/login'); }}><Icon n="logout" /></a></div>
    </aside>
    <main className="main"><Outlet context={{ setOpen }} /></main>
    <div className="scrim" onClick={() => setOpen(false)} />
  </div>;
}

/** page header + content wrapper for desktop portals */
export function Page({ title, sub, actions, children }) {
  const { me } = useAuth();
  useEffect(() => { document.title = title + ' · JEWELSETU Q-TRACK'; }, [title]);
  return <>
    <header className="top">
      <button className="burger" onClick={() => document.body.classList.toggle('nav-open')}><Icon n="menu" /></button>
      <div><h1>{title}</h1>{sub ? <p className="sub">{sub}</p> : null}</div>
      <div className="top-r">{actions}<Link className="bell" to="/notifications"><Icon n="bell" />{me?.counts?.unread ? <i>{me.counts.unread}</i> : null}</Link></div>
    </header>
    <div className="content">{children}</div>
  </>;
}

/* ---------- inspector mobile shell ---------- */
export function MShell() {
  const { me } = useAuth(); const loc = useLocation();
  const [online, setOnline] = useState(navigator.onLine); const [qn, setQn] = useState(queue.all().length);
  useEffect(() => {
    document.body.classList.add('mob');
    const on = () => { setOnline(navigator.onLine); if (navigator.onLine) flushQueue(); };
    const qq = () => setQn(queue.all().length);
    window.addEventListener('online', on); window.addEventListener('offline', on); window.addEventListener('qt-queue', qq);
    flushQueue();
    return () => { document.body.classList.remove('mob'); window.removeEventListener('online', on); window.removeEventListener('offline', on); window.removeEventListener('qt-queue', qq); };
  }, []);
  useEffect(() => { document.body.classList.toggle('offline', !online); }, [online]);
  const tab = loc.pathname.startsWith('/app/history') ? 'history' : loc.pathname.startsWith('/app/alerts') ? 'alerts' : loc.pathname.startsWith('/app/profile') ? 'profile' : 'home';
  const unread = me?.counts?.unread || 0;
  return <div className="mapp">
    <Outlet context={{ online, qn }} />
    <nav className="mtabs">
      <Link to="/app" className={tab === 'home' ? 'on' : ''}><Icon n="home" />Home</Link>
      <Link to="/app/history" className={tab === 'history' ? 'on' : ''}><Icon n="clip" />My QC</Link>
      <Link to="/app?scan=1" className="scanbtn"><Icon n="scan" />Scan</Link>
      <Link to="/app/alerts" className={tab === 'alerts' ? 'on' : ''}><Icon n="bell" />Alerts{unread ? <i>{unread}</i> : null}</Link>
      <Link to="/app/profile" className={tab === 'profile' ? 'on' : ''}><Icon n="user" />Profile</Link>
    </nav>
  </div>;
}
export function MPage({ title, back, right, children }) {
  const nav = useNavigate();
  useEffect(() => { document.title = title + ' · Q-TRACK Inspector'; }, [title]);
  return <>
    <header className="mtop">
      {back ? <a className="mback" href="#" onClick={(e) => { e.preventDefault(); nav(back); }}>‹</a> : <img src={import.meta.env.BASE_URL + 'img/mark-t.png'} className="mlogo" alt="" />}
      <div className="mtitle">{title}</div>
      <div className="mright">{right}<span className={'net' + (navigator.onLine ? '' : ' off')}>●</span></div>
    </header>
    <div className="mbody">{children}</div>
  </>;
}
