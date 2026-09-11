# BookingService — منسق الحجز الرئيسي

> **الملف:** `app/Services/BookingService.php:1` (778 سطر) — أهم Service في النظام

---

## 1. المسؤولية

`BookingService` هو **المنسق (Orchestrator)** لعملية الحجز. لا يحسب الضريبة بنفسه (يفوّض لـ `TaxCalculatorService`)، ولا يتحقق بنفسه (يفوّض لـ `BookingValidationService`) — بل يدير التسلسل والـ transaction.

---

## 2. `createBooking()` — المراحل الـ 7

### التوقيع — `BookingService.php:42`

```php
public function createBooking(?User $customer, array $bookingData): Appointment
```

| المعامل | النوع | الوصف |
|---------|-------|-------|
| `$customer` | `?User` | العميل (null للضيوف — لكن `bookings` routes تتطلب auth حاليًا) |
| `$bookingData` | `array` | `services`, `date`, `payment_method`, `notes`, `customer_name/email/phone`, flags |

### المرحلة 1: استخراج البيانات — `BookingService.php:45`

```php
$services      = $bookingData['services'];
$date          = $bookingData['date'];
$paymentMethod = $bookingData['payment_method'];
$notes         = $bookingData['notes'] ?? null;
$customerName  = $bookingData['customer_name']  ?? ($customer->full_name ?? null);
$customerEmail = $bookingData['customer_email'] ?? $customer->email ?? null;
$customerPhone = $bookingData['customer_phone'] ?? $customer->phone ?? null;
$isConfirmed   = $bookingData['is_confirmed'] ?? true; // دائمًا true
$markAsPaid    = $bookingData['mark_as_paid'] ?? false; // دائمًا false من API
$allowSameDayPast     = (bool) ($bookingData['allow_same_day_past'] ?? false);
$bypassAvailability   = (bool) ($bookingData['bypass_availability'] ?? false);
$allowCustomerOverlap = (bool) ($bookingData['allow_customer_overlap'] ?? false);
```

### المرحلة 2: التحقق الشكلي — خارج Transaction

```php
// BookingService.php:94 — شكل الطلب فقط، لا يعتمد على حالة مشتركة
$this->validationService->validateBasicData($services, $date);
```

- عدد الخدمات 1..`max_services_per_booking`، التاريخ في نافذة الحجز، لا تكرار `service_id`
- خارج `DB::transaction` لأنه لا يحتاج قفل — يرفض الطلبات السيئة بسرعة.

### المرحلة 3: الترتيب الزمني

```php
// BookingService.php:96
$services = $this->sortServicesByStartTime($services); // usort + strcmp على start_time
```

### المرحلة 4-7: داخل `DB::transaction` — `BookingService.php:102`

```php
return DB::transaction(function () use (...) {
    // 4. القفل
    $this->lockService->lockUsers(
        array_merge(array_column($services, 'provider_id'), [$customer?->id]),
    );

    // 5. إعادة التحقق تحت القفل
    if ($customer) {
        $this->validationService->validateDailyBookingLimit($customer, $date);
    }
    $preparedServices = $this->validateAndPrepareServices($services, $date, $customer, $customerPhone, ...);

    // 6. الحساب
    $totals = $this->calculateTotals($preparedServices);

    // 7. الكتابة
    $appointment = Appointment::create([...]); // status=PENDING, payment_status=PENDING, created_status=1
    foreach ($preparedServices as $index => $serviceData) {
        AppointmentService::create([... 'sequence_order' => $index+1]);
    }
    app(InvoiceService::class)->createDtaftInvoiceFromAppointment($appointment, 'cash', 0);

    return $appointment->load(['services', 'customer', 'provider', 'services_record']);
});

// بعد الـ commit
app(BookingMailService::class)->sendForNewBooking($appointment); // queued
```

---

## 3. `validateAndPrepareServices()` — `BookingService.php:196`

تُنفذ على كل خدمة بالترتيب:

```
لكل serviceData in services:
  1. $service  = $servicesCollection->get(service_id)  // batch loaded
     $provider = $providersCollection->get(provider_id)

  2. validateProviderOffersService(provider, service) ← يرفض null (soft-deleted)

  3. $duration = getEffectiveDuration(provider, service) // حاليًا = service.duration_minutes
     $price    = getEffectivePrice(provider, service)    // custom_price → discount_price → price

  4. $startTime = Carbon::parse(date + start_time)
     $endTime   = $startTime->copy()->addMinutes(duration)

  5. validateSequentialTiming(previousEndTime, startTime) ← لا تداخل بين خدمات نفس الحجز

  6. validateTimeSlotAvailability(provider, service, start, end, allowSameDayPast, bypassAvailability)
     └─ 7 فحوصات: جدول عمل، ساعات، إجازة يوم كامل، إجازة ساعية، تعارض مواعيد، ليس في الماضي، بعد book_buffer

  7. assertCustomerIsFree(customer, phone, start, end) ← العميل ليس في مكانين (إن لم يسمح بالتداخل)

  8. $preparedServices[] = { service_id, provider_id, service_name, duration, price, start_time, end_time }
```

**Batch Loading:**

```php
// BookingService.php:208 — query واحدة لكل نوع بدل N queries
$servicesCollection  = Service::whereIn('id', $serviceIds)->get()->keyBy('id');
$providersCollection = User::whereIn('id', $providerIds)->get()->keyBy('id');
```

---

## 4. `calculateTotals()` — `BookingService.php:303`

```php
private function calculateTotals(array $preparedServices): array {
    $totalDuration = array_sum(array_column($preparedServices, 'duration_minutes'));
    $taxRate = (string) get_setting('tax_rate', '0');
    $totals = $this->taxCalculator->calculateBulk(
        array_map(fn($s) => ['price' => (string)($s['price'] ?? '0'), 'tax_rate' => $taxRate], $preparedServices),
        2 // precision
    );
    return ['subtotal' => $totals['net'], 'tax_amount' => $totals['tax'], 'total_amount' => $totals['gross'], 'total_duration' => $totalDuration];
}
```

- **لا حساب هنا** — يفوّض لـ `TaxCalculatorService::calculateBulk` (التنفيذ الوحيد).
- كان يحمل 40 سطر `bcmath` بدقة 6 — حُذفت في `MON-01` لأنها تتعارض مع `TaxCalculatorService` بدقة 2.

---

## 5. `generateAppointmentNumber()` — `BookingService.php:367`

```php
public function generateAppointmentNumber(): string {
    $prefix = 'APT';
    $date = Carbon::now()->format('Ymd');
    do {
        $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)); // 6 hex chars
        $number = "{$prefix}-{$date}-{$random}";
    } while (Appointment::where('number', $number)->exists());
    return $number; // APT-20260911-A1B2C3
}
```

- `random_bytes(3)` آمن تشفيريًا — `2026_09_10_120100_repair_and_enforce_document_number_uniqueness.php` أضاف قيدًا فريدًا كطبقة ثانية.

---

## 6. `addServiceToBooking()` — `BookingService.php:461` (إضافة خدمة لحجز قائم)

### متى تُستخدم؟

الموظف في Staff Dashboard يريد إضافة خدمة لحجز `PENDING` (لم يُدفع بعد).

### النمطان

| النمط | الشرط | ماذا يحدث |
|-------|-------|-----------|
| `same_provider` | نفس `provider_id` | إضافة `AppointmentService` للـ anchor + تعديل `start_time/end_time` + إعادة حساب الإجماليات |
| `child_created` | مزود مختلف | إنشاء `Appointment` ابن (`parent_appointment_id = parent.id`) + `AppointmentService` واحد |

### التدفق

```
addServiceToBooking(anchor, {service_id, provider_id, placement: before/after, duration, start_time, apply_push})
  ├─ canAcceptNewService() — PENDING + DRAFT فقط
  ├─ GapAnalysisService@analyzeAddBefore/After أو @analyzeChildAdd
  │    └─ هل توجد فجوة كافية؟ هل المزود متاح؟ هل نحتاج push؟
  ├─ إن requires_push && !apply_push → throw PushRequiredException (يُعرض confirm للموظف)
  └─ DB::transaction
       ├─ lockUsers([newProvider, anchor.provider])
       ├─ assertNoConflictingAppointment (إعادة فحص تحت القفل — TOCTOU fix BOOK-02)
       ├─ executePushPlan (إن طُلب)
       ├─ addServiceSameProvider أو addServiceDifferentProvider
       └─ InvoiceService@rebuildAggregatedInvoice(invoiceOwner) — فاتورة واحدة مجمعة
```

---

## 7. Dead Code — `getEffectiveDuration()`

```php
// BookingService.php:332
private function getEffectiveDuration(User $provider, Service $service): int {
    return $service->duration_minutes; // ← return مبكر!
    $pivot = DB::table('provider_service')->where(...)->first(); // لا يُنفذ أبدًا
    return $pivot->custom_duration ?? $service->duration_minutes;
}
```

- `custom_duration` من pivot لا تُستخدم أبدًا — `docs/BOOKING_FLOW.md:268` يوثقها كـ bug.

---

*التالي: [`validation-service.md`](validation-service.md)*
