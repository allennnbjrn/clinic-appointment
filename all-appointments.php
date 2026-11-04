<?php
require 'config.php';
require_role('admin', 'receptionist');

$page_title = 'Manage All Appointments';

$allowed_statuses = ['pending', 'confirmed', 'completed', 'cancelled'];
$flash = '';
$flash_error = '';
$back_link = $_SESSION['role'] === 'admin' ? 'admin-dashboard.php' : 'receptionist.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $appointment_id = (int) ($_POST['appointment_id'] ?? 0);
    $new_status = $_POST['status'] ?? '';

    if ($appointment_id <= 0 || !in_array($new_status, $allowed_statuses, true)) {
        $flash_error = 'Invalid update request.';
    } else {
        $cur = $pdo->prepare(
            'SELECT a.status, u.fullname
             FROM appointments a JOIN users u ON a.user_id = u.id
             WHERE a.id = ? LIMIT 1'
        );
        $cur->execute([$appointment_id]);
        $target = $cur->fetch();

        if (!$target) {
            $flash_error = 'Appointment not found.';
        } else {
            $stmt = $pdo->prepare('UPDATE appointments SET status = ? WHERE id = ?');
            $stmt->execute([$new_status, $appointment_id]);

            if ($new_status !== $target['status']) {
                $event = in_array($new_status, ['confirmed', 'cancelled', 'completed'], true) ? $new_status : '';
                if ($event !== '') {
                    appointment_notify($appointment_id, $event, $event === 'cancelled' ? ['by' => 'clinic'] : []);
                    log_activity('appointment_' . $event, '#' . $appointment_id . ' ' . $target['fullname'] . ': ' . $target['status'] . ' -> ' . $new_status);
                } else {
                    log_activity('appointment_reopened', '#' . $appointment_id . ' ' . $target['fullname'] . ': ' . $target['status'] . ' -> ' . $new_status);
                }
            }

            $flash = 'Appointment #' . $appointment_id . ' marked as ' . $new_status . '.';
        }
    }
}

$stmt = $pdo->query(
    "SELECT a.*, u.fullname AS patient_name, u.phone AS patient_phone,
            (SELECT COUNT(*) FROM payments p
              WHERE p.appointment_id = a.id AND p.payment_type = 'downpayment' AND p.payment_status = 'paid') AS dp_paid
     FROM appointments a
     JOIN users u ON a.user_id = u.id
     ORDER BY FIELD(a.status, 'pending', 'confirmed', 'completed', 'cancelled'), a.appointment_date, a.id DESC"
);
$appointments = $stmt->fetchAll();

$counts = ['pending' => 0, 'confirmed' => 0, 'completed' => 0, 'cancelled' => 0];
foreach ($appointments as $a) {
    $counts[$a['status']] = ($counts[$a['status']] ?? 0) + 1;
}

require 'header.php';
?>

<div class="page-wrap">
    <div class="page-head">
        <div>
            <h1 class="page-title">Manage All Appointments</h1>
            <p class="page-sub">Review and update every booking in the clinic.</p>
        </div>
        <a href="<?= e($back_link) ?>" class="btn btn-outline"><?= $_SESSION['role'] === 'admin' ? 'Admin Dashboard' : 'Reception Panel' ?></a>
    </div>

    <?php if ($flash !== ''): ?>
        <div class="alert alert-success"><?= e($flash) ?></div>
    <?php elseif ($flash_error !== ''): ?>
        <div class="alert alert-error" role="alert"><?= e($flash_error) ?></div>
    <?php endif; ?>

    <div class="stat-grid">
        <div class="stat-card amber">
            <strong><?= (int) $counts['pending'] ?></strong>
            <span>Pending</span>
        </div>
        <div class="stat-card">
            <strong><?= (int) $counts['confirmed'] ?></strong>
            <span>Confirmed</span>
        </div>
        <div class="stat-card green">
            <strong><?= (int) $counts['completed'] ?></strong>
            <span>Completed</span>
        </div>
        <div class="stat-card red">
            <strong><?= (int) $counts['cancelled'] ?></strong>
            <span>Cancelled</span>
        </div>
    </div>

    <?php if (empty($appointments)): ?>
        <div class="panel">
            <div class="empty-state"><p>No appointments booked yet.</p></div>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Patient</th>
                        <th>Service</th>
                        <th>Dentist</th>
                        <th>Date &amp; Time</th>
                        <th>Notes</th>
                        <th>Status</th>
                        <th>Downpayment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $apt): ?>
                    <tr>
                        <td class="cell-muted">#<?= (int) $apt['id'] ?></td>
                        <td>
                            <div class="cell-strong"><?= e($apt['patient_name']) ?></div>
                            <div class="cell-muted"><?= e($apt['patient_phone'] ?? '') ?></div>
                        </td>
                        <td><?= e($apt['service']) ?></td>
                        <td><?= e($apt['dentist']) ?></td>
                        <td>
                            <?= date("M d, Y", strtotime($apt['appointment_date'])) ?>
                            <div class="cell-muted"><?= e($apt['time_slot']) ?></div>
                        </td>
                        <td class="cell-muted"><?= e($apt['notes'] ?? '-') ?></td>
                        <td><span class="badge badge-<?= e($apt['status']) ?>"><?= e(ucfirst($apt['status'])) ?></span></td>
                        <td>
                            <?php if ((int) $apt['dp_paid'] > 0): ?>
                                <span class="badge badge-paid">Paid</span>
                            <?php elseif ($apt['status'] === 'confirmed'): ?>
                                <span class="badge badge-pending">Unpaid</span>
                            <?php else: ?>
                                <span class="cell-muted">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" class="panel-actions no-print">
                                <?= csrf_field() ?>
                                <input type="hidden" name="appointment_id" value="<?= (int) $apt['id'] ?>">
                                <?php if ($apt['status'] === 'pending'): ?>
                                    <button type="submit" name="status" value="confirmed" class="btn btn-sm btn-primary">Confirm</button>
                                    <button type="submit" name="status" value="cancelled" class="btn btn-sm btn-danger">Cancel</button>
                                <?php elseif ($apt['status'] === 'confirmed'): ?>
                                    <button type="submit" name="status" value="completed" class="btn btn-sm btn-success">Complete</button>
                                    <button type="submit" name="status" value="cancelled" class="btn btn-sm btn-danger">Cancel</button>
                                <?php else: ?>
                                    <button type="submit" name="status" value="pending" class="btn btn-sm btn-neutral">Re-open</button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require 'footer.php'; ?>
