<?php
/**
 * Header Template — Smart Transaction
 * 
 * Renders the <head> section, opening <body> tag, and navigation bar.
 * Navigation links change based on user role.
 * Shows cart item count badge for customers.
 * Shows customer name and logout when customer is logged in.
 * Includes dark mode toggle.
 */
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#c8410a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title><?php echo $pageTitle ?? 'Smart Transaction — Smart. Simple. Seamless.'; ?></title>
    <link rel="stylesheet" href="/smart-transaction/assets/css/style.css">
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar">
    <div class="container">
        <?php
        $logoFile = __DIR__ . '/../uploads/logo.png';
        $hasCustomLogo = file_exists($logoFile);
        ?>
        <a href="/smart-transaction/index.php" class="navbar-brand qs-logo">
            <?php if ($hasCustomLogo): ?>
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

        <button class="navbar-toggle" id="navbarToggle" aria-label="Toggle navigation">
            <i data-lucide="menu" style="width:24px;height:24px;"></i>
        </button>

        <ul class="navbar-nav" id="navbarNav">
            <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'staff'])): ?>
                <!-- Admin / Staff Navigation -->
                <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin'): ?>
                    <li class="nav-item"><a href="/smart-transaction/admin/dashboard.php" class="nav-link">Dashboard</a></li>
                    <li class="nav-item"><a href="/smart-transaction/admin/orders.php" class="nav-link">Orders</a></li>
                    <li class="nav-item"><a href="/smart-transaction/admin/products.php" class="nav-link">Products</a></li>
                    <li class="nav-item"><a href="/smart-transaction/admin/promotions.php" class="nav-link">Promotions</a></li>
                    <li class="nav-item"><a href="/smart-transaction/admin/coupons.php" class="nav-link">Coupons</a></li>
                    <li class="nav-item"><a href="/smart-transaction/admin/transactions.php" class="nav-link">Transactions</a></li>
                    <li class="nav-item"><a href="/smart-transaction/admin/reports.php" class="nav-link">Reports</a></li>
                <?php endif; ?>
                <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin'): ?>
                    <li class="nav-item"><a href="/smart-transaction/admin/manage-accounts.php" class="nav-link">Manage Accounts</a></li>
                    <li class="nav-item"><a href="/smart-transaction/admin/settings.php" class="nav-link">Settings</a></li>
                <?php endif; ?>

                <?php if (isAdminOrCustomerLoggedIn()): ?>
                    <li class="nav-item nav-user">
                        <span class="user-avatar"><?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?></span>
                        <span><?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?></span>
                        <?php if (isset($_SESSION['admin_role'])): ?>
                            <span class="badge badge-<?php echo $_SESSION['admin_role'] === 'admin' ? 'preparing' : 'pending'; ?>" style="font-size:0.65rem;">
                                <?php echo htmlspecialchars(ucfirst($_SESSION['admin_role'])); ?>
                            </span>
                        <?php endif; ?>
                    </li>
                    <li class="nav-item">
                        <a href="/smart-transaction/auth/logout.php" class="nav-link logout-link">
                            <i data-lucide="log-out" style="width:16px;height:16px;"></i> Logout
                        </a>
                    </li>
                <?php endif; ?>

            <?php elseif (isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true): ?>
                <!-- Customer Navigation (Logged In) -->
                <li class="nav-item"><a href="/smart-transaction/index.php" class="nav-link">Menu</a></li>
                <li class="nav-item">
                    <a href="/smart-transaction/cart.php" class="nav-link cart-nav-link">
                        <i data-lucide="shopping-cart" style="width:16px;height:16px;"></i> Cart
                        <?php 
                        $cartCount = 0;
                        foreach (($_SESSION['cart'] ?? []) as $qty) {
                            $cartCount += (int) $qty;
                        }
                        if ($cartCount > 0): 
                        ?>
                            <span class="cart-badge"><?php echo $cartCount; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item"><a href="/smart-transaction/transaction-history.php" class="nav-link">My Orders</a></li>
                <li class="nav-item nav-user">
                    <span class="user-avatar"><?php echo strtoupper(substr($_SESSION['customer_name'] ?? 'C', 0, 1)); ?></span>
                    <span><?php echo htmlspecialchars($_SESSION['customer_name'] ?? 'Customer'); ?></span>
                </li>
                <li class="nav-item">
                    <a href="/smart-transaction/auth/logout.php" class="nav-link logout-link">
                        <i data-lucide="log-out" style="width:16px;height:16px;"></i> Logout
                    </a>
                </li>

            <?php else: ?>
                <!-- Guest Navigation (Not Logged In) -->
                <li class="nav-item"><a href="/smart-transaction/index.php" class="nav-link">Menu</a></li>
                <li class="nav-item"><a href="/smart-transaction/auth/login.php" class="nav-link">Login</a></li>
                <li class="nav-item"><a href="/smart-transaction/auth/signup.php" class="nav-link">Sign Up</a></li>
            <?php endif; ?>

            <!-- Dark Mode Toggle -->
            <li class="nav-item">
                <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode">
                    <i data-lucide="moon" id="themeIcon" style="width:18px;height:18px;"></i>
                </button>
            </li>
        </ul>
    </div>
</nav>

<!-- Flash Messages -->
<?php if (isset($_SESSION['flash_message'])): ?>
    <div class="container" style="padding-top: 0.5rem;">
        <div class="alert alert-<?php echo $_SESSION['flash_type'] ?? 'info'; ?> flash-message">
            <span class="alert-icon">
                <?php
                $icons = [
                    'success' => '&#9989;',
                    'danger'  => '&#10060;',
                    'warning' => '&#9888;',
                    'info'    => '&#8505;',
                ];
                echo $icons[$_SESSION['flash_type'] ?? 'info'] ?? '&#8505;';
                ?>
            </span>
            <span><?php echo htmlspecialchars($_SESSION['flash_message']); ?></span>
            <button class="alert-close" onclick="this.parentElement.remove();">&times;</button>
        </div>
    </div>
    <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
<?php endif; ?>

<main class="container" style="padding-top: 1.5rem; padding-bottom: 2rem;">

<script>
// Dark Mode Toggle
document.addEventListener('DOMContentLoaded', function() {
    var themeToggle = document.getElementById('themeToggle');
    var themeIcon = document.getElementById('themeIcon');
    var html = document.documentElement;
    
    // Load saved theme
    var savedTheme = localStorage.getItem('st-theme') || 'light';
    html.setAttribute('data-theme', savedTheme);
    updateIcon(savedTheme);
    
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            var current = html.getAttribute('data-theme');
            var next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            localStorage.setItem('st-theme', next);
            updateIcon(next);
        });
    }
    
    function updateIcon(theme) {
        if (themeIcon) {
            themeIcon.setAttribute('data-lucide', theme === 'dark' ? 'sun' : 'moon');
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }
    }
    
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Mobile navbar toggle
    var navbarToggle = document.getElementById('navbarToggle');
    var navbarNav = document.getElementById('navbarNav');
    if (navbarToggle && navbarNav) {
        navbarToggle.addEventListener('click', function() {
            navbarNav.classList.toggle('open');
            // Toggle menu/X icon
            var icon = navbarToggle.querySelector('[data-lucide]');
            if (icon) {
                var isOpen = navbarNav.classList.contains('open');
                icon.setAttribute('data-lucide', isOpen ? 'x' : 'menu');
                lucide.createIcons();
            }
        });
    }
});
</script>
