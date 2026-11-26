<?php
declare(strict_types=1);

/**
 * iboltnetworks E-Commerce - Secure Admin Provisioning & Password Reset CLI
 * 
 * Usage:
 *   php database/create_admin.php
 *   php database/create_admin.php "sales@iboltnetworks.com" "admin" "MySecurePass123!" "System Administrator"
 */

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/sanitize.php';

echo "=================================================================\n";
echo " iboltnetworks Secure Administrator Provisioning Tool            \n";
echo "=================================================================\n\n";

try {
    $db = Database::getConnection();
} catch (Throwable $e) {
    echo "ERROR: Could not connect to database: " . $e->getMessage() . "\n";
    echo "Please check your .env configuration.\n";
    exit(1);
}

// 1. Gather parameters from CLI arguments or prompt
$email = $argv[1] ?? null;
$username = $argv[2] ?? null;
$password = $argv[3] ?? null;
$name = $argv[4] ?? null;

if (empty($email)) {
    echo "Enter Administrator Email [sales@iboltnetworks.com]: ";
    $input = trim((string)fgets(STDIN));
    $email = !empty($input) ? $input : 'sales@iboltnetworks.com';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "ERROR: Invalid email address '{$email}'.\n";
    exit(1);
}

if (empty($username)) {
    echo "Enter Administrator Username [admin]: ";
    $input = trim((string)fgets(STDIN));
    $username = !empty($input) ? $input : 'admin';
}

if (empty($name)) {
    echo "Enter Administrator Display Name [System Administrator]: ";
    $input = trim((string)fgets(STDIN));
    $name = !empty($input) ? $input : 'System Administrator';
}

$generatedPassword = false;
if (empty($password)) {
    echo "Enter Strong Password (leave empty to generate a random 18-char password): ";
    $input = trim((string)fgets(STDIN));
    if (!empty($input)) {
        $password = $input;
    } else {
        $password = bin2hex(random_bytes(6)) . '!' . strtoupper(bin2hex(random_bytes(3)));
        $generatedPassword = true;
    }
}

if (strlen($password) < 8) {
    echo "ERROR: Password must be at least 8 characters long.\n";
    exit(1);
}

$passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// Check if admin with this email or username already exists
$stmt = $db->prepare("SELECT id FROM admin_users WHERE email = ? OR username = ? LIMIT 1");
$stmt->execute([$email, $username]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    $adminId = (int)$existing['id'];
    $upStmt = $db->prepare("UPDATE admin_users 
                           SET name = ?, username = ?, email = ?, password_hash = ?, role = 'super_admin', status = 'active', updated_at = NOW()
                           WHERE id = ?");
    $upStmt->execute([$name, $username, $email, $passwordHash, $adminId]);
    echo "\n[SUCCESS] Updated existing administrator account #{$adminId}.\n";
} else {
    $insStmt = $db->prepare("INSERT INTO admin_users (name, username, email, password_hash, role, status, created_at, updated_at)
                             VALUES (?, ?, ?, ?, 'super_admin', 'active', NOW(), NOW())");
    $insStmt->execute([$name, $username, $email, $passwordHash]);
    $adminId = (int)$db->lastInsertId();
    echo "\n[SUCCESS] Created new super administrator account #{$adminId}.\n";
}

echo "-----------------------------------------------------------------\n";
echo " Email:    {$email}\n";
echo " Username: {$username}\n";
echo " Password: {$password}\n";
echo " Role:     super_admin\n";
echo " Status:   active\n";
echo "-----------------------------------------------------------------\n";
if ($generatedPassword) {
    echo "IMPORTANT: Save the generated password above in your password manager!\n";
}
echo "\nYou can now sign in at: " . rtrim(config('app_url'), '/') . "/admin/login.php\n\n";
exit(0);

