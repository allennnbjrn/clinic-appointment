<?php
require 'config.php';
require_login();

$page_title = 'My Appointment History';

// Patients only
if ($_SESSION['role'] !== 'patient') {
    header('Location: index.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];

$flash = $_SESSION['appt_flash'] ?? null;
unset($_SESSION['appt_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';
    $appointment_id = (int) ($_POST['appointment_id'] ?? 0);

    $stmt = $pdo->prepare('SELECT * FROM appointments WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$appointment_id, $user_id]);
    $apt = $stmt->fetch();

    $today = date('Y-m-d');

    if (!$apt) {
        $flash = ['type' => 'error', 'msg' => 'That appointment could not be found.'];
    } elseif (!in_array($apt['status'], ['pending', 'confirmed'], true)) {
        $flash = ['type' => 'error', 'msg' => 'Only pending or confirmed appointments can be changed.'];
    } elseif ($apt['appointment_date'] < $today) {
        $flash = ['type' => 'error', 'msg' => 'Past appointments can no longer be changed. Please call the clinic.'];
    } elseif ($action === 'cancel') {
        $upd = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ? AND user_id = ?");
        $upd->execute([$appointment_id, $user_id]);
        log_activity('appointment_cancelled_by_patient', '#' . $appointment_id . ' ' . $apt['service'] . ' on ' . $apt['appointment_date'] . ' ' . $apt['time_slot']);
        appointment_notify($appointment_id, 'cancelled');
        $flash = ['type' => 'success', 'msg' => 'Appointment #' . $appointment_id . ' has been cancelled.'];
    } elseif ($action === 'reschedule') {
        $new_date = $_POST['appointment_date'] ?? '';
        $new_slot = $_POST['time_slot'] ?? '';

        $chosen = DateTime::createFromFormat('Y-m-d', (string) $new_date);
        if (!$chosen || $chosen->format('Y-m-d') !== $new_date || $new_date < $today) {
            $flash = ['type' => 'error', 'msg' => 'Choose today or a future date.'];
        } elseif ((int) $chosen->format('w') === CLINIC_CLOSED_WEEKDAY) {
            $flash = ['type' => 'error', 'msg' => 'We are closed on Sundays. Please pick another day.'];
        } elseif (!in_array($new_slot, TIME_SLOTS, true)) {
            $flash = ['type' => 'error', 'msg' => 'Please choose a valid time slot.'];
        } else {
            $conflict = $pdo->prepare(
                "SELECT id FROM appointments
                 WHERE dentist = ? AND appointment_date = ? AND time_slot = ? AND status != 'cancelled' AND id != ?
                 LIMIT 1"
            );
            $conflict->execute([$apt['dentist'], $new_date, $new_slot, $appointment_id]);

            if ($conflict->fetch()) {
                $flash = ['type' => 'error', 'msg' => 'That slot is already taken for this dentist. Please choose another.'];
            } else {
                $upd = $pdo->prepare('UPDATE appointments SET appointment_date = ?, time_slot = ? WHERE id = ? AND user_id = ?');
                $upd->execute([$new_date, $new_slot, $appointment_id, $user_id]);
                log_activity('appointment_rescheduled', '#' . $appointment_id . ': ' . $apt['appointment_date'] . ' ' . $apt['time_slot'] . ' -> ' . $new_date . ' ' . $new_slot);
                appointment_notify($appointment_id, 'rescheduled', [
                    'old_when' => date('F j, Y', strtotime($apt['appointment_date'])) . ' at ' . $apt['time_slot'],
                ]);
                $flash = [
                    'type' => 'success',
                    'msg' => 'Appointment #' . $appointment_id . ' moved to ' . date('F j, Y', strtotime($new_date)) . ' at ' . $new_slot . '.',
                ];
            }
        }
    } else {
        $flash = ['type' => 'error', 'msg' => 'Unknown action.'];
    }

    $_SESSION['appt_flash'] = $flash;
    header('Location: appointment-history.php');
    exit;
}

// Get all appointments for this user
$stmt = $pdo->prepare(
    "SELECT a.*,
            (SELECT COUNT(*) FROM payments p
              WHERE p.appointment_id = a.id AND p.payment_type = 'downpayment' AND p.payment_status = 'paid') AS dp_paid,
            (SELECT COUNT(*) FROM payments p
              WHERE p.appointment_id = a.id AND p.payment_type = 'balance' AND p.payment_status = 'paid') AS balance_paid,
            (SELECT COALESCE(SUM(p.amount), 0) FROM payments p
              WHERE p.appointment_id = a.id AND p.payment_status = 'paid') AS paid_total
     FROM appointments a
     WHERE a.user_id = ?
     ORDER BY a.appointment_date DESC"
);
$stmt->execute([$user_id]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$today = date('Y-m-d');

require 'header.php';
?>

<div class="page-wrap">
    <div class="page-head">
        <div>
            <h1 class="page-title">My Appointment History</h1>
            <p class="page-sub">All of your visits, past and upcoming.</p>
        </div>
        <a href="book.appointment.php" class="btn btn-primary">Book New Appointment</a>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>" role="<?= $flash['type'] === 'success' ? 'status' : 'alert' ?>">
            <?= e($flash['msg']) ?>
        </div>
    <?php endif; ?>

    <div class="panel">
        <?php if (empty($appointments)): ?>
            <div class="empty-state">
                <p>You have no appointments yet.</p>
                <a href="book.appointment.php" class="btn btn-primary">Book your first appointment</a>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Date</th>
                            <th>Time Slot</th>
                            <th>Status</th>
                            <th>Payments</th>
                            <th>Requested On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $apt): ?>
                        <?php
                            $remaining = max(0, (float) $apt['service_price'] - (float) $apt['paid_total']);
                            $can_modify = in_array($apt['status'], ['pending', 'confirmed'], true) && $apt['appointment_date'] >= $today;
                        ?>
                        <tr>
                            <td class="cell-strong">
                                <?= e($apt['service']) ?>
                                <div class="cell-muted">
                                    <?= e($apt['dentist']) ?><?php if ((float) $apt['service_price'] > 0): ?> &middot; &#8369;<?= number_format((float) $apt['service_price'], 2) ?><?php endif; ?>
                                </div>
                                <?php if (!empty($apt['notes'])): ?>
                                    <div class="cell-muted"><?= e($apt['notes']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= date("F j, Y", strtotime($apt['appointment_date'])) ?></td>
                            <td><?= e($apt['time_slot']) ?></td>
                            <td><span class="badge badge-<?= e($apt['status']) ?>"><?= e(ucfirst($apt['status'])) ?></span></td>
                            <td>
                                <?php if ((int) $apt['dp_paid'] > 0): ?>
                                    <span class="badge badge-paid">Downpayment Paid</span>
                                <?php endif; ?>
                                <?php if ($apt['status'] === 'confirmed' && (int) $apt['dp_paid'] === 0): ?>
                                    <a class="btn btn-sm btn-primary" href="payment.php?appointment=<?= (int) $apt['id'] ?>">Pay &#8369;<?= number_format(DOWNPAYMENT_AMOUNT, 2) ?></a>
                                <?php elseif ($apt['status'] === 'pending'): ?>
                                    <span class="cell-muted">After confirmation</span>
                                <?php elseif ($apt['status'] === 'completed'): ?>
                                    <?php if ((int) $apt['balance_paid'] > 0 || $remaining <= 0.009): ?>
                                        <span class="badge badge-paid">Fully Paid</span>
                                    <?php else: ?>
                                        <a class="btn btn-sm btn-primary" href="payment.php?appointment=<?= (int) $apt['id'] ?>#balance-panel">Pay &#8369;<?= number_format($remaining, 2) ?> Balance</a>
                                    <?php endif; ?>
                                <?php elseif ((int) $apt['dp_paid'] === 0): ?>
                                    <span class="cell-muted">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td class="cell-muted"><?= date("F j, Y g:i A", strtotime($apt['created_at'])) ?></td>
                            <td>
                                <?php if ($can_modify): ?>
                                    <div class="panel-actions no-print">
                                        <a class="btn btn-sm btn-neutral" href="reschedule.php?id=<?= (int) $apt['id'] ?>">Reschedule</a>
                                        <form method="POST" onsubmit="return confirm('Cancel appointment #<?= (int) $apt['id'] ?>? Any downpayment already paid is non-refundable.');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="appointment_id" value="<?= (int) $apt['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Cancel</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <span class="cell-muted">&mdash;</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="field-hint">Appointments can be rescheduled or cancelled until the clinic starts the visit. Already-paid downpayments stay on record; contact the clinic about refunds.</p>
        <?php endif; ?>
    </div>
</div>

<?php require 'footer.php'; ?>
