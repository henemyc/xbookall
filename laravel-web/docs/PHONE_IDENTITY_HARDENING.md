# Global Phone Identity Hardening

## Policy
A normalized Indian mobile number is one global GymXBook login identity. It may
belong to only one `users` row across all gyms and user types: admin/owner,
staff, trainer, and trainee.

## Phases

1. **Central service** — normalize and globally check every phone through one
   `PhoneIdentityService`.
2. **Create flows** — registration, API member/trainer creation, panel member,
   trainer, staff, and CSV import.
3. **Update flows** — all personal/member/trainer/staff/gym profile updates.
4. **Database guard** — canonical `phone_identity` column, duplicate audit,
   then unique index after legacy duplicates are resolved.
5. **Final audit** — static code audit plus an SQL duplicate report.

## Duplicate cleanup query

Run in phpMyAdmin before the final unique index:

```sql
SELECT
  RIGHT(REPLACE(REPLACE(REPLACE(phone_number, '+', ''), ' ', ''), '-', ''), 10) AS normalized_phone,
  GROUP_CONCAT(id ORDER BY id) AS user_ids,
  GROUP_CONCAT(CONCAT(name, ' [', type, ']') ORDER BY id SEPARATOR ' | ') AS accounts,
  COUNT(*) AS duplicate_count
FROM users
WHERE phone_number IS NOT NULL AND phone_number <> ''
GROUP BY normalized_phone
HAVING COUNT(*) > 1;
```

Do not create the unique index until every result has been merged, assigned a
new number, or deactivated according to the migration plan.
