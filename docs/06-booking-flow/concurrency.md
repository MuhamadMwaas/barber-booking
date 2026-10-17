# التزامن ومنع الحجز المزدوج

> **الملفات:** `app/Services/BookingLockService.php:1`، `app/Services/BookingService.php:102`، `database/migrations/2026_09_07_120000_add_conflict_lookup_index_to_appointments.php:1`

---

## 1. المشكلة

```
الزمن ──►

Request A:  SELECT "هل 10:00 متاح؟" → نعم ──────► INSERT 10:00
Request B:  SELECT "هل 10:00 متاح؟" → نعم ──► INSERT 10:00  ← حجز مزدوج!
```

`SELECT` ناجح لا يحجز شيئًا. كان فحص التعارض خارج `DB::transaction` — طلبان متزامنان يقرآن "فاضي" ثم يكتب كلاهما.

---

## 2. الحل — القفل + الفحص داخل Transaction

| العنصر | التفصيل |
|--------|---------|
| **القفل** | `BookingLockService::lockUsers([providerIds, customerId])` → `SELECT ... FOR UPDATE` على `users` |
| **لماذا `users` لا `appointments`؟** | الحالة المحمية هي غياب موعد — لا يمكن قفل صفوف غير موجودة. صف المزود موجود دائمًا |
| **منع deadlock** | كل ids مرتبة تصاعديًا `sort($ids)` — طلبان يقفلان بنفس الترتيب |
| **الفحص** | `assertNoConflictingAppointment()` بعد القفل، داخل نفس الـ transaction |
| **الفهرس** | `appointments_conflict_lookup_idx` على `(provider_id, appointment_date, created_status, status)` |
| **رد الخسارة** | `409` + `error_type: slot_conflict` (`SlotUnavailableException`) |

### الكود — `BookingService.php:102`

```php
return DB::transaction(function () use (...) {
    // 1. القفل أولًا
    $this->lockService->lockUsers(
        array_merge(array_column($services, 'provider_id'), [$customer?->id]),
    );

    // 2. إعادة الفحص تحت القفل
    if ($customer) {
        $this->validationService->validateDailyBookingLimit($customer, $date);
    }
    $preparedServices = $this->validateAndPrepareServices(...);
    // داخل validateAndPrepareServices:
    //   validateTimeSlotAvailability → assertNoConflictingAppointment (تحت القفل)

    // 3. الكتابة
    $appointment = Appointment::create([...]);
    // ...
});
```

### BookingLockService — `app/Services/BookingLockService.php:1`

```php
public function lockUsers(array $userIds): void {
    $ids = array_filter(array_unique($userIds)); // إزالة null/duplicates
    sort($ids, SORT_NUMERIC);
    if (empty($ids)) return;
    User::whereIn('id', $ids)->lockForUpdate()->get();
    // الآن كل من يحاول lock نفس المزود ينتظر حتى commit/rollback
}
```

---

## 3. المسارات الخمسة التي تكتب على تقويم المزود

| المسار | قفل | فحص تعارض تحت القفل |
|--------|-----|-----------|
| `BookingService::createBooking()` (API + StaffDashboard) | ✅ `lockUsers([providers…, customer])` | ✅ `assertNoConflictingAppointment` + `assertCustomerIsFree` + الحد اليومي |
| `BookingService::addServiceToBooking()` | ✅ `lockUsers([newProvider, anchor.provider])` | ✅ يُعيد قراءة الـ anchor و**يُعيد تحليل الفجوة وخطة الـ push كاملة** تحت القفل |
| `StaffDashboard::updateAppointment()` | ✅ `lockUsers` | ✅ (يستثني الموعد نفسه؛ `force_booking` يتجاوز) |
| Filament `CreateAppointment::handleRecordCreation()` | ✅ `lockUsers([provider, customer])` | ✅ يُعيد `performProfessionalValidations()` كاملة |
| Filament `EditAppointment::handleRecordUpdate()` | ✅ `lockUsers([oldProvider, newProvider, customer])` | ✅ فقط حين يحجز الحفظ وقتاً جديداً (نقل/تمديد/إعادة تفعيل ملغى)؛ `force_booking` يتجاوز |

`AppointmentCreationService` **كود ميت** فيه النمط القديم (فحص خارج المعاملة بلا قفل) — موسوم بتعليق تحذيري؛ لا تستدعِه قبل إصلاحه.

> تاريخ: حتى 2026-10-16 كانت صفحتا Filament خارج العقد — الإنشاء بفحص قبل المعاملة، والتعديل **بلا أي فحص**. التفاصيل: [`docs/fixes/BOOK-02_double_booking_toctou.md`](../fixes/BOOK-02_double_booking_toctou.md).

---

## 4. العقد الذي يجب احترامه

> أي كود جديد يكتب `provider_id` / `appointment_date` / `start_time` / `end_time` — **أو يعيد موعداً ملغى إلى حالة تحجز الوقت** (`status` / `created_status`):

1. داخل `DB::transaction()`
2. `lockUsers()` **أولًا**
3. `assertNoConflictingAppointment()` **بعد** القفل
4. أي **خطة** حُسبت قبل القفل (تحليل فجوة، خطة push) **تُحسب من جديد** بعده — لا تُنفَّذ الخطة القديمة

الفحص خارج الـ transaction مسموح كـ **رفض سريع فقط**، ليس كضمان.

**لماذا لا قيد `UNIQUE(provider_id, start_time)`؟** الإلغاء لا يحذف الصف — يغيّر `status` فقط. القيد كان سيمنع إعادة حجز أي فتحة سبق إلغاؤها. ويمنع أيضاً نقل `force_booking` المتعمد إلى نفس الدقيقة. القفل هو الضمان الوحيد.

**الاختبارات:** `tests/Feature/Booking/DoubleBookingRaceTest.php` (منطق القفل، على SQLite) + `tests/Concurrency/book02_mysql_race.php` (سباق حقيقي بعمليات منفصلة على MySQL).

---

*التالي: [`time-off-logic.md`](time-off-logic.md)*
