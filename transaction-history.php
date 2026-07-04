<?php
/**
 * Transaction History Page — Smart Transaction
 * 
 * Shows all orders for the logged-in customer, split into:
 *   - Order Status (current/active orders with progress tracker)
 *   - Order History (past orders in a neat table)
 * Tabbed interface for easy switching.
 */

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/customer_auth.php';

startSession();
requireCustomerLogin();

$pageTitle = 'My Orders — Smart Transaction';

// Default to empty array to prevent foreach on non-array
$currentOrders = [];
$historyOrders = [];

try {
    $pdo = getDBConnection();

    // Fetch all orders for this customer with item count via subquery
    $stmt = $pdo->prepare('
        SELECT o.*, 
               p.payment_method,
               p.transaction_ref,
               p.status AS payment_status_detail,
               (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS item_count
        FROM orders o
        LEFT JOIN payments p ON p.order_id = o.id AND p.status = "completed"
        WHERE o.customer_id = :customer_id
        ORDER BY o.created_at DESC
    ');
    $stmt->execute([':customer_id' => $_SESSION['customer_id'] ?? 0]);
    $orders = $stmt->fetchAll();

    // Split orders into current (active) and history (completed/cancelled)
    $activeStatuses = ['pending', 'processing', 'preparing', 'ready', 'active'];
    $historyStatuses = ['completed', 'cancelled'];

    foreach ($orders as $order) {
        $status = $order['status'] ?? '';
        if (in_array($status, $activeStatuses)) {
            $currentOrders[] = $order;
        } elseif (in_array($status, $historyStatuses)) {
            $historyOrders[] = $order;
        }
    }

} catch (PDOException $e) {
    $error = 'Unable to load order history.';
    error_log('Transaction history error: ' . $e->getMessage());
}

/**
 * Map database status to progress step index (0-based)
 * Steps: Order Placed → Confirmed → Preparing → Ready to Serve → Served
 */
function getProgressStep(string $status): int {
    $stepMap = [
        'pending'    => 0,
        'processing' => 1,
        'preparing'  => 2,
        'ready'      => 3,
        'active'     => 4,
    ];
    return $stepMap[$status] ?? 0;
}

/**
 * Get a friendly dine-in status message for the customer
 */
function getStatusMessage(string $status): string {
    $messages = [
        'pending'    => 'We received your order',
        'processing' => 'Your order has been confirmed',
        'preparing'  => 'Kitchen is preparing your order',
        'ready'      => 'Your order is ready to be served',
        'active'     => 'Your order has been served',
        'completed'  => 'Order completed',
        'cancelled'  => 'Order cancelled',
    ];
    return $messages[$status] ?? 'Processing your order';
}

/**
 * Get a short status description for the progress tracker
 */
function getStepLabel(int $step): string {
    $labels = ['Order Placed', 'Confirmed', 'Preparing', 'Ready to Serve', 'Served'];
    return $labels[$step] ?? '';
}

/**
 * Get the status badge label for display
 */
function getStatusBadgeLabel(string $status): string {
    $labels = [
        'pending'    => 'Pending',
        'processing' => 'Confirmed',
        'preparing'  => 'Preparing',
        'ready'      => 'Ready',
        'active'     => 'Served',
        'completed'  => 'Completed',
        'cancelled'  => 'Cancelled',
    ];
    return $labels[$status] ?? ucfirst($status);
}

/**
 * Generate a compact status-flow text string.
 * Shows all stages with the current stage highlighted.
 * Stages: Pending → Confirmed → Preparing → Ready to Serve → Served → Completed
 */
function getStatusFlowText(string $status): string {
    $allStages = [
        'pending'    => 'Pending',
        'processing' => 'Confirmed',
        'preparing'  => 'Preparing',
        'ready'      => 'Ready to Serve',
        'active'     => 'Served',
        'completed'  => 'Completed',
    ];
    
    $flow = [];
    $currentReached = false;
    foreach ($allStages as $key => $label) {
        if ($key === $status) {
            $flow[] = '<strong class="status-flow-current">' . htmlspecialchars($label) . '</strong>';
            $currentReached = true;
        } elseif (!$currentReached) {
            $flow[] = '<span class="status-flow-past">' . htmlspecialchars($label) . '</span>';
        } else {
            $flow[] = '<span class="status-flow-future">' . htmlspecialchars($label) . '</span>';
        }
    }
    return implode(' <span class="status-flow-arrow">→</span> ', $flow);
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>My Orders</h1>
    <p>Track your dine-in orders and view past orders</p>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- ============================================================ -->
<!-- TABBED INTERFACE -->
<!-- ============================================================ -->
<div class="history-tabs" role="tablist">
    <button class="history-tab active" role="tab" aria-selected="true" aria-controls="tab-status" id="tab-btn-status" data-tab="status">
        Order Status
        <?php if (!empty($currentOrders)): ?>
            <span class="badge badge-active" style="margin-left:6px;font-size:0.7rem;"><?php echo count($currentOrders); ?></span>
        <?php endif; ?>
    </button>
    <button class="history-tab" role="tab" aria-selected="false" aria-controls="tab-history" id="tab-btn-history" data-tab="history">
        Order History
        <?php if (!empty($historyOrders)): ?>
            <span class="badge badge-completed" style="margin-left:6px;font-size:0.7rem;"><?php echo count($historyOrders); ?></span>
        <?php endif; ?>
    </button>
</div>

<!-- ============================================================ -->
<!-- TAB 1: ORDER STATUS (Current / Active Orders) -->
<!-- ============================================================ -->
<section class="orders-section tab-panel" id="tab-status" role="tabpanel" aria-labelledby="tab-btn-status">

    <?php if (empty($currentOrders)): ?>
        <div class="card">
            <div class="card-body text-center" style="padding: 3rem 1rem;">
                <div class="empty-state-icon">&#128230;</div>
                <h3>No current orders right now</h3>
                <p class="text-muted">Place a new order to see it here.</p>
                <a href="/smart-transaction/index.php" class="btn btn-primary mt-2">Browse Menu</a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($currentOrders as $order): 
            $statusClass = $order['status'] ?? 'pending';
            $badgeLabel = getStatusBadgeLabel($statusClass);
            $statusMsg = getStatusMessage($statusClass);
            $currentStep = getProgressStep($statusClass);
            
            // Progress stages for dine-in (compact labels)
            $stages = [
                ['key' => 'pending',    'label' => 'Placed'],
                ['key' => 'processing', 'label' => 'Confirmed'],
                ['key' => 'preparing',  'label' => 'Preparing'],
                ['key' => 'ready',      'label' => 'Ready'],
                ['key' => 'active',     'label' => 'Served'],
                ['key' => 'completed',  'label' => 'Done'],
            ];
        ?>
            <div class="tracking-card">
                <!-- ===== HEADER: Status badge + Order info + Time ===== -->
                <div class="tracking-card-header">
                    <div class="tracking-card-header-left">
                        <span class="badge badge-<?php echo htmlspecialchars($statusClass); ?>">
                            <?php echo htmlspecialchars($badgeLabel); ?>
                        </span>
                        <span class="tracking-order-label">Dine-in · Order #<?php echo (int) $order['id']; ?></span>
                        <span class="tracking-time"><?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?></span>
                    </div>
                </div>

                <!-- ===== MAIN STATUS LINE ===== -->
                <div class="tracking-status-line"><?php echo htmlspecialchars($statusMsg); ?></div>

                <!-- ===== HORIZONTAL PROGRESS BAR ===== -->
                <div class="tracking-progress">
                    <?php foreach ($stages as $i => $stage): 
                        $isCompleted = $i < $currentStep;
                        $isCurrent = $i === $currentStep;
                    ?>
                        <?php if ($i > 0): ?>
                            <div class="tracking-line <?php echo $isCompleted ? 'completed' : ''; ?>"></div>
                        <?php endif; ?>
                        <div class="tracking-step <?php echo $isCompleted ? 'completed' : ($isCurrent ? 'current' : ''); ?>">
                            <div class="tracking-dot">
                                <?php if ($isCompleted): ?>
                                    <svg width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M2 5L4 7L8 3" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <?php endif; ?>
                            </div>
                            <div class="tracking-label"><?php echo htmlspecialchars($stage['label']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- ===== BOTTOM ROW: Summary + Action ===== -->
                <div class="tracking-bottom">
                    <div class="tracking-summary">
                        <span class="tracking-summary-item">
                            <span class="tracking-summary-icon">&#128099;</span>
                            <?php echo !empty($order['table_number']) ? htmlspecialchars($order['table_number']) : '—'; ?>
                        </span>
                        <span class="tracking-summary-divider"></span>
                        <span class="tracking-summary-item">
                            <?php echo (int) ($order['item_count'] ?? 0); ?> item<?php echo ((int)($order['item_count'] ?? 0) !== 1) ? 's' : ''; ?>
                        </span>
                        <span class="tracking-summary-divider"></span>
                        <span class="tracking-summary-item tracking-summary-total">
                            RM <?php echo number_format((float) ($order['total_amount'] ?? 0), 2); ?>
                        </span>
                        <span class="tracking-summary-divider"></span>
                        <span class="tracking-summary-item">
                            <?php if (($order['payment_status'] ?? '') === 'paid'): ?>
                                <span class="badge badge-paid">Paid</span>
                            <?php else: ?>
                                <span class="badge badge-unpaid">Unpaid</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="tracking-actions">
                        <a href="/smart-transaction/receipt.php?order_id=<?php echo (int) $order['id']; ?>" class="btn btn-outline btn-sm">View Details</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<!-- ============================================================ -->
<!-- TAB 2: ORDER HISTORY (Past Orders Table) -->
<!-- ============================================================ -->
<section class="orders-section tab-panel" id="tab-history" role="tabpanel" aria-labelledby="tab-btn-history" style="display:none;">

    <?php if (empty($historyOrders)): ?>
        <div class="card">
            <div class="card-body text-center" style="padding: 3rem 1rem;">
                <div class="empty-state-icon">&#128203;</div>
                <h3>No order history yet</h3>
                <p class="text-muted">Your completed and past orders will appear here.</p>
                <a href="/smart-transaction/index.php" class="btn btn-primary mt-2">Browse Menu</a>
            </div>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Date / Time</th>
                        <th>Table</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historyOrders as $order): 
                        $statusClass = $order['status'] ?? 'completed';
                        $statusLabels = [
                            'pending'    => 'Pending',
                            'processing' => 'Processing',
                            'preparing'  => 'Preparing',
                            'ready'      => 'Ready',
                            'active'     => 'Served',
                            'completed'  => 'Completed',
                            'cancelled'  => 'Cancelled',
                        ];
                    ?>
                        <tr>
                            <td class="history-table-id">#<?php echo (int) $order['id']; ?></td>
                            <td class="history-table-date"><?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?></td>
                            <td><?php echo !empty($order['table_number']) ? htmlspecialchars($order['table_number']) : '—'; ?></td>
                            <td><?php echo (int) ($order['item_count'] ?? 0); ?></td>
                            <td class="history-table-total">RM <?php echo number_format((float) ($order['total_amount'] ?? 0), 2); ?></td>
                            <td>
                                <?php if (($order['payment_status'] ?? '') === 'paid'): ?>
                                    <span class="badge badge-paid">Paid</span>
                                <?php else: ?>
                                    <span class="badge badge-unpaid">Unpaid</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo htmlspecialchars($statusClass); ?>">
                                    <?php echo $statusLabels[$statusClass] ?? ucfirst($statusClass); ?>
                                </span>
                            </td>
                            <td>
                                <a href="/smart-transaction/receipt.php?order_id=<?php echo (int) $order['id']; ?>" class="btn btn-outline btn-sm">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<script>
// Tab switching for My Orders page
document.addEventListener('DOMContentLoaded', function() {
    var tabs = document.querySelectorAll('.history-tab');
    var panels = {
        status: document.getElementById('tab-status'),
        history: document.getElementById('tab-history')
    };

    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            var target = this.getAttribute('data-tab');

            // Deactivate all tabs
            tabs.forEach(function(t) {
                t.classList.remove('active');
                t.setAttribute('aria-selected', 'false');
            });

            // Activate clicked tab
            this.classList.add('active');
            this.setAttribute('aria-selected', 'true');

            // Show/hide panels
            Object.keys(panels).forEach(function(key) {
                panels[key].style.display = (key === target) ? '' : 'none';
            });
        });
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
