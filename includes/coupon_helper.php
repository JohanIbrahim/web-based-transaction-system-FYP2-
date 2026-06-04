<?php
/**
 * Coupon Helper Functions
 * 
 * Handles coupon tier logic, awarding, validation, and usage.
 * Used by cart.php, admin/orders.php, validate_coupon.php, etc.
 */

/**
 * Count total COMPLETED orders for a customer
 */
function getCustomerCompletedOrderCount($pdo, $customer_id) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM orders 
        WHERE customer_id = :customer_id 
        AND status = 'completed'
    ");
    $stmt->execute([':customer_id' => $customer_id]);
    return (int) $stmt->fetchColumn();
}

/**
 * Determine coupon tier based on order count
 * Returns array [tier, tier_name, discount_percent] or null if no reward
 */
function getCouponTierForOrderCount($orderCount) {
    if ($orderCount >= 20) return ['tier' => 5, 'name' => 'Star Member',       'discount' => 30];
    if ($orderCount >= 15) return ['tier' => 4, 'name' => 'Elite Member',      'discount' => 25];
    if ($orderCount >= 10) return ['tier' => 3, 'name' => 'VIP Member',        'discount' => 20];
    if ($orderCount >= 5)  return ['tier' => 2, 'name' => 'Loyal Member',      'discount' => 15];
    if ($orderCount >= 2)  return ['tier' => 1, 'name' => 'Returning Customer', 'discount' => 10];
    return null; // first order — no coupon yet
}

/**
 * Generate a unique coupon code
 * Format: PREFIX-CUSTOMERID-RANDOM (e.g. RETURN10-42-X7K9)
 */
function generateCouponCode($prefix, $customer_id) {
    $random = strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
    return $prefix . '-' . $customer_id . '-' . $random;
}

/**
 * Award a coupon to customer after order completion
 * Called when order status changes to 'completed'
 */
function awardCouponIfEligible($pdo, $customer_id) {
    $orderCount = getCustomerCompletedOrderCount($pdo, $customer_id);
    $tier = getCouponTierForOrderCount($orderCount);
    
    if (!$tier) return null; // no reward for first order
    
    // Check if customer already has an unused coupon for this tier
    $stmt = $pdo->prepare("
        SELECT id FROM coupons 
        WHERE customer_id = :customer_id 
        AND tier = :tier 
        AND is_used = 0 
        AND expires_at > NOW()
    ");
    $stmt->execute([':customer_id' => $customer_id, ':tier' => $tier['tier']]);
    if ($stmt->fetch()) return null; // already has active coupon for this tier
    
    // Generate unique coupon code
    $prefixes = [1 => 'RETURN10', 2 => 'LOYAL15', 3 => 'VIP20', 4 => 'ELITE25', 5 => 'STAR30'];
    $prefix = $prefixes[$tier['tier']];
    
    do {
        $code = generateCouponCode($prefix, $customer_id);
        $check = $pdo->prepare("SELECT id FROM coupons WHERE coupon_code = :code");
        $check->execute([':code' => $code]);
    } while ($check->fetch()); // ensure uniqueness
    
    // Insert the coupon
    $stmt = $pdo->prepare("
        INSERT INTO coupons 
        (customer_id, coupon_code, discount_percent, tier, tier_name, 
         is_used, issued_at, expires_at)
        VALUES 
        (:customer_id, :code, :discount, :tier, :tier_name, 
         0, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY))
    ");
    $stmt->execute([
        ':customer_id' => $customer_id,
        ':code'        => $code,
        ':discount'    => $tier['discount'],
        ':tier'        => $tier['tier'],
        ':tier_name'   => $tier['name'],
    ]);
    
    return [
        'code'     => $code,
        'discount' => $tier['discount'],
        'tier_name'=> $tier['name'],
        'expires'  => date('d M Y', strtotime('+30 days'))
    ];
}

/**
 * Validate a coupon code at checkout
 * Returns coupon data array or error array
 */
function validateCoupon($pdo, $coupon_code, $customer_id, $order_total) {
    $minimum = 5.00;
    
    if ($order_total < $minimum) {
        return ['error' => 'Minimum order of RM ' . number_format($minimum, 2) . ' required to use a coupon.'];
    }
    
    $stmt = $pdo->prepare("
        SELECT * FROM coupons 
        WHERE coupon_code = :code 
        AND customer_id = :customer_id
    ");
    $stmt->execute([':code' => trim(strtoupper($coupon_code)), ':customer_id' => $customer_id]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$coupon) {
        return ['error' => 'Invalid coupon code. This coupon does not belong to your account.'];
    }
    if ($coupon['is_used']) {
        return ['error' => 'This coupon has already been used.'];
    }
    if (strtotime($coupon['expires_at']) < time()) {
        return ['error' => 'This coupon has expired.'];
    }
    
    $discount_amount = round($order_total * ($coupon['discount_percent'] / 100), 2);
    $final_total = $order_total - $discount_amount;
    
    return [
        'valid'           => true,
        'coupon_id'       => $coupon['id'],
        'coupon_code'     => $coupon['coupon_code'],
        'discount_percent'=> $coupon['discount_percent'],
        'discount_amount' => $discount_amount,
        'final_total'     => $final_total,
        'tier_name'       => $coupon['tier_name'],
    ];
}

/**
 * Mark coupon as used after successful order
 */
function markCouponAsUsed($pdo, $coupon_id, $order_id) {
    $stmt = $pdo->prepare("
        UPDATE coupons 
        SET is_used = 1, used_in_order_id = :order_id, used_at = NOW()
        WHERE id = :coupon_id
    ");
    $stmt->execute([':order_id' => $order_id, ':coupon_id' => $coupon_id]);
}
