<?php
/**
 * Admin / Staff Login Page
 * 
 * Authenticates admin and staff from the users table.
 * Uses password_verify() to check passwords.
 * Sets $_SESSION['user_id'], $_SESSION['role'], $_SESSION['user_name'].
 * Redirects to admin/dashboard.php on success.
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

startSession();

// Handle logout
if (isset($_GET['logout'])) {
    logout();
    header('Location: /smart-transaction/admin/login.php');
    exit;
}

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
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
            $pdo  = getDBConnection();
            $stmt = $pdo->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = :email AND role IN ("admin", "staff") LIMIT 1');
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);

                $_SESSION['user_id']    = (int) $user['id'];
                $_SESSION['user_name']  = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['role']       = $user['role'];

                header('Location: /smart-transaction/admin/dashboard.php');
                exit;
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            $error = 'An error occurred. Please try again later.';
            // In production, log the error: error_log($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login - Smart Transaction System</title>
    <link rel="stylesheet" href="/smart-transaction/assets/css/style.css">
</head>
<body class="login-page">

<div class="login-card">
    <div class="login-logo">
        <span style="font-size: 2.5rem;">&#9749;</span>
        <h2>Smart Transaction</h2>
        <p>Staff & Admin Login</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="email" class="form-label">Email Address</label>
            <input type="email" id="email" name="email" class="form-input" 
                   placeholder="admin@smarttransaction.com" required
                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
        </div>

        <div class="form-group">
            <label for="password" class="form-label">Password</label>
            <input type="password" id="password" name="password" class="form-input" 
                   placeholder="Enter your password" required>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg mt-2">Sign In</button>
    </form>

    <div style="text-align: center; margin-top: 1.5rem; font-size: 0.8rem; color: var(--neutral-500);">
        <p><strong>Test Credentials:</strong></p>
        <p>Admin: admin@smarttransaction.com / password</p>
        <p>Staff: staff@smarttransaction.com / password</p>
    </div>

    <div style="text-align: center; margin-top: 1rem;">
        <a href="/smart-transaction/index.php" class="btn btn-outline btn-sm">&larr; Back to Menu</a>
    </div>
</div>

</body>
</html>
