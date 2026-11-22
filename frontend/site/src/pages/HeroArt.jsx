import { useEffect, useRef, useState } from 'react';

/* Fully animated hero illustration: precision dial (rotating ticks, orbiting dots, marching crosshair),
   a brilliant-cut diamond that draws itself, turns, glints facet by facet and gets a light sweep,
   a QC scan line, sparkles, rising gold particles and four live badges (pop-in, float, count-up). */

const D = { // diamond geometry (160 × 120 units)
  outline: '4,36 32,6 128,6 156,36 80,116',
  crown: ['32,6 4,36 50,36', '32,6 50,36 80,6', '80,6 50,36 110,36', '80,6 110,36 128,6', '128,6 110,36 156,36'],
  pav: ['4,36 50,36 80,116', '50,36 110,36 80,116', '110,36 156,36 80,116'],
  lines: ['M4 36 32 6h96l28 30-76 80z', 'M4 36h152', 'M32 6l18 30 30-30 30 30 18-30', 'M50 36l30 80 30-80'],
};
const reduced = () => typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/** counts up the longest number inside a text, e.g. "18K · 750 ✓ verified" → 0…750 */
function CountText({ text = '', delay = 0 }) {
  const m = [...String(text).matchAll(/\d[\d,]*/g)].sort((a, b) => b[0].length - a[0].length)[0];
  const target = m ? Number(m[0].replace(/,/g, '')) : null;
  const [v, setV] = useState(target === null || reduced() ? target : 0);
  useEffect(() => {
    if (target === null || reduced()) return;
    let raf = 0; const t = setTimeout(() => { const t0 = performance.now();
      const tick = (now) => { const p = Math.min(1, (now - t0) / 1400); setV(Math.round(target * (1 - Math.pow(1 - p, 3)))); if (p < 1) raf = requestAnimationFrame(tick); };
      raf = requestAnimationFrame(tick); }, delay);
    return () => { clearTimeout(t); cancelAnimationFrame(raf); };
  }, [target, delay]);
  if (!m) return <span>{text}</span>;
  return <span>{text.slice(0, m.index)}<span className="tnum">{m[0].includes(',') ? Number(v).toLocaleString('en-IN') : v}</span>{text.slice(m.index + m[0].length)}</span>;
}

const Check = () => <svg className="hb-check" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9.5" pathLength="1" /><path d="M7.5 12.3l3 3 6-6.3" pathLength="1" /></svg>;
const Down = () => <svg className="hb-down" viewBox="0 0 24 24" fill="none"><path d="M2 7l7 7 4-4 9 9" pathLength="1" /><path d="M15 19h7v-7" pathLength="1" /></svg>;
const Clock = () => <svg className="hb-clock" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9.5" /><path className="h" d="M12 12V7.5" /><path className="m" d="M12 12h4" /></svg>;
const Star = ({ s, style }) => <svg className="spark" style={style} viewBox="0 0 20 20" width={s} height={s}><path d="M10 0c.9 5.6 4.4 9.1 10 10-5.6.9-9.1 4.4-10 10-.9-5.6-4.4-9.1-10-10C5.6 9.1 9.1 5.6 10 0z" /></svg>;

export default function HeroArt({ badges = [] }) {
  const ref = useRef(null);
  const [go, setGo] = useState(false);
  useEffect(() => { const t = setTimeout(() => setGo(true), 60); return () => clearTimeout(t); }, []);
  const ticks = Array.from({ length: 72 }, (_, i) => i);
  const parts = Array.from({ length: 14 }, (_, i) => ({ l: 8 + ((i * 37) % 84), d: (i * 0.73) % 6, s: 3 + (i % 3), t: 6 + (i % 5) }));
  const pos = ['b0', 'b1', 'b2', 'b3'];
  const icon = [<Check key="c" />, null, <Clock key="k" />, <Down key="d" />];
  return <div ref={ref} className={'hero-art ha' + (go ? ' go' : '')} aria-hidden="true">
    {/* radar sweep */}
    <div className="ha-sweep" />
    {/* dial: rings, ticks, orbits, crosshair */}
    <svg className="ha-dial" viewBox="0 0 600 600" fill="none">
      <defs>
        <radialGradient id="haGlow" cx="50%" cy="50%" r="50%"><stop offset="0" stopColor="#f3dc93" stopOpacity=".55" /><stop offset="1" stopColor="#f3dc93" stopOpacity="0" /></radialGradient>
      </defs>
      <circle cx="300" cy="300" r="150" fill="url(#haGlow)" className="ha-breath" />
      <circle className="ha-ring r1" cx="300" cy="300" r="282" pathLength="1" />
      <g className="ha-ticks">{ticks.map((i) => <line key={i} x1="300" y1={i % 6 ? 24 : 16} x2="300" y2="30" transform={`rotate(${i * 5} 300 300)`} />)}</g>
      <circle className="ha-ring r2" cx="300" cy="300" r="218" strokeDasharray="3 7" />
      <circle className="ha-ring r3" cx="300" cy="300" r="152" pathLength="1" />
      <g className="ha-cross"><path d="M18 300h564" /><path d="M300 18v564" /></g>
      <g className="ha-orbit o1"><circle cx="300" cy="18" r="5" /><circle cx="300" cy="18" r="11" className="halo" /></g>
      <g className="ha-orbit o2"><circle cx="300" cy="82" r="4" /></g>
      <g className="ha-orbit o3"><circle cx="300" cy="148" r="3" /></g>
      {/* links from the badges to the stone */}
      <g className="ha-links"><path d="M150 118 L246 238" /><path d="M462 110 L360 236" /><path d="M132 470 L246 362" /><path d="M462 430 L362 360" /></g>
    </svg>
    {/* the stone */}
    <div className="ha-stone"><div className="ha-turn">
      <svg viewBox="-6 -6 172 132" fill="none">
        <defs>
          <linearGradient id="haGold" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stopColor="#f6e2a4" /><stop offset=".5" stopColor="#d4ac52" /><stop offset="1" stopColor="#a8832f" /></linearGradient>
          <linearGradient id="haShine" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stopColor="#fff" stopOpacity="0" /><stop offset=".5" stopColor="#fff" stopOpacity=".85" /><stop offset="1" stopColor="#fff" stopOpacity="0" /></linearGradient>
          <clipPath id="haClip"><polygon points={D.outline} /></clipPath>
        </defs>
        <g className="ha-facets">{[...D.crown, ...D.pav].map((p, i) => <polygon key={p} points={p} style={{ '--f': i }} />)}</g>
        <g clipPath="url(#haClip)"><g transform="skewX(-18)"><rect className="ha-shine" x="-60" y="-10" width="46" height="140" fill="url(#haShine)" /></g></g>
        <g className="ha-lines">{D.lines.map((d, i) => <path key={i} d={d} pathLength="1" style={{ '--l': i }} />)}</g>
      </svg>
    </div>
      <Star s={18} style={{ left: '-4%', top: '18%', '--s': 0 }} /><Star s={14} style={{ right: '-2%', top: '20%', '--s': 1 }} />
      <Star s={22} style={{ left: '44%', top: '-14%', '--s': 2 }} /><Star s={12} style={{ left: '49%', bottom: '-10%', '--s': 3 }} />
    </div>
    {/* QC scan line */}
    <div className="ha-scan"><i /></div>
    {/* rising particles */}
    <div className="ha-parts">{parts.map((p, i) => <i key={i} style={{ left: p.l + '%', width: p.s, height: p.s, animationDelay: p.d + 's', animationDuration: p.t + 's' }} />)}</div>
    {/* live badges */}
    {badges.slice(0, 4).map((b, i) => <div key={i} className={'fbadge hb ' + pos[i]} style={{ '--b': i }}>
      <small>{i === 0 && <span className="live" />}{b.label}</small>
      <b>{icon[i]}<CountText text={b.value} delay={1500 + i * 250} /></b>
    </div>)}
  </div>;
}
