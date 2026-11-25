import { useEffect, useRef, useState } from 'react';

/* Motion for the public website: custom cursor, scroll reveals, card spotlight, magnetic buttons,
   hero parallax, sticky-header shadow and count-up numbers. Everything is off for touch screens
   (cursor / magnet / parallax) and for visitors who ask their OS for reduced motion. */
const reduced = () => typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const finePointer = () => typeof window !== 'undefined' && window.matchMedia('(hover: hover) and (pointer: fine)').matches;

const HOVER = 'a, button, [role="button"], label.drop, .post, .feat, .gate, .chip, select, .opt, .tags button';
const TEXT = 'input:not([type=checkbox]):not([type=radio]):not([type=file]), textarea, [contenteditable="true"]';

export function Cursor() {
  const dot = useRef(null); const ring = useRef(null); const glow = useRef(null); const trail = useRef(null);
  const [on] = useState(() => finePointer() && !reduced());
  useEffect(() => {
    if (!on) return;
    document.documentElement.classList.add('has-cursor');
    const sparks = [...trail.current.children]; let si = 0;
    let x = innerWidth / 2, y = innerHeight / 2, rx = x, ry = y, gx = x, gy = y, lx = x, ly = y, speed = 0, raf = 0, shown = false;
    const spawn = (px, py, v) => { // a fading gold light particle at the pointer
      const el = sparks[si++ % sparks.length]; const s = 3 + Math.random() * 4 + Math.min(v, 30) / 8;
      el.style.cssText = `left:${px}px;top:${py}px;width:${s}px;height:${s}px;--dx:${(Math.random() - .5) * 26}px;--dy:${(Math.random() - .2) * 22}px`;
      el.classList.remove('go'); void el.offsetWidth; el.classList.add('go');
    };
    const move = (e) => {
      x = e.clientX; y = e.clientY;
      if (!shown) { shown = true; rx = gx = lx = x; ry = gy = ly = y; [dot, ring, glow].forEach((r) => r.current.classList.add('show')); }
      dot.current.style.transform = `translate3d(${x}px, ${y}px, 0)`;
      const d = Math.hypot(x - lx, y - ly); if (d > 9) { spawn(x, y, d); lx = x; ly = y; }
      const t = e.target instanceof Element ? e.target : null;
      const txt = t && t.closest(TEXT); const hov = !txt && t && t.closest(HOVER);
      ring.current.classList.toggle('hover', !!hov); ring.current.classList.toggle('text', !!txt); dot.current.classList.toggle('text', !!txt); glow.current.classList.toggle('hover', !!hov);
    };
    const loop = () => {
      rx += (x - rx) * 0.2; ry += (y - ry) * 0.2; gx += (x - gx) * 0.08; gy += (y - gy) * 0.08;
      speed += (Math.hypot(x - rx, y - ry) - speed) * 0.2;
      const st = Math.min(speed / 40, 1);
      ring.current.style.transform = `translate3d(${rx}px, ${ry}px, 0) scale(${1 + st * 0.25})`;
      glow.current.style.transform = `translate3d(${gx}px, ${gy}px, 0) scale(${0.8 + st * 0.7})`; glow.current.style.opacity = shown ? 0.45 + st * 0.55 : 0;
      raf = requestAnimationFrame(loop);
    };
    const down = () => { ring.current.classList.add('down'); for (let i = 0; i < 8; i++) spawn(x + (Math.random() - .5) * 20, y + (Math.random() - .5) * 20, 30); };
    const up = () => ring.current.classList.remove('down');
    const leave = () => { shown = false; [dot, ring, glow].forEach((r) => r.current.classList.remove('show')); };
    addEventListener('pointermove', move, { passive: true }); addEventListener('pointerdown', down); addEventListener('pointerup', up);
    document.addEventListener('mouseleave', leave); raf = requestAnimationFrame(loop);
    return () => { cancelAnimationFrame(raf); removeEventListener('pointermove', move); removeEventListener('pointerdown', down); removeEventListener('pointerup', up);
      document.removeEventListener('mouseleave', leave); document.documentElement.classList.remove('has-cursor'); };
  }, [on]);
  if (!on) return null;
  return <>
    <div ref={glow} className="cur-glow" aria-hidden="true" />
    <div ref={trail} className="cur-trail" aria-hidden="true">{Array.from({ length: 22 }, (_, i) => <i key={i} />)}</div>
    <div ref={ring} className="cur-ring" aria-hidden="true" /><div ref={dot} className="cur-dot" aria-hidden="true" />
  </>;
}

/* elements that fade / slide in when scrolled into view (siblings get a small stagger) */
const REVEAL = ['.sec-head', '.trace-head', '.pcard', '.acard', '.post', '.step', '.lot', '.tcard', '.navy-card', '.score', '.checks2 li', '.gate', '.gate-note',
  '.mcard', '.role', '.timeline li', '.cert', '.cta-band', '.feat', '.side-pdf', '.side-box', '.svc-title', '.svc-text', '.phero h1', '.phero .chips', '.qt-hero h1',
  '.qt-hero p', '.qt-badge', '.ab-hero h1', '.ab-hero p', '.portrait', '.stats > div', '.hero .ctas', '.hero .cities', '.hero p.lead', '.hero .eyebrow',
  '.prose > div > *', '[data-reveal]', '.magnet', '.toc', '.related .posts', '.verify', '.ftr .cols > div'].join(',');
const SPOT = '.pcard, .post, .role, .tcard, .acard, .cert, .mcard, .feat, .score, .lot';
const MAGNET = '.btn-gold, .btn-navy, .wa-fab, .hdr-cta .btn';

export function useMotion(key) {
  // scroll reveal
  useEffect(() => {
    if (reduced() || !('IntersectionObserver' in window)) return;
    const io = new IntersectionObserver((es) => es.forEach((e) => { if (e.isIntersecting) { e.target.setAttribute('data-in', ''); io.unobserve(e.target); } }), { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    const scan = () => document.querySelectorAll(REVEAL).forEach((el) => {
      if (el.dataset.rv !== undefined || el.closest('.ovl, .mmenu')) return;
      const sib = el.parentElement ? [...el.parentElement.children].filter((c) => c.matches(REVEAL)) : [];
      el.dataset.rv = ''; el.style.setProperty('--d', Math.min(sib.indexOf(el), 6) * 85 + 'ms');
      io.observe(el);
    });
    scan();
    const mo = new MutationObserver(() => scan()); const main = document.querySelector('main');
    if (main) mo.observe(main, { childList: true, subtree: true });
    return () => { io.disconnect(); mo.disconnect(); };
  }, [key]);

  // header shadow, card spotlight, magnetic buttons, hero parallax
  useEffect(() => {
    const hdr = document.querySelector('.hdr');
    const onScroll = () => hdr && hdr.classList.toggle('scrolled', scrollY > 8);
    onScroll(); addEventListener('scroll', onScroll, { passive: true });
    if (reduced() || !finePointer()) return () => removeEventListener('scroll', onScroll);
    let mag = null;
    const move = (e) => {
      const t = e.target instanceof Element ? e.target : null; if (!t) return;
      const c = t.closest(SPOT);
      if (c) { const r = c.getBoundingClientRect(); c.style.setProperty('--mx', e.clientX - r.left + 'px'); c.style.setProperty('--my', e.clientY - r.top + 'px'); }
      const m = t.closest(MAGNET);
      if (mag && mag !== m) { mag.style.translate = ''; mag = null; }
      if (m && !m.closest('.ovl')) { const r = m.getBoundingClientRect(); mag = m;
        m.style.translate = `${((e.clientX - r.left) / r.width - 0.5) * 10}px ${((e.clientY - r.top) / r.height - 0.5) * 8}px`; }
      const hero = t.closest('.hero');
      if (hero) { const r = hero.getBoundingClientRect(); hero.style.setProperty('--px', ((e.clientX - r.left) / r.width - 0.5).toFixed(3)); hero.style.setProperty('--py', ((e.clientY - r.top) / r.height - 0.5).toFixed(3)); }
    };
    addEventListener('pointermove', move, { passive: true });
    return () => { removeEventListener('scroll', onScroll); removeEventListener('pointermove', move); };
  }, []);
}

/** counts a number up from 0 when it scrolls into view ("500" → 0…500); non-numbers are shown as-is */
export function CountUp({ value }) {
  const ref = useRef(null); const n = /^\d+$/.test(String(value)) ? Number(value) : null;
  const [v, setV] = useState(n === null || reduced() ? value : 0);
  useEffect(() => {
    if (n === null || reduced() || !ref.current) return;
    let raf = 0; const io = new IntersectionObserver(([e]) => {
      if (!e.isIntersecting) return; io.disconnect(); const t0 = performance.now(), dur = 1500;
      const tick = (t) => { const p = Math.min(1, (t - t0) / dur); setV(Math.round(n * (1 - Math.pow(1 - p, 3)))); if (p < 1) raf = requestAnimationFrame(tick); };
      raf = requestAnimationFrame(tick);
    }, { threshold: 0.4 });
    io.observe(ref.current); return () => { io.disconnect(); cancelAnimationFrame(raf); };
  }, [n]);
  return <span ref={ref}>{v}</span>;
}
