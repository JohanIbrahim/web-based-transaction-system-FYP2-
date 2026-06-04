<?php
/**
 * Admin / Staff Authentication Helper
 * 
 * Provides role-based access control functions for admin and staff users.
 * Uses $_SESSION keys set by auth/login.php:
 *   - $_SESSION['user_id']
 *   - $_SESSION['user_name']
 *   - $_SESSION['role'] ('admin' or 'staff')
 *   - $_SESSION['admin_logged_in']
 *   - $_SESSION['admin_role']
 */

/**
 * Check if an admin or staff user is logged in
 *
 * @return bool
 */
function isAdminLoggedIn(): bool
{
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Check if the logged-in user has admin role
 *
 * @return bool
 */
function isAdmin(): bool
{
    return isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin';
}

/**
 * Check if the logged-in user has staff role
 *
 * @return bool
 */
function isStaff(): bool
{
    return isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'staff';
}

/**
 * Require any admin/staff login to access a page.
 * Allows both admin and staff roles.
 * Redirects to login page if not authenticated.
 *
 * @return void
 */
function requireAdminLogin(): void
{
    startSession();

    if (!isAdminLoggedIn()) {
        $_SESSION['flash_message'] = 'Please log in to access this page.';
        $_SESSION['flash_type'] = 'info';
        header('Location: /smart-transaction/auth/login.php');
        exit();
    }
}

/**
 * Require admin-only access to a page.
 * Redirects staff users away with a flash message.
 *
 * @return void
 */
function requireAdminRole(): void
{
    requireAdminLogin();

    if (!isAdmin()) {
        $_SESSION['flash_message'] = 'Access denied. You do not have permission to view this page.';
        $_SESSION['flash_type'] = 'danger';
        header('Location: /smart-transaction/admin/orders.php');
        exit();
    }
}

/**
 * Check if any user (admin, staff, or customer) is logged in
 * Used by header.php for display purposes
 *
 * @return bool
 */
function isAdminOrCustomerLoggedIn(): bool
{
    return isAdminLoggedIn() || (isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true);
}
