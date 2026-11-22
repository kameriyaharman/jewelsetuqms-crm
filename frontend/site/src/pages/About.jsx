import { CalendarPlus, Linkedin, User } from 'lucide-react';
import { useSite, T, media } from '../site';
import { Icon } from '../icons';
import { CtaBand } from '../components';

export default function About() {
  const { C, openBook } = useSite(); const A = C.about;
  return <>
    <section className="ab-hero">
      <div className="wrap">
        <div className="portrait">
          {A.photo ? <img src={media(A.photo)} alt={A.name.replace(/\*/g, '')} /> : <div className="ph"><User />FOUNDER PORTRAIT</div>}
          {A.badge && <div className="yb">{A.badge}</div>}
        </div>
        <div>
          <span className="eyebrow">{A.eyebrow}</span>
          <T as="h1" text={A.name} />
          <p>{A.bio}</p>
          <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap' }}>
            <button className="btn btn-gold btn-sm" onClick={() => openBook({ message: 'I would like a consultation with ' + A.name.replace(/\*/g, '') + '.' })}><CalendarPlus />Book a consultation</button>
            {A.linkedin && <a className="btn btn-line btn-sm" href={A.linkedin} target="_blank" rel="noopener"><Linkedin />LinkedIn profile</a>}
          </div>
        </div>
      </div>
    </section>
    <section className="sec" style={{ paddingTop: 50 }}>
      <div className="wrap two" style={{ alignItems: 'start' }}>
        <div><span className="eyebrow">Career pedigree</span>
          <ul className="timeline">{A.timeline.map((t, i) => <li key={i} className={t.current ? 'cur' : ''}><small>{t.period}</small><b>{t.role}</b></li>)}</ul></div>
        <div><span className="eyebrow">Verified certifications</span>
          <div className="certs">{A.certs.map((c, i) => <div key={i} className="cert"><Icon name={c.icon} /><b>{c.title}</b><small>{c.issuer}</small></div>)}</div></div>
      </div>
      <div className="wrap" style={{ marginTop: 50 }}><CtaBand title={C.cta.services_title} text={C.cta.services_text} /></div>
    </section>
  </>;
}
