<?php
require 'config.php';
require_role('admin', 'receptionist');

$page_title = 'Patient Record';

$patient_id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'patient' LIMIT 1");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch();

if (!$patient) {
    header('Location: patients.php');
    exit;
}

$apt_stmt = $pdo->prepare(
    "SELECT a.*,
            (SELECT COUNT(*) FROM payments p
              WHERE p.appointment_id = a.id AND p.payment_type = 'downpayment' AND p.payment_status = 'paid') AS dp_paid,
            (SELECT COALESCE(SUM(p.amount), 0) FROM payments p
              WHERE p.appointment_id = a.id AND p.payment_status = 'paid') AS paid_total
     FROM appointments a
     WHERE a.user_id = ?
     ORDER BY a.appointment_date DESC, a.id DESC"
);
$apt_stmt->execute([$patient_id]);
$appointments = $apt_stmt->fetchAll();

$pay_stmt = $pdo->prepare(
    "SELECT p.*, a.service
     FROM payments p
     JOIN appointments a ON p.appointment_id = a.id
     WHERE a.user_id = ?
     ORDER BY p.created_at DESC"
);
$pay_stmt->execute([$patient_id]);
$payments = $pay_stmt->fetchAll();

$rx_stmt = $pdo->prepare('SELECT * FROM prescriptions WHERE patient_id = ? ORDER BY created_at DESC');
$rx_stmt->execute([$patient_id]);
$prescriptions = $rx_stmt->fetchAll();

$completed = 0;
$upcoming = 0;
$paid_total = 0.0;
$outstanding = 0.0;
$today = date('Y-m-d');
foreach ($appointments as $a) {
    $paid_total += (float) $a['paid_total'];
    if ($a['status'] === 'completed') {
        $completed++;
        $outstanding += max(0, (float) $a['service_price'] - (float) $a['paid_total']);
    } elseif (in_array($a['status'], ['pending', 'confirmed'], true) && $a['appointment_date'] >= $today) {
        $upcoming++;
    }
}

require 'header.php';
?>

<div class="page-wrap">
    <div class="page-head">
        <div>
            <h1 class="page-title"><?= e($patient['fullname']) ?></h1>
            <p class="page-sub">Patient record and visit history.</p>
        </div>
        <a href="patients.php" class="btn btn-outline">Back to Directory</a>
    </div>

    <div class="stat-grid">
        <div class="stat-card">
            <strong><?= $completed ?></strong>
            <span>Completed Visits</span>
        </div>
        <div class="stat-card amber">
            <strong><?= $upcoming ?></strong>
            <span>Upcoming Visits</span>
        </div>
        <div class="stat-card green">
            <strong>&#8369;<?= number_format($paid_total, 2) ?></strong>
            <span>Total Paid</span>
        </div>
        <div class="stat-card red">
            <strong>&#8369;<?= number_format($outstanding, 2) ?></strong>
            <span>Balance Due</span>
        </div>
    </div>

    <div class="panel">
        <h2>Contact</h2>
        <div class="table-wrap">
            <table class="data-table">
                <tbody>
                    <tr>
                        <td class="cell-strong">Email</td>
                        <td><?= e($patient['email']) ?></td>
                        <td class="cell-strong">Phone</td>
                        <td><?= e($patient['phone'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <td class="cell-strong">Patient ID</td>
                        <td>#<?= (int) $patient['id'] ?></td>
                        <td class="cell-strong">Registered</td>
                        <td><?= date('F j, Y', strtotime($patient['created_at'])) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <h2>Appointments</h2>
        <?php if (empty($appointments)): ?>
            <div class="empty-state"><p>No appointments on record.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Service</th>
                            <th>Dentist</th>
                            <th>Date &amp; Time</th>
                            <th>Status</th>
                            <th>Price</th>
                            <th>Paid</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $a): ?>
                        <?php $balance = max(0, (float) $a['service_price'] - (float) $a['paid_total']); ?>
                        <tr>
                            <td class="cell-muted">#<?= (int) $a['id'] ?></td>
                            <td class="cell-strong"><?= e($a['service']) ?></td>
                            <td><?= e($a['dentist']) ?></td>
                            <td>
                                <?= date('M j, Y', strtotime($a['appointment_date'])) ?>
                                <div class="cell-muted"><?= e($a['time_slot']) ?></div>
                            </td>
                            <td><span class="badge badge-<?= e($a['status']) ?>"><?= e(ucfirst($a['status'])) ?></span></td>
                            <td>&#8369;<?= number_format((float) $a['service_price'], 2) ?></td>
                            <td>&#8369;<?= number_format((float) $a['paid_total'], 2) ?></td>
                            <td>
                                <?php if ($a['status'] === 'cancelled'): ?>
                                    <span class="cell-muted">&mdash;</span>
                                <?php elseif ($balance > 0.009): ?>
                                    &#8369;<?= number_format($balance, 2) ?>
                                <?php else: ?>
                                    <span class="badge badge-paid">Settled</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h2>Payments</h2>
        <?php if (empty($payments)): ?>
            <div class="empty-state"><p>No payments yet.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Service</th>
                            <th>Type</th>
                            <th>Method</th>
                            <th>Amount</th>
                            <th>Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $p): ?>
                        <tr>
                            <td><?= date('M j, Y', strtotime($p['created_at'])) ?></td>
                            <td><?= e($p['service']) ?></td>
                            <td><?= e(ucfirst($p['payment_type'])) ?></td>
                            <td><?= e($p['payment_method']) ?></td>
                            <td class="cell-strong">&#8369;<?= number_format((float) $p['amount'], 2) ?></td>
                            <td><a class="btn btn-sm btn-neutral" href="receipt.php?id=<?= (int) $p['id'] ?>">View</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h2>Prescriptions</h2>
        <?php if (empty($prescriptions)): ?>
            <div class="empty-state"><p>No prescriptions issued.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Doctor</th>
                            <th>Medication</th>
                            <th>Instructions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prescriptions as $rx): ?>
                        <tr>
                            <td><?= date('M j, Y', strtotime($rx['created_at'])) ?></td>
                            <td><?= e($rx['doctor_name']) ?></td>
                            <td class="cell-strong"><?= e($rx['medication']) ?></td>
                            <td class="cell-muted"><?= e($rx['instructions'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require 'footer.php'; ?>
