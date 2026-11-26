-- ==============================================================================
-- iboltnetworks Migration 001: Homepage Sections & Section Product Assignments
-- Target: MySQL 8.0+ / MariaDB 10.4+ (Compatible with Hostinger Shared Hosting & MAMP)
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

