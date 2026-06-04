<?php
/**
 * Customer Signup Page
 * 
 * Registration form for new customers.
 * Validates input, checks for duplicate email/phone, creates user.
 * Redirects to login page on success.
 * Clean design - no navbar, no header menu.
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

startSession();

$pageTitle = 'Sign Up - Smart Transaction System';

$errors = [];
$oldInput = [
    'name'  => '',
    'email' => '',
    'phone' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup'])) {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    $oldInput = [
        'name'  => $name,
        'email' => $email,
        'phone' => $phone,
    ];

    // --- Validation ---

    // Name
    if (empty($name)) {
        $errors['name'] = 'Full name is required.';
    }

    // Email
    if (empty($email)) {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    } else {
        // Check if email already exists
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            if ($stmt->fetch()) {
                $errors['email'] = 'This email is already registered.';
            }
        } catch (PDOException $e) {
            $errors['email'] = 'Unable to verify email. Please try again.';
        }
    }

    // Phone
    if (empty($phone)) {
        $errors['phone'] = 'Phone number is required.';
    } else {
        // Check if phone already exists
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare('SELECT id FROM users WHERE phone = :phone LIMIT 1');
            $stmt->execute([':phone' => $phone]);
            if ($stmt->fetch()) {
                $errors['phone'] = 'This phone number is already registered.';
            }
        } catch (PDOException $e) {
            $errors['phone'] = 'Unable to verify phone. Please try again.';
        }
    }

    // Password
    if (empty($password)) {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    }

    // Confirm password
    if (empty($confirm)) {
        $errors['confirm_password'] = 'Please confirm your password.';
    } elseif ($password !== $confirm) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    // --- Insert if no errors ---
    if (empty($errors)) {
        try {
            $pdo = getDBConnection();
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare('INSERT INTO users (name, email, phone, password_hash, role) VALUES (:name, :email, :phone, :hash, :role)');
            $stmt->execute([
                ':name'  => $name,
                ':email' => $email,
                ':phone' => $phone,
                ':hash'  => $hash,
                ':role'  => 'customer',
            ]);

            $_SESSION['flash_message'] = 'Account created successfully! Please log in.';
            $_SESSION['flash_type'] = 'success';
            header('Location: /smart-transaction/auth/login.php');
            exit;
        } catch (PDOException $e) {
            $errors['general'] = 'An error occurred. Please try again.';
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
        /* Clean signup page - no navbar, no header menu */
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

        .signup-wrapper {
            width: 100%;
            max-width: 440px;
        }

        .signup-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            padding: 2.5rem 2rem;
        }

        .signup-logo {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .signup-logo .brand-icon {
            font-size: 2.5rem;
            display: block;
            margin-bottom: 0.5rem;
        }

        .signup-logo h2 {
            color: #01696f;
            font-size: 1.5rem;
            margin: 0 0 0.25rem 0;
        }

        .signup-logo p {
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

        .form-input.is-invalid {
            border-color: #dc2626;
        }

        .form-input.is-invalid:focus {
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.15);
        }

        .field-error {
            display: block;
            font-size: 0.75rem;
            color: #dc2626;
            margin-top: 0.25rem;
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

        .divider {
            border: none;
            border-top: 1px solid #e7e5e4;
            margin: 1.25rem 0;
        }

        .signup-footer {
            text-align: center;
            font-size: 0.875rem;
            color: #78716c;
            margin-top: 1rem;
        }

        .signup-footer a {
            color: #01696f;
            text-decoration: none;
        }

        .signup-footer a:hover {
            text-decoration: underline;
        }

        .site-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.75rem;
            color: #a8a29e;
        }

        @media (max-width: 480px) {
            .signup-card {
                padding: 1.5rem 1.25rem;
            }
        }
    </style>
</head>
<body>

<div class="signup-wrapper">
    <div class="signup-card">
        <div class="signup-logo">
            <span class="brand-icon">&#9749;</span>
            <h2>Smart Transaction System</h2>
            <p>Create your account to get started.</p>
        </div>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($errors['general']); ?></div>
        <?php endif; ?>

        <form method="POST" action="" novalidate>
            <!-- Full Name -->
            <div class="form-group">
                <label for="name" class="form-label">Full Name *</label>
                <input type="text" id="name" name="name" class="form-input <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>"
                       value="<?php echo htmlspecialchars($oldInput['name']); ?>"
                       placeholder="Enter your full name" required>
                <?php if (isset($errors['name'])): ?>
                    <small class="field-error"><?php echo htmlspecialchars($errors['name']); ?></small>
                <?php endif; ?>
            </div>

            <!-- Email -->
            <div class="form-group">
                <label for="email" class="form-label">Email Address *</label>
                <input type="email" id="email" name="email" class="form-input <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>"
                       value="<?php echo htmlspecialchars($oldInput['email']); ?>"
                       placeholder="you@example.com" required>
                <?php if (isset($errors['email'])): ?>
                    <small class="field-error"><?php echo htmlspecialchars($errors['email']); ?></small>
                <?php endif; ?>
            </div>

            <!-- Phone -->
            <div class="form-group">
                <label for="phone" class="form-label">Phone Number *</label>
                <input type="text" id="phone" name="phone" class="form-input <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>"
                       value="<?php echo htmlspecialchars($oldInput['phone']); ?>"
                       placeholder="e.g. 012-3456789" required>
                <?php if (isset($errors['phone'])): ?>
                    <small class="field-error"><?php echo htmlspecialchars($errors['phone']); ?></small>
                <?php endif; ?>
            </div>

            <!-- Password with show/hide toggle -->
            <div class="form-group">
                <label for="password" class="form-label">Password *</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" class="form-input <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>"
                           placeholder="Minimum 8 characters" required>
                    <button type="button" class="toggle-password" onclick="togglePassword('password')" aria-label="Toggle password visibility">&#128065;</button>
                </div>
                <?php if (isset($errors['password'])): ?>
                    <small class="field-error"><?php echo htmlspecialchars($errors['password']); ?></small>
                <?php endif; ?>
            </div>

            <!-- Confirm Password with show/hide toggle -->
            <div class="form-group">
                <label for="confirm_password" class="form-label">Confirm Password *</label>
                <div class="password-wrapper">
                    <input type="password" id="confirm_password" name="confirm_password" class="form-input <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>"
                           placeholder="Re-enter your password" required>
                    <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')" aria-label="Toggle password visibility">&#128065;</button>
                </div>
                <?php if (isset($errors['confirm_password'])): ?>
                    <small class="field-error"><?php echo htmlspecialchars($errors['confirm_password']); ?></small>
                <?php endif; ?>
            </div>

            <button type="submit" name="signup" value="1" class="btn btn-primary btn-block btn-lg">
                Sign Up
            </button>
        </form>

        <hr class="divider">

        <div class="signup-footer">
            Already have an account? <a href="/smart-transaction/auth/login.php">Log in here</a>
        </div>
    </div>

    <div class="site-footer">
        Smart Transaction System &copy; 2026
    </div>
</div>

<script>
function togglePassword(fieldId) {
    var pwd = document.getElementById(fieldId);
    var btn = pwd.parentElement.querySelector('.toggle-password');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        btn.innerHTML = '&#128064;';
    } else {
        pwd.type = 'password';
        btn.innerHTML = '&#128065;';
    }
}
</script>

</body>
</html>
