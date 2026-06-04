<?php
/**
 * Admin Login Redirect
 * 
 * This page has been replaced by the unified login page at auth/login.php.
 * All roles (customer, staff, admin) now use the same login page.
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/admin_auth.php';

startSession();

// Handle logout
if (isset($_GET['logout'])) {
    logout();
    header('Location: /smart-transaction/auth/login.php');
    exit;
}

// If already logged in, redirect based on role
if (isAdminLoggedIn()) {
    if (isAdmin()) {
        header('Location: /smart-transaction/admin/dashboard.php');
    } else {
        header('Location: /smart-transaction/admin/orders.php');
    }
    exit;
}

// Redirect to unified login page
header('Location: /smart-transaction/auth/login.php');
exit;
