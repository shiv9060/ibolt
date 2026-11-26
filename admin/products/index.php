<?php
declare(strict_types=1);

/**
 * Admin Product Management Listing & Advanced Control
 * iboltnetworks E-Commerce Platform
 */

$adminRoot = '../';
$adminPageTitle = 'Products Catalog & Advanced Management | iboltnetworks Admin';

require_once __DIR__ . '/../includes/admin-header.php';
require_once __DIR__ . '/../../app/helpers/homepage.php';

$db = Database::getConnection();

// Helper to escape SQL LIKE wildcard characters for literal matching
if (!function_exists('escape_like')) {
    function escape_like(string $str): string {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $str);
    }
}

// 1. Sanitize & Validate Filter Inputs
$search         = clean_string($_GET['q'] ?? '');
$catFilter      = clean_string($_GET['category'] ?? '');
$brandFilter    = clean_string($_GET['brand'] ?? '');
$statusFilter   = clean_string($_GET['status'] ?? '');
$stockFilter    = clean_string($_GET['stock_status'] ?? '');
$sectionFilter  = clean_string($_GET['section'] ?? '');
$minPriceInput  = clean_string($_GET['min_price'] ?? '');
$maxPriceInput  = clean_string($_GET['max_price'] ?? '');
$sortField      = clean_string($_GET['sort'] ?? 'id');
$sortOrder      = strtoupper(clean_string($_GET['order'] ?? 'DESC'));
if ($sortOrder !== 'ASC') {
    $sortOrder = 'DESC';
}

$page = max(1, sanitize_int($_GET['page'] ?? 1, 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = ["1=1"];
$params = [];

// Multi-field Search
if (!empty($search)) {
    $escaped = escape_like($search);
    $like = '%' . $escaped . '%';
    $where[] = "(p.title LIKE :s1 OR p.sku LIKE :s2 OR p.model LIKE :s3 OR p.slug LIKE :s4 OR b.name LIKE :s5 OR c.name LIKE :s6)";
    $params[':s1'] = $like;
    $params[':s2'] = $like;
    $params[':s3'] = $like;
    $params[':s4'] = $like;
    $params[':s5'] = $like;
    $params[':s6'] = $like;
}

// Category Filter
if (!empty($catFilter)) {
    $where[] = "c.slug = :cat";
    $params[':cat'] = $catFilter;
}

// Brand Filter
if (!empty($brandFilter)) {
    $where[] = "b.slug = :brand";
    $params[':brand'] = $brandFilter;
}

// Status Filter (published, draft, archived, inactive)
if (!empty($statusFilter)) {
    if ($statusFilter === 'inactive') {
        $where[] = "p.status != 'published'";
    } else {
        $where[] = "p.status = :status";
        $params[':status'] = $statusFilter;
    }
}

// Stock Health Filter (non-overlapping)
if (!empty($stockFilter)) {
    if ($stockFilter === 'out_of_stock') {
        $where[] = "p.stock_quantity <= 0";
    } elseif ($stockFilter === 'low_stock') {
        $where[] = "p.stock_quantity > 0 AND p.stock_quantity <= p.low_stock_threshold";
    } elseif ($stockFilter === 'in_stock') {
        $where[] = "p.stock_quantity > p.low_stock_threshold";
    }
}

// Homepage Section Filter (including unassigned)
if (!empty($sectionFilter)) {
    if ($sectionFilter === 'none') {
        $where[] = "p.id NOT IN (SELECT product_id FROM homepage_section_products WHERE is_visible = 1)";
    } else {
        $where[] = "p.id IN (SELECT product_id FROM homepage_section_products WHERE section_key = :sec AND is_visible = 1)";
        $params[':sec'] = $sectionFilter;
    }
}

// Price Range Filters (in INR, converted to integer paise)
if ($minPriceInput !== '' && is_numeric($minPriceInput)) {
    $minPaise = inr_to_paise((float)$minPriceInput);
    if ($minPaise >= 0) {
        $where[] = "p.price_paise >= :min_paise";
        $params[':min_paise'] = $minPaise;
    }
}
if ($maxPriceInput !== '' && is_numeric($maxPriceInput)) {
    $maxPaise = inr_to_paise((float)$maxPriceInput);
    if ($maxPaise >= 0) {
        $where[] = "p.price_paise <= :max_paise";
        $params[':max_paise'] = $maxPaise;
    }
}

// 2. Allowlisted Sorting
$sortMap = [
    'id'         => 'p.id',
    'title'      => 'p.title',
    'sku'        => 'p.sku',
    'price'      => 'p.price_paise',
    'mrp'        => 'p.compare_price_paise',
    'stock'      => 'p.stock_quantity',
    'updated_at' => 'p.updated_at',
];

$sortColumn = $sortMap[$sortField] ?? 'p.id';
$orderBySql = "{$sortColumn} {$sortOrder}, p.id DESC";

$whereSql = implode(' AND ', $where);

// Count Total Matching Records
$countStmt = $db->prepare("SELECT COUNT(*) FROM products p LEFT JOIN categories c ON p.category_id = c.id LEFT JOIN brands b ON p.brand_id = b.id WHERE {$whereSql}");
$countStmt->execute($params);
$totalProducts = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalProducts / $perPage));

// Query Products
$sql = "SELECT p.*,
               b.name AS brand_name,
               c.name AS category_name,
               (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE {$whereSql}
        ORDER BY {$orderBySql}
        LIMIT {$perPage} OFFSET {$offset}";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Batch fetch homepage sections for current page
$productIds = array_column($products, 'id');
$sectionMap = [];
if (!empty($productIds)) {
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $hspStmt = $db->prepare("SELECT product_id, section_key FROM homepage_section_products WHERE product_id IN ({$placeholders}) AND is_visible = 1");
    $hspStmt->execute($productIds);
    $hspRows = $hspStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($hspRows as $hr) {
        $sectionMap[(int)$hr['product_id']][] = $hr['section_key'];
    }
}

$allCategories = $db->query("SELECT name, slug FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$allBrands = $db->query("SELECT name, slug FROM brands ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$flashSuccess = clean_string($_GET['success'] ?? '');
$flashError   = clean_string($_GET['error'] ?? '');
$flashInfo    = clean_string($_GET['info'] ?? '');

$hasActiveFilters = (!empty($search) || !empty($catFilter) || !empty($brandFilter) || !empty($sectionFilter) || !empty($statusFilter) || !empty($stockFilter) || $minPriceInput !== '' || $maxPriceInput !== '' || $sortField !== 'id' || $sortOrder !== 'DESC');

// Helper to generate sort link URLs preserving active filters
function make_sort_url(string $field, string $currentField, string $currentOrder): string {
    $q = $_GET;
    if ($currentField === $field) {
        $q['order'] = ($currentOrder === 'ASC') ? 'DESC' : 'ASC';
    } else {
        $q['sort'] = $field;
        $q['order'] = in_array($field, ['title', 'sku'], true) ? 'ASC' : 'DESC';
    }
    $q['page'] = 1;
    return 'index.php?' . http_build_query($q);
}

function render_sort_icon(string $field, string $currentField, string $currentOrder): string {
    if ($currentField !== $field) {
        return '<span style="color: #cbd5e1; font-size: 11px; margin-left: 3px;">⇅</span>';
    }
    return $currentOrder === 'ASC' 
        ? '<span style="color: var(--admin-green); font-size: 11px; margin-left: 3px;">▲</span>' 
        : '<span style="color: var(--admin-green); font-size: 11px; margin-left: 3px;">▼</span>';
}
?>

<div class="admin-container">

  <!-- Page Header -->
  <div class="admin-page-header">
    <div>
      <h1>Product Inventory Catalog</h1>
      <p>Advanced multi-field search, inventory filters, bulk updates, and audited stock adjustments.</p>
    </div>
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
      <a href="../index.php" class="btn btn-secondary">← Back to Dashboard</a>
      <a href="form.php" class="btn btn-primary">+ Add New Product</a>
    </div>
  </div>

  <!-- Flash Alerts -->
  <?php if (!empty($flashSuccess)): ?>
    <div class="alert alert-success" role="alert"><?= e($flashSuccess) ?></div>
  <?php endif; ?>
  <?php if (!empty($flashError)): ?>
    <div class="alert alert-error" role="alert"><?= e($flashError) ?></div>
  <?php endif; ?>
  <?php if (!empty($flashInfo)): ?>
    <div class="alert" style="background: #e0f2fe; border: 1px solid #bae6fd; color: #0369a1;" role="alert"><?= e($flashInfo) ?></div>
  <?php endif; ?>

  <!-- Advanced Search & Filter Bar -->
  <div class="admin-card" style="padding: 18px 20px; margin-bottom: 22px;">
    <form method="get" action="index.php" id="filterForm">

      <!-- Always Visible Search Input & Quick Action Row -->
      <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search title, SKU, model, slug, brand, category..." class="form-control" style="flex: 2; min-width: min(100%, 260px);">
        <button type="submit" class="btn btn-secondary" style="min-width: 90px;">Search</button>
        <button type="button" class="btn btn-secondary product-filter-toggle" id="filterToggleBtn" onclick="toggleFilterPanel()" aria-expanded="<?= $hasActiveFilters ? 'true' : 'false' ?>">
          ⚙️ Filters <?= $hasActiveFilters ? '●' : '▼' ?>
        </button>
        <?php if ($hasActiveFilters): ?>
          <a href="index.php" class="btn btn-secondary" style="color: #dc2626;">✕ Clear</a>
        <?php endif; ?>
      </div>

      <!-- Collapsible / Advanced Filter Controls -->
      <div id="filterCollapsiblePanel" class="product-advanced-filters <?= $hasActiveFilters ? 'is-expanded' : '' ?>" style="margin-top: 14px;">
        <!-- Row 1: Category, Brand, Section -->
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 10px;">
          <select name="category" class="form-control" style="flex: 1; min-width: 160px;" onchange="this.form.submit()">
            <option value="">All Categories</option>
            <?php foreach ($allCategories as $c): ?>
              <option value="<?= e($c['slug']) ?>" <?= $catFilter === $c['slug'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>

          <select name="brand" class="form-control" style="flex: 1; min-width: 140px;" onchange="this.form.submit()">
            <option value="">All Brands</option>
            <?php foreach ($allBrands as $b): ?>
              <option value="<?= e($b['slug']) ?>" <?= $brandFilter === $b['slug'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
            <?php endforeach; ?>
          </select>

          <select name="section" class="form-control" style="flex: 1; min-width: 160px;" onchange="this.form.submit()">
            <option value="">All Homepage Sections</option>
            <option value="servers" <?= $sectionFilter === 'servers' ? 'selected' : '' ?>>▤ Servers</option>
            <option value="networking" <?= $sectionFilter === 'networking' ? 'selected' : '' ?>>🖧 Networking</option>
            <option value="components" <?= $sectionFilter === 'components' ? 'selected' : '' ?>>▦ Components</option>
            <option value="our-products" <?= $sectionFilter === 'our-products' ? 'selected' : '' ?>>🛍️ Our Products</option>
            <option value="featured" <?= $sectionFilter === 'featured' ? 'selected' : '' ?>>🏷️ Featured Deals</option>
            <option value="none" <?= $sectionFilter === 'none' ? 'selected' : '' ?>>🚫 Unassigned (Not on Homepage)</option>
          </select>
        </div>

        <!-- Row 2: Status, Stock, Price Range, Sorting -->
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
          <select name="status" class="form-control" style="flex: 1; min-width: 140px;" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <option value="published" <?= $statusFilter === 'published' ? 'selected' : '' ?>>Active (Published)</option>
            <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive (Draft/Archived)</option>
            <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft Only</option>
            <option value="archived" <?= $statusFilter === 'archived' ? 'selected' : '' ?>>Archived Only</option>
          </select>

          <select name="stock_status" class="form-control" style="flex: 1; min-width: 150px;" onchange="this.form.submit()">
            <option value="">All Stock Levels</option>
            <option value="in_stock" <?= $stockFilter === 'in_stock' ? 'selected' : '' ?>>In Stock</option>
            <option value="low_stock" <?= $stockFilter === 'low_stock' ? 'selected' : '' ?>>Low Stock (≤ Threshold)</option>
            <option value="out_of_stock" <?= $stockFilter === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock (0)</option>
          </select>

          <div style="display: flex; align-items: center; gap: 6px; flex: 1; min-width: 210px;">
            <input type="number" step="1" min="0" name="min_price" value="<?= e($minPriceInput) ?>" placeholder="Min ₹" class="form-control" style="flex: 1;">
            <span style="color: #94a3b8;">–</span>
            <input type="number" step="1" min="0" name="max_price" value="<?= e($maxPriceInput) ?>" placeholder="Max ₹" class="form-control" style="flex: 1;">
          </div>

          <select name="sort" class="form-control" style="flex: 1; min-width: 175px;" onchange="this.form.submit()">
            <option value="id" <?= $sortField === 'id' && $sortOrder === 'DESC' ? 'selected' : '' ?>>Recently Added (Newest)</option>
            <option value="updated_at" <?= $sortField === 'updated_at' ? 'selected' : '' ?>>Last Updated</option>
            <option value="title" <?= $sortField === 'title' ? 'selected' : '' ?>>Title (Alphabetical)</option>
            <option value="sku" <?= $sortField === 'sku' ? 'selected' : '' ?>>SKU (Alphabetical)</option>
            <option value="price" <?= $sortField === 'price' && $sortOrder === 'ASC' ? 'selected' : '' ?>>Price (Low to High)</option>
            <option value="price" <?= $sortField === 'price' && $sortOrder === 'DESC' ? 'selected' : '' ?>>Price (High to Low)</option>
            <option value="stock" <?= $sortField === 'stock' && $sortOrder === 'ASC' ? 'selected' : '' ?>>Stock (Low to High)</option>
            <option value="stock" <?= $sortField === 'stock' && $sortOrder === 'DESC' ? 'selected' : '' ?>>Stock (High to Low)</option>
          </select>

          <button type="submit" class="btn btn-primary" style="min-width: 100px;">Filter</button>
        </div>
      </div>

      <div style="margin-top: 10px; font-size: 13px; color: #64748b; font-weight: 600; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px;">
        <span>Found <strong><?= $totalProducts ?></strong> product(s)</span>
        <?php if ($hasActiveFilters): ?>
          <span style="color: var(--admin-green);">Active filters applied</span>
        <?php endif; ?>
      </div>

    </form>
  </div>

  <!-- Floating / Sticky Bulk Action Bar -->
  <div id="bulkActionBar" style="display: none; background: #0f172a; color: #ffffff; padding: 14px 22px; border-radius: 10px; margin-bottom: 18px; box-shadow: 0 10px 25px rgba(0,0,0,0.25); align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap;">
    <div style="display: flex; align-items: center; gap: 12px;">
      <span style="font-size: 18px;">📦</span>
      <div>
        <strong id="bulkSelectedCount" style="color: #38bdf8; font-size: 15px;">0</strong>
        <span style="font-size: 13.5px; color: #cbd5e1;">product(s) selected</span>
      </div>
    </div>

    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
      <button type="button" class="btn btn-secondary btn-sm" onclick="openBulkModal('status')">⚡ Update Status</button>
      <button type="button" class="btn btn-secondary btn-sm" onclick="openBulkModal('assign_section')">🏠 Assign Section</button>
      <button type="button" class="btn btn-secondary btn-sm" onclick="openBulkModal('remove_section')">✕ Remove Section</button>
      <button type="button" class="btn btn-primary btn-sm" onclick="openBulkModal('price_update')">🏷️ Bulk Price Update</button>
      <button type="button" class="btn btn-secondary btn-sm" style="color: #fca5a5;" onclick="clearProductSelections()">Clear Selection</button>
    </div>
  </div>

  <!-- Products Table & Mobile Cards -->
  <div class="admin-card">

    <!-- Desktop Table View (>= 769px) -->
    <div class="admin-table-wrap desktop-only-wrap">
      <table class="admin-table" id="productsTable">
        <thead>
          <tr>
            <th style="width: 36px; text-align: center;">
              <input type="checkbox" id="selectAllCheckbox" title="Select all on this page" style="cursor: pointer; width: 16px; height: 16px;">
            </th>
            <th style="width: 50px;">Image</th>
            <th>
              <a href="<?= make_sort_url('title', $sortField, $sortOrder) ?>" style="color: inherit; text-decoration: none; display: inline-flex; align-items: center;">
                Title &amp; Model <?= render_sort_icon('title', $sortField, $sortOrder) ?>
              </a>
            </th>
            <th>
              <a href="<?= make_sort_url('sku', $sortField, $sortOrder) ?>" style="color: inherit; text-decoration: none; display: inline-flex; align-items: center;">
                SKU <?= render_sort_icon('sku', $sortField, $sortOrder) ?>
              </a>
            </th>
            <th>Category</th>
            <th>
              <a href="<?= make_sort_url('price', $sortField, $sortOrder) ?>" style="color: inherit; text-decoration: none; display: inline-flex; align-items: center;">
                Selling Price <?= render_sort_icon('price', $sortField, $sortOrder) ?>
              </a>
            </th>
            <th>
              <a href="<?= make_sort_url('mrp', $sortField, $sortOrder) ?>" style="color: inherit; text-decoration: none; display: inline-flex; align-items: center;">
                MRP <?= render_sort_icon('mrp', $sortField, $sortOrder) ?>
              </a>
            </th>
            <th>
              <a href="<?= make_sort_url('stock', $sortField, $sortOrder) ?>" style="color: inherit; text-decoration: none; display: inline-flex; align-items: center;">
                Stock <?= render_sort_icon('stock', $sortField, $sortOrder) ?>
              </a>
            </th>
            <th>Condition</th>
            <th>Homepage Placements</th>
            <th>Status</th>
            <th style="text-align: right;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($products)): ?>
            <tr>
              <td colspan="12" style="text-align: center; padding: 40px; color: #64748b;">
                No products found matching the specified filters.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($products as $p): ?>
              <?php
              $prodId = (int)$p['id'];
              $assignedSecs = $sectionMap[$prodId] ?? [];
              $stockQty = (int)$p['stock_quantity'];
              $lowThreshold = (int)$p['low_stock_threshold'];
              ?>
              <tr id="row-<?= $prodId ?>">
                <td style="text-align: center;">
                  <input type="checkbox" class="product-chk" value="<?= $prodId ?>" data-title="<?= e($p['title']) ?>" data-sku="<?= e($p['sku']) ?>" data-price="<?= (int)$p['price_paise'] ?>" style="cursor: pointer; width: 16px; height: 16px;">
                </td>
                <td>
                  <img src="<?= e($adminRoot . ($p['primary_image'] ?? 'images/server-hud.png')) ?>" alt="" style="width: 44px; height: 44px; object-fit: contain; border-radius: 6px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 2px;">
                </td>
                <td>
                  <a href="form.php?id=<?= $prodId ?>" style="font-weight: 700; color: #1e293b; display: block; line-height: 1.35;">
                    <?= e($p['title']) ?>
                  </a>
                  <small style="color: #64748b; font-size: 11px;"><?= e($p['brand_name'] ?? 'iboltnetworks') ?> &bull; <?= e($p['model'] ?? '') ?></small>
                </td>
                <td><code><?= e($p['sku']) ?></code></td>
                <td><?= e($p['category_name'] ?? 'N/A') ?></td>
                <td><strong style="color: #078e49;"><?= format_inr((int)$p['price_paise']) ?></strong></td>
                <td>
                  <?php if (!empty($p['compare_price_paise'])): ?>
                    <del style="color: #94a3b8; font-size: 12px;"><?= format_inr((int)$p['compare_price_paise']) ?></del>
                  <?php else: ?>
                    <span style="color: #cbd5e1;">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div style="display: flex; align-items: center; gap: 8px;">
                    <?php if ($stockQty <= 0): ?>
                      <span class="badge badge-red">0 (Out)</span>
                    <?php elseif ($stockQty <= $lowThreshold): ?>
                      <strong style="color: #dc2626;"><?= $stockQty ?> unit(s)</strong>
                    <?php else: ?>
                      <span style="color: #166534; font-weight: 700;"><?= $stockQty ?> units</span>
                    <?php endif; ?>
                    <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 7px; font-size: 11px;" onclick="openStockModal(<?= $prodId ?>, '<?= e(addslashes($p['title'])) ?>', '<?= e($p['sku']) ?>', <?= $stockQty ?>)" title="Audited Stock Adjustment">
                      ⚡ Adjust
                    </button>
                  </div>
                </td>
                <td>
                  <span class="badge <?= $p['condition_type'] === 'refurbished' ? 'badge-blue' : 'badge-green' ?>">
                    <?= e(ucfirst($p['condition_type'])) ?>
                  </span>
                </td>
                <td>
                  <?php if (!empty($assignedSecs)): ?>
                    <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                      <?php foreach ($assignedSecs as $as): ?>
                        <span class="badge badge-purple" style="font-size: 10.5px; padding: 2px 7px; display: inline-flex; align-items: center; gap: 4px;">
                          <span><?= get_section_icon($as) ?></span> <?= e(ucfirst($as)) ?>
                        </span>
                      <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                    <span style="color: #94a3b8; font-size: 11.5px;">None</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge <?= $p['status'] === 'published' ? 'badge-green' : 'badge-gray' ?>">
                    <?= e(ucfirst($p['status'])) ?>
                  </span>
                </td>
                <td style="text-align: right; white-space: nowrap;">
                  <a href="form.php?id=<?= $prodId ?>" class="btn btn-secondary btn-sm" title="Edit Product">Edit</a>
                  <a href="<?= e($adminRoot) ?>images/index.php?product_id=<?= $prodId ?>" class="btn btn-secondary btn-sm" title="Manage Images">Images</a>
                  <a href="<?= e($adminRoot) ?>../product.php?slug=<?= e($p['slug']) ?>" target="_blank" class="btn btn-secondary btn-sm" title="View product page">View ↗</a>
                  <form action="delete.php" method="post" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete product <?= e(addslashes($p['title'])) ?>?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $prodId ?>">
                    <button type="submit" class="btn btn-danger btn-sm" title="Delete product">✕</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Mobile Touch-Optimized Cards View (<= 768px) -->
    <div class="mobile-only">
      <?php if (empty($products)): ?>
        <div style="text-align: center; padding: 32px 16px; color: #64748b;">
          No products found matching the specified filters.
        </div>
      <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 14px;">
          <?php foreach ($products as $p): ?>
            <?php
            $prodId = (int)$p['id'];
            $assignedSecs = $sectionMap[$prodId] ?? [];
            $stockQty = (int)$p['stock_quantity'];
            $lowThreshold = (int)$p['low_stock_threshold'];
            $isLowStock = ($stockQty > 0 && $stockQty <= $lowThreshold);
            $isOutOfStock = ($stockQty <= 0);
            $sellingPrice = (int)$p['price_paise'];
            $mrp = (int)($p['compare_price_paise'] ?? 0);
            $hasDiscount = ($mrp > $sellingPrice && $sellingPrice > 0);
            $discountPct = $hasDiscount ? (int)round((($mrp - $sellingPrice) / $mrp) * 100) : 0;
            $primaryImg = $p['primary_image'] ?: 'images/server-hud.png';
            $imgSrc = (str_starts_with($primaryImg, 'http') || str_starts_with($primaryImg, '/')) ? $primaryImg : $adminRoot . '../' . $primaryImg;
            ?>
            <div id="m-row-<?= $prodId ?>" style="border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; background: #ffffff; box-shadow: 0 1px 4px rgba(0,0,0,0.03);">

              <!-- Header with Checkbox, Thumb, Title, SKU -->
              <div style="display: flex; gap: 12px; align-items: flex-start; margin-bottom: 10px;">
                <div style="display: flex; flex-direction: column; align-items: center; gap: 6px; flex-shrink: 0;">
                  <input type="checkbox" class="product-chk" value="<?= $prodId ?>" data-title="<?= e($p['title']) ?>" data-sku="<?= e($p['sku']) ?>" data-price="<?= $sellingPrice ?>" style="cursor: pointer; width: 20px; height: 20px; accent-color: var(--admin-green);" title="Select for bulk action">
                  <img src="<?= e($imgSrc) ?>" alt="" style="width: 52px; height: 52px; object-fit: contain; border-radius: 6px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 2px;" loading="lazy" onerror="this.src='<?= e($adminRoot) ?>../images/server-hud.png'">
                </div>

                <div style="flex: 1; min-width: 0;">
                  <a href="form.php?id=<?= $prodId ?>" style="font-weight: 700; color: #0f172a; font-size: 14.5px; line-height: 1.35; display: block; word-break: break-word;">
                    <?= e($p['title']) ?>
                  </a>
                  <div style="font-size: 12px; color: #64748b; margin-top: 3px;">
                    SKU: <code><?= e($p['sku']) ?></code> &bull; <?= e($p['category_name'] ?? 'Hardware') ?>
                  </div>
                </div>
              </div>

              <!-- Price & Stock Row -->
              <div style="display: flex; justify-content: space-between; align-items: center; background: #f8fafc; border-radius: 8px; padding: 9px 12px; margin-bottom: 10px; flex-wrap: wrap; gap: 6px;">
                <div>
                  <span style="font-size: 16px; font-weight: 800; color: #078e49;"><?= format_inr($sellingPrice) ?></span>
                  <?php if ($hasDiscount): ?>
                    <span style="font-size: 12px; text-decoration: line-through; color: #94a3b8; margin-left: 4px;"><?= format_inr($mrp) ?></span>
                    <span class="badge badge-green" style="font-size: 10px; margin-left: 4px;"><?= $discountPct ?>% OFF</span>
                  <?php endif; ?>
                </div>

                <div>
                  <?php if ($isOutOfStock): ?>
                    <span class="badge badge-red" style="font-size: 11px;">0 Units (Depleted)</span>
                  <?php elseif ($isLowStock): ?>
                    <span class="badge badge-yellow" style="font-size: 11px;">Low: <?= $stockQty ?> left</span>
                  <?php else: ?>
                    <span class="badge badge-green" style="font-size: 11px;"><?= $stockQty ?> in stock</span>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Badges & Sections -->
              <div style="display: flex; gap: 6px; flex-wrap: wrap; align-items: center; margin-bottom: 12px;">
                <span class="badge <?= $p['status'] === 'published' ? 'badge-green' : 'badge-gray' ?>" style="font-size: 11px;">
                  <?= e(ucfirst($p['status'])) ?>
                </span>

                <span class="badge <?= $p['condition_type'] === 'refurbished' ? 'badge-blue' : 'badge-green' ?>" style="font-size: 10.5px;">
                  <?= e(ucfirst($p['condition_type'])) ?>
                </span>

                <?php if (!empty($assignedSecs)): ?>
                  <?php foreach ($assignedSecs as $as): ?>
                    <span class="badge badge-purple" style="font-size: 10.5px;">
                      <?= get_section_icon($as) ?> <?= e(ucfirst($as)) ?>
                    </span>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>

              <!-- Mobile Touch Actions Grid (min 42px touch target) -->
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                <a href="form.php?id=<?= $prodId ?>" class="btn btn-secondary btn-sm" style="justify-content: center; min-height: 42px; font-weight: 700;">
                  ✏️ Edit
                </a>

                <button type="button" class="btn btn-secondary btn-sm" style="justify-content: center; min-height: 42px; font-weight: 700;" onclick="openStockModal(<?= $prodId ?>, '<?= e(addslashes($p['title'])) ?>', '<?= e($p['sku']) ?>', <?= $stockQty ?>)">
                  ⚡ Stock (<?= $stockQty ?>)
                </button>

                <a href="<?= e($adminRoot) ?>images/index.php?product_id=<?= $prodId ?>" class="btn btn-secondary btn-sm" style="justify-content: center; min-height: 42px;">
                  🖼️ Images
                </a>

                <a href="<?= e($adminRoot) ?>../product.php?slug=<?= e($p['slug']) ?>" target="_blank" class="btn btn-secondary btn-sm" style="justify-content: center; min-height: 42px;">
                  Storefront ↗
                </a>
              </div>

            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
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
  </div>

</div>

<!-- ========================================================================= -->
<!-- MODAL 1: Audited Inventory Stock Adjustment Modal -->
<!-- ========================================================================= -->
<div id="stockModal" class="admin-modal">
  <div class="admin-modal-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
      <h3 style="margin: 0; font-size: 18px; color: #0f172a;">⚡ Audited Stock Adjustment</h3>
      <button type="button" onclick="closeModal('stockModal')" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #64748b;">✕</button>
    </div>

    <form method="post" action="adjust-stock.php" id="stockAdjustmentForm">
      <?= csrf_field() ?>
      <input type="hidden" name="product_id" id="smProductId" value="">

      <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; margin-bottom: 18px;">
        <strong id="smProductTitle" style="font-size: 14px; color: #1e293b; display: block;">—</strong>
        <span style="font-size: 12px; color: #64748b;">SKU: <code id="smProductSku">—</code></span>
        <div style="margin-top: 8px; font-size: 13px;">
          Current Stock: <strong id="smCurrentStock" style="color: #0284c7; font-size: 15px;">0</strong> units
        </div>
      </div>

      <div class="form-group">
        <label for="smType">Adjustment Method *</label>
        <select name="adjustment_type" id="smType" class="form-control" onchange="calculateStockBalance()">
          <option value="add">Increase Stock (Restock / Received +)</option>
          <option value="subtract">Decrease Stock (Defective / Writeoff -)</option>
          <option value="set">Set Exact Count (Physical Audit =)</option>
        </select>
      </div>

      <div class="form-group">
        <label for="smAmount">Quantity Units *</label>
        <input type="number" name="amount" id="smAmount" class="form-control" min="0" value="1" required oninput="calculateStockBalance()">
      </div>

      <div class="form-group">
        <label>Resulting Balance Preview</label>
        <div id="smBalancePreview" style="padding: 10px 14px; border-radius: 6px; font-weight: 700; background: #f0fdf4; color: #166534; font-size: 14px;">
          New Balance: 0 unit(s)
        </div>
      </div>

      <div class="form-group">
        <label for="smReason">Mandatory Audit Reason *</label>
        <select name="reason" id="smReason" class="form-control" required>
          <option value="restock">Restock (Vendor Shipment / Inflow)</option>
          <option value="manual_adjustment">Manual Adjustment (Physical Stock Audit Correction)</option>
          <option value="damage">Damage (Defective / Written Off)</option>
        </select>
      </div>

      <div class="form-group">
        <label for="smNote">Audit Note (Optional)</label>
        <input type="text" name="note" id="smNote" class="form-control" placeholder="e.g. PO-2026-99 received from vendor">
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 22px;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('stockModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" id="smSubmitBtn">Confirm &amp; Record Adjustment</button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 2: Bulk Status Update Modal -->
<!-- ========================================================================= -->
<div id="bulkStatusModal" class="admin-modal">
  <div class="admin-modal-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
      <h3 style="margin: 0; font-size: 18px; color: #0f172a;">⚡ Bulk Status Update</h3>
      <button type="button" onclick="closeModal('bulkStatusModal')" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #64748b;">✕</button>
    </div>

    <form method="post" action="bulk.php">
      <?= csrf_field() ?>
      <input type="hidden" name="bulk_action" value="status">
      <input type="hidden" name="selected_ids" class="bulkSelectedIdsInput" value="">

      <p style="color: #475569; font-size: 14px; margin-bottom: 16px;">
        Update status for <strong class="bulkModalCount">0</strong> selected product(s).
      </p>

      <div class="form-group">
        <label for="bulkNewStatus">New Product Status *</label>
        <select name="new_status" id="bulkNewStatus" class="form-control" required>
          <option value="published">Active (Published on Storefront)</option>
          <option value="draft">Draft (Hidden from Storefront)</option>
          <option value="archived">Archived (Catalog Archive)</option>
        </select>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 22px;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('bulkStatusModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Apply Status Change</button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 3: Bulk Assign Section Modal -->
<!-- ========================================================================= -->
<div id="bulkAssignModal" class="admin-modal">
  <div class="admin-modal-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
      <h3 style="margin: 0; font-size: 18px; color: #0f172a;">🏠 Bulk Homepage Section Assignment</h3>
      <button type="button" onclick="closeModal('bulkAssignModal')" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #64748b;">✕</button>
    </div>

    <form method="post" action="bulk.php">
      <?= csrf_field() ?>
      <input type="hidden" name="bulk_action" value="assign_section">
      <input type="hidden" name="selected_ids" class="bulkSelectedIdsInput" value="">

      <p style="color: #475569; font-size: 14px; margin-bottom: 16px;">
        Assign <strong class="bulkModalCount">0</strong> selected product(s) to a dedicated homepage section.
      </p>

      <div class="form-group">
        <label for="bulkAssignSec">Target Homepage Section *</label>
        <select name="section_key" id="bulkAssignSec" class="form-control" required>
          <option value="servers">▤ Servers Section</option>
          <option value="networking">🖧 Networking Section</option>
          <option value="components">▦ Components Section</option>
          <option value="our-products">🛍️ Our Products Section</option>
          <option value="featured">🏷️ Featured &amp; Promotional Deals</option>
        </select>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 22px;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('bulkAssignModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Assign Section</button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 4: Bulk Remove Section Modal -->
<!-- ========================================================================= -->
<div id="bulkRemoveModal" class="admin-modal">
  <div class="admin-modal-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
      <h3 style="margin: 0; font-size: 18px; color: #0f172a;">✕ Remove Homepage Section Placement</h3>
      <button type="button" onclick="closeModal('bulkRemoveModal')" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #64748b;">✕</button>
    </div>

    <form method="post" action="bulk.php">
      <?= csrf_field() ?>
      <input type="hidden" name="bulk_action" value="remove_section">
      <input type="hidden" name="selected_ids" class="bulkSelectedIdsInput" value="">

      <p style="color: #475569; font-size: 14px; margin-bottom: 16px;">
        Remove section placements for <strong class="bulkModalCount">0</strong> selected product(s).
      </p>

      <div class="form-group">
        <label for="bulkRemoveSec">Section to Remove *</label>
        <select name="section_key" id="bulkRemoveSec" class="form-control" required>
          <option value="all">✕ All Homepage Sections</option>
          <option value="servers">▤ Servers Section</option>
          <option value="networking">🖧 Networking Section</option>
          <option value="components">▦ Components Section</option>
          <option value="our-products">🛍️ Our Products Section</option>
          <option value="featured">🏷️ Featured Section</option>
        </select>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 22px;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('bulkRemoveModal')">Cancel</button>
        <button type="submit" class="btn btn-danger">Confirm Removal</button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL 5: Bulk Price Update Modal with Dynamic Live Preview -->
<!-- ========================================================================= -->
<div id="bulkPriceModal" class="admin-modal">
  <div class="admin-modal-content" style="max-width: 780px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
      <h3 style="margin: 0; font-size: 18px; color: #0f172a;">🏷️ Bulk Price Adjustment</h3>
      <button type="button" onclick="closeModal('bulkPriceModal')" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #64748b;">✕</button>
    </div>

    <form method="post" action="bulk.php" id="bulkPriceForm">
      <?= csrf_field() ?>
      <input type="hidden" name="bulk_action" value="price_update">
      <input type="hidden" name="selected_ids" class="bulkSelectedIdsInput" value="">

      <p style="color: #475569; font-size: 14px; margin-bottom: 16px;">
        Adjust selling prices for <strong class="bulkModalCount">0</strong> selected product(s). Changes are previewed below before committing.
      </p>

      <div class="form-row-3" style="margin-bottom: 16px;">
        <div class="form-group">
          <label>Adjustment Mode *</label>
          <select name="price_mode" id="bpMode" class="form-control" onchange="fetchPricePreview()">
            <option value="percent">Percentage (%)</option>
            <option value="fixed">Fixed Rupee Amount (₹)</option>
          </select>
        </div>

        <div class="form-group">
          <label>Direction *</label>
          <select name="price_direction" id="bpDirection" class="form-control" onchange="fetchPricePreview()">
            <option value="increase">Increase (+)</option>
            <option value="decrease">Decrease (-)</option>
          </select>
        </div>

        <div class="form-group">
          <label>Value *</label>
          <input type="number" step="0.01" min="0.01" name="price_value" id="bpValue" class="form-control" value="10" required oninput="fetchPricePreview()">
        </div>
      </div>

      <!-- Preview Container -->
      <div style="margin-bottom: 18px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
          <strong style="font-size: 13px; color: #334155;">Proposed Price Preview Table:</strong>
          <span id="previewSummaryText" style="font-size: 12px; color: #64748b;">Loading preview...</span>
        </div>

        <div style="max-height: 240px; overflow-y: auto; border: 1px solid #cbd5e1; border-radius: 6px;">
          <table class="admin-table" style="font-size: 12.5px;" id="pricePreviewTable">
            <thead>
              <tr style="position: sticky; top: 0; background: #f1f5f9; z-index: 2;">
                <th>Product</th>
                <th>Current Price</th>
                <th>MRP</th>
                <th>Proposed Price</th>
                <th>Difference</th>
                <th>Validation</th>
              </tr>
            </thead>
            <tbody id="pricePreviewTbody">
              <tr><td colspan="6" style="text-align: center; color: #64748b; padding: 20px;">Calculating preview...</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 22px;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('bulkPriceModal')">Cancel</button>
        <button type="submit" class="btn btn-primary" id="bpSubmitBtn">Confirm &amp; Apply Price Update</button>
      </div>
    </form>
  </div>
</div>

<script>
// =========================================================================
// Client-side Interactive Logic: Filters, Checkboxes, Modals, Audits, Live Previews
// =========================================================================

function toggleFilterPanel() {
  const panel = document.getElementById('filterCollapsiblePanel');
  const btn = document.getElementById('filterToggleBtn');
  if (panel) {
    panel.classList.toggle('is-expanded');
    const expanded = panel.classList.contains('is-expanded');
    if (btn) btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
  }
}

let selectedIds = new Set();

const selectAllChk = document.getElementById('selectAllCheckbox');
const rowCheckboxes = document.querySelectorAll('.product-chk');
const bulkActionBar = document.getElementById('bulkActionBar');
const bulkCountDisplays = document.querySelectorAll('.bulkModalCount');
const bulkSelectedCount = document.getElementById('bulkSelectedCount');
const bulkInputs = document.querySelectorAll('.bulkSelectedIdsInput');

selectAllChk?.addEventListener('change', function() {
  rowCheckboxes.forEach(chk => {
    chk.checked = selectAllChk.checked;
    const id = parseInt(chk.value, 10);
    const row = document.getElementById('row-' + id);
    const mRow = document.getElementById('m-row-' + id);
    if (chk.checked) {
      selectedIds.add(id);
      if (row) row.style.backgroundColor = '#f0fdf4';
      if (mRow) mRow.style.backgroundColor = '#f0fdf4';
    } else {
      selectedIds.delete(id);
      if (row) row.style.backgroundColor = '';
      if (mRow) mRow.style.backgroundColor = '';
    }
  });
  updateSelectionUi();
});

rowCheckboxes.forEach(chk => {
  chk.addEventListener('change', function() {
    const id = parseInt(this.value, 10);
    const row = document.getElementById('row-' + id);
    const mRow = document.getElementById('m-row-' + id);

    // Sync all matching checkboxes for this id (desktop & mobile)
    const allMatches = document.querySelectorAll(`.product-chk[value="${id}"]`);
    allMatches.forEach(c => c.checked = this.checked);

    if (this.checked) {
      selectedIds.add(id);
      if (row) row.style.backgroundColor = '#f0fdf4';
      if (mRow) mRow.style.backgroundColor = '#f0fdf4';
    } else {
      selectedIds.delete(id);
      if (row) row.style.backgroundColor = '';
      if (mRow) mRow.style.backgroundColor = '';
    }
    if (selectAllChk) {
      selectAllChk.checked = (selectedIds.size > 0 && selectedIds.size === rowCheckboxes.length);
    }
    updateSelectionUi();
  });
});

function updateSelectionUi() {
  const count = selectedIds.size;
  if (count > 0) {
    bulkActionBar.style.display = 'flex';
    bulkSelectedCount.textContent = count.toString();
    bulkCountDisplays.forEach(el => el.textContent = count.toString());
    const idsString = Array.from(selectedIds).join(',');
    bulkInputs.forEach(inp => inp.value = idsString);
  } else {
    bulkActionBar.style.display = 'none';
    if (selectAllChk) selectAllChk.checked = false;
  }
}

function clearProductSelections() {
  selectedIds.clear();
  rowCheckboxes.forEach(chk => {
    chk.checked = false;
    const row = document.getElementById('row-' + chk.value);
    if (row) row.style.backgroundColor = '';
  });
  updateSelectionUi();
}

// Modal utilities
function openModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.add('is-open');
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.remove('is-open');
}

// Close modal when pressing Escape or clicking outside content
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    document.querySelectorAll('.admin-modal.is-open').forEach(m => m.classList.remove('is-open'));
  }
});

document.querySelectorAll('.admin-modal').forEach(m => {
  m.addEventListener('click', function(e) {
    if (e.target === this) {
      this.classList.remove('is-open');
    }
  });
});

// Single Product Stock Adjustment Modal
let currentStockValue = 0;

function openStockModal(id, title, sku, stock) {
  document.getElementById('smProductId').value = id;
  document.getElementById('smProductTitle').textContent = title;
  document.getElementById('smProductSku').textContent = sku;
  document.getElementById('smCurrentStock').textContent = stock;
  document.getElementById('smAmount').value = 1;
  document.getElementById('smType').value = 'add';
  document.getElementById('smReason').value = 'restock';
  document.getElementById('smNote').value = '';
  currentStockValue = stock;
  calculateStockBalance();
  openModal('stockModal');
}

function calculateStockBalance() {
  const type = document.getElementById('smType').value;
  const amount = parseInt(document.getElementById('smAmount').value, 10) || 0;
  const preview = document.getElementById('smBalancePreview');
  const submitBtn = document.getElementById('smSubmitBtn');

  let newBal = currentStockValue;
  if (type === 'add') {
    newBal = currentStockValue + amount;
  } else if (type === 'subtract') {
    newBal = currentStockValue - amount;
  } else if (type === 'set') {
    newBal = amount;
  }

  if (newBal < 0) {
    preview.style.background = '#fef2f2';
    preview.style.color = '#991b1b';
    preview.textContent = `Invalid: Resulting stock cannot be negative (${newBal} units)`;
    submitBtn.disabled = true;
  } else {
    preview.style.background = '#f0fdf4';
    preview.style.color = '#166534';
    const diff = newBal - currentStockValue;
    const diffStr = diff > 0 ? `+${diff}` : `${diff}`;
    preview.textContent = `New Balance: ${newBal} unit(s) (${diffStr})`;
    submitBtn.disabled = false;
  }
}

// Bulk Modals
function openBulkModal(action) {
  if (selectedIds.size === 0) {
    alert('Please select at least one product using the checkboxes.');
    return;
  }

  if (action === 'status') {
    openModal('bulkStatusModal');
  } else if (action === 'assign_section') {
    openModal('bulkAssignModal');
  } else if (action === 'remove_section') {
    openModal('bulkRemoveModal');
  } else if (action === 'price_update') {
    openModal('bulkPriceModal');
    fetchPricePreview();
  }
}

// Bulk Price Preview via Fetch
let previewTimeout = null;

function fetchPricePreview() {
  clearTimeout(previewTimeout);
  previewTimeout = setTimeout(() => {
    const ids = Array.from(selectedIds).join(',');
    const mode = document.getElementById('bpMode').value;
    const direction = document.getElementById('bpDirection').value;
    const val = parseFloat(document.getElementById('bpValue').value) || 0;
    const tbody = document.getElementById('pricePreviewTbody');
    const summary = document.getElementById('previewSummaryText');
    const submitBtn = document.getElementById('bpSubmitBtn');

    if (val <= 0) {
      tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:#dc2626;">Please enter a value greater than 0.</td></tr>';
      submitBtn.disabled = true;
      return;
    }

    summary.textContent = 'Calculating preview...';

    fetch(`bulk.php?action=price_preview&ids=${encodeURIComponent(ids)}&mode=${encodeURIComponent(mode)}&direction=${encodeURIComponent(direction)}&value=${encodeURIComponent(val)}`)
      .then(res => res.json())
      .then(data => {
        if (!data.success) {
          tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; color:#dc2626;">${data.error || 'Preview error'}</td></tr>`;
          submitBtn.disabled = true;
          return;
        }

        let html = '';
        data.preview.forEach(row => {
          let badgeClass = 'badge-green';
          if (row.status === 'error') badgeClass = 'badge-red';
          else if (row.status === 'warning') badgeClass = 'badge-yellow';

          html += `
            <tr>
              <td><strong>${escapeHtml(row.title)}</strong><br><small style="color:#64748b;">${escapeHtml(row.sku)}</small></td>
              <td>${row.old_price_formatted}</td>
              <td>${row.mrp_formatted}</td>
              <td><strong>${row.new_price_formatted}</strong></td>
              <td><span style="color:${direction === 'increase' ? '#166534' : '#dc2626'}; font-weight:700;">${row.difference_formatted}</span></td>
              <td><span class="badge ${badgeClass}">${escapeHtml(row.message)}</span></td>
            </tr>
          `;
        });

        tbody.innerHTML = html;
        summary.textContent = `${data.valid_count} valid, ${data.error_count} error(s)`;
        submitBtn.disabled = (data.valid_count === 0);
      })
      .catch(err => {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:#dc2626;">Failed to load live preview.</td></tr>';
        summary.textContent = 'Preview request failed';
      });
  }, 250);
}

function escapeHtml(str) {
  if (!str) return '';
  return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>

<?php
require_once __DIR__ . '/../includes/admin-footer.php';
