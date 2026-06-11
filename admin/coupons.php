<?php
/**
 * Admin Coupon Management Page
 * 
 * Allows admin to view, create, edit, activate/deactivate, and delete coupons.
 * Two tabs: "All Coupons" and "Create Coupon"
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../includes/coupon_helper.php';

startSession();
requireAdminRole();

$pageTitle = 'Coupon Management — Smart Transaction';

$pdo = getDBConnection();
$flash = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create coupon
    if (isset($_POST['create_coupon'])) {
        $customer_id      = (int) ($_POST['customer_id'] ?? 0);
        $discount_percent = (float) ($_POST['discount_percent'] ?? 0);
        $tier             = (int) ($_POST['tier'] ?? 6);
        $expires_at       = $_POST['expires_at'] ?? date('Y-m-d', strtotime('+30 days'));
        $auto_generate    = isset($_POST['auto_generate']);
        $manual_code      = strtoupper(trim($_POST['manual_code'] ?? ''));

        // Validate
        $errors = [];
        if ($customer_id <= 0) $errors[] = 'Please select a customer.';
        if ($discount_percent < 1 || $discount_percent > 100) $errors[] = 'Discount must be between 1 and 100.';
        if (empty($expires_at)) $errors[] = 'Expiry date is required.';

        if (!$auto_generate && !empty($manual_code)) {
            if (strlen($manual_code) < 5 || strlen($manual_code) > 20) {
                $errors[] = 'Coupon code must be 5-20 characters.';
            } elseif (!preg_match('/^[A-Z0-9]+$/', $manual_code)) {
                $errors[] = 'Coupon code must contain only uppercase letters and numbers.';
            } else {
                // Check uniqueness
                $check = $pdo->prepare("SELECT id FROM coupons WHERE coupon_code = :code");
                $check->execute([':code' => $manual_code]);
                if ($check->fetch()) {
                    $errors[] = 'Coupon code already exists.';
                }
            }
        }

        if (empty($errors)) {
            // Generate or use manual code
            $tierNames = [1 => 'Returning Customer', 2 => 'Loyal Member', 3 => 'VIP Member', 4 => 'Elite Member', 5 => 'Star Member', 6 => 'Custom'];
            $prefixes = [1 => 'RETURN10', 2 => 'LOYAL15', 3 => 'VIP20', 4 => 'ELITE25', 5 => 'STAR30', 6 => 'CUSTOM'];

            if ($auto_generate) {
                do {
                    $code = generateCouponCode($prefixes[$tier] ?? 'CUSTOM', $customer_id);
                    $check = $pdo->prepare("SELECT id FROM coupons WHERE coupon_code = :code");
                    $check->execute([':code' => $code]);
                } while ($check->fetch());
            } else {
                $code = $manual_code;
            }

            $stmt = $pdo->prepare("
                INSERT INTO coupons (customer_id, coupon_code, discount_percent, tier, tier_name, is_used, is_active, issued_at, expires_at)
                VALUES (:customer_id, :code, :discount, :tier, :tier_name, 0, 1, NOW(), :expires)
            ");
            $stmt->execute([
                ':customer_id' => $customer_id,
                ':code'        => $code,
                ':discount'    => $discount_percent,
                ':tier'        => $tier,
                ':tier_name'   => $tierNames[$tier] ?? 'Custom',
                ':expires'     => $expires_at,
            ]);

            $flash = ['type' => 'success', 'message' => "Coupon '$code' created!"];
        } else {
            $flash = ['type' => 'danger', 'message' => implode('<br>', $errors)];
        }
    }

    // Edit coupon
    if (isset($_POST['edit_coupon'])) {
        $id               = (int) ($_POST['coupon_id'] ?? 0);
        $discount_percent = (float) ($_POST['discount_percent'] ?? 0);
        $expires_at       = $_POST['expires_at'] ?? '';
        $is_active        = isset($_POST['is_active']) ? 1 : 0;

        if ($id > 0 && $discount_percent >= 1 && $discount_percent <= 100 && !empty($expires_at)) {
            $stmt = $pdo->prepare("UPDATE coupons SET discount_percent = :discount, expires_at = :expires, is_active = :active WHERE id = :id");
            $stmt->execute([':discount' => $discount_percent, ':expires' => $expires_at, ':active' => $is_active, ':id' => $id]);
            $flash = ['type' => 'success', 'message' => 'Coupon updated!'];
        } else {
            $flash = ['type' => 'danger', 'message' => 'Invalid data.'];
        }
    }

    // Toggle active status
    if (isset($_POST['toggle_active'])) {
        $id = (int) ($_POST['coupon_id'] ?? 0);
        $newStatus = (int) ($_POST['new_status'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE coupons SET is_active = :status WHERE id = :id");
            $stmt->execute([':status' => $newStatus, ':id' => $id]);
            $flash = ['type' => 'success', 'message' => 'Coupon status updated!'];
        }
    }

    // Delete coupon
    if (isset($_POST['delete_coupon'])) {
        $id = (int) ($_POST['coupon_id'] ?? 0);
        if ($id > 0) {
            $check = $pdo->prepare("SELECT is_used FROM coupons WHERE id = :id");
            $check->execute([':id' => $id]);
            $coupon = $check->fetch();
            if ($coupon && $coupon['is_used']) {
                $flash = ['type' => 'danger', 'message' => 'Cannot delete a coupon already used.'];
            } else {
                $stmt = $pdo->prepare("DELETE FROM coupons WHERE id = :id");
                $stmt->execute([':id' => $id]);
                $flash = ['type' => 'success', 'message' => 'Coupon deleted.'];
            }
        }
    }
}

// Fetch all coupons with customer info
$coupons = [];
try {
    $stmt = $pdo->prepare("
        SELECT c.*, u.name AS customer_name, u.email AS customer_email
        FROM coupons c
        LEFT JOIN users u ON c.customer_id = u.id
        ORDER BY c.issued_at DESC
    ");
    $stmt->execute();
    $coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $flash = ['type' => 'danger', 'message' => 'Database error: ' . $e->getMessage()];
}

// Fetch customers for dropdown
$customers = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE role = 'customer' ORDER BY name");
    $stmt->execute();
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Stats
$totalIssued = count($coupons);
$active = 0;
$used = 0;
$expired = 0;
foreach ($coupons as $c) {
    if ($c['is_used']) $used++;
    elseif (!$c['is_active']) $active++; // deactivated
    elseif (strtotime($c['expires_at']) < time()) $expired++;
    else $active++;
}
$active = $totalIssued - $used - $expired - (count(array_filter($coupons, fn($c) => !$c['is_active'] && !$c['is_used'] && strtotime($c['expires_at']) >= time())));

// Recalculate properly
$activeCount = 0;
$deactivatedCount = 0;
$usedCount = 0;
$expiredCount = 0;
foreach ($coupons as $c) {
    if ($c['is_used']) $usedCount++;
    elseif (!$c['is_active']) $deactivatedCount++;
    elseif (strtotime($c['expires_at']) < time()) $expiredCount++;
    else $activeCount++;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Coupon Management</h1>
    <p>Create, edit, and manage customer discount coupons</p>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?> flash-message"><?php echo $flash['message']; ?></div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="grid grid-4" style="margin-bottom: 1.5rem;">
    <div class="card"><div class="card-body text-center"><h3 style="color:#01696f;"><?php echo $totalIssued; ?></h3><p style="font-size:0.85rem;color:#78716c;">Total Issued</p></div></div>
    <div class="card"><div class="card-body text-center"><h3 style="color:#16a34a;"><?php echo $activeCount; ?></h3><p style="font-size:0.85rem;color:#78716c;">Active</p></div></div>
    <div class="card"><div class="card-body text-center"><h3 style="color:#78716c;"><?php echo $usedCount; ?></h3><p style="font-size:0.85rem;color:#78716c;">Used</p></div></div>
    <div class="card"><div class="card-body text-center"><h3 style="color:#dc2626;"><?php echo $expiredCount; ?></h3><p style="font-size:0.85rem;color:#78716c;">Expired</p></div></div>
</div>

<!-- Tabs -->
<div class="tabs" style="margin-bottom: 1.5rem;">
    <button class="tab-btn active" onclick="switchTab('all')">All Coupons</button>
    <button class="tab-btn" onclick="switchTab('create')">Create Coupon</button>
</div>

<!-- Tab 1: All Coupons -->
<div id="tab-all" class="tab-content">
    <div class="card">
        <div class="card-header">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;">
                <span>All Coupons (<?php echo $totalIssued; ?>)</span>
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                    <select id="filterStatus" onchange="filterCoupons()" style="padding:0.35rem 0.5rem;border:1px solid #d6d3d1;border-radius:6px;font-size:0.85rem;">
                        <option value="all">All</option>
                        <option value="active">Active</option>
                        <option value="used">Used</option>
                        <option value="expired">Expired</option>
                        <option value="deactivated">Deactivated</option>
                    </select>
                    <input type="text" id="searchCoupon" placeholder="Search by code or customer..." onkeyup="filterCoupons()" style="padding:0.35rem 0.5rem;border:1px solid #d6d3d1;border-radius:6px;font-size:0.85rem;">
                </div>
            </div>
        </div>
        <div class="card-body" style="overflow-x:auto;">
            <table class="table" id="couponTable">
                <thead>
                    <tr>
                        <th>Coupon Code</th>
                        <th>Customer</th>
                        <th>Tier / Discount</th>
                        <th>Status</th>
                        <th>Issued</th>
                        <th>Expires</th>
                        <th>Used In</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($coupons as $c): 
                        $status = '';
                        $statusClass = '';
                        if ($c['is_used']) { $status = 'Used'; $statusClass = 'badge-used'; }
                        elseif (!$c['is_active']) { $status = 'Deactivated'; $statusClass = 'badge-deactivated'; }
                        elseif (strtotime($c['expires_at']) < time()) { $status = 'Expired'; $statusClass = 'badge-expired'; }
                        else { $status = 'Active'; $statusClass = 'badge-active'; }
                    ?>
                    <tr class="coupon-row" data-status="<?php echo $status; ?>">
                        <td><strong style="font-family:monospace;font-size:0.9rem;"><?php echo htmlspecialchars($c['coupon_code']); ?></strong></td>
                        <td>
                            <?php echo htmlspecialchars($c['customer_name'] ?? 'N/A'); ?>
                            <br><small style="color:#78716c;"><?php echo htmlspecialchars($c['customer_email'] ?? ''); ?></small>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($c['tier_name'] ?? 'Custom'); ?>
                            <br><strong style="color:#01696f;"><?php echo (int)$c['discount_percent']; ?>%</strong>
                        </td>
                        <td><span class="badge <?php echo $statusClass; ?>"><?php echo $status; ?></span></td>
                        <td><?php echo date('d M Y', strtotime($c['issued_at'])); ?></td>
                        <td><?php echo date('d M Y', strtotime($c['expires_at'])); ?></td>
                        <td>
                            <?php if ($c['used_in_order_id']): ?>
                                <a href="/smart-transaction/admin/orders.php?order_id=<?php echo $c['used_in_order_id']; ?>">#ORD-<?php echo str_pad($c['used_in_order_id'], 4, '0', STR_PAD_LEFT); ?></a>
                            <?php else: ?>
                                &mdash;
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex;gap:0.25rem;flex-wrap:wrap;">
                                <button class="btn btn-sm btn-outline" onclick="editCoupon(<?php echo $c['id']; ?>, <?php echo $c['discount_percent']; ?>, '<?php echo $c['expires_at']; ?>', <?php echo $c['is_active']; ?>)">Edit</button>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="coupon_id" value="<?php echo $c['id']; ?>">
                                    <input type="hidden" name="new_status" value="<?php echo $c['is_active'] ? 0 : 1; ?>">
                                    <button type="submit" name="toggle_active" class="btn btn-sm <?php echo $c['is_active'] ? 'btn-warning' : 'btn-success'; ?>">
                                        <?php echo $c['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this coupon?');">
                                    <input type="hidden" name="coupon_id" value="<?php echo $c['id']; ?>">
                                    <button type="submit" name="delete_coupon" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($coupons)): ?>
                    <tr><td colspan="8" class="text-center" style="padding:2rem;color:#78716c;">No coupons found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Tab 2: Create Coupon -->
<div id="tab-create" class="tab-content" style="display:none;">
    <div class="card" style="max-width:600px;">
        <div class="card-header">Create New Coupon</div>
        <div class="card-body">
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Customer *</label>
                    <select name="customer_id" class="form-select" required>
                        <option value="">-- Select Customer --</option>
                        <?php foreach ($customers as $cust): ?>
                            <option value="<?php echo $cust['id']; ?>"><?php echo htmlspecialchars($cust['name'] . ' (' . $cust['email'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Tier</label>
                    <select name="tier" id="tierSelect" class="form-select" onchange="onTierChange()">
                        <option value="1">Returning Customer (10%)</option>
                        <option value="2">Loyal Member (15%)</option>
                        <option value="3">VIP Member (20%)</option>
                        <option value="4">Elite Member (25%)</option>
                        <option value="5">Star Member (30%)</option>
                        <option value="6" selected>Custom</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Discount Percent * (1-100)</label>
                    <input type="number" name="discount_percent" id="discountPercent" class="form-input" min="1" max="100" value="10" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Expiry Date *</label>
                    <input type="date" name="expires_at" class="form-input" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Coupon Code</label>
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.5rem;">
                        <input type="checkbox" name="auto_generate" id="autoGenerate" checked onchange="toggleCodeInput()">
                        <label for="autoGenerate" style="font-size:0.875rem;margin:0;">Auto-generate</label>
                    </div>
                    <input type="text" name="manual_code" id="manualCode" class="form-input" placeholder="UPPERCASE + NUMBERS, 5-20 chars" style="display:none;">
                </div>

                <button type="submit" name="create_coupon" value="1" class="btn btn-primary">Create Coupon</button>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal" style="display:none;">
    <div class="modal-overlay" onclick="closeEditModal()"></div>
    <div class="modal-content" style="max-width:450px;">
        <div class="modal-header">
            <h3>Edit Coupon</h3>
            <button type="button" class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="coupon_id" id="editCouponId">
            <div class="form-group">
                <label class="form-label">Discount Percent *</label>
                <input type="number" name="discount_percent" id="editDiscountPercent" class="form-input" min="1" max="100" required>
            </div>
            <div class="form-group">
                <label class="form-label">Expiry Date *</label>
                <input type="date" name="expires_at" id="editExpiresAt" class="form-input" required>
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:0.5rem;">
                    <input type="checkbox" name="is_active" id="editIsActive" value="1">
                    <span>Active</span>
                </label>
            </div>
            <button type="submit" name="edit_coupon" value="1" class="btn btn-primary">Save Changes</button>
        </form>
    </div>
</div>

<style>
.badge-active { background:#dcfce7; color:#166534; }
.badge-deactivated { background:#fed7aa; color:#9a3412; }
.badge-used { background:#e5e7eb; color:#57534e; }
.badge-expired { background:#fee2e2; color:#991b1b; }
.tabs { display:flex; gap:0; border-bottom:2px solid #e7e5e4; }
.tab-btn { padding:0.6rem 1.25rem; background:none; border:none; cursor:pointer; font-size:0.9rem; color:#78716c; border-bottom:2px solid transparent; margin-bottom:-2px; transition:all 0.2s; }
.tab-btn.active { color:#01696f; border-bottom-color:#01696f; font-weight:600; }
.tab-btn:hover { color:#01696f; }
.modal { position:fixed; top:0; left:0; width:100%; height:100%; z-index:1000; display:flex; align-items:center; justify-content:center; }
.modal-overlay { position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.4); }
.modal-content { position:relative; background:#fff; border-radius:12px; padding:1.5rem; width:90%; max-width:450px; box-shadow:0 10px 25px rgba(0,0,0,0.15); }
.modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; }
.modal-header h3 { margin:0; }
.modal-close { background:none; border:none; font-size:1.5rem; cursor:pointer; color:#78716c; }
.flash-message { animation:flashFade 4s forwards; }
@keyframes flashFade { 0%,70%{opacity:1} 100%{opacity:0;display:none} }
</style>

<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-content').forEach(t => t.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + tab).style.display = 'block';
    document.querySelector(`.tab-btn[onclick*="'${tab}'"]`).classList.add('active');
}

function onTierChange() {
    var tier = parseInt(document.getElementById('tierSelect').value);
    var discounts = {1:10, 2:15, 3:20, 4:25, 5:30};
    if (discounts[tier]) {
        document.getElementById('discountPercent').value = discounts[tier];
    }
}

function toggleCodeInput() {
    var auto = document.getElementById('autoGenerate').checked;
    document.getElementById('manualCode').style.display = auto ? 'none' : 'block';
}

function editCoupon(id, discount, expires, isActive) {
    document.getElementById('editCouponId').value = id;
    document.getElementById('editDiscountPercent').value = discount;
    document.getElementById('editExpiresAt').value = expires.substring(0, 10);
    document.getElementById('editIsActive').checked = isActive == 1;
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function filterCoupons() {
    var status = document.getElementById('filterStatus').value;
    var search = document.getElementById('searchCoupon').value.toLowerCase();
    var rows = document.querySelectorAll('.coupon-row');
    rows.forEach(function(row) {
        var rowStatus = row.getAttribute('data-status').toLowerCase();
        var text = row.textContent.toLowerCase();
        var matchStatus = status === 'all' || rowStatus === status;
        var matchSearch = text.includes(search);
        row.style.display = matchStatus && matchSearch ? '' : 'none';
    });
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
