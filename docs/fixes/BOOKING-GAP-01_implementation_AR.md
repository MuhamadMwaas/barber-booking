# BOOKING-GAP-01 — شرح التنفيذ التفصيلي: تقسيم الحجز متعدد الخدمات إلى كتل (أب/أبناء)

> **التاريخ:** 2026-09-25
> **المشكلة:** [`BOOKING-GAP-01_multi_service_span_AR.md`](BOOKING-GAP-01_multi_service_span_AR.md)
> **الخطة المعتمدة:** [`BOOKING-GAP-01_plan_AR.md`](BOOKING-GAP-01_plan_AR.md)
> **النتيجة:** 23 اختباراً جديداً ناجحاً؛ الحزمة كاملة 554 ناجحاً، و18 فشلاً **موجوداً قبل التعديل ومطابقاً حرفياً** للخط الأساسي (لا فشل جديد)

---

## 1. الملخص

### ماذا كان يحدث
عميل حجز خدمتين من التطبيق: `Beard Trim` الساعة 09:40 و`Men's Haircut` الساعة 15:10. النظام حفظ **صفاً واحداً** في `appointments`:

```text
start_time = 09:40   (بداية أول خدمة)
end_time   = 15:10+  (نهاية آخر خدمة)
duration   = 55      (مجموع المدد)
```

`Appointment::scopeOverlapping()` يقرأ `start/end`، فصار المزود **مشغولاً 5.5 ساعات لعمل 55 دقيقة**. التطبيق عرض `09:40 AM - 03:10 PM`، والتقويم رسم كتلة تغطي اليوم كله.

### ماذا يحدث الآن
الطلب نفسه يُنتج **موعدين مربوطين**:

```text
APT-…-A  (الجذر)  09:40–10:00  Beard Trim     parent_appointment_id = NULL   ← يملك الفاتورة
APT-…-B  (ابن)    15:10–15:45  Men's Haircut  parent_appointment_id = A
```

- الفجوة 10:00–15:10 **حرة** للحجز.
- فاتورة مسودة **واحدة** على `A` تحوي الخدمتين (46 €) وتُدفع مرة واحدة.
- العميل يرى **بطاقتين** صحيحتين في `Meine Termine` دون أي تحديث للتطبيق، ويستطيع إلغاء كل واحدة وحدها.

### القاعدة في سطر واحد
> خدمات **متلاصقة تماماً** (نهاية = بداية) **عند نفس المزود** = صف واحد. أي **فجوة** (ولو دقيقة) أو **مزود مختلف** = صف جديد مربوط بالأول.

---

## 2. القرارات المتفق عليها

| # | القرار | أثره في الكود |
|---|---|---|
| 1 | متلاصقة + نفس المزود = صف واحد | `BookingService::splitIntoBlocks()` |
| 2 | لا حد للفجوة في نفس اليوم | حُذف فرع `> 120` الميت، ولم يُضف حد |
| 3 | فاتورة واحدة كما هو (MON-05) | الجذر يملك الفاتورة، والدفع يغطي الكتل **النشطة** |
| 4 | إلغاء الأب ⇒ ترقية أقرب ابن | `AppointmentCancellationService::promoteNewRoot()` |
| 5 | عقد API متوافق خلفياً | `data` = الجذر + مفاتيح إضافية فقط |
| 6 | لا بيانات حقيقية | لا أمر ترحيل |
| 7 | الحد اليومي + تنبيه الإلغاء بالمجموعة، تذكير لكل كتلة، إيميل واحد | `COUNT(DISTINCT COALESCE(...))`، حلقة التذكير، `group-details.blade.php` |
| 8 | الموبايل: توثيق فقط | قسم جديد في `API.md` |

---

## 3. المفاهيم الجديدة

| المصطلح | التعريف | أين يعيش |
|---|---|---|
| **كتلة (Block)** | خدمات متتالية بلا فجوة عند مزود واحد = صف `appointments` واحد | `BookingService::splitIntoBlocks()` |
| **مجموعة (Group)** | كل كتل حجز واحد: جذر + أبناء (مستوى واحد) | البنية الموجودة `parent_appointment_id` |
| **مفتاح المجموعة** | `COALESCE(parent_appointment_id, id)` | `Appointment::group_root_id` + استعلامات العدّ |
| **عضو نشط** | حالته ليست `USER_CANCELLED` / `ADMIN_CANCELLED` / `NO_SHOW` | `Appointment::INACTIVE_GROUP_STATUSES` |

**الثابت الذي صار مضموناً لكل صف ينشئه الحجز:** `end_time - start_time == duration_minutes`.

---

## 4. التعديلات ملفاً ملفاً

### 4.1 `app/Models/Appointment.php`

#### (أ) ثابت الحالات غير النشطة
```php
public const INACTIVE_GROUP_STATUSES = [
    AppointmentStatus::USER_CANCELLED->value,
    AppointmentStatus::ADMIN_CANCELLED->value,
    AppointmentStatus::NO_SHOW->value,
];
```
**لماذا `NO_SHOW` أيضاً؟** لم تُقدَّم خدمة فلا شيء يُفوتر. `AppointmentStatus::getCancelledStatuses()` الموجودة لا تحوي `NO_SHOW`، لذلك استُعمل ثابت مستقل بدل تغيير دلالة دالة مستعملة في أماكن أخرى.

#### (ب) `scopeActiveInGroup()` و`activeLinkedGroup()`
```php
public function scopeActiveInGroup(Builder $query): Builder
{
    return $query->whereNotIn('status', self::INACTIVE_GROUP_STATUSES);
}

public function activeLinkedGroup(): Builder
{
    return $this->linkedGroup()->activeInGroup();
}
```
**لماذا؟** كل من كان يبني الفاتورة أو المبلغ المستحق يستعمل `linkedGroup()` كما هي، **بما فيها الأبناء الملغاة**. هذا كان خللاً قائماً (تفاصيله في §4.4). المكان الواحد لتعريف «من يُدفع عنه» يمنع تكرار الشرط في خمسة ملفات.

#### (ج) `group_root_id` و`isActiveInGroup()`
```php
public function getGroupRootIdAttribute(): int
{
    return (int) ($this->parent_appointment_id ?? $this->id);
}

public function isActiveInGroup(): bool
{
    $status = $this->status?->value ?? $this->status;
    return ! in_array($status, self::INACTIVE_GROUP_STATUSES, true);
}
```
`group_root_id` هو نسخة PHP من `COALESCE(parent_appointment_id, id)` في SQL، ويُعرض في الـ API. `isActiveInGroup()` هو نفس القاعدة لكن على كائن محمّل.

#### (د) `cancel()` يفوّض للخدمة
```php
public function cancel(?string $reason = null): bool
{
    $cancelled = app(AppointmentCancellationService::class)->cancel(
        $this, AppointmentStatus::USER_CANCELLED, $reason,
    );
    $this->setRawAttributes($cancelled->getAttributes(), true);
    return true;
}
```
- **قبل:** `update(status=USER_CANCELLED)` ثم `CancellationMonitor`.
- **الآن:** كل منطق المجموعة (إعادة بناء الفاتورة، الترقية) في الخدمة، والخدمة نفسها تستدعي `CancellationMonitor` بعد الـ commit.
- **العقد العام لم يتغير:** ما زالت تُرجع `true`، ويبقى مستدعياها (`BookingService::cancelBooking` و`AppointmentService::cancelAppointment`) واختبار `does not let an alerting failure undo the cancellation` صالحين دون تعديل.
- `setRawAttributes` يحدّث النموذج في الذاكرة، لأن الترقية قد تغيّر `parent_appointment_id` للصف الملغى نفسه.

#### (هـ) توثيق `canBeCancelledOrDeleted()`
صار للحذف فقط (يستعمله `AppointmentDeletionService`). الاسم بقي كما هو لأن تغييره يمس الحذف دون داعٍ، والتعليق يشرح ذلك.

---

### 4.2 `app/Services/BookingService.php` — قلب التغيير

#### (أ) `createBooking()` — من صف واحد إلى كتل
**قبل:**
```php
$totals = $this->calculateTotals($preparedServices);
$firstService = $preparedServices[0];
$appointment = Appointment::create([
    'provider_id' => $firstService['provider_id'],                      // الأول فقط
    'start_time'  => … $firstService['start_time'],                     // أول خدمة
    'end_time'    => … $preparedServices[count-1]['end_time'],          // آخر خدمة ← يمتد على الفجوة
    'duration_minutes' => $totals['total_duration'],                    // ≠ end-start
    …
]);
foreach ($preparedServices as …) AppointmentService::create(…);
createDtaftInvoiceFromAppointment($appointment, 'cash', 0);
```

**بعد:**
```php
$common = [ /* customer_*, appointment_date, status, payment_*, notes,
               created_status, booking_source, is_override, override_reason */ ];

$blocks = $this->splitIntoBlocks($preparedServices);

$appointment = $this->createBlockAppointment($blocks[0], $date, $common);   // الجذر

foreach (array_slice($blocks, 1) as $block) {
    $this->linkingService->validateChildCandidate($appointment, ['appointment_date' => $date]);
    $this->createBlockAppointment($block, $date, $common + [
        'parent_appointment_id' => $appointment->id,
    ]);
}

$invoiceService->createDtaftInvoiceFromAppointment($appointment, 'cash', 0);
if (count($blocks) > 1) {
    $invoiceService->rebuildAggregatedInvoice($appointment);
}

return $appointment->load([
    'services', 'customer', 'provider', 'services_record',
    'children.services_record', 'children.provider',
]);
```

**لماذا بهذا الشكل؟**
- **نفس المعاملة ونفس القفل:** كل الكتل تُكتب داخل `DB::transaction` الموجودة وبعد `lockUsers()` لكل المزودين والعميل. رفض أي كتلة يُلغي كل شيء، فلا يوجد حجز «نصفه نجح». هذا يحترم عقد `booking-lock-contract`.
- **الفحوص لم تتغير:** `validateAndPrepareServices()` كان يفحص **كل خدمة على حدة** (الدوام، الإجازة، `assertNoConflictingAppointment`، `assertCustomerIsFree`). فحص كل خدمة بنافذتها يساوي تماماً فحص كل كتلة، لأن الكتلة اتحاد نوافذ متلاصقة.
- **`validateChildCandidate` دفاعي:** يضمن قواعد `AppointmentLinkingService` (مستوى واحد، نفس اليوم، الأب يقبل خدمات). بالبناء هي محققة أصلاً، لكن المرور بها يُبقي مكان القاعدة واحداً.
- **الفاتورة:**
  - كتلة واحدة → نفس الاستدعاء القديم، فبنود الفاتورة **حرفياً كما كانت** (بلا لاحقة «— by Provider»).
  - عدة كتل → `rebuildAggregatedInvoice` يعيد بناء البنود من كل الكتل. هذه نفس الدالة التي يعتمد عليها الدفع (MON-05)، فلا يوجد مسار فوترة جديد.
- **الملاحظات تُنسخ لكل كتلة:** مزود كتلة العصر يجب أن يرى ملاحظة العميل أيضاً.
- **ما يُرجَع:** الجذر (نفس النوع `Appointment`)، مع الأبناء محمّلين للاستجابة والإيميل والتذكير. لا مستدعٍ احتاج تعديلاً في توقيعه.

#### (ب) `splitIntoBlocks()` — دالة جديدة
```php
foreach ($preparedServices as $service) {
    $previous = end($current);
    $continuesBlock = $previous !== false
        && (int) $previous['provider_id'] === (int) $service['provider_id']
        && $previous['end_time'] === $service['start_time'];

    if ($previous !== false && ! $continuesBlock) {
        $blocks[] = $current;
        $current = [];
    }
    $current[] = $service;
}
$blocks[] = $current;
```
- المدخلات مرتبة (`sortServicesByStartTime`) ومفحوصة بلا تداخل (`validateSequentialTiming`)، فيكفي مقارنة كل خدمة بسابقتها.
- المقارنة بصيغة `H:i` صحيحة لأن كل الخدمات في نفس التاريخ.
- **مزود مختلف = كتلة جديدة حتى بلا فجوة:** الصف له `provider_id` واحد، ونافذته يجب أن تُحجب على تقويم **ذلك** المزود. هذا يصلح الخلل §4.5 في ملف المشكلة (المزود الثاني لم يكن يُحجب أبداً، فكان ممكناً حجزه مرتين).

#### (ج) `createBlockAppointment()` — دالة جديدة
تستخرج ما كان مكرراً: `calculateTotals($block)` ثم `Appointment::create` بحدود الكتلة ومبالغها، ثم `AppointmentService::create` لكل خدمة، و`sequence_order` يبدأ من 1 **داخل الكتلة**. `calculateTotals` نفسها لم تتغير، فالضريبة ما زالت عبر `TaxCalculatorService` الوحيد (MON-01).

#### (د) `addServiceToBooking()` — مرجع الفجوة في وضع الابن
```php
// قبل
$invoiceOwner = $this->linkingService->getInvoiceOwner($anchor);
$analysis = $this->gapAnalysis->analyzeChildAdd($invoiceOwner, …);
// بعد
$analysis = $this->gapAnalysis->analyzeChildAdd($anchor, …);
```
**لماذا؟** `analyzeChildAdd` يرفض أي فجوة أكبر من `MAX_GAP_MINUTES = 60` عن حدود المرجع. مع الحجز المقسوم قد يكون الجذر كتلة 09:40، والموظف يضغط كتلة 15:10 ليضيف خدمة بعدها بمزود آخر. القياس على الجذر كان سيرفضها دائماً بـ `gap_too_large`. الآن تُقاس على **الكتلة المضغوطة**، ويبقى الابن الجديد مربوطاً بالجذر (`addServiceDifferentProvider` لم يتغير)، فالمستوى الواحد محفوظ.

#### (هـ) `getBookingDetails()`
تحمّل `children.services_record` و`children.provider` إضافياً، ليعرض `GET /api/bookings/{id}` للجذر `linked_appointments`.

---

### 4.3 `app/Services/BookingValidationService.php`

#### (أ) `validateSequentialTiming()`
```php
// حُذف:
if ($currentStartTime->diffInMinutes($previousEndTime) > 120) {
    // You can add a warning or log here
}
```
كان كوداً ميتاً يوحي بقاعدة غير موجودة. استُبدل بتعليق يشرح أن **الفجوة مشروعة وبلا حد** (القرار 2)، لأنها تُقسَّم ولا تُحجب. منع **التداخل** بقي كما هو.

#### (ب) `validateDailyBookingLimit()`
```php
->count(DB::raw('DISTINCT COALESCE(parent_appointment_id, id)'));   // كان ->count()
```
حجز مقسوم إلى 3 كتل كان سيستهلك 3 من `max_daily_bookings`. الآن يُعدّ **حجزاً واحداً** (القرار 7). التعبير يعمل على MySQL وعلى SQLite الخاص بالاختبارات.

---

### 4.4 الفاتورة والدفع — استثناء الأعضاء غير النشطين (إصلاح خلل قائم)

**الخلل الذي كان موجوداً قبل هذه المهمة:**
1. `rebuildAggregatedInvoice()` تجمع `linkedGroup()` كاملة، فالابن الملغى **تبقى خدماته في الفاتورة**. كان تعليق `StaffDashboard::cancelAppointment` يقول إن الـ rebuild «يزيل بنوده»، والكود لا يفعل ذلك.
2. `InvoiceFinalizationService::assertAppointmentsCanBePaid()` ترفض الدفع إذا وُجد **أي** عضو ملغى. **النتيجة: بعد إلغاء ابن واحد تصبح المجموعة كلها غير قابلة للدفع.**

كان هذا نادراً لأن الأبناء كانوا يُنشؤون يدوياً من «إضافة خدمة». مع الحجز المقسوم صار الإلغاء الفردي هو الحالة العادية، فإصلاحه شرط للميزة.

| الملف | التعديل |
|---|---|
| `app/Services/InvoiceService.php` | `rebuildAggregatedInvoice()`: `linkedGroup()` ← `activeLinkedGroup()`. البنود و`invoice_data.appointment_ids` من النشطين فقط |
| `app/Models/Invoice.php` | `getCoveredAppointments()` (للطباعة): النشطون فقط |
| `app/Services/InvoiceFinalizationService.php` | انظر أدناه |
| `app/Livewire/StaffDashboard.php` → `openPaymentModal()` | المبلغ المقترح `activeLinkedGroup()->sum('total_amount')` |
| `app/Filament/Resources/Appointments/Tables/AppointmentsTable.php` (سطران) | نفس الشيء في نافذة الدفع |
| `app/Filament/Resources/Providers/RelationManagers/AppointmentsRelationManager.php` (سطران) | نفس الشيء |

**`InvoiceFinalizationService::finalizeAppointmentPayment()`:**
```php
$lockedGroup = $invoiceOwner->linkedGroup()->orderBy('id')->lockForUpdate()->get();

$this->assertAppointmentsCanBePaid($lockedGroup, $appointment->id);

$coveredAppointments = $lockedGroup
    ->filter(fn (Appointment $member) => $member->isActiveInGroup())
    ->values();
```
```php
private function assertAppointmentsCanBePaid($group, int $requestedAppointmentId): void
{
    if ($group->isEmpty()) throw … no_appointments;

    $requested = $group->firstWhere('id', $requestedAppointmentId);
    if (($requested && ! $requested->isActiveInGroup())
        || $group->every(fn ($m) => ! $m->isActiveInGroup())) {
        throw … cancelled_or_no_show;
    }
}
```
- **القفل لم يتغير:** ما زال يقفل **كل** صفوف المجموعة بنفس الترتيب. الترتيب مشترك مع الحذف والإلغاء، فتغييره كان سيفتح باب deadlock.
- **الرفض صار دقيقاً:** يُرفض فقط إذا كان الموعد الذي فتحه الكاشير نفسه ملغى، أو لم يبقَ أحد.
- **الإكمال:** يُعلَّم `COMPLETED` للنشطين فقط، و`covered_appointment_ids` يسجلهم فقط. الملغى يبقى ملغى.

---

### 4.5 ملف جديد: `app/Services/AppointmentCancellationService.php`

**لماذا خدمة جديدة؟** كانت هناك ثلاثة مسارات تكتب الإلغاء، كل واحد بطريقته:

| المسار | قبل |
|---|---|
| العميل | `Appointment::cancel()`: كتابة مباشرة، بلا وعي بالمجموعة (يترك أبناء نشطين فاتورتهم على أب ملغى) |
| اللوحة | يمنع إلغاء أب له أبناء، ويعيد بناء فاتورة الأب يدوياً عند إلغاء ابن |
| Filament | كتابة مباشرة، **بلا أي منع ولا rebuild** |

على نمط `AppointmentDeletionService` (DEL-01) صار هناك **مسار واحد**:

```text
cancel(Appointment $appointment, AppointmentStatus $status, ?string $reason): Appointment
│
├─ $status ∈ {USER_CANCELLED, ADMIN_CANCELLED} وإلا InvalidArgumentException
│
├─ DB::transaction
│   ├─ lockGroup(): الجذر أولاً (lockForUpdate)، ثم كل المجموعة orderBy id
│   ├─ إعادة الفحص على الصف المقفول: PENDING وغير مدفوع
│   │     وإلا booking.only_pending_can_be_cancelled
│   ├─ update(status, cancellation_reason, cancelled_at)
│   └─ reorganiseGroup():
│        • ابن + الجذر نشط      → rebuildAggregatedInvoice(root)
│        • جذر بلا كتل نشطة     → لا شيء (كما كان للموعد المستقل)
│        • جذر وله كتل نشطة     → promoteNewRoot()
│
└─ بعد الـ commit: إذا USER_CANCELLED → CancellationMonitor::recordCustomerCancellation()
```

**`promoteNewRoot()`:**
```php
$newRoot = $standing->sortBy(fn ($m) => [$m->start_time->getTimestamp(), $m->id])->first();

$newRoot->update(['parent_appointment_id' => null]);

Appointment::query()
    ->whereIn('id', $group->pluck('id')->reject(fn ($id) => $id === $newRoot->id)->all())
    ->update(['parent_appointment_id' => $newRoot->id]);

$invoice = Invoice::where('appointment_id', $oldRoot->id)->lockForUpdate()->first();
if ($invoice) {
    if ($invoice->status !== InvoiceStatus::DRAFT) throw …;
    $invoice->update(['appointment_id' => $newRoot->id, 'customer_id' => $newRoot->customer_id]);
}

$this->invoiceService->rebuildAggregatedInvoice($newRoot->fresh());
```

**قرارات التصميم:**
- **لماذا الترقية؟** (القرار 4) قاعدة المشروع: «الفاتورة تعيش على الأب» (`AppointmentLinkingService`). إبقاء الفاتورة على جذر ملغى كان سيجعل الطباعة و`payment_metadata.appointment_number` تشير إلى موعد لم يحدث.
- **لماذا نقل الفاتورة آمن؟** المسودة **بلا رقم** (`invoice_number = NULL`)، فتغيير `appointment_id` لا يمس الترقيم التسلسلي (MON-03) ولا أي تقرير. الفاتورة المُنهاة لا يمكن الوصول إليها هنا لأن المدفوع مرفوض قبلها، والفحص موجود دفاعياً. القيد الفريد `invoices(appointment_id)` لا يُكسر لأن الابن لا يملك فاتورة.
- **لماذا يُربط الجذر الملغى كابن للجذر الجديد بدل فصله؟**
  1. يبقى ظاهراً كجزء من نفس الحجز.
  2. مفتاح المجموعة `COALESCE(parent_appointment_id, id)` يبقى واحداً لكل الكتل، فإلغاء الكتلتين = **إلغاء واحد** في `CancellationMonitor` (القرار 7).
  3. الصف الملغى بلا فاتورة الآن، وهذا متسق مع كونه ابناً.
- **ترتيب الأقفال:** الجذر ثم المجموعة حسب `id`، مطابق لـ `InvoiceFinalizationService` و`AppointmentDeletionService`. دفع وحذف وإلغاء متزامنون على نفس المجموعة يصطفون ولا يتقاطعون.
- **إعادة الفحص تحت القفل:** نسخة المستدعي قد تكون قديمة (دفعٌ التزم للتو)، فالقرار يُتخذ على الصف المقفول.
- **`CancellationMonitor` بعد الـ commit:** إشعار لا يجب أن يُنشأ لإلغاء قد يُتراجع عنه، وفشل الإشعار لا يجب أن يلغي الإلغاء (الـ Monitor يبتلع أخطاءه).
- **التذكير:** لا كود خاص. هوك `updated` في `Appointment::boot()` يراقب عمود `status` ويلغي التذكير لكل كاتب.

### 4.6 مستدعو الإلغاء

| الملف | التعديل |
|---|---|
| `app/Models/Appointment.php` → `cancel()` | يفوّض للخدمة (§4.1-د) — يغطي `/api/bookings/{id}/cancel` و`/api/appointments/{id}/cancel` |
| `app/Livewire/StaffDashboard.php` → `cancelAppointment()` | حُذف منع «للأب أبناء نشطون» (`canBeCancelledOrDeleted`) وحُذف الـ rebuild اليدوي، وصار استدعاءً واحداً للخدمة بـ `ADMIN_CANCELLED`. منع المدفوع/المكتمل **بقي** كرسالة أولى للموظف. import `InvoiceService` (لم يعد مستعملاً) استُبدل بـ `AppointmentCancellationService` |
| `app/Filament/Resources/Appointments/Tables/AppointmentsTable.php` → إجراء `cancel` | الخدمة بـ `ADMIN_CANCELLED` وسبب الإلغاء من النموذج؛ `InvalidArgumentException` يظهر كإشعار Filament أحمر |
| `app/Services/AccountDeletionService.php` | **لم يتغير عمداً**: يلغي كل مواعيد العميل المستقبلية بـ UPDATE واحد، فالمجموعة تُلغى كاملة عادة (انظر §7) |

---

### 4.7 `app/Services/CancellationMonitor.php`
```php
->count(DB::raw('DISTINCT COALESCE(parent_appointment_id, id)'));   // كان ->count()
```
عميل يلغي كتلة الصباح ثم كتلة العصر من **نفس الحجز** ألغى حجزاً واحداً. قبل هذا التعديل كان العدّ 2، فيصل تنبيه «إلغاء متكرر» للمدير من حجز واحد.

---

### 4.8 الـ API

#### (أ) `app/Http/Resources/AppointmentResource.php` — مفاتيح إضافية فقط
```php
'parent_appointment_id' => $this->parent_appointment_id,
'group_root_id'         => $this->group_root_id,
'is_child_booking'      => $this->parent_appointment_id !== null,
'linked_appointments'   => $this->when(
    $this->parent_appointment_id === null && $this->relationLoaded('children'),
    fn () => LinkedAppointmentResource::collection($this->children->sortBy('start_time')->values())
),
'group_total_amount'    => $this->when(<نفس الشرط>,
    fn () => (float) collect([$this->resource])->merge($this->children)
        ->filter(fn ($m) => $m->isActiveInGroup())->sum('total_amount')
),
```
- **لا مفتاح حُذف أو تغيّر معناه** (القرار 5). `start_time`/`end_time`/`total_amount` في `data` هي للجذر، وهي الآن **صحيحة** (نافذته الحقيقية).
- **لماذا على الجذر فقط؟** `children` لابن يُحمَّل فارغاً دائماً، فكان `group_total_amount` سيدّعي مجموعة من كتلة واحدة. إخفاؤه أصدق من قيمة خاطئة.
- **لماذا فقط عند تحميل `children`؟** القوائم لا تحمّلها، فلا استعلام إضافي لكل بطاقة (لا N+1).

#### (ب) ملف جديد `app/Http/Resources/LinkedAppointmentResource.php`
مورد مختصر: `id, number, start_time, end_time, time_range, duration_minutes, total_amount, status, status_value, provider{id, full_name}, services_details`.
**لماذا لا `AppointmentResource` نفسه؟** لأنه يعرض `linked_appointments` بدوره، فالتداخل كان سيكرر الحمولة كاملة لكل كتلة ويخاطر بالتكرار اللانهائي.

#### (ج) `app/Http/Controllers/Api/BookingController.php` → `store()`
```php
foreach (collect([$appointment])->merge($appointment->children) as $block) {
    $this->scheduleReminderForBooking($block, (int) $reminderOffsetHours);
}
```
- تذكير **لكل كتلة** (القرار 7): عميل موعده 09:40 و15:10 يحتاج تذكيراً قبل كل زيارة.
- `scheduleReminderForBooking` تبتلع أخطاءها، فكل كتلة مستقلة، وفشل تذكير لا يلغي حجزاً ولا تذكيراً آخر.
- **ملاحظة تنفيذية:** استُعمل `collect([...])->merge(...)` لا `->prepend()`، لأن `prepend` يعدّل مجموعة العلاقة المحمّلة نفسها، فكان الجذر سيظهر داخل `linked_appointments` الخاصة به.

#### (د) `app/Services/AppointmentService.php` → `getAppointmentDetails()`
تحمّل `children.services_record` و`children.provider`، فـ `GET /api/appointments/{id}` للجذر يعرض `linked_appointments`.

#### (هـ) القوائم لم تتغير
`/api/bookings` و`/api/appointments` تُرجع كل صف لـ `customer_id`. كل كتلة تحمل `customer_id`، فتظهر **بطاقة مستقلة بوقتها الصحيح تلقائياً** في التطبيق الحالي.

---

### 4.9 الإيميل

| الملف | التعديل |
|---|---|
| `app/Services/BookingMailService.php` | `loadMissing` يضيف `children.services_record` و`children.provider`. `SerializesModels` يعيد تحميل العلاقات المتداخلة داخل الـ queue |
| `resources/views/emails/booking/partials/details.blade.php` | يحسب `$blocks` (الجذر + الأبناء النشطون). إذا أكثر من كتلة → `@include('…group-details')`، وإلا **القالب القديم حرفياً** داخل `@else` |
| ملف جديد `resources/views/emails/booking/partials/group-details.blade.php` | رقم الحجز والتاريخ وطريقة الدفع والملاحظات، ثم بطاقة لكل كتلة (الوقت، المزود، الخدمات، رقم الكتلة، المدة)، ثم مجموع المجموعة |

**لماذا إيميل واحد؟** (القرار 7) `sendForNewBooking` يُستدعى مرة واحدة للجذر، والقالب يسرد الكتل. إيميلان منفصلان كانا سيوحيان بحجزين مستقلين.

---

### 4.10 لوحة الموظفين `app/Livewire/StaffDashboard.php`

| الموضع | التعديل |
|---|---|
| `saveBookingFromAlpine()` و`saveBooking()` | رسالة النجاح `'#'.$appointment->number` ← `$this->bookingNumbersLabel($appointment)`، فتسرد `#A, #B` لكل الكتل |
| دالة جديدة `bookingNumbersLabel()` | تجمع أرقام الجذر والأبناء |
| `analyzeAddServiceGap()` | وضع الابن يُقاس على `$anchor` لا `$anchor->parent ?? $anchor` (مطابق لـ §4.2-د، وإلا عرضت المعاينة رفضاً والتنفيذ قبولاً) |
| `cancelAppointment()` / `openPaymentModal()` | §4.6 و§4.4 |

**الـ Timeline لم يحتج تعديلاً:** `getTimelineDataFromProviders()` كان يرسل أصلاً `parent_appointment_id` و`linked_group_root_id` و`is_child_booking`، والـ Blade يرسم شارة الابن وخط الربط. الفرق الآن أن كل كتلة بنافذتها الضيقة، فتظهر بطاقتان بدل كتلة تغطي اليوم.

### 4.11 `app/Services/GapAnalysisService.php`
تعديل توثيق فقط لـ `analyzeChildAdd()`: المعامل `$invoiceOwner` صار يستقبل الكتلة المضغوطة، والاسم يسبق التعديل. لم يُعَد تسميته لتجنب تغيير غير لازم.

### 4.12 الترجمات (ar / de / en)

| الملف | المفاتيح الجديدة |
|---|---|
| `lang/*/booking.php` | `only_pending_can_be_cancelled` |
| `lang/*/booking_email.php` | `appointments_heading`، `appointments_intro`، `appointment_n`، `group_total` |

### 4.13 التوثيق
| الملف | التعديل |
|---|---|
| `API.md` | قسم «الحجز المقسوم إلى عدة مواعيد» لفريق الموبايل: القاعدة، الحقول الجديدة، شكل `linked_appointments`، سلوك القوائم والإلغاء |
| `docs/BOOKING_FLOW.md` | جدول أعمدة `appointments` محدَّث، الملاحظتان 8 و9 مغلقتان، قسم BOOKING-GAP-01 جديد |
| `docs/STAFF_DASHBOARD.md` | §17.3–17.4 (الإلغاء عبر الخدمة، الترقية)، §19.1 (المبلغ من النشطين) |
| `docs/fixes/BOOKING-GAP-01_multi_service_span_AR.md` | سطر الحالة «مُصلَحة» + روابط |

---

## 5. قبل/بعد — مصفوفة السلوك

| السيناريو | قبل | بعد |
|---|---|---|
| لحية 09:40 + قص 15:10 عند نفس المزود | صف 09:40–15:45، المزود محجوب 5.5 ساعة | صفّان 09:40–10:00 و15:10–15:45، الفجوة حرة |
| قص 10:00 + لحية 11:00 عند نفس المزود | صف واحد 10:00–12:00 | **نفس الشيء** |
| قص 10:00 عند A + لحية 11:00 عند B | صف واحد باسم A فقط؛ B غير محجوب (حجز مزدوج ممكن) | صف لـ A وصف لـ B، كلٌّ محجوب على تقويمه |
| عميل آخر يحجز 12:00 في الفجوة | `409 slot_conflict` | `201` |
| الكتلة الثانية محجوزة | 409 | 409، ولا يُنشأ شيء |
| إلغاء كتلة العصر | — (غير ممكن) | تُلغى وحدها، الفاتورة تنقص، الدفع يعمل |
| إلغاء كتلة الصباح (الجذر) | يلغي كل شيء | تُلغى وحدها، العصر يصبح الجذر، الفاتورة تنتقل |
| دفع مجموعة فيها ابن ملغى | **يُرفض** (`cancelled_or_no_show`) | ينجح ويستثني الملغى |
| الحد اليومي = 1 وحجز مقسوم | — | يُعدّ حجزاً واحداً |
| العميل يلغي الكتلتين | — | إلغاء واحد في تنبيه المدير |
| `reminder_offset_hours` | تذكير للموعد الوحيد | تذكير قبل كل كتلة |
| إيميل التأكيد | وقت واحد خاطئ 09:40–15:45 | إيميل واحد يسرد الكتلتين |
| الموظف يلغي أباً له أبناء | ممنوع | مسموح، مع ترقية |
| Filament يلغي أباً له أبناء | يُلغى ويبقى الأبناء بفاتورة على أب ملغى | ترقية |

---

## 6. الاختبارات

### ملف جديد: `tests/Feature/Booking/MultiServiceSplitTest.php` — 23 اختباراً (119 assertion)

| # | الاختبار | ما يثبته |
|---|---|---|
| 1 | keeps back-to-back services at one provider in a single appointment | لا تقسيم بلا فجوة |
| 2 | splits services with a gap into a parent and a child that never span the gap | **حالة البلاغ**؛ `end-start == duration` لكل صف |
| 3 | puts one draft invoice on the root that covers every block | فاتورة واحدة 150، والابن بلا فاتورة |
| 4 | no longer holds the gap against the provider | 12:00 صارت قابلة للحجز |
| 5 | puts a second provider's service on that provider's own calendar | إصلاح الحجز المزدوج للمزود الثاني |
| 6 | groups a contiguous run and splits only at the gap | 3 خدمات → كتلتان |
| 7 | rolls the whole group back when a later block is taken | الكل أو لا شيء |
| 8 | returns the root as data and describes the other blocks alongside it | عقد الاستجابة |
| 9 | lists every block as its own card | القوائم بلا مفاتيح المجموعة |
| 10 | shows the linked blocks on the root's detail page but not on a child's | شرط الجذر |
| 11 | counts a split booking once against the daily limit | الحد اليومي |
| 12 | schedules the requested reminder before every block | تذكير 09:00 و13:00 |
| 13 | sends one confirmation email that lists every block | إيميل واحد بالوقتين والمجموع |
| 14 | cancels a child alone, drops it from the invoice, and still lets the rest be paid | **الخلل القائم §4.4** |
| 15 | promotes the next block when the customer cancels the root | الترقية + نقل نفس الفاتورة + الدفع |
| 16 | promotes the next block when staff cancel the root too | ADMIN |
| 17 | promotes the earliest standing block when a root has several children | ترقيتان متتاليتان |
| 18 | leaves a root in place when nothing else in its group still stands | لا ترقية بلا كتل نشطة |
| 19 | refuses to cancel a block that is no longer pending | إعادة الفحص تحت القفل |
| 20 | counts dropping every block of one booking as a single cancellation | لا تنبيه للمدير |
| 21 | splits a gapped booking made from the dashboard and names every block | Livewire: الحفظ + رسالة الأرقام |
| 22 | lets staff cancel a root that still has an active block, promoting that block | Livewire: الإلغاء |
| 23 | adds a service next to a child block without measuring the gap from the root | مرجع `analyzeChildAdd` |

### الحزمة كاملة
| | قبل التعديل (خط أساسي نظيف) | بعد |
|---|---|---|
| ناجح | 531 | **554** (+23) |
| فاشل | 18 | 18 — **نفس القائمة حرفياً** (مقارنة `comm` بين القائمتين: لا جديد، لا مفقود) |

الـ 18 لا علاقة لها بالحجز: Fiskaly (5)، `PhoneNumberValidationTest` (6، من تعديلات الهاتف غير المُلتزمة في الشجرة)، `ProfileImageUploadTest` (3)، `PasswordRequirementsTest`، `AuthRateLimitTest`، `DeleteAccountTest`، `ExampleTest`.
كل اختبارات `Booking` و`Money` و`Reminders` و`DailyReportTest` الموجودة (271) نجحت **دون أي تعديل عليها**، وهذا يؤكد التوافق الخلفي.

---

## 7. حدود معروفة (خارج النطاق عمداً)

| البند | التفصيل |
|---|---|
| `AccountDeletionService` | يلغي المواعيد **المستقبلية** بـ UPDATE خام. إذا بدأت كتلة الصباح وبقيت كتلة العصر، يبقى الصباح PENDING وفاتورته تشمل العصر الملغى حتى أي rebuild لاحق. الحساب يُحذف ويُجهَّل، فالأثر شكلي |
| `StaffDashboard::updateAppointment()` | ما زال يرقّع مدة **أول** خدمة فقط عند التعديل. ضرره انحصر الآن داخل كتلة ضيقة |
| `GapAnalysisService::hasFullDayTimeOff` | ما زال فيه فخ `end_date >= date` (NULL) الذي أُصلح في BOOK-04 بأماكن أخرى |
| الحذف | حذف أب له أبناء نشطون **ما زال ممنوعاً** (الحذف غير الإلغاء — DEL-01) |
| إحصائيات «عدد المواعيد» | تعدّ الصفوف، فالحجز المقسوم = موعدان. هذا صحيح دلالياً لأنهما زيارتان في التقويم |
| تطبيق الموبايل | يعمل كما هو (بطاقتان). عرض «جزء من حجز» اختياري عبر `group_root_id` — موثَّق في `API.md` |

---

## 8. التحقق اليدوي المقترح
1. من التطبيق أو Postman: `POST /api/bookings` بخدمتين (09:40 و15:10) لنفس المزود → `201`، و`data.end_time` = `10:00`، و`data.linked_appointments[0].start_time` = `15:10`.
2. `GET /api/bookings` → بطاقتان.
3. StaffDashboard في ذلك اليوم → بطاقتان في عمود المزود مع خط ربط، والفجوة قابلة للسحب والحجز.
4. ألغِ بطاقة الصباح من التطبيق → بطاقة العصر تبقى، وفي اللوحة تصبح هي الأب، وافتح الدفع: المبلغ = سعر العصر فقط.
5. ادفع → فاتورة واحدة برقم تسلسلي، والكتلة الملغاة تبقى ملغاة.
