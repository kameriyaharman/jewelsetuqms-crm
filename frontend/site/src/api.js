// JSON API client for the website + admin (same origin as the PHP API at /api/)
export const ROOT = (import.meta.env.BASE_URL || '/').replace(/\/$/, '');
const BASE = ROOT + '/api/';
let csrf = '';
export const setCsrf = (t) => { if (t) csrf = t; };
export const apiUrl = (path, q) => BASE + path + (q ? '?' + new URLSearchParams(Object.entries(q).filter(([, v]) => v !== undefined && v !== null && v !== '')).toString() : '');
async function handle(r) {
  let j = null;
  try { j = await r.json(); } catch { j = { error: 'Server error (' + r.status + ')' }; }
  if (!r.ok) { const e = new Error(j?.error || 'Request failed'); e.status = r.status; e.data = j; throw e; }
  if (j && j.csrf) setCsrf(j.csrf);
  return j;
}
export const get = async (path, q) => handle(await fetch(apiUrl(path, q), { credentials: 'same-origin', headers: { Accept: 'application/json' } }));
export const post = async (path, body = {}) => handle(await fetch(apiUrl(path), { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF': csrf }, body: JSON.stringify(body) }));
/** multipart POST with upload progress (0–100) */
export function upload(path, fd, onProgress) {
  return new Promise((ok, no) => {
    const x = new XMLHttpRequest();
    x.open('POST', apiUrl(path)); x.withCredentials = true; x.setRequestHeader('X-CSRF', csrf);
    if (onProgress) x.upload.onprogress = (e) => e.lengthComputable && onProgress(Math.round((e.loaded * 100) / e.total));
    x.onload = () => { let j; try { j = JSON.parse(x.responseText); } catch { j = { error: 'Server error (' + x.status + ')' }; }
      if (x.status >= 200 && x.status < 300) ok(j); else { const e = new Error(j.error || 'Upload failed'); e.status = x.status; no(e); } };
    x.onerror = () => no(new Error('Network error — check your connection.'));
    x.send(fd);
  });
}
