<?php
declare(strict_types=1);

/** True once host/username/password are filled in and mail isn't explicitly disabled. */
function mail_is_configured(): bool
{
    return (bool)config('mail.host') && (bool)config('mail.username') && (bool)config('mail.password')
        && config('mail.enabled') !== false;
}

/**
 * Minimal SMTP client with no external dependencies - talks directly to the mail
 * server over a socket. Supports implicit TLS (typically port 465) and STARTTLS
 * (typically port 587) with AUTH LOGIN, which covers Gmail and Outlook/Office365.
 *
 * @return array{ok:bool,error:?string}
 */
function send_mail(string $toEmail, string $toName, string $subject, string $body): array
{
    if (!mail_is_configured()) {
        return ['ok' => false, 'error' => 'Email is not configured yet (see config/config.php).'];
    }

    $host = (string)config('mail.host');
    $port = (int)config('mail.port');
    $encryption = (string)(config('mail.encryption') ?? 'tls'); // 'tls' (STARTTLS) or 'ssl' (implicit)
    $username = (string)config('mail.username');
    $password = (string)config('mail.password');
    $fromEmail = (string)(config('mail.from_email') ?? $username);
    $fromName = (string)(config('mail.from_name') ?? config('app.name'));
    $heloHost = (string)(parse_url((string)config('app.base_url'), PHP_URL_HOST) ?: 'localhost');

    $transport = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $fp = @stream_socket_client($transport, $errno, $errstr, 15);
    if (!$fp) {
        return ['ok' => false, 'error' => "Could not connect to $host:$port ($errstr)"];
    }
    stream_set_timeout($fp, 15);

    $read = static function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            // Multi-line SMTP replies use "250-text"; the final line uses "250 text".
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        return $data;
    };
    $write = static function (string $cmd) use ($fp): void {
        fwrite($fp, $cmd . "\r\n");
    };
    $ok = static fn(string $response, string $code): bool => strncmp($response, $code, strlen($code)) === 0;

    $banner = $read();
    if (!$ok($banner, '220')) {
        fclose($fp);
        return ['ok' => false, 'error' => 'Server did not greet: ' . trim($banner)];
    }

    $write("EHLO $heloHost");
    $resp = $read();
    if (!$ok($resp, '250')) {
        fclose($fp);
        return ['ok' => false, 'error' => 'EHLO failed: ' . trim($resp)];
    }

    if ($encryption === 'tls') {
        $write('STARTTLS');
        $resp = $read();
        if (!$ok($resp, '220')) {
            fclose($fp);
            return ['ok' => false, 'error' => 'STARTTLS failed: ' . trim($resp)];
        }
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($fp);
            return ['ok' => false, 'error' => 'Could not negotiate TLS with the mail server.'];
        }
        // EHLO must be resent after the TLS handshake per RFC 3207.
        $write("EHLO $heloHost");
        $resp = $read();
        if (!$ok($resp, '250')) {
            fclose($fp);
            return ['ok' => false, 'error' => 'EHLO after STARTTLS failed: ' . trim($resp)];
        }
    }

    $write('AUTH LOGIN');
    $resp = $read();
    if (!$ok($resp, '334')) {
        fclose($fp);
        return ['ok' => false, 'error' => 'AUTH LOGIN not accepted: ' . trim($resp)];
    }
    $write(base64_encode($username));
    $resp = $read();
    if (!$ok($resp, '334')) {
        fclose($fp);
        return ['ok' => false, 'error' => 'Username rejected: ' . trim($resp)];
    }
    $write(base64_encode($password));
    $resp = $read();
    if (!$ok($resp, '235')) {
        fclose($fp);
        return ['ok' => false, 'error' => 'Login failed - check mail.username/mail.password in config.php: ' . trim($resp)];
    }

    $write("MAIL FROM:<$fromEmail>");
    $resp = $read();
    if (!$ok($resp, '250')) {
        fclose($fp);
        return ['ok' => false, 'error' => 'Sender rejected: ' . trim($resp)];
    }

    $write("RCPT TO:<$toEmail>");
    $resp = $read();
    if (!$ok($resp, '250') && !$ok($resp, '251')) {
        fclose($fp);
        return ['ok' => false, 'error' => 'Recipient rejected: ' . trim($resp)];
    }

    $write('DATA');
    $resp = $read();
    if (!$ok($resp, '354')) {
        fclose($fp);
        return ['ok' => false, 'error' => 'DATA not accepted: ' . trim($resp)];
    }

    $headers = [
        'From: ' . mail_encode_header($fromName) . " <$fromEmail>",
        'To: ' . mail_encode_header($toName) . " <$toEmail>",
        'Subject: ' . mail_encode_header($subject),
        'Date: ' . date('r'),
        'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $heloHost . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];
    // Lines starting with a lone "." must be dot-stuffed per the SMTP DATA rules.
    $escapedBody = preg_replace('/^\./m', '..', $body);

    $write(implode("\r\n", $headers) . "\r\n\r\n" . $escapedBody . "\r\n.");
    $resp = $read();
    if (!$ok($resp, '250')) {
        fclose($fp);
        return ['ok' => false, 'error' => 'Message rejected: ' . trim($resp)];
    }

    $write('QUIT');
    fclose($fp);

    return ['ok' => true, 'error' => null];
}

function mail_encode_header(string $value): string
{
    if (preg_match('/^[\x20-\x7E]*$/', $value)) {
        return $value; // plain ASCII - no encoding needed
    }
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}
