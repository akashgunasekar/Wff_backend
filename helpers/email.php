<?php
require_once __DIR__ . '/../config/database.php';

// Load Composer autoload for PHPMailer if present
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Retrieve SMTP configuration from site_settings table or Environment variables.
 *
 * @param PDO|null $db
 * @return array
 */
function getSmtpConfig($db = null) {
    $config = [
        'host' => getenv('SMTP_HOST') ?: '',
        'port' => getenv('SMTP_PORT') ?: 587,
        'user' => getenv('SMTP_USER') ?: '',
        'pass' => getenv('SMTP_PASS') ?: '',
        'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls',
        'from_email' => getenv('SMTP_FROM_EMAIL') ?: 'no-reply@wfftamilnadu.in',
        'from_name' => getenv('SMTP_FROM_NAME') ?: 'World Fitness Federation Tamil Nadu',
        'reply_to' => getenv('SMTP_REPLY_TO') ?: 'wfftamilnadu@gmail.com'
    ];

    try {
        if (!$db) {
            $database = new Database();
            $db = $database->getConnection();
        }

        $stmt = $db->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_encryption', 'smtp_from_email', 'smtp_from_name', 'contact_email')");
        if ($stmt) {
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            if (!empty($settings['smtp_host'])) $config['host'] = trim($settings['smtp_host']);
            if (!empty($settings['smtp_port'])) $config['port'] = (int)$settings['smtp_port'];
            if (!empty($settings['smtp_user'])) $config['user'] = trim($settings['smtp_user']);
            if (!empty($settings['smtp_pass'])) $config['pass'] = trim($settings['smtp_pass']);
            if (!empty($settings['smtp_encryption'])) $config['encryption'] = trim(strtolower($settings['smtp_encryption']));
            if (!empty($settings['smtp_from_email'])) $config['from_email'] = trim($settings['smtp_from_email']);
            if (!empty($settings['smtp_from_name'])) $config['from_name'] = trim($settings['smtp_from_name']);
            if (!empty($settings['contact_email'])) $config['reply_to'] = trim($settings['contact_email']);
        }
    } catch (Exception $e) {
        error_log("[WFF SMTP Config] Failed to read database settings: " . $e->getMessage());
    }

    return $config;
}

/**
 * Robust HTML Email Dispatcher supporting Authenticated SMTP via PHPMailer with native mail() fallback.
 *
 * @param string $toEmail
 * @param string $toName
 * @param string $subject
 * @param string $htmlBody
 * @param PDO|null $db
 * @param string|null &$debugInfo
 * @param string|null $previewFilename
 * @return bool
 */
function sendHtmlEmail($toEmail, $toName, $subject, $htmlBody, $db = null, &$debugInfo = null, $previewFilename = null) {
    $toEmail = trim($toEmail);
    if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        $debugInfo = "Invalid recipient email address: '{$toEmail}'";
        error_log("[WFF Email Error] " . $debugInfo);
        return false;
    }

    // Save rendered HTML email to uploads/emails for developer & admin preview
    try {
        $emailDir = __DIR__ . '/../uploads/emails';
        if (!is_dir($emailDir)) {
            mkdir($emailDir, 0755, true);
        }
        $filename = $previewFilename ? preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $previewFilename) . '.html' : 'email_' . md5($toEmail . $subject . time()) . '.html';
        file_put_contents($emailDir . '/' . $filename, $htmlBody);
        
        // Also mirror to frontend public uploads if exists
        $frontendEmailDir = realpath(__DIR__ . '/../../Wff_frontend/public/uploads/emails');
        if ($frontendEmailDir && is_dir($frontendEmailDir)) {
            @file_put_contents($frontendEmailDir . '/' . $filename, $htmlBody);
        }
    } catch (Exception $e) {
        error_log("[WFF Email Preview] Could not save preview HTML: " . $e->getMessage());
    }

    $smtp = getSmtpConfig($db);

    // If SMTP Host and User are configured, use PHPMailer
    if (!empty($smtp['host']) && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $smtp['host'];
            $mail->SMTPAuth = !empty($smtp['user']) && !empty($smtp['pass']);
            if ($mail->SMTPAuth) {
                $mail->Username = $smtp['user'];
                $mail->Password = $smtp['pass'];
            }
            $mail->Port = (int)$smtp['port'];

            if ($smtp['encryption'] === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($smtp['encryption'] === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = false;
                $mail->SMTPAutoTLS = false;
            }

            $mail->CharSet = 'UTF-8';
            $fromEmail = !empty($smtp['from_email']) ? $smtp['from_email'] : 'no-reply@wfftamilnadu.in';
            $fromName = !empty($smtp['from_name']) ? $smtp['from_name'] : 'World Fitness Federation Tamil Nadu';
            $replyTo = !empty($smtp['reply_to']) ? $smtp['reply_to'] : $fromEmail;

            $mail->setFrom($fromEmail, $fromName);
            $mail->addReplyTo($replyTo, $fromName);
            $mail->addAddress($toEmail, $toName ?: $toEmail);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<p>', '</p>'], ["\n", "\n", "\n\n", ""], $htmlBody));

            $mail->send();
            $debugInfo = "Delivered via SMTP ({$smtp['host']}:{$smtp['port']})";
            error_log("[WFF Email Success] Sent email to {$toEmail} via SMTP ({$smtp['host']})");
            return true;

        } catch (PHPMailerException $e) {
            $debugInfo = "PHPMailer SMTP Error: " . $e->getMessage();
            error_log("[WFF Email SMTP Error] " . $debugInfo);
            // Fall through to native mail() attempt
        } catch (Exception $e) {
            $debugInfo = "PHPMailer General Error: " . $e->getMessage();
            error_log("[WFF Email General Error] " . $debugInfo);
            // Fall through to native mail() attempt
        }
    }

    // Fallback: Standard PHP mail()
    $fromEmail = !empty($smtp['from_email']) ? $smtp['from_email'] : 'no-reply@wfftamilnadu.in';
    $fromName = !empty($smtp['from_name']) ? $smtp['from_name'] : 'World Fitness Federation Tamil Nadu';
    $replyTo = !empty($smtp['reply_to']) ? $smtp['reply_to'] : 'wfftamilnadu@gmail.com';

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$fromName} <{$fromEmail}>\r\n";
    $headers .= "Reply-To: {$fromName} <{$replyTo}>\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    $mailSent = @mail($toEmail, $subject, $htmlBody, $headers);

    if ($mailSent) {
        $debugInfo = "Dispatched via local sendmail / mail()";
        error_log("[WFF Email] mail() returned true for {$toEmail}");
        return true;
    } else {
        $hint = empty($smtp['host']) ? " (SMTP not configured. Please enter SMTP credentials in Admin > Settings)" : "";
        $debugInfo = "Native mail() failed to deliver to {$toEmail}{$hint}";
        error_log("[WFF Email Failure] " . $debugInfo);
        return false;
    }
}

/**
 * Send the Official Athlete Stage Pass to the athlete's registered email.
 * 
 * @param int|string $registrationIdentifier (Registration ID or Registration Number)
 * @param PDO|null $db
 * @param string|null &$debugInfo
 * @return bool
 */
function sendAthletePassEmail($registrationIdentifier, $db = null, &$debugInfo = null) {
    try {
        if (!$db) {
            $database = new Database();
            $db = $database->getConnection();
        }

        // Fetch registration and event details
        $query = "SELECT r.id, r.registration_number, r.athlete_name, r.email, r.phone, r.status, r.created_at,
                         e.event_name, e.event_date, e.venue, c.name as category_name, c.entry_fee, r.event_id
                  FROM registrations r
                  JOIN events e ON r.event_id = e.id
                  JOIN event_categories c ON r.category_id = c.id
                  WHERE " . (is_numeric($registrationIdentifier) ? "r.id = ?" : "r.registration_number = ?");

        $stmt = $db->prepare($query);
        $stmt->execute([$registrationIdentifier]);
        $reg = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$reg || empty($reg['email'])) {
            $debugInfo = "Registration or email not found for {$registrationIdentifier}";
            error_log("[WFF Email Error] " . $debugInfo);
            return false;
        }

        $regId = $reg['id'];
        $athleteEmail = trim($reg['email']);
        $athleteName = $reg['athlete_name'];
        $regNumber = $reg['registration_number'];
        $eventName = htmlspecialchars_decode($reg['event_name']);
        $eventDate = $reg['event_date'];
        $venue = htmlspecialchars_decode($reg['venue'] ?: 'To Be Announced');

        // Fetch categories & pricing meta
        $metaStmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
        $metaStmt->execute(["registration_{$regId}_meta"]);
        $metaJson = $metaStmt->fetchColumn();

        $categories = [$reg['category_name']];
        $totalAmount = $reg['entry_fee'] ? (int)$reg['entry_fee'] : 0;
        $tanSpray = false;
        $clinicalCharge = 0;
        $categoryFee = 0;

        if ($metaJson) {
            $meta = json_decode($metaJson, true);
            if (isset($meta['category_ids']) && is_array($meta['category_ids']) && !empty($meta['category_ids'])) {
                $catIds = implode(',', array_map('intval', $meta['category_ids']));
                $catsStmt = $db->query("SELECT name FROM event_categories WHERE id IN ($catIds) ORDER BY FIELD(id, $catIds)");
                if ($catsStmt) {
                    $categories = $catsStmt->fetchAll(PDO::FETCH_COLUMN);
                }
            }
            if (isset($meta['pricing']['final_total'])) {
                $totalAmount = $meta['pricing']['final_total'];
            }
            if (!empty($meta['pricing']['clinical_charge'])) {
                $clinicalCharge = (int)$meta['pricing']['clinical_charge'];
            }
            if (!empty($meta['pricing']['category_fee'])) {
                $categoryFee = (int)$meta['pricing']['category_fee'];
            }
            if (!empty($meta['tan_spray_requested'])) {
                $tanSpray = true;
            }
        }

        // Fetch payment ref if available
        $payRef = 'ONLINE-CONFIRMED';
        $payStmt = $db->prepare("SELECT razorpay_payment_id, method, status FROM payments WHERE registration_id = ? ORDER BY id DESC LIMIT 1");
        $payStmt->execute([$regId]);
        $paymentRecord = $payStmt->fetch(PDO::FETCH_ASSOC);
        if ($paymentRecord) {
            if ($paymentRecord['method'] === 'cash') {
                $payRef = 'PAY-AT-VENUE';
            } elseif (!empty($paymentRecord['razorpay_payment_id'])) {
                $payRef = $paymentRecord['razorpay_payment_id'];
            }
        }

        $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($regNumber);
        $passUrl = "https://wfftamilnadu.in/registration-status?regNumber=" . urlencode($regNumber);

        // Build Category List HTML
        $categoriesHtml = '';
        foreach ($categories as $index => $cat) {
            $num = $index + 1;
            $catTitle = htmlspecialchars(htmlspecialchars_decode($cat));
            $categoriesHtml .= "
                <div style='background: #111D30; border-radius: 8px; padding: 10px 14px; margin-bottom: 8px; border: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center;'>
                    <span style='background: #C9A44A; color: #040A12; font-weight: 900; font-size: 11px; width: 22px; height: 22px; border-radius: 6px; display: inline-block; text-align: center; line-height: 22px; margin-right: 12px;'>{$num}</span>
                    <span style='color: #FFFFFF; font-weight: bold; font-size: 13px; letter-spacing: 0.5px; text-transform: uppercase;'>{$catTitle}</span>
                </div>
            ";
        }

        if ($tanSpray) {
            $categoriesHtml .= "
                <div style='background: rgba(201,164,74,0.15); border-radius: 8px; padding: 10px 14px; margin-top: 8px; border: 1px solid rgba(201,164,74,0.3); color: #FCF6BA; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px;'>
                    ★ Official Pro Stage Tan Spray Included
                </div>
            ";
        }

        $subject = "🏆 Official Stage Pass: {$eventName} - {$athleteName} ({$regNumber})";

        // HTML Email Body
        $message = "
<!DOCTYPE html>
<html>
<head>
<meta charset='UTF-8'>
<title>{$subject}</title>
</head>
<body style='margin: 0; padding: 20px 0; background-color: #03070E; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; color: #FFFFFF;'>

<table width='100%' border='0' cellspacing='0' cellpadding='0' style='background-color: #03070E;'>
  <tr>
    <td align='center'>
      
      <!-- Pass Container -->
      <table width='600' border='0' cellspacing='0' cellpadding='0' style='max-width: 600px; width: 100%; background: #081220; border-radius: 18px; overflow: hidden; border: 2px solid #C9A44A; box-shadow: 0 15px 40px rgba(0,0,0,0.6);'>
        
        <!-- Gold Trim -->
        <tr>
          <td height='6' style='background: linear-gradient(90deg, #BF953F, #FCF6BA, #B38728, #FBF5B7, #AA771C); font-size: 0; line-height: 0;'>&nbsp;</td>
        </tr>

        <!-- Header -->
        <tr>
          <td style='padding: 24px 30px; background: #040913; border-bottom: 1px solid rgba(255,255,255,0.08); text-align: left;'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
              <tr>
                <td>
                  <span style='display: inline-block; background: rgba(201,164,74,0.18); border: 1px solid rgba(201,164,74,0.4); color: #FCF6BA; font-size: 10px; font-weight: 900; letter-spacing: 2px; text-transform: uppercase; padding: 3px 8px; border-radius: 4px; margin-bottom: 8px;'>
                    OFFICIAL ATHLETE STAGE PASS
                  </span>
                  <h1 style='margin: 0; color: #FFFFFF; font-size: 24px; font-weight: 900; text-transform: uppercase; letter-spacing: 1px; line-height: 1.2;'>
                    " . htmlspecialchars($eventName) . "
                  </h1>
                  <p style='margin: 4px 0 0 0; color: #C9A44A; font-size: 11px; letter-spacing: 1.5px; text-transform: uppercase; font-weight: 700;'>
                    World Fitness Federation • Tamil Nadu
                  </p>
                </td>
                <td align='right' width='70'>
                  <img src='https://wfftamilnadu.in/assets/wff-india.png' alt='WFF' width='60' height='60' style='display: block; border: 0;' />
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Athlete Banner -->
        <tr>
          <td style='padding: 20px 30px; background: #0E1A2C; border-bottom: 1px solid rgba(201,164,74,0.2);'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
              <tr>
                <td>
                  <div style='font-size: 9px; color: #C9A44A; text-transform: uppercase; letter-spacing: 2px; font-weight: 800; margin-bottom: 2px;'>
                    Registered Competitor
                  </div>
                  <div style='font-size: 20px; font-weight: 900; color: #FFFFFF; text-transform: uppercase; letter-spacing: 0.5px;'>
                    " . htmlspecialchars($athleteName) . "
                  </div>
                </td>
                <td align='right'>
                  <div style='background: #040810; border: 1px solid #C9A44A; border-radius: 8px; padding: 6px 12px; display: inline-block; text-align: right;'>
                    <div style='font-size: 8px; color: #C9A44A; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 700;'>PASS ID</div>
                    <div style='font-family: monospace; font-size: 14px; color: #FCF6BA; font-weight: bold;'>{$regNumber}</div>
                  </div>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Event Schedule & Location -->
        <tr>
          <td style='padding: 16px 30px; background: rgba(0,0,0,0.25); border-bottom: 1px solid rgba(255,255,255,0.06);'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
              <tr>
                <td width='50%' style='padding-right: 10px;'>
                  <div style='font-size: 9px; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 1.5px; font-weight: bold; margin-bottom: 2px;'>Date</div>
                  <div style='font-size: 13px; color: #FFFFFF; font-weight: bold; text-transform: uppercase;'>{$eventDate}</div>
                </td>
                <td width='50%' style='padding-left: 10px;'>
                  <div style='font-size: 9px; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 1.5px; font-weight: bold; margin-bottom: 2px;'>Venue</div>
                  <div style='font-size: 13px; color: #FFFFFF; font-weight: bold; text-transform: uppercase;'>{$venue}</div>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Enrolled Categories -->
        <tr>
          <td style='padding: 24px 30px;'>
            <div style='font-size: 10px; color: #C9A44A; text-transform: uppercase; letter-spacing: 2px; font-weight: 800; margin-bottom: 12px;'>
              Enrolled Championship Categories (" . count($categories) . ")
            </div>
            {$categoriesHtml}
          </td>
        </tr>

        <!-- Perforated QR Stub -->
        <tr>
          <td style='padding: 20px 30px; background: #03070E; border-top: 2px dashed rgba(201,164,74,0.3); text-align: center;'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0'>
              <tr>
                <td align='center' width='140' style='padding-right: 20px;'>
                  <img src='{$qrUrl}' alt='Check-In QR' width='120' height='120' style='display: block; background: #FFFFFF; padding: 6px; border-radius: 8px; border: 2px solid #C9A44A;' />
                  <div style='font-size: 8px; color: #FCF6BA; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 800; margin-top: 6px;'>
                    Scan at Athlete Check-In
                  </div>
                </td>
                <td align='left' style='vertical-align: middle;'>
                  <div style='font-size: 9px; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 1.5px; font-weight: bold;'>Total Registration Fee</div>
                  <div style='font-size: 26px; font-weight: 900; color: #FFFFFF; margin-bottom: 4px;'>₹{$totalAmount}</div>
                  " . ($clinicalCharge > 0 ? "<div style='font-size: 10px; color: #FCF6BA; margin-bottom: 8px;'>Category Fee: ₹{$categoryFee} + Clinical Testing: ₹{$clinicalCharge}</div>" : "<div style='margin-bottom: 8px;'></div>") . "
                  
                  <div style='display: inline-block; background: rgba(16,185,129,0.2); border: 1px solid rgba(16,185,129,0.4); color: #34D399; font-size: 9px; font-weight: 900; letter-spacing: 1.5px; text-transform: uppercase; padding: 4px 10px; border-radius: 4px;'>
                    ✓ PAID &amp; CONFIRMED
                  </div>
                  <div style='font-size: 9px; color: rgba(255,255,255,0.35); font-family: monospace; margin-top: 4px;'>
                    Ref: {$payRef}
                  </div>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Direct Action Button -->
        <tr>
          <td style='padding: 24px 30px; background: #040913; text-align: center; border-top: 1px solid rgba(255,255,255,0.08);'>
            <a href='{$passUrl}' style='display: inline-block; background: linear-gradient(90deg, #BF953F, #E8CE7A, #B38728); color: #040A12; font-weight: 900; font-size: 13px; text-transform: uppercase; letter-spacing: 1.5px; text-decoration: none; padding: 14px 28px; border-radius: 8px; box-shadow: 0 4px 15px rgba(191,149,63,0.4);'>
              View &amp; Download Digital Pass
            </a>
            <p style='margin: 14px 0 0 0; font-size: 11px; color: rgba(255,255,255,0.4);'>
              Please show this QR code or printed pass at the weigh-in counter to collect your official stage chest number.
            </p>
          </td>
        </tr>

      </table>

      <!-- Footer Info -->
      <table width='600' border='0' cellspacing='0' cellpadding='0' style='max-width: 600px; width: 100%; margin-top: 15px; text-align: center;'>
        <tr>
          <td style='font-size: 11px; color: rgba(255,255,255,0.4); line-height: 1.5;'>
            World Fitness Federation (WFF) Tamil Nadu &bull; Official Athlete Services<br/>
            For queries or support, visit <a href='https://wfftamilnadu.in/contact' style='color: #C9A44A; text-decoration: none;'>wfftamilnadu.in/contact</a>
          </td>
        </tr>
      </table>

    </td>
  </tr>
</table>

</body>
</html>
        ";

        return sendHtmlEmail($athleteEmail, $athleteName, $subject, $message, $db, $debugInfo, "ticket_{$regNumber}");

    } catch (Exception $e) {
        $debugInfo = "Exception in sendAthletePassEmail: " . $e->getMessage();
        error_log("[WFF Pass Email Error] " . $debugInfo);
        return false;
    }
}

/**
 * Send Registration Submission Acknowledgement Email (Payment Pending Verification).
 * Does NOT attach or issue the official ticket.
 *
 * @param int|string $registrationIdentifier
 * @param PDO|null $db
 * @param string|null &$debugInfo
 * @return bool
 */
function sendRegistrationAcknowledgementEmail($registrationIdentifier, $db = null, &$debugInfo = null) {
    try {
        if (!$db) {
            $database = new Database();
            $db = $database->getConnection();
        }

        $query = "SELECT r.id, r.registration_number, r.athlete_name, r.email, r.phone, r.created_at,
                         e.event_name, e.event_date, e.venue, c.name as category_name, c.entry_fee
                  FROM registrations r
                  JOIN events e ON r.event_id = e.id
                  JOIN event_categories c ON r.category_id = c.id
                  WHERE " . (is_numeric($registrationIdentifier) ? "r.id = ?" : "r.registration_number = ?");

        $stmt = $db->prepare($query);
        $stmt->execute([$registrationIdentifier]);
        $reg = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$reg || empty($reg['email'])) {
            $debugInfo = "Registration or email not found for {$registrationIdentifier}";
            return false;
        }

        $regId = $reg['id'];
        $athleteEmail = trim($reg['email']);
        $athleteName = htmlspecialchars($reg['athlete_name']);
        $regNumber = htmlspecialchars($reg['registration_number']);
        $eventName = htmlspecialchars(htmlspecialchars_decode($reg['event_name']));
        $eventDate = htmlspecialchars($reg['event_date']);

        // Fetch meta
        $metaStmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = ?");
        $metaStmt->execute(["registration_{$regId}_meta"]);
        $metaJson = $metaStmt->fetchColumn();

        $categories = [$reg['category_name']];
        $totalAmount = $reg['entry_fee'] ? (int)$reg['entry_fee'] : 0;

        if ($metaJson) {
            $meta = json_decode($metaJson, true);
            if (isset($meta['pricing']['final_total'])) {
                $totalAmount = $meta['pricing']['final_total'];
            }
            if (isset($meta['category_ids']) && is_array($meta['category_ids'])) {
                $catIds = implode(',', array_map('intval', $meta['category_ids']));
                $catsStmt = $db->query("SELECT name FROM event_categories WHERE id IN ($catIds)");
                if ($catsStmt) {
                    $categories = $catsStmt->fetchAll(PDO::FETCH_COLUMN);
                }
            }
        }

        $statusUrl = "https://wfftamilnadu.in/registration-status?regNumber=" . urlencode($regNumber);
        $subject = "WFF Tamil Nadu — Registration Submitted ({$regNumber})";
        $categoriesList = htmlspecialchars(implode(', ', array_map('htmlspecialchars_decode', $categories)));

        $message = "
<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'><title>{$subject}</title></head>
<body style='margin: 0; padding: 20px 0; background-color: #03070E; font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color: #FFFFFF;'>
<table width='100%' border='0' cellspacing='0' cellpadding='0'>
  <tr>
    <td align='center'>
      <table width='600' border='0' cellspacing='0' cellpadding='0' style='max-width: 600px; width: 100%; background: #081220; border-radius: 16px; overflow: hidden; border: 1px solid rgba(201,164,74,0.4); box-shadow: 0 10px 30px rgba(0,0,0,0.5);'>
        <tr><td height='5' style='background: linear-gradient(90deg, #BF953F, #FCF6BA, #B38728);'>&nbsp;</td></tr>
        <tr>
          <td style='padding: 24px 30px; background: #040913; border-bottom: 1px solid rgba(255,255,255,0.08);'>
            <div style='color: #C9A44A; font-size: 11px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase;'>WORLD FITNESS FEDERATION • TAMIL NADU</div>
            <h1 style='color: #FFFFFF; font-size: 22px; font-weight: 800; text-transform: uppercase; margin: 4px 0 0 0;'>Registration Received</h1>
          </td>
        </tr>
        <tr>
          <td style='padding: 24px 30px; line-height: 1.6; font-size: 14px;'>
            <p style='color: #FFFFFF; font-size: 15px;'>Dear <strong>{$athleteName}</strong>,</p>
            <p style='color: rgba(255,255,255,0.8);'>
              Thank you for submitting your championship registration and payment screenshot for <strong>{$eventName}</strong>.
            </p>
            <div style='background: #0D192B; border-left: 3px solid #C9A44A; padding: 14px 18px; margin: 18px 0; border-radius: 4px;'>
              <div style='margin-bottom: 6px;'><span style='color: #C9A44A; font-weight: bold;'>Registration Number:</span> <span style='font-family: monospace; font-size: 15px; color: #FCF6BA;'>{$regNumber}</span></div>
              <div style='margin-bottom: 6px;'><span style='color: #C9A44A; font-weight: bold;'>Event:</span> {$eventName} ({$eventDate})</div>
              <div style='margin-bottom: 6px;'><span style='color: #C9A44A; font-weight: bold;'>Categories:</span> {$categoriesList}</div>
              <div style='margin-bottom: 6px;'><span style='color: #C9A44A; font-weight: bold;'>Amount Submitted:</span> ₹{$totalAmount}</div>
              <div><span style='color: #C9A44A; font-weight: bold;'>Status:</span> <span style='background: rgba(245,158,11,0.2); color: #FBBF24; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;'>PAYMENT VERIFICATION PENDING</span></div>
            </div>
            <p style='color: rgba(255,255,255,0.8);'>
              Our administration team is currently verifying your payment screenshot. <strong>Once approved, your official WFF Tamil Nadu championship ticket will be issued and sent to this email address.</strong>
            </p>
            <div style='text-align: center; margin: 24px 0;'>
              <a href='{$statusUrl}' style='background: linear-gradient(90deg, #BF953F, #E8CE7A, #B38728); color: #040A12; font-weight: bold; font-size: 13px; text-transform: uppercase; letter-spacing: 1.5px; text-decoration: none; padding: 12px 24px; border-radius: 8px; display: inline-block;'>
                Check Registration Status
              </a>
            </div>
            <p style='color: rgba(255,255,255,0.5); font-size: 12px;'>
              Note: This is an acknowledgement receipt. Your official entry ticket will only be activated and valid after admin approval.
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
        ";

        return sendHtmlEmail($athleteEmail, $athleteName, $subject, $message, $db, $debugInfo, "ack_{$regNumber}");
    } catch (Exception $e) {
        $debugInfo = "Exception in sendRegistrationAcknowledgementEmail: " . $e->getMessage();
        error_log("[WFF Ack Email Error] " . $debugInfo);
        return false;
    }
}
