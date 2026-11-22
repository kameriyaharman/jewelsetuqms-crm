import { TodaySummaryCard } from './DailySummary';
import { Link } from 'react-router-dom';
import { Page } from '../app/Layout';
import { useAuth } from '../app/auth';
import { pdfUrl } from '../app/api';
import { Badge, Empty, HBars, Icon, Kpi, Loader, Result, useApi, Paged } from '../app/ui';
import { fdate, num } from '../app/util';

const done = (s) => ['EMAILED', 'CLOSED'].includes(s);

export function ClientDash() {
  const { me } = useAuth(); const q = useApi('client/dash');
  return <Loader q={q}>{(d) => <Page title={'Client Portal · ' + d.client.name} sub="Live QC across all your mapped vendors"
    actions={me.flags.enable_client_qc_request && <Link className="btn pri" to="/request/new"><Icon n="plus" /> Request QC</Link>}>
    <div className="grid g4 mb">
      <Kpi label="Mapped vendors" value={String(d.vendors).padStart(2, '0')} icon="factory" /><Kpi label="Active QC" value={String(d.active).padStart(2, '0')} sub="requests in progress" tone="info" icon="clip" />
      <Kpi label="Avg rejection" value={d.stats.rate.toFixed(1) + '%'} sub={num(d.stats.insp) + ' pcs inspected'} tone="gold" icon="chart" />
      <Kpi label="Approvals pending" value={String(d.concessions.length + d.holds).padStart(2, '0')} sub={`${d.concessions.length} concession · ${d.holds} SKU hold`} tone={d.concessions.length + d.holds ? 'bad' : 'ok'} icon="check" />
    </div>
    <div className="manbanner"><span>📘 <b>QC Manuals &amp; Instructions</b> — share brand manuals, SOPs and special PO notes with your vendors and our inspectors</span><Link className="btn sm pri" to="/manuals">Upload / View QC Manual</Link></div>
    <TodaySummaryCard />
    <div className="grid g-73">
      <div className="card"><div className="card-h"><h2>QC requests across my vendors</h2><Link to="/requests">All</Link></div>
        {!d.jobs.length ? <Empty>No QC requests yet.</Empty> : <Paged rows={d.jobs} per={10} label="requests">{(pr) => <div className="tbl-wrap"><table className="t"><thead><tr><th>Job / Req</th><th>Vendor</th><th>PO no.</th><th>Scope / qty</th><th>QC date</th><th>Status</th></tr></thead><tbody>
          {pr.map((j) => <tr key={j.id}><td><Link className="strong" to={'/request/' + j.id}>{j.job_no || j.req_no}</Link></td><td>{j.vendor_name}</td><td className="mono">{j.po_no}</td>
            <td>{j.scope === 'SAMPLE' ? 'Sample' : j.bulk_mode === 'AQL' ? 'Bulk AQL' : 'Bulk'} / {num(j.qty)}</td><td>{fdate(j.scheduled_date || j.requested_date, 'd M')}</td>
            <td>{done(j.status) ? <><Result r={j.res} /> <a className="btn sm" target="_blank" rel="noreferrer" href={pdfUrl({ job: j.id })}>PDF</a></> : <Badge s={j.status} />}</td></tr>)}</tbody></table></div>}</Paged>}
      </div>
      <div>
        {!!d.concessions.length && (() => { const c = d.concessions[0]; const x = c.dev[0]; return <div className="card" style={{ borderColor: '#efc3ca' }}><div className="card-h"><h2>Concession approval needed</h2><Badge s="CONDITIONAL" /></div>
          <div className="kv"><div><small>Report</small><b className="mono">{c.report_no}</b></div><div><small>Vendor</small><b>{c.vendor_name}</b></div>
            <div><small>Issue</small><b>{x ? `${x.param} ${x.actual} vs ${x.spec} (${x.dev})` : 'Deviation'}</b></div><div><small>Qty</small><b>{num(c.inspected_qty)} pcs</b></div></div>
          <div className="actions mt"><Link className="btn pri" to="/approvals">Review &amp; decide</Link>{d.concessions.length > 1 && <span className="muted">+{d.concessions.length - 1} more</span>}</div></div>; })()}
        {!!d.holds && <div className="card"><div className="note rose"><b>{d.holds} SKU(s) on hold</b> — the inspector needs your confirmation before continuing. <Link to="/approvals">Respond →</Link></div></div>}
        <div className="card"><div className="card-h"><h2>Vendor rejection % ({d.rates_label})</h2><Link to="/analytics">Analytics</Link></div>
          {d.vendor_rates.length ? <HBars data={Object.fromEntries(d.vendor_rates.map((v) => [v.vendor, v.rate]))} suffix="%" /> : <Empty>No reports yet.</Empty>}</div>
      </div>
    </div>
  </Page>}</Loader>;
}

export function VendorDash() {
  const { me } = useAuth(); const q = useApi('vendor/dash'); const can = me.flags.enable_vendor_qc_request;
  return <Loader q={q}>{(d) => <Page title={'Vendor Portal · ' + d.vendor.name} sub="Raise QC requests, track inspection dates and download your reports"
    actions={can && <Link className="btn pri" to="/request/new"><Icon n="plus" /> New QC Request</Link>}>
    <div className="grid g4 mb">
      <Kpi label="Submitted" value={String(d.counts.submitted).padStart(2, '0')} sub="waiting for schedule" tone="warn" icon="inbox" />
      <Kpi label="Scheduled" value={String(d.counts.scheduled).padStart(2, '0')} tone="info" icon="cal" />
      <Kpi label="In progress" value={String(d.counts.progress).padStart(2, '0')} tone="gold" icon="clock" />
      <Kpi label="Completed" value={d.counts.completed} sub={d.rate.toFixed(1) + '% rejection'} tone="ok" icon="check" />
    </div>
    <div className="manbanner"><span>📘 <b>QC Manuals &amp; Instructions</b> — client QC manuals for you + upload your factory spec sheet / packaging guide</span><Link className="btn sm pri" to="/manuals">Upload / View QC Manual</Link></div>
    <TodaySummaryCard />
    {!!d.holds && <div className="note ruby mb"><b>{d.holds} design(s) held for your confirmation</b> — the inspector is waiting. <Link to="/approvals">Respond now →</Link></div>}
    <div className="grid g-73">
      <div className="card"><div className="card-h"><h2>My QC requests</h2><div className="actions">{can && <Link className="btn sm" to="/import">Bulk Excel</Link>}<Link to="/requests">All</Link></div></div>
        {!d.jobs.length ? <Empty>No QC requests yet.</Empty> : <Paged rows={d.jobs} per={10} label="requests">{(pr) => <div className="tbl-wrap"><table className="t"><thead><tr><th>Req ID</th><th>Client</th><th>PO no.</th><th>QC scope</th><th>Req. date</th><th>Status</th></tr></thead><tbody>
          {pr.map((j) => <tr key={j.id}><td><Link className="strong" to={'/request/' + j.id}>{done(j.status) ? j.job_no || j.req_no : j.req_no}</Link></td><td>{j.client_code}</td><td className="mono">{j.po_no}</td>
            <td>{j.scope_label}</td><td>{fdate(j.requested_date, 'd M')}</td><td>{done(j.status) ? <><Result r={j.res} /> <a className="btn sm" target="_blank" rel="noreferrer" href={pdfUrl({ job: j.id })}>PDF</a></> : <Badge s={j.status} />}</td></tr>)}</tbody></table></div>}</Paged>}
        <p className="muted" style={{ fontSize: 12, marginBottom: 0 }}>You see only your own requests. {!can && '“New QC request” appears when JEWELSETU enables self-service requests — until then JEWELSETU Back Office creates them for you.'}</p>
      </div>
      <div>
        {d.arrivals?.length > 0 && <div className="card" style={{ borderColor: '#9cc3e6' }}><h2 className="mb">Inspector at your factory today</h2>{d.arrivals.map((a, i) => <div key={i} style={{ padding: '6px 0', borderBottom: '1px solid var(--line)', fontSize: 13 }}>
          {a.out_at ? '⚪' : '🟢'} <b>Inspector {a.emp_code} ({a.name})</b> {a.out_at ? 'visited' : 'arrived at factory'} at <b>{new Date(a.at.replace(' ', 'T')).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</b>{a.out_at ? ' · left ' + new Date(a.out_at.replace(' ', 'T')).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : ''}
          <br /><small className="muted">PO {a.po_no}{+a.verified ? ' · GPS verified at your factory gate' : ''}{a.phone ? ' · ' + a.phone : ''}</small></div>)}</div>}
        <div className="card"><h2 className="mb">Upcoming inspection</h2>{!d.next ? <Empty>Nothing scheduled.</Empty> : <>
          <div className="kv"><div><small>Request</small><b>{d.next.req_no} · {d.next.client_code}</b></div><div><small>Date</small><b>{fdate(d.next.scheduled_date)} · {d.next.time_slot}</b></div>
            <div><small>Inspector</small><b>{d.next.emp_code ? `${d.next.emp_code} · ${d.next.iname}` : 'To be assigned'}</b></div><div><small>Location</small><b>{d.next.location}</b></div></div>
          {d.next.emp_code && <p className="note mt" style={{ fontSize: 12 }}>Gate-entry details sent on notification. Inspector mobile: {d.next.phone}</p>}</>}</div>
        <div className="card"><h2 className="mb">Latest result</h2>{!d.latest ? <Empty>No reports yet.</Empty> :
          <div style={{ display: 'flex', gap: 12, alignItems: 'center' }}><Result r={d.latest.final_result} /><div><b className="mono">{d.latest.report_no}</b><br /><small>{num(d.latest.rejected_qty)} pcs rejected / repair{d.latest.top ? ' · ' + d.latest.top : ''}</small></div>
            <a className="btn sm right" target="_blank" rel="noreferrer" href={pdfUrl({ item: d.latest.id })}>PDF</a></div>}</div>
      </div>
    </div>
  </Page>}</Loader>;
}
