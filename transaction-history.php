<?php
/**
 * Transaction History Page — Smart Transaction
 * 
 * Shows all orders for the logged-in customer.
 * Each order can be clicked to view details.
 * Includes order status badges and payment status.
 */

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/customer_auth.php';

startSession();
requireCustomerLogin();

$pageTitle = 'My Orders — Smart Transaction';

try {
    $pdo = getDBConnection();

    // Fetch all orders for this customer
    $stmt = $pdo->prepare('
        SELECT o.*, 
               p.payment_method,
               p.transaction_ref,
               p.status AS payment_status_detail
        FROM orders o
        LEFT JOIN payments p ON p.order_id = o.id AND p.status = "completed"
        WHERE o.customer_id = :customer_id
        ORDER BY o.created_at DESC
    ');
    $stmt->execute([':customer_id' => $_SESSION['customer_id']]);
    $orders = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = 'Unable to load order history.';
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>My Orders</h1>
    <p>View your order history and track status</p>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if (empty($orders)): ?>
    <div class="card">
        <div class="card-body text-center" style="padding: 3rem 1rem;">
            <p style="font-size: 3rem; margin-bottom: 1rem;">&#128230;</p>
            <h3>No orders yet</h3>
            <p class="text-muted mt-1">Place your first order to see it here.</p>
            <a href="/smart-transaction/index.php" class="btn btn-primary mt-2">Browse Menu</a>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): 
                            // Count items
                            $itemCountStmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM order_items WHERE order_id = :order_id');
                            $itemCountStmt->execute([':order_id' => $order['id']]);
                            $itemCount = (int) $itemCountStmt->fetchColumn();
                        ?>
                            <tr>
                                <td><strong>#<?php echo $order['id']; ?></strong></td>
                                <td><?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?></td>
                                <td><?php echo $itemCount; ?> item(s)</td>
                                <td><strong>RM <?php echo number_format((float) $order['total_amount'], 2); ?></strong></td>
                                <td>
                                    <?php if ($order['payment_status'] === 'paid'): ?>
                                        <span class="badge badge-paid">Paid</span>
                                    <?php else: ?>
                                        <span class="badge badge-pending">Unpaid</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusLabels = [
                                        'pending' => 'Pending',
                                        'preparing' => 'Preparing',
                                        'ready' => 'Ready',
                                        'completed' => 'Completed',
                                        'cancelled' => 'Cancelled',
                                    ];
                                    $statusClass = $order['status'];
                                    ?>
                                    <span class="badge badge-<?php echo $statusClass; ?>">
                                        <?php echo $statusLabels[$order['status']] ?? ucfirst($order['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="/smart-transaction/receipt.php?order_id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
