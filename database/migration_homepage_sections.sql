-- ==============================================================================
-- iboltnetworks Migration: Homepage Sections & Section Product Assignments
-- Target: MySQL 8.0+ / MariaDB 10.4+ (Compatible with MAMP & Hostinger)
-- Charset: utf8mb4 / Engine: InnoDB
-- Idempotent: Safe to execute once or multiple times
-- Portable: No hardcoded database name.
-- ==============================================================================

-- ------------------------------------------------------------------------------
-- 1. Homepage Sections Table
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `homepage_sections` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `section_key` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `display_title` VARCHAR(150) NOT NULL,
  `subtitle` VARCHAR(255) NULL,
  `icon` VARCHAR(50) NOT NULL DEFAULT 'server',
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_hs_key` (`section_key`),
  INDEX `idx_hs_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 2. Homepage Section Products Mapping Table
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `homepage_section_products` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `section_key` VARCHAR(50) NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `badge_text` VARCHAR(60) NULL,
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `is_visible` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_section_product` (`section_key`, `product_id`),
  INDEX `idx_hsp_section` (`section_key`),
  INDEX `idx_hsp_product` (`product_id`),
  INDEX `idx_hsp_order` (`sort_order`),
  CONSTRAINT `fk_hsp_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 3. Seed Homepage Sections (Idempotent INSERT IGNORE)
-- ------------------------------------------------------------------------------
INSERT IGNORE INTO `homepage_sections` (`section_key`, `name`, `display_title`, `subtitle`, `icon`, `sort_order`, `is_active`)
VALUES
  ('servers', 'Servers Section', 'Enterprise Rack & Tower Servers', 'Dell, HPE, Cisco & Huawei enterprise servers engineered for business-critical compute', 'server', 1, 1),
  ('networking', 'Networking Section', 'Enterprise Switches, Routers & Firewalls', 'High-performance switches, routers, network adapters, and datacenter connectivity', 'network', 2, 1),
  ('components', 'Components Section', 'Processors, Memory, Storage & Server Spares', 'Certified enterprise CPUs, DDR ECC RAM, NVMe/SATA SSDs, RAID controllers, and power supplies', 'cpu', 3, 1),
  ('our-products', 'Our Products Section', 'Computer & Laptop Accessories, Monitors & Peripherals', 'Storewide hardware essentials, keyboards, mice, LED monitors, docking stations, and cables', 'shopping-bag', 4, 1),
  ('featured', 'Featured & Promotional Products', 'Spotlight Deals & Special Offers', 'Curated limited-time discounts, bulk-purchase specials, and featured hardware spotlights', 'tag', 5, 1);

-- ------------------------------------------------------------------------------
-- 4. Seed Initial Section Assignments from Existing Database Products
-- ------------------------------------------------------------------------------

-- Seed Servers section products
INSERT IGNORE INTO `homepage_section_products` (`section_key`, `product_id`, `sort_order`, `badge_text`, `is_featured`, `is_visible`)
SELECT 'servers', p.id, (@row_srv := @row_srv + 1) AS sort_order,
       CASE WHEN p.condition_type = 'refurbished' THEN 'Certified Refurb' ELSE 'Top Seller' END,
       p.is_featured, 1
FROM products p
LEFT JOIN categories c ON p.category_id = c.id
CROSS JOIN (SELECT @row_srv := 0) r
WHERE (c.section = 'servers' OR p.title LIKE '%Server%')
  AND p.status = 'published'
ORDER BY p.is_featured DESC, p.views_count DESC, p.id DESC
LIMIT 12;

-- Seed Networking section products
INSERT IGNORE INTO `homepage_section_products` (`section_key`, `product_id`, `sort_order`, `badge_text`, `is_featured`, `is_visible`)
SELECT 'networking', p.id, (@row_net := @row_net + 1) AS sort_order,
       'Enterprise Grade', p.is_featured, 1
FROM products p
LEFT JOIN categories c ON p.category_id = c.id
CROSS JOIN (SELECT @row_net := 0) r
WHERE (c.section = 'networking' OR p.title LIKE '%Switch%' OR p.title LIKE '%Router%' OR p.title LIKE '%Network%')
  AND p.status = 'published'
ORDER BY p.is_featured DESC, p.views_count DESC, p.id DESC
LIMIT 12;

-- Seed Components section products
INSERT IGNORE INTO `homepage_section_products` (`section_key`, `product_id`, `sort_order`, `badge_text`, `is_featured`, `is_visible`)
SELECT 'components', p.id, (@row_cmp := @row_cmp + 1) AS sort_order,
       'OEM Certified', p.is_featured, 1
FROM products p
LEFT JOIN categories c ON p.category_id = c.id
CROSS JOIN (SELECT @row_cmp := 0) r
WHERE (c.section = 'components' OR p.title LIKE '%RAM%' OR p.title LIKE '%Processor%' OR p.title LIKE '%SSD%' OR p.title LIKE '%Xeon%')
  AND p.status = 'published'
ORDER BY p.is_featured DESC, p.views_count DESC, p.id DESC
LIMIT 12;

-- Seed Our Products section products
INSERT IGNORE INTO `homepage_section_products` (`section_key`, `product_id`, `sort_order`, `badge_text`, `is_featured`, `is_visible`)
SELECT 'our-products', p.id, (@row_our := @row_our + 1) AS sort_order,
       'Fast Shipping', p.is_featured, 1
FROM products p
LEFT JOIN categories c ON p.category_id = c.id
CROSS JOIN (SELECT @row_our := 0) r
WHERE (c.section = 'our-products' OR c.section = 'general' OR c.section IS NULL)
  AND p.status = 'published'
ORDER BY p.is_featured DESC, p.views_count DESC, p.id DESC
LIMIT 12;

-- Seed Featured section products
INSERT IGNORE INTO `homepage_section_products` (`section_key`, `product_id`, `sort_order`, `badge_text`, `is_featured`, `is_visible`)
SELECT 'featured', p.id, (@row_ft := @row_ft + 1) AS sort_order,
       'Special Offer', 1, 1
FROM products p
CROSS JOIN (SELECT @row_ft := 0) r
WHERE p.is_featured = 1 AND p.status = 'published'
ORDER BY p.views_count DESC, p.id DESC
LIMIT 8;
