-- GYMXBOOK GLOBAL PHONE IDENTITY: SAFE AUDIT ONLY
-- Run this first in phpMyAdmin. It makes NO changes.

-- 1) Every normalized duplicate phone group with account identities.
SELECT
    RIGHT(REPLACE(REPLACE(REPLACE(phone_number, '+', ''), ' ', ''), '-', ''), 10) AS normalized_phone,
    COUNT(*) AS duplicate_count,
    GROUP_CONCAT(id ORDER BY id) AS user_ids,
    GROUP_CONCAT(CONCAT(id, ': ', name, ' [', type, '] parent=', parent_id, ' active=', is_active) ORDER BY id SEPARATOR '\n') AS accounts
FROM users
WHERE phone_number IS NOT NULL AND phone_number <> ''
GROUP BY normalized_phone
HAVING COUNT(*) > 1
ORDER BY duplicate_count DESC, normalized_phone;

-- 2) Related business-data count per duplicate candidate.
-- Use this to determine which row contains the real historical account.
SELECT
    u.id,
    u.name,
    u.type,
    u.parent_id,
    u.phone_number,
    u.is_active,
    u.created_at,
    (SELECT COUNT(*) FROM trainee_details td WHERE td.user_id = u.id) AS trainee_details_count,
    (SELECT COUNT(*) FROM trainer_details tr WHERE tr.user_id = u.id) AS trainer_details_count,
    (SELECT COUNT(*) FROM invoices i WHERE i.user_id = u.id) AS invoice_count,
    (SELECT COUNT(*) FROM attendances a WHERE a.user_id = u.id) AS attendance_count
FROM users u
WHERE RIGHT(REPLACE(REPLACE(REPLACE(u.phone_number, '+', ''), ' ', ''), '-', ''), 10) IN (
    SELECT normalized_phone FROM (
        SELECT RIGHT(REPLACE(REPLACE(REPLACE(phone_number, '+', ''), ' ', ''), '-', ''), 10) AS normalized_phone
        FROM users
        WHERE phone_number IS NOT NULL AND phone_number <> ''
        GROUP BY normalized_phone
        HAVING COUNT(*) > 1
    ) duplicate_groups
)
ORDER BY RIGHT(REPLACE(REPLACE(REPLACE(u.phone_number, '+', ''), ' ', ''), '-', ''), 10), u.id;

-- Do NOT run automatic DELETE statements. For each duplicate group, decide:
-- A. keep one real account and deactivate legacy empty rows, or
-- B. assign a different real mobile number to legitimate separate people, or
-- C. merge related data only after a reviewed backup.
