<?php
declare(strict_types=1);

/**
 * Admin Order Inspection & Fulfillment Controller
 * iboltnetworks E-Commerce Platform
 */

$adminRoot = '../';

require_once __DIR__ . '/../includes/admin-header.php';
require_once __DIR__ . '/../../app/helpers/orders.php';

$db = Database::getConnection();

$orderId = sanitize_int($_GET['id'] ?? $_POST['id'] ?? 0);

$stmt = $db->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: index.php");
    exit;
}

$adminPageTitle = "Order #" . e($order['order_number']) . " | iboltnetworks Admin";
$success = '';
$error = '';

// Handle Fulfillment & Payment Submissions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Security session expired. Please re-submit.';
    } else {
        $action = clean_string($_POST['action'] ?? 'update_fulfillment');

        if ($action === 'update_fulfillment') {
            $newStatus         = clean_string($_POST['status'] ?? $order['status']);
            $trackingCarrier   = clean_string($_POST['tracking_carrier'] ?? '');
            $trackingNumber    = clean_string($_POST['tracking_number'] ?? '');
            $transitionReason  = clean_string($_POST['transition_reason'] ?? '');
            $adminNotes        = clean_string($_POST['admin_notes'] ?? '');

            $res = update_order_fulfillment(
                $orderId,
                $newStatus,
                $trackingCarrier,
                $trackingNumber,
                $adminNotes,
                (int)($admin['id'] ?? 0),
                $transitionReason
            );

            if ($res['success']) {
                $success = 'Order fulfillment and tracking details successfully updated.';
            } else {
                $error = $res['error'] ?? 'Failed to update order fulfillment.';
            }
        } elseif ($action === 'update_payment') {
            $newPaymentStatus = clean_string($_POST['payment_status'] ?? $order['payment_status']);
            $paymentNote      = clean_string($_POST['payment_note'] ?? '');

            $res = update_order_payment_status(
                $orderId,
                $newPaymentStatus,
                $paymentNote,
                (int)($admin['id'] ?? 0)
            );

            if ($res['success']) {
                $success = 'Order payment status successfully updated.';
            } else {
                $error = $res['error'] ?? 'Failed to update payment status.';
            }
        }

        // Refresh order record
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Fetch line items (locked snapshot)
$itemsStmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC");
$itemsStmt->execute([$orderId]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch payment transactions if any
$payStmt = $db->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC");
$payStmt->execute([$orderId]);
$payments = $payStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch order status history
$statusHistory = get_order_status_history($orderId);

$shippingAddress = [];
if (!empty($order['shipping_address_snapshot'])) {
    $shippingAddress = json_decode((string)$order['shipping_address_snapshot'], true) ?: [];
}

// Customer WhatsApp chat link
$customerWaUrl = build_customer_whatsapp_url(
    $order['customer_phone'] ?? '',
    $order['order_number'] ?? '',
    $order['customer_name'] ?? ''
);
?>

<style>
  .order-detail-layout {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 24px;
  }
  .order-address-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
  }
  @media (max-width: 960px) {
    .order-detail-layout {
      grid-template-columns: 1fr !important;
      gap: 18px;
    }
    .order-address-grid {
      grid-template-columns: 1fr !important;
      gap: 16px;
    }
    .order-header-actions {
      width: 100%;
      display: flex;
      gap: 8px;
    }
    .order-header-actions .btn {
      flex: 1;
      justify-content: center;
      min-height: 44px;
    }
  }
</style>

<div class="admin-container">

  <div class="admin-page-header">
    <div>
      <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px; flex-wrap: wrap;">
        <h1 style="margin: 0;">Order #<?= e($order['order_number']) ?></h1>
        <?php if ($order['payment_status'] === 'paid'): ?>
          <span class="badge badge-green" style="font-size: 13px;">● Paid</span>
        <?php elseif ($order['payment_status'] === 'refunded'): ?>
          <span class="badge badge-purple" style="font-size: 13px;">● Refunded</span>
        <?php else: ?>
          <span class="badge badge-yellow" style="font-size: 13px;">○ Unpaid</span>
        <?php endif; ?>

        <?php if ($order['status'] === 'delivered'): ?>
          <span class="badge badge-green" style="font-size: 13px;">Delivered</span>
        <?php elseif ($order['status'] === 'shipped'): ?>
          <span class="badge badge-purple" style="font-size: 13px;">Shipped</span>
        <?php elseif ($order['status'] === 'processing'): ?>
          <span class="badge badge-blue" style="font-size: 13px;">Processing</span>
        <?php elseif ($order['status'] === 'cancelled'): ?>
          <span class="badge badge-red" style="font-size: 13px;">Cancelled</span>
        <?php else: ?>
          <span class="badge badge-gray" style="font-size: 13px;"><?= e(ucfirst($order['status'])) ?></span>
        <?php endif; ?>
      </div>
      <p style="margin: 0; color: #64748b;">
        Placed on <strong><?= date('M d, Y h:i A', strtotime($order['created_at'])) ?> (IST)</strong> &bull;
        Channel: <strong><?= strtoupper(e($order['payment_method'])) ?></strong>
      </p>
    </div>

    <div class="order-header-actions" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
      <a href="receipt.php?id=<?= $orderId ?>" target="_blank" class="btn btn-secondary" style="font-weight: 600;">
        🖨️ Print Receipt
      </a>
      <a href="index.php" class="btn btn-secondary">
        ← Back to Orders
      </a>
    </div>
  </div>

  <?php if (!empty($success)): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
  <?php endif; ?>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
  <?php endif; ?>

  <!-- Two Column / Responsive Layout -->
  <div class="order-detail-layout">

    <!-- Left Column: Details, History, Items -->
    <div style="display: flex; flex-direction: column; gap: 24px;">

      <!-- WhatsApp Customer Communication Box -->
      <div class="admin-card" style="border-left: 4px solid #25D366;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 14px;">
          <div style="flex: 1; min-width: min(100%, 260px);">
            <h2 style="margin: 0 0 6px 0; font-size: 16px; display: flex; align-items: center; gap: 8px;">
              <span style="color: #25D366; font-size: 20px;">💬</span>
              Direct WhatsApp Customer Communication
            </h2>
            <p style="margin: 0; color: #475569; font-size: 13px;">
              Contact customer <strong><?= e($order['customer_name']) ?></strong> directly on WhatsApp for order confirmation, GSTIN requests, or delivery coordination.
            </p>
          </div>

          <div style="width: 100%; max-width: 240px;">
            <?php if ($customerWaUrl !== null): ?>
              <a href="<?= e($customerWaUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn" style="background: #25D366; color: #ffffff; font-weight: 700; padding: 12px 20px; border-radius: 8px; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 6px; width: 100%; min-height: 44px;">
                <span>💬 Open WhatsApp Chat</span>
                <span style="font-size: 12px; opacity: 0.85;">↗</span>
              </a>
            <?php else: ?>
              <span class="badge badge-gray" style="padding: 10px 14px; font-size: 12px; display: block; text-align: center;">
                ⚠️ Phone number invalid or missing
              </span>
            <?php endif; ?>
          </div>
        </div>

        <div style="margin-top: 12px; padding: 10px 14px; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0; font-size: 12px; color: #64748b;">
          <strong>Security &amp; Protocol Notice:</strong> Clicking "Open WhatsApp Chat" initiates a prefilled conversation in a new tab. Opening WhatsApp does NOT automatically send the message, nor does it constitute proof of payment or customer delivery.
        </div>
      </div>

      <!-- Ordered Hardware Items (Locked Snapshot) -->
      <div class="admin-card">
        <h2 style="margin-bottom: 16px;">Ordered Hardware Items (Locked Price Snapshot)</h2>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Product Description</th>
                <th>SKU</th>
                <th>Unit Price</th>
                <th>Qty</th>
                <th style="text-align: right;">Line Total</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $it): ?>
                <tr>
                  <td>
                    <strong><?= e($it['product_title']) ?></strong>
                  </td>
                  <td><code><?= e($it['sku']) ?></code></td>
                  <td><?= format_inr((int)$it['price_paise']) ?></td>
                  <td><?= (int)$it['quantity'] ?></td>
                  <td style="text-align: right; font-weight: 700;"><?= format_inr((int)$it['total_paise']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div style="max-width: 320px; margin-left: auto; margin-top: 20px;">
          <div style="display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 8px;">
            <span style="color: #64748b;">Subtotal:</span>
            <strong><?= format_inr((int)$order['subtotal_paise']) ?></strong>
          </div>
          <div style="display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 8px;">
            <span style="color: #64748b;">Shipping Charges:</span>
            <span style="color: #078e49; font-weight: 700;"><?= (int)$order['shipping_paise'] === 0 ? 'FREE' : format_inr((int)$order['shipping_paise']) ?></span>
          </div>
          <?php if ((int)($order['tax_paise'] ?? 0) > 0): ?>
            <div style="display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 8px;">
              <span style="color: #64748b;">Taxes (Stored):</span>
              <span><?= format_inr((int)$order['tax_paise']) ?></span>
            </div>
          <?php endif; ?>
          <div style="display: flex; justify-content: space-between; font-size: 16px; font-weight: 800; border-top: 2px solid #cbd5e1; padding-top: 10px; margin-top: 10px;">
            <span>Grand Total:</span>
            <span style="color: #078e49;"><?= format_inr((int)$order['total_paise']) ?></span>
          </div>
        </div>
      </div>

      <!-- Customer & Shipping Info -->
      <div class="admin-card">
        <h2>Customer &amp; Shipping Destination</h2>
        <div class="order-address-grid">
          <div>
            <h4 style="margin: 0 0 6px; font-size: 13px; color: #64748b; text-transform: uppercase;">Customer Contact</h4>
            <p style="margin: 0; font-size: 14.5px; line-height: 1.6;">
              <strong><?= e($order['customer_name']) ?></strong><br>
              Email: <a href="mailto:<?= e($order['customer_email']) ?>"><?= e($order['customer_email']) ?></a><br>
              Phone: <a href="tel:<?= e($order['customer_phone']) ?>"><?= e($order['customer_phone'] ?? 'N/A') ?></a>
            </p>
          </div>
          <div>
            <h4 style="margin: 0 0 6px; font-size: 13px; color: #64748b; text-transform: uppercase;">Shipping Address</h4>
            <p style="margin: 0; font-size: 14.5px; line-height: 1.6;">
              <?= e($shippingAddress['address_line1'] ?? '') ?><br>
              <?= !empty($shippingAddress['address_line2']) ? e($shippingAddress['address_line2']) . '<br>' : '' ?>
              <?= e($shippingAddress['city'] ?? '') ?>, <?= e($shippingAddress['state'] ?? '') ?> - <?= e($shippingAddress['postal_code'] ?? '') ?><br>
              <?= e($shippingAddress['country'] ?? 'India') ?>
            </p>
          </div>
        </div>

        <?php if (!empty($order['customer_notes'])): ?>
          <div style="margin-top: 20px; padding: 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
            <strong style="font-size: 13px; color: #64748b; display: block; margin-bottom: 4px;">Customer Notes / Registered GSTIN:</strong>
            <p style="margin: 0; font-size: 13.5px; color: #1e293b;"><?= nl2br(e($order['customer_notes'])) ?></p>
          </div>
        <?php endif; ?>
      </div>

      <!-- Order Status History Timeline -->
      <div class="admin-card">
        <h2>Order Status Lifecycle Timeline</h2>
        <?php if (empty($statusHistory)): ?>
          <p style="color: #64748b; font-size: 13.5px; margin: 0;">
            No lifecycle transitions recorded yet. Initial order registered in <strong><?= e(strtoupper($order['status'])) ?></strong> state.
          </p>
        <?php else: ?>
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th style="width: 170px;">Timestamp (IST)</th>
                  <th>Transition</th>
                  <th>Admin User</th>
                  <th>Reason / Notes</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($statusHistory as $hist): ?>
                  <tr>
                    <td style="font-size: 12.5px; white-space: nowrap;">
                      <?= date('M d, Y h:i A', strtotime($hist['created_at'])) ?>
                    </td>
                    <td>
                      <span class="badge badge-gray"><?= e($hist['previous_status']) ?></span>
                      <span style="color: #64748b; font-weight: bold;"> → </span>
                      <span class="badge badge-blue"><?= e($hist['new_status']) ?></span>
                    </td>
                    <td>
                      <?= e($hist['admin_name'] ?? $hist['admin_username'] ?? 'System / Admin') ?>
                    </td>
                    <td style="font-size: 13px; color: #334155;">
                      <?= e($hist['reason_or_notes'] ?? '') ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <!-- Gateway Payment Audit Trail if applicable -->
      <?php if (!empty($payments)): ?>
        <div class="admin-card">
          <h2>Payment Gateway Audit Trail</h2>
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Provider</th>
                  <th>Order ID</th>
                  <th>Payment ID</th>
                  <th>Amount</th>
                  <th>Status</th>
                  <th>Timestamp</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($payments as $pay): ?>
                  <tr>
                    <td><strong><?= e(strtoupper($pay['provider'])) ?></strong></td>
                    <td><code><?= e($pay['razorpay_order_id'] ?? 'N/A') ?></code></td>
                    <td><code><?= e($pay['razorpay_payment_id'] ?? 'Pending') ?></code></td>
                    <td><strong><?= format_inr((int)$pay['amount_paise']) ?></strong></td>
                    <td>
                      <span class="badge <?= $pay['status'] === 'captured' ? 'badge-green' : 'badge-yellow' ?>">
                        <?= e(ucfirst($pay['status'])) ?>
                      </span>
                    </td>
                    <td><?= date('M d, Y h:i A', strtotime($pay['created_at'])) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

    </div>

    <!-- Right Column: Separate Fulfillment & Payment Controls -->
    <div style="display: flex; flex-direction: column; gap: 24px;">

      <!-- Fulfillment Control Sidebar Card -->
      <aside class="admin-card">
        <h2 style="font-size: 17px; margin-bottom: 16px;">Fulfillment Control</h2>

        <form method="post" action="view.php?id=<?= $orderId ?>" onsubmit="return validateFulfillmentSubmit(this);">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= $orderId ?>">
          <input type="hidden" name="action" value="update_fulfillment">

          <div class="form-group">
            <label for="status">Order Fulfillment Status *</label>
            <select id="status" name="status" class="form-control" style="font-weight: 600;">
              <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending Review</option>
              <option value="processing" <?= $order['status'] === 'processing' ? 'selected' : '' ?>>Processing / Testing</option>
              <option value="shipped" <?= $order['status'] === 'shipped' ? 'selected' : '' ?>>Shipped (In Transit)</option>
              <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
              <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
              <option value="refunded" <?= $order['status'] === 'refunded' ? 'selected' : '' ?>>Refunded</option>
            </select>
            <small style="color: #64748b; display: block; margin-top: 4px;">
              Allowed progression: Pending → Processing → Shipped → Delivered.
            </small>
          </div>

          <div class="form-group">
            <label for="transition_reason">Status Transition Reason / Note</label>
            <input type="text" id="transition_reason" name="transition_reason" class="form-control" placeholder="e.g. Hardware testing passed, dispatched with BlueDart">
          </div>

          <div class="form-group">
            <label for="tracking_carrier">Logistics Carrier</label>
            <input type="text" id="tracking_carrier" name="tracking_carrier" class="form-control" value="<?= e($order['tracking_carrier'] ?? 'BlueDart Express') ?>" placeholder="e.g. BlueDart / Delhivery / SafeXpress">
          </div>

          <div class="form-group">
            <label for="tracking_number">Tracking Consignment #</label>
            <input type="text" id="tracking_number" name="tracking_number" class="form-control" value="<?= e($order['tracking_number'] ?? '') ?>" placeholder="e.g. BLU-982347102">
          </div>

          <div class="form-group">
            <label for="admin_notes">Internal Admin Notes</label>
            <textarea id="admin_notes" name="admin_notes" rows="3" class="form-control" placeholder="Internal notes for warehouse team, serial numbers dispatched, QA checklist..."><?= e($order['admin_notes'] ?? '') ?></textarea>
          </div>

          <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 11px;">
            Update Fulfillment Status
          </button>
        </form>
      </aside>

      <!-- Payment Reconciliation Card -->
      <aside class="admin-card" style="border-top: 3px solid #0284c7;">
        <h2 style="font-size: 17px; margin-bottom: 8px;">Payment Reconciliation</h2>
        <p style="font-size: 12.5px; color: #64748b; margin-top: 0; margin-bottom: 16px;">
          Payment status is strictly independent from fulfillment status. WhatsApp checkout orders remain <strong>Unpaid</strong> until confirmed by an administrator.
        </p>

        <form method="post" action="view.php?id=<?= $orderId ?>" onsubmit="return validatePaymentSubmit(this);">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= $orderId ?>">
          <input type="hidden" name="action" value="update_payment">

          <div class="form-group">
            <label for="payment_status">Confirmed Payment Status *</label>
            <select id="payment_status" name="payment_status" class="form-control" style="font-weight: 600;">
              <option value="unpaid" <?= $order['payment_status'] === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
              <option value="paid" <?= $order['payment_status'] === 'paid' ? 'selected' : '' ?>>Paid (Confirmed)</option>
              <option value="failed" <?= $order['payment_status'] === 'failed' ? 'selected' : '' ?>>Failed</option>
              <option value="refunded" <?= $order['payment_status'] === 'refunded' ? 'selected' : '' ?>>Refunded</option>
            </select>
          </div>

          <div class="form-group">
            <label for="payment_note">Reconciliation Reference / Notes</label>
            <input type="text" id="payment_note" name="payment_note" class="form-control" placeholder="e.g. Bank Transfer NEFT UTR / UPI Ref / Cash">
          </div>

          <button type="submit" class="btn btn-secondary" style="width: 100%; justify-content: center; padding: 10px; font-weight: 600;">
            Update Payment Status
          </button>
        </form>
      </aside>

    </div>

  </div>

</div>

<script>
function validateFulfillmentSubmit(form) {
  const currentStatus = <?= json_encode($order['status']) ?>;
  const newStatus = form.status.value;

  if (newStatus === 'cancelled' && currentStatus !== 'cancelled') {
    return confirm('Are you sure you want to CANCEL this order? This action will be recorded in the status timeline.');
  }
  if (newStatus === 'refunded' && currentStatus !== 'refunded') {
    return confirm('Are you sure you want to mark this order as REFUNDED?');
  }
  return true;
}

function validatePaymentSubmit(form) {
  const currentPay = <?= json_encode($order['payment_status']) ?>;
  const newPay = form.payment_status.value;

  if (newPay === 'paid' && currentPay !== 'paid') {
    return confirm('Confirm that payment has been received and verified for this order?');
  }
  if (newPay === 'refunded' && currentPay !== 'refunded') {
    return confirm('Confirm that the payment has been REFUNDED to the customer?');
  }
  return true;
}
</script>

<?php
require_once __DIR__ . '/../includes/admin-footer.php';
