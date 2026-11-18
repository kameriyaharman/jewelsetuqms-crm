import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Page } from '../app/Layout';
import { useAuth } from '../app/auth';
import { pdfUrl } from '../app/api';
import { Badge, Empty, Kpi, Loader, Result, Sel, useApi, Thumb } from '../app/ui';
import { fdate, fdt, num, pct } from '../app/util';

export default function Reports() {
  const { me } = useAuth(); const r = me.user.role; const internal = ['super_admin', 'back_office', 'qc_manager'].includes(r);
  const [sp, setSp] = useSearchParams(); const p = Object.fromEntries(sp.entries());
  const q = useApi('reports', p); const opts = useApi('options');
  const [f, setF] = useState(p);
  const set = (k) => (v) => setF((x) => ({ ...x, [k]: typeof v === 'object' ? v.target.value : v }));
  return <Page title="Reports" sub="Search and filter every report by client, vendor, inspector, result and date — view, download or re-email">
    <Loader q={q}>{(d) => { const s = d.stats; return <>
      <div className="card">
        <form className="filters" onSubmit={(e) => { e.preventDefault(); setSp(Object.fromEntries(Object.entries(f).filter(([, v]) => v))); }}>
          {opts.data && r !== 'client' && <div><label>Client</label><Sel value={f.client} onChange={set('client')} options={opts.data.clients.map((c) => [c.id, c.code])} blank="All" /></div>}
          {opts.data && r !== 'vendor' && <div><label>Vendor</label><Sel value={f.vendor} onChange={set('vendor')} options={opts.data.vendors.map((c) => [c.id, c.name])} blank="All" /></div>}
          {opts.data?.inspectors && <div><label>Inspector</label><Sel value={f.insp} onChange={set('insp')} options={opts.data.inspectors.map((c) => [c.id, c.label])} blank="All" /></div>}
          <div><label>PO / SKU / report</label><input type="search" value={f.q || ''} onChange={set('q')} placeholder="Any" /></div>
          <div><label>Result</label><Sel value={f.res} onChange={set('res')} options={[['PASS', 'Pass'], ['FAIL', 'Fail'], ['CONDITIONAL', 'Conditional']]} blank="All" /></div>
          <div><label>From</label><input type="date" value={f.from || d.filters.from} onChange={set('from')} /></div><div><label>To</label><input type="date" value={f.to || d.filters.to} onChange={set('to')} /></div>
          <button className="btn pri">Search</button>
        </form>
        {!d.rows.length ? <Empty>No approved reports for these filters.</Empty> : <div className="tbl-wrap"><table className="t"><thead><tr><th>Report no.</th><th>Date</th>{r !== 'client' && <th>Client</th>}{r !== 'vendor' && <th>Vendor</th>}<th>PO</th><th>SKU</th>{internal && <th>Inspector</th>}<th>Result</th>{internal && <th>Email</th>}<th>Download</th></tr></thead><tbody>
          {d.rows.map((x) => <tr key={x.id}><td><Link className="mono strong" to={'/report/' + x.id}>{x.report_no}</Link>{+x.rev ? <> <span className="badge b-conditional">REV {String(x.rev).padStart(2, '0')}</span></> : null}</td>
            <td>{fdate(x.approved_at, 'd M')}</td>{r !== 'client' && <td>{x.client_code}</td>}{r !== 'vendor' && <td>{x.vendor_name}</td>}<td className="mono">{x.po_no}</td><td><div className="skucell"><Thumb src={x.img} size={40} alt={x.sku} /><span className="mono">{x.sku}</span></div></td>{internal && <td>{x.insp_code}</td>}
            <td><Result r={x.final_result} />{x.concession_status && <><br /><small>concession {x.concession_status.toLowerCase()}</small></>}</td>
            {internal && <td>{+x.emails ? <Badge s="SENT" /> : <span className="muted">—</span>}</td>}
            <td><span className="actions" style={{ flexWrap: 'nowrap' }}><a className="btn sm" target="_blank" rel="noreferrer" href={pdfUrl({ item: x.id })}>Design PDF</a>
              {['EMAILED', 'CLOSED', 'APPROVED'].includes(x.jstatus) && <a className="btn sm" target="_blank" rel="noreferrer" href={pdfUrl({ job: x.jid })}>PO PDF{+x.nsku > 1 ? ` (${x.nsku})` : ''}</a>}</span></td></tr>)}
        </tbody></table></div>}
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
