<?php
/**
 * Promotion Helper Functions
 * 
 * Handles auto-promotion (Grab-style) logic.
 * Discounts are automatically applied to tagged items — no customer action needed.
 */

/**
 * Get all active promotions with their product IDs
 * Returns array keyed by product_id => ['discount_percent', 'title', 'type']
 * Uses a cached result per request (static variable) to avoid repeated queries
 */
function getActivePromoProducts($pdo) {
    static $cache = null;
    if ($cache !== null) return $cache;

    $stmt = $pdo->prepare("
        SELECT pp.product_id, p.discount_percent, p.title, p.promotion_type
        FROM promotions p
        JOIN promotion_products pp ON p.id = pp.promotion_id
        WHERE p.is_active = 1
        AND p.start_date <= CURDATE()
        AND p.end_date >= CURDATE()
        ORDER BY p.discount_percent DESC
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $cache = [];
    foreach ($rows as $row) {
        // If same product has multiple promos, highest discount wins
        if (!isset($cache[$row['product_id']]) || 
            $row['discount_percent'] > $cache[$row['product_id']]['discount_percent']) {
            $cache[$row['product_id']] = [
                'discount_percent' => floatval($row['discount_percent']),
                'title'            => $row['title'],
                'type'             => $row['promotion_type'],
            ];
        }
    }
    return $cache;
}

/**
 * Get promo info for a single product_id
 * Returns promo array or null if no active promo
 */
function getProductPromo($pdo, $product_id) {
    $promos = getActivePromoProducts($pdo);
    return $promos[$product_id] ?? null;
}

/**
 * Calculate discounted price for a product
 */
function getDiscountedPrice($original_price, $discount_percent) {
    return round($original_price * (1 - $discount_percent / 100), 2);
}
