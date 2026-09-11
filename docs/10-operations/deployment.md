# النشر — Deployment

> **الملفات:** `composer.json:1`، `vite.config.js:1`، `routes/console.php:1`

---

## 1. متطلبات الخادم

```
PHP >= 8.2 + ext-bcmath + ext-pdo_pgsql + ext-gd
PostgreSQL >= 14
Node.js >= 18
Composer >= 2
Supervisor (لـ queue:work)
Nginx/Apache
```

## 2. خطوات النشر

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
cp .env.example .env  # وعدّل DATABASE_URL, APP_KEY, MAIL_*, ...
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force  # أول مرة فقط
php artisan storage:link
npm install && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
sudo supervisorctl restart all  # أو systemd
```

## 3. Queue & Schedule

```bash
# يجب أن يعمل دائمًا:
php artisan queue:work --sleep=3 --tries=3
# Cron كل دقيقة:
* * * * * php /var/www/barberbooking/artisan schedule:run >> /dev/null 2>&1
```

- Queue: إيميلات الحجز (`BookingMailService`), تذكيرات (`AppointmentReminderService` delayed jobs).
- Schedule: إرسال التذكيرات عند `remind_at` — `app/Console/Kernel.php` (أو `routes/console.php`).

## 4. التحقق بعد النشر

```bash
php artisan migrate:status
php artisan db:seed --class=LanguageSeeder  # تأكد من اللغات
curl http://localhost:8000/api/services  # يجب 200
curl http://localhost:8000/api/availability/service?service_id=1&date=2026-09-12  # 200
```

---

*العودة: [`docs/README.md`](../README.md)*
