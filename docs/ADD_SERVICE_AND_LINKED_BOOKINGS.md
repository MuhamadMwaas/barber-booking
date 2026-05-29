# Add Service to Existing Booking — Smart Push & Linked Bookings

> **آخر تحديث:** 2026-05-27
> **النطاق:** Staff Dashboard
> **الـ Branch:** `main`
> **الـ Compliance:** TSE/Fiskaly (KassenSichV) محفوظ بالكامل

هذا الملف يصف بشكل مفصّل جداً المهمة التي تم تنفيذها، **ماذا تغيّر، أين، ولماذا**، بحيث يمكن لأي مطوّر أو AI آخر:
1. أن يفهم البزنس وراء الميزة.
2. أن يفهم بنية الكود الجديدة.
3. أن يصلح bug أو يطوّر الميزة بدون أن يكسر الـ TSE compliance.

---

## 1. الـ Business Context

### 1.1 المشكلة قبل التنفيذ

في الـ Staff Dashboard، الموظف يستطيع إنشاء حجز كامل بخدمات متعددة من البداية، لكن **لا يستطيع إضافة خدمة بعد إنشاء الحجز**. السيناريو الواقعي:

> الزبون أحمد جاء ليأخذ قصة شعر (30 دقيقة). أثناء جلوسه قال: "أبدي أيضاً حلاقة لحية" (20 دقيقة). الموظف يحتاج إضافة الخدمة الجديدة لنفس الحجز.

السيناريو الأعقد:
- بعد الـ 30 دقيقة، هناك حجز آخر لزبون آخر (10:40 → 11:00).
- لا يوجد متّسع كافٍ للحلاقة الإضافية.
- نحتاج **دفش (تأجيل) ذكي** للحجوزات التالية فقط بالقدر اللازم.

السيناريو الثالث:
- الخدمة الجديدة "مساج" يجب أن يقدّمها مزود مختلف.
- نحتاج إنشاء **حجز جديد** مرتبط بالحجز الأصلي.
- **فاتورة واحدة** فقط للحجزين معاً (شرط ضريبي + TSE).

### 1.2 المتطلبات النهائية المتفق عليها

| # | المتطلب | ملاحظة |
|---|---------|--------|
| 1 | إضافة خدمة فقط لحجوزات `PENDING` + `DRAFT invoice` | شرط TSE Compliance |
| 2 | إضافة قبل أو بعد، بفجوة ≤ 60 دقيقة بين بداية الحجز الأصلي ونهاية الخدمة الجديدة | منع خدمات بعيدة |
| 3 | عند نقص المساحة: خيار "تقليل المدة" أو "دفش الحجوزات التالية" | UX واضح |
| 4 | دفش cascading conditional: كل حجز يُدفش فقط بالقدر اللازم | بعض الحجوزات قد تُدفش 5 دقائق وأخرى 20 |
| 5 | منع كامل للدفش لو في الـ chain حجز **مدفوع** | TSE invariant |
| 6 | منع الدفش لو يتجاوز ساعات عمل المزود | حماية الـ schedule |
| 7 | حفظ `original_start_time/end_time` (نسخة أصلية واحدة فقط) | تتبّع |
| 8 | إشعار push للعملاء المسجلين عند الدفش | UX |
| 9 | مزود مختلف ⇒ ينشأ Appointment ابن مرتبط بالأب عبر `parent_appointment_id` | بنية parent/child |
| 10 | الابن لا يمتلك فاتورة. الفاتورة دائماً على الأب وتشمل كل خدمات الأبناء | TSE: فاتورة واحدة موقَّعة |
| 11 | وصف كل بند في الفاتورة يحتوي اسم المزود | "Haircut — by Ahmed" |
| 12 | الدفع من أي حجز في المجموعة يدفع الكل (parent + children → COMPLETED) | atomic |
| 13 | منع إلغاء/حذف الأب لو فيه أبناء غير ملغية | حماية الفاتورة |
| 14 | خط ربط بصري في الـ timeline بين الأب والأبناء | UX |
| 15 | زر طباعة في Appointment Modal فقط بعد الدفع | TSE: لا طباعة قبل التوقيع |
| 16 | مستوى تداخل واحد فقط (no grandchildren) | بساطة وثبات |

---

## 2. خريطة التغييرات (Files Changed Summary)

| نوع | الملف | الحالة |
|-----|------|--------|
| Migration | `database/migrations/2026_05_27_030322_add_linked_bookings_to_appointments_table.php` | جديد |
| Model | `app/Models/Appointment.php` | معدَّل |
| Model | `app/Models/Invoice.php` | معدَّل |
| Service | `app/Services/GapAnalysisService.php` | جديد |
| Service | `app/Services/PushBookingsService.php` | جديد |
| Service | `app/Services/AppointmentLinkingService.php` | جديد |
| Service | `app/Services/BookingService.php` | معدَّل (إضافة `addServiceToBooking`) |
| Service | `app/Services/InvoiceService.php` | معدَّل (إضافة `rebuildAggregatedInvoice` + child guard) |
| Service | `app/Services/InvoiceFinalizationService.php` | معدَّل (group-aware finalize) |
| Service | `app/Services/DashboardService.php` | معدَّل (eager load parent/children) |
| Notification | `app/Notifications/BookingPushedNotification.php` | جديد |
| Exception | `app/Exceptions/PushRequiredException.php` | جديد |
| Livewire | `app/Livewire/StaffDashboard.php` | معدَّل (10+ methods جديدة) |
| View | `resources/views/livewire/staff-dashboard.blade.php` | معدَّل (modals جديدة + SVG overlay + buttons) |
| Lang | `lang/en/dashboard.php` | معدَّل (أقسام add_service / push_preview / linked / print) |
| Lang | `lang/ar/dashboard.php` | معدَّل |
| Lang | `lang/de/dashboard.php` | معدَّل |
| Lang | `lang/en/notification.php` | معدَّل (مفاتيح booking_pushed) |
| Lang | `lang/ar/notification.php` | معدَّل |
| Lang | `lang/de/notification.php` | معدَّل |

**الإجمالي:** 3 ملفات جديدة + 11 ملف معدَّل + 1 migration + ترجمات.

---

## 3. Phase-by-Phase تفصيلي

### Phase 1 — Database Migration

**الملف:** `database/migrations/2026_05_27_030322_add_linked_bookings_to_appointments_table.php`

**الأعمدة المضافة لجدول `appointments`:**

| العمود | النوع | nullable | الغرض |
|--------|-------|----------|-------|
| `parent_appointment_id` | `unsignedBigInteger` | ✓ | FK ذاتي إلى `appointments.id` — يحدّد الحجز الأب لو هذا حجز ابن |
| `original_start_time` | `datetime` | ✓ | الوقت الأصلي قبل أول عملية دفش |
| `original_end_time` | `datetime` | ✓ | نهاية الوقت الأصلي قبل أول دفش |
| `was_pushed` | `boolean` | ✗ (default false) | flag سريع لإظهار badge في الـ UI |
| `last_pushed_at` | `datetime` | ✓ | متى تمت آخر عملية دفش |

**الـ FK behavior:** `onDelete: nullOnDelete`. السبب: لو شخص حذف الأب يدوياً من DB (نادر جداً، نمنعه على مستوى application)، الأبناء يصبحون مستقلين بدل أن يُحذفوا.

**الـ Index:** `appointments_parent_id_idx` على `parent_appointment_id` — لتسريع query `where('parent_appointment_id', ?)` (يُستدعى عند رسم الـ timeline).

---

### Phase 2 — Models

#### `app/Models/Appointment.php`

**أعمدة جديدة في `$fillable` و `$casts`:** كل الأعمدة من Phase 1، مع cast `boolean` لـ `was_pushed`, و`datetime` للأوقات.

**Relationships جديدة:**
```php
public function parent(): BelongsTo
{
    return $this->belongsTo(self::class, 'parent_appointment_id');
}

public function children(): HasMany
{
    return $this->hasMany(self::class, 'parent_appointment_id');
}

public function linkedGroup(): Builder
{
    $rootId = $this->parent_appointment_id ?? $this->id;
    return self::query()->where(function ($q) use ($rootId) {
        $q->where('id', $rootId)->orWhere('parent_appointment_id', $rootId);
    });
}
```

`linkedGroup()` تُستخدم في كل مكان نحتاج فيه "كل الحجوزات المرتبطة معاً" — سواء كان `$this` هو الأب أو ابن.

**Accessors جديدة:**
- `is_parent_booking` — true لو الحجز ليس ابناً ولديه أبناء.
- `is_child_booking` — true لو `parent_appointment_id !== null`.
- `is_standalone_booking` — true لو لا أب ولا أبناء.
- `invoice_owner` — يُرجع الحجز الذي يحمل الفاتورة (الأب أو self).

**Scopes جديدة:**
- `scopeParentsOnly` — `WHERE parent_appointment_id IS NULL`
- `scopeForProvider($providerId)` — هلبر بسيط
- `scopePushable($providerId, $afterTime, $date)` — حجوزات قابلة للدفش (نفس المزود، نفس اليوم، `created_status=1`، ليست مدفوعة، ليست ملغية، ليست مكتملة، تبدأ بعد `$afterTime`).

**Business Guards جديدة:**
```php
public function canAcceptNewService(): bool
```
**Single Source of Truth** للإجابة على: "هل يمكنني إضافة خدمة لهذا الحجز؟". يرفض إذا:
- الـ status ليس `PENDING`.
- الـ payment_status أحد قيم النجاح (`PAID_*`).
- الفاتورة موجودة وليست `DRAFT`.
- إذا الحجز ابن: الأب أيضاً يجب أن يقبل.

```php
public function canBeCancelledOrDeleted(): array
```
يُرجع `['allowed' => bool, 'reason' => ?string, 'children_numbers' => ?array]`. يرفض الإلغاء/الحذف لو فيه أبناء **غير ملغية**.

#### `app/Models/Invoice.php`

**Helper جديد:**
```php
public function getCoveredAppointments()
```
يُرجع `Collection<Appointment>` — كل الحجوزات (الأب + الأبناء) التي تنتمي لهذه الفاتورة، مرتبة (الأب أولاً، ثم الأبناء بالـ start_time).

```php
public function isAggregated(): bool
```
true لو الفاتورة تغطي أكثر من حجز (أي الأب فيه أبناء).

---

### Phase 3.A — GapAnalysisService

**الملف:** `app/Services/GapAnalysisService.php` (جديد)

**الهدف:** طبقة **تحليل خالصة** — لا تمسّ DB. تحسب فقط إذا كانت الخدمة الجديدة تتسع، وأين، وهل تحتاج دفشاً.

**المفهوم الجوهري:** `MAX_GAP_MINUTES = 60` — الحد الأقصى للفجوة بين الخدمة الجديدة والحدود الزمنية للحجز الأصلي.

**3 Public Methods:**

#### `analyzeAddBefore(Appointment $anchor, Service $service, int $duration, ?Carbon $requestedStart)`
يحلل إضافة خدمة **قبل** الحجز الأصلي (نفس المزود).
- يجد آخر حجز ينتهي قبل `$anchor->start_time`.
- يحسب `max_duration_available` = من نهاية الحجز السابق (أو بداية الدوام) إلى بداية الحجز الأصلي.
- إذا `duration > max` → يرجع `requires_reduction=true`.
- يتحقق من time-offs ساعية وكاملة، وأن الفجوة ≤ 60 دقيقة.
- **لا يدعم الدفش للخلف** (الحجوزات السابقة لا تُدفش).

#### `analyzeAddAfter(Appointment $anchor, Service $service, int $duration, ?Carbon $requestedStart)`
يحلل إضافة خدمة **بعد** الحجز الأصلي (نفس المزود). **قد يتطلب دفشاً.**
- يحسب نهاية الخدمة الجديدة.
- يتحقق من ساعات العمل والفجوة وأي time-off.
- يجد الحجز التالي للمزود.
- لو لا يوجد تالٍ، أو لا يتعارض → `is_possible=true, requires_push=false`.
- لو يتعارض → يستدعي `PushBookingsService::planPushFrom()` ويُرجع `requires_push=true` مع `push_plan`.
- لو الـ plan تعطّل بسبب حجز مدفوع → يُرجع `is_possible=false, push_blocked_by_paid=true`.

#### `analyzeChildAdd(Appointment $invoiceOwner, User $newProvider, Service $service, int $duration, string $placement, ?Carbon $requestedStart)`
لإضافة خدمة بمزود مختلف (سيُنشأ حجز ابن).
- مرجع الفجوة: حدود الـ `invoiceOwner` (الأب أو self).
- مرجع التعارض: schedule + bookings للمزود الجديد.
- **لا push** — لو الوقت محجوز عند المزود الجديد، يفشل مباشرة.

**Return shape موحَّد (مفاتيح أساسية):**
```php
[
    'is_possible' => bool,
    'reason' => ?string,                   // when not possible
    'suggested_start_time' => ?string,     // 'H:i'
    'suggested_end_time' => ?string,
    'gap_minutes' => ?int,
    'requires_push' => bool,
    'requires_reduction' => bool,
    'max_duration_available' => ?int,      // for reduce-button UI
    'push_plan' => ?array,                 // when requires_push=true
    'push_blocked_by_paid' => ?bool,       // when push refused by paid booking
    'blocking_appointment_number' => ?string,
]
```

---

### Phase 3.B — PushBookingsService

**الملف:** `app/Services/PushBookingsService.php` (جديد)

**المفهوم الجوهري:** **Cascading Conditional Push** — لكل حجز بالترتيب: لو يتعارض مع الحد الزمني الحالي، يُدفش بالقدر اللازم فقط؛ وإلا → توقف السلسلة.

#### `planPushFrom(User $provider, Carbon $fixedUntil, Appointment $firstCandidate, int $excludeAnchorId)`
لا يمسّ DB. يُرجع plan.

```php
foreach ($candidates as $appt) {
    if ($appt->start_time >= $currentBoundary) break; // chain stops
    if ($appt->payment_status->isSuccessful()) return ['is_possible' => false, 'reason' => 'paid_booking_in_chain'];

    $pushMinutes = $appt->start_time->diffInMinutes($currentBoundary);
    $newStart = $currentBoundary;
    $newEnd = $newStart->copy()->addMinutes($appt->duration_minutes);

    if ($newEnd > $workEnd) return ['is_possible' => false, 'reason' => 'exceeds_work_hours'];

    $plan[] = [...];
    $currentBoundary = $newEnd; // next iteration uses NEW boundary
}
```

**المثال الذي ذُكر في الـ requirements:**
```
الجدول: A(10:00-10:30), B(10:40-11:00), C(11:10-11:40), D(11:50-12:20)
إضافة 20 دقيقة بعد A → newEnd = 10:50.

Iteration 1 (B):
  B.start = 10:40 < 10:50 → conflict
  pushMinutes = 10:50 - 10:40 = 10
  B الجديد: 10:50 → 11:10
  currentBoundary = 11:10

Iteration 2 (C):
  C.start = 11:10 >= 11:10 → no conflict → BREAK

النتيجة:
  A: 10:00 → 10:50 ✓
  B: 10:50 → 11:10 (دفش 10 دقائق) ✓
  C: 11:10 → 11:40 (لا تغيير) ✓
  D: 11:50 → 12:20 (لا تغيير) ✓
```

#### `executePushPlan(array $plan, string $date): int[]`
يطبّق الـ plan على DB. **يجب أن يُستدعى داخل DB transaction** من الـ caller (`BookingService::addServiceToBooking`).

- يحفظ `original_start_time/end_time` **فقط عند الدفشة الأولى** (`!$appt->was_pushed`) — الدفشات اللاحقة لا تستبدل الأصلي.
- يضع `was_pushed=true, last_pushed_at=now()`.
- يُرسل `BookingPushedNotification` للعملاء المسجلين عبر `NotificationService` (push + DB).
- فشل الـ notification لا يكسر الـ transaction (try/catch داخلي).

---

### Phase 3.C — AppointmentLinkingService

**الملف:** `app/Services/AppointmentLinkingService.php` (جديد)

يحرس الـ invariants:

#### `getInvoiceOwner(Appointment $appointment): Appointment`
- لو الحجز ابن → يُرجع الأب.
- وإلا → يُرجع self.

#### `validateChildCandidate(Appointment $parent, array $childData): void`
يرمي `InvalidArgumentException` إذا:
- الأب نفسه ابن (single-level violation).
- الأب لا يقبل خدمات جديدة (`!canAcceptNewService()`).
- التاريخ يختلف.

#### `linkAsChild(Appointment $child, Appointment $parent): void`
يستدعي validate ثم يضبط `child->parent_appointment_id = $parent->id`.

#### `validateAddServiceToChild(Appointment $child): void`
عند إضافة خدمة لحجز ابن: نتحقق أن الابن يقبل **و** الأب يقبل. لو الأب مدفوع → خطأ.

---

### Phase 3.D — BookingService::addServiceToBooking

**الملف:** `app/Services/BookingService.php` (modified)

**التغيير في الـ constructor:** تم تحويله إلى constructor promotion مع 4 dependencies (`BookingValidationService`, `GapAnalysisService`, `PushBookingsService`, `AppointmentLinkingService`). الثلاثة الجدد lazy-resolved من container لو لم يتم حقنهم (يحافظ على backward compat).

#### `addServiceToBooking(Appointment $anchor, array $data): array`

**Signature:**
```php
[
    'service_id' => int,
    'provider_id' => int,
    'placement' => 'before'|'after',
    'duration_minutes' => ?int,   // null → service.duration_minutes
    'start_time' => ?string,      // null → auto-compute
    'apply_push' => bool,         // false → throws PushRequiredException
]
```

**Return:**
```php
[
    'mode' => 'same_provider' | 'child_created',
    'appointment' => Appointment, // anchor or new child
    'pushed_appointments' => int[],
    'invoice' => Invoice,         // unified invoice on parent
]
```

**الـ Flow الكامل:**

```
1. Pre-checks: anchor->canAcceptNewService()
2. Validate fields (service_id, provider_id, placement)
3. Validate provider offers service (existing method)
4. Determine mode: same_provider OR child
5. Call GapAnalysis: analyzeAddBefore | analyzeAddAfter | analyzeChildAdd
6. If !is_possible → throw InvalidArgumentException with formatted message
7. If requires_push && !apply_push → throw PushRequiredException($plan)
8. DB::transaction([
     a. PushBookingsService::executePushPlan() if push needed
     b. addServiceSameProvider() OR addServiceDifferentProvider()
     c. InvoiceService::rebuildAggregatedInvoice($invoiceOwner)
   ])
9. Return result
```

#### `addServiceSameProvider()` — Helper
- يُنشئ `AppointmentService` row جديد بـ `sequence_order` مناسب (0 لو before، max+1 لو after).
- لو before → يعيد ترتيب كل الـ services ليكون 1..N.
- يحسب `start_time/end_time` الجديد للـ anchor.
- يعيد حساب `subtotal/tax/total/duration` من **كل** الخدمات (يستخدم الـ `calculateTotals` الموجودة عبر Reflection).

#### `addServiceDifferentProvider()` — Helper
- يحدّد الأب الحقيقي (لو الـ anchor ابن، الأب هو `anchor->parent`).
- يستدعي `validateChildCandidate`.
- يُنشئ Appointment جديد **بـ `parent_appointment_id=$parent->id`** مباشرة في الـ create.
- ينسخ بيانات العميل من الأب (`customer_id`, `customer_name`, `customer_email`, `customer_phone`).
- يضع `payment_status=PENDING, status=PENDING, created_status=1`.
- يُنشئ `AppointmentService` للخدمة الجديدة.
- **لا ينشئ Invoice للـ child** — `InvoiceService` سيرفض ذلك بـ exception.

---

### Phase 4 — Invoice Aggregation

#### `InvoiceService::rebuildAggregatedInvoice(Appointment $parent): Invoice` (جديد)

**القاعدة الذهبية:** يستدعى **فقط على parent أو standalone**. يرمي exception على ابن.

**Algorithm:**
1. ابحث عن الفاتورة `DRAFT` أو أنشئ واحدة.
2. تحقق أن status = DRAFT (نرفض rebuild على فاتورة موقَّعة).
3. احذف كل `InvoiceItem` الحالية.
4. اجمع كل الحجوزات (الأب + الأبناء)، مرتبة (الأب أولاً، ثم start_time).
5. لكل حجز، لكل خدمة، أنشئ `InvoiceItem` بـ `description = "{service_name} — by {provider_name}"`.
6. استخدم `TaxCalculatorService::extractTax()` لحساب net/tax/gross بدقة `bcmath`.
7. أعد حساب `subtotal/tax_amount/total_amount`.
8. **Reconciliation:** لو `(subtotal + tax) != total` بسبب rounding، عدّل `tax` ليطابق.
9. خزّن metadata في `invoice_data->aggregated/appointment_ids/last_rebuilt_at`.

**InvoiceItem::withoutEvents** يُستخدم لتجنب recursive observer calls.

#### `InvoiceService::createDtaftInvoiceFromAppointment()` (modified — guard added)
```php
if ($appointment->is_child_booking) {
    throw new InvalidArgumentException('Cannot create invoice for child');
}
```

#### `InvoiceFinalizationService::updateAppointmentStatus()` (modified)
قبل: يحدّث الحجز الواحد فقط.
بعد: يحدّث **كل الحجوزات في الـ linked group** معاً.

```php
$linkedAppointments = $appointment->linkedGroup()->get();
foreach ($linkedAppointments as $linked) {
    $linked->update([
        'payment_status' => PaymentStatus::from($paymentType),
        'payment_method' => $paymentStatus->label(),
        'status' => $invoiceStatus === InvoiceStatus::PAID
            ? AppointmentStatus::COMPLETED
            : $linked->status,
    ]);
}
```

النتيجة: عند الدفع، **كل** الحجوزات (الأب + الأبناء) → `COMPLETED` + `PAID_*` معاً. atomically.

---

### Phase 5 — Notification Layer

#### `BookingPushedNotification` (جديد)

Notification class قابل للـ Queue، يدعم `database` channel فقط.

**Database payload shape** يتبع النمط الموجود في `PhoneNotification`:
```php
[
    'title_key' => 'notification.booking_pushed_title',
    'message_key' => 'notification.booking_pushed_body',
    'data' => [
        'type' => 'booking_pushed',
        'appointment_id' => ...,
        'appointment_number' => ...,
        'original_start_time' => 'H:i',
        'new_start_time' => 'H:i',
        'pushed_minutes' => int,
    ],
    'params' => [
        'number' => ..., 'time' => ..., 'minutes' => ...,
    ],
]
```

**Push (OneSignal):** يُرسَل بشكل مستقل في `PushBookingsService::executePushPlan` عبر `NotificationService::sendNotificationToUser()` — يحترم device routing والترجمات متعددة اللغات (en/ar/de) كما باقي إشعارات المشروع.

---

### Phase 6 — StaffDashboard Livewire Component

**الـ State الجديد المضاف:**
```php
// Add Service flow
public bool $showAddServiceModal = false;
public ?int $addServiceToAppointmentId = null;
public array $addServiceForm = [
    'category_id', 'service_id', 'provider_id',
    'placement' /* 'before'|'after' */, 'duration_minutes', 'start_time',
];
public array $addServiceAnalysis = [];  // آخر نتيجة من analyzeAddServiceGap

// Push Preview
public bool $showPushPreviewModal = false;
public array $pushPreviewPlan = [];
```

**الـ Methods الجديدة:**

| Method | الغرض |
|--------|-------|
| `openAddServiceModal(int $id)` | يفتح مودال الإضافة، يستخدم `canAcceptNewService()` كحارس |
| `closeAddServiceModal()` | يغلق ويعيد reset للنموذج |
| `analyzeAddServiceGap()` | يُستدعى من Alpine بعد كل تغيير form. يُرجع analysis array |
| `applyMaxDuration()` | زر "Reduce to max" — يضع duration = max_available |
| `confirmAddService(bool $applyPush = false)` | يستدعي `BookingService::addServiceToBooking`. يلتقط `PushRequiredException` ويفتح push preview |
| `confirmPushAndAddService()` | بعد موافقة المستخدم على الـ preview |
| `cancelPushPreview()` | يغلق modal الـ preview |
| `printInvoiceForAppointment(int $id)` | يحلّ owner ثم يطلق event `printInvoice` (موجود في layout) |

**Methods المعدَّلة:**

- `processPayment()` — الآن:
  1. يستخرج `invoiceOwner = $appointment->parent ?? $appointment`.
  2. يستدعي `rebuildAggregatedInvoice()` **قبل** `finalizeDraftInvoice()` — هذا حرج لـ TSE، حيث يضمن أن التوقيع يطبَّق على totals الصحيحة.
  3. `finalizeDraftInvoice` يُحدّث كل الحجوزات في الـ group.

- `cancelAppointment()` / `deleteAppointment()` — يستدعون `canBeCancelledOrDeleted()`. بعد cancel/delete child، يعيدون بناء فاتورة الأب.

- `getTimelineDataFromProviders()` — كل appointment item في الـ payload يحمل الآن:
  ```php
  'parent_appointment_id', 'linked_group_root_id',
  'is_child_booking', 'was_pushed',
  'original_start_time', 'original_end_time'
  ```

#### `DashboardService::getAppointmentsForDate()` (modified)
يحمّل eager: `parent`, `children` (إضافة لـ N+1 prevention).

#### `DashboardService::getAppointmentDetails()` (modified)
يحمّل eager: `parent`, `parent.invoice`, `children`, `children.provider`, `children.services_record`.

---

### Phase 7 — Blade Template

**الـ Modals الجديدة (في `staff-dashboard.blade.php`):**

#### Add Service Modal
- Radio: `before` / `after` (مع reactive Livewire trigger للتحليل).
- Select: Category → Service (filtered via Alpine `servicesForAddCategory()`).
- Input number: Duration (debounced 300ms).
- Select: Provider (default = same as anchor، مع option لكل المزودين الآخرين).
- **Analysis Result Box** — 3 حالات:
  - ✅ `fits_well` (أخضر) — يعرض الـ start/end.
  - ⚠️ `requires_push` (أصفر) — يعرض عدد الحجوزات التي ستُدفش.
  - ✕ `cannot_fit` (أحمر) — يعرض السبب + زر "Reduce to max".
- Button: ديناميكي — `Add Service` لو الـ analysis OK، `Review push & continue` لو يتطلب دفشاً.

#### Push Preview Modal
- جدول يعرض: Booking#, Customer, Original Time (مشطوب), New Time (أحمر), Push minutes (badge).
- Buttons: Cancel / Confirm Push & Add Service.

**Appointment Modal — Additions:**
- Linked-group info box (purple) — يظهر لو child أو parent، مع روابط الـ children.
- Push history badge (amber) — لو `was_pushed`، يعرض original_start_time.
- زر **"+ Add Service"** — يظهر فقط لو `canAcceptNewService()`.
- زر **"Print Invoice"** — يظهر فقط لو `canPrintInvoice()`. يُلصق label "(unified)" للحجوزات المرتبطة.

**Timeline Card — Additions:**
- `data-linked-root` attribute — يُستخدم بواسطة SVG overlay.
- `data-is-child` — للـ styling هوكس مستقبلية.
- Badge `↳` (بنفسجي) لو الحجز ابن.
- Badge `⚠` (كهرماني) لو `was_pushed`.
- Ring بلون `ring-amber-300` للحجوزات المدفوشة.

**SVG Overlay للخطوط البصرية:**
- في الـ `<svg>` بـ `position: fixed`.
- Alpine method `drawLinkedLines()` يجمع كل cards بنفس `data-linked-root`، يحسب coordinates مركز كل card، يرسم خط متقطع بنفسجي بين كل زوج متجاور.
- يُستدعى عبر `scheduleLinkedLineRedraw()` (rAF coalesced) عند:
  - `Livewire.hook('morph.updated')` (بعد كل تحديث).
  - `window.resize`.
  - `scroll` على timeline-container.

**Alpine Helpers الجديدة (داخل `dashboardApp()`):**
- `linkedLines: []` و `_linkedRedrawHandle: null`
- `scheduleLinkedLineRedraw()`, `drawLinkedLines()`
- `onCategoryChange()`, `onServiceChange()`, `servicesForAddCategory()`
- `triggerAnalysis()` — يستدعي `$wire.analyzeAddServiceGap()` بدون انتظار

---

### Phase 8 — Translation Keys

**`lang/{en,ar,de}/dashboard.php` — أقسام جديدة:**
- `add_service.*` — كل نصوص مودال الإضافة (button, title, placement, duration_label, provider_*, fits_well, requires_push, cannot_fit, reasons, reduce_to_max, review_push, confirm, cancel)
- `push_preview.*` — نصوص modal الـ preview
- `linked.*` — badges + info boxes للحجوزات المرتبطة
- `print.*` — زر الطباعة + توضيح "unified"
- `cannot_cancel_has_children`, `cannot_delete_has_children`
- `payment_modal.success` — رسالة نجاح

**`lang/{en,ar,de}/notification.php` — مفاتيح جديدة:**
- `booking_pushed_title` — عنوان push notification
- `booking_pushed_body` — نص الإشعار مع placeholder `:number`, `:time`, `:minutes`

---

## 4. التدفقات الكاملة (End-to-End Flows)

### 4.1 إضافة خدمة لنفس المزود — بدون دفش

```
Staff opens appointment modal
  ↓
Click "+ Add Service"
  ↓
Add Service Modal opens (placement='after' by default, provider=anchor.provider)
  ↓
Select category → service → duration auto-fills from service
  ↓
Alpine triggers $wire.analyzeAddServiceGap() (debounced 300ms)
  ↓
Livewire → GapAnalysisService::analyzeAddAfter()
  ↓
Returns: { is_possible: true, requires_push: false, suggested_start_time, suggested_end_time }
  ↓
UI shows green box "✓ Fits well"
  ↓
User clicks "Add Service"
  ↓
Livewire → BookingService::addServiceToBooking(anchor, {...form, apply_push: false})
  ↓
DB::transaction:
  • Create AppointmentService row (sequence_order = maxSeq + 1)
  • Update anchor.end_time, duration_minutes, totals
  • InvoiceService::rebuildAggregatedInvoice(anchor)
    - Delete current items
    - Re-create from anchor.services_record (1 appointment, multiple services)
    - Recalculate subtotal/tax/total with bcmath
  ↓
Modal closes, timeline refreshes (refreshTimeline event), toast "Service added"
```

### 4.2 إضافة خدمة بمزود مختلف — حجز ابن

```
Staff in Add Service Modal selects a DIFFERENT provider
  ↓
analyzeAddServiceGap() → GapAnalysisService::analyzeChildAdd(invoiceOwner, newProvider, ...)
  ↓
Boundary measured against invoiceOwner.start_time / end_time (≤60min gap)
Conflict measured against newProvider's schedule + bookings
  ↓
If new provider busy → "Selected provider is busy"
If outside hours → "Exceeds work hours"
Otherwise → { is_possible: true, requires_push: false, suggested_start_time, suggested_end_time }
  ↓
User clicks "Add Service"
  ↓
BookingService::addServiceToBooking(anchor, {provider_id: NEW, ...})
  → sameProvider=false → addServiceDifferentProvider()
  ↓
DB::transaction:
  • AppointmentLinkingService::validateChildCandidate(parent, ...)
  • Create new Appointment with parent_appointment_id=parent.id
    - customer_* copied from parent
    - created_status=1
    - status=PENDING, payment_status=PENDING
  • Create AppointmentService for the new service
  • InvoiceService::rebuildAggregatedInvoice(parent)
    - Items now span parent.services + child.services
    - Descriptions: "X — by ProviderA", "Y — by ProviderB"
  ↓
Timeline shows TWO cards (parent + child), connected by purple dashed SVG line
Child card shows ↳ badge linking to parent's number
```

### 4.3 إضافة خدمة مع دفش (cascading conditional)

```
Initial: A(10:00-10:30), B(10:40-11:00 PENDING), C(11:10-11:40 PENDING), D(11:50-12:20 PENDING)
Staff: Add 20-min service after A
  ↓
GapAnalysisService::analyzeAddAfter():
  proposedStart = 10:30, proposedEnd = 10:50
  next = B (10:40)
  proposedEnd (10:50) > B.start (10:40) → PUSH REQUIRED
  ↓
PushBookingsService::planPushFrom(provider, fixedUntil=10:50, firstCandidate=B, exclude=A.id):
  candidates = [B, C, D]
  currentBoundary = 10:50

  B: start=10:40 < 10:50 → conflict
     payment_status = PENDING → OK
     pushMinutes = 10, newStart=10:50, newEnd=11:10
     workEnd check: 11:10 < 21:00 → OK
     plan += [B push 10min]
     currentBoundary = 11:10

  C: start=11:10 >= 11:10 → no conflict → BREAK

  return { is_possible: true, plan: [B] }
  ↓
Returns to analyzeAddAfter:
  { is_possible: true, requires_push: true, push_plan: [B] }
  ↓
UI shows amber box "⚠ Requires push" + button "Review push & continue"
  ↓
User clicks → confirmAddService(false) called → BookingService throws PushRequiredException
  ↓
StaffDashboard catches → pushPreviewPlan = $e->plan, showPushPreviewModal = true
  ↓
Push Preview Modal shows:
  | #APT-XXXX | Customer | 10:40 → 11:00 | 10:50 → 11:10 | +10m |
  ↓
User clicks "Confirm Push & Add Service"
  ↓
confirmPushAndAddService() → confirmAddService(true)
  ↓
BookingService::addServiceToBooking(anchor, {...form, apply_push: true})
  ↓
DB::transaction:
  • PushBookingsService::executePushPlan([B push 10min], date)
    - B.update(start_time=10:50, end_time=11:10, original_start=10:40, original_end=11:00, was_pushed=true, last_pushed_at=now)
    - Notify B's customer via NotificationService (push + DB)
  • addServiceSameProvider on A
    - Create AppointmentService row
    - A.update(end_time=10:50, duration+=20, totals recalc)
  • rebuildAggregatedInvoice(A)
  ↓
Toast: "Service added (1 booking rescheduled)"
B's customer receives push notification: "Your booking #APT-XXXX has been moved to 10:50"
```

### 4.4 دفع الفاتورة الموحدة

```
Staff clicks "Pay" in appointment modal (on parent OR child — doesn't matter)
  ↓
Payment Modal opens (amount = invoice.total_amount)
  ↓
Staff confirms payment type (cash/card) + amount
  ↓
Click "Confirm & Print Invoice"
  ↓
StaffDashboard::processPayment():
  • Resolve invoiceOwner = appointment.parent ?? appointment
  • InvoiceService::rebuildAggregatedInvoice(invoiceOwner)   ← CRITICAL for TSE
  • If staff adjusted total → override invoice.total_amount
  • InvoiceFinalizationService::finalizeDraftInvoice(invoice, ...):
    - Apply TSE signature (placeholder — wires to FiskalyService when integrated)
    - Generate invoice_number
    - Update invoice: status=PAID, invoice_number, invoice_data.tse_data, finalized_at
    - For each appointment in invoice.appointment.linkedGroup():
      - status = COMPLETED
      - payment_status = PAID_ONSTIE_CASH (or _CARD)
      - payment_method = label
    - Create Payment record
  ↓
Dispatch event printInvoice (handled by layouts/dashboard.blade.php → opens /invoice/{id}/print)
```

### 4.5 إلغاء/حذف الحجز

```
Child cancel:
  cancelAppointment() guards: canBeCancelledOrDeleted() returns allowed=true (children check only matters for parents)
  Updates status=ADMIN_CANCELLED
  Then: rebuildAggregatedInvoice(parent) — removes the child's items from the unified invoice

Parent cancel:
  canBeCancelledOrDeleted() detects children → returns allowed=false, children_numbers=[...]
  UI shows: "Cannot cancel: this booking has linked child bookings (#XXX, #YYY). Cancel them first."
```

---

## 5. الـ Invariants الحرجة (DO NOT BREAK)

| # | Invariant | يحرسها |
|---|-----------|--------|
| 1 | TSE-signed invoices are immutable | `rebuildAggregatedInvoice()` يرفض non-DRAFT |
| 2 | A child appointment never owns an invoice | `InvoiceService::createDtaftInvoiceFromAppointment()` يرفض child |
| 3 | Single-level hierarchy (no grandchildren) | `AppointmentLinkingService::validateChildCandidate()` يرفض parent that is itself a child |
| 4 | Cancel/delete parent blocked if children exist | `Appointment::canBeCancelledOrDeleted()` + `StaffDashboard::cancelAppointment()` |
| 5 | Push of paid bookings forbidden | `PushBookingsService::planPushFrom()` — fails entire chain |
| 6 | Pushed booking saves original_* only on first push | `executePushPlan()` checks `!$appt->was_pushed` before backing up |
| 7 | Linked group finalized atomically (all or none) | `InvoiceFinalizationService::updateAppointmentStatus()` loops `linkedGroup()->get()` inside DB transaction |
| 8 | rebuildAggregatedInvoice MUST run before finalize | `StaffDashboard::processPayment()` calls it explicitly before `finalizeDraftInvoice()` |
| 9 | Notifications never break the DB transaction | `executePushPlan()` wraps notify in try/catch with Log::warning |

---

## 6. نقاط التوسعة (Phase 2 — Future)

| الميزة | التعليق |
|--------|---------|
| Multi-level nesting | الـ migration و relationships جاهزة. يحتاج إزالة الـ guard في `validateChildCandidate()` |
| Push backwards (الإضافة قبل) | حالياً لا ندفش الحجوزات السابقة — لو طُلب لاحقاً، أضف `analyzeAddBeforeWithPush()` |
| Adding service to paid booking | يتطلب فاتورة TSE جديدة منفصلة — قرار قانوني/UX |
| Audit log كامل للدفش | جدول `appointment_push_logs` (history of all pushes) |
| Multi-day linked bookings | إزالة date-match guard في `AppointmentLinkingService` |
| Guest customers SMS on push | إضافة SMS provider integration |

---

## 7. الـ Testing Manual Checklist

| # | السيناريو | النتيجة المتوقعة |
|---|----------|----------------|
| 1 | افتح appointment PENDING → Add Service → نفس المزود، duration 10min، after | يضاف بدون دفش، الـ timeline يتحدث |
| 2 | Add Service بـ duration > الفجوة المتاحة | يظهر "Reduce to max XXmin" |
| 3 | Add Service يحتاج دفش حجزين | يظهر "Requires push" → اضغط Review → preview بـ صفّين → confirm |
| 4 | المثال نفسه لكن أحد الحجوزات مدفوع | يظهر "Cannot push — paid booking #XXX in chain" مع max_duration_available |
| 5 | اختر مزود مختلف | يُنشأ حجز ابن، خط بنفسجي بين الـ cards، badge ↳ |
| 6 | حاول إلغاء parent مع children | يفشل: "Cannot cancel: ... cancel children first" |
| 7 | ادفع الفاتورة من **الابن** | الـ parent + child كلاهما COMPLETED + paid، فاتورة موحدة تطبع |
| 8 | افتح Appointment Modal لحجز ابن مدفوع | زر Print موجود، يطبع فاتورة الأب الموحدة |
| 9 | Add Service لحجز ابن (نفس مزود الابن) | يضاف على الابن نفسه، الفاتورة الموحدة على الأب تشمل البندين |
| 10 | حجز مدفوش له `original_start_time` | الأمر يظهر في appointment modal كـ badge أصفر |
| 11 | Add service لحجز مدفوع | الزر مخفي / يظهر error "cannot add: paid" |

---

## 8. Quick Code Map — أين أعدّل ماذا

| أريد أن أعدّل... | اذهب إلى... |
|-----------------|------------|
| منطق دفش الحجوزات | `PushBookingsService::planPushFrom()` |
| منطق فحص المساحة | `GapAnalysisService::analyze*` (3 methods) |
| القاعدة "هل يمكن إضافة خدمة لهذا الحجز؟" | `Appointment::canAcceptNewService()` |
| القاعدة "هل يمكن إلغاء/حذف الحجز؟" | `Appointment::canBeCancelledOrDeleted()` |
| كيف تُبنى الفاتورة الموحدة | `InvoiceService::rebuildAggregatedInvoice()` |
| كيف تنتقل كل الحجوزات معاً إلى COMPLETED | `InvoiceFinalizationService::updateAppointmentStatus()` |
| الـ UI للـ Add Service Modal | `staff-dashboard.blade.php` (ابحث `add_service.title`) |
| الـ UI للـ Push Preview Modal | `staff-dashboard.blade.php` (ابحث `push_preview.title`) |
| الخطوط البصرية بين الـ cards | `dashboardApp().drawLinkedLines()` |
| محتوى إشعار الدفش (text/payload) | `BookingPushedNotification::toDatabase()` + `lang/{en,ar,de}/notification.php` |
| الترجمات | `lang/{en,ar,de}/dashboard.php` (أقسام add_service / push_preview / linked / print) |

---

## 9. أمثلة على API Usage (للـ Programmatic Access)

### إضافة خدمة برمجياً (e.g. لـ test أو CLI)

```php
use App\Models\Appointment;
use App\Services\BookingService;

$anchor = Appointment::find(123);
$booking = app(BookingService::class);

try {
    $result = $booking->addServiceToBooking($anchor, [
        'service_id' => 5,
        'provider_id' => 7,            // same as anchor → augments anchor
        'placement' => 'after',
        'duration_minutes' => 20,
        'apply_push' => false,
    ]);

    // $result['mode'] === 'same_provider' OR 'child_created'
    // $result['appointment']           — the anchor (or new child)
    // $result['pushed_appointments']   — IDs of pushed bookings
    // $result['invoice']               — rebuilt unified invoice

} catch (\App\Exceptions\PushRequiredException $e) {
    // Push needed — present $e->plan to user, retry with apply_push: true
    foreach ($e->plan as $item) {
        echo "Push #{$item['appointment_number']} by {$item['push_minutes']}min\n";
    }
} catch (\InvalidArgumentException $e) {
    // Not possible — error message is user-facing
    echo $e->getMessage();
}
```

### استرجاع الفاتورة الموحدة لحجز

```php
$child = Appointment::with('parent.invoice')->find(456);
$invoice = ($child->parent ?? $child)->invoice;

// أو عبر helper:
$invoiceOwner = app(\App\Services\AppointmentLinkingService::class)
    ->getInvoiceOwner($child);
$invoice = $invoiceOwner->invoice;
```

### فحص هل الحجز يقبل خدمة جديدة

```php
if ($appointment->canAcceptNewService()) {
    // show "Add Service" button
}
```

---

## 10. Files Diff Summary (الـ insertions/deletions تقريبية)

| الملف | +Lines | -Lines | الحالة |
|------|--------|--------|--------|
| `database/migrations/...add_linked_bookings...php` | 45 | 0 | NEW |
| `app/Exceptions/PushRequiredException.php` | 24 | 0 | NEW |
| `app/Notifications/BookingPushedNotification.php` | 50 | 0 | NEW |
| `app/Services/GapAnalysisService.php` | 330 | 0 | NEW |
| `app/Services/PushBookingsService.php` | 200 | 0 | NEW |
| `app/Services/AppointmentLinkingService.php` | 105 | 0 | NEW |
| `app/Models/Appointment.php` | 200 | 5 | MODIFIED |
| `app/Models/Invoice.php` | 30 | 0 | MODIFIED |
| `app/Services/BookingService.php` | 270 | 10 | MODIFIED |
| `app/Services/InvoiceService.php` | 105 | 0 | MODIFIED |
| `app/Services/InvoiceFinalizationService.php` | 30 | 10 | MODIFIED |
| `app/Services/DashboardService.php` | 20 | 7 | MODIFIED |
| `app/Livewire/StaffDashboard.php` | 270 | 30 | MODIFIED |
| `resources/views/livewire/staff-dashboard.blade.php` | 350 | 20 | MODIFIED |
| `lang/en/dashboard.php` | 70 | 0 | MODIFIED |
| `lang/ar/dashboard.php` | 70 | 0 | MODIFIED |
| `lang/de/dashboard.php` | 70 | 0 | MODIFIED |
| `lang/{en,ar,de}/notification.php` | 12 | 0 | MODIFIED |

**التقريبي الإجمالي:** ~2,250 سطر مضاف، ~80 سطر معدّل/محذوف.

---

## 11. الـ Glossary

| المصطلح | المعنى |
|---------|--------|
| **Anchor** | الحجز الذي يضغط الموظف عليه "+ Add Service" |
| **Parent / Child** | حجوزات مرتبطة عبر `parent_appointment_id`. الأب يحمل الفاتورة |
| **Standalone** | حجز ليس أباً ولا ابناً |
| **Linked Group** | المجموعة = الأب + كل أبنائه (مع الأب نفسه إذا كان standalone) |
| **Invoice Owner** | الحجز الذي يحمل الفاتورة (دائماً = الأب أو standalone) |
| **Cascading Conditional Push** | الدفش لا يطبَّق على كل الحجوزات بنفس المقدار، فقط بالقدر اللازم لكل حجز |
| **DRAFT Invoice** | فاتورة بدون `invoice_number`، قابلة للتعديل (لم تُوقَّع TSE بعد) |
| **TSE / Fiskaly** | نظام التوقيع الرقمي الألماني (KassenSichV) — بعد التوقيع، الفاتورة immutable |
| **created_status** | flag على `appointments` (`1=confirmed`, `0=abandoned`) — يحجز الوقت في الـ timeline |

---

## 12. كلمة أخيرة لأي AI/مطور يقرأ هذا

اقرأ القسم **5 — Invariants** قبل أي تعديل. كسر أي واحد منها قد يخالف القانون الألماني (KassenSichV) أو يفسد الفواتير. كل التعديلات على الفاتورة يجب أن تحدث **قبل** `finalizeDraftInvoice()` — بعدها التوقيع TSE يصبح ملزماً. الـ flow الـ correct هو:

```
addServiceToBooking → rebuildAggregatedInvoice (still DRAFT)
                  → ... more addServiceToBooking ... → rebuildAggregatedInvoice
                  → processPayment → rebuildAggregatedInvoice → finalize (SIGNED)
```

بعد `finalize`: لا rebuild، لا تعديل، لا حذف. الفاتورة ميتة من ناحية البيانات وحيّة قانونياً.

