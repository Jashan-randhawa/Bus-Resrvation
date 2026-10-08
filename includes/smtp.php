<?php
// includes/smtp.php -- Lightweight RFC 5321 compliant SMTP client (Issue 3)
declare(strict_types=1);

/**
 * Sends an email using direct SMTP socket connection.
 * Reads SMTP credentials from environment:
 * MAIL_HOST, MAIL_PORT, MAIL_USER, MAIL_PASS, MAIL_ENCRYPTION (tls, ssl), MAIL_FROM, MAIL_FROM_NAME.
 *
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $html_body HTML message content
 * @return array ['ok' => bool, 'error' => string]
 */
function smtp_send_mail(string $to, string $subject, string $html_body): array {
    $mail_enabled = (getenv('MAIL_ENABLED') === '1' || strtolower((string)getenv('MAIL_ENABLED')) === 'true' || ($_ENV['MAIL_ENABLED'] ?? '') === '1');
    if (!$mail_enabled) {
        return ['ok' => false, 'error' => 'Email delivery is not configured (MAIL_ENABLED is false).'];
    }

    $host = getenv('MAIL_HOST') ?: ($_ENV['MAIL_HOST'] ?? '');
    $port = (int)(getenv('MAIL_PORT') ?: ($_ENV['MAIL_PORT'] ?? 587));
    $user = getenv('MAIL_USER') ?: ($_ENV['MAIL_USER'] ?? '');
    $pass = getenv('MAIL_PASS') ?: ($_ENV['MAIL_PASS'] ?? '');
    $encryption = strtolower((string)(getenv('MAIL_ENCRYPTION') ?: ($_ENV['MAIL_ENCRYPTION'] ?? 'tls')));
    $from_email = getenv('MAIL_FROM') ?: ($_ENV['MAIL_FROM'] ?? 'no-reply@busreservation.local');
    $from_name  = getenv('MAIL_FROM_NAME') ?: ($_ENV['MAIL_FROM_NAME'] ?? 'Bus Reservation System');

    if ($host === '') {
        return ['ok' => false, 'error' => 'SMTP host not configured (MAIL_HOST is empty).'];
    }

    $socket_host = ($encryption === 'ssl') ? 'ssl://' . $host : $host;
    $errno = 0;
    $errstr = '';
    $timeout = 10;

    $socket = @fsockopen($socket_host, $port, $errno, $errstr, $timeout);
    if (!$socket) {
        return ['ok' => false, 'error' => "Could not connect to SMTP server {$host}:{$port} ({$errstr})"];
    }

    stream_set_timeout($socket, $timeout);

    $read_resp = function() use ($socket): string {
        $data = '';
        while (!feof($socket)) {
            $line = fgets($socket, 515);
            if ($line === false) break;
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };

    $send_cmd = function(string $cmd) use ($socket, $read_resp): string {
        fputs($socket, $cmd . "\r\n");
        return $read_resp();
    };

    $banner = $read_resp();
    if (!str_starts_with($banner, '220')) {
        fclose($socket);
        return ['ok' => false, 'error' => "SMTP greeting failed: " . trim($banner)];
    }

    $ehlo = $send_cmd("EHLO localhost");
    if (!str_starts_with($ehlo, '250')) {
        fclose($socket);
        return ['ok' => false, 'error' => "EHLO failed: " . trim($ehlo)];
    }

    if ($encryption === 'tls') {
        $starttls = $send_cmd("STARTTLS");
        if (str_starts_with($starttls, '220')) {
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                return ['ok' => false, 'error' => 'Failed to establish TLS encryption with SMTP server.'];
            }
            $send_cmd("EHLO localhost");
        }
    }

    if ($user !== '' && $pass !== '') {
        $auth = $send_cmd("AUTH LOGIN");
        if (!str_starts_with($auth, '334')) {
            fclose($socket);
            return ['ok' => false, 'error' => "SMTP AUTH LOGIN rejected: " . trim($auth)];
        }
        $user_res = $send_cmd(base64_encode($user));
        if (!str_starts_with($user_res, '334')) {
            fclose($socket);
            return ['ok' => false, 'error' => "SMTP username rejected: " . trim($user_res)];
        }
        $pass_res = $send_cmd(base64_encode($pass));
        if (!str_starts_with($pass_res, '235')) {
            fclose($socket);
            return ['ok' => false, 'error' => "SMTP authentication failed: " . trim($pass_res)];
        }
    }

    $mail_from_res = $send_cmd("MAIL FROM:<{$from_email}>");
    if (!str_starts_with($mail_from_res, '250')) {
        fclose($socket);
        return ['ok' => false, 'error' => "MAIL FROM failed: " . trim($mail_from_res)];
    }

    $rcpt_res = $send_cmd("RCPT TO:<{$to}>");
    if (!str_starts_with($rcpt_res, '250')) {
        fclose($socket);
        return ['ok' => false, 'error' => "RCPT TO failed: " . trim($rcpt_res)];
    }

    $data_res = $send_cmd("DATA");
    if (!str_starts_with($data_res, '354')) {
        fclose($socket);
        return ['ok' => false, 'error' => "DATA handshake failed: " . trim($data_res)];
    }

    $headers = [
        "From: =?UTF-8?B?" . base64_encode($from_name) . "?= <{$from_email}>",
        "To: <{$to}>",
        "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
        "MIME-Version: 1.0",
        "Content-Type: text/html; charset=UTF-8",
        "Content-Transfer-Encoding: 8bit",
        "Date: " . date(DATE_RFC2822)
    ];

    $message = implode("\r\n", $headers) . "\r\n\r\n" . $html_body . "\r\n.";
    $send_res = $send_cmd($message);
    $send_cmd("QUIT");
    fclose($socket);

    if (str_starts_with($send_res, '250')) {
        return ['ok' => true, 'error' => ''];
    }

    return ['ok' => false, 'error' => "SMTP server refused message: " . trim($send_res)];
}

/**
 * Dry-run SMTP test for diagnostics page (Issue 3).
 */
function smtp_diagnostics_check(): array {
    $mail_enabled = (getenv('MAIL_ENABLED') === '1' || strtolower((string)getenv('MAIL_ENABLED')) === 'true');
    if (!$mail_enabled) {
        return ['ok' => false, 'status' => 'DISABLED', 'message' => 'Outbound email disabled (MAIL_ENABLED is false).'];
    }
    $host = getenv('MAIL_HOST') ?: ($_ENV['MAIL_HOST'] ?? '');
    $port = (int)(getenv('MAIL_PORT') ?: ($_ENV['MAIL_PORT'] ?? 587));
    if ($host === '') {
        return ['ok' => false, 'status' => 'NOT_CONFIGURED', 'message' => 'MAIL_HOST is not set.'];
    }

    $errno = 0; $errstr = '';
    $socket = @fsockopen($host, $port, $errno, $errstr, 5);
    if (!$socket) {
        return ['ok' => false, 'status' => 'UNREACHABLE', 'message' => "Cannot reach {$host}:{$port} ({$errstr})"];
    }
    $banner = fgets($socket, 515);
    fclose($socket);
    if ($banner && str_starts_with($banner, '220')) {
        return ['ok' => true, 'status' => 'ONLINE', 'message' => "SMTP service online at {$host}:{$port}"];
    }
    return ['ok' => false, 'status' => 'ERROR', 'message' => "Unexpected SMTP banner: " . trim((string)$banner)];
}

/**
 * Standard alias for sending application SMTP mail.
 */
function app_smtp_send(string $to, string $subject, string $html_body): array {
    return smtp_send_mail($to, $subject, $html_body);
}
