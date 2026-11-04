<?php
/**
 * OTP (one-time password) handling: issue 6-digit codes over SMS and verify them.
 *
 * Codes are stored hashed, expire in 5 minutes, allow 5 wrong attempts,
 * can be re-sent once per minute, and max 5 sends per phone per hour.
 */

require_once __DIR__ . '/sms.php';
require_once __DIR__ . '/mailer.php';

const OTP_TTL             = 300; // seconds until a code expires
const OTP_MAX_ATTEMPTS    = 5;   // wrong entries allowed per code
const OTP_RESEND_COOLDOWN = 60;  // seconds between sends
const OTP_HOURLY_CAP      = 5;   // max sends per phone per rolling hour

function otp_ensure_table(): void {
    global $pdo;
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS otps (
            id INT AUTO_INCREMENT PRIMARY KEY,
            phone VARCHAR(30) NOT NULL,
            purpose VARCHAR(30) NOT NULL DEFAULT 'verify',
            code_hash VARCHAR(255) NOT NULL,
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            KEY otp_phone (phone, purpose)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

/** Storage key so '0917 123 4567', '09171234567' and '+639171234567' are one number. */
function otp_phone_key(string $phone): string {
    $digits = preg_replace('/\D+/', '', $phone);
    if (strpos($digits, '0') === 0 && strlen($digits) === 11) {
        $digits = '63' . substr($digits, 1);
    }
    return $digits;
}

/**
 * Generate and send an OTP.
 * Returns ['ok'=>bool, 'expires_in'=>int, 'error'=>?string].
 * The code is only ever delivered by SMS — never returned to the page.
 */
function otp_issue(string $phone, string $purpose = 'verify'): array {
    global $pdo;
    otp_ensure_table();

    $key = otp_phone_key($phone);
    if (strlen($key) < 10) {
        return ['ok' => false, 'expires_in' => 0, 'error' => 'That phone number does not look valid.'];
    }

    $owner = $pdo->prepare('SELECT id FROM users WHERE phone IN (?, ?) LIMIT 1');
    $owner->execute(['+' . $key, '0' . substr($key, 2)]);
    if (!$owner->fetchColumn()) {
        return ['ok' => false, 'expires_in' => 0, 'error' => 'That mobile number is not registered.'];
    }

    $last = $pdo->prepare(
        'SELECT created_at FROM otps WHERE phone = ? AND purpose = ? ORDER BY id DESC LIMIT 1'
    );
    $last->execute([$key, $purpose]);
    if ($row = $last->fetch()) {
        $wait = OTP_RESEND_COOLDOWN - (time() - strtotime($row['created_at']));
        if ($wait > 0) {
            return ['ok' => false, 'expires_in' => 0, 'error' => 'Please wait ' . $wait . ' second' . ($wait === 1 ? '' : 's') . ' before requesting a new code.'];
        }
    }

    $hour = $pdo->prepare(
        'SELECT COUNT(*) FROM otps WHERE phone = ? AND purpose = ? AND created_at > ?'
    );
    $hour->execute([$key, $purpose, date('Y-m-d H:i:s', time() - 3600)]);
    if ((int) $hour->fetchColumn() >= OTP_HOURLY_CAP) {
        return ['ok' => false, 'expires_in' => 0, 'error' => 'Too many codes requested. Please try again later.'];
    }

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $now  = time();

    $stmt = $pdo->prepare(
        'INSERT INTO otps (phone, purpose, code_hash, expires_at, created_at) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $key,
        $purpose,
        password_hash($code, PASSWORD_DEFAULT),
        date('Y-m-d H:i:s', $now + OTP_TTL),
        date('Y-m-d H:i:s', $now),
    ]);

    $send = sms_send(
        $phone,
        'Your BrightSmile Dental verification code is: ' . $code . '. It expires in 5 minutes. Do not share this code.'
    );

    if (!$send['ok']) {
        $pdo->prepare('DELETE FROM otps WHERE id = ?')
            ->execute([(int) $pdo->lastInsertId()]);
        return ['ok' => false, 'expires_in' => 0, 'error' => $send['error'] ?? 'Could not send the SMS. Please try again.'];
    }

    return [
        'ok'         => true,
        'expires_in' => OTP_TTL,
        'error'      => null,
    ];
}

/**
 * Check a submitted code. Returns ['ok'=>bool, 'error'=>?string].
 */
function otp_verify(string $phone, string $code, string $purpose = 'verify'): array {
    global $pdo;
    otp_ensure_table();

    $key  = otp_phone_key($phone);
    $code = preg_replace('/\D+/', '', (string) $code);

    $stmt = $pdo->prepare(
        'SELECT * FROM otps WHERE phone = ? AND purpose = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$key, $purpose]);
    $otp = $stmt->fetch();

    if (!$otp) {
        return ['ok' => false, 'error' => 'No active code found. Please request a new one.'];
    }
    if (strtotime($otp['expires_at']) < time()) {
        return ['ok' => false, 'error' => 'That code has expired. Please request a new one.'];
    }
    if ((int) $otp['attempts'] >= OTP_MAX_ATTEMPTS) {
        return ['ok' => false, 'error' => 'Too many wrong attempts. Please request a new code.'];
    }
    if ($code === '' || !password_verify($code, $otp['code_hash'])) {
        $pdo->prepare('UPDATE otps SET attempts = attempts + 1 WHERE id = ?')
            ->execute([(int) $otp['id']]);
        return ['ok' => false, 'error' => 'That code is incorrect.'];
    }

    $pdo->prepare('UPDATE otps SET used_at = NOW() WHERE id = ?')
        ->execute([(int) $otp['id']]);

    return ['ok' => true, 'error' => null];
}

/** Seconds left before another code can be sent (0 = can send now). */
function otp_resend_wait(string $phone, string $purpose = 'verify'): int {
    global $pdo;
    otp_ensure_table();

    $stmt = $pdo->prepare(
        'SELECT created_at FROM otps WHERE phone = ? AND purpose = ? ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([otp_phone_key($phone), $purpose]);
    if ($row = $stmt->fetch()) {
        return max(0, OTP_RESEND_COOLDOWN - (time() - strtotime($row['created_at'])));
    }
    return 0;
}

/* ---------------------------------------------------------------------------
 * Email OTPs (Gmail) — same rules as SMS codes, stored in their own table.
 * ------------------------------------------------------------------------ */

function otp_email_ensure_table(): void {
    global $pdo;
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS email_otps (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(100) NOT NULL,
            purpose VARCHAR(30) NOT NULL DEFAULT 'reset',
            code_hash VARCHAR(255) NOT NULL,
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            KEY email_otp_lookup (email, purpose)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function otp_email_key(string $email): string {
    return strtolower(preg_replace('/\s+/', '', $email));
}

/**
 * Generate and email an OTP. Same return shape as otp_issue().
 * The code is only ever delivered by email — never returned to the page.
 * Pass $must_exist = false for pre-registration verification, where the
 * address is not in users yet.
 */
function otp_email_issue(string $email, string $purpose = 'reset', bool $must_exist = true): array {
    global $pdo;
    otp_email_ensure_table();

    $key = otp_email_key($email);
    if (!filter_var($key, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'expires_in' => 0, 'error' => 'That email address does not look valid.'];
    }

    if ($must_exist) {
        $owner = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = ? LIMIT 1');
        $owner->execute([$key]);
        if (!$owner->fetchColumn()) {
            return ['ok' => false, 'expires_in' => 0, 'error' => 'That email address is not registered.'];
        }
    }

    $last = $pdo->prepare(
        'SELECT created_at FROM email_otps WHERE email = ? AND purpose = ? ORDER BY id DESC LIMIT 1'
    );
    $last->execute([$key, $purpose]);
    if ($row = $last->fetch()) {
        $wait = OTP_RESEND_COOLDOWN - (time() - strtotime($row['created_at']));
        if ($wait > 0) {
            return ['ok' => false, 'expires_in' => 0, 'error' => 'Please wait ' . $wait . ' second' . ($wait === 1 ? '' : 's') . ' before requesting a new code.'];
        }
    }

    $hour = $pdo->prepare(
        'SELECT COUNT(*) FROM email_otps WHERE email = ? AND purpose = ? AND created_at > ?'
    );
    $hour->execute([$key, $purpose, date('Y-m-d H:i:s', time() - 3600)]);
    if ((int) $hour->fetchColumn() >= OTP_HOURLY_CAP) {
        return ['ok' => false, 'expires_in' => 0, 'error' => 'Too many codes requested. Please try again later.'];
    }

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $now  = time();

    $stmt = $pdo->prepare(
        'INSERT INTO email_otps (email, purpose, code_hash, expires_at, created_at) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $key,
        $purpose,
        password_hash($code, PASSWORD_DEFAULT),
        date('Y-m-d H:i:s', $now + OTP_TTL),
        date('Y-m-d H:i:s', $now),
    ]);
    $id = (int) $pdo->lastInsertId();

    $send = mail_send(
        $key,
        'Your BrightSmile Dental verification code',
        "Your BrightSmile Dental verification code is  Mel Pogi Sent a Code: {$code}\n\n"
        . "It expires in 5 minutes. Do not share this code.\n\n"
        . "If you did not request this, you can safely ignore this email."
    );

    if (!$send['ok']) {
        $pdo->prepare('DELETE FROM email_otps WHERE id = ?')->execute([$id]);
        return ['ok' => false, 'expires_in' => 0, 'error' => $send['error'] ?? 'Could not send the email. Please try again.'];
    }

    return [
        'ok'         => true,
        'expires_in' => OTP_TTL,
        'error'      => null,
    ];
}

/** Check a submitted email code. Same return shape as otp_verify(). */
function otp_email_verify(string $email, string $code, string $purpose = 'reset'): array {
    global $pdo;
    otp_email_ensure_table();

    $key  = otp_email_key($email);
    $code = preg_replace('/\D+/', '', (string) $code);

    $stmt = $pdo->prepare(
        'SELECT * FROM email_otps WHERE email = ? AND purpose = ? AND used_at IS NULL ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$key, $purpose]);
    $otp = $stmt->fetch();

    if (!$otp) {
        return ['ok' => false, 'error' => 'No active code found. Please request a new one.'];
    }
    if (strtotime($otp['expires_at']) < time()) {
        return ['ok' => false, 'error' => 'That code has expired. Please request a new one.'];
    }
    if ((int) $otp['attempts'] >= OTP_MAX_ATTEMPTS) {
        return ['ok' => false, 'error' => 'Too many wrong attempts. Please request a new code.'];
    }
    if ($code === '' || !password_verify($code, $otp['code_hash'])) {
        $pdo->prepare('UPDATE email_otps SET attempts = attempts + 1 WHERE id = ?')
            ->execute([(int) $otp['id']]);
        return ['ok' => false, 'error' => 'That code is incorrect.'];
    }

    $pdo->prepare('UPDATE email_otps SET used_at = NOW() WHERE id = ?')
        ->execute([(int) $otp['id']]);

    return ['ok' => true, 'error' => null];
}

/** Seconds left before another email code can be sent (0 = can send now). */
function otp_email_wait(string $email, string $purpose = 'reset'): int {
    global $pdo;
    otp_email_ensure_table();

    $stmt = $pdo->prepare(
        'SELECT created_at FROM email_otps WHERE email = ? AND purpose = ? ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([otp_email_key($email), $purpose]);
    if ($row = $stmt->fetch()) {
        return max(0, OTP_RESEND_COOLDOWN - (time() - strtotime($row['created_at'])));
    }
    return 0;
}
