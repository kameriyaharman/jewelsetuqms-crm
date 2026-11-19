import { useEffect, useMemo, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { MPage } from '../app/Layout';
import { flushQueue, get, inspectSend, post } from '../app/api';
import { Badge, Icon, Modal, Spinner, useToast, Thumb } from '../app/ui';
import { compressImage, fdt, ITEM_STATUS, num, today } from '../app/util';

const mres = (m) => { if (m.actual === '' || m.actual == null || m.spec === '' || m.spec == null) return ''; return Math.abs(parseFloat(m.actual) - parseFloat(m.spec)) <= Math.abs(parseFloat(m.tol) || 0) + 1e-9 ? 'PASS' : 'FAIL'; };
const PFN = ({ value, onChange, opts = ['PASS', 'FAIL', 'NA'], labels, disabled, cols }) =>
  <div className="pfn" style={cols ? { gridTemplateColumns: `repeat(${cols},1fr)` } : null}>{opts.map((v, i) => <label key={v}><input type="radio" disabled={disabled} checked={value === v} onChange={() => onChange(v)} value={v} />{labels ? labels[i] : v}</label>)}</div>;
const SEV = ({ value, onChange, opts, disabled }) => <div className="sev" style={{ gridTemplateColumns: `repeat(${opts.length},1fr)` }}>{opts.map((v) => <label key={v}><input type="radio" disabled={disabled} checked={value === v} onChange={() => onChange(v)} />{v}</label>)}</div>;
const KINDS = [['defect', 'Defect evidence'], ['product', 'Actual product image'], ['xrf', 'XRF micron report'], ['weight', 'Digital weight scale'], ['packaging', '925 stamping / barcode / packaging'], ['dimension', 'Dimension'], ['other', 'Other']];

export default function AppInspect() {
  const { id } = useParams(); const nav = useNavigate(); const toast = useToast();
  const [D, setD] = useState(null); const [S, setS] = useState(null); const [err, setErr] = useState('');
  const [step, setStep] = useState(1); const [saved, setSaved] = useState(''); const [hold, setHold] = useState(false); const [hf, setHf] = useState({ for: 'client', reason: '' });
  const [ph, setPh] = useState({ kind: 'defect', remark: '' }); const [busy, setBusy] = useState(false);
  const [info, setInfo] = useState(null); const [regen, setRegen] = useState(null);
  const DK = 'qt_draft_' + id;

  useEffect(() => { (async () => {
    try {
      const d = await get('app/inspect', { item: id }); const it = d.item; setD(d);
      let s = { insp_date: it.insp_date || today(), reinspection: !!+it.reinspection, lot_no: it.lot_no || '', xrf_machine: it.xrf_machine || 'Goldscope SD 550', master_sample: it.master_sample || 'Approved Master Sample Available', gps: it.gps || '',
        inspected_qty: +it.inspected_qty || +it.insp_qty, accepted_qty: +it.inspected_qty ? it.accepted_qty : '', rejected_qty: +it.inspected_qty ? it.rejected_qty : '',
        checks: d.checks, tests: d.tests, meas: d.meas, defects: d.defects, photos: d.photos, result: it.inspector_result || '', remark: it.remark || '', action_required: it.action_required || '',
        factory_coop: it.factory_coop || 'GOOD', factory_line: it.factory_line || 'GOOD', factory_ready: it.factory_ready || 'GOOD', started: !!it.started_at || d.admin,
        insp_type: it.insp_type || (+it.reinspection ? 'RE-INSPECTION' : 'REGULAR'), spec_check: it.spec_check || '', spec_remark: it.spec_remark || '', factory_rep: it.factory_rep || '' };
      if (d.editable && !d.admin) { try { const dr = JSON.parse(localStorage.getItem(DK) || 'null'); if (dr) s = { ...s, ...dr, photos: s.photos }; } catch { /* ignore */ } }
      setS(s);
      const ret = it.status === 'RETURNED' ? String(it.return_sections || '').split(',').map((x) => x.trim()).filter(Boolean) : [];
      setStep(ret.length ? Math.max(1, d.steps.indexOf(ret[0]) + 1) : Math.max(1, Math.min(8, +it.step || 1)));
      setSaved(it.updated_at ? 'Saved ' + fdt(it.updated_at).split('· ')[1] : '');
    } catch (e) { setErr(e.message); }
  })(); /* eslint-disable-next-line */ }, [id]);

  useEffect(() => { if (S && D?.editable && !D?.admin) { const { photos, ...rest } = S; try { localStorage.setItem(DK, JSON.stringify(rest)); } catch { /* full */ } } /* eslint-disable-next-line */ }, [S]);

  const sev = useMemo(() => { const t = { CRITICAL: 0, MAJOR: 0, MINOR: 0 }; (S?.defects || []).forEach((d) => { t[d.severity] = (t[d.severity] || 0) + (+d.qty || 0); }); return t; }, [S?.defects]);
  if (err) return <MPage title="Inspection" back="/app"><div className="flash f-err">{err}</div></MPage>;
  if (!D || !S) return <MPage title="Inspection" back="/app"><Spinner /></MPage>;
  const it = D.item, job = D.job, ed = D.editable;
  const set = (k, v) => setS((x) => ({ ...x, [k]: v }));
  const setArr = (k, i, f, v) => setS((x) => ({ ...x, [k]: x[k].map((r, j) => (j === i ? { ...r, [f]: v } : r)) }));
  const insp = +S.inspected_qty || 0, acc = +S.accepted_qty || 0, rej = +S.rejected_qty || 0;
  const qtyOk = acc + rej === insp && insp > 0; const qtyTouched = S.accepted_qty !== '' || S.rejected_qty !== '';
  const totDef = S.defects.reduce((a, d) => a + (+d.qty || 0), 0);
  const sys = (() => {
    if (D.aql && D.plan) { for (const k of ['critical', 'major', 'minor']) { const p = D.plan[k]; if (p && p.re != null && sev[k.toUpperCase()] >= p.re) return ['FAIL', `${k} ${sev[k.toUpperCase()]} ≥ Re ${p.re}`]; } return ['PASS', 'within AQL'] }
    const rp = (rej * 100) / Math.max(1, insp); return rp >= D.threshold ? ['FAIL', `Rej/Repair ${rp.toFixed(1)}% ≥ ${D.threshold}%`] : ['PASS', `Rej/Repair ${rp.toFixed(1)}% < ${D.threshold}% — defective pcs segregated`];
  })();

  const collect = (s) => {
    if (s === 1) return { insp_type: S.insp_type, insp_date: S.insp_date, reinspection: S.reinspection, gps: S.gps, lot_no: S.lot_no, xrf_machine: S.xrf_machine, master_sample: S.master_sample };
    if (s === 2) return { inspected_qty: insp, accepted_qty: acc, rejected_qty: rej };
    if (s === 3) return { checks: S.checks }; if (s === 4) return { meas: S.meas.map((m) => ({ ...m, result: mres(m) })) };
    if (s === 5) return { tests: S.tests }; if (s === 6) return { defects: S.defects };
    if (s === 8) return { result: S.result || sys[0], remark: S.remark, action_required: S.action_required, factory_coop: S.factory_coop, factory_line: S.factory_line, factory_ready: S.factory_ready, spec_check: S.spec_check, spec_remark: S.spec_remark, factory_rep: S.factory_rep };
    return null;
  };
  const saveStep = async (s) => {
    if (!ed) return true; const data = collect(s); if (!data) return true;
    if (s === 2 && !qtyOk) { toast('Fix quantity mismatch first', 'err'); return false; }
    try { const r = D.admin ? await post('app/inspect', { action: 'save', item_id: it.id, step: s, data }) : await inspectSend({ action: 'save', item_id: it.id, step: s, data });
      setSaved(r.queued ? 'Saved on phone (offline)' : 'Saved ' + (r.saved_at || '')); return true; }
    catch (e) { toast(e.message, 'err'); return false; }
  };
  const go = (n) => { if (n >= 1 && n <= 9) { setStep(n); window.scrollTo(0, 0); } };
  const start = () => {
    const done = (gps) => { set('gps', gps); set('started', true); inspectSend({ action: 'start', item_id: it.id, gps }); };
    const t = () => new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    if (D.gps && navigator.geolocation) navigator.geolocation.getCurrentPosition((p) => done(`${p.coords.latitude.toFixed(4)}, ${p.coords.longitude.toFixed(4)} · ${t()}`), () => done('location denied · ' + t()), { timeout: 8000 });
    else done('');
  };
  const addPhotos = async (files) => {
    for (const f of files) {
      try {
        const data = await compressImage(f); const tmp = { id: 0, kind: ph.kind, remark: ph.remark, url: data, pending: true };
        setS((x) => ({ ...x, photos: [...x.photos, tmp] }));
        const r = await inspectSend({ action: 'photo', item_id: it.id, kind: ph.kind, remark: ph.remark, data });
        if (!r.queued) setS((x) => ({ ...x, photos: x.photos.map((p) => (p === tmp ? { ...p, id: r.id, url: r.url, pending: false } : p)) }));
      } catch (e) { toast(e.message || 'Photo failed', 'err'); }
    }
  };
  const delPhoto = async (p) => { if (!window.confirm('Delete photo?')) return; await inspectSend({ action: 'photo_delete', item_id: it.id, photo_id: p.id }); setS((x) => ({ ...x, photos: x.photos.filter((y) => y !== p) })); };
  const submit = async () => {
    if (!(await saveStep(8))) return;
    if (!navigator.onLine) { toast('You are offline — data is saved. Submit when back online.', 'warn'); return; }
    setBusy(true); await flushQueue();
    try { const r = await inspectSend({ action: 'submit', item_id: it.id }); localStorage.removeItem(DK); toast(D.after_submit ? 'Changes resubmitted for review ✓' : 'Submitted for review ✓');
      if (D.admin) nav('/review/' + it.id); else nav(r.next ? '/app/inspect/' + r.next : '/app/job/' + job.id); }
    catch (e) { toast(e.message, 'err'); }
    setBusy(false);
  };
  const doHold = async () => {
    if (!hf.reason.trim()) { toast('Write what needs approval', 'err'); return; }
    await saveStep(step);
    try { const r = await inspectSend({ action: 'hold', item_id: it.id, hold_for: hf.for, reason: hf.reason }); toast(r.queued ? 'Saved — hold will be sent when online.' : `SKU put on HOLD — ${hf.for} notified.`); nav('/app/job/' + job.id); }
    catch (e) { toast(e.message, 'err'); }
  };
  const ret = it.status === 'RETURNED' ? String(it.return_sections || '').split(',').map((x) => x.trim()) : [];
  const defPhotos = S.photos.filter((p) => p.kind === 'defect').length;
  const catList = Object.keys(D.dm);
  const plan = D.plan;
  const saveInfo = async () => {
    try { const r = await post('app/inspect', { action: 'item_info', item_id: it.id, data: info }); toast(r.changed ? 'Product details saved' : 'No changes'); setInfo(null);
      const d = await get('app/inspect', { item: id }); setD(d); } catch (e) { toast(e.message, 'err'); }
  };
  const doRegen = async () => {
    try { await saveStep(step <= 8 ? step : 8); const r = await post('app/inspect', { action: 'regen', item_id: it.id, ...regen }); toast(r.message); setRegen(null); nav('/report/' + it.id); } catch (e) { toast(e.message, 'err'); }
  };
  // pre-submit review: every section with what is filled / missing
  const cnt = (arr, v) => arr.filter((c) => c.result === v).length;
  const blankChecks = S.checks.filter((c) => !c.result).map((c) => c.name);
  const blankTests = S.tests.filter((c) => !c.result).map((c) => c.name);
  const noActual = S.meas.filter((m) => m.param && (m.actual === '' || m.actual == null)).map((m) => m.param);
  const REV = [
    { s: 1, t: 'General info', st: S.insp_date ? 'ok' : 'miss', d: `${S.insp_date || 'no date'} · ${S.insp_type} · Lot ${S.lot_no || '—'} · XRF ${S.xrf_machine || '—'} · ${S.master_sample}`, w: !S.lot_no ? 'Lot no. empty' : '' },
    { s: 2, t: 'Quantity', st: qtyOk ? 'ok' : 'miss', d: `${insp} inspected · ${acc} accepted · ${rej} reject/repair`, w: qtyOk ? '' : 'Accepted + Rejected must equal Inspected' },
    { s: 3, t: 'Quality checks', st: !S.checks.length || blankChecks.length ? 'miss' : 'ok', d: `${cnt(S.checks, 'PASS')} pass · ${cnt(S.checks, 'FAIL')} fail · ${cnt(S.checks, 'NA')} N/A`, w: blankChecks.length ? 'Not marked: ' + blankChecks.join(', ') : '' },
    { s: 4, t: 'Measurements', st: noActual.length ? 'warn' : 'ok', d: S.meas.filter((m) => m.param).map((m) => `${m.param} ${m.actual || '—'}${m.unit ? ' ' + m.unit : ''} ${mres(m) ? '(' + mres(m) + ')' : ''}`).join(' · '), w: noActual.length ? 'No actual value: ' + noActual.join(', ') : '' },
    { s: 5, t: 'On-site tests', st: !S.tests.length ? 'miss' : blankTests.length ? 'warn' : 'ok', d: `${cnt(S.tests, 'PASS')} OK · ${cnt(S.tests, 'FAIL')} not OK · ${cnt(S.tests, 'NA')} N/A`, w: blankTests.length ? 'Not marked: ' + blankTests.join(', ') : '' },
    { s: 6, t: 'Defects', st: rej > 0 && !S.defects.length ? 'miss' : totDef < rej ? 'warn' : 'ok', d: S.defects.length ? S.defects.map((x) => `${x.type} ×${x.qty} (${x.severity.toLowerCase()})`).join(' · ') : 'No defects logged', w: totDef < rej ? `Defect qty ${totDef} < reject/repair ${rej}` : '' },
    { s: 7, t: 'Photos', st: D.need_photo && totDef > 0 && !defPhotos ? 'miss' : !S.photos.some((p) => p.kind === 'product') ? 'warn' : 'ok', d: `${S.photos.length} photo(s) · ${defPhotos} defect · ${S.photos.filter((p) => p.kind === 'xrf').length} XRF · ${S.photos.filter((p) => p.kind === 'product').length} product`, w: D.need_photo && totDef > 0 && !defPhotos ? 'At least 1 defect photo required' : !S.photos.some((p) => p.kind === 'product') ? 'No actual product photo' : '' },
    { s: 8, t: 'Final result', st: (S.result || sys[0]) ? 'ok' : 'miss', d: `${S.result || sys[0]} · client spec ${S.spec_check || 'not marked'} · factory ${[S.factory_coop, S.factory_line, S.factory_ready].join('/')}`, w: !S.remark ? 'Inspector remark is empty' : !S.spec_check ? 'Client technical spec not marked' : '' },
  ];
  const blocking = REV.filter((r) => r.st === 'miss').length;

  return <MPage title={(D.admin ? 'Edit · ' : '') + it.sku} back={D.admin ? (['SUBMITTED', 'UNDER_REVIEW'].includes(it.status) ? '/review/' + it.id : it.status === 'APPROVED' ? '/report/' + it.id : '/request/' + job.id) : '/app/job/' + job.id} right={ed && !D.admin ? <button className="btn sm" style={{ background: '#fff3', color: '#fff', borderColor: '#fff5' }} onClick={() => setHold(true)} title="Hold for approval"><Icon n="pause" /></button> : null}>
    <div className="offbar">Offline — saved on this phone. Changes will sync when internet returns.</div>
    {it.status === 'HOLD' && <div className="note" style={{ marginBottom: 10 }}><b>⏸ ON HOLD — waiting for {it.hold_for} approval</b><br />{it.hold_reason}<br /><small>{fdt(it.hold_at)}</small><br />Continue with other SKUs; you'll get a notification when they respond.</div>}
    {it.hold_status && it.hold_status !== 'PENDING' && <div className="note rose" style={{ marginBottom: 10 }}><b>{it.hold_for} {String(it.hold_status).toLowerCase()}</b>: {it.hold_response || '—'}<br /><small>Your question: {it.hold_reason}</small></div>}
    {it.status === 'RETURNED' && <div className="note ruby" style={{ marginBottom: 10 }}><b>Returned for correction</b> — {it.return_reason}<br />Sections: {it.return_sections}</div>}
    {D.line && (D.line.lots > 1 || (D.line.accepted > 0 && !D.line.complete)) && <div className="mcard" style={{ marginBottom: 10, padding: '10px 12px' }}><b>QC lot {it.lot_seq || 1} of {D.line.lots}</b>{+it.parent_id ? <small className="muted"> · re-inspection, specs copied from lot 1</small> : null}
      <div className="cum"><span>Order {num(D.line.order)}</span><span>This lot {num(it.ready_qty)} pcs</span><span className="ok">Cumulative OK {num(D.line.accepted)}</span><span className={D.line.pending ? 'p' : ''}>Pending {num(D.line.pending)}</span></div></div>}
    {D.admin && <div className="note gold" style={{ marginBottom: 10 }}><b>Super Admin edit mode</b> — every point can be corrected. Status: {ITEM_STATUS[it.status] || it.status}.{D.approved && <> Report is approved — after correcting, tap <b>Regenerate PDF</b> to issue a new revision (old PDF is kept).</>} All changes are logged.</div>}
    {!D.admin && D.after_submit && ed && <div className="note rose" style={{ marginBottom: 10 }}><b>Submitted — still editable</b> until the reviewer approves. Correct anything, then tap <b>Resubmit</b>. Every change is logged.</div>}
    {!ed && it.status !== 'HOLD' && <div className="qtyok" style={{ marginBottom: 10 }}>Status: {ITEM_STATUS[it.status] || it.status} — read only.</div>}

    {(it.img || it.instructions) && <div className="mcard" style={{ display: 'flex', gap: 12, alignItems: 'flex-start', marginBottom: 10 }}>
      <Thumb src={it.img} size={110} alt={it.sku} />
      <div style={{ flex: 1, minWidth: 0 }}><b className="mono">{it.sku}</b><br /><small className="muted">{it.product} · {it.metal}{it.plating ? ' · ' + it.plating : ''}</small>
        {it.instructions && <div className="note gold" style={{ margin: '8px 0 0', fontSize: 12.5 }}><b>Client instructions:</b><p className="spec-pre">{it.instructions}</p></div>}</div></div>}
    <details className="mcard" open={!S.started}><summary style={{ fontWeight: 650, cursor: 'pointer' }}>Product information</summary>
      <div className="mgrid2 mt">{[['SKU / design no', it.sku], ['Product', it.product], ['PO number', job.po_no], ['Order qty', num(it.order_qty) + ' pcs'], ['Ready for QC', num(it.ready_qty) + ' pcs'], ['Metal', it.metal],
        ['Plating', it.plating], ['Length', it.length], ['Lock', it.lock_type], ['Stamping', it.stamping], ['Client style / ref', it.style_ref || '—'], ['Stone / setting', it.stone_setting || '—'],
        ['QC scope', `${job.scope_label} (${num(it.insp_qty)})`], ['Target weight', it.target_weight]].map(([l, v]) => <div key={l}><small>{l}</small><b>{v}</b></div>)}</div>
      {it.spec_detail && <p className="spec-pre mt">{it.spec_detail}</p>}
      {plan && plan.letter && <p className="muted" style={{ fontSize: 12 }}>AQL {plan.level} · code {plan.letter} · sample {plan.sample}{['critical', 'major', 'minor'].filter((k) => plan[k]?.aql).map((k) => ` · ${k} Ac ${plan[k].ac}/Re ${plan[k].re}`).join('')}</p>}
      {!!D.attachments.length && <div className="mt">{D.attachments.map((a) => <a key={a.id} className="pill" target="_blank" rel="noreferrer" href={a.url}>📎 {a.name}</a>)}</div>}
      {job.special_req && <div className="note mt" style={{ fontSize: 12 }}><b>Special requirements:</b> {job.special_req}</div>}
      {D.can_info && !info && <button className="btn sm mt" onClick={() => setInfo({ sku: it.sku, product: it.product, metal: it.metal || '', plating: it.plating || '', target_weight: it.target_weight || '', length: it.length || '', lock_type: it.lock_type || '', stamping: it.stamping || '', style_ref: it.style_ref || '', stone_setting: it.stone_setting || '', order_qty: it.order_qty, ready_qty: it.ready_qty, spec_detail: it.spec_detail || '', instructions: it.instructions || '', ...(D.admin ? { job_po_no: job.po_no, job_qc_type: job.qc_type, job_location: job.location } : {}) })}>✎ Edit product details</button>}
      {info && <div className="mt"><div className="mgrid2">{Object.keys(info).filter((k) => !['spec_detail', 'instructions'].includes(k)).map((k) => <div key={k}><small>{k.replace('job_', 'job · ').replace(/_/g, ' ')}</small><input type="text" value={info[k] ?? ''} onChange={(e) => setInfo({ ...info, [k]: e.target.value })} /></div>)}</div>
        {['spec_detail', 'instructions'].map((k) => <div key={k} className="f mt"><small>{k === 'spec_detail' ? 'Stone / spec detail' : 'Client instructions'}</small><textarea value={info[k]} onChange={(e) => setInfo({ ...info, [k]: e.target.value })} /></div>)}
        <div className="actions"><button className="btn sm" onClick={() => setInfo(null)}>Cancel</button><button className="btn sm pri" onClick={saveInfo}>Save details</button></div></div>}
    </details>

    {ed && !S.started ? <button className="bigbtn" onClick={start}>START QC</button> : <>
      <div className="stepper">{D.steps.map((l, i) => <a key={l} href="#" title={l} className={(i + 1 === step ? 'cur' : i + 1 < step || i + 1 <= +it.step ? 'done' : '') + (ret.includes(l) ? ' ret' : '')} onClick={(e) => { e.preventDefault(); saveStep(step); go(i + 1); }}>{i + 1}</a>)}</div>
      <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12 }} className="muted"><span>{step === 9 ? 'Review before submit' : `Step ${step} of 8 · ${D.steps[step - 1]}`}</span><span>{saved}</span></div>

      <div className="mcard mt">
        {step === 1 && <><b>General information</b>
          <div className="mgrid2 mt"><div><small>Client</small><b>{job.client_code}</b></div><div><small>Vendor</small><b>{job.vendor_name}</b></div><div><small>PO no.</small><b>{job.po_no}</b></div><div><small>Job</small><b>{job.job_no}</b></div></div>
          <div className="mgrid2 f mt"><div><label>Inspection type</label><select disabled={!ed} value={S.insp_type} onChange={(e) => { set('insp_type', e.target.value); if (e.target.value === 'RE-INSPECTION') set('reinspection', true); }}>{D.insp_types.map((o) => <option key={o}>{o}</option>)}</select></div>
            <div><label>Qty offered</label><input disabled value={num(it.ready_qty) + ' pcs'} /></div></div>
          <div className="f"><label>Inspection date</label><input type="date" disabled={!ed} value={S.insp_date} onChange={(e) => set('insp_date', e.target.value)} /></div>
          <div className="mgrid2 f"><div><label>Production batch / lot no.</label><input type="text" disabled={!ed} value={S.lot_no} onChange={(e) => set('lot_no', e.target.value)} placeholder="LOT-OCT-09A" /></div>
            <div><label>XRF machine used</label><input type="text" disabled={!ed} value={S.xrf_machine} onChange={(e) => set('xrf_machine', e.target.value)} /></div></div>
          <div className="f"><label>Master sample reference</label><select disabled={!ed} value={S.master_sample} onChange={(e) => set('master_sample', e.target.value)}>
            {['Approved Master Sample Available', 'Master Sample Not Available', 'CAD / Photo Reference Only'].map((o) => <option key={o}>{o}</option>)}</select></div>
          <div className="qtyok" style={{ fontSize: 12, background: 'var(--rose-ll)', color: 'var(--rose-d)' }}>{S.gps ? 'GPS logged · ' + S.gps : D.gps ? 'GPS not captured' : 'GPS logging off'}</div>
          <label className="mt">Re-inspection?</label><PFN disabled={!ed} opts={['Y', 'N']} labels={['YES', 'NO']} cols={2} value={S.reinspection ? 'Y' : 'N'} onChange={(v) => set('reinspection', v === 'Y')} /></>}

        {step === 2 && <><b>Quantity</b>
          <div className="mgrid2 mt"><div><small>Order quantity</small><b>{num(it.order_qty)}</b></div><div><small>Ready for inspection</small><b>{num(it.ready_qty)}</b></div></div>
          <div className="f mt"><label>Inspection quantity {D.locked_qty ? `(locked · ${job.scope_label})` : <small className="muted">(editable · plan {num(it.insp_qty)} of {num(it.ready_qty)} ready)</small>}</label><input type="number" value={S.inspected_qty} readOnly={D.locked_qty || !ed} onChange={(e) => set('inspected_qty', e.target.value)} /></div>
          <div className="f"><label>Accepted quantity</label><input type="number" inputMode="numeric" disabled={!ed} value={S.accepted_qty} onChange={(e) => { const v = e.target.value; setS((x) => ({ ...x, accepted_qty: v, rejected_qty: v === '' ? x.rejected_qty : Math.max(0, insp - (+v || 0)) })); }} /></div>
          <div className="f"><label>Reject / repair quantity</label><input type="number" inputMode="numeric" disabled={!ed} value={S.rejected_qty} onChange={(e) => set('rejected_qty', e.target.value)} /></div>
          <div className="mgrid2 f"><div><small>Accepted %</small><b>{insp ? ((acc * 100) / insp).toFixed(2) + '%' : '—'}</b></div><div><small>Rej / repair %</small><b>{insp ? ((rej * 100) / insp).toFixed(2) + '%' : '—'}</b></div></div>
          {qtyTouched && (qtyOk ? <div className="qtyok">{acc} + {rej} = {insp} ✓ matches inspected qty</div> : <div className="qtybad">Quantity mismatch — Accepted + Rejected ({acc + rej}) ≠ Inspected ({insp}). Please check before proceeding.</div>)}</>}

        {step === 3 && <><b>Critical quality checkpoints</b>{S.checks.map((c, i) => { const pre = D.remarks[c.name] || {};
          return <div key={i} className="chkrow"><b style={{ fontSize: 13.5 }}>{i + 1}. {c.name}</b>
            <PFN disabled={!ed} value={c.result} onChange={(v) => setS((x) => ({ ...x, checks: x.checks.map((r, j) => (j !== i ? r : { ...r, result: v, remark: !r.remark || Object.values(pre).includes(r.remark) ? pre[v] || '' : r.remark })) }))} />
            {c.result && <><input type="text" list={'rm' + i} disabled={!ed} value={c.remark} onChange={(e) => setArr('checks', i, 'remark', e.target.value)} placeholder="QC remark" style={{ marginTop: 6 }} />
              <datalist id={'rm' + i}>{Object.values(pre).filter(Boolean).map((r) => <option key={r} value={r} />)}</datalist></>}</div>; })}</>}

        {step === 4 && <><b>Measurements</b><p className="muted" style={{ fontSize: 12, margin: '2px 0 8px' }}>Tolerance from SKU master · auto PASS / FAIL</p>
          <table className="t"><thead><tr><th>Parameter</th><th>Spec</th><th>±Tol</th><th>Actual</th><th /></tr></thead><tbody>
            {S.meas.map((m, i) => { const r = mres(m); const inp = (f, w) => <input disabled={!ed} value={m[f] ?? ''} inputMode={f === 'param' ? 'text' : 'decimal'} onChange={(e) => setArr('meas', i, f, e.target.value)} style={{ padding: 5, width: w }} />;
              return <tr key={i}><td>{inp('param', 84)}</td><td>{inp('spec', 56)}</td><td>{inp('tol', 46)}</td><td>{inp('actual', 60)}</td><td>{r && <Badge s={r} />}</td></tr>; })}</tbody></table>
          {ed && <button type="button" className="btn sm mt" onClick={() => set('meas', [...S.meas, { param: '', spec: '', tol: '', unit: '', qty: 2, actual: '' }])}>+ Add parameter</button>}</>}

        {step === 5 && <><b>On-site physical &amp; reliability tests</b>{S.tests.map((t, i) => <div key={i} className="chkrow">
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}><b style={{ fontSize: 13.5 }}>{t.name}</b><span style={{ fontSize: 12 }}>Qty <input disabled={!ed} value={t.qty} onChange={(e) => setArr('tests', i, 'qty', e.target.value)} style={{ width: 60, padding: '4px 6px' }} /></span></div>
          <PFN disabled={!ed} value={t.result} onChange={(v) => setArr('tests', i, 'result', v)} labels={['OK', 'NOT OK', 'NA']} />
          <input type="text" disabled={!ed} value={t.remark} onChange={(e) => setArr('tests', i, 'remark', e.target.value)} placeholder="Method / QC remark" style={{ marginTop: 6 }} /></div>)}
          {ed && <button type="button" className="btn sm mt" onClick={() => { const n = window.prompt('Test name'); if (n) set('tests', [...S.tests, { name: n, qty: 2, result: '', remark: '' }]); }}>+ Add test</button>}</>}

        {step === 6 && <><b>Defect details</b>
          {!S.defects.length && <p className="muted" style={{ fontSize: 13 }}>No defects. Tap “Add defect” if reject/repair qty &gt; 0.</p>}
          {S.defects.map((d, i) => <div key={i} className="chkrow"><div className="mgrid2">
            <div><small>Defect category</small><select disabled={!ed} value={d.category} onChange={(e) => { const f = (D.dm[e.target.value] || [])[0] || {}; setS((x) => ({ ...x, defects: x.defects.map((r, j) => (j === i ? { ...r, category: e.target.value, type: f.n || '', severity: f.s || r.severity } : r)) })); }}>{catList.map((c) => <option key={c}>{c}</option>)}</select></div>
            <div><small>Defect type</small><select disabled={!ed} value={d.type} onChange={(e) => { const f = (D.dm[d.category] || []).find((x) => x.n === e.target.value); setS((x) => ({ ...x, defects: x.defects.map((r, j) => (j === i ? { ...r, type: e.target.value, severity: f?.s || r.severity } : r)) })); }}>{(D.dm[d.category] || []).map((x) => <option key={x.n}>{x.n}</option>)}</select></div></div>
            <small style={{ display: 'block', marginTop: 8 }}>Severity</small><SEV disabled={!ed} opts={['CRITICAL', 'MAJOR', 'MINOR']} value={d.severity} onChange={(v) => setArr('defects', i, 'severity', v)} />
            <div className="mgrid2 mt"><div><small>Defect quantity</small><input type="number" inputMode="numeric" disabled={!ed} value={d.qty} onChange={(e) => setArr('defects', i, 'qty', e.target.value)} /></div>
              <div><small>Remark</small><input type="text" disabled={!ed} value={d.remark || ''} onChange={(e) => setArr('defects', i, 'remark', e.target.value)} /></div></div>
            {ed && <button type="button" className="btn sm mt" onClick={() => set('defects', S.defects.filter((_, j) => j !== i))}>Remove</button>}</div>)}
          {ed && <button type="button" className="btn block mt" onClick={() => { const c = catList[0]; const f = D.dm[c][0]; set('defects', [...S.defects, { category: c, type: f.n, severity: f.s, qty: '', remark: '' }]); }}>+ ADD DEFECT</button>}
          <div className={'mt ' + (totDef >= rej ? 'qtyok' : 'qtybad')}>Total defects {totDef} {totDef === rej ? '=' : totDef > rej ? '≥' : '<'} Reject/repair {rej} {totDef >= rej ? '✓' : '— add defects for all rejected pcs'}</div>
          <p className="muted" style={{ fontSize: 12 }}>Critical {sev.CRITICAL} · Major {sev.MAJOR} · Minor {sev.MINOR}</p></>}

        {step === 7 && <><b>Photos</b>
          {ed && <><div className="fr f mt" style={{ gridTemplateColumns: '1fr 1fr' }}><div><label>Photo type</label><select value={ph.kind} onChange={(e) => setPh({ ...ph, kind: e.target.value })}>{KINDS.map(([k, l]) => <option key={k} value={k}>{l}</option>)}</select></div>
            <div><label>Photo remark</label><input type="text" value={ph.remark} onChange={(e) => setPh({ ...ph, remark: e.target.value })} placeholder="e.g. bezel gap on stone" /></div></div>
          <div className="mgrid2 f"><label className="bigbtn" style={{ margin: 0, color: '#fff' }}><Icon n="camera" /> Take photo<input type="file" accept="image/*" capture="environment" hidden onChange={(e) => { addPhotos(Array.from(e.target.files)); e.target.value = ''; }} /></label>
            <label className="bigbtn alt" style={{ margin: 0 }}>Upload<input type="file" accept="image/*" multiple hidden onChange={(e) => { addPhotos(Array.from(e.target.files)); e.target.value = ''; }} /></label></div></>}
          <div className="thumbs">{S.photos.map((p, i) => <figure key={i} className={p.pending ? 'pend' : ''}><img src={p.url} alt="" /><figcaption>{p.kind}{p.remark ? ' · ' + p.remark : ''}</figcaption>
            {ed && p.id ? <button type="button" onClick={() => delPhoto(p)} style={{ position: 'absolute', top: 3, right: 3, border: 0, borderRadius: '50%', background: '#0008', color: '#fff', width: 22, height: 22 }}>✕</button> : null}</figure>)}</div>
          <p className="muted" style={{ fontSize: 12 }}>{D.need_photo && totDef > 0 ? (defPhotos ? `✓ ${defPhotos} defect photo(s)` : <b style={{ color: 'var(--ruby)' }}>At least 1 defect photo is required (defect qty &gt; 0)</b>) : `${S.photos.length} photo(s)`} · add XRF report, weight scale and packaging photos for the report.</p></>}

        {step === 8 && <><b>Final result</b>
          <div className="mstats"><div><b>{insp}</b><small>Inspected</small></div><div><b>{acc}</b><small>Accepted</small></div><div><b style={{ color: 'var(--ruby)' }}>{rej}</b><small>Rej / repair</small></div></div>
          <div className="mstats"><div><b>{sev.CRITICAL}</b><small>Critical</small></div><div><b>{sev.MAJOR}</b><small>Major</small></div><div><b>{sev.MINOR}</b><small>Minor</small></div></div>
          <div className="note" style={{ marginBottom: 10 }}>System suggests: <b>{sys[0]}</b> ({sys[1]}) — reviewer decides.{[...S.checks, ...S.tests].some((c) => c.result === 'FAIL') && <><br />⚠ Some checkpoints / tests are FAIL — mention in remarks.</>}</div>
          <PFN disabled={!ed} cols={4} opts={['PASS', 'FAIL', 'PENDING', 'CONDITIONAL']} labels={['PASS', 'FAIL', 'PENDING', 'COND. PASS']} value={S.result || sys[0]} onChange={(v) => set('result', v)} />
          <div className="f mt"><label>Inspector remark</label><textarea disabled={!ed} value={S.remark} onChange={(e) => set('remark', e.target.value)} /></div>
          <div className="f"><label>Action required (CAPA)</label><textarea disabled={!ed} value={S.action_required} onChange={(e) => set('action_required', e.target.value)} /></div>
          <b style={{ fontSize: 13.5 }}>Client technical specifications</b><p className="muted" style={{ fontSize: 12, margin: '2px 0 6px' }}>Checked against PO &amp; client spec sheet (metal, plating, stone, size, stamping)</p>
          <PFN disabled={!ed} cols={3} opts={['OK', 'NOT OK', 'NA']} value={S.spec_check} onChange={(v) => set('spec_check', v)} />
          <input type="text" disabled={!ed} value={S.spec_remark} onChange={(e) => set('spec_remark', e.target.value)} placeholder="Spec remark (e.g. stamping 925 missing on 3 pcs)" style={{ marginTop: 6 }} />
          <b style={{ fontSize: 13.5, display: 'block', marginTop: 12 }}>Factory evaluation</b>
          {[['factory_coop', 'Factory cooperation'], ['factory_line', 'Factory line organization'], ['factory_ready', 'Overall factory QC readiness']].map(([k, l]) =>
            <div key={k}><small style={{ display: 'block', marginTop: 8 }}>{l}</small><SEV disabled={!ed} opts={['GOOD', 'AVERAGE', 'POOR']} value={S[k]} onChange={(v) => set(k, v)} /></div>)}
          <div className="f mt"><label>Factory representative name</label><input type="text" disabled={!ed} value={S.factory_rep} onChange={(e) => set('factory_rep', e.target.value)} placeholder="Name of person who signs for the factory" /></div></>}

        {step === 9 && <><b>Review before submit</b><p className="muted" style={{ fontSize: 12, margin: '2px 0 8px' }}>Check every point. Tap a section to correct it. Red must be fixed; amber is a warning.</p>
          {REV.map((r) => <a key={r.s} href="#" className={'revrow ' + r.st} onClick={(e) => { e.preventDefault(); go(r.s); }}>
            <span className="revdot">{r.st === 'ok' ? '✓' : r.st === 'warn' ? '!' : '✕'}</span>
            <span style={{ flex: 1, minWidth: 0 }}><b>{r.s}. {r.t}</b><small>{r.d}</small>{r.w && <em>{r.w}</em>}</span><span className="muted">Edit ›</span></a>)}
          <div className={'mt ' + (blocking ? 'qtybad' : 'qtyok')}>{blocking ? `${blocking} section(s) incomplete — fix before submitting.` : 'All required points are filled ✓'}</div></>}
      </div>

      {step < 8 ? <div style={{ display: 'grid', gridTemplateColumns: '1fr 2fr', gap: 8 }}>
        <button className="bigbtn alt" disabled={step === 1} onClick={() => go(step - 1)}>‹ BACK</button>
        <button className="bigbtn" onClick={async () => { if (await saveStep(step)) go(step + 1); }}>{ed ? 'SAVE & NEXT' : 'NEXT ›'}</button></div>
        : step === 8 ? ed && <div style={{ display: 'grid', gridTemplateColumns: '1fr 2fr', gap: 8 }}>
          <button className="bigbtn alt" onClick={async () => { if (await saveStep(8)) toast('Saved'); }}>Save</button>
          <button className="bigbtn" onClick={async () => { if (await saveStep(8)) go(9); }}>REVIEW BEFORE SUBMIT ›</button></div>
        : ed && <div style={{ display: 'grid', gridTemplateColumns: '1fr 2fr', gap: 8 }}>
          <button className="bigbtn alt" onClick={() => go(8)}>‹ BACK</button>
          {D.approved ? <button className="bigbtn gold" onClick={() => setRegen({ reason: '', email: false, final: it.final_result })}>REGENERATE PDF</button>
            : <button className="bigbtn gold" disabled={busy || blocking > 0} onClick={submit}>{D.after_submit ? 'RESUBMIT FOR REVIEW' : 'CONFIRM & SUBMIT'}</button>}</div>}
      {D.admin && D.approved && step < 9 && <button className="bigbtn gold" onClick={async () => { await saveStep(step); setRegen({ reason: '', email: false, final: it.final_result }); }}>SAVE &amp; REGENERATE PDF</button>}
    </>}

    <div className="mcard mt"><small className="muted">Other SKUs in this job</small><div className="mlist">{D.siblings.filter((s) => s.id !== it.id).map((s) => <Link key={s.id} to={(D.admin ? '/edit/' : '/app/inspect/') + s.id}><span className="mitem"><Thumb src={s.img} size={40} /><b className="mono">{s.sku}</b></span><Badge s={s.status} /></Link>)}</div></div>

    <Modal open={!!regen} title="Regenerate report PDF" onClose={() => setRegen(null)}>
      {regen && <><p className="muted" style={{ fontSize: 12.5, marginTop: 0 }}>Issues revision {String((+it.rev || 0) + 1).padStart(2, '0')} of {it.report_no}. The old PDF is kept; the change log records who changed what.</p>
        <label>Final result</label><PFN cols={3} opts={['PASS', 'FAIL', 'CONDITIONAL']} labels={['PASS', 'FAIL', 'COND. PASS']} value={regen.final} onChange={(v) => setRegen({ ...regen, final: v })} />
        <div className="f mt"><label>Reason for correction</label><textarea value={regen.reason} onChange={(e) => setRegen({ ...regen, reason: e.target.value })} placeholder="e.g. Lot no. and defect qty corrected" /></div>
        <label style={{ display: 'flex', gap: 8, alignItems: 'center', fontSize: 13 }}><input type="checkbox" checked={regen.email} onChange={(e) => setRegen({ ...regen, email: e.target.checked })} style={{ width: 17, height: 17 }} /> Email revised report to client &amp; vendor</label>
        <button className="bigbtn gold mt" onClick={doRegen}>GENERATE NEW PDF</button></>}
    </Modal>
    <Modal open={hold} title="Hold SKU for approval" onClose={() => setHold(false)}>
      <p className="muted" style={{ fontSize: 12.5, marginTop: 0 }}>Use when you need a decision before continuing (e.g. shade difference, design deviation). The SKU pauses; other SKUs continue.</p>
      <label>Who must approve?</label><SEV opts={['client', 'vendor']} value={hf.for} onChange={(v) => setHf({ ...hf, for: v })} />
      <div className="f mt"><label>What needs approval?</label><textarea value={hf.reason} onChange={(e) => setHf({ ...hf, reason: e.target.value })} placeholder="Centre stone shade lighter than approved sample — accept?" /></div>
      <button className="bigbtn ruby" onClick={doHold}>HOLD FOR APPROVAL</button>
    </Modal>
  </MPage>;
}
