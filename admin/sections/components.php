<?php
declare(strict_types=1);

/**
 * Admin Components Section Management
 * Merchandising administration with drag-and-drop & keyboard reordering,
 * badge validation, section configuration, and storefront live preview.
 * iboltnetworks E-Commerce Platform
 */

$adminRoot = '../';
$adminPageTitle = 'Components Section Management | iboltnetworks Admin';

require_once __DIR__ . '/../includes/admin-header.php';
require_once __DIR__ . '/../../app/helpers/homepage.php';
require_once __DIR__ . '/../../app/helpers/uploader.php';

$db = Database::getConnection();
$sectionKey = 'components';
$section = get_homepage_section($sectionKey);

$message = '';
$error = '';
$adminId = $admin['id'] ?? null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $action = clean_string($_POST['action'] ?? '');

        // 1. Toggle product visibility
        if ($action === 'toggle_visibility') {
            $hspId = sanitize_int($_POST['hsp_id'] ?? 0);
            $newVisible = sanitize_int($_POST['new_visible'] ?? 1);
            if (toggle_section_product_visibility($hspId, $newVisible, $adminId)) {
                $message = 'Product visibility updated!';
            } else {
                $error = 'Failed to update visibility.';
            }
        }

        // 2. Update single item display & badge
        elseif ($action === 'update_item') {
            $hspId = sanitize_int($_POST['hsp_id'] ?? 0);
            $sortOrder = sanitize_int($_POST['sort_order'] ?? 0);
            $rawBadge = clean_string($_POST['badge_text'] ?? '');
            $isFeatured = sanitize_int($_POST['is_featured'] ?? 0);

            $itemInfoStmt = $db->prepare("SELECT p.price_paise, p.compare_price_paise, hsp.product_id
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

                $db->prepare("UPDATE homepage_section_products SET sort_order = ?, badge_text = ?, is_featured = ?, updated_at = NOW() WHERE id = ?")
                   ->execute([$sortOrder, $validatedBadge, $isFeatured, $hspId]);

                log_homepage_activity($adminId, 'section_update_item', 'homepage_section_product', (int)$itemInfo['product_id'], [
                    'hsp_id'     => $hspId,
                    'section'    => 'components',
                    'sort_order' => $sortOrder,
                    'badge'      => $validatedBadge
                ]);

                $message = 'Item display settings saved!';
            } else {
                $error = 'Placement record not found.';
            }
        }

        // 3. Batch reorder
        elseif ($action === 'batch_reorder') {
            $orders = $_POST['orders'] ?? [];
            if (is_array($orders) && !empty($orders)) {
                update_section_products_order('components', $orders, $adminId);
                $message = 'Display sequence saved successfully!';
            }
        }

        // 4. Add item to section
        elseif ($action === 'add_item') {
            $productId = sanitize_int($_POST['product_id'] ?? 0);
            $sortOrder = sanitize_int($_POST['sort_order'] ?? 0);
            $badgeText = clean_string($_POST['badge_text'] ?? 'Featured');
            $isFeatured = sanitize_int($_POST['is_featured'] ?? 0);

            if ($productId <= 0) {
                $error = 'Please select a valid catalog product.';
            } else {
                $res = assign_product_to_section('components', $productId, $sortOrder, $badgeText, $isFeatured, $adminId);
                if ($res['success']) {
                    $message = 'Product added to this homepage section successfully!';
                } else {
                    $error = $res['error'];
                }
            }
        }

        // 5. Remove item from section
        elseif ($action === 'remove_item') {
            $hspId = sanitize_int($_POST['hsp_id'] ?? 0);
            if (remove_section_item_by_id($hspId, $adminId)) {
                $message = 'Product removed from this homepage section.';
            } else {
                $error = 'Failed to remove product.';
            }
        }

        // 6. Update section configuration
        elseif ($action === 'update_section_config') {
            $secTitle = clean_string($_POST['display_title'] ?? '');
            $secSubtitle = clean_string($_POST['subtitle'] ?? '');
            $secActive = sanitize_int($_POST['is_active'] ?? 1);

            if (update_homepage_section('components', [
                'display_title' => $secTitle,
                'subtitle'      => $secSubtitle,
                'is_active'     => $secActive
            ], $adminId)) {
                $message = 'Section settings updated successfully!';
                $section = get_homepage_section('components');
            } else {
                $error = 'Failed to update section settings.';
            }
        }
    }
}

$search = clean_string($_GET['q'] ?? '');
$statusFilter = clean_string($_GET['status'] ?? '');

$items = get_admin_section_products('components', $search, $statusFilter);

// Catalog items for Add dropdown
$availableItems = $db->query("SELECT p.id, p.title, p.sku, p.price_paise, b.name AS brand_name
                             FROM products p
                             LEFT JOIN categories c ON p.category_id = c.id
                             LEFT JOIN brands b ON p.brand_id = b.id
                             WHERE (c.section = 'components' OR p.title LIKE '%RAM%' OR p.title LIKE '%SSD%' OR p.title LIKE '%CPU%' OR p.title LIKE '%RAID%')
                               AND p.status = 'published'
                             ORDER BY p.title ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="admin-container">

  <div class="admin-page-header">
    <div>
      <div style="display: flex; align-items: center; gap: 10px;">
        <span style="font-size: 26px; background: #e0f2fe; color: #0369a1; padding: 6px 12px; border-radius: 8px;">▦</span>
        <div>
          <h1 style="margin: 0;"><?= e($section['name'] ?? 'Components Section Management') ?></h1>
          <p><?= e($section['subtitle'] ?? 'Control processors, memory, storage, RAID controllers, and power supplies on the homepage.') ?></p>
        </div>
      </div>
    </div>
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
      <a href="../homepage/preview.php?section=components" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
        👁️ Live Section Preview
      </a>
      <button type="button" class="btn btn-secondary" onclick="openConfigModal()">⚙ Section Settings</button>
      <button type="button" class="btn btn-secondary" onclick="openAddItemModal()">+ Add Product to Section</button>
      <a href="../homepage/index.php" class="btn btn-secondary">← Master Overview</a>
    </div>
  </div>

  <?php if (!empty($message)): ?>
    <div class="alert alert-success"><?= e($message) ?></div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
  <?php endif; ?>

  <!-- Section Inactive Banner -->
  <?php if (!empty($section) && (int)$section['is_active'] === 0): ?>
    <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
      <div style="display: flex; align-items: center; gap: 8px;">
        <span style="font-size: 20px;">⚠️</span>
        <div>
          <strong style="color: #92400e;">This Section is Currently Inactive</strong>
          <span style="color: #b45309; font-size: 13px; display: block;">This section is hidden from public storefront visitors.</span>
        </div>
      </div>
      <button type="button" class="btn btn-primary btn-sm" onclick="openConfigModal()">Re-activate Section</button>
    </div>
  <?php endif; ?>

  <!-- Search & Filter Card -->
  <div class="admin-card" style="padding: 16px 20px;">
    <form method="get" action="<?= basename($_SERVER['PHP_SELF']) ?>" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search product title, SKU, model..." class="form-control" style="max-width: 300px;">
      
      <select name="status" class="form-control" style="max-width: 170px;" onchange="this.form.submit()">
        <option value="">All Placements</option>
        <option value="visible" <?= $statusFilter === 'visible' ? 'selected' : '' ?>>Visible Live</option>
        <option value="hidden" <?= $statusFilter === 'hidden' ? 'selected' : '' ?>>Hidden</option>
        <option value="out_of_stock" <?= $statusFilter === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
      </select>

      <button type="submit" class="btn btn-secondary">Filter</button>
      <?php if (!empty($search) || !empty($statusFilter)): ?>
        <a href="<?= basename($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary" style="color: #dc2626;">Reset</a>
      <?php endif; ?>
      <div style="margin-left: auto; font-size: 13.5px; color: #64748b; font-weight: 600;">
        <strong><?= count($items) ?></strong> product(s) assigned
      </div>
    </form>
  </div>

  <!-- Items Table with Reordering -->
  <div class="admin-card">
    <?php if (empty($items)): ?>
      <div style="text-align: center; padding: 40px; color: #64748b;">
        <p>No products currently match in this section.</p>
        <button type="button" class="btn btn-primary" onclick="openAddItemModal()">+ Add Product from Catalog</button>
      </div>
    <?php else: ?>
      <form id="orderBatchForm" method="post" action="<?= basename($_SERVER['PHP_SELF']) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="batch_reorder">

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 10px;">
          <span style="font-size: 13px; color: #64748b;">
            Drag rows or use ▲ / ▼ to adjust sequence on the homepage.
          </span>
          <button type="submit" class="btn btn-primary btn-sm">
            💾 Save Sequence Orders
          </button>
        </div>

        <div class="admin-table-wrap">
          <table class="admin-table" id="sectionTable">
            <thead>
              <tr>
                <th style="width: 70px;">Sequence</th>
                <th style="width: 50px;">Image</th>
                <th>Product Details</th>
                <th>Brand</th>
                <th>Badge Label</th>
                <th>Spotlight</th>
                <th>Price</th>
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
                <tr class="draggable-row" draggable="true" data-hsp-id="<?= (int)$it['hsp_id'] ?>">
                  <td>
                    <div style="display: flex; align-items: center; gap: 4px;">
                      <button type="button" class="btn btn-secondary btn-sm" style="padding: 1px 4px; font-size: 10px;" onclick="moveRowUp(this)" title="Move Up">▲</button>
                      <input type="number" name="orders[<?= (int)$it['product_id'] ?>]" value="<?= (int)$it['section_sort_order'] ?>" class="form-control row-order-input" style="width: 44px; padding: 2px 4px; font-size: 11px; text-align: center;" min="0">
                      <button type="button" class="btn btn-secondary btn-sm" style="padding: 1px 4px; font-size: 10px;" onclick="moveRowDown(this)" title="Move Down">▼</button>
                    </div>
                  </td>

                  <td>
                    <img src="<?= e($adminRoot . $thumb) ?>" alt="" style="width: 46px; height: 46px; object-fit: contain; border-radius: 6px; background: #fff; border: 1px solid #e2e8f0; padding: 2px;" loading="lazy">
                  </td>

                  <td>
                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                      <a href="<?= e($adminRoot) ?>products/form.php?id=<?= (int)$it['product_id'] ?>" style="font-weight: 700; color: #0f172a; text-decoration: none;">
                        <?= e($it['title']) ?>
                      </a>
                      <?php if (!$isPublished): ?>
                        <span class="badge badge-danger" style="font-size: 10px;">Draft</span>
                      <?php endif; ?>
                      <?php if ($isOutOfStock): ?>
                        <span class="badge badge-gray" style="font-size: 10px; color: #dc2626;">0 Stock</span>
                      <?php endif; ?>
                    </div>
                    <div style="font-size: 11.5px; color: #64748b; margin-top: 2px;">
                      <code><?= e($it['sku']) ?></code> &bull; <?= e(ucfirst($it['condition_type'] ?? 'refurbished')) ?>
                    </div>
                  </td>

                  <td><?= e($it['brand_name'] ?? 'iboltnetworks') ?></td>

                  <td>
                    <div style="display: flex; gap: 4px; align-items: center;">
                      <span class="badge badge-purple" style="font-size: 11px;">
                        <?= e($it['badge_text'] ?: 'None') ?>
                      </span>
                      <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 6px; font-size: 10px;" onclick='openBadgeModal(<?= (int)$it['hsp_id'] ?>, <?= json_encode($it['badge_text'] ?? '') ?>)'>
                        ✎
                      </button>
                    </div>
                  </td>

                  <td>
                    <button type="submit" formaction="<?= basename($_SERVER['PHP_SELF']) ?>" name="action" value="update_item" onclick="setSpotlight(<?= (int)$it['hsp_id'] ?>, <?= (int)$it['section_sort_order'] ?>, <?= json_encode($it['badge_text'] ?? '') ?>, <?= (int)$it['section_featured'] === 1 ? '0' : '1' ?>)" class="badge <?= (int)$it['section_featured'] === 1 ? 'badge-purple' : 'badge-gray' ?>" style="cursor: pointer; border: none; padding: 4px 8px;">
                      <?= (int)$it['section_featured'] === 1 ? '★ Spotlight' : '☆ Normal' ?>
                    </button>
                  </td>

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

                  <td>
                    <button type="submit" formaction="<?= basename($_SERVER['PHP_SELF']) ?>" name="action" value="toggle_visibility" onclick="setVisibility(<?= (int)$it['hsp_id'] ?>, <?= (int)$it['section_visible'] === 1 ? '0' : '1' ?>)" class="badge <?= (int)$it['section_visible'] === 1 ? 'badge-green' : 'badge-gray' ?>" style="cursor: pointer; border: none; padding: 4px 8px;">
                      <?= (int)$it['section_visible'] === 1 ? '● Live' : '○ Hidden' ?>
                    </button>
                  </td>

                  <td style="text-align: right; white-space: nowrap;">
                    <a href="<?= e($adminRoot) ?>products/form.php?id=<?= (int)$it['product_id'] ?>" class="btn btn-secondary btn-sm" style="font-size: 11px; padding: 3px 6px;">Edit</a>
                    <a href="<?= e($adminRoot) ?>images/index.php?product_id=<?= (int)$it['product_id'] ?>" class="btn btn-secondary btn-sm" style="font-size: 11px; padding: 3px 6px;">Photos</a>
                    <a href="<?= e($adminRoot) ?>../product.php?slug=<?= e($it['slug']) ?>" target="_blank" class="btn btn-secondary btn-sm" style="font-size: 11px; padding: 3px 6px;">View ↗</a>
                    <button type="submit" formaction="<?= basename($_SERVER['PHP_SELF']) ?>" name="action" value="remove_item" onclick="setRemoveId(<?= (int)$it['hsp_id'] ?>); return confirm('Remove product from this section?');" class="btn btn-danger btn-sm" style="font-size: 11px; padding: 3px 6px;" title="Remove from section">
                      ✕
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <input type="hidden" id="action_hsp_id" name="hsp_id" value="">
        <input type="hidden" id="action_new_visible" name="new_visible" value="">
        <input type="hidden" id="action_sort_order" name="sort_order" value="">
        <input type="hidden" id="action_badge_text" name="badge_text" value="">
        <input type="hidden" id="action_is_featured" name="is_featured" value="">
      </form>
    <?php endif; ?>
  </div>

</div>

<!-- Modal: Add Item -->
<div id="addItemModal" class="admin-modal">
  <div class="admin-modal-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
      <h2 style="margin: 0; font-size: 18px; font-weight: 800;">Add Component to Section</h2>
      <button type="button" onclick="closeAddItemModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #64748b;">✕</button>
    </div>

    <form method="post" action="<?= basename($_SERVER['PHP_SELF']) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_item">

      <div class="form-group">
        <label for="item_prod_id">Select Catalog Product *</label>
        <select id="item_prod_id" name="product_id" class="form-control" required style="font-size: 13.5px;">
          <option value="">-- Choose Product from Catalog --</option>
          <?php foreach ($availableItems as $ai): ?>
            <option value="<?= $ai['id'] ?>">
              <?= e($ai['title']) ?> (<?= e($ai['sku']) ?> - <?= format_inr((int)$ai['price_paise']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="item_sort_order">Display Order</label>
          <input type="number" id="item_sort_order" name="sort_order" value="10" min="0" class="form-control">
        </div>

        <div class="form-group">
          <label for="item_badge_text">Promotional Badge</label>
          <input type="text" id="item_badge_text" name="badge_text" value="Featured" class="form-control">
        </div>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px;">
        <button type="button" class="btn btn-secondary" onclick="closeAddItemModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Product to Section</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Edit Badge -->
<div id="badgeModal" class="admin-modal">
  <div class="admin-modal-content" style="max-width: 440px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
      <h2 style="margin: 0; font-size: 17px; font-weight: 800;">Edit Promotional Badge</h2>
      <button type="button" onclick="closeBadgeModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #64748b;">✕</button>
    </div>

    <form method="post" action="<?= basename($_SERVER['PHP_SELF']) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_item">
      <input type="hidden" id="modal_badge_hsp_id" name="hsp_id" value="">

      <div class="form-group">
        <label for="modal_badge_input">Badge Text</label>
        <input type="text" id="modal_badge_input" name="badge_text" class="form-control" placeholder="e.g. Best Seller, Hot Deal">
        <div style="display: flex; gap: 6px; margin-top: 8px; flex-wrap: wrap;">
          <button type="button" class="badge-preset-btn" onclick="document.getElementById('modal_badge_input').value='Featured'">Featured</button>
          <button type="button" class="badge-preset-btn" onclick="document.getElementById('modal_badge_input').value='Best Seller'">Best Seller</button>
          <button type="button" class="badge-preset-btn" onclick="document.getElementById('modal_badge_input').value='Hot Deal'">Hot Deal</button>
          <button type="button" class="badge-preset-btn" onclick="document.getElementById('modal_badge_input').value='New Launch'">New Launch</button>
        </div>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
        <button type="button" class="btn btn-secondary" onclick="closeBadgeModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Badge</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Section Config -->
<div id="configModal" class="admin-modal">
  <div class="admin-modal-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
      <h2 style="margin: 0; font-size: 18px; font-weight: 800;">Section Settings</h2>
      <button type="button" onclick="closeConfigModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #64748b;">✕</button>
    </div>

    <form method="post" action="<?= basename($_SERVER['PHP_SELF']) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_section_config">

      <div class="form-group">
        <label for="cfg_display_title">Storefront Display Title *</label>
        <input type="text" id="cfg_display_title" name="display_title" value="<?= e($section['display_title'] ?? '') ?>" required class="form-control">
      </div>

      <div class="form-group">
        <label for="cfg_subtitle">Subtitle Description</label>
        <textarea id="cfg_subtitle" name="subtitle" rows="2" class="form-control"><?= e($section['subtitle'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
          <input type="checkbox" id="cfg_is_active" name="is_active" value="1" <?= (!empty($section) && (int)$section['is_active'] === 1) ? 'checked' : '' ?>>
          <strong style="color: #0f172a;">Section Active on Public Storefront</strong>
        </label>
        <small style="color: #64748b; display: block; margin-top: 4px;">
          Unchecking this safely hides the entire section from public storefront visitors.
        </small>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px;">
        <button type="button" class="btn btn-secondary" onclick="closeConfigModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Section Settings</button>
      </div>
    </form>
  </div>
</div>

<script>
  function openAddItemModal() {
    document.getElementById('addItemModal').classList.add('is-open');
  }
  function closeAddItemModal() {
    document.getElementById('addItemModal').classList.remove('is-open');
  }

  function openConfigModal() {
    document.getElementById('configModal').classList.add('is-open');
  }
  function closeConfigModal() {
    document.getElementById('configModal').classList.remove('is-open');
  }

  function openBadgeModal(hspId, currentBadge) {
    document.getElementById('modal_badge_hsp_id').value = hspId;
    document.getElementById('modal_badge_input').value = currentBadge;
    document.getElementById('badgeModal').classList.add('is-open');
  }
  function closeBadgeModal() {
    document.getElementById('badgeModal').classList.remove('is-open');
  }

  function setVisibility(hspId, newVis) {
    document.getElementById('action_hsp_id').value = hspId;
    document.getElementById('action_new_visible').value = newVis;
  }
  function setRemoveId(hspId) {
    document.getElementById('action_hsp_id').value = hspId;
  }
  function setSpotlight(hspId, sortOrder, badge, isFeat) {
    document.getElementById('action_hsp_id').value = hspId;
    document.getElementById('action_sort_order').value = sortOrder;
    document.getElementById('action_badge_text').value = badge;
    document.getElementById('action_is_featured').value = isFeat;
  }

  function moveRowUp(btn) {
    const row = btn.closest('tr');
    const prev = row.previousElementSibling;
    if (prev) {
      row.parentNode.insertBefore(row, prev);
      renumberTableRows();
    }
  }

  function moveRowDown(btn) {
    const row = btn.closest('tr');
    const next = row.nextElementSibling;
    if (next) {
      row.parentNode.insertBefore(next, row);
      renumberTableRows();
    }
  }

  function renumberTableRows() {
    const rows = document.querySelectorAll('#sectionTable tbody tr');
    rows.forEach((r, idx) => {
      const input = r.querySelector('.row-order-input');
      if (input) {
        input.value = (idx + 1) * 10;
      }
    });
  }
</script>

<?php
require_once __DIR__ . '/../includes/admin-footer.php';
