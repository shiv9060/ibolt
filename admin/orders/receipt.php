<?php
declare(strict_types=1);

/**
 * Print-Ready Order Receipt & Delivery Summary
 * iboltnetworks E-Commerce Platform
 */

$adminRoot = '../';

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/config/database.php';
require_once __DIR__ . '/../../app/helpers/auth.php';
require_once __DIR__ . '/../../app/helpers/sanitize.php';
require_once __DIR__ . '/../../app/helpers/currency.php';
require_once __DIR__ . '/../../app/helpers/orders.php';

init_session();
require_admin();
$admin = current_admin();

$orderId = sanitize_int($_GET['id'] ?? 0);
$data = get_order_receipt_data($orderId);

if (!$data) {
    header("Location: index.php");
    exit;
}

$order = $data['order'];
$items = $data['items'];
$shippingAddress = $data['shipping_address'];
$business = $data['business'];

$pageTitle = "Receipt #" . e($order['order_number']) . " | iboltnetworks";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?></title>
  <style>
    *, *::before, *::after {
      box-sizing: border-box;
    }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      color: #0f172a;
      background: #f8fafc;
      margin: 0;
      padding: 30px 20px;
      font-size: 13.5px;
      line-height: 1.5;
    }
    .receipt-container {
      max-width: 820px;
      margin: 0 auto;
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 36px 40px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    .no-print-bar {
      max-width: 820px;
      margin: 0 auto 16px auto;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 16px;
      font-size: 13.5px;
      font-weight: 600;
      border-radius: 6px;
      text-decoration: none;
      cursor: pointer;
      border: 1px solid transparent;
      transition: all 0.2s ease;
    }
    .btn-primary {
      background: #078e49;
      color: #ffffff;
    }
    .btn-primary:hover {
      background: #06723b;
    }
    .btn-secondary {
      background: #ffffff;
      color: #334155;
      border-color: #cbd5e1;
    }
    .btn-secondary:hover {
      background: #f1f5f9;
    }
    .header-row {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      border-bottom: 2px solid #078e49;
      padding-bottom: 20px;
      margin-bottom: 24px;
    }
    .store-brand h1 {
      margin: 0 0 4px 0;
      font-size: 24px;
      font-weight: 800;
      color: #078e49;
      letter-spacing: -0.5px;
    }
    .store-brand p {
      margin: 0;
      font-size: 12.5px;
      color: #64748b;
    }
    .doc-meta {
      text-align: right;
    }
    .doc-meta h2 {
      margin: 0 0 6px 0;
      font-size: 18px;
      font-weight: 800;
      color: #0f172a;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .doc-meta .order-ref {
      font-size: 14px;
      font-weight: 700;
      color: #078e49;
      font-family: monospace;
    }
    .info-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 24px;
      margin-bottom: 24px;
      padding: 16px;
      background: #f8fafc;
      border-radius: 6px;
      border: 1px solid #e2e8f0;
    }
    .info-box h3 {
      margin: 0 0 8px 0;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #64748b;
      font-weight: 700;
    }
    .info-box p {
      margin: 0;
      line-height: 1.6;
      color: #1e293b;
    }
    table.receipt-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 24px;
    }
    table.receipt-table th {
      background: #f1f5f9;
      color: #334155;
      font-weight: 700;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      padding: 10px 12px;
      border-bottom: 1px solid #cbd5e1;
      text-align: left;
    }
    table.receipt-table td {
      padding: 12px;
      border-bottom: 1px solid #e2e8f0;
      vertical-align: top;
    }
    table.receipt-table th.num,
    table.receipt-table td.num {
      text-align: right;
    }
    .badge {
      display: inline-block;
      padding: 3px 8px;
      border-radius: 4px;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
    }
    .badge-paid { background: #dcfce7; color: #166534; }
    .badge-unpaid { background: #fef9c3; color: #854d0e; }
    .badge-fulfilled { background: #e0e7ff; color: #3730a3; }
    .totals-area {
      display: flex;
      justify-content: flex-end;
      margin-bottom: 24px;
    }
    .totals-table {
      width: 320px;
    }
    .totals-table .line {
      display: flex;
      justify-content: space-between;
      padding: 6px 0;
      font-size: 13.5px;
      color: #475569;
    }
    .totals-table .grand-total {
      display: flex;
      justify-content: space-between;
      padding: 12px 0 6px 0;
      margin-top: 6px;
      border-top: 2px solid #0f172a;
      font-size: 17px;
      font-weight: 800;
      color: #0f172a;
    }
    .disclaimer-box {
      border-top: 1px dashed #cbd5e1;
      padding-top: 16px;
      font-size: 11.5px;
      color: #64748b;
      line-height: 1.6;
    }

    @media print {
      body {
        background: #ffffff;
        padding: 0;
        margin: 0;
        color: #000000;
        font-size: 12px;
      }
      .no-print-bar {
        display: none !important;
      }
      .receipt-container {
        border: none;
        box-shadow: none;
        padding: 0;
        max-width: 100%;
      }
      .info-grid {
        background: #ffffff;
        border: 1px solid #cccccc;
      }
      table.receipt-table th {
        background: #f1f5f9 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
      .badge {
        border: 1px solid #999999;
      }
      @page {
        margin: 12mm;
        size: A4 portrait;
      }
    }
  </style>
</head>
<body>

  <!-- Screen-only top action bar -->
  <div class="no-print-bar">
    <a href="view.php?id=<?= (int)$order['id'] ?>" class="btn btn-secondary">
      ← Back to Order Details
    </a>
    <div style="display: flex; gap: 8px;">
      <button type="button" onclick="window.print()" class="btn btn-primary">
        🖨️ Print Receipt
      </button>
    </div>
  </div>

  <div class="receipt-container">

    <!-- Store & Document Header -->
    <div class="header-row">
      <div class="store-brand">
        <h1><?= e($business['name']) ?></h1>
        <p><?= e($business['division']) ?></p>
        <p>Tel / WhatsApp: <?= e($business['phone']) ?> &bull; Email: <?= e($business['email']) ?></p>
        <p>Web: <?= e($business['website']) ?> &bull; <?= e($business['address']) ?></p>
      </div>
      <div class="doc-meta">
        <h2><?= e($business['receipt_type']) ?></h2>
        <div class="order-ref">Order #<?= e($order['order_number']) ?></div>
        <p style="margin: 4px 0 0 0; color: #64748b; font-size: 12.5px;">
          Date: <strong><?= date('d M Y, h:i A', strtotime($order['created_at'])) ?> (IST)</strong>
        </p>
      </div>
    </div>

    <!-- Customer and Shipping Grid -->
    <div class="info-grid">
      <div class="info-box">
        <h3>Customer Information</h3>
        <p>
          <strong><?= e($order['customer_name']) ?></strong><br>
          Email: <?= e($order['customer_email']) ?><br>
          Phone: <?= e($order['customer_phone'] ?? 'N/A') ?><br>
          Channel: <strong><?= strtoupper(e($order['payment_method'])) ?></strong>
        </p>
      </div>
      <div class="info-box">
        <h3>Delivery Destination</h3>
        <p>
          <?= e($shippingAddress['address_line1'] ?? '') ?><br>
          <?php if (!empty($shippingAddress['address_line2'])): ?>
            <?= e($shippingAddress['address_line2']) ?><br>
          <?php endif; ?>
          <?= e($shippingAddress['city'] ?? '') ?>, <?= e($shippingAddress['state'] ?? '') ?> - <?= e($shippingAddress['postal_code'] ?? '') ?><br>
          <?= e($shippingAddress['country'] ?? 'India') ?>
        </p>
      </div>
    </div>

    <!-- Order Status Summary Strip -->
    <div style="display: flex; gap: 16px; margin-bottom: 20px; padding: 10px 14px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px;">
      <div>
        <span style="color: #64748b; font-size: 12px;">Payment Status:</span>
        <?php if ($order['payment_status'] === 'paid'): ?>
          <span class="badge badge-paid">PAID (CONFIRMED)</span>
        <?php else: ?>
          <span class="badge badge-unpaid">UNPAID</span>
        <?php endif; ?>
      </div>
      <div>
        <span style="color: #64748b; font-size: 12px;">Fulfillment Status:</span>
        <span class="badge badge-fulfilled"><?= strtoupper(e($order['status'])) ?></span>
      </div>
      <?php if (!empty($order['tracking_number'])): ?>
        <div>
          <span style="color: #64748b; font-size: 12px;">Carrier &amp; Tracking:</span>
          <strong><?= e($order['tracking_carrier'] ?? 'Logistics') ?></strong> &bull; <code><?= e($order['tracking_number']) ?></code>
        </div>
      <?php endif; ?>
    </div>

    <!-- Itemized Hardware Table (Historical Locked Snapshot) -->
    <table class="receipt-table">
      <thead>
        <tr>
          <th style="width: 5%;">#</th>
          <th style="width: 50%;">Hardware Description &amp; SKU</th>
          <th class="num" style="width: 15%;">Unit Price</th>
          <th class="num" style="width: 10%;">Qty</th>
          <th class="num" style="width: 20%;">Line Total</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($items)): ?>
          <tr>
            <td colspan="5" style="text-align: center; color: #64748b; padding: 20px;">No line items found.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($items as $idx => $it): ?>
            <tr>
              <td><?= $idx + 1 ?></td>
              <td>
                <strong><?= e($it['product_title']) ?></strong><br>
                <code style="font-size: 11.5px; color: #475569;">SKU: <?= e($it['sku']) ?></code>
              </td>
              <td class="num"><?= format_inr((int)$it['price_paise']) ?></td>
              <td class="num"><?= (int)$it['quantity'] ?></td>
              <td class="num"><strong><?= format_inr((int)$it['total_paise']) ?></strong></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <!-- Stored Financial Totals -->
    <div class="totals-area">
      <div class="totals-table">
        <div class="line">
          <span>Subtotal:</span>
          <span><?= format_inr((int)$order['subtotal_paise']) ?></span>
        </div>
        <div class="line">
          <span>Shipping &amp; Logistics:</span>
          <span><?= (int)$order['shipping_paise'] === 0 ? 'FREE' : format_inr((int)$order['shipping_paise']) ?></span>
        </div>
        <?php if ((int)($order['tax_paise'] ?? 0) > 0): ?>
          <div class="line">
            <span>Taxes (Stored):</span>
            <span><?= format_inr((int)$order['tax_paise']) ?></span>
          </div>
        <?php endif; ?>
        <?php if ((int)($order['discount_paise'] ?? 0) > 0): ?>
          <div class="line">
            <span>Discount Applied:</span>
            <span>-<?= format_inr((int)$order['discount_paise']) ?></span>
          </div>
        <?php endif; ?>
        <div class="grand-total">
          <span>Grand Total:</span>
          <span><?= format_inr((int)$order['total_paise']) ?></span>
        </div>
      </div>
    </div>

    <!-- Notes & Legal Disclaimer -->
    <?php if (!empty($order['customer_notes'])): ?>
      <div style="margin-bottom: 20px; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
        <strong style="color: #64748b; font-size: 12px; display: block; margin-bottom: 4px;">Customer Order Notes / Registered GSTIN:</strong>
        <p style="margin: 0; font-size: 12.5px;"><?= nl2br(e($order['customer_notes'])) ?></p>
      </div>
    <?php endif; ?>

    <div class="disclaimer-box">
      <p style="margin: 0 0 4px 0;">
        <strong>Document Notice:</strong> This document represents a merchant order receipt and warehouse dispatch record for Order #<?= e($order['order_number']) ?>. All line item amounts reflect locked price snapshots recorded at the time of order placement.
      </p>
      <p style="margin: 0;">
        For commercial warranty inquiries, technical RMA support, or tax invoices, please contact iboltnetworks Enterprise Support at sales@iboltnetworks.com or +91 99110 03865.
      </p>
    </div>

  </div>

</body>
</html>
