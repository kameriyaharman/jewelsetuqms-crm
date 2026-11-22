import { Fragment, useEffect, useMemo, useState } from 'react';
import { Link, useLocation, useSearchParams } from 'react-router-dom';
import { Page } from '../app/Layout';
import { apiUrl } from '../app/api';
import { useAuth } from '../app/auth';
import { Badge, Empty, HBars, Icon, Kpi, Loader, Sel, useApi, Paged, usePaged, Pager } from '../app/ui';
import { fdate, num } from '../app/util';

const TABS = [['client', 'Client-wise MIS & billing'], ['vendor', 'Vendor performance'], ['team', 'Team / inspector productivity'], ['sku', 'Design / SKU register'], ['defects', 'Defect Pareto & root cause']];
const HASH = { sku: 'sku', defects: 'defects', pareto: 'defects', vendors: 'vendor', clients: 'client', team: 'team' };
const p2 = (v) => (v == null ? '—' : Number(v).toFixed(2));
const TREND = { better: ['▼ Improving', 'ok-t'], worse: ['▲ Worse', 'bad-t'], same: ['= Stable', ''], new: ['New', 'muted'] };

/** Multi-dimensional MIS & business intelligence hub: 6 global filters, 5 views, Excel / PDF exports. */
export default function Analytics() {
  const { me } = useAuth(); const internal = !['client', 'vendor'].includes(me.user.role);
  const [sp, setSp] = useSearchParams(); const loc = useLocation();
  // dashboard links: ?period=YYYY-MM#sku → that month, SKU tab
  useEffect(() => {
    const per = sp.get('period'); const h = HASH[(loc.hash || '').slice(1)];
    if (per || (h && !sp.get('tab'))) {
      const n = new URLSearchParams(sp); n.delete('period');
      if (per && /^\d{4}-\d{2}$/.test(per)) { const [y, m] = per.split('-').map(Number); n.set('preset', 'custom'); n.set('from', `${per}-01`); n.set('to', `${per}-${String(new Date(y, m, 0).getDate()).padStart(2, '0')}`); }
      if (h) n.set('tab', h);
      setSp(n, { replace: true });
    } // eslint-disable-next-line
  }, []);
  const tab = sp.get('tab') || 'client';
  const F = { preset: sp.get('preset') || 'this', from: sp.get('from') || '', to: sp.get('to') || '', client: sp.get('client') || '', vendor: sp.get('vendor') || '', insp: sp.get('insp') || '', scope: sp.get('scope') || '', q: sp.get('q') || '' };
  const q = useApi('mis', F);
  const setP = (o) => { const n = new URLSearchParams(sp); Object.entries(o).forEach(([k, v]) => (v ? n.set(k, v) : n.delete(k))); setSp(n); };
  const [qs, setQs] = useState(F.q); const [exp, setExp] = useState(false);
  const xl = (kind) => apiUrl('mis/export', { ...F, kind, tab });
  return <Page title="Analytics · MIS & Business Intelligence" sub={q.data ? q.data.label : 'Client, vendor, team, design and defect views — filtered together'}
    actions={<div style={{ position: 'relative' }}><button className="btn gold" onClick={() => setExp(!exp)}><Icon n="download" /> Export ▾</button>
      {exp && <div className="menu" onMouseLeave={() => setExp(false)}>
        <a href={xl('view')} onClick={() => setExp(false)}><b>Export current view</b><small>{TABS.find((t) => t[0] === tab)[1]} · Excel .xlsx</small></a>
        <a href={xl('book')} onClick={() => setExp(false)}><b>Complete MIS workbook</b><small>5 sheets: client, vendor, team, SKU register, defects · Excel</small></a>
        <a href={xl('pdf')} onClick={() => setExp(false)}><b>Executive summary (PDF)</b><small>Branded summary to send to the client</small></a></div>}</div>}>
    <div className="card filters" style={{ padding: 14 }}>
      <div className="fbar">
        <div><label>Date range</label><Sel value={F.preset} onChange={(v) => setP({ preset: v, from: v === 'custom' ? (F.from || new Date().toISOString().slice(0, 8) + '01') : '', to: v === 'custom' ? F.to : '' })}
          options={[['this', 'This month'], ['last', 'Last month'], ['week', 'This week'], ['ytd', 'This year'], ['custom', 'Custom range']]} /></div>
        {F.preset === 'custom' && <><div><label>From</label><input type="date" value={F.from} onChange={(e) => setP({ from: e.target.value })} /></div><div><label>To</label><input type="date" value={F.to} onChange={(e) => setP({ to: e.target.value })} /></div></>}
        {q.data?.opts.clients.length > 0 && <div><label>Client</label><Sel value={F.client} onChange={(v) => setP({ client: v })} options={q.data.opts.clients.map((c) => [c.id, c.code + ' · ' + c.name])} blank="All clients" /></div>}
        <div><label>Vendor</label><Sel value={F.vendor} onChange={(v) => setP({ vendor: v })} options={(q.data?.opts.vendors || []).map((v) => [v.id, v.name])} blank="All vendors" /></div>
        {internal && <div><label>QC inspector</label><Sel value={F.insp} onChange={(v) => setP({ insp: v })} options={(q.data?.opts.inspectors || []).map((u) => [u.id, u.emp_code + ' · ' + u.name])} blank="All inspectors" /></div>}
        <div><label>QC scope / type</label><Sel value={F.scope} onChange={(v) => setP({ scope: v })} options={Object.entries(q.data?.opts.scopes || {})} blank="All" /></div>
        <form onSubmit={(e) => { e.preventDefault(); setP({ q: qs.trim() }); }}><label>SKU / PO search</label><div style={{ display: 'flex', gap: 6 }}><input type="search" value={qs} onChange={(e) => setQs(e.target.value)} placeholder="Design no. / PO no." /><button className="btn sm">Go</button></div></form>
        {(F.client || F.vendor || F.insp || F.scope || F.q || F.preset !== 'this') && <div style={{ alignSelf: 'flex-end' }}><button className="btn sm" onClick={() => { setQs(''); setSp({ tab }); }}>Reset filters</button></div>}
      </div></div>
    <Loader q={q}>{(d) => { const k = d.kpi; return <>
      <div className="g5 mb">
        <Kpi label="QC visits (man-days)" value={num(k.visits)} sub={`${k.days} working day(s)`} icon="cal" />
        <Kpi label="Designs / SKUs checked" value={num(k.designs)} sub={`${k.lots} QC lot(s)`} icon="clip" />
        <Kpi label="Inspected pcs" value={num(k.insp)} sub={`of ${num(k.offered)} offered`} tone="info" icon="box" />
        <Kpi label="Rejected / repair" value={num(k.rej)} sub={`${p2(k.rate)}% rejection`} tone="bad" icon="alert" />
        <Kpi label="First pass yield" value={p2(k.fpy) + '%'} sub={`${k.pass} pass · ${k.fail} fail · ${k.cond} cond.`} tone="gold" icon="check" />
      </div>
      <div className="tabs mb">{TABS.filter(([t]) => internal || t !== 'team').map(([t, l]) => <a key={t} href="#" className={tab === t ? 'on' : ''} onClick={(e) => { e.preventDefault(); setP({ tab: t }); }}>{l}</a>)}</div>
      {tab === 'client' && <ClientTab d={d} />}
      {tab === 'vendor' && <VendorTab d={d} />}
      {tab === 'team' && internal && <TeamTab d={d} />}
      {tab === 'sku' && <SkuTab d={d} />}
      {tab === 'defects' && <DefectTab d={d} />}
    </>; }}</Loader>
  </Page>;
}

function ClientTab({ d }) {
  const k = d.kpi;
  return <div className="card"><div className="card-h"><h2>Client-wise MIS &amp; billing summary</h2><span className="muted">QC visit = date + vendor + client + inspector</span></div>
    {!d.clients.length ? <Empty>No inspections in this range.</Empty> : <div className="tbl-wrap"><table className="t"><thead><tr><th>Client</th><th className="num">Active vendors</th><th className="num">QC visits</th><th className="num">Designs / SKUs</th><th className="num">Offered</th><th className="num">Inspected</th><th className="num">Accepted</th><th className="num">Rej / repair</th><th className="num">Rejection %</th><th className="num">FPY %</th></tr></thead><tbody>
      {d.clients.map((c) => <tr key={c.id}><td><b>{c.code}</b> <small>{c.name}</small></td><td className="num" title={c.vendor_names}>{c.vendors}</td><td className="num"><b>{c.visits}</b></td><td className="num">{num(c.designs)}</td><td className="num">{num(c.offered)}</td><td className="num">{num(c.insp)}</td><td className="num">{num(c.acc)}</td><td className="num">{num(c.rej)}</td>
        <td className={'num ' + (c.rate > 5 ? 'bad-t' : '')}>{p2(c.rate)}</td><td className="num">{p2(c.fpy)}</td></tr>)}
      <tr className="tot"><td><b>Total</b></td><td></td><td className="num"><b>{k.visits}</b></td><td className="num"><b>{num(k.designs)}</b></td><td className="num"><b>{num(k.offered)}</b></td><td className="num"><b>{num(k.insp)}</b></td><td className="num"><b>{num(k.acc)}</b></td><td className="num"><b>{num(k.rej)}</b></td><td className="num"><b>{p2(k.rate)}</b></td><td className="num"><b>{p2(k.fpy)}</b></td></tr>
    </tbody></table></div>}</div>;
}

function VendorTab({ d }) {
  const [open, setOpen] = useState(null);
  return <><div className="card"><div className="card-h"><h2>Vendor-wise performance &amp; design count</h2><span className="muted">Click a vendor to see its checked designs · trend vs previous period of same length</span></div>
    {!d.vendors.length ? <Empty>No inspections in this range.</Empty> : <div className="tbl-wrap"><table className="t"><thead><tr><th>Vendor</th><th>Mapped clients</th><th className="num">QC visits</th><th className="num">Unique designs</th><th className="num">Inspected</th><th className="num">Pass</th><th className="num">Fail</th><th className="num">Cond.</th><th className="num">Rej / repair</th><th className="num">Rej %</th><th>Top recurring defect</th><th>Trend</th></tr></thead><tbody>
      {d.vendors.map((v) => <Fragment key={v.id}><tr className="rowlink" onClick={() => setOpen(open === v.id ? null : v.id)}>
        <td><b style={{ color: 'var(--rose-d)' }}>{open === v.id ? '▾' : '▸'} {v.name}</b></td><td><small>{v.clients || '—'}</small></td><td className="num">{v.visits}</td><td className="num"><b>{v.designs}</b></td><td className="num">{num(v.insp)}</td><td className="num ok-t">{v.pass}</td><td className="num bad-t">{v.fail}</td><td className="num">{v.cond}</td><td className="num">{num(v.rej)}</td>
        <td className={'num ' + (v.rate > 5 ? 'bad-t' : '')}><b>{p2(v.rate)}</b></td><td style={{ fontSize: 12.5 }}>{v.top}</td><td className={TREND[v.trend][1]} title={v.prev_rate != null ? `Previous: ${p2(v.prev_rate)}%` : ''}>{TREND[v.trend][0]}</td></tr>
        {open === v.id && <tr className="sub"><td colSpan={12}><SkuTable rows={d.register.filter((r) => r.vendor === v.name)} compact /></td></tr>}</Fragment>)}
    </tbody></table></div>}</div>
    {d.vendors.length > 0 && <div className="card"><div className="card-h"><h2>Rejection % by vendor</h2></div><HBars data={Object.fromEntries(d.vendors.map((v) => [v.name, v.rate]))} suffix="%" /></div>}</>;
}

function TeamTab({ d }) {
  return <><div className="card"><div className="card-h"><h2>Team / inspector productivity &amp; accuracy</h2></div>
    {!d.inspectors.length ? <Empty>No inspections in this range.</Empty> : <div className="tbl-wrap"><table className="t"><thead><tr><th>Inspector</th><th className="num">Factory visits</th><th className="num">Designs / SKUs</th><th className="num">Pcs checked</th><th className="num">Avg pcs / day</th><th className="num">Avg report TAT</th><th className="num">Returned by manager</th><th className="num">First-time approval</th></tr></thead><tbody>
      {d.inspectors.map((u) => <tr key={u.id}><td><b className="mono">{u.code}</b> {u.name}</td><td className="num"><b>{u.visits}</b></td><td className="num">{u.designs}</td><td className="num">{num(u.insp)}</td><td className="num">{num(u.avg_day)}</td><td className="num">{u.tat != null ? u.tat + ' h' : '—'}</td><td className={'num ' + (u.returned ? 'bad-t' : '')}>{u.returned}</td><td className="num">{p2(u.fta)}%</td></tr>)}
    </tbody></table></div>}</div>
    <div className="card"><div className="card-h"><h2>Daily activity log</h2><span className="muted">{d.daily.length} visit(s)</span></div>
      {!d.daily.length ? <Empty>No activity.</Empty> : <Paged rows={d.daily} per={25} label="visits">{(pr) => <div className="tbl-wrap" style={{ maxHeight: 520 }}><table className="t"><thead><tr><th>Date</th><th>Inspector</th><th>Vendor</th><th>Client</th><th>PO(s)</th><th className="num">Designs</th><th className="num">Pcs checked</th><th className="num">Accepted</th><th className="num">Rej / repair</th></tr></thead><tbody>
        {pr.map((x, i) => <tr key={i}><td>{fdate(x.d, 'd M Y')}</td><td>{x.insp}</td><td>{x.vendor}</td><td>{x.client}</td><td className="mono" style={{ fontSize: 12 }}>{x.pos}</td><td className="num">{x.skus}</td><td className="num"><b>{num(x.pcs)}</b></td><td className="num">{num(x.acc)}</td><td className="num">{num(x.rej)}</td></tr>)}
      </tbody></table></div>}</Paged>}</div></>;
}

function SkuTable({ rows, compact }) {
  const T = useMemo(() => ({ designs: new Set(rows.map((r) => r.sku)).size, order: rows.reduce((a, r) => a + r.order, 0), offered: rows.reduce((a, r) => a + r.offered, 0), insp: rows.reduce((a, r) => a + r.insp, 0), acc: rows.reduce((a, r) => a + r.acc, 0), rej: rows.reduce((a, r) => a + r.rej, 0) }), [rows]);
  const pg = usePaged(rows, compact ? 25 : 50);
  if (!rows.length) return <Empty>No designs.</Empty>;
  return <><div className="tbl-wrap" style={{ maxHeight: compact ? 360 : 620 }}><table className="t"><thead><tr><th>Date</th><th>Job card</th><th>Report no.</th>{!compact && <><th>Client</th><th>Vendor</th></>}<th>PO</th><th>SKU / design</th><th>Product</th><th>QC scope</th><th className="num">Order</th><th className="num">Offered</th><th className="num">Inspected</th><th className="num">Accepted</th><th className="num">Rej / repair</th><th>Result</th><th>Inspector</th></tr></thead><tbody>
    {pg.rows.map((r) => <tr key={r.id}><td style={{ whiteSpace: 'nowrap' }}>{fdate(r.d, 'd M')}</td><td><Link to={'/request/' + r.job_id} className="mono">{r.job_no}</Link></td><td>{r.report_no ? <Link to={'/report/' + r.id} className="mono" style={{ fontSize: 12 }}>{r.report_no}</Link> : '—'}</td>
      {!compact && <><td>{r.client}</td><td>{r.vendor}</td></>}<td className="mono">{r.po}</td><td><b className="mono">{r.sku}</b>{r.lot > 1 && <small> · lot {r.lot}</small>}</td><td>{r.product}</td><td><small>{r.scope}</small></td>
      <td className="num">{num(r.order)}</td><td className="num">{num(r.offered)}</td><td className="num">{num(r.insp)}</td><td className="num">{num(r.acc)}</td><td className="num">{num(r.rej)}</td><td><Badge s={r.result} /></td><td><small>{r.inspector}</small></td></tr>)}
    <tr className="tot"><td colSpan={compact ? 4 : 6}><b>Grand total · {T.designs} design(s) · {rows.length} lot(s)</b></td><td colSpan={2}></td><td className="num"><b>{num(T.order)}</b></td><td className="num"><b>{num(T.offered)}</b></td><td className="num"><b>{num(T.insp)}</b></td><td className="num"><b>{num(T.acc)}</b></td><td className="num"><b>{num(T.rej)}</b></td><td colSpan={2}></td></tr>
  </tbody></table></div><Pager pg={pg} label="lots" /></>;
}
function SkuTab({ d }) {
  return <div className="card"><div className="card-h"><h2>Design / SKU-wise master register</h2><span className="muted">Every inspected lot, day by day</span></div><SkuTable rows={d.register} /></div>;
}

function DefectTab({ d }) {
  const D = d.defects;
  if (!D.types.length) return <div className="card"><Empty>No defects recorded in this range.</Empty></div>;
  const Mx = ({ title, rows }) => <div className="card"><div className="card-h"><h2>{title}</h2></div><div className="tbl-wrap"><table className="t"><thead><tr><th>{title.startsWith('Vendor') ? 'Vendor' : 'Product'}</th>{D.cats.map((c) => <th key={c} className="num" style={{ whiteSpace: 'normal', minWidth: 90 }}>{c}</th>)}<th className="num">Total</th></tr></thead><tbody>
    {rows.map((r) => { const mx = Math.max(...D.cats.map((c) => r[c])); return <tr key={r.name}><td><b>{r.name}</b></td>{D.cats.map((c) => <td key={c} className="num" style={r[c] && r[c] === mx ? { background: '#fde8eb', fontWeight: 700 } : {}}>{r[c] ? num(r[c]) : '·'}</td>)}<td className="num"><b>{num(r.total)}</b></td></tr>; })}
  </tbody></table></div></div>;
  return <>
    <div className="grid g2 mb"><div className="card" style={{ margin: 0 }}><div className="card-h"><h2>Defect categories</h2><span className="muted">{num(D.total)} pcs</span></div><HBars data={D.cat_totals} /></div>
      <div className="card" style={{ margin: 0 }}><div className="card-h"><h2>Top defects (Pareto)</h2></div><HBars data={Object.fromEntries(D.types.slice(0, 8).map((t) => [t.type, t.total]))} /></div></div>
    <div className="card"><div className="card-h"><h2>Defect type × severity</h2><span className="muted">Pareto — cumulative % shows the vital few</span></div><div className="tbl-wrap"><table className="t"><thead><tr><th>#</th><th>Category</th><th>Defect type</th><th className="num">Critical</th><th className="num">Major</th><th className="num">Minor</th><th className="num">Total pcs</th><th className="num">%</th><th className="num">Cum %</th><th>Most in vendor</th><th>Most in product</th></tr></thead><tbody>
      {D.types.map((t, i) => <tr key={i} style={t.cum <= 80 || i === 0 ? { background: '#fffaf0' } : {}}><td>{i + 1}</td><td>{t.category}</td><td><b>{t.type}</b></td><td className={'num ' + (t.CRITICAL ? 'bad-t' : '')}>{t.CRITICAL || '·'}</td><td className="num">{t.MAJOR || '·'}</td><td className="num">{t.MINOR || '·'}</td><td className="num"><b>{num(t.total)}</b></td><td className="num">{t.pct.toFixed(1)}</td><td className="num">{t.cum.toFixed(1)}</td><td>{t.top_vendor}</td><td>{t.top_product}</td></tr>)}
    </tbody></table></div></div>
    <Mx title="Vendor × defect category" rows={D.by_vendor} />
    <Mx title="Product category × defect category" rows={D.by_product} />
  </>;
}
