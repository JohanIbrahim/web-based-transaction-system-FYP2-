<?php
/**
 * Admin Dashboard Page
 * 
 * Shows summary statistics: total orders today, pending orders, total revenue today,
 * total products, and recent orders.
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin_auth.php';

startSession();
requireAdminRole();

$pageTitle = 'Dashboard - Smart Transaction System';

$stats = [];
$recentOrders = [];

try {
    $pdo = getDBConnection();

    // Total orders today
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()");
    $stats['orders_today'] = (int) $stmt->fetchColumn();

    // Pending orders
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
    $stats['pending_orders'] = (int) $stmt->fetchColumn();

    // Total revenue today (paid orders only)
    $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(created_at) = CURDATE() AND payment_status = 'paid'");
    $stats['revenue_today'] = (float) $stmt->fetchColumn();

    // Total products
    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE is_available = 1");
    $stats['total_products'] = (int) $stmt->fetchColumn();

    // Recent orders
    $stmt = $pdo->query("SELECT id, customer_name, total_amount, status, payment_status, created_at FROM orders ORDER BY created_at DESC LIMIT 5");
    $recentOrders = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Unable to load dashboard data.';
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Dashboard</h1>
    <p>Welcome back, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>!</p>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="grid grid-4 mb-4">
    <div class="card stat-card">
        <div class="card-body">
            <div class="stat-icon">&#128202;</div>
            <div class="stat-value"><?php echo $stats['orders_today'] ?? 0; ?></div>
            <div class="stat-label">Orders Today</div>
        </div>
    </div>
    <div class="card stat-card">
        <div class="card-body">
            <div class="stat-icon">&#9200;</div>
            <div class="stat-value"><?php echo $stats['pending_orders'] ?? 0; ?></div>
            <div class="stat-label">Pending Orders</div>
        </div>
    </div>
    <div class="card stat-card">
        <div class="card-body">
            <div class="stat-icon">&#128176;</div>
            <div class="stat-value">RM <?php echo number_format($stats['revenue_today'] ?? 0, 2); ?></div>
            <div class="stat-label">Revenue Today</div>
        </div>
    </div>
    <div class="card stat-card">
        <div class="card-body">
            <div class="stat-icon">&#127869;</div>
            <div class="stat-value"><?php echo $stats['total_products'] ?? 0; ?></div>
            <div class="stat-label">Active Products</div>
        </div>
    </div>
</div>

<!-- Recent Orders -->
<div class="card">
    <div class="card-header d-flex justify-between">
        <span>Recent Orders</span>
        <a href="/smart-transaction/admin/orders.php" class="btn btn-sm btn-outline">View All</a>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($recentOrders)): ?>
            <p style="padding: var(--spacing-lg); text-align: center; color: var(--neutral-500);">No orders yet.</p>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td><strong>#<?php echo (int) $order['id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                <td>RM <?php echo number_format((float) $order['total_amount'], 2); ?></td>
                                <td><span class="badge badge-<?php echo htmlspecialchars($order['status']); ?>"><?php echo htmlspecialchars(ucfirst($order['status'])); ?></span></td>
                                <td><span class="badge badge-<?php echo $order['payment_status'] === 'paid' ? 'paid' : 'unpaid'; ?>"><?php echo htmlspecialchars(ucfirst($order['payment_status'])); ?></span></td>
                                <td><?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
