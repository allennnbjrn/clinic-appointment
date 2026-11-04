<?php
require 'config.php';
require_role('admin', 'receptionist');
require_once 'mailer.php';

$page_title = 'Appointment Reminders';

$tomorrow = date('Y-m-d', strtotime('+1 day'));
$date = $_GET['date'] ?? $tomorrow;
$chosen = DateTime::createFromFormat('Y-m-d', (string) $date);
if (!$chosen || $chosen->format('Y-m-d') !== $date) {
    $date = $tomorrow;
}
$date = $chosen && $chosen->format('Y-m-d') === $date ? $date : $tomorrow;

$flash = $_SESSION['reminder_flash'] ?? null;
unset($_SESSION['reminder_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $date = $_POST['date'] ?? $tomorrow;
    $chosen = DateTime::createFromFormat('Y-m-d', (string) $date);
    if (!$chosen || $chosen->format('Y-m-d') !== $date) {
        $date = $tomorrow;
    }

    $ids = [];
    if (($_POST['action'] ?? '') === 'send_all') {
        $stmt = $pdo->prepare(
            "SELECT a.id FROM appointments a
             LEFT JOIN appointment_reminders r ON r.appointment_id = a.id
             WHERE a.status = 'confirmed' AND a.appointment_date = ? AND r.id IS NULL"
        );
        $stmt->execute([$date]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    } else {
        $ids = [(int) ($_POST['appointment_id'] ?? 0)];
    }

    $sent = 0;
    $skipped = 0;
    $failed = 0;

    $fetch = $pdo->prepare(
        "SELECT a.*, u.fullname, u.email,
                (SELECT COUNT(*) FROM payments p
                  WHERE p.appointment_id = a.id AND p.payment_type = 'downpayment' AND p.payment_status = 'paid') AS dp_paid,
                r.id AS reminder_id
         FROM appointments a
         JOIN users u ON a.user_id = u.id
         LEFT JOIN appointment_reminders r ON r.appointment_id = a.id
         WHERE a.id = ? AND a.status = 'confirmed' AND a.appointment_date = ? LIMIT 1"
    );
    $mark = $pdo->prepare('INSERT IGNORE INTO appointment_reminders (appointment_id, sent_to) VALUES (?, ?)');

    foreach (array_unique($ids) as $id) {
        if ($id <= 0) {
            continue;
        }
        $fetch->execute([$id, $date]);
        $apt = $fetch->fetch();

        if (!$apt || $apt['reminder_id'] !== null) {
            $skipped++;
            continue;
        }

        $body = "Hello " . $apt['fullname'] . ",\n\n"
            . "This is a reminder of your upcoming appointment at BrightSmile Dental Care.\n\n"
            . "Service: " . $apt['service'] . "\n"
            . "Dentist: " . trim($apt['dentist']) . "\n"
            . "Date: " . date('F j, Y', strtotime($apt['appointment_date'])) . "\n"
            . "Time: " . $apt['time_slot'] . "\n"
            . "Downpayment: " . ((int) $apt['dp_paid'] > 0 ? 'paid' : 'not yet paid') . "\n\n"
            . "Please arrive 10 minutes early. To reschedule or cancel, open My Appointments in your account or call us at (555) 123-4567.\n\n"
            . "BrightSmile Dental Care\n123 Health Ave, Suite 400";

        $result = mail_send($apt['email'], 'Appointment reminder — ' . date('M j, Y', strtotime($apt['appointment_date'])) . ' at ' . $apt['time_slot'], $body);
        if ($result['ok']) {
            $mark->execute([$id, $apt['email']]);
            $sent++;
        } else {
            $failed++;
        }
    }

    $msg = $sent . ' reminder' . ($sent === 1 ? '' : 's') . ' sent';
    if ($skipped > 0) {
        $msg .= ', ' . $skipped . ' skipped (already sent or not eligible)';
    }
    if ($failed > 0) {
        $msg .= ', ' . $failed . ' failed';
    }

    $_SESSION['reminder_flash'] = ['type' => $failed > 0 ? 'error' : 'success', 'msg' => $msg . '.'];
    log_activity('reminders_sent', $sent . ' sent, ' . $skipped . ' skipped, ' . $failed . ' failed for ' . $date);
    header('Location: reminders.php?date=' . urlencode($date));
    exit;
}

$stmt = $pdo->prepare(
    "SELECT a.*, u.fullname, u.email,
            (SELECT COUNT(*) FROM payments p
              WHERE p.appointment_id = a.id AND p.payment_type = 'downpayment' AND p.payment_status = 'paid') AS dp_paid,
            r.sent_at
     FROM appointments a
     JOIN users u ON a.user_id = u.id
     LEFT JOIN appointment_reminders r ON r.appointment_id = a.id
     WHERE a.status = 'confirmed' AND a.appointment_date = ?
     ORDER BY a.time_slot"
);
$stmt->execute([$date]);
$appointments = $stmt->fetchAll();

$pending_reminders = array_filter($appointments, fn ($a) => $a['sent_at'] === null);

require 'header.php';
?>

<div class="page-wrap">
    <div class="page-head">
        <div>
            <h1 class="page-title">Appointment Reminders</h1>
            <p class="page-sub">Email patients a reminder for confirmed visits. Each appointment gets one reminder per date.</p>
        </div>
        <a href="<?= $_SESSION['role'] === 'admin' ? 'admin-dashboard.php' : 'receptionist.php' ?>" class="btn btn-outline">Back to Dashboard</a>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>" role="alert"><?= e($flash['msg']) ?></div>
    <?php endif; ?>

    <div class="panel">
        <form method="GET" class="panel-actions">
            <div class="field">
                <label for="date">Reminder date</label>
                <input type="date" id="date" name="date" class="input" value="<?= e($date) ?>" onchange="this.form.submit()">
            </div>
            <noscript><button type="submit" class="btn btn-sm btn-neutral">Show</button></noscript>
        </form>

        <?php if (empty($appointments)): ?>
            <div class="empty-state">
                <p>No confirmed appointments on <?= date('F j, Y', strtotime($date)) ?>.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Patient</th>
                            <th>Service</th>
                            <th>Dentist</th>
                            <th>Downpayment</th>
                            <th>Reminder</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $apt): ?>
                        <tr>
                            <td class="cell-strong"><?= e($apt['time_slot']) ?></td>
                            <td>
                                <div class="cell-strong"><?= e($apt['fullname']) ?></div>
                                <div class="cell-muted"><?= e($apt['email']) ?></div>
                            </td>
                            <td><?= e($apt['service']) ?></td>
                            <td><?= e($apt['dentist']) ?></td>
                            <td>
                                <?php if ((int) $apt['dp_paid'] > 0): ?>
                                    <span class="badge badge-paid">Paid</span>
                                <?php else: ?>
                                    <span class="badge badge-pending">Unpaid</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($apt['sent_at'] !== null): ?>
                                    <span class="badge badge-confirmed">Sent</span>
                                    <div class="cell-muted"><?= date("M j, g:i A", strtotime($apt['sent_at'])) ?></div>
                                <?php else: ?>
                                    <span class="badge badge-pending">Not sent</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($apt['sent_at'] === null): ?>
                                    <form method="POST">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="date" value="<?= e($date) ?>">
                                        <input type="hidden" name="appointment_id" value="<?= (int) $apt['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-primary">Send reminder</button>
                                    </form>
                                <?php else: ?>
                                    <span class="cell-muted">&mdash;</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($pending_reminders)): ?>
                <form method="POST" class="panel-actions">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="send_all">
                    <input type="hidden" name="date" value="<?= e($date) ?>">
                    <button type="submit" class="btn btn-primary">Send to all (<?= count($pending_reminders) ?>)</button>
                </form>
            <?php else: ?>
                <p class="cell-muted">All confirmed patients for this date have been reminded.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require 'footer.php'; ?>
