<?php
/**
 * Admin Promotion Management Page
 * 
 * Allows admin to create, edit, activate/deactivate, and delete promotions.
 * Promotions auto-apply discounts to tagged items (Grab-style).
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin_auth.php';

startSession();
requireAdminRole();

$pageTitle = 'Promotion Management — Smart Transaction';

$pdo = getDBConnection();
$flash = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create promotion
    if (isset($_POST['create_promotion'])) {
        $title            = trim($_POST['title'] ?? '');
        $description      = trim($_POST['description'] ?? '');
        $promotion_type   = $_POST['promotion_type'] ?? 'day';
        $discount_percent = (float) ($_POST['discount_percent'] ?? 0);
        $start_date       = $_POST['start_date'] ?? '';
        $end_date         = $_POST['end_date'] ?? '';
        $is_active        = isset($_POST['is_active']) ? 1 : 0;
        $product_ids      = $_POST['product_ids'] ?? [];

        $errors = [];
        if (empty($title)) $errors[] = 'Title is required.';
        if ($discount_percent < 1 || $discount_percent > 100) $errors[] = 'Discount must be between 1 and 100.';
        if (empty($start_date)) $errors[] = 'Start date is required.';
        if (empty($end_date)) $errors[] = 'End date is required.';
        if (empty($product_ids)) $errors[] = 'Select at least one product.';

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    INSERT INTO promotions (title, description, promotion_type, discount_percent, start_date, end_date, is_active, created_by)
                    VALUES (:title, :desc, :type, :discount, :start, :end, :active, :created_by)
                ");
                $stmt->execute([
                    ':title'      => $title,
                    ':desc'       => $description,
                    ':type'       => $promotion_type,
                    ':discount'   => $discount_percent,
                    ':start'      => $start_date,
                    ':end'        => $end_date,
                    ':active'     => $is_active,
                    ':created_by' => $_SESSION['admin_id'] ?? null,
                ]);
                $promotionId = (int) $pdo->lastInsertId();

                // Insert products
                $prodStmt = $pdo->prepare("INSERT INTO promotion_products (promotion_id, product_id) VALUES (:promo_id, :prod_id)");
                foreach ($product_ids as $pid) {
                    $prodStmt->execute([':promo_id' => $promotionId, ':prod_id' => (int) $pid]);
                }

                $pdo->commit();
                $flash = ['type' => 'success', 'message' => "Promotion '$title' created! Discount will auto-apply to tagged items immediately."];
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $flash = ['type' => 'danger', 'message' => 'Error: ' . $e->getMessage()];
            }
        } else {
            $flash = ['type' => 'danger', 'message' => implode('<br>', $errors)];
        }
    }

    // Edit promotion
    if (isset($_POST['edit_promotion'])) {
        $id               = (int) ($_POST['promotion_id'] ?? 0);
        $title            = trim($_POST['title'] ?? '');
        $description      = trim($_POST['description'] ?? '');
        $promotion_type   = $_POST['promotion_type'] ?? 'day';
        $discount_percent = (float) ($_POST['discount_percent'] ?? 0);
        $start_date       = $_POST['start_date'] ?? '';
        $end_date         = $_POST['end_date'] ?? '';
        $is_active        = isset($_POST['is_active']) ? 1 : 0;
        $product_ids      = $_POST['product_ids'] ?? [];

        if ($id > 0 && !empty($title) && $discount_percent >= 1 && $discount_percent <= 100 && !empty($product_ids)) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("UPDATE promotions SET title=:title, description=:desc, promotion_type=:type, discount_percent=:discount, start_date=:start, end_date=:end, is_active=:active WHERE id=:id");
                $stmt->execute([
                    ':title'    => $title, ':desc' => $description, ':type' => $promotion_type,
                    ':discount' => $discount_percent, ':start' => $start_date, ':end' => $end_date,
                    ':active'   => $is_active, ':id' => $id,
                ]);

                // Re-insert products
                $pdo->prepare("DELETE FROM promotion_products WHERE promotion_id = :id")->execute([':id' => $id]);
                $prodStmt = $pdo->prepare("INSERT INTO promotion_products (promotion_id, product_id) VALUES (:promo_id, :prod_id)");
                foreach ($product_ids as $pid) {
                    $prodStmt->execute([':promo_id' => $id, ':prod_id' => (int) $pid]);
                }

                $pdo->commit();
                $flash = ['type' => 'success', 'message' => "Promotion '$title' updated!"];
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $flash = ['type' => 'danger', 'message' => 'Error: ' . $e->getMessage()];
            }
        } else {
            $flash = ['type' => 'danger', 'message' => 'Invalid data.'];
        }
    }

    // Toggle active
    if (isset($_POST['toggle_promo'])) {
        $id = (int) ($_POST['promotion_id'] ?? 0);
        $newStatus = (int) ($_POST['new_status'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("UPDATE promotions SET is_active = :status WHERE id = :id")->execute([':status' => $newStatus, ':id' => $id]);
            $flash = ['type' => 'success', 'message' => 'Promotion status updated!'];
        }
    }

    // Delete promotion
    if (isset($_POST['delete_promotion'])) {
        $id = (int) ($_POST['promotion_id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM promotions WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $flash = ['type' => 'success', 'message' => 'Promotion deleted.'];
        }
    }
}

// Fetch all promotions with product counts
$promotions = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               (SELECT COUNT(*) FROM promotion_products WHERE promotion_id = p.id) AS item_count,
               u.name AS created_by_name
        FROM promotions p
        LEFT JOIN users u ON p.created_by = u.id
        ORDER BY p.created_at DESC
    ");
    $stmt->execute();
    $promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $flash = ['type' => 'danger', 'message' => 'Database error: ' . $e->getMessage()];
}

// Fetch active products grouped by category
$products = [];
try {
    $stmt = $pdo->prepare("SELECT p.id, p.name, p.price, c.name AS category FROM products p JOIN categories c ON p.category_id = c.id WHERE p.is_available = 1 ORDER BY c.name, p.name");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Group products by category
$groupedProducts = [];
foreach ($products as $p) {
    $cat = $p['category'] ?? 'Uncategorized';
    if (!isset($groupedProducts[$cat])) $groupedProducts[$cat] = [];
    $groupedProducts[$cat][] = $p;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Promotion Management</h1>
    <p>Create and manage auto-applied promotions</p>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?> flash-message"><?php echo $flash['message']; ?></div>
<?php endif; ?>

<!-- Section A: Active & Upcoming Promotions -->
<h2 style="margin-bottom:1rem;">&#128293; Active & Upcoming Promotions</h2>

<?php
$activePromos = array_filter($promotions, function($p) {
    return $p['is_active'] && strtotime($p['end_date']) >= strtotime(date('Y-m-d'));
});
?>

<?php if (empty($activePromos)): ?>
    <div class="card" style="margin-bottom:1.5rem;">
        <div class="card-body text-center" style="padding:2rem;">
            <p style="font-size:2rem;margin-bottom:0.5rem;">&#128683;</p>
            <p style="color:#78716c;">No active promotions. Click 'Add Promotion' to create one.</p>
        </div>
    </div>
<?php else: ?>
    <div class="grid grid-2" style="margin-bottom:1.5rem;">
        <?php foreach ($activePromos as $p): 
            $typeLabel = $p['promotion_type'] === 'day' ? 'Promo of the Day' : 'Promo of the Week';
            $typeIcon = $p['promotion_type'] === 'day' ? '&#128293;' : '&#128197;';
            $isUpcoming = strtotime($p['start_date']) > strtotime(date('Y-m-d'));
        ?>
        <div class="card" style="border-left:4px solid #01696f;">
            <div class="card-body">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:0.5rem;">
                    <span style="font-size:0.8rem;color:#01696f;font-weight:600;"><?php echo $typeIcon; ?> <?php echo $typeLabel; ?></span>
                    <?php if ($isUpcoming): ?>
                        <span class="badge" style="background:#dbeafe;color:#1e40af;">Upcoming</span>
                    <?php else: ?>
                        <span class="badge" style="background:#dcfce7;color:#166534;">Active</span>
                    <?php endif; ?>
                </div>
                <h3 style="margin:0 0 0.25rem 0;"><?php echo htmlspecialchars($p['title']); ?></h3>
                <p style="font-size:1.1rem;font-weight:bold;color:#01696f;margin:0 0 0.5rem 0;"><?php echo (int)$p['discount_percent']; ?>% OFF</p>
                <p style="font-size:0.85rem;color:#78716c;margin:0 0 0.5rem 0;">
                    Valid: <?php echo date('d M Y', strtotime($p['start_date'])); ?> 
                    <?php if ($p['start_date'] !== $p['end_date']): ?>
                        &ndash; <?php echo date('d M Y', strtotime($p['end_date'])); ?>
                    <?php endif; ?>
                </p>
                <p style="font-size:0.85rem;color:#78716c;margin:0 0 0.75rem 0;">
                    Items: <?php echo (int)$p['item_count']; ?> product(s)
                </p>
                <div style="display:flex;gap:0.5rem;">
                    <button class="btn btn-sm btn-outline" onclick="editPromotion(<?php echo $p['id']; ?>)">Edit</button>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="promotion_id" value="<?php echo $p['id']; ?>">
                        <input type="hidden" name="new_status" value="0">
                        <button type="submit" name="toggle_promo" class="btn btn-sm btn-warning">Deactivate</button>
                    </form>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete \'<?php echo htmlspecialchars($p['title']); ?>\'? Items will return to original prices immediately.');">
                        <input type="hidden" name="promotion_id" value="<?php echo $p['id']; ?>">
                        <button type="submit" name="delete_promotion" class="btn btn-sm btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Section B: All Promotions Table + Add Button -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
    <h2 style="margin:0;">All Promotions</h2>
    <button class="btn btn-primary" onclick="openAddModal()">+ Add Promotion</button>
</div>

<div class="card">
    <div class="card-body" style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Discount</th>
                    <th>Items</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($promotions as $p): 
                    $today = date('Y-m-d');
                    if (!$p['is_active']) { $status = 'Off'; $statusClass = 'badge-deactivated'; }
                    elseif (strtotime($p['end_date']) < strtotime($today)) { $status = 'Ended'; $statusClass = 'badge-expired'; }
                    elseif (strtotime($p['start_date']) > strtotime($today)) { $status = 'Upcoming'; $statusClass = 'badge-upcoming'; }
                    else { $status = 'Active'; $statusClass = 'badge-active'; }
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($p['title']); ?></strong></td>
                    <td><?php echo $p['promotion_type'] === 'day' ? 'Day' : 'Week'; ?></td>
                    <td><strong style="color:#01696f;"><?php echo (int)$p['discount_percent']; ?>%</strong></td>
                    <td><?php echo (int)$p['item_count']; ?></td>
                    <td><?php echo date('d M Y', strtotime($p['start_date'])); ?></td>
                    <td><?php echo date('d M Y', strtotime($p['end_date'])); ?></td>
                    <td><span class="badge <?php echo $statusClass; ?>"><?php echo $status; ?></span></td>
                    <td>
                        <div style="display:flex;gap:0.25rem;flex-wrap:wrap;">
                            <button class="btn btn-sm btn-outline" onclick="editPromotion(<?php echo $p['id']; ?>)">Edit</button>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="promotion_id" value="<?php echo $p['id']; ?>">
                                <input type="hidden" name="new_status" value="<?php echo $p['is_active'] ? 0 : 1; ?>">
                                <button type="submit" name="toggle_promo" class="btn btn-sm <?php echo $p['is_active'] ? 'btn-warning' : 'btn-success'; ?>">
                                    <?php echo $p['is_active'] ? 'Off' : 'On'; ?>
                                </button>
                            </form>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete \'<?php echo htmlspecialchars($p['title']); ?>\'?');">
                                <input type="hidden" name="promotion_id" value="<?php echo $p['id']; ?>">
                                <button type="submit" name="delete_promotion" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($promotions)): ?>
                <tr><td colspan="8" class="text-center" style="padding:2rem;color:#78716c;">No promotions yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="promoModal" class="modal" style="display:none;">
    <div class="modal-overlay" onclick="closeModal()"></div>
    <div class="modal-content" style="max-width:650px;max-height:90vh;overflow-y:auto;">
        <div class="modal-header">
            <h3 id="modalTitle">Add Promotion</h3>
            <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" action="" id="promoForm">
            <input type="hidden" name="promotion_id" id="promoId" value="0">
            <input type="hidden" name="edit_promotion" id="editModeField" value="0">
            
            <div class="form-group">
                <label class="form-label">Title *</label>
                <input type="text" name="title" id="promoTitle" class="form-input" required>
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" id="promoDesc" class="form-input" rows="2"></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Type *</label>
                <div style="display:flex;gap:1rem;">
                    <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                        <input type="radio" name="promotion_type" value="day" checked onchange="onTypeChange()">
                        <span>&#128293; Promotion of the Day</span>
                    </label>
                    <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                        <input type="radio" name="promotion_type" value="week" onchange="onTypeChange()">
                        <span>&#128197; Promotion of the Week</span>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Discount % * (1-100)</label>
                <input type="number" name="discount_percent" id="promoDiscount" class="form-input" min="1" max="100" required>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Start Date *</label>
                    <input type="date" name="start_date" id="promoStart" class="form-input" min="<?php echo date('Y-m-d'); ?>" required onchange="onDateChange()">
                </div>
                <div class="form-group">
                    <label class="form-label">End Date *</label>
                    <input type="date" name="end_date" id="promoEnd" class="form-input" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Select Products *</label>
                <p style="font-size:0.8rem;color:#78716c;margin-bottom:0.5rem;">Check all products that this promotion applies to.</p>
                <div id="productCheckboxes" style="max-height:300px;overflow-y:auto;border:1px solid #e7e5e4;border-radius:8px;padding:0.75rem;">
                    <?php foreach ($groupedProducts as $category => $catProducts): ?>
                        <div style="margin-bottom:0.75rem;">
                            <label style="display:flex;align-items:center;gap:0.5rem;font-weight:600;font-size:0.9rem;color:#01696f;margin-bottom:0.25rem;">
                                <input type="checkbox" class="category-checkbox" data-category="<?php echo htmlspecialchars($category); ?>" onchange="toggleCategory('<?php echo htmlspecialchars($category); ?>')">
                                Select All in <?php echo htmlspecialchars($category); ?>
                            </label>
                            <?php foreach ($catProducts as $prod): ?>
                                <label style="display:flex;align-items:center;gap:0.5rem;padding:0.2rem 0 0.2rem 1.5rem;font-size:0.85rem;cursor:pointer;">
                                    <input type="checkbox" name="product_ids[]" value="<?php echo $prod['id']; ?>" class="product-checkbox category-<?php echo htmlspecialchars($category); ?>">
                                    <?php echo htmlspecialchars($prod['name']); ?> &mdash; RM <?php echo number_format($prod['price'], 2); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label style="display:flex;align-items:center;gap:0.5rem;">
                    <input type="checkbox" name="is_active" id="promoActive" value="1" checked>
                    <span>Active</span>
                </label>
            </div>

            <button type="submit" id="submitBtn" class="btn btn-primary">Create Promotion</button>
        </form>
    </div>
</div>

<style>
.badge-active { background:#dcfce7; color:#166534; }
.badge-deactivated { background:#fed7aa; color:#9a3412; }
.badge-expired { background:#fee2e2; color:#991b1b; }
.badge-upcoming { background:#dbeafe; color:#1e40af; }
.modal { position:fixed; top:0; left:0; width:100%; height:100%; z-index:1000; display:flex; align-items:center; justify-content:center; }
.modal-overlay { position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.4); }
.modal-content { position:relative; background:#fff; border-radius:12px; padding:1.5rem; width:90%; max-width:650px; box-shadow:0 10px 25px rgba(0,0,0,0.15); }
.modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; }
.modal-header h3 { margin:0; }
.modal-close { background:none; border:none; font-size:1.5rem; cursor:pointer; color:#78716c; }
.flash-message { animation:flashFade 4s forwards; }
@keyframes flashFade { 0%,70%{opacity:1} 100%{opacity:0;display:none} }
</style>

<script>
function onTypeChange() {
    var type = document.querySelector('input[name="promotion_type"]:checked').value;
    var start = document.getElementById('promoStart').value;
    if (start) {
        var startDate = new Date(start);
        if (type === 'day') {
            var end = new Date(startDate);
            document.getElementById('promoEnd').value = end.toISOString().split('T')[0];
        } else {
            var end = new Date(startDate);
            end.setDate(end.getDate() + 6);
            document.getElementById('promoEnd').value = end.toISOString().split('T')[0];
        }
    }
}

function onDateChange() {
    onTypeChange();
}

function toggleCategory(category) {
    var checkbox = document.querySelector(`.category-checkbox[data-category="${category}"]`);
    var products = document.querySelectorAll(`.category-${CSS.escape(category)}`);
    products.forEach(function(p) { p.checked = checkbox.checked; });
}

function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add Promotion';
    document.getElementById('promoId').value = '0';
    document.getElementById('editModeField').name = 'create_promotion';
    document.getElementById('editModeField').value = '1';
    document.getElementById('promoForm').reset();
    document.getElementById('promoActive').checked = true;
    document.getElementById('submitBtn').textContent = 'Create Promotion';
    document.getElementById('promoModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('promoModal').style.display = 'none';
}

function editPromotion(id) {
    // Fetch promotion data via AJAX
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/smart-transaction/admin/get_promotion.php?id=' + id, true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var data = JSON.parse(xhr.responseText);
                document.getElementById('modalTitle').textContent = 'Edit: ' + data.title;
                document.getElementById('promoId').value = data.id;
                document.getElementById('editModeField').name = 'edit_promotion';
                document.getElementById('editModeField').value = '1';
                document.getElementById('promoTitle').value = data.title;
                document.getElementById('promoDesc').value = data.description || '';
                document.querySelector(`input[name="promotion_type"][value="${data.promotion_type}"]`).checked = true;
                document.getElementById('promoDiscount').value = data.discount_percent;
                document.getElementById('promoStart').value = data.start_date;
                document.getElementById('promoEnd').value = data.end_date;
                document.getElementById('promoActive').checked = data.is_active == 1;
                document.getElementById('submitBtn').textContent = 'Update Promotion';

                // Uncheck all products first
                document.querySelectorAll('.product-checkbox').forEach(function(cb) { cb.checked = false; });
                // Check selected products
                if (data.product_ids) {
                    data.product_ids.forEach(function(pid) {
                        var cb = document.querySelector(`.product-checkbox[value="${pid}"]`);
                        if (cb) cb.checked = true;
                    });
                }

                document.getElementById('promoModal').style.display = 'flex';
            } catch(e) {
                alert('Failed to load promotion data.');
            }
        } else {
            alert('Failed to load promotion data.');
        }
    };
    xhr.send();
}

// Auto-dismiss flash
document.addEventListener('DOMContentLoaded', function() {
    var flash = document.querySelector('.flash-message');
    if (flash) {
        setTimeout(function() { flash.style.display = 'none'; }, 4000);
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
