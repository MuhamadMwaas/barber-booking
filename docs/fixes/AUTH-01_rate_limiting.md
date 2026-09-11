# AUTH-01 — إصلاح غياب حدود المعدّل على نقاط المصادقة

> **التاريخ:** 29 أغسطس 2026
> **الثغرة:** `AUTH-01` في [`SECURITY_AUDIT_2026-08-29.md`](../../SECURITY_AUDIT_2026-08-29.md) — 🔴 Critical
> **الحالة:** ✅ **مُصلحة ومُتحقَّق منها بتنفيذ فعلي**
> **الاختبارات:** 8 اختبارات جديدة تمر · 251 اختباراً ناجحاً في المجموعة (كانت 243) · **صفر تراجعات**

---

## 1. ما كانت المشكلة

نقاط المصادقة في [`routes/api.php`](../../routes/api.php) كانت **بلا أي سقف على الإطلاق**:

```php
Route::post('register', [AuthController::class, 'register']);      // بلا throttle
Route::post('login',    [AuthController::class, 'login']);         // بلا throttle
Route::post('refresh',  [AuthController::class, 'refresh']);       // بلا throttle
Route::post('request-otp', [OtpController::class, 'requestOtp']);  // بلا throttle
Route::post('verify-otp',  [OtpController::class, 'verifyOtp']);   // بلا throttle
Route::post('google',        [SocialAuthController::class, 'google']);        // بلا throttle
Route::post('google/mobile', [SocialAuthController::class, 'googleMobile']);  // بلا throttle
Route::post('verify-email-otp',        [OtpController::class, 'verifyEmailViaOtp']);        // بلا throttle
Route::post('resend-verification-otp', [OtpController::class, 'resendVerificationOtp']);    // بلا throttle
```

لاحظ التناقض: مسارات إعادة تعيين كلمة المرور في **نفس المجموعة** كانت محمية (`throttle:5,1` و`throttle:10,1`)، بينما تسجيل الدخول وتحقق الـ OTP — وهما أخطر منها — بلا شيء.

**ولماذا لم تحمِها المجموعة العامة؟** لأن `CFG-01` كان قد أزال `throttle:api` من مجموعة `api` (باستخدام `group()` بدل `appendToGroup()`). فلم يكن هناك سقف على مستوى المسار **ولا** على مستوى المجموعة.

### سلسلة الاستغلال التي كانت مفتوحة

```
AUTH-01  لا سقف على /api/auth/verify-otp
   +
AUTH-02  increment('attempts') قراءة-ثم-كتابة بلا قفل → العدّاد يتخلّف تحت التوازي
   +
AUTH-03  الـ OTP نصّي صريح، 6 أرقام، مساحة 1,000,000 فقط، عمره 10 دقائق
   ⇒ تخمين متوازٍ بلا سقف = استيلاء على أي حساب
```

---

## 2. القرار التصميمي الأهم: بُعدان لا بُعد واحد

الإصلاح البديهي هو `throttle:5,1` على كل مسار. **رفضتُ ذلك**، والسبب جوهري:

> `throttle:5,1` يُفرَض على **عنوان الـ IP**. والمهاجم **يختار عنوانه**. أما ما لا يختاره أبداً فهو **الحساب الذي يهاجمه**.

لذلك كل محدِّد هنا يفرض بُعدين مستقلين:

| البُعد | المفتاح | ما الذي يوقفه |
|---|---|---|
| **per_ip** | `request()->ip()` | مهاجم يجرّب **حسابات كثيرة** من مكان واحد (credential stuffing) |
| **per_account** | مُعرِّف الحساب من جسم الطلب | مهاجم يجرّب **حساباً واحداً** من أماكن كثيرة (password spraying / تخمين OTP موزّع) |

البُعدان يفشلان في اتجاهين متعاكسين، فلا يكفي أحدهما وحده:

- مهاجم يبدّل عناوينه ← يفلت من كل دلاء الـ IP، **لكنه يبقى مثبَّتاً على مفتاح حساب واحد**.
- مهاجم يرشّ حسابات كثيرة من مضيف واحد ← يفلت من دلاء الحساب، **لكنه يبقى مثبَّتاً على مفتاح IP واحد**.

الصياغة المضمّنة `throttle:5,1` **لا تستطيع التعبير عن بُعدين**. لذلك استخدمتُ محدِّدات مُسمّاة (`throttle:auth-login`) معرَّفة عبر `RateLimiter::for()`.

> **ملاحظة على الترتيب:** هذا الإصلاح كان سيبقى بلا قيمة تقريباً لولا أن `CFG-02` (`trustProxies('*')`) قد أُصلح مسبقاً. مع `trustProxies('*')`، كان بُعد الـ IP بالكامل مُدخلاً يختاره المهاجم عبر ترويسة `X-Forwarded-For`. الآن `bootstrap/app.php` يثبّت الثقة على `127.0.0.1` فقط، فصار `request()->ip()` حقيقة لا ادّعاءً.

---

## 3. الملفات المُعدَّلة

```
 app/Providers/AppServiceProvider.php  | 123 +++++++++++++++++++++++++++++++++
 routes/api.php                        |  47 ++++++++++---
 app/Http/Requests/LoginRequest.php    |  11 ++-
 app/Http/Requests/RegisterRequest.php |   8 ++-
 lang/ar/auth.php                      |   6 +-
 lang/de/auth.php                      |   5 ++
 lang/en/auth.php                      |   5 ++
 7 files changed, 191 insertions(+), 14 deletions(-)

 جديد: config/rate_limits.php
 جديد: app/Support/ThrottleKey.php
 جديد: tests/Feature/AuthRateLimitTest.php
```

---

## 4. تفصيل كل تغيير

### 4.1 `config/rate_limits.php` — جديد

كل الأرقام في ملف إعداد واحد قابل للضبط عبر `.env`، لا أرقام سحرية مدفونة في الكود. هذا يتبع نمط `config/otp.php` الموجود أصلاً في المشروع.

```php
'login' => [
    'per_ip'           => (int) env('RL_LOGIN_PER_IP', 10),
    'per_account'      => (int) env('RL_LOGIN_PER_ACCOUNT', 5),
    'per_account_hour' => (int) env('RL_LOGIN_PER_ACCOUNT_HOUR', 20),
],
'register'  => ['per_ip_hour' => (int) env('RL_REGISTER_PER_IP_HOUR', 5)],
'refresh'   => ['per_ip'      => (int) env('RL_REFRESH_PER_IP', 20)],
'otp_send'  => [
    'per_ip'               => (int) env('RL_OTP_SEND_PER_IP', 3),
    'per_destination_hour' => (int) env('RL_OTP_SEND_PER_DESTINATION_HOUR', 5),
],
'otp_verify' => [
    'per_ip'               => (int) env('RL_OTP_VERIFY_PER_IP', 10),
    'per_destination'      => (int) env('RL_OTP_VERIFY_PER_DESTINATION', 5),
    'per_destination_hour' => (int) env('RL_OTP_VERIFY_PER_DESTINATION_HOUR', 20),
],
'social'    => ['per_ip'      => (int) env('RL_SOCIAL_PER_IP', 20)],
```

#### لماذا هذه الأرقام تحديداً

| المسار | الحد | التبرير |
|---|---|---|
| **login** per_ip | 10/دقيقة | مستخدم حقيقي يحتاج 1–3 محاولات. الرقم سخيّ عمداً لأن شبكات الموبايل تضع آلاف العملاء خلف عنوان CGNAT واحد — سقف ضيّق يعاقب المستخدمين الحقيقيين أولاً. |
| **login** per_account | 5/دقيقة | هذا هو الحدّ الفعّال. الضيق يعيش هنا، حيث لا يقترب منه مستخدم شرعي أبداً. |
| **login** per_account_hour | 20/ساعة | حاجز النافذة الطويلة. بدونه، حدّ الدقيقة وحده يسمح بـ **7,200 تخمين يومياً** على حساب واحد. مع الحدّ الساعي تنزل إلى 480. |
| **register** per_ip_hour | 5/ساعة | التسجيل فعل يحدث مرة في العمر. نافذة ساعة لا دقيقة، لأن أي شيء أسرع هو أتمتة. |
| **refresh** per_ip | 20/دقيقة | التطبيق يُجدّد كل ~15 دقيقة. الحد أعلى بمراتب من الاستخدام الطبيعي؛ وجوده لمنع إعادة تشغيل توكن مسروق في حلقة. |
| **otp_send** per_ip | 3/دقيقة | يُنفق رصيد SMS حقيقياً. |
| **otp_send** per_destination_hour | 5/ساعة | يحدّ كم من رصيدك يستطيع مهاجم حرقه على وجهة واحدة، وكم يستطيع إغراق هاتف ضحية — **بغض النظر عن مصدره**. |
| **otp_verify** per_destination | 5/دقيقة + 20/ساعة | الأهم أمنياً. راجع الحساب أدناه. |
| **social** per_ip | 20/دقيقة | كل نداء يُطلق طلباً خارجياً إلى Google للتحقق من التوكن — تكلفة وتبعية خارجية. |

#### حساب جدوى تخمين الـ OTP بعد الإصلاح

```
مساحة الرمز:            1,000,000 احتمال (6 أرقام)
عمر الرمز:              10 دقائق  (config/otp.php: ttl_minutes)
الحد على الوجهة:        20 محاولة/ساعة

الزمن اللازم لاستنفاد المساحة = 1,000,000 ÷ 20 = 50,000 ساعة ≈ 5.7 سنة
   ... مقابل رمز يموت بعد 10 دقائق.
```

الهجوم لم يعد صعباً — صار **مستحيلاً رياضياً**.

---

### 4.2 `app/Support/ThrottleKey.php` — جديد

يشتق مفتاح «أي حساب يستهدفه هذا الطلب؟». وهو **دالة كلية (total): لا ترمي استثناءً أبداً**.

```php
public static function rawIdentifier(Request $request): ?string
{
    $method = $request->input('registration_method');
    $method = is_string($method) ? strtolower(trim($method)) : null;

    $value = match ($method) {
        'phone' => $request->input('phone'),
        'email' => $request->input('email'),
        default => $request->input('email') ?? $request->input('phone'),
    };

    if (! is_string($value)) {          // يحمي من email[]=a&email[]=b
        return null;
    }

    $value = trim($value);
    if ($value === '') return null;

    $normalised = str_contains($value, '@')
        ? mb_strtolower($value)
        : (string) preg_replace('/(?!^\+)\D/', '', $value);

    return $normalised === '' ? null : $normalised;
}
```

#### ثلاثة قرارات دقيقة داخل هذه الدالة

**١. التطبيع ليس تجميلاً — بل صحة.**
بدونه يحصل `Victim@Example.com` و`victim@example.com` على دلوين منفصلين، **فيتضاعف الحد بمجرد تغيير حالة الأحرف**. والهواتف تصل بصيغ كثيرة (`+49 30 123`، `0049-30-123`)؛ التقليص إلى الأرقام مع `+` بادئة يضمن دلواً واحداً لكل رقم حقيقي.

**٢. الحماية من المدخلات غير النصية.**
`?email[]=a&email[]=b` يجعل `input()` يُرجع مصفوفة. تمريرها إلى دالة نصية = `TypeError` = **500 على نقطة غير مصادَق عليها**. المبدأ: **المحدِّد يجب ألا يكون أبداً هو ما يكسر الطلب.**

**٣. لماذا لم أستخدم `Services\Sms\PhoneNumberNormalizer` الموجود؟**
لأنه قد يرفض مدخلاً يعتبره غير صالح. ومفتاح الـ throttle **يجب أن يكون قابلاً للاشتقاق من المدخلات المشوّهة أيضاً** — فتلك بالضبط هي اللحظة التي يكون فيها النظام تحت الهجوم.

#### والتجزئة قبل الاستخدام

```php
public static function forIdentifier(Request $request): ?string
{
    $identifier = self::rawIdentifier($request);
    return $identifier !== null ? hash('sha256', $identifier) : null;
}
```

السبب عملي: `CACHE_STORE=database`. مفتاح غير مجزّأ كان سيكتب **بريد وهاتف كل عميل نصاً صريحاً في جدول `cache`** — وهو مكان لا يتوقّع أحد أن يجد فيه بيانات شخصية.

---

### 4.3 `app/Providers/AppServiceProvider.php`

أُضيفت `registerAuthRateLimiters()` وتُستدعى من `boot()` بجانب محدِّد `api` الموجود.

```php
RateLimiter::for('auth-login', function (Request $request) use ($limits, $tooManyRequests, $accountKey) {
    return [
        Limit::perMinute($limits['login']['per_ip'])
            ->by('auth-login:ip:' . $request->ip())
            ->response($tooManyRequests),

        Limit::perMinute($limits['login']['per_account'])
            ->by($accountKey($request, 'auth-login'))
            ->response($tooManyRequests),

        Limit::perHour($limits['login']['per_account_hour'])
            ->by($accountKey($request, 'auth-login-hourly'))
            ->response($tooManyRequests),
    ];
});
```

إرجاع **مصفوفة** من `Limit` يجعل Laravel يفرضها كلها، وأول ما يُستنفَد يفوز.

#### تفصيلتان تمنعان علتين صامتتين

**١. كل مفتاح مسبوق باسم المحدِّد.**

```php
->by('auth-login:ip:' . $request->ip())      // ✅ مسبوق
->by($request->ip())                          // ❌ كان سيشارك الدلو
```

دلاء المحدِّدات مشتركة على مستوى الكاش كله. مفتاح غير مسبوق كان سيجعل طلباً إلى `login` **يُنفق ميزانية `verify-otp`** لنفس العنوان.

**٢. السقوط الآمن إلى الـ IP عند غياب المُعرِّف.**

```php
$accountKey = function (Request $request, string $scope): string {
    $identifier = ThrottleKey::forIdentifier($request);

    return $identifier !== null
        ? "{$scope}:acct:{$identifier}"
        : "{$scope}:ip:{$request->ip()}";     // ✅ لا مسار بلا حدّ
};
```

بدون هذا السقوط، كان جسم طلب مشوّه (`email[]=x` أو بلا بريد أصلاً) يُنتج مفتاحاً فارغاً — أي **مساراً غير محدود يفتحه المهاجم بمجرد حذف حقل**.

#### استجابة 429 بصيغة الـ API

استجابة Laravel القياسية عند تجاوز الحد هي `{"message": "Too Many Attempts."}` — بالإنجليزية فقط، وبلا `success`، ولا `error_type`. تطبيق الموبايل يقرأ `success`/`message`، فلن يعرض شيئاً مفهوماً.

```php
$tooManyRequests = function (Request $request, array $headers) {
    $seconds = (int) ($headers['Retry-After'] ?? 60);

    return response()->json([
        'success'     => false,
        'message'     => __('auth.throttle_generic', ['seconds' => $seconds]),
        'error_type'  => 'rate_limited',
        'retry_after' => $seconds,
    ], 429, $headers);
};
```

---

### 4.4 `routes/api.php`

```php
Route::post('register', [AuthController::class, 'register'])
    ->middleware('throttle:auth-register');
Route::post('login', [AuthController::class, 'login'])
    ->middleware('throttle:auth-login');
Route::post('refresh', [AuthController::class, 'refresh'])
    ->middleware('throttle:auth-refresh');

Route::post('request-otp', [OtpController::class, 'requestOtp'])
    ->middleware('throttle:otp-send');
Route::post('verify-otp', [OtpController::class, 'verifyOtp'])
    ->middleware('throttle:otp-verify');

Route::post('google', [SocialAuthController::class, 'google'])
    ->middleware('throttle:auth-social');
Route::post('google/mobile', [SocialAuthController::class, 'googleMobile'])
    ->middleware('throttle:auth-social');

Route::post('verify-email-otp', [OtpController::class, 'verifyEmailViaOtp'])
    ->middleware('throttle:otp-verify');
Route::post('resend-verification-otp', [OtpController::class, 'resendVerificationOtp'])
    ->middleware('throttle:otp-send');
```

**نقطة كانت ستُفوَّت بسهولة:** `verifyEmailViaOtp()` لا يحتوي منطقاً خاصاً به — بل يُفوِّض مباشرة إلى `verifyOtp()`:

```php
public function verifyEmailViaOtp(Request $request)
{
    $request->merge(['registration_method' => RegistrationMethod::EMAIL->value]);
    return $this->verifyOtp($request);          // نفس سطح التخمين، باسم آخر
}
```

تركه بلا حدّ كان سيترك **باباً ثانياً غير مُقاس إلى نفس سطح الهجوم**. لذلك يحمل نفس المحدِّد ويتقاسم نفس الميزانية — وهناك اختبار يحرس ذلك تحديداً.

**استثناءان مقصودان:**
- `logout` — مصادَق عليه ومتكرِّر الأثر (idempotent)؛ التوكن الصالح هو الحدّ بذاته، ويغطّيه `throttle:api`.
- `google/redirect` و`google/callback` — تحويلات ويب بطريقة `GET`، ويغطّيها `throttle:api` (60/دقيقة).

---

### 4.5 ملفات اللغة

أُضيف `auth.throttle_generic` بالثلاث لغات:

| اللغة | النص |
|---|---|
| ar | `طلبات كثيرة جداً. يرجى المحاولة مجدداً بعد :seconds ثانية.` |
| de | `Zu viele Anfragen. Bitte versuchen Sie es in :seconds Sekunden erneut.` |
| en | `Too many requests. Please try again in :seconds seconds.` |

**والرسالة لا تذكر أي حدّ تم تجاوزه — عمداً.** لو قالت «تجاوزت حدّ هذا الحساب»، لكانت **أكّدت للمهاجم أن الحساب موجود** — أي حوّلت حماية إلى أداة تعداد مستخدمين.

كما صحّحت `lang/ar/auth.php` سطر 6: مفتاح `throttle` كان نصاً إنجليزياً في ملف عربي.

---

## 5. إصلاح إضافي — خارج نطاق AUTH-01 (أُعلنه صراحةً)

أثناء اختبار المدخلات المشوّهة، اكتشفتُ **خطأ 500 موجوداً مسبقاً** لا علاقة له بعملي:

```php
// LoginRequest.php:46 (قبل)  و RegisterRequest.php:62
'registration_method' => strtolower((string) $this->input('registration_method')),
```

`registration_method[]=email` → تحويل مصفوفة إلى نص → `ErrorException: Array to string conversion` → **500 على نقطة غير مصادَق عليها**.

**أثبتُّ أنه سابق لتغييري:**

```
ThrottleKey::forIdentifier()          → OK  (كودي تعامل مع المصفوفة بشكل صحيح)
LoginRequest::prepareForValidation()  → THREW ErrorException — Array to string conversion
                                         at app/Http/Requests/LoginRequest.php:46
```

**لماذا أصلحتُه رغم خروجه عن النطاق:** إنه على المسارات نفسها التي كنتُ أُحصّنها، والإصلاح ثلاثة أسطر. وتركُ خطأ 500 غير مصادَق عليه اكتشفتُه للتو، بينما أُعلن أن سطح المصادقة صار محصّناً، كان سيكون تقريراً مضلِّلاً.

```php
// بعد — في الملفين
$method = $this->input('registration_method');

if (is_string($method)) {
    $this->merge(['registration_method' => strtolower($method)]);
}
// غير النصّي يسقط إلى Rule::enum في rules() ويُرفض كـ 422
```

**إن أردت تتبّع هذا كبند منفصل، تراجُعه سهل:** `git checkout app/Http/Requests/LoginRequest.php app/Http/Requests/RegisterRequest.php`.

---

## 6. التحقق — ما نفّذتُه فعلياً

لم أكتفِ بالقراءة. كل ما يلي مخرجات حقيقية من تشغيل فعلي على مشروعك.

### 6.1 الـ middleware مركّب على كل مسار

```
ROUTE                                  MIDDLEWARE
POST api/auth/register                 throttle:auth-register
POST api/auth/login                    throttle:auth-login
POST api/auth/refresh                  throttle:auth-refresh
POST api/auth/request-otp              throttle:otp-send
POST api/auth/verify-otp               throttle:otp-verify
POST api/auth/google                   throttle:auth-social
POST api/auth/google/mobile            throttle:auth-social
POST api/auth/verify-email-otp         throttle:otp-verify
POST api/auth/resend-verification-otp  throttle:otp-send
POST api/auth/forgot-password          throttle:5,1        (كان موجوداً)
POST api/auth/password/verify-otp      throttle:10,1       (كان موجوداً)
POST api/auth/reset-password           throttle:10,1       (كان موجوداً)
POST api/auth/logout                   —  (مصادَق عليه، مقصود)
```

### 6.2 بُعد الـ IP يعمل

```
=== login، نفس الـ IP، حسابات مختلفة (per_ip = 10) ===
  attempt  9 -> HTTP 401
  attempt 10 -> HTTP 401
  attempt 11 -> HTTP 429      ← الحدّ فُرِض
  attempt 12 -> HTTP 429
```

### 6.3 بُعد الحساب يصمد أمام تبديل العناوين — النتيجة الأهم

```
=== login: حساب ضحية واحد، كل طلب من IP مختلف (per_account = 5) ===
  IP 198.51.100.1  -> HTTP 401
  IP 198.51.100.2  -> HTTP 401
  IP 198.51.100.3  -> HTTP 401
  IP 198.51.100.4  -> HTTP 401
  IP 198.51.100.5  -> HTTP 401
  IP 198.51.100.6  -> HTTP 429  | rate_limited | retry_after=60 | Too many requests...
  IP 198.51.100.7  -> HTTP 429
  IP 198.51.100.8  -> HTTP 429
```

**هذا بالضبط ما لم يكن `throttle:5,1` ليحققه.** كل طلب جاء من عنوان جديد، ومع ذلك تم إيقافه.

### 6.4 تخمين الـ OTP موقوف

```
=== وجهة واحدة، عناوين متبدّلة (per_destination = 5/دقيقة) ===
  guess 000001 من 192.0.2.1 -> 422
  guess 000002 من 192.0.2.2 -> 422
  guess 000003 من 192.0.2.3 -> 422
  guess 000004 من 192.0.2.4 -> 422
  guess 000005 من 192.0.2.5 -> 422
  guess 000006 من 192.0.2.6 -> 429      ← موقوف
  guess 000007 من 192.0.2.7 -> 429
```

### 6.5 التطبيع يعمل

```
=== حالة الأحرف ===
  VICTIM@Example.COM من IP جديد -> 429   (شارك دلو victim@example.com)

=== الهواتف — كلها تُنتج مفتاحاً واحداً ===
  +49 30 1234567  -> +49301234567
  +49-30-1234567  -> +49301234567
  +493012 34567   -> +49301234567

=== البريد ===
  Victim@Example.COM  -> victim@example.com
   victim@example.com -> victim@example.com
```

### 6.6 المدخلات المشوّهة لا تُسقط السيرفر

```
=== بعد الإصلاح ===
  array email    -> HTTP 422   ok
  array method   -> HTTP 422   ok      (كان 500 قبل الإصلاح)
  int method     -> HTTP 422   ok
  no identifier  -> HTTP 422   ok
  blank email    -> HTTP 422   ok
```

### 6.7 الترجمة وترويسات المعيار

```
ar  [429] طلبات كثيرة جداً. يرجى المحاولة مجدداً بعد 60 ثانية.
de  [429] Zu viele Anfragen. Bitte versuchen Sie es in 60 Sekunden erneut.
en  [429] Too many requests. Please try again in 60 seconds.

Retry-After: 60
X-RateLimit-Limit: 5
X-RateLimit-Remaining: 0
```

### 6.8 صفر تراجعات — مُثبت بمقارنة قبل/بعد

شغّلت المجموعة كاملة، ثم **خبّأت تغييراتي بـ `git stash` وشغّلتها مجدداً**، ثم قارنت:

```
مع تغييراتي:  17 failed, 2 skipped, 251 passed (673 assertions)
بدون تغييراتي: 17 failed, 2 skipped, 243 passed (640 assertions)

diff أسماء الاختبارات الفاشلة → IDENTICAL — no test regressions introduced
```

الـ 17 فشلاً **موجودة مسبقاً** ولا علاقة لها بهذا العمل (اختبارات Fiskaly وTaxCalculator وProfileImageUpload وDeleteAccount وExampleTest). الزيادة `243 → 251` هي اختباراتي الثمانية الجديدة.

> **ملاحظة صريحة:** لم أُصلح الـ 17 فشلاً السابقة — فهي خارج نطاق هذه المهمة. ثلاثة منها (`TaxCalculatorService2Test`، `AdvancedTaxCalculatorTest`) مرتبطة بـ `MON-01` في التقرير الأمني.

---

## 7. الاختبارات الجديدة — `tests/Feature/AuthRateLimitTest.php`

```
PASS  Tests\Feature\AuthRateLimitTest
  ✓ login is limited per ip across different accounts
  ✓ login is limited per account even when the source ip changes
  ✓ account bucket ignores email casing and surrounding space
  ✓ otp verification is limited per destination across ips
  ✓ verify email otp shares the verification budget
  ✓ registration is limited per ip
  ✓ throttled response uses the api error envelope
  ✓ malformed identifiers are rejected without a server error

  Tests: 8 passed (33 assertions)
```

الاختبار الحامل للوزن هو **`login is limited per account even when the source ip changes`**. لو عاد أحدهم يوماً واستبدل المحدِّدات بـ `throttle:5,1` بسيط، فكل الاختبارات الأخرى ستبقى ناجحة — **وهذا وحده سيفشل**، كاشفاً أن الحماية عادت إلى بُعد واحد.

والاختبارات تقرأ حدودها من `config('rate_limits.*')` لا من أرقام مكتوبة، فضبط الأرقام في `.env` لا يكسرها.

---

## 8. مقايضة واعية يجب أن تعرفها

الحدّ المرتبط بالحساب يُدخل **إمكانية حرمان مستخدم بعينه من الخدمة**: مهاجم يستطيع عمداً استنفاد ميزانية تسجيل دخول ضحية بإرسال 5 محاولات خاطئة، فيمنعه من الدخول لمدة دقيقة (أو ساعة عند بلوغ الحدّ الساعي).

**هذه مقايضة أصيلة في كل حدّ مرتبط بحساب، وليست عيباً في التنفيذ.** وازنتُها هكذا:

- **دقيقة واحدة** فترة قصيرة؛ والمهاجم يحتاج إنفاقاً مستمراً لإبقاء الضحية محروماً.
- **20/ساعة** سخيّ بما يكفي ليتجاوزه المستخدم الحقيقي وقد نسي كلمته، لكنه يقلّص التخمين اليومي من 7,200 إلى 480.
- البديل — بُعد الـ IP وحده — يعني **لا حماية إطلاقاً** أمام مهاجم موزَّع. حرمان مؤقت أهون بكثير من استيلاء دائم.

**إن رأيت الحرمان مزعجاً عملياً،** ارفع `RL_LOGIN_PER_ACCOUNT` في `.env` دون تعديل أي كود. وإن أردت إلغاء المقايضة تماماً، فالخطوة التالية هي CAPTCHA بعد فشل متكرر بدل الحظر المطلق — لكن ذلك تغيير أكبر في تجربة المستخدم.

---

## 9. الأثر على ثغرات أخرى في التقرير

| الثغرة | الأثر |
|---|---|
| **`AUTH-02`** (عدّاد محاولات الـ OTP غير ذرّي) | **مُخفَّفة جوهرياً.** العدّاد يظل عرضة للسباق، لكن حدّ `otp-verify` يستخدم **زيادات ذرّية في الكاش** فلا يخضع لنفس السباق. المهاجم لم يعد يستطيع إرسال 200 طلب متوازٍ — يُوقَف عند 5. **ما زال يجب إصلاح `AUTH-02`** (`lockForUpdate`)، لكنه لم يعد قابلاً للاستغلال عملياً بمفرده. |
| **`AUTH-03`** (الـ OTP نصّي صريح) | **غير متأثرة.** الحدّ يمنع التخمين عبر الشبكة، لا قراءة قاعدة البيانات. لا يزال يجب تجزئة الـ OTP. |
| **`CFG-04`** (بوابة SMS مفتوحة) | **غير متأثرة بهذا الإصلاح** — لكنني لاحظت أنك **علّقت المسار بالفعل** في `routes/api.php:73`. ✅ |
| **`AUTHZ-05`** (تعداد المستخدمين عبر رسالة خطأ الحجز) | **غير متأثرة** — مسار مختلف. |

**ملاحظة جانبية لاحظتُها ولم أُصلحها:** `RegisterRequest::failedValidation()` يُرجع `409 EMAIL_ALREADY_EXISTS` لبريد مسجَّل — وهو **أداة تعداد مستخدمين** تكشف أي بريد لديه حساب. حدّ `auth-register` (5/ساعة لكل IP) يقلّص وتيرة الاستغلال لكنه لا يُغلق الثغرة. هذا سلوك مقصود على الأرجح (التطبيق يعرض حوار «الحساب موجود») فلم أُغيّره — لكنه يستحق قراراً واعياً منك.

---

## 10. قبل النشر

```bash
# 1) تحقق من تركيب الـ middleware في بيئة الإنتاج
php artisan route:list --path=api/auth

# 2) أعِد بناء كاش الإعدادات — config/rate_limits.php جديد
php artisan config:clear && php artisan config:cache
php artisan route:clear  && php artisan route:cache

# 3) شغّل اختبارات الحماية
php artisan test --filter=AuthRateLimitTest
```

**لا حاجة لأي متغيّر `.env` جديد** — كل الحدود لها قيم افتراضية معقولة. المتغيّرات (`RL_*`) موجودة للضبط لاحقاً فقط.

**نقطة أداء:** `CACHE_STORE=database`، فكل فحص حدّ هو كتابة في جدول `cache`. مقبول تماماً على أحجام نقاط المصادقة (عشرات الطلبات في الدقيقة). إن انتقلت إلى `CACHE_STORE=redis` — وهو مُعدّ في `.env` عندك — ستصبح هذه العمليات في الذاكرة وأسرع بمراتب، وهو تحسين موصى به مستقلاً عن هذا الإصلاح (راجع `PERF-02`).
