<?php
declare(strict_types=1);

/**
 * Admin Homepage Merchandising Live Preview
 * Interactive visual simulation of public homepage merchandising,
 * section ordering, promotional badges, and draft/inactive states.
 * iboltnetworks E-Commerce Platform
 */

$adminRoot = '../';
$adminPageTitle = 'Homepage Merchandising Live Preview | iboltnetworks Admin';

require_once __DIR__ . '/../includes/admin-header.php';
require_once __DIR__ . '/../../app/helpers/homepage.php';
require_once __DIR__ . '/../../app/helpers/uploader.php';

$db = Database::getConnection();

$previewMode = clean_string($_GET['mode'] ?? 'merchandising');
if (!in_array($previewMode, ['merchandising', 'visitor'], true)) {
    $previewMode = 'merchandising';
}

$sectionFilter = clean_string($_GET['section'] ?? '');

// Fetch sections
$allSections = get_homepage_sections(false);
if (!empty($sectionFilter)) {
    $sectionsToRender = array_filter($allSections, fn($s) => $s['section_key'] === $sectionFilter);
} else {
    $sectionsToRender = $allSections;
}

?>

<style>
  .preview-toolbar {
    background: #0f172a;
    color: #ffffff;
    border-radius: 12px;
    padding: 16px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 14px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  }
  .preview-mode-pill {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.15s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
  .preview-mode-pill.active {
    background: #078e49;
    color: #ffffff;
  }
  .preview-mode-pill.inactive {
    background: #1e293b;
    color: #94a3b8;
  }
  .preview-mode-pill.inactive:hover {
    background: #334155;
    color: #ffffff;
  }

  .preview-section-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 32px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
  }
  .preview-section-card.is-inactive {
    border-color: #f59e0b;
    background: #fffdfa;
  }

  .preview-product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 20px;
    margin-top: 20px;
  }

  .preview-product-card {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 14px;
    background: #ffffff;
    display: flex;
    flex-direction: column;
    position: relative;
    transition: transform 0.15s, box-shadow 0.15s;
  }
  .preview-product-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.06);
  }
  .preview-product-card.is-draft {
    border-color: #f59e0b;
    background: #fffbeb;
  }
  .preview-product-card.is-hidden {
    opacity: 0.65;
    border-style: dashed;
  }

  .card-img-wrap {
    height: 150px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8fafc;
    border-radius: 8px;
    margin-bottom: 12px;
    padding: 10px;
  }
  .card-img-wrap img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
  }
</style>

<div class="admin-container">

  <!-- Interactive Preview Toolbar -->
  <div class="preview-toolbar">
    <div>
      <div style="display: flex; align-items: center; gap: 8px;">
        <span style="font-size: 20px;">👁️</span>
        <strong style="font-size: 16px;">Storefront Merchandising Live Preview</strong>
      </div>
      <p style="margin: 4px 0 0; color: #94a3b8; font-size: 13px;">
        Visual preview of homepage sections, product ordering, promotional badges, and draft warnings.
      </p>
    </div>

    <!-- Mode Selector & Controls -->
    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
      <span style="font-size: 12.5px; color: #94a3b8;">View Mode:</span>
      <a href="preview.php?mode=merchandising<?= !empty($sectionFilter) ? '&section=' . urlencode($sectionFilter) : '' ?>" class="preview-mode-pill <?= $previewMode === 'merchandising' ? 'active' : 'inactive' ?>">
        🛠️ Merchandising View
      </a>
      <a href="preview.php?mode=visitor<?= !empty($sectionFilter) ? '&section=' . urlencode($sectionFilter) : '' ?>" class="preview-mode-pill <?= $previewMode === 'visitor' ? 'active' : 'inactive' ?>">
        🌐 Public Visitor View
      </a>

      <div style="height: 24px; width: 1px; background: #334155; margin: 0 4px;"></div>

      <a href="index.php" class="btn btn-secondary btn-sm" style="color: #ffffff; border-color: #475569; background: #1e293b;">
        ← Master Overview
      </a>
      <a href="../../index.php" target="_blank" class="btn btn-primary btn-sm">
        Open Live Storefront ↗
      </a>
    </div>
  </div>

  <!-- Section Quick Filters -->
  <div style="display: flex; gap: 8px; margin-bottom: 24px; flex-wrap: wrap;">
    <a href="preview.php?mode=<?= e($previewMode) ?>" class="btn <?= empty($sectionFilter) ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
      All Homepage Sections (<?= count($allSections) ?>)
    </a>
    <?php foreach ($allSections as $sec): ?>
      <a href="preview.php?mode=<?= e($previewMode) ?>&section=<?= e($sec['section_key']) ?>" class="btn <?= $sectionFilter === $sec['section_key'] ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
        <?= get_section_icon($sec['section_key']) ?> <?= e($sec['name']) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Sections Render Loop -->
  <?php foreach ($sectionsToRender as $sec): ?>
    <?php
      $isActive = ((int)$sec['is_active'] === 1);
      // In visitor view, skip inactive sections entirely
      if ($previewMode === 'visitor' && !$isActive) {
          continue;
      }

      $products = get_homepage_section_products($sec['section_key'], 24, ($previewMode === 'merchandising'));
    ?>

    <div class="preview-section-card <?= !$isActive ? 'is-inactive' : '' ?>">

      <!-- Section Header -->
      <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; flex-wrap: wrap; gap: 10px; border-bottom: 1px solid #f1f5f9; padding-bottom: 14px;">
        <div>
          <div style="display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 22px;"><?= get_section_icon($sec['section_key']) ?></span>
            <h2 style="margin: 0; font-size: 20px; color: #0f172a;">
              <?= e($sec['display_title']) ?>
            </h2>
            <?php if ($isActive): ?>
              <span class="badge badge-green">Live Active Section</span>
            <?php else: ?>
              <span class="badge badge-danger">Hidden / Inactive Section</span>
            <?php endif; ?>
          </div>
          <?php if (!empty($sec['subtitle'])): ?>
            <p style="margin: 4px 0 0; color: #64748b; font-size: 13.5px;"><?= e($sec['subtitle']) ?></p>
          <?php endif; ?>
        </div>

        <div style="display: flex; gap: 8px;">
          <a href="../sections/<?= e($sec['section_key'] === 'featured' ? 'promotions' : $sec['section_key']) ?>.php" class="btn btn-secondary btn-sm">
            ⚙ Manage Section Products
          </a>
        </div>
      </div>

      <!-- Inactive Section Warning Banner (Merchandising Mode Only) -->
      <?php if (!$isActive && $previewMode === 'merchandising'): ?>
        <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; display: flex; align-items: center; gap: 10px;">
          <span style="font-size: 20px;">⚠️</span>
          <div>
            <strong style="color: #92400e; font-size: 13.5px;">Section Inactive (is_active = 0)</strong>
            <p style="margin: 2px 0 0; color: #b45309; font-size: 12.5px;">
              This entire section is currently hidden from public storefront visitors. In Visitor View, this section does not render.
            </p>
          </div>
        </div>
      <?php endif; ?>

      <!-- Section Products Grid -->
      <?php if (empty($products)): ?>
        <div style="text-align: center; padding: 40px; color: #94a3b8; background: #f8fafc; border-radius: 8px;">
          <p style="margin: 0;">No products currently assigned to this section.</p>
        </div>
      <?php else: ?>
        <div class="preview-product-grid">
          <?php foreach ($products as $idx => $p): ?>
            <?php
              $isPublished = ($p['status'] === 'published');
              $isVisible = ((int)($p['section_visible'] ?? 1) === 1);
              $isOutOfStock = ((int)$p['stock_quantity'] <= 0);
              $thumb = get_product_thumbnail_path($p['primary_image'] ?? 'images/server-hud.png');
            ?>

            <div class="preview-product-card <?= !$isPublished ? 'is-draft' : '' ?> <?= !$isVisible ? 'is-hidden' : '' ?>">

              <!-- Top Badges Bar -->
              <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; gap: 4px;">
                <div>
                  <?php if (!empty($p['badge_text'])): ?>
                    <span class="badge badge-purple" style="font-size: 11px;">
                      <?= e($p['badge_text']) ?>
                    </span>
                  <?php endif; ?>
                </div>
                <div style="display: flex; gap: 4px; flex-wrap: wrap; justify-content: flex-end;">
                  <?php if ($previewMode === 'merchandising'): ?>
                    <span class="badge badge-gray" style="font-size: 10px;" title="Display Order">
                      #<?= (int)($p['section_sort_order'] ?? ($idx + 1)) ?>
                    </span>
                    <?php if (!$isPublished): ?>
                      <span class="badge badge-danger" style="font-size: 10px;">Draft / Archived</span>
                    <?php endif; ?>
                    <?php if (!$isVisible): ?>
                      <span class="badge badge-gray" style="font-size: 10px;">Hidden</span>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Product Image -->
              <div class="card-img-wrap">
                <img src="<?= e($adminRoot . $thumb) ?>" alt="<?= e($p['title']) ?>" loading="lazy">
              </div>

              <!-- Product Brand & Title -->
              <span style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: #64748b;">
                <?= e($p['brand_name'] ?? 'iboltnetworks') ?>
              </span>
              <a href="../products/form.php?id=<?= (int)$p['id'] ?>" style="font-weight: 700; font-size: 14px; color: #0f172a; text-decoration: none; margin: 4px 0 6px; line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;" title="<?= e($p['title']) ?>">
                <?= e($p['title']) ?>
              </a>

              <div style="font-size: 11.5px; color: #64748b; margin-bottom: 8px;">
                <code><?= e($p['sku']) ?></code> &bull; <?= e(ucfirst($p['condition_type'] ?? 'refurbished')) ?>
              </div>

              <!-- Price Box -->
              <div style="margin-top: auto; padding-top: 10px; border-top: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: baseline;">
                <div>
                  <span style="font-size: 16px; font-weight: 800; color: #078e49;">
                    <?= format_inr((int)$p['price_paise']) ?>
                  </span>
                  <?php if (!empty($p['compare_price_paise']) && (int)$p['compare_price_paise'] > (int)$p['price_paise']): ?>
                    <span style="font-size: 11.5px; text-decoration: line-through; color: #94a3b8; margin-left: 4px;">
                      <?= format_inr((int)$p['compare_price_paise']) ?>
                    </span>
                  <?php endif; ?>
                </div>

                <?php if ($isOutOfStock): ?>
                  <span style="font-size: 11px; color: #dc2626; font-weight: 700;">Out of Stock</span>
                <?php else: ?>
                  <span style="font-size: 11px; color: #16a34a; font-weight: 600;">● In Stock</span>
                <?php endif; ?>
              </div>

              <?php if ($previewMode === 'merchandising'): ?>
                <div style="display: flex; gap: 6px; margin-top: 10px; padding-top: 8px; border-top: 1px dashed #e2e8f0;">
                  <a href="../products/form.php?id=<?= (int)$p['id'] ?>" class="btn btn-secondary btn-sm" style="flex: 1; text-align: center; font-size: 11px; padding: 3px 6px;">
                    Edit Product
                  </a>
                  <a href="../sections/<?= e($sec['section_key'] === 'featured' ? 'promotions' : $sec['section_key']) ?>.php" class="btn btn-secondary btn-sm" style="flex: 1; text-align: center; font-size: 11px; padding: 3px 6px;">
                    Section Settings
                  </a>
                </div>
              <?php endif; ?>

            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    </div>
  <?php endforeach; ?>

</div>

<?php
require_once __DIR__ . '/../includes/admin-footer.php';
