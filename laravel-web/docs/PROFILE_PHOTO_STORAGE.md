# Profile photo storage

Phase 1 adds `users.profile`. It holds a **disk-relative path** only,
for example `profile-photos/42/profile.jpg`; it never stores a public URL.

`User::profile_photo_url` resolves that path using the configured Laravel storage
disk and falls back to `/images/default-avatar.png` when no profile image exists.

## Current / local deployment

## FTP-only hosting (current setup)

Profile photos are stored directly in the web-public folder:

```text
public/uploads/profile-photos/{user_id}/uuid.jpg
```

Create this folder through FTP once:

```text
public/uploads/profile-photos/
```

No terminal command, Laravel storage disk, or `storage:link` symlink is required.

## Later S3 migration

The database field and Flutter UI will not change: `users.profile` will still
store a relative path and Flutter will still use `profile_photo_url`.

When moving to S3, replace the FTP public-folder upload implementation in
`ProfilePhotoController` with a Laravel `Storage::disk('s3')` implementation
and configure the usual S3 credentials. No member data migration is needed.
