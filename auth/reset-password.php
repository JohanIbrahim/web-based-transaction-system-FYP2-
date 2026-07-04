<?php
/**
 * Reset Password Page — Step 3: Set New Password
 * 
 * User sets a new password after verifying email + security question.
 * Updates password_hash in database securely.
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

startSession();

// If already logged in, redirect
if (isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true) {
    header('Location: /smart-transaction/index.php');
    exit;
}
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: /smart-transaction/admin/dashboard.php');
    exit;
}

// Check if user has completed verification flow
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_user_id']) || !isset($_SESSION['reset_verified']) || $_SESSION['reset_verified'] !== true) {
    header('Location: /smart-transaction/auth/forgot-password.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($password)) {
        $error = 'Please enter a new password.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $pdo = getDBConnection();

            // Hash the new password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Update password in database
            $stmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id AND email = :email');
            $stmt->execute([
                ':password_hash' => $hashedPassword,
                ':id' => $_SESSION['reset_user_id'],
                ':email' => $_SESSION['reset_email']
            ]);

            if ($stmt->rowCount() > 0) {
                $success = 'Your password has been reset successfully! You can now log in with your new password.';

                // Clear reset session variables
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_user_id']);
                unset($_SESSION['reset_verified']);

                // Show success and redirect after a moment
                header('Refresh: 3; URL=/smart-transaction/auth/login.php');
            } else {
                $error = 'Failed to update password. Please try again.';
            }
        } catch (PDOException $e) {
            $error = 'An error occurred. Please try again later.';
        }
    }
}

$pageTitle = 'Reset Password — Smart Transaction';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="/smart-transaction/assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
<div class="auth-page">
    <!-- Left Panel — Branding -->
    <div class="auth-left">
        <div class="auth-logo">
            <a href="/smart-transaction/index.php" class="qs-logo qs-logo-lg qs-logo-white">
                <svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="50" cy="50" r="45" fill="currentColor" opacity="0.12"/>
                    <path d="M35 55c0-8 6-15 15-15s15 7 15 15" stroke="currentColor" stroke-width="3" fill="none"/>
                    <path d="M30 55h40" stroke="currentColor" stroke-width="3"/>
                    <path d="M45 40l-5-10" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                    <path d="M55 40l5-10" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                    <path d="M40 65c0 5 4 10 10 10s10-5 10-10" stroke="currentColor" stroke-width="2.5" fill="none"/>
                    <circle cx="42" cy="52" r="2" fill="currentColor"/>
                    <circle cx="58" cy="52" r="2" fill="currentColor"/>
                </svg>
                Smart Transaction
            </a>
        </div>
        <p class="auth-tagline">Smart. Simple. Seamless.</p>
        <div class="auth-benefits">
            <div class="auth-benefit">
                <span class="benefit-icon">&#128274;</span>
                <span>Secure password recovery</span>
            </div>
            <div class="auth-benefit">
                <span class="benefit-icon">&#128273;</span>
                <span>Identity verification via security questions</span>
            </div>
            <div class="auth-benefit">
                <span class="benefit-icon">&#128179;</span>
                <span>Your data stays protected</span>
            </div>
        </div>
    </div>

    <!-- Right Panel — Reset Password Form -->
    <div class="auth-right">
        <div class="auth-form-container">
            <div class="auth-logo-mobile">
                <a href="/smart-transaction/index.php" class="qs-logo qs-logo-lg">
                    <svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="50" cy="50" r="45" fill="currentColor" opacity="0.12"/>
                        <path d="M35 55c0-8 6-15 15-15s15 7 15 15" stroke="currentColor" stroke-width="3" fill="none"/>
                        <path d="M30 55h40" stroke="currentColor" stroke-width="3"/>
                        <path d="M45 40l-5-10" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                        <path d="M55 40l5-10" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                        <path d="M40 65c0 5 4 10 10 10s10-5 10-10" stroke="currentColor" stroke-width="2.5" fill="none"/>
                        <circle cx="42" cy="52" r="2" fill="currentColor"/>
                        <circle cx="58" cy="52" r="2" fill="currentColor"/>
                    </svg>
                    Smart Transaction
                </a>
            </div>

            <h1 class="auth-title">Reset Password</h1>
            <p class="auth-subtitle">Enter your new password</p>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <span class="alert-icon">&#10060;</span>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <span class="alert-icon">&#9989;</span>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
                <p class="mt-3 text-center">
                    <a href="/smart-transaction/auth/login.php" class="btn btn-primary">Sign In Now</a>
                </p>
            <?php else: ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label" for="password">New Password <span class="required">*</span></label>
                        <input type="password" id="password" name="password" class="form-input" 
                               placeholder="At least 6 characters" required minlength="6">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm New Password <span class="required">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" 
                               placeholder="Repeat your new password" required minlength="6">
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-lg">
                        Reset Password <i data-lucide="arrow-right" style="width:18px;height:18px;"></i>
                    </button>
                </form>

                <p class="mt-3 text-center" style="font-size: 0.9rem; color: var(--color-text-muted);">
                    <a href="/smart-transaction/auth/login.php">Back to Sign In</a>
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});
</script>
</body>
</html>
