<?php
/**
 * Admin - Order Management Page
 * 
 * Displays all orders with their status.
 * Staff can:
 *   - Update order status (pending → preparing → ready → completed)
 *   - Confirm cash payments (unpaid → paid)
 *   - Cancel orders
 *   - View order details
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';

startSession();
requireRole(['admin', 'staff']);

$pageTitle = 'Order Management - Admin';

$pdo = getDBConnection();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $action = $_POST['action'];
    $userId = $_SESSION['user_id'] ?? 0;

    if ($orderId > 0) {
        try {
            $pdo->beginTransaction();

            // Get current order
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $orderId]);
            $order = $stmt->fetch();

            if (!$order) {
                throw new Exception('Order not found.');
            }

            $oldStatus = $order['status'];
            $newStatus = $oldStatus;

            switch ($action) {
                case 'confirm_cash':
                    // Staff confirms cash payment
                    if ($order['payment_status'] !== 'paid') {
                        // Generate transaction reference
                        $transactionRef = 'TXN-' . str_pad($orderId, 5, '0', STR_PAD_LEFT) . '-' . date('YmdHis');

                        // Insert payment record
                        $payStmt = $pdo->prepare('INSERT INTO payments (order_id, payment_method, amount_paid, transaction_ref, status) VALUES (:order_id, :method, :amount, :ref, :status)');
                        $payStmt->execute([
                            ':order_id' => $orderId,
                            ':method'   => 'cash',
                            ':amount'   => $order['total_amount'],
                            ':ref'      => $transactionRef,
                            ':status'   => 'completed',
                        ]);
                        $paymentId = (int) $pdo->lastInsertId();

                        // Update order payment status
                        $updateStmt = $pdo->prepare('UPDATE orders SET payment_status = :payment_status WHERE id = :id');
                        $updateStmt->execute([
                            ':payment_status' => 'paid',
                            ':id'             => $orderId,
                        ]);

                        // Insert transaction record
                        $transStmt = $pdo->prepare('INSERT INTO transactions (order_id, payment_id, total_amount, payment_method, status) VALUES (:order_id, :payment_id, :total, :method, :status)');
                        $transStmt->execute([
                            ':order_id'   => $orderId,
                            ':payment_id' => $paymentId,
                            ':total'      => $order['total_amount'],
                            ':method'     => 'cash',
                            ':status'     => 'completed',
                        ]);

                        // Also move status to preparing
                        $newStatus = 'preparing';
                        $updateStatusStmt = $pdo->prepare('UPDATE orders SET status = :status WHERE id = :id');
                        $updateStatusStmt->execute([
                            ':status' => $newStatus,
                            ':id'     => $orderId,
                        ]);

                        // Log status change
                        $logStmt = $pdo->prepare('INSERT INTO order_status_logs (order_id, old_status, new_status, changed_by) VALUES (:order_id, :old_status, :new_status, :changed_by)');
                        $logStmt->execute([
                            ':order_id'   => $orderId,
                            ':old_status' => $oldStatus,
                            ':new_status' => $newStatus,
                            ':changed_by' => $userId,
                        ]);

                        $_SESSION['flash_message'] = 'Cash payment confirmed for Order #' . $orderId . '. Order is now preparing.';
                        $_SESSION['flash_type'] = 'success';
                    }
                    break;

                case 'update_status':
                    $newStatus = $_POST['new_status'] ?? '';
                    $validStatuses = ['pending', 'preparing', 'ready', 'completed', 'cancelled'];

                    if (in_array($newStatus, $validStatuses) && $newStatus !== $oldStatus) {
                        $updateStatusStmt = $pdo->prepare('UPDATE orders SET status = :status WHERE id = :id');
                        $updateStatusStmt->execute([
                            ':status' => $newStatus,
                            ':id'     => $orderId,
                        ]);

                        // Log status change
                        $logStmt = $pdo->prepare('INSERT INTO order_status_logs (order_id, old_status, new_status, changed_by) VALUES (:order_id, :old_status, :new_status, :changed_by)');
                        $logStmt->execute([
                            ':order_id'   => $orderId,
                            ':old_status' => $oldStatus,
                            ':new_status' => $newStatus,
                            ':changed_by' => $userId,
                        ]);

                        $_SESSION['flash_message'] = 'Order #' . $orderId . ' status updated to ' . ucfirst($newStatus) . '.';
                        $_SESSION['flash_type'] = 'success';
                    }
                    break;
            }

            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['flash_message'] = 'Error: ' . $e->getMessage();
            $_SESSION['flash_type'] = 'danger';
        }

        header('Location: /smart-transaction/admin/orders.php');
        exit;
    }
}

// Fetch all orders with item count
$ordersStmt = $pdo->query('
    SELECT o.*, 
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
    FROM orders o 
    ORDER BY o.created_at DESC
');
$orders = $ordersStmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-between align-center flex-wrap gap-1">
    <div>
        <h1>Order Management</h1>
        <p>View and manage all customer orders</p>
    </div>
    <div>
        <span class="badge badge-pending"><?php echo count(array_filter($orders, fn($o) => $o['status'] === 'pending')); ?> Pending</span>
        <span class="badge badge-preparing"><?php echo count(array_filter($orders, fn($o) => $o['status'] === 'preparing')); ?> Preparing</span>
        <span class="badge badge-ready"><?php echo count(array_filter($orders, fn($o) => $o['status'] === 'ready')); ?> Ready</span>
    </div>
</div>

<?php if (isset($_SESSION['flash_message'])): ?>
    <div class="alert alert-<?php echo $_SESSION['flash_type'] ?? 'info'; ?>">
        <?php echo htmlspecialchars($_SESSION['flash_message']); ?>
    </div>
    <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
<?php endif; ?>

<?php if (empty($orders)): ?>
    <div class="card">
        <div class="card-body text-center" style="padding: var(--spacing-2xl);">
            <p style="font-size: 1.2rem; color: var(--neutral-500);">No orders yet.</p>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><strong>#<?php echo (int) $order['id']; ?></strong></td>
                            <td>
                                <?php echo htmlspecialchars($order['customer_name']); ?>
                                <?php if ($order['customer_phone']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($order['customer_phone']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo (int) $order['item_count']; ?></td>
                            <td><strong>RM <?php echo number_format((float) $order['total_amount'], 2); ?></strong></td>
                            <td>
                                <?php if ($order['payment_status'] === 'paid'): ?>
                                    <span class="badge badge-paid">Paid</span>
                                <?php else: ?>
                                    <span class="badge badge-unpaid">Unpaid</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo htmlspecialchars($order['status']); ?>">
                                    <?php echo htmlspecialchars(ucfirst($order['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?></td>
                            <td>
                                <div class="d-flex gap-1 flex-wrap" style="align-items: center;">
                                    <!-- View Details -->
                                    <a href="/smart-transaction/receipt.php?order_id=<?php echo (int) $order['id']; ?>" class="btn btn-sm btn-outline" target="_blank">View</a>

                                    <!-- Confirm Cash Payment (only for unpaid orders) -->
                                    <?php if ($order['payment_status'] === 'unpaid'): ?>
                                        <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Confirm cash payment for Order #<?php echo (int) $order['id']; ?>? This will mark it as paid and start preparing.');">
                                            <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                                            <input type="hidden" name="action" value="confirm_cash">
                                            <button type="submit" class="btn btn-sm btn-success">&#128176; Confirm Cash</button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- Status Update Dropdown -->
                                    <?php if ($order['status'] !== 'cancelled' && $order['status'] !== 'completed'): ?>
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                                            <input type="hidden" name="action" value="update_status">
                                            <select name="new_status" onchange="this.form.submit()" class="form-select" style="width: auto; display: inline-block; padding: 0.3rem 1.5rem 0.3rem 0.5rem; font-size: 0.75rem;">
                                                <option value="">Change Status</option>
                                                <?php
                                                $statusFlow = ['pending', 'preparing', 'ready', 'completed'];
                                                $currentIdx = array_search($order['status'], $statusFlow);
                                                foreach ($statusFlow as $idx => $status):
                                                    if ($idx > $currentIdx):
                                                ?>
                                                    <option value="<?php echo $status; ?>">→ <?php echo ucfirst($status); ?></option>
                                                <?php 
                                                    endif;
                                                endforeach; 
                                                ?>
                                                <option value="cancelled">Cancel Order</option>
                                            </select>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
