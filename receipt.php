<?php
require 'config.php';
require_login();

$payment_id = (int) ($_GET['id'] ?? 0);
$just_paid = isset($_GET['new']);

$stmt = $pdo->prepare(
    "SELECT pay.*, apt.user_id AS patient_id, apt.service, apt.dentist, apt.appointment_date, apt.time_slot,
            u.fullname, u.email, u.phone
     FROM payments pay
     JOIN appointments apt ON pay.appointment_id = apt.id
     JOIN users u ON apt.user_id = u.id
     WHERE pay.id = ? LIMIT 1"
);
$stmt->execute([$payment_id]);
$payment = $stmt->fetch();

$is_owner = $payment && (int) $payment['patient_id'] === (int) $_SESSION['user_id'];
if (!$payment || ($_SESSION['role'] === 'patient' && !$is_owner)) {
    header('Location: payment.php');
    exit;
}

$receipt_no = 'BS-' . date('Y', strtotime($payment['created_at'])) . '-' . str_pad((string) (int) $payment['id'], 6, '0', STR_PAD_LEFT);
$page_title = 'Payment Receipt - BrightSmile Dental';

require 'header.php';
?>

<div class="page-wrap">
    <div class="page-head no-print">
        <div>
            <h1 class="page-title">Payment Receipt</h1>
            <p class="page-sub">Proof of your online payment.</p>
        </div>
        <div class="page-actions">
            <button type="button" class="btn btn-neutral" id="printReceipt">Print Receipt</button>
            <a href="payment.php" class="btn btn-primary">Back to Payments</a>
        </div>
    </div>

    <?php if ($just_paid): ?>
        <div class="alert alert-success no-print">Payment submitted successfully! Here is your receipt.</div>
    <?php endif; ?>

    <div class="receipt-card">
        <div class="receipt-head">
            <div class="receipt-brand">
                <img src="logo-icon.svg" alt="BrightSmile Dental">
                <div>
                    <div class="receipt-clinic">BrightSmile Dental Care</div>
                    <div class="receipt-meta">
                        123 Health Ave, Suite 400<br>
                        (555) 123-4567 &middot; info@brightsmiledental.com
                    </div>
                </div>
            </div>
            <div class="receipt-title">
                <div class="receipt-label">Payment Receipt</div>
                <div class="receipt-no"><?= e($receipt_no) ?></div>
                <span class="badge badge-paid"><?= e(ucfirst($payment['payment_status'])) ?></span>
            </div>
        </div>

        <div class="receipt-grid">
            <div>
                <h3>Billed To</h3>
                <p>
                    <strong><?= e($payment['fullname']) ?></strong><br>
                    <?= e($payment['email']) ?><br>
                    <?= e($payment['phone']) ?>
                </p>
            </div>
            <div>
                <h3>Appointment</h3>
                <p>
                    #<?= (int) $payment['appointment_id'] ?> &middot; <?= e($payment['service']) ?><br>
                    <?= e($payment['dentist']) ?><br>
                    <?= e(date('F j, Y', strtotime($payment['appointment_date']))) ?> &middot; <?= e($payment['time_slot']) ?>
                </p>
            </div>
        </div>

        <table class="receipt-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?= e(ucfirst($payment['payment_type'])) ?> &mdash; <?= e($payment['service']) ?>, appointment #<?= (int) $payment['appointment_id'] ?></td>
                    <td>&#8369;<?= number_format((float) $payment['amount'], 2) ?></td>
                </tr>
            </tbody>
        </table>

        <div class="receipt-summary">
            <div><span>Payment method</span><strong><?= e($payment['payment_method']) ?></strong></div>
            <div><span>Date paid</span><strong><?= e(date('F j, Y g:i A', strtotime($payment['created_at']))) ?></strong></div>
            <div class="receipt-total-row"><span>Total Paid</span><strong class="receipt-total">&#8369;<?= number_format((float) $payment['amount'], 2) ?></strong></div>
        </div>

        <p class="receipt-note">
            This is a system-generated proof of payment for an online transaction and is valid without a signature.
            Thank you for choosing BrightSmile Dental Care.
        </p>
    </div>
</div>

<script>
document.getElementById('printReceipt').addEventListener('click', function () {
    window.print();
});
</script>

<?php require 'footer.php'; ?>
