<?php
require_once __DIR__ . '/../../../helpers/cors.php';
require_once __DIR__ . '/../../../helpers/response.php';
require_once __DIR__ . '/../../../helpers/validation.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../helpers/email.php';

$admin = requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, null, "Method not allowed.", 405);
}

$data = json_decode(file_get_contents("php://input"), true);
$id = isset($data['id']) ? (int)$data['id'] : 0;
if ($id <= 0) {
    sendResponse(false, null, "Valid registration ID is required.", 400);
}

try {
    $db = (new Database())->getConnection();

    $stmt = $db->prepare("SELECT registration_number, status, athlete_name, email FROM registrations WHERE id = ?");
    $stmt->execute([$id]);
    $reg = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reg) {
        sendResponse(false, null, "Registration not found.", 404);
    }

    if ($reg['status'] !== 'paid' && $reg['status'] !== 'confirmed') {
        sendResponse(false, null, "Cannot issue ticket email for unconfirmed registration (current status: {$reg['status']}).", 400);
    }

    $debugInfo = '';
    $sent = sendAthletePassEmail($reg['registration_number'], $db, $debugInfo);

    if ($sent) {
        sendResponse(true, [
            "email_sent" => true,
            "recipient" => $reg['email'],
            "debug" => $debugInfo,
            "message" => "Official championship ticket emailed to {$reg['email']}."
        ]);
    } else {
        sendResponse(false, [
            "email_sent" => false,
            "recipient" => $reg['email'],
            "debug" => $debugInfo,
            "preview_url" => "/uploads/emails/ticket_{$reg['registration_number']}.html"
        ], "Email dispatch failed: " . ($debugInfo ?: "Mail server unavailable") . ". An offline stage pass preview has been saved to uploads/emails.", 500);
    }

} catch (Exception $e) {
    error_log("Resend Email Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to send ticket email: " . $e->getMessage(), 500);
}
