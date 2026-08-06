# GymXBook Tenant Isolation Checklist

## Core rule

Super Admin user id is `1`. Gym owner/admin users may have `users.parent_id = 1` because their parent is Super Admin.

Gym data must **not** use `parent_id = 1` just because Super Admin id is 1.

Correct production scoping:

```text
Super Admin user: users.id = 1, users.type = super_admin
Gym owner user:  users.type = admin/owner, users.parent_id = 1
Gym data:        parent_id = gym_owner.users.id
Staff user:      users.type = staff, users.parent_id = gym_owner.users.id
Trainer user:    users.type = trainer, users.parent_id = gym_owner.users.id
Member user:     users.type = trainee, users.parent_id = gym_owner.users.id
```

## Controller audit rules

### Use strict tenant scope for business data

Use:

```php
$pid = $this->getParentId();
$parentIds = $this->getGymParentIds();
```

Expected:

```php
$this->getGymParentIds() === [$gymOwnerId]
```

Do not use broad fallback for business tables:

```php
orWhere('parent_id', 1)  // Never for tenant data
orWhere('parent_id', 0)  // Only for true global defaults/lookups
```

### Tables that must be strict by gym owner id

```text
users (trainee/trainer/staff)
trainee_details
trainer_details
attendances
invoices
invoice_items (via invoice)
invoice_payments
expenses
memberships
classes
class_schedules
class_assigns
products
notice_boards
lockers
assign_lockers
events
workouts
workout_activities
healths
freeze_membership_logs
settings (gym settings)
activity_logs
```

### Tables allowed to include parent_id = 0

Only for true global/default lookup data:

```text
categories
types / expense types
event_types (if using global event types)
subscriptions (SaaS plans are not gym scoped)
payment_gateway_settings (platform scoped)
```

Use:

```php
$this->getGymAndGlobalParentIds()
```

for those lookup tables only.

## Manual SQL checks

Replace `GYM_ID_A` and `GYM_ID_B` with two different gym owner user ids.

### 1. Confirm gym owners belong to Super Admin

```sql
SELECT id, name, email, phone_number, type, parent_id
FROM users
WHERE type IN ('admin','owner')
ORDER BY id DESC;
```

Expected:

```text
users.parent_id = 1 for gym owners
```

### 2. Confirm gym-owned data is not stored under Super Admin id 1

```sql
SELECT 'memberships' AS table_name, COUNT(*) AS rows_under_superadmin FROM memberships WHERE parent_id = 1
UNION ALL SELECT 'classes', COUNT(*) FROM classes WHERE parent_id = 1
UNION ALL SELECT 'attendances', COUNT(*) FROM attendances WHERE parent_id = 1
UNION ALL SELECT 'invoices', COUNT(*) FROM invoices WHERE parent_id = 1
UNION ALL SELECT 'expenses', COUNT(*) FROM expenses WHERE parent_id = 1
UNION ALL SELECT 'products', COUNT(*) FROM products WHERE parent_id = 1
UNION ALL SELECT 'notice_boards', COUNT(*) FROM notice_boards WHERE parent_id = 1;
```

Expected for production tenant data:

```text
0 rows under parent_id = 1
```

If rows exist, they must be reviewed and migrated to the correct gym owner id.

### 3. Check members for one gym

```sql
SELECT COUNT(*) FROM users WHERE type = 'trainee' AND parent_id = GYM_ID_A;
SELECT COUNT(*) FROM trainee_details WHERE parent_id = GYM_ID_A;
```

Counts should match or be explainable.

### 4. Cross-gym visibility test

Login as Gym A and confirm API results do not contain Gym B records:

```bash
curl -H "Authorization: Bearer TOKEN_FOR_GYM_A" https://web.gymxbook.com/api/v1/members
curl -H "Authorization: Bearer TOKEN_FOR_GYM_A" https://web.gymxbook.com/api/v1/invoices
curl -H "Authorization: Bearer TOKEN_FOR_GYM_A" https://web.gymxbook.com/api/v1/expenses
```

Check returned `parent_id` values. Expected:

```text
Only GYM_ID_A
```

### 5. Staff scoping test

Login as Staff A under Gym A.

Expected:

```text
Dashboard counts = Gym A counts
Members list = Gym A members
Invoices = Gym A invoices
QR name = Gym A name
No Gym B data
```

### 6. Report/plan distribution test

```sql
SELECT id, title, parent_id FROM memberships WHERE parent_id IN (GYM_ID_A, GYM_ID_B, 1, 0);
```

Then login Gym A and check reports plan distribution.

Expected:

```text
Only memberships.parent_id = GYM_ID_A
```

## Release checklist

Before production release:

- [ ] Backup database.
- [ ] Copy all changed files.
- [ ] Run Super Admin → System Update → Update Now, or `php artisan migrate --force`.
- [ ] Clear cache.
- [ ] Login Gym A and verify members/invoices/reports.
- [ ] Login Gym B and verify no Gym A data.
- [ ] Login Staff A and verify Gym A only.
- [ ] Verify no tenant business data is stored under `parent_id = 1`.
- [ ] Verify no business list query uses `orWhere('parent_id', 1)`.

## Notes

If an old import stored gym data under `parent_id = 1`, do not re-enable broad fallback. Instead, migrate those rows to the correct gym owner id after identifying the gym.
