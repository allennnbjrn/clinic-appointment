<?php
require_once 'config.php';
$page_title = $page_title ?? 'BrightSmile Dental | Appointment System';
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? null;
$logged_in = isset($_SESSION['user_id']);

$nav_links = [
    'index.php'    => 'Home',
    'services.php' => 'Services',
    'about.php'    => 'About',
    'doctors.php'  => 'Doctors',
    'careers.php'  => 'Careers',
    'contact.php'  => 'Contact',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?></title>
    <link rel="icon" type="image/svg+xml" href="logo-icon.svg">
    <link rel="stylesheet" href="style.css?v=<?= (int) filemtime(__DIR__ . '/style.css') ?>">
</head>
<body>

<!-- 1. Top Utility Info Bar -->
<div class="top-info-bar">
    <div class="container top-info-container">
        <div class="info-group">
            <div class="info-item hide-md">
                <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                <span>123 Health Ave, Suite 400</span>
            </div>
            <div class="info-item hide-md">
                <svg viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
                <span>Mon–Fri: 10:00 AM – 7:00 PM</span>
            </div>
        </div>
        <div class="info-group">
            <div class="info-item">
                <svg viewBox="0 0 24 24"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>
                <a href="tel:5551234567">(555) 123-4567</a>
            </div>
            <div class="info-item hide-sm">
                <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                <a href="mailto:info@brightsmiledental.com">info@brightsmiledental.com</a>
            </div>
        </div>
    </div>
</div>

<!-- 2. Main Sticky Header / Top Bar -->
<header class="main-navbar" id="mainNavbar">
    <div class="container navbar-container">

        <a href="index.php" class="brand-logo">
            <div class="logo-icon-wrap">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2C8.5 2 5.5 3.5 5 7c-.5 3.5.5 8 2 12 .8 2.1 2.2 3 3.5 3 1.2 0 1.8-.8 2.5-.8s1.3.8 2.5.8c1.3 0 2.7-.9 3.5-3 1.5-4 2.5-8.5 2-12-.5-3.5-3.5-5-7-5zm-3 5c0-.55.45-1 1-1s1 .45 1 1-.45 1-1 1-1-.45-1-1zm3 10c-1.5 0-2.5-1-2.5-1s.8-1 2.5-1 2.5 1 2.5 1-1 1-2.5 1zm3-8c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1z"/>
                </svg>
            </div>
            <div class="logo-text">
                <span class="logo-title">BrightSmile</span>
                <span class="logo-subtitle">Dental Care</span>
            </div>
        </a>

        <button class="nav-toggle" id="navToggle" type="button" aria-label="Toggle navigation" aria-expanded="false" aria-controls="navCollapse">
            <span></span><span></span><span></span>
        </button>

        <div class="nav-collapse" id="navCollapse">
            <ul class="nav-menu">
                <?php foreach ($nav_links as $href => $label): ?>
                    <li><a href="<?= e($href) ?>" class="nav-link<?= $current_page === $href ? ' active' : '' ?>"><?= e($label) ?></a></li>
                <?php endforeach; ?>
            </ul>

            <div class="nav-actions">
                <?php if ($logged_in): ?>
                    <a href="account.php" class="user-greeting" title="Open my account">
                        <span>👋 Hi, <?= e($_SESSION['fullname'] ?? 'User') ?></span>
                    </a>
                    <?php if ($role === 'patient'): ?>
                        <a href="my.appointments.php" class="btn-outline">My Appointments</a>
                        <a href="payment.php" class="btn-outline">Payments</a>
                        <a href="book.appointment.php" class="btn-book">Book Appointment</a>
                    <?php elseif ($role === 'admin'): ?>
                        <a href="admin-dashboard.php" class="btn-book">Admin Dashboard</a>
                    <?php elseif ($role === 'receptionist'): ?>
                        <a href="receptionist.php" class="btn-book">Reception Panel</a>
                    <?php endif; ?>
                    <a href="logout.php" class="link-logout">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="btn-outline">Login</a>
                    <a href="register.php" class="btn-book">Register</a>
                <?php endif; ?>
            </div>
        </div>

    </div>
</header>

<script>
(function () {
    var toggle = document.getElementById('navToggle');
    var navbar = document.getElementById('mainNavbar');
    if (!toggle || !navbar) return;

    toggle.addEventListener('click', function () {
        var open = navbar.classList.toggle('nav-open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    navbar.addEventListener('click', function (e) {
        if (e.target.closest('a')) {
            navbar.classList.remove('nav-open');
            toggle.setAttribute('aria-expanded', 'false');
        }
    });
})();
</script>
