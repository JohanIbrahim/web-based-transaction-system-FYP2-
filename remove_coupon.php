<?php
/**
 * Remove Coupon AJAX Endpoint
 * 
 * Called when customer clicks "Remove" on an applied coupon.
 * Clears the coupon from session and returns success.
 */

require_once __DIR__ . '/includes/session.php';

header('Content-Type: application/json');

startSession();

unset($_SESSION['applied_coupon']);

echo json_encode(['success' => true]);
