import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { get, post } from '../api';
import { useAdmin } from './Admin';

export default function Settings() {
  const { toast, me } = useAdmin();
  const [s, setS] = useState(null); const [secret, setSecret] = useState('');
  useEffect(() => { get('site/admin/settings').then(setS).catch(() => {}); }, []);
  if (!s) return <div className="spin"><i /></div>;
  const admin = me.user.role === 'super_admin';
  const save = async (extra = {}) => { try { const r = await post('site/admin/settings', { turnstile_site: s.turnstile_site, turnstile_secret: secret, ...extra }); toast(r.message); setSecret(''); get('site/admin/settings').then(setS); } catch (e) { toast(e.message, 1); } };
  const base = import.meta.env.BASE_URL || '/';
  return <>
    <div className="atop"><div><h1 className="serif">Settings</h1><p>Website integrations. Page text, contact details and form options are under <Link to="/admin/content" style={{ color: 'var(--gold-d)' }}>Website content</Link>.</p></div></div>
    <div className="card" style={{ maxWidth: 760, marginBottom: 18 }}><div className="card-h"><h3>Email alerts</h3></div><div className="card-b" style={{ fontSize: 14 }}>
      <p style={{ marginTop: 0 }}>New audit requests email the address set in <b>Website content → Booking form → Lead alert email</b>, and the client gets an acknowledgement. Emails use the Q-Track mail settings.</p>
      <p>Current transport: <b>{s.mail_transport === 'none' ? 'OFF (log only)' : s.mail_transport.toUpperCase()}</b>{s.mail_from ? ' · from ' + s.mail_from : ''}</p>
      <a className="btn btn-line btn-sm" href={base + 'erp/settings'}>Open Q-Track → Settings → Email</a>
    </div></div>
    <div className="card" style={{ maxWidth: 760, marginBottom: 18 }}><div className="card-h"><h3>Spam protection — Cloudflare Turnstile</h3><span className={'st ' + (s.turnstile_site && s.turnstile_secret_set ? 'st-WON' : 'st-LOST')}>{s.turnstile_site && s.turnstile_secret_set ? 'On' : 'Off'}</span></div>
      <div className="card-b">
        <p style={{ marginTop: 0, fontSize: 13.5, color: 'var(--muted)' }}>Forms already have a hidden honeypot and a per-IP limit. For stronger protection create a free Turnstile widget at dash.cloudflare.com → Turnstile for your domain and paste both keys.</p>
        <label className="fl">Site key</label><input className="inp" value={s.turnstile_site} disabled={!admin} onChange={(e) => setS({ ...s, turnstile_site: e.target.value })} />
        <label className="fl">Secret key {s.turnstile_secret_set && <span style={{ color: 'var(--ok)' }}>· saved</span>}</label>
        <input className="inp" type="password" value={secret} disabled={!admin} onChange={(e) => setSecret(e.target.value)} placeholder={s.turnstile_secret_set ? '•••••••• (leave blank to keep)' : ''} />
        {admin && <div style={{ display: 'flex', gap: 10, marginTop: 16 }}><button className="btn btn-navy btn-sm" onClick={() => save()}>Save</button>
          {s.turnstile_site && <button className="btn btn-line btn-sm" onClick={() => save({ clear_turnstile: 1 })}>Turn off</button>}</div>}
      </div></div>
    <div className="card" style={{ maxWidth: 760 }}><div className="card-h"><h3>SEO</h3></div><div className="card-b" style={{ fontSize: 14 }}>
      <p style={{ marginTop: 0 }}>Every page and article gets its own title, description and LinkedIn / WhatsApp preview card automatically. Default title, description, share image and Google Analytics ID are under <Link to="/admin/content?s=seo" style={{ color: 'var(--gold-d)' }}>Website content → SEO</Link>.</p>
      <p style={{ marginBottom: 0 }}>Sitemap: <a href={base + 'sitemap.xml'} target="_blank" rel="noopener" style={{ color: 'var(--gold-d)' }}>{window.location.origin + base}sitemap.xml</a> — submit it in Google Search Console.</p>
    </div></div>
  </>;
}
