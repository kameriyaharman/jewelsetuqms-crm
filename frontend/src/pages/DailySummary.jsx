import { useState } from 'react';
import { post } from '../app/api';
import { Loader, Modal, useAction, useApi, useToast } from '../app/ui';
import { fdate, num, today } from '../app/util';

/** 📤 Send Daily Summary Now — preview per vendor / client, copy WhatsApp text, send all or one. */
export function DailySummaryButton({ className = 'btn' }) {
  const [open, setOpen] = useState(false);
  return <><button className={className} onClick={() => setOpen(true)}>📤 Send Daily Summary Now</button>{open && <DailySummaryModal onClose={() => setOpen(false)} />}</>;
}
function DailySummaryModal({ onClose }) {
  const [date, setDate] = useState(today()); const q = useApi('summary/daily', { date }); const [sel, setSel] = useState(0);
  const { run, busy } = useAction(); const toast = useToast();
  return <Modal open title="Daily QC summary — preview & send" onClose={onClose} wide>
    <div className="fr f" style={{ alignItems: 'flex-end' }}><div><label>Date</label><input type="date" value={date} max={today()} onChange={(e) => { setDate(e.target.value); setSel(0); }} /></div>
      <div><small className="muted">Auto-send: {q.data ? (q.data.on ? `ON · daily at ${q.data.time}` : 'OFF') : '…'}{q.data?.last ? ` · last sent ${fdate(q.data.last, 'd M')}` : ''}<br />CC: {q.data?.cc || '—'}</small></div></div>
    <Loader q={q}>{(d) => !d.messages.length ? <div className="note">No inspections on {fdate(date, 'd M Y')} — nothing to send (blank summaries are never sent).</div> : <>
      {d.transport === 'none' && <div className="note gold mb" style={{ fontSize: 12.5 }}>Email transport is OFF (Settings → Email) — mails will only be logged; portal alerts still go. Use “Copy WhatsApp summary” meanwhile.</div>}
      <div className="tabs mb">{d.messages.map((m, i) => <a key={m.key} href="#" className={sel === i ? 'on' : ''} onClick={(e) => { e.preventDefault(); setSel(i); }}>{m.type === 'Vendor' ? '🏭' : '💎'} {m.name}</a>)}</div>
      {(() => { const m = d.messages[sel] || d.messages[0]; return <>
        <p className="muted" style={{ fontSize: 12.5, margin: '0 0 6px' }}>To: {m.emails.join(', ') || <b style={{ color: 'var(--ruby)' }}>no email on file</b>} · {m.totals.designs} SKUs · {num(m.totals.pcs)} pcs · OK {num(m.totals.ok)} · Rej {num(m.totals.rej)} ({m.totals.rate.toFixed(1)}%)</p>
        <div style={{ border: '1px solid var(--line)', borderRadius: 10, maxHeight: '45vh', overflow: 'auto' }} dangerouslySetInnerHTML={{ __html: m.html }} />
        <div className="actions"><button className="btn" onClick={() => { navigator.clipboard?.writeText(m.text); toast('WhatsApp summary copied — paste in the group'); }}>📋 Copy WhatsApp summary</button>
          <button className="btn" disabled={busy} onClick={() => run(() => post('summary/send', { date, key: m.key }))}>Send to {m.type.toLowerCase()} only</button>
          <button className="btn pri" disabled={busy} onClick={async () => { if (await run(() => post('summary/send', { date }))) q.reload(); }}>📤 Send to all ({d.messages.length})</button></div></>; })()}
    </>}</Loader>
  </Modal>;
}
/** Portal card: today's QC summary for the logged-in client / vendor. */
export function TodaySummaryCard() {
  const q = useApi('summary/today'); const toast = useToast();
  if (!q.data) return null; const m = q.data.msg;
  return <div className="card"><div className="card-h"><h2>Today's QC summary ({fdate(q.data.date, 'd/m/Y')})</h2>{m && <button className="btn sm" onClick={() => { navigator.clipboard?.writeText(m.text); toast('Summary copied'); }}>📋 Copy</button>}</div>
    {!m ? <p className="muted" style={{ margin: 0 }}>No inspections completed today yet.</p> : <>
      <div className="g5" style={{ gridTemplateColumns: 'repeat(4,minmax(0,1fr))', gap: 8 }}>{[['Designs', m.totals.designs], ['Checked pcs', num(m.totals.pcs)], ['OK pcs', num(m.totals.ok)], ['Repair / rej', num(m.totals.rej) + ` (${m.totals.rate.toFixed(1)}%)`]].map(([l, v]) =>
        <div key={l} style={{ background: '#f8fafb', borderRadius: 8, padding: 8, textAlign: 'center' }}><small className="muted">{l}</small><div style={{ fontWeight: 700, fontSize: 16 }}>{v}</div></div>)}</div>
      <table className="t mt"><thead><tr><th>{m.type === 'Client' ? 'Vendor' : 'Client'}</th><th className="num">SKUs</th><th className="num">Checked</th><th className="num">OK</th><th className="num">Rej</th><th className="num">Pending bal.</th></tr></thead><tbody>
        {m.rows.map((g, i) => <tr key={i}><td>{m.type === 'Client' ? g.vendor : g.client}</td><td className="num">{g.designs}</td><td className="num">{num(g.pcs)}</td><td className="num">{num(g.ok)}</td><td className="num">{num(g.rej)}</td><td className="num">{num(g.pending)}</td></tr>)}</tbody></table></>}</div>;
}
