<?php
/**
 * Payment Page — Smart Transaction
 * 
 * Shows order summary and payment method selection.
 * On submit: records payment, updates order, creates transaction record.
 * Redirects to receipt.php on success.
 * Includes mock payment simulation with loading animations.
 * All payment methods auto-generate a transaction reference number.
 * 
 * Cash: Order stays unpaid — staff confirms payment in admin panel.
 * Online/E-Wallet: Payment processed immediately with mock simulation.
 * 
 * All totals are fetched from the DATABASE, not from session.
 */

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/customer_auth.php';
require_once __DIR__ . '/includes/promotion_helper.php';

startSession();
requireCustomerLogin();

$pageTitle = 'Payment — Smart Transaction';

$orderId = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;

if ($orderId <= 0) {
    header('Location: /smart-transaction/index.php');
    exit;
}

// Fetch order details
try {
    $pdo = getDBConnection();

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

    // Check if already paid
    if ($order['payment_status'] === 'paid') {
        header('Location: /smart-transaction/receipt.php?order_id=' . $orderId);
        exit;
    }

    // ============================================================
    // CALCULATE FROM DATABASE
    // ============================================================
    $subtotal = 0;
    $promoSavings = 0;
    foreach ($orderItems as $item) {
        $unitPrice = (float) $item['unit_price'];
        $qty = (int) $item['quantity'];
        $subtotal += $unitPrice * $qty;

        // Check if product had active promotion at time of order
        $promo = getProductPromo($pdo, $item['product_id']);
        if ($promo) {
            $discountedPrice = getDiscountedPrice($unitPrice, $promo['discount_percent']);
            $promoSavings += round(($unitPrice - $discountedPrice) * $qty, 2);
        }
    }
    $subtotal = round($subtotal, 2);
    $promoSavings = round($promoSavings, 2);
    $subtotalAfterPromos = $subtotal - $promoSavings;

    $discountPercent = 0;
    $couponCode = null;
    $discountAmount = 0;

    if ($order['coupon_id']) {
        $couponStmt = $pdo->prepare('SELECT * FROM coupons WHERE id = :id LIMIT 1');
        $couponStmt->execute([':id' => $order['coupon_id']]);
        $couponInfo = $couponStmt->fetch();

        if ($couponInfo) {
            $discountPercent = (float) $couponInfo['discount_percent'];
            $couponCode = $couponInfo['coupon_code'];
            $discountAmount = round($subtotalAfterPromos * ($discountPercent / 100), 2);
        }
    }

    $orderTotal = (float) $order['total_amount'];

} catch (PDOException $e) {
    $error = 'Unable to load order details.';
}

// Handle payment submission (via AJAX or regular POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    $paymentMethod = $_POST['payment_method'] ?? '';

    if (!in_array($paymentMethod, ['cash', 'online', 'ewallet'])) {
        $error = 'Please select a valid payment method.';
    } else {
        try {
            $pdo->beginTransaction();

            if ($paymentMethod === 'cash') {
                // Cash: Order stays unpaid — staff will confirm payment later
                $pdo->commit();

                // If AJAX request, return JSON
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'redirect' => '/smart-transaction/receipt.php?order_id=' . $orderId]);
                    exit;
                }

                header('Location: /smart-transaction/receipt.php?order_id=' . $orderId);
                exit;
            }

            // Online / E-Wallet: Process payment immediately
            $methodPrefixes = [
                'online' => 'ONLINE',
                'ewallet' => 'EWALLET',
            ];
            $prefix = $methodPrefixes[$paymentMethod];
            $transactionRef = $prefix . '-' . str_pad($orderId, 5, '0', STR_PAD_LEFT) . '-' . date('YmdHis');

            // Insert payment record
            $payStmt = $pdo->prepare('INSERT INTO payments (order_id, payment_method, amount_paid, transaction_ref, status) VALUES (:order_id, :method, :amount, :ref, :status)');
            $payStmt->execute([
                ':order_id' => $orderId,
                ':method'   => $paymentMethod,
                ':amount'   => $orderTotal,
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
                ':total'      => $orderTotal,
                ':method'     => $paymentMethod,
                ':status'     => 'completed',
            ]);

            $pdo->commit();

            // If AJAX request, return JSON
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'redirect' => '/smart-transaction/receipt.php?order_id=' . $orderId]);
                exit;
            }

            header('Location: /smart-transaction/receipt.php?order_id=' . $orderId);
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Payment processing error: ' . $e->getMessage());
            $error = 'Payment processing failed. Please try again.';
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Payment</h1>
    <p>Complete your payment for Order #<?php echo $orderId; ?></p>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if (isset($order) && $order): ?>
<div class="grid grid-2" style="align-items: start;">
    <!-- Order Summary -->
    <div class="card">
        <div class="card-header">Order Summary</div>
        <div class="card-body">
            <p><strong>Order #:</strong> <?php echo $orderId; ?></p>
            <p><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
            <?php if ($order['customer_phone']): ?>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['customer_phone']); ?></p>
            <?php endif; ?>
            <?php if (!empty($order['table_number'])): ?>
                <p><strong>Table:</strong> <?php echo htmlspecialchars($order['table_number']); ?></p>
            <?php endif; ?>
            <p><strong>Date:</strong> <?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?></p>

            <hr style="margin: 1rem 0; border: none; border-top: 1px solid var(--neutral-200);">

            <?php foreach ($orderItems as $item): ?>
                <div class="receipt-item">
                    <span><?php echo htmlspecialchars($item['product_name']); ?> x<?php echo (int) $item['quantity']; ?></span>
                    <span>RM <?php echo number_format((float) $item['subtotal'], 2); ?></span>
                </div>
            <?php endforeach; ?>

            <hr style="margin: 0.75rem 0; border: none; border-top: 1px solid var(--neutral-200);">

            <!-- Items Subtotal -->
            <div style="display: flex; justify-content: space-between; padding: 0.25rem 0; font-size: 0.9rem;">
                <span>Items Subtotal</span>
                <span>RM <?php echo number_format($subtotal, 2); ?></span>
            </div>

            <!-- Promotion Savings -->
            <?php if ($promoSavings > 0): ?>
            <div style="display: flex; justify-content: space-between; padding: 0.25rem 0; font-size: 0.9rem; color: #16a34a;">
                <span>Promotion Savings</span>
                <span>- RM <?php echo number_format($promoSavings, 2); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 0.25rem 0; font-size: 0.9rem; border-top:1px dashed #e7e5e4;">
                <span>Subtotal after Promotions</span>
                <span>RM <?php echo number_format($subtotalAfterPromos, 2); ?></span>
            </div>
            <?php endif; ?>

            <!-- Coupon Discount -->
            <?php if ($discountAmount > 0 && $couponCode): ?>
                <div style="display: flex; justify-content: space-between; padding: 0.25rem 0; font-size: 0.9rem; color: #16a34a;">
                    <span>Coupon Discount (<?php echo (int) $discountPercent; ?>% off)</span>
                    <span>- RM <?php echo number_format($discountAmount, 2); ?></span>
                </div>
                <div style="font-size: 0.8rem; color: var(--neutral-500); text-align: right; padding-bottom: 0.25rem;">
                    Coupon: <?php echo htmlspecialchars($couponCode); ?>
                </div>
            <?php endif; ?>

            <!-- Total -->
            <div class="receipt-total" style="border-top-color: var(--neutral-300);">
                <span>Total</span>
                <span>RM <?php echo number_format($orderTotal, 2); ?></span>
            </div>
        </div>
    </div>

    <!-- Payment Form -->
    <div class="card">
        <div class="card-header">Select Payment Method</div>
        <div class="card-body">
            <form method="POST" action="" id="paymentForm">
                <div class="form-group">
                    <label class="form-label">Payment Method *</label>
                    <div class="payment-methods">
                        <label class="payment-method-option">
                            <input type="radio" name="payment_method" value="cash" class="payment-radio" checked>
                            <span class="payment-radio-icon">&#128176;</span>
                            <div>
                                <strong>Cash</strong>
                                <p class="payment-method-desc">Pay at counter</p>
                            </div>
                        </label>
                        <label class="payment-method-option">
                            <input type="radio" name="payment_method" value="online" class="payment-radio">
                            <span class="payment-radio-icon">&#127760;</span>
                            <div>
                                <strong>Online Banking</strong>
                                <p class="payment-method-desc">FPX / Online transfer</p>
                            </div>
                        </label>
                        <label class="payment-method-option">
                            <input type="radio" name="payment_method" value="ewallet" class="payment-radio">
                            <span class="payment-radio-icon">&#128179;</span>
                            <div>
                                <strong>E-Wallet</strong>
                                <p class="payment-method-desc">Touch & Go, GrabPay, etc.</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Cash info message (shown when cash is selected) -->
                <div class="form-group" id="cashMessage">
                    <div class="alert alert-info">
                        <strong>&#128176; Pay at Counter</strong>
                        <p style="margin-top: 0.25rem;">Please proceed to the counter to pay. Your order will be prepared once payment is confirmed by our staff.</p>
                    </div>
                </div>

                <button type="submit" name="process_payment" value="1" class="btn btn-success btn-block btn-lg mt-2" id="payButton">
                    Place Order — RM <?php echo number_format($orderTotal, 2); ?>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Payment Processing Overlay -->
<div class="payment-overlay" id="paymentOverlay" style="display: none;">
    <div class="payment-overlay-content">
        <!-- Cash: Order placed message -->
        <div class="payment-loading" id="paymentCashSuccess" style="display: none;">
            <div style="font-size: 3rem; margin-bottom: var(--spacing-md);">&#128176;</div>
            <p class="payment-loading-text" style="font-size: var(--font-size-xl);">Order Placed!</p>
            <p style="color: var(--neutral-600); margin-bottom: var(--spacing-sm);">Please proceed to the counter to pay.</p>
            <p style="font-size: var(--font-size-sm); color: var(--neutral-500);">Your order will be prepared once staff confirms payment.</p>
            <div style="margin-top: var(--spacing-lg);">
                <a href="/smart-transaction/receipt.php?order_id=<?php echo $orderId; ?>" class="btn btn-primary">View Order Details</a>
            </div>
        </div>

        <!-- Default Spinner (Online/E-Wallet) -->
        <div class="payment-loading" id="paymentLoading">
            <div class="payment-spinner"></div>
            <p class="payment-loading-text">Processing your payment...</p>
            <p class="payment-loading-sub">Please do not close this page</p>
        </div>

        <!-- Mock Online Banking Portal -->
        <div class="mock-portal" id="mockOnline" style="display: none;">
            <div class="mock-portal-header">
                <span class="mock-portal-icon">&#127760;</span>
                <h3>Online Banking</h3>
                <p>FPX Secure Payment</p>
            </div>
            <div class="mock-portal-body">
                <div class="mock-portal-row">
                    <span>Merchant:</span>
                    <span><strong>Smart Transaction</strong></span>
                </div>
                <div class="mock-portal-row">
                    <span>Amount:</span>
                    <span><strong>RM <?php echo number_format($orderTotal, 2); ?></strong></span>
                </div>
                <div class="mock-portal-row">
                    <span>Reference:</span>
                    <span><strong id="mockRefDisplay">-</strong></span>
                </div>
                <div class="mock-bank-select">
                    <label class="form-label">Select Your Bank:</label>
                    <select class="form-select" id="mockBankSelect">
                        <option value="">-- Select a bank --</option>
                        <option>Maybank2u</option>
                        <option>CIMB Clicks</option>
                        <option>Public Bank</option>
                        <option>RHB Now</option>
                        <option>Hong Leong Connect</option>
                        <option>Bank Islam</option>
                    </select>
                </div>
                <button class="btn btn-primary btn-block mt-2 mock-pay-btn" id="mockOnlinePayBtn">
                    Pay Now
                </button>
                <div class="mock-portal-status" id="mockOnlineStatus" style="display: none;">
                    <div class="payment-spinner" style="width: 24px; height: 24px; border-width: 3px;"></div>
                    <span>Redirecting to bank...</span>
                </div>
            </div>
        </div>

        <!-- Mock E-Wallet Portal -->
        <div class="mock-portal" id="mockEwallet" style="display: none;">
            <div class="mock-portal-header" style="background: linear-gradient(135deg, #1a73e8, #0d47a1);">
                <span class="mock-portal-icon">&#128179;</span>
                <h3>E-Wallet Payment</h3>
                <p>Secure checkout</p>
            </div>
            <div class="mock-portal-body">
                <div class="mock-portal-row">
                    <span>Merchant:</span>
                    <span><strong>Smart Transaction</strong></span>
                </div>
                <div class="mock-portal-row">
                    <span>Amount:</span>
                    <span><strong>RM <?php echo number_format($orderTotal, 2); ?></strong></span>
                </div>
                <div class="mock-portal-row">
                    <span>Reference:</span>
                    <span><strong id="mockRefDisplay2">-</strong></span>
                </div>
                <div class="mock-ewallet-options">
                    <label class="form-label">Select Your E-Wallet:</label>
                    <div class="ewallet-grid">
                        <div class="ewallet-option" data-wallet="Touch & Go">
                            <span style="font-size: 1.5rem;">&#128176;</span>
                            <span>Touch & Go</span>
                        </div>
                        <div class="ewallet-option" data-wallet="GrabPay">
                            <span style="font-size: 1.5rem;">&#128179;</span>
                            <span>GrabPay</span>
                        </div>
                        <div class="ewallet-option" data-wallet="ShopeePay">
                            <span style="font-size: 1.5rem;">&#128722;</span>
                            <span>ShopeePay</span>
                        </div>
                    </div>
                </div>
                <button class="btn btn-primary btn-block mt-2 mock-pay-btn" id="mockEwalletPayBtn">
                    Pay Now
                </button>
                <div class="mock-portal-status" id="mockEwalletStatus" style="display: none;">
                    <div class="payment-spinner" style="width: 24px; height: 24px; border-width: 3px;"></div>
                    <span>Processing e-wallet payment...</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Payment method toggle — show/hide cash message
document.addEventListener('DOMContentLoaded', function() {
    var radios = document.querySelectorAll('.payment-radio');
    var cashMsg = document.getElementById('cashMessage');
    var payBtn = document.getElementById('payButton');

    radios.forEach(function(radio) {
        radio.addEventListener('change', function() {
            if (this.value === 'cash') {
                cashMsg.style.display = 'block';
                payBtn.textContent = 'Place Order — RM <?php echo number_format($orderTotal, 2); ?>';
            } else {
                cashMsg.style.display = 'none';
                payBtn.textContent = 'Confirm Payment — RM <?php echo number_format($orderTotal, 2); ?>';
            }
        });
    });
});
</script>

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
