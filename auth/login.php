<?php
/**
 * Unified Login Page
 * 
 * Single login page for ALL roles: customer, staff, admin.
 * Detects role from users table and sets appropriate session variables.
 * Redirects to role-appropriate dashboard after login.
 */

ob_start();

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

startSession();

$pageTitle = 'Log In - Smart Transaction System';

$error = '';
$rememberedEmail = '';

// Check for Remember Me cookie
if (isset($_COOKIE['customer_remember_email'])) {
    $rememberedEmail = $_COOKIE['customer_remember_email'];
}

// If already logged in as customer, redirect to menu
if (isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true) {
    header('Location: /smart-transaction/index.php');
    exit;
}

// If already logged in as admin/staff, redirect accordingly
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin') {
        header('Location: /smart-transaction/admin/dashboard.php');
    } else {
        header('Location: /smart-transaction/admin/orders.php');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($email) || empty($password)) {
        $error = 'Invalid email or password.';
    } else {
        try {
            $pdo = getDBConnection();
            // Query all users regardless of role
            $stmt = $pdo->prepare('SELECT id, name, email, phone, password_hash, role FROM users WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);

                // Handle Remember Me
                if ($remember) {
                    setcookie('customer_remember_email', $email, time() + (86400 * 7), '/', '', false, true);
                } else {
                    if (isset($_COOKIE['customer_remember_email'])) {
                        setcookie('customer_remember_email', '', time() - 3600, '/');
                    }
                }

                // Set session variables based on role
                if ($user['role'] === 'customer') {
                    $_SESSION['customer_id']         = (int) $user['id'];
                    $_SESSION['customer_name']       = $user['name'];
                    $_SESSION['customer_email']      = $user['email'];
                    $_SESSION['customer_phone']      = $user['phone'];
                    $_SESSION['customer_logged_in']  = true;

                    header('Location: /smart-transaction/index.php');
                    exit;
                } elseif ($user['role'] === 'staff') {
                    $_SESSION['admin_id']            = (int) $user['id'];
                    $_SESSION['admin_name']          = $user['name'];
                    $_SESSION['admin_email']         = $user['email'];
                    $_SESSION['admin_role']          = 'staff';
                    $_SESSION['admin_logged_in']     = true;
                    $_SESSION['user_id']             = (int) $user['id'];
                    $_SESSION['user_name']           = $user['name'];
                    $_SESSION['user_email']          = $user['email'];
                    $_SESSION['role']                = 'staff';

                    header('Location: /smart-transaction/admin/orders.php');
                    exit;
                } elseif ($user['role'] === 'admin') {
                    $_SESSION['admin_id']            = (int) $user['id'];
                    $_SESSION['admin_name']          = $user['name'];
                    $_SESSION['admin_email']         = $user['email'];
                    $_SESSION['admin_role']          = 'admin';
                    $_SESSION['admin_logged_in']     = true;
                    $_SESSION['user_id']             = (int) $user['id'];
                    $_SESSION['user_name']           = $user['name'];
                    $_SESSION['user_email']          = $user['email'];
                    $_SESSION['role']                = 'admin';

                    header('Location: /smart-transaction/admin/dashboard.php');
                    exit;
                } else {
                    $error = 'Invalid account type.';
                }
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            $error = 'An error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="/smart-transaction/assets/css/style.css">
    <style>
        /* Clean login page - no navbar, no header menu */
        body {
            background: #f7f6f2;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 1rem;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }

        .login-wrapper {
            width: 100%;
            max-width: 420px;
        }

        .login-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            padding: 2.5rem 2rem;
        }

        .login-logo {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .login-logo .brand-icon {
            font-size: 2.5rem;
            display: block;
            margin-bottom: 0.5rem;
        }

        .login-logo h2 {
            color: #01696f;
            font-size: 1.5rem;
            margin: 0 0 0.25rem 0;
        }

        .login-logo p {
            color: #78716c;
            font-size: 0.875rem;
            margin: 0;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #44403c;
            margin-bottom: 0.25rem;
        }

        .form-input {
            width: 100%;
            padding: 0.625rem 0.875rem;
            font-size: 1rem;
            color: #292524;
            background: #fff;
            border: 1px solid #d6d3d1;
            border-radius: 8px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            box-sizing: border-box;
        }

        .form-input:focus {
            outline: none;
            border-color: #01696f;
            box-shadow: 0 0 0 3px rgba(1, 105, 111, 0.15);
        }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper .form-input {
            padding-right: 2.5rem;
        }

        .toggle-password {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.1rem;
            color: #a8a29e;
            padding: 0;
            line-height: 1;
        }

        .toggle-password:hover {
            color: #57534e;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .checkbox-group input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: #01696f;
        }

        .checkbox-group label {
            font-size: 0.875rem;
            cursor: pointer;
            color: #57534e;
            margin: 0;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 500;
            border: 1px solid transparent;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-primary {
            background: #01696f;
            color: #fff;
            border-color: #01696f;
        }

        .btn-primary:hover {
            background: #014d52;
            border-color: #014d52;
        }

        .btn-block {
            display: flex;
            width: 100%;
        }

        .btn-lg {
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
        }

        .alert {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            border: 1px solid transparent;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fecaca;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border-color: #bbf7d0;
        }

        .alert-info {
            background: #e0f2fe;
            color: #0c4a6e;
            border-color: #bae6fd;
        }

        .divider {
            border: none;
            border-top: 1px solid #e7e5e4;
            margin: 1.25rem 0;
        }

        .login-footer {
            text-align: center;
            font-size: 0.875rem;
            color: #78716c;
            margin-top: 1rem;
        }

        .login-footer a {
            color: #01696f;
            text-decoration: none;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        .demo-credentials {
            margin-top: 1.25rem;
            padding: 0.75rem 1rem;
            background: #f5f5f4;
            border-radius: 8px;
            font-size: 0.75rem;
            color: #57534e;
            border: 1px solid #e7e5e4;
        }

        .demo-credentials strong {
            display: block;
            margin-bottom: 0.35rem;
            color: #44403c;
        }

        .demo-credentials code {
            display: block;
            line-height: 1.6;
        }

        .site-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.75rem;
            color: #a8a29e;
        }

        /* Flash message auto-dismiss */
        .flash-message {
            animation: flashFade 4s forwards;
        }

        @keyframes flashFade {
            0%, 70% { opacity: 1; }
            100% { opacity: 0; display: none; }
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 1.5rem 1.25rem;
            }
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="login-card">
        <div class="login-logo">
            <span class="brand-icon">&#9749;</span>
            <h2>Smart Transaction System</h2>
            <p>Welcome back! Please log in to continue.</p>
        </div>

        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['flash_type'] ?? 'info'; ?> flash-message">
                <?php echo htmlspecialchars($_SESSION['flash_message']); ?>
            </div>
            <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="" novalidate>
            <!-- Email -->
            <div class="form-group">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" id="email" name="email" class="form-input"
                       value="<?php echo htmlspecialchars($rememberedEmail); ?>"
                       placeholder="you@example.com" required>
            </div>

            <!-- Password with show/hide toggle -->
            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" class="form-input"
                           placeholder="Enter your password" required>
                    <button type="button" class="toggle-password" onclick="togglePassword()" aria-label="Toggle password visibility">&#128065;</button>
                </div>
            </div>

            <!-- Remember Me -->
            <div class="checkbox-group">
                <input type="checkbox" id="remember" name="remember" value="1"
                       <?php echo $rememberedEmail ? 'checked' : ''; ?>>
                <label for="remember">Remember Me</label>
            </div>

            <button type="submit" name="login" value="1" class="btn btn-primary btn-block btn-lg">
                Login
            </button>
        </form>

        <hr class="divider">

        <div class="login-footer">
            Don't have an account? <a href="/smart-transaction/auth/signup.php">Sign up here</a>
        </div>

        <!-- Demo Credentials -->
        <div class="demo-credentials">
            <strong>Demo Login Credentials</strong>
            <code>Admin   : admin@smarttransaction.com</code>
            <code>Staff   : staff@smarttransaction.com</code>
            <code>Customer: customer@smarttransaction.com</code>
            <code>Password: Admin@1234 / Staff@1234 / customer123</code>
        </div>
    </div>

    <div class="site-footer">
        Smart Transaction System &copy; 2026
    </div>
</div>

<script>
function togglePassword() {
    var pwd = document.getElementById('password');
    var btn = document.querySelector('.toggle-password');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        btn.innerHTML = '&#128064;';
    } else {
        pwd.type = 'password';
        btn.innerHTML = '&#128065;';
    }
}

// Auto-dismiss flash messages
document.addEventListener('DOMContentLoaded', function() {
    var flash = document.querySelector('.flash-message');
    if (flash) {
        setTimeout(function() {
            flash.style.display = 'none';
        }, 4000);
    }
});
</script>

</body>
</html>
