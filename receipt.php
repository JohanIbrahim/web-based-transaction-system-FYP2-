<?php
/**
 * Digital Receipt Page
 * 
 * Displays a printable receipt for a completed order.
 * Shows order details, items, payment info, and a track order button.
 * For unpaid cash orders, shows a waiting message instead.
 */

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';

startSession();

$pageTitle = 'Receipt - Smart Transaction System';

$orderId = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;

if ($orderId <= 0) {
    header('Location: /smart-transaction/index.php');
    exit;
}

try {
    $pdo = getDBConnection();

    // Fetch order
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        header('Location: /smart-transaction/index.php');
        exit;
    }

    // Fetch order items
    $itemStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :order_id');
    $itemStmt->execute([':order_id' => $orderId]);
    $orderItems = $itemStmt->fetchAll();

    // Fetch payment info
    $payStmt = $pdo->prepare('SELECT * FROM payments WHERE order_id = :order_id AND status = "completed" ORDER BY id DESC LIMIT 1');
    $payStmt->execute([':order_id' => $orderId]);
    $payment = $payStmt->fetch();

    // Fetch transaction info
    $transStmt = $pdo->prepare('SELECT * FROM transactions WHERE order_id = :order_id ORDER BY id DESC LIMIT 1');
    $transStmt->execute([':order_id' => $orderId]);
    $transaction = $transStmt->fetch();
} catch (PDOException $e) {
    $error = 'Unable to load receipt.';
}

include __DIR__ . '/includes/header.php';
?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php elseif (isset($order) && $order): ?>

    <?php if ($order['payment_status'] === 'unpaid'): ?>
        <!-- Unpaid Cash Order — Show waiting message -->
        <div class="card" style="max-width: 500px; margin: 0 auto; text-align: center;">
            <div class="card-body" style="padding: var(--spacing-2xl);">
                <div style="font-size: 3rem; margin-bottom: var(--spacing-md);">&#128176;</div>
                <h2>Order Placed!</h2>
                <p style="color: var(--neutral-600); margin-bottom: var(--spacing-md);">
                    Your order <strong>#<?php echo $orderId; ?></strong> has been placed successfully.
                </p>
                <div class="alert alert-info" style="text-align: left;">
                    <strong>&#128161; Next Step:</strong>
                    <p style="margin-top: 0.25rem;">Please proceed to the counter to pay. Your order will be prepared once payment is confirmed by our staff.</p>
                </div>
                <div style="margin-top: var(--spacing-lg);">
                    <p style="font-size: var(--font-size-sm); color: var(--neutral-500);">
                        <strong>Order Status:</strong> 
                        <span class="badge badge-pending">Pending Payment</span>
                    </p>
                </div>
                <div class="d-flex gap-1 flex-wrap" style="justify-content: center; margin-top: var(--spacing-lg);">
                    <a href="/smart-transaction/tracking.php?order_id=<?php echo (int) $order['id']; ?>" class="btn btn-primary">Track My Order</a>
                    <a href="/smart-transaction/index.php" class="btn btn-outline">&larr; Back to Menu</a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Paid Order — Show full receipt -->
        <div class="no-print mb-3 d-flex gap-1 flex-wrap">
            <a href="/smart-transaction/index.php" class="btn btn-outline">&larr; Back to Menu</a>
            <a href="/smart-transaction/tracking.php?order_id=<?php echo (int) $order['id']; ?>" class="btn btn-primary">Track My Order</a>
            <button id="printReceipt" class="btn btn-secondary">&#128424; Print / Download</button>
        </div>

        <div class="receipt" id="receiptContent">
            <div class="receipt-header">
                <span style="font-size: 2rem;">&#9749;</span>
                <h3>Smart Transaction</h3>
                <p style="font-size: 0.85rem; color: var(--neutral-500);">Digital Receipt</p>
            </div>

            <div style="font-size: 0.85rem; margin-bottom: 1rem;">
                <p><strong>Receipt #:</strong> RCP-<?php echo str_pad($orderId, 5, '0', STR_PAD_LEFT); ?></p>
                <p><strong>Order #:</strong> <?php echo $orderId; ?></p>
                <p><strong>Date:</strong> <?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?></p>
                <p><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                <?php if ($order['customer_phone']): ?>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['customer_phone']); ?></p>
                <?php endif; ?>
            </div>

            <hr style="border: none; border-top: 1px dashed var(--neutral-300);">

            <div class="receipt-items">
                <div class="receipt-item-header">
                    <span>Item</span>
                    <span>Qty</span>
                    <span>Price</span>
                    <span>Subtotal</span>
                </div>
                <?php foreach ($orderItems as $item): ?>
                    <div class="receipt-item">
                        <span style="flex: 1;"><?php echo htmlspecialchars($item['product_name']); ?></span>
                        <span style="width: 40px; text-align: center;"><?php echo (int) $item['quantity']; ?></span>
                        <span style="width: 70px; text-align: right;">RM <?php echo number_format((float) $item['unit_price'], 2); ?></span>
                        <span style="width: 80px; text-align: right;">RM <?php echo number_format((float) $item['subtotal'], 2); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="receipt-total">
                <span>Total</span>
                <span>RM <?php echo number_format((float) $order['total_amount'], 2); ?></span>
            </div>

            <hr style="border: none; border-top: 1px dashed var(--neutral-300); margin: 1rem 0;">

            <div style="font-size: 0.85rem;">
                <p><strong>Payment Method:</strong> 
                    <?php 
                    $methodLabels = ['cash' => 'Cash', 'online' => 'Online Banking', 'ewallet' => 'E-Wallet'];
                    echo $methodLabels[$payment['payment_method'] ?? ''] ?? ucfirst($payment['payment_method'] ?? 'N/A'); 
                    ?>
                </p>
                <?php if ($payment && $payment['transaction_ref']): ?>
                    <p><strong>Transaction Ref:</strong> <?php echo htmlspecialchars($payment['transaction_ref']); ?></p>
                <?php endif; ?>
                <p><strong>Payment Status:</strong> 
                    <span class="badge badge-paid">Paid</span>
                </p>
                <p><strong>Order Status:</strong> 
                    <span class="badge badge-<?php echo htmlspecialchars($order['status']); ?>">
                        <?php echo htmlspecialchars(ucfirst($order['status'])); ?>
                    </span>
                </p>
            </div>

            <div class="receipt-footer">
                <p>Thank you for your order!</p>
                <p style="margin-top: 0.25rem;">For inquiries, please contact the store.</p>
            </div>
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
