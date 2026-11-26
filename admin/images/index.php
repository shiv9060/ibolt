<?php
declare(strict_types=1);

/**
 * Admin Product Image Management & Gallery Controller
 * Comprehensive multi-image uploader, drag-and-drop & keyboard reordering,
 * single-image replacement, batch deletion, primary selection, and health audit.
 * iboltnetworks E-Commerce Platform
 */

$adminRoot = '../';
$adminPageTitle = 'Product Images & Gallery Management | iboltnetworks Admin';

require_once __DIR__ . '/../includes/admin-header.php';
require_once __DIR__ . '/../../app/helpers/uploader.php';

$db = Database::getConnection();

$message = '';
$error = '';
$activeTab = clean_string($_GET['tab'] ?? 'gallery');
if (!in_array($activeTab, ['gallery', 'catalog', 'audit'], true)) {
    $activeTab = 'gallery';
}

$selectedProductId = sanitize_int($_GET['product_id'] ?? $_POST['product_id'] ?? 0);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $action = clean_string($_POST['action'] ?? '');

        // 1. Multiple or Single Image Upload
        if ($action === 'upload_images') {
            $targetProdId = sanitize_int($_POST['product_id'] ?? 0);
            if ($targetProdId <= 0) {
                $error = 'Please select a valid product to upload images for.';
            } elseif (empty($_FILES['images']) || empty($_FILES['images']['name'][0])) {
                $error = 'Please select one or more image files to upload.';
            } else {
                $results = upload_multiple_product_images($targetProdId, $_FILES['images']);
                $successCount = 0;
                $errs = [];
                foreach ($results as $res) {
                    if ($res['success']) {
                        $successCount++;
                    } else {
                        $errs[] = $res['error'];
                    }
                }
                if ($successCount > 0) {
                    $message = "Successfully uploaded {$successCount} image(s)!";
                }
                if (!empty($errs)) {
                    $error = implode('; ', array_unique($errs));
                }
                $selectedProductId = $targetProdId;
                $activeTab = 'gallery';
            }
        }

        // 2. Set as Primary Image
        elseif ($action === 'set_primary') {
            $imageId = sanitize_int($_POST['image_id'] ?? 0);
            $targetProdId = sanitize_int($_POST['product_id'] ?? 0);
            if (set_primary_product_image($targetProdId, $imageId)) {
                $message = 'Primary product image updated successfully!';
            } else {
                $error = 'Failed to update primary image (ownership verification failed).';
            }
            $selectedProductId = $targetProdId;
            $activeTab = 'gallery';
        }

        // 3. Delete Single Image
        elseif ($action === 'delete_image') {
            $imageId = sanitize_int($_POST['image_id'] ?? 0);
            $targetProdId = sanitize_int($_POST['product_id'] ?? 0);
            $delRes = delete_product_image($imageId, $targetProdId);
            if ($delRes['success']) {
                $message = 'Image deleted successfully.';
            } else {
                $error = $delRes['error'] ?: 'Failed to delete image.';
            }
            $selectedProductId = $targetProdId;
            $activeTab = 'gallery';
        }

        // 4. Batch Delete Selected Images
        elseif ($action === 'delete_selected') {
            $targetProdId = sanitize_int($_POST['product_id'] ?? 0);
            $selectedImageIds = $_POST['selected_images'] ?? [];
            if (empty($selectedImageIds) || !is_array($selectedImageIds)) {
                $error = 'No images were selected for batch deletion.';
            } else {
                $delBatch = delete_multiple_product_images($targetProdId, $selectedImageIds);
                if ($delBatch['count'] > 0) {
                    $message = "Successfully deleted {$delBatch['count']} selected image(s).";
                }
                if (!empty($delBatch['errors'])) {
                    $error = implode('; ', $delBatch['errors']);
                }
            }
            $selectedProductId = $targetProdId;
            $activeTab = 'gallery';
        }

        // 5. Replace Image
        elseif ($action === 'replace_image') {
            $imageId = sanitize_int($_POST['image_id'] ?? 0);
            $targetProdId = sanitize_int($_POST['product_id'] ?? 0);
            if (empty($_FILES['replacement_file']['tmp_name'])) {
                $error = 'Please select a replacement image file.';
            } else {
                $repRes = replace_product_image($imageId, $_FILES['replacement_file'], $targetProdId);
                if ($repRes['success']) {
                    $message = 'Product image replaced successfully!';
                } else {
                    $error = $repRes['error'] ?: 'Failed to replace image.';
                }
            }
            $selectedProductId = $targetProdId;
            $activeTab = 'gallery';
        }

        // 6. Update Sort Orders
        elseif ($action === 'update_order') {
            $targetProdId = sanitize_int($_POST['product_id'] ?? 0);
            if (!empty($_POST['orders']) && is_array($_POST['orders'])) {
                update_product_images_order($targetProdId, $_POST['orders']);
                $message = 'Image display orders updated successfully!';
            }
            $selectedProductId = $targetProdId;
            $activeTab = 'gallery';
        }

        // 7. Health Audit: Cleanup Confirmed Orphaned Files
        elseif ($action === 'cleanup_orphaned') {
            $orphansToClean = $_POST['orphaned_paths'] ?? [];
            if (empty($orphansToClean) || !is_array($orphansToClean)) {
                $error = 'No orphaned files selected for cleanup.';
            } else {
                $cleanResult = cleanup_orphaned_files($orphansToClean);
                if ($cleanResult['deleted_count'] > 0) {
                    $message = "Successfully cleaned up {$cleanResult['deleted_count']} orphaned file(s).";
                }
                if (!empty($cleanResult['failed'])) {
                    $error = implode('; ', $cleanResult['failed']);
                }
            }
            $activeTab = 'audit';
        }
    }
}

// Fetch all products for selector dropdown
$allProducts = $db->query("SELECT id, title, sku, price_paise FROM products ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);

// If a specific product is selected, fetch its images
$selectedProduct = null;
$productImages = [];
if ($selectedProductId > 0) {
    $pStmt = $db->prepare("SELECT p.*, b.name AS brand_name, c.name AS category_name
                           FROM products p
                           LEFT JOIN brands b ON p.brand_id = b.id
                           LEFT JOIN categories c ON p.category_id = c.id
                           WHERE p.id = ? LIMIT 1");
    $pStmt->execute([$selectedProductId]);
    $selectedProduct = $pStmt->fetch(PDO::FETCH_ASSOC);

    if ($selectedProduct) {
        $imgStmt = $db->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC");
        $imgStmt->execute([$selectedProductId]);
        $productImages = $imgStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Recent image gallery overview (for catalog tab)
$recentImages = [];
if ($activeTab === 'catalog' || (!$selectedProduct && $activeTab === 'gallery')) {
    $recentImages = $db->query("SELECT pi.*, p.title AS product_title, p.sku AS product_sku
                                FROM product_images pi
                                INNER JOIN products p ON pi.product_id = p.id
                                ORDER BY pi.id DESC LIMIT 48")->fetchAll(PDO::FETCH_ASSOC);
}

// Health audit data
$auditData = null;
if ($activeTab === 'audit') {
    $auditData = audit_image_library();
}
?>

<style>
  .gallery-nav-pills {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 12px;
    flex-wrap: wrap;
  }
  .gallery-pill {
    padding: 10px 16px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    color: #475569;
    background: #f1f5f9;
    transition: all 0.15s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 42px;
  }
  .gallery-pill:hover {
    background: #e2e8f0;
    color: #0f172a;
  }
  .gallery-pill.is-active {
    background: #078e49;
    color: #ffffff;
  }

  /* Interactive Image Card & Drag-Drop */
  .image-card {
    cursor: grab;
    user-select: none;
    transition: transform 0.15s, box-shadow 0.15s, border-color 0.15s;
    background: #ffffff;
  }
  .image-card:active {
    cursor: grabbing;
  }
  .image-card.dragging {
    opacity: 0.45;
    border: 2px dashed #078e49 !important;
    transform: scale(0.96);
  }
  .image-card.drag-over {
    border-color: #078e49 !important;
    box-shadow: 0 0 0 3px rgba(7, 142, 73, 0.25);
  }
  .image-card.is-primary {
    border-color: #078e49 !important;
    box-shadow: 0 0 0 2px rgba(7, 142, 73, 0.2);
    background: #f0fdf4 !important;
  }

  .batch-actions-bar {
    background: #f8fafc;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 10px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    flex-wrap: wrap;
    gap: 10px;
  }

  .dropzone-container {
    background: #f8fafc;
    border: 2px dashed #94a3b8;
    border-radius: 12px;
    padding: 24px;
    text-align: center;
    transition: all 0.2s;
    cursor: pointer;
    margin-bottom: 24px;
  }
  .dropzone-container.is-dragover {
    background: #f0fdf4;
    border-color: #078e49;
  }

  .upload-preview-card {
    position: relative;
    width: 100px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    overflow: hidden;
    background: #ffffff;
    padding: 4px;
    text-align: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  }
  .upload-preview-card img {
    width: 100%;
    height: 75px;
    object-fit: contain;
    border-radius: 4px;
  }
  .upload-preview-card .filename {
    font-size: 10px;
    color: #475569;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    display: block;
    margin-top: 2px;
  }
  .upload-preview-card .remove-btn {
    position: absolute;
    top: 2px;
    right: 2px;
    background: #ef4444;
    color: #fff;
    border: none;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 11px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
  }

  .audit-metric-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 18px;
    flex: 1;
    min-width: 180px;
  }
  .audit-metric-number {
    font-size: 28px;
    font-weight: 800;
    margin-top: 4px;
  }
</style>

<div class="admin-container">

  <div class="admin-page-header">
    <div>
      <div style="display: flex; align-items: center; gap: 10px;">
        <span style="font-size: 26px; background: #fdf2f8; color: #be185d; padding: 6px 12px; border-radius: 8px;">🖼️</span>
        <div>
          <h1 style="margin: 0;">Product Images &amp; Gallery Management</h1>
          <p>Multi-image gallery, primary photo promotion, drag-and-drop &amp; keyboard reordering, replacement, and library health audit.</p>
        </div>
      </div>
    </div>
    <div style="display: flex; gap: 8px;">
      <?php if ($selectedProduct): ?>
        <a href="<?= e($adminRoot) ?>products/form.php?id=<?= (int)$selectedProduct['id'] ?>" class="btn btn-secondary">Edit Product Details</a>
        <a href="<?= e($adminRoot) ?>../product.php?slug=<?= e($selectedProduct['slug']) ?>" target="_blank" class="btn btn-secondary">View Product Page ↗</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($message)): ?>
    <div class="alert alert-success"><?= e($message) ?></div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
  <?php endif; ?>

  <!-- Sub-Navigation Tabs -->
  <div class="gallery-nav-pills">
    <a href="index.php?tab=gallery<?= $selectedProductId > 0 ? '&product_id=' . $selectedProductId : '' ?>" class="gallery-pill <?= $activeTab === 'gallery' ? 'is-active' : '' ?>">
      🖼️ Product Gallery
    </a>
    <a href="index.php?tab=catalog" class="gallery-pill <?= $activeTab === 'catalog' ? 'is-active' : '' ?>">
      🗂️ Catalog Overview
    </a>
    <a href="index.php?tab=audit" class="gallery-pill <?= $activeTab === 'audit' ? 'is-active' : '' ?>">
      🩺 Health &amp; Integrity Audit
    </a>
  </div>

  <!-- TAB 1: PRODUCT GALLERY (Selected Product or Selector) -->
  <?php if ($activeTab === 'gallery'): ?>

    <!-- Product Selector -->
    <div class="admin-card" style="padding: 18px 24px;">
      <form method="get" action="index.php" style="display: flex; gap: 14px; align-items: center; flex-wrap: wrap;">
        <input type="hidden" name="tab" value="gallery">
        <label for="select_prod" style="font-weight: 700; color: #0f172a; margin: 0;">Select Product to Manage Gallery:</label>
        <select id="select_prod" name="product_id" class="form-control" style="max-width: 480px;" onchange="this.form.submit()">
          <option value="">-- Choose Hardware Product from Catalog --</option>
          <?php foreach ($allProducts as $p): ?>
            <option value="<?= $p['id'] ?>" <?= $selectedProductId === (int)$p['id'] ? 'selected' : '' ?>>
              <?= e($p['title']) ?> (<?= e($p['sku']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">Open Gallery</button>
        <?php if ($selectedProductId > 0): ?>
          <a href="index.php?tab=catalog" class="btn btn-secondary">View Catalog Photos</a>
        <?php endif; ?>
      </form>
    </div>

    <?php if ($selectedProduct): ?>
      <div class="admin-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; border-bottom: 1px solid #f1f5f9; padding-bottom: 16px;">
          <div>
            <h2 style="margin: 0; font-size: 20px;">
              <?= e($selectedProduct['title']) ?>
            </h2>
            <span style="font-size: 13px; color: #64748b;">
              SKU: <code><?= e($selectedProduct['sku']) ?></code> &bull; Brand: <strong><?= e($selectedProduct['brand_name'] ?? 'iboltnetworks') ?></strong> &bull; Total Images: <strong><?= count($productImages) ?></strong>
            </span>
          </div>
          <span class="badge badge-green" style="font-size: 13px; padding: 6px 12px;">
            <?= format_inr((int)$selectedProduct['price_paise']) ?>
          </span>
        </div>

        <!-- Multi-File Drag & Drop Uploader -->
        <form id="uploadForm" method="post" action="index.php?tab=gallery&product_id=<?= (int)$selectedProduct['id'] ?>" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="upload_images">
          <input type="hidden" name="product_id" value="<?= (int)$selectedProduct['id'] ?>">

          <div id="dropzone" class="dropzone-container" onclick="document.getElementById('fileUploadInput').click()">
            <div style="font-size: 34px; margin-bottom: 8px;">📤</div>
            <h3 style="margin: 0 0 6px; font-size: 16px;">Upload Product Images</h3>
            <p style="color: #64748b; font-size: 13px; margin: 0 0 16px;">
              Drag &amp; drop image files here, or click to browse. Permitted formats: JPEG, PNG, WebP, GIF (Max 8MB each).
            </p>
            <input type="file" name="images[]" id="fileUploadInput" multiple accept="image/jpeg,image/png,image/webp,image/gif" style="display: none;" onchange="handleFileSelect(this.files)">
            <button type="button" class="btn btn-secondary" onclick="event.stopPropagation(); document.getElementById('fileUploadInput').click();">Browse Files</button>
          </div>

          <div id="uploadPreviewSection" style="display: none; margin-bottom: 24px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
              <strong style="font-size: 14px; color: #0f172a;">Selected Images Queue (<span id="queueCount">0</span>)</strong>
              <button type="button" class="btn btn-secondary btn-sm" onclick="clearUploadQueue()">Clear Queue</button>
            </div>
            <div id="uploadPreviewGrid" style="display: flex; gap: 12px; flex-wrap: wrap;"></div>
            <div style="margin-top: 16px; text-align: right;">
              <button type="submit" class="btn btn-primary" style="padding: 10px 24px;">Upload All Queued Images</button>
            </div>
          </div>
        </form>

        <!-- Current Images Gallery -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
          <div>
            <h3 style="margin: 0;">Gallery Photos (<?= count($productImages) ?>)</h3>
            <p style="font-size: 13px; color: #64748b; margin: 4px 0 0;">
              Drag and drop cards or use the ◀ / ▶ arrows to adjust display order. The primary image is marked with a star.
            </p>
          </div>
        </div>

        <?php if (empty($productImages)): ?>
          <div style="text-align: center; color: #94a3b8; padding: 40px; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1;">
            <p style="margin: 0 0 10px; font-size: 15px;">No images uploaded for this product yet.</p>
            <p style="font-size: 13px; margin: 0;">Use the uploader above to add front, angle, rear, or packaging photos.</p>
          </div>
        <?php else: ?>
          <form id="galleryOrderForm" method="post" action="index.php?tab=gallery&product_id=<?= (int)$selectedProduct['id'] ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_order">
            <input type="hidden" name="product_id" value="<?= (int)$selectedProduct['id'] ?>">

            <!-- Batch Selection Controls -->
            <div class="batch-actions-bar">
              <div style="display: flex; align-items: center; gap: 10px;">
                <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 700; cursor: pointer; margin: 0;">
                  <input type="checkbox" id="selectAllImages" onchange="toggleSelectAll(this.checked)"> Select All
                </label>
                <span id="selectedCountBadge" style="font-size: 12px; color: #64748b;">(0 selected)</span>
              </div>
              <div style="display: flex; gap: 8px;">
                <button type="button" id="deleteSelectedBtn" class="btn btn-danger btn-sm" style="display: none;" onclick="openBatchDeleteModal()">
                  🗑️ Delete Selected
                </button>
                <button type="submit" class="btn btn-primary btn-sm">
                  💾 Save Display Order
                </button>
              </div>
            </div>

            <!-- Draggable & Keyboard Accessible Image Grid -->
            <div id="sortableImageGrid" class="image-grid">
              <?php foreach ($productImages as $idx => $img): ?>
                <?php
                  $isPrimary = ((int)$img['is_primary'] === 1);
                  $displayThumb = get_product_thumbnail_path($img['image_path']);
                ?>
                <div class="image-card <?= $isPrimary ? 'is-primary' : '' ?>"
                     draggable="true"
                     data-image-id="<?= (int)$img['id'] ?>"
                     data-index="<?= $idx ?>"
                     aria-label="Image <?= $idx + 1 ?> for <?= e($selectedProduct['title']) ?>">

                  <!-- Checkbox & Badge Bar -->
                  <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; margin-bottom: 6px;">
                    <input type="checkbox" class="img-select-checkbox" name="selected_images[]" value="<?= (int)$img['id'] ?>" onchange="updateSelectedCount()">
                    <?php if ($isPrimary): ?>
                      <span class="badge badge-green" style="font-size: 11px;">★ Primary</span>
                    <?php else: ?>
                      <span class="badge badge-gray" style="font-size: 11px;">Gallery</span>
                    <?php endif; ?>
                  </div>

                  <img src="<?= e($adminRoot . $displayThumb) ?>" alt="<?= e($img['alt_text'] ?? 'Product Image') ?>" loading="lazy">

                  <!-- Keyboard & Touch Accessible Reorder Controls -->
                  <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; margin-bottom: 8px;">
                    <button type="button" class="btn btn-secondary btn-sm" style="min-width: 38px; min-height: 38px; justify-content: center; font-size: 13px;" title="Move earlier in order" onclick="moveCardEarlier(this)">◀</button>
                    <div style="display: flex; align-items: center; gap: 4px; font-size: 12px; color: #475569;">
                      <span>#</span>
                      <input type="number" class="order-input" name="orders[<?= (int)$img['id'] ?>]" value="<?= (int)$img['sort_order'] ?>" style="width: 44px; min-height: 34px; padding: 2px 4px; font-size: 13px; text-align: center; border: 1px solid #cbd5e1; border-radius: 4px;">
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" style="min-width: 38px; min-height: 38px; justify-content: center; font-size: 13px;" title="Move later in order" onclick="moveCardLater(this)">▶</button>
                  </div>

                  <!-- Actions -->
                  <div class="img-actions" style="display: flex; gap: 6px; width: 100%; flex-wrap: wrap; justify-content: center;">
                    <?php if (!$isPrimary): ?>
                      <button type="submit" formaction="index.php?tab=gallery&product_id=<?= (int)$selectedProduct['id'] ?>" name="action" value="set_primary" onclick="setHiddenField('target_image_id', '<?= (int)$img['id'] ?>')" class="btn btn-secondary btn-sm" title="Set as primary product photo" style="font-size: 11.5px; padding: 6px 10px; min-height: 36px; flex: 1;">
                        ★ Primary
                      </button>
                    <?php endif; ?>

                    <button type="button" class="btn btn-secondary btn-sm" onclick="openReplaceModal(<?= (int)$img['id'] ?>, '<?= e($adminRoot . $img['image_path']) ?>')" style="font-size: 11.5px; padding: 6px 10px; min-height: 36px; flex: 1;" title="Replace with new file">
                      🔄 Replace
                    </button>

                    <button type="submit" formaction="index.php?tab=gallery&product_id=<?= (int)$selectedProduct['id'] ?>" name="action" value="delete_image" onclick="setHiddenField('target_image_id', '<?= (int)$img['id'] ?>'); return confirm('Are you sure you want to delete this image?');" class="btn btn-danger btn-sm" style="font-size: 12px; min-width: 36px; min-height: 36px; justify-content: center;" title="Delete image">
                      ✕
                    </button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <input type="hidden" id="target_image_id" name="image_id" value="">
          </form>
        <?php endif; ?>

      </div>
    <?php else: ?>
      <!-- If no product selected in gallery tab, prompt to pick one -->
      <div class="admin-card" style="text-align: center; padding: 40px;">
        <span style="font-size: 40px; display: block; margin-bottom: 12px;">🔍</span>
        <h3 style="margin: 0 0 8px;">Please Select a Product</h3>
        <p style="color: #64748b; margin: 0 0 16px;">Choose a server, workstation, or component from the dropdown above to manage its photos.</p>
        <a href="index.php?tab=catalog" class="btn btn-primary">Or Browse Recent Catalog Images →</a>
      </div>
    <?php endif; ?>

  <!-- TAB 2: RECENT CATALOG OVERVIEW -->
  <?php elseif ($activeTab === 'catalog'): ?>
    <div class="admin-card">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
        <div>
          <h2 style="margin: 0;">Recent Catalog Images</h2>
          <span style="font-size: 13px; color: #64748b;">Showing latest uploaded hardware media across all products</span>
        </div>
        <a href="index.php?tab=gallery" class="btn btn-secondary">Select Product Gallery →</a>
      </div>

      <div class="image-grid">
        <?php foreach ($recentImages as $rImg): ?>
          <?php $rThumb = get_product_thumbnail_path($rImg['image_path']); ?>
          <div class="image-card <?= (int)$rImg['is_primary'] === 1 ? 'is-primary' : '' ?>" style="text-align: center;">
            <div style="position: absolute; top: 8px; left: 8px;">
              <?php if ((int)$rImg['is_primary'] === 1): ?>
                <span class="badge badge-green" style="font-size: 10px;">★ Primary</span>
              <?php endif; ?>
            </div>
            <img src="<?= e($adminRoot . $rThumb) ?>" alt="" loading="lazy">
            <div style="font-weight: 700; font-size: 12px; margin-bottom: 2px; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= e($rImg['product_title']) ?>">
              <?= e($rImg['product_title']) ?>
            </div>
            <code style="font-size: 10px; color: #64748b;"><?= e($rImg['product_sku']) ?></code>
            <div style="margin-top: 8px;">
              <a href="index.php?tab=gallery&product_id=<?= (int)$rImg['product_id'] ?>" class="btn btn-secondary btn-sm" style="font-size: 11px; padding: 3px 8px;">
                Manage Gallery →
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

  <!-- TAB 3: HEALTH & INTEGRITY AUDIT -->
  <?php elseif ($activeTab === 'audit' && $auditData): ?>
    <div class="admin-card">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; border-bottom: 1px solid #f1f5f9; padding-bottom: 16px;">
        <div>
          <h2 style="margin: 0; display: flex; align-items: center; gap: 8px;">
            <span>🩺 Image Library Health &amp; Integrity Audit</span>
            <?php if ($auditData['is_healthy']): ?>
              <span class="badge badge-green">Healthy</span>
            <?php else: ?>
              <span class="badge badge-danger">Attention Needed</span>
            <?php endif; ?>
          </h2>
          <p style="margin: 4px 0 0; color: #64748b; font-size: 13px;">
            Read-only scan of database references vs. filesystem storage in <code>uploads/products/</code>.
          </p>
        </div>
        <a href="index.php?tab=audit" class="btn btn-secondary btn-sm">🔄 Re-scan Library</a>
      </div>

      <!-- Health Metrics Bar -->
      <div style="display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap;">
        <div class="audit-metric-card">
          <div style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase;">Total DB Records</div>
          <div class="audit-metric-number" style="color: #0f172a;"><?= (int)$auditData['total_db_records'] ?></div>
        </div>

        <div class="audit-metric-card" style="<?= $auditData['missing_files_count'] > 0 ? 'border-color: #ef4444; background: #fef2f2;' : '' ?>">
          <div style="font-size: 12px; font-weight: 700; color: <?= $auditData['missing_files_count'] > 0 ? '#991b1b' : '#64748b' ?>; text-transform: uppercase;">Missing Files on Disk</div>
          <div class="audit-metric-number" style="color: <?= $auditData['missing_files_count'] > 0 ? '#dc2626' : '#16a34a' ?>;">
            <?= (int)$auditData['missing_files_count'] ?>
          </div>
        </div>

        <div class="audit-metric-card" style="<?= $auditData['orphaned_files_count'] > 0 ? 'border-color: #f59e0b; background: #fffbeb;' : '' ?>">
          <div style="font-size: 12px; font-weight: 700; color: <?= $auditData['orphaned_files_count'] > 0 ? '#92400e' : '#64748b' ?>; text-transform: uppercase;">Orphaned Uploads</div>
          <div class="audit-metric-number" style="color: <?= $auditData['orphaned_files_count'] > 0 ? '#d97706' : '#16a34a' ?>;">
            <?= (int)$auditData['orphaned_files_count'] ?>
          </div>
        </div>

        <div class="audit-metric-card">
          <div style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase;">Products Without Images</div>
          <div class="audit-metric-number" style="color: <?= $auditData['products_without_images_count'] > 0 ? '#2563eb' : '#16a34a' ?>;">
            <?= (int)$auditData['products_without_images_count'] ?>
          </div>
        </div>

        <div class="audit-metric-card">
          <div style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase;">Shared / Stock Images</div>
          <div class="audit-metric-number" style="color: #64748b;"><?= (int)$auditData['shared_images_count'] ?></div>
        </div>
      </div>

      <!-- Section: Orphaned Uploaded Files (Review & Explicit Cleanup) -->
      <div style="margin-bottom: 30px;">
        <h3 style="margin-bottom: 6px;">Orphaned Files in uploads/products/ (<?= (int)$auditData['orphaned_files_count'] ?>)</h3>
        <p style="font-size: 13px; color: #64748b; margin: 0 0 12px;">
          These physical files exist in the upload directory but have no references in the database. They can occur from interrupted creations or testing. Files are never deleted automatically.
        </p>

        <?php if (empty($auditData['orphaned_files'])): ?>
          <p style="color: #16a34a; font-size: 13px; background: #f0fdf4; padding: 12px; border-radius: 6px; border: 1px solid #dcfce7;">
            ✓ Clean filesystem: Zero orphaned image files detected in uploads/products/.
          </p>
        <?php else: ?>
          <form method="post" action="index.php?tab=audit" onsubmit="return confirm('CAUTION: Are you sure you want to permanently delete the selected orphaned files from disk? This cannot be undone.');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="cleanup_orphaned">

            <div style="overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 12px;">
              <table class="admin-table" style="margin: 0;">
                <thead>
                  <tr>
                    <th style="width: 40px; text-align: center;">
                      <input type="checkbox" onchange="document.querySelectorAll('.orphan-chk').forEach(c => c.checked = this.checked)">
                    </th>
                    <th>File Path</th>
                    <th>Size</th>
                    <th>Last Modified</th>
                    <th>Preview</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($auditData['orphaned_files'] as $of): ?>
                    <tr>
                      <td style="text-align: center;">
                        <input type="checkbox" class="orphan-chk" name="orphaned_paths[]" value="<?= e($of['relative_path']) ?>">
                      </td>
                      <td><code><?= e($of['relative_path']) ?></code></td>
                      <td><?= e($of['size_formatted']) ?></td>
                      <td><?= e($of['modified']) ?></td>
                      <td>
                        <img src="<?= e($adminRoot . $of['relative_path']) ?>" alt="" style="width: 40px; height: 35px; object-fit: contain; border: 1px solid #e2e8f0; border-radius: 4px;">
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <div style="display: flex; justify-content: flex-end;">
              <button type="submit" class="btn btn-danger">
                🗑️ Clean Up Selected Orphaned Files
              </button>
            </div>
          </form>
        <?php endif; ?>
      </div>

      <!-- Section: Missing Files on Disk (DB Broken References) -->
      <?php if (!empty($auditData['missing_files'])): ?>
        <div style="margin-bottom: 30px;">
          <h3 style="color: #dc2626; margin-bottom: 6px;">Missing Files on Disk (<?= count($auditData['missing_files']) ?>)</h3>
          <p style="font-size: 13px; color: #64748b; margin: 0 0 12px;">
            The following database records point to file paths that do not exist on the filesystem.
          </p>
          <div style="overflow-x: auto; border: 1px solid #fee2e2; border-radius: 8px;">
            <table class="admin-table" style="margin: 0;">
              <thead>
                <tr style="background: #fef2f2;">
                  <th>Record ID</th>
                  <th>Product</th>
                  <th>SKU</th>
                  <th>Expected Path</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($auditData['missing_files'] as $mf): ?>
                  <tr>
                    <td>#<?= (int)$mf['id'] ?></td>
                    <td><?= e($mf['product_title']) ?></td>
                    <td><code><?= e($mf['product_sku']) ?></code></td>
                    <td><code style="color: #dc2626;"><?= e($mf['image_path']) ?></code></td>
                    <td>
                      <a href="index.php?tab=gallery&product_id=<?= (int)$mf['product_id'] ?>" class="btn btn-secondary btn-sm">Fix in Gallery</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

      <!-- Section: Shared / Duplicate Files Across Records -->
      <div>
        <h3 style="margin-bottom: 6px;">Shared Image Paths Across Catalog (<?= count($auditData['shared_images']) ?>)</h3>
        <p style="font-size: 13px; color: #64748b; margin: 0 0 12px;">
          Multiple products sharing identical stock or placeholder files. The system strictly guards these files against deletion when one product's image is removed.
        </p>
        <div style="overflow-x: auto; border: 1px solid #e2e8f0; border-radius: 8px;">
          <table class="admin-table" style="margin: 0;">
            <thead>
              <tr>
                <th>Image Path</th>
                <th>Product Usage Count</th>
                <th>Referenced Product IDs</th>
                <th>Preview</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (array_slice($auditData['shared_images'], 0, 15) as $si): ?>
                <tr>
                  <td><code><?= e($si['image_path']) ?></code></td>
                  <td><span class="badge badge-gray"><?= (int)$si['usage_count'] ?> products</span></td>
                  <td><small style="color: #64748b;"><?= e($si['product_ids']) ?></small></td>
                  <td>
                    <img src="<?= e($adminRoot . $si['image_path']) ?>" alt="" style="width: 40px; height: 35px; object-fit: contain; border: 1px solid #e2e8f0; border-radius: 4px;">
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  <?php endif; ?>

</div>

<!-- Modal: Replace Image -->
<div id="replaceModal" class="admin-modal">
  <div class="admin-modal-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
      <h2 style="margin: 0; font-size: 18px; font-weight: 800;">Replace Product Image</h2>
      <button type="button" onclick="closeReplaceModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #64748b;">✕</button>
    </div>

    <form method="post" action="index.php?tab=gallery&product_id=<?= (int)$selectedProductId ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="replace_image">
      <input type="hidden" name="product_id" value="<?= (int)$selectedProductId ?>">
      <input type="hidden" id="replace_image_id" name="image_id" value="">

      <div style="text-align: center; margin-bottom: 18px;">
        <p style="font-size: 13px; color: #64748b;">Current image:</p>
        <img id="currentImageThumb" src="" alt="" style="width: 140px; height: 120px; object-fit: contain; border: 1px solid #cbd5e1; border-radius: 8px; padding: 4px; background: #fff;">
      </div>

      <div class="form-group">
        <label for="replacement_file">Select New Image File *</label>
        <input type="file" id="replacement_file" name="replacement_file" required accept="image/jpeg,image/png,image/webp,image/gif" class="form-control">
        <small style="color: #64748b; display: block; margin-top: 4px;">Permitted: JPG, PNG, WebP, GIF (Max 8MB)</small>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
        <button type="button" class="btn btn-secondary" onclick="closeReplaceModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Replace Image File</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Batch Delete Confirmation -->
<div id="batchDeleteModal" class="admin-modal">
  <div class="admin-modal-content" style="max-width: 440px;">
    <h2 style="margin: 0 0 12px; font-size: 18px; color: #dc2626;">Confirm Batch Deletion</h2>
    <p style="font-size: 14px; color: #475569; margin: 0 0 20px;">
      Are you sure you want to permanently delete the <strong id="modalSelectedCount">0</strong> selected image(s)?
      If the primary image is included, another image will be promoted automatically.
    </p>
    <div style="display: flex; justify-content: flex-end; gap: 10px;">
      <button type="button" class="btn btn-secondary" onclick="closeBatchDeleteModal()">Cancel</button>
      <button type="button" class="btn btn-danger" onclick="executeBatchDelete()">Yes, Delete Selected</button>
    </div>
  </div>
</div>

<script>
  function setHiddenField(id, val) {
    document.getElementById(id).value = val;
  }

  function openReplaceModal(imageId, currentSrc) {
    document.getElementById('replace_image_id').value = imageId;
    document.getElementById('currentImageThumb').src = currentSrc;
    document.getElementById('replaceModal').classList.add('is-open');
  }

  function closeReplaceModal() {
    document.getElementById('replaceModal').classList.remove('is-open');
  }

  // Multi-file drag and drop uploader
  const dropzone = document.getElementById('dropzone');
  const fileInput = document.getElementById('fileUploadInput');
  let queuedFiles = [];

  if (dropzone) {
    ['dragenter', 'dragover'].forEach(eventName => {
      dropzone.addEventListener(eventName, (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropzone.classList.add('is-dragover');
      }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
      dropzone.addEventListener(eventName, (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropzone.classList.remove('is-dragover');
      }, false);
    });

    dropzone.addEventListener('drop', (e) => {
      const dt = e.dataTransfer;
      if (dt.files && dt.files.length > 0) {
        handleFileSelect(dt.files);
      }
    }, false);
  }

  function handleFileSelect(files) {
    Array.from(files).forEach(file => {
      queuedFiles.push(file);
    });
    renderUploadPreviews();
  }

  function renderUploadPreviews() {
    const previewGrid = document.getElementById('uploadPreviewGrid');
    const previewSection = document.getElementById('uploadPreviewSection');
    const queueCount = document.getElementById('queueCount');

    if (!previewGrid || !previewSection) return;

    if (queuedFiles.length === 0) {
      previewSection.style.display = 'none';
      return;
    }

    previewSection.style.display = 'block';
    queueCount.textContent = queuedFiles.length;
    previewGrid.innerHTML = '';

    queuedFiles.forEach((file, index) => {
      const card = document.createElement('div');
      card.className = 'upload-preview-card';

      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'remove-btn';
      removeBtn.textContent = '✕';
      removeBtn.onclick = () => {
        queuedFiles.splice(index, 1);
        renderUploadPreviews();
      };

      const img = document.createElement('img');
      const reader = new FileReader();
      reader.onload = (e) => { img.src = e.target.result; };
      reader.readAsDataURL(file);

      const nameSpan = document.createElement('span');
      nameSpan.className = 'filename';
      nameSpan.title = file.name;
      nameSpan.textContent = file.name;

      card.appendChild(removeBtn);
      card.appendChild(img);
      card.appendChild(nameSpan);
      previewGrid.appendChild(card);
    });

    // Update actual file input with DataTransfer
    const dt = new DataTransfer();
    queuedFiles.forEach(f => dt.items.add(f));
    fileInput.files = dt.files;
  }

  function clearUploadQueue() {
    queuedFiles = [];
    if (fileInput) fileInput.value = '';
    renderUploadPreviews();
  }

  // Batch Selection
  function toggleSelectAll(checked) {
    document.querySelectorAll('.img-select-checkbox').forEach(cb => {
      cb.checked = checked;
    });
    updateSelectedCount();
  }

  function updateSelectedCount() {
    const checked = document.querySelectorAll('.img-select-checkbox:checked');
    const count = checked.length;
    const badge = document.getElementById('selectedCountBadge');
    const delBtn = document.getElementById('deleteSelectedBtn');

    if (badge) badge.textContent = `(${count} selected)`;
    if (delBtn) {
      delBtn.style.display = count > 0 ? 'inline-block' : 'none';
      delBtn.textContent = `🗑️ Delete Selected (${count})`;
    }
  }

  function openBatchDeleteModal() {
    const checked = document.querySelectorAll('.img-select-checkbox:checked');
    if (checked.length === 0) return;
    document.getElementById('modalSelectedCount').textContent = checked.length;
    document.getElementById('batchDeleteModal').classList.add('is-open');
  }

  function closeBatchDeleteModal() {
    document.getElementById('batchDeleteModal').classList.remove('is-open');
  }

  function executeBatchDelete() {
    const form = document.getElementById('galleryOrderForm');
    const actionInput = form.querySelector('input[name="action"]');
    actionInput.value = 'delete_selected';
    closeBatchDeleteModal();
    form.submit();
  }

  // Drag and Drop & Keyboard Accessible Reordering
  const grid = document.getElementById('sortableImageGrid');
  let draggedCard = null;

  if (grid) {
    grid.addEventListener('dragstart', (e) => {
      const card = e.target.closest('.image-card');
      if (!card) return;
      draggedCard = card;
      card.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', card.dataset.index);
    });

    grid.addEventListener('dragend', (e) => {
      const card = e.target.closest('.image-card');
      if (card) card.classList.remove('dragging');
      document.querySelectorAll('.image-card').forEach(c => c.classList.remove('drag-over'));
      draggedCard = null;
      renumberOrders();
    });

    grid.addEventListener('dragover', (e) => {
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
      const targetCard = e.target.closest('.image-card');
      if (targetCard && targetCard !== draggedCard) {
        document.querySelectorAll('.image-card').forEach(c => c.classList.remove('drag-over'));
        targetCard.classList.add('drag-over');
      }
    });

    grid.addEventListener('drop', (e) => {
      e.preventDefault();
      const targetCard = e.target.closest('.image-card');
      if (targetCard && draggedCard && targetCard !== draggedCard) {
        const cards = Array.from(grid.querySelectorAll('.image-card'));
        const draggedIndex = cards.indexOf(draggedCard);
        const targetIndex = cards.indexOf(targetCard);

        if (draggedIndex < targetIndex) {
          grid.insertBefore(draggedCard, targetCard.nextSibling);
        } else {
          grid.insertBefore(draggedCard, targetCard);
        }
        renumberOrders();
      }
      document.querySelectorAll('.image-card').forEach(c => c.classList.remove('drag-over'));
    });
  }

  function moveCardEarlier(btn) {
    const card = btn.closest('.image-card');
    const prev = card.previousElementSibling;
    if (prev && prev.classList.contains('image-card')) {
      card.parentNode.insertBefore(card, prev);
      renumberOrders();
    }
  }

  function moveCardLater(btn) {
    const card = btn.closest('.image-card');
    const next = card.nextElementSibling;
    if (next && next.classList.contains('image-card')) {
      card.parentNode.insertBefore(next, card);
      renumberOrders();
    }
  }

  function renumberOrders() {
    const cards = document.querySelectorAll('#sortableImageGrid .image-card');
    cards.forEach((card, index) => {
      const orderInput = card.querySelector('.order-input');
      if (orderInput) {
        orderInput.value = (index + 1) * 10;
      }
      card.dataset.index = index;
    });
  }
</script>

<?php
require_once __DIR__ . '/../includes/admin-footer.php';
