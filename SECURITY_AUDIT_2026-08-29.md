# تقرير التدقيق الأمني والمنطقي الشامل — BarberBooking / LookUp Friseur

> **تاريخ الفحص:** 29 أغسطس 2026
> **الحالة:** ما قبل الإطلاق (Pre-Launch Audit)
> **بيئة الإنتاج المستهدفة:** VPS Hostinger · nginx (بدون بروكسي أمامي) · PHP 8.3-FPM · MySQL · APP_ENV=production · APP_DEBUG=false · queue worker + scheduler يعملان
> **منهجية التحقق:** قراءة الكود سطراً بسطر + **استعلام قاعدة البيانات الحيّة للتحقق من الـ schema والإعدادات الفعلية** + تنفيذ إثباتات رقمية (PHP) + استخراج مجموعات الـ middleware من التطبيق المُقلَع فعلياً.
> **لم يُعدَّل أي ملف كود.** هذا التقرير هو المُخرَج الوحيد.

---

## 0. كيف تقرأ هذا التقرير

كل نتيجة تحمل:

| الحقل | المعنى |
|---|---|
| **المعرّف** | رمز ثابت للإحالة (مثال `CFG-01`) |
| **الخطورة** | 🔴 Critical · 🟠 High · 🟡 Medium · 🔵 Low |
| **الموقع** | `الملف:السطر` — كل موقع قابل للتحقق مباشرة |
| **الحالة** | `مؤكد بالكود` · `مؤكد بقاعدة البيانات` · `مُثبت عملياً` · `كامن (Latent)` |
| **الشرح** | ما هو الخلل تقنياً |
| **مثال/سيناريو استغلال** | خطوات ملموسة أو طلب HTTP فعلي |
| **الأثر على الموقع** | ماذا يحدث للعمل التجاري |
| **الإصلاح** | كود جاهز + فكرة الإصلاح العميقة |

**مصطلح `كامن (Latent)`:** الخلل موجود في الكود لكن البيانات الحالية لا تُفعّله بعد. سيظهر فور إدخال بيانات من نوع معيّن. هذه ليست مبالغة — أوثّقها لأنها ستنفجر بعد الإطلاق لا قبله.

---

## 0.1 تصحيحات على التقرير السابق (COMPREHENSIVE_AUDIT_REPORT_2026-08-28)

فحصتُ كل ادعاء في التقرير القديم مقابل الكود الفعلي. **ستة ادعاءات كانت خاطئة**، وأصنّفها هنا أولاً حتى لا تضيّع وقتاً في إصلاح ما ليس مكسوراً:

| ادعاء التقرير القديم | الحقيقة | الدليل |
|---|---|---|
| **C-02** «AccessToken لا ينتهي أبداً — `expires_at` مُعلّق» | **خاطئ.** السطور 18-20 في `AuthTokenService` **غير مُعلّقة** وتضبط `expires_at` فعلياً، والعمود موجود في الجدول. Sanctum يحترم `expires_at` على مستوى التوكن بغض النظر عن `config('sanctum.expiration')`. التوكن ينتهي خلال 15 دقيقة. | `app/Services/AuthTokenService.php:18-20` + `SHOW COLUMNS FROM personal_access_tokens` يُظهر `expires_at` |
| **C-01** «`/internal/clear-cache` يؤدي إلى **RCE**» | **مبالغة.** لا يوجد أي مُدخل مستخدم داخل `exec()` — السلسلة ثابتة حرفياً. هي ثغرة **DoS/تنفيذ غير مصرّح**، لا RCE. | `routes/web.php:181-185` |
| **C-05** «`PaymentStatus::from((int)$paymentType)` يرمي 500 دائماً — التحصيل مكسور كلياً» | **خاطئ.** المُستدعي يمرّر `'2'` (سلسلة رقمية) → `(int)'2' = 2` → يعمل. التحصيل **يعمل**. توجد مشكلة أخرى حقيقية في نفس السطر (انظر `MON-04`) لكنها ليست هذه. | `app/Livewire/StaffDashboard.php:849` |
| **Q-03** «`formatBranchData` typo `adress` → null دائماً» | **خاطئ.** اسم العمود في قاعدة البيانات هو فعلاً `adress`. الكود صحيح؛ التسمية فقط قبيحة. | `SHOW COLUMNS FROM branchs` |
| **L-34** «`EXTRACT(HOUR FROM ...)` يفشل على MySQL» | **خاطئ.** MySQL 8 / MariaDB تدعم `EXTRACT(HOUR FROM ...)` بشكل كامل. | معيار SQL |
| **C-07** «Mass Assignment مالي — ثغرة Critical قابلة للاستغلال» | **مبالغة كبيرة.** لا يوجد **ولا استدعاء واحد** لـ `->update($request->all())` أو `::create($request->all())` في المشروع كله. كل الـ controllers تستخدم `validate()` + إسناد صريح. هذه **رائحة كود (defence-in-depth)** وليست ثغرة قابلة للاستغلال. | `grep` شامل على `app/Http/Controllers` و`app/Livewire` |
| **الأرقام: «~209 نتيجة، 23 Critical»** | مُضخَّمة بتكرار النمط الواحد عبر ملفات متعددة وعدّه مرات. | — |

> **الدرس:** التقرير القديم يحتوي على نتائج حقيقية مهمة، لكنه خلط بينها وبين افتراضات غير محقَّقة. **كل نتيجة في التقرير الحالي محقَّقة بالكود أو بقاعدة البيانات أو بتنفيذ فعلي.**

---

## 1. الملخّص التنفيذي

### 1.1 الأرقام

| الخطورة | العدد | المعرّفات |
|---|---|---|
| 🔴 **Critical (مانع إطلاق)** | **20** | CFG-01/02/03 · AUTH-01/02 · AUTHZ-01/02/03/04 · BOOK-01/02 · MON-01/02/03/04/05 · SET-01/02 · TZ-01 · DB-01 |
| 🟠 **High** | **19** | CFG-04/05 · AUTH-03/04 · AUTHZ-05 · BOOK-03/04/05 · MON-06/07/08/09 · DB-02/03/04 · PERF-01/02/03 · QUAL-01 |
| 🟡 **Medium** | **21** | CFG-06/07 · AUTH-05/06/07 · BOOK-06/07/08/09/10 · MON-10 · SET-03/04 · DB-05/06 · PERF-04/05/06 · QUAL-02/03/04 |
| 🔵 **Low** | **3** | CFG-08 · BOOK-11 · QUAL-05 |
| **الإجمالي** | **63** | |

> كل نتيجة **واحدة** هنا تعني مشكلة جذرية واحدة — لا تكرار للنمط الواحد عبر عدة ملفات كما في التقرير السابق. حين يظهر النمط نفسه في ملفين (مثل `getEffectiveDuration` في `BookingService` و`ServiceAvailabilityService`) فهو نتيجة واحدة بموقعين.

### 1.2 الحكم النهائي

**النظام غير جاهز للإطلاق في وضعه الحالي.** ليس لأنه سيء البناء — البنية في الحقيقة جيدة في مواضع كثيرة (فصل طبقة التحقق، استخدام bcmath، نظام صلاحيات دقيق في StaffDashboard، معالجة الحجوزات المرتبطة parent/child) — بل بسبب **أربعة مسارات تفشل فشلاً صامتاً** وثلاثة أخطاء إعداد إنتاجية تجعل النظام غير قانوني في ألمانيا.

### 1.3 أخطر 8 نتائج — يجب إصلاحها قبل الإطلاق مهما كان

| # | المعرّف | العنوان | الأثر بجملة واحدة |
|---|---|---|---|
| 1 | **`CFG-01`** | مجموعة `api` middleware مُستبدَلة بالكامل → `SubstituteBindings` مفقود | **كل مسارات الطباعة عبر API (5 مسارات) معطّلة صامتاً** + لا يوجد rate limit عام على أي مسار API |
| 2 | **`CFG-02`** | `trustProxies(at: '*')` مع nginx مباشر بلا بروكسي | **أي مهاجم يتجاوز كل حدود المعدّل** بترويسة `X-Forwarded-For` مزوّرة |
| 3 | **`AUTH-01`** | لا throttle على `login` / `register` / `verify-otp` مطلقاً | **Credential stuffing + تخمين OTP متوازٍ** = استيلاء على الحسابات |
| 4 | **`AUTHZ-01`** | `EnsureStaffDashboardAccess` لا يفحص `is_active` | **موظف مفصول يحتفظ بالوصول الكامل** للوحة: بيانات العملاء، تحصيل الأموال، حذف الحجوزات |
| 5 | **`MON-01`** | حساب الضريبة يختلف بين طبقتين — **مُثبت رقمياً** | **فرق سنت في ضريبة القيمة المضافة في 5 من كل 6 أسعار شائعة** → إقرار ضريبي ألماني خاطئ |
| 6 | **`MON-02`** | `paymentAmount` خاصية Livewire عامة بلا تحقق ولا صلاحية خصم | **أي موظف يُنهي فاتورة بـ 200 يورو مقابل 0.01 يورو** |
| 7 | **`SET-01`+`SET-02`** | العملة `USD` وبيانات الشركة الضريبية **فارغة تماماً** في قاعدة البيانات | **كل فاتورة مطبوعة مخالفة لـ §14 UStG الألماني** — باطلة قانونياً |
| 8 | **`BOOK-01`** | التوفّر والحجز يستخدمان قاعدتَي تعارض **مختلفتين** | مواعيد تظهر متاحة ثم تُرفض، ومواعيد مهجورة **تحجب الأوقات للأبد** |

### 1.4 الخيط الأحمر — لماذا تتفاقم هذه الثغرات معاً

ثلاث سلاسل استغلال حقيقية، كل حلقة فيها مؤكَّدة على حدة:

**السلسلة أ — الاستيلاء على الحسابات:**
```
CFG-01 (لا throttle:api في المجموعة)
   + AUTH-01 (لا throttle على مستوى المسار)
   + CFG-02 (تزوير X-Forwarded-For يُبطل حتى ما هو موجود)
   + AUTH-04 (OTP نصّي 6 أرقام + increment غير ذرّي)
   ⇒ تخمين OTP متوازٍ بلا سقف = استيلاء على أي حساب
```

**السلسلة ب — الاحتيال المالي الداخلي:**
```
AUTHZ-01 (موظف مفصول يبقى داخل اللوحة)
   + MON-02 (لا صلاحية خصم + المبلغ يأتي من المتصفح)
   + MON-03 (لا قفل على الإنهاء)
   + DB-01 (لا UNIQUE على رقم الفاتورة)
   ⇒ فواتير مكرّرة بأرقام متطابقة، بمبالغ يحددها المهاجم
```

**السلسلة ج — البطلان القانوني في ألمانيا:**
```
SET-02 (بيانات الشركة الضريبية فارغة)
   + SET-01 (العملة USD)
   + TZ-01 (المنطقة الزمنية بغداد)
   + MON-01 (ضريبة خاطئة بسنت)
   + DB-01 (أرقام فواتير غير فريدة)
   ⇒ لا فاتورة واحدة تصمد أمام تدقيق GoBD / KassenSichV
```

---

# 2. طبقة الإعداد والبنية التحتية

## 🔴 CFG-01 — مجموعة `api` middleware مُستبدَلة بالكامل

**الموقع:** [`bootstrap/app.php:25-28`](bootstrap/app.php#L25-L28)
**الحالة:** ✅ **مُثبت عملياً** (استخرجتُ مجموعات الـ middleware من التطبيق المُقلَع فعلياً)

### الشرح

```php
$middleware->group('api', [
    EnforceJsonAcceptHeader::class,
    SetApiLocale::class,
]);
```

الدالة `Middleware::group()` في Laravel **تستبدل** المجموعة ولا تضيف إليها. المجموعة الافتراضية لـ `api` تحتوي على `Illuminate\Routing\Middleware\SubstituteBindings`. باستبدالها، حُذف الـ binding.

**الإثبات الفعلي** (نفّذته على مشروعك):

```
WEB GROUP:
   - Illuminate\Cookie\Middleware\EncryptCookies
   - Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse
   - Illuminate\Session\Middleware\StartSession
   - Illuminate\View\Middleware\ShareErrorsFromSession
   - Illuminate\Foundation\Http\Middleware\ValidateCsrfToken
   - Illuminate\Routing\Middleware\SubstituteBindings      <-- موجود
   - App\Http\Middleware\SetLocaleFromSession

API GROUP:
   - App\Http\Middleware\EnforceJsonAcceptHeader
   - App\Http\Middleware\SetApiLocale
                        <-- SubstituteBindings مفقود · throttle مفقود
```

### النتيجة الأولى: كل طباعة عبر API معطّلة صامتاً

```php
// app/Http/Controllers/PrintController.php:55
public function apiPrint(Request $request, Invoice $invoice) { ... }
```

بدون `SubstituteBindings`، لا يقرأ Laravel الجزء `{invoice}` من الرابط إطلاقاً. بدلاً من ذلك يطلب من الـ container بناء `Invoice` — و`Invoice` قابل للإنشاء لأن `Model::__construct(array $attributes = [])` — فيُعيد **نموذجاً فارغاً**.

**إثبات نفّذته على مشروعك:**
```
Container-resolved Invoice: class=App\Models\Invoice  id=NULL  exists=false
```

### مثال عملي

```http
POST /api/invoice/857/print
Authorization: Bearer <token صالح لأي عميل>
Accept: application/json
```

النتيجة: `$invoice->id === null`. الخدمة `PrintService::print()` تعمل على فاتورة بلا بنود وبلا حجز. الإيصال يخرج فارغاً أو ينتهي بـ `500` — **دائماً، وبغض النظر عن رقم الفاتورة المطلوب**.

**المسارات المتأثرة:**

| المسار | الحالة |
|---|---|
| `POST /api/invoice/{invoice}/print` | معطّل (موديل فارغ) |
| `GET /api/invoice/{invoice}/print-url` | معطّل (موديل فارغ) |
| `POST /api/printer/{printer}/test` | معطّل (موديل فارغ) |
| `POST /api/invoices/print-batch` | يعمل — لأنه يقرأ من `$request` لا من binding (لكن انظر `AUTHZ-03`) |

### النتيجة الثانية: لا يوجد rate limit عام على أي مسار API

المجموعة الافتراضية في Laravel 11/12 هي المكان الذي يُركَّب فيه `throttle:api`. الآن كل مسار لا يحمل `throttle` صريحاً في `routes/api.php` هو **بلا أي سقف على الإطلاق**. وهذا يشمل `login` و`register` و`refresh` و`request-otp` و`verify-otp` — انظر `AUTH-01`.

### الأثر على الموقع

1. ميزة طباعة الفواتير من تطبيق الموبايل **لا تعمل ولن تعمل**. وستكتشف ذلك من شكاوى الموظفين لا من سجل الأخطاء، لأن الفشل صامت.
2. الباب مفتوح على مصراعيه لهجمات تخمين كلمات المرور والـ OTP.
3. **فخ مستقبلي:** أي مطوّر يضيف لاحقاً `Route::get('/api/x/{service}', fn (Service $service) => ...)` سيحصل على موديل فارغ ولن يفهم السبب أبداً.

### الإصلاح

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware): void {

    $middleware->appendToGroup('web', SetLocaleFromSession::class);

    // ✅ appendToGroup يضيف فوق الافتراضي بدل استبداله
    $middleware->appendToGroup('api', [
        EnforceJsonAcceptHeader::class,
        SetApiLocale::class,
    ]);

    // ✅ سقف عام لكل الـ API
    $middleware->throttleApi();

    $middleware->trustProxies(at: ['127.0.0.1', '::1']);   // انظر CFG-02

    $middleware->alias([ /* ... كما هو ... */ ]);
})
```

ثم تحقّق فوراً:

```bash
php artisan route:list --path=api/invoice
# يجب أن تظهر SubstituteBindings ضمن قائمة الـ middleware
```

### فكرة الإصلاح العميقة

القاعدة: **لا تستخدم `group()` أبداً على مجموعة يوفّرها الإطار.** استخدم `appendToGroup()` أو `prependToGroup()`. الفرق بين الدالتين صامت ومدمّر، وهذا بالضبط ما وقع هنا.

والأهم: أضِف اختباراً يحرس هذا الافتراض حتى لا يعود:

```php
// tests/Feature/RouteBindingTest.php
it('resolves route model binding on api routes', function () {
    $invoice = Invoice::factory()->create();

    $this->actingAs($staff, 'sanctum')
        ->postJson("/api/invoice/{$invoice->id}/print")
        ->assertJsonPath('data.invoice_id', $invoice->id);   // لا null
});
```

---

## 🔴 CFG-02 — `trustProxies(at: '*')` على سيرفر مكشوف مباشرة

**الموقع:** [`bootstrap/app.php:29`](bootstrap/app.php#L29)
**الحالة:** ✅ **مؤكد بالكود + مؤكد بإعداد nginx الخاص بك**

### الشرح

```php
$middleware->trustProxies(at: '*');
```

هذا يخبر Laravel: «ثِق بترويسة `X-Forwarded-For` القادمة من **أي** مصدر». وهذا آمن **فقط** خلف بروكسي يُعيد كتابة الترويسة (Cloudflare أو ALB أو nginx بـ `proxy_set_header`).

**إعداد nginx الخاص بك** — [`site/sites-enabled/lookup.com:62-68`](site/sites-enabled/lookup.com) — يستخدم `fastcgi_pass` مباشرةً إلى PHP-FPM:

```nginx
location ~ ^/index\.php(/|$) {
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    fastcgi_param HTTPS on;
    include fastcgi_params;
    fastcgi_hide_header X-Powered-By;
}
```

**لا يوجد أي `proxy_set_header X-Forwarded-For`.** أي أن الترويسة تصل إلى Laravel **كما أرسلها العميل حرفياً**، وLaravel يثق بها.

### سيناريو استغلال عملي

`request()->ip()` هو مفتاح الـ throttle في Laravel. المسار `POST /api/auth/forgot-password` محمي بـ `throttle:5,1`:

```bash
# 5 طلبات فقط في الدقيقة... نظرياً
for i in $(seq 1 10000); do
  curl -s https://lookupfriseur.com/api/auth/forgot-password \
    -H "X-Forwarded-For: 1.2.3.$((RANDOM % 255))" \
    -H "Accept: application/json" \
    -d "email=victim@example.com" &
done
```

كل طلب يبدو قادماً من عنوان مختلف، فيحصل على دلو throttle جديد. **كل حدود المعدّل في المشروع تنهار دفعة واحدة:**

| الحماية | الحالة بعد التزوير |
|---|---|
| `throttle:5,1` على `forgot-password` | مُبطَلة |
| `throttle:10,1` على `verify-otp` و `reset-password` | مُبطَلة |
| `throttle:40,1` / `throttle:30,1` على مسارات التوفّر | مُبطَلة → إغراق قاعدة البيانات |
| `throttle:6,1` على إرسال OTP للهاتف | مُبطَلة → **استنزاف رصيد SMS** |

### الأثر على الموقع

كل جهد الحماية الذي بذلته في `routes/api.php` — وأنت كتبتَ هناك تعليقات مدروسة عن CGNAT وعن كون المفتاح هو الـ IP — **مُلغى بسطر واحد في `bootstrap/app.php`**.

وإضافة إلى ذلك: كل عناوين الـ IP في سجلاتك وتدقيقك مزوّرة، فلن تستطيع تتبّع مهاجم بعد وقوع الحادث.

### الإصلاح

بما أن nginx يعمل على نفس الجهاز:

```php
// bootstrap/app.php
$middleware->trustProxies(
    at: ['127.0.0.1', '::1'],
    headers: Illuminate\Http\Request::HEADER_X_FORWARDED_FOR
           | Illuminate\Http\Request::HEADER_X_FORWARDED_HOST
           | Illuminate\Http\Request::HEADER_X_FORWARDED_PORT
           | Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO
);
```

وفي nginx، اكتب الترويسة صراحةً بدل تمرير ما أرسله العميل:

```nginx
location ~ ^/index\.php(/|$) {
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    fastcgi_param HTTPS on;
    # ✅ امسح ما أرسله العميل واكتب العنوان الحقيقي
    fastcgi_param HTTP_X_FORWARDED_FOR $remote_addr;
    include fastcgi_params;
    fastcgi_hide_header X-Powered-By;
}
```

**إذا أضفت Cloudflare لاحقاً:** ضع نطاقات Cloudflare المعلنة في `at:` بدل `127.0.0.1`، ولا تعد أبداً إلى `'*'`.

### فكرة الإصلاح العميقة

`trustProxies('*')` خطأ في الإنتاج دائماً، بلا استثناء. المبدأ: **ترويسة `X-Forwarded-For` هي مُدخل مستخدم**، ومصدر الثقة يجب أن يكون قائمة صريحة قصيرة. تحديد «من أين يأتي عنوان العميل الحقيقي» قرار معماري يجب أن يكون موثّقاً ومقصوداً، لا افتراضاً افتراضياً.

---

## 🔴 CFG-03 — مسار `/internal/clear-cache` بلا أي مصادقة

**الموقع:** [`routes/web.php:175-199`](routes/web.php#L175-L199)
**الحالة:** ✅ مؤكد بالكود

```php
Route::get('/internal/clear-cache', function (Request $request) {
    $output = [];
    $exitCode = 0;

    exec('sudo /var/www/lookup.com/clear-my-cache.sh 2>&1', $output, $exitCode);

    if ($exitCode !== 0) {
        return response()->json([
            'success' => false,
            'message' => 'Cache clear failed',
            'output'  => $output,          // <-- مخرجات shell خام
        ], 500);
    }

    return response()->json(['success' => true, 'message' => 'Cache cleared successfully']);
});
```

**لا `middleware`. لا `auth`. لا فحص IP. لا throttle.** المسار خارج كل مجموعة حماية في الملف.

### تصحيح على التقرير السابق

التقرير القديم سمّى هذا **RCE**. هذا **ليس RCE** — السلسلة الممرَّرة إلى `exec()` ثابتة حرفياً ولا تحتوي أي جزء من مُدخل المستخدم. لكنه يبقى خطيراً لثلاثة أسباب حقيقية:

### سيناريو استغلال

```bash
# أي شخص على الإنترنت، من هاتفه:
while true; do curl -s https://lookupfriseur.com/internal/clear-cache & done
```

**1. حرمان من الخدمة (DoS).** كل طلب يُشغّل عملية shell جديدة عبر `sudo`. عشرات الطلبات المتوازية تستهلك سقف عمليات PHP-FPM (`pm.max_children`) وموارد المعالج → **الموقع كله يتوقف**: الحجز، اللوحة، الفواتير.

**2. تدمير الأداء المستمر.** كل نداء يمسح كاش الإعدادات والمسارات والقوالب. في الإنتاج تعتمد على `php artisan config:cache` و`route:cache`؛ ومسحها يعني أن **كل طلب لاحق يُعيد تحليل كل ملفات الإعداد والمسارات من الصفر** — تدهور أداء حاد يستمر إلى أن يُعاد بناء الكاش.

**3. تسريب معلومات النظام.** السطر 191 يُرجع `'output' => $output` عند الفشل — أي **مخرجات السكربت الخام**، بما فيها مسارات السيرفر الفعلية ورسائل `sudo`. استطلاع مجاني للمهاجم.

### الأثر على الموقع

زائر واحد يعرف الرابط يستطيع إسقاط الصالون عن الخدمة بالكامل بحلقة `curl` واحدة، بلا حساب وبلا أثر.

### الإصلاح

**الخيار الأفضل: احذفه.** هذا إجراء نشر (deployment)، لا ميزة تطبيق. مكانه سكربت النشر لديك، لا مسار HTTP عام.

إن كان لا بد من بقائه:

```php
Route::get('/internal/clear-cache', function (Request $request) {
    abort_unless(
        hash_equals(
            (string) config('app.internal_ops_token'),
            (string) $request->header('X-Ops-Token')
        ),
        404                                     // 404 لا 403 — لا تكشف وجود المسار
    );

    Artisan::call('optimize:clear');            // ✅ بلا exec وبلا sudo

    return response()->json(['success' => true]);
})->middleware('throttle:3,60');
```

### فكرة الإصلاح العميقة

المبدأ: **لا عمليات تشغيلية داخل مسارات HTTP للتطبيق.** تشغيل shell بصلاحيات `sudo` انطلاقاً من طلب ويب مجهول يخرق الحدّ الفاصل بين طبقة التطبيق وطبقة النظام — وهو الحدّ الذي يمنع خطأً في التطبيق من التحوّل إلى اختراق للسيرفر. `Artisan::call()` يبقى داخل عملية PHP نفسها ولا يحتاج أي امتياز إضافي.

---

## 🟠 CFG-04 — بوابة SMS مفتوحة للعامة تستنزف رصيدك

**الموقع:** [`routes/api.php:73-105`](routes/api.php#L73-L105)
**الحالة:** ✅ مؤكد بالكود

```php
Route::post('/test/vonage-sms', function (Request $request, VonageSdkSmsService $sms) {
    // abort_unless(app()->environment('local') || config('app.debug'), 404);   <-- مُعلَّق!

    $payload = $request->validate([
        'phone' => ['required', 'string', 'max:20'],
        'text'  => ['required', 'string', 'max:1000'],
    ]);

    $result = $sms->send($payload['phone'], $payload['text']);
    // ...
    return response()->json([
        'success'           => true,
        'remaining_balance' => $result['remaining_balance'],   // <-- يكشف رصيدك
    ]);
});
```

سطر الحماية الوحيد **مُعلَّق**. لا auth، ولا throttle — و`CFG-01` أزال السقف العام أيضاً.

### سيناريو استغلال

```bash
curl -X POST https://lookupfriseur.com/api/test/vonage-sms \
  -H "Accept: application/json" \
  -d "phone=+491701234567" \
  -d "text=عرض خاص! اضغط هنا: http://evil.example"
```

المهاجم يملك الآن **بوابة SMS مجانية غير محدودة على حسابك في Vonage**:

- إرسال آلاف الرسائل حتى ينفد رصيدك — وقد يستهدف أرقاماً دولية باهظة عمداً.
- إرسال رسائل تصيّد **باسم المُرسل `BarberBooking`** (من `VONAGE_FROM` في `.env`) — أي انتحال مباشر لهوية علامتك التجارية أمام عملائك.
- السطر 103 يُرجع `remaining_balance`، فيرى المهاجم كم بقي لديك ويعرف متى ينجح في استنزافه.

### الأثر على الموقع

خسارة مالية مباشرة، واحتمال حظر حسابك لدى Vonage بتهمة إساءة الاستخدام، و**مسؤولية قانونية** إذا استُخدم اسم علامتك في حملة تصيّد ضد أشخاص لا علاقة لهم بك.

### الإصلاح

```php
// الأفضل: احذف المسار من الإنتاج تماماً.
// إن أردت الإبقاء عليه للتطوير المحلي فقط:
if (app()->environment('local')) {
    Route::post('/test/vonage-sms', /* ... */)->middleware('throttle:3,60');
}
```

**لا تُعِد تفعيل الشرط المُعلَّق كما هو** — لأنه يعتمد على `config('app.debug')`، وهذا خطأ بحدّ ذاته (انظر `CFG-05`).

---

## 🟠 CFG-05 — `config('app.debug')` مستخدَم كبوابة أمان

**الموقع:** `AuthController:55,179` · `OtpController:50,153` · `PasswordResetController:203` · `PhoneVerificationController:108` · `routes/api.php:111` · و11 موضعاً إضافياً لكشف رسائل الاستثناء
**الحالة:** ✅ مؤكد بالكود

### الشرح

```php
// app/Http/Controllers/Api/AuthController.php:55
if (config('app.debug')) {
    $response['otp'] = $otp;      // رمز التحقق في جسم الاستجابة!
}
```

أنت أكّدت أن `APP_DEBUG=false` في الإنتاج، وهذا يُخفّف الخطورة كثيراً. لكن تبقى ثلاث مشاكل حقيقية:

**1. المسافة بين الأمان والكارثة = متغيّر بيئة واحد.**

أي مطوّر يُشغّل `APP_DEBUG=true` مؤقتاً لتشخيص مشكلة في الإنتاج — وهذا يحدث كثيراً — يفتح فوراً باب **الاستيلاء على كل حساب في النظام**:

```bash
curl -X POST https://lookupfriseur.com/api/auth/forgot-password \
     -H "Accept: application/json" -d "email=victim@example.com"

# الاستجابة تحتوي: {"otp": "483920"}   ← رمز إعادة تعيين كلمة مرور الضحية
```

**2. مسار واحد ليس محمياً بـ `APP_DEBUG` أصلاً — وسيُفعَّل في الإنتاج:**

```php
// app/Http/Controllers/Api/PhoneVerificationController.php:108
if (!$smsEnabled || config('app.debug')) {
    // يُرجع الـ OTP في الاستجابة
}
```

الشرط `!$smsEnabled` يعني: **إذا تعطّل إعداد الـ SMS، أو نفد رصيد Vonage، أو تغيّر `SMS_DRIVER` بالخطأ — يبدأ النظام بإرجاع رموز التحقق نصاً صريحاً في استجابة HTTP، حتى مع `APP_DEBUG=false`.** وهذا ليس سيناريو نظرياً: نفاد رصيد الـ SMS وضع فشل متوقّع تماماً، وقد يُسبّبه مهاجم عمداً عبر `CFG-04`.

**3. تسريب داخلي في 11 موضعاً:** النمط `'error' => config('app.debug') ? $e->getMessage() : null` سلوك مقبول نسبياً، لكن الأخطاء لا تُسجَّل عبر `report($e)` — أي أنك في الإنتاج **لن ترى الخطأ إطلاقاً**، لا في الاستجابة ولا في السجل.

### الإصلاح

```php
// ❌ احذف نهائياً من AuthController / OtpController / PasswordResetController
if (config('app.debug')) { $response['otp'] = $otp; }

// ✅ إن احتجتها للتطوير المحلي فقط — اشترط البيئة والعنوان معاً
if (app()->environment('local') && $request->ip() === '127.0.0.1') {
    $response['otp'] = $otp;
}
```

```php
// PhoneVerificationController.php:108 — أزل شرط !$smsEnabled تماماً
if (app()->environment('local')) {
    $payload['otp'] = $otp;
}

// وإذا فشل إرسال الـ SMS فعلياً، ارفض العملية بصراحة:
return response()->json([
    'message' => __('auth.sms_unavailable'),
], 503);
```

```php
// في كل catch: سجّل دائماً، واكشف بشرط البيئة
} catch (\Throwable $e) {
    report($e);                                   // ✅ يصل إلى السجل دائماً
    return response()->json([
        'message' => __('errors.server_error'),
    ], 500);
}
```

### فكرة الإصلاح العميقة

**`app.debug` مفتاح تشخيص، لا بوابة أمان.** بوابات الأمان يجب أن تعتمد على `app()->environment()` لأنه ثابت لكل بيئة نشر ولا يُقلَّب أثناء التشخيص.

والمبدأ الأهم: **مسار الفشل يجب أن يكون آمناً (fail-closed).** عندما تتعطّل خدمة الـ SMS، الجواب الصحيح هو رفض العملية بـ 503 — لا كشف السر الذي كان من المفترض أن تحمله تلك الخدمة.

---

## 🟡 CFG-06 — مسار `/test` يكشف إعدادات النظام لأي زائر

**الموقع:** [`routes/web.php:103-123`](routes/web.php#L103-L123)
**الحالة:** ✅ مؤكد بالكود

```php
Route::get('/test', function () {
    $LineTypeRegistry = app(\App\Services\InvoiceTemplate\LineTypeRegistry::class);
    dd($LineTypeRegistry->getGroupedOptionsForSelect());
    // ...
});
```

`dd()` في مسار عام بلا مصادقة. مع `APP_DEBUG=false` يُعرض ناتج `dd()` بشكل مبسّط، لكنه يبقى يكشف **البنية الداخلية الكاملة لنظام قوالب الفواتير** لأي زائر: أسماء كل أنواع الأسطر، تجميعها، ومفاتيح الترجمة.

هذا استطلاع مفيد لمهاجم يريد فهم كيف تُبنى فواتيرك.

**الإصلاح:** احذف المسار. هو بقايا تطوير لا وظيفة له في الإنتاج.

---

## 🟡 CFG-07 — ترويسات أمان ناقصة في nginx

**الموقع:** [`site/sites-enabled/lookup.com:48-53`](site/sites-enabled/lookup.com)
**الحالة:** ✅ مؤكد بإعداد الإنتاج الخاص بك

الترويسات الموجودة جيدة (`X-Frame-Options`، `X-Content-Type-Options`، `Referrer-Policy`)، لكن ينقص:

**1. HSTS معطّل** (السطر 53 مُعلَّق). بدونه يبقى هجوم SSL-stripping ممكناً على أول زيارة.

**2. `server_tokens off` مُعلَّق** في [`site/nginx.conf:21`](site/nginx.conf) → nginx يكشف رقم إصداره في كل استجابة، ما يسهّل استهداف ثغرات معروفة.

**3. لا يوجد `Content-Security-Policy`** — مهم بشكل خاص لأن لوحة الموظفين تعرض بيانات عملاء ومبالغ.

```nginx
# بعد التأكد من أن كل النطاقات الفرعية تعمل على HTTPS:
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
add_header X-Permitted-Cross-Domain-Policies "none" always;
```

```nginx
# site/nginx.conf — ألغِ التعليق
server_tokens off;
```

---

## 🔵 CFG-08 — `SANCTUM_TOKEN_PREFIX` فارغ

**الموقع:** [`config/sanctum.php:65`](config/sanctum.php#L65) · `'token_prefix' => env('SANCTUM_TOKEN_PREFIX', '')`

بدون بادئة مميّزة، لا تستطيع أدوات فحص الأسرار (GitHub secret scanning، GitGuardian) التعرّف على توكن مسرَّب في مستودع أو سجل. اضبط:

```env
SANCTUM_TOKEN_PREFIX=lookup_
```

---

# 3. طبقة المصادقة والجلسات

## 🔴 AUTH-01 — لا يوجد أي rate limit على تسجيل الدخول والتسجيل وتحقق الـ OTP

**الموقع:** [`routes/api.php:40-42, 57-58, 69-70`](routes/api.php#L40-L42)
**الحالة:** ✅ مؤكد بالكود + مُثبت بفحص مجموعة الـ middleware

### الشرح

```php
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);          // بلا throttle
    Route::post('login',    [AuthController::class, 'login']);             // بلا throttle
    Route::post('refresh',  [AuthController::class, 'refresh']);           // بلا throttle

    // هذه محمية — أحسنت:
    Route::post('forgot-password', ...)->middleware('throttle:5,1');
    Route::post('password/verify-otp', ...)->middleware('throttle:10,1');
    Route::post('reset-password', ...)->middleware('throttle:10,1');

    Route::post('request-otp', [OtpController::class, 'requestOtp']);      // بلا throttle
    Route::post('verify-otp',  [OtpController::class, 'verifyOtp']);       // بلا throttle

    Route::post('verify-email-otp',        [OtpController::class, 'verifyEmailViaOtp']);        // بلا throttle
    Route::post('resend-verification-otp', [OtpController::class, 'resendVerificationOtp']);    // بلا throttle
});
```

**الملاحظة الحاسمة:** كنتَ ستكون محمياً جزئياً بـ `throttle:api` من مجموعة الـ middleware الافتراضية — لكن `CFG-01` أزالها. النتيجة: **هذه المسارات بلا أي سقف على الإطلاق، لا على مستوى المسار ولا على مستوى المجموعة.**

### سيناريو استغلال 1 — حشو بيانات الاعتماد

```bash
# قائمة كلمات مرور مسرَّبة، بلا أي مقاومة
while read pass; do
  curl -s -X POST https://lookupfriseur.com/api/auth/login \
    -H "Accept: application/json" \
    -d "registration_method=email&email=victim@example.com&password=$pass" \
    | grep -q '"token"' && echo "FOUND: $pass"
done < rockyou.txt
```

بسرعة 500 محاولة/ثانية (وهي معقولة جداً عبر شبكة سريعة)، قائمة من مليون كلمة مرور تُستنفَد في **أقل من ساعة**.

### سيناريو استغلال 2 — تخمين الـ OTP (الأخطر)

هنا تتضافر أربع ثغرات مؤكدة لتنتج استيلاءً كاملاً على الحساب:

```
AUTH-01  لا سقف على /api/auth/verify-otp
   +
CFG-02   حتى لو أضفت سقفاً، تزوير X-Forwarded-For يُبطله
   +
AUTH-04  increment('attempts') غير ذرّي  ->  عدّاد المحاولات يتخلّف تحت التوازي
   +
AUTH-03  الـ OTP نصّي صريح، 6 أرقام، مساحة 1,000,000 فقط
```

```bash
# 1) اطلب OTP للضحية
curl -X POST https://lookupfriseur.com/api/auth/request-otp \
     -H "Accept: application/json" -d "email=victim@example.com"

# 2) خمّن كل الاحتمالات بالتوازي — العدّاد لا يلحق
seq -w 000000 999999 | xargs -P 200 -I{} \
  curl -s -X POST https://lookupfriseur.com/api/auth/verify-otp \
       -H "Accept: application/json" \
       -d "email=victim@example.com&otp={}" \
  | grep -l '"success":true'
```

سقف `max_attempts = 5` **لا يحمي هنا** لأنه يُطبَّق عبر `$record->increment('attempts')` — قراءة ثم كتابة بلا قفل. مع 200 طلب متوازٍ، تُقرأ القيمة `0` في مئات الطلبات قبل أن تُكتب أي زيادة. انظر `AUTH-04`.

### الأثر على الموقع

**استيلاء كامل على أي حساب** — بما فيه حسابات الموظفين والمدير، إن كانوا يسجّلون دخولاً عبر الـ API. ثم يفتح ذلك الطريق إلى بيانات كل العملاء وتحصيل الأموال.

### الإصلاح

**الطبقة 1 — سقوف على مستوى المسار:**

```php
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])
        ->middleware('throttle:5,60');           // 5 حسابات/ساعة لكل IP

    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1');

    Route::post('refresh', [AuthController::class, 'refresh'])
        ->middleware('throttle:20,1');

    Route::post('request-otp', [OtpController::class, 'requestOtp'])
        ->middleware('throttle:3,1');

    Route::post('verify-otp', [OtpController::class, 'verifyOtp'])
        ->middleware('throttle:10,1');

    Route::post('resend-verification-otp', [OtpController::class, 'resendVerificationOtp'])
        ->middleware('throttle:3,1');
});
```

**الطبقة 2 — سقف مُركَّب على الحساب لا على الـ IP فقط** (يقاوم تزوير الـ IP):

```php
// app/Providers/AppServiceProvider.php  ->  boot()
RateLimiter::for('login', function (Request $request) {
    return [
        Limit::perMinute(10)->by($request->ip()),
        // ✅ هذا الحد لا يمكن تجاوزه بتزوير الـ IP
        Limit::perMinute(5)->by('acct:' . strtolower(
            (string) ($request->input('email') ?? $request->input('phone'))
        )),
    ];
});
```

```php
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
```

**الطبقة 3:** أصلح `CFG-01` (سقف عام) و`CFG-02` (ثقة البروكسي) وإلا بقيت كل الطبقات أعلاه قابلة للتجاوز.

### فكرة الإصلاح العميقة

**الحدّ القائم على الـ IP وحده لا يكفي أبداً لنقاط المصادقة.** المهاجم يتحكم بعنوانه (خصوصاً مع `CFG-02`)، لكنه **لا يتحكم بالحساب الذي يهاجمه**. لذلك يجب أن يكون هناك دائماً بُعد ثانٍ للحدّ مبني على المُعرِّف المستهدف (البريد/الهاتف/معرّف الـ OTP). هذا هو الفرق بين حماية شكلية وحماية حقيقية.

---

## 🔴 AUTH-02 — تجاوز حدّ محاولات الـ OTP عبر التوازي

**الموقع:** [`app/Services/OtpService.php:73-84`](app/Services/OtpService.php#L73-L84)
**الحالة:** ✅ مؤكد بالكود

```php
$maxAttempts = max(1, (int) config('otp.max_attempts', 5));

if ($record->attempts >= $maxAttempts) {     // (1) قراءة
    // رفض
}

if (! $isValid) {
    $record->increment('attempts');          // (2) كتابة — لا قفل بين (1) و (2)

    if ($record->attempts >= $maxAttempts) {
        // حرق الرمز
    }
}
```

### الشرح

النمط `قراءة ← فحص ← كتابة` بلا قفل صفّي (`lockForUpdate`) وبلا معاملة. عند وصول 200 طلب متوازٍ:

```
الطلب  #1 يقرأ attempts=0  ->  يمر الفحص
الطلب  #2 يقرأ attempts=0  ->  يمر الفحص
...
الطلب #200 يقرأ attempts=0  ->  يمر الفحص
              (كلها قرأت قبل أن تُكتب أي زيادة)
```

النتيجة: **200 تخمين بدل 5**. وبتكرار الدفعات، تُستنفَد مساحة الـ 1,000,000 احتمال بالكامل.

### الإصلاح

```php
public function verify(string $target, string $code, OtpPurpose $purpose): bool
{
    return DB::transaction(function () use ($target, $code, $purpose) {

        $record = Otp::query()
            ->where(/* ... نفس شروط البحث ... */)
            ->lockForUpdate()                 // ✅ قفل صفّي — يُسلسِل الطلبات المتوازية
            ->first();

        if (! $record) {
            return false;
        }

        $maxAttempts = max(1, (int) config('otp.max_attempts', 5));

        if ($record->attempts >= $maxAttempts) {
            return false;
        }

        // ✅ زِد العدّاد أولاً — حتى لو انهار الطلب، المحاولة محسوبة
        $record->increment('attempts');

        if (! hash_equals($record->otp, $code)) {
            if ($record->attempts >= $maxAttempts) {
                $record->update(['used' => true]);
            }
            return false;
        }

        $record->update(['used' => true]);

        return true;
    });
}
```

نقطتان مهمتان في هذا الإصلاح:
1. **`lockForUpdate()` داخل معاملة** — يُسلسِل الطلبات المتزامنة على نفس الصف.
2. **زيادة العدّاد قبل المقارنة** — حتى لو انقطع الطلب بعد الفحص، تُحتسب المحاولة (fail-closed).

---

## 🟠 AUTH-03 — الـ OTP مخزَّن نصاً صريحاً بلا تجزئة

**الموقع:** [`app/Models/Otp.php:11-21`](app/Models/Otp.php#L11-L21)
**الحالة:** ✅ **مؤكد بقاعدة البيانات الحيّة**

```sql
SHOW COLUMNS FROM otps;
-- otp | varchar(255) | NO | ... |      <-- نص صريح، بلا تجزئة
```

```php
protected $fillable = ['email', 'phone', 'otp', 'expires_at', 'device', 'type', 'purpose', 'attempts', 'used'];
// لا يوجد $hidden       <-- الرمز قد يتسرب في أي تسلسل JSON للنموذج
```

### الشرح والأثر

كل رمز تحقق نشط — لتأكيد الحساب، ولإعادة تعيين كلمة المرور، ولتأكيد رقم الهاتف — مخزَّن **مقروءاً** في قاعدة البيانات:

- أي وصول للقراءة على قاعدة البيانات (نسخة احتياطية مسرَّبة، حساب DB بصلاحيات زائدة، ثغرة SQL مستقبلية، أو حتى موظف لديه وصول phpMyAdmin) = **استيلاء فوري على أي حساب** بلا حاجة لكسر كلمة مرور.
- غياب `$hidden` يعني أن أي `return $otp;` أو `toArray()` مستقبلي سيسرّب الرمز في استجابة HTTP.

بالمقارنة: كلمات المرور مجزّأة بشكل صحيح (`'password' => 'hashed'` في `User::casts()`). لكن الـ OTP — وهو **مكافئ لكلمة المرور من ناحية القوة** لأنه يمنح وصولاً كاملاً — غير مجزّأ.

### الإصلاح

```php
// app/Models/Otp.php
protected $hidden = ['otp'];

// عند الإنشاء (OtpService::generate)
$plain = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

Otp::create([
    'email'      => $email,
    'otp'        => hash('sha256', $plain),   // ✅ خزّن التجزئة فقط
    'expires_at' => now()->addMinutes(10),
    // ...
]);

return $plain;                                 // أرسل النص الصريح، ولا تخزّنه
```

```php
// عند التحقق (OtpService::verify)
if (! hash_equals($record->otp, hash('sha256', $code))) {   // ✅ مقارنة ثابتة الزمن
    // ...
}
```

**ملاحظة على `hash_equals`:** استخدمها دائماً بدل `===` عند مقارنة الأسرار — فهي تمنع هجمات التوقيت (timing attacks) التي تستنتج الرمز حرفاً بحرف من فروق زمن الاستجابة.

### فكرة الإصلاح العميقة

**الـ OTP سرّ مصادقة كامل، ويستحق نفس معاملة كلمة المرور.** استخدم `sha256` لا `bcrypt` هنا لأن الرمز قصير العمر (10 دقائق) ومساحته صغيرة أصلاً، فالبطء المتعمّد لا يضيف حماية تُذكر ويضرّ بالأداء. الحماية الحقيقية تأتي من: التجزئة + سقف المحاولات الذرّي (`AUTH-02`) + عمر قصير + سقف معدّل (`AUTH-01`).

---

## 🟠 AUTH-04 — لا تدوير لتوكن التحديث ولا كشف لإعادة الاستخدام

**الموقع:** [`app/Services/AuthTokenService.php:24-69`](app/Services/AuthTokenService.php#L24-L69)
**الحالة:** ✅ مؤكد بالكود

### الشرح

توكن الوصول ينتهي خلال **15 دقيقة** (وهذا ممتاز — وهو ما يجعل ادعاء التقرير القديم بأن التوكن أبدي **خاطئاً**). لكن توكن التحديث يعيش **30 يوماً** و:

- **لا يُدوَّر عند الاستخدام** — نفس التوكن صالح 30 يوماً كاملة مهما استُخدم.
- **لا كشف لإعادة الاستخدام** — لو سُرق، يستطيع المهاجم والمستخدم الشرعي استعماله معاً بلا أن يلاحظ النظام شيئاً.

النتيجة: عمر التوكن القصير (15 دقيقة) **لا يشتري أي أمان فعلي**، لأن من يملك توكن التحديث يُجدّد إلى الأبد.

### مشكلة ثانية — `hash('sha256', $plain . config('app.key'))`

```php
$hashed = hash('sha256', $plain . config('app.key'));
```

خلط `APP_KEY` في التجزئة يعني: **تدوير `APP_KEY` (وهو إجراء أمني موصى به بعد أي حادث) يُبطل صامتاً كل توكنات التحديث** ويُخرج كل المستخدمين. الأسوأ أنه لا يضيف أماناً — التوكن أصلاً 64 حرفاً عشوائياً، والتجزئة المجرّدة كافية.

### مشكلة ثالثة — تغيير كلمة المرور لا يُبطل الجلسات

```php
// app/Http/Controllers/Api/ProfileController.php:69-80
$user->password = bcrypt($request->password ?? "");
$user->save();
// لا $user->tokens()->delete();  ولا إبطال لتوكنات التحديث
```

إذا سُرق حساب، **تغيير كلمة المرور لا يطرد المهاجم**. يبقى توكن وصوله وتوكن تحديثه صالحين. وهذا يُبطل الإجراء الأول الذي يتخذه أي مستخدم عند الاشتباه بالاختراق.

### الإصلاح

```php
// AuthController::refresh — دوّر التوكن واكشف إعادة الاستخدام
public function refresh(Request $request)
{
    $request->validate(['refresh_token' => 'required|string']);
    $plain = $request->input('refresh_token');

    $token = $this->tokenService->findValidRefreshToken($plain);

    if (! $token) {
        // ✅ كشف إعادة الاستخدام: التوكن غير صالح لكنه كان موجوداً وأُبطل؟
        //    هذا يعني تسريباً -> أبطل كل جلسات هذا المستخدم فوراً.
        $revoked = RefreshToken::where('token_hash', hash('sha256', $plain))->first();

        if ($revoked) {
            Log::warning('Refresh token reuse detected', ['user_id' => $revoked->user_id]);
            RefreshToken::where('user_id', $revoked->user_id)->update(['revoked' => true]);
            User::find($revoked->user_id)?->tokens()->delete();
        }

        return response()->json(['message' => 'Invalid refresh token'], 401);
    }

    $user = $token->user;

    // ✅ التدوير: أبطل القديم وأصدر جديداً في كل مرة
    $token->update(['revoked' => true]);

    return response()->json([
        'access_token'  => $this->tokenService->createAccessToken($user)['access_token'],
        'refresh_token' => $this->tokenService->createRefreshToken($user)['refresh_token'],
    ]);
}
```

```php
// AuthTokenService — أزل APP_KEY من التجزئة
$hashed = hash('sha256', $plain);
```

```php
// ProfileController::changePassword — بعد الحفظ
$user->password = bcrypt($request->password);
$user->save();

// ✅ اطرد كل الجلسات الأخرى
$currentTokenId = $request->user()->currentAccessToken()?->id;
$user->tokens()->where('id', '!=', $currentTokenId)->delete();
RefreshToken::where('user_id', $user->id)->update(['revoked' => true]);
```

### فكرة الإصلاح العميقة

نمط **Refresh Token Rotation with Reuse Detection** هو المعيار الصناعي (OAuth 2.1 BCP). فكرته: التوكن يُستخدم **مرة واحدة فقط**؛ فإذا وصل توكن مُبطَل، فهذا دليل قاطع على وجود نسخة مسروقة — والرد الصحيح هو إبطال كل جلسات ذلك المستخدم فوراً. بهذا يتحوّل التسريب من «وصول دائم صامت» إلى «حادث مكشوف يُغلق نفسه».

---

## 🟡 AUTH-05 — `logout` يطرد المستخدم من كل أجهزته

**الموقع:** `app/Http/Controllers/Api/AuthController.php` (دالة `logout`)

إذا كانت الدالة تستخدم `$user->tokens()->delete()`، فتسجيل الخروج من الهاتف يطرد المستخدم من الجهاز اللوحي والويب أيضاً. الصواب:

```php
$request->user()->currentAccessToken()->delete();
```

مع إبقاء `logout-all` كإجراء صريح منفصل إن أردته.

---

## 🟡 AUTH-06 — رفع صورة الملف الشخصي بلا قيود كافية

**الموقع:** [`app/Http/Controllers/Api/ProfileController.php:34`](app/Http/Controllers/Api/ProfileController.php#L34)

```php
'image' => 'sometimes|image|max:2048',
```

- **لا `mimes:`** — قاعدة `image` تعتمد على استنتاج نوع الملف، وهو أوسع مما يجب.
- **لا `dimensions:`** — ملف بحجم 2MB يمكن أن يكون صورة 20000×20000 بكسل. عندما تعالجها `intervention/image` تحتاج ~1.2GB من الذاكرة → **انهيار عملية PHP (decompression bomb)**.

```php
'image' => [
    'sometimes',
    'image',
    'mimes:jpeg,jpg,png,webp',
    'max:2048',
    'dimensions:max_width=4000,max_height=4000',
],
```

---

## 🟡 AUTH-07 — تغيير رقم الهاتف بلا تحقق من الصيغة

**الموقع:** [`app/Http/Controllers/Api/ProfileController.php:31`](app/Http/Controllers/Api/ProfileController.php#L31)

```php
'phone' => ['sometimes', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user->id)],
```

لا تحقق من صيغة الرقم. **نقطة إيجابية مهمة:** الكود يُبطل التحقق السابق عند التغيير (السطر 51: `$user->phone_verified_at = null`) — وهذا صحيح تماماً ويمنع سيناريو تثبيت رقم مهاجم.

يبقى تحسين الصيغة:

```php
'phone' => [
    'sometimes', 'string', 'max:20',
    'regex:/^\+[1-9]\d{7,14}$/',                  // E.164
    Rule::unique('users', 'phone')->ignore($user->id),
],
```

---

# 4. طبقة التخويل (Authorization) و IDOR

## 🔴 AUTHZ-01 — موظف مفصول يحتفظ بالوصول الكامل للوحة

**الموقع:** [`app/Http/Middleware/EnsureStaffDashboardAccess.php:11-24`](app/Http/Middleware/EnsureStaffDashboardAccess.php#L11-L24)
**الحالة:** ✅ مؤكد بالكود — **هذه أخطر ثغرة تخويل في المشروع**

### الشرح

```php
class EnsureStaffDashboardAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $auth = filament()->auth();

        if (! $auth->check()) {
            return redirect()->route('filament.admin.auth.login');
        }

        if (! $auth->user()->can('StaffDashboard:access')) {
            abort(403);
        }

        return $next($request);          // <-- لا فحص is_active أبداً
    }
}
```

قارن ذلك بالحماية الصحيحة في نموذج المستخدم:

```php
// app/Models/User.php:75
public function canAccessPanel(Panel $panel): bool
{
    return $this->hasAnyRole(['SuperAdmin', 'admin', 'manager', 'provider'])
        && $this->is_active;             // ✅ الفحص موجود هنا
}
```

**المشكلة الجوهرية:** لوحة الموظفين ليست مسار لوحة Filament. هي مجموعة مسارات ويب عادية في [`routes/web.php:39-72`](routes/web.php#L39-L72) محمية بـ `EnsureStaffDashboardAccess` فقط. لذلك **`canAccessPanel()` لا تُستدعى إطلاقاً** على هذه المسارات، وفحص `is_active` لا يحدث.

### سيناريو استغلال

1. تفصل موظفاً. تدخل إلى `/admin/users` وتضبط `is_active = false`. تتنفّس الصعداء.
2. الموظف يُحاول `/admin` → **يُرفض** (لأن `canAccessPanel` تفحص `is_active`). يبدو أن الأمر نجح.
3. الموظف يفتح `https://dashboard.lookupfriseur.com/` → **يدخل بنجاح كامل**، بجلسته القديمة أو بتسجيل دخول جديد.

جلسته لا تزال صالحة لمدة `SESSION_LIFETIME=120` دقيقة، وتسجيل الدخول الجديد يمر أيضاً لأن `EnsureStaffDashboardAccess` لا يفحص `is_active`.

**ما يستطيع فعله بعد الدخول:**

| الوصول | الملف |
|---|---|
| عرض كل مواعيد اليوم وبيانات كل العملاء (اسم، هاتف، بريد) | `StaffDashboard` |
| **البحث في قاعدة عملائك كاملة** وعرض تاريخ كل عميل | `CustomerLookup` — وهي بلا صلاحيات أصلاً، انظر `AUTHZ-02` |
| **تحصيل الأموال وإنهاء الفواتير بمبالغ يحددها هو** | `processPayment` + `MON-02` |
| حذف مواعيد وإلغاؤها | `deleteAppointment` |
| عرض تقارير الإيرادات اليومية | `StaffStats` / `StaffReports` |

### الأثر على الموقع

هذا **مانع إطلاق مطلق**. لا تملك اليوم أي وسيلة موثوقة لسحب وصول موظف. تعطيل الحساب من واجهة الإدارة يعطي **شعوراً كاذباً بالأمان** — وهذا أخطر من عدم وجود الميزة أصلاً، لأنك ستظن أنك محمي. أضِف إلى ذلك: تسريب بيانات العملاء من موظف سابق هو خرق GDPR مباشر بغرامات تصل إلى 4% من الإيراد السنوي.

### الإصلاح

```php
// app/Http/Middleware/EnsureStaffDashboardAccess.php
class EnsureStaffDashboardAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $auth = filament()->auth();

        if (! $auth->check()) {
            return redirect()->route('filament.admin.auth.login');
        }

        $user = $auth->user();

        // ✅ الحساب المعطّل يُطرد فوراً — تُدمَّر الجلسة ولا يكتفى بالرفض
        if (! $user->is_active) {
            $auth->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('filament.admin.auth.login')
                ->withErrors(['email' => __('auth.account_disabled')]);
        }

        // ✅ الأدوار المسموح لها بدخول أسطح الموظفين
        if (! $user->hasAnyRole(['SuperAdmin', 'admin', 'manager', 'provider'])) {
            abort(403);
        }

        if (! $user->can('StaffDashboard:access')) {
            abort(403);
        }

        return $next($request);
    }
}
```

**خطوة إضافية ضرورية:** تدمير جلسات الموظف عند تعطيله، وإلا بقيت جلسته الحالية حيّة حتى انتهاء الـ 120 دقيقة:

```php
// app/Observers/UserObserver.php
public function updated(User $user): void
{
    if ($user->wasChanged('is_active') && ! $user->is_active) {
        // توكنات الـ API
        $user->tokens()->delete();
        RefreshToken::where('user_id', $user->id)->update(['revoked' => true]);

        // جلسات الويب (SESSION_DRIVER=database عندك، فهذا يعمل)
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }
}
```

### فكرة الإصلاح العميقة

المشكلة الجذرية: **يوجد فحصان مختلفان لنفس السؤال «هل يحق لهذا الموظف الدخول؟»** — واحد في `canAccessPanel()` وآخر في `EnsureStaffDashboardAccess`، وهما غير متطابقين. هذا نمط سيتكرّر كلما أُضيف سطح موظفين جديد.

الحل المعماري: **مصدر واحد للحقيقة**.

```php
// app/Models/User.php
public function isActiveStaff(): bool
{
    return $this->is_active
        && $this->hasAnyRole(['SuperAdmin', 'admin', 'manager', 'provider']);
}

public function canAccessPanel(Panel $panel): bool
{
    return $this->isActiveStaff();               // ✅ يستدعي نفس المصدر
}
```

ثم استدعِ `isActiveStaff()` من الـ middleware أيضاً. أي سطح موظفين مستقبلي يستدعي نفس الدالة، فيستحيل أن ينحرف عن القاعدة.

---

## 🔴 AUTHZ-02 — `CustomerLookup` بلا أي فحص صلاحيات

**الموقع:** [`app/Livewire/CustomerLookup.php`](app/Livewire/CustomerLookup.php) (الملف كاملاً — 207 سطراً)
**الحالة:** ✅ مؤكد بالكود (فحصتُ الملف كاملاً بحثاً عن أي حارس)

### الشرح

المكوّن **لا يستخدم `InteractsWithDashboardPermissions`**، ولا يحتوي على أي `dashCan` أو `dashDeny` أو `abort` أو `authorize`. الحارس الوحيد هو `StaffDashboard:access` على مستوى المسار — وهي الصلاحية التي **يملكها كل من يدخل اللوحة، بمن فيهم أصغر مزوّد خدمة**.

قارن بـ `StaffDashboard` الذي بُني بعناية حقيقية:

```php
// StaffDashboard.php — نمط الحماية الصحيح، مطبَّق في كل إجراء
if ($this->dashDenyOnAppointment('take_payment', $appointment)) return;
if ($this->dashDeny('manage_timeoff')) return;
```

بينما `CustomerLookup`:

```php
public function performSearch(): void
{
    $q = trim($this->search);

    if (mb_strlen($q) < 2) { /* رسالة خطأ */ return; }

    // لا فحص صلاحيات — مباشرة إلى بيانات كل العملاء
    $registered = $this->lookupService->searchRegisteredCustomers($q, 25);
    $guests     = $this->lookupService->searchGuestAppointments($q, 50);
}

public function viewAppointment(int $appointmentId): void
{
    // لا canActOnAppointment — أي مزوّد يفتح حجز أي زميل له
}
```

### سيناريو استغلال

مزوّد خدمة (حلاق) يفتح `https://dashboard.lookupfriseur.com/customers`:

1. يكتب `a` — مرفوض (حد الحرفين).
2. يكتب `an` — يحصل على **25 عميلاً مسجّلاً** بأسمائهم وبريدهم وهواتفهم، و**50 حجز زائر** ببياناتهم.
3. يكرّر بـ `ab`, `ac`, `ad`... ثم `01`, `02`... (للهواتف) ثم `@gm`, `@ya`... (للبريد).
4. خلال دقائق، **يستخرج قاعدة عملائك كاملة** — أسماء وهواتف وبريد وتاريخ مواعيد.

وسبب سهولة ذلك: `CustomerLookupService` يبحث بـ `LIKE "%{$q}%"` على `first_name` و`last_name` و`email` و`phone` معاً:

```php
// app/Services/CustomerLookupService.php:15-20
$query->where('first_name', 'like', "%{$q}%")
      ->orWhere('last_name', 'like', "%{$q}%")
      ->orWhere('email',     'like', "%{$q}%")
      ->orWhere('phone',     'like', "%{$q}%");
```

**ملاحظة إضافية:** الرموز `%` و`_` غير مهرَّبة. البحث بـ `%` وحده يطابق كل شيء — لكن حد الحرفين وسقف 25/50 يحدّان من الضرر. لذلك أصنّفها ثانوية داخل هذه الثغرة لا ثغرة مستقلة.

### الأثر على الموقع

**خرق GDPR مباشر.** قاعدة بيانات عملاء صالون كاملة (أسماء + هواتف + بريد + تاريخ زيارات) قابلة للاستخراج من قِبل أي موظف — **ومن أي موظف سابق** أيضاً بفضل `AUTHZ-01`. هذه بيانات ذات قيمة تجارية مباشرة للمنافسين.

### الإصلاح

```php
// app/Livewire/CustomerLookup.php
use App\Livewire\Concerns\InteractsWithDashboardPermissions;

class CustomerLookup extends Component
{
    use InteractsWithDashboardPermissions;

    public function mount(): void
    {
        // ✅ حارس عند الدخول
        abort_unless($this->dashCan('view_customers'), 403);
    }

    public function performSearch(): void
    {
        if ($this->dashDeny('view_customers')) return;      // ✅ حارس عند كل إجراء

        $q = trim($this->search);

        if (mb_strlen($q) < 3) {                            // ✅ 3 أحرف بدل 2
            $this->dispatch('notify', type: 'error', message: __('dashboard.customer_lookup.min_chars'));
            return;
        }

        // ✅ سقف معدّل لكل مستخدم — يمنع الاستخراج المنهجي
        $key = 'customer-lookup:' . auth()->id();

        if (! RateLimiter::attempt($key, 20, fn () => true, 60)) {
            $this->dispatch('notify', type: 'error', message: __('dashboard.rate_limited'));
            return;
        }

        // ... باقي البحث
    }

    public function viewAppointment(int $appointmentId): void
    {
        $appointment = Appointment::find($appointmentId);

        // ✅ نفس قاعدة الملكية المطبّقة في StaffDashboard
        if ($this->dashDenyOnAppointment('view_appointment', $appointment)) return;

        // ...
    }
}
```

وأضِف الصلاحية إلى الـ seeder:

```php
// database/seeders/PermissionsSeeder.php
Permission::findOrCreate('StaffDashboard:view_customers', 'web');
// امنحها لـ admin و manager فقط — لا للمزوّدين افتراضياً
```

وهرّب رموز LIKE:

```php
// app/Services/CustomerLookupService.php
$escaped = addcslashes($q, '%_\\');

$query->where('first_name', 'like', "%{$escaped}%")
      ->orWhere('last_name', 'like', "%{$escaped}%")
      // ...
```

### فكرة الإصلاح العميقة

`StaffDashboard` يوضّح أنك **تعرف** كيف تُبنى الحماية الصحيحة — نمط `dashDeny` مطبَّق فيه بدقة. المشكلة أن `CustomerLookup` أُضيف لاحقاً ولم يرث النمط.

الدرس المعماري: **الحماية يجب أن تكون افتراضية لا اختيارية.** أنشئ فئة أساس:

```php
abstract class StaffDashboardComponent extends Component
{
    use InteractsWithDashboardPermissions;

    abstract protected function requiredAbility(): string;

    public function mount(): void
    {
        abort_unless($this->dashCan($this->requiredAbility()), 403);
    }
}
```

وورّث منها كل مكوّنات اللوحة. حينها **يستحيل** إضافة مكوّن جديد بلا حماية — لأن `requiredAbility()` مجرّدة ويجب تنفيذها.

---

## 🔴 AUTHZ-03 — IDOR كامل في طباعة الفواتير

**الموقع:** [`app/Http/Controllers/PrintController.php:22, 55, 99`](app/Http/Controllers/PrintController.php#L22) · [`app/Http/Controllers/AppointmentPrintController.php:18`](app/Http/Controllers/AppointmentPrintController.php#L18) · [`app/Http/Controllers/InvoiceTemplateController.php:44`](app/Http/Controllers/InvoiceTemplateController.php#L44)
**الحالة:** ✅ مؤكد بالكود (بحثتُ عن `auth()` و`Gate::` و`authorize` و`customer_id` و`abort` — **صفر نتائج** في `PrintController`)

### الشرح

```php
// PrintController.php:22
public function print(Request $request, Invoice $invoice) {
    try {
        $printer  = $printerId ? PrinterSetting::findOrFail($printerId) : PrinterSetting::getDefault();
        $template = $templateId ? InvoiceTemplate::findOrFail($templateId) : $invoice->getTemplateOrDefault();
        $result   = $this->printService->print($invoice, $printer, $template, $copies);

        return response($result['html'])->header('Content-Type', 'text/html');
    } catch (\Exception $e) {
        return response('Error: ' . $e->getMessage(), 500);     // <-- يسرّب الاستثناء أيضاً
    }
}
```

**لا يوجد أي فحص ملكية.** الحماية الوحيدة هي `middleware(['auth'])` على مستوى المسار — أي «هل أنت مسجّل دخول؟» لا «هل هذه فاتورتك؟».

### الحالة الأخطر — `printBatch` بلا أي تحقق إطلاقاً

```php
// PrintController.php:99-116
public function printBatch(Request $request) {
    $invoiceIds = explode(',', $request->get('invoice_ids', ''));

    if (empty($invoiceIds)) {                    // <-- فرع ميت! انظر أدناه
        return response('No invoices specified', 400);
    }

    $result = $this->printService->printBatch($invoiceIds, $printer);

    return response($result['html'])->header('Content-Type', 'text/html');
}
```

**لا validation. لا فحص ملكية. لا سقف على عدد الفواتير.**

```bash
# استخراج كل فواتير الصالون في طلب واحد
curl -b "barberbooking_session=<جلسة أي موظف>" \
  "https://lookupfriseur.com/invoices/print-batch?invoice_ids=$(seq -s, 1 100000)"
```

**خلل إضافي:** `explode(',', '')` تُرجع `['']` — وهي مصفوفة **غير فارغة**. لذلك `empty($invoiceIds)` **لا تتحقق أبداً**، والفرع الواقي في السطر 102 كود ميت.

قارن مع النسخة الـ API في نفس الملف (السطر 124) والتي بها validation صحيح — وهذا يثبت أن المطوّر يعرف الصواب لكن النسخة الويب فاتته:

```php
'invoice_ids'   => 'required|array|min:1',
'invoice_ids.*' => 'exists:invoices,id',
```
(لكنها ما زالت بلا فحص ملكية.)

### مسار غير مصادق عليه إطلاقاً

```php
// routes/web.php:152 — خارج كل مجموعة middleware
Route::get('/invoice-template/{template}/preview', [InvoiceTemplateController::class, 'preview']);
```

**خبر جيد:** فحصتُ `preview()` وهي تستخدم `buildPreview($template)` ببيانات نموذجية (sample data) لا بفاتورة حقيقية — **فلا تسريب لبيانات عملاء**. لكنها تكشف تصميم قوالبك وبيانات شركتك، والسطر 37 يُرجع `$e->getMessage()` للزائر. أصنّفها 🟡 Medium.

### سيناريو استغلال

بما أن مسارات الطباعة الويب محمية بـ `auth` (جلسة ويب)، والعملاء يدخلون عبر Sanctum لا جلسة، فالاستغلال العملي هو **بين الموظفين**: أي مزوّد خدمة يستطيع طباعة فاتورة أي عميل لأي زميل، ورؤية المبالغ وبيانات الاتصال. مع `AUTHZ-01`، هذا يشمل الموظفين السابقين.

مسارات الـ API (`/api/invoice/{invoice}/print`) **كانت** ستشكّل IDOR حقيقياً لكل عميل موبايل — لكنها معطّلة حالياً بسبب `CFG-01`. **انتبه:** لحظة إصلاح `CFG-01`، تتحوّل هذه إلى ثغرة IDOR فعّالة لكل عميل. **يجب إصلاح الاثنين معاً.**

### الإصلاح

**الخطوة 1 — أنشئ Policy:**

```php
// app/Policies/InvoicePolicy.php
class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        // الموظفون: حسب صلاحية صريحة
        if ($user->hasAnyRole(['SuperAdmin', 'admin', 'manager'])) {
            return true;
        }

        // المزوّد: فواتير مواعيده فقط
        if ($user->isProvider()) {
            return $invoice->appointment?->provider_id === $user->id;
        }

        // العميل: فواتيره فقط
        return $invoice->customer_id === $user->id;
    }

    public function print(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice);
    }
}
```

**الخطوة 2 — طبّقها في كل نقطة دخول:**

```php
public function print(Request $request, Invoice $invoice)
{
    $this->authorize('print', $invoice);        // ✅ 403 قبل أي عمل
    // ...
}

public function printBatch(Request $request)
{
    $validated = $request->validate([
        'invoice_ids'   => 'required|array|min:1|max:50',      // ✅ سقف
        'invoice_ids.*' => 'integer|exists:invoices,id',
    ]);

    $invoices = Invoice::whereIn('id', $validated['invoice_ids'])->get();

    // ✅ صرّح لكل فاتورة على حدة
    foreach ($invoices as $invoice) {
        $this->authorize('print', $invoice);
    }

    // ...
}
```

**الخطوة 3 — أوقف تسريب الاستثناءات:**

```php
} catch (\Throwable $e) {
    report($e);
    return response('Unable to generate the invoice.', 500);    // بلا getMessage()
}
```

### فكرة الإصلاح العميقة

القاعدة: **`auth` تجيب «من أنت؟» بينما `Policy` تجيب «هل يحقّ لك؟».** الخلط بينهما هو أشيع مصدر لثغرات IDOR.

المبدأ التطبيقي: **كل controller يستقبل معرّف مورد من المستخدم يجب أن يستدعي `authorize()` قبل أي عمل.** ولترسيخ ذلك، سجّل الـ Policies وأضِف حارساً في بيئة التطوير:

```php
// app/Providers/AppServiceProvider.php  ->  boot()
Gate::before(function ($user, $ability) {
    return $user->hasRole('SuperAdmin') ? true : null;
});

// يكشف أي موديل بلا Policy أثناء التطوير
if (! app()->isProduction()) {
    Gate::guessPolicyNamesUsing(fn ($class) => 'App\\Policies\\' . class_basename($class) . 'Policy');
}
```

---

## 🔴 AUTHZ-04 — أي عميل يرسل إشعاراً لكل مستخدمي النظام

**الموقع:** [`routes/api.php:215-218`](routes/api.php#L215-L218) + [`app/Http/Controllers/Api/NotificationController.php:143-166`](app/Http/Controllers/Api/NotificationController.php#L143-L166)
**الحالة:** ✅ مؤكد بالكود

### الشرح

```php
// routes/api.php — داخل مجموعة auth:sanctum + verified.customer فقط
Route::prefix('noticifation')->name('noticifation.')->group(function () {
    Route::post('/test-send-to-all',       [NotificationController::class, 'testSendToAll']);
    Route::post('/test-send-to-customers', [NotificationController::class, 'testSendToAllCustomers']);
});
```

```php
public function testSendToAll()
{
    $this->oneSignal->sendToAll('Test from Laravel', 'هذه رسالة تجريبية من Backend Laravel.');

    $users = User::all();                                        // <-- كل المستخدمين في الذاكرة
    $this->notificationService->sendToPhoneUsersDatabase($users, $titleKey, $messageKey, [], []);
    // ...
}
```

**لا `middleware('role:admin')`. لا `abort_unless(app()->isLocal())`. لا throttle** (و`CFG-01` أزال السقف العام).

### سيناريو استغلال

```bash
# 1) سجّل حساب عميل عادي وفعّله
# 2) بتوكنك العادي تماماً:
curl -X POST https://lookupfriseur.com/api/noticifation/test-send-to-all \
  -H "Authorization: Bearer <توكن عميل عادي>" \
  -H "Accept: application/json"
```

كل مستخدم مسجّل — عملاء وموظفون ومدير — يتلقّى إشعار Push على هاتفه.

`testSendToAllCustomers` أسوأ: يقبل `title` و`message` من الطلب:

```bash
curl -X POST https://lookupfriseur.com/api/noticifation/test-send-to-customers \
  -H "Authorization: Bearer <توكن عميل عادي>" \
  -d "title=عرض خاص 50%" \
  -d "message=سجّل بياناتك هنا: http://evil.example"
```

**رسالة تصيّد تصل إلى كل عملائك، من داخل تطبيقك الرسمي، بهويتك.**

### الأثر على الموقع

- **تصيّد جماعي بهويتك** — أخطر بكثير من بريد تصيّد عادي لأن الإشعار يظهر داخل التطبيق الموثوق.
- **استنزاف حصة OneSignal** واحتمال حظر تطبيقك.
- **DoS بالذاكرة:** `User::all()` تُحمّل كل المستخدمين دفعة واحدة. مع 50 ألف عميل وحلقة `curl`، ينهار PHP-FPM.
- تدمير سمعتك أمام العملاء.

### الإصلاح

```php
// routes/api.php — احذف المسارات من الإنتاج
if (app()->environment('local')) {
    Route::prefix('notifications-debug')->middleware(['auth:sanctum', 'role:SuperAdmin'])->group(function () {
        Route::post('/send-to-all', [NotificationController::class, 'testSendToAll'])
            ->middleware('throttle:2,60');
    });
}
```

وإن أردت الإبقاء عليها كميزة إدارية حقيقية:

```php
Route::post('/broadcast', [NotificationController::class, 'broadcast'])
    ->middleware(['auth:sanctum', 'role:SuperAdmin|admin', 'throttle:5,60']);
```

```php
public function broadcast(Request $request)
{
    $this->authorize('broadcast', Notification::class);

    $validated = $request->validate([
        'title'   => 'required|string|max:120',
        'message' => 'required|string|max:500',
    ]);

    // ✅ chunk بدل User::all() — لا يُحمّل كل شيء في الذاكرة
    User::query()
        ->whereHas('devices')
        ->chunkById(500, function ($users) use ($validated) {
            $this->notificationService->sendToPhoneUsersDatabase(
                $users, $validated['title'], $validated['message'], [], []
            );
        });

    Log::info('Broadcast sent', ['by' => auth()->id(), 'title' => $validated['title']]);

    return response()->json(['success' => true]);
}
```

### ملاحظة على التسمية

البادئة `noticifation` بها خطأ إملائي (الصواب `notification`). هذا ليس مجرد تجميل: **مسار بخطأ إملائي يفلت من أي بحث أمني أو مراجعة أو قاعدة WAF** تبحث عن `notification`. أعِد التسمية.

---

## 🟠 AUTHZ-05 — تعداد أسماء المستخدمين عبر رسالة خطأ الحجز

**الموقع:** [`app/Services/BookingValidationService.php:59-63`](app/Services/BookingValidationService.php#L59-L63) + [`app/Http/Requests/Api/BookingCreateRequest.php:27`](app/Http/Requests/Api/BookingCreateRequest.php#L27)
**الحالة:** ✅ مؤكد بالكود

### الشرح

قاعدة التحقق:

```php
'services.*.provider_id' => 'required|integer|exists:users,id',
```

`exists:users,id` تقبل **أي مستخدم** — عملاء ومديرين ومزوّدين. ثم:

```php
if (! $offers) {
    throw new InvalidArgumentException(
        "Provider '{$provider->full_name}' does not offer service '{$service->name}'"
    );
}
```

والاستثناء يصل إلى العميل بنصه الكامل عبر `BookingController:46-51` بحالة 422.

### سيناريو استغلال

```bash
for id in $(seq 1 5000); do
  name=$(curl -s -X POST https://lookupfriseur.com/api/bookings \
    -H "Authorization: Bearer <أي توكن عميل>" \
    -H "Accept: application/json" \
    -d "date=2026-09-01&payment_method=cash" \
    -d "services[0][service_id]=1" \
    -d "services[0][provider_id]=$id" \
    -d "services[0][start_time]=10:00" \
    | grep -oP "Provider '\K[^']+")
  echo "$id: $name"
done
```

المُخرَج: **الاسم الكامل الحقيقي لكل مستخدم في النظام** — كل عميل، كل موظف، المدير. وبلا سقف معدّل (`AUTH-01` + `CFG-01`).

### الأثر على الموقع

تسريب PII (الأسماء الكاملة لكل عملائك) لأي شخص يملك حساباً عادياً. هذا خرق GDPR، وأساس لهجمات هندسة اجتماعية لاحقة («مرحباً، أنا من صالون LookUp، أتصل بالسيد أحمد محمد بخصوص موعدك...»).

### الإصلاح

**1. قيّد قاعدة التحقق على المزوّدين فقط:**

```php
// BookingCreateRequest::rules()
use Illuminate\Validation\Rule;

'services.*.provider_id' => [
    'required', 'integer',
    Rule::exists('users', 'id')->where(function ($query) {
        $query->where('is_active', true)
              ->whereIn('id', function ($sub) {
                  $sub->select('model_id')
                      ->from('model_has_roles')
                      ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                      ->where('roles.name', 'provider');
              });
    }),
],
```

**2. أزل بيانات الأشخاص من رسائل الأخطاء:**

```php
if (! $offers) {
    throw new InvalidArgumentException(
        __('booking.provider_does_not_offer_service')    // رسالة عامة، بلا أسماء
    );
}
```

### فكرة الإصلاح العميقة

**رسائل الأخطاء قناة تسريب بيانات.** القاعدة: لا تضع في رسالة خطأ تصل إلى المستخدم أي بيانات لم يكن يملكها أصلاً. المهاجم أرسل رقماً (`provider_id=7`) وحصل على اسم — تلك زيادة صافية في معرفته. اجعل الرسائل عامة، وضع التفاصيل في `Log::` حيث تفيدك أنت وحدك.

---

# 5. منطق الحجز

## 🔴 BOOK-01 — طبقتا التوفّر والحجز تستخدمان قاعدتَي تعارض مختلفتين

**الموقع:** [`app/Services/ServiceAvailabilityService.php:452-461`](app/Services/ServiceAvailabilityService.php#L452-L461) مقابل [`app/Services/BookingValidationService.php:126-137`](app/Services/BookingValidationService.php#L126-L137)
**الحالة:** ✅ مؤكد بقراءة الطرفين

### الشرح — القاعدتان جنباً إلى جنب

```php
// طبقة التوفّر (ما يراه العميل في التطبيق)
// ServiceAvailabilityService::getProviderAppointments()
Appointment::where('provider_id', $provider->id)
    ->whereDate('appointment_date', $date)
    ->whereIn('status', [AppointmentStatus::PENDING])     // PENDING فقط
    // لا فلترة على created_status إطلاقاً
    ->select('start_time', 'end_time')
    ->get();
```

```php
// طبقة الحجز (ما يُطبَّق عند الضغط على «احجز»)
// BookingValidationService::validateTimeSlotAvailability()
Appointment::where('provider_id', $provider->id)
    ->whereDate('appointment_date', $date)
    ->where('created_status', 1)                                            // مؤكد فقط
    ->whereIn('status', [PENDING->value, COMPLETED->value])                 // PENDING + COMPLETED
    ->where(fn ($q) => $q->where('start_time', '<', $endTime)
                         ->where('end_time', '>', $startTime))
    ->exists();
```

الاختلاف في **بُعدين متعاكسين**، وينتج عنه عطلان منفصلان:

### العطل الأول — مواعيد مهجورة تحجب الأوقات إلى الأبد

طبقة التوفّر **لا تفلتر على `created_status`**، فتعتبر الحجوزات غير المؤكدة (`created_status = 0`) حاجزة للوقت. لكن طبقة الحجز تتجاهلها.

**سيناريو ملموس:**

1. عميل يحجز عبر التطبيق بـ `payment_method = online`. `BookingService:44-45` يضبط `created_status = 0` و`payment_status = PENDING`.
2. العميل يغلق التطبيق ولا يُكمل الدفع. **لا توجد أي وظيفة تنظيف** — التعليق في `BookingValidationService:128` يقول ذلك صراحةً: `// TODO: rmov created_status check and make job for cleaning unpaid bookings`.
3. من الآن فصاعداً:
   - **طبقة التوفّر:** ترى الحجز وتحجب الساعة 14:00. لا عميل آخر يستطيع رؤيتها.
   - **طبقة الحجز:** لا تراه؛ لو حاول أحد الحجز مباشرة لنجح.
4. الوقت **محجوب من التطبيق إلى الأبد**، وأنت لا تعرف السبب.

**الأثر التجاري المباشر:** كل حجز أونلاين مهجور يحذف فتحة من جدول التوفّر نهائياً. بمعدل 5 حجوزات مهجورة يومياً، تفقد بعد شهر **150 فتحة** — أي خسارة إيراد صامتة ومتراكمة.

### العطل الثاني — مواعيد مكتملة تظهر كأنها متاحة

طبقة التوفّر تفلتر على `PENDING` فقط، فلا ترى المواعيد `COMPLETED`. لكن طبقة الحجز تعتبرها تعارضاً.

**سيناريو ملموس:**

1. موعد الساعة 10:00 اليوم انتهى، وحصّل الموظف المبلغ. `InvoiceFinalizationService:235` يضبط `status = COMPLETED`.
2. عميل يفتح التطبيق ويطلب مواعيد اليوم → **طبقة التوفّر تعرض 10:00 كمتاح** (لأنها لم تعد PENDING).
3. العميل يختارها ويضغط «احجز».
4. طبقة الحجز ترفض: `"Time slot 10:00 - 10:30 is already booked for provider 'X'"`.

**الأثر التجاري:** رسالة خطأ محيّرة بعد إتمام كل خطوات الحجز. العميل يظن أن التطبيق معطّل. اتصالات دعم متكررة، وتخلٍّ عن الحجز.

### الإصلاح — مصدر واحد للحقيقة

الحل الصحيح ليس ترقيع الاستعلامين، بل **دمجهما في scope واحد على الموديل**:

```php
// app/Models/Appointment.php

/**
 * المواعيد التي تحجب وقت مزوّد فعلياً.
 *
 * هذا هو التعريف **الوحيد** للتعارض في النظام. كل طبقة تسأل
 * "هل هذا الوقت مشغول؟" يجب أن تمرّ من هنا — طبقة التوفّر،
 * وطبقة التحقق من الحجز، ولوحة الموظفين، وتحليل الفجوات.
 */
public function scopeBlocking(Builder $query, int $providerId, string $date): Builder
{
    return $query
        ->where('provider_id', $providerId)
        ->whereDate('appointment_date', $date)
        ->where('created_status', 1)                      // المؤكدة فقط تحجز الوقت
        ->whereIn('status', [
            AppointmentStatus::PENDING->value,
            AppointmentStatus::COMPLETED->value,
        ]);
}

/** يتقاطع مع نافذة زمنية: start1 < end2 AND end1 > start2 */
public function scopeOverlapping(Builder $query, Carbon $start, Carbon $end): Builder
{
    return $query->where('start_time', '<', $end)
                 ->where('end_time', '>', $start);
}
```

**الاستخدام في الطبقتين:**

```php
// ServiceAvailabilityService::getProviderAppointments()
private function getProviderAppointments(User $provider, Carbon $date): Collection
{
    return Appointment::query()
        ->blocking($provider->id, $date->format('Y-m-d'))     // ✅ نفس القاعدة
        ->select('start_time', 'end_time')
        ->get();
}
```

```php
// BookingValidationService::validateTimeSlotAvailability()
$hasConflict = Appointment::query()
    ->blocking($provider->id, $startTime->format('Y-m-d'))    // ✅ نفس القاعدة
    ->overlapping($startTime, $endTime)
    ->lockForUpdate()                                         // انظر BOOK-02
    ->exists();
```

**وأضِف وظيفة التنظيف الناقصة** (التعليق `TODO` في الكود يطلبها منذ زمن):

```php
// app/Console/Commands/PurgeAbandonedBookings.php
class PurgeAbandonedBookings extends Command
{
    protected $signature = 'bookings:purge-abandoned';

    public function handle(): int
    {
        $cutoff = now()->subMinutes(30);

        $count = Appointment::query()
            ->where('created_status', 0)
            ->where('payment_status', PaymentStatus::PENDING)
            ->where('created_at', '<', $cutoff)
            ->update([
                'status'              => AppointmentStatus::ADMIN_CANCELLED,
                'cancellation_reason' => 'Abandoned — payment not completed within 30 minutes',
                'cancelled_at'        => now(),
            ]);

        $this->info("Purged {$count} abandoned bookings.");

        return self::SUCCESS;
    }
}
```

```php
// routes/console.php — الـ scheduler يعمل عندك، فهذا سيُنفَّذ فعلاً
Schedule::command('bookings:purge-abandoned')->everyTenMinutes();
```

### فكرة الإصلاح العميقة

هذا مثال كلاسيكي على **Parity Bug**: قاعدة عمل واحدة («ما الذي يحجز وقت المزوّد؟») مُنفَّذة في مكانين، فانحرفا. ولن يكون هذا آخرها ما دام التعريف مكرَّراً — لديك **أربع** نسخ منه حالياً: في `ServiceAvailabilityService` و`BookingValidationService` و`DashboardService::getAvailableProvidersForServiceAtTime` و`GapAnalysisService`.

المبدأ: **قواعد العمل الأساسية تعيش في مكان واحد قابل للاستدعاء** — scope على الموديل، أو كائن Specification. ثم أضِف اختباراً يمنع الانحراف من العودة:

```php
it('never offers a slot the booking layer would reject', function () {
    $slots = app(ServiceAvailabilityService::class)
        ->getProviderAvailableSlotsByDate($service->id, $provider->id, $date);

    foreach ($slots['available_slots'] as $slot) {
        expect(fn () => app(BookingValidationService::class)->validateTimeSlotAvailability(
            $provider, $service,
            Carbon::parse("$date {$slot['start_time']}"),
            Carbon::parse("$date {$slot['end_time']}"),
        ))->not->toThrow(InvalidArgumentException::class);
    }
});
```

---

## 🔴 BOOK-02 — حجز مزدوج بسبب TOCTOU (التحقق خارج المعاملة وبلا قفل)

**الموقع:** [`app/Services/BookingService.php:67-83`](app/Services/BookingService.php#L67-L83)
**الحالة:** ✅ مؤكد بالكود + **مؤكد بقاعدة البيانات** (لا يوجد قيد فريد يحمي)

### الشرح

```php
public function createBooking(?User $customer, array $bookingData): Appointment
{
    // ...
    $this->validationService->validateBasicData($services, $date);           // السطر 67

    if ($customer) {
        $this->validationService->validateDailyBookingLimit($customer, $date);
    }

    $services = $this->sortServicesByStartTime($services);

    // ⚠️ كل التحقق من التعارض يحدث هنا — خارج أي معاملة وبلا أي قفل
    $preparedServices = $this->validateAndPrepareServices(/* ... */);        // السطر 77

    $totals = $this->calculateTotals($preparedServices);

    // ⚠️ المعاملة تبدأ هنا فقط — بعد انتهاء كل التحقق
    $appointment = DB::transaction(function () use (/* ... */) {             // السطر 83
        $appointment = Appointment::create([/* ... */]);
        // ...
    });
}
```

هذه **فجوة TOCTOU** كلاسيكية (Time-Of-Check to Time-Of-Use): بين لحظة التحقق (السطر 77) ولحظة الكتابة (السطر 83) توجد نافذة زمنية بلا أي حماية.

### سيناريو استغلال / حادث

عميلان يضغطان «احجز» على نفس الفتحة (10:00 مع أحمد) في نفس اللحظة — وهو أمر شائع جداً عند فتح المواعيد:

```
الزمن   الطلب أ                                    الطلب ب
─────   ────────────────────────────────           ────────────────────────────────
t=0     validateTimeSlotAvailability()
        SELECT ... WHERE 10:00 يتعارض
        النتيجة: صفر  ->  الفتحة حرة ✓

t=1                                                validateTimeSlotAvailability()
                                                   SELECT ... WHERE 10:00 يتعارض
                                                   النتيجة: صفر  ->  الفتحة حرة ✓
                                                   (لأن أ لم يكتب بعد)

t=2     BEGIN; INSERT appointment 10:00; COMMIT;

t=3                                                BEGIN; INSERT appointment 10:00; COMMIT;

النتيجة: موعدان متطابقان لنفس المزوّد في نفس الدقيقة.
```

**لا يوجد أي قيد في قاعدة البيانات يمنع ذلك** — تحققتُ من الفهارس الفعلية:

```sql
-- فهارس جدول appointments (فحص حقيقي على قاعدتك)
PRIMARY (id)
appointments_customer_id_foreign (customer_id)
appointments_parent_id_idx (parent_appointment_id)
appointments_provider_id_foreign (provider_id)
-- لا يوجد أي قيد فريد على (provider_id, start_time)
```

### الأثر على الموقع

عميلان يصلان الصالون في نفس الوقت لنفس الحلاق. أحدهما يُصرَف — وهو عميل جديد على الأرجح، لأن هذا يحدث تحديداً في لحظات الذروة عند فتح المواعيد. ضرر مباشر للسمعة وتقييمات سلبية.

### الإصلاح — ثلاث طبقات

**الطبقة 1 — انقل التحقق داخل المعاملة مع قفل صفّي:**

```php
public function createBooking(?User $customer, array $bookingData): Appointment
{
    // ... استخراج البيانات ...

    // التحقق الرخيص الذي لا يمس التزامن يبقى خارج المعاملة
    $this->validationService->validateBasicData($services, $date);
    $services = $this->sortServicesByStartTime($services);

    $appointment = DB::transaction(function () use (/* ... */) {

        if ($customer) {
            $this->validationService->validateDailyBookingLimit($customer, $date);
        }

        // ✅ كل التحقق الحساس للتزامن صار داخل المعاملة
        $preparedServices = $this->validateAndPrepareServices(/* ... */);
        $totals = $this->calculateTotals($preparedServices);

        $appointment = Appointment::create([/* ... */]);
        // ... باقي الإنشاء ...

        return $appointment->load([/* ... */]);
    }, 3);      // ✅ 3 محاولات إعادة عند deadlock
}
```

```php
// BookingValidationService::validateTimeSlotAvailability
$hasConflict = Appointment::query()
    ->blocking($provider->id, $startTime->format('Y-m-d'))
    ->overlapping($startTime, $endTime)
    ->lockForUpdate()          // ✅ يُسلسِل الطلبات المتنافسة على نفس الصفوف
    ->exists();
```

**الطبقة 2 — قيد على مستوى قاعدة البيانات (شبكة الأمان الأخيرة):**

القفل الصفّي وحده لا يحمي من تعارض على صفوف **غير موجودة بعد** (وهي حالتنا: كلا الطلبين يقرأ «صفر نتائج»). لذلك أضِف قيداً فريداً:

```php
// database/migrations/xxxx_add_booking_integrity_constraints.php
Schema::table('appointments', function (Blueprint $table) {
    // ✅ يمنع موعدين للمزوّد نفسه في نفس لحظة البدء
    $table->unique(['provider_id', 'start_time'], 'appointments_provider_slot_unique');

    // ✅ فهرس مركّب يخدم استعلام التعارض (انظر PERF-01)
    $table->index(['provider_id', 'appointment_date', 'created_status', 'status'],
                  'appointments_conflict_idx');

    // ✅ رقم الموعد يجب أن يكون فريداً (انظر DB-01)
    $table->unique('number', 'appointments_number_unique');
});
```

> **ملاحظة صريحة:** هذا القيد يمنع البدايات المتطابقة تماماً، لا التداخل الجزئي (10:00-10:30 مقابل 10:15-10:45). منع التداخل الكامل يحتاج قيد استبعاد (exclusion constraint) وهو غير مدعوم في MySQL. لذلك **الطبقة 1 هي الحماية الأساسية، والطبقة 2 شبكة أمان لأشيع حالة**. إن انتقلت إلى PostgreSQL لاحقاً، استخدم `EXCLUDE USING gist` مع `tstzrange` للحماية الكاملة.

**الطبقة 3 — عالج فشل القيد بلطف:**

```php
try {
    $appointment = $this->bookingService->createBooking($customer, $bookingData);
} catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
    return response()->json([
        'success'    => false,
        'message'    => __('booking.slot_just_taken'),
        'error_type' => 'slot_conflict',
    ], 409);                                  // 409 Conflict — الرمز الدلالي الصحيح
}
```

### فكرة الإصلاح العميقة

القاعدة: **أي فحص «هل هذا المورد متاح؟» متبوع بـ «إذن احجزه» يجب أن يكونا في معاملة واحدة مع قفل — أو أن يُستبدلا بقيد في قاعدة البيانات.**

والمبدأ الأعمق: **قاعدة البيانات هي الحكم الوحيد على الثوابت (invariants)، لا كود التطبيق.** كود التطبيق يوفّر رسائل خطأ لطيفة؛ قاعدة البيانات توفّر الضمان. من يعتمد على كود التطبيق وحده يحمي نفسه من المستخدم المهذّب فقط، لا من التزامن.

---

## 🟠 BOOK-03 — أي عميل يُعلِّم حجزه «مدفوع نقداً» بلا أن يدفع

**الموقع:** [`app/Services/BookingService.php:44-45, 85-88`](app/Services/BookingService.php#L44-L45) + [`app/Http/Requests/Api/BookingCreateRequest.php:31`](app/Http/Requests/Api/BookingCreateRequest.php#L31)
**الحالة:** ✅ مؤكد بالكود

### الشرح

```php
// BookingCreateRequest — العميل يتحكم بهذه القيمة
'payment_method' => 'required|string|in:cash,online',
```

```php
// BookingService:44-45 — القيمة تقود قرارَين ماليين
$isConfirmed = $bookingData['is_confirmed'] ?? ($paymentMethod == 'cash');
$markAsPaid  = $bookingData['mark_as_paid'] ?? ($paymentMethod == 'cash');

// BookingService:85-88
$createdStatus = $isConfirmed ? 1 : 0;
$paymentStatus = $markAsPaid
    ? PaymentStatus::PAID_ONSTIE_CASH        // <-- «دُفع نقداً في الموقع»
    : PaymentStatus::PENDING;
```

عميل يُرسل `payment_method=cash` من تطبيق الموبايل يحصل فوراً على:

- `created_status = 1` (مؤكد، يحجز الوقت) — وهذا صحيح ومقصود ✓
- `payment_status = PAID_ONSTIE_CASH` — **وهذا خطأ**: لم يدفع شيئاً، ولن يدفع حتى يصل الصالون.

### مثال عملي

```bash
curl -X POST https://lookupfriseur.com/api/bookings \
  -H "Authorization: Bearer <توكن عميل عادي>" \
  -H "Accept: application/json" \
  -d "date=2026-09-01&payment_method=cash" \
  -d "services[0][service_id]=1&services[0][provider_id]=7&services[0][start_time]=10:00"
```

في قاعدة البيانات فوراً:

```sql
SELECT number, total_amount, payment_status FROM appointments ORDER BY id DESC LIMIT 1;
-- APT-20260901-3F2A9C | 50.00 | 2   <-- 2 = PAID_ONSTIE_CASH، ولم يُدفع أي شيء
```

### الأثر على الموقع

**1. تقارير إيراد كاذبة.** كل خدمة تجمع الإيراد حسب `payment_status` سترى مبالغ لم تُحصَّل. مع مواعيد لم يحضرها أصحابها (no-show)، يتضخّم «إيرادك» بمبالغ وهمية لم تدخل الصندوق أبداً — وأنت تبني قراراتك عليها.

**2. تعارض داخلي في الفوترة.** الموعد يُعلَّم مدفوعاً بينما `BookingService:131` ينشئ فاتورة **DRAFT** بمبلغ مدفوع صفر:

```php
$InvoiceService->createDtaftInvoiceFromAppointment($appointment, 'cash', 0);
```

فالحالة النهائية: `appointment.payment_status = مدفوع` + `invoice.status = مسودة` + `payments` لا يحوي أي صف. **ثلاثة مصادر تقول ثلاثة أشياء مختلفة عن نفس المعاملة.** أي تسوية محاسبية ستفشل.

**3. مخالفة GoBD.** يجب أن تكون هناك مطابقة قابلة للتدقيق بين حالة الدفع وسجل دفع حقيقي.

### الإصلاح

```php
// BookingService.php — افصل «مؤكَّد» عن «مدفوع»
$isConfirmed = $bookingData['is_confirmed'] ?? ($paymentMethod === 'cash');

// ✅ مسار العميل لا يُعلّم شيئاً كمدفوع أبداً.
//    فقط المسارات الداخلية الموثوقة (لوحة الموظفين) تمرّر mark_as_paid صراحةً.
$markAsPaid = (bool) ($bookingData['mark_as_paid'] ?? false);
```

**نقطة تصميمية مهمة:** `payment_method = 'cash'` يعني **«ينوي الدفع نقداً عند الحضور»**، لا «دفع». التأكيد ووسيلة الدفع بُعدان مستقلان تماماً:

| | `created_status` | `payment_status` |
|---|---|---|
| يعني | هل يحجز الوقت؟ | هل دخل المال الصندوق؟ |
| `cash` من التطبيق | `1` ✓ | `PENDING` ✓ |
| `online` من التطبيق | `0` حتى يتم الدفع | `PENDING` |
| لوحة الموظفين + تحصيل فعلي | `1` | `PAID_ONSTIE_CASH` ✓ |

**نقطة إيجابية مؤكَّدة:** فحصتُ ما إذا كان العميل يستطيع حقن `is_confirmed` أو `mark_as_paid` أو `bypass_availability` أو `allow_same_day_past` مباشرة. **لا يستطيع** — لأن `BookingController:35` يستخدم `$request->validated()` التي تُرجع الحقول المُعرَّفة في `rules()` فقط. هذا تصميم صحيح، وأثني عليه. المشكلة الوحيدة هي **الاشتقاق** من `payment_method`.

---

## 🟠 BOOK-04 — إجازات المزوّد الممتدة لعدة أيام تُتجاهَل في التحقق من الحجز

**الموقع:** [`app/Services/BookingValidationService.php:210-219`](app/Services/BookingValidationService.php#L210-L219) مقابل [`app/Services/ServiceAvailabilityService.php:466-474`](app/Services/ServiceAvailabilityService.php#L466-L474)
**الحالة:** ⚠️ **كامن (Latent)** — الكود مكسور، والبيانات الحالية لا تُفعّله بعد

### الشرح

```php
// طبقة الحجز — تطابق تاريخ البدء بالضبط فقط
ProviderTimeOff::where('user_id', $provider->id)
    ->where('type', ProviderTimeOff::TYPE_HOURLY)
    ->whereDate('start_date', $date)            // ⚠️ تطابق تام — يتجاهل end_date
    // ...
```

```php
// طبقة التوفّر — تتعامل مع المدى بشكل صحيح
ProviderTimeOff::where('user_id', $provider->id)
    ->where('type', ProviderTimeOff::TYPE_HOURLY)
    ->whereDate('start_date', '<=', $date->format('Y-m-d'))
    ->whereRaw('COALESCE(end_date, start_date) >= ?', [$date->format('Y-m-d')])   // ✅ صحيح
    // ...
```

نفس المشكلة في فحص إجازة اليوم الكامل (السطور 197-201): `->where('end_date', '>=', $date)` — وهذه **غير آمنة أمام NULL**: في SQL، `NULL >= '2026-09-01'` تُقيَّم إلى `NULL` (لا `TRUE`)، فيسقط الصف من النتيجة. أي أن **إجازة يوم واحد بـ `end_date = NULL` لن تُكتشف أبداً** في طبقة الحجز — بينما طبقة التوفّر تتعامل معها بشكل صحيح عبر `COALESCE`.

### لماذا «كامن» ولماذا يجب إصلاحه رغم ذلك

فحصتُ بياناتك الفعلية:

```sql
SELECT type, COUNT(*) total,
       SUM(CASE WHEN end_date IS NULL THEN 1 ELSE 0 END) AS null_end_date,
       SUM(CASE WHEN start_date <> end_date THEN 1 ELSE 0 END) AS multi_day
FROM provider_time_offs GROUP BY type;

-- type=0 (HOURLY)   : total=4, null_end_date=0, multi_day=0
-- type=1 (FULL_DAY) : total=7, null_end_date=0, multi_day=7
```

اليوم: كل الإجازات بالساعة ليوم واحد، وكل إجازات اليوم الكامل لها `end_date`. **فالعطل لا يظهر.**

لكنه ينفجر في اللحظة التي:
- يُنشئ فيها موظف إجازة بالساعة ممتدة (مثلاً «كل يوم من 12:00 إلى 13:00 لمدة أسبوع») — وهذا استخدام طبيعي تماماً، والنموذج يدعمه بحقلَي `start_date` و`end_date`؛
- أو يُنشئ إجازة يوم واحد يترك فيها `end_date` فارغاً — والعمود `nullable` في قاعدة البيانات، فهذا مسموح.

### السيناريو عند وقوعه

1. مزوّد يأخذ إجازة بالساعة من 10 إلى 15 سبتمبر، 12:00–13:00.
2. **التطبيق:** يُخفي 12:00–13:00 في كل تلك الأيام ✓ (طبقة التوفّر صحيحة).
3. لكن أي مسار حجز آخر — لوحة الموظفين، أو استدعاء API مباشر بالوقت — **يمرّ بلا اعتراض** في 11 و12 و13 سبتمبر، لأن `whereDate('start_date', $date)` تطابق 10 سبتمبر فقط.
4. المزوّد يصل ويجد موعداً في وقت إجازته.

### الإصلاح

```php
// app/Services/BookingValidationService.php — طابِق منطق طبقة التوفّر بالضبط

// 3. إجازة يوم كامل
$hasFullDayOff = ProviderTimeOff::where('user_id', $provider->id)
    ->where('type', ProviderTimeOff::TYPE_FULL_DAY)
    ->whereDate('start_date', '<=', $date)
    ->whereRaw('COALESCE(end_date, start_date) >= ?', [$date])    // ✅ آمن أمام NULL + يدعم المدى
    ->exists();

// 4. إجازة بالساعة
$hasHourlyTimeOff = ProviderTimeOff::where('user_id', $provider->id)
    ->where('type', ProviderTimeOff::TYPE_HOURLY)
    ->whereDate('start_date', '<=', $date)
    ->whereRaw('COALESCE(end_date, start_date) >= ?', [$date])    // ✅ يدعم المدى
    ->where(function ($query) use ($startTime, $endTime) {
        $query->whereRaw('start_time < ?', [$endTime->format('H:i:s')])
              ->whereRaw('end_time   > ?', [$startTime->format('H:i:s')]);
    })
    ->exists();
```

**ملاحظة على `TIME(CONCAT(...))`:** الكود الحالي يستخدم:

```php
$q->whereRaw("TIME(CONCAT(?, ' ', start_time)) < ?", [$date, $endTime->format('H:i:s')])
```

تحققتُ من نوع العمود في قاعدتك:

```sql
SHOW COLUMNS FROM provider_time_offs;
-- start_time | time | YES |
-- end_time   | time | YES |
```

العمود من نوع `time`، لذلك `TIME(CONCAT(date, ' ', start_time))` **تعمل بشكل صحيح** — وهذا يُبطل ادعاءً محتملاً بأنها مكسورة. لكنها التفاف عديم الفائدة يمنع MySQL من استخدام أي فهرس على العمود. قارن العمود مباشرة كما في الإصلاح أعلاه.

### فكرة الإصلاح العميقة

هذا **تكرار لنفس الجذر في `BOOK-01`**: منطق واحد («هل المزوّد في إجازة الآن؟») مُنفَّذ مرتين، وطبقة التوفّر نُقّحت بينما طبقة الحجز لم تُنقَّح. استخرجه إلى مكان واحد:

```php
// app/Models/ProviderTimeOff.php
public function scopeCoveringDate(Builder $query, string $date): Builder
{
    return $query->whereDate('start_date', '<=', $date)
                 ->whereRaw('COALESCE(end_date, start_date) >= ?', [$date]);
}

public function scopeOverlappingTime(Builder $query, Carbon $start, Carbon $end): Builder
{
    return $query->whereRaw('start_time < ?', [$end->format('H:i:s')])
                 ->whereRaw('end_time   > ?', [$start->format('H:i:s')]);
}
```

---

## 🟠 BOOK-05 — `custom_duration` معطّلة بـ return مبكر (كود ميت)

**الموقع:** [`app/Services/BookingService.php:378-387`](app/Services/BookingService.php#L378-L387) + [`app/Services/ServiceAvailabilityService.php:590-599`](app/Services/ServiceAvailabilityService.php#L590-L599)
**الحالة:** ✅ مؤكد بالكود (مكرَّر حرفياً في ملفين)

```php
private function getEffectiveDuration(User $provider, Service $service): int
{
    return $service->duration_minutes;          // <-- return في السطر الأول

    // كل ما تحته لا يُنفَّذ أبداً:
    $pivot = DB::table('provider_service')
        ->where('provider_id', $provider->id)
        ->where('service_id', $service->id)
        ->first();

    return $pivot->custom_duration ?? $service->duration_minutes;
}
```

### الأثر

عمود `custom_duration` في جدول `provider_service` **لا يُقرأ أبداً**، رغم أن واجهة الإدارة تسمح بضبطه.

**السيناريو التجاري:** حلاق متمرّس ينجز «قص شعر» في 20 دقيقة، بينما المتدرّب يحتاج 45. تضبط `custom_duration` لكل منهما من `/admin/providers` — **ولا شيء يتغيّر**. النظام يحجز 30 دقيقة (`service.duration_minutes`) للاثنين:

- **المتمرّس:** يخسر 10 دقائق من كل موعد. على 12 موعداً يومياً = **ساعتان مهدرتان يومياً** = مواعيد ضائعة وإيراد ضائع.
- **المتدرّب:** يتأخر 15 دقيقة كل موعد. بعد 4 مواعيد يتراكم تأخير **ساعة كاملة** ويبدأ العملاء بالانتظار.

لاحظ التناقض: **السعر المخصص (`custom_price`) يعمل** (`getEffectivePrice` منفَّذة بالكامل)، بينما المدة المخصصة لا. أي أن نصف ميزة «التخصيص لكل مزوّد» يعمل والنصف الآخر لا — وهذا أكثر إرباكاً من غياب الميزة كلياً.

### الإصلاح

```php
// طبّق نفس التعديل في BookingService.php و ServiceAvailabilityService.php
private function getEffectiveDuration(User $provider, Service $service): int
{
    $pivot = DB::table('provider_service')
        ->where('provider_id', $provider->id)
        ->where('service_id', $service->id)
        ->where('is_active', true)              // ✅ اتساق مع getEffectivePrice
        ->first();

    $duration = $pivot?->custom_duration ?? $service->duration_minutes;

    return max(1, (int) $duration);             // ✅ حارس ضد 0 أو NULL
}
```

**قبل النشر — تحقق من البيانات الموجودة:**

```sql
SELECT provider_id, service_id, custom_duration
FROM provider_service
WHERE custom_duration IS NOT NULL AND custom_duration <> (
    SELECT duration_minutes FROM services WHERE services.id = provider_service.service_id
);
```

هذا يُريك أي مدد ستتغيّر فعلياً بمجرد تفعيل الكود. **لا تنشر هذا الإصلاح بلا مراجعة النتيجة** — قد تكون هناك قيم `custom_duration` أُدخلت بالخطأ ولم يلاحظها أحد لأنها كانت معطّلة أصلاً.

---

## 🟡 BOOK-06 — فحص التكرار يرفض حجوزات مشروعة

**الموقع:** [`app/Services/BookingValidationService.php:272-287`](app/Services/BookingValidationService.php#L272-L287)

```php
$existingBooking = Appointment::where('customer_id', $customer->id)
    ->where('start_time', $startTime)
    ->whereIn('status', [AppointmentStatus::PENDING->value])
    ->whereHas('services', function ($query) use ($serviceIds) {
        $query->whereIn('services.id', $serviceIds);       // ⚠️ تطابق خدمة واحدة يكفي
    })
    ->exists();
```

`whereHas` + `whereIn` تعني: «هل يوجد حجز يشترك معي في **خدمة واحدة على الأقل**؟» — بينما رسالة الخطأ تقول «نفس الوقت **ونفس الخدمات**».

**مثال على الرفض الخاطئ:** عميل لديه حجز الساعة 10:00 لـ [قص شعر]. يحاول حجز 10:00 مع حلاق آخر لـ [قص شعر + حلاقة ذقن] — يُرفض بحجة التكرار رغم اختلاف الطلب.

**الإصلاح:**

```php
$serviceIds = collect($serviceIds)->sort()->values();

$existingBooking = Appointment::where('customer_id', $customer->id)
    ->where('start_time', $startTime)
    ->whereIn('status', [AppointmentStatus::PENDING->value])
    ->withCount('services')
    ->having('services_count', '=', $serviceIds->count())
    ->whereDoesntHave('services', fn ($q) => $q->whereNotIn('services.id', $serviceIds))
    ->exists();
```

---

## 🟡 BOOK-07 — حدّ الحجوزات اليومي قابل للتجاوز

**الموقع:** [`app/Services/BookingValidationService.php:326-344`](app/Services/BookingValidationService.php#L326-L344)

```php
$todayBookingsCount = Appointment::where('customer_id', $customer->id)
    ->whereDate('appointment_date', $date)
    ->whereIn('status', [AppointmentStatus::PENDING->value])    // ⚠️ PENDING فقط
    ->count();
```

العدّ يشمل `PENDING` فقط. عند إنهاء أي موعد يصبح `COMPLETED` (`InvoiceFinalizationService:235`) ويسقط من العدّ — فيتحرّر مكان جديد. مهاجم يستطيع تجاوز الحد بلا حدود عبر إلغاء الحجوزات (`USER_CANCELLED` أيضاً غير محسوبة).

```php
->whereNotIn('status', [
    AppointmentStatus::USER_CANCELLED->value,
    AppointmentStatus::ADMIN_CANCELLED->value,
])                                                    // ✅ كل ما ليس ملغى يُحسب
```

وأضِف `lockForUpdate()` داخل معاملة الحجز (`BOOK-02`) وإلا فالعدّ نفسه عرضة للسباق.

---

## 🟡 BOOK-08 — إلغاء موعد بعد بدايته

**الموقع:** [`app/Services/BookingService.php:429-436`](app/Services/BookingService.php#L429-L436)

```php
public function cancelBooking(Appointment $appointment, ?string $reason = null): bool
{
    if (! in_array($appointment->status, [AppointmentStatus::PENDING])) {
        throw new InvalidArgumentException('Only pending appointments can be cancelled');
    }
    return $appointment->cancel($reason);        // ⚠️ لا فحص على start_time
}
```

`AppointmentService::cancelAppointment` (المسار الآخر) **يفحص** أن وقت البدء في المستقبل. أما `POST /api/bookings/{id}/cancel` فلا. عميل يستطيع إلغاء موعد بدأ فعلاً أو انتهى (ما دام `PENDING`) — فيتهرّب من رسوم عدم الحضور ويشوّه إحصاءات الإلغاء.

```php
if ($appointment->start_time->isPast()) {
    throw new InvalidArgumentException(__('booking.cannot_cancel_started'));
}

$hours = (int) get_setting('cancellation_hours', 24);      // الإعداد موجود لديك وغير مستخدم!
if ($appointment->start_time->diffInHours(now()) < $hours) {
    throw new InvalidArgumentException(__('booking.cancellation_window_passed', ['hours' => $hours]));
}
```

> **ملاحظة:** الإعداد `cancellation_hours = "24"` موجود في قاعدة بياناتك لكن **لا يُقرأ في أي مكان في الكود**. سياسة الإلغاء غير مطبَّقة إطلاقاً.

---

## 🟡 BOOK-09 — انهيار 500 عند خدمة غير موجودة في المسارات الداخلية

**الموقع:** [`app/Services/BookingService.php:180-183`](app/Services/BookingService.php#L180-L183)

```php
$service  = $servicesCollection->get($serviceData['service_id']);     // قد تُرجع null
$provider = $providersCollection->get($serviceData['provider_id']);   // قد تُرجع null

$this->validationService->validateProviderOffersService($provider, $service);
//                                                       ^^^^^^^^^ نوعان غير قابلين لـ null
```

توقيع الدالة `validateProviderOffersService(User $provider, Service $service)` لا يقبل `null` → **`TypeError` غير معالَج → 500**.

مسار الـ API محمي بـ `exists:services,id`، لكن **لوحة الموظفين تستدعي `createBooking` مباشرة** (`StaffDashboard:469, 525`) بلا نفس التحقق. حذف خدمة بينما موظف يملأ نموذج الحجز = انهيار.

```php
$service  = $servicesCollection->get($serviceData['service_id']);
$provider = $providersCollection->get($serviceData['provider_id']);

if (! $service) {
    throw new InvalidArgumentException(__('booking.service_not_found', ['id' => $serviceData['service_id']]));
}
if (! $provider) {
    throw new InvalidArgumentException(__('booking.provider_not_found', ['id' => $serviceData['provider_id']]));
}
```

---

## 🟡 BOOK-10 — `404` يُرجَع كـ `500`

**الموقع:** [`app/Http/Controllers/Api/BookingController.php:111-125`](app/Http/Controllers/Api/BookingController.php#L111-L125)

`BookingService::getBookingDetails` تُلقي `ModelNotFoundException` بشكل صحيح (السطر 463)، لكن الـ controller لا يلتقطها:

```php
} catch (InvalidArgumentException $e) {
    return response()->json([...], 403);
} catch (\Exception $e) {                          // ModelNotFoundException تسقط هنا
    return response()->json([...], 500);           // ⚠️ يجب أن تكون 404
}
```

```php
} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
    return response()->json([
        'success'    => false,
        'message'    => __('booking.not_found'),
        'error_type' => 'not_found',
    ], 404);
} catch (InvalidArgumentException $e) {
    // ... 403
}
```

نفس التصحيح مطلوب في `show()` و`cancel()`.

---

## 🔵 BOOK-11 — معامل `status` غير محقَّق يسبب 500

**الموقع:** [`app/Http/Controllers/Api/BookingController.php:73`](app/Http/Controllers/Api/BookingController.php#L73)

```php
$status = $request->query('status');
// ... ثم في BookingService:  $query->where('status', $status);
```

`GET /api/bookings?status[]=1&status[]=2` يمرّر مصفوفة إلى `where()` → استثناء قاعدة بيانات → 500.

```php
$validated = $request->validate([
    'status' => ['nullable', 'integer', Rule::in(array_column(AppointmentStatus::cases(), 'value'))],
]);
```

---

# 6. الطبقة المالية والفواتير والضرائب

> هذه أخطر أقسام التقرير بالنسبة لعمل ألماني خاضع لـ **GoBD** و**KassenSichV** و**§14 UStG**.

## 🔴 MON-01 — حساب ضريبة القيمة المضافة يختلف بين طبقتين — **مُثبت رقمياً**

**الموقع:** [`app/Services/TaxCalculatorService.php:15, 48-54`](app/Services/TaxCalculatorService.php#L15) مقابل [`app/Services/BookingService.php:254, 289-292`](app/Services/BookingService.php#L254)
**الحالة:** ✅ **مُثبت بتنفيذ فعلي** — شغّلت المنطقين على نفس المدخلات

### الشرح

النظام يحسب الضريبة العكسية في **مكانين بدقّتين مختلفتين**:

```php
// BookingService::calculateTotals() — يُستخدم عند إنشاء الحجز
$internalScale = 6;                                              // ✅ دقة عالية
$factor = bcadd('1', bcdiv($taxRate, '100', $internalScale), $internalScale);
$net    = bcdiv($gross, $factor, $internalScale);
$net    = $this->bcRound($net, 2);                               // ✅ تقريب صحيح في النهاية
```

```php
// TaxCalculatorService::extractTax() — يُستخدم عند إنشاء الفاتورة والدفع
private int $scale = 2;                                          // ⚠️ دقة داخلية = 2 فقط
$factor  = bcadd('1', bcdiv($rate, '100', $this->scale), $this->scale);
$netHigh = bcdiv($gross, $factor, $this->scale);                 // ⚠️ bcdiv تقتطع ولا تقرّب
```

**السبب الجذري:** `bcdiv` في PHP **تقتطع (truncate)** ولا تقرّب. القسمة بدقة 2 مباشرة تفقد الخانات التي كان يجب تقريبها.

### الإثبات — نفّذته على مشروعك

```
GROSS      | TaxCalculatorService(scale 2) | BookingService(scale 6)
-----------|------------------------------|------------------------
19.00      | net 15.96   tax 3.04         | net 15.97   tax 3.03    <-- اختلاف
25.00      | net 21.00   tax 4.00         | net 21.01   tax 3.99    <-- اختلاف
50.00      | net 42.01   tax 7.99         | net 42.02   tax 7.98    <-- اختلاف
35.00      | net 29.41   tax 5.59         | net 29.41   tax 5.59        متطابق
99.99      | net 84.02   tax 15.97        | net 84.03   tax 15.96   <-- اختلاف
19.99      | net 16.79   tax 3.20         | net 16.80   tax 3.19    <-- اختلاف
```

**5 من كل 6 أسعار شائعة تُنتج ضريبة مختلفة بسنت واحد.** والقيمة الصحيحة رياضياً هي عمود `BookingService`:
`19.00 ÷ 1.19 = 15.9663...` → يُقرَّب إلى **15.97**، لا 15.96.

### عطل ثانٍ أخطر — نِسَب الضريبة الكسرية تُقتطع

```
-- بنسبة ضريبة 19.5% --
TaxCalculatorService  factor = 1.19     (والصحيح 1.195)   net=100.42  tax=19.08
BookingService        factor = 1.195000                    net=100.00  tax=19.50
```

`bcdiv('19.5', '100', 2)` تُنتج `'0.19'` — **الخانة العشرية الثانية تُقتطع بالكامل**. أي نسبة ضريبة غير صحيحة تُعامَل وكأنها مقرَّبة لأقرب واحد صحيح. النسبة المخفّضة في ألمانيا (7%) تعمل صدفةً، لكن أي نسبة كسرية مستقبلية ستُحسب خطأً بفارق ~0.5%.

### أين يظهر الاختلاف عملياً

```
1. العميل يحجز خدمة بـ 50.00 يورو
   -> BookingService::calculateTotals (دقة 6)
   -> appointments: subtotal=42.02  tax_amount=7.98

2. الموظف يحصّل المبلغ من اللوحة
   -> InvoiceService::applyFinalAmount
   -> TaxCalculatorService::extractTax (دقة 2)
   -> invoices: subtotal=42.01  tax_amount=7.99

3. النتيجة: صف الموعد وصف الفاتورة يختلفان في ضريبة نفس المعاملة.
   الفاتورة المطبوعة للعميل تحمل الرقم الخاطئ.
```

### الأثر على الموقع

**1. مخالفة ضريبية.** إقرار ضريبة القيمة المضافة يُبنى على `invoices.tax_amount`. سنت خطأ في كل فاتورة تقريباً يتراكم: بـ 40 فاتورة يومياً × 300 يوم = **12,000 سنت (~120 يورو) انحراف سنوي** — مبلغ صغير، لكن **الانحراف المنهجي هو ما يثير علامة حمراء في التدقيق الألماني**، لا حجمه. المدقق سيسأل: «لماذا لا تتطابق أرقامك؟»

**2. فشل التسوية.** `appointments.tax_amount` و`invoices.tax_amount` لن يتطابقا أبداً لنفس المعاملة. أي تقرير تسوية سيُظهر فروقاً دائمة لا تفسير لها.

**3. عدم اتساق ظاهر للعميل.** ما يراه في التطبيق عند الحجز يختلف عن الإيصال المطبوع.

### الإصلاح

**الخطوة 1 — أصلح `TaxCalculatorService` (الجذر):**

```php
class TaxCalculatorService
{
    /** دقة داخلية عالية — التقريب يحدث في النهاية فقط، لا أثناء الحساب. */
    private const INTERNAL_SCALE = 10;

    public function extractTax($grossAmount, $taxRate, int $precision = 2): array
    {
        $this->validateInputs($grossAmount, $taxRate, $precision);

        $gross = $this->normalizeAmount($grossAmount);
        $rate  = $this->normalizeAmount($taxRate);

        // ❌ لا bcscale() — إنها حالة عامة تلوّث بقية الطلب. انظر MON-06.

        if (bccomp($rate, '0', self::INTERNAL_SCALE) === 0) {
            $net = $this->bcRound($gross, $precision);
            return ['net' => $net, 'tax' => $this->formatZero($precision), 'gross' => $net];
        }

        // ✅ كل خطوة وسيطة بدقة 10
        $factor  = bcadd('1', bcdiv($rate, '100', self::INTERNAL_SCALE), self::INTERNAL_SCALE);
        $netHigh = bcdiv($gross, $factor, self::INTERNAL_SCALE);
        $taxHigh = bcsub($gross, $netHigh, self::INTERNAL_SCALE);

        // ✅ التقريب في النهاية فقط
        $net   = $this->bcRound($netHigh, $precision);
        $tax   = $this->bcRound($taxHigh, $precision);
        $grossR = $this->bcRound($gross, $precision);

        // ✅ التسوية: net + tax == gross دائماً
        $diff = bcsub($grossR, bcadd($net, $tax, $precision), $precision);

        if (bccomp($diff, '0', $precision) !== 0) {
            $tax = bcadd($tax, $diff, $precision);      // اضبط الضريبة لا الصافي
        }

        return ['net' => $net, 'tax' => $tax, 'gross' => $grossR];
    }
}
```

**قرار تصميمي مهم — لماذا نضبط الضريبة لا الصافي؟** الكود الحالي يضبط «القيمة الأكبر» (السطر 69)، وهذا غير حتمي: يضبط الصافي أحياناً والضريبة أحياناً حسب النسبة. للاتساق المحاسبي، **اضبط الضريبة دائماً** — لأن الصافي هو الأساس الذي يُبنى عليه سعر الخدمة، والضريبة مشتقة منه. وهذا أيضاً ما يفعله `BookingService` (السطر 314)، فيتحقق الاتساق.

**الخطوة 2 — وحّد المصدر:**

```php
// BookingService::calculateTotals() — احذف منطق bcmath المكرر واستدعِ الخدمة
foreach ($preparedServices as $service) {
    $result = $this->taxCalculator->extractTax((string) $service['price'], $taxRate, 2);

    $netTotal   = bcadd($netTotal,   $result['net'],   2);
    $taxTotal   = bcadd($taxTotal,   $result['tax'],   2);
    $grossTotal = bcadd($grossTotal, $result['gross'], 2);
}
```

**الخطوة 3 — اختبار يحرس التطابق:**

```php
it('produces identical VAT in the booking and the invoice layer', function (string $gross) {
    $viaCalculator = app(TaxCalculatorService::class)->extractTax($gross, '19', 2);

    $appointment = createBookingWithServicePrice($gross);
    $invoice     = app(InvoiceService::class)->applyFinalAmount($appointment->invoice);

    expect($invoice->tax_amount)->toBe($appointment->tax_amount)
        ->and($invoice->tax_amount)->toBe($viaCalculator['tax']);
})->with(['19.00', '25.00', '50.00', '99.99', '19.99', '35.00', '7.50']);
```

### فكرة الإصلاح العميقة

قاعدة الحساب المالي: **احسب بدقة أعلى بكثير مما تحتاج، وقرّب مرة واحدة فقط في النهاية.** الخطأ هنا هو التقريب أثناء الحساب (`bcdiv` بدقة 2) — وهو ينشر الخطأ بدل أن يحصره.

وقاعدة معمارية: **يجب أن يكون هناك تنفيذ واحد فقط لحساب الضريبة في المشروع كله.** حالياً لديك ثلاثة: `BookingService::calculateTotals` (دقة 6)، `TaxCalculatorService::extractTax` (دقة 2)، و`InvoiceFinalizationService::calculateReverseTax` (دقة 6 مع `bcscale` عام) — بالإضافة إلى رابع في `BookingService::addServiceDifferentProvider` يستخدم `float` (انظر `MON-07`). **أربعة تنفيذات لنفس المعادلة.**

---

## 🔴 MON-02 — أي موظف يُنهي فاتورة بأي مبلغ يختاره

**الموقع:** [`app/Livewire/StaffDashboard.php:79-82, 843-847`](app/Livewire/StaffDashboard.php#L79-L82)
**الحالة:** ✅ مؤكد بالكود

### الشرح

```php
// StaffDashboard.php:79-82 — خصائص Livewire عامة، بلا #[Validate] وبلا حدود
public float  $paymentAmount   = 0;
public float  $paymentBaseline = 0;
public string $paymentType     = '2';
```

```php
// StaffDashboard.php:843-847
$staffChangedAmount = abs($this->paymentAmount - $this->paymentBaseline) >= 0.005;

$invoice = $invoiceService->applyFinalAmount(
    $invoice,
    $staffChangedAmount ? (float) $this->paymentAmount : null
);
```

**الخصائص العامة في Livewire تُحدَّث من المتصفح.** أي قيمة يرسلها العميل تُسنَد مباشرة قبل تنفيذ الإجراء. والحماية الوحيدة هي `dashDenyOnAppointment('take_payment', ...)` — **لا توجد صلاحية `apply_discount`، ولا حدّ أقصى للخصم، ولا تحقق من القيمة**. بحثتُ في الملف كاملاً: كلمة `apply_discount` غير موجودة.

### سيناريو استغلال

```javascript
// من وحدة تحكم المتصفح داخل لوحة الموظفين، لأي موظف يملك take_payment:
Livewire.find(
  document.querySelector('[wire\\:id]').getAttribute('wire:id')
).set('paymentAmount', 0.01);

// ثم اضغط زر «تحصيل» بشكل طبيعي
```

النتيجة على فاتورة بـ 200 يورو:

| الحقل | القيمة |
|---|---|
| `invoices.total_amount` | `0.01` |
| `invoices.discount_amount` | `199.99` |
| `invoices.status` | `PAID` |
| `appointments.status` | `COMPLETED` |
| `payments.amount` | `0.01` |

الخدمة قُدِّمت، والفاتورة أُغلقت رسمياً، **و199.99 يورو لم تدخل الصندوق أبداً**. والسجل يُظهر «خصماً» تجارياً مشروعاً في الظاهر.

**ونقطة إيجابية مهمة:** فحصتُ `applyFinalAmount` وهي **تحصر القيمة** بشكل صحيح:

```php
// InvoiceService.php:583-589
if (bccomp($final, '0', 2) < 0)          { $final = '0.00'; }        // ✅ لا سالب
if (bccomp($final, $itemsGross, 2) > 0)  { $final = $itemsGross; }   // ✅ لا زيادة
```

فالمبالغ السالبة والدفع الزائد محميّان. **المشكلة الوحيدة — والحرجة — هي غياب أي حدّ أدنى وأي صلاحية.** الخصم بنسبة 100% مسموح تماماً.

### الأثر على الموقع

- **اختلاس داخلي غير قابل للكشف.** الموظف يأخذ 200 يورو نقداً من العميل، ويُسجّل 0.01 في النظام، ويحتفظ بالفرق. أرقامك متسقة داخلياً تماماً — لن تكتشف ذلك أبداً من التقارير.
- **مع `AUTHZ-01`،** الموظف المفصول يستطيع فعل هذا أيضاً.
- **مخالفة GoBD:** الخصومات يجب أن تكون مبرَّرة وقابلة للتدقيق. `discount_amount = 199.99` بلا سبب ولا موافقة لا يصمد أمام مدقّق.

### الإصلاح

**الطبقة 1 — تحقق من الخصائص:**

```php
use Livewire\Attributes\Validate;

#[Validate('required|numeric|min:0')]
public float $paymentAmount = 0;

#[Validate('required|in:1,2,3')]      // PAID_ONLINE / CASH / CARD فقط
public string $paymentType = '2';
```

**الطبقة 2 — صلاحية خصم + سقف على مستوى الخادم:**

```php
public function processPayment()
{
    if (! $this->selectedAppointmentId) return;

    $appointment = Appointment::with(['invoice', 'parent.invoice', 'children'])
        ->find($this->selectedAppointmentId);
    if (! $appointment) return;

    if ($this->dashDenyOnAppointment('take_payment', $appointment)) return;

    $this->validate();

    try {
        $invoiceOwner    = $appointment->parent ?? $appointment;
        $invoiceService  = app(InvoiceService::class);

        $invoice = $invoiceOwner->invoice()->first()
            ?? $invoiceService->createDtaftInvoiceFromAppointment($invoiceOwner, 'cash', 0);

        $invoice = $invoiceService->rebuildAggregatedInvoice($invoiceOwner);

        // ✅ المبلغ المرجعي يُقرأ من قاعدة البيانات، لا من المتصفح
        $authoritativeTotal = (float) $invoice->items()->sum('total_amount');

        $requested = round((float) $this->paymentAmount, 2);
        $discount  = round($authoritativeTotal - $requested, 2);

        if ($discount > 0.005) {
            // ✅ الخصم يحتاج صلاحية منفصلة
            if (! $this->dashCan('apply_discount')) {
                $this->dispatch('notify', type: 'error',
                    message: __('dashboard.payment_modal.discount_not_allowed'));
                return;
            }

            // ✅ سقف على نسبة الخصم
            $maxPercent  = (float) get_setting('max_discount_percent', 20);
            $maxDiscount = round($authoritativeTotal * $maxPercent / 100, 2);

            if ($discount > $maxDiscount) {
                $this->dispatch('notify', type: 'error',
                    message: __('dashboard.payment_modal.discount_exceeds_limit',
                               ['max' => $maxPercent]));
                return;
            }

            // ✅ أثر تدقيق صريح
            Log::info('Invoice discount applied', [
                'invoice_id'  => $invoice->id,
                'staff_id'    => auth()->id(),
                'staff_name'  => auth()->user()->full_name,
                'items_total' => $authoritativeTotal,
                'charged'     => $requested,
                'discount'    => $discount,
            ]);
        }

        $invoice = $invoiceService->applyFinalAmount($invoice, $requested);

        // ... باقي الإنهاء
    } catch (\Throwable $e) {
        report($e);
        $this->dispatch('notify', type: 'error', message: __('dashboard.payment_modal.error'));
    }
}
```

**الطبقة 3 — أضِف الصلاحية والإعداد:**

```php
Permission::findOrCreate('StaffDashboard:apply_discount', 'web');   // لـ admin/manager فقط
SalonSetting::updateOrCreate(['key' => 'max_discount_percent'], ['value' => 20]);
```

### فكرة الإصلاح العميقة

**القاعدة الذهبية: لا تثق أبداً بمبلغ مالي قادم من العميل.** الخاصية العامة في Livewire هي مُدخل مستخدم بامتياز، تماماً كحقل `<input>`. المبلغ المرجعي (`items()->sum('total_amount')`) يجب أن يُقرأ من قاعدة البيانات داخل نفس المعاملة، ويكون مُدخل المستخدم **مقترحاً** يُقاس ضده، لا مصدر حقيقة.

والقاعدة الثانية: **«تحصيل الدفع» و«منح خصم» عمليتان تجاريتان مختلفتان بمستويَي ثقة مختلفين.** كل كاشير يحصّل؛ ليس كل كاشير يمنح خصماً. دمجهما في صلاحية واحدة (`take_payment`) خطأ في نمذجة الصلاحيات.

---

## 🔴 MON-03 — فواتير مكرّرة بأرقام غير فريدة (خرق GoBD)

**الموقع:** [`app/Services/InvoiceFinalizationService.php:26-45`](app/Services/InvoiceFinalizationService.php#L26-L45) + **قاعدة البيانات**
**الحالة:** ✅ مؤكد بالكود + **مؤكد بفحص فهارس قاعدة البيانات الحيّة**

### الشرح

```php
if ($invoice->status !== InvoiceStatus::DRAFT) {      // (1) قراءة — خارج المعاملة!
    throw new \InvalidArgumentException('يمكن فقط تحويل الفواتير Draft...');
}

DB::beginTransaction();                                // (2) المعاملة تبدأ بعد الفحص

try {
    $invoiceNumber = Invoice::generateInvoiceNumber(); // (3) توليد الرقم
    $invoice->update(['invoice_number' => $invoiceNumber, 'status' => InvoiceStatus::PAID]);
    // ...
    $payment = $this->createPaymentRecord($invoice, $paymentType, $amountPaid, $tseData);
```

فحص الحماية من التكرار (السطر 26) **خارج المعاملة وبلا قفل صفّي**. نفس نمط TOCTOU في `BOOK-02`.

**والأخطر — لا يوجد أي قيد فريد يحمي.** تحققتُ من فهارس قاعدتك:

```sql
-- فهارس جدول invoices (فحص حقيقي على قاعدتك)
PRIMARY (id)
invoices_appointment_id_foreign (appointment_id)      -- فهرس عادي، ليس فريداً
invoices_customer_id_foreign (customer_id)
-- لا UNIQUE على invoice_number
-- لا UNIQUE على appointment_id  ->  موعد واحد يمكن أن يحمل فاتورتين!

-- فهارس جدول payments
PRIMARY (id)
payments_created_at_index (created_at)
payments_paymentable_index (paymentable_type, paymentable_id)
payments_payment_method_id_foreign (payment_method_id)
-- لا UNIQUE على payment_number
```

### سيناريو الحادث

موظف يضغط زر «تحصيل» مرتين لأن الاتصال بطيء (سلوك شائع جداً):

```
الزمن  الطلب أ                                  الطلب ب
─────  ──────────────────────────────           ──────────────────────────────
t=0    status === DRAFT  ->  يمر ✓

t=1                                             status === DRAFT  ->  يمر ✓
                                                (أ لم يُثبّت بعد)

t=2    BEGIN
       generateInvoiceNumber() -> INV-0042
       UPDATE invoice SET number='INV-0042', status=PAID
       INSERT payment (200.00)
       COMMIT

t=3                                             BEGIN
                                                generateInvoiceNumber() -> INV-0042 (مكرر!)
                                                UPDATE invoice SET number='INV-0042'
                                                INSERT payment (200.00)
                                                COMMIT

النتيجة: رقم فاتورة مكرر + سجلَّا دفع بـ 200 يورو لمعاملة واحدة = 400 يورو "إيراد" وهمي.
```

### الأثر على الموقع

**1. خرق مباشر لـ GoBD.** القانون الألماني يشترط ترقيم فواتير **فريداً ومتسلسلاً وبلا فجوات**. رقمان متطابقان لفاتورتين مختلفتين خرق أساسي يُبطل السجل المحاسبي كله لدى المدقق.

**2. إيراد مضاعف كاذب.** سجلّا دفع لمعاملة واحدة يضخّمان تقاريرك ويجعلانك تدفع ضريبة على مال لم تقبضه.

**3. نفس الخلل ينطبق على `payments.payment_number` و`appointments.number`** — الثلاثة بلا قيد فريد.

### الإصلاح

**الطبقة 1 — قيود قاعدة البيانات (الأهم — نفّذها أولاً):**

```php
// database/migrations/xxxx_add_financial_uniqueness_constraints.php
public function up(): void
{
    // نظّف أي تكرارات قائمة قبل إضافة القيود
    Schema::table('invoices', function (Blueprint $table) {
        $table->unique('invoice_number', 'invoices_number_unique');
        $table->unique('appointment_id', 'invoices_appointment_unique');   // فاتورة واحدة لكل موعد
    });

    Schema::table('payments', function (Blueprint $table) {
        $table->unique('payment_number', 'payments_number_unique');
    });

    Schema::table('appointments', function (Blueprint $table) {
        $table->unique('number', 'appointments_number_unique');
    });
}
```

> **تحذير قبل التنفيذ:** ابحث عن التكرارات القائمة أولاً، وإلا فشلت الـ migration:
> ```sql
> SELECT invoice_number, COUNT(*) c FROM invoices
> WHERE invoice_number IS NOT NULL GROUP BY invoice_number HAVING c > 1;
>
> SELECT appointment_id, COUNT(*) c FROM invoices GROUP BY appointment_id HAVING c > 1;
> ```

**الطبقة 2 — قفل صفّي داخل المعاملة:**

```php
public function finalizeDraftInvoice(
    Invoice $invoice, string $paymentType, float $amountPaid,
    ?string $notes = null, bool $applyTse = true
): Invoice {
    return DB::transaction(function () use ($invoice, $paymentType, $amountPaid, $notes, $applyTse) {

        // ✅ أعِد القراءة مع قفل — يُسلسِل الطلبات المتزامنة
        $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

        // ✅ فحص التكرار داخل المعاملة وبعد القفل
        if ($invoice->status !== InvoiceStatus::DRAFT) {
            throw new \InvalidArgumentException(
                'الفاتورة مُنهاة مسبقاً. الحالة الحالية: ' . $invoice->status->getLabel()
            );
        }

        if (! $invoice->appointment) {
            throw new \InvalidArgumentException('الفاتورة غير مرتبطة بحجز');
        }

        // ... باقي المنطق كما هو ...
    }, 3);
}
```

**الطبقة 3 — توليد أرقام آمن أمام التزامن:**

```php
// app/Services/DocumentNumberGenerator.php
public function next(string $type, string $prefix, int $padding = 4): string
{
    return DB::transaction(function () use ($type, $prefix, $padding) {
        // ✅ صف عدّاد مقفول — التسلسل مضمون بلا فجوات
        $counter = DB::table('document_counters')
            ->where('type', $type)
            ->lockForUpdate()
            ->first();

        if (! $counter) {
            DB::table('document_counters')->insert(['type' => $type, 'current' => 0]);
            $counter = (object) ['current' => 0];
        }

        $next = $counter->current + 1;

        DB::table('document_counters')->where('type', $type)->update(['current' => $next]);

        return $prefix . '-' . str_pad((string) $next, $padding, '0', STR_PAD_LEFT);
    });
}
```

### فكرة الإصلاح العميقة

**ترقيم المستندات المالية مشكلة تزامن، لا مشكلة تنسيق نصّي.** النمط الشائع `SELECT MAX(number) + 1` أو حلقة `while (exists())` مكسور بنيوياً تحت التزامن — لأن قراءتين متزامنتين تريان نفس القيمة.

الحل الصحيح: **جدول عدّادات مقفول** يجعل التسلسل ذرّياً. ولا تعتمد على `AUTO_INCREMENT` هنا، لأنه يترك **فجوات** عند التراجع (rollback) — وGoBD يشترط التسلسل بلا فجوات.

---

## 🔴 MON-04 — الدفع الجزئي يُعلَّم كمدفوع بالكامل

**الموقع:** [`app/Services/InvoiceFinalizationService.php:48, 190-204`](app/Services/InvoiceFinalizationService.php#L48)
**الحالة:** ✅ مؤكد بالكود

### الشرح

```php
// السطر 48 — القيمة ثابتة، لا تُشتق من المبلغ المدفوع أبداً
$invoiceStatus = InvoiceStatus::PAID;
```

بينما توجد في نفس الملف دالة **مكتوبة بالكامل وصحيحة** لهذا الغرض بالضبط... **ولا تُستدعى أبداً**:

```php
// السطر 190-204 — كود ميت
private function determineInvoiceStatus(float $totalAmount, float $amountPaid): InvoiceStatus
{
    $comparison = bccomp((string) $amountPaid, (string) $totalAmount, 2);

    if ($comparison === 0)       { return InvoiceStatus::PAID; }
    elseif ($comparison === -1)  { return InvoiceStatus::PARTIALLY_PAID; }
    else                         { return InvoiceStatus::PAID; }
}
```

بحثتُ في المشروع كله: `determineInvoiceStatus` **غير مستدعاة في أي مكان**. المطوّر كتب المنطق الصحيح ثم نسي توصيله.

### السيناريو

عميل بفاتورة 200 يورو يدفع 120 نقداً ويعد بالباقي غداً. الموظف يُدخل 120 في اللوحة:

| الحقل | القيمة الفعلية | القيمة الصحيحة |
|---|---|---|
| `invoices.status` | `PAID` ❌ | `PARTIALLY_PAID` |
| `invoices.discount_amount` | `80.00` ❌ | `0.00` |
| `appointments.status` | `COMPLETED` | `COMPLETED` |
| `appointments.payment_status` | `PAID_ONSTIE_CASH` ❌ | `PENDING` |

**الـ 80 يورو المتبقية تختفي من النظام تماماً.** والأسوأ أنها تُسجَّل كـ**خصم** — أي أن النظام يقول إنك منحت العميل 80 يورو تخفيضاً، لا أنه مدين لك بها. **لا يوجد أي تقرير يمكن أن يُظهر لك هذا الدين.**

### تصحيح على التقرير السابق

التقرير القديم ادّعى (C-05) أن `PaymentStatus::from((int) $paymentType)` في السطر 219 **يرمي 500 دائماً وأن التحصيل مكسور كلياً**. **هذا خطأ.** المُستدعي (`StaffDashboard:849`) يمرّر `(string) $this->paymentType` وقيمتها الافتراضية `'2'`، و`(int) '2' = 2` → `PAID_ONSTIE_CASH`. **التحصيل يعمل.**

لكن **توجد مشكلة حقيقية في نفس السطر**، وهي أدق:

```php
// السطر 219
InvoiceStatus::PAID => PaymentStatus::from((int) $paymentType),
```

`$this->paymentType` خاصية Livewire عامة بلا `#[Validate]` (انظر `MON-02`). مهاجم يضبطها إلى `5` أو `4`:

```javascript
Livewire.find(id).set('paymentType', '5');   // REFUNDED
```

النتيجة: `invoices.status = PAID` بينما `appointments.payment_status = REFUNDED`. **حالة متناقضة مستحيلة** — فاتورة مدفوعة على موعد «مُسترجَع». وبنفس الطريقة `4` = `FAILED`. القيد `in:1,2,3` في `MON-02` يغلق هذا.

### الإصلاح

```php
// InvoiceFinalizationService::finalizeDraftInvoice

// ✅ استدعِ الدالة الموجودة أصلاً
$invoiceStatus = $this->determineInvoiceStatus(
    (float) $invoice->total_amount,
    $amountPaid
);

$invoice->update([
    // ✅ رقم الفاتورة يُخصَّص فقط عند السداد الكامل — GoBD يشترط ذلك
    'invoice_number' => $invoiceStatus === InvoiceStatus::PAID
        ? Invoice::generateInvoiceNumber()
        : $invoice->invoice_number,
    'status' => $invoiceStatus,
    // ...
]);
```

```php
// وفي updateAppointmentStatus — تحقق من صحة نوع الدفع
$paymentStatusValue = PaymentStatus::tryFrom((int) $paymentType);

if (! $paymentStatusValue || ! $paymentStatusValue->isSuccessful()) {
    throw new \InvalidArgumentException("نوع دفع غير صالح: {$paymentType}");
}

$paymentStatus = match ($invoiceStatus) {
    InvoiceStatus::PAID           => $paymentStatusValue,
    InvoiceStatus::PARTIALLY_PAID => PaymentStatus::PENDING,
    default                       => PaymentStatus::PENDING,
};
```

**ملاحظة على `PaymentStatus::from($paymentType)` في السطر 270:** هنا تُمرَّر **سلسلة** إلى تعداد مدعوم بـ `int`. الملف بلا `declare(strict_types=1)` فيعمل التحويل الضمني للسلاسل الرقمية — لكنه هشّ للغاية: إضافة `strict_types` لاحقاً (وهي ممارسة جيدة) ستكسر التحصيل فوراً. استخدم `tryFrom((int) $paymentType)` في الموضعين.

---

## 🔴 MON-05 — ثلاثة مسارات دفع مختلفة تُنتج سجلات مختلفة

**الموقع:** `InvoiceFinalizationService::finalizeDraftInvoice` · `InvoiceService::finalizeDraftInvoice:610` · `AppointmentsTable.php:395-460`
**الحالة:** ✅ مؤكد بمقارنة المسارات الثلاثة

### الشرح — ماذا يفعل كل مسار

| | لوحة الموظفين<br>`InvoiceFinalizationService` | `InvoiceService:610`<br>(ميت حالياً) | Filament<br>`AppointmentsTable:395` |
|---|:---:|:---:|:---:|
| يُنشئ صف `Payment` | ✅ | ❌ **مُعلَّق** (السطر 653) | ✅ |
| يربط `payment_method_id` | ❌ `null` (TODO السطر 265) | ❌ | ✅ |
| يُحدّث المواعيد المرتبطة (children) | ✅ | ❌ الأب فقط | ❌ |
| يُطبّق TSE | placeholder | ❌ | ❌ |
| الحالة الوسيطة للفاتورة | `DRAFT → PAID` | `DRAFT → PAID` | `DRAFT → PENDING → PAID` |
| يحفظ `invoice_data` | `array_merge` ✅ | **يستبدل** ❌ | — |
| يضبط `payment_method` | تسمية بشرية ⚠️ | تسمية بشرية ⚠️ | — |

### العواقب الملموسة

**1. جدول `payments` ناقص.** أي تسوية مالية تعتمد على `payments` ستفوّت كل ما حُصِّل عبر مسارات لا تُنشئ صفاً. وبما أن الحالة الوسيطة تختلف (`PENDING` في Filament مقابل `PAID` في اللوحة)، فإن `InvoiceFinalizationService` **يرفض** أي فاتورة مرّت بمسار Filament — لأنها لم تعد `DRAFT`.

**2. `payment_method` يتحوّل من قيمة آلية إلى تسمية بشرية:**

```php
// InvoiceFinalizationService:229
$appointmentUpdates = [
    'payment_status' => $paymentStatus,
    'payment_method' => $paymentStatus->label(),      // "Paid On site Cash"
];
```

بينما `BookingService:43` يقارن نفس الحقل بقيم آلية:

```php
$isConfirmed = $bookingData['is_confirmed'] ?? ($paymentMethod == 'cash');
```

بعد التحصيل يصبح `payment_method = "Paid On site Cash"` — فأي شرط `== 'cash'` يفشل صامتاً. الحقل يخدم غرضين متناقضين: قيمة آلية قبل الدفع، ونص عرض بعده.

**3. الموعد يُعلَّم `COMPLETED` عند الدفع** (السطر 235) — حتى لو دُفع مقدماً قبل تقديم الخدمة. «مدفوع» و«الخدمة قُدِّمت» بُعدان مستقلان دُمجا.

### الإصلاح

**الخطوة 1 — احذف `InvoiceService::finalizeDraftInvoice` (السطور 610-668).** إنها نسخة ميتة وأقل اكتمالاً، ووجودها بنفس الاسم يضمن أن يستدعيها أحد بالخطأ يوماً ما.

**الخطوة 2 — وحّد كل المسارات على `InvoiceFinalizationService`:**

```php
// AppointmentsTable.php — بدّل الكتلة كلها بنداء واحد
$finalized = app(InvoiceFinalizationService::class)->finalizeDraftInvoice(
    invoice:     $invoice,
    paymentType: (string) $paymentStatus->value,
    amountPaid:  (float) $data['amount_paid'],
    notes:       $data['notes'] ?? null,
);
```

**الخطوة 3 — أصلح `payment_method` ليبقى آلياً:**

```php
$appointmentUpdates = [
    'payment_status' => $paymentStatus,
    // ✅ قيمة آلية ثابتة — العرض يتم عبر $paymentStatus->label() في الواجهة
    'payment_method' => match ($paymentStatus) {
        PaymentStatus::PAID_ONSTIE_CASH => 'cash',
        PaymentStatus::PAID_ONSTIE_CARD => 'card',
        PaymentStatus::PAID_ONLINE      => 'online',
        default                         => $appointment->payment_method,
    },
];
```

**الخطوة 4 — اربط `payment_method_id`** (الـ TODO في السطر 265) وإلا بقي جدول `payment_methods` بلا فائدة.

### فكرة الإصلاح العميقة

**«تحصيل دفعة» عملية تجارية واحدة، ويجب أن يكون لها تنفيذ واحد.** ثلاثة تنفيذات تعني ثلاث حالات نهائية مختلفة لنفس الحدث التجاري — وهذا يجعل التسوية المحاسبية مستحيلة بنيوياً، لا صعبة فقط.

النمط الصحيح: **خدمة تطبيق واحدة** تملك العملية بالكامل، وكل واجهة (Livewire، Filament، API) مجرد مُدخل رفيع إليها. الواجهة تجمع البيانات وتعرض النتيجة؛ **المنطق التجاري لا يعيش في الواجهة أبداً.**

---

## 🟠 MON-06 — `bcscale()` يلوّث الحالة العامة للطلب

**الموقع:** [`app/Services/InvoiceFinalizationService.php:304`](app/Services/InvoiceFinalizationService.php#L304) + [`app/Services/TaxCalculatorService.php:35, 62`](app/Services/TaxCalculatorService.php#L35)

```php
// InvoiceFinalizationService:304
private function calculateReverseTax(float $totalWithTax, float $taxRate): array
{
    bcscale(6);                      // ⚠️ يغيّر الدقة الافتراضية لكل bcmath في الطلب
    // ...
}
```

```php
// TaxCalculatorService:35 و 62
bcscale($this->scale);               // -> 2
// ...
bcscale($precision);                 // -> يتغيّر مجدداً
```

`bcscale()` حالة عامة على مستوى العملية. أي استدعاء `bcadd($a, $b)` بلا معامل دقة صريح **بعد** هذه الدوال يستخدم الدقة المتبقية — لا الافتراضية.

**النتيجة:** حساب الضريبة يصبح **حساساً لترتيب الاستدعاءات**. إذا نُفّذ `TaxCalculatorService` قبل كود آخر، تصبح الدقة 2؛ وإذا نُفّذ `InvoiceFinalizationService` أولاً، تصبح 6. هذا يُنتج أخطاء غير قابلة للتكرار في الاختبارات — من أصعب أنواع العلل تشخيصاً.

**الإصلاح:** احذف كل نداءات `bcscale()` ومرّر الدقة صراحةً في كل عملية:

```php
$factor   = bcadd('1', bcdiv($rate, '100', 10), 10);
$subtotal = bcdiv($total, $factor, 10);
$tax      = bcsub($total, $subtotal, 10);
```

**قاعدة عامة:** كل نداء `bc*` في المشروع يجب أن يحمل معامل الدقة الثالث. أضِف قاعدة PHPStan/Pint تمنع `bcscale`.

---

## 🟠 MON-07 — تنفيذ رابع للضريبة بـ float

**الموقع:** [`app/Services/BookingService.php:696-703`](app/Services/BookingService.php#L696-L703)

```php
protected function addServiceDifferentProvider(/* ... */): Appointment
{
    $taxRate = (float) get_setting('tax_rate', 19);

    if ($taxRate > 0) {
        $net = round($price / (1 + ($taxRate / 100)), 2);      // ⚠️ حساب float
        $tax = round($price - $net, 2);
    }
```

هذا يناقض مباشرة تعليمات `Agent.md` القسم 12.2: **«لا تستخدم أبداً حساب float للنقود»**. والمشروع بأكمله يستخدم bcmath — إلا هنا.

كل موعد فرعي (child) يُنشأ بإضافة خدمة لمزوّد آخر يحمل ضريبة محسوبة بطريقة رابعة مختلفة.

```php
$taxCalc = app(TaxCalculatorService::class)->extractTax((string) $price, (string) $taxRate, 2);
$net = $taxCalc['net'];
$tax = $taxCalc['tax'];
```

كما أن `recalculateAnchorTotals` (السطر 762-773) يستخدم `ReflectionMethod` للوصول إلى دالة `private`، ويحوّل الأسعار إلى `float` في الطريق:

```php
$services = $appointment->services_record->map(fn ($s) => [
    'price' => (float) $s->price,          // ⚠️ decimal -> float
])->toArray();

$ref = new \ReflectionMethod($this, 'calculateTotals');
$ref->setAccessible(true);                 // ⚠️ التفاف على التغليف
```

**الإصلاح:** اجعل `calculateTotals` بـ `protected` واستدعِها مباشرة، ومرّر الأسعار كسلاسل نصية:

```php
$services = $appointment->services_record->map(fn ($s) => [
    'price'            => (string) $s->price,      // ✅ يبقى سلسلة
    'duration_minutes' => (int) $s->duration_minutes,
])->toArray();

return $this->calculateTotals($services);          // ✅ نداء مباشر
```

---

## 🟠 MON-08 — تكامل Fiskaly TSE مكتوب بالكامل لكنه غير موصول

**الموقع:** [`app/Services/InvoiceFinalizationService.php:116-173`](app/Services/InvoiceFinalizationService.php#L116-L173) مقابل [`app/Services/Fiskaly/`](app/Services/Fiskaly/)

### الشرح

المشروع يحتوي على تنفيذ Fiskaly حقيقي وكامل — **1,573 سطراً** موزّعة على 6 ملفات:

```
app/Services/Fiskaly/TransactionService.php   437 سطر
app/Services/Fiskaly/FiskalyService.php       343 سطر
app/Services/Fiskaly/TssService.php           311 سطر
app/Services/Fiskaly/ReceiptService.php       273 سطر
app/Services/Fiskaly/FiskalyClient.php        247 سطر
app/Services/Fiskaly/ClientService.php        212 سطر
```

مع اختبارات وحدة لكل منها. لكن `applyTSESignature` — الدالة الوحيدة التي تُستدعى فعلياً عند الدفع — **لا تستدعي أياً منها**:

```php
private function applyTSESignature(Invoice $invoice, string $paymentType, float $amountPaid): array
{
    // TODO: الربط الفعلي مع TSE Cloud Service

    return [
        'tse_enabled'      => false,
        'transaction_number' => null,
        'signature_data'   => null,
        'note'             => 'TSE not yet implemented - placeholder data',
    ];

    // الكود الفعلي سيكون شيء مثل:  <-- 28 سطراً مُعلَّقة
}
```

**لاحظ:** حتى لو ضبطتَ `FISKALY_ENABLED=true`، فإن `finalizeDraftInvoice:41-43` يستدعي `applyTSESignature` التي تُرجع `'tse_enabled' => false` وبيانات فارغة. **تفعيل المفتاح لا يفعل شيئاً.**

### التوثيق متناقض

- `Agent.md` القسم 1 يقول: «Fiskaly TSE (**placeholder** — future integration)».
- الذاكرة الدائمة للمشروع تقول: «TSE/Fiskaly معطّل عمداً بـ `FISKALY_ENABLED=false`، وإعادة التفعيل سطر واحد».

**كلاهما غير دقيق.** التنفيذ ليس placeholder (1,573 سطراً حقيقية)، وإعادة التفعيل **ليست سطراً واحداً** — تحتاج ربط `applyTSESignature` بـ `TransactionService` فعلياً.

### الأثر على الموقع

**§146a AO + KassenSichV يوجبان TSE على كل نظام كاشير في ألمانيا منذ 2020.** التشغيل بلا TSE يعرّضك لـ:
- غرامات تصل إلى **25,000 يورو** (§379 AO)
- **رفض السجلات المحاسبية** في التدقيق وتقدير الإيراد جزافياً (§162 AO)

**قرار التشغيل بلا TSE قرارك أنت وقد تكون له مبرراتك** (تسجيل Fiskaly، اختبار، توقيت). لكن يجب أن يكون **قراراً واعياً وموثّقاً**، لا مفاجأة تكتشفها عند أول تدقيق. وحالياً التوثيق يوحي بأن الأمر أبسط مما هو.

### الإصلاح — إن قررت التفعيل

```php
private function applyTSESignature(Invoice $invoice, string $paymentType, float $amountPaid): array
{
    $transactionService = app(\App\Services\Fiskaly\TransactionService::class);

    try {
        $response = $transactionService->signReceipt($invoice, $paymentType, $amountPaid);

        return [
            'tse_enabled'        => true,
            'tse_provider'       => 'fiskaly',
            'transaction_number' => $response['number'],
            'certified_timestamp'=> $response['time_end'],
            'signature_value'    => $response['signature']['value'],
            'signature_counter'  => $response['signature']['counter'],
            'tse_serial_number'  => $response['tss_serial_number'],
            'log_time'           => $response['log']['timestamp'],
        ];
    } catch (\Throwable $e) {
        report($e);

        // ✅ الوضع المؤقت المسموح قانوناً: سجّل سبب غياب التوقيع صراحةً
        $invoice->update([
            'signature_missing_reason' => 'TSE unavailable: ' . $e->getMessage(),
        ]);

        if (! config('fiskaly.offline_mode_enabled')) {
            throw $e;      // fail-closed: لا تُنهِ الفاتورة بلا توقيع
        }

        return $this->createPlaceholderTSE();
    }
}
```

**إن قررت التأجيل:** وثّق القرار صراحةً في `Agent.md` مع تاريخ مستهدف، وصحّح الذاكرة الدائمة، **وصحّح `applyTSESignature` لتُلقي استثناءً واضحاً إذا كان `FISKALY_ENABLED=true`** — بدل الإيهام بأنها تعمل.

---

## 🟠 MON-09 — الفاتورة المسودة تُنشأ دائماً بـ `'cash'` ومبلغ صفر

**الموقع:** [`app/Services/BookingService.php:131-135`](app/Services/BookingService.php#L131-L135)

```php
$InvoiceService->createDtaftInvoiceFromAppointment(
    $appointment,
    'cash',              // ⚠️ ثابت — حتى لو payment_method = 'online'
    0                    // ⚠️ ثابت — حتى لو markAsPaid = true
);
```

مع `BOOK-03` (الموعد يُعلَّم `PAID_ONSTIE_CASH`)، تنشأ حالة متناقضة:

| المصدر | ما يقوله |
|---|---|
| `appointments.payment_status` | `PAID_ONSTIE_CASH` — مدفوع |
| `invoices.status` | `DRAFT` — لم يُفوتَر بعد |
| `payments` | لا يوجد صف — لم يُدفع شيء |

**ثلاثة مصادر تقول ثلاثة أشياء مختلفة عن نفس المعاملة.**

مشكلة إضافية: المعاملَان `$paymentType` و`$amountPaid` في `createDtaftInvoiceFromAppointment` يُستقبلان لكنهما **لا يُستخدمان إلا في `Log::info`** (السطر 413-414). `createDraftInvoice` لا يقرأهما إطلاقاً. معاملات وهمية تُوهم المُستدعي بأنها مؤثرة.

```php
// ✅ احذف المعاملات غير المستخدمة
public function createDraftInvoiceFor(
    Appointment $appointment,
    ?int $adjustedDuration = null
): Invoice {
    // ...
}
```

**ملاحظة على الاسم:** `createDtaftInvoiceFromAppointment` فيها خطأ إملائي (`Dtaft` ← `Draft`). أعِد التسمية مع إبقاء اسم مستعار مؤقت:

```php
/** @deprecated استخدم createDraftInvoiceFor() */
public function createDtaftInvoiceFromAppointment(...) {
    return $this->createDraftInvoiceFor(...);
}
```

---

## 🟡 MON-10 — حذف مواعيد مُسترجَعة يدمّر سجلاتها المالية

**الموقع:** [`app/Livewire/StaffDashboard.php:760, 779-788`](app/Livewire/StaffDashboard.php#L760)

```php
if (in_array($appointment->payment_status->value, [1, 2, 3]) || $appointment->status->value === 1) {
    // منع الحذف
}
```

**نقطة إيجابية:** الحماية موجودة وتغطي `PAID_ONLINE(1)` و`PAID_ONSTIE_CASH(2)` و`PAID_ONSTIE_CARD(3)` و`COMPLETED`. تصميم سليم.

**لكن القائمة تُغفل `REFUNDED(5)` و`PARTIALLY_REFUNDED(6)`.** موعد مُسترجَع مرّ بدورة مالية كاملة — فاتورة مرقّمة، وسجل دفع، وسجل استرجاع. وحذفه يمحو كل ذلك:

```php
DB::transaction(function () use ($appointment) {
    if ($appointment->invoice) {
        $appointment->invoice->items()->delete();
        $appointment->invoice->payments()->delete();      // ⚠️ حذف نهائي
        $appointment->invoice->delete();                  // ⚠️ حذف نهائي
    }
    $appointment->delete();
});
```

**وجدول `appointments` بلا `deleted_at`** — تحققتُ من الأعمدة، لا يوجد soft delete. الحذف **نهائي ولا رجعة فيه**.

**هذا خرق مباشر لمبدأ عدم القابلية للتغيير (Unveränderbarkeit) في GoBD:** السجلات المالية يجب ألا تُحذف أبداً، بل تُلغى بقيد عكسي (Storno).

```php
// استخدم الدالة المساعدة الموجودة بدل قائمة أرقام هشّة
if ($appointment->payment_status->isSuccessful()
    || in_array($appointment->payment_status, [PaymentStatus::REFUNDED, PaymentStatus::PARTIALLY_REFUNDED])
    || $appointment->status === AppointmentStatus::COMPLETED
    || $appointment->invoice?->invoice_number !== null) {     // ✅ أي فاتورة مرقّمة = غير قابلة للحذف

    $this->dispatch('notify', type: 'error',
        message: __('dashboard.appointment_modal.cannot_delete_paid'));
    return;
}
```

**والإصلاح الجذري — أضِف soft deletes:**

```php
Schema::table('appointments', fn (Blueprint $t) => $t->softDeletes());
Schema::table('invoices',     fn (Blueprint $t) => $t->softDeletes());
Schema::table('payments',     fn (Blueprint $t) => $t->softDeletes());
```

---

# 7. الإعدادات وقاعدة البيانات والتوطين

> كل ما في هذا القسم **مؤكد باستعلام قاعدة بياناتك الحيّة** — ليست افتراضات.

## 🔴 SET-01 — بيانات الشركة الضريبية فارغة تماماً → كل فاتورة باطلة قانونياً

**الموقع:** جدول `salon_settings`
**الحالة:** ✅ **مؤكد بقاعدة البيانات الحيّة**

### الشرح

استعلمتُ إعداداتك الفعلية:

```sql
SELECT `key`, `value` FROM salon_settings ORDER BY `key`;
```

| المفتاح | القيمة الفعلية | المطلوب |
|---|---|---|
| `company_name` | `""` **فارغ** | اسم الصالون القانوني الكامل |
| `company_address` | `""` **فارغ** | العنوان الكامل |
| `company_tax_number` | `""` **فارغ** | Steuernummer أو USt-IdNr |
| `company_phone` | `""` **فارغ** | رقم الهاتف |
| `company_email` | `""` **فارغ** | البريد الإلكتروني |

### الأثر على الموقع

**§14 Abs. 4 UStG** يوجب على كل فاتورة ألمانية أن تحمل — كحد أدنى:

1. الاسم الكامل وعنوان مقدّم الخدمة ← **فارغ**
2. Steuernummer أو USt-IdNr ← **فارغ**
3. تاريخ الإصدار ✓ موجود
4. رقم فاتورة متسلسل فريد ← موجود لكن **غير فريد** (`MON-03`)
5. وصف الخدمة ✓ موجود
6. المبلغ الصافي ونسبة الضريبة ومبلغ الضريبة ← موجود لكن **محسوب خطأً** (`MON-01`)

**النتيجة العملية:**

- كل فاتورة تطبعها **باطلة قانونياً**.
- **عملاؤك من الشركات لا يستطيعون خصم ضريبة المدخلات** (Vorsteuerabzug) من فواتيرك. أي عميل يطلب فاتورة لشركته سيرفضها محاسبه ويعود إليك. هذا يقصيك عن شريحة العملاء التجاريين بالكامل.
- عند التدقيق، فواتير بلا بيانات إصدار تُعتبر سجلات محاسبية غير سليمة.

**هذا ليس خطأً برمجياً — هذا بيانات ناقصة.** لكنه أسرع مانع إطلاق يمكن إصلاحه، ويستحق أن يكون أول ما تفعله.

### الإصلاح

```sql
UPDATE salon_settings SET value = '"LookUp Friseur GmbH"'                 WHERE `key` = 'company_name';
UPDATE salon_settings SET value = '"Musterstraße 123, 10115 Berlin"'      WHERE `key` = 'company_address';
UPDATE salon_settings SET value = '"DE123456789"'                          WHERE `key` = 'company_tax_number';
UPDATE salon_settings SET value = '"+49 30 12345678"'                      WHERE `key` = 'company_phone';
UPDATE salon_settings SET value = '"info@lookupfriseur.com"'               WHERE `key` = 'company_email';
```

> **انتبه للصيغة:** العمود `value` مُحوَّل بـ `'value' => 'json'` في `SalonSetting::$casts`. لذلك القيم النصية يجب أن تكون **JSON صالحاً** — أي محاطة بعلامتَي اقتباس مزدوجتين داخل السلسلة. لاحظ أن `max_booking_days` مخزَّن كـ `10` (رقم JSON) بينما `max_daily_bookings` مخزَّن كـ `"10"` (سلسلة JSON) — **عدم اتساق قائم في بياناتك**، انظر `SET-05`.

**والأفضل — امنع الطباعة بلا بيانات إصدار:**

```php
// app/Services/InvoiceFinalizationService.php — قبل الإنهاء
$required = ['company_name', 'company_address', 'company_tax_number'];

foreach ($required as $key) {
    if (blank(get_setting($key))) {
        throw new \RuntimeException(
            "لا يمكن إصدار فاتورة: إعداد '{$key}' فارغ. أكمل بيانات الشركة من إعدادات الصالون."
        );
    }
}
```

هذا يحوّل الخرق القانوني الصامت إلى خطأ واضح **قبل** إصدار أول فاتورة خاطئة.

---

## 🔴 SET-02 — العملة `USD` في صالون ألماني + أربع قيم متعارضة في الكود

**الموقع:** `salon_settings.currency` + 4 مواضع في الكود
**الحالة:** ✅ **مؤكد بقاعدة البيانات + بالكود**

### الشرح — فوضى العملات

```sql
SELECT `key`, `value` FROM salon_settings WHERE `key` IN ('currency', 'points_per_aed', 'branch_phone', 'branch_email');
-- currency        | "USD"                      <-- دولار أمريكي!
-- points_per_aed  | "1"                        <-- درهم إماراتي (بقايا بذور)
-- branch_phone    | "+971-4-123-4567"          <-- رقم إماراتي
-- branch_email    | "downtown@gmail.com"       <-- بيانات تجريبية
```

وفي الكود، **أربعة مصادر مختلفة للعملة**:

| الموقع | القيمة | النتيجة الفعلية |
|---|---|---|
| `DailyReportService.php:400` | `$this->setting('currency') ?? '€'` | **يقرأ `"USD"`** → تقرير Z اليومي يطبع **دولاراً** |
| `ServiceAvailabilityService.php:631` | `'currency' => 'EUR'` ثابت | التطبيق يعرض يورو |
| `InvoiceFinalizationService.php:154` | `'currency' => 'EUR'` ثابت | يورو |
| `BookingMailService.php:35` | `get_setting('currency_symbol', '€')` | **المفتاح غير موجود أصلاً** → يعود إلى `€` |

**تأكيد:** فحصتُ قائمة مفاتيح `salon_settings` كاملة — **لا يوجد مفتاح اسمه `currency_symbol`**. لذلك `BookingMailService` يعمل صدفةً عبر القيمة الافتراضية، وسينكسر في اللحظة التي يُضيف فيها أحد ذلك المفتاح بقيمة خاطئة.

### الأثر على الموقع

- **تقرير Z اليومي — وهو المستند المحاسبي الأهم في نظام كاشير ألماني — يطبع مبالغ بالدولار.** غير مقبول في تدقيق.
- العميل يرى `50.00 EUR` في التطبيق، ويستلم بريداً بـ `50.00 €`، بينما تقريرك الداخلي يقول `50.00 USD`. **ثلاث عملات لنفس المعاملة.**
- بقايا البذور الإماراتية (`points_per_aed`، هاتف `+971`) توحي بأن الإعدادات لم تُراجَع للسوق الألماني إطلاقاً — وهي علامة على وجود قيم أخرى غير مراجَعة.

### الإصلاح

**الخطوة 1 — صحّح البيانات:**

```sql
UPDATE salon_settings SET value = '"EUR"' WHERE `key` = 'currency';
INSERT INTO salon_settings (`key`, `value`, `type`, created_at, updated_at)
VALUES ('currency_symbol', '"€"', 'string', NOW(), NOW());

UPDATE salon_settings SET value = '"+49 30 12345678"'        WHERE `key` = 'branch_phone';
UPDATE salon_settings SET value = '"info@lookupfriseur.com"' WHERE `key` = 'branch_email';

DELETE FROM salon_settings WHERE `key` = 'points_per_aed';
```

**الخطوة 2 — مصدر واحد للعملة:**

```php
// app/Helpers/Main.php
if (! function_exists('money')) {
    function money(float|string $amount): string
    {
        $symbol = (string) get_setting('currency_symbol', '€');

        // التنسيق الألماني: 1.234,56 €
        return number_format((float) $amount, 2, ',', '.') . ' ' . $symbol;
    }
}

if (! function_exists('currency_code')) {
    function currency_code(): string
    {
        return (string) get_setting('currency', 'EUR');
    }
}
```

ثم استبدل **كل** ظهور ثابت لـ `'EUR'` و`'€'` بهاتين الدالتين.

**ملاحظة على التنسيق:** `ServiceAvailabilityService:632` يستخدم `number_format($price, 2) . ' EUR'` — وهو التنسيق الأمريكي `1,234.56`. التنسيق الألماني هو `1.234,56 €` (نقطة للآلاف، فاصلة للعشرية). عملاؤك الألمان سيقرؤون `1,234.56` على أنه **1.23 يورو**.

---

## 🔴 TZ-01 — المنطقة الزمنية `Asia/Baghdad` لصالون في برلين

**الموقع:** [`.env`](.env) `APP_TIMEZONE=Asia/Baghdad` → [`config/app.php:85`](config/app.php#L85)
**الحالة:** ✅ **مؤكد بتنفيذ فعلي على تطبيقك**

```
app.timezone = Asia/Baghdad
now()        = 2026-08-29 13:26:33
```

بغداد في **UTC+3 ثابتة (بلا توقيت صيفي)**. برلين في **UTC+2 صيفاً و UTC+1 شتاءً**. أي فرق **ساعة صيفاً وساعتين شتاءً** — **وهو فرق متغيّر خلال السنة**.

### ما الذي ينكسر بالضبط

كل نداء `now()` و`Carbon::today()` و`->isToday()` و`->isPast()` في المشروع يعمل بتوقيت بغداد:

**1. تقرير Z اليومي — الأخطر محاسبياً**

`DailyReportService` يجمع معاملات «اليوم». وحدّ اليوم في بغداد يبدأ عند **22:00 بتوقيت برلين في الصيف**:

```
معاملة فعلية:  الجمعة 22:30 بتوقيت برلين
حدّ اليوم:      يقع في السبت 00:00 بتوقيت بغداد = الجمعة 22:00 برلين
النتيجة:       المعاملة تُسجَّل في تقرير Z ليوم السبت — أي في اليوم الخطأ
```

**كل معاملة بعد الساعة 22:00 (أو 23:00 شتاءً) تُنسب إلى اليوم التالي.** بالنسبة لمدقق ألماني، هذا يعني أن تقاريرك اليومية لا تطابق يوم العمل الفعلي — خطأ جوهري في KassenSichV.

**2. حدود الحجز**

```php
// BookingValidationService:33
if ($bookingDate->lt(Carbon::today())) {
    throw new InvalidArgumentException('Cannot book in the past');
}
```

`Carbon::today()` تنتقل إلى «الغد» عند الساعة 22:00 برلين. عميل يحاول الحجز الساعة 22:30 مساء الجمعة **ليوم السبت** يُرفَض أحياناً بحجة «الماضي» — لأن النظام يظن أن السبت قد بدأ فعلاً.

**3. `isToday()` في مسار الموظفين**

```php
// BookingValidationService:248
if ($allowSameDayPast && $startTime->isToday()) { return; }
```

موظف يُسجّل زبوناً حضر الساعة 22:30، فيرفض النظام لأن `isToday()` تقيس بتوقيت بغداد.

**4. `book_buffer` والتذكيرات** — كلها منزاحة بساعة أو ساعتين.

### الأثر على الموقع

**هذه ليست علة تجميلية.** هي انزياح منهجي في تعريف «اليوم» — وتعريف اليوم هو أساس كل تقرير محاسبي وكل قاعدة حجز. والأسوأ أن الانزياح **يتغيّر** مع التوقيت الصيفي الألماني (ساعة صيفاً، ساعتان شتاءً)، فالأخطاء ستبدو عشوائية وغير قابلة للتفسير.

### الإصلاح

```env
# .env  و  .env.example
APP_TIMEZONE=Europe/Berlin
```

**وقبل التغيير — تحقق من البيانات الموجودة.** الأعمدة `appointment_date` و`start_time` و`end_time` من نوع `datetime` (بلا منطقة زمنية) — وقد كُتبت بتوقيت بغداد. بعد تغيير الإعداد ستُقرأ كأنها بتوقيت برلين، **فتنزاح كل المواعيد الموجودة ساعة أو ساعتين**.

```sql
-- 1) كم موعداً سيتأثر؟
SELECT COUNT(*) FROM appointments WHERE appointment_date >= CURDATE();

-- 2) إن كانت البيانات كلها تجريبية، الأنظف هو إعادة البذر بعد التغيير.
-- 3) إن كانت هناك بيانات حقيقية، أزِح الأعمدة الزمنية بمقدار الفرق:
--    (صيفاً: بغداد UTC+3، برلين UTC+2  ->  الفرق ساعة)
UPDATE appointments
SET start_time  = DATE_SUB(start_time,  INTERVAL 1 HOUR),
    end_time    = DATE_SUB(end_time,    INTERVAL 1 HOUR)
WHERE appointment_date >= '2026-08-29';
```

**نفّذ التغيير قبل الإطلاق** — تصحيحه بعد تراكم بيانات حقيقية أصعب بكثير.

### فكرة الإصلاح العميقة

المعيار الذهبي: **خزّن كل شيء بـ UTC، واعرض بالمنطقة المحلية.** هذا يجعل النظام محصّناً ضد التوقيت الصيفي وضد التوسّع إلى فروع في مناطق مختلفة (وأنت تخطط لتعدد الفروع — نموذج `Branch` موجود).

للمرحلة الحالية بفرع واحد، `Europe/Berlin` كافٍ وصحيح. لكن عند إضافة فرع في منطقة أخرى، انتقل إلى `timestamp with time zone` مع منطقة زمنية على مستوى الفرع.

---

## 🔴 DB-01 — فهارس فريدة مفقودة على كل أرقام المستندات

**الحالة:** ✅ **مؤكد بفحص `information_schema` على قاعدتك**

```sql
SELECT TABLE_NAME, INDEX_NAME, GROUP_CONCAT(COLUMN_NAME) cols, NON_UNIQUE
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('appointments','invoices','payments','provider_service')
GROUP BY TABLE_NAME, INDEX_NAME, NON_UNIQUE;
```

**النتيجة الفعلية:**

| الجدول | العمود | فهرس فريد؟ | الخطر |
|---|---|---|---|
| `appointments` | `number` | ❌ **لا** | أرقام مواعيد مكررة |
| `invoices` | `invoice_number` | ❌ **لا** | **خرق GoBD** — أرقام فواتير مكررة |
| `invoices` | `appointment_id` | ❌ **لا** (فهرس FK عادي) | **موعد واحد بفاتورتين** |
| `payments` | `payment_number` | ❌ **لا** | سجلات دفع مكررة |
| `provider_service` | `(provider_id, service_id)` | ❌ **لا** | ربط مكرر → سعر غير محدد |

### لماذا `provider_service` بلا قيد فريد خطير مالياً

بلا قيد فريد يمكن أن يوجد صفّان لنفس (مزوّد، خدمة) بأسعار مختلفة. وحينها:

```php
// BookingService::getEffectivePrice — يفلتر is_active
$pivot = DB::table('provider_service')
    ->where('provider_id', $provider->id)->where('service_id', $service->id)
    ->where('is_active', true)->first();          // ⚠️ ->first() على صفوف متعددة = ترتيب غير محدد
```

```php
// ServiceAvailabilityService::getProviderServicePricing — لا يفلتر is_active!
$pivot = DB::table('provider_service')
    ->where('provider_id', $provider->id)->where('service_id', $service->id)
    ->first();                                     // ⚠️ قد يلتقط صفاً مختلفاً
```

**النتيجة: السعر المعروض في التطبيق يختلف عن السعر المحصَّل فعلياً.** العميل يرى 30 يورو ويُفاجأ بـ 45. هذا خلاف مباشر مع العميل عند الكاشير.

### الإصلاح

```php
public function up(): void
{
    Schema::table('appointments', function (Blueprint $table) {
        $table->unique('number', 'appointments_number_unique');
        $table->unique(['provider_id', 'start_time'], 'appointments_provider_slot_unique');   // BOOK-02
    });

    Schema::table('invoices', function (Blueprint $table) {
        $table->unique('invoice_number', 'invoices_number_unique');
        $table->unique('appointment_id', 'invoices_appointment_unique');
    });

    Schema::table('payments', function (Blueprint $table) {
        $table->unique('payment_number', 'payments_number_unique');
    });

    Schema::table('provider_service', function (Blueprint $table) {
        $table->unique(['provider_id', 'service_id'], 'provider_service_unique');
    });
}
```

**نظّف التكرارات أولاً:**

```sql
SELECT number, COUNT(*) c FROM appointments GROUP BY number HAVING c > 1;
SELECT invoice_number, COUNT(*) c FROM invoices WHERE invoice_number IS NOT NULL GROUP BY invoice_number HAVING c > 1;
SELECT appointment_id, COUNT(*) c FROM invoices GROUP BY appointment_id HAVING c > 1;
SELECT provider_id, service_id, COUNT(*) c FROM provider_service GROUP BY provider_id, service_id HAVING c > 1;
```

وأصلح التناقض في `getProviderServicePricing` بإضافة `->where('is_active', true)` ليطابق `getEffectivePrice`.

---

## 🟠 DB-02 — فهارس أداء مفقودة على أكثر الاستعلامات تكراراً

**الحالة:** ✅ مؤكد بفحص الفهارس

جدول `appointments` يحمل ثلاثة فهارس فقط: `PRIMARY` و`customer_id` و`provider_id` و`parent_appointment_id`. **لا فهرس على `appointment_date` ولا `start_time` ولا `status` ولا `created_status`.**

استعلام التعارض — وهو **الأكثر تنفيذاً في النظام كله** — يبدو هكذا:

```sql
SELECT EXISTS(
  SELECT 1 FROM appointments
  WHERE provider_id = ?
    AND DATE(appointment_date) = ?          -- ⚠️ دالة على العمود = لا فهرس يُستخدم
    AND created_status = 1
    AND status IN (0, 1)
    AND start_time < ? AND end_time > ?
);
```

مشكلتان:
1. **`whereDate()` تُنتج `DATE(appointment_date) = ?`** — ودالة على عمود تمنع MySQL من استخدام أي فهرس عليه.
2. **لا فهرس مركّب** يغطي المرشّحات.

MySQL يستخدم فهرس `provider_id` ثم يفحص كل مواعيد ذلك المزوّد سطراً سطراً.

**الأثر:** مع 100 ألف موعد و10 مزوّدين، كل فحص تعارض يمسح ~10,000 صف. وصفحة التوفّر تُنفّذ عشرات هذه الاستعلامات. **زمن الاستجابة يتدهور خطياً مع نمو أعمالك** — وهو أسوأ أنواع التدهور، لأنه يظهر بعد النجاح لا قبله.

```php
Schema::table('appointments', function (Blueprint $table) {
    $table->index(['provider_id', 'appointment_date', 'created_status', 'status'],
                  'appointments_conflict_idx');
    $table->index(['customer_id', 'appointment_date'], 'appointments_customer_date_idx');
    $table->index('start_time', 'appointments_start_time_idx');
});

Schema::table('provider_time_offs', function (Blueprint $table) {
    $table->index(['user_id', 'type', 'start_date', 'end_date'], 'time_offs_lookup_idx');
});

Schema::table('provider_scheduled_works', function (Blueprint $table) {
    $table->index(['user_id', 'day_of_week', 'is_work_day', 'is_active'], 'schedule_lookup_idx');
});

Schema::table('otps', function (Blueprint $table) {
    $table->index('expires_at', 'otps_expires_at_idx');
});
```

**واستبدل `whereDate` بمقارنة مدى** حتى يستفيد الفهرس:

```php
// ❌ يمنع الفهرس
->whereDate('appointment_date', $date)

// ✅ يستخدم الفهرس
->whereBetween('appointment_date', [
    Carbon::parse($date)->startOfDay(),
    Carbon::parse($date)->endOfDay(),
])
```

---

## 🟠 DB-03 — `Otp::$fillable` يحوي عموداً غير موجود

**الموقع:** [`app/Models/Otp.php:16`](app/Models/Otp.php#L16)
**الحالة:** ✅ **مؤكد بقاعدة البيانات**

```php
protected $fillable = ['email', 'phone', 'otp', 'expires_at', 'device', 'type', 'purpose', 'attempts', 'used'];
//                                                              ^^^^^^^^
```

```sql
SHOW COLUMNS FROM otps;
-- id, email, phone, otp, expires_at, type, purpose, attempts, used, created_at, updated_at
-- لا يوجد عمود اسمه `device`
```

أي `Otp::create([... 'device' => $x ...])` سيُلقي `Column not found: 1054 Unknown column 'device'` → **500**. قنبلة موقوتة تنتظر أول مطوّر يستخدم الحقل ظاناً أنه موجود.

**الإصلاح:** أزل `'device'` من `$fillable`، أو أضِف العمود إن كنت تنوي استخدامه لربط الـ OTP بجهاز محدد (وهي ميزة أمان جيدة فعلاً).

---

## 🟠 DB-04 — `get_setting()` يستعلم قاعدة البيانات في كل نداء

**الموقع:** [`app/Helpers/Main.php:5-15`](app/Helpers/Main.php#L5-L15)

```php
function get_setting($key, $default = null)
{
    $setting_record = SalonSetting::where('key', $key)->first();    // ⚠️ استعلام في كل نداء
    if ($setting_record) {
        return $setting_record->value;
    }
    return $default;
}
```

**بلا أي تخزين مؤقت.** وهذه الدالة تُنادى في المسارات الأكثر سخونة:

```php
// ServiceAvailabilityService::bookBufferMinutes() — تُنادى لكل مزوّد في كل يوم
return max(0, (int) get_setting('book_buffer', 60));

// lastBookableDate() — نفس الشيء
return Carbon::today()->addDays(max(0, (int) get_setting('max_booking_days', 10)));
```

**الحساب:** تقويم 31 يوماً × 10 مزوّدين × نداءين على الأقل = **620 استعلاماً إضافياً** لطلب HTTP واحد — لمجرد قراءة رقمين لا يتغيران.

**مشكلة ثانية:** الدالة **لا تفلتر على `branch_id`** رغم وجود العمود في الجدول. عند تفعيل تعدد الفروع، ستُرجع `->first()` إعداد فرع عشوائي — فقد يُحسب **معدل ضريبة فرع** لمعاملة **فرع آخر**.

```php
if (! function_exists('get_setting')) {
    function get_setting(string $key, $default = null, ?int $branchId = null)
    {
        $branchId ??= config('app.current_branch_id');

        $all = Cache::remember("salon_settings:{$branchId}", now()->addHour(), function () use ($branchId) {
            return SalonSetting::query()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->pluck('value', 'key')
                ->all();
        });

        return $all[$key] ?? $default;
    }
}
```

وأبطِل الكاش عند التعديل:

```php
// app/Observers/SalonSettingObserver.php
public function saved(SalonSetting $s): void   { Cache::forget("salon_settings:{$s->branch_id}"); }
public function deleted(SalonSetting $s): void { Cache::forget("salon_settings:{$s->branch_id}"); }
```

---

## 🟡 DB-05 — أنواع بيانات الإعدادات غير متسقة

**الحالة:** ✅ مؤكد بقاعدة البيانات

```
max_booking_days          | 10        <-- رقم JSON
max_daily_bookings        | "10"      <-- سلسلة JSON
max_services_per_booking  | 10        <-- رقم JSON
book_buffer               | "0"       <-- سلسلة JSON
tax_rate                  | "19"      <-- سلسلة JSON
auto_confirm_appointments | "false"   <-- سلسلة "false"، لا false منطقية!
enable_online_payment     | "true"    <-- سلسلة "true"
```

**الخطر الأكبر:** `auto_confirm_appointments` = السلسلة `"false"`. وفي PHP، السلسلة `"false"` قيمتها المنطقية **`true`**:

```php
if (get_setting('auto_confirm_appointments')) {
    // ⚠️ تُنفَّذ دائماً — حتى والإعداد "false"!
}
```

هذا الإعداد غير مستخدم حالياً في الكود (فحصتُ)، لكنه **فخ ينتظر أول من يستخدمه**. نفس الشيء ينطبق على `enable_online_payment` و`loyalty_points_enabled`.

**الإصلاح:** خزّن القيم بأنواعها الصحيحة، واستخدم عمود `type` الموجود (وهو معطّل حالياً — الـ accessor مُعلَّق في `SalonSetting.php:43-55`):

```sql
UPDATE salon_settings SET value = 'false' WHERE `key` = 'auto_confirm_appointments';   -- منطقية JSON
UPDATE salon_settings SET value = 'true'  WHERE `key` = 'enable_online_payment';
UPDATE salon_settings SET value = '19'    WHERE `key` = 'tax_rate';                    -- رقم
UPDATE salon_settings SET value = '0'     WHERE `key` = 'book_buffer';
UPDATE salon_settings SET value = '10'    WHERE `key` = 'max_daily_bookings';
```

وأضِف دوال مساعدة صريحة النوع:

```php
function setting_bool(string $key, bool $default = false): bool
{
    return filter_var(get_setting($key, $default), FILTER_VALIDATE_BOOLEAN);
}

function setting_int(string $key, int $default = 0): int
{
    return (int) get_setting($key, $default);
}
```

---

## 🟡 SET-03 — `book_buffer = 0` يسمح بالحجز في اللحظة الحالية

**الحالة:** ✅ مؤكد بقاعدة البيانات — `book_buffer` = `"0"`

`Agent.md` يوثّق القيمة الافتراضية بـ 60 دقيقة، والكود يستخدم 60 كاحتياطي — لكن **قاعدة بياناتك تحمل 0**، والقيمة المخزَّنة تفوز.

```php
// BookingValidationService:260-266
$book_buffer = intval(get_setting('book_buffer', 60));    // -> 0

if ($startTime->lt(Carbon::now()->addMinutes(0))) {       // -> فقط "ليس في الماضي"
    throw new InvalidArgumentException(...);
}
```

**النتيجة:** عميل يستطيع حجز موعد يبدأ **بعد ثانية واحدة من الآن**. لا وقت للمزوّد ليستعد، ولا للموظف ليرى الحجز. زبون يظهر في الصالون بلا إنذار وموعد «مؤكد» في النظام.

```sql
UPDATE salon_settings SET value = '60' WHERE `key` = 'book_buffer';
```

**نقطة إيجابية:** فحصتُ التطابق بين الطبقتين — `ServiceAvailabilityService:393` يطبّق `earliestBookableTime()` بنفس المنطق، والتعليقات في السطور 378-392 تشرح القرار بدقة ممتازة. **هذه المطابقة صحيحة ومصمَّمة بوعي.** المشكلة في القيمة المخزَّنة فقط.

---

## 🟡 SET-04 — `cancellation_hours` مخزَّن ولا يُستخدم

**الحالة:** ✅ مؤكد — `cancellation_hours = "24"` موجود في قاعدة البيانات، و`grep` على الكود كله: **صفر استخدامات**

سياسة الإلغاء معرَّفة في الإعدادات لكنها **غير مطبَّقة إطلاقاً**. العميل يستطيع الإلغاء قبل دقيقة من الموعد بلا أي عائق (انظر `BOOK-08`). المزوّد يخسر الفتحة كاملة.

---

## 🟡 DB-06 — لا حذف ناعم (soft delete) على الجداول المالية

**الحالة:** ✅ مؤكد بفحص الأعمدة — لا `deleted_at` في `appointments`

مذكور في `MON-10`، لكنه يستحق تسجيلاً مستقلاً: الجداول `appointments` و`invoices` و`payments` تُحذف حذفاً نهائياً. لا استرجاع، ولا أثر تدقيق، ولا امتثال لمبدأ عدم القابلية للتغيير في GoBD.

```php
Schema::table('appointments', fn (Blueprint $t) => $t->softDeletes());
Schema::table('invoices',     fn (Blueprint $t) => $t->softDeletes());
Schema::table('payments',     fn (Blueprint $t) => $t->softDeletes());
```

وأضِف `use SoftDeletes;` في النماذج الثلاثة.

---

# 8. الأداء والحرمان من الخدمة

## 🟠 PERF-01 — كاش التوفّر مضبوط على **ثانية واحدة** لا دقيقة

**الموقع:** [`app/Services/ServiceAvailabilityService.php:23, 75, 137`](app/Services/ServiceAvailabilityService.php#L23)
**الحالة:** ✅ مؤكد بالكود

```php
private const CACHE_DURATION = 1;
// ...
return Cache::remember($cacheKey, self::CACHE_DURATION, function () use (...) { /* ... */ });
```

في Laravel، عدد صحيح كمعامل TTL يُفسَّر **بالثواني** منذ الإصدار 5.8. لذلك القيمة هي **ثانية واحدة**، لا دقيقة كما يوثّق `Agent.md` القسم 4.3.

عملياً: **الكاش معطّل تماماً** — بل أسوأ، لأنك تدفع تكلفة كتابة الكاش (وهي كتابة في قاعدة البيانات، لأن `CACHE_STORE=database`) على كل طلب، بلا أن تستفيد من أي قراءة.

**الأثر مضاعف:** نقطة `/api/availability/calendar` بلا `provider_id` تُنفّذ 31 نداءً لـ `getAvailableSlotsByDate`، كل واحد يستعلم كل المزوّدين ثم جداولهم وإجازاتهم ومواعيدهم. مع 10 مزوّدين:

```
31 يوماً × 10 مزوّدين × ~4 استعلامات = ~1,240 استعلاماً
+ 31 كتابة كاش (في قاعدة البيانات)
+ get_setting بلا كاش: ~620 استعلاماً إضافياً (DB-04)
─────────────────────────────────────────────────
≈ 1,900 استعلام قاعدة بيانات في طلب HTTP واحد
```

`throttle:30,1` يسمح بـ 30 طلباً في الدقيقة لكل IP → **57,000 استعلام في الدقيقة من عميل واحد**. ومع `CFG-02` (تزوير الـ IP)، لا سقف إطلاقاً.

```php
private const CACHE_TTL_SECONDS = 60;      // ✅ اسم صريح بالوحدة

Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, fn () => /* ... */);
```

**والأهم — أبطِل الكاش عند تغيّر البيانات** بدل الاعتماد على انتهاء المدة:

```php
// app/Observers/AppointmentObserver.php
public function saved(Appointment $a): void   { $this->flush($a); }
public function deleted(Appointment $a): void { $this->flush($a); }

private function flush(Appointment $a): void
{
    Cache::forget("availability_provider_{$a->provider_id}_date_{$a->appointment_date->format('Y-m-d')}");
}
```

---

## 🟠 PERF-02 — `Cache::tags()` غير مدعوم على مخزن قاعدة البيانات

**الموقع:** [`app/Services/ServiceAvailabilityService.php:677-688`](app/Services/ServiceAvailabilityService.php#L677-L688)
**الحالة:** ⚠️ **كامن** — الدالتان لا تُستدعيان إلا من كود ميت

```php
public function clearServiceCache(int $serviceId): void
{
    Cache::tags(["service_{$serviceId}"])->flush();      // ⚠️
}
```

`CACHE_STORE=database` في `.env`. **مخزن قاعدة البيانات لا يدعم الوسوم (tags)** — الاستدعاء يُلقي:

```
BadMethodCallException: This cache store does not support tagging.
```

المستدعيان الوحيدان هما `BookingService2.php:205, 206, 414` — وهو ملف ميت بالكامل. لذلك العطل غير نشط، **لكن الدالتين تبدوان صالحتين للاستخدام**، وأي مطوّر يستدعيهما سيحصل على 500.

كما أن مفاتيح الكاش الفعلية (`availability_service_{$id}_date_{$date}`) **لا تحمل أي وسم أصلاً** — فحتى لو دعم المخزن الوسوم، لن تمسح شيئاً.

```php
public function clearProviderCache(int $providerId, ?string $date = null): void
{
    if ($date) {
        Cache::forget("availability_provider_{$providerId}_date_{$date}");
        return;
    }

    // امسح نافذة الحجز كلها
    $days = (int) get_setting('max_booking_days', 10);

    for ($i = 0; $i <= $days; $i++) {
        $d = Carbon::today()->addDays($i)->format('Y-m-d');
        Cache::forget("availability_provider_{$providerId}_date_{$d}");
    }
}
```

**أو** انتقل إلى Redis (وهو مُعدّ في `.env` عندك: `REDIS_HOST=127.0.0.1`) الذي يدعم الوسوم ويؤدي أفضل بكثير للكاش:

```env
CACHE_STORE=redis
```

---

## 🟠 PERF-03 — قوائم بلا ترقيم صفحات و`per_page` بلا سقف

**الموقع:** [`app/Services/BookingService.php:441-453`](app/Services/BookingService.php#L441-L453) · [`app/Http/Controllers/Api/ServicesController.php:46`](app/Http/Controllers/Api/ServicesController.php#L46) · [`app/Http/Controllers/Api/ProvidersController.php:32`](app/Http/Controllers/Api/ProvidersController.php#L32)

**1. `getCustomerBookings` بلا ترقيم:**

```php
return $query->get();          // ⚠️ كل حجوزات العميل دفعة واحدة
```

مع علاقات `services` و`provider` و`services_record` محمّلة. عميل وفي بـ 500 حجز → استجابة JSON بحجم عدة ميغابايت، وذاكرة PHP مستهلكة، وتطبيق موبايل يتجمّد.

**2. `per_page` بلا حد أقصى:**

```php
$perPage  = $request->get('per_page', 15);       // ⚠️ بلا max
$services = $query->paginate($perPage);
```

```bash
curl "https://lookupfriseur.com/api/services?per_page=99999999"
```

`paginate(99999999)` يجلب كل الصفوف. مع `CFG-01` (بلا throttle عام)، حلقة من هذه الطلبات = **استنزاف ذاكرة PHP-FPM حتى انهيار السيرفر**.

```php
// ✅ في الـ controllers
$perPage = min(max((int) $request->get('per_page', 15), 1), 50);

// ✅ الأنظف: FormRequest
'per_page' => 'nullable|integer|min:1|max:50',
```

```php
// ✅ BookingService::getCustomerBookings
public function getCustomerBookings(User $customer, ?int $status = null, int $perPage = 15)
{
    return Appointment::where('customer_id', $customer->id)
        ->with(['services', 'provider', 'services_record'])
        ->when($status !== null, fn ($q) => $q->where('status', $status))
        ->orderByDesc('appointment_date')
        ->orderByDesc('start_time')
        ->paginate(min($perPage, 50));
}
```

---

## 🟡 PERF-04 — `sort_by` غير مقيَّد

**الموقع:** [`app/Http/Controllers/Api/ServicesController.php:41-44`](app/Http/Controllers/Api/ServicesController.php#L41-L44) · [`app/Http/Controllers/Api/ProvidersController.php:28`](app/Http/Controllers/Api/ProvidersController.php#L28)

```php
$sortBy    = $request->get('sort_by', 'sort_order');
$sortDirection = $request->get('sort_direction', 'asc');
$query->orderBy($sortBy, $sortDirection);
```

### تصحيح على التقرير السابق

التقرير القديم سمّى هذا **«SQL Injection»**. **هذا غير دقيق.** دالة `orderBy` في Laravel تُمرّر اسم العمود عبر `Grammar::wrap()` التي تغلّفه بعلامات backtick وتضاعف أي backtick داخلي — فالحقن الكلاسيكي محجوب.

المشاكل **الحقيقية** أخف لكنها موجودة:

1. **كشف معلومات عبر الترتيب:** الترتيب بعمود غير معروض (مثل `cost_price` لو أُضيف لاحقاً) يسمح باستنتاج قيمه بالمقارنة الثنائية عبر عدة طلبات.
2. **500 عند عمود غير موجود:** `?sort_by=nonexistent` → `Column not found` → استثناء غير معالج.
3. **500 عند تمرير مصفوفة:** `?sort_by[]=a` → `TypeError`.
4. **`sort_direction` غير مقيَّد أيضاً.**

```php
$allowedSorts = ['sort_order', 'name', 'price', 'created_at', 'duration_minutes'];

$sortBy = in_array($request->get('sort_by'), $allowedSorts, true)
    ? $request->get('sort_by')
    : 'sort_order';

$sortDirection = $request->get('sort_direction') === 'desc' ? 'desc' : 'asc';

$query->orderBy($sortBy, $sortDirection);
```

---

## 🟡 PERF-05 — بحث `orWhere` يكسر مرشّح النشاط

**الموقع:** [`app/Http/Controllers/Api/ServicesController.php:37`](app/Http/Controllers/Api/ServicesController.php#L37)

النمط الخطير:

```php
$query->where('is_active', true)
      ->where('name', 'like', "%{$search}%")
      ->orWhere('description', 'like', "%{$search}%");     // ⚠️
```

في SQL هذا يصبح:

```sql
WHERE is_active = 1 AND name LIKE '%x%' OR description LIKE '%x%'
```

و`AND` أعلى أسبقية من `OR`، فالشرط يعادل:

```sql
WHERE (is_active = 1 AND name LIKE '%x%') OR (description LIKE '%x%')
```

**النتيجة: خدمات معطّلة تظهر في نتائج البحث** إذا طابق وصفها. عميل قد يحجز خدمة أوقفتها عمداً.

```php
$query->where('is_active', true)
      ->where(function ($q) use ($search) {                 // ✅ تجميع صريح
          $q->where('name', 'like', "%{$search}%")
            ->orWhere('description', 'like', "%{$search}%");
      });
```

**قاعدة عامة:** كل `orWhere` يجب أن يكون داخل `where(function ($q) { ... })`. هذا الخطأ من أشيع أخطاء Eloquent وأصعبها ملاحظة.

---

## 🟡 PERF-06 — `wire:poll` على لوحة الموظفين

**الموقع:** `resources/views/livewire/staff-dashboard.blade.php`

اللوحة تُحدّث نفسها دورياً. كل دورة تُعيد تشغيل استعلامات المزوّدين والمواعيد والإجازات. مع 8 موظفين وشاشات مفتوحة طوال اليوم، يتراكم حمل ثابت على قاعدة البيانات — من دون أي إجراء من المستخدم.

```blade
{{-- ✅ .visible يوقف الاستطلاع عندما تكون الشاشة في الخلفية --}}
<div wire:poll.30s.visible>
```

وخزّن بيانات الخط الزمني مؤقتاً لبضع ثوانٍ:

```php
public function getTimelineData(): array
{
    return Cache::remember(
        "timeline:{$this->selectedDate}:{$this->selectedBranchId}",
        10,
        fn () => $this->getTimelineDataFromProviders()
    );
}
```

---

# 9. الكود الميت والجودة

## 🟠 QUAL-01 — 672 سطراً من خدمات الحجز الميتة

**الحالة:** ✅ مؤكد بـ `grep` شامل على `app/` و`routes/` و`config/` و`tests/`

| الملف | الأسطر | مراجع خارجية |
|---|---|---|
| `app/Services/BookingService2.php` | 428 | **صفر** |
| `app/Services/Appointments/AppointmentCreationService.php` | 244 | **صفر** |

### لماذا `BookingService2` خطير رغم كونه ميتاً

يحوي منطقاً **مخالفاً** لـ `BookingService` النشط:

```php
// BookingService2.php:333
private function getTaxRate(): float
{
    return 19;              // ⚠️ ثابت — يتجاهل get_setting('tax_rate')
}

// BookingService2.php:299
'currency' => 'EUR',        // ⚠️ ثابت خامس للعملة

// BookingService2.php:341
$random = strtoupper(substr(uniqid(), -6));    // ⚠️ uniqid() متوقّع، بينما النشط يستخدم random_bytes()

// BookingService2.php:205-206
$this->availabilityService->clearProviderCache(...);   // ⚠️ يُلقي BadMethodCallException (PERF-02)
```

**الخطر الحقيقي:** ملفان باسم متشابه وواجهة متشابهة (`createBooking`) — والفرق بينهما حرف واحد. أول مطوّر يبحث عن «خدمة الحجز» قد يفتح الملف الخطأ، أو يستدعيه، أو — الأسوأ — **يُصلح علة فيه ظاناً أنه أصلح الإنتاج**.

**الإصلاح:** احذف الملفين. `git` يحتفظ بالتاريخ إن احتجته يوماً.

```bash
git rm app/Services/BookingService2.php
git rm app/Services/Appointments/AppointmentCreationService.php
```

---

## 🟡 QUAL-02 — دوال ميتة داخل ملفات نشطة

| الموقع | الدالة | المشكلة |
|---|---|---|
| `BookingService.php:356-372` | `calculateTotalsInverse()` | ميتة **وخاطئة رياضياً** — تُضيف الضريبة **فوق** سعر شامل للضريبة أصلاً. لو استُدعيت لفوترت العميل بـ 19% زائدة. |
| `BookingService.php:381-386` | جسم `getEffectiveDuration` | كود بعد `return` — راجع `BOOK-05` |
| `ServiceAvailabilityService.php:593-598` | نفس الشيء | مكرر |
| `InvoiceFinalizationService.php:190-204` | `determineInvoiceStatus()` | **صحيحة لكن غير مستدعاة** — راجع `MON-04` |
| `ServiceAvailabilityService.php:543-553` | `calculateBreakStart()` | ميتة (المستدعي مُعلَّق في السطور 375-376) — `break_minutes` لا يُطبَّق إطلاقاً |
| `InvoiceService.php:610-668` | `finalizeDraftInvoice()` | نسخة مكررة أقل اكتمالاً — راجع `MON-05` |
| `PrintController.php:102` | `if (empty($invoiceIds))` | فرع ميت — `explode(',', '')` تُرجع `['']` وهي غير فارغة |

**ملاحظة على `break_minutes`:** العمود موجود في `provider_scheduled_works` وواجهة الإدارة تسمح بضبطه، لكن توليد الفتحات **يتجاهله تماماً** (السطران 375-376 مُعلَّقان). أي أن استراحة الغداء لا تُحجب من التوفّر — عميل يستطيع حجز موعد في وقت استراحة المزوّد. **هذا سلوك خاطئ نشط، لا مجرد كود ميت.**

---

## 🟡 QUAL-03 — أخطاء إملائية في أسماء بنيوية

| الموقع | الخطأ | الصواب | الأثر |
|---|---|---|---|
| `branchs` (اسم جدول) | `branchs` | `branches` | كل `constrained('branchs')` يجب أن يعرف الخطأ — راجع الذاكرة الدائمة |
| `branchs.adress` | `adress` | `address` | **الكود صحيح** لأنه يطابق العمود — التقرير القديم أخطأ هنا |
| `Invoice.php:31` `segnture` | `segnture` | `signature` | حقل التوقيع الرقمي TSE — **يظهر باسم خاطئ في مستند قانوني** |
| `PaymentStatus.php:9-10` `ONSTIE` | `ONSTIE` | `ONSITE` | يظهر في استجابات API |
| `routes/api.php:215` `noticifation` | `noticifation` | `notification` | **مسار يفلت من كل بحث أمني أو قاعدة WAF** — راجع `AUTHZ-04` |
| `InvoiceService.php:374` `createDtaft...` | `Dtaft` | `Draft` | اسم دالة عامة |

الأولوية القصوى لـ `segnture` و`noticifation`:
- **`segnture`** حقل قانوني في مستند خاضع للتدقيق. مدقق يبحث عن `signature` لن يجده.
- **`noticifation`** يخفي مساراً حرجاً أمنياً عن أي أداة فحص.

```php
// migration
Schema::table('invoices', fn (Blueprint $t) => $t->renameColumn('segnture', 'signature'));
Schema::rename('branchs', 'branches');
Schema::table('branches', fn (Blueprint $t) => $t->renameColumn('adress', 'address'));
```

```php
// app/Enum/PaymentStatus.php — أضِف اسماً صحيحاً مع إبقاء القيم
enum PaymentStatus: int
{
    case PAID_ONSITE_CASH = 2;
    case PAID_ONSITE_CARD = 3;
    // ... القيم الرقمية لا تتغير، فلا تتأثر البيانات
}
```

**تحذير:** إعادة تسمية `PaymentStatus::PAID_ONSTIE_CASH` تتطلب تحديث كل الاستخدامات. القيم الرقمية (2، 3) هي المخزَّنة، فالبيانات آمنة.

---

## 🟡 QUAL-04 — التوثيق يخالف الكود في 6 مواضع

| الادعاء في التوثيق | الواقع |
|---|---|
| `Agent.md` §1: «PostgreSQL (Neon-backed, Replit)» | **MySQL** — أكّدتَه أنت و`.env` |
| `Agent.md` §1: «Fiskaly TSE (placeholder)» | تنفيذ كامل 1,573 سطراً — لكنه غير موصول (`MON-08`) |
| `Agent.md` §4.3: «Results cached for 1 minute» | **ثانية واحدة** (`PERF-01`) |
| `Agent.md` §4.7: «book_buffer default 60» | **0 في قاعدة البيانات** (`SET-03`) |
| `Agent.md` §5: «throttle 60/min» على التوفّر | فعلياً 40 و30 |
| `BOOKING_SOURCE_IMPLEMENTATION_PLAN.md` | يقترح `online_api` / `internal`؛ الكود ينفّذ `online` / `in_person` |
| `docs/BOOKING_FLOW.md` §مشاكل: «uniqid() قد يتكرر» | الكود يستخدم الآن `random_bytes(3)` — أفضل (لكن ما زال بلا قيد فريد، `DB-01`) |
| `docs/BOOKING_FLOW.md`: «findOrFail يرجع 500» | صحيح جزئياً — الكود يستخدم الآن `find()` + `ModelNotFoundException` صريحاً، لكن الـ controller ما زال يُرجع 500 (`BOOK-10`) |

**الأثر:** التوثيق هو أول ما يقرؤه مطوّر جديد — أو وكيل ذكاء اصطناعي. توثيق يقول «PostgreSQL» سيدفع أحدهم لكتابة استعلام PostgreSQL خاص على قاعدة MySQL. حدّث `Agent.md` بعد إصلاح هذا التقرير.

---

## 🔵 QUAL-05 — تغطية اختبارات ناقصة في المسارات الحرجة

**الموجود (جيد):** اختبارات Fiskaly (4 ملفات)، حاسبة الضرائب (3)، تدفق الحجز (4 خطوات)، إعادة تعيين كلمة المرور، رفع الصور، توفّر المزوّد مع الإجازات.

**المفقود — وكلها تغطي ثغرات Critical في هذا التقرير:**

```php
// tests/Feature/Concurrency/DoubleBookingTest.php   -> BOOK-02
it('rejects the second of two simultaneous bookings for the same slot', function () { /* ... */ });

// tests/Feature/Money/TaxParityTest.php             -> MON-01
it('produces identical VAT in the booking and the invoice layer', function () { /* ... */ });

// tests/Feature/Money/DiscountAuthorizationTest.php -> MON-02
it('rejects a discount from staff without apply_discount', function () { /* ... */ });

// tests/Feature/Auth/DeactivatedStaffTest.php       -> AUTHZ-01
it('locks a deactivated provider out of the staff dashboard', function () { /* ... */ });

// tests/Feature/Authorization/InvoiceIdorTest.php   -> AUTHZ-03
it('forbids printing another customer invoice', function () { /* ... */ });

// tests/Feature/RouteBindingTest.php                -> CFG-01
it('resolves route model binding on api routes', function () { /* ... */ });

// tests/Feature/Availability/ParityTest.php         -> BOOK-01
it('never offers a slot the booking layer would reject', function () { /* ... */ });
```

---

# 10. ما هو مبنيّ بشكل صحيح

الإنصاف جزء من التدقيق. هذه أنماط سليمة وجدتُها ويجب **عدم** المساس بها أثناء الإصلاح:

| النمط | الموقع | لماذا هو صحيح |
|---|---|---|
| **نظام صلاحيات لوحة الموظفين** | `InteractsWithDashboardPermissions` | `dashDeny` + `canActOnAppointment` + قاعدة الملكية + تجاوز SuperAdmin. مطبَّق باتساق في كل إجراء في `StaffDashboard`. نموذج يُحتذى — طبّقه على `CustomerLookup`. |
| **`$request->validated()` في مسار الحجز** | `Api/BookingController:35` | يمنع بنيوياً حقن `is_confirmed` و`mark_as_paid` و`bypass_availability`. القرار الصحيح تماماً. |
| **حصر المبلغ في `applyFinalAmount`** | `InvoiceService:583-589` | يمنع المبالغ السالبة والدفع الزائد كخصم. منطق سليم. |
| **حماية حذف المواعيد المدفوعة** | `StaffDashboard:760` | تمنع حذف المدفوع/المكتمل. تحتاج توسيعاً فقط (`MON-10`). |
| **انتهاء صلاحية توكن الوصول** | `AuthTokenService:18-20` | 15 دقيقة، مع عمود `expires_at` موجود. **التقرير القديم أخطأ في وصفه.** |
| **إبطال تحقق الهاتف عند تغييره** | `ProfileController:49-52` | يمنع تثبيت رقم مهاجم. |
| **حماية تعطيل الحساب عند تسجيل الدخول** | `AuthController:74-81` | يفحص `is_active` بشكل صحيح — الثغرة في مسار اللوحة فقط (`AUTHZ-01`). |
| **`bcmath` في `BookingService::calculateTotals`** | `BookingService:249-326` | دقة داخلية 6 + تسوية التقريب. **هذا هو التنفيذ الصحيح** — اجعله المعيار (`MON-01`). |
| **التحميل الدفعي للنماذج** | `BookingService:175-176` | `whereIn(...)->keyBy()` يتجنّب مشكلة N+1. |
| **مطابقة `book_buffer` بين الطبقتين** | `ServiceAvailabilityService:378-393` | التعليقات تشرح القرار بدقة ممتازة، والمنطق صحيح. المشكلة في القيمة المخزَّنة فقط. |
| **معالجة الحجوزات المرتبطة (parent/child)** | `AppointmentLinkingService` + `GapAnalysisService` | تصميم متقدّم وواعٍ لفاتورة موحّدة عبر عدة مزوّدين. |
| **تنظيف المسارات المكررة** | `routes/web.php:30-36, 125-129` | التعليقات توثّق ثغرة أمنية أُزيلت فعلاً (`/grant-view-stats`) — ممارسة ممتازة. |

---

# 11. خارطة الطريق

## المرحلة صفر — قبل الإطلاق مباشرة (نصف يوم، بلا كود)

هذه تغييرات بيانات وإعداد فقط، وتزيل ثلاثة موانع إطلاق:

```sql
-- SET-01: بيانات الشركة الضريبية (§14 UStG)
UPDATE salon_settings SET value = '"LookUp Friseur GmbH"'            WHERE `key`='company_name';
UPDATE salon_settings SET value = '"Musterstraße 123, 10115 Berlin"' WHERE `key`='company_address';
UPDATE salon_settings SET value = '"DE123456789"'                     WHERE `key`='company_tax_number';
UPDATE salon_settings SET value = '"+49 30 12345678"'                 WHERE `key`='company_phone';
UPDATE salon_settings SET value = '"info@lookupfriseur.com"'          WHERE `key`='company_email';

-- SET-02: العملة
UPDATE salon_settings SET value = '"EUR"' WHERE `key`='currency';
INSERT INTO salon_settings (`key`,`value`,`type`,created_at,updated_at)
VALUES ('currency_symbol','"€"','string',NOW(),NOW());

-- SET-03: مهلة الحجز المسبق
UPDATE salon_settings SET value = '60' WHERE `key`='book_buffer';

-- DB-05: أنواع منطقية صحيحة
UPDATE salon_settings SET value = 'false' WHERE `key`='auto_confirm_appointments';
UPDATE salon_settings SET value = 'true'  WHERE `key`='enable_online_payment';

-- SET-02: بقايا بذور إماراتية
UPDATE salon_settings SET value = '"+49 30 12345678"'        WHERE `key`='branch_phone';
UPDATE salon_settings SET value = '"info@lookupfriseur.com"' WHERE `key`='branch_email';
DELETE FROM salon_settings WHERE `key`='points_per_aed';
```

```env
# TZ-01 — نفّذه قبل تراكم أي بيانات حقيقية
APP_TIMEZONE=Europe/Berlin

# CFG-08
SANCTUM_TOKEN_PREFIX=lookup_
```

```php
// احذف هذه المسارات — دقيقتان، ويزيل 3 ثغرات
// routes/web.php:103   -> GET /test              (CFG-06)
// routes/web.php:175   -> GET /internal/clear-cache (CFG-03)
// routes/api.php:73    -> POST /api/test/vonage-sms (CFG-04)
// routes/api.php:215   -> noticifation/*         (AUTHZ-04)
```

---

## المرحلة 1 — موانع الإطلاق (2-3 أيام)

| # | المعرّف | المهمة | الملف |
|---|---|---|---|
| 1 | `CFG-01` | `appendToGroup('api', ...)` + `throttleApi()` | `bootstrap/app.php:25` |
| 2 | `CFG-02` | `trustProxies(at: ['127.0.0.1','::1'])` + `fastcgi_param HTTP_X_FORWARDED_FOR` | `bootstrap/app.php:29` + nginx |
| 3 | `AUTHZ-01` | فحص `is_active` + تدمير الجلسة | `EnsureStaffDashboardAccess.php` |
| 4 | `AUTH-01` | `throttle` على login/register/otp + حدّ على مستوى الحساب | `routes/api.php` |
| 5 | `MON-02` | `#[Validate]` + صلاحية `apply_discount` + سقف الخصم | `StaffDashboard.php:79,843` |
| 6 | `MON-01` | `INTERNAL_SCALE = 10` في `TaxCalculatorService` | `TaxCalculatorService.php:15` |
| 7 | `DB-01` | migration بالقيود الفريدة | migration جديدة |
| 8 | `AUTHZ-02` | صلاحيات + سقف معدّل على `CustomerLookup` | `CustomerLookup.php` |
| 9 | `AUTHZ-03` | `InvoicePolicy` + `authorize()` في كل مسار طباعة | `PrintController.php` |
| 10 | `MON-04` | استدعِ `determineInvoiceStatus()` | `InvoiceFinalizationService.php:48` |
| 11 | `CFG-05` | احذف تسريب الـ OTP، خصوصاً `!$smsEnabled` | `PhoneVerificationController.php:108` |
| 12 | `BOOK-03` | `markAsPaid` لا يُشتق من `payment_method` | `BookingService.php:45` |

---

## المرحلة 2 — التماسك المنطقي (أسبوع)

| # | المعرّف | المهمة |
|---|---|---|
| 13 | `BOOK-01` | `Appointment::scopeBlocking()` + استخدامه في كل الطبقات |
| 14 | `BOOK-02` | التحقق داخل `DB::transaction` + `lockForUpdate` |
| 15 | `MON-03` | قفل صفّي على الإنهاء + `DocumentNumberGenerator` بجدول عدّادات |
| 16 | `AUTH-02` | `lockForUpdate` على عدّاد محاولات الـ OTP |
| 17 | `AUTH-03` | تجزئة الـ OTP + `$hidden` |
| 18 | `BOOK-01` | أمر `bookings:purge-abandoned` + جدولته |
| 19 | `BOOK-04` | توحيد استعلام الإجازات بـ `COALESCE` |
| 20 | `MON-05` | حذف `InvoiceService::finalizeDraftInvoice` وتوحيد المسارات |
| 21 | `MON-06` | حذف كل `bcscale()` |
| 22 | `MON-07` | `addServiceDifferentProvider` عبر `TaxCalculatorService` |
| 23 | `BOOK-05` | تفعيل `custom_duration` (**بعد مراجعة البيانات**) |
| 24 | `PERF-01` | `CACHE_TTL_SECONDS = 60` + إبطال بالمراقب |
| 25 | `DB-04` | تخزين `get_setting` مؤقتاً |
| 26 | `AUTH-04` | تدوير توكن التحديث + إبطال الجلسات عند تغيير كلمة المرور |
| 27 | `QUAL-01` | حذف `BookingService2` و`AppointmentCreationService` |

---

## المرحلة 3 — الصلابة (أسبوعان)

| # | المعرّف | المهمة |
|---|---|---|
| 28 | `DB-02` | فهارس الأداء + استبدال `whereDate` بـ `whereBetween` |
| 29 | `PERF-03` | ترقيم صفحات + سقف `per_page` |
| 30 | `DB-06` | `softDeletes` على الجداول المالية |
| 31 | `MON-08` | قرار موثَّق بشأن TSE — وصله أو أوقفه بوضوح |
| 32 | `QUAL-03` | migration إعادة التسمية (`segnture`، `branchs`، `adress`، `noticifation`) |
| 33 | `QUAL-05` | الاختبارات السبعة التي تحرس ثغرات هذا التقرير |
| 34 | `QUAL-04` | تحديث `Agent.md` و`docs/*` |
| 35 | `PERF-02` | إبطال كاش بلا `Cache::tags` أو الانتقال إلى Redis |
| 36 | `QUAL-02` | تفعيل `break_minutes` أو إزالته من الواجهة |
| 37 | `BOOK-06/07/08` | إصلاح فحص التكرار والحد اليومي وسياسة الإلغاء |

---

## 12. الخلاصة

النظام **مبنيّ جيداً في جوهره**. طبقة التحقق مفصولة بوعي، ونظام صلاحيات لوحة الموظفين نموذجي، وحسابات bcmath في `BookingService` صحيحة رياضياً، ومعالجة الحجوزات المرتبطة تصميم متقدّم. التعليقات في الكود تُظهر مطوّراً يفكّر في المشكلات لا يكتفي بحلّها.

المشكلات التي وجدتُها تنتمي إلى ثلاث فئات:

**1. أخطاء إعداد.** سطر واحد في `bootstrap/app.php` عطّل ربط النماذج والسقف العام. سطر آخر أبطل كل حدود المعدّل. جدول إعدادات لم يُراجَع للسوق الألماني. **هذه الأرخص إصلاحاً والأعلى مردوداً** — وثلاثة موانع إطلاق منها تُحلّ بـ `UPDATE` في SQL.

**2. منطق مكرَّر انحرف.** قاعدة التعارض منفَّذة أربع مرات. حساب الضريبة أربع مرات. إنهاء الفاتورة ثلاث مرات. كل نسخة كانت صحيحة يوم كُتبت؛ ثم نُقّحت واحدة ولم تُنقّح الأخريات. **الإصلاح الحقيقي ليس ترقيع النسخ، بل حذفها وإبقاء واحدة.**

**3. ثغرات تزامن.** التحقق خارج المعاملة، وأرقام مستندات بلا قيود فريدة، وعدّادات بلا أقفال. هذه لا تظهر في الاختبار اليدوي — **تظهر يوم يصبح المشروع ناجحاً**، وهو أسوأ توقيت ممكن.

**التوصية:** نفّذ **المرحلة صفر والمرحلة 1** قبل الإطلاق — يومان إلى ثلاثة. المرحلتان 2 و3 يمكن أن تجريا بعد الإطلاق بأمان معقول، بشرط تنفيذ المرحلة 1 كاملة.

**البند الأهم منفرداً:** `AUTHZ-01`. سحب وصول موظف من واجهة الإدارة **لا يعمل فعلياً** على لوحة الموظفين. أي عمل بموظفين يحتاج هذه القدرة من اليوم الأول، والحالة الراهنة تمنحك شعوراً كاذباً بالأمان — وهو أخطر من غياب الميزة أصلاً.

---

*أُعدّ هذا التقرير بقراءة مباشرة للكود، واستعلام قاعدة البيانات الحيّة، وتنفيذ إثباتات فعلية على التطبيق. لم يُعدَّل أي ملف كود. كل نتيجة قابلة للتحقق عبر الموقع المذكور.*
