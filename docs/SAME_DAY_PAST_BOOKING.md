# السماح بالحجز في الماضي ضمن اليوم الحالي (Same‑Day Back‑Dating)

> **التاريخ:** 2026-06-12
> **النطاق:** تمكين الطاقم (provider + admin) من إنشاء/تعديل حجز في **وقتٍ ماضٍ ضمن اليوم الحالي فقط** من **Staff Dashboard** و**Filament Admin**، دون كسر منطق التحقق القائم ودون التأثير على المسارات العميلة (API / Web).

---

## 1. الهدف بدقّة

كان النظام يمنع أي حجز في الماضي مطلقًا. المطلوب:

- من **الداشبورد** و**لوحة Filament**: يستطيع الطاقم تسجيل حجز بدأ **في وقتٍ سابق من نفس اليوم** (زبون دخل الساعة 10:00 وأنا أسجّله الساعة 14:00).
- يبقى **ممنوعًا** الحجز في **أيام سابقة** (قبل اليوم).
- تبقى كل قواعد التحقق الأخرى كما هي (ساعات الدوام، التعارض، الإجازات، التكرار، الحد اليومي).
- **المسارات العميلة** (تطبيق الموبايل / API / Web) تبقى صارمة: لا حجز في الماضي إطلاقًا.

---

## 2. القرارات المتّفق عليها (قبل التنفيذ)

| القرار | الخيار المعتمد |
|--------|----------------|
| **مدى التجاوز** | كامل اليوم الحالي بلا `book_buffer` (ماضي + مستقبل ضمن اليوم). إلغاء الـ buffer ضروري تقنيًا لأن أي وقت ماضٍ أصغر من `now + buffer` فيُرفض تلقائيًا. |
| **الصلاحية** | إعادة استخدام `create_booking` الموجودة — لا صلاحية جديدة. أي طاقم يستطيع الحجز من الداشبورد يستطيع التأريخ الرجعي ضمن اليوم. |
| **المسارات المشمولة** | المسار النشط `saveBookingFromAlpine` + إضافة الخدمة لحجز قائم + Filament `CreateAppointment`. |
| **المسارات غير المشمولة** | `saveBooking()` (legacy غير موصول)، `Api\BookingController`، `Http\Controllers\BookingController` (عميلة). |

---

## 3. مبدأ التصميم: عزل جراحي عبر flag صريح

المنطق كله مُركّز في **مكان واحد**: دالة جديدة خاصة `validateNotInPast()` داخل `BookingValidationService`. يتحكم بها **flag بولياني صريح** اسمه `allow_same_day_past`:

- **افتراضيًا `false`** ⇒ السلوك القديم تمامًا (لا يتغيّر شيء لأي مستدعٍ قائم لم يُحدَّث).
- **`true`** ⇒ يُمرَّر فقط من حدود الطاقم الموثوقة (الداشبورد + Filament).

**لماذا flag صريح وليس الاعتماد على `booking_source`؟**
لأن الداشبورد و`Http\Controllers\BookingController` كلاهما ينتج `booking_source = in_person`، فلا يمكن التمييز بينهما عبره. الـ flag الصريح يضمن أن التساهل **لا يتسرّب أبدًا** لأي مسار غير مقصود.

**خريطة تدفّق الـ flag:**

```
المسار النشط للحجز:
  StaffDashboard::saveBookingFromAlpine()   ['allow_same_day_past' => true]
      → BookingService::createBooking($bookingData)
          → validateAndPrepareServices(..., $allowSameDayPast)
              → BookingValidationService::validateTimeSlotAvailability(..., $allowSameDayPast)
                  → validateNotInPast($startTime, $allowSameDayPast)   ← القرار الوحيد

لوحة Filament:
  CreateAppointment::performProfessionalValidations()
      → validateTimeSlotAvailability(..., true)
          → validateNotInPast($startTime, true)

إضافة خدمة لحجز قائم:
  StaffDashboard::confirmAddService()   ['allow_same_day_past' => true]
      → BookingService::addServiceToBooking($data)
          → GapAnalysisService::analyzeAddBefore/analyzeChildAdd(..., $allowSameDayPast)
  StaffDashboard::analyzeAddServiceGap()  (المعاينة الحيّة)
      → GapAnalysisService::analyze*(..., true)
```

---

## 4. التعديلات تفصيلًا

### 4.1 `app/Services/BookingValidationService.php` — قلب التعديل

**(أ) توقيع `validateTimeSlotAvailability`** — أُضيف باراميتر اختياري أخير:

```php
public function validateTimeSlotAvailability(
    User $provider,
    Service $service,
    Carbon $startTime,
    Carbon $endTime,
    bool $allowSameDayPast = false   // ← جديد، افتراضي false
): void {
```

**(ب) استبدال الفحصين #6 و #7 المضمَّنين** بنداء واحد للدالة المعزولة:

```php
// 6 + 7. Past-time / minimum-advance guard.
$this->validateNotInPast($startTime, $allowSameDayPast);
```

**(ج) الدالة الجديدة المعزولة** `validateNotInPast()` — وهي **المكان الوحيد** الذي يقرّر "هل وقت البداية في الماضي؟":

```php
private function validateNotInPast(Carbon $startTime, bool $allowSameDayPast = false): void
{
    // تأريخ رجعي موثوق للطاقم: مسموح فقط ضمن اليوم الحالي.
    if ($allowSameDayPast && $startTime->isToday()) {
        return;
    }

    // 6. لا حجز في الماضي
    if ($startTime->lt(Carbon::now())) {
        throw new InvalidArgumentException("Cannot book time slot in the past");
    }

    // 7. الحد الأدنى للحجز المسبق (book_buffer)
    $book_buffer = intval(get_setting('book_buffer', 60));
    if ($startTime->lt(Carbon::now()->addMinutes($book_buffer))) {
        throw new InvalidArgumentException("Booking must be at least {$book_buffer} minutes in advance");
    }
}
```

**لماذا يخدم المهمة؟**
- `isToday()` يضمن أن التساهل محصور في **اليوم الحالي فقط**؛ أي يوم سابق ليس "today" فيسقط على الفحص العادي ويُرفض ⇒ **الأيام السابقة تبقى ممنوعة (دفاع طبقي)**.
- بإرجاع `return` مبكرًا نتجاوز **الفحصين معًا** (#6 الماضي و#7 الـ buffer)، وهو ضروري لأن إبقاء #7 وحده يرفض الماضي تلقائيًا.
- الأيام المستقبلية لا تتأثر (`isToday()` يكون false فتمر على الفحص الطبيعي والـ buffer يبقى ساريًا عليها).
- باقي فحوص `validateTimeSlotAvailability` (ساعات الدوام، الإجازة اليومية/الساعية، **تعارض المواعيد**) لم تُمسّ ⇒ لا يمكن حجز ماضٍ يتعارض مع موعد قائم.

---

### 4.2 `app/Services/BookingService.php` — تمرير الـ flag

**(أ) داخل `createBooking()`** — استخراج الـ flag من بيانات الحجز:

```php
// Trusted staff flag (Staff Dashboard / Filament admin)
$allowSameDayPast = (bool) ($bookingData['allow_same_day_past'] ?? false);
```

ثم تمريره لخطوة التحضير:

```php
$preparedServices = $this->validateAndPrepareServices($services, $date, $customer, $customerPhone, $allowSameDayPast);
```

**(ب) توقيع `validateAndPrepareServices`** — باراميتر اختياري أخير:

```php
private function validateAndPrepareServices(
    array $services, string $date, ?User $customer,
    ?string $customerPhone = null,
    bool $allowSameDayPast = false,   // ← جديد
): array {
```

وتمريره عند نداء التحقق:

```php
$this->validationService->validateTimeSlotAvailability(
    $provider, $service, $startTime, $endTime, $allowSameDayPast
);
```

**(ج) داخل `addServiceToBooking()`** — استخراج الـ flag وتمريره لطبقة التحليل:

```php
$allowSameDayPast = (bool) ($data['allow_same_day_past'] ?? false);
...
$analysis = $placement === 'before'
    ? $this->gapAnalysis->analyzeAddBefore($anchor, $service, $duration, $requestedStart, $allowSameDayPast)
    : $this->gapAnalysis->analyzeAddAfter($anchor, $service, $duration, $requestedStart, $allowSameDayPast);
// أو
$analysis = $this->gapAnalysis->analyzeChildAdd(
    $invoiceOwner, $newProvider, $service, $duration, $placement, $requestedStart, $allowSameDayPast
);
```

**لماذا يخدم المهمة؟** التمرير صريح وافتراضيّه `false`، فأي مستدعٍ لم يُمرّر الـ flag (الـ API مثلًا) يبقى على السلوك الصارم.

---

### 4.3 `app/Services/GapAnalysisService.php` — مسار "إضافة خدمة لحجز قائم"

أُضيف باراميتر `bool $allowSameDayPast = false` (أخيرًا) إلى الدوال الثلاث `analyzeAddBefore`, `analyzeAddAfter`, `analyzeChildAdd` للحفاظ على توقيع موحّد لنقاط النداء. والمنطق الفعلي يتغيّر في موضعين فقط:

**(أ) `analyzeAddBefore`** — كان يثبّت أقرب بداية ممكنة عند `now()` لمنع الماضي:

```php
// قبل:
if ($anchor->appointment_date->isToday() && $earliestStart->lt(Carbon::now())) {
    $earliestStart = Carbon::now();
}
// بعد:
if (! $allowSameDayPast && $anchor->appointment_date->isToday() && $earliestStart->lt(Carbon::now())) {
    $earliestStart = Carbon::now();
}
```

عند رفع الـ flag، تبقى أقرب بداية = نهاية الموعد السابق أو بداية الدوام ⇒ يُسمح بوضع الخدمة في وقتٍ ماضٍ قبل الـ anchor.

**(ب) `analyzeChildAdd`** — كان يرفض صراحةً (`in_past`):

```php
// قبل:
if ($proposedStart->lt(Carbon::now()) && $invoiceOwner->appointment_date->isToday()) {
    return ['is_possible' => false, 'reason' => 'in_past'];
}
// بعد:
if (! $allowSameDayPast && $proposedStart->lt(Carbon::now()) && $invoiceOwner->appointment_date->isToday()) {
    return ['is_possible' => false, 'reason' => 'in_past'];
}
```

> ملاحظة: `analyzeAddAfter` لا يحتوي أصلًا على فحص ماضٍ (الإضافة بعد الـ anchor تقع طبيعيًا عند نهايته أو بعدها)، فالباراميتر فيها للتناسق فقط ولا يُستخدم. **شرط `isToday()` يبقى قائمًا في الموضعين** ⇒ الأيام السابقة تظل محميّة هنا أيضًا.

---

### 4.4 `app/Livewire/StaffDashboard.php` — رفع الـ flag عند الحدود

**(أ) `saveBookingFromAlpine()`** (المسار النشط لإنشاء الحجز):

```php
$bookingData = [
    'date' => $this->selectedDate,
    'payment_method' => 'cash',
    'is_confirmed' => true,
    'mark_as_paid' => false,
    'allow_same_day_past' => true,   // ← جديد
    ...
];
```

**(ب) `analyzeAddServiceGap()`** (المعاينة الحيّة لإضافة خدمة) — تمرير `true`:

```php
$gap->analyzeAddBefore($anchor, $service, $duration, null, true)
$gap->analyzeAddAfter($anchor, $service, $duration, null, true)
$gap->analyzeChildAdd($anchor->parent ?? $anchor, $provider, $service, $duration, $form['placement'], null, true)
```

**(ج) `confirmAddService()`** (تنفيذ إضافة الخدمة):

```php
$bookingService->addServiceToBooking($anchor, [
    ...$this->addServiceForm,
    'apply_push' => $applyPush,
    'allow_same_day_past' => true,   // ← جديد
]);
```

> الأمان: كل هذه النقاط محميّة مسبقًا بـ `dashDeny('create_booking')` / `dashDeny('add_service')` و middleware `EnsureStaffDashboardAccess`، فلا يمكن لغير الطاقم رفع الـ flag.

---

### 4.5 `app/Filament/Resources/Appointments/Pages/CreateAppointment.php`

لوحة Filament لا تمر عبر `createBooking`، لكنها تستدعي **نفس** دالة التحقق مباشرةً. أُمرّر `true`:

```php
$validationService->validateTimeSlotAvailability(
    $provider, $firstService, $startTime, $endTime,
    true   // ← admin = staff موثوق؛ الأيام السابقة تبقى محظورة عبر validateBasicData
);
```

---

## 5. لماذا "لم نكسر النظام"؟ (تحليل المخاطر)

1. **التغيير افتراضيّه محايد:** كل باراميتر جديد قيمته الافتراضية `false`. أي مستدعٍ قائم لم يُحدَّث (API، Web، legacy `saveBooking`) يسلك **نفس السلوك القديم بالضبط**.
2. **نقطة قرار واحدة:** منطق "الماضي" صار محصورًا في `validateNotInPast()` ⇒ سهل التدقيق، ويستحيل أن يتسرّب التساهل إلى قاعدة أخرى.
3. **`validateBasicData` لم تُمسّ:** ما يزال يرفض `date < today` ⇒ **الأيام السابقة ممنوعة** بصرف النظر عن الـ flag (دفاع طبقي مزدوج مع `isToday()`).
4. **فحوص التعارض باقية:** لا يمكن إنشاء حجز ماضٍ يتداخل مع موعد آخر (الفحص #5 وفحص تعارض الـ child قائمان).
5. **العميل لا يستطيع رفع الـ flag:** لا يُقرأ من request body في أي مسار API؛ يُحقَن فقط server-side عند حدود الطاقم.

---

## 6. ما لم يُغيَّر عمدًا

| العنصر | السبب |
|--------|-------|
| `StaffDashboard::saveBooking()` (legacy) | غير موصول بالواجهة الحالية (راجع `docs/STAFF_DASHBOARD.md §25`) وخارج النطاق المتّفق عليه. |
| `Api\BookingController` / `Http\Controllers\BookingController` | مسارات عميلة — يجب أن تبقى صارمة (لا حجز في الماضي). |
| `validateBasicData()` (حظر الأيام السابقة) | هو بالضبط ما يمنع الأيام السابقة، ونريد إبقاءه. |
| فحص ساعات الدوام والتعارض والإجازات | غير متعلق بالماضي؛ يجب أن يبقى ساريًا حتى للحجز الرجعي. |

---

## 7. checklist للاختبار اليدوي

افترض الآن الساعة 14:00 واليوم هو D:

1. **داشبورد — ماضي اليوم:** احجز خدمة تبدأ 10:00 اليوم ⇒ يجب أن **ينجح**.
2. **داشبورد — مستقبل اليوم بلا buffer:** احجز 14:10 (ضمن الـ 60 دقيقة) ⇒ يجب أن **ينجح** (الـ buffer مُلغى لليوم).
3. **داشبورد — يوم سابق:** اختر D‑1 واحجز ⇒ يجب أن **يُرفض** ("Cannot book in the past").
4. **داشبورد — تعارض ماضٍ:** احجز 10:00 لمزوّد لديه موعد 10:00–10:30 ⇒ يجب أن **يُرفض** (تعارض).
5. **Filament Create — ماضي اليوم:** أنشئ موعدًا 09:30 اليوم ⇒ يجب أن **ينجح**.
6. **API (`POST /api/bookings`) — ماضي اليوم:** ⇒ يجب أن **يُرفض** (لا flag).
7. **إضافة خدمة "before" بوقت ماضٍ اليوم لحجز قائم:** ⇒ يجب أن **ينجح** (بشرط عدم التعارض/ضمن الدوام).
8. **حجز مستقبلي عبر API ضمن الـ buffer:** ⇒ يبقى **مرفوضًا** كما قبل (لم يتغيّر).

---

## 8. ملخّص الملفات المعدّلة

| # | الملف | جوهر التغيير |
|---|------|--------------|
| 1 | `app/Services/BookingValidationService.php` | باراميتر `$allowSameDayPast` + دالة معزولة `validateNotInPast()` |
| 2 | `app/Services/BookingService.php` | تمرير الـ flag عبر `createBooking` و`addServiceToBooking` |
| 3 | `app/Services/GapAnalysisService.php` | تخطّي clamp/`in_past` عند رفع الـ flag في `analyzeAddBefore`/`analyzeChildAdd` |
| 4 | `app/Livewire/StaffDashboard.php` | `allow_same_day_past => true` في الحجز وإضافة الخدمة (إنشاء + معاينة) |
| 5 | `app/Filament/Resources/Appointments/Pages/CreateAppointment.php` | تمرير `true` لـ `validateTimeSlotAvailability` |
| — | `docs/SAME_DAY_PAST_BOOKING.md` | هذا الملف |

> لا توجد migration ولا تغييرات قاعدة بيانات — الميزة منطق تحقّق بحت.
