# BOOKING-GAP-01 — خطة التنفيذ: تقسيم الحجز متعدد الخدمات إلى كتل زمنية (أب/أبناء)

> **التاريخ:** 2026-09-25
> **المرجع:** [`BOOKING-GAP-01_multi_service_span_AR.md`](BOOKING-GAP-01_multi_service_span_AR.md) (تحليل المشكلة) — الحل المختار: **الحل 3**
> **الحالة:** خطة معتمدة بعد جولتي أسئلة مع صاحب المشروع
> **ملف الشرح بعد التنفيذ:** [`BOOKING-GAP-01_implementation_AR.md`](BOOKING-GAP-01_implementation_AR.md)

---

## 0. القرارات المتفق عليها

| # | السؤال | القرار |
|---|---|---|
| Q1 | قاعدة التقسيم | خدمات **متلاصقة تماماً** (نهاية = بداية) **وعند نفس المزود** = صف واحد. أي فجوة (ولو دقيقة) أو مزود مختلف = موعد ابن جديد. |
| Q2 | حد الفجوة بين الكتل | **بلا حد** داخل نفس اليوم. كل كتلة تُفحص وحدها (دوام/إجازة/تعارض/عميل حر). |
| Q3 | الدفع | **فاتورة واحدة كما هو اليوم** (MON-05): دفعة واحدة تغطي المجموعة وتُكمل كل الكتل **النشطة** فقط. |
| Q4 | إلغاء الأب مع وجود أبناء نشطين | **ترقية أقرب ابن نشط ليصبح الأب**، تنتقل إليه الفاتورة المسودة ويُعاد ربط البقية به. كل كتلة قابلة للإلغاء الفردي من التطبيق واللوحة وFilament. |
| Q5 | عقد `POST /api/bookings` | **متوافق خلفياً**: `data` يبقى الموعد الأب بنفس الشكل + حقول جديدة. |
| Q6 | البيانات القديمة | **لا توجد بيانات حقيقية** → لا أمر ترحيل/تقسيم. |
| Q7 | الآثار الجانبية | الحد اليومي يعدّ **المجموعة**، تنبيه الإلغاء يعدّ **المجموعة**، **تذكير لكل كتلة**، **إيميل واحد يسرد كل الكتل**. |
| Q8 | الموبايل | باك-إند + توثيق للموبايل (التطبيق الحالي يعمل دون تحديث). |

---

## 1. المفاهيم الجديدة (المصطلحات الموحدة)

- **كتلة (Block):** مجموعة خدمات متتالية بلا فجوة عند نفس المزود. تُخزَّن كصف `appointments` واحد، فيه `start/end` = حدود الكتلة، و`duration_minutes` = مجموع مدد خدماتها. **النتيجة: `end - start == duration_minutes` دائماً لكل صف ينشئه الحجز.**
- **مجموعة (Group):** كل الكتل الناتجة عن طلب حجز واحد. الجذر هو أبكر كتلة (`parent_appointment_id = NULL`)، والبقية أبناء (`parent_appointment_id = root.id`). بنية مستوى واحد، نفس بنية `AppointmentLinkingService`.
- **عضو نشط في المجموعة:** صف حالته ليست `USER_CANCELLED` ولا `ADMIN_CANCELLED` ولا `NO_SHOW`. الفاتورة والدفع والمبالغ المعروضة تُبنى من الأعضاء النشطين **فقط**.
- **مفتاح المجموعة:** `COALESCE(parent_appointment_id, id)`. هو المستعمل في عدّ الحجوزات اليومية وعدّ الإلغاءات.

---

## 2. خريطة التغييرات حسب الملف

### 2.1 `app/Models/Appointment.php`
1. ثابت `INACTIVE_GROUP_STATUSES` = `[USER_CANCELLED, ADMIN_CANCELLED, NO_SHOW]`.
2. `scopeActiveInGroup(Builder)`: `whereNotIn('status', …)`.
3. `activeLinkedGroup(): Builder`، وهي = `linkedGroup()->activeInGroup()`.
4. `getGroupRootIdAttribute(): int`، وقيمتها = `parent_appointment_id ?? id`.
5. `isActiveInGroup(): bool`.
6. `cancel()` (مسار العميل) يُفوِّض إلى `AppointmentCancellationService` بدل الكتابة المباشرة، ثم يُحدِّث حالة النموذج في الذاكرة ويُرجع `true`. **العقد العام للدالة لا يتغير**، فالمستدعون الحاليون والاختبارات تبقى صالحة.
7. تحديث توثيق `canBeCancelledOrDeleted()`: صارت للحذف فقط، والإلغاء لا يمنع الأب بعد اليوم.

### 2.2 `app/Services/BookingService.php` — قلب التغيير
1. `createBooking()`:
   - بعد `validateAndPrepareServices()` (بلا تغيير في فحوصه لكل خدمة): `$blocks = $this->splitIntoBlocks($preparedServices)`.
   - لكل كتلة: `calculateTotals($block)`، ثم إنشاء صف `Appointment` بحدود الكتلة ومزودها ومبالغها.
     - الكتلة الأولى هي الجذر.
     - البقية تأخذ `parent_appointment_id = root.id`، مع `validateChildCandidate` دفاعياً.
   - بيانات العميل، والملاحظات، و`booking_source`، و`is_override`/`override_reason`، و`payment_method`، و`status`، و`created_status`: تُنسخ لكل كتلة (مزود كل كتلة يرى الملاحظات).
   - صفوف `appointment_services` لكل كتلة بترقيم `sequence_order` يبدأ من 1 داخل الكتلة.
   - الفاتورة: `createDtaftInvoiceFromAppointment(root)` كما اليوم. إذا وُجد أبناء → `rebuildAggregatedInvoice(root)` لتضم خدمات كل الكتل.
   - يُرجِع **الجذر** محمّلاً بـ `children.services_record` و`children.provider`. توقيع الدالة لا يتغير (`Appointment`).
   - كل ما سبق داخل **نفس المعاملة ونفس القفل** الحاليين: أي رفض في أي كتلة يُلغي المجموعة كلها (ROLLBACK).
2. دالة جديدة `splitIntoBlocks(array $prepared): array`: كتلة جديدة عند (مزود مختلف) أو (`start != previous end`).
3. استخراج `createBlockAppointment()` لتجنب تكرار `Appointment::create` / `AppointmentService::create`.
4. `addServiceToBooking()` (وضع الابن): مرجع الفجوة يصبح **الكتلة التي ضغطها الموظف (anchor)** بدل الجذر، لأن الجذر قد يكون كتلة الصباح، فتُرفض كل إضافة بجانب كتلة العصر بـ `gap_too_large`. الابن الجديد يبقى مربوطاً بالجذر (مستوى واحد).
5. `getBookingDetails()`: تحميل `children` إضافياً.

### 2.3 `app/Services/BookingValidationService.php`
1. `validateSequentialTiming()`: يبقى منع التداخل. يُحذف فرع `> 120` الميت، ويُوثَّق أن الفجوة صارت مشروعة لأنها تُقسَّم ولا تُحجب.
2. `validateDailyBookingLimit()`: عدّ `COUNT(DISTINCT COALESCE(parent_appointment_id, id))` بدل `count()`.

### 2.4 ملف جديد `app/Services/AppointmentCancellationService.php` — المسار الوحيد للإلغاء
على نمط `AppointmentDeletionService` (مسار واحد، أقفال بنفس الترتيب):

```text
cancel(Appointment, AppointmentStatus $status, ?string $reason): Appointment
  - الحالة المطلوبة يجب أن تكون USER_CANCELLED أو ADMIN_CANCELLED
  - DB::transaction:
      1. قفل الجذر ثم كل صفوف المجموعة (orderBy id, lockForUpdate)
         — نفس ترتيب InvoiceFinalizationService / AppointmentDeletionService (لا deadlock)
      2. إعادة الفحص على الصف المقفول: PENDING وغير مدفوع، وإلا InvalidArgumentException
      3. كتابة الحالة + cancelled_at + cancellation_reason
      4. إذا كان الصف الجذر وله أبناء نشطون → promoteNewRoot():
           newRoot = أبكر ابن نشط (start_time ثم id)
           newRoot.parent_appointment_id = null
           كل البقية (بما فيها الجذر الملغى) → parent_appointment_id = newRoot.id
           الفاتورة المسودة: invoices.appointment_id = newRoot.id
           rebuildAggregatedInvoice(newRoot)
         إذا كان الصف ابناً وجذره نشط → rebuildAggregatedInvoice(root)
         إذا كان مستقلاً → لا شيء (كما اليوم)
  - بعد الـ commit: إذا USER_CANCELLED → CancellationMonitor::recordCustomerCancellation()
```

لماذا يُعاد ربط الجذر الملغى كابن للجذر الجديد بدل فصله؟ ليبقى أثره في المجموعة (مفتاح المجموعة لا يتغير في العدّ)، فإلغاء الكتلتين = **إلغاء حجز واحد** في `CancellationMonitor`، كما اتُّفق في Q7.

### 2.5 مستدعو الإلغاء (يتحولون كلهم للخدمة)
| المسار | الملف | التغيير |
|---|---|---|
| العميل `/api/bookings/{id}/cancel` و`/api/appointments/{id}/cancel` | `Appointment::cancel()` | يفوّض للخدمة (USER_CANCELLED) |
| الموظف في اللوحة | `StaffDashboard::cancelAppointment()` | يُزال منع «للأب أبناء نشطون» ومعه الـ rebuild اليدوي → الخدمة (ADMIN_CANCELLED) |
| Filament | `AppointmentsTable` إجراء `cancel` | الخدمة (ADMIN_CANCELLED) |
| حذف الحساب | `AccountDeletionService` | **بلا تغيير**: يلغي كل مواعيد العميل المستقبلية دفعة واحدة، فالمجموعة تُلغى كاملة عادة (مذكور كحد معروف) |

### 2.6 الفاتورة والدفع — استثناء الأعضاء غير النشطين
1. `InvoiceService::rebuildAggregatedInvoice()`: `linkedGroup()` ← `activeLinkedGroup()` (مع `appointment_ids` في `invoice_data`).
2. `Invoice::getCoveredAppointments()`: الأعضاء النشطون فقط (الطباعة).
3. `InvoiceFinalizationService::finalizeAppointmentPayment()`:
   - يقفل كل المجموعة كما اليوم.
   - يرفض فقط إذا كان **الموعد المطلوب نفسه** غير نشط أو **لا يوجد عضو نشط**.
   - يُكمل ويُسجّل `covered_appointment_ids` للنشطين فقط.
   - هذا يصلح خللاً قائماً اليوم: كان إلغاء ابن واحد **يمنع دفع المجموعة كلها**.
4. المبلغ المقترح في نافذة الدفع: `StaffDashboard::openPaymentModal()`، و`AppointmentsTable` (سطران)، و`Providers/AppointmentsRelationManager` (سطران). كلها `linkedGroup()->sum` ← `activeLinkedGroup()->sum`.

### 2.7 `app/Services/CancellationMonitor.php`
`recentCancellationCount()` يعدّ `COUNT(DISTINCT COALESCE(parent_appointment_id, id))`.

### 2.8 الـ API
1. `AppointmentResource` — حقول جديدة (إضافية فقط، لا شيء يُحذف أو يُعاد تسميته):
   - `parent_appointment_id`، `group_root_id`، `is_child_booking`
   - `linked_appointments`: عند تحميل `children` فقط، عبر مورد مختصر جديد `LinkedAppointmentResource`.
   - `group_total_amount`: عند تحميل `children` فقط، وهو مجموع الأعضاء النشطين.
2. ملف جديد `app/Http/Resources/LinkedAppointmentResource.php`: `id, number, start/end, time_range, duration, status, provider{id, full_name}, services_details, total_amount`.
3. `BookingController::store()`: التذكير يُجدول **لكل كتلة** (الجذر + الأبناء).
4. `AppointmentService::getAppointmentDetails()`: تحميل `children`.
5. القوائم (`/bookings`، `/appointments`) **بلا تغيير**: كل كتلة تظهر كبطاقة مستقلة تلقائياً لأن `customer_id` على كل صف.

### 2.9 الإيميل
1. `BookingMailService::sendForNewBooking()`: `loadMissing` لـ `children.services_record` و`children.provider`. `SerializesModels` يعيد تحميل العلاقات المتداخلة في الـ queue.
2. `resources/views/emails/booking/partials/details.blade.php`:
   - إذا للجذر أبناء نشطون → قسم «مواعيدك» يسرد كل كتلة (الوقت، المزود، الخدمات) + المجموع الكلي للمجموعة.
   - وإلا → **القالب الحالي حرفياً** بلا تغيير.
3. مفاتيح ترجمة جديدة في `lang/{ar,de,en}/booking_email.php`.

### 2.10 لوحة الموظفين
1. `saveBookingFromAlpine()` / `saveBooking()`: رسالة النجاح تسرد أرقام كل الكتل.
2. `analyzeAddServiceGap()`: وضع الابن يُقاس على الـ anchor (مطابق لـ 2.2-4).
3. الـ Timeline **بلا تغيير**: يرسم الأبناء وخطوط الربط أصلاً، والآن كل كتلة بنافذتها الضيقة.

### 2.11 الترجمة
- `lang/{ar,de,en}/booking.php`: `only_pending_can_be_cancelled`.
- `lang/{ar,de,en}/dashboard.php`: رسالة الحفظ متعددة الكتل (إن لزم).
- `lang/{ar,de,en}/booking_email.php`: `appointments_heading`، `appointment_n`، `group_total`.

---

## 3. ما لا يتغير عمداً (حدود النطاق)

| البند | السبب |
|---|---|
| `scopeBlocksProviderTime` / `scopeOverlapping` | صحيحان. المشكلة كانت في شكل الصف لا في القاعدة. كل صف الآن نافذته حقيقية. |
| `ServiceAvailabilityService` | يعرض فتحات لخدمة واحدة، وهذا صار صحيحاً: كل كتلة تُفحص وحدها. |
| `assertCustomerIsFree` لكل خدمة | صار متسقاً مع فحص المزود (كلاهما لا يحجب الفجوة). |
| `AppointmentDeletionService` | يبقى يمنع حذف أب له أبناء نشطون (الحذف غير الإلغاء). |
| `updateAppointment()` في اللوحة (يرقّع أول خدمة) | خلل مستقل، وضرره انحصر الآن داخل كتلة ضيقة. يُوثَّق فقط. |
| فخ `end_date >= date` في `GapAnalysisService::hasFullDayTimeOff` | خارج النطاق. يُوثَّق فقط. |
| أمر تقسيم البيانات القديمة | لا توجد بيانات حقيقية (Q6). |
| تطبيق الموبايل | ليس في هذا المستودع. يُوثَّق له العقد الجديد (Q8). |

---

## 4. التوافق الخلفي

| المستهلك | قبل | بعد |
|---|---|---|
| طلب `POST /api/bookings` | نفسه | نفسه، لا تغيير في الحقول |
| استجابة `POST /api/bookings` | `data` = الصف الوحيد | `data` = الجذر (نفس المفاتيح) + مفاتيح إضافية |
| قائمة المواعيد في التطبيق | بطاقة واحدة 09:40–15:10 | بطاقتان 09:40–10:00 و15:10–15:45 (بلا تحديث للتطبيق) |
| زر `Buchung stornieren` | يلغي كل شيء | يلغي الكتلة المعروضة فقط (ميزة جديدة: الإلغاء الفردي) |
| حجز خدمتين متلاصقتين عند نفس المزود | صف واحد | صف واحد (لا تغيير) |

---

## 5. خطة الاختبارات — ملف جديد `tests/Feature/Booking/MultiServiceSplitTest.php`

| # | السيناريو | المتوقع |
|---|---|---|
| T1 | خدمتان متلاصقتان عند نفس المزود | صف واحد، بلا أبناء (سلوك اليوم) |
| T2 | خدمتان عند نفس المزود بفجوة (10:00 + 14:00) — **حالة البلاغ** | جذر 10–11 + ابن 14–15، ولكل صف `end-start == duration`، فاتورة واحدة على الجذر فيها بندان = 150 |
| T3 | الفجوة لم تعد محجوبة | عميل آخر يحجز 12:00 عند نفس المزود → 201 |
| T4 | مزودان مختلفان متلاصقان | صفّان، والابن باسم المزود الثاني؛ المزود الثاني صار محجوباً فعلاً (409) — يصلح خلل §4.5 |
| T5 | ثلاث خدمات: اثنتان متلاصقتان ثم فجوة | صفّان، والجذر فيه خدمتان |
| T6 | شكل الاستجابة | `data.linked_appointments` = 1، `group_total_amount` = 150، `/api/bookings` يُرجع صفين |
| T7 | فشل الكتلة الثانية (محجوزة) | 409 ولا يُنشأ شيء (لا جذر، لا فاتورة) |
| T8 | الحد اليومي = 1 وحجز مقسوم | 201، ثم حجز ثانٍ → 422 |
| T9 | إلغاء الابن من التطبيق | الابن ملغى، الجذر نشط، الفاتورة = 100، والدفع ينجح ويُكمل الجذر فقط |
| T10 | إلغاء الجذر وله ابن نشط | ترقية: الابن جذر، الجذر القديم ابن ملغى، الفاتورة انتقلت = 50، الدفع ينجح |
| T11 | إلغاء الموظف للجذر (ADMIN) | نفس الترقية |
| T12 | العميل يلغي الكتلتين | `CancellationMonitor` يعدّها 1 → لا تنبيه |
| T13 | `reminder_offset_hours` مع حجز مقسوم | تذكير نشط لكل كتلة |
| T14 | إيميل التأكيد | يحتوي وقتي الكتلتين |
| T15 | إضافة خدمة بمزود آخر بعد الكتلة الابن | تنجح (`child_created`) مربوطة بالجذر، ولا `gap_too_large` |
| T16 | خلل قائم: دفع مجموعة فيها ابن ملغى | ينجح ويستثني الملغى |

وتشغيل الحزمة كاملة ومقارنتها بالخط الأساسي (12 فشلاً معروفاً سابقاً لا علاقة لها بالحجز).

---

## 6. ترتيب التنفيذ

1. `Appointment` (مفاهيم المجموعة) ← 2. `InvoiceService` / `Invoice` / `InvoiceFinalizationService` (استثناء غير النشط) ← 3. `AppointmentCancellationService` + ربط المستدعين ← 4. `BookingService::createBooking` (التقسيم) + `BookingValidationService` ← 5. `CancellationMonitor` ← 6. API (`AppointmentResource`، `LinkedAppointmentResource`، `BookingController`، `AppointmentService`) ← 7. الإيميل ← 8. اللوحة وFilament ← 9. الترجمات ← 10. الاختبارات ← 11. التوثيق (`BOOKING_FLOW.md`، `API.md`، `STAFF_DASHBOARD.md`، ملف المشكلة، ملف الشرح).
