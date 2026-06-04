<?php
/**
 * Admin Transaction Records Page
 * 
 * Lists all completed transactions with payment details.
 * Shows invoices and allows searching/filtering.
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin_auth.php';

startSession();
requireAdminRole();

$pageTitle = 'Transactions - Smart Transaction System';

$transactions = [];

try {
    $pdo = getDBConnection();

    $stmt = $pdo->query('
        SELECT t.id AS txn_id, t.total_amount, t.payment_method, t.status AS txn_status, t.created_at,
               o.id AS order_id, o.customer_name, o.customer_phone,
               p.transaction_ref, p.payment_method AS pay_method, p.status AS pay_status
        FROM transactions t
        JOIN orders o ON t.order_id = o.id
        LEFT JOIN payments p ON t.payment_id = p.id
        ORDER BY t.created_at DESC
    ');
    $transactions = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Unable to load transactions.';
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>Transactions</h1>
    <p>View all completed transaction records and invoices</p>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">Transaction Records</div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($transactions)): ?>
            <p style="padding: var(--spacing-lg); text-align: center; color: var(--neutral-500);">No transactions yet.</p>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Txn #</th>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th>Date</th>
                            <th>Invoice</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $txn): ?>
                            <tr>
                                <td><strong>#<?php echo (int) $txn['txn_id']; ?></strong></td>
                                <td>#<?php echo (int) $txn['order_id']; ?></td>
                                <td><?php echo htmlspecialchars($txn['customer_name']); ?></td>
                                <td><strong>RM <?php echo number_format((float) $txn['total_amount'], 2); ?></strong></td>
                                <td>
                                    <?php 
                                    $methodLabels = ['cash' => 'Cash', 'online' => 'Online', 'ewallet' => 'E-Wallet'];
                                    echo $methodLabels[$txn['payment_method']] ?? htmlspecialchars(ucfirst($txn['payment_method']));
                                    ?>
                                </td>
                                <td style="font-size: 0.8rem;"><?php echo htmlspecialchars($txn['transaction_ref'] ?? '-'); ?></td>
                                <td><?php echo date('d M Y, h:i A', strtotime($txn['created_at'])); ?></td>
                                <td>
                                    <a href="/smart-transaction/receipt.php?order_id=<?php echo (int) $txn['order_id']; ?>" class="btn btn-sm btn-outline">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
