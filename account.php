<?php
require 'config.php';
require_login();

$page_title = 'My Account';

$user_id = (int) $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    session_end();
    header('Location: login.php');
    exit;
}

$profile_success = '';
$profile_error = '';
$pw_success = '';
$pw_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $fullname = trim((string) ($_POST['fullname'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));

        if (mb_strlen($fullname) < 2 || mb_strlen($fullname) > 100) {
            $profile_error = 'Please enter your full name (2-100 characters).';
        } elseif ($phone !== '' && (mb_strlen($phone) > 30 || !preg_match('/^[0-9+()\-\s.]+$/', $phone))) {
            $profile_error = 'Enter a valid phone number (digits, spaces, and + ( ) - . only).';
        } else {
            $upd = $pdo->prepare('UPDATE users SET fullname = ?, phone = ? WHERE id = ?');
            $upd->execute([$fullname, $phone !== '' ? $phone : null, $user_id]);

            $_SESSION['fullname'] = $fullname;
            $user['fullname'] = $fullname;
            $user['phone'] = $phone !== '' ? $phone : null;

            log_activity('profile_updated', 'Name/phone updated');
            $profile_success = 'Your details have been saved.';
        }
    } elseif ($action === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');

        if ($current === '' || !password_verify($current, $user['password'])) {
            $pw_error = 'Your current password is incorrect.';
        } elseif (($policy = password_policy_error($new)) !== null) {
            $pw_error = $policy;
        } elseif ($new !== $confirm) {
            $pw_error = 'The new password and confirmation do not match.';
        } else {
            $upd = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $upd->execute([password_hash($new, PASSWORD_DEFAULT), $user_id]);

            log_activity('password_changed', 'Password changed from account page');
            $pw_success = 'Your password has been updated.';
        }
    } else {
        $profile_error = 'Invalid request.';
    }
}

$role_label = ucfirst((string) $user['role']);

require 'header.php';
?>

<div class="page-wrap">
    <div class="page-head">
        <div>
            <h1 class="page-title">My Account</h1>
            <p class="page-sub">Update your details and keep your password secure.</p>
        </div>
        <a href="<?= e(home_for_role((string) $user['role'])) ?>" class="btn btn-outline">Back to Dashboard</a>
    </div>

    <div class="panel">
        <h2>Profile</h2>

        <?php if ($profile_success !== ''): ?>
            <div class="alert alert-success"><?= e($profile_success) ?></div>
        <?php elseif ($profile_error !== ''): ?>
            <div class="alert alert-error" role="alert"><?= e($profile_error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="profile">
            <div class="form-grid">
                <div class="field">
                    <label for="fullname">Full Name</label>
                    <input type="text" name="fullname" id="fullname" class="input" maxlength="100" value="<?= e($user['fullname']) ?>" required>
                </div>
                <div class="field">
                    <label for="phone">Phone Number</label>
                    <input type="tel" name="phone" id="phone" class="input" maxlength="30" value="<?= e($user['phone'] ?? '') ?>" placeholder="e.g. (555) 123-4567">
                </div>
            </div>

            <div class="form-grid">
                <div class="field">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" class="input" value="<?= e($user['email']) ?>" readonly>
                    <p class="field-hint">This is your sign-in address and where verification codes are sent. Ask the clinic administrator to change it.</p>
                </div>
                <div class="field">
                    <label>Account</label>
                    <p class="cell-muted">
                        <span class="badge badge-role-<?= e($user['role']) ?>"><?= e($role_label) ?></span>
                        &middot; Member since <?= date('F j, Y', strtotime($user['created_at'])) ?>
                    </p>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
    </div>

    <div class="panel">
        <h2>Change Password</h2>

        <?php if ($pw_success !== ''): ?>
            <div class="alert alert-success"><?= e($pw_success) ?></div>
        <?php elseif ($pw_error !== ''): ?>
            <div class="alert alert-error" role="alert"><?= e($pw_error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="password">
            <div class="form-grid-3">
                <div class="field">
                    <label for="current_password">Current Password</label>
                    <input type="password" name="current_password" id="current_password" class="input" maxlength="20" autocomplete="current-password" required>
                </div>
                <div class="field">
                    <label for="new_password">New Password</label>
                    <input type="password" name="new_password" id="new_password" class="input" maxlength="20" autocomplete="new-password" required>
                </div>
                <div class="field">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="input" maxlength="20" autocomplete="new-password" required>
                </div>
            </div>
            <p class="field-hint">8 to 20 characters, with at least one letter, one number, and one special character (for example # or @).</p>
            <button type="submit" class="btn btn-primary">Update Password</button>
        </form>
    </div>
</div>

<?php require 'footer.php'; ?>
