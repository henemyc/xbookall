-- RUN ONLY AFTER sql_phone_duplicate_cleanup_audit.sql RETURNS ZERO ROWS.
-- Make a database backup before running this script.

-- 1) Canonicalize all existing stored phone values to 10 digits.
UPDATE users
SET phone_number = RIGHT(REPLACE(REPLACE(REPLACE(phone_number, '+', ''), ' ', ''), '-', ''), 10)
WHERE phone_number IS NOT NULL AND phone_number <> '';

-- 2) Verify no duplicates remain. This query MUST return zero rows.
SELECT phone_number, COUNT(*) AS duplicate_count
FROM users
WHERE phone_number IS NOT NULL AND phone_number <> ''
GROUP BY phone_number
HAVING COUNT(*) > 1;

-- 3) Only after the previous query returns zero rows, add global enforcement.
-- Execute the next line separately in phpMyAdmin.
-- ALTER TABLE users ADD UNIQUE INDEX users_phone_number_global_unique (phone_number);

-- Note: if inactive/legacy accounts should release their phone number, change
-- their phone_number to NULL only after confirming they will never be restored.
