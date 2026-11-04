<?php
require 'config.php';
require_login();

$page_title = 'Payments';

$allowed_methods = ['GCash', 'PayMaya', 'Card'];
$error = '';
$focus_appointment = (int) ($_GET['appointment'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['role'] === 'patient') {
    verify_csrf();

    $appointment_id = (int) ($_POST['appointment_id'] ?? 0);
    $method = $_POST['payment_method'] ?? '';
    $kind = $_POST['payment_kind'] ?? 'downpayment';

    if (!in_array($method, $allowed_methods, true) || !in_array($kind, ['downpayment', 'balance'], true)) {
        $error = 'Please choose a valid payment method.';
    } else {
        $check = $pdo->prepare('SELECT id, status, service_price FROM appointments WHERE id = ? AND user_id = ? LIMIT 1');
        $check->execute([$appointment_id, $_SESSION['user_id']]);
        $apt = $check->fetch();

        $paid_count = $pdo->prepare(
            "SELECT
                (SELECT COUNT(*) FROM payments WHERE appointment_id = ? AND payment_type = 'downpayment' AND payment_status = 'paid') AS dp_paid,
                (SELECT COUNT(*) FROM payments WHERE appointment_id = ? AND payment_type = 'balance' AND payment_status = 'paid') AS balance_paid,
                (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE appointment_id = ? AND payment_status = 'paid') AS paid_total"
        );
        $paid_count->execute([$appointment_id, $appointment_id, $appointment_id]);
        $totals = $paid_count->fetch();

        $price = $apt ? (float) $apt['service_price'] : 0.0;
        $remaining = max(0, $price - (float) ($totals['paid_total'] ?? 0));

        if (!$apt) {
            $error = 'Please select one of your own appointments.';
        } elseif ($kind === 'downpayment') {
            if ($apt['status'] !== 'confirmed') {
                $error = 'You can pay the downpayment once the clinic confirms your appointment.';
            } elseif ((int) $totals['dp_paid'] > 0) {
                $error = 'The downpayment for this appointment is already paid.';
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO payments (appointment_id, amount, payment_method, payment_type, payment_status)
                     VALUES (?, ?, ?, 'downpayment', 'paid')"
                );
                $stmt->execute([$appointment_id, DOWNPAYMENT_AMOUNT, $method]);
                $payment_id = (int) $pdo->lastInsertId();
                log_activity('downpayment_paid', 'Payment #' . $payment_id . ' for appointment #' . $appointment_id . ' via ' . $method . ' (PHP ' . number_format(DOWNPAYMENT_AMOUNT, 2) . ')');

                header('Location: receipt.php?id=' . $payment_id . '&new=1');
                exit;
            }
        } else {
            if ($apt['status'] !== 'completed') {
                $error = 'The balance can be paid after the clinic marks your visit as completed.';
            } elseif ($price <= 0) {
                $error = 'This appointment has no recorded service price. Please contact the clinic.';
            } elseif ((int) $totals['balance_paid'] > 0 || $remaining <= 0.009) {
                $error = 'This appointment is already fully paid.';
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO payments (appointment_id, amount, payment_method, payment_type, payment_status)
                     VALUES (?, ?, ?, 'balance', 'paid')"
                );
                $balance_due = round($remaining, 2);
                $stmt->execute([$appointment_id, $balance_due, $method]);
                $payment_id = (int) $pdo->lastInsertId();
                log_activity('balance_paid', 'Payment #' . $payment_id . ' for appointment #' . $appointment_id . ' via ' . $method . ' (PHP ' . number_format($balance_due, 2) . ')');

                header('Location: receipt.php?id=' . $payment_id . '&new=1');
                exit;
            }
        }
    }
}

if ($_SESSION['role'] === 'patient') {
    $stmt = $pdo->prepare(
        "SELECT pay.*, apt.appointment_date, apt.service
         FROM payments pay
         JOIN appointments apt ON pay.appointment_id = apt.id
         WHERE apt.user_id = ?
         ORDER BY pay.created_at DESC"
    );
    $stmt->execute([$_SESSION['user_id']]);
    $payments = $stmt->fetchAll();

    $apt_stmt = $pdo->prepare(
        "SELECT a.id, a.appointment_date, a.time_slot, a.service, a.dentist, a.service_price,
                (SELECT COUNT(*) FROM payments p
                  WHERE p.appointment_id = a.id AND p.payment_type = 'downpayment' AND p.payment_status = 'paid') AS dp_paid
         FROM appointments a
         WHERE a.user_id = ? AND a.status = 'confirmed'
         ORDER BY a.appointment_date"
    );
    $apt_stmt->execute([$_SESSION['user_id']]);
    $confirmed_appointments = $apt_stmt->fetchAll();

    $unpaid_appointments = array_values(array_filter($confirmed_appointments, fn ($a) => (int) $a['dp_paid'] === 0));

    // Completed visits: what is still owed after the downpayment (and any earlier payments).
    $bal_stmt = $pdo->prepare(
        "SELECT a.id, a.appointment_date, a.time_slot, a.service, a.dentist, a.service_price,
                (SELECT COALESCE(SUM(p.amount), 0) FROM payments p
                  WHERE p.appointment_id = a.id AND p.payment_status = 'paid') AS paid_total,
                (SELECT COUNT(*) FROM payments p
                  WHERE p.appointment_id = a.id AND p.payment_type = 'balance' AND p.payment_status = 'paid') AS balance_paid
         FROM appointments a
         WHERE a.user_id = ? AND a.status = 'completed' AND a.service_price > 0
         ORDER BY a.appointment_date DESC"
    );
    $bal_stmt->execute([$_SESSION['user_id']]);
    $completed_appointments = $bal_stmt->fetchAll();
    foreach ($completed_appointments as &$apt) {
        $apt['remaining'] = max(0, (float) $apt['service_price'] - (float) $apt['paid_total']);
    }
    unset($apt);
    $balance_due_appointments = array_values(array_filter(
        $completed_appointments,
        fn ($a) => (int) $a['balance_paid'] === 0 && $a['remaining'] > 0.009
    ));

    $pend_stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE user_id = ? AND status = 'pending'");
    $pend_stmt->execute([$_SESSION['user_id']]);
    $pending_count = (int) $pend_stmt->fetchColumn();
} else {
    $payments = $pdo->query(
        "SELECT pay.*, apt.appointment_date, u.fullname AS patient_name
         FROM payments pay
         JOIN appointments apt ON pay.appointment_id = apt.id
         JOIN users u ON apt.user_id = u.id
         ORDER BY pay.created_at DESC"
    )->fetchAll();
    $total_sales = (float) ($pdo->query("SELECT SUM(amount) FROM payments WHERE payment_status = 'paid'")->fetchColumn() ?? 0);
}

require 'header.php';
?>

<div class="page-wrap">
    <div class="page-head">
        <div>
            <h1 class="page-title">Payments</h1>
            <p class="page-sub"><?= $_SESSION['role'] === 'patient' ? 'Pay your downpayment after confirmation and the balance after your visit — GCash, PayMaya, or Card.' : 'Clinic sales and payment records.' ?></p>
        </div>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($_SESSION['role'] === 'patient'): ?>
        <div class="panel">
            <h2>Downpayment</h2>
            <p class="cell-muted">A &#8369;<?= number_format(DOWNPAYMENT_AMOUNT, 2) ?> downpayment secures your slot once the clinic confirms your booking. After the visit is marked completed, the remaining balance can also be paid here.</p>

            <?php if (empty($confirmed_appointments)): ?>
                <div class="empty-state">
                    <?php if ($pending_count > 0): ?>
                        <p>Your booking is waiting for clinic confirmation. You can pay the downpayment as soon as it is confirmed.</p>
                        <a href="appointment-history.php" class="btn btn-outline">View my appointments</a>
                    <?php else: ?>
                        <p>You have no confirmed appointments yet.</p>
                        <a href="book.appointment.php" class="btn btn-primary">Book an appointment</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Appointment</th>
                                <th>Date &amp; Time</th>
                                <th>Dentist</th>
                                <th>Service Price</th>
                                <th>Downpayment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($confirmed_appointments as $apt): ?>
                            <tr>
                                <td class="cell-strong">#<?= (int) $apt['id'] ?> &mdash; <?= e($apt['service']) ?></td>
                                <td>
                                    <?= date("M j, Y", strtotime($apt['appointment_date'])) ?>
                                    <div class="cell-muted"><?= e($apt['time_slot']) ?></div>
                                </td>
                                <td><?= e($apt['dentist']) ?></td>
                                <td><?= (float) $apt['service_price'] > 0 ? '&#8369;' . number_format((float) $apt['service_price'], 2) : '&mdash;' ?></td>
                                <td>
                                    <?php if ((int) $apt['dp_paid'] > 0): ?>
                                        <span class="badge badge-paid">Paid</span>
                                    <?php else: ?>
                                        <span class="badge badge-pending">Unpaid</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!empty($unpaid_appointments)): ?>
                    <form method="POST" class="panel-actions">
                        <?= csrf_field() ?>
                        <input type="hidden" name="payment_kind" value="downpayment">
                        <div class="form-grid-3">
                            <div class="field">
                                <label for="appointment_id">Appointment to pay</label>
                                <select name="appointment_id" id="appointment_id" class="select" required>
                                    <?php foreach ($unpaid_appointments as $apt): ?>
                                        <option value="<?= (int) $apt['id'] ?>" <?= $focus_appointment === (int) $apt['id'] ? 'selected' : '' ?>>
                                            #<?= (int) $apt['id'] ?> &mdash; <?= e($apt['service']) ?> (<?= date("M j, Y", strtotime($apt['appointment_date'])) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label for="amount_display">Downpayment (&#8369;)</label>
                                <input id="amount_display" type="text" class="input" value="<?= number_format(DOWNPAYMENT_AMOUNT, 2) ?>" readonly>
                            </div>
                            <div class="field">
                                <label>Payment Method</label>
                                <div class="pay-methods">
                                    <label class="pay-chip">
                                        <input type="radio" name="payment_method" value="GCash" required>
                                        <span class="brand brand-gcash">G</span>
                                        GCash
                                    </label>
                                    <label class="pay-chip">
                                        <input type="radio" name="payment_method" value="PayMaya" required>
                                        <span class="brand brand-maya">M</span>
                                        PayMaya
                                    </label>
                                    <label class="pay-chip">
                                        <input type="radio" name="payment_method" value="Card" required>
                                        <span class="brand brand-card"><svg viewBox="0 0 24 24"><path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg></span>
                                        Card
                                    </label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Pay &#8369;<?= number_format(DOWNPAYMENT_AMOUNT, 2) ?> Downpayment</button>
                    </form>
                <?php else: ?>
                    <p class="cell-muted">All of your confirmed appointments have the downpayment paid.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <div class="panel" id="balance-panel">
            <h2>Balance</h2>
            <p class="cell-muted">Once the clinic marks your visit as completed, settle the remaining balance here. Your service price is recorded at booking, so the amount is fixed.</p>

            <?php if (empty($completed_appointments)): ?>
                <div class="empty-state"><p>No completed visits yet. The balance appears here after your appointment.</p></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Appointment</th>
                                <th>Visit Date</th>
                                <th>Service Price</th>
                                <th>Paid</th>
                                <th>Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($completed_appointments as $apt): ?>
                            <tr>
                                <td class="cell-strong">#<?= (int) $apt['id'] ?> &mdash; <?= e($apt['service']) ?></td>
                                <td>
                                    <?= date("M j, Y", strtotime($apt['appointment_date'])) ?>
                                    <div class="cell-muted"><?= e($apt['time_slot']) ?></div>
                                </td>
                                <td>&#8369;<?= number_format((float) $apt['service_price'], 2) ?></td>
                                <td>&#8369;<?= number_format((float) $apt['paid_total'], 2) ?></td>
                                <td>
                                    <?php if ((int) $apt['balance_paid'] > 0 || $apt['remaining'] <= 0.009): ?>
                                        <span class="badge badge-paid">Settled</span>
                                    <?php else: ?>
                                        <span class="badge badge-pending">&#8369;<?= number_format($apt['remaining'], 2) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!empty($balance_due_appointments)): ?>
                    <form method="POST" class="panel-actions" id="balanceForm">
                        <?= csrf_field() ?>
                        <input type="hidden" name="payment_kind" value="balance">
                        <div class="form-grid-3">
                            <div class="field">
                                <label for="balance_appointment">Appointment to settle</label>
                                <select name="appointment_id" id="balance_appointment" class="select" required>
                                    <?php foreach ($balance_due_appointments as $apt): ?>
                                        <option value="<?= (int) $apt['id'] ?>" data-remaining="<?= number_format($apt['remaining'], 2, '.', '') ?>" <?= $focus_appointment === (int) $apt['id'] ? 'selected' : '' ?>>
                                            #<?= (int) $apt['id'] ?> &mdash; <?= e($apt['service']) ?> (<?= date("M j, Y", strtotime($apt['appointment_date'])) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label for="balance_amount">Balance due (&#8369;)</label>
                                <input id="balance_amount" type="text" class="input" value="<?= number_format($balance_due_appointments[0]['remaining'], 2) ?>" readonly>
                            </div>
                            <div class="field">
                                <label>Payment Method</label>
                                <div class="pay-methods">
                                    <label class="pay-chip">
                                        <input type="radio" name="payment_method" value="GCash" required>
                                        <span class="brand brand-gcash">G</span>
                                        GCash
                                    </label>
                                    <label class="pay-chip">
                                        <input type="radio" name="payment_method" value="PayMaya" required>
                                        <span class="brand brand-maya">M</span>
                                        PayMaya
                                    </label>
                                    <label class="pay-chip">
                                        <input type="radio" name="payment_method" value="Card" required>
                                        <span class="brand brand-card"><svg viewBox="0 0 24 24"><path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg></span>
                                        Card
                                    </label>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Pay Remaining Balance</button>
                    </form>
                <?php else: ?>
                    <p class="cell-muted">All completed visits are fully paid. Thank you!</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="stat-grid">
            <div class="stat-card green">
                <strong>&#8369;<?= number_format($total_sales, 2) ?></strong>
                <span>Total Sales</span>
            </div>
            <div class="stat-card">
                <strong><?= count($payments) ?></strong>
                <span>Payment Records</span>
            </div>
        </div>
    <?php endif; ?>

    <div class="panel">
        <h2>Payment History</h2>
        <?php if (empty($payments)): ?>
            <div class="empty-state"><p>No payment records found.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <?php if ($_SESSION['role'] !== 'patient'): ?><th>Patient</th><?php endif; ?>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Type</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $pay): ?>
                        <tr>
                            <?php if ($_SESSION['role'] !== 'patient'): ?><td class="cell-strong"><?= e($pay['patient_name']) ?></td><?php endif; ?>
                            <td><?= date("F j, Y", strtotime($pay['created_at'])) ?></td>
                            <td class="cell-strong">&#8369;<?= number_format((float) $pay['amount'], 2) ?></td>
                            <td><?= e(ucfirst($pay['payment_type'])) ?></td>
                            <td><?= e($pay['payment_method']) ?></td>
                            <td><span class="badge badge-paid"><?= e(ucfirst($pay['payment_status'])) ?></span></td>
                            <td><a class="btn btn-sm btn-neutral" href="receipt.php?id=<?= (int) $pay['id'] ?>">View</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.querySelectorAll('.pay-chip input').forEach(function (input) {
    input.addEventListener('change', function () {
        document.querySelectorAll('.pay-chip').forEach(function (chip) {
            chip.classList.toggle('selected', chip.querySelector('input').checked);
        });
    });
});

(function () {
    var select = document.getElementById('balance_appointment');
    var amount = document.getElementById('balance_amount');
    if (!select || !amount) return;
    var update = function () {
        var opt = select.options[select.selectedIndex];
        var value = parseFloat(opt.dataset.remaining || '0');
        amount.value = value.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };
    select.addEventListener('change', update);
    update();
})();
</script>

<?php require 'footer.php'; ?>
