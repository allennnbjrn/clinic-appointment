<?php
require 'config.php';
require_role('admin');
require_once __DIR__ . '/credentials-lib.php';

$page_title = 'Email Settings';

$credentials_file = __DIR__ . '/credentials.php';

$creds = credentials_read($credentials_file);
$success = '';
$error = '';
$action = 'save';
$configured = $creds['gmail_user'] !== '' && $creds['gmail_app_password'] !== '';
$local_inbox = mail_local_available();

$admin_email = '';
$admin_stmt = $pdo->prepare('SELECT email FROM users WHERE id = ?');
$admin_stmt->execute([(int) ($_SESSION['user_id'] ?? 0)]);
$admin_email = (string) ($admin_stmt->fetchColumn() ?: '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'test') {
        if ($configured) {
            $recipient = $creds['gmail_user'];
        } elseif ($local_inbox && $admin_email !== '') {
            $recipient = $admin_email;
        } else {
            $recipient = '';
        }

        if ($recipient === '') {
            $error = 'Save a Gmail address and App Password before sending a test email.';
        } else {
            $body = "This is a test message from BrightSmile Dental.\r\n\r\n"
                . "If you received this, patient verification codes can now be delivered by email.";
            $result = mail_send($recipient, 'BrightSmile Dental test email', $body);
            if ($result['ok']) {
                $success = $configured
                    ? 'Test email sent to ' . $recipient . '. Check that inbox to confirm delivery.'
                    : 'Test email sent to ' . $recipient . ' through the local inbox. Open http://localhost:8025 to read it.';
            } else {
                $error = $result['error'];
            }
        }
    } elseif ($action === 'clear') {
        $creds['mail_provider'] = '';
        $creds['gmail_user'] = '';
        $creds['gmail_app_password'] = '';
        if (credentials_write($credentials_file, $creds)) {
            header('Location: email-settings.php?cleared=1');
            exit;
        }
        $error = 'Could not update credentials.php. Check the file permissions.';
    } else {
        $email = strtolower(trim((string) ($_POST['gmail_user'] ?? '')));
        $password = preg_replace('/\s+/', '', (string) ($_POST['gmail_app_password'] ?? ''));
        $from_name = trim((string) ($_POST['mail_from_name'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/@(gmail|googlemail)\.com$/', $email)) {
            $error = 'Enter the clinic Gmail address, for example brightsmileclinic@gmail.com.';
        } elseif ($password === '' && $creds['gmail_app_password'] === '') {
            $error = 'Enter the 16-character Google App Password for that Gmail account.';
        } elseif (!preg_match('/^[A-Za-z0-9]{16}$/', $password)) {
            $error = 'The App Password must be exactly 16 letters and numbers. Copy it again from your Google Account.';
        } elseif ($from_name !== '' && mb_strlen($from_name) > 80) {
            $error = 'The sender name must be 80 characters or fewer.';
        } else {
            if ($password === '') {
                $password = $creds['gmail_app_password'];
            }
            $creds['mail_provider'] = 'smtp';
            $creds['gmail_user'] = $email;
            $creds['gmail_app_password'] = $password;
            $creds['mail_from_name'] = $from_name !== '' ? $from_name : 'BrightSmile Dental';

            if (credentials_write($credentials_file, $creds)) {
                $synced = false;
                if (!empty($_POST['sync_admin_email']) && strcasecmp($email, $admin_email) !== 0) {
                    try {
                        $pdo->prepare('UPDATE users SET email = ? WHERE id = ?')
                            ->execute([$email, (int) $_SESSION['user_id']]);
                        $synced = true;
                    } catch (PDOException $ex) {
                        header('Location: email-settings.php?saved=1&emailtaken=1');
                        exit;
                    }
                }
                header('Location: email-settings.php?saved=1' . ($synced ? '&adminemail=1' : ''));
                exit;
            }
            $error = 'Could not save credentials.php. Check the file permissions.';
        }
    }
}

if (isset($_GET['saved'])) {
    $success = 'Email settings saved. Verification codes will now be sent through Gmail.';
    if (isset($_GET['adminemail'])) {
        $success .= ' Your admin login email is now ' . $admin_email . ' — use it the next time you log in.';
    }
} elseif (isset($_GET['cleared'])) {
    $success = 'Gmail disconnected. Email delivery is off until an account is saved again.';
}
if (isset($_GET['emailtaken'])) {
    $error = 'The clinic Gmail is already used by another account, so your admin login email was not changed. Verification codes are still sent through Gmail.';
}

$form_email = $creds['gmail_user'];
$form_name = $creds['mail_from_name'] !== '' ? $creds['mail_from_name'] : 'BrightSmile Dental';
if ($action === 'save' && $error !== '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_email = (string) ($_POST['gmail_user'] ?? '');
    $form_name = (string) ($_POST['mail_from_name'] ?? '');
}

require 'header.php';
?>

<div class="page-wrap">
    <div class="page-head">
        <div>
            <h1 class="page-title">Email Settings</h1>
            <p class="page-sub">Connect the clinic Gmail account that delivers patient verification codes.</p>
        </div>
        <a href="admin-dashboard.php" class="btn btn-outline">Admin Dashboard</a>
    </div>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="panel">
        <h2>Gmail connection</h2>
        <?php if ($configured): ?>
            <p class="alert alert-success">Connected as <strong><?= e($creds['gmail_user']) ?></strong>. An App Password is saved.</p>
        <?php elseif ($local_inbox): ?>
            <p class="alert alert-info">Gmail is not connected. Codes are being delivered to this machine's <a href="http://localhost:8025" target="_blank" rel="noopener">local test inbox</a> instead. Connect Gmail below for real patient delivery.</p>
        <?php else: ?>
            <p class="alert alert-info">Email delivery is off. Verification codes cannot be emailed to patients until a Gmail account is connected.</p>
        <?php endif; ?>

        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">

            <div class="field">
                <label for="gmail_user">Clinic Gmail address</label>
                <input id="gmail_user" type="email" name="gmail_user" class="input" value="<?= e($form_email) ?>" maxlength="120" placeholder="brightsmileclinic@gmail.com" autocomplete="off" required>
                <p class="field-hint">Codes are sent from this address.</p>
            </div>

            <div class="field">
                <label for="gmail_app_password">Google App Password</label>
                <div class="password-wrap">
                    <input id="gmail_app_password" type="password" name="gmail_app_password" class="input" maxlength="19" autocomplete="off" placeholder="<?= $configured ? 'Saved — leave blank to keep it' : '16 characters' ?>">
                    <button type="button" class="pw-toggle" aria-label="Show password" aria-pressed="false">
                        <svg class="icon-eye" viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                        <svg class="icon-eye-off" viewBox="0 0 24 24"><path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/></svg>
                    </button>
                </div>
                <p class="field-hint">Google Account &rarr; Security &rarr; App passwords. Requires 2-Step Verification on the account.</p>
            </div>

            <div class="field">
                <label for="mail_from_name">Sender name</label>
                <input id="mail_from_name" type="text" name="mail_from_name" class="input" value="<?= e($form_name) ?>" maxlength="80" placeholder="BrightSmile Dental">
                <p class="field-hint">Shown in the patient's inbox next to the address.</p>
            </div>

            <div class="field">
                <label style="display:flex; align-items:flex-start; gap:10px; text-transform:none; font-size:14.5px;">
                    <input type="checkbox" name="sync_admin_email" value="1" style="margin-top:3px;" <?= preg_match('/@(gmail|googlemail)\.com$/i', $admin_email) ? '' : 'checked' ?>>
                    <span>Use this Gmail for my admin login too</span>
                </label>
                <p class="field-hint">Admin 2FA codes go to <?= e($admin_email) ?> right now. Tick this so they reach a real inbox.</p>
            </div>

            <div class="panel-actions">
                <button type="submit" class="btn btn-primary">Save email settings</button>
                <?php if ($configured): ?>
                    <button type="submit" form="clearEmailForm" class="btn btn-neutral" onclick="return confirm('Disconnect Gmail? Verification codes will not be emailed until an account is saved again.');">Disconnect Gmail</button>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($configured): ?>
            <form id="clearEmailForm" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="clear">
            </form>
        <?php endif; ?>
    </div>

    <?php if ($configured || ($local_inbox && $admin_email !== '')): ?>
        <div class="panel">
            <h2>Test delivery</h2>
            <?php if ($configured): ?>
                <p class="field-hint">Sends a test message to <?= e($creds['gmail_user']) ?> to confirm the App Password works before patients rely on it.</p>
            <?php else: ?>
                <p class="field-hint">Sends a test message to <?= e($admin_email) ?> through the <a href="http://localhost:8025" target="_blank" rel="noopener">local test inbox</a>. It stays on this machine and does not reach patients.</p>
            <?php endif; ?>
            <form method="POST" class="panel-actions">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="test">
                <button type="submit" class="btn btn-neutral">Send test email</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php require 'footer.php'; ?>
