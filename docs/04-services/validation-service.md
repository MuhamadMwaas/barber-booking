# BookingValidationService — كل قواعد التحقق

> **الملف:** `app/Services/BookingValidationService.php:1`

---

## 1. المسؤولية

كل التحقق الذي يعتمد على حالة النظام (جداول، إجازات، مواعيد) يعيش هنا. `BookingService` يستدعيه ولا يحقق بنفسه.

---

## 2. الدوال الست

### 1. `validateBasicData(services, date)`

```
- 1 ≤ count(services) ≤ max_services_per_booking (SalonSetting)
- date ≥ today
- date ≤ today + max_booking_days
- لا تكرار service_id
```

خارج transaction — شكل الطلب فقط.

### 2. `validateProviderOffersService(provider, service, providerId, serviceId)`

```
- provider !== null && service !== null (يرفض soft-deleted — BOOK-09)
- pivot provider_service موجود + is_active=true
- provider.is_active = true
- service.is_active = true
```

إن فشل → `InvalidArgumentException` برسالة واحدة موحدة (منع تعداد الـ ids) — `Log::notice` يسجل السبب الحقيقي.

### 3. `validateSequentialTiming(prevEnd, start, index)`

```
start ≥ prevEnd  (لا تداخل بين خدمات نفس الحجز)
```

### 4. `validateTimeSlotAvailability(provider, service, start, end, allowSameDayPast, bypassAvailability)`

**7 فحوصات بالترتيب:**

| # | الفحص | إن فشل |
|---|-------|--------|
| 1 | جدول عمل في `day_of_week` + `is_work_day=true` | `provider_not_working` |
| 2 | ضمن ساعات العمل `start ≥ work_start && end ≤ work_end` | `exceeds_work_hours` |
| 3 | لا إجازة يوم كامل `ProviderTimeOff::coveringDate + FULL_DAY` | `provider_full_day_off` |
| 4 | لا إجازة ساعية تحجب `coveringDate + blocksWindow` | `provider_time_off_conflict` |
| 5 | لا تعارض مواعيد `assertNoConflictingAppointment` | `409 slot_conflict` |
| 6 | ليس في الماضي `start > now()` | `in_past` |
| 7 | بعد `book_buffer` `start > now()+buffer` | `too_soon` |

`bypassAvailability=true` يتجاوز 1-4 فقط — التعارض وهوية المزود يبقيان.

### 5. `assertCustomerIsFree(customer, phone, start, end)`

```
هل للعميل موعد آخر يتداخل [start,end) عند أي مزود؟
  → Appointment::blocksProviderTime()->overlapping(start,end)
  → مطابقة: customer_id أو PhoneNumber::key(phone) (آخر 9 أرقام)
  → إن وُجد → InvalidArgumentException
  → allow_customer_overlap=true يتجاوز (force_booking فقط)
```

### 6. `validateDailyBookingLimit(customer, date)`

```
count(appointments where customer_id=X && appointment_date=date) < max_daily_bookings
```

تحت القفل — وإلا طلبان يقرآن "1 < 10" ويكتبان معًا.

---

## 3. `assertNoConflictingAppointment()` — الفحص الحرج

```php
public function assertNoConflictingAppointment(User $provider, Carbon $start, Carbon $end, ?int $excludeId = null): void {
    $conflict = Appointment::where('provider_id', $provider->id)
        ->where('appointment_date', $start->format('Y-m-d'))
        ->blocksProviderTime() // created_status=1 + status IN (0,1)
        ->overlapping($start, $end)
        ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
        ->exists();
    if ($conflict) {
        throw new SlotUnavailableException('Time slot is already booked');
    }
}
```

- `SlotUnavailableException` → `409` في Controller.
- `excludeId` يُستخدم في `StaffDashboard::updateAppointment` لاستثناء الموعد نفسه.

---

*التالي: [`availability-service.md`](availability-service.md)*
