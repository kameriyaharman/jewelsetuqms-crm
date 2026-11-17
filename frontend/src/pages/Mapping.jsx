import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Page } from '../app/Layout';
import { post } from '../app/api';
import { Badge, Loader, useAction, useApi } from '../app/ui';
import { fdt } from '../app/util';

export default function Mapping() {
  const q = useApi('mapping'); const [map, setMap] = useState(new Set()); const { run, busy } = useAction();
  useEffect(() => { if (q.data) setMap(new Set(q.data.map)); }, [q.data]);
  const toggle = (k) => setMap((m) => { const n = new Set(m); n.has(k) ? n.delete(k) : n.add(k); return n; });
  return <Page title="Client – Vendor Mapping" sub="One vendor can work for many clients; each client sees only its own requests and reports">
    <Loader q={q}>{(d) => <div className="grid g2">
      <div className="card"><div className="card-h"><h2>Client – Vendor mapping (many-to-many)</h2></div>
        <div className="tbl-wrap"><table className="t"><thead><tr><th>Vendor \ Client</th>{d.clients.map((c) => <th key={c.id} style={{ textAlign: 'center' }}>{c.code}</th>)}</tr></thead><tbody>
          {d.vendors.map((v) => <tr key={v.id}><td className="strong">{v.name}</td>{d.clients.map((c) => { const k = c.id + '_' + v.id;
            return <td key={k} style={{ textAlign: 'center' }}><input type="checkbox" checked={map.has(k)} onChange={() => toggle(k)} style={{ width: 17, height: 17, accentColor: 'var(--rose-d)' }} /></td>; })}</tr>)}
        </tbody></table></div>
        <div className="actions mt"><button className="btn pri" disabled={busy} onClick={() => run(() => post('mapping/save', { pairs: [...map] }))}>Save mapping</button></div>
        {d.demo && <><div className="hr" /><h2 className="mb">Visibility check — request {d.demo.req_no}</h2>
          <p className="muted" style={{ marginTop: -6 }}>{d.demo.vendor} raised this for {d.demo.client}. Row-level security on client_id + vendor_id:</p>
          <div className="grid g2" style={{ gap: 8 }}>
            <div className="note rose"><b>JEWELSETU Back Office</b><br />Queue · {d.demo.status}</div>
            <div className="note rose"><b>Client {d.demo.client}</b><br />Visible in {d.demo.client} portal</div>
            {d.demo.others.map((o) => <div key={o} className="note ruby"><b>Client {o}</b> (also maps {d.demo.vendor})<br />Not visible</div>)}
            <div className="note ruby"><b>Other vendors</b><br />Not visible</div>
          </div></>}
      </div>
      <div className="card"><div className="card-h"><h2>Portal logins</h2><Link to="/team?f=portal&new=1">Create login</Link></div>
        <div className="tbl-wrap"><table className="t"><thead><tr><th>User</th><th>Portal</th><th>Linked to</th><th>Last login</th><th>Status</th></tr></thead><tbody>
          {d.logins.map((u) => <tr key={u.id}><td className="mono">{u.role === 'vendor' ? u.emp_code : u.email}</td><td>{u.role === 'vendor' ? 'Vendor' : 'Client'}</td><td>{u.ccode || u.vname}</td>
            <td>{u.last_login ? fdt(u.last_login) : <span className="muted">never</span>}</td><td><Badge s={u.status} /></td></tr>)}</tbody></table></div>
      </div>
    </div>}</Loader>
  </Page>;
}
