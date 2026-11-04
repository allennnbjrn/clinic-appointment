<?php
require 'config.php';
$page_title = 'Our Services - BrightSmile Dental';
require 'header.php';
?>

<main class="page-wrap">
    <div class="page-head">
        <div>
            <h1 class="page-title">Our Dental Services</h1>
            <p class="page-sub">Complete care for every smile, all in one clinic.</p>
        </div>
    </div>

    <div class="grid-3">
        <div class="service-card">
            <h3>Preventive Dentistry</h3>
            <p>Checkups, cleaning, fluoride treatments, and sealants.</p>
        </div>
        <div class="service-card">
            <h3>Cosmetic Dentistry</h3>
            <p>Whitening, veneers, bonding, and smile makeovers.</p>
        </div>
        <div class="service-card">
            <h3>Restorative Dentistry</h3>
            <p>Fillings, crowns, bridges, and implants.</p>
        </div>
        <div class="service-card">
            <h3>Orthodontics</h3>
            <p>Braces and clear aligners for all ages.</p>
        </div>
        <div class="service-card">
            <h3>Root Canal Treatment</h3>
            <p>Save infected teeth and stop the pain.</p>
        </div>
        <div class="service-card">
            <h3>Pediatric Dental Care</h3>
            <p>Gentle, friendly care for children.</p>
        </div>
    </div>

    <div class="page-actions" style="margin-top: 30px; align-items: center;">
        <a href="book.appointment.php" class="btn-primary">Book an Appointment</a>
        <a href="index.php" class="learn-link">← Back to Home</a>
    </div>
</main>

<?php require 'footer.php'; ?>
