<?php
/**
 * Unified Logout Page
 * 
 * Handles logout for ALL roles: customer, staff, admin.
 * Clears appropriate session variables and redirects to login page.
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/customer_auth.php';

startSession();

// Check if admin/staff is logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    // Clear admin/staff session variables
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_name']);
    unset($_SESSION['admin_email']);
    unset($_SESSION['admin_role']);
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['user_id']);
    unset($_SESSION['user_name']);
    unset($_SESSION['user_email']);
    unset($_SESSION['role']);
} else {
    // Clear customer session variables
    customerLogout();
}

$_SESSION['flash_message'] = 'You have been logged out.';
$_SESSION['flash_type'] = 'info';

header('Location: /smart-transaction/auth/login.php');
exit;
