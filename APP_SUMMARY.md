# GymXBook — Gym Management App

## Overview
GymXBook is a professional, full-featured gym management application built with **Flutter** (mobile) and **PHP** (backend). It helps gym owners manage members, attendance, payments, invoices, trainers, classes, and more — all from a single app.

---

## Tech Stack

| Component | Technology |
|-----------|-----------|
| **Mobile App** | Flutter 3.35+ / Dart 3.9+ |
| **State Management** | Riverpod |
| **Backend** | PHP (single api.php) |
| **Database** | MySQL |
| **HTTP Client** | Dio |
| **Payment Gateway** | Cashfree PG SDK |
| **Auth** | Bearer Token + Session |
| **Storage** | Flutter Secure Storage + Hive |

---

## Design System

| Element | Value |
|---------|-------|
| **Brand Color** | Fire Orange (#FF6B2C) |
| **Gradient** | Amber → Orange → Deep Red |
| **Display Font** | Space Grotesk (headings, numbers) |
| **Body Font** | Poppins (UI, labels, body) |
| **Theme** | Light + Dark mode with system follow |
| **Cards** | Rounded (20-26px), subtle shadows |
| **Animations** | FadeInUp, CountUp, TweenAnimationBuilder |

---

## Features — Admin (Gym Owner)

### 🔐 Authentication
- Email + password login
- Phone + WhatsApp OTP registration (2-step)
- Forgot password via WhatsApp OTP
- Remember me (secure token storage)
- Auto-refresh session after 5+ min background
- Subscription expiry overlay (blocks expired gyms)

### 📊 Dashboard
- Greeting hero card with fire gradient
- Total Members stat (tap → members list)
- Trainers count (tap → trainers list)
- Present Today (tap → attendance)
- Active Members count
- Revenue & Expense card (this month)
- 6 Quick Actions: Add Member, Gym QR, Invoice, Add Expense, Notices, Notifications
- Recent Members list (tappable → member detail)
- Today's Attendance with in/out times

### 👥 Members
- Member list with search (name, phone, email)
- Filter: All / Active / Expired
- Status badges: ACTIVE, EXPIRED, FROZEN, INACTIVE
- Expiry color coding (green/yellow/red)
- Add Member form:
  - Personal info (name, email, phone, DOB, gender, address, city)
  - Plan selection (compulsory)
  - Trainer assignment
  - Class assignment
  - Start/Expiry date (auto-calculated from plan)
  - Payment (registration fee, paid amount, method)
  - Summary card with balance due
  - Auto-creates invoice on add
- Member Detail page:
  - Hero card with avatar, name, email, phone
  - Action buttons: Edit, Renew, Freeze, Workout, Locker
  - Attendance history
  - Health records
  - Freeze logs
  - Assigned classes
- Edit Member (all fields)
- Renew Membership (with invoice creation)
- Freeze/Unfreeze membership (with date range)
- Hard delete member (removes all related data)
- Phone validation (10 digits, starts with 6-9)

### 🏋️ Trainers
- Trainer list with search
- Add/Edit/Delete trainers
- Trainer details (qualification, address, gender, DOB)
- Assign trainers to members

### ✅ Attendance
- Daily attendance list
- QR code check-in/check-out
- Manual check-in with member search
- Edit attendance (time picker)
- Delete attendance record
- Auto checkout after 4 hours
- Auto checkout for previous missed days
- Attendance calendar (monthly view)
- Future dates disabled

### 📅 Attendance Calendar
- Monthly calendar grid
- Color-coded days (green = present, gray = absent)
- Today highlighted with fire gradient
- Attendance count per day
- Month navigation (prev/next)

### 💰 Transactions
- Monthly transaction list
- Income (payments received)
- Expenses
- Total income/expense summary
- Net balance card (dark hero gradient)
- Month/year selector
- Tappable → opens invoice detail
- Scroll-to-collapse header

### 📄 Invoices
- Invoice list with status filter (All/Paid/Partial/Unpaid)
- Status badges: PAID (green), PARTIAL (amber), UNPAID (red)
- Create new invoice:
  - Select member
  - Add line items (title, amount, description)
  - Set payment (amount, method, date)
  - Auto-calculates subtotal, balance
- Invoice detail:
  - Invoice number, date, member
  - Line items list
  - Payments list
  - Total/Paid/Due amounts
  - Add payment button
  - Print/share invoice
- Due amount display for partial/unpaid

### 📋 Reports
- Attendance calendar (monthly)
- Monthly Income card
- Monthly Expense card
- Active Members card (tap → active members list)
- Expired Members card (tap → expired members list)
- New This Month card (tap → bottom sheet with list)
- Upcoming Payment card (7-day renewal forecast):
  - Total renewal amount
  - Individual member amounts
- Last 7 Days Attendance chart (bar chart)
- Expiring in 7 Days list
- Expired Members list
- New This Month list
- Plan Distribution breakdown

### 🎫 Notices
- Create/Edit/Delete notices
- Title + description + attachment
- Admin: Full CRUD with FAB
- Members: Read-only view

### 🔔 Notifications
- Real-time notifications list
- Badge count on app bar
- Delete individual or all
- Types: info, warning, error, success

### 💳 Subscription (SaaS Plans)
- View all available plans
- Current plan display with expiry
- Renew/Upgrade flow:
  1. Creates Cashfree payment link
  2. Opens Chrome with payment page
  3. App shows "Waiting for Payment" overlay
  4. 5-minute countdown timer
  5. Polls every 5 seconds for status
  6. Success/Failed/Timeout dialogs
  7. Deep link back to app from browser
- Payment link (browser-based, Google Play compliant)

### 🧾 Expenses
- Monthly expense list
- Add/Edit/Delete expenses
- Expense types/categories
- Total expenses summary

### 🏪 Products
- Product catalog
- Add/Edit/Delete products
- Price and discount management

### 🔒 Lockers
- Locker grid view
- Add multiple lockers at once
- Assign locker to member (with search)
- Unassign locker
- Available/Occupied status

### 📅 Events
- Event list
- Add/Delete events
- Event types
- Start/End dates

### 📊 Classes
- Class list with schedules
- Add/Edit/Delete classes
- Class fees
- Assign members to classes
- Schedule management (days, times)

### ⚙️ Settings
- Personal Profile (name, email, phone)
- Change Password
- Subscription management
- Gym Profile (business name, contact)
- Attendance QR code (view/print)
- Notices management
- Notifications
- Theme toggle (Light/Dark/System)
- App version display

### 🔍 QR Code
- Gym QR code display (for entrance)
- QR code printing
- Auto-generated attendance secret
- Member scan for check-in/check-out

---

## Features — Member (Trainee)

### 🏠 Member Dashboard
- Personal info card
- Membership expiry display
- Quick actions

### ✅ My Attendance
- Personal attendance history
- Check-in/check-out times

### 📱 QR Scan
- Scan gym QR for attendance
- Success/Error overlays
- Auto checkout after 4 hours

### 🏋️ Workout Plan
- View assigned workout plan
- JSON parsed into day + exercise cards

### 📋 Notices
- Read-only notice board

### 🔔 Notifications
- Personal notifications

### ⚙️ Settings
- Theme toggle
- Change password
- Logout

---

## Security Features
- Bearer token authentication
- API token per device
- Session management
- Secure storage (flutter_secure_storage)
- OTP verification (WhatsApp)
- Rate limiting on OTP (3 per 15 min)
- Duplicate payment prevention (60s window)
- Password hashing (bcrypt)
- QR secret per gym

---

## UI/UX Features
- Dark/Light theme with system follow
- Smooth page transitions (FadeInUp)
- Haptic feedback on navigation
- Pull-to-refresh on all lists
- Skeleton loading states
- Empty state illustrations
- Error retry with friendly messages
- Toast notifications (overlay-based)
- Bottom sheets for actions
- Search with debounce
- Scroll physics (ClampingScrollPhysics)
- Double-tap prevention (800ms debounce)
- Back button → home (with exit confirmation)
- Edge-to-edge display
- Adaptive icons

---

## API Endpoints

| Category | Endpoints |
|----------|-----------|
| **Auth** | login, logout, me, register, update_profile, change_password |
| **OTP** | send_otp, verify_otp, forgot_password_send_otp, forgot_password_verify_otp, forgot_password_reset |
| **Members** | members (CRUD), member (GET/PUT/DELETE), renew_membership, freeze_membership, unfreeze_membership |
| **Attendance** | attendance (CRUD), attendance_search, attendance_calendar |
| **Invoices** | invoices (CRUD), invoice (GET), invoice_payment |
| **Transactions** | transactions, member_transactions |
| **Reports** | reports |
| **Trainers** | trainers (CRUD), trainer (GET/PUT/DELETE) |
| **Memberships** | memberships (CRUD), membership (PUT/DELETE) |
| **Classes** | classes (CRUD), class (GET/PUT/DELETE) |
| **Expenses** | expenses (CRUD), expense (PUT/DELETE) |
| **Products** | products (CRUD), product (PUT/DELETE) |
| **Notices** | notices (CRUD), notice (PUT/DELETE) |
| **Notifications** | notifications (GET/POST/DELETE) |
| **Lockers** | lockers (CRUD), assign_locker (POST/PUT) |
| **Events** | events (CRUD), event (DELETE) |
| **Health** | healths (CRUD) |
| **Workouts** | workouts (CRUD), workout_activities (CRUD) |
| **Subscription** | subscription_plans, create_subscription_payment_link, create_subscription_order, create_subscription_checkout, verify_subscription_payment, cancel_subscription_order |
| **Settings** | settings (GET/POST), smtp_settings |
| **WhatsApp** | test_whatsapp, whatsapp_logs, whatsapp_stats |
| **Cron** | cron_whatsapp_expired |

---

## File Structure

```
gymxbook-flutter/
├── lib/
│   ├── main.dart                          # App entry, AuthWrapper, MainShell, Drawer
│   ├── core/
│   │   ├── api/api_client.dart            # Dio HTTP client with interceptors
│   │   ├── providers/nav_provider.dart    # Navigation state
│   │   ├── storage/secure_storage.dart    # Token storage
│   │   ├── theme/app_theme.dart           # Design tokens, colors, typography
│   │   ├── theme/theme_provider.dart      # Dark/Light mode
│   │   ├── utils/date_formatter.dart      # Date formatting
│   │   └── widgets/
│   │       ├── ui.dart                    # Barrel export
│   │       ├── components.dart            # FireButton, StatTile, StatusBadge, GxAvatar, IconBadge
│   │       ├── glass.dart                 # SurfaceCard, Pressable
│   │       ├── helpers.dart               # Toast, showAppSheet, BottomNav
│   │       └── app_bottom_nav.dart        # Bottom navigation bar
│   └── features/
│       ├── auth/
│       │   ├── providers/auth_provider.dart
│       │   └── screens/
│       │       ├── login_screen.dart
│       │       └── register_screen.dart
│       ├── dashboard/screens/dashboard_screen.dart
│       ├── members/
│       │   ├── models/member.dart
│       │   ├── providers/members_provider.dart
│       │   └── screens/
│       │       ├── members_list_screen.dart
│       │       ├── member_detail_screen.dart
│       │       └── add_member_screen.dart
│       ├── attendance/screens/attendance_screen.dart
│       ├── invoices/
│       │   ├── models/invoice.dart
│       │   └── screens/
│       │       ├── invoices_list_screen.dart
│       │       ├── invoice_detail_screen.dart
│       │       └── new_invoice_screen.dart
│       ├── transactions/screens/transactions_screen.dart
│       ├── reports/screens/reports_screen.dart
│       ├── trainers/screens/trainers_list_screen.dart
│       ├── memberships/screens/memberships_screen.dart
│       ├── classes/screens/classes_screen.dart
│       ├── expenses/screens/expenses_screen.dart
│       ├── products/screens/products_screen.dart
│       ├── notices/
│       │   ├── models/notice.dart
│       │   ├── providers/notices_provider.dart
│       │   └── screens/notices_list_screen.dart
│       ├── notifications/
│       │   ├── models/notification.dart
│       │   ├── providers/notifications_provider.dart
│       │   └── screens/notifications_screen.dart
│       ├── lockers/screens/lockers_screen.dart
│       ├── events/screens/events_screen.dart
│       ├── subscription/
│       │   ├── screens/subscription_screen.dart
│       │   └── screens/subscription_detail_screen.dart
│       ├── qr/
│       │   ├── screens/admin_qr_screen.dart
│       │   └── screens/member_scan_screen.dart
│       ├── member_dashboard/screens/member_dashboard_screen.dart
│       ├── member_attendance/screens/member_attendance_screen.dart
│       ├── member_workout/screens/member_workout_screen.dart
│       └── settings/
│           └── screens/
│               ├── settings_screen.dart
│               ├── gym_profile_screen.dart
│               └── change_password_screen.dart
├── assets/images/
│   ├── gymxbook_logo_icon.png
│   ├── gymxbook_foreground_logo.png
│   └── gymxbook_background.jpg
└── pubspec.yaml

gymxbook/ (Backend)
├── api.php              # All API endpoints (3500+ lines)
├── config.php           # DB credentials, auth helpers
└── lib/
    ├── MailHelper.php
    ├── WhatsAppHelper.php
    └── whatsapp_config.php
```

---

## Build Configuration

| Setting | Value |
|---------|-------|
| **Package Name** | com.gymxbook.app |
| **Version** | 1.0.0+1 |
| **Min SDK** | 21 (Android 5.0) |
| **Target SDK** | 35 |
| **Compile SDK** | 36 |
| **AGP** | 8.9.1 |
| **Kotlin** | 2.1.0 |
| **Gradle** | 8.11.1 |
| **Keystore** | gymxbook.jks (RSA 2048, 10000 days) |

---

## Google Play Compliance
- ✅ Payment happens in browser (not in-app)
- ✅ No Google Play Billing required
- ✅ Cashfree payment link → Chrome → redirect back via deep link
- ✅ Physical service (gym membership) exemption

---

## Version History

| Version | Changes |
|---------|---------|
| **1.0.0** | Initial release — Full gym management with member, attendance, invoicing, payments, reports, QR, workouts, lockers, events, notices, notifications, subscription management |
