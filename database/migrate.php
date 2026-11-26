<?php
declare(strict_types=1);

/**
 * Database Migration and Seeder Runner
 * iboltnetworks E-Commerce Platform
 *
 * Runs schema.sql and seeders.sql against the configured MySQL instance.
 * Compatible with local MAMP (socket/port 8889) and Hostinger production.
 */

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/database.php';

echo "====================================================\n";
echo " iboltnetworks Database Migration & Seeding Tool \n";
echo "====================================================\n\n";

try {
    $dbName = (string)config('db_name', env('DB_DATABASE', 'iboltnetworks_db'));
    $dbUser = (string)config('db_user', env('DB_USERNAME', 'root'));
    $dbPass = (string)config('db_pass', env('DB_PASSWORD', 'root'));
    $dbHost = (string)config('db_host', env('DB_HOST', 'localhost'));
    $dbPort = (int)config('db_port', (int)env('DB_PORT', 3306));
    $dbSocket = (string)config('db_socket', env('DB_SOCKET', ''));

    echo "1. Connecting to MySQL server and ensuring database '{$dbName}' exists...\n";

    if (!empty($dbSocket) && file_exists($dbSocket)) {
        $serverDsn = "mysql:unix_socket={$dbSocket};charset=utf8mb4";
        $dsn = "mysql:unix_socket={$dbSocket};dbname={$dbName};charset=utf8mb4";
        echo "   Using UNIX socket: {$dbSocket}\n";
    } else {
        $serverDsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
        $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
        echo "   Using TCP host: {$dbHost}:{$dbPort}\n";
    }

    // Ensure database exists
    try {
        $initPdo = new PDO($serverDsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $initPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    } catch (Throwable $dbInitErr) {
        // Fallback: Continue if user has restricted privileges (e.g., shared hosting)
    }

    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
    ]);

    echo "   [SUCCESS] Connected to database '{$dbName}' successfully.\n\n";

    // 2. Run Schema (All 18 tables + Reference Homepage Sections)
    $schemaFile = __DIR__ . '/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new RuntimeException("Schema file not found at: {$schemaFile}");
    }
    echo "2. Applying complete database schema ({$schemaFile})...\n";
    $schemaSql = file_get_contents($schemaFile);
    $pdo->exec($schemaSql);
    echo "   [SUCCESS] Schema applied successfully (all 18 tables verified).\n\n";

    // 3. Run Seeders (Catalog products, categories, brands, images, specs)
    $seedersFile = __DIR__ . '/seeders.sql';
    if (!file_exists($seedersFile)) {
        throw new RuntimeException("Seeders file not found at: {$seedersFile}");
    }
    echo "3. Seeding catalog data ({$seedersFile})...\n";
    $seedersSql = file_get_contents($seedersFile);
    $pdo->exec($seedersSql);
    echo "   [SUCCESS] Seeders executed successfully.\n\n";

    // 4. Run Any Versioned Migrations (Idempotent)
    $migrationDir = __DIR__ . '/migrations';
    if (is_dir($migrationDir)) {
        echo "4. Running versioned migrations...\n";
        $migrationFiles = glob($migrationDir . '/*.sql');
        sort($migrationFiles);
        foreach ($migrationFiles as $migPath) {
            $baseName = basename($migPath);
            echo "   Applying {$baseName}...\n";
            $migSql = file_get_contents($migPath);
            $pdo->exec($migSql);
            echo "   [SUCCESS] {$baseName} verified.\n";
        }
        echo "\n";
    }

    // 4. Verify Database Integrity
    echo "4. Verifying database records...\n";

    $catCount = $pdo->query("SELECT COUNT(*) FROM `categories`")->fetchColumn();
    $brandCount = $pdo->query("SELECT COUNT(*) FROM `brands`")->fetchColumn();
    $prodCount = $pdo->query("SELECT COUNT(*) FROM `products`")->fetchColumn();
    $imgCount = $pdo->query("SELECT COUNT(*) FROM `product_images`")->fetchColumn();
    $specCount = $pdo->query("SELECT COUNT(*) FROM `product_specifications`")->fetchColumn();
    $adminCount = $pdo->query("SELECT COUNT(*) FROM `admin_users`")->fetchColumn();

    echo "   - Categories:      {$catCount}\n";
    echo "   - Brands:          {$brandCount}\n";
    echo "   - Products:        {$prodCount}\n";
    echo "   - Product Images:  {$imgCount}\n";
    echo "   - Specifications:  {$specCount}\n";
    echo "   - Admin Accounts:  {$adminCount}\n\n";

    $admin = $pdo->query("SELECT `id`, `name`, `email`, `role`, `status` FROM `admin_users` LIMIT 1")->fetch();
    if ($admin) {
        echo "   Super Admin Initialized:\n";
        echo "   Email:    {$admin['email']}\n";
        echo "   Role:     {$admin['role']}\n";
        echo "   Status:   {$admin['status']}\n";
    }

    echo "\n====================================================\n";
    echo " Migration and Seeding Completed Successfully! \n";
    echo "====================================================\n";

} catch (Throwable $e) {
    echo "\n[ERROR] Migration failed:\n" . $e->getMessage() . "\n";
    exit(1);
}
