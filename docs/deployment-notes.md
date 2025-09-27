# Deployment & Testing Follow-Up

## Testing
- Regenerate caches by running the application once and verifying `storage/cache` is writable.
- Exercise the API endpoint `api/get-more-articles.php` to confirm pagination still respects the new storage paths.
- Run `composer install` (to pull dev dependencies) and execute `vendor/bin/phpunit` locally before deploying.

## Environment Variables
- Set `APP_DEBUG=false` in production to suppress error output.
- Configure web server to deny access to `storage/` (Apache `.htaccess` provided; add equivalent Nginx rules).

## Shared Hosting (Hostinger)
- Run `composer install --no-dev` locally before deployment and upload the `vendor/` directory, since Hostinger shared plans typically disallow Composer on the server.
- Ensure `storage/cache` and `storage/logs` directories are writable (`755` usually works on Hostinger).
- If using Hostinger's cron jobs, update paths to use the new `storage/` structure.

## File Permissions
- Ensure the web user can write to `storage/cache` and `storage/logs` when deployed.
- Remove any legacy cron/jobs that reference the old `cache/` or `logs/` directories.

## Housekeeping
- Update deployment scripts or rsync excludes to retain `storage/.htaccess` while ignoring runtime files per `.gitignore`.
- Consider adding a configurable cache TTL to avoid hard-coded 30-minute window in `getAllFeeds()`.
