<?php
declare(strict_types=1);

/**
 * Order Management, WhatsApp Communication & Status History Helper
 * iboltnetworks E-Commerce Platform
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/currency.php';
require_once __DIR__ . '/sanitize.php';

/**
 * Normalize and validate a customer phone number.
 * Supports standard 10-digit Indian numbers, leading zeros, and +91 prefixes.
 * Returns normalized digits (e.g. 919876543210) or null if invalid.
 */
function normalize_phone_number(?string $phone): ?string
{
    if (empty($phone)) {
        return null;
    }

    $clean = preg_replace('/[^0-9]/', '', (string)$phone);

    // 10-digit Indian local mobile number -> prepend country code 91
    if (strlen($clean) === 10) {
        return '91' . $clean;
    }

    // 11 digits starting with 0 (e.g. 09876543210) -> replace 0 with 91
    if (strlen($clean) === 11 && str_starts_with($clean, '0')) {
        return '91' . substr($clean, 1);
    }

    // 12 digits starting with 91 -> valid full Indian mobile number
    if (strlen($clean) === 12 && str_starts_with($clean, '91')) {
        return $clean;
    }

    // Generic international numbers (11 to 15 digits)
    if (strlen($clean) >= 11 && strlen($clean) <= 15) {
        return $clean;
    }

    // Invalid phone number (< 10 digits or incomplete)
    return null;
}

/**
 * Build a WhatsApp chat link for direct merchant-to-customer communication.
 * Validates the phone number and crafts a neutral, secure prefilled message.
 * Does NOT expose full customer address, payment tokens, or sensitive credentials.
 */
function build_customer_whatsapp_url(?string $phone, string $orderNumber, string $customerName): ?string
{
    $cleanPhone = normalize_phone_number($phone);
    if ($cleanPhone === null) {
        return null;
    }

    $name = trim($customerName) !== '' ? trim($customerName) : 'Customer';
    $ref = trim($orderNumber) !== '' ? trim($orderNumber) : 'your order';

    $lines = [
        "Hello {$name}, this is iboltnetworks regarding your Order #{$ref}.",
        "We are reaching out regarding your hardware order confirmation and delivery schedule.",
        "Please let us know if you need assistance, technical specifications, or an updated invoice copy."
    ];

    $message = implode("\n\n", $lines);

    return 'https://wa.me/' . $cleanPhone . '?text=' . rawurlencode($message);
}

/**
 * Get map of permitted order fulfillment status transitions.
 */
function get_allowed_order_transitions(): array
{
    return [
        'pending'    => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped'    => ['delivered', 'cancelled'],
        'delivered'  => ['refunded'],
        'cancelled'  => ['pending'], // allow reopen with administrative justification
        'refunded'   => []          // terminal status
    ];
}

/**
 * Verify whether a requested status transition is permitted.
 */
function is_valid_order_transition(string $currentStatus, string $newStatus): bool
{
    if ($currentStatus === $newStatus) {
        return true;
    }

    $allowed = get_allowed_order_transitions();
    return in_array($newStatus, $allowed[$currentStatus] ?? [], true);
}

/**
 * Update order fulfillment details and record status history atomically.
 * Uses SELECT ... FOR UPDATE to prevent race conditions.
 */
function update_order_fulfillment(
    int $orderId,
    string $newStatus,
    string $trackingCarrier,
    string $trackingNumber,
    string $adminNotes,
    ?int $adminId = null,
    ?string $transitionReason = null
): array {
    $db = Database::getConnection();

    $validStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
    if (!in_array($newStatus, $validStatuses, true)) {
        return ['success' => false, 'error' => 'Invalid fulfillment status requested.'];
    }

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("SELECT id, status, payment_status, tracking_carrier, tracking_number, admin_notes
                              FROM orders WHERE id = ? FOR UPDATE");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            $db->rollBack();
            return ['success' => false, 'error' => 'Order record not found.'];
        }

        $currentStatus = $order['status'];
        $statusChanged = ($currentStatus !== $newStatus);

        if ($statusChanged && !is_valid_order_transition($currentStatus, $newStatus)) {
            $db->rollBack();
            return [
                'success' => false,
                'error' => "Transition from '{$currentStatus}' to '{$newStatus}' is not allowed by order lifecycle rules."
            ];
        }

        // Update orders table
        $updateStmt = $db->prepare("UPDATE orders SET
                                      status = ?,
                                      tracking_carrier = ?,
                                      tracking_number = ?,
                                      admin_notes = ?,
                                      updated_at = NOW()
                                    WHERE id = ?");
        $updateStmt->execute([
            $newStatus,
            $trackingCarrier,
            $trackingNumber,
            $adminNotes,
            $orderId
        ]);

        // Record status transition in order_status_history if status changed
        if ($statusChanged) {
            $note = !empty($transitionReason) ? $transitionReason : "Status changed from {$currentStatus} to {$newStatus}";
            $histStmt = $db->prepare("INSERT INTO order_status_history
                                      (order_id, previous_status, new_status, admin_id, reason_or_notes, created_at)
                                      VALUES (?, ?, ?, ?, ?, NOW())");
            $histStmt->execute([
                $orderId,
                $currentStatus,
                $newStatus,
                $adminId,
                $note
            ]);
        }

        // Record activity log
        $activityDetails = [
            'previous_status'  => $currentStatus,
            'new_status'       => $newStatus,
            'status_changed'   => $statusChanged,
            'tracking_carrier' => $trackingCarrier,
            'tracking_number'  => $trackingNumber,
            'reason'           => $transitionReason
        ];

        $logStmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, entity_type, entity_id, details, created_at)
                                 VALUES (?, 'order_fulfillment_update', 'order', ?, ?, NOW())");
        $logStmt->execute([
            $adminId,
            $orderId,
            json_encode($activityDetails)
        ]);

        $db->commit();

        return [
            'success'        => true,
            'status_changed' => $statusChanged,
            'previous_status'=> $currentStatus,
            'new_status'     => $newStatus
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['success' => false, 'error' => 'Database error while updating order: ' . $e->getMessage()];
    }
}

/**
 * Update order payment status independently with row locking and audit logging.
 * Never automatically flips fulfillment status.
 */
function update_order_payment_status(
    int $orderId,
    string $newPaymentStatus,
    ?string $paymentNote = null,
    ?int $adminId = null
): array {
    $db = Database::getConnection();

    $validPayStatuses = ['unpaid', 'paid', 'failed', 'refunded'];
    if (!in_array($newPaymentStatus, $validPayStatuses, true)) {
        return ['success' => false, 'error' => 'Invalid payment status value.'];
    }

    try {
        $db->beginTransaction();

        $stmt = $db->prepare("SELECT id, payment_status, status FROM orders WHERE id = ? FOR UPDATE");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            $db->rollBack();
            return ['success' => false, 'error' => 'Order record not found.'];
        }

        $oldPaymentStatus = $order['payment_status'];
        $paymentChanged = ($oldPaymentStatus !== $newPaymentStatus);

        if ($paymentChanged) {
            $updateStmt = $db->prepare("UPDATE orders SET payment_status = ?, updated_at = NOW() WHERE id = ?");
            $updateStmt->execute([$newPaymentStatus, $orderId]);

            // Record in order status history as payment transition
            $note = !empty($paymentNote) ? $paymentNote : "Payment status updated from {$oldPaymentStatus} to {$newPaymentStatus}";
            $histStmt = $db->prepare("INSERT INTO order_status_history
                                      (order_id, previous_status, new_status, admin_id, reason_or_notes, created_at)
                                      VALUES (?, ?, ?, ?, ?, NOW())");
            $histStmt->execute([
                $orderId,
                'payment:' . $oldPaymentStatus,
                'payment:' . $newPaymentStatus,
                $adminId,
                $note
            ]);

            // Log activity
            $logStmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, entity_type, entity_id, details, created_at)
                                     VALUES (?, 'order_payment_update', 'order', ?, ?, NOW())");
            $logStmt->execute([
                $adminId,
                $orderId,
                json_encode([
                    'old_payment_status' => $oldPaymentStatus,
                    'new_payment_status' => $newPaymentStatus,
                    'notes'              => $paymentNote
                ])
            ]);
        }

        $db->commit();

        return [
            'success'            => true,
            'payment_changed'    => $paymentChanged,
            'old_payment_status' => $oldPaymentStatus,
            'new_payment_status' => $newPaymentStatus
        ];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['success' => false, 'error' => 'Database error updating payment status: ' . $e->getMessage()];
    }
}

/**
 * Retrieve chronological status history timeline for an order.
 */
function get_order_status_history(int $orderId): array
{
    $db = Database::getConnection();
    $stmt = $db->prepare("SELECT osh.*,
                                 au.username AS admin_username,
                                 au.name AS admin_name
                          FROM order_status_history osh
                          LEFT JOIN admin_users au ON osh.admin_id = au.id
                          WHERE osh.order_id = ?
                          ORDER BY osh.created_at ASC, osh.id ASC");
    $stmt->execute([$orderId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetch full order data for inspection, fulfillment, and receipt printing.
 */
function get_order_receipt_data(int $orderId): ?array
{
    $db = Database::getConnection();

    $stmt = $db->prepare("SELECT o.*,
                                 u.email AS user_account_email
                          FROM orders o
                          LEFT JOIN users u ON o.user_id = u.id
                          WHERE o.id = ? LIMIT 1");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        return null;
    }

    // Fetch order items (locked historical snapshots)
    $itemsStmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC");
    $itemsStmt->execute([$orderId]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode shipping address snapshot
    $address = [];
    if (!empty($order['shipping_address_snapshot'])) {
        $address = json_decode((string)$order['shipping_address_snapshot'], true) ?: [];
    }

    // Compute / verify calculated line totals
    $computedSubtotal = 0;
    foreach ($items as &$item) {
        $unitPrice = (int)($item['price_paise'] ?? 0);
        $qty = (int)($item['quantity'] ?? 1);
        $lineTotal = (int)($item['total_paise'] ?? ($unitPrice * $qty));
        $item['line_total_calc'] = $lineTotal;
        $computedSubtotal += $lineTotal;
    }
    unset($item);

    return [
        'order'             => $order,
        'items'             => $items,
        'shipping_address'  => $address,
        'computed_subtotal' => $computedSubtotal,
        'business'          => [
            'name'          => 'iboltnetworks',
            'division'      => 'Enterprise Server & Datacenter Hardware Division',
            'phone'         => '+91 99110 03865',
            'whatsapp'      => '+91 99110 03865',
            'email'         => 'sales@iboltnetworks.com',
            'website'       => 'www.iboltnetworks.com',
            'address'       => 'New Delhi, India',
            'receipt_type'  => 'Order Receipt & Hardware Delivery Slip'
        ]
    ];
}
