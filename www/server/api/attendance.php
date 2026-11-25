<?php
/**
 * Attendance & Visit Log — built from factory check-in / check-out sessions.
 *  - many sessions per day (one per factory visit), duration per session and per day
 *  - missed check-out: reminder at att_remind_at (20:00), auto-flag next day, fix by QC Manager / Super Admin with reason
 *  - late arrival: first check-in after att_late_after (10:30)
 *  - monthly summary, daily table and a 3-sheet Excel export
 */

function att_due(): bool {
    $last = (int)setting('att_tick', '0'); return time() - $last >= 600;   // at most every 10 minutes
}
function att_tick(bool $force = false): void {
    if (!$force && !att_due()) return;
    set_setting('att_tick', (string)time());
    try {
        // 1) evening reminder for anyone still checked in today
        if (date('H:i') >= setting('att_remind_at', '20:00')) {
            foreach (all('SELECT c.id, c.user_id, COALESCE(v.name, c.place) vname FROM checkins c LEFT JOIN vendors v ON v.id=c.vendor_id WHERE c.out_at IS NULL AND c.at>=? AND COALESCE(c.reminded,0)=0', [today() . ' 00:00:00']) as $c) {
                notify((int)$c['user_id'], "⏰ You are still checked in at {$c['vname']}. Please CHECK-OUT from the factory in Q-TRACK app.", 'app');
                q('UPDATE checkins SET reminded=1 WHERE id=?', [$c['id']]);
            }
        }
        // 2) sessions left open from an earlier day → MISSED CHECK-OUT, managers alerted
        $mgr = array_map('intval', array_column(all("SELECT id FROM users WHERE role IN ('super_admin','qc_manager') AND status='ACTIVE'"), 'id'));
        foreach (all("SELECT c.id, c.at, u.emp_code, COALESCE(v.name, c.place) vname FROM checkins c JOIN users u ON u.id=c.user_id LEFT JOIN vendors v ON v.id=c.vendor_id WHERE c.out_at IS NULL AND c.at<? AND c.auto_flag IS NULL", [today() . ' 00:00:00']) as $c) {
            q("UPDATE checkins SET auto_flag='MISSED' WHERE id=?", [$c['id']]);
            foreach ($mgr as $m) notify($m, "⚠️ MISSED CHECK-OUT: {$c['emp_code']} at {$c['vname']} on " . date('d M', strtotime($c['at'])) . ' — verify in Attendance', 'attendance');
        }
    } catch (Throwable $e) { error_log('[qtrack] attendance tick: ' . $e->getMessage()); }
}

/** Admin-configurable HR attendance rules (Master Settings). */
function att_rules(): array {
    $d = ['att_late_after' => '10:30', 'att_remind_at' => '20:00', 'att_full_hrs' => '8', 'att_half_hrs' => '4', 'att_weekoff' => '0', 'att_late_grace' => '3', 'att_hd_on_late' => '0', 'att_leave_paid' => '1'];
    foreach ($d as $k => $v) $d[$k] = setting($k, $v); return $d;
}
function att_hm(int $min): string { return intdiv($min, 60) . 'h ' . str_pad((string)($min % 60), 2, '0', STR_PAD_LEFT) . 'm'; }
function att_hrs(int $min): string { return intdiv($min, 60) . ' Hrs ' . str_pad((string)($min % 60), 2, '0', STR_PAD_LEFT) . ' Mins'; }

/** Everything for one month: per inspector per day sessions, totals, flags. */
function att_month(string $ym, int $uid = 0): array {
    $from = "$ym-01"; $to = date('Y-m-t', strtotime($from)); $last = min($to, today());
    $users = all("SELECT id, name, emp_code, location FROM users WHERE role='inspector' AND status='ACTIVE'" . ($uid ? ' AND id=' . $uid : '') . ' ORDER BY emp_code');
    $late = setting('att_late_after', '10:30'); $R = att_rules();
    $fullH = (float)$R['att_full_hrs']; $halfH = (float)$R['att_half_hrs']; $woff = (int)$R['att_weekoff']; $grace = (int)$R['att_late_grace']; $hdLate = (int)$R['att_hd_on_late'] === 1; $leavePaid = (int)$R['att_leave_paid'] === 1;
    $mus = [];
    $ids = array_column($users, 'id') ?: [0]; $in = implode(',', array_map('intval', $ids));
    $ses = all("SELECT c.*, COALESCE(v.name, c.place) vname, j.client_id, cl.code ccode, j.job_no, fx.name fixer FROM checkins c LEFT JOIN vendors v ON v.id=c.vendor_id LEFT JOIN qc_jobs j ON j.id=c.job_id
        LEFT JOIN clients cl ON cl.id=j.client_id LEFT JOIN users fx ON fx.id=c.out_by WHERE c.user_id IN ($in) AND c.at>=? AND c.at<=? ORDER BY c.at", [$from . ' 00:00:00', $to . ' 23:59:59']);
    $work = all("SELECT j.inspector_id uid, i.insp_date d, j.vendor_id, COUNT(*) n, SUM(i.inspected_qty) pcs FROM qc_items i JOIN qc_jobs j ON j.id=i.job_id
        WHERE j.inspector_id IN ($in) AND i.insp_date>=? AND i.insp_date<=? AND i.status IN ('SUBMITTED','UNDER_REVIEW','APPROVED') GROUP BY j.inspector_id, i.insp_date, j.vendor_id", [$from, $to]);
    $W = []; foreach ($work as $w) { $W[$w['uid']][$w['d']]['n'] = ($W[$w['uid']][$w['d']]['n'] ?? 0) + (int)$w['n']; $W[$w['uid']][$w['d']]['pcs'] = ($W[$w['uid']][$w['d']]['pcs'] ?? 0) + (int)$w['pcs'];
        $W[$w['uid']][$w['d']]['v'][$w['vendor_id']] = ($W[$w['uid']][$w['d']]['v'][$w['vendor_id']] ?? 0) + (int)$w['pcs']; }
    $S = []; foreach ($ses as $s) $S[$s['user_id']][substr($s['at'], 0, 10)][] = $s;
    $rows = []; $sum = []; $reg = [];
    foreach ($users as $u) {
        $t = ['present' => 0, 'half' => 0, 'short' => 0, 'leave' => 0, 'absent' => 0, 'weekoff' => 0, 'min' => 0, 'net' => 0, 'transit' => 0, 'visits' => 0, 'missed' => 0, 'late' => 0, 'pcs' => 0, 'designs' => 0, 'days_worked' => 0];
        for ($d = $from; $d <= $to; $d = date('Y-m-d', strtotime("$d +1 day"))) {
            $list = $S[$u['id']][$d] ?? []; $wk = $W[$u['id']][$d] ?? null; $sessions = []; $dayMin = 0; $flags = [];
            foreach ($list as $s) {
                $open = !$s['out_at']; $missed = $s['auto_flag'] === 'MISSED';
                $end = $s['out_at'] ?: ($d === today() ? null : null);
                $min = $s['out_at'] ? max(0, (int)round((strtotime($s['out_at']) - strtotime($s['at'])) / 60)) : ($d === today() && $open ? max(0, (int)round((time() - strtotime($s['at'])) / 60)) : 0);
                $dayMin += $min; $t['visits']++;
                if ($missed) { $t['missed']++; $flags[] = $s['out_at'] ? 'MISSED CHECK-OUT (fixed)' : 'MISSED CHECK-OUT'; }
                $sessions[] = ['id' => (int)$s['id'], 'vendor' => $s['vname'], 'client' => $s['ccode'], 'job_no' => $s['job_no'], 'in' => $s['at'], 'in_ok' => (int)$s['verified'] === 1, 'in_dist' => $s['dist_m'] !== null ? (int)$s['dist_m'] : null,
                    'out' => $s['out_at'], 'out_ok' => $s['out_verified'] === null ? null : (int)$s['out_verified'] === 1, 'out_gps' => $s['out_lat'] !== null && $s['out_lat'] !== '', 'out_dist' => $s['out_dist'] !== null ? (int)$s['out_dist'] : null,
                    'min' => $min, 'purpose' => $s['purpose'] ?? null, 'manual' => !$s['vendor_id'], 'address' => $s['address'] ?? null, 'live' => $open && $d === today(), 'missed' => $missed, 'fixed' => $missed && $s['out_at'] ? true : false, 'fix_note' => $s['out_note'], 'fixer' => $s['fixer'], 'lat' => $s['lat'], 'lng' => $s['lng']];
                $vk = $s['vendor_id']; $reg[$vk] = $reg[$vk] ?? ['vendor' => $s['vname'], 'visits' => 0, 'min' => 0, 'pcs' => 0, 'list' => []];
                $reg[$vk]['visits']++; $reg[$vk]['min'] += $min; $reg[$vk]['list'][] = ['date' => $d, 'insp' => $u['emp_code'] . ' · ' . $u['name'], 'client' => $s['ccode'], 'in' => $s['at'], 'out' => $s['out_at'], 'min' => $min];
            }
            if ($wk) foreach ($wk['v'] as $vk => $p) if (isset($reg[$vk])) $reg[$vk]['pcs'] += $p;
            $lv = on_leave((int)$u['id'], $d); $dow = (int)date('w', strtotime($d)); $first = $list[0]['at'] ?? null;
            $C = att_day_calc($list); $live = $C['open'] && $d === today();
            $isLate = $first && substr($first, 11, 5) > $late;
            if ($isLate) { $t['late']++; $flags[] = 'LATE ARRIVAL'; }
            if ($list || $wk) {
                $status = 'PRESENT';
                if ($list && $fullH > 0 && !$live && $C['net'] < $fullH * 60 && !($C['open'] && !$C['last_out'])) $status = $C['net'] >= $halfH * 60 ? 'HALF DAY' : 'SHORT DAY';
                if ($status === 'PRESENT' && $isLate && $hdLate && $t['late'] > $grace) { $status = 'HALF DAY'; $flags[] = 'LATE → HALF DAY'; }
                if ($dow === $woff) $flags[] = 'WORKED ON WEEK OFF';
                if ($status === 'PRESENT') $t['present']++; elseif ($status === 'HALF DAY') $t['half']++; else $t['short']++;
            }
            elseif ($lv) { $status = 'ON LEAVE'; $t['leave']++; }
            elseif ($d > $last) $status = '';
            elseif ($dow === $woff) { $status = 'WEEK OFF'; $t['weekoff']++; }
            else { $status = 'ABSENT'; $t['absent']++; }
            $t['min'] += $dayMin; $t['net'] += $C['net']; $t['transit'] += $C['transit']; $t['pcs'] += (int)($wk['pcs'] ?? 0); $t['designs'] += (int)($wk['n'] ?? 0);
            $mus[$u['id']][$d] = ['PRESENT' => 'P', 'HALF DAY' => 'HD', 'SHORT DAY' => 'SD', 'ON LEAVE' => 'L', 'WEEK OFF' => 'WO', 'ABSENT' => 'A', '' => ''][$status] ?? '';
            if ($status === '' && !$list) continue;
            if (!$list && !$wk && $uid === 0 && $status !== 'ON LEAVE') continue;   // all-team table: only visit / work / leave days (absent days counted in summary, listed per inspector)
            $rows[] = ['date' => $d, 'day' => date('D', strtotime($d)), 'uid' => (int)$u['id'], 'insp' => $u['emp_code'] . ' · ' . explode(' ', $u['name'])[0], 'sessions' => $sessions,
                'min' => $dayMin, 'first_in' => $C['first_in'], 'last_out' => $C['last_out'], 'net' => $C['net'], 'transit' => $C['transit'], 'designs' => (int)($wk['n'] ?? 0), 'pcs' => (int)($wk['pcs'] ?? 0), 'status' => $status, 'leave' => $lv, 'flags' => $flags];
        }
        $wd = $t['present'] + $t['half'] + $t['short'];
        $t['avg'] = $wd ? (int)round($t['net'] / $wd) : 0;
        $t['payable'] = $t['present'] + $t['half'] / 2 + ($leavePaid ? $t['leave'] : 0) + $t['weekoff'];
        $sum[] = ['uid' => (int)$u['id'], 'name' => $u['name'], 'code' => $u['emp_code'], 'location' => $u['location']] + $t;
    }
    usort($rows, fn($a, $b) => [$a['date'], $a['insp']] <=> [$b['date'], $b['insp']]);
    $T = ['present' => 0, 'half' => 0, 'short' => 0, 'leave' => 0, 'absent' => 0, 'weekoff' => 0, 'min' => 0, 'net' => 0, 'transit' => 0, 'payable' => 0, 'visits' => 0, 'missed' => 0, 'late' => 0, 'pcs' => 0, 'designs' => 0];
    foreach ($sum as $s) foreach ($T as $k => $_) $T[$k] += $s[$k];
    uasort($reg, fn($a, $b) => $b['visits'] <=> $a['visits']);
    return ['month' => $ym, 'from' => $from, 'to' => $to, 'late_after' => $late, 'remind_at' => setting('att_remind_at', '20:00'), 'users' => array_map(fn($u) => ['id' => (int)$u['id'], 'label' => $u['emp_code'] . ' · ' . $u['name']], all("SELECT id, name, emp_code FROM users WHERE role='inspector' AND status='ACTIVE' ORDER BY emp_code")),
        'totals' => $T, 'summary' => $sum, 'rows' => array_reverse($rows), 'register' => array_values($reg), 'rules' => $R, 'muster' => $mus, 'days' => (int)date('t', strtotime($from))];
}

api('GET', 'attendance', ['super_admin', 'qc_manager', 'back_office'], function () {
    $ym = (string)in_('month', date('Y-m')); if (!preg_match('/^\d{4}-\d{2}$/', $ym)) $ym = date('Y-m');
    att_tick();
    return att_month($ym, (int)in_('user')) + ['can_fix' => is_role('super_admin', 'qc_manager')];
});

/** QC Manager / Super Admin: set the check-out time of a missed / wrong session, with a reason (kept on the record + audit). */
api('POST', 'attendance/fix', ['super_admin', 'qc_manager'], function () {
    $c = one('SELECT * FROM checkins WHERE id=?', [(int)in_('id')]); if (!$c) fail('Session not found', 404);
    $reason = trim((string)in_('reason')); if (mb_strlen($reason) < 3) fail('Write the reason / how it was verified.');
    $t = (string)in_('out_time'); if (!preg_match('/^\d{2}:\d{2}$/', $t)) fail('Enter the check-out time like 18:30.');
    $out = substr($c['at'], 0, 10) . " $t:00";
    if ($out <= $c['at']) fail('Check-out time must be after the check-in time (' . date('h:i A', strtotime($c['at'])) . ').');
    $old = $c['out_at'];
    update('checkins', ['out_at' => $out, 'out_note' => $reason, 'out_by' => user()['id'], 'auto_flag' => $c['auto_flag'] ?: 'EDITED'], 'id=?', [$c['id']]);
    audit('checkin', (int)$c['id'], 'CHECKOUT_FIXED', 'out_at', $old, $out . ' · ' . $reason);
    notify((int)$c['user_id'], 'Your check-out on ' . date('d M', strtotime($c['at'])) . ' was set to ' . date('h:i A', strtotime($out)) . ' by ' . user()['name'] . '.', 'app');
    return ['ok' => true, 'message' => 'Check-out set to ' . date('h:i A', strtotime($out)) . ' — session ' . att_hm((int)round((strtotime($out) - strtotime($c['at'])) / 60)) . '.'];
});

api('POST', 'attendance/settings', ['super_admin'], function () {
    foreach (['att_late_after', 'att_remind_at'] as $k) { $v = (string)in_($k); if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v)) fail('Time like 10:30.'); set_setting($k, $v); }
    $full = (float)in_('att_full_hrs', 8); $half = (float)in_('att_half_hrs', 4);
    if ($full < 0 || $full > 16 || $half < 0 || $half > $full) fail('Full-day hours 0–16 and half-day hours must be less than full-day hours.');
    $w = (int)in_('att_weekoff', 0); if ($w < -1 || $w > 6) fail('Choose the weekly off day.');
    set_setting('att_full_hrs', (string)$full); set_setting('att_half_hrs', (string)$half); set_setting('att_weekoff', (string)$w);
    set_setting('att_late_grace', (string)max(0, min(31, (int)in_('att_late_grace', 3))));
    set_setting('att_hd_on_late', in_('att_hd_on_late') ? '1' : '0'); set_setting('att_leave_paid', in_('att_leave_paid') ? '1' : '0');
    return ['ok' => true, 'message' => 'Attendance rules saved.'];
});

/** 3-sheet monthly Excel: team summary · date-wise log · vendor-wise visit register. */
api('GET', 'attendance/export', ['super_admin', 'qc_manager', 'back_office'], function () {
    $ym = (string)in_('month', date('Y-m')); if (!preg_match('/^\d{4}-\d{2}$/', $ym)) $ym = date('Y-m');
    require_once ROOT . '/app/xlsx.php';
    $A = att_month($ym, (int)in_('user')); $mon = date('F Y', strtotime($A['from']));
    $tm = fn($s) => $s ? date('h:i A', strtotime($s)) : '—';
    $R = $A['rules']; $wdn = ['-1' => 'None', '0' => 'Sunday', '1' => 'Monday', '2' => 'Tuesday', '3' => 'Wednesday', '4' => 'Thursday', '5' => 'Friday', '6' => 'Saturday'][$R['att_weekoff']] ?? 'Sunday';
    $s1 = [["JEWELSETU Q-TRACK · HR Attendance & Payroll Register · $mon"], ["Rules: full day ≥ {$R['att_full_hrs']} h net shift · half day ≥ {$R['att_half_hrs']} h · late after {$R['att_late_after']} · weekly off $wdn · net shift = first check-in to last check-out"],
        ['Emp code', 'Inspector', 'Base', 'Full days (P)', 'Half days (HD)', 'Short days (SD)', 'Leave (L)', 'Week off (WO)', 'Absent (A)', 'Payable days', 'Net shift hours', 'On-site hours', 'Transit / travel hours', 'Avg net hrs / day', 'Visits', 'Designs checked', 'Pcs checked', 'Late arrivals', 'Missed check-outs']];
    foreach ($A['summary'] as $s) $s1[] = [$s['code'], $s['name'], $s['location'], $s['present'], $s['half'], $s['short'], $s['leave'], $s['weekoff'], $s['absent'], $s['payable'], att_hrs($s['net']), att_hrs($s['min']), att_hrs($s['transit']), att_hrs($s['avg']), $s['visits'], $s['designs'], $s['pcs'], $s['late'], $s['missed']];
    $T = $A['totals']; $s1[] = ['TOTAL', '', '', $T['present'], $T['half'], $T['short'], $T['leave'], $T['weekoff'], $T['absent'], $T['payable'], att_hrs($T['net']), att_hrs($T['min']), att_hrs($T['transit']), '', $T['visits'], $T['designs'], $T['pcs'], $T['late'], $T['missed']];
    $hd = ['Emp code', 'Inspector']; for ($i = 1; $i <= $A['days']; $i++) $hd[] = $i . ' ' . date('D', strtotime(sprintf('%s-%02d', $ym, $i)))[0];
    $sm = [["Day-by-Day HR Muster Roll · $mon"], ['P = full day · HD = half day · SD = short day · L = leave · WO = week off · A = absent'], array_merge($hd, ['P', 'HD', 'L', 'A', 'Payable'])];
    foreach ($A['summary'] as $s) { $r = [$s['code'], $s['name']]; for ($i = 1; $i <= $A['days']; $i++) $r[] = $A['muster'][$s['uid']][sprintf('%s-%02d', $ym, $i)] ?? ''; $sm[] = array_merge($r, [$s['present'], $s['half'], $s['leave'], $s['absent'], $s['payable']]); }
    $s2 = [["Date-wise Daily Log (Check-In / Check-Out) · $mon"], [''], ['Date', 'Day', 'Inspector', 'First in', 'Last out', 'Net shift', 'Transit', 'Vendor / place (client)', 'Purpose', 'Job card', 'Check-in time', 'Check-in GPS', 'Check-out time', 'Check-out GPS', 'Session duration', 'Daily working hours', 'Designs', 'Pcs checked', 'Status', 'Flags / note']];
    foreach (array_reverse($A['rows']) as $r) {
        if (!$r['sessions']) { $s2[] = [date('d-m-Y', strtotime($r['date'])), $r['day'], $r['insp'], '', '', '', '', '—', '', '', '—', '', '—', '', '', att_hrs(0), $r['designs'] ?: '', $r['pcs'] ?: '', $r['status'] . ($r['leave'] ? ' (' . $r['leave'] . ')' : ''), implode(', ', $r['flags'])]; continue; }
        foreach ($r['sessions'] as $k => $s) $s2[] = [$k ? '' : date('d-m-Y', strtotime($r['date'])), $k ? '' : $r['day'], $k ? '' : $r['insp'], $k ? '' : $tm($r['first_in']), $k ? '' : $tm($r['last_out']), $k ? '' : att_hm($r['net']), $k ? '' : att_hm($r['transit']),
            $s['vendor'] . ($s['client'] ? ' (' . $s['client'] . ')' : ''), $s['purpose'] ?? '', $s['job_no'],
            $tm($s['in']), $s['in_ok'] ? 'Verified ✓ ' . ($s['in_dist'] !== null ? $s['in_dist'] . ' m' : '') : ($s['manual'] ? 'GPS captured' : 'Pin pending'), $s['out'] ? $tm($s['out']) : ($s['live'] ? 'On-site now' : 'MISSED'),
            $s['out_ok'] === true ? 'Verified ✓' : ($s['out_ok'] === false ? 'Outside fence' : ($s['out_gps'] ? 'Captured' : ($s['fixed'] ? 'Set by manager' : '—'))), att_hm($s['min']),
            $k ? '' : att_hrs($r['min']), $k ? '' : ($r['designs'] ?: ''), $k ? '' : ($r['pcs'] ?: ''), $k ? '' : $r['status'], trim(($k ? '' : implode(', ', $r['flags'])) . ($s['fix_note'] ? ' · fixed by ' . $s['fixer'] . ': ' . $s['fix_note'] : ''), ' ·,')];
    }
    $s3 = [["Vendor-wise Visit Register · $mon (client billing & travel allowance)"], [''], ['Vendor / factory', 'Total visits', 'Total hours at factory', 'Pcs checked', 'Date', 'Inspector', 'Client', 'In', 'Out', 'Duration']];
    foreach ($A['register'] as $g) {
        $s3[] = [$g['vendor'], $g['visits'], att_hrs($g['min']), $g['pcs'], '', '', '', '', '', ''];
        foreach ($g['list'] as $x) $s3[] = ['', '', '', '', date('d-m-Y', strtotime($x['date'])), $x['insp'], $x['client'], $tm($x['in']), $x['out'] ? $tm($x['out']) : 'MISSED', att_hm($x['min'])];
    }
    $bin = xlsx_book(['Payroll Summary' => $s1, 'HR Muster Roll' => $sm, 'Date-wise Daily Log' => $s2, 'Vendor Visit Register' => $s3], 2);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="HR_Attendance_Payroll_' . $ym . '.xlsx"');
    echo $bin; exit;
});
