<?php
declare(strict_types=1);

/**
 * Product Detail View Controller
 * iboltnetworks E-Commerce Platform
 */

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/auth.php';
require_once __DIR__ . '/app/helpers/csrf.php';
require_once __DIR__ . '/app/helpers/sanitize.php';
require_once __DIR__ . '/app/helpers/currency.php';
require_once __DIR__ . '/app/helpers/whatsapp.php';
require_once __DIR__ . '/app/helpers/uploader.php';

init_session();

try {
    $db = Database::getConnection();
} catch (Throwable $e) {
    error_log("[iboltnetworks] product.php DB error: " . $e->getMessage());
    render_controlled_error_page("Product Detail Unavailable", "Our product detail service is temporarily undergoing database maintenance. Please contact our technical sales team via WhatsApp or telephone at +91 99110 03865 for immediate specifications, availability, and quotes.", 503);
    exit;
}

// 1. Resolve Product by slug or id
$slug = clean_string($_GET['slug'] ?? $_GET['model'] ?? '');
$id   = sanitize_int($_GET['id'] ?? 0);

$product = null;
if (!empty($slug)) {
    $stmt = $db->prepare("SELECT p.*,
                                 b.name AS brand_name,
                                 b.slug AS brand_slug,
                                 c.name AS category_name,
                                 c.slug AS category_slug,
                                 c.section AS category_section,
                                 c.parent_id AS category_parent_id
                          FROM products p
                          LEFT JOIN brands b ON p.brand_id = b.id
                          LEFT JOIN categories c ON p.category_id = c.id
                          WHERE p.slug = ? AND p.status = 'published'
                          LIMIT 1");
    $stmt->execute([$slug]);
    $product = $stmt->fetch();
} elseif ($id > 0) {
    $stmt = $db->prepare("SELECT p.*,
                                 b.name AS brand_name,
                                 b.slug AS brand_slug,
                                 c.name AS category_name,
                                 c.slug AS category_slug,
                                 c.section AS category_section,
                                 c.parent_id AS category_parent_id
                          FROM products p
                          LEFT JOIN brands b ON p.brand_id = b.id
                          LEFT JOIN categories c ON p.category_id = c.id
                          WHERE p.id = ? AND p.status = 'published'
                          LIMIT 1");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
}

// 2. Handle 404 if product not found
if (!$product) {
    http_response_code(404);
    $pageTitle = "Product Not Found | iboltnetworks";
    $pageDescription = "The requested hardware model could not be found in our inventory.";
    require_once __DIR__ . '/includes/header.php';
    require_once __DIR__ . '/includes/navigation.php';
    ?>
    <div style="background: #f6f8fa; padding: 80px 20px; min-height: 500px; text-align: center;">
      <div style="max-width: 600px; margin: 0 auto; background: #fff; padding: 48px; border-radius: 12px; border: 1px solid #e1e7ec; box-shadow: 0 4px 18px rgba(0,0,0,0.06);">
        <h1 style="font-size: 28px; color: #1e293b; margin: 0 0 12px;">Product Not Found</h1>
        <p style="color: #64748b; font-size: 15px; margin-bottom: 24px; line-height: 1.6;">
          The hardware item or server configuration you are looking for is currently unavailable or has been archived.
        </p>
        <a href="products.php" class="btn-quote" style="display: inline-block; background: #078e49; color: #fff; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 700;">
          Browse Hardware Catalog
        </a>
      </div>
    </div>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Increment view count
$db->prepare("UPDATE products SET views_count = views_count + 1 WHERE id = ?")->execute([$product['id']]);

// 3. Fetch Product Images
$imgStmt = $db->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC");
$imgStmt->execute([$product['id']]);
$images = $imgStmt->fetchAll();
$primaryImage = !empty($images[0]['image_path']) ? $images[0]['image_path'] : 'images/server-hud.png';

// 4. Fetch Specifications grouped by group_name
$specStmt = $db->prepare("SELECT * FROM product_specifications WHERE product_id = ? ORDER BY sort_order ASC, id ASC");
$specStmt->execute([$product['id']]);
$specs = $specStmt->fetchAll();

// 5. Fetch Related Products from same category or brand
$relStmt = $db->prepare("SELECT p.*,
                                b.name AS brand_name,
                                (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image
                         FROM products p
                         LEFT JOIN brands b ON p.brand_id = b.id
                         WHERE p.id != ? AND (p.category_id = ? OR p.brand_id = ?) AND p.status = 'published'
                         ORDER BY p.is_featured DESC, p.id DESC
                         LIMIT 4");
$relStmt->execute([$product['id'], $product['category_id'], $product['brand_id']]);
$relatedProducts = $relStmt->fetchAll();

// Page metadata
$pageTitle = e($product['title']) . " | iboltnetworks";
$pageDescription = e($product['short_description'] ?? "Enterprise hardware and server components.");

$condClass = ($product['condition_type'] === 'refurbished') ? 'refurbished' : 'new';
$condLabel = ($product['condition_type'] === 'refurbished') ? 'Certified Refurbished' : 'Brand New';

$baseUrl = rtrim((string)config('app_url', ''), '/');
if (empty($baseUrl)) {
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}
$productFullUrl = $baseUrl . '/product.php?slug=' . urlencode($product['slug']);
$whatsappMsg = build_product_whatsapp_message($product, 1, $productFullUrl);
$whatsappLink = build_whatsapp_url($whatsappMsg);

$extraHead = <<<'HTML'
<style>
  .catalog-wrapper { background: #f6f8fa; padding: 30px 0 60px; min-height: 600px; }
  .catalog-container { width: min(var(--content-width), calc(100% - 48px)); margin: 0 auto; }
  
  .breadcrumbs { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--muted); margin-bottom: 24px; flex-wrap: wrap; }
  .breadcrumbs a { color: #078e49; text-decoration: none; font-weight: 500; }
  .breadcrumbs a:hover { text-decoration: underline; }
  .breadcrumbs span.current { color: #2b2b2b; font-weight: 600; }

  /* Product Detail Card */
  .product-detail-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e1e7ec;
    box-shadow: 0 4px 20px rgba(0,0,0,0.06);
    padding: 36px;
    display: grid;
    grid-template-columns: 460px 1fr;
    gap: 40px;
    margin-bottom: 40px;
  }
  .product-gallery {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: #fbfcfd;
    border: 1px solid #edf1f4;
    border-radius: 10px;
    padding: 30px 20px;
    position: relative;
    min-height: 380px;
  }
  .product-gallery img.main-img {
    max-width: 100%;
    max-height: 290px;
    object-fit: contain;
    filter: drop-shadow(0 10px 18px rgba(0,0,0,0.12));
    transition: transform 0.25s ease;
  }
  .product-gallery img.main-img:hover { transform: scale(1.05); }

  .badge-condition {
    position: absolute;
    top: 14px;
    left: 14px;
    background: #078e49;
    color: #fff;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 4px 10px;
    border-radius: 20px;
    z-index: 2;
  }
  .badge-condition.refurbished { background: #1e70bf; }

  .product-info { display: flex; flex-direction: column; }
  .product-info .brand-tag {
    font-size: 13px;
    font-weight: 800;
    color: #078e49;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 6px;
  }
  .product-info h1 {
    font-size: 26px;
    font-weight: 800;
    color: #111;
    margin: 0 0 10px;
    line-height: 1.3;
  }
  .sku-meta { font-size: 12.5px; color: #64748b; margin-bottom: 14px; }
  .product-rating {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13.5px;
    color: #64748b;
    margin-bottom: 18px;
  }
  .stars { color: #f59e0b; letter-spacing: 2px; }

  /* Price Area */
  .price-box {
    background: #f8fafc;
    border: 1px solid #eef2f6;
    border-radius: 8px;
    padding: 16px 20px;
    margin-bottom: 22px;
    display: flex;
    align-items: baseline;
    gap: 14px;
    flex-wrap: wrap;
  }
  .price-main { font-size: 30px; font-weight: 800; color: #0f172a; }
  .price-compare { font-size: 16px; color: #94a3b8; text-decoration: line-through; }
  .price-badge-save {
    background: #dcfce7;
    color: #166534;
    font-size: 12px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 4px;
  }

  .product-highlights {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 16px 18px;
    margin-bottom: 24px;
  }
  .highlight-item { display: flex; flex-direction: column; }
  .highlight-item .label { font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
  .highlight-item .value { font-size: 13.5px; color: #1e293b; font-weight: 700; margin-top: 2px; }

  /* Order Action Form */
  .action-form-wrap {
    margin-top: auto;
    padding-top: 14px;
  }
  .quantity-row {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 18px;
  }
  .qty-label { font-size: 14px; font-weight: 700; color: #334155; }
  .qty-selector {
    display: inline-flex;
    align-items: center;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    overflow: hidden;
  }
  .qty-btn {
    background: #f8fafc;
    border: none;
    width: 36px;
    height: 38px;
    cursor: pointer;
    font-size: 16px;
    font-weight: 700;
    color: #334155;
    transition: background 0.15s;
  }
  .qty-btn:hover { background: #e2e8f0; }
  .qty-input {
    width: 48px;
    height: 38px;
    border: none;
    text-align: center;
    font-size: 14px;
    font-weight: 700;
    color: #0f172a;
    outline: none;
  }

  .product-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
  }
  .btn-add-cart-detail {
    background: #078e49;
    color: #ffffff;
    font-weight: 700;
    font-size: 15px;
    padding: 13px 28px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: background 0.2s;
  }
  .btn-add-cart-detail:hover { background: #06773d; }
  .btn-buy-now {
    background: #f33349;
    color: #ffffff;
    font-weight: 700;
    font-size: 15px;
    padding: 13px 28px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: background 0.2s;
    text-decoration: none;
  }
  .btn-buy-now:hover { background: #dc263b; }
  .btn-whatsapp-detail {
    background: #25d366;
    color: #ffffff;
    font-weight: 700;
    font-size: 14px;
    padding: 13px 20px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    transition: background 0.2s;
  }
  .btn-whatsapp-detail:hover { background: #20ba5a; }

  /* Specifications Table */
  .specs-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e1e7ec;
    padding: 32px;
    margin-bottom: 40px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.04);
  }
  .specs-card h2 {
    margin: 0 0 20px;
    font-size: 20px;
    color: #111;
    border-bottom: 2px solid #edf2f5;
    padding-bottom: 12px;
  }
  .specs-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
  }
  .specs-table tr { border-bottom: 1px solid #f0f3f6; }
  .specs-table th {
    text-align: left;
    padding: 12px 16px;
    width: 260px;
    color: #475569;
    background: #fbfcfd;
    font-weight: 600;
  }
  .specs-table td { padding: 12px 16px; color: #1e293b; }

  /* Related Products */
  .related-card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 20px;
  }

  @media (max-width: 980px) {
    .product-detail-card { grid-template-columns: 1fr; }
    .product-gallery { min-height: 280px; }
  }
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
      <?php if (!empty($product['category_section'])): ?>
        <a href="products.php?section=<?= e($product['category_section']) ?>">
          <?= e(ucwords(str_replace('-', ' ', $product['category_section']))) ?>
        </a>
        <span aria-hidden="true">›</span>
      <?php endif; ?>
      <?php if (!empty($product['category_slug'])): ?>
        <a href="products.php?category=<?= e($product['category_slug']) ?>">
          <?= e($product['category_name']) ?>
        </a>
        <span aria-hidden="true">›</span>
      <?php endif; ?>
      <span class="current"><?= e($product['title']) ?></span>
    </nav>

    <!-- Product Detail Card -->
    <div class="product-detail-card">
      <div class="product-gallery">
        <span class="badge-condition <?= $condClass ?>"><?= e($condLabel) ?></span>
        <img src="<?= e($primaryImage) ?>" alt="<?= e($product['title']) ?>" class="main-img" id="product-main-view">
        <?php if (count($images) > 1): ?>
          <div class="product-thumbnails" style="display: flex; gap: 8px; margin-top: 14px; flex-wrap: wrap; justify-content: center;">
            <?php foreach ($images as $img): ?>
              <?php
                $thumbSrc = function_exists('get_product_thumbnail_path') ? get_product_thumbnail_path($img['image_path']) : $img['image_path'];
                $imgAlt = !empty($img['alt_text']) ? $img['alt_text'] : ($product['title'] . ' photo');
              ?>
              <button type="button" onclick="document.getElementById('product-main-view').src='<?= e($img['image_path']) ?>'" style="border: 1px solid #cbd5e1; background: #fff; padding: 4px; border-radius: 6px; cursor: pointer;" title="<?= e($imgAlt) ?>">
                <img src="<?= e($thumbSrc) ?>" alt="<?= e($imgAlt) ?>" width="48" height="48" style="width: 48px; height: 48px; object-fit: contain;" loading="lazy">
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="product-info">
        <span class="brand-tag"><?= e($product['brand_name'] ?? 'iboltnetworks') ?></span>
        <h1><?= e($product['title']) ?></h1>
        <div class="sku-meta">
          Model: <strong><?= e($product['model'] ?? 'N/A') ?></strong> • SKU: <strong><?= e($product['sku']) ?></strong>
        </div>

        <div class="product-rating">
          <span class="stars">★★★★★</span>
          <span>4.8 (19 customer reviews)</span>
          <span>•</span>
          <span style="color: #078e49; font-weight: 700;">● In Stock • Ready to Dispatch</span>
        </div>

        <!-- Price Area -->
        <div class="price-box">
          <span class="price-main"><?= format_inr((int)$product['price_paise']) ?></span>
          <?php if (!empty($product['compare_price_paise']) && $product['compare_price_paise'] > $product['price_paise']): ?>
            <span class="price-compare"><?= format_inr((int)$product['compare_price_paise']) ?></span>
            <?php
              $savingPercent = round((($product['compare_price_paise'] - $product['price_paise']) / $product['compare_price_paise']) * 100);
            ?>
            <span class="price-badge-save"><?= $savingPercent ?>% OFF</span>
          <?php endif; ?>
        </div>

        <!-- Quick Spec Highlights -->
        <div class="product-highlights">
          <div class="highlight-item">
            <span class="label">Condition</span>
            <span class="value"><?= e($condLabel) ?></span>
          </div>
          <div class="highlight-item">
            <span class="label">Warranty</span>
            <span class="value">3-Year Comprehensive</span>
          </div>
          <div class="highlight-item">
            <span class="label">Testing</span>
            <span class="value">Burn-In Tested</span>
          </div>
          <div class="highlight-item">
            <span class="label">Delivery</span>
            <span class="value">Fast Pan-India Dispatch</span>
          </div>
        </div>

        <!-- Purchase & Inquiry Actions -->
        <div class="action-form-wrap">
          <form action="cart.php" method="post" id="detail-add-cart-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">

            <div class="quantity-row">
              <span class="qty-label">Quantity:</span>
              <div class="qty-selector">
                <button type="button" class="qty-btn" onclick="adjustQty(-1)">−</button>
                <input type="number" name="quantity" id="detail-qty-input" value="1" min="1" max="<?= max(1, (int)$product['stock_quantity']) ?>" class="qty-input">
                <button type="button" class="qty-btn" onclick="adjustQty(1)">+</button>
              </div>
            </div>

            <div class="product-actions">
              <button type="submit" class="btn-add-cart-detail">
                🛒 Add to Cart
              </button>

              <button type="submit" name="buy_now" value="1" class="btn-buy-now">
                ⚡ Buy Now
              </button>

              <a href="<?= e($whatsappLink) ?>" target="_blank" rel="noopener noreferrer" class="btn-whatsapp-detail" id="btn-product-whatsapp" style="display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                <svg style="width: 18px; height: 18px; fill: currentColor;" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.312.045-.694.06-2.072-.511-1.636-.679-2.73-2.316-2.812-2.426-.082-.109-.661-.88-.661-1.677 0-.797.413-1.189.56-1.348.146-.16.32-.2.427-.2.106 0 .213 0 .306.006.1.006.233-.038.365.279.133.319.453 1.106.493 1.187.04.08.067.174.013.281-.053.107-.08.174-.16.267-.08.093-.167.208-.239.28-.08.08-.163.167-.07.327.094.16.417.688.894 1.114.614.547 1.132.717 1.292.797.16.08.254.067.347-.04.094-.107.4-.467.507-.627.107-.16.213-.133.36-.08.147.053.934.44 1.094.52.16.08.267.12.307.187.04.066.04.386-.104.791z"/></svg>
                Order via WhatsApp
              </a>
            </div>
          </form>
        </div>

      </div>
    </div>

    <!-- Specifications Table -->
    <div class="specs-card">
      <h2>Technical Specifications &amp; System Configuration</h2>
      <?php if (!empty($specs)): ?>
        <table class="specs-table">
          <tbody>
            <?php foreach ($specs as $s): ?>
              <tr>
                <th><?= e($s['spec_key']) ?></th>
                <td><?= e($s['spec_value']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p style="color: #64748b; font-size: 14px;">Detailed technical specs available upon request. Contact our certified solution architects for custom configurations.</p>
      <?php endif; ?>
    </div>

    <!-- Long Description -->
    <?php if (!empty($product['description'])): ?>
      <div class="specs-card">
        <h2>Product Overview</h2>
        <div style="font-size: 14.5px; line-height: 1.7; color: #334155;">
          <p><?= nl2br(e($product['description'])) ?></p>
        </div>
      </div>
    <?php endif; ?>

    <!-- Related Products -->
    <?php if (!empty($relatedProducts)): ?>
      <div class="specs-card" style="margin-top: 40px;">
        <h2>Related Hardware Solutions</h2>
        <div class="related-card-grid">
          <?php foreach ($relatedProducts as $rp): ?>
            <div class="server-card">
              <a href="product.php?slug=<?= e($rp['slug']) ?>" class="server-card-image" style="height: 160px;">
                <img src="<?= e($rp['primary_image'] ?? 'images/server-hud.png') ?>" alt="<?= e($rp['title']) ?>" style="max-height: 110px;">
              </a>
              <div class="server-card-body" style="padding: 16px;">
                <span class="brand"><?= e($rp['brand_name'] ?? 'iboltnetworks') ?></span>
                <h3 style="font-size: 14px; min-height: 38px;">
                  <a href="product.php?slug=<?= e($rp['slug']) ?>"><?= e($rp['title']) ?></a>
                </h3>
                <div class="card-pricing" style="margin-bottom: 8px;">
                  <span class="card-price" style="font-size: 17px;"><?= format_inr((int)$rp['price_paise']) ?></span>
                </div>
                <div class="card-footer" style="padding-top: 10px;">
                  <a href="product.php?slug=<?= e($rp['slug']) ?>" class="view-btn" style="padding: 6px 10px;">View Specs</a>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php
$extraScripts = <<<'HTML'
<script>
  function adjustQty(amount) {
    const input = document.getElementById('detail-qty-input');
    if (!input) return;
    let current = parseInt(input.value, 10) || 1;
    current = Math.max(1, current + amount);
    input.value = current;
  }
</script>
HTML;

require_once __DIR__ . '/includes/footer.php';
