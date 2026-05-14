<?php
/**
 * Cart & Checkout Page
 * 
 * Displays all items in $_SESSION['cart'] with quantity controls.
 * Customer info form for name and phone.
 * On checkout: saves order to database and redirects to payment.php.
 */

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';

startSession();

$pageTitle = 'Cart - Smart Transaction System';

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
        header('Location: /smart-transaction/cart.php');
        exit;
    }

    // Remove item
    if (isset($_POST['remove_item'])) {
        $productId = (int) $_POST['remove_item'];
        unset($_SESSION['cart'][$productId]);
        header('Location: /smart-transaction/cart.php');
        exit;
    }

    // Place order (checkout)
    if (isset($_POST['checkout'])) {
        $customerName  = trim($_POST['customer_name'] ?? '');
        $customerPhone = trim($_POST['customer_phone'] ?? '');

        if (empty($customerName)) {
            $error = 'Please enter your name.';
        } elseif (empty($_SESSION['cart'])) {
            $error = 'Your cart is empty.';
        } else {
            try {
                $pdo = getDBConnection();

                // Fetch product details for cart items
                $productIds = array_keys($_SESSION['cart']);
                $placeholders = implode(',', array_fill(0, count($productIds), '?'));
                $stmt = $pdo->prepare("SELECT id, name, price FROM products WHERE id IN ($placeholders) AND is_available = 1");
                $stmt->execute($productIds);
                $products = $stmt->fetchAll();

                // Calculate total
                $totalAmount = 0;
                $orderItems = [];
                foreach ($products as $product) {
                    $pid = (int) $product['id'];
                    $qty = $_SESSION['cart'][$pid];
                    $subtotal = (float) $product['price'] * $qty;
                    $totalAmount += $subtotal;
                    $orderItems[] = [
                        'product_id'   => $pid,
                        'product_name' => $product['name'],
                        'quantity'     => $qty,
                        'unit_price'   => (float) $product['price'],
                        'subtotal'     => $subtotal,
                    ];
                }

                if (empty($orderItems)) {
                    $error = 'No valid items in cart.';
                } else {
                    // Begin transaction
                    $pdo->beginTransaction();

                    // Insert order
                    $orderStmt = $pdo->prepare('INSERT INTO orders (customer_name, customer_phone, total_amount, status, payment_status) VALUES (:name, :phone, :total, :status, :payment_status)');
                    $orderStmt->execute([
                        ':name'           => $customerName,
                        ':phone'          => $customerPhone,
                        ':total'          => $totalAmount,
                        ':status'         => 'pending',
                        ':payment_status' => 'unpaid',
                    ]);
                    $orderId = (int) $pdo->lastInsertId();

                    // Insert order items
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

                    // Log order status
                    $logStmt = $pdo->prepare('INSERT INTO order_status_logs (order_id, old_status, new_status, changed_by) VALUES (:order_id, NULL, :new_status, :changed_by)');
                    $logStmt->execute([
                        ':order_id'   => $orderId,
                        ':new_status' => 'pending',
                        ':changed_by' => 'System',
                    ]);

                    $pdo->commit();

                    // Clear cart and store order ID in session for payment
                    $_SESSION['cart'] = [];
                    $_SESSION['last_order_id'] = $orderId;

                    header('Location: /smart-transaction/payment.php?order_id=' . $orderId);
                    exit;
                }
            } catch (PDOException $e) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'An error occurred while placing your order. Please try again.';
            }
        }
    }
}

// Fetch current cart product details
$cartItems = [];
$cartTotal = 0;

if (!empty($_SESSION['cart'])) {
    try {
        $pdo = getDBConnection();
        $productIds = array_keys($_SESSION['cart']);
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $stmt = $pdo->prepare("SELECT id, name, price FROM products WHERE id IN ($placeholders)");
        $stmt->execute($productIds);
        $products = $stmt->fetchAll();

        foreach ($products as $product) {
            $pid = (int) $product['id'];
            $qty = $_SESSION['cart'][$pid];
            $subtotal = (float) $product['price'] * $qty;
            $cartTotal += $subtotal;
            $cartItems[] = [
                'id'       => $pid,
                'name'     => $product['name'],
                'price'    => (float) $product['price'],
                'quantity' => $qty,
                'subtotal' => $subtotal,
            ];
        }
    } catch (PDOException $e) {
        $error = 'Unable to load cart details.';
    }
}

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
                <div class="cart-summary">
                    <div class="summary-row">
                        <span>Subtotal (<?php echo count($cartItems); ?> items)</span>
                        <span>RM <?php echo number_format($cartTotal, 2); ?></span>
                    </div>
                    <div class="summary-row total">
                        <span>Total</span>
                        <span>RM <?php echo number_format($cartTotal, 2); ?></span>
                    </div>
                </div>

                <form method="POST" action="" id="checkoutForm" data-validate>
                    <div class="form-group">
                        <label for="customer_name" class="form-label">Full Name *</label>
                        <input type="text" id="customer_name" name="customer_name" class="form-input" required
                               placeholder="Enter your name">
                    </div>
                    <div class="form-group">
                        <label for="customer_phone" class="form-label">Phone Number</label>
                        <input type="text" id="customer_phone" name="customer_phone" class="form-input"
                               placeholder="e.g. 012-3456789">
                    </div>
                    <button type="submit" name="checkout" value="1" class="btn btn-primary btn-block btn-lg">
                        Proceed to Payment
                    </button>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
