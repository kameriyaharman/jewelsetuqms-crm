import { ManualViewer } from './Manuals';
import { useEffect, useState } from 'react';
import { Link, useNavigate, useOutletContext, useParams } from 'react-router-dom';
import { MPage } from '../app/Layout';
import { useAuth } from '../app/auth';
import { flushQueue, pdfUrl, queue, post } from '../app/api';
import { Badge, Empty, Icon, Loader, useApi, Thumb, Modal, useToast } from '../app/ui';
import { fdate, fdt, initials, num } from '../app/util';
import { PasswordForm, ProfileHead } from './Account';

export function AppHome() {
  const { me } = useAuth(); const q = useApi('app/home'); const { online, qn } = useOutletContext(); const nav = useNavigate();
  const [search, setSearch] = useState(''); const [syncMsg, setSyncMsg] = useState('synced');
  const [view, setView] = useState(() => { try { return localStorage.getItem('qt_home_view') || 'vendor'; } catch { return 'vendor'; } });
  const pickView = (v) => { setView(v); try { localStorage.setItem('qt_home_view', v); } catch { /* ignore */ } };
  const h = new Date().getHours(); const greet = h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening';
  return <MPage title="Q-TRACK" right={<Link to="/app/alerts" style={{ color: '#fff' }}><Icon n="bell" /></Link>}>
    <div className="offbar">You are offline — everything you enter is saved on this phone and will sync automatically.</div>
    <Loader q={q}>{(d) => { const res = search ? d.skus.filter((s) => s.sku.toUpperCase().includes(search.toUpperCase())) : [];
      return <>
        <div className="mhello">{greet},<b>{me.user.name.split(' ')[0]} · {me.user.emp_code}</b></div>
        <div className="mstats"><div><b>{String(d.today).padStart(2, '0')}</b><small>Today's QC</small></div><div><b>{String(d.pending).padStart(2, '0')}</b><small>Pending SKUs</small></div><div><b>{d.completed}</b><small>Completed</small></div></div>
        <p className="muted" style={{ fontSize: 12, margin: '-4px 0 12px' }}><span style={{ color: online ? 'var(--ok)' : 'var(--warn)' }}>●</span> {online ? 'ONLINE' : 'OFFLINE'} · {syncMsg}
          {qn > 0 && <> · <b>{qn}</b> pending upload <a href="#" onClick={async (e) => { e.preventDefault(); const n = await flushQueue(); setSyncMsg(n ? `synced ${n} change(s) ✓` : 'synced'); q.reload(); }}>sync now</a></>}</p>
        {d.can_new && <Link to="/app/new" className="bigbtn alt" style={{ display: 'flex', marginBottom: 12, textDecoration: 'none' }}><Icon n="plus" /> NEW QC JOB ON SITE</Link>}
        <div className="mcard"><b>Search design / SKU</b>
          <div className="mt" style={{ display: 'flex', gap: 8 }}><input type="search" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search SKU / design no." /></div>
          <div className="mlist">{search && (res.length ? res.map((s) => <Link key={s.id} to={'/app/inspect/' + s.id}><span className="mitem"><Thumb src={s.img} size={40} /><span><b>{s.sku}</b><small>{s.product} · {s.ready_qty} pcs · {s.job_no}</small></span></span><Badge s={s.status} /></Link>) : <Empty>No matching SKU in your jobs.</Empty>)}</div></div>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', margin: '6px 0 10px' }}><h3 style={{ fontSize: 14, margin: 0 }}>My assigned QC</h3>
          <div className="seg"><button className={view === 'vendor' ? 'on' : ''} onClick={() => pickView('vendor')}>🏭 Vendor-wise</button><button className={view === 'job' ? 'on' : ''} onClick={() => pickView('job')}>Job-wise</button></div></div>
        {!d.jobs.length && <div className="mcard"><Empty>No QC jobs assigned right now.</Empty></div>}
        {view === 'vendor' && (d.groups || []).map((g) => <VendorFolder key={g.key} g={g} />)}
        {view === 'job' && d.jobs.map((j) => { const ret = +j.ret, view = !ret && +j.done === +j.skus; return <div key={j.id} className="mcard">
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 10 }}><span className="mitem"><Thumb src={j.img} size={48} /><b>{j.client_code} – {j.vendor_name.toUpperCase()}</b></span><Badge s={ret ? 'RETURNED' : j.status} /></div>
          <p className="muted" style={{ margin: '6px 0 10px', fontSize: 12.5 }}>{fdate(j.scheduled_date)} · {j.time_slot} · {j.skus} SKU · {j.location}<br />PO {j.po_no} · {j.job_no} · {j.done}/{j.skus} submitted
            {+j.hold ? <b style={{ color: '#b4561b' }}> · {j.hold} on hold</b> : null}{ret ? <b style={{ color: 'var(--ruby)' }}> · {ret} returned</b> : null}</p>
          <button className={'bigbtn ' + (j.status === 'ASSIGNED' ? '' : ret ? 'ruby' : 'alt')} onClick={() => nav('/app/job/' + j.id)}>{j.status === 'ASSIGNED' ? 'START QC' : ret ? 'FIX & RESUBMIT' : view ? 'VIEW' : 'CONTINUE QC'}</button>
        </div>; })}
      </>; }}</Loader>
  </MPage>;
}

/** Level 1: one folder card per vendor + client + location. */
function VendorFolder({ g }) {
  const nav = useNavigate(); const url = `/app/vendor/${g.vendor_id}/${g.client_id}`;
  return <div className={'mcard vfold' + (g.onsite ? ' on' : '')} onClick={() => nav(url)}>
    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 8 }}>
      <span className="mitem"><Thumb src={g.img} size={46} /><span><b>🏭 {g.vendor_name.toUpperCase()}</b><small>Client: {g.client_code} · 📍 {g.location || '—'}</small></span></span>
      <span className={'vst ' + (g.onsite ? 'on' : '')}>{g.onsite ? `ON-SITE ✓ ${g.onsite}` : 'NOT CHECKED-IN'}</span></div>
    <div className="vgrid"><div><small>Total designs</small><b>{g.designs}</b></div><div><small>Pending qty</small><b>{num(g.pending_qty)}</b></div><div><small>Done today</small><b>{String(g.today).padStart(2, '0')}</b></div><div><small>Remaining</small><b>{g.open}</b></div></div>
    {(g.ret > 0 || g.hold > 0) && <p style={{ margin: '0 0 8px', fontSize: 12 }}>{g.ret > 0 && <b style={{ color: 'var(--ruby)' }}>{g.ret} returned to fix · </b>}{g.hold > 0 && <b style={{ color: '#b4561b' }}>{g.hold} on hold</b>}</p>}
    <button className={'bigbtn ' + (g.ret ? 'ruby' : g.onsite ? '' : 'alt')} onClick={(e) => { e.stopPropagation(); nav(url); }}>OPEN VENDOR JOBS ({g.open}) →</button>
  </div>;
}

const VF = [['all', 'All open'], ['pending', 'Pending'], ['partial', 'Partial / repair'], ['hold', 'Hold'], ['done', 'Submitted']];
const DONE = ['SUBMITTED', 'UNDER_REVIEW', 'APPROVED'];
const vfOf = (r) => (r.status === 'HOLD' ? 'hold' : r.status === 'RETURNED' || r.can_add_lot || (r.lots > 1 && !DONE.includes(r.status)) ? 'partial' : DONE.includes(r.status) ? 'done' : 'pending');

/** Level 2: every design of my open jobs at one vendor — search by SKU / PO, quick filters, start / continue / add lot. */
export function AppVendor() {
  const { v, c } = useParams();
  const q = useApi('app/vendor', { v, c }); const nav = useNavigate(); const toast = useToast();
  const [s, setS] = useState(''); const [f, setF] = useState('all'); const [busy, setBusy] = useState(false);
  const [lot, setLot] = useState(null); const [mv, setMv] = useState(null); const [manOpen, setManOpen] = useState(false); const [ackTick, setAckTick] = useState(false);
  return <Loader q={q}>{(d) => {
    const lock = !!(d.geo?.required && !d.checkin);
    const needAck = !d.acked && d.manuals.some((m) => m.priority === 'HIGH');
    const cnt = { all: 0, pending: 0, partial: 0, hold: 0, done: 0 };
    d.rows.forEach((r) => { const k = vfOf(r); cnt[k]++; if (k !== 'done') cnt.all++; });
    const Q = s.trim().toUpperCase();
    const rows = d.rows.filter((r) => (f === 'all' ? vfOf(r) !== 'done' : vfOf(r) === f) && (!Q || r.sku.toUpperCase().includes(Q) || String(r.po_no).toUpperCase().includes(Q) || String(r.job_no).toUpperCase().includes(Q)));
    const open = (r) => { if (lock && !DONE.includes(r.status)) { toast('Check-in at the factory first', 'warn'); window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
      if (needAck && r.status === 'PENDING') { setManOpen(true); return; } nav('/app/inspect/' + r.id); };
    const addLot = async () => { setBusy(true); try { const x = await post('app/newjob', { lot_of: lot.root, ready_qty: lot.ready }); toast(x.message); nav('/app/inspect/' + x.first); } catch (e) { toast(e.message, 'err'); } setBusy(false); };
    const ack = async () => { setBusy(true); try { const x = await post('manuals/ack', { job_ids: d.jobs.map((j) => j.id) }); toast(x.message); setManOpen(false); q.reload(); } catch (e) { toast(e.message, 'err'); } setBusy(false); };
    const btn = (r) => r.status === 'PENDING' ? 'START QC' : r.status === 'RETURNED' ? 'FIX' : DONE.includes(r.status) ? 'VIEW' : r.status === 'HOLD' ? 'VIEW' : 'CONTINUE';
    return <MPage title={d.vendor.name} back="/app">
      <div className="mcard" style={{ paddingBottom: 10 }}><b>🏭 {d.vendor.name} <span className="muted" style={{ fontWeight: 500 }}>· Client {d.vendor.client_code}</span></b>
        <p className="muted" style={{ fontSize: 12, margin: '4px 0 0' }}>📍 {d.vendor.location}{d.vendor.address ? ' — ' + d.vendor.address : ''} · {d.jobs.length} job card{d.jobs.length > 1 ? 's' : ''} · {d.rows.length} design{d.rows.length > 1 ? 's' : ''}</p></div>
      {d.manuals.length > 0 && <button className="manalert" onClick={() => setManOpen(true)}><span>⚠️ <b>Client QC Manual &amp; Special Instructions</b> ({d.manuals.length} file{d.manuals.length > 1 ? 's' : ''}){needAck ? ' — read & confirm before START QC' : ''}</span><b>TAP TO VIEW</b></button>}
      <CheckIn d={{ ...d, job: d.job }} reload={q.reload} />
      {lock && <div className="note" style={{ marginBottom: 10, fontSize: 12.5 }}>🔒 <b>Step 1: Check-in at the factory.</b> Designs open for inspection after check-in.</div>}
      <div className="vsearch"><input type="search" placeholder="Search SKU / PO no. / job no." value={s} onChange={(e) => setS(e.target.value)} /></div>
      <div className="pills">{VF.map(([k, l]) => <button key={k} className={f === k ? 'on' : ''} onClick={() => setF(k)}>{l} ({cnt[k]})</button>)}</div>
      <div className="mcard" style={{ padding: '4px 12px' }}>{!rows.length ? <Empty>{Q ? 'No design matches this search.' : 'Nothing here.'}</Empty> : rows.map((r) => <div key={r.root} className="vrow">
        <span onClick={() => open(r)} style={{ cursor: 'pointer' }}><Thumb src={r.img} size={54} alt={r.sku} /></span>
        <div style={{ flex: 1, minWidth: 0 }} onClick={() => open(r)}><b className="mono" style={{ fontSize: 13.5 }}>{r.sku}</b>{r.lots > 1 && <small style={{ display: 'inline' }}> · Lot {r.lot}</small>}
          <small>PO {r.po_no} · {r.job_no}</small>
          <small><b style={{ color: r.pending ? '#b4561b' : 'var(--ok)' }}>{num(r.pending)}</b> pending / {num(r.order)} order{r.accepted ? ` · OK ${num(r.accepted)}` : ''}</small>
          {r.instructions && <small className="vins">📝 {r.instructions}</small>}</div>
        <div className="vact"><Badge s={r.status} />
          {r.can_add_lot && d.can_lot ? <button className="btn sm" onClick={() => setLot({ root: r.root, sku: r.sku, pending: r.pending, ready: r.pending })}>+ ADD LOT</button>
            : <button className={'btn sm ' + (r.status === 'PENDING' ? 'pri' : '')} style={lock && !DONE.includes(r.status) ? { opacity: 0.55 } : {}} onClick={() => open(r)}>{btn(r)}</button>}</div>
      </div>)}</div>
      <Modal open={manOpen} title={'Client QC manual · ' + d.vendor.client_code} onClose={() => setManOpen(false)}>
        {d.manuals.map((m) => <button key={m.id} className="manitem" onClick={() => setMv(m)}><span>{m.path ? (m.ext === 'pdf' ? '📕' : ['jpg', 'jpeg', 'png', 'webp'].includes(m.ext) ? '🖼️' : m.ext.startsWith('xl') ? '📗' : '📘') : '📝'}</span>
          <span style={{ flex: 1, textAlign: 'left' }}><b>{m.title}</b> <small>v{m.version}{m.badge ? ' · ' + m.badge : ''}{m.priority === 'HIGH' ? ' · MANDATORY' : ''}</small><small style={{ display: 'block' }}>{m.scope_label}</small>{m.note && <small className="mannote" style={{ display: 'block' }}>{m.note}</small>}</span><b>›</b></button>)}
        {needAck && <><label className="chk mt" style={{ fontSize: 14 }}><input type="checkbox" checked={ackTick} onChange={(e) => setAckTick(e.target.checked)} style={{ width: 20, height: 20 }} /> ✓ I have read the Client QC Manual / Special Instructions</label>
          <button className="bigbtn gold" disabled={!ackTick || busy} onClick={ack}>CONFIRM &amp; CONTINUE</button></>}
      </Modal>
      <ManualViewer m={mv} onClose={() => setMv(null)} />
      <Modal open={!!lot} title={lot ? 'Next QC lot · ' + lot.sku : ''} onClose={() => setLot(null)}>
        {lot && <><p className="muted" style={{ marginTop: 0, fontSize: 12.5 }}>Balance <b>{num(lot.pending)}</b> pcs. Enter how many pieces the vendor presented today (fresh + repaired).</p>
          <div className="f"><label>Presented / ready qty</label><input type="number" inputMode="numeric" min="1" value={lot.ready} onChange={(e) => setLot({ ...lot, ready: e.target.value })} /></div>
          <button className="bigbtn gold" disabled={busy || !(+lot.ready > 0)} onClick={addLot}>START LOT</button></>}
      </Modal>
    </MPage>; }}</Loader>;
}

export function AppJob() {
  const { id } = useParams(); const q = useApi('app/job', { id }); const nav = useNavigate(); const [s, setS] = useState('');
  const [lot, setLot] = useState(null); const toast = useToast(); const [busy, setBusy] = useState(false);
  const [mv, setMv] = useState(null); const [manOpen, setManOpen] = useState(false); const [ackTick, setAckTick] = useState(false);
  const doAck = async () => { setBusy(true); try { const r = await post('manuals/ack', { job_id: +id }); toast(r.message); setManOpen(false); q.reload(); } catch (e) { toast(e.message, 'err'); } setBusy(false); };
  const addLot = async () => { setBusy(true); try { const r = await post('app/newjob', { lot_of: lot.root, ready_qty: lot.ready }); toast(r.message); nav('/app/inspect/' + r.first); } catch (e) { toast(e.message, 'err'); } setBusy(false); };
  return <Loader q={q}>{(d) => { const j = d.job; const first = d.items.find((i) => ['RETURNED', 'IN_PROGRESS', 'PENDING', 'COMPLETED'].includes(i.status));
    // step 1 at the factory: check-in comes before any design can be opened (when the rule is on)
    const lock = !!(d.geo?.required && !d.checkin && ['ASSIGNED', 'IN_PROGRESS', 'PARTIAL'].includes(j.status));
    const needAck = !d.acked && (d.manuals || []).some((m) => m.priority === 'HIGH');
    const gate = (e, st) => { if (!['PENDING'].includes(st) && !(lock && st === 'IN_PROGRESS')) return; if (lock) { e.preventDefault(); toast('Check-in at the factory first', 'warn'); window.scrollTo({ top: 0, behavior: 'smooth' }); } else if (needAck && st === 'PENDING') { e.preventDefault(); setManOpen(true); } };
    return <MPage title={j.job_no} back="/app">
      <div className="mcard"><div className="mgrid2">
        <div><small>Client</small><b>{j.client_code}</b></div><div><small>Vendor</small><b>{j.vendor_name}</b></div>
        <div><small>QC date</small><b>{fdate(j.scheduled_date, 'd-m-Y')} {j.time_slot}</b></div><div><small>Type</small><b>{j.qc_type}</b></div>
        <div><small>Total SKU</small><b>{d.items.filter((i) => !i.parent_id).length}</b></div><div><small>Order qty</small><b>{num(d.items.filter((i) => !i.parent_id).reduce((a, i) => a + +i.order_qty, 0))} pcs</b></div>
        <div><small>PO</small><b>{j.po_no}</b></div><div><small>Scope</small><b>{j.scope_label}</b></div></div>
        <p className="muted" style={{ fontSize: 12, margin: '10px 0 0' }}>📍 {j.location} — {j.vendor_address}</p>
        {j.special_req && <div className="note mt" style={{ fontSize: 12 }}><b>Special:</b> {j.special_req}</div>}
        {!!d.attachments.length && <div className="mt">{d.attachments.map((a) => <a key={a.id} className="pill" target="_blank" rel="noreferrer" href={a.url}>📎 {a.name}</a>)}</div>}
      </div>
      {d.manuals?.length > 0 && <button className="manalert" onClick={() => setManOpen(true)}><span>⚠️ <b>Client QC Manual &amp; Special Instructions</b> ({d.manuals.length} file{d.manuals.length > 1 ? 's' : ''}){needAck ? ' — read & confirm before START QC' : d.acked && d.manuals.some((m) => m.priority === 'HIGH') ? ' — read ✓' : ''}</span><b>TAP TO VIEW</b></button>}
      <CheckIn d={d} reload={q.reload} />
      {lock && <div className="note" style={{ marginBottom: 10, fontSize: 12.5 }}>🔒 <b>Step 1: Check-in at the factory.</b> Designs open for inspection after check-in.</div>}
      <input type="search" placeholder="Search SKU…" value={s} onChange={(e) => setS(e.target.value)} style={{ marginBottom: 10 }} />
      {j.status === 'PARTIAL' && <div className="note" style={{ background: '#fff1e0', borderColor: '#f0c79a', color: '#8a4310', marginBottom: 10 }}><b>Partial QC done — job open.</b> When the vendor presents the balance or repaired pieces, tap <b>+ Next QC lot</b> on the SKU.</div>}
      <div className="mcard"><b>Product list</b><div className="mlist">{d.items.filter((i) => i.sku.toUpperCase().includes(s.toUpperCase())).map((i) => { const L = d.lines[i.id]; return <div key={i.id}>
        <Link to={'/app/inspect/' + i.id} onClick={(e) => gate(e, i.status)} style={lock && ['PENDING', 'IN_PROGRESS'].includes(i.status) ? { opacity: 0.55 } : {}}><span className="mitem">{i.parent_id ? <span style={{ width: 52, textAlign: 'center', color: 'var(--mut)' }}>↳</span> : <Thumb src={i.img} size={52} alt={i.sku} />}<span><b className="mono">{i.sku}</b>{(i.parent_id || (L && L.lots > 1)) && <small style={{ display: 'inline' }}> · Lot {i.lot_seq || 1}</small>}<small>{i.product} · {num(i.ready_qty)} pcs · insp {num(i.insp_qty)}</small>
          {L && (L.lots > 1 || (L.accepted > 0 && !L.complete)) && <span className="cum"><span>Order {num(L.order)}</span><span className="ok">OK {num(L.accepted)}</span><span className={L.pending ? 'p' : ''}>Bal {num(L.pending)}</span></span>}</span></span><Badge s={i.status} /></Link>
        {L && L.can_add_lot && d.can_lot && <button className="bigbtn alt" style={{ margin: '0 0 8px', padding: '9px', fontSize: 13 }} onClick={() => setLot({ root: i.id, sku: i.sku, pending: L.pending, ready: L.pending })}><Icon n="plus" /> NEXT QC LOT / RE-INSPECTION</button>}</div>; })}</div></div>
      <Modal open={manOpen} title={'Client QC manual · ' + j.client_code} onClose={() => setManOpen(false)}>
        {(d.manuals || []).map((m) => <button key={m.id} className="manitem" onClick={() => setMv(m)}><span>{m.path ? (m.ext === 'pdf' ? '📕' : ['jpg', 'jpeg', 'png', 'webp'].includes(m.ext) ? '🖼️' : m.ext.startsWith('xl') ? '📗' : '📘') : '📝'}</span>
          <span style={{ flex: 1, textAlign: 'left' }}><b>{m.title}</b> <small>v{m.version}{m.badge ? ' · ' + m.badge : ''}{m.priority === 'HIGH' ? ' · MANDATORY' : ''}</small><small style={{ display: 'block' }}>{m.scope_label}</small>{m.note && <small className="mannote" style={{ display: 'block' }}>{m.note}</small>}</span><b>›</b></button>)}
        {needAck && <><label className="chk mt" style={{ fontSize: 14 }}><input type="checkbox" checked={ackTick} onChange={(e) => setAckTick(e.target.checked)} style={{ width: 20, height: 20 }} /> ✓ I have read the Client QC Manual / Special Instructions</label>
          <button className="bigbtn gold" disabled={!ackTick || busy} onClick={doAck}>CONFIRM &amp; CONTINUE</button></>}
      </Modal>
      <ManualViewer m={mv} onClose={() => setMv(null)} />
      <Modal open={!!lot} title={lot ? 'Next QC lot · ' + lot.sku : ''} onClose={() => setLot(null)}>
        {lot && <><p className="muted" style={{ marginTop: 0, fontSize: 12.5 }}>Balance <b>{num(lot.pending)}</b> pcs. Specs, images and files are already filled — enter how many pieces the vendor presented today (fresh + repaired).</p>
          <div className="f"><label>Presented / ready qty</label><input type="number" inputMode="numeric" min="1" value={lot.ready} onChange={(e) => setLot({ ...lot, ready: e.target.value })} /></div>
          <button className="bigbtn gold" disabled={busy || !(+lot.ready > 0)} onClick={addLot}>START LOT</button></>}
      </Modal>
      {d.can_add && !lock && <Link to={'/app/job/' + j.id + '/add'} className="bigbtn alt" style={{ display: 'flex', textDecoration: 'none', marginBottom: 8 }}><Icon n="plus" /> ADD SKU ON SITE</Link>}
      {lock ? null : first ? <button className="bigbtn" onClick={() => (needAck && first.status === 'PENDING' ? setManOpen(true) : nav('/app/inspect/' + first.id))}>{first.status === 'PENDING' ? 'START INSPECTION' : 'CONTINUE · ' + first.sku}</button>
        : j.status === 'PARTIAL' ? null : <div className="qtyok" style={{ textAlign: 'center' }}>All SKUs submitted — waiting for review. You can still open a SKU to correct it until it is approved.</div>}
    </MPage>; }}</Loader>;
}

export function AppHistory() {
  const [f, setF] = useState('ALL'); const q = useApi('app/history', { f });
  return <MPage title="My QC history">
    <div className="tabs">{[['ALL', 'All'], ['SUBMITTED', 'Submitted'], ['RETURNED', 'Returned'], ['APPROVED', 'Approved']].map(([k, l]) => <a key={k} href="#" className={f === k ? 'on' : ''} onClick={(e) => { e.preventDefault(); setF(k); }}>{l}</a>)}</div>
    <Loader q={q}>{(d) => <div className="mcard"><div className="mlist">{!d.rows.length && <Empty>Nothing here yet.</Empty>}
      {d.rows.map((r) => r.status === 'APPROVED' ? <a key={r.id} href={pdfUrl({ item: r.id })} target="_blank" rel="noreferrer"><span className="mitem"><Thumb src={r.img} size={40} /><span><b className="mono">{r.report_no}</b><small>{r.vendor_name} · {r.client_code} · {fdate(r.approved_at, 'd M')} · {r.sku}</small></span></span><Badge s={r.status} /></a>
        : <Link key={r.id} to={'/app/inspect/' + r.id}><span className="mitem"><Thumb src={r.img} size={40} /><span><b className="mono">{r.sku}</b><small>{r.vendor_name} · {r.client_code} · {fdate(r.submitted_at, 'd M')} · {r.job_no}</small></span></span><Badge s={r.status} /></Link>)}</div></div>}</Loader>
  </MPage>;
}

export function AppAlerts() {
  const q = useApi('notifications'); const { refresh } = useAuth();
  useEffect(() => { if (q.data) refresh(); /* eslint-disable-next-line */ }, [q.data]);
  return <MPage title="Notifications"><Loader q={q}>{(d) => <>
    {d.returned.map((r) => <div key={r.id} className="mcard" style={{ borderColor: '#efc3ca' }}><b style={{ color: 'var(--ruby)' }}>Inspection returned for correction</b>
      <p style={{ margin: '6px 0', fontSize: 13 }}>{r.job_no} · {r.sku} — {r.return_reason}</p>
      <p style={{ fontSize: 12, margin: '0 0 8px' }}>{String(r.return_sections || '').split(',').filter((x) => x.trim()).map((x) => <span key={x}><Badge s="RETURNED" /> {x.trim()}<br /></span>)}</p>
      <Link className="bigbtn ruby" to={'/app/inspect/' + r.id}>FIX &amp; RESUBMIT</Link></div>)}
    <div className="mcard"><div className="mlist">{!d.items.length && <Empty>No notifications.</Empty>}
      {d.items.map((n) => <Link key={n.id} to={'/' + (n.link || 'app')}><span><b style={{ fontWeight: +n.is_read ? 500 : 700 }}>{n.text}</b><small>{fdt(n.created_at)}</small></span></Link>)}</div></div>
  </>}</Loader></MPage>;
}

export function AppProfile() {
  const { me, logout } = useAuth(); const q = useApi('profile'); const nav = useNavigate(); const [qn, setQn] = useState(queue.all().length);
  return <MPage title="Profile"><Loader q={q}>{(d) => <>
    <div className="mcard" style={{ textAlign: 'center' }}><ProfileHead />
      <div className="mstats"><div><b>{d.kpi.assigned}</b><small>Assigned</small></div><div><b>{d.kpi.done}</b><small>Completed</small></div><div><b>{Math.round(d.kpi.first)}%</b><small>Approval</small></div></div></div>
    <div className="mcard"><b>Offline data &amp; sync</b><p className="muted" style={{ fontSize: 12.5 }}>Pending uploads: <b>{qn}</b></p>
      <button className="bigbtn alt" onClick={async () => { await flushQueue(); setQn(queue.all().length); }}>SYNC NOW</button>
      <button className="bigbtn alt mt" onClick={() => { if (window.confirm('Clear local drafts on this phone?')) { Object.keys(localStorage).filter((k) => k.startsWith('qt_draft_')).forEach((k) => localStorage.removeItem(k)); alert('Cleared'); } }}>Clear local drafts</button></div>
    <div className="mcard"><b>Change password</b><div className="mt"><PasswordForm mobile /></div></div>
    <button className="bigbtn ruby" onClick={async () => { await logout(); nav('/login'); }}>Logout</button>
  </>}</Loader></MPage>;
}

/** Geo-fenced factory check-in: inspector proves arrival at the vendor gate (live GPS within the factory radius). */
function CheckIn({ d, reload }) {
  const toast = useToast(); const [busy, setBusy] = useState(false); const c = d.checkin; const g = d.geo;
  if (!g || ['SUBMITTED', 'UNDER_REVIEW', 'APPROVED', 'EMAILED', 'CLOSED'].includes(d.job.status)) return null;
  const send = async (out) => {
    setBusy(true);
    try {
      if (out) { const r = await post('app/checkin', { job_id: d.job.id, out: 1 }); toast(r.message); reload(); setBusy(false); return; }
      if (!navigator.geolocation) throw new Error('This phone/browser cannot share location.');
      const pos = await new Promise((ok, no) => navigator.geolocation.getCurrentPosition(ok, (e) => no(new Error(e.code === 1 ? 'Location permission denied — allow location for this site in browser settings and try again.' : 'Could not get GPS location — switch on location / GPS and try again outdoors.')), { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 }));
      const r = await post('app/checkin', { job_id: d.job.id, lat: pos.coords.latitude, lng: pos.coords.longitude, acc: Math.round(pos.coords.accuracy || 0) });
      toast(r.message, r.verified === false ? 'warn' : undefined); reload();
    } catch (e) { toast(e.message, 'err'); }
    setBusy(false);
  };
  if (c) return <div className="mcard" style={{ borderColor: '#9cc3e6', background: '#f1f7fd' }}><b style={{ color: '#1f5f99' }}>🔵 ON-SITE · checked in {new Date(c.at.replace(' ', 'T')).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</b>
    <small style={{ display: 'block', margin: '3px 0 8px', color: 'var(--mut)' }}>{+c.verified ? `GPS verified ✓ · ${c.dist_m} m from factory pin` : 'Factory pin not set yet — your location was sent to Admin'}</small>
    <button className="btn sm" disabled={busy} onClick={() => send(true)}>Check-out from factory</button></div>;
  return <div className="mcard"><b>Arrived at the factory?</b><small style={{ display: 'block', margin: '3px 0 8px', color: 'var(--mut)' }}>{g.pin ? `Check-in works within ${g.radius} m of the factory gate.` : 'This factory has no GPS pin yet — your first check-in location is sent to Admin to verify.'}{g.required ? ' Required before starting inspection.' : ''}{d.checkin_other ? ` You are still checked in at ${d.checkin_other} — it will be closed.` : ''}</small>
    <button className="bigbtn gold" style={{ margin: 0 }} disabled={busy} onClick={() => send(false)}>{busy ? 'Getting GPS location…' : '📍 CHECK-IN AT VENDOR'}</button></div>;
}
