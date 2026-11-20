import { DailySummaryButton } from './DailySummary';
import { Fragment, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Page } from '../app/Layout';
import { useAuth } from '../app/auth';
import { Badge, Bars, Empty, Icon, Kpi, Loader, useApi, Thumb, InspSt } from '../app/ui';
import { fdate, num, pct } from '../app/util';

export default function Dashboard() {
  const [dv, setDv] = useState(() => { try { return localStorage.getItem('qt_dash_view') || 'vendor'; } catch { return 'vendor'; } });
  const pickDv = (v) => { setDv(v); try { localStorage.setItem('qt_dash_view', v); } catch { /* ignore */ } };
  const q = useApi('dashboard'); const { me } = useAuth(); const r = me.user.role; const nav = useNavigate();
  const ms = new Date(); const from = `${ms.getFullYear()}-${String(ms.getMonth() + 1).padStart(2, '0')}-01`; const per = from.slice(0, 7);
  const canCreate = ['super_admin', 'back_office'].includes(r);
  return <Page title="Dashboard" sub={q.data ? 'Overview for ' + q.data.month_label : ''} actions={<><DailySummaryButton />{canCreate && <Link className="btn gold" to="/import"><Icon n="upload" /> Import Excel</Link>}</>}>
    <Loader q={q}>{(d) => { const m = d.month, p = d.prev; const diff = p.rate ? m.rate - p.rate : 0; return <>
      <div className="g5 mb">
        <Kpi to={`/reports?from=${from}`} label="Total QC reports" value={num(m.n)} sub="This month" icon="clip" />
        <Kpi to={`/reports?from=${from}&res=PASS`} label="Pass" value={num(m.pass)} sub={pct(m.pass, m.n, 0)} tone="ok" icon="check" />
        <Kpi to={`/reports?from=${from}&res=NOTPASS`} label="Fail" value={num(m.fail)} sub={pct(m.fail, m.n, 0) + (m.cond ? ` · ${m.cond} conditional` : '')} tone="bad" icon="x" />
        <Kpi to="/review" label="Pending" value={num(d.pending)} sub={`${d.review} under review`} tone="warn" icon="clock" />
        <Kpi to="/jobs?f=OPEN" label="Open job cards" value={String(d.open_jobs).padStart(2, '0')} sub={d.open_jobs ? `Partial QC · ${num(d.open_bal)} pcs balance` : 'No pending balance'} tone="gold" icon="clip" />
      </div>
      <div className="grid g3 mb">
        <Kpi to={`/analytics?period=${per}#sku`} label="Total inspected pcs" value={num(m.insp)} sub="This month · SKU-wise breakdown" tone="info" icon="box" />
        <Kpi to={`/analytics?period=${per}#defects`} label="Total rejected / repair" value={num(m.rej)} sub="pcs · defect analysis" tone="bad" icon="alert" />
        <Kpi to={`/analytics?period=${per}#vendors`} label="Rejection %" value={m.rate.toFixed(1) + '%'} sub={p.rate ? `${diff <= 0 ? '↓' : '↑'} ${Math.abs(diff).toFixed(1)}% vs ${d.prev_label}` : 'Vendor-wise breakdown'} tone="gold" icon="chart" />
      </div>
      <div className="grid g-73 mb">
        <div className="card"><div className="card-h"><h2>{dv === 'vendor' ? 'Live QC · vendor-wise' : `Today's QC · ${fdate(new Date(), 'd M')}`}</h2>
          <span style={{ display: 'flex', gap: 10, alignItems: 'center' }}><span className="seg"><button className={dv === 'vendor' ? 'on' : ''} onClick={() => pickDv('vendor')}>Vendor-wise view</button><button className={dv === 'job' ? 'on' : ''} onClick={() => pickDv('job')}>Job-wise view</button></span><Link to="/schedule">Schedule</Link></span></div>
          {dv === 'vendor' ? <VendorView groups={d.by_vendor || []} /> : !d.today.length ? <Empty>No QC scheduled for today.</Empty> :
            <div className="tbl-wrap"><table className="t"><thead><tr><th>Job</th><th>Vendor</th><th>Client</th><th>Inspector</th><th>SKUs</th><th>Status</th></tr></thead><tbody>
              {d.today.map((j) => <tr key={j.id} className="rowlink" onClick={() => nav('/request/' + j.id)}><td><div className="skucell"><Thumb src={j.img} size={36} /><Link className="strong" to={'/request/' + j.id}>{j.job_no || j.req_no}</Link></div></td><td>{j.vendor_name}</td><td>{j.client_code}</td>
                <td>{j.insp_code ? `${j.insp_code} · ${j.insp_name.split(' ')[0]}` : <span className="muted">—</span>}</td><td>{j.skus} SKU</td><td><Badge s={j.status} /></td></tr>)}
            </tbody></table></div>}
        </div>
        {d.team_live?.length > 0 && <div className="card"><div className="card-h"><h2>Team live status · today</h2><Link to="/team?f=inspector">Team</Link></div>
          {d.team_live.map((u) => <div key={u.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 8, padding: '7px 0', borderBottom: '1px solid var(--line)' }}>
            <span style={{ fontSize: 13 }}><b className="mono">{u.emp_code}</b> {u.name}<br /><small className="muted">{u.st.sub}</small></span><InspSt st={u.st} small /></div>)}</div>}
        <div className="card"><div className="card-h"><h2>Upcoming QC</h2><Link to="/jobs">All</Link></div>
          {!d.upcoming.length ? <Empty>Nothing upcoming.</Empty> :
            <table className="t"><thead><tr><th>Date</th><th>Job / vendor</th><th>Client</th><th>Status</th></tr></thead><tbody>
              {d.upcoming.map((j) => <tr key={j.id} className="rowlink" title="Open to assign / reschedule" onClick={() => nav('/request/' + j.id)}><td>{fdate(j.d, 'd M')}</td><td><Link to={'/request/' + j.id}>{j.job_no || j.req_no}</Link><br /><small>{j.vendor_name} · PO {j.po_no}</small></td><td>{j.client_code}</td>
                <td>{j.status === 'SCHEDULED' && !j.inspector_id ? <Badge s="UNASSIGNED" /> : <Badge s={j.status} />}</td></tr>)}</tbody></table>}
        </div>
      </div>
      <div className="grid g-73">
        <div className="card"><div className="card-h"><h2>Rejection % — last 6 months</h2></div><Bars data={d.trend} /></div>
        <div className="card"><div className="card-h"><h2>Quick actions</h2></div>
          <div className="grid g2">
            {canCreate && <><Link className="btn" to="/request/new"><Icon n="plus" /> New QC Request</Link><Link className="btn" to="/import"><Icon n="upload" /> Import Excel</Link></>}
            {['super_admin', 'qc_manager'].includes(r) && <><Link className="btn" to="/jobs?f=UNASSIGNED"><Icon n="users" /> Assign QC</Link><Link className="btn" to="/review"><Icon n="eye" /> Pending review ({d.review})</Link></>}
            <Link className="btn" to="/reports"><Icon n="file" /> View Reports</Link><Link className="btn" to="/analytics"><Icon n="chart" /> Analytics</Link>
          </div>
          <div className="note mt">Phase flags — vendor requests: <b>{me.flags.enable_vendor_qc_request ? 'ON' : 'OFF'}</b> · client requests: <b>{me.flags.enable_client_qc_request ? 'ON' : 'OFF'}</b>
            {r === 'super_admin' && <> · <Link to="/settings">change</Link></>}</div>
        </div>
      </div>
    </>; }}</Loader>
  </Page>;
}

/** Open job cards grouped per vendor + client: inspectors, open / inspected today / pending designs, live status; click a row to expand its job cards. */
function VendorView({ groups }) {
  const [open, setOpen] = useState(null); const nav = useNavigate();
  if (!groups.length) return <Empty>No open job cards right now.</Empty>;
  const tone = { ONSITE: 'b-pass', ASSIGNED: 'b-pending', PENDING: 'b-fail' };
  return <div className="tbl-wrap"><table className="t"><thead><tr><th>Vendor (client)</th><th>Inspector</th><th className="num">Total designs</th><th className="num">Inspected today</th><th className="num">Pending</th><th>Live status</th></tr></thead><tbody>
    {groups.map((g) => <Fragment key={g.key}>
      <tr className="rowlink" onClick={() => setOpen(open === g.key ? null : g.key)}>
        <td><div className="skucell"><Thumb src={g.img} size={36} /><span><b>{open === g.key ? '▾' : '▸'} {g.vendor_name}</b><br /><small className="muted">Client {g.client_code} · {g.location} · {g.jobs.length} job card{g.jobs.length > 1 ? 's' : ''}</small></span></div></td>
        <td>{g.insp.length ? g.insp.join(', ') : <span className="muted">— unassigned</span>}</td><td className="num strong">{g.designs}</td><td className="num">{g.today}</td><td className="num">{g.open}</td>
        <td><span className={'badge ' + (tone[g.live.code] || '')}>{g.live.label}</span></td></tr>
      {open === g.key && <tr className="subrow"><td colSpan={6} style={{ padding: 0 }}><table className="t" style={{ margin: 0, background: '#fafbfc' }}><tbody>
        {g.jobs.map((j) => <tr key={j.id} className="rowlink" onClick={() => nav('/request/' + j.id)}><td style={{ paddingLeft: 38 }}><div className="skucell"><Thumb src={j.img} size={30} /><span><Link className="strong" to={'/request/' + j.id}>{j.job_no || j.req_no}</Link><br /><small className="muted mono">{j.sku}</small></span></div></td>
          <td>PO {j.po_no}</td><td>{j.insp_code ? `${j.insp_code} · ${(j.insp_name || '').split(' ')[0]}` : <span className="muted">—</span>}</td><td>{j.scheduled_date ? fdate(j.scheduled_date, 'd M') : '—'}</td><td className="num">{j.designs} design · {j.open_items} open</td><td><Badge s={j.status} /></td></tr>)}
      </tbody></table></td></tr>}
    </Fragment>)}</tbody></table></div>;
}
