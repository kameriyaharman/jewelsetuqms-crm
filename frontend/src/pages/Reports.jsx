import { DailySummaryButton } from './DailySummary';
import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Page } from '../app/Layout';
import { useAuth } from '../app/auth';
import { pdfUrl } from '../app/api';
import { Badge, Empty, Kpi, Loader, Result, Sel, useApi, Thumb, Paged } from '../app/ui';
import { fdate, fdt, num, pct, today } from '../app/util';

export default function Reports() {
  const { me } = useAuth(); const r = me.user.role; const internal = ['super_admin', 'back_office', 'qc_manager'].includes(r);
  const [sp, setSp] = useSearchParams(); const p = Object.fromEntries(sp.entries());
  const q = useApi('reports', p); const opts = useApi('options');
  const [f, setF] = useState(p); const [pick, setPick] = useState(new Set()); const [mode, setMode] = useState('A');
  const [vis, setVis] = useState({ vendor: '', client: '', date: today() });
  const toggle = (id) => setPick((x) => { const n = new Set(x); n.has(id) ? n.delete(id) : n.add(id); return n; });
  const set = (k) => (v) => setF((x) => ({ ...x, [k]: typeof v === 'object' ? v.target.value : v }));
  return <Page title="Reports" sub="Search and filter every report by client, vendor, inspector, result and date — view, download or re-email" actions={['super_admin', 'back_office', 'qc_manager'].includes(me?.user?.role) && <DailySummaryButton />}>
    <Loader q={q}>{(d) => { const s = d.stats; return <>
      <div className="card multirep"><div className="card-h"><h2>📑 Multi-design report</h2>
        <span className="seg">{[['A', 'A · PO-wise'], ['B', 'B · Choose designs'], ['C', 'C · Vendor + date visit']].map(([k, l]) => <button key={k} className={mode === k ? 'on' : ''} onClick={() => setMode(k)}>{l}</button>)}</span></div>
        {mode === 'A' && <p className="muted" style={{ margin: 0, fontSize: 12.5 }}>All designs of one PO in one consolidated report, grouped automatically by PO no. — click <b>PO PDF</b> on any row below (shows how many designs it covers).</p>}
        {mode === 'B' && <div style={{ display: 'flex', gap: 10, alignItems: 'center', flexWrap: 'wrap' }}><p className="muted" style={{ margin: 0, fontSize: 12.5, flex: 1 }}>Tick the designs you want in the table below (any POs / job cards of one client) and merge them into one report.</p>
          <b>{pick.size} selected</b>{pick.size > 0 && <button className="btn sm" onClick={() => setPick(new Set())}>Clear</button>}
          <a className={'btn pri' + (pick.size ? '' : ' disabled')} style={pick.size ? {} : { pointerEvents: 'none', opacity: 0.5 }} target="_blank" rel="noreferrer" href={pdfUrl({ items: [...pick].join(',') })}>Generate merged PDF ({pick.size})</a></div>}
        {mode === 'C' && <div className="filters" style={{ margin: 0 }}>
          {opts.data && r !== 'vendor' && <div><label>Vendor visited</label><Sel value={vis.vendor} onChange={(v) => setVis({ ...vis, vendor: v })} options={opts.data.vendors.map((c) => [c.id, c.name])} blank="Select vendor" /></div>}
          {opts.data && r !== 'client' && <div><label>Client</label><Sel value={vis.client} onChange={(v) => setVis({ ...vis, client: v })} options={opts.data.clients.map((c) => [c.id, c.code])} blank="Select client" /></div>}
          <div><label>Visit date</label><input type="date" value={vis.date} onChange={(e) => setVis({ ...vis, date: e.target.value })} /></div>
          {(() => { const v = r === 'vendor' ? me.user.vendor_id : vis.vendor; const ok = v && vis.date && (r === 'client' || vis.client);
            return <a className="btn pri" style={ok ? {} : { pointerEvents: 'none', opacity: 0.5 }} target="_blank" rel="noreferrer" href={pdfUrl({ visit_vendor: v, client: r === 'client' ? '' : vis.client, date: vis.date })}>Generate visit report</a>; })()}
          <p className="muted" style={{ margin: 0, fontSize: 12, flexBasis: '100%' }}>Every design inspected at that factory on that date, in one report — for client billing and visit proof.</p></div>}
      </div>
      <div className="card">
        <form className="filters" onSubmit={(e) => { e.preventDefault(); setSp(Object.fromEntries(Object.entries(f).filter(([, v]) => v))); }}>
          {opts.data && r !== 'client' && <div><label>Client</label><Sel value={f.client} onChange={set('client')} options={opts.data.clients.map((c) => [c.id, c.code])} blank="All" /></div>}
          {opts.data && r !== 'vendor' && <div><label>Vendor</label><Sel value={f.vendor} onChange={set('vendor')} options={opts.data.vendors.map((c) => [c.id, c.name])} blank="All" /></div>}
          {opts.data?.inspectors && <div><label>Inspector</label><Sel value={f.insp} onChange={set('insp')} options={opts.data.inspectors.map((c) => [c.id, c.label])} blank="All" /></div>}
          <div><label>PO / SKU / report</label><input type="search" value={f.q || ''} onChange={set('q')} placeholder="Any" /></div>
          <div><label>Result</label><Sel value={f.res} onChange={set('res')} options={[['PASS', 'Pass'], ['FAIL', 'Fail'], ['CONDITIONAL', 'Conditional'], ['NOTPASS', 'Fail + conditional']]} blank="All" /></div>
          <div><label>From</label><input type="date" value={f.from || d.filters.from} onChange={set('from')} /></div><div><label>To</label><input type="date" value={f.to || d.filters.to} onChange={set('to')} /></div>
          <button className="btn pri">Search</button>
        </form>
        {!d.rows.length ? <Empty>No approved reports for these filters.</Empty> : <Paged rows={d.rows} per={25} label="reports">{(pr) => <div className="tbl-wrap"><table className="t"><thead><tr>{mode === 'B' && <th style={{ width: 28 }}><input type="checkbox" title="Select all on this page" checked={d.rows.length > 0 && d.rows.every((x) => pick.has(x.id))} onChange={(e) => setPick(e.target.checked ? new Set([...pick, ...pr.map((x) => x.id)]) : new Set())} /></th>}<th>Report no.</th><th>Date</th>{r !== 'client' && <th>Client</th>}{r !== 'vendor' && <th>Vendor</th>}<th>PO</th><th>SKU</th>{internal && <th>Inspector</th>}<th>Result</th>{internal && <th>Email</th>}<th>Download</th></tr></thead><tbody>
          {d.rows.map((x) => <tr key={x.id} className={pick.has(x.id) ? 'sel' : ''}>{mode === 'B' && <td><input type="checkbox" checked={pick.has(x.id)} onChange={() => toggle(x.id)} /></td>}<td><Link className="mono strong" to={'/report/' + x.id}>{x.report_no}</Link>{+x.rev ? <> <span className="badge b-conditional">REV {String(x.rev).padStart(2, '0')}</span></> : null}</td>
            <td>{fdate(x.approved_at, 'd M')}</td>{r !== 'client' && <td>{x.client_code}</td>}{r !== 'vendor' && <td>{x.vendor_name}</td>}<td className="mono">{x.po_no}</td><td><div className="skucell"><Thumb src={x.img} size={40} alt={x.sku} /><span className="mono">{x.sku}</span></div></td>{internal && <td>{x.insp_code}</td>}
            <td><Result r={x.final_result} />{x.concession_status && <><br /><small>concession {x.concession_status.toLowerCase()}</small></>}</td>
            {internal && <td>{+x.emails ? <Badge s="SENT" /> : <span className="muted">—</span>}</td>}
            <td><span className="actions" style={{ flexWrap: 'nowrap' }}><a className="btn sm" target="_blank" rel="noreferrer" href={pdfUrl({ item: x.id })}>Design PDF</a>
              {['EMAILED', 'CLOSED', 'APPROVED'].includes(x.jstatus) && <a className="btn sm" target="_blank" rel="noreferrer" href={pdfUrl({ job: x.jid })}>PO PDF{+x.nsku > 1 ? ` (${x.nsku})` : ''}</a>}</span></td></tr>)}
        </tbody></table></div>}</Paged>}
      </div>
      <div className={'grid mt ' + (d.revisions.length ? 'g-73' : '')}>
        {!!d.revisions.length && <div className="card" style={{ margin: 0 }}><h2 className="mb">Revision &amp; audit (latest)</h2><table className="t"><thead><tr><th>Report</th><th>Rev</th><th>Changed by</th><th>Field</th><th>Old → New</th><th>Time</th></tr></thead><tbody>
          {d.revisions.map((a) => <tr key={a.id}><td className="mono">{a.report_no}</td><td>{String(a.rev).padStart(2, '0')}</td><td>{a.uname || 'System'}</td><td>{a.field}</td><td>{(a.old_val ?? '—') + ' → ' + (a.new_val ?? '')}</td><td>{fdt(a.created_at)}</td></tr>)}</tbody></table>
          <p className="muted" style={{ fontSize: 12 }}>Unlock needs Super Admin + reason; old PDF kept, new PDF re-sent.</p></div>}
        <div className="grid g2" style={{ gap: 12, alignContent: 'start' }}>
          <Kpi label="Reports in range" value={s.n} icon="file" /><Kpi label="Pass" value={s.pass} sub={pct(s.pass, s.n, 0)} tone="ok" icon="check" />
          <Kpi label="Fail" value={s.fail} sub={s.cond + ' conditional'} tone="bad" icon="x" />
          {internal ? <Kpi label="Emails sent" value={d.emails} sub={d.pos ? (d.emails / d.pos).toFixed(1) + ' per PO' : ''} tone="gold" icon="mail" /> : <Kpi label="Rejection %" value={s.rate.toFixed(1) + '%'} sub={num(s.rej) + ' pcs'} tone="gold" icon="chart" />}
        </div>
      </div>
    </>; }}</Loader>
  </Page>;
}
