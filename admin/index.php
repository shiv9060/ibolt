<?php
declare(strict_types=1);

/**
 * Admin Panel Dashboard & Homepage Management Hub
 * iboltnetworks E-Commerce Platform
 */

$adminRoot = '';
$adminPageTitle = 'Dashboard Analytics & Operations | iboltnetworks Admin';

require_once __DIR__ . '/includes/admin-header.php';

$dbError = null;

// Initialize safe default metrics
$activeProductCount = 0;
$inactiveProductCount = 0;
$outOfStockCount = 0;
$lowStockCount = 0;

$totalOrdersCount = 0;
$totalOrderValuePaise = 0;
$validOrdersCount = 0;
$confirmedRevenuePaise = 0;
$paidOrdersCount = 0;
$processingOrdersCount = 0;
$pendingWhatsappCount = 0;
$todayOrdersCount = 0;

$homepageProductsCount = 0;
$serversCount = 0;
$networkingCount = 0;
$componentsCount = 0;
$ourProductsCount = 0;
$featuredCount = 0;
$categoriesCount = 0;
$imagesCount = 0;

$recentOrders = [];
$recentMovements = [];
$lowStockProducts = [];

try {
    $db = Database::getConnection();

    // 1. Product & Inventory Metrics (Consolidated)
    // Strictly separates active vs inactive, and out-of-stock (stock <= 0) vs low-stock (stock > 0 and stock <= low_stock_threshold)
    $prodStmt = $db->query("SELECT 
        COUNT(CASE WHEN status = 'published' THEN 1 END) AS active_count,
        COUNT(CASE WHEN status != 'published' THEN 1 END) AS inactive_count,
        COUNT(CASE WHEN stock_quantity <= 0 THEN 1 END) AS out_of_stock_count,
        COUNT(CASE WHEN stock_quantity > 0 AND stock_quantity <= low_stock_threshold THEN 1 END) AS low_stock_count
    FROM products");
    if ($prodRow = $prodStmt->fetch(PDO::FETCH_ASSOC)) {
        $activeProductCount   = (int)($prodRow['active_count'] ?? 0);
        $inactiveProductCount = (int)($prodRow['inactive_count'] ?? 0);
        $outOfStockCount      = (int)($prodRow['out_of_stock_count'] ?? 0);
        $lowStockCount        = (int)($prodRow['low_stock_count'] ?? 0);
    }

    // 2. Order & Revenue Analytics (Consolidated)
    // - Confirmed Paid Revenue: strictly counts orders with payment_status = 'paid' (excludes pending WhatsApp orders)
    // - Total Order Value: includes all valid orders placed, excluding 'cancelled' and 'refunded'
    $orderStmt = $db->query("SELECT 
        COUNT(*) AS total_orders,
        COALESCE(SUM(CASE WHEN status NOT IN ('cancelled', 'refunded') THEN total_paise ELSE 0 END), 0) AS total_order_value_paise,
        COUNT(CASE WHEN status NOT IN ('cancelled', 'refunded') THEN 1 END) AS valid_orders_count,
        COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_paise ELSE 0 END), 0) AS confirmed_revenue_paise,
        COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) AS paid_orders_count,
        COUNT(CASE WHEN status = 'processing' THEN 1 END) AS processing_orders_count,
        COUNT(CASE WHEN payment_status = 'unpaid' AND payment_method = 'whatsapp' THEN 1 END) AS pending_whatsapp_count
    FROM orders");
    if ($orderRow = $orderStmt->fetch(PDO::FETCH_ASSOC)) {
        $totalOrdersCount      = (int)($orderRow['total_orders'] ?? 0);
        $totalOrderValuePaise  = (int)($orderRow['total_order_value_paise'] ?? 0);
        $validOrdersCount      = (int)($orderRow['valid_orders_count'] ?? 0);
        $confirmedRevenuePaise = (int)($orderRow['confirmed_revenue_paise'] ?? 0);
        $paidOrdersCount       = (int)($orderRow['paid_orders_count'] ?? 0);
        $processingOrdersCount = (int)($orderRow['processing_orders_count'] ?? 0);
        $pendingWhatsappCount  = (int)($orderRow['pending_whatsapp_count'] ?? 0);
    }

    // 3. Today's Orders in Asia/Kolkata
    // Calculate local midnight-to-midnight boundaries and query indexed created_at column
    $kolkataTz = new DateTimeZone('Asia/Kolkata');
    $todayStart = (new DateTime('today', $kolkataTz))->format('Y-m-d 00:00:00');
    $todayEnd   = (new DateTime('today', $kolkataTz))->format('Y-m-d 23:59:59');

    $todayStmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE created_at >= :today_start AND created_at <= :today_end");
    $todayStmt->execute([':today_start' => $todayStart, ':today_end' => $todayEnd]);
    $todayOrdersCount = (int)$todayStmt->fetchColumn();

    // 4. Homepage & Section Statistics (Consolidated)
    $secStmt = $db->query("SELECT 
        COUNT(DISTINCT product_id) AS homepage_products_count,
        COUNT(CASE WHEN section_key = 'servers' THEN 1 END) AS servers_count,
        COUNT(CASE WHEN section_key = 'networking' THEN 1 END) AS networking_count,
        COUNT(CASE WHEN section_key = 'components' THEN 1 END) AS components_count,
        COUNT(CASE WHEN section_key = 'our-products' THEN 1 END) AS our_products_count,
        COUNT(CASE WHEN section_key = 'featured' THEN 1 END) AS featured_count
    FROM homepage_section_products
    WHERE is_visible = 1");
    if ($secRow = $secStmt->fetch(PDO::FETCH_ASSOC)) {
        $homepageProductsCount = (int)($secRow['homepage_products_count'] ?? 0);
        $serversCount          = (int)($secRow['servers_count'] ?? 0);
        $networkingCount       = (int)($secRow['networking_count'] ?? 0);
        $componentsCount       = (int)($secRow['components_count'] ?? 0);
        $ourProductsCount      = (int)($secRow['our_products_count'] ?? 0);
        $featuredCount         = (int)($secRow['featured_count'] ?? 0);
    }

    $categoriesCount = (int)$db->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    $imagesCount = (int)$db->query("SELECT COUNT(*) FROM product_images")->fetchColumn();

    // 5. Fetch Recent Orders (Limit 6)
    $recentOrdersStmt = $db->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 6");
    $recentOrders = $recentOrdersStmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Fetch Recent Inventory Movements (Limit 6)
    $movStmt = $db->query("SELECT im.*,
                                  p.title AS product_title,
                                  p.sku AS product_sku,
                                  a.name AS admin_name,
                                  a.username AS admin_username
                           FROM inventory_movements im
                           LEFT JOIN products p ON im.product_id = p.id
                           LEFT JOIN admin_users a ON im.admin_id = a.id
                           ORDER BY im.created_at DESC
                           LIMIT 6");
    $recentMovements = $movStmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. Fetch Low Stock Items (stock_quantity > 0 AND stock_quantity <= low_stock_threshold)
    $lowStockStmt = $db->query("SELECT p.id, p.title, p.sku, p.stock_quantity, p.low_stock_threshold, b.name AS brand_name
                               FROM products p
                               LEFT JOIN brands b ON p.brand_id = b.id
                               WHERE p.stock_quantity > 0 AND p.stock_quantity <= p.low_stock_threshold AND p.status = 'published'
                               ORDER BY p.stock_quantity ASC
                               LIMIT 6");
    $lowStockProducts = $lowStockStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log("Admin dashboard database error: " . $e->getMessage());
    $dbError = "Real-time analytics could not be retrieved from the database. Please check your database connection.";
}
?>

<div class="admin-container">

  <div class="admin-page-header">
    <div>
      <h1>Platform Analytics &amp; Control</h1>
      <p>Real-time metrics, inventory alerts, orders, and storefront homepage management.</p>
    </div>
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
      <a href="profile.php" class="btn btn-secondary">👤 Staff Profile &amp; Password</a>
      <a href="products/form.php" class="btn btn-primary">+ Add New Product</a>
      <a href="../index.php" target="_blank" class="btn btn-secondary">Preview Storefront ↗</a>
    </div>
  </div>

  <?php if ($dbError !== null): ?>
    <div class="alert alert-error" role="alert">
      <strong>Notice:</strong> <?= e($dbError) ?>
    </div>
  <?php endif; ?>

  <!-- Financial & Order Velocity Cards (Row 1) -->
  <div style="margin-bottom: 8px;">
    <h3 style="font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; font-weight: 800; margin: 0 0 12px;">
      Financial &amp; Order Performance
    </h3>
  </div>
  <div class="admin-stats-grid" style="margin-bottom: 24px;">

    <!-- 1. Confirmed Paid Revenue -->
    <a href="orders/index.php?payment_status=paid" class="stat-box" aria-label="View confirmed paid orders" title="Click to filter paid orders">
      <span class="label">
        <span>💳 Confirmed Paid Revenue</span>
        <span style="font-size: 14px; color: #078e49;">→</span>
      </span>
      <span class="number" style="color: #078e49;"><?= format_inr($confirmedRevenuePaise) ?></span>
      <span class="sub"><?= $paidOrdersCount ?> verified paid order(s)</span>
      <span style="font-size: 11px; color: #64748b; margin-top: 4px;">Excludes pending WhatsApp &amp; unpaid orders</span>
      <span class="card-click-hint">Filter Paid Orders →</span>
    </a>

    <!-- 2. Total Order Value -->
    <a href="orders/index.php" class="stat-box" aria-label="View all valid orders" title="Click to view all orders">
      <span class="label">
        <span>📦 Total Order Value</span>
        <span style="font-size: 14px; color: #0284c7;">→</span>
      </span>
      <span class="number" style="color: #0f172a;"><?= format_inr($totalOrderValuePaise) ?></span>
      <span class="sub" style="color: #0284c7;"><?= $validOrdersCount ?> active order(s) placed</span>
      <span style="font-size: 11px; color: #64748b; margin-top: 4px;">Excludes cancelled &amp; refunded orders</span>
      <span class="card-click-hint" style="color: #0284c7;">View All Orders →</span>
    </a>

    <!-- 3. Today's Orders (IST) -->
    <a href="orders/index.php?date_range=today" class="stat-box" aria-label="View orders placed today in IST" title="Click to filter orders placed today">
      <span class="label">
        <span>📅 Today's Orders (IST)</span>
        <span style="font-size: 14px; color: #6d28d9;">→</span>
      </span>
      <span class="number" style="color: #6d28d9;"><?= $todayOrdersCount ?></span>
      <span class="sub" style="color: #6d28d9;">Asia/Kolkata reporting window</span>
      <span style="font-size: 11px; color: #64748b; margin-top: 4px;">Since 00:00:00 IST today</span>
      <span class="card-click-hint" style="color: #6d28d9;">View Today's Orders →</span>
    </a>

    <!-- 4. Pending Fulfillment & Inquiries -->
    <a href="orders/index.php?status=processing" class="stat-box" aria-label="View processing orders" title="Click to filter processing orders">
      <span class="label">
        <span>⏳ Pending Fulfillment</span>
        <span style="font-size: 14px; color: #d97706;">→</span>
      </span>
      <span class="number" style="color: #d97706;"><?= $processingOrdersCount ?></span>
      <span class="sub" style="color: #b45309;"><?= $pendingWhatsappCount ?> pending WhatsApp inquiry</span>
      <span style="font-size: 11px; color: #64748b; margin-top: 4px;">Orders awaiting dispatch / processing</span>
      <span class="card-click-hint" style="color: #d97706;">Inspect Processing →</span>
    </a>

  </div>

  <!-- Product & Inventory Health Cards (Row 2) -->
  <div style="margin-bottom: 8px;">
    <h3 style="font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; font-weight: 800; margin: 0 0 12px;">
      Product &amp; Inventory Health
    </h3>
  </div>
  <div class="admin-stats-grid" style="margin-bottom: 28px;">

    <!-- 5. Active Products -->
    <a href="products/index.php?status=published" class="stat-box" aria-label="Filter active published products" title="Click to view published products">
      <span class="label">
        <span>✅ Active Products</span>
        <span style="font-size: 14px; color: #166534;">→</span>
      </span>
      <span class="number" style="color: #166534;"><?= $activeProductCount ?></span>
      <span class="sub" style="color: #166534;">Published in store catalog</span>
      <span style="font-size: 11px; color: #64748b; margin-top: 4px;">Live for customer browsing &amp; checkout</span>
      <span class="card-click-hint">Filter Active Catalog →</span>
    </a>

    <!-- 6. Inactive Products -->
    <a href="products/index.php?status=inactive" class="stat-box" aria-label="Filter inactive draft and archived products" title="Click to view inactive products">
      <span class="label">
        <span>⏸️ Inactive Products</span>
        <span style="font-size: 14px; color: #475569;">→</span>
      </span>
      <span class="number" style="color: #475569;"><?= $inactiveProductCount ?></span>
      <span class="sub" style="color: #475569;">Drafts &amp; archived catalog</span>
      <span style="font-size: 11px; color: #64748b; margin-top: 4px;">Hidden from public storefront</span>
      <span class="card-click-hint" style="color: #475569;">Filter Inactive →</span>
    </a>

    <!-- 7. Low Stock Products -->
    <a href="products/index.php?stock_status=low_stock" class="stat-box" aria-label="Filter low stock products" title="Click to view low stock products">
      <span class="label">
        <span>⚠️ Low-Stock Products</span>
        <span style="font-size: 14px; color: <?= $lowStockCount > 0 ? '#b45309' : '#16a34a' ?>;">→</span>
      </span>
      <span class="number" style="color: <?= $lowStockCount > 0 ? '#b45309' : '#16a34a' ?>;"><?= $lowStockCount ?></span>
      <span class="sub" style="color: <?= $lowStockCount > 0 ? '#b45309' : '#16a34a' ?>;">Stock &gt; 0 &amp; ≤ threshold</span>
      <span style="font-size: 11px; color: #64748b; margin-top: 4px;">Configured per product (needs restock)</span>
      <span class="card-click-hint" style="color: #b45309;">Filter Low Stock →</span>
    </a>

    <!-- 8. Out-of-Stock Products -->
    <a href="products/index.php?stock_status=out_of_stock" class="stat-box" aria-label="Filter out-of-stock products" title="Click to view out-of-stock products">
      <span class="label">
        <span>🚫 Out-of-Stock Products</span>
        <span style="font-size: 14px; color: <?= $outOfStockCount > 0 ? '#dc2626' : '#16a34a' ?>;">→</span>
      </span>
      <span class="number" style="color: <?= $outOfStockCount > 0 ? '#dc2626' : '#16a34a' ?>;"><?= $outOfStockCount ?></span>
      <span class="sub" style="color: <?= $outOfStockCount > 0 ? '#dc2626' : '#16a34a' ?>;">0 units available</span>
      <span style="font-size: 11px; color: #64748b; margin-top: 4px;">Completely depleted (cannot order)</span>
      <span class="card-click-hint" style="color: #dc2626;">Filter Out of Stock →</span>
    </a>

  </div>

  <!-- ========================================================================= -->
  <!-- PROMINENT HOMEPAGE & PRODUCT MANAGEMENT HUB -->
  <!-- ========================================================================= -->
  <div class="admin-card" style="border: 2px solid #bbf7d0; background: linear-gradient(180deg, #f0fdf4 0%, #ffffff 120px);">
    <h2>
      <span style="display: flex; align-items: center; gap: 8px;">
        <span style="font-size: 22px;">🏠</span>
        Homepage &amp; Product Management Hub
      </span>
      <span style="font-size: 13px; font-weight: 600; color: #166534;">
        Manage storefront sections without touching code
      </span>
    </h2>
    <p style="color: #475569; font-size: 14px; margin: 0 0 20px;">
      Directly control which hardware models, server prices, images, and categories appear across each dedicated homepage section.
    </p>

    <div class="admin-sections-grid">

      <!-- 1. Homepage Products -->
      <a href="homepage/index.php" class="section-nav-card">
        <div class="card-top">
          <div class="card-icon" style="background: #e0f2fe; color: #0284c7;">🏠</div>
          <span class="card-badge"><?= $homepageProductsCount ?> items</span>
        </div>
        <h3>Homepage Products</h3>
        <p>Comprehensive master overview of all products featured anywhere on the homepage with quick filters and reordering.</p>
        <span class="card-action">Manage Placement &amp; Visibility →</span>
      </a>

      <!-- 2. Servers Section -->
      <a href="sections/servers.php" class="section-nav-card">
        <div class="card-top">
          <div class="card-icon" style="background: #dcfce7; color: #166534;">▤</div>
          <span class="card-badge"><?= $serversCount ?> live</span>
        </div>
        <h3>Servers Section</h3>
        <p>Manage enterprise rack &amp; tower servers, Dell PowerEdge, HPE ProLiant, Cisco UCS, and Huawei models.</p>
        <span class="card-action">Manage Servers →</span>
      </a>

      <!-- 3. Networking Section -->
      <a href="sections/networking.php" class="section-nav-card">
        <div class="card-top">
          <div class="card-icon" style="background: #ede9fe; color: #6d28d9;">🖧</div>
          <span class="card-badge"><?= $networkingCount ?> live</span>
        </div>
        <h3>Networking Section</h3>
        <p>Manage network switches, routers, firewalls, network adapter cards, and fiber patch cables.</p>
        <span class="card-action">Manage Networking →</span>
      </a>

      <!-- 4. Components Section -->
      <a href="sections/components.php" class="section-nav-card">
        <div class="card-top">
          <div class="card-icon" style="background: #fef3c7; color: #92400e;">▦</div>
          <span class="card-badge"><?= $componentsCount ?> live</span>
        </div>
        <h3>Components Section</h3>
        <p>Manage CPUs, ECC server RAM, storage drives, RAID controllers, SFP modules, and server power supplies.</p>
        <span class="card-action">Manage Components →</span>
      </a>

      <!-- 5. Our Products Section -->
      <a href="sections/our-products.php" class="section-nav-card">
        <div class="card-top">
          <div class="card-icon" style="background: #fee2e2; color: #991b1b;">🛍️</div>
          <span class="card-badge"><?= $ourProductsCount ?> live</span>
        </div>
        <h3>Our Products Section</h3>
        <p>Manage general accessories, keyboards, mice, LED monitors, laptop docking stations, and peripherals.</p>
        <span class="card-action">Manage Catalogue Accessories →</span>
      </a>

      <!-- 6. Product Categories -->
      <a href="categories/index.php" class="section-nav-card">
        <div class="card-top">
          <div class="card-icon" style="background: #f1f5f9; color: #334155;">📁</div>
          <span class="card-badge"><?= $categoriesCount ?> categories</span>
        </div>
        <h3>Product Categories</h3>
        <p>Organize category hierarchy, section mappings (servers, networking, components, our-products), and navigation.</p>
        <span class="card-action">Manage Categories →</span>
      </a>

      <!-- 7. Product Images -->
      <a href="images/index.php" class="section-nav-card">
        <div class="card-top">
          <div class="card-icon" style="background: #fdf2f8; color: #be185d;">🖼️</div>
          <span class="card-badge"><?= $imagesCount ?> images</span>
        </div>
        <h3>Product Images</h3>
        <p>Upload multi-image galleries, assign primary product photos, reorder thumbnails, replace, and remove images.</p>
        <span class="card-action">Manage Image Gallery →</span>
      </a>

      <!-- 8. Featured & Promotional Products -->
      <a href="sections/promotions.php" class="section-nav-card">
        <div class="card-top">
          <div class="card-icon" style="background: #fef08a; color: #854d0e;">🏷️</div>
          <span class="card-badge"><?= $featuredCount ?> spotlight</span>
        </div>
        <h3>Featured &amp; Promotions</h3>
        <p>Spotlight special discounts, limited-time offers, promotional server bundles, and custom deal badges.</p>
        <span class="card-action">Manage Promotions →</span>
      </a>

    </div>
  </div>

  <!-- Recent Orders Table -->
  <div class="admin-card">
    <h2>
      <span style="display: flex; align-items: center; gap: 8px;">
        <span>🛒</span>
        Recent Orders
      </span>
      <a href="orders/index.php" style="font-size: 13px; font-weight: 700;">View All Orders (<?= $totalOrdersCount ?>) →</a>
    </h2>

    <?php if (empty($recentOrders)): ?>
      <p style="color: #64748b; font-size: 14px; margin: 0; padding: 24px 0; text-align: center;">No orders have been placed yet.</p>
    <?php else: ?>
      <!-- Desktop Table View -->
      <div class="admin-table-wrap desktop-only-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Order #</th>
              <th>Customer</th>
              <th>Date (IST)</th>
              <th>Total</th>
              <th>Payment Method</th>
              <th>Payment Status</th>
              <th>Fulfillment</th>
              <th style="text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentOrders as $o): ?>
              <tr>
                <td><strong><?= e($o['order_number']) ?></strong></td>
                <td>
                  <strong><?= e($o['customer_name']) ?></strong><br>
                  <small style="color: #64748b;"><?= e($o['customer_email']) ?></small>
                </td>
                <td><?= date('M d, Y h:i A', strtotime($o['created_at'])) ?></td>
                <td><strong><?= format_inr((int)$o['total_paise']) ?></strong></td>
                <td>
                  <?php if ($o['payment_method'] === 'whatsapp'): ?>
                    <span class="badge badge-green" style="background: #dcfce7; color: #166534;">💬 WhatsApp</span>
                  <?php else: ?>
                    <span class="badge badge-blue">💳 Razorpay</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($o['payment_status'] === 'paid'): ?>
                    <span class="badge badge-green">Paid</span>
                  <?php elseif ($o['payment_status'] === 'failed'): ?>
                    <span class="badge badge-red">Failed</span>
                  <?php else: ?>
                    <span class="badge badge-yellow">Unpaid</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($o['status'] === 'delivered'): ?>
                    <span class="badge badge-green">Delivered</span>
                  <?php elseif ($o['status'] === 'shipped'): ?>
                    <span class="badge badge-purple">Shipped</span>
                  <?php elseif ($o['status'] === 'processing'): ?>
                    <span class="badge badge-blue">Processing</span>
                  <?php elseif ($o['status'] === 'cancelled'): ?>
                    <span class="badge badge-red">Cancelled</span>
                  <?php else: ?>
                    <span class="badge badge-gray"><?= e(ucfirst($o['status'])) ?></span>
                  <?php endif; ?>
                </td>
                <td style="text-align: right;">
                  <a href="orders/view.php?id=<?= (int)$o['id'] ?>" class="btn btn-secondary btn-sm">Inspect &amp; Fulfill →</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Mobile Touch-Optimized Cards View -->
      <div class="mobile-only">
        <div style="display: flex; flex-direction: column; gap: 12px;">
          <?php foreach ($recentOrders as $o): ?>
            <div style="border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; background: #ffffff; box-shadow: 0 1px 4px rgba(0,0,0,0.03);">
              <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; margin-bottom: 8px;">
                <div>
                  <span style="font-weight: 800; color: #0f172a; font-size: 15px;"><?= e($o['order_number']) ?></span>
                  <div style="font-size: 13.5px; font-weight: 600; color: #1e293b; margin-top: 2px;"><?= e($o['customer_name']) ?></div>
                  <div style="font-size: 12px; color: #64748b;"><?= e($o['customer_email']) ?></div>
                </div>
                <div style="text-align: right; flex-shrink: 0;">
                  <div style="font-weight: 800; font-size: 16px; color: #078e49;"><?= format_inr((int)$o['total_paise']) ?></div>
                  <div style="font-size: 11px; color: #64748b; margin-top: 2px;"><?= date('M d, h:i A', strtotime($o['created_at'])) ?></div>
                </div>
              </div>

              <div style="display: flex; gap: 6px; flex-wrap: wrap; align-items: center; margin-bottom: 12px;">
                <?php if ($o['payment_method'] === 'whatsapp'): ?>
                  <span class="badge badge-green" style="background: #dcfce7; color: #166534; font-size: 11px;">💬 WhatsApp</span>
                <?php else: ?>
                  <span class="badge badge-blue" style="font-size: 11px;">💳 <?= e(strtoupper($o['payment_method'])) ?></span>
                <?php endif; ?>

                <?php if ($o['payment_status'] === 'paid'): ?>
                  <span class="badge badge-green">Paid</span>
                <?php elseif ($o['payment_status'] === 'failed'): ?>
                  <span class="badge badge-red">Failed</span>
                <?php else: ?>
                  <span class="badge badge-yellow">Unpaid</span>
                <?php endif; ?>

                <?php if ($o['status'] === 'delivered'): ?>
                  <span class="badge badge-green">Delivered</span>
                <?php elseif ($o['status'] === 'shipped'): ?>
                  <span class="badge badge-purple">Shipped</span>
                <?php elseif ($o['status'] === 'processing'): ?>
                  <span class="badge badge-blue">Processing</span>
                <?php elseif ($o['status'] === 'cancelled'): ?>
                  <span class="badge badge-red">Cancelled</span>
                <?php else: ?>
                  <span class="badge badge-gray"><?= e(ucfirst($o['status'])) ?></span>
                <?php endif; ?>
              </div>

              <a href="orders/view.php?id=<?= (int)$o['id'] ?>" class="btn btn-secondary btn-sm" style="width: 100%; justify-content: center; min-height: 44px; font-size: 14px; font-weight: 700;">
                Inspect &amp; Fulfill Order →
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- Recent Inventory Adjustments -->
  <div class="admin-card">
    <h2>
      <span style="display: flex; align-items: center; gap: 8px;">
        <span>📊</span>
        Recent Inventory Movements &amp; Adjustments
      </span>
      <span style="font-size: 13px; font-weight: 600; color: #64748b;">
        Automated stock audits &amp; manual changes
      </span>
    </h2>

    <?php if (empty($recentMovements)): ?>
      <p style="color: #64748b; font-size: 14px; margin: 0; padding: 24px 0; text-align: center;">No inventory adjustment records recorded in database.</p>
    <?php else: ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Hardware Model / Item</th>
              <th>SKU</th>
              <th>Quantity Change</th>
              <th>Balance After</th>
              <th>Reason</th>
              <th>Actor / Reference</th>
              <th>Timestamp</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentMovements as $m): ?>
              <tr>
                <td>
                  <strong><?= e($m['product_title'] ?? ('Product #' . (int)$m['product_id'])) ?></strong>
                </td>
                <td><code><?= e($m['product_sku'] ?? '—') ?></code></td>
                <td>
                  <?php $change = (int)$m['quantity_change']; ?>
                  <?php if ($change > 0): ?>
                    <span class="badge badge-green">+<?= $change ?></span>
                  <?php elseif ($change < 0): ?>
                    <span class="badge badge-red"><?= $change ?></span>
                  <?php else: ?>
                    <span class="badge badge-gray">0</span>
                  <?php endif; ?>
                </td>
                <td><strong><?= (int)$m['balance_after'] ?> units</strong></td>
                <td>
                  <?php
                  $reasonLabels = [
                      'order_placed'      => ['label' => 'Order Placed', 'badge' => 'badge-blue'],
                      'order_cancelled'   => ['label' => 'Order Cancelled', 'badge' => 'badge-yellow'],
                      'manual_adjustment' => ['label' => 'Manual Adjustment', 'badge' => 'badge-purple'],
                      'restock'           => ['label' => 'Restock', 'badge' => 'badge-green'],
                      'stock_in'          => ['label' => 'Initial Stock', 'badge' => 'badge-green'],
                      'damage'            => ['label' => 'Damage / Writeoff', 'badge' => 'badge-red']
                  ];
                  $reasonInfo = $reasonLabels[$m['reason']] ?? ['label' => ucfirst(str_replace('_', ' ', (string)$m['reason'])), 'badge' => 'badge-gray'];
                  ?>
                  <span class="badge <?= $reasonInfo['badge'] ?>"><?= e($reasonInfo['label']) ?></span>
                </td>
                <td>
                  <?php if (!empty($m['admin_name']) || !empty($m['admin_username'])): ?>
                    <span>👤 <?= e($m['admin_name'] ?: $m['admin_username']) ?></span>
                  <?php elseif (!empty($m['reference_id'])): ?>
                    <code><?= e($m['reference_id']) ?></code>
                  <?php else: ?>
                    <span style="color: #94a3b8;">System Checkout</span>
                  <?php endif; ?>
                  <?php if (!empty($m['note'])): ?>
                    <br><small style="color: #64748b;"><?= e($m['note']) ?></small>
                  <?php endif; ?>
                </td>
                <td><?= date('M d, Y h:i A', strtotime($m['created_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- Low Stock Alerts -->
  <?php if (!empty($lowStockProducts)): ?>
    <div class="admin-card">
      <h2 style="color: #dc2626;">
        <span style="display: flex; align-items: center; gap: 8px;">
          <span>⚠️</span>
          Low Inventory Stock Alerts
        </span>
        <span style="font-size: 13px; font-weight: 600; color: #64748b;">Requires restocking attention (Stock &gt; 0 &amp; ≤ Threshold)</span>
      </h2>

      <!-- Desktop Table View -->
      <div class="admin-table-wrap desktop-only-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Hardware Model</th>
              <th>SKU</th>
              <th>Brand</th>
              <th>Available Stock</th>
              <th>Threshold</th>
              <th style="text-align: right;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($lowStockProducts as $lp): ?>
              <tr>
                <td><strong><?= e($lp['title']) ?></strong></td>
                <td><code><?= e($lp['sku']) ?></code></td>
                <td><?= e($lp['brand_name'] ?? 'iboltnetworks') ?></td>
                <td>
                  <strong style="color: #dc2626; font-size: 15px;"><?= (int)$lp['stock_quantity'] ?> unit(s)</strong>
                </td>
                <td><?= (int)$lp['low_stock_threshold'] ?> units</td>
                <td style="text-align: right;">
                  <a href="products/form.php?id=<?= (int)$lp['id'] ?>" class="btn btn-primary btn-sm">Restock / Edit</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Mobile Touch Cards View -->
      <div class="mobile-only">
        <div style="display: flex; flex-direction: column; gap: 10px;">
          <?php foreach ($lowStockProducts as $lp): ?>
            <div style="border: 1px solid #fed7aa; border-left: 4px solid #ea580c; border-radius: 8px; padding: 12px 14px; background: #fffbeb;">
              <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                <div>
                  <strong style="color: #0f172a; font-size: 14px; line-height: 1.3; display: block;"><?= e($lp['title']) ?></strong>
                  <div style="font-size: 12px; color: #64748b; margin-top: 3px;">
                    SKU: <code><?= e($lp['sku']) ?></code> &bull; <?= e($lp['brand_name'] ?? 'iboltnetworks') ?>
                  </div>
                </div>
                <div style="text-align: right; flex-shrink: 0;">
                  <span style="font-weight: 800; color: #dc2626; font-size: 15px;"><?= (int)$lp['stock_quantity'] ?> left</span>
                  <div style="font-size: 11px; color: #9a3412;">Min: <?= (int)$lp['low_stock_threshold'] ?></div>
                </div>
              </div>
              <a href="products/form.php?id=<?= (int)$lp['id'] ?>" class="btn btn-primary btn-sm" style="margin-top: 10px; width: 100%; justify-content: center; min-height: 44px; font-weight: 700;">
                Restock / Edit Hardware →
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/includes/admin-footer.php';
