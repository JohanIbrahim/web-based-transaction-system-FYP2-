<?php
/**
 * AJAX endpoint to fetch promotion data for editing
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin_auth.php';

startSession();
requireAdminRole();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

try {
    $pdo = getDBConnection();

    $stmt = $pdo->prepare("SELECT * FROM promotions WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $promo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$promo) {
        http_response_code(404);
        echo json_encode(['error' => 'Promotion not found']);
        exit;
    }

    // Fetch product IDs
    $prodStmt = $pdo->prepare("SELECT product_id FROM promotion_products WHERE promotion_id = :id");
    $prodStmt->execute([':id' => $id]);
    $productIds = $prodStmt->fetchAll(PDO::FETCH_COLUMN);

    $promo['product_ids'] = $productIds;

    header('Content-Type: application/json');
    echo json_encode($promo);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
