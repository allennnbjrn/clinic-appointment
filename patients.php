<?php
require 'config.php';
require_role('admin', 'receptionist');

$page_title = 'Patient Directory';

$q = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($q) > 100) {
    $q = mb_substr($q, 0, 100);
}

$sql = "SELECT u.id, u.fullname, u.email, u.phone, u.created_at,
            (SELECT COUNT(*) FROM appointments a
              WHERE a.user_id = u.id AND a.status != 'cancelled') AS visit_count,
            (SELECT MAX(a2.appointment_date) FROM appointments a2
              WHERE a2.user_id = u.id AND a2.status = 'completed') AS last_visit,
            (SELECT COALESCE(SUM(p.amount), 0) FROM payments p
              JOIN appointments a3 ON p.appointment_id = a3.id
              WHERE a3.user_id = u.id AND p.payment_status = 'paid') AS paid_total,
            (SELECT COALESCE(SUM(GREATEST(a4.service_price - COALESCE(
                        (SELECT SUM(p2.amount) FROM payments p2
                          WHERE p2.appointment_id = a4.id AND p2.payment_status = 'paid'), 0), 0)), 0)
               FROM appointments a4
              WHERE a4.user_id = u.id AND a4.status = 'completed') AS outstanding
        FROM users u
        WHERE u.role = 'patient'";

$params = [];
if ($q !== '') {
    $sql .= ' AND (u.fullname LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}
$sql .= ' ORDER BY u.fullname LIMIT 200';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll();

$total_patients = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'patient'")->fetchColumn();
$new_this_month = (int) $pdo->query(
    "SELECT COUNT(*) FROM users WHERE role = 'patient' AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
)->fetchColumn();
$outstanding_total = (float) $pdo->query(
    "SELECT COALESCE(SUM(GREATEST(a.service_price - COALESCE(
                (SELECT SUM(p.amount) FROM payments p
                  WHERE p.appointment_id = a.id AND p.payment_status = 'paid'), 0), 0)), 0)
       FROM appointments a WHERE a.status = 'completed'"
)->fetchColumn();

$back_link = $_SESSION['role'] === 'admin' ? 'admin-dashboard.php' : 'receptionist.php';

require 'header.php';
?>

<div class="page-wrap">
    <div class="page-head">
        <div>
            <h1 class="page-title">Patient Directory</h1>
            <p class="page-sub">Search patients, check visit history, and see who still owes a balance.</p>
        </div>
        <a href="<?= e($back_link) ?>" class="btn btn-outline"><?= $_SESSION['role'] === 'admin' ? 'Admin Dashboard' : 'Reception Panel' ?></a>
    </div>

    <div class="stat-grid">
        <div class="stat-card">
            <strong><?= $total_patients ?></strong>
            <span>Total Patients</span>
        </div>
        <div class="stat-card green">
            <strong><?= $new_this_month ?></strong>
            <span>New This Month</span>
        </div>
        <div class="stat-card amber">
            <strong>&#8369;<?= number_format($outstanding_total, 2) ?></strong>
            <span>Outstanding Balances</span>
        </div>
    </div>

    <div class="panel">
        <h2>Find a Patient</h2>
        <form method="GET" class="panel-actions">
            <input type="search" name="q" class="input" style="max-width:360px;" value="<?= e($q) ?>" placeholder="Name, email, or phone number" aria-label="Search patients">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($q !== ''): ?>
                <a href="patients.php" class="btn btn-neutral">Clear</a>
            <?php endif; ?>
        </form>
        <?php if ($q !== ''): ?>
            <p class="field-hint">Showing <?= count($patients) ?> result<?= count($patients) === 1 ? '' : 's' ?> for &ldquo;<?= e($q) ?>&rdquo;.</p>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h2>Patients</h2>
        <?php if (empty($patients)): ?>
            <div class="empty-state">
                <p><?= $q !== '' ? 'No patients match that search.' : 'No patients registered yet.' ?></p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Phone</th>
                            <th>Visits</th>
                            <th>Last Visit</th>
                            <th>Total Paid</th>
                            <th>Balance Due</th>
                            <th>Registered</th>
                            <th>Record</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($patients as $p): ?>
                        <tr>
                            <td>
                                <div class="cell-strong"><?= e($p['fullname']) ?></div>
                                <div class="cell-muted"><?= e($p['email']) ?></div>
                            </td>
                            <td><?= e($p['phone'] ?? '-') ?></td>
                            <td><?= (int) $p['visit_count'] ?></td>
                            <td><?= $p['last_visit'] ? date('M j, Y', strtotime($p['last_visit'])) : '<span class="cell-muted">&mdash;</span>' ?></td>
                            <td>&#8369;<?= number_format((float) $p['paid_total'], 2) ?></td>
                            <td>
                                <?php if ((float) $p['outstanding'] > 0.009): ?>
                                    <span class="badge badge-pending">&#8369;<?= number_format((float) $p['outstanding'], 2) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-paid">Settled</span>
                                <?php endif; ?>
                            </td>
                            <td class="cell-muted"><?= date('M j, Y', strtotime($p['created_at'])) ?></td>
                            <td><a class="btn btn-sm btn-neutral" href="patient-view.php?id=<?= (int) $p['id'] ?>">Open</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require 'footer.php'; ?>
