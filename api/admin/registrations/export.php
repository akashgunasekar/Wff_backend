<?php
require_once __DIR__ . '/../../../helpers/cors.php';
require_once __DIR__ . '/../../../helpers/response.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/auth.php';

require_once __DIR__ . '/../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$admin = requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, null, "Method not allowed.", 405);
}

try {
    $db = (new Database())->getConnection();

    // Fetch Filters
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $paymentStatus = isset($_GET['payment_status']) ? trim($_GET['payment_status']) : '';
    $paymentMethod = isset($_GET['payment_method']) ? trim($_GET['payment_method']) : '';
    $eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
    
    $fromDate = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
    $toDate = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';

    $where = ["1=1"];
    $params = [];
    
    if ($status !== '') {
        $where[] = "r.status = ?";
        $params[] = $status;
    }

    if ($paymentStatus !== '') {
        $where[] = "EXISTS (SELECT 1 FROM payments p WHERE p.registration_id = r.id AND p.status = ? ORDER BY p.id DESC LIMIT 1)";
        $params[] = $paymentStatus;
    }

    if ($paymentMethod !== '') {
        $where[] = "EXISTS (SELECT 1 FROM payments p WHERE p.registration_id = r.id AND p.method = ? ORDER BY p.id DESC LIMIT 1)";
        $params[] = $paymentMethod;
    }

    if ($eventId > 0) {
        $where[] = "r.event_id = ?";
        $params[] = $eventId;
    }
    
    if ($fromDate !== '') {
        $where[] = "DATE(r.created_at) >= ?";
        $params[] = $fromDate;
    }
    
    if ($toDate !== '') {
        $where[] = "DATE(r.created_at) <= ?";
        $params[] = $toDate;
    }

    // Resolve Event Name for Filename
    $eventNameForFile = "All-Events";
    if ($eventId > 0) {
        $evtStmt = $db->prepare("SELECT slug FROM events WHERE id = ?");
        $evtStmt->execute([$eventId]);
        $evtSlug = $evtStmt->fetchColumn();
        if ($evtSlug) {
            $eventNameForFile = $evtSlug;
        }
    }

    $whereClause = implode(' AND ', $where);

    // Fetch master categories map for quick resolution
    $catMap = [];
    $catQuery = $db->query("SELECT id, name FROM event_categories");
    while ($row = $catQuery->fetch(PDO::FETCH_ASSOC)) {
        $catMap[$row['id']] = strtoupper($row['name']);
    }

    // Get site settings metadata
    // Instead of querying site_settings in a loop, fetch all relevant metadata at once
    // We can do this efficiently by fetching all registration metadata
    $metaQuery = $db->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'registration_%_meta'");
    $metaMap = [];
    while ($row = $metaQuery->fetch(PDO::FETCH_ASSOC)) {
        $metaMap[$row['setting_key']] = json_decode($row['setting_value'], true) ?: [];
    }
    
    // Payments map
    $paymentsQuery = $db->query("SELECT registration_id, method, status, razorpay_order_id, razorpay_payment_id, updated_at FROM payments ORDER BY id ASC");
    $paymentsMap = [];
    while ($row = $paymentsQuery->fetch(PDO::FETCH_ASSOC)) {
        // Keep latest payment record for each registration
        $paymentsMap[$row['registration_id']] = $row;
    }

    // Build the query
    $sql = "SELECT r.id, r.registration_number, r.athlete_name, r.phone, r.email, r.date_of_birth, r.status as registration_status, r.created_at, e.event_name
            FROM registrations r
            JOIN events e ON r.event_id = e.id
            WHERE $whereClause
            ORDER BY r.id ASC";
            
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    if ($stmt->rowCount() === 0) {
        sendResponse(false, null, "No registrations found for the selected filters.", 404);
        exit;
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Registrations');

    // Headers
    $headers = [
        'Registration Number', 'Registration Date', 'Athlete Name', 'Phone', 'Email',
        'Instagram ID', 'DOB', 'Age', 'Height', 'Weight', 'Event', 'Categories',
        'Base Category Fees', 'Additional Category Discount', 'Tan Spray Requested',
        'Tan Spray Fee', 'Final Total', 'Payment Method', 'Payment Status',
        'Razorpay Order ID', 'Razorpay Payment ID', 'Payment Date'
    ];
    
    $sheet->fromArray($headers, null, 'A1');
    $sheet->getStyle('A1:V1')->getFont()->setBold(true);
    $sheet->getStyle('A1:V1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEADDAC');
    $sheet->freezePane('A2');

    $rowNum = 2;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $regId = $row['id'];
        
        // Extended Data
        $meta = $metaMap["registration_{$regId}_meta"] ?? [];
        $payment = $paymentsMap[$regId] ?? null;
        
        // Age calculation based on registration date
        $age = '';
        if (!empty($row['date_of_birth'])) {
            $dob = new DateTime($row['date_of_birth']);
            $regDate = new DateTime($row['created_at']);
            $age = $regDate->diff($dob)->y;
        }
        
        // Categories
        $categoryNames = [];
        $categoryIds = $meta['category_ids'] ?? [];
        foreach ($categoryIds as $cId) {
            if (isset($catMap[$cId])) {
                $categoryNames[] = $catMap[$cId];
            }
        }
        $categoriesString = implode(', ', $categoryNames);
        
        // Pricing
        $baseTotal = isset($meta['pricing']['base_total']) ? (int)$meta['pricing']['base_total'] : 0;
        $discountTotal = isset($meta['pricing']['discount_total']) ? (int)$meta['pricing']['discount_total'] : 0;
        $tsRequested = !empty($meta['tan_spray_requested']) ? 'YES' : 'NO';
        $tsFee = isset($meta['pricing']['tan_spray_fee']) ? (int)$meta['pricing']['tan_spray_fee'] : 0;
        $finalTotal = isset($meta['pricing']['final_total']) ? (int)$meta['pricing']['final_total'] : 0;
        
        // Payment
        $pmMethod = '';
        $pmStatus = '';
        $rzpOrderId = '';
        $rzpPaymentId = '';
        $pmDate = '';
        
        if ($payment) {
            $method = strtolower($payment['method'] ?? '');
            $status = strtolower($payment['status'] ?? '');
            
            if ($method === 'cash') {
                $pmMethod = 'CASH';
                if ($status === 'created') $pmStatus = 'CASH DUE';
                else if ($status === 'captured') $pmStatus = 'CASH PAID';
                else $pmStatus = strtoupper($status);
                
                if ($status === 'captured') {
                    $pmDate = $payment['updated_at'];
                }
            } else if ($method === 'online') {
                $pmMethod = 'ONLINE';
                if ($status === 'created') $pmStatus = 'PAYMENT CREATED / PENDING';
                else if ($status === 'captured') $pmStatus = 'PAID';
                else $pmStatus = strtoupper($status);
                
                $rzpOrderId = $payment['razorpay_order_id'] ?? '';
                $rzpPaymentId = $payment['razorpay_payment_id'] ?? '';
                $pmDate = $payment['updated_at'];
            }
        }

        $rowData = [
            $row['registration_number'],
            $row['created_at'],
            $row['athlete_name'],
            $row['phone'],
            $row['email'],
            $meta['instagram_id'] ?? '',
            $row['date_of_birth'],
            $age,
            $meta['height'] ?? '',
            $meta['weight'] ?? '',
            $row['event_name'],
            $categoriesString,
            $baseTotal,
            $discountTotal,
            $tsRequested,
            $tsFee,
            $finalTotal,
            $pmMethod,
            $pmStatus,
            $rzpOrderId,
            $rzpPaymentId,
            $pmDate
        ];
        
        $sheet->fromArray($rowData, null, 'A' . $rowNum);
        
        // Apply numeric formatting
        $sheet->getStyle("M{$rowNum}:N{$rowNum}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("P{$rowNum}:Q{$rowNum}")->getNumberFormat()->setFormatCode('#,##0');
        
        $rowNum++;
    }

    // Auto-size columns
    foreach (range('A', 'V') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    $sheet->setAutoFilter($sheet->calculateWorksheetDimension());

    $dateStr = date('Y-m-d');
    $filename = "WFFTN-Registrations-{$eventNameForFile}-{$dateStr}.xlsx";

    // Output headers
    ob_end_clean();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Expires: Fri, 11 Jan 1990 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate');
    header('Pragma: public');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    error_log("Admin Registrations Export Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to export registrations.", 500);
}
