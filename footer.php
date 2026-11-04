<!-- Footer -->
<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <h4>Contact Info</h4>
            <p>(555) 123-4567</p>
            <p>info@brightsmiledental.com</p>
            <p>123 Health Ave, Suite 400</p>
        </div>
        <div>
            <h4>Hours</h4>
            <p>Mon-Fri: 10:00 AM - 7:00 PM</p>
            <p>Sat-Sun: Closed</p>
        </div>
        <div>
            <h4>Quick Links</h4>
            <a href="services.php">Services</a>
            <a href="doctors.php">Our Dentists</a>
            <a href="contact.php">Contact Us</a>
        </div>
    </div>
    <div class="copyright">
        &copy; <?= date("Y") ?> BrightSmile Dental. All rights reserved.
    </div>
</footer>

<script>
document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('.pw-toggle');
    if (!btn) return;
    var input = btn.parentElement.querySelector('input');
    if (!input) return;
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.classList.toggle('showing', show);
    btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    btn.setAttribute('aria-pressed', show ? 'true' : 'false');
});

document.addEventListener('input', function (ev) {
    var el = ev.target;
    if (!el.matches) return;

    if (el.matches('input[type="email"]')) {
        var cleanEmail = el.value.replace(/\s+/g, '');
        if (cleanEmail !== el.value) el.value = cleanEmail;
        return;
    }

    if (!el.matches('input[inputmode="numeric"]')) return;
    var clean = el.value.replace(/\D/g, '');
    if (clean !== el.value) el.value = clean;
});
</script>

</body>
</html>
