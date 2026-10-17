# BOOK-02 — الحجز المزدوج بسبب TOCTOU — تقرير الإصلاح

> **التاريخ:** 2026-10-16
> **المرجع:** [`SECURITY_AUDIT_2026-08-29.md`](../../SECURITY_AUDIT_2026-08-29.md) — قسم `BOOK-02`
> **الحالة:** ✅ مُصلحة في كل مسارات الكتابة على تقويم الحلاق
> **الاختبارات:** 9 اختبارات جديدة كلها ناجحة. المجموعة الكاملة: 615 ناجحاً، و18 فشلاً قديماً لا علاقة لها بالحجز (نفس الخط الأساسي قبل التعديل)
> **لم يُشغَّل:** اختبار السباق الحقيقي على MySQL (السبب في القسم 8)

---

## فهرس

1. [المشكلة بلغة بسيطة](#1-المشكلة-بلغة-بسيطة)
2. [ماذا وجدتُ عندما قرأتُ الكود](#2-ماذا-وجدت-عندما-قرأت-الكود)
3. [أين أخطأ تقرير التدقيق](#3-أين-أخطأ-تقرير-التدقيق)
4. [القرارات التي اتفقنا عليها](#4-القرارات-التي-اتفقنا-عليها)
5. [الإصلاحات بالتفصيل](#5-الإصلاحات-بالتفصيل)
6. [الاختبارات](#6-الاختبارات)
7. [نتائج التحقق](#7-نتائج-التحقق)
8. [ما لم يُتحقَّق منه](#8-ما-لم-يتحقق-منه)
9. [ملاحظات جانبية ظهرت أثناء العمل](#9-ملاحظات-جانبية-ظهرت-أثناء-العمل)
10. [قائمة الملفات المعدّلة](#10-قائمة-الملفات-المعدلة)
11. [القاعدة لأي كود جديد](#11-القاعدة-لأي-كود-جديد)

---

## 1. المشكلة بلغة بسيطة

حجز موعد يتكون من خطوتين:

1. **السؤال:** «هل الساعة 10:00 فارغة عند أحمد؟»
2. **الفعل:** «إذن اكتب الموعد.»

إذا وصل طلبان في نفس اللحظة، قد يسأل كلاهما السؤال قبل أن يكتب أيٌّ منهما:

```
الزمن   الطلب أ                          الطلب ب
─────   ───────────────────────────      ───────────────────────────
t=0     هل 10:00 فارغة؟  ← نعم
t=1                                      هل 10:00 فارغة؟  ← نعم (أ لم يكتب بعد)
t=2     اكتب الموعد 10:00
t=3                                      اكتب الموعد 10:00
                                         ⇒ موعدان لنفس الحلاق في نفس الوقت
```

هذا اسمه **TOCTOU** (Time-Of-Check to Time-Of-Use): الجواب صحيح لحظة السؤال، وخاطئ لحظة الاستخدام.

**الأثر الحقيقي:** عميلان يصلان الصالون لنفس الحلاق في نفس الساعة، ويجب صرف أحدهما. يحدث هذا تحديداً في لحظات الذروة.

---

## 2. ماذا وجدتُ عندما قرأتُ الكود

### 2.1 المسار الذي يتكلم عنه التقرير كان قد أُصلح

التقرير كُتب على كود قديم. في الكود الحالي، [`BookingService::createBooking()`](../../app/Services/BookingService.php#L91-L108) يعمل هكذا:

```php
// فحوص الشكل فقط (عدد الخدمات، التاريخ، تكرار الخدمة) — لا تعتمد على الآخرين
$this->validationService->validateBasicData($services, $date);
$services = $this->sortServicesByStartTime($services);

$appointment = DB::transaction(function () use (...) {
    // ① القفل أولاً
    $this->lockService->lockUsers(
        array_merge(array_column($services, 'provider_id'), [$customer?->id]),
    );

    // ② ثم الفحص — تحت القفل
    if ($customer) {
        $this->validationService->validateDailyBookingLimit($customer, $date);
    }
    $preparedServices = $this->validateAndPrepareServices(...);

    // ③ ثم الكتابة
    ...
});
```

هذا المسار **سليم**. الإصلاح الأساسي موجود منذ 2026-09-07.

### 2.2 لماذا نقفل صف الحلاق في `users` وليس صفوف المواعيد

هذه أهم فكرة في الإصلاح كله.

اقتراح التقرير هو إضافة `lockForUpdate()` على استعلام التعارض نفسه:

```php
Appointment::where(...)->overlapping(...)->lockForUpdate()->exists();
```

**هذا لا يحمي.** `lockForUpdate()` يقفل الصفوف التي **وجدها** الاستعلام. في الحالة التي نخاف منها، الفتحة فارغة، فلا يجد الاستعلام أي صف، ولا يقفل شيئاً. ما نحاول حمايته هو «**غياب** موعد»، والغياب لا يُقفل.

(نظرياً InnoDB يملك gap locks تغطي المساحات الفارغة، لكنها تعتمد على وجود فهرس مناسب يختاره المخطط. بناء سلامة الحجوزات على سلوك قفل لا نتحكم به ليس خياراً مقبولاً.)

**الحل الموجود:** نقفل شيئاً **موجوداً دائماً**: صف الحلاق نفسه في جدول `users`.

[`BookingLockService::lockUsers()`](../../app/Services/BookingLockService.php):

```php
DB::table('users')
    ->whereIn('id', $ids)   // الحلاقون + العميل
    ->orderBy('id')         // ترتيب ثابت ⇒ لا deadlock
    ->lockForUpdate()
    ->pluck('id');
```

النتيجة مع نفس السيناريو:

```
الزمن   الطلب أ                               الطلب ب
─────   ───────────────────────────           ───────────────────────────
t=0     BEGIN; اقفل صف أحمد ✓
t=1                                           BEGIN; اقفل صف أحمد … ينتظر ⏳
t=2     هل 10:00 فارغة؟ نعم
t=3     اكتب الموعد; COMMIT (يُحرَّر القفل)
t=4                                           … حصل على القفل ✓
t=5                                           هل 10:00 فارغة؟ لا ← 409 slot_conflict
```

**لماذا الترتيب `orderBy('id')`؟** الحجز قد يقفل حلاقَين وعميلاً. لو قفل طلبٌ (أحمد ثم سامر) وطلبٌ آخر (سامر ثم أحمد)، لانتظر كل منهما الآخر إلى الأبد (deadlock). الترتيب الثابت بالـ id يجعل الجميع يقفلون بنفس الترتيب.

### 2.3 لكن القفل لا يعمل إلا إذا التزم به **كل** من يكتب

القفل يشبه طابوراً أمام باب: لا قيمة له إذا دخل أحدٌ من الشباك. لذلك فحصتُ **كل** مسار يكتب وقت موعد أو حلاقه:

| # | المسار | قفل؟ | إعادة فحص تحت القفل؟ | الحكم قبل الإصلاح |
|---|---|:-:|:-:|---|
| 1 | `BookingService::createBooking()` (API + StaffDashboard) | ✅ | ✅ | سليم |
| 2 | `StaffDashboard::updateAppointment()` (نقل/تغيير مدة) | ✅ | ✅ | سليم |
| 3 | `addServiceToBooking()` **بدون** push | ✅ | ✅ | سليم |
| 4 | `addServiceToBooking()` **مع** push | ✅ | ❌ | 🟠 **ثغرة** |
| 5 | Filament: [`CreateAppointment`](../../app/Filament/Resources/Appointments/Pages/CreateAppointment.php) | ❌ | ❌ | 🔴 **ثغرة BOOK-02 بالحرف** |
| 6 | Filament: [`EditAppointment`](../../app/Filament/Resources/Appointments/Pages/EditAppointment.php) | ❌ | ❌ | 🔴 **بلا أي فحص أصلاً** |
| 7 | `AppointmentCreationService` | ❌ | ❌ | ⚪ كود ميت (لا يُستدعى) |

تفصيل الثغرات الثلاث:

**المسار 4 — إضافة خدمة مع «دفع» المواعيد التالية (push):**
عندما يضيف الموظف خدمة تتداخل مع الموعد التالي، يحسب النظام **خطة دفع** (مثلاً: انقل موعد 11:00 إلى 12:00). كانت هذه الخطة تُحسب **قبل** القفل ثم تُنفَّذ كما هي **بعده**. لو وصل حجز جديد على 12:00 بين الحساب والقفل، يدفع النظام موعد 11:00 **فوقه** مباشرة. إعادة الفحص الموجودة كانت تعمل فقط حين لا يوجد push، أي أن هذه الحالة بالذات كانت بلا فحص.

**المسار 5 — إنشاء موعد من لوحة Filament:**
الفحص كان في `beforeCreate()`، وهذا يجري **خارج أي معاملة** (لوحة Filament لا تفعّل `databaseTransactions`). الكتابة كانت في `handleRecordCreation()` داخل `DB::transaction` لكن **بلا قفل وبلا إعادة فحص**. هذا نفس نمط BOOK-02 الأصلي حرفياً.

**المسار 6 — تعديل موعد من لوحة Filament:**
الصفحة كانت تستدعي `$record->update($data)` مباشرة (السلوك الافتراضي في Filament). يستطيع الأدمن تغيير الحلاق أو التاريخ أو الساعة أو المدة ويحفظ فوق موعد آخر **بلا أي فحص**. وأخطر من ذلك: حقل الحالة فيه خياران فقط (`PENDING` و`COMPLETED`)، فمجرد فتح موعد **ملغى** وحفظه يعيده نشطاً يحجز الوقت، ولو كانت الفتحة قد حُجزت لشخص آخر.

---

## 3. أين أخطأ تقرير التدقيق

| ما قاله التقرير | الواقع |
|---|---|
| الثغرة في `BookingService.php:67-83` | **أُصلحت** هناك قبل كتابة هذا التقرير (2026-09-07). الثغرات الحقيقية المتبقية كانت في صفحتي Filament ومسار push، والتقرير لم يذكرها. |
| الطبقة 1: `lockForUpdate()` على استعلام التعارض | **لا يحمي.** يقفل صفر صفوف حين تكون الفتحة فارغة. والتقرير نفسه يعترف بهذا في الطبقة 2: «القفل الصفّي وحده لا يحمي من تعارض على صفوف غير موجودة بعد». الحل الصحيح: قفل صف موجود دائماً (`users`). |
| الطبقة 2: `unique(['provider_id', 'start_time'])` | **يكسر النظام.** الإلغاء لا يحذف الصف، بل يغيّر `status` فقط ([`AppointmentCancellationService.php:84`](../../app/Services/AppointmentCancellationService.php#L84)). مع هذا القيد: عميل يحجز 10:00 ثم يلغي، فيأتي عميل آخر يريد 10:00، فيرفضه القيد مع أن الفتحة فارغة. هذه حالة يومية. |
| الطبقة 3: التقاط `UniqueConstraintViolationException` وإرجاع 409 | غير لازم: الـ 409 موجود أصلاً عبر `SlotUnavailableException` في [`BookingController.php:65`](../../app/Http/Controllers/Api/BookingController.php#L65). |

> **تصحيح أيضاً لملاحظة MON-03:** ذكرتْ أن القيد الفريد رُفض لأن `force_booking` يسمح بالتداخل. هذا صحيح جزئياً فقط: `bypass_availability` في `createBooking` **لا** يتجاوز فحص تعارض الحلاق، والتجاوز الوحيد هو نقل موعد في StaffDashboard بصلاحية `force_booking`. **السبب الأقوى لرفض القيد هو الإلغاءات.**

---

## 4. القرارات التي اتفقنا عليها

| السؤال | القرار | السبب |
|---|---|---|
| صفحة تعديل Filament | **قفل + فحص عند الحفظ** (وليس منع التعديل) | نُبقي الميزة للأدمن ونجعلها آمنة. صاحب `force_booking` يتجاوز، تماماً كما في StaffDashboard. |
| `AppointmentCreationService` الميت | **تعليق تحذيري فقط** | يبقى الكود، لكن أول من يربطه لاحقاً يقرأ أنه يحتاج القفل أولاً. |
| قيد في قاعدة البيانات | **بلا قيد. القفل هو الضمان** | القيد العادي يكسر إعادة حجز الفتحات الملغاة (القسم 3). |
| طريقة التحقق | Pest + اختبار سباق على MySQL | Pest نُفِّذ. سكربت MySQL كُتب ولم يُشغَّل (القسم 8). |

---

## 5. الإصلاحات بالتفصيل

### 5.1 إضافة خدمة مع push — إعادة التحليل كاملاً تحت القفل

**الملف:** [`app/Services/BookingService.php`](../../app/Services/BookingService.php#L575-L625)

**قبل:**

```php
$analysis = /* تحليل الفجوة + خطة الدفع — بلا قفل */;
// … رفض إن لم يكن ممكناً / إن احتاج push غير مؤكد …

return DB::transaction(function () use ($analysis, ...) {
    $this->lockService->lockUsers([...]);
    if (! $applyPush && ...) {
        $this->validationService->assertNoConflictingAppointment(...); // فقط بلا push
    }
    $this->pushService->executePushPlan($analysis['push_plan'], ...);    // ← خطة قديمة!
    ...
});
```

**بعد:**

```php
$lockedAnchorProviderId = (int) $anchor->provider_id;

// التحليل صار دالة تُستدعى مرتين
$analyse = fn (Appointment $anchor): array => $this->assertPlacementPossible(
    $this->analyseServicePlacement($anchor, ...),
    $applyPush
);

$analyse($anchor);   // ① مرة بلا قفل: رفض سريع + سؤال الـ push قبل أي قفل

return DB::transaction(function () use (...) {
    $this->lockService->lockUsers([$newProvider->id, $lockedAnchorProviderId]);

    $anchor->refresh();                         // ② نقرأ الموعد من جديد تحت القفل

    if (! $anchor->canAcceptNewService()) {     // ③ ربما أُلغي أو دُفع أثناء الانتظار
        throw new InvalidArgumentException(...);
    }

    if ((int) $anchor->provider_id !== $lockedAnchorProviderId) {
        // ④ نُقل لحلاق آخر أثناء الانتظار: القفل الذي نملكه على التقويم الخطأ
        throw new SlotUnavailableException(__('booking.time_slot_unavailable'));
    }

    $analysis = $analyse($anchor);              // ⑤ هذا التحليل هو الوحيد الذي يُنفَّذ
    ...
});
```

**لماذا نرفض في الخطوة ④ بدل أن نقفل الحلاق الجديد؟** لو قفلنا الآن حلاقاً إضافياً، فنحن نقفل خارج الترتيب المرتّب الذي يمنع الـ deadlock. حالة نادرة جداً (نقل موعد وإضافة خدمة له في نفس الثانية)، والرفض الآمن مع «حدّث الصفحة» أفضل من تعقيد القفل.

**أُضيفت دالتان مساعدتان** (نقلٌ للكود القائم دون تغيير في منطقه):
- `analyseServicePlacement()`: نفس الـ `if ($sameProvider) … else analyzeChildAdd(…)` الذي كان inline.
- `assertPlacementPossible()`: نفس فحص `is_possible` وبوابة الـ push (`PushRequiredException`).

إذا ظهرت الحاجة لـ push جديد تحت القفل لم يكن موجوداً قبله، يُرمى `PushRequiredException` من داخل المعاملة. لم يُكتب شيء بعد في تلك اللحظة، فتتراجع المعاملة بلا أثر ويرى الموظف نافذة التأكيد من جديد.

### 5.2 إنشاء موعد من Filament — قفل + إعادة كل الفحوص

**الملف:** [`app/Filament/Resources/Appointments/Pages/CreateAppointment.php`](../../app/Filament/Resources/Appointments/Pages/CreateAppointment.php#L107-L140)

```php
protected function handleRecordCreation(array $data): Model
{
    return DB::transaction(function () use ($data) {
        // 0. beforeCreate() فحص بلا معاملة وبلا قفل ⇒ جوابه لقطة فقط
        $formData = $this->collectFormData();

        app(BookingLockService::class)->lockUsers([
            $formData['provider_id'] ?? null,
            $formData['customer_id'] ?? null,    // للحد اليومي وقاعدة تداخل العميل
        ]);

        try {
            $this->performProfessionalValidations($formData);   // نفس الفحوص كاملة
        } catch (\InvalidArgumentException $e) {
            Notification::make()->danger()->...->send();
            $this->halt();      // Halt يمر عبر DB::transaction فيتراجع كل شيء
        }

        // 1. … الكتابة كما كانت …
    });
}
```

- **`beforeCreate()` بقي كما هو**، وهو الآن «رفض سريع» يعطي رسالة لطيفة في الحالة الشائعة.
- **`collectFormData()`** دالة صغيرة جديدة: بيانات النموذج الخام + صفوف الـ repeater. يستخدمها الفحص السريع والفحص تحت القفل، فلا يمكن أن يفحصا بيانات مختلفة.
- **لماذا `halt()` وليس رمي استثناء؟** صفحة Filament تلتقط `Halt` فقط. أي استثناء آخر كان سيظهر للأدمن كصفحة خطأ 500.

### 5.3 تعديل موعد من Filament — قفل + فحص عند الحفظ

**الملف:** [`app/Filament/Resources/Appointments/Pages/EditAppointment.php`](../../app/Filament/Resources/Appointments/Pages/EditAppointment.php#L58-L101)

```php
protected function handleRecordUpdate(Model $record, array $data): Model
{
    $data = $this->normaliseSchedule($record, $data);    // (انظر 5.3.2)

    return DB::transaction(function () use ($record, $data) {
        $originalProviderId = $record->getRawOriginal('provider_id');
        $wasOccupying = (clone $record)
            ->setRawAttributes($record->getRawOriginal())
            ->occupiesProviderTime();                     // هل كان يحجز وقتاً قبل التعديل؟

        $record->fill($data);

        $claimsNewTime = $record->occupiesProviderTime() && (
            ! $wasOccupying                               // إعادة تفعيل موعد ملغى
            || $record->isDirty(['provider_id', 'appointment_date', 'start_time', 'end_time'])
        );

        if ($claimsNewTime) {
            app(BookingLockService::class)->lockUsers([
                $originalProviderId,       // التقويم الذي يُترك
                $record->provider_id,      // التقويم الذي يُحجز
                $record->customer_id,
            ]);

            if (! $this->canForceBooking()) {
                $this->assertSlotIsFree($record);   // تعارض الحلاق + تعارض العميل
            }
        }

        $record->save();
        return $record;
    });
}
```

#### 5.3.1 متى يُفحص ومتى لا

| ما تغيّر في الحفظ | يُقفل ويُفحص؟ | لماذا |
|---|:-:|---|
| الحلاق / التاريخ / الساعة / المدة | ✅ | يحجز وقتاً جديداً |
| الحالة من ملغى ← `PENDING`/`COMPLETED` | ✅ | يعيد حجز وقت حُرِّر |
| الملاحظات / الأسعار / الدفع فقط | ❌ | لا شيء على التقويم يتغير |
| النتيجة موعد ملغى | ❌ | لا يحجز وقتاً |

**لماذا لا نفحص تعديل الملاحظات؟** لو فحصنا كل حفظ، فإن موعداً متداخلاً **عمداً** بصلاحية `force_booking` يصبح غير قابل للتعديل أبداً، حتى لتصحيح ملاحظة. وهناك اختبار يحرس هذا.

**لماذا نفحص تعارض العميل أيضاً؟** نفس قاعدة `createBooking` ([customer-overlap-rule](../06-booking-flow/customer-free-check.md)): العميل لا يكون في مكانين في نفس الوقت.

**`canForceBooking()`** تستخدم نفس الصلاحية التي يستخدمها StaffDashboard (`SuperAdmin` أو `StaffDashboard:force_booking`)، فيحصل نفس الشخص على نفس الجواب في المكانين.

**لماذا `$record->provider()->first()` وليس `$record->provider`؟** بعد `fill()` يكون `provider_id` الجديد، لكن العلاقة المحمّلة مسبقاً ما زالت تشير إلى الحلاق **القديم**. لو استخدمناها لفحصنا تقويم الحلاق الخطأ.

#### 5.3.2 خطأ جانبي أُصلح تلقائياً: التاريخ ينقلب إلى «اليوم»

حقل الساعة في النموذج يرسل الساعة وحدها (`"10:00:00"`)، والعمود `start_time` من نوع `datetime`. حين يحوّل Laravel ساعةً بلا تاريخ إلى datetime، يأخذ **تاريخ اليوم**. يعني تعديل موعد يوم 20 وحفظه كان قد ينقله إلى تاريخ اليوم. وكان `end_time` يبقى قديماً لأن الحقل المخفي لا يُحدَّث دائماً.

`normaliseSchedule()` يبني الوقت كما يفعل `CreateAppointment` تماماً:

```php
$date     = Carbon::parse($data['appointment_date'] ?? $record->appointment_date)->format('Y-m-d');
$time     = Carbon::parse($data['start_time'] ?? $record->start_time)->format('H:i:s');
$duration = (int) ($data['duration_minutes'] ?? $record->duration_minutes);

$start = Carbon::parse("{$date} {$time}");
$data['start_time'] = $start;
$data['end_time']   = $start->copy()->addMinutes($duration);
```

هذا ضروري للإصلاح وليس تحسيناً إضافياً: فحص التعارض يحتاج النافذة **الحقيقية** التي ستُحفظ، وإلا فإنه يفحص وقتاً ويكتب وقتاً آخر.

### 5.4 النموذج `Appointment` — تعريف واحد لـ «يحجز وقت الحلاق»

**الملف:** [`app/Models/Appointment.php`](../../app/Models/Appointment.php#L41)

صفحة التعديل تحتاج أن تسأل موعداً **في الذاكرة** (بتغييراته غير المحفوظة): «هل تحجز وقتاً؟». الـ scope الموجود يعمل على استعلامات فقط. لكي لا يصبح عندنا تعريفان يمكن أن يختلفا (وهذا بالضبط ما سبّب BOOK-01)، صار الاثنان يقرآن من ثابت واحد:

```php
public const PROVIDER_BLOCKING_STATUSES = [
    AppointmentStatus::PENDING,
    AppointmentStatus::COMPLETED,
];

public function scopeBlocksProviderTime(Builder $query): Builder   // للاستعلامات
{
    return $query->where('created_status', 1)
        ->whereIn('status', array_map(fn ($s) => $s->value, self::PROVIDER_BLOCKING_STATUSES));
}

public function occupiesProviderTime(): bool                        // لنموذج واحد في الذاكرة
{
    return (int) $this->created_status === 1
        && in_array($this->status, self::PROVIDER_BLOCKING_STATUSES, true);
}
```

اسم الدالة `occupiesProviderTime` وليس `blocksProviderTime` عن قصد: الاسم الثاني كان سيخطف استدعاء الـ scope الثابت `Appointment::blocksProviderTime()`.

### 5.5 `AppointmentCreationService` — تعليق تحذيري

**الملف:** [`app/Services/Appointments/AppointmentCreationService.php`](../../app/Services/Appointments/AppointmentCreationService.php)

بحثتُ في `app/` و`routes/` و`tests/`، ولا يوجد أي استدعاء لهذه الخدمة. كما اتفقنا، لم تُحذف ولم تُعدَّل، بل أُضيف تعليق فوق الكلاس يقول: كود ميت، وفيه نمط BOOK-02 (فحص قبل المعاملة بلا قفل)، وما يجب فعله قبل ربطه (القفل ثم إعادة `performProfessionalValidations()` داخل المعاملة كما في `CreateAppointment`).

### 5.6 التوثيق

[`docs/06-booking-flow/concurrency.md`](../06-booking-flow/concurrency.md) كان يقول «المسارات **الثلاثة**» ويغفل صفحتي Filament. صار جدولاً بالمسارات **الخمسة**، وأُضيف للعقد بندان:
- إعادة تفعيل موعد ملغى = كتابة على التقويم.
- أي **خطة** حُسبت قبل القفل (تحليل فجوة، push) تُحسب من جديد بعده.

وأُضيف سبب رفض القيد الفريد.

---

## 6. الاختبارات

**الملف:** [`tests/Feature/Booking/DoubleBookingRaceTest.php`](../../tests/Feature/Booking/DoubleBookingRaceTest.php)

### 6.1 المشكلة: كيف تختبر سباقاً بلا اتصالين؟

الاختبارات تعمل على SQLite في الذاكرة، باتصال واحد. وSQLite **يتجاهل** `FOR UPDATE` تماماً. لا يمكن إطلاق طلبين متوازيين حقاً.

### 6.2 الحل: «حقن المنافس» عند لحظة القفل

```php
function competitorOnLock(?callable $competitor = null): BookingLockService
{
    $spy = new class($competitor) extends BookingLockService {
        public array $calls = [];
        public function lockUsers(array $userIds): void
        {
            if ($this->calls === [] && $this->competitor) {
                ($this->competitor)();      // ← المنافس يحجز الآن
            }
            $this->calls[] = $userIds;
            parent::lockUsers($userIds);
        }
    };
    app()->instance(BookingLockService::class, $spy);
    return $spy;
}
```

المنطق: الجاسوس يُدخل حجزاً منافساً **في اللحظة التي يطلب فيها الكود القفل**. هذا يحاكي «طلب آخر انتهى قبل أن نحصل على القفل بلحظة». كل فحص جرى **قبل** هذه اللحظة رأى الفتحة فارغة. وحده الفحص **بعد** القفل يرى المنافس. يعني:
- مسار ما زال يعتمد على جوابه القديم ⇒ **يحجز مرتين** في الاختبار ⇒ يفشل.
- مسار مُصلح ⇒ **يرفض** ⇒ ينجح.

**ملاحظة على طريقة التأكيد:** المنافس يُكتب على نفس الاتصال وداخل معاملتنا، فعند الرفض يتراجع معها. لذلك التأكيد هو «الرفض حدث + العميل لم يحصل على أي موعد»، وليس «يوجد موعد واحد». على MySQL الحقيقي يكون المنافس طلباً منفصلاً مُثبَّتاً.

### 6.3 الاختبارات التسعة

| # | الاختبار | ماذا يحرس |
|---|---|---|
| 1 | `refuses an API booking when a competitor takes the slot just before the lock` | `createBooking` يفحص بعد القفل ⇒ 409 `slot_conflict` |
| 2 | `refuses a Filament-created appointment when a competitor takes the slot just before the lock` | صفحة الإنشاء تقفل الحلاق **والعميل** وتعيد الفحص |
| 3 | `refuses moving an appointment onto a taken slot from the Filament edit page` | صفحة التعديل لم تعد تكتب بلا فحص |
| 4 | `re-checks an edit-page move under the lock` | فحص التعديل يجري تحت القفل (مع منافس محقون) |
| 5 | `refuses reviving a cancelled appointment onto a slot someone else now holds` | إعادة تفعيل ملغى = حجز وقت جديد |
| 6 | `moves to a free slot and keeps the appointment on its own date` | خطأ «تاريخ اليوم» + إعادة حساب `end_time` |
| 7 | `saves a notes-only edit without locking, even on a deliberately overlapped appointment` | تعديل الملاحظات لا يقفل ولا يُرفض |
| 8 | `lets a force_booking holder overlap on purpose from the edit page` | صلاحية `force_booking` تعمل في Filament كما في StaffDashboard |
| 9 | `re-plans a push under the lock instead of executing a stale plan` | خطة الـ push تُعاد تحت القفل ⇒ المنافس يُدفع أيضاً ولا تداخل |

الاختبار 9 يتحقق من **الثابت نفسه** وليس من التفاصيل: دالة `overlappingPairs()` تعدّ كل زوج مواعيد متداخلة لنفس الحلاق في قاعدة البيانات، ويجب أن يكون العدد صفراً.

---

## 7. نتائج التحقق

```
php artisan test tests/Feature/Booking/DoubleBookingRaceTest.php
  ✓ 9 passed (47 assertions)

php artisan test
  Tests: 18 failed, 3 skipped, 615 passed (2276 assertions)
```

الفشل الـ 18 هو **نفس الخط الأساسي** الموثّق قبل هذا العمل، وكله خارج الحجز:

| الملف | العدد |
|---|---|
| `Fiskaly\FiskalyClientTest` / `FiskalyServiceTest` / `TransactionServiceTest` | 5 |
| `PhoneNumberValidationTest` | 6 |
| `ProfileImageUploadTest` | 3 |
| `AuthRateLimitTest` · `DeleteAccountTest` · `ExampleTest` · `PasswordRequirementsTest` | 4 |

لم يفشل أي اختبار حجز قديم: `Step4BookingCreationTest` و`MultiServiceSplitTest` و`CustomerOverlapTest` و`AppointmentDeletionTest` كلها ناجحة.

---

## 8. ما لم يُتحقَّق منه

### 8.1 السباق الحقيقي على MySQL — **لم يُشغَّل**

كُتب سكربت للسباق الحقيقي: [`tests/Concurrency/book02_mysql_race.php`](../../tests/Concurrency/book02_mysql_race.php). يطلق 8 عمليات PHP منفصلة، لكل منها اتصال MySQL خاص، تتسابق على نفس الفتحة في نفس اللحظة. ولكي يكون السباق مؤكداً وليس صدفة، ينام كل عامل 300ms **بين** «الفحص قال فارغ» و«الكتابة».

يشغّل سيناريوين:
- **`locked`** (الكود الحالي): المتوقع حجز واحد فقط، والباقي `SLOT_CONFLICT`.
- **`unlocked`** (القفل مُعطَّل = السلوك القديم): المتوقع عدة حجوزات على نفس الفتحة. هذا **الضابط**: يثبت أن السكربت قادر على رؤية الحجز المزدوج، فتصبح نتيجة `locked` النظيفة ذات معنى.

**الأمان:** يعمل فقط على قاعدة اسمها `barberbooking_race_test` ينشئها بنفسه ويعمل عليها `migrate:fresh`. ويرفض العمل إن كان الاتصال يشير لأي قاعدة أخرى، أو إن كانت الإعدادات مخزّنة مؤقتاً (config cache). البريد والـ SMS والـ push معطّلة فيه.

**لماذا لم يُشغَّل:** MySQL في XAMPP، وهو الذي يحوي قاعدة `barberbooking`، ينهار عند التشغيل (القسم 9.1). وتقرّر تخطي الاختبار.

**للتشغيل لاحقاً** (على أي خادم MySQL سليم، حتى لو كان فارغاً):

```bash
php tests/Concurrency/book02_mysql_race.php --workers=8 --delay-ms=300
```

رمز الخروج `0` = القفل صمد. إذا لم يُظهر سيناريو `unlocked` حجزاً مزدوجاً، يطلب السكربت زيادة `--delay-ms`.

### 8.2 التأكد من فشل الاختبارات على الكود القديم — **لم يُنفَّذ آلياً**

حاولتُ تشغيل الاختبارات الجديدة على الكود القديم بـ `git stash`. فشلت العملية في منتصفها لأن ملفاً كان مقفلاً من المحرر، وتركت شجرة العمل في حالة مختلطة. **استُعيدت كل الملفات** من الـ index، وتحققتُ منها سطراً سطراً (`diff`) قبل المتابعة. لم أُعد المحاولة.

بالتحليل، الاختبارات **يجب** أن تفشل على الكود القديم:
- **الاختبار 2:** الكود القديم لا يستدعي `lockUsers` إطلاقاً ⇒ `$spy->calls` فارغة ⇒ `toHaveCount(1)` تفشل، والموعد يُنشأ.
- **3 و4 و5:** الكود القديم لا يفحص شيئاً ⇒ الحفظ ينجح ⇒ `assertNotified` تفشل.
- **6:** الكود القديم يحفظ الساعة على تاريخ اليوم.
- **9:** الكود القديم ينفّذ الخطة القديمة (دفع موعد واحد إلى 12:00 فوق المنافس) ⇒ `pushed_appointments` = 1 و`overlappingPairs` = 1.
- **الاختبار 1** وحده كان **سينجح** على الكود القديم، لأن `createBooking` كان مُصلحاً من قبل. هو اختبار حراسة ضد التراجع، وليس دليلاً على هذا الإصلاح.

---

## 9. ملاحظات جانبية ظهرت أثناء العمل

### 9.1 ⚠️ خادم MySQL في XAMPP — **يحتاج انتباهك**

`C:\xampp\mysql\data` يحوي قاعدة `barberbooking`. عند التشغيل، يبدأ MariaDB 10.4 ثم ينهار، والسجل `mysql_error.log` مليء بـ:

```
[ERROR] InnoDB: Page [...] log sequence number 847401649 is in the future!
        Current system log sequence number 206442006.
[ERROR] InnoDB: Your database may be corrupt or you may have copied the InnoDB
        tablespace but not the InnoDB log files.
```

هذا يحدث عادة حين يستخدم خادمان مختلفان نفس ملفات البيانات، أو حين تنفصل ملفات الـ redo log عن ملفات البيانات (نسخ جزئي، إغلاق غير نظيف). **لم ألمس شيئاً.** قبل أي محاولة إصلاح: **انسخ المجلد `C:\xampp\mysql\data` كاملاً** إلى مكان آمن. ثم راجع [InnoDB recovery modes](https://mariadb.com/kb/en/library/innodb-recovery-modes/).

### 9.2 خطأ في نموذج إنشاء الموعد (Filament) — **لم يُصلح**، خارج النطاق

في [`AppointmentForm.php`](../../app/Filament/Resources/Appointments/Schemas/AppointmentForm.php)، حقل **السعر** داخل صف الخدمة (repeater) يستدعي `self::calculateTotals($get, $set)`. هذه الدالة مكتوبة لمستوى النموذج كله، لكنها تُستدعى هنا بمسارات نسبية **داخل الصف**:
- `$get('services_record')` داخل الصف يعطي `null`، فيكون المجموع 0.
- `$set('duration_minutes', 0)` يكتب على مدة **الصف نفسه**.

**الأثر المتوقع:** تعديل سعر خدمة في معالج الإنشاء يصفّر مدتها. اكتُشف أثناء كتابة الاختبار 2، والاختبار يتجاوزه بإعادة المدة يدوياً. يستحق تذكرة مستقلة.

### 9.3 تاريخ «اليوم» في صفحة التعديل — **أُصلح** ضمن هذا العمل

انظر 5.3.2.

---

## 10. قائمة الملفات المعدّلة

| الملف | التغيير |
|---|---|
| `app/Services/BookingService.php` | `addServiceToBooking`: إعادة قراءة الـ anchor + إعادة التحليل تحت القفل؛ دالتان مساعدتان `analyseServicePlacement` و`assertPlacementPossible` |
| `app/Filament/Resources/Appointments/Pages/CreateAppointment.php` | قفل + `performProfessionalValidations` داخل `handleRecordCreation`؛ `collectFormData()` |
| `app/Filament/Resources/Appointments/Pages/EditAppointment.php` | `handleRecordUpdate` جديد: `normaliseSchedule` + قفل + فحص الحلاق والعميل + `force_booking` |
| `app/Models/Appointment.php` | `PROVIDER_BLOCKING_STATUSES` + `occupiesProviderTime()`؛ الـ scope يقرأ من الثابت |
| `app/Services/Appointments/AppointmentCreationService.php` | تعليق تحذيري فقط |
| `tests/Feature/Booking/DoubleBookingRaceTest.php` | **جديد**: 9 اختبارات |
| `tests/Concurrency/book02_mysql_race.php` | **جديد**: سباق MySQL حقيقي (لم يُشغَّل) |
| `docs/06-booking-flow/concurrency.md` | المسارات الخمسة + العقد المحدَّث |
| `SECURITY_AUDIT_2026-08-29.md` | BOOK-02 موسوم ✅ مُصلحة + التصحيحات |
| `docs/fixes/BOOK-02_double_booking_toctou.md` | هذا التقرير |

**لا migrations ولا تغيير في قاعدة البيانات.** النشر = نشر الكود فقط.

---

## 11. القاعدة لأي كود جديد

> أي كود يكتب `provider_id` أو `appointment_date` أو `start_time` أو `end_time` لموعد، **أو يعيد موعداً ملغى إلى حالة تحجز الوقت**:

```php
DB::transaction(function () {
    app(BookingLockService::class)->lockUsers([$providerId, $customerId]);   // ① أولاً

    // ② كل فحص وكل «خطة» تُحسب هنا، بعد القفل
    app(BookingValidationService::class)->assertNoConflictingAppointment(...);

    // ③ ثم الكتابة
});
```

الفحص قبل المعاملة مسموح، لكنه **رفض سريع لرسالة لطيفة فقط، وليس ضماناً.**

والمبدأ وراء هذا كله: **لا يمكن قفل «غياب» شيء.** حين تريد حماية «لا يوجد حجز هنا»، اقفل شيئاً موجوداً دائماً يمر به كل من يريد الكتابة. هنا هو صف الحلاق، وفي `MON-03` كان صف العدّاد.
