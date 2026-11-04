<?php
require 'config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . home_for_role($_SESSION['role'] ?? 'patient'));
    exit;
}

if (isset($_GET['cancel'])) {
    unset($_SESSION['pending_login_id'], $_SESSION['pending_login_email'], $_SESSION['pending_login_at']);
    header('Location: login.php');
    exit;
}

$userId = (int) ($_SESSION['pending_login_id'] ?? 0);
if ($userId <= 0) {
    header('Location: login.php');
    exit;
}

if (time() - (int) ($_SESSION['pending_login_at'] ?? 0) > 600) {
    unset($_SESSION['pending_login_id'], $_SESSION['pending_login_email'], $_SESSION['pending_login_at']);
    header('Location: login.php?otp_expired=1');
    exit;
}

$stmt = $pdo->prepare('SELECT id, fullname, email, password, role FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    unset($_SESSION['pending_login_id'], $_SESSION['pending_login_email'], $_SESSION['pending_login_at']);
    header('Location: login.php');
    exit;
}

// Staff accounts never need an email code.
if (skips_login_code($user['role'])) {
    sign_in_user($user);
}

$email = $_SESSION['pending_login_email'] ?? $user['email'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (($_POST['action'] ?? '') === 'resend') {
        $sent = otp_email_issue($email, 'login');
        if ($sent['ok']) {
            $success = 'A new code was sent to ' . $email . '.';
        } else {
            $error = $sent['error'];
        }
    } else {
        $code = trim((string) ($_POST['code'] ?? ''));

        if (!preg_match('/^\d{6}$/', $code)) {
            $error = 'Enter the 6-digit code from the email.';
        } else {
            $check = otp_email_verify($email, $code, 'login');
            if ($check['ok']) {
                sign_in_user($user);
            }
            $error = $check['error'];
        }
    }
}

$wait = otp_email_wait($email, 'login');

$page_title = 'Verification Code - BrightSmile Dental';
require 'header.php';
?>

<div class="auth-wrap">
    <div class="auth-card">
        <img src="logo-icon.svg" alt="BrightSmile Dental" class="auth-logo">
        <h1>Check Your Email</h1>
        <p class="auth-sub">Step 2 of 2 — enter the 6-digit code we sent to <strong><?= e($email) ?></strong>.</p>

        <div class="steps">
            <div class="step done"><span>1</span>Sign In</div>
            <div class="step active"><span>2</span>Verification Code</div>
        </div>

        <?php if ($success !== ''): ?>
            <div class="alert alert-success"><?= e($success) ?></div>
        <?php elseif ($error !== ''): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrf_field() ?>
            <div class="field">
                <label for="code">Verification Code</label>
                <input id="code" type="text" name="code" class="input" inputmode="numeric" pattern="\d{6}" maxlength="6" placeholder="000000" autocomplete="one-time-code" required>
                <p class="field-hint">Codes expire after 5 minutes.</p>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Verify &amp; Log In</button>
        </form>

        <form method="POST" class="auth-alt">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="resend">
            <button type="submit" class="btn btn-sm btn-neutral" id="resendBtn" <?= $wait > 0 ? 'disabled' : '' ?>>Resend code</button>
            <span class="cell-muted" id="resendTimer" <?= $wait > 0 ? '' : 'style="display:none"' ?>>Resend available in <strong><?= (int) $wait ?></strong>s</span>
        </form>

        <p class="auth-alt"><a href="login-otp.php?cancel=1">Cancel and start over</a></p>
    </div>
</div>

<script>
(function () {
    var btn = document.getElementById('resendBtn');
    var timer = document.getElementById('resendTimer');
    if (!btn || !timer || btn.disabled !== true) return;

    var left = parseInt(timer.textContent.replace(/\D/g, ''), 10) || 0;
    var iv = setInterval(function () {
        left--;
        if (left <= 0) {
            clearInterval(iv);
            btn.disabled = false;
            timer.style.display = 'none';
        } else {
            timer.querySelector('strong').textContent = left;
        }
    }, 1000);
})();
</script>

<?php require 'footer.php'; ?>
