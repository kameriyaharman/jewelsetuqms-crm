import { Link } from 'react-router-dom';
import { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react';
import { fileUrl, get } from './api';
import { JOB_STATUS, ITEM_STATUS, num } from './util';

const P = {
  grid: <><rect x="3" y="3" width="7" height="7" rx="1.5" /><rect x="14" y="3" width="7" height="7" rx="1.5" /><rect x="3" y="14" width="7" height="7" rx="1.5" /><rect x="14" y="14" width="7" height="7" rx="1.5" /></>,
  building: <><path d="M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16" /><path d="M16 9h2a2 2 0 0 1 2 2v10" /><path d="M8 7h4M8 11h4M8 15h4M3 21h18" /></>,
  factory: <><path d="M3 21V10l6 4V10l6 4V6l6 3v12z" /><path d="M3 21h18" /></>,
  link: <><path d="M10 14a4 4 0 0 0 5.66 0l3-3a4 4 0 0 0-5.66-5.66l-1 1" /><path d="M14 10a4 4 0 0 0-5.66 0l-3 3a4 4 0 0 0 5.66 5.66l1-1" /></>,
  inbox: <><path d="M4 13h4l2 3h4l2-3h4" /><path d="M5 5h14l2 8v6H3v-6z" /></>,
  upload: <><path d="M12 16V4M7 9l5-5 5 5" /><path d="M4 16v4h16v-4" /></>,
  clip: <><rect x="5" y="4" width="14" height="17" rx="2" /><path d="M9 4V2h6v2M9 10h6M9 14h6M9 18h3" /></>,
  cal: <><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M3 10h18M8 3v4M16 3v4" /></>,
  eye: <><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z" /><circle cx="12" cy="12" r="3" /></>,
  file: <><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z" /><path d="M14 3v6h6M8 13h8M8 17h5" /></>,
  chart: <path d="M4 20V10M10 20V4M16 20v-7M22 20H2" />,
  users: <><circle cx="9" cy="8" r="3.5" /><path d="M2 20c0-3.5 3-6 7-6s7 2.5 7 6" /><path d="M16 4.5a3.5 3.5 0 0 1 0 7M22 20c0-3-2-5.2-5-5.8" /></>,
  cog: <><circle cx="12" cy="12" r="3" /><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1" /></>,
  plus: <path d="M12 5v14M5 12h14" />,
  check: <path d="M20 6 9 17l-5-5" />,
  pause: <><rect x="6" y="4" width="4" height="16" rx="1" /><rect x="14" y="4" width="4" height="16" rx="1" /></>,
  user: <><circle cx="12" cy="8" r="4" /><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7" /></>,
  bell: <><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" /><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0" /></>,
  logout: <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" />,
  home: <path d="M3 11 12 3l9 8v10h-6v-6H9v6H3z" />,
  scan: <path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2M7 12h10" />,
  menu: <path d="M3 6h18M3 12h18M3 18h18" />,
  gem: <><path d="M6 3h12l3 6-9 12L3 9z" /><path d="M3 9h18M9 3l3 6 3-6M12 21 9 9M12 21l3-12" /></>,
  cards: <><rect x="3" y="3" width="8" height="8" rx="1.5" /><rect x="13" y="3" width="8" height="8" rx="1.5" /><rect x="3" y="13" width="8" height="8" rx="1.5" /><rect x="13" y="13" width="8" height="8" rx="1.5" /></>,
  rows: <><path d="M4 6h16M4 12h16M4 18h16" /></>,
  box: <><path d="M21 8 12 3 3 8v8l9 5 9-5z" /><path d="M3 8l9 5 9-5M12 13v8" /></>,
  x: <path d="M18 6 6 18M6 6l12 12" />,
  alert: <><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z" /><path d="M12 9v4M12 17h.01" /></>,
  clock: <><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></>,
  download: <path d="M12 4v12M7 11l5 5 5-5M4 20h16" />,
  mail: <><rect x="3" y="5" width="18" height="14" rx="2" /><path d="m3 7 9 6 9-6" /></>,
  camera: <><path d="M3 8a2 2 0 0 1 2-2h2l2-2h6l2 2h2a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" /><circle cx="12" cy="13" r="4" /></>,
};
export function Icon({ n, size }) {
  return <svg className="ic" style={size ? { width: size, height: size } : null} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">{P[n]}</svg>;
}
export function Badge({ s, label }) {
  if (!s) return <span className="muted">—</span>;
  const cls = String(s).toLowerCase().replace(/[_ &]+/g, '-');
  const l = label ?? JOB_STATUS[s] ?? ITEM_STATUS[s] ?? String(s).replace(/_/g, ' ');
  return <span className={'badge b-' + cls}>{String(l).toUpperCase()}</span>;
}
export const Result = ({ r }) => <Badge s={r} label={r === 'CONDITIONAL' ? 'Conditional' : r} />;
export function Kpi({ label, value, sub, tone = '', icon = 'box', to }) {
  const inner = <><div className="kic"><Icon n={icon} /></div><div><small>{label}</small><b>{value}</b>{sub ? <span>{sub}</span> : null}</div></>;
  return to ? <Link to={to} className={'kpi click ' + tone}>{inner}</Link> : <div className={'kpi ' + tone}>{inner}</div>;
}
export function Bars({ data, suffix = '%' }) {
  const vals = Object.values(data || {}); const max = Math.max(0.0001, ...vals);
  return <div className="vbars">{Object.entries(data || {}).map(([k, v]) => (
    <div className="vb" key={k}><div className="vbar" style={{ height: Math.max(3, Math.round((v / max) * 100)) + '%' }}><span>{typeof v === 'number' && !Number.isInteger(v) ? v.toFixed(1) : num(v)}{suffix}</span></div><small>{k}</small></div>))}</div>;
}
export function HBars({ data, suffix = '' }) {
  const e = Object.entries(data || {}); const max = Math.max(0.0001, ...e.map(([, v]) => v));
  return <div className="hbars">{e.map(([k, v]) => <div className="hb" key={k}><span>{k}</span><div><i style={{ width: Math.max(2, Math.round((v / max) * 100)) + '%' }} /></div><b>{Number.isInteger(v) ? num(v) : Number(v).toFixed(1)}{suffix}</b></div>)}</div>;
}
export const Empty = ({ children }) => <div className="empty">{children}</div>;
export const Spinner = ({ text = 'Loading…' }) => <div className="spin">{text}</div>;
export function Sel({ value, onChange, options, blank, className, ...rest }) {
  return <select value={value ?? ''} onChange={(e) => onChange(e.target.value)} className={className} {...rest}>
    {blank !== undefined && <option value="">{blank}</option>}
    {options.map((o) => Array.isArray(o) ? <option key={o[0]} value={o[0]}>{o[1]}</option> : <option key={o} value={o}>{o}</option>)}
  </select>;
}
export function Modal({ open, title, onClose, children, wide }) {
  const ref = useRef();
  useEffect(() => { const d = ref.current; if (!d) return; if (open && !d.open) d.showModal(); if (!open && d.open) d.close(); }, [open]);
  return <dialog ref={ref} onClose={onClose} style={wide ? { width: 'min(760px,94vw)' } : null}>
    <div className="dh"><b>{title}</b><button className="btn sm" onClick={onClose}>✕</button></div><div className="db">{open && children}</div></dialog>;
}
export function Flow({ steps, current, extra }) {
  const ci = steps.indexOf(current);
  return <div className="flow">{steps.map((s, k) => <span key={s} className={ci < 0 ? '' : k < ci ? 'done' : k === ci ? 'cur' : ''}>{k + 1}. {JOB_STATUS[s] || s}</span>)}{extra}</div>;
}

/* ---------- toasts ---------- */
const ToastCtx = createContext(() => {});
export function ToastProvider({ children }) {
  const [list, setList] = useState([]);
  const push = useCallback((msg, type = 'ok') => {
    const id = Math.random(); setList((l) => [...l, { id, msg, type }]);
    setTimeout(() => setList((l) => l.filter((x) => x.id !== id)), type === 'err' ? 7000 : 4000);
  }, []);
  return <ToastCtx.Provider value={push}>{children}<div className="toast">{list.map((t) => <div key={t.id} className={t.type}>{t.msg}</div>)}</div></ToastCtx.Provider>;
}
export const useToast = () => useContext(ToastCtx);

/* ---------- data hook ---------- */
export function useApi(path, params, deps = []) {
  const [data, setData] = useState(null); const [error, setError] = useState(null); const [loading, setLoading] = useState(true);
  const key = JSON.stringify(params || {});
  const load = useCallback(async () => {
    setLoading(true); setError(null);
    try { setData(await get(path, params)); } catch (e) { setError(e.message); }
    setLoading(false);
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [path, key, ...deps]);
  useEffect(() => { load(); }, [load]);
  return { data, error, loading, reload: load, setData };
}
export function Loader({ q, children }) {
  if (q.error) return <div className="card"><div className="flash f-err">{q.error}</div></div>;
  if (!q.data) return <Spinner />;
  return children(q.data);
}

/* ---------- simple form state ---------- */
export function useForm(init) {
  const [f, setF] = useState(init);
  const bind = (k) => ({ value: f[k] ?? '', onChange: (e) => setF((x) => ({ ...x, [k]: e.target.type === 'checkbox' ? e.target.checked : e.target.value })) });
  const set = (k, v) => setF((x) => ({ ...x, [k]: v }));
  return { f, setF, bind, set };
}
/** run an async action with toast feedback; returns result or null */
export function useAction() {
  const toast = useToast(); const [busy, setBusy] = useState(false);
  const run = async (fn, okMsg) => {
    setBusy(true);
    try { const r = await fn(); if (r?.message || okMsg) toast(r?.message || okMsg, r?.ok === false ? 'err' : 'ok'); setBusy(false); return r || true; }
    catch (e) { toast(e.message, 'err'); setBusy(false); return null; }
  };
  return { run, busy };
}
export function Multi({ value = [], onChange, options, size = 5 }) {
  return <select multiple size={size} value={value.map(String)} onChange={(e) => onChange(Array.from(e.target.selectedOptions).map((o) => Number(o.value)))}>
    {options.map(([v, l]) => <option key={v} value={v}>{l}</option>)}</select>;
}

/** Product picture thumbnail; click opens it large. `src` is an upload path from the API (img field). */
export function Thumb({ src, size = 44, alt = '', style }) {
  const [open, setOpen] = useState(false);
  const box = { width: size, height: size, ...style };
  if (!src) return <span className="thumb none" style={box} title="No product image"><Icon n="gem" size={Math.max(14, size * 0.42)} /></span>;
  const u = fileUrl(src);
  return <>
    <img className="thumb" src={u} alt={alt} loading="lazy" style={box} onClick={(e) => { e.preventDefault(); e.stopPropagation(); setOpen(true); }} />
    {open && <div className="lightbox" onClick={() => setOpen(false)}><img src={u} alt={alt} /><span>{alt}</span></div>}
  </>;
}

/** 4-state inspector status: AVAILABLE / ASSIGNED (en-route) / ON-SITE / OFF-LEAVE. */
export function InspSt({ st, small }) {
  if (!st) return null;
  const c = { AVAILABLE: ['#e3f4ea', '#1d7a4b', '🟢'], ASSIGNED: ['#fff5d6', '#8a6a10', '🟡'], ONSITE: ['#e1effa', '#1f5f99', '🔵'], LEAVE: ['#eceef1', '#4b5560', '⚫'] }[st.code] || ['#eee', '#333', ''];
  return <span className="ist" title={st.sub} style={{ background: c[0], color: c[1], fontSize: small ? 10.5 : 11.5 }}>{c[2]} {st.label}</span>;
}
