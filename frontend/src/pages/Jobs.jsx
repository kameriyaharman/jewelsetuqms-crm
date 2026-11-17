import { useEffect } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Page } from '../app/Layout';
import { useAuth } from '../app/auth';
import { post } from '../app/api';
import { Badge, Empty, Icon, Loader, Sel, useAction, useApi, useForm } from '../app/ui';
import { fdate } from '../app/util';

const TABS = [['ALL', 'All'], ['UNASSIGNED', 'Unassigned'], ['ASSIGNED', 'Assigned'], ['IN_PROGRESS', 'In progress'], ['REVIEW', 'Review'], ['CLOSED', 'Reported / closed']];
const OPEN = ['SCHEDULED', 'ASSIGNED', 'REQUESTED', 'IN_PROGRESS'];

export default function Jobs() {
  const { me, refresh } = useAuth(); const canAssign = ['super_admin', 'qc_manager'].includes(me.user.role);
  const [sp, setSp] = useSearchParams(); const f = sp.get('f') || 'ALL';
  const q = useApi('jobs', { f });
  let sel = Number(sp.get('id')) || null;
  if (!sel && q.data) { const u = q.data.rows.find((r) => !r.inspector_id && ['SCHEDULED', 'REQUESTED'].includes(r.status)); sel = u?.id || null; }
  const selRow = q.data?.rows.find((r) => r.id === sel);
  return <Page title="QC Jobs" sub="All jobs by status; assign or reassign an inspector with availability and notifications" actions={<Link className="btn" to="/schedule"><Icon n="cal" /> Week schedule</Link>}>
    <Loader q={q}>{(d) => <div className={'grid ' + (canAssign && selRow && OPEN.includes(selRow.status) ? 'g-82' : '')}>
      <div className="card">
        <div className="tabs">{TABS.map(([k, l]) => <a key={k} href="#" className={f === k ? 'on' : ''} onClick={(e) => { e.preventDefault(); setSp({ f: k }); }}>{l} <b>{d.counts[k] || 0}</b></a>)}</div>
        {!d.rows.length ? <Empty>No jobs.</Empty> : <div className="tbl-wrap"><table className="t"><thead><tr><th>QC job no.</th><th>Client</th><th>Vendor</th><th>PO</th><th>SKUs</th><th>QC date</th><th>Inspector</th><th>Status</th></tr></thead><tbody>
          {d.rows.map((j) => <tr key={j.id} className={sel === j.id ? 'sel' : ''}>
            <td><Link className="strong mono" to={'/request/' + j.id}>{j.job_no}</Link></td><td>{j.client_code}</td><td>{j.vendor_name}</td><td className="mono">{j.po_no}</td><td>{j.skus}</td>
            <td>{fdate(j.scheduled_date, 'd M')} <small>{j.time_slot}</small></td><td>{j.insp_code ? `${j.insp_code} · ${j.insp_name.split(' ')[0]}` : '—'}</td>
            <td>{!j.inspector_id && ['SCHEDULED', 'REQUESTED'].includes(j.status) ? <Badge s="UNASSIGNED" /> : <Badge s={j.status} />}
              {canAssign && OPEN.includes(j.status) && <> <button className="btn sm" onClick={() => setSp({ f, id: j.id })}>{j.inspector_id ? 'Reassign' : 'Assign'}</button></>}</td></tr>)}
        </tbody></table></div>}
        <p className="muted" style={{ fontSize: 12, marginTop: 12 }}>Lifecycle: Requested → Scheduled → Assigned → In progress → Submitted → Under review → Approved → PDF generated → Emailed → Closed</p>
      </div>
      {canAssign && selRow && OPEN.includes(selRow.status) && <AssignPanel key={selRow.id} id={selRow.id} onDone={() => { q.reload(); refresh(); }} />}
    </div>}</Loader>
  </Page>;
}

function AssignPanel({ id, onDone }) {
  const q = useApi('jobs/assign', { id }); const { run, busy } = useAction();
  const F = useForm({ inspector_id: '', inspector2_id: '', notify_vendor: true });
  useEffect(() => { if (q.data) F.setF({ inspector_id: q.data.job.inspector_id || '', inspector2_id: q.data.job.inspector2_id || '', notify_vendor: true }); /* eslint-disable-next-line */ }, [q.data]);
  return <Loader q={q}>{(d) => <div className="card"><h2 className="mb">Assign inspector</h2>
    <div className="kv mb"><div><small>QC job</small><b>{d.job.job_no || d.job.req_no}</b></div><div><small>Client</small><b>{d.job.client_code}</b></div><div><small>Vendor</small><b>{d.job.vendor_name}</b></div><div><small>QC date</small><b>{fdate(d.date, 'd-m-Y')}</b></div></div>
    <form onSubmit={async (e) => { e.preventDefault(); if (!F.f.inspector_id) return; const r = await run(() => post('job/action', { act: 'assign', id, date: d.date, ...F.f })); if (r) onDone(); }}>
      <label>Available team</label>
      <div className="stack f">{d.team.map((t) => <label key={t.id} className="rc"><input type="radio" checked={String(F.f.inspector_id) === String(t.id)} onChange={() => F.set('inspector_id', t.id)} />
        <b> {t.emp_code} · {t.name}</b> <span style={{ float: 'right' }}>{+t.busy ? <Badge s="BUSY" /> : <Badge s="AVAILABLE" />}</span>
        <small>{t.location} · {+t.busy ? `${t.busy} job(s) that day` : 'free'}{t.near ? ' · near vendor' : ''}</small></label>)}</div>
      {d.split && d.skus > 1 && <div className="f"><label>Split job across 2 inspectors ({d.skus} SKUs) — second inspector</label>
        <Sel value={F.f.inspector2_id} onChange={(v) => F.set('inspector2_id', v)} options={d.team.map((t) => [t.id, `${t.emp_code} · ${t.name}`])} blank="— no split —" /></div>}
      <label>Notify</label><label className="chk">✓ Inspector — app notification</label>
      <label className="chk f"><input type="checkbox" checked={F.f.notify_vendor} onChange={(e) => F.set('notify_vendor', e.target.checked)} /> Vendor — gate entry notice</label>
      <button className="btn pri block" disabled={busy || !F.f.inspector_id}>ASSIGN INSPECTOR</button>
    </form></div>}</Loader>;
}
