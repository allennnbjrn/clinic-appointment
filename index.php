<?php 
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Guests land on the login page first; the homepage is for signed-in users.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$page_title = 'BrightSmile Dental | Appointment System';
require 'header.php';
?>

<!-- Hero Section -->
<section class="hero">
    <div class="container hero-content">
        <h1>Your Smile, <span class="accent">Reimagined.</span><br>Gentle, Modern Dental Care.</h1>
        <p>Comprehensive dentistry for the whole family in a comfortable, welcoming environment. New patients are always welcome.</p>
        <div class="hero-buttons">
            <a href="book.appointment.php" class="btn-primary">Schedule Your Appointment</a>
            <p style="margin-top: 12px; font-size: 15px;">or Call (555) 123-4567</p>
        </div>
    </div>
</section>

<!-- Features Highlights -->
<section class="features">
    <div class="container grid-4">
        <div class="feature-card">
            <h3>ONLINE SCHEDULING</h3>
            <p>Easy booking 24/7. Find a time that works for you.</p>
            <a href="register.php" class="learn-link">Learn More &rarr;</a>
        </div>
        <div class="feature-card">
            <h3>COMPREHENSIVE SERVICES</h3>
            <p>Preventive, Cosmetic, Restorative & Orthodontics.</p>
            <a href="services.php" class="learn-link">Learn More &rarr;</a>
        </div>
        <div class="feature-card">
            <h3>EXPERT DENTISTS</h3>
            <p>Experienced, caring, and highly qualified team.</p>
            <a href="doctors.php" class="learn-link">Learn More &rarr;</a>
        </div>
        <div class="feature-card">
            <h3>PATIENT-FOCUSED</h3>
            <p>Personalized care plans in a relaxed, warm environment.</p>
            <a href="about.php" class="learn-link">Learn More &rarr;</a>
        </div>
    </div>
</section>

<!-- Core Services -->
<section class="core-services">
    <div class="container">
        <h2>OUR CORE SERVICES</h2>
        <div class="grid-3">
            <div class="service-card">
                <h3>Preventive</h3>
                <p>Checkups, cleanings, fluoride treatments, and sealants.</p>
                <a href="services.php" class="learn-link">Learn More &rarr;</a>
            </div>
            <div class="service-card">
                <h3>Cosmetic</h3>
                <p>Whitening, veneers, bonding, and smile makeovers.</p>
                <a href="services.php" class="learn-link">Learn More &rarr;</a>
            </div>
            <div class="service-card">
                <h3>Implants</h3>
                <p>Permanent tooth replacement for missing teeth.</p>
                <a href="services.php" class="learn-link">Learn More &rarr;</a>
            </div>
        </div>
    </div>
</section>

<?php require 'footer.php'; ?>
