# 06 — تدفق الحجز بعمق — Booking Flow Deep Dive

> **الملفات:** `app/Services/BookingService.php:42`، `app/Services/BookingValidationService.php:1`، `docs/BOOKING_FLOW.md:1` (المرجع القديم المفصل)

---

## 1. المخطط التسلسلي الكامل

```
POST /api/bookings
  │
  ▼
[Middleware: auth:sanctum + verified.customer]  ← 401/403 إن فشل
  │
  ▼
BookingCreateRequest::rules()                  ← 422 إن فشل
  │  date: required|date_format:Y-m-d|after_or_equal:today
  │  services: required|array|min:1|max:10
  │  services.*.service_id: required|exists:services,id + whereNull(deleted_at)
  │  services.*.provider_id: required|exists:users,id + whereNull(deleted_at)
  │  services.*.start_time: required|date_format:H:i
  │  payment_method: required|in:cash,online
  ▼
BookingController::store()                     ← app/Http/Controllers/Api/BookingController.php:29
  │  $customer = request()->user()
  │  $appointment = bookingService->createBooking($customer, $validated)
  │  try/catch:
  │    SlotUnavailableException → 409 {error_type: slot_conflict}
  │    InvalidArgumentException → 422
  │    Throwable → 500
  ▼
BookingService::createBooking()                ← app/Services/BookingService.php:42
  │
  ├─ validateBasicData(services, date)         ← خارج transaction (شكل الطلب فقط)
  │    ├─ 1 ≤ count ≤ max_services_per_booking
  │    ├─ date ≥ today && date ≤ today+max_booking_days
  │    └─ لا تكرار service_id
  │
  ├─ sortServicesByStartTime(services)         ← usort strcmp
  │
  └─ DB::transaction()  ◄══ كل ما يعتمد على حالة مشتركة هنا
       │
       ├─ BookingLockService::lockUsers([providerIds, customerId])
       │    └─ SELECT ... FOR UPDATE ORDER BY id (لا deadlock)
       │
       ├─ validateDailyBookingLimit(customer, date)  ← تحت القفل
       │    └─ count < max_daily_bookings
       │
       ├─ validateAndPrepareServices(services, date, customer, phone, flags)
       │    │
       │    └─ لكل serviceData:
       │         ├─ validateProviderOffersService(provider, service) ← 422
       │         │    └─ pivot is_active && provider.is_active && service.is_active
       │         │    └─ يرفض null (soft-deleted) — BOOK-09
       │         │
       │         ├─ getEffectiveDuration() → service.duration_minutes
       │         ├─ getEffectivePrice() → custom_price → discount_price → price
       │         │
       │         ├─ startTime = parse(date + start_time)
       │         │  endTime = startTime + duration
       │         │
       │         ├─ validateSequentialTiming(prevEnd, start) ← 422
       │         │    └─ start ≥ prevEnd (لا تداخل بين خدمات نفس الحجز)
       │         │
       │         ├─ validateTimeSlotAvailability(provider, service, start, end, flags)
       │         │    ├─ جدول عمل في day_of_week && is_work_day
       │         │    ├─ ضمن ساعات العمل (start ≥ work_start && end ≤ work_end)
       │         │    ├─ لا إجازة يوم كامل (ProviderTimeOff::coveringDate + FULL_DAY)
       │         │    ├─ لا إجازة ساعية تحجب (coveringDate + blocksWindow)
       │         │    ├─ لا تعارض مواعيد (assertNoConflictingAppointment → 409)
       │         │    │    └─ Appointment::blocksProviderTime()->overlapping(start,end)
       │         │    ├─ ليس في الماضي (start > now())
       │         │    └─ بعد book_buffer (start > now()+buffer)
       │         │
       │         └─ assertCustomerIsFree(customer, phone, start, end) ← 422
       │              └─ لا موعد آخر للعميل يتداخل [start,end) عند أي مزود
       │              └─ مطابقة بالـ id أو PhoneNumber::key() (آخر 9 أرقام)
       │
       ├─ calculateTotals(preparedServices) → TaxCalculatorService::calculateBulk
       │
       ├─ Appointment::create({
       │      number: APT-YYYYMMDD-XXXXXX,
       │      customer_id, provider_id (من أول خدمة),
       │      appointment_date: date,
       │      start_time: أول start, end_time: آخر end,
       │      duration_minutes, subtotal, tax_amount, total_amount,
       │      status: PENDING(0), payment_status: PENDING(0),
       │      created_status: 1, payment_method, notes, ...
       │   })
       │
       ├─ AppointmentService::create() × N  (service_name, duration, price, sequence_order)
       │
       └─ InvoiceService::createDtaftInvoiceFromAppointment(appointment, 'cash', 0)
            └─ Invoice DRAFT (invoice_number=NULL, status=DRAFT)
            └─ InvoiceItem × N (net/tax عبر TaxCalculatorService)
            └─ لا Payment — المال يُسجل عند الكاشير فقط

  ─────────────────────────────────────────
  │  بعد commit:
  │  BookingMailService::sendForNewBooking(appointment) — queued
  │    ├─ إيميل للعميل (API فقط)
  │    └─ إشعار للشركة (كل الحجوزات)
  │    └─ فشل الإيميل لا يُبطل الحجز (try/catch + Log)
  ▼
AppointmentResource::toArray() → 201 Created JSON
```

---

## 2. حالات الـ Response

| الحالة | Code | متى |
|--------|------|-----|
| نجاح | `201` | كل التحقق نجح + transaction commit |
| `SlotUnavailableException` | `409` + `slot_conflict` | فحص التعارض فشل تحت القفل — سبقك أحد |
| `InvalidArgumentException` | `422` | أي تحقق آخر فشل (خارج الدوام، إجازة، حد يومي، ...) |
| `ModelNotFoundException` | `404` | service/provider غير موجود (بعد BOOK-09) |
| `Throwable` | `500` | خطأ غير متوقع |

---

## 3. الملفات التفصيلية

| الملف | المحتوى |
|-------|---------|
| [`concurrency.md`](concurrency.md) | التزامن ومنع الحجز المزدوج — `BookingLockService` + `SELECT FOR UPDATE` |
| [`time-off-logic.md`](time-off-logic.md) | منطق الإجازات — `scopeCoveringDate` + `blockedWindowOn` |
| [`customer-free-check.md`](customer-free-check.md) | فحص العميل الحر — `assertCustomerIsFree` + `PhoneNumber` |
| [`add-service-flow.md`](add-service-flow.md) | إضافة خدمة لحجز قائم — `GapAnalysis` + `Push` |

> **المرجع القديم الأكثر تفصيلًا (1191 سطر):** [`docs/BOOKING_FLOW.md`](../BOOKING_FLOW.md) — لا يزال يُقرأ جنبًا إلى جنب مع هذا القسم.

---

*التالي: [`concurrency.md`](concurrency.md)*
