// Thin client for the PHP JSON API (same origin, session cookie + CSRF header)
// the ERP is built under /erp/ — the API lives one level up at /api/
const BASE = (import.meta.env.BASE_URL || '/').replace(/erp\/?$/, '').replace(/\/$/, '') + '/api/';
let csrf = '';
export const setCsrf = (t) => { csrf = t || csrf; };
export const apiUrl = (path, q) => BASE + path + (q ? '?' + new URLSearchParams(Object.entries(q).filter(([, v]) => v !== undefined && v !== null && v !== '')).toString() : '');

let onAuthLost = () => {};
export const setAuthLost = (fn) => { onAuthLost = fn; };

async function handle(r) {
  let j = null;
  try { j = await r.json(); } catch { j = { error: 'Server error (' + r.status + ')' }; }
  if (r.status === 401 && j && j.auth === false) onAuthLost();
  if (!r.ok) { const e = new Error(j?.error || 'Request failed'); e.status = r.status; e.data = j; throw e; }
  if (j && j.csrf) setCsrf(j.csrf);
  return j;
}
export async function get(path, q) {
  return handle(await fetch(apiUrl(path, q), { credentials: 'same-origin', headers: { Accept: 'application/json' } }));
}
export async function post(path, body = {}) {
  return handle(await fetch(apiUrl(path), { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF': csrf }, body: JSON.stringify(body) }));
}
/** multipart (file uploads) */
export async function upload(path, formData) {
  return handle(await fetch(apiUrl(path), { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF': csrf }, body: formData }));
}
export const fileUrl = (p, dl) => apiUrl('file', { p, dl: dl ? 1 : undefined });
export const pdfUrl = (q) => apiUrl('pdf', q);

/* ---------- inspector offline queue ---------- */
const QK = 'qt_queue_v2';
export const queue = {
  all: () => { try { return JSON.parse(localStorage.getItem(QK) || '[]'); } catch { return []; } },
  set: (q) => { try { localStorage.setItem(QK, JSON.stringify(q)); } catch { alert('Phone storage is full — please sync before taking more photos.'); } window.dispatchEvent(new Event('qt-queue')); },
};
/** send an inspection action; when offline (or network fails) it is queued and replayed later */
export async function inspectSend(payload) {
  if (!navigator.onLine) { queue.set([...queue.all(), payload]); return { queued: true }; }
  try { return await post('app/inspect', payload); }
  catch (e) { if (e.status) throw e; queue.set([...queue.all(), payload]); return { queued: true }; }
}
let flushing = false;
export async function flushQueue() {
  if (flushing || !navigator.onLine) return 0;
  const q = queue.all(); if (!q.length) return 0;
  flushing = true; const rest = [];
  for (const p of q) {
    try { await post('app/inspect', p); }
    catch (e) { if (!e.status || e.status >= 500) rest.push(p); }
  }
  queue.set(rest); flushing = false;
  return q.length - rest.length;
}
if (typeof window !== 'undefined') window.addEventListener('online', () => flushQueue());
