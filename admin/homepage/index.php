<?php
declare(strict_types=1);

/**
 * Admin Homepage Products & Section Master Overview
 * Comprehensive management of all products across homepage sections,
 * section configurations, drag-and-drop reordering, badge validation, and live preview.
 * iboltnetworks E-Commerce Platform
 */

$adminRoot = '../';
$adminPageTitle = 'Homepage Products & Sections Master Overview | iboltnetworks Admin';

require_once __DIR__ . '/../includes/admin-header.php';
require_once __DIR__ . '/../../app/helpers/homepage.php';
require_once __DIR__ . '/../../app/helpers/uploader.php';

$db = Database::getConnection();

$message = '';
$error = '';

// Handle POST actions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $action = clean_string($_POST['action'] ?? '');
        $adminId = $admin['id'] ?? null;

        // 1. Toggle product visibility on homepage
        if ($action === 'toggle_visibility') {
            $hspId = sanitize_int($_POST['hsp_id'] ?? 0);
            $newVisible = sanitize_int($_POST['new_visible'] ?? 1);
            if (toggle_section_product_visibility($hspId, $newVisible, $adminId)) {
                $message = 'Homepage product visibility updated successfully!';
            } else {
                $error = 'Failed to update visibility.';
            }
        }

        // 2. Update placement order and badge text
        elseif ($action === 'update_placement') {
            $hspId = sanitize_int($_POST['hsp_id'] ?? 0);
            $sortOrder = sanitize_int($_POST['sort_order'] ?? 0);
            $rawBadge = clean_string($_POST['badge_text'] ?? '');

            // Fetch product prices to validate discount claim
            $itemInfoStmt = $db->prepare("SELECT p.price_paise, p.compare_price_paise, hsp.section_key, hsp.product_id
                                          FROM homepage_section_products hsp
                                          INNER JOIN products p ON hsp.product_id = p.id
                                          WHERE hsp.id = ? LIMIT 1");
            $itemInfoStmt->execute([$hspId]);
            $itemInfo = $itemInfoStmt->fetch(PDO::FETCH_ASSOC);

            if ($itemInfo) {
                $validatedBadge = validate_badge_text(
                    $rawBadge,
                    (int)$itemInfo['price_paise'],
                    $itemInfo['compare_price_paise'] !== null ? (int)$itemInfo['compare_price_paise'] : null
                );

                $db->prepare("UPDATE homepage_section_products SET sort_order = ?, badge_text = ?, updated_at = NOW() WHERE id = ?")
                   ->execute([$sortOrder, $validatedBadge, $hspId]);

                log_homepage_activity($adminId, 'section_update_placement', 'homepage_section_product', (int)$itemInfo['product_id'], [
                    'hsp_id'      => $hspId,
                    'section_key' => $itemInfo['section_key'],
                    'sort_order'  => $sortOrder,
                    'badge_text'  => $validatedBadge
                ]);

                $message = 'Display order and promotional badge updated successfully!';
            } else {
                $error = 'Homepage placement record not found.';
            }
        }

        // 3. Add product to homepage section
        elseif ($action === 'add_to_section') {
            $productId = sanitize_int($_POST['product_id'] ?? 0);
            $sectionKey = clean_string($_POST['section_key'] ?? '');
            $sortOrder = sanitize_int($_POST['sort_order'] ?? 0);
            $badgeText = clean_string($_POST['badge_text'] ?? '');
            $isFeatured = ($sectionKey === 'featured') ? 1 : 0;

            if ($productId <= 0 || empty($sectionKey)) {
                $error = 'Please select a valid product and homepage section.';
            } else {
                $res = assign_product_to_section($sectionKey, $productId, $sortOrder, $badgeText, $isFeatured, $adminId);
                if ($res['success']) {
                    $message = 'Product assigned to homepage section successfully!';
                } else {
                    $error = $res['error'];
                }
            }
        }

        // 4. Remove product from section
        elseif ($action === 'remove_from_section') {
            $hspId = sanitize_int($_POST['hsp_id'] ?? 0);
            if (remove_section_item_by_id($hspId, $adminId)) {
                $message = 'Product removed from this homepage section.';
            } else {
                $error = 'Failed to remove product from section.';
            }
        }

        // 5. Update section configuration (title, subtitle, icon, is_active, sort_order)
        elseif ($action === 'update_section_config') {
            $secKey = clean_string($_POST['section_key'] ?? '');
            $secTitle = clean_string($_POST['display_title'] ?? '');
            $secSubtitle = clean_string($_POST['subtitle'] ?? '');
            $secIcon = clean_string($_POST['icon'] ?? 'server');
            $secSort = sanitize_int($_POST['sort_order'] ?? 0);
            $secActive = sanitize_int($_POST['is_active'] ?? 1);

            $updateRes = update_homepage_section($secKey, [
                'display_title' => $secTitle,
                'subtitle'      => $secSubtitle,
                'icon'          => $secIcon,
                'sort_order'    => $secSort,
                'is_active'     => $secActive
            ], $adminId);

            if ($updateRes) {
                $message = "Section '{$secKey}' settings saved successfully!";
            } else {
                $error = 'Failed to update section settings.';
            }
        }

        // 6. Quick toggle section active/inactive
        elseif ($action === 'toggle_section_active') {
            $secKey = clean_string($_POST['section_key'] ?? '');
            $newActive = sanitize_int($_POST['new_active'] ?? 1);
            $sec = get_homepage_section($secKey);
            if ($sec) {
                update_homepage_section($secKey, ['is_active' => $newActive], $adminId);
                $message = "Section '{$sec['name']}' visibility toggled to " . ($newActive ? 'Active' : 'Hidden') . '!';
            }
        }

        // 7. Batch reorder within section
        elseif ($action === 'batch_reorder') {
            $secKey = clean_string($_POST['section_key'] ?? '');
            $orders = $_POST['orders'] ?? [];
            if (!empty($secKey) && is_array($orders)) {
                update_section_products_order($secKey, $orders, $adminId);
                $message = 'Section display orders saved successfully!';
            }
        }
    }
}

// Filters & Pagination
$sectionFilter = clean_string($_GET['section'] ?? '');
$categoryFilter = clean_string($_GET['category'] ?? '');
$brandFilter = clean_string($_GET['brand'] ?? '');
$search = clean_string($_GET['q'] ?? '');
$statusFilter = clean_string($_GET['status'] ?? '');
$page = max(1, sanitize_int($_GET['page'] ?? 1, 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$where = ["1=1"];
$params = [];

if (!empty($sectionFilter)) {
    $where[] = "hsp.section_key = :sec";
    $params[':sec'] = $sectionFilter;
}

if (!empty($categoryFilter)) {
    $where[] = "c.slug = :cat";
    $params[':cat'] = $categoryFilter;
}

if (!empty($brandFilter)) {
    $where[] = "b.slug = :brand";
    $params[':brand'] = $brandFilter;
}

if (!empty($statusFilter)) {
    if ($statusFilter === 'visible') {
        $where[] = "hsp.is_visible = 1 AND p.status = 'published'";
    } elseif ($statusFilter === 'hidden') {
        $where[] = "(hsp.is_visible = 0 OR p.status != 'published')";
    } elseif ($statusFilter === 'draft') {
        $where[] = "p.status != 'published'";
    } elseif ($statusFilter === 'out_of_stock') {
        $where[] = "p.stock_quantity <= 0";
    }
}

if (!empty($search)) {
    $where[] = "(p.title LIKE :s1 OR p.sku LIKE :s2 OR p.model LIKE :s3)";
    $like = '%' . $search . '%';
    $params[':s1'] = $like;
    $params[':s2'] = $like;
    $params[':s3'] = $like;
}

$whereSql = implode(' AND ', $where);

// Count
$countSql = "SELECT COUNT(*) FROM homepage_section_products hsp
             INNER JOIN products p ON hsp.product_id = p.id
             LEFT JOIN categories c ON p.category_id = c.id
             LEFT JOIN brands b ON p.brand_id = b.id
             WHERE {$whereSql}";
$countStmt = $db->prepare($countSql);
$countStmt->execute($params);
$totalItems = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalItems / $perPage));

// Query Items
$querySql = "SELECT hsp.id AS hsp_id,
                    hsp.section_key,
                    hsp.sort_order AS section_sort_order,
                    hsp.badge_text,
                    hsp.is_visible,
                    hsp.is_featured AS section_featured,
                    p.id AS product_id,
                    p.title,
                    p.slug,
                    p.sku,
                    p.model,
                    p.condition_type,
                    p.status,
                    p.price_paise,
                    p.compare_price_paise,
                    p.stock_quantity,
                    b.name AS brand_name,
                    c.name AS category_name,
                    hs.name AS section_name,
                    hs.icon AS section_icon,
                    hs.is_active AS section_active,
                    (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image
             FROM homepage_section_products hsp
             INNER JOIN products p ON hsp.product_id = p.id
             LEFT JOIN categories c ON p.category_id = c.id
             LEFT JOIN brands b ON p.brand_id = b.id
             LEFT JOIN homepage_sections hs ON hsp.section_key = hs.section_key
             WHERE {$whereSql}
             ORDER BY hs.sort_order ASC, hsp.sort_order ASC, hsp.id DESC
             LIMIT {$perPage} OFFSET {$offset}";

$stmt = $db->prepare($querySql);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all sections, categories, brands
$allSections = get_homepage_sections(false);
$allCategories = $db->query("SELECT name, slug FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$allBrands = $db->query("SELECT name, slug FROM brands ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Published catalog products for Add to Section dropdown
$allCatalogProducts = $db->query("SELECT id, title, sku, price_paise FROM products WHERE status = 'published' ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);

// Section Counts Summary
$sectionStats = [];
$statsStmt = $db->query("SELECT section_key,
                                COUNT(*) AS total_count,
                                SUM(CASE WHEN is_visible = 1 THEN 1 ELSE 0 END) AS visible_count
                         FROM homepage_section_products
                         GROUP BY section_key");
while ($r = $statsStmt->fetch(PDO::FETCH_ASSOC)) {
    $sectionStats[$r['section_key']] = $r;
}
?>

<style>
  .section-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
  }
  .section-stat-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 16px;
    transition: all 0.2s;
    position: relative;
    display: flex;
    flex-direction: column;
  }
  .section-stat-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
  }
  .section-stat-card.is-inactive {
    border-color: #f59e0b;
    background: #fffdfa;
  }
  .section-stat-card.is-focused {
    border-color: #078e49;
    background: #f0fdf4;
  }

  .badge-preset-btn {
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    cursor: pointer;
    transition: all 0.15s;
  }
  .badge-preset-btn:hover {
    background: #e2e8f0;
    border-color: #94a3b8;
  }

  .draggable-row {
    cursor: grab;
    transition: background 0.15s;
  }
  .draggable-row:active {
    cursor: grabbing;
  }
  .draggable-row.dragging {
    opacity: 0.45;
    background: #e0f2fe !important;
  }
  .draggable-row.drag-over {
    border-top: 2px solid #078e49 !important;
  }
</style>

<div class="admin-container">

  <!-- Page Header -->
  <div class="admin-page-header">
    <div>
      <div style="display: flex; align-items: center; gap: 10px;">
        <span style="font-size: 26px; background: #dcfce7; color: #166534; padding: 6px 12px; border-radius: 8px;">🏠</span>
        <div>
          <h1 style="margin: 0;">Homepage Merchandising &amp; Sections</h1>
          <p>Control product placements, section display orders, promotional badges, and visibility on the storefront.</p>
        </div>
      </div>
    </div>
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
      <a href="preview.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
        👁️ Live Merchandising Preview
      </a>
      <button type="button" class="btn btn-secondary" onclick="openAddModal()">+ Add Product to Section</button>
      <a href="../../index.php" target="_blank" class="btn btn-secondary">Storefront ↗</a>
    </div>
  </div>

  <?php if (!empty($message)): ?>
    <div class="alert alert-success"><?= e($message) ?></div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
  <?php endif; ?>

  <!-- Homepage Sections Configuration Cards -->
  <div class="section-cards-grid">
    <?php foreach ($allSections as $s): ?>
      <?php
        $secKey = $s['section_key'];
        $isActive = ((int)$s['is_active'] === 1);
        $totalSecProds = (int)($sectionStats[$secKey]['total_count'] ?? 0);
        $visibleSecProds = (int)($sectionStats[$secKey]['visible_count'] ?? 0);
        $isFocused = ($sectionFilter === $secKey);
      ?>
      <div class="section-stat-card <?= !$isActive ? 'is-inactive' : '' ?> <?= $isFocused ? 'is-focused' : '' ?>">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
          <div style="display: flex; align-items: center; gap: 6px;">
            <span style="font-size: 20px;"><?= get_section_icon($secKey) ?></span>
            <strong style="font-size: 14px; color: #0f172a;"><?= e($s['name']) ?></strong>
          </div>
          <form method="post" action="index.php" style="margin: 0;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle_section_active">
            <input type="hidden" name="section_key" value="<?= e($secKey) ?>">
            <input type="hidden" name="new_active" value="<?= $isActive ? '0' : '1' ?>">
            <button type="submit" class="badge <?= $isActive ? 'badge-green' : 'badge-danger' ?>" style="cursor: pointer; border: none; font-size: 10px; padding: 2px 6px;" title="Click to toggle section status">
              <?= $isActive ? '● Active' : '○ Inactive' ?>
            </button>
          </form>
        </div>

        <p style="font-size: 11.5px; color: #64748b; margin: 0 0 10px; line-height: 1.35; flex: 1;">
          <?= e($s['display_title']) ?>
        </p>

        <div style="font-size: 11px; color: #475569; margin-bottom: 10px; background: #f8fafc; padding: 4px 8px; border-radius: 4px; display: flex; justify-content: space-between;">
          <span>Placements: <strong><?= $visibleSecProds ?></strong>/<?= $totalSecProds ?> visible</span>
          <span>Order: <strong>#<?= (int)$s['sort_order'] ?></strong></span>
        </div>

        <div style="display: flex; gap: 4px;">
          <a href="index.php?section=<?= e($secKey) ?>" class="btn <?= $isFocused ? 'btn-primary' : 'btn-secondary' ?> btn-sm" style="flex: 1; text-align: center; font-size: 11px; padding: 3px 4px;">
            <?= $isFocused ? 'Filter Applied' : 'Filter Table' ?>
          </a>
          <button type="button" class="btn btn-secondary btn-sm" style="font-size: 11px; padding: 3px 8px;" onclick='openConfigModal(<?= json_encode($s) ?>)'>
            ⚙ Config
          </button>
          <a href="../sections/<?= e($secKey === 'featured' ? 'promotions' : $secKey) ?>.php" class="btn btn-secondary btn-sm" style="font-size: 11px; padding: 3px 6px;" title="Open Dedicated Section Manager">
            ↗
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Search & Filter Bar -->
  <div class="admin-card" style="padding: 16px 20px;">
    <form method="get" action="index.php" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
      <?php if (!empty($sectionFilter)): ?>
        <input type="hidden" name="section" value="<?= e($sectionFilter) ?>">
      <?php endif; ?>

      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search product title, SKU, model..." class="form-control" style="max-width: 260px;">

      <select name="category" class="form-control" style="max-width: 180px;" onchange="this.form.submit()">
        <option value="">All Categories</option>
        <?php foreach ($allCategories as $c): ?>
          <option value="<?= e($c['slug']) ?>" <?= $categoryFilter === $c['slug'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>

      <select name="brand" class="form-control" style="max-width: 150px;" onchange="this.form.submit()">
        <option value="">All Brands</option>
        <?php foreach ($allBrands as $b): ?>
          <option value="<?= e($b['slug']) ?>" <?= $brandFilter === $b['slug'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
        <?php endforeach; ?>
      </select>

      <select name="status" class="form-control" style="max-width: 160px;" onchange="this.form.submit()">
        <option value="">All Placements</option>
        <option value="visible" <?= $statusFilter === 'visible' ? 'selected' : '' ?>>Visible Live</option>
        <option value="hidden" <?= $statusFilter === 'hidden' ? 'selected' : '' ?>>Hidden Placements</option>
        <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft / Inactive Product</option>
        <option value="out_of_stock" <?= $statusFilter === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
      </select>

      <button type="submit" class="btn btn-secondary">Filter</button>

      <?php if (!empty($search) || !empty($categoryFilter) || !empty($brandFilter) || !empty($statusFilter) || !empty($sectionFilter)): ?>
        <a href="index.php" class="btn btn-secondary" style="color: #dc2626;">Reset All</a>
      <?php endif; ?>

      <div style="margin-left: auto; font-size: 13px; color: #64748b; font-weight: 600;">
        Showing <strong><?= count($items) ?></strong> of <strong><?= $totalItems ?></strong> placements
      </div>
    </form>
  </div>

  <!-- Homepage Placements Table -->
  <div class="admin-card">
    <?php if (empty($items)): ?>
      <div style="text-align: center; padding: 40px 20px; color: #64748b;">
        <p style="font-size: 16px; margin: 0 0 12px;">No products match the selected filters for this homepage view.</p>
        <button type="button" class="btn btn-primary btn-sm" onclick="openAddModal()">+ Add Product to Homepage</button>
      </div>
    <?php else: ?>
      <!-- Desktop Table -->
      <div class="desktop-only-wrap">
        <div class="admin-table-wrap">
          <table class="admin-table" id="placementTable">
            <thead>
              <tr>
                <th style="width: 44px;">Order</th>
                <th style="width: 48px;">Image</th>
                <th>Product Details</th>
                <th>Section</th>
                <th>Badge Text</th>
                <th>Selling Price</th>
                <th>Visibility</th>
                <th style="text-align: right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $idx => $it): ?>
                <?php
                  $isPublished = ($it['status'] === 'published');
                  $isOutOfStock = ((int)$it['stock_quantity'] <= 0);
                  $thumb = get_product_thumbnail_path($it['primary_image'] ?? 'images/server-hud.png');
                ?>
                <tr class="draggable-row" draggable="true" data-hsp-id="<?= (int)$it['hsp_id'] ?>" data-product-id="<?= (int)$it['product_id'] ?>">
                  <!-- Keyboard & Drag Order Controls -->
                  <td>
                    <form method="post" action="index.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . e($_SERVER['QUERY_STRING']) : '' ?>" style="display: flex; align-items: center; gap: 2px;">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="update_placement">
                      <input type="hidden" name="hsp_id" value="<?= (int)$it['hsp_id'] ?>">
                      <input type="hidden" name="badge_text" value="<?= e($it['badge_text'] ?? '') ?>">
                      <input type="number" name="sort_order" value="<?= (int)$it['section_sort_order'] ?>" class="form-control" style="width: 46px; padding: 2px 4px; font-size: 11.5px; text-align: center;" min="0">
                      <button type="submit" class="btn btn-secondary btn-sm" style="padding: 2px 4px; font-size: 10px;" title="Save order">✓</button>
                    </form>
                  </td>

                  <!-- Image with Thumbnail Optimization -->
                  <td>
                    <img src="<?= e($adminRoot . $thumb) ?>" alt="" style="width: 44px; height: 44px; object-fit: contain; border-radius: 6px; background: #fff; border: 1px solid #e2e8f0; padding: 2px;" loading="lazy">
                  </td>

                  <!-- Product Details with Status Warnings -->
                  <td>
                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                      <a href="<?= e($adminRoot) ?>products/form.php?id=<?= (int)$it['product_id'] ?>" style="font-weight: 700; color: #0f172a; text-decoration: none;">
                        <?= e($it['title']) ?>
                      </a>
                      <?php if (!$isPublished): ?>
                        <span class="badge badge-danger" style="font-size: 10px;">Draft / Not Published</span>
                      <?php endif; ?>
                      <?php if ($isOutOfStock): ?>
                        <span class="badge badge-gray" style="font-size: 10px; color: #dc2626;">0 Stock</span>
                      <?php endif; ?>
                    </div>
                    <div style="font-size: 11.5px; color: #64748b; margin-top: 3px;">
                      <code><?= e($it['sku']) ?></code> &bull; <?= e($it['brand_name'] ?? 'iboltnetworks') ?> &bull; <?= e($it['category_name'] ?? 'General') ?>
                    </div>
                  </td>

                  <!-- Section Badge -->
                  <td>
                    <span class="badge badge-blue">
                      <?= get_section_icon($it['section_key']) ?> <?= e($it['section_name'] ?? ucfirst($it['section_key'])) ?>
                    </span>
                  </td>

                  <!-- Badge Text Form -->
                  <td>
                    <form method="post" action="index.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . e($_SERVER['QUERY_STRING']) : '' ?>" style="display: flex; gap: 4px; align-items: center;">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="update_placement">
                      <input type="hidden" name="hsp_id" value="<?= (int)$it['hsp_id'] ?>">
                      <input type="hidden" name="sort_order" value="<?= (int)$it['section_sort_order'] ?>">
                      <input type="text" name="badge_text" value="<?= e($it['badge_text'] ?? '') ?>" placeholder="e.g. Best Seller" class="form-control" style="width: 105px; padding: 3px 6px; font-size: 11.5px;">
                      <button type="submit" class="btn btn-secondary btn-sm" style="padding: 2px 6px; font-size: 11px;" title="Save badge">✓</button>
                    </form>
                  </td>

                  <!-- Selling Price -->
                  <td>
                    <strong style="font-size: 13.5px; color: #078e49;">
                      <?= format_inr((int)$it['price_paise']) ?>
                    </strong>
                    <?php if (!empty($it['compare_price_paise']) && (int)$it['compare_price_paise'] > (int)$it['price_paise']): ?>
                      <div style="font-size: 11px; text-decoration: line-through; color: #94a3b8;">
                        <?= format_inr((int)$it['compare_price_paise']) ?>
                      </div>
                    <?php endif; ?>
                  </td>

                  <!-- Visibility Toggle -->
                  <td>
                    <form method="post" action="index.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . e($_SERVER['QUERY_STRING']) : '' ?>">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="toggle_visibility">
                      <input type="hidden" name="hsp_id" value="<?= (int)$it['hsp_id'] ?>">
                      <input type="hidden" name="new_visible" value="<?= (int)$it['is_visible'] === 1 ? '0' : '1' ?>">
                      <button type="submit" class="badge <?= (int)$it['is_visible'] === 1 ? 'badge-green' : 'badge-gray' ?>" style="cursor: pointer; border: none; font-size: 11px; padding: 3px 8px;">
                        <?= (int)$it['is_visible'] === 1 ? '● Live' : '○ Hidden' ?>
                      </button>
                    </form>
                  </td>

                  <!-- Actions -->
                  <td style="text-align: right; white-space: nowrap;">
                    <a href="<?= e($adminRoot) ?>products/form.php?id=<?= (int)$it['product_id'] ?>" class="btn btn-secondary btn-sm" style="font-size: 11px; padding: 2px 6px;">
                      Edit Product
                    </a>
                    <a href="<?= e($adminRoot) ?>images/index.php?product_id=<?= (int)$it['product_id'] ?>" class="btn btn-secondary btn-sm" style="font-size: 11px; padding: 2px 6px;" title="Manage Gallery">
                      Photos
                    </a>
                    <form method="post" action="index.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . e($_SERVER['QUERY_STRING']) : '' ?>" style="display: inline-block;" onsubmit="return confirm('Remove product from this homepage section?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="remove_from_section">
                      <input type="hidden" name="hsp_id" value="<?= (int)$it['hsp_id'] ?>">
                      <button type="submit" class="btn btn-danger btn-sm" style="font-size: 11px; padding: 2px 6px;" title="Remove from section">
                        ✕
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Mobile Placements Cards (≤ 768px) -->
      <div class="mobile-only" style="display: flex; flex-direction: column; gap: 14px;">
        <?php foreach ($items as $it): ?>
          <?php
            $isPublished = ($it['status'] === 'published');
            $isOutOfStock = ((int)$it['stock_quantity'] <= 0);
            $thumb = get_product_thumbnail_path($it['primary_image'] ?? 'images/server-hud.png');
          ?>
          <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="display: flex; gap: 12px; align-items: flex-start; margin-bottom: 10px;">
              <img src="<?= e($adminRoot . $thumb) ?>" alt="" style="width: 52px; height: 52px; object-fit: contain; border-radius: 6px; background: #fff; border: 1px solid #e2e8f0; padding: 2px; flex-shrink: 0;" loading="lazy">
              <div style="flex: 1; min-width: 0;">
                <a href="<?= e($adminRoot) ?>products/form.php?id=<?= (int)$it['product_id'] ?>" style="font-weight: 700; color: #0f172a; text-decoration: none; font-size: 14px; display: block; line-height: 1.35; margin-bottom: 4px;">
                  <?= e($it['title']) ?>
                </a>
                <div style="font-size: 11.5px; color: #64748b;">
                  <code><?= e($it['sku']) ?></code> &bull; <?= e($it['category_name'] ?? 'General') ?>
                </div>
              </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 8px 10px; border-radius: 6px; margin-bottom: 10px; flex-wrap: wrap; gap: 6px;">
              <span class="badge badge-blue" style="font-size: 11.5px;">
                <?= get_section_icon($it['section_key']) ?> <?= e($it['section_name'] ?? ucfirst($it['section_key'])) ?>
              </span>
              <div style="display: flex; align-items: center; gap: 8px;">
                <strong style="color: #078e49; font-size: 14px;"><?= format_inr((int)$it['price_paise']) ?></strong>
                <form method="post" action="index.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . e($_SERVER['QUERY_STRING']) : '' ?>" style="margin: 0;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle_visibility">
                  <input type="hidden" name="hsp_id" value="<?= (int)$it['hsp_id'] ?>">
                  <input type="hidden" name="new_visible" value="<?= (int)$it['is_visible'] === 1 ? '0' : '1' ?>">
                  <button type="submit" class="badge <?= (int)$it['is_visible'] === 1 ? 'badge-green' : 'badge-gray' ?>" style="cursor: pointer; border: none; font-size: 11.5px; min-height: 32px; padding: 4px 10px;">
                    <?= (int)$it['is_visible'] === 1 ? '● Live' : '○ Hidden' ?>
                  </button>
                </form>
              </div>
            </div>

            <!-- Mobile Inline Order & Badge Adjuster -->
            <form method="post" action="index.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . e($_SERVER['QUERY_STRING']) : '' ?>" style="display: flex; gap: 6px; align-items: center; margin-bottom: 10px;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="update_placement">
              <input type="hidden" name="hsp_id" value="<?= (int)$it['hsp_id'] ?>">
              <div style="display: flex; align-items: center; gap: 4px;">
                <span style="font-size: 12px; color: #64748b; font-weight: 600;">Seq:</span>
                <input type="number" name="sort_order" value="<?= (int)$it['section_sort_order'] ?>" class="form-control" style="width: 50px; min-height: 40px; text-align: center; font-size: 14px; padding: 4px;" min="0">
              </div>
              <input type="text" name="badge_text" value="<?= e($it['badge_text'] ?? '') ?>" placeholder="Badge label" class="form-control" style="flex: 1; min-height: 40px; font-size: 13px;">
              <button type="submit" class="btn btn-secondary btn-sm" style="min-height: 40px; min-width: 44px; display: inline-flex; align-items: center; justify-content: center; font-weight: 700;">Save</button>
            </form>

            <!-- Actions Bar with 44px Touch Targets -->
            <div style="display: flex; gap: 8px; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 10px;">
              <a href="<?= e($adminRoot) ?>products/form.php?id=<?= (int)$it['product_id'] ?>" class="btn btn-secondary btn-sm" style="flex: 1; min-height: 44px; display: inline-flex; align-items: center; justify-content: center; font-weight: 700;">
                ✏️ Edit
              </a>
              <a href="<?= e($adminRoot) ?>images/index.php?product_id=<?= (int)$it['product_id'] ?>" class="btn btn-secondary btn-sm" style="min-height: 44px; padding: 0 14px; display: inline-flex; align-items: center; justify-content: center;" title="Manage Gallery">
                📷 Photos
              </a>
              <form method="post" action="index.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . e($_SERVER['QUERY_STRING']) : '' ?>" style="margin: 0;" onsubmit="return confirm('Remove product from this homepage section?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="remove_from_section">
                <input type="hidden" name="hsp_id" value="<?= (int)$it['hsp_id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm" style="min-height: 44px; padding: 0 14px; display: inline-flex; align-items: center; justify-content: center;" title="Remove from section">
                  🗑️
                </button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Pagination Controls -->
      <?php if ($totalPages > 1): ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding-top: 16px; border-top: 1px solid #f1f5f9; flex-wrap: wrap; gap: 10px;">
          <div style="font-size: 13px; color: #64748b;">
            Page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong>
          </div>
          <div style="display: flex; gap: 4px;">
            <?php if ($page > 1): ?>
              <a href="index.php?page=<?= $page - 1 ?><?= !empty($sectionFilter) ? '&section=' . urlencode($sectionFilter) : '' ?>" class="btn btn-secondary btn-sm">Previous</a>
            <?php endif; ?>
            <?php if ($page < $totalPages): ?>
              <a href="index.php?page=<?= $page + 1 ?><?= !empty($sectionFilter) ? '&section=' . urlencode($sectionFilter) : '' ?>" class="btn btn-secondary btn-sm">Next</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

    <?php endif; ?>
  </div>

</div>

<!-- Modal: Add Product to Section -->
<div id="addModal" class="admin-modal">
  <div class="admin-modal-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
      <h2 style="margin: 0; font-size: 18px; font-weight: 800;">Add Product to Homepage Section</h2>
      <button type="button" onclick="closeAddModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #64748b;">✕</button>
    </div>

    <form method="post" action="index.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_to_section">

      <div class="form-group">
        <label for="modal_prod_id">Select Catalog Product *</label>
        <select id="modal_prod_id" name="product_id" class="form-control" required style="font-size: 13.5px;">
          <option value="">-- Choose Product from Published Catalog --</option>
          <?php foreach ($allCatalogProducts as $cp): ?>
            <option value="<?= $cp['id'] ?>">
              <?= e($cp['title']) ?> (<?= e($cp['sku']) ?> - <?= format_inr((int)$cp['price_paise']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="modal_sec_key">Target Homepage Section *</label>
          <select id="modal_sec_key" name="section_key" class="form-control" required>
            <?php foreach ($allSections as $s): ?>
              <option value="<?= e($s['section_key']) ?>" <?= $sectionFilter === $s['section_key'] ? 'selected' : '' ?>>
                <?= get_section_icon($s['section_key']) ?> <?= e($s['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="modal_sort_order">Display Order</label>
          <input type="number" id="modal_sort_order" name="sort_order" value="10" min="0" class="form-control">
        </div>
      </div>

      <div class="form-group">
        <label for="modal_badge_text">Promotional Badge Text</label>
        <input type="text" id="modal_badge_text" name="badge_text" placeholder="e.g. Featured, Best Seller, New Launch" class="form-control">
        <div style="display: flex; gap: 6px; margin-top: 6px; flex-wrap: wrap;">
          <span style="font-size: 11px; color: #64748b; align-self: center;">Presets:</span>
          <button type="button" class="badge-preset-btn" onclick="document.getElementById('modal_badge_text').value='Featured'">Featured</button>
          <button type="button" class="badge-preset-btn" onclick="document.getElementById('modal_badge_text').value='Best Seller'">Best Seller</button>
          <button type="button" class="badge-preset-btn" onclick="document.getElementById('modal_badge_text').value='Hot Deal'">Hot Deal</button>
          <button type="button" class="badge-preset-btn" onclick="document.getElementById('modal_badge_text').value='New Launch'">New Launch</button>
          <button type="button" class="badge-preset-btn" onclick="document.getElementById('modal_badge_text').value='Limited Stock'">Limited Stock</button>
        </div>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px;">
        <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Assign Product to Section</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Section Configuration -->
<div id="configModal" class="admin-modal">
  <div class="admin-modal-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
      <h2 style="margin: 0; font-size: 18px; font-weight: 800;">Configure Homepage Section</h2>
      <button type="button" onclick="closeConfigModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #64748b;">✕</button>
    </div>

    <form method="post" action="index.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_section_config">
      <input type="hidden" id="cfg_sec_key" name="section_key" value="">

      <div class="form-group">
        <label for="cfg_display_title">Storefront Display Title *</label>
        <input type="text" id="cfg_display_title" name="display_title" required class="form-control">
      </div>

      <div class="form-group">
        <label for="cfg_subtitle">Subtitle / Category Description</label>
        <textarea id="cfg_subtitle" name="subtitle" rows="2" class="form-control"></textarea>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="cfg_icon">Section Icon Key</label>
          <select id="cfg_icon" name="icon" class="form-control">
            <option value="server">server (▤ Servers)</option>
            <option value="network">network (🖧 Networking)</option>
            <option value="cpu">cpu (▦ Components)</option>
            <option value="shopping-bag">shopping-bag (🛍️ Our Products)</option>
            <option value="tag">tag (🏷️ Promotions)</option>
          </select>
        </div>

        <div class="form-group">
          <label for="cfg_sort_order">Homepage Sequence Order</label>
          <input type="number" id="cfg_sort_order" name="sort_order" min="0" class="form-control">
        </div>
      </div>

      <div class="form-group">
        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
          <input type="checkbox" id="cfg_is_active" name="is_active" value="1">
          <strong style="color: #0f172a;">Section Active on Public Storefront</strong>
        </label>
        <small style="color: #64748b; display: block; margin-top: 4px;">
          Unchecking this safely hides the entire section from public storefront visitors without removing product placements.
        </small>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px;">
        <button type="button" class="btn btn-secondary" onclick="closeConfigModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Section Configuration</button>
      </div>
    </form>
  </div>
</div>

<script>
  function openAddModal() {
    document.getElementById('addModal').classList.add('is-open');
  }
  function closeAddModal() {
    document.getElementById('addModal').classList.remove('is-open');
  }

  function openConfigModal(sec) {
    document.getElementById('cfg_sec_key').value = sec.section_key;
    document.getElementById('cfg_display_title').value = sec.display_title;
    document.getElementById('cfg_subtitle').value = sec.subtitle || '';
    document.getElementById('cfg_icon').value = sec.icon || 'server';
    document.getElementById('cfg_sort_order').value = sec.sort_order;
    document.getElementById('cfg_is_active').checked = (parseInt(sec.is_active) === 1);
    document.getElementById('configModal').classList.add('is-open');
  }
  function closeConfigModal() {
    document.getElementById('configModal').classList.remove('is-open');
  }
</script>

<?php
require_once __DIR__ . '/../includes/admin-footer.php';
