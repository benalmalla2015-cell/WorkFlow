# أوامر النشر على Hostinger SSH
# انسخ والصق كل مجموعة أوامر في SSH terminal

## ========================
## STEP 1: الذهاب للمجلد الصحيح
## ========================
```
cd /home/u859266589/domains/dayancosys.com
```

## ========================
## STEP 2: حذف المحتوى القديم وإنشاء مجلد التطبيق
## ========================
```
rm -rf laravel_app
mkdir laravel_app
git clone https://github.com/benalmalla2015-cell/WorkFlow.git laravel_app
```

## ========================
## STEP 3: تثبيت Composer
## ========================
```
cd laravel_app
composer install --no-dev --optimize-autoloader
```

## ========================
## STEP 4: إعداد ملف البيئة
## ========================
```
cp .env.example .env
sed -i 's/APP_ENV=local/APP_ENV=production/' .env
sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' .env
sed -i 's|APP_URL=http://localhost|APP_URL=https://dayancosys.com|' .env
sed -i 's/DB_DATABASE=workflow_db/DB_DATABASE=workflow_db/' .env
sed -i 's/DB_USERNAME=workflow_user/DB_USERNAME=workflow_user/' .env
sed -i 's/DB_PASSWORD=.*$/DB_PASSWORD="change_me"/' .env
php artisan key:generate --force
```

## ========================
## STEP 5: إعداد الصلاحيات وقاعدة البيانات
## ========================
```
chmod -R 775 storage bootstrap/cache
php artisan storage:link
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
```

## ========================
## STEP 6: ربط public_html بمجلد public في Laravel
## ========================
```
cd /home/u859266589/domains/dayancosys.com

# احتياطي للمحتوى القديم
[ -d "public_html" ] && mv public_html public_html_bak

# إنشاء رابط رمزي أو نسخ ملفات public
cp -r laravel_app/public public_html

# تحديث مسار autoload في index.php
sed -i "s|require __DIR__.'/../vendor/autoload.php'|require '/home/u859266589/domains/dayancosys.com/laravel_app/vendor/autoload.php'|" public_html/index.php
sed -i "s|require_once __DIR__.'/../bootstrap/app.php'|require_once '/home/u859266589/domains/dayancosys.com/laravel_app/bootstrap/app.php'|" public_html/index.php
```

## ========================
## STEP 7: التحقق من النتيجة
## ========================
```
curl -I https://dayancosys.com
```

## ========================
## بيانات تسجيل الدخول الافتراضية
## ========================
- Admin:   admin@workflow.com   / admin123
- Sales:   sales@workflow.com   / sales123
- Factory: factory@workflow.com / factory123
