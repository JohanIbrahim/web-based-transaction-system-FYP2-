<?php
/**
 * Customer Menu Page
 * 
 * Displays all available products grouped by category.
 * Customers can browse the menu and add items to their cart.
 */

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';

startSession();

$pageTitle = 'Menu - Smart Transaction System';

// Initialize cart in session if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle "Add to Cart" via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    $productId = (int) $_POST['product_id'];
    $quantity  = max(1, (int) ($_POST['quantity'] ?? 1));

    if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId] += $quantity;
    } else {
        $_SESSION['cart'][$productId] = $quantity;
    }

    header('Location: /smart-transaction/index.php?added=1');
    exit;
}

$addedToCart = isset($_GET['added']);

// Fetch categories and products
try {
    $pdo = getDBConnection();

    // Get active categories
    $catStmt = $pdo->query('SELECT id, name, description FROM categories WHERE is_active = 1 ORDER BY id');
    $categories = $catStmt->fetchAll();

    // Get all available products
    $prodStmt = $pdo->query('SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.is_available = 1 ORDER BY p.category_id, p.name');
    $allProducts = $prodStmt->fetchAll();

    // Group products by category
    $productsByCategory = [];
    foreach ($allProducts as $product) {
        $productsByCategory[$product['category_id']][] = $product;
    }
} catch (PDOException $e) {
    $error = 'Unable to load menu. Please try again later.';
}

include __DIR__ . '/includes/header.php';
?>

<?php if ($addedToCart): ?>
    <div class="alert alert-success">Item added to cart! <a href="/smart-transaction/cart.php">View Cart</a></div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="page-header">
    <h1>Our Menu</h1>
    <p>Browse our selection of freshly prepared food and beverages</p>
</div>

<?php if (empty($categories)): ?>
    <div class="alert alert-info">No menu items available at the moment. Please check back later.</div>
<?php else: ?>
    <?php foreach ($categories as $category): ?>
        <?php $products = $productsByCategory[$category['id']] ?? []; ?>
        <?php if (empty($products)) continue; ?>

        <section class="mb-4">
            <h2 class="mb-2" style="color: var(--primary); font-size: 1.3rem;">
                <?php echo htmlspecialchars($category['name']); ?>
            </h2>
            <?php if ($category['description']): ?>
                <p class="text-muted mb-2"><?php echo htmlspecialchars($category['description']); ?></p>
            <?php endif; ?>

            <div class="grid grid-4">
                <?php foreach ($products as $product): ?>
                    <div class="card">
                        <div class="card-body">
                            <div style="font-size: 2rem; text-align: center; margin-bottom: 0.5rem;">
                                <?php
                                $icons = [
                                    'Coffee' => '&#9749;',
                                    'Non-Coffee' => '&#129482;',
                                    'Pastries' => '&#129360;',
                                    'Snacks' => '&#127839;',
                                    'Specials' => '&#127858;',
                                ];
                                echo $icons[$category['name']] ?? '&#127869;';
                                ?>
                            </div>
                            <h3 style="font-size: 1rem; margin-bottom: 0.25rem;"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p style="font-size: 0.8rem; color: var(--neutral-500); margin-bottom: 0.25rem;"><?php echo htmlspecialchars($category['name']); ?></p>
                            <?php if ($product['description']): ?>
                                <p style="font-size: 0.8rem; color: var(--neutral-500); margin-bottom: 0.5rem;"><?php echo htmlspecialchars($product['description']); ?></p>
                            <?php endif; ?>
                            <p style="font-size: 1.1rem; font-weight: 700; color: var(--primary); margin-bottom: 0.75rem;">
                                RM <?php echo number_format((float) $product['price'], 2); ?>
                            </p>
                            <form method="POST" action="">
                                <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
                                <div class="d-flex gap-1 align-center">
                                    <input type="number" name="quantity" value="1" min="1" max="99"
                                           class="form-input" style="width: 60px; text-align: center;">
                                    <button type="submit" class="btn btn-primary btn-sm" style="flex: 1;">
                                        Add to Cart
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
