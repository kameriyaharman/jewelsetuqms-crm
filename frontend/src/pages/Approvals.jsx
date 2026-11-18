import { useState } from 'react';
import { Page } from '../app/Layout';
import { useAuth } from '../app/auth';
import { pdfUrl, post } from '../app/api';
import { Badge, Empty, Loader, useAction, useApi, Thumb } from '../app/ui';
import { fdate, fdt, num } from '../app/util';

export default function Approvals() {
  const { me, refresh } = useAuth(); const r = me.user.role; const q = useApi('approvals'); const { run, busy } = useAction();
  const [rem, setRem] = useState({}); const [dec, setDec] = useState({});
  const act = async (body) => { const x = await run(() => post('job/action', body)); if (x) { q.reload(); refresh(); } };
  const title = r === 'client' ? 'Approvals — holds & concessions' : r === 'vendor' ? 'Designs held for your approval' : 'Holds & Concessions';
  return <Page title={title} sub={r === 'vendor' ? 'Inspector paused these SKUs and needs your confirmation before continuing' : 'SKUs held by inspectors for your decision, and CONDITIONAL lots waiting for deviation approval'}>
    <Loader q={q}>{(d) => <>
      <div className={'grid ' + (d.concessions.length ? 'g2' : '')}>
        <div className="card"><div className="card-h"><h2>⏸ SKU hold for approval ({d.holds.length})</h2></div>
          {!d.holds.length && <Empty>No SKU is waiting for {['client', 'vendor'].includes(r) ? 'your' : 'a'} decision.</Empty>}
          {d.holds.map((h) => <div key={h.id} className="note rose" style={{ marginBottom: 12, color: 'var(--tx)' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8, flexWrap: 'wrap' }}><span className="skucell"><Thumb src={h.img} size={48} alt={h.sku} /><b className="mono">{h.sku} · {h.product}</b></span><span><Badge s="HOLD" /> <small>for {h.hold_for}</small></span></div>
            <p className="muted" style={{ margin: '6px 0', fontSize: 12.5 }}>{r === 'vendor' ? h.client_code : h.vendor_name} · PO {h.po_no} · {h.job_no} · held by {h.hold_by} · {fdt(h.hold_at)}</p>
            <p style={{ margin: '6px 0 10px', fontSize: 14 }}>“{h.hold_reason}”</p>
            {!!h.photos.length && <div className="photos" style={{ gridTemplateColumns: 'repeat(4,1fr)', marginBottom: 10 }}>{h.photos.map((u) => <figure key={u}><a target="_blank" rel="noreferrer" href={u}><img src={u} style={{ height: 70 }} alt="" /></a></figure>)}</div>}
            {(r === h.hold_for || r === 'super_admin') ? <>
              <div className="f"><textarea rows="2" value={rem[h.id] || ''} onChange={(e) => setRem({ ...rem, [h.id]: e.target.value })} placeholder="Your instruction to the inspector (e.g. Accept shade — proceed with QC)" /></div>
              <div className="actions"><button className="btn pri" disabled={busy} onClick={() => act({ act: 'hold_respond', item_id: h.id, decision: 'APPROVE', remark: rem[h.id] || '' })}>✓ Approve — continue QC</button>
                <button className="btn ruby" disabled={busy} onClick={() => act({ act: 'hold_respond', item_id: h.id, decision: 'REJECT', remark: rem[h.id] || '' })}>✕ Reject</button></div></>
              : <small className="muted">Waiting for {h.hold_for} response.</small>}
          </div>)}
        </div>
        {!!d.concessions.length && <div className="card"><div className="card-h"><h2>Concession approval needed ({d.concessions.length})</h2></div>
          {d.concessions.map((c) => <div key={c.id} className="card" style={{ margin: '0 0 12px', boxShadow: 'none' }}>
            <div className="card-h"><h2>Deviation request — <span className="mono">{c.report_no}</span></h2><Badge s="CONDITIONAL" /></div>
            <div className="kv mb"><div><small>Vendor</small><b>{c.vendor_name}</b></div><div className="skucell"><Thumb src={c.img} size={40} alt={c.sku} /><span><small>PO / SKU</small><b>{c.po_no} · {c.sku}</b></span></div><div><small>Inspected</small><b>{num(c.inspected_qty)} pcs</b></div>
              <div><small>Raised by</small><b>{c.mgr || 'QC Manager'}</b></div><div><small>Raised on</small><b>{fdate(c.approved_at)}</b></div><div><small>Reply needed by</small><b>{fdate(c.concession_due)}</b></div></div>
            {!!c.deviation.length && <><label>What is outside specification</label><table className="t mb"><thead><tr><th>Parameter</th><th>Spec</th><th>Actual</th><th>Deviation</th><th className="num">Pcs</th></tr></thead><tbody>
              {c.deviation.map((x, k) => <tr key={k}><td>{x.param}</td><td>{x.spec}</td><td>{x.actual}</td><td className="bad-t">{x.dev}</td><td className="num">{x.pcs}</td></tr>)}</tbody></table></>}
            <div className="note mb"><b>QC manager note:</b> {c.manager_note}</div>
            <div className="actions mb"><a className="btn" target="_blank" rel="noreferrer" href={pdfUrl({ item: c.id })}>View report PDF</a></div>
            {['client', 'super_admin'].includes(r) && <form onSubmit={(e) => { e.preventDefault(); act({ act: 'concession', item_id: c.id, decision: dec[c.id] || 'APPROVE', remark: rem['c' + c.id] || '' }); }}>
              <div className="decision"><label><input type="radio" checked={(dec[c.id] || 'APPROVE') === 'APPROVE'} onChange={() => setDec({ ...dec, [c.id]: 'APPROVE' })} /> Approve deviation (concession)</label>
                <label><input type="radio" checked={dec[c.id] === 'REJECT'} onChange={() => setDec({ ...dec, [c.id]: 'REJECT' })} /> Reject — rework required</label></div>
              <div className="f"><label>Client remark</label><textarea value={rem['c' + c.id] || ''} onChange={(e) => setRem({ ...rem, ['c' + c.id]: e.target.value })} placeholder="Accept with 2″ extender added before dispatch." /></div>
              <div className="f"><label>Approved by</label><input type="text" readOnly value={d.me} /></div>
              <button className="btn pri block" disabled={busy}>SUBMIT DECISION</button>
              <p className="muted" style={{ fontSize: 12 }}>Decision, name &amp; time are printed on the PDF report and stored in the audit trail.</p></form>}
          </div>)}</div>}
      </div>
      {!!d.history.length && <div className="card mt"><h2 className="mb">Recent decisions</h2><table className="t"><thead><tr><th>SKU / report</th><th>{r === 'vendor' ? 'Client' : 'Vendor'}</th><th>Type</th><th>Decision</th><th>Remark</th><th>When</th></tr></thead><tbody>
        {d.history.map((x) => { const isC = x.concession_status && x.concession_status !== 'PENDING';
          return <tr key={x.id}><td className="mono">{x.report_no || x.sku}</td><td>{r === 'vendor' ? x.client_code : x.vendor_name}</td><td>{isC ? 'Concession' : `SKU hold (${x.hold_for})`}</td>
            <td><Badge s={isC ? x.concession_status : x.hold_status} /></td><td>{isC ? x.concession_remark : x.hold_response}</td><td>{fdt(isC ? x.concession_at : x.hold_resp_at)}</td></tr>; })}</tbody></table></div>}
    </>}</Loader>
  </Page>;
}
