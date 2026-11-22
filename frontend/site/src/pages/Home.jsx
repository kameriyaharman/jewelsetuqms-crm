import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { CalendarPlus, Check, CheckCircle2, FileText, MapPin, ScanLine, TrendingDown } from 'lucide-react';
import { get } from '../api';
import { useSite, T } from '../site';
import { Diamond, Icon } from '../icons';
import { CountUp } from '../motion';
import { ArrowLink, CtaBand, PostCard } from '../components';

export function HeroArt({ badges = [] }) {
  const pos = [{ left: '2%', top: '9%' }, { right: '0%', top: '6%' }, { left: '-2%', bottom: '20%' }, { right: '4%', bottom: '26%' }];
  const ic = [<CheckCircle2 key="a" />, null, null, <TrendingDown key="d" style={{ color: 'var(--gold-d)' }} />];
  return <div className="hero-art" aria-hidden="true">
    <svg className="rings" viewBox="0 0 420 420" fill="none">
      <circle cx="210" cy="210" r="205" stroke="#e8d6a8" strokeWidth="1" />
      <circle className="spin" cx="210" cy="210" r="160" stroke="#e8d6a8" strokeDasharray="3 6" />
      <circle cx="210" cy="210" r="118" stroke="#efe1bb" />
      <path d="M5 210h410M210 5v410" stroke="#f1e6c8" strokeDasharray="2 8" />
    </svg>
    <Diamond className="dia" stroke="#c9a24a" w={1.6} />
    {badges.slice(0, 4).map((b, i) => <div key={i} className="fbadge" style={pos[i]}><small>{b.label}</small><b>{ic[i]}{b.value}</b></div>)}
  </div>;
}

export default function Home() {
  const { C, openBook, setReport } = useSite();
  const H = C.home; const P = C.pillars; const TR = C.trace;
  const [posts, setPosts] = useState(null);
  useEffect(() => { get('site/pub/posts', { limit: 3 }).then((r) => setPosts(r.posts)).catch(() => setPosts([])); }, []);
  const done = Math.max(1, Math.round((TR.steps.length || 6) / 2));
  return <>
    <section className="hero">
      <div className="wrap">
        <div>
          <span className="eyebrow">{H.eyebrow}</span>
          <T as="h1" text={H.title} anim />
          <p className="lead">{H.subtitle}</p>
          <div className="ctas">
            <button className="btn btn-gold btn-lg" onClick={() => openBook()}><CalendarPlus />{H.cta_primary}</button>
            <button className="btn btn-line btn-lg" onClick={() => setReport(true)}><FileText />{H.cta_secondary}</button>
          </div>
          <div className="cities"><MapPin />On-ground in {(C.brand.cities || []).map((c, i) => <span key={c} style={{ display: 'contents' }}>{i > 0 && <i />}<b>{c}</b></span>)}</div>
        </div>
        <HeroArt badges={H.badges} />
      </div>
      <div className="wrap plain"><div className="stats">
        {H.stats.map((s, i) => <div key={i}><div className={'v' + (/^\d/.test(s.value) ? '' : ' txt')}><CountUp value={s.value} />{s.suffix && <sup>{s.suffix}</sup>}</div><div className="l">{s.label}</div></div>)}
      </div></div>
      <div className="hero-pad" />
    </section>

    <section className="sec" id="services">
      <div className="wrap">
        <div className="sec-head"><div><span className="eyebrow">{P.eyebrow}</span><T as="h2" className="h-sec" text={P.title} /></div><p>{P.intro}</p></div>
        <div className="pillars">{P.items.map((p, i) => <div key={i} className={'pcard' + (i === 1 ? ' dark' : '')}>
          <span className="ico"><Icon name={p.icon} /></span><h3>{p.title}</h3><p>{p.text}</p>
          <ul>{(p.points || []).map((x) => <li key={x}><Check />{x}</li>)}</ul>
          <ArrowLink to={'/services#' + (p.anchor || '')}>Explore</ArrowLink></div>)}</div>
      </div>
    </section>

    <section className="sec cream">
      <Diamond className="trace-dia" stroke="#e3cd92" w={0.7} />
      <div className="wrap" style={{ position: 'relative' }}>
        <div className="trace-head"><div><span className="eyebrow">{TR.eyebrow}</span><T as="h2" className="h-sec" text={TR.title} /></div>
          <Link to="/q-track" className="btn btn-gline"><ScanLine />Explore Q-Track Portal</Link></div>
        <div className="steps">{TR.steps.map((s, i) => <div key={i} className={'step' + (i < done ? ' done' : '')}><div className="c"><Icon name={s.icon} /></div><b>{s.title}</b><small>{s.text}</small></div>)}</div>
        <div className="lot">
          <div><small>{TR.lot_code}</small><b style={{ fontSize: 15 }}>{TR.lot_product}</b><div className="bar"><i style={{ width: (Number(TR.lot_progress) || 68) + '%' }} /></div></div>
          <div><div className="k">Sample size</div><div className="v serif">{TR.lot_sample}</div></div>
          <div><div className="k">Major / Minor</div><div className="v serif">{TR.lot_majmin}</div></div>
          <span className="chip-ok"><CheckCircle2 />{TR.lot_status}</span>
        </div>
      </div>
    </section>

    <section className="sec white" style={{ paddingBottom: 30 }} id="insights">
      <div className="wrap">
        <div className="aud">{C.audiences.items.map((a, i) => <div key={i} className="acard"><span className="ico sm"><Icon name={a.icon} /></span><div><h3>{a.title}</h3><p>{a.text}</p></div></div>)}</div>
      </div>
    </section>
    <section className="sec white" style={{ paddingTop: 40 }}>
      <div className="wrap">
        <div className="sec-head" style={{ marginBottom: 22 }}><div><span className="eyebrow">{C.blog.eyebrow}</span><T as="h2" className="h-sec" text={C.blog.home_title} /></div>
          <ArrowLink to="/blog">View all articles</ArrowLink></div>
        {posts && posts.length > 0 && <div className="posts">{posts.map((p, i) => <PostCard key={p.id} p={p} i={i} />)}</div>}
        <div style={{ marginTop: 40 }}><CtaBand title={C.cta.title} text={C.cta.text} /></div>
      </div>
    </section>
  </>;
}
