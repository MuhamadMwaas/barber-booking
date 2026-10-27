# منطق الإجازات — Time-Off Logic

> **الملفات:** `app/Models/ProviderTimeOff.php:1`، `docs/BOOKING_FLOW.md:1111` (BOOK-04)

---

## 1. المصدر الوحيد

| الدالة | تجيب عن |
|--------|---------|
| `scopeCoveringDate($date)` | هل تنطبق هذه الإجازة على ذلك اليوم؟ |
| `blockedWindowOn($date)` | أي شريحة من ذلك اليوم تشغلها؟ |
| `blocksWindow($start,$end)` | هل تتعارض مع [start,end)؟ |

التوفر والحجز يستدعيان الثلاثة — لا اشتقاق inline.

## 2. `scopeCoveringDate` — `COALESCE`

```php
where('start_date','<=',date)->whereRaw('COALESCE(end_date, start_date) >= ?', [date])
```

- `end_date=null` = يوم واحد — `COALESCE` يحولها لـ `start_date`.
- القديم `end_date >= date` يُسقط null rows بصمت (`NULL >= '2026-09-15'` = UNKNOWN لا FALSE).

## 3. `blockedWindowOn` — الإجازة الساعية الممتدة = غياب متصل واحد

```
10/9 12:00 → 15/9 13:00:
  البداية (10/9): من 12:00 حتى نهاية اليوم
  الوسط (11-14/9): اليوم كامل
  النهاية (15/9): من بداية اليوم حتى 13:00
  يوم واحد: من start_time حتى end_time
```

## 4. التحقق

**المصدر الوحيد:** `LeaveReassignmentService::validationError()` (على المدخلات الخام) و `assertValid()` (على الإجازة كاملة).
`grant()` و `plan()` يستدعيان `assertValid()` بنفسيهما، فلا يمكن تجاوز التحقق من أي مسار — ولا حتى بطلب مصنوع إلى إجراء التأكيد في Filament.

- النوع `0` أو `1` فقط، `start_date` مطلوب وصحيح، `end_date ≥ start_date`.
- للساعية: `start_time/end_time` مطلوبان وصحيحان و `end_time > start_time`. `22:00→02:00` مرفوض (لا عبور منتصف ليل).
- العامل والسبب يجب أن يكونا موجودين في قاعدة البيانات.

`StaffDashboard::timeOffValidationError()` يفوّض إلى نفس الدالة ليعرض الرسالة مبكرًا فقط. النماذج في Filament (`->after('start_time')`) طبقة عرض، لا ضمان.

## 5. منح الإجازة فوق حجوزات مفتوحة — `LeaveReassignmentService`

> **الملف:** `app/Services/LeaveReassignmentService.php` — **المسار الوحيد لكتابة إجازة.**
> مراجعة التنفيذ وإصلاحاتها: [`time-off-implementation-review-ar.md`](time-off-implementation-review-ar.md) و [`time-off-review-fixes-ar.md`](time-off-review-fixes-ar.md).

الإجازة لا تُحفظ إلا إذا كان جدول العامل فارغًا ضمنها. كل الأماكن الخمسة تمر عبر `grant()`:
الداشبورد (`saveTimeOffFromAlpine` + `planTimeOffReassignment` + `confirmTimeOffReassignment` + `saveTimeOff` القديم)،
`ViewProvider` (إجازة ساعية/يومية)، و `TimeOffsRelationManager` (إنشاء **وتعديل**).

| الدالة | ماذا تفعل |
|--------|-----------|
| `conflicts($leave)` | الحجوزات **المفتوحة** فقط (`created_status=1` + `PENDING`) التي تغطيها الإجازة (`blocksWindow`) **ولم يأتِ وقت بدايتها بعد** (`start_time > now()`). المكتملة/الملغاة، والمفتوحة التي مضى وقتها أو الجارية الآن، لا تمنع الإجازة ولا يُبلَّغ عنها الزبون |
| `plan($leave)` | قراءة فقط. لكل حجز: `move` (لعامل آخر) / `cancel` / `blocked` (مدفوع ولا بديل) |
| `grant($leave, $signature, $mayCancel)` | تحقق → قفل → إعادة بناء الخطة → قفل كل حجز وإعادة فحصه → تطبيق → حفظ الإجازة، داخل transaction واحدة |

**شروط العامل البديل:** **نفس الفرع تمامًا** (عامل بلا فرع لا يُقابل إلا عمالًا بلا فرع)، فعّال، يقدّم **كل** خدمات الحجز
(`provider_service.is_active`)، لديه وردية تغطي الحجز كاملًا، ليس في إجازة، وغير مشغول.

**التوزيع — الحفاظ على الحجوزات أولًا، ثم التوازن:** الحجوزات المتداخلة فيما بينها (ممكنة فقط عبر الحجز الإجباري) تتنافس
على نفس العمال، فتُحل كل مجموعة متداخلة ببحث (الأكثر تقييدًا أولًا، مع تراجع، وسقف 20000 عقدة) يختار التوزيع الذي
يرحّل **أكبر عدد**. بين التوزيعات المتساوية، ولكل حجز بلا منافسة: **الأقل حجوزات في ذلك اليوم**، ثم الاسم، ثم id.

**الترحيل:** نفس الوقت ونفس السعر — يتغير `provider_id` فقط، ويُحفظ العامل الأصلي في
`appointments.reassigned_from_provider_id` (يظهر في الداشبورد "Übernommen von"). **لا إشعار للزبون.**

**الإلغاء:** عبر `AppointmentCancellationService` (`ADMIN_CANCELLED`). الإشعارات تُسجَّل بـ `DB::afterCommit` — تُرسل بعد
**الـ commit الأخير** فقط، وتُهمل إذا تراجعت أي transaction محيطة. كل قناة (إيميل `BookingCancelledProviderUnavailableMail`،
إشعار تطبيق `notification.booking_cancelled_provider_unavailable_*`) بمعالجة أخطاء مستقلة: فشل إحداهما لا يمنع الأخرى.
اللغة = `appointment_reminders.locale` وإلا `de`.

**الصلاحيات:**
- الداشبورد: `manage_timeoff`، والإلغاء يتطلب `cancel_appointment` — يُحكم عليه داخل `grant()` (`$mayCancel`) على الخطة المبنية تحت القفل.
- Filament: `ProviderTimeOff:create` للإنشاء و `ProviderTimeOff:edit` للتعديل (للأدمن فقط حسب `PermissionsSeeder`)، وتكفي للإلغاءات.
  الزران "إضافة إجازة ساعية/يومية" مقفلان بنفس الصلاحية. نوع العملية يُشتق من وجود `time_off_id`، لا مما يرسله المتصفح.
- Filament لا يثق بـ arguments الإجراء: العامل دائمًا عامل الصفحة (`leaveOwner()`)، والإجازة المعدّلة تُحمّل من
  `leaveOwner()->timeOffs()` فقط، والأعمدة المسموحة محددة (`leaveColumns()`).

**التزامن والموافقة:** `grant()` يقفل العامل المجاز + **كل** العمال المرشحين (`BookingLockService`) ثم يعيد بناء الخطة.
`signature` = SHA-256 لبيانات الإجازة + لكل حجز (id، التاريخ، الوقت، الخدمات، الزبون، الإجراء، البديل). أي تغيير في أي منها
→ `LeaveReassignmentRequiredException(changed: true)` بالخطة الجديدة، ولا يُكتب شيء.
قبل كتابة كل حجز يُقفل **مجموعته** بنفس ترتيب بقية الكتّاب (الجذر أولًا ثم المجموعة حسب id) ويُعاد التأكد أنه ما زال
`PENDING` وعند نفس العامل — لأن الدفع يقفل صفوف الحجوزات لا صفوف `users`.

**الواجهة:** نموذج الإجازة ← "حجوزات مفتوحة" ← "محاولة الترحيل" (خطة **جديدة** من الخادم) ← جدول الخطة (مع رقم الحجز) ← "موافق".
في Filament عبر `App\Filament\Concerns\GrantsLeaveWithReassignment` (`replaceMountedAction`
إلى `resolveLeaveConflicts` ثم `confirmLeaveReassignment`).

**الاختبارات:** `tests/Feature/Booking/LeaveReassignmentServiceTest.php`، `tests/Feature/Booking/LeaveReassignmentHardeningTest.php`،
`tests/Feature/Booking/DashboardLeaveReassignmentTest.php`، `tests/Feature/Filament/LeaveReassignmentFilamentTest.php`.

---

*التالي: [`customer-free-check.md`](customer-free-check.md)*
