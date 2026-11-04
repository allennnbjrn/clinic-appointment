<?php
/**
 * Login throttling and password policy.
 *
 * A password must be 8-20 characters and contain at least one letter,
 * one number, and one special character.
 */

const LOGIN_MAX_FAILURES = 5;    // failed passwords allowed per window
const LOGIN_BLOCK_WINDOW = 900;  // window and block length, seconds (15 min)

function login_attempts_ensure_table(): void {
    global $pdo;
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(100) NOT NULL,
            ip VARCHAR(45) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            KEY login_attempt_lookup (email, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

/** Minutes left in a block for this email, or 0 when not blocked. */
function login_attempts_blocked(string $email): int {
    global $pdo;
    login_attempts_ensure_table();

    $cutoff = date('Y-m-d H:i:s', time() - LOGIN_BLOCK_WINDOW);
    $count = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND created_at > ?');
    $count->execute([$email, $cutoff]);
    if ((int) $count->fetchColumn() < LOGIN_MAX_FAILURES) {
        return 0;
    }

    $oldest = $pdo->prepare('SELECT created_at FROM login_attempts WHERE email = ? AND created_at > ? ORDER BY id ASC LIMIT 1');
    $oldest->execute([$email, $cutoff]);
    $since = strtotime((string) $oldest->fetchColumn());
    if ($since === false) {
        return 1;
    }

    return max(1, (int) ceil(($since + LOGIN_BLOCK_WINDOW - time()) / 60));
}

function login_attempts_record(string $email): void {
    global $pdo;
    login_attempts_ensure_table();

    $pdo->prepare('DELETE FROM login_attempts WHERE email = ? AND created_at <= ?')
        ->execute([$email, date('Y-m-d H:i:s', time() - LOGIN_BLOCK_WINDOW)]);

    $pdo->prepare('INSERT INTO login_attempts (email, ip, created_at) VALUES (?, ?, ?)')
        ->execute([
            $email,
            substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            date('Y-m-d H:i:s'),
        ]);
}

function login_attempts_clear(string $email): void {
    global $pdo;
    login_attempts_ensure_table();
    $pdo->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([$email]);
}

/** Human-readable length violation for an email/username, or null when acceptable. */
function email_length_error(string $email): ?string {
    $len = mb_strlen($email);
    if ($len < 10 || $len > 30) {
        return 'Email address must be between 10 and 30 characters long.';
    }
    return null;
}

/** Human-readable policy violation, or null when the password is acceptable. */
function password_policy_error(string $password): ?string {
    if (strlen($password) < 8 || strlen($password) > 20) {
        return 'Password must be between 8 and 20 characters long.';
    }
    if (!preg_match('/[A-Za-z]/', $password)) {
        return 'Password must include at least one letter.';
    }
    if (!preg_match('/\d/', $password)) {
        return 'Password must include at least one number.';
    }
    if (!preg_match('/[^A-Za-z0-9\s]/', $password)) {
        return 'Password must include at least one special character (for example # or @).';
    }
    return null;
}
