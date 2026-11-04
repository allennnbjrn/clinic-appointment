<?php
require 'config.php';
require_role('admin');
require_once __DIR__ . '/credentials-lib.php';
require_once __DIR__ . '/sms.php';

$page_title = 'SMS Settings';

$credentials_file = __DIR__ . '/credentials.php';
$creds = credentials_read($credentials_file);

$success = '';
$error = '';
$action = 'save';

$sms_provider = in_array($creds['sms_provider'], ['semaphore', 'twilio'], true) ? $creds['sms_provider'] : '';
$semaphore_ready = $creds['semaphore_key'] !== '';
$twilio_ready = $creds['twilio_sid'] !== '' && $creds['twilio_token'] !== '' && $creds['twilio_from'] !== '';
$configured = ($sms_provider === 'semaphore' && $semaphore_ready) || ($sms_provider === 'twilio' && $twilio_ready);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'test') {
        $test_phone = sms_to_plus63((string) ($_POST['test_phone'] ?? ''));

        if (!$configured) {
            $error = 'Save a gateway API key before sending a test SMS.';
        } elseif ($test_phone === '') {
            $error = 'Enter a valid PH mobile number, for example 9171234567.';
        } else {
            $result = sms_send($test_phone, 'BrightSmile Dental test message. Your SMS gateway is working.');
            if ($result['ok']) {
                $success = 'Test SMS sent to ' . $test_phone . '.';
            } else {
                $error = $result['error'];
            }
        }
    } elseif ($action === 'clear') {
        $creds['sms_provider'] = '';
        $creds['semaphore_key'] = '';
        $creds['semaphore_name'] = '';
        $creds['twilio_sid'] = '';
        $creds['twilio_token'] = '';
        $creds['twilio_from'] = '';
        if (credentials_write($credentials_file, $creds)) {
            header('Location: sms-settings.php?cleared=1');
            exit;
        }
        $error = 'Could not update credentials.php. Check the file permissions.';
    } else {
        $provider = $_POST['provider'] ?? '';
        $semaphore_key = trim((string) ($_POST['semaphore_key'] ?? ''));
        $semaphore_name = trim((string) ($_POST['semaphore_name'] ?? ''));
        $twilio_sid = trim((string) ($_POST['twilio_sid'] ?? ''));
        $twilio_token = trim((string) ($_POST['twilio_token'] ?? ''));
        $twilio_from = trim((string) ($_POST['twilio_from'] ?? ''));

        if ($provider === 'semaphore') {
            if ($semaphore_key === '') {
                $semaphore_key = $creds['semaphore_key'];
            }
            if ($semaphore_key === '') {
                $error = 'Enter your Semaphore API key.';
            } elseif (!preg_match('/^[A-Za-z0-9_-]{16,128}$/', $semaphore_key)) {
                $error = 'That Semaphore API key does not look right. Copy it again from your Semaphore account.';
            } elseif ($semaphore_name !== '' && !preg_match('/^[A-Za-z0-9 ]{1,11}$/', $semaphore_name)) {
                $error = 'The sender name must be 11 characters or fewer (letters, numbers, spaces).';
            } else {
                $creds['sms_provider'] = 'semaphore';
                $creds['semaphore_key'] = $semaphore_key;
                $creds['semaphore_name'] = $semaphore_name;
                if (credentials_write($credentials_file, $creds)) {
                    header('Location: sms-settings.php?saved=1');
                    exit;
                }
                $error = 'Could not save credentials.php. Check the file permissions.';
            }
        } elseif ($provider === 'twilio') {
            if ($twilio_sid === '') {
                $twilio_sid = $creds['twilio_sid'];
            }
            if ($twilio_token === '') {
                $twilio_token = $creds['twilio_token'];
            }
            if ($twilio_from === '') {
                $twilio_from = $creds['twilio_from'];
            }

            if (!preg_match('/^AC[0-9a-fA-F]{32}$/', $twilio_sid)) {
                $error = 'The Twilio Account SID starts with AC and has 32 more characters.';
            } elseif (!preg_match('/^[0-9a-fA-F]{32}$/', $twilio_token)) {
                $error = 'Enter the 32-character Twilio Auth Token for that Account SID.';
            } elseif (!preg_match('/^(\+\d{8,15}|[A-Za-z0-9]{1,11})$/', $twilio_from)) {
                $error = 'Enter the Twilio sender: a phone number like +1234567890 or a 1–11 character sender ID.';
            } else {
                $creds['sms_provider'] = 'twilio';
                $creds['twilio_sid'] = $twilio_sid;
                $creds['twilio_token'] = $twilio_token;
                $creds['twilio_from'] = $twilio_from;
                if (credentials_write($credentials_file, $creds)) {
                    header('Location: sms-settings.php?saved=1');
                    exit;
                }
                $error = 'Could not save credentials.php. Check the file permissions.';
            }
        } else {
            $error = 'Choose Semaphore or Twilio.';
        }
    }
}

if (isset($_GET['saved'])) {
    $success = 'SMS settings saved. Verification codes will now be texted through the gateway.';
} elseif (isset($_GET['cleared'])) {
    $success = 'SMS gateway disconnected. Phone verification is off until a gateway is saved again.';
}

$form_provider = $sms_provider !== '' ? $sms_provider : 'semaphore';
if ($action === 'save' && $error !== '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_provider = $_POST['provider'] ?? '';
    if (in_array($posted_provider, ['semaphore', 'twilio'], true)) {
        $form_provider = $posted_provider;
    }
}

$any_saved = $semaphore_ready || $twilio_ready;

require 'header.php';
?>

<div class="page-wrap">
    <div class="page-head">
        <div>
            <h1 class="page-title">SMS Settings</h1>
            <p class="page-sub">Connect the SMS gateway that texts verification codes to patients' phones.</p>
        </div>
        <a href="admin-dashboard.php" class="btn btn-outline">Admin Dashboard</a>
    </div>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php elseif ($error !== ''): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="panel">
        <h2>Gateway connection</h2>
        <?php if ($configured && $sms_provider === 'semaphore'): ?>
            <p class="alert alert-success">Connected to <strong>Semaphore</strong>. An API key is saved.</p>
        <?php elseif ($configured && $sms_provider === 'twilio'): ?>
            <p class="alert alert-success">Connected to <strong>Twilio</strong> (account <?= e($creds['twilio_sid']) ?>).</p>
        <?php else: ?>
            <p class="alert alert-info">SMS delivery is off. Patients cannot verify their phone number until a gateway is connected.</p>
        <?php endif; ?>

        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">

            <div class="field">
                <label for="provider">Gateway</label>
                <select id="provider" name="provider" class="select" required>
                    <option value="semaphore" <?= $form_provider === 'semaphore' ? 'selected' : '' ?>>Semaphore (Philippines)</option>
                    <option value="twilio" <?= $form_provider === 'twilio' ? 'selected' : '' ?>>Twilio (international)</option>
                </select>
                <p class="field-hint">Codes are sent through the gateway selected here.</p>
            </div>

            <div class="field">
                <label for="semaphore_key">Semaphore API key</label>
                <div class="password-wrap">
                    <input id="semaphore_key" type="password" name="semaphore_key" class="input" maxlength="128" autocomplete="off" placeholder="<?= $semaphore_ready ? 'Saved — leave blank to keep it' : 'From semaphore.co, Dashboard → API key' ?>">
                    <button type="button" class="pw-toggle" aria-label="Show API key" aria-pressed="false">
                        <svg class="icon-eye" viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                        <svg class="icon-eye-off" viewBox="0 0 24 24"><path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/></svg>
                    </button>
                </div>
                <p class="field-hint">Uses your Semaphore credits.</p>
            </div>

            <div class="field">
                <label for="semaphore_name">Semaphore sender name <span class="cell-muted">(optional)</span></label>
                <input id="semaphore_name" type="text" name="semaphore_name" class="input" maxlength="11" value="<?= e($creds['semaphore_name']) ?>" placeholder="BRIGHTSMILE">
                <p class="field-hint">Up to 11 characters. Must be approved by Semaphore.</p>
            </div>

            <div class="field">
                <label for="twilio_sid">Twilio Account SID</label>
                <input id="twilio_sid" type="text" name="twilio_sid" class="input" maxlength="34" autocomplete="off" value="<?= e($creds['twilio_sid']) ?>" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
            </div>

            <div class="field">
                <label for="twilio_token">Twilio Auth Token</label>
                <div class="password-wrap">
                    <input id="twilio_token" type="password" name="twilio_token" class="input" maxlength="32" autocomplete="off" placeholder="<?= $twilio_ready ? 'Saved — leave blank to keep it' : '32-character Auth Token' ?>">
                    <button type="button" class="pw-toggle" aria-label="Show auth token" aria-pressed="false">
                        <svg class="icon-eye" viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                        <svg class="icon-eye-off" viewBox="0 0 24 24"><path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/></svg>
                    </button>
                </div>
            </div>

            <div class="field">
                <label for="twilio_from">Twilio sender</label>
                <input id="twilio_from" type="text" name="twilio_from" class="input" maxlength="16" value="<?= e($creds['twilio_from']) ?>" placeholder="+1234567890">
                <p class="field-hint">A Twilio phone number or a 1–11 character sender ID.</p>
            </div>

            <div class="panel-actions">
                <button type="submit" class="btn btn-primary">Save SMS settings</button>
                <?php if ($any_saved): ?>
                    <button type="submit" form="clearSmsForm" class="btn btn-neutral" onclick="return confirm('Disconnect the SMS gateway? Phone verification will not work until a gateway is saved again.');">Disconnect gateway</button>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($any_saved): ?>
            <form id="clearSmsForm" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="clear">
            </form>
        <?php endif; ?>
    </div>

    <?php if ($configured): ?>
        <div class="panel">
            <h2>Test delivery</h2>
            <p class="field-hint">Sends one test SMS through the gateway. This uses a message credit.</p>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="test">
                <div class="field">
                    <label for="test_phone">Mobile number</label>
                    <input id="test_phone" type="tel" name="test_phone" class="input" inputmode="numeric" placeholder="9171234567" maxlength="13" required>
                    <p class="field-hint">PH mobile number, with or without +63.</p>
                </div>
                <div class="panel-actions">
                    <button type="submit" class="btn btn-neutral">Send test SMS</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php require 'footer.php'; ?>
