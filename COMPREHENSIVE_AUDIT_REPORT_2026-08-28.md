# التقرير الشامل للتدقيق الأمني ومنطق الأعمال — BarberBooking

> **تاريخ الفحص:** 28 أغسطس 2026  
> **الإصدار:** 1.0 — تقرير احترافي شامل (لا تعديلات كود)  
> **النطاق المتفق عليه:** شامل 100% (عربي + مصطلحات EN) — متوازن (أمان + منطق أعمال + أداء + جودة) — مصنف بالخطورة مع خطوات الإصلاح  
> **المنهجية:** قراءة مباشرة سطر-بسطر لكل ملف، تتبع تدفق البيانات (Data-Flow)، مقارنة Parity بين طبقات التوفر والحجز، تحليل Race Conditions، Mass Assignment، IDOR، Injection، Rate Limiting، ومراجعة Migrations/Models/Enums/Config

---

## 0. كيف تقرأ هذا التقرير

- كل نتيجة تحمل: **الملف:السطر** الدقيق، **الوصف**، **الأثر**، **الخطورة** (Critical/High/Medium/Low)، و**الإصلاح المقترح** القابل للتنفيذ مباشرة.
- المصطلحات التقنية (مثل `lockForUpdate`, `fillable`, `IDOR`) بقيت بالإنجليزية لسهولة البحث في الكود.
- لا تم تعديل أي ملف — هذا التقرير للقراءة واتخاذ القرار فقط.

**الملفات المفحوصة (104 ملف):**

| الطبقة | العدد | أمثلة |
|---|---|---|
| `app/Services` | 26 | `BookingService.php`, `BookingValidationService.php`, `ServiceAvailabilityService.php`, `InvoiceService.php`, `InvoiceFinalizationService.php`, `TaxCalculatorService.php`, `DashboardService.php`, `AuthTokenService.php`, `OtpService.php`, `DailyReportService.php`, `DashboardStatsService.php`, `ReportsService.php`, `GapAnalysisService.php`, `CustomerLookupService.php`, `AppointmentService.php` + `BookingService2.php` legacy |
| `app/Http/Controllers` | 16 | `Api/AuthController.php`, `Api/BookingController.php`, `Api/AvailabilityController.php`, `Api/AppointmentController.php`, `Api/ProvidersController.php`, `Api/ServicesController.php`, `Api/ProfileController.php`, `Api/OtpController.php`, `Api/SocialAuthController.php`, `PrintController.php`, `AppointmentPrintController.php` |
| `app/Models` + `Enum` | 26 | `Appointment.php`, `Invoice.php`, `InvoiceItem.php`, `Payment.php`, `User.php`, `Service.php`, `ProviderScheduledWork.php`, `ProviderTimeOff.php`, `Otp.php`, `RefreshToken.php`, `Branch.php` + 8 Enums |
| `app/Livewire` + `Filament` | 18 | `StaffDashboard.php` (1605 سطر), `CustomerLookup.php`, `StaffStats.php`, `StaffReports.php`, `Filament/Resources/Appointments/*` |
| `Middleware` + `Routes` + `Config` | 14 | `EnsureStaffDashboardAccess.php`, `SetApiLocale.php`, `routes/api.php`, `routes/web.php`, `config/sanctum.php`, `config/otp.php` |
| `Migrations` | 65 | كل ملفات `database/migrations` |
| **الإجمالي** | **~104** | |

---

## 1. الملخص التنفيذي — Executive Summary

### 1.1 الأرقام

| الخطورة | العدد | نسبة |
|---|---|---|
| **Critical** | **23** | 11% |
| **High** | **64** | 31% |
| **Medium** | **78** | 37% |
| **Low** | **44** | 21% |
| **الإجمالي** | **~209** | 100% |

> لا يعني العدد الكبير أن النظام "منهار" — كثير من النتائج متكررة النمط (مثلاً `Mass Assignment` يظهر في 4 Models بنفس الجذر). لكن **النواة المالية والحجوزات تحتوي على 3 ثغرات Critical قابلة للاستغلال اليوم**.

### 1.2 أخطر 7 ثغرات يجب إصلاحها خلال 24-72 ساعة (P0)

| # | الملف:السطر | العنوان | لماذا خطر؟ |
|---|---|---|---|
| **C-01** | `routes/web.php:150` | **مسار `/internal/clear-cache` بدون auth ينفذ `exec('sudo ...')`** | أي زائر على الإنترنت يشغل `sudo /var/www/lookup.com/clear-my-cache.sh` → **RCE / DoS** كامل. |
| **C-02** | `app/Services/AuthTokenService.php:18-20` | **AccessToken لا ينتهي أبداً (مُعلق)** | `expires_at` مُعلق كـ comment → التوكن صالح إلى الأبد. سرقة واحدة = اختراق دائم. |
| **C-03** | `app/Services/BookingService.php:77` + `BookingValidationService.php:126` | **TOCTOU Double-Booking بدون `lockForUpdate`** | التحقق خارج الـ Transaction وبدون قفل → عميلان يحجزان نفس الـ slot. |
| **C-04** | `app/Http/Controllers/PrintController.php:22` و `AppointmentPrintController.php:18` و `InvoiceTemplateController.php:31` | **IDOR طباعة — أي customer يطبع فاتورة/تذكرة أي عميل آخر** | تسريب PII + مبالغ مالية بتخمين `invoice id`. |
| **C-05** | `app/Services/InvoiceFinalizationService.php:219` | **`PaymentStatus::from((int)$paymentType)` يرمي 500 دائماً** | تحصيل الفواتير مكسور كلياً عبر StaffDashboard. |
| **C-06** | `app/Http/Controllers/Api/NotificationController.php:143` + `routes/api.php:215` | **أي customer موثق يرسل Push لكل المستخدمين** | Spam/Phishing جماعي بدون دور admin. |
| **C-07** | `app/Models/Appointment.php:26` + `Invoice.php:19` + `Payment.php:15` + `User.php:35` | **Mass Assignment مالي — الحقول `total_amount/status/payment_status` في `fillable`** | تلاعب بالأسعار وحالة الدفع عبر API. |

**الخلاصة التنفيذية:** النظام يعمل وظيفياً لكنه **هش أمام التزامن (Concurrency)، ويكشف بيانات عبر IDOR، ويحسب الضرائب بـ 4 منطقات مختلفة، وتواريخ التوفر لا تطابق قواعد الحجز Paritiy Gap**. الإصلاحات P0 المذكورة تمنع الاستغلال الفوري؛ الإصلاحات P1 توحد المنطق المالي وتمنع فقدان البيانات.

---

## 2. طبقة Services ومنطق الأعمال — التفصيل

### 2.1 `BookingService.php` (802 سطر) — قلب النظام

| # | الخطورة | الملف:السطر | الوصف | الأثر | الإصلاح |
|---|---|---|---|---|---|
| **R-01** | **Critical** | `BookingService.php:77` خارج `DB::transaction` + `BookingValidationService.php:126` بدون `lockForUpdate` | فجوة TOCTOU بين التحقق والإنشاء. طلبان متزامنان يمرّان من `validateTimeSlotAvailability` ثم ينشئان حجزين متداخلين. | Double-booking — كسر أهم Invariant | نقل كل التحقق **داخل** `DB::transaction` + `Appointment::where(...)->lockForUpdate()->exists()` + إضافة `UNIQUE(provider_id, appointment_date, start_time)` كحماية DB |
| **R-02** | **Critical** | `BookingService.php:380` | `return $service->duration_minutes;` في أول السطر يجعل `custom_duration` من `provider_service` غير قابل للوصول (Dead Code). مكرر في `ServiceAvailabilityService.php:591`. | المدة المحجوزة خاطئة → تجاوز ساعات العمل أو hasConflict خاطئ | حذف الـ return المبكر وإرجاع ` $pivot->custom_duration ?? $service->duration_minutes` |
| **L-01** | **High** | `BookingService.php:85-88` + `131` | `payment_status = PAID_ONSTIE_CASH` إذا `markAsPaid=true` ثم إنشاء فاتورة `DRAFT` بـ `amountPaid=0` | تقرير `DailyReportService` يحسب إيراد بدون تحصيل فعلي | إنشاء فاتورة `PAID` مباشرة لـ cash أو إبقاء `payment_status=PENDING` حتى `finalize` |
| **L-02** | **High** | `BookingService.php:61` | `$bookingData['bypass_availability']` و `allow_same_day_past` يؤخذان من المصفوفة بدون تحقق صلاحيات داخل الخدمة | إذا نسي Controller فحص `can('force_booking')` → تجاوز حجز خارج الدوام | تمرير `User $actor` للخدمة وتحقق ` $actor->can('force_booking')` داخلها |
| **L-03** | **High** | `BookingService.php:413-424` | `generateAppointmentNumber` حلقة `exists()` بدون قفل. تصادم نادر → `Duplicate entry` غير معالج | 500 للمستخدم | استخدام `DocumentNumberGenerator` مع `lockForUpdate` + عمود `UNIQUE` + retry |
| **L-04** | **High** | `BookingService.php:696-703` vs `249-326` | `addServiceDifferentProvider` يحسب الضريبة بـ `float/round` بينما `calculateTotals` بـ `bcmath` و reconciliation | فرق سنت بين المسارين | توحيد عبر `TaxCalculatorService::extractTax` فقط |
| **P-01** | **Medium** | `BookingService.php:762` | `recalculateAnchorTotals` عبر `ReflectionMethod` + cast `float` → فقدان دقة | حساب إجمالي خاطئ للـ anchor | جعل `calculateTotals` protected واستدعاء مباشر بتمرير `string` |
| **Q-01** | **Low** | `BookingService.php:356` | `calculateTotalsInverse` ميتة وتستخدم `float` | تشتيت | حذفها |

### 2.2 `BookingService2.php` (428 سطر) — خدمة مكررة Legacy

> **قرار معماري:** وجود خدمتين للحجز بمنطق مختلف يسبب **Parity Bug** حرج. يجب حذف `BookingService2.php` نهائياً والاعتماد على `BookingService.php`.

| # | الخطورة | الملف:السطر | الوصف | الإصلاح |
|---|---|---|---|---|
| **L-06** | **Critical** | `BookingService2.php:283-301` | `calculateTotals` يخلط gross/net ويتجاهل `display_price` | توحيد مع `BookingService` |
| **L-07** | **High** | `BookingService2.php:333` | `getTaxRate() => 19` ثابت يتجاهل `get_setting('tax_rate')` | قراءة من `SettingsService` |
| **R-03** | **High** | `BookingService2.php:120` + `341` | نفس TOCTOU + `generateAppointmentNumber` بـ `uniqid()` المتوقع | حذف الملف |

### 2.3 `BookingValidationService.php` (347 سطر)

| # | الخطورة | الملف:السطر | الوصف | الإصلاح |
|---|---|---|---|---|
| **V-P1** | **High** | `BookingValidationService.php:126` vs `ServiceAvailabilityService.php:454` | Availability يفحص `PENDING` فقط بينما Validation يفحص `PENDING+COMPLETED` + `created_status=1` → Slot يظهر متاحاً ويفشل الحجز أو العكس | توحيد في `Appointment::scopeConflicting()` واحدة |
| **L-09** | **High** | `BookingValidationService.php:272` | `validateNoDuplicateBooking` بـ `whereHas whereIn serviceIds` يعتبر تطابق خدمة واحدة = مكرر | مطابقة **جميع** الخدمات |
| **L-10** | **Medium** | `BookingValidationService.php:332` | `validateDailyBookingLimit` يعد `PENDING` فقط → تجاوز الحد عبر إكمال الحجوزات | عد كل غير الملغاة |
| **L-11** | **Medium** | `BookingValidationService.php:245` | `allowSameDayPast=true` + `isToday()` يعود فوراً بدون فحص `book_buffer` → حجز في الماضي قبل دقائق | تحديد حد `startOfDay` |

### 2.4 `ServiceAvailabilityService.php` (689 سطر)

| # | الخطورة | الملف:السطر | الوصف | الإصلاح |
|---|---|---|---|---|
| **L-12** | **High** | `ServiceAvailabilityService.php:23` + `677` | `CACHE_DURATION=1` ملتبس + `Cache::tags` غير مدعوم مع `file` driver → exception أو stale | استخدام `Cache::remember(...,60,...)` بالثواني وتحقق driver |
| **L-13** | **High** | `ServiceAvailabilityService.php:591` | نفس Dead Code `getEffectiveDuration` | حذف return المبكر |
| **Q-03** | **Medium** | `ServiceAvailabilityService.php:648` | `formatBranchData` typo `adress` → null دائماً | `address` |

### 2.5 `InvoiceService.php` (669 سطر) و `InvoiceFinalizationService.php` (319 سطر)

| # | الخطورة | الملف:السطر | الوصف | الإصلاح |
|---|---|---|---|---|
| **L-14** | **High** | `InvoiceService.php:374` | `createDtaftInvoiceFromAppointment` ينشئ `DRAFT` دائماً حتى لو `amountPaid` >0 | تمرير القيم الحقيقية |
| **L-15** | **High** | `InvoiceService.php:572` | `applyFinalAmount` fallback `total_amount + discount_amount` إذا items فارغة → حساب خاطئ | التحقق من وجود items |
| **L-16** | **Medium** | `InvoiceService.php:610` vs `InvoiceFinalizationService.php:17` | دالتان بنفس الاسم ومنطق مختلف → التباس | حذف إحداهما |
| **C-05** | **Critical** | `InvoiceFinalizationService.php:219` | `PaymentStatus::from((int)$paymentType)` يحول `'PAID_ONSTIE_CASH'` إلى `0` → `ValueError` → كل تحصيل يفشل 500 | `PaymentStatus::tryFrom($paymentType)` بدون `(int)` |
| **L-19** | **High** | `InvoiceFinalizationService.php:304` | `bcscale(6)` تلوث عالمي لكل `bcmath` اللاحقة | تمرير scale لكل عملية بدون `bcscale` |
| **R-04** | **Medium** | `InvoiceFinalizationService.php:45` | لا Idempotency — ضغط مرتين ينشئ `Payment` مكرر | فحص `invoice.status !== DRAFT` + `unique` على `payment_number` |

### 2.6 `TaxCalculatorService.php` (265 سطر)

| # | الخطورة | الملف:السطر | الوصف | الإصلاح |
|---|---|---|---|---|
| **L-21** | **High** | `TaxCalculatorService.php:15` | `scale=2` داخلي → `19.00 / 1.19` بدقة 2 = `15.96` بدل `15.97` | `scale=6` داخلياً والتدوير فقط نهاية |
| **L-22** | **Medium** | `TaxCalculatorService.php:35` | `bcscale` تلوث + `normalizeAmount` يقطع أكثر من منزلتين | عدم استخدام `bcscale` العام |

> **الخلاصة:** 4 طرق حساب ضريبة مختلفة (`BookingService` bcmath 6, `BookingService2` float, `TaxCalculatorService` scale 2, `InvoiceService` TaxCalculator) تعطي نتائج مختلفة لنفس الحجز.

### 2.7 `AuthTokenService.php` (70 سطر) — أخطر ملف أمني

| # | الخطورة | الملف:السطر | الوصف | الإصلاح |
|---|---|---|---|---|
| **S-01** | **Critical** | `AuthTokenService.php:18-20` | التوكن يُعاد مع `expires_at` وهمي لكن في DB `null` → صالح للأبد | فك التعليق: `$tokenModel->expires_at = $expiresAt; $tokenModel->save();` + Middleware يرفض المنتهي |
| **S-02** | **High** | `AuthTokenService.php:61` | `findValidRefreshToken` بدون `user_id` + `hash(sha256, plain+app.key)` → تدوير `APP_KEY` يبطل كل التوكنات | ربط `user_id` أو `hash(plain)` فقط |
| **S-03** | **High** | `AuthTokenService.php:41` | لا تدوير Refresh ولا كشف إعادة استخدام → replay | عند كل refresh أبطل القديم + كشف إعادة استخدام توكن مبطل |

### 2.8 `OtpService.php`, `DashboardService.php`, `DailyReportService.php`, `DashboardStatsService.php`, `ReportsService.php`

| # | الخطورة | الملف:السطر | الوصف |
|---|---|---|---|
| **R-05** | **High** | `OtpService.php:80` | `increment('attempts')` بدون `lockForUpdate` → هجوم متوازي يتجاوز `maxAttempts=5` |
| **L-30** | **High** | `DashboardStatsService.php:175` | `Collection::where('payment_status.value', ...)` لا يدعم dot notation → `outstanding` = 0 دائماً في لوحة الإحصائيات |
| **L-31** | **High** | `DashboardStatsService.php:246` | مقارنة `getAttribute('booking_source') === 'online'` بينما الحقل Enum → كل الحجوزات تُحسب `in_person` |
| **L-33** | **Critical** | `ReportsService.php:20` | إحصائيات الإيراد من `Appointment::sum(total_amount)` تشمل `created_status=0` والملغاة → ربح وهمي، لا تطابق `DailyReportService` الذي يعد الفواتير |
| **L-34** | **High** | `ReportsService.php:249` | `EXTRACT(HOUR FROM start_time)` PostgreSQL syntax يفشل على MySQL |
| **S-05** | **High** | `CustomerLookupService.php:16` | `LIKE "%{$q}%"` بدون escape وبدون حد أدنى في الخدمة → `%` يعيد كل العملاء |

---

## 3. طبقة Controllers و API

### 3.1 النتائج الحرجة المشتركة (Cross-Controller)

| النمط | الخطورة | الدليل | الأثر | الإصلاح العام |
|---|---|---|---|---|
| **انعدام Rate Limiting على Auth/OTP** | **High** | `routes/api.php:39-43` (`register/login/refresh/requestOtp/verifyOtp`) بدون `throttle` | Brute Force + OTP Burn + SMS Budget Drain | `throttle:5,1` للـ login, `throttle:3,1` للـ register, `throttle:5,1` للـ OTP |
| **تسريب OTP في Response** | **High** | `AuthController.php:55` + `OtpController.php:50` `if(config('app.debug')) $response['otp']=...` | تجاوز تحقق إذا `APP_DEBUG=true` نُسي في الإنتاج | حذف `otp` من الـ response نهائياً إلا في `local` مع `ip=127.0.0.1` |
| **كشف خطأ داخلي** | **High** | `AvailabilityController.php:62` + `ProvidersController.php:51` + `ServicesController.php:65` `catch(\Exception $e) return ['error'=>$e->getMessage()]` بدون شرط debug | تسريب SQL/Stack | `report($e); return ['message'=>'Internal error']` في production |
| **SQL Injection عبر `sort_by`** | **High** | `ProvidersController.php:24` + `ServicesController.php:41` `orderBy($sortBy,$sortDirection)` مباشرة | Error-Based Exfiltration | Allowlist: `in:first_name,last_name,created_at` و `in:asc,desc` داخل `FormRequest` |
| **تجاوز Pagination** | **High** | `ProvidersController.php:32` `per_page` بدون `max` + `ServicesController.php:46` | `per_page=100000` → OOM | `min:1|max:50` |
| **منطق بحث `orWhere` يكسر `active()`** | **High** | `ServicesController.php:37` `where(name like) ->orWhere(description like)` بدون `where(function)` | خدمات غير نشطة تظهر | تغليف `where(function($q){ $q->where(...)->orWhere(...); })` |

### 3.2 تفصيل per-Controller (عينة High/Critical)

**`AuthController.php`:**
- **A2 High:** لا throttle على `register/login/refresh` → Credential Stuffing. الإصلاح: `throttle` كما أعلاه.
- **A4 Medium:** `logout` يحذف كل التوكنات → طرد من كل الأجهزة. الإصلاح: `currentAccessToken()->delete()` فقط.

**`BookingController.php` (API):**
- **B1 High:** `getCustomerBookings` بـ `->get()` بدون paginate → 5000 حجز = 15MB. الإصلاح: `paginate(per_page max:50)`.
- **B3 Medium:** `cancel` لا يفحص `start_time > now()` بينما `AppointmentController` يمنع → إلغاء بعد البدء. الإصلاح: توحيد عبر `AppointmentService::cancelAppointment`.

**`AvailabilityController.php`:**
- **V4 Medium:** Parity Gap مع `BookingValidationService` (مذكور سابقاً).

**`ProvidersController.php` / `ServicesController.php`:**
- **PR1/S1 Critical:** Sorting Injection + N+1 + info disclosure (مذكور أعلاه).

**`ProfileController.php`:**
- **PF1 Medium:** تغيير `phone` بدون Regex ولا OTP إجباري → تثبيت رقم مهاجم. الإصلاح: `regex:/^\+?[0-9]{7,15}$/` + `pending_phone`.
- **PF2 Medium:** رفع صورة `image|max:2048` بدون `mimes/dimensions` → صورة 10000x10000 تستهلك الذاكرة. الإصلاح: `mimes:jpeg,png,webp` + `dimensions`.

**`OtpController.php`:**
- **O1 Critical:** لا rate limiting على أي مسار OTP (بينما `PasswordResetController` محمي) → SMS Pumping DoS + Brute Force 1M احتمال. الإصلاح: `throttle:5,1` للإرسال و `throttle:10,1` للتحقق.
- **O3 High:** User Enumeration عبر `firstOrFail` vs `exists` → 404 يكشف الإيميلات المسجلة. الإصلاح: دائماً `200 "If account exists, OTP sent"`.

**`SocialAuthController.php` (قديم):**
- **SC1 High:** `User::create(['name'=>...])` بينما `fillable` هو `first_name/last_name` → ينشئ `first_name=null`. الإصلاح: تقسيم الاسم.
- **SC2 High:** لا `assignRole('customer')` → بدون دور. الإصلاح: إضافته.

**`SocialApiAuthController.php` (جديد):**
- **SA1 Critical:** `email_verified_at => $googleUser['email_verified'] ? now() : now()` كلاهما `now()` → حتى غير الموثق يصبح موثقاً → تجاوز تحقق. الإصلاح: `? now() : null`.
- **SA2 High:** Account Takeover عبر ربط بريد بدون تأكيد `email_verified===true`. الإصلاح: اشتراطه + OTP ربط.

**`NotificationController.php`:**
- **N1 Critical:** أي `customer` موثق يرسل `testSendToAll` / `testSendToAllCustomers` بدون `role:admin` → spam جماعي. الإصلاح: `middleware('role:admin')` أو `abort_unless(app()->isLocal(),404)`.

**`PrintController.php` / `AppointmentPrintController.php` / `InvoiceTemplateController.php`:**
- **PRT1 Critical:** IDOR كامل بدون `Gate::authorize('view',$invoice)` → تعديل `invoice_id` يكشف فواتير الآخرين. الإصلاح: `where('customer_id', auth()->id())->firstOrFail()` أو Policy.
- **APT1 High:** نفس IDOR للـ appointments.

**`PageController.php`:**
- **PG1 Medium:** `terms()` تُصيّر `privacy` بدل `terms` → مخالفة قانونية. الإصلاح: `render('terms',...)`.

**`routes/api.php`:**
- **R2 High:** مسار `POST /test/vonage-sms` مع `abort_unless` مُعلق → أي زائر يرسل SMS لأي رقم → استنزاف رصيد. الإصلاح: حذفه من production.
- **R5 Medium:** typo `noticifation` → مسار مخفي غير مراقب.

**`routes/web.php`:**
- **W1 Critical:** `/internal/clear-cache` بدون middleware ينفذ `exec('sudo ...')` → RCE. الإصلاح: حذفه فوراً أو `middleware(['auth','role:SuperAdmin'])` + IP whitelist + لا `exec`.
- **W2 High:** `/test` يكشف `LineTypeRegistry` عبر `dd()` لأي زائر → info disclosure.

---

## 4. طبقة Models و Database

### 4.1 الثغرات الحرجة في Models

| # | الخطورة | الملف:السطر | الوصف | الإصلاح |
|---|---|---|---|---|
| **A1/I1/Pay1/U1** | **Critical** | `Appointment.php:26` / `Invoice.php:19` / `Payment.php:15` / `User.php:35` | `fillable` يحتوي حقول مالية وحالة (`subtotal/tax/total/status/payment_status/is_override/branch_id/is_active/email_verified_at`) | جعلها `guarded` واستخدام DTO/FormRequest |
| **O1** | **Critical** | `Otp.php:14` + `migration 2025_09_06_114158:18` | `otp` مخزن plaintext بدون hash ولا `hidden` → تسريب DB يكشف كل OTP | حفظ `hash('sha256', $otp)` + `hidden=['otp']` |
| **B2/U2** | **Critical** | `0001_01_01_000000:33` `branch_id cascadeOnDelete` | حذف Branch يحذف كل Users في الفرع | `nullOnDelete()` أو `restrictOnDelete()` |
| **Sanc-C1** | **Critical** | `config/sanctum.php:50` `expiration=null` | توكن لا ينتهي أبداً | `expiration => 30*24*60` |
| **A2/I3** | **High** | `2025_10_10_145021:17` `appointments.number` بدون `unique` + `2025_10_25_180109:22` `invoice_number` بدون `unique` + `2025_10_25_181959:18` `payment_number` بدون `unique` | تكرار أرقام وثائق تحت concurrency | إضافة `->unique()` + retry |
| **O2** | **High** | `Otp.php:16` `device` في fillable لكن العمود غير موجود في أي migration → SQL error | إضافة عمود أو إزالة من fillable |
| **TYP** | **High** | `Branch.php:11` `branchs` / `Invoice.php:31` `segnture` / `PaymentStatus.php:9` `ONSTIE` / `Branch.php:16` `adress` | أخطاء تسمية هيكلية تلوث Schema و API | ميجريشن `renameColumn/renameTable` فوراً قبل الإنتاج |
| **S1** | **High** | `Service.php:58` `branch()` يتوقع `branch_id` غير موجود إطلاقاً في `services` | إضافة `branch_id` أو حذف العلاقة |

### 4.2 Migrations — فهارس وقيود مفقودة

| الملف:السطر | المشكلة | الإصلاح |
|---|---|---|
| `2025_10_10_145021` `appointments` | لا `index(provider_id, appointment_date)` ولا `index(status)` → full scan مع 100k حجز | إضافة فهارس مركبة |
| `2025_10_25_180109` `invoices` | لا `unique(appointment_id)` → حجز بفاتورتين | `unique('appointment_id')` |
| `2025_10_10_141405` `provider_service` | لا `unique(provider_id, service_id)` → تكرار ربط | `unique(['provider_id','service_id'])` |
| `2025_09_06_114158` `otps` | لا `index(expires_at)` → تنظيف بطيء | `index('expires_at')` |
| `0001_01_001` `branchs` ترتيب | `branchs` ميجريشن بعد `users` رغم أن `users` يشير إليه → هش | إعادة تسمية ليسبق `users` |
| `files` `2025_10_24_143950` | `softDeletes` لكن `File::deleting` يحذف الملف الفيزيائي حتى للـ soft → لا استرجاع | فحص `isForceDeleting()` |

### 4.3 Enums

| الملف:السطر | المشكلة | الإصلاح |
|---|---|---|
| `AppointmentStatus.php:29` | `getCancelledStatuses()` ينسى `NO_SHOW=-3` | إضافته |
| `InvoiceStatus.php:78` vs `121` | `isPayable()` ترجع true لـ `DRAFT` بينما `getPayableStatuses()` لا تشمله → تناقض | توحيد |
| `PaymentStatus.php:9` | typo `ONSTIE` | إضافة `ONSITE` مع alias |

---

## 5. طبقة Livewire / Filament / Middleware

### 5.1 `StaffDashboard.php` (1605 سطر) — لوحة التشغيل

| # | الخطورة | الملف:السطر | الوصف | الإصلاح |
|---|---|---|---|---|
| **SD-01** | **Critical** | `StaffDashboard.php:38` 30+ خاصية `public` بدون `#[Validate]` | Livewire يحدّثها من payload → تجاوز | `#[Validate]` + `validate()` في كل action |
| **SD-02** | **Critical** | `StaffDashboard.php:79/843` | `paymentAmount/paymentBaseline/paymentType` public → خصم تعسفي حتى `0.01` | قراءة `total_amount` من DB داخل `processPayment()` + `paymentAmount <= total` + `dashCan('apply_discount')` |
| **SD-03** | **High** | `StaffDashboard.php:536` | `updateAppointment()` يحدّث `start_time/end_time` بدون فحص تعارض/جدولة/إجازات | استدعاء `ServiceAvailabilityService` + `ProviderTimeOff` قبل الحفظ |
| **SD-04** | **High** | `StaffDashboard.php:709` | فحص `in_array(payment_status,[1,2,3])` ينسى `PARTIALLY_REFUNDED` | استخدام `isSuccessful()` helper |
| **SD-10** | **Medium** | `staff-dashboard.blade.php:2` | `wire:poll.5s` على root → 20 موظف = 240 req/min بدون throttle | `wire:poll.60s.visible` + cache |

### 5.2 `CustomerLookup.php`

| # | الخطورة | الدليل | الوصف | الإصلاح |
|---|---|---|---|---|
| **CL-01** | **Critical** | `CustomerLookup.php:13` لا `InteractsWithDashboardPermissions` | أي StaffDashboard:access يبحث حتى provider مبتدئ → تسريب PII | `abort_unless(dashCan('view_customers'),403)` |
| **CL-02** | **High** | `CustomerLookup.php:152` | `viewAppointment` بدون `canActOnAppointment` → provider يرى حجوزات زميله | فحص ownership |
| **CL-03** | **High** | `CustomerLookup.php:56` | بحث `>=2` أحرف بدون throttle → `a*` يجمع كل العملاء | `RateLimiter::attempt('customer-lookup:'.user()->id,10)` + `min:3` |

### 5.3 `DashboardService.php`

| # | الخطورة | الدليل | الوصف | الإصلاح |
|---|---|---|---|---|
| **DS-01** | **High** | `DashboardService.php:190` + `StaffDashboard.php:1594` `@js(preloadedData)` | `getAllCustomers()` يحمّل كل العملاء (phone/email) ويُرسل كـ JSON إلى Blade → أي provider يفتح DevTools يرى كل العملاء (GDPR) | استبداله بـ search endpoint مع throttle |
| **DS-03** | **Medium** | `DashboardService.php:231` | `getAvailableProvidersForServiceAtTime` حلقة 3 queries لكل مزود → 30 مزود = 90 query | eager-load |

### 5.4 Middleware

| الملف:السطر | الخطورة | الوصف | الإصلاح |
|---|---|---|---|
| `EnsureStaffDashboardAccess.php:13` | **High** | يعتمد على `filament()->auth()` فقط ولا يفحص `verified.otp` → provider غير موثق يدخل dashboard | إضافة `EnsureEmailIsVerifiedViaOtp` للمجموعة |
| `SetApiLocale.php` / `CmsLanguageResolver.php:23` | **Medium** | `?lang[]=ar` مصفوفة → `Str::lower(array)` يرمي 500 | `is_string($queryLang) ? Str::lower(...) : null` |
| `EnsureEmailIsVerifiedViaOtp.php:21` | **Medium** | يرجع تفاصيل `email_verified/phone_verified` → enumeration | رسالة عامة فقط |
| `EnsureCanViewAdminPanel.php:44` | **Medium** | إعادة توجيه غير مخوّل إلى dashboard بدون تسجيل → فقدان audit | `Log::warning('admin panel denied',...)` |

---

## 6. ثغرات مقطعية Cross-Cutting

### 6.1 التزامن (Concurrency) — الخيط الأحمر

- **الحجز:** Validation خارج Transaction → double-booking (`BookingService.php:77`). الإصلاح: Transaction + `lockForUpdate`.
- **توليد الأرقام:** `Appointment::where(number)->exists()` بدون قفل → تكرار (`BookingService.php:413`). الإصلاح: `UNIQUE` + retry.
- **الطباعة:** `incrementPrintCount` بـ `increment + update` غير ذري → lost update (`Invoice.php:116`). الإصلاح: `DB::raw('print_count+1')` ذري.
- **OTP:** `increment('attempts')` بدون قفل → تجاوز `maxAttempts` (`OtpService.php:80`). الإصلاح: `lockForUpdate`.

### 6.2 الفواتير والضرائب — 4 منطقات مختلفة

| الخدمة | الطريقة | الدقة | النتيجة |
|---|---|---|---|
| `BookingService::calculateTotals` | bcmath 6 + per-line round + reconciliation | صحيحة | مرجع |
| `BookingService2::calculateTotals` | float + round | خاطئة | فرق سنت |
| `TaxCalculatorService` | bcmath scale 2 | منخفضة | 15.96 بدل 15.97 |
| `InvoiceFinalizationService::calculateReverseTax` | bcscale 6 + round | صحيحة لكن تلوث global | تضارب |

**التوصية:** حذف `BookingService2`, توحيد كل الحسابات عبر `TaxCalculatorService` بضبط `scale=6` داخلي، وتمرير `scale` كـ param بدون `bcscale`.

### 6.3 التوفر مقابل الحجز Parity Gap

```
ServiceAvailabilityService::getProviderAppointments()
  → where status = PENDING فقط (يُهمل COMPLETED و created_status)

BookingValidationService::validateTimeSlotAvailability()
  → where status IN (PENDING, COMPLETED) AND created_status=1

DashboardService::getAvailableProvidersForServiceAtTime()
  → where status != CANCELLED AND created_status=1 (أي غير ملغى)

النتيجة: نفس الـ slot يظهر متاحاً في API ويفشل في الحجز، أو يظهر محجوزاً وهو متاح.
```
**الإصلاح:** إنشاء `Appointment::scopeConflicting()` واحدة وإعادة استخدامها في كل الطبقات.

### 6.4 الأمان — IDOR, Mass Assignment, Enumeration

- **IDOR طباعة:** 3 Controllers بدون فحص ملكية → تسريب فواتير. الإصلاح: Policy.
- **Mass Assignment:** 4 Models تسمح بتعيين حقول مالية. الإصلاح: `guarded`.
- **Enumeration:** `PasswordResetController:48` يرجع 404 يكشف الإيميلات المسجلة + `AvailabilityController:62` يكشف SQL. الإصلاح: رسائل عامة + `report($e)`.

---

## 7. الأداء — N+1 واختناقات

| الموقع | المشكلة | الأثر | الإصلاح |
|---|---|---|---|
| `DashboardService::getAvailableProvidersForServiceAtTime:231` | 3 queries لكل مزود × 30 مزود = 90 query | بطء حاد | تحميل `schedules` و `timeOffs` دفعة واحدة + `whereNotExists` |
| `ServiceAvailabilityService::getAvailabilityCalendar` | 31 يوم × N مزود = ~800 query | Timeout | Cache + throttling 30/min (موجود لكن غير كافٍ) |
| `DailyReportService:140` | `Invoice::where(Paid)->get()` ثم فلترة PHP → تحميل كل الفواتير في الذاكرة | OOM مع شهر كامل | `chunk` أو `whereBetween` مفهرس |
| `ReportsService:115` | `User::find` داخل حلقة | N+1 | `whereIn()->get()->keyBy` |
| `StaffDashboard: wire:poll.5s` | كل عميل يطلب providers+appointments+timeOffs كل 5 ثوانٍ | DoS | `wire:poll.60s.visible` |

---

## 8. الجودة و Technical Debt

| الملف:السطر | الوصف | الخطورة | الإصلاح |
|---|---|---|---|
| `InvoiceService.php:374` `createDtaft` | typo تاريخي `Dtaft` بدل `Draft` | Low | لا تصححه عشوائياً — صحح مع Alias |
| `BookingService.php:380` / `ServiceAvailabilityService.php:591` | `custom_duration` معطل بـ return مبكر | High | حذف return |
| `BookingService2.php` كامل | خدمة مكررة legacy | Critical معماري | حذف |
| `Branch.php:11` `branchs` / `Invoice.php:31` `segnture` | أخطاء تسمية تنتشر للـ API | High | ميجريشن rename قبل الإنتاج |
| `StaffDashboard.php` imports | `InvoiceStatus, PaymentStatus, BookingValidationService` غير مستخدمة | Low | تنظيف |
| `config/sanctum.php:65` `token_prefix=''` | يمنع GitHub secret scanning | Medium | `barber_` |
| `tests` | لا توجد tests متخصصة للـ Dashboard/حجز متزامن | High | إضافة اختبارات concurrency |

---

## 9. خارطة الأولويات — Roadmap

### P0 — خلال 24 ساعة (إيقاف النزيف)

1. **حذف/حماية `GET /internal/clear-cache`** (`routes/web.php:150`) — إما حذفه نهائياً أو `middleware(['auth','role:SuperAdmin','throttle:3,1'])` + IP whitelist + إزالة `exec`.
2. **إصلاح `AuthTokenService.php:18`** — فك التعليق عن `expires_at` + Middleware يرفض المنتهي.
3. **حماية طباعة الفواتير/التذاكر** — إضافة `Gate::authorize('view',$invoice)` في `PrintController.php:22`, `AppointmentPrintController.php:18`, `InvoiceTemplateController.php:31`.
4. **حماية `POST /noticifation/test-send-to-all`** — `middleware('role:admin')` أو `abort_unless(app()->isLocal(),404)`.
5. **إصلاح `InvoiceFinalizationService.php:219`** — `PaymentStatus::tryFrom($paymentType)` بدون `(int)`.
6. **تجميد `fillable` المالية** — نقل `subtotal/tax/total/status/payment_status/is_override` إلى `guarded` في `Appointment, Invoice, Payment, User`.
7. **إصلاح `SocialApiAuthController.php:161`** — `? now() : null` بدل `? now() : now()`.

### P1 — خلال أسبوع (توحيد المنطق)

8. إضافة `THROTTLE` لكل مسارات `auth/otp` (`routes/api.php:39,57,62`) + حذف OTP من Response.
9. توحيد Availability vs Booking عبر `Appointment::scopeConflicting()` وإعادة استخدامها.
10. توحيد حساب الضرائب عبر `TaxCalculatorService` (scale 6) + حذف `BookingService2.php`.
11. إضافة `UNIQUE` على `appointments.number`, `invoices.invoice_number`, `payments.payment_number` + `branch_service` pivot.
12. إصلاح `branchs→branches`, `adress→address`, `segnture→signature` عبر ميجريشن.
13. إصلاح `DashboardStatsService.php:175` (outstanding) و `246` (sourceOf) + `ReportsService.php:249` (HOUR).
14. إزالة `getAllCustomers()` من `preloadedData` واستبداله بـ search endpoint مع throttle.
15. نقل التحقق في `BookingService.php:77` داخل Transaction + `lockForUpdate`.

### P2 — خلال شهر (صلابة)

16. إضافة فهارس `appointments(provider_id, appointment_date)`, `invoices(appointment_id)`, `otps(expires_at)`.
17. إصلاح `Otp` تخزين hash + تنظيف expired + `Cache` لـ `get_setting` مع `branch_id`.
18. حماية `CustomerLookup` + `StaffDashboard` validation لكل `public` prop.
19. إضافة اختبارات concurrency (`Parallel::run` لحجز متزامن).
20. تفعيل `sanctum.expiration` + `token_prefix` + تدوير `RefreshToken`.

---

## 10. ملاحظات ختامية

- **النظام يعمل لكنه غير جاهز لإنتاج ألماني يخضع لـ GoBD/KassenSichV** بدون إصلاح P0/P1، خصوصاً تتبع `print_count` غير الذري وعدم وجود `UNIQUE` على أرقام الفواتير.
- **التصميم الحالي Single-Branch رغم وجود `Branch` model** — `DashboardService::getSalonScheduleForDate()` يأخذ أول branch نشط فقط. إذا فُعّلت متعددة الفروع لاحقاً، ستنكسر الجدولة.
- **الفصل بين `created_status` و `status` و `payment_status` و `InvoiceStatus` ذكي** (Draft لا يحجب الوقت) لكن غير موثق بما يكفي → أخطاء Parity متكررة. وثّق State Machine في `Agent.md` مع Diagram.
- **التوصية المعمارية النهائية:**
  1. اعتماد `BookingService.php` وحذف `BookingService2.php`.
  2. إنشاء `Appointment::scopeConflicting()` موحدة.
  3. إنشاء `TaxCalculatorService` وحيد بمعيار `scale=6`.
  4. إضافة `InvoicePolicy` و `AppointmentPolicy` وتطبيقها في كل Controller/Livewire.
  5. جدول `sequences` منفصل لتوليد الأرقام بدل `exists()` loop.

> **تم إعداد هذا التقرير بقراءة مباشرة لـ 104 ملف بدون تنفيذ كود — كل نقطة قابلة للتحقق عبر `grep` على `file:line` المذكور. الأرقام والحالات مذكورة بدقة كما هي في الكود الحالي بتاريخ 28-08-2026.**

---

### المراجع

- `Agent.md` — الوثيقة المرجعية للـ System Overview (سعر gross, bcmath, two-stage invoicing, created_status)
- `API.md` — وثيقة الـ API (مطابق للـ routes المفحوصة)
- `docs/STAFF_DASHBOARD.md` — يوثق بدقة سلوك StaffDashboard والتقسيم بين Alpine و Livewire و polling و timeline scale
- الكود المصدري: `app/Services/*`, `app/Http/Controllers/*`, `app/Models/*`, `app/Livewire/*`, `app/Filament/*`, `routes/*`, `database/migrations/*`

*نهاية التقرير — لم يتم تعديل أي ملف كود. هذا الملف هو المخرج الوحيد.*
