<?php
require 'config.php';
$page_title = 'Contact Us - BrightSmile Dental';
require 'header.php';
?>

<main class="page-wrap">
    <div class="page-head">
        <div>
            <h1 class="page-title">Contact Us</h1>
            <p class="page-sub">Questions about appointments, treatments, or your account? Reach out any time.</p>
        </div>
    </div>

    <div class="grid-3">
        <div class="service-card contact-card">
            <h3>📞 Call Us</h3>
            <p>(555) 123-4567<br>Mon-Fri, 10:00 AM - 7:00 PM</p>
        </div>
        <div class="service-card contact-card">
            <h3>✉️ Email</h3>
            <p>info@brightsmiledental.com<br>We reply within 1 business day.</p>
        </div>
        <div class="service-card contact-card">
            <h3>📍 Visit</h3>
            <p>123 Health Ave, Suite 400<br>Walk-ins welcome before 6:00 PM.</p>
        </div>
    </div>

    <section class="panel" style="margin-top: 30px;">
        <h2>Clinic Hours</h2>
        <table class="hours-table">
            <tr><td>Monday – Friday</td><td>10:00 AM – 7:00 PM</td></tr>
            <tr><td>Saturday – Sunday</td><td>Closed</td></tr>
        </table>
    </section>
</main>

<?php require 'footer.php'; ?>
