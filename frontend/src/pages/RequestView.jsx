import { ManualList } from './Manuals';
import { useState, Fragment } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { Page } from '../app/Layout';
import { useAuth } from '../app/auth';
import { fileUrl, pdfUrl, post, upload } from '../app/api';
import { Badge, Empty, Flow, Icon, Loader, Modal, Result, Sel, useAction, useApi, useForm, useToast, Thumb, Paged } from '../app/ui';
import { fdate, fdt, num, today } from '../app/util';
import { SchedulePanel } from './Requests';
import { DeleteJobs } from './Jobs';

const FLOW = ['REQUESTED', 'SCHEDULED', 'ASSIGNED', 'IN_PROGRESS', 'SUBMITTED', 'UNDER_REVIEW', 'APPROVED', 'EMAILED', 'CLOSED'];
const SCOPES = [['JOB', 'Same as job'], ['BULK100', 'Bulk 100%'], ['AQL1.5', 'AQL 1.5'], ['AQL2.5', 'AQL 2.5'], ['AQL4.0', 'AQL 4.0'], ['DEFINED', 'Defined sample qty'], ['SAMPLE', 'Sample QC']];

export default function RequestView() {
  const { id } = useParams(); const { me, refresh } = useAuth(); const r = me.user.role; const nav = useNavigate(); const toast = useToast();
  const q = useApi('request', { id }); const opts = useApi('options'); const { run, busy } = useAction();
  const act = async (body, confirmMsg) => { if (confirmMsg && !window.confirm(confirmMsg)) return; const x = await run(() => post('job/action', { id, ...body })); if (x) { q.reload(); refresh(); } };
  const [sched, setSched] = useState(false); const [edit, setEdit] = useState(null); const [lot, setLot] = useState(null); const [fc, setFc] = useState(null); const [man, setMan] = useState(false); const [delJob, setDelJob] = useState(false);
  return <Loader q={q}>{(d) => { const j = d.job, c = d.can; const cur = j.status === 'RETURNED' ? 'UNDER_REVIEW' : j.status === 'DRAFT' ? 'REQUESTED' : j.status;
    return <Page title={(j.job_no || j.req_no) + ' · PO ' + j.po_no} sub={`${j.client_name} — ${j.vendor_name} · ${j.source} · created ${fdt(j.created_at)}`}
      actions={<>{c.edit && <Link className="btn" to={`/request/${j.id}/edit`}>Edit</Link>}{c.schedule && ['REQUESTED', 'DRAFT'].includes(j.status) && <button className="btn pri" onClick={() => setSched(!sched)}>Schedule</button>}
        {c.po_pdf && <a className="btn gold" target="_blank" rel="noreferrer" href={pdfUrl({ job: j.id })}><Icon n="download" /> PO-wise PDF</a>}
        {c.force_close && <button className="btn" style={{ color: 'var(--ruby)' }} onClick={() => setFc({ note: '', item_id: 0 })}>Force close job</button>}</>}>
      <div className="card mb"><Flow steps={FLOW} current={j.status === 'CANCELLED' ? '' : cur}
        extra={<>{j.status === 'PARTIAL' && <span className="cur" style={{ background: '#b4561b' }}>Partial QC done — open</span>}{j.status === 'RETURNED' && <span className="cur" style={{ background: 'var(--ruby)' }}>Returned for correction</span>}{j.status === 'CANCELLED' && <span className="cur" style={{ background: 'var(--ruby)' }}>CANCELLED</span>}</>} /></div>
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
          {d.manuals?.length > 0 && <div className="manbanner"><span>📘 <b>{d.manuals.length} active client QC manual{d.manuals.length > 1 ? 's' : ''} / special instruction{d.manuals.length > 1 ? 's' : ''}</b> available for {j.client_code}{d.manuals.some((m) => m.priority === 'HIGH') ? ' · includes mandatory' : ''}</span><button className="btn sm pri" onClick={() => setMan(true)}>View / Download</button></div>}
          <div className="card"><div className="card-h"><h2>SKUs / designs ({d.items.filter((i) => !i.parent_id).length}){d.items.some((i) => i.parent_id) && <small className="muted"> · {d.items.length} QC lots</small>}</h2>
            {c.edit_design && <button className="btn sm" onClick={() => setEdit({ job_id: j.id, scope: 'JOB', product: '', metal: '925 Silver' })}><Icon n="plus" /> Add design</button>}</div>
            <Paged rows={d.items} per={25} label="SKUs">{(pr) => <div className="tbl-wrap"><table className="t"><thead><tr><th>Image</th><th>SKU</th><th>Product</th><th>Style ref / stone</th><th>Metal / plating</th><th className="num">Order</th><th className="num">Ready</th><th className="num">Insp. qty</th><th className="num">Rej.</th><th>Status</th><th>Result</th><th></th></tr></thead><tbody>
              {pr.map((i) => { const pub = i.status === 'APPROVED'; return <Fragment key={i.id}><tr>
                <td>{i.parent_id ? <span className="muted" style={{ paddingLeft: 18 }}>↳</span> : <Thumb src={i.img} size={56} alt={i.sku} />}</td><td><b className="mono">{i.sku}</b>{(i.parent_id || i.line?.lots > 1) && <> <span className="pill">Lot {i.lot_seq || 1}{i.insp_date ? ' · ' + fdate(i.insp_date, 'd M') : ''}</span></>}
                  {i.target_weight && !i.parent_id && <><br /><small className="muted" style={{ whiteSpace: 'nowrap' }}>{i.target_weight}</small></>}{i.scope_label && <><br /><small className="pill">{i.scope_label}</small></>}
                  {i.line && (i.line.lots > 1 || (i.line.accepted > 0 && !i.line.complete) || i.line.closed) && <div className="cum"><span>Order {num(i.line.order)}</span><span className="ok">OK {num(i.line.accepted)}</span><span className={i.line.pending ? 'p' : ''}>{i.line.closed ? 'Force closed' : `Pending ${num(i.line.pending)}`}</span></div>}
                  {i.edited && <><br /><small className="muted" title="Last edit">✎ {i.edited.by} · {fdt(i.edited.at)}</small></>}</td><td>{i.product}</td><td>{i.style_ref || '—'}<br /><small>{i.stone_setting}</small></td><td>{i.metal}<br /><small>{i.plating}</small></td>
                <td className="num">{num(i.order_qty)}</td><td className="num">{num(i.ready_qty)}</td><td className="num">{num(i.insp_qty)}</td><td className="num">{i.status === 'PENDING' ? '—' : num(i.rejected_qty)}</td>
                <td><Badge s={i.status} />{i.hold_status === 'PENDING' && <><br /><small>waiting {i.hold_for}</small></>}</td>
                <td>{i.final_result ? <Result r={i.final_result} /> : i.system_result ? <small>sys: {i.system_result}</small> : '—'}</td>
                <td>{pub ? <Link className="btn sm" to={'/report/' + i.id}>Report</Link> : c.review && ['SUBMITTED', 'UNDER_REVIEW'].includes(i.status) ? <Link className="btn sm pri" to={'/review/' + i.id}>Review</Link>
                  : i.hold_status === 'PENDING' && r === i.hold_for ? <Link className="btn sm pri" to="/approvals">Respond</Link> : null}{me.user.role === 'super_admin' && i.status !== 'PENDING' && <> <Link className="btn sm" title="Super Admin: edit any inspection point" to={'/edit/' + i.id}>✎ Report</Link></>}
                  {c.edit_design && <> <button className="btn sm" title="Edit design" onClick={() => setEdit({ ...i, scope: i.i_scope ? (i.i_scope === 'SAMPLE' ? 'SAMPLE' : i.i_bulk_mode === 'AQL' ? 'AQL' + (i.i_aql_major || '2.5') : i.i_bulk_mode === 'DEFINED' ? 'DEFINED' : 'BULK100') : 'JOB', defined_qty: i.i_defined_qty || '' })}>✏️ Edit design</button></>}
                  {i.can_remove && c.edit_design && <> <button className="btn sm" style={{ color: 'var(--ruby)' }} onClick={async () => { if (!window.confirm(`Remove ${i.sku}${i.parent_id ? ' lot ' + i.lot_seq : ''} from this job card?`)) return; const x = await run(() => post('item/delete', { id: i.id })); if (x) { q.reload(); refresh(); } }}>Remove</button></>}
                  {i.line?.can_add_lot && c.add_lot && <> <button className="btn sm gold" onClick={() => setLot({ item_id: i.id, sku: i.sku, pending: i.line.pending, ready: i.line.pending, date: today(), inspector_id: j.inspector_id || '' })}>+ Add QC lot / re-inspection</button></>}
                  {i.line && !i.line.complete && c.force_close && i.line.lots > 0 && d.items.filter((x) => !x.parent_id).length > 1 && <> <button className="btn sm" title="Close only this SKU at current accepted qty" onClick={() => setFc({ note: '', item_id: i.id, sku: i.sku })}>Close SKU</button></>}</td></tr>
                {i.line && i.line.lots > 1 && <tr className="sub"><td></td><td colSpan={11}><details className="specbox"><summary>Lot-wise log · cumulative {num(i.line.accepted)} / {num(i.line.order)}</summary><ul className="lotlog">
                  {i.line.log.map((g) => <li key={g.id}><b>Lot {g.lot}</b> · {g.date ? fdate(g.date, 'd M Y') : '—'} · presented {num(g.ready)} · inspected {num(g.inspected)} · accepted {num(g.accepted)} · rej/repair {num(g.rejected)} → counts {num(g.counted)} {g.report_no && <>· {g.report_no}</>} <Badge s={g.status} /></li>)}</ul></details></td></tr>}
                {(i.spec_detail || i.instructions) && <tr className="sub"><td></td><td colSpan={10}><details className="specbox"><summary>Stones, size &amp; client instructions{i.instructions ? ' ⚠' : ''}</summary>
                  <div className="specgrid">{i.instructions && <div className="note gold" style={{ margin: 0 }}><b>Client instructions:</b><p className="spec-pre">{i.instructions}</p></div>}
                  <div>{(i.length || i.lock_type) && <p className="spec-pre"><b>Size / lock:</b> {[i.length, i.lock_type].filter(Boolean).join(' · ')}</p>}{i.spec_detail && <p className="spec-pre">{i.spec_detail}</p>}</div></div></details></td></tr>}
                </Fragment>; })}
            </tbody></table></div>}</Paged>
          </div>
          {!!d.emails.length && <div className="card"><h2 className="mb">Email history</h2><table className="t"><thead><tr><th>Recipient</th><th>Email</th><th>Subject</th><th>Sent</th><th>Status</th></tr></thead><tbody>
            {d.emails.map((e) => <tr key={e.id}><td>{e.recipient_type}</td><td>{e.email}</td><td>{e.subject}</td><td>{fdt(e.sent_at)}</td><td><Badge s={e.status} /></td></tr>)}</tbody></table></div>}
        </div>
        <div>
          {c.assign && opts.data?.inspectors && <AssignBox job={j} inspectors={opts.data.inspectors} split={c.split} busy={busy} onAssign={(f) => act({ act: 'assign', ...f })} isSA={me.user.role === 'super_admin'} />}
          {c.report_actions && <div className="card"><h2 className="mb">Report actions</h2><div className="stack">
            <button className="btn block" disabled={busy} onClick={() => act({ act: 'email_job' })}><Icon n="mail" /> Re-send PO-wise report</button>
            {c.finalize && <button className="btn gold block" disabled={busy} onClick={() => act({ act: 'finalize' })}>Generate PDF &amp; email</button>}
            {c.close && <button className="btn pri block" disabled={busy} onClick={() => act({ act: 'close' })}>Close job</button>}
          </div></div>}
          {d.checkins?.length > 0 && <div className="card"><h2 className="mb">Factory check-ins</h2>{d.checkins.map((c, i) => <div key={i} style={{ fontSize: 13, padding: '5px 0', borderBottom: '1px solid var(--line)' }}>
            {c.out_at ? '⚪' : '🔵'} <b>{c.emp_code}</b> {c.name} · in {fdt(c.at)}{c.out_at ? ' · out ' + fdt(c.out_at) : ' · on-site now'}<br /><small className={+c.verified ? 'ok-t' : 'muted'}>{+c.verified ? `GPS verified ✓ (${c.dist_m} m from factory pin)` : 'Factory pin not set — location sent to Admin'}</small></div>)}</div>}
          {j.status === 'PARTIAL' && <div className="card" style={{ borderColor: '#f0c79a' }}><h2 className="mb">Partial QC done — job open</h2>
            <p className="muted" style={{ fontSize: 12.5, marginTop: 0 }}>All lots so far are approved, but some SKUs have not reached their order qty. Add the next QC lot when the vendor presents the balance / repaired pieces, or force close if the client accepts a short shipment.</p>
            {d.items.filter((i) => i.line && !i.line.complete).map((i) => <div key={i.id} className="cum" style={{ marginBottom: 6 }}><b className="mono">{i.sku}</b><span>Order {num(i.line.order)}</span><span className="ok">OK {num(i.line.accepted)}</span><span className="p">Pending {num(i.line.pending)}</span></div>)}</div>}
          {r === 'super_admin' && <div className="card" style={{ padding: 12 }}><button className="btn block sm" style={{ color: 'var(--ruby)' }} onClick={() => setDelJob(true)}>🗑 Delete job card</button>
            <small className="muted" style={{ display: 'block', marginTop: 6, fontSize: 11.5 }}>Super Admin only · removes designs, inspection data &amp; reports permanently</small></div>}
          {delJob && <DeleteJobs jobs={[j]} onClose={() => setDelJob(false)} onDone={(x) => { setDelJob(false); toast(x.message); nav('/jobs'); }} />}
          {c.cancel && <div className="card"><button className="btn block" style={{ color: 'var(--ruby)' }} disabled={busy} onClick={async () => { await act({ act: 'cancel' }, 'Cancel this QC request?'); }}>Cancel request</button></div>}
          <div className="card"><h2 className="mb">Activity</h2>
            {!d.log.length ? <Empty>No activity yet.</Empty> : <div className="timeline">{d.log.map((l) => <div key={l.id}><b>{l.action.replace(/_/g, ' ')}</b> {l.new_val ? '· ' + String(l.new_val).slice(0, 60) : ''}<small>{fdt(l.created_at)}</small><br /><span className="muted" style={{ fontSize: 12 }}>{l.uname || 'System'}</span></div>)}</div>}
          </div>
          <button className="btn ghost" onClick={() => nav(-1)}>‹ Back</button>
        </div>
      </div>
      <Modal open={man} title={`QC manuals & instructions · ${j.client_code} · ${j.vendor_name}`} onClose={() => setMan(false)} wide>{man && <ManualList rows={d.manuals} compact />}</Modal>
      <EditDesign row={edit} onClose={() => setEdit(null)} onSaved={() => { setEdit(null); q.reload(); refresh(); }} />
      <Modal open={!!lot} title={lot ? 'Add next QC lot · ' + lot.sku : ''} onClose={() => setLot(null)}>
        {lot && <><p className="muted" style={{ marginTop: 0, fontSize: 12.5 }}>Pending balance <b>{num(lot.pending)}</b> pcs (not yet presented + repaired). Client, vendor, PO, SKU specs, images and files are copied — the inspector enters only this lot's quantities, defects and photos. Order qty stays {num(d.items.find((x) => x.id === lot.item_id)?.line?.order)}.</p>
          <div className="fr f"><div><label>Presented / ready qty</label><input type="number" min="1" value={lot.ready} onChange={(e) => setLot({ ...lot, ready: e.target.value })} /></div><div><label>QC date</label><input type="date" value={lot.date} onChange={(e) => setLot({ ...lot, date: e.target.value })} /></div></div>
          {opts.data?.inspectors && <div className="f"><label>Inspector</label><Sel value={lot.inspector_id} onChange={(v) => setLot({ ...lot, inspector_id: v })} options={opts.data.inspectors.map((x) => [x.id, x.label])} blank="Same inspector" /></div>}
          <button className="btn gold block" disabled={busy || !(+lot.ready > 0)} onClick={async () => { await act({ act: 'add_lot', item_id: lot.item_id, ready_qty: lot.ready, date: lot.date, inspector_id: lot.inspector_id }); setLot(null); }}>ADD QC LOT</button></>}
      </Modal>
      <Modal open={!!fc} title={fc?.item_id ? 'Close SKU ' + fc.sku : 'Force close job'} onClose={() => setFc(null)}>
        {fc && <><p className="muted" style={{ marginTop: 0, fontSize: 12.5 }}>{fc.item_id ? 'This SKU is closed at its current cumulative accepted qty.' : 'Every SKU is closed at its current cumulative accepted qty, the consolidated PO report is generated and the job card is CLOSED.'} Un-inspected extra lots are removed. This is logged.</p>
          <div className="f"><label>Reason</label><textarea value={fc.note} onChange={(e) => setFc({ ...fc, note: e.target.value })} placeholder="e.g. Client accepted short shipment of 2 pcs" /></div>
          <button className="btn pri block" style={{ background: 'var(--ruby)', borderColor: 'var(--ruby)' }} disabled={busy || !fc.note.trim()} onClick={async () => { await act({ act: 'force_close', note: fc.note, item_id: fc.item_id }); setFc(null); }}>{fc.item_id ? 'CLOSE SKU' : 'FORCE CLOSE JOB'}</button></>}
      </Modal>
    </Page>; }}</Loader>;
}

/** Design-wise edit / add inside a generated job card (Super Admin, Back Office, QC Manager). */
function EditDesign({ row, onClose, onSaved }) {
  const [f, setF] = useState(null); const [ref, setRef] = useState(null); const [busy, setBusy] = useState(false); const toast = useToast();
  const [last, setLast] = useState(null);
  if (row !== last) { setLast(row); setF(row ? { ...row } : null); setRef(null); }
  if (!row || !f) return null;
  const isNew = !row.id; const lockedQty = !isNew && ['SUBMITTED', 'UNDER_REVIEW', 'APPROVED'].includes(row.status);
  const b = (k) => ({ value: f[k] ?? '', onChange: (e) => setF({ ...f, [k]: e.target.value }) });
  const save = async () => {
    const keys = ['sku', 'product', 'metal', 'plating', 'target_weight', 'length', 'width', 'thickness', 'lock_type', 'stamping', 'style_ref', 'stone_setting', 'spec_detail', 'instructions', 'order_qty', 'ready_qty', 'scope', 'defined_qty'];
    const data = Object.fromEntries(keys.filter((k) => f[k] !== undefined && !(lockedQty && ['ready_qty', 'scope', 'defined_qty'].includes(k))).map((k) => [k, f[k]]));
    if (row.parent_id) delete data.order_qty;
    const fd = new FormData(); fd.append('data', JSON.stringify({ ...data, id: row.id || 0, job_id: row.job_id })); if (ref) fd.append('ref', ref);
    setBusy(true); try { const r = await upload('item/save', fd); toast(r.message); onSaved(); } catch (e) { toast(e.message, 'err'); } setBusy(false);
  };
  return <Modal open title={isNew ? 'Add design to job card' : `Edit design · ${row.sku}${row.parent_id ? ' (lot ' + row.lot_seq + ')' : ''}`} onClose={onClose} wide>
    <div className="grid g2" style={{ gap: 10 }}>
      <div className="f"><label>SKU / design no.</label><input type="text" {...b('sku')} /></div><div className="f"><label>Product</label><input type="text" {...b('product')} /></div>
      <div className="f"><label>Order qty{row.parent_id ? ' (edit on lot 1)' : ''}</label><input type="number" {...b('order_qty')} disabled={!!row.parent_id} /></div>
      <div className="f"><label>Ready qty{lockedQty ? ' (locked — already inspected)' : ''}</label><input type="number" {...b('ready_qty')} disabled={lockedQty} /></div>
      <div className="f"><label>QC scope for this design</label><Sel value={f.scope} onChange={(v) => setF({ ...f, scope: v })} options={SCOPES} disabled={lockedQty} /></div>
      {['DEFINED', 'SAMPLE'].includes(f.scope) ? <div className="f"><label>Sample / defined qty</label><input type="number" {...b('defined_qty')} disabled={lockedQty} /></div> : <div />}
      <div className="f"><label>Metal</label><input type="text" {...b('metal')} /></div><div className="f"><label>Plating</label><input type="text" {...b('plating')} /></div>
      <div className="f"><label>Target weight</label><input type="text" {...b('target_weight')} /></div><div className="f"><label>Length / size</label><input type="text" {...b('length')} /></div>
      <div className="f"><label>Width</label><input type="text" {...b('width')} /></div><div className="f"><label>Thickness</label><input type="text" {...b('thickness')} /></div>
      <div className="f"><label>Lock type</label><input type="text" {...b('lock_type')} /></div><div className="f"><label>Stamping</label><input type="text" {...b('stamping')} /></div>
      <div className="f"><label>Client style ref</label><input type="text" {...b('style_ref')} /></div><div className="f"><label>Stone / setting</label><input type="text" {...b('stone_setting')} /></div>
    </div>
    <div className="f"><label>Special remarks / client instructions</label><textarea {...b('instructions')} /></div>
    <div className="f"><label>Stone / spec detail</label><textarea {...b('spec_detail')} /></div>
    <div className="f"><label>Reference / CAD image {row.img ? '(replaces current)' : ''}</label><div className="skucell">{!isNew && <Thumb src={row.img} size={64} />}<input type="file" accept="image/*" onChange={(e) => setRef(e.target.files[0] || null)} /></div>
      <small className="muted">Inspectors can only view this image — they add their own inspection photos.</small></div>
    <div className="actions"><button className="btn" onClick={onClose}>Cancel</button><button className="btn pri" disabled={busy} onClick={save}>{isNew ? 'Add design' : 'Save changes'}</button></div>
  </Modal>;
}

function AssignBox({ job, inspectors, split, busy, onAssign, isSA }) {
  const F = useForm({ inspector_id: job.inspector_id || '', inspector2_id: job.inspector2_id || '', date: job.scheduled_date || job.requested_date || '', notify_vendor: true });
  return <div className="card"><h2 className="mb">{job.inspector_id ? 'Reassign inspector' : 'Assign inspector'}</h2>
    <form onSubmit={(e) => { e.preventDefault(); onAssign(F.f); }}>
      <div className="f"><label>Inspector</label><Sel value={F.f.inspector_id} onChange={(v) => F.set('inspector_id', v)} options={inspectors.map((i) => [i.id, i.label])} blank={job.inspector_id ? '— Unassigned (move to pending pool) —' : 'Select inspector'} /></div>
      {job.inspector_id && !F.f.inspector_id && <p className="note" style={{ fontSize: 12 }}>Saving without an inspector moves this job back to the Unassigned pool and removes it from the inspector's app. Only allowed while not yet started{isSA ? ' — tick force to override.' : '.'}</p>}
      {job.inspector_id && !F.f.inspector_id && isSA && <label className="chk f" style={{ color: 'var(--ruby)' }}><input type="checkbox" checked={!!F.f.force} onChange={(e) => F.set('force', e.target.checked)} /> Force (inspection already started)</label>}
      {split && <div className="f"><label>Second inspector (split large job)</label><Sel value={F.f.inspector2_id} onChange={(v) => F.set('inspector2_id', v)} options={inspectors.map((i) => [i.id, i.label])} blank="—" /></div>}
      <div className="f"><label>QC date</label><input type="date" {...F.bind('date')} /></div>
      <label className="chk f"><input type="checkbox" checked={F.f.notify_vendor} onChange={(e) => F.set('notify_vendor', e.target.checked)} /> Notify vendor for gate entry</label>
      <button className="btn pri block" disabled={busy || (!F.f.inspector_id && !job.inspector_id)}>{F.f.inspector_id || !job.inspector_id ? 'ASSIGN INSPECTOR' : 'SAVE · MOVE TO UNASSIGNED'}</button>
    </form></div>;
}
