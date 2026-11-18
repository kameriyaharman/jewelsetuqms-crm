import { useEffect, useState } from 'react';
import { Link, useNavigate, useOutletContext, useParams } from 'react-router-dom';
import { MPage } from '../app/Layout';
import { useAuth } from '../app/auth';
import { flushQueue, pdfUrl, queue, post } from '../app/api';
import { Badge, Empty, Icon, Loader, useApi, Thumb, Modal, useToast } from '../app/ui';
import { fdate, fdt, initials, num } from '../app/util';
import { PasswordForm } from './Account';

export function AppHome() {
  const { me } = useAuth(); const q = useApi('app/home'); const { online, qn } = useOutletContext(); const nav = useNavigate();
  const [search, setSearch] = useState(''); const [syncMsg, setSyncMsg] = useState('synced');
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
        <h3 style={{ fontSize: 14, margin: '6px 0 10px' }}>My assigned QC</h3>
        {!d.jobs.length && <div className="mcard"><Empty>No QC jobs assigned right now.</Empty></div>}
        {d.jobs.map((j) => { const ret = +j.ret, view = !ret && +j.done === +j.skus; return <div key={j.id} className="mcard">
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 10 }}><span className="mitem"><Thumb src={j.img} size={48} /><b>{j.client_code} – {j.vendor_name.toUpperCase()}</b></span><Badge s={ret ? 'RETURNED' : j.status} /></div>
          <p className="muted" style={{ margin: '6px 0 10px', fontSize: 12.5 }}>{fdate(j.scheduled_date)} · {j.time_slot} · {j.skus} SKU · {j.location}<br />PO {j.po_no} · {j.job_no} · {j.done}/{j.skus} submitted
            {+j.hold ? <b style={{ color: '#b4561b' }}> · {j.hold} on hold</b> : null}{ret ? <b style={{ color: 'var(--ruby)' }}> · {ret} returned</b> : null}</p>
          <button className={'bigbtn ' + (j.status === 'ASSIGNED' ? '' : ret ? 'ruby' : 'alt')} onClick={() => nav('/app/job/' + j.id)}>{j.status === 'ASSIGNED' ? 'START QC' : ret ? 'FIX & RESUBMIT' : view ? 'VIEW' : 'CONTINUE QC'}</button>
        </div>; })}
      </>; }}</Loader>
  </MPage>;
}

export function AppJob() {
  const { id } = useParams(); const q = useApi('app/job', { id }); const nav = useNavigate(); const [s, setS] = useState('');
  const [lot, setLot] = useState(null); const toast = useToast(); const [busy, setBusy] = useState(false);
  const addLot = async () => { setBusy(true); try { const r = await post('app/newjob', { lot_of: lot.root, ready_qty: lot.ready }); toast(r.message); nav('/app/inspect/' + r.first); } catch (e) { toast(e.message, 'err'); } setBusy(false); };
  return <Loader q={q}>{(d) => { const j = d.job; const first = d.items.find((i) => ['RETURNED', 'IN_PROGRESS', 'PENDING', 'COMPLETED'].includes(i.status));
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
      <input type="search" placeholder="Search SKU…" value={s} onChange={(e) => setS(e.target.value)} style={{ marginBottom: 10 }} />
      {j.status === 'PARTIAL' && <div className="note" style={{ background: '#fff1e0', borderColor: '#f0c79a', color: '#8a4310', marginBottom: 10 }}><b>Partial QC done — job open.</b> When the vendor presents the balance or repaired pieces, tap <b>+ Next QC lot</b> on the SKU.</div>}
      <div className="mcard"><b>Product list</b><div className="mlist">{d.items.filter((i) => i.sku.toUpperCase().includes(s.toUpperCase())).map((i) => { const L = d.lines[i.id]; return <div key={i.id}>
        <Link to={'/app/inspect/' + i.id}><span className="mitem">{i.parent_id ? <span style={{ width: 52, textAlign: 'center', color: 'var(--mut)' }}>↳</span> : <Thumb src={i.img} size={52} alt={i.sku} />}<span><b className="mono">{i.sku}</b>{(i.parent_id || (L && L.lots > 1)) && <small style={{ display: 'inline' }}> · Lot {i.lot_seq || 1}</small>}<small>{i.product} · {num(i.ready_qty)} pcs · insp {num(i.insp_qty)}</small>
          {L && (L.lots > 1 || !L.complete) && <span className="cum"><span>Order {num(L.order)}</span><span className="ok">OK {num(L.accepted)}</span><span className={L.pending ? 'p' : ''}>Bal {num(L.pending)}</span></span>}</span></span><Badge s={i.status} /></Link>
        {L && L.can_add_lot && d.can_lot && <button className="bigbtn alt" style={{ margin: '0 0 8px', padding: '9px', fontSize: 13 }} onClick={() => setLot({ root: i.id, sku: i.sku, pending: L.pending, ready: L.pending })}><Icon n="plus" /> NEXT QC LOT / RE-INSPECTION</button>}</div>; })}</div></div>
      <Modal open={!!lot} title={lot ? 'Next QC lot · ' + lot.sku : ''} onClose={() => setLot(null)}>
        {lot && <><p className="muted" style={{ marginTop: 0, fontSize: 12.5 }}>Balance <b>{num(lot.pending)}</b> pcs. Specs, images and files are already filled — enter how many pieces the vendor presented today (fresh + repaired).</p>
          <div className="f"><label>Presented / ready qty</label><input type="number" inputMode="numeric" min="1" value={lot.ready} onChange={(e) => setLot({ ...lot, ready: e.target.value })} /></div>
          <button className="bigbtn gold" disabled={busy || !(+lot.ready > 0)} onClick={addLot}>START LOT</button></>}
      </Modal>
      {d.can_add && <Link to={'/app/job/' + j.id + '/add'} className="bigbtn alt" style={{ display: 'flex', textDecoration: 'none', marginBottom: 8 }}><Icon n="plus" /> ADD SKU ON SITE</Link>}
      {first ? <button className="bigbtn" onClick={() => nav('/app/inspect/' + first.id)}>{first.status === 'PENDING' ? 'START INSPECTION' : 'CONTINUE · ' + first.sku}</button>
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
    <div className="mcard" style={{ textAlign: 'center' }}><div className="avatar" style={{ width: 64, height: 64, fontSize: 22, margin: '0 auto 8px', display: 'grid' }}>{initials(me.user.name)}</div>
      <b style={{ fontSize: 17 }}>{me.user.name}</b><p className="muted" style={{ margin: '2px 0 0' }}>{me.user.emp_code} · Inspector · {me.user.location}</p>
      <div className="mstats"><div><b>{d.kpi.assigned}</b><small>Assigned</small></div><div><b>{d.kpi.done}</b><small>Completed</small></div><div><b>{Math.round(d.kpi.first)}%</b><small>Approval</small></div></div></div>
    <div className="mcard"><b>Offline data &amp; sync</b><p className="muted" style={{ fontSize: 12.5 }}>Pending uploads: <b>{qn}</b></p>
      <button className="bigbtn alt" onClick={async () => { await flushQueue(); setQn(queue.all().length); }}>SYNC NOW</button>
      <button className="bigbtn alt mt" onClick={() => { if (window.confirm('Clear local drafts on this phone?')) { Object.keys(localStorage).filter((k) => k.startsWith('qt_draft_')).forEach((k) => localStorage.removeItem(k)); alert('Cleared'); } }}>Clear local drafts</button></div>
    <div className="mcard"><b>Change password</b><div className="mt"><PasswordForm mobile /></div></div>
    <button className="bigbtn ruby" onClick={async () => { await logout(); nav('/login'); }}>Logout</button>
  </>}</Loader></MPage>;
}
