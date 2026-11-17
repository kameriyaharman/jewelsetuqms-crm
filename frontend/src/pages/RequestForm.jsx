import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { Page } from '../app/Layout';
import { useAuth } from '../app/auth';
import { fileUrl, get, upload } from '../app/api';
import { Badge, Icon, Sel, Spinner, useToast } from '../app/ui';
import { AQL_OPTIONS, addDays, aqlPlan, num } from '../app/util';

let K = 1;
const newItem = () => ({ key: 'n' + K++, id: 0, sku: '', product: 'Necklace', style_ref: '', metal: '925 Silver', plating: '', stone_setting: '', target_weight: '', length: '', lock_type: '', stamping: '', order_qty: '', ready_qty: '', file: null });
const aqlKey = (v) => { const n = Number(v); const o = AQL_OPTIONS.find(([k]) => Math.abs(Number(k) - n) < 1e-6); return o ? o[0] : '0'; };

export default function RequestForm() {
  const { id } = useParams(); const { me, refresh } = useAuth(); const r = me.user.role; const nav = useNavigate(); const toast = useToast();
  const [opts, setOpts] = useState(null); const [J, setJ] = useState(null); const [items, setItems] = useState([]); const [files, setFiles] = useState([]);
  const [atts, setAtts] = useState([]); const [busy, setBusy] = useState(false); const [errs, setErrs] = useState([]); const [loadErr, setLoadErr] = useState('');
  useEffect(() => { (async () => {
    try {
      const [o, f] = await Promise.all([get('options'), get('request/form', { id })]); setOpts(o);
      const j = f.job;
      setJ(j ? { ...j, aql_critical: aqlKey(j.aql_critical), aql_major: aqlKey(j.aql_major), aql_minor: aqlKey(j.aql_minor), allow_excess: !!+j.allow_excess } : {
        id: 0, req_no: f.next_req_no, status: 'DRAFT', client_id: r === 'client' ? me.user.client_id : (o.clients.length === 1 ? o.clients[0].id : ''), vendor_id: r === 'vendor' ? me.user.vendor_id : '',
        po_no: '', qc_type: 'Pre-shipment', location: '', requested_date: addDays(2), scope: 'BULK', bulk_mode: '100', insp_level: 'II', aql_critical: '0', aql_major: '1.5', aql_minor: '4', defined_qty: 0, allow_excess: false, specs: '', special_req: '' });
      setItems(f.items.length ? f.items.map((i) => ({ ...newItem(), ...i, key: 'i' + i.id })) : [newItem()]); setAtts(f.attachments);
    } catch (e) { setLoadErr(e.message); }
  })(); /* eslint-disable-next-line */ }, [id]);

  const set = (k, v) => setJ((x) => ({ ...x, [k]: v }));
  const setItem = (key, k, v) => setItems((l) => l.map((i) => (i.key === key ? { ...i, [k]: v } : i)));
  const vendors = useMemo(() => { if (!opts || !J) return []; if (r === 'vendor') return opts.vendors;
    const ok = opts.mapping.filter((m) => m.client_id === Number(J.client_id)).map((m) => m.vendor_id); return opts.vendors.filter((v) => ok.includes(v.id)); }, [opts, J?.client_id]);
  if (loadErr) return <Page title="QC Request"><div className="card"><div className="flash f-err">{loadErr}</div></div></Page>;
  if (!J || !opts) return <Page title="QC Request"><Spinner /></Page>;

  const lot = items.reduce((a, i) => a + (Number(i.ready_qty) || 0), 0);
  const isAql = J.scope === 'BULK' && J.bulk_mode === 'AQL';
  const plan = isAql ? aqlPlan(Math.max(lot, 2), J.insp_level, +J.aql_critical, +J.aql_major, +J.aql_minor) : null;
  const excessBad = !J.allow_excess && items.some((i) => i.order_qty !== '' && Number(i.ready_qty) > Number(i.order_qty));

  const save = async (action) => {
    setBusy(true); setErrs([]);
    const fd = new FormData();
    fd.append('data', JSON.stringify({ ...J, action, items: items.map(({ file, ...i }) => i) }));
    items.forEach((i) => { if (i.file) fd.append('row_file_' + i.key, i.file); });
    files.forEach((f) => fd.append('files[]', f));
    try { const res = await upload('request/save', fd); toast(res.message); refresh(); nav('/request/' + res.id); }
    catch (e) { setErrs(e.data?.errors || [e.message]); window.scrollTo(0, 0); }
    setBusy(false);
  };
  const vendorSel = r === 'vendor' ? <input type="text" readOnly value={opts.vendors[0]?.name || ''} /> :
    <Sel value={J.vendor_id} onChange={(v) => { set('vendor_id', v); const vv = opts.vendors.find((x) => String(x.id) === v); if (vv && !J.location) set('location', (vv.location || '').split(',')[0] + ' Unit 1'); }} options={vendors.map((v) => [v.id, v.name])} blank="Select vendor" />;
  return <Page title={J.id ? 'Edit QC Request' : 'New QC Request'} sub="Same form for Back Office, Vendor and Client — multi-SKU, attachments and dynamic QC scope with AQL calculator">
    {errs.map((e, k) => <div key={k} className="flash f-err">{e}</div>)}
    <div className="grid g-73">
      <div>
        <div className="card"><div className="card-h"><h2>Create QC request</h2><span>{J.req_no} · <Badge s={J.status} /></span></div>
          <div className="fr f">
            <div><label>Client</label>{r === 'client' ? <input type="text" readOnly value={opts.clients[0]?.code + ' — ' + opts.clients[0]?.name} /> :
              <Sel value={J.client_id} onChange={(v) => { set('client_id', v); if (r !== 'vendor') set('vendor_id', ''); }} options={opts.clients.filter((c) => c.status !== 'INACTIVE').map((c) => [c.id, c.code + ' — ' + c.name])} blank="Select client" />}</div>
            <div><label>Vendor</label>{vendorSel}</div>
            <div><label>PO number</label><input type="text" value={J.po_no} onChange={(e) => set('po_no', e.target.value)} placeholder="81966" /></div>
          </div>
          <div className="fr f">
            <div><label>Requested QC date</label><input type="date" value={J.requested_date || ''} onChange={(e) => set('requested_date', e.target.value)} /></div>
            <div><label>QC location</label><input type="text" value={J.location || ''} onChange={(e) => set('location', e.target.value)} placeholder="Factory unit" /></div>
            <div><label>QC type</label><Sel value={J.qc_type} onChange={(v) => set('qc_type', v)} options={opts.qc_types} /></div>
          </div>
        </div>
        <div className="card"><div className="card-h"><h2>Product / SKU line items</h2><button type="button" className="btn sm" onClick={() => setItems((l) => [...l, newItem()])}><Icon n="plus" /> Add SKU</button></div>
          <div className="tbl-wrap"><table className="t"><thead><tr><th>#</th><th>SKU / Design no.</th><th>Product</th><th>Client style / ref</th><th>Metal</th><th>Plating</th><th>Stone / setting</th><th>Wt.</th><th>Length</th><th>Lock</th><th>Stamping</th><th>Order qty</th><th>Ready qty</th><th>Product image / spec</th><th></th></tr></thead><tbody>
            {items.map((i, k) => { const bad = !J.allow_excess && i.order_qty !== '' && Number(i.ready_qty) > Number(i.order_qty); const inp = (f, w, ph) =>
              <input type="text" value={i[f] ?? ''} placeholder={ph} onChange={(e) => setItem(i.key, f, e.target.value)} style={{ minWidth: w, padding: '7px 8px' }} />;
              return <tr key={i.key}><td>{k + 1}</td><td>{inp('sku', 140)}</td>
                <td><Sel value={i.product} onChange={(v) => setItem(i.key, 'product', v)} options={opts.categories} style={{ minWidth: 100 }} /></td>
                <td>{inp('style_ref', 100)}</td><td>{inp('metal', 90)}</td><td>{inp('plating', 90)}</td><td>{inp('stone_setting', 110, 'Topaz / Bezel')}</td><td>{inp('target_weight', 60)}</td><td>{inp('length', 70)}</td><td>{inp('lock_type', 80)}</td><td>{inp('stamping', 100)}</td>
                <td><input type="number" min="0" value={i.order_qty} onChange={(e) => setItem(i.key, 'order_qty', e.target.value)} style={{ width: 85, padding: '7px 8px' }} /></td>
                <td><input type="number" min="1" value={i.ready_qty} onChange={(e) => setItem(i.key, 'ready_qty', e.target.value)} style={{ width: 85, padding: '7px 8px', borderColor: bad ? 'var(--ruby)' : undefined }} /></td>
                <td><input type="file" accept=".jpg,.jpeg,.png,.pdf,.xlsx" onChange={(e) => setItem(i.key, 'file', e.target.files[0] || null)} style={{ width: 160, fontSize: 11 }} /></td>
                <td><button type="button" className="btn sm ghost" onClick={() => setItems((l) => (l.length > 1 ? l.filter((x) => x.key !== i.key) : l))}>✕</button></td></tr>; })}
          </tbody></table></div>
          <div className="actions mt"><label className="chk"><input type="checkbox" checked={J.allow_excess} onChange={(e) => set('allow_excess', e.target.checked)} /> Allow excess quantity (Ready &gt; Order)</label>
            {excessBad && <span className="right bad-t">Ready qty is more than Order qty on a row</span>}</div>
        </div>
        <div className="card"><h2 className="mb">Specifications &amp; attachments</h2>
          <div className="f"><label>Specifications / remarks</label><textarea value={J.specs || ''} onChange={(e) => set('specs', e.target.value)} placeholder="925 Silver, White Rhodium, 16+2″ Lobster lock, stamping 925 + ARCL + LGDS" /></div>
          <div className="f"><label>Special requirements for inspector</label><input type="text" value={J.special_req || ''} onChange={(e) => set('special_req', e.target.value)} /></div>
          <div className="f"><label>Upload spec sheet / CAD / reference photos (PDF, JPG, PNG, XLSX)</label><input type="file" multiple accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.webp" onChange={(e) => setFiles(Array.from(e.target.files))} /></div>
          {!!atts.length && <div className="actions">{atts.map((a) => <a key={a.id} className="pill" target="_blank" rel="noreferrer" href={fileUrl(a.path)}>{a.name}</a>)}</div>}
        </div>
      </div>
      <div>
        <div className="card" style={{ position: 'sticky', top: 84 }}>
          <h2 className="mb">QC scope</h2>
          <label>Primary scope</label>
          <div className="radio-cards f">{[['SAMPLE', 'Sample QC', 'Pre-production / pilot sample'], ['BULK', 'Bulk QC', 'Production lot inspection']].map(([v, l, s]) =>
            <label key={v}><input type="radio" checked={J.scope === v} onChange={() => set('scope', v)} /><b>{l}</b><small>{s}</small></label>)}</div>
          {J.scope === 'BULK' && <><label>Bulk QC mode</label><div className="stack f">{[['100', '100% inspection', 'Inspection qty = Ready qty (locked)'], ['AQL', 'AQL inspection (ISO 2859-1)', 'Auto sample size & Ac / Re'], ['DEFINED', 'Defined sample quantity', 'Enter fixed pcs (≤ Ready qty)']].map(([v, l, s]) =>
            <label key={v} className="rc"><input type="radio" checked={J.bulk_mode === v} onChange={() => set('bulk_mode', v)} /> <b>{l}</b><small>{s}</small></label>)}</div></>}
          {(J.scope === 'SAMPLE' || J.bulk_mode === 'DEFINED') && <div className="f"><label>{J.scope === 'SAMPLE' ? 'Sample pcs to check per SKU (0 = all ready)' : 'Defined sample qty (pcs per SKU, ≤ Ready qty)'}</label>
            <input type="number" min="0" value={J.defined_qty} onChange={(e) => set('defined_qty', e.target.value)} /></div>}
          {isAql && <>
            <div className="fr f"><div><label>Inspection level</label><Sel value={J.insp_level} onChange={(v) => set('insp_level', v)} options={[['I', 'General I'], ['II', 'General II'], ['III', 'General III'], ['S1', 'Special S-1'], ['S2', 'Special S-2'], ['S3', 'Special S-3'], ['S4', 'Special S-4']]} /></div>
              <div><label>Lot size (Ready)</label><input type="text" readOnly value={num(lot)} /></div></div>
            <table className="t"><thead><tr><th>AQL</th><th>Limit</th><th className="num">Sample</th><th className="num">Ac</th><th className="num">Re</th></tr></thead><tbody>
              {[['critical', 'Critical', 'aql_critical'], ['major', 'Major', 'aql_major'], ['minor', 'Minor', 'aql_minor']].map(([k, l, f]) => { const p = plan[k];
                return <tr key={k}><td>{l}</td><td><Sel value={J[f]} onChange={(v) => set(f, v)} options={AQL_OPTIONS} style={{ padding: '5px 6px' }} /></td>
                  <td className="num">{p ? Math.min(p.sample, lot) : '—'}</td><td className="num">{p ? p.ac : k === 'critical' ? 0 : '—'}</td><td className="num">{p ? p.re : k === 'critical' ? 1 : '—'}</td></tr>; })}
            </tbody></table>
            <p className="muted" style={{ fontSize: 12 }}>Sample size {plan.sample} for lot {num(lot)} · Level {J.insp_level} (code letter {plan.letter}){plan.full ? ' — 100% inspection' : ''}<br />
              <b>Per SKU (each SKU is its own lot):</b><br />
              {items.filter((i) => +i.ready_qty).map((i) => { const p = aqlPlan(+i.ready_qty, J.insp_level, +J.aql_critical, +J.aql_major, +J.aql_minor); return <span key={i.key}>{i.sku || 'SKU'}: lot {num(i.ready_qty)} → {p.sample} pcs ({p.letter})<br /></span>; })}</p>
          </>}
          <div className="actions mt"><button className="btn" disabled={busy} onClick={() => save('draft')}>Save draft</button><button className="btn pri" style={{ flex: 1 }} disabled={busy} onClick={() => save('submit')}>SUBMIT QC REQUEST</button></div>
        </div>
      </div>
    </div>
  </Page>;
}
