<div dir="rtl">

# تسجيل الدخول بحساب غير مفعّل: دليل مطوّر Flutter

## لمين هالملف؟

لمطوّر تطبيق Flutter. بيشرح شو بيصير لما مستخدم **عنده حساب بس ما أكّده** (سجّل وما دخل كود الـ OTP) ورجع عمل تسجيل دخول، وكيف لازم يتصرف التطبيق خطوة بخطوة لحد ما يوصل المستخدم للـ tokens.

كل الكلام بهالملف مأخوذ من الكود الحالي:

| الملف | الدور |
|---|---|
| `routes/api.php` | تعريف الـ routes والـ throttles |
| `app/Http/Controllers/Api/AuthController.php` | `login` و `refresh` |
| `app/Http/Controllers/Api/OtpController.php` | `verify-otp` و `resend-verification-otp` |
| `app/Services/OtpService.php` | توليد الكود والتحقق منه |
| `app/Services/AccountVerificationService.php` | تحديد القناة (SMS/Email) وبناء الـ payload |
| `app/Http/Middleware/EnsureEmailIsVerifiedViaOtp.php` | حماية الـ routes المحمية من الحسابات غير المفعّلة |

> للدليل العام لكل الـ auth (register, forgot-password, ...) شوف `docs/frontend-auth-api-guide-ar.md`. هالملف مركّز بس على هالحالة.

---

## الخلاصة بسطرين

1. `POST /api/auth/login` لحساب غير مفعّل **ما بيرجع tokens**. بيرجع **403** ومعه `requires_otp_verification: true`، **وبيبعت OTP جديد تلقائياً** على القناة اللي سجّل فيها المستخدم.
2. التطبيق بينقل المستخدم لشاشة OTP، وبيبعت الكود على `POST /api/auth/verify-otp`. إذا الكود صح بيرجع **200 مع `access_token` و `refresh_token`**، ومن هون المستخدم صار داخل.

---

## متى الحساب بيعتبر "غير مفعّل"؟

من `User::requiresOtpVerification()`:

```php
return !$this->isStaffAccount() && !$this->isAccountVerified();
```

- `isAccountVerified()` بترجع `true` إذا **واحد على الأقل** من `email_verified_at` أو `phone_verified_at` مش `null`.
- حسابات الـ staff (admin, provider, manager) ما بتحتاج OTP أبداً.

يعني الزبون اللي سجّل وما أكّد ولا إيميل ولا هاتف هو اللي بيوقع بهالمسار.

---

## المسار الكامل (Sequence)

```
Flutter                                   Server
   │                                          │
   │ 1) POST /api/auth/login                  │
   │    {registration_method, email|phone,    │
   │     password}                            │
   │ ───────────────────────────────────────▶ │
   │                                          │ ├─ كلمة السر غلط؟    → 401 (ما في OTP)
   │                                          │ ├─ الحساب معطّل؟      → 403 disabled (ما في OTP)
   │                                          │ └─ غير مفعّل؟
   │                                          │      ├─ يلغي كل الأكواد القديمة
   │                                          │      ├─ يولّد كود جديد (صالح 10 دقائق)
   │                                          │      └─ يبعته بالـ queue (SMS أو Email)
   │ ◀─────────────────────────────────────── │
   │   403 {requires_otp_verification: true,  │
   │        registration_method,              │
   │        masked_destination, user, ...}    │
   │                                          │
   │ 2) شاشة OTP (عدّاد 60 ثانية لزر إعادة الإرسال)
   │                                          │
   │ 3) POST /api/auth/verify-otp             │
   │    {registration_method, email|phone,    │
   │     otp}                                 │
   │ ───────────────────────────────────────▶ │
   │                                          │ ├─ كود غلط/منتهي → 422 {"error": ...}
   │                                          │ └─ كود صح:
   │                                          │      ├─ يعلّم الحساب مفعّل
   │                                          │      └─ يصدر access + refresh tokens
   │ ◀─────────────────────────────────────── │
   │   200 {access_token, refresh_token,      │
   │        user, is_account_verified: true}  │
   │                                          │
   │ 4) خزّن الـ tokens → الشاشة الرئيسية
   │                                          │
   │ (اختياري) POST /api/auth/resend-verification-otp
   │    إذا ما وصل الكود أو انتهى             │
```

---

## الخطوة 1: تسجيل الدخول

### الطلب

```http
POST /api/auth/login
Content-Type: application/json
Accept: application/json
```

بالإيميل:

```json
{
  "registration_method": "email",
  "email": "ahmad@example.com",
  "password": "Secret123!"
}
```

بالهاتف:

```json
{
  "registration_method": "phone",
  "phone": "015223917565",
  "password": "Secret123!"
}
```

**ملاحظات على الحقول (من `LoginRequest`):**

- `registration_method` **إجباري**، وقيمته `email` أو `phone`. حالة الأحرف مش مهمة لأن السيرفر بيعملها lowercase.
- `email` إجباري إذا `registration_method = email`، و `phone` إجباري إذا `registration_method = phone`.
- الهاتف بتقدر تبعته بأي صيغة (`015...` أو `+49...` أو `0049...`)، والسيرفر بيحوّله لصيغة E.164 (`+4915223917565`) قبل البحث.

### الردود المحتملة

السيرفر بيفحص بهالترتيب، وأول شرط بيتحقق هو اللي بيحدد الرد:

| الترتيب | الحالة | Status | هل بينبعت OTP؟ |
|---|---|---|---|
| 0 | خطأ بالحقول (validation) | `422` | لا |
| 0 | تجاوز حد المحاولات | `429` | لا |
| 1 | المستخدم مش موجود أو كلمة السر غلط | `401` | لا |
| 2 | الحساب معطّل (`is_active = false`) | `403` | لا |
| 3 | **الحساب غير مفعّل** | **`403`** | **نعم، كود جديد** |
| 4 | كل شي تمام | `200` | لا |

> ⚠️ **الـ 403 إلها معنيين.** لا تعتمد على الـ status لحاله، **لازم تفحص `requires_otp_verification`**.

#### ✅ الحالة اللي بتهمنا: 403 غير مفعّل

```json
{
  "user": {
    "id": 42,
    "first_name": "Ahmad",
    "last_name": "Ali",
    "full_name": "Ahmad Ali",
    "email": "ahmad@example.com",
    "phone": null,
    "registration_method": "email",
    "address": null,
    "city": null,
    "avatar_url": null,
    "profile_image_url": null,
    "is_active": true,
    "email_verified_at": null,
    "phone_verified_at": null,
    "phone_verified": false,
    "is_account_verified": false,
    "requires_otp_verification": true,
    "created_at": "2026-09-20T10:00:00.000000Z",
    "updated_at": "2026-09-20T10:00:00.000000Z"
  },
  "message": "Your account is not verified. A new OTP has been sent to your registered contact.",
  "registration_method": "email",
  "verification_channel": "email",
  "masked_destination": "ah***@example.com",
  "email_verified": false,
  "phone_verified": false,
  "is_account_verified": false,
  "requires_otp_verification": true
}
```

| الحقل | شو بتعمل فيه |
|---|---|
| `requires_otp_verification` | **هاد اللي بيقرر.** إذا `true` روح على شاشة OTP |
| `registration_method` / `verification_channel` | القناة اللي انبعت عليها الكود (`email` أو `phone`). **استخدمها بطلب `verify-otp`** |
| `masked_destination` | اعرضها للمستخدم: "بعتنالك كود على ah***@example.com". للهاتف بتطلع متل `********7565` |
| `user.email` / `user.phone` | المعرّف اللي لازم تبعته مع `verify-otp` (شوف الملاحظة المهمة تحت) |
| `message` | **نص إنكليزي ثابت، ما بيتترجم.** لا تعرضه للمستخدم ولا تعتمد عليه لتاخد قرار |
| `otp` | **بيظهر بس إذا السيرفر شغال بـ `APP_DEBUG=true`**. مفيد للتطوير، ولا تعتمد عليه أبداً |

> 🔴 **ملاحظة مهمة: القناة بتتحدد من طريقة التسجيل، مش من طريقة الدخول.**
>
> السيرفر بيبعت الكود على القناة المخزنة بـ `user.registration_method`، مش على اللي استخدمه المستخدم بطلب الـ login هلأ.
>
> مثال: مستخدم سجّل بالإيميل وحط رقم هاتف كمان، وبعدين عمل login **بالهاتف**. الكود رح يروح على **الإيميل**. لو بعت التطبيق `verify-otp` بالهاتف رح يفشل بـ 422.
>
> **الحل:** ابنِ طلب `verify-otp` من **الرد** مش من اللي كتبه المستخدم:
> - `registration_method` = `response.registration_method`
> - المعرّف = `response.user.email` إذا القناة `email`، و `response.user.phone` إذا القناة `phone`

#### ❌ 403 حساب معطّل (لا تخلطها بالحالة الأولى)

```json
{
  "success": false,
  "message": "تم تعطيل حسابك. يرجى التواصل مع الإدارة للمساعدة."
}
```

- ما فيها `requires_otp_verification`.
- الرسالة **مترجمة** حسب `locale` بالـ body أو الـ header `Accept-Language` (`ar` أو `en` أو `de`)، فبتقدر تعرضها مباشرة.
- ما تنقل المستخدم على شاشة OTP.

#### ❌ 401 بيانات خاطئة

```json
{ "message": "Invalid credentials" }
```

نفس الرد إذا الحساب مش موجود أو كلمة السر غلط. **وما بينبعت OTP.**

#### ❌ 429 كثرة محاولات

```json
{
  "success": false,
  "message": "طلبات كثيرة جداً. يرجى المحاولة مجدداً بعد 42 ثانية.",
  "error_type": "rate_limited",
  "retry_after": 42
}
```

حدود الـ login الافتراضية: **100/دقيقة لكل IP**، و **50/دقيقة لكل حساب**، و **200/ساعة لكل حساب**. الـ header `Retry-After` كمان موجود.

---

## الخطوة 2: شاشة OTP

**شو لازم يكون عندك بالـ state:**

```dart
class OtpChallenge {
  final String registrationMethod; // 'email' | 'phone'  ← من الرد
  final String identifier;         // user.email أو user.phone ← من الرد
  final String maskedDestination;  // للعرض
  final DateTime sentAt;           // لحساب العدّاد
}
```

**قواعد الشاشة:**

- طول الكود **6 أرقام** افتراضياً (`OTP_LENGTH`). الكود ممكن يبدأ بصفر، فتعامل معه كـ **String** مش int.
- الكود صالح لـ **10 دقائق** (`OTP_TTL_MINUTES`).
- اعرض عدّاد **60 ثانية** قبل ما تفعّل زر "إعادة الإرسال".
- **⚠️ لا تستدعي `login` مرة ثانية لتعيد إرسال الكود.** كل login ناجح بكلمة السر بيولّد كود جديد **وبيلغي الكود القديم**. إذا المستخدم كبس "دخول" مرتين، الكود الأول اللي وصله بيصير ميت. لإعادة الإرسال استخدم `resend-verification-otp`.
- لما المستخدم يرجع من شاشة OTP لشاشة الدخول ويعمل login من جديد، هاد طبيعي ورح يوصله كود جديد، بس لازم يستخدم **آخر كود وصله**.

---

## الخطوة 3: التحقق من الكود

### الطلب

```http
POST /api/auth/verify-otp
Content-Type: application/json
Accept: application/json
```

```json
{
  "registration_method": "email",
  "email": "ahmad@example.com",
  "otp": "048213"
}
```

أو للهاتف:

```json
{
  "registration_method": "phone",
  "phone": "+4915223917565",
  "otp": "048213"
}
```

- `otp` لازم يكون **string**.
- بدل `registration_method` بتقدر تبعت `type` (`1` = email و `2` = sms)، بس الأفضل تبعت `registration_method`. إذا ما بعت ولا وحدة منهم بيرجع 422.

### ✅ 200 نجاح

```json
{
  "message": "Email verified successfully.",
  "user": { "...": "...", "is_account_verified": true, "requires_otp_verification": false },
  "access_token": "12|abc...",
  "access_expires_at": "2026-09-26T12:15:00+00:00",
  "refresh_token": "Xy9...",
  "refresh_expires_at": "2026-10-26T12:00:00+00:00",
  "token_type": "bearer",
  "registration_method": "email",
  "verification_channel": "email",
  "masked_destination": "ah***@example.com",
  "email_verified": true,
  "phone_verified": false,
  "is_account_verified": true,
  "requires_otp_verification": false
}
```

**بعد النجاح:**

1. خزّن `access_token` و `refresh_token` بـ `flutter_secure_storage`.
2. امسح الـ `OtpChallenge` من الـ state.
3. روح على الشاشة الرئيسية. **ما في داعي تعمل login مرة ثانية**، لأن `verify-otp` نفسه بيعطيك الـ tokens.

### ❌ 422 كود غلط أو منتهي

```json
{ "error": "Invalid or expired OTP" }
```

> ⚠️ **انتبه لشكل الرد:** المفتاح هون اسمه **`error`** مش `message`، وهاد مختلف عن أخطاء الـ validation العادية بـ Laravel اللي بترجع `{"message": ..., "errors": {...}}`. لازم الـ parser تبعك يتعامل مع الشكلين.

هالرد بيرجع بكل هالحالات:

- الكود غلط.
- الكود انتهت صلاحيته (أكتر من 10 دقائق).
- الكود انلغى لأنه انبعت كود أحدث (login جديد أو resend).
- الكود **انحرق بعد 5 محاولات غلط** (`OTP_MAX_ATTEMPTS`). بعدها حتى الكود الصح بيرجع 422.
- المعرّف أو القناة غلط (مثلاً بعتت الهاتف والكود راح على الإيميل).

**التصرف:** اعرض "الكود غير صحيح أو منتهي". وبعد عدة محاولات فاشلة اقترح على المستخدم يطلب كود جديد.

### ❌ 429 كثرة محاولات تحقق

نفس شكل الـ 429 فوق. الحدود: **10/دقيقة لكل IP**، و **5/دقيقة لكل وجهة**، و **20/ساعة لكل وجهة**. اعرض `retry_after` وعطّل زر التأكيد لهالمدة.

---

## الخطوة 4 (اختيارية): إعادة إرسال الكود

### الطلب

```http
POST /api/auth/resend-verification-otp
Content-Type: application/json
Accept: application/json
```

```json
{
  "registration_method": "phone",
  "phone": "+4915223917565"
}
```

### ✅ 200

```json
{
  "message": "OTP sent to your phone.",
  "registration_method": "phone",
  "verification_channel": "phone",
  "masked_destination": "********7565",
  "email_verified": false,
  "phone_verified": false,
  "is_account_verified": false,
  "requires_otp_verification": true
}
```

بعدها صفّر العدّاد، والكود القديم صار ملغى.

### ❌ الأخطاء

| Status | الشكل | المعنى |
|---|---|---|
| `400` | `{"message": "Account already verified."}` | الحساب مفعّل أصلاً. رجّع المستخدم على شاشة الدخول |
| `422` | `{"message": ..., "errors": {"email": [...]}}` | الإيميل أو الهاتف مش موجود بالنظام (قاعدة `exists`) |
| `429` | `{"success": false, "error_type": "rate_limited", "retry_after": N}` | **3/دقيقة لكل IP**، و **5/ساعة لكل وجهة** |

> ⚠️ **ما في cooldown من السيرفر بين كل إرسال والتاني على هالـ route.** الحماية الوحيدة هي الـ 429 فوق. فعدّاد الـ 60 ثانية لازم يكون **بالتطبيق**. وإذا المستخدم استهلك الـ 5 إرسالات بالساعة، رح يضطر يستنى.

---

## حالة إضافية: عنده tokens بس الحساب رجع غير مفعّل

عادةً المستخدم غير المفعّل **ما بياخد أي token**، لأن `register` و `login` ما بيعطوه tokens. بس في حالتين نادرتين لازم التطبيق يكون جاهز إلهن:

### أ) أي route محمي (`auth:sanctum` + `verified.customer`)

متل `/api/profile` و `/api/bookings` و `/api/appointments/*`. إذا المستخدم مش مفعّل بيرجع:

```json
{
  "message": "Your account is not verified. Please verify it using the OTP sent to your registered contact.",
  "registration_method": "email",
  "email_verified": false,
  "phone_verified": false,
  "is_account_verified": false,
  "requires_otp_verification": true
}
```

بـ status **403**. **هون ما بينبعت OTP تلقائياً**، ولا في `masked_destination`. التصرف: اعمل `resend-verification-otp` بعدين روح على شاشة OTP.

### ب) `POST /api/auth/refresh`

إذا المستخدم غير مفعّل، بيرجع **403** بنفس شكل رد الـ login (مع `masked_destination`) **وبيبعت OTP جديد**. والـ refresh token **ما بينصرف**، يعني بيضل صالح بعد ما يأكد المستخدم.

**الحل الأنظف:** interceptor واحد بـ Dio بيمسك أي 403 فيها `requires_otp_verification == true` وبيحوّل على شاشة OTP.

---

## كود Flutter مقترح

### نموذج الرد

```dart
enum LoginOutcome { success, needsOtp, disabled, invalidCredentials, rateLimited, validation }

class LoginResult {
  final LoginOutcome outcome;
  final Map<String, dynamic>? data;
  final int? retryAfter;
  LoginResult(this.outcome, {this.data, this.retryAfter});
}
```

### تسجيل الدخول

```dart
Future<LoginResult> login({
  required String method, // 'email' | 'phone'
  required String identifier,
  required String password,
}) async {
  final res = await dio.post(
    '/api/auth/login',
    data: {
      'registration_method': method,
      method: identifier, // 'email': ... أو 'phone': ...
      'password': password,
    },
    options: Options(validateStatus: (_) => true),
  );

  final body = (res.data is Map) ? Map<String, dynamic>.from(res.data) : <String, dynamic>{};

  switch (res.statusCode) {
    case 200:
      await tokenStore.save(body['access_token'], body['refresh_token']);
      return LoginResult(LoginOutcome.success, data: body);

    case 403:
      // المعنيين الاثنين بيرجعوا 403، والفرق بهالـ flag
      if (body['requires_otp_verification'] == true) {
        return LoginResult(LoginOutcome.needsOtp, data: body);
      }
      return LoginResult(LoginOutcome.disabled, data: body);

    case 401:
      return LoginResult(LoginOutcome.invalidCredentials);

    case 429:
      return LoginResult(LoginOutcome.rateLimited, retryAfter: body['retry_after'] as int?);

    default:
      return LoginResult(LoginOutcome.validation, data: body);
  }
}
```

### بناء الـ OTP challenge من الرد (مش من المدخلات)

```dart
OtpChallenge challengeFrom(Map<String, dynamic> body) {
  final method = body['registration_method'] as String; // القناة الفعلية
  final user = body['user'] as Map<String, dynamic>;
  return OtpChallenge(
    registrationMethod: method,
    identifier: method == 'phone' ? user['phone'] : user['email'],
    maskedDestination: body['masked_destination'] as String,
    sentAt: DateTime.now(),
  );
}
```

### التحقق

```dart
Future<bool> verifyOtp(OtpChallenge c, String code) async {
  final res = await dio.post(
    '/api/auth/verify-otp',
    data: {
      'registration_method': c.registrationMethod,
      c.registrationMethod: c.identifier,
      'otp': code, // String، مش int
    },
    options: Options(validateStatus: (_) => true),
  );

  if (res.statusCode == 200) {
    await tokenStore.save(res.data['access_token'], res.data['refresh_token']);
    return true;
  }
  if (res.statusCode == 422) {
    // انتبه: {"error": "..."} للكود الغلط، و {"message", "errors"} للـ validation
    throw OtpInvalidException();
  }
  if (res.statusCode == 429) {
    throw RateLimitedException(res.data['retry_after']);
  }
  throw UnexpectedException(res.statusCode);
}
```

### إعادة الإرسال

```dart
Future<void> resend(OtpChallenge c) async {
  final res = await dio.post(
    '/api/auth/resend-verification-otp',
    data: {
      'registration_method': c.registrationMethod,
      c.registrationMethod: c.identifier,
    },
    options: Options(validateStatus: (_) => true),
  );

  switch (res.statusCode) {
    case 200: return; // صفّر العدّاد
    case 400: throw AlreadyVerifiedException(); // رجّعه على الـ login
    case 429: throw RateLimitedException(res.data['retry_after']);
    default:  throw UnexpectedException(res.statusCode);
  }
}
```

---

## Checklist قبل التسليم

- [ ] الـ 403 من `login` بتنفحص بـ `requires_otp_verification`، مش بالـ status لحاله.
- [ ] طلب `verify-otp` مبني من `registration_method` و `user.email/phone` **من الرد**.
- [ ] الـ OTP بينبعت كـ **String**، وبيحافظ على الأصفار بالبداية.
- [ ] الـ parser بيقرأ `error` (من `verify-otp`) و `message`/`errors` (من الـ validation) الاثنين.
- [ ] زر إعادة الإرسال بيستدعي `resend-verification-otp` **مش `login`**.
- [ ] عدّاد 60 ثانية بالتطبيق قبل تفعيل إعادة الإرسال.
- [ ] كل 429 بتعرض `retry_after` وبتعطّل الزر لهالمدة.
- [ ] بعد `verify-otp` الناجح، الـ tokens بتتخزن مباشرة بدون login ثاني.
- [ ] Interceptor عام بيمسك 403 + `requires_otp_verification: true` من أي route.
- [ ] نص `message` بالـ 403 تبع الـ OTP ما بينعرض للمستخدم، لأنه إنكليزي ثابت.
- [ ] بعد التأكيد، `refresh` بيرجع `refresh_token` جديد كل مرة ولازم تخزّنه (rotation). الـ token القديم بيموت، وإذا انبعت مرة ثانية ممكن يعمل logout من كل الأجهزة.

---

## ملاحظات للـ backend (معروفة وما انحلت لسا)

- **ما في cooldown على مسار الـ login.** كل login بكلمة سر صحيحة لحساب غير مفعّل بيبعت OTP جديد، وما بيمر على `throttle:otp-send`. الحد الوحيد هو حد الـ login (200/ساعة لكل حساب). الحل المقترح: استخدام `OtpService::cooldownRemaining()` بـ `buildVerificationChallengeResponse()`، ورجّع نفس الـ 403 بدون كود جديد إذا في كود انبعت من أقل من 60 ثانية.
- رسالة الـ 403 تبع الـ OTP إنكليزية ثابتة وما بتنترجم، عكس رسالة الحساب المعطّل.

</div>
