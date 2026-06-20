<div dir="rtl">

# تقرير تفصيلي: ميزة تأكيد رقم الهاتف بعد تسجيل الدخول (Post-Login Phone Verification)

> **تاريخ التنفيذ:** 2026-06-20
> **النطاق:** إضافة مسار API مستقل يسمح للمستخدم المسجّل دخوله بتأكيد رقم هاتفه عبر OTP يُرسل بالـ SMS، مع إظهار حالة التأكيد في الإعدادات/البروفايل.

---

## 1. الفكرة العامة من المهمة

السلوك المطلوب من جهة المستخدم:

1. المستخدم يسجّل دخوله ويستخدم التطبيق بشكل طبيعي (حسابه مفعّل أصلاً عبر الإيميل).
2. في شاشة **الإعدادات**، بجانب رقم هاتفه تظهر شارة: «لم يتم تأكيد رقم الهاتف».
3. عند جلب بيانات الـ **profile** (بشكل دوري) يستطيع التطبيق قراءة حالة تأكيد الهاتف.
4. لو الرقم غير مؤكّد، التطبيق يعرض تنبيهاً يحثّ المستخدم على التأكيد (تنبيه **اختياري**، لا يمنع أي شيء).
5. عند الضغط على «تأكيد»، نرسل **SMS فيها OTP** إلى رقم الحساب.
6. نعرض للمستخدم حقل إدخال الـ OTP.
7. إذا أدخل OTP صحيحاً ⟵ **نفعّل رقمه** (نضبط `phone_verified_at`).

---

## 2. الوضع قبل التنفيذ — ماذا كان موجوداً وماذا كان ناقصاً

النظام كان فيه بنية تحتية قوية للـ OTP، لكن **لم يكن فيه أي مسار مخصّص لتأكيد الهاتف بعد تسجيل الدخول**. التفصيل:

### الموجود مسبقاً (أُعيد استخدامه كما هو)

| المكوّن | الملف | الدور |
|---|---|---|
| توليد OTP وإرساله | `app/Services/OtpService.php` | `generate($user, $length, OtpType::SMS_OTP)` يُنشئ سجل OTP بـ `phone` ويُطلق `SendOtpDeliveryJob` |
| توصيل SMS | `app/Services/OtpDeliveryService.php` | `sendSmsOtp()` عبر Vonage (أو log محلياً) |
| التحقق من OTP | `app/Services/OtpService.php` | `validate($phone, $otp, OtpType::SMS_OTP)` يتحقق حسب الهاتف |
| تثبيت التفعيل | `app/Services/AccountVerificationService.php` | `markVerified($user, OtpType::SMS_OTP)` يضبط `phone_verified_at` |
| إخفاء الوجهة | `app/Services/AccountVerificationService.php` | `maskTarget($phone, OtpType::SMS_OTP)` |
| حقول الحالة | `app/Http/Resources/UserResource.php` | كان يرجّع `phone_verified_at` و`is_account_verified` |

### الناقص (وهو ما بنيناه)

- **لا يوجد endpoint** يسمح لمستخدم **مسجّل دخوله** أن يطلب OTP لرقمه ويؤكّده.
- المسار الوحيد المشابه (`OtpController::verifyOtp`) **غير صالح لهذا الغرض** (الشرح في القسم 3).
- **ثغرة:** تعديل البروفايل كان يسمح بتغيير الهاتف **دون** تصفير `phone_verified_at`، فيبقى الرقم «مؤكّداً» وهو يؤشّر على رقم جديد غير مؤكّد.

### نقطة معمارية محورية

الدالة `User::isAccountVerified()` ترجع `true` إذا كان **الإيميل أو الهاتف** مؤكّداً:

```php
// app/Models/User.php
public function isAccountVerified(): bool
{
    return $this->email_verified_at !== null || $this->phone_verified_at !== null;
}
```

نتيجة ذلك: المستخدم الذي فعّل حسابه بالإيميل يُعتبر «حساب مفعّل» وداخل التطبيق ومعه tokens، بينما `phone_verified_at` لا يزال `null`. لذلك **تأكيد الهاتف خطوة إضافية مستقلة تماماً عن بوابة التفعيل** ولا تتعارض معها — وهذا ما يجعل الميزة آمنة الإضافة دون لمس منطق المصادقة.

---

## 3. القرارات المعمارية وأسبابها

### 3.1 لماذا endpoints جديدة مخصّصة بدل إعادة استخدام `verify-otp`؟

`OtpController::verifyOtp` مُغرٍ لكنه **خاطئ هنا** لسببين:

1. **عام وبدون مصادقة:** يجلب المستخدم من `phone` الموجود في الـ body. لو استخدمناه، أي مستخدم يقدر يطلب/يؤكّد OTP لرقم حساب آخر (مشكلة أمنية: انتحال/اختطاف أرقام).
2. **يُصدر tokens جديدة** (لأنه جزء من activation flow). أما هنا فالمستخدم **مسجّل دخوله أصلاً** ولا نريد عمل re-login أو إصدار tokens.

**القرار:** Controller مستقل `PhoneVerificationController` محمي بـ `auth:sanctum`، يعمل على `$request->user()` حصراً — رقم الهاتف يأتي من **الحساب**، لا من body موثوق — ولا يلمس أي tokens.

### 3.2 القرارات الأربعة المتّفق عليها (تحدّد سلوك التنفيذ)

| القرار | الاختيار | الأثر في الكود |
|---|---|---|
| مصدر الرقم | **السماح بإدخال/تعديل الرقم** ضمن نفس العملية | `sendOtp` يقبل `phone` اختياري ويحدّث الحساب قبل الإرسال |
| إلزامي أم تنبيه | **تنبيه اختياري فقط** | لا middleware ولا بوابة على أي مسار؛ مجرد حقول حالة في الـ Resource |
| تغيير الرقم | **تصفير التحقق** | عند تغيير الهاتف نضبط `phone_verified_at = null` (في الـ Controller وفي تعديل البروفايل) |
| رقم مكرر | **رفض التكرار (422)** | فحص `ensurePhoneIsAvailable()` متوافق مع قيد `unique(phone)` في قاعدة البيانات |

---

## 4. التغييرات ملفاً ملفاً (ماذا / لماذا / كيف يخدم المهمة)

### 4.1 ملف جديد: `app/Http/Controllers/Api/PhoneVerificationController.php`

**ماذا:** Controller جديد فيه دالتان عامتان + دالتان مساعدتان خاصتان.

#### `sendOtp(Request $request)`

تسلسل العمل (بالترتيب، وكل خطوة لها سبب):

1. **التحقق من المدخلات:** `phone` اختياري (`sometimes|string|max:20`).
2. **تبنّي رقم جديد/معدّل (إن وُجد):** لو أُرسل `phone` ويختلف عن الحالي:
   - `ensurePhoneIsAvailable()` ⟵ رفض 422 إذا الرقم مملوك لحساب آخر.
   - تحديث `phone` وتصفير `phone_verified_at` (لأننا سنؤكّد الرقم الجديد).
   - *يخدم المهمة:* يغطّي حالتين واقعيتين — مستخدم سجّل بالإيميل بلا رقم، أو يريد تصحيح خطأ مطبعي في رقمه.
3. **وجوب وجود رقم:** لو لا يوجد رقم بالحساب ولا أُرسل ⟵ 422 برسالة واضحة.
4. **منع التكرار غير المجدي:** لو الرقم **مؤكّد مسبقاً** ⟵ 400 «مؤكّد مسبقاً».
5. **Cooldown:** فحص آخر OTP لنفس الرقم/النوع؛ لو ضمن 60 ثانية ⟵ 429 مع `retry_after`. *يخدم المهمة:* يمنع إساءة استخدام إرسال SMS (وله كلفة فعلية).
6. **توليد وإرسال:** `OtpService::generate($user, OTP_LENGTH, OtpType::SMS_OTP)` ⟵ يمرّ تلقائياً عبر `SendOtpDeliveryJob` ⟵ Vonage (أو log محلياً).
7. **الاستجابة:** `success` + `message` + `masked_destination` (الرقم مُقنّعاً) + `phone_verified=false`، و`otp` **يُرجَع مؤقتاً للتجربة طالما خدمة SMS غير مفعّلة** (`VONAGE_SMS_ENABLED=false`) أو في وضع debug — ويختفي تلقائياً بمجرد تفعيل Vonage.

#### `verifyOtp(Request $request)`

1. **التحقق:** `otp` مطلوب.
2. **حواجز:** لا يوجد رقم ⟵ 422؛ مؤكّد مسبقاً ⟵ يرجع نجاحاً idempotent مع بيانات المستخدم.
3. **التحقق من OTP:** `OtpService::validate($user->phone, $otp, OtpType::SMS_OTP)` ⟵ فشل = 422.
4. **التثبيت:** `AccountVerificationService::markVerified($user, OtpType::SMS_OTP)` يضبط `phone_verified_at`. **بدون أي tokens** — المستخدم مصادق أصلاً.
5. **الاستجابة:** `UserResource` المحدّث (يحوي الآن `phone_verified=true`).

#### دوال مساعدة خاصة

- `ensurePhoneIsAvailable($phone, $user)` ⟵ يرمي 422 إذا الرقم مستخدم في حساب آخر (`where('id','!=',$user->id)`).
- `cooldownRemaining($user)` ⟵ يحسب الثواني المتبقية اعتماداً على `created_at` لآخر سجل OTP (بالـ timestamps لتجنّب اختلاف سلوك Carbon بين الإصدارات).

**كيف يخدم المهمة:** هذا الملف هو **قلب الميزة** — الخطوات 5/6/7 من وصف المهمة (إرسال SMS، إدخال OTP، تفعيل الرقم) كلها هنا، وبشكل آمن مرتبط بالمستخدم المسجّل.

---

### 4.2 `routes/api.php` — تسجيل المسارين

**ماذا:** أضفنا مسارين داخل المجموعة المحميّة الموجودة `['auth:sanctum', 'verified.customer']` بجانب مسارات `profile`، مع استيراد الـ Controller.

```php
Route::post('profile/phone/send-otp', [PhoneVerificationController::class, 'sendOtp'])
    ->middleware('throttle:6,1')
    ->name('profile.phone.send-otp');
Route::post('profile/phone/verify-otp', [PhoneVerificationController::class, 'verifyOtp'])
    ->middleware('throttle:10,1')
    ->name('profile.phone.verify-otp');
```

**لماذا داخل `verified.customer`؟** لأن المستخدم لا يصل لشاشة الإعدادات إلا وهو مسجّل دخول وحسابه مفعّل أصلاً — فالوضع متّسق، ولا يخل بكون تأكيد الهاتف اختيارياً (الاختياري هو تأكيد *الهاتف*، لا الوصول للمسار).

**لماذا `throttle`؟** طبقة حماية ثانية فوق الـ cooldown: `6/دقيقة` للإرسال، و`10/دقيقة` للتحقق (لمنع تخمين الـ OTP بالقوة الغاشمة — brute force).

**كيف يخدم المهمة:** يوفّر نقطتي الدخول الفعليتين اللتين يستدعيهما التطبيق.

---

### 4.3 `app/Http/Controllers/Api/ProfileController.php` — سدّ ثغرة + توافق

**ماذا عدّلنا في `update()`:**

1. نقلنا `$user = $request->user();` **قبل** الـ validation (لازم لقاعدة `unique`).
2. أضفنا قاعدة تفرّد على الهاتف تتجاهل المستخدم نفسه:
   ```php
   'phone' => ['sometimes', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user->id)],
   ```
3. **تصفير التحقق عند تغيّر الرقم فعلياً:**
   ```php
   if (isset($data['phone']) && $data['phone'] !== $user->phone) {
       $user->phone = $data['phone'];
       $user->phone_verified_at = null;
   }
   ```
4. استبدلنا `User::find($user->id)` بـ `$user->fresh()` (أنظف، وأزال خطأ التحليل الساكن P1005).

**لماذا:** القرار المتّفق عليه «صفّر التحقق عند تغيير الرقم». بدونها يبقى رقم «مؤكّد» يؤشّر على رقم جديد لم يُرسل إليه أي OTP — وهي ثغرة منطقية وأمنية.

**كيف يخدم المهمة:** يضمن أن حالة التأكيد التي يعرضها التطبيق **تعكس الحقيقة دائماً** بعد أي تعديل للرقم.

---

### 4.4 `app/Http/Resources/UserResource.php` — حقل بوليان جاهز

**ماذا:** أضفنا حقلاً واحداً:

```php
'phone_verified' => (bool) $this->phone_verified_at,
```

**لماذا:** الحقول المطلوبة لعرض الحالة في الإعدادات وللـ polling كانت موجودة أصلاً (`phone_verified_at`, `is_account_verified`)، لكن بوليان صريح `phone_verified` أسهل للفرونت لرسم الشارة دون تحويل تاريخ.

**كيف يخدم المهمة:** يغطّي الخطوتين 2 و3 من وصف المهمة (إظهار الحالة بجانب الرقم + قراءتها عند جلب البروفايل) مباشرة وبأقل احتكاك للفرونت.

---

## 5. عقود الـ API (للفرونت)

> كل النداءات تتطلب `Authorization: Bearer <access_token>`.

### 5.1 إرسال OTP — `POST /api/profile/phone/send-otp`

**Body (اختياري):**
```json
{ "phone": "+9665xxxxxxxx" }
```

**نجاح `200`:**
```json
{
  "success": true,
  "message": "A verification code has been sent to your phone number.",
  "masked_destination": "********xxxx",
  "phone_verified": false,
  "otp": "123456"   // مؤقتاً للتجربة طالما SMS غير مفعّل (أو debug) — يختفي عند تفعيل Vonage
}
```

**حالات الخطأ:**
- `422` لا يوجد رقم / صيغة غير صالحة / الرقم مستخدم بحساب آخر.
- `400` الرقم مؤكّد مسبقاً.
- `429` ضمن فترة الـ cooldown (مع `retry_after`).

### 5.2 التحقق من OTP — `POST /api/profile/phone/verify-otp`

**Body:**
```json
{ "otp": "123456" }
```

**نجاح `200`:**
```json
{
  "success": true,
  "message": "Your phone number has been verified successfully.",
  "data": { "...": "UserResource (يحوي phone_verified=true)" }
}
```

**خطأ `422`:** OTP غير صحيح أو منتهٍ.

### 5.3 قراءة الحالة — `GET /api/profile`

الـ `data` تحوي الآن: `phone`, `phone_verified` (بوليان), `phone_verified_at`, `is_account_verified`.

---

## 6. الحالات الحديّة المُعالَجة

- **مستخدم بلا رقم** (سجّل بالإيميل): يرسل `phone` في `send-otp` فيُحدَّث حسابه ثم يُرسل OTP.
- **تغيير الرقم أثناء التأكيد:** يصفّر التحقق ويُرسل لرقم جديد فوراً (لا cooldown لأن الرقم الجديد بلا سجلّات سابقة).
- **رقم مملوك لحساب آخر:** رفض 422 ودود قبل أن يضرب قيد قاعدة البيانات (يمنع خطأ 500 غامض).
- **استدعاء التحقق لرقم مؤكّد:** استجابة idempotent ناجحة بدل خطأ.
- **إعادة الإرسال السريع:** يحجبه الـ cooldown (60 ثانية) + `throttle`.
- **تخمين الـ OTP:** يحدّه `throttle:10,1` على التحقق + انتهاء الصلاحية بعد 10 دقائق + إبطال السجلات القديمة عند كل توليد.

---

## 7. ما الذي **لم** يُشمل عمداً (وانتباه مهم)

في `app/Http/Controllers/Api/AuthController.php` يوجد سطر يدوس على طريقة التسجيل بالقوة:

```php
$registrationMethod = RegistrationMethod::from($data['registration_method']);
$registrationMethod = RegistrationMethod::from('email');  // ← يلغي اختيار المستخدم
```

هذا يجعل **كل** تسجيل يُعامَل كـ email (ولذلك الـ OTP يذهب للإيميل دائماً). حذف هذا السطر **يفعّل مسار التسجيل بالهاتف بالكامل** (OTP بالـ SMS وقت التسجيل) — وهو تغيير أوسع من هذه المهمة وله تبعات على تدفّق التسجيل. **تُركت كقرار منفصل**؛ ميزة «تأكيد الهاتف بعد الدخول» مستقلة عنها تماماً.

---

## 8. كيف تختبر محلياً

1. سجّل دخول واحصل على `access_token`.
2. `POST /api/profile/phone/send-otp` مع رقم في الـ body ⟵ سيُرجَع الـ `otp` في الاستجابة (لأن `VONAGE_SMS_ENABLED` غير مفعّل حالياً)، ولن يُضرب Vonage فعلياً. عند تفعيل `VONAGE_SMS_ENABLED=true` وبقية المفاتيح، يتوقّف إرجاع الـ `otp` ويُرسَل عبر SMS فقط.
3. `POST /api/profile/phone/verify-otp` بالـ `otp` ⟵ يصبح `phone_verified=true`.
4. `GET /api/profile` ⟵ تأكّد أن `phone_verified=true`.
5. غيّر الرقم عبر `POST /api/profile` ⟵ تأكّد أن `phone_verified` رجع `false`.

---

## 9. ملخّص الملفات المتأثّرة

| الملف | النوع | الغرض |
|---|---|---|
| `app/Http/Controllers/Api/PhoneVerificationController.php` | **جديد** | قلب الميزة: إرسال + تحقق OTP للهاتف |
| `routes/api.php` | تعديل | تسجيل المسارين + import + throttle |
| `app/Http/Controllers/Api/ProfileController.php` | تعديل | تصفير التحقق عند تغيير الرقم + تفرّد الهاتف |
| `app/Http/Resources/UserResource.php` | تعديل | حقل `phone_verified` البوليان |

**لم نُضِف أي حزمة (package) ولا أي migration** — كل البنية التحتية (OtpService / OtpDeliveryService / Vonage / حقل `phone_verified_at`) كانت موجودة وأعيد استخدامها.

</div>
