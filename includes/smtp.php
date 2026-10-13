<?php
// includes/smtp.php -- Transactional Email Client: Brevo HTTPS REST API (Port 443) + RFC 5321 SMTP
declare(strict_types=1);

/**
 * Returns the configured Brevo REST API key (xkeysib-...) if available.
 */
function brevo_resolve_api_key(): string {
    $key = trim((string)(getenv('BREVO_API_KEY') ?: ($_ENV['BREVO_API_KEY'] ?? '')));
    if ($key !== '') {
        return $key;
    }
    $mailPass = trim((string)(getenv('MAIL_PASS') ?: ($_ENV['MAIL_PASS'] ?? '')));
    if (str_starts_with($mailPass, 'xkeysib-')) {
        return $mailPass;
    }
    return '';
}

/**
 * Resolves sender name and email from MAIL_FROM / MAIL_FROM_NAME / EMAIL_FROM / GMAIL_USER.
 * Supports combined format: "Sender Name" <sender@example.com> or plain sender@example.com.
 *
 * @return array{name: string, email: string}
 */
function resolve_mail_sender(): array {
    $defaultName = trim((string)(getenv('MAIL_FROM_NAME') ?: ($_ENV['MAIL_FROM_NAME'] ?? 'Bus Reservation System')));
    if ($defaultName === '') {
        $defaultName = 'Bus Reservation System';
    }

    // 1. Check explicit MAIL_FROM
    $mailFrom = trim((string)(getenv('MAIL_FROM') ?: ($_ENV['MAIL_FROM'] ?? '')));
    if ($mailFrom !== '' && $mailFrom !== 'reservations@example.com' && $mailFrom !== 'no-reply@busreservation.local') {
        if (preg_match('/^"?([^"<]*)"?\s*<([^>]+)>$/', $mailFrom, $m)) {
            return [
                'name'  => trim($m[1]) !== '' ? trim($m[1]) : $defaultName,
                'email' => trim($m[2]),
            ];
        }
        return ['name' => $defaultName, 'email' => $mailFrom];
    }

    // 2. Check EMAIL_FROM (e.g. "Bus Reservation System" <verified@gmail.com>)
    $emailFrom = trim((string)(getenv('EMAIL_FROM') ?: ($_ENV['EMAIL_FROM'] ?? '')));
    if ($emailFrom !== '') {
        if (preg_match('/^"?([^"<]*)"?\s*<([^>]+)>$/', $emailFrom, $m)) {
            $parsedName = trim($m[1]);
            if ($parsedName === '' || stripos($parsedName, 'Fitness') !== false) {
                $parsedName = $defaultName;
            }
            return [
                'name'  => $parsedName,
                'email' => trim($m[2]),
            ];
        }
        return ['name' => $defaultName, 'email' => $emailFrom];
    }

    // 3. Fallback to GMAIL_USER or MAIL_FROM
    $gmailUser = trim((string)(getenv('GMAIL_USER') ?: ($_ENV['GMAIL_USER'] ?? '')));
    if ($gmailUser !== '' && filter_var($gmailUser, FILTER_VALIDATE_EMAIL)) {
        return ['name' => $defaultName, 'email' => $gmailUser];
    }

    return [
        'name'  => $defaultName,
        'email' => $mailFrom !== '' ? $mailFrom : 'no-reply@busreservation.local',
    ];
}

/**
 * Sends transactional email via Brevo HTTPS REST API (Port 443).
 * Bypasses cloud outbound SMTP port blocking (25, 465, 587) on hosts like Render.
 *
 * @return array{ok: bool, error: string, messageId?: string}
 */
function brevo_send_http_mail(string $apiKey, string $to, string $subject, string $html_body): array {
    $sender = resolve_mail_sender();
    if ($sender['email'] === '' || !filter_var($sender['email'], FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Verified sender email is not configured (set MAIL_FROM or EMAIL_FROM).'];
    }

    $text_body = trim( html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</h2>'], "\n", $html_body)), ENT_QUOTES, 'UTF-8') );

    $payload = json_encode([
        'sender'      => ['name' => $sender['name'], 'email' => $sender['email']],
        'to'          => [['email' => $to]],
        'subject'     => $subject,
        'htmlContent' => $html_body,
        'textContent' => $text_body !== '' ? $text_body : $subject,
        'tags'        => ['bus-reservation', 'transactional'],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($payload === false) {
        return ['ok' => false, 'error' => 'Failed to encode Brevo JSON payload.'];
    }

    $url = 'https://api.brevo.com/v3/smtp/email';
    $maxRetries = 3;
    $delaysUs = [500000, 1500000]; // 0.5s, 1.5s
    $lastError = 'Unknown Brevo API error';

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $status = 0;
        $respBody = '';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $headers = [
                'Accept: application/json',
                'Content-Type: application/json',
                'api-key: ' . $apiKey,
            ];
            if (strtolower((string)getenv('BREVO_SANDBOX')) === 'true') {
                $headers[] = 'X-Sib-Sandbox: drop';
            }
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_IPRESOLVE      => defined('CURL_IPRESOLVE_V4') ? CURL_IPRESOLVE_V4 : 1,
            ]);
            // On local Windows dev environments where curl.cainfo may not be configured in php.ini
            if (PHP_OS_FAMILY === 'Windows' && !ini_get('curl.cainfo') && !ini_get('openssl.cafile')) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            }
            $raw = curl_exec($ch);
            $errno = curl_errno($ch);
            if ($raw === false && PHP_OS_FAMILY === 'Windows' && ($errno === 60 || $errno === 77)) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $raw = curl_exec($ch);
            }
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);

            if ($raw === false) {
                $lastError = 'Brevo HTTPS network error: ' . $curlErr;
                if ($attempt < $maxRetries) {
                    usleep($delaysUs[$attempt - 1] ?? 1000000);
                    continue;
                }
                return ['ok' => false, 'error' => $lastError];
            }
            $respBody = (string)$raw;
        } else {
            $headerStr = "Accept: application/json\r\n"
                . "Content-Type: application/json\r\n"
                . "api-key: {$apiKey}\r\n";
            if (strtolower((string)getenv('BREVO_SANDBOX')) === 'true') {
                $headerStr .= "X-Sib-Sandbox: drop\r\n";
            }
            $ctx = stream_context_create([
                'http' => [
                    'method'        => 'POST',
                    'header'        => $headerStr,
                    'content'       => $payload,
                    'timeout'       => 10,
                    'ignore_errors' => true,
                ],
                'ssl' => [
                    'verify_peer'      => PHP_OS_FAMILY !== 'Windows',
                    'verify_peer_name' => PHP_OS_FAMILY !== 'Windows',
                ],
            ]);
            $raw = @file_get_contents($url, false, $ctx);
            if (isset($http_response_header) && is_array($http_response_header)) {
                foreach ($http_response_header as $hdr) {
                    if (preg_match('#^HTTP/\S+\s+(\d{3})#', $hdr, $m)) {
                        $status = (int)$m[1];
                    }
                }
            }
            $respBody = (string)$raw;
        }

        if ($status >= 200 && $status < 300) {
            $decoded = json_decode($respBody, true);
            $msgId = is_array($decoded) ? (string)($decoded['messageId'] ?? '') : '';
            return ['ok' => true, 'error' => '', 'messageId' => $msgId];
        }

        $lastError = "Brevo HTTP {$status}: " . substr(trim($respBody), 0, 240);
        // Do not retry 4xx client/auth errors (except 429 rate limit)
        if ($status >= 400 && $status < 500 && $status !== 429) {
            break;
        }
        if ($attempt < $maxRetries) {
            usleep($delaysUs[$attempt - 1] ?? 1000000);
        }
    }

    return ['ok' => false, 'error' => $lastError];
}

/**
 * Sends an email using Brevo HTTPS API (preferred on cloud/Render) or direct RFC 5321 SMTP socket.
 *
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $html_body HTML message content
 * @return array ['ok' => bool, 'error' => string]
 */
function smtp_send_mail(string $to, string $subject, string $html_body): array {
    // 1. Prefer Brevo HTTPS REST API (Port 443) whenever BREVO_API_KEY (or xkeysib- key) is present
    $brevoKey = brevo_resolve_api_key();
    $brevoError = '';
    if ($brevoKey !== '') {
        $brevoRes = brevo_send_http_mail($brevoKey, $to, $subject, $html_body);
        if (!empty($brevoRes['ok'])) {
            return $brevoRes;
        }
        $brevoError = (string)($brevoRes['error'] ?? 'Brevo API error');
    }

    // 2. Fallback to standard SMTP socket (or Gmail SMTP if GMAIL_USER + GMAIL_APP_PASSWORD are set)
    $gmailUser = trim((string)(getenv('GMAIL_USER') ?: ($_ENV['GMAIL_USER'] ?? '')));
    $gmailPass = preg_replace('/\s+/', '', trim((string)(getenv('GMAIL_APP_PASSWORD') ?: ($_ENV['GMAIL_APP_PASSWORD'] ?? ''))));
    $hasGmailFallback = ($gmailUser !== '' && $gmailPass !== '' && !str_starts_with($gmailPass, 'PASTE_'));

    $mail_enabled = (
        getenv('MAIL_ENABLED') === '1' ||
        strtolower((string)getenv('MAIL_ENABLED')) === 'true' ||
        ($_ENV['MAIL_ENABLED'] ?? '') === '1' ||
        strtolower((string)($_ENV['MAIL_ENABLED'] ?? '')) === 'true' ||
        $hasGmailFallback
    );
    if (!$mail_enabled) {
        return ['ok' => false, 'error' => $brevoError !== '' ? $brevoError : 'Email delivery is not configured (set BREVO_API_KEY or MAIL_ENABLED=true).'];
    }

    $host = trim((string)(getenv('MAIL_HOST') ?: ($_ENV['MAIL_HOST'] ?? '')));
    $port = (int)(getenv('MAIL_PORT') ?: ($_ENV['MAIL_PORT'] ?? 587));
    $user = trim((string)(getenv('MAIL_USER') ?: ($_ENV['MAIL_USER'] ?? '')));
    $pass = (string)(getenv('MAIL_PASS') ?: ($_ENV['MAIL_PASS'] ?? ''));
    $encryption = strtolower((string)(getenv('MAIL_ENCRYPTION') ?: ($_ENV['MAIL_ENCRYPTION'] ?? 'tls')));

    if (($host === '' || $host === 'smtp.example.com') && $hasGmailFallback) {
        $host = 'smtp.gmail.com';
        $port = 587;
        $user = $gmailUser;
        $pass = $gmailPass;
        $encryption = 'tls';
    }

    $sender = resolve_mail_sender();
    $from_email = $sender['email'];
    $from_name  = $sender['name'];

    if ($host === '' || $host === 'smtp.example.com') {
        return ['ok' => false, 'error' => $brevoError !== '' ? $brevoError : 'SMTP host not configured (MAIL_HOST is empty).'];
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
 * Dry-run email transport check for diagnostics page (Issue 3).
 */
function smtp_diagnostics_check(): array {
    $brevoKey = brevo_resolve_api_key();
    if ($brevoKey !== '') {
        $sender = resolve_mail_sender();
        return [
            'ok'      => true,
            'status'  => 'ONLINE',
            'message' => 'Brevo HTTPS REST API configured (Port 443, sender: ' . $sender['email'] . ')',
        ];
    }

    $mail_enabled = (getenv('MAIL_ENABLED') === '1' || strtolower((string)getenv('MAIL_ENABLED')) === 'true');
    if (!$mail_enabled) {
        return ['ok' => false, 'status' => 'DISABLED', 'message' => 'Outbound email disabled (set BREVO_API_KEY or MAIL_ENABLED=true).'];
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
