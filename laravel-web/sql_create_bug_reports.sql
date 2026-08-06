-- =====================================================
-- DIRECT SQL: Create bug_reports table
-- Use this when you cannot run `php artisan migrate`
-- Run in: phpMyAdmin → SQL tab, MySQL Workbench, etc.
-- =====================================================

CREATE TABLE IF NOT EXISTS `bug_reports` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    
    `user_id` BIGINT UNSIGNED NULL,
    `gym_name` VARCHAR(255) NULL,
    `email` VARCHAR(255) NULL,
    
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    
    `screenshot_path` VARCHAR(255) NULL,
    `has_screenshot` TINYINT(1) NOT NULL DEFAULT 0,
    
    `status` VARCHAR(50) NOT NULL DEFAULT 'open',
    `admin_notes` TEXT NULL,
    
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    
    PRIMARY KEY (`id`),
    
    INDEX `bug_reports_status_created_at_index` (`status`, `created_at`),
    INDEX `bug_reports_user_id_index` (`user_id`)
) 
ENGINE=InnoDB 
DEFAULT CHARSET=utf8mb4 
COLLATE=utf8mb4_unicode_ci;

-- Optional: Add a comment to the table
ALTER TABLE `bug_reports` COMMENT = 'User bug reports submitted from GymXBook Flutter app';
