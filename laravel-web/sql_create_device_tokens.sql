-- Run once in phpMyAdmin for FCM Phase 3.
CREATE TABLE `device_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `token` TEXT NOT NULL,
  `token_hash` VARCHAR(64) NOT NULL,
  `platform` VARCHAR(20) NOT NULL DEFAULT 'android',
  `app_version` VARCHAR(40) NULL,
  `device_name` VARCHAR(120) NULL,
  `last_seen_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `device_tokens_token_hash_unique` (`token_hash`),
  KEY `device_tokens_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
