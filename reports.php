<?php
require 'config.php';
require_role('admin');

$page_title = 'Clinic Reports';

$today = date('Y-m-d');
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? $today;

$from_ok = DateTime::createFromFormat('Y-m-d', (string) $from);
$to_ok = DateTime::createFromFormat('Y-m-d', (string) $to);
if (!$from_ok || $from_ok->format('Y-m-d') !== $from) {
    $from = date('Y-m-01');
}
if (!$to_ok || $to_ok->format('Y-m-d') !== $to) {
    $to = $today;
}
if ($from > $to) {
    [$from, $to] = [$to, $from];
}

$range = [$from, $to];

$revenue_stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(amount), 0) FROM payments
     WHERE payment_status = 'paid' AND DATE(created_at) BETWEEN ? AND ?"
);
$revenue_stmt->execute($range);
$revenue = (float) $revenue_stmt->fetchColumn();

$by_type_stmt = $pdo->prepare(
    "SELECT payment_type, COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total
     FROM payments
     WHERE payment_status = 'paid' AND DATE(created_at) BETWEEN ? AND ?
     GROUP BY payment_type"
);
$by_type_stmt->execute($range);
$by_type = ['downpayment' => ['cnt' => 0, 'total' => 0], 'balance' => ['cnt' => 0, 'total' => 0], 'full' => ['cnt' => 0, 'total' => 0]];
foreach ($by_type_stmt->fetchAll() as $row) {
    $by_type[$row['payment_type']] = ['cnt' => (int) $row['cnt'], 'total' => (float) $row['total']];
}

$appt_stmt = $pdo->prepare(
    "SELECT status, COUNT(*) AS cnt FROM appointments
     WHERE appointment_date BETWEEN ? AND ? GROUP BY status"
);
$appt_stmt->execute($range);
$status_counts = ['pending' => 0, 'confirmed' => 0, 'completed' => 0, 'cancelled' => 0];
foreach ($appt_stmt->fetchAll() as $row) {
    $status_counts[$row['status']] = (int) $row['cnt'];
}
$appt_total = array_sum($status_counts);

$new_patients_stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM users WHERE role = 'patient' AND DATE(created_at) BETWEEN ? AND ?"
);
$new_patients_stmt->execute($range);
$new_patients = (int) $new_patients_stmt->fetchColumn();

$services_stmt = $pdo->prepare(
    "SELECT a.service, COUNT(DISTINCT a.id) AS visits, COALESCE(SUM(CASE WHEN p.payment_status = 'paid' THEN p.amount ELSE 0 END), 0) AS paid
     FROM appointments a
     LEFT JOIN payments p ON p.appointment_id = a.id
     WHERE a.appointment_date BETWEEN ? AND ?
     GROUP BY a.service
     ORDER BY visits DESC, a.service"
);
$services_stmt->execute($range);
$services = $services_stmt->fetchAll();

$dentists_stmt = $pdo->prepare(
    "SELECT dentist, COUNT(*) AS visits,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed
     FROM appointments
     WHERE appointment_date BETWEEN ? AND ?
     GROUP BY dentist
     ORDER BY visits DESC"
);
$dentists_stmt->execute($range);
$dentists = $dentists_stmt->fetchAll();

$daily_stmt = $pdo->prepare(
    "SELECT DATE(created_at) AS day, COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS total
     FROM payments
     WHERE payment_status = 'paid' AND DATE(created_at) BETWEEN ? AND ?
     GROUP BY DATE(created_at)
     ORDER BY day DESC"
);
$daily_stmt->execute($range);
$daily = $daily_stmt->fetchAll();

$export_qs = 'from=' . urlencode($from) . '&to=' . urlencode($to);

require 'header.php';
?>

<div class="page-wrap">
    <div class="page-head">
        <div>
            <h1 class="page-title">Clinic Reports</h1>
            <p class="page-sub">Revenue and appointment activity from <?= date('M j, Y', strtotime($from)) ?> to <?= date('M j, Y', strtotime($to)) ?>.</p>
        </div>
        <div class="page-actions no-print">
            <button type="button" class="btn btn-neutral" id="printReport">Print</button>
            <a class="btn btn-outline" href="export.php?type=appointments&amp;<?= e($export_qs) ?>">Export Appointments CSV</a>
            <a class="btn btn-outline" href="export.php?type=payments&amp;<?= e($export_qs) ?>">Export Payments CSV</a>
        </div>
    </div>

    <div class="panel no-print">
        <form method="GET" class="panel-actions">
            <div class="form-grid">
                <div class="field">
                    <label for="from">From</label>
                    <input type="date" id="from" name="from" class="input" value="<?= e($from) ?>" max="<?= e($today) ?>">
                </div>
                <div class="field">
                    <label for="to">To</label>
                    <input type="date" id="to" name="to" class="input" value="<?= e($to) ?>" max="<?= e($today) ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Apply Range</button>
        </form>
    </div>

    <div class="stat-grid">
        <div class="stat-card green">
            <strong>&#8369;<?= number_format($revenue, 2) ?></strong>
            <span>Revenue Collected</span>
        </div>
        <div class="stat-card">
            <strong><?= $appt_total ?></strong>
            <span>Appointments in Range</span>
        </div>
        <div class="stat-card amber">
            <strong><?= $status_counts['pending'] + $status_counts['confirmed'] ?></strong>
            <span>Still Upcoming</span>
        </div>
        <div class="stat-card">
            <strong><?= $new_patients ?></strong>
            <span>New Patients</span>
        </div>
    </div>

    <div class="grid-2">
        <div class="panel">
            <h2>Revenue Breakdown</h2>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Type</th><th>Payments</th><th>Amount</th></tr></thead>
                    <tbody>
                        <?php foreach (['downpayment' => 'Downpayments', 'balance' => 'Balances', 'full' => 'Full payments'] as $key => $label): ?>
                        <tr>
                            <td><?= e($label) ?></td>
                            <td><?= (int) $by_type[$key]['cnt'] ?></td>
                            <td class="cell-strong">&#8369;<?= number_format($by_type[$key]['total'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td class="cell-strong">Total</td>
                            <td class="cell-strong"><?= (int) ($by_type['downpayment']['cnt'] + $by_type['balance']['cnt'] + $by_type['full']['cnt']) ?></td>
                            <td class="cell-strong">&#8369;<?= number_format($revenue, 2) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <h2 style="margin-top:24px;">Appointments by Status</h2>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Status</th><th>Count</th></tr></thead>
                    <tbody>
                        <?php foreach ($status_counts as $status => $count): ?>
                        <tr>
                            <td><span class="badge badge-<?= e($status) ?>"><?= e(ucfirst($status)) ?></span></td>
                            <td><?= (int) $count ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel">
            <h2>Daily Revenue</h2>
            <?php if (empty($daily)): ?>
                <div class="empty-state"><p>No payments in this range.</p></div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Date</th><th>Payments</th><th>Amount</th></tr></thead>
                        <tbody>
                            <?php foreach ($daily as $row): ?>
                            <tr>
                                <td><?= date('M j, Y', strtotime($row['day'])) ?></td>
                                <td><?= (int) $row['cnt'] ?></td>
                                <td class="cell-strong">&#8369;<?= number_format((float) $row['total'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel">
        <h2>Services</h2>
        <?php if (empty($services)): ?>
            <div class="empty-state"><p>No appointments in this range.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Service</th><th>Visits</th><th>Payments Collected</th></tr></thead>
                    <tbody>
                        <?php foreach ($services as $row): ?>
                        <tr>
                            <td><?= e($row['service']) ?></td>
                            <td><?= (int) $row['visits'] ?></td>
                            <td class="cell-strong">&#8369;<?= number_format((float) $row['paid'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h2>Dentists</h2>
        <?php if (empty($dentists)): ?>
            <div class="empty-state"><p>No appointments in this range.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Dentist</th><th>Bookings</th><th>Completed</th></tr></thead>
                    <tbody>
                        <?php foreach ($dentists as $row): ?>
                        <tr>
                            <td><?= e(trim($row['dentist'])) ?></td>
                            <td><?= (int) $row['visits'] ?></td>
                            <td><?= (int) $row['completed'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('printReport').addEventListener('click', function () {
    window.print();
});
</script>

<?php require 'footer.php'; ?>
