import {
  ScanSearch, Factory, Layers, GraduationCap, CalendarPlus, UserCheck, Camera, FileCheck2, RefreshCcw, Globe, Package, Store, Loader, ListChecks, Sigma,
  BarChart3, Radar, Box, Shapes, Network, Flame, PenTool, Sparkles, Gem, ZoomIn, ClipboardCheck, BadgeCheck, Briefcase, Award, ShieldCheck, Building2, Users, BookOpen, Trophy, Lightbulb,
} from 'lucide-react';

/** Icons that admins can pick for cards (by name). */
export const ICONS = {
  scan: ScanSearch, factory: Factory, layers: Layers, graduation: GraduationCap, calendar: CalendarPlus, usercheck: UserCheck, camera: Camera, filecheck: FileCheck2,
  refresh: RefreshCcw, globe: Globe, package: Package, store: Store, loader: Loader, listcheck: ListChecks, sigma: Sigma, chart: BarChart3, radar: Radar, box: Box,
  shapes: Shapes, network: Network, flame: Flame, pen: PenTool, sparkles: Sparkles, gem: Gem, zoom: ZoomIn, clipboard: ClipboardCheck, badgecheck: BadgeCheck,
  briefcase: Briefcase, award: Award, shield: ShieldCheck, building: Building2, users: Users, book: BookOpen, trophy: Trophy, bulb: Lightbulb,
};
export function Icon({ name, ...p }) { const I = ICONS[name] || Sparkles; return <I {...p} />; }

/** Line-art brilliant-cut diamond (logo mark + hero art) */
export function Diamond({ className, stroke = '#c9a24a', w = 1.4 }) {
  return <svg className={'dia-svg ' + (className || '')} viewBox="0 0 160 120" fill="none" stroke={stroke} strokeWidth={w} strokeLinejoin="round">
    <path pathLength="1" d="M4 36 32 6h96l28 30-76 80z" /><path pathLength="1" d="M4 36h152" />
    <path pathLength="1" d="M32 6l18 30 30-30 30 30 18-30" /><path pathLength="1" d="M50 36l30 80 30-80" />
    <path pathLength="1" d="M32 6 4 36M128 6l28 30M50 36 80 6M110 36 80 6" opacity=".6" />
    <path pathLength="1" d="M4 36 80 116M156 36 80 116M50 36 80 116 110 36" opacity=".55" />
  </svg>;
}
export function Logo({ C, light }) {
  const b = C?.brand || {};
  return <span className={'logo' + (light ? ' light' : '')}>
    {b.logo ? <img className="custom" src={b.logo.startsWith('/') ? b.logo : (import.meta.env.BASE_URL || '/') + 'api/site/pub/media?p=' + encodeURIComponent(b.logo)} alt={b.name} />
      : <Diamond stroke="#c9a24a" w={7} />}
    {!b.logo && <span><b>{b.name || 'JEWELSETU'}</b><small>{b.tagline || 'Quality Management Systems'}</small></span>}
  </span>;
}
/** cover gradient + icon by category, for articles without a cover image */
export function catStyle(cat = '') {
  const c = cat.toLowerCase();
  if (c.includes('aql') || c.includes('sampl')) return { cls: 'cv-0', I: 'grid' };
  if (c.includes('cast') || c.includes('manuf')) return { cls: 'cv-1', I: 'flame' };
  if (c.includes('diamond') || c.includes('gem')) return { cls: 'cv-2', I: 'gem' };
  return { cls: 'cv-3', I: 'shield' };
}
