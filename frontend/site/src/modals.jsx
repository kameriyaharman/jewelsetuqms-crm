import { useEffect, useRef, useState } from 'react';
import { ArrowLeft, ArrowRight, Calendar, Camera, Check, CheckCircle2, Clock, Download, FileText, Info, Lightbulb, MapPin, MessageCircle, Send, ShieldCheck, UploadCloud, X, ZoomIn, CalendarPlus } from 'lucide-react';
import { upload } from './api';
import { useSite, waLink, media } from './site';
import { Icon, Logo } from './icons';

/* Estimated ANSI/ASQ Z1.4 Level II sample size for a batch bucket */
const aqlFor = (q = '') => q.startsWith('<') ? '32–50 pcs' : q.startsWith('500') ? '80–125 pcs' : q.startsWith('2k') ? '125–200 pcs' : q.startsWith('10k') ? '200–315 pcs' : '';
const useEsc = (fn) => useEffect(() => { const k = (e) => e.key === 'Escape' && fn(); window.addEventListener('keydown', k); document.body.style.overflow = 'hidden';
  return () => { window.removeEventListener('keydown', k); document.body.style.overflow = ''; }; }, []); // eslint-disable-line

function Turnstile({ siteKey, onToken }) {
  const ref = useRef(null);
  useEffect(() => {
    if (!siteKey) return;
    const render = () => window.turnstile && ref.current && !ref.current.dataset.done && (ref.current.dataset.done = '1', window.turnstile.render(ref.current, { sitekey: siteKey, callback: onToken, 'expired-callback': () => onToken('') }));
    if (window.turnstile) render();
    else if (!document.getElementById('cf-ts')) { const s = document.createElement('script'); s.id = 'cf-ts'; s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js'; s.async = true; s.onload = render; document.head.appendChild(s); }
    else { const t = setInterval(() => { if (window.turnstile) { clearInterval(t); render(); } }, 200); return () => clearInterval(t); }
  }, [siteKey]); // eslint-disable-line
  return siteKey ? <div ref={ref} style={{ marginTop: 12 }} /> : null;
}

export function BookModal() {
  const { C, site, book, closeBook } = useSite();
  const B = C.booking;
  const [step, setStep] = useState(book?.service ? 2 : 1);
  const [f, setF] = useState({ service: book?.service || '', categories: [], batch_qty: '', hub: B.hubs[0] || '', pref_date: '', name: '', company: '', email: '', phone: '', message: book?.message || '', website: '' });
  const [file, setFile] = useState(null); const [pct, setPct] = useState(0);
  const [err, setErr] = useState(''); const [busy, setBusy] = useState(false); const [done, setDone] = useState(false); const [token, setToken] = useState(''); const [over, setOver] = useState(false);
  useEsc(closeBook);
  const set = (k) => (e) => setF({ ...f, [k]: e?.target ? e.target.value : e });
  const svc = B.services.find((s) => s.key === f.service);
  const training = /train/i.test(f.service);
  const qtys = training ? B.staff : B.quantities;
  const toggleCat = (c) => setF({ ...f, categories: f.categories.includes(c) ? f.categories.filter((x) => x !== c) : [...f.categories, c] });
  const next = () => {
    setErr('');
    if (step === 1 && !f.service) return setErr('Please pick a service.');
    if (step === 2 && !f.categories.length) return setErr('Select at least one product category.');
    setStep(step + 1);
  };
  const pickFile = (fl) => { if (!fl) return; if (fl.size > 10 * 1024 * 1024) return setErr('File must be under 10 MB.'); setErr(''); setFile(fl); };
  const submit = async () => {
    setErr('');
    if (!f.name.trim()) return setErr('Please enter your full name.');
    if (!f.email.trim() && !f.phone.trim()) return setErr('Please enter a work email or WhatsApp / phone number.');
    if (f.email && !/^\S+@\S+\.\S+$/.test(f.email)) return setErr('Please enter a valid email address.');
    if (site?.turnstile && !token) return setErr('Please complete the security check.');
    const fd = new FormData();
    Object.entries(f).forEach(([k, v]) => (Array.isArray(v) ? v.forEach((x) => fd.append(k + '[]', x)) : fd.append(k, v)));
    fd.append('source', 'Website · ' + (window.location.pathname || '/'));
    if (token) fd.append('cf_token', token);
    if (file) fd.append('file', file);
    setBusy(true);
    try { await upload('site/pub/lead', fd, setPct); setDone(true); }
    catch (e) { setErr(e.message); } finally { setBusy(false); }
  };
  const label = ['Service', training ? 'Team & scope' : 'Technical & batch scope', 'Contact & files'][step - 1];
  return <div className="ovl" onMouseDown={(e) => e.target === e.currentTarget && closeBook()}>
    <div className="modal" role="dialog" aria-modal="true" aria-label="Book an Audit">
      <div className="mh">
        <div className="t"><div>Book an Audit{svc && step > 1 && <span> · {svc.key}</span>}</div><button onClick={closeBook} aria-label="Close"><X /></button></div>
        {!done && <><div className="bars">{[1, 2, 3].map((i) => <i key={i} className={i <= step ? 'on' : ''} />)}</div><small>Step {step} of 3 · {label}</small></>}
      </div>
      {done ? <div className="mb"><div className="done">
        <div className="ck"><CheckCircle2 /></div>
        <h2 className="serif">Request received</h2>
        <p className="sub">Thank you, {f.name.split(' ')[0]}. {B.reply_note}{f.email ? ' A copy has been sent to ' + f.email + '.' : ''}</p>
        <div style={{ display: 'flex', gap: 10, justifyContent: 'center', flexWrap: 'wrap', marginTop: 10 }}>
          <a className="btn btn-line" href={waLink(C, 'Hi, I just submitted a ' + f.service + ' request on your website (' + (f.company || f.name) + ').')} target="_blank" rel="noopener"><MessageCircle />WhatsApp us</a>
          <button className="btn btn-gold" onClick={closeBook}>Done</button>
        </div></div></div>
      : <>
        <div className="mb">
          {step === 1 && <>
            <h2 className="serif">What do you need?</h2><p className="sub">Pick one — you can add more later.</p>
            {B.services.map((s) => <button key={s.key} className={'opt' + (f.service === s.key ? ' on' : '')} onClick={() => setF({ ...f, service: s.key, batch_qty: '' })}>
              <span className="ico sm"><Icon name={s.icon} /></span><span><b>{s.key}</b><small>{s.sub}</small></span>
              <span className="rd">{f.service === s.key && <Check />}</span></button>)}
          </>}
          {step === 2 && <>
            <h2 className="serif">{training ? 'Tell us about the team' : 'Tell us about the batch'}</h2>
            <p className="sub">{training ? 'This helps us plan the workshop content and trainer.' : 'This helps us plan the sample size and auditor.'}</p>
            <label className="fl">Product category</label>
            <div className="tg">{B.categories.map((c) => <button key={c} type="button" className={f.categories.includes(c) ? 'on' : ''} onClick={() => toggleCat(c)}>{f.categories.includes(c) && <Check />}{c}</button>)}</div>
            <label className="fl">{training ? 'Team size' : 'Approx. batch quantity'}</label>
            <div className="tg seg">{qtys.map((q) => <button key={q} type="button" className={f.batch_qty === q ? 'on' : ''} onClick={() => set('batch_qty')(f.batch_qty === q ? '' : q)}>{q}</button>)}</div>
            <label className="fl">{training ? 'Location' : 'Manufacturing hub'}</label>
            <div className="inp-ico"><MapPin /><select className="inp" value={f.hub} onChange={set('hub')}>{B.hubs.map((h) => <option key={h}>{h}</option>)}</select></div>
            <div className="hint">{B.hubs.join(' · ')}</div>
            <label className="fl">Preferred {training ? 'workshop' : 'inspection'} date</label>
            <div className="inp-ico"><Calendar /><input className="inp" type="date" min={new Date().toISOString().slice(0, 10)} value={f.pref_date} onChange={set('pref_date')} /></div>
            {!training && /tpq|inspect/i.test(f.service) && aqlFor(f.batch_qty) && <div className="aqlhint"><Lightbulb />Estimated AQL sample: <b>{aqlFor(f.batch_qty)}</b> <span style={{ color: 'var(--muted)' }}>(Level II)</span></div>}
          </>}
          {step === 3 && <>
            <h2 className="serif">Where should we send the quote?</h2><p className="sub">{B.reply_note}</p>
            <div className="g2">
              <div><label className="fl">Full name</label><input className="inp" value={f.name} onChange={set('name')} autoComplete="name" /></div>
              <div><label className="fl">Company / Buying house</label><input className="inp" value={f.company} onChange={set('company')} autoComplete="organization" /></div>
              <div><label className="fl">Work email</label><input className="inp" type="email" value={f.email} onChange={set('email')} autoComplete="email" /></div>
              <div><label className="fl">WhatsApp / Phone</label><input className="inp" type="tel" value={f.phone} onChange={set('phone')} autoComplete="tel" /></div>
            </div>
            <label className="fl">Anything else? <em>(optional)</em></label>
            <textarea className="inp" rows={2} value={f.message} onChange={set('message')} placeholder="PO number, style refs, special tests…" />
            <label className="fl">PO / Spec sheet <em>(optional)</em></label>
            <label className={'drop' + (over ? ' over' : '')} onDragOver={(e) => { e.preventDefault(); setOver(true); }} onDragLeave={() => setOver(false)}
              onDrop={(e) => { e.preventDefault(); setOver(false); pickFile(e.dataTransfer.files[0]); }}>
              <UploadCloud /><b>Drop file or browse</b>.pdf · .xlsx · .docx — max 10 MB
              <input type="file" hidden accept=".pdf,.xlsx,.xls,.docx,.doc,.csv,.jpg,.jpeg,.png" onChange={(e) => pickFile(e.target.files[0])} />
            </label>
            {file && <div className="filechip"><FileText /><div className="nm"><b>{file.name}</b>{busy && <div className="bar"><i style={{ width: pct + '%' }} /></div>}</div>
              {!busy && <button onClick={() => setFile(null)} aria-label="Remove"><X /></button>}{pct === 100 && <CheckCircle2 color="var(--ok)" size={18} />}</div>}
            <input className="hp" tabIndex={-1} autoComplete="off" value={f.website} onChange={set('website')} aria-hidden="true" />
            <Turnstile siteKey={site?.turnstile} onToken={setToken} />
            <div className="safe"><ShieldCheck />{site?.turnstile ? 'Protected by Cloudflare Turnstile · ' : ''}your data stays confidential</div>
          </>}
          {err && <div className="err">{err}</div>}
        </div>
        <div className="mf">
          {step === 1 ? <span className="note"><Clock />Takes under 60 seconds</span>
            : <button className="btn btn-line btn-sm" onClick={() => { setErr(''); setStep(step - 1); }} disabled={busy}><ArrowLeft />Back</button>}
          {step < 3 ? <button className="btn btn-gold" onClick={next}>Continue<ArrowRight /></button>
            : <button className="btn btn-gold" onClick={submit} disabled={busy}><Send />{busy ? 'Sending…' : 'Submit Request'}</button>}
        </div>
      </>}
    </div>
  </div>;
}

export function ReportModal() {
  const { C, setReport, openBook } = useSite(); const R = C.report;
  const close = () => { setReport(false); const u = new URL(window.location.href); if (u.searchParams.has('preview')) { u.searchParams.delete('preview'); window.history.replaceState(null, '', u.pathname + u.search + u.hash); } };
  useEsc(close);
  const pass = String(R.status || 'PASS').toUpperCase() !== 'FAIL';
  return <div className="ovl" onMouseDown={(e) => e.target === e.currentTarget && close()}>
    <div className="certm" role="dialog" aria-modal="true" aria-label="Sample QC report">
      <div className="ch"><Logo C={C} light /><div className="r"><small>CERTIFICATE NO.</small><b>{R.cert_no}</b></div></div>
      <div className="cb">
        <div className="wm">SAMPLE</div>
        <div className="std">— {R.standard} —</div>
        <h2 className="serif">{R.title}</h2>
        <div className="kv">
          <div><small>Lot size</small><b>{R.lot_size}</b></div><div><small>Sample code</small><b>{R.sample_code}</b></div>
          <div><small>AQL (Maj / Min)</small><b>{R.aql}</b></div><div><small>Accept / Reject</small><b>{R.accept_reject}</b></div>
        </div>
        <div className="info">
          <div><b>Product:</b> {R.product}</div><div><b>Style / PO:</b> {R.style_po}</div>
          <div><b>Inspection site:</b> {R.site}</div><div><b>Date:</b> {R.date}</div>
        </div>
        <div style={{ overflowX: 'auto' }}><table><thead><tr><th>Checkpoint</th><th>Method</th><th>Finding</th><th>Result</th></tr></thead>
          <tbody>{(R.rows || []).map((r, i) => <tr key={i}><td><b>{r.checkpoint}</b></td><td>{r.method}</td><td className="m">{r.finding}</td>
            <td className={/fail/i.test(r.result) ? 'f' : 'p'}>{/fail/i.test(r.result) ? '✕' : '✓'} {r.result}</td></tr>)}</tbody></table></div>
        <div className="foot">
          <div className="ph"><i><Camera /></i><i><ZoomIn /></i><i>{R.photos_more}</i><span>Macro defect<br />photo log</span></div>
          <div className={'stamp' + (pass ? '' : ' fail')}><div><small>LOT STATUS</small><b>{pass ? 'PASS' : 'FAIL'}</b><small>{pass ? 'ACCEPTED' : 'REJECTED'}</small></div></div>
          <div className="sig"><b>{R.signatory}</b>{R.signatory_title}<div className="mono">Verify: {window.location.host}/verify</div></div>
        </div>
      </div>
      <div className="cf">
        <span className="n"><Info />Sample data for illustration — real certificates issue within 24 hours.</span>
        {R.sample_pdf ? <a className="btn btn-line btn-sm" href={media(R.sample_pdf)} target="_blank" rel="noopener"><Download />Download sample PDF</a>
          : <button className="btn btn-line btn-sm" onClick={() => window.print()}><Download />Download sample PDF</button>}
        <button className="btn btn-gold btn-sm" onClick={() => { close(); openBook({ service: C.booking.services[0]?.key }); }}><CalendarPlus />Book a Pilot Audit</button>
      </div>
    </div>
  </div>;
}
