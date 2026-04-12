#!/bin/bash
# ===================================================
# WorkFlow System - Complete Server Setup Script
# Run this on Hostinger SSH terminal
# ===================================================

DOMAIN_PATH="/home/u859266589/domains/dayancosys.com/public_html"
REPO_URL="https://github.com/benalmalla2015-cell/WorkFlow.git"

echo "======================================"
echo "  WorkFlow System - Server Setup"
echo "======================================"

# Step 1: Navigate to domain folder
cd /home/u859266589/domains/dayancosys.com/

# Step 2: Backup existing public_html if it exists
if [ -d "public_html" ]; then
    echo ">> Backing up existing public_html..."
    mv public_html public_html_backup_$(date +%Y%m%d_%H%M%S)
fi

# Step 3: Clone the repository
echo ">> Cloning WorkFlow repository..."
git clone $REPO_URL public_html_temp

# Step 4: Move files to public_html correctly
# Laravel's web root is /public, so we need to set up properly
mkdir -p public_html

# Copy all Laravel files
cp -r public_html_temp/. /home/u859266589/domains/dayancosys.com/laravel_app/

# Step 5: Set public_html to point to Laravel public folder
cp -r public_html_temp/public/. public_html/
rm -rf public_html_temp

# Step 6: Create proper index.php that points to Laravel app
cat > public_html/index.php << 'PHPEOF'
<?php

/**
 * Laravel - WorkFlow System
 * Redirect from public_html to Laravel public folder
 */

define('LARAVEL_START', microtime(true));

// Point to the Laravel application root
$laravelRoot = '/home/u859266589/domains/dayancosys.com/laravel_app';

if (file_exists($laravelRoot . '/storage/framework/maintenance.php')) {
    require $laravelRoot . '/storage/framework/maintenance.php';
}

require $laravelRoot . '/vendor/autoload.php';

$app = require_once $laravelRoot . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
)->send();

$kernel->terminate($request, $response);
PHPEOF

echo ">> index.php created"

# Step 7: Install PHP dependencies
echo ">> Installing Composer dependencies..."
cd /home/u859266589/domains/dayancosys.com/laravel_app
composer install --no-dev --optimize-autoloader --no-interaction

# Step 8: Setup .env file
echo ">> Setting up environment file..."
cp .env.example .env

# Update .env with production settings
cat > .env << 'ENVEOF'
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

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
ENVEOF

# Step 9: Generate app key
echo ">> Generating application key..."
php artisan key:generate --force

# Step 10: Set storage permissions
echo ">> Setting permissions..."
chmod -R 775 storage bootstrap/cache
chown -R u859266589:u859266589 storage bootstrap/cache

# Step 11: Create storage symlink
php artisan storage:link

# Step 12: Run migrations
echo ">> Running database migrations..."
php artisan migrate --force

# Step 13: Seed database with default data
echo ">> Seeding database..."
php artisan db:seed --class=DatabaseSeeder --force

# Step 14: Optimize application
echo ">> Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Step 15: Create .htaccess in public_html
cat > /home/u859266589/domains/dayancosys.com/public_html/.htaccess << 'HTEOF'
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</IfModule>
HTEOF

echo ""
echo "======================================"
echo "  ✅ Setup Complete!"
echo "======================================"
echo ""
echo "🌐 Visit: https://dayancosys.com"
echo ""
echo "🔑 Login Credentials:"
echo "   Admin:   admin@workflow.com   / admin123"
echo "   Sales:   sales@workflow.com   / sales123"
echo "   Factory: factory@workflow.com / factory123"
echo ""
echo "⚠️  IMPORTANT: Change passwords after first login!"
echo "======================================"
