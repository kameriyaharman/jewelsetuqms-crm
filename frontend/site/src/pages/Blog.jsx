import { useEffect, useRef, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { ArrowRight, Clock, Download, Search, Star, User } from 'lucide-react';
import { get, post } from '../api';
import { useSite, T, media } from '../site';
import { Diamond } from '../icons';
import { PostCard, Spinner } from '../components';
import { usePaged, Pager } from '../admin/pager';

export function MagnetSide({ magnet }) {
  const [open, setOpen] = useState(false);
  if (!magnet) return null;
  return <div className="side-pdf">
    <Diamond className="dia" stroke="#c9a24a" w={1.2} />
    <span className="tag"><Download />FREE PDF</span>
    <h3>{magnet.title}</h3><p>{magnet.description}</p>
    {open ? <MagnetForm magnet={magnet} dark /> : <button className="btn btn-gold btn-sm" onClick={() => setOpen(true)}>Get the chart<ArrowRight /></button>}
  </div>;
}
export function MagnetForm({ magnet, postId, dark }) {
  const [f, setF] = useState({ name: '', company: '', email: '', website: '' });
  const [st, setSt] = useState({ busy: false, err: '', ok: '' });
  const submit = async (e) => {
    e.preventDefault(); setSt({ busy: true, err: '', ok: '' });
    try {
      const r = await post('site/pub/download', { ...f, magnet_id: magnet.id, post_id: postId });
      if (r.url) { window.location.href = r.url; setSt({ busy: false, ok: 'Your download has started.' }); }
      else setSt({ busy: false, ok: r.message || 'Thank you!' });
    } catch (er) { setSt({ busy: false, err: er.message }); }
  };
  if (st.ok) return <div className="ok" style={{ color: dark ? 'var(--gold-l)' : 'var(--ok)', fontWeight: 700, fontSize: 14 }}>✓ {st.ok}</div>;
  const s = dark ? { background: 'rgba(255,255,255,.08)', border: '1px solid rgba(255,255,255,.15)', color: '#fff' } : undefined;
  return <form onSubmit={submit} style={{ display: 'flex', gap: 8, flexWrap: 'wrap', position: 'relative' }}>
    <input className="inp" style={{ ...s, flex: '1 1 130px', padding: '9px 12px' }} placeholder="Name" value={f.name} onChange={(e) => setF({ ...f, name: e.target.value })} required />
    {!dark && <input className="inp" style={{ flex: '1 1 130px', padding: '9px 12px' }} placeholder="Company" value={f.company} onChange={(e) => setF({ ...f, company: e.target.value })} />}
    <input className="inp" style={{ ...s, flex: '1 1 160px', padding: '9px 12px' }} type="email" placeholder="Work email" value={f.email} onChange={(e) => setF({ ...f, email: e.target.value })} required />
    <input className="hp" tabIndex={-1} autoComplete="off" value={f.website} onChange={(e) => setF({ ...f, website: e.target.value })} />
    <button className="btn btn-gold btn-sm" disabled={st.busy}>{st.busy ? '…' : 'Get PDF'}</button>
    {st.err && <div style={{ width: '100%', color: dark ? '#ffb4a8' : '#b42318', fontSize: 12.5 }}>{st.err}</div>}
  </form>;
}

export default function Blog() {
  const { C, site } = useSite(); const B = C.blog;
  const [sp, setSp] = useSearchParams();
  const cat = sp.get('cat') || ''; const tag = sp.get('tag') || ''; const qq = sp.get('q') || '';
  const [q, setQ] = useState(qq);
  const [posts, setPosts] = useState(null);
  const ref = useRef(null);
  useEffect(() => { setPosts(null); get('site/pub/posts', { cat, tag, q: qq, limit: 500 }).then((r) => setPosts(r.posts)).catch(() => setPosts([])); }, [cat, tag, qq]);
  useEffect(() => { const k = (e) => { if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); ref.current?.focus(); } }; window.addEventListener('keydown', k); return () => window.removeEventListener('keydown', k); }, []);
  useEffect(() => { const t = setTimeout(() => { if (q !== qq) { const n = new URLSearchParams(sp); q ? n.set('q', q) : n.delete('q'); setSp(n, { replace: true }); } }, 350); return () => clearTimeout(t); }, [q]); // eslint-disable-line
  const setParam = (k, v) => { const n = new URLSearchParams(); if (v) n.set(k, v); setQ(''); setSp(n); };
  const filtered = cat || tag || qq;
  const feat = !filtered && posts ? (posts.find((p) => p.featured) || posts[0]) : null;
  const rest = posts ? posts.filter((p) => p !== feat) : [];
  const pg = usePaged(rest, 12, cat + '|' + tag + '|' + qq);
  const magnet = (site?.magnets || [])[0];
  return <>
    <section className="phero">
      <div className="wrap">
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-end', gap: 20, flexWrap: 'wrap', marginBottom: 20 }}>
          <div><span className="eyebrow">{B.eyebrow}</span><T as="h1" text={B.title} /></div>
          <label className="search"><Search /><input ref={ref} value={q} onChange={(e) => setQ(e.target.value)} placeholder={'Search "AQL", "porosity", "lab-grown"…'} /><kbd>⌘K</kbd></label>
        </div>
        <div className="chips">
          <button className={'chip' + (!cat ? ' on' : '')} onClick={() => setParam('cat', '')}>All articles</button>
          {B.categories.map((c) => <button key={c} className={'chip' + (cat === c ? ' on' : '')} onClick={() => setParam('cat', c)}>{c}</button>)}
        </div>
      </div>
    </section>
    <section className="sec" style={{ paddingTop: 40 }}>
      <div className="wrap blog-grid">
        <div>
          {!posts ? <Spinner /> : !posts.length ? <div className="empty">No articles found{qq ? ` for “${qq}”` : ''}.</div> : <>
            {feat && pg.page === 1 && <Link to={'/blog/' + feat.slug} className="feat">
              <div className="cv">{feat.cover ? <img className="cover" src={media(feat.cover)} alt="" /> : <Diamond className="dia" stroke="#c9a24a" w={1.3} />}<span className="star"><Star />Featured</span></div>
              <div className="bd"><span className="pill">{feat.category}</span><h2 className="serif">{feat.title}</h2><p>{feat.excerpt}</p>
                <div className="meta-row"><span><User />{feat.author}</span><span><Clock />{feat.read_min} min read</span></div></div>
            </Link>}
            {filtered && <p style={{ margin: '0 0 16px', color: 'var(--muted)', fontSize: 14 }}>{posts.length} article{posts.length > 1 ? 's' : ''}{cat ? ' in ' + cat : ''}{tag ? ' tagged #' + tag : ''}{qq ? ` matching “${qq}”` : ''}</p>}
            {rest.length > 0 && <div className="posts small" style={{ marginTop: feat ? 20 : 0 }}>{pg.rows.map((p, i) => <PostCard key={p.id} p={p} i={i} small />)}</div>}
            <Pager pg={pg} simple onPage={() => window.scrollTo({ top: 0, behavior: 'smooth' })} />
          </>}
        </div>
        <aside>
          <MagnetSide magnet={magnet} />
          <div className="side-box"><h4>POPULAR TOPICS</h4>
            <div className="tags">{B.topics.map((t) => <button key={t} className={tag === t ? 'on' : ''} onClick={() => setParam('tag', tag === t ? '' : t)}>#{t}</button>)}</div></div>
        </aside>
      </div>
    </section>
  </>;
}
