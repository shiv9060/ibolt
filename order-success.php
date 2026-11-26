<?php
declare(strict_types=1);

/**
 * Order Success & Invoice Confirmation View
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

$orderNumber = clean_string($_GET['order_number'] ?? '');

if (empty($orderNumber)) {
    header("Location: index.php");
    exit;
}

$stmt = $db->prepare("SELECT * FROM orders WHERE order_number = ? LIMIT 1");
$stmt->execute([$orderNumber]);
$order = $stmt->fetch();

if (!$order) {
    header("Location: index.php");
    exit;
}

// Fetch order items
$itemsStmt = $db->prepare("SELECT oi.*,
                                  (SELECT image_path FROM product_images WHERE product_id = oi.product_id AND is_primary = 1 LIMIT 1) AS primary_image
                           FROM order_items oi
                           WHERE oi.order_id = ?");
$itemsStmt->execute([$order['id']]);
$items = $itemsStmt->fetchAll();

$shippingAddress = [];
if (!empty($order['shipping_address_snapshot'])) {
    $shippingAddress = json_decode($order['shipping_address_snapshot'], true) ?: [];
}

$pageTitle = "Order Confirmed #" . e($order['order_number']) . " | iboltnetworks";
$pageDescription = "Thank you for your order with iboltnetworks. Your hardware is being prepared for dispatch.";

$extraHead = <<<'HTML'
<style>
  .success-wrapper { background: #f6f8fa; padding: 50px 20px 80px; min-height: 650px; }
  .success-card {
    background: #ffffff;
    border: 1px solid #e1e7ec;
    border-radius: 14px;
    padding: 40px;
    max-width: 780px;
    margin: 0 auto;
    box-shadow: 0 6px 24px rgba(0,0,0,0.05);
  }
  .success-banner {
    text-align: center;
    border-bottom: 1px solid #edf2f6;
    padding-bottom: 28px;
    margin-bottom: 28px;
  }
  .success-icon {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: #dcfce7;
    color: #166534;
    font-size: 32px;
    display: grid;
    place-items: center;
    margin: 0 auto 16px;
  }
  .success-banner h1 { font-size: 26px; color: #1e293b; margin: 0 0 6px; font-weight: 800; }
  .success-banner p { font-size: 15px; color: #64748b; margin: 0; }

  .order-meta-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 28px;
    font-size: 13.5px;
  }
  .meta-col strong { display: block; color: #0f172a; margin-top: 4px; font-size: 14.5px; }

  .receipt-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13.5px;
    margin-bottom: 24px;
  }
  .receipt-table th {
    text-align: left;
    padding: 10px 14px;
    background: #f8fafc;
    border-bottom: 2px solid #e2e8f0;
    color: #475569;
    font-weight: 700;
  }
  .receipt-table td {
    padding: 14px;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
  }

  .totals-row {
    display: flex;
    justify-content: flex-end;
    font-size: 14px;
    margin-bottom: 8px;
  }
  .totals-row span:first-child { width: 140px; color: #64748b; }
  .totals-row span:last-child { width: 140px; text-align: right; font-weight: 700; color: #0f172a; }
  .totals-grand {
    font-size: 18px;
    border-top: 2px solid #cbd5e1;
    padding-top: 10px;
    margin-top: 10px;
  }

  .success-actions {
    display: flex;
    gap: 14px;
    justify-content: center;
    margin-top: 36px;
    padding-top: 24px;
    border-top: 1px solid #edf2f6;
    flex-wrap: wrap;
  }
  .btn-print {
    background: #f1f5f9;
    color: #334155;
    font-weight: 700;
    font-size: 14px;
    padding: 12px 24px;
    border-radius: 6px;
    border: 1px solid #cbd5e1;
    cursor: pointer;
  }
  .btn-print:hover { background: #e2e8f0; }

  @media print {
    body * { visibility: hidden; }
    .success-card, .success-card * { visibility: visible; }
    .success-card { position: absolute; left: 0; top: 0; width: 100%; border: none; box-shadow: none; padding: 0; }
    .success-actions, .announcement, .main-header, .main-nav, .site-footer, .whatsapp-float { display: none !important; }
  }

  @media (max-width: 768px) {
    .order-meta-grid { grid-template-columns: 1fr; }
  }
</style>
HTML;

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navigation.php';
?>

<div class="success-wrapper">
  <div class="success-card">
    
    <div class="success-banner">
      <div class="success-icon">✓</div>
      <h1>Order Confirmed &amp; Paid</h1>
      <p>Thank you for choosing iboltnetworks. Your hardware order is confirmed and being prepared for testing &amp; dispatch.</p>
    </div>

    <div class="order-meta-grid">
      <div class="meta-col">
        <span>Order Number:</span>
        <strong><?= e($order['order_number']) ?></strong>
      </div>
      <div class="meta-col">
        <span>Payment Status:</span>
        <strong style="color: #078e49;">✓ Paid (Razorpay)</strong>
      </div>
      <div class="meta-col">
        <span>Estimated Delivery:</span>
        <strong>3–5 Business Days</strong>
      </div>
    </div>

    <!-- Items Table -->
    <table class="receipt-table">
      <thead>
        <tr>
          <th>Item Description</th>
          <th>Unit Price</th>
          <th>Qty</th>
          <th style="text-align: right;">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $it): ?>
          <tr>
            <td>
              <strong><?= e($it['product_title']) ?></strong><br>
              <small style="color: #64748b;">SKU: <?= e($it['sku']) ?></small>
            </td>
            <td><?= format_inr((int)$it['price_paise']) ?></td>
            <td><?= (int)$it['quantity'] ?></td>
            <td style="text-align: right; font-weight: 700;"><?= format_inr((int)$it['total_paise']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- Totals -->
    <div style="max-width: 280px; margin-left: auto;">
      <div class="totals-row">
        <span>Subtotal</span>
        <span><?= format_inr((int)$order['subtotal_paise']) ?></span>
      </div>
      <div class="totals-row">
        <span>Insured Shipping</span>
        <span style="color: #078e49;">FREE</span>
      </div>
      <div class="totals-row">
        <span>GST / Taxes</span>
        <span>Included</span>
      </div>
      <div class="totals-row totals-grand">
        <span>Total Paid</span>
        <span><?= format_inr((int)$order['total_paise']) ?></span>
      </div>
    </div>

    <!-- Destination Address -->
    <?php if (!empty($shippingAddress)): ?>
      <div style="margin-top: 28px; padding: 18px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13.5px;">
        <strong style="display: block; margin-bottom: 6px; color: #1e293b;">Delivery Destination:</strong>
        <p style="margin: 0; color: #475569; line-height: 1.5;">
          <?= e($shippingAddress['full_name'] ?? '') ?> • <?= e($shippingAddress['phone'] ?? '') ?><br>
          <?= e($shippingAddress['address_line1'] ?? '') ?><?= !empty($shippingAddress['address_line2']) ? ', ' . e($shippingAddress['address_line2']) : '' ?><br>
          <?= e($shippingAddress['city'] ?? '') ?>, <?= e($shippingAddress['state'] ?? '') ?> - <?= e($shippingAddress['postal_code'] ?? '') ?>
        </p>
      </div>
    <?php endif; ?>

    <div class="success-actions">
      <a href="<?= e(build_whatsapp_url(build_order_whatsapp_message($order, $items, $shippingAddress))) ?>" target="_blank" rel="noopener noreferrer" style="display: inline-flex; align-items: center; gap: 8px; background: #25D366; color: #fff; padding: 12px 22px; border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 14px;">
        <svg style="width: 18px; height: 18px; fill: currentColor;" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.312.045-.694.06-2.072-.511-1.636-.679-2.73-2.316-2.812-2.426-.082-.109-.661-.88-.661-1.677 0-.797.413-1.189.56-1.348.146-.16.32-.2.427-.2.106 0 .213 0 .306.006.1.006.233-.038.365.279.133.319.453 1.106.493 1.187.04.08.067.174.013.281-.053.107-.08.174-.16.267-.08.093-.167.208-.239.28-.08.08-.163.167-.07.327.094.16.417.688.894 1.114.614.547 1.132.717 1.292.797.16.08.254.067.347-.04.094-.107.4-.467.507-.627.107-.16.213-.133.36-.08.147.053.934.44 1.094.52.16.08.267.12.307.187.04.066.04.386-.104.791z"/></svg>
        Chat on WhatsApp
      </a>

      <button type="button" class="btn-print" onclick="window.print()">
        🖨️ Print Receipt
      </button>

      <?php if (is_logged_in()): ?>
        <a href="account/orders.php" class="btn-quote" style="display: inline-block; background: #078e49; color: #fff; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 14px;">
          View in My Account →
        </a>
      <?php else: ?>
        <a href="products.php" class="btn-quote" style="display: inline-block; background: #078e49; color: #fff; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 700; font-size: 14px;">
          Continue Shopping →
        </a>
      <?php endif; ?>
    </div>

  </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
