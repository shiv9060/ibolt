<?php
declare(strict_types=1);

/**
 * Payment Verification & Inventory Fulfillment Controller
 * iboltnetworks E-Commerce Platform
 */

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/auth.php';
require_once __DIR__ . '/app/helpers/csrf.php';
require_once __DIR__ . '/app/helpers/sanitize.php';
require_once __DIR__ . '/app/helpers/currency.php';

init_session();
$db = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    die("Security verification failed. Please try again.");
}

$orderId           = sanitize_int($_POST['order_id'] ?? 0);
$razorpayOrderId   = clean_string($_POST['razorpay_order_id'] ?? '');
$razorpayPaymentId = clean_string($_POST['razorpay_payment_id'] ?? '');
$razorpaySignature = clean_string($_POST['razorpay_signature'] ?? '');

$stmt = $db->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    die("Order record could not be found.");
}

// Verify payment signature
$keySecret = env('RAZORPAY_KEY_SECRET', 'placeholder_secret_key');
$isTestSimulator = empty($keySecret) || str_contains($keySecret, 'placeholder');

$signatureValid = false;

if ($isTestSimulator) {
    // In dev simulation, any simulation signature is approved
    $signatureValid = !empty($razorpayPaymentId);
} else {
    // Cryptographic HMAC-SHA256 signature verification per Razorpay spec
    $expectedSignature = hash_hmac('sha256', $razorpayOrderId . '|' . $razorpayPaymentId, $keySecret);
    $signatureValid = hash_equals($expectedSignature, $razorpaySignature);
}

if (!$signatureValid) {
    // Record failed payment attempt
    $failStmt = $db->prepare("UPDATE payments SET status = 'failed', error_description = 'HMAC signature mismatch' WHERE order_id = ? AND razorpay_order_id = ?");
    $failStmt->execute([$orderId, $razorpayOrderId]);

    $orderFailStmt = $db->prepare("UPDATE orders SET payment_status = 'failed' WHERE id = ?");
    $orderFailStmt->execute([$orderId]);

    die("Payment signature verification failed. Please contact support if your account was charged.");
}

try {
    $db->beginTransaction();

    // 1. Update Payment Record to Captured
    $payUpdate = $db->prepare("UPDATE payments SET razorpay_payment_id = ?, razorpay_signature = ?, status = 'captured', raw_response = ? WHERE order_id = ? AND razorpay_order_id = ?");
    $payUpdate->execute([
        $razorpayPaymentId,
        $razorpaySignature,
        json_encode(['payment_id' => $razorpayPaymentId, 'verified_at' => date('Y-m-d H:i:s')]),
        $orderId,
        $razorpayOrderId
    ]);

    // 2. Update Order Status to Processing & Paid
    $orderUpdate = $db->prepare("UPDATE orders SET status = 'processing', payment_status = 'paid' WHERE id = ?");
    $orderUpdate->execute([$orderId]);

    // 3. Deduct Inventory & Record Movement Audit Logs
    $itemsStmt = $db->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
    $itemsStmt->execute([$orderId]);
    $orderItems = $itemsStmt->fetchAll();

    foreach ($orderItems as $item) {
        if (!empty($item['product_id'])) {
            $pId = (int)$item['product_id'];
            $qty = (int)$item['quantity'];

            // Deduct stock
            $deductStmt = $db->prepare("UPDATE products SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ?");
            $deductStmt->execute([$qty, $pId]);

            // Get new balance
            $balStmt = $db->prepare("SELECT stock_quantity FROM products WHERE id = ?");
            $balStmt->execute([$pId]);
            $newBal = (int)$balStmt->fetchColumn();

            // Record movement
            $movStmt = $db->prepare("INSERT INTO inventory_movements (product_id, quantity_change, balance_after, reason, reference_id, note)
                                     VALUES (?, ?, ?, 'order_placed', ?, 'Customer checkout order')");
            $movStmt->execute([$pId, -$qty, $newBal, $order['order_number']]);
        }
    }

    $db->commit();

    // 4. Clear Shopping Cart & Pending Order Session
    $_SESSION['cart'] = [];
    unset($_SESSION['pending_order_id'], $_SESSION['pending_order_number']);

    header("Location: order-success.php?order_number=" . urlencode($order['order_number']));
    exit;

} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    die("An unexpected error occurred while processing fulfillment: " . $e->getMessage());
}
