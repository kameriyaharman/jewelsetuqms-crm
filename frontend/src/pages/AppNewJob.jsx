import { useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { MPage } from '../app/Layout';
import { post } from '../app/api';
import { Icon, Loader, useApi, useToast } from '../app/ui';
import { compressImage } from '../app/util';

const blank = () => ({ k: Math.random(), sku: '', product: '', metal: '925 Silver', plating: '', target_weight: '', length: '', lock_type: '', stone_setting: '', order_qty: '', ready_qty: '', instructions: '', img: '' });

/** Inspector creates a QC job on site (or adds SKUs to an open job) when the vendor hands over the files at the factory. */
export default function AppNewJob() {
  const { id } = useParams(); const addTo = id ? +id : 0;
  const q = useApi(addTo ? 'app/job' : 'app/newjob', addTo ? { id: addTo } : {}); const nav = useNavigate(); const toast = useToast();
  const [J, setJ] = useState({ client_id: '', vendor_id: '', po_no: '', qc_type: 'Pre-shipment', scope: 'Bulk 100%', location: '', special_req: '' });
  const [items, setItems] = useState([blank()]); const [files, setFiles] = useState([]); const [busy, setBusy] = useState(false);
  const setI = (k, f, v) => setItems((x) => x.map((r) => (r.k === k ? { ...r, [f]: v } : r)));
  const vendors = useMemo(() => (q.data?.vendors || []).filter((v) => String(v.client_id) === String(J.client_id)), [q.data, J.client_id]);
  const pic = async (k, f) => { if (!f) return; try { setI(k, 'img', await compressImage(f)); } catch { toast('Could not read photo', 'err'); } };
  const addFiles = async (list) => { for (const f of list) { try { const data = await compressImage(f); setFiles((x) => [...x, { name: f.name, data }]); } catch { toast('Only photos of the spec / PO can be attached here', 'err'); } } };
  const save = async () => {
    const rows = items.filter((r) => r.sku.trim());
    if (!rows.length) { toast('Add at least one SKU / design no.', 'err'); return; }
    if (rows.some((r) => !(+r.ready_qty > 0))) { toast('Enter ready qty for every SKU', 'err'); return; }
    if (!addTo && (!J.client_id || !J.vendor_id || !J.po_no.trim())) { toast('Select client, vendor and PO no.', 'err'); return; }
    if (!navigator.onLine) { toast('You are offline — creating a job needs internet. Your entries stay on this screen.', 'warn'); return; }
    setBusy(true);
    try {
      const r = await post('app/newjob', addTo ? { job_id: addTo, items: rows } : { ...J, items: rows, files });
      toast(r.message); nav('/app/inspect/' + r.first);
    } catch (e) { toast(e.message, 'err'); }
    setBusy(false);
  };
  return <MPage title={addTo ? 'Add SKU on site' : 'New QC job on site'} back={addTo ? '/app/job/' + addTo : '/app'}>
    <Loader q={q}>{(d) => <>
      {addTo ? <div className="mcard"><small className="muted">Adding to</small><br /><b>{d.job.job_no}</b> · {d.job.client_code} – {d.job.vendor_name} · PO {d.job.po_no}</div>
        : <div className="mcard"><b>Job details</b>
          <div className="f mt"><label>Client</label><select value={J.client_id} onChange={(e) => setJ({ ...J, client_id: e.target.value, vendor_id: '' })}><option value="">Select client</option>{d.clients.map((c) => <option key={c.id} value={c.id}>{c.code} — {c.name}</option>)}</select></div>
          <div className="f"><label>Vendor / factory</label><select value={J.vendor_id} onChange={(e) => setJ({ ...J, vendor_id: e.target.value })} disabled={!J.client_id}><option value="">{J.client_id ? 'Select vendor' : 'Select client first'}</option>{vendors.map((v) => <option key={v.id} value={v.id}>{v.name}</option>)}</select></div>
          <div className="mgrid2 f"><div><label>PO no.</label><input type="text" value={J.po_no} onChange={(e) => setJ({ ...J, po_no: e.target.value })} /></div>
            <div><label>QC type</label><select value={J.qc_type} onChange={(e) => setJ({ ...J, qc_type: e.target.value })}>{d.qc_types.map((t) => <option key={t}>{t}</option>)}</select></div></div>
          <div className="mgrid2 f"><div><label>QC scope</label><select value={J.scope} onChange={(e) => setJ({ ...J, scope: e.target.value })}>{['Bulk 100%', 'Sample', 'AQL-2.5', 'AQL-1.5', 'AQL-4.0'].map((t) => <option key={t}>{t}</option>)}</select></div>
            <div><label>Location</label><input type="text" value={J.location} onChange={(e) => setJ({ ...J, location: e.target.value })} placeholder="Factory unit / city" /></div></div>
          <div className="f"><label>Special requirements / notes</label><textarea value={J.special_req} onChange={(e) => setJ({ ...J, special_req: e.target.value })} /></div>
          <label className="bigbtn alt" style={{ margin: 0 }}><Icon n="camera" /> Photo of PO / spec sheet<input type="file" accept="image/*" multiple hidden onChange={(e) => { addFiles(Array.from(e.target.files)); e.target.value = ''; }} /></label>
          {!!files.length && <div className="thumbs">{files.map((f, i) => <figure key={i}><img src={f.data} alt="" /><figcaption>{f.name}</figcaption>
            <button type="button" onClick={() => setFiles(files.filter((_, j) => j !== i))} style={{ position: 'absolute', top: 3, right: 3, border: 0, borderRadius: '50%', background: '#0008', color: '#fff', width: 22, height: 22 }}>✕</button></figure>)}</div>}
        </div>}

      {items.map((r, n) => <div key={r.k} className="mcard">
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}><b>SKU {n + 1}</b>{items.length > 1 && <button className="btn sm" onClick={() => setItems(items.filter((x) => x.k !== r.k))}>Remove</button>}</div>
        <div style={{ display: 'flex', gap: 10, marginTop: 8 }}>
          <label style={{ width: 92, height: 92, flex: 'none', border: '1px dashed var(--line2)', borderRadius: 10, display: 'flex', alignItems: 'center', justifyContent: 'center', overflow: 'hidden', background: '#f8fafb', cursor: 'pointer' }}>
            {r.img ? <img src={r.img} alt="" style={{ width: '100%', height: '100%', objectFit: 'contain' }} /> : <span style={{ textAlign: 'center', fontSize: 11, color: 'var(--mut)' }}><Icon n="camera" /><br />Product photo</span>}
            <input type="file" accept="image/*" capture="environment" hidden onChange={(e) => pic(r.k, e.target.files[0])} /></label>
          <div style={{ flex: 1, display: 'grid', gap: 6 }}>
            <input type="text" placeholder="SKU / design no. *" value={r.sku} onChange={(e) => setI(r.k, 'sku', e.target.value.toUpperCase())} />
            <input type="text" placeholder="Product (Ring, Earring…)" list="prodlist" value={r.product} onChange={(e) => setI(r.k, 'product', e.target.value)} />
            <div className="mgrid2"><input type="number" inputMode="numeric" placeholder="Order qty" value={r.order_qty} onChange={(e) => setI(r.k, 'order_qty', e.target.value)} />
              <input type="number" inputMode="numeric" placeholder="Ready qty *" value={r.ready_qty} onChange={(e) => setI(r.k, 'ready_qty', e.target.value)} /></div>
          </div></div>
        <details className="mt"><summary style={{ fontSize: 12.5, cursor: 'pointer', color: 'var(--rose-d)', fontWeight: 600 }}>More specs (metal, plating, weight, size…)</summary>
          <div className="mgrid2 mt">{[['metal', 'Metal'], ['plating', 'Plating'], ['target_weight', 'Target weight'], ['length', 'Length / size'], ['lock_type', 'Lock'], ['stone_setting', 'Stone / setting']].map(([f, l]) =>
            <div key={f}><small>{l}</small><input type="text" value={r[f]} onChange={(e) => setI(r.k, f, e.target.value)} /></div>)}</div>
          <div className="f mt"><small>Client instructions</small><textarea value={r.instructions} onChange={(e) => setI(r.k, 'instructions', e.target.value)} /></div></details>
      </div>)}
      <datalist id="prodlist">{(d.products || ['Ring', 'Earring', 'Pendant', 'Necklace', 'Bracelet', 'Chain', 'Bangle', 'Set']).map((p) => <option key={p} value={p} />)}</datalist>
      <button className="bigbtn alt" onClick={() => setItems([...items, blank()])}><Icon n="plus" /> ADD ANOTHER SKU</button>
      <button className="bigbtn gold" disabled={busy} onClick={save}>{busy ? 'Saving…' : addTo ? 'ADD SKU & START' : 'CREATE JOB & START QC'}</button>
      <p className="muted" style={{ fontSize: 12 }}>The job is assigned to you and starts right away. Back office and the reviewer get a notification.</p>
    </>}</Loader>
  </MPage>;
}
