<?php
declare(strict_types=1);

/**
 * Customer Hardware Orders Management Controller
 * iboltnetworks E-Commerce Platform
 */

$adminPageTitle = "Customer Hardware Orders | iboltnetworks Admin";
$adminRoot = '../';

require_once __DIR__ . '/../includes/admin-header.php';
require_once __DIR__ . '/../../app/helpers/orders.php';

$db = Database::getConnection();

// Sanitize query filters
$statusFilter   = clean_string($_GET['status'] ?? '');
$paymentFilter  = clean_string($_GET['payment_status'] ?? '');
$methodFilter   = clean_string($_GET['payment_method'] ?? '');
$dateRange      = clean_string($_GET['date_range'] ?? '');
$dateFrom       = clean_string($_GET['date_from'] ?? '');
$dateTo         = clean_string($_GET['date_to'] ?? '');
$search         = clean_string($_GET['q'] ?? '');
$page           = max(1, sanitize_int($_GET['page'] ?? 1, 1));
$perPage        = 20;
$offset         = ($page - 1) * $perPage;

$where = ["1=1"];
$params = [];

// Status validation & filtering
$validStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
if (!empty($statusFilter) && in_array($statusFilter, $validStatuses, true)) {
    $where[] = "o.status = :status";
    $params[':status'] = $statusFilter;
}

// Payment status validation & filtering
$validPayStatuses = ['unpaid', 'paid', 'failed', 'refunded'];
if (!empty($paymentFilter) && in_array($paymentFilter, $validPayStatuses, true)) {
    $where[] = "o.payment_status = :pay_status";
    $params[':pay_status'] = $paymentFilter;
}

// Payment method filtering
$validMethods = ['whatsapp', 'razorpay'];
if (!empty($methodFilter) && in_array($methodFilter, $validMethods, true)) {
    $where[] = "o.payment_method = :pay_method";
    $params[':pay_method'] = $methodFilter;
}

// Date range filtering (IST Asia/Kolkata timezone standard)
$kolkataTz = new DateTimeZone('Asia/Kolkata');
if ($dateRange === 'today') {
    $todayStart = (new DateTime('today', $kolkataTz))->format('Y-m-d 00:00:00');
    $todayEnd   = (new DateTime('today', $kolkataTz))->format('Y-m-d 23:59:59');
    $where[] = "o.created_at >= :today_start AND o.created_at <= :today_end";
    $params[':today_start'] = $todayStart;
    $params[':today_end']   = $todayEnd;
} elseif ($dateRange === 'last_7_days') {
    $sevenDaysAgo = (new DateTime('-7 days', $kolkataTz))->format('Y-m-d 00:00:00');
    $where[] = "o.created_at >= :seven_days";
    $params[':seven_days'] = $sevenDaysAgo;
} elseif ($dateRange === 'this_month') {
    $monthStart = (new DateTime('first day of this month', $kolkataTz))->format('Y-m-d 00:00:00');
    $where[] = "o.created_at >= :month_start";
    $params[':month_start'] = $monthStart;
} elseif ($dateRange === 'custom' && !empty($dateFrom)) {
    $fromStr = $dateFrom . ' 00:00:00';
    $where[] = "o.created_at >= :custom_from";
    $params[':custom_from'] = $fromStr;
    if (!empty($dateTo)) {
        $toStr = $dateTo . ' 23:59:59';
        $where[] = "o.created_at <= :custom_to";
        $params[':custom_to'] = $toStr;
    }
}

// Multi-attribute search (Order Reference, Name, Email, Phone digits)
if (!empty($search)) {
    $like = '%' . $search . '%';
    $digits = preg_replace('/[^0-9]/', '', $search);

    if (strlen($digits) >= 4) {
        $where[] = "(o.order_number LIKE :s1 OR o.customer_name LIKE :s2 OR o.customer_email LIKE :s3 OR o.customer_phone LIKE :s4)";
        $params[':s1'] = $like;
        $params[':s2'] = $like;
        $params[':s3'] = $like;
        $params[':s4'] = '%' . $digits . '%';
    } else {
        $where[] = "(o.order_number LIKE :s1 OR o.customer_name LIKE :s2 OR o.customer_email LIKE :s3)";
        $params[':s1'] = $like;
        $params[':s2'] = $like;
        $params[':s3'] = $like;
    }
}

$whereSql = implode(' AND ', $where);

// Count total matching orders
$countStmt = $db->prepare("SELECT COUNT(*) FROM orders o WHERE {$whereSql}");
$countStmt->execute($params);
$totalOrders = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalOrders / $perPage));

// Fetch paginated orders with item count
$sql = "SELECT o.*,
               (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS item_count
        FROM orders o
        WHERE {$whereSql}
        ORDER BY o.created_at DESC, o.id DESC
        LIMIT {$perPage} OFFSET {$offset}";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$flashSuccess = clean_string($_GET['success'] ?? '');
$hasFilters = (!empty($search) || !empty($statusFilter) || !empty($paymentFilter) || !empty($methodFilter) || !empty($dateRange));
?>

<div class="admin-container">

  <div class="admin-page-header">
    <div>
      <h1>Customer Hardware Orders</h1>
      <p>Inspect incoming orders, track WhatsApp checkout inquiries, monitor payment confirmation, and manage fulfillment.</p>
    </div>
    <div style="display: flex; gap: 10px; align-items: center;">
      <a href="index.php" class="btn btn-secondary">🔄 Refresh Orders</a>
    </div>
  </div>

  <?php if (!empty($flashSuccess)): ?>
    <div class="alert alert-success"><?= e($flashSuccess) ?></div>
  <?php endif; ?>

  <!-- Filter & Search Bar -->
  <div class="admin-card" style="padding: 18px 20px; margin-bottom: 22px;">
    <form method="get" action="index.php" id="orderFilterForm">

      <!-- Always Visible Search Input & Quick Actions -->
      <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search Order #, Name, Email or Phone..." class="form-control" style="flex: 2; min-width: min(100%, 260px);">
        <button type="submit" class="btn btn-secondary" style="min-width: 90px;">Search</button>
        <button type="button" class="btn btn-secondary product-filter-toggle" id="orderFilterToggleBtn" onclick="toggleOrderFilters()" aria-expanded="<?= $hasFilters ? 'true' : 'false' ?>">
          ⚙️ Filters <?= $hasFilters ? '●' : '▼' ?>
        </button>
        <?php if ($hasFilters): ?>
          <a href="index.php" class="btn btn-secondary" style="color: #dc2626;">✕ Clear</a>
        <?php endif; ?>
      </div>

      <!-- Collapsible / Advanced Filter Controls -->
      <div id="orderFilterCollapsible" class="product-advanced-filters <?= $hasFilters ? 'is-expanded' : '' ?>" style="margin-top: 14px;">
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
          <select name="status" class="form-control" style="flex: 1; min-width: 140px;" onchange="this.form.submit()">
            <option value="">All Fulfillment</option>
            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending Review</option>
            <option value="processing" <?= $statusFilter === 'processing' ? 'selected' : '' ?>>Processing / Testing</option>
            <option value="shipped" <?= $statusFilter === 'shipped' ? 'selected' : '' ?>>Shipped</option>
            <option value="delivered" <?= $statusFilter === 'delivered' ? 'selected' : '' ?>>Delivered</option>
            <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            <option value="refunded" <?= $statusFilter === 'refunded' ? 'selected' : '' ?>>Refunded</option>
          </select>

          <select name="payment_status" class="form-control" style="flex: 1; min-width: 140px;" onchange="this.form.submit()">
            <option value="">All Payments</option>
            <option value="unpaid" <?= $paymentFilter === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
            <option value="paid" <?= $paymentFilter === 'paid' ? 'selected' : '' ?>>Paid (Confirmed)</option>
            <option value="failed" <?= $paymentFilter === 'failed' ? 'selected' : '' ?>>Failed</option>
            <option value="refunded" <?= $paymentFilter === 'refunded' ? 'selected' : '' ?>>Refunded</option>
          </select>

          <select name="payment_method" class="form-control" style="flex: 1; min-width: 140px;" onchange="this.form.submit()">
            <option value="">All Channels</option>
            <option value="whatsapp" <?= $methodFilter === 'whatsapp' ? 'selected' : '' ?>>WhatsApp</option>
            <option value="razorpay" <?= $methodFilter === 'razorpay' ? 'selected' : '' ?>>Razorpay / Gateway</option>
          </select>

          <select name="date_range" id="dateRangeSelect" class="form-control" style="flex: 1; min-width: 140px;" onchange="toggleCustomDates(this.value)">
            <option value="">All Dates</option>
            <option value="today" <?= $dateRange === 'today' ? 'selected' : '' ?>>Today (IST)</option>
            <option value="last_7_days" <?= $dateRange === 'last_7_days' ? 'selected' : '' ?>>Last 7 Days</option>
            <option value="this_month" <?= $dateRange === 'this_month' ? 'selected' : '' ?>>This Month</option>
            <option value="custom" <?= $dateRange === 'custom' ? 'selected' : '' ?>>Custom Range...</option>
          </select>

          <div id="customDateInputs" style="display: <?= $dateRange === 'custom' ? 'flex' : 'none' ?>; gap: 6px; align-items: center; flex: 1; min-width: 260px;">
            <input type="date" name="date_from" value="<?= e($dateFrom) ?>" class="form-control" style="flex: 1;" placeholder="From">
            <span style="color: #64748b;">to</span>
            <input type="date" name="date_to" value="<?= e($dateTo) ?>" class="form-control" style="flex: 1;" placeholder="To">
          </div>

          <button type="submit" class="btn btn-primary" style="min-width: 100px;">Filter</button>
        </div>
      </div>

      <div style="margin-top: 10px; font-size: 13px; color: #64748b; font-weight: 600; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px;">
        <span>Found <strong><?= $totalOrders ?></strong> order(s)</span>
        <?php if ($hasFilters): ?>
          <span style="color: var(--admin-green);">Active filters applied</span>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- Orders Table & Mobile Cards -->
  <div class="admin-card">
    <?php if (empty($orders)): ?>
      <p style="text-align: center; padding: 40px; color: #64748b; font-size: 14.5px;">No orders found matching the filter criteria.</p>
    <?php else: ?>
      <!-- Desktop Table View -->
      <div class="admin-table-wrap desktop-only-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Order #</th>
              <th>Customer</th>
              <th>Channel</th>
              <th>Date (IST)</th>
              <th>Items</th>
              <th>Total Amount</th>
              <th>Payment</th>
              <th>Fulfillment</th>
              <th style="text-align: right; min-width: 250px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($orders as $o): ?>
              <?php
                $waUrl = build_customer_whatsapp_url($o['customer_phone'], $o['order_number'], $o['customer_name']);
              ?>
              <tr>
                <td>
                  <a href="view.php?id=<?= (int)$o['id'] ?>" style="font-weight: 700; color: #078e49; text-decoration: none;">
                    <?= e($o['order_number']) ?>
                  </a>
                </td>
                <td>
                  <strong><?= e($o['customer_name']) ?></strong><br>
                  <small style="color: #64748b; font-size: 12px;">
                    <?= e($o['customer_phone'] ?? 'No phone') ?> &bull; <?= e($o['customer_email']) ?>
                  </small>
                </td>
                <td>
                  <?php if ($o['payment_method'] === 'whatsapp'): ?>
                    <span class="badge" style="background: #dcfce7; color: #166534; font-weight: 600; font-size: 11px;">
                      💬 WhatsApp
                    </span>
                  <?php else: ?>
                    <span class="badge badge-gray" style="font-size: 11px;">
                      <?= e(strtoupper($o['payment_method'] ?? 'CARD')) ?>
                    </span>
                  <?php endif; ?>
                </td>
                <td style="font-size: 12.5px; white-space: nowrap;">
                  <?= date('M d, Y', strtotime($o['created_at'])) ?><br>
                  <span style="color: #64748b; font-size: 11px;"><?= date('h:i A', strtotime($o['created_at'])) ?></span>
                </td>
                <td style="white-space: nowrap;"><?= (int)$o['item_count'] ?> item(s)</td>
                <td><strong style="color: #0f172a;"><?= format_inr((int)$o['total_paise']) ?></strong></td>
                <td>
                  <?php if ($o['payment_status'] === 'paid'): ?>
                    <span class="badge badge-green">Paid</span>
                  <?php elseif ($o['payment_status'] === 'failed'): ?>
                    <span class="badge badge-red">Failed</span>
                  <?php elseif ($o['payment_status'] === 'refunded'): ?>
                    <span class="badge badge-purple">Refunded</span>
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
                  <?php elseif ($o['status'] === 'refunded'): ?>
                    <span class="badge badge-purple">Refunded</span>
                  <?php else: ?>
                    <span class="badge badge-gray"><?= e(ucfirst($o['status'])) ?></span>
                  <?php endif; ?>
                </td>
                <td style="text-align: right; white-space: nowrap;">
                  <div style="display: inline-flex; gap: 6px; align-items: center;">
                    <!-- WhatsApp Contact Button -->
                    <?php if ($waUrl !== null): ?>
                      <a href="<?= e($waUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm" style="background: #25D366; color: #ffffff; padding: 4px 8px; font-size: 11.5px; font-weight: 600; text-decoration: none; border-radius: 4px;" title="Chat with customer on WhatsApp regarding this order">
                        💬 Chat
                      </a>
                    <?php else: ?>
                      <span class="badge badge-gray" style="font-size: 11px; opacity: 0.6;" title="Customer phone number is invalid or missing">
                        No WA
                      </span>
                    <?php endif; ?>

                    <!-- Printable Receipt Button -->
                    <a href="receipt.php?id=<?= (int)$o['id'] ?>" target="_blank" class="btn btn-secondary btn-sm" style="padding: 4px 8px; font-size: 11.5px;" title="Open Print-Ready Order Receipt">
                      🖨️ Receipt
                    </a>

                    <!-- View & Fulfill Button -->
                    <a href="view.php?id=<?= (int)$o['id'] ?>" class="btn btn-secondary btn-sm" style="padding: 4px 8px; font-size: 11.5px;">
                      Inspect →
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Mobile Touch-Optimized Order Cards -->
      <div class="mobile-only">
        <div style="display: flex; flex-direction: column; gap: 14px;">
          <?php foreach ($orders as $o): ?>
            <?php
              $waUrl = build_customer_whatsapp_url($o['customer_phone'], $o['order_number'], $o['customer_name']);
            ?>
            <div style="border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; background: #ffffff; box-shadow: 0 1px 4px rgba(0,0,0,0.03);">

              <!-- Order Header -->
              <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; margin-bottom: 8px;">
                <div>
                  <a href="view.php?id=<?= (int)$o['id'] ?>" style="font-weight: 800; color: #078e49; font-size: 15px; text-decoration: none;">
                    <?= e($o['order_number']) ?>
                  </a>
                  <div style="font-size: 13.5px; font-weight: 700; color: #0f172a; margin-top: 2px;">
                    <?= e($o['customer_name']) ?>
                  </div>
                  <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                    <a href="tel:<?= e($o['customer_phone']) ?>" style="color: #64748b; text-decoration: none;">📞 <?= e($o['customer_phone'] ?? 'No phone') ?></a> &bull; <?= e($o['customer_email']) ?>
                  </div>
                </div>

                <div style="text-align: right; flex-shrink: 0;">
                  <div style="font-weight: 800; font-size: 16px; color: #0f172a;"><?= format_inr((int)$o['total_paise']) ?></div>
                  <div style="font-size: 11px; color: #64748b; margin-top: 2px;"><?= date('d M, h:i A', strtotime($o['created_at'])) ?></div>
                  <div style="font-size: 11px; color: #94a3b8;"><?= (int)$o['item_count'] ?> item(s)</div>
                </div>
              </div>

              <!-- Status Badges Row -->
              <div style="display: flex; gap: 6px; flex-wrap: wrap; align-items: center; margin: 10px 0 14px;">
                <?php if ($o['payment_method'] === 'whatsapp'): ?>
                  <span class="badge" style="background: #dcfce7; color: #166534; font-size: 11px;">💬 WhatsApp Order</span>
                <?php else: ?>
                  <span class="badge badge-gray" style="font-size: 11px;">💳 <?= e(strtoupper($o['payment_method'] ?? 'CARD')) ?></span>
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

              <!-- Action Controls Row (44px touch targets) -->
              <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <?php if ($waUrl !== null): ?>
                  <a href="<?= e($waUrl) ?>" target="_blank" rel="noopener noreferrer" class="btn" style="flex: 2; min-width: 140px; justify-content: center; background: #25D366; color: #ffffff; min-height: 44px; font-weight: 700; text-decoration: none;">
                    💬 Chat on WhatsApp
                  </a>
                <?php endif; ?>

                <a href="view.php?id=<?= (int)$o['id'] ?>" class="btn btn-secondary" style="flex: 1; min-width: 100px; justify-content: center; min-height: 44px; font-weight: 700;">
                  👁️ Inspect
                </a>

                <a href="receipt.php?id=<?= (int)$o['id'] ?>" target="_blank" class="btn btn-secondary" style="min-width: 44px; justify-content: center; min-height: 44px;" title="Print Receipt">
                  🖨️
                </a>
              </div>

            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
        <?php $qParams = $_GET; ?>
        <div style="display: flex; justify-content: center; gap: 8px; margin-top: 24px; flex-wrap: wrap;">
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php $qParams['page'] = $i; ?>
            <a href="index.php?<?= http_build_query($qParams) ?>" class="btn <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?> btn-sm" style="min-width: 44px; min-height: 44px; justify-content: center;">
              <?= $i ?>
            </a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

</div>

<script>
function toggleOrderFilters() {
  const panel = document.getElementById('orderFilterCollapsible');
  const btn = document.getElementById('orderFilterToggleBtn');
  if (panel) {
    panel.classList.toggle('is-expanded');
    const expanded = panel.classList.contains('is-expanded');
    if (btn) btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
  }
}

function toggleCustomDates(val) {
  const customBox = document.getElementById('customDateInputs');
  if (val === 'custom') {
    customBox.style.display = 'flex';
  } else {
    customBox.style.display = 'none';
    document.querySelector('input[name="date_from"]').value = '';
    document.querySelector('input[name="date_to"]').value = '';
    document.getElementById('dateRangeSelect').form.submit();
  }
}
</script>

<?php
require_once __DIR__ . '/../includes/admin-footer.php';
