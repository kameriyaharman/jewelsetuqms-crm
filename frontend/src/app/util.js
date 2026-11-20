export const ROLES = { super_admin: 'Super Admin', back_office: 'Back Office', qc_manager: 'QC Manager', inspector: 'QC Inspector', client: 'Client User', vendor: 'Vendor User' };
export const INTERNAL = ['super_admin', 'back_office', 'qc_manager'];
export const JOB_STATUS = { DRAFT: 'Draft', REQUESTED: 'Requested', SCHEDULED: 'Scheduled', ASSIGNED: 'Assigned', IN_PROGRESS: 'In progress', SUBMITTED: 'Submitted',
  UNDER_REVIEW: 'Under review', RETURNED: 'Returned', APPROVED: 'Approved', PARTIAL: 'Partial QC done (open)', EMAILED: 'PDF & emailed', CLOSED: 'Closed', CANCELLED: 'Cancelled' };
export const ITEM_STATUS = { PENDING: 'Pending', IN_PROGRESS: 'In progress', HOLD: 'On hold', COMPLETED: 'Completed', SUBMITTED: 'Submitted', UNDER_REVIEW: 'Under review', RETURNED: 'Returned', APPROVED: 'Approved' };
export const STEPS = ['General info', 'Quantity', 'Quality checks', 'Measurements', 'On-site tests', 'Defects', 'Photos', 'Final result'];

/** Indian grouping 1,25,000 */
export function num(n) {
  const v = Math.round(Number(n) || 0); const neg = v < 0; let s = String(Math.abs(v));
  if (s.length > 3) { const last = s.slice(-3); let rest = s.slice(0, -3); rest = rest.replace(/\B(?=(\d{2})+(?!\d))/g, ','); s = rest + ',' + last; }
  return (neg ? '-' : '') + s;
}
export const pct = (a, b, dp = 1) => (b > 0 ? ((a * 100) / b).toFixed(dp) + '%' : '0%');
const D = (d) => (!d ? null : d instanceof Date ? d : new Date(String(d).length === 10 ? d + 'T00:00:00' : String(d).replace(' ', 'T')));
const M = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
export function fdate(d, f = 'd M Y') {
  const x = D(d); if (!x || isNaN(x)) return '—';
  const dd = String(x.getDate()).padStart(2, '0');
  if (f === 'd M') return `${dd} ${M[x.getMonth()]}`;
  if (f === 'd-m-Y') return `${dd}-${String(x.getMonth() + 1).padStart(2, '0')}-${x.getFullYear()}`;
  if (f === 'D d') return `${['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][x.getDay()]} ${dd}`;
  return `${dd} ${M[x.getMonth()]} ${x.getFullYear()}`;
}
export function fdt(d) {
  const x = D(d); if (!x || isNaN(x)) return '—';
  let h = x.getHours(); const ap = h >= 12 ? 'PM' : 'AM'; h = h % 12 || 12;
  return `${String(x.getDate()).padStart(2, '0')} ${M[x.getMonth()]} · ${String(h).padStart(2, '0')}:${String(x.getMinutes()).padStart(2, '0')} ${ap}`;
}
export const initials = (n = '') => (n.trim().split(/\s+/).slice(0, 2).map((p) => p[0] || '').join('').toUpperCase() || '?');
/** Local calendar date (YYYY-MM-DD) — not UTC, so IST mornings before 5:30 stay on the right day. */
export const ymd = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
export const today = () => ymd(new Date());
export const addDays = (n) => { const d = new Date(); d.setDate(d.getDate() + n); return ymd(d); };
export function scopeLabel(j) {
  if (!j) return '';
  if (j.scope === 'SAMPLE') return 'Sample QC';
  if (j.bulk_mode === 'AQL') return `Bulk · AQL ${j.insp_level || 'II'} ${Number(j.aql_major)}`;
  if (j.bulk_mode === 'DEFINED') return `Bulk · Defined ${j.defined_qty}`;
  return 'Bulk · 100%';
}

/* ---------- ISO 2859-1 (mirror of server/app/aql.php) ---------- */
const L = 'ABCDEFGHJKLMNPQR'.split(''), S = [2, 3, 5, 8, 13, 20, 32, 50, 80, 125, 200, 315, 500, 800, 1250, 2000];
const AV = [0.010, 0.015, 0.025, 0.040, 0.065, 0.10, 0.15, 0.25, 0.40, 0.65, 1.0, 1.5, 2.5, 4.0, 6.5, 10], AC = [0, 1, 2, 3, 5, 7, 10, 14, 21];
const CT = [[2, 8, 'AAAAAAB'], [9, 15, 'AAAAABC'], [16, 25, 'AABBBCD'], [26, 50, 'ABBCCDE'], [51, 90, 'BBCCCEF'], [91, 150, 'BBCDDFG'], [151, 280, 'BCDEEGH'],
  [281, 500, 'BCDEFHJ'], [501, 1200, 'CCEFGJK'], [1201, 3200, 'CDEGHKL'], [3201, 10000, 'CDFGJLM'], [10001, 35000, 'CDFHKMN'], [35001, 150000, 'DEGJLNP'], [150001, 500000, 'DEGJMPQ'], [500001, 1e12, 'DEHKNQR']];
function code(lot, lv) { const c = { S1: 0, S2: 1, S3: 2, S4: 3, I: 4, II: 5, III: 6 }[lv] ?? 5; for (const [a, b, s] of CT) if (lot >= a && lot <= b) return s[c]; return 'A'; }
function single(letter, aql) {
  let j = AV.findIndex((v) => Math.abs(v - aql) < 1e-9); if (j < 0) j = 12;
  let i = L.indexOf(letter);
  for (let g = 0; g < 20; g++) {
    const s = i + j;
    if (s < 14) i = 14 - j; else if (s === 15) i -= 1; else if (s === 16) i += 1; else if (s > 24) i = 24 - j;
    else { if (i < 0 || i > 15) return null; const ac = s === 14 ? 0 : AC[s - 16]; return { letter: L[i], sample: S[i], ac, re: ac + 1 }; }
    if (i < 0 || i > 15) return null;
  }
  return null;
}
export function aqlPlan(lot, lv, cr, ma, mi) {
  const letter = code(Math.max(2, lot), lv), out = { letter, sample: 0 };
  for (const [k, v] of [['critical', cr], ['major', ma], ['minor', mi]]) {
    if (!(v > 0)) { out[k] = null; continue; }
    const p = single(letter, v) || { letter, sample: lot, ac: 0, re: 1 }; out[k] = p; out.sample = Math.max(out.sample, p.sample);
  }
  if (!out.sample) out.sample = S[L.indexOf(letter)];
  if (out.sample >= lot) { out.sample = lot; out.full = true; }
  return out;
}
export const AQL_OPTIONS = [['0', 'Not allowed (0)'], ['0.065', '0.065'], ['0.1', '0.10'], ['0.15', '0.15'], ['0.25', '0.25'], ['0.4', '0.40'], ['0.65', '0.65'], ['1', '1.0'], ['1.5', '1.5'], ['2.5', '2.5'], ['4', '4.0'], ['6.5', '6.5']];

/** compress a camera photo to a JPEG data URL */
export function compressImage(file, max = 1400, q = 0.72) {
  return new Promise((res, rej) => {
    const fr = new FileReader(); fr.onerror = rej;
    fr.onload = () => { const img = new Image(); img.onerror = rej; img.onload = () => {
      let { width: w, height: h } = img; const sc = Math.min(1, max / Math.max(w, h)); w = Math.round(w * sc); h = Math.round(h * sc);
      const c = document.createElement('canvas'); c.width = w; c.height = h; c.getContext('2d').drawImage(img, 0, 0, w, h); res(c.toDataURL('image/jpeg', q)); };
      img.src = fr.result; };
    fr.readAsDataURL(file);
  });
}
