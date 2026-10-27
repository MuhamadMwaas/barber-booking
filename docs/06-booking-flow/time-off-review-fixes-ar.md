# إصلاحات مراجعة الإجازات وترحيل الحجوزات

**التاريخ:** 27 أكتوبر 2026  
**المرجع:** [`time-off-implementation-review-ar.md`](time-off-implementation-review-ar.md) — تقرير المراجعة الخارجية.  
**الوصف الحالي للنظام:** [`time-off-logic.md` §4–5](time-off-logic.md).  
**الحالة:** كل المشاكل السبع المثبتة (TO-01 → TO-07) مُصلحة، ومعها نقاط 6.1 و6.2 و6.4، وقرار الحجوزات التي مضى وقتها، وتصحيح التوثيق. لكل إصلاح اختبار يفشل على الكود القديم وينجح على الجديد.

---

## 1. الخلاصة

| | قبل | بعد |
|---|---|---|
| من يستطيع منح إجازة في Filament | أي مستخدم يفتح صفحة العامل | صاحب `ProviderTimeOff:create` (والتعديل `ProviderTimeOff:edit`) فقط |
| بيانات الإجازة في مودالات Filament | يُصدَّق كل ما يرسله المتصفح (العامل، رقم الإجازة) | العامل = عامل الصفحة دائمًا، والإجازة تُحمّل من إجازاته فقط |
| التحقق من الإجازة | في النموذج فقط — يُتجاوز بطلب مباشر | داخل الخدمة نفسها، على كل مسار |
| الموافقة على الخطة | تبقى صالحة لو تغيّر وقت الحجز | تبطل عند أي تغيير في الإجازة أو الحجوزات المعروضة |
| حجزان متداخلان | قد يُلغى حجز كان يمكن إنقاذه | يُختار التوزيع الذي يرحّل أكبر عدد |
| الإشعارات داخل transaction خارجية | تُرسل قبل حسمها | تُرسل بعد الـ commit الأخير فقط |
| فشل البريد | يمنع إشعار التطبيق | كل قناة مستقلة |
| حجز دُفع أثناء الترحيل | قد يُرحَّل حجز مدفوع | يُقفل الحجز ويُعاد فحصه قبل الكتابة |

---

## 2. هل كان التقرير صحيحًا؟ (تحقق مستقل)

قبل أي إصلاح، أعدت إنتاج كل مشكلة **بنفسي** بملف اختبار مؤقت يثبت السلوك الخاطئ (حُذف بعد التحقق).

**ثبت صحيحًا:** TO-01، TO-02 (الحالتان)، TO-03، TO-04، TO-05، TO-06، TO-07، وسلوك التراجع الكامل (§7.10)، و6.2، و6.4، وقِدم `STAFF_DASHBOARD.md`، وأرقام الاختبارات (44 / 153).

**تصحيحات على التقرير:**

| النقطة | التصحيح |
|---|---|
| TO-01 «مستخدم عرض» | فتح الصفحة يحتاج `Provider:access` + `Provider:view` + `StaffDashboard:view_admin`، لا `view` وحدها. وجزء من المشكلة **قديم**: نفس المستخدم كان يضيف إجازة من الزرين القديمين قبل الميزة. الجديد كان: إلغاء حجوزات الزبائن |
| TO-06 | صحيحة نظريًا، لكن لا يوجد اليوم مسار يستدعي الخدمة داخل transaction خارجية (لوحة Filament لا تفعّل `databaseTransactions()`). أُصلحت كتحصين |
| TO-04 | صحيحة، وأثرها منخفض: أهلية البديل كانت تُعاد تحت القفل، فلم يكن ممكنًا ترحيل غير صالح — المشكلة أن المنفَّذ قد يختلف عمّا رآه المستخدم |
| §6.1 «اختبار PostgreSQL» | المشروع يعمل على **MySQL/MariaDB** (`DB_CONNECTION=mysql`) |
| §6.3 «قرارات لم تُحسم» | خطأ في معظمها: السعر («نبقي السعر الأصلي»)، عدم الإشعار عند الترحيل، عدم إعطاء العامل صلاحية الإجازة، وعمود العامل الأصلي — كلها حُسمت صراحة. غير المحسوم فعلًا: الحجز المفتوح الذي مضى وقته (انظر §6) |

---

## 3. القرارات المتفق عليها لهذه الإصلاحات

1. **صلاحية الإلغاء في Filament:** تكفي صلاحية الإجازة (`ProviderTimeOff:create` / `edit`) — هي للأدمن فقط أصلًا حسب `PermissionsSeeder`.
2. **الزران القديمان** "إضافة إجازة ساعية/يومية" في صفحة العامل يُقفلان بـ `ProviderTimeOff:create`.
3. **الفرع:** البديل من **نفس الفرع تمامًا**. حاليًا فرع واحد، وقد يصبح فرعين؛ لا يوجد عامل بلا فرع. عامل بلا فرع (لو وُجد) لا يُقابل إلا عمالًا بلا فرع، ولا يسحب أبدًا من فروع أخرى.

---

## 4. الإصلاحات بالتفصيل

### TO-01 — تجاوز صلاحيات منح الإجازة والإلغاء في Filament

**السبب:** `canGrantLeave()` في الـ trait كانت تعيد `true`، وصفحة `ViewProvider` لم تضيّقها، والزران القديمان بلا أي فحص.

**الإصلاح:**
- دالة واحدة `mayWriteLeave('create'|'edit')` في `GrantsLeaveWithReassignment`: `SuperAdmin` أو `ProviderTimeOff:{operation}`.
- `->authorize(...)` على `resolveLeaveConflicts` و `confirmLeaveReassignment` **وعلى الزرين القديمين**. في Filament، الإجراء غير المصرّح به يُعتبر `disabled`، فيرفض `mountAction` تحميله حتى بطلب مصنوع (`CanBeDisabled::isDisabled()`).
- فحص ثانٍ داخل جسم الإجراء نفسه (`ensureMayWriteLeave()`) — حماية في العمق.
- صلاحية الإجازة تغطي الإلغاءات المصاحبة (قرار 1).

**الاختبارات:** `TO-01: hides the leave buttons…`، `TO-01: a crafted confirm from a user without leave permission changes nothing` — في `LeaveReassignmentFilamentTest.php`.

### TO-02 — بيانات التأكيد غير مربوطة بالعامل والسجل والعملية

**السبب:** `leaveFromArguments()` كانت تأخذ `user_id` و `time_off_id` من arguments الإجراء (حالة في المتصفح) وتستخدم `ProviderTimeOff::findOrFail()` على كل الإجازات. وجدول الإجازات كان يقبل `create` **أو** `edit` لأي عملية.

**الإصلاح:**
- دالة مجردة `leaveOwner(): User` — `ViewProvider` يعيد `$this->record`، وجدول الإجازات يعيد `getOwnerRecord()`.
- `user_id` يُفرض دائمًا = `leaveOwner()`؛ أي `user_id` في arguments يُتجاهل.
- الإجازة المعدّلة تُحمّل من `leaveOwner()->timeOffs()->findOrFail()` — رقم إجازة عامل آخر **غير موجود** بالنسبة لهذه الصفحة.
- نوع العملية يُشتق على الخادم: وجود `time_off_id` = تعديل ⇐ يتطلب `ProviderTimeOff:edit`؛ غيابه = إنشاء ⇐ `ProviderTimeOff:create`.
- `leaveColumns()` تحصر الأعمدة المقبولة في أعمدة نموذج الإجازة فقط.

**الاختبارات:** `TO-02: a user_id smuggled…`، `TO-02: another provider's leave cannot be edited…`، `TO-02: a create-only user cannot edit…`، `TO-02: a create-only user can still create`.

### TO-03 — تجاوز التحقق عبر إجراء التأكيد

**السبب:** التحقق كان في النماذج فقط (`->after('start_time')`) وفي دالة خاصة داخل `StaffDashboard`. `grant()` لم تتحقق من شيء.

**الإصلاح:**
- `LeaveReassignmentService::validationError()` — **المصدر الوحيد** لقواعد الشكل: نوع الإجازة، صحة التواريخ وترتيبها، الأوقات المطلوبة وصحتها وترتيبها (لا عبور منتصف الليل).
- `LeaveReassignmentService::assertValid()` — الشكل + وجود العامل والسبب في قاعدة البيانات.
- `grant()` و `plan()` تستدعيان `assertValid()` بنفسيهما، فلا مسار يتجاوزها.
- `StaffDashboard::timeOffValidationError()` أصبحت تفوّض إلى نفس الدالة (لا تكرار للقواعد).
- القيم تُقرأ خامًا (`rawTime()` / `rawDate()`) لأن قراءة وقت فارغ عبر cast الـ datetime تعطي "الآن" بدل null.
- في Filament تظهر رسالة الخطأ كإشعار ويُوقف الإجراء (`leaveRejected()`).
- رسائل جديدة: `dashboard.time_off_modal.provider_required` / `type_invalid` / `date_invalid` (ar/de/en).

**الاختبارات:** `TO-03: refuses a backwards hourly leave whatever the caller`، و5 حالات بيانات (بلا أوقات، بلا سبب، سبب غير موجود، نهاية قبل البداية، نوع مجهول) في `LeaveReassignmentHardeningTest.php`، و`TO-03: a crafted confirm with a backwards hourly leave is refused` في Filament.

### TO-04 — الموافقة القديمة تبقى صالحة بعد تغيّر التفاصيل

**السبب:** التوقيع كان نصًا مثل `41>7,42>cancel` — رقم الحجز والوجهة فقط.

**الإصلاح:** التوقيع الآن SHA-256 لـ:
- بيانات الإجازة: العامل، النوع، أول وآخر يوم، وقت البداية والنهاية؛
- لكل حجز: الرقم، التاريخ، وقت البداية والنهاية، الخدمات، الزبون، الإجراء، والعامل البديل.

أي تغيير في أيٍّ منها يبطل الموافقة ويعيد خطة محدّثة. وبما أن صيغة التوقيع لم تعد تحتوي أرقام العمال، صار `grant()` يقفل **كل العمال المرشحين** (لا العمال المسمَّين في التوقيع فقط) — فالخطة المبنية تحت القفل تبقى صحيحة حتى الكتابة دون أي استثناء.

**الاختبارات:** `TO-04: a booking whose time changed voids the confirmation…`، `TO-04: a confirmation does not carry over to different leave dates`.

### TO-05 — اقتراح إلغاء رغم وجود توزيع ممكن

**السبب:** اختيار جشع: لكل حجز بالترتيب الزمني يُثبَّت الأقل حجوزات، دون مراجعة.

**الإصلاح — "الحفاظ على الحجوزات أولًا، ثم التوازن":**
1. لكل حجز تُحسب قائمة العمال المؤهلين مقابل الجدول الحالي.
2. الحجوزات تُقسَّم إلى **مجموعات متداخلة** (`overlapGroups()`): حجوزات في مجموعات مختلفة لا تتنافس أبدًا على وقت عامل، فتُحل كل مجموعة وحدها. في الجدول العادي كل مجموعة = حجز واحد.
3. داخل المجموعة بحث بالعمق مع تراجع (`assignGroup()`): الحجز الأكثر تقييدًا أولًا، والعامل الأقل حجوزات أولًا، مع تقليم عندما لا يمكن تجاوز أفضل حل. **أول توزيع كامل يُكتشف هو المتوازن**، فيتوقف البحث فورًا.
4. سقف 20000 عقدة لكل مجموعة كحماية من يوم شاذ — يُستخدم أفضل حل وُجد، ولا يكون أسوأ من الاختيار الجشع.

**الاختبار:** `TO-05: finds the hand-out that saves both overlapping bookings` (السيناريو المضاد من التقرير: مرن + مقيد ⇐ ترحيل الاثنين، صفر إلغاء).

> **ملاحظة تقنية:** أثناء التنفيذ كشف اختبار قائم خطأً: `[...$array, $id => $x]` في PHP يعيد ترقيم المفاتيح الرقمية، والمفاتيح هنا أرقام حجوزات. استُبدل بإسناد مباشر مع تعليق يشرح السبب.

### TO-06 — الإشعارات قبل commit المعاملة الخارجية

**الإصلاح:** كل إشعار إلغاء يُسجَّل بـ `DB::afterCommit()` داخل المعاملة. Laravel ينفّذه بعد **الـ commit الأخير** (أو فورًا إن لم توجد معاملة)، ويُهمله إذا تراجعت أي معاملة محيطة.

**الاختبارات:** `TO-06: sends nothing when an enclosing transaction rolls back`، `TO-06: sends the notices once the enclosing transaction commits`.

### TO-07 — فشل البريد يمنع إشعار التطبيق

**الإصلاح:** `notifyCancelledCustomer()` مقسومة: التحضير، البريد، إشعار التطبيق — كلٌّ في `try` مستقل، وكل فشل يُسجَّل مع اسم القناة (`channel: prepare|mail|app`).

**الاختبارات:** `TO-07: the app notice still goes out when the mail cannot be queued`، `TO-07: the mail still goes out when the app notice fails`.

---

## 5. إصلاحات إضافية من التقرير

### 6.1 — التزامن مع الدفع وتعديل الدوام

- **الدفع:** `InvoiceFinalizationService` يقفل صفوف الحجوزات لا صفوف `users`، فكان يمكن أن يُدفع حجز بين بناء الخطة والكتابة ثم يُرحَّل وهو مدفوع. الآن `lockStillOpen()` يقفل **مجموعة الحجز** بنفس ترتيب بقية الكتّاب (الجذر أولًا ثم المجموعة حسب id — نفس `AppointmentCancellationService` و`InvoiceFinalizationService`، فلا deadlock) ويتأكد أن الحجز ما زال `PENDING` وعند نفس العامل؛ وإلا يُلغى كل شيء وتُعاد خطة محدّثة.
  **الاختبار:** `6.1: does not move a booking that was paid between the plan and the write` (يحاكي دفعًا متزامنًا لحظة طلب القفل).
- **حفظ الدوام:** `ProviderScheduledWorkForm::saveScheduleData()` كان يحذف كل الورديات ثم يعيد إدخالها **بلا transaction ولا قفل** — فشل في المنتصف يترك العامل بلا جدول، والتعديل المتزامن قد يمنح حجزًا لعامل تُحذف ورديته. الآن داخل `DB::transaction` مع `BookingLockService::lockUsers([$userId])`. هذا يحمي أيضًا مسار الحجز العادي، لا الإجازات فقط.

### 6.2 — الفرع

`candidates()` صار صارمًا: نفس `branch_id`، أو `whereNull` إذا كان العامل بلا فرع (قرار 3).  
**الاختبارات:** `6.2: a provider without a branch never draws colleagues from a branch`، `6.2: colleagues of another branch are never candidates`.

### صلاحية الإلغاء في الداشبورد تُفحص تحت القفل

كان الداشبورد يفحص `cancel_appointment` بالبحث عن `>cancel` في نص التوقيع — لم يعد ممكنًا بعد TO-04، والأصح أصلًا الحكم على الخطة الفعلية. الآن `grant($leave, $signature, mayCancel: …)` يرفض بـ `AuthorizationException` إذا كانت **الخطة المبنية تحت القفل** فيها إلغاءات والمستخدم لا يملك الصلاحية.  
**الاختبارات:** `refuses a plan that cancels when the caller may not cancel…`، `lets a caller without the cancel permission grant a leave that only moves bookings`، واختبار الداشبورد `needs the cancel permission…`.

### 6.4 — الواجهة

- **خطة جديدة عند "محاولة الترحيل" في الداشبورد:** دالة Livewire جديدة `planTimeOffReassignment()`؛ الزر يطلب خطة من الخادم بدل إعادة عرض خطة الحفظ الأول (كما يفعل Filament). **الاختبار:** `builds a fresh plan when "try reassigning" is clicked…`.
- **رقم الحجز** يظهر الآن كعمود أول في كل جداول الخطة (الداشبورد وFilament). ترجمة `dashboard.leave_conflicts.col_number`.

### الحجز المفتوح الذي مضى وقته — يُتجاهل (قرار 28 أكتوبر)

حجز ما زال `PENDING` لكن **وقت بدايته جاء** (زبون لم يحضر ولم يُحدَّث الحجز، أو حجز جارٍ الآن) لا يُعدّ تعارضًا:
لا يُرحَّل، ولا يُلغى، ولا يُرسل للزبون شيء، ولا يمنع الإجازة. يبقى عند عامله ليغلقه الموظفون يدويًا.
التنفيذ: شرط `start_time > now()` في `conflicts()`.
**الاختبارات:** `ignores open bookings whose start time has already come, and tells nobody`،
`grants a leave straight away when its only open bookings are already past`.

### التوثيق

- `time-off-logic.md` §4–5 أُعيد كتابتهما لوصف الضمانات الجديدة بدقة.
- `STAFF_DASHBOARD.md` §20.2 كان ما زال يصف `ProviderTimeOff::create($data)` — حُدِّث.

---

## 6. ما لم يُغلق بعد — بوضوح

| البند | الوضع |
|---|---|
| إثبات الأقفال باتصالين متزامنين | اختبارات المشروع على SQLite في الذاكرة، اتصال واحد. منطق القفل مطابق لبقية الكتّاب ومحاكاة السباق ناجحة، لكن لم يُشغَّل اختبار متزامن حقيقي على MySQL |
| سجل تدقيق كامل | `reassigned_from_provider_id` يحفظ آخر عامل سابق فقط (كما اتُّفق). سجل كامل (من نفّذ، متى، كل عمليات النقل) لم يُطلب |
| التسليم الفعلي للبريد/الإشعار | الاختبارات تثبت طلب الإرسال (fakes)، لا التسليم عبر SMTP/OneSignal |
| الواجهة في المتصفح | لم تُجرَّب بصريًا؛ اختبارات Livewire تغطي منطق الخادم وتدفق الإجراءات |

---

## 7. الملفات المعدّلة في هذه الإصلاحات

| الملف | التغيير |
|---|---|
| `app/Services/LeaveReassignmentService.php` | تحقق مركزي، توقيع SHA-256، قفل كل المرشحين، `lockStillOpen()`، توزيع بالبحث، `afterCommit`، قنوات مستقلة، فرع صارم، `$mayCancel` |
| `app/Filament/Concerns/GrantsLeaveWithReassignment.php` | `leaveOwner()`، `mayWriteLeave()`، authorize + فحص داخلي، تحميل الإجازة من إجازات العامل، `leaveColumns()`، رسائل التحقق |
| `app/Filament/Resources/Providers/Pages/ViewProvider.php` | `leaveOwner()`، قفل الزرين القديمين بـ `ProviderTimeOff:create`، إزالة `user_id` من البيانات |
| `app/Filament/Resources/Providers/RelationManagers/TimeOffsRelationManager.php` | `leaveOwner()` بدل `canGrantLeave()` |
| `app/Livewire/StaffDashboard.php` | `planTimeOffReassignment()`، `$mayCancel` + `AuthorizationException`، التحقق يفوّض للخدمة، رسالة مترجمة |
| `resources/views/livewire/staff-dashboard.blade.php` | "محاولة الترحيل" تطلب خطة جديدة، عمود رقم الحجز |
| `resources/views/filament/modals/leave-reassignment-plan.blade.php` | عمود رقم الحجز |
| `app/Filament/Resources/ProviderScheduledWorks/Schemas/ProviderScheduledWorkForm.php` | `saveScheduleData()` داخل transaction مع قفل العامل |
| `lang/{ar,de,en}/dashboard.php` | `provider_required`، `type_invalid`، `date_invalid`، `col_number` |
| `docs/06-booking-flow/time-off-logic.md`، `docs/STAFF_DASHBOARD.md` | تحديث |

---

## 8. الاختبارات

| الملف | العدد | يغطي |
|---|---:|---|
| `tests/Feature/Booking/LeaveReassignmentServiceTest.php` | 13 | السلوك الأساسي للخدمة (بدون تغيير) |
| `tests/Feature/Booking/LeaveReassignmentHardeningTest.php` | 20 | **جديد** — TO-03 حتى TO-07، صلاحية الإلغاء، 6.1، 6.2، الحجوزات التي مضى وقتها |
| `tests/Feature/Booking/DashboardLeaveReassignmentTest.php` | 6 | +2: الخطة الجديدة عند "محاولة الترحيل"، رفض غير المصرّح |
| `tests/Feature/Filament/LeaveReassignmentFilamentTest.php` | 15 | +8: TO-01، TO-02 (4)، TO-03، دورة تعديل كاملة |

آخر تشغيل لملفات المهمة وكل الملفات المتأثرة (15 ملفًا، منها الإجازات، الداشبورد، الحجز المقسوم، البريد، جدول الدوام): **176 اختبارًا، 698 تحققًا، كلها ناجحة.** لم تُشغَّل مجموعة اختبارات المشروع كاملة.

```bash
php artisan test tests/Feature/Booking/LeaveReassignmentServiceTest.php \
  tests/Feature/Booking/LeaveReassignmentHardeningTest.php \
  tests/Feature/Booking/DashboardLeaveReassignmentTest.php \
  tests/Feature/Filament/LeaveReassignmentFilamentTest.php
```

---

## 9. قبل النشر

1. `php artisan migrate` — عمود `reassigned_from_provider_id` (من التنفيذ الأول، إن لم يُشغَّل بعد).
2. **تغيير سلوك مقصود:** زرا "إضافة إجازة ساعية/يومية" في صفحة العامل يختفيان عن كل من لا يملك `ProviderTimeOff:create`. تأكد أن دور `admin` يملك صلاحيات `ProviderTimeOff:*` (يمنحها `PermissionsSeeder`، أو من شاشة الأدوار). `SuperAdmin` غير متأثر.
3. لا تغيير في الجداول غير العمود أعلاه، ولا في أي API.
