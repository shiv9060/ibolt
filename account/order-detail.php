<?php
declare(strict_types=1);

/**
 * Customer Order Detail & Invoice View
 * iboltnetworks E-Commerce Platform
 */

$rootPath = '../';

require_once __DIR__ . '/../app/config/config.php';
require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/auth.php';
require_once __DIR__ . '/../app/helpers/csrf.php';
require_once __DIR__ . '/../app/helpers/sanitize.php';
require_once __DIR__ . '/../app/helpers/currency.php';

init_session();
require_login('../login.php');

$user = current_user();
$orderId = sanitize_int($_GET['id'] ?? 0);

$db = Database::getConnection();

// Fetch order verifying ownership
$orderStmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
$orderStmt->execute([$orderId, $user['id']]);
$order = $orderStmt->fetch();

if (!$order) {
    header("Location: orders.php");
    exit;
}

// Fetch order items
$itemsStmt = $db->prepare("SELECT oi.*,
                                  (SELECT image_path FROM product_images WHERE product_id = oi.product_id AND is_primary = 1 LIMIT 1) AS primary_image
                           FROM order_items oi
                           WHERE oi.order_id = ?");
$itemsStmt->execute([$order['id']]);
$items = $itemsStmt->fetchAll();

// Decode shipping address snapshot
$shippingAddress = [];
if (!empty($order['shipping_address_snapshot'])) {
    $shippingAddress = json_decode($order['shipping_address_snapshot'], true) ?: [];
}

$pageTitle = "Order #" . e($order['order_number']) . " Details | iboltnetworks";
$pageDescription = "View invoice and shipment tracking details for order #" . e($order['order_number']);

$extraHead = <<<'HTML'
<style>
  .dashboard-wrapper { background: #f6f8fa; padding: 40px 0 80px; min-height: 600px; }
  .order-detail-card {
    background: #ffffff;
    border: 1px solid #e1e7ec;
    border-radius: 12px;
    padding: 36px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.04);
    margin-bottom: 24px;
  }
  .order-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 24px;
    margin-bottom: 28px;
    flex-wrap: wrap;
    gap: 16px;
  }
  .order-header h1 { font-size: 24px; font-weight: 800; color: #1e293b; margin: 0 0 6px; }
  .order-header p { font-size: 13.5px; color: #64748b; margin: 0; }
  
  .order-grid-info {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
    margin-bottom: 32px;
  }
  .info-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 18px;
  }
  .info-box h3 { font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin: 0 0 8px; }
  .info-box p { font-size: 14px; color: #1e293b; margin: 0; line-height: 1.5; }

  .items-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
    margin-bottom: 30px;
  }
  .items-table th {
    background: #f8fafc;
    padding: 12px 16px;
    border-bottom: 2px solid #e2e8f0;
    color: #475569;
    text-align: left;
    font-weight: 700;
  }
  .items-table td {
    padding: 16px;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
  }
  .item-title { font-weight: 700; color: #111; }
  .item-sku { font-size: 12px; color: #64748b; }

  .summary-box {
    width: 320px;
    margin-left: auto;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 20px;
  }
  .summary-row {
    display: flex;
    justify-content: space-between;
    font-size: 14px;
    color: #475569;
    margin-bottom: 10px;
  }
  .summary-total {
    border-top: 2px solid #cbd5e1;
    padding-top: 12px;
    margin-top: 12px;
    font-size: 18px;
    font-weight: 800;
    color: #0f172a;
  }

  .badge-status { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
  .status-paid { background: #dcfce7; color: #166534; }
  .status-unpaid { background: #fef3c7; color: #92400e; }
  .status-pending { background: #f1f5f9; color: #475569; }
  .status-processing { background: #e0f2fe; color: #0369a1; }
  .status-shipped { background: #ede9fe; color: #6d28d9; }
  .status-delivered { background: #dcfce7; color: #166534; }

  @media (max-width: 800px) {
    .order-grid-info { grid-template-columns: 1fr; }
    .summary-box { width: 100%; margin: 0; }
  }
</style>
HTML;

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navigation.php';
?>

<div class="dashboard-wrapper">
  <div class="catalog-container">

    <div style="margin-bottom: 20px;">
      <a href="orders.php" style="color: #078e49; font-weight: 700; text-decoration: none; font-size: 14px;">
        ← Back to Order History
      </a>
    </div>

    <div class="order-detail-card">
      <div class="order-header">
        <div>
          <h1>Order #<?= e($order['order_number']) ?></h1>
          <p>Placed on <?= date('F j, Y \a\t g:i A', strtotime($order['created_at'])) ?></p>
        </div>
        <div style="display: flex; gap: 10px;">
          <span class="badge-status status-<?= e($order['payment_status']) ?>">
            Payment: <?= e(ucfirst($order['payment_status'])) ?>
          </span>
          <span class="badge-status status-<?= e($order['status']) ?>">
            Fulfillment: <?= e(ucfirst($order['status'])) ?>
          </span>
        </div>
      </div>

      <div class="order-grid-info">
        <div class="info-box">
          <h3>Customer Details</h3>
          <p>
            <strong><?= e($order['customer_name']) ?></strong><br>
            <?= e($order['customer_email']) ?><br>
            <?= e($order['customer_phone']) ?>
          </p>
        </div>

        <div class="info-box">
          <h3>Shipping Address</h3>
          <?php if (!empty($shippingAddress)): ?>
            <p>
              <?= e($shippingAddress['address_line1'] ?? '') ?><br>
              <?php if (!empty($shippingAddress['address_line2'])): ?>
                <?= e($shippingAddress['address_line2']) ?><br>
              <?php endif; ?>
              <?= e($shippingAddress['city'] ?? '') ?>, <?= e($shippingAddress['state'] ?? '') ?> - <?= e($shippingAddress['postal_code'] ?? '') ?><br>
              <?= e($shippingAddress['country'] ?? 'India') ?>
            </p>
          <?php else: ?>
            <p>On file with sales department</p>
          <?php endif; ?>
        </div>

        <div class="info-box">
          <h3>Shipment &amp; Tracking</h3>
          <?php if (!empty($order['tracking_number'])): ?>
            <p>
              Carrier: <strong><?= e($order['tracking_carrier'] ?? 'BlueDart / Delhivery') ?></strong><br>
              Tracking #: <strong><?= e($order['tracking_number']) ?></strong>
            </p>
          <?php else: ?>
            <p>Dispatched via insured freight upon quality check inspection.</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Items Table -->
      <table class="items-table">
        <thead>
          <tr>
            <th>Product</th>
            <th>Unit Price</th>
            <th>Quantity</th>
            <th style="text-align: right;">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <tr>
              <td>
                <div class="item-title"><?= e($item['product_title']) ?></div>
                <div class="item-sku">SKU: <?= e($item['sku']) ?></div>
              </td>
              <td><?= format_inr((int)$item['price_paise']) ?></td>
              <td><?= (int)$item['quantity'] ?></td>
              <td style="text-align: right; font-weight: 700;">
                <?= format_inr((int)$item['total_paise']) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <!-- Order Summary -->
      <div class="summary-box">
        <div class="summary-row">
          <span>Subtotal</span>
          <span><?= format_inr((int)$order['subtotal_paise']) ?></span>
        </div>
        <div class="summary-row">
          <span>Shipping &amp; Handling</span>
          <span><?= ($order['shipping_paise'] > 0) ? format_inr((int)$order['shipping_paise']) : 'FREE' ?></span>
        </div>
        <div class="summary-row">
          <span>Estimated Taxes (GST)</span>
          <span>Included</span>
        </div>
        <div class="summary-row summary-total">
          <span>Total</span>
          <span><?= format_inr((int)$order['total_paise']) ?></span>
        </div>
      </div>

    </div>

  </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
