<?php
require 'config.php';
$page_title = 'Our Dentists - BrightSmile Dental';
require 'header.php';
?>

<main class="page-wrap">
    <div class="page-head">
        <div>
            <h1 class="page-title">Meet Our Team</h1>
            <p class="page-sub">Experienced, caring dentists who put you at ease.</p>
        </div>
    </div>

    <div class="grid-3">
        <div class="service-card doctor-card">
            <img src="images/doctors/michael-jordan.jpg" alt="Dr. Michael Jordan" class="doctor-photo" width="150" height="150" loading="lazy">
            <h3>Dr. Michael Jordan</h3>
            <p>Lead Dentist • General &amp; Cosmetic Dentistry</p>
        </div>
        <div class="service-card doctor-card">
            <img src="images/doctors/xhander-ford.jpg" alt="Dr. Xhander Ford" class="doctor-photo" width="150" height="150" loading="lazy">
            <h3>Dr. Xhander Ford</h3>
            <p>Orthodontist • Braces &amp; Alignments</p>
        </div>
        <div class="service-card doctor-card">
            <img src="images/doctors/mel-cabales.jpg?v=2" alt="Dr. Mel Cabales" class="doctor-photo" width="150" height="150" loading="lazy">
            <h3>Dr. Mel Cabales</h3>
            <p>Pediatric Dentist • Kids Dental Care</p>
        </div>
        <div class="service-card doctor-card">
            <img src="images/doctors/lorens-paraiso.jpg" alt="Dr. Lorens Paraiso" class="doctor-photo" width="150" height="150" loading="lazy">
            <h3>Dr. Lorens Paraiso</h3>
            <p>General Dentistry • Oral Surgery</p>
        </div>
    </div>
</main>

<?php require 'footer.php'; ?>
