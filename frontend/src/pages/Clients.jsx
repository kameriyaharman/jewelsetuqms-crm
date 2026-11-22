import { ManualsPanel } from './Manuals';
import { useEffect } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Page } from '../app/Layout';
import { post } from '../app/api';
import { Badge, Empty, Icon, Loader, Multi, useAction, useApi, useForm, Paged } from '../app/ui';

const blank = { id: 0, code: '', name: '', contact: '', email: '', phone: '', ho_email: '', address: '', report_format: 'Standard', aql_default: '2.5', fail_threshold: 80, status: 'ACTIVE', vendor_ids: [], create_login: false, login_pw: '' };

export default function Clients() {
  const [sp, setSp] = useSearchParams(); const id = sp.get('id'); const isNew = sp.has('new'); const qs = sp.get('q') || '';
  const q = useApi('clients', { id, q: qs });
  const F = useForm(blank); const { run, busy } = useAction();
  const sel = q.data?.sel;
  useEffect(() => { if (isNew) F.setF(blank); else if (sel) F.setF({ ...blank, ...sel, fail_threshold: Number(sel.fail_threshold || 80) }); /* eslint-disable-next-line */ }, [sel?.id, isNew]);
  const save = async (e) => { e.preventDefault(); const r = await run(() => post('clients/save', F.f)); if (r) { setSp({ id: r.id }); q.reload(); } };
  const f = F.f, b = F.bind;
  return <Page title="Client Management" actions={<button className="btn pri" onClick={() => setSp({ new: 1 })}><Icon n="plus" /> Add Client</button>}>
    <Loader q={q}>{(d) => <div className="grid g-82">
      <div>
        <div className="card">
          <form className="filters" onSubmit={(e) => { e.preventDefault(); setSp({ q: e.target.q.value }); }}><input type="search" name="q" defaultValue={qs} placeholder="Search client…" /><button className="btn sm">Search</button></form>
          <Paged rows={d.rows} per={25} label="clients">{(pr) => <div className="tbl-wrap"><table className="t"><thead><tr><th>Client</th><th>Company</th><th>Contact</th><th className="num">Mapped vendors</th><th className="num">Total QC</th><th>Portal login</th><th>Status</th></tr></thead><tbody>
            {pr.map((c) => <tr key={c.id} className={'click ' + (sel?.id === c.id ? 'sel' : '')} onClick={() => setSp({ id: c.id })}>
              <td><span className="avatar">{c.code.slice(0, 3)}</span><b>{c.code}</b></td><td>{c.name}</td><td>{c.contact}</td><td className="num">{c.nv}</td><td className="num">{c.nq}</td>
              <td>{+c.nl ? <Badge s="ACTIVE" /> : <span className="muted">—</span>}</td><td><Badge s={c.status} /></td></tr>)}
          </tbody></table></div>}</Paged>
        </div>
        {sel && !isNew && <div className="card"><div className="card-h"><h2>Client profile — {sel.code}</h2><Link to={'/requests?client=' + sel.id}>Open QC requests</Link></div>
          <div className="kv"><div><small>Head office email (auto-CC)</small><b>{sel.ho_email}</b></div><div><small>Report format</small><b>{sel.report_format}</b></div>
            <div><small>Pass / fail rule</small><b>Fail if ≥{Number(sel.fail_threshold)}% Rej/Repair · AQL {sel.aql_default}</b></div><div><small>Vendors</small><b>{sel.vendor_names.join(', ') || '—'}</b></div>
            <div><small>QC this month</small><b>{sel.month.n}</b></div><div><small>Rejection % (all time)</small><b>{sel.all.rate.toFixed(1)}%</b></div></div></div>}
        {sel && !isNew && <ManualsPanel key={sel.id} client={sel.id} title={`QC Manuals & SOPs — ${sel.code}`} />}
      </div>
      {(sel || isNew) ? <div className="card"><h2 className="mb">{isNew ? 'Add client' : 'Edit client'}</h2>
        <form onSubmit={save}>
          <div className="f"><label>Client short code</label><input type="text" required placeholder="KP" {...b('code')} /></div>
          <div className="f"><label>Company name</label><input type="text" required {...b('name')} /></div>
          <div className="f"><label>Contact person</label><input type="text" {...b('contact')} /></div>
          <div className="fr f"><div><label>Email</label><input type="email" {...b('email')} /></div><div><label>Phone</label><input type="text" {...b('phone')} /></div></div>
          <div className="f"><label>Head office email (auto-CC on reports)</label><input type="email" {...b('ho_email')} /></div>
          <div className="f"><label>Address</label><textarea {...b('address')} /></div>
          <div className="fr f"><div><label>Report format</label><input type="text" {...b('report_format')} /></div><div><label>Default AQL</label><input type="text" {...b('aql_default')} /></div></div>
          <div className="fr f"><div><label>FAIL if Rej/Repair % ≥</label><input type="number" min="1" max="100" {...b('fail_threshold')} /></div>
            <div><label>Status</label><select {...b('status')}><option value="ACTIVE">Active</option><option value="INACTIVE">Inactive</option></select></div></div>
          <div className="f"><label>Mapped vendors</label><Multi value={f.vendor_ids} onChange={(v) => F.set('vendor_ids', v)} options={d.vendors.map((v) => [v.id, v.name])} /><small>Ctrl/Cmd-click to select several</small></div>
          <label className="chk f"><input type="checkbox" checked={!!f.create_login} onChange={(e) => F.set('create_login', e.target.checked)} /> Create client portal login (uses email above)</label>
          {f.create_login && <div className="f"><label>Initial password (blank = auto)</label><input type="text" {...b('login_pw')} /></div>}
          <button className="btn pri block" disabled={busy}>SAVE CLIENT</button>
        </form></div> : <div className="card"><Empty>Select a client to see the profile, or add a new one.</Empty></div>}
    </div>}</Loader>
  </Page>;
}
