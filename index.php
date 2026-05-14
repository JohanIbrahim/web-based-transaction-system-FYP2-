<?php
/**
 * Customer Menu Browsing Page
 * 
 * Displays all available products grouped by category with category filter tabs,
 * search bar, and add-to-cart functionality. Cart count shown in navbar.
 */

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';

startSession();

$pageTitle = 'Menu - Smart Transaction System';

// Initialize cart in session if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Calculate cart item count
$cartCount = 0;
foreach (($_SESSION['cart'] ?? []) as $qty) {
    $cartCount += (int) $qty;
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

    header('Location: /smart-transaction/index.php?added=' . $productId);
    exit;
}

$addedProductId = isset($_GET['added']) ? (int) $_GET['added'] : 0;

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

<?php if ($addedProductId > 0): ?>
    <div class="alert alert-success">
        Item added to cart! <a href="/smart-transaction/cart.php" class="btn btn-sm btn-primary ml-1">View Cart</a>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="page-header">
    <h1>Our Menu</h1>
    <p>Browse our selection of freshly prepared food and beverages</p>
</div>

<!-- Search Bar -->
<div class="mb-3">
    <input type="text" id="menuSearch" class="form-input" placeholder="Search menu items..." style="max-width: 400px;">
</div>

<!-- Category Filter Tabs -->
<div class="category-tabs mb-3" id="categoryTabs">
    <button class="category-tab active" data-category="all">All</button>
    <?php foreach ($categories as $cat): ?>
        <button class="category-tab" data-category="<?php echo (int) $cat['id']; ?>">
            <?php echo htmlspecialchars($cat['name']); ?>
        </button>
    <?php endforeach; ?>
</div>

<?php if (empty($categories)): ?>
    <div class="alert alert-info">No menu items available at the moment. Please check back later.</div>
<?php else: ?>
    <?php foreach ($categories as $category): ?>
        <?php $products = $productsByCategory[$category['id']] ?? []; ?>
        <?php if (empty($products)) continue; ?>

        <section class="mb-4 category-section" data-category="<?php echo (int) $category['id']; ?>">
            <h2 class="mb-2" style="color: var(--primary); font-size: 1.3rem;">
                <?php echo htmlspecialchars($category['name']); ?>
            </h2>
            <?php if ($category['description']): ?>
                <p class="text-muted mb-2"><?php echo htmlspecialchars($category['description']); ?></p>
            <?php endif; ?>

            <div class="grid grid-3 product-grid">
                <?php foreach ($products as $product): ?>
                    <div class="card product-card" data-name="<?php echo htmlspecialchars(strtolower($product['name'])); ?>">
                        <div class="card-body">
                            <div class="product-image-placeholder">
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
                            <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p class="product-category-label"><?php echo htmlspecialchars($category['name']); ?></p>
                            <?php if ($product['description']): ?>
                                <p class="product-description"><?php echo htmlspecialchars($product['description']); ?></p>
                            <?php endif; ?>
                            <p class="product-price">RM <?php echo number_format((float) $product['price'], 2); ?></p>
                            <form method="POST" action="" class="add-to-cart-form">
                                <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
                                <div class="d-flex gap-1 align-center">
                                    <input type="number" name="quantity" value="1" min="1" max="99"
                                           class="form-input qty-input" style="width: 60px; text-align: center;">
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

<script>
// Category filter tabs
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.category-tab');
    const sections = document.querySelectorAll('.category-section');
    const searchInput = document.getElementById('menuSearch');
    const productCards = document.querySelectorAll('.product-card');

    // Category filter
    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            tabs.forEach(function(t) { t.classList.remove('active'); });
            this.classList.add('active');

            const category = this.getAttribute('data-category');

            sections.forEach(function(section) {
                if (category === 'all') {
                    section.style.display = 'block';
                } else {
                    section.style.display = section.getAttribute('data-category') === category ? 'block' : 'none';
                }
            });
        });
    });

    // Search filter
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const query = this.value.toLowerCase().trim();

            productCards.forEach(function(card) {
                const name = card.getAttribute('data-name');
                if (name.indexOf(query) !== -1) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });

            // Show/hide category sections based on visible products
            sections.forEach(function(section) {
                const visibleCards = section.querySelectorAll('.product-card[style*="display: none"]');
                const totalCards = section.querySelectorAll('.product-card');
                if (visibleCards.length === totalCards.length && totalCards.length > 0) {
                    section.style.display = 'none';
                } else {
                    section.style.display = '';
                }
            });
        });
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
