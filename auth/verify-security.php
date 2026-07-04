<?php
/**
 * Verify Security Question — Step 2: Answer Security Question
 * 
 * Shows the user's security question and asks for the answer.
 * If answer matches, redirects to reset-password.php.
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

// Check if user has gone through step 1 (email entry)
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_user_id'])) {
    header('Location: /smart-transaction/auth/forgot-password.php');
    exit;
}

// If already verified, redirect to reset password
if (isset($_SESSION['reset_verified']) && $_SESSION['reset_verified'] === true) {
    header('Location: /smart-transaction/auth/reset-password.php');
    exit;
}

$error = '';
$security_question = '';

try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare('SELECT security_question FROM users WHERE id = :id AND email = :email LIMIT 1');
    $stmt->execute([
        ':id' => $_SESSION['reset_user_id'],
        ':email' => $_SESSION['reset_email']
    ]);
    $user = $stmt->fetch();

    if (!$user || empty($user['security_question'])) {
        // Invalid session or no security question set
        session_unset();
        header('Location: /smart-transaction/auth/forgot-password.php');
        exit;
    }

    $security_question = $user['security_question'];
} catch (PDOException $e) {
    $error = 'An error occurred. Please try again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $answer = trim($_POST['security_answer'] ?? '');

    if (empty($answer)) {
        $error = 'Please enter your security answer.';
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare('SELECT security_answer FROM users WHERE id = :id AND email = :email LIMIT 1');
            $stmt->execute([
                ':id' => $_SESSION['reset_user_id'],
                ':email' => $_SESSION['reset_email']
            ]);
            $user = $stmt->fetch();

            if ($user && password_verify(strtolower(trim($answer)), $user['security_answer'])) {
                // Answer matches — mark as verified
                $_SESSION['reset_verified'] = true;
                header('Location: /smart-transaction/auth/reset-password.php');
                exit;
            } else {
                $error = 'Incorrect answer. Please try again.';
            }
        } catch (PDOException $e) {
            $error = 'An error occurred. Please try again.';
        }
    }
}

$pageTitle = 'Verify Security Question — Smart Transaction';
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

    <!-- Right Panel — Security Question Form -->
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

            <h1 class="auth-title">Verify Your Identity</h1>
            <p class="auth-subtitle">Answer your security question to reset your password</p>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <span class="alert-icon">&#10060;</span>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Security Question</label>
                    <div style="padding: 11px 14px; background: var(--color-surface-offset); border-radius: var(--radius-md); border: 1px solid var(--color-border); font-size: 0.9375rem; color: var(--color-text);">
                        <?php echo htmlspecialchars($security_question); ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="security_answer">Your Answer <span class="required">*</span></label>
                    <input type="text" id="security_answer" name="security_answer" class="form-input" 
                           placeholder="Enter your answer" required
                           value="<?php echo htmlspecialchars($_POST['security_answer'] ?? ''); ?>">
                    <small style="color: var(--color-text-muted);">Answer is case-insensitive</small>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    Verify <i data-lucide="arrow-right" style="width:18px;height:18px;"></i>
                </button>
            </form>

            <p class="mt-3 text-center" style="font-size: 0.9rem; color: var(--color-text-muted);">
                <a href="/smart-transaction/auth/forgot-password.php">Start over</a> &middot;
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
