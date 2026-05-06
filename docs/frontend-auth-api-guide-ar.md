<div dir="rtl">

# دليل Frontend لواجهات Authentication و OTP

## الهدف من هذا الملف

هذا الدليل موجه لفريق الـ frontend حتى يطبّق الـ API الجديدة الخاصة بـ:

- `register`
- `login`
- `verify-otp`
- `resend-verification-otp`
- `request-otp`
- `refresh`
- `logout`
- `forgot-password`
- `reset-password`

ويشرح بالتفصيل كيف تغيّر الـ flow، ما الذي يجب إرساله في كل request، ما الذي سيعود في كل response، وكيف يجب أن يتصرف التطبيق في كل حالة.

> هذا الدليل يركّز على `email/phone + OTP authentication flow`.
> توجد أيضاً routes خاصة بـ `Google auth` داخل `/api/auth`, لكنها ليست محور هذا التعديل ولذلك ذكرتها في جدول الراوترات فقط كمرجع.

---

## TL;DR

### ما الذي تغيّر؟

السلوك القديم كان تقريباً:

1. المستخدم يعمل `register`.
2. النظام ينشئ الحساب.
3. النظام يرسل OTP.
4. النظام كان يعطي `access_token` و `refresh_token` مباشرة.

السلوك الجديد أصبح:

1. المستخدم يعمل `register`.
2. النظام ينشئ الحساب ويرسل OTP.
3. **لا** يعطي أي tokens في `register`.
4. إذا كان الحساب غير مفعّل، فإن `login` يعيد `403 verification challenge` وليس tokens.
5. المستخدم يجب أن يكمل `verify-otp`.
6. فقط بعد `verify-otp` الناجح يتم إصدار `access_token` و `refresh_token`.

### القاعدة الذهبية للـ frontend

- لا تخزّن أي tokens بعد `register`.
- لا تفترض أن `login` دائماً يرجع `200`.
- إذا رجع `403` ومعه `requires_otp_verification=true`، انقل المستخدم إلى شاشة OTP.
- لا تعتمد على نص `message` وحده لاتخاذ القرار.
- اعتمد على `status code` + flags مثل:
  - `requires_otp_verification`
  - `registration_method`
  - `is_account_verified`
  - `masked_destination`

---

## Base URL و Headers

كل الأمثلة هنا تفترض أن الـ base URL هو:

```text
{{base_url}}/api
```

### Headers العامة

لجميع طلبات JSON:

```http
Accept: application/json
Content-Type: application/json
```

لجميع الـ protected routes:

```http
Authorization: Bearer YOUR_ACCESS_TOKEN
```

---

## القيم الأساسية التي يجب أن يفهمها الـ frontend

### 1. `registration_method`

هذه القيمة تحدد هل الحساب مبني على:

- `email`
- `phone`

وهي الآن جزء أساسي من معظم auth requests.

### 2. `verification_channel`

حالياً تساوي نفس قيمة `registration_method`:

- `email`
- `phone`

لكن يفضّل التعامل معها كحقل display/use-case خاص بقناة التحقق.

### 3. `masked_destination`

هذا الحقل يوضح للمستخدم أين تم إرسال الـ OTP، لكن بشكل masked.

أمثلة:

- `ah******@example.com`
- `********7890`

### 4. flags الخاصة بالتفعيل

هذه الحقول تظهر كثيراً في responses المتعلقة بالتفعيل:

<div dir="ltr">

```json
{
  "registration_method": "email",
  "verification_channel": "email",
  "masked_destination": "ah******@example.com",
  "email_verified": false,
  "phone_verified": false,
  "is_account_verified": false,
  "requires_otp_verification": true
}

```

</div>

### 5. `otp` في الـ response

في بعض البيئات التطويرية قد يظهر حقل `otp` داخل الـ response إذا كان:

<div dir="ltr">

```text
APP_DEBUG=true

```

</div>

مثال:

```json
{
  "otp": "123456"
}
```

> مهم جداً: الـ frontend يجب ألا يعتمد على هذا الحقل نهائياً في production.
> هذا الحقل فقط للتجربة المحلية و debugging.

---

## شكل `user` object في auth responses

الـ backend يعيد `user` عبر `UserResource` بالشكل التالي تقريباً:

<div dir="ltr">

```json
{
  "id": 15,
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
  "is_account_verified": false,
  "requires_otp_verification": true,
  "created_at": "2026-05-02T10:00:00.000000Z",
  "updated_at": "2026-05-02T10:00:00.000000Z"
}
```

</div>

### ملاحظات مهمة

- إذا كان `registration_method=email` فغالباً `phone` سيكون `null`.
- إذا كان `registration_method=phone` فغالباً `email` سيكون `null`.
- لا تفترض أن كلا الحقلين موجودان دائماً.

---

## خريطة الراوترات المتعلقة بـ Authentication

| Method | Route | الهدف | يحتاج Token؟ | أهم Status Codes |
| --- | --- | --- | --- | --- |
| `POST` | `/api/auth/register` | إنشاء حساب جديد بدون tokens | لا | `201`, `422` |
| `POST` | `/api/auth/login` | تسجيل الدخول أو إرجاع verification challenge | لا | `200`, `401`, `403`, `422` |
| `POST` | `/api/auth/verify-otp` | التحقق من OTP وإصدار tokens | لا | `200`, `422` |
| `POST` | `/api/auth/verify-email-otp` | alias قديم لـ email verification | لا | `200`, `422` |
| `POST` | `/api/auth/resend-verification-otp` | إعادة إرسال OTP لحساب غير مفعّل | لا | `200`, `400`, `422` |
| `POST` | `/api/auth/request-otp` | طلب OTP عام بالبريد أو الهاتف | لا | `200`, `422`, `404` |
| `POST` | `/api/auth/refresh` | إنشاء access token جديد من refresh token | لا | `200`, `401`, `403`, `422` |
| `POST` | `/api/auth/logout` | تسجيل الخروج وإلغاء التوكنات | نعم | `200`, `401` |
| `POST` | `/api/auth/forgot-password` | إرسال OTP لإعادة تعيين كلمة المرور عبر email فقط | لا | `200`, `422`, `404` |
| `POST` | `/api/auth/reset-password` | إعادة تعيين كلمة المرور عبر OTP | لا | `200`, `422`, `404` |
| `POST` | `/api/auth/google` | Google auth | لا | خارج نطاق هذا الدليل |
| `POST` | `/api/auth/google/mobile` | Google mobile auth | لا | خارج نطاق هذا الدليل |
| `GET` | `/api/auth/google/redirect` | Google web redirect | لا | خارج نطاق هذا الدليل |
| `GET` | `/api/auth/google/callback` | Google web callback | لا | خارج نطاق هذا الدليل |

---

## 1. `POST /api/auth/register`

### الهدف

إنشاء حساب جديد وإرسال OTP، لكن **بدون** login مباشر وبدون أي tokens.

### متى يستخدمه الـ frontend؟

- عند إنشاء حساب جديد عبر email.
- عند إنشاء حساب جديد عبر phone.

### Request: تسجيل عبر email

<div dir="ltr">

```http
POST /api/auth/register

```

</div>

<div dir="ltr">

```json
{
  "first_name": "Ahmad",
  "last_name": "Ali",
  "registration_method": "email",
  "email": "ahmad@example.com",
  "password": "Password@123",
  "password_confirmation": "Password@123"
}

```

</div>

### Success Response: `201 Created`

<div dir="ltr">

```json
{
  "user": {
    "id": 15,
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
    "is_account_verified": false,
    "requires_otp_verification": true,
    "created_at": "2026-05-02T10:00:00.000000Z",
    "updated_at": "2026-05-02T10:00:00.000000Z"
  },
  "message": "Registration successful. Please verify your email using the OTP sent to your email.",
  "registration_method": "email",
  "verification_channel": "email",
  "masked_destination": "ah***@example.com",
  "email_verified": false,
  "phone_verified": false,
  "is_account_verified": false,
  "requires_otp_verification": true
}

```

</div>

### Request: تسجيل عبر phone

<div dir="ltr">

```json
{
  "first_name": "Ahmad",
  "last_name": "Ali",
  "registration_method": "phone",
  "phone": "+491234567890",
  "password": "Password@123",
  "password_confirmation": "Password@123"
}

```

</div>

### Success Response: `201 Created`

<div dir="ltr">

```json
{
  "user": {
    "id": 16,
    "first_name": "Ahmad",
    "last_name": "Ali",
    "full_name": "Ahmad Ali",
    "email": null,
    "phone": "+491234567890",
    "registration_method": "phone",
    "address": null,
    "city": null,
    "avatar_url": null,
    "profile_image_url": null,
    "is_active": true,
    "email_verified_at": null,
    "phone_verified_at": null,
    "is_account_verified": false,
    "requires_otp_verification": true,
    "created_at": "2026-05-02T10:00:00.000000Z",
    "updated_at": "2026-05-02T10:00:00.000000Z"
  },
  "message": "Registration successful. Please verify your phone number using the OTP sent to your phone.",
  "registration_method": "phone",
  "verification_channel": "phone",
  "masked_destination": "********7890",
  "email_verified": false,
  "phone_verified": false,
  "is_account_verified": false,
  "requires_otp_verification": true
}

```

</div>

### Validation Errors: `422 Unprocessable Entity`

مثال إذا نسيت `registration_method` أو `password_confirmation`:

<div dir="ltr">

```json
{
  "message": "The registration method field is required. (and 1 more error)",
  "errors": {
    "registration_method": [
      "The registration method field is required."
    ],
    "password": [
      "The password field confirmation does not match."
    ]
  }
}

```

</div>

### ماذا يجب أن يفعل الـ frontend بعد `register`؟

1. لا تخزّن أي tokens.
2. انتقل مباشرة إلى شاشة `OTP Verification`.
3. مرّر إلى الشاشة:
   - `registration_method`
   - `email` أو `phone`
   - `masked_destination`
4. ابدأ countdown لإتاحة `resend-verification-otp`.

---

## 2. `POST /api/auth/login`

### الهدف

تسجيل الدخول بحساب موجود.

### السلوك الجديد

- إذا الحساب `verified` يرجع tokens بشكل طبيعي.
- إذا الحساب `unverified` يرجع `403` مع verification challenge ويرسل OTP جديد.
- إذا الـ credentials غلط يرجع `401`.
- إذا الحساب `inactive` يرجع `403`.

### Request: login عبر email

<div dir="ltr">

```json
{
  "registration_method": "email",
  "email": "ahmad@example.com",
  "password": "Password@123"
}

```

</div>

### Request: login عبر phone

<div dir="ltr">

```json
{
  "registration_method": "phone",
  "phone": "+491234567890",
  "password": "Password@123"
}

```

</div>

### Success Response للحساب المفعّل: `200 OK`

<div dir="ltr">

```json
{
  "user": {
    "id": 15,
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
    "email_verified_at": "2026-05-02T10:05:00.000000Z",
    "phone_verified_at": null,
    "is_account_verified": true,
    "requires_otp_verification": false,
    "created_at": "2026-05-02T10:00:00.000000Z",
    "updated_at": "2026-05-02T10:05:00.000000Z"
  },
  "access_token": "1|long_access_token_here",
  "access_expires_at": "2026-05-02T12:00:00.000000Z",
  "refresh_token": "plain_refresh_token_here",
  "refresh_expires_at": "2026-05-09T10:05:00.000000Z",
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

</div>

### Response للحساب غير المفعّل: `403 Forbidden`

<div dir="ltr">

```json
{
  "user": {
    "id": 15,
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
    "is_account_verified": false,
    "requires_otp_verification": true,
    "created_at": "2026-05-02T10:00:00.000000Z",
    "updated_at": "2026-05-02T10:00:00.000000Z"
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

</div>

### Response للـ credentials الخاطئة: `401 Unauthorized`

<div dir="ltr">

```json
{
  "message": "Invalid credentials"
}

```

</div>

### Response للحساب غير النشط: `403 Forbidden`

<div dir="ltr">

```json
{
  "message": "This account is inactive."
}

```

</div>

### ماذا يجب أن يفعل الـ frontend بعد `login`؟

#### إذا كانت النتيجة `200`

1. خزّن `access_token`.
2. خزّن `refresh_token`.
3. خزّن `user` إن كنت تحتاجه في session store.
4. انتقل إلى الشاشة الرئيسية.

#### إذا كانت النتيجة `403` ومعها `requires_otp_verification=true`

1. لا تعتبر العملية login ناجحة بعد.
2. لا تخزّن أي tokens.
3. انتقل إلى شاشة OTP.
4. استخدم `registration_method` و `masked_destination` لعرض الرسالة للمستخدم.

#### إذا كانت النتيجة `401`

1. اعرض رسالة `Invalid credentials`.
2. ابقَ في شاشة login.

---

## 3. `POST /api/auth/verify-otp`

### الهدف

هذا هو الـ endpoint الأساسي الذي يُكمل activation ويصدر tokens.

### متى يستخدم؟

- بعد `register`
- بعد `login` إذا رجع verification challenge
- بعد `resend-verification-otp`

### Request: verify email OTP

<div dir="ltr">

```json
{
  "registration_method": "email",
  "email": "ahmad@example.com",
  "otp": "123456"
}

```

</div>

### Request: verify phone OTP

<div dir="ltr">

```json
{
  "registration_method": "phone",
  "phone": "+491234567890",
  "otp": "123456"
}

```

</div>

### Success Response: `200 OK`

<div dir="ltr">

```json
{
  "message": "Email verified successfully.",
  "user": {
    "id": 15,
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
    "email_verified_at": "2026-05-02T10:05:00.000000Z",
    "phone_verified_at": null,
    "is_account_verified": true,
    "requires_otp_verification": false,
    "created_at": "2026-05-02T10:00:00.000000Z",
    "updated_at": "2026-05-02T10:05:00.000000Z"
  },
  "access_token": "1|long_access_token_here",
  "access_expires_at": "2026-05-02T12:00:00.000000Z",
  "refresh_token": "plain_refresh_token_here",
  "refresh_expires_at": "2026-05-09T10:05:00.000000Z",
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

</div>

### Success Response: `200 OK` عند phone verification

<div dir="ltr">

```json
{
  "message": "Phone number verified successfully.",
  "user": {
    "id": 16,
    "first_name": "Ahmad",
    "last_name": "Ali",
    "full_name": "Ahmad Ali",
    "email": null,
    "phone": "+491234567890",
    "registration_method": "phone",
    "address": null,
    "city": null,
    "avatar_url": null,
    "profile_image_url": null,
    "is_active": true,
    "email_verified_at": null,
    "phone_verified_at": "2026-05-02T10:05:00.000000Z",
    "is_account_verified": true,
    "requires_otp_verification": false,
    "created_at": "2026-05-02T10:00:00.000000Z",
    "updated_at": "2026-05-02T10:05:00.000000Z"
  },
  "access_token": "1|long_access_token_here",
  "access_expires_at": "2026-05-02T12:00:00.000000Z",
  "refresh_token": "plain_refresh_token_here",
  "refresh_expires_at": "2026-05-09T10:05:00.000000Z",
  "token_type": "bearer",
  "registration_method": "phone",
  "verification_channel": "phone",
  "masked_destination": "********7890",
  "email_verified": false,
  "phone_verified": true,
  "is_account_verified": true,
  "requires_otp_verification": false
}

```

</div>

### Invalid OTP: `422 Unprocessable Entity`

<div dir="ltr">

```json
{
  "error": "Invalid or expired OTP"
}

```

</div>

### ماذا يجب أن يفعل الـ frontend بعد `verify-otp`؟

1. خزّن `access_token` و `refresh_token`.
2. خزّن `user`.
3. أغلق شاشة OTP.
4. انتقل إلى الشاشة الرئيسية أو onboarding التالية.

---

## 4. `POST /api/auth/verify-email-otp`

### الهدف

هذا route موجود للتوافق الخلفي `backward compatibility` مع integrations أقدم.

### التوصية للـ frontend الجديد

استخدم دائماً:

```text
POST /api/auth/verify-otp
```

مع:

<div dir="ltr">

```json
{
  "registration_method": "email"
}

```

</div>

### متى يمكن استخدام `verify-email-otp`؟

إذا كان لديكم كود frontend قديم مبني عليه ولا تريدون تغييره حالياً.

### Request Example

<div dir="ltr">

```json
{
  "email": "ahmad@example.com",
  "otp": "123456"
}

```

</div>

### Success Response

هو نفس response الخاص بـ `verify-otp` عند email verification.

---

## 5. `POST /api/auth/resend-verification-otp`

### الهدف

إعادة إرسال OTP لحساب موجود لكنه غير مفعّل.

### متى يستخدم؟

- من شاشة OTP إذا انتهت المهلة أو طلب المستخدم إعادة الإرسال.

### Request: email account

<div dir="ltr">

```json
{
  "registration_method": "email",
  "email": "ahmad@example.com"
}

```

</div>

### Request: phone account

<div dir="ltr">

```json
{
  "registration_method": "phone",
  "phone": "+491234567890"
}

```

</div>

### Success Response: `200 OK`

<div dir="ltr">

```json
{
  "message": "OTP sent to your email.",
  "registration_method": "email",
  "verification_channel": "email",
  "masked_destination": "ah***@example.com",
  "email_verified": false,
  "phone_verified": false,
  "is_account_verified": false,
  "requires_otp_verification": true
}

```

</div>

وعند `phone` يصبح `message` غالباً:

<div dir="ltr">

```json
{
  "message": "OTP sent to your phone."
}

```

</div>

### إذا كان الحساب مفعّلاً أصلاً: `400 Bad Request`

<div dir="ltr">

```json
{
  "message": "Account already verified."
}

```

</div>

### Validation/Existence Errors

- إذا لم ترسل `email` أو `phone` المناسبين سيعود `422`.
- إذا لم يوجد المستخدم قد تعود `422` أو `404` حسب نقطة الفشل.

### ماذا يجب أن يفعل الـ frontend؟

1. اعرض toast مثل `OTP resent successfully`.
2. أعد تشغيل countdown.
3. لا تغير screen flow.
4. لا تتوقع tokens من هذا endpoint.

---

## 6. `POST /api/auth/request-otp`

### الهدف

طلب OTP بشكل عام بواسطة `email` أو `phone`.

### متى يستخدم؟

- لإرسال OTP بدون login.
- لإعادة طلب OTP في بعض flows العامة.
- يمكن استخدامه أيضاً ضمن password reset flow خاصة للهاتف.

### الفرق بينه وبين `resend-verification-otp`

`request-otp`:

- endpoint عام
- لا يفحص أن الحساب غير verified قبل الإرسال

`resend-verification-otp`:

- endpoint مخصص لـ account activation
- يعيد `400` إذا كان الحساب verified بالفعل

### Request: email

<div dir="ltr">

```json
{
  "registration_method": "email",
  "email": "ahmad@example.com"
}

```

</div>

### Request: phone

<div dir="ltr">

```json
{
  "registration_method": "phone",
  "phone": "+491234567890"
}

```

</div>

### Success Response: `200 OK`

<div dir="ltr">

```json
{
  "message": "OTP sent successfully.",
  "registration_method": "phone",
  "verification_channel": "phone",
  "masked_destination": "********7890",
  "email_verified": false,
  "phone_verified": false,
  "is_account_verified": false,
  "requires_otp_verification": true
}

```

</div>

### مهم

- هذا endpoint **لا** يفعّل الحساب.
- هذا endpoint **لا** يعيد tokens.
- هذا endpoint فقط يرسل OTP.

---

## 7. `POST /api/auth/refresh`

### الهدف

الحصول على `access_token` جديد باستخدام `refresh_token`.

### Request

<div dir="ltr">

```json
{
  "refresh_token": "plain_refresh_token_here"
}

```

</div>

### Success Response: `200 OK`

<div dir="ltr">

```json
{
  "access_token": "1|new_access_token_here",
  "access_expires_at": "2026-05-02T12:30:00.000000Z",
  "requires_otp_verification": false
}

```

</div>

### إذا كان الحساب غير مفعّل: `403 Forbidden`

<div dir="ltr">

```json
{
  "user": {
    "id": 15,
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
    "is_account_verified": false,
    "requires_otp_verification": true,
    "created_at": "2026-05-02T10:00:00.000000Z",
    "updated_at": "2026-05-02T10:00:00.000000Z"
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

</div>

### إذا كان refresh token غير صالح: `401 Unauthorized`

<div dir="ltr">

```json
{
  "message": "Invalid or expired refresh token"
}

```

</div>

### ملاحظات مهمة للـ frontend

- `refresh` يعيد `access_token` جديد فقط.
- لا يعيد `refresh_token` جديد.
- احتفظ بالـ `refresh_token` الحالي حتى تنتهي صلاحيته أو يحصل logout.
- إذا رجع `403` ومعه `requires_otp_verification=true`، انقل المستخدم إلى شاشة OTP.

---

## 8. `POST /api/auth/logout`

### الهدف

تسجيل خروج المستخدم.

### Request

```http
POST /api/auth/logout
Authorization: Bearer YOUR_ACCESS_TOKEN
```

### Success Response: `200 OK`

<div dir="ltr">

```json
{
  "message": "Logged out"
}

```

</div>

### ملاحظة مهمة

الـ backend يلغي:

- access tokens
- refresh tokens

لذلك بعد logout يجب على الـ frontend حذف أي session محلية بالكامل.

---

## 9. Password Reset Flow

يوجد هنا مساران مهمان:

1. `forgot-password`
2. `reset-password`

### ملاحظة مهمة جداً

`forgot-password` الحالي هو endpoint قديم نسبيّاً ويدعم `email` فقط.

أما `reset-password` الحالي فيدعم:

- `email`
- `phone`

لكن يجب إرسال `registration_method` أو `type` حتى يعرف الـ backend القناة.

---

## 9.1 `POST /api/auth/forgot-password`

### الهدف

إرسال OTP لإعادة تعيين كلمة المرور عبر email فقط.

### Request

<div dir="ltr">

```json
{
  "email": "ahmad@example.com"
}

```

</div>

### Success Response: `200 OK`

<div dir="ltr">

```json
{
  "message": "OTP sent to email"
}

```

</div>

### ملاحظات

- هذا endpoint لا يعيد verification payload.
- هذا endpoint لا يعيد tokens.
- في local/dev قد يظهر `otp` إذا كان `APP_DEBUG=true`.

---

## 9.2 `POST /api/auth/reset-password`

### الهدف

إعادة تعيين كلمة المرور بعد إدخال OTP صحيح.

### Request: reset via email

<div dir="ltr">

```json
{
  "registration_method": "email",
  "email": "ahmad@example.com",
  "otp": "123456",
  "password": "NewPassword@123",
  "password_confirmation": "NewPassword@123"
}

```

</div>

### Request: reset via phone

<div dir="ltr">

```json
{
  "registration_method": "phone",
  "phone": "+491234567890",
  "otp": "123456",
  "password": "NewPassword@123",
  "password_confirmation": "NewPassword@123"
}

```

</div>

### Success Response: `200 OK`

<div dir="ltr">

```json
{
  "message": "Password reset successful"
}

```

</div>

### Invalid OTP: `422 Unprocessable Entity`

<div dir="ltr">

```json
{
  "error": "Invalid or expired OTP"
}

```

</div>

### كيف يبني الـ frontend flow الصحيح؟

#### Password reset via email

1. اطلب `POST /api/auth/forgot-password` مع `email`.
2. افتح شاشة إدخال OTP + password الجديدة.
3. أرسل `POST /api/auth/reset-password` مع:
   - `registration_method=email`
   - `email`
   - `otp`
   - `password`
   - `password_confirmation`

#### Password reset via phone

لأنه لا يوجد endpoint مخصص باسم `forgot-password-phone` حالياً، استخدم:

1. `POST /api/auth/request-otp`
2. ثم `POST /api/auth/reset-password`

مع:

```json
{
  "registration_method": "phone",
  "phone": "+491234567890"
}
```

ثم:

```json
{
  "registration_method": "phone",
  "phone": "+491234567890",
  "otp": "123456",
  "password": "NewPassword@123",
  "password_confirmation": "NewPassword@123"
}
```

---

## 10. سلوك الـ protected routes بعد هذا التعديل

بعض الـ routes المحمية للمستخدم customer أصبحت تتطلب:

- `auth:sanctum`
- وأن يكون الحساب `verified`

إذا استُخدم access token لحساب customer غير مفعّل، سيعود `403` مثل هذا:

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

### ماذا يعني هذا للـ frontend؟

- إذا ضربت أي protected route ورجعت هذه الـ shape، لا تعاملها كخطأ عام فقط.
- حوّل المستخدم إلى شاشة OTP verification.
- هذا مهم خصوصاً في حالات:
  - access token قديم
  - session قديمة
  - refresh/login challenge

---

## 11. Decision Matrix للـ frontend

| الحالة | ماذا تفعل؟ |
| --- | --- |
| `register -> 201` ومع `requires_otp_verification=true` | افتح شاشة OTP ولا تخزّن tokens |
| `login -> 200` | خزّن `access_token` و `refresh_token` وانتقل للـ app |
| `login -> 403` ومع `requires_otp_verification=true` | افتح شاشة OTP ولا تخزّن tokens |
| `verify-otp -> 200` | خزّن tokens وأغلق شاشة OTP |
| `refresh -> 200` | استبدل `access_token` فقط |
| `refresh -> 403` ومع `requires_otp_verification=true` | افتح شاشة OTP |
| `protected route -> 403` ومع `requires_otp_verification=true` | افتح شاشة OTP |
| `resend-verification-otp -> 200` | أعد تشغيل countdown فقط |
| `verify-otp -> 422` | اعرض `Invalid or expired OTP` |

---

## 12. Recommended frontend state

عند الانتقال إلى شاشة OTP، يفضّل أن تخزّن state مثل التالي:

```json
{
  "flow": "register",
  "registration_method": "phone",
  "email": null,
  "phone": "+491234567890",
  "masked_destination": "********7890"
}
```

أو:

```json
{
  "flow": "login",
  "registration_method": "email",
  "email": "ahmad@example.com",
  "phone": null,
  "masked_destination": "ah***@example.com"
}
```

### الحقول المقترحة داخل OTP screen state

- `flow`: `register | login | refresh | protected_route | password_reset`
- `registration_method`
- `email`
- `phone`
- `masked_destination`

هذا يسهل عليك:

- تنفيذ `verify-otp`
- تنفيذ `resend-verification-otp`
- عرض النص الصحيح للمستخدم

---

## 13. Validation و Error Handling

### 1. أخطاء Laravel validation العادية

هذه عادة تأتي بهذا الشكل:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "registration_method": [
      "The selected registration method is invalid."
    ],
    "phone": [
      "The phone field is required."
    ]
  }
}
```

### 2. أخطاء OTP invalid/expired

هذه تأتي بشكل مختلف قليلاً:

```json
{
  "error": "Invalid or expired OTP"
}
```

### 3. أخطاء credentials أو refresh token

```json
{
  "message": "Invalid credentials"
}
```

أو:

```json
{
  "message": "Invalid or expired refresh token"
}
```

### توصية مهمة

لا تبنوا منطق الـ frontend على مقارنة نص `message` حرفياً.
ابنوا المنطق على:

- `status code`
- وجود `requires_otp_verification`
- وجود `access_token`
- وجود `errors`
- وجود `error`

---

## 14. أفضل ممارسة للـ frontend

### افعلوا هذا

1. وحّدوا شاشة OTP لتخدم:
   - بعد `register`
   - بعد `login challenge`
   - بعد `refresh challenge`
   - بعد `protected route challenge`

2. اجعلوا الـ frontend يرسل فقط identifier المناسب:
   - `email` إذا `registration_method=email`
   - `phone` إذا `registration_method=phone`

3. حافظوا على phone format موحّد، ويفضل `E.164` مثل:

```text
+491234567890
```

4. خزّنوا `refresh_token` فقط عندما يأتي من:
   - `login` الناجح
   - `verify-otp` الناجح

5. تعاملوا مع `403 + requires_otp_verification=true` كحالة flow معروفة وليس كـ generic error.

### لا تفعلوا هذا

1. لا تتوقعوا tokens من `register`.
2. لا تعتمدوا على `otp` داخل الـ response.
3. لا تفترضوا أن `email` موجود دائماً لكل users.
4. لا تفترضوا أن `refresh` يعيد `refresh_token` جديد.
5. لا تربطوا منطق التطبيق بنصوص الرسائل الإنجليزية نفسها.

---

## 15. مثال عملي كامل: Email Registration Flow

### Step 1: Register

```json
{
  "first_name": "Ahmad",
  "last_name": "Ali",
  "registration_method": "email",
  "email": "ahmad@example.com",
  "password": "Password@123",
  "password_confirmation": "Password@123"
}
```

النتيجة:

- `201`
- بدون tokens
- مع `requires_otp_verification=true`

### Step 2: افتح شاشة OTP

اعرض مثلاً:

```text
تم إرسال رمز التحقق إلى ah***@example.com
```

### Step 3: Verify OTP

```json
{
  "registration_method": "email",
  "email": "ahmad@example.com",
  "otp": "123456"
}
```

النتيجة:

- `200`
- يوجد `access_token`
- يوجد `refresh_token`
- `requires_otp_verification=false`

### Step 4: ادخل التطبيق

- خزّن tokens
- خزّن user data
- انتقل إلى home/dashboard

---

## 16. مثال عملي كامل: Unverified Login Flow

### Step 1: Login

```json
{
  "registration_method": "phone",
  "phone": "+491234567890",
  "password": "Password@123"
}
```

### Step 2: إذا رجع `403`

مثال:

```json
{
  "message": "Your account is not verified. A new OTP has been sent to your registered contact.",
  "registration_method": "phone",
  "verification_channel": "phone",
  "masked_destination": "********7890",
  "is_account_verified": false,
  "requires_otp_verification": true
}
```

### Step 3: افتح شاشة OTP مباشرة

### Step 4: Verify OTP

```json
{
  "registration_method": "phone",
  "phone": "+491234567890",
  "otp": "123456"
}
```

### Step 5: خزّن tokens

بعد `200`, خزّن:

- `access_token`
- `refresh_token`
- `token_type`
- `user`

---

## 17. ما الذي يحتاجه فريق الـ frontend لتعديل التطبيق؟

### المطلوب تحديثه في التطبيق

1. شاشة التسجيل ترسل `registration_method`.
2. شاشة login تدعم طريقتين:
   - email + password
   - phone + password
3. شاشة OTP موحدة وتدعم:
   - verify
   - resend
4. session manager يتعامل مع:
   - `login -> 200`
   - `login -> 403 challenge`
   - `refresh -> 200`
   - `refresh -> 403 challenge`
5. global API error handler يتعامل مع:
   - `403 + requires_otp_verification=true`
   - ويحوّل المستخدم إلى شاشة OTP إذا لزم

### أقل تعديل منطقي مقترح في frontend architecture

اجعلوا عندكم auth states مثل:

```text
guest
pending_verification
authenticated
```

بحيث:

- بعد `register`: الحالة تصبح `pending_verification`
- بعد `login 403 challenge`: الحالة تصبح `pending_verification`
- بعد `verify-otp 200`: الحالة تصبح `authenticated`
- بعد `logout`: الحالة تصبح `guest`

---

## 18. الخلاصة النهائية

الـ API الجديدة لم تعد تعتبر `register` نهاية عملية الدخول، بل بداية flow التفعيل.

الترتيب الصحيح أصبح:

### للحساب الجديد

```text
register -> OTP screen -> verify-otp -> store tokens -> enter app
```

### للحساب الموجود وغير المفعّل

```text
login -> 403 verification challenge -> OTP screen -> verify-otp -> store tokens -> enter app
```

### للحساب المفعّل

```text
login -> 200 -> store tokens -> enter app
```

إذا التزم الـ frontend بهذا الـ flow فلن يحتاج لأي افتراضات إضافية، وسيكون متوافقاً بالكامل مع الـ backend الحالي.
