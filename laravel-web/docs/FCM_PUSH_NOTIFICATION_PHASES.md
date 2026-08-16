# GymXBook FCM Push Notifications

## Goal

Use Firebase Cloud Messaging (FCM) to deliver real Android push notifications while keeping the existing `app_notifications` database table as the in-app notification source of truth.

```
Laravel event
→ store app_notifications row
→ queue FCM push to matching device tokens
→ Android notification
→ user taps notification
→ Flutter opens relevant screen
```

## Phase 1 — Firebase foundation

### Firebase Console tasks

1. Create a Firebase project for **GymXBook Production**.
2. Add Android app package:

```
com.gymxbook.app
```

3. Download `google-services.json` and place it locally at:

```
flutter_app/android/app/google-services.json
```

4. Enable Firebase Cloud Messaging.
5. Create a service account JSON key for the Laravel server.
6. Store it outside the Laravel public web root. Recommended production path:

```
storage/app/private/firebase/gymxbook-production-fcm.json
```

7. Never upload the service account key to GitHub, public FTP folders, or browser-accessible paths.

### Required server variables

Use the real absolute server path after uploading the service account JSON:

```env
FCM_PROJECT_ID=your-firebase-project-id
FCM_SERVICE_ACCOUNT_PATH=/absolute/path/outside/public/firebase-service-account.json
```

Do not place credential JSON directly in `.env`; use a filesystem path.

## Phase 2 — Flutter registration

Dependencies planned:

```yaml
firebase_core
firebase_messaging
flutter_local_notifications
```

Responsibilities:

- request Android notification permission;
- retrieve FCM token;
- register FCM token after authenticated login;
- refresh token on rotation;
- remove token on logout;
- handle foreground/background/tapped notifications.

## Phase 3 — Laravel token storage

New table:

```text
device_tokens
```

Fields:

```text
id
user_id
token (unique)
platform
app_version
device_name
last_seen_at
created_at
updated_at
```

Every token belongs to one GymXBook user. A user can have multiple device tokens.

## Phase 4 — Delivery service

Laravel service:

```text
App\Services\FcmPushService
```

Uses FCM HTTP v1 API with service-account OAuth credentials.

Rules:

- push is never trusted as payment confirmation;
- webhook/server verification remains source of truth;
- database notification is stored before push send;
- invalid FCM tokens are deleted automatically;
- retry failures through queued jobs later.

## Phase 5 — First notification events

Initial production events:

```text
New notice
Membership expiry / renewal reminder
Member payment received
Workout assigned
Subscription status change
Super Admin targeted/broadcast notification
```

## Testing

Use a separate Firebase project for staging later:

```text
GymXBook Staging
com.gymxbook.app.staging
```

Never send test pushes from staging to production users.
