<?php
/**
 * Admin Transaction Records Page
 * 
 * Lists all completed transactions with payment details.
 * Shows invoices and allows searching/filtering.
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin_auth.php';

startSession();
requireAdminRole();

$pageTitle = 'Transactions — Smart Transaction';

$transactions = [];
$coupons = [];

try {
    $pdo = getDBConnection();

    $stmt = $pdo->query('
        SELECT t.id AS txn_id, t.total_amount, t.payment_method, t.status AS txn_status, t.created_at,
               o.id AS order_id, o.customer_name, o.customer_phone,
               p.transaction_ref, p.payment_method AS pay_method, p.status AS pay_status
        FROM transactions t
        JOIN orders o ON t.order_id = o.id
        LEFT JOIN payments p ON t.payment_id = p.id
        ORDER BY t.created_at DESC
    ');
    $transactions = $stmt->fetchAll();

    // Fetch all coupons with customer info
    $couponStmt = $pdo->query('
        SELECT c.*, u.name AS customer_name
        FROM coupons c
        LEFT JOIN users u ON c.customer_id = u.id
        ORDER BY c.issued_at DESC
        LIMIT 50
    ');
    $coupons = $couponStmt->fetchAll();

} catch (PDOException $e) {
    $error = 'Unable to load data.';
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Transactions</h1>
    <p>View all completed transaction records and invoices</p>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">Transaction Records</div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($transactions)): ?>
            <p style="padding: var(--spacing-lg); text-align: center; color: var(--neutral-500);">No transactions yet.</p>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Txn #</th>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th>Date</th>
                            <th>Invoice</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $txn): ?>
                            <tr>
                                <td><strong>#<?php echo (int) $txn['txn_id']; ?></strong></td>
                                <td>#<?php echo (int) $txn['order_id']; ?></td>
                                <td><?php echo htmlspecialchars($txn['customer_name']); ?></td>
                                <td><strong>RM <?php echo number_format((float) $txn['total_amount'], 2); ?></strong></td>
                                <td>
                                    <?php 
                                    $methodLabels = ['cash' => 'Cash', 'online' => 'Online', 'ewallet' => 'E-Wallet'];
                                    echo $methodLabels[$txn['payment_method']] ?? htmlspecialchars(ucfirst($txn['payment_method']));
                                    ?>
                                </td>
                                <td style="font-size: 0.8rem;"><?php echo htmlspecialchars($txn['transaction_ref'] ?? '-'); ?></td>
                                <td><?php echo date('d M Y, h:i A', strtotime($txn['created_at'])); ?></td>
                                <td>
                                    <a href="/smart-transaction/receipt.php?order_id=<?php echo (int) $txn['order_id']; ?>" class="btn btn-sm btn-outline">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Coupon Management Section -->
<div class="card mt-4">
    <div class="card-header d-flex justify-between align-center">
        <span>Coupon Management (Last 50)</span>
        <span class="badge badge-paid" style="font-size: 0.75rem;"><?php echo count($coupons); ?> total</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($coupons)): ?>
            <p style="padding: var(--spacing-lg); text-align: center; color: var(--neutral-500);">No coupons issued yet. Coupons are automatically awarded when customers complete their 2nd, 5th, 10th, etc. order.</p>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Coupon Code</th>
                            <th>Customer</th>
                            <th>Discount</th>
                            <th>Tier</th>
                            <th>Issued</th>
                            <th>Expires</th>
                            <th>Status</th>
                            <th>Used In</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($coupons as $coupon): 
                            $isExpired = strtotime($coupon['expires_at']) < time();
                            $isActive = !$coupon['is_used'] && !$isExpired;
                        ?>
                            <tr>
                                <td><code style="background: var(--neutral-100); padding: 0.15rem 0.4rem; border-radius: 4px; font-weight: bold;"><?php echo htmlspecialchars($coupon['coupon_code']); ?></code></td>
                                <td><?php echo htmlspecialchars($coupon['customer_name'] ?? 'Unknown'); ?></td>
                                <td><strong><?php echo (int) $coupon['discount_percent']; ?>%</strong></td>
                                <td><span class="badge badge-<?php echo $coupon['tier']; ?>" style="background: var(--primary); color: white; font-size: 0.7rem;"><?php echo htmlspecialchars($coupon['tier_name']); ?></span></td>
                                <td><?php echo date('d M Y', strtotime($coupon['issued_at'])); ?></td>
                                <td><?php echo date('d M Y', strtotime($coupon['expires_at'])); ?></td>
                                <td>
                                    <?php if ($isActive): ?>
                                        <span class="badge badge-paid" style="font-size: 0.7rem;">Active</span>
                                    <?php elseif ($coupon['is_used']): ?>
                                        <span class="badge badge-unpaid" style="font-size: 0.7rem; background: #dc2626;">Used</span>
                                    <?php else: ?>
                                        <span class="badge badge-pending" style="font-size: 0.7rem; background: #f59e0b;">Expired</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($coupon['used_in_order_id']): ?>
                                        <a href="/smart-transaction/receipt.php?order_id=<?php echo (int) $coupon['used_in_order_id']; ?>" class="btn btn-sm btn-outline">Order #<?php echo (int) $coupon['used_in_order_id']; ?></a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
