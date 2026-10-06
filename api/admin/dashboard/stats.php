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

    // Total events & active
    $stmt = $db->query("SELECT COUNT(*) FROM events");
    $total_events = (int)$stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(*) FROM events WHERE status IN ('published', 'upcoming', 'ongoing')");
    $active_events = (int)$stmt->fetchColumn();

    // Champions & Officials counts
    $stmt = $db->query("SELECT COUNT(*) FROM champions");
    $total_champions = (int)$stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(*) FROM officials WHERE status = 1");
    $total_officials = (int)$stmt->fetchColumn();

    // Registrations stats
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'payment_pending' OR status = 'pending' THEN 1 ELSE 0 END) as payment_pending,
            SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status = 'cancelled' OR status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM registrations
    ");
    $regStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Recent 6 registrations (matching columns in database)
    $recentStmt = $db->query("
        SELECT 
            r.id,
            r.registration_number,
            r.athlete_name,
            r.phone,
            r.email,
            r.status as registration_status,
            r.created_at,
            e.event_name,
            c.name as category_name,
            (SELECT p.status FROM payments p WHERE p.registration_id = r.id ORDER BY p.id DESC LIMIT 1) as payment_status,
            (SELECT p.method FROM payments p WHERE p.registration_id = r.id ORDER BY p.id DESC LIMIT 1) as payment_method
        FROM registrations r
        JOIN events e ON r.event_id = e.id
        JOIN event_categories c ON r.category_id = c.id
        ORDER BY r.id DESC
        LIMIT 6
    ");
    $recentRegistrations = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

    // Events summary (Top 4 latest/upcoming events)
    $eventsStmt = $db->query("
        SELECT 
            e.id,
            e.event_name,
            e.event_date,
            e.venue,
            e.status,
            (SELECT COUNT(*) FROM registrations WHERE event_id = e.id) as registration_count,
            (SELECT COUNT(*) FROM event_categories WHERE event_id = e.id) as category_count
        FROM events e
        ORDER BY e.event_date DESC
        LIMIT 4
    ");
    $eventsSummary = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Pending payment proofs count (athletes who submitted payment / paid status needing approval)
    $pendingProofStmt = $db->query("
        SELECT COUNT(*) FROM registrations 
        WHERE status = 'paid'
    ");
    $pendingApprovalsCount = (int)$pendingProofStmt->fetchColumn();

    // Optional revenue calculation from payments table
    $confirmedRevenue = 0;
    $paidRevenue = 0;
    try {
        $stmt = $db->query("
            SELECT 
                COALESCE(SUM(CASE WHEN status = 'captured' THEN amount / 100 ELSE 0 END), 0) as confirmed_revenue,
                COALESCE(SUM(CASE WHEN status IN ('authorized', 'created') THEN amount / 100 ELSE 0 END), 0) as pending_revenue
            FROM payments
        ");
        $payStats = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($payStats) {
            $confirmedRevenue = (int)$payStats['confirmed_revenue'];
            $paidRevenue = (int)$payStats['pending_revenue'];
        }
    } catch (Exception $e) {
        // Fallback if payments table has different structure
        $confirmedRevenue = 0;
        $paidRevenue = 0;
    }

    sendResponse(true, [
        'active_events' => $active_events,
        'total_events' => $total_events,
        'total_champions' => $total_champions,
        'total_officials' => $total_officials,
        'pending_approvals_count' => $pendingApprovalsCount,
        'registrations' => [
            'total' => (int)($regStats['total'] ?? 0),
            'payment_pending' => (int)($regStats['payment_pending'] ?? 0),
            'paid' => (int)($regStats['paid'] ?? 0),
            'confirmed' => (int)($regStats['confirmed'] ?? 0),
            'rejected' => (int)($regStats['rejected'] ?? 0),
        ],
        'revenue' => [
            'confirmed' => $confirmedRevenue,
            'paid' => $paidRevenue,
            'pending' => 0,
            'total_projected' => $confirmedRevenue + $paidRevenue
        ],
        'recent_registrations' => $recentRegistrations ?: [],
        'events_summary' => $eventsSummary ?: []
    ]);

} catch (Exception $e) {
    error_log("Admin Dashboard Stats Error: " . $e->getMessage());
    sendResponse(false, null, "Failed to load dashboard statistics: " . $e->getMessage(), 500);
}
