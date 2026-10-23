# اختبار حيّ: هل يصل إشعار التذكير (Push) إلى الجوال؟

> **لمن هذا الملف:** لوكيل AI يعمل على السيرفر داخل مشروع BarberBooking، ويملك Laravel Boost ووصولاً لقاعدة البيانات والتيرمينال.
> **الهدف:** إنشاء حجز تجريبي للمستخدم `mwaasmuhamad@gmail.com` وتذكير يُطلق بعد **دقيقة واحدة**، ثم التحقق هل وصل الـ push إلى جواله، وإن لم يصل: أين انكسر المسار بالضبط.
> **الإنسان المرافق:** مسجّل دخول في التطبيق على جواله بنفس الإيميل، ويراقب الجوال.

---

## 0. قواعد إلزامية (هذا سيرفر إنتاج)

1. **لا تعدّل أي شيء غير ما يطلبه هذا الملف.** المسموح فقط: إنشاء حجز تجريبي واحد + تذكيره، ثم حذفهما في النهاية.
2. **ممنوع بدون موافقة صريحة من الإنسان:** تشغيل `db:seed`، أو `migrate`، أو تعديل `app_settings` / `user_settings` / `user_devices`، أو تعديل `.env`، أو إعادة تشغيل خدمات.
3. إذا فشلت أي خطوة فحص في القسم 2 وكانت كفيلة بإفشال الاختبار، **توقف واكتب تقريراً للإنسان** ولا تكمل.
4. لا تطبع مفتاح `ONESIGNAL_REST_API_KEY` كاملاً. أول 8 أحرف تكفي.

---

## 1. كيف يعمل المسار (لتعرف ماذا تفحص)

```
AppointmentReminderService::scheduleReminder()
   └─ ينشئ صف appointment_reminders (status=pending)
   └─ SendAppointmentReminderJob::dispatch($id)->delay($remindAt)   ← job مؤجّل في الـ queue
                │
                ▼  (عند remind_at — يحتاج queue worker شغّالاً)
SendAppointmentReminderJob::handle()
   ├─ ReminderChannelResolver::enabledChannels($user)
   │     يقرأ reminder_push_enabled / reminder_email_enabled / reminder_sms_enabled
   │     ⚠ إذا كان صف reminder_push_enabled غير موجود في app_settings → null → false → لا push أبداً
   ├─ push  → NotificationService::sendNotificationToUser()
   │            ├─ يجلب device_id من user_devices (is_active=1)  ← هذا هو OneSignal Subscription ID
   │            ├─ OneSignalService::sendToMany()  → POST onesignal.com/api/v1/notifications
   │            │     ⚠ رد OneSignal بـ 200 مع أخطاء لا يُسجَّل؛ رد غير 200 يُسجَّل "OneSignal API error"
   │            └─ يكتب إشعاراً داخل التطبيق (جدول notifications) دائماً
   ├─ email → AppointmentReminderMail
   └─ يحفظ delivered_channels  (ملاحظة: "push" تُحفظ حتى لو رفضها OneSignal أو لم يوجد جهاز)
```

الملفات: `app/Jobs/SendAppointmentReminderJob.php`، `app/Services/Reminders/ReminderChannelResolver.php`، `app/Services/NotificationService.php`، `app/Services/OneSignalService.php`.

---

## 2. فحوصات ما قبل الاختبار

نفّذها كلها وسجّل النتيجة. استخدم أداة `database-query` من Laravel Boost للـ SQL، و`tinker` (من Boost إن توفرت، وإلا `php artisan tinker --execute="..."`) لكود PHP.

### 2.1 إعدادات البيئة

```bash
php artisan about --only=environment,drivers
php artisan config:show services.onesignal    # اعرض app_id و base_url وأول 8 أحرف من المفتاح فقط
php artisan config:show queue.default
```

- سجّل قيمة `queue.default` (`database` أو `redis`...). ستحتاجها في القسم 4.
- إذا كان `services.onesignal.app_id` أو `rest_api_key` فارغاً → **توقف وبلّغ**.

### 2.2 هل الـ queue worker شغّال؟

```bash
ps aux | grep -E "queue:work|horizon" | grep -v grep
supervisorctl status 2>/dev/null
```

إذا لم يكن هناك worker → **توقف وبلّغ**؛ بدونه لن يُطلق أي تذكير. (مع أن الإيميل يصل حسب المدير، وهذا يعني غالباً أنه يعمل.)

### 2.3 المستخدم

```sql
SELECT id, email, first_name, last_name, phone, deleted_at
FROM users WHERE email = 'mwaasmuhamad@gmail.com';
```

احفظ `id`، وسنسمّيه `{USER_ID}` فيما يلي. لا صف أو `deleted_at` غير فارغ → **توقف وبلّغ**.

### 2.4 مفاتيح قنوات التذكير ⚠ السبب الأرجح للمشكلة

```sql
SELECT `key`, default_value, is_active FROM app_settings WHERE `key` LIKE 'reminder_%';
```

المتوقع: ثلاثة صفوف، و`reminder_push_enabled` قيمته `true`.

**إذا كان `reminder_push_enabled` غير موجود:** فهذا على الأرجح سبب المشكلة كلها، إذ لن يُرسل push لأي عميل. **لا تُصلحه بنفسك.** بلّغ الإنسان واقترح عليه الأمر التالي (آمن للتكرار، ويُنشئ الصف الناقص فقط):

```bash
php artisan db:seed --class=AppSettingSeeder --force
```

لا تكمل إلا بعد أن يوافق الإنسان وتُنفّذه ويظهر الصف.

### 2.5 إعدادات المستخدم نفسه

```sql
SELECT `key`, value FROM user_settings WHERE user_id = {USER_ID} AND `key` LIKE 'reminder_%';
```

إذا وُجد `reminder_push_enabled` = `false` فالمستخدم أطفأ الـ push. بلّغ الإنسان ليفعّله من شاشة الإعدادات في التطبيق، ولا تعدّله بنفسك.

ثم أكّد النتيجة النهائية من الكود نفسه:

```php
$u = App\Models\User::where('email', 'mwaasmuhamad@gmail.com')->first();
dump(app(App\Services\Reminders\ReminderChannelResolver::class)->resolve($u));
```

المطلوب: `push.enabled = true` و`push.deliverable = true`.

### 2.6 أجهزة المستخدم

```sql
SELECT id, device_id, LENGTH(device_id) AS len, platform, is_active, app_version, last_active_at, updated_at
FROM user_devices WHERE user_id = {USER_ID} ORDER BY updated_at DESC;
```

| النتيجة | المعنى | الإجراء |
|---|---|---|
| لا صفوف | التطبيق لم يسجّل الجهاز (`POST /api/register-device` لم يُستدعَ أو فشل) | **توقف**: اطلب من الإنسان تسجيل خروج ثم دخول في التطبيق، ثم أعد الاستعلام |
| صفوف كلها `is_active = 0` | الجهاز أُلغي تسجيله | نفس الإجراء السابق |
| `device_id` ليس UUID (الطول ≠ 36، أو بلا شرطات) | ليس OneSignal Subscription ID؛ و**OneSignal يرفض الطلب كله بـ 400 إذا وُجد معرّف واحد خاطئ** | سجّلها في التقرير؛ هذه مشكلة في تطبيق الموبايل أو بيانات قديمة |
| صف نشط واحد على الأقل بـ UUID صحيح، و`last_active_at` حديث | جيد | تابع |

قارن أحدث `device_id` مع لوحة OneSignal (Audience ← Subscriptions) إن أمكن للإنسان.

---

## 3. إنشاء الحجز التجريبي + تذكير بعد دقيقة

اكتب السكربت التالي في ملف مؤقت ثم شغّله. السكربت يجرّب أزواج (مزوّد، خدمة) وأوقاتاً مختلفة بعد يومين حتى ينجح حجز واحد. `createBooking` يُجري كل التحقق بنفسه ويتراجع عند الفشل، فلا يبقى أثر للمحاولات الفاشلة.

`bypass_availability` و`allow_customer_overlap` مُفعّلان فقط كي لا يفشل الاختبار بسبب ساعات الدوام؛ أما فحص تعارض المزوّد وفحص «المزوّد يقدّم الخدمة» فيعملان دائماً.

```bash
cat > storage/app/reminder_push_test.php <<'PHP'
<?php
use App\Models\User;
use App\Services\BookingService;
use App\Services\AppointmentReminderService;
use Illuminate\Support\Facades\DB;

$customer = User::where('email', 'mwaasmuhamad@gmail.com')->firstOrFail();
$date = now()->addDays(2)->toDateString();

$pairs = DB::table('provider_service')->where('is_active', 1)
    ->select('provider_id', 'service_id')->limit(30)->get();

$appointment = null;
$lastError = null;
foreach ($pairs as $pair) {
    foreach (['10:00', '12:00', '14:00', '16:00', '18:00', '09:00', '11:00', '13:00', '15:00', '17:00'] as $time) {
        try {
            $appointment = app(BookingService::class)->createBooking($customer, [
                'services' => [[
                    'service_id'  => $pair->service_id,
                    'provider_id' => $pair->provider_id,
                    'start_time'  => $time,
                ]],
                'date'                   => $date,
                'payment_method'         => 'cash',
                'notes'                  => 'PUSH REMINDER TEST — safe to delete',
                'bypass_availability'    => true,
                'override_reason'        => 'push reminder live test',
                'allow_customer_overlap' => true,
            ]);
            break 2;
        } catch (\Throwable $e) {
            $lastError = get_class($e) . ': ' . $e->getMessage();
        }
    }
}

if (! $appointment) {
    echo "BOOKING FAILED. Last error: {$lastError}\n";
    return;
}

// التذكير يحفظ app()->getLocale() لحظة الجدولة كلغة للإشعار. في طلب حقيقي
// تضبطها SetApiLocale من لغة التطبيق؛ في tinker هي لغة السيرفر الافتراضية،
// لذلك نحاكي هنا تطبيقاً بالعربية.
app()->setLocale('ar');

$svc = app(AppointmentReminderService::class);
[$params, $data] = $svc->buildPayload($appointment);
$reminder = $svc->rescheduleReminder($appointment, now()->addMinute(), $params, $data);

echo json_encode([
    'now'            => now()->toDateTimeString(),
    'timezone'       => config('app.timezone'),
    'appointment_id' => $appointment->id,
    'number'         => $appointment->number,
    'start_time'     => (string) $appointment->start_time,
    'provider_id'    => $appointment->provider_id,
    'reminder_id'    => $reminder->id,
    'remind_at'      => (string) $reminder->remind_at,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
PHP

php artisan tinker --execute="require base_path('storage/app/reminder_push_test.php');"
```

احفظ `appointment_id` و`reminder_id` و`remind_at`. إذا ظهر `BOOKING FAILED` فانسخ رسالة الخطأ للإنسان وتوقف.

> قد يصل للمستخدم إيميل تأكيد حجز. هذا طبيعي.

**أخبر الإنسان فوراً:** «التذكير سيُطلق عند `{remind_at}`. اترك التطبيق في الخلفية أو أغلقه، واترك الشاشة مقفلة، وراقب الجوال.» (بعض التطبيقات لا تُظهر الإشعار في شريط النظام وهي مفتوحة في الواجهة.)

### 3.1 تأكد أن الـ job دخل الـ queue

إذا كان `queue.default = database`:

```sql
SELECT id, queue, attempts, FROM_UNIXTIME(available_at) AS available_at
FROM jobs WHERE payload LIKE '%SendAppointmentReminderJob%' ORDER BY id DESC LIMIT 3;
```

يجب أن يظهر job وقت `available_at` فيه ≈ `remind_at`. (مع `redis` تجاوز هذه الخطوة واعتمد على القسم 4.)

---

## 4. بعد ~90 ثانية: ماذا حدث؟

انتظر حتى تمر `remind_at` بنصف دقيقة على الأقل، ثم:

### 4.1 حالة التذكير

```sql
SELECT id, status, remind_at, sent_at, delivered_channels, locale
FROM appointment_reminders WHERE id = {REMINDER_ID};
```

- `status = pending` بعد مرور الوقت → الـ worker لم يلتقط الـ job. افحص `jobs` و`failed_jobs` وبلّغ.
- `status = sent`: انظر إلى `delivered_channels`:
  - لا يحتوي `push` → الـ push **لم يُحاول أصلاً**، فالإعداد مفقود أو مطفأ (راجع 2.4 و2.5).
  - يحتوي `push` → حاول الإرسال. والمشكلة في الجهاز أو OneSignal (راجع 4.2 والقسم 5).

```sql
SELECT id, exception, failed_at FROM failed_jobs ORDER BY id DESC LIMIT 3;
```

### 4.2 السجل

```bash
grep -n "\"reminder_id\":{REMINDER_ID}" storage/logs/laravel*.log | tail -20
grep -nE "Sending notification|OneSignal API error|OneSignal exception|Failed to send" storage/logs/laravel*.log | tail -10 | cut -c1-700
```

(أو أداة `read-log-entries` من Boost.)

- `Delivering appointment reminder` → `enabled_channels` يبيّن القنوات المفعّلة وقت الإرسال.
- `Sending notification` → `device_ids_count`: إذا كانت `0` فلا يوجد جهاز نشط، أي لم يخرج أي طلب إلى OneSignal.
- `OneSignal API error` → انسخ `status` و`body` كما هما؛ ففيهما السبب الحرفي.

### 4.3 هل كُتب الإشعار داخل التطبيق؟

```sql
SELECT id, type, created_at, LEFT(data, 300) AS data
FROM notifications WHERE notifiable_id = {USER_ID} ORDER BY created_at DESC LIMIT 3;
```

إذا ظهر هنا ولم يظهر على شاشة الجوال كـ push، فالسيرفر يعمل حتى حدود OneSignal، والخلل في OneSignal أو الجهاز.

---

### 4.4 لغة الإشعار

يجب أن يصل الـ push **بالعربية** (`locale = ar` في 4.1)، مهما كانت لغة نظام الجوال.

ثم تحقق أن التطبيق يرسل لغته فعلاً مع طلبات الحجز (`?lang=ar` أو هيدر `Accept-Language: ar`):

```sql
SELECT locale, COUNT(*) AS n, MAX(created_at) AS latest
FROM appointment_reminders WHERE created_at > NOW() - INTERVAL 30 DAY GROUP BY locale;
```

إذا كانت كل التذكيرات الحديثة `en` أو `de` رغم أن عملاء يستخدمون التطبيق بالعربية، فالتطبيق لا يرسل لغته. هذه مشكلة في تطبيق الموبايل، وليست في السيرفر؛ بلّغ الإنسان.

---

## 5. فحص مباشر لـ OneSignal (يكشف ما يبتلعه الكود)

الكود الحالي لا يقرأ رد OneSignal عند 200، لذلك أرسل push مباشراً واطبع الرد الخام. هذا يرسل إشعاراً واحداً إضافياً لجوال المستخدم فقط، لكن **أخبر الإنسان قبل تنفيذه**.

```php
$u   = App\Models\User::where('email', 'mwaasmuhamad@gmail.com')->first();
$ids = app(App\Services\NotificationService::class)->resolveDeviceIdsForUsers(collect([$u]));
dump($ids);
dump(app(App\Services\OneSignalService::class)->sendToMany(
    $ids,
    ['en' => 'Push test', 'ar' => 'اختبار إشعار', 'de' => 'Push-Test'],
    ['en' => 'Direct test from the server', 'ar' => 'اختبار مباشر من السيرفر', 'de' => 'Direkter Test vom Server'],
    ['type' => 'debug']
));
```

| الرد | المعنى |
|---|---|
| `id` غير فارغ، ولا `errors` | OneSignal قبل الطلب. إذا لم يصل للجوال: الإشعارات مطفأة على الجوال، أو الـ subscription في OneSignal ليس لهذا الجهاز |
| `errors: ["All included players are not subscribed"]` أو `invalid_player_ids` | الـ `device_id` المخزّن غير مشترك أو قديم. يجب أن يرسل التطبيق `OneSignal.User.pushSubscription.id` الحالي |
| `status: 400` + `not a valid UUID` | `device_id` بصيغة خاطئة (راجع 2.6) |
| `status: 401` أو `403` | مشكلة في المفتاح أو الهيدر (`Authorization: Basic` مقابل مفاتيح `os_v2_` التي تتطلب `Key`) |
| `$ids` فارغة | لا يوجد جهاز نشط؛ لم يُرسل شيء |

اطلب من الإنسان أن يؤكد: هل وصل هذا الإشعار المباشر؟

---

## 6. التنظيف (إلزامي)

```php
$a = App\Models\Appointment::find({APPOINTMENT_ID});
if ($a && str_contains((string) $a->notes, 'PUSH REMINDER TEST')) {
    app(App\Services\AppointmentDeletionService::class)->delete($a);
    echo "deleted\n";
}
```

```bash
rm storage/app/reminder_push_test.php
```

- الحذف يمر عبر `AppointmentDeletionService` حصراً، فلا تستخدم `->delete()` مباشرة. التذكير يُحذف معه (CASCADE).
- لا تستخدم الإلغاء (`AppointmentCancellationService`): الإلغاء الثاني خلال 7 أيام يرسل تنبيهاً للأدمن والمدير.

تحقق:

```sql
SELECT COUNT(*) FROM appointments WHERE id = {APPOINTMENT_ID};   -- يجب 0
```

---

## 7. التقرير المطلوب للإنسان

أعد تقريراً بهذا الشكل بالضبط:

```
1. البيئة:            queue=…  worker شغّال؟ …  OneSignal app_id موجود؟ …  بادئة المفتاح: os_v2_… / غيرها
2. app_settings:       reminder_push_enabled موجود؟ … قيمته …
3. user_settings:      push override = … (أو لا يوجد)
4. الأجهزة:           عدد النشطة … ؛ device_id صيغته UUID؟ … ؛ last_active_at …
5. الحجز التجريبي:    appointment_id=…  reminder_id=…  remind_at=…
6. بعد الإطلاق:       status=…  delivered_channels=…  device_ids_count=…  locale=…  وصل بالعربية؟ …
   لغات التذكيرات الحديثة (4.4): …
7. أخطاء السجل:       (نص OneSignal API error حرفياً، أو "لا يوجد")
8. الفحص المباشر:     رد OneSignal الخام = …  وهل وصل للجوال؟ …
9. التنظيف:           تم حذف الحجز؟ …  حُذف السكربت؟ …
10. الاستنتاج:        السبب المرجّح في سطر واحد + الدليل
```
