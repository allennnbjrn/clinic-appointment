<?php
require 'config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . home_for_role($_SESSION['role'] ?? 'patient'));
    exit;
}

if (isset($_GET['cancel'])) {
    unset($_SESSION['pending_reg'], $_SESSION['pending_reg_at']);
    header('Location: register.php');
    exit;
}

$pending = $_SESSION['pending_reg'] ?? null;
if (!is_array($pending) || empty($pending['email'])) {
    header('Location: register.php');
    exit;
}

if (time() - (int) ($_SESSION['pending_reg_at'] ?? 0) > 1800) {
    unset($_SESSION['pending_reg'], $_SESSION['pending_reg_at']);
    header('Location: register.php?expired=1');
    exit;
}

$email = $pending['email'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (($_POST['action'] ?? '') === 'resend') {
        $sent = otp_email_issue($email, 'register', false);
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
            $check = otp_email_verify($email, $code, 'register');
            if (!$check['ok']) {
                $error = $check['error'];
            } else {
                $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);

                if ($stmt->fetch()) {
                    unset($_SESSION['pending_reg'], $_SESSION['pending_reg_at']);
                    $error = 'Email is already registered!';
                } else {
                    $insert = $pdo->prepare(
                        'INSERT INTO users (fullname, first_name, middle_name, last_name, email, password, phone, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $insert->execute([
                        $pending['fullname'],
                        $pending['first_name'],
                        $pending['middle_name'],
                        $pending['last_name'],
                        $email,
                        $pending['password_hash'],
                        $pending['phone'],
                        'patient',
                    ]);

                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int) $pdo->lastInsertId();
                    $_SESSION['fullname'] = $pending['fullname'];
                    $_SESSION['role'] = 'patient';
                    $_SESSION['login_at'] = time();
                    $_SESSION['last_activity'] = time();
                    unset($_SESSION['pending_reg'], $_SESSION['pending_reg_at']);

                    header('Location: ' . home_for_role('patient'));
                    exit;
                }
            }
        }
    }
}

$wait = otp_email_wait($email, 'register');

$page_title = 'Verify Your Gmail - BrightSmile Dental';
require 'header.php';
?>

<div class="auth-wrap">
    <div class="auth-card">
        <img src="logo-icon.svg" alt="BrightSmile Dental" class="auth-logo">
        <h1>Verify Your Gmail</h1>
        <p class="auth-sub">Step 2 of 2 — enter the 6-digit code we sent to <strong><?= e($email) ?></strong>.</p>

        <div class="steps">
            <div class="step done"><span>1</span>Your Details</div>
            <div class="step active"><span>2</span>Verify Gmail</div>
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
            <button type="submit" class="btn btn-primary btn-block">Verify &amp; Create Account</button>
        </form>

        <form method="POST" class="auth-alt">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="resend">
            <button type="submit" class="btn btn-sm btn-neutral" id="resendBtn" <?= $wait > 0 ? 'disabled' : '' ?>>Resend code</button>
            <span class="cell-muted" id="resendTimer" <?= $wait > 0 ? '' : 'style="display:none"' ?>>Resend available in <strong><?= (int) $wait ?></strong>s</span>
        </form>

        <p class="auth-alt"><a href="register-verify.php?cancel=1">Cancel registration</a></p>
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
