import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Page } from '../app/Layout';
import { useAuth } from '../app/auth';
import { apiUrl, post, upload } from '../app/api';
import { Empty, Icon, Kpi, Loader, Sel, useAction, useApi, useToast } from '../app/ui';
import { num } from '../app/util';

export default function Import() {
  const q = useApi('import'); const { refresh } = useAuth(); const nav = useNavigate(); const toast = useToast(); const { run, busy } = useAction();
  const [cid, setCid] = useState(''); const [file, setFile] = useState(null); const [up, setUp] = useState(false);
  const doUpload = async (e) => {
    e.preventDefault(); if (!cid || !file) { toast('Select a client and an Excel file.', 'err'); return; }
    const fd = new FormData(); fd.append('client_id', cid); fd.append('file', file); setUp(true);
    try { const r = await upload('import/upload', fd); q.setData((d) => ({ ...d, import: r.import })); } catch (ex) { toast(ex.message, 'err'); }
    setUp(false);
  };
  const create = async (mode) => { const r = await run(() => post('import/create', { mode })); if (r) { refresh(); nav(q.data?.vendor_mode ? '/requests' : '/requests?src=Excel+import'); } };
  const reset = async () => { await post('import/reset'); q.setData((d) => ({ ...d, import: null })); };
  return <Loader q={q}>{(d) => { const imp = d.import; const rows = imp?.rows || [];
    const ok = rows.filter((r) => !r.errors.length).length; const warn = rows.filter((r) => !r.errors.length && r.warnings.length).length; const err = rows.length - ok;
    const issues = {}; rows.forEach((r) => [...r.errors, ...r.warnings].forEach((x) => { issues[x] = (issues[x] || 0) + 1; }));
    return <Page title={d.vendor_mode ? 'Bulk Excel QC Request' : 'Import Excel'} sub="Upload the client’s requirement sheet, see every row checked, download errors, then create QC jobs"
      actions={<a className="btn" href={apiUrl('import/template')}><Icon n="download" /> Download Excel template</a>}>
      <div className="card mb"><div className="flow"><span className={imp ? 'done' : 'cur'}>1. Select client &amp; upload</span><span className={imp ? 'cur' : ''}>2. Preview &amp; validate</span><span>3. Create QC jobs</span></div></div>
      <div className="grid" style={{ gridTemplateColumns: 'minmax(280px,.8fr) minmax(0,2.2fr)' }}>
        <div className="card"><h2 className="mb">Import QC requirement</h2>
          <form onSubmit={doUpload}>
            <div className="f"><label>Select client</label><Sel value={cid || imp?.client_id || ''} onChange={setCid} options={d.clients.map((c) => [c.id, c.code + ' — ' + c.name])} blank="Select client" /></div>
            <div className="f"><label>Excel file (.xlsx or .csv)</label><input type="file" accept=".xlsx,.csv" onChange={(e) => setFile(e.target.files[0] || null)} /></div>
            <button className="btn pri block" disabled={up}>{up ? 'Reading…' : 'Upload & validate'}</button>
          </form>
          {imp && <div className="note rose mt">📄 <b>{imp.file}</b><br />{rows.length} rows · {Math.round(imp.size / 1024)} KB · uploaded</div>}
          <div className="hr" />
          <p className="muted" style={{ fontSize: 12.5 }}><b>Columns:</b> {d.columns.join(', ')}.<br /><b>Checks:</b> client–vendor mapping, Ready ≤ Order, duplicate PO + SKU (in file and already in system), scope format (Bulk 100% · AQL-1.5 · Sample · Defined 50).</p>
          <a className="btn block" href={apiUrl('import/template')}><Icon n="download" /> Download Excel template</a>
        </div>
        <div className="card">
          {!imp ? <Empty>Upload a file to preview and validate the rows here.</Empty> : <>
            <div className="card-h"><h2>Preview — client: {imp.client} · file: {imp.file}</h2><span>{rows.length} rows</span></div>
            <div className="tbl-wrap" style={{ maxHeight: 440 }}><table className="t"><thead><tr><th>No</th><th>Vendor</th><th>PO no</th><th>SKU</th><th>Scope</th><th className="num">Order</th><th className="num">Ready</th><th>Check</th></tr></thead><tbody>
              {rows.map((r) => <tr key={r.n}><td>{String(r.n - 1).padStart(2, '0')}</td><td>{r.vendor}</td><td className="mono">{r.po}</td><td className="mono">{r.sku}</td><td>{r.scope.label}</td><td className="num">{num(r.order)}</td><td className="num">{num(r.ready)}</td>
                <td>{r.errors.length ? <span className="badge b-fail">{r.errors.join(', ')}</span> : r.warnings.length ? <span className="badge b-pending">{r.warnings.join(', ')}</span> : <span className="badge b-pass">Valid</span>}</td></tr>)}
            </tbody></table></div>
            <div className="grid g3 mt" style={{ gap: 10 }}>
              <Kpi label="Records valid" value={ok} sub={`${warn} with warnings`} tone="ok" icon="check" />
              {Object.entries(issues).slice(0, 2).map(([k, n]) => <Kpi key={k} label={k} value={n} tone={/missing/i.test(k) && !/qty/i.test(k) ? 'warn' : 'bad'} icon="alert" />)}
            </div>
            <div className="actions mt">
              <button className="btn ghost" onClick={reset}>Start over</button>
              {(err || warn) ? <a className="btn" href={apiUrl('import/template', { errors: 1 })}><Icon n="download" /> Download error file</a> : null}
              <span className="right actions"><button className="btn" disabled={!ok || busy} onClick={() => create('draft')}>Save as draft</button><button className="btn pri" disabled={!ok || busy} onClick={() => create('create')}>Create QC jobs ({ok} rows)</button></span>
            </div>
            {err > 0 && <p className="muted" style={{ fontSize: 12 }}>Rows with errors are skipped. Valid rows are grouped into one request per vendor + PO + scope.</p>}
          </>}
        </div>
      </div>
    </Page>; }}</Loader>;
}
