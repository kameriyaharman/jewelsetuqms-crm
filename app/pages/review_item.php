<?php
require_once ROOT . '/app/workflow.php';
$it = one('SELECT * FROM qc_items WHERE id=?', [(int)get('id', post('id'))]);
if (!$it) { flash('Not found', 'err'); redirect('review'); }
$job = job_load((int)$it['job_id']);

if (is_post()) {
    $a = (string)post('act');
    if ($a === 'return' && in_array($it['status'], ['SUBMITTED', 'UNDER_REVIEW'], true)) {
        $secs = array_intersect((array)post('sections', []), STEPS);
        $reason = trim((string)post('reason'));
        if (!$secs || !$reason) { flash('Select the section(s) to correct and write a reason.', 'err'); redirect('review-item', ['id' => $it['id']]); }
        update('qc_items', ['status' => 'RETURNED', 'return_sections' => implode(', ', $secs), 'return_reason' => $reason, 'return_count' => (int)$it['return_count'] + 1], 'id=?', [$it['id']]);
        audit('item', (int)$it['id'], 'RETURNED', implode(', ', $secs), null, $reason);
        notify((int)$job['inspector_id'], "Inspection returned for correction · {$job['job_no']} · {$it['sku']} — $reason", 'app/inspect?item=' . $it['id']);
        refresh_job_status((int)$job['id']);
        flash('Returned to inspector with remarks.'); redirect('review');
    }
    if ($a === 'approve' && in_array($it['status'], ['SUBMITTED', 'UNDER_REVIEW'], true)) {
        $final = in_array(post('final'), ['PASS', 'FAIL', 'CONDITIONAL'], true) ? post('final') : null;
        if (!$final) { flash('Choose the final decision (PASS / FAIL / CONDITIONAL PASS). PENDING cannot be approved.', 'err'); redirect('review-item', ['id' => $it['id']]); }
        $note = trim((string)post('note'));
        if ($final !== $it['system_result'] && $note === '') { flash('Override of the system result needs a manager note.', 'err'); redirect('review-item', ['id' => $it['id']]); }
        $dev = [];
        foreach ((array)post('dev_param', []) as $k => $p) $dev[] = ['param' => $p, 'spec' => $_POST['dev_spec'][$k] ?? '', 'actual' => $_POST['dev_actual'][$k] ?? '', 'dev' => $_POST['dev_dev'][$k] ?? '', 'pcs' => $_POST['dev_pcs'][$k] ?? ''];
        if ($final === 'CONDITIONAL' && !array_filter($dev, fn($d) => trim($d['param']) !== '')) { flash('Conditional pass needs at least one deviation line for the client.', 'err'); redirect('review-item', ['id' => $it['id']]); }
        $r = approve_item((int)$it['id'], $final, $note, $dev);
        flash("Approved — locked PDF {$r['report_no']} generated." . ($final === 'CONDITIONAL' ? ' Sent to client for concession.' : ''));
        redirect('report', ['id' => $it['id']]);
    }
    if ($a === 'revise' && is_role('super_admin') && $it['locked']) {
        $reason = trim((string)post('reason'));
        if ($reason === '') { flash('Unlock needs a reason.', 'err'); redirect('review-item', ['id' => $it['id']]); }
        $rev = (int)$it['rev'] + 1;
        $fields = ['inspected_qty', 'accepted_qty', 'rejected_qty', 'final_result', 'remark', 'action_required', 'manager_note'];
        $chg = [];
        foreach ($fields as $f) { $nv = post($f); if ($nv !== null && (string)$nv !== (string)$it[$f]) $chg[$f] = $nv; }
        $meas = jdec($it['meas_json']); $mch = false;
        foreach ((array)post('m_actual', []) as $k => $v) if (isset($meas[$k]) && (string)$v !== (string)$meas[$k]['actual']) {
            audit('item', (int)$it['id'], 'REVISION', 'Measurement · ' . $meas[$k]['param'], $meas[$k]['actual'], $v, $rev);
            $meas[$k]['actual'] = $v; $sp = (float)$meas[$k]['spec']; $meas[$k]['result'] = abs((float)$v - $sp) <= (float)$meas[$k]['tol'] + 1e-9 ? 'PASS' : 'FAIL'; $mch = true; }
        if (isset($chg['accepted_qty']) || isset($chg['rejected_qty']) || isset($chg['inspected_qty'])) {
            $i2 = (int)($chg['inspected_qty'] ?? $it['inspected_qty']); $a2 = (int)($chg['accepted_qty'] ?? $it['accepted_qty']); $r2 = (int)($chg['rejected_qty'] ?? $it['rejected_qty']);
            if ($a2 + $r2 !== $i2) { flash('Accepted + Rejected must equal Inspected.', 'err'); redirect('review-item', ['id' => $it['id'], 'revise' => 1]); }
        }
        if (!$chg && !$mch) { flash('No changes made.', 'warn'); redirect('review-item', ['id' => $it['id'], 'revise' => 1]); }
        foreach ($chg as $f => $v) audit('item', (int)$it['id'], 'REVISION', ucwords(str_replace('_', ' ', $f)), $it[$f], $v, $rev);
        audit('item', (int)$it['id'], 'UNLOCK', 'Reason', null, $reason, $rev);
        update('qc_items', $chg + ['rev' => $rev, 'meas_json' => json_encode($meas), 'updated_at' => now()], 'id=?', [$it['id']]);
        [, , $file] = item_pdf((int)$it['id'], true); // old PDF kept on disk, new versioned PDF
        email_report($job, one('SELECT * FROM qc_items WHERE id=?', [$it['id']]), $file, 'REVISED QC Report ' . sprintf('REV %02d', $rev) . " | {$it['report_no']}",
            "Dear Sir/Madam,\n\nThe QC report {$it['report_no']} has been revised (Revision " . sprintf('%02d', $rev) . ").\nReason: $reason\n\nPlease refer to the attached revised report.");
        if ($job['po_pdf']) job_pdf((int)$job['id'], true);
        flash('Revision ' . sprintf('%02d', $rev) . ' saved, new PDF generated and re-sent. Old PDF kept.');
        redirect('report', ['id' => $it['id']]);
    }
    redirect('review-item', ['id' => $it['id']]);
}

if ($it['status'] === 'SUBMITTED') { q("UPDATE qc_items SET status='UNDER_REVIEW' WHERE id=?", [$it['id']]); $it['status'] = 'UNDER_REVIEW'; audit('item', (int)$it['id'], 'UNDER_REVIEW'); refresh_job_status((int)$job['id']); }
$def = all('SELECT * FROM item_defects WHERE item_id=?', [$it['id']]);
$photos = all('SELECT * FROM item_photos WHERE item_id=? ORDER BY id', [$it['id']]);
$checks = jdec($it['checks_json']); $meas = jdec($it['meas_json']); $tests = jdec($it['tests_json']);
$sev = ['CRITICAL' => 0, 'MAJOR' => 0, 'MINOR' => 0]; foreach ($def as $d) $sev[$d['severity']] += (int)$d['qty'];
$dq = array_sum($sev);
$comp = ['General info' => (bool)$it['insp_date'], 'Quantity' => $it['inspected_qty'] > 0 && $it['accepted_qty'] + $it['rejected_qty'] == $it['inspected_qty'],
    'Quality checks' => $checks && !array_filter($checks, fn($c) => ($c['result'] ?? '') === ''), 'Measurements' => (bool)array_filter($meas, fn($m) => ($m['actual'] ?? '') !== ''),
    'On-site tests' => (bool)$tests, 'Defects' => $it['rejected_qty'] == 0 || $dq >= $it['rejected_qty'], 'Photos' => !$dq || (bool)array_filter($photos, fn($p) => $p['kind'] === 'defect'),
    'Final result' => (bool)$it['inspector_result']];
$canReview = in_array($it['status'], ['UNDER_REVIEW', 'SUBMITTED'], true);
$revising = get('revise') && is_role('super_admin') && $it['locked'];
$insp = max(1, (int)$it['inspected_qty']);
layout_start('Inspection review — ' . ($it['report_no'] ?: $it['sku']), 'review', $it['locked'] ? btn('Open report', url('report', ['id' => $it['id']]), 'gold') : '', h($job['job_no'] . ' · ' . $job['client_code'] . ' – ' . $job['vendor_name']));
?>
<div class="grid g-73">
<div>
<div class="card"><div class="card-h"><h2>Inspection review — <?= h($it['report_no'] ?: $job['job_no'] . ' / ' . $it['sku']) ?></h2><?= badge($it['status']) ?></div>
  <div class="kv"><div><small>Inspector</small><b><?= h($job['inspector_code'] . ' · ' . $job['inspector_name']) ?></b></div><div><small>Vendor</small><b><?= h($job['vendor_name']) ?></b></div><div><small>SKU</small><b class="mono"><?= h($it['sku']) ?></b></div>
    <div><small>PO</small><b><?= h($job['po_no']) ?></b></div><div><small>Submitted</small><b><?= fdt($it['submitted_at']) ?></b></div><div><small>System result</small><b><?= badge($it['system_result'] ?: 'PENDING') ?></b></div>
    <div><small>Inspector result</small><b><?= badge($it['inspector_result'] ?: 'PENDING') ?></b></div><div><small>GPS / start</small><b><?= h($it['gps'] ?: '—') ?></b></div></div>
</div>
<div class="grid g2" style="margin-top:16px">
<div class="card" style="margin:0"><h2 class="mb">Quantity</h2>
  <div class="grid g2" style="gap:10px"><div><small>Inspected</small><div class="big"><?= num($it['inspected_qty']) ?></div></div><div><small>Accepted</small><div class="big ok-t"><?= num($it['accepted_qty']) ?> <small>· <?= pct($it['accepted_qty'], $insp, 2) ?></small></div></div>
  <div><small>Rejected</small><div class="big bad-t"><?= num($it['rejected_qty']) ?> <small>· <?= pct($it['rejected_qty'], $insp, 2) ?></small></div></div>
  <div><small>Check</small><div class="<?= $comp['Quantity'] ? 'ok-t' : 'bad-t' ?>" style="font-weight:700;margin-top:6px"><?= $comp['Quantity'] ? '✓' : '✗' ?> <?= num($it['accepted_qty']) ?> + <?= num($it['rejected_qty']) ?> = <?= num($it['accepted_qty'] + $it['rejected_qty']) ?></div></div></div></div>
<div class="card" style="margin:0"><h2 class="mb">Defects</h2>
  <?php if (!$def): echo empty_state('No defects'); else: ?><table class="t"><tr><th>Category</th><th>Type</th><th>Severity</th><th class="num">Qty</th></tr>
  <?php foreach ($def as $d): ?><tr><td><?= h($d['category']) ?></td><td><?= h($d['type']) ?></td><td><?= badge($d['severity']) ?></td><td class="num"><?= num($d['qty']) ?></td></tr><?php endforeach ?></table><?php endif ?>
  <p class="muted" style="font-size:12px">Critical <?= $sev['CRITICAL'] ?> · Major <?= $sev['MAJOR'] ?> · Minor <?= $sev['MINOR'] ?></p></div>
</div>
<div class="card mt"><h2 class="mb">Quality checks &amp; tests</h2>
  <div class="grid g2" style="gap:4px 20px"><?php foreach (array_merge($checks, $tests) as $c): ?><div style="display:flex;justify-content:space-between;border-bottom:1px solid #f3ebe4;padding:6px 0"><span><?= h($c['name']) ?><?= !empty($c['remark']) ? '<br><small>' . h($c['remark']) . '</small>' : '' ?></span><?= badge($c['result'] ?: 'PENDING') ?></div><?php endforeach ?></div>
  <h2 class="mb mt">Measurements</h2>
  <?php if ($revising): ?><form method="post" id="revform"><?= csrf_field() ?><input type="hidden" name="act" value="revise"><input type="hidden" name="id" value="<?= $it['id'] ?>"><?php endif ?>
  <table class="t"><tr><th>Parameter</th><th>Spec</th><th>Tolerance</th><th>Actual</th><th>Result</th></tr>
  <?php foreach ($meas as $k => $m): ?><tr><td><?= h($m['param']) ?></td><td><?= h($m['spec'] . ' ' . ($m['unit'] ?? '')) ?></td><td>± <?= h($m['tol']) ?></td>
    <td><?= $revising ? '<input type="text" name="m_actual[' . $k . ']" value="' . h($m['actual']) . '" style="width:90px;padding:4px 6px" form="revform">' : h($m['actual']) ?></td><td><?= $m['result'] ? badge($m['result']) : '—' ?></td></tr><?php endforeach ?></table>
  <?php if ($revising): ?></form><?php endif ?>
</div>
<div class="card"><h2 class="mb">Photos (<?= count($photos) ?>)</h2>
  <?php if (!$photos) echo empty_state('No photos'); ?><div class="photos"><?php foreach ($photos as $p): ?><figure><a target="_blank" href="<?= url('file', ['p' => $p['path']]) ?>"><img src="<?= url('file', ['p' => $p['path']]) ?>" loading="lazy"></a><figcaption><b><?= h(ucfirst($p['kind'])) ?></b> <?= h($p['remark']) ?></figcaption></figure><?php endforeach ?></div>
</div>
<?php if ($it['hold_status']): ?><div class="card"><h2 class="mb">SKU hold history</h2><div class="note">Held for <b><?= h($it['hold_for']) ?></b> by <?= h($it['hold_by']) ?> · <?= fdt($it['hold_at']) ?><br><?= h($it['hold_reason']) ?><br><b>Response:</b> <?= h($it['hold_status']) ?> — <?= h($it['hold_response'] ?: '—') ?></div></div><?php endif ?>
</div>
<div>
<div class="card"><h2 class="mb">Completeness check</h2>
  <div class="complete"><?php foreach ($comp as $k => $ok): ?><span class="<?= $ok ? '' : 'no' ?>"><?= $ok ? '✓' : '✗' ?> <?= h($k) ?></span><?php endforeach ?></div></div>
<?php if ($canReview): ?>
<div class="card"><h2 class="mb">System recommendation: <?= h($it['system_result'] ?: '—') ?> · Manager final decision</h2>
<form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="approve"><input type="hidden" name="id" value="<?= $it['id'] ?>">
  <div class="decision"><?php foreach (['PASS' => 'PASS', 'FAIL' => 'FAIL', 'CONDITIONAL' => 'CONDITIONAL PASS (deviation → client)', 'PENDING' => 'PENDING'] as $k => $l): ?>
    <label><input type="radio" name="final" value="<?= $k ?>" <?= ($it['system_result'] ?: 'PASS') === $k ? 'checked' : '' ?> onchange="devbox.style.display=this.value==='CONDITIONAL'?'block':'none'"> <?= h($l) ?></label><?php endforeach ?></div>
  <div id="devbox" style="display:none" class="f"><label>Deviation for client (what is outside specification)</label>
    <table class="t"><tr><th>Parameter</th><th>Spec</th><th>Actual</th><th>Deviation</th><th>Pcs</th></tr>
    <?php $pre = array_values(array_filter($meas, fn($m) => ($m['result'] ?? '') === 'FAIL')); for ($k = 0; $k < 3; $k++): $m = $pre[$k] ?? null; ?><tr>
      <td><input name="dev_param[]" value="<?= h($m['param'] ?? '') ?>" style="padding:5px"></td><td><input name="dev_spec[]" value="<?= h($m ? $m['spec'] . ($m['unit'] ?? '') : '') ?>" style="padding:5px;width:70px"></td>
      <td><input name="dev_actual[]" value="<?= h($m ? $m['actual'] . ($m['unit'] ?? '') : '') ?>" style="padding:5px;width:70px"></td><td><input name="dev_dev[]" value="<?= $m ? h(sprintf('%+.2f', (float)$m['actual'] - (float)$m['spec'])) : '' ?>" style="padding:5px;width:70px"></td>
      <td><input name="dev_pcs[]" value="<?= $m ? (int)$it['inspected_qty'] : '' ?>" style="padding:5px;width:60px"></td></tr><?php endfor ?></table></div>
  <div class="f"><label>Inspector remark</label><div class="note"><?= h($it['remark'] ?: '—') ?><?= $it['action_required'] ? '<br><b>Action required:</b> ' . h($it['action_required']) : '' ?></div></div>
  <div class="f"><label>Manager note (required when overriding the system result)</label><textarea name="note"></textarea></div>
  <button class="btn pri block" data-confirm="Approve, lock the inspection and generate the PDF?">APPROVE &amp; GENERATE LOCKED PDF</button>
</form>
<div class="hr"></div>
<h2 class="mb">Return for correction</h2>
<form method="post"><?= csrf_field() ?><input type="hidden" name="act" value="return"><input type="hidden" name="id" value="<?= $it['id'] ?>">
  <div class="f"><label>Section to correct</label><div class="grid g2" style="gap:4px"><?php foreach (STEPS as $s): ?><label class="chk"><input type="checkbox" name="sections[]" value="<?= h($s) ?>" <?= ($comp[$s] ?? true) ? '' : 'checked' ?>> <?= h($s) ?></label><?php endforeach ?></div></div>
  <div class="f"><label>Reason for return</label><textarea name="reason" placeholder="Add close-up photo of defect &amp; check length measurement"></textarea></div>
  <button class="btn block">RETURN TO INSPECTOR</button>
</form></div>
<?php elseif ($it['status'] === 'RETURNED'): ?><div class="card"><div class="note ruby"><b>Returned for correction</b> (×<?= (int)$it['return_count'] ?>)<br>Sections: <?= h($it['return_sections']) ?><br><?= h($it['return_reason']) ?></div></div>
<?php elseif ($it['locked']): ?>
<div class="card"><h2 class="mb">Approved &amp; locked</h2><div class="kv"><div><small>Final result</small><b><?= badge($it['final_result']) ?></b></div><div><small>Revision</small><b><?= sprintf('%02d', $it['rev']) ?></b></div></div>
  <?php if (is_role('super_admin') && !$revising): ?><p class="muted" style="font-size:12.5px">Unlock needs Super Admin + reason; old PDF kept, new PDF re-sent.</p><?= btn('Unlock & create revision', url('review-item', ['id' => $it['id'], 'revise' => 1]), 'ruby block') ?><?php endif ?>
  <?php if ($revising): ?><div class="hr"></div><h2 class="mb">Revision <?= sprintf('%02d', $it['rev'] + 1) ?></h2>
    <div class="fr f"><div><label>Inspected</label><input form="revform" type="number" name="inspected_qty" value="<?= (int)$it['inspected_qty'] ?>"></div><div><label>Accepted</label><input form="revform" type="number" name="accepted_qty" value="<?= (int)$it['accepted_qty'] ?>"></div><div><label>Rejected</label><input form="revform" type="number" name="rejected_qty" value="<?= (int)$it['rejected_qty'] ?>"></div></div>
    <div class="f"><label>Final result</label><?= sel('final_result', ['PASS' => 'PASS', 'FAIL' => 'FAIL', 'CONDITIONAL' => 'CONDITIONAL PASS'], $it['final_result'], 'form="revform"') ?></div>
    <div class="f"><label>Inspector remark</label><textarea form="revform" name="remark"><?= h($it['remark']) ?></textarea></div>
    <div class="f"><label>Manager note</label><textarea form="revform" name="manager_note"><?= h($it['manager_note']) ?></textarea></div>
    <div class="f"><label>Reason for unlock (audit)</label><textarea form="revform" name="reason" required></textarea></div>
    <p class="muted" style="font-size:12px">Measurement “Actual” values on the left are editable too.</p>
    <button class="btn ruby block" form="revform">SAVE REVISION &amp; RE-SEND PDF</button><?php endif ?>
</div><?php endif ?>
</div></div>
<?php layout_end();
