<?php
declare(strict_types=1);

/**
 * Migration Runner for Homepage Sections
 * Compatible with local MAMP & Hostinger
 */

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/database.php';

try {
    $db = Database::getConnection();
    echo "Connected to database...\n";

    $sql = file_get_contents(__DIR__ . '/migration_homepage_sections.sql');
    $db->exec($sql);
    echo "Executed migration_homepage_sections.sql successfully!\n\n";

    $sections = $db->query("SELECT * FROM homepage_sections ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
    echo "Homepage Sections (" . count($sections) . "):\n";
    foreach ($sections as $s) {
        $cnt = $db->prepare("SELECT COUNT(*) FROM homepage_section_products WHERE section_key = ?");
        $cnt->execute([$s['section_key']]);
        $prodCount = $cnt->fetchColumn();
        echo " - [{$s['section_key']}] {$s['name']} (icon: {$s['icon']}) -> {$prodCount} product(s) assigned\n";
    }

} catch (Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
