<?php
declare(strict_types=1);

/**
 * Business Reports & Analytics Dashboard
 * iboltnetworks E-Commerce Platform
 */

$adminPageTitle = "Business Reports & Analytics | iboltnetworks Admin";
$adminRoot = "../";

require_once __DIR__ . "/../../app/helpers/auth.php";
require_admin();

require_once __DIR__ . "/../../app/helpers/reports.php";
require_once __DIR__ . "/../../app/helpers/csrf.php";
require_once __DIR__ . "/../../app/helpers/currency.php";

$db = Database::getConnection();

// -----------------------------------------------------------------------------
// 1. Resolve Date Range Filters (Asia/Kolkata timezone)
// -----------------------------------------------------------------------------
$preset = $_GET["preset"] ?? "last_7_days";
$customStart = !empty($_GET["start_date"]) ? trim((string)$_GET["start_date"]) : null;
$customEnd = !empty($_GET["end_date"]) ? trim((string)$_GET["end_date"]) : null;
$activeTab = $_GET["tab"] ?? "sales";

$range = resolve_report_date_range($preset, $customStart, $customEnd);
$startDate = $range["start_date"];
$endDate = $range["end_date"];
$startDatetime = $range["start_datetime"];
$endDatetime = $range["end_datetime"];
$dateLabel = $range["label"];

// -----------------------------------------------------------------------------
// 2. Handle CSV Exports (POST + CSRF Verification + Formula Injection Defense)
// -----------------------------------------------------------------------------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["export_type"])) {
    if (!verify_csrf_token($_POST["csrf_token"] ?? null)) {
        header("Location: index.php?preset=" . urlencode($preset) . "&start_date=" . urlencode($startDate) . "&end_date=" . urlencode($endDate) . "&tab=" . urlencode($activeTab) . "&error=" . urlencode("Security token expired. Please retry export."));
        exit;
    }

    $exportType = trim((string)$_POST["export_type"]);

    if ($exportType === "orders") {
        $stmt = $db->prepare("
            SELECT 
                o.order_number,
                o.created_at,
                o.customer_name,
                o.customer_phone,
                o.customer_email,
                o.status,
                o.payment_status,
                o.payment_method,
                ROUND(o.subtotal_paise / 100, 2) AS subtotal_inr,
                ROUND(o.tax_paise / 100, 2) AS tax_inr,
                ROUND(o.shipping_paise / 100, 2) AS shipping_inr,
                ROUND(o.total_paise / 100, 2) AS total_inr
            FROM orders o
            WHERE o.created_at BETWEEN ? AND ?
            ORDER BY o.created_at DESC
        ");
        $stmt->execute([$startDatetime, $endDatetime]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $headers = [
            "Order Number", "Order Date (IST)", "Customer Name", "Phone", "Email",
            "Fulfillment Status", "Payment Status", "Payment Method",
            "Subtotal (INR)", "Tax (INR)", "Shipping (INR)", "Total (INR)"
        ];
        $filename = "orders_report_" . $startDate . "_to_" . $endDate . ".csv";
        stream_csv_export($filename, $headers, $rows);
    }

    if ($exportType === "products") {
        $topProducts = get_top_selling_products($db, $startDatetime, $endDatetime, 500);
        $rows = [];
        foreach ($topProducts as $p) {
            $rows[] = [
                $p["product_title"],
                $p["sku"],
                $p["category_name"],
                $p["current_stock"],
                $p["total_quantity_sold"],
                round($p["historical_sales_paise"] / 100, 2),
                $p["order_count"]
            ];
        }

        $headers = [
            "Product Title", "SKU", "Category", "Current Stock",
            "Units Sold", "Historical Sales Value (INR)", "Order Count"
        ];
        $filename = "product_performance_" . $startDate . "_to_" . $endDate . ".csv";
        stream_csv_export($filename, $headers, $rows);
    }

    if ($exportType === "inventory_movements") {
        $movements = get_inventory_movements_report($db, $startDatetime, $endDatetime, 1000);
        $rows = [];
        foreach ($movements as $m) {
            $rows[] = [
                $m["created_at"],
                $m["product_title"],
                $m["sku"],
                $m["quantity_change"],
                $m["balance_after"],
                $m["reason"],
                $m["admin_name"],
                $m["note"] ?? "",
                $m["reference_id"] ?? ""
            ];
        }

        $headers = [
            "Date (IST)", "Product Title", "SKU", "Quantity Change",
            "Balance After", "Reason", "Admin User", "Notes", "Reference ID"
        ];
        $filename = "inventory_movements_" . $startDate . "_to_" . $endDate . ".csv";
        stream_csv_export($filename, $headers, $rows);
    }
}

// -----------------------------------------------------------------------------
// 3. Fetch Data for Current Tab & Date Range
// -----------------------------------------------------------------------------
$salesSummary = get_sales_order_summary($db, $startDatetime, $endDatetime);
$topProducts = get_top_selling_products($db, $startDatetime, $endDatetime, 10);
$categoryPerformance = get_category_performance_summary($db, $startDatetime, $endDatetime);
$zeroSalesProducts = get_zero_sales_products($db, $startDatetime, $endDatetime, 10);
$inventoryHealth = get_inventory_health_summary($db);
$movementsSummary = get_inventory_movements_summary($db, $startDatetime, $endDatetime);
$recentMovements = get_inventory_movements_report($db, $startDatetime, $endDatetime, 20);

// Fetch recent orders in period
$stmtRecentOrders = $db->prepare("
    SELECT o.id, o.order_number, o.created_at, o.customer_name, o.customer_phone,
           o.status, o.payment_status, o.total_paise
    FROM orders o
    WHERE o.created_at BETWEEN ? AND ?
    ORDER BY o.created_at DESC
    LIMIT 25
");
$stmtRecentOrders->execute([$startDatetime, $endDatetime]);
$recentOrders = $stmtRecentOrders->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . "/../includes/admin-header.php";
?>

<div class="admin-container" style="max-width: 1300px; margin: 0 auto; padding: 24px 20px;">
  
  <!-- Header Title & Export Actions -->
  <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
    <div>
      <h1 style="font-size: 26px; font-weight: 800; color: #0f172a; margin: 0 0 6px;">📈 Business Reports &amp; Analytics</h1>
      <p style="color: #64748b; font-size: 14px; margin: 0;">
        Accurate sales reporting, WhatsApp order conversion analysis, product velocity, and inventory movement audits.
      </p>
    </div>
    
    <!-- Quick CSV Export Form -->
    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
      <form method="POST" action="index.php" style="margin: 0;">
        <?= csrf_field() ?>
        <input type="hidden" name="preset" value="<?= e($preset) ?>">
        <input type="hidden" name="start_date" value="<?= e($startDate) ?>">
        <input type="hidden" name="end_date" value="<?= e($endDate) ?>">
        <input type="hidden" name="tab" value="<?= e($activeTab) ?>">
        
        <?php if ($activeTab === "sales"): ?>
          <input type="hidden" name="export_type" value="orders">
          <button type="submit" class="btn" style="background: #078e49; color: #fff; padding: 9px 16px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
            📥 Export Orders CSV
          </button>
        <?php elseif ($activeTab === "products"): ?>
          <input type="hidden" name="export_type" value="products">
          <button type="submit" class="btn" style="background: #078e49; color: #fff; padding: 9px 16px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
            📥 Export Product Sales CSV
          </button>
        <?php elseif ($activeTab === "inventory"): ?>
          <input type="hidden" name="export_type" value="inventory_movements">
          <button type="submit" class="btn" style="background: #078e49; color: #fff; padding: 9px 16px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
            📥 Export Movements CSV
          </button>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <!-- Date Range Filter Bar -->
  <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 20px; margin-bottom: 24px; box-shadow: 0 1px 4px rgba(0,0,0,0.03);">
    <form method="GET" action="index.php" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin: 0;">
      <input type="hidden" name="tab" value="<?= e($activeTab) ?>">

      <!-- Presets -->
      <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
        <span style="font-size: 13px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-right: 4px;">Period:</span>
        <?php
        $presetsList = [
            "today"       => "Today",
            "last_7_days" => "Last 7 Days",
            "this_month"  => "This Month",
            "prev_month"  => "Previous Month"
        ];
        foreach ($presetsList as $key => $title):
            $isActive = ($preset === $key);
        ?>
          <a href="index.php?preset=<?= e($key) ?>&tab=<?= e($activeTab) ?>" 
             style="padding: 6px 14px; border-radius: 6px; font-size: 13px; font-weight: 600; text-decoration: none; transition: all 0.15s; <?= $isActive ? "background: #078e49; color: #ffffff;" : "background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1;" ?>">
            <?= e($title) ?>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- Custom Date Inputs -->
      <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
        <input type="hidden" name="preset" value="custom">
        <label style="font-size: 13px; font-weight: 600; color: #475569;">From:</label>
        <input type="date" name="start_date" value="<?= e($startDate) ?>" 
               style="padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; color: #1e293b;">
        <label style="font-size: 13px; font-weight: 600; color: #475569;">To:</label>
        <input type="date" name="end_date" value="<?= e($endDate) ?>" 
               style="padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; color: #1e293b;">
        <button type="submit" 
                style="padding: 6px 14px; background: #0f172a; color: #ffffff; border: none; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">
          Apply
        </button>
      </div>
    </form>
    
    <div style="margin-top: 10px; font-size: 12.5px; color: #056633; font-weight: 600; display: flex; align-items: center; gap: 6px;">
      <span>📅 Current Active Window:</span> 
      <span style="color: #0f172a; background: #e8f7ee; padding: 2px 8px; border-radius: 4px;"><?= e($dateLabel) ?> (Asia/Kolkata)</span>
    </div>
  </div>

  <!-- Navigation Tabs -->
  <div style="display: flex; gap: 8px; border-bottom: 2px solid #e2e8f0; margin-bottom: 24px; flex-wrap: wrap;">
    <a href="index.php?preset=<?= e($preset) ?>&start_date=<?= e($startDate) ?>&end_date=<?= e($endDate) ?>&tab=sales" 
       style="padding: 10px 18px; font-size: 14px; font-weight: 700; text-decoration: none; border-bottom: 3px solid <?= $activeTab === "sales" ? "#078e49" : "transparent" ?>; color: <?= $activeTab === "sales" ? "#078e49" : "#64748b" ?>; margin-bottom: -2px; min-height: 44px; display: inline-flex; align-items: center;">
      📊 Sales &amp; Orders Summary
    </a>
    <a href="index.php?preset=<?= e($preset) ?>&start_date=<?= e($startDate) ?>&end_date=<?= e($endDate) ?>&tab=products" 
       style="padding: 10px 18px; font-size: 14px; font-weight: 700; text-decoration: none; border-bottom: 3px solid <?= $activeTab === "products" ? "#078e49" : "transparent" ?>; color: <?= $activeTab === "products" ? "#078e49" : "#64748b" ?>; margin-bottom: -2px; min-height: 44px; display: inline-flex; align-items: center;">
      🏆 Product &amp; Category Velocity
    </a>
    <a href="index.php?preset=<?= e($preset) ?>&start_date=<?= e($startDate) ?>&end_date=<?= e($endDate) ?>&tab=inventory" 
       style="padding: 10px 18px; font-size: 14px; font-weight: 700; text-decoration: none; border-bottom: 3px solid <?= $activeTab === "inventory" ? "#078e49" : "transparent" ?>; color: <?= $activeTab === "inventory" ? "#078e49" : "#64748b" ?>; margin-bottom: -2px; min-height: 44px; display: inline-flex; align-items: center;">
      📦 Inventory Health &amp; Movements
    </a>
  </div>

  <!-- =========================================================================
       TAB 1: SALES & ORDERS SUMMARY
       ========================================================================= -->
  <?php if ($activeTab === "sales"): ?>
    
    <!-- Top 4 KPI Metric Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 18px; margin-bottom: 24px;">
      
      <!-- Gross Order Value -->
      <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
        <div style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Gross Order Value</div>
        <div style="font-size: 26px; font-weight: 800; color: #0f172a; margin: 8px 0 4px;"><?= format_inr($salesSummary["gross_order_value_paise"]) ?></div>
        <div style="font-size: 12px; color: #64748b;">All <?= $salesSummary["total_orders"] ?> orders created in window</div>
      </div>

      <!-- Confirmed Paid Revenue -->
      <div style="background: #ffffff; border: 1px solid #bbf7d0; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); background: #f0fdf4;">
        <div style="font-size: 12px; font-weight: 700; color: #166534; text-transform: uppercase; letter-spacing: 0.5px;">Confirmed Paid Revenue</div>
        <div style="font-size: 26px; font-weight: 800; color: #078e49; margin: 8px 0 4px;"><?= format_inr($salesSummary["confirmed_paid_paise"]) ?></div>
        <div style="font-size: 12px; color: #166534;">
          <strong><?= $salesSummary["paid_orders_count"] ?></strong> paid order(s) (verified settlement)
        </div>
      </div>

      <!-- Unpaid WhatsApp Order Value -->
      <div style="background: #ffffff; border: 1px solid #fed7aa; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); background: #fffbeb;">
        <div style="font-size: 12px; font-weight: 700; color: #9a3412; text-transform: uppercase; letter-spacing: 0.5px;">Unpaid WhatsApp Orders</div>
        <div style="font-size: 26px; font-weight: 800; color: #c2410c; margin: 8px 0 4px;"><?= format_inr($salesSummary["unpaid_value_paise"]) ?></div>
        <div style="font-size: 12px; color: #9a3412;">
          <strong><?= $salesSummary["unpaid_orders_count"] ?></strong> pending inquiry order(s)
        </div>
      </div>

      <!-- Average Order Value -->
      <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
        <div style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Average Order Value (AOV)</div>
        <div style="font-size: 26px; font-weight: 800; color: #0f172a; margin: 8px 0 4px;"><?= format_inr($salesSummary["avg_order_value_paise"]) ?></div>
        <div style="font-size: 12px; color: #64748b;">Gross revenue / <?= max(1, $salesSummary["total_orders"]) ?> order(s)</div>
      </div>

    </div>

    <!-- Financial Notice Alert -->
    <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-left: 4px solid #078e49; border-radius: 6px; padding: 14px 18px; margin-bottom: 24px; font-size: 13px; color: #334155;">
      <strong>⚠️ Financial Accounting Policy Notice:</strong> 
      WhatsApp checkout creates order inquiries that remain <em>Unpaid</em> by default. 
      Only orders where staff explicitly verify receipt of payment and mark payment status as <code>Paid</code> are included in <strong>Confirmed Paid Revenue</strong>. 
      Product unit costs are not recorded in this catalog, so gross margin and profit calculations are strictly omitted to prevent misleading figures.
    </div>

    <!-- Status Breakdown Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 24px;">
      
      <!-- Fulfillment Status Breakdown -->
      <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px;">
        <h3 style="font-size: 15px; font-weight: 700; margin: 0 0 14px; color: #0f172a;">📦 Fulfillment Lifecycle Breakdown</h3>
        <div style="display: flex; flex-direction: column; gap: 8px;">
          <div style="display: flex; justify-content: space-between; padding: 8px 12px; background: #f8fafc; border-radius: 6px; font-size: 13px;">
            <span style="color: #475569;">⏳ Pending (WhatsApp Inquiries)</span>
            <strong><?= $salesSummary["pending_fulfillment_count"] ?></strong>
          </div>
          <div style="display: flex; justify-content: space-between; padding: 8px 12px; background: #f8fafc; border-radius: 6px; font-size: 13px;">
            <span style="color: #475569;">⚙️ Processing Orders</span>
            <strong><?= $salesSummary["processing_fulfillment_count"] ?></strong>
          </div>
          <div style="display: flex; justify-content: space-between; padding: 8px 12px; background: #f8fafc; border-radius: 6px; font-size: 13px;">
            <span style="color: #475569;">🚚 Shipped Orders</span>
            <strong><?= $salesSummary["shipped_fulfillment_count"] ?></strong>
          </div>
          <div style="display: flex; justify-content: space-between; padding: 8px 12px; background: #f8fafc; border-radius: 6px; font-size: 13px;">
            <span style="color: #475569;">✅ Delivered &amp; Completed</span>
            <strong><?= $salesSummary["delivered_fulfillment_count"] ?></strong>
          </div>
          <div style="display: flex; justify-content: space-between; padding: 8px 12px; background: #f8fafc; border-radius: 6px; font-size: 13px;">
            <span style="color: #475569;">❌ Cancelled Orders</span>
            <strong><?= $salesSummary["cancelled_fulfillment_count"] ?></strong>
          </div>
        </div>
      </div>

      <!-- Payment Settlement Breakdown -->
      <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px;">
        <h3 style="font-size: 15px; font-weight: 700; margin: 0 0 14px; color: #0f172a;">💳 Payment Status Breakdown</h3>
        <div style="display: flex; flex-direction: column; gap: 8px;">
          <div style="display: flex; justify-content: space-between; padding: 8px 12px; background: #f0fdf4; border-radius: 6px; font-size: 13px;">
            <span style="color: #166534; font-weight: 600;">✓ Confirmed Paid</span>
            <span style="color: #166534; font-weight: 700;"><?= $salesSummary["paid_orders_count"] ?> (<?= format_inr($salesSummary["confirmed_paid_paise"]) ?>)</span>
          </div>
          <div style="display: flex; justify-content: space-between; padding: 8px 12px; background: #fffbeb; border-radius: 6px; font-size: 13px;">
            <span style="color: #9a3412;">⏳ Unpaid Orders</span>
            <span style="color: #9a3412; font-weight: 700;"><?= $salesSummary["unpaid_orders_count"] ?> (<?= format_inr($salesSummary["unpaid_value_paise"]) ?>)</span>
          </div>
          <div style="display: flex; justify-content: space-between; padding: 8px 12px; background: #fef2f2; border-radius: 6px; font-size: 13px;">
            <span style="color: #991b1b;">⚠️ Failed / Rejected</span>
            <span style="color: #991b1b; font-weight: 700;"><?= $salesSummary["failed_orders_count"] ?></span>
          </div>
          <div style="display: flex; justify-content: space-between; padding: 8px 12px; background: #f8fafc; border-radius: 6px; font-size: 13px;">
            <span style="color: #475569;">↩️ Refunded Orders</span>
            <span style="color: #475569; font-weight: 700;"><?= $salesSummary["refunded_orders_count"] ?> (<?= format_inr($salesSummary["refunded_value_paise"]) ?>)</span>
          </div>
        </div>
      </div>

    </div>

    <!-- Orders Table in Selected Period -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h3 style="font-size: 16px; font-weight: 700; margin: 0; color: #0f172a;">
          📋 Orders in Selected Window (<?= count($recentOrders) ?>)
        </h3>
        <a href="../orders/index.php" style="font-size: 13px; font-weight: 600; color: #078e49;">View All in Order Manager &rarr;</a>
      </div>

      <?php if (empty($recentOrders)): ?>
        <div style="text-align: center; padding: 36px 20px; color: #64748b; font-size: 14px;">
          No orders found in the selected date range. Try selecting "This Month" or a broader custom range.
        </div>
      <?php else: ?>
        <div style="overflow-x: auto;">
          <table style="width: 100%; border-collapse: collapse; font-size: 13.5px;">
            <thead>
              <tr style="border-bottom: 2px solid #e2e8f0; text-align: left; color: #64748b; font-size: 12px; text-transform: uppercase;">
                <th style="padding: 10px 12px;">Order #</th>
                <th style="padding: 10px 12px;">Date (IST)</th>
                <th style="padding: 10px 12px;">Customer</th>
                <th style="padding: 10px 12px;">Fulfillment</th>
                <th style="padding: 10px 12px;">Payment</th>
                <th style="padding: 10px 12px; text-align: right;">Total Amount</th>
                <th style="padding: 10px 12px; text-align: center;">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentOrders as $o): ?>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                  <td style="padding: 12px; font-weight: 700; color: #0f172a;">
                    <a href="../orders/view.php?id=<?= (int)$o["id"] ?>" style="color: #078e49; text-decoration: none;">
                      <?= e($o["order_number"]) ?>
                    </a>
                  </td>
                  <td style="padding: 12px; color: #64748b;"><?= date("d M Y H:i", strtotime($o["created_at"])) ?></td>
                  <td style="padding: 12px;">
                    <div style="font-weight: 600; color: #1e293b;"><?= e($o["customer_name"]) ?></div>
                    <div style="font-size: 12px; color: #64748b;"><?= e($o["customer_phone"]) ?></div>
                  </td>
                  <td style="padding: 12px;">
                    <?php
                    $fColor = match($o["status"]) {
                        "delivered" => "background: #dcfce7; color: #166534;",
                        "processing" => "background: #e0f2fe; color: #075985;",
                        "shipped" => "background: #ede9fe; color: #5b21b6;",
                        "cancelled", "refunded" => "background: #fee2e2; color: #991b1b;",
                        default => "background: #fef3c7; color: #92400e;"
                    };
                    ?>
                    <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11.5px; font-weight: 700; text-transform: uppercase; <?= $fColor ?>">
                      <?= e($o["status"]) ?>
                    </span>
                  </td>
                  <td style="padding: 12px;">
                    <?php
                    $pColor = match($o["payment_status"]) {
                        "paid" => "background: #dcfce7; color: #166534;",
                        "refunded" => "background: #fee2e2; color: #991b1b;",
                        "failed" => "background: #fee2e2; color: #991b1b;",
                        default => "background: #fef3c7; color: #92400e;"
                    };
                    ?>
                    <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11.5px; font-weight: 700; text-transform: uppercase; <?= $pColor ?>">
                      <?= e($o["payment_status"]) ?>
                    </span>
                  </td>
                  <td style="padding: 12px; text-align: right; font-weight: 700; color: #0f172a;">
                    <?= format_inr((int)$o["total_paise"]) ?>
                  </td>
                  <td style="padding: 12px; text-align: center;">
                    <a href="../orders/view.php?id=<?= (int)$o["id"] ?>" style="font-size: 12.5px; color: #078e49; font-weight: 600;">View &rarr;</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

  <!-- =========================================================================
       TAB 2: PRODUCT & CATEGORY PERFORMANCE
       ========================================================================= -->
  <?php elseif ($activeTab === "products"): ?>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-bottom: 24px;">
      
      <!-- Top Selling Products -->
      <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
        <h3 style="font-size: 16px; font-weight: 700; margin: 0 0 16px; color: #0f172a;">
          🏆 Best Selling Products (Quantity &amp; Value)
        </h3>
        
        <?php if (empty($topProducts)): ?>
          <div style="text-align: center; padding: 24px; color: #64748b; font-size: 13.5px;">
            No product sales recorded in this period.
          </div>
        <?php else: ?>
          <div style="display: flex; flex-direction: column; gap: 12px;">
            <?php foreach ($topProducts as $idx => $prod): ?>
              <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: #f8fafc; border-radius: 6px; border-left: 3px solid #078e49;">
                <div>
                  <div style="font-weight: 700; font-size: 13.5px; color: #0f172a;">
                    #<?= $idx + 1 ?> <?= e($prod["product_title"]) ?>
                  </div>
                  <div style="font-size: 12px; color: #64748b;">
                    SKU: <code><?= e($prod["sku"]) ?></code> &bull; <?= e($prod["category_name"]) ?> &bull; Stock: <?= (int)$prod["current_stock"] ?>
                  </div>
                </div>
                <div style="text-align: right;">
                  <div style="font-weight: 700; color: #078e49; font-size: 14px;">
                    <?= (int)$prod["total_quantity_sold"] ?> unit(s)
                  </div>
                  <div style="font-size: 12px; color: #64748b; font-weight: 600;">
                    <?= format_inr((int)$prod["historical_sales_paise"]) ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Category Performance -->
      <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
        <h3 style="font-size: 16px; font-weight: 700; margin: 0 0 16px; color: #0f172a;">
          📁 Category Sales Performance
        </h3>
        
        <?php if (empty($categoryPerformance)): ?>
          <div style="text-align: center; padding: 24px; color: #64748b; font-size: 13.5px;">
            No category sales recorded in this period.
          </div>
        <?php else: ?>
          <div style="display: flex; flex-direction: column; gap: 12px;">
            <?php foreach ($categoryPerformance as $cat): ?>
              <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: #f8fafc; border-radius: 6px;">
                <div>
                  <div style="font-weight: 700; font-size: 13.5px; color: #0f172a;">
                    📁 <?= e($cat["category_name"]) ?>
                  </div>
                  <div style="font-size: 12px; color: #64748b;">
                    Across <?= (int)$cat["order_count"] ?> order(s)
                  </div>
                </div>
                <div style="text-align: right;">
                  <div style="font-weight: 700; color: #078e49; font-size: 14px;">
                    <?= format_inr((int)$cat["total_sales_paise"]) ?>
                  </div>
                  <div style="font-size: 12px; color: #64748b;">
                    <?= (int)$cat["total_units_sold"] ?> unit(s) sold
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

    </div>

    <!-- Products with Zero Sales in Period -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
      <h3 style="font-size: 16px; font-weight: 700; margin: 0 0 8px; color: #0f172a;">
        💤 Catalogue Products with Zero Sales in Period
      </h3>
      <p style="font-size: 13px; color: #64748b; margin: 0 0 16px;">
        Published products in stock that received no order placements during the selected window. Consider promotional merchandising or homepage feature placement.
      </p>

      <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; font-size: 13.5px;">
          <thead>
            <tr style="border-bottom: 2px solid #e2e8f0; text-align: left; color: #64748b; font-size: 12px; text-transform: uppercase;">
              <th style="padding: 8px 10px;">Product</th>
              <th style="padding: 8px 10px;">SKU</th>
              <th style="padding: 8px 10px;">Category</th>
              <th style="padding: 8px 10px; text-align: right;">Selling Price</th>
              <th style="padding: 8px 10px; text-align: right;">Current Stock</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($zeroSalesProducts as $zp): ?>
              <tr style="border-bottom: 1px solid #f1f5f9;">
                <td style="padding: 10px; font-weight: 600; color: #0f172a;"><?= e($zp["title"]) ?></td>
                <td style="padding: 10px; color: #64748b;"><code><?= e($zp["sku"]) ?></code></td>
                <td style="padding: 10px; color: #64748b;"><?= e($zp["category_name"]) ?></td>
                <td style="padding: 10px; text-align: right; font-weight: 600; color: #0f172a;">
                  <?= format_inr((int)$zp["price_paise"]) ?>
                </td>
                <td style="padding: 10px; text-align: right; font-weight: 700; color: #078e49;">
                  <?= (int)$zp["stock_quantity"] ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  <!-- =========================================================================
       TAB 3: INVENTORY HEALTH & MOVEMENTS
       ========================================================================= -->
  <?php elseif ($activeTab === "inventory"): ?>
    
    <!-- Inventory Overview Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 18px; margin-bottom: 24px;">
      
      <!-- Catalogue Retail Valuation -->
      <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
        <div style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Catalogue Retail Value</div>
        <div style="font-size: 26px; font-weight: 800; color: #078e49; margin: 8px 0 4px;">
          <?= format_inr($inventoryHealth["catalogue_retail_value_paise"]) ?>
        </div>
        <div style="font-size: 12px; color: #64748b;">
          Selling price basis (<?= $inventoryHealth["total_units_in_stock"] ?> total units in stock)
        </div>
      </div>

      <!-- In-Stock Products -->
      <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
        <div style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Healthy In-Stock Items</div>
        <div style="font-size: 26px; font-weight: 800; color: #0f172a; margin: 8px 0 4px;">
          <?= $inventoryHealth["healthy_stock_count"] ?>
        </div>
        <div style="font-size: 12px; color: #64748b;">
          Products above low-stock threshold
        </div>
      </div>

      <!-- Low-Stock Alerts -->
      <div style="background: #ffffff; border: 1px solid <?= $inventoryHealth["low_stock_count"] > 0 ? "#fed7aa" : "#e2e8f0" ?>; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); <?= $inventoryHealth["low_stock_count"] > 0 ? "background: #fffbeb;" : "" ?>">
        <div style="font-size: 12px; font-weight: 700; color: <?= $inventoryHealth["low_stock_count"] > 0 ? "#9a3412" : "#64748b" ?>; text-transform: uppercase; letter-spacing: 0.5px;">Low Stock Warnings</div>
        <div style="font-size: 26px; font-weight: 800; color: <?= $inventoryHealth["low_stock_count"] > 0 ? "#c2410c" : "#0f172a" ?>; margin: 8px 0 4px;">
          <?= $inventoryHealth["low_stock_count"] ?>
        </div>
        <div style="font-size: 12px; color: #64748b;">
          At or below threshold limit
        </div>
      </div>

      <!-- Out of Stock -->
      <div style="background: #ffffff; border: 1px solid <?= $inventoryHealth["out_of_stock_count"] > 0 ? "#fecaca" : "#e2e8f0" ?>; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); <?= $inventoryHealth["out_of_stock_count"] > 0 ? "background: #fef2f2;" : "" ?>">
        <div style="font-size: 12px; font-weight: 700; color: <?= $inventoryHealth["out_of_stock_count"] > 0 ? "#991b1b" : "#64748b" ?>; text-transform: uppercase; letter-spacing: 0.5px;">Out of Stock</div>
        <div style="font-size: 26px; font-weight: 800; color: <?= $inventoryHealth["out_of_stock_count"] > 0 ? "#dc2626" : "#0f172a" ?>; margin: 8px 0 4px;">
          <?= $inventoryHealth["out_of_stock_count"] ?>
        </div>
        <div style="font-size: 12px; color: #64748b;">
          Zero units remaining
        </div>
      </div>

    </div>

    <!-- Inventory Movement Audit Log Table -->
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
        <div>
          <h3 style="font-size: 16px; font-weight: 700; margin: 0; color: #0f172a;">
            📦 Audited Inventory Movements (<?= count($recentMovements) ?>)
          </h3>
          <div style="font-size: 12.5px; color: #64748b; margin-top: 4px;">
            Net activity in period: 
            <span style="color: #078e49; font-weight: 700;">+<?= $movementsSummary["total_additions"] ?> added</span> / 
            <span style="color: #c2410c; font-weight: 700;">-<?= $movementsSummary["total_deductions"] ?> deducted</span>
          </div>
        </div>
      </div>

      <?php if (empty($recentMovements)): ?>
        <div style="text-align: center; padding: 36px 20px; color: #64748b; font-size: 14px;">
          No inventory adjustments or movements recorded in this date range.
        </div>
      <?php else: ?>
        <div style="overflow-x: auto;">
          <table style="width: 100%; border-collapse: collapse; font-size: 13.5px;">
            <thead>
              <tr style="border-bottom: 2px solid #e2e8f0; text-align: left; color: #64748b; font-size: 12px; text-transform: uppercase;">
                <th style="padding: 10px 12px;">Date (IST)</th>
                <th style="padding: 10px 12px;">Product</th>
                <th style="padding: 10px 12px;">SKU</th>
                <th style="padding: 10px 12px; text-align: center;">Change</th>
                <th style="padding: 10px 12px; text-align: center;">Balance After</th>
                <th style="padding: 10px 12px;">Reason</th>
                <th style="padding: 10px 12px;">Administrator</th>
                <th style="padding: 10px 12px;">Notes / Ref</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentMovements as $mv): ?>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                  <td style="padding: 12px; color: #64748b; font-size: 12.5px;">
                    <?= date("d M Y H:i", strtotime($mv["created_at"])) ?>
                  </td>
                  <td style="padding: 12px; font-weight: 600; color: #0f172a;">
                    <?= e($mv["product_title"]) ?>
                  </td>
                  <td style="padding: 12px; color: #64748b;">
                    <code><?= e($mv["sku"]) ?></code>
                  </td>
                  <td style="padding: 12px; text-align: center;">
                    <?php if ((int)$mv["quantity_change"] > 0): ?>
                      <span style="font-weight: 800; color: #078e49;">+<?= (int)$mv["quantity_change"] ?></span>
                    <?php else: ?>
                      <span style="font-weight: 800; color: #dc2626;"><?= (int)$mv["quantity_change"] ?></span>
                    <?php endif; ?>
                  </td>
                  <td style="padding: 12px; text-align: center; font-weight: 700; color: #0f172a;">
                    <?= (int)$mv["balance_after"] ?>
                  </td>
                  <td style="padding: 12px;">
                    <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11.5px; font-weight: 700; text-transform: uppercase; background: #f1f5f9; color: #334155;">
                      <?= e($mv["reason"]) ?>
                    </span>
                  </td>
                  <td style="padding: 12px; color: #475569; font-size: 13px;">
                    👤 <?= e($mv["admin_name"]) ?>
                  </td>
                  <td style="padding: 12px; color: #64748b; font-size: 12.5px;">
                    <?= e($mv["note"] ?? "") ?>
                    <?php if (!empty($mv["reference_id"])): ?>
                      <div style="font-size: 11px; color: #94a3b8;">Ref: <?= e($mv["reference_id"]) ?></div>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

  <?php endif; ?>

</div>
