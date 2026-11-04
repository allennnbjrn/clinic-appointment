<?php
require 'config.php';
require_login();

$home = home_for_role($_SESSION['role'] ?? 'patient');
$fullname = trim((string) ($_SESSION['fullname'] ?? 'there'));
$role = $_SESSION['role'] ?? 'patient';
$display_name = $role === 'patient' ? (explode(' ', $fullname)[0] ?: $fullname) : $fullname;

$role_label = [
    'admin'        => 'Clinic Administrator',
    'receptionist' => 'Front Desk Team',
    'dentist'      => 'Dentist',
    'patient'      => 'Patient Portal',
][$role] ?? 'BrightSmile Dental';

$dest_label = $role === 'patient' ? 'the homepage' : 'your dashboard';

header('Cache-Control: no-store, max-age=0');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - BrightSmile Dental</title>
    <link rel="icon" type="image/svg+xml" href="logo-icon.svg">
    <meta http-equiv="refresh" content="5;url=<?= e($home) ?>">
    <link rel="stylesheet" href="style.css?v=<?= (int) filemtime(__DIR__ . '/style.css') ?>">
    <style>
        body.welcome-body { display: grid; place-items: center; min-height: 100vh; margin: 0; background: radial-gradient(circle at 20% 20%, #e8f6ff 0%, #f8fbff 45%, #eef7ff 100%); font-family: "Inter", "Segoe UI", system-ui, sans-serif; color: #0b1220; }
        .welcome-box { text-align: center; padding: 40px 28px; animation: welcomeRise .5s ease-out both; }
        .welcome-logo { margin: 0 auto 22px; animation: welcomePulse 2.4s ease-in-out infinite; }
        .welcome-logo img { display: block; width: clamp(240px, 62vw, 340px); height: auto; margin: 0 auto; }
        .welcome-box h1 { margin: 0; font-size: clamp(24px, 4vw, 32px); letter-spacing: -.02em; }
        .welcome-box p { margin: 8px 0 0; color: #64748b; font-size: 15px; }
        .welcome-role { display: inline-block; margin-top: 14px; padding: 5px 14px; border-radius: 999px; background: rgba(2, 132, 199, .12); color: #0369a1; font-size: 13px; font-weight: 600; letter-spacing: .02em; }
        .welcome-progress { width: min(260px, 70vw); height: 5px; margin: 30px auto 0; border-radius: 999px; background: rgba(2, 132, 199, .15); overflow: hidden; }
        .welcome-progress span { display: block; height: 100%; width: 100%; border-radius: inherit; background: linear-gradient(90deg, #38bdf8, #0284c7); transform-origin: left center; animation: welcomeFill 5s linear forwards; }
        .welcome-skip { margin-top: 26px; border: 1px solid rgba(3, 105, 161, .25); background: #fff; color: #0369a1; font: inherit; font-size: 13px; font-weight: 600; padding: 9px 18px; border-radius: 999px; cursor: pointer; }
        .welcome-skip:hover { background: #eff8ff; }
        @keyframes welcomeFill { from { transform: scaleX(0); } to { transform: scaleX(1); } }
        @keyframes welcomeRise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }
        @keyframes welcomePulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.04); } }
        @media (prefers-reduced-motion: reduce) { .welcome-box, .welcome-logo { animation: none; } }
    </style>
</head>
<body class="welcome-body">
    <main class="welcome-box" aria-labelledby="welcomeTitle">
        <div class="welcome-logo"><img src="logo.svg" alt="BrightSmile Dental Care"></div>
        <h1 id="welcomeTitle">Welcome, <?= e($display_name) ?>!</h1>
        <p>Taking you to <?= e($dest_label) ?>&hellip;</p>
        <span class="welcome-role"><?= e($role_label) ?></span>
        <div class="welcome-progress" aria-hidden="true"><span></span></div>
        <button type="button" class="welcome-skip" id="skipBtn">Continue now</button>
    </main>
    <script>
        (function () {
            var home = <?= json_encode($home, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            var go = function () { window.location.replace(home); };
            var timer = setTimeout(go, 5000);
            var skip = document.getElementById('skipBtn');
            if (skip) {
                skip.addEventListener('click', function () {
                    clearTimeout(timer);
                    go();
                });
            }
        })();
    </script>
</body>
</html>
