<?php
$me = user();
$jobs = all("SELECT j.*, c.code client_code, v.name vendor_name, v.location vloc, (SELECT COUNT(*) FROM qc_items i WHERE i.job_id=j.id) skus,
   (SELECT COUNT(*) FROM qc_items i WHERE i.job_id=j.id AND i.status IN ('SUBMITTED','UNDER_REVIEW','APPROVED')) done,
   (SELECT COUNT(*) FROM qc_items i WHERE i.job_id=j.id AND i.status='RETURNED') ret,
   (SELECT COUNT(*) FROM qc_items i WHERE i.job_id=j.id AND i.status='HOLD') hold
   FROM qc_jobs j JOIN clients c ON c.id=j.client_id JOIN vendors v ON v.id=j.vendor_id
   WHERE (j.inspector_id=? OR j.inspector2_id=?) AND j.status IN ('ASSIGNED','IN_PROGRESS','SUBMITTED','UNDER_REVIEW','RETURNED')
   ORDER BY CASE WHEN j.status='RETURNED' THEN 0 WHEN j.status='IN_PROGRESS' THEN 1 ELSE 2 END, j.scheduled_date", [$me['id'], $me['id']]);
$today = count(array_filter($jobs, fn($j) => $j['scheduled_date'] === today()));
$pending = (int)val("SELECT COUNT(*) FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id WHERE (j.inspector_id=? OR j.inspector2_id=?) AND i.status IN ('PENDING','IN_PROGRESS','HOLD','RETURNED','COMPLETED') AND j.status NOT IN ('CANCELLED','CLOSED')", [$me['id'], $me['id']]);
$completed = (int)val("SELECT COUNT(*) FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id WHERE (j.inspector_id=? OR j.inspector2_id=?) AND i.status IN ('SUBMITTED','UNDER_REVIEW','APPROVED')", [$me['id'], $me['id']]);
$h = (int)date('G'); $greet = $h < 12 ? 'Good morning' : ($h < 17 ? 'Good afternoon' : 'Good evening');
app_start('Q-TRACK', null, '<a href="' . url('app/alerts') . '" style="color:#fff">' . icon('bell') . '</a>');
?>
<div class="offbar">You are offline — everything you enter is saved on this phone and will sync automatically.</div>
<div class="mhello"><?= $greet ?>,<b><?= h(explode(' ', $me['name'])[0]) ?> · <?= h($me['emp_code']) ?></b></div>
<div class="mstats"><div><b><?= sprintf('%02d', $today) ?></b><small>Today's QC</small></div><div><b><?= sprintf('%02d', $pending) ?></b><small>Pending SKUs</small></div><div><b><?= $completed ?></b><small>Completed</small></div></div>
<p class="muted" style="font-size:12px;margin:-4px 0 12px"><span class="net" id="net2" style="color:var(--ok)">●</span> <span id="nettxt">ONLINE</span> · <span id="syncmsg">synced</span> <span style="display:none">· <b id="qcount">0</b> pending upload <a href="#" onclick="qtFlush();return false">sync now</a></span></p>

<div id="scanbox" class="mcard" style="display:<?= get('scan') ? 'block' : 'none' ?>">
  <b>Scan SKU QR / search design</b>
  <video id="qrvid" playsinline muted style="display:none;margin-top:8px"></video>
  <form onsubmit="findSku(event)" class="mt" style="display:flex;gap:8px"><input type="search" id="skuq" placeholder="Search SKU / design no."><button class="btn">Go</button></form>
  <div id="skures" class="mlist"></div>
</div>

<h3 style="font-size:14px;margin:6px 0 10px">My assigned QC</h3>
<?php if (!$jobs) echo '<div class="mcard">' . empty_state('No QC jobs assigned right now.') . '</div>'; ?>
<?php foreach ($jobs as $j): $label = $j['ret'] ? 'RETURNED' : $j['status']; ?>
<div class="mcard">
  <div style="display:flex;justify-content:space-between;align-items:center"><b><?= h($j['client_code'] . ' – ' . strtoupper($j['vendor_name'])) ?></b><?= badge($label) ?></div>
  <p class="muted" style="margin:6px 0 10px;font-size:12.5px"><?= fdate($j['scheduled_date']) ?> · <?= h($j['time_slot']) ?> · <?= (int)$j['skus'] ?> SKU · <?= h($j['location']) ?><br>PO <?= h($j['po_no']) ?> · <?= h($j['job_no']) ?> · <?= $j['done'] ?>/<?= $j['skus'] ?> submitted<?= $j['hold'] ? ' · <b style="color:#b4561b">' . $j['hold'] . ' on hold</b>' : '' ?><?= $j['ret'] ? ' · <b style="color:var(--ruby)">' . $j['ret'] . ' returned</b>' : '' ?></p>
  <?php $view = !$j['ret'] && $j['done'] == $j['skus']; ?><a class="bigbtn <?= $j['status'] === 'ASSIGNED' ? '' : ($j['ret'] ? 'ruby' : 'alt') ?>" href="<?= url('app/job', ['id' => $j['id']]) ?>"><?= $j['status'] === 'ASSIGNED' ? 'START QC' : ($j['ret'] ? 'FIX & RESUBMIT' : ($view ? 'VIEW' : 'CONTINUE QC')) ?></a>
</div>
<?php endforeach ?>
<script>
const SKUS = <?= json_encode(array_map(fn($r) => ['id' => (int)$r['id'], 'sku' => $r['sku'], 'p' => $r['product'], 'q' => (int)$r['ready_qty'], 's' => $r['status'], 'j' => $r['job_no']],
  all("SELECT i.*, j.job_no FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id WHERE (j.inspector_id=? OR j.inspector2_id=?) AND j.status IN ('ASSIGNED','IN_PROGRESS','SUBMITTED','UNDER_REVIEW','RETURNED')", [$me['id'], $me['id']]))) ?>;
function showRes(q){const r=SKUS.filter(s=>s.sku.toUpperCase().includes(q.toUpperCase()));document.getElementById('skures').innerHTML=r.map(s=>`<a href="${QT.base}/app/inspect?item=${s.id}"><span><b>${s.sku}</b><small>${s.p} · ${s.q} pcs · ${s.j}</small></span><span class="badge">${s.s}</span></a>`).join('')||'<div class="empty">No matching SKU in your jobs.</div>';
 if(r.length===1 && q.length>5) location.href=QT.base+'/app/inspect?item='+r[0].id;}
function findSku(e){e.preventDefault();showRes(document.getElementById('skuq').value);}
async function startScan(){ if(!('BarcodeDetector' in window)) return; try{const v=document.getElementById('qrvid');const st=await navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'}});v.srcObject=st;v.style.display='block';await v.play();
 const bd=new BarcodeDetector({formats:['qr_code','code_128','ean_13']});const tick=async()=>{try{const c=await bd.detect(v);if(c.length){st.getTracks().forEach(t=>t.stop());v.style.display='none';document.getElementById('skuq').value=c[0].rawValue;showRes(c[0].rawValue);return;}}catch(e){}requestAnimationFrame(tick);};tick();}catch(e){} }
<?php if (get('scan')): ?>startScan();<?php endif ?>
function nt(){const on=navigator.onLine;document.getElementById('nettxt').textContent=on?'ONLINE':'OFFLINE';document.getElementById('net2').style.color=on?'var(--ok)':'var(--warn)';}
addEventListener('online',nt);addEventListener('offline',nt);nt();
</script>
<?php app_end('home');
