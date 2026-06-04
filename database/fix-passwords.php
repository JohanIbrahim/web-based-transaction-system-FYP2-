<?php
/**
 * Fix Passwords Script
 * 
 * Updates all demo account passwords to match the credentials
 * shown on the login page.
 * 
 * Usage: php database/fix-passwords.php
 *        or access via browser
 */

require_once __DIR__ . '/../includes/db.php';

$updates = [
    'admin@smarttransaction.com'    => 'Admin@1234',
    'coadmin@smarttransaction.com'  => 'Admin@1234',
    'staff@smarttransaction.com'    => 'Staff@1234',
    'customer@smarttransaction.com' => 'customer123',
];

try {
    $pdo = getDBConnection();
    $success = 0;
    $errors = 0;

    foreach ($updates as $email => $plainPassword) {
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE email = :email");
        $stmt->execute([
            ':hash'  => $hash,
            ':email' => $email,
        ]);

        if ($stmt->rowCount() > 0) {
            echo "✓ Updated: {$email} → password updated successfully.\n";
            $success++;
        } else {
            echo "⚠ Skipped: {$email} — user not found.\n";
            $errors++;
        }
    }

    echo "\n--- Summary ---\n";
    echo "Successfully updated: {$success}\n";
    echo "Skipped (not found): {$errors}\n";
    echo "\nYou can now log in with the demo credentials shown on the login page.\n";

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
