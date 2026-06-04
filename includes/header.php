<?php
/**
 * Header Template
 * 
 * Renders the <head> section, opening <body> tag, and navigation bar.
 * Navigation links change based on user role.
 * Shows cart item count badge for customers.
 * Shows customer name and logout when customer is logged in.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Smart Transaction System'; ?></title>
    <link rel="stylesheet" href="/smart-transaction/assets/css/style.css">
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar">
    <div class="container">
        <a href="/smart-transaction/index.php" class="navbar-brand">
            <span class="brand-icon">&#9749;</span>
            Smart Transaction
        </a>

        <button class="navbar-toggle" id="navbarToggle" aria-label="Toggle navigation">&#9776;</button>

        <ul class="navbar-nav" id="navbarNav">
            <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'staff'])): ?>
                <!-- Admin / Staff Navigation -->
                <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'admin'): ?>
                    <li class="nav-item"><a href="/smart-transaction/admin/dashboard.php" class="nav-link">Dashboard</a></li>
                    <li class="nav-item"><a href="/smart-transaction/admin/products.php" class="nav-link">Products</a></li>
                    <li class="nav-item"><a href="/smart-transaction/admin/transactions.php" class="nav-link">Transactions</a></li>
                    <li class="nav-item"><a href="/smart-transaction/admin/reports.php" class="nav-link">Reports</a></li>
                <?php endif; ?>
                <li class="nav-item"><a href="/smart-transaction/admin/orders.php" class="nav-link">Orders</a></li>

                <?php if (isAdminOrCustomerLoggedIn()): ?>
                    <li class="nav-item">
                        <span style="color: rgba(255,255,255,0.8); font-size: 0.85rem; padding: 0.5rem;">
                            Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>
                            <?php if (isset($_SESSION['admin_role'])): ?>
                                <span class="role-badge role-<?php echo $_SESSION['admin_role']; ?>">
                                    <?php echo htmlspecialchars(ucfirst($_SESSION['admin_role'])); ?>
                                </span>
                            <?php endif; ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a href="/smart-transaction/auth/logout.php" class="nav-link logout-link">Logout</a>
                    </li>
                <?php endif; ?>

            <?php elseif (isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true): ?>
                <!-- Customer Navigation (Logged In) -->
                <li class="nav-item"><a href="/smart-transaction/index.php" class="nav-link">Menu</a></li>
                <li class="nav-item">
                    <a href="/smart-transaction/cart.php" class="nav-link cart-nav-link">
                        Cart
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
                <li class="nav-item">
                    <span style="color: rgba(255,255,255,0.8); font-size: 0.85rem; padding: 0.5rem;">
                        Hello, <?php echo htmlspecialchars($_SESSION['customer_name'] ?? 'Customer'); ?>
                    </span>
                </li>
                <li class="nav-item">
                    <a href="/smart-transaction/auth/logout.php" class="nav-link logout-link">Logout</a>
                </li>

            <?php else: ?>
                <!-- Guest Navigation (Not Logged In) -->
                <li class="nav-item"><a href="/smart-transaction/index.php" class="nav-link">Menu</a></li>
                <li class="nav-item"><a href="/smart-transaction/auth/login.php" class="nav-link">Login</a></li>
                <li class="nav-item"><a href="/smart-transaction/auth/signup.php" class="nav-link">Sign Up</a></li>
            <?php endif; ?>
        </ul>
    </div>
</nav>

<!-- Flash Messages -->
<?php if (isset($_SESSION['flash_message'])): ?>
    <div class="container" style="padding-top: 0.5rem;">
        <div class="alert alert-<?php echo $_SESSION['flash_type'] ?? 'info'; ?> flash-message">
            <?php echo htmlspecialchars($_SESSION['flash_message']); ?>
        </div>
    </div>
    <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
<?php endif; ?>

<main class="container" style="padding-top: 1.5rem; padding-bottom: 2rem;">
