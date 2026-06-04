<?php
/**
 * My Orders Page
 * 
 * Shows all orders for the logged-in customer.
 * - Active Orders: pending, preparing, ready (with progress bar)
 * - Order History: completed, cancelled
 * Auto-refreshes active orders every 15 seconds via AJAX.
 */

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/customer_auth.php';
require_once __DIR__ . '/includes/coupon_helper.php';

startSession();
requireCustomerLogin();

$pageTitle = 'My Orders - Smart Transaction System';

$activeOrders = [];
$pastOrders = [];
$coupons = [];

try {
    $pdo = getDBConnection();
    $customerId = (int) $_SESSION['customer_id'];

    // Fetch active orders (pending, preparing, ready)
    $activeStmt = $pdo->prepare('
        SELECT o.*, 
               p.payment_method, p.transaction_ref
        FROM orders o
        LEFT JOIN payments p ON p.order_id = o.id AND p.status = "completed"
        WHERE o.customer_id = :customer_id 
          AND o.status IN ("pending", "preparing", "ready")
        ORDER BY o.created_at DESC
    ');
    $activeStmt->execute([':customer_id' => $customerId]);
    $activeOrders = $activeStmt->fetchAll();

    // Fetch order items for active orders
    $activeOrderIds = array_column($activeOrders, 'id');
    if (!empty($activeOrderIds)) {
        $placeholders = implode(',', array_fill(0, count($activeOrderIds), '?'));
        $itemsStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id IN ($placeholders) ORDER BY order_id, id");
        $itemsStmt->execute($activeOrderIds);
        $allItems = $itemsStmt->fetchAll();

        // Group items by order_id
        $itemsByOrder = [];
        foreach ($allItems as $item) {
            $itemsByOrder[$item['order_id']][] = $item;
        }
    }

    // Fetch past orders (completed, cancelled)
    $pastStmt = $pdo->prepare('
        SELECT o.*, 
               p.payment_method, p.transaction_ref
        FROM orders o
        LEFT JOIN payments p ON p.order_id = o.id AND p.status = "completed"
        WHERE o.customer_id = :customer_id 
          AND o.status IN ("completed", "cancelled")
        ORDER BY o.created_at DESC
    ');
    $pastStmt->execute([':customer_id' => $customerId]);
    $pastOrders = $pastStmt->fetchAll();

    // Fetch order items for past orders
    $pastOrderIds = array_column($pastOrders, 'id');
    if (!empty($pastOrderIds)) {
        $placeholders = implode(',', array_fill(0, count($pastOrderIds), '?'));
        $itemsStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id IN ($placeholders) ORDER BY order_id, id");
        $itemsStmt->execute($pastOrderIds);
        $allPastItems = $itemsStmt->fetchAll();

        $pastItemsByOrder = [];
        foreach ($allPastItems as $item) {
            $pastItemsByOrder[$item['order_id']][] = $item;
        }
    }

    // Fetch customer coupons
    $couponStmt = $pdo->prepare('
        SELECT * FROM coupons 
        WHERE customer_id = :customer_id 
        ORDER BY issued_at DESC
    ');
    $couponStmt->execute([':customer_id' => $customerId]);
    $coupons = $couponStmt->fetchAll();

} catch (PDOException $e) {
    error_log('My Orders page error: ' . $e->getMessage());
    $error = 'An error occurred. Please try again later.';
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>My Orders</h1>
    <p>Track your current orders and view past orders</p>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Tab Navigation -->
<div class="order-tabs mb-3" id="orderTabs">
    <button class="order-tab active" data-tab="active">
        Active Orders
        <?php if (!empty($activeOrders)): ?>
            <span class="badge badge-preparing" style="margin-left: 0.5rem; font-size: 0.7rem;"><?php echo count($activeOrders); ?></span>
        <?php endif; ?>
    </button>
    <button class="order-tab" data-tab="history">
        Order History
        <?php if (!empty($pastOrders)): ?>
            <span class="badge badge-completed" style="margin-left: 0.5rem; font-size: 0.7rem;"><?php echo count($pastOrders); ?></span>
        <?php endif; ?>
    </button>
    <button class="order-tab" data-tab="coupons">
        My Coupons
        <?php 
        $activeCouponCount = 0;
        foreach ($coupons as $c) {
            if (!$c['is_used'] && strtotime($c['expires_at']) > time()) $activeCouponCount++;
        }
        ?>
        <?php if ($activeCouponCount > 0): ?>
            <span class="badge badge-paid" style="margin-left: 0.5rem; font-size: 0.7rem;"><?php echo $activeCouponCount; ?></span>
        <?php endif; ?>
    </button>
</div>

<!-- ============================================================ -->
<!-- SECTION 1: ACTIVE ORDERS -->
<!-- ============================================================ -->
<div id="activeOrdersSection" class="order-section">
    <?php if (empty($activeOrders)): ?>
        <div class="card">
            <div class="card-body text-center" style="padding: var(--spacing-2xl);">
                <p style="font-size: 3rem; margin-bottom: 1rem;">&#128230;</p>
                <h3>No active orders</h3>
                <p class="text-muted mt-1">No active orders right now. Browse the menu to place an order!</p>
                <a href="/smart-transaction/index.php" class="btn btn-primary mt-2">Browse Menu</a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($activeOrders as $order): 
            $orderId = (int) $order['id'];
            $statusSteps = ['pending', 'preparing', 'ready', 'completed'];
            $currentStatus = $order['status'];
            $currentStepIndex = array_search($currentStatus, $statusSteps);
            $items = $itemsByOrder[$orderId] ?? [];
        ?>
            <div class="card mb-3 active-order-card" data-order-id="<?php echo $orderId; ?>">
                <div class="card-header d-flex justify-between align-center" style="flex-wrap: wrap; gap: 0.5rem;">
                    <div>
                        <strong>Order #<?php echo $orderId; ?></strong>
                        <span style="font-size: 0.85rem; color: var(--neutral-500); margin-left: 0.5rem;">
                            <?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?>
                        </span>
                    </div>
                    <div class="d-flex gap-1 align-center" style="flex-wrap: wrap;">
                        <?php if ($order['payment_status'] === 'paid'): ?>
                            <span class="badge badge-paid">Paid</span>
                        <?php else: ?>
                            <span class="badge badge-unpaid">Unpaid</span>
                        <?php endif; ?>
                        <span class="badge badge-<?php echo htmlspecialchars($order['status']); ?> status-badge">
                            <?php echo htmlspecialchars(ucfirst($order['status'])); ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Order Items -->
                    <div style="margin-bottom: 1rem;">
                        <?php foreach ($items as $item): ?>
                            <div class="d-flex justify-between" style="padding: 0.25rem 0; font-size: 0.9rem;">
                                <span><?php echo htmlspecialchars($item['product_name']); ?> <strong>x<?php echo (int) $item['quantity']; ?></strong></span>
                                <span>RM <?php echo number_format((float) $item['subtotal'], 2); ?></span>
                            </div>
                        <?php endforeach; ?>
                        <hr style="border: none; border-top: 1px solid var(--neutral-200); margin: 0.5rem 0;">
                        <div class="d-flex justify-between" style="font-weight: bold;">
                            <span>Total</span>
                            <span>RM <?php echo number_format((float) $order['total_amount'], 2); ?></span>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <?php if ($currentStatus !== 'cancelled'): ?>
                        <div class="order-progress">
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

                    <!-- Action Button -->
                    <div style="margin-top: 1rem; text-align: right;">
                        <a href="/smart-transaction/receipt.php?order_id=<?php echo $orderId; ?>" class="btn btn-sm btn-outline">View Receipt</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ============================================================ -->
<!-- SECTION 2: ORDER HISTORY -->
<!-- ============================================================ -->
<div id="historySection" class="order-section" style="display: none;">
    <?php if (empty($pastOrders)): ?>
        <div class="card">
            <div class="card-body text-center" style="padding: var(--spacing-2xl);">
                <p style="font-size: 3rem; margin-bottom: 1rem;">&#128203;</p>
                <h3>No past orders</h3>
                <p class="text-muted mt-1">No past orders yet.</p>
                <a href="/smart-transaction/index.php" class="btn btn-primary mt-2">Browse Menu</a>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header">
                Past Orders (<?php echo count($pastOrders); ?>)
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Date & Time</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pastOrders as $order): 
                                $orderId = (int) $order['id'];
                                $items = $pastItemsByOrder[$orderId] ?? [];
                                $itemSummary = [];
                                foreach ($items as $item) {
                                    $itemSummary[] = htmlspecialchars($item['product_name']) . ' x' . (int) $item['quantity'];
                                }
                                $itemSummaryStr = !empty($itemSummary) ? implode(', ', $itemSummary) : '-';
                            ?>
                                <tr>
                                    <td><strong>#<?php echo $orderId; ?></strong></td>
                                    <td><?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?></td>
                                    <td style="font-size: 0.85rem;"><?php echo $itemSummaryStr; ?></td>
                                    <td>
                                        <strong>RM <?php echo number_format((float) $order['total_amount'], 2); ?></strong>
                                        <?php if ($order['coupon_id']): ?>
                                            <br><span style="font-size: 0.7rem; color: #16a34a;">&#127873; Coupon used</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $methodLabels = ['cash' => 'Cash', 'online' => 'Online', 'ewallet' => 'E-Wallet'];
                                        echo $methodLabels[$order['payment_method'] ?? ''] ?? '-';
                                        ?>
                                        <?php if ($order['payment_status'] === 'paid'): ?>
                                            <br><span class="badge badge-paid" style="font-size: 0.7rem;">Paid</span>
                                        <?php else: ?>
                                            <br><span class="badge badge-unpaid" style="font-size: 0.7rem;">Unpaid</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo htmlspecialchars($order['status']); ?>">
                                            <?php echo htmlspecialchars(ucfirst($order['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="/smart-transaction/receipt.php?order_id=<?php echo $orderId; ?>" 
                                           class="btn btn-sm btn-outline">View Receipt</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ============================================================ -->
<!-- SECTION 3: MY COUPONS -->
<!-- ============================================================ -->
<div id="couponsSection" class="order-section" style="display: none;">
    <?php if (empty($coupons)): ?>
        <div class="card">
            <div class="card-body text-center" style="padding: var(--spacing-2xl);">
                <p style="font-size: 3rem; margin-bottom: 1rem;">&#127873;</p>
                <h3>No coupons yet</h3>
                <p class="text-muted mt-1">Complete your 2nd order to earn your first coupon reward! 🎉</p>
                <a href="/smart-transaction/index.php" class="btn btn-primary mt-2">Browse Menu</a>
            </div>
        </div>
    <?php else: ?>
        <div style="margin-bottom: 1rem;">
            <p style="color: var(--neutral-600);">
                You have <strong style="color: var(--primary);"><?php echo $activeCouponCount; ?> active coupon(s)</strong> available to use.
            </p>
        </div>
        <div class="coupon-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem;">
            <?php foreach ($coupons as $coupon): 
                $isExpired = strtotime($coupon['expires_at']) < time();
                $isActive = !$coupon['is_used'] && !$isExpired;
                $isUsed = $coupon['is_used'];
            ?>
                <div class="card coupon-card" style="border-left: 4px solid <?php echo $isActive ? '#16a34a' : ($isUsed ? '#dc2626' : '#f59e0b'); ?>;">
                    <div class="card-body">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                            <div>
                                <strong style="font-size: 1.1rem; font-family: monospace; letter-spacing: 0.5px; color: var(--primary);">
                                    <?php echo htmlspecialchars($coupon['coupon_code']); ?>
                                </strong>
                                <div style="margin-top: 0.25rem;">
                                    <span class="badge badge-<?php echo $coupon['tier']; ?>" style="background: var(--primary); color: white; font-size: 0.7rem;">
                                        <?php echo htmlspecialchars($coupon['tier_name']); ?>
                                    </span>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 1.5rem; font-weight: bold; color: <?php echo $isActive ? '#16a34a' : ($isUsed ? '#dc2626' : '#f59e0b'); ?>;">
                                    <?php echo (int) $coupon['discount_percent']; ?>% OFF
                                </div>
                                <?php if ($isActive): ?>
                                    <span class="badge badge-paid" style="font-size: 0.7rem;">Active</span>
                                <?php elseif ($isUsed): ?>
                                    <span class="badge badge-unpaid" style="font-size: 0.7rem; background: #dc2626;">Used</span>
                                <?php else: ?>
                                    <span class="badge badge-pending" style="font-size: 0.7rem; background: #f59e0b;">Expired</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <hr style="border: none; border-top: 1px solid var(--neutral-200); margin: 0.5rem 0;">
                        <div style="font-size: 0.85rem; color: var(--neutral-600);">
                            <p><strong>Issued:</strong> <?php echo date('d M Y', strtotime($coupon['issued_at'])); ?></p>
                            <p><strong>Expires:</strong> <?php echo date('d M Y', strtotime($coupon['expires_at'])); ?></p>
                            <?php if ($isUsed && $coupon['used_in_order_id']): ?>
                                <p><strong>Used on:</strong> Order #<?php echo (int) $coupon['used_in_order_id']; ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if ($isActive): ?>
                            <div style="margin-top: 0.75rem; padding: 0.5rem; background: #f0fdf4; border-radius: 6px; font-size: 0.8rem; color: #166534; text-align: center;">
                                &#127873; Available to use at checkout
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Auto-refresh script for active orders -->
<script>
(function() {
    var activeSection = document.getElementById('activeOrdersSection');
    if (!activeSection) return;

    var orderCards = activeSection.querySelectorAll('.active-order-card');
    if (orderCards.length === 0) return;

    // Collect all active order IDs
    var orderIds = [];
    orderCards.forEach(function(card) {
        var id = card.getAttribute('data-order-id');
        if (id) orderIds.push(parseInt(id));
    });

    function refreshActiveOrders() {
        if (orderIds.length === 0) return;

        var queryParams = orderIds.map(function(id) { return 'order_ids[]=' + id; }).join('&');
        fetch('/smart-transaction/get_active_orders.php?' + queryParams)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (!data || data.length === 0) return;

                data.forEach(function(update) {
                    var card = activeSection.querySelector('.active-order-card[data-order-id="' + update.order_id + '"]');
                    if (!card) return;

                    // Update status badge
                    var badge = card.querySelector('.status-badge');
                    if (badge && update.status) {
                        badge.textContent = update.status.charAt(0).toUpperCase() + update.status.slice(1);
                        badge.className = 'badge badge-' + update.status + ' status-badge';
                    }

                    // Update payment badge
                    var payBadge = card.querySelector('.badge-paid, .badge-unpaid');
                    if (payBadge && update.payment_status) {
                        if (update.payment_status === 'paid') {
                            payBadge.textContent = 'Paid';
                            payBadge.className = 'badge badge-paid';
                        } else {
                            payBadge.textContent = 'Unpaid';
                            payBadge.className = 'badge badge-unpaid';
                        }
                    }

                    // Reload page if status changed to completed/cancelled (moves to history)
                    if (update.status === 'completed' || update.status === 'cancelled') {
                        location.reload();
                    }
                });
            })
            .catch(function() {
                // Silently fail
            });
    }

    // Auto-refresh every 15 seconds
    setInterval(refreshActiveOrders, 15000);
})();
</script>

<!-- Tab switching script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var tabs = document.querySelectorAll('.order-tab');
    var sections = {
        'active': document.getElementById('activeOrdersSection'),
        'history': document.getElementById('historySection'),
        'coupons': document.getElementById('couponsSection')
    };

    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            // Update tab active state
            tabs.forEach(function(t) { t.classList.remove('active'); });
            this.classList.add('active');

            // Show/hide sections
            var target = this.getAttribute('data-tab');
            Object.keys(sections).forEach(function(key) {
                if (sections[key]) {
                    sections[key].style.display = (key === target) ? 'block' : 'none';
                }
            });
        });
    });
});
</script>

<style>
/* Order tabs styling */
.order-tabs {
    display: flex;
    gap: 0.5rem;
    border-bottom: 2px solid var(--neutral-200);
    padding-bottom: 0;
}

.order-tab {
    padding: 0.75rem 1.5rem;
    border: none;
    background: none;
    cursor: pointer;
    font-size: 1rem;
    font-weight: 500;
    color: var(--neutral-500);
    border-bottom: 3px solid transparent;
    margin-bottom: -2px;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
}

.order-tab:hover {
    color: var(--primary);
    background: rgba(1, 105, 111, 0.05);
}

.order-tab.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
}

/* Progress bar for active orders */
.order-progress {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.5rem 0;
}

.order-progress .progress-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    flex: 1;
}

.order-progress .progress-step-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: var(--neutral-200);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.order-progress .progress-step.completed .progress-step-icon {
    background: var(--primary);
    color: white;
}

.order-progress .progress-step.current .progress-step-icon {
    background: var(--primary);
    color: white;
    box-shadow: 0 0 0 4px rgba(1, 105, 111, 0.2);
}

.order-progress .progress-step-label {
    font-size: 0.75rem;
    margin-top: 0.25rem;
    color: var(--neutral-500);
    font-weight: 500;
}

.order-progress .progress-step.completed .progress-step-label,
.order-progress .progress-step.current .progress-step-label {
    color: var(--primary);
}

.order-progress .progress-connector {
    position: absolute;
    top: 18px;
    left: calc(50% + 18px);
    width: calc(100% - 36px);
    height: 3px;
    background: var(--neutral-200);
    z-index: 0;
}

.order-progress .progress-connector.completed {
    background: var(--primary);
}

@media (max-width: 576px) {
    .order-progress .progress-step-icon {
        width: 28px;
        height: 28px;
        font-size: 0.8rem;
    }
    .order-progress .progress-connector {
        top: 14px;
        left: calc(50% + 14px);
        width: calc(100% - 28px);
    }
    .order-progress .progress-step-label {
        font-size: 0.65rem;
    }
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
