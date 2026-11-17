<?php
require_once ROOT . '/app/xlsx.php';
$imp = $_SESSION['import'] ?? null;
$isV = role() === 'vendor';
if ($isV && !flag('enable_vendor_qc_request')) { flash('Bulk Excel requests are not enabled yet.', 'warn'); redirect('vendor'); }

function parse_scope(string $s): ?array {
    $s = strtoupper(trim($s));
    if ($s === '' ) return null;
    if (str_contains($s, 'SAMPLE')) return ['scope' => 'SAMPLE', 'bulk_mode' => '100', 'aql_major' => 2.5, 'defined_qty' => 0, 'label' => 'Sample'];
    if (preg_match('/AQL[^0-9]*([0-9.]+)/', $s, $m)) return ['scope' => 'BULK', 'bulk_mode' => 'AQL', 'aql_major' => (float)$m[1], 'defined_qty' => 0, 'label' => 'AQL-' . $m[1]];
    if (str_contains($s, 'AQL')) return ['scope' => 'BULK', 'bulk_mode' => 'AQL', 'aql_major' => 2.5, 'defined_qty' => 0, 'label' => 'AQL-2.5'];
    if (preg_match('/DEFINED[^0-9]*([0-9]+)/', $s, $m)) return ['scope' => 'BULK', 'bulk_mode' => 'DEFINED', 'aql_major' => 2.5, 'defined_qty' => (int)$m[1], 'label' => 'Defined ' . $m[1]];
    if (str_contains($s, '100') || str_contains($s, 'BULK')) return ['scope' => 'BULK', 'bulk_mode' => '100', 'aql_major' => 2.5, 'defined_qty' => 0, 'label' => 'Bulk 100%'];
    return null;
}

if (is_post() && post('step') === 'upload') {
    $cid = (int)post('client_id');
    if ($isV && !one('SELECT 1 FROM client_vendor WHERE client_id=? AND vendor_id=?', [$cid, user()['vendor_id']])) { flash('Client not mapped to you.', 'err'); redirect('import'); }
    $client = one('SELECT * FROM clients WHERE id=?', [$cid]);
    $f = $_FILES['file'] ?? null;
    if (!$client || !$f || $f['error'] !== UPLOAD_ERR_OK) { flash('Select a client and an Excel file.', 'err'); redirect('import'); }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    try { $raw = $ext === 'csv' ? csv_read($f['tmp_name']) : xlsx_read($f['tmp_name']); }
    catch (Throwable $e) { flash('Could not read file: ' . $e->getMessage() . ' (use .xlsx or .csv)', 'err'); redirect('import'); }
    if (count($raw) < 2) { flash('The file has no data rows.', 'err'); redirect('import'); }
    // map header names → column index (tolerant)
    $hdr = array_map(fn($x) => strtolower(preg_replace('/[^a-z]/i', '', (string)$x)), $raw[0]);
    $find = function (array $names) use ($hdr) { foreach ($names as $n) { $k = array_search($n, $hdr, true); if ($k !== false) return $k; } return null; };
    $map = [];
    foreach (['vendor' => ['vendor', 'vendorname', 'factory'], 'po' => ['pono', 'po', 'ponumber'], 'sku' => ['sku', 'designno', 'skudesignno', 'design'],
        'product' => ['product', 'category'], 'order' => ['orderqty', 'orderquantity', 'order'], 'ready' => ['readyqty', 'readyquantity', 'ready'],
        'scope' => ['qcscope', 'scope'], 'metal' => ['metal'], 'plating' => ['plating'], 'weight' => ['weight', 'targetweight'], 'length' => ['length'],
        'lock' => ['lock', 'locktype'], 'stamping' => ['stamping'], 'date' => ['requesteddate', 'qcdate', 'date'], 'spec' => ['specfile', 'spec', 'attachment']] as $k => $names) $map[$k] = $find($names);
    foreach (($isV ? ['po', 'sku', 'ready'] : ['vendor', 'po', 'sku', 'ready']) as $req) if ($map[$req] === null) { flash("Column “$req” not found. Download the template to see the expected columns.", 'err'); redirect('import'); }
    $vendors = []; foreach (all('SELECT * FROM vendors') as $v) { $vendors[strtolower($v['name'])] = $v; $vendors[strtolower($v['code'])] = $v; }
    $mapped = array_column(all('SELECT vendor_id FROM client_vendor WHERE client_id=?', [$cid]), 'vendor_id');
    $existing = []; foreach (all("SELECT j.po_no, i.sku FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id WHERE j.client_id=? AND j.status NOT IN ('CANCELLED')", [$cid]) as $e) $existing[strtoupper($e['po_no'] . '|' . $e['sku'])] = true;
    $seen = []; $rows = [];
    foreach (array_slice($raw, 1) as $n => $r) {
        $g = fn($k) => $map[$k] === null ? '' : trim((string)($r[$map[$k]] ?? ''));
        if (!array_filter($r, fn($x) => trim((string)$x) !== '')) continue;
        $e = []; $w = [];
        $v = $isV ? one('SELECT * FROM vendors WHERE id=?', [user()['vendor_id']]) : ($vendors[strtolower($g('vendor'))] ?? null);
        if (!$v && !$isV) { foreach ($vendors as $key => $vv) if ($g('vendor') !== '' && str_starts_with($key, strtolower($g('vendor')))) { $v = $vv; break; } }
        if (!$v) $e[] = 'Unknown vendor'; elseif (!in_array($v['id'], $mapped)) $e[] = 'Vendor not mapped to client';
        $po = $g('po'); $sku = strtoupper($g('sku'));
        if ($po === '') $e[] = 'PO missing'; if ($sku === '') $e[] = 'SKU missing';
        $oq = (int)preg_replace('/[^0-9]/', '', $g('order')); $rq = (int)preg_replace('/[^0-9]/', '', $g('ready'));
        if ($rq <= 0) $e[] = 'Ready qty missing'; if ($oq && $rq > $oq) $e[] = 'Ready > Order';
        $sc = parse_scope($g('scope')) ?? ['scope' => 'BULK', 'bulk_mode' => '100', 'aql_major' => 2.5, 'defined_qty' => 0, 'label' => 'Bulk 100%'];
        if ($g('scope') === '') $w[] = 'Scope blank → Bulk 100%';
        if ($sc['bulk_mode'] === 'DEFINED' && $sc['defined_qty'] > $rq) $e[] = 'Defined qty > Ready';
        $key = strtoupper($po . '|' . $sku);
        if (isset($seen[$key]) || isset($existing[$key])) $e[] = 'Duplicate SKU'; $seen[$key] = true;
        if ($g('spec') === '') $w[] = 'Spec file missing';
        $rows[] = ['n' => $n + 2, 'vendor_id' => $v['id'] ?? null, 'vendor' => $v['name'] ?? $g('vendor'), 'po' => $po, 'sku' => $sku, 'product' => $g('product') ?: 'Necklace',
            'order' => $oq ?: $rq, 'ready' => $rq, 'scope' => $sc, 'metal' => $g('metal'), 'plating' => $g('plating'), 'weight' => $g('weight'), 'length' => $g('length'),
            'lock' => $g('lock'), 'stamping' => $g('stamping'), 'date' => excel_date($g('date')), 'spec' => $g('spec'), 'errors' => $e, 'warnings' => $w,
            'raw' => array_map(fn($c) => $map[$c] === null ? '' : ($r[$map[$c]] ?? ''), ['vendor', 'po', 'sku', 'product', 'order', 'ready', 'scope', 'metal', 'plating', 'weight', 'length', 'lock', 'stamping', 'date', 'spec'])];
    }
    $saved = save_upload($f, 'imports', ['xlsx', 'csv', 'xls']);
    $_SESSION['import'] = ['client_id' => $cid, 'client' => $client['code'], 'file' => $f['name'], 'size' => $f['size'], 'rows' => $rows, 'path' => $saved['path'] ?? null];
    redirect('import');
}
if (is_post() && post('step') === 'create' && $imp) {
    $status = post('mode') === 'draft' ? 'DRAFT' : 'REQUESTED';
    $groups = [];
    foreach ($imp['rows'] as $r) { if ($r['errors']) continue; $groups[$r['vendor_id'] . '|' . $r['po'] . '|' . $r['scope']['label']][] = $r; }
    $made = [];
    foreach ($groups as $g) {
        $f = $g[0]; $sc = $f['scope'];
        $job = ['req_no' => next_req_no(), 'source' => $isV ? 'Vendor portal' : 'Excel import', 'client_id' => $imp['client_id'], 'vendor_id' => $f['vendor_id'], 'po_no' => $f['po'],
            'qc_type' => $sc['scope'] === 'SAMPLE' ? 'Pre-production' : 'Pre-shipment', 'location' => explode(',', (string)val('SELECT location FROM vendors WHERE id=?', [$f['vendor_id']]))[0],
            'requested_date' => $f['date'] ?: date('Y-m-d', strtotime('+2 days')), 'scope' => $sc['scope'], 'bulk_mode' => $sc['bulk_mode'], 'insp_level' => 'II',
            'aql_critical' => 0, 'aql_major' => $sc['aql_major'], 'aql_minor' => 4.0, 'defined_qty' => $sc['defined_qty'], 'allow_excess' => 0,
            'specs' => 'Imported from ' . $imp['file'], 'status' => $status, 'created_by' => user()['id'], 'created_at' => now()];
        $jid = insert('qc_jobs', $job); $job['id'] = $jid;
        foreach ($g as $r) {
            $plan = item_plan($job, $r['ready']);
            insert('qc_items', ['job_id' => $jid, 'sku' => $r['sku'], 'product' => $r['product'], 'metal' => $r['metal'], 'plating' => $r['plating'], 'target_weight' => $r['weight'],
                'length' => $r['length'], 'lock_type' => $r['lock'], 'stamping' => $r['stamping'], 'order_qty' => $r['order'], 'ready_qty' => $r['ready'],
                'insp_qty' => $plan['insp_qty'], 'plan_json' => json_encode($plan['plan']), 'status' => 'PENDING', 'updated_at' => now()]);
        }
        if ($imp['path']) insert('attachments', ['job_id' => $jid, 'item_id' => null, 'path' => $imp['path'], 'name' => $imp['file'], 'created_at' => now()]);
        audit('job', $jid, 'CREATE', 'source', null, 'Excel import');
        if ($status === 'REQUESTED') { notify_job_parties(job_load($jid), "QC request {$job['req_no']} created from Excel · PO {$job['po_no']}", 'request?id=' . $jid);
            if ($isV) notify_role('back_office', "Vendor bulk request {$job['req_no']} · PO {$job['po_no']}", 'request?id=' . $jid); }
        $made[] = $job['req_no'];
    }
    unset($_SESSION['import']);
    flash(count($made) . ' QC request(s) created (' . implode(', ', $made) . ') — status ' . $status . '.');
    redirect('requests', $isV ? [] : ['src' => 'Excel import']);
}
if (get('reset')) { unset($_SESSION['import']); redirect('import'); }

$clients = $isV ? all("SELECT c.id, c.code, c.name FROM clients c JOIN client_vendor m ON m.client_id=c.id WHERE m.vendor_id=? AND c.status='ACTIVE' ORDER BY c.code", [user()['vendor_id']]) : all("SELECT id, code, name FROM clients WHERE status='ACTIVE' ORDER BY code");
layout_start($isV ? 'Bulk Excel QC Request' : 'Import Excel', 'import', btn(icon('download') . ' Download Excel template', url('import-template')), 'Upload the client’s requirement sheet, see every row checked, download errors, then create QC jobs');
$step = $imp ? 2 : 1;
?>
<div class="card mb"><div class="flow"><span class="<?= $step > 1 ? 'done' : 'cur' ?>">1. Select client &amp; upload</span><span class="<?= $step === 2 ? 'cur' : '' ?>">2. Preview &amp; validate</span><span>3. Create QC jobs</span></div></div>
<div class="grid g-82" style="grid-template-columns:minmax(280px,.8fr) minmax(0,2.2fr)">
<div class="card"><h2 class="mb">Import QC requirement</h2>
<form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="step" value="upload">
  <div class="f"><label>Select client</label><?= sel('client_id', array_column(array_map(fn($c) => ['id' => $c['id'], 'l' => $c['code'] . ' — ' . $c['name']], $clients), 'l', 'id'), $imp['client_id'] ?? '', 'required', 'Select client') ?></div>
  <div class="f"><label>Excel file (.xlsx or .csv)</label><input type="file" name="file" accept=".xlsx,.csv" required></div>
  <button class="btn pri block">Upload &amp; validate</button>
</form>
<?php if ($imp): ?><div class="note rose mt">📄 <b><?= h($imp['file']) ?></b><br><?= count($imp['rows']) ?> rows · <?= round($imp['size'] / 1024) ?> KB · uploaded</div><?php endif ?>
<div class="hr"></div>
<p style="font-size:12.5px" class="muted"><b>Columns:</b> <?= h(implode(', ', IMPORT_COLS)) ?>.<br><b>Checks:</b> client–vendor mapping, Ready ≤ Order, duplicate PO + SKU (in file and already in system), scope format (Bulk 100% · AQL-1.5 · Sample · Defined 50).</p>
<?= btn(icon('download') . ' Download Excel template', url('import-template'), 'block') ?>
</div>
<div class="card">
<?php if (!$imp): ?><?= empty_state('Upload a file to preview and validate the rows here.') ?>
<?php else: $ok = count(array_filter($imp['rows'], fn($r) => !$r['errors'])); $err = count($imp['rows']) - $ok; $warn = count(array_filter($imp['rows'], fn($r) => !$r['errors'] && $r['warnings']));
  $issues = []; foreach ($imp['rows'] as $r) foreach (array_merge($r['errors'], $r['warnings']) as $x) $issues[$x] = ($issues[$x] ?? 0) + 1; ?>
  <div class="card-h"><h2>Preview — client: <?= h($imp['client']) ?> · file: <?= h($imp['file']) ?></h2><span><?= count($imp['rows']) ?> rows</span></div>
  <div class="tbl-wrap" style="max-height:440px"><table class="t"><tr><th>No</th><th>Vendor</th><th>PO no</th><th>SKU</th><th>Scope</th><th class="num">Order</th><th class="num">Ready</th><th>Check</th></tr>
  <?php foreach ($imp['rows'] as $r): ?><tr><td><?= sprintf('%02d', $r['n'] - 1) ?></td><td><?= h($r['vendor']) ?></td><td class="mono"><?= h($r['po']) ?></td><td class="mono"><?= h($r['sku']) ?></td><td><?= h($r['scope']['label']) ?></td>
    <td class="num"><?= num($r['order']) ?></td><td class="num"><?= num($r['ready']) ?></td>
    <td><?php if ($r['errors']): ?><span class="badge b-fail"><?= h(implode(', ', $r['errors'])) ?></span><?php elseif ($r['warnings']): ?><span class="badge b-pending"><?= h(implode(', ', $r['warnings'])) ?></span><?php else: ?><span class="badge b-pass">Valid</span><?php endif ?></td></tr><?php endforeach ?>
  </table></div>
  <div class="grid g3 mt" style="gap:10px">
    <div class="kpi ok"><div class="kic"><?= icon('check') ?></div><div><small>Records valid</small><b><?= $ok ?></b><span><?= $warn ?> with warnings</span></div></div>
    <?php $i = 0; foreach ($issues as $k => $n): if ($i++ > 1) break; ?><div class="kpi <?= str_contains($k, 'missing') && !str_contains($k, 'qty') ? 'warn' : 'bad' ?>"><div class="kic"><?= icon('alert') ?></div><div><small><?= h($k) ?></small><b><?= $n ?></b></div></div><?php endforeach ?>
  </div>
  <div class="actions mt">
    <?= btn('Start over', url('import', ['reset' => 1]), 'ghost') ?>
    <?php if ($err || $warn): ?><?= btn(icon('download') . ' Download error file', url('import-template', ['errors' => 1])) ?><?php endif ?>
    <form method="post" class="right actions"><?= csrf_field() ?><input type="hidden" name="step" value="create">
      <button class="btn" name="mode" value="draft" <?= $ok ? '' : 'disabled' ?>>Save as draft</button>
      <button class="btn pri" name="mode" value="create" <?= $ok ? '' : 'disabled' ?>>Create QC jobs (<?= $ok ?> rows)</button></form>
  </div>
  <?php if ($err): ?><p class="muted" style="font-size:12px">Rows with errors are skipped. Valid rows are grouped into one request per vendor + PO + scope.</p><?php endif ?>
<?php endif ?>
</div></div>
<?php layout_end();
