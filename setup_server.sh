#!/bin/bash
# ================================================================
# WorkFlow System - One-Command Server Setup Script
# Run on Hostinger SSH Terminal
# ================================================================
set -e

echo ""
echo "╔══════════════════════════════════════════╗"
echo "║     WorkFlow System - Auto Deployment    ║"
echo "╚══════════════════════════════════════════╝"
echo ""

BASE="/home/u859266589/domains/dayancosys.com"
APP="$BASE/laravel_app"
WEB="$BASE/public_html"
REPO="https://github.com/benalmalla2015-cell/WorkFlow.git"

# ── Step 1: Clone repo ──────────────────────────────────────────
echo "▶ [1/8] Cloning WorkFlow repository..."
rm -rf "$APP"
git clone "$REPO" "$APP"
echo "  ✓ Repository cloned"

# ── Step 2: Composer install ────────────────────────────────────
echo "▶ [2/8] Installing PHP dependencies..."
cd "$APP"
composer install --no-dev --optimize-autoloader --no-interaction --quiet
echo "  ✓ Dependencies installed"

# ── Step 3: .env setup ──────────────────────────────────────────
echo "▶ [3/8] Configuring environment..."
cat > "$APP/.env" << 'ENVEOF'
APP_NAME="WorkFlow"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://dayancosys.com

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=workflow_db
DB_USERNAME=workflow_user
DB_PASSWORD=change_me

CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120
ENVEOF

php artisan key:generate --force --quiet
echo "  ✓ Environment configured"

# ── Step 4: Permissions ─────────────────────────────────────────
echo "▶ [4/8] Setting permissions..."
chmod -R 775 "$APP/storage" "$APP/bootstrap/cache"
echo "  ✓ Permissions set"

# ── Step 5: Database ────────────────────────────────────────────
echo "▶ [5/8] Running migrations and seeders..."
php artisan migrate --force --quiet
php artisan db:seed --force --quiet
echo "  ✓ Database ready with default users"

# ── Step 6: Deploy public files ─────────────────────────────────
echo "▶ [6/8] Deploying web files..."

# Backup existing public_html
if [ -d "$WEB" ] && [ "$(ls -A $WEB)" ]; then
    mv "$WEB" "${WEB}_backup_$(date +%Y%m%d%H%M%S)" 2>/dev/null || true
fi
mkdir -p "$WEB"

# Copy Laravel public folder to public_html
cp -r "$APP/public/." "$WEB/"

# Fix paths in index.php to point to correct laravel_app location
cat > "$WEB/index.php" << INDEXEOF
<?php
define('LARAVEL_START', microtime(true));
if (file_exists(\$maintenance = '$APP/storage/framework/maintenance.php')) {
    require \$maintenance;
}
require '$APP/vendor/autoload.php';
\$app = require_once '$APP/bootstrap/app.php';
\$kernel = \$app->make(Illuminate\Contracts\Http\Kernel::class);
\$response = \$kernel->handle(
    \$request = Illuminate\Http\Request::capture()
)->send();
\$kernel->terminate(\$request, \$response);
INDEXEOF

# Copy .htaccess to public_html
cp "$APP/.htaccess" "$WEB/.htaccess"

echo "  ✓ Web files deployed"

# ── Step 7: Storage symlink ─────────────────────────────────────
echo "▶ [7/8] Creating storage link..."
cd "$APP"
php artisan storage:link --quiet 2>/dev/null || true
echo "  ✓ Storage linked"

# ── Step 8: Cache & optimize ────────────────────────────────────
echo "▶ [8/8] Optimizing application..."
php artisan config:cache --quiet
php artisan route:cache --quiet
php artisan view:cache --quiet
echo "  ✓ Application optimized"

# ── Setup daily backup cron ─────────────────────────────────────
CRON_JOB="0 2 * * * mysqldump -u workflow_user -pchange_me workflow_db > /home/u859266589/backups/workflow_\$(date +\%Y\%m\%d).sql 2>/dev/null"
mkdir -p /home/u859266589/backups
(crontab -l 2>/dev/null | grep -v "workflow"; echo "$CRON_JOB") | crontab -

# ── Final Report ────────────────────────────────────────────────
echo ""
echo "╔══════════════════════════════════════════════════════╗"
echo "║            ✅  Deployment Successful!                ║"
echo "╠══════════════════════════════════════════════════════╣"
echo "║  🌐  URL:     https://dayancosys.com                 ║"
echo "║                                                      ║"
echo "║  🔑  LOGIN CREDENTIALS:                              ║"
echo "║  Admin:    admin@workflow.com   / admin123           ║"
echo "║  Sales:    sales@workflow.com   / sales123           ║"
echo "║  Factory:  factory@workflow.com / factory123         ║"
echo "║                                                      ║"
echo "║  ⚠️   Change passwords after first login!            ║"
echo "║  📦  Daily DB backup set at 2:00 AM                  ║"
echo "╚══════════════════════════════════════════════════════╝"
echo ""
