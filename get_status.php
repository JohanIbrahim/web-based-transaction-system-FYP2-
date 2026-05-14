<?php
/**
 * AJAX Endpoint for Order Status
 * 
 * Returns the current order status as JSON for auto-refresh on tracking page.
 * Called via fetch() from tracking.php every 15 seconds.
 */

require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json');

$orderId = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;

if ($orderId <= 0) {
    echo json_encode(['error' => 'Invalid order ID']);
    exit;
}

try {
    $pdo = getDBConnection();

    $stmt = $pdo->prepare('SELECT id, status, payment_status, updated_at FROM orders WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch();

    if ($order) {
        echo json_encode([
            'order_id'       => (int) $order['id'],
            'status'         => $order['status'],
            'payment_status' => $order['payment_status'],
            'updated_at'     => $order['updated_at'],
        ]);
    } else {
        echo json_encode(['error' => 'Order not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error']);
}
