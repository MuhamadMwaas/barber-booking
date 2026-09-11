# 10 — التشغيل والإعداد — Operations

> **الملفات:** `config/` (24 ملف)، `.env.example:1`، `database/seeders/DatabaseSeeder.php:1`

---

## 1. متطلبات التشغيل

```
PHP >= 8.2
  ├─ ext-bcmath (إجباري)
  ├─ ext-pdo_pgsql
  └─ ext-gd
PostgreSQL >= 14 (Neon)
Node.js >= 18
Composer >= 2
```

---

## 2. التثبيت

```bash
composer install
cp .env.example .env
php artisan key:generate
# عدّل .env: DATABASE_URL, APP_URL, MAIL_*, VONAGE_*, ONESIGNAL_*
php artisan migrate
php artisan db:seed
php artisan storage:link
npm install && npm run build
php artisan serve
```

---

## 3. الأوامر اليومية

| الأمر | الغرض |
|-------|-------|
| `php artisan migrate` | تطبيق migrations |
| `php artisan db:seed` | تعبئة البيانات الافتراضية |
| `php artisan queue:work` | معالجة Queue (إيميلات، تذكيرات) |
| `php artisan schedule:work` | المهام المجدولة (تذكيرات) |
| `php artisan storage:link` | ربط storage |
| `npm run dev` | Vite dev server |
| `npm run build` | بناء الإنتاج |

---

## 4. الإعدادات

| المصدر | الملف | مثال |
|--------|-------|------|
| `.env` | `.env` | `DATABASE_URL`, `APP_KEY`, `MAIL_MAILER` |
| `config/` | `config/*.php` | 24 ملف |
| DB `salon_settings` | `SalonSetting` | `tax_rate=19`, `max_booking_days=10` |
| DB `app_settings` | `AppSetting` | كتالوج الإعدادات |
| Helper | `get_setting('tax_rate','19')` | `app/Helpers/Main.php:10` |

---

## 5. الملفات التفصيلية

| الملف | المحتوى |
|-------|---------|
| [`configuration.md`](configuration.md) | كل `config/` + `.env` + `get_setting()` |
| [`seeding.md`](seeding.md) | الـ 26 Seeder + ترتيب `DatabaseSeeder` |
| [`deployment.md`](deployment.md) | النشر للإنتاج |

---

*العودة: [`docs/README.md`](../README.md) — المدخل الموحد*
