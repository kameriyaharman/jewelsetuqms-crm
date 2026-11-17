import { Link, useSearchParams } from 'react-router-dom';
import { Page } from '../app/Layout';
import { useAuth } from '../app/auth';
import { post } from '../app/api';
import { Badge, Empty, Icon, Loader, Sel, useAction, useApi, useForm } from '../app/ui';
import { fdate, num } from '../app/util';

const TABS = [['ALL', 'All'], ['REQUESTED', 'Requested'], ['DRAFT', 'Draft'], ['SCHEDULED', 'Scheduled'], ['ASSIGNED', 'Assigned'], ['IN_PROGRESS', 'In progress'], ['SUBMITTED', 'Submitted'], ['APPROVED', 'Approved'], ['EMAILED', 'Reported'], ['CLOSED', 'Closed']];

export default function Requests() {
  const { me, refresh } = useAuth(); const r = me.user.role; const internal = ['super_admin', 'back_office', 'qc_manager'].includes(r);
  const [sp, setSp] = useSearchParams(); const st = sp.get('st') || 'ALL'; const sel = Number(sp.get('id')) || null;
  const params = { st, src: sp.get('src'), client: sp.get('client'), vendor: sp.get('vendor'), q: sp.get('q') };
  const q = useApi('requests', params); const opts = useApi('options');
  const setP = (k, v) => { const n = new URLSearchParams(sp); v ? n.set(k, v) : n.delete(k); n.delete('id'); setSp(n); };
  const canNew = ['super_admin', 'back_office'].includes(r) || (r === 'vendor' && me.flags.enable_vendor_qc_request) || (r === 'client' && me.flags.enable_client_qc_request);
  const canSchedule = ['super_admin', 'back_office'].includes(r);
  const title = r === 'vendor' ? 'My QC Requests' : r === 'client' ? 'QC requests across my vendors' : 'QC Requests';
  const selRow = q.data?.rows.find((x) => x.id === sel);
  return <Page title={title} sub={internal ? 'Requests from Back Office, Excel, Vendor portal and Client portal land in one queue; Back Office confirms date & scope' : ''}
    actions={<>{canSchedule && <Link className="btn" to="/import"><Icon n="upload" /> Import Excel</Link>}{canNew && <Link className="btn pri" to="/request/new"><Icon n="plus" /> New QC Request</Link>}</>}>
    <Loader q={q}>{(d) => <div className={'grid ' + (canSchedule && selRow ? 'g-82' : '')}>
      <div className="card">
        <div className="tabs">{TABS.map(([k, l]) => <a key={k} href="#" className={st === k ? 'on' : ''} onClick={(e) => { e.preventDefault(); setP('st', k === 'ALL' ? '' : k); }}>{l} <b>{k === 'ALL' ? d.total : d.counts[k] || 0}</b></a>)}</div>
        <form className="filters" onSubmit={(e) => { e.preventDefault(); setP('q', e.target.q.value); }}>
          <input type="search" name="q" defaultValue={sp.get('q') || ''} placeholder="PO / REQ / SKU…" />
          {internal && <Sel value={sp.get('src')} onChange={(v) => setP('src', v)} options={['Back Office', 'Excel import', 'Vendor portal', 'Client portal']} blank="Source: All" />}
          {opts.data && r !== 'client' && <Sel value={sp.get('client')} onChange={(v) => setP('client', v)} options={opts.data.clients.map((c) => [c.id, c.code])} blank="Client: All" />}
          {opts.data && r === 'client' && <Sel value={sp.get('vendor')} onChange={(v) => setP('vendor', v)} options={opts.data.vendors.map((c) => [c.id, c.name])} blank="Vendor: All mapped" />}
          <button className="btn sm">Filter</button></form>
        {!d.rows.length ? <Empty>No QC requests match.</Empty> : <div className="tbl-wrap"><table className="t"><thead><tr><th>Req / Job</th>{internal && <th>Source</th>}{r !== 'client' && <th>Client</th>}{r !== 'vendor' && <th>Vendor</th>}<th>PO</th><th>Scope</th><th className="num">SKUs / Qty</th><th>QC date</th><th>Status</th></tr></thead><tbody>
          {d.rows.map((j) => <tr key={j.id} className={sel === j.id ? 'sel' : ''}>
            <td><Link className="strong" to={'/request/' + j.id}>{j.req_no}</Link>{j.job_no && <><br /><small className="mono">{j.job_no}</small></>}</td>
            {internal && <td>{j.source}</td>}{r !== 'client' && <td>{j.client_code}</td>}{r !== 'vendor' && <td>{j.vendor_name}</td>}
            <td className="mono">{j.po_no}</td><td>{j.scope_label}</td><td className="num">{j.skus} / {num(j.qty)}</td>
            <td>{fdate(j.scheduled_date || j.requested_date, 'd M')}{!j.scheduled_date && j.requested_date ? <small> (req.)</small> : null}</td>
            <td><Badge s={j.status} />{canSchedule && ['REQUESTED', 'DRAFT'].includes(j.status) && <> <button className="btn sm" onClick={() => setSp((p) => { const n = new URLSearchParams(p); n.set('id', j.id); return n; })}>Schedule</button></>}</td></tr>)}
        </tbody></table></div>}
      </div>
      {canSchedule && selRow && <SchedulePanel key={selRow.id} row={selRow} managers={opts.data?.managers || []} onDone={() => { q.reload(); refresh(); setP('id', ''); }} />}
    </div>}</Loader>
  </Page>;
}

export function SchedulePanel({ row, managers, onDone }) {
  const F = useForm({ date: row.scheduled_date || row.requested_date || '', time: '10:30', manager_id: row.manager_id || managers[0]?.id || '', priority: row.priority || 'Normal', location: row.location || '' });
  const { run, busy } = useAction(); const b = F.bind;
  const go = async (e) => { e.preventDefault(); const r = await run(() => post('job/action', { act: 'schedule', id: row.id, ...F.f })); if (r) onDone(); };
  return <div className="card"><div className="card-h"><h2>Schedule request {row.req_no}</h2><span className="pill">From {String(row.source).toLowerCase()}</span></div>
    <div className="kv mb"><div><small>Client</small><b>{row.client_code}</b></div><div><small>Vendor</small><b>{row.vendor_name}</b></div><div><small>PO</small><b>{row.po_no}</b></div>
      <div><small>Scope</small><b>{row.scope_label} · {row.skus} SKUs</b></div><div><small>Requested date</small><b>{fdate(row.requested_date)}</b></div></div>
    <form onSubmit={go}>
      <div className="fr f"><div><label>Confirmed QC date</label><input type="date" required {...b('date')} /></div><div><label>Time slot</label><input type="time" {...b('time')} /></div></div>
      <div className="fr f"><div><label>QC manager</label><Sel value={F.f.manager_id} onChange={(v) => F.set('manager_id', v)} options={managers.map((m) => [m.id, m.label])} /></div>
        <div><label>Priority</label><Sel value={F.f.priority} onChange={(v) => F.set('priority', v)} options={['Normal', 'High', 'Urgent']} /></div></div>
      <div className="f"><label>QC location</label><input type="text" {...b('location')} /></div>
      <button className="btn pri block" disabled={busy}>CONFIRM &amp; SCHEDULE</button>
      <p className="muted" style={{ fontSize: 12, textAlign: 'center' }}>Status → SCHEDULED · vendor &amp; client notified</p>
    </form>
    <div className="actions"><Link className="btn" to={'/request/' + row.id}>Open full request</Link><Link className="btn" to={'/request/' + row.id + '/edit'}>Edit</Link></div>
  </div>;
}
