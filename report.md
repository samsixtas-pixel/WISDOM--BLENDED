# WISDOM2 Implementation Report

## Delivery 3B–4C source audit and updates

The deployed project was checked against the Delivery 3B, Week 3/4, 4A–4C, and Delivery 4B error-audit sections of `implementing.md`. The following application changes were applied in `/opt/lampp/htdocs/WISDOM2`:

- Replaced the legacy admin monolith flow with a GET-only dashboard and a secretary-specific exam desk; added separated, linked admin routes and grouped role-aware navigation.
- Restored payment review/settings controls, directory filtering/actions, Add User form contracts, exam autocomplete/listing, notices with durations, password-reset review feedback, admin flashes, throttling/CSRF, and branded permission errors.
- Completed the XLSX reader/import service, manual review pages, DI wiring, validation/matching, and private staging path. Updated the tracked schema and applied an additive migration for import rejection counts and suggested-user review assignments.
- Added XLSX template CSV download and a CLI script for removing staged workbooks older than 30 days.
- Reworked the PDF result layout, added automatic cleanup throttling, improved the student dashboard avatar and payment messaging, exposed avatar format hints/cache busting, and updated deployment and setup documentation.
- Added Apache caching/compression and denied access to sensitive/public storage paths. Updated `.env.example`, `.gitignore`, and protected navigation/access for security diagnostics.

## Validation performed

- PHP 8.4 syntax checks passed on the changed application PHP files.
- The XLSX parser smoke test passed under XAMPP PHP 8.0 with its ZIP/DOM extensions.
- The additive exam-import database migration was applied and verified; no existing rows were deleted or modified by the migration.
- An authenticated admin session on a temporary PHP 8.4 server loaded the dashboard, users, Add User, payments, exams, notices, password-reset, Excel import/review, reports, and security pages successfully (HTTP 200). An unauthenticated route pass redirected private pages to login.
- XAMPP HTTP and filesystem checks below remain blockers; these are not counted as passed.

## Deployment blockers / not verified

- XAMPP Apache runs PHP 8.0.30, while Composer and the application require PHP 8.1+. The local PHP 8.4 development server can exercise source, but does not fix port 8080. Upgrade XAMPP's Apache PHP runtime before using the app there; the platform check was not bypassed.
- The writable private directories `storage/imports/` and `storage/backups/` are absent. Creating them with `daemon:daemon` ownership requires administrator privileges; non-interactive sudo was unavailable. The XLSX upload and backup CLI job therefore need those directories provisioned before end-to-end deployment tests.
- The temporary PHP 8.4 process runs as the workspace user and cannot write XAMPP-owned `storage/ratelimits/` or `.cleanup.lock`; its authenticated autocomplete request returned 500 for that reason. The actual XAMPP Apache worker is `daemon`, but its PHP 8.0 platform check currently prevents confirming the feature there.
- Active `/opt/lampp/etc/php.ini` and `/opt/lampp/etc/my.cnf` were inspected but not edited or restarted: privileged deployment changes require an administrator. `.env.example`/deployment templates document the intended settings. Do not disable `exec()` while using `bin/backup.php`, which relies on it.
- No Excel workbook was uploaded or imported, and no reset request, payment decision, notice, or exam record was written during verification. No staging writes were performed.

## Next deployment actions

1. Upgrade the Apache PHP runtime to 8.1+ and restart XAMPP.
2. As an administrator, create private `storage/imports/` and `storage/backups/` directories owned by the appropriate account with restrictive permissions.
3. After Apache uses supported PHP, test suggestions and the admin workflows with staging data, then run a disposable XLSX import and validate review/approve/reject.
4. Configure and verify OPcache/security settings with timestamp validation enabled in development; schedule the import cleanup and backup jobs only after access and restore testing.
