# دورة حياة الطلب — Request Lifecycle

> **الملفات:** `bootstrap/app.php:1`، `routes/api.php:1`، `app/Http/Controllers/Api/BookingController.php:1`، `app/Services/BookingService.php:42`

---

## 1. من الـ HTTP حتى الـ Response — مثال `POST /api/bookings`

```
Client (Mobile)
  │
  │  POST /api/bookings
  │  Headers: Authorization: Bearer 1|abc..., Accept: application/json, Accept-Language: ar
  │  Body: { date: "2026-09-15", payment_method: "cash", services: [{service_id:3, provider_id:7, start_time:"10:00"}] }
  │
  ▼
┌─────────────────────────────────────────────────────────────────┐
│  1. Web Server (Nginx/Apache) → public/index.php                │
│     └─ bootstrap/app.php: ينشئ Application + يسجل Middleware    │
└──────────────────────────┬──────────────────────────────────────┘
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│  2. Global Middleware (bootstrap/app.php)                        │
│     ├─ TrustProxies                                             │
│     ├─ HandleCors                                               │
│     └─ ...                                                      │
└──────────────────────────┬──────────────────────────────────────┘
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│  3. Router — routes/api.php:338                                 │
│     Route::prefix('bookings')->group(...)                       │
│     Route::post('/', [BookingController::class, 'store'])       │
│     Middleware على المجموعة: ['auth:sanctum', 'verified.customer']│
└──────────────────────────┬──────────────────────────────────────┘
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│  4. Route Middleware (بالترتيب)                                 │
│     ├─ auth:sanctum                                             │
│     │    └─ يفحص Authorization header → يبحث في personal_access_tokens│
│     │    └─ إن فشل → 401 Unauthorized                           │
│     ├─ verified.customer (EnsureCustomerIsVerified)             │
│     │    └─ يفحص User@is_account_verified                       │
│     │    └─ إن فشل → 403 + requires_otp_verification            │
│     └─ throttle (إن وُجد على المسار)                            │
└──────────────────────────┬──────────────────────────────────────┘
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│  5. Form Request — BookingCreateRequest                          │
│     app/Http/Requests/Api/BookingCreateRequest.php              │
│     ├─ authorize(): يرجع true (التحقق تم في Middleware)         │
│     ├─ rules():                                                 │
│     │    'date' => 'required|date_format:Y-m-d|after_or_equal:today'│
│     │    'services' => 'required|array|min:1|max:10'            │
│     │    'services.*.service_id' => 'required|exists:services,id'│
│     │    'services.*.provider_id' => 'required|exists:users,id' │
│     │    'services.*.start_time' => 'required|date_format:H:i'  │
│     │    'payment_method' => 'required|in:cash,online'          │
│     └─ إن فشل → 422 { success:false, errors:{...} }             │
│     └─ إن نجح → $request->validated() يحتوي الحقول المسموحة فقط│
└──────────────────────────┬──────────────────────────────────────┘
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│  6. Controller — BookingController@store                         │
│     app/Http/Controllers/Api/BookingController.php:29           │
│     public function store(BookingCreateRequest $request) {      │
│         $customer = request()->user(); // من Sanctum            │
│         $appointment = $this->bookingService                    │
│             ->createBooking($customer, $request->validated());  │
│         return new AppointmentResource($appointment); // 201    │
│     }                                                           │
│     ├─ try/catch:                                               │
│     │    SlotUnavailableException → 409 { error_type: slot_conflict }│
│     │    InvalidArgumentException → 422                         │
│     │    ModelNotFoundException → 404                           │
│     │    Throwable → 500 + Log                                  │
└──────────────────────────┬──────────────────────────────────────┘
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│  7. Service — BookingService@createBooking                       │
│     app/Services/BookingService.php:42                          │
│     ├─ validateBasicData() — خارج transaction (شكل الطلب فقط)  │
│     ├─ sortServicesByStartTime()                                │
│     └─ DB::transaction() {                                      │
│           lockUsers([providerIds, customerId])                  │
│           validateDailyBookingLimit() — تحت القفل              │
│           validateAndPrepareServices() — لكل خدمة:             │
│             validateProviderOffersService()                      │
│             getEffectivePrice/Duration()                        │
│             validateSequentialTiming()                           │
│             validateTimeSlotAvailability() — 7 فحوصات          │
│             assertCustomerIsFree()                               │
│           calculateTotals() → TaxCalculatorService              │
│           Appointment::create()                                  │
│           AppointmentService::create() × N                       │
│           InvoiceService::createDtaftInvoiceFromAppointment()   │
│        }                                                        │
│     └─ BookingMailService::sendForNewBooking() — queued         │
└──────────────────────────┬──────────────────────────────────────┘
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│  8. Model Events — Appointment.php:80 (boot)                    │
│     ├─ creating: generateAppointmentNumber() إن لم يوجد        │
│     └─ (بعد الحفظ) — لا شيء إضافي هنا                          │
└──────────────────────────┬──────────────────────────────────────┘
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│  9. Resource — AppointmentResource                               │
│     app/Http/Resources/AppointmentResource.php:1                │
│     toArray(): يحول Appointment + relations إلى JSON            │
│     { id, number, appointment_date, start_time, end_time,      │
│       status, payment_status, provider:{...},                   │
│       services_details:[...], subtotal, tax_amount, total_amount }│
└──────────────────────────┬──────────────────────────────────────┘
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│  10. Response Middleware (reverse)                               │
│      └─ CORS headers, etc.                                      │
└──────────────────────────┬──────────────────────────────────────┘
                           ▼
                    201 Created JSON
                    (أو 409/422/500 حسب الحالة)
```

---

## 2. مسار Filament — `GET /admin/appointments`

```
Browser → GET /admin/appointments
  │
  ├─ routes/web.php (Filament auto-registers /admin/*)
  ├─ Middleware: auth (session) + canAccessPanel() — app/Models/User.php:120
  │    └─ إن فشل → redirect /admin/login
  ├─ Filament Panel Provider — app/Providers/Filament/AdminPanelProvider.php
  ├─ AppointmentResource::getPages() → ListAppointments page
  ├─ AppointmentsTable — يبني query + columns + filters + actions
  ├─ Eloquent: Appointment::query()->with(...)->paginate()
  └─ Blade: resources/views/filament/... → HTML
```

---

## 3. مسار Staff Dashboard — `GET /` (Livewire)

```
Browser → GET / (staff subdomain)
  │
  ├─ routes/web.php:30 — Livewire StaffDashboard
  ├─ Middleware: EnsureStaffDashboardAccess — يفحص hasStaffRole + is_active
  ├─ StaffDashboard.php: mount() — يحمّل providers, appointments, schedules
  ├─ render() → resources/views/livewire/staff-dashboard.blade.php
  └─ Livewire JS: تفاعل بدون reload (drag & drop للمواعيد، إضافة خدمة، دفع)
```

---

## 4. Middleware المسجلة

| Middleware | الملف | يفعل ماذا |
|------------|-------|-----------|
| `auth:sanctum` | `config/auth.php` | يتحقق من Bearer token في `personal_access_tokens` |
| `verified.customer` | `app/Http/Middleware/EnsureCustomerIsVerified.php` | يمنع غير الموثقين من bookings/appointments |
| `EnsureStaffDashboardAccess` | `app/Http/Middleware/EnsureStaffDashboardAccess.php` | يمنع غير الـ staff من Staff Dashboard |
| `throttle:*` | `config/rate_limits.php` + `AppServiceProvider.php` | يحد المعدل (auth-login, otp-verify, ...) |
| `role:SuperAdmin` | Spatie | يفحص دور المستخدم |

---

## 5. تحديد اللغة — Language Resolution

```
Request
  ├─ ?lang=ar  (query param — أعلى أولوية)
  ├─ Accept-Language: ar  (header)
  └─ CMS_DEFAULT_LANGUAGE (config — افتراضي ar)
  │
  └─ AppServiceProvider / CmsLanguageResolver → App::setLocale()
       └─ كل response تالي يستخدم locale المحدد
       └─ Service::getNameIn($locale) يرجع الترجمة المناسبة
```

`API.md:128` + `app/Services/Cms/CmsLanguageResolver.php:1`

---

*التالي: [`design-decisions.md`](design-decisions.md)*
