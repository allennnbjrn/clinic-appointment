<?php
require 'config.php';
require_once __DIR__ . '/sms.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . home_for_role($_SESSION['role'] ?? 'patient'));
    exit;
}

if (!empty($_SESSION['pending_reg'])) {
    header('Location: register-verify.php');
    exit;
}

$first_name = '';
$middle_name = '';
$last_name = '';
$email = '';
$phone = '';
$error = '';
$notice = '';

if (isset($_GET['expired'])) {
    $notice = 'Your registration expired. Please enter your details again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $firstNameInput = $_POST['first_name'] ?? '';
    $middleNameInput = $_POST['middle_name'] ?? '';
    $lastNameInput = $_POST['last_name'] ?? '';
    $emailInput = $_POST['email'] ?? '';
    $phoneInput = $_POST['phone'] ?? '';
    $passwordInput = $_POST['password'] ?? '';
    $confirmInput = $_POST['confirm_password'] ?? '';

    if (!is_string($firstNameInput) || !is_string($middleNameInput) || !is_string($lastNameInput)
        || !is_string($emailInput) || !is_string($phoneInput) || !is_string($passwordInput)
        || !is_string($confirmInput)) {
        $error = 'Please fill in all fields.';
    } else {
        $first_name = preg_replace('/\s+/', ' ', trim($firstNameInput));
        $middle_name = preg_replace('/\s+/', ' ', trim($middleNameInput));
        $last_name = preg_replace('/\s+/', ' ', trim($lastNameInput));
        $email = preg_replace('/\s+/', '', $emailInput);
        $phone = sms_to_plus63($phoneInput);
        $fullname = implode(' ', array_filter([$first_name, $middle_name, $last_name], fn ($v) => $v !== ''));

        if ($first_name === '' || mb_strlen($first_name) > 50) {
            $error = 'Please enter your first name (max 50 characters).';
        } elseif ($last_name === '' || mb_strlen($last_name) > 50) {
            $error = 'Please enter your surname (max 50 characters).';
        } elseif (mb_strlen($middle_name) > 50) {
            $error = 'Middle name must be at most 50 characters.';
        } elseif (($email_err = email_length_error($email)) !== null) {
            $error = $email_err;
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!str_ends_with(strtolower($email), '@gmail.com')) {
            $error = 'Only Gmail addresses are allowed (e.g., yourname@gmail.com).';
        } elseif ($phone === '') {
            $error = 'Please enter a valid mobile number (e.g., 9171234567).';
        } elseif (($policy = password_policy_error($passwordInput)) !== null) {
            $error = $policy;
        } elseif ($passwordInput !== $confirmInput) {
            $error = 'Passwords do not match.';
        } else {
            $check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $check->execute([$email]);

            if ($check->fetch()) {
                $error = 'Email is already registered!';
            } else {
                // Verify the Gmail before the account exists: stash the details,
                // send the code, and finish registration on register-verify.php.
                $_SESSION['pending_reg'] = [
                    'first_name'    => $first_name,
                    'middle_name'   => $middle_name,
                    'last_name'     => $last_name,
                    'fullname'      => $fullname,
                    'email'         => $email,
                    'phone'         => $phone,
                    'password_hash' => password_hash($passwordInput, PASSWORD_DEFAULT),
                ];
                $_SESSION['pending_reg_at'] = time();

                $sent = otp_email_issue($email, 'register', false);
                if ($sent['ok']) {
                    header('Location: register-verify.php');
                    exit;
                }

                unset($_SESSION['pending_reg']);
                $error = $sent['error'];
            }
        }
    }
}

$page_title = 'Register - BrightSmile Dental';
require 'header.php';
?>

<div class="auth-wrap">
    <div class="auth-card">
        <img src="logo-icon.svg" alt="BrightSmile Dental" class="auth-logo">
        <h1>Create Your Account</h1>
        <p class="auth-sub">Join BrightSmile Dental and book appointments online.</p>

        <div class="steps">
            <div class="step active"><span>1</span>Your Details</div>
            <div class="step"><span>2</span>Verify Gmail</div>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php elseif ($notice !== ''): ?>
            <div class="alert alert-info"><?= e($notice) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrf_field() ?>
            <div class="field">
                <label for="first_name">First Name</label>
                <input id="first_name" type="text" name="first_name" class="input" value="<?= e($first_name) ?>" maxlength="50" autocomplete="given-name" required>
            </div>
            <div class="field">
                <label for="middle_name">Middle Name</label>
                <input id="middle_name" type="text" name="middle_name" class="input" value="<?= e($middle_name) ?>" maxlength="50" autocomplete="additional-name">
                <p class="field-hint">Optional.</p>
            </div>
            <div class="field">
                <label for="last_name">Surname</label>
                <input id="last_name" type="text" name="last_name" class="input" value="<?= e($last_name) ?>" maxlength="50" autocomplete="family-name" required>
            </div>
            <div class="field">
                <label for="email">Email Address</label>
                <input id="email" type="email" name="email" class="input" value="<?= e($email) ?>" minlength="10" maxlength="30" pattern="[A-Za-z0-9._%+-]+@gmail\.com" autocomplete="username" required>
                <p class="field-hint">Gmail address only (e.g., yourname@gmail.com).</p>
            </div>
            <div class="field">
                <label for="phone">Mobile Number</label>
                <div class="phone-wrap">
                    <span class="phone-prefix">+63</span>
                    <input id="phone" type="tel" name="phone" class="input" value="<?= e($phone !== '' ? substr($phone, 3) : '') ?>" inputmode="numeric" pattern="9[0-9]{9}" maxlength="10" placeholder="9171234567" required>
                </div>
                <p class="field-hint">10-digit mobile number after +63.</p>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <div class="password-wrap">
                    <input id="password" type="password" name="password" class="input" minlength="8" maxlength="20" pattern="(?=.*[A-Za-z])(?=.*\d)(?=.*[^A-Za-z0-9\s]).{8,20}" autocomplete="new-password" required>
                    <button type="button" class="pw-toggle" aria-label="Show password" aria-pressed="false">
                        <svg class="icon-eye" viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                        <svg class="icon-eye-off" viewBox="0 0 24 24"><path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/></svg>
                    </button>
                </div>
                <p class="field-hint">8&ndash;20 characters with at least one letter, one number, and one special character (#, @, !, etc.).</p>
            </div>
            <div class="field">
                <label for="confirm_password">Confirm Password</label>
                <div class="password-wrap">
                    <input id="confirm_password" type="password" name="confirm_password" class="input" minlength="8" maxlength="20" pattern="(?=.*[A-Za-z])(?=.*\d)(?=.*[^A-Za-z0-9\s]).{8,20}" autocomplete="new-password" required>
                    <button type="button" class="pw-toggle" aria-label="Show password" aria-pressed="false">
                        <svg class="icon-eye" viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                        <svg class="icon-eye-off" viewBox="0 0 24 24"><path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/></svg>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Create Account</button>
        </form>

        <p class="auth-alt">Already have an account? <a href="login.php">Login here</a></p>
    </div>
</div>

<?php require 'footer.php'; ?>
