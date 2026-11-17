import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Page } from '../app/Layout';
import { useAuth } from '../app/auth';
import { post } from '../app/api';
import { Badge, Loader, Sel, useAction, useApi } from '../app/ui';
import { fdt, num } from '../app/util';

const TABS = [['toggles', 'Feature toggles'], ['defects', 'Defect master'], ['checkpoints', 'Quality checkpoints'], ['tests', 'On-site tests'], ['masters', 'Categories & QC types'],
  ['aql', 'AQL tables'], ['template', 'Report template'], ['email', 'Email (SMTP)'], ['roles', 'Role permissions']];
const PERM = [['QC requests', ['Full', 'Full', 'View', '—', 'View / raise*', 'View / raise*']], ['Excel import', ['Full', 'Full', '—', '—', '—', 'Bulk request*']],
  ['Schedule requests', ['Full', 'Full', 'View', '—', 'View', 'View']], ['Assign inspector', ['Full', 'Full', 'Full', '—', '—', '—']], ['Inspection (app)', ['—', '—', '—', 'Full', '—', '—']],
  ['SKU hold approval', ['Full', '—', 'View', 'Raise', 'Respond (client holds)', 'Respond (vendor holds)']], ['Review / approve / return', ['Full', '—', 'Full', '—', '—', '—']],
  ['Concession decision', ['Full', '—', 'Raise', '—', 'Approve / reject', '—']], ['Reports / PDF', ['Full + revise', 'View', 'Full', 'Own', 'View (own client)', 'View (own vendor)']],
  ['Analytics', ['Full', 'View', 'Full', '—', 'View (mapped vendors)', '—']], ['Masters & settings', ['Full', 'View', 'View', '—', '—', '—']], ['Team & logins', ['Full', 'View', 'View', '—', '—', '—']]];

export default function Settings() {
  const [sp, setSp] = useSearchParams(); const tab = sp.get('tab') || 'toggles'; const { refresh } = useAuth();
  const q = useApi('settings', { lv: sp.get('lv') }); const { run, busy } = useAction();
  const save = async (body) => { const r = await run(() => post('settings/save', body)); if (r) { q.reload(); refresh(); } return r; };
  return <Page title="Master Settings" sub={'Masters, standard defect list, checkpoints, AQL tables, report template, email, feature toggles and role permissions' + (q.data && !q.data.can_edit ? ' · view only' : '')}>
    <Loader q={q}>{(d) => { const ro = !d.can_edit; return <div className="grid" style={{ gridTemplateColumns: '220px minmax(0,1fr)' }}>
      <div className="card" style={{ padding: 10, alignSelf: 'start' }}><div className="stack">
        {[['clients', 'Client master'], ['vendors', 'Vendor master'], ['mapping', 'Client–vendor mapping']].map(([k, l]) => <Link key={k} className="btn ghost block" style={{ justifyContent: 'flex-start', border: 0 }} to={'/' + k}>{l} ↗</Link>)}
        {TABS.map(([k, l]) => <button key={k} className={'btn block ' + (tab === k ? 'pri' : 'ghost')} style={{ justifyContent: 'flex-start', border: tab === k ? undefined : 0 }} onClick={() => setSp({ tab: k })}>{l}</button>)}
      </div></div>
      <div>{
        tab === 'toggles' ? <Toggles d={d} ro={ro} save={save} busy={busy} /> :
        tab === 'defects' ? <Defects d={d} ro={ro} save={save} /> :
        tab === 'checkpoints' ? <Checkpoints d={d} ro={ro} save={save} /> :
        tab === 'tests' ? <Tests d={d} ro={ro} save={save} /> :
        tab === 'masters' ? <Masters d={d} ro={ro} save={save} /> :
        tab === 'aql' ? <Aql d={d} lv={sp.get('lv') || 'II'} setLv={(v) => setSp({ tab: 'aql', lv: v })} /> :
        tab === 'template' ? <Template d={d} ro={ro} save={save} busy={busy} /> :
        tab === 'email' ? <Email d={d} ro={ro} save={save} busy={busy} /> :
        <div className="card"><h2 className="mb">Role permissions (6 roles)</h2><div className="tbl-wrap"><table className="t"><thead><tr><th>Module</th>{['Super Admin', 'Back Office', 'QC Manager', 'QC Inspector', 'Client User', 'Vendor User'].map((r) => <th key={r}>{r}</th>)}</tr></thead><tbody>
          {PERM.map(([m, v]) => <tr key={m}><td className="strong">{m}</td>{v.map((x, i) => <td key={i}>{x === '—' ? <span className="muted">—</span> : x}</td>)}</tr>)}</tbody></table></div>
          <p className="muted" style={{ fontSize: 12 }}>* when the matching feature toggle is ON. Every record carries client_id + vendor_id; client and vendor logins only ever see their own rows.</p></div>
      }</div>
    </div>; }}</Loader>
  </Page>;
}

function Toggles({ d, ro, save, busy }) {
  const [t, setT] = useState({}); useEffect(() => setT(Object.fromEntries(d.toggles.map((x) => [x.key, x.on]))), [d]);
  return <><div className="card"><div className="card-h"><h2>Feature toggles</h2><span className="pill">No code deploy needed</span></div>
    {d.toggles.map((x) => <label key={x.key} className="chk" style={{ padding: '10px 0', borderBottom: '1px solid var(--line)', justifyContent: 'space-between' }}>
      <span><b className="mono">{x.key}</b><br /><small>{x.label}</small></span><input type="checkbox" disabled={ro} checked={!!t[x.key]} onChange={(e) => setT({ ...t, [x.key]: e.target.checked })} style={{ width: 20, height: 20 }} /></label>)}
    <p className="note mt">Phase 1: vendor &amp; client request flags OFF — Back Office creates requests. Switch ON in Phase 2.</p>
    {!ro && <button className="btn pri" disabled={busy} onClick={() => save({ a: 'toggles', ...t })}>Save toggles</button>}</div>
    <div className="card"><h2 className="mb">Automation rules (built in)</h2><ul style={{ margin: 0, paddingLeft: 18, lineHeight: 1.9 }}>
      <li>Report no. format <b className="mono">JS/{'{YYYY}'}/{'{MM}'}/{'{CLIENT}'}_{'{VENDOR}'}/{'{SERIAL}'}</b> — e.g. {d.example_report_no}</li><li>Block submit if Accepted + Rejected ≠ Inspected</li>
      <li>Pass/fail: FAIL if Reject/Repair % ≥ client threshold (default 80%) for 100% / defined / sample jobs; AQL jobs use ISO Ac/Re</li>
      <li>Defect photo mandatory when defect qty &gt; 0 (toggle above)</li><li>System suggests PASS/FAIL; manager decides (override needs a note)</li>
      <li>Report locked after approval &amp; PDF — edits by Super Admin create Revision 01, 02… with audit trail; old PDF kept</li></ul></div></>;
}
function Defects({ d, ro, save }) {
  const [cat, setCat] = useState(d.categories[0]?.id); const [n, setN] = useState({ name: '', severity: 'MAJOR', new_cat: '' });
  const SEV = ['CRITICAL', 'MAJOR', 'MINOR'];
  return <div className="card"><div className="card-h"><h2>Defect master</h2></div>
    <div className="tabs">{d.categories.map((c) => <a key={c.id} href="#" className={+cat === +c.id ? 'on' : ''} onClick={(e) => { e.preventDefault(); setCat(c.id); }}>{c.name}</a>)}</div>
    <table className="t"><thead><tr><th>Defect</th><th>Severity</th><th /></tr></thead><tbody>
      {d.defects.filter((x) => +x.category_id === +cat).map((x) => <tr key={x.id}><td>{x.name}</td>
        <td>{ro ? <Badge s={x.severity} /> : <Sel className="w-auto" value={x.severity} onChange={(v) => save({ a: 'sev_defect', id: x.id, severity: v })} options={SEV} style={{ padding: '4px 8px' }} />}</td>
        <td>{!ro && <button className="btn sm ghost" onClick={() => window.confirm('Remove defect?') && save({ a: 'del_defect', id: x.id })}>✕</button>}</td></tr>)}</tbody></table>
    <p className="muted" style={{ fontSize: 12 }}>Standard defect list — every inspector picks from the same names, so analytics stay clean.</p>
    {!ro && <form className="filters mt" onSubmit={async (e) => { e.preventDefault(); if (await save({ a: 'add_defect', category_id: cat, ...n })) setN({ name: '', severity: 'MAJOR', new_cat: '' }); }}>
      <input type="text" value={n.new_cat} onChange={(e) => setN({ ...n, new_cat: e.target.value })} placeholder="…or new category" />
      <input type="text" required value={n.name} onChange={(e) => setN({ ...n, name: e.target.value })} placeholder="Defect name" />
      <Sel value={n.severity} onChange={(v) => setN({ ...n, severity: v })} options={SEV} /><button className="btn pri">Add defect</button></form>}
  </div>;
}
function Checkpoints({ d, ro, save }) {
  const [rows, setRows] = useState(d.checkpoints); useEffect(() => setRows(d.checkpoints), [d]);
  const [n, setN] = useState({ name: '', client_id: '', category: '', pass_remark: '', fail_remark: '' });
  const upd = (id, k, v) => setRows(rows.map((r) => (r.id === id ? { ...r, [k]: v } : r)));
  return <div className="card"><h2 className="mb">Critical quality checkpoints (report section 7)</h2>
    <p className="muted" style={{ marginTop: -6, fontSize: 12.5 }}>Remarks are offered to the inspector as presets when they pick PASS / FAIL / NA, and printed on the report.</p>
    <div className="tbl-wrap"><table className="t"><thead><tr><th>#</th><th>Checkpoint</th><th>Client / product</th><th>PASS remark</th><th>FAIL remark</th><th>NA remark</th><th /></tr></thead><tbody>
      {rows.map((k, i) => <tr key={k.id}><td>{i + 1}</td><td className="strong">{k.name}</td><td>{(k.client_code || 'All') + ' / ' + (k.category || 'All')}</td>
        {['pass_remark', 'fail_remark', 'na_remark'].map((f) => <td key={f}>{ro ? k[f] : <input type="text" value={k[f] || ''} onChange={(e) => upd(k.id, f, e.target.value)} style={{ minWidth: 150, padding: 5 }} />}</td>)}
        <td>{!ro && <span className="actions" style={{ flexWrap: 'nowrap' }}><button className="btn sm" onClick={() => save({ a: 'edit_check', ...k })}>Save</button>
          <button className="btn sm ghost" onClick={() => window.confirm('Remove checkpoint?') && save({ a: 'del_check', id: k.id })}>✕</button></span>}</td></tr>)}</tbody></table></div>
    {!ro && <form className="filters mt" onSubmit={async (e) => { e.preventDefault(); if (await save({ a: 'add_check', ...n })) setN({ name: '', client_id: '', category: '', pass_remark: '', fail_remark: '' }); }}>
      <input type="text" required value={n.name} onChange={(e) => setN({ ...n, name: e.target.value })} placeholder="Checkpoint" />
      <Sel value={n.client_id} onChange={(v) => setN({ ...n, client_id: v })} options={d.clients.map((c) => [c.id, c.code])} blank="All clients" />
      <Sel value={n.category} onChange={(v) => setN({ ...n, category: v })} options={d.product_categories.map((c) => c.name)} blank="All products" />
      <input type="text" value={n.pass_remark} onChange={(e) => setN({ ...n, pass_remark: e.target.value })} placeholder="PASS remark" />
      <input type="text" value={n.fail_remark} onChange={(e) => setN({ ...n, fail_remark: e.target.value })} placeholder="FAIL remark" /><button className="btn pri">Add checkpoint</button></form>}
  </div>;
}
function Tests({ d, ro, save }) {
  const [rows, setRows] = useState(d.tests); useEffect(() => setRows(d.tests), [d]); const [n, setN] = useState({ name: '', method: '' });
  return <div className="card"><h2 className="mb">On-site physical &amp; reliability tests (report section 4)</h2><table className="t"><thead><tr><th>Test</th><th>Method / default QC remark</th><th /></tr></thead><tbody>
    {rows.map((t) => <tr key={t.id}><td className="strong">{t.name}</td><td>{ro ? t.method : <input type="text" value={t.method || ''} onChange={(e) => setRows(rows.map((x) => (x.id === t.id ? { ...x, method: e.target.value } : x)))} style={{ padding: 5 }} />}</td>
      <td>{!ro && <span className="actions" style={{ flexWrap: 'nowrap' }}><button className="btn sm" onClick={() => save({ a: 'edit_test', id: t.id, method: t.method })}>Save</button><button className="btn sm ghost" onClick={() => window.confirm('Remove test?') && save({ a: 'del_test', id: t.id })}>✕</button></span>}</td></tr>)}</tbody></table>
    {!ro && <form className="filters mt" onSubmit={async (e) => { e.preventDefault(); if (await save({ a: 'add_test', ...n })) setN({ name: '', method: '' }); }}>
      <input type="text" required value={n.name} onChange={(e) => setN({ ...n, name: e.target.value })} placeholder="Test name" /><input type="text" value={n.method} onChange={(e) => setN({ ...n, method: e.target.value })} placeholder="Method / remark" /><button className="btn pri">Add test</button></form>}
  </div>;
}
function Masters({ d, ro, save }) {
  const [c, setC] = useState(''); const [t, setT] = useState('');
  const list = (rows, del) => <table className="t"><tbody>{rows.map((x) => <tr key={x.id}><td>{x.name}</td><td>{!ro && <button className="btn sm ghost" onClick={() => save({ a: del, id: x.id })}>✕</button>}</td></tr>)}</tbody></table>;
  return <div className="grid g2">
    <div className="card" style={{ margin: 0 }}><h2 className="mb">Product categories</h2>{list(d.product_categories, 'del_cat')}
      {!ro && <form className="filters mt" onSubmit={async (e) => { e.preventDefault(); if (await save({ a: 'add_cat', name: c })) setC(''); }}><input type="text" required value={c} onChange={(e) => setC(e.target.value)} placeholder="New category" /><button className="btn pri">Add</button></form>}</div>
    <div className="card" style={{ margin: 0 }}><h2 className="mb">QC types</h2>{list(d.qc_types, 'del_type')}
      {!ro && <form className="filters mt" onSubmit={async (e) => { e.preventDefault(); if (await save({ a: 'add_type', name: t })) setT(''); }}><input type="text" required value={t} onChange={(e) => setT(e.target.value)} placeholder="New QC type" /><button className="btn pri">Add</button></form>}</div>
  </div>;
}
function Aql({ d, lv, setLv }) {
  return <div className="card"><div className="card-h"><h2>AQL lookup — ISO 2859-1 (single sampling, normal inspection)</h2>
    <Sel className="w-auto" value={lv} onChange={setLv} options={[['I', 'General I'], ['II', 'General II'], ['III', 'General III'], ['S1', 'S-1'], ['S2', 'S-2'], ['S3', 'S-3'], ['S4', 'S-4']]} /></div>
    <div className="tbl-wrap"><table className="t"><thead><tr><th>Lot size</th><th>Code</th><th className="num">Sample</th>{d.aql.aqls.map((a) => <th key={a} className="num">AQL {a} Ac/Re</th>)}</tr></thead><tbody>
      {d.aql.rows.map((r) => <tr key={r.lo}><td>{num(r.lo)} – {r.hi ? num(r.hi) : 'over'}</td><td><b>{r.letter}</b></td><td className="num">{r.sample}</td>
        {r.plans.map((p, i) => <td key={i} className="num">{p ? <>{p.ac} / {p.re}{p.n !== r.sample && <small> (n={p.n})</small>}</> : '—'}</td>)}</tr>)}</tbody></table></div>
    <p className="muted" style={{ fontSize: 12 }}>Arrows of the ISO master table are resolved automatically — when the plan uses a different sample size it is shown as (n=…). Values to be verified by JEWELSETU before go-live.</p></div>;
}
function Template({ d, ro, save, busy }) {
  const [s, setS] = useState(d.settings); useEffect(() => setS(d.settings), [d]); const b = (k) => ({ value: s[k] || '', onChange: (e) => setS({ ...s, [k]: e.target.value }), disabled: ro });
  return <div className="card"><h2 className="mb">PDF report template</h2>
    <div className="fr f"><div><label>Header</label><input type="text" readOnly value="Logo · company bar · report title · report mode · report no." /></div><div><label>Numbering</label><input type="text" readOnly value="JS/{YYYY}/{MM}/{CLIENT}_{VENDOR}/{SERIAL} · PO: …/PO-{PO}" /></div></div>
    <div className="fr f"><div><label>Company name (header bar)</label><input type="text" {...b('company_name')} /></div><div><label>Website</label><input type="text" {...b('company_web')} /></div></div>
    <div className="f"><label>Tagline (footer)</label><input type="text" {...b('company_tagline')} /></div>
    <div className="f"><label>Inspection rules &amp; terms of acceptance</label><textarea rows="6" {...b('report_terms')} /></div>
    <div className="f"><label>Consultancy portfolio</label><textarea rows="5" {...b('report_portfolio')} /></div>
    <div className="f"><label>Contact line</label><input type="text" {...b('company_contact')} /></div>
    <p className="muted" style={{ fontSize: 12 }}>Report title is automatic from scope + QC type, e.g. “100% PRE-SHIPMENT INSPECTION (PSI) REPORT”. Pass/fail rule comes from each client (Clients → fail threshold %).</p>
    {!ro && <button className="btn pri" disabled={busy} onClick={() => save({ a: 'template', ...s })}>Save template</button>}</div>;
}
function Email({ d, ro, save, busy }) {
  const [s, setS] = useState({ ...d.settings, smtp_pass: '' }); const [to, setTo] = useState('');
  useEffect(() => setS({ ...d.settings, smtp_pass: '' }), [d]); const b = (k) => ({ value: s[k] || '', onChange: (e) => setS({ ...s, [k]: e.target.value }), disabled: ro });
  return <div className="card"><h2 className="mb">Email (SMTP) settings</h2>
    <div className="f"><label>Transport</label><Sel value={s.mail_transport || 'none'} onChange={(v) => setS({ ...s, mail_transport: v })} disabled={ro}
      options={[['none', 'Off — log only (reports still published on portals)'], ['phpmail', 'PHP mail() — shared hosting default'], ['smtp', 'SMTP (Gmail, SES, SendGrid, cPanel mailbox)']]} /></div>
    <div className="fr f"><div><label>SMTP host</label><input type="text" placeholder="mail.jewelsetu.com" {...b('smtp_host')} /></div><div><label>Port</label><input type="text" {...b('smtp_port')} /></div>
      <div><label>Security</label><Sel value={s.smtp_secure || 'tls'} onChange={(v) => setS({ ...s, smtp_secure: v })} disabled={ro} options={[['tls', 'TLS (587)'], ['ssl', 'SSL (465)'], ['none', 'None']]} /></div></div>
    <div className="fr f"><div><label>SMTP user</label><input type="text" {...b('smtp_user')} /></div><div><label>SMTP password {d.settings.smtp_pass_set ? '(saved — leave blank to keep)' : ''}</label><input type="password" {...b('smtp_pass')} /></div></div>
    <div className="fr f"><div><label>From email</label><input type="email" {...b('mail_from')} /></div><div><label>From name</label><input type="text" {...b('mail_from_name')} /></div></div>
    <div className="f"><label>JEWELSETU admin copy (CC on every report)</label><input type="email" {...b('admin_email')} /></div>
    {!ro && <button className="btn pri" disabled={busy} onClick={() => save({ a: 'email', ...s })}>Save email settings</button>}
    {!ro && <><div className="hr" /><form className="filters" onSubmit={(e) => { e.preventDefault(); save({ a: 'testmail', to }); }}><input type="email" required value={to} onChange={(e) => setTo(e.target.value)} placeholder="Send test to…" /><button className="btn">Send test email</button></form></>}
    <h2 className="mb mt">Email history</h2>
    <table className="t"><thead><tr><th>Recipient</th><th>Email</th><th>Subject</th><th>Sent</th><th>Status</th></tr></thead><tbody>
      {d.emails.map((e) => <tr key={e.id}><td>{e.recipient_type}</td><td>{e.email}</td><td>{String(e.subject).slice(0, 60)}</td><td>{fdt(e.sent_at)}</td><td><Badge s={e.status} /></td></tr>)}</tbody></table></div>;
}
