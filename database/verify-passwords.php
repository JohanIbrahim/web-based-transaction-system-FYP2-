<?php
require_once __DIR__ . '/../includes/db.php';

$tests = [
    'admin@smarttransaction.com'    => 'Admin@1234',
    'coadmin@smarttransaction.com'  => 'Admin@1234',
    'staff@smarttransaction.com'    => 'Staff@1234',
    'customer@smarttransaction.com' => 'customer123',
];

$pdo = getDBConnection();
$allOk = true;

foreach ($tests as $email => $expectedPassword) {
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $hash = $stmt->fetchColumn();
    
    if ($hash && password_verify($expectedPassword, $hash)) {
        echo "✓ {$email} — password matches '{$expectedPassword}'\n";
    } else {
        echo "✗ {$email} — PASSWORD MISMATCH!\n";
        $allOk = false;
    }
}

echo "\n" . ($allOk ? "All passwords verified successfully!" : "Some passwords still need fixing.");
