<?php
declare(strict_types=1);

/**
 * Bulk Product Operations Controller
 * iboltnetworks E-Commerce Platform
 */

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/auth.php';
require_once __DIR__ . '/../../app/helpers/csrf.php';
require_once __DIR__ . '/../../app/helpers/sanitize.php';
require_once __DIR__ . '/../../app/helpers/currency.php';
require_once __DIR__ . '/../../app/helpers/homepage.php';

init_session();
require_admin();

$admin = current_admin();
$db = Database::getConnection();

// Handle AJAX Price Preview
if (isset($_GET['action']) && $_GET['action'] === 'price_preview') {
    header('Content-Type: application/json; charset=utf-8');

    $rawIds = $_GET['ids'] ?? [];
    if (is_string($rawIds)) {
        $rawIds = explode(',', $rawIds);
    }
    $ids = array_filter(array_map('intval', (array)$rawIds), fn($id) => $id > 0);

    if (empty($ids)) {
        echo json_encode(['success' => false, 'error' => 'No products selected']);
        exit;
    }

    $mode      = clean_string($_GET['mode'] ?? 'percent'); // 'percent' or 'fixed'
    $direction = clean_string($_GET['direction'] ?? 'increase'); // 'increase' or 'decrease'
    $value     = (float)($_GET['value'] ?? 0);

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT id, title, sku, price_paise, compare_price_paise FROM products WHERE id IN ({$placeholders}) ORDER BY id DESC");
    $stmt->execute(array_values($ids));
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $previewList = [];
    $validCount = 0;
    $errorCount = 0;

    foreach ($products as $p) {
        $oldPricePaise = (int)$p['price_paise'];
        $mrpPaise = $p['compare_price_paise'] !== null ? (int)$p['compare_price_paise'] : null;

        $deltaPaise = 0;
        if ($mode === 'percent') {
            $deltaPaise = (int)round($oldPricePaise * ($value / 100));
        } else {
            $deltaPaise = inr_to_paise($value);
        }

        $proposedPricePaise = ($direction === 'increase') ? ($oldPricePaise + $deltaPaise) : ($oldPricePaise - $deltaPaise);
        $status = 'valid';
        $message = 'OK';

        if ($proposedPricePaise <= 0) {
            $status = 'error';
            $message = 'Resulting price cannot be ≤ ₹0';
            $errorCount++;
        } elseif ($mrpPaise !== null && $mrpPaise > 0 && $proposedPricePaise > $mrpPaise) {
            $status = 'warning';
            $message = 'Proposed price exceeds MRP (' . format_inr($mrpPaise) . ')';
            $validCount++;
        } else {
            $validCount++;
        }

        $previewList[] = [
            'id'                  => (int)$p['id'],
            'title'               => $p['title'],
            'sku'                 => $p['sku'],
            'old_price_paise'     => $oldPricePaise,
            'old_price_formatted' => format_inr($oldPricePaise),
            'mrp_formatted'       => $mrpPaise ? format_inr($mrpPaise) : '—',
            'new_price_paise'     => $proposedPricePaise,
            'new_price_formatted' => format_inr(max(0, $proposedPricePaise)),
            'difference_formatted'=> ($proposedPricePaise >= $oldPricePaise ? '+' : '') . format_inr($proposedPricePaise - $oldPricePaise),
            'status'              => $status,
            'message'             => $message
        ];
    }

    echo json_encode([
        'success'      => true,
        'preview'      => $previewList,
        'total_count'  => count($products),
        'valid_count'  => $validCount,
        'error_count'  => $errorCount
    ]);
    exit;
}

// POST Actions
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header("Location: index.php");
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    header("Location: index.php?error=" . urlencode("Security session expired. Please re-submit."));
    exit;
}

$action = clean_string($_POST['bulk_action'] ?? '');
$rawIds = $_POST['selected_ids'] ?? [];
if (is_string($rawIds)) {
    $rawIds = explode(',', $rawIds);
}
$ids = array_filter(array_map('intval', (array)$rawIds), fn($id) => $id > 0);

if (empty($ids)) {
    header("Location: index.php?error=" . urlencode("No products were selected for bulk action."));
    exit;
}

// -------------------------------------------------------------------------
// 1. Bulk Status Update (published, draft, archived)
// -------------------------------------------------------------------------
if ($action === 'status') {
    $newStatus = clean_string($_POST['new_status'] ?? '');
    $validStatuses = ['published', 'draft', 'archived'];

    if (!in_array($newStatus, $validStatuses, true)) {
        header("Location: index.php?error=" . urlencode("Invalid product status specified."));
        exit;
    }

    try {
        $db->beginTransaction();

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("UPDATE products SET status = ?, updated_at = NOW() WHERE id IN ({$placeholders})");
        $stmt->execute(array_merge([$newStatus], array_values($ids)));
        $updatedCount = $stmt->rowCount();

        // Audit log
        $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, entity_type, details, ip_address)
                      VALUES (?, 'bulk_status_update', 'products', ?, ?)")
           ->execute([
               $admin['id'],
               json_encode(['status' => $newStatus, 'product_ids' => array_values($ids), 'count' => $updatedCount]),
               $_SERVER['REMOTE_ADDR'] ?? ''
           ]);

        $db->commit();

        $msg = "Successfully updated {$updatedCount} product(s) to " . ucfirst($newStatus) . ".";
        header("Location: index.php?success=" . urlencode($msg));
        exit;

    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Bulk status update error: " . $e->getMessage());
        header("Location: index.php?error=" . urlencode("Bulk status operation failed: " . $e->getMessage()));
        exit;
    }
}

// -------------------------------------------------------------------------
// 2. Bulk Homepage Section Assignment
// -------------------------------------------------------------------------
if ($action === 'assign_section') {
    $sectionKey = clean_string($_POST['section_key'] ?? '');
    $validSections = ['servers', 'networking', 'components', 'our-products', 'featured'];

    if (!in_array($sectionKey, $validSections, true)) {
        header("Location: index.php?error=" . urlencode("Invalid homepage section specified."));
        exit;
    }

    try {
        $db->beginTransaction();

        // Validate products exist
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $checkStmt = $db->prepare("SELECT id FROM products WHERE id IN ({$placeholders})");
        $checkStmt->execute(array_values($ids));
        $validProdIds = $checkStmt->fetchAll(PDO::FETCH_COLUMN);

        $upsertStmt = $db->prepare("INSERT INTO homepage_section_products (section_key, product_id, sort_order, badge_text, is_featured, is_visible)
                                    VALUES (?, ?, 0, '', ?, 1)
                                    ON DUPLICATE KEY UPDATE is_visible = 1, updated_at = NOW()");

        $assignedCount = 0;
        $isFeatured = ($sectionKey === 'featured') ? 1 : 0;
        foreach ($validProdIds as $pId) {
            $upsertStmt->execute([$sectionKey, $pId, $isFeatured]);
            $assignedCount++;
        }

        // Audit log
        $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, entity_type, details, ip_address)
                      VALUES (?, 'bulk_assign_section', 'products', ?, ?)")
           ->execute([
               $admin['id'],
               json_encode(['section' => $sectionKey, 'product_ids' => $validProdIds, 'count' => $assignedCount]),
               $_SERVER['REMOTE_ADDR'] ?? ''
           ]);

        $db->commit();

        $secName = ucfirst(str_replace('-', ' ', $sectionKey));
        $msg = "Successfully assigned {$assignedCount} product(s) to {$secName} section.";
        header("Location: index.php?success=" . urlencode($msg));
        exit;

    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Bulk assign section error: " . $e->getMessage());
        header("Location: index.php?error=" . urlencode("Bulk section assignment failed: " . $e->getMessage()));
        exit;
    }
}

// -------------------------------------------------------------------------
// 3. Bulk Homepage Section Removal
// -------------------------------------------------------------------------
if ($action === 'remove_section') {
    $sectionKey = clean_string($_POST['section_key'] ?? '');
    $validSections = ['servers', 'networking', 'components', 'our-products', 'featured', 'all'];

    if (!in_array($sectionKey, $validSections, true)) {
        header("Location: index.php?error=" . urlencode("Invalid homepage section specified for removal."));
        exit;
    }

    try {
        $db->beginTransaction();

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        if ($sectionKey === 'all') {
            $stmt = $db->prepare("DELETE FROM homepage_section_products WHERE product_id IN ({$placeholders})");
            $stmt->execute(array_values($ids));
        } else {
            $stmt = $db->prepare("DELETE FROM homepage_section_products WHERE section_key = ? AND product_id IN ({$placeholders})");
            $stmt->execute(array_merge([$sectionKey], array_values($ids)));
        }
        $removedCount = $stmt->rowCount();

        // Audit log
        $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, entity_type, details, ip_address)
                      VALUES (?, 'bulk_remove_section', 'products', ?, ?)")
           ->execute([
               $admin['id'],
               json_encode(['section' => $sectionKey, 'product_ids' => array_values($ids), 'count' => $removedCount]),
               $_SERVER['REMOTE_ADDR'] ?? ''
           ]);

        $db->commit();

        $secLabel = $sectionKey === 'all' ? 'all homepage sections' : (ucfirst(str_replace('-', ' ', $sectionKey)) . ' section');
        $msg = "Successfully removed section assignments for selected products from {$secLabel}.";
        header("Location: index.php?success=" . urlencode($msg));
        exit;

    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Bulk remove section error: " . $e->getMessage());
        header("Location: index.php?error=" . urlencode("Bulk section removal failed: " . $e->getMessage()));
        exit;
    }
}

// -------------------------------------------------------------------------
// 4. Bulk Price Adjustment
// -------------------------------------------------------------------------
if ($action === 'price_update') {
    $mode      = clean_string($_POST['price_mode'] ?? 'percent'); // 'percent' or 'fixed'
    $direction = clean_string($_POST['price_direction'] ?? 'increase'); // 'increase' or 'decrease'
    $value     = (float)($_POST['price_value'] ?? 0);

    if ($value <= 0) {
        header("Location: index.php?error=" . urlencode("Please specify an adjustment value greater than 0."));
        exit;
    }

    try {
        $db->beginTransaction();

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("SELECT id, title, sku, price_paise, compare_price_paise FROM products WHERE id IN ({$placeholders}) FOR UPDATE");
        $stmt->execute(array_values($ids));
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $updateStmt = $db->prepare("UPDATE products SET price_paise = ?, updated_at = NOW() WHERE id = ?");
        $updatedCount = 0;
        $skippedCount = 0;
        $details = [];

        foreach ($products as $p) {
            $oldPricePaise = (int)$p['price_paise'];
            $deltaPaise = 0;

            if ($mode === 'percent') {
                $deltaPaise = (int)round($oldPricePaise * ($value / 100));
            } else {
                $deltaPaise = inr_to_paise($value);
            }

            $proposedPricePaise = ($direction === 'increase') ? ($oldPricePaise + $deltaPaise) : ($oldPricePaise - $deltaPaise);

            // Reject invalid prices (<= 0)
            if ($proposedPricePaise <= 0) {
                $skippedCount++;
                continue;
            }

            $updateStmt->execute([$proposedPricePaise, (int)$p['id']]);
            $updatedCount++;
            $details[] = [
                'id'        => (int)$p['id'],
                'sku'       => $p['sku'],
                'old_paise' => $oldPricePaise,
                'new_paise' => $proposedPricePaise
            ];
        }

        // Audit log
        $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, entity_type, details, ip_address)
                      VALUES (?, 'bulk_price_update', 'products', ?, ?)")
           ->execute([
               $admin['id'],
               json_encode([
                   'mode'          => $mode,
                   'direction'     => $direction,
                   'value'         => $value,
                   'updated_count' => $updatedCount,
                   'skipped_count' => $skippedCount,
                   'items'         => $details
               ]),
               $_SERVER['REMOTE_ADDR'] ?? ''
           ]);

        $db->commit();

        $msg = "Bulk price update complete: {$updatedCount} product(s) updated.";
        if ($skippedCount > 0) {
            $msg .= " ({$skippedCount} product(s) skipped due to zero/negative price limits)";
        }
        header("Location: index.php?success=" . urlencode($msg));
        exit;

    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Bulk price update error: " . $e->getMessage());
        header("Location: index.php?error=" . urlencode("Bulk price update failed: " . $e->getMessage()));
        exit;
    }
}

header("Location: index.php");
exit;
