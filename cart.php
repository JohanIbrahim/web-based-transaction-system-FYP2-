<?php
/**
 * Cart & Checkout Page — Smart Transaction
 * 
 * Displays all items in $_SESSION['cart'] with quantity controls.
 * Customer info auto-filled from logged-in session.
 * On checkout: saves order to database and redirects to payment.php.
 * Stores customer_id in orders table.
 * 
 * Coupon system: Grab-style click-to-select, no manual code input.
 */

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/customer_auth.php';
require_once __DIR__ . '/includes/coupon_helper.php';
require_once __DIR__ . '/includes/promotion_helper.php';

startSession();
requireCustomerLogin();

$pageTitle = 'Cart — Smart Transaction';

// Handle coupon removal via GET (fallback if JS fails)
if (isset($_GET['remove_coupon'])) {
    unset($_SESSION['applied_coupon']);
    header('Location: /smart-transaction/cart.php');
    exit;
}

// Initialize cart
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle cart updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update quantities
    if (isset($_POST['update_cart'])) {
        foreach ($_POST['qty'] ?? [] as $productId => $quantity) {
            $qty = max(0, (int) $quantity);
            if ($qty > 0) {
                $_SESSION['cart'][(int) $productId] = $qty;
            } else {
                unset($_SESSION['cart'][(int) $productId]);
            }
        }
        // Clear applied coupon when cart changes
        unset($_SESSION['applied_coupon']);
        header('Location: /smart-transaction/cart.php');
        exit;
    }

    // Remove item
    if (isset($_POST['remove_item'])) {
        $productId = (int) $_POST['remove_item'];
        unset($_SESSION['cart'][$productId]);
        // Clear applied coupon when cart changes
        unset($_SESSION['applied_coupon']);
        header('Location: /smart-transaction/cart.php');
        exit;
    }

    // Place order (checkout)
    if (isset($_POST['checkout'])) {
        // Check if cart is empty
        if (empty($_SESSION['cart'])) {
            $_SESSION['flash_message'] = 'Your cart is empty.';
            $_SESSION['flash_type'] = 'warning';
            header('Location: /smart-transaction/index.php');
            exit;
        }

        // Validate session variables
        $customerId = $_SESSION['customer_id'] ?? 0;
        $customerName = $_SESSION['customer_name'] ?? '';
        $customerPhone = $_SESSION['customer_phone'] ?? '';

        if ($customerId <= 0 || empty($customerName)) {
            $_SESSION['flash_message'] = 'Session expired. Please log in again.';
            $_SESSION['flash_type'] = 'danger';
            header('Location: /smart-transaction/auth/login.php');
            exit;
        }

        try {
            $pdo = getDBConnection();

            // Fetch product details for cart items
            $productIds = array_keys($_SESSION['cart']);
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $stmt = $pdo->prepare("SELECT id, name, price FROM products WHERE id IN ($placeholders) AND is_available = 1");
            $stmt->execute($productIds);
            $products = $stmt->fetchAll();

            // Calculate total and build order items
            $totalAmount = 0;
            $orderItems = [];
            $promoSavingsCheckout = 0;
            foreach ($products as $product) {
                $pid = (int) $product['id'];
                $qty = (int) ($_SESSION['cart'][$pid] ?? 0);
                if ($qty <= 0) continue;
                $unitPrice = (float) $product['price'];
                $subtotal = round($unitPrice * $qty, 2);
                $totalAmount += $subtotal;

                // Check for active promotion
                $promo = getProductPromo($pdo, $pid);
                $itemPromoSaving = 0;
                if ($promo) {
                    $discountedPrice = getDiscountedPrice($unitPrice, $promo['discount_percent']);
                    $itemPromoSaving = round(($unitPrice - $discountedPrice) * $qty, 2);
                    $promoSavingsCheckout += $itemPromoSaving;
                }

                $orderItems[] = [
                    'product_id'   => $pid,
                    'product_name' => $product['name'],
                    'quantity'     => $qty,
                    'unit_price'   => $unitPrice,
                    'subtotal'     => $subtotal,
                ];
            }

            if (empty($orderItems)) {
                $_SESSION['flash_message'] = 'No valid items in cart.';
                $_SESSION['flash_type'] = 'warning';
                header('Location: /smart-transaction/cart.php');
                exit;
            }

            $subtotalAfterPromosCheckout = $totalAmount - $promoSavingsCheckout;

            // Apply coupon discount if available (on subtotal after promos)
            $finalAmount = $subtotalAfterPromosCheckout;
            $couponId = null;
            $couponCode = null;
            $discountAmount = 0;
            $discountPercent = 0;

            if (isset($_SESSION['applied_coupon']) && $_SESSION['applied_coupon']['valid']) {
                $applied = $_SESSION['applied_coupon'];
                // Recalculate coupon on subtotal after promos
                $discountPercent = $applied['discount_percent'];
                $discountAmount = round($subtotalAfterPromosCheckout * ($discountPercent / 100), 2);
                $finalAmount = $subtotalAfterPromosCheckout - $discountAmount;
                $couponId = $applied['coupon_id'];
                $couponCode = $applied['coupon_code'];
            }

            // Begin transaction
            $pdo->beginTransaction();

            // Validate table number
            $tableNumber = trim($_POST['table_number'] ?? '');
            if (empty($tableNumber)) {
                $_SESSION['flash_message'] = 'Please enter your table number.';
                $_SESSION['flash_type'] = 'warning';
                header('Location: /smart-transaction/cart.php');
                exit;
            }

            // Insert order (with coupon_id if applicable)
            $orderStmt = $pdo->prepare("
                INSERT INTO orders 
                (customer_id, customer_name, customer_phone, table_number, total_amount, coupon_id, status, payment_status, created_at, updated_at) 
                VALUES 
                (:customer_id, :customer_name, :customer_phone, :table_number, :total_amount, :coupon_id, 'pending', 'unpaid', NOW(), NOW())
            ");
            $orderStmt->execute([
                ':customer_id'    => $_SESSION['customer_id'] ?? null,
                ':customer_name'  => $_SESSION['customer_name'] ?? '',
                ':customer_phone' => $_SESSION['customer_phone'] ?? '',
                ':table_number'   => $tableNumber,
                ':total_amount'   => $finalAmount,
                ':coupon_id'      => $couponId,
            ]);
            $orderId = (int) $pdo->lastInsertId();

            // Insert order items with rounded subtotals
            $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, subtotal) VALUES (:order_id, :product_id, :product_name, :quantity, :unit_price, :subtotal)');
            foreach ($orderItems as $item) {
                $itemStmt->execute([
                    ':order_id'     => $orderId,
                    ':product_id'   => $item['product_id'],
                    ':product_name' => $item['product_name'],
                    ':quantity'     => $item['quantity'],
                    ':unit_price'   => $item['unit_price'],
                    ':subtotal'     => $item['subtotal'],
                ]);
            }

            // Mark coupon as used if applied
            if ($couponId) {
                markCouponAsUsed($pdo, $couponId, $orderId);
            }

            // Log order status
            $logStmt = $pdo->prepare('INSERT INTO order_status_logs (order_id, old_status, new_status, changed_by) VALUES (:order_id, :old_status, :new_status, :changed_by)');
            $logStmt->execute([
                ':order_id'   => $orderId,
                ':old_status' => null,
                ':new_status' => 'pending',
                ':changed_by' => 'System',
            ]);

            $pdo->commit();

            // Store order details in session for receipt
            $_SESSION['last_order'] = [
                'order_id'        => $orderId,
                'subtotal'        => $totalAmount,
                'promo_savings'   => $promoSavingsCheckout,
                'subtotal_after_promos' => $subtotalAfterPromosCheckout,
                'discount_amount' => $discountAmount,
                'discount_percent'=> $discountPercent,
                'coupon_code'     => $couponCode,
                'total_amount'    => $finalAmount,
            ];

            // Clear cart and coupon
            $_SESSION['cart'] = [];
            unset($_SESSION['applied_coupon']);
            $_SESSION['last_order_id'] = $orderId;

            header('Location: /smart-transaction/payment.php?order_id=' . $orderId);
            exit;

        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Order placement error: ' . $e->getMessage());
            $_SESSION['flash_message'] = 'Order failed: ' . $e->getMessage();
            $_SESSION['flash_type'] = 'danger';
            header('Location: /smart-transaction/cart.php');
            exit;
        }
    }
}

// Fetch current cart product details
$cartItems = [];
$cartTotal = 0;
$promoSavings = 0;

if (!empty($_SESSION['cart'])) {
    try {
        $pdo = getDBConnection();
        $productIds = array_keys($_SESSION['cart']);
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $stmt = $pdo->prepare("SELECT id, name, price, image_url FROM products WHERE id IN ($placeholders)");
        $stmt->execute($productIds);
        $products = $stmt->fetchAll();

        foreach ($products as $product) {
            $pid = (int) $product['id'];
            $qty = $_SESSION['cart'][$pid];
            $unitPrice = (float) $product['price'];
            $subtotal = round($unitPrice * $qty, 2);
            $cartTotal += $subtotal;

            // Check for active promotion
            $promo = getProductPromo($pdo, $pid);
            $itemPromoSaving = 0;
            if ($promo) {
                $discountedPrice = getDiscountedPrice($unitPrice, $promo['discount_percent']);
                $itemPromoSaving = round(($unitPrice - $discountedPrice) * $qty, 2);
                $promoSavings += $itemPromoSaving;
            }

            $cartItems[] = [
                'id'              => $pid,
                'name'            => $product['name'],
                'price'           => $unitPrice,
                'quantity'        => $qty,
                'subtotal'        => $subtotal,
                'promo'           => $promo,
                'promo_saving'    => $itemPromoSaving,
                'discounted_price'=> isset($discountedPrice) ? $discountedPrice : $unitPrice,
                'image_url'       => $product['image_url'] ?? '',
            ];
            unset($discountedPrice);
        }
    } catch (PDOException $e) {
        $error = 'Unable to load cart details.';
    }
}

$subtotalAfterPromos = $cartTotal - $promoSavings;

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Shopping Cart</h1>
    <p>Review your items before checkout</p>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if (empty($cartItems)): ?>
    <div class="card">
        <div class="card-body text-center" style="padding: 3rem 1rem;">
            <p style="font-size: 3rem; margin-bottom: 1rem;">&#128722;</p>
            <h3>Your cart is empty</h3>
            <p class="text-muted mt-1">Browse our menu and add items to get started.</p>
            <a href="/smart-transaction/index.php" class="btn btn-primary mt-2">Browse Menu</a>
        </div>
    </div>
<?php else: ?>
    <div class="grid grid-2" style="align-items: start;">
        <!-- Cart Items -->
        <div class="card">
            <div class="card-header">Cart Items (<?php echo count($cartItems); ?>)</div>
            <div class="card-body">
                <form method="POST" action="" id="cartForm">
                    <?php foreach ($cartItems as $item): ?>
                        <div class="cart-item" data-product-id="<?php echo $item['id']; ?>">
                            <?php if ($item['image_url']): ?>
                                <img src="/smart-transaction/uploads/<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" style="width: 48px; height: 48px; object-fit: cover; border-radius: 8px; margin-right: 0.75rem;">
                            <?php endif; ?>
                            <div class="cart-item-info">
                                <div class="cart-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                <div class="cart-item-unit-price">RM <?php echo number_format($item['price'], 2); ?> each</div>
                            </div>
                            <div class="cart-item-controls">
                                <div class="qty-control">
                                    <button type="button" class="qty-btn qty-minus" data-id="<?php echo $item['id']; ?>">-</button>
                                    <span class="qty-value"><?php echo $item['quantity']; ?></span>
                                    <button type="button" class="qty-btn qty-plus" data-id="<?php echo $item['id']; ?>">+</button>
                                    <input type="hidden" name="qty[<?php echo $item['id']; ?>]" value="<?php echo $item['quantity']; ?>" class="qty-input">
                                </div>
                                <div class="cart-item-subtotal">RM <?php echo number_format($item['subtotal'], 2); ?></div>
                                <button type="submit" name="remove_item" value="<?php echo $item['id']; ?>" class="btn btn-danger btn-sm remove-btn">&#10005;</button>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="d-flex justify-between mt-2">
                        <button type="submit" name="update_cart" value="1" class="btn btn-secondary btn-sm">Update Quantities</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Checkout Form -->
        <div class="card">
            <div class="card-header">Checkout</div>
            <div class="card-body">
                <!-- ============================================================ -->
                <!-- Grab-Style Coupon Selection (No Code Input) -->
                <!-- ============================================================ -->
                <div class="coupon-section" style="margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid var(--neutral-200);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                        <strong style="font-size: 0.95rem;">&#127873; Available Coupons</strong>
                        <span id="couponLoading" style="font-size: 0.8rem; color: var(--neutral-500); display: none;">Loading...</span>
                    </div>

                    <!-- Coupon cards container (populated by AJAX) -->
                    <div id="couponCardsContainer">
                        <!-- Applied coupon display (shown when coupon is applied) -->
                        <div id="appliedCouponDisplay" style="display: none;">
                            <div style="background: #dcfce7; border: 2px solid #16a34a; border-radius: 8px; padding: 0.75rem;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong style="color: #166534;">&#9989; Coupon Applied!</strong>
                                        <p style="margin: 0.25rem 0 0 0; font-size: 0.85rem; color: #166534;">
                                            <span id="appliedCouponInfo"></span>
                                        </p>
                                    </div>
                                    <button type="button" id="removeCouponBtn" class="btn btn-sm btn-danger" style="font-size: 0.75rem;">&#10005; Remove</button>
                                </div>
                            </div>
                        </div>

                        <!-- Coupon list (populated by AJAX) -->
                        <div id="couponList"></div>

                        <!-- No coupons message (shown by AJAX if empty) -->
                        <div id="noCouponsMessage" style="display: none;">
                            <div style="background: #f5f5f4; border: 1px solid #e7e5e4; border-radius: 8px; padding: 1rem; text-align: center;">
                                <p style="font-size: 1.5rem; margin-bottom: 0.5rem;">&#127873;</p>
                                <p style="color: var(--neutral-600); font-size: 0.9rem; margin: 0;">
                                    <strong>No coupons available</strong>
                                </p>
                                <p style="color: var(--neutral-500); font-size: 0.8rem; margin: 0.25rem 0 0 0;">
                                    Complete more orders to earn discount coupons!
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="cart-summary" id="orderSummary">
                    <div class="summary-row">
                        <span>Items Subtotal (<?php echo count($cartItems); ?> items)</span>
                        <span id="summarySubtotal">RM <?php echo number_format($cartTotal, 2); ?></span>
                    </div>
                    <?php if ($promoSavings > 0): ?>
                    <div class="summary-row" style="color: #16a34a;">
                        <span>Promotion Savings</span>
                        <span>- RM <?php echo number_format($promoSavings, 2); ?></span>
                    </div>
                    <div class="summary-row" style="border-top:1px dashed #e7e5e4;padding-top:0.5rem;">
                        <span>Subtotal after Promotions</span>
                        <span id="summaryAfterPromos">RM <?php echo number_format($subtotalAfterPromos, 2); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="summary-row" id="summaryDiscountRow" style="color: #16a34a; display: none;">
                        <span>Coupon Discount (<span id="summaryDiscountPercent">0</span>% off)</span>
                        <span>- RM <span id="summaryDiscountAmount">0.00</span></span>
                    </div>
                    <div class="summary-row total">
                        <span>Total</span>
                        <span id="summaryTotal">RM <?php echo number_format($subtotalAfterPromos, 2); ?></span>
                    </div>
                </div>

                <form method="POST" action="" id="checkoutForm">
                    <!-- Customer info auto-filled from session -->
                    <div class="form-group">
                        <label class="form-label">Customer</label>
                        <p style="padding: 0.5rem 0; color: var(--neutral-700);">
                            <strong><?php echo htmlspecialchars($_SESSION['customer_name']); ?></strong>
                            <?php if (!empty($_SESSION['customer_phone'])): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($_SESSION['customer_phone']); ?></small>
                            <?php endif; ?>
                        </p>
                    </div>

                    <!-- Table Number -->
                    <div class="form-group">
                        <label class="form-label" for="table_number">Table Number <span class="required">*</span></label>
                        <input type="text" id="table_number" name="table_number" class="form-input" 
                               placeholder="e.g. 5, A3, 12" required
                               value="<?php echo htmlspecialchars($_POST['table_number'] ?? ''); ?>">
                        <small class="text-muted" style="font-size: 0.75rem;">Enter your table number so we can serve you.</small>
                    </div>

                    <button type="submit" name="checkout" value="1" class="btn btn-primary btn-block btn-lg">
                        Proceed to Payment
                    </button>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Coupon AJAX Script -->
<style>
.coupon-card-selectable {
    border: 2px solid #e7e5e4;
    border-radius: 10px;
    padding: 0.85rem;
    margin-bottom: 0.75rem;
    background: #fff;
    transition: all 0.2s ease;
    cursor: pointer;
    position: relative;
}
.coupon-card-selectable:hover {
    border-color: #01696f;
    box-shadow: 0 2px 8px rgba(1, 105, 111, 0.1);
}
.coupon-card-selectable.selected {
    border-color: #16a34a;
    background: #f0fdf4;
}
.coupon-card-selectable.disabled {
    opacity: 0.5;
    pointer-events: none;
}
.coupon-tier-badge {
    display: inline-block;
    padding: 0.15rem 0.5rem;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 600;
    color: #fff;
}
.coupon-tier-1 { background: #0d9488; }
.coupon-tier-2 { background: #16a34a; }
.coupon-tier-3 { background: #2563eb; }
.coupon-tier-4 { background: #7c3aed; }
.coupon-tier-5 { background: #d97706; }
.coupon-use-btn {
    display: block;
    width: 100%;
    padding: 0.5rem;
    background: #01696f;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s ease;
    text-align: center;
    margin-top: 0.5rem;
}
.coupon-use-btn:hover {
    background: #014d52;
}
.coupon-use-btn:disabled {
    background: #d6d3d1;
    cursor: not-allowed;
}
.coupon-use-btn.used {
    background: #16a34a;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var cartTotal = <?php echo json_encode($cartTotal); ?>;
    var couponList = document.getElementById('couponList');
    var noCouponsMsg = document.getElementById('noCouponsMessage');
    var appliedDisplay = document.getElementById('appliedCouponDisplay');
    var removeBtn = document.getElementById('removeCouponBtn');
    var summaryDiscountRow = document.getElementById('summaryDiscountRow');
    var summaryDiscountPercent = document.getElementById('summaryDiscountPercent');
    var summaryDiscountAmount = document.getElementById('summaryDiscountAmount');
    var summaryTotal = document.getElementById('summaryTotal');
    var summarySubtotal = document.getElementById('summarySubtotal');
    var appliedCouponInfo = document.getElementById('appliedCouponInfo');
    var couponLoading = document.getElementById('couponLoading');

    // ============================================================
    // Load coupons via AJAX on page load
    // ============================================================
    function loadCoupons() {
        if (!couponList) return;

        couponLoading.style.display = 'inline';

        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/smart-transaction/get_customer_coupons.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            couponLoading.style.display = 'none';
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data.error) {
                        couponList.innerHTML = '';
                        noCouponsMsg.style.display = 'block';
                        return;
                    }
                    renderCoupons(data.coupons, data.order_total);
                } catch(e) {
                    couponList.innerHTML = '';
                    noCouponsMsg.style.display = 'block';
                }
            } else {
                couponList.innerHTML = '';
                noCouponsMsg.style.display = 'block';
            }
        };
        xhr.onerror = function() {
            couponLoading.style.display = 'none';
            couponList.innerHTML = '';
            noCouponsMsg.style.display = 'block';
        };
        xhr.send('order_total=' + cartTotal);
    }

    // ============================================================
    // Render coupon cards
    // ============================================================
    function renderCoupons(coupons, orderTotal) {
        if (!coupons || coupons.length === 0) {
            couponList.innerHTML = '';
            noCouponsMsg.style.display = 'block';
            return;
        }

        noCouponsMsg.style.display = 'none';
        var html = '';

        coupons.forEach(function(coupon) {
            var tierClass = 'coupon-tier-' + (coupon.tier || 1);
            var savings = 'RM ' + coupon.discount_amount.toFixed(2);

            html += '<div class="coupon-card-selectable" data-coupon-id="' + coupon.id + '">';
            html += '  <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">';
            html += '    <div>';
            html += '      <span class="coupon-tier-badge ' + tierClass + '">' + escapeHtml(coupon.tier_name || 'Tier ' + coupon.tier) + '</span>';
            html += '      <div style="font-size: 1.3rem; font-weight: bold; color: #01696f; margin-top: 0.25rem;">' + parseInt(coupon.discount_percent) + '% OFF</div>';
            html += '    </div>';
            html += '    <div style="text-align: right; font-size: 0.8rem; color: var(--neutral-500);">';
            html += '      <div>Expires: ' + escapeHtml(coupon.expires_formatted) + '</div>';
            html += '    </div>';
            html += '  </div>';
            html += '  <div style="font-size: 0.85rem; color: var(--neutral-600); margin-bottom: 0.5rem;">';
            html += '    Code: <strong>' + escapeHtml(coupon.coupon_code) + '</strong>';
            html += '  </div>';
            html += '  <div style="font-size: 0.85rem; color: #16a34a; margin-bottom: 0.5rem;">';
            html += '    You save: <strong>' + savings + '</strong> on this order';
            html += '  </div>';
            html += '  <button type="button" class="coupon-use-btn" data-coupon-id="' + coupon.id + '" data-discount="' + coupon.discount_percent + '" data-amount="' + coupon.discount_amount.toFixed(2) + '" data-final="' + coupon.final_total.toFixed(2) + '">Use This Coupon</button>';
            html += '</div>';
        });

        couponList.innerHTML = html;

        // Attach click handlers to "Use This Coupon" buttons
        couponList.querySelectorAll('.coupon-use-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var couponId = this.getAttribute('data-coupon-id');
                applyCoupon(couponId, orderTotal, this);
            });
        });
    }

    // ============================================================
    // Apply coupon via AJAX
    // ============================================================
    function applyCoupon(couponId, orderTotal, btnElement) {
        btnElement.disabled = true;
        btnElement.textContent = 'Applying...';

        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/smart-transaction/apply_coupon.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    var result = JSON.parse(xhr.responseText);
                    if (result.success) {
                        // Mark this coupon as selected
                        var card = btnElement.closest('.coupon-card-selectable');
                        couponList.querySelectorAll('.coupon-card-selectable').forEach(function(c) {
                            if (c === card) {
                                c.classList.add('selected');
                            } else {
                                c.classList.add('disabled');
                            }
                        });

                        // Update all buttons
                        couponList.querySelectorAll('.coupon-use-btn').forEach(function(b) {
                            if (b !== btnElement) {
                                b.disabled = true;
                                b.textContent = 'Unavailable';
                            } else {
                                b.textContent = '&#9989; Applied';
                                b.classList.add('used');
                            }
                        });

                        // Show applied display
                        appliedCouponInfo.innerHTML = 'Code: <strong>' + escapeHtml(result.coupon_code) + '</strong> &mdash; ' + result.discount_percent + '% off (' + escapeHtml(result.tier_name) + ')';
                        appliedDisplay.style.display = 'block';

                        // Update order summary
                        summaryDiscountPercent.textContent = result.discount_percent;
                        summaryDiscountAmount.textContent = result.discount_amount;
                        summaryDiscountRow.style.display = 'flex';
                        summaryTotal.textContent = 'RM ' + result.final_total;

                    } else {
                        alert(result.error || 'Failed to apply coupon.');
                        btnElement.disabled = false;
                        btnElement.textContent = 'Use This Coupon';
                    }
                } catch(e) {
                    alert('An error occurred. Please try again.');
                    btnElement.disabled = false;
                    btnElement.textContent = 'Use This Coupon';
                }
            } else {
                alert('Server error. Please try again.');
                btnElement.disabled = false;
                btnElement.textContent = 'Use This Coupon';
            }
        };
        xhr.onerror = function() {
            alert('Network error. Please try again.');
            btnElement.disabled = false;
            btnElement.textContent = 'Use This Coupon';
        };
        xhr.send('coupon_id=' + couponId + '&order_total=' + orderTotal);
    }

    // ============================================================
    // Remove coupon via AJAX
    // ============================================================
    if (removeBtn) {
        removeBtn.addEventListener('click', function() {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '/smart-transaction/remove_coupon.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (xhr.status === 200) {
                    // Reset all coupon cards
                    couponList.querySelectorAll('.coupon-card-selectable').forEach(function(c) {
                        c.classList.remove('selected', 'disabled');
                    });
                    couponList.querySelectorAll('.coupon-use-btn').forEach(function(b) {
                        b.disabled = false;
                        b.textContent = 'Use This Coupon';
                        b.classList.remove('used');
                    });

                    // Hide applied display
                    appliedDisplay.style.display = 'none';

                    // Restore original total
                    summaryDiscountRow.style.display = 'none';
                    summaryTotal.textContent = 'RM ' + cartTotal.toFixed(2);
                }
            };
            xhr.send();
        });
    }

    // ============================================================
    // Utility: escape HTML
    // ============================================================
    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // ============================================================
    // Initialize: load coupons on page load
    // ============================================================
    loadCoupons();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
