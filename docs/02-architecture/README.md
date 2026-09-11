# 02 — البنية والمعمارية — Architecture

> **الملفات:** `composer.json:1`، `bootstrap/app.php:1`، `config/` (24 ملف)، `routes/api.php:1`، `routes/web.php:1`

---

## 1. النظرة المعمارية العامة

```
┌─────────────────────────────────────────────────────────────────┐
│                        Client Layer                             │
│  Mobile App (Flutter/React Native)  │  Web (Vite + Tailwind)    │
│  Staff Dashboard (Livewire)          │  Filament Admin (/admin)  │
└──────────────┬──────────────────────────────────┬───────────────┘
               │ REST API (Sanctum)               │ Web + Livewire
               ▼                                  ▼
┌─────────────────────────────────────────────────────────────────┐
│                      Laravel 12 Application                     │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────────────┐ │
│  │  Routes  │→ │Middleware│→ │Controllers│→ │   Services       │ │
│  │ api.php  │  │auth:sanct│  │ Api/*    │  │ BookingService   │ │
│  │ web.php  │  │throttle  │  │ Filament │  │ InvoiceService   │ │
│  └──────────┘  └──────────┘  └──────────┘  └────────┬─────────┘ │
│                                                     │           │
│                                            ┌────────▼─────────┐ │
│                                            │     Models       │ │
│                                            │  Appointment     │ │
│                                            │  User / Service  │ │
│                                            │  Invoice / ...   │ │
│                                            └────────┬─────────┘ │
└─────────────────────────────────────────────────────┼───────────┘
                                                      │ Eloquent
                                                      ▼
                                            ┌──────────────────┐
                                            │   PostgreSQL     │
                                            │   (Neon-backed)  │
                                            │  74 migrations   │
                                            └──────────────────┘
         ┌──────────────┐  ┌──────────────┐  ┌──────────────┐
         │  OneSignal   │  │  Vonage/Seven│  │  Mail (SMTP) │
         │  (Push)      │  │  (SMS)       │  │  (OTP/Booking)│
         └──────────────┘  └──────────────┘  └──────────────┘
```

### فصل الاهتمامات (Separation of Concerns)

| الطبقة | المسؤولية | مثال |
|--------|-----------|------|
| **Routes** | تعريف الـ endpoints والـ middleware والـ throttling | `routes/api.php:206` (`/availability/service` throttle 40/min) |
| **Middleware** | توثيق، تحقق، تحديد معدل، لغة | `auth:sanctum`, `verified.customer`, `throttle:auth-login` |
| **Requests** | Validation rules + رسائل خطأ | `app/Http/Requests/Api/BookingCreateRequest.php:1` |
| **Controllers** | استقبال الطلب، استدعاء Service، إرجاع Response | `app/Http/Controllers/Api/BookingController.php:29` |
| **Services** | منطق العمل الحقيقي — كل القواعد هنا | `app/Services/BookingService.php:42` |
| **Models** | تعريف الحقول، العلاقات، Scopes، Accessors | `app/Models/Appointment.php:1` |
| **Resources** | تحويل Model إلى JSON للـ API | `app/Http/Resources/AppointmentResource.php:1` |
| **Filament** | CRUD إداري + Livewire components | `app/Filament/Resources/` (142 ملف) |

> **قاعدة ذهبية في هذا المشروع:** الـ Controller لا يحتوي منطقًا. كل منطق في Service. الـ Model لا يحتوي منطق عمل معقد — فقط علاقات وscopes.

---

## 2. طبقات النظام الأربع

### 2.1 طبقة الـ API (العملاء)

- **بلا حالة (Stateless):** كل طلب يحمل `Authorization: Bearer <token>` — `config/sanctum.php`
- **Tokens:** `access_token` قصير + `refresh_token` طويل — `app/Services/AuthTokenService.php:1`
- **Public vs Authenticated:**
  - Public: `GET /api/services`, `GET /api/providers`, `GET /api/availability/*`, `POST /api/auth/*`
  - Authenticated: `GET /api/appointments/*`, `POST /api/bookings`, `GET /api/profile`, `POST /api/appointments/reminders`

### 2.2 طبقة الـ Web (Filament + Livewire)

- **Filament 4** في `/admin` — 18 Resource كاملة (Appointments, Providers, Services, ...)
- **Staff Dashboard** في `/` (subdomain) — Livewire component واحد كبير `app/Livewire/StaffDashboard.php` يدير التقويم والسحب والدفع
- **Livewire components:** `ScheduleManager`, `ShiftManager`, `WeeklyScheduleTimeline`, `CustomerLookup` — `resources/views/livewire/`

### 2.3 طبقة الخدمات (Services) — قلب النظام

71 Service في `app/Services/` — انظر [`04-services/README.md`](../04-services/README.md) للخريطة الكاملة.

أهمها:

| Service | الدور | السطر |
|---------|-------|-------|
| `BookingService` | منسق الحجز الرئيسي | `app/Services/BookingService.php:42` |
| `BookingValidationService` | كل قواعد التحقق | `app/Services/BookingValidationService.php:1` |
| `ServiceAvailabilityService` | حساب الـ Slots | `app/Services/ServiceAvailabilityService.php:1` |
| `TaxCalculatorService` | الحساب الوحيد للضريبة | `app/Services/TaxCalculatorService.php:1` |
| `InvoiceFinalizationService` | العملية الوحيدة للدفع | `app/Services/InvoiceFinalizationService.php:1` |
| `BookingLockService` | منع الحجز المزدوج | `app/Services/BookingLockService.php:1` |

### 2.4 طبقة البيانات (Data)

- 46 Model في `app/Models/`
- 74 Migration في `database/migrations/`
- 26 Seeder في `database/seeders/`
- PostgreSQL مع `bcmath` للأموال

---

## 3. تدفق البيانات — مثال حجز واحد

```
POST /api/bookings  { date: "2026-09-15", services: [{service_id:3, provider_id:7, start_time:"10:00"}] }
  │
  ├─ routes/api.php:338  →  BookingController@store
  │     │
  │     ├─ BookingCreateRequest (validation: required, exists, date_format)
  │     │
  │     └─ BookingService@createBooking  (app/Services/BookingService.php:42)
  │           │
  │           ├─ validateBasicData()  (خارج transaction — شكل الطلب فقط)
  │           ├─ sortServicesByStartTime()
  │           └─ DB::transaction()
  │                 ├─ BookingLockService@lockUsers([7, customerId])  — SELECT FOR UPDATE
  │                 ├─ validateDailyBookingLimit()  (تحت القفل)
  │                 ├─ validateAndPrepareServices()  — لكل خدمة:
  │                 │     ├─ validateProviderOffersService()
  │                 │     ├─ getEffectivePrice() / getEffectiveDuration()
  │                 │     ├─ validateSequentialTiming()
  │                 │     ├─ validateTimeSlotAvailability()  — 7 فحوصات
  │                 │     └─ assertCustomerIsFree()
  │                 ├─ calculateTotals()  → TaxCalculatorService@calculateBulk
  │                 ├─ Appointment::create()
  │                 ├─ AppointmentService::create() × N
  │                 └─ InvoiceService@createDtaftInvoiceFromAppointment() → Invoice DRAFT
  │
  ├─ BookingMailService@sendForNewBooking()  (queued, بعد الـ commit)
  │
  └─ AppointmentResource  →  201 Created JSON
```

التفصيل الكامل في [`06-booking-flow/README.md`](../06-booking-flow/README.md).

---

## 4. القرارات المعمارية الكبرى

| القرار | لماذا | أين موثق |
|--------|-------|----------|
| GROSS pricing | القانون الألماني + بساطة العميل | `business-model.md` + `design-decisions.md` |
| bcmath لكل المال | تجنب أخطاء float (0.1+0.2≠0.3) | `04-services/tax-calculator.md` |
| Two-stage invoicing | الفاتورة القانونية لا تُصدر إلا عند الدفع | `08-invoicing-printing/` |
| قفل على `users` لا `appointments` | لا يمكن قفل صفوف غير موجودة (غياب الحجز) | `06-booking-flow/concurrency.md` |
| `TaxCalculatorService` وحيد | منع اختلاف الضريبة بين appointments و invoices | `04-services/tax-calculator.md` |
| `created_status=1` دائمًا | لا دفع أونلاين → كل حجز مؤكد | `design-decisions.md` |

---

## 5. الملفات المفتاحية للمعمارية

| الملف | الدور |
|-------|-------|
| `bootstrap/app.php` | تسجيل Middleware + Exception handling + Rate limiters |
| `config/app.php` | إعدادات عامة |
| `config/auth.php` + `config/auth_tokens.php` | Sanctum + TTL للـ tokens |
| `config/permission.php` | Spatie Roles |
| `app/Helpers/Main.php` | دالة `get_setting()` العامة |
| `app/Providers/AppServiceProvider.php` | تسجيل Rate Limiters (`registerAuthRateLimiters`) |

---

*التالي: [`tech-stack.md`](tech-stack.md) — التقنيات بالتفصيل*
