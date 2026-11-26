<?php
declare(strict_types=1);

/**
 * Admin Brands Management
 * View, create, and edit hardware brands
 * iboltnetworks E-Commerce Platform
 */

$adminRoot = '../';
$adminPageTitle = 'Hardware Brands Management | iboltnetworks Admin';

require_once __DIR__ . '/../includes/admin-header.php';

$db = Database::getConnection();

$message = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $action = clean_string($_POST['action'] ?? '');

        if ($action === 'create_brand') {
            $name = clean_string($_POST['name'] ?? '');
            $slug = slugify(clean_string($_POST['slug'] ?? ''));
            $website = clean_string($_POST['website'] ?? '');
            $isActive = sanitize_int($_POST['is_active'] ?? 1);

            if (empty($slug)) {
                $slug = slugify($name);
            }

            if (empty($name)) {
                $error = 'Please enter a brand name.';
            } else {
                try {
                    $stmt = $db->prepare("INSERT INTO brands (name, slug, website, is_active) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $slug, $website ?: null, $isActive]);
                    $message = "Brand \"{$name}\" created successfully!";
                } catch (Throwable $e) {
                    error_log('Brand create error: ' . $e->getMessage());
                    $error = 'Failed to create brand. A brand with this name or slug may already exist.';
                }
            }
        } elseif ($action === 'update_brand') {
            $brandId = sanitize_int($_POST['brand_id'] ?? 0);
            $name = clean_string($_POST['name'] ?? '');
            $slug = slugify(clean_string($_POST['slug'] ?? ''));
            $website = clean_string($_POST['website'] ?? '');
            $isActive = sanitize_int($_POST['is_active'] ?? 1);

            if ($brandId <= 0 || empty($name)) {
                $error = 'Brand name and ID are required.';
            } else {
                try {
                    $stmt = $db->prepare("UPDATE brands SET name = ?, slug = ?, website = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([$name, $slug, $website ?: null, $isActive, $brandId]);
                    $message = "Brand \"{$name}\" updated successfully!";
                } catch (Throwable $e) {
                    error_log('Brand update error: ' . $e->getMessage());
                    $error = 'Failed to update brand. Please verify the brand name and slug are unique.';
                }
            }
        } elseif ($action === 'toggle_status') {
            $brandId = sanitize_int($_POST['brand_id'] ?? 0);
            $newActive = sanitize_int($_POST['new_active'] ?? 1);
            $db->prepare("UPDATE brands SET is_active = ? WHERE id = ?")->execute([$newActive, $brandId]);
            $message = 'Brand status updated!';
        } elseif ($action === 'delete_brand') {
            $brandId = sanitize_int($_POST['brand_id'] ?? 0);
            // Safely unassign products before deletion
            $db->prepare("UPDATE products SET brand_id = NULL WHERE brand_id = ?")->execute([$brandId]);
            $db->prepare("DELETE FROM brands WHERE id = ?")->execute([$brandId]);
            $message = "Brand deleted successfully (associated products preserved and unassigned).";
        }
    }
}

$search = clean_string($_GET['q'] ?? '');

$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(b.name LIKE :s1 OR b.slug LIKE :s2)";
    $like = '%' . $search . '%';
    $params[':s1'] = $like;
    $params[':s2'] = $like;
}

$whereSql = implode(' AND ', $where);

$sql = "SELECT b.*,
               (SELECT COUNT(*) FROM products WHERE brand_id = b.id) AS product_count
        FROM brands b
        WHERE {$whereSql}
        ORDER BY b.name ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$brands = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="admin-container">

  <div class="admin-page-header">
    <div>
      <div style="display: flex; align-items: center; gap: 10px;">
        <span style="font-size: 26px; background: #f1f5f9; color: #334155; padding: 6px 12px; border-radius: 8px;">🏷️</span>
        <div>
          <h1 style="margin: 0;">Hardware Brands Management</h1>
          <p>Manage hardware manufacturers and brand filters selectable in product catalog and forms.</p>
        </div>
      </div>
    </div>
    <div>
      <button type="button" class="btn btn-primary" onclick="openCreateBrandModal()" style="min-height: 44px; display: inline-flex; align-items: center; justify-content: center;">+ Create New Brand</button>
    </div>
  </div>

  <?php if (!empty($message)): ?>
    <div class="alert alert-success"><?= e($message) ?></div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
  <?php endif; ?>

  <div class="admin-card" style="padding: 16px 20px;">
    <form method="get" action="index.php" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search brand name, slug..." class="form-control" style="flex: 1; min-width: 200px; max-width: 320px;">

      <button type="submit" class="btn btn-secondary" style="min-height: 44px; padding: 0 16px;">Filter</button>
      <?php if (!empty($search)): ?>
        <a href="index.php" class="btn btn-secondary" style="color: #dc2626; min-height: 44px; display: inline-flex; align-items: center;">Reset</a>
      <?php endif; ?>

      <div style="margin-left: auto; font-size: 13.5px; color: #64748b; font-weight: 600;">
        Total Brands: <strong><?= count($brands) ?></strong>
      </div>
    </form>
  </div>

  <div class="admin-card">
    <!-- Desktop Data Table -->
    <div class="desktop-only-wrap">
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Brand Name</th>
              <th>Slug</th>
              <th>Official Website</th>
              <th>Assigned Products</th>
              <th>Status</th>
              <th style="text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($brands)): ?>
              <tr>
                <td colspan="6" style="text-align: center; color: #64748b; padding: 30px;">
                  No hardware brands found. Click <strong>+ Create New Brand</strong> above to add one.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($brands as $b): ?>
                <tr>
                  <td>
                    <strong style="color: #0f172a; font-size: 14.5px;"><?= e($b['name']) ?></strong>
                  </td>
                  <td><code><?= e($b['slug']) ?></code></td>
                  <td>
                    <?php if (!empty($b['website'])): ?>
                      <a href="<?= e($b['website']) ?>" target="_blank" rel="noopener noreferrer" style="font-size: 13px;">Visit Site ↗</a>
                    <?php else: ?>
                      <span style="color: #94a3b8;">—</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <a href="<?= e($adminRoot) ?>products/index.php?brand=<?= urlencode($b['slug']) ?>" style="font-weight: 700;">
                      <?= (int)$b['product_count'] ?> products
                    </a>
                  </td>
                  <td>
                    <form method="post" action="index.php" style="display: inline-block;">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="toggle_status">
                      <input type="hidden" name="brand_id" value="<?= (int)$b['id'] ?>">
                      <input type="hidden" name="new_active" value="<?= $b['is_active'] ? '0' : '1' ?>">
                      <button type="submit" class="badge <?= $b['is_active'] ? 'badge-green' : 'badge-gray' ?>" style="cursor: pointer; border: none; font-size: 12px; padding: 4px 10px;">
                        <?= $b['is_active'] ? '● Active' : '○ Inactive' ?>
                      </button>
                    </form>
                  </td>
                  <td style="text-align: right;">
                    <div style="display: flex; gap: 6px; justify-content: flex-end;">
                      <button type="button" class="btn btn-secondary btn-sm" onclick='openEditBrandModal(<?= json_encode($b, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>Edit</button>

                      <form method="post" action="index.php" onsubmit="return confirm('Are you sure you want to delete brand &quot;<?= e(addslashes($b['name'])) ?>&quot;? Products will not be deleted.');" style="display: inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_brand">
                        <input type="hidden" name="brand_id" value="<?= (int)$b['id'] ?>">
                        <button type="submit" class="btn btn-secondary btn-sm" style="color: #dc2626;">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Mobile Card View -->
    <div class="mobile-only-flex" style="flex-direction: column; gap: 12px;">
      <?php foreach ($brands as $b): ?>
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
            <div>
              <strong style="font-size: 15px; color: #0f172a;"><?= e($b['name']) ?></strong>
              <div style="font-size: 12px; color: #64748b; margin-top: 2px;"><code><?= e($b['slug']) ?></code></div>
            </div>
            <form method="post" action="index.php">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle_status">
              <input type="hidden" name="brand_id" value="<?= (int)$b['id'] ?>">
              <input type="hidden" name="new_active" value="<?= $b['is_active'] ? '0' : '1' ?>">
              <button type="submit" class="badge <?= $b['is_active'] ? 'badge-green' : 'badge-gray' ?>" style="cursor: pointer; border: none; font-size: 11px;">
                <?= $b['is_active'] ? 'Active' : 'Inactive' ?>
              </button>
            </form>
          </div>

          <div style="font-size: 13px; color: #64748b; margin-bottom: 12px;">
            Linked Products: <strong style="color: #0f172a;"><?= (int)$b['product_count'] ?></strong>
          </div>

          <div style="display: flex; gap: 8px; justify-content: flex-end; border-top: 1px solid #f1f5f9; padding-top: 10px;">
            <button type="button" class="btn btn-secondary btn-sm" style="flex: 1;" onclick='openEditBrandModal(<?= json_encode($b, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>Edit</button>
            <form method="post" action="index.php" onsubmit="return confirm('Delete brand &quot;<?= e(addslashes($b['name'])) ?>&quot;?');" style="flex: 1;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_brand">
              <input type="hidden" name="brand_id" value="<?= (int)$b['id'] ?>">
              <button type="submit" class="btn btn-secondary btn-sm" style="width: 100%; color: #dc2626;">Delete</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>

<!-- Create Brand Modal -->
<div class="admin-modal" id="createBrandModal">
  <div class="admin-modal-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
      <h2 style="margin: 0; font-size: 20px;">+ Create New Brand</h2>
      <button type="button" onclick="closeCreateBrandModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #64748b;">✕</button>
    </div>

    <form method="post" action="index.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_brand">

      <div class="form-group">
        <label for="create_brand_name">Brand Name *</label>
        <input type="text" id="create_brand_name" name="name" class="form-control" required placeholder="e.g. SK Hynix, Micron, Molex" oninput="autoSlugBrand(this.value, 'create_brand_slug')">
      </div>

      <div class="form-group">
        <label for="create_brand_slug">Slug (URL friendly identifier) *</label>
        <input type="text" id="create_brand_slug" name="slug" class="form-control" required placeholder="e.g. sk-hynix">
      </div>

      <div class="form-group">
        <label for="create_brand_website">Official Website URL (Optional)</label>
        <input type="url" id="create_brand_website" name="website" class="form-control" placeholder="https://example.com">
      </div>

      <div class="form-group">
        <label for="create_brand_active">Status</label>
        <select id="create_brand_active" name="is_active" class="form-control">
          <option value="1">Active (Selectable in Product Form)</option>
          <option value="0">Inactive / Hidden</option>
        </select>
      </div>

      <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 24px;">
        <button type="button" class="btn btn-secondary" onclick="closeCreateBrandModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Brand</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Brand Modal -->
<div class="admin-modal" id="editBrandModal">
  <div class="admin-modal-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
      <h2 style="margin: 0; font-size: 20px;">Edit Brand</h2>
      <button type="button" onclick="closeEditBrandModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #64748b;">✕</button>
    </div>

    <form method="post" action="index.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_brand">
      <input type="hidden" id="edit_brand_id" name="brand_id" value="">

      <div class="form-group">
        <label for="edit_brand_name">Brand Name *</label>
        <input type="text" id="edit_brand_name" name="name" class="form-control" required>
      </div>

      <div class="form-group">
        <label for="edit_brand_slug">Slug *</label>
        <input type="text" id="edit_brand_slug" name="slug" class="form-control" required>
      </div>

      <div class="form-group">
        <label for="edit_brand_website">Official Website URL</label>
        <input type="url" id="edit_brand_website" name="website" class="form-control">
      </div>

      <div class="form-group">
        <label for="edit_brand_active">Status</label>
        <select id="edit_brand_active" name="is_active" class="form-control">
          <option value="1">Active (Selectable in Product Form)</option>
          <option value="0">Inactive / Hidden</option>
        </select>
      </div>

      <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 24px;">
        <button type="button" class="btn btn-secondary" onclick="closeEditBrandModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function autoSlugBrand(val, targetId) {
  const target = document.getElementById(targetId);
  if (!target) return;
  target.value = val.toLowerCase()
    .trim()
    .replace(/[^\w\s-]/g, '')
    .replace(/[\s_-]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

function openCreateBrandModal() {
  document.getElementById('createBrandModal').classList.add('is-open');
}
function closeCreateBrandModal() {
  document.getElementById('createBrandModal').classList.remove('is-open');
}

function openEditBrandModal(b) {
  document.getElementById('edit_brand_id').value = b.id;
  document.getElementById('edit_brand_name').value = b.name;
  document.getElementById('edit_brand_slug').value = b.slug;
  document.getElementById('edit_brand_website').value = b.website || '';
  document.getElementById('edit_brand_active').value = b.is_active;
  document.getElementById('editBrandModal').classList.add('is-open');
}
function closeEditBrandModal() {
  document.getElementById('editBrandModal').classList.remove('is-open');
}
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
