<?php
/**
 * Session Handler
 * 
 * Provides session management functions for the application.
 * Sessions are started on every page via this file.
 */

/**
 * Start a secure session if not already started
 *
 * @return void
 */
function startSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        // Secure session settings
        ini_set('session.use_strict_mode', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_samesite', 'Lax');

        session_start();
    }
}

/**
 * Check if a user is currently logged in
 *
 * @return bool
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

/**
 * Require a specific role to access a page.
 * Redirects to login if not authenticated, or shows 403 if wrong role.
 *
 * @param string|array $role Required role(s) — single string or array of allowed roles
 * @return void
 */
function requireRole($role): void
{
    startSession();

    if (!isLoggedIn()) {
        header('Location: /smart-transaction/admin/login.php');
        exit;
    }

    $allowedRoles = is_array($role) ? $role : [$role];

    if (!in_array($_SESSION['role'], $allowedRoles, true)) {
        http_response_code(403);
        die('Access denied. You do not have permission to view this page.');
    }
}

/**
 * Log out the current user by destroying the session
 *
 * @return void
 */
function logout(): void
{
    startSession();

    // Clear all session variables
    $_SESSION = [];

    // Delete the session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    // Destroy the session
    session_destroy();
}
