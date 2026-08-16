-- Run once in phpMyAdmin after FCM Phase 3.
ALTER TABLE `device_tokens`
  ADD COLUMN `installation_id` VARCHAR(80) NULL AFTER `token_hash`,
  ADD KEY `device_tokens_user_installation_index` (`user_id`, `installation_id`);
