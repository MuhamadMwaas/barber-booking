<div lang="ar" dir="rtl">

# توثيق: قنوات تذكير الموعد + واجهة اختيار مدة التذكير

> **الشرح بالعربية — أسماء الكود والمتغيرات بالإنجليزية**
> **تاريخ التنفيذ:** 2026-09-11
> **الطلب:** «ميزة تعيين التذكير أثناء حجز الموعد بحاجة لتعديلات لتصبح كما في الصورة. طريقة التذكير ستكون حسب إعدادات الإشعارات في التطبيق: SMS — تذكير من خلال الإشعارات — Email. مثال: إذا كان المستخدم قد فعّل فقط الرسائل النصية تصله رسالة نصية فقط.»
> **الحالة:** ✅ منفَّذ ومختبَر — 33 اختبار جديد ناجح.

---

## فهرس

1. [الخلاصة التنفيذية](#1-الخلاصة-التنفيذية)
2. [لماذا لم يكن تعديل الفرونت إند كافياً](#2-لماذا-لم-يكن-تعديل-الفرونت-إند-كافياً)
3. [القرارات المعمارية](#3-القرارات-المعمارية)
4. [مخطط التدفق بعد التعديل](#4-مخطط-التدفق-بعد-التعديل)
5. [تفصيل كل تعديل: شو / وين / كيف / ليش](#5-تفصيل-كل-تعديل)
6. [المشاكل التي أُصلحت بالمناسبة](#6-المشاكل-التي-أُصلحت-بالمناسبة)
7. [الـAPI الكامل](#7-الـapi-الكامل)
8. [دليل مطوّر تطبيق الجوال](#8-دليل-مطوّر-تطبيق-الجوال)
9. [الاختبارات](#9-الاختبارات)
10. [خطوات النشر](#10-خطوات-النشر)
11. [قائمة كل الملفات المتأثرة](#11-قائمة-كل-الملفات-المتأثرة)
12. [ما لم يُنفَّذ عمداً](#12-ما-لم-يُنفَّذ-عمداً)

---

## 1. الخلاصة التنفيذية

| # | ما تغيّر | لماذا |
|---|----------|-------|
| 1 | **Push أصبحت خياراً** بمفتاح `reminder_push_enabled` | كانت تُرسل دائماً بلا شرط، فكان مثال العميل «SMS فقط» مستحيلاً |
| 2 | **إعادة هيكلة `SendAppointmentReminderJob`** | `markSent()` كانت مربوطة بمعاملة Push؛ إطفاء Push كان سيُطفئ الإيميل والـSMS معها |
| 3 | **`offset_hours`** بدل التاريخ المطلق | يزيل خطر المنطقة الزمنية (`Asia/Baghdad` مقابل برلين) نهائياً |
| 4 | **3 مسارات جديدة**: options / GET / DELETE | التوغل في الصورة قابل للإطفاء ويجب أن يعرض حالته المحفوظة |
| 5 | **`reminder_offset_hours` داخل `POST /api/bookings`** | نداء واحد بدل اثنين، فلا فجوة «حجز بلا تذكير» |
| 6 | **عمود `delivered_channels`** | شكوى «لم يصلني تذكير» أصبحت قابلة للتشخيص |
| 7 | **إصلاح القيد الفريد** (`active_slot`) | تبديل الخيار ذهاباً وإياباً كان ينتهي بخطأ 500 |
| 8 | **إلغاء التذكير عند إلغاء الحجز** | الصف كان يبقى `pending` للأبد |
| 9 | **throttle على مسارات التذكير** | كانت المسار الحساس الوحيد بلا حد معدل |
| 10 | **عرض التذكيرات في Filament** | الموظف لم يكن يملك أي طريقة للإجابة على شكوى التذكير |

**النصوص التي زوّدنا بها العميل (ألمانية/عربية) مزروعة حرفياً** في ملفات اللغة وتُخدَم عبر API — لا يحتاج التطبيق تثبيتها.

---

## 2. لماذا لم يكن تعديل الفرونت إند كافياً

هذا سؤال العميل الأصلي، وهذه إجابته بالكود.

### 2.1 الشيفرة السابقة

```php
// app/Jobs/SendAppointmentReminderJob.php — قبل التعديل
$sent = DB::transaction(function () use ($reminder, $notificationService) {
    ...
    $notificationService->sendNotificationToUser(...);   // ← Push بلا أي شرط
    $reminder->markSent();
    return true;
});

if (!$sent) { return; }

// Push is the always-on baseline. Email and SMS are opt-in extra channels
$this->deliverOptInChannels(...);   // ← الإيميل والـSMS فقط هما المشروطان
```

والسيدر كان يحمل **مفتاحين فقط**: `reminder_email_enabled` و`reminder_sms_enabled`. **لا وجود لـ`reminder_push_enabled` إطلاقاً.**

### 2.2 النتيجة العملية

مستخدم فعّل SMS فقط كان يتلقى:
- ✅ رسالة نصية — كما طلب
- ❌ **وإشعاراً فورياً أيضاً** — لم يطلبه ولا يملك أي وسيلة لإطفائه

أي أن مثال العميل الحرفي **كان يفشل**، ولا يوجد أي تعديل في تطبيق الجوال يمكنه إصلاح ذلك: المفتاح غير موجود في قاعدة البيانات، والشرط غير موجود في الكود.

### 2.3 التوثيق نفسه كان يعترف بذلك

في [docs/app-settings-reminder-channels-implementation-ar.md](app-settings-reminder-channels-implementation-ar.md) تحت «توسعات مستقبلية»:

> **3. Push كخيار قابل للإيقاف:** لو لاحقاً بدّك تخلّي الـPush برضو on/off، أضف خيار `reminder_push_enabled` ونفس البوابة بالـJob.

طلب العميل هو بالضبط هذا البند المؤجَّل.

### 2.4 تصحيح لعبارة ذلك التوثيق

يقول «نفس البوابة بالـJob» وكأنها `if` واحد. **ليست كذلك.** لو غلّفنا نداء Push بشرط فقط:

```php
$sent = DB::transaction(function () {
    if ($pushEnabled) { $notificationService->sendNotificationToUser(...); }
    $reminder->markSent();
    return true;                      // ← ما الذي يعيده لو كانت Push مطفأة؟
});
if (!$sent) { return; }               // ← وهنا يموت الإيميل والـSMS معها
```

`markSent()` كانت **داخل** معاملة Push، و`if (!$sent) return;` يمنع القنوات الأخرى. لذلك كان التعديل إعادة ترتيب لا إضافة شرط — وهو أخطر جزء في هذه المهمة.

---

## 3. القرارات المعمارية

| القرار | الاختيار | ليش |
|--------|----------|-----|
| **موقع منطق القنوات** | كلاس مستقل `ReminderChannelResolver` | كان موزّعاً بين Job وسيدر وخدمة إعدادات. الآن سؤال «أي قناة؟» له مكان واحد |
| **افتراضي `reminder_push_enabled`** | `true` | Push كانت أساساً دائماً. لو صار الافتراضي `false` **لتوقفت التذكيرات فجأة عن كل مستخدم حالي** |
| **افتراضي Email/SMS** | يبقى `false` | كانا opt-in ولم يوافق عليهما أحد بأثر رجعي، والـSMS مكلفة لكل رسالة |
| **وقت قراءة الإعداد** | لحظة الإرسال (بلا تغيير) | من يغيّر رأيه بعد الحجز يُحترم على التذكير المضبوط سلفاً |
| **صيغة الوقت** | `offset_hours` + دعم `remind_at` | الأول محصّن ضد المنطقة الزمنية، والثاني يبقي الإصدارات المنشورة تعمل |
| **موقع قائمة الخيارات** | `config/appointment_reminders.php` | تغيير الخيارات لا يلمس validation ولا Resource ولا التطبيق |
| **نصوص الواجهة** | من الباك إند عبر API | متّسق مع شاشة الإعدادات data-driven الموجودة |
| **القيد الفريد** | `active_slot` بدل `status` | NULL لا يتكرر في فهارس MySQL/SQLite ⟸ «تذكير نشط واحد» تُفرض على مستوى قاعدة البيانات |
| **كل القنوات مطفأة** | يُسمح + يُبلَّغ التطبيق | المنع لا يعالج الحالة الحقيقية (الإطفاء **بعد** الضبط)، ويمنع سيناريو مشروعاً |
| **فشل التذكير في مسار الحجز** | لا يُلغي الحجز | الموعد مورد متنازع عليه لا يُسترجع؛ التذكير على بُعد ضغطة |
| **`APP_TIMEZONE`** | لم يُمس | يمس الفواتير والتقارير والجداول — مهمة منفصلة |

---

## 4. مخطط التدفق بعد التعديل

<div dir="ltr">

```
┌─ SETTING THE REMINDER ─────────────────────────────────────────────────┐
│                                                                         │
│  GET /api/appointments/reminders/options                                │
│      └─ config/appointment_reminders.php + lang/*/appointment_reminder  │
│         → 7 options + screen texts + current channel state              │
│                                                                         │
│  Either, in ONE call:                                                   │
│     POST /api/bookings { ..., reminder_offset_hours: 2 }                │
│         ├─ BookingService::createBooking()      ← unchanged, locked tx  │
│         └─ scheduleReminderForBooking()         ← failure ≠ lost booking│
│                                                                         │
│  Or, separately / to change it later:                                   │
│     POST /api/appointments/reminders { appointment_id, offset_hours }   │
│         └─ AppointmentReminderService::rescheduleReminder()             │
│               ├─ cancelRemindersForAppointment()  active_slot → NULL    │
│               └─ scheduleReminder()               active_slot → 1       │
│                     └─ SendAppointmentReminderJob::dispatch()->delay()  │
│                                                                         │
│     GET    /api/appointments/{id}/reminders   ← restore toggle state    │
│     DELETE /api/appointments/{id}/reminders   ← toggle switched off     │
└─────────────────────────────────────────────────────────────────────────┘

┌─ DELIVERING THE REMINDER ──────────────────────────────────────────────┐
│  SendAppointmentReminderJob::handle()                                   │
│                                                                         │
│  1. GUARDS    reminder exists? pending? appointment alive? user exists?  │
│                                                                         │
│  2. CLAIM     DB::transaction { lockForUpdate → re-check → markSent }    │
│               ↑ claim BEFORE sending, so a retried job cannot double-send│
│                                                                         │
│  3. RESOLVE   ReminderChannelResolver::enabledChannels($user)           │
│               ↑ read NOW, not at scheduling time                        │
│               reminder_push_enabled  → push                             │
│               reminder_email_enabled → email                            │
│               reminder_sms_enabled   → sms                              │
│                                                                         │
│  4. DELIVER   outside the transaction, each channel in its own try/catch │
│               push  → NotificationService (OneSignal + in-app record)   │
│               email → AppointmentReminderMail                           │
│               sms   → SmsService                                        │
│                                                                         │
│  5. RECORD    recordDeliveredChannels(['sms'])                          │
│               [] = fired with every channel off — the diagnosable case  │
└─────────────────────────────────────────────────────────────────────────┘
```

</div>

---

## 5. تفصيل كل تعديل

### 5.1 `config/appointment_reminders.php` — جديد

- **شو:** `offset_hours => [1,2,3,4,5,6,24]` و`default_offset_hours => 1`.
- **كيف بيخدم المهمة:** هو مصدر الحقيقة الوحيد لخيارات العميل السبعة. يقرأه: قاعدة الـvalidation في المسار المنفصل، وقاعدة الـvalidation في مسار الحجز، ومسار `options`.
- **ليش ملف config:** توسيع القائمة أو تضييقها لا يلمس أي كلاس. لو أضفت `12` مثلاً، تظهر في التطبيق فوراً مع نص احتياطي `option_fallback` حتى تُضاف ترجمتها.

### 5.2 Migration — إصلاح القيد الفريد

- **الملف (جديد):** `database/migrations/2026_09_11_000001_fix_appointment_reminders_unique_constraint.php`
- **شو:** يضيف `active_slot` (`unsignedTinyInteger` **nullable**)، يملأ الصفوف القائمة، يزيل التكرارات التاريخية، يحذف `appointment_reminders_unique_pending` ويضع `appointment_reminders_one_active` على `(appointment_id, user_id, active_slot)`.
- **ليش nullable إلزامياً:** الـNULL هو ما **يُخرج** صف الأرشيف من الفهرس الفريد. لو وضعنا `default 1` لانهار المبدأ كله.
- **ليش يشيل التكرارات أولاً:** بيانات قديمة قد تحمل تذكيرين نشطين، فتفشل الميجريشن عند إنشاء الفهرس. الكود يبقي الأحدث ويؤرشف الباقي — فالنشر آمن على قاعدة بيانات حية.

### 5.3 Migration — `delivered_channels`

- **الملف (جديد):** `database/migrations/2026_09_11_000002_add_delivered_channels_to_appointment_reminders_table.php`
- **شو:** عمود `json` nullable.
- **ليش:** بعد أن صارت القنوات الثلاث قابلة للإطفاء، لم تعد كلمة «أُرسل» حقيقة واحدة. الصف قد يكون وصل عبر ثلاث قنوات أو واحدة أو **ولا واحدة**. بدون هذا العمود تصبح أشهر شكوى («ضبطت تذكيراً ولم يصلني شيء») غير قابلة للتشخيص.
- **دلالة القيم:** `null` = لم يُرسل بعد · `["sms"]` = وصل عبر SMS · `[]` = **حان وقته ونُفِّذ وكل القنوات كانت مطفأة**.

### 5.4 `AppSettingSeeder` — معدّل

- **شو:** أضيف `reminder_push_enabled` بـ`default_value = true` و`sort_order = 0` مع ترجمات en/ar/de.
- **كيف بيخدم المهمة:** بما أن `PATCH /api/settings/{key}` عام بالتصميم، هذا السطر وحده يجعل التوغل الثالث يظهر في شاشة إعدادات التطبيق **بلا سطر Flutter واحد**.
- **ليش `true`:** موثّق في الكود نفسه — قرار ترحيل لا ذوق. `false` كان سيُسكت تذكيرات كل مستخدم حالي بصمت لحظة تفعيل البوابة.
- **تعديل ثانوي:** `$this->command?->info(...)` بدل `$this->command->info(...)` ليعمل السيدر من الاختبارات حيث لا يوجد console command.

### 5.5 `AppointmentReminder` — إعادة كتابة

- **شو:**
  - ثوابت `ACTIVE_SLOT` و`STATUS_PENDING/SENT/CANCELLED` بدل السلاسل المتناثرة.
  - `scopeActive()` يرشّح على `active_slot` **لا** على `status`.
  - `offsetHours()` يحسب المدة بالساعات للتطبيق.
  - `markSent(array $channels)` و`recordDeliveredChannels()` و`markCancelled()` — **كلها تُصفّر `active_slot`**.
- **ليش `scopeActive` على `active_slot`:** الاستعلام والقيد يقرآن **نفس العمود**، فيستحيل أن يختلف «ما يعتبره الكود نشطاً» عن «ما تعتبره قاعدة البيانات نشطاً». هذا نفس مبدأ `Appointment::scopeBlocksProviderTime()` الذي منع انفصال طبقة التوفر عن طبقة الحجز (BOOK-01).
- **ليش `offsetHours()` بطرح الـtimestamps لا بـ`diffInMinutes`:** Carbon 2 يعيدها مطلقة و Carbon 3 يعيدها بإشارة — نفس النداء يغيّر معناه بصمت عند ترقية. الطرح لا يفعل.

### 5.6 `Appointment` — معدّل

- **`activeReminder(): HasOne`** — علاقة جديدة مرشّحة على `whereNotNull('active_slot')`. `HasOne` لأن القيد يضمن واحداً فقط. وجودها منفصلة عن `reminders()` يسمح بـeager loading للصف النشط وحده بدل سحب كل الأرشيف في كل قائمة حجوزات.
- **`static::updated()`** — يلغي التذكير عند تحوّل `status` إلى حالة ملغاة.
  - **ليش في `boot()` لا داخل `cancel()`:** `cancel()` هو مسار الزبون فقط. الموظف يكتب `ADMIN_CANCELLED` مباشرة من StaffDashboard ومن Filament، وكانت ستحتاج نفس السطر في كل مكان. مراقبة **العمود** تغطي كل الكُتّاب الحاليين والمستقبليين.
  - **ما الذي يصلحه فعلاً:** لا شيء كان يُرسل خطأً — الـJob يرفض الإرسال لحجز ملغى أصلاً. المُصلَح هو **السجل**: الصف كان يبقى `pending` للأبد، وبعد إضافة مسار GET كان حجز ملغى سيُبلغ التطبيق عن تذكير نشط لا يستطيع الزبون إطفاءه.
  - الفشل يُسجَّل ولا يُرمى: تذكير يعيش بعد حجزه فوضى، أما إلغاء يفشل في الحفظ فهو موعد ضائع.

### 5.7 `ReminderChannelResolver` — جديد ⭐

- **الملف:** `app/Services/Reminders/ReminderChannelResolver.php`
- **شو:** خريطة `channel → setting key`، ودالتان متمايزتان عمداً:

| الدالة | تجيب على | تُستخدم في |
|--------|----------|-----------|
| `enabledChannels()` | «ماذا **طلب** الزبون؟» | بوابة الإرسال في الـJob |
| `resolve()` | «ماذا **سيصله فعلاً**؟» | ردود الـAPI |

- **ليش الفصل:** `enabled` وحدها لا تكفي للتطبيق — من فعّل SMS بلا رقم جوال يجب أن يُقال له ذلك، لا أن يُوعد بتذكير يذهب إلى العدم. و`deliverable` وحدها لا تكفي للـJob — Push بلا جهاز مسجّل ما زالت تكتب إشعاراً داخل التطبيق يراه الزبون عند فتحه.
- **نقطة التوسعة:** إضافة قناة رابعة = سطر في `SETTING_KEYS` + صف في السيدر. الـJob والـResource وشاشة الإعدادات تتبع تلقائياً.

### 5.8 `SendAppointmentReminderJob` — إعادة هيكلة ⭐ (التعديل الجوهري)

- **ما تغيّر:**

| قبل | بعد |
|-----|-----|
| Push **داخل** المعاملة وبلا شرط | Push **خارج** المعاملة ومشروطة كالبقية |
| `markSent()` مربوطة بنجاح Push | `markSent()` عملية «حجز» مستقلة |
| `if (!$sent) return;` يقتل باقي القنوات | القنوات الثلاث مستقلة تماماً |
| `$user->email && $setting` مبعثر | `ReminderChannelResolver` مركزي |
| لا سجل لما وصل | `recordDeliveredChannels()` |

- **الترتيب الجديد ولماذا كل خطوة في مكانها:**
  1. **CLAIM** — قفل الصف، إعادة فحصه داخل المعاملة، ثم `markSent()`. **لا شيء يُرسل بعد.** الحجز قبل الإرسال هو ما يجعل تكرار الـjob آمناً: المنفّذ الثاني يجد الصف `sent` ويتوقف قبل أن يرسل شيئاً. العكس — أرسل ثم علّم — يرسل مرتين كلما فشل التعليم.
  2. **DELIVER** — **خارج** المعاملة. Push ومايل وSMS كلها نداءات شبكة؛ إبقاء قفل صف مفتوحاً طوال مصافحة SMTP خطأ، ولا يمكن لأي rollback أن يُلغي رسالة غادرت فعلاً.
  3. **RECORD** — بعد المحاولة، فيُرى فشل قناة بدل أن يُلغى مع البقية.
- **الفحص قبل القفل باقٍ كما هو:** رفض سريع، لا ضمان. الضمان هو إعادة الفحص **بعد** القفل — نفس عقد BOOK-02 وMON-03.

### 5.9 `AppointmentReminderService` — إعادة كتابة

- **جديد:** `remindAtFromOffset()` و`allowedOffsetHours()` و`activeReminderFor()` و`buildPayload()`.
- **`buildPayload()` ليش:** المسار المنفصل ومسار الحجز كلاهما ينشئ تذكيراً. لو بنى كلٌّ منهما الـparams بنفسه لانحرفا مع الوقت وأصبح نص التذكير مختلفاً حسب من أنشأه.
- **`cancelRemindersForAppointment()` — أهم سطر:** إضافة `'active_slot' => null`. تعليق الكود يشرح أنه **الجزء الحامل** لا الـstatus: هو العمود الذي بُني عليه الفهرس، فهو ما يحرّر الخانة. كتابة `status` وحدها كانت ستُبقي الصف محتلاً للخانة ويفشل التذكير التالي فوراً بخطأ مفتاح مكرر.
- **`rescheduleReminder()`** صار كله داخل معاملة واحدة: الصف القديم يجب أن يسلّم الخانة قبل أن يطلبها الجديد، وفشل في المنتصف يجب ألا يترك الحجز بلا تذكير.

### 5.10 `AppointmentReminderStoreRequest` — إعادة كتابة

- **شو:** يقبل `offset_hours` (مفضّل) أو `remind_at` (قديم)، **أحدهما لا كليهما**.
- **ليش يُرفض الاثنان معاً بدل ترجيح أحدهما:** التخمين هو كيف ينتهي تذكير بالانطلاق في وقت لم يطلبه أحد.
- **`offset_hours` يُفحص ضد الموعد لا ضد الساعة:** تذكير 24 ساعة على حجز حُجز بعد ظهر نفس اليوم يقع في الماضي. يُقال للزبون ذلك بدل جدولة تذكير لا يمكنه الانطلاق أبداً.
- **تصحيح أثناء التنفيذ:** أضفت ابتداءً `whereNull('deleted_at')` على قاعدة `exists` اتّباعاً لنمط BOOK-09. **كان خطأً:** جدول `appointments` لا يستخدم SoftDeletes ولا يملك العمود أصلاً (النمط ينطبق على `services` و`users`). أسقطت اختبارات الـAPI ذلك فوراً، والتعليق في الكود الآن يشرح لماذا الحارس غائب هنا تحديداً.

### 5.11 `AppointmentReminderController` — إعادة كتابة (4 عمليات)

- **`options()`** — الخيارات السبعة مترجمة + نصوص الشاشة + حالة القنوات. يحمل احتياطاً: لو أُضيفت مدة للـconfig بلا ترجمة يُولَّد نص بدل عرض المفتاح الخام.
- **`store()`** — idempotent: لا حاجة للحذف قبل التغيير.
- **`show()`** — **يُرجع 200 مع `data: null`** لا 404 عند غياب التذكير. «لا يوجد تذكير» حالة طبيعية ترسمها الشاشة كتوغل مطفأ، لا خطأ يحتاج مساراً استثنائياً في التطبيق.
- **`destroy()`** — 404 عند عدم وجود شيء لإلغائه، فتتمايز الضغطة المكررة عن الإلغاء الفعلي.

### 5.12 `AppointmentReminderResource` — جديد

- **شو:** شكل موحّد للتذكير في كل الردود.
- **`offset_hours` هو الحقل الذي يرسمه التطبيق** — `null` فقط لتذكير قديم أُنشئ بوقت مطلق لا يقع على ساعة كاملة، وعندها يعرض التطبيق `remind_at` ويترك القائمة بلا اختيار.
- **`has_active_channel`** — الحقل الذي يشغّل التنبيه: التذكير محفوظ لكن لن يصله شيء.

### 5.13 دمج التذكير في `POST /api/bookings`

- **`BookingCreateRequest`** += `reminder_offset_hours` (اختياري، يُفحص ضد **نفس** قائمة الـconfig).
- **`BookingController::store()`** يسحب الحقل قبل تمرير البيانات — `BookingService` لا يجب أن يتعلم عن التذكيرات أصلاً.
- **`scheduleReminderForBooking()`** — التذكير يُجدول **بعد** `createBooking()` لا داخل معاملتها، وأي فشل يُسجَّل ويُبتلع.
  - **المقايضة صريحة وضيّقة:** الحجز هو ما لا يمكن إعادة إنشائه (الموعد مورد متنازع عليه)، والتذكير على بُعد ضغطة. خسارة موعد مؤكَّد لأن إدراج job فشل مقايضة خاسرة.
  - النتيجة في الرد: `reminder: null` مع 201 — وهو بالضبط ما يحتاج التطبيق رؤيته ليعرض إعادة المحاولة.

### 5.14 `AppointmentResource` + الـeager loading

- `'reminder' => $this->whenLoaded('activeReminder', ...)` — عبر `whenLoaded` عمداً: من لا يحمّل العلاقة يُسقط المفتاح بدل إطلاق استعلام لكل حجز في القائمة.
- أضيف `activeReminder` إلى أربعة مواضع تحميل: `AppointmentService::getCustomerAppointments/getAppointmentDetails` و`BookingService::getCustomerBookings/getBookingDetails`.

### 5.15 ملفات اللغة

- **`lang/{ar,de,en}/appointment_reminder.php`** — أُعيدت كتابتها بـ`screen` (عناوين الشاشة) و`options` (الخيارات السبعة) و`option_fallback`.
  - **نصوص العميل مزروعة حرفياً**، بما فيها الـumlauts الألمانية: `Wann möchtest du erinnert werden?` (بقية ملفات الألمانية في المشروع تستخدم `ue/oe/ae`، لكن هذه نصوص واجهة زوّدنا بها العميل بصيغتها الصحيحة).
- **`lang/{ar,de,en}/main.php`** — `appointment.validation.offset_hours.*` و`appointment.success.reminder_deleted` و`appointment.errors.reminder_not_found`.
- **`lang/{ar,de,en}/resources.php`** — مفاتيح قسم Filament.

### 5.16 `routes/api.php`

```php
GET    /api/appointments/reminders/options   throttle:60,1
POST   /api/appointments/reminders           throttle:30,1
GET    /api/appointments/{id}/reminders      throttle:60,1
DELETE /api/appointments/{id}/reminders      throttle:30,1
```

- **ليش throttle:** كل كتابة تُدرج صفاً **و**تدفع job مؤجلاً. حلقة بلا حد تملأ جدول الـjobs لا أن تهدر المعالج فقط. الحدود لكل مستخدم مُصادَق (المسارات خلف `auth:sanctum`) فلا تكلّف زبوناً حقيقياً شيئاً.
- `reminders/options` أُعلن **قبل** مسارات `/{id}` عمداً — لن يتصادما أصلاً لأن الـid مقيّد بالأرقام، لكن الترتيب يجعل ذلك مستقلاً عن بقاء القيد.

### 5.17 `AppointmentInfolist` — قسم Filament

- **شو:** قسم «تذكيرات الموعد» مطوي افتراضياً، يعرض لكل تذكير: الحالة (شارة ملونة)، وقت الاستحقاق، المدة بالساعات، والقنوات التي وصل عبرها.
- **`delivered_channels === []` تُعرض بالأحمر** — الحالة التي يجب أن تلفت نظر الموظف: التذكير انطلق وكل القنوات كانت مطفأة.
- **للقراءة فقط عمداً:** الموظف يرى التذكير، والزبون وحده يضبطه من تطبيقه.

---

## 6. المشاكل التي أُصلحت بالمناسبة

### 6.1 🔴 خطأ 500 عند تبديل الخيار ذهاباً وإياباً

**السبب:** القيد `unique(appointment_id, user_id, remind_at, status)`. عمود `status` **متغيّر**: إعادة الجدولة تقلب الصف من `pending` إلى `cancelled` بدل حذفه. فكل تغيير يخلّف صف `cancelled` دائماً، والقيد يمنع صفّي `cancelled` بنفس `remind_at`.

**السيناريو:** زبون يبدّل بين «قبل ساعة» و«قبل ساعتين» — وهو بالضبط ما تدعو إليه قائمة منسدلة — فيصطدم بـduplicate key على الـUPDATE داخل `cancelRemindersForAppointment()`، يتحول إلى `QueryException`، يلتقطه `catch (\Throwable)` في الكنترولر، ويصل المستخدم **خطأ 500 على طلب سليم تماماً**.

**الإصلاح:** `active_slot` — القاعدة الحقيقية هي «تذكير نشط واحد لكل حجز»، وصفوف التاريخ يجب ألا تشارك في الفهرس أصلاً. الـNULL لا يتكرر في الفهارس الفريدة، فيتعايش أي عدد من صفوف الأرشيف بينما ترفض قاعدة البيانات ثانياً نشطاً.

**الاختبار الذي يحرسه:** `it('survives the customer flipping between two lead times repeatedly')` — سبع عمليات تبديل متتالية.

### 6.2 🟡 التذكير يبقى `pending` بعد إلغاء الحجز
مشروح في [5.6](#56-appointment--معدّل).

### 6.3 🟡 مسار التذكير بلا حد معدل
مشروح في [5.16](#516-routesapiphp).

### 6.4 🟢 السيدر ينهار خارج الـconsole
`$this->command->info()` كان يرمي `TypeError` عند تشغيل السيدر برمجياً. صار `?->`.

---

## 7. الـAPI الكامل

التوثيق التفصيلي مع كل الأمثلة والأخطاء في **[API.md](../API.md)** — الأقسام 8 إلى 11 من «Appointments API» + «إعدادات قنوات التذكير» + حقل `reminder_offset_hours` في «Bookings - Create».

ملخص سريع:

| Method | Path | الغرض |
|--------|------|-------|
| GET | `/api/appointments/reminders/options` | الخيارات السبعة + نصوص الشاشة |
| POST | `/api/appointments/reminders` | ضبط/تغيير التذكير |
| GET | `/api/appointments/{id}/reminders` | قراءة التذكير النشط |
| DELETE | `/api/appointments/{id}/reminders` | إيقاف التذكير |
| POST | `/api/bookings` | حقل `reminder_offset_hours` الاختياري |
| GET | `/api/settings` | حالة القنوات الثلاث |
| PATCH | `/api/settings/{key}` | تفعيل/إطفاء قناة |

---

## 8. دليل مطوّر تطبيق الجوال

### 8.1 ما لا تحتاج فعله

- ❌ **لا تثبّت الخيارات السبعة** — اقرأها من `GET /api/appointments/reminders/options`.
- ❌ **لا تثبّت نصوص الشاشة** — تأتي في `data.texts` بلغة المستخدم.
- ❌ **لا تكتب توغل الإشعارات يدوياً في شاشة الإعدادات** — يظهر تلقائياً في `GET /api/settings` بعد تشغيل السيدر.
- ❌ **لا تحسب `remind_at` بنفسك** — أرسل `offset_hours` ودع السيرفر يحسب.

### 8.2 تدفق شاشة الحجز

```
1. افتح الشاشة
   └─ GET /api/appointments/reminders/options
      → املأ القائمة من data.options، وحدّد data.default_offset_hours
      → اعرض النصوص من data.texts
      → إن كانت كل قنوات data.channels لها effective=false → حذّر المستخدم

2. المستخدم يفعّل التوغل ويختار مدة ثم يضغط «احجز الآن»
   └─ POST /api/bookings { ..., reminder_offset_hours: <المختارة> }
      → 201 و data.reminder != null  ⟵ تم كل شيء
      → 201 و data.reminder == null  ⟵ الحجز نجح والتذكير لا
                                        أعد المحاولة عبر المسار المنفصل
```

### 8.3 تدفق شاشة تفاصيل الحجز

```
1. GET /api/appointments/{id}
   └─ data.reminder == null  → التوغل OFF
      data.reminder != null  → التوغل ON والقائمة على data.reminder.offset_hours

2. المستخدم يغيّر المدة
   └─ POST /api/appointments/reminders { appointment_id, offset_hours }
      (لا تحذف أولاً — العملية idempotent)

3. المستخدم يطفئ التوغل
   └─ DELETE /api/appointments/{id}/reminders
```

### 8.4 التنبيه المطلوب

عندما يكون `has_active_channel = false`:

> ⚠️ **اعرض رسالة:** «تم حفظ التذكير، لكن جميع قنوات الإشعار مطفأة — لن يصلك شيء. فعّل قناة من الإعدادات.»

وعندما تكون قناة `enabled: true` لكن `deliverable: false`:

> ⚠️ SMS مفعّلة بلا رقم جوال → «أضف رقم جوالك لتصلك الرسائل النصية.»
> ⚠️ Email مفعّلة بلا إيميل → «أضف بريدك الإلكتروني.»

### 8.5 ⚠️ المنطقة الزمنية

`APP_TIMEZONE` على السيرفر هو `Asia/Baghdad` حالياً والصالون يعمل بتوقيت برلين (انظر [12.1](#12-ما-لم-يُنفَّذ-عمداً)).

- **استخدم `offset_hours`** ← محصّن تماماً، السيرفر يحسب كل شيء بمنطقة واحدة.
- **إن اضطررت لـ`remind_at`** ← أرسل ISO8601 بـoffset صريح (`2026-03-15T09:00:00+02:00`). إرسال `2026-03-15 09:00:00` بلا offset يُفسَّر على أنه بغداد.

---

## 9. الاختبارات

**33 اختباراً جديداً، كلها ناجحة.** المجموعة الكاملة: 164 ناجحة في `Feature/Reminders` + `Feature/Booking`.

### 9.1 `tests/Feature/Reminders/ReminderChannelGatingTest.php` (12)

قلب المهمة — كل اختبار جملة من طلب العميل:

| الاختبار | يثبت |
|----------|------|
| `sends ONLY an SMS when the customer enabled SMS only` | **مثال العميل حرفياً** — يفشل على الكود القديم |
| `sends ONLY an email when...` / `ONLY a push when...` | نفس القاعدة للقناتين الأخريين |
| `sends on every channel the customer enabled` | لا قناة تُسقط الأخرى |
| `sends nothing, and records that it sent nothing` | حالة كل القنوات مطفأة + `delivered_channels = []` |
| `is still marked sent when a channel is enabled but unreachable` | SMS بلا رقم لا تعلّق التذكير |
| `honours a channel switched on/off AFTER scheduling` | الإعدادات تُقرأ لحظة الإرسال |
| `falls back to push for a customer who never opened the settings screen` | **حارس الترحيل** — لا أحد يفقد تذكيراته |
| `seeds push enabled and the paid channels disabled` | الافتراضيات |
| `does not send twice when the job runs again` | صحة آلية الحجز (claim) |
| `refuses to fire for a cancelled appointment` | الحارس القائم لم ينكسر |

> **الادعاءات مبنية على `delivered_channels`** لا على عدّاد mock — وهي نفس الأدلة التي يقرأها الدعم الفني عند شكوى. فينبني نجاح الاختبار وصحة الإجابة على الحقيقة ذاتها.

### 9.2 `tests/Feature/Reminders/AppointmentReminderApiTest.php` (21)

الخيارات والترجمة الألمانية · الجدولة بالـoffset · رفض مدة خارج القائمة · رفض مدة مضت · رفض إرسال الحقلين معاً · التوافق الخلفي لـ`remind_at` · منع الوصول لحجز الغير · الاستبدال بدل التكديس · **حارس انحدار التبديل المتكرر** · القراءة والإطفاء وإعادة الضبط · إلغاء التذكير مع إلغاء الحجز · الإنشاء ضمن نداء الحجز.

> **`Queue::fake()` إلزامي فيها:** بيئة الاختبار تعمل بـ`QUEUE_CONNECTION=sync` حيث ينفَّذ الـjob المؤجَّل **فوراً**، فيُرسل التذكير لحظة إنشائه وتفقد كل ادعاءات «هل ما زال pending؟» معناها.

### 9.3 التحقق من عدم كسر شيء

```
tests/Feature/Reminders + tests/Feature/Booking → 164 passed
المجموعة الكاملة                                   → 495 passed, 11 failed, 3 skipped
```

الأعطال الـ11 سابقة لهذا العمل: Fiskaly (5)، رفع الصورة الرمزية (3)، حذف الحساب (1)، صفحة الترحيب (1)، عرض متطلبات كلمة المرور (1). **تم التحقق بالتجربة:** بإخفاء `Appointment.php` و`SendAppointmentReminderJob.php` عبر `git stash` تفشل نفس الاختبارات بنفس العدد.

---

## 10. خطوات النشر

```bash
# 1) الميجريشن (إصلاح القيد + عمود التتبع)
php artisan migrate --force

# 2) زرع مفتاح reminder_push_enabled (idempotent — آمن التكرار)
php artisan db:seed --class=AppSettingSeeder --force

# 3) مسح الكاش بعد إضافة ملف config جديد
php artisan config:clear && php artisan config:cache

# 4) تأكد أن عامل الطابور شغّال — بدونه لا ينطلق أي تذكير
php artisan queue:work
```

**التحقق بعد النشر:**

```bash
php artisan tinker --execute="
foreach (App\Models\AppSetting::orderBy('sort_order')->get() as \$s)
    echo \$s->key.' = '.json_encode(\$s->default_value).PHP_EOL;
"
# المتوقع:
#   reminder_push_enabled = true
#   reminder_email_enabled = false
#   reminder_sms_enabled = false
```

**متغيرات البيئة:** لا جديد. الـSMS يستخدم إعداد `SMS_*` القائم، والإيميل يستخدم `MAIL_*`. قناة غير مكوَّنة تُسجَّل وتُتخطى بهدوء ولا تكسر بقية القنوات.

**التراجع:** `php artisan migrate:rollback --step=2` يعيد القيد القديم ويحذف العمودين. الكود يعتمد على `active_slot` فيجب التراجع عن نشر الكود معه.

---

## 11. قائمة كل الملفات المتأثرة

**جديدة (9):**

| الملف | الدور |
|------|------|
| `config/appointment_reminders.php` | مصدر الحقيقة لخيارات المدة |
| `database/migrations/2026_09_11_000001_fix_appointment_reminders_unique_constraint.php` | إصلاح خطأ 500 |
| `database/migrations/2026_09_11_000002_add_delivered_channels_to_appointment_reminders_table.php` | تتبع القنوات |
| `app/Services/Reminders/ReminderChannelResolver.php` | ⭐ قلب الميزة |
| `app/Http/Resources/AppointmentReminderResource.php` | شكل التذكير في الـAPI |
| `tests/Feature/Reminders/ReminderChannelGatingTest.php` | ⭐ 12 اختبار |
| `tests/Feature/Reminders/AppointmentReminderApiTest.php` | 21 اختبار |
| `docs/APPOINTMENT_REMINDER_CHANNELS_2026-09-11.md` | هذا الملف |

**معدّلة (16):**

| الملف | ما تغيّر |
|------|---------|
| `app/Jobs/SendAppointmentReminderJob.php` | ⭐ إعادة هيكلة كاملة |
| `app/Services/AppointmentReminderService.php` | offset + active_slot + buildPayload |
| `app/Models/AppointmentReminder.php` | active_slot + delivered_channels + offsetHours |
| `app/Models/Appointment.php` | activeReminder + إلغاء عند الإلغاء |
| `app/Http/Controllers/Api/AppointmentReminderController.php` | 4 عمليات بدل واحدة |
| `app/Http/Requests/Api/AppointmentReminderStoreRequest.php` | offset_hours |
| `app/Http/Controllers/Api/BookingController.php` | جدولة التذكير |
| `app/Http/Requests/Api/BookingCreateRequest.php` | reminder_offset_hours |
| `app/Http/Resources/AppointmentResource.php` | حقل reminder |
| `app/Services/AppointmentService.php` | eager load × 2 |
| `app/Services/BookingService.php` | eager load × 2 |
| `app/Filament/.../AppointmentInfolist.php` | قسم التذكيرات |
| `database/seeders/AppSettingSeeder.php` | reminder_push_enabled |
| `routes/api.php` | 3 مسارات + throttle |
| `lang/{ar,de,en}/{main,resources,appointment_reminder}.php` | 9 ملفات لغة |
| `tests/Pest.php` | تسجيل مجلد Reminders |
| `API.md` | الأقسام 8-11 + الإعدادات + حقل الحجز |

---

## 12. ما لم يُنفَّذ عمداً

### 12.1 ⚠️ `APP_TIMEZONE = Asia/Baghdad` — لم يُمس

**المشكلة:** الصالون يعمل بتوقيت برلين بينما السيرفر مضبوط على بغداد.

**لماذا تُرك:** تغييره يمس **كل شيء** دفعة واحدة — أوقات الحجوزات المخزّنة، تواريخ الفواتير (وهي مستندات ضريبية ألمانية تخضع لـGoBD)، ساعات دوام المزودين، التقارير اليومية. كل موعد مخزَّن حالياً سيُقرأ بمنطقة مختلفة عن التي كُتب بها، أي **إزاحة ظاهرية لكل الحجوزات القائمة**. هذه مهمة ترحيل قائمة بذاتها تحتاج خطة واختباراً شاملاً، لا سطراً في `.env` ضمن مهمة تذكيرات.

**لماذا لا يؤثر على هذه الميزة:** اختيار `offset_hours` يجعل التذكير محصّناً تماماً — السيرفر يحسب `start_time − N` في منطقته، فأياً كانت المنطقة تبقى النتيجة «ساعتان قبل الموعد».

**يبقى قائماً في:** الفواتير، التقارير، ساعات الدوام، وأي عميل يصرّ على `remind_at`. راجع مذكرة `german-compliance-gaps` (عملة USD، حقول ضريبية فارغة، منطقة بغداد).

### 12.2 توسعات مقترحة لاحقاً

1. **قناة WhatsApp** — سطر في `ReminderChannelResolver::SETTING_KEYS` + صف في السيدر + فرع في `deliver()`.
2. **أكثر من تذكير للحجز الواحد** — يتطلب توسيع `active_slot` ليحمل رقم الخانة (1، 2، 3) بدل قيمة ثابتة؛ القيد الفريد يعمل كما هو.
3. **ربط SMS بالهاتف المُتحقَّق** — `canReach()` يفحص وجود الرقم فقط، ويمكن ربطه بـ`phone_verified_at`.
4. **إدارة `app_settings` من Filament** — إضافة خيارات وتعديل ترجماتها بلا كود.
5. **تنبيه للإدارة عند تكرار `delivered_channels = []`** — مؤشر على أن الافتراضيات أو الواجهة تدفع الزبائن لإطفاء كل شيء.

</div>
