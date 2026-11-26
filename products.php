<?php
declare(strict_types=1);

/**
 * Product Catalog & Search Controller
 * iboltnetworks E-Commerce Platform
 */

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/auth.php';
require_once __DIR__ . '/app/helpers/csrf.php';
require_once __DIR__ . '/app/helpers/sanitize.php';
require_once __DIR__ . '/app/helpers/currency.php';

init_session();

try {
    $db = Database::getConnection();
} catch (Throwable $e) {
    error_log("[iboltnetworks] products.php DB error: " . $e->getMessage());
    render_controlled_error_page("Product Catalog Temporarily Unavailable", "Our product catalog is temporarily undergoing database maintenance. Please contact our technical sales team via WhatsApp or telephone at +91 99110 03865 for instant hardware pricing and stock verification.", 503);
    exit;
}

// 1. Process Request Parameters
$categorySlug = clean_string($_GET['category'] ?? '');
$brandSlug    = clean_string($_GET['brand'] ?? '');
$condition    = clean_string($_GET['condition'] ?? '');
$section      = clean_string($_GET['section'] ?? '');
$searchQuery  = clean_string($_GET['q'] ?? $_GET['search'] ?? '');
$modelSlug    = clean_string($_GET['model'] ?? '');
$sort         = clean_string($_GET['sort'] ?? 'popular');
$page         = max(1, sanitize_int($_GET['page'] ?? 1, 1));
$perPage      = 16;
$offset       = ($page - 1) * $perPage;

// If model query parameter was passed directly from mega menu, redirect to product detail
if (!empty($modelSlug)) {
    header("Location: product.php?slug=" . urlencode($modelSlug));
    exit;
}

// 2. Resolve Active Category / Brand Metadata for Page Title & Headers
$activeCategory = null;
if (!empty($categorySlug)) {
    $catStmt = $db->prepare("SELECT * FROM categories WHERE slug = ? LIMIT 1");
    $catStmt->execute([$categorySlug]);
    $activeCategory = $catStmt->fetch();
}

$activeBrand = null;
if (!empty($brandSlug)) {
    $brandStmt = $db->prepare("SELECT * FROM brands WHERE slug = ? LIMIT 1");
    $brandStmt->execute([$brandSlug]);
    $activeBrand = $brandStmt->fetch();
}

// Compute Title & Banner Header
if (!empty($searchQuery)) {
    $pageHeading = "Search Results for \"" . e($searchQuery) . "\"";
    $pageSubheading = "Browse hardware models matching your search query.";
    $pageTitle = "Search: " . e($searchQuery) . " | iboltnetworks";
} elseif ($activeCategory && $activeBrand) {
    $pageHeading = e($activeBrand['name']) . " " . e($activeCategory['name']);
    $pageSubheading = e($activeCategory['description'] ?? "Explore our certified hardware inventory.");
    $pageTitle = e($activeBrand['name']) . " " . e($activeCategory['name']) . " | iboltnetworks";
} elseif ($activeCategory) {
    $pageHeading = e($activeCategory['name']);
    $pageSubheading = e($activeCategory['description'] ?? "Enterprise hardware and components.");
    $pageTitle = e($activeCategory['name']) . " | iboltnetworks";
} elseif ($activeBrand) {
    $pageHeading = e($activeBrand['name']) . " Products";
    $pageSubheading = "Enterprise compute, networking, and component solutions by " . e($activeBrand['name']) . ".";
    $pageTitle = e($activeBrand['name']) . " Hardware Catalog | iboltnetworks";
} elseif ($section === 'our-products') {
    $pageHeading = "Our Products & Accessories";
    $pageSubheading = "Explore computer accessories, laptop essentials, LED monitors, and peripherals.";
    $pageTitle = "Computer Accessories & Peripherals | iboltnetworks";
} else {
    $pageHeading = "All Hardware Catalog";
    $pageSubheading = "Enterprise servers, networking equipment, components, and workstation hardware.";
    $pageTitle = "Servers & Enterprise Hardware Catalog | iboltnetworks";
}

// 3. Build Dynamic Filtering SQL Query
$where = ["p.status = 'published'"];
$params = [];

if (!empty($categorySlug)) {
    // Match the category or any direct children
    $where[] = "(c.slug = :cat_slug OR c.parent_id IN (SELECT id FROM categories WHERE slug = :cat_parent))";
    $params[':cat_slug'] = $categorySlug;
    $params[':cat_parent'] = $categorySlug;
}

if (!empty($brandSlug)) {
    $where[] = "b.slug = :brand_slug";
    $params[':brand_slug'] = $brandSlug;
}

if (!empty($condition) && in_array(strtolower($condition), ['new', 'refurbished'], true)) {
    $where[] = "p.condition_type = :condition";
    $params[':condition'] = strtolower($condition);
}

if (!empty($section)) {
    $where[] = "c.section = :section";
    $params[':section'] = $section;
}

if (!empty($searchQuery)) {
    $where[] = "(p.title LIKE :q1 OR p.short_description LIKE :q2 OR p.sku LIKE :q3 OR p.model LIKE :q4 OR b.name LIKE :q5)";
    $like = '%' . $searchQuery . '%';
    $params[':q1'] = $like;
    $params[':q2'] = $like;
    $params[':q3'] = $like;
    $params[':q4'] = $like;
    $params[':q5'] = $like;
}

$whereSql = implode(' AND ', $where);

// Sorting logic
$orderSql = match ($sort) {
    'price-asc'  => 'p.price_paise ASC',
    'price-desc' => 'p.price_paise DESC',
    'name-asc'   => 'p.title ASC',
    'name-desc'  => 'p.title DESC',
    'newest'     => 'p.created_at DESC',
    default      => 'p.is_featured DESC, p.views_count DESC, p.id DESC'
};

// Count total matching items
$countSql = "SELECT COUNT(*) FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             LEFT JOIN brands b ON p.brand_id = b.id
             WHERE {$whereSql}";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalProducts = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalProducts / $perPage));

// Fetch paginated products with primary images
$querySql = "SELECT p.*,
                    b.name AS brand_name,
                    b.slug AS brand_slug,
                    c.name AS category_name,
                    c.slug AS category_slug,
                    c.section AS category_section,
                    (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             LEFT JOIN brands b ON p.brand_id = b.id
             WHERE {$whereSql}
             ORDER BY {$orderSql}
             LIMIT {$perPage} OFFSET {$offset}";

$stmt = $db->prepare($querySql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Fetch filter options for sidebar/dropdowns
$allBrands = $db->query("SELECT name, slug FROM brands WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$allTopCategories = $db->query("SELECT name, slug, section FROM categories WHERE parent_id IS NULL AND is_active = 1 ORDER BY sort_order ASC, name ASC")->fetchAll();

// Render header
$extraHead = <<<'HTML'
<style>
  .catalog-wrapper { background: #f6f8fa; padding: 30px 0 60px; min-height: 650px; }
  .catalog-container { width: min(var(--content-width), calc(100% - 48px)); margin: 0 auto; }
  
  .breadcrumbs { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--muted); margin-bottom: 24px; flex-wrap: wrap; }
  .breadcrumbs a { color: #078e49; text-decoration: none; font-weight: 500; }
  .breadcrumbs a:hover { text-decoration: underline; }
  .breadcrumbs span.current { color: #2b2b2b; font-weight: 600; }

  .category-header {
    background: linear-gradient(135deg, #078e49 0%, #056633 100%);
    color: #ffffff;
    border-radius: 12px;
    padding: 36px 40px;
    margin-bottom: 28px;
    box-shadow: 0 4px 20px rgba(7,142,73,0.15);
  }
  .category-header h1 { margin: 0 0 8px; font-size: 30px; font-weight: 800; }
  .category-header p { margin: 0; font-size: 15px; opacity: 0.94; max-width: 800px; line-height: 1.5; }

  /* Toolbar / Filters */
  .catalog-toolbar {
    background: #ffffff;
    border: 1px solid #e1e7ec;
    border-radius: 10px;
    padding: 16px 20px;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
  }
  .results-count { font-size: 14px; color: #475569; font-weight: 600; }
  .toolbar-controls { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
  .filter-select {
    padding: 8px 14px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: #ffffff;
    font-size: 13px;
    color: #1f2937;
    outline: none;
    cursor: pointer;
  }
  .filter-select:focus { border-color: #078e49; ring: 2px solid rgba(7,142,73,0.2); }
  .btn-reset-filter {
    font-size: 13px;
    color: #dc2626;
    text-decoration: none;
    font-weight: 600;
    padding: 8px 12px;
    border: 1px solid #fee2e2;
    background: #fef2f2;
    border-radius: 6px;
  }
  .btn-reset-filter:hover { background: #fee2e2; }

  /* Product Grid */
  .category-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 24px;
  }
  .server-card {
    background: #ffffff;
    border: 1px solid #e1e7ec;
    border-radius: 10px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    position: relative;
    transition: transform 0.2s, box-shadow 0.2s;
  }
  .server-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(0,0,0,0.08);
  }
  .server-card-image {
    background: #fbfcfd;
    padding: 24px 20px;
    height: 190px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-bottom: 1px solid #edf1f4;
    position: relative;
  }
  .server-card-image img {
    max-height: 135px;
    max-width: 100%;
    object-fit: contain;
    transition: transform 0.2s;
  }
  .server-card:hover .server-card-image img { transform: scale(1.04); }
  
  .badge-condition {
    position: absolute;
    top: 12px;
    left: 12px;
    background: #078e49;
    color: #fff;
    font-size: 10.5px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 3px 8px;
    border-radius: 14px;
    z-index: 2;
  }
  .badge-condition.refurbished { background: #1e70bf; }

  .server-card-body {
    padding: 20px;
    display: flex;
    flex-direction: column;
    flex: 1;
  }
  .server-card-body .brand {
    font-size: 11px;
    font-weight: 800;
    color: #078e49;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
  }
  .server-card-body h3 {
    font-size: 15.5px;
    font-weight: 700;
    color: #111;
    margin: 0 0 10px;
    line-height: 1.35;
    min-height: 42px;
  }
  .server-card-body h3 a { color: #111; text-decoration: none; }
  .server-card-body h3 a:hover { color: #078e49; }

  .specs-pill {
    font-size: 12px;
    color: #64748b;
    margin-bottom: 16px;
    line-height: 1.45;
    flex: 1;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }

  .card-pricing {
    margin-bottom: 14px;
    display: flex;
    align-items: baseline;
    gap: 8px;
  }
  .card-price {
    font-size: 20px;
    font-weight: 800;
    color: #111827;
  }
  .card-compare-price {
    font-size: 13px;
    color: #94a3b8;
    text-decoration: line-through;
  }

  .server-card-body .card-footer {
    display: flex;
    gap: 8px;
    align-items: center;
    padding-top: 14px;
    border-top: 1px solid #f1f5f9;
  }
  .view-btn {
    background: #f1f5f9;
    color: #334155;
    font-weight: 700;
    font-size: 12.5px;
    padding: 9px 12px;
    border-radius: 6px;
    text-decoration: none;
    text-align: center;
    flex: 1;
    transition: background 0.2s;
  }
  .view-btn:hover { background: #e2e8f0; color: #0f172a; }
  .btn-add-cart {
    background: #078e49;
    color: #ffffff;
    font-weight: 700;
    font-size: 12.5px;
    padding: 9px 14px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    flex: 1;
    text-align: center;
    transition: background 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
  }
  .btn-add-cart:hover { background: #06773d; }

  /* Empty State */
  .catalog-empty {
    background: #ffffff;
    border: 1px solid #e1e7ec;
    border-radius: 12px;
    padding: 60px 24px;
    text-align: center;
  }
  .catalog-empty h3 { font-size: 20px; color: #1e293b; margin: 0 0 10px; }
  .catalog-empty p { color: #64748b; font-size: 14px; margin: 0 0 20px; }

  /* Pagination */
  .pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    margin-top: 40px;
  }
  .pagination a, .pagination span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 38px;
    height: 38px;
    padding: 0 10px;
    border-radius: 6px;
    border: 1px solid #d1d5db;
    background: #ffffff;
    color: #374151;
    font-size: 13.5px;
    font-weight: 600;
    text-decoration: none;
  }
  .pagination a:hover { background: #f3f4f6; border-color: #9ca3af; }
  .pagination span.active { background: #078e49; color: #ffffff; border-color: #078e49; }
</style>
HTML;

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navigation.php';
?>

<div class="catalog-wrapper">
  <div class="catalog-container">

    <!-- Breadcrumbs -->
    <nav class="breadcrumbs" aria-label="Breadcrumb">
      <a href="index.php">Home</a>
      <span aria-hidden="true">›</span>
      <?php if (!empty($section)): ?>
        <a href="products.php?section=<?= e($section) ?>"><?= e(ucwords(str_replace('-', ' ', $section))) ?></a>
        <span aria-hidden="true">›</span>
      <?php endif; ?>
      <?php if ($activeCategory): ?>
        <a href="products.php?category=<?= e($activeCategory['slug']) ?>"><?= e($activeCategory['name']) ?></a>
        <span aria-hidden="true">›</span>
      <?php endif; ?>
      <?php if ($activeBrand): ?>
        <span class="current"><?= e($activeBrand['name']) ?></span>
      <?php elseif (!empty($searchQuery)): ?>
        <span class="current">Search: "<?= e($searchQuery) ?>"</span>
      <?php else: ?>
        <span class="current">Products</span>
      <?php endif; ?>
    </nav>

    <!-- Category / Filter Headline -->
    <div class="category-header">
      <h1><?= $pageHeading ?></h1>
      <p><?= $pageSubheading ?></p>
    </div>

    <!-- Toolbar / Sort / Filters -->
    <div class="catalog-toolbar">
      <div class="results-count">
        Showing <strong><?= count($products) ?></strong> of <strong><?= $totalProducts ?></strong> items
      </div>

      <div class="toolbar-controls">
        <!-- Sort Selector Form -->
        <form method="get" action="products.php" style="display: flex; gap: 8px; align-items: center;">
          <?php if (!empty($categorySlug)): ?><input type="hidden" name="category" value="<?= e($categorySlug) ?>"><?php endif; ?>
          <?php if (!empty($brandSlug)): ?><input type="hidden" name="brand" value="<?= e($brandSlug) ?>"><?php endif; ?>
          <?php if (!empty($condition)): ?><input type="hidden" name="condition" value="<?= e($condition) ?>"><?php endif; ?>
          <?php if (!empty($section)): ?><input type="hidden" name="section" value="<?= e($section) ?>"><?php endif; ?>
          <?php if (!empty($searchQuery)): ?><input type="hidden" name="q" value="<?= e($searchQuery) ?>"><?php endif; ?>

          <label for="sort-select" class="visually-hidden">Sort by</label>
          <select id="sort-select" name="sort" class="filter-select" onchange="this.form.submit()">
            <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Sort: Popularity</option>
            <option value="price-asc" <?= $sort === 'price-asc' ? 'selected' : '' ?>>Price: Low to High</option>
            <option value="price-desc" <?= $sort === 'price-desc' ? 'selected' : '' ?>>Price: High to Low</option>
            <option value="name-asc" <?= $sort === 'name-asc' ? 'selected' : '' ?>>Name: A to Z</option>
            <option value="name-desc" <?= $sort === 'name-desc' ? 'selected' : '' ?>>Name: Z to A</option>
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest Arrivals</option>
          </select>
        </form>

        <!-- Condition Filter Form -->
        <form method="get" action="products.php" style="display: flex; gap: 8px; align-items: center;">
          <?php if (!empty($categorySlug)): ?><input type="hidden" name="category" value="<?= e($categorySlug) ?>"><?php endif; ?>
          <?php if (!empty($brandSlug)): ?><input type="hidden" name="brand" value="<?= e($brandSlug) ?>"><?php endif; ?>
          <?php if (!empty($section)): ?><input type="hidden" name="section" value="<?= e($section) ?>"><?php endif; ?>
          <?php if (!empty($searchQuery)): ?><input type="hidden" name="q" value="<?= e($searchQuery) ?>"><?php endif; ?>
          <?php if (!empty($sort)): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>

          <label for="condition-select" class="visually-hidden">Condition</label>
          <select id="condition-select" name="condition" class="filter-select" onchange="this.form.submit()">
            <option value="">Condition: All</option>
            <option value="new" <?= $condition === 'new' ? 'selected' : '' ?>>Brand New</option>
            <option value="refurbished" <?= $condition === 'refurbished' ? 'selected' : '' ?>>Certified Refurbished</option>
          </select>
        </form>

        <?php if (!empty($categorySlug) || !empty($brandSlug) || !empty($condition) || !empty($searchQuery)): ?>
          <a href="products.php<?= !empty($section) ? '?section=' . e($section) : '' ?>" class="btn-reset-filter">✕ Reset Filters</a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Product Grid or Empty State -->
    <?php if (empty($products)): ?>
      <div class="catalog-empty">
        <h3>No Products Found</h3>
        <p>We couldn't find any hardware matching your filter criteria. Try adjusting your search or clear active filters.</p>
        <a href="products.php" class="btn-quote" style="display: inline-block;">View All Products</a>
      </div>
    <?php else: ?>
      <div class="category-grid">
        <?php foreach ($products as $p): ?>
          <?php
            $imgSrc = !empty($p['primary_image']) ? $p['primary_image'] : 'images/server-hud.png';
            $condClass = ($p['condition_type'] === 'refurbished') ? 'refurbished' : 'new';
            $condLabel = ($p['condition_type'] === 'refurbished') ? 'Refurbished' : 'New';
          ?>
          <div class="server-card">
            <span class="badge-condition <?= $condClass ?>"><?= e($condLabel) ?></span>
            
            <a href="product.php?slug=<?= e($p['slug']) ?>" class="server-card-image" aria-label="<?= e($p['title']) ?>">
              <img src="<?= e($imgSrc) ?>" alt="<?= e($p['title']) ?>" loading="lazy">
            </a>

            <div class="server-card-body">
              <span class="brand"><?= e($p['brand_name'] ?? 'iboltnetworks') ?></span>
              <h3><a href="product.php?slug=<?= e($p['slug']) ?>"><?= e($p['title']) ?></a></h3>

              <div class="specs-pill">
                <?= e($p['short_description'] ?? 'Enterprise computing solution.') ?>
              </div>

              <div class="card-pricing">
                <span class="card-price"><?= format_inr((int)$p['price_paise']) ?></span>
                <?php if (!empty($p['compare_price_paise']) && $p['compare_price_paise'] > $p['price_paise']): ?>
                  <span class="card-compare-price"><?= format_inr((int)$p['compare_price_paise']) ?></span>
                <?php endif; ?>
              </div>

              <div class="card-footer">
                <a href="product.php?slug=<?= e($p['slug']) ?>" class="view-btn">Details</a>
                
                <form action="cart.php" method="post" style="flex: 1;" class="ajax-add-cart-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="add">
                  <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                  <input type="hidden" name="quantity" value="1">
                  <button type="submit" class="btn-add-cart">+ Add</button>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
        <?php
          // Preserve query parameters for pagination links
          $queryParams = $_GET;
        ?>
        <div class="pagination">
          <?php if ($page > 1): ?>
            <?php $queryParams['page'] = $page - 1; ?>
            <a href="products.php?<?= http_build_query($queryParams) ?>" aria-label="Previous Page">‹ Prev</a>
          <?php endif; ?>

          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php if ($i === $page): ?>
              <span class="active"><?= $i ?></span>
            <?php elseif ($i <= 3 || $i >= $totalPages - 2 || abs($i - $page) <= 1): ?>
              <?php $queryParams['page'] = $i; ?>
              <a href="products.php?<?= http_build_query($queryParams) ?>"><?= $i ?></a>
            <?php elseif ($i === 4 && $page > 4): ?>
              <span>...</span>
            <?php elseif ($i === $totalPages - 3 && $page < $totalPages - 3): ?>
              <span>...</span>
            <?php endif; ?>
          <?php endfor; ?>

          <?php if ($page < $totalPages): ?>
            <?php $queryParams['page'] = $page + 1; ?>
            <a href="products.php?<?= http_build_query($queryParams) ?>" aria-label="Next Page">Next ›</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>

  </div>
</div>

<?php
$extraScripts = <<<'HTML'
<script>
  // AJAX Add-to-Cart Progressive Enhancement
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.ajax-add-cart-form').forEach(form => {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = form.querySelector('button[type="submit"]');
        const origText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Adding...';

        try {
          const formData = new FormData(form);
          const response = await fetch('cart.php?format=json', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
          });
          const data = await response.json();

          if (data.success) {
            submitBtn.textContent = '✓ Added!';
            submitBtn.style.background = '#056633';
            // Update cart badges
            document.querySelectorAll('#cart-count').forEach(b => b.textContent = data.total_items);
            if (typeof window.showDemoToast === 'function') {
              window.showDemoToast('Item Added to Cart', data.message || 'Product successfully added to your cart.');
            }
            setTimeout(() => {
              submitBtn.textContent = origText;
              submitBtn.style.background = '';
              submitBtn.disabled = false;
            }, 1800);
          } else {
            alert(data.error || 'Failed to add item to cart.');
            submitBtn.textContent = origText;
            submitBtn.disabled = false;
          }
        } catch (err) {
          // If AJAX fails, submit standard form
          form.submit();
        }
      });
    });
  });
</script>
HTML;

require_once __DIR__ . '/includes/footer.php';
