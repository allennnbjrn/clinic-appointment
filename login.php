<?php
require 'config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . home_for_role($_SESSION['role'] ?? 'patient'));
    exit;
}

if (!empty($_SESSION['pending_login_id'])) {
    $pending = $pdo->prepare('SELECT id, fullname, role FROM users WHERE id = ? LIMIT 1');
    $pending->execute([(int) $_SESSION['pending_login_id']]);
    $pendingUser = $pending->fetch();

    if (!$pendingUser) {
        unset($_SESSION['pending_login_id'], $_SESSION['pending_login_email'], $_SESSION['pending_login_at']);
    } elseif (skips_login_code($pendingUser['role'])) {
        // Staff accounts never need the email code, even from an earlier attempt.
        sign_in_user($pendingUser);
    } else {
        header('Location: login-otp.php');
        exit;
    }
}

$email = '';
$error = '';
$notice = '';
if (isset($_GET['logged_out'])) {
    $notice = 'You have been logged out.';
} elseif (isset($_GET['reset'])) {
    $notice = 'Your password has been updated. Log in with your new password.';
} elseif (isset($_GET['timeout'])) {
    $notice = 'You were signed out after 30 minutes of inactivity. Please log in again.';
} elseif (isset($_GET['expired'])) {
    $notice = 'Your session reached its 8-hour limit. Please log in again.';
} elseif (isset($_GET['otp_expired'])) {
    $notice = 'Your verification code expired. Please sign in again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $emailInput = $_POST['email'] ?? '';
    $passwordInput = $_POST['password'] ?? '';

    if (!is_string($emailInput) || !is_string($passwordInput)) {
        $error = 'Enter a valid email address and password.';
    } else {
        $email = strtolower(preg_replace('/\s+/', '', $emailInput));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $passwordInput === '') {
            $error = 'Enter a valid email address and password.';
        } elseif (($email_err = email_length_error($email)) !== null) {
            $error = $email_err;
        } elseif (($blocked_for = login_attempts_blocked($email)) > 0) {
            $error = 'Too many failed attempts. Please try again in ' . $blocked_for . ' minute' . ($blocked_for === 1 ? '' : 's') . '.';
        } else {
            $stmt = $pdo->prepare(
                'SELECT id, fullname, email, password, role FROM users WHERE LOWER(email) = ? LIMIT 1'
            );
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($passwordInput, $user['password'])) {
                login_attempts_record($email);
                $error = 'Incorrect email or password.';
            } else {
                login_attempts_clear($email);

                // Staff accounts skip the email code and go straight to their dashboard.
                if (skips_login_code($user['role'])) {
                    sign_in_user($user);
                }

                // Step 2: one-time code to the registered email before the session starts.
                $_SESSION['pending_login_id'] = (int) $user['id'];
                $sent = otp_email_issue($user['email'], 'login');

                if ($sent['ok']) {
                    $_SESSION['pending_login_email'] = $user['email'];
                    $_SESSION['pending_login_at'] = time();
                    header('Location: login-otp.php');
                    exit;
                }

                unset($_SESSION['pending_login_id']);
                $error = $sent['error'];
            }
        }
    }
}

$page_title = 'Login - BrightSmile Dental';
require 'header.php';
?>

<div class="auth-wrap">
    <div class="auth-card">
        <img src="logo-icon.svg" alt="BrightSmile Dental" class="auth-logo">
        <h1>Welcome Back</h1>
        <p class="auth-sub">Log in to manage your appointments and account.</p>

        <div class="steps">
            <div class="step active"><span>1</span>Sign In</div>
            <div class="step"><span>2</span>Verification Code</div>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php elseif ($notice !== ''): ?>
            <div class="alert alert-success"><?= e($notice) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <?= csrf_field() ?>
            <div class="field">
                <label for="email">Email Address</label>
                <input id="email" type="email" name="email" class="input" value="<?= e($email) ?>" minlength="10" maxlength="30" autocomplete="username" required>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <div class="password-wrap">
                    <input id="password" type="password" name="password" class="input" maxlength="20" autocomplete="current-password" required>
                    <button type="button" class="pw-toggle" aria-label="Show password" aria-pressed="false">
                        <svg class="icon-eye" viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                        <svg class="icon-eye-off" viewBox="0 0 24 24"><path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/></svg>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Login</button>
        </form>

        <p class="auth-alt"><a href="forgot-password.php">Forgot your password?</a></p>
        <p class="auth-alt">No account yet? <a href="register.php">Register here</a></p>
    </div>
</div>

<?php require 'footer.php'; ?>
