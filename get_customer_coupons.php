<?php
/**
 * Get Customer Coupons AJAX Endpoint
 * 
 * Returns all active (unused, not expired) coupons for the logged-in customer.
 * Each coupon includes calculated savings based on current order total.
 * Called from cart.php on page load.
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/customer_auth.php';

header('Content-Type: application/json');

startSession();

if (!isCustomerLoggedIn()) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$customer_id = (int) $_SESSION['customer_id'];
$order_total = floatval($_POST['order_total'] ?? 0);

try {
    $pdo = getDBConnection();

    $stmt = $pdo->prepare("
        SELECT * FROM coupons 
        WHERE customer_id = :customer_id 
        AND is_used = 0 
        AND expires_at > NOW()
        ORDER BY discount_percent DESC
    ");
    $stmt->execute([':customer_id' => $customer_id]);
    $coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate savings for each coupon based on current order total
    foreach ($coupons as &$coupon) {
        $coupon['discount_amount'] = round($order_total * ($coupon['discount_percent'] / 100), 2);
        $coupon['final_total'] = round($order_total - $coupon['discount_amount'], 2);
        $coupon['expires_formatted'] = date('d M Y', strtotime($coupon['expires_at']));
    }
    unset($coupon);

    echo json_encode(['coupons' => $coupons, 'order_total' => $order_total]);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error']);
    exit;
}
