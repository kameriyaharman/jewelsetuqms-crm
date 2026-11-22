import { Link } from 'react-router-dom';
import { Page } from '../app/Layout';
import { Badge, Empty, Loader, useApi, Thumb, Paged } from '../app/ui';
import { fdate, fdt, num, pct } from '../app/util';

export default function Review() {
  const q = useApi('review');
  return <Page title="Pending Review" sub="Submitted inspections waiting for QC manager review, approval or return">
    <Loader q={q}>{(d) => <>
      <div className="card">{!d.rows.length ? <Empty>Nothing waiting for review. 🎉</Empty> : <Paged rows={d.rows} per={25} label="inspections">{(pr) => <div className="tbl-wrap"><table className="t"><thead><tr><th>Job</th><th>SKU</th><th>Client</th><th>Vendor</th><th>Inspector</th><th className="num">Inspected</th><th className="num">Rej/Repair</th><th>System</th><th>Photos</th><th>Submitted</th><th>Status</th><th></th></tr></thead><tbody>
        {pr.map((r) => <tr key={r.id}><td className="mono"><Link to={'/request/' + r.jid}>{r.job_no}</Link></td><td><div className="skucell"><Thumb src={r.img} size={40} alt={r.sku} /><span className="mono strong">{r.sku}</span></div></td><td>{r.client_code}</td><td>{r.vendor_name}</td><td>{r.insp_code}</td>
          <td className="num">{num(r.inspected_qty)}</td><td className="num">{num(r.rejected_qty)} <small>({pct(r.rejected_qty, r.inspected_qty)})</small></td><td><Badge s={r.system_result || 'PENDING'} /></td><td>{r.nph}</td><td>{fdt(r.submitted_at)}</td>
          <td><Badge s={r.status} />{+r.return_count ? <small> ×{r.return_count}</small> : null}</td>
          <td><Link className={'btn sm ' + (r.status !== 'RETURNED' ? 'pri' : '')} to={'/review/' + r.id}>{r.status !== 'RETURNED' ? 'Review' : 'View'}</Link></td></tr>)}</tbody></table></div>}</Paged>}</div>
      {!!d.concessions.length && <div className="card"><h2 className="mb">Waiting for client concession decision</h2><table className="t"><thead><tr><th>Report</th><th>Client</th><th>Vendor</th><th>SKU</th><th>Reply by</th></tr></thead><tbody>
        {d.concessions.map((c) => <tr key={c.id}><td><Link className="mono" to={'/report/' + c.id}>{c.report_no}</Link></td><td>{c.client_code}</td><td>{c.vendor_name}</td><td><div className="skucell"><Thumb src={c.img} size={34} alt={c.sku} /><span className="mono">{c.sku}</span></div></td><td>{fdate(c.concession_due)}</td></tr>)}</tbody></table></div>}
    </>}</Loader>
  </Page>;
}
