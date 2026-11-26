<?php
declare(strict_types=1);

/**
 * Admin Servers Section Management
 * Manage all server products and featured servers on the public homepage.
 * Includes drag-and-drop & keyboard reordering, badge validation, section config, and preview.
 * iboltnetworks E-Commerce Platform
 */

$adminRoot = '../';
$adminPageTitle = 'Servers Section Management | iboltnetworks Admin';

require_once __DIR__ . '/../includes/admin-header.php';
require_once __DIR__ . '/../../app/helpers/homepage.php';
require_once __DIR__ . '/../../app/helpers/uploader.php';

$db = Database::getConnection();
$sectionKey = 'servers';
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
                $message = 'Server visibility updated!';
            } else {
                $error = 'Failed to update visibility.';
            }
        }

        // 2. Update single server item display
        elseif ($action === 'update_item') {
            $hspId = sanitize_int($_POST['hsp_id'] ?? 0);
            $sortOrder = sanitize_int($_POST['sort_order'] ?? 0);
            $rawBadge = clean_string($_POST['badge_text'] ?? '');
            $isFeatured = sanitize_int($_POST['is_featured'] ?? 0);

            // Fetch product prices to validate discount claim
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
                    'section'    => 'servers',
                    'sort_order' => $sortOrder,
                    'badge'      => $validatedBadge
                ]);

                $message = 'Server display settings saved!';
            } else {
                $error = 'Server placement record not found.';
            }
        }

        // 3. Batch reorder
        elseif ($action === 'batch_reorder') {
            $orders = $_POST['orders'] ?? [];
            if (is_array($orders) && !empty($orders)) {
                update_section_products_order('servers', $orders, $adminId);
                $message = 'Server display sequence saved successfully!';
            }
        }

        // 4. Add server to section
        elseif ($action === 'add_server') {
            $productId = sanitize_int($_POST['product_id'] ?? 0);
            $sortOrder = sanitize_int($_POST['sort_order'] ?? 0);
            $badgeText = clean_string($_POST['badge_text'] ?? 'Top Server');
            $isFeatured = sanitize_int($_POST['is_featured'] ?? 0);

            if ($productId <= 0) {
                $error = 'Please select a valid server product.';
            } else {
                $res = assign_product_to_section('servers', $productId, $sortOrder, $badgeText, $isFeatured, $adminId);
                if ($res['success']) {
                    $message = 'Server added to homepage Servers section successfully!';
                } else {
                    $error = $res['error'];
                }
            }
        }

        // 5. Remove server from section
        elseif ($action === 'remove_server') {
            $hspId = sanitize_int($_POST['hsp_id'] ?? 0);
            if (remove_section_item_by_id($hspId, $adminId)) {
                $message = 'Server removed from homepage Servers section.';
            } else {
                $error = 'Failed to remove server.';
            }
        }

        // 6. Update section configuration
        elseif ($action === 'update_section_config') {
            $secTitle = clean_string($_POST['display_title'] ?? '');
            $secSubtitle = clean_string($_POST['subtitle'] ?? '');
            $secActive = sanitize_int($_POST['is_active'] ?? 1);

            if (update_homepage_section('servers', [
                'display_title' => $secTitle,
                'subtitle'      => $secSubtitle,
                'is_active'     => $secActive
            ], $adminId)) {
                $message = 'Servers section configuration updated successfully!';
                $section = get_homepage_section('servers');
            } else {
                $error = 'Failed to update section configuration.';
            }
        }
    }
}

$search = clean_string($_GET['q'] ?? '');
$statusFilter = clean_string($_GET['status'] ?? '');

$servers = get_admin_section_products('servers', $search, $statusFilter);

// Catalog servers for Add dropdown
$availableServers = $db->query("SELECT p.id, p.title, p.sku, p.price_paise, b.name AS brand_name
                               FROM products p
                               LEFT JOIN categories c ON p.category_id = c.id
                               LEFT JOIN brands b ON p.brand_id = b.id
                               WHERE (c.section = 'servers' OR p.title LIKE '%Server%')
                                 AND p.status = 'published'
                               ORDER BY p.title ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="admin-container">

  <div class="admin-page-header">
    <div>
      <div style="display: flex; align-items: center; gap: 10px;">
        <span style="font-size: 26px; background: #dcfce7; color: #166534; padding: 6px 12px; border-radius: 8px;">▤</span>
        <div>
          <h1 style="margin: 0;"><?= e($section['name'] ?? 'Servers Section Management') ?></h1>
          <p><?= e($section['subtitle'] ?? 'Control Dell PowerEdge, HPE ProLiant, Cisco UCS, and Huawei rack/tower servers on the homepage.') ?></p>
        </div>
      </div>
    </div>
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
      <a href="../homepage/preview.php?section=servers" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
        👁️ Live Section Preview
      </a>
      <button type="button" class="btn btn-secondary" onclick="openConfigModal()">⚙ Section Settings</button>
      <button type="button" class="btn btn-secondary" onclick="openAddServerModal()">+ Add Server to Homepage</button>
      <a href="../homepage/index.php" class="btn btn-secondary">← Master Overview</a>
    </div>
  </div>

  <?php if (!empty($message)): ?>
    <div class="alert alert-success"><?= e($message) ?></div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
  <?php endif; ?>

  <!-- Section Status Alert Banner if Inactive -->
  <?php if (!empty($section) && (int)$section['is_active'] === 0): ?>
    <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
      <div style="display: flex; align-items: center; gap: 8px;">
        <span style="font-size: 20px;">⚠️</span>
        <div>
          <strong style="color: #92400e;">Servers Section is Currently Inactive</strong>
          <span style="color: #b45309; font-size: 13px; display: block;">This section is completely hidden from public visitors on the homepage.</span>
        </div>
      </div>
      <button type="button" class="btn btn-primary btn-sm" onclick="openConfigModal()">Re-activate Section</button>
    </div>
  <?php endif; ?>

  <!-- Search & Filter Card -->
  <div class="admin-card" style="padding: 16px 20px;">
    <form method="get" action="servers.php" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search server models, SKU..." class="form-control" style="max-width: 300px;">
      
      <select name="status" class="form-control" style="max-width: 170px;" onchange="this.form.submit()">
        <option value="">All Servers</option>
        <option value="visible" <?= $statusFilter === 'visible' ? 'selected' : '' ?>>Visible Live</option>
        <option value="hidden" <?= $statusFilter === 'hidden' ? 'selected' : '' ?>>Hidden Placements</option>
        <option value="out_of_stock" <?= $statusFilter === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
      </select>

      <button type="submit" class="btn btn-secondary">Filter</button>
      <?php if (!empty($search) || !empty($statusFilter)): ?>
        <a href="servers.php" class="btn btn-secondary" style="color: #dc2626;">Reset</a>
      <?php endif; ?>
      <div style="margin-left: auto; font-size: 13.5px; color: #64748b; font-weight: 600;">
        <strong><?= count($servers) ?></strong> server(s) assigned
      </div>
    </form>
  </div>

  <!-- Servers Table with Reordering -->
  <div class="admin-card">
    <?php if (empty($servers)): ?>
      <div style="text-align: center; padding: 40px; color: #64748b;">
        <p>No servers currently match the criteria in the homepage Servers section.</p>
        <button type="button" class="btn btn-primary" onclick="openAddServerModal()">+ Add Server from Catalog</button>
      </div>
    <?php else: ?>
      <form id="orderBatchForm" method="post" action="servers.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="batch_reorder">

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 10px;">
          <span style="font-size: 13px; color: #64748b;">
            Drag rows or use ▲ / ▼ buttons to adjust sequence order on the homepage.
          </span>
          <button type="submit" class="btn btn-primary btn-sm">
            💾 Save Sequence Orders
          </button>
        </div>

        <div class="admin-table-wrap">
          <table class="admin-table" id="serversTable">
            <thead>
              <tr>
                <th style="width: 70px;">Sequence</th>
                <th style="width: 50px;">Image</th>
                <th>Server Model</th>
                <th>Brand</th>
                <th>Badge Label</th>
                <th>Spotlight</th>
                <th>Selling Price</th>
                <th>Visibility</th>
                <th style="text-align: right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($servers as $idx => $s): ?>
                <?php
                  $isPublished = ($s['status'] === 'published');
                  $isOutOfStock = ((int)$s['stock_quantity'] <= 0);
                  $thumb = get_product_thumbnail_path($s['primary_image'] ?? 'images/server-hud.png');
                ?>
                <tr class="draggable-row" draggable="true" data-hsp-id="<?= (int)$s['hsp_id'] ?>">
                  <!-- Sequence & Keyboard Reorder Controls -->
                  <td>
                    <div style="display: flex; align-items: center; gap: 4px;">
                      <button type="button" class="btn btn-secondary btn-sm" style="padding: 1px 4px; font-size: 10px;" onclick="moveRowUp(this)" title="Move Up">▲</button>
                      <input type="number" name="orders[<?= (int)$s['product_id'] ?>]" value="<?= (int)$s['section_sort_order'] ?>" class="form-control row-order-input" style="width: 44px; padding: 2px 4px; font-size: 11px; text-align: center;" min="0">
                      <button type="button" class="btn btn-secondary btn-sm" style="padding: 1px 4px; font-size: 10px;" onclick="moveRowDown(this)" title="Move Down">▼</button>
                    </div>
                  </td>

                  <td>
                    <img src="<?= e($adminRoot . $thumb) ?>" alt="" style="width: 46px; height: 46px; object-fit: contain; border-radius: 6px; background: #fff; border: 1px solid #e2e8f0; padding: 2px;" loading="lazy">
                  </td>

                  <td>
                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                      <a href="<?= e($adminRoot) ?>products/form.php?id=<?= (int)$s['product_id'] ?>" style="font-weight: 700; color: #0f172a; text-decoration: none;">
                        <?= e($s['title']) ?>
                      </a>
                      <?php if (!$isPublished): ?>
                        <span class="badge badge-danger" style="font-size: 10px;">Draft</span>
                      <?php endif; ?>
                      <?php if ($isOutOfStock): ?>
                        <span class="badge badge-gray" style="font-size: 10px; color: #dc2626;">0 Stock</span>
                      <?php endif; ?>
                    </div>
                    <div style="font-size: 11.5px; color: #64748b; margin-top: 2px;">
                      <code><?= e($s['sku']) ?></code> &bull; <?= e(ucfirst($s['condition_type'])) ?>
                    </div>
                  </td>

                  <td><?= e($s['brand_name'] ?? 'iboltnetworks') ?></td>

                  <!-- Badge Label Edit -->
                  <td>
                    <div style="display: flex; gap: 4px; align-items: center;">
                      <span class="badge badge-purple" style="font-size: 11px;">
                        <?= e($s['badge_text'] ?: 'None') ?>
                      </span>
                      <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 6px; font-size: 10px;" onclick='openBadgeModal(<?= (int)$s['hsp_id'] ?>, <?= json_encode($s['badge_text'] ?? '') ?>)'>
                        ✎
                      </button>
                    </div>
                  </td>

                  <!-- Spotlight Toggle -->
                  <td>
                    <button type="submit" formaction="servers.php" name="action" value="update_item" onclick="setSpotlight(<?= (int)$s['hsp_id'] ?>, <?= (int)$s['section_sort_order'] ?>, <?= json_encode($s['badge_text'] ?? '') ?>, <?= (int)$s['section_featured'] === 1 ? '0' : '1' ?>)" class="badge <?= (int)$s['section_featured'] === 1 ? 'badge-purple' : 'badge-gray' ?>" style="cursor: pointer; border: none; padding: 4px 8px;">
                      <?= (int)$s['section_featured'] === 1 ? '★ Spotlight' : '☆ Normal' ?>
                    </button>
                  </td>

                  <!-- Selling Price -->
                  <td>
                    <strong style="font-size: 13.5px; color: #078e49;">
                      <?= format_inr((int)$s['price_paise']) ?>
                    </strong>
                    <?php if (!empty($s['compare_price_paise']) && (int)$s['compare_price_paise'] > (int)$s['price_paise']): ?>
                      <div style="font-size: 11px; text-decoration: line-through; color: #94a3b8;">
                        <?= format_inr((int)$s['compare_price_paise']) ?>
                      </div>
                    <?php endif; ?>
                  </td>

                  <!-- Visibility Toggle -->
                  <td>
                    <?php $isVisible = (int)($s['is_visible'] ?? $s['section_visible'] ?? 1); ?>
                    <button type="submit" formaction="servers.php" name="action" value="toggle_visibility" onclick="setVisibility(<?= (int)$s['hsp_id'] ?>, <?= $isVisible === 1 ? '0' : '1' ?>)" class="badge <?= $isVisible === 1 ? 'badge-green' : 'badge-gray' ?>" style="cursor: pointer; border: none; padding: 4px 8px;">
                      <?= $isVisible === 1 ? '● Live' : '○ Hidden' ?>
                    </button>
                  </td>

                  <!-- Actions -->
                  <td style="text-align: right; white-space: nowrap;">
                    <a href="<?= e($adminRoot) ?>products/form.php?id=<?= (int)$s['product_id'] ?>" class="btn btn-secondary btn-sm" style="font-size: 11px; padding: 3px 6px;">Edit</a>
                    <a href="<?= e($adminRoot) ?>images/index.php?product_id=<?= (int)$s['product_id'] ?>" class="btn btn-secondary btn-sm" style="font-size: 11px; padding: 3px 6px;">Photos</a>
                    <a href="<?= e($adminRoot) ?>../product.php?slug=<?= e($s['slug']) ?>" target="_blank" class="btn btn-secondary btn-sm" style="font-size: 11px; padding: 3px 6px;">View ↗</a>
                    <button type="submit" formaction="servers.php" name="action" value="remove_server" onclick="setRemoveId(<?= (int)$s['hsp_id'] ?>); return confirm('Remove server from homepage Servers section?');" class="btn btn-danger btn-sm" style="font-size: 11px; padding: 3px 6px;" title="Remove from section">
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

<!-- Modal: Add Server -->
<div id="addServerModal" class="admin-modal">
  <div class="admin-modal-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
      <h2 style="margin: 0; font-size: 18px; font-weight: 800;">Add Server to Homepage Section</h2>
      <button type="button" onclick="closeAddServerModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #64748b;">✕</button>
    </div>

    <form method="post" action="servers.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_server">

      <div class="form-group">
        <label for="server_prod_id">Select Server Model *</label>
        <select id="server_prod_id" name="product_id" class="form-control" required style="font-size: 13.5px;">
          <option value="">-- Choose Server from Published Inventory --</option>
          <?php foreach ($availableServers as $as): ?>
            <option value="<?= $as['id'] ?>">
              <?= e($as['title']) ?> (<?= e($as['sku']) ?> - <?= format_inr((int)$as['price_paise']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="server_sort_order">Display Order</label>
          <input type="number" id="server_sort_order" name="sort_order" value="10" min="0" class="form-control">
        </div>

        <div class="form-group">
          <label for="server_badge_text">Promotional Badge</label>
          <input type="text" id="server_badge_text" name="badge_text" value="Top Server" class="form-control">
        </div>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px;">
        <button type="button" class="btn btn-secondary" onclick="closeAddServerModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Server to Homepage</button>
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

    <form method="post" action="servers.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_item">
      <input type="hidden" id="modal_badge_hsp_id" name="hsp_id" value="">

      <div class="form-group">
        <label for="modal_badge_input">Badge Text</label>
        <input type="text" id="modal_badge_input" name="badge_text" class="form-control" placeholder="e.g. Best Seller, Top Server">
        <div style="display: flex; gap: 6px; margin-top: 8px; flex-wrap: wrap;">
          <button type="button" class="badge-preset-btn" onclick="document.getElementById('modal_badge_input').value='Top Server'">Top Server</button>
          <button type="button" class="badge-preset-btn" onclick="document.getElementById('modal_badge_input').value='Featured'">Featured</button>
          <button type="button" class="badge-preset-btn" onclick="document.getElementById('modal_badge_input').value='Best Seller'">Best Seller</button>
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
      <h2 style="margin: 0; font-size: 18px; font-weight: 800;">Servers Section Settings</h2>
      <button type="button" onclick="closeConfigModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #64748b;">✕</button>
    </div>

    <form method="post" action="servers.php">
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
          Unchecking this safely hides the entire servers section from public storefront visitors.
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
  function openAddServerModal() {
    document.getElementById('addServerModal').classList.add('is-open');
  }
  function closeAddServerModal() {
    document.getElementById('addServerModal').classList.remove('is-open');
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
    const rows = document.querySelectorAll('#serversTable tbody tr');
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
