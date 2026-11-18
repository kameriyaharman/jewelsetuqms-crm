import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { homeFor, useAuth } from '../app/auth';

const DEMO = [['Super Admin', 'admin@jewelsetu.com', 'Admin@123'], ['Back Office', 'sonal@jewelsetu.com', 'Demo@123'], ['QC Manager', 'ravi@jewelsetu.com', 'Demo@123'],
  ['Inspector (JS-010)', 'JS-010', 'Demo@123'], ['Client · KP', 'rajiv@kpjewels.com', 'Demo@123'], ['Vendor · Ashok', 'VND-ASHOK', 'Demo@123']];

export default function Login() {
  const { login, me } = useAuth(); const nav = useNavigate();
  const [id, setId] = useState(''); const [pw, setPw] = useState(''); const [err, setErr] = useState(''); const [busy, setBusy] = useState(false);
  const submit = async (e) => {
    e.preventDefault(); setBusy(true); setErr('');
    try { const m = await login(id.trim(), pw); nav(homeFor(m.user.role), { replace: true }); } catch (ex) { setErr(ex.message); }
    setBusy(false);
  };
  return <div className="login">
    <div className="hero">
      <div>
        <div className="lg"><img src={import.meta.env.BASE_URL + 'img/logo.png'} alt="Jewelsetu Quality Services" /></div>
        <h2>QC Inspection &amp;<br />Reporting System<br /><em>From Inspection to Insight</em></h2>
        <p style={{ color: '#b9d0d3', maxWidth: 460 }}>One platform for JEWELSETU, brands and factories — QC requests, on-site inspection, locked PDF reports and vendor quality analytics.</p>
      </div>
      <div className="feats"><div>Excel → QC jobs in minutes</div><div>On-site inspection with photos</div><div>Auto PDF report &amp; email</div><div>Vendor quality analytics</div></div>
    </div>
    <div className="form"><div className="box">
      <h1>Welcome back</h1><p className="muted" style={{ marginTop: 0 }}>Sign in with your assigned credentials</p>
      {me?.offline && <div className="flash f-warn">Cannot reach the server — check your internet connection.</div>}
      {err && <div className="flash f-err">{err}</div>}
      <form onSubmit={submit}>
        <div className="f"><label>Email / Employee ID / Vendor code</label><input type="text" value={id} onChange={(e) => setId(e.target.value)} required autoFocus autoComplete="username" /></div>
        <div className="f"><label>Password</label><input type="password" value={pw} onChange={(e) => setPw(e.target.value)} required autoComplete="current-password" /></div>
        <div className="actions" style={{ justifyContent: 'space-between', margin: '6px 0 16px' }}><label className="chk"><input type="checkbox" defaultChecked /> Remember me</label>
          <a href="#" onClick={(e) => { e.preventDefault(); alert('Please contact JEWELSETU admin to reset your password.'); }}>Forgot password?</a></div>
        <button className="btn pri block" style={{ padding: 12 }} disabled={busy}>{busy ? 'Signing in…' : 'LOGIN'}</button>
      </form>
      <p className="muted" style={{ fontSize: 12, marginTop: 14 }}>One login · 6 roles · routes to Internal, Client, Vendor portal or the Inspector app</p>
      <div className="roles">{['SUPER ADMIN', 'BACK OFFICE', 'QC MANAGER', 'INSPECTOR', 'CLIENT', 'VENDOR'].map((r) => <span key={r}>{r}</span>)}</div>
      {me?.demo !== false && <div className="demo"><b>Demo logins</b> (click to fill)<br />
        {DEMO.map(([l, a, b]) => <button key={l} type="button" onClick={() => { setId(a); setPw(b); }}>{l}</button>)}</div>}
    </div></div>
  </div>;
}
