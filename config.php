<?php
// Per-deployment overrides live in config.local.php (never committed or served).
// It may set $host, $dbname, $username, $password, $port and constants such as
// APP_ENV / BREVO_API_KEY / BREVO_SENDER_EMAIL / BREVO_SENDER_NAME.
if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

$host = $host ?? '127.0.0.1';
$dbname = $dbname ?? 'brightsmile_db';
$username = $username ?? 'brightsmile';
$password = $password ?? 'BrightSmile!2026';
$port = $port ?? 3306;

if (defined('APP_ENV') && APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
}

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $is_https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

const SESSION_IDLE_TIMEOUT     = 1800;   // 30 minutes without activity
const SESSION_ABSOLUTE_TIMEOUT = 28800;  // 8 hours since login
const DOWNPAYMENT_AMOUNT       = 500.00; // clinic downpayment per confirmed appointment
const CLINIC_CLOSED_WEEKDAY    = 0;      // Sunday

const SERVICE_PRICES = [
    'Comprehensive Dentistry'   => 3000.00,
    'Teeth Cleaning'            => 1500.00,
    'Checkup / Consultation'    => 800.00,
    'Tooth Extraction'          => 1200.00,
    'Orthodontics / Braces'     => 45000.00,
];

const CLINIC_DENTISTS = [
    'Dr. Michael Jordan',
    'Dr. Xhander Ford',
    'Dr. Mel Cabales',
    'Dr. Lorens Paraiso ',
];

const TIME_SLOTS = ['10:30 AM', '12:00 AM', '01:30 PM', '03:00 PM', '04:30 PM'];

/** Bookable services, keyed by name. */
function clinic_services(): array {
    return array_keys(SERVICE_PRICES);
}

function service_price(string $service): float {
    return (float) (SERVICE_PRICES[$service] ?? 0);
}

function e($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        die('Your session expired. Go back, refresh the page, and try again.');
    }
}

function session_end(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Drop the session and send the user back to the login page with a reason. */
function session_expire(string $reason): void {
    session_end();
    header('Location: login.php?' . $reason);
    exit;
}

function require_login(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }

    $now = time();
    if ($now - (int) ($_SESSION['last_activity'] ?? $now) > SESSION_IDLE_TIMEOUT) {
        session_expire('timeout=1');
    }
    if ($now - (int) ($_SESSION['login_at'] ?? $now) > SESSION_ABSOLUTE_TIMEOUT) {
        session_expire('expired=1');
    }

    $_SESSION['last_activity'] = $now;
}

function require_role(string ...$roles): void {
    require_login();
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        header('Location: index.php');
        exit;
    }
}

function home_for_role(string $role): string {
    if ($role === 'admin') {
        return 'admin-dashboard.php';
    }
    if ($role === 'receptionist') {
        return 'receptionist.php';
    }
    return 'index.php';
}

/** Staff roles sign in with password only; patients still get the email code. */
function skips_login_code(string $role): bool {
    return in_array($role, ['admin', 'receptionist'], true);
}

/** Start an authenticated session for a verified user and send them home. */
function sign_in_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['fullname'] = $user['fullname'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['login_at'] = time();
    $_SESSION['last_activity'] = time();
    unset($_SESSION['pending_login_id'], $_SESSION['pending_login_email'], $_SESSION['pending_login_at']);

    log_activity('signed_in', ucfirst((string) $user['role']) . ' signed in');

    header('Location: welcome.php');
    exit;
}

require_once __DIR__ . '/otp.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/activity.php';
require_once __DIR__ . '/notify.php';
