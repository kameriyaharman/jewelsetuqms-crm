import { useState } from 'react';
import { Link } from 'react-router-dom';
import { BadgeCheck, Search, XCircle } from 'lucide-react';
import { get } from '../api';

export default function NotFound({ what = 'page' }) {
  return <div className="nf wrap">
    <h1 className="serif">404</h1>
    <p style={{ color: 'var(--muted)' }}>Sorry, this {what} could not be found.</p>
    <div style={{ display: 'flex', gap: 12, justifyContent: 'center', marginTop: 20 }}>
      <Link className="btn btn-gold" to="/">Go to home</Link><Link className="btn btn-line" to="/blog">Knowledge Hub</Link>
    </div>
  </div>;
}

export function Verify() {
  const [no, setNo] = useState(new URLSearchParams(window.location.search).get('no') || '');
  const [r, setR] = useState(null); const [busy, setBusy] = useState(false); const [err, setErr] = useState('');
  const go = async (e) => { e?.preventDefault(); setErr(''); setR(null); setBusy(true);
    try { setR(await get('site/pub/verify', { no: no.trim() })); } catch (x) { setErr(x.message); } finally { setBusy(false); } };
  return <section className="phero" style={{ minHeight: '62vh', paddingTop: 60 }}>
    <div className="wrap">
      <div style={{ textAlign: 'center', marginBottom: 28 }}><span className="eyebrow">Certificate verification</span>
        <h1 className="serif" style={{ marginTop: 12 }}>Verify a <em className="g">JEWELSETU</em> report</h1>
        <p style={{ color: 'var(--muted)', margin: 0 }}>Enter the report / certificate number printed on the inspection report.</p></div>
      <div className="verify">
        <form onSubmit={go}><div className="inp-ico" style={{ flex: 1 }}><Search /><input className="inp" value={no} onChange={(e) => setNo(e.target.value)} placeholder="e.g. JS-PSI-2026-0418" /></div>
          <button className="btn btn-navy" disabled={busy || no.trim().length < 4}>{busy ? 'Checking…' : 'Verify'}</button></form>
        {err && <div className="err">{err}</div>}
        {r && (r.found ? <div className="vres ok"><b style={{ display: 'flex', gap: 8, alignItems: 'center', color: 'var(--ok)' }}><BadgeCheck size={20} />Genuine report issued by JEWELSETU</b>
          <dl><dt>Report no.</dt><dd>{r.report.report_no}{r.report.rev ? ' · Rev ' + r.report.rev : ''}</dd><dt>Product / SKU</dt><dd>{[r.report.product, r.report.sku].filter(Boolean).join(' · ')}</dd>
            {r.report.qc_type && <><dt>Inspection</dt><dd>{r.report.qc_type}</dd></>}<dt>Inspection date</dt><dd>{r.report.date}</dd><dt>Issued</dt><dd>{r.report.issued}</dd><dt>Result</dt><dd>{r.report.result}</dd></dl></div>
          : <div className="vres no"><b style={{ display: 'flex', gap: 8, alignItems: 'center' }}><XCircle size={20} />No approved report found with this number.</b>
            <div style={{ marginTop: 6, color: 'var(--muted)' }}>Check the number, or contact us to confirm the document.</div></div>)}
      </div>
    </div>
  </section>;
}
