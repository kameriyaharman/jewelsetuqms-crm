/* JEWELSETU Q-TRACK — client-side helpers (no build step, works on shared hosting) */
(function () {
  const $ = (s, r = document) => r.querySelector(s), $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  window.$q = $; window.$$q = $$;

  /* ---------- ISO 2859-1 (mirror of app/aql.php) ---------- */
  const L = 'ABCDEFGHJKLMNPQR'.split(''), S = [2,3,5,8,13,20,32,50,80,125,200,315,500,800,1250,2000];
  const AV = [0.010,0.015,0.025,0.040,0.065,0.10,0.15,0.25,0.40,0.65,1.0,1.5,2.5,4.0,6.5,10], AC = [0,1,2,3,5,7,10,14,21];
  const CT = [[2,8,'AAAAAAB'],[9,15,'AAAAABC'],[16,25,'AABBBCD'],[26,50,'ABBCCDE'],[51,90,'BBCCCEF'],[91,150,'BBCDDFG'],[151,280,'BCDEEGH'],
    [281,500,'BCDEFHJ'],[501,1200,'CCEFGJK'],[1201,3200,'CDEGHKL'],[3201,10000,'CDFGJLM'],[10001,35000,'CDFHKMN'],[35001,150000,'DEGJLNP'],[150001,500000,'DEGJMPQ'],[500001,1e12,'DEHKNQR']];
  function code(lot, lv) { const c = {S1:0,S2:1,S3:2,S4:3,I:4,II:5,III:6}[lv] ?? 5; for (const [a,b,s] of CT) if (lot >= a && lot <= b) return s[c]; return 'A'; }
  function single(letter, aql) {
    let j = AV.findIndex(v => Math.abs(v - aql) < 1e-9); if (j < 0) j = 12;
    let i = L.indexOf(letter);
    for (let g = 0; g < 20; g++) { const s = i + j;
      if (s < 14) i = 14 - j; else if (s === 15) i -= 1; else if (s === 16) i += 1; else if (s > 24) i = 24 - j;
      else { if (i < 0 || i > 15) return null; const ac = s === 14 ? 0 : AC[s - 16]; return {letter: L[i], sample: S[i], ac, re: ac + 1}; }
      if (i < 0 || i > 15) return null; }
    return null;
  }
  window.aqlPlan = function (lot, lv, cr, ma, mi) {
    const letter = code(Math.max(2, lot), lv), out = {letter, sample: 0};
    for (const [k, v] of [['critical', cr], ['major', ma], ['minor', mi]]) {
      if (!(v > 0)) { out[k] = null; continue; }
      const p = single(letter, v) || {letter, sample: lot, ac: 0, re: 1}; out[k] = p; out.sample = Math.max(out.sample, p.sample);
    }
    if (!out.sample) out.sample = S[L.indexOf(letter)];
    if (out.sample >= lot) { out.sample = lot; out.full = true; }
    return out;
  };

  /* ---------- request form ---------- */
  const rf = $('#reqform');
  if (rf) {
    const cSel = $('#client_id'), vSel = $('select#vendor_id');
    function filterVendors() {
      if (!cSel || !vSel || window.ROLE === 'vendor') return;
      const ok = (window.MAPPING[cSel.value] || []).map(String);
      $$('option', vSel).forEach(o => { if (!o.value) return; o.hidden = !ok.includes(o.value); });
      if (vSel.selectedOptions[0] && vSel.selectedOptions[0].hidden) vSel.value = '';
    }
    cSel && cSel.addEventListener('change', filterVendors); filterVendors();
    vSel && vSel.addEventListener('change', () => { const o = vSel.selectedOptions[0]; const loc = $('#location'); if (o && loc && !loc.value) loc.value = (o.dataset.loc || '').split(',')[0] + ' Unit 1'; });
    function sync() {
      const scope = (rf.querySelector('[name=scope]:checked') || {}).value, mode = (rf.querySelector('[name=bulk_mode]:checked') || {}).value;
      $('#bulkbox').style.display = scope === 'BULK' ? '' : 'none';
      $('#aqlbox').style.display = scope === 'BULK' && mode === 'AQL' ? '' : 'none';
      $('#definedbox').style.display = scope === 'SAMPLE' || mode === 'DEFINED' ? '' : 'none';
      $('#deflabel').textContent = scope === 'SAMPLE' ? 'Sample pcs to check per SKU (0 = all ready)' : 'Defined sample qty (pcs per SKU, ≤ Ready qty)';
      const rqs = $$('.rq').map(i => +i.value || 0), lot = rqs.reduce((a, b) => a + b, 0);
      $('#lotsize').value = lot.toLocaleString('en-IN');
      // qty warning
      let warn = ''; const ex = $('#allow_excess').checked;
      $$('#skus tr').forEach(tr => { const o = $('.oq', tr), r = $('.rq', tr); if (!o) return; const bad = !ex && (+r.value > +o.value) && o.value !== '';
        r.style.borderColor = bad ? 'var(--ruby)' : ''; if (bad) warn = 'Ready qty is more than Order qty on a row'; });
      $('#qtywarn').textContent = warn; $('#qtywarn').style.color = 'var(--ruby)';
      if (scope === 'BULK' && mode === 'AQL') {
        const lv = $('#insp_level').value, g = n => +rf.querySelector(`[name=${n}]`).value;
        const p = aqlPlan(Math.max(lot, 2), lv, g('aql_critical'), g('aql_major'), g('aql_minor'));
        for (const k of ['critical', 'major', 'minor']) { const x = p[k];
          $(`[data-s=${k}]`).textContent = x ? (Math.min(x.sample, lot) || '—') : '—'; $(`[data-a=${k}]`).textContent = x ? x.ac : (k === 'critical' ? 0 : '—'); $(`[data-r=${k}]`).textContent = x ? x.re : (k === 'critical' ? 1 : '—'); }
        const per = $$('#skus tr').map(tr => { const s = $('[name="sku[]"]', tr), r = $('.rq', tr); if (!s || !+r.value) return null;
          const q = aqlPlan(+r.value, lv, g('aql_critical'), g('aql_major'), g('aql_minor')); return `${s.value || 'SKU'}: lot ${(+r.value).toLocaleString('en-IN')} → ${q.sample} pcs (${q.letter})`; }).filter(Boolean);
        $('#aqlnote').innerHTML = `Sample size ${p.sample} for lot ${lot.toLocaleString('en-IN')} · Level ${lv} (code letter ${p.letter})${p.full ? ' — 100% inspection' : ''}<br><b>Per SKU (each SKU is its own lot):</b><br>` + per.join('<br>');
      }
    }
    rf.addEventListener('input', sync); rf.addEventListener('change', sync); sync();
    window.addSku = function () {
      const t = $('#skus'), last = t.rows[t.rows.length - 1], n = last.cloneNode(true);
      $$('input', n).forEach(i => { if (i.type === 'file') { i.value = ''; i.name = `row_file[${t.rows.length - 1}]`; } else if (i.name === 'item_id[]') i.value = 0; else if (!['metal[]'].includes(i.name)) i.value = ''; });
      t.appendChild(n); renumber(); sync();
    };
    window.delSku = function (b) { const t = $('#skus'); if (t.rows.length <= 2) return; b.closest('tr').remove(); renumber(); sync(); };
    function renumber() { $$('#skus tr').slice(1).forEach((tr, i) => { $('.rn', tr).textContent = i + 1; const f = $('input[type=file]', tr); if (f) f.name = `row_file[${i}]`; }); }
  }

  /* ---------- dialogs ---------- */
  window.openDlg = id => { const d = document.getElementById(id); d && d.showModal(); };
  $$('[data-dlg]').forEach(b => b.addEventListener('click', e => { e.preventDefault(); openDlg(b.dataset.dlg); }));
  $$('dialog [data-close]').forEach(b => b.addEventListener('click', () => b.closest('dialog').close()));

  /* ---------- week schedule drag & drop ---------- */
  $$('.chipjob[draggable=true]').forEach(c => c.addEventListener('dragstart', e => e.dataTransfer.setData('text', c.dataset.job)));
  $$('.slot[data-insp]').forEach(s => {
    s.addEventListener('dragover', e => { e.preventDefault(); s.classList.add('over'); });
    s.addEventListener('dragleave', () => s.classList.remove('over'));
    s.addEventListener('drop', e => { e.preventDefault(); s.classList.remove('over'); const id = e.dataTransfer.getData('text'); if (!id) return;
      const f = document.createElement('form'); f.method = 'post'; f.action = QT.base + '/job-action';
      for (const [k, v] of Object.entries({_csrf: QT.csrf, act: 'assign', id, inspector_id: s.dataset.insp, date: s.dataset.date, notify_vendor: 1, back: location.pathname + location.search}))
        { const i = document.createElement('input'); i.type = 'hidden'; i.name = k; i.value = v; f.appendChild(i); }
      document.body.appendChild(f); f.submit(); });
  });

  /* ---------- online / offline indicator ---------- */
  function net() { const off = !navigator.onLine; document.body.classList.toggle('offline', off); const n = $('#net'); if (n) { n.classList.toggle('off', off); n.title = off ? 'Offline' : 'Online'; } if (!off) flushQueue(); }
  window.addEventListener('online', net); window.addEventListener('offline', net);

  /* ---------- offline sync queue (inspector app) ---------- */
  const QK = 'qt_queue_v1';
  const getQ = () => { try { return JSON.parse(localStorage.getItem(QK) || '[]'); } catch (e) { return []; } };
  const setQ = q => { try { localStorage.setItem(QK, JSON.stringify(q)); } catch (e) { alert('Phone storage is full — please sync before taking more photos.'); } updQ(); };
  function updQ() { const n = getQ().length, el = $('#qcount'); if (el) { el.textContent = n; el.parentElement.style.display = n ? '' : 'none'; } }
  window.qtSend = async function (payload, opts = {}) {
    if (!navigator.onLine) { const q = getQ(); q.push(payload); setQ(q); return {queued: true}; }
    try {
      const r = await fetch(QT.base + '/api/inspect', {method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF': QT.csrf}, body: JSON.stringify(payload), credentials: 'same-origin'});
      const j = await r.json(); if (!r.ok) throw Object.assign(new Error(j.error || 'Error'), {server: true, data: j}); return j;
    } catch (e) { if (e.server) throw e; const q = getQ(); q.push(payload); setQ(q); return {queued: true}; }
  };
  let flushing = false;
  async function flushQueue() {
    if (flushing || !navigator.onLine) return; const q = getQ(); if (!q.length) return; flushing = true;
    const rest = [];
    for (const p of q) {
      try { const r = await fetch(QT.base + '/api/inspect', {method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF': QT.csrf}, body: JSON.stringify(p), credentials: 'same-origin'});
        if (r.status >= 500 || r.status === 0) rest.push(p); } catch (e) { rest.push(p); }
    }
    setQ(rest); flushing = false;
    const s = $('#syncmsg'); if (s && !rest.length) { s.textContent = 'Sync complete ✓ ' + new Date().toLocaleTimeString(); }
  }
  window.qtFlush = flushQueue;
  net(); updQ();

  /* ---------- photo compression ---------- */
  window.compressImage = function (file, max = 1400, q = 0.72) {
    return new Promise((res, rej) => {
      const fr = new FileReader(); fr.onerror = rej;
      fr.onload = () => { const img = new Image(); img.onerror = rej; img.onload = () => {
        let {width: w, height: h} = img; const sc = Math.min(1, max / Math.max(w, h)); w = Math.round(w * sc); h = Math.round(h * sc);
        const c = document.createElement('canvas'); c.width = w; c.height = h; c.getContext('2d').drawImage(img, 0, 0, w, h);
        res(c.toDataURL('image/jpeg', q)); }; img.src = fr.result; };
      fr.readAsDataURL(file);
    });
  };

  /* ---------- confirm buttons ---------- */
  $$('[data-confirm]').forEach(b => b.addEventListener('click', e => { if (!confirm(b.dataset.confirm)) e.preventDefault(); }));
  /* auto-submit selects */
  $$('select[data-auto]').forEach(s => s.addEventListener('change', () => s.form.submit()));
})();
