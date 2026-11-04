<?php
require 'config.php';
require_role('receptionist');

$page_title = 'Receptionist Panel';

require 'header.php';
?>

<div class="page-wrap">
    <div class="page-head">
        <div>
            <h1 class="page-title">Receptionist Panel</h1>
            <p class="page-sub">Welcome, <?= e($_SESSION['fullname'] ?? '') ?>. Manage all daily tasks here.</p>
        </div>
        <a href="index.php" class="btn btn-outline">View Website</a>
    </div>

    <div class="menu-grid">
        <a href="patients.php" class="menu-card">
            <div class="icon">
                <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
            </div>
            <h2>Patient Directory</h2>
            <p>Search patients, review visit history, and check balances.</p>
        </a>
        <a href="all-appointments.php" class="menu-card">
            <div class="icon">
                <svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V9h14v10zM5 7V5h14v2H5z"/></svg>
            </div>
            <h2>Appointment Schedule</h2>
            <p>Confirm, complete, or cancel daily bookings.</p>
        </a>
        <a href="prescriptions.php" class="menu-card">
            <div class="icon">
                <svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
            </div>
            <h2>Prescriptions</h2>
            <p>Create and view patient prescriptions.</p>
        </a>
        <a href="reminders.php" class="menu-card">
            <div class="icon">
                <svg viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.9 2 2 2zm6-6V11c0-3.07-1.63-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5S10.5 3.17 10.5 4v.68C7.64 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>
            </div>
            <h2>Appointment Reminders</h2>
            <p>Email confirmed patients the day before their visit.</p>
        </a>
        <a href="payment.php" class="menu-card">
            <div class="icon">
                <svg viewBox="0 0 24 24"><path d="M19 14V6c0-1.1-.9-2-2-2H3c-1.1 0-2 .9-2 2v8c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zm-9-1c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm13-6v11c0 1.1-.9 2-2 2H4v-2h17V7h2z"/></svg>
            </div>
            <h2>Payments</h2>
            <p>Record and review patient payments.</p>
        </a>
    </div>
</div>

<?php require 'footer.php'; ?>
