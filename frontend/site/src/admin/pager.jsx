import { useEffect, useState } from 'react';
/* Pagination for the website admin + Knowledge Hub lists */
export function usePaged(rows, per0 = 25, resetKey) {
  const list = rows || []; const [page, setPage] = useState(1); const [per, setPer] = useState(per0);
  useEffect(() => { setPage(1); }, [list.length, resetKey]);
  const pages = Math.max(1, Math.ceil(list.length / per)); const p = Math.min(page, pages);
  return { rows: list.slice((p - 1) * per, p * per), page: p, pages, total: list.length, per, setPage, setPer: (n) => { setPer(n); setPage(1); },
    from: list.length ? (p - 1) * per + 1 : 0, to: Math.min(p * per, list.length) };
}
const nums = (p, n) => { const a = [...new Set([1, n, p - 1, p, p + 1].filter((x) => x >= 1 && x <= n))].sort((x, y) => x - y); const o = [];
  a.forEach((x, i) => { if (i && x - a[i - 1] > 1) o.push('…' + x); o.push(x); }); return o; };
export function Pager({ pg, label = 'rows', perOptions = [10, 25, 50, 100], simple, onPage }) {
  if (!pg || pg.total <= (simple ? pg.per : perOptions[0])) return null;
  const go = (x) => { pg.setPage(x); onPage && onPage(x); };
  return <div className={'pager' + (simple ? ' simple' : '')}>
    {!simple && <span className="pg-info">Showing <b>{pg.from}–{pg.to}</b> of <b>{pg.total}</b> {label}</span>}
    {!simple && <select className="pg-per" value={pg.per} onChange={(e) => pg.setPer(+e.target.value)}>{perOptions.map((n) => <option key={n} value={n}>{n} / page</option>)}</select>}
    {pg.pages > 1 && <span className="pg-btns">
      <button disabled={pg.page <= 1} onClick={() => go(pg.page - 1)} aria-label="Previous page">‹ Prev</button>
      {nums(pg.page, pg.pages).map((x) => (typeof x === 'string' ? <i key={x}>…</i> : <button key={x} className={x === pg.page ? 'on' : ''} onClick={() => go(x)}>{x}</button>))}
      <button disabled={pg.page >= pg.pages} onClick={() => go(pg.page + 1)} aria-label="Next page">Next ›</button>
    </span>}
  </div>;
}
