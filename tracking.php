<?php
/**
 * Order Tracking Page
 * 
 * Allows customers to look up their order status by order ID.
 * Displays a visual step-by-step progress bar and auto-refreshes every 15 seconds.
 */

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';

startSession();

$pageTitle = 'Track Order - Smart Transaction System';

$order = null;
$orderItems = [];
$statusLogs = [];
$searchError = '';

// Handle order lookup
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['order_id'])) {
    $searchId = isset($_POST['order_id']) ? (int) $_POST['order_id'] : (isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0);

    if ($searchId <= 0) {
        $searchError = 'Please enter a valid order number.';
    } else {
        try {
            $pdo = getDBConnection();

            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id');
            $stmt->execute([':id' => $searchId]);
            $order = $stmt->fetch();

            if ($order) {
                // Fetch order items
                $itemStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :order_id');
                $itemStmt->execute([':order_id' => $searchId]);
                $orderItems = $itemStmt->fetchAll();

                // Fetch status logs
                $logStmt = $pdo->prepare('SELECT * FROM order_status_logs WHERE order_id = :order_id ORDER BY changed_at ASC');
                $logStmt->execute([':order_id' => $searchId]);
                $statusLogs = $logStmt->fetchAll();
            } else {
                $searchError = 'Order not found. Please check your order number and try again.';
            }
        } catch (PDOException $e) {
            $searchError = 'An error occurred. Please try again later.';
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Track Your Order</h1>
    <p>Enter your order number to check the status</p>
</div>

<!-- Search Form -->
<div class="card mb-3">
    <div class="card-body">
        <form method="POST" action="" id="trackForm">
            <div class="d-flex gap-1 align-center" style="flex-wrap: wrap;">
                <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 200px;">
                    <label for="order_id" class="form-label">Order Number *</label>
                    <input type="number" id="order_id" name="order_id" class="form-input" required
                           placeholder="e.g. 1" min="1"
                           value="<?php echo htmlspecialchars($_POST['order_id'] ?? $_GET['order_id'] ?? ''); ?>">
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top: 1.5rem;">Track Order</button>
            </div>
        </form>
    </div>
</div>

<?php if ($searchError): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($searchError); ?></div>
<?php endif; ?>

<?php if ($order): ?>
    <?php
    // Define status steps in order
    $statusSteps = ['pending', 'preparing', 'ready', 'completed'];
    $currentStatus = $order['status'];
    $currentStepIndex = array_search($currentStatus, $statusSteps);
    if ($currentStatus === 'cancelled') {
        $currentStepIndex = -1; // Cancelled
    }
    ?>

    <!-- Order Info -->
    <div class="card mb-3">
        <div class="card-header">Order #<?php echo (int) $order['id']; ?></div>
        <div class="card-body">
            <div class="grid grid-2" style="align-items: start;">
                <div>
                    <p><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['customer_phone'] ?? 'N/A'); ?></p>
                    <p><strong>Date:</strong> <?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?></p>
                </div>
                <div>
                    <p><strong>Total:</strong> RM <?php echo number_format((float) $order['total_amount'], 2); ?></p>
                    <p>
                        <strong>Status:</strong>
                        <span class="badge badge-<?php echo htmlspecialchars($order['status']); ?>" id="statusBadge">
                            <?php echo htmlspecialchars(ucfirst($order['status'])); ?>
                        </span>
                    </p>
                    <p>
                        <strong>Payment:</strong>
                        <span class="badge badge-<?php echo $order['payment_status'] === 'paid' ? 'paid' : 'unpaid'; ?>">
                            <?php echo htmlspecialchars(ucfirst($order['payment_status'])); ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress Bar -->
    <div class="card mb-3">
        <div class="card-header">Order Progress</div>
        <div class="card-body">
            <?php if ($currentStatus === 'cancelled'): ?>
                <div class="alert alert-danger text-center" style="font-size: 1.1rem;">
                    &#10060; This order has been cancelled.
                </div>
            <?php else: ?>
                <div class="progress-steps">
                    <?php foreach ($statusSteps as $index => $step): 
                        $isCompleted = $index <= $currentStepIndex;
                        $isCurrent = $index === $currentStepIndex;
                        $stepLabels = [
                            'pending'   => ['icon' => '&#9200;', 'label' => 'Pending'],
                            'preparing' => ['icon' => '&#127859;', 'label' => 'Preparing'],
                            'ready'     => ['icon' => '&#9989;', 'label' => 'Ready'],
                            'completed' => ['icon' => '&#127881;', 'label' => 'Completed'],
                        ];
                        $info = $stepLabels[$step];
                    ?>
                        <div class="progress-step <?php echo $isCompleted ? 'completed' : ''; ?> <?php echo $isCurrent ? 'current' : ''; ?>">
                            <div class="progress-step-icon"><?php echo $info['icon']; ?></div>
                            <div class="progress-step-label"><?php echo $info['label']; ?></div>
                            <?php if ($index < count($statusSteps) - 1): ?>
                                <div class="progress-connector <?php echo $isCompleted && $index < $currentStepIndex ? 'completed' : ''; ?>"></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Order Items -->
    <div class="card mb-3">
        <div class="card-header">Order Items</div>
        <div class="card-body" style="padding: 0;">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderItems as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td><?php echo (int) $item['quantity']; ?></td>
                                <td>RM <?php echo number_format((float) $item['unit_price'], 2); ?></td>
                                <td>RM <?php echo number_format((float) $item['subtotal'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3" style="text-align: right;">Total:</th>
                            <th>RM <?php echo number_format((float) $order['total_amount'], 2); ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Status History -->
    <?php if (!empty($statusLogs)): ?>
    <div class="card mb-3">
        <div class="card-header">Status History</div>
        <div class="card-body" style="padding: 0;">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Old Status</th>
                            <th>New Status</th>
                            <th>Changed By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($statusLogs as $log): ?>
                            <tr>
                                <td><?php echo date('d M Y, h:i A', strtotime($log['changed_at'])); ?></td>
                                <td><?php echo $log['old_status'] ? htmlspecialchars(ucfirst($log['old_status'])) : '-'; ?></td>
                                <td><span class="badge badge-<?php echo htmlspecialchars($log['new_status']); ?>"><?php echo htmlspecialchars(ucfirst($log['new_status'])); ?></span></td>
                                <td><?php echo htmlspecialchars($log['changed_by'] ?? 'System'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Auto-refresh script -->
    <script>
    (function() {
        var orderId = <?php echo (int) $order['id']; ?>;
        var statusBadge = document.getElementById('statusBadge');
        var progressSteps = document.querySelector('.progress-steps');

        function refreshStatus() {
            fetch('/smart-transaction/get_status.php?order_id=' + orderId)
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.status) {
                        // Update badge
                        if (statusBadge) {
                            statusBadge.textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);
                            statusBadge.className = 'badge badge-' + data.status;
                        }

                        // Reload page if status changed (to update progress bar)
                        if (data.status !== '<?php echo $currentStatus; ?>') {
                            location.reload();
                        }
                    }
                })
                .catch(function() {
                    // Silently fail - don't disrupt user
                });
        }

        // Auto-refresh every 15 seconds
        setInterval(refreshStatus, 15000);
    })();
    </script>

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
