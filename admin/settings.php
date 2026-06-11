<?php
/**
 * Admin Settings Page — Smart Transaction
 * 
 * Allows admin to upload/change the system logo.
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin_auth.php';

startSession();
requireAdminRole();

$pageTitle = 'Settings — Smart Transaction';

$message = '';
$messageType = '';

$logoPath = __DIR__ . '/../uploads/logo.png';
$logoUrl = '/smart-transaction/uploads/logo.png';

// Handle logo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_logo'])) {
    if (isset($_FILES['logo_image']) && $_FILES['logo_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['logo_image'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 2 * 1024 * 1024; // 2MB

        if (!in_array($file['type'], $allowedTypes)) {
            $message = 'Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.';
            $messageType = 'danger';
        } elseif ($file['size'] > $maxSize) {
            $message = 'File too large. Maximum size is 2MB.';
            $messageType = 'danger';
        } else {
            // Delete old logo if exists
            if (file_exists($logoPath)) {
                unlink($logoPath);
            }
            if (move_uploaded_file($file['tmp_name'], $logoPath)) {
                $message = 'Logo uploaded successfully!';
                $messageType = 'success';
            } else {
                $message = 'Failed to upload logo.';
                $messageType = 'danger';
            }
        }
    } else {
        $message = 'Please select an image file.';
        $messageType = 'warning';
    }
}

// Handle logo removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_logo'])) {
    if (file_exists($logoPath)) {
        unlink($logoPath);
        $message = 'Logo removed. Default logo will be used.';
        $messageType = 'success';
    }
}

$hasLogo = file_exists($logoPath);

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Settings</h1>
    <p>Manage system settings</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="card" style="max-width: 600px;">
    <div class="card-header">System Logo</div>
    <div class="card-body">
        <div style="margin-bottom: 1.5rem;">
            <label class="form-label">Current Logo</label>
            <div style="padding: 1rem; background: var(--neutral-100); border-radius: 8px; border: 1px solid var(--neutral-200); display: flex; align-items: center; gap: 1rem;">
                <?php if ($hasLogo): ?>
                    <img src="/smart-transaction/uploads/logo.png?t=<?php echo time(); ?>" alt="System Logo" style="max-height: 60px; max-width: 200px; object-fit: contain;">
                    <span style="color: var(--color-text-muted); font-size: 0.85rem;">Current logo</span>
                <?php else: ?>
                    <span style="font-size: 2rem; opacity: 0.4;">&#127912;</span>
                    <span style="color: var(--color-text-muted); font-size: 0.85rem;">No custom logo uploaded — using default SVG logo</span>
                <?php endif; ?>
            </div>
        </div>

        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label for="logo_image" class="form-label">Upload New Logo</label>
                <input type="file" id="logo_image" name="logo_image" class="form-input" accept="image/jpeg,image/png,image/gif,image/webp" required>
                <small style="color: var(--color-text-muted);">Max 2MB. Recommended size: 200x60px. Allowed: JPG, PNG, GIF, WebP</small>
            </div>
            <div class="d-flex gap-1">
                <button type="submit" name="upload_logo" value="1" class="btn btn-primary">Upload Logo</button>
                <?php if ($hasLogo): ?>
                    <button type="submit" name="remove_logo" value="1" class="btn btn-danger" onclick="return confirm('Remove the custom logo? Default logo will be used.');">Remove Logo</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
