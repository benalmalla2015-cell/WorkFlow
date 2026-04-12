#!/bin/bash

# WorkFlow System Deployment Script
# This script deploys the WorkFlow system to Hostinger

echo "🚀 Starting WorkFlow System Deployment..."

# Set variables
REPO_URL="https://github.com/benalmalla2015-cell/WorkFlow.git"
SSH_HOST="u859266589@45.13.255.111"
SSH_PORT="65002"
DEPLOY_PATH="/home/u859266589/domains/dayancosys.com/public_html"
BACKUP_PATH="/home/u859266589/backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

echo "📦 Step 1: Preparing local repository..."

# Add all files to git
git add .
git commit -m "Complete WorkFlow system implementation - $TIMESTAMP"
git push origin main

echo "🔐 Step 2: Creating backup on server..."

# Create backup directory if it doesn't exist
ssh -p $SSH_PORT $SSH_HOST "mkdir -p $BACKUP_PATH"

# Backup current deployment
ssh -p $SSH_PORT $SSH_HOST "if [ -d '$DEPLOY_PATH' ]; then cp -r $DEPLOY_PATH $BACKUP_PATH/backup_$TIMESTAMP; fi"

echo "📁 Step 3: Deploying to server..."

# Clone or update the repository
ssh -p $SSH_PORT $SSH_HOST "
cd $DEPLOY_PATH && \
if [ -d '.git' ]; then \
    git pull origin main; \
else \
    git clone $REPO_URL temp && \
    mv temp/* . && \
    mv temp/.* . 2>/dev/null || true && \
    rm -rf temp; \
fi
"

echo "🔧 Step 4: Installing dependencies..."

# Install PHP dependencies
ssh -p $SSH_PORT $SSH_HOST "
cd $DEPLOY_PATH && \
composer install --no-dev --optimize-autoloader
"

echo "🎨 Step 5: Building frontend..."

# Build React frontend
ssh -p $SSH_PORT $SSH_HOST "
cd $DEPLOY_PATH/frontend && \
npm install && \
npm run build
"

echo "🗄️ Step 6: Setting up database..."

# Run database migrations
ssh -p $SSH_PORT $SSH_HOST "
cd $DEPLOY_PATH && \
php artisan migrate --force
"

# Seed database (only on first deployment)
ssh -p $SSH_PORT $SSH_HOST "
cd $DEPLOY_PATH && \
if ! php artisan tinker --execute='App\Models\User::count()' > /dev/null 2>&1; then \
    php artisan db:seed --class=DatabaseSeeder --force; \
fi
"

echo "🔒 Step 7: Setting permissions..."

# Set proper permissions
ssh -p $SSH_PORT $SSH_HOST "
cd $DEPLOY_PATH && \
chmod -R 755 storage bootstrap/cache && \
chown -R u859266589:u859266589 storage bootstrap/cache
"

echo "🎯 Step 8: Optimizing application..."

# Clear and cache configurations
ssh -p $SSH_PORT $SSH_HOST "
cd $DEPLOY_PATH && \
php artisan config:clear && \
php artisan config:cache && \
php artisan route:clear && \
php artisan route:cache && \
php artisan view:clear && \
php artisan view:cache
"

echo "🌐 Step 9: Configuring web server..."

# Create .htaccess for Laravel
ssh -p $SSH_PORT $SSH_HOST "
cd $DEPLOY_PATH && \
cat > .htaccess << 'EOF'
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Frontend...
    RewriteCond %{REQUEST_URI} ^/$ [OR]
    RewriteCond %{REQUEST_URI} ^/(login|dashboard|sales|factory|admin) [NC]
    RewriteRule ^ frontend/build/index.html [L]

    # Send Requests To Frontend Static Files...
    RewriteCond %{REQUEST_URI} ^/static/ [NC]
    RewriteRule ^(.*)$ frontend/build/$1 [L]

    # Send Requests To API...
    RewriteCond %{REQUEST_URI} ^/api/ [NC]
    RewriteRule ^ index.php [L]

    # Handle All Other Requests...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
EOF
"

echo "🔐 Step 10: Setting up environment file..."

# Create .env file with production settings
ssh -p $SSH_PORT $SSH_HOST "
cd $DEPLOY_PATH && \
if [ ! -f '.env' ]; then \
    cp .env.example .env && \
    php artisan key:generate --force
fi
"

echo "📊 Step 11: Setting up automated backups..."

# Create backup script
ssh -p $SSH_PORT $SSH_HOST "
cat > backup_workflow.sh << 'EOF'
#!/bin/bash
# Automated backup script for WorkFlow system

BACKUP_DIR=\"/home/u859266589/backups\"
TIMESTAMP=\$(date +%Y%m%d_%H%M%S)
DB_NAME=\"u859266589_workflow\"
DB_USER=\"u859266589_workflow\"
DB_PASS=\"%@!9782aNOFAveetRi*^*\"

# Create database backup
mysqldump -u \$DB_USER -p\"\$DB_PASS\" \$DB_NAME > \$BACKUP_DIR/db_backup_\$TIMESTAMP.sql

# Compress backup
gzip \$BACKUP_DIR/db_backup_\$TIMESTAMP.sql

# Keep only last 7 days of backups
find \$BACKUP_DIR -name \"db_backup_*.sql.gz\" -mtime +7 -delete

echo \"Backup completed: \$TIMESTAMP\"
EOF

chmod +x backup_workflow.sh

# Add to crontab for daily backup at 2 AM
(crontab -l 2>/dev/null; echo \"0 2 * * * /home/u859266589/backup_workflow.sh\") | crontab -
"

echo "✅ Deployment completed successfully!"
echo ""
echo "🌐 Your WorkFlow system is now live at: https://dayancosys.com"
echo ""
echo "🔑 Default Login Credentials:"
echo "   Admin: admin@workflow.com / admin123"
echo "   Sales: sales@workflow.com / sales123"
echo "   Factory: factory@workflow.com / factory123"
echo ""
echo "📚 Next Steps:"
echo "   1. Update AWS S3 credentials in .env file"
echo "   2. Configure email settings for notifications"
echo "   3. Change default passwords for security"
echo "   4. Set up SSL certificate if not already configured"
echo ""
echo "🔧 Important Files:"
echo "   - Environment: $DEPLOY_PATH/.env"
echo "   - Logs: $DEPLOY_PATH/storage/logs"
echo "   - Backups: $BACKUP_PATH"
echo ""
echo "🎉 WorkFlow System Deployment Complete!"
