<?php
/**
 * Delivery credentials for BrightSmile Dental codes.
 * Managed by the admin Email and SMS Settings pages. Never share this file.
 */

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

return [
    'mail_provider' => 'smtp',
    'gmail_user' => 'allenbjornlacson@gmail.com',
    'gmail_app_password' => 'puotedvazlrvuyub',
    'mail_from_name' => 'BrightSmile Dental',
    'sms_provider' => '',
    'semaphore_key' => '',
    'semaphore_name' => '',
    'twilio_sid' => '',
    'twilio_token' => '',
    'twilio_from' => '',
];
