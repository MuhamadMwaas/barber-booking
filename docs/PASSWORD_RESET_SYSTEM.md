# نظام إعادة تعيين كلمة المرور — المرجع الهندسي

> **Look Up App — Password Reset Engineering Reference**
> آخر تحديث: 2026-07-26
> الجمهور: مطوّرو الباك إند. للفرونت إند راجع [`docs/API/PASSWORD_RESET_FRONTEND_GUIDE.md`](API/PASSWORD_RESET_FRONTEND_GUIDE.md).

---

## 📋 جدول المحتويات

1. [الفكرة العامة والمشكلة التي يحلّها](#1-الفكرة-العامة-والمشكلة-التي-يحلها)
2. [خريطة الملفات](#2-خريطة-الملفات)
3. [نموذج البيانات](#3-نموذج-البيانات)
4. [الفلو الكامل خطوة بخطوة](#4-الفلو-الكامل-خطوة-بخطوة)
5. [تشريح `OtpService`](#5-تشريح-otpservice)
6. [تشريح `PasswordResetService`](#6-تشريح-passwordresetservice)
7. [مسار التوصيل — كيف يصل الكود فعلياً](#7-مسار-التوصيل--كيف-يصل-الكود-فعلياً)
8. [القرارات الأمنية ولماذا](#8-القرارات-الأمنية-ولماذا)
9. [الإعدادات](#9-الإعدادات)
10. [المسار القديم (Legacy)](#10-المسار-القديم-legacy)
11. [التستات](#11-التستات)
12. [مطبّات وأشياء تنتبه لها](#12-مطبات-وأشياء-تنتبه-لها)

---

## 1. الفكرة العامة والمشكلة التي يحلّها

### المشكلة

النظام يسمح بإنشاء حساب بطريقتين (`registration_method`):

| الطريقة | الحقل المطلوب | هل يوجد إيميل؟ |
|---|---|---|
| `email` | `email` | نعم |
| `phone` | `phone` | **لا — `email` يكون `NULL`** |

إعادة التعيين القديمة كانت مبنية على الإيميل فقط. يعني **حساب مسجّل برقم هاتف كان لا يملك أي وسيلة لاستعادة كلمة مروره إطلاقاً**.

### الحل

فلو استعادة **مستقل عن قناة التسجيل**، من ثلاث خطوات:

```
┌──────────────────────┐   ┌──────────────────────────┐   ┌────────────────────┐
│ 1. forgot-password   │   │ 2. password/verify-otp   │   │ 3. reset-password  │
│                      │   │                          │   │                    │
│ يحدد القناة          │──▶│ يتحقق من الكود           │──▶│ يستهلك الـ grant   │
│ ويرسل OTP            │   │ ويصدر reset_token        │   │ ويغيّر كلمة المرور │
└──────────────────────┘   └──────────────────────────┘   └────────────────────┘
        ↓                            ↓                             ↓
   صف في `otps`              صف في `password_reset_grants`   password + إبطال
   purpose=password_reset     token_hash = sha256(token)      كل الجلسات
```

**المبدأ الأساسي:** القناة يحدّدها **الطلب** (`registration_method` في الـ payload)، لا طريقة التسجيل المخزّنة. يعني:

- حساب مسجّل بإيميل وعنده رقم هاتف → يقدر يستعيد عبر SMS.
- حساب مسجّل بهاتف فقط → SMS هو خياره الوحيد (ما عنده إيميل أصلاً).
- البحث يتم على العمود المطابق للقناة (`where('phone', ...)` أو `where('email', ...)`)، فالقناة والوجهة دائماً متطابقتان.

---

## 2. خريطة الملفات

| الملف | الدور |
|---|---|
| `app/Enum/OtpPurpose.php` | يفصل *ماذا يفتح الكود* عن *كيف يصل* |
| `app/Enum/OtpType.php` | القناة: `EMAIL_OTP=1` / `SMS_OTP=2` |
| `app/Models/Otp.php` | صف الكود، مع `purpose` و `attempts` |
| `app/Models/PasswordResetGrant.php` | التوكن قصير العمر أحادي الاستخدام |
| `app/Services/OtpService.php` | توليد/تحقق/إبطال/cooldown — لكل القنوات والأغراض |
| `app/Services/PasswordResetService.php` | منطق الاستعادة نفسه |
| `app/Services/OtpDeliveryService.php` | التوصيل الفعلي (Mail / Vonage) |
| `app/Jobs/SendOtpDeliveryJob.php` | يجعل التوصيل غير متزامن |
| `app/Http/Controllers/Api/PasswordResetController.php` | الـ HTTP layer فقط |
| `app/Http/Requests/ForgotPasswordRequest.php` | تحقق الخطوة 1 |
| `app/Http/Requests/VerifyPasswordResetOtpRequest.php` | تحقق الخطوة 2 (يرث الأولى) |
| `app/Http/Requests/ResetPasswordRequest.php` | تحقق الخطوة 3 (بشكلين) |
| `config/otp.php` | كل الأرقام السحرية في مكان واحد |
| `lang/{ar,de,en}/passwords.php` | الرسائل |
| `tests/Feature/PasswordResetFlowTest.php` | 14 تست تغطي الفلو |

**Migrations:**
- `2026_07_26_100000_add_purpose_and_attempts_to_otps_table.php`
- `2026_07_26_100100_create_password_reset_grants_table.php`

---

## 3. نموذج البيانات

### 3.1 جدول `otps`

```
id           bigint
email        varchar  NULL   ← يُملأ فقط للـ EMAIL_OTP
phone        varchar  NULL   ← يُملأ فقط للـ SMS_OTP
otp          varchar         ← الكود نصاً صريحاً
type         tinyint  NULL   ← OtpType: 1=email, 2=sms
purpose      varchar(40)     ← OtpPurpose ★ جديد
attempts     tinyint         ← عدّاد التخمينات الخاطئة ★ جديد
used         boolean
expires_at   timestamp
created_at / updated_at
```

**لماذا عمودان منفصلان `email` و `phone` بدل عمود `target` واحد؟**
هذا التصميم موروث، لكنه مفيد: العمودان متنافيان، فمطابقة العمود الصحيح **تثبّت القناة ضمنياً**. لذلك `OtpService::scopeToTarget()` لا يحتاج `where('type', ...)` إضافي.

**`purpose` — أهم إضافة.** قبلها كان كود التفعيل وكود إعادة التعيين نفس الشيء تماماً في قاعدة البيانات، أي أن كوداً أُرسل لتفعيل الحساب كان يصلح لتغيير كلمة المرور. الآن كل غرض جزيرة مغلقة:

- التوليد يبطل الأكواد السابقة **لنفس الغرض فقط** — طلب كود استعادة لا يقتل كود تفعيل معلّق.
- التحقق يبحث ضمن الغرض المطلوب فقط.

`purpose` له `default('account_verification')` والـ migration تعمل backfill للصفوف القديمة، فأي كود لم يُحدَّث ليمرّر الغرض يبقى يعمل كما كان.

### 3.2 جدول `password_reset_grants`

```
id           bigint
user_id      FK users  cascadeOnDelete
token_hash   varchar(64)  UNIQUE   ← sha256 للتوكن، لا التوكن نفسه
channel      tinyint               ← OtpType الذي أثبت الملكية
expires_at   timestamp
used_at      timestamp NULL        ← NULL = ما زال صالحاً
ip_address   varchar(45) NULL      ← تدقيق فقط
user_agent   varchar(255) NULL     ← تدقيق فقط
created_at / updated_at
INDEX (user_id, used_at)
```

**لماذا جدول مخصص وليس `password_reset_tokens` تبع لارافيل؟**
جدول لارافيل الافتراضي **مفتاحه الأساسي هو الإيميل**، وبالتالي لا يستطيع تمثيل حساب بهاتف فقط — وهي بالضبط الحالة التي وُجدت هذه الميزة لأجلها. جدولنا مفتاحه `user_id`.

**لماذا نخزن الهاش فقط؟**
تسريب قاعدة البيانات يجب ألا يسلّم المهاجم توكنات استعادة قابلة للاستخدام مباشرة.

---

## 4. الفلو الكامل خطوة بخطوة

### الخطوة 1 — `POST /api/auth/forgot-password`

```php
PasswordResetController::forgotPassword(ForgotPasswordRequest $request)
```

| # | ما يحدث | عند الفشل |
|---|---|---|
| 1 | `applyRequestLocale()` — يقرأ `locale` من الـ body ثم `Accept-Language` | — |
| 2 | `ForgotPasswordRequest` يتحقق: `registration_method` مطلوب، و`email` أو `phone` حسبه | `422` + `errors` |
| 3 | `channelFor($method)` → `OtpType::SMS_OTP` أو `EMAIL_OTP` | — |
| 4 | `findUser($method, $identifier)` — بحث على العمود المطابق | `404 USER_NOT_FOUND` |
| 5 | `guardAccount($user)` — يرفض `is_active = false` | `403 ACCOUNT_DISABLED` |
| 6 | `cooldownRemaining()` — 60 ثانية لكل وجهة/غرض | `429 OTP_COOLDOWN` + `retry_after` |
| 7 | `sendResetOtp()` → `OtpService::generate(..., PASSWORD_RESET)` | — |
| 8 | يبني الرد مع `masked_destination` + `expires_in` + `resend_after` | — |

الكود يُضاف للرد فقط عندما `shouldExposeOtp()` صحيحة: `app.debug` مفعّل، **أو** القناة SMS وبوابة Vonage مطفأة (لأن الكود عندها لن يصل أبداً). هذا يطابق سلوك `PhoneVerificationController::sendOtp()` الموجود.

### الخطوة 2 — `POST /api/auth/password/verify-otp`

```php
PasswordResetController::verifyOtp(VerifyPasswordResetOtpRequest $request)
```

نفس خطوات 1→5 أعلاه، ثم:

| # | ما يحدث | عند الفشل |
|---|---|---|
| 6 | `verifyOtp()` → `OtpService::validate(..., PASSWORD_RESET)` | `422 INVALID_OTP` |
| 7 | `issueGrant()` — ينشئ التوكن ويبطل أي grant سابق للمستخدم | — |
| 8 | يرد بـ `reset_token` + `expires_at` | — |

**ملاحظة مهمة:** الكود يُحرق (`used = true`) هنا، **قبل** تغيير كلمة المرور. إذا فشلت الخطوة 3 لأي سبب (كلمة مرور ضعيفة مثلاً) فالكود مستهلك لكن **الـ `reset_token` ما زال صالحاً 15 دقيقة** — فالمستخدم يعيد المحاولة بكلمة مرور أقوى دون طلب كود جديد. هذا مقصود.

### الخطوة 3 — `POST /api/auth/reset-password`

```php
PasswordResetController::resetPassword(ResetPasswordRequest $request)
```

يتفرّع حسب `usesGrantToken()`:

**المسار المفضّل (`reset_token` موجود):**

| # | ما يحدث | عند الفشل |
|---|---|---|
| 1 | `findRedeemableGrant($token)` — `sha256` ثم فحص `used_at === null && expires_at > now` | `422 INVALID_RESET_TOKEN` |
| 2 | `guardAccount($grant->user)` | `403 ACCOUNT_DISABLED` |
| 3 | `resetPassword($user, $password, $grant->channel, $grant)` | — |

**المسار القديم (`otp` + وجهة):** يعيد التحقق من الكود مباشرة ثم يستدعي نفس `resetPassword()` بدون grant. تفاصيله في [القسم 10](#10-المسار-القديم-legacy).

---

## 5. تشريح `OtpService`

### `generate(User $user, ?int $length, OtpType $type, OtpPurpose $purpose): string`

كل شيء داخل `DB::transaction`:

1. `invalidateUnusedOtps()` — يعلّم كل الأكواد غير المستخدمة **لنفس الوجهة والقناة والغرض** كـ `used`. النتيجة: **كود حي واحد فقط لكل (وجهة، غرض)** في أي لحظة.
2. `Otp::create([...])` — يكتب `purpose` و `attempts = 0`.
3. `SendOtpDeliveryJob::dispatch(...)->afterCommit()` — `afterCommit` حرجة: بدونها قد يبدأ العامل بمعالجة الوظيفة قبل أن تُثبَّت المعاملة فلا يجد الصف.

يُرجع الكود نصاً صريحاً لأن المتحكّم يحتاجه لوضعه في الرد في بيئة التطوير.

### `validate(string $target, string $otp, OtpType $type, OtpPurpose $purpose): bool`

```php
$record = $this->latestLiveOtp($target, $type, $purpose);   // أحدث صف: !used && expires_at > now
if (!$record) return false;

if ($record->attempts >= $max) { $record->update(['used' => true]); return false; }

if (!hash_equals((string) $record->otp, $otp)) {
    $record->increment('attempts');
    if ($record->attempts >= $max) $record->update(['used' => true]);
    return false;
}

$record->update(['used' => true]);
return true;
```

ثلاث نقاط تستحق الانتباه:

1. **`latestLiveOtp` بدل البحث بقيمة الكود.** السلوك القديم كان `where('otp', $otp)` — أي "هل يوجد أي كود مطابق؟". ذلك يجعل عدّ المحاولات مستحيلاً لأن التخمين الخاطئ لا يطابق أي صف. الآن نجلب الصف الحي الوحيد أولاً ثم نقارن، فيصير للتخمين الخاطئ عنوان واضح يُحسب عليه. `generate()` يضمن أن الصف الحي واحد، فالسلوكان متكافئان وظيفياً.
2. **`hash_equals` بدل `===`** — مقارنة ثابتة الزمن.
3. **حرق الكود عند استنفاد المحاولات.** كود من 6 خانات صالح 10 دقائق = مليون احتمال؛ بلا سقف محاولات هو قابل للتخمين آلياً. السقف الافتراضي 5.

### `cooldownRemaining(string $target, OtpType $type, OtpPurpose $purpose): int`

يجلب أحدث صف لنفس (الوجهة، القناة، الغرض) — **بغض النظر عن `used`** — ويحسب الفرق عن `created_at`. يرجّع 0 إذا مضى الوقت الكافي.

`PhoneVerificationController` كان يملك نسخته الخاصة من هذا المنطق؛ حُذفت وصار يستدعي الخدمة، فتوجد اليوم تطبيقة واحدة فقط.

---

## 6. تشريح `PasswordResetService`

الخدمة لا تعرف شيئاً عن HTTP — تتعامل بـ Models و Enums فقط، فيبقى المتحكّم رقيقاً وتسهل إعادة استخدامها (Filament، أمر artisan، إلخ).

### `issueGrant(User, OtpType, ?string $ip, ?string $ua): array`

```php
$plain = Str::random(64);                     // يُرجع للعميل، لا يُخزّن أبداً
$expiresAt = now()->addMinutes(config('otp.password_reset.token_ttl_minutes'));

DB::transaction(function () {
    $this->revokeGrants($user);               // grant واحد فقط قيد التنفيذ
    PasswordResetGrant::create([
        'token_hash' => hash('sha256', $plain),
        'channel'    => $channel->value,
        ...
    ]);
});
```

`revokeGrants()` قبل الإنشاء يعني أن طلب كود جديد يبطل التوكن السابق تلقائياً — لا يمكن أن تكون عمليتا استعادة قيد التنفيذ لنفس الحساب.

### `resetPassword(User, string, OtpType, ?PasswordResetGrant): User`

كله في معاملة واحدة، وكله **متعمّد التدمير**:

```php
$user->forceFill(['password' => Hash::make($newPassword)])->save();

$this->verificationService->markVerified($user, $channel);   // ① توثيق القناة

$user->tokens()->delete();                                   // ② Sanctum access tokens
$user->refreshTokens()->update(['revoked' => true]);         // ③ refresh tokens

$grant?->forceFill(['used_at' => now()])->save();            // ④ استهلاك الـ grant
$this->revokeGrants($user, exceptId: $grant?->id);           // ⑤ إبطال الباقي
```

**① لماذا `markVerified`؟**
إتمام دورة OTP كاملة يثبت السيطرة على الوجهة. بدون هذه السطر، مستخدم غير مفعّل يعيد تعيين كلمة مروره ثم يُرمى إلى شاشة التفعيل عند الدخول — حلقة مزعجة بلا فائدة أمنية. القناة المستخدمة هي التي تُوثَّق: SMS → `phone_verified_at`، Email → `email_verified_at`.

**②③ لماذا إبطال كل الجلسات؟**
إعادة تعيين كلمة المرور هي **العلاج** لحساب مخترق. لو بقيت التوكنات القديمة صالحة لظلّ المهاجم داخل الحساب رغم تغيير الضحية لكلمة المرور — وهذا يفرّغ العملية من معناها. لاحظ أن `ProfileController::changePassword` كان يفعل هذا أصلاً؛ مسار الاستعادة القديم لم يكن يفعله.

**النتيجة العملية للفرونت إند:** بعد نجاح الخطوة 3 كل التوكنات المخزّنة على الجهاز صارت ميتة. يجب مسحها وإجبار المستخدم على تسجيل دخول جديد.

---

## 7. مسار التوصيل — كيف يصل الكود فعلياً

```
OtpService::generate()
        │
        └── SendOtpDeliveryJob::dispatch()->afterCommit()      [طابور]
                    │
                    └── OtpDeliveryService::deliver()
                                │
                    ┌───────────┴────────────┐
              EMAIL_OTP                  SMS_OTP
                    │                        │
            Mail::to()->send(         sendSmsOtp() → Vonage
              SendOtpMail)            POST {base_url}/sms/json
```

### نقاط حرجة

**يجب أن يكون هناك queue worker يعمل.** بدونه يُكتب الصف في `otps` لكن الوظيفة تبقى في الطابور ولا يصل شيء للمستخدم — والـ API يرجّع `200` بمرح. أعراض هذه الحالة: "الـ API يقول تم الإرسال لكن ما وصلني شي".

**Vonage قد يُتخطّى بصمت.** في `OtpDeliveryService::sendSmsOtp()`:

```php
if (!$enabled || !$key || !$secret || !$from) {
    Log::info('SMS OTP delivery skipped because Vonage is not fully configured.', [...]);
    return;   // ← لا استثناء
}
```

لهذا السبب بالذات يكشف `shouldExposeOtp()` الكود في الرد عندما تكون بوابة SMS مطفأة: بدون ذلك يصير الفلو غير قابل للاختبار إطلاقاً. إذا لم يصل SMS في بيئة يُفترض أنها مضبوطة، ابحث في اللوغ عن السطر أعلاه أولاً.

**فشل Vonage الحقيقي يرمي `RuntimeException`** — يفشل الـ job ويُعاد حسب إعدادات الطابور، والمستخدم قد يستلم رسالة مكرّرة. مقبول.

---

## 8. القرارات الأمنية ولماذا

| القرار | التنفيذ | لولاه |
|---|---|---|
| فصل أغراض الأكواد | `OtpPurpose` + عمود `purpose` | كود تفعيل يصلح لتغيير كلمة المرور |
| سقف محاولات | `attempts` + `config('otp.max_attempts')` | تخمين آلي لكود 6 خانات |
| مقارنة ثابتة الزمن | `hash_equals()` | تسريب توقيتي (نظري) |
| كود حي واحد | `invalidateUnusedOtps()` في `generate` | مجموعة أكواد صالحة تكبر مع كل طلب |
| تخزين هاش التوكن | `sha256` في `token_hash` | تسريب DB = توكنات استعادة جاهزة |
| grant أحادي الاستخدام | `used_at` + `revokeGrants()` | إعادة تشغيل التوكن (replay) |
| إبطال كل الجلسات | `tokens()->delete()` + `revoked` | المهاجم يبقى داخل الحساب بعد الاستعادة |
| رفض الحسابات المعطّلة | `guardAccount()` | الاستعادة تلتف على قرار إداري |
| cooldown لكل وجهة | `cooldownRemaining()` — 60 ث | قصف SMS من عميل يبدّل الـ IP |
| throttle لكل IP | `throttle:5,1` / `10,1` | قصف عام |
| قوة كلمة المرور | نفس قواعد التسجيل | الاستعادة تصير طريقاً لإضعاف الحساب |

### قرار واعٍ: كشف وجود الحسابات

النقطة الوحيدة التي خالفنا فيها المعيار الأمني الشائع. `forgot-password` يرجّع **`404 USER_NOT_FOUND`** صراحةً عندما لا يوجد حساب.

- **الثمن:** يستطيع أي شخص استخدام الـ API لمعرفة هل رقم/إيميل معيّن مسجّل (user enumeration).
- **المقابل:** التطبيق يقدر يعرض "هذا الرقم غير مسجّل" بدل "أرسلنا لك كوداً" الكاذبة التي تترك المستخدم ينتظر رسالة لن تأتي.

هذا **اختيار صريح من مالك المنتج**. إذا تغيّر الرأي، التعديل نقطة واحدة: في `forgotPassword()` استبدل فرع `if (!$user)` بردّ نجاح عام دون إرسال شيء. (لاحظ أن `verify-otp` سيظل يرجّع 404 — ولا بأس، فهو لا يُستدعى إلا بكود لا يملكه إلا من استلمه.)

---

## 9. الإعدادات

كل الأرقام في `config/otp.php`، وكلها لها قيم افتراضية — **لا يوجد متغيّر `.env` إلزامي**.

| المفتاح | `.env` | الافتراضي | المعنى |
|---|---|---|---|
| `otp.length` | `OTP_LENGTH` | `6` | عدد خانات الكود |
| `otp.ttl_minutes` | `OTP_TTL_MINUTES` | `10` | صلاحية الكود |
| `otp.resend_cooldown_seconds` | `OTP_RESEND_COOLDOWN_SECONDS` | `60` | الفاصل بين إرسالين لنفس الوجهة |
| `otp.max_attempts` | `OTP_MAX_ATTEMPTS` | `5` | تخمينات خاطئة قبل حرق الكود |
| `otp.password_reset.token_ttl_minutes` | `PASSWORD_RESET_TOKEN_TTL_MINUTES` | `15` | صلاحية الـ `reset_token` |

الـ throttle لكل IP معرّف في المسارات نفسها (`routes/api.php`): `5,1` للإرسال و `10,1` للتحقق والتعيين.

> **تنبيه:** `config/otp.php` هو المصدر الوحيد لهذه القيم في الكود الجديد. بقيت في الشيفرة القديمة استدعاءات `env('OTP_LENGTH', 6)` مباشرة (في `AuthController` و `OtpController`) — تعمل، لكنها **تعود إلى الافتراضي عند `php artisan config:cache`** لأن `env()` لا يقرأ خارج ملفات الإعدادات في تلك الحالة. مرشّحة للتنظيف لاحقاً.

---

## 10. المسار القديم (Legacy)

`POST /api/auth/reset-password` يقبل شكلين، ويميّز بينهما عبر `ResetPasswordRequest::usesGrantToken()` أي ببساطة: هل `reset_token` موجود؟

```php
'reset_token' => ['required_without:otp', 'nullable', 'string'],
'otp'         => ['required_without:reset_token', 'nullable', 'string', 'max:10'],
```

الحقول `registration_method` / `email` / `phone` مطلوبة **فقط** في الشكل القديم — عبر `Rule::requiredIf` مربوطة بـ `$legacy = fn () => !$this->filled('reset_token')`.

**سبب بقائه:** تطبيق الموبايل المنشور حالياً يرسل الشكل القديم على نفس الـ URL. حذفه كان سيكسر كل النسخ المثبّتة.

**متى يُحذف؟** بعد أن تصير نسبة المستخدمين على نسخة تدعم الخطوات الثلاث كافية. التنظيف حينها: حذف الفرع الثاني من `resetPassword()`، وتبسيط `ResetPasswordRequest` إلى `reset_token` + `password`، وحذف تست `test_legacy_single_request_reset_still_works`.

---

## 11. التستات

`tests/Feature/PasswordResetFlowTest.php` — 14 تست، كلها ناجحة:

| المجموعة | التغطية |
|---|---|
| الخطوة 1 | إرسال SMS لحساب هاتف · إرسال إيميل لحساب إيميل · 404 لوجهة مجهولة · 403 لحساب معطّل · 429 داخل الـ cooldown |
| الخطوة 2 | إصدار `reset_token` · **رفض كود التفعيل على مسار الاستعادة** · رفض الكود منتهي الصلاحية · حرق الكود بعد استنفاد المحاولات |
| الخطوة 3 | تغيير كلمة المرور + توثيق الرقم + إبطال كل الجلسات · رفض إعادة استخدام الـ grant · المسار القديم · رفض كلمة المرور الضعيفة |
| تكامل | تسجيل الدخول بالجديدة ينجح وبالقديمة يفشل (401) |

```bash
php artisan test --filter=PasswordResetFlowTest
```

**ملاحظة عند كتابة تستات جديدة:** `Queue::fake()` موجودة في `setUp()`. بدونها ينفّذ `SendOtpDeliveryJob` بشكل متزامن ويصطدم بـ `Http::fake()` (الرد المزيّف الفارغ يفشل فحص Vonage) فيرمي `RuntimeException: Vonage SMS delivery failed`.

---

## 12. مطبّات وأشياء تنتبه لها

**١. أرقام الهواتف تُقارن كنصوص خام.** لا يوجد تطبيع (normalization) في أي مكان بالنظام. `+491701111111` و `00491701111111` و `0170 1111111` ثلاثة أرقام مختلفة تماماً بنظر قاعدة البيانات. المستخدم الذي يسجّل بصيغة ويستعيد بأخرى سيحصل على `404`. هذا **دَين تقني قائم** يسبق هذه الميزة (نفس السلوك في `login` و `register`)؛ علاجه الصحيح تطبيع مركزي عند الكتابة والقراءة معاً، وهو خارج نطاق ما نُفّذ هنا.

**٢. `Otp::$casts` صار يحوّل `type` و `purpose` إلى Enums.** أي كود يقرأ `$otp->type` يستلم الآن `OtpType` لا `int`. الاستعلامات (`where('type', OtpType::SMS_OTP->value)`) لم تتأثر لأنها تمرّ على query builder.

**٣. `expires_in` في الرد بالثواني، `expires_at` بصيغة ISO 8601.** لا تخلط بينهما.

**٤. الـ cooldown يُحسب من `created_at` لآخر صف بغض النظر عن `used`.** يعني حتى بعد استخدام الكود بنجاح، طلب كود جديد لنفس الوجهة يبقى ممنوعاً 60 ثانية. مقصود — يمنع الحلقات السريعة.

**٥. الحسابات بلا كلمة مرور.** حساب أُنشئ عبر Google (`SocialAuthController`) قد يكون `password` فيه `NULL`. هذا الفلو **يعمل معه بشكل صحيح**: يضع كلمة مرور لأول مرة، فيصير الحساب قابلاً للدخول بالطريقتين. لا يوجد فحص يمنع ذلك، وهذا سلوك مرغوب.

**٦. حسابات الطاقم (admin/manager/provider) غير مستثناة.** يستطيعون استخدام هذا الفلو، وإبطال الجلسات يشملهم — سيخرجون من لوحة Filament أيضاً. مقصود.

---

## ملاحق

- دليل الفرونت إند: [`docs/API/PASSWORD_RESET_FRONTEND_GUIDE.md`](API/PASSWORD_RESET_FRONTEND_GUIDE.md)
- فلو التفعيل (منفصل تماماً): `OtpController` + `AccountVerificationService`
- تغيير كلمة المرور وأنت داخل الحساب: `ProfileController::changePassword`
