<?php
/**
 * Admin Manage Accounts Page
 * 
 * Allows admin to view, edit, and delete user accounts.
 * Admin-only access.
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin_auth.php';

startSession();
requireAdminRole();

$pageTitle = 'Manage Accounts — Smart Transaction';

$pdo = getDBConnection();
$flash = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Edit user
    if (isset($_POST['edit_user'])) {
        $userId   = (int) ($_POST['user_id'] ?? 0);
        $name     = trim($_POST['name'] ?? '');
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $phone    = trim($_POST['phone'] ?? '');
        $role     = $_POST['role'] ?? 'customer';
        $password = $_POST['password'] ?? '';

        if ($userId > 0 && !empty($name) && !empty($email)) {
            try {
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET name = :name, email = :email, phone = :phone, role = :role, password_hash = :hash WHERE id = :id");
                    $stmt->execute([':name' => $name, ':email' => $email, ':phone' => $phone, ':role' => $role, ':hash' => $hash, ':id' => $userId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET name = :name, email = :email, phone = :phone, role = :role WHERE id = :id");
                    $stmt->execute([':name' => $name, ':email' => $email, ':phone' => $phone, ':role' => $role, ':id' => $userId]);
                }
                $flash = ['type' => 'success', 'message' => 'User updated successfully.'];
            } catch (PDOException $e) {
                $flash = ['type' => 'danger', 'message' => 'Error: ' . $e->getMessage()];
            }
        } else {
            $flash = ['type' => 'danger', 'message' => 'Invalid data.'];
        }
    }

    // Delete user
    if (isset($_POST['delete_user'])) {
        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($userId > 0) {
            // Prevent deleting yourself
            if ($userId === (int) ($_SESSION['user_id'] ?? 0)) {
                $flash = ['type' => 'danger', 'message' => 'You cannot delete your own account.'];
            } else {
                try {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
                    $stmt->execute([':id' => $userId]);
                    $flash = ['type' => 'success', 'message' => 'User deleted successfully.'];
                } catch (PDOException $e) {
                    $flash = ['type' => 'danger', 'message' => 'Error: ' . $e->getMessage()];
                }
            }
        }
    }
}

// Fetch all users
$users = [];
try {
    $stmt = $pdo->query("SELECT id, name, email, phone, role, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $flash = ['type' => 'danger', 'message' => 'Database error: ' . $e->getMessage()];
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Manage Accounts</h1>
    <p>View, edit, and manage all user accounts</p>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?> flash-message"><?php echo $flash['message']; ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body" style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><strong>#<?php echo (int) $u['id']; ?></strong></td>
                    <td><?php echo htmlspecialchars($u['name']); ?></td>
                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                    <td><?php echo htmlspecialchars($u['phone'] ?? '-'); ?></td>
                    <td>
                        <span class="badge badge-<?php echo htmlspecialchars($u['role']); ?>">
                            <?php echo htmlspecialchars(ucfirst($u['role'])); ?>
                        </span>
                    </td>
                    <td><?php echo date('d M Y', strtotime($u['created_at'])); ?></td>
                    <td>
                        <div style="display:flex;gap:0.25rem;flex-wrap:wrap;">
                            <button class="btn btn-sm btn-outline" onclick="editUser(<?php echo (int) $u['id']; ?>)">Edit</button>
                            <?php if ((int) $u['id'] !== (int) ($_SESSION['user_id'] ?? 0)): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete user \'<?php echo htmlspecialchars($u['name']); ?>\'? This cannot be undone.');">
                                <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                                <button type="submit" name="delete_user" class="btn btn-sm btn-danger">Delete</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                <tr><td colspan="7" class="text-center" style="padding:2rem;color:#78716c;">No users found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="modal" style="display:none;">
    <div class="modal-overlay" onclick="closeEditModal()"></div>
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
            <h3 id="modalTitle">Edit User</h3>
            <button type="button" class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form method="POST" action="" id="editUserForm">
            <input type="hidden" name="user_id" id="editUserId" value="0">

            <div class="form-group">
                <label class="form-label">Name *</label>
                <input type="text" name="name" id="editName" class="form-input" required>
            </div>

            <div class="form-group">
                <label class="form-label">Email *</label>
                <input type="email" name="email" id="editEmail" class="form-input" required>
            </div>

            <div class="form-group">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" id="editPhone" class="form-input">
            </div>

            <div class="form-group">
                <label class="form-label">Role *</label>
                <select name="role" id="editRole" class="form-input" required>
                    <option value="customer">Customer</option>
                    <option value="staff">Staff</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">New Password <span style="font-weight:normal;color:#78716c;">(leave blank to keep current)</span></label>
                <input type="password" name="password" id="editPassword" class="form-input" placeholder="Enter new password">
            </div>

            <button type="submit" name="edit_user" class="btn btn-primary">Save Changes</button>
        </form>
    </div>
</div>

<style>
.badge-admin { background:#dcfce7; color:#166534; }
.badge-staff { background:#dbeafe; color:#1e40af; }
.badge-customer { background:#fef3c7; color:#92400e; }
.modal { position:fixed; top:0; left:0; width:100%; height:100%; z-index:1000; display:flex; align-items:center; justify-content:center; }
.modal-overlay { position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.4); }
.modal-content { position:relative; background:#fff; border-radius:12px; padding:1.5rem; width:90%; max-width:500px; box-shadow:0 10px 25px rgba(0,0,0,0.15); }
.modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; }
.modal-header h3 { margin:0; }
.modal-close { background:none; border:none; font-size:1.5rem; cursor:pointer; color:#78716c; }
.flash-message { animation:flashFade 4s forwards; }
@keyframes flashFade { 0%,70%{opacity:1} 100%{opacity:0;display:none} }
</style>

<script>
function closeEditModal() {
    document.getElementById('editUserModal').style.display = 'none';
}

function editUser(id) {
    // Fetch user data via AJAX
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '/smart-transaction/admin/get_user.php?id=' + id, true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var data = JSON.parse(xhr.responseText);
                document.getElementById('editUserId').value = data.id;
                document.getElementById('editName').value = data.name;
                document.getElementById('editEmail').value = data.email;
                document.getElementById('editPhone').value = data.phone || '';
                document.getElementById('editRole').value = data.role;
                document.getElementById('editPassword').value = '';
                document.getElementById('modalTitle').textContent = 'Edit: ' + data.name;
                document.getElementById('editUserModal').style.display = 'flex';
            } catch(e) {
                alert('Failed to load user data.');
            }
        } else {
            alert('Failed to load user data.');
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
