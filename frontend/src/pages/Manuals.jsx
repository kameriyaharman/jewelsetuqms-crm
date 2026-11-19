import { useState } from 'react';
import { Page } from '../app/Layout';
import { fileUrl, post, upload } from '../app/api';
import { Empty, Icon, Loader, Modal, Sel, useAction, useApi, useToast } from '../app/ui';
import { fdate } from '../app/util';

const ICON = { pdf: '📕', xlsx: '📗', xls: '📗', docx: '📘', doc: '📘', jpg: '🖼️', jpeg: '🖼️', png: '🖼️', webp: '🖼️' };
const isImg = (e) => ['jpg', 'jpeg', 'png', 'webp'].includes(e);

/** Opens a manual inside the app: image / PDF inline, other files download; text note shown. */
export function ManualViewer({ m, onClose }) {
  if (!m) return null;
  const u = m.path ? fileUrl(m.path) : null;
  return <Modal open title={`${m.title} · v${m.version}`} onClose={onClose} wide>
    {m.priority === 'HIGH' && <div className="note ruby mb" style={{ fontSize: 12.5 }}>Mandatory / high priority instruction</div>}
    {m.note && <div className="note gold mb" style={{ whiteSpace: 'pre-wrap', fontSize: 13.5 }}>{m.note}</div>}
    {u && isImg(m.ext) && <img src={u} alt={m.title} style={{ width: '100%', borderRadius: 10, border: '1px solid var(--line)' }} />}
    {u && m.ext === 'pdf' && <iframe title={m.title} src={u} style={{ width: '100%', height: '70vh', border: '1px solid var(--line)', borderRadius: 10 }} />}
    {u && <div className="actions" style={{ justifyContent: 'flex-start' }}><a className="btn" href={u} target="_blank" rel="noreferrer">Open full screen</a><a className="btn" href={u + (u.includes('?') ? '&' : '?') + 'dl=1'}>⬇ Download {m.file_name}</a></div>}
    <p className="muted" style={{ fontSize: 12 }}>{m.client_code} · {m.scope_label} · by {m.uploader || '—'} ({m.uploader_role}) · updated {fdate(m.created_at, 'd M Y')}</p>
  </Modal>;
}

/** Row list of manuals with NEW / UPDATED badge, version, view / download, update and history. */
export function ManualList({ rows, onEdit, onDelete, compact }) {
  const [v, setV] = useState(null); const [h, setH] = useState(null);
  if (!rows.length) return <Empty>No QC manuals or instructions yet.</Empty>;
  return <>{rows.map((m) => <div key={m.id} className="manrow">
    <span className="manic">{m.path ? ICON[m.ext] || '📄' : '📝'}</span>
    <div style={{ flex: 1, minWidth: 0 }}><b>{m.title}</b> <span className="pill">v{m.version}</span>{m.badge && <span className={'pill ' + (m.badge === 'NEW' ? 'pnew' : 'pupd')}>{m.badge}</span>}{m.priority === 'HIGH' && <span className="pill phigh">MANDATORY</span>}
      <small>{m.client_code} · {m.scope_label} · {m.uploader_role === 'vendor' ? 'Vendor note' : 'Client manual'} · updated {fdate(m.created_at, 'd M Y')}{m.file_name ? ' · ' + m.file_name : ''}</small>
      {m.note && !compact && <small className="mannote">{m.note}</small>}</div>
    <div className="actions" style={{ margin: 0, flexWrap: 'nowrap' }}><button className="btn sm" onClick={() => setV(m)}>View</button>
      {m.path && <a className="btn sm" href={fileUrl(m.path) + (fileUrl(m.path).includes('?') ? '&' : '?') + 'dl=1'} title="Download">⬇</a>}
      {!compact && m.can_edit && onEdit && <button className="btn sm" onClick={() => onEdit(m)}>Update</button>}
      {!compact && m.history?.length > 0 && <button className="btn sm" onClick={() => setH(m)}>History ({m.history.length})</button>}
      {!compact && m.can_edit && onDelete && <button className="btn sm" style={{ color: 'var(--ruby)' }} onClick={() => onDelete(m)}>✕</button>}</div>
  </div>)}
  <ManualViewer m={v} onClose={() => setV(null)} />
  <Modal open={!!h} title={h ? 'Version history · ' + h.title : ''} onClose={() => setH(null)}>{h && h.history.map((x) => <div key={x.id} className="manrow"><span className="manic">{x.path ? ICON[x.ext] || '📄' : '📝'}</span>
    <div style={{ flex: 1 }}><b>v{x.version}</b><small>{fdate(x.created_at, 'd M Y, h:i A')} · {x.uploader}{x.file_name ? ' · ' + x.file_name : ''}</small></div>
    <button className="btn sm" onClick={() => { setH(null); setV(x); }}>View</button></div>)}</Modal></>;
}

/** Upload / update form (client: scope; vendor: which client; internal: both). */
function ManualForm({ row, opts, role, fixedClient, onClose, onSaved }) {
  const [f, setF] = useState(() => ({ client_id: fixedClient || row?.client_id || opts.clients[0]?.id || '', scope: row?.scope || 'ALL', vendor_id: row?.vendor_id || '', po_no: row?.po_no || '', title: row?.title || '', note: row?.note || '', priority: row?.priority || 'NORMAL' }));
  const [file, setFile] = useState(null); const [busy, setBusy] = useState(false); const toast = useToast();
  const vendors = (opts.vendors || []).filter((v) => !f.client_id || String(v.client_id) === String(f.client_id));
  const save = async () => {
    const fd = new FormData(); fd.append('data', JSON.stringify({ ...f, id: row?.id || 0 })); if (file) fd.append('file', file);
    setBusy(true); try { const r = await upload('manuals/save', fd); toast(r.message); onSaved(); } catch (e) { toast(e.message, 'err'); } setBusy(false);
  };
  return <Modal open title={row ? `Update · ${row.title} (now v${row.version})` : 'Upload QC manual / instruction'} onClose={onClose} wide>
    {role !== 'client' && !fixedClient && <div className="f"><label>{role === 'vendor' ? 'This instruction is for client' : 'Client'}</label><Sel value={f.client_id} onChange={(v) => setF({ ...f, client_id: v, vendor_id: '' })} options={opts.clients.map((c) => [c.id, c.code + ' · ' + c.name])} /></div>}
    {role !== 'vendor' && <div className="f"><label>Applies to</label><div className="stack">
      {[['ALL', 'Apply to all my vendors (master brand manual)'], ['VENDOR', 'Specific vendor only'], ['PO', 'Specific PO / design only']].map(([k, l]) => <label key={k} className="rc"><input type="radio" checked={f.scope === k} onChange={() => setF({ ...f, scope: k })} /> {l}</label>)}</div></div>}
    {role !== 'vendor' && f.scope !== 'ALL' && <div className="fr f"><div><label>Vendor{f.scope === 'PO' ? ' (optional)' : ''}</label><Sel value={f.vendor_id} onChange={(v) => setF({ ...f, vendor_id: v })} options={vendors.map((v) => [v.id, v.name])} blank="Select vendor" /></div>
      {f.scope === 'PO' && <div><label>PO no.</label><input type="text" value={f.po_no} onChange={(e) => setF({ ...f, po_no: e.target.value })} /></div>}</div>}
    <div className="f"><label>Title</label><input type="text" value={f.title} placeholder={role === 'vendor' ? 'e.g. Factory spec sheet / packaging guide' : 'e.g. Brand QC manual 2026 / Defect boundary chart'} onChange={(e) => setF({ ...f, title: e.target.value })} /></div>
    <div className="f"><label>File (PDF, Excel, Word, JPG / PNG){row?.file_name ? ' — leave empty to keep ' + row.file_name : ''}</label><input type="file" accept=".pdf,.xlsx,.xls,.docx,.doc,.jpg,.jpeg,.png,.webp" onChange={(e) => setFile(e.target.files[0] || null)} /></div>
    <div className="f"><label>Quick text instruction (optional)</label><textarea rows={4} value={f.note} placeholder="Short instruction — no file needed" onChange={(e) => setF({ ...f, note: e.target.value })} /></div>
    <label className="chk f"><input type="checkbox" checked={f.priority === 'HIGH'} onChange={(e) => setF({ ...f, priority: e.target.checked ? 'HIGH' : 'NORMAL' })} /> Mandatory / high priority — inspector must tick “I have read” before starting QC</label>
    <div className="actions"><button className="btn" onClick={onClose}>Cancel</button><button className="btn pri" disabled={busy} onClick={save}>{busy ? 'Uploading…' : row ? 'Save new version' : 'Upload & share'}</button></div>
  </Modal>;
}

/** Full panel (used on the Manuals page and inside a client profile). */
export function ManualsPanel({ client, title }) {
  const q = useApi('manuals', client ? { client } : {}); const [form, setForm] = useState(null); const { run } = useAction();
  return <Loader q={q}>{(d) => <div className="card"><div className="card-h"><h2>{title || 'QC Manuals & Instructions'}</h2>
    {d.can_upload && <button className="btn pri sm" onClick={() => setForm({})}><Icon n="upload" /> Upload / add instruction</button>}</div>
    <ManualList rows={d.rows} onEdit={(m) => setForm({ row: m })} onDelete={async (m) => { if (window.confirm(`Remove “${m.title}” (all versions)?`) && await run(() => post('manuals/delete', { id: m.id }))) q.reload(); }} />
    {form && <ManualForm row={form.row} opts={d.opts} role={d.role === 'client' || d.role === 'vendor' ? d.role : 'internal'} fixedClient={client} onClose={() => setForm(null)} onSaved={() => { setForm(null); q.reload(); }} />}
  </div>}</Loader>;
}

export default function Manuals() {
  return <Page title="📘 QC Manuals & Instructions" sub="Brand QC manuals, SOPs, defect boundary charts, special PO notes and factory spec sheets — always the latest version">
    <ManualsPanel /></Page>;
}
