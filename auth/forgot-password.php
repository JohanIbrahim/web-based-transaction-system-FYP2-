<?php
/**
 * Forgot Password Page — Step 1: Enter Email
 * 
 * User enters their email address.
 * If email exists, redirect to verify-security.php with email in session.
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

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $pdo = getDBConnection();

            // Check if email exists in users table (customer only)
            $stmt = $pdo->prepare('SELECT id, name, security_question FROM users WHERE email = :email AND role = :role LIMIT 1');
            $stmt->execute([':email' => $email, ':role' => 'customer']);
            $user = $stmt->fetch();

            if ($user) {
                // Check if user has security question set
                if (empty($user['security_question'])) {
                    $error = 'This account does not have a security question set. Please contact support.';
                } else {
                    // Store email in session for verification flow
                    $_SESSION['reset_email'] = $email;
                    $_SESSION['reset_user_id'] = (int) $user['id'];
                    $_SESSION['reset_verified'] = false;

                    // Redirect to security question verification
                    header('Location: /smart-transaction/auth/verify-security.php');
                    exit;
                }
            } else {
                // Don't reveal whether email exists for security
                $error = 'No account found with this email address.';
            }
        } catch (PDOException $e) {
            $error = 'An error occurred. Please try again later.';
        }
    }
}

$pageTitle = 'Forgot Password — Smart Transaction';
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

    <!-- Right Panel — Forgot Password Form -->
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

            <h1 class="auth-title">Forgot Password</h1>
            <p class="auth-subtitle">Enter your email to reset your password</p>

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
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label" for="email">Email Address <span class="required">*</span></label>
                    <input type="email" id="email" name="email" class="form-input" 
                           placeholder="you@example.com" required
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    Continue <i data-lucide="arrow-right" style="width:18px;height:18px;"></i>
                </button>
            </form>

            <p class="mt-3 text-center" style="font-size: 0.9rem; color: var(--color-text-muted);">
                <a href="/smart-transaction/auth/login.php">Back to Sign In</a>
            </p>
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
