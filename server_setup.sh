#!/bin/bash
# ===================================================
# WorkFlow System - Complete Server Setup Script
# Run this on Hostinger SSH terminal
# ===================================================

set -euo pipefail

BASE="/home/u859266589/domains/dayancosys.com"
APP="$BASE/laravel_app"
WEB="$BASE/public_html"
ARCHIVE="${ARCHIVE:-/home/u859266589/workflow_release.zip}"
STAMP=$(date +%Y%m%d_%H%M%S)
RELEASE="$BASE/laravel_app_release_$STAMP"
BACKUP="$BASE/laravel_app_backup_$STAMP"

echo "======================================"
echo "  WorkFlow System - Server Setup"
echo "======================================"

if [ ! -f "$ARCHIVE" ]; then
    echo "Archive not found at $ARCHIVE"
    exit 1
fi

echo ">> Extracting uploaded release..."
rm -rf "$RELEASE"
mkdir -p "$RELEASE"
unzip -q -o "$ARCHIVE" -d "$RELEASE"
mkdir -p "$RELEASE/bootstrap/cache"
mkdir -p "$RELEASE/storage/app/public"
mkdir -p "$RELEASE/storage/framework/cache/data"
mkdir -p "$RELEASE/storage/framework/sessions"
mkdir -p "$RELEASE/storage/framework/views"
mkdir -p "$RELEASE/storage/logs"
chmod -R 775 "$RELEASE/storage" "$RELEASE/bootstrap/cache"

echo ">> Preserving runtime files..."
if [ -f "$APP/.env" ]; then
    cp "$APP/.env" "$RELEASE/.env"
fi
if [ -f "$APP/composer.lock" ]; then
    cp "$APP/composer.lock" "$RELEASE/composer.lock"
fi
if [ -d "$APP/storage/app/public" ]; then
    cp -a "$APP/storage/app/public/." "$RELEASE/storage/app/public/"
fi
if [ -d "$APP/storage/logs" ]; then
    cp -a "$APP/storage/logs/." "$RELEASE/storage/logs/"
fi
if [ ! -f "$RELEASE/.env" ] && [ -f "$RELEASE/.env.example" ]; then
    cp "$RELEASE/.env.example" "$RELEASE/.env"
fi
if [ ! -f "$RELEASE/.env" ]; then
    echo "Missing .env file. Create $APP/.env or provide one in the archive."
    exit 1
fi
mv "$RELEASE/.env.production" "$RELEASE/.env.production.bak_runtime" 2>/dev/null || true

echo ">> Installing Composer dependencies..."
cd "$RELEASE"
composer install --no-dev --optimize-autoloader --no-interaction

echo ">> Validating Laravel app..."
php artisan optimize:clear || true
php artisan about --only=environment >/dev/null
php artisan route:list >/dev/null
if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

echo ">> Running migrations and seeders..."
php artisan migrate --force
php artisan db:seed --class=DatabaseSeeder --force

echo ">> Switching live release..."
if [ -d "$APP" ]; then
    mv "$APP" "$BACKUP"
fi
mv "$RELEASE" "$APP"
cd "$APP"
mv .env.production .env.production.bak_runtime 2>/dev/null || true
php artisan optimize:clear || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

cat > "$WEB/index.php" << 'PHPEOF'
<?php
define('LARAVEL_START', microtime(true));
if (file_exists($maintenance = '/home/u859266589/domains/dayancosys.com/laravel_app/storage/framework/maintenance.php')) {
    require $maintenance;
}
require '/home/u859266589/domains/dayancosys.com/laravel_app/vendor/autoload.php';
$app = require_once '/home/u859266589/domains/dayancosys.com/laravel_app/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
)->send();
$kernel->terminate($request, $response);
PHPEOF

cat > "$WEB/.htaccess" << 'HTEOF'
<IfModule mod_rewrite.c>
    RewriteEngine On

    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
HTEOF

echo ""
echo "======================================"
echo "  Setup Complete!"
echo "======================================"
echo " Visit: https://dayancosys.com"
echo " Backup: $BACKUP"
echo " IMPORTANT: Change default passwords after first login!"
echo "======================================"
