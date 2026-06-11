<?php
/**
 * Admin Sales Reports Page
 * 
 * Shows daily, weekly, and monthly sales summaries.
 * Displays total revenue, order counts, and payment method breakdown.
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin_auth.php';

startSession();
requireAdminRole();

$pageTitle = 'Reports — Smart Transaction';

$dailySales = [];
$weeklySales = [];
$monthlySales = [];
$paymentBreakdown = [];

try {
    $pdo = getDBConnection();

    // Daily sales (last 7 days)
    $stmt = $pdo->query("
        SELECT DATE(created_at) AS date, COUNT(*) AS orders, COALESCE(SUM(total_amount), 0) AS revenue
        FROM orders
        WHERE payment_status = 'paid' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ");
    $dailySales = $stmt->fetchAll();

    // Monthly sales (last 6 months)
    $stmt = $pdo->query("
        SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS orders, COALESCE(SUM(total_amount), 0) AS revenue
        FROM orders
        WHERE payment_status = 'paid' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
    ");
    $monthlySales = $stmt->fetchAll();

    // Payment method breakdown
    $stmt = $pdo->query("
        SELECT COALESCE(p.payment_method, 'cash') AS method, COUNT(*) AS count, COALESCE(SUM(p.amount_paid), 0) AS total
        FROM payments p
        JOIN orders o ON p.order_id = o.id
        WHERE p.status = 'completed'
        GROUP BY p.payment_method
        ORDER BY total DESC
    ");
    $paymentBreakdown = $stmt->fetchAll();

    // Overall stats
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'paid'");
    $totalPaidOrders = (int) $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'paid'");
    $totalRevenue = (float) $stmt->fetchColumn();

} catch (PDOException $e) {
    $error = 'Unable to load reports.';
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Sales Reports</h1>
    <p>View sales performance and payment breakdown</p>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Overall Stats -->
<div class="grid grid-2 mb-4">
    <div class="card stat-card">
        <div class="card-body">
            <div class="stat-icon">&#128176;</div>
            <div class="stat-value">RM <?php echo number_format($totalRevenue ?? 0, 2); ?></div>
            <div class="stat-label">Total Revenue (All Time)</div>
        </div>
    </div>
    <div class="card stat-card">
        <div class="card-body">
            <div class="stat-icon">&#128203;</div>
            <div class="stat-value"><?php echo $totalPaidOrders ?? 0; ?></div>
            <div class="stat-label">Total Paid Orders</div>
        </div>
    </div>
</div>

<!-- Daily Sales -->
<div class="card mb-3">
    <div class="card-header">Daily Sales (Last 7 Days)</div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($dailySales)): ?>
            <p style="padding: var(--spacing-lg); text-align: center; color: var(--neutral-500);">No data for the last 7 days.</p>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Orders</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dailySales as $row): ?>
                            <tr>
                                <td><?php echo date('d M Y', strtotime($row['date'])); ?></td>
                                <td><?php echo (int) $row['orders']; ?></td>
                                <td><strong>RM <?php echo number_format((float) $row['revenue'], 2); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Monthly Sales -->
<div class="card mb-3">
    <div class="card-header">Monthly Sales (Last 6 Months)</div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($monthlySales)): ?>
            <p style="padding: var(--spacing-lg); text-align: center; color: var(--neutral-500);">No data available.</p>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Orders</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthlySales as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['month']); ?></td>
                                <td><?php echo (int) $row['orders']; ?></td>
                                <td><strong>RM <?php echo number_format((float) $row['revenue'], 2); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Payment Method Breakdown -->
<div class="card">
    <div class="card-header">Payment Method Breakdown</div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($paymentBreakdown)): ?>
            <p style="padding: var(--spacing-lg); text-align: center; color: var(--neutral-500);">No payment data available.</p>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Method</th>
                            <th>Transactions</th>
                            <th>Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $methodLabels = ['cash' => 'Cash', 'online' => 'Online Banking', 'ewallet' => 'E-Wallet'];
                        foreach ($paymentBreakdown as $row): 
                        ?>
                            <tr>
                                <td><?php echo $methodLabels[$row['method']] ?? htmlspecialchars(ucfirst($row['method'])); ?></td>
                                <td><?php echo (int) $row['count']; ?></td>
                                <td><strong>RM <?php echo number_format((float) $row['total'], 2); ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
