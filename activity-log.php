<?php
require 'config.php';
require_role('admin');
activity_table_ensure();

$page_title = 'Activity Log';

$labels = [
    'signed_in' => 'Signed in',
    'booking_created' => 'Appointment booked',
    'appointment_confirmed' => 'Appointment confirmed',
    'appointment_cancelled' => 'Appointment cancelled',
    'appointment_completed' => 'Appointment completed',
    'appointment_reopened' => 'Appointment re-opened',
    'appointment_cancelled_by_patient' => 'Cancelled by patient',
    'appointment_rescheduled' => 'Appointment rescheduled',
    'downpayment_paid' => 'Downpayment paid',
    'balance_paid' => 'Balance paid',
    'prescription_issued' => 'Prescription issued',
    'staff_account_created' => 'Staff account created',
    'user_role_changed' => 'Role changed',
    'user_deleted' => 'User deleted',
    'profile_updated' => 'Profile updated',
    'password_changed' => 'Password changed',
    'reminders_sent' => 'Reminders sent',
    'export_generated' => 'Export downloaded',
];

$action = (string) ($_GET['action'] ?? '');
$q = trim((string) ($_GET['q'] ?? ''));
$from = (string) ($_GET['from'] ?? '');
$to = (string) ($_GET['to'] ?? '');

if (!isset($labels[$action])) {
    $action = '';
}
if (mb_strlen($q) > 100) {
    $q = mb_substr($q, 0, 100);
}
foreach (['from', 'to'] as $key) {
    if ($$key !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $$key)) {
        $$key = '';
    }
}

$where = [];
$params = [];
if ($action !== '') {
    $where[] = 'action = ?';
    $params[] = $action;
}
if ($q !== '') {
    $where[] = '(actor_name LIKE ? OR details LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($from !== '') {
    $where[] = 'created_at >= ?';
    $params[] = $from . ' 00:00:00';
}
if ($to !== '') {
    $where[] = 'created_at <= ?';
    $params[] = $to . ' 23:59:59';
}

$sql = 'SELECT * FROM activity_log' . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
    . ' ORDER BY created_at DESC, id DESC LIMIT 300';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$entries = $stmt->fetchAll();

$today_count = (int) $pdo->query('SELECT COUNT(*) FROM activity_log WHERE created_at >= CURDATE()')->fetchColumn();
$week_count = (int) $pdo->query('SELECT COUNT(*) FROM activity_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')->fetchColumn();
$total_count = (int) $pdo->query('SELECT COUNT(*) FROM activity_log')->fetchColumn();

require 'header.php';
?>

<div class="page-wrap">
    <div class="page-head">
        <div>
            <h1 class="page-title">Activity Log</h1>
            <p class="page-sub">Who did what, and when. Newest 300 matching events are shown.</p>
        </div>
        <a href="admin-dashboard.php" class="btn btn-outline">Admin Dashboard</a>
    </div>

    <div class="stat-grid">
        <div class="stat-card">
            <strong><?= $today_count ?></strong>
            <span>Events Today</span>
        </div>
        <div class="stat-card amber">
            <strong><?= $week_count ?></strong>
            <span>Last 7 Days</span>
        </div>
        <div class="stat-card green">
            <strong><?= $total_count ?></strong>
            <span>All Events</span>
        </div>
    </div>

    <div class="panel">
        <h2>Filter</h2>
        <form method="GET">
            <div class="form-grid-3">
                <div class="field">
                    <label for="filter_action">Action</label>
                    <select name="action" id="filter_action" class="select">
                        <option value="">All actions</option>
                        <?php foreach ($labels as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= $action === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="filter_q">Search</label>
                    <input type="search" name="q" id="filter_q" class="input" value="<?= e($q) ?>" placeholder="Actor or details">
                </div>
                <div class="field">
                    <label for="filter_from">From</label>
                    <input type="date" name="from" id="filter_from" class="input" value="<?= e($from) ?>">
                </div>
                <div class="field">
                    <label for="filter_to">To</label>
                    <input type="date" name="to" id="filter_to" class="input" value="<?= e($to) ?>">
                </div>
            </div>
            <div class="panel-actions">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="activity-log.php" class="btn btn-neutral">Clear</a>
            </div>
        </form>
    </div>

    <div class="panel">
        <h2>Events</h2>
        <?php if (empty($entries)): ?>
            <div class="empty-state"><p>No activity matches these filters yet.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Actor</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $row): ?>
                        <tr>
                            <td class="cell-muted"><?= date('M j, Y g:i A', strtotime($row['created_at'])) ?></td>
                            <td>
                                <div class="cell-strong"><?= e($row['actor_name']) ?></div>
                                <?php if ($row['actor_role'] !== ''): ?>
                                    <span class="badge badge-role-<?= e($row['actor_role']) ?>"><?= e(ucfirst($row['actor_role'])) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><span class="cell-strong"><?= e($labels[$row['action']] ?? $row['action']) ?></span></td>
                            <td class="cell-muted"><?= e($row['details']) ?></td>
                            <td class="cell-muted"><?= e($row['ip']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require 'footer.php'; ?>
