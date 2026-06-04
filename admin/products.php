<?php
/**
 * Admin Product Management Page
 * 
 * Lists all products with add/edit/delete/toggle availability functionality.
 * Uses PDO prepared statements for all queries.
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin_auth.php';

startSession();
requireAdminRole();

$pageTitle = 'Products - Smart Transaction System';

$message = '';
$messageType = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Toggle availability
    if (isset($_POST['toggle_available'])) {
        $productId = (int) $_POST['product_id'];
        $current = (int) $_POST['current'];
        $new = $current ? 0 : 1;
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare('UPDATE products SET is_available = :available WHERE id = :id');
            $stmt->execute([':available' => $new, ':id' => $productId]);
            $message = 'Product availability updated.';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Update failed.';
            $messageType = 'danger';
        }
    }

    // Delete product
    if (isset($_POST['delete_product'])) {
        $productId = (int) $_POST['product_id'];
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
            $stmt->execute([':id' => $productId]);
            $message = 'Product deleted.';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Delete failed.';
            $messageType = 'danger';
        }
    }

    // Add/Edit product
    if (isset($_POST['save_product'])) {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $price = (float) ($_POST['price'] ?? 0);
        $description = trim($_POST['description'] ?? '');

        if (empty($name) || $categoryId <= 0 || $price <= 0) {
            $message = 'Name, category, and price are required.';
            $messageType = 'danger';
        } else {
            try {
                $pdo = getDBConnection();
                if ($productId > 0) {
                    $stmt = $pdo->prepare('UPDATE products SET name = :name, category_id = :cat, price = :price, description = :desc WHERE id = :id');
                    $stmt->execute([':name' => $name, ':cat' => $categoryId, ':price' => $price, ':desc' => $description, ':id' => $productId]);
                    $message = 'Product updated.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO products (name, category_id, price, description) VALUES (:name, :cat, :price, :desc)');
                    $stmt->execute([':name' => $name, ':cat' => $categoryId, ':price' => $price, ':desc' => $description]);
                    $message = 'Product added.';
                }
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Save failed.';
                $messageType = 'danger';
            }
        }
    }
}

// Fetch products and categories
try {
    $pdo = getDBConnection();
    $products = $pdo->query('SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON p.category_id = c.id ORDER BY p.category_id, p.name')->fetchAll();
    $categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
} catch (PDOException $e) {
    $error = 'Unable to load data.';
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header d-flex justify-between align-center">
    <div>
        <h1>Products</h1>
        <p>Manage your menu items</p>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('productForm').style.display='block'; document.getElementById('formTitle').textContent='Add Product'; document.getElementById('saveProduct').value=''; document.getElementById('name').value=''; document.getElementById('category_id').value=''; document.getElementById('price').value=''; document.getElementById('description').value='';">+ Add Product</button>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- Add/Edit Form -->
<div class="card mb-3" id="productForm" style="display: none;">
    <div class="card-header" id="formTitle">Add Product</div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="product_id" id="saveProduct" value="">
            <div class="grid grid-2">
                <div class="form-group">
                    <label for="name" class="form-label">Product Name *</label>
                    <input type="text" id="name" name="name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label for="category_id" class="form-label">Category *</label>
                    <select id="category_id" name="category_id" class="form-select" required>
                        <option value="">-- Select --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo (int) $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="price" class="form-label">Price (RM) *</label>
                    <input type="number" id="price" name="price" class="form-input" step="0.01" min="0.01" required>
                </div>
                <div class="form-group">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" class="form-input" rows="2"></textarea>
                </div>
            </div>
            <div class="d-flex gap-1 mt-2">
                <button type="submit" name="save_product" value="1" class="btn btn-primary">Save</button>
                <button type="button" class="btn btn-secondary" onclick="this.closest('#productForm').style.display='none';">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Products Table -->
<div class="card">
    <div class="card-header">All Products</div>
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Available</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?php echo (int) $product['id']; ?></td>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                            <td>RM <?php echo number_format((float) $product['price'], 2); ?></td>
                            <td>
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
                                    <input type="hidden" name="current" value="<?php echo (int) $product['is_available']; ?>">
                                    <button type="submit" name="toggle_available" value="1" class="btn btn-sm <?php echo $product['is_available'] ? 'btn-success' : 'btn-secondary'; ?>">
                                        <?php echo $product['is_available'] ? 'Yes' : 'No'; ?>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline" onclick="editProduct(<?php echo (int) $product['id']; ?>, '<?php echo htmlspecialchars(addslashes($product['name'])); ?>', <?php echo (int) $product['category_id']; ?>, <?php echo (float) $product['price']; ?>, '<?php echo htmlspecialchars(addslashes($product['description'] ?? '')); ?>')">Edit</button>
                                <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Delete this product?');">
                                    <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
                                    <button type="submit" name="delete_product" value="1" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function editProduct(id, name, catId, price, desc) {
    document.getElementById('productForm').style.display = 'block';
    document.getElementById('formTitle').textContent = 'Edit Product';
    document.getElementById('saveProduct').value = id;
    document.getElementById('name').value = name;
    document.getElementById('category_id').value = catId;
    document.getElementById('price').value = price;
    document.getElementById('description').value = desc;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
