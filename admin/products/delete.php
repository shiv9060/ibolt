<?php
declare(strict_types=1);

/**
 * Admin Product Deletion Controller
 * iboltnetworks E-Commerce Platform
 */

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/auth.php';
require_once __DIR__ . '/../../app/helpers/csrf.php';
require_once __DIR__ . '/../../app/helpers/sanitize.php';

init_session();
require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $id = sanitize_int($_POST['id'] ?? 0);
        if ($id > 0) {
            $db = Database::getConnection();

            // Fetch product title for log
            $stmt = $db->prepare("SELECT title FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $title = $stmt->fetchColumn();

            // Delete product (cascades specs and images)
            $db->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);

            // Log activity
            $admin = current_admin();
            $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, entity_type, entity_id, details) VALUES (?, 'delete_product', 'product', ?, ?)")
               ->execute([$admin['id'], $id, json_encode(['title' => $title])]);

            header("Location: index.php?success=" . urlencode("Product \"{$title}\" was successfully deleted."));
            exit;
        }
    }
}

header("Location: index.php");
exit;
