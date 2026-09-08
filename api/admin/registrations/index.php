<?php
require_once __DIR__ . '/../../../helpers/cors.php';
require_once __DIR__ . '/../../../helpers/response.php';
require_once __DIR__ . '/../../../helpers/validation.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/auth.php';

$admin = requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, null, "Method not allowed.", 405);
}

try {
    $db = (new Database())->getConnection();

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    if (!in_array($limit, [20, 50, 100])) $limit = 20;
    $offset = ($page - 1) * $limit;

    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $paymentStatus = isset($_GET['payment_status']) ? trim($_GET['payment_status']) : '';
    $paymentMethod = isset($_GET['payment_method']) ? trim($_GET['payment_method']) : '';
    $eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
    $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
    $fromDate = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
    $toDate = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';

    $where = ["1=1"];
    $params = [];

    if ($search !== '') {
        $where[] = "(r.registration_number LIKE ? OR r.athlete_name LIKE ? OR r.phone LIKE ? OR r.email LIKE ?)";
        $searchTerm = "%{$search}%";
        array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    }
    
    if ($status !== '') {
        $where[] = "r.status = ?";
        $params[] = $status;
    }

    if ($paymentStatus !== '') {
        // Find latest payment status using a subquery logic or direct join if we assume the latest payment governs it
        // A simple way is to use EXISTS for the specific status if we want to filter by the latest payment
        // We will just filter if any payment has this status. A better way:
        $where[] = "EXISTS (SELECT 1 FROM payments p WHERE p.registration_id = r.id AND p.status = ? ORDER BY p.id DESC LIMIT 1)";
        $params[] = $paymentStatus;
    }

    if ($eventId > 0) {
        $where[] = "r.event_id = ?";
        $params[] = $eventId;
    }

    if ($categoryId > 0) {
        $where[] = "r.category_id = ?";
        $params[] = $categoryId;
    }

    if ($paymentMethod !== '') {
        $where[] = "EXISTS (SELECT 1 FROM payments p WHERE p.registration_id = r.id AND p.method = ? ORDER BY p.id DESC LIMIT 1)";
        $params[] = $paymentMethod;
    }
    
    if ($fromDate !== '') {
        $where[] = "DATE(r.created_at) >= ?";
        $params[] = $fromDate;
    }
    
    if ($toDate !== '') {
        $where[] = "DATE(r.created_at) <= ?";
        $params[] = $toDate;
    }

    $whereClause = implode(' AND ', $where);

    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM registrations r WHERE $whereClause";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $totalRows = $countStmt->fetch()['total'];

    // Get paginated data
    $sql = "SELECT r.id, r.registration_number, r.athlete_name, r.phone, r.email, r.status as registration_status, r.created_at,
            e.event_name, c.name as category_name,
            (SELECT p.status FROM payments p WHERE p.registration_id = r.id ORDER BY p.id DESC LIMIT 1) as payment_status,
            (SELECT p.method FROM payments p WHERE p.registration_id = r.id ORDER BY p.id DESC LIMIT 1) as payment_method
            FROM registrations r
            JOIN events e ON r.event_id = e.id
            JOIN event_categories c ON r.category_id = c.id
            WHERE $whereClause
            ORDER BY r.id DESC
            LIMIT $limit OFFSET $offset";
            
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(true, [
        'registrations' => $registrations,
        'pagination' => [
            'total' => $totalRows,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($totalRows / $limit)
        ]
    ]);

} catch (Exception $e) {
    error_log("Admin Registrations Index Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to load registrations.", 500);
}
