<?php
declare(strict_types=1);

if (ob_get_level() === 0) {
    ob_start();
}

/**
 * Admin Product Create & Edit Form Controller
 * iboltnetworks E-Commerce Platform
 */

$adminRoot = '../';

require_once __DIR__ . '/../includes/admin-header.php';
require_once __DIR__ . '/../../app/helpers/homepage.php';
require_once __DIR__ . '/../../app/helpers/uploader.php';

$db = Database::getConnection();

$productId = sanitize_int($_GET['id'] ?? $_POST['id'] ?? 0);
$isEditing = ($productId > 0);

$product = [
    'title'               => '',
    'slug'                => '',
    'sku'                 => 'IB-PRD-' . rand(5000, 9999),
    'model'               => '',
    'category_id'         => '',
    'brand_id'            => '',
    'condition_type'      => 'new',
    'status'              => 'published',
    'is_featured'         => 0,
    'price_paise'         => 0,
    'compare_price_paise' => 0,
    'cost_price_paise'    => 0,
    'stock_quantity'      => 10,
    'low_stock_threshold' => 3,
    'short_description'   => '',
    'description'         => '',
    'primary_image'       => 'images/server-hud.png'
];

$existingSpecs = [];
$existingImages = [];
$assignedSections = [];

if ($isEditing) {
    $stmt = $db->prepare("SELECT p.*,
                                 (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image
                          FROM products p
                          WHERE p.id = ? LIMIT 1");
    $stmt->execute([$productId]);
    $found = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($found) {
        $product = array_merge($product, $found);
    } else {
        header("Location: index.php");
        exit;
    }

    // Specifications
    $specStmt = $db->prepare("SELECT * FROM product_specifications WHERE product_id = ? ORDER BY sort_order ASC, id ASC");
    $specStmt->execute([$productId]);
    $existingSpecs = $specStmt->fetchAll(PDO::FETCH_ASSOC);

    // Images
    $imgStmt = $db->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC");
    $imgStmt->execute([$productId]);
    $existingImages = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

    // Homepage section assignments
    $assignedSections = get_product_section_assignments($productId);
}

$error = '';
$flashSuccess = clean_string($_GET['success'] ?? '');

$allCategories = $db->query("SELECT id, name, section FROM categories ORDER BY section ASC, sort_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
$allBrands = $db->query("SELECT id, name FROM brands ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$allSections = get_homepage_sections(false);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Security session expired. Please re-submit.';
    } else {
        $title           = clean_string($_POST['title'] ?? '');
        $slug            = slugify(clean_string($_POST['slug'] ?? ''));
        $sku             = clean_string($_POST['sku'] ?? '');
        $model           = clean_string($_POST['model'] ?? '');
        $categoryId      = sanitize_int($_POST['category_id'] ?? 0) ?: null;
        $brandId         = sanitize_int($_POST['brand_id'] ?? 0) ?: null;
        $condition       = clean_string($_POST['condition_type'] ?? 'new');
        $status          = clean_string($_POST['status'] ?? 'published');
        $isFeatured      = sanitize_int($_POST['is_featured'] ?? 0);
        $priceInr        = sanitize_float($_POST['price_inr'] ?? 0);
        $comparePriceInr = sanitize_float($_POST['compare_price_inr'] ?? 0);
        $costPriceInr    = sanitize_float($_POST['cost_price_inr'] ?? 0);
        $stockQuantity   = sanitize_int($_POST['stock_quantity'] ?? 0);
        $lowThreshold    = sanitize_int($_POST['low_stock_threshold'] ?? 3);
        $shortDesc       = clean_string($_POST['short_description'] ?? '');
        $desc            = clean_string($_POST['description'] ?? '');
        $selectedSecs    = $_POST['homepage_sections'] ?? [];
        $secBadges       = $_POST['section_badges'] ?? [];
        $secOrders       = $_POST['section_orders'] ?? [];

        if (empty($slug)) {
            $slug = slugify($title);
        }

        if (empty($title)) {
            $error = 'Please enter a product title.';
        } elseif (empty($sku)) {
            $error = 'Please enter a SKU code.';
        } elseif ($priceInr <= 0) {
            $error = 'Selling price must be greater than zero.';
        } else {
            $pricePaise = inr_to_paise($priceInr);
            $comparePricePaise = $comparePriceInr > 0 ? inr_to_paise($comparePriceInr) : null;
            $costPricePaise = $costPriceInr > 0 ? inr_to_paise($costPriceInr) : null;

            try {
                $db->beginTransaction();

                if ($isEditing) {
                    $oldStock = (int)$product['stock_quantity'];

                    $updateSql = "UPDATE products SET
                                    category_id = ?, brand_id = ?, title = ?, slug = ?, sku = ?, model = ?,
                                    condition_type = ?, short_description = ?, description = ?,
                                    price_paise = ?, compare_price_paise = ?, cost_price_paise = ?,
                                    stock_quantity = ?, low_stock_threshold = ?, status = ?, is_featured = ?
                                  WHERE id = ?";
                    $db->prepare($updateSql)->execute([
                        $categoryId, $brandId, $title, $slug, $sku, $model,
                        $condition, $shortDesc, $desc,
                        $pricePaise, $comparePricePaise, $costPricePaise,
                        $stockQuantity, $lowThreshold, $status, $isFeatured,
                        $productId
                    ]);

                    // Audit stock change if modified
                    if ($stockQuantity !== $oldStock) {
                        $diff = $stockQuantity - $oldStock;
                        $db->prepare("INSERT INTO inventory_movements (product_id, quantity_change, balance_after, reason, admin_id, note)
                                      VALUES (?, ?, ?, 'manual_adjustment', ?, 'Admin manual adjustment')")
                           ->execute([$productId, $diff, $stockQuantity, $admin['id']]);
                    }

                    $targetProductId = $productId;

                } else {
                    $insertSql = "INSERT INTO products (category_id, brand_id, title, slug, sku, model, condition_type, short_description, description, price_paise, compare_price_paise, cost_price_paise, stock_quantity, low_stock_threshold, status, is_featured)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $db->prepare($insertSql)->execute([
                        $categoryId, $brandId, $title, $slug, $sku, $model,
                        $condition, $shortDesc, $desc,
                        $pricePaise, $comparePricePaise, $costPricePaise,
                        $stockQuantity, $lowThreshold, $status, $isFeatured
                    ]);
                    $targetProductId = (int)$db->lastInsertId();

                    // Initial default image placeholder if no images uploaded
                    $defaultImgPath = clean_string($_POST['default_image_path'] ?? 'images/server-hud.png');
                    $db->prepare("INSERT INTO product_images (product_id, image_path, alt_text, is_primary, sort_order)
                                  VALUES (?, ?, ?, 1, 1)")
                       ->execute([$targetProductId, $defaultImgPath, $title]);

                    // Inventory audit
                    $db->prepare("INSERT INTO inventory_movements (product_id, quantity_change, balance_after, reason, admin_id, note)
                                  VALUES (?, ?, ?, 'restock', ?, 'Initial product creation')")
                       ->execute([$targetProductId, $stockQuantity, $stockQuantity, $admin['id']]);
                }

                // 2. Handle Image Uploads if files were selected
                if (!empty($_FILES['product_images']) && !empty($_FILES['product_images']['name'][0])) {
                    $uploadResults = upload_multiple_product_images($targetProductId, $_FILES['product_images']);
                    if (!$isEditing) {
                        $hasReal = false;
                        foreach ($uploadResults as $ur) {
                            if (!empty($ur['success'])) {
                                $hasReal = true;
                                break;
                            }
                        }
                        if ($hasReal) {
                            $db->prepare("DELETE FROM product_images WHERE product_id = ? AND image_path = 'images/server-hud.png'")->execute([$targetProductId]);
                            $firstImg = $db->query("SELECT id FROM product_images WHERE product_id = {$targetProductId} ORDER BY sort_order ASC, id ASC LIMIT 1")->fetchColumn();
                            if ($firstImg) {
                                set_primary_product_image($targetProductId, (int)$firstImg);
                            }
                        }
                    }
                }

                // 3. Set Primary Image if requested from existing images
                $primaryImgId = sanitize_int($_POST['primary_image_id'] ?? 0);
                if ($primaryImgId > 0) {
                    set_primary_product_image($targetProductId, $primaryImgId);
                }

                // 4. Handle Specifications
                if (isset($_POST['spec_keys']) && is_array($_POST['spec_keys'])) {
                    $db->prepare("DELETE FROM product_specifications WHERE product_id = ?")->execute([$targetProductId]);
                    $specInsert = $db->prepare("INSERT INTO product_specifications (product_id, group_name, spec_key, spec_value, sort_order) VALUES (?, 'Specifications', ?, ?, ?)");

                    $order = 0;
                    foreach ($_POST['spec_keys'] as $idx => $k) {
                        $k = clean_string($k);
                        $v = clean_string($_POST['spec_values'][$idx] ?? '');
                        if (!empty($k) && !empty($v)) {
                            $specInsert->execute([$targetProductId, $k, $v, $order++]);
                        }
                    }
                }

                // 5. Synchronize Homepage Sections Placement
                if (is_array($selectedSecs)) {
                    sync_product_homepage_sections($targetProductId, $selectedSecs, $secBadges, $secOrders);
                }

                // 6. Admin activity log
                $actType = $isEditing ? 'update_product' : 'create_product';
                $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, entity_type, entity_id, details, ip_address)
                              VALUES (?, ?, 'product', ?, ?, ?)")
                   ->execute([$admin['id'], $actType, $targetProductId, json_encode(['title' => $title, 'sku' => $sku, 'price_inr' => $priceInr]), $_SERVER['REMOTE_ADDR'] ?? '']);

                $db->commit();

                $redirectUrl = "form.php?id={$targetProductId}&success=" . urlencode("Product \"{$title}\" successfully saved!");
                if (!headers_sent()) {
                    if (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    header("Location: {$redirectUrl}");
                    exit;
                } else {
                    echo "<script>window.location.href='{$redirectUrl}';</script>";
                    exit;
                }

            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = 'Database operation failed: ' . $e->getMessage();
            }
        }
    }
}

$adminPageTitle = ($isEditing ? "Edit Product: " . e($product['title']) : "Add New Hardware Product") . " | iboltnetworks Admin";
?>

<div class="admin-container">

  <div class="admin-page-header">
    <div>
      <h1><?= $isEditing ? 'Edit Hardware Product' : 'Add New Hardware Product' ?></h1>
      <p>Configure product pricing, MRP discounts, multi-image gallery, homepage placement, and specifications.</p>
    </div>
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
      <a href="index.php" class="btn btn-secondary">← Back to Products List</a>
      <?php if ($isEditing): ?>
        <a href="../images/index.php?product_id=<?= (int)$productId ?>" class="btn btn-secondary">🖼️ Image Manager</a>
        <a href="../../product.php?slug=<?= e($product['slug']) ?>" target="_blank" class="btn btn-primary">Preview Storefront ↗</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($flashSuccess)): ?>
    <div class="alert alert-success"><?= e($flashSuccess) ?></div>
  <?php endif; ?>

  <?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" action="form.php<?= $isEditing ? '?id=' . $productId : '' ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $productId ?>">

    <!-- 1. Basic Information -->
    <div class="admin-card">
      <h2>1. Basic Information</h2>

      <div class="form-group">
        <label for="title">Product Name / Title *</label>
        <input type="text" id="title" name="title" class="form-control" value="<?= e($_POST['title'] ?? $product['title']) ?>" required placeholder="e.g. Dell PowerEdge R760 Rack Server">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="slug">Product Slug (URL friendly)</label>
          <input type="text" id="slug" name="slug" class="form-control" value="<?= e($_POST['slug'] ?? $product['slug']) ?>" placeholder="e.g. dell-poweredge-r760-rack-server">
        </div>
        <div class="form-group">
          <label for="sku">SKU Code *</label>
          <input type="text" id="sku" name="sku" class="form-control" value="<?= e($_POST['sku'] ?? $product['sku']) ?>" required placeholder="IB-SRV-1001">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="model">Model Number / Hardware Code</label>
          <input type="text" id="model" name="model" class="form-control" value="<?= e($_POST['model'] ?? $product['model']) ?>" placeholder="e.g. R760">
        </div>
        <div class="form-group">
          <label for="brand_id">Hardware Brand</label>
          <select id="brand_id" name="brand_id" class="form-control">
            <option value="">Select Brand</option>
            <?php foreach ($allBrands as $b): ?>
              <?php $sel = ((string)$b['id'] === (string)($_POST['brand_id'] ?? $product['brand_id'])) ? 'selected' : ''; ?>
              <option value="<?= $b['id'] ?>" <?= $sel ?>><?= e($b['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="category_id">Category &amp; Storefront Section</label>
          <select id="category_id" name="category_id" class="form-control">
            <option value="">Select Category</option>
            <?php foreach ($allCategories as $c): ?>
              <?php $sel = ((string)$c['id'] === (string)($_POST['category_id'] ?? $product['category_id'])) ? 'selected' : ''; ?>
              <option value="<?= $c['id'] ?>" <?= $sel ?>><?= e($c['name']) ?> (Section: <?= e($c['section']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="condition_type">Hardware Condition</label>
          <select id="condition_type" name="condition_type" class="form-control">
            <?php $activeCond = $_POST['condition_type'] ?? $product['condition_type']; ?>
            <option value="new" <?= $activeCond === 'new' ? 'selected' : '' ?>>Brand New</option>
            <option value="refurbished" <?= $activeCond === 'refurbished' ? 'selected' : '' ?>>Certified Refurbished</option>
            <option value="open_box" <?= $activeCond === 'open_box' ? 'selected' : '' ?>>Open Box</option>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="status">Publication Status</label>
          <select id="status" name="status" class="form-control">
            <?php $activeStatus = $_POST['status'] ?? $product['status']; ?>
            <option value="published" <?= $activeStatus === 'published' ? 'selected' : '' ?>>Published (Visible on storefront)</option>
            <option value="draft" <?= $activeStatus === 'draft' ? 'selected' : '' ?>>Draft (Hidden / Inactive)</option>
            <option value="archived" <?= $activeStatus === 'archived' ? 'selected' : '' ?>>Archived</option>
          </select>
        </div>
        <div class="form-group">
          <label for="is_featured">Featured Flag</label>
          <select id="is_featured" name="is_featured" class="form-control">
            <?php $actFt = (int)($_POST['is_featured'] ?? $product['is_featured']); ?>
            <option value="0" <?= $actFt === 0 ? 'selected' : '' ?>>Standard Product</option>
            <option value="1" <?= $actFt === 1 ? 'selected' : '' ?>>★ Featured Spotlight Product</option>
          </select>
        </div>
      </div>
    </div>

    <!-- 2. Pricing & Inventory Management -->
    <div class="admin-card">
      <h2>2. Pricing &amp; Inventory</h2>

      <div class="form-row-3">
        <div class="form-group">
          <label for="price_inr">Selling Price (INR ₹) *</label>
          <input type="number" step="0.01" id="price_inr" name="price_inr" class="form-control" value="<?= e((string)($_POST['price_inr'] ?? paise_to_inr((int)$product['price_paise']))) ?>" required placeholder="95000.00" oninput="calculateDiscount()">
        </div>
        <div class="form-group">
          <label for="compare_price_inr">Original / MRP Price (INR ₹)</label>
          <input type="number" step="0.01" id="compare_price_inr" name="compare_price_inr" class="form-control" value="<?= e((string)($_POST['compare_price_inr'] ?? ($product['compare_price_paise'] ? paise_to_inr((int)$product['compare_price_paise']) : ''))) ?>" placeholder="125000.00" oninput="calculateDiscount()">
        </div>
        <div class="form-group">
          <label>Calculated Customer Discount</label>
          <div id="discountDisplay" style="padding: 10px 14px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; font-weight: 700; color: #166534; font-size: 13.5px;">
            No discount set
          </div>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="stock_quantity">Available Inventory Stock *</label>
          <input type="number" id="stock_quantity" name="stock_quantity" class="form-control" value="<?= (int)($_POST['stock_quantity'] ?? $product['stock_quantity']) ?>" required min="0">
        </div>
        <div class="form-group">
          <label for="low_stock_threshold">Low Stock Warning Threshold *</label>
          <input type="number" id="low_stock_threshold" name="low_stock_threshold" class="form-control" value="<?= (int)($_POST['low_stock_threshold'] ?? $product['low_stock_threshold']) ?>" required min="1">
        </div>
      </div>
    </div>

    <!-- 3. Flexible Product Image Management -->
    <div class="admin-card">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
        <h2 style="margin: 0;">3. Product Image Gallery Management</h2>
        <span style="font-size: 12.5px; color: #64748b;">Upload single or multiple images &bull; Set primary photo</span>
      </div>

      <!-- Multiple file upload input -->
      <div style="background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 10px; padding: 20px; text-align: center; margin-bottom: 20px;">
        <p style="margin: 0 0 10px; font-weight: 700; color: #334155; font-size: 14px;">Upload Additional Product Photos</p>
        <input type="file" name="product_images[]" id="productImagesInput" multiple accept="image/jpeg,image/png,image/webp,image/gif" class="form-control" style="max-width: 400px; margin: 0 auto;" onchange="previewSelectedImages(this)">
        <p style="font-size: 12px; color: #64748b; margin: 8px 0 0;">Upload any number of images. Supported formats: JPG, PNG, WebP, GIF (Max 8MB each).</p>

        <!-- Dynamic previews of files about to be uploaded -->
        <div id="newImagesPreview" style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin-top: 14px;"></div>
      </div>

      <!-- Existing product images -->
      <?php if (!empty($existingImages)): ?>
        <h3>Current Images (<?= count($existingImages) ?>)</h3>
        <p style="font-size: 12.5px; color: #64748b; margin: 0 0 12px;">Choose which image serves as the Primary image on product cards and listings.</p>
        
        <div class="image-grid">
          <?php foreach ($existingImages as $img): ?>
            <?php $isPrimary = ((int)$img['is_primary'] === 1); ?>
            <div class="image-card <?= $isPrimary ? 'is-primary' : '' ?>">
              <img src="<?= e($adminRoot . get_product_thumbnail_path($img['image_path'])) ?>" alt="<?= e($img['alt_text'] ?? 'Product photo') ?>">
              <label style="font-size: 12px; font-weight: 700; display: flex; align-items: center; gap: 6px; cursor: pointer; margin-bottom: 6px;">
                <input type="radio" name="primary_image_id" value="<?= (int)$img['id'] ?>" <?= $isPrimary ? 'checked' : '' ?>>
                <?= $isPrimary ? '★ Primary Image' : 'Set as Primary' ?>
              </label>
              <div style="font-size: 11px; color: #64748b;">Order: <?= (int)$img['sort_order'] ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="form-group" style="margin-top: 10px;">
          <label for="default_image_path">Or Fallback Relative Image Path</label>
          <input type="text" id="default_image_path" name="default_image_path" class="form-control" value="<?= e($product['primary_image']) ?>" placeholder="images/server-hud.png">
        </div>
      <?php endif; ?>
    </div>

    <!-- 4. Homepage Section Placement -->
    <div class="admin-card">
      <h2>4. Homepage Section Placement</h2>
      <p style="color: #64748b; font-size: 13.5px; margin: 0 0 16px;">
        Choose which sections of the public homepage this hardware product should appear in. A product can appear across multiple sections without duplicating database records.
      </p>

      <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px;">
        <?php foreach ($allSections as $sec): ?>
          <?php
          $secKey = $sec['section_key'];
          $isAssigned = isset($assignedSections[$secKey]);
          $assignment = $assignedSections[$secKey] ?? null;
          ?>
          <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; background: <?= $isAssigned ? '#f0fdf4' : '#ffffff' ?>;">
            <label style="display: flex; align-items: center; gap: 8px; font-weight: 700; cursor: pointer; margin-bottom: 8px;">
              <input type="checkbox" name="homepage_sections[]" value="<?= e($secKey) ?>" <?= $isAssigned ? 'checked' : '' ?>>
              <span style="font-size: 16px;"><?= get_section_icon($secKey) ?></span>
              <span><?= e($sec['name']) ?></span>
            </label>
            <div style="display: flex; gap: 8px; align-items: center; margin-top: 8px;">
              <input type="number" name="section_orders[<?= e($secKey) ?>]" value="<?= (int)($assignment['sort_order'] ?? 1) ?>" placeholder="Order" title="Display Order" class="form-control" style="width: 70px; padding: 4px 6px; font-size: 12px; text-align: center;">
              <input type="text" name="section_badges[<?= e($secKey) ?>]" value="<?= e($assignment['badge_text'] ?? '') ?>" placeholder="Badge (e.g. Save 10%)" class="form-control" style="padding: 4px 6px; font-size: 12px;">
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- 5. Technical Specifications & Descriptions -->
    <div class="admin-card">
      <h2>5. Descriptions &amp; Technical Specifications</h2>

      <div class="form-group">
        <label for="short_description">Short Summary</label>
        <textarea id="short_description" name="short_description" rows="2" class="form-control" placeholder="Brief technical summary displayed on homepage cards..."><?= e($_POST['short_description'] ?? $product['short_description']) ?></textarea>
      </div>

      <div class="form-group">
        <label for="description">Full Technical Overview</label>
        <textarea id="description" name="description" rows="5" class="form-control" placeholder="Detailed architectural, compute, and connectivity specifications..."><?= e($_POST['description'] ?? $product['description']) ?></textarea>
      </div>

      <h3>Dynamic Technical Specifications</h3>
      <p style="font-size: 13px; color: #64748b; margin: 0 0 12px;">Add or remove specification attributes (e.g. Processors, Memory, Storage Bays, Form Factor, RAID).</p>

      <div id="specs-container">
        <?php if (!empty($existingSpecs)): ?>
          <?php foreach ($existingSpecs as $sp): ?>
            <div class="form-row" style="margin-bottom: 10px;">
              <input type="text" name="spec_keys[]" value="<?= e($sp['spec_key']) ?>" class="form-control" placeholder="Key (e.g. Processors)">
              <div style="display: flex; gap: 6px;">
                <input type="text" name="spec_values[]" value="<?= e($sp['spec_value']) ?>" class="form-control" placeholder="Value (e.g. Up to 2x Intel Xeon)">
                <button type="button" class="btn btn-danger btn-sm" style="min-width: 44px; min-height: 44px; justify-content: center;" onclick="this.parentElement.parentElement.remove()">✕</button>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="form-row" style="margin-bottom: 10px;">
            <input type="text" name="spec_keys[]" class="form-control" placeholder="Key (e.g. Processors)">
            <div style="display: flex; gap: 6px;">
              <input type="text" name="spec_values[]" class="form-control" placeholder="Value (e.g. 2x Intel Xeon Silver)">
              <button type="button" class="btn btn-danger btn-sm" style="min-width: 44px; min-height: 44px; justify-content: center;" onclick="this.parentElement.parentElement.remove()">✕</button>
            </div>
          </div>
          <div class="form-row" style="margin-bottom: 10px;">
            <input type="text" name="spec_keys[]" class="form-control" placeholder="Key (e.g. Memory)">
            <div style="display: flex; gap: 6px;">
              <input type="text" name="spec_values[]" class="form-control" placeholder="Value (e.g. 64GB DDR4 ECC)">
              <button type="button" class="btn btn-danger btn-sm" style="min-width: 44px; min-height: 44px; justify-content: center;" onclick="this.parentElement.parentElement.remove()">✕</button>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <button type="button" class="btn btn-secondary btn-sm" onclick="addSpecRow()" style="margin-top: 8px; min-height: 42px;">+ Add Specification Row</button>
    </div>

    <!-- Submit Action Bar -->
    <div style="margin-top: 24px; display: flex; gap: 14px; align-items: center; flex-wrap: wrap;">
      <button type="submit" class="btn btn-primary" style="padding: 13px 34px; font-size: 15px; min-height: 48px;">
        <?= $isEditing ? 'Save Product Changes' : 'Create Hardware Product' ?>
      </button>

      <?php if ($isEditing): ?>
        <a href="../../product.php?slug=<?= e($product['slug']) ?>" target="_blank" class="btn btn-secondary" style="padding: 13px 24px; font-size: 15px; min-height: 48px;">
          Preview on Storefront ↗
        </a>
      <?php endif; ?>
    </div>
  </form>

</div>

<script>
  function calculateDiscount() {
    const priceInput = parseFloat(document.getElementById('price_inr').value) || 0;
    const mrpInput = parseFloat(document.getElementById('compare_price_inr').value) || 0;
    const display = document.getElementById('discountDisplay');

    if (mrpInput > priceInput && priceInput > 0) {
      const saving = mrpInput - priceInput;
      const pct = Math.round((saving / mrpInput) * 100);
      display.style.color = '#166534';
      display.textContent = `${pct}% OFF • Saves ₹${saving.toLocaleString('en-IN', {minimumFractionDigits: 2})}`;
    } else {
      display.style.color = '#64748b';
      display.textContent = 'No discount set';
    }
  }

  function addSpecRow() {
    const container = document.getElementById('specs-container');
    const row = document.createElement('div');
    row.className = 'form-row';
    row.style.marginBottom = '10px';
    row.innerHTML = `
      <input type="text" name="spec_keys[]" class="form-control" placeholder="Key (e.g. Drive Bays)">
      <div style="display: flex; gap: 6px;">
        <input type="text" name="spec_values[]" class="form-control" placeholder="Value (e.g. 8x 2.5\\" SAS/SATA)">
        <button type="button" class="btn btn-danger btn-sm" style="min-width: 44px; min-height: 44px; justify-content: center;" onclick="this.parentElement.parentElement.remove()">✕</button>
      </div>
    `;
    container.appendChild(row);
  }

  function previewSelectedImages(input) {
    const previewGrid = document.getElementById('newImagesPreview');
    previewGrid.innerHTML = '';
    if (input.files) {
      Array.from(input.files).forEach((file, index) => {
        const reader = new FileReader();
        reader.onload = function(e) {
          const wrap = document.createElement('div');
          wrap.style.cssText = 'position: relative; width: 70px; height: 70px; border: 1px solid #cbd5e1; border-radius: 6px; overflow: hidden; background: #fff; padding: 2px;';
          wrap.innerHTML = `<img src="${e.target.result}" style="width: 100%; height: 100%; object-fit: contain;">
                            <span style="position: absolute; bottom: 2px; right: 2px; background: rgba(7,142,73,0.85); color: #fff; font-size: 9px; padding: 1px 4px; border-radius: 3px;">#${index+1}</span>`;
          previewGrid.appendChild(wrap);
        }
        reader.readAsDataURL(file);
      });
    }
  }

  // Calculate discount on initial load
  window.addEventListener('DOMContentLoaded', calculateDiscount);
</script>

<?php
require_once __DIR__ . '/../includes/admin-footer.php';
