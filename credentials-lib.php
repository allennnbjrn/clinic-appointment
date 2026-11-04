<?php
/** Shared read/write helpers for credentials.php. */

function credential_defaults(): array {
    return [
        'mail_provider'      => '',
        'gmail_user'         => '',
        'gmail_app_password' => '',
        'mail_from_name'     => 'BrightSmile Dental',
        'sms_provider'       => '',
        'semaphore_key'      => '',
        'semaphore_name'     => '',
        'twilio_sid'         => '',
        'twilio_token'       => '',
        'twilio_from'        => '',
    ];
}

function credentials_read(string $file): array {
    $values = credential_defaults();
    if (!is_file($file)) {
        return $values;
    }
    $saved = require $file;
    if (!is_array($saved)) {
        return $values;
    }
    foreach ($values as $key => $value) {
        if (isset($saved[$key]) && is_string($saved[$key])) {
            $values[$key] = $saved[$key];
        }
    }
    return $values;
}

function credentials_write(string $file, array $values): bool {
    $values = array_merge(credential_defaults(), $values);

    $php = "<?php\n"
        . "/**\n"
        . " * Delivery credentials for BrightSmile Dental codes.\n"
        . " * Managed by the admin Email and SMS Settings pages. Never share this file.\n"
        . " */\n\n"
        . "if (realpath(\$_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {\n"
        . "    http_response_code(404);\n"
        . "    exit;\n"
        . "}\n\n"
        . "return [\n";
    foreach ($values as $key => $value) {
        $php .= '    ' . var_export($key, true) . ' => ' . var_export((string) $value, true) . ",\n";
    }
    $php .= "];\n";

    $tmp = $file . '.tmp';
    if (@file_put_contents($tmp, $php, LOCK_EX) === false) {
        return false;
    }
    @chmod($tmp, 0640);
    if (!@rename($tmp, $file)) {
        @unlink($tmp);
        return false;
    }
    return true;
}
