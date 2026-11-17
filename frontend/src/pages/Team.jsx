import { useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Page } from '../app/Layout';
import { useAuth } from '../app/auth';
import { post } from '../app/api';
import { Badge, Empty, Icon, Loader, Sel, useAction, useApi, useForm } from '../app/ui';
import { fdate, fdt, initials } from '../app/util';

const TABS = [['internal', 'All staff'], ['inspector', 'Inspectors'], ['qc_manager', 'Managers'], ['back_office', 'Back office'], ['portal', 'Client / vendor logins']];
const blank = { id: 0, role: 'inspector', name: '', emp_code: '', email: '', designation: '', client_id: '', vendor_id: '', phone: '', location: '', area: '', joined_on: '', status: 'ACTIVE', password: '' };

export default function Team() {
  const { me } = useAuth(); const canEdit = me.user.role === 'super_admin';
  const [sp, setSp] = useSearchParams(); const f = sp.get('f') || 'internal'; const id = sp.get('id'); const edit = sp.has('edit') || sp.has('new');
  const q = useApi('team', { f, id }); const opts = useApi('options'); const F = useForm(blank); const { run, busy } = useAction();
  const sel = q.data?.sel;
  useEffect(() => { if (sp.has('new')) F.setF({ ...blank, role: f === 'portal' ? 'client' : 'inspector' }); else if (sel) F.setF({ ...blank, ...Object.fromEntries(Object.entries(sel).map(([k, v]) => [k, v ?? ''])), password: '' }); /* eslint-disable-next-line */ }, [sel?.id, sp.has('new')]);
  const save = async (e) => { e.preventDefault(); const r = await run(() => post('team/save', F.f)); if (r) { setSp({ f, id: r.id }); q.reload(); } };
  const b = F.bind;
  return <Page title="Team Management" sub="Add team members with roles and areas; track completed jobs, report time, returns and approval rate"
    actions={canEdit && <button className="btn pri" onClick={() => setSp({ f, new: 1 })}><Icon n="plus" /> Add team member</button>}>
    <Loader q={q}>{(d) => <div className="grid g-73">
      <div className="card">
        <div className="tabs">{TABS.map(([k, l]) => <a key={k} href="#" className={f === k ? 'on' : ''} onClick={(e) => { e.preventDefault(); setSp({ f: k }); }}>{l}</a>)}</div>
        <div className="tbl-wrap"><table className="t"><thead><tr><th>Employee ID / login</th><th>Name</th><th>Role</th><th>Location / link</th><th>Last login</th><th>Status</th></tr></thead><tbody>
          {d.rows.map((u) => <tr key={u.id} className={'click ' + (sel?.id === u.id ? 'sel' : '')} onClick={() => setSp({ f, id: u.id })}><td className="mono">{u.emp_code || u.email}</td>
            <td><span className="avatar">{initials(u.name)}</span>{u.name}</td><td><Badge s={u.role} label={d.roles[u.role]} /></td><td>{u.ccode || u.vname || u.location}</td><td>{u.last_login ? fdt(u.last_login) : '—'}</td><td><Badge s={u.status} /></td></tr>)}
        </tbody></table></div>
        {!!d.perf.length && <><h2 className="mb mt">Team this month</h2><table className="t"><thead><tr><th>Inspector</th><th className="num">Done</th><th className="num">Avg time</th><th className="num">Return %</th><th className="num">1st-time approval</th></tr></thead><tbody>
          {d.perf.map((u) => <tr key={u.id}><td>{u.emp_code} {u.name.split(' ')[0]}</td><td className="num">{u.done}</td><td className="num">{u.avg.toFixed(1)} h</td><td className="num">{Math.round(u.ret)}%</td><td className="num">{Math.round(u.first)}%</td></tr>)}</tbody></table></>}
      </div>
      <div>
        {sel && !edit && sel.kpi && <div className="card"><div className="card-h"><h2>Inspector performance</h2><span>{sel.emp_code}</span></div>
          <div className="grid g3" style={{ gap: 8, textAlign: 'center' }}>{[['Assigned QC', sel.kpi.assigned], ['Completed', sel.kpi.done], ['Avg turnaround', sel.kpi.avg.toFixed(1) + ' h'], ['Return rate', Math.round(sel.kpi.ret) + '%'], ['First-time approval', Math.round(sel.kpi.first) + '%'], ['Pending', sel.kpi.pending]].map(([l, v]) =>
            <div key={l}><small>{l}</small><div className="big">{v}</div></div>)}</div></div>}
        {sel && !edit ? <div className="card"><div className="card-h"><h2>Profile — {sel.emp_code || sel.name}</h2>{canEdit && <a href="#" onClick={(e) => { e.preventDefault(); setSp({ f, id: sel.id, edit: 1 }); }}>Edit</a>}</div>
          <div className="kv"><div><small>Name</small><b>{sel.name}</b></div><div><small>Mobile</small><b>{sel.phone || '—'}</b></div><div><small>Email</small><b>{sel.email || '—'}</b></div>
            <div><small>Role</small><b>{d.roles[sel.role]}</b></div><div><small>Designation</small><b>{sel.designation || '—'}</b></div><div><small>Location</small><b>{sel.location || '—'}</b></div>
            <div><small>Joining date</small><b>{fdate(sel.joined_on)}</b></div><div><small>Assigned area</small><b>{sel.area || '—'}</b></div><div><small>Status</small><b><Badge s={sel.status} /></b></div></div></div>
          : canEdit && edit ? <div className="card"><h2 className="mb">{sp.has('new') ? 'Add team member / portal login' : 'Edit member'}</h2>
            <form onSubmit={save}>
              <div className="f"><label>Role</label><Sel value={F.f.role} onChange={(v) => F.set('role', v)} options={Object.entries(d.roles)} /></div>
              <div className="f"><label>Name</label><input type="text" required {...b('name')} /></div>
              <div className="fr f"><div><label>Employee ID / login code</label><input type="text" placeholder="JS-014 / VND-XYZ" {...b('emp_code')} /></div><div><label>Email</label><input type="email" {...b('email')} /></div></div>
              {F.f.role === 'client' && opts.data && <div className="f"><label>Client (for client login)</label><Sel value={F.f.client_id} onChange={(v) => F.set('client_id', v)} options={opts.data.clients.map((c) => [c.id, c.code])} blank="Select" /></div>}
              {F.f.role === 'vendor' && opts.data && <div className="f"><label>Vendor (for vendor login)</label><Sel value={F.f.vendor_id} onChange={(v) => F.set('vendor_id', v)} options={opts.data.vendors.map((c) => [c.id, c.name])} blank="Select" /></div>}
              <div className="f"><label>Designation (printed on reports for approvers)</label><input type="text" placeholder="Principal Consultant" {...b('designation')} /></div>
              <div className="fr f"><div><label>Mobile</label><input type="text" {...b('phone')} /></div><div><label>Location</label><input type="text" {...b('location')} /></div></div>
              <div className="fr f"><div><label>Assigned area</label><input type="text" {...b('area')} /></div><div><label>Joining date</label><input type="date" {...b('joined_on')} /></div></div>
              <div className="fr f"><div><label>Status</label><select {...b('status')}><option value="ACTIVE">Active</option><option value="INACTIVE">Inactive</option></select></div>
                <div><label>{sp.has('new') ? 'Password (blank = auto)' : 'Reset password'}</label><input type="text" {...b('password')} /></div></div>
              <button className="btn pri block" disabled={busy}>SAVE</button></form></div>
          : <div className="card"><Empty>Select a team member to see profile and performance.</Empty></div>}
      </div>
    </div>}</Loader>
  </Page>;
}
