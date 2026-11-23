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
$targetEmail = !empty($data['test_email']) ? trim($data['test_email']) : '';

if (empty($targetEmail) || !validateEmail($targetEmail)) {
    sendResponse(false, null, "Please provide a valid test email address.", 422);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $smtp = getSmtpConfig($db);
    $timestamp = date('d M Y, h:i:s A T');
    $smtpHost = !empty($smtp['host']) ? $smtp['host'] : 'Native PHP mail() / Local Sendmail';
    $smtpPort = !empty($smtp['port']) ? $smtp['port'] : 'N/A';
    $smtpEnc = !empty($smtp['encryption']) ? strtoupper($smtp['encryption']) : 'None';
    $fromAddr = !empty($smtp['from_email']) ? $smtp['from_email'] : 'no-reply@wfftamilnadu.in';

    $subject = "Live Test Email - World Fitness Federation Tamil Nadu";
    $htmlBody = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>WFF Tamil Nadu - Email Test</title>
    </head>
    <body style='margin: 0; padding: 30px; background-color: #050a12; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; color: #ffffff;'>
        <div style='max-width: 600px; margin: 0 auto; background: #0c1524; border: 1px solid rgba(201, 164, 74, 0.4); border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5);'>
            <div style='background: linear-gradient(135deg, #121f36, #09111c); padding: 28px 24px; text-align: center; border-bottom: 2px solid #c9a44a;'>
                <h1 style='margin: 0 0 6px 0; font-size: 22px; color: #fcf6ba; text-transform: uppercase; letter-spacing: 2px;'>WORLD FITNESS FEDERATION</h1>
                <p style='margin: 0; font-size: 13px; color: #c9a44a; letter-spacing: 1px; font-weight: bold;'>TAMIL NADU &bull; SMTP CONFIGURATION VERIFIED</p>
            </div>
            <div style='padding: 28px 24px;'>
                <div style='background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.4); border-radius: 10px; padding: 14px 18px; margin-bottom: 22px;'>
                    <h3 style='margin: 0 0 4px 0; color: #34d399; font-size: 15px;'>&#x2714; Live Email Dispatch Successful</h3>
                    <p style='margin: 0; font-size: 13px; color: #d1fae5; line-height: 1.5;'>
                        Your mail configuration has been successfully tested. Official athlete passes and confirmation notifications can now be reliably sent from this server.
                    </p>
                </div>
                <table style='width: 100%; border-collapse: collapse; font-size: 13px; color: #cbd5e1; margin-bottom: 20px;'>
                    <tr style='border-bottom: 1px solid rgba(255,255,255,0.08);'>
                        <td style='padding: 10px 0; color: #94a3b8;'>SMTP Server</td>
                        <td style='padding: 10px 0; text-align: right; font-weight: 600; color: #ffffff;'>{$smtpHost}:{$smtpPort}</td>
                    </tr>
                    <tr style='border-bottom: 1px solid rgba(255,255,255,0.08);'>
                        <td style='padding: 10px 0; color: #94a3b8;'>Security / Encryption</td>
                        <td style='padding: 10px 0; text-align: right; font-weight: 600; color: #ffffff;'>{$smtpEnc}</td>
                    </tr>
                    <tr style='border-bottom: 1px solid rgba(255,255,255,0.08);'>
                        <td style='padding: 10px 0; color: #94a3b8;'>Sender (From)</td>
                        <td style='padding: 10px 0; text-align: right; font-weight: 600; color: #ffffff;'>{$fromAddr}</td>
                    </tr>
                    <tr style='border-bottom: 1px solid rgba(255,255,255,0.08);'>
                        <td style='padding: 10px 0; color: #94a3b8;'>Recipient (To)</td>
                        <td style='padding: 10px 0; text-align: right; font-weight: 600; color: #c9a44a;'>{$targetEmail}</td>
                    </tr>
                    <tr>
                        <td style='padding: 10px 0; color: #94a3b8;'>Timestamp</td>
                        <td style='padding: 10px 0; text-align: right; font-weight: 600; color: #ffffff;'>{$timestamp}</td>
                    </tr>
                </table>
                <div style='background: #080f1a; border-radius: 8px; padding: 12px 16px; font-size: 12px; color: #94a3b8; text-align: center; border: 1px solid rgba(255,255,255,0.05);'>
                    Initiated by Admin: <strong style='color: #ffffff;'>{$admin['name']} ({$admin['email']})</strong>
                </div>
            </div>
            <div style='background: #060c14; padding: 14px 24px; text-align: center; font-size: 11px; color: #64748b; border-top: 1px solid rgba(255,255,255,0.05);'>
                &copy; " . date('Y') . " World Fitness Federation Tamil Nadu. All rights reserved.
            </div>
        </div>
    </body>
    </html>
    ";

    $debugInfo = '';
    $sent = sendHtmlEmail($targetEmail, "WFF Administrator", $subject, $htmlBody, $db, $debugInfo, "test_smtp_" . time());

    if ($sent) {
        sendResponse(true, [
            "message" => "Test email dispatched successfully to {$targetEmail}.",
            "debug" => $debugInfo,
            "preview_url" => "/uploads/emails/test_smtp_" . time() . ".html"
        ]);
    } else {
        sendResponse(false, [
            "debug" => $debugInfo
        ], "Email dispatch failed: {$debugInfo}. A local HTML preview has been saved to uploads/emails.", 500);
    }

} catch (Exception $e) {
    error_log("Test Email API Error: " . $e->getMessage());
    sendResponse(false, null, "Test email exception: " . $e->getMessage(), 500);
}
