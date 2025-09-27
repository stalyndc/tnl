# Project Guide for Agents

## Overview
The News Log is a PHP-based tech news aggregator. The application now routes all business logic through a service layer with Composer autoloading (`src/`). Front controllers (`index.php`, API endpoints) continue to call procedural helpers in `functions.php`, which delegate to these services.

## Getting Started
1. **Prerequisites**
   - PHP 8.1 or newer with cURL and mbstring extensions enabled
   - Composer 2.x
2. **Install Dependencies**
   ```bash
   composer install --no-dev
   ```
   This generates the `vendor/` directory required by the service layer.
3. **Writable Directories**
   Ensure `storage/cache` and `storage/logs` are writable by the web server user. The directories are created automatically on first run if they don't exist.

## Repository Structure Highlights
- `config/` – configuration files (`feeds.php`, `app.php`).
- `src/` – PSR-4 autoloaded classes.
  - `Config/FeedRepository.php` & `AppConfig.php`
  - `Cache/CacheRepository.php`
  - `Http/FeedClient.php`
  - `Services/FeedAggregator.php`
  - `Support/ContentFormatter.php`, `TimeFormatter.php`
- `public entry points` – `index.php`, `about.php`, and API scripts load `functions.php`.
- `storage/` – runtime cache and logs (never exposed publicly; `.htaccess` included).

## Common Commands
- **Lint PHP files**
  ```bash
  php -l functions.php
  php -l src/Services/FeedAggregator.php
  ```
- **Regenerate Composer autoload**
  ```bash
  composer dump-autoload
  ```
- **Clear caches manually**
  ```bash
  rm storage/cache/*.json
  ```
- **Run automated tests (requires Composer dev dependencies)**
  ```bash
  vendor/bin/phpunit
  ```

## Development Workflow
1. Add or modify services/classes inside `src/` (see the simple container registrations in `functions.php`) and regenerate the autoloader if new files are created.
2. Update configuration defaults in `config/app.php` or `config/feeds.php` as needed.
3. Run linting and the PHPUnit suite (`vendor/bin/phpunit`) before committing.
4. Document operational changes in `docs/`.

## Deployment Notes (Hostinger Shared Hosting)
- Hostinger shared plans typically restrict CLI Composer access. Run `composer install --no-dev` locally and upload the `vendor/` directory.
- Verify permissions on `storage/cache` and `storage/logs` post-deploy.
- Add equivalent deny rules in Nginx if not relying on Apache `.htaccess`.
- Update any Hostinger cron jobs to reflect the new `storage/` layout.

## Future Tasks Candidates
- Add PHPUnit with mocks for `FeedClient` and `CacheRepository`.
- Implement a lightweight dependency container to avoid manual singletons in `functions.php`.
- Add configuration UI or CLI to tweak feed sources without editing code.
- Extend deployment automation (e.g., GitHub Actions artifact that bundles `vendor/`).
