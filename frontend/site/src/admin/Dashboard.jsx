import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { BookOpen, Calendar, Download, FileDown, Inbox, Trophy } from 'lucide-react';
import { apiUrl, get } from '../api';
import { useAdmin } from './Admin';
import { LeadsTable } from './Leads';

const monthLabel = (m) => new Date(m + '-01T00:00:00').toLocaleDateString('en-GB', { month: 'short', year: 'numeric' });
const prevLabel = (m) => { const d = new Date(m + '-01T00:00:00'); d.setMonth(d.getMonth() - 1); return d.toLocaleDateString('en-GB', { month: 'short' }); };

export default function Dashboard() {
  const { me } = useAdmin(); const nav = useNavigate();
  const [month, setMonth] = useState(new Date().toISOString().slice(0, 7));
  const [d, setD] = useState(null);
  useEffect(() => { get('site/admin/dash', { month }).then(setD).catch(() => {}); }, [month]);
  const h = new Date().getHours(); const greet = h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening';
  const chg = (v) => v === null || v === undefined ? <small>— vs {prevLabel(month)}</small> : <small className={v >= 0 ? 'up' : 'dn'}>{v >= 0 ? '↑' : '↓'} {Math.abs(v)}% vs {prevLabel(month)}</small>;
  return <>
    <div className="atop"><div><h1 className="serif">{greet}, {me.user.name.split(' ')[0]}</h1><p>Here’s what came in {month === new Date().toISOString().slice(0, 7) ? 'this month' : 'in ' + monthLabel(month)}</p></div>
      <div className="acts">
        <label className="sbox"><Calendar /><input type="month" value={month} onChange={(e) => e.target.value && setMonth(e.target.value)} style={{ width: 140 }} /></label>
        <a className="btn btn-navy btn-sm" href={apiUrl('site/admin/leads.csv', { month })}><FileDown />Export CSV</a>
      </div></div>
    {d && d.mail === 'none' && <div className="notice">Email sending is <b>off</b> — new leads are saved here but no alert / acknowledgement emails go out. Turn it on in Q-Track ERP → Settings → Email.</div>}
    <div className="kpis">
      <div className="kpi"><div className="h">Audit leads<Inbox /></div><div className="v">{d ? d.leads : '…'}</div>{d && chg(d.leads_chg)}</div>
      <div className="kpi"><div className="h">PDF downloads<Download /></div><div className="v">{d ? d.downloads : '…'}</div>{d && chg(d.downloads_chg)}</div>
      <div className="kpi"><div className="h">Published articles<BookOpen /></div><div className="v">{d ? d.published : '…'}</div><small>{d ? d.drafts + ' draft' + (d.drafts === 1 ? '' : 's') : ''}</small></div>
      <div className="kpi"><div className="h">Won (converted)<Trophy /></div><div className="v">{d ? d.won : '…'}</div><small>{d ? d.conversion + '% conversion' : ''}</small></div>
    </div>
    <LeadsTable month={month} />
    {d && d.top_posts.length > 0 && <div className="card" style={{ marginTop: 20 }}>
      <div className="card-h"><h3>Most-read articles</h3><Link className="link-gold" to="/admin/blog">All articles</Link></div>
      <table className="tbl"><tbody>{d.top_posts.map((p) => <tr key={p.id} onClick={() => nav('/admin/blog/' + p.id)}>
        <td><b>{p.title}</b></td><td style={{ textAlign: 'right', color: 'var(--muted)' }}>{p.views} views</td></tr>)}</tbody></table></div>}
  </>;
}
