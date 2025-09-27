#!/usr/bin/env bash
set -euo pipefail

# Build deployment archive for Hostinger shared hosting.
# Run this on your local machine (not on Hostinger) where Composer is available.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST_DIR="$ROOT_DIR/dist"
ARCHIVE_NAME="the-news-log.zip"

cd "$ROOT_DIR"

printf ':: Cleaning previous build directories\n'
rm -rf "$DIST_DIR"
mkdir -p "$DIST_DIR"

printf ':: Installing production dependencies (composer install --no-dev)\n'
composer install --no-dev --optimize-autoloader

printf ':: Pruning runtime directories\n'
rm -rf storage/cache/*.json storage/logs/*.log storage/logs/*.md || true

printf ':: Packaging project\n'
zip -rq "$DIST_DIR/$ARCHIVE_NAME" \
    about.php \
    api \
    config \
    functions.php \
    img \
    includes \
    index.php \
    js \
    robots.txt \
    sitemap.xml \
    style.css \
    storage/.htaccess \
    storage/README.md \
    vendor \
    composer.json \
    composer.lock

printf '\nBuild complete: %s/%s\n' "$DIST_DIR" "$ARCHIVE_NAME"
