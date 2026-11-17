import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { Page } from '../app/Layout';
import { useAuth } from '../app/auth';
import { fileUrl, pdfUrl, post } from '../app/api';
import { Badge, Empty, Flow, Icon, Loader, Result, Sel, useAction, useApi, useForm } from '../app/ui';
import { fdate, fdt, num } from '../app/util';
import { SchedulePanel } from './Requests';

const FLOW = ['REQUESTED', 'SCHEDULED', 'ASSIGNED', 'IN_PROGRESS', 'SUBMITTED', 'UNDER_REVIEW', 'APPROVED', 'EMAILED', 'CLOSED'];

export default function RequestView() {
  const { id } = useParams(); const { me, refresh } = useAuth(); const r = me.user.role; const nav = useNavigate();
  const q = useApi('request', { id }); const opts = useApi('options'); const { run, busy } = useAction();
  const act = async (body, confirmMsg) => { if (confirmMsg && !window.confirm(confirmMsg)) return; const x = await run(() => post('job/action', { id, ...body })); if (x) { q.reload(); refresh(); } };
  const [sched, setSched] = useState(false);
  return <Loader q={q}>{(d) => { const j = d.job, c = d.can; const cur = j.status === 'RETURNED' ? 'UNDER_REVIEW' : j.status === 'DRAFT' ? 'REQUESTED' : j.status;
    return <Page title={(j.job_no || j.req_no) + ' · PO ' + j.po_no} sub={`${j.client_name} — ${j.vendor_name} · ${j.source} · created ${fdt(j.created_at)}`}
      actions={<>{c.edit && <Link className="btn" to={`/request/${j.id}/edit`}>Edit</Link>}{c.schedule && ['REQUESTED', 'DRAFT'].includes(j.status) && <button className="btn pri" onClick={() => setSched(!sched)}>Schedule</button>}
        {c.po_pdf && <a className="btn gold" target="_blank" rel="noreferrer" href={pdfUrl({ job: j.id })}><Icon n="download" /> PO-wise PDF</a>}</>}>
      <div className="card mb"><Flow steps={FLOW} current={j.status === 'CANCELLED' ? '' : cur}
        extra={<>{j.status === 'RETURNED' && <span className="cur" style={{ background: 'var(--ruby)' }}>Returned for correction</span>}{j.status === 'CANCELLED' && <span className="cur" style={{ background: 'var(--ruby)' }}>CANCELLED</span>}</>} /></div>
      <div className="grid g-73">
        <div>
          {sched && opts.data && <SchedulePanel row={{ ...j, scope_label: j.scope_label, skus: d.items.length }} managers={opts.data.managers || []} onDone={() => { setSched(false); q.reload(); refresh(); }} />}
          <div className="card"><div className="card-h"><h2>Request details</h2><Badge s={j.status} /></div>
            <div className="kv">
              <div><small>Request no.</small><b>{j.req_no}</b></div><div><small>QC job no.</small><b>{j.job_no || '—'}</b></div>
              <div><small>Client</small><b>{j.client_code} — {j.client_name}</b></div><div><small>Vendor</small><b>{j.vendor_name}</b></div>
              <div><small>PO number</small><b>{j.po_no}</b></div><div><small>QC type</small><b>{j.qc_type}</b></div>
              <div><small>QC scope</small><b>{j.scope_label}</b></div><div><small>Location</small><b>{j.location}</b></div>
              <div><small>Requested date</small><b>{fdate(j.requested_date)}</b></div><div><small>Scheduled</small><b>{j.scheduled_date ? fdate(j.scheduled_date) + ' · ' + (j.time_slot || '') : '—'}</b></div>
              <div><small>Inspector</small><b>{j.inspector_id ? `${j.inspector_code} · ${j.inspector_name}` : '—'}</b></div><div><small>QC manager</small><b>{j.manager_name || '—'}</b></div>
            </div>
            {(j.specs || j.special_req) && <div className="note mt">{j.specs && <><b>Specs:</b> {j.specs}<br /></>}{j.special_req && <><b>Special requirement:</b> {j.special_req}</>}</div>}
            {!!d.attachments.length && <div className="actions mt">{d.attachments.map((a) => <a key={a.id} className="pill" target="_blank" rel="noreferrer" href={fileUrl(a.path)}>📎 {a.name}</a>)}</div>}
          </div>
          <div className="card"><div className="card-h"><h2>SKUs / designs ({d.items.length})</h2></div>
            <div className="tbl-wrap"><table className="t"><thead><tr><th>SKU</th><th>Product</th><th>Style ref / stone</th><th>Metal / plating</th><th className="num">Order</th><th className="num">Ready</th><th className="num">Insp. qty</th><th className="num">Rej.</th><th>Status</th><th>Result</th><th></th></tr></thead><tbody>
              {d.items.map((i) => { const pub = i.status === 'APPROVED'; return <tr key={i.id}>
                <td className="mono strong">{i.sku}</td><td>{i.product}</td><td>{i.style_ref || '—'}<br /><small>{i.stone_setting}</small></td><td>{i.metal}<br /><small>{i.plating}</small></td>
                <td className="num">{num(i.order_qty)}</td><td className="num">{num(i.ready_qty)}</td><td className="num">{num(i.insp_qty)}</td><td className="num">{i.status === 'PENDING' ? '—' : num(i.rejected_qty)}</td>
                <td><Badge s={i.status} />{i.hold_status === 'PENDING' && <><br /><small>waiting {i.hold_for}</small></>}</td>
                <td>{i.final_result ? <Result r={i.final_result} /> : i.system_result ? <small>sys: {i.system_result}</small> : '—'}</td>
                <td>{pub ? <Link className="btn sm" to={'/report/' + i.id}>Report</Link> : c.review && ['SUBMITTED', 'UNDER_REVIEW'].includes(i.status) ? <Link className="btn sm pri" to={'/review/' + i.id}>Review</Link>
                  : i.hold_status === 'PENDING' && r === i.hold_for ? <Link className="btn sm pri" to="/approvals">Respond</Link> : null}</td></tr>; })}
            </tbody></table></div>
          </div>
          {!!d.emails.length && <div className="card"><h2 className="mb">Email history</h2><table className="t"><thead><tr><th>Recipient</th><th>Email</th><th>Subject</th><th>Sent</th><th>Status</th></tr></thead><tbody>
            {d.emails.map((e) => <tr key={e.id}><td>{e.recipient_type}</td><td>{e.email}</td><td>{e.subject}</td><td>{fdt(e.sent_at)}</td><td><Badge s={e.status} /></td></tr>)}</tbody></table></div>}
        </div>
        <div>
          {c.assign && opts.data?.inspectors && <AssignBox job={j} inspectors={opts.data.inspectors} split={c.split} busy={busy} onAssign={(f) => act({ act: 'assign', ...f })} />}
          {c.report_actions && <div className="card"><h2 className="mb">Report actions</h2><div className="stack">
            <button className="btn block" disabled={busy} onClick={() => act({ act: 'email_job' })}><Icon n="mail" /> Re-send PO-wise report</button>
            {c.finalize && <button className="btn gold block" disabled={busy} onClick={() => act({ act: 'finalize' })}>Generate PDF &amp; email</button>}
            {c.close && <button className="btn pri block" disabled={busy} onClick={() => act({ act: 'close' })}>Close job</button>}
          </div></div>}
          {c.cancel && <div className="card"><button className="btn block" style={{ color: 'var(--ruby)' }} disabled={busy} onClick={async () => { await act({ act: 'cancel' }, 'Cancel this QC request?'); }}>Cancel request</button></div>}
          <div className="card"><h2 className="mb">Activity</h2>
            {!d.log.length ? <Empty>No activity yet.</Empty> : <div className="timeline">{d.log.map((l) => <div key={l.id}><b>{l.action.replace(/_/g, ' ')}</b> {l.new_val ? '· ' + String(l.new_val).slice(0, 60) : ''}<small>{fdt(l.created_at)}</small><br /><span className="muted" style={{ fontSize: 12 }}>{l.uname || 'System'}</span></div>)}</div>}
          </div>
          <button className="btn ghost" onClick={() => nav(-1)}>‹ Back</button>
        </div>
      </div>
    </Page>; }}</Loader>;
}

function AssignBox({ job, inspectors, split, busy, onAssign }) {
  const F = useForm({ inspector_id: job.inspector_id || '', inspector2_id: job.inspector2_id || '', date: job.scheduled_date || job.requested_date || '', notify_vendor: true });
  return <div className="card"><h2 className="mb">{job.inspector_id ? 'Reassign inspector' : 'Assign inspector'}</h2>
    <form onSubmit={(e) => { e.preventDefault(); onAssign(F.f); }}>
      <div className="f"><label>Inspector</label><Sel value={F.f.inspector_id} onChange={(v) => F.set('inspector_id', v)} options={inspectors.map((i) => [i.id, i.label])} blank="Select inspector" required /></div>
      {split && <div className="f"><label>Second inspector (split large job)</label><Sel value={F.f.inspector2_id} onChange={(v) => F.set('inspector2_id', v)} options={inspectors.map((i) => [i.id, i.label])} blank="—" /></div>}
      <div className="f"><label>QC date</label><input type="date" {...F.bind('date')} /></div>
      <label className="chk f"><input type="checkbox" checked={F.f.notify_vendor} onChange={(e) => F.set('notify_vendor', e.target.checked)} /> Notify vendor for gate entry</label>
      <button className="btn pri block" disabled={busy}>ASSIGN INSPECTOR</button>
    </form></div>;
}
