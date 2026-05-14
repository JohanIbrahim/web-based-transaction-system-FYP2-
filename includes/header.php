<?php
/**
 * Header Template
 * 
 * Renders the <head> section, opening <body> tag, and navigation bar.
 * Navigation links change based on user role.
 * Shows cart item count badge for customers.
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
            <?php if (!isset($_SESSION['role']) || $_SESSION['role'] === 'customer'): ?>
                <!-- Customer Navigation -->
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
                <li class="nav-item"><a href="/smart-transaction/tracking.php" class="nav-link">Track Order</a></li>
                <li class="nav-item"><a href="/smart-transaction/transaction-history.php" class="nav-link">History</a></li>
            <?php endif; ?>

            <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'staff'])): ?>
                <!-- Admin / Staff Navigation -->
                <li class="nav-item"><a href="/smart-transaction/admin/dashboard.php" class="nav-link">Dashboard</a></li>
                <li class="nav-item"><a href="/smart-transaction/admin/orders.php" class="nav-link">Orders</a></li>
                <li class="nav-item"><a href="/smart-transaction/admin/products.php" class="nav-link">Products</a></li>
                <li class="nav-item"><a href="/smart-transaction/admin/transactions.php" class="nav-link">Transactions</a></li>
                <li class="nav-item"><a href="/smart-transaction/admin/reports.php" class="nav-link">Reports</a></li>
            <?php endif; ?>

            <?php if (isLoggedIn()): ?>
                <li class="nav-item">
                    <span style="color: rgba(255,255,255,0.7); font-size: 0.85rem; padding: 0.5rem;">
                        <?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>
                        (<?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?>)
                    </span>
                </li>
                <li class="nav-item">
                    <a href="/smart-transaction/admin/login.php?logout=1" class="nav-link logout-link">Logout</a>
                </li>
            <?php else: ?>
                <li class="nav-item"><a href="/smart-transaction/admin/login.php" class="nav-link">Staff Login</a></li>
            <?php endif; ?>
        </ul>
    </div>
</nav>

<main class="container" style="padding-top: 1.5rem; padding-bottom: 2rem;">
