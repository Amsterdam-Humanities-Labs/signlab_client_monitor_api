-- Migration: Create client_metrics table for system metrics monitoring
-- Author: Client Monitor API
-- Date: 2026-01-20
-- Description: Stores hourly system metrics (CPU, disk, I/O wait) for monitored clients

CREATE TABLE `client_metrics` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `client_id` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `cpu_percent` DECIMAL(5,2) NOT NULL COMMENT 'CPU usage percentage (0-100)',
  `cpu_wait_percent` DECIMAL(5,2) DEFAULT NULL COMMENT 'I/O wait percentage (0-100)',
  `disk_usage_percent` DECIMAL(5,2) NOT NULL COMMENT 'Disk usage percentage (0-100)',
  `disk_total_gb` DECIMAL(10,2) DEFAULT NULL COMMENT 'Total disk size in GB',
  `disk_used_gb` DECIMAL(10,2) DEFAULT NULL COMMENT 'Used disk space in GB',
  `disk_free_gb` DECIMAL(10,2) DEFAULT NULL COMMENT 'Free disk space in GB',
  `memory_percent` DECIMAL(5,2) DEFAULT NULL COMMENT 'Memory usage percentage (optional)',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_client_timestamp` (`client_id`, `timestamp` DESC),
  KEY `idx_timestamp` (`timestamp`),
  CONSTRAINT `fk_metrics_client` FOREIGN KEY (`client_id`)
    REFERENCES `client_monitors` (`client_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stores hourly system metrics for clients';

-- Create automated cleanup event (7-day retention)
-- Note: event_scheduler is already enabled globally
CREATE EVENT IF NOT EXISTS cleanup_old_metrics
ON SCHEDULE EVERY 1 DAY
STARTS (TIMESTAMP(CURRENT_DATE) + INTERVAL 1 DAY + INTERVAL 2 HOUR)
COMMENT 'Delete metrics older than 7 days'
DO
  DELETE FROM client_metrics
  WHERE timestamp < DATE_SUB(NOW(), INTERVAL 7 DAY);
