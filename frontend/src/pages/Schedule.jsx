import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Page } from '../app/Layout';
import { useAuth } from '../app/auth';
import { post } from '../app/api';
import { Badge, Empty, Loader, Sel, useAction, useApi } from '../app/ui';
import { fdate } from '../app/util';

const shift = (d, n) => { const x = new Date(d); x.setDate(x.getDate() + n); return x.toISOString().slice(0, 10); };

export default function Schedule() {
  const { me, refresh } = useAuth(); const r = me.user.role; const internal = ['super_admin', 'back_office', 'qc_manager'].includes(r);
  const [sp, setSp] = useSearchParams(); const q = useApi('schedule', { w: sp.get('w'), client: sp.get('client') }); const opts = useApi('options');
  const { run } = useAction(); const [over, setOver] = useState('');
  if (!internal) return <Page title={r === 'vendor' ? 'Inspection schedule' : 'QC Schedule'} sub="Confirmed inspection dates and assigned inspectors">
    <Loader q={q}>{(d) => <div className="card"><h2 className="mb">Upcoming inspections</h2>
      {!d.upcoming.length ? <Empty>No upcoming inspections.</Empty> : <div className="tbl-wrap"><table className="t"><thead><tr><th>Request</th><th>{r === 'vendor' ? 'Client' : 'Vendor'}</th><th>PO</th><th>Date</th><th>Time</th><th>Inspector</th><th>Location</th><th>Status</th></tr></thead><tbody>
        {d.upcoming.map((j) => <tr key={j.id}><td><Link className="strong" to={'/request/' + j.id}>{j.req_no}</Link></td><td>{r === 'vendor' ? j.client_code : j.vendor_name}</td><td className="mono">{j.po_no}</td>
          <td>{j.scheduled_date ? fdate(j.scheduled_date) : <>{fdate(j.requested_date)} <small>(requested)</small></>}</td><td>{j.time_slot || '—'}</td>
          <td>{j.insp_code ? <>{j.insp_code} · {j.insp_name}{r === 'vendor' && j.insp_phone && <><br /><small>{j.insp_phone}</small></>}</> : '—'}</td><td>{j.location}</td><td><Badge s={j.status} /></td></tr>)}</tbody></table></div>}
      {r === 'vendor' && <p className="note mt">Gate-entry details are sent on assignment. Please keep the ready quantity, spec sheets and approved sample available on the inspection date.</p>}
    </div>}</Loader></Page>;

  const drop = async (jobId, insp, date) => { setOver(''); const x = await run(() => post('job/action', { act: 'assign', id: jobId, inspector_id: insp, date, notify_vendor: 1 })); if (x) { q.reload(); refresh(); } };
  return <Loader q={q}>{(d) => { const grid = {}; const un = {};
    d.jobs.forEach((j) => { if (j.inspector_id) ((grid[j.inspector_id] ||= {})[j.d] ||= []).push(j); else (un[j.d] ||= []).push(j); });
    const nav = <div className="actions"><button className="btn sm" onClick={() => setSp({ w: shift(d.start, -7), client: sp.get('client') || '' })}>‹</button>
      <b style={{ padding: '0 6px' }}>{fdate(d.days[0], 'd M')} – {fdate(d.days[6])}</b><button className="btn sm" onClick={() => setSp({ w: shift(d.start, 7), client: sp.get('client') || '' })}>›</button>
      <button className="btn sm" onClick={() => setSp({})}>Today</button></div>;
    const chip = (j) => { const cls = !j.inspector_id ? 'un' : ['SUBMITTED', 'UNDER_REVIEW', 'APPROVED', 'EMAILED', 'CLOSED'].includes(j.status) ? 'done' : '';
      const drag = d.can_drag && ['REQUESTED', 'SCHEDULED', 'ASSIGNED'].includes(j.status);
      return <Link key={j.id} className={'chipjob ' + cls} draggable={drag} onDragStart={(e) => e.dataTransfer.setData('text', String(j.id))} to={'/request/' + j.id}>
        <b>{j.job_no || j.req_no}</b><small>{j.vendor_name} ({j.client_code}) · {j.skus} SKU{j.time_slot ? ' · ' + j.time_slot : ''}</small></Link>; };
    const slot = (insp, day, list) => <div key={day} className={'slot' + (over === insp + day ? ' over' : '')}
      onDragOver={insp && d.can_drag ? (e) => { e.preventDefault(); setOver(insp + day); } : undefined} onDragLeave={() => setOver('')}
      onDrop={insp && d.can_drag ? (e) => { e.preventDefault(); drop(e.dataTransfer.getData('text'), insp, day); } : undefined}>{(list || []).map(chip)}</div>;
    return <Page title="QC Schedule" sub={'Every inspector’s QC jobs by day; unassigned jobs highlighted' + (d.can_drag ? ' and assigned by drag & drop' : '')}
      actions={<>{opts.data && <Sel className="w-auto" value={sp.get('client') || ''} onChange={(v) => setSp({ w: d.start, client: v })} options={opts.data.clients.map((c) => [c.id, c.code])} blank="Client: All" />}{nav}</>}>
      <div className="week">
        <div className="wh" />{d.days.map((x) => <div key={x} className={'wh' + (x === d.today ? ' today' : '')}>{fdate(x, 'D d')}</div>)}
        {d.inspectors.map((u) => [<div key={'i' + u.id} className="wi"><span className="avatar">{u.name[0]}</span><span>{u.name.split(' ')[0]}<br /><small>{u.emp_code} · {u.location}</small></span></div>,
          ...d.days.map((x) => slot(u.id, x, grid[u.id]?.[x]))])}
        <div className="wi" style={{ color: 'var(--ruby)' }}><span className="avatar" style={{ background: 'var(--ruby-l)', color: 'var(--ruby)' }}>!</span>Unassigned</div>
        {d.days.map((x) => <div key={'u' + x} className="slot" style={{ background: '#f8fafb' }}>{(un[x] || []).map(chip)}</div>)}
      </div>
      {d.can_drag && <p className="muted" style={{ fontSize: 12.5 }}>Drag a job from “Unassigned” (or another inspector) onto an inspector row &amp; day to assign it — notification goes out automatically.</p>}
    </Page>; }}</Loader>;
}
