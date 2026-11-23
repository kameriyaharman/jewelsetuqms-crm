import { ArrowRight, Check, ScanLine, ShieldCheck, Smartphone } from 'lucide-react';
import { useSite, T } from '../site';
import { Icon } from '../icons';
import { ERP_LOGIN } from '../components';

export default function QTrack() {
  const { C, openBook } = useSite(); const Q = C.qtrack;
  return <section className="qt-hero">
    <div className="wrap">
      <span className="qt-badge"><ScanLine />{Q.badge}</span>
      <T as="h1" text={Q.title} />
      <p style={{ color: 'var(--muted)', margin: 0 }}>{Q.subtitle}</p>
      <div className="roles">{Q.roles.map((r, i) => <div key={i} className={'role' + (i === 0 ? ' gold' : '')}>
        <div className="top"><span className="ico"><Icon name={r.icon} /></span><small>ROLE {String(i + 1).padStart(2, '0')}</small></div>
        <h3>{r.title}</h3><div className="sub">{r.sub}</div>
        <ul>{(r.points || []).map((p) => <li key={p}><Check />{p}</li>)}</ul>
        <a className={'btn ' + (i === 0 ? 'btn-gold' : 'btn-navy')} href={ERP_LOGIN}>{r.button}<ArrowRight /></a>
      </div>)}</div>
      <div className="qt-app"><a className="btn btn-navy" href={(import.meta.env.BASE_URL || '/') + 'downloads/jewelsetu-qtrack.apk'}><Smartphone />Download Q-Track Android app</a><span>For field auditors, vendors &amp; clients · Android 7+</span></div>
      <div className="qt-foot"><span><ShieldCheck />{Q.footnote}</span>
        <span>New buyer or vendor? <button onClick={() => openBook({ message: 'Please set up Q-Track portal access for our company.' })}>Request Q-Track onboarding →</button></span></div>
    </div>
  </section>;
}
