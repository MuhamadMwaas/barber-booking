# AUTH-GOOGLE — تسجيل الدخول بجوجل من تطبيق الموبايل

**التاريخ:** 2026-10-17
**الحالة:** الكود مصلَّح ومُختبَر محلياً بتستات. لسا ما تجرّب بتوكن حقيقي من التطبيق، وما في commit.
**القرار الحالي:** بنبقى على مشروع Firebase تبع مطوّر التطبيق (الطريقة 2) بشكل مؤقت.

---

## 1. المشكلة

التطبيق كان يسجّل الدخول بجوجل بنجاح، ويبعت التوكن للسيرفر:

```http
POST /api/auth/google
{ "token": "GOOGLE_ID_TOKEN" }
```

والسيرفر كان يرد دايماً:

```json
{ "message": "Invalid Google token" }
```

## 2. السبب الحقيقي

مطوّر التطبيق ظن إن السبب هو الـ Client ID. هاد **نصّه صحيح**، بس كان في سبب أعمق قبله.

### السبب الأول (الأساسي): الكود كان يقرأ التوكن على إنه نوع تاني

جوجل بيعطي نوعين توكن:

| | ID Token | Access Token |
|---|---|---|
| شو هو | "هوية" موقّعة: هاد فلان وإيميله كذا | "مفتاح" لطلب بيانات من جوجل |
| شكله | `eyJhbGciOi...` (JWT، 3 أجزاء بينها نقاط) | `ya29.a0Af...` |
| مين بيبعته التطبيق | ✅ هاد | ❌ |

الكود القديم كان يستعمل `Socialite::driver('google')->userFromToken($idToken)`، وهي دالة بتتوقع **Access Token**. فكان أي ID Token يترفض مهما كان صحيح، وما كان الكود يقرأ `GOOGLE_CLIENT_ID` أصلاً.

> تشبيه: متل لما تعطي الحارس هويتك وهو مبرمج يقبل مفتاح بس.

### السبب الثاني: التطبيق والسيرفر على مشاريع Google مختلفة

كل ID Token فيه حقل `aud` بيقول "هالتوكن صادر لمين". كانت في أكتر من Client ID مستعملة بنفس الوقت:

| المكان | Client ID |
|---|---|
| `GOOGLE_CLIENT_ID` بـ `.env` الباك إند | `924997862398-...` |
| Google Console (Web client 1، الصورة اللي شاركها صاحب المشروع) | `1088227039910-...` |
| رقم ذكره مطوّر التطبيق إنه "عند السيرفر" (مش موجود بالـ `.env` المحلي) | `120153122367-...` |
| مشروع Firebase تبع التطبيق (`app-test-2c25c`، اسمه LOOKUP) | `710582652316-...` |

## 3. مشاكل تانية انكشفت وانحلّت

1. **ما كان في فحص للـ audience بـ `/api/auth/google`.** يعني توكن جوجل صادر لأي تطبيق تاني كان ممكن يفوت، ولأن الربط بيصير بالإيميل، هاد معناه دخول على حساب موجود. انسدّت.
2. **المستخدم الجديد كان رح ينشئ بحقل `name`**، وهاد مش موجود بالـ fillable. فكان `first_name` (NOT NULL) يضل فاضي ويصير خطأ 500. انحلّت.
3. **المسارات `google/mobile` و `google/redirect` و `google/callback` بـ `routes/api.php`** كانت تأشّر على `SocialAuthController`، والدوال الحقيقية موجودة بـ `SocialApiAuthController`، فكانوا يرجعوا 500. انحلّت.

## 4. شو اتغيّر بالكود

| الملف | التغيير |
|---|---|
| `app/Services/Auth/GoogleSignInService.php` (جديد) | المكان الوحيد اللي بيتحقق من توكن جوجل وبينشئ أو يربط المستخدم |
| `app/Http/Controllers/Api/SocialAuthController.php` | `POST /api/auth/google` صار يستعمل الـ service بدل Socialite |
| `app/Http/Controllers/Api/SocialApiAuthController.php` | `google/mobile` والـ web callback صاروا يستعملوا نفس الـ service |
| `routes/api.php` | تصحيح الـ controller بـ 3 مسارات |
| `config/services.php` | إضافة `services.google.allowed_audiences` |
| `.env.example` | توثيق متغيرات Google |
| `tests/Feature/Auth/GoogleSignInTest.php` (جديد) | 7 تستات، كلها ناجحة |

### كيف بيتحقق السيرفر هلق (`GoogleSignInService::verifyIdToken`)

1. توقيع جوجل وتاريخ الانتهاء والـ `iss` (لازم يكون `accounts.google.com`)، عن طريق مكتبة `google/apiclient`.
2. `aud` لازم يكون من القائمة المسموحة (القسم 5).
3. `email_verified` لازم يكون `true`، لأن الحسابات بتنربط بالإيميل.

إذا نجح، الربط بيصير هيك: بالأول بيدوّر بـ `google_id`، إذا ما لقى بيدوّر بالإيميل (وبيربط الحساب الموجود)، وإذا ما لقى بينشئ مستخدم جديد.

**شكل الطلب ما تغيّر:** الحقل لسا `token`، وما في شي لازم يتغيّر بالتطبيق.

**شكل الرد:**

```json
{
  "user": { "id": 1, "name": "Ali Hasan", "first_name": "Ali", "last_name": "Hasan", "email": "...", "avatar_url": "..." },
  "access_token": "...",
  "refresh_token": "..."
}
```

> تذكير: الـ `refresh_token` بيُستعمل مرة وحدة (شوف rotation)، فلازم التطبيق يخزّن الجديد بعد كل refresh.

## 5. الإعداد الحالي (الطريقة 2: Firebase تبع مطوّر التطبيق)

السيرفر بيقبل ID Tokens لما يكون الـ `aud` واحد من هدول:
- `GOOGLE_CLIENT_ID`
- `GOOGLE_ANDROID_CLIENT_ID`
- `GOOGLE_IOS_CLIENT_ID`
- `GOOGLE_ALLOWED_AUDIENCES` (قائمة مفصولة بفواصل)

الموجود حالياً بـ `.env` المحلي:

```env
GOOGLE_CLIENT_ID=924997862398-jq9li49nceld02fu47vosqr247vrsff7.apps.googleusercontent.com
GOOGLE_ALLOWED_AUDIENCES=710582652316-jka04ehvmrm0ecmcje436fvs7f2a3klh.apps.googleusercontent.com
```

تأكّدت إن الاتنين مقبولين بعد `php artisan config:clear`.

### شرط مهم لمطوّر التطبيق

مع Firebase في توكنين وبيتشابهوا:

| | |
|---|---|
| ✅ ابعت | `googleAuth.idToken`، اللي بيعطيه Google Sign-In (`iss` = `accounts.google.com`) |
| ❌ لا تبعت | `FirebaseAuth.instance.currentUser.getIdToken()`، توكن Firebase (`iss` = `securetoken.google.com/app-test-2c25c`) |

السيرفر بيقبل الأول بس.

## 6. شو باقي (TODO)

- [ ] **السيرفر الحقيقي (lookupfriseur.com):** إضافة `GOOGLE_ALLOWED_AUDIENCES` بالـ `.env` تبعه، وبعدين `php artisan config:clear` (أو `config:cache`).
- [ ] رفع التعديلات للسيرفر (لسا ما في commit).
- [ ] تجربة حقيقية من التطبيق، والتأكد إن الرد 200 وفيه `access_token`.
- [ ] **الأمان:** `GOOGLE_CLIENT_SECRET` انلصق بمحادثة. إذا انشارك برا، لازم نعمل secret جديد من Google Console (زر Add secret) ونلغي القديم.
- [ ] **على المدى الطويل (الطريقة 1):** مشروع Firebase اسمه `app-test` وهو على حساب مطوّر التطبيق. إذا انحذف، تسجيل الدخول بيوقف عند كل المستخدمين. الحل الدائم:
  1. صاحب المشروع بيفوت على Google Cloud Console تبعه (مش Firebase).
  2. بيعمل OAuth client من نوع **Android** (اسم الـ package والـ SHA-1 بيعطيهم مطوّر التطبيق)، ونوع **iOS** إذا لازم.
  3. التطبيق بيستعمل رقم الـ Web client تبع الباك إند كـ `serverClientId`.
  4. بعدها `GOOGLE_ALLOWED_AUDIENCES` بيصير ما إله لزوم.
  - ملاحظة: Firebase مش ضروري أبداً لتسجيل الدخول بجوجل.
- [ ] نوحّد الـ Client IDs: `.env` (`924997862398`) مقابل Console (`1088227039910`). لازم نعرف أي واحد هو الصحيح.

## 7. إذا رجع `Invalid Google token`

1. افتح `storage/logs/laravel.log`:
   - `Google ID token has an unknown audience` معناها التوكن صادر لـ Client ID مش بالقائمة. القيمة `aud` بتكون بالسطر نفسه. ضيفها لـ `GOOGLE_ALLOWED_AUDIENCES` أو صحّح التطبيق.
   - `Google ID token verification failed` معناها التوكن مش ID Token من جوجل (غالباً توكن Firebase أو access token)، أو منتهي الصلاحية.
   - ما في ولا سطر من هدول؟ معناها `email_verified` = false.
2. حط التوكن على jwt.io وشوف `iss` و `aud`.
3. بعد أي تعديل على `.env`، شغّل `php artisan config:clear`.

## 8. التستات

```bash
php artisan test tests/Feature/Auth/GoogleSignInTest.php
```

بتغطي: توكن صحيح بينشئ مستخدم، audience إضافي مقبول، تطبيق تاني مرفوض، إيميل مش مأكَّد مرفوض، توقيع غلط مرفوض، ربط حساب موجود بالإيميل، ومسار `/mobile` شغّال.

---

## 9. تحديث 2026-10-23: التوافق مع كود تطبيق MAUI

مطوّر التطبيق شارك الكود (.NET MAUI مع مكتبة `FirebaseAuthentication.net` 4.1.0) ورقم Client ID جديد.

### كيف بيشتغل التطبيق

1. `FirebaseAuthClient.SignInWithRedirectAsync(Google)` بيفتح صفحة جوجل جوّا WebView، عن طريق `app-test-2c25c.firebaseapp.com`.
2. بعدها بيختار التوكن بهالترتيب (أول واحد مش فاضي):
   1. `id_token` من رابط الـ redirect
   2. `credential.AuthCredential` (توكن جوجل)
   3. **`credential.User.GetIdTokenAsync()` (توكن Firebase)**، كاحتياط
3. بيبعت `POST https://lookupfriseur.com/api/auth/google` ومعه `token` و `id_token` و `user_id` و `email` و `display_name`. السيرفر بيقرأ `token` بس، والباقي بيتجاهله.

### شو تغيّر بالسيرفر

| | |
|---|---|
| `GOOGLE_ALLOWED_AUDIENCES` | انضاف `710582652316-i98i7o68srv3smf6v1c49lr42g0qtid2...`، وهو الـ Web client اللي بيستعمله Firebase مع جوجل |
| `GOOGLE_FIREBASE_PROJECT_ID=app-test-2c25c` (جديد) | السيرفر صار يقبل **توكن Firebase كمان** (الاحتياط رقم 3)، بس بشروط (تحت) |
| شكل الرد | صار نفس شكل تسجيل الدخول العادي: `user` (UserResource)، `access_token`، `access_expires_at`، `refresh_token`، `refresh_expires_at`، `token_type`. ⚠️ `user.name` صار اسمه `user.full_name` |

### شروط قبول توكن Firebase (`verifyFirebaseIdToken`)

- التوقيع: بمفاتيح جوجل العامة `securetoken@system.gserviceaccount.com`، وبتنحفظ بالكاش ساعة. إذا الكاش معطّل، السيرفر بيجيبها مباشرة.
- `iss` = `https://securetoken.google.com/app-test-2c25c` و `aud` = `app-test-2c25c`.
- `firebase.sign_in_provider` = `google.com`. يعني حساب Firebase بإيميل وكلمة سر **ما بيفوت**.
- `email_verified` = true.
- `google_id` بيتاخد من `firebase.identities["google.com"][0]`، مش من Firebase uid. هيك نفس المستخدم بيوصل لنفس الحساب، أي نوع توكن بعت.
- إذا `GOOGLE_FIREBASE_PROJECT_ID` فاضي، توكنات Firebase بتترفض كلها.

### السيرفر الحقيقي، لازم ينضاف بالـ `.env`

```env
GOOGLE_ALLOWED_AUDIENCES=710582652316-jka04ehvmrm0ecmcje436fvs7f2a3klh.apps.googleusercontent.com,710582652316-i98i7o68srv3smf6v1c49lr42g0qtid2.apps.googleusercontent.com
GOOGLE_FIREBASE_PROJECT_ID=app-test-2c25c
```

وبعدين `php artisan config:clear`.

### ملاحظات لمطوّر التطبيق

- `loukupm/Model/AuthtoctionWithGoogle.cs` قديم وما حدا بيستعمله، وكل قيمه placeholders. الأفضل يتحذف حتى ما يلخبط.
- `user.name` صار `user.full_name`، و`first_name` و`last_name` موجودين كمان.
- رسائل اللوغ الجديدة: `Firebase ID token verification failed` (توقيع غلط أو منتهي الصلاحية)، و`Firebase ID token rejected` (مشروع غلط، أو الحساب مش من جوجل، أو الإيميل مش مأكَّد).

التستات صاروا 12، كلهم ناجحين.
