-- ==============================================================================
-- iboltnetworks Migration 003: Login Attempts Rate Limiting & Throttling
-- Target: MySQL 8.0+ / MariaDB 10.4+ (Compatible with Hostinger Shared Hosting & MAMP)
-- Charset: utf8mb4 / Engine: InnoDB
-- Idempotent: Safe to execute once or multiple times
-- Portable: No hardcoded database name.
-- ==============================================================================

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `identifier` VARCHAR(191) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_la_ident_time` (`identifier`, `attempted_at`),
  INDEX `idx_la_ip_time` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

