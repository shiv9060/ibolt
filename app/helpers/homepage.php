<?php
declare(strict_types=1);

/**
 * Homepage Sections & Product Placement Helpers
 * Comprehensive merchandising administration, section ordering,
 * badge validation, activity logging, and live storefront preview.
 * iboltnetworks E-Commerce Platform
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/sanitize.php';

/**
 * Log administrative activities to admin_activity_logs safely.
 */
function log_homepage_activity(?int $adminId, string $action, string $entityType, ?int $entityId = null, array|string|null $details = null): void
{
    try {
        $db = Database::getConnection();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI', 0, 255);
        $detailsJson = is_array($details) ? json_encode($details) : (is_string($details) ? $details : null);

        $stmt = $db->prepare("INSERT INTO admin_activity_logs (admin_id, action, entity_type, entity_id, details, ip_address, user_agent)
                              VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$adminId, $action, $entityType, $entityId, $detailsJson, $ip, $ua]);
    } catch (Throwable $e) {
        // Silently prevent logging errors from breaking main transaction
    }
}

/**
 * Get all configured homepage sections.
 */
function get_homepage_sections(bool $onlyActive = true): array
{
    try {
        $db = Database::getConnection();
        $sql = "SELECT * FROM homepage_sections";
        if ($onlyActive) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY sort_order ASC, id ASC";
        return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log("[iboltnetworks] get_homepage_sections error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get a single homepage section by key.
 */
function get_homepage_section(string $sectionKey): ?array
{
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM homepage_sections WHERE section_key = ? LIMIT 1");
        $stmt->execute([$sectionKey]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    } catch (Throwable $e) {
        error_log("[iboltnetworks] get_homepage_section error for '{$sectionKey}': " . $e->getMessage());
        return null;
    }
}

/**
 * Update homepage section configuration.
 */
function update_homepage_section(string $sectionKey, array $data, ?int $adminId = null): bool
{
    $db = Database::getConnection();
    $existing = get_homepage_section($sectionKey);
    if (!$existing) {
        return false;
    }

    $title = clean_string($data['display_title'] ?? $existing['display_title']);
    $subtitle = clean_string($data['subtitle'] ?? $existing['subtitle']);
    $icon = clean_string($data['icon'] ?? $existing['icon']);
    $sortOrder = sanitize_int($data['sort_order'] ?? $existing['sort_order']);
    $isActive = sanitize_int($data['is_active'] ?? $existing['is_active']);

    $stmt = $db->prepare("UPDATE homepage_sections
                          SET display_title = ?, subtitle = ?, icon = ?, sort_order = ?, is_active = ?, updated_at = NOW()
                          WHERE section_key = ?");
    $res = $stmt->execute([$title, $subtitle, $icon, $sortOrder, $isActive, $sectionKey]);

    if ($res) {
        log_homepage_activity($adminId, 'section_update_config', 'homepage_section', (int)$existing['id'], [
            'section_key' => $sectionKey,
            'display_title' => $title,
            'sort_order' => $sortOrder,
            'is_active' => $isActive
        ]);
    }
    return $res;
}

/**
 * Validate and sanitize promotional badge text.
 * Strictly prevents discount claims unless compare_price > price.
 */
function validate_badge_text(?string $badgeText, ?int $pricePaise = null, ?int $comparePricePaise = null): string
{
    if ($badgeText === null) {
        return '';
    }

    // Strip script and style blocks completely
    $clean = preg_replace('#<script[^>]*>.*?</script>#is', '', $badgeText);
    $badge = trim(strip_tags((string)$clean));
    if (empty($badge)) {
        return '';
    }

    // Check discount claims like "Save 20%", "20% OFF", "Discount"
    $isDiscountClaim = (bool)preg_match('/(%|save|off|discount)/i', $badge);
    if ($isDiscountClaim) {
        $hasRealDiscount = ($pricePaise !== null && $comparePricePaise !== null && $comparePricePaise > $pricePaise);
        if (!$hasRealDiscount) {
            // Cannot claim discount without valid compare_price > price; fallback to 'Featured'
            return 'Featured';
        }
    }

    // Limit badge length
    return substr($badge, 0, 60);
}

/**
 * Fetch products assigned to a specific homepage section for public display.
 * When previewMode is true, includes draft/inactive items for authorized admin preview.
 */
function get_homepage_section_products(string $sectionKey, int $limit = 12, bool $previewMode = false): array
{
    try {
        $db = Database::getConnection();

        // Check if section itself is active (unless previewMode is requested)
        if (!$previewMode) {
            $secStmt = $db->prepare("SELECT is_active FROM homepage_sections WHERE section_key = ? LIMIT 1");
            $secStmt->execute([$sectionKey]);
            $secActive = $secStmt->fetchColumn();
            if ($secActive !== false && (int)$secActive === 0) {
                return [];
            }
        }

        $whereConditions = ["hsp.section_key = :section_key"];
        if (!$previewMode) {
            $whereConditions[] = "hsp.is_visible = 1";
            $whereConditions[] = "p.status = 'published'";
        }

        $whereSql = implode(' AND ', $whereConditions);

        $sql = "SELECT p.*,
                       b.name AS brand_name,
                       b.slug AS brand_slug,
                       c.name AS category_name,
                       c.slug AS category_slug,
                       c.section AS category_section,
                       hsp.badge_text,
                       hsp.sort_order,
                       hsp.sort_order AS section_sort_order,
                       hsp.is_featured,
                       hsp.is_featured AS section_featured,
                       hsp.is_visible,
                       hsp.is_visible AS section_visible,
                       COALESCE(
                           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1),
                           (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order ASC, id ASC LIMIT 1),
                           'images/server-hud.png'
                       ) AS primary_image
                FROM homepage_section_products hsp
                INNER JOIN products p ON hsp.product_id = p.id
                LEFT JOIN brands b ON p.brand_id = b.id
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE {$whereSql}
                ORDER BY hsp.sort_order ASC, p.id DESC
                LIMIT :limit";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':section_key', $sectionKey, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fallback: If section has no manually assigned products yet and is active, fallback to published products matching category section
        if (empty($results) && in_array($sectionKey, ['servers', 'networking', 'components', 'our-products'], true)) {
            if (!$previewMode) {
                $secCheck = $db->prepare("SELECT is_active FROM homepage_sections WHERE section_key = ? LIMIT 1");
                $secCheck->execute([$sectionKey]);
                if ((int)$secCheck->fetchColumn() === 0) {
                    return [];
                }
            }

            $fallbackSql = "SELECT p.*,
                                   b.name AS brand_name,
                                   b.slug AS brand_slug,
                                   c.name AS category_name,
                                   c.slug AS category_slug,
                                   c.section AS category_section,
                                   'Featured' AS badge_text,
                                   0 AS section_sort_order,
                                   p.is_featured AS section_featured,
                                   1 AS section_visible,
                                   COALESCE(
                                       (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1),
                                       (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order ASC, id ASC LIMIT 1),
                                       'images/server-hud.png'
                                   ) AS primary_image
                            FROM products p
                            LEFT JOIN categories c ON p.category_id = c.id
                            LEFT JOIN brands b ON p.brand_id = b.id
                            WHERE c.section = :sec AND p.status = 'published'
                            ORDER BY p.is_featured DESC, p.views_count DESC, p.id DESC
                            LIMIT :limit";
            $stmt = $db->prepare($fallbackSql);
            $stmt->bindValue(':sec', $sectionKey, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $results;
    } catch (Throwable $e) {
        error_log("[iboltnetworks] get_homepage_section_products error for section '{$sectionKey}': " . $e->getMessage());
        return [];
    }
}

/**
 * Assign a product to a homepage section with duplicate prevention,
 * badge validation, and activity logging.
 *
 * @return array ['success' => bool, 'error' => string]
 */
function assign_product_to_section(
    string $sectionKey,
    int $productId,
    int $sortOrder = 0,
    ?string $badgeText = null,
    int $isFeatured = 0,
    ?int $adminId = null
): array {
    $db = Database::getConnection();

    // Verify section exists
    $sec = get_homepage_section($sectionKey);
    if (!$sec) {
        return ['success' => false, 'error' => "Homepage section '{$sectionKey}' does not exist."];
    }

    // Verify product exists
    $pStmt = $db->prepare("SELECT id, title, price_paise, compare_price_paise, status FROM products WHERE id = ? LIMIT 1");
    $pStmt->execute([$productId]);
    $prod = $pStmt->fetch(PDO::FETCH_ASSOC);
    if (!$prod) {
        return ['success' => false, 'error' => "Product ID #{$productId} does not exist."];
    }

    // Validate badge text
    $validatedBadge = validate_badge_text(
        $badgeText,
        (int)$prod['price_paise'],
        $prod['compare_price_paise'] !== null ? (int)$prod['compare_price_paise'] : null
    );

    // Calculate sort order if 0
    if ($sortOrder === 0) {
        $maxStmt = $db->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM homepage_section_products WHERE section_key = ?");
        $maxStmt->execute([$sectionKey]);
        $sortOrder = (int)$maxStmt->fetchColumn() + 10;
    }

    $stmt = $db->prepare("INSERT INTO homepage_section_products (section_key, product_id, sort_order, badge_text, is_featured, is_visible)
                          VALUES (?, ?, ?, ?, ?, 1)
                          ON DUPLICATE KEY UPDATE
                            sort_order = VALUES(sort_order),
                            badge_text = VALUES(badge_text),
                            is_featured = VALUES(is_featured),
                            is_visible = 1,
                            updated_at = NOW()");
    $res = $stmt->execute([$sectionKey, $productId, $sortOrder, $validatedBadge, $isFeatured]);

    if ($res) {
        log_homepage_activity($adminId, 'section_assign_product', 'homepage_section_product', $productId, [
            'section_key' => $sectionKey,
            'product_id'  => $productId,
            'sort_order'  => $sortOrder,
            'badge_text'  => $validatedBadge
        ]);
        return ['success' => true, 'error' => ''];
    }

    return ['success' => false, 'error' => 'Failed to assign product to homepage section.'];
}

/**
 * Remove a product from a specific homepage section safely.
 */
function remove_product_from_section(string $sectionKey, int $productId, ?int $adminId = null): bool
{
    $db = Database::getConnection();
    $stmt = $db->prepare("DELETE FROM homepage_section_products WHERE section_key = ? AND product_id = ?");
    $res = $stmt->execute([$sectionKey, $productId]);
    if ($res) {
        log_homepage_activity($adminId, 'section_remove_product', 'homepage_section_product', $productId, [
            'section_key' => $sectionKey,
            'product_id'  => $productId
        ]);
    }
    return $res;
}

/**
 * Remove by primary key ID from homepage_section_products.
 */
function remove_section_item_by_id(int $hspId, ?int $adminId = null): bool
{
    $db = Database::getConnection();
    $infoStmt = $db->prepare("SELECT section_key, product_id FROM homepage_section_products WHERE id = ?");
    $infoStmt->execute([$hspId]);
    $info = $infoStmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("DELETE FROM homepage_section_products WHERE id = ?");
    $res = $stmt->execute([$hspId]);
    if ($res && $info) {
        log_homepage_activity($adminId, 'section_remove_product', 'homepage_section_product', (int)$info['product_id'], [
            'hsp_id'      => $hspId,
            'section_key' => $info['section_key'],
            'product_id'  => (int)$info['product_id']
        ]);
    }
    return $res;
}

/**
 * Update display ordering for products in a specific section.
 * Scoped strictly to section_key to ensure products in other sections are unaffected.
 */
function update_section_products_order(string $sectionKey, array $orderMap, ?int $adminId = null): bool
{
    $db = Database::getConnection();
    $manageTx = !$db->inTransaction();
    if ($manageTx) {
        $db->beginTransaction();
    }
    try {
        $stmt = $db->prepare("UPDATE homepage_section_products SET sort_order = ?, updated_at = NOW() WHERE product_id = ? AND section_key = ?");
        foreach ($orderMap as $productId => $order) {
            $stmt->execute([(int)$order, (int)$productId, $sectionKey]);
        }
        if ($manageTx) {
            $db->commit();
        }
        log_homepage_activity($adminId, 'section_reorder_products', 'homepage_section', null, [
            'section_key' => $sectionKey,
            'order_count' => count($orderMap)
        ]);
        return true;
    } catch (Throwable $e) {
        if ($manageTx && $db->inTransaction()) {
            $db->rollBack();
        }
        return false;
    }
}

/**
 * Toggle visibility of an assigned section product.
 */
function toggle_section_product_visibility(int $hspId, int $newVisible, ?int $adminId = null): bool
{
    $db = Database::getConnection();
    $stmt = $db->prepare("UPDATE homepage_section_products SET is_visible = ?, updated_at = NOW() WHERE id = ?");
    $res = $stmt->execute([$newVisible ? 1 : 0, $hspId]);
    if ($res) {
        log_homepage_activity($adminId, 'section_product_visibility', 'homepage_section_product', $hspId, [
            'is_visible' => $newVisible
        ]);
    }
    return $res;
}

/**
 * Fetch detailed section products for admin management.
 */
function get_admin_section_products(string $sectionKey, ?string $search = null, ?string $statusFilter = null): array
{
    $db = Database::getConnection();

    $where = ["hsp.section_key = :section_key"];
    $params = [':section_key' => $sectionKey];

    if (!empty($search)) {
        $where[] = "(p.title LIKE :s1 OR p.sku LIKE :s2 OR p.model LIKE :s3)";
        $like = '%' . $search . '%';
        $params[':s1'] = $like;
        $params[':s2'] = $like;
        $params[':s3'] = $like;
    }

    if (!empty($statusFilter)) {
        if ($statusFilter === 'visible') {
            $where[] = "hsp.is_visible = 1 AND p.status = 'published'";
        } elseif ($statusFilter === 'hidden') {
            $where[] = "(hsp.is_visible = 0 OR p.status != 'published')";
        } elseif ($statusFilter === 'out_of_stock') {
            $where[] = "p.stock_quantity <= 0";
        }
    }

    $whereSql = implode(' AND ', $where);

    $sql = "SELECT hsp.id AS hsp_id,
                   hsp.section_key,
                   hsp.product_id,
                   hsp.sort_order,
                   hsp.sort_order AS section_sort_order,
                   hsp.badge_text,
                   hsp.is_featured,
                   hsp.is_featured AS section_featured,
                   hsp.is_visible,
                   hsp.is_visible AS section_visible,
                   hsp.updated_at AS placement_updated_at,
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
                   COALESCE(
                       (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1),
                       (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY sort_order ASC, id ASC LIMIT 1),
                       'images/server-hud.png'
                   ) AS primary_image
            FROM homepage_section_products hsp
            INNER JOIN products p ON hsp.product_id = p.id
            LEFT JOIN brands b ON p.brand_id = b.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE {$whereSql}
            ORDER BY hsp.sort_order ASC, hsp.id ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get section keys assigned to a specific product.
 */
function get_product_homepage_sections(int $productId): array
{
    $db = Database::getConnection();
    $stmt = $db->prepare("SELECT section_key FROM homepage_section_products WHERE product_id = ?");
    $stmt->execute([$productId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Get full section assignment records for a product (includes badge_text, sort_order, is_visible).
 */
function get_product_section_assignments(int $productId): array
{
    $db = Database::getConnection();
    $stmt = $db->prepare("SELECT * FROM homepage_section_products WHERE product_id = ?");
    $stmt->execute([$productId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $map = [];
    foreach ($rows as $r) {
        $map[$r['section_key']] = $r;
    }
    return $map;
}

/**
 * Synchronize a product's homepage sections from an array of selected section keys.
 */
function sync_product_homepage_sections(int $productId, array $selectedSections, array $badges = [], array $orders = []): void
{
    $db = Database::getConnection();

    // Fetch existing assignments
    $stmt = $db->prepare("SELECT section_key FROM homepage_section_products WHERE product_id = ?");
    $stmt->execute([$productId]);
    $currentSections = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Delete unselected sections
    $toRemove = array_diff($currentSections, $selectedSections);
    if (!empty($toRemove)) {
        $inPlaceholders = implode(',', array_fill(0, count($toRemove), '?'));
        $delStmt = $db->prepare("DELETE FROM homepage_section_products WHERE product_id = ? AND section_key IN ({$inPlaceholders})");
        $delStmt->execute(array_merge([$productId], array_values($toRemove)));
    }

    // Insert or update selected sections
    $upsertStmt = $db->prepare("INSERT INTO homepage_section_products (section_key, product_id, sort_order, badge_text, is_featured, is_visible)
                                VALUES (?, ?, ?, ?, ?, 1)
                                ON DUPLICATE KEY UPDATE
                                  sort_order = VALUES(sort_order),
                                  badge_text = VALUES(badge_text),
                                  is_featured = VALUES(is_featured),
                                  is_visible = 1,
                                  updated_at = NOW()");

    foreach ($selectedSections as $secKey) {
        $secKey = clean_string($secKey);
        if (empty($secKey)) continue;

        $badge = clean_string($badges[$secKey] ?? '');
        $sortOrder = sanitize_int($orders[$secKey] ?? 0);
        $isFeatured = ($secKey === 'featured') ? 1 : 0;

        $upsertStmt->execute([$secKey, $productId, $sortOrder, $badge, $isFeatured]);
    }
}

/**
 * Return display icon for a homepage section or category.
 */
function get_section_icon(string $sectionKey): string
{
    return match ($sectionKey) {
        'servers'      => '▤',
        'networking'   => '🖧',
        'components'   => '▦',
        'our-products' => '🛍️',
        'featured'     => '🏷️',
        'homepage'     => '🏠',
        'categories'   => '📁',
        'images'       => '🖼️',
        default        => '📦',
    };
}
