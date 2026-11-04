<?php
/**
 * Email delivery for BrightSmile Dental.
 *
 * Codes go out through the clinic Gmail account when its credentials are saved
 * in credentials.php. Until then, the local Mailpit inbox is used so the flow
 * works on this machine without showing codes on screen.
 */

const LOCAL_SMTP_HOST = '127.0.0.1';
const LOCAL_SMTP_PORT = 1025;
const LOCAL_MAIL_SENDER = 'no-reply@brightsmile.local';

/** Placeholder domains that cannot receive real mail (staff accounts). */
const PLACEHOLDER_MAIL_DOMAINS = ['brightsmile.com', 'brightsmile.local'];

function mail_is_placeholder(string $to): bool {
    $at = strrchr($to, '@');
    if ($at === false) {
        return false;
    }
    return in_array(strtolower(substr($at, 1)), PLACEHOLDER_MAIL_DOMAINS, true);
}

/** Sender + credentials, resolved once per request from credentials.php. */
function mail_config(): array {
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $config = [
        'provider'  => '',
        'user'      => '',
        'password'  => '',
        'from_name' => 'BrightSmile Dental',
    ];

    $file = __DIR__ . '/credentials.php';
    if (is_file($file)) {
        $saved = require $file;
        if (is_array($saved)) {
            if (isset($saved['mail_provider']) && is_string($saved['mail_provider'])) {
                $config['provider'] = strtolower(trim($saved['mail_provider']));
            }
            if (isset($saved['gmail_user']) && is_string($saved['gmail_user'])) {
                $config['user'] = trim($saved['gmail_user']);
            }
            if (isset($saved['gmail_app_password']) && is_string($saved['gmail_app_password'])) {
                // Google shows App Passwords as four groups of four; strip the spaces.
                $config['password'] = preg_replace('/\s+/', '', $saved['gmail_app_password']);
            }
            if (isset($saved['mail_from_name']) && is_string($saved['mail_from_name']) && trim($saved['mail_from_name']) !== '') {
                $config['from_name'] = trim($saved['mail_from_name']);
            }
        }
    }

    if ($config['provider'] === '') {
        $config['provider'] = ($config['user'] !== '' && $config['password'] !== '') ? 'smtp' : '';
    }

    return $config;
}

/** True when the local Mailpit inbox is accepting mail. */
function mail_local_available(): bool {
    $fp = @stream_socket_client('tcp://' . LOCAL_SMTP_HOST . ':' . LOCAL_SMTP_PORT, $errno, $errstr, 1);
    if (!$fp) {
        return false;
    }
    fclose($fp);
    return true;
}

/**
 * Send an email. Returns ['ok' => bool, 'error' => ?string].
 */
function mail_send(string $to, string $subject, string $body): array {
    $config = mail_config();

    // Hosts that block outbound SMTP (shared/free hosting) use the Brevo HTTPS API.
    if (defined('BREVO_API_KEY') && BREVO_API_KEY !== '' && !mail_is_placeholder($to)) {
        $error = '';
        if (mail_api_brevo($to, $subject, $body, $error)) {
            return ['ok' => true, 'error' => null];
        }
        return ['ok' => false, 'error' => $error !== '' ? $error : 'Could not send the email. Please try again.'];
    }

    // Placeholder staff domains have no real mailbox, so Gmail would bounce
    // the code and lock the account out. Deliver those locally instead.
    $use_gmail = $config['provider'] === 'smtp'
        && $config['user'] !== ''
        && $config['password'] !== ''
        && !mail_is_placeholder($to);

    if ($use_gmail) {
        $error = '';
        if (mail_smtp_gmail($to, $subject, $body, $error)) {
            return ['ok' => true, 'error' => null];
        }
        return ['ok' => false, 'error' => $error !== '' ? $error : 'Could not send the email. Please try again.'];
    }

    if (mail_local_available()) {
        $error = '';
        if (mail_smtp_local($to, $subject, $body, $error)) {
            return ['ok' => true, 'error' => null];
        }
        return ['ok' => false, 'error' => $error !== '' ? $error : 'Could not send the email. Please try again.'];
    }

    return ['ok' => false, 'error' => 'Email delivery is not set up yet. Please contact the clinic administrator.'];
}

/** Gmail SMTP (smtp.gmail.com:587, STARTTLS + AUTH LOGIN). */
function mail_smtp_gmail(string $to, string $subject, string $body, string &$error): bool {
    $config = mail_config();
    return mail_smtp_deliver(
        'smtp.gmail.com', 587, true,
        $config['user'], $config['password'],
        $config['user'], $config['from_name'],
        $to, $subject, $body, $error
    );
}

/** Local Mailpit SMTP (no TLS, no auth). */
function mail_smtp_local(string $to, string $subject, string $body, string &$error): bool {
    $config = mail_config();
    return mail_smtp_deliver(
        LOCAL_SMTP_HOST, LOCAL_SMTP_PORT, false,
        '', '',
        LOCAL_MAIL_SENDER, $config['from_name'],
        $to, $subject, $body, $error
    );
}

/** Brevo transactional email over HTTPS (works where SMTP ports are blocked). */
function mail_api_brevo(string $to, string $subject, string $body, string &$error): bool {
    $sender_email = defined('BREVO_SENDER_EMAIL') ? (string) BREVO_SENDER_EMAIL : '';
    if ($sender_email === '') {
        $error = 'The email sender address is not configured.';
        return false;
    }
    $sender_name = defined('BREVO_SENDER_NAME') && BREVO_SENDER_NAME !== '' ? BREVO_SENDER_NAME : 'BrightSmile Dental';

    $payload = json_encode([
        'sender'      => ['name' => $sender_name, 'email' => $sender_email],
        'to'          => [['email' => $to]],
        'subject'     => $subject,
        'textContent' => $body,
    ]);
    if ($payload === false) {
        $error = 'Could not prepare the email.';
        return false;
    }

    $url = 'https://api.brevo.com/v3/smtp/email';
    $headers = [
        'accept: application/json',
        'content-type: application/json',
        'api-key: ' . BREVO_API_KEY,
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $transport_error = curl_error($ch);
        curl_close($ch);
        if ($response === false) {
            $error = 'Could not reach the email service.';
            return false;
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => implode("\r\n", $headers),
                'content'       => $payload,
                'timeout'       => 15,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }
        if ($response === false) {
            $error = 'Could not reach the email service.';
            return false;
        }
    }

    if ($status < 200 || $status >= 300) {
        $detail = json_decode($response, true);
        $error = (is_array($detail) && !empty($detail['message']))
            ? 'The email service rejected the message: ' . $detail['message']
            : 'The email service rejected the message.';
        return false;
    }
    return true;
}

/** Minimal SMTP client used for both Gmail and the local inbox. */
function mail_smtp_deliver(
    string $host, int $port, bool $starttls,
    string $user, string $password,
    string $envelope_from, string $from_name,
    string $to, string $subject, string $body,
    string &$error
): bool {
    $fp = @stream_socket_client('tcp://' . $host . ':' . $port, $errno, $errstr, 15);
    if (!$fp) {
        $error = 'Could not connect to the mail server.';
        return false;
    }
    stream_set_timeout($fp, 15);

    $read = static function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $cmd = static function (string $command) use ($fp, $read): string {
        fwrite($fp, $command . "\r\n");
        return $read();
    };
    $fail = static function (string $message) use ($fp, &$error): bool {
        @fwrite($fp, "QUIT\r\n");
        fclose($fp);
        $error = $message;
        return false;
    };

    $read(); // welcome banner

    if (strpos($cmd('EHLO brightsmile.local'), '250') !== 0) {
        return $fail('The mail server refused the connection.');
    }
    if ($starttls) {
        if (strpos($cmd('STARTTLS'), '220') !== 0) {
            return $fail('The mail server refused a secure connection.');
        }
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            return $fail('Could not establish a secure connection to the mail server.');
        }
        if (strpos($cmd('EHLO brightsmile.local'), '250') !== 0) {
            return $fail('The mail server refused the connection.');
        }
    }
    if ($user !== '') {
        if (strpos($cmd('AUTH LOGIN'), '334') !== 0) {
            return $fail('The mail server rejected the login.');
        }
        if (strpos($cmd(base64_encode($user)), '334') !== 0) {
            return $fail('The mail server rejected the login.');
        }
        if (strpos($cmd(base64_encode($password)), '235') !== 0) {
            return $fail('The clinic email account could not sign in. Please try again later.');
        }
    }
    if (strpos($cmd('MAIL FROM:<' . $envelope_from . '>'), '250') !== 0) {
        return $fail('The mail server rejected the sender address.');
    }
    if (strpos($cmd('RCPT TO:<' . $to . '>'), '250') !== 0) {
        return $fail('The mail server rejected the recipient address.');
    }
    if (strpos($cmd('DATA'), '354') !== 0) {
        return $fail('The mail server refused the message.');
    }

    $headers = [
        'From: ' . $from_name . ' <' . $envelope_from . '>',
        'To: <' . $to . '>',
        'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
        'Date: ' . date('r'),
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];
    $data = implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n", "\r\n", $body);
    $data = preg_replace('/^\./m', '..', $data); // dot-stuffing

    fwrite($fp, $data . "\r\n.\r\n");
    if (strpos($read(), '250') !== 0) {
        return $fail('The mail server refused the message.');
    }

    $cmd('QUIT');
    fclose($fp);
    return true;
}
