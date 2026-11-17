<?php
$u = user(); $r = role();
if ($r === 'vendor' && !flag('enable_vendor_qc_request')) { flash('Self-service QC requests are not enabled yet. Please contact JEWELSETU.', 'warn'); redirect('vendor'); }
if ($r === 'client' && !flag('enable_client_qc_request')) { flash('Self-service QC requests are not enabled yet. Please contact JEWELSETU.', 'warn'); redirect('client'); }

$job = null; $items = [];
if ($id = (int)get('id')) {
    $job = job_load($id);
    if (!$job || !can_see_job($job) || !in_array($job['status'], ['DRAFT', 'REQUESTED', 'SCHEDULED'], true) || (in_array($r, ['client', 'vendor'], true) && $job['status'] !== 'DRAFT' && $job['status'] !== 'REQUESTED')) {
        flash('This request can no longer be edited.', 'err'); redirect('requests');
    }
    $items = all('SELECT * FROM qc_items WHERE job_id=? ORDER BY id', [$id]);
}
// option lists respecting mapping
if ($r === 'vendor') { $clients = all('SELECT c.* FROM clients c JOIN client_vendor m ON m.client_id=c.id WHERE m.vendor_id=? AND c.status=? ORDER BY c.code', [$u['vendor_id'], 'ACTIVE']); $vendors = all('SELECT * FROM vendors WHERE id=?', [$u['vendor_id']]); }
elseif ($r === 'client') { $clients = all('SELECT * FROM clients WHERE id=?', [$u['client_id']]); $vendors = all('SELECT v.* FROM vendors v JOIN client_vendor m ON m.vendor_id=v.id WHERE m.client_id=? AND v.status=? ORDER BY v.name', [$u['client_id'], 'ACTIVE']); }
else { $clients = all("SELECT * FROM clients WHERE status='ACTIVE' ORDER BY code"); $vendors = all("SELECT * FROM vendors WHERE status='ACTIVE' ORDER BY name"); }
$mapping = []; foreach (all('SELECT * FROM client_vendor') as $m) $mapping[$m['client_id']][] = (int)$m['vendor_id'];

if (is_post()) {
    $cid = $r === 'client' ? (int)$u['client_id'] : (int)post('client_id');
    $vid = $r === 'vendor' ? (int)$u['vendor_id'] : (int)post('vendor_id');
    $err = [];
    if (!in_array($vid, $mapping[$cid] ?? [], true)) $err[] = 'Selected vendor is not mapped to this client.';
    if (!trim((string)post('po_no'))) $err[] = 'PO number is required.';
    $scope = post('scope') === 'SAMPLE' ? 'SAMPLE' : 'BULK';
    $mode = in_array(post('bulk_mode'), ['100', 'AQL', 'DEFINED'], true) ? post('bulk_mode') : '100';
    $excess = post('allow_excess') ? 1 : 0;
    $rows = [];
    foreach ((array)post('sku', []) as $k => $sku) {
        $sku = trim((string)$sku); if ($sku === '') continue;
        $oq = (int)($_POST['order_qty'][$k] ?? 0); $rq = (int)($_POST['ready_qty'][$k] ?? 0);
        if ($rq > $oq && !$excess) $err[] = "SKU $sku: Ready qty ($rq) is more than Order qty ($oq). Tick “Allow excess quantity” if this is intended.";
        if ($rq <= 0) $err[] = "SKU $sku: Ready qty must be more than 0.";
        if ($scope === 'BULK' && $mode === 'DEFINED' && (int)post('defined_qty') > $rq) $err[] = "SKU $sku: Defined sample qty is more than Ready qty.";
        $rows[] = ['k' => $k, 'id' => (int)($_POST['item_id'][$k] ?? 0), 'sku' => strtoupper($sku)] + array_map(fn($f) => trim((string)($_POST[$f][$k] ?? '')), array_combine(
            ['product', 'metal', 'plating', 'target_weight', 'length', 'lock_type', 'stamping'], ['product', 'metal', 'plating', 'target_weight', 'length', 'lock_type', 'stamping'])) + ['order_qty' => $oq, 'ready_qty' => $rq];
    }
    if (!$rows) $err[] = 'Add at least one SKU / design.';
    $skus = array_column($rows, 'sku'); if (count($skus) !== count(array_unique($skus))) $err[] = 'Duplicate SKU in this request.';
    if ($err) { foreach ($err as $e) flash($e, 'err'); $_SESSION['old_req'] = $_POST; redirect('request-new', $job ? ['id' => $job['id']] : []); }

    $submit = post('action') === 'submit';
    $data = ['client_id' => $cid, 'vendor_id' => $vid, 'po_no' => trim((string)post('po_no')), 'qc_type' => post('qc_type'), 'location' => post('location'),
        'requested_date' => post('requested_date') ?: null, 'scope' => $scope, 'bulk_mode' => $mode, 'insp_level' => post('insp_level', 'II'),
        'aql_critical' => (float)post('aql_critical', 0), 'aql_major' => (float)post('aql_major', 2.5), 'aql_minor' => (float)post('aql_minor', 4.0),
        'defined_qty' => (int)post('defined_qty', 0), 'allow_excess' => $excess, 'specs' => post('specs'), 'special_req' => post('special_req')];
    if ($job) {
        if ($job['status'] === 'DRAFT' && $submit) $data['status'] = 'REQUESTED';
        update('qc_jobs', $data, 'id=?', [$job['id']]); $jid = $job['id']; audit('job', $jid, 'UPDATE');
    } else {
        $data += ['req_no' => next_req_no(), 'source' => match ($r) { 'vendor' => 'Vendor portal', 'client' => 'Client portal', default => 'Back Office' },
            'status' => $submit ? 'REQUESTED' : 'DRAFT', 'created_by' => $u['id'], 'created_at' => now()];
        $jid = insert('qc_jobs', $data); audit('job', $jid, 'CREATE', 'status', null, $data['status']);
    }
    $jobRow = one('SELECT * FROM qc_jobs WHERE id=?', [$jid]);
    $keep = [];
    foreach ($rows as $row) {
        $plan = item_plan($jobRow, $row['ready_qty']);
        $d = array_diff_key($row, ['k' => 1, 'id' => 1]) + ['insp_qty' => $plan['insp_qty'], 'plan_json' => json_encode($plan['plan']), 'updated_at' => now()];
        if ($row['id'] && one('SELECT id FROM qc_items WHERE id=? AND job_id=?', [$row['id'], $jid])) { update('qc_items', $d, 'id=?', [$row['id']]); $iid = $row['id']; }
        else $iid = insert('qc_items', $d + ['job_id' => $jid, 'status' => 'PENDING']);
        $keep[] = $iid;
        // per-row spec file
        if (!empty($_FILES['row_file']['name'][$row['k']])) {
            $f = ['name' => $_FILES['row_file']['name'][$row['k']], 'tmp_name' => $_FILES['row_file']['tmp_name'][$row['k']], 'error' => $_FILES['row_file']['error'][$row['k']]];
            if ($s = save_upload($f, 'specs')) insert('attachments', ['job_id' => $jid, 'item_id' => $iid, 'path' => $s['path'], 'name' => $s['name'], 'created_at' => now()]);
        }
    }
    q('DELETE FROM qc_items WHERE job_id=? AND id NOT IN (' . implode(',', array_map('intval', $keep)) . ')', [$jid]);
    foreach (files_array('files') as $f) if ($s = save_upload($f, 'specs')) insert('attachments', ['job_id' => $jid, 'item_id' => null, 'path' => $s['path'], 'name' => $s['name'], 'created_at' => now()]);
    unset($_SESSION['old_req']);
    $jobRow = job_load($jid);
    if ($submit) {
        notify_role('back_office', "New QC request {$jobRow['req_no']} · {$jobRow['client_code']} – {$jobRow['vendor_name']} · PO {$jobRow['po_no']}", 'request?id=' . $jid);
        notify_role('super_admin', "New QC request {$jobRow['req_no']} from {$jobRow['source']}", 'request?id=' . $jid);
        if ($r !== 'client') notify_role('client', "{$jobRow['vendor_name']} raised QC request {$jobRow['req_no']} · PO {$jobRow['po_no']}", 'request?id=' . $jid, ['client_id' => $cid]);
        if ($r !== 'vendor') notify_role('vendor', "QC request {$jobRow['req_no']} raised for PO {$jobRow['po_no']}", 'request?id=' . $jid, ['vendor_id' => $vid]);
        flash("QC request {$jobRow['req_no']} submitted — status REQUESTED.");
    } else flash("Draft {$jobRow['req_no']} saved.");
    redirect('request', ['id' => $jid]);
}

$old = $_SESSION['old_req'] ?? null; unset($_SESSION['old_req']);
$J = $job ?? ['client_id' => $r === 'client' ? $u['client_id'] : (count($clients) === 1 ? $clients[0]['id'] : ''), 'vendor_id' => $r === 'vendor' ? $u['vendor_id'] : '',
    'po_no' => '', 'qc_type' => 'Pre-shipment', 'location' => '', 'requested_date' => date('Y-m-d', strtotime('+2 days')), 'scope' => 'BULK', 'bulk_mode' => '100',
    'insp_level' => 'II', 'aql_critical' => 0, 'aql_major' => 1.5, 'aql_minor' => 4.0, 'defined_qty' => 0, 'allow_excess' => 0, 'specs' => '', 'special_req' => '', 'req_no' => next_req_no(), 'status' => 'DRAFT'];
if ($old) foreach ($J as $k => $v) if (isset($old[$k])) $J[$k] = $old[$k];
if (!$items) $items = [['id' => 0, 'sku' => '', 'product' => '', 'metal' => '925 Silver', 'plating' => '', 'target_weight' => '', 'length' => '', 'lock_type' => '', 'stamping' => '', 'order_qty' => '', 'ready_qty' => '']];
if ($old && !empty($old['sku'])) { $items = []; foreach ($old['sku'] as $k => $s) { $it = ['id' => $old['item_id'][$k] ?? 0, 'sku' => $s]; foreach (['product', 'metal', 'plating', 'target_weight', 'length', 'lock_type', 'stamping', 'order_qty', 'ready_qty'] as $f) $it[$f] = $old[$f][$k] ?? ''; $items[] = $it; } }
$cats = array_column(all('SELECT name FROM product_categories ORDER BY name'), 'name');
$types = array_column(all('SELECT name FROM qc_types ORDER BY id'), 'name');
$atts = $job ? all('SELECT * FROM attachments WHERE job_id=?', [$job['id']]) : [];
$aqls = ['0' => 'Not allowed (0)', '0.065' => '0.065', '0.10' => '0.10', '0.15' => '0.15', '0.25' => '0.25', '0.40' => '0.40', '0.65' => '0.65', '1.0' => '1.0', '1.5' => '1.5', '2.5' => '2.5', '4.0' => '4.0', '6.5' => '6.5'];
$aSel = function ($name, $v) use ($aqls) { $cur = '0'; foreach ($aqls as $k => $_) if (abs((float)$k - (float)$v) < 1e-6) $cur = (string)$k; return sel($name, $aqls, $cur, 'data-aql'); };
layout_start($job ? 'Edit QC Request' : 'New QC Request', 'request-new', '', 'Same form for Back Office, Vendor and Client — multi-SKU, attachments and dynamic QC scope with AQL calculator');
?>
<form method="post" enctype="multipart/form-data" id="reqform"><?= csrf_field() ?>
<div class="grid g-73">
<div>
<div class="card">
  <div class="card-h"><h2>Create QC request</h2><span><?= h($J['req_no']) ?> · <?= badge($J['status']) ?></span></div>
  <div class="fr f">
    <div><label>Client</label><?php if ($r === 'client'): ?><input type="text" readonly value="<?= h($clients[0]['code'] . ' — ' . $clients[0]['name']) ?>"><input type="hidden" name="client_id" value="<?= $clients[0]['id'] ?>">
      <?php else: ?><?= sel('client_id', array_column(array_map(fn($c) => ['id' => $c['id'], 'l' => $c['code'] . ' — ' . $c['name']], $clients), 'l', 'id'), $J['client_id'], 'required id="client_id"', 'Select client') ?><?php endif ?></div>
    <div><label>Vendor</label><?php if ($r === 'vendor'): ?><input type="text" readonly value="<?= h($vendors[0]['name']) ?>"><input type="hidden" name="vendor_id" id="vendor_id" value="<?= $vendors[0]['id'] ?>">
      <?php else: ?><select name="vendor_id" id="vendor_id" required><option value="">Select vendor</option><?php foreach ($vendors as $v): ?><option value="<?= $v['id'] ?>" data-loc="<?= h($v['location']) ?>" <?= $J['vendor_id'] == $v['id'] ? 'selected' : '' ?>><?= h($v['name']) ?></option><?php endforeach ?></select><?php endif ?></div>
    <div><label>PO number</label><input type="text" name="po_no" required value="<?= h($J['po_no']) ?>" placeholder="81966"></div>
  </div>
  <div class="fr f">
    <div><label>Requested QC date</label><input type="date" name="requested_date" value="<?= h($J['requested_date']) ?>"></div>
    <div><label>QC location</label><input type="text" name="location" id="location" value="<?= h($J['location']) ?>" placeholder="Factory unit"></div>
    <div><label>QC type</label><?= sel('qc_type', array_combine($types, $types), $J['qc_type']) ?></div>
  </div>
</div>
<div class="card">
  <div class="card-h"><h2>Product / SKU line items</h2><button type="button" class="btn sm" onclick="addSku()"><?= icon('plus') ?> Add SKU</button></div>
  <div class="tbl-wrap"><table class="t" id="skus"><tr><th>#</th><th>SKU / Design no.</th><th>Product</th><th>Metal</th><th>Plating</th><th>Wt.</th><th>Length</th><th>Lock</th><th>Stamping</th><th>Order qty</th><th>Ready qty</th><th>Spec / file</th><th></th></tr>
  <?php foreach ($items as $k => $it): ?>
  <tr><td class="rn"><?= $k + 1 ?></td><td><input type="hidden" name="item_id[]" value="<?= (int)$it['id'] ?>"><input type="text" name="sku[]" value="<?= h($it['sku']) ?>" style="min-width:140px" required></td>
    <td><select name="product[]" style="min-width:100px"><?php foreach ($cats as $c): ?><option <?= $it['product'] === $c ? 'selected' : '' ?>><?= h($c) ?></option><?php endforeach ?></select></td>
    <td><input type="text" name="metal[]" value="<?= h($it['metal']) ?>" style="min-width:90px"></td><td><input type="text" name="plating[]" value="<?= h($it['plating']) ?>" style="min-width:90px"></td>
    <td><input type="text" name="target_weight[]" value="<?= h($it['target_weight']) ?>" style="width:70px"></td><td><input type="text" name="length[]" value="<?= h($it['length']) ?>" style="width:80px"></td>
    <td><input type="text" name="lock_type[]" value="<?= h($it['lock_type']) ?>" style="width:90px"></td><td><input type="text" name="stamping[]" value="<?= h($it['stamping']) ?>" style="width:110px"></td>
    <td><input type="number" name="order_qty[]" value="<?= h($it['order_qty']) ?>" min="0" style="width:85px" class="oq"></td><td><input type="number" name="ready_qty[]" value="<?= h($it['ready_qty']) ?>" min="1" style="width:85px" class="rq" required></td>
    <td><input type="file" name="row_file[<?= $k ?>]" style="width:150px;font-size:11px"></td><td><button type="button" class="btn sm ghost" onclick="delSku(this)">✕</button></td></tr>
  <?php endforeach ?></table></div>
  <div class="actions mt"><label class="chk"><input type="checkbox" name="allow_excess" value="1" id="allow_excess" <?= $J['allow_excess'] ? 'checked' : '' ?>> Allow excess quantity (Ready &gt; Order)</label>
    <span class="right muted" id="qtywarn"></span></div>
</div>
<div class="card">
  <h2 class="mb">Specifications &amp; attachments</h2>
  <div class="f"><label>Specifications / remarks</label><textarea name="specs" placeholder="925 Silver, White Rhodium, 16+2″ Lobster lock, stamping 925 + ARCL + LGDS"><?= h($J['specs']) ?></textarea></div>
  <div class="f"><label>Special requirements for inspector</label><input type="text" name="special_req" value="<?= h($J['special_req']) ?>"></div>
  <div class="f"><label>Upload spec sheet / CAD / reference photos (PDF, JPG, PNG, XLSX)</label><input type="file" name="files[]" multiple accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.webp"></div>
  <?php if ($atts): ?><div class="actions"><?php foreach ($atts as $a): ?><a class="pill" target="_blank" href="<?= url('file', ['p' => $a['path']]) ?>"><?= h($a['name']) ?></a><?php endforeach ?></div><?php endif ?>
</div>
</div>
<div>
<div class="card" style="position:sticky;top:84px">
  <h2 class="mb">QC scope</h2>
  <label>Primary scope</label>
  <div class="radio-cards f">
    <label><input type="radio" name="scope" value="SAMPLE" <?= $J['scope'] === 'SAMPLE' ? 'checked' : '' ?>><b>Sample QC</b><small>Pre-production / pilot sample</small></label>
    <label><input type="radio" name="scope" value="BULK" <?= $J['scope'] === 'BULK' ? 'checked' : '' ?>><b>Bulk QC</b><small>Production lot inspection</small></label>
  </div>
  <div id="bulkbox">
    <label>Bulk QC mode</label>
    <div class="stack f">
      <label class="rc"><input type="radio" name="bulk_mode" value="100" <?= $J['bulk_mode'] === '100' ? 'checked' : '' ?>> <b>100% inspection</b><small>Inspection qty = Ready qty (locked)</small></label>
      <label class="rc"><input type="radio" name="bulk_mode" value="AQL" <?= $J['bulk_mode'] === 'AQL' ? 'checked' : '' ?>> <b>AQL inspection (ISO 2859-1)</b><small>Auto sample size &amp; Ac / Re</small></label>
      <label class="rc"><input type="radio" name="bulk_mode" value="DEFINED" <?= $J['bulk_mode'] === 'DEFINED' ? 'checked' : '' ?>> <b>Defined sample quantity</b><small>Enter fixed pcs (≤ Ready qty)</small></label>
    </div>
  </div>
  <div id="definedbox" class="f"><label id="deflabel">Defined sample qty (pcs per SKU)</label><input type="number" name="defined_qty" id="defined_qty" min="0" value="<?= (int)$J['defined_qty'] ?>"></div>
  <div id="aqlbox">
    <div class="fr f"><div><label>Inspection level</label><?= sel('insp_level', ['I' => 'General I', 'II' => 'General II', 'III' => 'General III', 'S1' => 'Special S-1', 'S2' => 'Special S-2', 'S3' => 'Special S-3', 'S4' => 'Special S-4'], $J['insp_level'], 'id="insp_level"') ?></div>
      <div><label>Lot size (Ready)</label><input type="text" readonly id="lotsize" value="0"></div></div>
    <table class="t" id="aqltbl"><tr><th>AQL</th><th>Limit</th><th class="num">Sample</th><th class="num">Ac</th><th class="num">Re</th></tr>
      <tr><td>Critical</td><td><?= $aSel('aql_critical', $J['aql_critical']) ?></td><td class="num" data-s="critical">—</td><td class="num" data-a="critical">—</td><td class="num" data-r="critical">—</td></tr>
      <tr><td>Major</td><td><?= $aSel('aql_major', $J['aql_major']) ?></td><td class="num" data-s="major">—</td><td class="num" data-a="major">—</td><td class="num" data-r="major">—</td></tr>
      <tr><td>Minor</td><td><?= $aSel('aql_minor', $J['aql_minor']) ?></td><td class="num" data-s="minor">—</td><td class="num" data-a="minor">—</td><td class="num" data-r="minor">—</td></tr>
    </table>
    <p class="muted" id="aqlnote" style="font-size:12px"></p>
  </div>
  <div class="actions mt"><button class="btn" name="action" value="draft">Save draft</button><button class="btn pri" name="action" value="submit" style="flex:1">SUBMIT QC REQUEST</button></div>
</div>
</div>
</div>
</form>
<script>window.MAPPING=<?= json_encode($mapping) ?>;window.ROLE=<?= json_encode($r) ?>;</script>
<?php layout_end();
