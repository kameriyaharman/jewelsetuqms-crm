import { useEffect } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Page } from '../app/Layout';
import { useAuth } from '../app/auth';
import { post } from '../app/api';
import { Badge, Empty, Icon, Loader, Multi, Result, Sel, useAction, useApi, useForm, Paged } from '../app/ui';
import { fdate } from '../app/util';

const blank = { id: 0, code: 'VND-', short_code: '', name: '', contact: '', email: '', phone: '', ho_email: '', address: '', location: '', geo_lat: '', geo_lng: '', geo_radius: '', status: 'ACTIVE', client_ids: [], create_login: false, login_pw: '' };

export default function Vendors() {
  const { me } = useAuth(); const role = me.user.role; const canEdit = ['super_admin', 'back_office'].includes(role);
  const [sp, setSp] = useSearchParams(); const id = sp.get('id'); const edit = sp.has('edit') || sp.has('new');
  const q = useApi('vendors', { id, q: sp.get('q'), client: sp.get('client') });
  const F = useForm(blank); const { run, busy } = useAction(); const sel = q.data?.sel;
  useEffect(() => { if (sp.has('new')) F.setF(blank); else if (sel) F.setF({ ...blank, ...sel, short_code: sel.short_code || '', geo_lat: sel.geo_lat || '', geo_lng: sel.geo_lng || '', geo_radius: sel.geo_radius || '' }); /* eslint-disable-next-line */ }, [sel?.id, sp.has('new')]);
  const save = async (e) => { e.preventDefault(); const r = await run(() => post('vendors/save', F.f)); if (r) { setSp({ id: r.id }); q.reload(); } };
  const b = F.bind, f = F.f;
  return <Page title={role === 'client' ? 'My Vendors' : 'Vendor Management'} actions={canEdit && <button className="btn pri" onClick={() => setSp({ new: 1 })}><Icon n="plus" /> Add Vendor</button>}>
    <Loader q={q}>{(d) => <div className="grid g-82">
      <div className="card">
        <form className="filters" onSubmit={(e) => { e.preventDefault(); setSp({ q: e.target.q.value, client: sp.get('client') || '' }); }}>
          <input type="search" name="q" defaultValue={sp.get('q') || ''} placeholder="Search vendor…" />
          {role !== 'client' && <Sel value={sp.get('client') || ''} onChange={(v) => setSp({ client: v })} options={d.clients.map((c) => [c.id, c.code])} blank="Client: All" />}
          <button className="btn sm">Search</button></form>
        <Paged rows={d.rows} per={25} label="vendors">{(pr) => <div className="tbl-wrap"><table className="t"><thead><tr><th>Vendor</th><th>Mapped clients</th><th>Location</th><th className="num">Total QC</th><th className="num">Rejection %</th><th>Status</th></tr></thead><tbody>
          {pr.map((v) => <tr key={v.id} className={'click ' + (sel?.id === v.id ? 'sel' : '')} onClick={() => setSp({ id: v.id })}>
            <td><span className="avatar">{v.name[0]}</span><b>{v.name}</b></td><td>{v.clients.join(', ')}</td><td>{v.location}</td><td className="num">{v.n}</td>
            <td className={'num ' + (v.rate > 5 ? 'bad-t' : '')}>{Number(v.rate).toFixed(1)}%</td><td><Badge s={v.status} /></td></tr>)}
        </tbody></table></div>}</Paged>
      </div>
      <div>
        {sel && !edit ? <div className="card">
          <div className="card-h"><h2><span className="avatar">{sel.name[0]}</span>{sel.name}</h2><Badge s={sel.status} /></div>
          <p className="muted" style={{ marginTop: -6 }}>Mapped clients: {sel.clients.join(', ')} · {sel.location}</p>
          <div className="kv mb"><div><small>Factory address</small><b>{sel.address}</b></div><div><small>Contact person</small><b>{sel.contact}</b></div>
            <div><small>Email</small><b>{sel.email}</b></div><div><small>Head office email</small><b>{sel.ho_email}</b></div><div><small>Report code</small><b>{sel.short_code || '—'}</b></div></div>
          <div className="grid g4 mb" style={{ gap: 8, textAlign: 'center' }}>
            {[['Total QC', sel.stats.n, ''], ['Pass', sel.stats.pass, 'ok-t'], ['Fail', sel.stats.fail, 'bad-t'], ['Rejection', sel.stats.rate.toFixed(1) + '%', '']].map(([l, v, c]) =>
              <div key={l} className="card" style={{ padding: 10, margin: 0 }}><small>{l}</small><div className={'big ' + c}>{v}</div></div>)}</div>
          <div className={'note mb ' + (sel.geo_lat ? '' : 'gold')} style={{ fontSize: 12.5 }}><b>Factory GPS pin:</b> {sel.geo_lat ? <>{sel.geo_lat}, {sel.geo_lng} · radius {sel.geo_radius || d.geo_radius || 250} m · <a target="_blank" rel="noreferrer" href={`https://www.google.com/maps?q=${sel.geo_lat},${sel.geo_lng}`}>map</a></> : 'not set — inspector check-in cannot be GPS-verified yet.'}
            {sel.geo_pending_lat && <div className="mt"><b>📍 Location captured by inspector {sel.geo_pending_by_code || ''} on {fdate(sel.geo_pending_at, 'd M, h:i A')}:</b> {sel.geo_pending_lat}, {sel.geo_pending_lng} · <a target="_blank" rel="noreferrer" href={`https://www.google.com/maps?q=${sel.geo_pending_lat},${sel.geo_pending_lng}`}>check on map</a>
              {canEdit && <div className="actions" style={{ justifyContent: 'flex-start', marginTop: 6 }}><button className="btn sm pri" disabled={busy} onClick={async () => { if (await run(() => post('vendors/geo_verify', { id: sel.id, accept: 1 }))) q.reload(); }}>✓ Verify &amp; save as factory pin</button>
                <button className="btn sm" disabled={busy} onClick={async () => { if (await run(() => post('vendors/geo_verify', { id: sel.id, accept: 0 }))) q.reload(); }}>Reject</button></div>}</div>}</div>
          <div className="actions mb"><Link className="btn" to={'/reports?vendor=' + sel.id}>View QC history</Link><Link className="btn" to={'/analytics?vendor=' + sel.id}>Defect analysis</Link>
            {canEdit && <button className="btn pri" onClick={() => setSp({ id: sel.id, edit: 1 })}>Edit / portal login</button>}</div>
          <h2 className="mb">Recent QC</h2>
          {!sel.recent.length ? <Empty>No approved reports yet.</Empty> : <table className="t"><thead><tr><th>Report</th><th>Date</th><th>SKU</th><th>Result</th></tr></thead><tbody>
            {sel.recent.map((i) => <tr key={i.id}><td><Link className="mono" to={'/report/' + i.id}>{i.report_no}</Link></td><td>{fdate(i.approved_at, 'd M')}</td><td className="mono">{i.sku}</td><td><Result r={i.final_result} /></td></tr>)}</tbody></table>}
        </div> : canEdit && edit ? <div className="card"><h2 className="mb">{sel && !sp.has('new') ? 'Edit vendor' : 'Add vendor'}</h2>
          <form onSubmit={save}>
            <div className="fr f"><div><label>Vendor code (portal login)</label><input type="text" required {...b('code')} /></div><div><label>Report code (e.g. GL)</label><input type="text" maxLength="8" {...b('short_code')} /></div></div>
            <div className="f"><label>Vendor / factory name</label><input type="text" required {...b('name')} /></div>
            <div className="fr f"><div><label>Contact person</label><input type="text" {...b('contact')} /></div><div><label>Status</label><select {...b('status')}><option value="ACTIVE">Active</option><option value="INACTIVE">Inactive</option></select></div></div>
            <div className="fr f"><div><label>Email (reports go here)</label><input type="email" {...b('email')} /></div><div><label>Phone</label><input type="text" {...b('phone')} /></div></div>
            <div className="f"><label>Head office email</label><input type="email" {...b('ho_email')} /></div>
            <div className="f"><label>Factory address</label><textarea {...b('address')} /></div>
            <div className="f"><label>Location (area, city)</label><input type="text" {...b('location')} /></div>
            <div className="f"><label>Factory GPS pin (for inspector check-in)</label><div className="fr"><div><input type="text" placeholder="Latitude e.g. 26.8467" {...b('geo_lat')} /></div><div><input type="text" placeholder="Longitude e.g. 75.8022" {...b('geo_lng')} /></div><div><input type="number" placeholder="Radius m (250)" {...b('geo_radius')} /></div></div>
              <div className="actions" style={{ justifyContent: 'flex-start', marginTop: 6 }}><button type="button" className="btn sm" onClick={() => navigator.geolocation?.getCurrentPosition((p) => F.setF({ ...F.f, geo_lat: p.coords.latitude.toFixed(6), geo_lng: p.coords.longitude.toFixed(6) }), () => alert('Allow location access to use this.'), { enableHighAccuracy: true, timeout: 20000 })}>📍 Use my current location</button>
                {f.geo_lat && f.geo_lng && <a className="btn sm" target="_blank" rel="noreferrer" href={`https://www.google.com/maps?q=${f.geo_lat},${f.geo_lng}`}>Check on Google Maps</a>}</div>
              <small className="muted">Tip: in Google Maps long-press the factory gate → copy the two numbers shown. Leave blank and the first inspector check-in is sent to you to verify.</small></div>
            <div className="f"><label>Mapped clients</label><Multi value={f.client_ids} onChange={(v) => F.set('client_ids', v)} options={d.clients.map((c) => [c.id, c.code + ' — ' + c.name])} size={4} /></div>
            {sel?.login && !sp.has('new') ? <div className="note rose f">Portal login exists: <b>{sel.login.emp_code}</b> ({sel.login.status}). Manage in Team.</div> : <>
              <label className="chk f"><input type="checkbox" checked={!!f.create_login} onChange={(e) => F.set('create_login', e.target.checked)} /> Create vendor portal login (login = vendor code)</label>
              {f.create_login && <div className="f"><label>Initial password (blank = auto)</label><input type="text" {...b('login_pw')} /></div>}</>}
            <button className="btn pri block" disabled={busy}>SAVE VENDOR</button>
          </form></div> : <div className="card"><Empty>Select a vendor to see its profile and quality history.</Empty></div>}
      </div>
    </div>}</Loader>
  </Page>;
}
