<?php
/**
 * SMS gateway for BrightSmile Dental.
 *
 * Credentials live in credentials.php. The code is texted straight to the
 * patient's phone; if no gateway is configured, sending fails with an error
 * instead of exposing the code.
 *
 *   Semaphore (Philippines): https://semaphore.co  — load credits, copy your API key
 *   Twilio (international):  https://www.twilio.com — Account SID + Auth Token
 */

/** Provider + credentials, resolved once per request from credentials.php. */
function sms_config(): array {
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $config = [
        'provider'       => '',
        'semaphore_key'  => '',
        'semaphore_name' => '',
        'twilio_sid'     => '',
        'twilio_token'   => '',
        'twilio_from'    => '',
    ];

    $file = __DIR__ . '/credentials.php';
    if (is_file($file)) {
        $saved = require $file;
        if (is_array($saved)) {
            foreach (['semaphore_key', 'semaphore_name', 'twilio_sid', 'twilio_token', 'twilio_from'] as $key) {
                if (isset($saved[$key]) && is_string($saved[$key])) {
                    $config[$key] = trim($saved[$key]);
                }
            }
            if (isset($saved['sms_provider']) && is_string($saved['sms_provider'])) {
                $config['provider'] = strtolower(trim($saved['sms_provider']));
            }
        }
    }

    if ($config['provider'] === '') {
        if ($config['semaphore_key'] !== '') {
            $config['provider'] = 'semaphore';
        } elseif ($config['twilio_sid'] !== '' && $config['twilio_token'] !== '' && $config['twilio_from'] !== '') {
            $config['provider'] = 'twilio';
        } else {
            $config['provider'] = '';
        }
    }

    return $config;
}

/**
 * Normalize a PH mobile number to international format for gateways.
 * '0917 123 4567' → '+639171234567'; numbers already in 63… pass through.
 */
function sms_normalize_phone(string $phone): string {
    $digits = preg_replace('/\D+/', '', $phone);
    if (strpos($digits, '0') === 0 && strlen($digits) === 11) {
        $digits = '63' . substr($digits, 1);
    }
    return '+' . $digits;
}

/**
 * Convert a PH mobile number typed in any common form to strict +63 format.
 * '09171234567', '+639171234567', or just '9171234567' all → '+639171234567'.
 * Returns '' when the digits cannot form a valid PH mobile number.
 */
function sms_to_plus63(string $phone): string {
    $digits = preg_replace('/\D+/', '', $phone);
    if (preg_match('/^0?(?:63)?(9\d{9})$/', $digits, $m)) {
        return '+63' . $m[1];
    }
    return '';
}

/**
 * Send an SMS. Returns ['ok' => bool, 'error' => ?string].
 */
function sms_send(string $phone, string $message): array {
    $to = sms_normalize_phone($phone);
    $config = sms_config();

    if ($config['provider'] === '') {
        return ['ok' => false, 'error' => 'SMS delivery is not set up yet. Please contact the clinic administrator.'];
    }

    $ch = curl_init();

    if ($config['provider'] === 'semaphore') {
        if ($config['semaphore_key'] === '') {
            return ['ok' => false, 'error' => 'SMS delivery is not set up yet. Please contact the clinic administrator.'];
        }
        $fields = [
            'apikey'  => $config['semaphore_key'],
            'number'  => ltrim($to, '+'),
            'message' => $message,
        ];
        if ($config['semaphore_name'] !== '') {
            $fields['sendername'] = $config['semaphore_name'];
        }
        curl_setopt_array($ch, [
            CURLOPT_URL        => 'https://semaphore.co/api/v4/messages',
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
        ]);
    } elseif ($config['provider'] === 'twilio') {
        if ($config['twilio_sid'] === '' || $config['twilio_token'] === '' || $config['twilio_from'] === '') {
            return ['ok' => false, 'error' => 'SMS delivery is not set up yet. Please contact the clinic administrator.'];
        }
        curl_setopt_array($ch, [
            CURLOPT_URL        => 'https://api.twilio.com/2010-04-01/Accounts/' . $config['twilio_sid'] . '/Messages.json',
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'From' => $config['twilio_from'],
                'To'   => $to,
                'Body' => $message,
            ]),
            CURLOPT_USERPWD    => $config['twilio_sid'] . ':' . $config['twilio_token'],
        ]);
    } else {
        return ['ok' => false, 'error' => 'Unknown SMS provider.'];
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $body = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($body === false || ($http !== 200 && $http !== 201)) {
        error_log(sprintf('[SMS] send failed http=%d body=%s', $http, is_string($body) ? $body : ''));
        return ['ok' => false, 'error' => 'The SMS gateway did not respond. Please try again.'];
    }

    // Semaphore answers 200 even for a rejected key, so the body decides.
    $decoded = json_decode((string) $body, true);
    $accepted = false;
    if (is_array($decoded)) {
        if ($config['provider'] === 'semaphore') {
            $accepted = array_is_list($decoded) && isset($decoded[0]['message_id']);
        } else {
            $accepted = isset($decoded['sid']);
        }
    }
    if (!$accepted) {
        error_log(sprintf('[SMS] rejected http=%d body=%s', $http, (string) $body));
        return ['ok' => false, 'error' => 'The SMS gateway rejected the message. Check the gateway API key and account credits.'];
    }

    return ['ok' => true, 'error' => null];
}
