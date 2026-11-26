<?php
declare(strict_types=1);

/**
 * Payment Gateway Bypass -> WhatsApp Order Dispatcher
 * Directly redirects checkout customers to WhatsApp with order & product info
 * iboltnetworks E-Commerce Platform
 */

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/auth.php';
require_once __DIR__ . '/app/helpers/csrf.php';
require_once __DIR__ . '/app/helpers/sanitize.php';
require_once __DIR__ . '/app/helpers/currency.php';
require_once __DIR__ . '/app/helpers/whatsapp.php';

init_session();
$db = Database::getConnection();

$orderId = sanitize_int($_GET['order_id'] ?? $_SESSION['pending_order_id'] ?? 0);

if ($orderId <= 0) {
    header("Location: index.php");
    exit;
}

// Fetch order
$stmt = $db->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    header("Location: index.php");
    exit;
}

// Fetch order items
$itemsStmt = $db->prepare("SELECT oi.*, p.title, p.sku FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
$itemsStmt->execute([$order['id']]);
$items = $itemsStmt->fetchAll();

$shippingAddress = [];
if (!empty($order['shipping_address_snapshot'])) {
    $shippingAddress = json_decode($order['shipping_address_snapshot'], true) ?: [];
}

// Build WhatsApp URL with full product information
$waMessage = build_order_whatsapp_message($order, $items, $shippingAddress);
$whatsappUrl = build_whatsapp_url($waMessage);

// Clear cart
$_SESSION['cart'] = [];

// Direct redirect to WhatsApp
header("Location: " . $whatsappUrl);
exit;
