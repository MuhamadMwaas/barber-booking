# Seeders

> **المجلد:** `database/seeders/` (26 ملف) — `DatabaseSeeder.php:1`

---

## 1. ترتيب التنفيذ — `DatabaseSeeder.php`

```php
$this->call([
    LanguageSeeder::class,          // en, ar, de
    BranchSeeder::class,            // Main Branch
    SalonSettingSeeder::class,      // tax_rate=19, max_booking_days=10, ...
    RoleSeeder::class,              // admin, provider, customer, manager, SuperAdmin
    PermissionsSeeder::class,
    UserSeeder::class,              // admin@elitebeauty.ae + 8 providers + customers
    ServiceCategorySeeder::class,   // Hair, Nails, Skin, ...
    ServiceSeeder::class,           // خدمات بأسعار ومدد
    ProviderServiceSeeder::class,   // ربط مزودين↔خدمات
    ProviderScheduledWorkSeeder::class,
    ProviderTimeOffSeeder::class,
    SalonScheduleSeeder::class,
    AppointmentSeeder::class,       // مواعيد تجريبية
    PaymentMethodSeeder::class,     // Cash, Card, Online
    PrinterSeeder::class,
    InvoiceTemplateSeeder::class,   // قالب افتراضي + lines
    ReasonLeaveSeeder::class,
    StaticPagesSeeder::class,       // Privacy, Terms
    // ... + Cms, Landing, Sliders, AboutUs
]);
```

## 2. بيانات الدخول الافتراضية

| الدور | Email | Password |
|-------|-------|----------|
| Admin | `admin@elitebeauty.ae` | `password` |
| Customer | `hala.alhashimi@gmail.com` | `password` |

## 3. التشغيل

```bash
php artisan db:seed              # كل شيء
php artisan db:seed --class=UserSeeder  # واحد فقط
php artisan migrate:fresh --seed # إعادة كاملة
```

---

*التالي: [`deployment.md`](deployment.md)*
