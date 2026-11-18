import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { Page } from '../app/Layout';
import { useAuth } from '../app/auth';
import { post } from '../app/api';
import { Badge, Empty, Loader, useAction, useApi, useForm, Thumb } from '../app/ui';
import { fdt, num, pct } from '../app/util';

export default function ReviewItem() {
  const { id } = useParams(); const [sp, setSp] = useSearchParams(); const revising = sp.has('revise');
  const q = useApi('review/item', { id }); const { refresh } = useAuth(); const nav = useNavigate(); const { run, busy } = useAction();
  const A = useForm({ final: 'PASS', note: '' }); const R = useForm({ sections: [], reason: '' });
  const [dev, setDev] = useState([]); const V = useForm({}); const [mAct, setMAct] = useState({});
  useEffect(() => { const d = q.data; if (!d) return;
    A.setF({ final: d.item.system_result || 'PASS', note: '' });
    R.setF({ sections: Object.entries(d.complete).filter(([, ok]) => !ok).map(([k]) => k), reason: '' });
    const fails = d.meas.filter((m) => m.result === 'FAIL');
    setDev([0, 1, 2].map((k) => { const m = fails[k]; return m ? { param: m.param, spec: m.spec + (m.unit || ''), actual: m.actual + (m.unit || ''), dev: (Number(m.actual) - Number(m.spec)).toFixed(2), pcs: d.item.inspected_qty } : { param: '', spec: '', actual: '', dev: '', pcs: '' }; }));
    const it = d.item; V.setF({ inspected_qty: it.inspected_qty, accepted_qty: it.accepted_qty, rejected_qty: it.rejected_qty, final_result: it.final_result || 'PASS', remark: it.remark || '', manager_note: it.manager_note || '', reason: '' });
    setMAct(Object.fromEntries(d.meas.map((m, k) => [k, m.actual ?? ''])));
  /* eslint-disable-next-line */ }, [q.data]);
  const done = (r, to) => { if (r) { refresh(); to ? nav(to) : q.reload(); } };
  return <Loader q={q}>{(d) => { const it = d.item, j = d.job; const insp = Math.max(1, +it.inspected_qty);
    return <Page title={'Inspection review — ' + (it.report_no || it.sku)} sub={`${j.job_no} · ${j.client_code} – ${j.vendor_name}`} actions={it.locked ? <Link className="btn gold" to={'/report/' + it.id}>Open report</Link> : null}>
      <div className="grid g-73">
        <div>
          <div className="card"><div className="card-h"><h2>Inspection review — {it.report_no || `${j.job_no} / ${it.sku}`}</h2><Badge s={it.status} /></div>
            {(it.img || it.instructions) && <div className="skucell mb" style={{ alignItems: 'flex-start' }}><Thumb src={it.img} size={96} alt={it.sku} />{it.instructions && <div className="note gold" style={{ margin: 0, flex: 1 }}><b>Client instructions:</b><p className="spec-pre">{it.instructions}</p></div>}</div>}
            <div className="kv">
              <div><small>Inspector</small><b>{j.inspector_code} · {j.inspector_name}</b></div><div><small>Vendor</small><b>{j.vendor_name}</b></div><div><small>SKU</small><b className="mono">{it.sku}</b></div>
              <div><small>PO</small><b>{j.po_no}</b></div><div><small>Submitted</small><b>{fdt(it.submitted_at)}</b></div><div><small>System result</small><b><Badge s={it.system_result || 'PENDING'} /></b></div>
              <div><small>Inspector result</small><b><Badge s={it.inspector_result || 'PENDING'} /></b></div><div><small>GPS / start</small><b>{it.gps || '—'}</b></div>
              <div><small>Pass / fail rule</small><b>{j.rule}</b></div><div><small>Lot no. · XRF machine</small><b>{(it.lot_no || '—') + ' · ' + (it.xrf_machine || '—')}</b></div>
              <div><small>Client style / stone</small><b>{(it.style_ref || '—') + ' · ' + (it.stone_setting || '—')}</b></div>
              <div><small>Factory evaluation</small><b>{[it.factory_coop, it.factory_line, it.factory_ready].map((x) => x || '—').join(' / ')}</b></div>
            </div></div>
          <div className="grid g2" style={{ marginTop: 16 }}>
            <div className="card" style={{ margin: 0 }}><h2 className="mb">Quantity</h2>
              <div className="grid g2" style={{ gap: 10 }}><div><small>Inspected</small><div className="big">{num(it.inspected_qty)}</div></div>
                <div><small>Accepted</small><div className="big ok-t">{num(it.accepted_qty)} <small>· {pct(it.accepted_qty, insp, 2)}</small></div></div>
                <div><small>Reject / repair</small><div className="big bad-t">{num(it.rejected_qty)} <small>· {pct(it.rejected_qty, insp, 2)}</small></div></div>
                <div><small>Check</small><div className={d.complete.Quantity ? 'ok-t' : 'bad-t'} style={{ fontWeight: 700, marginTop: 6 }}>{d.complete.Quantity ? '✓' : '✗'} {num(it.accepted_qty)} + {num(it.rejected_qty)} = {num(+it.accepted_qty + +it.rejected_qty)}</div></div></div></div>
            <div className="card" style={{ margin: 0 }}><h2 className="mb">Defects</h2>
              {!d.defects.length ? <Empty>No defects</Empty> : <table className="t"><thead><tr><th>Category</th><th>Type</th><th>Severity</th><th className="num">Qty</th></tr></thead><tbody>
                {d.defects.map((x) => <tr key={x.id}><td>{x.category}</td><td>{x.type}</td><td><Badge s={x.severity} /></td><td className="num">{num(x.qty)}</td></tr>)}</tbody></table>}
              <p className="muted" style={{ fontSize: 12 }}>Critical {d.sev.CRITICAL} · Major {d.sev.MAJOR} · Minor {d.sev.MINOR}</p></div>
          </div>
          <div className="card mt"><h2 className="mb">Quality checks &amp; tests</h2>
            <div className="grid g2" style={{ gap: '4px 20px' }}>{[...d.checks, ...d.tests].map((c, k) => <div key={k} style={{ display: 'flex', justifyContent: 'space-between', borderBottom: '1px solid #edf1f4', padding: '6px 0' }}>
              <span>{c.name}{c.remark ? <><br /><small>{c.remark}</small></> : null}</span><Badge s={c.result || 'PENDING'} /></div>)}</div>
            <h2 className="mb mt">Measurements</h2>
            <table className="t"><thead><tr><th>Parameter</th><th>Spec</th><th>Tolerance</th><th>Actual</th><th>Result</th></tr></thead><tbody>
              {d.meas.map((m, k) => <tr key={k}><td>{m.param}</td><td>{m.spec} {m.unit}</td><td>± {m.tol}</td>
                <td>{revising ? <input type="text" value={mAct[k] ?? ''} onChange={(e) => setMAct({ ...mAct, [k]: e.target.value })} style={{ width: 90, padding: '4px 6px' }} /> : m.actual}</td><td>{m.result ? <Badge s={m.result} /> : '—'}</td></tr>)}</tbody></table>
          </div>
          <div className="card"><h2 className="mb">Photos ({d.photos.length})</h2>{!d.photos.length && <Empty>No photos</Empty>}
            <div className="photos">{d.photos.map((p) => <figure key={p.id}><a target="_blank" rel="noreferrer" href={p.url}><img src={p.url} loading="lazy" alt="" /></a><figcaption><b>{p.kind}</b> {p.remark}</figcaption></figure>)}</div></div>
          {it.hold_status && <div className="card"><h2 className="mb">SKU hold history</h2><div className="note">Held for <b>{it.hold_for}</b> by {it.hold_by} · {fdt(it.hold_at)}<br />{it.hold_reason}<br /><b>Response:</b> {it.hold_status} — {it.hold_response || '—'}</div></div>}
        </div>
        <div>
          <div className="card"><h2 className="mb">Completeness check</h2><div className="complete">{Object.entries(d.complete).map(([k, ok]) => <span key={k} className={ok ? '' : 'no'}>{ok ? '✓' : '✗'} {k}</span>)}</div></div>
          {d.can_review && <div className="card"><h2 className="mb">System recommendation: {it.system_result || '—'} · Manager final decision</h2>
            <form onSubmit={async (e) => { e.preventDefault(); if (!window.confirm('Approve, lock the inspection and generate the PDF?')) return; done(await run(() => post('review/action', { act: 'approve', id, ...A.f, deviation: dev })), '/report/' + id); }}>
              <div className="decision">{[['PASS', 'PASS'], ['FAIL', 'FAIL'], ['CONDITIONAL', 'CONDITIONAL PASS (deviation → client)'], ['PENDING', 'PENDING']].map(([k, l]) =>
                <label key={k}><input type="radio" checked={A.f.final === k} onChange={() => A.set('final', k)} /> {l}</label>)}</div>
              {A.f.final === 'CONDITIONAL' && <div className="f"><label>Deviation for client (what is outside specification)</label>
                <table className="t"><thead><tr><th>Parameter</th><th>Spec</th><th>Actual</th><th>Deviation</th><th>Pcs</th></tr></thead><tbody>
                  {dev.map((x, k) => <tr key={k}>{['param', 'spec', 'actual', 'dev', 'pcs'].map((f) => <td key={f}><input value={x[f] ?? ''} onChange={(e) => setDev(dev.map((y, i) => (i === k ? { ...y, [f]: e.target.value } : y)))} style={{ padding: 5, width: f === 'param' ? 110 : 70 }} /></td>)}</tr>)}</tbody></table></div>}
              <div className="f"><label>Inspector remark</label><div className="note">{it.remark || '—'}{it.action_required ? <><br /><b>Action required:</b> {it.action_required}</> : null}</div></div>
              <div className="f"><label>Manager note (required when overriding the system result)</label><textarea {...A.bind('note')} /></div>
              <button className="btn pri block" disabled={busy}>APPROVE &amp; GENERATE LOCKED PDF</button>
            </form>
            <div className="hr" /><h2 className="mb">Return for correction</h2>
            <form onSubmit={async (e) => { e.preventDefault(); done(await run(() => post('review/action', { act: 'return', id, ...R.f })), '/review'); }}>
              <div className="f"><label>Section to correct</label><div className="grid g2" style={{ gap: 4 }}>{d.steps.map((s) => <label key={s} className="chk"><input type="checkbox" checked={R.f.sections.includes(s)}
                onChange={(e) => R.set('sections', e.target.checked ? [...R.f.sections, s] : R.f.sections.filter((x) => x !== s))} /> {s}</label>)}</div></div>
              <div className="f"><label>Reason for return</label><textarea {...R.bind('reason')} placeholder="Add close-up photo of defect & check length measurement" /></div>
              <button className="btn block" disabled={busy}>RETURN TO INSPECTOR</button>
            </form></div>}
          {it.status === 'RETURNED' && <div className="card"><div className="note ruby"><b>Returned for correction</b> (×{it.return_count})<br />Sections: {it.return_sections}<br />{it.return_reason}</div></div>}
          {!!+it.locked && <div className="card"><h2 className="mb">Approved &amp; locked</h2>
            <div className="kv"><div><small>Final result</small><b><Badge s={it.final_result} /></b></div><div><small>Revision</small><b>{String(it.rev).padStart(2, '0')}</b></div></div>
            {d.can_revise && !revising && <><p className="muted" style={{ fontSize: 12.5 }}>Unlock needs Super Admin + reason; old PDF kept, new PDF re-sent.</p><button className="btn ruby block" onClick={() => setSp({ revise: 1 })}>Unlock &amp; create revision</button></>}
            {d.can_revise && revising && <form onSubmit={async (e) => { e.preventDefault(); const r = await run(() => post('review/action', { act: 'revise', id, ...V.f, m_actual: mAct })); if (r) { setSp({}); done(r, '/report/' + id); } }}>
              <div className="hr" /><h2 className="mb">Revision {String(+it.rev + 1).padStart(2, '0')}</h2>
              <div className="fr f"><div><label>Inspected</label><input type="number" {...V.bind('inspected_qty')} /></div><div><label>Accepted</label><input type="number" {...V.bind('accepted_qty')} /></div><div><label>Rejected</label><input type="number" {...V.bind('rejected_qty')} /></div></div>
              <div className="f"><label>Final result</label><select {...V.bind('final_result')}><option value="PASS">PASS</option><option value="FAIL">FAIL</option><option value="CONDITIONAL">CONDITIONAL PASS</option></select></div>
              <div className="f"><label>Inspector remark</label><textarea {...V.bind('remark')} /></div>
              <div className="f"><label>Manager note</label><textarea {...V.bind('manager_note')} /></div>
              <div className="f"><label>Reason for unlock (audit)</label><textarea required {...V.bind('reason')} /></div>
              <p className="muted" style={{ fontSize: 12 }}>Measurement “Actual” values on the left are editable too.</p>
              <button className="btn ruby block" disabled={busy}>SAVE REVISION &amp; RE-SEND PDF</button>
            </form>}</div>}
        </div>
      </div>
    </Page>; }}</Loader>;
}
