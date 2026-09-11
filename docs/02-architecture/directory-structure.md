# هيكل المجلدات — Directory Structure

> **الجذر:** `D:\Coding\BarberBooking\`  
> **المرجع:** `Agent.md:34` (Directory Structure)، `composer.json:30` (autoload)

---

## 1. نظرة عامة — الشجرة الكاملة

```
BarberBooking/
├── app/
│   ├── Console/                  ← أوامر Artisan (Commands)
│   ├── Enum/                     ← 8 Enums (AppointmentStatus, InvoiceStatus, ...)
│   ├── Exceptions/               ← معالجة الاستثناءات + PushRequiredException, SlotUnavailableException
│   ├── Filament/                 ← لوحة الإدارة (142 ملف)
│   │   ├── Pages/                ← صفحات مخصصة (ManageProviderSchedules, ...)
│   │   ├── Resources/            ← 18 Resource (Appointments, Providers, Services, ...)
│   │   └── Widgets/              ← ودجات Filament
│   ├── Helpers/
│   │   └── Main.php              ← دالة get_setting() العامة — composer.json: files
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/              ← 21 Controller للـ API
│   │   │   │   ├── AuthController.php
│   │   │   │   ├── BookingController.php
│   │   │   │   ├── AvailabilityController.php
│   │   │   │   ├── ServicesController.php
│   │   │   │   ├── ProvidersController.php
│   │   │   │   ├── AppointmentController.php
│   │   │   │   ├── ProfileController.php
│   │   │   │   ├── OtpController.php
│   │   │   │   └── ...
│   │   │   ├── BookingController.php          ← حجز عبر الويب (Filament auth)
│   │   │   ├── PrintController.php            ← طباعة الفواتير
│   │   │   └── InvoiceTemplateController.php
│   │   ├── Middleware/
│   │   │   ├── EnsureCustomerIsVerified.php
│   │   │   └── EnsureStaffDashboardAccess.php
│   │   └── Requests/
│   │       └── Api/
│   │           ├── BookingCreateRequest.php
│   │           └── ...
│   ├── Jobs/                     ← مهام Queue (إرسال إيميلات، تذكيرات)
│   ├── Livewire/                 ← 7 Components (StaffDashboard, ScheduleManager, ...)
│   ├── Models/                   ← 46 Model
│   │   ├── User.php
│   │   ├── Appointment.php
│   │   ├── AppointmentService.php
│   │   ├── Service.php
│   │   ├── Invoice.php
│   │   ├── ProviderScheduledWork.php
│   │   └── ...
│   ├── Observers/
│   │   └── UserObserver.php
│   ├── Services/                 ← 71 Service — قلب النظام
│   │   ├── BookingService.php
│   │   ├── BookingValidationService.php
│   │   ├── ServiceAvailabilityService.php
│   │   ├── TaxCalculatorService.php
│   │   ├── InvoiceFinalizationService.php
│   │   ├── Fiskaly/              ← 6 ملفات TSE (معطلة)
│   │   ├── InvoiceTemplate/      ← 6 ملفات قوالب
│   │   ├── Sms/                  ← 7 ملفات + Drivers
│   │   ├── Cms/                  ← 5 ملفات CMS
│   │   └── ...
│   └── Support/
│       └── PhoneNumber.php       ← تطبيع أرقام الهواتف (آخر 9 أرقام)
├── bootstrap/
│   ├── app.php                   ← تسجيل Middleware + Exception handling
│   └── cache/
├── config/                       ← 24 ملف إعداد
│   ├── app.php
│   ├── auth.php + auth_tokens.php
│   ├── rate_limits.php
│   ├── sms.php
│   ├── fiskaly.php
│   ├── invoice-line-types.php
│   └── ...
├── database/
│   ├── migrations/               ← 74 ملف
│   ├── seeders/                  ← 26 ملف
│   └── factories/
├── docs/                         ← أنت هنا — التوثيق الشامل
│   ├── README.md                 ← المدخل الموحد
│   ├── 01-overview/
│   ├── 02-architecture/
│   ├── 03-data-models/
│   ├── ... (10 أقسام)
│   ├── BOOKING_FLOW.md           ← التوثيق القديم المفصل (محفوظ)
│   └── fixes/                    ← تقارير الإصلاحات (MON-01, MON-03, ...)
├── lang/                         ← ملفات الترجمة (en, ar, de)
├── resources/
│   ├── views/                    ← 77 Blade view
│   │   ├── filament/             ← تخصيصات Filament
│   │   ├── livewire/             ← StaffDashboard, ScheduleManager, ...
│   │   ├── invoices/line-types/  ← 16 نوع سطر فاتورة
│   │   ├── landing/              ← صفحات الهبوط
│   │   ├── emails/               ← قوالب الإيميل
│   │   └── reports/
│   ├── css/
│   └── js/
├── routes/
│   ├── api.php                   ← 412 سطر — كل endpoints الـ API
│   ├── web.php                   ← 215 سطر — الويب + Filament + Staff Dashboard
│   └── console.php
├── storage/
│   ├── app/
│   └── logs/
├── tests/                        ← اختبارات Pest/PHPUnit
├── public/
│   └── storage → storage/app/public (symlink)
├── .env / .env.example
├── composer.json
├── package.json + vite.config.js
├── Agent.md                      ← مرجع AI الشامل
├── API.md                        ← مرجع API للموبايل
└── README.md                     ← مدخل المشروع (Laravel boilerplate سابقًا)
```

---

## 2. شرح المجلدات الحرجة

### `app/Enum/` — 8 Enums

| الملف | القيم |
|-------|-------|
| `AppointmentStatus.php` | `PENDING(0)`, `COMPLETED(1)`, `USER_CANCELLED(-1)`, `ADMIN_CANCELLED(-2)`, `NO_SHOW(-3)` |
| `InvoiceStatus.php` | `DRAFT(0)`, `PENDING(1)`, `PAID(2)`, `PARTIALLY_PAID(3)`, `CANCELLED(-1)`, `REFUNDED(-2)`, `OVERDUE(4)` |
| `PaymentStatus.php` | `PENDING(0)`, `PAID_ONLINE(1)`, `PAID_ONSTIE_CASH(2)`, `PAID_ONSTIE_CARD(3)`, `FAILED(4)`, `REFUNDED(5)`, `PARTIALLY_REFUNDED(6)` |
| `RegistrationMethod.php` | `EMAIL`, `PHONE` |
| `OtpPurpose.php` | `VERIFICATION`, `PASSWORD_RESET` |
| `OtpType.php` | `EMAIL_OTP(1)`, `SMS_OTP(2)` |
| `BookingSource.php` | `API`, `WEB`, `IN_PERSON` |
| `TemplateSectionType.php` | `Header`, `Body`, `Footer` |

كلها `int-backed` أو `string-backed` Enums (PHP 8.1+).

### `app/Services/` — 71 Service

انظر [`04-services/README.md`](../04-services/README.md) للخريطة الكاملة. الأهم:

```
BookingService.php              ← منسق الحجز
BookingValidationService.php    ← كل التحقق
ServiceAvailabilityService.php  ← حساب التوفر
TaxCalculatorService.php        ← الحساب الوحيد للضريبة
InvoiceService.php              ← المسودات
InvoiceFinalizationService.php  ← الدفع الوحيد
BookingLockService.php          ← منع التزامن
GapAnalysisService.php          ← تحليل الفجوات لإضافة خدمة
PushBookingsService.php         ← دفع المواعيد عند الإضافة
```

### `app/Models/` — 46 Model

انظر [`03-data-models/README.md`](../03-data-models/README.md).

### `app/Filament/Resources/` — 142 ملف

كل Resource يتبع نفس الهيكل:

```
Appointments/
├── AppointmentResource.php       ← تعريف Resource (model, navigation, permissions)
├── Pages/
│   ├── ListAppointments.php
│   ├── CreateAppointment.php
│   ├── EditAppointment.php
│   └── ViewAppointment.php
├── Schemas/
│   ├── AppointmentForm.php       ← حقول النموذج
│   └── AppointmentInfolist.php   ← عرض التفاصيل
├── Tables/
│   └── AppointmentsTable.php     ← أعمدة الجدول + فلاتر + actions
└── RelationManagers/             ← (في Providers, Services, ...)
```

### `database/migrations/` — 74 ملف

مرتبة زمنيًا من `0001_00_001` حتى `2026_09_11_000002`. أهمها:

| الملف | ما يُنشئ |
|-------|----------|
| `2025_10_10_145021_create_appointments_table.php` | `appointments` + فهرس `appointments_conflict_lookup_idx` |
| `2025_10_25_180109_create_invoices_table.php` | `invoices` |
| `2026_09_07_100000_confirm_all_appointments_by_default.php` | `created_status` default → 1 |
| `2026_09_10_120000_create_document_counters_table.php` | `document_counters` للترقيم المتسلسل |
| `2026_09_11_000001_fix_appointment_reminders_unique_constraint.php` | قيد `one_active` للتذكيرات |

### `config/` — 24 ملف

| الملف | الغرض |
|-------|-------|
| `app.php` | اسم التطبيق، URL، locale |
| `auth.php` + `auth_tokens.php` | Sanctum + TTL |
| `rate_limits.php` | حدود الـ throttling المسماة |
| `sms.php` | `driver: log/seven/vonage` + `enabled` |
| `fiskaly.php` | `api_key` (معطل) |
| `invoice-line-types.php` | سجل أنواع أسطر الفاتورة |
| `appointment_reminders.php` | مهل التذكير |
| `otp.php` | expiry + max attempts |

### `resources/views/` — 77 Blade

| المجلد | المحتوى |
|--------|---------|
| `filament/` | تخصيصات Filament (fields, pages, modals) |
| `livewire/` | `staff-dashboard`, `schedule-manager`, `shift-manager`, ... |
| `invoices/line-types/` | 16 Blade لكل نوع سطر (barcode, qr-code, items-table, ...) |
| `landing/` | صفحات الهبوط (hero, features, gallery, ...) |
| `emails/` | `booking/confirmation`, `otp`, `appointment-reminder` |
| `reports/` | `daily-report` للطباعة |

---

## 3. ملفات الجذر

| الملف | الغرض |
|-------|-------|
| `composer.json` | Dependencies + autoload (`App\` → `app/`, files: `app/Helpers/Main.php`) |
| `package.json` | Node deps (Vite, Tailwind) |
| `vite.config.js` | بناء الأصول |
| `.env` / `.env.example` | متغيرات البيئة (`DATABASE_URL`, `APP_KEY`, `FISKALY_*`, ...) |
| `artisan` | CLI |
| `phpunit.xml` | إعدادات الاختبار |
| `Agent.md` | مرجع AI (1000+ سطر) |
| `API.md` | مرجع API عربي (1700+ سطر) |
| `README.md` | مدخل المشروع |

---

*التالي: [`request-lifecycle.md`](request-lifecycle.md)*
