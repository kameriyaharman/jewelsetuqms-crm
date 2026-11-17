<?php
$job = job_load((int)get('id'));
$me = user();
if (!$job || ((int)$job['inspector_id'] !== (int)$me['id'] && (int)$job['inspector2_id'] !== (int)$me['id'])) { flash('Job not found.', 'err'); redirect('app'); }
$items = all('SELECT * FROM qc_items WHERE job_id=? ORDER BY id', [$job['id']]);
$atts = all('SELECT * FROM attachments WHERE job_id=?', [$job['id']]);
$tot = array_sum(array_column($items, 'order_qty'));
app_start($job['job_no'], url('app'));
?>
<div class="mcard"><div class="mgrid2">
  <div><small>Client</small><b><?= h($job['client_code']) ?></b></div><div><small>Vendor</small><b><?= h($job['vendor_name']) ?></b></div>
  <div><small>QC date</small><b><?= fdate($job['scheduled_date'], 'd-m-Y') ?> <?= h($job['time_slot']) ?></b></div><div><small>Type</small><b><?= h($job['qc_type']) ?></b></div>
  <div><small>Total SKU</small><b><?= count($items) ?></b></div><div><small>Order qty</small><b><?= num($tot) ?> pcs</b></div>
  <div><small>PO</small><b><?= h($job['po_no']) ?></b></div><div><small>Scope</small><b><?= h(scope_label($job)) ?></b></div>
</div>
<p class="muted" style="font-size:12px;margin:10px 0 0">📍 <?= h($job['location']) ?> — <?= h($job['vendor_address']) ?></p>
<?php if ($job['special_req']): ?><div class="note mt" style="font-size:12px"><b>Special:</b> <?= h($job['special_req']) ?></div><?php endif ?>
<?php if ($atts): ?><div class="mt"><?php foreach ($atts as $a): ?><a class="pill" target="_blank" href="<?= url('file', ['p' => $a['path']]) ?>">📎 <?= h($a['name']) ?></a><?php endforeach ?></div><?php endif ?>
</div>
<input type="search" placeholder="Search SKU…" oninput="document.querySelectorAll('.skurow').forEach(a=>a.style.display=a.dataset.s.includes(this.value.toUpperCase())?'':'none')" style="margin-bottom:10px">
<div class="mcard"><b>Product list</b><div class="mlist">
<?php foreach ($items as $i): ?>
  <a class="skurow" data-s="<?= h(strtoupper($i['sku'])) ?>" href="<?= url('app/inspect', ['item' => $i['id']]) ?>"><span><b class="mono"><?= h($i['sku']) ?></b><small><?= h($i['product']) ?> · <?= num($i['ready_qty']) ?> pcs · insp <?= num($i['insp_qty']) ?></small></span><?= badge($i['status']) ?></a>
<?php endforeach ?></div></div>
<?php $first = null; foreach ($items as $i) if (in_array($i['status'], ['RETURNED', 'IN_PROGRESS', 'PENDING', 'COMPLETED'], true)) { $first = $i; break; } ?>
<?php if ($first): ?><a class="bigbtn" href="<?= url('app/inspect', ['item' => $first['id']]) ?>"><?= $first['status'] === 'PENDING' ? 'START INSPECTION' : 'CONTINUE · ' . h($first['sku']) ?></a>
<?php else: ?><div class="qtyok" style="text-align:center">All SKUs submitted — waiting for QC manager review.</div><?php endif ?>
<?php app_end('home');
