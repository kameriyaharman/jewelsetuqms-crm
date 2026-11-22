import { useCallback, useEffect, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { Bold, Clock, Code, Download, Eye, Heading2, Heading3, ImagePlus, Italic, Link2, List, ListOrdered, Pilcrow, Plus, Quote, Redo2, Send, Table, Trash2, Undo2, FileCode, Search } from 'lucide-react';
import { get, post, upload } from '../api';
import { fmtDate, media } from '../site';
import { useAdmin } from './Admin';
import { usePaged, Pager } from './pager';

export function Posts() {
  const [d, setD] = useState(null); const [q, setQ] = useState(''); const nav = useNavigate();
  useEffect(() => { get('site/admin/posts').then(setD).catch(() => setD({ posts: [] })); }, []);
  const rows = (d?.posts || []).filter((p) => !q || (p.title + ' ' + p.category).toLowerCase().includes(q.toLowerCase()));
  const pg = usePaged(rows, 25, q);
  return <>
    <div className="atop"><div><h1 className="serif">Blog articles</h1><p>Knowledge Hub posts — published articles appear on the website, sitemap and LinkedIn / WhatsApp share cards.</p></div>
      <div className="acts"><label className="sbox"><Search /><input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search articles…" /></label>
        <Link className="btn btn-gold btn-sm" to="/admin/blog/new"><Plus />New article</Link></div></div>
    <div className="card"><div className="tbl-wrap"><table className="tbl">
      <thead><tr><th>Title</th><th>Category</th><th>Status</th><th>Views</th><th>Published</th><th>Updated</th></tr></thead>
      <tbody>{!d ? <tr><td colSpan={6}>Loading…</td></tr> : !rows.length ? <tr><td colSpan={6} style={{ textAlign: 'center', padding: 30, color: 'var(--muted)' }}>No articles yet.</td></tr>
        : pg.rows.map((p) => <tr key={p.id} onClick={() => nav('/admin/blog/' + p.id)}>
          <td><b>{p.title}</b><small>/blog/{p.slug}{p.featured ? ' · ★ Featured' : ''}</small></td><td>{p.category || '—'}</td>
          <td><span className={'st st-' + p.status}>{p.status === 'PUBLISHED' ? 'Published' : 'Draft'}</span></td><td>{p.views}</td>
          <td>{p.published_at ? fmtDate(p.published_at) : '—'}</td><td style={{ color: 'var(--muted)' }}>{fmtDate(p.updated_at)}</td></tr>)}</tbody>
    </table></div><Pager pg={pg} label="articles" /></div>
  </>;
}

const blank = { id: 0, title: '', slug: '', body: '', excerpt: '', cover: '', cover_url: '', category: '', tags: '', author: '', author_title: '', featured: 0, magnet_id: '', meta_title: '', meta_desc: '', status: 'DRAFT', published_at: '' };
const words = (h) => (h.replace(/<[^>]+>/g, ' ').match(/\S+/g) || []).length;
const slugify = (s) => s.toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 90);

export function Editor() {
  const { id } = useParams(); const nav = useNavigate(); const { toast, me } = useAdmin();
  const [p, setP] = useState(null); const [opts, setOpts] = useState({ categories: [], magnets: [] });
  const [dirty, setDirty] = useState(false); const [saved, setSaved] = useState(null); const [busy, setBusy] = useState(false);
  const [src, setSrc] = useState(false); const [slugTouched, setSlugTouched] = useState(false); const [tick, setTick] = useState(0);
  const ed = useRef(null); const titleRef = useRef(null); const pRef = useRef(null); pRef.current = p;
  useEffect(() => {
    get('site/admin/posts').then((r) => setOpts({ categories: r.categories, magnets: r.magnets })).catch(() => {});
    if (id) get('site/admin/post', { id }).then((r) => { setP({ ...blank, ...r.post, magnet_id: r.post.magnet_id || '' }); setSlugTouched(true); setSaved(new Date()); })
      .catch((e) => { toast(e.message, 1); nav('/admin/blog'); });
    else setP({ ...blank });
  }, [id]); // eslint-disable-line
  useEffect(() => { if (p && ed.current && !src && ed.current.innerHTML !== p.body) ed.current.innerHTML = p.body || ''; }, [p?.id, src]); // eslint-disable-line
  useEffect(() => { const el = titleRef.current; if (el) { el.style.height = 'auto'; el.style.height = el.scrollHeight + 'px'; } }, [p?.title]);
  useEffect(() => { const t = setInterval(() => setTick((x) => x + 1), 10000); return () => clearInterval(t); }, []);
  const set = (k, v) => { setP((o) => { const n = { ...o, [k]: v }; if (k === 'title' && !slugTouched) n.slug = slugify(v); return n; }); setDirty(true); };
  const syncBody = () => { if (ed.current) { const h = ed.current.innerHTML; setP((o) => (o.body === h ? o : { ...o, body: h })); setDirty(true); } };

  const save = useCallback(async (status, quiet) => {
    const cur = pRef.current; if (!cur) return;
    if (!cur.title.trim()) { if (!quiet) toast('Add a title first.', 1); return; }
    setBusy(true);
    try {
      const body = ed.current && !src ? ed.current.innerHTML : cur.body;
      const r = await post('site/admin/post/save', { ...cur, body, status: status || cur.status });
      setP((o) => ({ ...o, id: r.id, slug: r.slug, status: status || o.status })); setDirty(false); setSaved(new Date());
      if (!quiet) toast(r.message);
      if (!cur.id) nav('/admin/blog/' + r.id, { replace: true });
      return r;
    } catch (e) { if (!quiet) toast(e.message, 1); } finally { setBusy(false); }
  }, [src]); // eslint-disable-line
  // autosave drafts every 20 s
  useEffect(() => { if (!dirty || !p || p.status !== 'DRAFT' || !p.title.trim()) return; const t = setTimeout(() => save('DRAFT', true), 20000); return () => clearTimeout(t); }, [dirty, p, save]);
  useEffect(() => { const w = (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } }; window.addEventListener('beforeunload', w); return () => window.removeEventListener('beforeunload', w); }, [dirty]);

  const cmd = (c, v) => { ed.current?.focus(); document.execCommand(c, false, v); syncBody(); };
  const block = (t) => cmd('formatBlock', t);
  const pickImg = (cb, pdf) => { const i = document.createElement('input'); i.type = 'file'; i.accept = pdf ? 'application/pdf' : 'image/*';
    i.onchange = async () => { const f = i.files[0]; if (!f) return; const fd = new FormData(); fd.append('file', f); if (pdf) fd.append('pdf', '1');
      try { const r = await upload('site/admin/upload', fd); cb(r); } catch (e) { toast(e.message, 1); } }; i.click(); };
  const addLink = () => { const u = prompt('Link URL (https://…)'); if (u) cmd('createLink', u); };
  const addTable = () => cmd('insertHTML', '<table><thead><tr><th>Column 1</th><th>Column 2</th><th>Column 3</th></tr></thead><tbody><tr><td>—</td><td>—</td><td>—</td></tr><tr><td>—</td><td>—</td><td>—</td></tr></tbody></table><p><br></p>');
  const addMagnet = () => cmd('insertHTML', '<p>{{magnet}}</p>');
  const delPost = async () => { if (!confirm('Delete this article permanently?')) return; await post('site/admin/post/delete', { id: p.id }); toast('Article deleted.'); nav('/admin/blog'); };
  const preview = async () => { let pid = p.id, slug = p.slug; if (!pid || dirty) { const r = await save(null, true); if (!r) return toast('Add a title first.', 1); pid = r.id; slug = r.slug; }
    window.open((import.meta.env.BASE_URL || '/') + 'blog/' + slug + '?preview=' + pid, '_blank'); };

  if (!p) return <div className="spin"><i /></div>;
  const mins = Math.max(1, Math.ceil(words(p.body) / 200));
  const secs = saved ? Math.round((Date.now() - saved.getTime()) / 1000) : null; void tick;
  const host = window.location.host;
  return <div className="ed">
    <div className="ed-main">
      <div className="ed-bar">
        <span className="crumbs"><Link to="/admin/blog">Articles</Link> / <b>{p.id ? 'Edit article' : 'New article'}</b></span>
        <span className={'stat' + (p.status === 'PUBLISHED' ? ' pub' : '')}>{p.status === 'PUBLISHED' ? 'Published' : 'Draft'}{dirty ? ' · unsaved changes' : secs !== null ? ' · saved ' + (secs < 60 ? secs + 's' : Math.round(secs / 60) + ' min') + ' ago' : ''}</span>
        <span className="sp" />
        <button className="btn btn-line btn-sm" onClick={preview}><Eye />Preview</button>
        {p.status === 'PUBLISHED' ? <>
          <button className="btn btn-line btn-sm" disabled={busy} onClick={() => save('DRAFT')}>Unpublish</button>
          <button className="btn btn-gold btn-sm" disabled={busy} onClick={() => save('PUBLISHED')}><Send />Update</button></>
          : <><button className="btn btn-line btn-sm" disabled={busy} onClick={() => save('DRAFT')}>Save draft</button>
            <button className="btn btn-gold btn-sm" disabled={busy} onClick={() => save('PUBLISHED')}><Send />Publish</button></>}
      </div>
      <div className="cover-drop" onClick={() => !p.cover && pickImg((r) => { set('cover', r.path); set('cover_url', r.url); })}>
        {p.cover ? <><img src={p.cover_url || media(p.cover)} alt="" /><button className="rm" onClick={(e) => { e.stopPropagation(); set('cover', ''); set('cover_url', ''); }}>Remove</button>
          <button className="rm" style={{ right: 84 }} onClick={(e) => { e.stopPropagation(); pickImg((r) => { set('cover', r.path); set('cover_url', r.url); }); }}>Change</button></>
          : <span className="ins"><ImagePlus />Upload cover image (1200×630 recommended)</span>}
      </div>
      <textarea ref={titleRef} className="ed-title" rows={1} placeholder="Article title" value={p.title} onChange={(e) => set('title', e.target.value)} />
      <div className="tb" onMouseDown={(e) => e.target.closest('button') && e.preventDefault()}>
        <button title="Heading 2" onClick={() => block('h2')}><Heading2 /></button><button title="Heading 3" onClick={() => block('h3')}><Heading3 /></button><button title="Paragraph" onClick={() => block('p')}><Pilcrow /></button><i />
        <button title="Bold" onClick={() => cmd('bold')}><Bold /></button><button title="Italic" onClick={() => cmd('italic')}><Italic /></button>
        <button title="Bullet list" onClick={() => cmd('insertUnorderedList')}><List /></button><button title="Numbered list" onClick={() => cmd('insertOrderedList')}><ListOrdered /></button><i />
        <button title="Table" onClick={addTable}><Table /></button><button title="Image" onClick={() => pickImg((r) => cmd('insertImage', r.url))}><ImagePlus /></button>
        <button title="Link" onClick={addLink}><Link2 /></button><button title="Quote" onClick={() => block('blockquote')}><Quote /></button><button title="Code block" onClick={() => block('pre')}><Code /></button>
        <button title="Insert lead-magnet download box here" onClick={addMagnet}><Download /></button><i />
        <button title="Undo" onClick={() => cmd('undo')}><Undo2 /></button><button title="Redo" onClick={() => cmd('redo')}><Redo2 /></button>
        <button title="Edit HTML" className={src ? 'on' : ''} onClick={() => { if (!src) syncBody(); setSrc(!src); }}><FileCode /></button>
        <span className="r">Rich text · HTML</span>
      </div>
      {src ? <textarea className="src" value={p.body} onChange={(e) => set('body', e.target.value)} />
        : <div ref={ed} className="rte prose" contentEditable suppressContentEditableWarning onInput={syncBody} onBlur={syncBody}
            onPaste={(e) => { const t = e.clipboardData.getData('text/html'); if (!t) { e.preventDefault(); cmd('insertText', e.clipboardData.getData('text/plain')); } }} />}
      <p style={{ fontSize: 12, color: 'var(--muted)' }}>Tip: the <Download size={12} /> button inserts <code>{'{{magnet}}'}</code> — the lead-magnet download form appears at that spot (otherwise at the end). Table rows with a highlight: edit HTML and add <code>class="hl"</code> to the &lt;tr&gt;.</p>
    </div>
    <aside className="ed-side">
      <h4>ARTICLE SETTINGS</h4>
      <label className="fl">Category</label>
      <select className="inp" value={p.category} onChange={(e) => set('category', e.target.value)}><option value="">— Select —</option>{opts.categories.map((c) => <option key={c}>{c}</option>)}</select>
      <label className="fl">URL slug</label>
      <div className="inp-ico"><span style={{ position: 'absolute', left: 10, top: '50%', transform: 'translateY(-50%)', fontSize: 13, color: 'var(--muted)' }}>/blog/</span>
        <input className="inp" style={{ paddingLeft: 50 }} value={p.slug} onChange={(e) => { setSlugTouched(true); set('slug', slugify(e.target.value)); }} /></div>
      <label className="fl">Lead magnet attached</label>
      <select className="inp" value={p.magnet_id} onChange={(e) => set('magnet_id', e.target.value)}><option value="">None</option>{opts.magnets.map((m) => <option key={m.id} value={m.id}>{m.title}</option>)}</select>
      <div className="tgl">Feature on Knowledge Hub<button className={'sw' + (Number(p.featured) ? ' on' : '')} onClick={() => set('featured', Number(p.featured) ? 0 : 1)} aria-label="Featured" /></div>
      <label className="fl">Tags <span style={{ fontWeight: 400, color: 'var(--muted2)' }}>comma separated</span></label>
      <input className="inp" value={p.tags} onChange={(e) => set('tags', e.target.value)} placeholder="AQL, XRF, ISO9001" />
      <label className="fl">Author</label><input className="inp" value={p.author} onChange={(e) => set('author', e.target.value)} placeholder="Founder (default)" />
      <label className="fl">Publish date</label><input className="inp" type="datetime-local" value={(p.published_at || '').replace(' ', 'T').slice(0, 16)} onChange={(e) => set('published_at', e.target.value ? e.target.value.replace('T', ' ') + ':00' : '')} />
      <hr />
      <h4>SEO META</h4>
      <label className="fl">Meta title<span className={'cnt' + (p.meta_title.length > 60 ? ' bad' : '')}>{p.meta_title.length}/60</span></label>
      <input className="inp" value={p.meta_title} onChange={(e) => set('meta_title', e.target.value)} placeholder={p.title} />
      <label className="fl">Meta description<span className={'cnt' + (p.meta_desc.length > 160 ? ' bad' : '')}>{p.meta_desc.length}/160</span></label>
      <textarea className="inp" rows={3} value={p.meta_desc} onChange={(e) => set('meta_desc', e.target.value)} placeholder="One or two sentences shown in Google and share cards" />
      <label className="fl">Short excerpt <span style={{ fontWeight: 400, color: 'var(--muted2)' }}>for cards (auto if empty)</span></label>
      <textarea className="inp" rows={2} value={p.excerpt} onChange={(e) => set('excerpt', e.target.value)} />
      <div className="ogp"><div className="im" style={p.cover ? { backgroundImage: `url("${p.cover_url || media(p.cover)}")` } : undefined} />
        <div className="tx"><b>{p.meta_title || p.title || 'Article title'}</b><small>{host} · LinkedIn preview</small></div></div>
      <div className="read-t"><Clock />Reading time: <b>{mins} min</b> (auto)</div>
      {p.id && me.user.role === 'super_admin' ? <button className="btn btn-sm" style={{ color: '#b42318', marginTop: 20, paddingLeft: 0 }} onClick={delPost}><Trash2 />Delete article</button> : null}
    </aside>
  </div>;
}
