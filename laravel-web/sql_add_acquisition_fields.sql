-- Run once in phpMyAdmin if you cannot run `php artisan migrate`.
ALTER TABLE `users`
  ADD COLUMN `acquisition_source` VARCHAR(50) NULL AFTER `profile`,
  ADD COLUMN `acquisition_detail` VARCHAR(255) NULL AFTER `acquisition_source`;
