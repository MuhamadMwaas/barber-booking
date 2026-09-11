<div lan="ar" dir="rtl">

# توثيق عملية الحجز — Booking Flow Documentation

> **ثنائي اللغة:** الشرح بالعربية — أسماء الكود والمتغيرات بالإنجليزية
> **المسار الكامل:** `routes/api.php` → `BookingController` → `BookingService` → `BookingValidationService` → `Appointment` model

---

## فهرس المحتويات

1. [نظرة عامة — Overview](#نظرة-عامة)
2. [الـ Routes المتاحة](#الـ-routes-المتاحة)
3. [عملية إنشاء الحجز — POST /bookings](#1-إنشاء-حجز-جديد)
4. [عملية جلب الحجوزات — GET /bookings](#2-جلب-حجوزات-العميل)
5. [عملية جلب حجز واحد — GET /bookings/{id}](#3-جلب-تفاصيل-حجز)
6. [عملية إلغاء الحجز — POST /bookings/{id}/cancel](#4-إلغاء-حجز)
7. [تفاصيل التحقق — BookingValidationService](#تفاصيل-التحقق)
8. [حساب الإجماليات — calculateTotals](#حساب-الإجماليات)
9. [موديل الحجز — Appointment Model](#موديل-الحجز)
10. [الـ Response النهائي — AppointmentResource](#الـ-response-النهائي)
11. [مخطط تدفق البيانات — Data Flow Diagram](#مخطط-تدفق-البيانات)
12. [مشاكل وملاحظات — Issues & Notes](#مشاكل-وملاحظات)

---

## نظرة عامة

عملية الحجز تمر عبر 4 طبقات رئيسية:

```
HTTP Request
    │
    ▼
BookingCreateRequest   ← التحقق من صحة البيانات (Validation)
    │
    ▼
BookingController      ← استقبال الطلب وإدارة الاستجابة
    │
    ▼
BookingService         ← منطق العمل الأساسي (Business Logic)
    │
    ├──► BookingValidationService  ← قواعد التحقق المعقدة
    ├──► Service / User models     ← جلب البيانات
    ├──► calculateTotals()         ← حساب الأسعار والضريبة
    └──► InvoiceService            ← إنشاء فاتورة مسودة
         │
         ▼
    Appointment::create()          ← حفظ في قاعدة البيانات
    AppointmentService::create()   ← حفظ الخدمات المرتبطة
```

---

## الـ Routes المتاحة

**الملف:** [`routes/api.php`](../routes/api.php) — السطر 123-128

```php
Route::middleware(['auth:sanctum', 'verified'])->prefix('bookings')->group(function () {
    Route::get('/',           [BookingController::class, 'index'])->name('bookings.index');
    Route::post('/',          [BookingController::class, 'store'])->name('bookings.store');
    Route::get('/{id}',       [BookingController::class, 'show'])->name('bookings.show');
    Route::post('/{id}/cancel',[BookingController::class, 'cancel'])->name('bookings.cancel');
});
```

**الـ Middleware المطبق:**
- `auth:sanctum` → يشترط وجود Bearer Token صالح
- `verified` → يشترط أن يكون البريد الإلكتروني مُتحقق منه

> ⚠️ **ملاحظة:** هذه الـ routes داخل `middleware('auth:sanctum')` الخارجية أيضاً (السطر 69)، أي أن `auth:sanctum` مكرر لكنه لا يسبب مشكلة.

---

## 1. إنشاء حجز جديد

### المسار
`POST /api/bookings`

### أ) الـ Request — BookingCreateRequest

**الملف:** [`app/Http/Requests/Api/BookingCreateRequest.php`](../app/Http/Requests/Api/BookingCreateRequest.php)

#### الحقول المطلوبة:

| الحقل | النوع | القواعد | الوصف |
|-------|-------|---------|-------|
| `services` | `array` | `required, min:1, max:10` | قائمة الخدمات المطلوبة |
| `services.*.service_id` | `integer` | `required, exists:services,id` | معرف الخدمة (يجب أن تكون موجودة في DB) |
| `services.*.provider_id` | `integer` | `required, exists:users,id` | معرف مقدم الخدمة |
| `services.*.start_time` | `string` | `required, date_format:H:i` | وقت البدء بصيغة `14:30` |
| `date` | `string` | `required, date_format:Y-m-d, after_or_equal:today` | تاريخ الحجز |
| `payment_method` | `string` | `required, in:cash,online` | طريقة الدفع |
| `notes` | `string` | `nullable, max:1000` | ملاحظات اختيارية |

#### مثال على Request Body:
```json
{
  "date": "2026-03-15",
  "payment_method": "cash",
  "notes": "أفضل الساعة الثانية",
  "services": [
    {
      "service_id": 3,
      "provider_id": 7,
      "start_time": "10:00"
    },
    {
      "service_id": 5,
      "provider_id": 7,
      "start_time": "10:30"
    }
  ]
}
```

#### عند فشل الـ Validation:
```json
{
  "success": false,
  "message": "بيانات غير صحيحة",
  "errors": {
    "date": ["لا يمكن الحجز في تاريخ سابق"],
    "services.0.service_id": ["الخدمة المحددة غير موجودة"]
  },
  "error_type": "validation_error"
}
```
الكود: `422 Unprocessable Entity`

---

### ب) BookingController::store()

**الملف:** [`app/Http/Controllers/Api/BookingController.php`](../app/Http/Controllers/Api/BookingController.php) — السطر 29-57

```php
public function store(BookingCreateRequest $request): JsonResponse
{
    $customer = request()->user();  // المستخدم المسجل دخوله
    $appointment = $this->bookingService->createBooking($customer, $request->validated());
    // ...
}
```

**تدفق الـ Controller:**
1. استخراج المستخدم من `request()->user()` (Sanctum Token)
2. تمرير البيانات المُتحقق منها `$request->validated()` إلى `BookingService::createBooking()`
3. إرجاع الـ response

**حالات الـ Response من الـ Controller:**

| الحالة | الكود | السبب |
|--------|-------|-------|
| نجاح | `201 Created` | الحجز أُنشئ بنجاح |
| `SlotUnavailableException` | `409` | الوقت محجوز — `error_type: slot_conflict` |
| `InvalidArgumentException` | `422` | خطأ في المنطق (خارج الدوام، إجازة، حد يومي، ...) |
| `Exception` عامة | `500` | خطأ في السيرفر |

> **ليش 409 منفصلة عن 422؟** الطلب كان سليماً والفتحة كانت معروضة فعلاً — شخص آخر
> سبقه إليها. التمييز يسمح للتطبيق أن يحدّث قائمة الفتحات تلقائياً بدل أن يعرض
> «بياناتك غير صحيحة» على شيء ليس خطأ العميل.

---

### ج) BookingService::createBooking()

**الملف:** [`app/Services/BookingService.php`](../app/Services/BookingService.php) — السطر 32-112

هذه الدالة هي قلب عملية الحجز. تمر بـ **6 مراحل متسلسلة:**

#### المرحلة 1: استخراج البيانات

```php
$services      = $bookingData['services'];
$date          = $bookingData['date'];
$paymentMethod = $bookingData['payment_method'];
$notes         = $bookingData['notes'] ?? null;

// بيانات العميل — يأتي من الـ request أو من حساب المستخدم
$customerName  = $bookingData['customer_name']  ?? ($customer->full_name ?? null);
$customerEmail = $bookingData['customer_email'] ?? $customer->email ?? null;
$customerPhone = $bookingData['customer_phone'] ?? $customer->phone ?? null;
```

> **ملاحظة:** `customer_name`, `customer_email`, `customer_phone` ليست موجودة في قواعد الـ Validation الحالية في `BookingCreateRequest`. هذا يعني أن هذه الحقول **لا تصل أبداً من الـ request**، وتُجلب دائماً من كائن المستخدم المسجل.

#### المرحلة 2: التحقق الأساسي

```php
$this->validationService->validateBasicData($services, $date);
```

يتحقق من:
- عدد الخدمات ≥ 1
- عدد الخدمات ≤ `max_services_per_booking` (من الإعدادات، افتراضي 10)
- التاريخ ليس في الماضي
- التاريخ ليس أبعد من `max_booking_days` يوماً (من الإعدادات، افتراضي 10)
- لا يوجد `service_id` مكرر في نفس الطلب

#### المرحلة 3: التحقق من حد الحجوزات اليومي

```php
if ($customer) {
    $this->validationService->validateDailyBookingLimit($customer, $date);
}
```

يُفعَّل فقط إذا كان المستخدم مسجل دخوله. يتحقق من عدد حجوزاته في ذلك اليوم < `max_daily_bookings` (من الإعدادات، افتراضي 10).

#### المرحلة 4: ترتيب الخدمات زمنياً

```php
$services = $this->sortServicesByStartTime($services);
```

يُرتب مصفوفة `$services` حسب `start_time` تصاعدياً باستخدام `usort` + `strcmp`.

> **مثال:** إذا أرسل المستخدم الخدمة الثانية أولاً (11:00) ثم الأولى (10:00)، تُعاد ترتيبهما تلقائياً.

#### المرحلة 5: التحقق المفصل وتحضير كل خدمة

```php
$preparedServices = $this->validateAndPrepareServices($services, $date, $customer, $customerPhone);
```

**تفاصيل `validateAndPrepareServices()`** — السطر 129-208:

تُنفذ على كل خدمة بالترتيب:

```
لكل خدمة في $services:
    1. جلب Service من DB (batch query مسبقاً)
    2. جلب User/Provider من DB (batch query مسبقاً)
    3. validateProviderOffersService()  ← تحقق من ارتباط المزود بالخدمة
    4. getEffectiveDuration()           ← حساب المدة الفعلية
    5. getEffectivePrice()              ← حساب السعر الفعلي
    6. حساب start_time و end_time لهذه الخدمة
    7. validateSequentialTiming()       ← تحقق من عدم التداخل مع الخدمة السابقة
    8. validateTimeSlotAvailability()   ← التحقق من توفر الوقت
    9. validateNoDuplicateBooking()     ← لحساب مسجل
       أو validateNoDuplicateBookingByPhone() ← لزبون بدون حساب
    10. إضافة الخدمة المُحضَّرة إلى $preparedServices[]
```

**Batch Loading للأداء:**
```php
$servicesCollection = Service::whereIn('id', $serviceIds)->get()->keyBy('id');
$providersCollection = User::whereIn('id', $providerIds)->get()->keyBy('id');
```
بدلاً من query لكل خدمة، يُحمِّل الكل مرة واحدة ثم يُفهرس بالـ id.

**getEffectiveDuration() — ملاحظة مهمة:**

```php
private function getEffectiveDuration(User $provider, Service $service): int
{
    return $service->duration_minutes; // ← الكود يرجع هنا مباشرة!

    // هذا الكود لا يُنفَّذ أبداً (dead code):
    $pivot = DB::table('provider_service')
        ->where('provider_id', $provider->id)
        ->where('service_id', $service->id)
        ->first();
    return $pivot->custom_duration ?? $service->duration_minutes;
}
```

> ⚠️ **مشكلة:** `return $service->duration_minutes` في السطر الأول يجعل باقي الدالة **dead code** لا يُنفَّذ. لن تُستخدم `custom_duration` من pivot أبداً.

**getEffectivePrice():**
```php
// 1. يجلب السعر من pivot table (custom_price) أو سعر الخدمة الأساسي
$pivot = DB::table('provider_service')
    ->where('provider_id', $provider->id)
    ->where('service_id', $service->id)
    ->where('is_active', true)
    ->first();

$effectivePrice = $pivot->custom_price ?? $service->price;

// 2. إذا كان هناك سعر مخفض وهو أقل، يستخدمه
if ($service->discount_price && $service->discount_price < $effectivePrice) {
    return (float) $service->discount_price;
}
return (float) $effectivePrice;
```

**ترتيب أولوية السعر:**
```
discount_price (إذا < effective)  ← أعلى أولوية
    ↓
custom_price من pivot
    ↓
service->price                    ← الافتراضي
```

#### المرحلة 6: حساب الإجماليات

```php
$totals = $this->calculateTotals($preparedServices);
```

انظر قسم [حساب الإجماليات](#حساب-الإجماليات) أدناه للتفاصيل الكاملة.

#### المرحلة 7: الحفظ داخل Transaction

```php
return DB::transaction(function () use (...) {
    // لا يوجد دفع أونلاين — كل الحجوزات تُدفع نقداً في المحل — لذلك:
    //  - كل حجز يُنشأ مؤكداً ويحجب وقته فوراً
    //  - ولا يُعلَّم مدفوعاً أبداً عند الإنشاء (العميل لم يصل بعد)
    $createdStatus = 1;
    $paymentStatus = PaymentStatus::PENDING;   // = 0

    // 1. إنشاء سجل الحجز الرئيسي
    $appointment = Appointment::create([...]);

    // 2. إنشاء سجل لكل خدمة
    foreach ($preparedServices as $index => $serviceData) {
        AppointmentService::create([
            'appointment_id' => $appointment->id,
            'service_id'     => $serviceData['service_id'],
            'service_name'   => $serviceData['service_name'],
            'duration_minutes' => $serviceData['duration_minutes'],
            'price'          => $serviceData['price'],
            'sequence_order' => $index + 1,  // يبدأ من 1
        ]);
    }

    // 3. إنشاء فاتورة مسودة
    $InvoiceService->createDtaftInvoiceFromAppointment(
        $appointment, 'cash', 0
    );

    return $appointment->load(['services', 'customer', 'provider', 'services_record']);
});
```

**أهمية `created_status`:**

| `payment_method` | `created_status` | `payment_status` | المعنى |
|-----------------|-----------------|-----------------|--------|
| `cash` أو `online` | `1` | `PENDING (0)` | الحجز مؤكد ويحجب وقته، والمال لم يدخل بعد |

`payment_method` صار **نية فقط** ولا يقرر أبداً إن كان الحجز حقيقياً. الدفع كله نقدي في المحل، فالحجز يُنشأ مؤكداً دائماً، و`payment_status` يبقى `PENDING` حتى يُنهي الموظف الفاتورة على الكاشير.

#### ما الذي يحدث لاحقاً عند الدفع؟ — MON-05

الدفع لا يتم من API ولا من `BookingService`. بعد تقديم الخدمة يفتح الموظف نافذة
الدفع في `StaffDashboard` ويختار `cash` أو `card`. كل شاشات الإدارة الأخرى مجرد
واجهات لنفس العملية:

```php
app(InvoiceFinalizationService::class)->finalizeAppointmentPayment(
    appointment: $appointment,
    paymentMethod: 'cash', // أو card / PaymentMethod id من Filament
    finalAmount: 80.00,    // null = السعر الكامل؛ الأقل = سعر خاص كامل
    source: 'staff_dashboard',
);
```

داخل transaction واحدة تُعاد قراءة خدمات الأب وكل المواعيد الأبناء، ويُطبّق
السعر الخاص كخصم، ثم تصدر فاتورة واحدة و`Payment` واحد، وتتحول كل المواعيد
المشمولة إلى `COMPLETED`. لا توجد دفعة جزئية في السياسة الحالية، وTSE متوقف
عمداً ولا يُستدعى Fiskaly. التفاصيل والاختبارات في
[`docs/fixes/MON-05_unified_payment_flow.md`](fixes/MON-05_unified_payment_flow.md).

> **ملاحظة تقنية:** تعريف «المزوّد مشغول» موجود بمكان واحد فقط: `Appointment::scopeBlocksProviderTime()` (`created_status = 1` و status ضمن `PENDING`/`COMPLETED`) مع `scopeOverlapping()`. طبقة التوفّر وطبقة الحجز تستعملان الاثنين معاً، فلا يمكن أن تُعرض فتحة ثم تُرفض، ولا أن تُخفى فتحة بلا سبب حقيقي (كانت هذه المشكلة `BOOK-01`).

---

### د) بيانات جدول `appointments` المُحفوظة

| العمود | القيمة | المصدر |
|--------|--------|--------|
| `number` | `APT-20260315-A1B2C3` | `generateAppointmentNumber()` |
| `customer_id` | `auth()->user()->id` | من الـ Token |
| `provider_id` | `$firstService['provider_id']` | من أول خدمة بعد الترتيب |
| `appointment_date` | `2026-03-15` | من الـ request |
| `start_time` | وقت بدء أول خدمة | من أول خدمة مرتبة |
| `end_time` | وقت انتهاء آخر خدمة | من آخر خدمة مرتبة |
| `duration_minutes` | مجموع مدد كل الخدمات | `calculateTotals()` |
| `subtotal` | السعر قبل الضريبة | `calculateTotals()` |
| `tax_amount` | مبلغ الضريبة | `calculateTotals()` |
| `total_amount` | السعر الإجمالي شامل الضريبة | `calculateTotals()` |
| `status` | `PENDING (0)` | ثابت عند الإنشاء |
| `payment_method` | `cash` أو `online` | من الـ request |
| `payment_status` | `PENDING (0)` | ثابت عند الإنشاء — المال يُسجَّل عند إنهاء الفاتورة |
| `created_status` | `1` | ثابت — لا يوجد دفع أونلاين |
| `customer_name` | اسم المستخدم | من User model |
| `customer_email` | بريد المستخدم | من User model |
| `customer_phone` | هاتف المستخدم | من User model |
| `notes` | ملاحظات | من الـ request |

**توليد رقم الحجز `generateAppointmentNumber()`:**
```php
$prefix = 'APT';
$date   = Carbon::now()->format('Ymd');           // مثال: 20260315
$random = strtoupper(substr(uniqid(), -6));        // مثال: A1B2C3

// النتيجة: APT-20260315-A1B2C3
```

> ⚠️ **مشكلة محتملة:** `uniqid()` يعتمد على الـ microsecond، وليس مضموناً 100% تفادي التكرار تحت ضغط عالٍ. لا يوجد `UNIQUE constraint` أو retry في الكود.

---

### هـ) Response عند النجاح

```json
{
  "success": true,
  "message": "Booking created successfully",
  "data": {
    "id": 42,
    "number": "APT-20260315-A1B2C3",
    "appointment_date": "2026-03-15",
    "formatted_date": "Mar 15, 2026",
    "start_time": "10:00",
    "end_time": "11:00",
    "time_range": "10:00 AM - 11:00 AM",
    "duration_minutes": 60,
    "formatted_duration": "1h",
    "subtotal": 42.02,
    "tax_amount": 7.98,
    "total_amount": 50.00,
    "status": "PENDING",
    "status_value": 0,
    "status_label": "Pending",
    "payment_status": "PAID_ONSTIE_CASH",
    "payment_status_value": 2,
    "payment_status_label": "Paid On site Cash",
    "payment_method": "cash",
    "cancellation_reason": null,
    "cancelled_at": null,
    "provider": {
      "id": 7,
      "full_name": "أحمد محمد",
      "email": "ahmed@example.com",
      "phone": "+491234567890",
      "avatar_url": "https://..."
    },
    "services_details": [
      {
        "id": 1,
        "service_id": 3,
        "service_name": "قص شعر",
        "duration_minutes": 30,
        "formatted_duration": "30m",
        "price": 25.00,
        "formatted_price": "25.00",
        "sequence_order": 1
      }
    ],
    "notes": "أفضل الساعة الثانية",
    "created_at": "2026-02-28 10:00:00",
    "updated_at": "2026-02-28 10:00:00",
    "is_upcoming": true,
    "is_past": false,
    "is_cancelled": false,
    "is_completed": false,
    "can_cancel": true
  }
}
```

---

## 2. جلب حجوزات العميل

### المسار
`GET /api/bookings?status=0`

### BookingController::index()

**الملف:** [`app/Http/Controllers/Api/BookingController.php`](../app/Http/Controllers/Api/BookingController.php) — السطر 65-87

```php
$customer = auth()->user();
$status   = $request->query('status');  // اختياري
$bookings = $this->bookingService->getCustomerBookings($customer, $status);
```

### BookingService::getCustomerBookings()

```php
$query = Appointment::where('customer_id', $customer->id)
    ->with(['services', 'provider', 'services_record'])
    ->orderBy('appointment_date', 'desc')
    ->orderBy('start_time', 'desc');

if ($status) {
    $query->where('status', $status);
}

return $query->get();
```

**الفلترة بالحالة:**

| قيمة `status` | الحالة |
|---------------|--------|
| `0` | `PENDING` — قيد الانتظار |
| `1` | `COMPLETED` — مكتمل |
| `-1` | `USER_CANCELLED` — ألغاه العميل |
| `-2` | `ADMIN_CANCELLED` — ألغاه الإدارة |
| `-3` | `NO_SHOW` — لم يحضر |
| (بدون) | كل الحجوزات |

**Response:** مصفوفة من `AppointmentResource` مُغلفة في `collection`.

---

## 3. جلب تفاصيل حجز

### المسار
`GET /api/bookings/{id}`

### BookingController::show()

**الملف:** السطر 95-122

```php
$customer    = auth()->user();
$appointment = $this->bookingService->getBookingDetails($id, $customer);
```

### BookingService::getBookingDetails()

```php
$appointment = Appointment::with(['services', 'provider', 'customer', 'services_record'])
    ->findOrFail($appointmentId);  // 404 إذا لم يوجد

// التحقق من ملكية الحجز
if ($appointment->customer_id !== $customer->id) {
    throw new InvalidArgumentException('Unauthorized access to this appointment');
    // → 403 Forbidden
}
return $appointment;
```

**حالات الـ Response:**

| الحالة | الكود | السبب |
|--------|-------|-------|
| نجاح | `200` | الحجز موجود والمستخدم يملكه |
| `ModelNotFoundException` | `500` | `findOrFail` يرمي استثناء لا تُعالجه الدالة كـ 404 |
| `InvalidArgumentException` | `403` | المستخدم لا يملك هذا الحجز |

> ⚠️ **مشكلة:** `findOrFail()` يرمي `ModelNotFoundException` عند عدم وجود السجل، لكن الـ `catch` في الـ Controller يلتقطها كـ `\Exception` ويرجع `500` بدلاً من `404`.

---

## 4. إلغاء حجز

### المسار
`POST /api/bookings/{id}/cancel`

### Request Body (اختياري):
```json
{
  "cancellation_reason": "تغيير في الخطط"
}
```

### BookingController::cancel()

**الملف:** السطر 131-161

```php
$customer    = auth()->user();
$appointment = $this->bookingService->getBookingDetails($id, $customer);
$reason      = $request->input('cancellation_reason');
$this->bookingService->cancelBooking($appointment, $reason);
```

### BookingService::cancelBooking()

```php
public function cancelBooking(Appointment $appointment, ?string $reason = null): bool
{
    // يسمح بالإلغاء فقط إذا كان الحجز PENDING
    if (!in_array($appointment->status, [AppointmentStatus::PENDING])) {
        throw new InvalidArgumentException('Only pending appointments can be cancelled');
    }

    return $appointment->cancel($reason);
}
```

> **لا يوجد حاجز «فات الأوان»** بأي من المسارين. `/api/appointments/{id}/cancel`
> كان يرفض بعد بدء الموعد بينما `/api/bookings/{id}/cancel` يسمح — فكان العميل
> يختار السياسة باختيار الـ endpoint (`BOOK-08`). وُحِّدا على السماح: من لن يحضر
> يجب أن يستطيع قول ذلك، والإلغاء المتأخر يحرّر الكرسي ويترك سجلاً صادقاً بدل موعد
> يتحول بصمت إلى `NO_SHOW`. الردع صار بالمراقبة (أدناه)، و`cancellation_hours`
> يبقى معطلاً عمداً.

### Appointment::cancel()

```php
public function cancel(?string $reason = null): bool
{
    return $this->update([
        'status'              => AppointmentStatus::USER_CANCELLED,  // = -1
        'cancellation_reason' => $reason,
        'cancelled_at'        => now(),
    ]);
}
```

**حالات الـ Response:**

| الحالة | الكود | السبب |
|--------|-------|-------|
| نجاح | `200` | الحجز أُلغي |
| حجز غير `PENDING` | `422` | محاولة إلغاء حجز مكتمل أو مُلغى |
| ليس حجز المستخدم | `403` | لا يملك الحجز |

---

## تفاصيل التحقق

**الملف:** [`app/Services/BookingValidationService.php`](../app/Services/BookingValidationService.php)

### 1. validateBasicData()

```
عدد الخدمات >= 1                        (InvalidArgumentException)
عدد الخدمات <= max_services_per_booking  (من DB settings)
التاريخ >= اليوم                         (لا حجز في الماضي)
التاريخ <= اليوم + max_booking_days       (من DB settings)
service_id فريد في نفس الطلب             (لا خدمات مكررة)
```

### 2. validateProviderOffersService()

```
provider_service pivot موجود مع is_active = true
provider->is_active = true
service->is_active  = true
```

### 3. validateSequentialTiming()

```
start_time للخدمة الحالية >= end_time للخدمة السابقة
```
> الخدمات يجب أن تكون متتالية ولا تتداخل مع بعضها.

### 4. validateTimeSlotAvailability() — الأهم

تتحقق من **7 شروط بالترتيب:**

```
1. المزود لديه جدول عمل في ذلك اليوم من الأسبوع
   (provider_scheduled_works: day_of_week, is_work_day=true, is_active=true)

2. الوقت ضمن ساعات العمل
   (start_time >= work_start AND end_time <= work_end)

3. لا يوجد إجازة يوم كامل
   (type=FULL_DAY + ProviderTimeOff::scopeCoveringDate)

4. لا يوجد إجازة بالساعة تتعارض
   (type=HOURLY + scopeCoveringDate ثم blocksWindow)

5. لا يوجد حجز مُؤكد (created_status=1) يتعارض
   (appointments: provider_id, status IN [PENDING,COMPLETED], time overlap)

6. الوقت ليس في الماضي
   (start_time > Carbon::now())

7. الوقت بعد مهلة الحجز المسبق
   (start_time > now() + book_buffer دقيقة)
```

**استعلام التعارض الزمني** (Overlap Detection):
```php
// يتعارض إذا: start < newEnd AND end > newStart
->where('start_time', '<', $endTime)
->where('end_time',   '>', $startTime)
```

### 5. assertCustomerIsFree() — العميل لا يكون في مكانين

```
هل للعميل موعد آخر يتداخل زمنياً مع [start, end)؟
    - عند أي مزوّد، وبأي خدمة — المزوّد والخدمة لا يدخلان في القاعدة
    - الحالات المانعة: PENDING + COMPLETED (نفس قاعدة المزوّد)
    - التداخل نصف مفتوح: 10:00–11:00 ثم 11:00 مسموح
```

**هوية العميل:**

| الشكل | المطابقة |
|---|---|
| مسجّل | `customer_id` **أو** مفتاح هاتفه |
| ضيف | مفتاح الهاتف فقط |
| ضيف بلا هاتف | لا مطابقة (لا يُربط بأحد) |

مفتاح الهاتف = آخر 9 خانات بعد تجريد كل ما ليس رقماً (`App\Support\PhoneNumber`)،
فـ `+49 176 1234567` و`0176 1234567` شخص واحد. المقارنة تتم في PHP لأن تطبيع
الهاتف في SQL غير محمول بين MySQL وSQLite، والمرشحون محصورون بالمواعيد المتداخلة
مع هذه النافذة تحديداً فعددهم ضئيل.

**التجاوز:** `allow_customer_overlap` — يُرفع خادمياً بعد فحص صلاحية `force_booking`
فقط. منفصل عمداً عن `bypass_availability`. سببه أن بعض عمل الصالون متوازٍ فعلاً
(مانيكير أثناء تفاعل الصبغة).

> **استبدل** `validateNoDuplicateBooking()` و`validateNoDuplicateBookingByPhone()`
> اللتين كانتا تسألان: «نفس لحظة البداية بالضبط + خدمة مشتركة؟» — فكانتا تسمحان
> بحجز متزامن عند مزوّد آخر بخدمة مختلفة، وبأي تداخل جزئي (`BOOK-06`).

---

## حساب الإجماليات

**الملف:** [`app/Services/BookingService.php`](../app/Services/BookingService.php) — السطر 213-290

`calculateTotals()` **لا تحتوي حساباً خاصاً بها** — تستدعي
`TaxCalculatorService::calculateBulk()`، وهو **التنفيذ الوحيد لحساب الضريبة في
المشروع كله**.

### الخوارزمية:

```
tax_rate = get_setting('tax_rate', '0')     // مثال: "19" أو "19.5"

TaxCalculatorService::calculateBulk(services, precision = 2)
    │
    │  الدقة الداخلية = max(10, precision + 8)   ← أوسع من الناتج دائماً
    │
    └── لكل خدمة:
            factor = 1 + (tax_rate / 100)          // بالدقة الداخلية
            net    = gross / factor                 // بالدقة الداخلية
            tax    = gross - net                    // بالدقة الداخلية
            تقريب net و tax إلى منزلتين  ← التقريب هنا فقط، مرة واحدة

        جمع البنود، ثم تسوية أخيرة:
            diff = grossTotal - (netTotal + taxTotal)
            إذا diff != 0.00:  taxTotal += diff     // الضريبة دائماً، لا الصافي
```

### ⚠️ لماذا الدقة الداخلية واسعة؟ (`MON-01`)

`bcdiv` في PHP **تقتطع ولا تقرّب**. القسمة بدقة الناتج المطلوب تفقد الخانة التي
كان يجب تقريبها، وتقريبُها بعد ذلك بلا فائدة:

```
50.00 ÷ 1.19 = 42.016806722689...

bcdiv(..., 2)  →  "42.01"    ← اقتطاع  ⇒ الضريبة 7.99  (خطأ)
التقريب الصحيح →  42.02      ⇒ الضريبة 7.98  (صحيح)
```

كانت `TaxCalculatorService` تقسم بدقة 2 بينما `BookingService` بدقة 6، فتنتج
**معاملة واحدة ضريبتين مختلفتين**: صف `appointments` يقول 7.98 وصف `invoices`
يقول 7.99 لنفس الـ 50 يورو — وإيميل التأكيد والإيصال المطبوع يعرضان الرقمين
على الزبون. وُحِّدت الطبقات كلها 2026-09-10؛ التفاصيل في
[`docs/fixes/MON-01_vat_calculation_unified.md`](fixes/MON-01_vat_calculation_unified.md).

### مثال عملي (tax_rate = 19%):

| الخدمة | gross (السعر) | net (قبل ضريبة) | tax (الضريبة) |
|--------|---------------|-----------------|---------------|
| قص شعر | 50.00 | 42.02 | 7.98 |
| لحية | 25.00 | 21.01 | 3.99 |
| **المجموع** | **75.00** | **63.03** | **11.97** |

**ملاحظة:** `calculateTotalsInverse()` و`bcRound()` الخاصتان بـ `BookingService`
**حُذفتا** — الأولى كانت كوداً ميتاً يحسب الضريبة بالاتجاه الخطأ، والثانية لم
تكن تُستدعى إلا من `calculateTotals` التي صارت تفوّض للحاسبة.

---

## موديل الحجز

**الملف:** [`app/Models/Appointment.php`](../app/Models/Appointment.php)

### العلاقات:

```php
customer()        → BelongsTo(User, 'customer_id')   // العميل
provider()        → BelongsTo(User, 'provider_id')   // مقدم الخدمة
services()        → BelongsToMany(Service, 'appointment_services')
                      withPivot(['service_name', 'duration_minutes', 'price', 'sequence_order'])
services_record() → HasMany(AppointmentService)       // نفس البيانات لكن كـ Model مستقل
invoice()         → HasOne(Invoice)
payments()        → MorphMany(Payment)
reminders()       → HasMany(AppointmentReminder)
```

> **لماذا علاقتان للخدمات؟**
> - `services()` → BelongsToMany: للوصول السريع `$appointment->services` + بيانات pivot
> - `services_record()` → HasMany: للوصول للـ Model الكامل `AppointmentService` مع accessors مثل `formatted_duration`, `formatted_price`

### Boot Events:

```php
static::creating → إذا لم يكن هناك number، يولده تلقائياً
static::deleting → يلغي كل التذكيرات المرتبطة بالحجز
```

### الـ Accessors المتاحة:

| Accessor | الوصف | المثال |
|----------|-------|--------|
| `customer_name` | اسم العميل أو "Guest" | "أحمد محمد" |
| `customer_email` | بريد من حساب أو من الحجز | "ahmed@..." |
| `customer_phone` | هاتف من حساب أو من الحجز | "+49..." |
| `status_label` | اسم الحالة | "Pending" |
| `payment_status_label` | اسم حالة الدفع | "Paid On site Cash" |
| `formatted_date` | تاريخ منسق | "Mar 15, 2026" |
| `time_range` | نطاق الوقت | "10:00 AM - 11:00 AM" |
| `formatted_duration` | المدة منسقة | "1h 30m" |
| `has_customer_account` | هل للعميل حساب | `true / false` |

### القيم المتاحة لـ AppointmentStatus:

| الاسم | القيمة | الوصف |
|-------|--------|-------|
| `PENDING` | `0` | قيد الانتظار |
| `COMPLETED` | `1` | مكتمل |
| `USER_CANCELLED` | `-1` | ألغاه العميل |
| `ADMIN_CANCELLED` | `-2` | ألغاه الإدارة |
| `NO_SHOW` | `-3` | لم يحضر |

### القيم المتاحة لـ PaymentStatus:

| الاسم | القيمة | الوصف |
|-------|--------|-------|
| `PENDING` | `0` | لم يُدفع |
| `PAID_ONLINE` | `1` | دُفع أونلاين |
| `PAID_ONSTIE_CASH` | `2` | دُفع نقداً في الموقع |
| `PAID_ONSTIE_CARD` | `3` | دُفع ببطاقة في الموقع |
| `FAILED` | `4` | فشل الدفع |
| `REFUNDED` | `5` | استُرجع |
| `PARTIALLY_REFUNDED` | `6` | استُرجع جزئياً |

---

## الـ Response النهائي

**الملف:** [`app/Http/Resources/AppointmentResource.php`](../app/Http/Resources/AppointmentResource.php)

| الحقل | المصدر | الوصف |
|-------|--------|-------|
| `id` | `$this->id` | معرف الحجز |
| `number` | `$this->number` | رقم الحجز المرجعي |
| `appointment_date` | format `Y-m-d` | التاريخ |
| `formatted_date` | Accessor | تاريخ منسق للعرض |
| `start_time / end_time` | format `H:i` | أوقات البدء والانتهاء |
| `time_range` | Accessor | نطاق الوقت كنص |
| `duration_minutes` | الدقيق العددي | المدة بالدقائق |
| `formatted_duration` | Accessor | المدة كنص |
| `subtotal / tax_amount / total_amount` | cast `float` | المبالغ المالية |
| `status` | `->name` (اسم الـ Enum) | "PENDING" |
| `status_value` | `->value` (رقم الـ Enum) | `0` |
| `status_label` | Accessor | "Pending" |
| `payment_status / payment_status_value / payment_status_label` | مثل status | بيانات حالة الدفع |
| `payment_method` | النص كما خُزِّن | "cash" أو "online" |
| `provider` | علاقة محملة | بيانات المزود |
| `services_details` | `services_record` relation | تفاصيل الخدمات |
| `is_upcoming` | `start_time > now()` | هل قادم |
| `is_past` | `start_time < now()` | هل مضى |
| `is_cancelled` | `status.value in [-1, -2]` | هل مُلغى |
| `is_completed` | `status.value === 1` | هل مكتمل |
| `can_cancel` | `status=0 AND start_time > now()` | هل يمكن إلغاؤه |

**ملاحظة:** حقل `customer` موجود في الكود لكن مُعلَّق (commented out) ولا يُرسل في الـ response.

---

## مخطط تدفق البيانات

```
POST /api/bookings
        │
        ▼
[Middleware: auth:sanctum + verified]
        │ ← 401 إذا لم يكن مسجلاً أو غير مُتحقق
        ▼
BookingCreateRequest::rules()
        │ ← 422 مع errors إذا فشل الـ validation
        ▼
BookingController::store()
        │
        ▼
BookingService::createBooking($customer, $data)
        │
        ├─ validateBasicData()          ← شكل الطلب فقط، خارج المعاملة
        │       │ ← InvalidArgumentException → 422
        │
        ├─ sortServicesByStartTime()
        │
        └─ DB::transaction()  ◄══ كل ما يعتمد على حالة مشتركة صار هنا
                │
                ├─ BookingLockService::lockUsers([المزوّدون…, العميل])
                │       └─ SELECT … FOR UPDATE مرتّب بالـ id (لا deadlock)
                │
                ├─ validateDailyBookingLimit()   ← يُعاد عدّه تحت القفل
                │       │ ← InvalidArgumentException → 422
                │
                ├─ validateAndPrepareServices()
                │       │
                │       ├─ [لكل خدمة]:
                │       │   ├─ validateProviderOffersService()    ← 422
                │       │   ├─ getEffectiveDuration()
                │       │   ├─ getEffectivePrice()
                │       │   ├─ validateSequentialTiming()         ← 422
                │       │   ├─ validateTimeSlotAvailability()     ← 422 (نافذة الدوام/الإجازة)
                │       │   │      └─ assertNoConflictingAppointment() ← 409 (الوقت محجوز)
                │       │   └─ validateNoDuplicateBooking()       ← 422
                │
                ├─ calculateTotals()
                │
                ├─ Appointment::create()
                ├─ AppointmentService::create() × N
                ├─ InvoiceService::createDraftInvoice()
                └─ return $appointment->load([...])

أي رفض داخل المعاملة يعني ROLLBACK كامل: لا موعد، ولا خدمات، ولا فاتورة مسودة.
                        │
                        ▼
              AppointmentResource::toArray()
                        │
                        ▼
              Response 201 Created
```

---

## مشاكل وملاحظات

### 🔴 مشاكل موجودة (Bugs)

#### 1. Dead Code في getEffectiveDuration()
**الملف:** [`BookingService.php:344`](../app/Services/BookingService.php#L344)

```php
private function getEffectiveDuration(User $provider, Service $service): int
{
    return $service->duration_minutes; // ← return مبكر!

    // هذا الكود لن يُنفَّذ أبداً:
    $pivot = DB::table('provider_service')->...->first();
    return $pivot->custom_duration ?? $service->duration_minutes;
}
```
**النتيجة:** `custom_duration` من جدول `provider_service` لا تُستخدم أبداً في تحديد المدة.

---

#### 2. findOrFail يرجع 500 بدلاً من 404
**الملف:** [`BookingController.php:99`](../app/Http/Controllers/Api/BookingController.php#L99)

```php
// في BookingService::getBookingDetails():
$appointment = Appointment::with([...])->findOrFail($appointmentId);
// يرمي ModelNotFoundException

// في BookingController::show():
} catch (\Exception $e) {
    return response()->json([...], 500);  // ← يُعالَج كـ 500!
}
```
**النتيجة:** إذا لم يوجد الحجز، يُرجع `500 Server Error` بدلاً من `404 Not Found`.

---

#### 3. customer_name/email/phone لا تصل من الـ request
**الملف:** [`BookingCreateRequest.php`](../app/Http/Requests/Api/BookingCreateRequest.php)

```php
// في BookingService::createBooking():
$customerName = $bookingData['customer_name'] ?? ($customer->full_name ?? null);
```
لكن `customer_name` ليست في `rules()` في `BookingCreateRequest`، ولأن `$request->validated()` يُرجع فقط الحقول المُعرَّفة في `rules()`، فهذه الحقول لن تكون موجودة في `$bookingData` **أبداً**.

---

#### 4. ~~رقم الحجز قد يتكرر~~ — ✅ مُصلَحة

`generateAppointmentNumber()` صار يستخدم `random_bytes(3)` داخل حلقة
`do…while (exists())`، و`appointments.number` صار يحمل قيداً فريداً
(`appointments_number_unique`) — فالحماية بطبقتين: الحلقة تمنع التكرار،
والقيد يمنع كل ما تفوّته الحلقة تحت التزامن.

وأرقام الفواتير والمدفوعات لم تعد عشوائية أصلاً: صارت متسلسلة من صفّ عدّاد
مقفول (`INV-2026-000001` / `PAY-2026-000001`). التفاصيل في
[`docs/fixes/MON-03_document_numbering.md`](fixes/MON-03_document_numbering.md)
(`MON-03` / `DB-01`).

---

#### 5. ~~حجوزات online بـ created_status=0 لا تُنظَّف~~ — ✅ مُصلَحة

لم يعد أي مسار ينتج `created_status = 0`: كل حجز يُنشأ بـ `1`، وقيمة العمود الافتراضية في قاعدة البيانات صارت `1` كذلك. وطبقتا التوفّر والحجز صارتا تقرآن الشرط نفسه من `Appointment::scopeBlocksProviderTime()`، فلا حجوزات ميتة تحجب وقتاً ولا TODO معلّق.

---

#### 6. ~~calculateTotalsInverse() غير مُستخدمة~~ — ✅ مُصلَحة

حُذفت مع `bcRound()` الخاصة بـ `BookingService`، وصارت `calculateTotals()` تفوّض
لـ `TaxCalculatorService` (`MON-01`، 2026-09-10). وحُذف معها ملف
`BookingService2.php` كاملاً (428 سطراً، صفر مستدعين، وفيه نسخة ثالثة من الضريبة
بالاتجاه الخطأ).

---

### 🟡 ملاحظات تصميمية

#### 7. قيمة `cash` عند إنشاء المسودة لا تعني أن الدفع حصل
```php
$InvoiceService->createDtaftInvoiceFromAppointment(
    $appointment, 'cash', 0
);
```

هذه معاملات تاريخية في API إنشاء المسودة ولا تصدر فاتورة مدفوعة ولا تنشئ
`Payment`. طريقة التحصيل الفعلية لا تُحسم إلا لاحقاً بواسطة
`InvoiceFinalizationService::finalizeAppointmentPayment()`، والتي تربط صف
`PaymentMethod` الفعلي وتوحّد `appointments.payment_method` على `cash`/`card`.

#### 8. provider_id يُؤخذ من أول خدمة فقط
```php
'provider_id' => $firstService['provider_id'],
```
إذا كانت الخدمات تخص مزودين مختلفين، يُسجَّل فقط مزود الخدمة الأولى في جدول `appointments`. بيانات بقية المزودين موجودة فقط في `appointment_services`.

#### 9. الـ request يقبل خدمات لمزودين مختلفين
لا يوجد تحقق يمنع إرسال خدمات لـ `provider_id` مختلفة في نفس الطلب، مما قد يسبب تعقيداً في الجدولة.

---

---

## المعرّفات المفقودة والحذف الناعم (BOOK-09 — مُصلَحة 2026-09-07)

### الفخ

`exists:services,id` ينفّذ استعلاماً **خاماً** على الجدول، فلا يعرف شيئاً عن
`SoftDeletes` ويطابق الصفوف المحذوفة. ثم يحمّل `BookingService` نفس المعرّف عبر
Eloquent فيُخفيه الـ global scope ويعود `null` — والتوقيع الصارم يرمي `TypeError`.

**حذف خدمة واحدة من اللوحة كان كافياً لإرجاع 500 على كل حجز عليها.**

### الحراسة بطبقتين

| الطبقة | ماذا تفعل |
|---|---|
| `BookingCreateRequest` | `whereNull('deleted_at')` في قاعدتَي الخدمة والمزوّد → `422` |
| `validateProviderOffersService()` | يقبل `?User` و`?Service` ويرفض `null` كأي زوج غير متاح |

الطبقة الثانية هي الأهم: **StaffDashboard يبني الحمولة يدوياً ويستدعي
`BookingService` مباشرة** بلا Form Request، فقواعد التحقق لا تمرّ عليه أصلاً.

### `TypeError` ليس `Exception`

`TypeError` يرث `Error`، وهما شقيقان تحت `Throwable`. فـ `catch (\Exception)` كان
يفوّته: في الـ API يهرب إلى معالج Laravel، وفي الداشبورد **يُسقط مكوّن Livewire**.
نقاط دخول الحجز صارت تلتقط `\Throwable` وتسجّل `$e::class`.

### ⚠️ قاعدة يجب عدم كسرها: لا تمييز بين «غير موجود» و«غير متاح»

`rejectProviderServicePair()` تُرجع **رسالة واحدة** لكل أسباب الرفض (غير موجود /
معطّل / الزوج غير مرتبط) والسبب الحقيقي يذهب إلى `Log::notice` فقط. الغرض منع
تعداد معرّفات الخدمات والمستخدمين. أي مسار رفض جديد يجب أن يمرّ من هنا.
---

## مراقبة الإلغاء المتكرر (BOOK-08 — مُصلَحة 2026-09-07)

### القاعدة

من الإلغاء **الثاني** خلال **7 أيام متحركة** — ومع كل إلغاء بعده — يصل إشعار
Filament لكل مستخدم نشط بدور `admin` أو `manager`.

| البند | القيمة |
|---|---|
| ما يُعدّ | `USER_CANCELLED` فقط |
| عمود النافذة | `cancelled_at` (لحظة انسحاب العميل، لا تاريخ الموعد) |
| العتبة | 2 فأكثر |
| المستقبِلون | `admin` + `manager` النشطون |

`ADMIN_CANCELLED` لا يُحسب: إلغاء الإدارة (حلاق مريض مثلاً) ليس ذنب الزبون،
وحسابه عليه ينتج إشعارات لا يستطيع أحد التصرف بناءً عليها.

### المسار

```text
العميل يلغي
  -> Appointment::cancel()   ← المكان الوحيد الذي يكتب USER_CANCELLED
  -> CancellationMonitor::recordCustomerCancellation()
       -> عدّ آخر 7 أيام
       -> >= 2 ? إشعار Filament لكل admin/manager : صمت
```

`Appointment::cancel()` لها مستدعيان فقط وكلاهما مسار عميل، بينما إلغاء الموظف
يكتب `ADMIN_CANCELLED` مباشرة ولا يمرّ بها — لذلك هي نقطة الوصل الطبيعية.

### ضمانات

- الإشعار **لا يمنع ولا يُلغي** شيئاً — معلومة للمدير فقط.
- فشل الإشعار **لا يُبطل الإلغاء**: `CancellationMonitor` يبتلع أخطاءه ويسجّلها
  بـ `Log::warning` (وهذا ما كشف أن اسم صنف الـ Action كان خاطئاً في Filament 4).

---

## الإجازات: نطاق التواريخ ودلالة الامتداد (BOOK-04 — مُصلَحة 2026-09-07)

### المصدر الوحيد

| الدالة | تجيب عن |
|---|---|
| `ProviderTimeOff::scopeCoveringDate($date)` | هل تنطبق هذه الإجازة على ذلك اليوم؟ |
| `ProviderTimeOff::blockedWindowOn($date)` | أي شريحة من ذلك اليوم تشغلها؟ |
| `ProviderTimeOff::blocksWindow($start, $end)` | هل تتعارض مع هذه الفترة؟ (نصف مفتوحة) |

طبقة التوفّر وطبقة الحجز تستدعيان الثلاثة، فلا يمكن أن تختلفا.

### `end_date = NULL` تعني يوم واحد

`COALESCE(end_date, start_date)` في الطبقتين. الشرط القديم `end_date >= :date`
كان **يُسقط** هذه الصفوف بصمت، لأن `NULL >= '2026-09-10'` في SQL تساوي `UNKNOWN`
لا `FALSE`، و`WHERE` لا يُرجع الصف.

### الإجازة الساعية الممتدة = غياب متصل واحد

`10/9 12:00 → 15/9 13:00` تعني غياباً متواصلاً، وليس نافذة تتكرر كل صباح:

| اليوم | المحجوب |
|---|---|
| البداية | من `start_time` حتى نهاية اليوم |
| الأوسط | اليوم بأكمله |
| النهاية | من بداية اليوم حتى `end_time` |
| يوم واحد | من `start_time` حتى `end_time` |

### تحقق الإدخال

`StaffDashboard::timeOffValidationError()` يُطبَّق على مساري الحفظ:
`end_date >= start_date`، وللساعية `start_time`/`end_time` مطلوبان و
`end_time > start_time`. الأوقات المعكوسة تُرفض ولا تُفسَّر كعبور لمنتصف الليل —
الصالون لا يعمل بعد منتصف الليل، فـ `22:00 → 02:00` خطأ إدخال على الأرجح.

---

## التزامن ومنع الحجز المزدوج (BOOK-02 — مُصلَحة 2026-09-07)

### المشكلة التي كانت

`SELECT` ناجح **لا يحجز شيئاً**. كان فحص التعارض يقع خارج `DB::transaction()`،
فطلبان متزامنان يقرآن «الفتحة فاضية» ثم يكتب كلاهما.

### القاعدة الآن

| العنصر | التفصيل |
|---|---|
| القفل | `BookingLockService::lockUsers()` → `SELECT … FOR UPDATE` على `users` |
| لماذا `users` وليس `appointments`؟ | الحالة المحمية هي **غياب** موعد، ولا يمكن قفل صفوف غير موجودة. صف المزوّد موجود دائماً. |
| منع الـ deadlock | المزوّدون والعميل كلهم صفوف في `users` → قفل واحد مرتّب تصاعدياً بالـ `id` |
| الفحص | `BookingValidationService::assertNoConflictingAppointment()` بعد القفل، داخل المعاملة |
| الفهرس | `appointments_conflict_lookup_idx` على `(provider_id, appointment_date, created_status, status)` |
| رد الخسارة | `409` + `error_type: slot_conflict` |

### المسارات الثلاثة التي تكتب على تقويم المزوّد

| المسار | قفل | فحص تعارض |
|---|---|---|
| `BookingService::createBooking()` | ✅ | ✅ |
| `BookingService::addServiceToBooking()` | ✅ | ✅ يعيد التأكد من نافذة التحليل |
| `StaffDashboard::updateAppointment()` | ✅ | ✅ (يستثني الموعد نفسه؛ `force_booking` يتجاوز) |

### ⚠️ العقد الذي يجب احترامه

أي كود جديد يكتب `provider_id` / `appointment_date` / `start_time` / `end_time`:

1. داخل `DB::transaction()`
2. `lockUsers()` **أولاً**
3. `assertNoConflictingAppointment()` **بعد** القفل

الفحص خارج المعاملة مسموح **كرفض سريع فقط**، وليس كضمان. الضمان تطبيقي وليس على
مستوى قاعدة البيانات — مسار كتابة ينسى القفل يستطيع اختراقه.

---

### 🟢 نقاط قوة في التصميم

- ✅ استخدام `bcmath` لحسابات دقيقة للمبالغ المالية
- ✅ Batch loading للـ models (`whereIn`) تجنباً لمشكلة N+1
- ✅ التحقق من التعارض الزمني بمعادلة رياضية دقيقة (interval overlap)
- ✅ دعم الزبائن بدون حساب (guest booking) عبر `customer_phone`
- ✅ فصل منطق التحقق في `BookingValidationService` منفصل
- ✅ استخدام `DB::transaction()` لضمان atomicity
- ✅ تقريب الضريبة مع تصحيح فرق الـ rounding
- ✅ دعم خدمات متعددة في نفس الحجز مع ترتيب تلقائي
