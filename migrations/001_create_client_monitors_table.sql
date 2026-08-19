-- Client Monitoring System - Database Migration
-- Creates the client_monitors table for tracking external scripts and cron jobs

CREATE TABLE IF NOT EXISTS `client_monitors` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `client_id` VARCHAR(100) NOT NULL UNIQUE,
  `client_name` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `status` ENUM('online', 'offline', 'warning') NOT NULL DEFAULT 'offline',
  `last_seen` TIMESTAMP NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ip_address` VARCHAR(45),
  `metadata` JSON,
  `heartbeat_interval` INT NOT NULL DEFAULT 3600 COMMENT 'Expected heartbeat interval in seconds',
  `warning_threshold` DECIMAL(3,2) NOT NULL DEFAULT 1.5 COMMENT 'Multiplier for warning status',
  `offline_threshold` DECIMAL(3,2) NOT NULL DEFAULT 2.0 COMMENT 'Multiplier for offline status',
  PRIMARY KEY (`id`),
  KEY `idx_client_id` (`client_id`),
  KEY `idx_status` (`status`),
  KEY `idx_last_seen` (`last_seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
