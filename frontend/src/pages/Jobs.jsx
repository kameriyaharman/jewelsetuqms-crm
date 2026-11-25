import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Page } from '../app/Layout';
import { useAuth } from '../app/auth';
import { post } from '../app/api';
import { Badge, Empty, Icon, InspSt, Loader, Modal, Sel, useAction, useApi, useForm, Thumb, Paged } from '../app/ui';
import { fdate, num, today } from '../app/util';

const TABS = [['ALL', 'All'], ['UNASSIGNED', 'Unassigned'], ['ASSIGNED', 'Assigned'], ['IN_PROGRESS', 'In progress'], ['REVIEW', 'Review'], ['OPEN', 'Open / partial QC'], ['CLOSED', 'Complete / closed']];
const OPEN = ['SCHEDULED', 'ASSIGNED', 'REQUESTED', 'IN_PROGRESS'];
const BULK = ['SCHEDULED', 'ASSIGNED', 'REQUESTED', 'IN_PROGRESS', 'PARTIAL'];

export default function Jobs() {
  const { me, refresh } = useAuth(); const canAssign = ['super_admin', 'qc_manager', 'back_office'].includes(me.user.role);
  const isSA = me.user.role === 'super_admin'; const canPick = (j) => isSA || BULK.includes(j.status);
  const [sp, setSp] = useSearchParams(); const f = sp.get('f') || 'ALL'; const qs = sp.get('q') || '';
  const q = useApi('jobs', { f, q: qs });
  const [search, setSearch] = useState(qs); const [pick, setPick] = useState(new Set()); const [del, setDel] = useState(null); const [bulk, setBulk] = useState({ inspector_id: '', inspector2_id: '', date: '' });
  const [lot, setLot] = useState(null); const { run, busy } = useAction();
  useEffect(() => { setPick(new Set()); }, [f, qs]);
  const go = (o) => { const n = { f, ...(qs ? { q: qs } : {}), ...o }; Object.keys(n).forEach((k) => !n[k] && delete n[k]); setSp(n); };
  const toggle = (id) => setPick((s) => { const n = new Set(s); n.has(id) ? n.delete(id) : n.add(id); return n; });
  const doBulk = async () => { const r = await run(() => post('jobs/bulk_assign', { ids: [...pick], ...bulk })); if (r) { setPick(new Set()); q.reload(); refresh(); } };
  const doUnassign = async () => {
    if (!window.confirm(`Move ${pick.size} job(s) back to the Unassigned pool? They disappear from the inspector's app.`)) return;
    let r = await run(() => post('jobs/bulk_assign', { ids: [...pick], unassign: 1 }));
    if (r && me.user.role === 'super_admin' && /use force/.test(r.message || '') && window.confirm('Some jobs are already in progress. Force unassign them too? (Saved inspection data stays on the job.)')) r = await run(() => post('jobs/bulk_assign', { ids: [...pick], unassign: 1, force: 1 }));
    if (r) { setPick(new Set()); q.reload(); refresh(); }
  };
  const addLot = async () => { const r = await run(() => post('job/action', { act: 'add_lot', id: lot.job_id, item_id: lot.item_id, ready_qty: lot.ready, date: lot.date })); if (r) { setLot(null); q.reload(); refresh(); } };
  const [view, setViewS] = useState(() => { try { return localStorage.getItem('qt_jobs_view') || 'list'; } catch { return 'list'; } });
  const setView = (v) => { setViewS(v); try { localStorage.setItem('qt_jobs_view', v); } catch { /* ignore */ } };
  let sel = Number(sp.get('id')) || null;
  if (!sel && q.data) { const u = q.data.rows.find((r) => !r.inspector_id && ['SCHEDULED', 'REQUESTED'].includes(r.status)); sel = u?.id || null; }
  const selRow = q.data?.rows.find((r) => r.id === sel);
  return <Page title="QC Jobs" sub="All jobs by status; assign or reassign an inspector with availability and notifications" actions={<Link className="btn" to="/schedule"><Icon n="cal" /> Week schedule</Link>}>
    <Loader q={q}>{(d) => <div className={'grid ' + (canAssign && selRow && OPEN.includes(selRow.status) ? 'g-82' : '')}>
      <div className="card">
        <div style={{ display: 'flex', alignItems: 'flex-start', gap: 10 }}><div className="tabs" style={{ flex: 1 }}>{TABS.map(([k, l]) => <a key={k} href="#" className={f === k ? 'on' : ''} onClick={(e) => { e.preventDefault(); setSp({ f: k }); }}>{l} <b>{d.counts[k] || 0}</b></a>)}</div>
          <form onSubmit={(e) => { e.preventDefault(); go({ q: search.trim(), id: '' }); }} style={{ display: 'flex', gap: 6 }}><input type="search" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="PO no. / job / SKU / vendor" style={{ width: 210 }} /><button className="btn sm">Search</button></form>
          <span className="viewtog"><button className={view === 'list' ? 'on' : ''} title="List view" onClick={() => setView('list')}><Icon n="rows" /></button><button className={view === 'cards' ? 'on' : ''} title="Card view" onClick={() => setView('cards')}><Icon n="cards" /></button></span></div>
        {qs && <p className="muted" style={{ margin: '0 0 8px', fontSize: 12.5 }}>Showing {d.rows.length} job card(s) for “<b>{qs}</b>” · <a href="#" onClick={(e) => { e.preventDefault(); setSearch(''); go({ q: '' }); }}>clear</a></p>}
        {canAssign && pick.size > 0 && <div className="bulkbar"><b>{pick.size} job(s) selected</b>
          <Sel value={bulk.inspector_id} onChange={(v) => setBulk({ ...bulk, inspector_id: v })} options={d.inspectors.map((i) => [i.id, `${i.emp_code} · ${i.name}`])} blank="Select inspector" />
          <Sel value={bulk.inspector2_id || ''} onChange={(v) => setBulk({ ...bulk, inspector2_id: v })} options={d.inspectors.filter((i) => String(i.id) !== String(bulk.inspector_id)).map((i) => [i.id, `${i.emp_code} · ${i.name}`])} blank="+ 2nd inspector (optional)" />
          <input type="date" value={bulk.date} onChange={(e) => setBulk({ ...bulk, date: e.target.value })} title="QC date (optional)" />
          <button className="btn gold" disabled={busy || !bulk.inspector_id} onClick={doBulk}>ASSIGN SELECTED ({pick.size} JOBS)</button>
          <button className="btn" style={{ background: '#fff', color: 'var(--ruby)', borderColor: '#fff' }} disabled={busy} onClick={doUnassign}>UNASSIGN SELECTED</button>
          {isSA && <button className="btn" style={{ background: 'var(--ruby)', color: '#fff', borderColor: 'var(--ruby)' }} disabled={busy} onClick={() => setDel(d.rows.filter((j) => pick.has(j.id)))}>🗑 DELETE SELECTED</button>}
          <button className="btn sm" onClick={() => setPick(new Set())}>Clear</button></div>}
        {del && <DeleteJobs jobs={del} onClose={() => setDel(null)} onDone={() => { setDel(null); setPick(new Set()); q.reload(); refresh(); }} />}
        {!d.rows.length ? <Empty>No jobs.</Empty> : <Paged rows={d.rows} per={25} label="jobs" resetKey={f + view}>{(pr) => (view === 'cards' ? <div className="cards">{pr.map((j) => <div key={j.id} className={'pcard' + (sel === j.id ? ' sel' : '')}>
            <Link to={'/request/' + j.id} className="pimg"><Thumb src={j.img} size={150} alt={j.job_no} /></Link>
            <div className="pbody"><Link className="strong mono" to={'/request/' + j.id}>{j.job_no || j.req_no}</Link><b>{j.vendor_name}</b><small className="muted">{j.client_code} · PO {j.po_no} · {j.skus} SKU</small>
              <small>{fdate(j.scheduled_date, 'd M Y')} {j.time_slot}</small><small>{j.insp_code ? `${j.insp_code} · ${j.insp_name}` : 'Not assigned'}</small></div>
            <div className="pfoot">{!j.inspector_id && ['SCHEDULED', 'REQUESTED'].includes(j.status) ? <Badge s="UNASSIGNED" /> : <Badge s={j.status} />}
              {canAssign && OPEN.includes(j.status) && <button className="btn sm" onClick={() => setSp({ f, id: j.id })}>{j.inspector_id ? 'Reassign' : 'Assign'}</button>}</div></div>)}</div>
          : <div className="tbl-wrap"><table className="t"><thead><tr>{canAssign && <th style={{ width: 28 }}><input type="checkbox" title="Select all" checked={d.rows.filter((j) => canPick(j)).length > 0 && d.rows.filter((j) => canPick(j)).every((j) => pick.has(j.id))} onChange={(e) => setPick(e.target.checked ? new Set(d.rows.filter((j) => canPick(j)).map((j) => j.id)) : new Set())} /></th>}
            <th>QC job no.</th><th>Client</th><th>Vendor</th><th>PO</th><th>SKU</th>{f === 'OPEN' ? <><th className="num">Order</th><th className="num">Accepted</th><th className="num">Balance</th></> : <th>QC date</th>}<th>Inspector</th><th>Status</th></tr></thead><tbody>
          {pr.map((j) => <tr key={j.id} className={sel === j.id ? 'sel' : ''}>
            {canAssign && <td>{canPick(j) && <input type="checkbox" checked={pick.has(j.id)} onChange={() => toggle(j.id)} />}</td>}
            <td><div className="skucell"><Thumb src={j.img} size={40} alt={j.job_no} /><Link className="strong mono" to={'/request/' + j.id}>{j.job_no}</Link></div></td><td>{j.client_code}</td><td>{j.vendor_name}</td><td className="mono"><a href="#" onClick={(e) => { e.preventDefault(); setSearch(j.po_no); go({ q: j.po_no, id: '' }); }}>{j.po_no}</a></td><td>{+j.skus === 1 ? <span className="mono">{j.sku1}</span> : `${j.skus} SKUs`}</td>
            {f === 'OPEN' ? <><td className="num">{num(j.bal?.order)}</td><td className="num ok-t"><b>{num(j.bal?.accepted)}</b></td><td className="num bad-t"><b>{num(j.bal?.pending)}</b></td></> : <>
            <td>{fdate(j.scheduled_date, 'd M')} <small>{j.time_slot}</small></td></>}<td>{j.insp_code ? `${j.insp_code} · ${j.insp_name.split(' ')[0]}` : '—'}</td>
            <td>{!j.inspector_id && ['SCHEDULED', 'REQUESTED'].includes(j.status) ? <Badge s="UNASSIGNED" /> : <Badge s={j.status} />}
              {canAssign && [...OPEN, 'SCHEDULED'].includes(j.status) && <> <button className="btn sm" onClick={() => go({ id: j.id })}>{j.inspector_id ? 'Reassign' : 'Assign'}</button></>}
              {j.status === 'PARTIAL' && j.bal?.lot && <> <button className="btn sm gold" onClick={() => setLot({ job_id: j.id, job_no: j.job_no, ...j.bal.lot, ready: j.bal.lot.pending, date: today() })}>+ Add next QC lot</button></>}</td></tr>)}
        </tbody></table></div>)}</Paged>}
        <p className="muted" style={{ fontSize: 12, marginTop: 12 }}>Lifecycle: Requested → Scheduled → Assigned → In progress → Submitted → Under review → Approved → PDF generated → Emailed → Closed</p>
      </div>
      {canAssign && selRow && OPEN.includes(selRow.status) && <AssignPanel key={selRow.id} id={selRow.id} onDone={() => { q.reload(); refresh(); }} />}
    </div>}</Loader>
    <Modal open={!!lot} title={lot ? `Add next QC lot · ${lot.sku}` : ''} onClose={() => setLot(null)}>
      {lot && <><p className="muted" style={{ marginTop: 0, fontSize: 12.5 }}>{lot.job_no} · pending balance <b>{num(lot.pending)}</b> pcs (un-presented + repaired). Specs, images and files are copied; the order qty does not change.</p>
        <div className="fr f"><div><label>Presented / ready qty (this lot)</label><input type="number" min="1" max={lot.pending} value={lot.ready} onChange={(e) => setLot({ ...lot, ready: e.target.value })} /></div>
          <div><label>QC date</label><input type="date" value={lot.date} onChange={(e) => setLot({ ...lot, date: e.target.value })} /></div></div>
        <button className="btn gold block" disabled={busy || !(+lot.ready > 0)} onClick={addLot}>ADD QC LOT &amp; RE-OPEN JOB</button></>}
    </Modal>
  </Page>;
}

function AssignPanel({ id, onDone }) {
  const q = useApi('jobs/assign', { id }); const { run, busy } = useAction();
  const F = useForm({ inspector_id: '', inspector2_id: '', notify_vendor: true, force: false });
  useEffect(() => { if (q.data) F.setF({ inspector_id: q.data.job.inspector_id || '', inspector2_id: q.data.job.inspector2_id || '', notify_vendor: true, force: false }); /* eslint-disable-next-line */ }, [q.data]);
  return <Loader q={q}>{(d) => <div className="card"><h2 className="mb">Assign inspector</h2>
    <div className="kv mb"><div><small>QC job</small><b>{d.job.job_no || d.job.req_no}</b></div><div><small>Client</small><b>{d.job.client_code}</b></div><div><small>Vendor</small><b>{d.job.vendor_name}</b></div><div><small>QC date</small><b>{fdate(d.date, 'd-m-Y')}</b></div></div>
    <form onSubmit={async (e) => { e.preventDefault(); if (!F.f.inspector_id && !d.job.inspector_id) return; const r = await run(() => post('job/action', { act: 'assign', id, date: d.date, ...F.f })); if (r) onDone(); }}>
      <label>Available team</label>
      <div className="stack f">
        {d.job.inspector_id && <label className={'rc' + (!d.unassign_ok && !d.is_sa ? ' dis' : '')} style={{ borderStyle: 'dashed' }}><input type="radio" disabled={!d.unassign_ok && !d.is_sa} checked={!F.f.inspector_id} onChange={() => F.setF({ ...F.f, inspector_id: '', inspector2_id: '' })} />
          <b> — Unassigned (move to pending pool) —</b><small>{d.unassign_ok ? 'Removes the job from the inspector\'s app; it goes back to the Unassigned tab.' : d.is_sa ? 'Inspection already started — Super Admin can force it (saved data stays).' : 'Not allowed — inspection already started / submitted.'}</small></label>}
        {d.team.map((t) => <label key={t.id} className={'rc' + (t.st.code === 'LEAVE' ? ' dis' : '')}><input type="radio" disabled={t.st.code === 'LEAVE'} checked={String(F.f.inspector_id) === String(t.id)} onChange={() => F.set('inspector_id', t.id)} />
        <b> {t.emp_code} · {t.name}</b> <span style={{ float: 'right' }}><InspSt st={t.st} small /></span>
        <small>{t.location} · {t.st.sub}{t.near ? ' · near vendor' : ''}</small></label>)}</div>
      {!F.f.inspector_id && d.job.inspector_id && !d.unassign_ok && d.is_sa && <label className="chk f" style={{ color: 'var(--ruby)' }}><input type="checkbox" checked={F.f.force} onChange={(e) => F.set('force', e.target.checked)} /> I understand — force unassign although inspection has started</label>}
      {d.split && <div className="f"><label>Second inspector (2 inspectors on this job){d.skus > 1 ? ` · ${d.skus} SKUs` : ""}</label>
        <Sel value={F.f.inspector2_id} onChange={(v) => F.set('inspector2_id', v)} options={d.team.map((t) => [t.id, `${t.emp_code} · ${t.name}`])} blank="— no split —" /></div>}
      <label>Notify</label><label className="chk">✓ Inspector — app notification</label>
      <label className="chk f"><input type="checkbox" checked={F.f.notify_vendor} onChange={(e) => F.set('notify_vendor', e.target.checked)} /> Vendor — gate entry notice</label>
      <button className="btn pri block" disabled={busy || (!F.f.inspector_id && (!d.job.inspector_id || (!d.unassign_ok && !F.f.force)))} style={!F.f.inspector_id && d.job.inspector_id ? { background: 'var(--ruby)', borderColor: 'var(--ruby)' } : {}}>{F.f.inspector_id || !d.job.inspector_id ? 'ASSIGN INSPECTOR' : 'SAVE · MOVE TO UNASSIGNED'}</button>
    </form></div>}</Loader>;
}

/** Super Admin: permanently delete job card(s) — reason + typing DELETE to confirm. */
export function DeleteJobs({ jobs, onClose, onDone }) {
  const [reason, setReason] = useState(''); const [word, setWord] = useState(''); const { run, busy } = useAction();
  const done = jobs.filter((j) => ['APPROVED', 'EMAILED', 'CLOSED', 'PARTIAL', 'SUBMITTED', 'UNDER_REVIEW', 'IN_PROGRESS'].includes(j.status));
  const go = async () => { const r = await run(() => post('jobs/delete', { ids: jobs.map((j) => j.id), reason })); if (r) onDone(r); };
  return <Modal open title={`Delete ${jobs.length} job card${jobs.length > 1 ? 's' : ''} permanently`} onClose={onClose}>
    <div className="note ruby mb" style={{ fontSize: 12.5 }}>This removes the job card{jobs.length > 1 ? 's' : ''} with all designs, inspection data, photos, defects and PDF reports. <b>It cannot be undone.</b> Who deleted it, when and why stays in the audit log.</div>
    <div style={{ maxHeight: 150, overflow: 'auto', marginBottom: 10, fontSize: 12.5 }}>{jobs.map((j) => <div key={j.id}>• <b className="mono">{j.job_no || j.req_no}</b> · PO {j.po_no} · {j.client_code} – {j.vendor_name} · <Badge s={j.status} /></div>)}</div>
    {done.length > 0 && <div className="note gold mb" style={{ fontSize: 12.5 }}>⚠️ {done.length} of these already have inspection work / reports ({[...new Set(done.map((j) => j.status))].join(', ')}). Their QC reports will be deleted too.</div>}
    <div className="f"><label>Reason (required)</label><input type="text" value={reason} placeholder="e.g. Wrong PO imported — will re-import updated sheet" onChange={(e) => setReason(e.target.value)} /></div>
    <div className="f"><label>Type <b>DELETE</b> to confirm</label><input type="text" value={word} onChange={(e) => setWord(e.target.value)} /></div>
    <div className="actions"><button className="btn" onClick={onClose}>Cancel</button><button className="btn" style={{ background: 'var(--ruby)', color: '#fff', borderColor: 'var(--ruby)' }} disabled={busy || reason.trim().length < 3 || word.trim().toUpperCase() !== 'DELETE'} onClick={go}>{busy ? 'Deleting…' : `Delete ${jobs.length} job card${jobs.length > 1 ? 's' : ''}`}</button></div>
  </Modal>;
}
