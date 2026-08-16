# Staff Roles — Production QA Checklist

> Phase 5 release gate document.

## Before release
- [ ] Backup every changed Laravel file from hosting.
- [ ] Run `php artisan optimize:clear` after upload.
- [ ] Build Flutter release only after API checks pass.

## Required test accounts
- [ ] Gym Owner/Admin
- [ ] Staff: view-only
- [ ] Staff: finance-only
- [ ] Staff: receptionist
- [ ] Disabled staff
- [ ] Staff from a second gym

## Permission regression tests
For every restricted action, test both Flutter UI and direct API request.

- [ ] Hidden Flutter action when permission is missing.
- [ ] Direct request returns HTTP 403 when permission is missing.
- [ ] Allowed staff action succeeds only for same-gym records.
- [ ] Staff Gym A cannot access Gym B ID.
- [ ] Gym Owner remains allowed.
- [ ] Role permission update revokes the staff login token.
- [ ] Disabled staff login token is rejected.

## Critical actions
- [ ] Member permanent delete
- [ ] Invoice delete and payment
- [ ] Expense create/edit/delete
- [ ] Product create/edit/delete
- [ ] Gym profile/SMTP blocked for staff
- [ ] Subscription payment blocked for staff
- [ ] Web login approval blocked for staff
- [ ] Staff cannot see Gym Owner-only notification
