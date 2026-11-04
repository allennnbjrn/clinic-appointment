<?php
/**
 * Clinic activity trail.
 *
 * Logging is best-effort: a failure here must never break the request that
 * triggered it, so every write is wrapped and swallowed.
 */

function activity_table_ensure(): void {
    global $pdo;
    static $done = false;
    if ($done) {
        return;
    }

    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS activity_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                actor_name VARCHAR(100) NOT NULL DEFAULT '',
                actor_role VARCHAR(20) NOT NULL DEFAULT '',
                action VARCHAR(50) NOT NULL,
                details VARCHAR(500) NOT NULL DEFAULT '',
                ip VARCHAR(45) NOT NULL DEFAULT '',
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                KEY activity_created (created_at),
                KEY activity_action (action)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $done = true;
    } catch (Throwable $e) {
        // Audit storage is optional; ignore and keep the request alive.
    }
}

/** Record who did what, from where. Never throws. */
function log_activity(string $action, string $details = ''): void {
    global $pdo;
    activity_table_ensure();

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO activity_log (user_id, actor_name, actor_role, action, details, ip)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
            mb_substr((string) ($_SESSION['fullname'] ?? 'Guest'), 0, 100),
            mb_substr((string) ($_SESSION['role'] ?? ''), 0, 20),
            $action,
            mb_substr($details, 0, 500),
            substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        ]);
    } catch (Throwable $e) {
        // Ignore.
    }
}
