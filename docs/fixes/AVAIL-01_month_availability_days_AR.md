# AVAIL-01 — أيام التوفر الشهرية + تطبيق إغلاق الصالون: شرح المهمة والتنفيذ

> **التاريخ:** 2026-10-24
> **الـ Commit:** `2ebe56f` (مدموج في نفس الـ commit مع عمل إيميل التذكير، وهو غير متعلق بهذه المهمة)
> **النتيجة:** 35 اختباراً جديداً ناجحاً. الحزمة كاملة: 684 ناجحاً و18 فشلاً **قديماً مطابقاً للخط الأساسي** (Fiskaly، PhoneNumberValidation، رفع الصورة، حذف الحساب، صفحة الترحيب، عرض كلمة السر، rate limit الدخول). لا يوجد فشل جديد.
> **مراجع مرتبطة:** [`API.md`](../../API.md) (القسمان 4 و5 من Availability) · [دليل Flutter](../API/AVAILABILITY_MONTH_DAYS_FLUTTER_GUIDE.md) · [`05-api/availability.md`](../05-api/availability.md) · [`04-services/availability-service.md`](../04-services/availability-service.md)

---

## الفهرس

1. [الملخص](#1-الملخص)
2. [فهم المهمة](#2-فهم-المهمة)
3. [ما وجدناه قبل التعديل](#3-ما-وجدناه-قبل-التعديل)
4. [القرارات المتفق عليها](#4-القرارات-المتفق-عليها)
5. [التصميم: محرك واحد لليوم والشهر](#5-التصميم-محرك-واحد-لليوم-والشهر)
6. [التعديلات ملفاً ملفاً](#6-التعديلات-ملفاً-ملفاً)
7. [تتبّع طلب واحد من البداية للنهاية](#7-تتبّع-طلب-واحد-من-البداية-للنهاية)
8. [تغيّرات في السلوك يجب معرفتها](#8-تغيّرات-في-السلوك-يجب-معرفتها)
9. [الاختبارات](#9-الاختبارات)
10. [النشر](#10-النشر)
11. [حدود معروفة وما لم يُنفَّذ](#11-حدود-معروفة-وما-لم-يُنفَّذ)
12. [كيف تضيف قاعدة توفر جديدة مستقبلاً](#12-كيف-تضيف-قاعدة-توفر-جديدة-مستقبلاً)
13. [فهرس الملفات](#13-فهرس-الملفات)

---

## 1. الملخص

### الطلب
العميل (الصالون) طلب أن يعرض التطبيق **أيام التوفر** بدل أن يجرّب الزبون يوماً يوماً:

1. **API 1:** يُرسَل **خدمة + مزوّد** فيُرجَع أيام الشهر التي فيها موعد فارغ عند هذا المزوّد.
2. **API 2:** تُرسَل **خدمة فقط** فيُرجَع أيام الشهر المتوفرة، ومع كل يوم **من المزوّدون المتاحون فيه**.
3. العرض **شهراً بشهر**، ويستطيع الزبون الانتقال للشهر التالي.
4. العطل والإجازات **لا تظهر كأيام متوفرة**.

### ماذا بُني

| | Endpoint | يُرجع |
|---|---|---|
| API 1 | `GET /api/availability/days?service_id&provider_id&month=YYYY-MM` | كل أيام الشهر، ولكل يوم: `is_available` + `reason_code` + عدد الأوقات + أول وقت |
| API 2 | `GET /api/availability/days/providers?service_id&month&branch_id` | كل أيام الشهر، ولكل يوم: المزوّدون المتاحون (اسم، صورة، سعر، عدد الأوقات، أول وقت) |

### وما تغيّر معه
- **إغلاق الصالون** (`salon_schedules.is_open = false`) صار قاعدة حقيقية في **الحجز** وفي **كل** endpoints التوفر. قبل هذا التعديل كان مجرد إعداد لا يقرؤه أحد.
- **محرك توفر واحد** يخدم الـ endpoints اليومية القديمة والشهرية الجديدة. يحمّل بيانات الشهر كله دفعة واحدة، فيحتاج **~10 استعلامات** مهما كان عدد المزوّدين والأيام.
- **إصلاح عدم تطابق قديم:** التوفر كان يعرض مزوّداً بلا دور `provider` وخدمة غير نشطة، والحجز يرفضهما.
- **`max_booking_days` رُفع إلى 60 يوماً** عبر migration، حتى يتسع العرض الشهري للشهر الحالي والذي بعده.

---

## 2. فهم المهمة

### شجرة الفهم

```
"يوم متوفر" = يوجد فيه وقت واحد على الأقل يقبله POST /api/bookings فعلاً
│
├── شروط على مستوى اليوم
│     ├── التاريخ ليس في الماضي، وليس بعد today + max_booking_days
│     ├── الصالون (فرع المزوّد) مفتوح في هذا اليوم من الأسبوع      ← جديد
│     ├── المزوّد يعمل هذا اليوم من الأسبوع (provider_scheduled_works)
│     └── المزوّد ليس في إجازة يوم كامل (provider_time_offs FULL_DAY)
│
├── شروط على مستوى الوقت (هل بقي وقت واحد؟)
│     ├── الإجازات الساعية (بما فيها الممتدة على عدة أيام)
│     ├── الحجوزات القائمة (Appointment::scopeBlocksProviderTime)
│     └── لا وقت قبل now + book_buffer
│
└── شروط على مستوى المزوّد/الخدمة
      ├── المستخدم نشط ويحمل دور provider
      ├── رابط provider_service نشط
      └── الخدمة نشطة وغير محذوفة
```

### المبدأ الحاكم
> **أي يوم يُعرض متوفراً يجب أن ينجح الحجز فيه.**
> لذلك يجب أن يستعمل التوفر **نفس** قواعد `BookingValidationService`، لا نسخة موازية منها. النسخ الموازية هي التي أنتجت مشكلة `BOOK-01` قديماً: كانت تُعرض فتحة ثم يُرفض حجزها.

---

## 3. ما وجدناه قبل التعديل

| # | الاكتشاف | الأثر |
|---|---|---|
| 1 | `GET /api/availability/calendar` موجود. مع `provider_id` يقترب من API 1 | لكنه بدون `provider_id` يُرجع **عدد** الفتحات فقط بلا أسماء، فـ API 2 غير موجود |
| 2 | `/calendar` يحسب كل يوم لكل مزوّد بـ ~4 استعلامات | شهر × 8 مزوّدين ≈ **1000 استعلام** في طلب واحد |
| 3 | التوفر لا يفحص دور `provider` ولا `services.is_active`، والحجز يفحصهما | يمكن عرض مزوّد/خدمة ثم رفض الحجز |
| 4 | `salon_schedules` (جدول أسبوعي لكل فرع فيه `is_open`) يُدار من Filament، لكن **لا الحجز ولا التوفر يقرآنه**. يقرؤه فقط منتقي المزوّدين في `AppointmentForm` | إغلاق الصالون يوم الأحد لم يكن يمنع الحجز من التطبيق |
| 5 | لا يوجد في النظام إغلاق **بتاريخ** (عطلة رسمية مثل 25/12) | خارج النطاق، انظر القسم 11 |
| 6 | `max_booking_days` محلياً = **1** (الافتراضي في الكود 10) | العرض الشهري كان سيظهر شبه فارغ |
| 7 | `get_setting()` يُنفّذ استعلاماً في كل استدعاء | حساب شهر يوماً بيوم كان سيكرّر قراءة الإعدادات مئات المرات |

---

## 4. القرارات المتفق عليها

| # | السؤال | القرار | أثره في الكود |
|---|---|---|---|
| 1 | نافذة الحجز تتعارض مع العرض الشهري | **نحترمها دائماً** ونرفعها إلى 60 | `monthWindow()` + migration البيانات |
| 2 | شكل الرد | **كل الأيام مع السبب + قائمة مختصرة بالمتوفر** | `days[]` + `available_dates[]` |
| 3 | بيانات المزوّد في API 2 | **ملخص بدون الأوقات** (اسم، صورة، سعر، عدد، أول وقت) | `getServiceMonthAvailability()` |
| 4 | العطل | **تطبيق `salon_schedules`** على الحجز والتوفر | `SalonSchedule::isClosedFor()` |
| 5 | الإغلاق لكل الفروع أم لفرع؟ | **صف الفرع، وإلا الصف العام `branch_id = NULL`** | ترتيب الأولوية في `isClosedFor()` |
| 6 | هل يتجاوز الحجز القسري الإغلاق؟ | **نعم، مثل الإجازات** | الفحص داخل `validateProviderScheduleWindow()` |
| 7 | بارامتر الشهر | **`month=YYYY-MM`**، والافتراضي الشهر الحالي | `monthWindow()` |
| 8 | ساعات فتح الصالون | **`is_open` فقط**، والساعات للعرض | `isClosedFor()` لا يقرأ `open_time/close_time` |

**لماذا `is_open` فقط؟** ورديات المزوّدين تحدد الساعات أصلاً. لو قُصّت الوردية على ساعات الصالون المسجّلة، فأي وردية تتجاوزها ستخسر فتحات بصمت دون أن يعرف أحد السبب.

---

## 5. التصميم: محرك واحد لليوم والشهر

```
                        ┌────────────────────────────────────────┐
  طلب يومي (قديم)  ───▶ │ loadCalendarData(providers, from, to)  │ ◀─── طلب شهري (جديد)
  from = to = اليوم     │  - الإعدادان (مرة واحدة)               │      from..to = الجزء
                        │  - جداول العمل      (استعلام واحد)     │      القابل للحجز من الشهر
                        │  - الإجازات         (استعلام واحد)     │
                        │  - الحجوزات المانعة (استعلام واحد)     │
                        │  - salon_schedules  (استعلام واحد)     │
                        └───────────────────┬────────────────────┘
                                            │ array $calendar
                                            ▼
                        ┌────────────────────────────────────────┐
                        │ getProviderDayAvailability(            │
                        │   provider, service, date, $calendar)  │  ◀── كل القواعد هنا فقط
                        │  1 نافذة الحجز                          │
                        │  2 إغلاق الصالون                        │
                        │  3 يوم عمل                              │
                        │  4 إجازة يوم كامل                       │
                        │  5 generateTimeSlots()                  │
                        └────────────────────────────────────────┘
```

- **قبل:** كل يوم يُحمّل بياناته بنفسه، فكلفة الشهر = يوم × مزوّد × 4 استعلامات.
- **بعد:** التحميل مرة واحدة للنطاق كله، ثم القرار لكل يوم **في الذاكرة** بنفس الدالة. النتيجة: **نفس القواعد** و**كلفة ثابتة**.

**المسارات اليومية القديمة** (`/availability/service` و`/availability/provider`) تمرّ الآن من نفس المحرك بنطاق يوم واحد، فاستفادت تلقائياً من قاعدة الإغلاق ومن إصلاح شروط المزوّد.

---

## 6. التعديلات ملفاً ملفاً

### 6.1 `app/Models/SalonSchedule.php` — قاعدة الإغلاق الوحيدة

```php
public static function isClosedFor(?int $branchId, int $dayOfWeek, ?Collection $schedules = null): bool
{
    $schedules ??= static::query()
        ->where('day_of_week', $dayOfWeek)
        ->where(fn ($query) => $query
            ->whereNull('branch_id')
            ->when($branchId, fn ($query) => $query->orWhere('branch_id', $branchId)))
        ->get();

    $forDay = $schedules->where('day_of_week', $dayOfWeek);

    $row = ($branchId ? $forDay->firstWhere('branch_id', $branchId) : null)
        ?? $forDay->first(fn (self $schedule) => $schedule->branch_id === null);

    return $row !== null && ! $row->is_open;
}
```

**كيف تُقرأ:**

| الوضع في `salon_schedules` لهذا اليوم من الأسبوع | النتيجة |
|---|---|
| صف لفرع المزوّد | **هو الحَكَم** (`is_open` يقرر) |
| لا صف للفرع، يوجد صف `branch_id = NULL` | الصف العام يقرر |
| لا صفوف أبداً | **مفتوح**: لا قيد، وجدول المزوّد وحده يقرر (السلوك القديم) |
| المزوّد بلا فرع (`branch_id = NULL`) | الصف العام فقط |

**لماذا البارامتر الثالث `$schedules`؟** الجدول صغير جداً (7 صفوف لكل فرع)، فالمسار الشهري يحمّله **مرة** بـ `SalonSchedule::all()` ويمرّره. أما الحجز فيستدعي الدالة بلا بارامتر لتجلب صفوفها بنفسها. **منطق واحد واستخدامان.**

---

### 6.2 `app/Models/ProviderTimeOff.php` — نطاق الإجازات لفترة

```php
public function scopeCoveringDate(Builder $query, string $date): Builder
{
    return $query->touchingRange($date, $date);
}

public function scopeTouchingRange(Builder $query, string $from, string $to): Builder
{
    return $query
        ->whereDate('start_date', '<=', $to)
        ->whereRaw('COALESCE(end_date, start_date) >= ?', [$from]);
}
```

- `coveringDate()` (المستعمل في الحجز) صار **حالة خاصة** من `touchingRange()`، فالقاعدة واحدة.
- **`COALESCE(end_date, start_date)`** يحافظ على قاعدة `BOOK-04`: `end_date = NULL` يعني إجازة يوماً واحداً. لو كتبنا `end_date >= ?` لسقطت هذه الصفوف بصمت، لأن `NULL >= x` في SQL قيمته `UNKNOWN`.
- **لماذا لم نستعمل `scopeOverlapping()` الموجود؟** لأنه يقارن `end_date` مباشرة، فيقع في الفخ نفسه.
- **أي شريحة من اليوم تحجبها الإجازة؟** يبقى ذلك من مسؤولية `blockedWindowOn($date)` الموجودة، وهي تُستدعى لكل يوم في الذاكرة.

---

### 6.3 `app/Services/BookingValidationService.php` — الحجز يحترم الإغلاق

داخل `validateProviderScheduleWindow()`، **قبل** فحص جدول المزوّد:

```php
// 0. The salon itself is closed that weekday (salon_schedules.is_open).
//    Inside this window on purpose: force booking may open a closed day
//    exactly as it may override a leave.
if (SalonSchedule::isClosedFor($provider->branch_id, $dayOfWeek)) {
    throw new InvalidArgumentException(__('booking.salon_closed'));
}
```

**لماذا هنا بالذات؟** هذه الدالة هي "نافذة توفر المزوّد" التي يتجاوزها الحجز القسري (`bypass_availability`) **كوحدة واحدة** (انظر `validateTimeSlotAvailability()`). وضعُ الفحص داخلها يحقق القرار 6 دون أي `if` إضافي. وفحوص التعارض والماضي تبقى خارجها، فلا يتجاوزها أحد.

**من يمرّ من هنا؟** كل مسارات الكتابة:
- `BookingService` (الـ API)
- `AppointmentCreationService` (StaffDashboard)
- `CreateAppointment` و`AppointmentForm` في Filament

فقاعدة الإغلاق تغطيها كلها **بسطر واحد**.

---

### 6.4 `app/Services/ServiceAvailabilityService.php` — قلب التعديل

#### (أ) ثوابت جديدة

```php
public const REASON_SALON_CLOSED = 'salon_closed';

// Day-level reasons used only by the month views.
public const REASON_PAST = 'past';
public const REASON_NO_AVAILABILITY = 'no_availability';
```

#### (ب) `bookableProviders()` — من يقبله الحجز فعلاً

```php
private function bookableProviders(Service $service, ?int $branchId = null, ?int $providerId = null): Collection
{
    if (! $service->is_active) {
        return collect();
    }

    return $service->activeProviders()                     // pivot نشط + users.is_active
        ->whereHas('roles', fn ($query) => $query->where('roles.name', 'provider'))
        ->when($branchId, fn ($query) => $query->where('users.branch_id', $branchId))
        ->when($providerId, fn ($query) => $query->where('users.id', $providerId))
        ->with('branch')
        ->get();
}
```

- هذه **نفس شروط** `BookingValidationService::validateProviderOffersService()`. الدور ونشاط الخدمة كانا غائبين في التوفر.
- الأعمدة مؤهلة باسم الجدول (`users.branch_id`) لأن `users` و`provider_service` كليهما فيه `is_active`، وMySQL يرفض العمود الغامض.
- `with('branch')` يمنع N+1 عند تنسيق الفرع.
- النتيجة تحمل **`$provider->pivot->custom_price`**، فيُحسب السعر بلا استعلام إضافي لكل مزوّد.

#### (ج) `loadCalendarData()` — التحميل دفعة واحدة

```php
private function loadCalendarData(Collection $providers, Carbon $from, Carbon $to): array
{
    $providerIds = $providers->pluck('id')->all();

    return [
        'last_bookable_date' => $this->lastBookableDate(),     // إعداد واحد، مرة واحدة
        'earliest_start' => $this->earliestBookableTime(),     // now + book_buffer
        'schedules' => ProviderScheduledWork::whereIn('user_id', $providerIds)
            ->where('is_work_day', true)
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->groupBy('user_id'),
        'time_offs' => ProviderTimeOff::whereIn('user_id', $providerIds)
            ->touchingRange($from->format('Y-m-d'), $to->format('Y-m-d'))
            ->get()
            ->groupBy('user_id'),
        'appointments' => Appointment::whereIn('provider_id', $providerIds)
            ->whereDate('appointment_date', '>=', $from)
            ->whereDate('appointment_date', '<=', $to)
            ->blocksProviderTime()
            ->select('provider_id', 'appointment_date', 'start_time', 'end_time')
            ->get()
            ->groupBy(fn (Appointment $appointment) =>
                $appointment->provider_id . '|' . $appointment->appointment_date->format('Y-m-d')),
        'salon_schedules' => SalonSchedule::all(),
    ];
}
```

| المفتاح | الشكل | لماذا هكذا |
|---|---|---|
| `schedules` | `[provider_id => Collection]` | يُبحث فيها بـ `firstWhere('day_of_week', …)`. `orderBy('id')` يجعل الصف المكرّر يُحسم كما يحسمه `->first()` في الحجز |
| `time_offs` | `[provider_id => Collection]` | كل إجازات النطاق، والفرز لكل يوم يتم في الذاكرة |
| `appointments` | `["provider_id|Y-m-d" => Collection]` | بحث مباشر عن حجوزات مزوّد في يوم |
| `salon_schedules` | `Collection` | تُمرَّر لـ `isClosedFor()` |

**`blocksProviderTime()`** هو نفس الـ scope الذي يستعمله الحجز في `assertNoConflictingAppointment()`، فتعريف "المزوّد مشغول" واحد في الطبقتين.

#### (د) `getProviderDayAvailability()` — القواعد بترتيبها

```php
private function getProviderDayAvailability(User $provider, Service $service, Carbon $date, ?array $calendar = null): array
{
    $calendar ??= $this->loadCalendarData(collect([$provider]), $date, $date);

    // 1. نافذة الحجز
    if ($date->gt($calendar['last_bookable_date'])) { return $this->unavailable(REASON_OUTSIDE_BOOKING_WINDOW, …); }

    // 2. إغلاق الصالون — نفس ترتيب validateProviderScheduleWindow()
    if (SalonSchedule::isClosedFor($provider->branch_id, $dayOfWeek, $calendar['salon_schedules'])) { … REASON_SALON_CLOSED … }

    // 3. يوم عمل
    $workSchedule = $calendar['schedules']->get($provider->id, collect())->firstWhere('day_of_week', $dayOfWeek);
    if (!$workSchedule) { … REASON_NOT_WORKING_DAY … }

    // 4. الإجازات التي تلمس هذا اليوم
    $leaves = $calendar['time_offs']->get($provider->id, collect())
        ->filter(fn (ProviderTimeOff $timeOff) => $timeOff->blockedWindowOn($date) !== null);

    $fullDayTimeOff = $leaves->first(fn ($timeOff) => $timeOff->isFullDay());
    if ($fullDayTimeOff) { … REASON_ON_LEAVE … }

    // 5. الفتحات
    $slots = $this->generateTimeSlots(
        $date, $workSchedule, $serviceDuration,
        $calendar['appointments']->get($provider->id . '|' . $date->format('Y-m-d'), collect()),
        $leaves->filter(fn ($timeOff) => $timeOff->isHourly()),
        $calendar['earliest_start']
    );
    if (empty($slots)) { … REASON_FULLY_BOOKED … }

    return ['is_available' => true, 'reason_code' => REASON_AVAILABLE, 'available_slots' => $slots, …];
}
```
(مختصر للعرض. الكود الكامل في الملف.)

- **`$calendar ??=`**: إن لم يُمرَّر شيء (المسار اليومي) يحمّل يوماً واحداً لمزوّد واحد. لهذا **لم يتغيّر توقيع أي دالة عامة قديمة**.
- **`blockedWindowOn($date) !== null`** هو النصف اليومي من `coveringDate()`. الصفوف حُمّلت للنطاق كله، وهذا السطر يُبقي ما يلمس اليوم فقط.
- **الإجازة الساعية ذات الوقت الناقص** (بيانات معطوبة) يُرجِع لها `blockedWindowOn()` القيمة `null`، فلا تحجب شيئاً. هذا مطابق للسلوك القديم.

#### (هـ) `generateTimeSlots()` — صارت دالة صافية

```php
private function generateTimeSlots(
    Carbon $date,
    ProviderScheduledWork $workSchedule,
    int $serviceDuration,
    Collection $existingAppointments,   // كانت تُستعلَم هنا
    Collection $hourlyTimeOffs,         // كانت تُستعلَم هنا
    Carbon $earliestStart               // كانت تقرأ book_buffer هنا
): array
```

الخوارزمية **لم تتغير**: شبكة `shift_start + k × duration`، ويُحذف ما قبل `now + book_buffer`، وما يتقاطع مع حجز أو إجازة ساعية. التغيير الوحيد أن **المدخلات تأتي جاهزة** بدل أن تستعلم الدالة عنها بنفسها. لذلك حُذفت الدوال الخاصة `getFullDayTimeOff()` و`getProviderAppointments()` و`getProviderHourlyTimeOffs()` و`providerOffersService()`.

#### (و) `monthWindow()` — حدود الشهر

```php
$start = $month === null
    ? $today->copy()->startOfMonth()
    : Carbon::createFromFormat('!Y-m', $month);      // '!' يصفّر اليوم والوقت → يوم 1، 00:00

if ($start->lt($today->copy()->startOfMonth()) || $start->gt($lastBookableDate->copy()->startOfMonth())) {
    throw new InvalidArgumentException(__('booking.month_outside_booking_window'));
}

return [
    'start' => $start,                                  // أول الشهر
    'end'   => $start->copy()->endOfMonth()->startOfDay(),
    'from'  => ($start->lt($today) ? $today : $start)->copy(),                   // أول يوم يُحسب
    'to'    => ($end->gt($lastBookableDate) ? $lastBookableDate : $end)->copy(), // آخر يوم يُحسب
    …
];
```

```
     start                from                       to                  end
       │── past ──────────│──── يُحسب فعلاً ─────────│── outside_window ──│
       1                 اليوم              last_bookable_date           30/31
```

**لماذا يُرفض الشهر الخارج كلياً بـ 422 بدل إرجاع 30 يوماً معطّلاً؟** لأن التطبيق يأخذ `next_month` و`previous_month` من الرد، فلا سبب مشروعاً لطلب شهر خارج النافذة. الرفض يمنع طلبات لا معنى لها.

**`.copy()` في `from` و`to`** تمنع مشاركة كائن `Carbon` واحد بين حقلين، فلا يتغير أحدهما إذا عُدّل الآخر لاحقاً.

#### (ز) `eachMonthDay()` — كل أيام الشهر

```php
for ($date = $window['start']->copy(); $date->lte($window['end']); $date->addDay()) {
    $resolved = match (true) {
        $date->lt($window['from']) => ['is_available' => false, 'reason_code' => self::REASON_PAST] + $emptyDay,
        $date->gt($window['to'])   => ['is_available' => false, 'reason_code' => self::REASON_OUTSIDE_BOOKING_WINDOW] + $emptyDay,
        default                    => $resolve($date->copy()),
    };
    $days[] = $baseFields + $resolved;
}
```

- أيام ما قبل `from` وما بعد `to` **لا تُحسب أصلاً**، فلا كلفة لها.
- `$emptyDay` هو الحقول الفارغة الخاصة بكل API. في API 1: `available_slots_count: 0` و`first_available_slot: null`. في API 2: `providers_count: 0` و`providers: []`. بهذا **يبقى شكل كل يوم ثابتاً** مهما كانت حالته.

#### (ح) `monthSummary()` — حقول التنقل

| الحقل | القاعدة |
|---|---|
| `previous_month` | الشهر السابق إن كان الشهر المعروض بعد الشهر الحالي، وإلا `null` |
| `next_month` | الشهر التالي إن كان الشهر المعروض قبل شهر `last_bookable_date`، وإلا `null` |
| `available_dates` | تواريخ الأيام التي `is_available = true` |

#### (ط) الدالتان العامتان الجديدتان

**`getProviderMonthAvailability()`** (API 1):
```php
$provider = $this->bookableProviders($service, providerId: $providerId)->first();

if (! $provider) {
    throw new InvalidArgumentException(__('booking.provider_unavailable_for_service'));
}
```
**رسالة واحدة** للمزوّد غير الموجود، وغير النشط، وبلا دور، والذي لا يقدّم الخدمة. هذه نفس سياسة `rejectProviderServicePair()` في الحجز (`AUTHZ-05`)، لأن رسائل مختلفة كانت ستسمح بتعداد معرّفات المستخدمين.

**`getServiceMonthAvailability()`** (API 2) — كيف يُختصر سبب اليوم:
```php
$allClosed = $reasons !== [] && array_unique($reasons) === [self::REASON_SALON_CLOSED];

'reason_code' => match (true) {
    $available !== [] => self::REASON_AVAILABLE,
    $allClosed        => self::REASON_SALON_CLOSED,
    default           => self::REASON_NO_AVAILABILITY,
},
```
**لماذا `salon_closed` فقط يُسمّى؟** هو السبب الوحيد الذي يخص **الجميع** ويستطيع الزبون التصرف بناءً عليه ("تعال يوماً آخر"). أي خليط آخر من الأسباب (هذا في إجازة وذاك ممتلئ) لا يفيد الزبون، فيُختصر إلى `no_availability`.

**السعر يُحسب مرة لكل مزوّد** (لا لكل يوم)، ثم يُعاد استخدامه داخل حلقة الأيام.

#### (ي) `servicePricing()` — بدل `getProviderServicePricing()`

نفس الحساب تماماً، لكنه يأخذ `custom_price` من الـ pivot المحمّل بدل استعلام جديد. والـ pivot نشط بالضرورة لأن `activeProviders()` لا تُرجع إلا الروابط النشطة، وهذا هو الشرط الذي كان الاستعلام القديم يفرضه.

---

### 6.5 `app/Http/Controllers/Api/AvailabilityController.php`

```php
public function getProviderMonthDays(Request $request): JsonResponse
{
    $request->validate([
        'service_id'  => ['required', 'integer', $this->activeServiceRule()],
        'provider_id' => 'required|integer',            // ← ليس exists:users عمداً
        'month'       => 'nullable|date_format:Y-m',
    ]);

    return $this->monthResponse(fn () => $this->availabilityService->getProviderMonthAvailability(…));
}

private function activeServiceRule(): Exists
{
    return Rule::exists('services', 'id')
        ->whereNull('deleted_at')
        ->where('is_active', true);
}
```

| النقطة | السبب |
|---|---|
| `validate()` **خارج** `try` | `ValidationException` يجب أن يصل لمعالج Laravel ليصبح 422 بشكله المعتاد، لا 500 |
| `provider_id` بلا `exists:users` | لو استُعمل لصار "غير موجود" خطأ validation، و"لا يقدّم الخدمة" خطأ آخر، فيمكن التمييز بينهما (تعداد) |
| `whereNull('deleted_at')` | `exists:` يستعلم الجدول خاماً فيطابق المحذوف ناعماً، ثم `findOrFail` لا يراه فيصير 404/500 (فخ `BOOK-09`) |
| `where('is_active', true)` | الخدمة غير النشطة يرفضها الحجز، فلا نعرض لها أياماً |

**`monthResponse()`** هو الغلاف المشترك للـ endpoint الاثنين:

| الاستثناء | الرد |
|---|---|
| — | `200 {success: true, data, message}` |
| `InvalidArgumentException` | `422 {success: false, message}`: رفض منطقي برسالة مترجمة |
| `\Throwable` (أي شيء آخر) | `Log::error` باسم الصنف، ثم `500` برسالة عامة **بلا تفاصيل داخلية** |

**لماذا `\Throwable` وليس `\Exception`؟** لأن `TypeError` يرث `Error` لا `Exception`، فكان سيفلت من الـ catch (درس `BOOK-09`).

---

### 6.6 `routes/api.php`

```php
Route::get('/availability/days', [AvailabilityController::class, 'getProviderMonthDays'])
    ->middleware('throttle:30,1')
    ->name('api.availability.days');
Route::get('/availability/days/providers', [AvailabilityController::class, 'getServiceMonthDays'])
    ->middleware('throttle:30,1')
    ->name('api.availability.days.providers');
```

- **عامّان** (بلا `auth`) مثل باقي endpoints التوفر، لأن الزائر يتصفح قبل تسجيل الدخول.
- **30 طلباً في الدقيقة لكل IP**، نفس حد `/calendar`: الحساب صار رخيصاً، لكن الرد ما زال يغطي شهراً كاملاً.

---

### 6.7 الترجمات — `lang/{ar,de,en}/booking.php`

| المفتاح | ar | de | en |
|---|---|---|---|
| `salon_closed` | الصالون مغلق في التاريخ المطلوب. | Der Salon ist am gewünschten Datum geschlossen. | The salon is closed on the requested date. |
| `month_outside_booking_window` | لا يمكن الحجز في هذا الشهر. | Für diesen Monat sind keine Buchungen möglich. | This month cannot be booked. |

اللغة تُحدَّد بـ `SetApiLocale` من `?lang=` أو من هيدر `Accept-Language`.

---

### 6.8 `database/migrations/2026_10_24_000001_raise_max_booking_days_for_month_view.php`

```php
$row = DB::table('salon_settings')->where('key', 'max_booking_days')->first();

if ($row === null) {
    // إنشاء الصف بـ 60 (الافتراضي في الكود 10 قصير أيضاً)
}

if ((int) json_decode((string) $row->value) < self::DAYS) {
    // رفع إلى 60
}
```

- **migration بيانات فقط، وللأعلى فقط**: إن ضبطت الإدارة قيمة ≥ 60 تبقى كما هي.
- **`json_encode(60)`** لأن `salon_settings.value` عمود JSON-cast، والـ seeder يخزّن رقماً.
- **`down()` فارغ عمداً**: القيمة السابقة غير محفوظة، وتقليص النافذة عند التراجع قد يترك حجوزات أنشأها الزبائن خارجها.
- **محلياً:** القيمة تغيّرت من `1` إلى `60` بعد التشغيل.

---

### 6.9 `tests/Feature/ProviderAvailabilityLeaveTest.php`

```php
Role::findOrCreate('provider', 'web');
$this->provider = User::factory()->create(['is_active' => true]);
$this->provider->assignRole('provider');
```

الاختبار القديم كان ينشئ "مزوّداً" **بلا دور `provider`**، والحجز يرفض هذا المزوّد أصلاً. بعد توحيد الشروط صار التوفر يرفضه أيضاً، فصُحّحت بيانات الاختبار لتطابق الواقع. **لم يتغير أي assertion.**

---

## 7. تتبّع طلب واحد من البداية للنهاية

بيانات الاختبار (`SalonFixture`): الآن **الإثنين 2026-09-07 08:00**، و`max_booking_days = 10`، والمزوّد `Available` يعمل **الأربعاء** 09:00–17:00، والخدمة 60 دقيقة.

```
GET /api/availability/days?service_id=1&provider_id=5&month=2026-09
│
├─ validate(): service نشطة ✓ | month بصيغة Y-m ✓
│
├─ getProviderMonthAvailability()
│   ├─ bookableProviders(service, providerId: 5) → المزوّد + pivot + branch
│   ├─ monthWindow('2026-09')
│   │     start = 09-01   end = 09-30
│   │     from  = 09-07 (اليوم)   to = 09-17 (07 + 10)
│   ├─ loadCalendarData([5], 09-07, 09-17)          ← استعلامات ثابتة
│   └─ eachMonthDay():
│         09-01 … 09-06  → past                     (بلا حساب)
│         09-07 (Mon)    → getProviderDayAvailability → not_working_day
│         09-08 (Tue)    → not_working_day
│         09-09 (Wed)    → available, 8 فتحات، أول 09:00
│         …
│         09-16 (Wed)    → available
│         09-17 (Thu)    → not_working_day
│         09-18 … 09-30  → outside_booking_window   (بلا حساب)
│
└─ 200 { month: "2026-09", previous_month: null, next_month: null,
         last_bookable_date: "2026-09-17",
         available_dates: ["2026-09-09", "2026-09-16"], days: [30 عنصراً] }
```

**لو كان صف `salon_schedules` لفرع المزوّد يوم الأربعاء `is_open = false`:** يصير 09-09 و09-16 بالسبب `salon_closed`، وتصير `available_dates = []`. وأي `POST /api/bookings` على الأربعاء يُرجع `422 booking.salon_closed`، إلا عبر الحجز القسري.

---

## 8. تغيّرات في السلوك يجب معرفتها

| # | التغيير | من يتأثر |
|---|---|---|
| 1 | **حجز عادي في يوم إغلاق الصالون يُرفض** (422) | الـ API، وStaffDashboard، وFilament. الحجز القسري ما زال يسمح. **الحجوزات الموجودة لم تُمس** |
| 2 | `/availability/service` لم يعد يعرض مستخدماً بلا دور `provider`، ولا شيئاً لخدمة غير نشطة | الـ API: صار يطابق ما يقبله الحجز |
| 3 | `/availability/provider` لمزوّد غير نشط/بلا دور/لخدمة غير نشطة يُرجع `400 Provider does not offer this service` | كان يعرض فتحات يرفضها الحجز |
| 4 | كل endpoints التوفر قد تُرجع `reason_code = salon_closed` | التطبيق: قيمة جديدة، ويجب معاملة أي قيمة غير معروفة كـ "غير متوفر" |
| 5 | `DashboardService::getAvailableSlotsForProvider()` (يستدعي المسار اليومي) لا يعرض فتحات في يوم الإغلاق | StaffDashboard: متسق مع رفض الحجز |
| 6 | `max_booking_days = 60` بعد الـ migration | الزبون يستطيع الحجز حتى 60 يوماً مقدماً |

---

## 9. الاختبارات

### الملف الجديد: `tests/Feature/Booking/MonthAvailabilityTest.php` — 35 اختباراً

يعتمد على `SalonFixture`: صالون فيه مزوّد لكل حالة (متاح، في إجازة، لا يعمل، ممتلئ، غير نشط، رابط معطّل، فرع آخر، لا يقدّم الخدمة، إجازة ساعية).

| المجموعة | ماذا تثبت |
|---|---|
| **API 1 — الأساسيات** | الأيام المتوفرة، و30 يوماً كاملة، وسبب كل يوم، وعدد الفتحات وأولها، والشهر الافتراضي |
| **الإجازات** | يوم كامل، و`end_date = NULL` (يوم واحد)، والإجازة الساعية الممتدة تحجب اليوم الأوسط كله، والساعية تُنقص الفتحات فقط |
| **الحجوزات والوقت** | يوم ممتلئ ← `fully_booked`، و`book_buffer` يؤخر أول فتحة، واليوم الحالي بعد انتهاء الدوام ← `fully_booked` |
| **إغلاق الصالون** | صف الفرع يغلق، والصف العام يغلق كل الفروع، وصف الفرع يتغلب على العام، وإغلاق فرع آخر لا يؤثر |
| **التنقل** | لا جيران حين تتسع النافذة لشهر، والتنقل عبر 3 أشهر مع `max_booking_days = 60`، و422 للشهر الماضي والبعيد، والصيغة الخاطئة |
| **من يُسأل عنه** | رسالة واحدة لكل مزوّد غير قابل للحجز (5 حالات)، والخدمة غير النشطة والمحذوفة ← 422 لا 500 |
| **API 2** | من المتاح كل يوم، وملخص بلا فتحات، و`providers_count`، وفلتر الفرع، وعدم ظهور مستخدم بلا دور، و`salon_closed` فقط حين يُغلق للجميع |
| **الأداء** | عدد الاستعلامات **متساوٍ** قبل إضافة 5 مزوّدين وبعدها، وأقل من 20 |
| **التطابق مع الحجز** ⭐ | `first_available_slot` لكل يوم متوفر يُحجز فعلاً عبر `POST /api/bookings` ← `201` (للـ API الاثنين) |
| **الحجز والإغلاق** | الحجز في يوم مغلق ← 422 `salon_closed`، و`bypassAvailability: true` يمرّره |

### النتائج

| التشغيل | النتيجة |
|---|---|
| الملف الجديد | **35/35** ✅ |
| `tests/Feature/Booking` + اختبارات الإجازات (بعد إعادة الهيكلة وقبل الاختبارات الجديدة) | **213/213** ✅، فإعادة الهيكلة لم تكسر شيئاً |
| الحزمة كاملة | **684 ناجحاً، 18 فاشلاً، 3 متخطاة**. الـ 18 هي الخط الأساسي القديم نفسه |

### فحص يدوي على بيانات محلية حقيقية
- API 1 لشهر كامل: **10 استعلامات**.
- `next_month` يتسلسل حتى `last_bookable_date = 2026-12-23` (اليوم + 60).

### تشغيلها
```bash
php artisan test tests/Feature/Booking/MonthAvailabilityTest.php
php artisan test tests/Feature/Booking tests/Feature/ProviderAvailabilityLeaveTest.php
```

---

## 10. النشر

```bash
php artisan migrate   # يشغّل 2026_10_24_000001_raise_max_booking_days_for_month_view
```

- لا تغيير في بنية الجداول (لا DDL)، بيانات فقط.
- **بعد النشر:** راجع شاشة جدول الصالون في Filament (`ManageSalonSchedules`). أي يوم `is_open = false` صار **يمنع الحجز فعلاً**. تأكد أن الإعدادات تعكس الواقع قبل أن يصطدم بها الزبائن.
- التطبيق: الدليل في [`docs/API/AVAILABILITY_MONTH_DAYS_FLUTTER_GUIDE.md`](../API/AVAILABILITY_MONTH_DAYS_FLUTTER_GUIDE.md).

---

## 11. حدود معروفة وما لم يُنفَّذ

| البند | الوضع |
|---|---|
| **عطل بتاريخ** (25/12، عيد…) | **غير موجودة**. `salon_schedules` أسبوعي فقط. تحتاج جدولاً جديداً + شاشة Filament + سطراً في `getProviderDayAvailability()` وفي `validateProviderScheduleWindow()` (انظر القسم 12) |
| ساعات فتح الصالون | غير مطبّقة، بقرار متفق عليه (القسم 4 / القرار 8) |
| `/availability/calendar` القديم | باقٍ للتوافق ولم يُحسَّن أداؤه (يستدعي المسار اليومي لكل يوم). الشاشات الجديدة تستعمل `/days*` |
| `getEffectiveDuration()` | ما زال يُرجع `duration_minutes` قبل كود ميت يقرأ `custom_duration`. **لم يُلمس**، فهو موثّق كمشكلة قديمة في `BOOKING_FLOW.md` |
| تحذير `DEPRECATED` في `getAvailabilityCalendar()` | قديم: بارامتر اختياري قبل إلزامي في التوقيع. لم يُلمس للحفاظ على التوافق |
| `API.md` يذكر 60/دقيقة لـ `/service` و`/provider` | الـ route الفعلي 40/دقيقة. تناقض قديم في التوثيق، خارج هذه المهمة |
| `day_name` و`month_name` | بالإنجليزية دائماً. التطبيق يترجمها من التاريخ |

---

## 12. كيف تضيف قاعدة توفر جديدة مستقبلاً

مثال: إضافة **عطل بتاريخ**. القاعدة الذهبية: **مكانان، لا أكثر، ونفس الدالة في الاثنين.**

1. **القاعدة** على الموديل كدالة واحدة تقبل مجموعة محمّلة مسبقاً، على نمط `SalonSchedule::isClosedFor()`:
   ```php
   SalonClosure::closes(?int $branchId, Carbon $date, ?Collection $rows = null): bool
   ```
2. **الحجز:** سطر داخل `validateProviderScheduleWindow()` (فيتجاوزه الحجز القسري تلقائياً)، أو خارجها إن كان يجب ألا يُتجاوز.
3. **التوفر:**
   - التحميل في `loadCalendarData()` (استعلام واحد للنطاق، **لا** داخل حلقة الأيام).
   - الفحص في `getProviderDayAvailability()` بنفس الترتيب الموجود في الحجز.
   - `reason_code` جديد كثابت في `ServiceAvailabilityService`.
4. **الاختبار:** حالة في `MonthAvailabilityTest` + تأكد أن اختبار عدد الاستعلامات ما زال يمر.
5. **التوثيق:** جدول `reason_code` في `API.md` ودليل Flutter.

**ما لا يجب فعله:** فحص القاعدة في الـ controller، أو داخل `generateTimeSlots()`، أو باستعلام لكل يوم. كل واحد من هذه يكسر إما التطابق مع الحجز أو الكلفة الثابتة.

---

## 13. فهرس الملفات

| الملف | نوع التغيير |
|---|---|
| `app/Models/SalonSchedule.php` | + `isClosedFor()` |
| `app/Models/ProviderTimeOff.php` | + `scopeTouchingRange()`؛ `scopeCoveringDate()` يفوّض إليه |
| `app/Services/BookingValidationService.php` | + الفحص 0 (إغلاق الصالون) |
| `app/Services/ServiceAvailabilityService.php` | إعادة هيكلة + دالتان عامتان جديدتان |
| `app/Http/Controllers/Api/AvailabilityController.php` | + `getProviderMonthDays()` و`getServiceMonthDays()` و`activeServiceRule()` و`monthResponse()` |
| `routes/api.php` | + مساران |
| `lang/{ar,de,en}/booking.php` | + مفتاحان |
| `database/migrations/2026_10_24_000001_…` | migration بيانات |
| `tests/Feature/Booking/MonthAvailabilityTest.php` | جديد، 35 اختباراً |
| `tests/Feature/ProviderAvailabilityLeaveTest.php` | إسناد دور `provider` في الإعداد |
| `API.md` | القسمان 4 و5 + `salon_closed` + حدود الطلبات |
| `docs/API/AVAILABILITY_MONTH_DAYS_FLUTTER_GUIDE.md` | جديد: دليل مطوّر التطبيق |
| `docs/05-api/availability.md`، `docs/04-services/availability-service.md`، `docs/BOOKING_FLOW.md` | تحديث |
