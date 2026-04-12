#!/bin/bash

# WorkFlow System Deployment Script
# This script deploys the WorkFlow system to Hostinger
set -euo pipefail

echo "🚀 Starting WorkFlow System Deployment..."

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SSH_HOST="${SSH_HOST:-u859266589@45.13.255.111}"
SSH_PORT="${SSH_PORT:-65002}"
BASE_PATH="${BASE_PATH:-/home/u859266589/domains/dayancosys.com}"
APP_PATH="${APP_PATH:-$BASE_PATH/laravel_app}"
BACKUP_PATH="${BACKUP_PATH:-/home/u859266589/backups}"
ARCHIVE_PATH_LOCAL="$(mktemp "${TMPDIR:-/tmp}/workflow_release_XXXXXX.zip")"
ARCHIVE_PATH_REMOTE="${ARCHIVE_PATH_REMOTE:-/home/u859266589/workflow_release.zip}"

cleanup() {
    rm -f "$ARCHIVE_PATH_LOCAL"
}

trap cleanup EXIT

echo "📦 Step 1: Packaging current git HEAD..."
git -C "$REPO_ROOT" archive --format=zip --output="$ARCHIVE_PATH_LOCAL" HEAD

echo "📤 Step 2: Uploading release archive..."
scp -P "$SSH_PORT" "$ARCHIVE_PATH_LOCAL" "$SSH_HOST:$ARCHIVE_PATH_REMOTE"

echo "🛠️ Step 3: Deploying release on server..."
ssh -p "$SSH_PORT" "$SSH_HOST" "ARCHIVE_PATH_REMOTE='$ARCHIVE_PATH_REMOTE' BASE_PATH='$BASE_PATH' APP_PATH='$APP_PATH' BACKUP_PATH='$BACKUP_PATH' bash -s" <<'REMOTE'
set -euo pipefail

STAMP=$(date +%Y%m%d_%H%M%S)
RELEASE_PATH="${BASE_PATH}/laravel_app_release_${STAMP}"
BACKUP_APP_PATH="${BASE_PATH}/laravel_app_backup_${STAMP}"

mkdir -p "$BACKUP_PATH"
rm -rf "$RELEASE_PATH"
mkdir -p "$RELEASE_PATH"
unzip -q -o "$ARCHIVE_PATH_REMOTE" -d "$RELEASE_PATH"
mkdir -p "$RELEASE_PATH/bootstrap/cache"
mkdir -p "$RELEASE_PATH/storage/app/public"
mkdir -p "$RELEASE_PATH/storage/framework/cache/data"
mkdir -p "$RELEASE_PATH/storage/framework/sessions"
mkdir -p "$RELEASE_PATH/storage/framework/views"
mkdir -p "$RELEASE_PATH/storage/logs"
chmod -R 775 "$RELEASE_PATH/storage" "$RELEASE_PATH/bootstrap/cache"

if [ -f "$APP_PATH/.env" ]; then
    cp "$APP_PATH/.env" "$RELEASE_PATH/.env"
fi

if [ -f "$APP_PATH/composer.lock" ]; then
    cp "$APP_PATH/composer.lock" "$RELEASE_PATH/composer.lock"
fi

if [ -d "$APP_PATH/storage/app/public" ]; then
    cp -a "$APP_PATH/storage/app/public/." "$RELEASE_PATH/storage/app/public/"
fi

if [ -d "$APP_PATH/storage/logs" ]; then
    cp -a "$APP_PATH/storage/logs/." "$RELEASE_PATH/storage/logs/"
fi

if [ ! -f "$RELEASE_PATH/.env" ]; then
    echo "Missing production .env file at $APP_PATH/.env" >&2
    exit 1
fi

cd "$RELEASE_PATH"
composer install --no-dev --optimize-autoloader --no-interaction
php artisan optimize:clear
php artisan about --only=environment >/dev/null
php artisan route:list >/dev/null
php artisan migrate --force
php artisan db:seed --force
mv .env.production .env.production.bak_runtime 2>/dev/null || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

if [ -d "$APP_PATH" ]; then
    mv "$APP_PATH" "$BACKUP_APP_PATH"
fi

mv "$RELEASE_PATH" "$APP_PATH"
cd "$APP_PATH"
mv .env.production .env.production.bak_runtime 2>/dev/null || true
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

cat > "${BASE_PATH}/public_html/index.php" <<PHP
<?php
define('LARAVEL_START', microtime(true));

if (file_exists(\$maintenance = '${APP_PATH}/storage/framework/maintenance.php')) {
    require \$maintenance;
}

require '${APP_PATH}/vendor/autoload.php';

\$app = require_once '${APP_PATH}/bootstrap/app.php';

\$kernel = \$app->make(Illuminate\Contracts\Http\Kernel::class);

\$response = \$kernel->handle(
    \$request = Illuminate\Http\Request::capture()
)->send();

\$kernel->terminate(\$request, \$response);
PHP

cat > "${BASE_PATH}/public_html/.htaccess" <<'HTACCESS'
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
HTACCESS

echo "Backup saved at: $BACKUP_APP_PATH"
REMOTE

echo "✅ Deployment completed successfully!"
echo "🌐 Your WorkFlow system is now live at: https://dayancosys.com"
echo "🔧 Runtime app path: $APP_PATH"
echo "📦 Backup path: $BACKUP_PATH"
