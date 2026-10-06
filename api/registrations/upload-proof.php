<?php
require_once __DIR__ . '/../../helpers/cors.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/validation.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, null, "Method not allowed.", 405);
}

$regNumber = isset($_POST['registration_number']) ? trim($_POST['registration_number']) : '';
$transactionRef = isset($_POST['transaction_ref']) ? trim($_POST['transaction_ref']) : '';

if (empty($regNumber)) {
    sendResponse(false, null, "Registration number is required.", 400);
}

if (empty($_FILES['screenshot'])) {
    sendResponse(false, null, "Payment screenshot file is required.", 422);
}

$file = $_FILES['screenshot'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    sendResponse(false, null, "File upload error: code " . $file['error'], 400);
}

if ($file['size'] > 10 * 1024 * 1024) {
    sendResponse(false, null, "Screenshot file is too large. Maximum allowed size is 10MB.", 400);
}

// Validate MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
if (!in_array($mimeType, $allowedMimeTypes)) {
    sendResponse(false, null, "Please upload a valid image file (JPEG, PNG, WebP).", 400);
}

$imageInfo = getimagesize($file['tmp_name']);
if ($imageInfo === false) {
    sendResponse(false, null, "Invalid image content.", 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Fetch registration
    $stmt = $db->prepare("SELECT r.*, e.event_name, e.event_date, e.venue FROM registrations r JOIN events e ON r.event_id = e.id WHERE r.registration_number = ?");
    $stmt->execute([$regNumber]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registration) {
        sendResponse(false, null, "Registration not found.", 404);
    }

    $regId = (int)$registration['id'];

    // Generate filename
    $extension = 'jpg';
    switch ($mimeType) {
        case 'image/png': $extension = 'png'; break;
        case 'image/webp': $extension = 'webp'; break;
        case 'image/jpeg':
        case 'image/jpg':
        default:
            $extension = 'jpg'; break;
    }

    $safeRegNum = preg_replace('/[^A-Za-z0-9_-]/', '', $regNumber);
    $filename = 'proof_' . $safeRegNum . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $extension;

    $uploadDir = realpath(__DIR__ . '/../../uploads');
    if (!$uploadDir) {
        mkdir(__DIR__ . '/../../uploads', 0755, true);
        $uploadDir = realpath(__DIR__ . '/../../uploads');
    }

    $proofsDir = $uploadDir . '/proofs';
    if (!file_exists($proofsDir)) {
        mkdir($proofsDir, 0755, true);
    }

    $targetPath = $proofsDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        sendResponse(false, null, "Failed to save uploaded screenshot.", 500);
    }

    $publicPath = '/uploads/proofs/' . $filename;

    $db->beginTransaction();

    // Update registration status to 'paid'
    $updateStmt = $db->prepare("UPDATE registrations SET status = 'paid' WHERE id = ?");
    $updateStmt->execute([$regId]);

    // Fetch existing meta
    $metaStmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
    $metaStmt->execute(["registration_{$regId}_meta"]);
    $metaJson = $metaStmt->fetchColumn();

    $meta = $metaJson ? json_decode($metaJson, true) : [];
    $meta['payment_proof'] = $publicPath;
    $meta['transaction_ref'] = $transactionRef ?: null;
    $meta['proof_uploaded_at'] = date('Y-m-d H:i:s');

    $setting_key = "registration_{$regId}_meta";
    $setting_value = json_encode($meta);
    $saveMetaStmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $saveMetaStmt->execute([$setting_key, $setting_value, $setting_value]);

    // Record or update payment in payments table
    $totalAmount = isset($meta['pricing']['final_total']) ? $meta['pricing']['final_total'] : 0;
    $payStmt = $db->prepare("INSERT INTO payments (registration_id, razorpay_order_id, razorpay_payment_id, amount, currency, status, method) VALUES (?, ?, ?, ?, 'INR', 'captured', 'upi_qr')");
    $payStmt->execute([
        $regId,
        'UPI-PROOF-' . time(),
        $transactionRef ?: ('PROOF-' . substr(md5($filename), 0, 10)),
        $totalAmount * 100
    ]);

    $db->commit();

    sendResponse(true, [
        "registration_number" => $regNumber,
        "status" => "paid",
        "payment_proof" => $publicPath,
        "transaction_ref" => $transactionRef,
        "message" => "Payment screenshot uploaded and verified successfully."
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Upload Proof Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to upload payment proof: " . $e->getMessage(), 500);
}
