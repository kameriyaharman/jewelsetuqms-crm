<?php
/* =============================================================================
 * Automated daily end-of-day (EOD) inspection summary.
 * Vendor gets its own numbers (per client); client gets one consolidated vendor-wise table.
 * Sent at the configured time (lazy trigger on the first request after that time, or the
 * cron URL for shared hosting), or now via "Send Daily Summary Now" with preview.
 * Email (HTML table + design-wise Excel), WhatsApp copy text and portal dashboard card.
 * ========================================================================== */

/** All inspected lots of one date, grouped vendor → client with totals. */
function daily_data(string $date): array {
    $F = ['client' => 0, 'vendor' => 0, 'insp' => 0, 'scope' => '', 'q' => ''];
    $rows = mis_rows($F, $date, $date);
    $groups = [];
    foreach ($rows as $r) {
        $k = $r['vendor_id'] . '|' . $r['client_id'];
        $groups[$k] ??= ['vendor_id' => (int)$r['vendor_id'], 'vendor' => $r['vendor_name'], 'client_id' => (int)$r['client_id'], 'client' => $r['client_code'], 'client_name' => $r['client_name'], 'rows' => []];
        $groups[$k]['rows'][] = $r;
    }
    foreach ($groups as &$g) {
        $rs = $g['rows']; $s = mis_sum($rs); $roots = [];
        foreach ($rs as $r) $roots[line_root_id($r)] = (int)$r['job_id'];
        $pend = 0; foreach ($roots as $root => $jid) { static $jc = []; $jc[$jid] ??= one('SELECT * FROM qc_jobs WHERE id=?', [$jid]); $pend += line_stats($jc[$jid], $root)['pending']; }
        $g += ['designs' => $s['designs'], 'pcs' => $s['insp'], 'ok' => $s['acc'], 'rej' => $s['rej'], 'pending' => $pend, 'rate' => $s['rate'], 'ok_pct' => mis_pct($s['acc'], $s['insp'])];
    }
    unset($g);
    return array_values($groups);
}
function dsum_tot(array $gs): array {
    $t = ['designs' => 0, 'pcs' => 0, 'ok' => 0, 'rej' => 0, 'pending' => 0];
    foreach ($gs as $g) foreach ($t as $k => $_) $t[$k] += $g[$k];
    $t['rate'] = mis_pct($t['rej'], $t['pcs']); $t['ok_pct'] = mis_pct($t['ok'], $t['pcs']); return $t;
}
/** Messages per recipient: vendors (per client block) and clients (vendor-wise table). */
function daily_messages(string $date, ?string $only = null): array {
    $gs = daily_data($date); $d = date('d/m/Y', strtotime($date)); $out = [];
    $byV = []; $byC = []; foreach ($gs as $g) { $byV[$g['vendor_id']][] = $g; $byC[$g['client_id']][] = $g; }
    $th = 'style="background:#0f3d45;color:#fff;padding:7px 9px;text-align:left;font-size:12px"'; $td = 'style="padding:7px 9px;border-bottom:1px solid #e6e9ee;font-size:13px"';
    $wrap = fn($title, $body) => '<div style="font-family:Arial,sans-serif;color:#1d2b33;max-width:720px"><div style="background:#0f3d45;color:#fff;padding:14px 16px;border-bottom:3px solid #c9a24b"><b style="font-size:16px">' . h($title) . '</b><br><span style="color:#e7d4a2;font-size:12px">' . h(setting('company_name', 'JEWELSETU Quality Services')) . ' · Q-TRACK</span></div><div style="padding:14px 4px">' . $body . '<p style="color:#6b7780;font-size:11.5px;margin-top:16px">Design-wise sheet attached. Full reports are on your Q-TRACK portal. This is an automated end-of-day summary.</p></div></div>';
    foreach ($byV as $vid => $list) {
        if ($only && $only !== "vendor:$vid") continue;
        $v = one('SELECT * FROM vendors WHERE id=?', [$vid]); $t = dsum_tot($list);
        $txt = "*JEWELSETU Daily QC Summary — $d*\nVendor: {$v['name']}\n"; $html = '';
        foreach ($list as $g) {
            $txt .= "\nClient: {$g['client_name']} ({$g['client']})\n• Total Checked Designs (SKUs): {$g['designs']} SKUs\n• Total Checked Qty: " . num($g['pcs']) . " pcs\n• Total OK (Accepted) Qty: " . num($g['ok']) . ' pcs (' . number_format($g['ok_pct'], 1) . "%)\n• Total Repair / Rejected Qty: " . num($g['rej']) . ' pcs (' . number_format($g['rate'], 1) . "%)\n• Pending / Open Balance Qty: " . num($g['pending']) . " pcs\n";
            $html .= '<table style="border-collapse:collapse;width:100%;margin-bottom:12px"><tr><td colspan="2" ' . $th . '>Client: ' . h($g['client_name']) . ' (' . h($g['client']) . ')</td></tr>'
                . implode('', array_map(fn($x) => "<tr><td $td>{$x[0]}</td><td $td><b>{$x[1]}</b></td></tr>", [['Total checked designs (SKUs)', $g['designs'] . ' SKUs'], ['Total checked qty', num($g['pcs']) . ' pcs'],
                    ['Total OK (accepted) qty', num($g['ok']) . ' pcs (' . number_format($g['ok_pct'], 1) . '%)'], ['Total repair / rejected qty', num($g['rej']) . ' pcs (' . number_format($g['rate'], 1) . '%)'], ['Pending / open balance qty', num($g['pending']) . ' pcs']])) . '</table>';
        }
        $out[] = ['key' => "vendor:$vid", 'type' => 'Vendor', 'name' => $v['name'], 'emails' => array_values(array_filter([$v['email'], $v['ho_email'] ?? null])), 'totals' => $t, 'groups' => $list,
            'subject' => "JEWELSETU Daily QC Summary — $d — {$v['name']}", 'text' => trim($txt) . "\n\n_(Design-wise sheet sent on email / available on portal)_", 'html' => $wrap("JEWELSETU Daily QC Summary — $d · Vendor: {$v['name']}", $html)];
    }
    foreach ($byC as $cid => $list) {
        if ($only && $only !== "client:$cid") continue;
        $c = one('SELECT * FROM clients WHERE id=?', [$cid]); $t = dsum_tot($list);
        $txt = "*JEWELSETU Daily QC Summary — $d*\nClient: {$c['name']} ({$c['code']})\n";
        foreach ($list as $g) $txt .= "\n▪ {$g['vendor']}: {$g['designs']} SKUs · " . num($g['pcs']) . ' pcs checked · OK ' . num($g['ok']) . ' · Repair/Rej ' . num($g['rej']) . ' (' . number_format($g['rate'], 1) . '%)';
        $txt .= "\n\n*TOTAL* — " . count($list) . ' vendor(s) · ' . $t['designs'] . ' SKUs · ' . num($t['pcs']) . ' pcs · OK ' . num($t['ok']) . ' · Repair/Rej ' . num($t['rej']) . ' (' . number_format($t['rate'], 1) . '%)';
        $rowsH = implode('', array_map(fn($g) => "<tr><td $td>$d</td><td $td><b>" . h($g['vendor']) . "</b></td><td $td align=\"right\">{$g['designs']}</td><td $td align=\"right\">" . num($g['pcs']) . "</td><td $td align=\"right\">" . num($g['ok']) . "</td><td $td align=\"right\">" . num($g['rej']) . "</td><td $td align=\"right\">" . number_format($g['rate'], 1) . '%</td></tr>', $list));
        $tot = '<tr style="background:#f6eed9;font-weight:bold"><td ' . $td . '>TOTAL</td><td ' . $td . '>' . count($list) . ' Vendor(s)</td><td ' . $td . ' align="right">' . $t['designs'] . ' SKUs</td><td ' . $td . ' align="right">' . num($t['pcs']) . '</td><td ' . $td . ' align="right">' . num($t['ok']) . '</td><td ' . $td . ' align="right">' . num($t['rej']) . '</td><td ' . $td . ' align="right">' . number_format($t['rate'], 1) . '%</td></tr>';
        $html = "<table style=\"border-collapse:collapse;width:100%\"><tr><th $th>Date</th><th $th>Vendor name</th><th $th>Checked designs (SKUs)</th><th $th>Checked pcs</th><th $th>OK pcs</th><th $th>Repair / rejected pcs</th><th $th>Rejection %</th></tr>$rowsH$tot</table>";
        $out[] = ['key' => "client:$cid", 'type' => 'Client', 'name' => $c['name'] . ' (' . $c['code'] . ')', 'emails' => array_values(array_filter([$c['ho_email'] ?: $c['email']])), 'totals' => $t, 'groups' => $list,
            'subject' => "JEWELSETU Daily QC Summary — $d — {$c['code']}", 'text' => $txt, 'html' => $wrap("JEWELSETU Daily QC Summary — $d · Client: {$c['name']}", $html)];
    }
    return $out;
}
/** Design-wise sheet for one recipient. */
function daily_sheet(array $msg, string $date): string {
    require_once ROOT . '/app/xlsx.php';
    $rows = [['JEWELSETU Daily QC Summary — ' . date('d/m/Y', strtotime($date)) . ' — ' . $msg['name']], [], ['Date', 'Client', 'Vendor', 'PO no.', 'Job card', 'SKU / design', 'Lot', 'Product', 'Offered', 'Checked pcs', 'OK pcs', 'Repair / rejected', 'Result / status', 'Report no.', 'Inspector']];
    foreach ($msg['groups'] as $g) foreach ($g['rows'] as $r) $rows[] = [$date, $r['client_code'], $r['vendor_name'], $r['po_no'], $r['job_no'], $r['sku'], (int)($r['lot_seq'] ?: 1), $r['product'], (int)$r['ready_qty'], (int)$r['inspected_qty'], (int)$r['accepted_qty'], (int)$r['rejected_qty'], $r['status'] === 'APPROVED' ? $r['final_result'] : $r['status'], $r['report_no'] ?? '', trim(($r['insp_code'] ?? '') . ' ' . ($r['insp_name'] ?? ''))];
    $t = $msg['totals']; $rows[] = ['TOTAL', '', '', '', '', $t['designs'] . ' SKUs', '', '', '', $t['pcs'], $t['ok'], $t['rej'], number_format($t['rate'], 1) . '% rejection'];
    $dir = sys_get_temp_dir() . '/qt_eod'; if (!is_dir($dir)) mkdir($dir, 0775, true);
    $f = $dir . '/Daily_QC_Summary_' . $date . '_' . preg_replace('/[^A-Za-z0-9]+/', '_', $msg['name']) . '.xlsx';
    file_put_contents($f, xlsx_book(['Today\'s QC' => $rows], 2)); return $f;
}
/** Send summaries; returns counts. */
function daily_send(string $date, ?string $only = null, bool $auto = false): array {
    require_once ROOT . '/app/mailer.php';
    $msgs = daily_messages($date, $only); $sent = 0; $skipped = []; $cc = array_values(array_filter(array_map('trim', explode(',', setting('daily_summary_cc', '')))));
    foreach ($msgs as $m) {
        if (!$m['emails']) { $skipped[] = $m['name'] . ' (no email)'; continue; }
        $file = daily_sheet($m, $date);
        foreach ($m['emails'] as $i => $to) {
            [$st, $err] = send_mail($to, $m['subject'], $m['html'], [$file], $i === 0 ? $cc : []);
            insert('email_log', ['job_id' => null, 'item_id' => null, 'recipient_type' => 'Daily summary · ' . $m['type'], 'email' => $to . ($i === 0 && $cc ? ' (cc ' . implode(', ', $cc) . ')' : ''), 'subject' => $m['subject'], 'status' => $st, 'error' => $err, 'sent_at' => now()]);
        }
        notify_role(strtolower($m['type']), '📊 Daily QC summary ' . date('d/m/Y', strtotime($date)) . ': ' . $m['totals']['designs'] . ' SKUs · ' . num($m['totals']['pcs']) . ' pcs · OK ' . num($m['totals']['ok']), 'dashboard',
            [strtolower($m['type']) . '_id' => (int)explode(':', $m['key'])[1]]);
        @unlink($file); $sent++;
    }
    audit('settings', 0, $auto ? 'DAILY_SUMMARY_AUTO' : 'DAILY_SUMMARY_SENT', 'date', null, "$date · $sent recipient(s)" . ($only ? " · $only" : ''));
    if (!$only) set_setting('daily_summary_last', $date);
    return ['sent' => $sent, 'skipped' => $skipped, 'total' => count($msgs)];
}
/** Lazy scheduler: true when today's automatic summary is due and not yet sent. */
function daily_due(): bool {
    if (setting('daily_summary_on', '0') !== '1' || setting('daily_summary_last', '') === today()) return false;
    return date('H:i') >= setting('daily_summary_time', '19:30');
}
function daily_run_auto(): void {
    if (!daily_due()) return;
    set_setting('daily_summary_last', today());   // claim first so parallel requests do not double-send
    try { daily_send(today(), null, true); } catch (Throwable $e) { error_log('[qtrack] daily summary: ' . $e->getMessage()); }
}

api('GET', 'summary/daily', R_INT, function () {
    $date = (string)(in_('date') ?: today());
    $m = daily_messages($date);
    return ['date' => $date, 'messages' => array_map(fn($x) => array_diff_key($x, ['groups' => 1]), $m), 'on' => setting('daily_summary_on', '0') === '1', 'time' => setting('daily_summary_time', '19:30'),
        'last' => setting('daily_summary_last', ''), 'cc' => setting('daily_summary_cc', ''), 'transport' => setting('mail_transport', 'none')];
});
api('POST', 'summary/send', R_INT, function () {
    $date = (string)(in_('date') ?: today()); $only = in_('key') ?: null;
    $r = daily_send($date, $only);
    if (!$r['total']) fail('No inspections on ' . fdate($date) . ' — nothing to send.');
    $t = setting('mail_transport', 'none');
    return ['ok' => true, 'message' => "Daily summary sent to {$r['sent']} recipient(s)" . ($r['skipped'] ? ' · skipped: ' . implode(', ', $r['skipped']) : '') . ($t === 'none' ? ' — email transport is OFF in Settings, so mails were only logged (portal alerts sent).' : '.')];
});
/** Portal card: today's numbers for the logged-in client / vendor. */
api('GET', 'summary/today', ['client', 'vendor'], function () {
    $u = user(); $key = role() === 'client' ? 'client:' . $u['client_id'] : 'vendor:' . $u['vendor_id'];
    $m = daily_messages(today(), $key);
    return ['date' => today(), 'msg' => $m ? array_diff_key($m[0], ['groups' => 1, 'html' => 1]) + ['rows' => array_map(fn($g) => array_diff_key($g, ['rows' => 1]), $m[0]['groups'])] : null];
});
/** Cron URL for shared hosting: /api/cron/daily?key=… (any time after the set time; sends once a day). */
api('GET', 'cron/daily', null, function () {
    if (!hash_equals(setting('cron_key', 'x'), (string)in_('key'))) fail('Bad key', 403);
    if (!daily_due()) return ['ok' => true, 'message' => 'Not due (off, already sent today, or before ' . setting('daily_summary_time', '19:30') . ').'];
    set_setting('daily_summary_last', today());
    return ['ok' => true] + daily_send(today(), null, true);
});
