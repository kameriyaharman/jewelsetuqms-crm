<?php
require_once ROOT . '/lib/phpmailer/src/Exception.php';
require_once ROOT . '/lib/phpmailer/src/PHPMailer.php';
require_once ROOT . '/lib/phpmailer/src/SMTP.php';

/**
 * Send one email. Transport (Settings → Email): smtp | phpmail (shared-hosting mail()) | none (log only).
 * Returns [status, error].
 */
function send_mail(string $to, string $subject, string $html, array $attach = [], array $cc = []): array {
    $t = setting('mail_transport', 'none');
    if ($t === 'none') return ['LOGGED', 'Email transport is off (Settings → Email). Report published on portals.'];
    $m = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        if ($t === 'smtp') {
            $m->isSMTP(); $m->Host = setting('smtp_host'); $m->Port = (int)setting('smtp_port', 587);
            $m->SMTPAuth = setting('smtp_user') !== ''; $m->Username = setting('smtp_user'); $m->Password = setting('smtp_pass');
            $sec = setting('smtp_secure', 'tls'); $m->SMTPSecure = $sec === 'none' ? '' : $sec; $m->Timeout = 15;
        } else $m->isMail();
        $m->CharSet = 'UTF-8';
        $m->setFrom(setting('mail_from', 'reports@jewelsetu.com'), setting('mail_from_name', 'JEWELSETU Q-TRACK'));
        $m->addAddress($to);
        foreach ($cc as $c) if ($c) $m->addCC($c);
        $m->Subject = $subject; $m->isHTML(true); $m->Body = $html; $m->AltBody = strip_tags(str_replace(['<br>', '</p>'], "\n", $html));
        foreach ($attach as $a) if (is_file($a)) $m->addAttachment($a);
        $m->send();
        return ['SENT', null];
    } catch (Throwable $e) {
        return ['FAILED', $m->ErrorInfo ?: $e->getMessage()];
    }
}

/** Email the report to vendor, client head office and JEWELSETU admin; logs every recipient. */
function email_report(array $job, ?array $item, string $pdfFile, string $subject, string $message): array {
    $rcpt = [
        'Vendor' => $job['vendor_email'],
        'Client head office' => $job['client_ho_email'] ?: $job['client_email'],
        'JEWELSETU admin' => setting('admin_email'),
    ];
    $html = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#2b2224">' . nl2br(h($message)) .
        '<p style="margin-top:18px;color:#85767a;font-size:12px">— JEWELSETU Quality Services · Q-TRACK<br>Report is also available on your portal.</p></div>';
    $res = [];
    foreach ($rcpt as $type => $email) {
        if (!$email) continue;
        [$st, $err] = send_mail($email, $subject, $html, [$pdfFile]);
        insert('email_log', ['job_id' => $job['id'], 'item_id' => $item['id'] ?? null, 'recipient_type' => $type, 'email' => $email,
            'subject' => $subject, 'status' => $st, 'error' => $err, 'sent_at' => now()]);
        $res[$type] = $st;
    }
    return $res;
}
