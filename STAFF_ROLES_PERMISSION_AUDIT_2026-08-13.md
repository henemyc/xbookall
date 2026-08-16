# GymXBook — Staff Roles & Permissions Security Audit

**Audit date:** 13 August 2026  
**Scope:** Laravel mobile API, Laravel Gym Web Panel, Flutter Android/iOS UI code, staff-role lifecycle.  
**Method:** Static source-code and route audit. This report does **not** claim that production runtime tests, penetration testing, or every user journey has already been executed. Those are mandatory remediation phases listed at the end.

---

## 1. Executive conclusion

The Staff & Roles foundation is present, and the **web panel route layer is substantially better protected than the mobile API**. However, the system is **not production-secure for staff authorization yet**.

### Current security verdict

- A Gym Owner can create roles, assign valid permission keys, assign staff to a role, disable/delete staff, and revoke sessions when a role’s permissions change.
- Flutter hides many staff menus and some action buttons, but UI hiding is not security.
- The mobile Laravel API has exact staff permission enforcement only in **Products** (and member photo editing has a narrow check).
- Most mobile API modules have no exact staff permission check. A staff member can potentially use a stale screen, a modified app, or a direct API request to execute actions which the Gym Owner unticked.
- The web panel routes use `staff.permission:<key>` for most operational modules. This is a good design; it must be verified by runtime tests and completed for settings/personal access.
- Web staff Settings currently exposes Gym/Business Profile fields and the update endpoint is protected only by `settings.edit`, not Gym Owner status. A staff user granted that permission can alter Gym Name, address, business phone/email and website. This is a confirmed privilege issue.

**Overall current completion:** **~42%** of the required production authorization work.

This percentage is not product completeness. It means: “How completely are Gym Owner decisions enforced in both Flutter, Web and Laravel backend for staff users?”

---

## 2. Security standard used for this audit

A module is counted as **fully controlled** only when all four conditions are true:

1. Flutter hides the menu/page if `.view` is absent.
2. Flutter hides each individual create/edit/delete/payment/other restricted action.
3. Laravel mobile API rejects unauthorized staff with HTTP **403**.
4. Laravel web panel route/controller rejects unauthorized staff with HTTP **403**.

### Status legend

| Mark | Meaning |
|---|---|
| **FULL** | UI and both backend entry points are designed to enforce the permission. Runtime verification remains required. |
| **PARTIAL** | Some UI or one backend path is protected, but a bypass/gap remains. |
| **WEB-ONLY** | Panel route middleware is present; mobile API is not strict. |
| **RISK** | Confirmed or highly likely authorization bypass. |
| **OWNER-ONLY** | Must be restricted to `admin` / legacy `owner`, never ordinary staff. |

---

## 3. Confirmed architecture findings

### 3.1 Good controls already present

| Control | Current result |
|---|---|
| Permission catalog | `StaffRole::permissionCatalog()` centrally validates permission keys. Invalid keys are rejected. |
| Role ownership | Roles are scoped to the Gym Owner’s `parent_id`. |
| Staff-role assignment | Staff can only be assigned an active role belonging to that gym. |
| Duplicate role names | Prevented per gym. |
| Role permission update | Assigned staff Sanctum tokens are deleted. |
| Direct role reassignment | Staff tokens are deleted. |
| Disable/delete staff | Tokens are deleted. |
| Owner/legacy owner support | Existing code supports both `admin` and `owner`. Must remain compatible. |
| Panel operational routes | Most panel routes declare `staff.permission:<permission>`. |
| Product mobile API | `view/create/edit/delete` backend checks exist. |
| Member photo action | Staff photo access requires `members.edit`. |

### 3.2 Critical design defect: unsafe `isAdmin()` meaning

`BaseController::isAdmin()` currently returns true for:

```text
admin, owner, staff
```

This is unsafe when used to mean “Gym Owner access.” Trainer and Class mobile controllers currently use it. Therefore a staff user can satisfy their generic “Admin access required” check without necessarily holding the corresponding `trainers.*` or `classes.*` permission.

**Required resolution:** introduce/use an explicit Gym Owner check (`admin` or legacy `owner`) and exact staff-permission middleware. Do not rely on `isAdmin()` for authorization of staff-sensitive routes.

### 3.3 Critical design defect: mobile API permission gap

The authenticated `/api/v1` mobile routes do not currently declare `staff.permission:*` middleware. Static scan found no exact staff-permission enforcement in these mobile controllers:

```text
Member, Attendance, Invoice, Trainer, Membership, Class, Expense,
Notice, Locker, Event, Workout, Dashboard, Report, Settings,
Category, HealthRecord, Notification, WebLogin
```

Products is the principal exception.

---

## 4. Module-by-module audit table

### Scoring definition

- **Flutter UI %**: menu/page guard + action buttons checked in source.
- **Mobile API %**: exact server-side staff permission enforcement seen in source.
- **Web Panel %**: route-level permission enforcement seen in `routes/panel.php`; controller/data-scope runtime testing still required.
- **Overall %**: conservative security completion, not an assurance of safety.

| Module / actions | Required permissions | Flutter UI | Mobile API | Web panel | Overall | Current verdict / known gap |
|---|---|---:|---:|---:|---:|---|
| **Dashboard** | `dashboard.view` | 75% | 0% | 90% | **45%** | Flutter page/menu guard exists. API dashboard has no exact staff permission. Panel route has `dashboard.view`. |
| **Members** | view/create/edit/delete/renew/freeze | 45% | 0% | 90% | **35% RISK** | Member list uses permission provider, but Member Detail permanent delete is not protected in Flutter. `MemberController` has no exact checks; staff can currently delete if API permits. Panel routes are correctly mapped. |
| **Member photo** | `members.edit` | 40% | 60% | N/A | **50%** | Backend `ProfilePhotoController` checks staff `members.edit`; Flutter action visibility still needs full audit. |
| **Attendance** | view/mark/edit/delete/qr | 75% | 0% | 55% | **40% RISK** | Flutter has `attendance.mark` checks. Mobile API has no exact check. Panel only exposes view/calendar routes in current routes file; mark/edit/delete routes require separate panel route audit if they exist. |
| **Invoices** | view/create/delete/payment | 65% | 0% | 95% | **55% RISK** | Flutter list has create check; detail/payment/delete must be fully verified. Mobile controller has no exact checks. Panel routes correctly map view/create/payment/delete. |
| **Transactions** | `transactions.view` | 50% | 0% | 95% | **45% RISK** | Flutter main navigation guards route; no feature-level provider usage found. Mobile report transaction endpoints lack exact permission. Panel route is protected. |
| **Reports** | `reports.view` | 50% | 0% | 95% | **45% RISK** | Flutter navigation guard exists. `ReportController` has no staff check. Panel report and financial report routes are protected. |
| **Membership Plans** | view/create/edit/delete | 75% | 0% | 95% | **55% RISK** | Flutter screen uses provider for actions. Mobile controller uses generic `isAdmin()` for mutations and lacks exact role checks; index has no exact permission enforcement. Panel mappings are good. |
| **Trainers** | view/create/edit/delete | 80% | 0% | 95% | **55% RISK** | Flutter list/detail have several permission checks. Mobile controller uses unsafe `isAdmin()` which includes staff; exact permission enforcement absent. Panel mappings are good. |
| **Classes** | view/create/edit/delete | 75% | 0% | 95% | **55% RISK** | Flutter screen uses provider. Mobile controller uses unsafe `isAdmin()` and lacks exact permissions. Panel mappings are good. |
| **Products** | view/create/edit/delete | 50% | 100% | 95% | **80% PARTIAL** | Mobile API exact checks exist. Flutter has MainShell menu/page guard but product screen has no detected shared permission-provider action checks; create/edit/delete UI may remain visible. Panel routes are mapped. |
| **Expenses** | view/create/edit/delete | 75% | 0% | 95% | **55% RISK** | Flutter screen uses provider. Mobile API has no exact staff enforcement. Panel mappings are good. |
| **Notices** | view/create/edit/delete | 45% | 0% | 95% | **45% RISK** | MainShell menu/page guard exists, but no provider usage found in notice screens. Mobile API has no exact enforcement. Panel mappings are good. |
| **Lockers** | view/create/assign/delete | 40% | 0% | 95% | **40% RISK** | Flutter menu/page guard only detected. Mobile Locker API has no exact checks. Panel maps create/assign/delete correctly, including delete-all. |
| **Events** | view/create/edit/delete | 40% | 0% | 95% | **40% RISK** | Flutter menu/page guard only detected. Mobile Event API has no exact checks. Panel event routes are mapped; event type creation also needs explicitly mapped policy. |
| **Workouts / activity catalogue** | view/create/edit/delete | 35% | 0% | 85% | **35% RISK** | No Flutter permission-provider use found in workouts feature. Mobile Workout API has no exact checks. Panel protects activity CRUD; no panel GET workout-plan index route found in current route file. |
| **Health records** | policy required; normally members.view/edit | Unknown | 0% | Not audited as panel module | **20% RISK** | Mobile HealthRecordController has no exact staff authorization. Permission mapping needs a final product decision. |
| **Categories** | dependent module policy required | Unknown | 0% | Not explicitly routed | **20% RISK** | Category API has no exact staff checks. Decide whether it requires `products.*`, `expenses.*`, etc. |
| **Gym QR** | `attendance.qr` | 75% | API not applicable/needs route review | 95% | **70% PARTIAL** | Flutter main guard + panel route middleware present. Need runtime test that QR data cannot be fetched by unauthorized staff. |
| **Subscription** | view/pay | 40% | 0% | 95% | **45% HIGH RISK** | Staff subscription access should normally be owner-only. Mobile subscription API has no staff checks. Panel currently allows staff if explicit subscription permission is granted; policy decision required. |
| **Gym / Business settings** | **OWNER-ONLY** | 50% | 0% | 0% | **10% CRITICAL** | Web staff can see/update gym/business profile if granted `settings.edit`. Panel controller has no owner-only check. Mobile Settings API has no exact staff restriction. |
| **Personal profile/password** | own account only | 70% | Needs ownership test | 40% | **45% PARTIAL** | Should always be available to staff, independent of business settings. Panel routes currently require `settings.edit`, which conflicts with the intended “personal Settings always accessible” rule. |
| **SMTP settings** | **OWNER-ONLY** | N/A | 0% | Not explicitly in current panel routes | **15% CRITICAL** | Mobile API exposes SMTP routes without exact staff/owner restriction in controller source. Must be owner-only. |
| **Web login / QR approval** | `web_login.view` or owner-only policy | Unknown | 0% | Partial | **25% RISK** | Mobile/web login endpoints require a dedicated authorization and ownership audit. |
| **Notifications** | own notifications only | 60% | 0% | N/A | **35% RISK** | MainShell maps Notifications through dashboard guard for staff. API controller has no exact staff authorization scan result. Must verify users only access own notification IDs. |
| **Device tokens / notification preferences** | own account only | 70% | Needs ownership test | N/A | **50% PARTIAL** | Must ensure staff can only register/remove/update their own device and preference records. |
| **Bug reports / support** | own submission allowed; all-report view restricted | 60% | Needs test | N/A | **45% PARTIAL** | Submission can be allowed. Any list/update/delete must be owner/admin/super-admin only. |
| **Staff Users management** | **OWNER-ONLY** | Web feature | N/A | 95% | **90%** | Controllers call `requireGymOwner()` and token revoke logic is present. Need runtime/cross-gym test. |
| **Roles & Permissions management** | **OWNER-ONLY** | Web feature | N/A | 95% | **90%** | Controllers call `requireGymOwner()`, validate keys and scope roles by gym. Needs template work and automated tests. |
| **Staff activity log** | **OWNER-ONLY** | Web feature | N/A | 95% | **90%** | Controller calls `requireGymOwner()`. Need runtime permission and data-scope test. |

---

## 5. Web panel audit in detail

### 5.1 Strong point: operational panel route mapping

`routes/panel.php` uses `staff.permission:<key>` middleware for most of these routes:

- Dashboard
- Members (view/create/edit/delete/freeze/import)
- Invoices (view/create/payment/delete)
- Reports
- Trainers
- Expenses
- Notices
- Lockers
- Plans
- Classes
- Events
- Products
- Subscription
- Transactions
- Gym QR
- Workout activities

This is the correct direction and is significantly safer than the current mobile API route layer.

### 5.2 Panel gaps / defects

1. **Gym Business Profile is not owner-only — confirmed.**
   - `PanelSettingsController::index()` loads gym profile for any panel user.
   - `updateProfile()` writes gym profile values without checking `admin`/`owner`.
   - Routes only use `settings.view` / `settings.edit`.
   - A role with `settings.edit` can change gym name/address/etc.

2. **Personal Settings are incorrectly coupled to `settings.edit`.**
   - Web staff should always be able to edit their own personal profile/password.
   - Current routes require `settings.edit` for personal profile and password updates.
   - The design must split **Personal Settings** from **Gym/Business Settings**.

3. **Panel Workout route completeness.**
   - Activity CRUD routes are present; no `GET /workouts` route was found in the current panel route file.
   - Verify all workout plans are accessible only through mapped, permission-protected endpoints.

4. **Attendance panel action coverage.**
   - Only view/calendar routes were found in the current panel routes.
   - If attendance mark/edit/delete exists through another route, AJAX endpoint, or embedded form, it must be mapped to `attendance.mark`, `attendance.edit`, `attendance.delete`.

5. **Subscription policy needs product decision.**
   - Existing panel mappings permit a staff role to receive `subscription.view` or `subscription.pay`.
   - Recommendation: make subscription/payment permanently Gym Owner-only; do not expose it as a routine staff permission.

---

## 6. Flutter audit in detail

### Existing good mechanisms

- `main.dart` has staff menu/page permission guards.
- Staff always has access to personal Settings at index 14.
- A shared Riverpod `permissionProvider` exists.
- Screens with detected action-level permission usage:
  - Attendance
  - Classes
  - Expenses
  - Invoices list
  - Members list
  - Memberships
  - Trainers list/detail

### Flutter gaps confirmed by source scan

Screens/features with **no detected `permissionProvider` use** in their feature directory:

```text
Products
Notices
Lockers
Events
Workouts
Reports
Settings
Transactions
```

This does not prove every button is visible, because a few may use another local condition. It does prove those features are not consistently using the central permission service and need an action-by-action review.

### Confirmed Flutter bug

`member_detail_screen.dart` exposes the permanent “Delete Member” flow without a detected `members.delete` permission guard. This matches the issue reported by you.

### Flutter design rule for remediation

For every screen:

```text
No .view permission       → menu and page unavailable
.view only                → read-only mode
.create                   → add button/form only
.edit                     → edit action/form only
.delete                   → deactivate/permanent delete only
special permission        → renew/freeze/payment/assign/QR only
```

Flutter must also convert a backend 403 into a clear Hindi/English access-denied message, but backend 403 is the final security control.

---

## 7. Mobile API audit in detail

### Current mobile API risk

All listed `/api/v1` routes are inside general Sanctum authentication, but source inspection does **not** show central `staff.permission:*` middleware on those routes.

This means authentication exists, but **authorization is incomplete** for staff.

### Only confirmed exact mobile backend protections

| Endpoint family | Exact server check |
|---|---|
| Products | `products.view`, `products.create`, `products.edit`, `products.delete` |
| Member photo | staff requires `members.edit` |

### Required architecture correction

Use a route-level permission middleware for every staff-capable mobile API action, for example:

```text
GET    /members                  → members.view
POST   /members                  → members.create
PUT    /members/{id}             → members.edit
DELETE /members/{id}             → members.delete
DELETE /members/{id}/hard        → members.delete
POST   /members/{id}/renew       → members.renew
POST   /members/{id}/freeze      → members.freeze
POST   /members/{id}/unfreeze    → members.freeze
```

Owner/admin bypass remains supported; staff must hold the exact permission; trainer and trainee routes must use their own scope rules.

---

## 8. Role/session lifecycle audit

| Scenario | Current status | Result |
|---|---|---|
| Owner updates permissions in a role | Implemented | Assigned staff Sanctum tokens are deleted. |
| Owner changes a staff member’s role | Implemented | Staff tokens are deleted. |
| Owner disables staff | Implemented | Tokens are deleted. |
| Owner deletes staff | Implemented | Tokens are deleted. |
| Flutter reaction after revoked token | Partial | Must reliably clear session and show “permissions/status changed; sign in again.” |
| Staff role disabled | Partial | `staffPermissionKeys()` returns none for inactive role; all API routes still need exact middleware. |
| Permission removed while an old UI page remains open | Unsafe in most modules | Backend must reject each action with 403. |

---

## 9. Cross-gym and privilege-escalation audit requirements

Static controller inspection shows many controllers use parent IDs, but this cannot be certified without automated and runtime tests.

Mandatory test cases:

1. Staff of Gym A requests a Member/Invoice/Product/Expense/Locker/Notice ID belonging to Gym B.
2. Staff attempts to set `parent_id`, `type`, `staff_role_id`, or `is_active` in profile/CRUD requests.
3. Staff changes a role ID in a request to a role belonging to another gym.
4. Staff calls owner-only panel endpoints by direct URL.
5. Disabled staff calls API with their last known token.
6. Permission is removed while staff app is open; staff retries create/edit/delete/payment.
7. Trainer and trainee tokens call gym owner/staff routes.

Expected result: no data returned or changed; use `403` or `404` consistently; never leak cross-gym data.

---

## 10. Remediation phases

## Phase 1 — Critical hotfix (deploy first)

1. Fix Member backend authorization for all member actions.
2. Add Flutter `members.delete` control to Member Detail, including permanent delete.
3. Make Gym/Business profile owner-only in web UI and `PanelSettingsController` backend.
4. Split web personal Settings from Gym Settings; personal profile/password must be available to staff without business-settings access.
5. Remove unsafe authorization reliance on `isAdmin()` for Trainer and Classes; use exact staff permissions/owner-only rules.
6. Standardize mobile 403 response and Flutter access-denied message.

**Exit tests:** a view-only staff role cannot create/edit/delete/renew/freeze a member in Flutter, web, or direct API request; staff cannot update Gym Name/address directly.

## Phase 2 — Central mobile API authorization

1. Implement/reuse `staff.permission` middleware for `/api/v1` authenticated routes.
2. Add every API action to a reviewed permission map.
3. Deny unknown staff-sensitive routes by default.
4. Keep owner/admin and legacy owner compatibility.

**Exit tests:** direct API requests from staff without permission return 403 across the first five highest-risk modules.

## Phase 3 — Financial/destructive modules

Protect and UI-audit:

- Members
- Attendance
- Invoices/payments
- Membership plans
- Expenses
- Products

**Exit tests:** all create/edit/delete/payment/renew/freeze actions are denied server-side and hidden client-side when unticked.

## Phase 4 — Operational modules

Protect and UI-audit:

- Trainers
- Classes
- Notices
- Lockers
- Events/event types
- Workouts/activity catalogue
- Categories
- Health records
- QR

## Phase 5 — Sensitive owner-only and user-scoped modules

- Dashboard/reports/transactions
- Business profile
- SMTP
- Subscription
- Web login/QR approval
- Notifications
- Device tokens/preferences
- Bug reports

Make a final policy decision for each: exact staff permission, own-user scope, or permanently owner-only.

## Phase 6 — Web panel completion

- Verify every panel AJAX/form route is represented in `routes/panel.php` with exact middleware.
- Ensure hidden panel links are not the only barrier.
- Add explicit controller protection for owner-only flows as defense in depth.

## Phase 7 — Automated tests and release gating

Create Laravel feature tests for every protected route:

| Actor | Required result |
|---|---|
| Owner/admin | allowed for own gym |
| Legacy owner | allowed for own gym |
| Staff with exact permission | allowed for own gym |
| Staff without permission | 403 |
| Disabled staff | session/token denied |
| Staff of a different gym | 403/404, no leak |
| Trainer/trainee | denied except their scoped routes |
| No token | 401 |

Flutter tests/manual QA must cover menu, page, button, popup, swipe, detail page, deep link, stale screen and error state.

## Phase 8 — Templates and usability (after security)

Add built-in templates:

- Receptionist
- Gym Manager
- Accountant
- Trainer Assistant

Flow: **Select template → recommended permissions → optional Advanced Permissions → save custom role.**

---

## 11. Production-ready definition

Do not label Staff & Roles “production secure” until all are true:

- Every staff-capable API route has exact backend authorization.
- Flutter and Web action visibility exactly matches backend permissions.
- Owner-only business/security functions cannot be accessed by staff through UI, direct URL, old app, or API client.
- Multi-gym ID manipulation tests pass.
- Role change/disable/delete invalidates sessions and Flutter handles it cleanly.
- Full Laravel authorization test suite passes in staging and production smoke test.
- High-risk permission denials and role changes are logged.

---

## 12. Immediate priority list

1. **Member delete loophole** — critical.
2. **Web Gym Name/address/business settings loophole** — critical.
3. **Unsafe `isAdmin()` includes staff** — critical.
4. **Mobile API route-level authorization for financial/destructive modules** — critical.
5. **Flutter action-level audit for Products, Notices, Lockers, Events, Workouts, Reports, Settings and Transactions** — high.
6. **Cross-gym and automated authorization tests** — high.

---

## Appendix: Files identified in this audit

### Laravel mobile API

```text
routes/api.php
app/Http/Controllers/BaseController.php
app/Models/User.php
app/Models/StaffRole.php
app/Http/Controllers/MemberController.php
app/Http/Controllers/AttendanceController.php
app/Http/Controllers/InvoiceController.php
app/Http/Controllers/TrainerController.php
app/Http/Controllers/MembershipController.php
app/Http/Controllers/ClassController.php
app/Http/Controllers/ExpenseController.php
app/Http/Controllers/ProductController.php
app/Http/Controllers/NoticeController.php
app/Http/Controllers/LockerController.php
app/Http/Controllers/EventController.php
app/Http/Controllers/WorkoutController.php
app/Http/Controllers/SettingsController.php
app/Http/Controllers/ReportController.php
app/Http/Controllers/DashboardController.php
```

### Laravel web panel

```text
routes/panel.php
app/Http/Controllers/Panel/PanelSettingsController.php
app/Http/Controllers/Panel/PanelStaffRoleController.php
app/Http/Controllers/Panel/PanelStaffUserController.php
resources/views/panel/settings/index.blade.php
```

### Flutter

```text
lib/main.dart
lib/core/providers/permission_provider.dart
lib/features/members/screens/member_detail_screen.dart
lib/features/members/screens/members_list_screen.dart
lib/features/attendance/screens/attendance_screen.dart
lib/features/invoices/screens/invoices_list_screen.dart
lib/features/expenses/screens/expenses_screen.dart
lib/features/memberships/screens/memberships_screen.dart
lib/features/trainers/screens/trainers_list_screen.dart
lib/features/trainers/screens/trainer_detail_screen.dart
lib/features/classes/screens/classes_screen.dart
```
