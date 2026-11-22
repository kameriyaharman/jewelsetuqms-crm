import { useCallback, useEffect, useState } from 'react';
import { Calendar, FileDown, FileText, Mail, MessageCircle, Phone, Search, Trash2, X } from 'lucide-react';
import { apiUrl, get, post } from '../api';
import { fmtDate, telLink } from '../site';
import { useAdmin } from './Admin';

export const ago = (d) => {
  if (!d) return ''; const t = new Date(String(d).replace(' ', 'T')); const s = (Date.now() - t.getTime()) / 1000;
  if (s < 3600) return Math.max(1, Math.round(s / 60)) + ' min ago'; if (s < 86400) return Math.round(s / 3600) + ' h ago';
  if (s < 172800) return 'Yesterday'; return fmtDate(d, { day: 'numeric', month: 'short' });
};
const STAT = { NEW: 'New', QUOTED: 'Quoted', WON: 'Won', LOST: 'Lost' };

export function LeadDrawer({ lead, onClose, onChange }) {
  const { toast, me, refreshCounts } = useAdmin();
  const [notes, setNotes] = useState(lead.notes || ''); const [busy, setBusy] = useState(false);
  const save = async (patch) => { setBusy(true); try { const r = await post('site/admin/lead/update', { id: lead.id, ...patch }); toast(r.message); onChange({ ...lead, ...patch }); refreshCounts(); } catch (e) { toast(e.message, 1); } finally { setBusy(false); } };
  const del = async () => { if (!confirm('Delete this lead permanently?')) return; try { await post('site/admin/lead/delete', { id: lead.id }); toast('Lead deleted.'); onChange(null); refreshCounts(); } catch (e) { toast(e.message, 1); } };
  const wa = String(lead.phone || '').replace(/\D/g, '');
  return <>
    <div className="drawer-ovl" onClick={onClose} />
    <div className="drawer">
      <div className="dh"><div><h3>{lead.name || '—'}</h3><small>{lead.company || 'No company'} · #{lead.id} · {fmtDate(lead.created_at, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</small></div>
        <button onClick={onClose} aria-label="Close"><X /></button></div>
      <div className="db">
        <div className="qlinks">
          {lead.email && <a className="btn btn-line btn-sm" href={'mailto:' + lead.email + '?subject=' + encodeURIComponent('Your ' + (lead.service || 'audit') + ' request — JEWELSETU')}><Mail />Email</a>}
          {wa && <a className="btn btn-sm" style={{ background: '#25d366', color: '#fff' }} href={'https://wa.me/' + wa} target="_blank" rel="noopener"><MessageCircle />WhatsApp</a>}
          {lead.phone && <a className="btn btn-line btn-sm" href={telLink(lead.phone)}><Phone />Call</a>}
        </div>
        <label className="fl">Status</label>
        <div className="segs" style={{ marginBottom: 18 }}>{Object.entries(STAT).map(([k, l]) => <button key={k} className={lead.status === k ? 'on' : ''} disabled={busy} onClick={() => save({ status: k })}>{l}</button>)}</div>
        <dl className="kvl">
          <dt>Service</dt><dd>{lead.service || '—'}</dd><dt>Product</dt><dd>{lead.categories || '—'}</dd><dt>Batch / team</dt><dd>{lead.batch_qty || '—'}</dd>
          <dt>Hub</dt><dd>{lead.hub || '—'}</dd><dt>Preferred date</dt><dd>{lead.pref_date ? fmtDate(lead.pref_date) : '—'}</dd>
          <dt>Email</dt><dd>{lead.email || '—'}</dd><dt>Phone</dt><dd>{lead.phone || '—'}</dd>
          {lead.message && <><dt>Message</dt><dd style={{ fontWeight: 500, whiteSpace: 'pre-wrap' }}>{lead.message}</dd></>}
          <dt>File</dt><dd>{lead.file_url ? <a href={lead.file_url} style={{ color: 'var(--gold-d)', display: 'inline-flex', gap: 6, alignItems: 'center' }}><FileText size={15} />{lead.file_name}</a> : '—'}</dd>
          <dt>Source</dt><dd style={{ fontWeight: 500 }}>{lead.source || 'Website'}</dd>
        </dl>
        <label className="fl">Internal notes</label>
        <textarea className="inp" rows={5} value={notes} onChange={(e) => setNotes(e.target.value)} placeholder="Quote sent, follow-up date, contact person…" />
      </div>
      <div className="df">
        {me.user.role === 'super_admin' ? <button className="btn btn-sm" style={{ color: '#b42318' }} onClick={del}><Trash2 />Delete</button> : <span />}
        <button className="btn btn-navy btn-sm" disabled={busy || notes === (lead.notes || '')} onClick={() => save({ notes })}>Save notes</button>
      </div>
    </div>
  </>;
}

export function LeadsTable({ month, limit, title = 'Audit requests' }) {
  const [st, setSt] = useState(''); const [q, setQ] = useState(''); const [d, setD] = useState(null); const [sel, setSel] = useState(null);
  const load = useCallback(() => get('site/admin/leads', { status: st, q, month }).then(setD).catch(() => setD({ leads: [], counts: {} })), [st, q, month]);
  useEffect(() => { const t = setTimeout(load, q ? 300 : 0); return () => clearTimeout(t); }, [load]); // eslint-disable-line
  const rows = d ? (limit ? d.leads.slice(0, limit) : d.leads) : [];
  const total = d ? Object.values(d.counts).reduce((a, b) => a + b, 0) : 0;
  return <div className="card">
    <div className="card-h"><h3>{title}</h3>
      <div style={{ display: 'flex', gap: 10, alignItems: 'center', flexWrap: 'wrap' }}>
        <div className="segs"><button className={!st ? 'on' : ''} onClick={() => setSt('')}>All{!month && <i>{total}</i>}</button>
          {['NEW', 'QUOTED', 'WON', 'LOST'].map((k) => <button key={k} className={st === k ? 'on' : ''} onClick={() => setSt(k)}>{STAT[k]}{!month && d?.counts[k] ? <i>{d.counts[k]}</i> : null}</button>)}</div>
        <label className="sbox"><Search /><input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search company…" /></label>
      </div></div>
    <div className="tbl-wrap"><table className="tbl">
      <thead><tr><th>Client / Company</th><th>Service</th><th>Product</th><th>Batch</th><th>Hub</th><th>File</th><th>Status</th><th>Received</th></tr></thead>
      <tbody>{!d ? <tr><td colSpan={8} style={{ textAlign: 'center', color: 'var(--muted)' }}>Loading…</td></tr>
        : !rows.length ? <tr><td colSpan={8} style={{ textAlign: 'center', color: 'var(--muted)', padding: 30, cursor: 'default' }}>No audit requests yet. Requests from the “Book an Audit” form appear here.</td></tr>
        : rows.map((l) => <tr key={l.id} onClick={() => setSel(l)}>
          <td><b>{l.name}</b><small>{l.company}</small></td><td>{l.service || '—'}</td><td>{l.categories || '—'}</td><td>{l.batch_qty || '—'}</td><td>{l.hub || '—'}</td>
          <td className="fi">{l.file_url ? <a href={l.file_url} onClick={(e) => e.stopPropagation()} title={l.file_name}><FileText /></a> : '—'}</td>
          <td><span className={'st st-' + l.status}>{STAT[l.status] || l.status}</span></td><td style={{ color: 'var(--muted)', whiteSpace: 'nowrap' }}>{ago(l.created_at)}</td></tr>)}</tbody>
    </table></div>
    {sel && <LeadDrawer lead={sel} onClose={() => setSel(null)} onChange={(n) => { setSel(n); load(); }} />}
  </div>;
}

export default function Leads() {
  const [month, setMonth] = useState('');
  return <>
    <div className="atop"><div><h1 className="serif">Audit leads</h1><p>Every “Book an Audit” request from the website. Click a row to update status, add notes or download the file.</p></div>
      <div className="acts">
        <label className="sbox"><Calendar /><input type="month" value={month} onChange={(e) => setMonth(e.target.value)} style={{ width: 150 }} /></label>
        <a className="btn btn-navy btn-sm" href={apiUrl('site/admin/leads.csv', { month })}><FileDown />Export CSV</a>
      </div></div>
    <LeadsTable month={month} />
  </>;
}
