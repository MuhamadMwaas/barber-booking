# 01 — نظرة عامة وفكرة النظام

> **المسار:** `docs/01-overview/`  
> **الملفات المرتبطة:** `Agent.md:1` (System Overview)، `API.md:1`، `app/Models/Appointment.php:1`، `app/Models/User.php:1`

---

## 1. ما هو النظام؟

**BarberBooking** هو نظام إدارة صالون تجميل (Beauty Salon Management System) مبني بـ **Laravel 12** و **Filament 4**. يغطي دورة الحياة الكاملة:

```
العميل يتصفح الخدمات → يتحقق من التوفر → يحجز موعدًا → يصل للصالون → يُقدَّم الخدمة → يدفع نقدًا في المحل → تُطبع فاتورة مطابقة للضريبة الألمانية
```

النظام ليس متجرًا إلكترونيًا: **لا يوجد دفع أونلاين**. كل الحجوزات تُدفع نقدًا/بطاقة عند الكاشير. هذا القرار يبسّط التدفق ولكنه يفرض قواعد دقيقة على الحجز والفوترة (انظر `02-architecture/design-decisions.md`).

### التقنيات الأساسية

| الطبقة | التقنية | الملف/الإعداد |
|--------|---------|---------------|
| Framework | Laravel 12 (PHP 8.2) | `composer.json:12` |
| لوحة الإدارة | Filament 4.0 | `composer.json` + `app/Filament/` |
| قاعدة البيانات | PostgreSQL (Neon) | `config/database.php` + `database/migrations/` (74 ملف) |
| التوثيق | Sanctum (API) + Spatie Permissions (أدوار) | `config/auth.php`, `config/permission.php` |
| الأصول | Vite + TailwindCSS 4 | `vite.config.js` |
| الإشعارات | OneSignal (push) + Mail + SMS (Vonage/Seven.io) | `app/Services/OneSignalService.php`, `app/Services/Sms/` |
| الترجمة | نظام مخصص `Language` + `ServiceTranslation` + Filament Language Switcher (en/ar/de) | `app/Models/Language.php` |

---

## 2. أصحاب المصلحة (Actors)

### 2.1 العميل (Customer)
- **يمثله:** `User` بدور `customer` — `app/Models/User.php:80` (`STAFF_ROLES` لا تشمله)
- **يستطيع:** تصفح الخدمات `GET /api/services`، معرفة التوفر `GET /api/availability/*`، إنشاء حجز `POST /api/bookings` (`routes/api.php:338`)، عرض حجوزاته `GET /api/appointments`، إلغاء حجز `POST /api/bookings/{id}/cancel`، إدارة التذكيرات `POST /api/appointments/reminders`
- **قد يكون ضيفًا (Guest):** الحجز يقبل `customer_id = null` ويُخزن `customer_name/email/phone` مباشرة على `appointments` — `app/Models/Appointment.php:126` (`BookingService.php:62`)

### 2.2 المزود (Provider / Stylist / Barber)
- **يمثله:** `User` بدور `provider` — `is_active` + `branch_id`
- **له:** جدول عمل أسبوعي `ProviderScheduledWork` (`app/Models/ProviderScheduledWork.php:1`)، إجازات `ProviderTimeOff`، خدمات يقدمها عبر pivot `provider_service` (مع `custom_price`, `custom_duration`)
- **يظهر في:** `GET /api/providers`, `GET /api/availability/provider`

### 2.3 الإدارة (Admin / Manager / SuperAdmin)
- **يمثلها:** `User` بدور `admin`/`manager`/`SuperAdmin` — `app/Models/User.php:80`
- **تستطيع:** كل CRUD في Filament (`/admin`) — المواعيد، المزودين، الخدمات، الفوترة، الطباعة، التقارير
- **تدخل لوحة التحكم عبر:** `canAccessPanel()` في `app/Models/User.php:120` (يشترط `hasStaffRole() && is_active`)

### 2.4 النظام نفسه (System)
- يحسب الضريبة (`TaxCalculatorService.php:1`)، يولّد أرقام المستندات (`DocumentNumberGenerator.php:1`)، يرسل تذكيرات (`AppointmentReminderService.php:1`)، يراقب الإلغاءات (`CancellationMonitor.php:1`)

---

## 3. دورة حياة الحجز — القصة الكاملة

### 3.1 من وجهة نظر العميل (Mobile/Web App)

```
1. يفتح التطبيق
   → GET /api/services?per_page=15                    (ServicesController@index)
   → GET /api/services/{id}                           (تفاصيل خدمة)

2. يختار خدمة وتاريخًا
   → GET /api/availability/service?service_id=3&date=2026-09-15
     (AvailabilityController@getServiceAvailability — يرجع كل المزودين المتاحين مع slots)
   أو
   → GET /api/availability/provider?service_id=3&provider_id=7&date=2026-09-15
   → GET /api/availability/calendar?service_id=3&start_date=2026-09-01&end_date=2026-09-30

3. يسجل دخول أو يكمل كضيف
   → POST /api/auth/register  → OTP → POST /api/auth/verify-otp → token
   → POST /api/auth/login {email, password} → token
   → أو Google OAuth: POST /api/auth/google/mobile

4. يحجز
   → POST /api/bookings  (BookingController@store → BookingService@createBooking)
     Body: { date, payment_method: "cash", services: [{service_id, provider_id, start_time}], notes }
   ← 201 { appointment, invoice (DRAFT) }

5. يتابع حجوزاته
   → GET /api/appointments?status=PENDING
   → GET /api/appointments/{id}
   → POST /api/appointments/{id}/cancel  (أو POST /api/bookings/{id}/cancel — نفس النتيجة)

6. يضبط تذكيرًا
   → GET /api/appointments/reminders/options
   → POST /api/appointments/reminders {appointment_id, lead_minutes}
```

### 3.2 من وجهة نظر الصالون (Filament + Staff Dashboard)

```
العميل يصل
  → الموظف يفتح Staff Dashboard (Livewire: app/Livewire/StaffDashboard.php)
  → يرى Timeline اليوم + كل المواعيد (AttendanceBoardService)
  → يؤكد الخدمات المقدمة (قد يضيف خدمة: BookingService@addServiceToBooking)
  → يعالج الدفع: InvoiceFinalizationService@finalizeAppointmentPayment(cash|card)
    → توليد رقم فاتورة INV-2026-000001 (DocumentNumberGenerator داخل transaction)
    → إنشاء Payment واحد (PaymentMethod cash/card)
    → تحويل كل المواعيد المرتبطة إلى COMPLETED
  → يطبع: GET /invoice/{id}/print  (PrintController@print → InvoiceTemplate)
```

### 3.3 ماذا يحدث في قاعدة البيانات عند الحجز؟

```sql
-- داخل DB::transaction واحدة (BookingService.php:102)
INSERT INTO appointments (number, customer_id, provider_id, appointment_date, start_time, end_time, duration_minutes, subtotal, tax_amount, total_amount, status=0, payment_status=0, created_status=1, ...)
INSERT INTO appointment_services (appointment_id, service_id, service_name, duration_minutes, price, sequence_order) -- لكل خدمة
INSERT INTO invoices (appointment_id, status=0/DRAFT, invoice_number=NULL, subtotal, tax_amount, total_amount)
INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, tax_rate, tax_amount, total_amount) -- لكل خدمة
-- بعد الـ commit: queued emails (BookingMailService) + reminder job
```

---

## 4. المفاهيم الجوهرية التي يجب فهمها أولًا

| المفهوم | الشرح السريع | أين يُشرح بعمق |
|---------|--------------|----------------|
| **GROSS pricing** | كل الأسعار في DB شاملة الضريبة. الضريبة تُستخرج عكسيًا لا تُضاف | `04-services/tax-calculator.md` + `02-architecture/design-decisions.md` |
| **bcmath** | كل حسابات المال بـ `bcmath` لا `float` لتجنب أخطاء الفاصلة | `04-services/tax-calculator.md:10` |
| **Two-stage invoicing** | مسودة عند الحجز (لا رقم) → مدفوعة عند الدفع (مع رقم متسلسل) | `08-invoicing-printing/invoice-lifecycle.md` |
| **created_status=1** | كل حجز يحجب وقته فورًا (لا يوجد دفع أونلاين فلا حجز غير مؤكد) | `06-booking-flow/README.md` + `Agent.md:29` |
| **BookingLockService** | قفل `SELECT FOR UPDATE` على صفوف `users` (المزودين+العميل) لمنع الحجز المزدوج | `06-booking-flow/concurrency.md` |
| **Single source of truth للضريبة** | `TaxCalculatorService` وحده يحسب الضريبة — كل الطبقات تفوّض إليه | `04-services/tax-calculator.md` |
| **Guest booking** | `customer_id` nullable + `customer_name/email/phone` على `appointments` | `03-data-models/appointment.md` |

---

## 5. أين تجد كل شيء؟ — خريطة سريعة

```
Agent.md                          ← مرجع AI الشامل (12 قسمًا، 1000+ سطر)
API.md                            ← مرجع API للموبايل بالعربية (1700+ سطر)
docs/BOOKING_FLOW.md              ← تدفق الحجز المفصل (1191 سطر) — لا يزال المرجع الأدق لمرحلة الحجز
docs/01-overview/                 ← أنت هنا
docs/02-architecture/             ← البنية
docs/03-data-models/              ← كل النماذج
docs/04-services/                 ← كل الخدمات
docs/05-api/                      ← كل الـ Endpoints
docs/06-booking-flow/             ← الحجز بعمق (التزامن، الإجازات، ...)
docs/07-filament-admin/           ← لوحة الإدارة
docs/08-invoicing-printing/       ← الفوترة والطباعة
docs/09-security/                 ← الأمان
docs/10-operations/               ← التشغيل
```

---

## 6. للمطور الجديد — ماذا تقرأ بعد هذه الصفحة؟

1. [`business-model.md`](business-model.md) — افهم لماذا النظام مصمم هكذا (دفع نقدي، GROSS، مسودة).
2. [`user-roles.md`](user-roles.md) — افهم الأدوار والصلاحيات.
3. [`02-architecture/README.md`](../02-architecture/README.md) — افهم الطبقات.
4. [`06-booking-flow/README.md`](../06-booking-flow/README.md) — افهم أعقد جزء في النظام.

---

*التالي: [`business-model.md`](business-model.md) — نموذج العمل بالتفصيل*
