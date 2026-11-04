<?php
require 'config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . home_for_role($_SESSION['role'] ?? 'patient'));
    exit;
}

if (isset($_GET['restart'])) {
    unset($_SESSION['reset_email'], $_SESSION['reset_verified'], $_SESSION['reset_at']);
}

$resetEmail = (string) ($_SESSION['reset_email'] ?? '');
$verified = !empty($_SESSION['reset_verified']);
$error = '';
$success = '';
$email_value = $resetEmail;

if ($resetEmail !== '' && time() - (int) ($_SESSION['reset_at'] ?? 0) > 1800) {
    unset($_SESSION['reset_email'], $_SESSION['reset_verified'], $_SESSION['reset_at']);
    $resetEmail = '';
    $verified = false;
    $email_value = '';
    $error = 'Your reset session expired. Please start again.';
}

$step = 'identify';
if ($resetEmail !== '' && $verified) {
    $step = 'newpass';
} elseif ($resetEmail !== '') {
    $step = 'code';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'send_email') {
        $email_value = strtolower(preg_replace('/\s+/', '', (string) ($_POST['email'] ?? '')));
        $resetEmail = '';

        if (($email_err = email_length_error($email_value)) !== null) {
            $error = $email_err;
        } elseif (!filter_var($email_value, FILTER_VALIDATE_EMAIL) || !str_ends_with($email_value, '@gmail.com')) {
            $error = 'Enter a valid Gmail address (e.g., yourname@gmail.com).';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = ? LIMIT 1');
            $stmt->execute([$email_value]);

            if (!$stmt->fetch()) {
                $error = 'No account was found with that Gmail address.';
            } else {
                $sent = otp_email_issue($email_value, 'reset');
                if ($sent['ok']) {
                    $_SESSION['reset_email'] = $email_value;
                    $_SESSION['reset_verified'] = 0;
                    $_SESSION['reset_at'] = time();
                    $resetEmail = $email_value;
                    $verified = false;
                    $step = 'code';
                    $success = 'We sent a 6-digit code to ' . $email_value . '.';
                } else {
                    $error = $sent['error'];
                }
            }
        }
    } elseif ($action === 'resend' && $resetEmail !== '' && !$verified) {
        $sent = otp_email_issue($resetEmail, 'reset');
        if ($sent['ok']) {
            $success = 'A new code was sent to ' . $resetEmail . '.';
        } else {
            $error = $sent['error'];
        }
    } elseif ($action === 'verify' && $resetEmail !== '') {
        $code = trim((string) ($_POST['code'] ?? ''));

        if (!preg_match('/^\d{6}$/', $code)) {
            $error = 'Enter the 6-digit code from the email.';
        } else {
            $check = otp_email_verify($resetEmail, $code, 'reset');

            if ($check['ok']) {
                $_SESSION['reset_verified'] = 1;
                $_SESSION['reset_at'] = time();
                $verified = true;
                $step = 'newpass';
            } else {
                $error = $check['error'];
            }
        }
    } elseif ($action === 'reset' && $resetEmail !== '' && $verified) {
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        $stmt = $pdo->prepare('SELECT id, password FROM users WHERE LOWER(email) = ? LIMIT 1');
        $stmt->execute([$resetEmail]);
        $account = $stmt->fetch();

        if (!$account) {
            unset($_SESSION['reset_email'], $_SESSION['reset_verified'], $_SESSION['reset_at']);
            $resetEmail = '';
            $verified = false;
            $email_value = '';
            $step = 'identify';
            $error = 'That account is no longer available. Please start again.';
        } elseif (!is_string($newPass) || !is_string($confirmPass)) {
            $error = 'Enter and confirm your new password.';
        } elseif (($policy = password_policy_error($newPass)) !== null) {
            $error = $policy;
        } elseif ($newPass !== $confirmPass) {
            $error = 'Passwords do not match.';
        } elseif (password_verify($newPass, $account['password'])) {
            $error = 'Choose a password you have not used before.';
        } else {
            $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->execute([password_hash($newPass, PASSWORD_DEFAULT), (int) $account['id']]);
            login_attempts_clear($resetEmail);

            session_regenerate_id(true);
            unset($_SESSION['reset_email'], $_SESSION['reset_verified'], $_SESSION['reset_at']);

            header('Location: login.php?reset=1');
            exit;
        }
    } else {
        unset($_SESSION['reset_email'], $_SESSION['reset_verified'], $_SESSION['reset_at']);
        $resetEmail = '';
        $verified = false;
        $step = 'identify';
    }
}

$stepNumber = $step === 'identify' ? 1 : ($step === 'code' ? 2 : 3);
$wait = $step === 'code' ? otp_email_wait($resetEmail, 'reset') : 0;

$page_title = 'Forgot Password - BrightSmile Dental';
require 'header.php';
?>

<div class="auth-wrap">
    <div class="auth-card">
        <img src="logo-icon.svg" alt="BrightSmile Dental" class="auth-logo">

        <div class="steps">
            <div class="step <?= $stepNumber === 1 ? 'active' : 'done' ?>"><span>1</span>Enter Gmail</div>
            <div class="step <?= $stepNumber === 2 ? 'active' : ($stepNumber > 2 ? 'done' : '') ?>"><span>2</span>Verification Code</div>
            <div class="step <?= $stepNumber === 3 ? 'active' : '' ?>"><span>3</span>New Password</div>
        </div>

        <?php if ($step === 'identify'): ?>
            <h1>Forgot Password</h1>
            <p class="auth-sub">Enter the Gmail address on your account and we will email you a verification code.</p>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="send_email">
                <div class="field">
                    <label for="email">Gmail Address</label>
                    <input id="email" type="email" name="email" class="input" value="<?= e($email_value) ?>" minlength="10" maxlength="30" pattern="[A-Za-z0-9._%+-]+@gmail\.com" autocomplete="username" required>
                    <p class="field-hint">Only Gmail addresses can be used.</p>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Send Code to Gmail</button>
            </form>

        <?php elseif ($step === 'code'): ?>
            <h1>Check Your Gmail</h1>
            <p class="auth-sub">Enter the 6-digit code we sent to <strong><?= e($resetEmail) ?></strong>.</p>

            <?php if ($success !== ''): ?>
                <div class="alert alert-success"><?= e($success) ?></div>
            <?php elseif ($error !== ''): ?>
                <div class="alert alert-error" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="verify">
                <div class="field">
                    <label for="code">Verification Code</label>
                    <input id="code" type="text" name="code" class="input" inputmode="numeric" pattern="\d{6}" maxlength="6" placeholder="000000" autocomplete="one-time-code" required>
                    <p class="field-hint">Codes expire after 5 minutes.</p>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Verify Code</button>
            </form>

            <form method="POST" class="auth-alt">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="resend">
                <button type="submit" class="btn btn-sm btn-neutral" id="resendBtn" <?= $wait > 0 ? 'disabled' : '' ?>>Resend code</button>
                <span class="cell-muted" id="resendTimer" <?= $wait > 0 ? '' : 'style="display:none"' ?>>Resend available in <strong><?= (int) $wait ?></strong>s</span>
            </form>

            <p class="auth-alt"><a href="forgot-password.php?restart=1">Start over with a different account</a></p>

        <?php else: ?>
            <h1>Set a New Password</h1>
            <p class="auth-sub">Verified. Choose a new password for <strong><?= e($resetEmail) ?></strong>.</p>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reset">
                <div class="field">
                    <label for="new_password">New Password</label>
                    <div class="password-wrap">
                        <input id="new_password" type="password" name="new_password" class="input" minlength="8" maxlength="20" pattern="(?=.*[A-Za-z])(?=.*\d)(?=.*[^A-Za-z0-9\s]).{8,20}" autocomplete="new-password" required>
                        <button type="button" class="pw-toggle" aria-label="Show password" aria-pressed="false">
                            <svg class="icon-eye" viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                            <svg class="icon-eye-off" viewBox="0 0 24 24"><path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/></svg>
                        </button>
                    </div>
                    <p class="field-hint">8&ndash;20 characters with a letter, a number and a special character (#, @, !, etc.).</p>
                </div>
                <div class="field">
                    <label for="confirm_password">Confirm New Password</label>
                    <div class="password-wrap">
                        <input id="confirm_password" type="password" name="confirm_password" class="input" minlength="8" maxlength="20" pattern="(?=.*[A-Za-z])(?=.*\d)(?=.*[^A-Za-z0-9\s]).{8,20}" autocomplete="new-password" required>
                        <button type="button" class="pw-toggle" aria-label="Show password" aria-pressed="false">
                            <svg class="icon-eye" viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                            <svg class="icon-eye-off" viewBox="0 0 24 24"><path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/></svg>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Update Password</button>
            </form>
        <?php endif; ?>

        <p class="auth-alt"><a href="login.php">Back to login</a></p>
    </div>
</div>

<?php if ($step === 'code'): ?>
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
<?php endif; ?>

<?php require 'footer.php'; ?>
