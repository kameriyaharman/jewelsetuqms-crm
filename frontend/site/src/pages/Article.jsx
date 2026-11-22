import { Fragment, useEffect, useMemo, useRef, useState } from 'react';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import { CalendarPlus, Download, Link2, Linkedin } from 'lucide-react';
import { get } from '../api';
import { useSite, fmtDate, initials, media } from '../site';
import { Spinner, PostCard, WaIcon } from '../components';
import { MagnetForm } from './Blog';
import NotFound from './NotFound';

function MagnetBox({ magnet, postId }) {
  return <div className="magnet"><div className="doc"><Download /></div><div style={{ flex: 1 }}><b>Download: {magnet.title} (PDF)</b><MagnetForm magnet={magnet} postId={postId} /></div></div>;
}

export default function Article() {
  const { slug } = useParams(); const [sp] = useSearchParams();
  const { C, openBook, toast } = useSite();
  const [d, setD] = useState(null); const [err, setErr] = useState(false);
  const [pct, setPct] = useState(0); const [active, setActive] = useState('');
  const bodyRef = useRef(null);
  useEffect(() => { setD(null); setErr(false); window.scrollTo(0, 0);
    get('site/pub/post', { slug, preview: sp.get('preview') }).then((r) => { setD(r); document.title = (r.post.meta_title || r.post.title) + ' | ' + C.brand.name; }).catch(() => setErr(true)); }, [slug]); // eslint-disable-line

  // body split at {{magnet}} + headings with ids for the table of contents
  const { parts, toc } = useMemo(() => {
    if (!d) return { parts: [], toc: [] };
    const toc = []; let n = 0;
    const html = d.post.body.replace(/<h2([^>]*)>([\s\S]*?)<\/h2>/g, (m, a, inner) => {
      const txt = inner.replace(/<[^>]+>/g, '').trim(); const id = 's-' + (++n) + '-' + txt.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 40);
      toc.push({ id, txt }); return `<h2 id="${id}">${inner}</h2>`;
    });
    return { parts: html.split(/<p>\s*\{\{magnet\}\}\s*<\/p>|\{\{magnet\}\}/), toc };
  }, [d]);

  useEffect(() => {
    const on = () => {
      const el = bodyRef.current; if (!el) return;
      const r = el.getBoundingClientRect(); const h = el.offsetHeight - window.innerHeight + 200;
      setPct(Math.max(0, Math.min(100, ((-r.top + 120) / Math.max(1, h)) * 100)));
      let cur = ''; toc.forEach((t) => { const x = document.getElementById(t.id); if (x && x.getBoundingClientRect().top < 140) cur = t.id; }); setActive(cur || toc[0]?.id || '');
    };
    on(); window.addEventListener('scroll', on, { passive: true }); return () => window.removeEventListener('scroll', on);
  }, [toc]);

  if (err) return <NotFound what="article" />;
  if (!d) return <Spinner />;
  const p = d.post; const url = window.location.origin + window.location.pathname;
  const share = { li: 'https://www.linkedin.com/sharing/share-offsite/?url=' + encodeURIComponent(url), wa: 'https://wa.me/?text=' + encodeURIComponent(p.title + ' — ' + url) };
  const copy = () => navigator.clipboard?.writeText(url).then(() => toast('Link copied'));
  return <>
    <div className="progress" style={{ width: pct + '%' }} />
    <div className="wrap art-grid">
      <article className="art">
        <div className="crumb"><Link to="/blog">Knowledge Hub</Link>{p.category && <> / <Link to={'/blog?cat=' + encodeURIComponent(p.category)}><span>{p.category}</span></Link></>}</div>
        {p.status !== 'PUBLISHED' && <div className="err" style={{ margin: '6px 0 10px' }}>Preview — this article is a draft and not visible to visitors.</div>}
        <h1 className="serif">{p.title}</h1>
        {p.cover && <img src={media(p.cover)} alt="" style={{ borderRadius: 14, marginBottom: 20, width: '100%', maxHeight: 380, objectFit: 'cover' }} />}
        <div className="byline">
          <span className="avatar">{C.about.photo && p.author === C.about.author_short ? <img src={media(C.about.photo)} alt="" /> : initials(p.author)}</span>
          <div><b>{p.author}</b><small>{p.author_title} · {fmtDate(p.published_at)} · {p.read_min} min read</small></div>
          <div className="share">
            <a className="li" href={share.li} target="_blank" rel="noopener"><Linkedin />Share</a>
            <a className="wa" href={share.wa} target="_blank" rel="noopener"><WaIcon />WhatsApp</a>
            <button className="cp" onClick={copy} aria-label="Copy link"><Link2 /></button>
          </div>
        </div>
        <div className="prose" ref={bodyRef}>
          {parts.map((h, i) => <Fragment key={i}>
            <div dangerouslySetInnerHTML={{ __html: h }} />
            {i < parts.length - 1 && d.magnet && <MagnetBox magnet={d.magnet} postId={p.id} />}
          </Fragment>)}
          {parts.length === 1 && d.magnet && <MagnetBox magnet={d.magnet} postId={p.id} />}
        </div>
        {p.tags.length > 0 && <div className="tags" style={{ marginTop: 26 }}>{p.tags.map((t) => <Link key={t} to={'/blog?tag=' + encodeURIComponent(t)}><button>#{t}</button></Link>)}</div>}
      </article>
      <aside className="toc">
        {toc.length > 1 && <><h4>ON THIS PAGE</h4><nav>{toc.map((t) => <a key={t.id} href={'#' + t.id} className={active === t.id ? 'on' : ''}
          onClick={(e) => { e.preventDefault(); document.getElementById(t.id)?.scrollIntoView({ behavior: 'smooth' }); }}>{t.txt}</a>)}</nav></>}
        <div className="cta-card"><h3>Need an AQL inspection for your next lot?</h3><p>24-hour certificate · {(C.brand.cities || []).join(', ')}</p>
          <button className="btn btn-gold" onClick={() => openBook()}><CalendarPlus />Book an Audit</button></div>
        <div className="og-card"><span className="li"><Linkedin /></span><div><b>Share this article</b>Rich preview card on LinkedIn & WhatsApp</div></div>
      </aside>
    </div>
    {d.related.length > 0 && <section className="related" style={{ background: '#fff' }}><div className="wrap">
      <span className="eyebrow">Keep reading</span><h2 className="h-sec" style={{ marginBottom: 24 }}>Related <em className="g">articles</em></h2>
      <div className="posts">{d.related.map((r, i) => <PostCard key={r.id} p={r} i={i} />)}</div></div></section>}
  </>;
}
