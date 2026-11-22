import { createContext, useCallback, useContext, useEffect, useState } from 'react';
import { get } from './api';

/* Website content (editable in /admin) — bootstrapped by index.php as window.__SITE__, else fetched. */
const Ctx = createContext(null);
export function SiteProvider({ children }) {
  const [site, setSite] = useState(() => (window.__SITE__ && window.__SITE__.content ? window.__SITE__ : null));
  const [book, setBook] = useState(null);       // Book-an-Audit modal: null | { service? }
  const [report, setReport] = useState(false);  // sample QC certificate modal
  const [toast, setToastS] = useState('');
  const reload = useCallback(() => get('site/pub/content').then(setSite).catch(() => {}), []);
  useEffect(() => { if (!site) reload(); }, []); // eslint-disable-line
  const toastMsg = useCallback((m) => { setToastS(m); setTimeout(() => setToastS(''), 2600); }, []);
  return <Ctx.Provider value={{ site, C: site?.content, reload, book, openBook: (o = {}) => setBook(o), closeBook: () => setBook(null), report, setReport, toast: toastMsg }}>
    {children}{toast && <div className="toast">{toast}</div>}
  </Ctx.Provider>;
}
export const useSite = () => useContext(Ctx);

/** Title text with *gold italic* parts */
export function T({ text = '', as: As = 'span', className, br }) {
  const parts = String(text).split(/(\*[^*]+\*)/g).filter(Boolean);
  return <As className={className}>{parts.map((p, i) => p.startsWith('*') && p.endsWith('*')
    ? <em key={i} className="g">{br && i > 0 ? <br /> : null}{p.slice(1, -1)}</em> : <span key={i}>{p}</span>)}</As>;
}
export const plain = (s) => String(s || '').replace(/\*/g, '');
export const media = (p) => !p ? '' : /^(https?:)?\/\//.test(p) || p.startsWith('/') ? p : (import.meta.env.BASE_URL || '/') + 'api/site/pub/media?p=' + encodeURIComponent(p);
export const waLink = (C, text) => 'https://wa.me/' + String(C?.brand?.whatsapp || '').replace(/\D/g, '') + '?text=' + encodeURIComponent(text || C?.brand?.whatsapp_text || '');
export const telLink = (p) => 'tel:' + String(p || '').replace(/[^\d+]/g, '');
export const fmtDate = (d, o = { day: 'numeric', month: 'short', year: 'numeric' }) => { if (!d) return ''; const x = new Date(String(d).replace(' ', 'T')); return isNaN(x) ? '' : x.toLocaleDateString('en-GB', o); };
export const monYear = (d) => fmtDate(d, { month: 'short', year: 'numeric' });
export const initials = (n) => String(n || '').replace(/\*/g, '').split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
export const useTitle = (t) => useEffect(() => { if (t) document.title = t; }, [t]);
