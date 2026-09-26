# مشكلة الفجوة الزمنية في الحجز متعدد الخدمات — تحليل جذري وخيارات الحل

> **التاريخ:** 2026-09-25
> **الحالة المبلغ عنها:** عميل حجز خدمتين (09:40 + 15:10) فظهر موعد واحد من 09:40 حتى 15:10 وحجب يوم المزود كاملاً
> **الصور:** `Meine Termine` (موبايل) + `StaffDashboard Timeline` (ويب)
> **الخطورة:** عالية — حجب كاذب للتقويم + خسارة حجوزات + تجربة عميل مكسورة
> **✅ الحالة:** مُصلَحة 2026-09-25 بالحل 3 — الخطة: [`BOOKING-GAP-01_plan_AR.md`](BOOKING-GAP-01_plan_AR.md) — شرح التنفيذ: [`BOOKING-GAP-01_implementation_AR.md`](BOOKING-GAP-01_implementation_AR.md)

---

## 1. ملخص تنفيذي (TL;DR)

النظام يسمح في `POST /api/bookings` بإرسال **وقت بدء مستقل لكل خدمة** (`services.*.start_time`)، ويقبل **أي فجوة بين الخدمات مهما كبرت**، ثم يخزنها كلها في **صف `appointments` واحد** بفاصل واحد:

```php
start_time = أول خدمة.start  // 09:40
end_time   = آخر خدمة.end    // 15:10 (+ المدة)
```

النتيجة في حالة العميل:

| البند | القيمة |
|---|---|
| الخدمة 1 | `Beard Trim & Shape` — 20m — بداية 09:40 — نهاية 10:00 |
| الخدمة 2 | `Men's Haircut` — 35m — بداية ~14:35 / 15:10 — نهاية 15:10 / 15:45 |
| `duration_minutes` المخزن (مجموع المدد) | **55 دقيقة** |
| `end_time - start_time` (النافذة المحجوبة فعلياً) | **~330 دقيقة (5.5 ساعات)** |
| وقت مهدور محجوب بلا عمل | **~275 دقيقة (~83% من الكتلة)** |

أي أن المزود يظهر مشغولاً من 09:40 حتى 15:10 لعمل حقيقي 55 دقيقة. الصورتان تعرضان نفس الصف من زاويتين: الموبايل يعرض `time_range`، والداشبورد يرسم `timelineBlock` بنفس `start/end`.

**هذا ليس خطأ عرض. الصف نفسه مخزن هكذا في DB.**

---

## 2. ما الذي حدث بالضبط في حالة العميل؟

### 2.1 الصورة الأولى (تطبيق العميل — `Meine Termine`)

- البطاقة تعرض `Pending` + `09:40 AM - 03:10 PM`.
- المصدر: `app/Http/Resources/AppointmentResource.php:21-23` يعرض `start_time` و`end_time` من صف `appointments`، و`time_range` من `app/Models/Appointment.php:287-290`.
- تحته الخدمتان بمدديهما الصحيحتين (20m + 35m) من `services_record` — أي أن **تفاصيل الخدمات صحيحة، لكن غلاف الموعد خطأ**.
- العميل اختار وقتين متباعدين ظناً منه أنه يحجز موعدين (صباحاً وعصراً)، والنظام دمجها في موعد واحد.

### 2.2 الصورة الثانية (لوحة الموظفين — `StaffDashboard`)

- عمود `Aisha Al Zaabi` فيه كتلة صفراء واحدة من 09:40 حتى 15:10 بعرض اليوم كله.
- المصدر: `app/Livewire/StaffDashboard.php:1434` → `getTimelineDataFromProviders()` يمرر `start_time/end_time` للـ Blade، و`timelineBlockStyle(offset, duration)` في `resources/views/livewire/staff-dashboard.blade.php` يرسم الارتفاع من `end - start`.
- هذه الكتلة **تحجب السحب `drag-to-create` والقراءة** لبقية اليوم، وتجعل اليوم يبدو محجوزاً وهو فارغ من 10:00 حتى ~14:30.

### 2.3 ملاحظة عن "9:70" في بلاغ العميل

العميل كتب "من الساعة 9:70 لغاية 3:10" — لا توجد دقيقة 70، والمقصود 09:40 (بداية أول خدمة) حتى 15:10 (نهاية/بداية الخدمة الثانية). الفرق 35 دقيقة بين 15:10 المعروضة و15:45 المتوقعة (15:10 + 35m) يعود لاحتمالين ولا يغير الجوهر:

1. الخدمة الثانية بدأت 14:35 (35m) وانتهت 15:10 — والعميل اختصرها ذهنياً إلى "3:10"، أو
2. الخدمة الثانية بدأت 15:10 فعلاً والنهاية الحقيقية 15:45 لكن العرض/البلاغ أغفلها.

في الحالتين الفجوة ~4.5–5.5 ساعات وهي جوهر المشكلة.

---

## 3. السبب الجذري في الكود (Root Cause)

### 3.1 العقد الحالي: كل خدمة لها `start_time` مستقل

`app/Http/Requests/Api/BookingCreateRequest.php` يشترط `services.*.start_time` بصيغة `H:i` لكل خدمة. لا يوجد أي قيد بأن تكون الخدمات متلاصقة.

### 3.2 الترتيب لا يصلح الفجوة، فقط يعيد ترتيبها

`app/Services/BookingService.php:184-191` — `sortServicesByStartTime()`:

```php
usort($services, fn($a,$b) => strcmp($a['start_time'], $b['start_time']));
```

يرتب 15:10 بعد 09:40 لكنه **لا يعدل أي وقت**.

### 3.3 الفحص الوحيد يمنع التداخل، ويسمح بأي فجوة

`app/Services/BookingValidationService.php:123-141` — `validateSequentialTiming()`:

```php
if ($currentStartTime->lt($previousEndTime)) throw ... // يمنع التداخل فقط
if ($currentStartTime->diffInMinutes($previousEndTime) > 120) {
    // You can add a warning or log here  ← لا شيء يحدث فعلاً
}
```

أي فجوة `>= 0` مقبولة: 5 دقائق أو 5 ساعات، نفس الحكم. لا رفض، لا تحذير، لا log.

### 3.4 التخزين: نافذة واحدة تمتد على الفجوة + مجموع مدد منفصل عنها

`app/Services/BookingService.php:129-136`:

```php
'start_time' => Carbon::parse($date.' '.$firstService['start_time']),
'end_time'   => Carbon::parse($date.' '.$preparedServices[count-1]['end_time']),
'duration_minutes' => $totals['total_duration'], // = array_sum المدد = 55
```

فينتج صف متناقض ذاتياً:

```
duration_minutes = 55
end - start      = 330
```

كل قارئ لاحق يختار واحداً منهما فيكذب على الآخر: التقويم والحجوزات يستخدمان `end-start`، والإحصائيات والفواتير قد تستخدم `duration_minutes`.

### 3.5 لماذا لم تمنعه طبقة التوفر؟

`app/Services/ServiceAvailabilityService.php:363-433` تولد `slots` **لخدمة واحدة في كل مرة** (`getAvailableSlotsByDate` / `getProviderAvailableSlotsByDate`). لا توجد دالة "توفر حزمة خدمات". التطبيق يعرض فتحتين صحيحتين كل على حدة (09:40 متاحة للخدمة 1، و15:10 متاحة للخدمة 2)، والعميل يجمعهما في طلب واحد فيقبله `createBooking`. الفحصان يعملان على مستوى مختلف ولا يلتقيان.

### 3.6 مفارقة `GapAnalysisService`: القاعدة موجودة لكن في المسار الخطأ

`app/Services/GapAnalysisService.php:31` يفرض:

```php
public const MAX_GAP_MINUTES = 60;
```

لكن هذا يُستخدم **فقط في مسار إضافة خدمة لحجز قائم** (`addServiceToBooking` — `analyzeAddBefore/After/ChildAdd`). مسار الإنشاء `createBooking()` لا يستدعيه أبداً. أي أن النظام يعرف أن الفجوة يجب أن تُحدّ (60 دقيقة) لكنه نسي تطبيقها عند الإنشاء.

---

## 4. ماذا تسبب هذه المشكلة؟ (الأضرار)

### 4.1 حجب كاذب للمزود — خسارة إيراد مباشرة (الأخطر)

تعريف "المزود مشغول" الوحيد هو `app/Models/Appointment.php:460-480`:

```php
scopeBlocksProviderTime() + scopeOverlapping([start,end))
```

بما أن `start/end` يغطيان الفجوة، فكل استعلام تعارض في `BookingValidationService.php:200-216` (`assertNoConflictingAppointment`) يرفض أي حجز جديد بين 10:00 و15:10 بـ `409 slot_conflict` رغم أن الكرسي فارغ. النتيجة: يوم يظهر `fully_booked` وهو فارغ ~83% منه.

### 4.2 تجربة عميل مكسورة وفقدان ثقة

بطاقة `Meine Termine` تعرض 5.5 ساعات لعمل 55 دقيقة. العميل لا يعرف متى يحضر فعلاً (09:40؟ 15:10؟ كلاهما؟)، وقد يلغي أو لا يحضر (`NO_SHOW`) أو يفتح تذكرة دعم. زر `Buchung stornieren` يلغي الخدمتين معاً حتى لو أراد إلغاء واحدة.

### 4.3 شلل لوحة الموظفين

- كتلة واحدة عملاقة تخفي بقية اليوم، تمنع `drag-to-create`، وتجعل السحب/التعديل (`updateAppointment()` في `app/Livewire/StaffDashboard.php:625`) خطيراً: أي تعديل للمدة يكتب على `start/end` للصف كله ويرقع أول `services_record` فقط (`StaffDashboard.php:679-682`) — فيزيد الفساد.
- الـ `polling` كل 3 ثوانٍ يعيد رسم نفس الكتلة بلا فائدة.

### 4.4 تناقض `duration_minutes ≠ end-start` يكسر التقارير

- `formatted_duration` (من 55) يقول شيئاً و`time_range` (من 330) يقول عكسه — يظهران معاً في نفس API response (`AppointmentResource.php:21-25`).
- أي إحصائية تحسب ساعات العمل/الإشغال من `end-start` (timeline) ستضخم إنتاجية المزود ~6x؛ وأي واحدة تحسب من `duration_minutes` ستعرض رقماً مختلفاً لنفس الموعد. `DashboardStatsService` وتقرير Z عرضة لهذا الانقسام.

### 4.5 ع bug مضاعف عند اختلاف المزودين (أخطر تقنياً)

`BookingService.php:132` يخزن:

```php
'provider_id' => $firstService['provider_id'], // الأول فقط
```

فإذا كانت الخدمتان لمزودين مختلفين:

- المزود الأول يُحجب طوال النافذة **بما فيها وقت الخدمة الثانية التي سينفذها شخص آخر**.
- المزود الثاني **لا يُحجب أبداً** (لا صف باسمه)، فيمكن حجزه مرتين في نفس الوقت — حجز مزدوج حقيقي لا كاذب.

في حالة العميل الحالية المزود واحد فلم يظهر هذا الوجه، لكنه موجود في نفس السطور وسيظهر فور أول حجز مختلط المزودين بفجوة.

### 4.6 فحص العميل الحر غير متسق مع فحص المزود

`assertCustomerIsFree()` في `BookingValidationService.php:363-401` يُفحص **لكل خدمة على حدة** (التعليق في `BookingService.php:257-260` يقول عمداً حتى لا تُحجب الفجوة على العميل)، بينما فحص المزود يُفحص على **النافذة الكاملة بما فيها الفجوة**. أي أن العميل يستطيع حجز موعد آخر وسط الفجوة عند مزود آخر، بينما لا يستطيع أي عميل آخر حجز نفس المزود في نفس الفجوة. قاعدتان متعاكستان لنفس الفجوة.

### 4.7 الفاتورة سليمة سعرياً لكن مضللة زمنياً

`calculateTotals()` تجمع الأسعار صح (46€)، والفاتورة المسودة (`createDtaftInvoiceFromAppointment`) سليمة مالياً. لكن أي إيصال يعرض المدة/النطاق يعرض بيانات متناقضة، وإلغاء/دفع الموعد (`InvoiceFinalizationService`) ينهي 5.5 ساعات ككتلة واحدة بلا تفكيك.

---

## 5. قائمة الحلول — كل حل وتأثيره على النظام

### الحل 1 — رفض الفجوة عند الإنشاء (تشديد `validateSequentialTiming`) — **مقترح كإصلاح فوري**

**الفكرة:** أي طلب فيه `currentStart != previousEnd` (أو بفارق يتجاوز سماح صغير مثل 0–15 دقيقة) يُرفض بـ `422` ورسالة واضحة. الفجوة الكبيرة خطأ إدخال، وليست حالة عمل.

```php
// BookingValidationService::validateSequentialTiming()
$gap = $previousEndTime->diffInMinutes($currentStartTime);
if ($gap > $maxAllowedGap) throw new InvalidArgumentException(...);
```

**التأثير على النظام:**

| الطبقة | التأثير |
|---|---|
| `BookingValidationService` | تغيير صغير وموضعي؛ إضافة ثابت `MAX_GAP_MINUTES_CREATE` (يُفضل توحيده مع `GapAnalysisService::MAX_GAP_MINUTES = 60` أو تشديد أكثر 0/15). |
| `BookingService::createBooking` | بلا تغيير — يستفيد تلقائياً. |
| `API / Mobile` | **كسر متوافق جزئياً**: طلبات كانت تُقبل (بفجوة) ستُرفض. يجب تحديث التطبيق ليمنع اختيار وقت ثانٍ بعيد، أو ليرسل خدمتين كحجزين منفصلين. يحتاج تنسيق نسخ API + رسالة خطأ مترجمة (`lang/*/booking.php`). |
| `StaffDashboard` (Alpine `booking` object) | يجب إضافة فحص لحظي يمنع حفظ صفين متباعدين، أو زر "حجزان منفصلان". وإلا سيرى الموظف `422` بعد التعبئة. |
| `Availability` | بلا تغيير. |
| `DB / Migration` | لا شيء. |
| البيانات القديمة | الصف المبلغ عنه يبقى فاسداً — يحتاج إصلاح يدوي (إلغاء وإعادة حجز موعدين، أو تقسيم — راجع §6). |
| الاختبارات | إضافة `tests/Feature/Booking/MultiServiceGapTest.php`: فجوة 0 مقبولة، 15 مقبولة/مرفوضة حسب العتبة، 330 مرفوضة. |

**إيجابيات:** أصغر diff، يحافظ على نموذج "صف واحد = نافذة واحدة متصلة"، يمنع كل الأضرار المستقبلية، متوافق مع فلسفة `GapAnalysisService` الموجودة.
**سلبيات:** لا يصلح الماضي، ويكسر طلبات الموبايل القديمة حتى تُحدّث، ولا يدعم حالة "عميل يريد فعلاً موعدين في يوم واحد" إلا كحجزين منفصلين (وهو الصحيح أصلاً).

---

### الحل 2 — التجاهل المحسوب: السيرفر يلصق الخدمات تلقائياً (Auto-chain) — **الأسهل على الموبايل**

**الفكرة:** يُعتمد فقط `services[0].start_time` من العميل، وكل خدمة لاحقة تبدأ عند `previousEnd` تلقائياً. `start_time` الثاني المرسل يُتجاهل (أو يُستخدم للتحقق فقط).

```php
// BookingService::validateAndPrepareServices()
$startTime = ($index === 0)
    ? Carbon::parse($date.' '.$serviceData['start_time'])
    : $previousEndTime->copy();
```

**التأثير:**

| الطبقة | التأثير |
|---|---|
| `BookingService` | تغيير متوسط؛ `validateSequentialTiming` تصبح تحصيل حاصل. يجب توثيق أن `start_time` للخدمات 2..N مهمل. |
| `API / Mobile` | **متوافق كلياً**: لا كسر — التطبيق القديم يستمر بالإرسال والنتيجة تصبح صحيحة (09:40 + 20m + 35m = 09:40–10:35). لكن سلوك مفاجئ: عميل اختار 15:10 سيجد موعده 10:00 دون تفسير — يحتاج رسالة توضيحية في الـ response. |
| `StaffDashboard` | بلا تغيير إجباري. |
| `Availability` | يجب أن تعرض التوفر للحزمة كاملة (مجموع المدد من أول فتحة)، وإلا تُعرض 09:40 متاحة للخدمة 1 بينما الحزمة الممتدة 55m تتعارض مع حجز 10:15 — فحص لاحق يرفض ما عُرض. |
| `DB` | لا شيء. |

**إيجابيات:** لا أخطاء 422 جديدة، إصلاح صامت لمعظم الحالات، منطقي لصالون (خدمات متتالية على نفس الكرسي).
**سلبيات:** يسلب العميل نية حقيقية محتملة (أراد فعلاً وقتين منفصلين)، يخفي خطأ واجهة بدل إظهاره، لا يصلح حجوزات المزودين المختلفين (خدمتان متوازيتان لا متتاليتين)، ويحتاج تغيير عقد API موثق (`docs/BOOKING_FLOW.md` + `API.md`).

---

### الحل 3 — التقسيم: حجز واحد لكل كتلة زمنية، مربوط بأب/أبناء (Parent/Child) — **الأصح معمارياً**

**الفكرة:** منع دمج نافذتين متباعدتين في صف واحد أصلاً. كل خدمة (أو كل مجموعة متصلة) تصبح `Appointment` مستقلاً بنافذته الضيقة، مربوطة عبر `parent_appointment_id` الموجود أصلاً، والفاتورة الموحدة تُبنى على الأب عبر `InvoiceService::rebuildAggregatedInvoice()` (تعمل اليوم لمزودين مختلفين — تُوسّع لتشمل نفس المزود بفجوة).

**التأثير:**

| الطبقة | التأثير |
|---|---|
| `BookingService::createBooking` | تغيير كبير: يُرجع مجموعة بدل صف واحد (أو أب + أبناء). عقد الـ response يتغير (`AppointmentResource` مفرد → مجموعة/فاتورة موحدة). نسخ API (`v1/v2`) أو حقل `group_id`. |
| `Appointment` + `AppointmentLinkingService` | توسيع شروط `validateChildCandidate` للسماح بفجوة كبيرة لنفس المزود؛ فحوص `blocksProviderTime` تبقى صحيحة لأن كل صف نافذته ضيقة. يصلح تلقائياً bug المزودين المختلفين (§4.5). |
| `Invoice / Payment` | جاهز بنسبة كبيرة (`rebuildAggregatedInvoice` + `finalizeAppointmentPayment` يدعمان المجموعة) — يحتاج اختبار دفع/إلغاء جزئي (إلغاء خدمة واحدة دون الأخرى — غير ممكن اليوم). |
| `Mobile` | تغيير متوسط-كبير: شاشة `Meine Termine` تعرض بطاقتين مرتبطتين بدل بطاقة عملاقة؛ الإلغاء الفردي يصبح ممكناً (ميزة). |
| `StaffDashboard` | بطاقتان صغيرتان بدل كتلة عملاقة — تحسن فوري للقراءة والسحب. `cancelAppointment` يحتاج منطق "إلغاء طفل دون الأب" (موجود جزئياً: إعادة بناء الفاتورة عند إلغاء طفل `StaffDashboard.php:883-891`). |
| `Stats / Z-Report` | تصبح صحيحة تلقائياً (كل صف مدته = نافذته). |
| `DB` | لا migration إجباري (البنية موجودة)؛ migration بيانات لتفكيك الصفوف القديمة الموبوءة. |

**إيجابيات:** النموذج الوحيد الصحيح دلالياً (موعدان = صفان)، يصلح كل الأضرار بما فيها bug المزودين، يفتح ميزة الإلغاء الفردي، متوافق مع اتجاه النظام (الأب/الأبناء + فاتورة موحدة).
**سلبيات:** أكبر تكلفة تطوير واختبار، تغيير عقد API، يحتاج ترحيل بيانات + تنسيق موبايل/داشبورد معاً. يُنصح به كحل استراتيجي لا فوري.

---

### الحل 4 — نموذج الفواصل المتعددة داخل نفس الصف (Per-service intervals) — **غير مُوصى به**

**الفكرة:** إبقاء صف واحد لكن تخزين `start/end` لكل خدمة في `appointment_services`، وتغيير كل فحوص التعارض لتتجاهل `appointments.start/end` وتفحص مجموع الفواصل.

**التأثير:** migration (`appointment_services.start_time/end_time`) + إعادة كتابة `scopeBlocksProviderTime` + `scopeOverlapping` + `ServiceAvailabilityService` + `DashboardService` + رسم Timeline متعدد الكتل لكل موعد + كل التقارير. عملياً إعادة بناء طبقة الجدولة كاملة، مع إبقاء تناقض "صف واحد لمزودين" قائماً. **تكلفة الحل 3 مع عيوب الحل 1.** يُذكر فقط لاستبعاده بوعي.

---

### الحل 5 — إصلاح الواجهات فقط (منع الاختيار المتباعد في الموبايل والداشبورد) — **مكمل إجباري، وليس بديلاً**

**الفكرة:** الموبايل يحسب `services[i].start = services[i-1].end` تلقائياً ويمنع التعديل اليدوي للثاني، أو يعرض زر "إضافة موعد آخر في نفس اليوم" ينشئ طلب `POST /bookings` ثانياً. الداشبورد (Alpine) يفعل نفسه عند `onServiceChange / loadProviders`.

**التأثير:** بلا تغيير باك-إند، لكن **بلا ضمان**: أي عميل API مباشر يتجاوز الواجهة يعيد إنتاج المشكلة. لذلك هذا الحل **يجب أن يرافق الحل 1 أو 2، ولا يغني عنهما أبداً** (القاعدة الذهبية في هذا المشروع: `BookingService` لا يثق بالواجهة — راجع `docs/STAFF_DASHBOARD.md` §14.7).

---

## 6. ما العمل في الصف الفاسد الحالي (صور العميل)؟

1. **تشخيص:** جلب الصف والتحقق: `duration_minutes` (55) مقابل `TIMESTAMPDIFF(MINUTE,start_time,end_time)` (330). أي صف فيه الفرق > سماح (مثلاً > 15) هو مشتبه.
2. **استعلام كشف عام (للكل):**
   ```sql
   SELECT id, number, provider_id, appointment_date, start_time, end_time,
          duration_minutes,
          TIMESTAMPDIFF(MINUTE, start_time, end_time) AS span,
          TIMESTAMPDIFF(MINUTE, start_time, end_time) - duration_minutes AS phantom
   FROM appointments
   WHERE created_status = 1
     AND status IN (0, 1)
     AND TIMESTAMPDIFF(MINUTE, start_time, end_time) - duration_minutes > 15
   ORDER BY phantom DESC;
   ```
3. **إصلاح الحالة المفردة:** الاتصال بالعميل وتخييره: (أ) موعد واحد متصل 09:40–10:35، أو (ب) موعدان منفصلان 09:40–10:00 و14:35–15:10 (أو 15:10–15:45 حسب نيته). التنفيذ من الداشبورد: إلغاء (`ADMIN_CANCELLED`) وإعادة حجز — لا تعديل `end_time` يدوياً لأن `services_record` والفاتورة المسودة لن تتبعه.
4. **منع التكرار:** تطبيق الحل 1 فوراً + فحص واجهات (الحل 5) في نفس الإصدار.

---

## 7. التوصية

| الأفق | الإجراء |
|---|---|
| **الآن (Hotfix)** | الحل 1 (رفض الفجوة > عتبة صغيرة في `validateSequentialTiming`) + الحل 5 (قفل اختيار الوقت الثاني في الموبايل والداشبورد) + سكربت الكشف §6. العتبة المقترحة: `0` للتشدد الكامل، أو `15` دقيقة كفترة تبديل/تعقيم بين خدمتين. توحيد الثابت مع `GapAnalysisService::MAX_GAP_MINUTES` أو فصل ثابت إنشاء أصغر منه وتوثيق الفرق. |
| **تالياً (استراتيجي)** | الحل 3 (التقسيم أب/أبناء لكل كتلة زمنية) عندما يُراد دعم "موعدين في يوم واحد بفاتورة واحدة وإلغاء فردي" — وهو ما ظن العميل أنه فعله أصلاً. |
| **مرفوض** | الحل 4 (فواصل داخل الصف) — تكلفة مضاعفة بلا مكسب دلالي. الحل 2 وحده — يخفي النية بدل تصحيحها (يُقبل فقط إذا أصر المنتج على صفر أخطاء 422 للموبايل القديم). |

---

## المراجع في الكود

- `app/Services/BookingService.php:96` — الترتيب الزمني قبل المعاملة
- `app/Services/BookingService.php:129-136` — بناء `start/end` من الأول/الأخير + `duration` من المجموع (موضع الخلل)
- `app/Services/BookingValidationService.php:123-141` — `validateSequentialTiming` (الفجوة مسموحة)
- `app/Services/BookingValidationService.php:200-216` — `assertNoConflictingAppointment` (يُطبق الحجب الكاذب)
- `app/Services/BookingValidationService.php:363-401` — `assertCustomerIsFree` (لكل خدمة — غير متسق مع فحص المزود)
- `app/Services/ServiceAvailabilityService.php:363-433` — توليد الفتحات لخدمة واحدة
- `app/Services/GapAnalysisService.php:31` — `MAX_GAP_MINUTES = 60` (مطبق فقط على إضافة خدمة، لا على الإنشاء)
- `app/Models/Appointment.php:460-480` — `scopeBlocksProviderTime` + `scopeOverlapping` (تعريف الإشغال الوحيد)
- `app/Http/Resources/AppointmentResource.php:21-23` — عرض النطاق في الموبايل
- `app/Livewire/StaffDashboard.php:475-564` — `saveBookingFromAlpine` (يبني نفس الحمولة المتباعدة من الداشبورد أيضاً)
- `app/Livewire/StaffDashboard.php:625-691` — `updateAppointment` (يرقع أول خدمة فقط)
