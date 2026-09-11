-- ====================================================================
-- MIGRACIÓN 006: CONTROL DE RATE LIMITING Y PROTECCIÓN CONTRA ABUSO (F11)
-- Plataforma Descartables Peruanos
-- ====================================================================

CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `identifier` VARCHAR(100) NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `attempts` INT NOT NULL DEFAULT 1,
    `window_start` INT NOT NULL,
    `blocked_until` INT NOT NULL DEFAULT 0,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_rate_id_action` (`identifier`, `action`),
    INDEX `idx_rate_blocked` (`blocked_until`),
    INDEX `idx_rate_action_window` (`action`, `window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

