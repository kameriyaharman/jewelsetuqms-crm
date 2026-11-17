import { createContext, useCallback, useContext, useEffect, useState } from 'react';
import { get, post, setAuthLost, setCsrf } from './api';

const AuthCtx = createContext(null);
export function AuthProvider({ children }) {
  const [me, setMe] = useState(null); // null = loading, {auth:false} = logged out
  const refresh = useCallback(async () => {
    try { const m = await get('me'); setCsrf(m.csrf); setMe(m); return m; } catch (e) { setMe({ auth: false, offline: !e.status }); return null; }
  }, []);
  useEffect(() => { refresh(); setAuthLost(() => setMe((m) => (m && m.auth ? { auth: false } : m))); }, [refresh]);
  const login = async (login, password) => { const m = await post('auth/login', { login, password }); setCsrf(m.csrf); setMe(m); return m; };
  const logout = async () => { try { await post('auth/logout'); } catch { /* ignore */ } setMe({ auth: false }); };
  return <AuthCtx.Provider value={{ me, user: me?.user, refresh, login, logout }}>{children}</AuthCtx.Provider>;
}
export const useAuth = () => useContext(AuthCtx);
export const homeFor = (role) => ({ inspector: '/app', client: '/client', vendor: '/vendor' }[role] || '/dashboard');
