import { useState } from 'react';
import { Page } from '../app/Layout';
import { apiUrl, post } from '../app/api';
import { Empty, Loader, Modal, Sel, useAction, useApi, Paged } from '../app/ui';
import { fdate } from '../app/util';

const hm = (m) => `${Math.floor(m / 60)}h ${String(m % 60).padStart(2, '0')}m`;
const hrs = (m) => `${Math.floor(m / 60)} Hrs ${String(m % 60).padStart(2, '0')} Mins`;
const tm = (s) => (s ? new Date(s.replace(' ', 'T')).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '—');
const ST = { PRESENT: 'b-pass', 'ON LEAVE': 'b-conditional', ABSENT: 'b-fail', 'WEEK OFF': '' };

/** 🕒 Attendance & Visit Log — monthly, from factory check-in / check-out sessions. */
export default function Attendance() {
  const now = new Date(); const [month, setMonth] = useState(`${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`);
  const [user, setUser] = useState(''); const q = useApi('attendance', { month, user }); const [fix, setFix] = useState(null); const [rules, setRules] = useState(null);
  const { run, busy } = useAction();
  return <Page title="🕒 Attendance & Visit Log" sub="Factory check-in / check-out sessions — working hours, vendor visits, missed check-outs and late arrivals"
    actions={<a className="btn gold" href={apiUrl('attendance/export', { month, user })}>⬇ Export monthly attendance &amp; visit report (.xlsx)</a>}>
    <Loader q={q}>{(d) => { const T = user ? d.summary[0] || d.totals : d.totals; return <>
      <div className="card"><div className="filters" style={{ margin: 0, alignItems: 'flex-end' }}>
        <div><label>Month</label><input type="month" value={month} onChange={(e) => e.target.value && setMonth(e.target.value)} /></div>
        <div><label>Inspector</label><Sel value={user} onChange={setUser} options={d.users.map((u) => [u.id, u.label])} blank="All inspectors" /></div>
        <p className="muted" style={{ margin: 0, fontSize: 12, flex: 1 }}>Late arrival = first check-in after <b>{d.late_after}</b> · check-out reminder at <b>{d.remind_at}</b>; a session still open next day is flagged <b>MISSED CHECK-OUT</b>.
          {d.can_fix && <> <a href="#" onClick={(e) => { e.preventDefault(); setRules({ att_late_after: d.late_after, att_remind_at: d.remind_at }); }}>Change</a></>}</p></div></div>
      <div className="kpis" style={{ display: 'grid', gridTemplateColumns: 'repeat(5,minmax(0,1fr))', gap: 12, marginBottom: 14 }}>
        {[['Total present days', T.present + ' Days', 'ok'], ['Absent / leave days', `${String(T.absent).padStart(2, '0')} / ${String(T.leave).padStart(2, '0')} Days`, ''], ['Total working hours', hrs(T.min), 'gold'],
          ['Total vendor visits', T.visits + ' Visits', ''], ['Missed check-outs / late', `${String(T.missed).padStart(2, '0')} / ${String(T.late).padStart(2, '0')} Flags`, T.missed || T.late ? 'bad' : '']].map(([l, v, tone]) =>
          <div key={l} className={'card attk ' + tone} style={{ margin: 0 }}><small>{l}</small><b>{v}</b></div>)}</div>
      {!user && <div className="card"><h2 className="mb">Team summary · {fdate(d.from, 'd M')} – {fdate(d.to)}</h2><div className="tbl-wrap"><table className="t"><thead><tr><th>Inspector</th><th className="num">Present</th><th className="num">Leave</th><th className="num">Absent</th><th className="num">Working hours</th><th className="num">Avg / day</th><th className="num">Visits</th><th className="num">Designs / pcs</th><th className="num">Missed out</th><th className="num">Late</th></tr></thead><tbody>
        {d.summary.map((s) => <tr key={s.uid} className="rowlink" onClick={() => setUser(String(s.uid))}><td><b>{s.code}</b> · {s.name}</td><td className="num">{s.present}</td><td className="num">{s.leave}</td><td className="num">{s.absent}</td><td className="num strong">{hrs(s.min)}</td><td className="num">{hm(s.avg)}</td><td className="num">{s.visits}</td><td className="num">{s.designs} / {s.pcs}</td>
          <td className="num" style={s.missed ? { color: 'var(--ruby)', fontWeight: 700 } : {}}>{s.missed}</td><td className="num" style={s.late ? { color: '#b4561b', fontWeight: 700 } : {}}>{s.late}</td></tr>)}</tbody></table></div></div>}
      <div className="card"><h2 className="mb">Daily attendance &amp; visit log{user ? ' · ' + (d.users.find((u) => String(u.id) === String(user))?.label || '') : ''}</h2>
        {!d.rows.length ? <Empty>No check-ins this month.</Empty> : <Paged rows={d.rows} per={25} label="days">{(pr) => <div className="tbl-wrap"><table className="t att"><thead><tr><th>Date</th><th>Day</th><th>Inspector</th><th>Vendor visited (client)</th><th>Check-in time &amp; GPS</th><th>Check-out time &amp; GPS</th><th>Visit duration</th><th>Daily working hrs</th><th>Designs / pcs</th><th>Status</th></tr></thead><tbody>
          {pr.map((r) => <tr key={r.date + r.uid}>
            <td className="nowrap">{fdate(r.date, 'd-m-Y')}</td><td>{r.day}</td><td className="nowrap">{r.insp}</td>
            <td>{r.sessions.length ? r.sessions.map((s, i) => <div key={s.id}>{r.sessions.length > 1 ? `${i + 1}. ` : ''}<b>{s.vendor}</b>{s.client ? ` (${s.client})` : ''}</div>) : '—'}</td>
            <td>{r.sessions.length ? r.sessions.map((s) => <div key={s.id} className="nowrap">{tm(s.in)} <span className={s.in_ok ? 'gok' : 'gpend'}>{s.in_ok ? '(Verified ✓)' : '(pin pending)'}</span>{s.lat && <a className="gmap" target="_blank" rel="noreferrer" href={`https://maps.google.com/?q=${s.lat},${s.lng}`} title="Open GPS on map">📍</a>}</div>) : '—'}</td>
            <td>{r.sessions.length ? r.sessions.map((s) => <div key={s.id} className="nowrap">{s.out ? <>{tm(s.out)} <span className={s.out_ok === false ? 'gpend' : 'gok'}>{s.fixed ? '(set by manager)' : s.out_ok === true ? '(Verified ✓)' : s.out_ok === false ? '(outside fence)' : s.out_gps ? '(GPS ✓)' : ''}</span></>
              : s.live ? <span className="gok">● on-site now</span> : <span className="missed">MISSED CHECK-OUT</span>}
              {d.can_fix && !s.live && (!s.out || s.missed) && <button className="btn sm" style={{ marginLeft: 6, padding: '1px 7px' }} onClick={() => setFix({ ...s, date: r.date, insp: r.insp, time: s.out ? s.out.slice(11, 16) : '' })}>{s.out ? 'Edit' : 'Verify / set'}</button>}
              {s.fix_note && <small className="muted" style={{ display: 'block' }}>✎ {s.fixer}: {s.fix_note}</small>}</div>) : '—'}</td>
            <td>{r.sessions.length ? r.sessions.map((s) => <div key={s.id}>{s.out || s.live ? hm(s.min) : '—'}</div>) : '—'}</td>
            <td className="strong nowrap">{hrs(r.min)}</td><td className="nowrap">{r.designs ? `${String(r.designs).padStart(2, '0')} SKUs / ${r.pcs} pcs` : '—'}</td>
            <td><span className={'badge ' + (ST[r.status] || '')}>{r.status}</span>{r.leave && <small className="muted" style={{ display: 'block' }}>{r.leave}</small>}{r.flags.map((f) => <span key={f} className={'badge ' + (f.startsWith('MISSED') && !f.includes('fixed') ? 'b-fail' : 'b-pending')} style={{ display: 'inline-block', marginTop: 3 }}>{f}</span>)}</td></tr>)}</tbody></table></div>}</Paged>}</div>
      {d.register.length > 0 && <div className="card"><h2 className="mb">Vendor-wise visit register</h2><table className="t"><thead><tr><th>Vendor / factory</th><th className="num">Visits</th><th className="num">Hours at factory</th><th className="num">Pcs checked</th><th>Visit dates &amp; inspectors</th></tr></thead><tbody>
        {d.register.map((g) => <tr key={g.vendor}><td><b>{g.vendor}</b></td><td className="num">{g.visits}</td><td className="num">{hrs(g.min)}</td><td className="num">{g.pcs}</td><td style={{ fontSize: 12, whiteSpace: 'normal', maxWidth: 560 }}>{g.list.map((x, i) => <span key={i} style={{ display: 'inline-block', marginRight: 4 }}>{fdate(x.date, 'd M')} · {x.insp.split(' · ')[0]} ({hm(x.min)}){i < g.list.length - 1 ? ', ' : ''}</span>)}</td></tr>)}</tbody></table></div>}
      {fix && <Modal open title={`Verify check-out · ${fix.insp} · ${fdate(fix.date, 'd M Y')}`} onClose={() => setFix(null)}>
        <p className="muted" style={{ marginTop: 0, fontSize: 12.5 }}>{fix.vendor} · checked in {tm(fix.in)}. Set the real check-out time after verifying with the inspector / vendor gate register. The reason is saved on the record and in the audit log.</p>
        <div className="fr f"><div><label>Check-out time</label><input type="time" value={fix.time} onChange={(e) => setFix({ ...fix, time: e.target.value })} /></div></div>
        <div className="f"><label>Reason / verification (required)</label><input type="text" value={fix.reason || ''} placeholder="e.g. Forgot to check out — confirmed with vendor gate register 6:30 PM" onChange={(e) => setFix({ ...fix, reason: e.target.value })} /></div>
        <div className="actions"><button className="btn" onClick={() => setFix(null)}>Cancel</button><button className="btn pri" disabled={busy || !fix.time || (fix.reason || '').trim().length < 3} onClick={async () => { if (await run(() => post('attendance/fix', { id: fix.id, out_time: fix.time, reason: fix.reason }))) { setFix(null); q.reload(); } }}>Save check-out</button></div></Modal>}
      {rules && <Modal open title="Attendance rules" onClose={() => setRules(null)}>
        <div className="fr f"><div><label>Late arrival after</label><input type="time" value={rules.att_late_after} onChange={(e) => setRules({ ...rules, att_late_after: e.target.value })} /></div>
          <div><label>Check-out reminder at</label><input type="time" value={rules.att_remind_at} onChange={(e) => setRules({ ...rules, att_remind_at: e.target.value })} /></div></div>
        <div className="actions"><button className="btn" onClick={() => setRules(null)}>Cancel</button><button className="btn pri" disabled={busy} onClick={async () => { if (await run(() => post('attendance/settings', rules))) { setRules(null); q.reload(); } }}>Save</button></div></Modal>}
    </>; }}</Loader>
  </Page>;
}
