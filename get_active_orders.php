<?php
/**
 * AJAX Endpoint: Get Active Orders Status
 * 
 * Returns JSON with current status and payment_status for requested order IDs.
 * Used by transaction-history.php for auto-refresh of active orders.
 */

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/customer_auth.php';

startSession();

// Ensure customer is logged in
if (!isset($_SESSION['customer_logged_in']) || $_SESSION['customer_logged_in'] !== true) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    $pdo = getDBConnection();
    $customerId = (int) $_SESSION['customer_id'];

    // Get order IDs from query string
    $orderIds = isset($_GET['order_ids']) ? $_GET['order_ids'] : [];

    if (empty($orderIds) || !is_array($orderIds)) {
        echo json_encode([]);
        exit;
    }

    // Sanitize to integers only
    $orderIds = array_map('intval', $orderIds);
    $orderIds = array_filter($orderIds, function($id) { return $id > 0; });

    if (empty($orderIds)) {
        echo json_encode([]);
        exit;
    }

    // Build placeholders
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));

    // Query only orders belonging to this customer
    $stmt = $pdo->prepare("
        SELECT id AS order_id, status, payment_status 
        FROM orders 
        WHERE id IN ($placeholders) 
          AND customer_id = ?
        ORDER BY id
    ");

    // Merge order IDs and customer ID into params
    $params = array_merge($orderIds, [$customerId]);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    echo json_encode($orders);

} catch (PDOException $e) {
    error_log('get_active_orders.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit;
}
