<?php
/**
 * Customer Transaction History Page
 * 
 * Allows customers to look up past orders by entering their phone number.
 * Displays a table of past transactions with links to view receipts.
 */

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';

startSession();

$pageTitle = 'Transaction History - Smart Transaction System';

$transactions = [];
$searchError = '';
$searchedPhone = '';

// Handle phone lookup
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['phone'])) {
    $searchedPhone = trim($_POST['phone'] ?? $_GET['phone'] ?? '');

    if (empty($searchedPhone)) {
        $searchError = 'Please enter your phone number.';
    } else {
        try {
            $pdo = getDBConnection();

            $stmt = $pdo->prepare('
                SELECT o.id AS order_id, o.customer_name, o.customer_phone, o.total_amount, 
                       o.status, o.payment_status, o.created_at,
                       p.payment_method, p.transaction_ref
                FROM orders o
                LEFT JOIN payments p ON p.order_id = o.id AND p.status = "completed"
                WHERE o.customer_phone = :phone
                ORDER BY o.created_at DESC
            ');
            $stmt->execute([':phone' => $searchedPhone]);
            $transactions = $stmt->fetchAll();

            if (empty($transactions)) {
                $searchError = 'No transactions found for this phone number.';
            }
        } catch (PDOException $e) {
            $searchError = 'An error occurred. Please try again later.';
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1>Transaction History</h1>
    <p>Look up your past orders using your phone number</p>
</div>

<!-- Search Form -->
<div class="card mb-3">
    <div class="card-body">
        <form method="POST" action="">
            <div class="d-flex gap-1 align-center" style="flex-wrap: wrap;">
                <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 250px;">
                    <label for="phone" class="form-label">Phone Number *</label>
                    <input type="text" id="phone" name="phone" class="form-input" required
                           placeholder="e.g. 012-3456789"
                           value="<?php echo htmlspecialchars($searchedPhone); ?>">
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top: 1.5rem;">Search</button>
            </div>
        </form>
    </div>
</div>

<?php if ($searchError): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($searchError); ?></div>
<?php endif; ?>

<?php if (!empty($transactions)): ?>
    <div class="card">
        <div class="card-header">
            Past Transactions (<?php echo count($transactions); ?> found)
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Date & Time</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $txn): ?>
                            <tr>
                                <td><strong>#<?php echo (int) $txn['order_id']; ?></strong></td>
                                <td><?php echo date('d M Y, h:i A', strtotime($txn['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($txn['customer_name']); ?></td>
                                <td><strong>RM <?php echo number_format((float) $txn['total_amount'], 2); ?></strong></td>
                                <td>
                                    <?php 
                                    $methodLabels = ['cash' => 'Cash', 'online' => 'Online', 'ewallet' => 'E-Wallet'];
                                    echo $methodLabels[$txn['payment_method'] ?? ''] ?? '-';
                                    ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo htmlspecialchars($txn['status']); ?>">
                                        <?php echo htmlspecialchars(ucfirst($txn['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="/smart-transaction/receipt.php?order_id=<?php echo (int) $txn['order_id']; ?>" 
                                       class="btn btn-sm btn-outline">View Receipt</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !$searchError): ?>
    <div class="alert alert-info">No transactions found for this phone number.</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
