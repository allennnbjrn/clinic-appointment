<?php
require 'config.php';
require_role('admin');

$type = $_GET['type'] ?? '';
if (!in_array($type, ['appointments', 'payments'], true)) {
    http_response_code(400);
    die('Unknown export type.');
}

$today = date('Y-m-d');
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? $today;

$from_ok = DateTime::createFromFormat('Y-m-d', (string) $from);
$to_ok = DateTime::createFromFormat('Y-m-d', (string) $to);
if (!$from_ok || $from_ok->format('Y-m-d') !== $from) {
    $from = date('Y-m-01');
}
if (!$to_ok || $to_ok->format('Y-m-d') !== $to) {
    $to = $today;
}
if ($from > $to) {
    [$from, $to] = [$to, $from];
}

log_activity('export_generated', ucfirst($type) . ' CSV for ' . $from . ' to ' . $to);

/** Stop spreadsheet apps from treating text cells as formulas. */
function csv_safe($value): string {
    $value = (string) $value;
    if (preg_match('/^[=+\-@\t\r]/', $value)) {
        return "'" . $value;
    }
    return $value;
}

if ($type === 'appointments') {
    $stmt = $pdo->prepare(
        "SELECT a.id, u.fullname, u.email, u.phone, a.service, a.dentist,
                a.appointment_date, a.time_slot, a.service_price, a.status, a.created_at
         FROM appointments a
         JOIN users u ON a.user_id = u.id
         WHERE a.appointment_date BETWEEN ? AND ?
         ORDER BY a.appointment_date, a.time_slot"
    );
    $stmt->execute([$from, $to]);
    $rows = $stmt->fetchAll();

    $filename = 'appointments-' . $from . '-to-' . $to . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Appointment ID', 'Patient', 'Email', 'Phone', 'Service', 'Dentist', 'Date', 'Time', 'Service Price', 'Status', 'Requested At']);
    foreach ($rows as $row) {
        fputcsv($out, array_map('csv_safe', [
            $row['id'],
            $row['fullname'],
            $row['email'],
            $row['phone'] ?? '',
            $row['service'],
            trim($row['dentist']),
            $row['appointment_date'],
            $row['time_slot'],
            number_format((float) $row['service_price'], 2, '.', ''),
            $row['status'],
            $row['created_at'],
        ]));
    }
    fclose($out);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT p.id, p.amount, p.payment_method, p.payment_type, p.payment_status, p.created_at,
            a.id AS appointment_id, a.service, a.appointment_date,
            u.fullname, u.email
     FROM payments p
     JOIN appointments a ON p.appointment_id = a.id
     JOIN users u ON a.user_id = u.id
     WHERE DATE(p.created_at) BETWEEN ? AND ?
     ORDER BY p.created_at"
);
$stmt->execute([$from, $to]);
$rows = $stmt->fetchAll();

$filename = 'payments-' . $from . '-to-' . $to . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Receipt No', 'Payment ID', 'Patient', 'Email', 'Appointment ID', 'Service', 'Appointment Date', 'Type', 'Method', 'Amount', 'Status', 'Paid At']);
foreach ($rows as $row) {
    $receipt = 'BS-' . date('Y', strtotime($row['created_at'])) . '-' . str_pad((string) (int) $row['id'], 6, '0', STR_PAD_LEFT);
    fputcsv($out, array_map('csv_safe', [
        $receipt,
        $row['id'],
        $row['fullname'],
        $row['email'],
        $row['appointment_id'],
        $row['service'],
        $row['appointment_date'],
        $row['payment_type'],
        $row['payment_method'],
        number_format((float) $row['amount'], 2, '.', ''),
        $row['payment_status'],
        $row['created_at'],
    ]));
}
fclose($out);
exit;
