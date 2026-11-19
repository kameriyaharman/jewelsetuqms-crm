import { Link, useNavigate } from 'react-router-dom';
import { Page } from '../app/Layout';
import { useAuth } from '../app/auth';
import { Badge, Bars, Empty, Icon, Kpi, Loader, useApi, Thumb, InspSt } from '../app/ui';
import { fdate, num, pct } from '../app/util';

export default function Dashboard() {
  const q = useApi('dashboard'); const { me } = useAuth(); const r = me.user.role; const nav = useNavigate();
  const ms = new Date(); const from = `${ms.getFullYear()}-${String(ms.getMonth() + 1).padStart(2, '0')}-01`; const per = from.slice(0, 7);
  const canCreate = ['super_admin', 'back_office'].includes(r);
  return <Page title="Dashboard" sub={q.data ? 'Overview for ' + q.data.month_label : ''} actions={canCreate && <Link className="btn gold" to="/import"><Icon n="upload" /> Import Excel</Link>}>
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
        <div className="card"><div className="card-h"><h2>Today's QC · {fdate(new Date(), 'd M')}</h2><Link to="/schedule">View schedule</Link></div>
          {!d.today.length ? <Empty>No QC scheduled for today.</Empty> :
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
