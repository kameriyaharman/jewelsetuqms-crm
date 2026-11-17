import { useEffect } from 'react';
import { Link, useParams } from 'react-router-dom';
import { Page } from '../app/Layout';
import { useAuth } from '../app/auth';
import { pdfUrl, post } from '../app/api';
import { Badge, Empty, Icon, Loader, Result, useAction, useApi, useForm } from '../app/ui';
import { fdt } from '../app/util';

export default function ReportView() {
  const { id } = useParams(); const { me } = useAuth(); const internal = ['super_admin', 'back_office', 'qc_manager'].includes(me.user.role);
  const q = useApi('report', { id }); const { run, busy } = useAction(); const F = useForm({ subject: '', message: '' });
  useEffect(() => { if (q.data) F.setF({ subject: q.data.subject, message: q.data.message }); /* eslint-disable-next-line */ }, [q.data]);
  return <Loader q={q}>{(d) => { const it = d.item, j = d.job;
    return <Page title={'Report ' + it.report_no} sub={`${j.client_name} · ${j.vendor_name} · PO ${j.po_no} · ${it.sku}`}
      actions={<><a className="btn gold" href={pdfUrl({ item: it.id, dl: 1 })}><Icon n="download" /> Design PDF</a>{d.po_pdf && <a className="btn" target="_blank" rel="noreferrer" href={pdfUrl({ job: j.id })}>PO-wise PDF</a>}</>}>
      <div className="grid g-73">
        <div className="card" style={{ padding: 0, overflow: 'hidden' }}><div className="card-h" style={{ padding: '14px 18px 0' }}><h2>Report preview · {it.report_no}</h2><a target="_blank" rel="noreferrer" href={pdfUrl({ item: it.id })}>Open full screen</a></div>
          <iframe title="report" src={pdfUrl({ item: it.id }) + '#view=FitH'} style={{ width: '100%', height: '78vh', border: 0, background: '#eee' }} /></div>
        <div>
          <div className="card"><h2 className="mb">Report generated successfully</h2>
            <p className="muted" style={{ marginTop: -6 }}>approved {fdt(it.approved_at)}</p>
            <div className="actions mb"><Badge s="APPROVED" /><Badge s="PDF GENERATED" /><Result r={it.final_result} /><span className="right">🔒 Locked · Rev {String(it.rev).padStart(2, '0')}</span></div>
            <div className="actions"><a className="btn" target="_blank" rel="noreferrer" href={pdfUrl({ item: it.id })}>View PDF</a><a className="btn" href={pdfUrl({ item: it.id, dl: 1 })}>Download</a>
              {me.user.role === 'super_admin' && <Link className="btn" to={`/review/${it.id}?revise=1`}>Unlock / revise</Link>}</div>
          </div>
          {it.final_result === 'CONDITIONAL' && <div className="card"><h2 className="mb">Concession</h2><div className="note">{it.manager_note}</div>
            <p>Client decision: <Badge s={it.concession_status || 'PENDING'} /> {it.concession_by ? `· ${it.concession_by} · ${fdt(it.concession_at)}` : ''}</p>
            {it.concession_remark && <p className="muted">“{it.concession_remark}”</p>}
            {me.user.role === 'client' && it.concession_status === 'PENDING' && <Link className="btn pri" to="/approvals">Respond now</Link>}</div>}
          {internal && <>
            <div className="card"><h2 className="mb">Send QC report</h2>
              <form onSubmit={(e) => { e.preventDefault(); run(() => post('job/action', { act: 'email_item', id: j.id, item_id: it.id, ...F.f })).then((r) => r && q.reload()); }}>
                <div className="f"><label>To</label><input type="text" readOnly value={'Vendor · ' + (j.vendor_email || '')} /></div>
                <div className="f"><label>CC</label><input type="text" readOnly value={`Client HO · ${j.client_ho_email || ''} ; JEWELSETU · ${d.admin_email}`} /></div>
                <div className="f"><label>Subject</label><input type="text" {...F.bind('subject')} /></div>
                <div className="f"><label>Message</label><textarea rows="5" {...F.bind('message')} /></div>
                <p className="muted" style={{ fontSize: 12 }}>📎 report PDF · already published to Client portal ({j.client_code}) &amp; Vendor portal ({j.vendor_name})</p>
                {d.mail_off && <div className="note f" style={{ fontSize: 12 }}>Email transport is off — sends are logged only. Configure in Settings → Email.</div>}
                <button className="btn pri block" disabled={busy}>SEND REPORT</button></form></div>
            <div className="card"><h2 className="mb">Email history</h2>{!d.emails.length ? <Empty>Not emailed yet.</Empty> :
              <table className="t"><thead><tr><th>Recipient</th><th>Sent</th><th>Status</th></tr></thead><tbody>{d.emails.map((e) => <tr key={e.id}><td>{e.recipient_type}<br /><small>{e.email}</small></td><td>{fdt(e.sent_at)}</td><td><Badge s={e.status} />{e.error && <><br /><small title={e.error}>{e.error.slice(0, 40)}…</small></>}</td></tr>)}</tbody></table>}</div>
            <div className="card"><h2 className="mb">Revision &amp; audit</h2>
              <table className="t"><thead><tr><th>Rev</th><th>By</th><th>Field</th><th>Old → New</th><th>Time</th></tr></thead><tbody>
                {d.audit.map((a) => <tr key={a.id}><td>{String(a.rev).padStart(2, '0')}</td><td>{a.uname || 'System'}</td><td>{a.field || a.action.replace(/_/g, ' ')}</td><td>{(a.old_val ?? '—') + ' → ' + (a.new_val ?? '')}</td><td>{fdt(a.created_at)}</td></tr>)}</tbody></table>
              {d.versions.length > 1 && <p style={{ fontSize: 12 }}>Kept versions: {d.versions.map((v) => <a key={v} className="pill" target="_blank" rel="noreferrer" href={pdfUrl({ file: 'reports/' + v })}>{v}</a>)}</p>}</div>
          </>}
        </div>
      </div>
    </Page>; }}</Loader>;
}
