import { useState } from 'react';
import { Link } from 'react-router-dom';
import { CalendarPlus, Check, FileText, Factory, GraduationCap, Layers, ScanSearch } from 'lucide-react';
import { useSite, T } from '../site';
import { Diamond, Icon } from '../icons';
import { CtaBand, useHashScroll } from '../components';

export default function Services() {
  const { C, openBook, setReport } = useSite(); const S = C.services;
  const [gate, setGate] = useState(Number(S.gate_active) || 0);
  useHashScroll();
  const svcKey = (i) => C.booking.services[i]?.key;
  const tabs = [['tpq', 'TPQ Inspections', ScanSearch], ['audits', 'Factory & Compliance Audits', Factory], ['qms', 'Lean QMS Retainers', Layers], ['training', 'Technical Training', GraduationCap]];
  const [tab, setTab] = useState('tpq');
  const g = S.gates[gate] || S.gates[0];
  const max = Math.max(...S.scorecard.map((r) => Number(r.score) || 0));
  return <>
    <section className="phero">
      <Diamond className="dia-bg" stroke="#d9c38a" w={0.8} />
      <div className="wrap" style={{ position: 'relative' }}>
        <div className="crumb"><Link to="/">Home</Link> / <span>Quality Services</span></div>
        <T as="h1" text={S.title} />
        <div className="chips">{tabs.map(([id, l, I]) => <a key={id} href={'#' + id} className={'chip' + (tab === id ? ' on' : '')} onClick={() => setTab(id)}><I />{l}</a>)}</div>
      </div>
    </section>

    <section className="sec" id="tpq" style={{ paddingTop: 50 }}>
      <div className="wrap svc-grid">
        <div>
          <span className="eyebrow">Service 01</span>
          <T as="h2" className="svc-title" text={S.tpq_title} />
          <p className="svc-text">{S.tpq_text}</p>
          <div className="tpq-cards">{S.tpq_cards.map((c, i) => <div key={i} className="tcard"><span className="code">{c.code}</span><Icon name={c.icon} /><h3>{c.title}</h3><p>{c.text}</p></div>)}</div>
        </div>
        <div className="navy-card">
          <span className="eyebrow">What you receive</span>
          <ul>{S.deliverables.map((d, i) => <li key={i}><Icon name={d.icon} /><div><b>{d.title}</b><small>{d.text}</small></div></li>)}</ul>
          <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
            <button className="btn btn-gold btn-sm" onClick={() => openBook({ service: svcKey(0) })}><CalendarPlus />Request TPQ Quote</button>
            <button className="btn btn-wline btn-sm" onClick={() => setReport(true)}><FileText />Sample Report</button>
          </div>
        </div>
      </div>
    </section>

    <section className="sec white" id="audits">
      <div className="wrap two">
        <div>
          <span className="eyebrow">Service 02</span>
          <T as="h2" className="svc-title" text={S.audit_title} />
          <ul className="checks2">{S.audit_points.map((p) => <li key={p}><Check />{p}</li>)}</ul>
          <button className="btn btn-navy" style={{ marginTop: 24 }} onClick={() => openBook({ service: svcKey(1) })}><CalendarPlus />Request an Audit</button>
        </div>
        <div className="score">
          <div className="t">Weighted Vendor Scorecard<span className="gr">{S.scorecard_grade}</span></div>
          {S.scorecard.map((r, i) => { const v = Number(r.score) || 0; return <div key={i} className={'srow' + (v < max * 0.8 ? ' low' : '')}>
            <span>{r.label} · {r.weight}</span><span className="tr"><i style={{ width: Math.min(100, v) + '%' }} /></span><b>{r.score}</b></div>; })}
        </div>
      </div>
    </section>

    <section className="sec cream" id="qms">
      <div className="wrap">
        <div className="sec-head" style={{ marginBottom: 0 }}><div><span className="eyebrow">{S.qms_eyebrow}</span><T as="h2" className="svc-title" text={S.qms_title} /></div>
          <p style={{ textAlign: 'right' }}>{S.qms_text}</p></div>
        <div className="gates">{S.gates.map((x, i) => <button key={i} className={'gate' + (i === gate ? ' on' : '')} onClick={() => setGate(i)} onMouseEnter={() => setGate(i)}>
          <div className="c"><Icon name={x.icon} /></div><small>GATE {String(i + 1).padStart(2, '0')}</small><b>{x.name}</b></button>)}</div>
        {g && <div className="gate-note"><Icon name={g.icon} /><span><b>Gate {String(gate + 1).padStart(2, '0')} · {g.name} checkpoint:</b>&nbsp; {g.note}</span></div>}
        <button className="btn btn-gline" style={{ marginTop: 22 }} onClick={() => openBook({ service: svcKey(2) })}><CalendarPlus />Discuss a QMS retainer</button>
      </div>
    </section>

    <section className="sec" id="training">
      <div className="wrap two" style={{ alignItems: 'start' }}>
        <div>
          <span className="eyebrow">Service 04</span>
          <T as="h2" className="svc-title" text={S.training_title} />
          <p className="svc-text">{S.training_text}</p>
          <button className="btn btn-navy" style={{ marginTop: 22 }} onClick={() => openBook({ service: svcKey(3) })}><CalendarPlus />Plan a workshop</button>
        </div>
        <div className="mini-cards">{S.training_cards.map((c, i) => <div key={i} className="mcard"><Icon name={c.icon} /><b>{c.title}</b><small>{c.text}</small></div>)}</div>
      </div>
      <div className="wrap" style={{ marginTop: 50 }}><CtaBand title={C.cta.services_title} text={C.cta.services_text} /></div>
    </section>
  </>;
}
