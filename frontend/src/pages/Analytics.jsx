import { useEffect } from 'react';
import { Link, useLocation, useSearchParams } from 'react-router-dom';
import { Page } from '../app/Layout';
import { useAuth } from '../app/auth';
import { apiUrl } from '../app/api';
import { Badge, Bars, Empty, HBars, Icon, Kpi, Loader, Sel, useApi } from '../app/ui';
import { num, pct } from '../app/util';

export default function Analytics() {
  const { me } = useAuth(); const r = me.user.role;
  const [sp, setSp] = useSearchParams(); const p = { vendor: sp.get('vendor'), client: sp.get('client'), period: sp.get('period') };
  const q = useApi('analytics', p); const opts = useApi('options');
  const setP = (k, v) => { const n = new URLSearchParams(sp); v ? n.set(k, v) : n.delete(k); setSp(n); };
  const loc = useLocation();
  useEffect(() => { if (q.data && loc.hash) { const el = document.getElementById(loc.hash.slice(1)); if (el) setTimeout(() => el.scrollIntoView({ behavior: 'smooth', block: 'start' }), 80); } }, [q.data, loc.hash]);
  return <Page title="Vendor Quality Analytics" sub="Vendor-wise inspections, rejection rate, top defects, trend and vendor comparison"
    actions={<a className="btn" href={apiUrl('analytics/export', p)}><Icon n="download" /> Export</a>}>
    <Loader q={q}>{(d) => { const s = d.stats; const ml = Object.fromEntries(d.months.map((m) => [m.v, m.l])); const pl = ml[d.period] || d.period;
      const tot = Object.values(d.pareto).reduce((a, b) => a + b, 0); let cum = 0;
      return <>
        <div className="filters card mb" style={{ padding: '12px 16px' }}>
          <div><label>Vendor</label><Sel value={sp.get('vendor')} onChange={(v) => setP('vendor', v)} options={d.vendor_opts.map((v) => [v.id, v.name])} blank="All vendors" /></div>
          {r !== 'client' && opts.data && <div><label>Client</label><Sel value={sp.get('client')} onChange={(v) => setP('client', v)} options={opts.data.clients.map((c) => [c.id, c.code])} blank="All clients" /></div>}
          <div><label>Period</label><Sel value={d.period} onChange={(v) => setP('period', v)} options={d.months.map((m) => [m.v, m.l])} /></div>
        </div>
        <div className="grid g5 mb">
          <Kpi label="Total inspections" value={s.n} icon="clip" /><Kpi label="Pass" value={s.pass} sub={pct(s.pass, s.n, 0)} tone="ok" icon="check" /><Kpi label="Fail" value={s.fail} sub={s.cond + ' conditional'} tone="bad" icon="x" />
          <Kpi label="Total inspected" value={num(s.insp)} sub={num(s.rej) + ' rejected / repair'} tone="info" icon="box" /><Kpi label="Rejection rate" value={s.rate.toFixed(1) + '%'} tone="gold" icon="chart" />
        </div>
        <div className="grid g2 mb">
          <div className="card" style={{ margin: 0 }} id="pareto"><div className="card-h"><h2>Top defects (Pareto) · {pl}</h2><span className="muted">{num(tot)} pcs</span></div>
            {tot ? <><HBars data={Object.fromEntries(Object.entries(d.pareto).slice(0, 7))} /><p className="muted" style={{ fontSize: 12 }}>Cumulative: {Object.entries(d.pareto).slice(0, 4).map(([k, v]) => { cum += v; return `${k} ${Math.round((cum * 100) / tot)}%`; }).join(' · ')}</p></> : <Empty>No defects recorded in this period.</Empty>}</div>
          <div className="card" style={{ margin: 0 }}><div className="card-h"><h2>Rejection % trend</h2></div><Bars data={d.trend} /></div>
        </div>
        <div className="card" id="vendors"><div className="card-h"><h2>Vendor comparison — {pl}</h2></div>
          {!d.vendors.length ? <Empty>No approved inspections in this period.</Empty> : <div className="tbl-wrap"><table className="t"><thead><tr><th>Vendor</th><th>Client</th><th className="num">Inspections</th><th className="num">Inspected pcs</th><th className="num">Rejected</th><th className="num">Rejection %</th><th>Top defect</th><th>Trend</th></tr></thead><tbody>
            {d.vendors.map((v) => <tr key={v.vendor_id}><td className="strong"><a href="#" onClick={(e) => { e.preventDefault(); setP('vendor', String(v.vendor_id)); }}>{v.vendor}</a></td><td>{v.clients}</td>
              <td className="num">{v.n}</td><td className="num">{num(v.insp)}</td><td className="num">{num(v.rej)}</td><td className={'num ' + (v.rate > 5 ? 'bad-t' : '')}><b>{v.rate.toFixed(1)}%</b></td><td>{v.top}</td>
              <td style={{ fontSize: 16 }}>{v.prev == null ? '→' : v.rate > v.prev + 0.1 ? <span className="bad-t">↑</span> : v.rate < v.prev - 0.1 ? <span className="ok-t">↓</span> : '→'}</td></tr>)}</tbody></table></div>}
        </div>
        <div className="card" id="sku"><div className="card-h"><h2>SKU-wise quantity breakdown — {pl}</h2><span className="muted">{d.skus.length} report(s)</span></div>
          {!d.skus.length ? <Empty>No approved inspections in this period.</Empty> : <div className="tbl-wrap" style={{ maxHeight: 460 }}><table className="t"><thead><tr><th>Date</th><th>PO</th><th>SKU / lot</th><th>Vendor</th><th>Client</th><th className="num">Offered</th><th className="num">Inspected</th><th className="num">Accepted</th><th className="num">Rej / repair</th><th>Result</th><th>Report</th></tr></thead><tbody>
            {d.skus.map((x) => <tr key={x.id}><td>{x.date}</td><td>{x.po}</td><td><Link to={'/request/' + x.job_id} className="mono strong">{x.sku}</Link>{x.lot > 1 && <small> · lot {x.lot}</small>}</td><td>{x.vendor}</td><td>{x.client}</td>
              <td className="num">{num(x.ready)}</td><td className="num">{num(x.insp)}</td><td className="num">{num(x.acc)}</td><td className="num">{num(x.rej)}</td><td><Badge s={x.result} /></td><td><Link to={'/report/' + x.id}>{x.report_no}</Link></td></tr>)}
            <tr className="tot"><td colSpan={5}><b>Total</b></td><td className="num"><b>{num(d.skus.reduce((a, x) => a + x.ready, 0))}</b></td><td className="num"><b>{num(d.skus.reduce((a, x) => a + x.insp, 0))}</b></td><td className="num"><b>{num(d.skus.reduce((a, x) => a + x.acc, 0))}</b></td><td className="num"><b>{num(d.skus.reduce((a, x) => a + x.rej, 0))}</b></td><td colSpan={2}></td></tr>
          </tbody></table></div>}</div>
        <div className="card" id="defects"><div className="card-h"><h2>Defect analysis &amp; rejected lots — {pl}</h2><span className="muted">{num(d.rejects.reduce((a, x) => a + x.rej, 0))} pcs</span></div>
          {!d.rejects.length ? <Empty>No rejected / repair pieces in this period.</Empty> : <div className="tbl-wrap" style={{ maxHeight: 460 }}><table className="t"><thead><tr><th>PO</th><th>SKU</th><th>Vendor</th><th className="num">Inspected</th><th className="num">Rej / repair</th><th className="num">%</th><th>Defects found</th><th>Result</th><th>Report</th></tr></thead><tbody>
            {d.rejects.map((x) => <tr key={x.id}><td>{x.po}</td><td className="mono strong">{x.sku}</td><td>{x.vendor}</td><td className="num">{num(x.insp)}</td><td className="num"><b>{num(x.rej)}</b></td><td className="num">{pct(x.rej, x.insp)}</td><td style={{ fontSize: 12.5 }}>{x.defects}</td><td><Badge s={x.result} /></td><td><Link to={'/report/' + x.id}>{x.report_no}</Link></td></tr>)}
          </tbody></table></div>}</div>
        {d.inspectors && <div className="card"><div className="card-h"><h2>Inspector performance</h2></div>
          <table className="t"><thead><tr><th>Inspector</th><th className="num">Assigned SKUs</th><th className="num">Done</th><th className="num">Avg time</th><th className="num">Return %</th><th className="num">1st-time approval</th><th className="num">Pending</th></tr></thead><tbody>
            {d.inspectors.map((u) => <tr key={u.id}><td className="strong">{u.emp_code} {u.name}</td><td className="num">{u.assigned}</td><td className="num">{u.done}</td><td className="num">{u.avg.toFixed(1)} h</td><td className="num">{Math.round(u.ret)}%</td><td className="num">{Math.round(u.first)}%</td><td className="num">{u.pending}</td></tr>)}</tbody></table></div>}
      </>; }}</Loader>
  </Page>;
}
