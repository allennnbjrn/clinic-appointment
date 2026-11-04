<?php
require 'config.php';
require_login();

$page_title = 'Book an Appointment';

$services = clinic_services();
$dentists = CLINIC_DENTISTS;
$time_slots = TIME_SLOTS;

$user_id = (int) $_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $service = $_POST['service'] ?? '';
    $dentist = $_POST['dentist'] ?? '';
    $appointment_date = $_POST['appointment_date'] ?? '';
    $time_slot = $_POST['time_slot'] ?? '';
    $notes = trim($_POST['notes'] ?? '');

    $today = new DateTime('today');

    if (!in_array($service, $services, true) || !in_array($dentist, $dentists, true) || !in_array($time_slot, $time_slots, true)) {
        $error = 'Please choose a valid service, dentist, and time slot.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointment_date)) {
        $error = 'Please choose a valid date.';
    } else {
        $chosen_date = DateTime::createFromFormat('Y-m-d', $appointment_date);
        if (!$chosen_date || $chosen_date->format('Y-m-d') !== $appointment_date || $chosen_date < $today) {
            $error = 'Please choose today or a future date.';
        } else {
            // Sundays closed
            if ((int) $chosen_date->format('w') === 0) {
                $error = 'We are closed on Sundays. Please pick another day.';
            } else {
                // Prevent double-booking the same dentist, date, and slot
                $conflict = $pdo->prepare(
                    "SELECT id FROM appointments
                     WHERE dentist = ? AND appointment_date = ? AND time_slot = ? AND status != 'cancelled'
                     LIMIT 1"
                );
                $conflict->execute([$dentist, $appointment_date, $time_slot]);

                if ($conflict->fetch()) {
                    $error = 'That time slot is already taken for this dentist. Please choose another slot or dentist.';
                } else {
                    $stmt = $pdo->prepare(
                        "INSERT INTO appointments (user_id, service, dentist, appointment_date, time_slot, notes, status, service_price)
                         VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)"
                    );
                    $stmt->execute([
                        $user_id, $service, $dentist, $appointment_date, $time_slot,
                        $notes !== '' ? mb_substr($notes, 0, 1000) : null,
                        service_price($service),
                    ]);

                    $new_id = (int) $pdo->lastInsertId();
                    log_activity('booking_created', '#' . $new_id . ' ' . $service . ' with ' . $dentist . ' on ' . $appointment_date . ' ' . $time_slot);
                    appointment_notify($new_id, 'booked');

                    $success = 'Appointment request submitted. Once the clinic confirms it, pay the ₱' . number_format(DOWNPAYMENT_AMOUNT, 2) . ' downpayment under Payments.';
                }
            }
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM appointments WHERE user_id = ? ORDER BY appointment_date DESC, id DESC');
$stmt->execute([$user_id]);
$my_appointments = $stmt->fetchAll();

require 'header.php';
?>

<div class="page-wrap">
    <div class="page-head">
        <div>
            <h1 class="page-title">Book an Appointment</h1>
            <p class="page-sub">Choose a service, dentist, and time that works for you.</p>
        </div>
        <a href="my.appointments.php" class="btn btn-outline">My Appointments</a>
    </div>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php elseif ($error !== ''): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <div class="panel">
        <h2>Appointment Details</h2>
        <form method="POST">
            <?= csrf_field() ?>
            <div class="form-grid">
                <div class="field">
                    <label for="service">Select Service</label>
                    <select name="service" id="service" class="select" required>
                        <?php foreach ($services as $s): ?>
                            <option value="<?= e($s) ?>" data-price="<?= number_format(service_price($s), 2, '.', '') ?>"><?= e($s) ?> &mdash; &#8369;<?= number_format(service_price($s), 2) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="field-hint">Service price: <strong id="servicePrice">&#8369;<?= number_format(service_price($services[0]), 2) ?></strong> &middot; &#8369;<?= number_format(DOWNPAYMENT_AMOUNT, 2) ?> downpayment once confirmed, balance after your visit.</p>
                </div>

                <div class="field">
                    <label for="dentist">Preferred Dentist</label>
                    <select name="dentist" id="dentist" class="select" required>
                        <?php foreach ($dentists as $d): ?>
                            <option value="<?= e($d) ?>"><?= e($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="appointment_date">Select Date</label>
                    <input type="date" name="appointment_date" id="appointment_date" class="input" min="<?= date('Y-m-d') ?>" required>
                </div>
            </div>

            <div class="field">
                <label>Select Available Time Slot</label>
                <div class="slot-grid">
                    <?php foreach ($time_slots as $i => $slot): ?>
                        <button type="button" class="slot-btn<?= $i === 1 ? ' active' : '' ?>" data-time="<?= e($slot) ?>"><?= e($slot) ?></button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="time_slot" id="selected_time" value="<?= e($time_slots[1]) ?>" required>
                <p class="field-hint">We are closed on Sundays. Clinic hours: 10:00 AM – 7:00 PM.</p>
            </div>

            <div class="field">
                <label for="notes">Chief Complaint / Notes (Optional)</label>
                <textarea name="notes" id="notes" class="textarea" maxlength="1000" placeholder="Describe any pain or specific service request..."></textarea>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Confirm Appointment Request</button>
        </form>
    </div>

    <div class="panel">
        <h2>My Appointments</h2>

        <?php if (empty($my_appointments)): ?>
            <div class="empty-state">
                <p>You have no appointments yet. Book your first visit above.</p>
            </div>
        <?php else: ?>
            <?php foreach ($my_appointments as $apt): ?>
            <div class="appt-item">
                <div class="appt-title"><?= e($apt['service']) ?></div>
                <div class="appt-meta">
                    <span><?= date("M d, Y", strtotime($apt['appointment_date'])) ?></span>
                    <span><?= e($apt['time_slot']) ?></span>
                    <span><?= e($apt['dentist']) ?></span>
                    <?php if ((float) $apt['service_price'] > 0): ?>
                        <span>&#8369;<?= number_format((float) $apt['service_price'], 2) ?></span>
                    <?php endif; ?>
                </div>
                <span class="badge badge-<?= e($apt['status']) ?>"><?= e(ucfirst($apt['status'])) ?></span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
const slotBtns = document.querySelectorAll('.slot-btn');
const slotInput = document.getElementById('selected_time');

slotBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        slotBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        slotInput.value = btn.dataset.time;
    });
});

var serviceSelect = document.getElementById('service');
var priceLabel = document.getElementById('servicePrice');
if (serviceSelect && priceLabel) {
    var updatePrice = function () {
        var opt = serviceSelect.options[serviceSelect.selectedIndex];
        var price = parseFloat(opt.dataset.price || '0');
        priceLabel.textContent = '₱' + price.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };
    serviceSelect.addEventListener('change', updatePrice);
    updatePrice();
}
</script>

<?php require 'footer.php'; ?>
