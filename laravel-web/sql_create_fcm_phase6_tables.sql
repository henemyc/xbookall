-- Run once in phpMyAdmin for FCM Phase 6.

CREATE TABLE `notification_preferences` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `notices_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `super_admin_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `payments_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `membership_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `workouts_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `notification_preferences_user_id_unique` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `push_delivery_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NULL,
  `device_token_id` BIGINT UNSIGNED NULL,
  `category` VARCHAR(40) NOT NULL DEFAULT 'general',
  `title` VARCHAR(255) NOT NULL,
  `status` VARCHAR(30) NOT NULL,
  `fcm_message_id` VARCHAR(255) NULL,
  `error_message` TEXT NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `push_delivery_logs_user_id_index` (`user_id`),
  KEY `push_delivery_logs_device_token_id_index` (`device_token_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
