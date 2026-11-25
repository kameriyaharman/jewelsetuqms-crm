import { useState } from 'react';
import { Page } from '../app/Layout';
import { apiUrl, post } from '../app/api';
import { Empty, Loader, Modal, Sel, useAction, useApi, Paged } from '../app/ui';
import { fdate } from '../app/util';

const hm = (m) => `${Math.floor(m / 60)}h ${String(m % 60).padStart(2, '0')}m`;
const hrs = (m) => `${Math.floor(m / 60)} Hrs ${String(m % 60).padStart(2, '0')} Mins`;
const tm = (s) => (s ? new Date(s.replace(' ', 'T')).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '—');
const ST = { PRESENT: 'b-pass', 'HALF DAY': 'b-pending', 'SHORT DAY': 'b-fail', 'ON LEAVE': 'b-conditional', ABSENT: 'b-fail', 'WEEK OFF': '' };
const WD = [['-1', 'No weekly off'], ['0', 'Sunday'], ['1', 'Monday'], ['2', 'Tuesday'], ['3', 'Wednesday'], ['4', 'Thursday'], ['5', 'Friday'], ['6', 'Saturday']];
const MC = { P: 'mp', HD: 'mh', SD: 'ms', L: 'ml', WO: 'mw', A: 'ma' };

/** 🕒 Attendance & Visit Log — monthly, from factory check-in / check-out sessions. */
export default function Attendance() {
  const now = new Date(); const [month, setMonth] = useState(`${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`);
  const [user, setUser] = useState(''); const q = useApi('attendance', { month, user }); const [fix, setFix] = useState(null); const [rules, setRules] = useState(null);
  const { run, busy } = useAction();
  const [tab, setTab] = useState('score');
  return <Page title="🕒 HR Attendance & Payroll" sub="Field check-in / check-out sessions → net shift hours (first in to last out), on-site and travel time, monthly scorecard, muster roll and payroll export"
    actions={<a className="btn gold" href={apiUrl('attendance/export', { month, user })}>⬇ 1-click payroll Excel export</a>}>
    <Loader q={q}>{(d) => { const T = user ? d.summary[0] || d.totals : d.totals; return <>
      <div className="card"><div className="filters" style={{ margin: 0, alignItems: 'flex-end' }}>
        <div><label>Month</label><input type="month" value={month} onChange={(e) => e.target.value && setMonth(e.target.value)} /></div>
        <div><label>Inspector</label><Sel value={user} onChange={setUser} options={d.users.map((u) => [u.id, u.label])} blank="All inspectors" /></div>
        <p className="muted" style={{ margin: 0, fontSize: 12, flex: 1 }}>Full day ≥ <b>{d.rules.att_full_hrs} h</b> net shift · half day ≥ <b>{d.rules.att_half_hrs} h</b> · late after <b>{d.late_after}</b>{+d.rules.att_hd_on_late ? <> (after {d.rules.att_late_grace} lates → half day)</> : null} · weekly off <b>{(WD.find((w) => w[0] === String(d.rules.att_weekoff)) || WD[1])[1]}</b> · check-out reminder <b>{d.remind_at}</b>.
          {d.can_fix && <> <a href="#" onClick={(e) => { e.preventDefault(); setRules({ ...d.rules }); }}>Change rules</a></>}</p></div></div>
      <div className="kpis" style={{ display: 'grid', gridTemplateColumns: 'repeat(6,minmax(0,1fr))', gap: 12, marginBottom: 14 }}>
        {[['Full / half days', `${T.present} / ${T.half}`, 'ok'], ['Absent / leave', `${T.absent} / ${T.leave}`, ''], ['Payable days', String(T.payable), 'gold'], ['Net shift hours', hrs(T.net), ''],
          ['On-site / travel', `${hm(T.min)} / ${hm(T.transit)}`, ''], ['Missed out / late', `${T.missed} / ${T.late}`, T.missed || T.late ? 'bad' : '']].map(([l, v, tone]) =>
          <div key={l} className={'card attk ' + tone} style={{ margin: 0 }}><small>{l}</small><b>{v}</b></div>)}</div>
      <div className="tabs" style={{ marginBottom: 12 }}>{[['score', 'Monthly scorecard'], ['muster', 'Day-by-day muster roll'], ['log', 'Daily log'], ['vendor', 'Vendor visits']].map(([k, l]) => <a key={k} href="#" className={tab === k ? 'on' : ''} onClick={(e) => { e.preventDefault(); setTab(k); }}>{l}</a>)}</div>
      {tab === 'score' && <div className="card"><h2 className="mb">Monthly scorecard · {fdate(d.from, 'd M')} – {fdate(d.to)}</h2><div className="tbl-wrap"><table className="t"><thead><tr><th>Inspector</th><th className="num">Full (P)</th><th className="num">Half (HD)</th><th className="num">Short</th><th className="num">Leave</th><th className="num">Week off</th><th className="num">Absent</th><th className="num">Payable days</th><th className="num">Net shift hrs</th><th className="num">On-site</th><th className="num">Travel</th><th className="num">Avg / day</th><th className="num">Visits</th><th className="num">Late</th><th className="num">Missed out</th></tr></thead><tbody>
        {d.summary.map((s) => <tr key={s.uid} className="rowlink" onClick={() => { setUser(String(s.uid)); setTab('log'); }}><td><b>{s.code}</b> · {s.name}</td><td className="num">{s.present}</td><td className="num">{s.half}</td><td className="num">{s.short}</td><td className="num">{s.leave}</td><td className="num">{s.weekoff}</td><td className="num">{s.absent}</td><td className="num strong">{s.payable}</td>
          <td className="num strong">{hrs(s.net)}</td><td className="num">{hm(s.min)}</td><td className="num">{hm(s.transit)}</td><td className="num">{hm(s.avg)}</td><td className="num">{s.visits}</td>
          <td className="num" style={s.late ? { color: '#b4561b', fontWeight: 700 } : {}}>{s.late}</td><td className="num" style={s.missed ? { color: 'var(--ruby)', fontWeight: 700 } : {}}>{s.missed}</td></tr>)}</tbody></table></div></div>}
      {tab === 'muster' && <div className="card"><h2 className="mb">HR muster roll · {new Date(d.from + 'T00:00').toLocaleDateString('en-IN', { month: 'long', year: 'numeric' })}</h2><p className="muted" style={{ fontSize: 12, marginTop: -6 }}>P full day · HD half day · SD short day · L leave · WO week off · A absent</p>
        <div className="tbl-wrap"><table className="t muster"><thead><tr><th>Inspector</th>{Array.from({ length: d.days }, (_, i) => { const dt = new Date(d.from); dt.setDate(i + 1); return <th key={i} className="c">{i + 1}<small>{'SMTWTFS'[dt.getDay()]}</small></th>; })}<th className="num">P</th><th className="num">HD</th><th className="num">A</th><th className="num">Pay</th></tr></thead><tbody>
          {d.summary.map((s) => <tr key={s.uid}><td className="nowrap"><b>{s.code}</b> · {s.name.split(' ')[0]}</td>{Array.from({ length: d.days }, (_, i) => { const k = `${d.month}-${String(i + 1).padStart(2, '0')}`; const c = (d.muster[s.uid] || {})[k] || ''; return <td key={i} className={'c ' + (MC[c] || '')}>{c}</td>; })}
            <td className="num">{s.present}</td><td className="num">{s.half}</td><td className="num">{s.absent}</td><td className="num strong">{s.payable}</td></tr>)}</tbody></table></div></div>}
      {tab === 'log' && <div className="card"><h2 className="mb">Daily attendance &amp; visit log{user ? ' · ' + (d.users.find((u) => String(u.id) === String(user))?.label || '') : ''}</h2>
        {!d.rows.length ? <Empty>No check-ins this month.</Empty> : <Paged rows={d.rows} per={25} label="days">{(pr) => <div className="tbl-wrap"><table className="t att"><thead><tr><th>Date</th><th>Day</th><th>Inspector</th><th>First in → last out</th><th>Vendor / place visited</th><th>Check-in time &amp; GPS</th><th>Check-out time &amp; GPS</th><th>Visit duration</th><th>On-site hrs</th><th>Designs / pcs</th><th>Status</th></tr></thead><tbody>
          {pr.map((r) => <tr key={r.date + r.uid}>
            <td className="nowrap">{fdate(r.date, 'd-m-Y')}</td><td>{r.day}</td><td className="nowrap">{r.insp}</td>
            <td className="nowrap">{r.first_in ? <>{tm(r.first_in)} → {r.last_out ? tm(r.last_out) : '…'}<small className="muted" style={{ display: 'block' }}>net {hm(r.net)}{r.transit ? ` · travel ${hm(r.transit)}` : ''}</small></> : '—'}</td>
            <td>{r.sessions.length ? r.sessions.map((s, i) => <div key={s.id}>{r.sessions.length > 1 ? `${i + 1}. ` : ''}<b>{s.vendor}</b>{s.client ? ` (${s.client})` : ''}{s.purpose ? <small className="muted"> · {s.purpose}</small> : null}{s.address && <small className="muted" style={{ display: 'block', maxWidth: 260, whiteSpace: 'normal' }}>{s.address}</small>}</div>) : '—'}</td>
            <td>{r.sessions.length ? r.sessions.map((s) => <div key={s.id} className="nowrap">{tm(s.in)} <span className={s.in_ok || s.manual ? 'gok' : 'gpend'}>{s.in_ok ? '(Verified ✓)' : s.manual ? '(GPS ✓)' : '(pin pending)'}</span>{s.lat && <a className="gmap" target="_blank" rel="noreferrer" href={`https://maps.google.com/?q=${s.lat},${s.lng}`} title="Open GPS on map">📍</a>}</div>) : '—'}</td>
            <td>{r.sessions.length ? r.sessions.map((s) => <div key={s.id} className="nowrap">{s.out ? <>{tm(s.out)} <span className={s.out_ok === false ? 'gpend' : 'gok'}>{s.fixed ? '(set by manager)' : s.out_ok === true ? '(Verified ✓)' : s.out_ok === false ? '(outside fence)' : s.out_gps ? '(GPS ✓)' : ''}</span></>
              : s.live ? <span className="gok">● on-site now</span> : <span className="missed">MISSED CHECK-OUT</span>}
              {d.can_fix && !s.live && (!s.out || s.missed) && <button className="btn sm" style={{ marginLeft: 6, padding: '1px 7px' }} onClick={() => setFix({ ...s, date: r.date, insp: r.insp, time: s.out ? s.out.slice(11, 16) : '' })}>{s.out ? 'Edit' : 'Verify / set'}</button>}
              {s.fix_note && <small className="muted" style={{ display: 'block' }}>✎ {s.fixer}: {s.fix_note}</small>}</div>) : '—'}</td>
            <td>{r.sessions.length ? r.sessions.map((s) => <div key={s.id}>{s.out || s.live ? hm(s.min) : '—'}</div>) : '—'}</td>
            <td className="strong nowrap">{hrs(r.min)}</td><td className="nowrap">{r.designs ? `${String(r.designs).padStart(2, '0')} SKUs / ${r.pcs} pcs` : '—'}</td>
            <td><span className={'badge ' + (ST[r.status] || '')}>{r.status}</span>{r.leave && <small className="muted" style={{ display: 'block' }}>{r.leave}</small>}{r.flags.map((f) => <span key={f} className={'badge ' + (f.startsWith('MISSED') && !f.includes('fixed') ? 'b-fail' : 'b-pending')} style={{ display: 'inline-block', marginTop: 3 }}>{f}</span>)}</td></tr>)}</tbody></table></div>}</Paged>}</div>}
      {tab === 'vendor' && d.register.length > 0 && <div className="card"><h2 className="mb">Vendor-wise visit register</h2><table className="t"><thead><tr><th>Vendor / factory</th><th className="num">Visits</th><th className="num">Hours at factory</th><th className="num">Pcs checked</th><th>Visit dates &amp; inspectors</th></tr></thead><tbody>
        {d.register.map((g) => <tr key={g.vendor}><td><b>{g.vendor}</b></td><td className="num">{g.visits}</td><td className="num">{hrs(g.min)}</td><td className="num">{g.pcs}</td><td style={{ fontSize: 12, whiteSpace: 'normal', maxWidth: 560 }}>{g.list.map((x, i) => <span key={i} style={{ display: 'inline-block', marginRight: 4 }}>{fdate(x.date, 'd M')} · {x.insp.split(' · ')[0]} ({hm(x.min)}){i < g.list.length - 1 ? ', ' : ''}</span>)}</td></tr>)}</tbody></table></div>}
      {fix && <Modal open title={`Verify check-out · ${fix.insp} · ${fdate(fix.date, 'd M Y')}`} onClose={() => setFix(null)}>
        <p className="muted" style={{ marginTop: 0, fontSize: 12.5 }}>{fix.vendor} · checked in {tm(fix.in)}. Set the real check-out time after verifying with the inspector / vendor gate register. The reason is saved on the record and in the audit log.</p>
        <div className="fr f"><div><label>Check-out time</label><input type="time" value={fix.time} onChange={(e) => setFix({ ...fix, time: e.target.value })} /></div></div>
        <div className="f"><label>Reason / verification (required)</label><input type="text" value={fix.reason || ''} placeholder="e.g. Forgot to check out — confirmed with vendor gate register 6:30 PM" onChange={(e) => setFix({ ...fix, reason: e.target.value })} /></div>
        <div className="actions"><button className="btn" onClick={() => setFix(null)}>Cancel</button><button className="btn pri" disabled={busy || !fix.time || (fix.reason || '').trim().length < 3} onClick={async () => { if (await run(() => post('attendance/fix', { id: fix.id, out_time: fix.time, reason: fix.reason }))) { setFix(null); q.reload(); } }}>Save check-out</button></div></Modal>}
      {rules && <Modal open title="HR attendance rules" onClose={() => setRules(null)}>
        <div className="fr f"><div><label>Full day = net shift hours ≥</label><input type="number" step="0.5" min="0" max="16" value={rules.att_full_hrs} onChange={(e) => setRules({ ...rules, att_full_hrs: e.target.value })} /></div>
          <div><label>Half day = net shift hours ≥</label><input type="number" step="0.5" min="0" max="16" value={rules.att_half_hrs} onChange={(e) => setRules({ ...rules, att_half_hrs: e.target.value })} /></div></div>
        <div className="fr f"><div><label>Late arrival after</label><input type="time" value={rules.att_late_after} onChange={(e) => setRules({ ...rules, att_late_after: e.target.value })} /></div>
          <div><label>Check-out reminder at</label><input type="time" value={rules.att_remind_at} onChange={(e) => setRules({ ...rules, att_remind_at: e.target.value })} /></div></div>
        <div className="fr f"><div><label>Weekly off</label><select value={String(rules.att_weekoff)} onChange={(e) => setRules({ ...rules, att_weekoff: e.target.value })}>{WD.map(([v, l]) => <option key={v} value={v}>{l}</option>)}</select></div>
          <div><label>Late arrivals allowed per month</label><input type="number" min="0" max="31" value={rules.att_late_grace} onChange={(e) => setRules({ ...rules, att_late_grace: e.target.value })} /></div></div>
        <label className="chk"><input type="checkbox" checked={+rules.att_hd_on_late === 1} onChange={(e) => setRules({ ...rules, att_hd_on_late: e.target.checked ? 1 : 0 })} /> Each late arrival beyond the allowed count = half day</label>
        <label className="chk"><input type="checkbox" checked={+rules.att_leave_paid === 1} onChange={(e) => setRules({ ...rules, att_leave_paid: e.target.checked ? 1 : 0 })} /> Approved leave counts as payable day</label>
        <p className="muted" style={{ fontSize: 12 }}>Net shift = first check-in to last check-out of the day. Below half-day hours = short day (not payable). Set full-day hours to 0 to count every day with a check-in as a full day.</p>
        <div className="actions"><button className="btn" onClick={() => setRules(null)}>Cancel</button><button className="btn pri" disabled={busy} onClick={async () => { if (await run(() => post('attendance/settings', rules))) { setRules(null); q.reload(); } }}>Save rules</button></div></Modal>}
    </>; }}</Loader>
  </Page>;
}
