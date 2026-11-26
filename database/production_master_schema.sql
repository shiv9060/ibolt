-- ==============================================================================
-- iboltnetworks - Consolidated Production Master Database Schema & Catalog Data
-- Target: Hostinger Shared Hosting & MAMP (MySQL 8.0+ / MariaDB 10.4+)
-- Portable: Safe to import directly into any target database in phpMyAdmin.
-- Contains all 18 tables, reference homepage sections, and full product catalog.
-- ZERO customer records, ZERO orders, ZERO hardcoded administrator passwords.
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- >>> 1. SCHEMA DEFINITIONS (18 TABLES) >>>

-- ==============================================================================
-- iboltnetworks E-Commerce Database Schema
-- Target: MySQL 8.0+ / MariaDB 10.4+ (Compatible with Hostinger Shared Hosting & MAMP)
-- Charset: utf8mb4 / Engine: InnoDB
-- All monetary amounts are stored in PAISE (1 INR = 100 paise) as INT UNSIGNED
-- Portable: No hardcoded database names. Run inside any active database.
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. Customer Users Table
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(191) NOT NULL UNIQUE,
  `phone` VARCHAR(24) NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `email_verified_at` DATETIME NULL,
  `remember_token` VARCHAR(100) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_users_email` (`email`),
  INDEX `idx_users_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 2. Administrative Users Table
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(120) NOT NULL,
  `username` VARCHAR(60) NULL UNIQUE,
  `email` VARCHAR(191) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('super_admin', 'admin', 'manager') NOT NULL DEFAULT 'admin',
  `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
  `last_login_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 3. Categories Table (Hierarchical support with parent_id)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `parent_id` INT UNSIGNED NULL,
  `name` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(150) NOT NULL UNIQUE,
  `section` VARCHAR(50) NOT NULL DEFAULT 'general', -- 'servers', 'networking', 'components', 'our-products'
  `description` TEXT NULL,
  `image` VARCHAR(255) NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_cat_parent` (`parent_id`),
  INDEX `idx_cat_section` (`section`),
  INDEX `idx_cat_slug` (`slug`),
  CONSTRAINT `fk_categories_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 4. Brands Table
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `brands` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(150) NOT NULL UNIQUE,
  `logo` VARCHAR(255) NULL,
  `website` VARCHAR(255) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_brands_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 5. Products Table
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT UNSIGNED NULL,
  `brand_id` INT UNSIGNED NULL,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(191) NOT NULL UNIQUE,
  `sku` VARCHAR(64) NOT NULL UNIQUE,
  `model` VARCHAR(120) NULL,
  `condition_type` ENUM('new', 'refurbished', 'open_box') NOT NULL DEFAULT 'new',
  `short_description` VARCHAR(500) NULL,
  `description` TEXT NULL,
  `price_paise` INT UNSIGNED NOT NULL, -- Integer paise e.g. 149900 = ₹1,499.00
  `compare_price_paise` INT UNSIGNED NULL, -- Original MRP / crossed-out price
  `cost_price_paise` INT UNSIGNED NULL, -- Admin internal wholesale cost
  `stock_quantity` INT NOT NULL DEFAULT 10,
  `low_stock_threshold` INT NOT NULL DEFAULT 3,
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'published',
  `rating` DECIMAL(2,1) NOT NULL DEFAULT 4.8,
  `review_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `views_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_prod_cat` (`category_id`),
  INDEX `idx_prod_brand` (`brand_id`),
  INDEX `idx_prod_status` (`status`),
  INDEX `idx_prod_condition` (`condition_type`),
  INDEX `idx_prod_price` (`price_paise`),
  INDEX `idx_prod_featured` (`is_featured`),
  FULLTEXT INDEX `ft_prod_search` (`title`, `short_description`, `model`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_products_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 6. Product Images Table
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_images` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT UNSIGNED NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `alt_text` VARCHAR(255) NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_img_product` (`product_id`),
  CONSTRAINT `fk_product_images_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 7. Product Specifications Table
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_specifications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT UNSIGNED NOT NULL,
  `group_name` VARCHAR(80) NOT NULL DEFAULT 'General',
  `spec_key` VARCHAR(100) NOT NULL,
  `spec_value` VARCHAR(255) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  INDEX `idx_spec_product` (`product_id`),
  CONSTRAINT `fk_product_specs_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 8. Customer Addresses Table
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `addresses` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NULL,
  `full_name` VARCHAR(120) NOT NULL,
  `phone` VARCHAR(24) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `address_line1` VARCHAR(255) NOT NULL,
  `address_line2` VARCHAR(255) NULL,
  `city` VARCHAR(100) NOT NULL,
  `state` VARCHAR(100) NOT NULL,
  `postal_code` VARCHAR(16) NOT NULL,
  `country` VARCHAR(60) NOT NULL DEFAULT 'India',
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_addr_user` (`user_id`),
  CONSTRAINT `fk_addresses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 9. Orders Table
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_number` VARCHAR(32) NOT NULL UNIQUE,
  `user_id` INT UNSIGNED NULL,
  `customer_name` VARCHAR(120) NOT NULL,
  `customer_email` VARCHAR(191) NOT NULL,
  `customer_phone` VARCHAR(24) NOT NULL,
  `shipping_address_id` INT UNSIGNED NULL,
  `shipping_address_snapshot` JSON NOT NULL,
  `status` ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded') NOT NULL DEFAULT 'pending',
  `payment_status` ENUM('unpaid', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'unpaid',
  `payment_method` VARCHAR(32) NOT NULL DEFAULT 'razorpay',
  `currency` VARCHAR(3) NOT NULL DEFAULT 'INR',
  `subtotal_paise` INT UNSIGNED NOT NULL,
  `shipping_paise` INT UNSIGNED NOT NULL DEFAULT 0,
  `tax_paise` INT UNSIGNED NOT NULL DEFAULT 0,
  `discount_paise` INT UNSIGNED NOT NULL DEFAULT 0,
  `total_paise` INT UNSIGNED NOT NULL,
  `tracking_carrier` VARCHAR(80) NULL,
  `tracking_number` VARCHAR(100) NULL,
  `customer_notes` TEXT NULL,
  `admin_notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_order_user` (`user_id`),
  INDEX `idx_order_status` (`status`),
  INDEX `idx_order_payment_status` (`payment_status`),
  INDEX `idx_order_created` (`created_at`),
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_shipping_addr` FOREIGN KEY (`shipping_address_id`) REFERENCES `addresses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 10. Order Items Table
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NULL,
  `product_title` VARCHAR(255) NOT NULL,
  `sku` VARCHAR(64) NOT NULL,
  `price_paise` INT UNSIGNED NOT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `total_paise` INT UNSIGNED NOT NULL,
  `specs_snapshot` JSON NULL,
  INDEX `idx_item_order` (`order_id`),
  INDEX `idx_item_product` (`product_id`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 11. Payments Table (Razorpay Integration)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT UNSIGNED NOT NULL,
  `provider` VARCHAR(32) NOT NULL DEFAULT 'razorpay',
  `razorpay_order_id` VARCHAR(64) NULL,
  `razorpay_payment_id` VARCHAR(64) NULL,
  `razorpay_signature` VARCHAR(128) NULL,
  `amount_paise` INT UNSIGNED NOT NULL,
  `currency` VARCHAR(3) NOT NULL DEFAULT 'INR',
  `status` ENUM('created', 'authorized', 'captured', 'failed', 'refunded') NOT NULL DEFAULT 'created',
  `error_code` VARCHAR(64) NULL,
  `error_description` TEXT NULL,
  `raw_response` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_pay_order` (`order_id`),
  INDEX `idx_pay_rzp_order` (`razorpay_order_id`),
  INDEX `idx_pay_rzp_payment` (`razorpay_payment_id`),
  CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 12. Inventory Movements Audit Table
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventory_movements` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT UNSIGNED NOT NULL,
  `quantity_change` INT NOT NULL, -- e.g. -2 or +10
  `balance_after` INT NOT NULL,
  `reason` ENUM('order_placed', 'order_cancelled', 'manual_adjustment', 'restock', 'damage', 'stock_in') NOT NULL,
  `reference_id` VARCHAR(64) NULL, -- Order number or batch ID
  `admin_id` INT UNSIGNED NULL,
  `note` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_inv_product` (`product_id`),
  CONSTRAINT `fk_inv_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inv_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 13. Admin Activity Logs Table
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_activity_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT UNSIGNED NULL,
  `action` VARCHAR(64) NOT NULL,
  `entity_type` VARCHAR(64) NOT NULL,
  `entity_id` INT UNSIGNED NULL,
  `details` JSON NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_log_admin` (`admin_id`),
  INDEX `idx_log_action` (`action`),
  CONSTRAINT `fk_activity_admin` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------------------------
-- 14. Password Resets Table
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(191) NOT NULL,
  `token` VARCHAR(100) NOT NULL,
  `user_type` ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_pwd_reset_email` (`email`),
  INDEX `idx_pwd_reset_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 15. Homepage Sections Table
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
-- 16. Homepage Section Products Mapping Table
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
-- 17. Order Status History Table
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_status_history` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT UNSIGNED NOT NULL,
  `old_status` VARCHAR(32) NOT NULL,
  `new_status` VARCHAR(32) NOT NULL,
  `status_type` ENUM('fulfillment', 'payment') NOT NULL DEFAULT 'fulfillment',
  `changed_by_admin_id` INT UNSIGNED NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_osh_order` (`order_id`),
  INDEX `idx_osh_created` (`created_at`),
  INDEX `idx_osh_admin` (`changed_by_admin_id`),
  CONSTRAINT `fk_osh_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_osh_admin` FOREIGN KEY (`changed_by_admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 18. Login Attempts Rate Limiting Table
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `identifier` VARCHAR(191) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_la_ident_time` (`identifier`, `attempted_at`),
  INDEX `idx_la_ip_time` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- System Reference Data: Default Homepage Sections (Idempotent)
-- ------------------------------------------------------------------------------
INSERT IGNORE INTO `homepage_sections` (`section_key`, `name`, `display_title`, `subtitle`, `icon`, `sort_order`, `is_active`)
VALUES
  ('servers', 'Servers Section', 'Enterprise Rack & Tower Servers', 'Dell, HPE, Cisco & Huawei enterprise servers engineered for business-critical compute', 'server', 1, 1),
  ('networking', 'Networking Section', 'Enterprise Switches, Routers & Firewalls', 'High-performance switches, routers, network adapters, and datacenter connectivity', 'network', 2, 1),
  ('components', 'Components Section', 'Processors, Memory, Storage & Server Spares', 'Certified enterprise CPUs, DDR ECC RAM, NVMe/SATA SSDs, RAID controllers, and power supplies', 'cpu', 3, 1),
  ('our-products', 'Our Products Section', 'Computer & Laptop Accessories, Monitors & Peripherals', 'Storewide hardware essentials, keyboards, mice, LED monitors, docking stations, and cables', 'shopping-bag', 4, 1),
  ('featured', 'Featured & Promotional Products', 'Spotlight Deals & Special Offers', 'Curated limited-time discounts, bulk-purchase specials, and featured hardware spotlights', 'tag', 5, 1);

SET FOREIGN_KEY_CHECKS = 1;


-- >>> 2. CATALOG REFERENCE SEED DATA >>>

-- ==============================================================================
-- iboltnetworks Production Catalog Reference Seed Data
-- 121 enterprise hardware products, 65 categories, 29 brands, specifications,
-- product gallery images, and homepage merchandising section placements.
-- Target: MySQL 8.0+ / MariaDB 10.4+ (Hostinger Shared Hosting & MAMP)
-- Non-destructive: Safe to run on empty or existing databases.
-- Contains ZERO customer records, ZERO orders, and ZERO hardcoded administrator passwords.
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------------------------
-- 1. Seed Brands (Idempotent)
-- ------------------------------------------------------------------------------
INSERT IGNORE INTO `brands` (`name`, `slug`, `is_active`) VALUES
('Dell', 'dell', 1),
('HPE', 'hpe', 1),
('Cisco', 'cisco', 1),
('Huawei', 'huawei', 1),
('Intel', 'intel', 1),
('AMD', 'amd', 1),
('NVIDIA', 'nvidia', 1),
('Seagate', 'seagate', 1),
('Western Digital', 'western-digital', 1),
('Toshiba', 'toshiba', 1),
('HGST', 'hgst', 1),
('Fortinet', 'fortinet', 1),
('Radware', 'radware', 1),
('A10 Networks', 'a10-networks', 1),
('F5 Networks', 'f5-networks', 1),
('Juniper', 'juniper', 1),
('MikroTik', 'mikrotik', 1),
('Kemp', 'kemp', 1),
('Citrix', 'citrix', 1),
('Kingston', 'kingston', 1),
('Samsung', 'samsung', 1),
('Crucial', 'crucial', 1),
('Logitech', 'logitech', 1),
('TP-Link', 'tp-link', 1),
('ASUS', 'asus', 1),
('Lenovo', 'lenovo', 1),
('Apple', 'apple', 1),
('HP', 'hp', 1),
('SK Hynix', 'sk-hynix', 1),
('WD', 'wd', 1),
('D-Link', 'd-link', 1),
('Micron', 'micron', 1),
('Molex', 'molex', 1),
('CommScope', 'commscope', 1),
('Nokia', 'nokia', 1),
('iboltnetworks', 'iboltnetworks', 1);

-- ------------------------------------------------------------------------------
-- 3. Seed Categories (Hierarchy with Parent Slugs)
-- ------------------------------------------------------------------------------
INSERT INTO `categories` (`name`, `slug`, `section`, `description`, `is_active`) VALUES
('Servers', 'servers', 'servers', 'High-performance enterprise rack, tower, and blade servers with multi-socket configurations.', 1),
('Networking', 'networking', 'networking', 'Enterprise firewalls, switches, hardware load balancers, and fiber modules.', 1),
('Components', 'components', 'components', 'Genuine processors, DDR4/DDR5 ECC RAM, SAS/NVMe drives, and AI GPUs.', 1),
('Our Products', 'our-products', 'our-products', 'Premium computer accessories, displays, docking stations, and peripherals.', 1),
('Dell Refurbished Servers', 'dell-refurbished-servers', 'servers', 'Tested and certified refurbished Dell PowerEdge rack servers with warranty.', 1),
('HPE Refurbished Servers', 'hpe-refurbished-servers', 'servers', 'Enterprise refurbished HPE ProLiant Gen9 and Gen10 servers.', 1),
('Dell New Servers', 'dell-new-servers', 'servers', 'Factory-sealed 15th & 16th generation Dell PowerEdge servers.', 1),
('HPE New Servers', 'hpe-new-servers', 'servers', 'Brand new HPE ProLiant Gen11 and Gen12 compute platforms.', 1),
('Cisco Servers', 'cisco-servers', 'servers', 'Cisco UCS C-Series high density compute nodes.', 1),
('Huawei Refurbished Servers', 'huawei-refurbished-servers', 'servers', 'Huawei FusionServer multi-node architectures.', 1),
('Storage Servers', 'storage-servers', 'servers', 'High density SAN and NAS storage server appliances.', 1),
('AI & GPU Servers', 'ai-servers', 'servers', 'Multi-GPU accelerated platforms for deep learning and high-performance computing.', 1),
('Customized Servers', 'custom-server', 'servers', 'Configured-to-order enterprise server solutions.', 1),
('Firewalls', 'firewalls', 'networking', 'Next-Generation threat prevention and enterprise firewalls.', 1),
('Basic Firewalls', 'basic-firewalls', 'networking', 'Branch and SMB next-gen firewalls.', 1),
('Enterprise Firewalls', 'enterprise-firewalls', 'networking', 'High-throughput datacenter and enterprise perimeter firewalls.', 1),
('FortiGate Firewalls', 'fortigate-firewall', 'networking', 'Industry-leading Fortinet FortiGate security appliances.', 1),
('Network Switches', 'network-switches', 'networking', 'Managed Layer 2/3 enterprise and datacenter switches.', 1),
('Cisco Switches', 'cisco-switches', 'networking', 'Cisco Catalyst and Nexus enterprise switches.', 1),
('Huawei Switches', 'huawei-switches', 'networking', 'Huawei CloudEngine enterprise switches.', 1),
('Juniper Switches', 'juniper-switches', 'networking', 'Juniper EX-series datacenter switches.', 1),
('KVM Switches', 'kvm-switches', 'networking', 'Rackmount remote KVM console switches.', 1),
('MikroTik Switches', 'mikrotik-switches', 'networking', 'MikroTik high-density cloud router switches.', 1),
('Load Balancers', 'load-balancers', 'networking', 'Application delivery controllers and hardware traffic balancers.', 1),
('SFP Modules', 'sfp-modules', 'networking', '1G, 10G, 40G, and 100G optical transceivers.', 1),
('DAC Cables', 'dac-cables', 'networking', 'Direct attach copper high-speed interconnect cables.', 1),
('Routers', 'routers', 'networking', 'Carrier-grade and enterprise edge routing systems.', 1),
('Processors', 'processors', 'components', 'Intel Xeon and multi-core server processors.', 1),
('AMD EPYC Processors', 'amd-epyc-processors', 'components', 'High-density AMD EPYC server CPUs up to 64 cores.', 1),
('DDR5 Server RAM', 'ddr5-server-ram', 'components', 'Next-generation registered ECC DDR5 memory modules.', 1),
('DDR4 Server RAM', 'ddr4-server-ram', 'components', 'Registered and load-reduced DDR4 2666/2933/3200MHz RAM.', 1),
('DDR3 Server RAM', 'ddr3-server-ram', 'components', 'Legacy DDR3 ECC memory for Gen8/R720 servers.', 1),
('Hard Disk Drives', 'hard-disk-drives', 'components', 'Enterprise 12Gbps SAS and SATA 7.2K RPM HDDs up to 24TB.', 1),
('Solid State Drives', 'solid-state-drives', 'components', 'Datacenter grade SAS, SATA, and NVMe SSD drives.', 1),
('AI & Enterprise GPUs', 'enterprise-gpus', 'components', 'NVIDIA RTX Ada and Tensor Core datacenter accelerators.', 1),
('Other Spares', 'other-spares', 'components', 'Redundant power supplies, RAID controllers, motherboards, fans, and caddies.', 1),
('Computer Accessories', 'computer-accessories', 'our-products', 'Input peripherals, hubs, webcams, and accessories.', 1),
('Keyboards', 'keyboards', 'our-products', 'Mechanical, tactile, and silent office keyboards.', 1),
('Computer Mice', 'computer-mice', 'our-products', 'Ergonomic, gaming, and precision wireless mice.', 1),
('Mouse Pads', 'mouse-pads', 'our-products', 'Extended desk mats and micro-weave tracking surfaces.', 1),
('Webcams', 'webcams', 'our-products', 'Full HD and 4K conferencing cameras with stereo microphones.', 1),
('USB Hubs', 'usb-hubs', 'our-products', 'Powered multi-port USB 3.2 hubs.', 1),
('Laptop Accessories', 'laptop-accessories', 'our-products', 'Stands, chargers, bags, and multi-display docking hubs.', 1),
('Laptop Chargers', 'laptop-chargers', 'our-products', 'USB-C GaN fast chargers and universal power adapters.', 1),
('Laptop Bags', 'laptop-bags', 'our-products', 'Water-resistant padded laptop backpacks and sleeves.', 1),
('Cooling Pads', 'cooling-pads', 'our-products', 'Aluminum active cooling stands with high-flow fans.', 1),
('Laptop Stands', 'laptop-stands', 'our-products', 'Ergonomic folding aluminum laptop risers.', 1),
('Docking Stations', 'docking-stations', 'our-products', 'Thunderbolt 4 and multi-display USB-C docks.', 1),
('Monitors & Displays', 'monitors-displays', 'our-products', 'Business monitors, gaming screens, and VESA arms.', 1),
('LED Monitors', 'led-monitors', 'our-products', 'IPS Full HD and 4K productivity monitors.', 1),
('Gaming Monitors', 'gaming-monitors', 'our-products', '165Hz–240Hz fast response gaming displays.', 1),
('Monitor Stands', 'monitor-stands', 'our-products', 'Gas spring single and dual monitor desk arms.', 1),
('Display Adapters', 'display-adapters', 'our-products', 'USB-C to HDMI, DisplayPort, and multi-stream adapters.', 1),
('HDMI Cables', 'hdmi-cables-display', 'our-products', 'High-speed 4K/8K certified HDMI cables.', 1),
('Storage & Components', 'storage-components', 'our-products', 'NVMe SSDs, external storage drives, RAM, and GPUs.', 1),
('Internal SSDs', 'internal-ssds', 'our-products', 'PCIe Gen4/Gen5 high speed internal NVMe SSDs.', 1),
('External SSDs', 'external-ssds', 'our-products', 'Rugged portable SSDs with USB-C 3.2 Gen2x2.', 1),
('Hard Drives', 'hard-drives', 'our-products', 'SATA and external USB desktop hard disk drives.', 1),
('RAM Modules', 'ram-modules', 'our-products', 'Desktop DDR4 and DDR5 gaming and productivity RAM.', 1),
('Graphics Cards', 'graphics-cards', 'our-products', 'Consumer and creator desktop graphics cards.', 1),
('Cables & Networking', 'cables-networking', 'our-products', 'Cables, adapters, and high-speed Wi-Fi network cards.', 1),
('USB Cables', 'usb-cables', 'our-products', 'Braided USB-C, Type-A, and 100W PD charging cables.', 1),
('Ethernet Cables', 'ethernet-cables', 'our-products', 'Cat6 and Cat7 shielded gigabit network cables.', 1),
('Network Adapters', 'network-adapters', 'our-products', 'USB-C to 2.5G/5G Ethernet network adapters.', 1),
('Wi-Fi Adapters', 'wifi-adapters', 'our-products', 'Wi-Fi 6E dual and tri-band high-gain wireless USB cards.', 1),
('Laptop RAM', 'laptop-ram', 'components', 'DDR4, DDR5, and SODIMM memory modules for laptops and notebooks.', 1),
('Desktop RAM', 'desktop-ram', 'components', 'High-speed DDR4 and DDR5 DIMM RAM for desktop computers and workstations.', 1),
('Laptop SSD', 'laptop-ssd', 'components', 'M.2 NVMe and 2.5-inch SATA solid state drives for laptops.', 1),
('Desktop Hard Disk', 'desktop-hard-disk', 'components', '3.5-inch SATA internal and external hard disk drives for desktop PCs.', 1),
('Laptop Hard Disk', 'laptop-hard-disk', 'components', '2.5-inch SATA slim internal hard disk drives for laptops.', 1),
('Server Hard Disk', 'server-hard-disk', 'components', 'Enterprise 2.5-inch and 3.5-inch 12Gbps SAS, SATA, and NVMe server hard drives.', 1),
('Server RAM', 'server-ram', 'components', 'ECC Registered (RDIMM/LRDIMM) server memory for Dell, HPE, and Cisco servers.', 1),
('SFP', 'sfp', 'networking', '1G and 10G SFP/SFP+ optical transceiver modules and transceivers.', 1),
('QSFP', 'qsfp', 'networking', 'High-bandwidth 40G, 100G, and 200G QSFP+/QSFP28 transceivers and interconnects.', 1),
('Module', 'module', 'networking', 'Network expansion modules, supervisor modules, and line cards.', 1),
('Laptop', 'laptop', 'our-products', 'Business, enterprise, and portable performance laptops.', 1),
('Desktop', 'desktop', 'our-products', 'Desktop tower PCs, small form factor (SFF) units, and workstations.', 1),
('Server', 'server', 'servers', 'Enterprise rack, tower, and modular compute server systems.', 1),
('Switch', 'switch', 'networking', 'Managed Layer 2/3 network switches and PoE distribution switches.', 1),
('Cable', 'cable', 'networking', 'Ethernet patch cords, fiber optic patch cables, and DAC interconnects.', 1),
('Adapter', 'adapter', 'our-products', 'Power adapters, charging bricks, and hardware interface adapters.', 1),
('Battery', 'battery', 'our-products', 'Replacement laptop batteries, server RAID cache batteries, and UPS batteries.', 1),
('Phone', 'phone', 'our-products', 'VoIP business desk phones, IP conference phones, and communication handsets.', 1),
('Printer', 'printer', 'our-products', 'Office laser printers, multifunction network printers, and consumables.', 1);

UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'components' LIMIT 1) AS p) 
WHERE `slug` IN ('laptop-ram', 'desktop-ram', 'laptop-ssd', 'desktop-hard-disk', 'laptop-hard-disk', 'server-hard-disk', 'server-ram');

UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'networking' LIMIT 1) AS p) 
WHERE `slug` IN ('sfp', 'qsfp', 'module', 'switch', 'cable');

UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'our-products' LIMIT 1) AS p) 
WHERE `slug` IN ('laptop', 'desktop', 'adapter', 'battery', 'phone', 'printer');

UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'servers' LIMIT 1) AS p) 
WHERE `slug` IN ('server');

UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'servers') AS p) WHERE `slug` = 'dell-refurbished-servers';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'servers') AS p) WHERE `slug` = 'hpe-refurbished-servers';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'servers') AS p) WHERE `slug` = 'dell-new-servers';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'servers') AS p) WHERE `slug` = 'hpe-new-servers';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'servers') AS p) WHERE `slug` = 'cisco-servers';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'servers') AS p) WHERE `slug` = 'huawei-refurbished-servers';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'servers') AS p) WHERE `slug` = 'storage-servers';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'servers') AS p) WHERE `slug` = 'ai-servers';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'servers') AS p) WHERE `slug` = 'custom-server';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'networking') AS p) WHERE `slug` = 'firewalls';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'firewalls') AS p) WHERE `slug` = 'basic-firewalls';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'firewalls') AS p) WHERE `slug` = 'enterprise-firewalls';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'firewalls') AS p) WHERE `slug` = 'fortigate-firewall';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'networking') AS p) WHERE `slug` = 'network-switches';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'network-switches') AS p) WHERE `slug` = 'cisco-switches';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'network-switches') AS p) WHERE `slug` = 'huawei-switches';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'network-switches') AS p) WHERE `slug` = 'juniper-switches';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'network-switches') AS p) WHERE `slug` = 'kvm-switches';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'network-switches') AS p) WHERE `slug` = 'mikrotik-switches';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'networking') AS p) WHERE `slug` = 'load-balancers';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'networking') AS p) WHERE `slug` = 'sfp-modules';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'networking') AS p) WHERE `slug` = 'dac-cables';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'networking') AS p) WHERE `slug` = 'routers';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'components') AS p) WHERE `slug` = 'processors';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'components') AS p) WHERE `slug` = 'amd-epyc-processors';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'components') AS p) WHERE `slug` = 'ddr5-server-ram';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'components') AS p) WHERE `slug` = 'ddr4-server-ram';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'components') AS p) WHERE `slug` = 'ddr3-server-ram';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'components') AS p) WHERE `slug` = 'hard-disk-drives';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'components') AS p) WHERE `slug` = 'solid-state-drives';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'components') AS p) WHERE `slug` = 'enterprise-gpus';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'components') AS p) WHERE `slug` = 'other-spares';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'our-products') AS p) WHERE `slug` = 'computer-accessories';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'computer-accessories') AS p) WHERE `slug` = 'keyboards';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'computer-accessories') AS p) WHERE `slug` = 'computer-mice';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'computer-accessories') AS p) WHERE `slug` = 'mouse-pads';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'computer-accessories') AS p) WHERE `slug` = 'webcams';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'computer-accessories') AS p) WHERE `slug` = 'usb-hubs';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'our-products') AS p) WHERE `slug` = 'laptop-accessories';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'laptop-accessories') AS p) WHERE `slug` = 'laptop-chargers';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'laptop-accessories') AS p) WHERE `slug` = 'laptop-bags';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'laptop-accessories') AS p) WHERE `slug` = 'cooling-pads';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'laptop-accessories') AS p) WHERE `slug` = 'laptop-stands';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'laptop-accessories') AS p) WHERE `slug` = 'docking-stations';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'our-products') AS p) WHERE `slug` = 'monitors-displays';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'monitors-displays') AS p) WHERE `slug` = 'led-monitors';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'monitors-displays') AS p) WHERE `slug` = 'gaming-monitors';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'monitors-displays') AS p) WHERE `slug` = 'monitor-stands';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'monitors-displays') AS p) WHERE `slug` = 'display-adapters';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'monitors-displays') AS p) WHERE `slug` = 'hdmi-cables-display';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'our-products') AS p) WHERE `slug` = 'storage-components';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'storage-components') AS p) WHERE `slug` = 'internal-ssds';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'storage-components') AS p) WHERE `slug` = 'external-ssds';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'storage-components') AS p) WHERE `slug` = 'hard-drives';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'storage-components') AS p) WHERE `slug` = 'ram-modules';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'storage-components') AS p) WHERE `slug` = 'graphics-cards';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'our-products') AS p) WHERE `slug` = 'cables-networking';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'cables-networking') AS p) WHERE `slug` = 'usb-cables';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'cables-networking') AS p) WHERE `slug` = 'ethernet-cables';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'cables-networking') AS p) WHERE `slug` = 'network-adapters';
UPDATE `categories` SET `parent_id` = (SELECT `id` FROM (SELECT `id` FROM `categories` WHERE `slug` = 'cables-networking') AS p) WHERE `slug` = 'wifi-adapters';

-- ------------------------------------------------------------------------------
-- 4. Seed Products
-- ------------------------------------------------------------------------------
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'dell-refurbished-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'dell' LIMIT 1),
  'Dell PowerEdge R840 Rack Server',
  'dell-r840-rack-server',
  'IB-SRV-1001',
  'dell-r840-rack-server',
  'refurbished',
  'Dell enterprise server featuring Up to 4x 2nd Gen Intel Xeon Scalable Processors (Up to 112 Cores). Form factor: 2U 4-Socket Rack.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  24500000,
  32000000,
  8,
  1,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r840-rack-server'),
  'images/dell-poweredge-r640.png',
  'Dell PowerEdge R840 Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r840-rack-server'),
  'Specifications',
  'Form Factor',
  '2U 4-Socket Rack',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r840-rack-server'),
  'Specifications',
  'Processors',
  'Up to 4x 2nd Gen Intel Xeon Scalable Processors (Up to 112 Cores)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r840-rack-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 6TB DDR4 2933MT/s (48 DIMM slots)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r840-rack-server'),
  'Specifications',
  'Internal Storage',
  'Up to 24x 2.5',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r840-rack-server'),
  'Specifications',
  'RAID Controller',
  'PERC H730P, H740P, H330, or 12Gbps SAS HBA',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r840-rack-server'),
  'Specifications',
  'Power Supply',
  'Hot-plug redundant 750W, 1100W, 1600W, or 2400W Titanium/Platinum',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r840-rack-server'),
  'Specifications',
  'Network Interface',
  'Embedded Broadcom / Intel 4x 10GbE / 1GbE Daughtercard',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'dell-refurbished-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'dell' LIMIT 1),
  'Dell PowerEdge R740xd Rack Server',
  'dell-r740xd-rack-server',
  'IB-SRV-1002',
  'dell-r740xd-rack-server',
  'refurbished',
  'Dell enterprise server featuring Up to 2x Intel Xeon Scalable Processors (Up to 28 cores per processor). Form factor: 2U 2-Socket High-Density Storage Rack.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  17500000,
  24000000,
  8,
  1,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r740xd-rack-server'),
  'images/dell-poweredge-r640.png',
  'Dell PowerEdge R740xd Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r740xd-rack-server'),
  'Specifications',
  'Form Factor',
  '2U 2-Socket High-Density Storage Rack',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r740xd-rack-server'),
  'Specifications',
  'Processors',
  'Up to 2x Intel Xeon Scalable Processors (Up to 28 cores per processor)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r740xd-rack-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 3TB DDR4 2933MT/s RDIMM/LRDIMM (24 slots)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r740xd-rack-server'),
  'Specifications',
  'Internal Storage',
  'Up to 24x 2.5',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r740xd-rack-server'),
  'Specifications',
  'RAID Controller',
  'PERC H730P, H740P 8GB NV Cache, HBA330',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r740xd-rack-server'),
  'Specifications',
  'Power Supply',
  'Dual Hot-Plug Redundant 750W / 1100W Platinum PSUs',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r740xd-rack-server'),
  'Specifications',
  'Network Interface',
  'Quad-port 10GbE SFP+ / 1GbE Base-T Daughtercard',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'dell-refurbished-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'dell' LIMIT 1),
  'Dell PowerEdge R740 Rack Server',
  'dell-r740-rack-server',
  'IB-SRV-1003',
  'dell-r740-rack-server',
  'refurbished',
  'Dell enterprise server featuring Up to 2x 2nd Gen Intel Xeon Scalable Gold/Platinum (Up to 56 cores). Form factor: 2U 2-Socket Enterprise Workhorse.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  9500000,
  16000000,
  8,
  1,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r740-rack-server'),
  'images/dell-poweredge-r640.png',
  'Dell PowerEdge R740 Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r740-rack-server'),
  'Specifications',
  'Form Factor',
  '2U 2-Socket Enterprise Workhorse',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r740-rack-server'),
  'Specifications',
  'Processors',
  'Up to 2x 2nd Gen Intel Xeon Scalable Gold/Platinum (Up to 56 cores)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r740-rack-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 3TB DDR4 ECC Registered (24 DIMM slots)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r740-rack-server'),
  'Specifications',
  'Internal Storage',
  'Up to 16x 2.5',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r740-rack-server'),
  'Specifications',
  'RAID Controller',
  'PERC H740P 8GB Cache / H730P',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r740-rack-server'),
  'Specifications',
  'Power Supply',
  'Dual Hot-Plug 750W / 1100W Titanium/Platinum',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r740-rack-server'),
  'Specifications',
  'Network Interface',
  '4x 10GbE SFP+ / 10GBASE-T NDC',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'dell-refurbished-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'dell' LIMIT 1),
  'Dell PowerEdge R640 Rack Server',
  'dell-r640-rack-server',
  'IB-SRV-1004',
  'dell-r640-rack-server',
  'refurbished',
  'Dell enterprise server featuring Up to 2x Intel Xeon Scalable (Bronze, Silver, Gold, Platinum). Form factor: 1U Ultra-Dense 2-Socket Rack.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  9500000,
  16000000,
  8,
  1,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r640-rack-server'),
  'images/dell-poweredge-r640.png',
  'Dell PowerEdge R640 Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r640-rack-server'),
  'Specifications',
  'Form Factor',
  '1U Ultra-Dense 2-Socket Rack',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r640-rack-server'),
  'Specifications',
  'Processors',
  'Up to 2x Intel Xeon Scalable (Bronze, Silver, Gold, Platinum)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r640-rack-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 3TB DDR4 2933MT/s (24 DIMM slots)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r640-rack-server'),
  'Specifications',
  'Internal Storage',
  'Up to 10x 2.5',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r640-rack-server'),
  'Specifications',
  'RAID Controller',
  'PERC H730P, H740P or HBA330',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r640-rack-server'),
  'Specifications',
  'Power Supply',
  'Dual 495W / 750W / 1100W Hot-Plug Redundant',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r640-rack-server'),
  'Specifications',
  'Network Interface',
  'Broadcom / Intel 2x 10GbE + 2x 1GbE',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'dell-refurbished-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'dell' LIMIT 1),
  'Dell PowerEdge R430 Rack Server',
  'dell-r430-rack-server',
  'IB-SRV-1005',
  'dell-r430-rack-server',
  'refurbished',
  'Dell enterprise server featuring Up to 2x Intel Xeon E5-2600 v3 / v4 Series. Form factor: 1U Entry 2-Socket Rack.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  9500000,
  16000000,
  8,
  1,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r430-rack-server'),
  'images/dell-poweredge-r630.png',
  'Dell PowerEdge R430 Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r430-rack-server'),
  'Specifications',
  'Form Factor',
  '1U Entry 2-Socket Rack',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r430-rack-server'),
  'Specifications',
  'Processors',
  'Up to 2x Intel Xeon E5-2600 v3 / v4 Series',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r430-rack-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 384GB DDR4 (12 DIMM slots)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r430-rack-server'),
  'Specifications',
  'Internal Storage',
  'Up to 4x 3.5',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r430-rack-server'),
  'Specifications',
  'RAID Controller',
  'PERC H330, H730',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r430-rack-server'),
  'Specifications',
  'Power Supply',
  'Dual 450W / 550W Redundant Power Supplies',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r430-rack-server'),
  'Specifications',
  'Network Interface',
  '4x 1GbE Onboard LOM',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'dell-refurbished-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'dell' LIMIT 1),
  'Dell PowerEdge R530 Rack Server',
  'dell-r530-rack-server',
  'IB-SRV-1006',
  'dell-r530-rack-server',
  'refurbished',
  'Dell enterprise server featuring Up to 2x Intel Xeon E5-2600 v3 / v4 Series. Form factor: 2U High-Capacity Storage Rack.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  9500000,
  16000000,
  8,
  1,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r530-rack-server'),
  'images/dell-poweredge-r630.png',
  'Dell PowerEdge R530 Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r530-rack-server'),
  'Specifications',
  'Form Factor',
  '2U High-Capacity Storage Rack',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r530-rack-server'),
  'Specifications',
  'Processors',
  'Up to 2x Intel Xeon E5-2600 v3 / v4 Series',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r530-rack-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 384GB DDR4 (12 DIMM slots)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r530-rack-server'),
  'Specifications',
  'Internal Storage',
  'Up to 8x 3.5',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r530-rack-server'),
  'Specifications',
  'RAID Controller',
  'PERC H330, H730',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r530-rack-server'),
  'Specifications',
  'Power Supply',
  'Dual 495W / 750W Redundant Power Supplies',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r530-rack-server'),
  'Specifications',
  'Network Interface',
  '4x 1GbE Embedded LOM',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'dell-refurbished-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'dell' LIMIT 1),
  'Dell PowerEdge R630 Rack Server',
  'dell-r630-rack-server',
  'IB-SRV-1007',
  'dell-r630-rack-server',
  'refurbished',
  'Dell enterprise server featuring Up to 2x Intel Xeon E5-2600 v3 / v4 Processors (Up to 44 cores). Form factor: 1U Compact High-Performance Rack.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  9500000,
  16000000,
  8,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r630-rack-server'),
  'images/dell-poweredge-r630.png',
  'Dell PowerEdge R630 Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r630-rack-server'),
  'Specifications',
  'Form Factor',
  '1U Compact High-Performance Rack',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r630-rack-server'),
  'Specifications',
  'Processors',
  'Up to 2x Intel Xeon E5-2600 v3 / v4 Processors (Up to 44 cores)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r630-rack-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 1.5TB DDR4 2400MT/s (24 DIMM slots)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r630-rack-server'),
  'Specifications',
  'Internal Storage',
  'Up to 10x 2.5',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r630-rack-server'),
  'Specifications',
  'RAID Controller',
  'PERC H730 1GB NV Cache / H730P 2GB NV Cache',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r630-rack-server'),
  'Specifications',
  'Power Supply',
  'Dual 495W / 750W Platinum Hot-Plug PSUs',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r630-rack-server'),
  'Specifications',
  'Network Interface',
  '4x 1GbE or 2x 10GbE + 2x 1GbE NDC',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'dell-refurbished-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'dell' LIMIT 1),
  'Dell PowerEdge R730xd Rack Server',
  'dell-r730xd-rack-server',
  'IB-SRV-1008',
  'dell-r730xd-rack-server',
  'refurbished',
  'Dell enterprise server featuring Up to 2x Intel Xeon E5-2600 v3/v4 Family. Form factor: 2U High-Density Storage Server.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  9500000,
  16000000,
  8,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r730xd-rack-server'),
  'images/dell-poweredge-r630.png',
  'Dell PowerEdge R730xd Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r730xd-rack-server'),
  'Specifications',
  'Form Factor',
  '2U High-Density Storage Server',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r730xd-rack-server'),
  'Specifications',
  'Processors',
  'Up to 2x Intel Xeon E5-2600 v3/v4 Family',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r730xd-rack-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 1.5TB DDR4 (24 slots)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r730xd-rack-server'),
  'Specifications',
  'Internal Storage',
  'Up to 24x 2.5',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r730xd-rack-server'),
  'Specifications',
  'RAID Controller',
  'PERC H730P 2GB NV Cache',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r730xd-rack-server'),
  'Specifications',
  'Power Supply',
  'Dual Hot-Plug 750W / 1100W Redundant',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r730xd-rack-server'),
  'Specifications',
  'Network Interface',
  'Intel / Broadcom 10GbE SFP+ / 1GbE Daughtercard',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'dell-refurbished-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'dell' LIMIT 1),
  'Dell PowerEdge R730 Rack Server',
  'dell-r730-rack-server',
  'IB-SRV-1009',
  'dell-r730-rack-server',
  'refurbished',
  'Dell enterprise server featuring Up to 2x Intel Xeon E5-2600 v3/v4 Series (Up to 44 Cores). Form factor: 2U Dual Socket Workhorse Server.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  9500000,
  16000000,
  8,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r730-rack-server'),
  'images/dell-poweredge-r630.png',
  'Dell PowerEdge R730 Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r730-rack-server'),
  'Specifications',
  'Form Factor',
  '2U Dual Socket Workhorse Server',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r730-rack-server'),
  'Specifications',
  'Processors',
  'Up to 2x Intel Xeon E5-2600 v3/v4 Series (Up to 44 Cores)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r730-rack-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 1.5TB DDR4 Registered ECC',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r730-rack-server'),
  'Specifications',
  'Internal Storage',
  'Up to 16x 2.5',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r730-rack-server'),
  'Specifications',
  'RAID Controller',
  'PERC H730 1GB / H730P 2GB',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r730-rack-server'),
  'Specifications',
  'Power Supply',
  'Dual 750W Platinum Redundant Power Supplies',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r730-rack-server'),
  'Specifications',
  'Network Interface',
  '4x 1GbE / 2x 10GbE + 2x 1GbE NDC',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'dell-refurbished-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'dell' LIMIT 1),
  'Dell PowerEdge R820 Rack Server',
  'dell-r820-rack-server',
  'IB-SRV-1010',
  'dell-r820-rack-server',
  'refurbished',
  'Dell enterprise server featuring Up to 4x Intel Xeon E5-4600 / E5-4600 v2 Processors. Form factor: 2U 4-Socket Heavy Compute Server.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  9500000,
  16000000,
  8,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r820-rack-server'),
  'images/dell-poweredge-r630.png',
  'Dell PowerEdge R820 Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r820-rack-server'),
  'Specifications',
  'Form Factor',
  '2U 4-Socket Heavy Compute Server',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r820-rack-server'),
  'Specifications',
  'Processors',
  'Up to 4x Intel Xeon E5-4600 / E5-4600 v2 Processors',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r820-rack-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 1.5TB DDR3 (48 DIMM slots)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r820-rack-server'),
  'Specifications',
  'Internal Storage',
  'Up to 16x 2.5',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r820-rack-server'),
  'Specifications',
  'RAID Controller',
  'PERC H710P 1GB NV Cache',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r820-rack-server'),
  'Specifications',
  'Power Supply',
  'Dual 1100W Hot-Plug Redundant PSUs',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r820-rack-server'),
  'Specifications',
  'Network Interface',
  'Quad-port 1GbE / Dual 10GbE LOM',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'dell-refurbished-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'dell' LIMIT 1),
  'Dell PowerEdge R720xd Rack Server',
  'dell-r720xd-rack-server',
  'IB-SRV-1011',
  'dell-r720xd-rack-server',
  'refurbished',
  'Dell enterprise server featuring Up to 2x Intel Xeon E5-2600 / E5-2600 v2 Processors. Form factor: 2U High-Density Storage Rack.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  9500000,
  16000000,
  8,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r720xd-rack-server'),
  'images/dell-poweredge-r630.png',
  'Dell PowerEdge R720xd Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r720xd-rack-server'),
  'Specifications',
  'Form Factor',
  '2U High-Density Storage Rack',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r720xd-rack-server'),
  'Specifications',
  'Processors',
  'Up to 2x Intel Xeon E5-2600 / E5-2600 v2 Processors',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r720xd-rack-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 768GB DDR3 (24 slots)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r720xd-rack-server'),
  'Specifications',
  'Internal Storage',
  'Up to 24x 2.5',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r720xd-rack-server'),
  'Specifications',
  'RAID Controller',
  'PERC H710P Mini 1GB NV Cache',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r720xd-rack-server'),
  'Specifications',
  'Power Supply',
  'Dual 750W / 1100W PSUs',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r720xd-rack-server'),
  'Specifications',
  'Network Interface',
  '4x 1GbE Broadcom NDC',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'dell-refurbished-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'dell' LIMIT 1),
  'Dell PowerEdge R720 Rack Server',
  'dell-r720-rack-server',
  'IB-SRV-1012',
  'dell-r720-rack-server',
  'refurbished',
  'Dell enterprise server featuring Up to 2x Intel Xeon E5-2600 or v2 Series. Form factor: 2U 2-Socket Rack Server.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  9500000,
  16000000,
  8,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r720-rack-server'),
  'images/dell-poweredge-r630.png',
  'Dell PowerEdge R720 Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r720-rack-server'),
  'Specifications',
  'Form Factor',
  '2U 2-Socket Rack Server',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r720-rack-server'),
  'Specifications',
  'Processors',
  'Up to 2x Intel Xeon E5-2600 or v2 Series',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r720-rack-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 768GB DDR3 ECC',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r720-rack-server'),
  'Specifications',
  'Internal Storage',
  'Up to 16x 2.5',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r720-rack-server'),
  'Specifications',
  'RAID Controller',
  'PERC H710 512MB / H710P 1GB',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r720-rack-server'),
  'Specifications',
  'Power Supply',
  'Dual 750W Redundant Power Supplies',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r720-rack-server'),
  'Specifications',
  'Network Interface',
  'Quad 1GbE Broadcom / Intel',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'dell-refurbished-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'dell' LIMIT 1),
  'Dell PowerEdge R620 Rack Server',
  'dell-r620-rack-server',
  'IB-SRV-1013',
  'dell-r620-rack-server',
  'refurbished',
  'Dell enterprise server featuring Up to 2x Intel Xeon E5-2600 or v2 Series. Form factor: 1U Compact Rack Server.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  9500000,
  16000000,
  8,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r620-rack-server'),
  'images/dell-poweredge-r630.png',
  'Dell PowerEdge R620 Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r620-rack-server'),
  'Specifications',
  'Form Factor',
  '1U Compact Rack Server',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r620-rack-server'),
  'Specifications',
  'Processors',
  'Up to 2x Intel Xeon E5-2600 or v2 Series',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r620-rack-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 768GB DDR3 (24 slots)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r620-rack-server'),
  'Specifications',
  'Internal Storage',
  'Up to 10x 2.5',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r620-rack-server'),
  'Specifications',
  'RAID Controller',
  'PERC H710 512MB / H710P 1GB',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r620-rack-server'),
  'Specifications',
  'Power Supply',
  'Dual 495W / 750W PSUs',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r620-rack-server'),
  'Specifications',
  'Network Interface',
  '4x 1GbE LOM',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'huawei-refurbished-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'huawei' LIMIT 1),
  'Huawei FusionServer RH2288 V3 Rack Server',
  'huawei-fusionserver-rh2288-v3',
  'IB-SRV-1014',
  'huawei-fusionserver-rh2288-v3',
  'refurbished',
  'Huawei enterprise server featuring Up to 2x Intel Xeon E5-2600 v3/v4 Series. Form factor: 2U Dual Socket Rack Server.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  9500000,
  16000000,
  8,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'huawei-fusionserver-rh2288-v3'),
  'images/server-hero.png',
  'Huawei FusionServer RH2288 V3 Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'huawei-fusionserver-rh2288-v3'),
  'Specifications',
  'Form Factor',
  '2U Dual Socket Rack Server',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'huawei-fusionserver-rh2288-v3'),
  'Specifications',
  'Processors',
  'Up to 2x Intel Xeon E5-2600 v3/v4 Series',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'huawei-fusionserver-rh2288-v3'),
  'Specifications',
  'Memory Capacity',
  'Up to 1.5TB DDR4 (24 slots)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'huawei-fusionserver-rh2288-v3'),
  'Specifications',
  'Internal Storage',
  'Up to 12x 3.5',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'huawei-fusionserver-rh2288-v3'),
  'Specifications',
  'RAID Controller',
  'RAID 0, 1, 10, 5, 50, 6 with supercapacitor',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'huawei-fusionserver-rh2288-v3'),
  'Specifications',
  'Power Supply',
  'Dual 460W / 750W / 1200W Titanium/Platinum',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'huawei-fusionserver-rh2288-v3'),
  'Specifications',
  'Network Interface',
  'GE or 10GE Flexible NICs',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'hpe-refurbished-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'hpe' LIMIT 1),
  'HPE ProLiant DL360 Gen10 Rack Server',
  'hpe-dl360-g10-rack-server',
  'IB-SRV-1015',
  'hpe-dl360-g10-rack-server',
  'refurbished',
  'HPE enterprise server featuring Up to 2x 1st or 2nd Gen Intel Xeon Scalable (Up to 28 Cores each). Form factor: 1U 2-Socket Enterprise Dense Server.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  9500000,
  16000000,
  8,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360-g10-rack-server'),
  'images/hpe-dl380-gen10.png',
  'HPE ProLiant DL360 Gen10 Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360-g10-rack-server'),
  'Specifications',
  'Form Factor',
  '1U 2-Socket Enterprise Dense Server',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360-g10-rack-server'),
  'Specifications',
  'Processors',
  'Up to 2x 1st or 2nd Gen Intel Xeon Scalable (Up to 28 Cores each)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360-g10-rack-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 3TB DDR4 2933MT/s SmartMemory (24 DIMM slots)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360-g10-rack-server'),
  'Specifications',
  'Internal Storage',
  'Up to 10x SFF SAS/SATA/NVMe or 4x LFF bays',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360-g10-rack-server'),
  'Specifications',
  'RAID Controller',
  'HPE Smart Array P408i-a SR Gen10 2GB FBWC',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360-g10-rack-server'),
  'Specifications',
  'Power Supply',
  'Dual 500W / 800W / 1600W Flex Slot Platinum',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360-g10-rack-server'),
  'Specifications',
  'Network Interface',
  'Embedded 4x 1GbE + FlexibleLOM 10GbE/25GbE',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'hpe-refurbished-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'hpe' LIMIT 1),
  'HPE ProLiant DL380 Gen10 Rack Server',
  'hpe-dl380-g10-rack-server',
  'IB-SRV-1016',
  'hpe-dl380-g10-rack-server',
  'refurbished',
  'HPE enterprise server featuring Up to 2x Intel Xeon Scalable 8100/8200 series (Up to 56 cores total). Form factor: 2U 2-Socket Industry Benchmark Server.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  17500000,
  24000000,
  8,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380-g10-rack-server'),
  'images/hpe-dl380-gen10.png',
  'HPE ProLiant DL380 Gen10 Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380-g10-rack-server'),
  'Specifications',
  'Form Factor',
  '2U 2-Socket Industry Benchmark Server',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380-g10-rack-server'),
  'Specifications',
  'Processors',
  'Up to 2x Intel Xeon Scalable 8100/8200 series (Up to 56 cores total)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380-g10-rack-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 3TB DDR4 SmartMemory (24 slots)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380-g10-rack-server'),
  'Specifications',
  'Internal Storage',
  'Up to 24x 2.5',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380-g10-rack-server'),
  'Specifications',
  'RAID Controller',
  'HPE Smart Array P408i-a 2GB FBWC / P816i-a 4GB',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380-g10-rack-server'),
  'Specifications',
  'Power Supply',
  'Dual 500W / 800W / 1600W Platinum/Titanium',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380-g10-rack-server'),
  'Specifications',
  'Network Interface',
  'Embedded 4x 1GbE + Choice of 10GbE/25GbE FLOM',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'hpe-refurbished-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'hpe' LIMIT 1),
  'HPE ProLiant DL560 Gen9 Rack Server',
  'hpe-dl560-g9-rack-server',
  'IB-SRV-1017',
  'hpe-dl560-g9-rack-server',
  'refurbished',
  'HPE enterprise server featuring Up to 4x Intel Xeon E5-4600 v3/v4 Processors. Form factor: 2U 4-Socket High Density Compute.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  24500000,
  32000000,
  8,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl560-g9-rack-server'),
  'images/hpe-dl380-gen10.png',
  'HPE ProLiant DL560 Gen9 Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl560-g9-rack-server'),
  'Specifications',
  'Form Factor',
  '2U 4-Socket High Density Compute',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl560-g9-rack-server'),
  'Specifications',
  'Processors',
  'Up to 4x Intel Xeon E5-4600 v3/v4 Processors',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl560-g9-rack-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 3TB DDR4 SmartMemory (48 DIMM slots)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl560-g9-rack-server'),
  'Specifications',
  'Internal Storage',
  'Up to 24x SFF SAS/SATA drives',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl560-g9-rack-server'),
  'Specifications',
  'RAID Controller',
  'HPE Smart Array P440ar 2GB FBWC',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl560-g9-rack-server'),
  'Specifications',
  'Power Supply',
  'Dual/Quad 1200W Common Slot Platinum PSUs',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl560-g9-rack-server'),
  'Specifications',
  'Network Interface',
  'HPE FlexibleLOM 4x 1GbE / 2x 10GbE',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'hpe-refurbished-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'hpe' LIMIT 1),
  'HPE ProLiant DL380 Gen9 Rack Server',
  'hpe-dl380-g9-rack-server',
  'IB-SRV-1018',
  'hpe-dl380-g9-rack-server',
  'refurbished',
  'HPE enterprise server featuring Up to 2x Intel Xeon E5-2600 v3/v4 Processors. Form factor: 2U 2-Socket Enterprise Server.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  9500000,
  16000000,
  8,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380-g9-rack-server'),
  'images/hpe-dl380-gen10.png',
  'HPE ProLiant DL380 Gen9 Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380-g9-rack-server'),
  'Specifications',
  'Form Factor',
  '2U 2-Socket Enterprise Server',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380-g9-rack-server'),
  'Specifications',
  'Processors',
  'Up to 2x Intel Xeon E5-2600 v3/v4 Processors',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380-g9-rack-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 1.5TB DDR4 2400MT/s (24 slots)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380-g9-rack-server'),
  'Specifications',
  'Internal Storage',
  'Up to 24x SFF or 12x LFF drives',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380-g9-rack-server'),
  'Specifications',
  'RAID Controller',
  'HPE Smart Array P440ar 2GB FBWC',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380-g9-rack-server'),
  'Specifications',
  'Power Supply',
  'Dual 500W / 800W / 1400W Flex Slot Platinum',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380-g9-rack-server'),
  'Specifications',
  'Network Interface',
  '4x 1GbE embedded + FlexibleLOM',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'hpe-refurbished-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'hpe' LIMIT 1),
  'HPE ProLiant DL360 Gen9 Rack Server',
  'hpe-dl360-g9-rack-server',
  'IB-SRV-1019',
  'hpe-dl360-g9-rack-server',
  'refurbished',
  'HPE enterprise server featuring Up to 2x Intel Xeon E5-2600 v3/v4 Processors. Form factor: 1U Dual Socket Server.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  9500000,
  16000000,
  8,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360-g9-rack-server'),
  'images/hpe-dl380-gen10.png',
  'HPE ProLiant DL360 Gen9 Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360-g9-rack-server'),
  'Specifications',
  'Form Factor',
  '1U Dual Socket Server',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360-g9-rack-server'),
  'Specifications',
  'Processors',
  'Up to 2x Intel Xeon E5-2600 v3/v4 Processors',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360-g9-rack-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 1.5TB DDR4 (24 slots)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360-g9-rack-server'),
  'Specifications',
  'Internal Storage',
  'Up to 8x SFF SAS/SATA drives',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360-g9-rack-server'),
  'Specifications',
  'RAID Controller',
  'HPE Smart Array P440ar 2GB FBWC',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360-g9-rack-server'),
  'Specifications',
  'Power Supply',
  'Dual 500W / 800W Flex Slot',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360-g9-rack-server'),
  'Specifications',
  'Network Interface',
  '4x 1GbE embedded',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'hpe-refurbished-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'hpe' LIMIT 1),
  'HPE ProLiant DL180 Gen9 Rack Server',
  'hpe-dl180-g9-rack-server',
  'IB-SRV-1020',
  'hpe-dl180-g9-rack-server',
  'refurbished',
  'HPE enterprise server featuring Up to 2x Intel Xeon E5-2600 v3/v4 Processors. Form factor: 2U 2-Socket Cost-Effective Server.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  9500000,
  16000000,
  8,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl180-g9-rack-server'),
  'images/hpe-dl380-gen10.png',
  'HPE ProLiant DL180 Gen9 Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl180-g9-rack-server'),
  'Specifications',
  'Form Factor',
  '2U 2-Socket Cost-Effective Server',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl180-g9-rack-server'),
  'Specifications',
  'Processors',
  'Up to 2x Intel Xeon E5-2600 v3/v4 Processors',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl180-g9-rack-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 512GB DDR4 (16 slots)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl180-g9-rack-server'),
  'Specifications',
  'Internal Storage',
  'Up to 12x LFF or 16x SFF drive bays',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl180-g9-rack-server'),
  'Specifications',
  'RAID Controller',
  'HPE Dynamic Smart Array B140i / H240 HBA',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl180-g9-rack-server'),
  'Specifications',
  'Power Supply',
  'Dual 550W / 900W Redundant PSUs',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl180-g9-rack-server'),
  'Specifications',
  'Network Interface',
  '2x 1GbE embedded',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'hpe-refurbished-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'hpe' LIMIT 1),
  'HPE ProLiant DL380p Gen8 Rack Server',
  'hpe-dl380p-g8-rack-server',
  'IB-SRV-1021',
  'hpe-dl380p-g8-rack-server',
  'refurbished',
  'HPE enterprise server featuring Up to 2x Intel Xeon E5-2600 or v2 Series. Form factor: 2U 2-Socket Classic Rack Server.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  9500000,
  16000000,
  8,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380p-g8-rack-server'),
  'images/hpe-dl380-gen10.png',
  'HPE ProLiant DL380p Gen8 Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380p-g8-rack-server'),
  'Specifications',
  'Form Factor',
  '2U 2-Socket Classic Rack Server',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380p-g8-rack-server'),
  'Specifications',
  'Processors',
  'Up to 2x Intel Xeon E5-2600 or v2 Series',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380p-g8-rack-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 768GB DDR3 (24 slots)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380p-g8-rack-server'),
  'Specifications',
  'Internal Storage',
  'Up to 16x SFF or 8x LFF SAS/SATA bays',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380p-g8-rack-server'),
  'Specifications',
  'RAID Controller',
  'HPE Smart Array P420i 1GB/2GB FBWC',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380p-g8-rack-server'),
  'Specifications',
  'Power Supply',
  'Dual 460W / 750W Common Slot Platinum',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380p-g8-rack-server'),
  'Specifications',
  'Network Interface',
  '4x 1GbE FlexibleLOM',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'hpe-refurbished-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'hpe' LIMIT 1),
  'HPE ProLiant DL360p Gen8 Rack Server',
  'hpe-dl360p-g8-rack-server',
  'IB-SRV-1022',
  'hpe-dl360p-g8-rack-server',
  'refurbished',
  'HPE enterprise server featuring Up to 2x Intel Xeon E5-2600 or v2 Series. Form factor: 1U Dual Socket Server.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  9500000,
  16000000,
  8,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360p-g8-rack-server'),
  'images/hpe-dl380-gen10.png',
  'HPE ProLiant DL360p Gen8 Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360p-g8-rack-server'),
  'Specifications',
  'Form Factor',
  '1U Dual Socket Server',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360p-g8-rack-server'),
  'Specifications',
  'Processors',
  'Up to 2x Intel Xeon E5-2600 or v2 Series',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360p-g8-rack-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 768GB DDR3 (24 slots)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360p-g8-rack-server'),
  'Specifications',
  'Internal Storage',
  'Up to 8x SFF bays',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360p-g8-rack-server'),
  'Specifications',
  'RAID Controller',
  'HPE Smart Array P420i 1GB/2GB',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360p-g8-rack-server'),
  'Specifications',
  'Power Supply',
  'Dual 460W Platinum PSUs',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360p-g8-rack-server'),
  'Specifications',
  'Network Interface',
  '4x 1GbE FlexibleLOM',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'cisco-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'cisco' LIMIT 1),
  'Cisco UCS C240 M6 Rack Server',
  'cisco-c240-m6-rack-server',
  'IB-SRV-1023',
  'cisco-c240-m6-rack-server',
  'refurbished',
  'Cisco enterprise server featuring Up to 2x 3rd Gen Intel Xeon Scalable Processors (Up to 40 cores/socket). Form factor: 2U 2-Socket Next-Gen Enterprise Server.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  9500000,
  16000000,
  8,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c240-m6-rack-server'),
  'images/cisco-ucs-c240-m3.png',
  'Cisco UCS C240 M6 Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c240-m6-rack-server'),
  'Specifications',
  'Form Factor',
  '2U 2-Socket Next-Gen Enterprise Server',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c240-m6-rack-server'),
  'Specifications',
  'Processors',
  'Up to 2x 3rd Gen Intel Xeon Scalable Processors (Up to 40 cores/socket)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c240-m6-rack-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 8TB DDR4 3200MHz (32 DIMMs) with Intel Optane Support',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c240-m6-rack-server'),
  'Specifications',
  'Internal Storage',
  'Up to 28x SFF SAS/SATA/NVMe drives',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c240-m6-rack-server'),
  'Specifications',
  'RAID Controller',
  'Cisco 12G Modular SAS RAID Controller 4GB cache',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c240-m6-rack-server'),
  'Specifications',
  'Power Supply',
  'Dual 1050W / 1600W / 2300W Titanium/Platinum',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c240-m6-rack-server'),
  'Specifications',
  'Network Interface',
  'Modular VIC 1467 quad-port 10/25G or 1477 dual-port 40/100G',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'cisco-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'cisco' LIMIT 1),
  'Cisco UCS C220 M5 Rack Server',
  'cisco-c220-m5-rack-server',
  'IB-SRV-1024',
  'cisco-c220-m5-rack-server',
  'refurbished',
  'Cisco enterprise server featuring Up to 2x 1st or 2nd Gen Intel Xeon Scalable (Up to 28 cores each). Form factor: 1U 2-Socket High Density Computing.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  9500000,
  16000000,
  8,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c220-m5-rack-server'),
  'images/cisco-ucs-c240-m3.png',
  'Cisco UCS C220 M5 Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c220-m5-rack-server'),
  'Specifications',
  'Form Factor',
  '1U 2-Socket High Density Computing',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c220-m5-rack-server'),
  'Specifications',
  'Processors',
  'Up to 2x 1st or 2nd Gen Intel Xeon Scalable (Up to 28 cores each)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c220-m5-rack-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 3TB DDR4 2933MT/s (24 DIMMs)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c220-m5-rack-server'),
  'Specifications',
  'Internal Storage',
  'Up to 10x 2.5',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c220-m5-rack-server'),
  'Specifications',
  'RAID Controller',
  'Cisco 12G SAS Modular RAID Controller 2GB/4GB',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c220-m5-rack-server'),
  'Specifications',
  'Power Supply',
  'Dual 770W / 1050W Platinum Hot-Plug PSUs',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c220-m5-rack-server'),
  'Specifications',
  'Network Interface',
  'Cisco UCS VIC 1457 quad-port 10/25G or VIC 1387 dual 40G',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'cisco-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'cisco' LIMIT 1),
  'Cisco UCS C240 M4 Rack Server',
  'cisco-c240-m4-rack-server',
  'IB-SRV-1025',
  'cisco-c240-m4-rack-server',
  'refurbished',
  'Cisco enterprise server featuring Up to 2x Intel Xeon E5-2600 v3/v4 Processors. Form factor: 2U 2-Socket Enterprise Rack Server.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  9500000,
  16000000,
  8,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c240-m4-rack-server'),
  'images/cisco-ucs-c240-m3.png',
  'Cisco UCS C240 M4 Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c240-m4-rack-server'),
  'Specifications',
  'Form Factor',
  '2U 2-Socket Enterprise Rack Server',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c240-m4-rack-server'),
  'Specifications',
  'Processors',
  'Up to 2x Intel Xeon E5-2600 v3/v4 Processors',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c240-m4-rack-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 1.5TB DDR4 (24 slots)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c240-m4-rack-server'),
  'Specifications',
  'Internal Storage',
  'Up to 24x SFF or 12x LFF drives',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c240-m4-rack-server'),
  'Specifications',
  'RAID Controller',
  'Cisco 12G SAS RAID Controller 2GB FBWC',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c240-m4-rack-server'),
  'Specifications',
  'Power Supply',
  'Dual 650W / 1200W / 1400W Platinum PSUs',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c240-m4-rack-server'),
  'Specifications',
  'Network Interface',
  'Cisco UCS VIC 1227 / VIC 1387 Modular LOM',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'cisco-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'cisco' LIMIT 1),
  'Cisco UCS C220 M4 Rack Server',
  'cisco-c220-m4-rack-server',
  'IB-SRV-1026',
  'cisco-c220-m4-rack-server',
  'refurbished',
  'Cisco enterprise server featuring Up to 2x Intel Xeon E5-2600 v3/v4 Processors. Form factor: 1U 2-Socket Server.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  9500000,
  16000000,
  8,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c220-m4-rack-server'),
  'images/cisco-ucs-c240-m3.png',
  'Cisco UCS C220 M4 Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c220-m4-rack-server'),
  'Specifications',
  'Form Factor',
  '1U 2-Socket Server',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c220-m4-rack-server'),
  'Specifications',
  'Processors',
  'Up to 2x Intel Xeon E5-2600 v3/v4 Processors',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c220-m4-rack-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 1.5TB DDR4 (24 slots)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c220-m4-rack-server'),
  'Specifications',
  'Internal Storage',
  'Up to 8x SFF SAS/SATA drives',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c220-m4-rack-server'),
  'Specifications',
  'RAID Controller',
  'Cisco 12G SAS Modular RAID Controller',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c220-m4-rack-server'),
  'Specifications',
  'Power Supply',
  'Dual 770W Platinum PSUs',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c220-m4-rack-server'),
  'Specifications',
  'Network Interface',
  'Dual 1GbE embedded + Modular VIC',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'cisco-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'cisco' LIMIT 1),
  'Cisco UCS C240 M3 Rack Server',
  'cisco-c240-m3-rack-server',
  'IB-SRV-1027',
  'cisco-c240-m3-rack-server',
  'refurbished',
  'Cisco enterprise server featuring Up to 2x Intel Xeon E5-2600 or v2 Series. Form factor: 2U Dual Socket Rack Server.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  9500000,
  16000000,
  8,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c240-m3-rack-server'),
  'images/cisco-ucs-c240-m3.png',
  'Cisco UCS C240 M3 Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c240-m3-rack-server'),
  'Specifications',
  'Form Factor',
  '2U Dual Socket Rack Server',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c240-m3-rack-server'),
  'Specifications',
  'Processors',
  'Up to 2x Intel Xeon E5-2600 or v2 Series',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c240-m3-rack-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 768GB DDR3 (24 slots)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c240-m3-rack-server'),
  'Specifications',
  'Internal Storage',
  'Up to 16x SFF or 12x LFF bays',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c240-m3-rack-server'),
  'Specifications',
  'RAID Controller',
  'LSI MegaRAID SAS 9271CV-8i',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c240-m3-rack-server'),
  'Specifications',
  'Power Supply',
  'Dual 650W / 1200W PSUs',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c240-m3-rack-server'),
  'Specifications',
  'Network Interface',
  'Dual 1GbE LOM + VIC card',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'cisco-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'cisco' LIMIT 1),
  'Cisco UCS C220 M3 Rack Server',
  'cisco-c220-m3-rack-server',
  'IB-SRV-1028',
  'cisco-c220-m3-rack-server',
  'refurbished',
  'Cisco enterprise server featuring Up to 2x Intel Xeon E5-2600 or v2 Series. Form factor: 1U Compact Server.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  9500000,
  16000000,
  8,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c220-m3-rack-server'),
  'images/cisco-ucs-c240-m3.png',
  'Cisco UCS C220 M3 Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c220-m3-rack-server'),
  'Specifications',
  'Form Factor',
  '1U Compact Server',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c220-m3-rack-server'),
  'Specifications',
  'Processors',
  'Up to 2x Intel Xeon E5-2600 or v2 Series',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c220-m3-rack-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 512GB DDR3',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c220-m3-rack-server'),
  'Specifications',
  'Internal Storage',
  'Up to 8x SFF bays',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c220-m3-rack-server'),
  'Specifications',
  'RAID Controller',
  'Cisco UCS SAS RAID Controller',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c220-m3-rack-server'),
  'Specifications',
  'Power Supply',
  'Dual 650W PSUs',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-c220-m3-rack-server'),
  'Specifications',
  'Network Interface',
  'Dual 1GbE onboard',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'hpe-new-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'hpe' LIMIT 1),
  'HPE ProLiant DL380 Gen12 Rack Server',
  'hpe-dl380-g12-rack-server',
  'IB-SRV-1029',
  'hpe-dl380-g12-rack-server',
  'new',
  'HPE enterprise server featuring Latest Intel Xeon 6th Gen / 5th Gen Scalable CPUs. Form factor: 2U Dual Socket Flagship Server.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  38500000,
  45000000,
  8,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380-g12-rack-server'),
  'images/hpe-dl380-gen10.png',
  'HPE ProLiant DL380 Gen12 Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380-g12-rack-server'),
  'Specifications',
  'Form Factor',
  '2U Dual Socket Flagship Server',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380-g12-rack-server'),
  'Specifications',
  'Processors',
  'Latest Intel Xeon 6th Gen / 5th Gen Scalable CPUs',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380-g12-rack-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 8TB DDR5 ECC Registered (32 DIMM slots)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380-g12-rack-server'),
  'Specifications',
  'Internal Storage',
  'Up to 30x SFF NVMe/SAS or 12x LFF SAS/SATA',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380-g12-rack-server'),
  'Specifications',
  'RAID Controller',
  'HPE MR416i-p / SR932i-p Gen11/Gen12 Tri-Mode Controller',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380-g12-rack-server'),
  'Specifications',
  'Power Supply',
  'Dual 1000W / 1800W Titanium Hot-Plug PSUs',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380-g12-rack-server'),
  'Specifications',
  'Network Interface',
  'Embedded OCP 3.0 Gen5 networking',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'hpe-new-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'hpe' LIMIT 1),
  'HPE ProLiant DL360 Gen12 Rack Server',
  'hpe-dl360-g12-rack-server',
  'IB-SRV-1030',
  'hpe-dl360-g12-rack-server',
  'new',
  'HPE enterprise server featuring Latest Intel Xeon Scalable Multi-Core Processors. Form factor: 1U Ultra-Dense Dual Socket Server.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  38500000,
  45000000,
  8,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360-g12-rack-server'),
  'images/hpe-dl380-gen10.png',
  'HPE ProLiant DL360 Gen12 Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360-g12-rack-server'),
  'Specifications',
  'Form Factor',
  '1U Ultra-Dense Dual Socket Server',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360-g12-rack-server'),
  'Specifications',
  'Processors',
  'Latest Intel Xeon Scalable Multi-Core Processors',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360-g12-rack-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 8TB DDR5 (32 DIMM slots)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360-g12-rack-server'),
  'Specifications',
  'Internal Storage',
  'Up to 10x SFF EDSFF / NVMe Gen5 drives',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360-g12-rack-server'),
  'Specifications',
  'RAID Controller',
  'Tri-Mode PCIe Gen5 RAID',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360-g12-rack-server'),
  'Specifications',
  'Power Supply',
  'Dual 800W / 1600W Flex Slot Platinum/Titanium',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360-g12-rack-server'),
  'Specifications',
  'Network Interface',
  'OCP 3.0 high-bandwidth networking',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'hpe-new-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'hpe' LIMIT 1),
  'HPE ProLiant DL380 Gen11 Rack Server',
  'hpe-dl380-g11-rack-server',
  'IB-SRV-1031',
  'hpe-dl380-g11-rack-server',
  'new',
  'HPE enterprise server featuring 4th & 5th Gen Intel Xeon Scalable Processors (Up to 64 cores each). Form factor: 2U 2-Socket Industry Benchmark Server.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  38500000,
  45000000,
  8,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380-g11-rack-server'),
  'images/hpe-dl380-gen10.png',
  'HPE ProLiant DL380 Gen11 Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380-g11-rack-server'),
  'Specifications',
  'Form Factor',
  '2U 2-Socket Industry Benchmark Server',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380-g11-rack-server'),
  'Specifications',
  'Processors',
  '4th & 5th Gen Intel Xeon Scalable Processors (Up to 64 cores each)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380-g11-rack-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 8TB DDR5 4800/5600 MT/s (32 DIMM slots)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380-g11-rack-server'),
  'Specifications',
  'Internal Storage',
  'Up to 24x SFF or 12x LFF SAS/SATA/NVMe drives',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380-g11-rack-server'),
  'Specifications',
  'RAID Controller',
  'HPE Smart Array MR416i-p Gen11 8GB Cache',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380-g11-rack-server'),
  'Specifications',
  'Power Supply',
  'Dual 800W / 1000W / 1600W Flex Slot Titanium PSUs',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl380-g11-rack-server'),
  'Specifications',
  'Network Interface',
  'Choice of OCP 3.0 NICs (10G/25G/100G)',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'hpe-new-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'hpe' LIMIT 1),
  'HPE ProLiant DL360 Gen11 Rack Server',
  'hpe-dl360-g11-rack-server',
  'IB-SRV-1032',
  'hpe-dl360-g11-rack-server',
  'new',
  'HPE enterprise server featuring 4th & 5th Gen Intel Xeon Scalable Processors. Form factor: 1U Dual Socket High Performance Server.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  38500000,
  45000000,
  8,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360-g11-rack-server'),
  'images/hpe-dl380-gen10.png',
  'HPE ProLiant DL360 Gen11 Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360-g11-rack-server'),
  'Specifications',
  'Form Factor',
  '1U Dual Socket High Performance Server',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360-g11-rack-server'),
  'Specifications',
  'Processors',
  '4th & 5th Gen Intel Xeon Scalable Processors',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360-g11-rack-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 8TB DDR5 (32 slots)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360-g11-rack-server'),
  'Specifications',
  'Internal Storage',
  'Up to 10x SFF NVMe Gen5 drives',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360-g11-rack-server'),
  'Specifications',
  'RAID Controller',
  'HPE MR408i-o Gen11 Tri-Mode',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360-g11-rack-server'),
  'Specifications',
  'Power Supply',
  'Dual 800W / 1000W Platinum/Titanium',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl360-g11-rack-server'),
  'Specifications',
  'Network Interface',
  'OCP 3.0 NIC slot',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'hpe-refurbished-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'hpe' LIMIT 1),
  'HPE ProLiant DL385 Gen10 Plus v2 Server',
  'hpe-dl385-g10-plus-v2-server',
  'IB-SRV-1033',
  'hpe-dl385-g10-plus-v2-server',
  'refurbished',
  'HPE enterprise server featuring Up to 2x 3rd Gen AMD EPYC 7003 Series (Up to 128 Cores / 256 Threads). Form factor: 2U 2-Socket AMD EPYC High-Compute Server.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  9500000,
  16000000,
  8,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl385-g10-plus-v2-server'),
  'images/hpe-dl385.png',
  'HPE ProLiant DL385 Gen10 Plus v2 Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl385-g10-plus-v2-server'),
  'Specifications',
  'Form Factor',
  '2U 2-Socket AMD EPYC High-Compute Server',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl385-g10-plus-v2-server'),
  'Specifications',
  'Processors',
  'Up to 2x 3rd Gen AMD EPYC 7003 Series (Up to 128 Cores / 256 Threads)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl385-g10-plus-v2-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 8TB DDR4 3200MT/s (32 DIMM slots)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl385-g10-plus-v2-server'),
  'Specifications',
  'Internal Storage',
  'Up to 24x SFF or 12x LFF SAS/SATA/NVMe drives',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl385-g10-plus-v2-server'),
  'Specifications',
  'RAID Controller',
  'HPE Smart Array P408i-a / P816i-a Gen10',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl385-g10-plus-v2-server'),
  'Specifications',
  'Power Supply',
  'Dual 800W / 1600W Flex Slot Titanium PSUs',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'hpe-dl385-g10-plus-v2-server'),
  'Specifications',
  'Network Interface',
  'OCP 3.0 slot + FlexibleLOM options',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'dell-new-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'dell' LIMIT 1),
  'Dell PowerEdge R760 Rack Server',
  'dell-r760-rack-server',
  'IB-SRV-1034',
  'dell-r760-rack-server',
  'new',
  'Dell enterprise server featuring Up to 2x 4th or 5th Gen Intel Xeon Scalable Processors (Up to 64 cores each). Form factor: 2U 2-Socket Enterprise Workhorse.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  38500000,
  45000000,
  8,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r760-rack-server'),
  'images/dell-poweredge-r640.png',
  'Dell PowerEdge R760 Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r760-rack-server'),
  'Specifications',
  'Form Factor',
  '2U 2-Socket Enterprise Workhorse',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r760-rack-server'),
  'Specifications',
  'Processors',
  'Up to 2x 4th or 5th Gen Intel Xeon Scalable Processors (Up to 64 cores each)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r760-rack-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 8TB DDR5 5600MT/s (32 DIMM slots)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r760-rack-server'),
  'Specifications',
  'Internal Storage',
  'Up to 24x 2.5',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r760-rack-server'),
  'Specifications',
  'RAID Controller',
  'PERC H965i 8GB NV Cache PCIe Gen5',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r760-rack-server'),
  'Specifications',
  'Power Supply',
  'Dual Hot-Plug 800W / 1400W / 2400W Titanium/Platinum',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r760-rack-server'),
  'Specifications',
  'Network Interface',
  'OCP 3.0 Slot (10GbE / 25GbE / 100GbE options)',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'dell-new-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'dell' LIMIT 1),
  'Dell PowerEdge R660 Rack Server',
  'dell-r660-rack-server',
  'IB-SRV-1035',
  'dell-r660-rack-server',
  'new',
  'Dell enterprise server featuring Up to 2x 4th or 5th Gen Intel Xeon Scalable Processors. Form factor: 1U 2-Socket High Density Server.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  21000000,
  28000000,
  8,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r660-rack-server'),
  'images/dell-poweredge-r640.png',
  'Dell PowerEdge R660 Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r660-rack-server'),
  'Specifications',
  'Form Factor',
  '1U 2-Socket High Density Server',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r660-rack-server'),
  'Specifications',
  'Processors',
  'Up to 2x 4th or 5th Gen Intel Xeon Scalable Processors',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r660-rack-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 8TB DDR5 (32 DIMM slots)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r660-rack-server'),
  'Specifications',
  'Internal Storage',
  'Up to 10x 2.5',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r660-rack-server'),
  'Specifications',
  'RAID Controller',
  'PERC H965i, PERC H755, H355',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r660-rack-server'),
  'Specifications',
  'Power Supply',
  'Dual 800W / 1400W Titanium PSUs',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r660-rack-server'),
  'Specifications',
  'Network Interface',
  'OCP 3.0 card slot',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'dell-refurbished-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'dell' LIMIT 1),
  'Dell PowerEdge R750 Rack Server',
  'dell-r750-rack-server',
  'IB-SRV-1036',
  'dell-r750-rack-server',
  'refurbished',
  'Dell enterprise server featuring Up to 2x 3rd Gen Intel Xeon Scalable Processors (Up to 40 cores/socket). Form factor: 2U 2-Socket Enterprise Server.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  9500000,
  16000000,
  8,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r750-rack-server'),
  'images/dell-poweredge-r640.png',
  'Dell PowerEdge R750 Rack Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r750-rack-server'),
  'Specifications',
  'Form Factor',
  '2U 2-Socket Enterprise Server',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r750-rack-server'),
  'Specifications',
  'Processors',
  'Up to 2x 3rd Gen Intel Xeon Scalable Processors (Up to 40 cores/socket)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r750-rack-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 8TB DDR4 3200MT/s (32 DIMM slots)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r750-rack-server'),
  'Specifications',
  'Internal Storage',
  'Up to 24x 2.5',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r750-rack-server'),
  'Specifications',
  'RAID Controller',
  'PERC H755 8GB NV Cache / H745',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r750-rack-server'),
  'Specifications',
  'Power Supply',
  'Dual 800W / 1400W Platinum/Titanium',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-r750-rack-server'),
  'Specifications',
  'Network Interface',
  'OCP 3.0 Slot + 2x 1GbE LOM',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'dell-new-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'dell' LIMIT 1),
  'Dell PowerEdge T560 Tower Server',
  'dell-t560-tower-server',
  'IB-SRV-1037',
  'dell-t560-tower-server',
  'new',
  'Dell enterprise server featuring Up to 2x 4th Gen Intel Xeon Scalable Processors. Form factor: Tower (Rackable 5U Chassis).',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  21000000,
  28000000,
  8,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-t560-tower-server'),
  'images/dell-workstations.png',
  'Dell PowerEdge T560 Tower Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-t560-tower-server'),
  'Specifications',
  'Form Factor',
  'Tower (Rackable 5U Chassis)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-t560-tower-server'),
  'Specifications',
  'Processors',
  'Up to 2x 4th Gen Intel Xeon Scalable Processors',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-t560-tower-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 1TB DDR5 (16 DIMM slots)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-t560-tower-server'),
  'Specifications',
  'Internal Storage',
  'Up to 12x 3.5',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-t560-tower-server'),
  'Specifications',
  'RAID Controller',
  'PERC H755, H355',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-t560-tower-server'),
  'Specifications',
  'Power Supply',
  'Dual Hot-Plug 800W / 1400W Titanium PSUs',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-t560-tower-server'),
  'Specifications',
  'Network Interface',
  '2x 1GbE Base-T LOM + PCIe expansion',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'dell-new-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'dell' LIMIT 1),
  'Dell PowerEdge T360 Tower Server',
  'dell-t360-tower-server',
  'IB-SRV-1038',
  'dell-t360-tower-server',
  'new',
  'Dell enterprise server featuring Intel Xeon E-2400 Series Processors (Up to 8 Cores). Form factor: Single-Socket Tower Server for SMBs & Remote Offices.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  21000000,
  28000000,
  8,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-t360-tower-server'),
  'images/dell-workstations.png',
  'Dell PowerEdge T360 Tower Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-t360-tower-server'),
  'Specifications',
  'Form Factor',
  'Single-Socket Tower Server for SMBs & Remote Offices',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-t360-tower-server'),
  'Specifications',
  'Processors',
  'Intel Xeon E-2400 Series Processors (Up to 8 Cores)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-t360-tower-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 128GB DDR5 4400MT/s (4 DIMM slots)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-t360-tower-server'),
  'Specifications',
  'Internal Storage',
  'Up to 8x 3.5',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-t360-tower-server'),
  'Specifications',
  'RAID Controller',
  'PERC H755, H355',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-t360-tower-server'),
  'Specifications',
  'Power Supply',
  'Single or Dual Hot-Plug 600W Platinum',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-t360-tower-server'),
  'Specifications',
  'Network Interface',
  '2x 1GbE embedded ports',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'dell-new-servers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'dell' LIMIT 1),
  'Dell PowerEdge T160 Tower Server',
  'dell-t160-tower-server',
  'IB-SRV-1039',
  'dell-t160-tower-server',
  'new',
  'Dell enterprise server featuring Intel Xeon E-2400 Series Processors. Form factor: Ultra-Compact Single-Socket Tower.',
  'Enterprise-grade compute platform designed for demanding datacenter workloads, virtualization, database clustering, and high reliability. Fully inspected, stress-tested, and backed by a comprehensive hardware warranty.',
  21000000,
  28000000,
  8,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-t160-tower-server'),
  'images/dell-workstations.png',
  'Dell PowerEdge T160 Tower Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-t160-tower-server'),
  'Specifications',
  'Form Factor',
  'Ultra-Compact Single-Socket Tower',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-t160-tower-server'),
  'Specifications',
  'Processors',
  'Intel Xeon E-2400 Series Processors',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-t160-tower-server'),
  'Specifications',
  'Memory Capacity',
  'Up to 128GB DDR5 UDIMM',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-t160-tower-server'),
  'Specifications',
  'Internal Storage',
  'Up to 3x 3.5',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-t160-tower-server'),
  'Specifications',
  'RAID Controller',
  'PERC H355, S160 software RAID',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-t160-tower-server'),
  'Specifications',
  'Power Supply',
  '300W Bronze or 500W Platinum cabled PSU',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-t160-tower-server'),
  'Specifications',
  'Network Interface',
  '2x 1GbE ports',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'firewalls' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'fortinet' LIMIT 1),
  'Fortinet FortiGate 60F Next-Gen Firewall',
  'fortinet-fortigate-60f-next-gen-firewall',
  'IB-NET-2001',
  'fortinet-fortigate-60f-next-ge',
  'refurbished',
  'Fortinet enterprise networking solution. 10x GE RJ45 ports (including 7x Internal, 2x WAN, 1x DMZ) • 1 Gbps Threat Protection • 700 Mbps SSL Inspection',
  'High reliability network hardware built for 24x7 continuous datacenter and campus deployment. Features: 10x GE RJ45 ports (including 7x Internal, 2x WAN, 1x DMZ) • 1 Gbps Threat Protection • 700 Mbps SSL Inspection.',
  4850000,
  6500000,
  15,
  1,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'fortinet-fortigate-60f-next-gen-firewall'),
  'images/server-hud.png',
  'Fortinet FortiGate 60F Next-Gen Firewall',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'fortinet-fortigate-60f-next-gen-firewall'),
  'Specifications',
  'Brand',
  'Fortinet',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'fortinet-fortigate-60f-next-gen-firewall'),
  'Specifications',
  'Form Factor',
  'Desktop / Rackmount Kit',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'fortinet-fortigate-60f-next-gen-firewall'),
  'Specifications',
  'Specifications',
  '10x GE RJ45 ports (including 7x Internal, 2x WAN, 1x DMZ) • 1 Gbps Threat Protection • 700 Mbps SSL Inspection',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'fortinet-fortigate-60f-next-gen-firewall'),
  'Specifications',
  'Condition',
  'New / Certified Refurbished',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'firewalls' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'fortinet' LIMIT 1),
  'Fortinet FortiGate 100F Enterprise Firewall',
  'fortinet-fortigate-100f-enterprise-firewall',
  'IB-NET-2002',
  'fortinet-fortigate-100f-enterp',
  'new',
  'Fortinet enterprise networking solution. 2x 10GE SFP+ FortiLink slots, 4x GE SFP, 16x GE RJ45 • 1 Gbps Threat Protection • Dual Redundant Power',
  'High reliability network hardware built for 24x7 continuous datacenter and campus deployment. Features: 2x 10GE SFP+ FortiLink slots, 4x GE SFP, 16x GE RJ45 • 1 Gbps Threat Protection • Dual Redundant Power.',
  4850000,
  6500000,
  15,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'fortinet-fortigate-100f-enterprise-firewall'),
  'images/server-hud.png',
  'Fortinet FortiGate 100F Enterprise Firewall',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'fortinet-fortigate-100f-enterprise-firewall'),
  'Specifications',
  'Brand',
  'Fortinet',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'fortinet-fortigate-100f-enterprise-firewall'),
  'Specifications',
  'Form Factor',
  '1U Rackmount Appliance',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'fortinet-fortigate-100f-enterprise-firewall'),
  'Specifications',
  'Specifications',
  '2x 10GE SFP+ FortiLink slots, 4x GE SFP, 16x GE RJ45 • 1 Gbps Threat Protection • Dual Redundant Power',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'fortinet-fortigate-100f-enterprise-firewall'),
  'Specifications',
  'Condition',
  'Brand New',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'firewalls' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'cisco' LIMIT 1),
  'Cisco Firepower 2130 Enterprise Firewall',
  'cisco-firepower-2130-enterprise-firewall',
  'IB-NET-2003',
  'cisco-firepower-2130-enterpris',
  'refurbished',
  'Cisco enterprise networking solution. Up to 5 Gbps Stateful Inspection • 12x 1G RJ45 + 4x 10G SFP+ onboard • Resilient Clustered High Availability',
  'High reliability network hardware built for 24x7 continuous datacenter and campus deployment. Features: Up to 5 Gbps Stateful Inspection • 12x 1G RJ45 + 4x 10G SFP+ onboard • Resilient Clustered High Availability.',
  4850000,
  6500000,
  15,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-firepower-2130-enterprise-firewall'),
  'images/server-hud.png',
  'Cisco Firepower 2130 Enterprise Firewall',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-firepower-2130-enterprise-firewall'),
  'Specifications',
  'Brand',
  'Cisco',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-firepower-2130-enterprise-firewall'),
  'Specifications',
  'Form Factor',
  '1U Rackmount',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-firepower-2130-enterprise-firewall'),
  'Specifications',
  'Specifications',
  'Up to 5 Gbps Stateful Inspection • 12x 1G RJ45 + 4x 10G SFP+ onboard • Resilient Clustered High Availability',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-firepower-2130-enterprise-firewall'),
  'Specifications',
  'Condition',
  'Certified Refurbished',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'network-switches' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'cisco' LIMIT 1),
  'Cisco Catalyst 9300 48-Port Gigabit PoE+ Switch',
  'cisco-catalyst-9300-48-port-gigabit-poe-switch',
  'IB-NET-2004',
  'cisco-catalyst-9300-48-port-gi',
  'refurbished',
  'Cisco enterprise networking solution. 48x 10/100/1000 PoE+ ports (740W power budget) • Modular 4x 10G/25G SFP28 uplink • StackWise-480 architecture',
  'High reliability network hardware built for 24x7 continuous datacenter and campus deployment. Features: 48x 10/100/1000 PoE+ ports (740W power budget) • Modular 4x 10G/25G SFP28 uplink • StackWise-480 architecture.',
  6800000,
  9200000,
  15,
  1,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-catalyst-9300-48-port-gigabit-poe-switch'),
  'images/server-hud.png',
  'Cisco Catalyst 9300 48-Port Gigabit PoE+ Switch',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-catalyst-9300-48-port-gigabit-poe-switch'),
  'Specifications',
  'Brand',
  'Cisco',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-catalyst-9300-48-port-gigabit-poe-switch'),
  'Specifications',
  'Form Factor',
  '1U Managed Layer 3 Switch',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-catalyst-9300-48-port-gigabit-poe-switch'),
  'Specifications',
  'Specifications',
  '48x 10/100/1000 PoE+ ports (740W power budget) • Modular 4x 10G/25G SFP28 uplink • StackWise-480 architecture',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-catalyst-9300-48-port-gigabit-poe-switch'),
  'Specifications',
  'Condition',
  'Refurbished / New Open Box',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'network-switches' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'cisco' LIMIT 1),
  'Cisco Nexus 93180YC-EX Datacenter Switch',
  'cisco-nexus-93180yc-ex-datacenter-switch',
  'IB-NET-2005',
  'cisco-nexus-93180yc-ex-datacen',
  'refurbished',
  'Cisco enterprise networking solution. 48x 1/10/25-Gbps fiber SFP28 ports + 6x 40/100-Gbps QSFP28 uplinks • Sub-microsecond latency • 3.6 Tbps bandwidth',
  'High reliability network hardware built for 24x7 continuous datacenter and campus deployment. Features: 48x 1/10/25-Gbps fiber SFP28 ports + 6x 40/100-Gbps QSFP28 uplinks • Sub-microsecond latency • 3.6 Tbps bandwidth.',
  6800000,
  9200000,
  15,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-nexus-93180yc-ex-datacenter-switch'),
  'images/server-hud.png',
  'Cisco Nexus 93180YC-EX Datacenter Switch',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-nexus-93180yc-ex-datacenter-switch'),
  'Specifications',
  'Brand',
  'Cisco',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-nexus-93180yc-ex-datacenter-switch'),
  'Specifications',
  'Form Factor',
  '1U Datacenter Spine/Leaf',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-nexus-93180yc-ex-datacenter-switch'),
  'Specifications',
  'Specifications',
  '48x 1/10/25-Gbps fiber SFP28 ports + 6x 40/100-Gbps QSFP28 uplinks • Sub-microsecond latency • 3.6 Tbps bandwidth',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-nexus-93180yc-ex-datacenter-switch'),
  'Specifications',
  'Condition',
  'Refurbished',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'network-switches' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'huawei' LIMIT 1),
  'Huawei CloudEngine S5735-L48P4X-A-V2 Switch',
  'huawei-cloudengine-s5735-l48p4x-a-v2-switch',
  'IB-NET-2006',
  'huawei-cloudengine-s5735-l48p4',
  'new',
  'Huawei enterprise networking solution. 48x 10/100/1000BASE-T PoE+ ports • 4x 10GE SFP+ uplinks • Intelligent iStack management & energy-efficient design',
  'High reliability network hardware built for 24x7 continuous datacenter and campus deployment. Features: 48x 10/100/1000BASE-T PoE+ ports • 4x 10GE SFP+ uplinks • Intelligent iStack management & energy-efficient design.',
  6800000,
  9200000,
  15,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'huawei-cloudengine-s5735-l48p4x-a-v2-switch'),
  'images/server-hud.png',
  'Huawei CloudEngine S5735-L48P4X-A-V2 Switch',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'huawei-cloudengine-s5735-l48p4x-a-v2-switch'),
  'Specifications',
  'Brand',
  'Huawei',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'huawei-cloudengine-s5735-l48p4x-a-v2-switch'),
  'Specifications',
  'Form Factor',
  '1U Managed Switch',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'huawei-cloudengine-s5735-l48p4x-a-v2-switch'),
  'Specifications',
  'Specifications',
  '48x 10/100/1000BASE-T PoE+ ports • 4x 10GE SFP+ uplinks • Intelligent iStack management & energy-efficient design',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'huawei-cloudengine-s5735-l48p4x-a-v2-switch'),
  'Specifications',
  'Condition',
  'Brand New',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'network-switches' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'juniper' LIMIT 1),
  'Juniper Networks EX3400-48P Enterprise Switch',
  'juniper-networks-ex3400-48p-enterprise-switch',
  'IB-NET-2007',
  'juniper-networks-ex3400-48p-en',
  'refurbished',
  'Juniper enterprise networking solution. 48x 10/100/1000 PoE+ ports • 4x 10GbE SFP+ and 2x 40GbE QSFP+ uplinks • Junos OS Virtual Chassis technology',
  'High reliability network hardware built for 24x7 continuous datacenter and campus deployment. Features: 48x 10/100/1000 PoE+ ports • 4x 10GbE SFP+ and 2x 40GbE QSFP+ uplinks • Junos OS Virtual Chassis technology.',
  6800000,
  9200000,
  15,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'juniper-networks-ex3400-48p-enterprise-switch'),
  'images/server-hud.png',
  'Juniper Networks EX3400-48P Enterprise Switch',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'juniper-networks-ex3400-48p-enterprise-switch'),
  'Specifications',
  'Brand',
  'Juniper',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'juniper-networks-ex3400-48p-enterprise-switch'),
  'Specifications',
  'Form Factor',
  '1U Layer 3 Switch',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'juniper-networks-ex3400-48p-enterprise-switch'),
  'Specifications',
  'Specifications',
  '48x 10/100/1000 PoE+ ports • 4x 10GbE SFP+ and 2x 40GbE QSFP+ uplinks • Junos OS Virtual Chassis technology',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'juniper-networks-ex3400-48p-enterprise-switch'),
  'Specifications',
  'Condition',
  'Refurbished',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'network-switches' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'mikrotik' LIMIT 1),
  'MikroTik CRS326-24G-2S+RM Cloud Router Switch',
  'mikrotik-crs326-24g-2srm-cloud-router-switch',
  'IB-NET-2008',
  'mikrotik-crs326-24g-2srm-cloud',
  'new',
  'MikroTik enterprise networking solution. 24x Gigabit RJ45 Ethernet ports • 2x 10G SFP+ cages • Dual boot RouterOS and SwOS • Wire speed Layer 2/3 forwarding',
  'High reliability network hardware built for 24x7 continuous datacenter and campus deployment. Features: 24x Gigabit RJ45 Ethernet ports • 2x 10G SFP+ cages • Dual boot RouterOS and SwOS • Wire speed Layer 2/3 forwarding.',
  6800000,
  9200000,
  15,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'mikrotik-crs326-24g-2srm-cloud-router-switch'),
  'images/server-hud.png',
  'MikroTik CRS326-24G-2S+RM Cloud Router Switch',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'mikrotik-crs326-24g-2srm-cloud-router-switch'),
  'Specifications',
  'Brand',
  'MikroTik',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'mikrotik-crs326-24g-2srm-cloud-router-switch'),
  'Specifications',
  'Form Factor',
  '1U Rackmount Managed Switch',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'mikrotik-crs326-24g-2srm-cloud-router-switch'),
  'Specifications',
  'Specifications',
  '24x Gigabit RJ45 Ethernet ports • 2x 10G SFP+ cages • Dual boot RouterOS and SwOS • Wire speed Layer 2/3 forwarding',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'mikrotik-crs326-24g-2srm-cloud-router-switch'),
  'Specifications',
  'Condition',
  'Brand New',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'network-switches' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'aten' LIMIT 1),
  'ATEN 16-Port Cat 5 KVM Over IP Switch',
  'aten-16-port-cat-5-kvm-over-ip-switch',
  'IB-NET-2009',
  'aten-16-port-cat-5-kvm-over-ip',
  'new',
  'ATEN enterprise networking solution. 16x RJ-45 server ports • 1 Local + 1 Remote IP User • High video resolution 1920x1200 • Multiplatform BIOS-level access',
  'High reliability network hardware built for 24x7 continuous datacenter and campus deployment. Features: 16x RJ-45 server ports • 1 Local + 1 Remote IP User • High video resolution 1920x1200 • Multiplatform BIOS-level access.',
  6800000,
  9200000,
  15,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'aten-16-port-cat-5-kvm-over-ip-switch'),
  'images/server-hud.png',
  'ATEN 16-Port Cat 5 KVM Over IP Switch',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'aten-16-port-cat-5-kvm-over-ip-switch'),
  'Specifications',
  'Brand',
  'ATEN',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'aten-16-port-cat-5-kvm-over-ip-switch'),
  'Specifications',
  'Form Factor',
  '1U Rackmount KVM',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'aten-16-port-cat-5-kvm-over-ip-switch'),
  'Specifications',
  'Specifications',
  '16x RJ-45 server ports • 1 Local + 1 Remote IP User • High video resolution 1920x1200 • Multiplatform BIOS-level access',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'aten-16-port-cat-5-kvm-over-ip-switch'),
  'Specifications',
  'Condition',
  'New',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'networking' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'f5-networks' LIMIT 1),
  'F5 BIG-IP i2800 Local Traffic Manager (LTM)',
  'f5-big-ip-i2800-local-traffic-manager-ltm',
  'IB-NET-2010',
  'f5-big-ip-i2800-local-traffic-',
  'refurbished',
  'F5 Networks enterprise networking solution. 5 Gbps L4/L7 throughput • 4x 10G SFP+ & 4x 1G ports • Full proxy architecture • Hardware SSL offloading & compression',
  'High reliability network hardware built for 24x7 continuous datacenter and campus deployment. Features: 5 Gbps L4/L7 throughput • 4x 10G SFP+ & 4x 1G ports • Full proxy architecture • Hardware SSL offloading & compression.',
  3200000,
  4500000,
  15,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'f5-big-ip-i2800-local-traffic-manager-ltm'),
  'images/server-hud.png',
  'F5 BIG-IP i2800 Local Traffic Manager (LTM)',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'f5-big-ip-i2800-local-traffic-manager-ltm'),
  'Specifications',
  'Brand',
  'F5 Networks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'f5-big-ip-i2800-local-traffic-manager-ltm'),
  'Specifications',
  'Form Factor',
  '1U Application Delivery Controller',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'f5-big-ip-i2800-local-traffic-manager-ltm'),
  'Specifications',
  'Specifications',
  '5 Gbps L4/L7 throughput • 4x 10G SFP+ & 4x 1G ports • Full proxy architecture • Hardware SSL offloading & compression',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'f5-big-ip-i2800-local-traffic-manager-ltm'),
  'Specifications',
  'Condition',
  'Certified Refurbished',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'load-balancers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'a10-networks' LIMIT 1),
  'A10 Networks Thunder 3030S ADC Load Balancer',
  'a10-networks-thunder-3030s-adc-load-balancer',
  'IB-NET-2011',
  'a10-networks-thunder-3030s-adc',
  'refurbished',
  'A10 Networks enterprise networking solution. 30 Gbps application throughput • 4x 10GE SFP+ and 8x 1GE ports • Dedicated SSL Security Acceleration Module',
  'High reliability network hardware built for 24x7 continuous datacenter and campus deployment. Features: 30 Gbps application throughput • 4x 10GE SFP+ and 8x 1GE ports • Dedicated SSL Security Acceleration Module.',
  11500000,
  15000000,
  15,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'a10-networks-thunder-3030s-adc-load-balancer'),
  'images/server-hud.png',
  'A10 Networks Thunder 3030S ADC Load Balancer',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'a10-networks-thunder-3030s-adc-load-balancer'),
  'Specifications',
  'Brand',
  'A10 Networks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'a10-networks-thunder-3030s-adc-load-balancer'),
  'Specifications',
  'Form Factor',
  '1U ADC Appliance',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'a10-networks-thunder-3030s-adc-load-balancer'),
  'Specifications',
  'Specifications',
  '30 Gbps application throughput • 4x 10GE SFP+ and 8x 1GE ports • Dedicated SSL Security Acceleration Module',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'a10-networks-thunder-3030s-adc-load-balancer'),
  'Specifications',
  'Condition',
  'Refurbished',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'networking' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'radware' LIMIT 1),
  'Radware Alteon 5208 Application Delivery Controller',
  'radware-alteon-5208-application-delivery-controller',
  'IB-NET-2012',
  'radware-alteon-5208-applicatio',
  'refurbished',
  'Radware enterprise networking solution. 26 Gbps maximum throughput • 8x 10GbE SFP+ ports • Advanced SLA management & multi-tenant virtualization',
  'High reliability network hardware built for 24x7 continuous datacenter and campus deployment. Features: 26 Gbps maximum throughput • 8x 10GbE SFP+ ports • Advanced SLA management & multi-tenant virtualization.',
  3200000,
  4500000,
  15,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'radware-alteon-5208-application-delivery-controller'),
  'images/server-hud.png',
  'Radware Alteon 5208 Application Delivery Controller',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'radware-alteon-5208-application-delivery-controller'),
  'Specifications',
  'Brand',
  'Radware',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'radware-alteon-5208-application-delivery-controller'),
  'Specifications',
  'Form Factor',
  '1U Rackmount ADC',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'radware-alteon-5208-application-delivery-controller'),
  'Specifications',
  'Specifications',
  '26 Gbps maximum throughput • 8x 10GbE SFP+ ports • Advanced SLA management & multi-tenant virtualization',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'radware-alteon-5208-application-delivery-controller'),
  'Specifications',
  'Condition',
  'Certified Refurbished',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'load-balancers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'citrix' LIMIT 1),
  'Citrix NetScaler MPX 5905 Hardware Load Balancer',
  'citrix-netscaler-mpx-5905-hardware-load-balancer',
  'IB-NET-2013',
  'citrix-netscaler-mpx-5905-hard',
  'refurbished',
  'Citrix enterprise networking solution. 5 Gbps L7 throughput • 4x 10GE SFP+ & 6x 1GE ports • Hardware SSL acceleration • Global Server Load Balancing (GSLB)',
  'High reliability network hardware built for 24x7 continuous datacenter and campus deployment. Features: 5 Gbps L7 throughput • 4x 10GE SFP+ & 6x 1GE ports • Hardware SSL acceleration • Global Server Load Balancing (GSLB).',
  11500000,
  15000000,
  15,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'citrix-netscaler-mpx-5905-hardware-load-balancer'),
  'images/server-hud.png',
  'Citrix NetScaler MPX 5905 Hardware Load Balancer',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'citrix-netscaler-mpx-5905-hardware-load-balancer'),
  'Specifications',
  'Brand',
  'Citrix',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'citrix-netscaler-mpx-5905-hardware-load-balancer'),
  'Specifications',
  'Form Factor',
  '1U Hardware Appliance',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'citrix-netscaler-mpx-5905-hardware-load-balancer'),
  'Specifications',
  'Specifications',
  '5 Gbps L7 throughput • 4x 10GE SFP+ & 6x 1GE ports • Hardware SSL acceleration • Global Server Load Balancing (GSLB)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'citrix-netscaler-mpx-5905-hardware-load-balancer'),
  'Specifications',
  'Condition',
  'Refurbished',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'networking' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'kemp' LIMIT 1),
  'Progress Kemp LoadMaster LM-X15 ADC Appliance',
  'progress-kemp-loadmaster-lm-x15-adc-appliance',
  'IB-NET-2014',
  'progress-kemp-loadmaster-lm-x1',
  'refurbished',
  'KEMP enterprise networking solution. 15 Gbps L4/L7 throughput • 4x 10GbE SFP+ & 4x 1GbE ports • Pre-configured templates for Exchange & VMware',
  'High reliability network hardware built for 24x7 continuous datacenter and campus deployment. Features: 15 Gbps L4/L7 throughput • 4x 10GbE SFP+ & 4x 1GbE ports • Pre-configured templates for Exchange & VMware.',
  3200000,
  4500000,
  15,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'progress-kemp-loadmaster-lm-x15-adc-appliance'),
  'images/server-hud.png',
  'Progress Kemp LoadMaster LM-X15 ADC Appliance',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'progress-kemp-loadmaster-lm-x15-adc-appliance'),
  'Specifications',
  'Brand',
  'KEMP',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'progress-kemp-loadmaster-lm-x15-adc-appliance'),
  'Specifications',
  'Form Factor',
  '1U Hardware Appliance',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'progress-kemp-loadmaster-lm-x15-adc-appliance'),
  'Specifications',
  'Specifications',
  '15 Gbps L4/L7 throughput • 4x 10GbE SFP+ & 4x 1GbE ports • Pre-configured templates for Exchange & VMware',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'progress-kemp-loadmaster-lm-x15-adc-appliance'),
  'Specifications',
  'Condition',
  'New / Refurbished',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'networking' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'peplink' LIMIT 1),
  'Peplink Balance 380 Enterprise Multi-WAN Router',
  'peplink-balance-380-enterprise-multi-wan-router',
  'IB-NET-2015',
  'peplink-balance-380-enterprise',
  'new',
  'Peplink enterprise networking solution. 3x Gigabit WAN ports + 3x Gigabit LAN ports • 1 Gbps router throughput • SpeedFusion bandwidth bonding technology',
  'High reliability network hardware built for 24x7 continuous datacenter and campus deployment. Features: 3x Gigabit WAN ports + 3x Gigabit LAN ports • 1 Gbps router throughput • SpeedFusion bandwidth bonding technology.',
  3200000,
  4500000,
  15,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'peplink-balance-380-enterprise-multi-wan-router'),
  'images/server-hud.png',
  'Peplink Balance 380 Enterprise Multi-WAN Router',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'peplink-balance-380-enterprise-multi-wan-router'),
  'Specifications',
  'Brand',
  'Peplink',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'peplink-balance-380-enterprise-multi-wan-router'),
  'Specifications',
  'Form Factor',
  '1U Rackmount Router',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'peplink-balance-380-enterprise-multi-wan-router'),
  'Specifications',
  'Specifications',
  '3x Gigabit WAN ports + 3x Gigabit LAN ports • 1 Gbps router throughput • SpeedFusion bandwidth bonding technology',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'peplink-balance-380-enterprise-multi-wan-router'),
  'Specifications',
  'Condition',
  'New',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'sfp-modules' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'cisco-finisar-compatible' LIMIT 1),
  '100G QSFP28 SR4 Multi-Mode Optical Transceiver',
  '100g-qsfp28-sr4-multi-mode-optical-transceiver',
  'IB-NET-2016',
  '100g-qsfp28-sr4-multi-mode-opt',
  'new',
  'Cisco / Finisar Compatible enterprise networking solution. 100Gbps data rate • 850nm VCSEL wavelength • MPO-12 optical connector • Up to 100m over OM4 multimode fiber',
  'High reliability network hardware built for 24x7 continuous datacenter and campus deployment. Features: 100Gbps data rate • 850nm VCSEL wavelength • MPO-12 optical connector • Up to 100m over OM4 multimode fiber.',
  850000,
  1200000,
  15,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '100g-qsfp28-sr4-multi-mode-optical-transceiver'),
  'images/server-hud.png',
  '100G QSFP28 SR4 Multi-Mode Optical Transceiver',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '100g-qsfp28-sr4-multi-mode-optical-transceiver'),
  'Specifications',
  'Brand',
  'Cisco / Finisar Compatible',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '100g-qsfp28-sr4-multi-mode-optical-transceiver'),
  'Specifications',
  'Form Factor',
  'QSFP28 Hot-Pluggable Transceiver',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '100g-qsfp28-sr4-multi-mode-optical-transceiver'),
  'Specifications',
  'Specifications',
  '100Gbps data rate • 850nm VCSEL wavelength • MPO-12 optical connector • Up to 100m over OM4 multimode fiber',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '100g-qsfp28-sr4-multi-mode-optical-transceiver'),
  'Specifications',
  'Condition',
  'Brand New',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'sfp-modules' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'hpe-cisco-compatible' LIMIT 1),
  '40G QSFP+ SR4 Optical Transceiver Module',
  '40g-qsfp-sr4-optical-transceiver-module',
  'IB-NET-2017',
  '40g-qsfp-sr4-optical-transceiv',
  'new',
  'HPE / Cisco Compatible enterprise networking solution. 40Gbps line rate • 850nm wavelength • MPO/MTP connector • Up to 150m reach • Full DDM/DOM diagnostic support',
  'High reliability network hardware built for 24x7 continuous datacenter and campus deployment. Features: 40Gbps line rate • 850nm wavelength • MPO/MTP connector • Up to 150m reach • Full DDM/DOM diagnostic support.',
  850000,
  1200000,
  15,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '40g-qsfp-sr4-optical-transceiver-module'),
  'images/server-hud.png',
  '40G QSFP+ SR4 Optical Transceiver Module',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '40g-qsfp-sr4-optical-transceiver-module'),
  'Specifications',
  'Brand',
  'HPE / Cisco Compatible',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '40g-qsfp-sr4-optical-transceiver-module'),
  'Specifications',
  'Form Factor',
  'QSFP+ Hot-Pluggable Transceiver',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '40g-qsfp-sr4-optical-transceiver-module'),
  'Specifications',
  'Specifications',
  '40Gbps line rate • 850nm wavelength • MPO/MTP connector • Up to 150m reach • Full DDM/DOM diagnostic support',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '40g-qsfp-sr4-optical-transceiver-module'),
  'Specifications',
  'Condition',
  'Brand New',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'sfp-modules' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'multi-vendor-compatible' LIMIT 1),
  '10G SFP+ SR Optical Transceiver (850nm 300m)',
  '10g-sfp-sr-optical-transceiver-850nm-300m',
  'IB-NET-2018',
  '10g-sfp-sr-optical-transceiver',
  'new',
  'Multi-Vendor Compatible enterprise networking solution. 10.3Gbps data rate • 850nm multimode • Dual LC connector • 300m transmission over OM3 fiber • Low power < 1W',
  'High reliability network hardware built for 24x7 continuous datacenter and campus deployment. Features: 10.3Gbps data rate • 850nm multimode • Dual LC connector • 300m transmission over OM3 fiber • Low power < 1W.',
  850000,
  1200000,
  15,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '10g-sfp-sr-optical-transceiver-850nm-300m'),
  'images/server-hud.png',
  '10G SFP+ SR Optical Transceiver (850nm 300m)',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '10g-sfp-sr-optical-transceiver-850nm-300m'),
  'Specifications',
  'Brand',
  'Multi-Vendor Compatible',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '10g-sfp-sr-optical-transceiver-850nm-300m'),
  'Specifications',
  'Form Factor',
  'SFP+ Transceiver Module',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '10g-sfp-sr-optical-transceiver-850nm-300m'),
  'Specifications',
  'Specifications',
  '10.3Gbps data rate • 850nm multimode • Dual LC connector • 300m transmission over OM3 fiber • Low power < 1W',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '10g-sfp-sr-optical-transceiver-850nm-300m'),
  'Specifications',
  'Condition',
  'Brand New',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'sfp-modules' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'multi-vendor-compatible' LIMIT 1),
  '10G SFP+ LR 1310nm 10km Single-Mode Optical Module',
  '10g-sfp-lr-1310nm-10km-single-mode-optical-module',
  'IB-NET-2019',
  '10g-sfp-lr-1310nm-10km-single-',
  'new',
  'Multi-Vendor Compatible enterprise networking solution. 10Gbps line rate • 1310nm DFB laser • Dual LC optical interface • Up to 10km link distance over standard SMF',
  'High reliability network hardware built for 24x7 continuous datacenter and campus deployment. Features: 10Gbps line rate • 1310nm DFB laser • Dual LC optical interface • Up to 10km link distance over standard SMF.',
  850000,
  1200000,
  15,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '10g-sfp-lr-1310nm-10km-single-mode-optical-module'),
  'images/server-hud.png',
  '10G SFP+ LR 1310nm 10km Single-Mode Optical Module',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '10g-sfp-lr-1310nm-10km-single-mode-optical-module'),
  'Specifications',
  'Brand',
  'Multi-Vendor Compatible',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '10g-sfp-lr-1310nm-10km-single-mode-optical-module'),
  'Specifications',
  'Form Factor',
  'SFP+ Optical Module',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '10g-sfp-lr-1310nm-10km-single-mode-optical-module'),
  'Specifications',
  'Specifications',
  '10Gbps line rate • 1310nm DFB laser • Dual LC optical interface • Up to 10km link distance over standard SMF',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '10g-sfp-lr-1310nm-10km-single-mode-optical-module'),
  'Specifications',
  'Condition',
  'Brand New',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'sfp-modules' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'brocade-cisco-compatible' LIMIT 1),
  '16G Fibre Channel SW SFP+ Optical Transceiver',
  '16g-fibre-channel-sw-sfp-optical-transceiver',
  'IB-NET-2020',
  '16g-fibre-channel-sw-sfp-optic',
  'new',
  'Brocade / Cisco Compatible enterprise networking solution. 14.025 Gbps / 8.5 Gbps tri-rate • 850nm LC Duplex • Designed for SAN storage arrays & Fibre Channel fabric switches',
  'High reliability network hardware built for 24x7 continuous datacenter and campus deployment. Features: 14.025 Gbps / 8.5 Gbps tri-rate • 850nm LC Duplex • Designed for SAN storage arrays & Fibre Channel fabric switches.',
  850000,
  1200000,
  15,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '16g-fibre-channel-sw-sfp-optical-transceiver'),
  'images/server-hud.png',
  '16G Fibre Channel SW SFP+ Optical Transceiver',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '16g-fibre-channel-sw-sfp-optical-transceiver'),
  'Specifications',
  'Brand',
  'Brocade / Cisco Compatible',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '16g-fibre-channel-sw-sfp-optical-transceiver'),
  'Specifications',
  'Form Factor',
  'FC SFP+ Transceiver',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '16g-fibre-channel-sw-sfp-optical-transceiver'),
  'Specifications',
  'Specifications',
  '14.025 Gbps / 8.5 Gbps tri-rate • 850nm LC Duplex • Designed for SAN storage arrays & Fibre Channel fabric switches',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '16g-fibre-channel-sw-sfp-optical-transceiver'),
  'Specifications',
  'Condition',
  'Brand New',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'sfp-modules' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'multi-vendor-compatible' LIMIT 1),
  '1G SFP 1000BASE-T Copper RJ45 Transceiver (100m)',
  '1g-sfp-1000base-t-copper-rj45-transceiver-100m',
  'IB-NET-2021',
  '1g-sfp-1000base-t-copper-rj45-',
  'new',
  'Multi-Vendor Compatible enterprise networking solution. 1.25Gbps bi-directional • Standard RJ-45 copper port • Category 5e/6 UTP cable up to 100m • Plug and play',
  'High reliability network hardware built for 24x7 continuous datacenter and campus deployment. Features: 1.25Gbps bi-directional • Standard RJ-45 copper port • Category 5e/6 UTP cable up to 100m • Plug and play.',
  850000,
  1200000,
  15,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '1g-sfp-1000base-t-copper-rj45-transceiver-100m'),
  'images/server-hud.png',
  '1G SFP 1000BASE-T Copper RJ45 Transceiver (100m)',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '1g-sfp-1000base-t-copper-rj45-transceiver-100m'),
  'Specifications',
  'Brand',
  'Multi-Vendor Compatible',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '1g-sfp-1000base-t-copper-rj45-transceiver-100m'),
  'Specifications',
  'Form Factor',
  'SFP Copper Transceiver',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '1g-sfp-1000base-t-copper-rj45-transceiver-100m'),
  'Specifications',
  'Specifications',
  '1.25Gbps bi-directional • Standard RJ-45 copper port • Category 5e/6 UTP cable up to 100m • Plug and play',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '1g-sfp-1000base-t-copper-rj45-transceiver-100m'),
  'Specifications',
  'Condition',
  'Brand New',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'sfp-modules' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'cisco-dell-hpe-compatible' LIMIT 1),
  '10G SFP+ Passive Direct Attach Copper (DAC) Cable',
  '10g-sfp-passive-direct-attach-copper-dac-cable',
  'IB-NET-2022',
  '10g-sfp-passive-direct-attach-',
  'new',
  'Cisco / Dell / HPE Compatible enterprise networking solution. Available lengths: 0.5m, 1m, 2m, 3m, 5m • Ultra-low latency Twinax cable • Dual SFP+ connectors • Zero power consumption',
  'High reliability network hardware built for 24x7 continuous datacenter and campus deployment. Features: Available lengths: 0.5m, 1m, 2m, 3m, 5m • Ultra-low latency Twinax cable • Dual SFP+ connectors • Zero power consumption.',
  850000,
  1200000,
  15,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '10g-sfp-passive-direct-attach-copper-dac-cable'),
  'images/server-hud.png',
  '10G SFP+ Passive Direct Attach Copper (DAC) Cable',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '10g-sfp-passive-direct-attach-copper-dac-cable'),
  'Specifications',
  'Brand',
  'Cisco / Dell / HPE Compatible',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '10g-sfp-passive-direct-attach-copper-dac-cable'),
  'Specifications',
  'Form Factor',
  'High-Speed Interconnect Cable',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '10g-sfp-passive-direct-attach-copper-dac-cable'),
  'Specifications',
  'Specifications',
  'Available lengths: 0.5m, 1m, 2m, 3m, 5m • Ultra-low latency Twinax cable • Dual SFP+ connectors • Zero power consumption',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '10g-sfp-passive-direct-attach-copper-dac-cable'),
  'Specifications',
  'Condition',
  'Brand New',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'firewalls' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks-custom' LIMIT 1),
  '1U Rackmount Network Telemetry & Firewall Server',
  '1u-rackmount-network-telemetry-firewall-server',
  'IB-NET-2023',
  '1u-rackmount-network-telemetry',
  'new',
  'iboltnetworks Custom enterprise networking solution. Intel Xeon E-2200 CPU • 32GB DDR4 ECC • 4x Intel I350 Gigabit + 2x 10G SFP+ ports • Ready for pfSense / OPNsense',
  'High reliability network hardware built for 24x7 continuous datacenter and campus deployment. Features: Intel Xeon E-2200 CPU • 32GB DDR4 ECC • 4x Intel I350 Gigabit + 2x 10G SFP+ ports • Ready for pfSense / OPNsense.',
  4850000,
  6500000,
  15,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '1u-rackmount-network-telemetry-firewall-server'),
  'images/server-hero.png',
  '1U Rackmount Network Telemetry & Firewall Server',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '1u-rackmount-network-telemetry-firewall-server'),
  'Specifications',
  'Brand',
  'iboltnetworks Custom',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '1u-rackmount-network-telemetry-firewall-server'),
  'Specifications',
  'Form Factor',
  '1U Short-Depth Rack Server',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '1u-rackmount-network-telemetry-firewall-server'),
  'Specifications',
  'Specifications',
  'Intel Xeon E-2200 CPU • 32GB DDR4 ECC • 4x Intel I350 Gigabit + 2x 10G SFP+ ports • Ready for pfSense / OPNsense',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '1u-rackmount-network-telemetry-firewall-server'),
  'Specifications',
  'Condition',
  'Brand New',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'networking' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'cisco' LIMIT 1),
  'Cisco Integrated Services Router ISR 4331/K9',
  'cisco-integrated-services-router-isr-4331k9',
  'IB-NET-2024',
  'cisco-integrated-services-rout',
  'refurbished',
  'Cisco enterprise networking solution. 100 Mbps to 300 Mbps aggregate throughput • 3x GE ports (2x SFP) • 2x NIM slots, 1x SM slot • Integrated Cisco IOS-XE',
  'High reliability network hardware built for 24x7 continuous datacenter and campus deployment. Features: 100 Mbps to 300 Mbps aggregate throughput • 3x GE ports (2x SFP) • 2x NIM slots, 1x SM slot • Integrated Cisco IOS-XE.',
  3200000,
  4500000,
  15,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-integrated-services-router-isr-4331k9'),
  'images/server-hud.png',
  'Cisco Integrated Services Router ISR 4331/K9',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-integrated-services-router-isr-4331k9'),
  'Specifications',
  'Brand',
  'Cisco',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-integrated-services-router-isr-4331k9'),
  'Specifications',
  'Form Factor',
  '1U Enterprise Branch Router',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-integrated-services-router-isr-4331k9'),
  'Specifications',
  'Specifications',
  '100 Mbps to 300 Mbps aggregate throughput • 3x GE ports (2x SFP) • 2x NIM slots, 1x SM slot • Integrated Cisco IOS-XE',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cisco-integrated-services-router-isr-4331k9'),
  'Specifications',
  'Condition',
  'Certified Refurbished',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'networking' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'mikrotik' LIMIT 1),
  'MikroTik CCR2004-1G-12S+2XS Cloud Core Router',
  'mikrotik-ccr2004-1g-12s2xs-cloud-core-router',
  'IB-NET-2025',
  'mikrotik-ccr2004-1g-12s2xs-clo',
  'new',
  'MikroTik enterprise networking solution. Annapurna Alpine v2 4-core 1.7GHz CPU • 12x 10G SFP+ & 2x 25G SFP28 ports • Dual redundant power supplies • RouterOS v7',
  'High reliability network hardware built for 24x7 continuous datacenter and campus deployment. Features: Annapurna Alpine v2 4-core 1.7GHz CPU • 12x 10G SFP+ & 2x 25G SFP28 ports • Dual redundant power supplies • RouterOS v7.',
  3200000,
  4500000,
  15,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'mikrotik-ccr2004-1g-12s2xs-cloud-core-router'),
  'images/server-hud.png',
  'MikroTik CCR2004-1G-12S+2XS Cloud Core Router',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'mikrotik-ccr2004-1g-12s2xs-cloud-core-router'),
  'Specifications',
  'Brand',
  'MikroTik',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'mikrotik-ccr2004-1g-12s2xs-cloud-core-router'),
  'Specifications',
  'Form Factor',
  '1U Core Optical Router',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'mikrotik-ccr2004-1g-12s2xs-cloud-core-router'),
  'Specifications',
  'Specifications',
  'Annapurna Alpine v2 4-core 1.7GHz CPU • 12x 10G SFP+ & 2x 25G SFP28 ports • Dual redundant power supplies • RouterOS v7',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'mikrotik-ccr2004-1g-12s2xs-cloud-core-router'),
  'Specifications',
  'Condition',
  'Brand New',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'enterprise-gpus' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'nvidia' LIMIT 1),
  'NVIDIA RTX 6000 Ada Generation 48GB GPU',
  'nvidia-rtx-6000-ada-generation-48gb-gpu',
  'IB-CMP-3001',
  'nvidia-rtx-6000-ada-generation',
  'new',
  'High-grade NVIDIA component. 48GB GDDR6 with ECC • 18,176 CUDA Cores • 568 Tensor Cores • PCIe 4.0 x16 • 300W Active Cooling',
  'Authentic server component tested for compatibility and stability in critical server systems. 48GB GDDR6 with ECC • 18,176 CUDA Cores • 568 Tensor Cores • PCIe 4.0 x16 • 300W Active Cooling.',
  42500000,
  52000000,
  20,
  1,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'nvidia-rtx-6000-ada-generation-48gb-gpu'),
  'images/server-hud.png',
  'NVIDIA RTX 6000 Ada Generation 48GB GPU',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'nvidia-rtx-6000-ada-generation-48gb-gpu'),
  'Specifications',
  'Brand',
  'NVIDIA',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'nvidia-rtx-6000-ada-generation-48gb-gpu'),
  'Specifications',
  'Form Factor',
  'Dual-Slot Full-Height Workstation / Server GPU',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'nvidia-rtx-6000-ada-generation-48gb-gpu'),
  'Specifications',
  'Technical Specifications',
  '48GB GDDR6 with ECC • 18,176 CUDA Cores • 568 Tensor Cores • PCIe 4.0 x16 • 300W Active Cooling',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'nvidia-rtx-6000-ada-generation-48gb-gpu'),
  'Specifications',
  'Hardware Condition',
  'Brand New / Authorized Stock',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'enterprise-gpus' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'nvidia' LIMIT 1),
  'NVIDIA RTX A6000 48GB GDDR6 Enterprise GPU',
  'nvidia-rtx-a6000-48gb-gddr6-enterprise-gpu',
  'IB-CMP-3002',
  'nvidia-rtx-a6000-48gb-gddr6-en',
  'new',
  'High-grade NVIDIA component. 48GB GDDR6 ECC • 10,752 CUDA Cores • 336 Tensor Cores • 300W • NVLink 2-way Support',
  'Authentic server component tested for compatibility and stability in critical server systems. 48GB GDDR6 ECC • 10,752 CUDA Cores • 336 Tensor Cores • 300W • NVLink 2-way Support.',
  42500000,
  52000000,
  20,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'nvidia-rtx-a6000-48gb-gddr6-enterprise-gpu'),
  'images/server-hud.png',
  'NVIDIA RTX A6000 48GB GDDR6 Enterprise GPU',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'nvidia-rtx-a6000-48gb-gddr6-enterprise-gpu'),
  'Specifications',
  'Brand',
  'NVIDIA',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'nvidia-rtx-a6000-48gb-gddr6-enterprise-gpu'),
  'Specifications',
  'Form Factor',
  'Dual-Slot PCIe 4.0 x16',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'nvidia-rtx-a6000-48gb-gddr6-enterprise-gpu'),
  'Specifications',
  'Technical Specifications',
  '48GB GDDR6 ECC • 10,752 CUDA Cores • 336 Tensor Cores • 300W • NVLink 2-way Support',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'nvidia-rtx-a6000-48gb-gddr6-enterprise-gpu'),
  'Specifications',
  'Hardware Condition',
  'Brand New',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'enterprise-gpus' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'nvidia' LIMIT 1),
  'NVIDIA RTX A5000 24GB GDDR6 Workstation GPU',
  'nvidia-rtx-a5000-24gb-gddr6-workstation-gpu',
  'IB-CMP-3003',
  'nvidia-rtx-a5000-24gb-gddr6-wo',
  'new',
  'High-grade NVIDIA component. 24GB GDDR6 ECC • 8,192 CUDA Cores • 256 Tensor Cores • 230W • VR & Ray Tracing Ready',
  'Authentic server component tested for compatibility and stability in critical server systems. 24GB GDDR6 ECC • 8,192 CUDA Cores • 256 Tensor Cores • 230W • VR & Ray Tracing Ready.',
  42500000,
  52000000,
  20,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'nvidia-rtx-a5000-24gb-gddr6-workstation-gpu'),
  'images/server-hud.png',
  'NVIDIA RTX A5000 24GB GDDR6 Workstation GPU',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'nvidia-rtx-a5000-24gb-gddr6-workstation-gpu'),
  'Specifications',
  'Brand',
  'NVIDIA',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'nvidia-rtx-a5000-24gb-gddr6-workstation-gpu'),
  'Specifications',
  'Form Factor',
  'Dual-Slot PCIe 4.0 x16',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'nvidia-rtx-a5000-24gb-gddr6-workstation-gpu'),
  'Specifications',
  'Technical Specifications',
  '24GB GDDR6 ECC • 8,192 CUDA Cores • 256 Tensor Cores • 230W • VR & Ray Tracing Ready',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'nvidia-rtx-a5000-24gb-gddr6-workstation-gpu'),
  'Specifications',
  'Hardware Condition',
  'Brand New',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'amd-epyc-processors' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'amd' LIMIT 1),
  'AMD EPYC 7742 64-Core 128-Thread Server Processor',
  'amd-epyc-7742-64-core-128-thread-server-processor',
  'IB-CMP-3004',
  'amd-epyc-7742-64-core-128-thre',
  'refurbished',
  'High-grade AMD component. 64 Cores / 128 Threads • 2.25 GHz Base / 3.4 GHz Boost • 256MB L3 Cache • 225W TDP • Socket SP3',
  'Authentic server component tested for compatibility and stability in critical server systems. 64 Cores / 128 Threads • 2.25 GHz Base / 3.4 GHz Boost • 256MB L3 Cache • 225W TDP • Socket SP3.',
  8500000,
  11000000,
  20,
  1,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'amd-epyc-7742-64-core-128-thread-server-processor'),
  'images/server-hud.png',
  'AMD EPYC 7742 64-Core 128-Thread Server Processor',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'amd-epyc-7742-64-core-128-thread-server-processor'),
  'Specifications',
  'Brand',
  'AMD',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'amd-epyc-7742-64-core-128-thread-server-processor'),
  'Specifications',
  'Form Factor',
  'Socket SP3 (LGA 4094)',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'amd-epyc-7742-64-core-128-thread-server-processor'),
  'Specifications',
  'Technical Specifications',
  '64 Cores / 128 Threads • 2.25 GHz Base / 3.4 GHz Boost • 256MB L3 Cache • 225W TDP • Socket SP3',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'amd-epyc-7742-64-core-128-thread-server-processor'),
  'Specifications',
  'Hardware Condition',
  'Certified Refurbished / Clean Pull',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'amd-epyc-processors' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'amd' LIMIT 1),
  'AMD EPYC 7551 32-Core 64-Thread Server Processor',
  'amd-epyc-7551-32-core-64-thread-server-processor',
  'IB-CMP-3005',
  'amd-epyc-7551-32-core-64-threa',
  'refurbished',
  'High-grade AMD component. 32 Cores / 64 Threads • 2.0 GHz Base / 3.0 GHz Boost • 64MB L3 Cache • 180W TDP • PCIe 3.0 128 Lanes',
  'Authentic server component tested for compatibility and stability in critical server systems. 32 Cores / 64 Threads • 2.0 GHz Base / 3.0 GHz Boost • 64MB L3 Cache • 180W TDP • PCIe 3.0 128 Lanes.',
  8500000,
  11000000,
  20,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'amd-epyc-7551-32-core-64-thread-server-processor'),
  'images/server-hud.png',
  'AMD EPYC 7551 32-Core 64-Thread Server Processor',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'amd-epyc-7551-32-core-64-thread-server-processor'),
  'Specifications',
  'Brand',
  'AMD',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'amd-epyc-7551-32-core-64-thread-server-processor'),
  'Specifications',
  'Form Factor',
  'Socket SP3 Server CPU',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'amd-epyc-7551-32-core-64-thread-server-processor'),
  'Specifications',
  'Technical Specifications',
  '32 Cores / 64 Threads • 2.0 GHz Base / 3.0 GHz Boost • 64MB L3 Cache • 180W TDP • PCIe 3.0 128 Lanes',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'amd-epyc-7551-32-core-64-thread-server-processor'),
  'Specifications',
  'Hardware Condition',
  'Certified Refurbished',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'processors' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'intel' LIMIT 1),
  'Intel Xeon Gold 6248 Skylake Scalable Processor',
  'intel-xeon-gold-6248-skylake-scalable-processor',
  'IB-CMP-3006',
  'intel-xeon-gold-6248-skylake-s',
  'refurbished',
  'High-grade Intel component. 20 Cores / 40 Threads • 2.50 GHz Base / 3.90 GHz Turbo • 27.5 MB Cache • 150W • LGA3647',
  'Authentic server component tested for compatibility and stability in critical server systems. 20 Cores / 40 Threads • 2.50 GHz Base / 3.90 GHz Turbo • 27.5 MB Cache • 150W • LGA3647.',
  4500000,
  6200000,
  20,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'intel-xeon-gold-6248-skylake-scalable-processor'),
  'images/server-hud.png',
  'Intel Xeon Gold 6248 Skylake Scalable Processor',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'intel-xeon-gold-6248-skylake-scalable-processor'),
  'Specifications',
  'Brand',
  'Intel',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'intel-xeon-gold-6248-skylake-scalable-processor'),
  'Specifications',
  'Form Factor',
  'FCLGA3647 Socket P',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'intel-xeon-gold-6248-skylake-scalable-processor'),
  'Specifications',
  'Technical Specifications',
  '20 Cores / 40 Threads • 2.50 GHz Base / 3.90 GHz Turbo • 27.5 MB Cache • 150W • LGA3647',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'intel-xeon-gold-6248-skylake-scalable-processor'),
  'Specifications',
  'Hardware Condition',
  'Certified Refurbished',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'processors' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'intel' LIMIT 1),
  'Intel Xeon E5-2680 v4 Broadwell 14-Core Processor',
  'intel-xeon-e5-2680-v4-broadwell-14-core-processor',
  'IB-CMP-3007',
  'intel-xeon-e5-2680-v4-broadwel',
  'refurbished',
  'High-grade Intel component. 14 Cores / 28 Threads • 2.40 GHz Base / 3.30 GHz Turbo • 35MB SmartCache • 120W • LGA2011-3',
  'Authentic server component tested for compatibility and stability in critical server systems. 14 Cores / 28 Threads • 2.40 GHz Base / 3.30 GHz Turbo • 35MB SmartCache • 120W • LGA2011-3.',
  4500000,
  6200000,
  20,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'intel-xeon-e5-2680-v4-broadwell-14-core-processor'),
  'images/server-hud.png',
  'Intel Xeon E5-2680 v4 Broadwell 14-Core Processor',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'intel-xeon-e5-2680-v4-broadwell-14-core-processor'),
  'Specifications',
  'Brand',
  'Intel',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'intel-xeon-e5-2680-v4-broadwell-14-core-processor'),
  'Specifications',
  'Form Factor',
  'FCLGA2011-v3 Socket',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'intel-xeon-e5-2680-v4-broadwell-14-core-processor'),
  'Specifications',
  'Technical Specifications',
  '14 Cores / 28 Threads • 2.40 GHz Base / 3.30 GHz Turbo • 35MB SmartCache • 120W • LGA2011-3',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'intel-xeon-e5-2680-v4-broadwell-14-core-processor'),
  'Specifications',
  'Hardware Condition',
  'Certified Refurbished',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'ddr5-server-ram' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'samsung' LIMIT 1),
  'Samsung 64GB DDR5 4800MHz ECC Registered RDIMM',
  'samsung-64gb-ddr5-4800mhz-ecc-registered-rdimm',
  'IB-CMP-3008',
  'samsung-64gb-ddr5-4800mhz-ecc-',
  'new',
  'High-grade Samsung component. DDR5-4800 PC5-38400 • Dual Rank x4 • CL40 • 1.1V • On-Die ECC & Sideband ECC Protection',
  'Authentic server component tested for compatibility and stability in critical server systems. DDR5-4800 PC5-38400 • Dual Rank x4 • CL40 • 1.1V • On-Die ECC & Sideband ECC Protection.',
  1850000,
  2400000,
  20,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'samsung-64gb-ddr5-4800mhz-ecc-registered-rdimm'),
  'images/server-hud.png',
  'Samsung 64GB DDR5 4800MHz ECC Registered RDIMM',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'samsung-64gb-ddr5-4800mhz-ecc-registered-rdimm'),
  'Specifications',
  'Brand',
  'Samsung',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'samsung-64gb-ddr5-4800mhz-ecc-registered-rdimm'),
  'Specifications',
  'Form Factor',
  '288-Pin Registered RDIMM',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'samsung-64gb-ddr5-4800mhz-ecc-registered-rdimm'),
  'Specifications',
  'Technical Specifications',
  'DDR5-4800 PC5-38400 • Dual Rank x4 • CL40 • 1.1V • On-Die ECC & Sideband ECC Protection',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'samsung-64gb-ddr5-4800mhz-ecc-registered-rdimm'),
  'Specifications',
  'Hardware Condition',
  'Brand New OEM',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'ddr4-server-ram' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'sk-hynix' LIMIT 1),
  'SK Hynix 32GB DDR4 3200MHz ECC Registered Server RAM',
  'sk-hynix-32gb-ddr4-3200mhz-ecc-registered-server-ram',
  'IB-CMP-3009',
  'sk-hynix-32gb-ddr4-3200mhz-ecc',
  'new',
  'High-grade SK Hynix component. DDR4-3200 PC4-25600R • 2Rx4 Registered Smart Memory • 1.2V Low Voltage • JEDEC Compliant',
  'Authentic server component tested for compatibility and stability in critical server systems. DDR4-3200 PC4-25600R • 2Rx4 Registered Smart Memory • 1.2V Low Voltage • JEDEC Compliant.',
  750000,
  1100000,
  20,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'sk-hynix-32gb-ddr4-3200mhz-ecc-registered-server-ram'),
  'images/server-hud.png',
  'SK Hynix 32GB DDR4 3200MHz ECC Registered Server RAM',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'sk-hynix-32gb-ddr4-3200mhz-ecc-registered-server-ram'),
  'Specifications',
  'Brand',
  'SK Hynix',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'sk-hynix-32gb-ddr4-3200mhz-ecc-registered-server-ram'),
  'Specifications',
  'Form Factor',
  '288-Pin Server RDIMM',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'sk-hynix-32gb-ddr4-3200mhz-ecc-registered-server-ram'),
  'Specifications',
  'Technical Specifications',
  'DDR4-3200 PC4-25600R • 2Rx4 Registered Smart Memory • 1.2V Low Voltage • JEDEC Compliant',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'sk-hynix-32gb-ddr4-3200mhz-ecc-registered-server-ram'),
  'Specifications',
  'Hardware Condition',
  'Brand New',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'hard-disk-drives' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'western-digital' LIMIT 1),
  'WD Ultrastar DC HC550 18TB 12Gb/s SAS Enterprise HDD',
  'wd-ultrastar-dc-hc550-18tb-12gbs-sas-enterprise-hdd',
  'IB-CMP-3010',
  'wd-ultrastar-dc-hc550-18tb-12g',
  'new',
  'High-grade Western Digital component. 18TB Capacity • 7,200 RPM • 12Gb/s SAS Dual-Port Interface • 512MB Cache • 2.5M Hours MTBF',
  'Authentic server component tested for compatibility and stability in critical server systems. 18TB Capacity • 7,200 RPM • 12Gb/s SAS Dual-Port Interface • 512MB Cache • 2.5M Hours MTBF.',
  1650000,
  2200000,
  20,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'wd-ultrastar-dc-hc550-18tb-12gbs-sas-enterprise-hdd'),
  'images/server-hud.png',
  'WD Ultrastar DC HC550 18TB 12Gb/s SAS Enterprise HDD',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'wd-ultrastar-dc-hc550-18tb-12gbs-sas-enterprise-hdd'),
  'Specifications',
  'Brand',
  'Western Digital',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'wd-ultrastar-dc-hc550-18tb-12gbs-sas-enterprise-hdd'),
  'Specifications',
  'Form Factor',
  '3.5',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'wd-ultrastar-dc-hc550-18tb-12gbs-sas-enterprise-hdd'),
  'Specifications',
  'Technical Specifications',
  '18TB Capacity • 7,200 RPM • 12Gb/s SAS Dual-Port Interface • 512MB Cache • 2.5M Hours MTBF',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'wd-ultrastar-dc-hc550-18tb-12gbs-sas-enterprise-hdd'),
  'Specifications',
  'Hardware Condition',
  'Brand New Enterprise Stock',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'other-spares' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'seagate' LIMIT 1),
  'Seagate Exos X20 20TB 12Gb/s SAS Enterprise Hard Drive',
  'seagate-exos-x20-20tb-12gbs-sas-enterprise-hard-drive',
  'IB-CMP-3011',
  'seagate-exos-x20-20tb-12gbs-sa',
  'new',
  'High-grade Seagate component. 20TB Capacity • 7,200 RPM • 12Gb/s SAS Interface • Helium Sealed Drive Design • FastFormat 512e/4Kn',
  'Authentic server component tested for compatibility and stability in critical server systems. 20TB Capacity • 7,200 RPM • 12Gb/s SAS Interface • Helium Sealed Drive Design • FastFormat 512e/4Kn.',
  1250000,
  1800000,
  20,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'seagate-exos-x20-20tb-12gbs-sas-enterprise-hard-drive'),
  'images/server-hud.png',
  'Seagate Exos X20 20TB 12Gb/s SAS Enterprise Hard Drive',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'seagate-exos-x20-20tb-12gbs-sas-enterprise-hard-drive'),
  'Specifications',
  'Brand',
  'Seagate',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'seagate-exos-x20-20tb-12gbs-sas-enterprise-hard-drive'),
  'Specifications',
  'Form Factor',
  '3.5',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'seagate-exos-x20-20tb-12gbs-sas-enterprise-hard-drive'),
  'Specifications',
  'Technical Specifications',
  '20TB Capacity • 7,200 RPM • 12Gb/s SAS Interface • Helium Sealed Drive Design • FastFormat 512e/4Kn',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'seagate-exos-x20-20tb-12gbs-sas-enterprise-hard-drive'),
  'Specifications',
  'Hardware Condition',
  'Brand New Enterprise Stock',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'solid-state-drives' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'samsung' LIMIT 1),
  'Samsung PM9A3 3.84TB PCIe 4.0 x4 NVMe U.2 Enterprise SSD',
  'samsung-pm9a3-384tb-pcie-40-x4-nvme-u2-enterprise-ssd',
  'IB-CMP-3012',
  'samsung-pm9a3-384tb-pcie-40-x4',
  'new',
  'High-grade Samsung component. PCIe Gen4 x4 NVMe 1.4 • Up to 6,900 MB/s Read / 4,100 MB/s Write • 1.0 DWPD Endurance • Power Loss Protection',
  'Authentic server component tested for compatibility and stability in critical server systems. PCIe Gen4 x4 NVMe 1.4 • Up to 6,900 MB/s Read / 4,100 MB/s Write • 1.0 DWPD Endurance • Power Loss Protection.',
  2100000,
  2900000,
  20,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'samsung-pm9a3-384tb-pcie-40-x4-nvme-u2-enterprise-ssd'),
  'images/server-hud.png',
  'Samsung PM9A3 3.84TB PCIe 4.0 x4 NVMe U.2 Enterprise SSD',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'samsung-pm9a3-384tb-pcie-40-x4-nvme-u2-enterprise-ssd'),
  'Specifications',
  'Brand',
  'Samsung',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'samsung-pm9a3-384tb-pcie-40-x4-nvme-u2-enterprise-ssd'),
  'Specifications',
  'Form Factor',
  '2.5',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'samsung-pm9a3-384tb-pcie-40-x4-nvme-u2-enterprise-ssd'),
  'Specifications',
  'Technical Specifications',
  'PCIe Gen4 x4 NVMe 1.4 • Up to 6,900 MB/s Read / 4,100 MB/s Write • 1.0 DWPD Endurance • Power Loss Protection',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'samsung-pm9a3-384tb-pcie-40-x4-nvme-u2-enterprise-ssd'),
  'Specifications',
  'Hardware Condition',
  'Brand New OEM',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'other-spares' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'dell' LIMIT 1),
  'Dell PERC H730P 2GB NV Cache 12Gb/s SAS PCIe RAID Controller',
  'dell-perc-h730p-2gb-nv-cache-12gbs-sas-pcie-raid-controller',
  'IB-CMP-3013',
  'dell-perc-h730p-2gb-nv-cache-1',
  'refurbished',
  'High-grade Dell component. 12Gb/s SAS & 6Gb/s SATA • RAID 0, 1, 5, 6, 10, 50, 60 • 2GB NV Flash Backed Cache • PCIe 3.0 x8',
  'Authentic server component tested for compatibility and stability in critical server systems. 12Gb/s SAS & 6Gb/s SATA • RAID 0, 1, 5, 6, 10, 50, 60 • 2GB NV Flash Backed Cache • PCIe 3.0 x8.',
  1250000,
  1800000,
  20,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-perc-h730p-2gb-nv-cache-12gbs-sas-pcie-raid-controller'),
  'images/server-hud.png',
  'Dell PERC H730P 2GB NV Cache 12Gb/s SAS PCIe RAID Controller',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-perc-h730p-2gb-nv-cache-12gbs-sas-pcie-raid-controller'),
  'Specifications',
  'Brand',
  'Dell',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-perc-h730p-2gb-nv-cache-12gbs-sas-pcie-raid-controller'),
  'Specifications',
  'Form Factor',
  'Low Profile / Full Height Bracket',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-perc-h730p-2gb-nv-cache-12gbs-sas-pcie-raid-controller'),
  'Specifications',
  'Technical Specifications',
  '12Gb/s SAS & 6Gb/s SATA • RAID 0, 1, 5, 6, 10, 50, 60 • 2GB NV Flash Backed Cache • PCIe 3.0 x8',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-perc-h730p-2gb-nv-cache-12gbs-sas-pcie-raid-controller'),
  'Specifications',
  'Hardware Condition',
  'Certified Refurbished',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'other-spares' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'dell' LIMIT 1),
  'Dell 1100W Hot-Plug Redundant Platinum Power Supply',
  'dell-1100w-hot-plug-redundant-platinum-power-supply',
  'IB-CMP-3014',
  'dell-1100w-hot-plug-redundant-',
  'refurbished',
  'High-grade Dell component. 1100W 80 PLUS Platinum certified • Hot-swappable • Compatible with PowerEdge R740, R740xd, R640, R730',
  'Authentic server component tested for compatibility and stability in critical server systems. 1100W 80 PLUS Platinum certified • Hot-swappable • Compatible with PowerEdge R740, R740xd, R640, R730.',
  1250000,
  1800000,
  20,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-1100w-hot-plug-redundant-platinum-power-supply'),
  'images/server-hud.png',
  'Dell 1100W Hot-Plug Redundant Platinum Power Supply',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-1100w-hot-plug-redundant-platinum-power-supply'),
  'Specifications',
  'Brand',
  'Dell',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-1100w-hot-plug-redundant-platinum-power-supply'),
  'Specifications',
  'Form Factor',
  'Hot-Plug Redundant PSU',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-1100w-hot-plug-redundant-platinum-power-supply'),
  'Specifications',
  'Technical Specifications',
  '1100W 80 PLUS Platinum certified • Hot-swappable • Compatible with PowerEdge R740, R740xd, R640, R730',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-1100w-hot-plug-redundant-platinum-power-supply'),
  'Specifications',
  'Hardware Condition',
  'Certified Refurbished / Clean Pull',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'other-spares' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'dell' LIMIT 1),
  'Dell PowerEdge R740 / R740xd ReadyRails II Sliding Rails',
  'dell-poweredge-r740-r740xd-readyrails-ii-sliding-rails',
  'IB-CMP-3015',
  'dell-poweredge-r740-r740xd-rea',
  'new',
  'High-grade Dell component. Tool-less mounting for 4-post racks • Square or unthreaded round holes • Cable Management Arm (CMA) ready',
  'Authentic server component tested for compatibility and stability in critical server systems. Tool-less mounting for 4-post racks • Square or unthreaded round holes • Cable Management Arm (CMA) ready.',
  1250000,
  1800000,
  20,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-poweredge-r740-r740xd-readyrails-ii-sliding-rails'),
  'images/server-hud.png',
  'Dell PowerEdge R740 / R740xd ReadyRails II Sliding Rails',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-poweredge-r740-r740xd-readyrails-ii-sliding-rails'),
  'Specifications',
  'Brand',
  'Dell',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-poweredge-r740-r740xd-readyrails-ii-sliding-rails'),
  'Specifications',
  'Form Factor',
  '2U Sliding Rail Kit',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-poweredge-r740-r740xd-readyrails-ii-sliding-rails'),
  'Specifications',
  'Technical Specifications',
  'Tool-less mounting for 4-post racks • Square or unthreaded round holes • Cable Management Arm (CMA) ready',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'dell-poweredge-r740-r740xd-readyrails-ii-sliding-rails'),
  'Specifications',
  'Hardware Condition',
  'Brand New OEM',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'keyboards' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  'Pro Mechanical RGB Gaming Keyboard',
  'pro-mechanical-rgb-gaming-keyboard',
  'IB-PRD-4001',
  'pro-mechanical-rgb-gaming-keyb',
  'new',
  'Pro Mechanical RGB Gaming Keyboard — Tactile Blue Switches • Per-Key RGB Lighting • Detachable USB-C Braided Cable • Anti-Ghosting.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: Tactile Blue Switches • Per-Key RGB Lighting • Detachable USB-C Braided Cable • Anti-Ghosting.',
  299900,
  404865,
  25,
  1,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'pro-mechanical-rgb-gaming-keyboard'),
  'assets/images/placeholder-keyboard.svg',
  'Pro Mechanical RGB Gaming Keyboard',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'pro-mechanical-rgb-gaming-keyboard'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'pro-mechanical-rgb-gaming-keyboard'),
  'Specifications',
  'Category',
  'Keyboards',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'pro-mechanical-rgb-gaming-keyboard'),
  'Specifications',
  'Key Features',
  'Tactile Blue Switches • Per-Key RGB Lighting • Detachable USB-C Braided Cable • Anti-Ghosting',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'keyboards' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  'Ultra-Slim Wireless Office Keyboard',
  'ultra-slim-wireless-office-keyboard',
  'IB-PRD-4002',
  'ultra-slim-wireless-office-key',
  'new',
  'Ultra-Slim Wireless Office Keyboard — Scissor-Switch Silent Keys • Dual Bluetooth 5.0 + 2.4GHz • Rechargeable 500mAh Battery • Multi-OS.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: Scissor-Switch Silent Keys • Dual Bluetooth 5.0 + 2.4GHz • Rechargeable 500mAh Battery • Multi-OS.',
  189900,
  256365,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'ultra-slim-wireless-office-keyboard'),
  'assets/images/placeholder-keyboard.svg',
  'Ultra-Slim Wireless Office Keyboard',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'ultra-slim-wireless-office-keyboard'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'ultra-slim-wireless-office-keyboard'),
  'Specifications',
  'Category',
  'Keyboards',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'ultra-slim-wireless-office-keyboard'),
  'Specifications',
  'Key Features',
  'Scissor-Switch Silent Keys • Dual Bluetooth 5.0 + 2.4GHz • Rechargeable 500mAh Battery • Multi-OS',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'keyboards' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  'Tenkeyless Custom Hot-Swap Keyboard',
  'tenkeyless-custom-hot-swap-keyboard',
  'IB-PRD-4003',
  'tenkeyless-custom-hot-swap-key',
  'new',
  'Tenkeyless Custom Hot-Swap Keyboard — Hot-Swappable 5-Pin PCB • Sound Dampening Foam • Double-Shot PBT Keycaps • Mac/Win Layout.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: Hot-Swappable 5-Pin PCB • Sound Dampening Foam • Double-Shot PBT Keycaps • Mac/Win Layout.',
  349900,
  472365,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'tenkeyless-custom-hot-swap-keyboard'),
  'assets/images/placeholder-keyboard.svg',
  'Tenkeyless Custom Hot-Swap Keyboard',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'tenkeyless-custom-hot-swap-keyboard'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'tenkeyless-custom-hot-swap-keyboard'),
  'Specifications',
  'Category',
  'Keyboards',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'tenkeyless-custom-hot-swap-keyboard'),
  'Specifications',
  'Key Features',
  'Hot-Swappable 5-Pin PCB • Sound Dampening Foam • Double-Shot PBT Keycaps • Mac/Win Layout',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'computer-mice' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  'Precision Wireless Optical Mouse',
  'precision-wireless-optical-mouse',
  'IB-PRD-4004',
  'precision-wireless-optical-mou',
  'new',
  'Precision Wireless Optical Mouse — 2400 DPI Adjustable Sensor • 2.4GHz Nano Receiver • Silent Click Buttons • 18-Month Battery Life.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: 2400 DPI Adjustable Sensor • 2.4GHz Nano Receiver • Silent Click Buttons • 18-Month Battery Life.',
  89900,
  121365,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'precision-wireless-optical-mouse'),
  'assets/images/placeholder-mouse.svg',
  'Precision Wireless Optical Mouse',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'precision-wireless-optical-mouse'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'precision-wireless-optical-mouse'),
  'Specifications',
  'Category',
  'Computer Mice',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'precision-wireless-optical-mouse'),
  'Specifications',
  'Key Features',
  '2400 DPI Adjustable Sensor • 2.4GHz Nano Receiver • Silent Click Buttons • 18-Month Battery Life',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'computer-mice' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  'Ergonomic Vertical Wireless Mouse',
  'ergonomic-vertical-wireless-mouse',
  'IB-PRD-4005',
  'ergonomic-vertical-wireless-mo',
  'new',
  'Ergonomic Vertical Wireless Mouse — 57° Natural Handshake Angle • Prevents Wrist Strain • Thumb Forward/Back Buttons • USB-C Charging.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: 57° Natural Handshake Angle • Prevents Wrist Strain • Thumb Forward/Back Buttons • USB-C Charging.',
  149900,
  202365,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'ergonomic-vertical-wireless-mouse'),
  'assets/images/placeholder-mouse.svg',
  'Ergonomic Vertical Wireless Mouse',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'ergonomic-vertical-wireless-mouse'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'ergonomic-vertical-wireless-mouse'),
  'Specifications',
  'Category',
  'Computer Mice',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'ergonomic-vertical-wireless-mouse'),
  'Specifications',
  'Key Features',
  '57° Natural Handshake Angle • Prevents Wrist Strain • Thumb Forward/Back Buttons • USB-C Charging',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'computer-mice' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  'Pro Esports RGB 16000-DPI Mouse',
  'pro-esports-rgb-16000-dpi-mouse',
  'IB-PRD-4006',
  'pro-esports-rgb-16000-dpi-mous',
  'new',
  'Pro Esports RGB 16000-DPI Mouse — PixArt PMW3389 Sensor • 68g Ultralight Honeycomb Shell • 50M Click Optical Switches • Paracord Cable.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: PixArt PMW3389 Sensor • 68g Ultralight Honeycomb Shell • 50M Click Optical Switches • Paracord Cable.',
  219900,
  296865,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'pro-esports-rgb-16000-dpi-mouse'),
  'assets/images/placeholder-mouse.svg',
  'Pro Esports RGB 16000-DPI Mouse',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'pro-esports-rgb-16000-dpi-mouse'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'pro-esports-rgb-16000-dpi-mouse'),
  'Specifications',
  'Category',
  'Computer Mice',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'pro-esports-rgb-16000-dpi-mouse'),
  'Specifications',
  'Key Features',
  'PixArt PMW3389 Sensor • 68g Ultralight Honeycomb Shell • 50M Click Optical Switches • Paracord Cable',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'webcams' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  'Full HD 1080p Streaming Webcam',
  'full-hd-1080p-streaming-webcam',
  'IB-PRD-4007',
  'full-hd-1080p-streaming-webcam',
  'new',
  'Full HD 1080p Streaming Webcam — 1080p @ 60FPS • Built-in Privacy Cover • Dual Noise-Cancelling Mics • Auto Low-Light Correction.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: 1080p @ 60FPS • Built-in Privacy Cover • Dual Noise-Cancelling Mics • Auto Low-Light Correction.',
  249900,
  337365,
  25,
  1,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'full-hd-1080p-streaming-webcam'),
  'assets/images/placeholder-webcam.svg',
  'Full HD 1080p Streaming Webcam',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'full-hd-1080p-streaming-webcam'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'full-hd-1080p-streaming-webcam'),
  'Specifications',
  'Category',
  'Webcams',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'full-hd-1080p-streaming-webcam'),
  'Specifications',
  'Key Features',
  '1080p @ 60FPS • Built-in Privacy Cover • Dual Noise-Cancelling Mics • Auto Low-Light Correction',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'webcams' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  '4K Ultra HD Conference Webcam',
  '4k-ultra-hd-conference-webcam',
  'IB-PRD-4008',
  '4k-ultra-hd-conference-webcam',
  'new',
  '4K Ultra HD Conference Webcam — Sony 4K Sensor • AI Auto-Framing & Face Tracking • Stereo Beamforming Microphones • Tripod Included.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: Sony 4K Sensor • AI Auto-Framing & Face Tracking • Stereo Beamforming Microphones • Tripod Included.',
  699900,
  944865,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '4k-ultra-hd-conference-webcam'),
  'assets/images/placeholder-webcam.svg',
  '4K Ultra HD Conference Webcam',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '4k-ultra-hd-conference-webcam'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '4k-ultra-hd-conference-webcam'),
  'Specifications',
  'Category',
  'Webcams',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '4k-ultra-hd-conference-webcam'),
  'Specifications',
  'Key Features',
  'Sony 4K Sensor • AI Auto-Framing & Face Tracking • Stereo Beamforming Microphones • Tripod Included',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'usb-hubs' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  '7-Port Powered USB 3.0 Hub',
  '7-port-powered-usb-30-hub',
  'IB-PRD-4009',
  '7-port-powered-usb-30-hub',
  'new',
  '7-Port Powered USB 3.0 Hub — 7x USB 3.0 5Gbps Ports • Individual LED Power Switches • 12V External Power Adapter • Surge Protection.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: 7x USB 3.0 5Gbps Ports • Individual LED Power Switches • 12V External Power Adapter • Surge Protection.',
  179900,
  242865,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '7-port-powered-usb-30-hub'),
  'assets/images/placeholder-generic.svg',
  '7-Port Powered USB 3.0 Hub',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '7-port-powered-usb-30-hub'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '7-port-powered-usb-30-hub'),
  'Specifications',
  'Category',
  'Usb Hubs',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '7-port-powered-usb-30-hub'),
  'Specifications',
  'Key Features',
  '7x USB 3.0 5Gbps Ports • Individual LED Power Switches • 12V External Power Adapter • Surge Protection',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'usb-hubs' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  'Aluminum 4-Port USB-C to USB-A Hub',
  'aluminum-4-port-usb-c-to-usb-a-hub',
  'IB-PRD-4010',
  'aluminum-4-port-usb-c-to-usb-a',
  'new',
  'Aluminum 4-Port USB-C to USB-A Hub — 4x USB 3.1 Gen 1 Ports • Compact Pocket Design • Heat-Dissipating Aluminum Shell • Plug & Play.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: 4x USB 3.1 Gen 1 Ports • Compact Pocket Design • Heat-Dissipating Aluminum Shell • Plug & Play.',
  119900,
  161865,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'aluminum-4-port-usb-c-to-usb-a-hub'),
  'assets/images/placeholder-generic.svg',
  'Aluminum 4-Port USB-C to USB-A Hub',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'aluminum-4-port-usb-c-to-usb-a-hub'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'aluminum-4-port-usb-c-to-usb-a-hub'),
  'Specifications',
  'Category',
  'Usb Hubs',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'aluminum-4-port-usb-c-to-usb-a-hub'),
  'Specifications',
  'Key Features',
  '4x USB 3.1 Gen 1 Ports • Compact Pocket Design • Heat-Dissipating Aluminum Shell • Plug & Play',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'laptop-chargers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  '65W USB-C GaN Fast Laptop Charger',
  '65w-usb-c-gan-fast-laptop-charger',
  'IB-PRD-4011',
  '65w-usb-c-gan-fast-laptop-char',
  'new',
  '65W USB-C GaN Fast Laptop Charger — Gallium Nitride (GaN III) Tech • Power Delivery 3.0 • Compact Folding Plug • Universal Dell/HP/MacBook.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: Gallium Nitride (GaN III) Tech • Power Delivery 3.0 • Compact Folding Plug • Universal Dell/HP/MacBook.',
  199900,
  269865,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '65w-usb-c-gan-fast-laptop-charger'),
  'assets/images/placeholder-laptop.svg',
  '65W USB-C GaN Fast Laptop Charger',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '65w-usb-c-gan-fast-laptop-charger'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '65w-usb-c-gan-fast-laptop-charger'),
  'Specifications',
  'Category',
  'Laptop Chargers',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '65w-usb-c-gan-fast-laptop-charger'),
  'Specifications',
  'Key Features',
  'Gallium Nitride (GaN III) Tech • Power Delivery 3.0 • Compact Folding Plug • Universal Dell/HP/MacBook',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'laptop-chargers' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  '100W Dual USB-C + USB-A GaN Charger',
  '100w-dual-usb-c-usb-a-gan-charger',
  'IB-PRD-4012',
  '100w-dual-usb-c-usb-a-gan-char',
  'new',
  '100W Dual USB-C + USB-A GaN Charger — 100W Max Single Port Output • 3-Port Multi-Device Simultaneous Fast Charging • Smart Temp Monitoring.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: 100W Max Single Port Output • 3-Port Multi-Device Simultaneous Fast Charging • Smart Temp Monitoring.',
  289900,
  391365,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '100w-dual-usb-c-usb-a-gan-charger'),
  'assets/images/placeholder-laptop.svg',
  '100W Dual USB-C + USB-A GaN Charger',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '100w-dual-usb-c-usb-a-gan-charger'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '100w-dual-usb-c-usb-a-gan-charger'),
  'Specifications',
  'Category',
  'Laptop Chargers',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '100w-dual-usb-c-usb-a-gan-charger'),
  'Specifications',
  'Key Features',
  '100W Max Single Port Output • 3-Port Multi-Device Simultaneous Fast Charging • Smart Temp Monitoring',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'laptop-bags' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  'Water-Resistant 15.6',
  'water-resistant-156',
  'IB-PRD-4013',
  'water-resistant-156',
  'new',
  'Water-Resistant 15.6 — High-Density Oxford Fabric • Shockproof 360° Padded Laptop Sleeve • Anti-Theft Hidden Pocket • USB Port.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: High-Density Oxford Fabric • Shockproof 360° Padded Laptop Sleeve • Anti-Theft Hidden Pocket • USB Port.',
  229900,
  310365,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'water-resistant-156'),
  'assets/images/placeholder-laptop.svg',
  'Water-Resistant 15.6',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'water-resistant-156'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'water-resistant-156'),
  'Specifications',
  'Category',
  'Laptop Bags',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'water-resistant-156'),
  'Specifications',
  'Key Features',
  'High-Density Oxford Fabric • Shockproof 360° Padded Laptop Sleeve • Anti-Theft Hidden Pocket • USB Port',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'laptop-bags' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  'Slim Shockproof 14',
  'slim-shockproof-14',
  'IB-PRD-4014',
  'slim-shockproof-14',
  'new',
  'Slim Shockproof 14 — Spill-Resistant Neoprene Shell • Soft Velvet Scratch Protection Lining • Front Accessory Zipper Pocket.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: Spill-Resistant Neoprene Shell • Soft Velvet Scratch Protection Lining • Front Accessory Zipper Pocket.',
  99900,
  134865,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'slim-shockproof-14'),
  'assets/images/placeholder-laptop.svg',
  'Slim Shockproof 14',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'slim-shockproof-14'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'slim-shockproof-14'),
  'Specifications',
  'Category',
  'Laptop Bags',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'slim-shockproof-14'),
  'Specifications',
  'Key Features',
  'Spill-Resistant Neoprene Shell • Soft Velvet Scratch Protection Lining • Front Accessory Zipper Pocket',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'laptop-stands' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  'Ergonomic Adjustable Aluminum Laptop Stand',
  'ergonomic-adjustable-aluminum-laptop-stand',
  'IB-PRD-4015',
  'ergonomic-adjustable-aluminum-',
  'new',
  'Ergonomic Adjustable Aluminum Laptop Stand — 6-Level Height & Angle Adjustment • Solid Anodized Aluminum Alloy • Silicone Anti-Scratch Pads.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: 6-Level Height & Angle Adjustment • Solid Anodized Aluminum Alloy • Silicone Anti-Scratch Pads.',
  149900,
  202365,
  25,
  1,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'ergonomic-adjustable-aluminum-laptop-stand'),
  'assets/images/placeholder-laptop.svg',
  'Ergonomic Adjustable Aluminum Laptop Stand',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'ergonomic-adjustable-aluminum-laptop-stand'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'ergonomic-adjustable-aluminum-laptop-stand'),
  'Specifications',
  'Category',
  'Laptop Stands',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'ergonomic-adjustable-aluminum-laptop-stand'),
  'Specifications',
  'Key Features',
  '6-Level Height & Angle Adjustment • Solid Anodized Aluminum Alloy • Silicone Anti-Scratch Pads',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'laptop-stands' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  '360° Swivel Heavy-Duty Laptop Desk Riser',
  '360-swivel-heavy-duty-laptop-desk-riser',
  'IB-PRD-4016',
  '360-swivel-heavy-duty-laptop-d',
  'new',
  '360° Swivel Heavy-Duty Laptop Desk Riser — Smooth 360° Rotating Turntable Base • Holds up to 17.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: Smooth 360° Rotating Turntable Base • Holds up to 17.',
  189900,
  256365,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '360-swivel-heavy-duty-laptop-desk-riser'),
  'assets/images/placeholder-laptop.svg',
  '360° Swivel Heavy-Duty Laptop Desk Riser',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '360-swivel-heavy-duty-laptop-desk-riser'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '360-swivel-heavy-duty-laptop-desk-riser'),
  'Specifications',
  'Category',
  'Laptop Stands',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '360-swivel-heavy-duty-laptop-desk-riser'),
  'Specifications',
  'Key Features',
  'Smooth 360° Rotating Turntable Base • Holds up to 17',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'docking-stations' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  '12-in-1 Triple Display USB-C Docking Station',
  '12-in-1-triple-display-usb-c-docking-station',
  'IB-PRD-4017',
  '12-in-1-triple-display-usb-c-d',
  'new',
  '12-in-1 Triple Display USB-C Docking Station — Dual 4K HDMI + DisplayPort • 100W Power Delivery Pass-through • Gigabit LAN • SD/TF Card Reader.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: Dual 4K HDMI + DisplayPort • 100W Power Delivery Pass-through • Gigabit LAN • SD/TF Card Reader.',
  849900,
  1147365,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '12-in-1-triple-display-usb-c-docking-station'),
  'assets/images/placeholder-laptop.svg',
  '12-in-1 Triple Display USB-C Docking Station',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '12-in-1-triple-display-usb-c-docking-station'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '12-in-1-triple-display-usb-c-docking-station'),
  'Specifications',
  'Category',
  'Docking Stations',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '12-in-1-triple-display-usb-c-docking-station'),
  'Specifications',
  'Key Features',
  'Dual 4K HDMI + DisplayPort • 100W Power Delivery Pass-through • Gigabit LAN • SD/TF Card Reader',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'docking-stations' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  'Thunderbolt 4 Quad-Display Workstation Dock',
  'thunderbolt-4-quad-display-workstation-dock',
  'IB-PRD-4018',
  'thunderbolt-4-quad-display-wor',
  'new',
  'Thunderbolt 4 Quad-Display Workstation Dock — 40Gbps High-Speed Bandwidth • 4x 4K 60Hz or 1x 8K Display • 96W Host Charging • 2.5G Ethernet Port.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: 40Gbps High-Speed Bandwidth • 4x 4K 60Hz or 1x 8K Display • 96W Host Charging • 2.5G Ethernet Port.',
  1899900,
  2564865,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'thunderbolt-4-quad-display-workstation-dock'),
  'assets/images/placeholder-laptop.svg',
  'Thunderbolt 4 Quad-Display Workstation Dock',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'thunderbolt-4-quad-display-workstation-dock'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'thunderbolt-4-quad-display-workstation-dock'),
  'Specifications',
  'Category',
  'Docking Stations',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'thunderbolt-4-quad-display-workstation-dock'),
  'Specifications',
  'Key Features',
  '40Gbps High-Speed Bandwidth • 4x 4K 60Hz or 1x 8K Display • 96W Host Charging • 2.5G Ethernet Port',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'led-monitors' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  '27',
  '27',
  'IB-PRD-4019',
  '27',
  'new',
  '27 — 1920x1080 Full HD IPS • 75Hz Refresh • 99% sRGB Color Gamut • Eye Care Flicker-Free • HDMI & VGA.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: 1920x1080 Full HD IPS • 75Hz Refresh • 99% sRGB Color Gamut • Eye Care Flicker-Free • HDMI & VGA.',
  1149900,
  1552365,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '27'),
  'assets/images/placeholder-monitor.svg',
  '27',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '27'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '27'),
  'Specifications',
  'Category',
  'Led Monitors',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '27'),
  'Specifications',
  'Key Features',
  '1920x1080 Full HD IPS • 75Hz Refresh • 99% sRGB Color Gamut • Eye Care Flicker-Free • HDMI & VGA',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'led-monitors' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  '24',
  '24',
  'IB-PRD-4020',
  '24',
  'new',
  '24 — Anti-Glare Matt Display • Low Blue Light Filter • Built-in Speakers • VESA 100x100 Wall Mountable.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: Anti-Glare Matt Display • Low Blue Light Filter • Built-in Speakers • VESA 100x100 Wall Mountable.',
  899900,
  1214865,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '24'),
  'assets/images/placeholder-monitor.svg',
  '24',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '24'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '24'),
  'Specifications',
  'Category',
  'Led Monitors',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '24'),
  'Specifications',
  'Key Features',
  'Anti-Glare Matt Display • Low Blue Light Filter • Built-in Speakers • VESA 100x100 Wall Mountable',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'gaming-monitors' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  '32',
  '32',
  'IB-PRD-4021',
  '32',
  'new',
  '32 — 2560x1440 2K Resolution • 1500R Curvature • 165Hz Refresh Rate • 1ms MPRT • FreeSync & G-Sync.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: 2560x1440 2K Resolution • 1500R Curvature • 165Hz Refresh Rate • 1ms MPRT • FreeSync & G-Sync.',
  2499900,
  3374865,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '32'),
  'assets/images/placeholder-monitor.svg',
  '32',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '32'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '32'),
  'Specifications',
  'Category',
  'Gaming Monitors',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '32'),
  'Specifications',
  'Key Features',
  '2560x1440 2K Resolution • 1500R Curvature • 165Hz Refresh Rate • 1ms MPRT • FreeSync & G-Sync',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'gaming-monitors' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  '27',
  '27-1',
  'IB-PRD-4022',
  '27',
  'new',
  '27 — 0.5ms GtG Response Time • HDR400 Display • Ergonomic Height Swivel Tilt Stand • RGB Backglow.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: 0.5ms GtG Response Time • HDR400 Display • Ergonomic Height Swivel Tilt Stand • RGB Backglow.',
  2749900,
  3712365,
  25,
  1,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '27-1'),
  'assets/images/placeholder-monitor.svg',
  '27',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '27-1'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '27-1'),
  'Specifications',
  'Category',
  'Gaming Monitors',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '27-1'),
  'Specifications',
  'Key Features',
  '0.5ms GtG Response Time • HDR400 Display • Ergonomic Height Swivel Tilt Stand • RGB Backglow',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'monitor-stands' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  'Gas Spring Single Monitor Desk Arm Mount',
  'gas-spring-single-monitor-desk-arm-mount',
  'IB-PRD-4023',
  'gas-spring-single-monitor-desk',
  'new',
  'Gas Spring Single Monitor Desk Arm Mount — Supports 17.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: Supports 17.',
  279900,
  377865,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'gas-spring-single-monitor-desk-arm-mount'),
  'assets/images/placeholder-generic.svg',
  'Gas Spring Single Monitor Desk Arm Mount',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'gas-spring-single-monitor-desk-arm-mount'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'gas-spring-single-monitor-desk-arm-mount'),
  'Specifications',
  'Category',
  'Monitor Stands',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'gas-spring-single-monitor-desk-arm-mount'),
  'Specifications',
  'Key Features',
  'Supports 17',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'monitor-stands' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  'Heavy-Duty Dual Monitor Desk Arm',
  'heavy-duty-dual-monitor-desk-arm',
  'IB-PRD-4024',
  'heavy-duty-dual-monitor-desk-a',
  'new',
  'Heavy-Duty Dual Monitor Desk Arm — Holds Two 32.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: Holds Two 32.',
  449900,
  607365,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'heavy-duty-dual-monitor-desk-arm'),
  'assets/images/placeholder-generic.svg',
  'Heavy-Duty Dual Monitor Desk Arm',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'heavy-duty-dual-monitor-desk-arm'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'heavy-duty-dual-monitor-desk-arm'),
  'Specifications',
  'Category',
  'Monitor Stands',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'heavy-duty-dual-monitor-desk-arm'),
  'Specifications',
  'Key Features',
  'Holds Two 32',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'display-adapters' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  'USB-C to 4K 60Hz DisplayPort Adapter',
  'usb-c-to-4k-60hz-displayport-adapter',
  'IB-PRD-4025',
  'usb-c-to-4k-60hz-displayport-a',
  'new',
  'USB-C to 4K 60Hz DisplayPort Adapter — Supports 4K @ 60Hz / 2K @ 144Hz • Unidirectional USB-C Host to DP • Braided Cable • Aluminum Shell.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: Supports 4K @ 60Hz / 2K @ 144Hz • Unidirectional USB-C Host to DP • Braided Cable • Aluminum Shell.',
  99900,
  134865,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'usb-c-to-4k-60hz-displayport-adapter'),
  'assets/images/placeholder-cable.svg',
  'USB-C to 4K 60Hz DisplayPort Adapter',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'usb-c-to-4k-60hz-displayport-adapter'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'usb-c-to-4k-60hz-displayport-adapter'),
  'Specifications',
  'Category',
  'Display Adapters',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'usb-c-to-4k-60hz-displayport-adapter'),
  'Specifications',
  'Key Features',
  'Supports 4K @ 60Hz / 2K @ 144Hz • Unidirectional USB-C Host to DP • Braided Cable • Aluminum Shell',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'display-adapters' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  'Active DisplayPort to HDMI 2.0 Adapter',
  'active-displayport-to-hdmi-20-adapter',
  'IB-PRD-4026',
  'active-displayport-to-hdmi-20-',
  'new',
  'Active DisplayPort to HDMI 2.0 Adapter — Active Signal Conversion • 4K @ 60Hz with Audio • Eyefinity Multi-Screen Ready • Gold Plated.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: Active Signal Conversion • 4K @ 60Hz with Audio • Eyefinity Multi-Screen Ready • Gold Plated.',
  79900,
  107865,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'active-displayport-to-hdmi-20-adapter'),
  'assets/images/placeholder-cable.svg',
  'Active DisplayPort to HDMI 2.0 Adapter',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'active-displayport-to-hdmi-20-adapter'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'active-displayport-to-hdmi-20-adapter'),
  'Specifications',
  'Category',
  'Display Adapters',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'active-displayport-to-hdmi-20-adapter'),
  'Specifications',
  'Key Features',
  'Active Signal Conversion • 4K @ 60Hz with Audio • Eyefinity Multi-Screen Ready • Gold Plated',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'hdmi-cables' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  'Certified Ultra High Speed 8K HDMI 2.1 Cable 2m',
  'certified-ultra-high-speed-8k-hdmi-21-cable-2m',
  'IB-PRD-4027',
  'certified-ultra-high-speed-8k-',
  'new',
  'Certified Ultra High Speed 8K HDMI 2.1 Cable 2m — 48Gbps Bandwidth • 8K @ 60Hz / 4K @ 120Hz • eARC, VRR, ALLM • 24K Gold Plated Contacts.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: 48Gbps Bandwidth • 8K @ 60Hz / 4K @ 120Hz • eARC, VRR, ALLM • 24K Gold Plated Contacts.',
  69900,
  94365,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'certified-ultra-high-speed-8k-hdmi-21-cable-2m'),
  'assets/images/placeholder-cable.svg',
  'Certified Ultra High Speed 8K HDMI 2.1 Cable 2m',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'certified-ultra-high-speed-8k-hdmi-21-cable-2m'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'certified-ultra-high-speed-8k-hdmi-21-cable-2m'),
  'Specifications',
  'Category',
  'Hdmi Cables',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'certified-ultra-high-speed-8k-hdmi-21-cable-2m'),
  'Specifications',
  'Key Features',
  '48Gbps Bandwidth • 8K @ 60Hz / 4K @ 120Hz • eARC, VRR, ALLM • 24K Gold Plated Contacts',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'hdmi-cables-display' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  'Premium High-Speed 4K HDMI 2.0 Cable 3m',
  'premium-high-speed-4k-hdmi-20-cable-3m',
  'IB-PRD-4028',
  'premium-high-speed-4k-hdmi-20-',
  'new',
  'Premium High-Speed 4K HDMI 2.0 Cable 3m — 18Gbps Speed • 4K @ 60Hz HDR • Triple Shielded Interference Protection • Durable Cotton Braid.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: 18Gbps Speed • 4K @ 60Hz HDR • Triple Shielded Interference Protection • Durable Cotton Braid.',
  49900,
  67365,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'premium-high-speed-4k-hdmi-20-cable-3m'),
  'assets/images/placeholder-cable.svg',
  'Premium High-Speed 4K HDMI 2.0 Cable 3m',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'premium-high-speed-4k-hdmi-20-cable-3m'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'premium-high-speed-4k-hdmi-20-cable-3m'),
  'Specifications',
  'Category',
  'Hdmi Cables Display',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'premium-high-speed-4k-hdmi-20-cable-3m'),
  'Specifications',
  'Key Features',
  '18Gbps Speed • 4K @ 60Hz HDR • Triple Shielded Interference Protection • Durable Cotton Braid',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'internal-ssds' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  'PCIe 4.0 NVMe M.2 1TB High-Speed Internal SSD',
  'pcie-40-nvme-m2-1tb-high-speed-internal-ssd',
  'IB-PRD-4029',
  'pcie-40-nvme-m2-1tb-high-speed',
  'new',
  'PCIe 4.0 NVMe M.2 1TB High-Speed Internal SSD — Up to 7,450 MB/s Read / 6,900 MB/s Write • 3D TLC NAND • DRAM Cache Buffer • 5-Year Endurance.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: Up to 7,450 MB/s Read / 6,900 MB/s Write • 3D TLC NAND • DRAM Cache Buffer • 5-Year Endurance.',
  649900,
  877365,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'pcie-40-nvme-m2-1tb-high-speed-internal-ssd'),
  'assets/images/placeholder-ssd.svg',
  'PCIe 4.0 NVMe M.2 1TB High-Speed Internal SSD',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'pcie-40-nvme-m2-1tb-high-speed-internal-ssd'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'pcie-40-nvme-m2-1tb-high-speed-internal-ssd'),
  'Specifications',
  'Category',
  'Internal Ssds',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'pcie-40-nvme-m2-1tb-high-speed-internal-ssd'),
  'Specifications',
  'Key Features',
  'Up to 7,450 MB/s Read / 6,900 MB/s Write • 3D TLC NAND • DRAM Cache Buffer • 5-Year Endurance',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'internal-ssds' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  '2.5',
  '25',
  'IB-PRD-4030',
  '25',
  'new',
  '2.5 — 560 MB/s Read / 530 MB/s Write • 7mm Aluminum Casing • TRIM & S.M.A.R.T. Supported • SATA 6Gb/s.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: 560 MB/s Read / 530 MB/s Write • 7mm Aluminum Casing • TRIM & S.M.A.R.T. Supported • SATA 6Gb/s.',
  319900,
  431865,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '25'),
  'assets/images/placeholder-ssd.svg',
  '2.5',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '25'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '25'),
  'Specifications',
  'Category',
  'Internal Ssds',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '25'),
  'Specifications',
  'Key Features',
  '560 MB/s Read / 530 MB/s Write • 7mm Aluminum Casing • TRIM & S.M.A.R.T. Supported • SATA 6Gb/s',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'external-ssds' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  'Rugged Portable USB 3.2 1TB External SSD',
  'rugged-portable-usb-32-1tb-external-ssd',
  'IB-PRD-4031',
  'rugged-portable-usb-32-1tb-ext',
  'new',
  'Rugged Portable USB 3.2 1TB External SSD — 1,050 MB/s High Speed • IP65 Dust and Water Resistant • Drop-resistant Rubber Bumper • Type-C.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: 1,050 MB/s High Speed • IP65 Dust and Water Resistant • Drop-resistant Rubber Bumper • Type-C.',
  799900,
  1079865,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'rugged-portable-usb-32-1tb-external-ssd'),
  'assets/images/placeholder-ssd.svg',
  'Rugged Portable USB 3.2 1TB External SSD',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'rugged-portable-usb-32-1tb-external-ssd'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'rugged-portable-usb-32-1tb-external-ssd'),
  'Specifications',
  'Category',
  'External Ssds',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'rugged-portable-usb-32-1tb-external-ssd'),
  'Specifications',
  'Key Features',
  '1,050 MB/s High Speed • IP65 Dust and Water Resistant • Drop-resistant Rubber Bumper • Type-C',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'hard-drives' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  '4TB 5400RPM 3.5',
  '4tb-5400rpm-35',
  'IB-PRD-4032',
  '4tb-5400rpm-35',
  'new',
  '4TB 5400RPM 3.5 — 4TB Storage Capacity • 256MB Cache • SATA 6Gb/s Interface • Whisper-Quiet Operation • 1M Hours MTBF.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: 4TB Storage Capacity • 256MB Cache • SATA 6Gb/s Interface • Whisper-Quiet Operation • 1M Hours MTBF.',
  749900,
  1012365,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '4tb-5400rpm-35'),
  'assets/images/placeholder-generic.svg',
  '4TB 5400RPM 3.5',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '4tb-5400rpm-35'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '4tb-5400rpm-35'),
  'Specifications',
  'Category',
  'Hard Drives',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '4tb-5400rpm-35'),
  'Specifications',
  'Key Features',
  '4TB Storage Capacity • 256MB Cache • SATA 6Gb/s Interface • Whisper-Quiet Operation • 1M Hours MTBF',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'ram-modules' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  '16GB DDR4 3200MHz Desktop RAM Module',
  '16gb-ddr4-3200mhz-desktop-ram-module',
  'IB-PRD-4033',
  '16gb-ddr4-3200mhz-desktop-ram-',
  'new',
  '16GB DDR4 3200MHz Desktop RAM Module — CL16 Latency • 1.35V XMP 2.0 Auto Overclocking • Black Aluminum Heat Spreader • 288-Pin UDIMM.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: CL16 Latency • 1.35V XMP 2.0 Auto Overclocking • Black Aluminum Heat Spreader • 288-Pin UDIMM.',
  329900,
  445365,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '16gb-ddr4-3200mhz-desktop-ram-module'),
  'images/ddr4-server-ram.png',
  '16GB DDR4 3200MHz Desktop RAM Module',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '16gb-ddr4-3200mhz-desktop-ram-module'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '16gb-ddr4-3200mhz-desktop-ram-module'),
  'Specifications',
  'Category',
  'Ram Modules',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '16gb-ddr4-3200mhz-desktop-ram-module'),
  'Specifications',
  'Key Features',
  'CL16 Latency • 1.35V XMP 2.0 Auto Overclocking • Black Aluminum Heat Spreader • 288-Pin UDIMM',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'ram-modules' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  '32GB (2x16GB) DDR5 5600MHz High-Performance RAM Kit',
  '32gb-2x16gb-ddr5-5600mhz-high-performance-ram-kit',
  'IB-PRD-4034',
  '32gb-2x16gb-ddr5-5600mhz-high-',
  'new',
  '32GB (2x16GB) DDR5 5600MHz High-Performance RAM Kit — Intel XMP 3.0 & AMD EXPO Ready • On-Die ECC Error Correction • PMIC Power Efficiency • CL36.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: Intel XMP 3.0 & AMD EXPO Ready • On-Die ECC Error Correction • PMIC Power Efficiency • CL36.',
  899900,
  1214865,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '32gb-2x16gb-ddr5-5600mhz-high-performance-ram-kit'),
  'images/ddr4-server-ram.png',
  '32GB (2x16GB) DDR5 5600MHz High-Performance RAM Kit',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '32gb-2x16gb-ddr5-5600mhz-high-performance-ram-kit'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '32gb-2x16gb-ddr5-5600mhz-high-performance-ram-kit'),
  'Specifications',
  'Category',
  'Ram Modules',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = '32gb-2x16gb-ddr5-5600mhz-high-performance-ram-kit'),
  'Specifications',
  'Key Features',
  'Intel XMP 3.0 & AMD EXPO Ready • On-Die ECC Error Correction • PMIC Power Efficiency • CL36',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'graphics-cards' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  'GeForce RTX 4060 8GB GDDR6 Graphics Card',
  'geforce-rtx-4060-8gb-gddr6-graphics-card',
  'IB-PRD-4035',
  'geforce-rtx-4060-8gb-gddr6-gra',
  'new',
  'GeForce RTX 4060 8GB GDDR6 Graphics Card — Ada Lovelace Architecture • DLSS 3 Frame Generation • 3x DisplayPort 1.4a + 1x HDMI 2.1a • Dual Fan.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: Ada Lovelace Architecture • DLSS 3 Frame Generation • 3x DisplayPort 1.4a + 1x HDMI 2.1a • Dual Fan.',
  2899900,
  3914865,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'geforce-rtx-4060-8gb-gddr6-graphics-card'),
  'assets/images/placeholder-generic.svg',
  'GeForce RTX 4060 8GB GDDR6 Graphics Card',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'geforce-rtx-4060-8gb-gddr6-graphics-card'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'geforce-rtx-4060-8gb-gddr6-graphics-card'),
  'Specifications',
  'Category',
  'Graphics Cards',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'geforce-rtx-4060-8gb-gddr6-graphics-card'),
  'Specifications',
  'Key Features',
  'Ada Lovelace Architecture • DLSS 3 Frame Generation • 3x DisplayPort 1.4a + 1x HDMI 2.1a • Dual Fan',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'usb-cables' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  'Braided USB-C to USB-C 100W PD Cable 2m',
  'braided-usb-c-to-usb-c-100w-pd-cable-2m',
  'IB-PRD-4036',
  'braided-usb-c-to-usb-c-100w-pd',
  'new',
  'Braided USB-C to USB-C 100W PD Cable 2m — E-Marker Chip for 100W (20V/5A) Fast Charging • 480Mbps Data Sync • 20,000+ Bend Tested • Nylon Braided.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: E-Marker Chip for 100W (20V/5A) Fast Charging • 480Mbps Data Sync • 20,000+ Bend Tested • Nylon Braided.',
  49900,
  67365,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'braided-usb-c-to-usb-c-100w-pd-cable-2m'),
  'assets/images/placeholder-cable.svg',
  'Braided USB-C to USB-C 100W PD Cable 2m',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'braided-usb-c-to-usb-c-100w-pd-cable-2m'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'braided-usb-c-to-usb-c-100w-pd-cable-2m'),
  'Specifications',
  'Category',
  'Usb Cables',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'braided-usb-c-to-usb-c-100w-pd-cable-2m'),
  'Specifications',
  'Key Features',
  'E-Marker Chip for 100W (20V/5A) Fast Charging • 480Mbps Data Sync • 20,000+ Bend Tested • Nylon Braided',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'usb-cables' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  'Heavy-Duty USB 3.0 to Type-C Sync Cable 1.5m',
  'heavy-duty-usb-30-to-type-c-sync-cable-15m',
  'IB-PRD-4037',
  'heavy-duty-usb-30-to-type-c-sy',
  'new',
  'Heavy-Duty USB 3.0 to Type-C Sync Cable 1.5m — 5Gbps SuperSpeed Data Transfer • 3A Fast Charging Support • Aluminum Shielding • Tangle-Free.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: 5Gbps SuperSpeed Data Transfer • 3A Fast Charging Support • Aluminum Shielding • Tangle-Free.',
  34900,
  47115,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'heavy-duty-usb-30-to-type-c-sync-cable-15m'),
  'assets/images/placeholder-cable.svg',
  'Heavy-Duty USB 3.0 to Type-C Sync Cable 1.5m',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'heavy-duty-usb-30-to-type-c-sync-cable-15m'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'heavy-duty-usb-30-to-type-c-sync-cable-15m'),
  'Specifications',
  'Category',
  'Usb Cables',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'heavy-duty-usb-30-to-type-c-sync-cable-15m'),
  'Specifications',
  'Key Features',
  '5Gbps SuperSpeed Data Transfer • 3A Fast Charging Support • Aluminum Shielding • Tangle-Free',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'ethernet-cables' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  'Cat 6 Shielded RJ45 Gigabit Ethernet Patch Cord 5m',
  'cat-6-shielded-rj45-gigabit-ethernet-patch-cord-5m',
  'IB-PRD-4038',
  'cat-6-shielded-rj45-gigabit-et',
  'new',
  'Cat 6 Shielded RJ45 Gigabit Ethernet Patch Cord 5m — 1000BASE-T Gigabit Bandwidth • 250MHz Frequency • 26AWG 100% Bare Copper • Snagless Strain Relief.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: 1000BASE-T Gigabit Bandwidth • 250MHz Frequency • 26AWG 100% Bare Copper • Snagless Strain Relief.',
  39900,
  53865,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cat-6-shielded-rj45-gigabit-ethernet-patch-cord-5m'),
  'assets/images/placeholder-cable.svg',
  'Cat 6 Shielded RJ45 Gigabit Ethernet Patch Cord 5m',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cat-6-shielded-rj45-gigabit-ethernet-patch-cord-5m'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cat-6-shielded-rj45-gigabit-ethernet-patch-cord-5m'),
  'Specifications',
  'Category',
  'Ethernet Cables',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cat-6-shielded-rj45-gigabit-ethernet-patch-cord-5m'),
  'Specifications',
  'Key Features',
  '1000BASE-T Gigabit Bandwidth • 250MHz Frequency • 26AWG 100% Bare Copper • Snagless Strain Relief',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'ethernet-cables' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  'Cat 7 High-Speed 10Gbps Flat Network Cable 10m',
  'cat-7-high-speed-10gbps-flat-network-cable-10m',
  'IB-PRD-4039',
  'cat-7-high-speed-10gbps-flat-n',
  'new',
  'Cat 7 High-Speed 10Gbps Flat Network Cable 10m — 10Gbps Data Speed • 600MHz Frequency • Shielded Twisted Pair (STP) • Ultra-Flat Under-Carpet Design.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: 10Gbps Data Speed • 600MHz Frequency • Shielded Twisted Pair (STP) • Ultra-Flat Under-Carpet Design.',
  79900,
  107865,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cat-7-high-speed-10gbps-flat-network-cable-10m'),
  'assets/images/placeholder-cable.svg',
  'Cat 7 High-Speed 10Gbps Flat Network Cable 10m',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cat-7-high-speed-10gbps-flat-network-cable-10m'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cat-7-high-speed-10gbps-flat-network-cable-10m'),
  'Specifications',
  'Category',
  'Ethernet Cables',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'cat-7-high-speed-10gbps-flat-network-cable-10m'),
  'Specifications',
  'Key Features',
  '10Gbps Data Speed • 600MHz Frequency • Shielded Twisted Pair (STP) • Ultra-Flat Under-Carpet Design',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'network-adapters' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  'USB 3.0 to Gigabit Ethernet LAN Adapter',
  'usb-30-to-gigabit-ethernet-lan-adapter',
  'IB-PRD-4040',
  'usb-30-to-gigabit-ethernet-lan',
  'new',
  'USB 3.0 to Gigabit Ethernet LAN Adapter — Realtek RTL8153 Chipset • 10/100/1000 Mbps Auto Sensing • Wake-on-LAN • Driverless Mac/Windows/Linux.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: Realtek RTL8153 Chipset • 10/100/1000 Mbps Auto Sensing • Wake-on-LAN • Driverless Mac/Windows/Linux.',
  89900,
  121365,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'usb-30-to-gigabit-ethernet-lan-adapter'),
  'assets/images/placeholder-generic.svg',
  'USB 3.0 to Gigabit Ethernet LAN Adapter',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'usb-30-to-gigabit-ethernet-lan-adapter'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'usb-30-to-gigabit-ethernet-lan-adapter'),
  'Specifications',
  'Category',
  'Network Adapters',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'usb-30-to-gigabit-ethernet-lan-adapter'),
  'Specifications',
  'Key Features',
  'Realtek RTL8153 Chipset • 10/100/1000 Mbps Auto Sensing • Wake-on-LAN • Driverless Mac/Windows/Linux',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'wifi-adapters' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  'Wi-Fi 6 AX1800 Dual Band USB 3.0 Wi-Fi Adapter',
  'wi-fi-6-ax1800-dual-band-usb-30-wi-fi-adapter',
  'IB-PRD-4041',
  'wi-fi-6-ax1800-dual-band-usb-3',
  'new',
  'Wi-Fi 6 AX1800 Dual Band USB 3.0 Wi-Fi Adapter — 1201 Mbps (5GHz) + 574 Mbps (2.4GHz) • Dual 5dBi High-Gain Antennas • WPA3 Security • OFDMA & MU-MIMO.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: 1201 Mbps (5GHz) + 574 Mbps (2.4GHz) • Dual 5dBi High-Gain Antennas • WPA3 Security • OFDMA & MU-MIMO.',
  169900,
  229365,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'wi-fi-6-ax1800-dual-band-usb-30-wi-fi-adapter'),
  'assets/images/placeholder-generic.svg',
  'Wi-Fi 6 AX1800 Dual Band USB 3.0 Wi-Fi Adapter',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'wi-fi-6-ax1800-dual-band-usb-30-wi-fi-adapter'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'wi-fi-6-ax1800-dual-band-usb-30-wi-fi-adapter'),
  'Specifications',
  'Category',
  'Wifi Adapters',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'wi-fi-6-ax1800-dual-band-usb-30-wi-fi-adapter'),
  'Specifications',
  'Key Features',
  '1201 Mbps (5GHz) + 574 Mbps (2.4GHz) • Dual 5dBi High-Gain Antennas • WPA3 Security • OFDMA & MU-MIMO',
  0
);
INSERT INTO `products` (`category_id`, `brand_id`, `title`, `slug`, `sku`, `model`, `condition_type`, `short_description`, `description`, `price_paise`, `compare_price_paise`, `stock_quantity`, `is_featured`, `status`)
VALUES (
  (SELECT `id` FROM `categories` WHERE `slug` = 'wifi-adapters' LIMIT 1),
  (SELECT `id` FROM `brands` WHERE `slug` = 'iboltnetworks' LIMIT 1),
  'PCIe Wi-Fi 6E + Bluetooth 5.3 Desktop Card',
  'pcie-wi-fi-6e-bluetooth-53-desktop-card',
  'IB-PRD-4042',
  'pcie-wi-fi-6e-bluetooth-53-des',
  'new',
  'PCIe Wi-Fi 6E + Bluetooth 5.3 Desktop Card — Intel AX210 Chipset • Tri-Band 2.4G/5G/6GHz (Up to 2400Mbps) • Magnetic Antenna Base • Heatsink Included.',
  'Designed for exceptional durability, performance, and everyday reliability. Features: Intel AX210 Chipset • Tri-Band 2.4G/5G/6GHz (Up to 2400Mbps) • Magnetic Antenna Base • Heatsink Included.',
  289900,
  391365,
  25,
  0,
  'published'
);
INSERT INTO `product_images` (`product_id`, `image_path`, `alt_text`, `is_primary`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'pcie-wi-fi-6e-bluetooth-53-desktop-card'),
  'assets/images/placeholder-generic.svg',
  'PCIe Wi-Fi 6E + Bluetooth 5.3 Desktop Card',
  1,
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'pcie-wi-fi-6e-bluetooth-53-desktop-card'),
  'Specifications',
  'Brand',
  'iboltnetworks',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'pcie-wi-fi-6e-bluetooth-53-desktop-card'),
  'Specifications',
  'Category',
  'Wifi Adapters',
  0
);
INSERT INTO `product_specifications` (`product_id`, `group_name`, `spec_key`, `spec_value`, `sort_order`)
VALUES (
  (SELECT `id` FROM `products` WHERE `slug` = 'pcie-wi-fi-6e-bluetooth-53-desktop-card'),
  'Specifications',
  'Key Features',
  'Intel AX210 Chipset • Tri-Band 2.4G/5G/6GHz (Up to 2400Mbps) • Magnetic Antenna Base • Heatsink Included',
  0
);

-- ------------------------------------------------------------------------------
-- Seed Initial Homepage Merchandising Section Product Placements (Idempotent)
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

SET FOREIGN_KEY_CHECKS = 1;

SET FOREIGN_KEY_CHECKS = 1;
