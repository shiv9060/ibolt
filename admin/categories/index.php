<?php
declare(strict_types=1);

/**
 * Admin Categories Management
 * View, create, and edit product categories and homepage section assignments
 * iboltnetworks E-Commerce Platform
 */

$adminRoot = '../';
$adminPageTitle = 'Product Categories Management | iboltnetworks Admin';

require_once __DIR__ . '/../includes/admin-header.php';

$db = Database::getConnection();

$message = '';
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $action = clean_string($_POST['action'] ?? '');

        if ($action === 'create_category') {
            $name = clean_string($_POST['name'] ?? '');
            $slug = slugify(clean_string($_POST['slug'] ?? ''));
            $section = clean_string($_POST['section'] ?? 'general');
            $parentId = sanitize_int($_POST['parent_id'] ?? 0) ?: null;
            $sortOrder = sanitize_int($_POST['sort_order'] ?? 0);
            $description = clean_string($_POST['description'] ?? '');

            if (empty($slug)) {
                $slug = slugify($name);
            }

            if (empty($name)) {
                $error = 'Please enter a category name.';
            } else {
                try {
                    $stmt = $db->prepare("INSERT INTO categories (parent_id, name, slug, section, description, sort_order, is_active)
                                          VALUES (?, ?, ?, ?, ?, ?, 1)");
                    $stmt->execute([$parentId, $name, $slug, $section, $description, $sortOrder]);
                    $message = "Category \"{$name}\" created successfully!";
                } catch (Throwable $e) {
                    error_log('Category create error: ' . $e->getMessage()); $error = 'Failed to create category. A category with this name or slug may already exist.';
                }
            }
        } elseif ($action === 'update_category') {
            $catId = sanitize_int($_POST['category_id'] ?? 0);
            $name = clean_string($_POST['name'] ?? '');
            $slug = slugify(clean_string($_POST['slug'] ?? ''));
            $section = clean_string($_POST['section'] ?? 'general');
            $parentId = sanitize_int($_POST['parent_id'] ?? 0) ?: null;
            $sortOrder = sanitize_int($_POST['sort_order'] ?? 0);
            $description = clean_string($_POST['description'] ?? '');
            $isActive = sanitize_int($_POST['is_active'] ?? 1);

            if ($catId <= 0 || empty($name)) {
                $error = 'Category name and ID are required.';
            } else {
                try {
                    $stmt = $db->prepare("UPDATE categories SET
                                            parent_id = ?, name = ?, slug = ?, section = ?,
                                            description = ?, sort_order = ?, is_active = ?
                                          WHERE id = ?");
                    $stmt->execute([$parentId, $name, $slug, $section, $description, $sortOrder, $isActive, $catId]);
                    $message = "Category \"{$name}\" updated successfully!";
                } catch (Throwable $e) {
                    error_log('Category update error: ' . $e->getMessage()); $error = 'Failed to update category. Please verify the category name and slug are unique.';
                }
            }
        } elseif ($action === 'toggle_status') {
            $catId = sanitize_int($_POST['category_id'] ?? 0);
            $newActive = sanitize_int($_POST['new_active'] ?? 1);
            $db->prepare("UPDATE categories SET is_active = ? WHERE id = ?")->execute([$newActive, $catId]);
            $message = 'Category status updated!';
        } elseif ($action === 'delete_category') {
            $catId = sanitize_int($_POST['category_id'] ?? 0);
            // Check if products exist in category
            $prodCountStmt = $db->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
            $prodCountStmt->execute([$catId]);
            $pCount = (int)$prodCountStmt->fetchColumn();

            if ($pCount > 0) {
                // Set products category_id to NULL to prevent data loss
                $db->prepare("UPDATE products SET category_id = NULL WHERE category_id = ?")->execute([$catId]);
            }
            $db->prepare("DELETE FROM categories WHERE id = ?")->execute([$catId]);
            $message = "Category deleted successfully (products preserved and unassigned).";
        }
    }
}

$sectionFilter = clean_string($_GET['section'] ?? '');
$search = clean_string($_GET['q'] ?? '');

$where = ["1=1"];
$params = [];

if (!empty($sectionFilter)) {
    $where[] = "c.section = :sec";
    $params[':sec'] = $sectionFilter;
}

if (!empty($search)) {
    $where[] = "(c.name LIKE :s1 OR c.slug LIKE :s2)";
    $like = '%' . $search . '%';
    $params[':s1'] = $like;
    $params[':s2'] = $like;
}

$whereSql = implode(' AND ', $where);

$sql = "SELECT c.*,
               p.name AS parent_name,
               (SELECT COUNT(*) FROM products WHERE category_id = c.id) AS product_count
        FROM categories c
        LEFT JOIN categories p ON c.parent_id = p.id
        WHERE {$whereSql}
        ORDER BY c.section ASC, c.sort_order ASC, c.name ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$parentOptions = $db->query("SELECT id, name, section FROM categories WHERE parent_id IS NULL ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="admin-container">

  <div class="admin-page-header">
    <div>
      <div style="display: flex; align-items: center; gap: 10px;">
        <span style="font-size: 26px; background: #f1f5f9; color: #334155; padding: 6px 12px; border-radius: 8px;">📁</span>
        <div>
          <h1 style="margin: 0;">Product Categories Management</h1>
          <p>Organize category hierarchy, section mappings (servers, networking, components, our-products), and navigation.</p>
        </div>
      </div>
    </div>
    <div>
      <button type="button" class="btn btn-primary" onclick="openCreateModal()" style="min-height: 44px; display: inline-flex; align-items: center; justify-content: center;">+ Create New Category</button>
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
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search category name, slug..." class="form-control" style="flex: 1; min-width: 200px; max-width: 280px;">

      <select name="section" class="form-control" style="width: auto; min-width: 180px;" onchange="this.form.submit()">
        <option value="">All Storefront Sections</option>
        <option value="servers" <?= $sectionFilter === 'servers' ? 'selected' : '' ?>>Servers</option>
        <option value="networking" <?= $sectionFilter === 'networking' ? 'selected' : '' ?>>Networking</option>
        <option value="components" <?= $sectionFilter === 'components' ? 'selected' : '' ?>>Components</option>
        <option value="our-products" <?= $sectionFilter === 'our-products' ? 'selected' : '' ?>>Our Products</option>
        <option value="general" <?= $sectionFilter === 'general' ? 'selected' : '' ?>>General</option>
      </select>

      <button type="submit" class="btn btn-secondary" style="min-height: 44px; padding: 0 16px;">Filter</button>
      <?php if (!empty($search) || !empty($sectionFilter)): ?>
        <a href="index.php" class="btn btn-secondary" style="color: #dc2626; min-height: 44px; display: inline-flex; align-items: center;">Reset</a>
      <?php endif; ?>

      <div style="margin-left: auto; font-size: 13.5px; color: #64748b; font-weight: 600;">
        Found <strong><?= count($categories) ?></strong> categories
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
              <th>Category Name</th>
              <th>Slug</th>
              <th>Parent Category</th>
              <th>Storefront Section</th>
              <th>Display Order</th>
              <th>Assigned Products</th>
              <th>Status</th>
              <th style="text-align: right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($categories as $cat): ?>
              <tr>
                <td>
                  <strong style="color: #0f172a; font-size: 14px;"><?= e($cat['name']) ?></strong>
                  <?php if (!empty($cat['description'])): ?>
                    <div style="font-size: 11.5px; color: #64748b; margin-top: 2px;">
                      <?= e(substr($cat['description'], 0, 80)) ?><?= strlen($cat['description']) > 80 ? '...' : '' ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td><code><?= e($cat['slug']) ?></code></td>
                <td><?= e($cat['parent_name'] ?? '— Root —') ?></td>
                <td>
                  <span class="badge <?= match($cat['section']) {
                    'servers' => 'badge-green',
                    'networking' => 'badge-purple',
                    'components' => 'badge-yellow',
                    'our-products' => 'badge-red',
                    default => 'badge-gray'
                  } ?>">
                    <?= e(ucfirst($cat['section'])) ?>
                  </span>
                </td>
                <td><?= (int)$cat['sort_order'] ?></td>
                <td>
                  <a href="<?= e($adminRoot) ?>products/index.php?category=<?= urlencode($cat['slug']) ?>" style="font-weight: 700;">
                    <?= (int)$cat['product_count'] ?> products
                  </a>
                </td>
                <td>
                  <form method="post" action="index.php" style="display: inline-block;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="category_id" value="<?= (int)$cat['id'] ?>">
                    <input type="hidden" name="new_active" value="<?= (int)$cat['is_active'] === 1 ? '0' : '1' ?>">
                    <button type="submit" class="badge <?= (int)$cat['is_active'] === 1 ? 'badge-green' : 'badge-gray' ?>" style="cursor: pointer; border: none; padding: 4px 8px;">
                      <?= (int)$cat['is_active'] === 1 ? 'Active' : 'Disabled' ?>
                    </button>
                  </form>
                </td>
                <td style="text-align: right; white-space: nowrap;">
                  <button type="button" class="btn btn-secondary btn-sm" onclick='openEditModal(<?= json_encode($cat, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>Edit</button>
                  <a href="<?= e($adminRoot) ?>../products.php?category=<?= urlencode($cat['slug']) ?>" target="_blank" class="btn btn-secondary btn-sm">View ↗</a>
                  <form method="post" action="index.php" style="display: inline-block;" onsubmit="return confirm('Delete category <?= e(addslashes($cat['name'])) ?>?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_category">
                    <input type="hidden" name="category_id" value="<?= (int)$cat['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Mobile Category Cards (≤ 768px) -->
    <div class="mobile-only" style="display: flex; flex-direction: column; gap: 12px;">
      <?php if (empty($categories)): ?>
        <p style="text-align: center; color: #64748b; padding: 24px;">No categories found matching criteria.</p>
      <?php else: ?>
        <?php foreach ($categories as $cat): ?>
          <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; margin-bottom: 6px;">
              <div>
                <strong style="color: #0f172a; font-size: 15px;"><?= e($cat['name']) ?></strong>
                <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                  <code><?= e($cat['slug']) ?></code> &bull; <?= e($cat['parent_name'] ?? 'Root') ?>
                </div>
              </div>
              <form method="post" action="index.php" style="margin: 0;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="category_id" value="<?= (int)$cat['id'] ?>">
                <input type="hidden" name="new_active" value="<?= (int)$cat['is_active'] === 1 ? '0' : '1' ?>">
                <button type="submit" class="badge <?= (int)$cat['is_active'] === 1 ? 'badge-green' : 'badge-gray' ?>" style="cursor: pointer; border: none; padding: 6px 10px; min-height: 36px; font-size: 11px;">
                  <?= (int)$cat['is_active'] === 1 ? '● Active' : '○ Disabled' ?>
                </button>
              </form>
            </div>

            <?php if (!empty($cat['description'])): ?>
              <p style="font-size: 12px; color: #475569; margin: 4px 0 8px; line-height: 1.4;">
                <?= e(substr($cat['description'], 0, 100)) ?><?= strlen($cat['description']) > 100 ? '...' : '' ?>
              </p>
            <?php endif; ?>

            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; background: #f8fafc; padding: 8px 10px; border-radius: 6px; margin-bottom: 12px;">
              <span>Section: <strong style="text-transform: capitalize; color: #0f172a;"><?= e($cat['section']) ?></strong></span>
              <a href="<?= e($adminRoot) ?>products/index.php?category=<?= urlencode($cat['slug']) ?>" style="font-weight: 700; color: #087fc0;">
                <?= (int)$cat['product_count'] ?> products &rarr;
              </a>
            </div>

            <div style="display: flex; gap: 8px; align-items: center;">
              <button type="button" class="btn btn-secondary btn-sm" style="flex: 1; min-height: 44px; display: inline-flex; align-items: center; justify-content: center; font-weight: 700;" onclick='openEditModal(<?= json_encode($cat, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                ✏️ Edit
              </button>
              <a href="<?= e($adminRoot) ?>../products.php?category=<?= urlencode($cat['slug']) ?>" target="_blank" class="btn btn-secondary btn-sm" style="min-height: 44px; padding: 0 14px; display: inline-flex; align-items: center; justify-content: center;">
                Storefront ↗
              </a>
              <form method="post" action="index.php" style="margin: 0;" onsubmit="return confirm('Delete category <?= e(addslashes($cat['name'])) ?>?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_category">
                <input type="hidden" name="category_id" value="<?= (int)$cat['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm" style="min-height: 44px; padding: 0 14px; display: inline-flex; align-items: center; justify-content: center;" title="Delete Category">
                  🗑️
                </button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- Modal: Create / Edit Category -->
<div id="categoryModal" class="admin-modal">
  <div class="admin-modal-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
      <h2 id="modalTitle" style="margin: 0; font-size: 18px; font-weight: 800;">Create Category</h2>
      <button type="button" onclick="closeCatModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #64748b;">✕</button>
    </div>

    <form method="post" action="index.php">
      <?= csrf_field() ?>
      <input type="hidden" id="formAction" name="action" value="create_category">
      <input type="hidden" id="categoryId" name="category_id" value="">

      <div class="form-group">
        <label for="cat_name">Category Name *</label>
        <input type="text" id="cat_name" name="name" class="form-control" required placeholder="e.g. Ethernet Adapters">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="cat_slug">Slug (URL identifier)</label>
          <input type="text" id="cat_slug" name="slug" class="form-control" placeholder="e.g. ethernet-adapters">
        </div>
        <div class="form-group">
          <label for="cat_section">Storefront Section *</label>
          <select id="cat_section" name="section" class="form-control" required>
            <option value="servers">Servers Section</option>
            <option value="networking">Networking Section</option>
            <option value="components">Components Section</option>
            <option value="our-products">Our Products Section</option>
            <option value="general">General Catalogue</option>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="cat_parent_id">Parent Category (Optional)</label>
          <select id="cat_parent_id" name="parent_id" class="form-control">
            <option value="">— None (Top-level Category) —</option>
            <?php foreach ($parentOptions as $po): ?>
              <option value="<?= $po['id'] ?>"><?= e($po['name']) ?> (<?= e($po['section']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="cat_sort_order">Display Sort Order</label>
          <input type="number" id="cat_sort_order" name="sort_order" value="0" class="form-control">
        </div>
      </div>

      <div class="form-group">
        <label for="cat_desc">Category Description</label>
        <textarea id="cat_desc" name="description" rows="3" class="form-control" placeholder="Short description shown on category headers..."></textarea>
      </div>

      <div id="statusGroup" class="form-group" style="display: none;">
        <label for="cat_is_active">Active Status</label>
        <select id="cat_is_active" name="is_active" class="form-control">
          <option value="1">Active (Visible)</option>
          <option value="0">Disabled (Hidden)</option>
        </select>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 24px;">
        <button type="button" class="btn btn-secondary" onclick="closeCatModal()">Cancel</button>
        <button type="submit" class="btn btn-primary" id="saveBtn">Save Category</button>
      </div>
    </form>
  </div>
</div>

<script>
  function openCreateModal() {
    document.getElementById('modalTitle').textContent = 'Create New Category';
    document.getElementById('formAction').value = 'create_category';
    document.getElementById('categoryId').value = '';
    document.getElementById('cat_name').value = '';
    document.getElementById('cat_slug').value = '';
    document.getElementById('cat_section').value = 'servers';
    document.getElementById('cat_parent_id').value = '';
    document.getElementById('cat_sort_order').value = '0';
    document.getElementById('cat_desc').value = '';
    document.getElementById('statusGroup').style.display = 'none';
    document.getElementById('saveBtn').textContent = 'Create Category';
    document.getElementById('categoryModal').classList.add('is-open');
  }

  function openEditModal(cat) {
    document.getElementById('modalTitle').textContent = 'Edit Category: ' + cat.name;
    document.getElementById('formAction').value = 'update_category';
    document.getElementById('categoryId').value = cat.id;
    document.getElementById('cat_name').value = cat.name;
    document.getElementById('cat_slug').value = cat.slug;
    document.getElementById('cat_section').value = cat.section;
    document.getElementById('cat_parent_id').value = cat.parent_id || '';
    document.getElementById('cat_sort_order').value = cat.sort_order;
    document.getElementById('cat_desc').value = cat.description || '';
    document.getElementById('cat_is_active').value = cat.is_active;
    document.getElementById('statusGroup').style.display = 'block';
    document.getElementById('saveBtn').textContent = 'Update Category';
    document.getElementById('categoryModal').classList.add('is-open');
  }

  function closeCatModal() {
    document.getElementById('categoryModal').classList.remove('is-open');
  }
</script>

<?php
require_once __DIR__ . '/../includes/admin-footer.php';
