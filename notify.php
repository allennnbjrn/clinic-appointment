<?php
require_once __DIR__ . '/mailer.php';

/** Patient appointment emails. Delivery problems are returned, never thrown. */
function appointment_notify(int $appointment_id, string $event, array $extra = []): array {
    global $pdo;

    $stmt = $pdo->prepare(
        "SELECT a.*, u.fullname, u.email,
                (SELECT COALESCE(SUM(p.amount), 0) FROM payments p
                  WHERE p.appointment_id = a.id AND p.payment_status = 'paid') AS paid_total
         FROM appointments a JOIN users u ON a.user_id = u.id
         WHERE a.id = ? LIMIT 1"
    );
    $stmt->execute([$appointment_id]);
    $apt = $stmt->fetch();

    if (!$apt || empty($apt['email'])) {
        return ['ok' => false, 'error' => 'No recipient for this appointment.'];
    }

    $first = trim(explode(' ', trim((string) $apt['fullname']))[0]);
    $first = $first === '' ? 'there' : $first;
    $when = date('F j, Y', strtotime($apt['appointment_date'])) . ' at ' . $apt['time_slot'];
    $price = (float) $apt['service_price'];
    $paid = (float) $apt['paid_total'];
    $balance = max(0, $price - $paid);
    $down = number_format(DOWNPAYMENT_AMOUNT, 2);

    $body = [
        'Hi ' . $first . ',',
        '',
        'Service:  ' . $apt['service'],
        'Dentist:  ' . $apt['dentist'],
        'When:     ' . $when,
        '',
    ];

    if ($event === 'booked') {
        $subject = 'Appointment request received - ' . $when;
        array_splice($body, 2, 0, 'Thanks for booking with BrightSmile Dental. Your request is waiting for clinic confirmation.');
        $body[] = 'Once confirmed, pay the PHP ' . $down . ' downpayment under Payments to secure your slot.';
    } elseif ($event === 'confirmed') {
        $subject = 'Appointment confirmed - ' . $when;
        array_splice($body, 2, 0, 'Your appointment is confirmed. We look forward to seeing you.');
        $body[] = 'Please pay the PHP ' . $down . ' downpayment under Payments to secure your slot.';
        $body[] = 'Arrive about 10 minutes early. You can reschedule or cancel from My Appointments until the visit starts.';
    } elseif ($event === 'cancelled') {
        $subject = 'Appointment cancelled - ' . $when;
        $line = ($extra['by'] ?? '') === 'clinic'
            ? 'The clinic has cancelled this appointment.'
            : 'Your appointment has been cancelled.';
        array_splice($body, 2, 0, $line);
        $body[] = 'If you paid a downpayment, contact the clinic about a refund or moving it to a new booking.';
    } elseif ($event === 'rescheduled') {
        $subject = 'Appointment moved - ' . $when;
        $old = (string) ($extra['old_when'] ?? '');
        $line = $old !== '' ? 'Your appointment has been moved from ' . $old . '.' : 'Your appointment has been moved.';
        array_splice($body, 2, 0, $line);
        $body[] = 'If this new schedule does not work, reschedule again from My Appointments or call the clinic.';
    } elseif ($event === 'completed') {
        $subject = 'Visit completed - balance due for ' . date('M j, Y', strtotime($apt['appointment_date']));
        array_splice($body, 2, 0, 'Thank you for visiting BrightSmile Dental. Your appointment has been marked completed.');
        $body = array_merge($body, [
            'Service price:   PHP ' . number_format($price, 2),
            'Paid so far:     PHP ' . number_format($paid, 2),
            'Balance due:     PHP ' . number_format($balance, 2),
            '',
            $balance > 0.009
                ? 'Settle the remaining balance under Payments in your account.'
                : 'Your balance is fully settled. Thank you!',
        ]);
    } else {
        return ['ok' => false, 'error' => 'Unknown notification event.'];
    }

    $body = array_merge($body, [
        '',
        'BrightSmile Dental',
        '(555) 123-4567  |  Mon-Fri: 10:00 AM - 7:00 PM',
        '',
        'This is an automated message. Please do not reply.',
    ]);

    return mail_send((string) $apt['email'], $subject, implode("\n", $body));
}
