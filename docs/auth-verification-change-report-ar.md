<div dir="rtl">

# تقرير تفصيلي جداً: تفعيل الحساب الإجباري عبر OTP

## ملاحظة مهمة قبل القراءة

هذا التقرير يوثّق **التعديلات الخاصة بهذه المهمة فقط**.

يوجد في المستودع تغييرات أخرى غير مرتبطة بهذه المهمة وكانت موجودة في مساحة العمل أثناء التنفيذ، مثل تغييرات في `StaffDashboard` و`PageController` و`PageRenderService` و`routes/web.php`. هذه **ليست جزءاً من هذا التنفيذ** ولم ألمسها ضمن هذا العمل.

---

## الفكرة العامة من التعديل

المشكلة الأصلية كانت أن النظام كان يسمح بما يلي:

1. المستخدم يسجّل عبر `register` ويتم إنشاء الحساب.
2. النظام يرسل OTP للتحقق.
3. **لكن** في نفس اللحظة كان يولّد `access_token` و`refresh_token` ويعتبر المستخدم logged in فعلياً حتى قبل التحقق.
4. كذلك `login` لم يكن يفحص إن كان الحساب verified أم لا.

هذا السلوك كان يسمح عملياً بإنشاء حسابات بـ fake email أو unverified phone ثم استخدام النظام مباشرة.

التعديل الجديد نقل النظام إلى نموذج `verification-first auth flow`:

1. `register` ينشئ الحساب فقط.
2. يتم إرسال OTP حسب `registration_method`.
3. لا يتم إصدار أي tokens في `register`.
4. `login` إذا كان الحساب غير مفعّل يعيد `verification challenge` بدون tokens ويرسل OTP جديد تلقائياً.
5. `verify-otp` أو `verify-email-otp` أصبحا نقطة إكمال التفعيل وإصدار tokens.
6. المسارات المحمية customer-side أصبحت ترفض أي customer غير مفعّل حتى لو كان لديه token قديم.

---

## كيف كان النظام يعمل قبل التعديل

### قبل التعديل في `register`

- كان `register` في `app/Http/Controllers/Api/AuthController.php` ينشئ المستخدم ثم يولّد OTP.
- بعدها مباشرة كان ينشئ `access_token` و`refresh_token`.
- النتيجة: المستخدم غير المفعّل يصبح authenticated مباشرة.

### قبل التعديل في `login`

- كان `login` يعتمد على `email` فقط.
- لم يكن هناك `registration_method`.
- لم يكن هناك أي فحص لـ `email_verified_at` أو `phone_verified_at` أو أي account activation state.

### قبل التعديل في `OTP verification`

- `verifyEmailViaOtp` فقط هو الذي كان يحدّث `email_verified_at`.
- `verifyOtp` العام كان فقط يتحقق من OTP ثم يعيد رسالة نجاح بدون تفعيل فعلي للحساب وبدون tokens.
- دعم SMS كان شكلياً في `OtpService` بدون إرسال حقيقي.

### قبل التعديل في route protection

- كان هناك استخدام لـ `verified` على بعض customer routes.
- لكن `User` لا يطبّق `MustVerifyEmail`، لذلك الحماية لم تكن تعبّر بدقة عن activation logic المطلوبة.
- كذلك customer قد يملك token قديم من قبل التفعيل ويستخدم بعض المسارات.

---

## كيف يعمل النظام بعد التعديل

### بعد التعديل في `register`

- العميل يرسل `registration_method` بقيمة `email` أو `phone`.
- النظام ينشئ المستخدم unverified.
- النظام يعيّن له role `customer` مباشرة.
- النظام يرسل OTP حسب القناة المطلوبة.
- النظام يعيد:
  - `user`
  - `registration_method`
  - `verification_channel`
  - `masked_destination`
  - `requires_otp_verification=true`
  - `otp` فقط في `debug`
- لا يعيد أي `access_token` أو `refresh_token`.

### بعد التعديل في `login`

- العميل يستطيع تسجيل الدخول بواسطة `email` أو `phone` حسب `registration_method`.
- إذا كانت credentials خاطئة: `401`.
- إذا كان الحساب inactive: `403`.
- إذا كان الحساب صحيح لكن غير مفعّل:
  - يتم إرسال OTP جديد تلقائياً
  - لا يتم إصدار tokens
  - يتم إرجاع `403` مع `requires_otp_verification=true`
- إذا كان الحساب مفعّلاً: يتم إصدار tokens بشكل طبيعي.

### بعد التعديل في `verify-otp`

- هذا endpoint أصبح هو نقطة إكمال login فعلياً.
- عند نجاح OTP:
  - إذا القناة `email`: يتم تحديث `email_verified_at` و `email_verified_via_otp_at`
  - إذا القناة `phone`: يتم تحديث `phone_verified_at`
  - يتم إصدار `access_token` و`refresh_token`
- بذلك activation + authentication أصبحا متسلسلين وصحيحين.

### بعد التعديل في `refresh`

- `refresh_token` لم يعد كافياً وحده.
- إذا كان الحساب غير مفعّل، فـ `refresh` نفسه يعيد `verification challenge` ولا يعيد access token جديد.

### بعد التعديل في customer route protection

- أي customer غير مفعّل لن يستطيع دخول customer protected routes مثل:
  - `profile`
  - `appointments`
  - `bookings`
  - `print routes`
- حتى لو كان يملك token قديماً من flow قديم، middleware سيمنعه.

---

## التعديلات على مستوى قاعدة البيانات `Database`

### 1. إضافة حقول تفعيل جديدة على `users`

**الملف:** `database/migrations/2026_05_02_120000_add_account_verification_fields_to_users_table.php`

**المواضع المهمة:**

- line 13: إضافة `phone_verified_at`
- line 14: إضافة `registration_method`
- line 19-21: backfill لقيمة `registration_method` للحسابات القديمة
- line 25-28: grandfathering للحسابات القديمة عبر تعيين `email_verified_at` إذا لم يكن هناك أي verification timestamps
- line 32: إضافة `unique` على `phone`

**ماذا أضفنا؟**

- `phone_verified_at`
- `registration_method`
- database-level unique constraint على `phone`

**ماذا عدلنا؟**

- جعل `users.email` قابلاً لأن يكون `nullable`

**ماذا حذفنا؟**

- لم نحذف أعمدة من schema الحالية، لكن في `down()` أزلنا الحقول الجديدة لإعادة rollback.

**الأثر على النظام:**

- أصبح التسجيل بالهاتف فقط ممكناً فعلياً.
- أصبح لدينا semantic separation صحيحة بين email verification و phone verification.
- أصبح من الممكن معرفة channel الأصلية التي يُرسل عليها OTP لاحقاً.

**ملاحظة مهمة جداً:**

- إذا كانت قاعدة البيانات الحالية تحتوي duplicate values في `phone`، فإضافة unique constraint ستفشل حتى يتم تنظيف البيانات.

### 2. إصلاح schema جدول `otps` لدعم phone-only OTP

**الملف:** `database/migrations/2026_05_02_121000_make_otp_email_nullable.php`

**الموضع المهم:**

- line 12: جعل `otps.email` قابلاً لأن يكون `nullable`

**لماذا كان هذا ضرورياً؟**

- لأن `SMS OTP` يحتاج أحياناً إلى حفظ record فيه `phone` فقط بدون `email`.
- بدون هذا التعديل كان phone registration و phone verification يفشلان على مستوى `SQL integrity constraint`.

---

## التعديلات على نموذج المستخدم `User model`

### 3. توسيع `User` ليفهم account verification state

**الملف:** `app/Models/User.php`

**المواضع المهمة:**

- line 49-52: إضافة `registration_method` و `phone_verified_at` إلى `fillable`
- line 60-63: إضافة `is_account_verified` و `requires_otp_verification` إلى `appends`
- line 78-79: casts لـ `phone_verified_at` و `registration_method`
- line 83: `getIsAccountVerifiedAttribute()`
- line 88: `getRequiresOtpVerificationAttribute()`
- line 93: `isAccountVerified()`
- line 98 تقريباً: `isStaffAccount()`
- line 103: `requiresOtpVerification()`
- line 108 تقريباً: `getRegistrationMethodEnum()`
- line 124 تقريباً: `getVerificationOtpType()`
- line 209: تعديل `updateProfileImage()`

**ماذا أضفنا؟**

- account verification helpers داخل الـ model نفسه.
- staff bypass logic للحسابات `admin/provider/manager`.
- طريقة موحّدة لحسم هل المستخدم يحتاج OTP gate أم لا.

**ماذا عدلنا؟**

- `updateProfileImage()` عُدلت تقنياً أيضاً حتى لا يتعارض حذف الصورة القديمة مع static analysis، مع تحسين naming للملف وتحديث relation cache.

**الأثر على النظام:**

- أصبح أي جزء في النظام يمكنه سؤال المستخدم مباشرة:
  - هل الحساب verified؟
  - هل يحتاج OTP gate؟
  - ما هي قناة التحقق الأصلية؟

---

## طبقة التحقق من الطلبات `Request Validation`

### 4. تحديث `RegisterRequest`

**الملف:** `app/Http/Requests/RegisterRequest.php`

**المواضع المهمة:**

- line 30: فرض `registration_method`
- line 32: `email` مطلوب إذا كانت الطريقة `email`
- line 39: `phone` مطلوب إذا كانت الطريقة `phone`
- line 56: `prepareForValidation()` لتوحيد lowercase

**ماذا عدلنا؟**

- validation لم تعد تفترض أن `email` مطلوب دائماً.
- أصبحت تعتمد على `RegistrationMethod` enum.

### 5. تحديث `LoginRequest`

**الملف:** `app/Http/Requests/LoginRequest.php`

**المواضع المهمة:**

- line 27: فرض `registration_method`
- line 29: `email` مطلوب conditionally
- line 34: `phone` مطلوب conditionally
- line 42: `prepareForValidation()`

**الأثر على النظام:**

- أصبح login يدعم email login و phone login بنفس العقدة الرسمية.

---

## خدمة verification الجديدة

### 6. إضافة `AccountVerificationService`

**الملف:** `app/Services/AccountVerificationService.php`

**المواضع المهمة:**

- line 10: تعريف الكلاس
- line 12: `resolveRegistrationMethod()`
- line 29 تقريباً: `resolveOtpType()`
- line 36 تقريباً: `resolveVerificationTarget()`
- line 48 تقريباً: `maskTarget()`
- line 64: `buildVerificationPayload()`
- line 81: `markVerified()`

**ماذا أضفنا؟**

- خدمة مركزية توحّد منطق:
  - اختيار channel
  - بناء masked destination
  - بناء verification metadata في responses
  - تحديث account verification timestamps

**لماذا هذا مهم معمارياً؟**

- بدلاً من تكرار منطق التفعيل في `AuthController` و `OtpController`، أصبح هناك service واحدة تضمن consistency.

---

## التعديلات على OTP delivery

### 7. توسيع `OtpService`

**الملف:** `app/Services/OtpService.php`

**المواضع المهمة:**

- line 19: `generate()`
- line 46: استدعاء `sendSmsOtp()`
- line 53: `validate()`
- line 80: `invalidateUnusedOtps()`
- line 93: `sendSmsOtp()`

**ماذا أضفنا؟**

- دعم إرسال SMS فعلياً عبر Vonage HTTP API.

**ماذا عدلنا؟**

- `validate()` أصبح يستخدم `query()->where(...)` لتوافق أفضل مع analyzer.
- عند `generate()` إذا كان النوع `SMS_OTP` يتم إرسال الرسالة فعلياً أو logging fallback حسب config.

**ماذا حذفنا؟**

- حذفنا الفراغات placeholder القديمة الخاصة بـ SMS branch غير المنفذة.

**السلوك بعد التعديل:**

- Email OTP ما زال يخرج عبر mail.
- Phone OTP أصبح يمر عبر `sendSmsOtp()`.
- إذا كانت إعدادات Vonage ناقصة أو `VONAGE_SMS_ENABLED=false`، فلن يضرب API الخارجي، بل سيكتب log ويكمل flow. هذا مقصود لحماية local/testing.

**قرار تقني مهم:**

- لم أضف package جديداً في `composer.json`.
- تم تنفيذ التكامل مع Vonage عبر Laravel `Http client` مباشرة.
- السبب: تقليل dependency footprint، تسريع الدمج، والإبقاء على التكامل بسيطاً ومضبوطاً بدون تغيير package graph.

---

## التعديلات على `AuthController`

### 8. إعادة بناء `register/login/refresh`

**الملف:** `app/Http/Controllers/Api/AuthController.php`

**المواضع المهمة:**

- line 28: `register()`
- line 62: `login()`
- line 90: `refresh()`
- line 163: `buildAuthenticatedResponse()`
- line 178: `buildVerificationChallengeResponse()`

### ماذا عدلنا في `register()`

- أضفنا قراءة `registration_method`.
- جعلنا `email` و `phone` conditional.
- خزّنا `registration_method` على المستخدم.
- أنشأنا role `customer` إذا لم يكن موجوداً ثم ربطناه بالمستخدم.
- أرسلنا OTP حسب channel.
- **أزلنا** إصدار tokens من register.

### ماذا عدلنا في `login()`

- أصبح login يحل المستخدم عبر `email` أو `phone`.
- أضفنا فحص `is_active`.
- أضفنا branch للحساب غير المفعّل يعيد `403 verification challenge`.
- الحساب verified فقط هو الذي يحصل على tokens.

### ماذا عدلنا في `refresh()`

- أضفنا نفس verification gate على refresh flow.
- إذا الحساب غير مفعّل، يعود challenge بدل access token جديد.

### ماذا أضفنا؟

- helper داخلي `buildAuthenticatedResponse()` لتوحيد response الناجح.
- helper داخلي `buildVerificationChallengeResponse()` لتوحيد response الخاص بالتفعيل.

### ماذا حذفنا؟

- حذفنا implicit auto-login من التسجيل.
- حذفنا الافتراض أن مجرد صحة password تعني السماح بالوصول.

---

## التعديلات على `OtpController`

### 9. تحويل OTP endpoints إلى activation endpoints حقيقية

**الملف:** `app/Http/Controllers/Api/OtpController.php`

**المواضع المهمة:**

- line 27: `requestOtp()`
- line 58: `verifyOtp()`
- line 107: `resetPassword()`
- line 147: `verifyEmailViaOtp()`
- line 156: `resendVerificationOtp()`
- line 210: `resolveRegistrationMethod()`

### ماذا عدلنا في `requestOtp()`

- أصبح يقبل `registration_method` كمسار canonical.
- أبقينا `type` مدعوماً للتوافق الخلفي.
- أصبح يعيد verification metadata وليس message فقط.

### ماذا عدلنا في `verifyOtp()`

- لم يعد endpoint بسيطاً للتحقق فقط.
- أصبح endpoint activation + token issuance.
- عند نجاح OTP:
  - يجلب المستخدم
  - يحدّث verification timestamps عبر `AccountVerificationService`
  - ينشئ access/refresh tokens
  - يعيد user + tokens + verification metadata

### ماذا عدلنا في `resetPassword()`

- أصبح يفهم `registration_method` أيضاً، مع إبقاء `type` مدعوماً.

### ماذا عدلنا في `verifyEmailViaOtp()`

- أزلنا المنطق المحلي القديم.
- حوّلناه إلى wrapper يمرّر التنفيذ إلى `verifyOtp()` مع `registration_method=email`.

### ماذا عدلنا في `resendVerificationOtp()`

- أصبح generic للبريد أو الهاتف.
- يمنع resend إذا الحساب already verified.

### ماذا أضفنا؟

- `resolveRegistrationMethod()` لدعم العقدة الجديدة مع backward compatibility.

---

## Resource layer

### 10. تحديث `UserResource`

**الملف:** `app/Http/Resources/UserResource.php`

**المواضع المهمة:**

- line 24: `registration_method`
- line 30: `email_verified_at`
- line 31: `phone_verified_at`
- line 32 تقريباً: `is_account_verified`
- line 33: `requires_otp_verification`

**الأثر:**

- الـ frontend أصبح يستلم account state واضحة وموحّدة بعد `register/login/verify-otp`.

---

## Middleware و route protection

### 11. تعميم middleware التحقق

**الملف:** `app/Http/Middleware/EnsureEmailIsVerifiedViaOtp.php`

**المواضع المهمة:**

- line 20: فحص `$user->requiresOtpVerification()`
- line 27: إعادة `requires_otp_verification=true`

**ماذا عدلنا؟**

- الميدلوير لم يعد يفحص `email_verified_via_otp_at` فقط.
- أصبح generic account verification gate.
- الرسالة أصبحت أعم: account verification وليس email verification فقط.

### 12. تسجيل alias جديد للميدلوير

**الملف:** `bootstrap/app.php`

**المواضع المهمة:**

- line 27: الإبقاء على `verified.otp`
- line 28: إضافة `verified.customer`

**الهدف:**

- الحفاظ على backward compatibility مع alias القديم.
- تقديم alias أوضح للمسارات customer-side.

### 13. تحديث `routes/api.php`

**الملف:** `routes/api.php`

**المواضع المهمة:**

- line 29: `request-otp`
- line 30: `verify-otp`
- line 43: `resend-verification-otp`
- line 68: حماية customer auth routes عبر `verified.customer`
- line 143: حماية print routes عبر `verified.customer`

**ماذا عدلنا؟**

- حذفنا duplication قديم لعدد من auth routes خارج group.
- ربطنا `request-otp` و`verify-otp` و`reset-password` داخل `/api/auth/...` بشكل موحّد.
- استبدلنا الاعتماد على `verified` في bookings بتوحيد gate على مستوى customer protected groups.

**ماذا حذفنا؟**

- حذفنا registrations المكررة لمسارات:
  - `/auth/request-otp`
  - `/auth/verify-otp`
  - `/auth/reset-password`

**الأثر:**

- أي unverified customer أصبح blocked على المسارات المحمية حتى لو كان يملك token قديم.

---

## Config و environment

### 14. إضافة إعدادات Vonage

**الملف:** `config/services.php`

**المواضع المهمة:**

- line 54-59: إضافة block `vonage`

**الملف:** `.env.example`

**المواضع المهمة:**

- line 67: `VONAGE_SMS_ENABLED`
- line 68: `VONAGE_KEY`
- line 69: `VONAGE_SECRET`
- line 70: `VONAGE_FROM`
- line 71: `VONAGE_BASE_URL`

**الأثر:**

- SMS delivery أصبح قابلاً للتشغيل production-wise من غير hardcode.

---

## Seed data و compatibility

### 15. تحديث `UserSeeder`

**الملف:** `database/seeders/UserSeeder.php`

**المواضع المهمة:**

- line 31-32: admin
- line 51-52: manager
- line 132-133: providers
- line 262-263: customers

**ماذا عدلنا؟**

- أضفنا `registration_method => RegistrationMethod::EMAIL` إلى البيانات المزروعة.
- أبقينا `email_verified_at` للحسابات المزروعة.

**الهدف:**

- seeders الآن متوافقة مع الـ schema الجديدة ومع سياسة التفعيل.

---

## الاختبارات `Tests`

### 16. إضافة test suite جديدة لمسار auth بالكامل

**الملف:** `tests/Feature/AuthVerificationFlowTest.php`

**المواضع المهمة:**

- line 15: بداية الكلاس
- line 32: `test_register_with_email_creates_unverified_user_without_tokens`
- line 62: `test_register_with_phone_creates_unverified_user_without_tokens`
- line 88: `test_unverified_login_returns_verification_challenge_without_tokens`
- line 120: `test_verify_email_otp_marks_account_verified_and_returns_tokens`
- line 164: `test_verify_phone_otp_marks_account_verified_and_returns_tokens`
- line 204: `test_refresh_is_blocked_for_unverified_customer_accounts`
- line 236: `test_verified_customer_can_access_protected_route`
- line 258: `test_unverified_customer_is_blocked_from_protected_route_even_with_existing_token`
- line 281: `test_provider_login_is_not_blocked_by_customer_verification_gate`

**ماذا غطّينا؟**

- register email
- register phone
- login challenge
- verify email otp
- verify phone otp
- refresh challenge
- middleware blocking
- staff bypass

### 17. تعديل اختبارات موجودة لتتوافق مع policy الجديدة

**الملف:** `tests/Feature/ProfileUpdateTest.php`

- line 29-30: إضافة `registration_method` و`email_verified_at`

**الملف:** `tests/Feature/DeleteAccountTest.php`

- line 28-29: تفعيل customer fixture
- line 38: إضافة `registration_method` لحساب provider fixture
- line 179: إضافة `registration_method` في provider test الآخر

**لماذا؟**

- لأن profile/delete أصبحا خلف verification gate، ومن غير المنطقي اختبارهما على customer غير مفعّل بعد الآن.

---

## التوثيق `Docs`

### 18. تحديث `API.md`

**الملف:** `API.md`

**المواضع المهمة:**

- line 61: مثال `Step 1 — Login` المختصر في مقدمة الملف
- line 133: `Login`
- line 225: `Register`
- line 350: `Refresh Token`
- line 491: `Request OTP`
- line 538: `Verify OTP`
- line 601: `Verify Email via OTP`
- line 639: `Resend Verification OTP`
- line 1642: `Bookings API`
- line 1644: ملاحظة `Authentication + Account Verification`

**ماذا عدلنا؟**

- حدّثنا مثال login المختصر في بداية الملف ليستخدم `registration_method` ويشرح challenge flow.
- شرحنا أن `register` لم يعد يعيد tokens.
- شرحنا verification challenge في `login` و`refresh`.
- شرحنا أن `verify-otp` أصبح يعيد tokens.
- شرحنا generic verification باستخدام `registration_method`.
- حدّثنا note الخاصة بالمسارات التي كانت تعتمد فقط على `email_verified_at` لتصبح account activation عامة.

### 19. تحديث `docs/API/API_TEST_PLAN.md`

**المواضع المهمة:**

- line 246: `TC-AUTH-REG-001`
- line 296: `TC-AUTH-LOGIN-004`
- line 322: `TC-AUTH-REFRESH-004`
- line 410: `TC-AUTH-VEMAIL-001`
- line 424: `TC-AUTH-RESEND-001`
- line 438: `TC-AUTH-REQOTP-001`

**ماذا عدلنا؟**

- تحديث expected results لتطابق السلوك الجديد.
- إضافة حالات challenge على login/refresh.
- توسيع اختبار request/resend OTP.

### 20. تحديث `docs/API/BarberBooking_Postman_Collection.json`

**المواضع المهمة:**

- line 36: `Register`
- line 77: `Login`
- line 284: `Verify Email OTP`
- line 325: `Resend Verification OTP`
- line 366: `Request OTP`
- line 407: `Verify OTP`
- line 448: `Reset Password Via OTP`

**ماذا عدلنا؟**

- عدّلنا payloads لتستخدم `registration_method`.
- حدّثنا descriptions لتشرح challenge flow.
- عدّلنا login test script ليقبل `403` كحالة متوقعة للحساب غير المفعّل.

---

## ملف الخطة المحفوظ

### 21. ملف الخطة المرجعية

**الملف:** `plan-unifiedOtpActivation.prompt.md`

**الموضع المهم:**

- line 1

**الغرض:**

- حفظ الخطة التنفيذية المرجعية التي بُني عليها التنفيذ.

---

## ماذا أضفنا وماذا عدلنا وماذا حذفنا بشكل مركز

### ماذا أضفنا

1. `phone_verified_at`
2. `registration_method`
3. `AccountVerificationService`
4. Vonage config/env
5. Auth feature tests جديدة
6. Middleware alias جديد `verified.customer`
7. Generic OTP verification flow يعيد tokens

### ماذا عدلنا

1. `register`
2. `login`
3. `refresh`
4. `verify-otp`
5. `verify-email-otp`
6. `resend-verification-otp`
7. `request-otp`
8. customer route protection
9. seeders
10. API docs + test plan + postman collection

### ماذا حذفنا أو أزلنا من السلوك

1. auto-login من `register`
2. implicit access للتوكن قبل التفعيل
3. duplication في بعض auth routes خارج group
4. اعتماد bookings على `verified` القديم الذي لم يكن يعكس policy المطلوبة فعلياً

---

## التأثير الفعلي على النظام

### التأثير الأمني

- تقليل إمكانية استخدام fake email accounts مباشرة بعد التسجيل.
- منع الوصول customer-side قبل التفعيل.
- منع bypass عبر `refresh_token`.
- منع الاستفادة من access tokens القديمة غير المفعّلة عبر middleware gate.

### التأثير على الـ frontend / mobile app

- `register` response تغيّر جذرياً: لا يوجد tokens.
- `login` قد يرجع `403 verification challenge` بدل `200 tokens`.
- `verify-otp` أصبح الآن مسؤولاً عن إصدار tokens.
- يجب على العميل التعامل مع `registration_method` و`requires_otp_verification` بشكل صريح.

### التأثير على البيانات القديمة

- migration الجديدة تقوم grandfathering للحسابات القديمة غير المفعلة عبر ضبط `email_verified_at` عندما لا يوجد أي verification timestamp.
- هذا يمنع كسر وصول المستخدمين الحاليين.

---

## الأمور التي يجب الانتباه لها جداً

1. **تفعيل Vonage فعلياً** لن يحدث إلا إذا ضُبطت المتغيرات التالية في البيئة الحقيقية:
   - `VONAGE_SMS_ENABLED=true`
   - `VONAGE_KEY`
   - `VONAGE_SECRET`
   - `VONAGE_FROM`

2. **Duplicate phones** في قاعدة البيانات الحالية ستمنع migration من إضافة `unique(phone)`.

3. **العملاء الحاليون** سيتم اعتبارهم grandfathered إذا لم يكن عندهم verification timestamps، وهذا قرار intentional لمنع lockout.

4. **`forgot-password`** في `AuthController` ما زال email-only، بينما `request-otp` و`reset-password` أصبحا يفهمان `registration_method`. إذا أردتم توحيد reset بالكامل لاحقاً، فهذا تحسين لاحق منطقي.

5. **legacy compatibility**:
   - أبقينا `verify-email-otp` كـ wrapper.
   - أبقينا `type` مدعوماً مؤقتاً في OTP endpoints.
   - لكن المسار canonical الجديد هو `registration_method`.

6. **staff bypass** حالياً يعامل `admin`, `provider`, `manager` كحسابات staff لا تحتاج OTP gate.

7. **register الآن ينشئ role customer تلقائياً** باستخدام `Role::findOrCreate(...)` قبل `assignRole(...)`.

---

## نتائج التحقق والاختبارات التي تم تشغيلها

تم تشغيل الأمر التالي:

```bash
php artisan test tests/Feature/AuthVerificationFlowTest.php tests/Feature/ProfileUpdateTest.php tests/Feature/DeleteAccountTest.php
```

**النتيجة:**

- 12 tests passed
- 76 assertions passed

### ما الذي أثبتته هذه الاختبارات؟

1. email registration لا يعيد tokens
2. phone registration لا يعيد tokens
3. unverified login يعيد challenge
4. email verification يعيد tokens ويفعّل الحساب
5. phone verification يعيد tokens ويفعّل الحساب
6. refresh للحساب غير المفعّل مرفوض
7. verified customer يصل إلى protected routes
8. unverified customer يُمنع حتى مع token موجود
9. provider لا يتأثر ببوابة التفعيل الخاصة بالعملاء
10. اختبارات profile/delete القديمة استمرت ناجحة بعد جعل fixtures verified

---

## الخلاصة المعمارية

التعديل لم يكن مجرد إضافة `OTP check`، بل كان **إعادة تعريف كاملة لـ authentication lifecycle**:

- قبل التعديل: `register -> token issued -> verification optional عملياً`
- بعد التعديل: `register -> otp challenge -> verify-otp -> token issued`

وبنفس الوقت تم دعم:

- `email activation`
- `phone activation`
- `legacy compatibility`
- `route enforcement`
- `refresh token enforcement`
- `documentation + tests`

هذا يجعل النظام الآن أقرب إلى `production-safe onboarding flow` بدل السلوك السابق الذي كان يسمح بـ premature access قبل التفعيل.
