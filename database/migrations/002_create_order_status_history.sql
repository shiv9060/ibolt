-- ==============================================================================
-- iboltnetworks Migration 002: Order Status History Table
-- Target: MySQL 8.0+ / MariaDB 10.4+ (Compatible with Hostinger Shared Hosting & MAMP)
-- Charset: utf8mb4 / Engine: InnoDB
-- Idempotent: Safe to execute once or multiple times
-- Portable: No hardcoded database name.
-- ==============================================================================

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

