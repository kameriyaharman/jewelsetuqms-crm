import { useEffect, useState } from 'react';
import { FileDown, FileText, Plus, Search, Trash2, X } from 'lucide-react';
import { apiUrl, get, post, upload } from '../api';
import { fmtDate } from '../site';
import { useAdmin } from './Admin';

export function Magnets() {
  const { toast, me } = useAdmin();
  const [rows, setRows] = useState(null); const [edit, setEdit] = useState(null); const [file, setFile] = useState(null); const [busy, setBusy] = useState(false);
  const load = () => get('site/admin/magnets').then((r) => setRows(r.rows)).catch(() => setRows([]));
  useEffect(() => { load(); }, []);
  const save = async (e) => {
    e.preventDefault(); setBusy(true);
    const fd = new FormData(); fd.append('id', edit.id || ''); fd.append('title', edit.title); fd.append('description', edit.description || ''); if (Number(edit.active)) fd.append('active', '1');
    if (file) fd.append('file', file);
    try { const r = await upload('site/admin/magnet/save', fd); toast(r.message); setEdit(null); setFile(null); load(); } catch (x) { toast(x.message, 1); } finally { setBusy(false); }
  };
  const del = async (m) => { if (!confirm('Delete “' + m.title + '”? Articles using it will show no download box.')) return; await post('site/admin/magnet/delete', { id: m.id }); toast('Deleted.'); load(); };
  return <>
    <div className="atop"><div><h1 className="serif">Lead magnets</h1><p>Free PDFs (e.g. AQL Defect Tolerance Chart) offered in exchange for name + work email. Attach one to any article.</p></div>
      <div className="acts"><button className="btn btn-gold btn-sm" onClick={() => { setFile(null); setEdit({ title: '', description: '', active: 1 }); }}><Plus />New lead magnet</button></div></div>
    {rows && rows.some((m) => !m.file_path) && <div className="notice">Upload the PDF for each lead magnet — until then visitors get a “we will email it to you” message and you see their details under Downloads.</div>}
    <div className="pgrid">{(rows || []).map((m) => <div key={m.id} className="card mg">
      <div style={{ display: 'flex', justifyContent: 'space-between', gap: 10 }}><h3>{m.title}</h3><span className={'st ' + (Number(m.active) ? 'st-WON' : 'st-LOST')}>{Number(m.active) ? 'Active' : 'Hidden'}</span></div>
      <p>{m.description}</p>
      <div className="row"><span><b>{m.downloads}</b> downloads</span><span><b>{m.posts}</b> articles</span></div>
      <div className="row">{m.file_url ? <a href={m.file_url} target="_blank" rel="noopener" style={{ color: 'var(--gold-d)', display: 'inline-flex', gap: 6, alignItems: 'center' }}><FileText size={15} />{m.file_name}</a> : <span style={{ color: '#b42318' }}>No file uploaded yet</span>}</div>
      <div className="acts"><button className="btn btn-line btn-sm" onClick={() => { setFile(null); setEdit(m); }}>Edit</button>
        {me.user.role === 'super_admin' && <button className="btn btn-sm" style={{ color: '#b42318' }} onClick={() => del(m)}><Trash2 /></button>}</div>
    </div>)}</div>
    {edit && <><div className="drawer-ovl" onClick={() => setEdit(null)} />
      <form className="drawer" onSubmit={save}>
        <div className="dh"><h3>{edit.id ? 'Edit lead magnet' : 'New lead magnet'}</h3><button type="button" onClick={() => setEdit(null)}><X /></button></div>
        <div className="db">
          <label className="fl">Title</label><input className="inp" value={edit.title} onChange={(e) => setEdit({ ...edit, title: e.target.value })} required />
          <label className="fl">Short description</label><textarea className="inp" rows={3} value={edit.description || ''} onChange={(e) => setEdit({ ...edit, description: e.target.value })} />
          <label className="fl">File (PDF){edit.file_name ? ' — current: ' + edit.file_name : ''}</label>
          <input type="file" accept=".pdf,.xlsx,.docx,.zip" onChange={(e) => setFile(e.target.files[0])} />
          <div className="tgl">Show on website<button type="button" className={'sw' + (Number(edit.active) ? ' on' : '')} onClick={() => setEdit({ ...edit, active: Number(edit.active) ? 0 : 1 })} /></div>
        </div>
        <div className="df"><span /><button className="btn btn-navy btn-sm" disabled={busy}>{busy ? 'Saving…' : 'Save'}</button></div>
      </form></>}
  </>;
}

export function Downloads() {
  const [rows, setRows] = useState(null); const [q, setQ] = useState('');
  useEffect(() => { get('site/admin/downloads').then((r) => setRows(r.rows)).catch(() => setRows([])); }, []);
  const list = (rows || []).filter((r) => !q || [r.name, r.company, r.email].join(' ').toLowerCase().includes(q.toLowerCase()));
  return <>
    <div className="atop"><div><h1 className="serif">PDF downloads</h1><p>People who downloaded a lead magnet — warm leads for follow-up.</p></div>
      <div className="acts"><label className="sbox"><Search /><input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search…" /></label>
        <a className="btn btn-navy btn-sm" href={apiUrl('site/admin/downloads.csv')}><FileDown />Export CSV</a></div></div>
    <div className="card"><div className="tbl-wrap"><table className="tbl">
      <thead><tr><th>Name</th><th>Email</th><th>Lead magnet</th><th>From article</th><th>Date</th></tr></thead>
      <tbody>{!rows ? <tr><td colSpan={5}>Loading…</td></tr> : !list.length ? <tr><td colSpan={5} style={{ textAlign: 'center', padding: 30, color: 'var(--muted)' }}>No downloads yet.</td></tr>
        : list.map((r) => <tr key={r.id} style={{ cursor: 'default' }}><td><b>{r.name}</b><small>{r.company}</small></td><td><a href={'mailto:' + r.email} style={{ color: 'var(--gold-d)' }}>{r.email}</a></td>
          <td>{r.magnet || '—'}</td><td style={{ maxWidth: 260 }}>{r.post || '—'}</td><td style={{ color: 'var(--muted)', whiteSpace: 'nowrap' }}>{fmtDate(r.created_at)}</td></tr>)}</tbody>
    </table></div></div>
  </>;
}
