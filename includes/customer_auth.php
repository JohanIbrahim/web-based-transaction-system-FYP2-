<?php
/**
 * Customer Authentication Helper
 * 
 * Provides functions for customer login/session management.
 * Separate from admin/staff auth to avoid session key conflicts.
 */

/**
 * Check if a customer is currently logged in
 *
 * @return bool
 */
function isCustomerLoggedIn(): bool
{
    return isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true;
}

/**
 * Require customer to be logged in to access a page.
 * Redirects to login page if not authenticated.
 *
 * @return void
 */
function requireCustomerLogin(): void
{
    startSession();

    if (!isCustomerLoggedIn()) {
        $_SESSION['flash_message'] = 'Please log in to continue.';
        $_SESSION['flash_type'] = 'info';
        header('Location: /smart-transaction/auth/login.php');
        exit;
    }
}

/**
 * Get the logged-in customer's name
 *
 * @return string
 */
function getCustomerName(): string
{
    return $_SESSION['customer_name'] ?? 'Guest';
}

/**
 * Log out the customer by clearing customer session variables
 *
 * @return void
 */
function customerLogout(): void
{
    unset($_SESSION['customer_id']);
    unset($_SESSION['customer_name']);
    unset($_SESSION['customer_email']);
    unset($_SESSION['customer_phone']);
    unset($_SESSION['customer_logged_in']);
    unset($_SESSION['cart']);
}
