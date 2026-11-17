<?php
$me = user();
$it = one('SELECT * FROM qc_items WHERE id=?', [(int)get('item')]);
$job = $it ? job_load((int)$it['job_id']) : null;
if (!$job || ((int)$job['inspector_id'] !== (int)$me['id'] && (int)$job['inspector2_id'] !== (int)$me['id'])) { flash('SKU not found in your jobs.', 'err'); redirect('app'); }
$editable = in_array($it['status'], ['PENDING', 'IN_PROGRESS', 'COMPLETED', 'RETURNED'], true) && in_array($job['status'], ['ASSIGNED', 'IN_PROGRESS', 'SUBMITTED', 'UNDER_REVIEW', 'RETURNED'], true);
$checks = jdec($it['checks_json']);
$cpm = all('SELECT * FROM checkpoints WHERE (client_id IS NULL OR client_id=?) AND (category IS NULL OR category=? OR category=\'\') ORDER BY sort, id', [$job['client_id'], $it['product']]);
$remarks = []; foreach ($cpm as $c) $remarks[$c['name']] = ['PASS' => $c['pass_remark'], 'FAIL' => $c['fail_remark'], 'NA' => $c['na_remark']];
if (!$checks) foreach ($cpm as $c) $checks[] = ['name' => $c['name'], 'result' => '', 'remark' => ''];
$tests = jdec($it['tests_json']);
if (!$tests) foreach (all('SELECT name, method FROM onsite_tests ORDER BY sort, id') as $t) $tests[] = ['name' => $t['name'], 'qty' => 2, 'result' => '', 'remark' => (string)$t['method']];
$meas = jdec($it['meas_json']);
if (!$meas) {
    $n = fn($s) => preg_replace('/[^0-9.]/', '', explode('+', (string)$s)[0]) ?: '';
    $meas = [['param' => 'Weight', 'spec' => $n($it['target_weight']), 'tol' => '0.05', 'unit' => 'g', 'actual' => '', 'result' => ''],
             ['param' => 'Length', 'spec' => $n($it['length']), 'tol' => '0.2', 'unit' => str_contains((string)$it['length'], 'mm') ? 'mm' : 'in', 'actual' => '', 'result' => ''],
             ['param' => 'Width', 'spec' => $n($it['width']) ?: '', 'tol' => '0.2', 'unit' => 'mm', 'actual' => '', 'result' => ''],
             ['param' => 'Thickness', 'spec' => $n($it['thickness']) ?: '', 'tol' => '0.1', 'unit' => 'mm', 'actual' => '', 'result' => '']];
}
$defects = all('SELECT category, type, severity, qty, remark FROM item_defects WHERE item_id=?', [$it['id']]);
$photos = array_map(fn($p) => ['id' => (int)$p['id'], 'kind' => $p['kind'], 'remark' => $p['remark'], 'url' => url('file', ['p' => $p['path']])], all('SELECT * FROM item_photos WHERE item_id=? ORDER BY id', [$it['id']]));
$dm = []; foreach (all('SELECT d.name, d.severity, c.name cat FROM defect_master d JOIN defect_categories c ON c.id=d.category_id ORDER BY c.sort, d.name') as $d) $dm[$d['cat']][] = ['n' => $d['name'], 's' => $d['severity']];
$atts = all('SELECT * FROM attachments WHERE job_id=? AND (item_id IS NULL OR item_id=?)', [$job['id'], $it['id']]);
$plan = jdec($it['plan_json']);
$lockedQty = $job['scope'] !== 'SAMPLE';
$returned = $it['status'] === 'RETURNED' ? array_filter(array_map('trim', explode(',', (string)$it['return_sections']))) : [];
$S = ['item' => (int)$it['id'], 'editable' => $editable, 'step' => (int)$it['step'], 'insp_qty' => (int)$it['insp_qty'], 'ready' => (int)$it['ready_qty'],
    'lockedQty' => $lockedQty, 'checks' => $checks, 'tests' => $tests, 'meas' => $meas, 'defects' => $defects, 'photos' => $photos, 'dm' => $dm,
    'needPhoto' => flag('require_defect_photo'), 'gps' => flag('gps_on_inspection_start'), 'started' => (bool)$it['started_at'], 'plan' => $plan,
    'threshold' => client_threshold((int)$job['client_id']), 'remarks' => $remarks, 'aql' => $job['scope'] === 'BULK' && $job['bulk_mode'] === 'AQL'];
$siblings = all('SELECT id, sku, status FROM qc_items WHERE job_id=? ORDER BY id', [$job['id']]);
app_start($it['sku'], url('app/job', ['id' => $job['id']]), $editable ? '<button class="btn sm" style="background:#fff3" onclick="openDlg(\'holddlg\')" title="Hold for approval">' . icon('pause') . '</button>' : '');
?>
<div class="offbar">Offline — saved on this phone. <b id="qcount">0</b> change(s) will sync when internet returns.</div>
<?php if ($it['status'] === 'HOLD'): ?><div class="note" style="margin-bottom:10px"><b>⏸ ON HOLD — waiting for <?= h($it['hold_for']) ?> approval</b><br><?= h($it['hold_reason']) ?><br><small><?= fdt($it['hold_at']) ?></small><br>Continue with other SKUs; you'll get a notification when they respond.</div><?php endif ?>
<?php if ($it['hold_status'] && $it['hold_status'] !== 'PENDING'): ?><div class="note rose" style="margin-bottom:10px"><b><?= ucfirst($it['hold_for']) ?> <?= strtolower($it['hold_status']) ?></b>: <?= h($it['hold_response'] ?: '—') ?><br><small>Your question: <?= h($it['hold_reason']) ?></small></div><?php endif ?>
<?php if ($it['status'] === 'RETURNED'): ?><div class="note ruby" style="margin-bottom:10px"><b>Returned for correction</b> — <?= h($it['return_reason']) ?><br>Sections: <?= h($it['return_sections']) ?></div><?php endif ?>
<?php if (!$editable && $it['status'] !== 'HOLD'): ?><div class="qtyok" style="margin-bottom:10px">Status: <?= h(ITEM_STATUS[$it['status']] ?? $it['status']) ?> — read only.</div><?php endif ?>

<details class="mcard" <?= $it['step'] == 0 ? 'open' : '' ?>><summary style="font-weight:650;cursor:pointer">Product information</summary>
<div class="mgrid2 mt">
  <div><small>SKU / design no</small><b class="mono"><?= h($it['sku']) ?></b></div><div><small>Product</small><b><?= h($it['product']) ?></b></div>
  <div><small>PO number</small><b><?= h($job['po_no']) ?></b></div><div><small>Order qty</small><b><?= num($it['order_qty']) ?> pcs</b></div>
  <div><small>Ready for QC</small><b><?= num($it['ready_qty']) ?> pcs</b></div><div><small>Metal</small><b><?= h($it['metal']) ?></b></div>
  <div><small>Plating</small><b><?= h($it['plating']) ?></b></div><div><small>Length</small><b><?= h($it['length']) ?></b></div>
  <div><small>Lock</small><b><?= h($it['lock_type']) ?></b></div><div><small>Stamping</small><b><?= h($it['stamping']) ?></b></div>
  <div><small>QC scope</small><b><?= h(scope_label($job)) ?> (<?= num($it['insp_qty']) ?>)</b></div><div><small>Target weight</small><b><?= h($it['target_weight']) ?></b></div>
</div>
<?php if ($plan): ?><p class="muted" style="font-size:12px">AQL <?= h($plan['level']) ?> · code <?= h($plan['letter']) ?> · sample <?= (int)$plan['sample'] ?><?php foreach (['critical', 'major', 'minor'] as $k) if (!empty($plan[$k]['aql'])) echo ' · ' . ucfirst($k) . ' Ac ' . $plan[$k]['ac'] . '/Re ' . $plan[$k]['re']; ?></p><?php endif ?>
<?php if ($atts): ?><div class="mt"><?php foreach ($atts as $a): ?><a class="pill" target="_blank" href="<?= url('file', ['p' => $a['path']]) ?>">📎 <?= h($a['name']) ?></a><?php endforeach ?></div><?php endif ?>
<?php if ($job['special_req']): ?><div class="note mt" style="font-size:12px"><b>Special requirements:</b> <?= h($job['special_req']) ?></div><?php endif ?>
</details>

<div class="stepper" id="stepper"><?php foreach (STEPS as $k => $l): ?><a href="#" data-go="<?= $k ?>" title="<?= h($l) ?>" class="<?= in_array($l, $returned, true) ? 'ret' : '' ?>"><?= $k ?></a><?php endforeach ?></div>
<div style="display:flex;justify-content:space-between;font-size:12px" class="muted"><span id="steplbl"></span><span id="savedat"><?= $it['updated_at'] ? 'Saved ' . date('h:i A', strtotime($it['updated_at'])) : '' ?></span></div>

<form id="insp" onsubmit="return false" class="mt">
<!-- 1 -->
<section class="mcard step" data-step="1"><b>General information</b>
  <div class="mgrid2 mt"><div><small>Client</small><b><?= h($job['client_code']) ?></b></div><div><small>Vendor</small><b><?= h($job['vendor_name']) ?></b></div>
  <div><small>PO no.</small><b><?= h($job['po_no']) ?></b></div><div><small>SKU</small><b class="mono"><?= h($it['sku']) ?></b></div>
  <div><small>Inspector</small><b><?= h($me['emp_code']) ?></b></div><div><small>Job</small><b><?= h($job['job_no']) ?></b></div></div>
  <div class="f mt"><label>Inspection date</label><input type="date" name="insp_date" value="<?= h($it['insp_date'] ?: today()) ?>"></div>
  <div class="mgrid2 f"><div><label>Production batch / lot no.</label><input type="text" name="lot_no" value="<?= h($it['lot_no']) ?>" placeholder="LOT-OCT-09A"></div>
    <div><label>XRF machine used</label><input type="text" name="xrf_machine" value="<?= h($it['xrf_machine'] ?: 'Goldscope SD 550') ?>"></div></div>
  <div class="f"><label>Master sample reference</label><select name="master_sample"><?php foreach (['Approved Master Sample Available', 'Master Sample Not Available', 'CAD / Photo Reference Only'] as $o): ?><option <?= ($it['master_sample'] ?: 'Approved Master Sample Available') === $o ? 'selected' : '' ?>><?= $o ?></option><?php endforeach ?></select></div>
  <div class="qtyok" id="gpsline" style="font-size:12px;background:var(--rose-ll);color:var(--rose-d)"><?= $it['gps'] ? 'GPS logged · ' . h($it['gps']) : (flag('gps_on_inspection_start') ? 'GPS will be logged when you start' : 'GPS logging off') ?></div>
  <label class="mt">Re-inspection?</label>
  <div class="pfn" style="grid-template-columns:1fr 1fr"><label><input type="radio" name="reinspection" value="PASS" <?= $it['reinspection'] ? 'checked' : '' ?>>YES</label><label><input type="radio" name="reinspection" value="NA" <?= !$it['reinspection'] ? 'checked' : '' ?>>NO</label></div>
</section>
<!-- 2 -->
<section class="mcard step" data-step="2"><b>Quantity</b>
  <div class="mgrid2 mt"><div><small>Order quantity</small><b><?= num($it['order_qty']) ?></b></div><div><small>Ready for inspection</small><b><?= num($it['ready_qty']) ?></b></div></div>
  <div class="f mt"><label>Inspection quantity <?= $lockedQty ? '(locked · ' . h(scope_label($job)) . ')' : '' ?></label><input type="number" name="inspected_qty" value="<?= (int)($it['inspected_qty'] ?: $it['insp_qty']) ?>" <?= $lockedQty ? 'readonly' : '' ?> min="1"></div>
  <div class="f"><label>Accepted quantity</label><input type="number" name="accepted_qty" value="<?= $it['inspected_qty'] ? (int)$it['accepted_qty'] : '' ?>" min="0" inputmode="numeric"></div>
  <div class="f"><label>Rejected quantity</label><input type="number" name="rejected_qty" value="<?= $it['inspected_qty'] ? (int)$it['rejected_qty'] : '' ?>" min="0" inputmode="numeric"></div>
  <div class="mgrid2 f"><div><small>Accepted %</small><b id="accp">—</b></div><div><small>Rejected %</small><b id="rejp">—</b></div></div>
  <div id="qtymsg"></div>
</section>
<!-- 3 -->
<section class="mcard step" data-step="3"><b>Quality checks</b><div id="checks"></div></section>
<!-- 4 -->
<section class="mcard step" data-step="4"><b>Measurements</b><p class="muted" style="font-size:12px;margin:2px 0 8px">Tolerance from SKU master · auto PASS / FAIL</p>
  <table class="t" id="meas"><tr><th>Parameter</th><th>Spec</th><th>±Tol</th><th>Actual</th><th></th></tr></table>
  <button type="button" class="btn sm mt" onclick="addMeas()">+ Add parameter</button></section>
<!-- 5 -->
<section class="mcard step" data-step="5"><b>On-site tests</b><div id="tests"></div><button type="button" class="btn sm mt" onclick="addTest()">+ Add test</button></section>
<!-- 6 -->
<section class="mcard step" data-step="6"><b>Defect details</b>
  <div id="defs"></div>
  <button type="button" class="btn block mt" onclick="addDef()">+ ADD DEFECT</button>
  <div id="defsum" class="mt"></div>
</section>
<!-- 7 -->
<section class="mcard step" data-step="7"><b>Photos</b>
  <div class="fr f mt" style="grid-template-columns:1fr 1fr"><div><label>Photo type</label><select id="phkind"><option value="defect">Defect evidence</option><option value="product">Actual product image</option><option value="xrf">XRF micron report</option><option value="weight">Digital weight scale</option><option value="packaging">925 stamping / barcode / packaging</option><option value="dimension">Dimension</option><option value="other">Other</option></select></div>
    <div><label>Photo remark</label><input type="text" id="phrem" placeholder="e.g. cut mix on centre stone"></div></div>
  <div class="mgrid2 f"><label class="bigbtn" style="margin:0;color:#fff"><?= icon('camera') ?> Take photo<input type="file" accept="image/*" capture="environment" hidden onchange="addPhotos(this)"></label>
    <label class="bigbtn alt" style="margin:0">Upload<input type="file" accept="image/*" multiple hidden onchange="addPhotos(this)"></label></div>
  <div class="thumbs" id="thumbs"></div><p id="phnote" class="muted" style="font-size:12px"></p>
</section>
<!-- 8 -->
<section class="mcard step" data-step="8"><b>Final result</b>
  <div class="mstats" id="fsum"></div>
  <div class="mstats" id="fsev"></div>
  <div class="note" id="sysres" style="margin-bottom:10px"></div>
  <div class="pfn" style="grid-template-columns:repeat(4,1fr)" id="fres">
    <?php foreach (['PASS', 'FAIL', 'PENDING', 'CONDITIONAL'] as $r): ?><label style="font-size:11.5px"><input type="radio" name="result" value="<?= $r === 'PASS' ? 'PASS' : ($r === 'FAIL' ? 'FAIL' : $r) ?>" <?= $it['inspector_result'] === $r ? 'checked' : '' ?>><?= $r === 'CONDITIONAL' ? 'COND. PASS' : $r ?></label><?php endforeach ?>
  </div>
  <div class="f mt"><label>Inspector remark</label><textarea name="remark"><?= h($it['remark']) ?></textarea></div>
  <div class="f"><label>Action required (CAPA)</label><textarea name="action_required"><?= h($it['action_required']) ?></textarea></div>
  <b style="font-size:13.5px">Factory evaluation</b>
  <?php foreach (['factory_coop' => 'Factory cooperation', 'factory_line' => 'Factory line organization', 'factory_ready' => 'Overall factory QC readiness'] as $fk => $fl): ?>
  <small style="display:block;margin-top:8px"><?= $fl ?></small><div class="sev"><?php foreach (['GOOD', 'AVERAGE', 'POOR'] as $o): ?><label><input type="radio" name="<?= $fk ?>" value="<?= $o ?>" <?= ($it[$fk] ?: 'GOOD') === $o ? 'checked' : '' ?>><?= $o ?></label><?php endforeach ?></div>
  <?php endforeach ?>
</section>
</form>
<div id="navbtns" style="display:grid;grid-template-columns:1fr 2fr;gap:8px">
  <button class="bigbtn alt" id="prevb" onclick="go(cur-1)">‹ BACK</button>
  <button class="bigbtn" id="nextb" onclick="saveNext()">SAVE &amp; NEXT</button>
</div>
<div id="submitbtns" style="display:none;grid-template-columns:1fr 2fr;gap:8px">
  <button class="bigbtn alt" onclick="saveStep(8).then(()=>toast('Draft saved'))">Save draft</button>
  <button class="bigbtn gold" onclick="submitItem()">SUBMIT FOR REVIEW</button>
</div>
<?php if (!$it['started_at'] && $editable): ?><div id="startwrap" class="mt"><button class="bigbtn" onclick="startQc()">START QC</button></div><?php endif ?>
<div class="mcard mt"><small class="muted">Other SKUs in this job</small><div class="mlist"><?php foreach ($siblings as $s): if ($s['id'] == $it['id']) continue; ?><a href="<?= url('app/inspect', ['item' => $s['id']]) ?>"><b class="mono"><?= h($s['sku']) ?></b><?= badge($s['status']) ?></a><?php endforeach ?></div></div>

<dialog id="holddlg"><div class="dh"><b>Hold SKU for approval</b><button class="btn sm" data-close>✕</button></div><div class="db">
  <p class="muted" style="font-size:12.5px;margin-top:0">Use when you need a decision before continuing (e.g. shade difference, design deviation). The SKU pauses; other SKUs continue.</p>
  <label>Who must approve?</label>
  <div class="sev f" style="grid-template-columns:1fr 1fr"><label><input type="radio" name="hfor" value="client" checked>CLIENT</label><label><input type="radio" name="hfor" value="vendor">VENDOR</label></div>
  <div class="f"><label>What needs approval?</label><textarea id="hreason" placeholder="Centre stone shade lighter than approved sample — accept?"></textarea></div>
  <button class="bigbtn ruby" onclick="doHold()">HOLD FOR APPROVAL</button></div></dialog>
<div id="toast" style="position:fixed;left:50%;bottom:80px;transform:translateX(-50%);background:var(--ink);color:#fff;padding:9px 16px;border-radius:20px;font-size:13px;display:none;z-index:50"></div>

<script>
const S = <?= json_encode($S, JSON_UNESCAPED_UNICODE) ?>;
const STEPN = <?= json_encode(STEPS) ?>;
const DK = 'qt_draft_' + S.item;
let cur = 1;
const F = document.getElementById('insp');
const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
function toast(m){const t=document.getElementById('toast');t.textContent=m;t.style.display='block';clearTimeout(t._h);t._h=setTimeout(()=>t.style.display='none',2600);}
function pfn(name, val){return `<div class="pfn">${['PASS','FAIL','NA'].map(v=>`<label><input type="radio" name="${name}" value="${v}" ${val===v?'checked':''}>${v}</label>`).join('')}</div>`;}
/* --- restore local draft (offline edits not yet synced) --- */
try{const d=JSON.parse(localStorage.getItem(DK)||'null');if(d&&S.editable){Object.assign(S,d.S||{});}}catch(e){}
function persist(){if(!S.editable)return;try{localStorage.setItem(DK,JSON.stringify({S:{checks:S.checks,tests:S.tests,meas:S.meas,defects:S.defects},t:Date.now()}));}catch(e){}}
/* --- renderers --- */
function rChecks(){document.getElementById('checks').innerHTML=S.checks.map((c,i)=>`<div class="chkrow"><b style="font-size:13.5px">${i+1}. ${esc(c.name)}</b>${pfn('ck'+i,c.result)}<input type="text" list="rm${i}" placeholder="QC remark" data-ckr="${i}" value="${esc(c.remark)}" style="margin-top:6px;${c.result?'':'display:none'}"><datalist id="rm${i}">${Object.values((S.remarks||{})[c.name]||{}).filter(Boolean).map(r=>`<option value="${esc(r)}">`).join('')}</datalist></div>`).join('');}
function rTests(){document.getElementById('tests').innerHTML=S.tests.map((c,i)=>`<div class="chkrow"><div style="display:flex;justify-content:space-between;align-items:center"><b style="font-size:13.5px">${esc(c.name)}</b><span style="font-size:12px">Qty <input type="number" data-tq="${i}" value="${esc(c.qty)}" style="width:60px;padding:4px 6px"></span></div>${pfn('ts'+i,c.result)}<input type="text" data-tr="${i}" value="${esc(c.remark)}" placeholder="Method / QC remark" style="margin-top:6px"></div>`).join('');}
function mres(m){if(m.actual===''||m.actual==null||m.spec==='')return '';return Math.abs(parseFloat(m.actual)-parseFloat(m.spec))<=Math.abs(parseFloat(m.tol)||0)+1e-9?'PASS':'FAIL';}
function rMeas(){const t=document.getElementById('meas');t.innerHTML='<tr><th>Parameter</th><th>Spec</th><th>±Tol</th><th>Actual</th><th></th></tr>'+S.meas.map((m,i)=>{const r=mres(m);return `<tr><td><input data-m="${i}" data-k="param" value="${esc(m.param)}" style="padding:5px;width:84px"></td><td><input data-m="${i}" data-k="spec" value="${esc(m.spec)}" style="padding:5px;width:56px" inputmode="decimal"></td><td><input data-m="${i}" data-k="tol" value="${esc(m.tol)}" style="padding:5px;width:46px" inputmode="decimal"></td><td><input data-m="${i}" data-k="actual" value="${esc(m.actual)}" style="padding:5px;width:60px" inputmode="decimal"></td><td>${r?`<span class="badge b-${r.toLowerCase()}">${r}</span>`:''}</td></tr>`;}).join('');}
function catOpts(sel){return Object.keys(S.dm).map(c=>`<option ${c===sel?'selected':''}>${esc(c)}</option>`).join('');}
function typeOpts(cat,sel){return (S.dm[cat]||[]).map(d=>`<option ${d.n===sel?'selected':''}>${esc(d.n)}</option>`).join('');}
function rDefs(){
  const el=document.getElementById('defs');
  el.innerHTML=S.defects.map((d,i)=>`<div class="chkrow"><div class="mgrid2"><div><small>Defect category</small><select data-d="${i}" data-k="category">${catOpts(d.category)}</select></div><div><small>Defect type</small><select data-d="${i}" data-k="type">${typeOpts(d.category,d.type)}</select></div></div>
   <small style="display:block;margin-top:8px">Severity</small><div class="sev">${['CRITICAL','MAJOR','MINOR'].map(s=>`<label><input type="radio" name="sv${i}" value="${s}" ${d.severity===s?'checked':''}>${s}</label>`).join('')}</div>
   <div class="mgrid2 mt"><div><small>Defect quantity</small><input type="number" data-d="${i}" data-k="qty" value="${esc(d.qty)}" min="0" inputmode="numeric"></div><div><small>Remark</small><input type="text" data-d="${i}" data-k="remark" value="${esc(d.remark)}"></div></div>
   <button type="button" class="btn sm mt" onclick="S.defects.splice(${i},1);rDefs();persist()">Remove</button></div>`).join('')||'<p class="muted" style="font-size:13px">No defects. Tap “Add defect” if rejected qty &gt; 0.</p>';
  defSummary();
}
function sevTotals(){const t={CRITICAL:0,MAJOR:0,MINOR:0};S.defects.forEach(d=>t[d.severity]=(t[d.severity]||0)+(+d.qty||0));return t;}
function defSummary(){const rej=+F.rejected_qty.value||0,tot=S.defects.reduce((a,d)=>a+(+d.qty||0),0),by={};S.defects.forEach(d=>by[d.category]=(by[d.category]||0)+(+d.qty||0));const sv=sevTotals();
  document.getElementById('defsum').innerHTML=`<div class="mlist">${Object.entries(by).map(([k,v])=>`<a><span>${esc(k)}</span><b>${v}</b></a>`).join('')}</div><div class="${tot>=rej?'qtyok':'qtybad'}">Total defects ${tot} ${tot===rej?'=':(tot>rej?'≥':'<')} Rejected ${rej} ${tot>=rej?'✓':'— add defects for all rejected pcs'}</div><p class="muted" style="font-size:12px">Critical ${sv.CRITICAL} · Major ${sv.MAJOR} · Minor ${sv.MINOR}</p>`;}
function rPhotos(){document.getElementById('thumbs').innerHTML=S.photos.map((p,i)=>`<figure class="${p.pending?'pend':''}"><img src="${p.url}"><figcaption>${esc(p.kind)}${p.remark?' · '+esc(p.remark):''}</figcaption>${S.editable&&p.id?`<button type="button" onclick="delPhoto(${i})" style="position:absolute;top:3px;right:3px;border:0;border-radius:50%;background:#0008;color:#fff;width:22px;height:22px">✕</button>`:''}</figure>`).join('');
  const dq=S.defects.reduce((a,d)=>a+(+d.qty||0),0),dp=S.photos.filter(p=>p.kind==='defect').length;
  document.getElementById('phnote').innerHTML=S.needPhoto&&dq>0?(dp?`✓ ${dp} defect photo(s)`:'<b style="color:var(--ruby)">At least 1 defect photo is required (defect qty &gt; 0)</b>'):`${S.photos.length} photo(s)`;}
function qtyCheck(){const i=+F.inspected_qty.value||0,a=+F.accepted_qty.value||0,r=+F.rejected_qty.value||0;
  document.getElementById('accp').textContent=i?(a*100/i).toFixed(2)+'%':'—';document.getElementById('rejp').textContent=i?(r*100/i).toFixed(2)+'%':'—';
  const ok=a+r===i&&i>0;document.getElementById('qtymsg').innerHTML=(F.accepted_qty.value===''&&F.rejected_qty.value==='')?'':ok?`<div class="qtyok">${a} + ${r} = ${i} ✓ matches inspected qty</div>`:`<div class="qtybad">Quantity mismatch — Accepted + Rejected (${a+r}) ≠ Inspected (${i}). Please check before proceeding.</div>`;return ok;}
function sysResult(){const sv=sevTotals(),insp=+F.inspected_qty.value||1;let fail=false,why='';
  if(S.aql&&S.plan){for(const k of ['critical','major','minor']){const p=S.plan[k];if(p&&p.re!=null&&sv[k.toUpperCase()]>=p.re){fail=true;why=`${k} ${sv[k.toUpperCase()]} ≥ Re ${p.re}`;}}}
  else{const rp=(+F.rejected_qty.value||0)*100/insp;if(rp>=S.threshold){fail=true;why=`Rej/Repair ${rp.toFixed(1)}% ≥ ${S.threshold}%`;}else why=`Rej/Repair ${rp.toFixed(1)}% < ${S.threshold}% — defective pcs segregated`;}
  return [fail?'FAIL':'PASS',why];}
function rFinal(){const i=+F.inspected_qty.value||0,a=+F.accepted_qty.value||0,r=+F.rejected_qty.value||0,sv=sevTotals(),[res,why]=sysResult();
  document.getElementById('fsum').innerHTML=`<div><b>${i}</b><small>Inspected</small></div><div><b>${a}</b><small>Accepted</small></div><div><b style="color:var(--ruby)">${r}</b><small>Rejected</small></div>`;
  document.getElementById('fsev').innerHTML=`<div><b>${sv.CRITICAL}</b><small>Critical</small></div><div><b>${sv.MAJOR}</b><small>Major</small></div><div><b>${sv.MINOR}</b><small>Minor</small></div>`;
  document.getElementById('sysres').innerHTML=`System suggests: <b>${res}</b>${why?' ('+esc(why)+')':''} — QC manager decides.`+([...S.checks,...S.tests].some(c=>c.result==='FAIL')?'<br>⚠ Some checkpoints / tests are FAIL — mention in remarks.':'');
  if(!F.querySelector('[name=result]:checked')){const x=F.querySelector(`[name=result][value=${res}]`);if(x)x.checked=true;}}
/* --- collect --- */
function collect(step){
  if(step===1) return {insp_date:F.insp_date.value, reinspection:(F.querySelector('[name=reinspection]:checked')||{}).value==='PASS', gps:S.gpsv||'', lot_no:F.lot_no.value, xrf_machine:F.xrf_machine.value, master_sample:F.master_sample.value};
  if(step===2) return {inspected_qty:+F.inspected_qty.value, accepted_qty:+F.accepted_qty.value, rejected_qty:+F.rejected_qty.value};
  if(step===3) return {checks:S.checks}; if(step===4) return {meas:S.meas.map(m=>({...m,result:mres(m)}))};
  if(step===5) return {tests:S.tests}; if(step===6) return {defects:S.defects};
  if(step===8){const g=n=>(F.querySelector(`[name=${n}]:checked`)||{}).value||''; return {result:g('result'), remark:F.remark.value, action_required:F.action_required.value, factory_coop:g('factory_coop'), factory_line:g('factory_line'), factory_ready:g('factory_ready')};}
  return {};
}
async function saveStep(step){
  if(!S.editable) return true;
  if(step===2 && !qtyCheck()){toast('Fix quantity mismatch first');return false;}
  persist();
  try{const r=await qtSend({action:'save',item_id:S.item,step,data:collect(step)});
    document.getElementById('savedat').textContent=r.queued?'Saved on phone (offline)':'Auto-saved '+(r.saved_at||'');return true;}
  catch(e){toast(e.message);return false;}
}
async function saveNext(){ if(await saveStep(cur)) go(cur+1); }
function go(n){ if(n<1||n>8)return; cur=n; document.querySelectorAll('.step').forEach(s=>s.style.display=+s.dataset.step===n?'':'none');
  document.querySelectorAll('#stepper a').forEach(a=>{const k=+a.dataset.go;a.classList.toggle('cur',k===n);a.classList.toggle('done',k<n||k<=S.step&&k!==n);});
  document.getElementById('steplbl').textContent=`Step ${n} of 8 · ${STEPN[n]}`;
  document.getElementById('navbtns').style.display=n===8?'none':'grid'; document.getElementById('submitbtns').style.display=n===8&&S.editable?'grid':'none';
  document.getElementById('prevb').disabled=n===1; if(n===6)defSummary(); if(n===7)rPhotos(); if(n===8)rFinal(); window.scrollTo(0,0);}
async function submitItem(){ if(!await saveStep(8)) return;
  if(!navigator.onLine){toast('You are offline — data is saved. Submit when back online.');return;}
  await qtFlush();
  try{const r=await qtSend({action:'submit',item_id:S.item}); localStorage.removeItem(DK);
    alert('Submitted for review ✓'); location.href = r.next ? QT.base+'/app/inspect?item='+r.next : QT.base+'/app/job?id=<?= (int)$job['id'] ?>';}
  catch(e){alert(e.message);}
}
function startQc(){const done=gps=>{S.gpsv=gps;qtSend({action:'start',item_id:S.item,gps}).then(()=>{document.getElementById('startwrap').remove();document.getElementById('gpsline').textContent=gps?'GPS logged · '+gps:'Started';go(1);});};
  if(S.gps&&navigator.geolocation){navigator.geolocation.getCurrentPosition(p=>done(p.coords.latitude.toFixed(4)+', '+p.coords.longitude.toFixed(4)+' · '+new Date().toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'})),()=>done('location denied · '+new Date().toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'})),{timeout:8000});}else done('');}
async function addPhotos(inp){for(const f of inp.files){try{const data=await compressImage(f);const p={kind:document.getElementById('phkind').value,remark:document.getElementById('phrem').value,url:data,pending:true};S.photos.push(p);rPhotos();
  const r=await qtSend({action:'photo',item_id:S.item,kind:p.kind,remark:p.remark,data});if(!r.queued){p.id=r.id;p.url=r.url;p.pending=false;}rPhotos();}catch(e){toast(e.message||'Photo failed');}} inp.value='';}
async function delPhoto(i){if(!confirm('Delete photo?'))return;const p=S.photos[i];await qtSend({action:'photo_delete',item_id:S.item,photo_id:p.id});S.photos.splice(i,1);rPhotos();}
async function doHold(){const reason=document.getElementById('hreason').value.trim();if(!reason){toast('Write what needs approval');return;}
  try{await saveStep(cur);const r=await qtSend({action:'hold',item_id:S.item,hold_for:document.querySelector('[name=hfor]:checked').value,reason});alert(r.queued?'Saved — hold will be sent when online.':'SKU put on HOLD — '+document.querySelector('[name=hfor]:checked').value+' notified.');location.href=QT.base+'/app/job?id=<?= (int)$job['id'] ?>';}catch(e){toast(e.message);}}
window.addMeas=()=>{S.meas.push({param:'',spec:'',tol:'',unit:'',actual:''});rMeas();};
window.addTest=()=>{const n=prompt('Test name');if(n){S.tests.push({name:n,qty:2,result:'',remark:''});rTests();}};
window.addDef=()=>{const c=Object.keys(S.dm)[0];S.defects.push({category:c,type:(S.dm[c][0]||{}).n,severity:(S.dm[c][0]||{}).s||'MAJOR',qty:'',remark:''});rDefs();};
/* --- events --- */
F.addEventListener('change',e=>{const t=e.target,n=t.name||'';
  if(n.startsWith('ck')){const i=+n.slice(2),c=S.checks[i],pre=((S.remarks||{})[c.name]||{}),old=c.result;c.result=t.value;const r=document.querySelector(`[data-ckr="${i}"]`);
    if(r){r.style.display='';if(!r.value||Object.values(pre).includes(r.value)){r.value=pre[t.value]||'';c.remark=r.value;}}}
  if(n.startsWith('ts')) S.tests[+n.slice(2)].result=t.value;
  if(n.startsWith('sv')) S.defects[+n.slice(2)].severity=t.value;
  if(t.dataset.d!==undefined){const d=S.defects[+t.dataset.d];d[t.dataset.k]=t.value;if(t.dataset.k==='category'){const f=(S.dm[t.value]||[])[0]||{};d.type=f.n;d.severity=f.s||d.severity;rDefs();}if(t.dataset.k==='type'){const f=(S.dm[d.category]||[]).find(x=>x.n===t.value);if(f){d.severity=f.s;rDefs();}}}
  persist();});
F.addEventListener('input',e=>{const t=e.target;
  if(t.dataset.ckr!==undefined) S.checks[+t.dataset.ckr].remark=t.value;
  if(t.dataset.tq!==undefined) S.tests[+t.dataset.tq].qty=t.value;
  if(t.dataset.tr!==undefined) S.tests[+t.dataset.tr].remark=t.value;
  if(t.dataset.m!==undefined){S.meas[+t.dataset.m][t.dataset.k]=t.value;const tr=t.closest('tr'),r=mres(S.meas[+t.dataset.m]);tr.lastElementChild.innerHTML=r?`<span class="badge b-${r.toLowerCase()}">${r}</span>`:'';}
  if(t.dataset.d!==undefined&&t.dataset.k!=='category'&&t.dataset.k!=='type'){S.defects[+t.dataset.d][t.dataset.k]=t.value;defSummary();}
  if(['inspected_qty','accepted_qty','rejected_qty'].includes(t.name)){ if(t.name==='accepted_qty'&&F.inspected_qty.value){F.rejected_qty.value=Math.max(0,(+F.inspected_qty.value)-(+t.value||0));} qtyCheck(); }
  persist();});
document.querySelectorAll('#stepper a').forEach(a=>a.addEventListener('click',e=>{e.preventDefault();saveStep(cur);go(+a.dataset.go);}));
rChecks();rTests();rMeas();rDefs();rPhotos();qtyCheck();
if(!S.editable){F.querySelectorAll('input,select,textarea,button').forEach(x=>x.disabled=true);document.querySelectorAll('.step button,#submitbtns button').forEach(b=>b.disabled=true);document.getElementById('nextb').textContent='NEXT ›';window.saveNext=()=>go(cur+1);}
go(<?= $returned ? (int)(array_search($returned[array_key_first($returned)] ?? '', STEPS, true) ?: 1) : max(1, min(8, (int)$it['step'] ?: 1)) ?>);
</script>
<?php app_end('home');
