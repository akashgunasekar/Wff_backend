<?php
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
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
