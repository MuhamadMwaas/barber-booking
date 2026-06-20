<div lang="ar" dir="rtl">

# توثيق: إعدادات التطبيق + قنوات تذكير الحجز (إيميل / SMS)

> **الشرح بالعربية — أسماء الكود والمتغيرات بالإنجليزية**
> **تاريخ التنفيذ:** 2026-06-21
> **الهدف:** بناء نظام «خيارات تطبيق» قابل للتوسعة، كل خيار له `key` + ترجمات للاسم + نوع قيمة + قيمة + **قواعد validation مخزّنة بقاعدة المعطيات**، مع **راوت واحد generic** لتعديل أي خيار. وأول خيارين: تفعيل/إيقاف تذكير الحجز عبر **الإيميل** و**الـSMS**، وربطهما بمسار التذكير الموجود (اللي كان Push فقط).

---

## فهرس

1. [القرارات المعمارية](#1-القرارات-المعمارية)
2. [مخطط التدفق العام](#2-مخطط-التدفق-العام)
3. [تفصيل كل تعديل: شو / وين / كيف / ليش](#3-تفصيل-كل-تعديل)
4. [الـAPI — أمثلة عملية](#4-الـapi--أمثلة-عملية)
5. [كيف ينعكس التفعيل/الإلغاء فوراً](#5-كيف-ينعكس-التفعيلالإلغاء-فوراً)
6. [خطوات التشغيل (مهم)](#6-خطوات-التشغيل-مهم)
7. [متغيرات البيئة المطلوبة](#7-متغيرات-البيئة-المطلوبة)
8. [قائمة كل الملفات المتأثرة](#8-قائمة-كل-الملفات-المتأثرة)
9. [توسعات مستقبلية مقترحة](#9-توسعات-مستقبلية-مقترحة)

---

## 1. القرارات المعمارية

| القرار | الاختيار | ليش |
|--------|----------|-----|
| **النطاق** | **Per-user** (تفضيل خاص لكل زبون) | كل زبون بيختار قنواته بنفسه؛ ومنطقياً نفس الزبون اللي بيجدول تذكيره هو اللي بتتحكّم تفضيلاته بالقنوات. |
| **عدد الجداول** | **جدولين** (`app_settings` تعريفات + `user_settings` قيم) | الترجمة/النوع/الـvalidation خصائص **تعريف الخيار** (وحدة لكل الناس)، بينما القيمة خاصة بكل زبون. جدول واحد كان رح يكرّر الترجمات بكل صف مستخدم. |
| **الـValidation** | **مخزّنة بعمود `validation` على `app_settings`** | إعداد ثابت بقاعدة المعطيات (مثل `required\|boolean`). الراوت الـgeneric بيقرأها ويطبّقها ديناميكياً، فإضافة أي خيار جديد **ما بتحتاج تعديل كود**. |
| **وقت قراءة الإعداد** | **وقت الإرسال داخل الـJob** (مش وقت الجدولة) | حتى ينعكس أي تفعيل/إلغاء **فوراً** على أي تذكير لسا ما انبعت. |
| **Push** | **القناة الأساسية الدائمة** (مش قابلة للإيقاف حالياً) | المطلوب كان: Push موجود، والإيميل/SMS قنوات إضافية opt-in. |
| **القيمة الافتراضية للخيارين** | **`false`** | Push كافٍ كأساس، والإيميل/SMS اشتراك اختياري (opt-in). |
| **إرسال SMS موحّد** | خدمة جديدة `SmsService` | تعميم منطق Vonage الموجود بـ `OtpDeliveryService` ليستخدمه أي feature (تذكير، OTP لاحقاً). |

---

## 2. مخطط التدفق العام

<div dir="ltr">

```
المستخدم بالتطبيق
   │
   ├─ GET /api/settings ───────────────► SettingsController@index
   │      يرجّع كل الخيارات + قيمة كل خيار للمستخدم الحالي (لبناء شاشة الإعدادات)
   │
   └─ PATCH /api/settings/{key} ───────► SettingsController@update
          │  body: { "value": true }
          └─ UserSettingService::set()
                 ├─ يجيب تعريف الخيار من app_settings (404 لو مش موجود/غير مفعّل)
                 ├─ validation من عمود app_settings.validation
                 └─ upsert في user_settings (user_id + key + value)

تذكير الموعد (مجدول مسبقاً عبر AppointmentReminderController):
   SendAppointmentReminderJob::handle()
      ├─ Push (دايماً)  ──► NotificationService::sendNotificationToUser()
      ├─ markSent() داخل transaction
      └─ deliverOptInChannels():   ◄── يقرأ تفضيل المستخدم الحالي (وقت الإرسال)
            ├─ if reminder_email_enabled & user.email  ──► AppointmentReminderMail
            └─ if reminder_sms_enabled  & user.phone   ──► SmsService::send()
```

</div>

---

## 3. تفصيل كل تعديل

### 3.1 Migration — جدول `app_settings` (كتالوج التعريفات)

- **الملف (جديد):** [database/migrations/2026_06_21_000001_create_app_settings_table.php](../database/migrations/2026_06_21_000001_create_app_settings_table.php)
- **شو:** جدول يعرّف الخيارات المتاحة بالتطبيق. الأعمدة: `key` (unique) ، `label_translations` (json) ، `description_translations` (json) ، `type` ، `default_value` (json) ، `validation` (string) ، `group` ، `is_active` ، `sort_order`.
- **كيف بيخدم المهمة:** هو «الجدول اللي فيه اسم الخيار + ترجمات + نوع القيمة + القيمة (الافتراضية) + الـvalidation» اللي طلبته.
- **ليش:** فصلنا التعريف عن القيمة حتى ما نكرّر الترجمات لكل مستخدم. وعمود `validation` يخلّي قواعد التحقق **بيانات** مش كود.

### 3.2 Migration — جدول `user_settings` (قيم كل زبون)

- **الملف (جديد):** [database/migrations/2026_06_21_000002_create_user_settings_table.php](../database/migrations/2026_06_21_000002_create_user_settings_table.php)
- **شو:** `user_id` (FK→users, cascade) ، `key` ، `value` (json) ، مع `unique(user_id, key)`.
- **كيف:** بيخزن **فقط** الخيارات اللي الزبون غيّرها فعلياً عن الافتراضي.
- **ليش:** `unique(user_id, key)` يمنع تكرار قيمة لنفس الخيار لنفس المستخدم؛ والـcascade يحذف تفضيلاته لو انحذف المستخدم.

### 3.3 Model — `AppSetting`

- **الملف (جديد):** [app/Models/AppSetting.php](../app/Models/AppSetting.php)
- **شو:** موديل التعريفات. casts للـjson، ثوابت الأنواع (`TYPE_BOOLEAN`…)، `scopeActive()`، و`label($locale)` / `description($locale)` مع fallback ذكي (locale → en → key).
- **كيف:** `label()` بترجّع الاسم بلغة المستخدم لشاشة الإعدادات.
- **ليش:** الـfallback يمنع ظهور نص فارغ لو لغة معينة ناقصة.

### 3.4 Model — `UserSetting`

- **الملف (جديد):** [app/Models/UserSetting.php](../app/Models/UserSetting.php)
- **شو:** موديل قيمة المستخدم، cast `value` → array، وعلاقة `user()`.
- **ليش:** cast الـvalue يخلّي القيمة ترجع بنوعها الصح (`true` مش `"true"`).

### 3.5 تعديل Model — `User`

- **الملف (معدّل):** [app/Models/User.php](../app/Models/User.php)
- **شو:** أضفنا علاقة `settings(): HasMany` على `UserSetting`.
- **ليش:** وصول سهل لتفضيلات المستخدم، وتأسيس لأي استخدام مستقبلي (eager loading).

### 3.6 Service — `UserSettingService` (قلب المنطق)

- **الملف (جديد):** [app/Services/UserSettingService.php](../app/Services/UserSettingService.php)
- **شو:** نقطة واحدة لقراءة/كتابة الإعدادات:
  - `catalog(User)` → كل الخيارات المفعّلة + القيمة السارية لكل خيار (override أو الافتراضي) → لبناء شاشة الإعدادات.
  - `get(User, key)` → القيمة السارية (تُستخدم بالـJob للبوابة).
  - `set(User, key, value)` → **يجيب الـvalidation من صف `app_settings`** ويطبّقها، ثم `updateOrCreate` بجدول `user_settings`.
  - `castToType()` → توحيد نوع القيمة (boolean/integer/decimal/string/json).
- **كيف بيخدم المهمة:** كل سلوك «الإعدادات» مركزّ هون، والراوت الـgeneric ما بيعمل غير ينده عليه.
- **ليش:** قراءة الـvalidation من DB تحقّق شرطك («الـvalidation تتخزن بالجدول») وتخلّي إضافة خيارات جديدة بدون لمس الكود.

### 3.7 Service — `SmsService`

- **الملف (جديد):** [app/Services/SmsService.php](../app/Services/SmsService.php)
- **شو:** `send($phone, $text)` عبر Vonage — نفس منطق [OtpDeliveryService::sendSmsOtp()](../app/Services/OtpDeliveryService.php#L36) لكن معمّم.
- **كيف:** لو Vonage مش مكوّن بالكامل → يسجّل log ويتخطّى بهدوء (بدون استثناء) فما يكسر مسار التذكير.
- **ليش:** فصل الإرسال بمكان واحد قابل لإعادة الاستخدام (DRY).

### 3.8 Controller + Routes — `SettingsController`

- **الملف (جديد):** [app/Http/Controllers/Api/SettingsController.php](../app/Http/Controllers/Api/SettingsController.php)
- **الراوتات (معدّل):** [routes/api.php](../routes/api.php) — ضمن مجموعة `auth:sanctum + verified.customer`:
  - `GET  /api/settings` → `index` (الكتالوج + قيم المستخدم).
  - `PATCH /api/settings/{key}` → `update` (**الراوت الواحد الـgeneric** لتعديل أي خيار).
- **كيف:** `update` بيمرّر `value` لـ`UserSettingService::set` ويعالج الأخطاء: 404 لو الخيار مش موجود، 422 لو الـvalidation فشلت.
- **ليش:** راوت واحد generic = بالضبط متطلبك «راوت واحد من أجل تعديل أي خيار وتمرير قيمة له».

### 3.9 ترجمات الرسائل — `main.php`

- **الملفات (معدّلة):** [lang/en/main.php](../lang/en/main.php) ، [lang/ar/main.php](../lang/ar/main.php) ، [lang/de/main.php](../lang/de/main.php)
- **شو:** أضفنا `settings.updated` و`settings.not_found` بالثلاث لغات.
- **ليش:** رسائل استجابة الراوت تطلع بلغة المستخدم.

### 3.10 Seeder — `AppSettingSeeder` + تسجيله

- **الملف (جديد):** [database/seeders/AppSettingSeeder.php](../database/seeders/AppSettingSeeder.php)
- **التسجيل (معدّل):** [database/seeders/DatabaseSeeder.php](../database/seeders/DatabaseSeeder.php) — بعد `SalonSettingSeeder`.
- **شو:** يزرع الخيارين `reminder_email_enabled` و`reminder_sms_enabled` (boolean، default=false، validation=`required|boolean`، group=`notifications`) مع ترجمات en/ar/de.
- **كيف:** `updateOrCreate` على `key` → idempotent (إعادة التشغيل ما تكرّر).
- **ليش:** هاي «إضافة الخيارين حالياً» اللي طلبتها.

### 3.11 ربط التذكير — `SendAppointmentReminderJob` (التعديل الجوهري)

- **الملف (معدّل):** [app/Jobs/SendAppointmentReminderJob.php](../app/Jobs/SendAppointmentReminderJob.php)
- **شو تغيّر:**
  1. حقن `UserSettingService` و`SmsService` بالإضافة لـ`NotificationService`.
  2. الـ`DB::transaction` صار يرجّع flag `$sent` (هل انبعت فعلاً) ليضمن إن الإيميل/SMS ينبعتو **مرة وحدة** و**خارج** الـtransaction.
  3. ميثود جديدة `deliverOptInChannels()`:
     - بتجيب النص المترجم بلغة المستخدم عبر `NotificationService::translateKey()` (**نفس نص الـPush**).
     - **إيميل:** لو `reminder_email_enabled` و عند المستخدم email → `AppointmentReminderMail`.
     - **SMS:** لو `reminder_sms_enabled` و عند المستخدم phone → `SmsService::send()`.
     - كل قناة بـ`try/catch` مستقل + log (فشل قناة ما يوقف الباقي).
- **كيف بيخدم المهمة:** هون فعلياً «بينعكس» تفعيل/إيقاف الخيارين على التذكير قبل الموعد.
- **ليش وقت الإرسال:** لأن `get()` بيتنده **لحظة الإرسال**، فأي تغيير من المستخدم ساري فوراً على التذكيرات الجاية.

### 3.12 قناة الإيميل — Mailable + View

- **الملفات (جديدة):** [app/Mail/AppointmentReminderMail.php](../app/Mail/AppointmentReminderMail.php) ، [resources/views/emails/appointment-reminder.blade.php](../resources/views/emails/appointment-reminder.blade.php)
- **شو:** Mailable بياخد عنوان/نص **مترجمين جاهزين** ويعرضهم بقالب HTML بسيط مع دعم RTL للعربي.
- **ليش:** نمرّر النص مترجم مسبقاً حتى نفس نص القنوات (Push/Email/SMS) يضل موحّد.

### 3.13 ترجمات التذكير (موجودة مسبقاً — تم التحقق)

- **الملفات:** [lang/en/appointment_reminder.php](../lang/en/appointment_reminder.php) ، [lang/ar/appointment_reminder.php](../lang/ar/appointment_reminder.php) ، [lang/de/appointment_reminder.php](../lang/de/appointment_reminder.php)
- **ملاحظة:** الثلاثة موجودين أصلاً ومضبوطين (`title` + `message` بالـplaceholders `:number :date :time`). ما احتاجو تعديل.

---

## 4. الـAPI — أمثلة عملية

### 4.1 جلب الإعدادات

```
GET /api/settings
Authorization: Bearer <token>
```

```json
{
  "success": true,
  "data": [
    {
      "key": "reminder_email_enabled",
      "label": "تذكير المواعيد عبر الإيميل",
      "description": "استقبل تذكيرات مواعيدك عبر البريد الإلكتروني أيضاً.",
      "type": "boolean",
      "group": "notifications",
      "value": false,
      "is_default": true
    },
    {
      "key": "reminder_sms_enabled",
      "label": "تذكير المواعيد عبر الرسائل النصية",
      "type": "boolean",
      "group": "notifications",
      "value": false,
      "is_default": true
    }
  ]
}
```

### 4.2 تعديل خيار (تفعيل الإيميل)

```
PATCH /api/settings/reminder_email_enabled
Authorization: Bearer <token>
Content-Type: application/json

{ "value": true }
```

```json
{
  "success": true,
  "message": "تم تحديث الإعداد بنجاح.",
  "data": { "key": "reminder_email_enabled", "value": true }
}
```

| الحالة | الكود |
|--------|-------|
| نجاح | `200` |
| خيار غير موجود/غير مفعّل | `404` |
| قيمة فشلت بالـvalidation المخزّنة | `422` + `errors` |

---

## 5. كيف ينعكس التفعيل/الإلغاء فوراً

- التذكير بيُجدول مسبقاً (Job مؤجّل لوقت `remind_at`).
- لحظة ما الـJob يشتغل، `deliverOptInChannels()` بتنده `UserSettingService::get($user, 'reminder_email_enabled')` و`...sms...` → **بتقرأ آخر قيمة** بجدول `user_settings`.
- يعني لو المستخدم فعّل/ألغى الخيار بأي وقت **قبل** ما ينبعت التذكير → التغيير ساري فوراً، بدون إعادة جدولة.

---

## 6. خطوات التشغيل (مهم)

> ⚠️ قاعدة المعطيات ما كانت متاحة محلياً وقت التنفيذ (الإعداد المحلي بيأشّر على MySQL على `localhost:3306` وهو مطفأ)، فالـmigrations والـseeder **ما انشغّلو**. شغّلهن بالبيئة الصحيحة:

```bash
# 1) الجدولين الجديدين (تشغيل موجّه حتى ما نشغّل migrations أخرى معلّقة)
php artisan migrate \
  --path=database/migrations/2026_06_21_000001_create_app_settings_table.php \
  --path=database/migrations/2026_06_21_000002_create_user_settings_table.php --force

# 2) زرع الخيارين
php artisan db:seed --class=AppSettingSeeder --force
```

تأكّد إن طابور المهام (queue worker) شغّال حتى ينفّذ `SendAppointmentReminderJob`:

```bash
php artisan queue:work
```

---

## 7. متغيرات البيئة المطلوبة

- **SMS (Vonage):** موجودة أصلاً بـ[config/services.php](../config/services.php#L54):
  `VONAGE_SMS_ENABLED=true` ، `VONAGE_KEY` ، `VONAGE_SECRET` ، `VONAGE_FROM`.
  بدون هالقيم، الـSMS بينتخطّى بهدوء (log فقط).
- **الإيميل:** إعداد `MAIL_*` الاعتيادي بـLaravel (نفس اللي بيرسل OTP حالياً).

---

## 8. قائمة كل الملفات المتأثرة

**جديدة:**
- `database/migrations/2026_06_21_000001_create_app_settings_table.php`
- `database/migrations/2026_06_21_000002_create_user_settings_table.php`
- `app/Models/AppSetting.php`
- `app/Models/UserSetting.php`
- `app/Services/UserSettingService.php`
- `app/Services/SmsService.php`
- `app/Http/Controllers/Api/SettingsController.php`
- `database/seeders/AppSettingSeeder.php`
- `app/Mail/AppointmentReminderMail.php`
- `resources/views/emails/appointment-reminder.blade.php`
- `docs/app-settings-reminder-channels-implementation-ar.md` (هذا الملف)

**معدّلة:**
- `app/Models/User.php` — علاقة `settings()`.
- `app/Jobs/SendAppointmentReminderJob.php` — قنوات الإيميل/SMS.
- `routes/api.php` — راوتات `/settings`.
- `database/seeders/DatabaseSeeder.php` — تسجيل `AppSettingSeeder`.
- `lang/en/main.php` ، `lang/ar/main.php` ، `lang/de/main.php` — مفاتيح `settings.*`.

---

## 9. توسعات مستقبلية مقترحة

1. **لوحة Admin (Filament):** إدارة صفوف `app_settings` (إضافة خيارات/تعديل ترجمات) بدون كود.
2. **بوابة الهاتف المُتحقَّق:** ربط شرط الـSMS بـ`phone_verified_at` (في شغل تحقق جوال جارٍ) بدل مجرد وجود الرقم.
3. **Push كخيار قابل للإيقاف:** لو لاحقاً بدّك تخلّي الـPush برضو on/off، أضف خيار `reminder_push_enabled` ونفس البوابة بالـJob.
4. **تعميم `SmsService` على OTP:** خلّي [OtpDeliveryService](../app/Services/OtpDeliveryService.php) يستخدم `SmsService` لإزالة التكرار.
5. **Helper عام:** دالة `user_setting($key)` شبيهة بـ`get_setting()` للوصول السريع.

</div>
