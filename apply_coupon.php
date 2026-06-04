<?php
/**
 * Apply Coupon AJAX Endpoint
 * 
 * Called when customer clicks "Use This Coupon" on a coupon card.
 * Validates the coupon and stores it in $_SESSION['applied_coupon'].
 * Returns JSON with discount details for instant UI update.
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/customer_auth.php';
require_once __DIR__ . '/includes/coupon_helper.php';

header('Content-Type: application/json');

startSession();

if (!isCustomerLoggedIn()) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$coupon_id   = intval($_POST['coupon_id'] ?? 0);
$order_total = floatval($_POST['order_total'] ?? 0);
$customer_id = (int) $_SESSION['customer_id'];

if ($coupon_id <= 0) {
    echo json_encode(['error' => 'Invalid coupon.']);
    exit;
}

try {
    $pdo = getDBConnection();

    // Verify coupon belongs to this customer and is still valid
    $stmt = $pdo->prepare("
        SELECT * FROM coupons 
        WHERE id = :id 
        AND customer_id = :customer_id 
        AND is_used = 0 
        AND expires_at > NOW()
    ");
    $stmt->execute([':id' => $coupon_id, ':customer_id' => $customer_id]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        echo json_encode(['error' => 'Coupon is invalid or has expired.']);
        exit;
    }

    $discount_amount = round($order_total * ($coupon['discount_percent'] / 100), 2);
    $final_total     = round($order_total - $discount_amount, 2);

    // Store in session
    $_SESSION['applied_coupon'] = [
        'coupon_id'        => (int) $coupon['id'],
        'coupon_code'      => $coupon['coupon_code'],
        'discount_percent' => (float) $coupon['discount_percent'],
        'discount_amount'  => $discount_amount,
        'final_total'      => $final_total,
        'tier_name'        => $coupon['tier_name'],
        'valid'            => true,
    ];

    echo json_encode([
        'success'          => true,
        'discount_percent' => (float) $coupon['discount_percent'],
        'discount_amount'  => number_format($discount_amount, 2),
        'final_total'      => number_format($final_total, 2),
        'tier_name'        => $coupon['tier_name'],
        'coupon_code'      => $coupon['coupon_code'],
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error.']);
    exit;
}
