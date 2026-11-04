<?php
require 'config.php';
require_role('admin');

$page_title = 'Admin Dashboard';

// Get counts for dashboard
$total_patients = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'patient'")->fetchColumn();
$total_appointments = $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn();
$pending_appointments = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending'")->fetchColumn();
$total_payments = $pdo->query("SELECT SUM(amount) FROM payments WHERE payment_status = 'paid'")->fetchColumn() ?? 0;

require 'header.php';
?>

<div class="page-wrap">
    <div class="page-head">
        <div>
            <h1 class="page-title">Admin Dashboard</h1>
            <p class="page-sub">Welcome, <?= e($_SESSION['fullname'] ?? 'Administrator') ?>. Here is your clinic at a glance.</p>
        </div>
        <a href="index.php" class="btn btn-outline">View Website</a>
    </div>

    <div class="stat-grid">
        <div class="stat-card">
            <strong><?= (int) $total_patients ?></strong>
            <span>Total Patients</span>
        </div>
        <div class="stat-card">
            <strong><?= (int) $total_appointments ?></strong>
            <span>Total Appointments</span>
        </div>
        <div class="stat-card amber">
            <strong><?= (int) $pending_appointments ?></strong>
            <span>Pending Approval</span>
        </div>
        <div class="stat-card green">
            <strong>&#8369;<?= number_format((float) $total_payments, 2) ?></strong>
            <span>Total Sales</span>
        </div>
    </div>

    <div class="menu-grid">
        <a href="all-appointments.php" class="menu-card">
            <div class="icon">
                <svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V9h14v10zM5 7V5h14v2H5z"/></svg>
            </div>
            <h2>Manage All Appointments</h2>
            <p>Confirm, complete, or cancel patient bookings.</p>
        </a>
        <a href="patients.php" class="menu-card">
            <div class="icon">
                <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
            </div>
            <h2>Patient Directory</h2>
            <p>Search patients, visit history, and outstanding balances.</p>
        </a>
        <a href="activity-log.php" class="menu-card">
            <div class="icon">
                <svg viewBox="0 0 24 24"><path d="M13 3c-4.97 0-9 4.03-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42C8.27 19.99 10.51 21 13 21c4.97 0 9-4.03 9-9s-4.03-9-9-9zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/></svg>
            </div>
            <h2>Activity Log</h2>
            <p>Audit sign-ins, bookings, payments, and account changes.</p>
        </a>
        <a href="manage-users.php" class="menu-card">
            <div class="icon">
                <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
            </div>
            <h2>Patient &amp; Staff Records</h2>
            <p>Manage user accounts and their roles.</p>
        </a>
        <a href="prescriptions.php" class="menu-card">
            <div class="icon">
                <svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
            </div>
            <h2>Prescriptions</h2>
            <p>Issue and review patient prescriptions.</p>
        </a>
        <a href="payment.php" class="menu-card">
            <div class="icon">
                <svg viewBox="0 0 24 24"><path d="M19 14V6c0-1.1-.9-2-2-2H3c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zm-9-1c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm13-6v11c0 1.1-.9 2-2 2H4v-2h17V7h2z"/></svg>
            </div>
            <h2>Sales &amp; Payments</h2>
            <p>Track clinic revenue and payment records.</p>
        </a>
        <a href="reports.php" class="menu-card">
            <div class="icon">
                <svg viewBox="0 0 24 24"><path d="M3 3h2v18H3V3zm4 10h3v8H7v-8zm5-6h3v14h-3V7zm5 3h3v11h-3V10z"/></svg>
            </div>
            <h2>Reports &amp; Exports</h2>
            <p>Revenue, activity, and CSV downloads by date range.</p>
        </a>
        <a href="reminders.php" class="menu-card">
            <div class="icon">
                <svg viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6V11c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5S10.5 3.17 10.5 4v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
            </div>
            <h2>Appointment Reminders</h2>
            <p>Email confirmed patients the day before their visit.</p>
        </a>
        <a href="email-settings.php" class="menu-card">
            <div class="icon">
                <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
            </div>
            <h2>Email Settings</h2>
            <p>Connect the clinic Gmail account that sends verification codes.</p>
        </a>
        <a href="sms-settings.php" class="menu-card">
            <div class="icon">
                <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12zM7 9h2v2H7zm4 0h2v2h-2zm4 0h2v2h-2z"/></svg>
            </div>
            <h2>SMS Settings</h2>
            <p>Connect the SMS gateway that texts verification codes.</p>
        </a>
    </div>
</div>

<?php require 'footer.php'; ?>
