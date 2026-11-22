import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { ArrowDown, ArrowUp, ExternalLink, FileText, Image as ImgI, Plus, RotateCcw, Trash2, Upload, X } from 'lucide-react';
import { get, post, upload } from '../api';
import { media } from '../site';
import { ICONS } from '../icons';
import { useAdmin } from './Admin';
import { SECTIONS } from './schema';

function Tags({ value = [], onChange }) {
  const [v, setV] = useState('');
  const add = () => { const x = v.trim(); if (x && !value.includes(x)) onChange([...value, x]); setV(''); };
  return <div className="tagin">
    {value.map((x, i) => <span key={x + i}>{x}<button type="button" className="x" onClick={() => onChange(value.filter((_, j) => j !== i))}><X /></button></span>)}
    <input value={v} onChange={(e) => setV(e.target.value)} placeholder="Type and press Enter" onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); add(); } else if (e.key === 'Backspace' && !v && value.length) onChange(value.slice(0, -1)); }} onBlur={add} />
  </div>;
}
function FileField({ value, onChange, pdf }) {
  const { toast } = useAdmin(); const [busy, setBusy] = useState(false);
  const pick = () => { const i = document.createElement('input'); i.type = 'file'; i.accept = pdf ? 'application/pdf' : 'image/*';
    i.onchange = async () => { const f = i.files[0]; if (!f) return; setBusy(true); const fd = new FormData(); fd.append('file', f); if (pdf) fd.append('pdf', '1');
      try { const r = await upload('site/admin/upload', fd); onChange(r.path); } catch (e) { toast(e.message, 1); } finally { setBusy(false); } }; i.click(); };
  return <div className="imgf">
    <span className="pv" style={!pdf && value ? { backgroundImage: `url("${media(value)}")` } : undefined}>{!value ? (pdf ? <FileText /> : <ImgI />) : pdf ? <FileText color="var(--gold-d)" /> : null}</span>
    <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', alignItems: 'center' }}>
      <button type="button" className="btn btn-line btn-sm" onClick={pick} disabled={busy}><Upload />{busy ? 'Uploading…' : value ? 'Replace' : 'Upload'}</button>
      {value && <><a className="btn btn-sm" href={media(value)} target="_blank" rel="noopener"><ExternalLink />View</a><button type="button" className="btn btn-sm" style={{ color: '#b42318' }} onClick={() => onChange('')}>Remove</button></>}
    </div>
  </div>;
}
function IconPick({ value, onChange }) {
  return <div className="iconsel">{Object.entries(ICONS).map(([k, I]) => <button type="button" key={k} title={k} className={value === k ? 'on' : ''} onClick={() => onChange(k)}><I /></button>)}</div>;
}
function Field({ f, value, onChange }) {
  let el;
  switch (f.type) {
    case 'area': el = <textarea className="inp" rows={Math.min(6, Math.max(2, Math.ceil(String(value || '').length / 80)))} value={value ?? ''} onChange={(e) => onChange(e.target.value)} />; break;
    case 'image': el = <FileField value={value} onChange={onChange} />; break;
    case 'pdf': el = <FileField value={value} onChange={onChange} pdf />; break;
    case 'tags': el = <Tags value={Array.isArray(value) ? value : []} onChange={onChange} />; break;
    case 'icon': el = <IconPick value={value} onChange={onChange} />; break;
    case 'toggle': el = <button type="button" className={'sw' + (value && value !== '0' ? ' on' : '')} onClick={() => onChange(value && value !== '0' ? '' : '1')} />; break;
    case 'number': el = <input className="inp" type="number" value={value ?? ''} onChange={(e) => onChange(e.target.value)} style={{ maxWidth: 160 }} />; break;
    case 'select': el = <select className="inp" value={value ?? ''} onChange={(e) => onChange(e.target.value)} style={{ maxWidth: 240 }}>{f.options.map((o) => <option key={o}>{o}</option>)}</select>; break;
    case 'list': {
      const arr = Array.isArray(value) ? value : [];
      const upd = (i, k, v) => onChange(arr.map((x, j) => (j === i ? { ...x, [k]: v } : x)));
      const move = (i, d) => { const n = [...arr]; [n[i], n[i + d]] = [n[i + d], n[i]]; onChange(n); };
      const small = f.of.filter((x) => ['text', 'number', 'select', 'toggle'].includes(x.type)); const big = f.of.filter((x) => !small.includes(x));
      el = <div>{arr.map((it, i) => <div className="grp" key={i}>
        <div className="gh"><span>{f.label.replace(/s$|\s*\(.*\)/g, '')} {i + 1}</span><span className="ctl">
          <button type="button" disabled={i === 0} onClick={() => move(i, -1)} title="Move up"><ArrowUp /></button>
          <button type="button" disabled={i === arr.length - 1} onClick={() => move(i, 1)} title="Move down"><ArrowDown /></button>
          <button type="button" onClick={() => onChange(arr.filter((_, j) => j !== i))} title="Remove"><Trash2 /></button></span></div>
        <div className="grp-row">{small.map((sf) => <div className="fld" key={sf.k} style={{ marginBottom: 10 }}><label className="l">{sf.label}</label><Field f={sf} value={it[sf.k]} onChange={(v) => upd(i, sf.k, v)} /></div>)}</div>
        {big.map((sf) => <div className="fld" key={sf.k} style={{ marginBottom: 10 }}><label className="l">{sf.label}</label><Field f={sf} value={it[sf.k]} onChange={(v) => upd(i, sf.k, v)} /></div>)}
      </div>)}
        {(!f.max || arr.length < f.max) && <button type="button" className="btn btn-line btn-sm" onClick={() => onChange([...arr, Object.fromEntries(f.of.map((x) => [x.k, x.type === 'tags' ? [] : x.type === 'icon' ? 'sparkles' : '']))])}><Plus />Add</button>}
      </div>; break;
    }
    default: el = <input className="inp" value={value ?? ''} onChange={(e) => onChange(e.target.value)} />;
  }
  return el;
}

export default function Content() {
  const { toast, me } = useAdmin();
  const [sp, setSp] = useSearchParams();
  const [d, setD] = useState(null); const [vals, setVals] = useState({}); const [dirty, setDirty] = useState(false); const [busy, setBusy] = useState(false);
  const key = sp.get('s') || 'brand';
  const sec = SECTIONS.find((s) => s.key === key) || SECTIONS[0];
  const load = () => get('site/admin/content').then((r) => { setD(r); setVals(r.content[sec.key]); setDirty(false); });
  useEffect(() => { load(); }, []); // eslint-disable-line
  useEffect(() => { if (d) { setVals(d.content[sec.key]); setDirty(false); } }, [sec.key]); // eslint-disable-line
  const go = (k) => { if (dirty && !confirm('Discard unsaved changes in this section?')) return; setSp({ s: k }); };
  const save = async () => { setBusy(true); try { const r = await post('site/admin/content/save', { key: sec.key, value: vals }); toast(r.message); setDirty(false); setD({ ...d, content: { ...d.content, [sec.key]: vals }, saved: { ...d.saved, [sec.key]: 'now' } }); } catch (e) { toast(e.message, 1); } finally { setBusy(false); } };
  const reset = async () => { if (!confirm('Reset “' + sec.label + '” to the original design text? Your edits in this section will be lost.')) return; const r = await post('site/admin/content/reset', { key: sec.key }); toast(r.message); load(); };
  if (!d) return <div className="spin"><i /></div>;
  const base = (import.meta.env.BASE_URL || '/').replace(/\/$/, '');
  return <>
    <div className="atop"><div><h1 className="serif">Website content</h1><p>Edit any text, image, list or contact detail on the website. Changes are live as soon as you save.</p></div></div>
    <div className="cgrid">
      <div className="clist">{SECTIONS.map((s) => <button key={s.key} className={s.key === sec.key ? 'on' : ''} onClick={() => go(s.key)}>{s.label}{d.saved[s.key] && <i title="Edited" />}</button>)}</div>
      <div className="card"><div className="card-h"><h3>{sec.label}</h3><a className="link-gold" href={base + sec.page} target="_blank" rel="noopener">View on site<ExternalLink /></a></div>
        <div className="card-b cform">
          {sec.fields.map((f) => <div className="fld" key={f.k}><label className="l">{f.label}</label>
            <Field f={f} value={vals?.[f.k]} onChange={(v) => { setVals({ ...vals, [f.k]: v }); setDirty(true); }} />
            {f.help && <div className="help">{f.help}</div>}</div>)}
          <div className="savebar">
            <button className="btn btn-gold btn-sm" disabled={busy || !dirty} onClick={save}>{busy ? 'Saving…' : 'Save section'}</button>
            {dirty && <span className="dirty">Unsaved changes</span>}
            <span style={{ flex: 1 }} />
            {me.user.role === 'super_admin' && d.saved[sec.key] && <button className="btn btn-sm" onClick={reset}><RotateCcw />Reset to design</button>}
          </div>
        </div></div>
    </div>
  </>;
}
