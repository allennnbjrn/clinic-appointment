<?php
// Database configuration
$host = 'localhost';
$dbname = 'appointment_db';
$username = 'root';
$password = '';

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $service = trim($_POST['service'] ?? '');
    $date = $_POST['appointment_date'] ?? '';
    $time = $_POST['appointment_time'] ?? '';

    // Basic validation
    if (empty($name) || empty($email) || empty($service) || empty($date) || empty($time)) {
        $message = "Please fill in all required fields.";
        $messageType = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $messageType = "error";
    } else {
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            $stmt = $pdo->prepare("INSERT INTO appointments (name, email, service, appointment_date, appointment_time) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $service, $date, $time]);

            $message = "Appointment booked successfully! We will send a confirmation to your email.";
            $messageType = "success";
        } catch (PDOException $e) {
            $message = "Database error: " . $e->getMessage();
            $messageType = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book an Appointment</title>
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --text: #1e293b;
            --border: #cbd5e1;
        }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            width: 100%;
            max-width: 500px;
            background: var(--card-bg);
            padding: 32px;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        }
        h2 { margin-top: 0; font-size: 24px; margin-bottom: 8px; }
        p.subtitle { color: #64748b; margin-bottom: 24px; font-size: 14px; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        input, select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.2s;
        }
        input:focus, select:focus { border-color: var(--primary); }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        button {
            width: 100%;
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 12px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 8px;
            transition: background-color 0.2s;
        }
        button:hover { background-color: var(--primary-hover); }
        .alert {
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            margin-bottom: 20px;
        }
        .alert.success { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert.error { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    </style>
</head>
<body>

<div class="container">
    <h2>Schedule a Session</h2>
    <p class="subtitle">Select your preferred date, time, and service below.</p>

    <?php if (!empty($message)): ?>
        <div class="alert <?= $messageType ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="name">Full Name</label>
            <input type="text" id="name" name="name" required placeholder="John Doe">
        </div>

        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" required placeholder="john@example.com">
        </div>

        <div class="form-group">
            <label for="service">Select Service</label>
            <select id="service" name="service" required>
                <option value="">Choose a service...</option>
                <option value="Consultation">Initial Consultation (30 mins)</option>
                <option value="Strategy Session">Strategy Session (60 mins)</option>
                <option value="Follow-up">Follow-up Review (45 mins)</option>
            </select>
        </div>

        <div class="row">
            <div class="form-group">
                <label for="appointment_date">Date</label>
                <input type="date" id="appointment_date" name="appointment_date" required min="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group">
                <label for="appointment_time">Time</label>
                <input type="time" id="appointment_time" name="appointment_time" required>
            </div>
        </div>

        <button type="submit">Confirm Appointment</button>
    </form>
</div>

</body>
</html>