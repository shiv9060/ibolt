<?php
declare(strict_types=1);

/**
 * Audited Inventory Adjustment Controller
 * iboltnetworks E-Commerce Platform
 */

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/auth.php';
require_once __DIR__ . '/../../app/helpers/csrf.php';
require_once __DIR__ . '/../../app/helpers/sanitize.php';

init_session();
require_admin();

$admin = current_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header("Location: index.php");
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    header("Location: index.php?error=" . urlencode("Security session expired. Please retry."));
    exit;
}

$productId = sanitize_int($_POST['product_id'] ?? 0);
$type      = clean_string($_POST['adjustment_type'] ?? 'add'); // 'add', 'subtract', 'set'
$amount    = sanitize_int($_POST['amount'] ?? 0);
$reason    = clean_string($_POST['reason'] ?? '');
$note      = clean_string($_POST['note'] ?? '');

$validReasons = ['restock', 'manual_adjustment', 'damage'];
if ($productId <= 0) {
    header("Location: index.php?error=" . urlencode("Invalid product selected for stock adjustment."));
    exit;
}

if (!in_array($reason, $validReasons, true)) {
    header("Location: index.php?error=" . urlencode("A valid mandatory reason must be selected for stock adjustment."));
    exit;
}

if ($amount < 0 || ($amount === 0 && $type !== 'set')) {
    header("Location: index.php?error=" . urlencode("Adjustment amount must be a positive integer."));
    exit;
}

$db = Database::getConnection();

try {
    $db->beginTransaction();

    // Lock product row to prevent race conditions
    $lockStmt = $db->prepare("SELECT id, title, sku, stock_quantity FROM products WHERE id = ? FOR UPDATE");
    $lockStmt->execute([$productId]);
    $prod = $lockStmt->fetch(PDO::FETCH_ASSOC);

    if (!$prod) {
        $db->rollBack();
        header("Location: index.php?error=" . urlencode("Product not found."));
        exit;
    }

    $currentStock = (int)$prod['stock_quantity'];
    $newStock = $currentStock;
    $change = 0;

    if ($type === 'add') {
        $change = $amount;
        $newStock = $currentStock + $amount;
    } elseif ($type === 'subtract') {
        $change = -$amount;
        $newStock = $currentStock - $amount;
    } elseif ($type === 'set') {
        $change = $amount - $currentStock;
        $newStock = $amount;
    } else {
        $db->rollBack();
        header("Location: index.php?error=" . urlencode("Invalid adjustment type specified."));
        exit;
    }

    // Prevent negative stock
    if ($newStock < 0) {
        $db->rollBack();
        header("Location: index.php?error=" . urlencode("Stock cannot be reduced below zero. Current stock is {$currentStock}."));
        exit;
    }

    // If change is 0, nothing to update
    if ($change === 0) {
        $db->rollBack();
        header("Location: index.php?info=" . urlencode("No stock change resulted from adjustment."));
        exit;
    }

    // 1. Update products table
    $updateStmt = $db->prepare("UPDATE products SET stock_quantity = ?, updated_at = NOW() WHERE id = ?");
    $updateStmt->execute([$newStock, $productId]);

    // 2. Insert into inventory_movements
    $movStmt = $db->prepare("INSERT INTO inventory_movements (product_id, quantity_change, balance_after, reason, admin_id, note)
                            VALUES (?, ?, ?, ?, ?, ?)");
    $movStmt->execute([
        $productId,
        $change,
        $newStock,
        $reason,
        $admin['id'],
        $note ?: "Admin adjustment ({$type} " . abs($change) . ")"
    ]);

    // 3. Log to admin_activity_logs
    $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, entity_type, entity_id, details, ip_address)
                  VALUES (?, 'stock_adjustment', 'product', ?, ?, ?)")
       ->execute([
           $admin['id'],
           $productId,
           json_encode([
               'sku'           => $prod['sku'],
               'title'         => $prod['title'],
               'old_stock'     => $currentStock,
               'new_stock'     => $newStock,
               'change'        => $change,
               'reason'        => $reason,
               'note'          => $note
           ]),
           $_SERVER['REMOTE_ADDR'] ?? ''
       ]);

    $db->commit();

    $signedChange = $change > 0 ? "+{$change}" : (string)$change;
    $msg = "Stock for \"{$prod['title']}\" adjusted ({$signedChange}). New balance: {$newStock} unit(s).";
    header("Location: index.php?success=" . urlencode($msg));
    exit;

} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Stock adjustment error: " . $e->getMessage());
    header("Location: index.php?error=" . urlencode("Stock adjustment failed: " . $e->getMessage()));
    exit;
}
