<?php
require 'config.php';
require_login();

if ($_SESSION['role'] !== 'patient') {
    header('Location: index.php');
    exit;
}

$page_title = 'Reschedule Appointment';

$appointment_id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM appointments WHERE id = ? AND user_id = ? LIMIT 1');
$stmt->execute([$appointment_id, $_SESSION['user_id']]);
$apt = $stmt->fetch();

$today = date('Y-m-d');
if (!$apt || !in_array($apt['status'], ['pending', 'confirmed'], true) || $apt['appointment_date'] < $today) {
    $_SESSION['appt_flash'] = ['type' => 'error', 'msg' => 'That appointment cannot be rescheduled.'];
    header('Location: appointment-history.php');
    exit;
}

require 'header.php';
?>

<div class="page-wrap">
    <div class="page-head">
        <div>
            <h1 class="page-title">Reschedule Appointment #<?= (int) $apt['id'] ?></h1>
            <p class="page-sub">Currently <?= date("F j, Y", strtotime($apt['appointment_date'])) ?> at <?= e($apt['time_slot']) ?> with <?= e($apt['dentist']) ?>.</p>
        </div>
        <a href="appointment-history.php" class="btn btn-outline">Back to My Appointments</a>
    </div>

    <div class="panel">
        <h2><?= e($apt['service']) ?></h2>
        <form method="POST" action="appointment-history.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reschedule">
            <input type="hidden" name="appointment_id" value="<?= (int) $apt['id'] ?>">

            <div class="form-grid">
                <div class="field">
                    <label for="appointment_date">New Date</label>
                    <input type="date" name="appointment_date" id="appointment_date" class="input" min="<?= e($today) ?>" value="<?= e($apt['appointment_date']) ?>" required>
                </div>
                <div class="field">
                    <label>Dentist</label>
                    <input type="text" class="input" value="<?= e($apt['dentist']) ?>" readonly>
                </div>
            </div>

            <div class="field">
                <label>New Time Slot</label>
                <div class="slot-grid">
                    <?php foreach (TIME_SLOTS as $slot): ?>
                        <button type="button" class="slot-btn<?= $slot === $apt['time_slot'] ? ' active' : '' ?>" data-time="<?= e($slot) ?>"><?= e($slot) ?></button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="time_slot" id="selected_time" value="<?= e($apt['time_slot']) ?>" required>
                <p class="field-hint">We are closed on Sundays. If the new slot is taken for this dentist, you will be asked to pick another.</p>
            </div>

            <button type="submit" class="btn btn-primary">Save New Schedule</button>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.slot-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.slot-btn').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        document.getElementById('selected_time').value = btn.dataset.time;
    });
});
</script>

<?php require 'footer.php'; ?>
