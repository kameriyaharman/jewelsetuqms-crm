import { createContext, useCallback, useContext, useEffect, useState } from 'react';
import { NavLink, Route, Routes, useLocation, Navigate } from 'react-router-dom';
import { Download, ExternalLink, FileText, Globe, Inbox, LayoutDashboard, LogOut, Magnet, Menu, PenSquare, Settings as Cog, ScanLine } from 'lucide-react';
import { get, post } from '../api';
import { initials } from '../site';
import { Logo } from '../icons';
import { Spinner } from '../components';
import Dashboard from './Dashboard';
import Leads from './Leads';
import { Posts, Editor } from './Posts';
import { Magnets, Downloads } from './Magnets';
import Content from './Content';
import Settings from './Settings';
import './admin.css';

const ALLOWED = ['super_admin', 'back_office'];
const ACtx = createContext(null);
export const useAdmin = () => useContext(ACtx);

function Login({ onIn, denied }) {
  const [f, setF] = useState({ login: '', password: '' }); const [err, setErr] = useState(denied ? 'This login does not have website admin access (Super Admin / Back Office only).' : ''); const [busy, setBusy] = useState(false);
  const go = async (e) => { e.preventDefault(); setBusy(true); setErr('');
    try { const r = await post('auth/login', f); if (!ALLOWED.includes(r.user.role)) { setErr('This login does not have website admin access (Super Admin / Back Office only).'); await post('auth/logout').catch(() => {}); } else onIn(r); }
    catch (x) { setErr(x.message); } finally { setBusy(false); } };
  return <div className="alogin"><form className="box" onSubmit={go}>
    <Logo C={{ brand: { name: 'JEWELSETU', tagline: 'Website admin' } }} />
    <h2 className="serif">Sign in to admin</h2>
    <label className="fl">Email or login code</label><input className="inp" value={f.login} onChange={(e) => setF({ ...f, login: e.target.value })} autoFocus autoComplete="username" />
    <label className="fl">Password</label><input className="inp" type="password" value={f.password} onChange={(e) => setF({ ...f, password: e.target.value })} autoComplete="current-password" />
    {err && <div className="err">{err}</div>}
    <button className="btn btn-gold" style={{ width: '100%', marginTop: 18 }} disabled={busy}>{busy ? 'Signing in…' : 'Sign in'}</button>
    <p style={{ textAlign: 'center', fontSize: 12.5, color: 'var(--muted)', margin: '16px 0 0' }}>Same login as Q-Track ERP · <a href={(import.meta.env.BASE_URL || '/') } style={{ color: 'var(--gold-d)' }}>Back to website</a></p>
  </form></div>;
}

export default function Admin() {
  const [me, setMe] = useState(null); const [open, setOpen] = useState(false); const [toast, setToast] = useState(null); const [counts, setCounts] = useState({});
  const loc = useLocation();
  useEffect(() => { get('me').then(setMe).catch(() => setMe({ auth: false })); }, []);
  useEffect(() => setOpen(false), [loc.pathname]);
  const msg = useCallback((m, bad) => { setToast({ m, bad }); setTimeout(() => setToast(null), 3000); }, []);
  const refreshCounts = useCallback(() => get('site/admin/leads', { status: 'NEW' }).then((r) => setCounts({ new: r.counts.NEW || 0 })).catch(() => {}), []);
  useEffect(() => { if (me?.auth && ALLOWED.includes(me.user.role)) refreshCounts(); }, [me, refreshCounts]);
  useEffect(() => { document.title = 'Admin · JEWELSETU'; }, [loc.pathname]);
  if (!me) return <Spinner />;
  if (!me.auth || !ALLOWED.includes(me.user.role)) return <Login onIn={setMe} denied={me.auth} />;
  const out = async () => { await post('auth/logout').catch(() => {}); setMe({ auth: false }); };
  const u = me.user; const base = import.meta.env.BASE_URL || '/';
  const L = ({ to, icon: I, children, end, n }) => <NavLink to={to} end={end}><I />{children}{n ? <span className="n">{n}</span> : null}</NavLink>;
  return <ACtx.Provider value={{ me, toast: msg, refreshCounts }}>
    <div className="mtop"><button onClick={() => setOpen(true)} aria-label="Menu"><Menu /></button><Logo C={{ brand: { name: 'JEWELSETU', tagline: 'Admin' } }} light /></div>
    <div className="adm">
      <aside className={'aside' + (open ? ' open' : '')}>
        <Logo C={{ brand: { name: 'JEWELSETU', tagline: 'Admin' } }} light />
        <nav>
          <L to="/admin" icon={LayoutDashboard} end>Dashboard</L>
          <L to="/admin/blog" icon={PenSquare}>Blog Articles</L>
          <L to="/admin/leads" icon={Inbox} n={counts.new}>Audit Leads</L>
          <L to="/admin/downloads" icon={Download}>Downloads</L>
          <L to="/admin/magnets" icon={Magnet}>Lead Magnets</L>
          <L to="/admin/content" icon={FileText}>Website Content</L>
          <L to="/admin/settings" icon={Cog}>Settings</L>
          <div className="sep" />
          <a href={base} target="_blank" rel="noopener"><Globe />View website<ExternalLink style={{ marginLeft: 'auto', width: 13 }} /></a>
          <a href={base + 'erp/'}><ScanLine />Q-Track ERP</a>
        </nav>
        <div className="me"><span className="avatar">{initials(u.name)}</span><div><b>{u.name}</b><small>{u.role_label}</small></div><button onClick={out} title="Sign out"><LogOut /></button></div>
      </aside>
      {open && <div className="drawer-ovl" style={{ zIndex: 65 }} onClick={() => setOpen(false)} />}
      <main className="amain">
        <Routes>
          <Route index element={<Dashboard />} />
          <Route path="blog" element={<Posts />} />
          <Route path="blog/new" element={<Editor />} />
          <Route path="blog/:id" element={<Editor />} />
          <Route path="leads" element={<Leads />} />
          <Route path="downloads" element={<Downloads />} />
          <Route path="magnets" element={<Magnets />} />
          <Route path="content" element={<Content />} />
          <Route path="settings" element={<Settings />} />
          <Route path="*" element={<Navigate to="/admin" replace />} />
        </Routes>
      </main>
    </div>
    {toast && <div className="toast" style={toast.bad ? { background: '#b42318' } : undefined}>{toast.m}</div>}
  </ACtx.Provider>;
}
