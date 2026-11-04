<?php
require 'config.php';
require_login();

$page_title = 'Prescriptions';

$dentists = [
    'Dr. Michael Jordan',
    'Dr. Xhander Ford',
    'Dr. Mel Cabales',
    'Dr. Lorens Paraiso',
];

$is_staff = in_array($_SESSION['role'], ['admin', 'receptionist', 'dentist'], true);
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_staff) {
    verify_csrf();

    $patient_id = (int) ($_POST['patient_id'] ?? 0);
    $doctor_name = $_POST['doctor_name'] ?? '';
    $medication = trim($_POST['medication'] ?? '');
    $instructions = trim($_POST['instructions'] ?? '');

    if ($patient_id <= 0) {
        $error = 'Please select a patient.';
    } elseif (!in_array($doctor_name, $dentists, true)) {
        $error = 'Please choose a valid dentist.';
    } elseif ($medication === '' || mb_strlen($medication) > 255) {
        $error = 'Please enter the medication (max 255 characters).';
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'patient' LIMIT 1");
        $check->execute([$patient_id]);

        if (!$check->fetch()) {
            $error = 'Patient not found.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO prescriptions (patient_id, doctor_name, medication, instructions) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([
                $patient_id,
                $doctor_name,
                $medication,
                $instructions !== '' ? mb_substr($instructions, 0, 1000) : null,
            ]);
            log_activity('prescription_issued', 'Patient #' . $patient_id . ': ' . $medication . ' by ' . $doctor_name);
            $success = 'Prescription issued successfully.';
        }
    }
}

if ($_SESSION['role'] === 'patient') {
    $stmt = $pdo->prepare('SELECT * FROM prescriptions WHERE patient_id = ? ORDER BY created_at DESC');
    $stmt->execute([$_SESSION['user_id']]);
    $prescriptions = $stmt->fetchAll();
} else {
    $stmt = $pdo->query(
        'SELECT p.*, u.fullname AS patient_name
         FROM prescriptions p
         JOIN users u ON p.patient_id = u.id
         ORDER BY p.created_at DESC'
    );
    $prescriptions = $stmt->fetchAll();
    $patients = $pdo->query("SELECT id, fullname FROM users WHERE role = 'patient' ORDER BY fullname")->fetchAll();
}

require 'header.php';
?>

<div class="page-wrap">
    <div class="page-head">
        <div>
            <h1 class="page-title">Prescriptions</h1>
            <p class="page-sub"><?= $is_staff ? 'Issue and track patient prescriptions.' : 'Prescriptions issued by your dentist.' ?></p>
        </div>
    </div>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php elseif ($error !== ''): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($is_staff): ?>
        <div class="panel">
            <h2>Issue a Prescription</h2>
            <?php if (empty($patients)): ?>
                <div class="empty-state"><p>No patients registered yet.</p></div>
            <?php else: ?>
                <form method="POST">
                    <?= csrf_field() ?>
                    <div class="form-grid">
                        <div class="field">
                            <label for="patient_id">Patient</label>
                            <select name="patient_id" id="patient_id" class="select" required>
                                <option value="">Select patient...</option>
                                <?php foreach ($patients as $p): ?>
                                    <option value="<?= (int) $p['id'] ?>"><?= e($p['fullname']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="doctor_name">Doctor</label>
                            <select name="doctor_name" id="doctor_name" class="select" required>
                                <?php foreach ($dentists as $d): ?>
                                    <option value="<?= e($d) ?>"><?= e($d) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="medication">Medication</label>
                            <input type="text" name="medication" id="medication" class="input" maxlength="255" placeholder="e.g. Amoxicillin 500mg" required>
                        </div>
                        <div class="field">
                            <label for="instructions">Instructions</label>
                            <input type="text" name="instructions" id="instructions" class="input" maxlength="1000" placeholder="e.g. 3x daily for 7 days">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success">Issue Prescription</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="panel">
        <h2><?= $is_staff ? 'All Prescriptions' : 'My Prescriptions' ?></h2>
        <?php if (empty($prescriptions)): ?>
            <div class="empty-state"><p>No prescriptions found.</p></div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <?php if ($is_staff): ?><th>Patient</th><?php endif; ?>
                            <th>Doctor</th>
                            <th>Medication</th>
                            <th>Instructions</th>
                            <th>Date Issued</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($prescriptions as $rx): ?>
                        <tr>
                            <?php if ($is_staff): ?><td class="cell-strong"><?= e($rx['patient_name']) ?></td><?php endif; ?>
                            <td><?= e($rx['doctor_name']) ?></td>
                            <td class="cell-strong"><?= e($rx['medication']) ?></td>
                            <td class="cell-muted"><?= e($rx['instructions'] ?? '-') ?></td>
                            <td><?= date("F j, Y", strtotime($rx['created_at'])) ?></td>
                            <td><button type="button" class="btn btn-sm btn-neutral" onclick="window.print()">Print</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require 'footer.php'; ?>
