<?php
declare(strict_types=1);

/**
 * Business Reports & Analytics Helper
 * iboltnetworks E-Commerce Platform
 */

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/currency.php";
require_once __DIR__ . "/security.php";

/**
 * Resolve reporting date range presets in Asia/Kolkata timezone.
 *
 * Supported presets:
 * - "today": Current day 00:00:00 to 23:59:59
 * - "last_7_days": Past 7 calendar days including today
 * - "this_month": First calendar day of current month to today
 * - "prev_month": Entire previous calendar month
 * - "custom": Validated start and end dates (YYYY-MM-DD)
 */
function resolve_report_date_range(?string $preset = "last_7_days", ?string $customStart = null, ?string $customEnd = null): array
{
    $tz = new DateTimeZone("Asia/Kolkata");
    $now = new DateTimeImmutable("now", $tz);
    $validPresets = ["today", "last_7_days", "this_month", "prev_month", "custom"];

    $preset = in_array($preset, $validPresets, true) ? $preset : "last_7_days";

    if ($preset === "custom" && !empty($customStart) && !empty($customEnd)) {
        $sObj = DateTimeImmutable::createFromFormat("Y-m-d", trim($customStart), $tz);
        $eObj = DateTimeImmutable::createFromFormat("Y-m-d", trim($customEnd), $tz);

        if ($sObj && $eObj && $sObj->format("Y-m-d") === trim($customStart) && $eObj->format("Y-m-d") === trim($customEnd)) {
            // Swap if user provided inverted range
            if ($sObj > $eObj) {
                [$sObj, $eObj] = [$eObj, $sObj];
            }
            $startDate = $sObj->format("Y-m-d");
            $endDate = $eObj->format("Y-m-d");
            $label = "Custom Range: " . $sObj->format("d M Y") . " – " . $eObj->format("d M Y");
        } else {
            // Fall back to last_7_days if format invalid
            $preset = "last_7_days";
        }
    }

    if ($preset === "today") {
        $startDate = $now->format("Y-m-d");
        $endDate = $now->format("Y-m-d");
        $label = "Today (" . $now->format("d M Y") . ")";
    } elseif ($preset === "last_7_days") {
        $startObj = $now->sub(new DateInterval("P6D"));
        $startDate = $startObj->format("Y-m-d");
        $endDate = $now->format("Y-m-d");
        $label = "Last 7 Days (" . $startObj->format("d M") . " – " . $now->format("d M Y") . ")";
    } elseif ($preset === "this_month") {
        $startObj = $now->modify("first day of this month");
        $startDate = $startObj->format("Y-m-d");
        $endDate = $now->format("Y-m-d");
        $label = "This Month (" . $startObj->format("d M") . " – " . $now->format("d M Y") . ")";
    } elseif ($preset === "prev_month") {
        $startObj = $now->modify("first day of last month");
        $endObj = $now->modify("last day of last month");
        $startDate = $startObj->format("Y-m-d");
        $endDate = $endObj->format("Y-m-d");
        $label = "Previous Month (" . $startObj->format("F Y") . ")";
    }

    return [
        "preset"         => $preset,
        "start_date"     => $startDate,
        "end_date"       => $endDate,
        "start_datetime" => $startDate . " 00:00:00",
        "end_datetime"   => $endDate . " 23:59:59",
        "label"          => $label
    ];
}

/**
 * Fetch sales and order summary metrics for a given date range.
 */
function get_sales_order_summary(PDO $db, string $startDatetime, string $endDatetime): array
{
    $sql = "
        SELECT 
            COUNT(*) AS total_orders,
            COALESCE(SUM(total_paise), 0) AS gross_order_value_paise,
            COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_paise ELSE 0 END), 0) AS confirmed_paid_paise,
            COALESCE(SUM(CASE WHEN payment_status = 'unpaid' THEN total_paise ELSE 0 END), 0) AS unpaid_value_paise,
            COALESCE(SUM(CASE WHEN payment_status = 'refunded' THEN total_paise ELSE 0 END), 0) AS refunded_value_paise,
            COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) AS pending_count,
            COALESCE(SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END), 0) AS processing_count,
            COALESCE(SUM(CASE WHEN status = 'shipped' THEN 1 ELSE 0 END), 0) AS shipped_count,
            COALESCE(SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END), 0) AS delivered_count,
            COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END), 0) AS cancelled_count,
            COALESCE(SUM(CASE WHEN status = 'refunded' THEN 1 ELSE 0 END), 0) AS refunded_status_count,
            COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END), 0) AS paid_count,
            COALESCE(SUM(CASE WHEN payment_status = 'unpaid' THEN 1 ELSE 0 END), 0) AS unpaid_count,
            COALESCE(SUM(CASE WHEN payment_status = 'failed' THEN 1 ELSE 0 END), 0) AS failed_count,
            COALESCE(SUM(CASE WHEN payment_status = 'refunded' THEN 1 ELSE 0 END), 0) AS refunded_payment_count
        FROM orders
        WHERE created_at BETWEEN ? AND ?
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([$startDatetime, $endDatetime]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $totalOrders = (int)($res["total_orders"] ?? 0);
    $grossPaise = (int)($res["gross_order_value_paise"] ?? 0);
    $paidPaise = (int)($res["confirmed_paid_paise"] ?? 0);
    $refundedPaise = (int)($res["refunded_value_paise"] ?? 0);

    return [
        "total_orders"                => $totalOrders,
        "gross_order_value_paise"     => $grossPaise,
        "confirmed_paid_paise"        => $paidPaise,
        "net_paid_revenue_paise"      => max(0, $paidPaise - $refundedPaise),
        "unpaid_value_paise"          => (int)($res["unpaid_value_paise"] ?? 0),
        "refunded_value_paise"        => $refundedPaise,
        "avg_order_value_paise"       => $totalOrders > 0 ? (int)round($grossPaise / $totalOrders) : 0,
        "paid_orders_count"           => (int)($res["paid_count"] ?? 0),
        "unpaid_orders_count"         => (int)($res["unpaid_count"] ?? 0),
        "failed_orders_count"         => (int)($res["failed_count"] ?? 0),
        "refunded_orders_count"       => (int)($res["refunded_payment_count"] ?? 0),
        "pending_fulfillment_count"   => (int)($res["pending_count"] ?? 0),
        "processing_fulfillment_count"=> (int)($res["processing_count"] ?? 0),
        "shipped_fulfillment_count"   => (int)($res["shipped_count"] ?? 0),
        "delivered_fulfillment_count" => (int)($res["delivered_count"] ?? 0),
        "cancelled_fulfillment_count" => (int)($res["cancelled_count"] ?? 0),
        "refunded_fulfillment_count"  => (int)($res["refunded_status_count"] ?? 0)
    ];
}

/**
 * Fetch top selling products ranked by quantity sold or historical order value.
 */
function get_top_selling_products(PDO $db, string $startDatetime, string $endDatetime, int $limit = 10, bool $paidOnly = false): array
{
    $whereClause = "o.created_at BETWEEN ? AND ? AND o.status NOT IN ('cancelled', 'refunded')";
    $params = [$startDatetime, $endDatetime];

    if ($paidOnly) {
        $whereClause .= " AND o.payment_status = 'paid'";
    }

    $sql = "
        SELECT 
            oi.product_id,
            oi.product_title,
            oi.sku,
            COALESCE(c.name, 'Uncategorized') AS category_name,
            COALESCE(p.stock_quantity, 0) AS current_stock,
            COALESCE(p.price_paise, 0) AS current_price_paise,
            SUM(oi.quantity) AS total_quantity_sold,
            SUM(oi.total_paise) AS historical_sales_paise,
            COUNT(DISTINCT o.id) AS order_count
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        LEFT JOIN products p ON oi.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE {$whereClause}
        GROUP BY oi.product_id, oi.product_title, oi.sku, c.name, p.stock_quantity, p.price_paise
        ORDER BY total_quantity_sold DESC, historical_sales_paise DESC
        LIMIT " . (int)$limit;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetch category sales performance breakdown for a given date range.
 */
function get_category_performance_summary(PDO $db, string $startDatetime, string $endDatetime): array
{
    $sql = "
        SELECT 
            COALESCE(c.id, 0) AS category_id,
            COALESCE(c.name, 'Uncategorized') AS category_name,
            SUM(oi.quantity) AS total_units_sold,
            SUM(oi.total_paise) AS total_sales_paise,
            COUNT(DISTINCT o.id) AS order_count
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        LEFT JOIN products p ON oi.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE o.created_at BETWEEN ? AND ? AND o.status NOT IN ('cancelled', 'refunded')
        GROUP BY c.id, c.name
        ORDER BY total_sales_paise DESC, total_units_sold DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([$startDatetime, $endDatetime]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetch products with zero sales in the given date range.
 */
function get_zero_sales_products(PDO $db, string $startDatetime, string $endDatetime, int $limit = 50): array
{
    $sql = "
        SELECT 
            p.id,
            p.title,
            p.sku,
            COALESCE(c.name, 'Uncategorized') AS category_name,
            p.price_paise,
            p.stock_quantity,
            p.low_stock_threshold
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.status = 'published'
          AND p.id NOT IN (
              SELECT DISTINCT oi.product_id 
              FROM order_items oi
              JOIN orders o ON oi.order_id = o.id
              WHERE oi.product_id IS NOT NULL
                AND o.created_at BETWEEN ? AND ?
                AND o.status NOT IN ('cancelled', 'refunded')
          )
        ORDER BY p.stock_quantity DESC, p.title ASC
        LIMIT " . (int)$limit;

    $stmt = $db->prepare($sql);
    $stmt->execute([$startDatetime, $endDatetime]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetch inventory health metrics across catalogue products.
 */
function get_inventory_health_summary(PDO $db): array
{
    $sql = "
        SELECT 
            COUNT(*) AS total_products,
            COALESCE(SUM(CASE WHEN stock_quantity <= 0 THEN 1 ELSE 0 END), 0) AS out_of_stock_count,
            COALESCE(SUM(CASE WHEN stock_quantity > 0 AND stock_quantity <= low_stock_threshold THEN 1 ELSE 0 END), 0) AS low_stock_count,
            COALESCE(SUM(CASE WHEN stock_quantity > low_stock_threshold THEN 1 ELSE 0 END), 0) AS healthy_stock_count,
            COALESCE(SUM(stock_quantity), 0) AS total_units_in_stock,
            COALESCE(SUM(CASE WHEN stock_quantity > 0 THEN stock_quantity * price_paise ELSE 0 END), 0) AS catalogue_retail_value_paise
        FROM products
        WHERE status = 'published'
    ";

    $stmt = $db->query($sql);
    $res = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        "total_products"               => (int)($res["total_products"] ?? 0),
        "out_of_stock_count"           => (int)($res["out_of_stock_count"] ?? 0),
        "low_stock_count"              => (int)($res["low_stock_count"] ?? 0),
        "healthy_stock_count"          => (int)($res["healthy_stock_count"] ?? 0),
        "total_units_in_stock"         => (int)($res["total_units_in_stock"] ?? 0),
        "catalogue_retail_value_paise" => (int)($res["catalogue_retail_value_paise"] ?? 0)
    ];
}

/**
 * Fetch detailed inventory movements in a date range.
 */
function get_inventory_movements_report(PDO $db, string $startDatetime, string $endDatetime, int $limit = 100): array
{
    $sql = "
        SELECT 
            m.id,
            m.product_id,
            p.title AS product_title,
            p.sku,
            m.quantity_change,
            m.balance_after,
            m.reason,
            m.reference_id,
            COALESCE(u.name, u.username, 'System') AS admin_name,
            m.note,
            m.created_at
        FROM inventory_movements m
        JOIN products p ON m.product_id = p.id
        LEFT JOIN admin_users u ON m.admin_id = u.id
        WHERE m.created_at BETWEEN ? AND ?
        ORDER BY m.created_at DESC, m.id DESC
        LIMIT " . (int)$limit;

    $stmt = $db->prepare($sql);
    $stmt->execute([$startDatetime, $endDatetime]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetch inventory movement aggregation in a date range.
 */
function get_inventory_movements_summary(PDO $db, string $startDatetime, string $endDatetime): array
{
    $sql = "
        SELECT 
            COALESCE(SUM(CASE WHEN quantity_change > 0 THEN quantity_change ELSE 0 END), 0) AS total_additions,
            COALESCE(SUM(CASE WHEN quantity_change < 0 THEN ABS(quantity_change) ELSE 0 END), 0) AS total_deductions,
            COUNT(*) AS total_movements
        FROM inventory_movements
        WHERE created_at BETWEEN ? AND ?
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([$startDatetime, $endDatetime]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        "total_additions"  => (int)($res["total_additions"] ?? 0),
        "total_deductions" => (int)($res["total_deductions"] ?? 0),
        "total_movements"  => (int)($res["total_movements"] ?? 0)
    ];
}

/**
 * Stream a sanitized CSV file to browser output defended against formula injection.
 * Prepends UTF-8 BOM for Microsoft Excel compatibility.
 */
function stream_csv_export(string $filename, array $headers, iterable $rows): void
{
    if (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . rawurlencode($filename) . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    if ($out === false) {
        exit('Failed to open output stream');
    }

    // UTF-8 BOM for Excel UTF-8 recognition
    fwrite($out, "ï»¿");

    // Write headers
    $escapedHeaders = array_map('escape_csv_cell', $headers);
    fwrite($out, implode(',', $escapedHeaders) . "
");

    // Write rows
    foreach ($rows as $row) {
        $escapedRow = array_map('escape_csv_cell', (array)$row);
        fwrite($out, implode(',', $escapedRow) . "
");
    }

    fclose($out);
    exit;
}
