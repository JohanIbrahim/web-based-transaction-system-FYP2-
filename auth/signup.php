<?php
/**
 * Sign Up Page — Smart Transaction
 * 
 * Two-panel layout: left brand panel, right form panel.
 * Customer registration with validation.
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/customer_auth.php';

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
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $pdo = getDBConnection();

            // Check if email already exists
            $check = $pdo->prepare('SELECT id FROM customers WHERE email = :email LIMIT 1');
            $check->execute([':email' => $email]);
            if ($check->fetch()) {
                $error = 'An account with this email already exists.';
            } else {
                // Insert new customer
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO customers (name, email, phone, password, created_at) VALUES (:name, :email, :phone, :password, NOW())');
                $stmt->execute([
                    ':name'     => $name,
                    ':email'    => $email,
                    ':phone'    => $phone,
                    ':password' => $hashed,
                ]);

                $success = 'Account created successfully! You can now log in.';
            }
        } catch (PDOException $e) {
            $error = 'Registration failed. Please try again later.';
        }
    }
}

$pageTitle = 'Sign Up — Smart Transaction';
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
                <span class="benefit-icon">&#9749;</span>
                <span>Freshly prepared food & beverages</span>
            </div>
            <div class="auth-benefit">
                <span class="benefit-icon">&#128722;</span>
                <span>Easy online ordering</span>
            </div>
            <div class="auth-benefit">
                <span class="benefit-icon">&#127873;</span>
                <span>Exclusive coupons & promotions</span>
            </div>
            <div class="auth-benefit">
                <span class="benefit-icon">&#128179;</span>
                <span>Secure payment options</span>
            </div>
        </div>
    </div>

    <!-- Right Panel — Sign Up Form -->
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

            <h1 class="auth-title">Create Account</h1>
            <p class="auth-subtitle">Join Smart Transaction and start ordering</p>

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
                    <label class="form-label" for="name">Full Name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" class="form-input" 
                           placeholder="Your name" required
                           value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email Address <span class="required">*</span></label>
                    <input type="email" id="email" name="email" class="form-input" 
                           placeholder="you@example.com" required
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-input" 
                           placeholder="012-3456789"
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" class="form-input" 
                           placeholder="At least 6 characters" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm Password <span class="required">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-input" 
                           placeholder="Repeat your password" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    Create Account <i data-lucide="arrow-right" style="width:18px;height:18px;"></i>
                </button>
            </form>

            <p class="mt-3 text-center" style="font-size: 0.9rem; color: var(--color-text-muted);">
                Already have an account? <a href="/smart-transaction/auth/login.php">Sign In</a>
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
