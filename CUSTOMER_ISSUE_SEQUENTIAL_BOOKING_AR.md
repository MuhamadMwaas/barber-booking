# تقرير تشخيص مشكلة عميل — رفض الحجز: «Services must be sequential»

> **التاريخ:** 2026-09-25
> **المصدر:** بلاغ عميل عبر صور التطبيق (شاشة اختيار المزود/الوقت + شاشة الخطأ عند الضغط على `Jetzt buchen`)
> **النوع:** خطأ تحقق منطقي `422 validation_error` — وليس عطل سيرفر ولا حجز مزدوج
> **الخلاصة:** النظام يعمل كما صُمم. العميل اختار خدمتين متداخلتين زمنياً عند نفس الحلاق، فرفضها الباك-إند بشكل صحيح. المشكلة الحقيقية هي في تجربة الاستخدام (UX): التطبيق عرض وقتين متداخلين كأنهما متاحان معاً، ثم فاجأ العميل بالرفض لحظة الدفع.
> **لا يوجد فقدان بيانات ولا حجز وهمي:** الطلب رُفض داخل `DB::transaction` قبل أي `INSERT`.

---

## 1. ملخص تنفيذي (للمدير / للعميل)

| البند | التفاصيل |
|---|---|
| **ماذا فعل العميل؟** | اختار خدمتين + نفس الحلاقة `Elena Petrov` + نفس اليوم `12` ثم ضغط `Jetzt buchen` (احجز الآن) |
| **ماذا اختار بالضبط؟** | الخدمة 1 `Bart trimmen & formen` الساعة `08:20 PM (20:20)` + الخدمة 2 `Herrenhaarschnitt` الساعة `08:25 PM (20:25)` |
| **ماذا ظهر له؟** | رسالة حمراء: `Service at position 2 start time (20:25) must be after or equal to previous service end time (20:40). Services must be sequential.` |
| **هل هذا عطل؟** | لا. هذا فحص حماية مقصود يمنع أن يكون الحلاق والزبون في مكانين بنفس الوقت |
| **لماذا ارتبك العميل؟** | لأن التطبيق أظهر الوقتين باللون الأصفر كأنهما صالحان معاً، ولم يمنعه من هذا المزيج المستحيل إلا بعد الضغط على زر الحجز |
| **الحل الفوري للعميل** | إعادة اختيار وقتين متتاليين لا متداخلين (مثال بالأسفل)، وسيقبل الحجز فوراً |
| **الحل الجذري (علينا)** | تعديل شاشة الموبايل لتمنع/تُحذّر من التداخل قبل الإرسال + تحسين نص الرسالة بالعربية والألمانية |

---

## 2. فهم النظام بعمق (ما الذي يحدث تحت الغطاء؟)

هذا القسم يثبت أن التشخيص مبني على فهم كامل للنظام، وليس تخميناً من نص الرسالة.

### 2.1 فكرة النظام ودورة الحياة

`BarberBooking` نظام إدارة صالون تجميل (`Laravel 12 / PHP 8.2 / Filament 4 / PostgreSQL`) — المرجع: `docs/01-overview/README.md:8`.

الدورة الكاملة:

```
تصفح الخدمات GET /api/services
  → فحص التوفر GET /api/availability/service|provider|calendar
  → تسجيل الدخول POST /api/auth/login (Sanctum + OTP)
  → إنشاء الحجز POST /api/bookings (BookingController@store → BookingService@createBooking)
    → INSERT appointments + appointment_services + invoices (DRAFT)
  → الحضور → الدفع نقداً في المحل → فاتورة PAID + طباعة
```

لا يوجد دفع أونلاين. كل الأسعار `GROSS` (شاملة الضريبة، تُستخرج عكسياً عبر `TaxCalculatorService` وحده) — `docs/01-overview/README.md:117`.

### 2.2 مسار الحجز `POST /api/bookings` — أين صدرت الرسالة؟

التسلسل الكامل موثق في `docs/06-booking-flow/README.md:7`:

```
POST /api/bookings
  → Middleware auth:sanctum + verified.customer (401/403)
  → BookingCreateRequest::rules() (422 شكلي)
      date: Y-m-d + after_or_equal:today
      services.*.service_id / provider_id / start_time:H:i
      payment_method: cash|online
  → BookingController::store() — app/Http/Controllers/Api/BookingController.php:32
  → BookingService::createBooking() — app/Services/BookingService.php:42
      ├─ validateBasicData() خارج transaction (عدد 1..10، لا تكرار service_id)
      ├─ sortServicesByStartTime() — usort + strcmp — app/Services/BookingService.php:265
      └─ DB::transaction()
           ├─ BookingLockService::lockUsers() — SELECT FOR UPDATE
           ├─ validateAndPrepareServices() — app/Services/BookingService.php:277
           │    لكل خدمة بالترتيب الزمني:
           │      1. validateProviderOffersService
           │      2. حساب start/end = start_time + duration_minutes
           │      3. validateSequentialTiming(prevEnd, start) ← *** رسالتنا من هنا ***
           │      4. validateTimeSlotAvailability (دوام/إجازة/تعارض/ماضي/book_buffer)
           │      5. assertCustomerIsFree (الزبون ليس في مكانين)
           │    ...
           └─ splitIntoBlocks() + createBlockAppointment() + فاتورة DRAFT
```

الفشل في الخطوة 3 يُرمى كـ `InvalidArgumentException` فيُترجم في الكنترولر إلى `422 + validation_error` — `app/Http/Controllers/Api/BookingController.php:76`:

```php
catch (InvalidArgumentException $e) {
    return response()->json([
        'success' => false,
        'message' => $e->getMessage(),   // ← النص الذي رآه العميل
        'error_type' => 'validation_error',
    ], 422);
}
```

أي أن الرسالة الحمراء في الصورة هي `message` من رد `422`، وليست `409 slot_conflict` (الذي يعني «سبقك أحد») ولا `500`.

### 2.3 الفحص الذي رفض الطلب — `validateSequentialTiming`

الكود الحالي — `app/Services/BookingValidationService.php:123`:

```php
public function validateSequentialTiming(?Carbon $previousEndTime, Carbon $currentStartTime, int $serviceIndex): void
{
    if ($previousEndTime === null) {
        return;
    }
    if ($currentStartTime->lt($previousEndTime)) {
        throw new InvalidArgumentException(__('booking.services_overlap', [
            'position' => $serviceIndex,
            'start' => $currentStartTime->format('H:i'),
            'end' => $previousEndTime->format('H:i'),
        ]));
    }
    // الفجوة مسموحة عمداً بلا حد أعلى (BOOKING-GAP-01):
    // الزبون قد يريد 09:40 و 15:10 بنفس اليوم،
    // وBookingService يقسمها إلى appointment لكل كتلة متلاصقة.
}
```

القاعدة ببساطة: **`start >= prevEnd`**. التداخل مرفوض دائماً. الفجوة مسموحة دائماً (وتُخزن كموعدين مربوطين منذ إصلاح `BOOKING-GAP-01` — انظر `docs/fixes/BOOKING-GAP-01_multi_service_span_AR.md:1` و `API.md:2203`).

نصوص الرسالة بكل اللغات:

| اللغة | الملف | النص الحالي |
|---|---|---|
| EN | `lang/en/booking.php:13` | `Service :position starts at :start, before the previous service ends at :end. Services must not overlap.` |
| AR | `lang/ar/booking.php:13` | `الخدمة رقم :position تبدأ الساعة :start قبل انتهاء الخدمة السابقة الساعة :end. يجب ألا تتداخل الخدمات.` |
| DE | `lang/de/booking.php:13` | `Dienstleistung :position beginnt um :start, bevor die vorherige um :end endet...` |

> ملاحظة: نص الصورة (`must be after or equal ... Services must be sequential`) هو الصياغة القديمة لنفس الفحص قبل توحيد الرسائل (`BOOKING-MSG-01`). المنطق لم يتغير — فقط الصياغة تحسنت. إن كان السيرفر الإنتاجي يعرض الصياغة القديمة فهو متأخر بنسخة عن `lang/*` الحالية.

### 2.4 لماذا عرض التوفر الوقتين كمتاحين؟ — جذر الارتباك

`ServiceAvailabilityService` يحسب التوفر **لخدمة واحدة في كل مرة** — `app/Services/ServiceAvailabilityService.php:70` + `docs/04-services/availability-service.md:23`:

```
generateTimeSlots(provider, service, date):
  window = shift_start → shift_end
  current = shift_start
  while current + duration ≤ shift_end:
    slot = [current, current+duration]
    skip إن يتعارض مع appointments أو إجازة ساعية أو book_buffer
    else أضفه
    current += duration + SLOT_BUFFER(=0)
```

النقاط الحاسمة:

1. **الشبكة تعتمد على مدة الخدمة:** `shift_start + k × duration`. خدمة `20 دقيقة` شبكتها `08:00، 08:20، 08:40...` وخدمة `35 دقيقة` شبكتها `07:50، 08:25، 09:00...` — وهذا يطابق الصور تماماً (البطاقة الأولى 3 أوقات بفارق 35 دقيقة، والثانية 6 أوقات بفارق 20 دقيقة).
2. **كل استعلام مستقل:** `GET /availability/service?service_id=X` أو `GET /availability/provider?service_id=X&provider_id=Y` يفحص تعارض تلك الخدمة وحدها مع حجوزات DB الموجودة — ولا يعرف شيئاً عن الخدمة الأخرى في سلة العميل.
3. **النتيجة:** `20:20` متاحة لـ `Bart` وحدها، و`20:25` متاحة لـ `Herrenhaarschnitt` وحده — وكلاهما صحيح منفرداً، ومستحيل مجتمعاً.

التوثيق نفسه يعترف بهذا الفصل: «الفحصان يعملان على مستوى مختلف ولا يلتقيان» — `docs/fixes/BOOKING-GAP-01_multi_service_span_AR.md:110`.

---

## 3. إعادة بناء ما فعله العميل (دليل من الصور)

### 3.1 القراءة الدقيقة للصورتين

| الصورة | المحتوى |
|---|---|
| الصورة 2 (اختيار المزود) | بطاقتان لنفس المزودة `Elena Petrov` ونفس اليوم `12`. الأولى `Herrenhaarschnitt` الوقت المحدد (أصفر) `08:25 PM` من بين `07:50 / 08:25 / 09:00`. الثانية `Bart trimmen & formen` الوقت المحدد `08:20 PM` من بين `08:00 / 08:20 / 08:40 / 09:00 / 09:20 / 09:40` |
| الصورة 1 (الخطأ) | نفس الاختيار + السعر `Gesamt 46,00 EUR` + زر `Jetzt buchen` + الرسالة الحمراء أسفله |

### 3.2 الحساب الزمني (لماذا 20:40؟)

من شبكة الـ Slots نستنتج المدد (الشبكة = المدة):

- `Bart trimmen & formen`: فارق 20 دقيقة → **المدة 20 دقيقة** → `20:20 + 20m = 20:40 نهاية`.
- `Herrenhaarschnitt`: فارق 35 دقيقة → **المدة ~35 دقيقة** → `20:25 + 35m = 21:00 نهاية`.

ما أرسله التطبيق تقريباً (بعد `sortServicesByStartTime`):

```json
POST /api/bookings
{
  "date": "2026-XX-12",
  "payment_method": "cash",
  "services": [
    { "service_id": "<bart>", "provider_id": "<elena>", "start_time": "20:20" },
    { "service_id": "<herren>", "provider_id": "<elena>", "start_time": "20:25" }
  ]
}
```

داخل `validateAndPrepareServices` — `app/Services/BookingService.php:295`:

```
i=0: Bart  start 20:20  end 20:40  prevEnd=null → مقبول
i=1: Herren start 20:25  prevEnd=20:40 → 20:25 < 20:40 → throw
     position = index+1 = 2, start=20:25, end=20:40
     → "Service at position 2 start time (20:25) must be after ... (20:40)"
```

تطابق تام بين الأرقام في الرسالة والأوقات المحددة في الصور. التشخيص مؤكد.

### 3.3 لماذا هذا الرفض صحيح؟ (وليس تعنتاً)

1. **استحالة فيزيائية:** حلاقة واحدة (`Elena`) لا تستطيع قص شعر ولحية لرجل واحد في نفس الدقائق `20:25–20:40`. والزبون نفسه لا يمكن أن يكون في كرسيين.
2. **حتى بمزودين مختلفين** كان سيُرفض عبر `assertCustomerIsFree` — `app/Services/BookingValidationService.php:357` («الزبون لا يمكن أن يكون في مكانين»).
3. **الفجوة مسموحة، التداخل ممنوع:** لو اختار `20:20 + 20:40` أو `20:20 + 21:00` لقُبِل (الكتل المتلاصقة تُدمج في موعد واحد، والمتباعدة تُقسم لموعدين مربوطين — `splitIntoBlocks` في `app/Services/BookingService.php:199`).

---

## 4. هل هي مشكلة باك-إند أم فرونت-إند؟

**الباك-إند بريء ويعمل كما يجب.** الرفض بـ `422` قبل أي كتابة هو السلوك المصمم له (`docs/06-booking-flow/README.md:110`).

**المشكلة الحقيقية: فجوة UX في الموبايل:**

- الشاشة تعرض **منتقي تاريخ ووقت مستقل لكل خدمة**، فيختار المستخدم وقتين متداخلين دون أي تحذير.
- لا يوجد فحص مسبق في التطبيق (`start2 >= end1`) قبل `POST`.
- الرسالة تقنية بالإنجليزية (`position 2 ... sequential`) بينما واجهة العميل ألمانية (`Jetzt buchen / Gesamt`) — فبدت له كعطل غامض.
- لا يوجد اقتراح بديل («أقرب وقت صالح هو 20:40»).

هذا النمط موثق كدَين معروف: التوفر لكل خدمة وحدها، والحجز للحزمة كلها — ولا طبقة تجمعهما في الواجهة.

---

## 5. كيف نحلها؟ (خطة على 3 مستويات)

### 5.1 حل فوري — يفعله العميل الآن (بدون أي تعديل كود)

أي واحد من هذه المزيجين يقبل فوراً (نفس اليوم 12، نفس المزودة):

**الخيار A — متتالي مباشر (الأسرع):**
- `Bart` الساعة `20:20` (ينتهي `20:40`) + `Herrenhaarschnitt` الساعة `20:40` أو بعده.
- إن لم تكن `20:40` معروضة في شبكة الـ 35 دقيقة، اختر `21:00` (أول slot بعد نهاية الأولى).

**الخيار B — عكس الترتيب:**
- `Herrenhaarschnitt` الساعة `08:25 PM` (ينتهي `~09:00`) + `Bart` الساعة `09:00 PM` (معروضة فعلاً في الصورة).

> القاعدة الذهبية للعميل: **بداية الخدمة الثانية = نهاية الأولى أو بعدها، أبداً قبلها.**

### 5.2 إصلاح الفرونت-إند (المطلوب فعلاً — على فريق الموبايل)

1. **توحيد التاريخ:** منتقي تاريخ واحد للحجز كله، لا تاريخ لكل خدمة (الصور تظهر `12` مكرراً في البطاقتين — مصدر خطأ إضافي).
2. **فلترة تسلسلية:** بعد اختيار الخدمة الأولى (`start1 + duration1 = end1`)، احذف/عطّل من قائمة الخدمة الثانية كل `slot < end1`. اعكس المنطق إن بدأ بالثانية.
3. **فحص قبل الإرسال:** رتّب الخدمات بـ `start_time` ثم تحقق محلياً `start[i] >= end[i-1]` واعرض تحذيراً فورياً بلغة الواجهة قبل الوصول لزر الحجز.
4. **اعرض المدة والنهاية:** بجانب كل slot اعرض `20:20 – 20:40 (20m)` بدل وقت البدء وحده — معظم المستخدمين لا يحسبون النهاية ذهنياً.
5. **اقتراح تلقائي:** زر «رتّب لي الأوقات» يختار أول مزيج متتالٍ متاح.
6. **عالج `422` بلطف:** عند `error_type=validation_error` اعرض نصاً مترجماً + زر «اختر أول وقت متاح» بدل النص الإنجليزي الخام. وعالج `409 slot_conflict` بتحديث الـ Slots (شخص آخر سبقك) — الفرق موثق في `docs/05-api/booking.md:75`.

مثال منطق الفلترة (شبه كود للموبايل):

```dart
services.sort((a,b) => a.start.compareTo(b.start));
for (i = 1; i < services.length; i++) {
  if (services[i].start < services[i-1].end) {
    // عطّل هذا الـ slot في القائمة + اعرض: "يتداخل مع الخدمة السابقة (تنتهي 20:40)"
  }
}
```

### 5.3 تحسين الباك-إند (اختياري لكن مستحسن)

1. **نشر صياغة الرسالة الجديدة:** إن كان الإنتاج يعرض الصياغة القديمة، انشر `lang/{en,ar,de}/booking.php:13` الحالية («يجب ألا تتداخل») — أوضح من «sequential».
2. **إرفاق اقتراح:** إرجاع `suggested_start` (أول `slot >= prevEnd`) ضمن رد `422` ليسرّع الفرونت في التصحيح التلقائي.
3. **endpoint تحقق مسبق:** `POST /api/bookings/validate` (dry-run لنفس الفحوص بلا كتابة) لتظهر الأخطاء لحظياً أثناء الاختيار.
4. **توثيق للفرونت:** إضافة مثال «خدمتان متداخلتان → 422» في `API.md` قسم `Bookings - Create` بجانب مثال الخدمتين المتتاليتين (`API.md:2116`).

### 5.4 ما الذي لا نفعله؟

- لا نسمح بالتداخل تحت أي ظرف (يكسر `blocksProviderTime` والتقويم والفوترة).
- لا نعدّل `validateSequentialTiming` لتقبل `start < prevEnd` — الفجوة مسموحة أصلاً، والتداخل خط أحمر.
- لا نحوّل `422` إلى `409` هنا — `409` محجوز لحالة «كان متاحاً وسبقك أحد» (`SlotUnavailableException`)، بينما هنا الطلب نفسه مستحيل منذ البداية.

---

## 6. كيف نرد على العميل؟ (قوالب جاهزة للنسخ)

### 6.1 الرد العربي المقترح (للإدارة / للدعم)

> مرحباً، شكراً لتواصلك وإرفاق الصور — ساعدتنا كثيراً في فهم ما حدث.
>
> ما ظهر لك ليس عطلاً في حسابك ولا في الدفع: التطبيق عرض لك وقتين متداخلين (08:20 و08:25) لنفس الحلاقة، وعند الضغط على «احجز الآن» رفض النظام الحجز لأنه مستحيل التنفيذ — الخدمة الثانية تبدأ قبل انتهاء الأولى (تنتهي 20:40). القاعدة في النظام: الخدمات في نفس الحجز يجب أن تكون متتالية، لا متداخلة.
>
> **الحل خلال دقيقة:** اختر الخدمة الأولى 08:20، ثم اختر للخدمة الثانية أي وقت يبدأ من 08:40 فصاعداً (مثلاً 09:00 الظاهرة عندك)، ثم اضغط احجز — وسيتم الحجز بنجاح بنفس السعر 46,00 EUR.
>
> نعتذر عن الارتباك — التطبيق كان يجب أن يمنعك من اختيار وقتين متداخلين من البداية بدل رفضهما في النهاية، وقد سجلنا هذا كتحسين عاجل على الشاشة. لو تكررت معك أي رسالة أخبرنا وسنساعدك فوراً.

### 6.2 الرد الألماني المقترح (واجهة العميل ألمانية)

> Hallo, vielen Dank für die Screenshots — sie haben uns sehr geholfen.
>
> Das ist kein Fehler in Ihrem Konto: Die App hat Ihnen zwei sich überschneidende Zeiten (20:20 Uhr und 20:25 Uhr) bei derselben Friseurin angeboten. Beim Buchen hat das System zu Recht abgelehnt, weil die zweite Leistung beginnt, bevor die erste endet (Ende 20:40 Uhr). Leistungen in einer Buchung müssen nacheinander stattfinden, nicht gleichzeitig.
>
> **Lösung in einer Minute:** Wählen Sie die erste Leistung um 20:20 Uhr und die zweite ab 20:40 Uhr (z. B. 21:00 Uhr), dann klappt die Buchung zum gleichen Preis von 46,00 EUR.
>
> Entschuldigung für die Verwirrung — die App sollte eine solche Kombination von vornherein verhindern. Wir haben das als dringende Verbesserung aufgenommen.

### 6.3 ما نطلبه من العميل (إن أردنا بيانات إضافية)

- هل التاريخ المختار في البطاقتين هو نفسه (`12` من أي شهر)؟
- هل تفضل خدمتين متتاليتين مباشرة أم بفاصل؟
- لقطة لـ `X-RateLimit-Remaining` غير مطلوبة هنا (ليست مشكلة `429`).

---

## 7. الملحق التقني (للمهندسين)

### 7.1 بصمة الخطأ

| الحقل | القيمة |
|---|---|
| HTTP | `422 Unprocessable Entity` |
| `error_type` | `validation_error` (وليس `slot_conflict`) |
| الاستثناء | `InvalidArgumentException` من `validateSequentialTiming` |
| الملف | `app/Services/BookingValidationService.php:129` |
| الرسالة | `__('booking.services_overlap', [position=2, start=20:25, end=20:40])` |
| الترتيب قبله | `sortServicesByStartTime` — `app/Services/BookingService.php:265` |
| لا كتابة DB | يُرمى قبل `Appointment::create` وداخل `DB::transaction` فيُلفّ كل شيء |

### 7.2 لماذا ليست `409` ولا `500`؟

- `409 + slot_conflict` = طلب سليم + فتحة سُرقت لحظة الإرسال (`SlotUnavailableException` — `app/Http/Controllers/Api/BookingController.php:65`). هنا الطلب نفسه مستحيل.
- `500` = خطأ غير متوقع (`catch Throwable` — `BookingController.php:83`). هنا خطأ قواعد عمل متوقع وممسوك بـ `catch InvalidArgumentException`.

### 7.3 المراجع داخل المشروع

- التدفق: `docs/06-booking-flow/README.md:39` (`sortServicesByStartTime`) و`:62` (`validateSequentialTiming ← 422`)
- الخدمة: `docs/04-services/booking-service.md:53` (المراحل الـ 7) و`:111` (منع التداخل)
- التحقق: `docs/04-services/validation-service.md:37` (`start ≥ prevEnd`)
- التوفر: `docs/04-services/availability-service.md:25` (الشبكة `shift_start + k × duration`)
- الـ API: `API.md:2047` (`POST /api/bookings`) و`:2070` («الخدمات يجب أن تكون متتالية») و`:2203` (الحجز المقسوم `BOOKING-GAP-01`)
- الإصلاح السابق ذو الصلة: `docs/fixes/BOOKING-GAP-01_multi_service_span_AR.md:78` (الفحص يمنع التداخل ويسمح بأي فجوة)
- الطلب: `app/Http/Requests/Api/BookingCreateRequest.php:36` (`services.*.start_time: H:i`)

---

## 8. خلاصة الحكم

1. **الرسالة صحيحة والرفض صحيح** — حمى التقويم من حجز مستحيل.
2. **تجربة الاستخدام خاطئة** — سمحت باختيار المستحيل ثم لامت المستخدم.
3. **الإصلاح على الموبايل أولاً** (فلترة تسلسلية + رسالة مترجمة + عرض النهاية)، والباك-إند تحسين ثانوي (نشر الصياغة الجديدة + اقتراح وقت).
4. **الرد على العميل:** اعتذار + شرح بكلمات بسيطة + خطوتان عمليتان + وعد بتحسين الشاشة — القوالب في §6 جاهزة للإرسال.

*أُعدّ هذا التقرير بعد قراءة `docs/README.md` و`API.md` وتتبع الكود من `routes/api.php` حتى `BookingService` و`BookingValidationService` و`ServiceAvailabilityService`.*
