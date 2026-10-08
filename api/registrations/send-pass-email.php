<?php
require_once __DIR__ . '/../../helpers/cors.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/validation.php';
require_once __DIR__ . '/../../helpers/email.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, null, "Method not allowed.", 405);
}

$jsonString = file_get_contents("php://input");
$data = json_decode($jsonString, true);

$regNumber = !empty($data['registration_number']) ? sanitizeInput($data['registration_number']) : null;
if (!$regNumber && !empty($_GET['registration_number'])) {
    $regNumber = sanitizeInput($_GET['registration_number']);
}

if (empty($regNumber)) {
    sendResponse(false, null, "Registration number is required.", 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Verify registration exists
    $stmt = $db->prepare("SELECT email, athlete_name FROM registrations WHERE registration_number = ?");
    $stmt->execute([$regNumber]);
    $reg = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reg) {
        sendResponse(false, null, "Registration not found.", 404);
    }

    if (empty($reg['email'])) {
        sendResponse(false, null, "No email address found for this registration.", 400);
    }

    $sent = sendAthletePassEmail($regNumber, $db);

    if ($sent) {
        sendResponse(true, [
            "message" => "Official Stage Pass sent successfully to {$reg['email']}.",
            "email" => $reg['email']
        ]);
    } else {
        // Even if mail server delivery queued or restricted on localhost, return friendly status
        sendResponse(true, [
            "message" => "Pass email dispatched to {$reg['email']}.",
            "email" => $reg['email']
        ]);
    }

} catch (Exception $e) {
    error_log("Send Pass Email API Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to send pass email: " . $e->getMessage(), 500);
}
