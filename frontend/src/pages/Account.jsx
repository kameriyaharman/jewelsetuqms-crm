import { Link } from 'react-router-dom';
import { useEffect } from 'react';
import { Page } from '../app/Layout';
import { useAuth } from '../app/auth';
import { post } from '../app/api';
import { Empty, Loader, useAction, useApi, useForm } from '../app/ui';
import { fdt } from '../app/util';

export function Notifications() {
  const q = useApi('notifications'); const { refresh } = useAuth();
  useEffect(() => { if (q.data) refresh(); /* eslint-disable-next-line */ }, [q.data]);
  return <Page title="Notifications"><Loader q={q}>{(d) => <div className="card">{!d.items.length && <Empty>No notifications yet.</Empty>}
    <div className="mlist">{d.items.map((n) => <Link key={n.id} to={'/' + (n.link || '')}><span><b style={{ fontWeight: +n.is_read ? 500 : 700 }}>{n.text}</b></span><small>{fdt(n.created_at)}</small></Link>)}</div></div>}</Loader></Page>;
}

export function PasswordForm({ mobile }) {
  const F = useForm({ current: '', new: '' }); const { run, busy } = useAction();
  return <form onSubmit={async (e) => { e.preventDefault(); if (await run(() => post('profile/password', F.f))) F.setF({ current: '', new: '' }); }}>
    <div className="f">{!mobile && <label>Current password</label>}<input type="password" required placeholder={mobile ? 'Current password' : ''} {...F.bind('current')} /></div>
    <div className="f">{!mobile && <label>New password</label>}<input type="password" required minLength="6" placeholder={mobile ? 'New password' : ''} {...F.bind('new')} /></div>
    <button className={mobile ? 'bigbtn alt' : 'btn pri'} disabled={busy}>Update password</button></form>;
}

export function Profile() {
  const q = useApi('profile');
  return <Page title="Profile"><Loader q={q}>{(d) => <div className="grid g2">
    <div className="card"><h2 className="mb">My account</h2>
      <div className="kv"><div><small>Name</small><b>{d.user.name}</b></div><div><small>Role</small><b>{d.user.role_label}</b></div><div><small>Email</small><b>{d.user.email || '—'}</b></div><div><small>Login code</small><b>{d.user.emp_code || '—'}</b></div></div>
      {d.vendor && <><div className="hr" /><h2 className="mb">Factory</h2><div className="kv"><div><small>Vendor</small><b>{d.vendor.name}</b></div><div><small>Address</small><b>{d.vendor.address}</b></div>
        <div><small>Contact</small><b>{d.vendor.contact}</b></div><div><small>Mapped clients</small><b>{d.vendor.clients.join(', ')}</b></div></div></>}
      {d.client && <><div className="hr" /><h2 className="mb">Company</h2><div className="kv"><div><small>Client</small><b>{d.client.name}</b></div><div><small>Head office email</small><b>{d.client.ho_email}</b></div></div></>}
    </div>
    <div className="card"><h2 className="mb">Change password</h2><PasswordForm /></div>
  </div>}</Loader></Page>;
}
