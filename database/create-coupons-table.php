<?php
/**
 * Create Coupons Table
 * 
 * Run this script ONCE to set up the coupons table
 * and add coupon_id column to orders table.
 * 
 * Usage: php database/create-coupons-table.php
 *        or access via browser
 */

require_once __DIR__ . '/../includes/db.php';

try {
    $pdo = getDBConnection();
    
    // Create coupons table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS coupons (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            customer_id     INT NOT NULL,
            coupon_code     VARCHAR(30) NOT NULL UNIQUE,
            discount_percent DECIMAL(5,2) NOT NULL,
            discount_amount  DECIMAL(10,2) NOT NULL DEFAULT 0,
            tier            INT NOT NULL,
            tier_name       VARCHAR(50) NOT NULL,
            is_used         TINYINT(1) DEFAULT 0,
            used_in_order_id INT NULL,
            issued_at       DATETIME NOT NULL,
            expires_at      DATETIME NOT NULL,
            used_at         DATETIME NULL,
            FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ coupons table created.\n";
    
    // Create index for faster lookup
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_coupons_customer ON coupons(customer_id, is_used)");
    echo "✓ index on coupons(customer_id, is_used) created.\n";
    
    // Add coupon_id column to orders table if not exists
    $stmt = $pdo->query("SHOW COLUMNS FROM orders LIKE 'coupon_id'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN coupon_id INT NULL AFTER total_amount");
        echo "✓ coupon_id column added to orders table.\n";
    } else {
        echo "✓ coupon_id column already exists in orders table.\n";
    }
    
    echo "\nSUCCESS: Coupons table and related schema changes completed.";
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
