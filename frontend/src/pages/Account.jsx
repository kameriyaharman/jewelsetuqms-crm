import { Link } from 'react-router-dom';
import { useEffect, useRef, useState } from 'react';
import { Page } from '../app/Layout';
import { useAuth } from '../app/auth';
import { post, upload } from '../app/api';
import { Avatar, Empty, Loader, useAction, useApi, useForm, useToast } from '../app/ui';
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
    <div className="card"><ProfileHead /><div className="hr" /><h2 className="mb">My account</h2>
      <div className="kv"><div><small>Name</small><b>{d.user.name}</b></div><div><small>Role</small><b>{d.user.role_label}</b></div><div><small>Email</small><b>{d.user.email || '—'}</b></div><div><small>Login code</small><b>{d.user.emp_code || '—'}</b></div></div>
      {d.vendor && <><div className="hr" /><h2 className="mb">Factory</h2><div className="kv"><div><small>Vendor</small><b>{d.vendor.name}</b></div><div><small>Address</small><b>{d.vendor.address}</b></div>
        <div><small>Contact</small><b>{d.vendor.contact}</b></div><div><small>Mapped clients</small><b>{d.vendor.clients.join(', ')}</b></div></div></>}
      {d.client && <><div className="hr" /><h2 className="mb">Company</h2><div className="kv"><div><small>Client</small><b>{d.client.name}</b></div><div><small>Head office email</small><b>{d.client.ho_email}</b></div></div></>}
    </div>
    <div className="card"><h2 className="mb">Change password</h2><PasswordForm /></div>
  </div>}</Loader></Page>;
}

/** Big avatar + change / remove photo, then name and role. Used on web and app profile pages. */
export function ProfileHead() {
  const { refresh, user } = useAuth(); const toast = useToast(); const [busy, setBusy] = useState(false); const ref = useRef();
  const pick = async (file) => {
    if (!file) return; setBusy(true);
    try { const fd = new FormData(); fd.append('file', file); const r = await upload('profile/photo', fd); toast(r.message); await refresh(); } catch (e) { toast(e.message, 'err'); }
    setBusy(false); if (ref.current) ref.current.value = '';
  };
  const remove = async () => { setBusy(true); try { const r = await post('profile/photo', { remove: 1 }); toast(r.message); await refresh(); } catch (e) { toast(e.message, 'err'); } setBusy(false); };
  return <div className="profhead">
    <div className="profav"><Avatar name={user.name} photo={user.photo} size={88} />
      <button className="profcam" title="Change photo" disabled={busy} onClick={() => ref.current?.click()}>📷</button>
      <input ref={ref} type="file" accept="image/jpeg,image/png,image/webp" capture="user" hidden onChange={(e) => pick(e.target.files[0])} /></div>
    <b style={{ fontSize: 18 }}>{user.name}</b>
    <span className="rolechip">{user.role_label}</span>
    <small className="muted">{[user.emp_code, user.org, user.location].filter(Boolean).join(' · ')}</small>
    <div style={{ display: 'flex', gap: 8, justifyContent: 'center', marginTop: 6 }}><button className="btn sm" disabled={busy} onClick={() => ref.current?.click()}>{busy ? 'Uploading…' : user.photo ? 'Change photo' : 'Upload photo'}</button>
      {user.photo && <button className="btn sm" disabled={busy} onClick={remove}>Remove</button>}</div>
  </div>;
}
