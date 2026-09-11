# التقنيات — Tech Stack

> **الملف:** `composer.json:1`، `package.json:1`، `vite.config.js:1`، `config/` (24 ملف)

---

## 1. نظرة عامة

| الطبقة | التقنية | الإصدار | الغرض |
|--------|---------|---------|-------|
| **Language** | PHP | ^8.2 | اللغة الأساسية |
| **Framework** | Laravel | ^12.0 | إطار العمل |
| **Admin Panel** | Filament | ~5.0 (فعليًا 4.x) | لوحة الإدارة |
| **Database** | PostgreSQL | Neon-backed | قاعدة البيانات |
| **Auth (API)** | Laravel Sanctum | ^4.0 | التوكن |
| **Auth (Roles)** | Spatie Permission | * | الأدوار والصلاحيات |
| **Frontend Build** | Vite | ^5.x | بناء الأصول |
| **CSS** | TailwindCSS | ^4.0 | التنسيق |
| **Money** | brick/money | ^0.10.3 | كائنات المال (إضافة لـ bcmath) |
| **QR** | endroid/qr-code + simple-qrcode | ^5.0 / ^4.2 | رموز QR للفواتير |
| **Social Auth** | Laravel Socialite + google/apiclient | ^5.23 / ^2.18 | Google OAuth |
| **Avatar** | laravolt/avatar | ^6.3 | صور رمزية افتراضية |
| **SMS** | vonage/client + seven.io | ^4.3 / REST | إرسال SMS |
| **Image** | intervention/image | ^3.11 | معالجة الصور |
| **Testing** | Pest + PHPUnit | ^3.8 / ^11.5 | الاختبارات |
| **Lint** | Laravel Pint | ^1.24 | تنسيق الكود |

---

## 2. PHP 8.2 + Laravel 12

### 2.1 لماذا Laravel 12؟

- أحدث إصدار مستقر عند كتابة المشروع (2025-2026)
- يدعم PHP 8.2 features: Enums, Readonly, Fibers
- Filament 4 يتطلب Laravel 11+

### 2.2 ميزات PHP 8.2 المستخدمة في المشروع

| الميزة | أين تُستخدم | مثال |
|--------|-------------|------|
| **Backed Enums** | كل الحالات | `app/Enum/AppointmentStatus.php:10` (`PENDING = 0`) |
| **Constructor Promotion** | Services | `BookingService.php:18` (`protected BookingValidationService $validationService`) |
| **Readonly** | Value Objects | `SmsResult.php` |
| **Match Expression** | تحويل الأخطاء | `BookingService.php:761` (`match ($reason)`) |
| **Named Arguments** | استدعاء Services | `InvoiceFinalizationService.php:45` (`appointment: $appointment`) |

---

## 3. Filament 4.0 — لوحة الإدارة

### 3.1 ما هو Filament؟

إطار لبناء لوحات إدارة داخل Laravel — يولّد CRUD تلقائيًا من Models.

### 3.2 هيكل Filament في المشروع

```
app/Filament/
├── Resources/          ← 18 Resource (142 ملف)
│   ├── Appointments/
│   │   ├── AppointmentResource.php
│   │   ├── Pages/{List,Create,Edit,View}Appointment.php
│   │   ├── Schemas/{AppointmentForm,AppointmentInfolist}.php
│   │   └── Tables/AppointmentsTable.php
│   ├── Providers/      ← مع RelationManagers (TimeOffs, ScheduledWorks, ...)
│   ├── Services/
│   ├── InvoiceTemplates/
│   └── ...
├── Pages/              ← صفحات مخصصة
│   ├── ManageProviderSchedules.php
│   ├── ManageProviderLeaves.php
│   ├── ManageSalonSchedules.php
│   └── ViewProviderScheduleTimeline.php
└── Widgets/
    └── ProviderStatsOverview.php
```

### 3.3 Livewire داخل Filament

- Filament مبني على Livewire — كل Form/Table هو Livewire component.
- Staff Dashboard (`app/Livewire/StaffDashboard.php`) هو Livewire مستقل خارج Filament، يعيش في `routes/web.php:30` على subdomain.

---

## 4. PostgreSQL

### 4.1 لماذا PostgreSQL؟

- Neon-backed (سحابي) — `DATABASE_URL` في `.env`
- يدعم `SELECT FOR UPDATE` بشكل قوي (لـ BookingLockService)
- يدعم JSON columns (`invoice_data`, `global_styles`, `company_info`)

### 4.2 Migrations — 74 ملف

```
database/migrations/
├── 0001_00_001_000000_create_languages_table.php
├── 0001_01_01_000000_create_users_table.php
├── 2025_10_10_145021_create_appointments_table.php
├── 2025_10_25_180109_create_invoices_table.php
├── 2026_09_07_100000_confirm_all_appointments_by_default.php
├── 2026_09_07_120000_add_conflict_lookup_index_to_appointments.php  ← فهرس التزامن
├── 2026_09_10_120000_create_document_counters_table.php             ← عداد الفواتير
└── ...
```

انظر [`03-data-models/README.md`](../03-data-models/README.md) للتفصيل.

---

## 5. التوثيق — Sanctum + Spatie

### 5.1 Sanctum (API Tokens)

```php
// config/sanctum.php + config/auth_tokens.php
// User model: HasApiTokens trait — app/Models/User.php:25
// Token creation: app/Services/AuthTokenService.php:1
// Routes: routes/api.php:53 (auth group)
```

- `access_token`: قصير المدى (ساعات) — يُرسل في `Authorization: Bearer`
- `refresh_token`: طويل المدى (أيام) — يُخزن في `refresh_tokens` table — `app/Models/RefreshToken.php:1`
- Rotation: كل refresh يُبطل القديم ويُنشئ جديدًا — `database/migrations/2026_08_29_200000_harden_refresh_token_rotation.php`

### 5.2 Spatie Permission (Roles)

```php
// config/permission.php
// User model: HasRoles trait — app/Models/User.php:25
// Seeder: database/seeders/RoleSeeder.php — admin, provider, customer, manager, SuperAdmin
// Seeder: database/seeders/PermissionsSeeder.php — permissions دقيقة
```

---

## 6. الواجهة الأمامية — Vite + TailwindCSS 4

```js
// vite.config.js — يبني resources/css/app.css + resources/js/app.js
// tailwind.config.js — Tailwind 4 (بدون config file منفصل في v4)
// resources/views/layouts/landing.blade.php — layout العام
// resources/views/landing/ — صفحات الهبوط (hero, features, gallery, ...)
// resources/views/filament/ — تخصيصات Filament
```

---

## 7. الخدمات الخارجية

| الخدمة | الحزمة | الملف | الحالة |
|--------|--------|-------|--------|
| **OneSignal** | HTTP client مباشر | `app/Services/OneSignalService.php` | نشط |
| **Vonage SMS** | `vonage/client` | `app/Services/Sms/Drivers/VonageSmsDriver.php` | احتياطي |
| **Seven.io SMS** | REST `api.seven.io` | `app/Services/Sms/Drivers/SevenSmsDriver.php` | أساسي |
| **Mail** | `config/mail.php` (SMTP) | `app/Services/BookingMailService.php` | نشط |
| **Google OAuth** | `laravel/socialite` + `google/apiclient` | `app/Http/Controllers/Api/SocialAuthController.php` | نشط |
| **Fiskaly TSE** | `app/Services/Fiskaly/` (6 ملفات) | `config/fiskaly.php` | **معطّل عمداً** |

> **Fiskaly/TSE معطّل:** الكود موجود لكنه خارج مسار الدفع. `InvoiceFinalizationService` يسجل `tse_enabled=false` ولا يستدعي Fiskaly. إعادة التفعيل تتطلب مشروع تكامل منفصل — `Agent.md:945`.

---

## 8. الاختبار والجودة

| الأداة | الإصدار | الاستخدام |
|--------|---------|-----------|
| **Pest** | ^3.8 | إطار الاختبار (بديل PHPUnit الأكثر تعبيرًا) |
| **PHPUnit** | ^11.5 | مشغل الاختبارات الأساسي |
| **Laravel Pint** | ^1.24 | تنسيق الكود (PSR-12) |
| **Laravel Pail** | ^1.2.2 | عرض الـ logs في التطوير |
| **Laravel Sail** | ^1.41 | بيئة Docker (اختياري) |

```bash
php artisan test          # تشغيل كل الاختبارات (Pest)
./vendor/bin/pint         # تنسيق الكود
php artisan pail          # متابعة الـ logs
```

---

## 9. المتطلبات للتشغيل

```
PHP >= 8.2
  └─ ext-bcmath (إجباري — للحسابات المالية)
  └─ ext-pdo_pgsql
  └─ ext-gd (لـ intervention/image)
PostgreSQL >= 14
Node.js >= 18 (لـ Vite)
Composer >= 2.x
```

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm install && npm run build
php artisan serve
```

التفصيل في [`10-operations/deployment.md`](../10-operations/deployment.md).

---

*التالي: [`directory-structure.md`](directory-structure.md)*
