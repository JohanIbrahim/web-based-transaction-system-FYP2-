<?php
/**
 * Login Page — Smart Transaction
 * 
 * Two-panel layout: left brand panel, right form panel.
 * Handles both customer and admin/staff login.
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        try {
            $pdo = getDBConnection();

            // Check users table (admin/staff/customer)
            $stmt = $pdo->prepare('SELECT id, name, email, password_hash, role, phone FROM users WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if (in_array($user['role'], ['admin', 'staff'])) {
                    // Admin/Staff login
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['user_id']         = (int) $user['id'];
                    $_SESSION['user_name']       = $user['name'];
                    $_SESSION['user_email']      = $user['email'];
                    $_SESSION['role']            = $user['role'];
                    $_SESSION['admin_role']      = $user['role'];

                    header('Location: /smart-transaction/admin/dashboard.php');
                    exit;
                } else {
                    // Customer login
                    $_SESSION['customer_logged_in'] = true;
                    $_SESSION['customer_id']        = (int) $user['id'];
                    $_SESSION['customer_name']      = $user['name'];
                    $_SESSION['customer_email']     = $user['email'];
                    $_SESSION['customer_phone']     = $user['phone'] ?? '';

                    header('Location: /smart-transaction/index.php');
                    exit;
                }
            }

            $error = 'Invalid email or password.';
        } catch (PDOException $e) {
            $error = 'Login failed. Please try again later.';
        }
    }
}

$pageTitle = 'Login — Smart Transaction';
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
                <?php
                $logoFile = __DIR__ . '/../uploads/logo.png';
                if (file_exists($logoFile)):
                ?>
                    <img src="/smart-transaction/uploads/logo.png?t=<?php echo filemtime($logoFile); ?>" alt="Smart Transaction" style="height: 40px; width: auto; filter: brightness(0) invert(1);">
                <?php else: ?>
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
                <?php endif; ?>
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

    <!-- Right Panel — Login Form -->
    <div class="auth-right">
        <div class="auth-form-container">
            <div class="auth-logo-mobile">
                <a href="/smart-transaction/index.php" class="qs-logo qs-logo-lg">
                    <?php
                    $logoFile = __DIR__ . '/../uploads/logo.png';
                    if (file_exists($logoFile)):
                    ?>
                        <img src="/smart-transaction/uploads/logo.png?t=<?php echo filemtime($logoFile); ?>" alt="Smart Transaction" style="height: 36px; width: auto;">
                    <?php else: ?>
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
                    <?php endif; ?>
                </a>
            </div>

            <h1 class="auth-title">Welcome Back</h1>
            <p class="auth-subtitle">Sign in to your account to continue</p>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <span class="alert-icon">&#10060;</span>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label" for="email">Email Address <span class="required">*</span></label>
                    <input type="email" id="email" name="email" class="form-input" 
                           placeholder="you@example.com" required
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" class="form-input" 
                           placeholder="Enter your password" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    Sign In <i data-lucide="arrow-right" style="width:18px;height:18px;"></i>
                </button>
            </form>

            <p class="mt-3 text-center" style="font-size: 0.9rem; color: var(--color-text-muted);">
                Don't have an account? <a href="/smart-transaction/auth/signup.php">Sign Up</a>
            </p>

            <!-- Demo Credentials -->
            <div class="demo-credentials">
                <p class="demo-credentials-title">Demo Login Credentials</p>
                <div class="demo-credentials-grid">
                    <div><strong>Admin</strong> : admin@smarttransaction.com</div>
                    <div><strong>Staff</strong> : staff@smarttransaction.com</div>
                    <div><strong>Customer</strong> : customer@smarttransaction.com</div>
                    <div><strong>Password</strong> : password</div>
                </div>
            </div>
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
