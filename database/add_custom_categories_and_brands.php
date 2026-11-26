<?php
declare(strict_types=1);

/**
 * Custom Categories & Brands Seeder Script
 * iboltnetworks E-Commerce Platform
 */

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/database.php';

echo "=====================================================\n";
echo " Adding Categories and Brands to iboltnetworks Database \n";
echo "=====================================================\n\n";

try {
    $db = Database::getConnection();
    echo "Connected to database.\n";

    $migrationSql = file_get_contents(__DIR__ . '/migrations/004_add_custom_categories_and_brands.sql');
    if (!$migrationSql) {
        throw new RuntimeException("Migration SQL file not found!");
    }

    $db->exec($migrationSql);

    echo "[SUCCESS] All categories and brands have been added/updated successfully!\n\n";

    echo "--- Available Brands in Database ---\n";
    $brands = $db->query("SELECT id, name, slug FROM brands ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($brands as $b) {
        echo "  [#{$b['id']}] {$b['name']} (slug: {$b['slug']})\n";
    }

    echo "\n--- Added Categories in Database ---\n";
    $categories = $db->query("SELECT id, name, slug, section FROM categories ORDER BY section ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($categories as $c) {
        echo "  [#{$c['id']}] {$c['name']} (Section: {$c['section']}, slug: {$c['slug']})\n";
    }

} catch (Throwable $e) {
    echo "[ERROR] Failed: " . $e->getMessage() . "\n";
    exit(1);
}
