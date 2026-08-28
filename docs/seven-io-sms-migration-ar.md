<div dir="rtl">

# تقرير تفصيلي: الانتقال من Vonage إلى seven.io لإرسال رسائل الـ SMS

> **تاريخ التنفيذ:** 2026-08-28
> **النطاق:** استبدال مزوّد الـ SMS المستخدم في إرسال أكواد الـ OTP (وتذكيرات المواعيد) من Vonage/Nexmo إلى [seven.io](https://dashboard.seven.io/developer/api)، عبر طبقة مزوّدين (drivers) قابلة للتبديل من `.env` دون أي تعديل في الكود.
> **المرجع الرسمي:** <https://docs.seven.io/en/rest-api/endpoints/sms>

---

## 1. الوضع قبل التنفيذ — التحليل

### 1.1 ثلاث نقاط إرسال منفصلة، كلها مربوطة بـ Vonage مباشرة

| الكلاس | من يستدعيه | الطريقة |
|---|---|---|
| `OtpDeliveryService::sendSmsOtp()` | `SendOtpDeliveryJob` (مسار الـ OTP) | نداء HTTP خام لـ `rest.nexmo.com/sms/json` |
| `SmsService::send()` | `SendAppointmentReminderJob` (تذكيرات المواعيد) | **نفس الكود مكرّراً حرفياً** |
| `VonageSdkSmsService::send()` | مسار اختبار `/api/test/vonage-sms` فقط | حزمة `vonage/vonage-laravel` الرسمية |

**المشكلة المعمارية:** منطق «ابنِ الطلب ← أرسل ← افحص الرد ← ارمِ استثناء» كان مكرّراً في كلاسين، ومكتوباً بلغة Vonage (`messages.0.status`, `error-text`). أي تغيير للمزوّد كان يعني إعادة كتابة الثلاثة، ولا يوجد أي واجهة مشتركة تفصل «ماذا نرسل» عن «من يرسل».

### 1.2 اكتشاف حرج: قفل أمني مربوط باسم المزوّد

الإعداد `services.vonage.enabled` لم يكن يتحكم بالإرسال فقط — بل كان يُقرأ في **موضعين حسّاسين** يقرّران هل يُعاد كود الـ OTP داخل استجابة الـ API أم لا:

```php
// app/Http/Controllers/Api/PhoneVerificationController.php:105
$smsEnabled = (bool) config('services.vonage.enabled', false);
if (!$smsEnabled || config('app.debug')) { $response['otp'] = $otp; }   // ← تسريب متعمّد للتجربة

// app/Http/Controllers/Api/PasswordResetController.php:207
return $channel === OtpType::SMS_OTP && !config('services.vonage.enabled', false);
```

**لماذا هذا خطير في سياق الترحيل؟** لو استبدلنا المزوّد وتركنا هذين السطرين يقرآن مفتاح Vonage، لكانت النتيجة: seven.io يرسل الكود بالـ SMS فعلياً، وفي الوقت نفسه يبقى `VONAGE_SMS_ENABLED=false` فيستمر النظام بإرجاع الـ OTP **علناً في استجابة الـ API** — أي أن أي شخص يعرف رقم هاتف مستخدم يستطيع طلب كود إعادة تعيين كلمة مروره وقراءته من الرد مباشرة. هذه أخطر نقطة في المهمة كلها، وقد عولجت (القسم 4.6).

### 1.3 اكتشاف حرج: صيغة أرقام الهواتف في قاعدة البيانات

استعلام فعلي على جدول `users`:

```
+971-50-101-0101   ← 25 رقماً بهذه الصيغة
+963-9414-01024    ← رقمان
```

الأرقام **مخزّنة بفواصل (شرطات)**. وثائق seven.io تقبل `+49171999999999` أو `49171999999999` أو `0049171999999999` — لكن **لا تقبل الشرطات**، والنتيجة رفض بالكود `202 Invalid recipient`. الكود القديم كان يمرّر `$user->phone` كما هو إلى Vonage، أي أن الترحيل بدون تطبيع للأرقام كان سيفشل صامتاً على **كل** رقم في قاعدة البيانات.

### 1.4 اكتشاف حرج: اسم المُرسِل الحالي غير صالح

seven.io يحدّ الـ sender ID بـ **11 حرفاً أبجدياً** (أو 16 رقماً). القيمة الموجودة في `.env.example` كانت `VONAGE_FROM=BarberBooking` وطولها **13 حرفاً** → رفض بالكود `201 Invalid sender`. تم اعتماد `Barber` (6 أحرف) بقرارك.

---

## 2. تحليل واجهة seven.io (من التوثيق الرسمي)

### 2.1 نقطة النهاية

```
POST https://gateway.seven.io/api/sms
X-Api-Key: YOUR_API_KEY
Accept: application/json
Content-Type: application/x-www-form-urlencoded
```

المصادقة تدعم `X-Api-Key` أو `Authorization: basic YOUR_API_KEY` (بدون Base64) أو OAuth2. اخترنا `X-Api-Key` لأنه الأبسط والموصى به في التوثيق.

### 2.2 المعاملات

| المعامل | إلزامي | الوصف |
|---|---|---|
| `to` | ✅ | الرقم بصيغة دولية. يقبل الفاصلة لعدة مستقبِلين |
| `text` | ✅ | نص الرسالة |
| `from` | ❌ | اسم المرسل — **حد أقصى 11 حرفاً أبجدياً / 16 رقماً** |
| `ttl` | ❌ | مدة صلاحية الرسالة بالدقائق (الافتراضي 2880 = 48 ساعة) |
| `label` | ❌ | تسمية إحصائية تظهر في لوحة seven.io (حد 100 حرف) |
| `foreign_id` | ❌ | معرّف خاص بك يعود في الـ callbacks (حد 64 حرفاً) |
| `flash` | ❌ | رسالة فلاش تظهر مباشرة على الشاشة |
| `delay` | ❌ | جدولة الإرسال |
| `debug` | ❌ | **وضع تجريبي: يتحقّق ويسعّر الرسالة دون إرسالها ودون خصم رصيد** |

### 2.3 شكل الرد

```json
{
  "success": "100",
  "total_price": 0.075,
  "balance": 593.994,
  "sms_type": "direct",
  "messages": [
    { "id": "77229318510", "recipient": "49123456789", "parts": 1,
      "price": 0.075, "success": true, "error": null, "error_text": null }
  ]
}
```

### 2.4 مصيدتان تقنيّتان حدّدتا شكل التنفيذ

> **المصيدة الأولى:** seven.io يرجّع **HTTP 200 حتى للرسائل المرفوضة**. الحالة الحقيقية في حقل `success` داخل الجسم، وهو **نص وليس رقماً**. لذلك `$response->successful()` وحده لا يعني شيئاً — يجب قراءة الكود ومقارنته بـ `'100'`.

> **المصيدة الثانية:** المصفوفة `messages` قد تحتوي على رسالة فاشلة (`success: false`) بينما الغلاف الخارجي يقول `100` (رفض جزئي في الإرسال الجماعي). فحص الغلاف وحده يعطي «نجاحاً» كاذباً.

### 2.5 جدول أكواد الحالة (منفّذ كاملاً في الكود)

| الكود | المعنى |
|---|---|
| `100` | الرسالة قُبلت من البوابة ✅ |
| `101` | فشل التسليم لمستقبِل واحد على الأقل |
| `201` | اسم مرسل غير صالح (تجاوز 11/16 حرفاً) |
| `202` | رقم مستقبِل غير صالح |
| `300` | بيانات اعتماد ناقصة أو غير صالحة |
| `301` | معامل `to` مفقود |
| `305` | نص الرسالة مفقود أو غير صالح |
| `308` | معامل غير معروف أو غير مدعوم |
| `400` | نوع رسالة غير صالح |
| `401` | نص الرسالة تجاوز الطول المسموح |
| `402` | رسالة مكرّرة: نفس النص لنفس الرقم خلال آخر 180 ثانية |
| `403` | تجاوز الحد اليومي لهذا المستقبِل |
| `500` | **رصيد الحساب غير كافٍ** |
| `600` | خطأ إرسال من المشغّل |
| `700` | خطأ غير معروف |
| `801` / `802` | `foreign_id` / `label` غير صالح |
| `900` | **فشل المصادقة — تحقّق من `SEVEN_API_KEY`** |
| `901` | فشل التحقق من التوقيع |
| `902` | مفتاح الـ API لا يملك صلاحية على هذه النقطة |
| `903` | عنوان IP للخادم غير مُدرج في القائمة البيضاء |

---

## 3. القرارات المتّفق عليها

| القرار | الاختيار | الأثر |
|---|---|---|
| البنية | **طبقة drivers قابلة للتبديل** | واجهة `SmsGateway` + `SmsManager`، والاختيار عبر `SMS_DRIVER` |
| اسم المرسل | **`Barber`** (6 أحرف) | ضمن حد الـ 11 حرفاً، مع تحقّق برمجي يمنع تجاوزه |
| كود Vonage القديم | **إبقاؤه كاملاً** | لم يُحذف أي ملف ولا أي حزمة — أصبح خطّ رجوع بتغيير سطر واحد |
| الاختبار | **اختبارات آلية (`Http::fake`) + أمر `sms:balance`** | **لم يُرسَل أي SMS حقيقي ولم يُخصم أي رصيد** |

---

## 4. التغييرات ملفاً ملفاً

### 4.1 ملف جديد: `config/sms.php`

**ماذا:** ملف الإعدادات الموحّد لكل ما يخص الـ SMS.

| المفتاح | الوصف |
|---|---|
| `sms.enabled` | **المفتاح الرئيسي**. `false` = لا تخرج أي رسالة إطلاقاً |
| `sms.driver` | المزوّد الفعّال: `seven` \| `vonage` \| `log` |
| `sms.default_country_code` | رمز الدولة للأرقام المكتوبة بصيغة محلية |
| `sms.drivers.seven.*` | مفتاح الـ API، اسم المرسل، الـ base_url، الـ ttl، الـ label، وضع الـ debug، المهلة |
| `sms.drivers.vonage.*` | إعدادات المزوّد القديم (خط الرجوع) |
| `sms.drivers.log.*` | قناة السجل للتطوير المحلي |

**توافقية خلفية مقصودة:**
```php
'enabled' => env('SMS_ENABLED', env('VONAGE_SMS_ENABLED', false)),
```
أي ملف `.env` قديم لا يحتوي `SMS_ENABLED` يستمر بالعمل بالضبط كما كان، دون أي تعديل عليه.

---

### 4.2 ملف جديد: `app/Services/Sms/SmsGateway.php` (واجهة)

**ماذا:** العقد الوحيد الذي ينفّذه كل مزوّد.

```php
public function send(string $to, string $text, array $options = []): SmsResult;
public function name(): string;
```

**العقد السلوكي الموثّق داخل الواجهة** (وهو جوهر الأمان التشغيلي هنا):

- المزوّد **يجب ألا يرمي استثناءً** حين لا يكون مُعدّاً بالكامل — يرجّع نتيجة `skipped`. السبب: قناة SMS معطّلة يجب ألّا تُسقط عملية تسجيل أو حجز.
- المزوّد **يجب أن يرمي** `SmsDeliveryException` حين يكون مُعدّاً لكنه رفض الرسالة أو فشل. السبب: هذا خطأ حقيقي يستحق إعادة محاولة وتسجيلاً.

---

### 4.3 ملف جديد: `app/Services/Sms/SmsResult.php`

**ماذا:** كائن نتيجة موحّد (DTO) بدل المصفوفات المتناثرة:
`sent`, `skipped`, `driver`, `to`, `from`, `messageIds`, `price`, `balance`, `statusCode`, `raw`.

**لماذا:** يفصل «لم تُرسل لأن القناة مطفأة» عن «فشلت» — وهو الفرق بين تجاهل صامت مقصود وبين خطأ يجب أن يظهر. كما يوفّر `toArray()` بنفس شكل المصفوفة التي كان `VonageSdkSmsService` يرجّعها، حفاظاً على التوافق.

---

### 4.4 ملف جديد: `app/Services/Sms/PhoneNumberNormalizer.php`

**ماذا:** تحويل الرقم من صيغة بشرية إلى الصيغة الدولية الخام.

**قواعد التطبيع (كلها مغطّاة باختبارات):**

| المدخل | رمز الدولة | الناتج | القاعدة |
|---|---|---|---|
| `+971-50-101-0101` | — | `971501010101` | حذف كل ما ليس رقماً (هذه هي الحالة الفعلية في قاعدة بياناتك) |
| `+49 (0)171 999 9999` | — | `4901719999999` | المسافات والأقواس فواصل بشرية |
| `00491719999999` | — | `491719999999` | `00` هي الصيغة المكتوبة لـ `+` |
| `971501010101` | — | `971501010101` | دولي أصلاً |
| `050-101-0101` | `971` | `971501010101` | الصفر الأول بادئة محلية تُستبدل برمز الدولة |
| `0501010101` | (فارغ) | `0501010101` | بدون رمز دولة لا يمكن التخمين → يُمرَّر كما هو |

**لماذا هذا ملف مستقل:** التطبيع يحدث عند حافة البوابة فقط، فلا يحتاج أي جزء آخر من التطبيق (البروفايل، الحجز، Filament) أن يعرف عنه شيئاً، ولا نُعدّل الأرقام المخزّنة في قاعدة البيانات إطلاقاً.

---

### 4.5 ملف جديد: `app/Services/Sms/Drivers/SevenSmsDriver.php` ⭐ **قلب المهمة**

**ماذا:** تنفيذ بوابة seven.io فوق `Illuminate\Support\Facades\Http`.

**لماذا HTTP client وليس حزمة `seven.io/api` الرسمية؟** الواجهة نداء `POST` واحد بحقول form؛ البقاء على `Http` يعني: صفر تبعيات جديدة، وقابلية كاملة للتزييف بـ `Http::fake()` في الاختبارات، ونفس أسلوب باقي المشروع (`FiskalyService` يعمل بنفس الطريقة).

**تفاصيل التنفيذ:**

1. **بناء الطلب:** رؤوس `X-Api-Key` + `Accept: application/json`، جسم `asForm()`، مهلة قابلة للضبط.
2. **حذف المعاملات الفارغة:** أي معامل بلا قيمة **لا يُرسل إطلاقاً**. السبب مباشر من التوثيق: الكود `308` يعني «معامل غير معروف»، و`from` فارغ يُقرأ كاسم مرسل غير صالح (`201`) بدل «استخدم الافتراضي».
3. **قراءة الرد بالطريقة الصحيحة:** يُقرأ `success` من الجسم لا حالة HTTP، ويُقارن بـ `'100'`.
4. **معالجة الرفض الجزئي:** `collectMessageFailures()` يمرّ على `messages[]` ويلتقط أي `success: false` حتى لو قال الغلاف `100`.
5. **دعم الرد النصي:** لو أتى الجسم كنصّ خام (كود من 3 أرقام) بدل JSON، يُفسَّر بشكل صحيح بدل أن ينهار التحليل.
6. **رسائل أخطاء مفهومة:** جدول `STATUS_MESSAGES` يحوّل `900` إلى «فشل المصادقة — تحقّق من `SEVEN_API_KEY`» بدل رقم غامض في السجل.
7. **حماية اسم المرسل:** `resolveSender()` يفحص الطول (11 أبجدي / 16 رقمي)؛ إن تجاوزه **يُسقط المعامل ويسجّل تحذيراً** بدل أن يقتطعه. السبب: الاقتطاع الصامت يرسل باسم لم يختره أحد؛ الإسقاط يجعل الرسالة تصل باسم الحساب الافتراضي مع أثر واضح في السجل.
8. **وضع التجربة الجافّة:** `SEVEN_DEBUG=true` يضيف `debug=1` فيتحقّق seven.io من الرسالة ويسعّرها **دون إرسالها ودون خصم رصيد** — مثالي لبيئة staging.
9. **`balance()`:** استعلام `GET /api/balance` (مجاني، لا يُخصم منه شيء) — أرخص إثبات أن المفتاح صالح وأن الخادم يصل للبوابة.

---

### 4.6 ملف جديد: `app/Services/Sms/SmsManager.php`

**ماذا:** يحلّ المزوّد المُسمّى في `config('sms.driver')` ويفوّض إليه. وهو نفسه ينفّذ `SmsGateway`.

**لماذا المفتاح الرئيسي يُفحص هنا وليس داخل كل مزوّد:**

```php
if (! config('sms.enabled', false)) {
    return SmsResult::skipped($driverName, $to, $options['from'] ?? null);
}
```

هذا يضمن أن «الـ SMS مطفأ» يعني الشيء نفسه مهما كان المزوّد — وهو **بالضبط** الشرط الذي تقرأه نقاط الـ OTP لتقرّر هل ما زال آمناً إرجاع الكود في الاستجابة. المصدر واحد، فلا يمكن للاثنين أن يتناقضا. هذا هو الحلّ المباشر للثغرة الموصوفة في القسم 1.2.

---

### 4.7 ملفات جديدة أخرى

| الملف | الدور |
|---|---|
| `app/Services/Sms/Drivers/VonageSmsDriver.php` | منطق Vonage القديم منقولاً حرفياً إلى مزوّد مستقل — `SMS_DRIVER=vonage` يعيد السلوك السابق تماماً |
| `app/Services/Sms/Drivers/LogSmsDriver.php` | يكتب الرسالة في السجل بدل إرسالها. نظير `MAIL_MAILER=log` للتطوير المحلي |
| `app/Services/Sms/Exceptions/SmsDeliveryException.php` | يرث `RuntimeException` **عمداً**، ليستمر كل مَن يلتقط `RuntimeException` حالياً بالعمل دون تعديل. يحمل `statusCode` و`driver` |
| `app/Console/Commands/SmsBalanceCommand.php` | `php artisan sms:balance` — يعرض حالة القناة والمزوّد واسم المرسل ثم الرصيد |

---

### 4.8 الملفات المعدَّلة

#### `app/Services/OtpDeliveryService.php`

- الحقن الآن `SmsGateway` بدل نداء Vonage الخام (حُذف ~35 سطراً من منطق HTTP مكرّر).
- **تحسين سلوكي:** كان النص ثابتاً «expires in 10 minutes» بينما مدة الصلاحية تُقرأ من `config('otp.ttl_minutes')` وقد تختلف. الآن تُحسب المدة الفعلية من `$expiresAt` وتُستخدم في النص **وفي معامل `ttl`** المُرسل للبوابة.

**لماذا `ttl`:** كود يصل بعد انتهاء صلاحيته أسوأ من كود لا يصل، لأن المستخدم يُدخله فيحصل على فشل لا يفهم سببه.

- يُضاف `label => 'otp'` لتمييز رسائل الـ OTP في إحصاءات لوحة seven.io.

#### `app/Services/SmsService.php`

أصبح واجهة رفيعة فوق `SmsGateway`. **التوقيع العام `send(string $phone, string $text): void` لم يتغيّر**، لذا `SendAppointmentReminderJob` يعمل دون أي تعديل عليه.

#### `app/Providers/AppServiceProvider.php`

```php
$this->app->singleton(SmsManager::class);
$this->app->alias(SmsManager::class, SmsGateway::class);
```
ربط واحد يمرّ عبره كل إرسال في التطبيق.

#### `app/Http/Controllers/Api/PhoneVerificationController.php`  🔒

```diff
- $smsEnabled = (bool) config('services.vonage.enabled', false);
+ $smsEnabled = (bool) config('sms.enabled', false);
```

#### `app/Http/Controllers/Api/PasswordResetController.php`  🔒

```diff
- return $channel === OtpType::SMS_OTP && !config('services.vonage.enabled', false);
+ return $channel === OtpType::SMS_OTP && !config('sms.enabled', false);
```

**هذان السطران هما أهم تعديلين أمنيين في المهمة** — بدونهما كان تفعيل seven.io سيُرسل الكود ويُبقيه معروضاً في الاستجابة في آن واحد (القسم 1.2).

#### `.env.example`

أُضيف قسم SMS موثّق بالكامل، وأُبقي قسم Vonage تحته بعنوان صريح «legacy fallback».

---

### 4.9 ما لم يُمسّ إطلاقاً (بقرارك)

`app/Services/VonageSdkSmsService.php` · `config/vonage.php` · `config/services.php` (كتلة vonage) · `app/Http/Controllers/vonageWebhookController.php` · مسار `/api/test/vonage-sms` · حزمتا `vonage/client` و`vonage/vonage-laravel` في `composer.json`.

كلها بقيت تعمل كما هي، وأصبحت خاملة ما دام `SMS_DRIVER=seven`.

---

## 5. الإعداد المطلوب منك في `.env`

```dotenv
SMS_ENABLED=true
SMS_DRIVER=seven
SMS_DEFAULT_COUNTRY_CODE=971      # اختياري — للأرقام المكتوبة بصيغة محلية (0501010101)

SEVEN_API_KEY=<مفتاحك من https://dashboard.seven.io/developer/api>
SEVEN_FROM=Barber                 # حد أقصى 11 حرفاً — اتركه فارغاً لاستخدام رقم حسابك الافتراضي
SEVEN_DEBUG=false                 # true = تجربة جافّة بلا إرسال وبلا خصم رصيد
```

ثم:
```bash
php artisan config:clear
php artisan sms:balance           # يتحقّق من المفتاح دون إرسال أي رسالة ودون تكلفة
```

> ⚠️ **تنبيه:** لحظة ضبط `SMS_ENABLED=true` يتوقّف إرجاع الـ OTP في استجابات `/api/profile/phone/send-otp` و`/api/password/forgot` (إلا إذا كان `APP_DEBUG=true`). هذا هو السلوك الصحيح المقصود — تأكّد أن تطبيق الموبايل جاهز لقراءة الكود من الرسالة لا من الاستجابة.

---

## 6. الاختبارات والتحقّق

### 6.1 اختبارات آلية جديدة: `tests/Feature/Sms/SevenSmsDriverTest.php`

**20 اختباراً، 47 تأكيداً — كلها ناجحة**، ولا تلمس الشبكة ولا تكلّف شيئاً (`Http::fake`):

| الاختبار | ماذا يثبت |
|---|---|
| إرسال ناجح | قراءة `message_ids` و`total_price` و`balance` |
| رأس المصادقة والتطبيع | `X-Api-Key` صحيح، و`+971-50-101-0101` تُرسل كـ `971501010101` |
| حذف المعاملات الفارغة | لا يُرسل `ttl`/`label`/`flash`/`foreign_id`/`debug` بلا قيمة (وقاية من `308`) |
| تمرير `ttl` و`label` | تصل للبوابة حين تُطلب |
| وضع `debug` | يُرسل `debug=1` عند تفعيل التجربة الجافّة |
| اسم مرسل طويل | `BarberBooking` (13 حرفاً) يُسقَط ولا يُرسل (وقاية من `201`) |
| الكود `900` | مفتاح API خاطئ ← استثناء برسالة مفهومة |
| الكود `202` | رقم غير صالح ← `statusCode` صحيح في الاستثناء |
| الكود `500` | رصيد غير كافٍ ← رسالة واضحة |
| **رفض جزئي** | غلاف `100` + رسالة `success:false` ← يُعتبر فشلاً لا نجاحاً |
| رد نصّي خام | `"100"` كنص عادي يُفسَّر بشكل صحيح |
| القناة مطفأة | `SMS_ENABLED=false` ← `skipped` وصفر نداءات شبكة |
| مفتاح مفقود | `skipped` وصفر نداءات شبكة (لا استثناء) |
| `balance()` | نداء `GET /api/balance` صحيح وقراءة صحيحة |
| تطبيع الأرقام (6 حالات) | كل قواعد الجدول في القسم 4.4 |

### 6.2 عدم كسر ما هو موجود

```
Tests\Feature\AuthVerificationFlowTest ....... 10 passed
Tests\Feature\PasswordResetFlowTest .......... 14 passed
```
كل مسارات الـ OTP والمصادقة الحالية تعمل دون أي تعديل عليها.

### 6.3 نتيجة السويت الكاملة

`243 passed, 2 skipped, 17 failed`

الـ 17 الفاشلة **سابقة لهذا العمل ولا علاقة لها بالـ SMS**: `AdvancedTaxCalculatorTest`، `TaxCalculatorService2Test`، `Fiskaly*Test`، `ProfileImageUploadTest`، `ExampleTest` (صفحة الهبوط)، و`DeleteAccountTest` (تأكيد على جدول `users` عند حذف الحساب). لا واحد منها يستدعي أي كود إرسال SMS.

### 6.4 ما لم يُنفَّذ

**لم يُرسَل أي SMS حقيقي ولم يُستخدم مفتاحك الفعلي** — لم تختر ذلك، وهو يكلّف رصيداً. حين تريد التجربة الحقيقية: املأ `SEVEN_API_KEY`، شغّل `php artisan sms:balance` أولاً (مجاني)، ثم إن أردت إرسالاً حقيقياً اطلب مني ذلك صراحةً.

---

## 7. كيف ترجع عن القرار (خطة التراجع)

```dotenv
SMS_DRIVER=vonage
```
سطر واحد. لا `composer`، لا migration، لا تعديل كود — كل ملفات Vonage وحزمها ما زالت في مكانها.

---

## 8. ملخّص الملفات

### جديدة (10)

| الملف | الغرض |
|---|---|
| `config/sms.php` | إعدادات القناة والمزوّدين |
| `app/Services/Sms/SmsGateway.php` | الواجهة المشتركة |
| `app/Services/Sms/SmsResult.php` | كائن النتيجة الموحّد |
| `app/Services/Sms/SmsManager.php` | اختيار المزوّد + المفتاح الرئيسي |
| `app/Services/Sms/PhoneNumberNormalizer.php` | تطبيع الأرقام |
| `app/Services/Sms/Exceptions/SmsDeliveryException.php` | استثناء الإرسال |
| `app/Services/Sms/Drivers/SevenSmsDriver.php` | **بوابة seven.io** |
| `app/Services/Sms/Drivers/VonageSmsDriver.php` | خط الرجوع |
| `app/Services/Sms/Drivers/LogSmsDriver.php` | مزوّد السجل للتطوير |
| `app/Console/Commands/SmsBalanceCommand.php` | `php artisan sms:balance` |
| `tests/Feature/Sms/SevenSmsDriverTest.php` | 20 اختباراً |

### معدَّلة (6)

| الملف | التعديل |
|---|---|
| `app/Services/OtpDeliveryService.php` | يستخدم `SmsGateway` + `ttl` ديناميكي + `label` |
| `app/Services/SmsService.php` | واجهة رفيعة فوق `SmsGateway` (نفس التوقيع العام) |
| `app/Providers/AppServiceProvider.php` | ربط `SmsGateway` ← `SmsManager` |
| `app/Http/Controllers/Api/PhoneVerificationController.php` | 🔒 `config('sms.enabled')` |
| `app/Http/Controllers/Api/PasswordResetController.php` | 🔒 `config('sms.enabled')` |
| `.env.example` | قسم SMS موثّق + وسم Vonage كخط رجوع |

**لم تُضَف أي حزمة composer، ولم تُحذف أي حزمة، ولا يوجد أي migration.**

</div>
