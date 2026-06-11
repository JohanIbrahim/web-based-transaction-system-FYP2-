<?php
/**
 * Logout Script — Smart Transaction
 * 
 * Destroys session and redirects to login page.
 */

require_once __DIR__ . '/../includes/session.php';

startSession();

// Clear all session variables
$_SESSION = [];

// Destroy the session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

// Destroy the session
session_destroy();

// Redirect to login
header('Location: /smart-transaction/auth/login.php');
exit;
