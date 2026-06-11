<?php
/**
 * Admin Product Management Page
 * 
 * Lists all products with add/edit/delete/toggle availability functionality.
 * Supports product image upload.
 * Uses PDO prepared statements for all queries.
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin_auth.php';

startSession();
requireAdminRole();

$pageTitle = 'Products — Smart Transaction';

$message = '';
$messageType = '';

// Image upload directory
$uploadDir = __DIR__ . '/../uploads/';
$uploadUrl = '/smart-transaction/uploads/';

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
            // Get image to delete file
            $stmt = $pdo->prepare('SELECT image_url FROM products WHERE id = :id');
            $stmt->execute([':id' => $productId]);
            $product = $stmt->fetch();
            if ($product && $product['image_url']) {
                $filePath = $uploadDir . basename($product['image_url']);
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
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
        $removeImage = isset($_POST['remove_image']) ? true : false;

        if (empty($name) || $categoryId <= 0 || $price <= 0) {
            $message = 'Name, category, and price are required.';
            $messageType = 'danger';
        } else {
            try {
                $pdo = getDBConnection();

                // Handle image upload
                $imageUrl = null;
                if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['product_image'];
                    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    $maxSize = 2 * 1024 * 1024; // 2MB

                    if (!in_array($file['type'], $allowedTypes)) {
                        throw new Exception('Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.');
                    }
                    if ($file['size'] > $maxSize) {
                        throw new Exception('File too large. Maximum size is 2MB.');
                    }

                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = uniqid('product_') . '.' . $ext;
                    $destPath = $uploadDir . $filename;

                    if (move_uploaded_file($file['tmp_name'], $destPath)) {
                        $imageUrl = $filename;
                    } else {
                        throw new Exception('Failed to upload image.');
                    }
                }

                if ($productId > 0) {
                    // Update existing product
                    if ($imageUrl) {
                        // Delete old image if exists
                        $stmt = $pdo->prepare('SELECT image_url FROM products WHERE id = :id');
                        $stmt->execute([':id' => $productId]);
                        $old = $stmt->fetch();
                        if ($old && $old['image_url']) {
                            $oldPath = $uploadDir . basename($old['image_url']);
                            if (file_exists($oldPath)) {
                                unlink($oldPath);
                            }
                        }
                        $stmt = $pdo->prepare('UPDATE products SET name = :name, category_id = :cat, price = :price, description = :desc, image_url = :image WHERE id = :id');
                        $stmt->execute([':name' => $name, ':cat' => $categoryId, ':price' => $price, ':desc' => $description, ':image' => $imageUrl, ':id' => $productId]);
                    } elseif ($removeImage) {
                        // Remove image
                        $stmt = $pdo->prepare('SELECT image_url FROM products WHERE id = :id');
                        $stmt->execute([':id' => $productId]);
                        $old = $stmt->fetch();
                        if ($old && $old['image_url']) {
                            $oldPath = $uploadDir . basename($old['image_url']);
                            if (file_exists($oldPath)) {
                                unlink($oldPath);
                            }
                        }
                        $stmt = $pdo->prepare('UPDATE products SET name = :name, category_id = :cat, price = :price, description = :desc, image_url = NULL WHERE id = :id');
                        $stmt->execute([':name' => $name, ':cat' => $categoryId, ':price' => $price, ':desc' => $description, ':id' => $productId]);
                    } else {
                        $stmt = $pdo->prepare('UPDATE products SET name = :name, category_id = :cat, price = :price, description = :desc WHERE id = :id');
                        $stmt->execute([':name' => $name, ':cat' => $categoryId, ':price' => $price, ':desc' => $description, ':id' => $productId]);
                    }
                    $message = 'Product updated.';
                } else {
                    // Insert new product
                    $stmt = $pdo->prepare('INSERT INTO products (name, category_id, price, description, image_url) VALUES (:name, :cat, :price, :desc, :image)');
                    $stmt->execute([':name' => $name, ':cat' => $categoryId, ':price' => $price, ':desc' => $description, ':image' => $imageUrl]);
                    $message = 'Product added.';
                }
                $messageType = 'success';
            } catch (Exception $e) {
                $message = $e->getMessage();
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
    <button class="btn btn-primary" onclick="document.getElementById('productForm').style.display='block'; document.getElementById('formTitle').textContent='Add Product'; document.getElementById('saveProduct').value=''; document.getElementById('name').value=''; document.getElementById('category_id').value=''; document.getElementById('price').value=''; document.getElementById('description').value=''; document.getElementById('currentImage').style.display='none'; document.getElementById('removeImageWrap').style.display='none';">+ Add Product</button>
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
        <form method="POST" action="" enctype="multipart/form-data">
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
                <div class="form-group">
                    <label for="product_image" class="form-label">Product Image</label>
                    <input type="file" id="product_image" name="product_image" class="form-input" accept="image/jpeg,image/png,image/gif,image/webp">
                    <small style="color: var(--color-text-muted);">Max 2MB. Allowed: JPG, PNG, GIF, WebP</small>
                    <div id="currentImage" style="display: none; margin-top: 0.5rem;">
                        <img id="currentImagePreview" src="" alt="Current image" style="max-width: 100px; max-height: 100px; border-radius: 8px; border: 1px solid var(--color-border);">
                        <label style="display: block; margin-top: 0.25rem; font-size: 0.85rem;">
                            <input type="checkbox" name="remove_image" value="1"> Remove current image
                        </label>
                    </div>
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
                        <th>Image</th>
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
                            <td>
                                <?php if ($product['image_url']): ?>
                                    <img src="/smart-transaction/uploads/<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 6px;">
                                <?php else: ?>
                                    <span style="font-size: 1.5rem; opacity: 0.4;">&#127869;</span>
                                <?php endif; ?>
                            </td>
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
                                <button class="btn btn-sm btn-outline" onclick="editProduct(<?php echo (int) $product['id']; ?>, '<?php echo htmlspecialchars(addslashes($product['name'])); ?>', <?php echo (int) $product['category_id']; ?>, <?php echo (float) $product['price']; ?>, '<?php echo htmlspecialchars(addslashes($product['description'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes($product['image_url'] ?? '')); ?>')">Edit</button>
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
function editProduct(id, name, catId, price, desc, imageUrl) {
    document.getElementById('productForm').style.display = 'block';
    document.getElementById('formTitle').textContent = 'Edit Product';
    document.getElementById('saveProduct').value = id;
    document.getElementById('name').value = name;
    document.getElementById('category_id').value = catId;
    document.getElementById('price').value = price;
    document.getElementById('description').value = desc;
    
    var currentImage = document.getElementById('currentImage');
    var preview = document.getElementById('currentImagePreview');
    if (imageUrl) {
        preview.src = '/smart-transaction/uploads/' + imageUrl;
        currentImage.style.display = 'block';
    } else {
        currentImage.style.display = 'none';
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
