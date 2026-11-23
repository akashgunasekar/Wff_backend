<?php
function sanitizeInput($data) {
    if (is_string($data)) {
        return html_entity_decode(strip_tags(trim($data)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return $data;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePhone($phone) {
    // Simple check for reasonable length (allow basic international/Indian numbers)
    $phoneClean = preg_replace('/[^0-9+]/', '', $phone);
    return strlen($phoneClean) >= 10 && strlen($phoneClean) <= 15;
}

function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}
