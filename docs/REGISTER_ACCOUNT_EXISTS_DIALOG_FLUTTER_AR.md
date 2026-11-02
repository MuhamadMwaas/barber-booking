<div dir="rtl">

# حوار «الحساب موجود» عند التسجيل: دليل مطوّر Flutter

## لمين هالملف؟

لمطوّر تطبيق Flutter. بيشرح شو لازم يعرض التطبيق لما المستخدم يحاول **ينشئ حساب برقم هاتف أو إيميل مستخدم أصلاً** بحساب تاني، وشو تغيّر بالسيرفر.

الكلام بهالملف مأخوذ من الكود الحالي:

| الملف | الدور |
|---|---|
| `routes/api.php` | `POST /api/auth/register` |
| `app/Http/Requests/RegisterRequest.php` | الـ validation وبناء رد الـ 409 (`failedValidation()` و `throwAccountExists()`) |
| `lang/{ar,de,en}/auth.php` | نصوص الحوار والأزرار |
| `tests/Feature/AuthVerificationFlowTest.php` | الاختبارات اللي بتثبّت شكل الرد |

---

## الخلاصة بسطرين

1. إذا الهاتف **أو** الإيميل مستخدم، `POST /api/auth/register` بيرجع **409** مع `error_code` و `title` و `message` و `actions` (نص الزرين)، **وكل النصوص مترجمة من السيرفر** حسب `Accept-Language`.
2. التطبيق بيعرض حوار فيه العنوان والرسالة وزرّين: **تسجيل الدخول** (بيروح لشاشة الدخول) و **إغلاق** (بيسكّر الحوار ويضل بشاشة التسجيل). **ما في انتقال لشاشة OTP.**

---

## شو تغيّر؟

| الحالة | قبل | هلّق |
|---|---|---|
| إيميل مستخدم | `409 EMAIL_ALREADY_EXISTS` + `title` + `message` | نفسه **+ `actions`** (إضافة فقط، ما انكسر شي) |
| هاتف مستخدم | `422` برسالة Laravel العامة: *"The phone has already been taken."* | **`409 PHONE_ALREADY_EXISTS`** + `title` + `message` + `actions` |

> ⚠️ **التغيير المهم:** حالة الهاتف المكرر بالتسجيل صارت **409 بدل 422**. إذا عندك كود بيفحص `422` + `PHONE_ALREADY_EXISTS` بشاشة التسجيل، رح يوقف يشتغل. اعتمد على المنطق بالأسفل.

---

## الطلب (ما تغيّر)

```http
POST /api/auth/register
Accept: application/json
Accept-Language: ar          ← ar | de | en
Content-Type: application/json

{
  "first_name": "Ali",
  "last_name": "Hasan",
  "registration_method": "phone",
  "phone": "0152 2391 7565",
  "password": "Password@123",
  "password_confirmation": "Password@123"
}
```

- **`Accept-Language` ضروري** لتطلع النصوص بلغة المستخدم. ممكن كمان تبعت `"locale": "ar"` بالـ body، وهو بياخد الأولوية على الـ header.
- إذا ما بعتت لغة مدعومة، بترجع النصوص بلغة السيرفر الافتراضية.
- السيرفر بيوحّد شكل الرقم قبل الفحص: `0152 2391 7565` و `+49 1522 3917565` و `004915223917565` كلهم **نفس الرقم**. يعني ما بتقدر تتجاوز الفحص بكتابة الرقم بشكل مختلف، وما في داعي تعمل تنسيق بالتطبيق.

---

## الرد: 409 Conflict

### هاتف مستخدم

```json
{
  "success": false,
  "error_code": "PHONE_ALREADY_EXISTS",
  "title": "رقم الهاتف مرتبط بحساب آخر",
  "message": "يرجى استخدام رقم هاتف آخر أو تسجيل الدخول",
  "actions": {
    "login": "تسجيل الدخول",
    "close": "إغلاق"
  }
}
```

### إيميل مستخدم

```json
{
  "success": false,
  "error_code": "EMAIL_ALREADY_EXISTS",
  "title": "الحساب موجود بالفعل",
  "message": "تم إنشاء حساب باستخدام هذا البريد الإلكتروني مسبقًا. سجّل الدخول للمتابعة.",
  "actions": {
    "login": "تسجيل الدخول",
    "close": "إغلاق"
  }
}
```

### النصوص حسب اللغة (حالة الهاتف)

| المفتاح | `ar` | `de` | `en` |
|---|---|---|---|
| `title` | رقم الهاتف مرتبط بحساب آخر | Diese Handynummer ist bereits mit einem anderen Konto verknüpft. | This phone number is already linked to another account. |
| `message` | يرجى استخدام رقم هاتف آخر أو تسجيل الدخول | Bitte verwende eine andere Handynummer oder melde dich an. | Please use a different phone number or sign in. |
| `actions.login` | تسجيل الدخول | Anmelden | Sign in |
| `actions.close` | إغلاق | Schließen | Close |

> الرد **ما فيه** `user` ولا `access_token` ولا `errors`. وما بينعمل أي حساب جديد بالسيرفر.

---

## المنطق المطلوب بالتطبيق

```
register() →
  201                                   → شاشة OTP (متل قبل)
  409 + error_code ∈ {PHONE_ALREADY_EXISTS, EMAIL_ALREADY_EXISTS}
                                        → حوار «الحساب موجود» (تحت)
  422                                   → أخطاء الحقول تحت كل حقل (متل قبل)
  429                                   → رسالة "طلبات كثيرة" من body.message
```

**قواعد:**

1. **قرّر على `error_code`، مش على نص `message`.** النص بيتغيّر حسب اللغة وممكن ينعدّل لاحقاً. الرمز ثابت.
2. **اعرض النصوص اللي جاية من السيرفر** (`title`، `message`، `actions.login`، `actions.close`). هيك بنعدّل الصياغة من السيرفر بدون إصدار جديد للتطبيق.
3. **احتفظ بنصوص احتياطية محلية** (الجدول فوق) بحال حقل ناقص، مثلاً لو التطبيق اتصل بسيرفر أقدم ما بيرجع `actions`.
4. **زر «تسجيل الدخول»:** روح لشاشة الدخول، اختار تبويب الهاتف (أو الإيميل حسب الحالة)، **واملأ الحقل بالقيمة اللي كتبها المستخدم**، وخلّي حقل كلمة السر فاضي.
5. **زر «إغلاق»:** سكّر الحوار بس. ضل بشاشة التسجيل، **وخلّي البيانات اللي كتبها المستخدم متل ما هي**، وحطّ الـ focus على حقل الهاتف ليغيّره.
6. **لا تنقل المستخدم لشاشة OTP** بهالحالة أبداً.

---

## كود Dart مقترح

### 1) الموديل

```dart
class AccountExistsError {
  final String errorCode; // PHONE_ALREADY_EXISTS | EMAIL_ALREADY_EXISTS
  final String? title;
  final String? message;
  final String? loginLabel;
  final String? closeLabel;

  const AccountExistsError({
    required this.errorCode,
    this.title,
    this.message,
    this.loginLabel,
    this.closeLabel,
  });

  static const codes = {'PHONE_ALREADY_EXISTS', 'EMAIL_ALREADY_EXISTS'};

  bool get isPhone => errorCode == 'PHONE_ALREADY_EXISTS';

  /// يرجّع null إذا الرد مش حالة «الحساب موجود».
  static AccountExistsError? tryParse(int? status, dynamic body) {
    if (status != 409 || body is! Map) return null;
    final code = body['error_code'];
    if (code is! String || !codes.contains(code)) return null;

    final actions = body['actions'] is Map ? body['actions'] as Map : const {};
    return AccountExistsError(
      errorCode: code,
      title: body['title'] as String?,
      message: body['message'] as String?,
      loginLabel: actions['login'] as String?,
      closeLabel: actions['close'] as String?,
    );
  }
}
```

### 2) استدعاء الـ API (Dio)

> Dio بيرمي `DioException` لأي status ≥ 400، فلازم تلتقط الـ 409 بالـ `catch`.

```dart
Future<void> register(RegisterPayload payload) async {
  try {
    final res = await dio.post(
      '/api/auth/register',
      data: payload.toJson(),
      options: Options(headers: {
        'Accept': 'application/json',
        'Accept-Language': currentLocale.languageCode, // ar | de | en
      }),
    );
    // 201 → شاشة OTP
    goToOtp(res.data);
  } on DioException catch (e) {
    final status = e.response?.statusCode;
    final body = e.response?.data;

    final exists = AccountExistsError.tryParse(status, body);
    if (exists != null) {
      await showAccountExistsDialog(context, exists, payload);
      return;
    }

    if (status == 422) {
      showFieldErrors(body['errors']); // متل قبل
      return;
    }

    showGenericError(body?['message']);
  }
}
```

إذا عم تستخدم `http` بدل Dio، نفس الشي: `AccountExistsError.tryParse(res.statusCode, jsonDecode(res.body))`.

### 3) الحوار

```dart
Future<void> showAccountExistsDialog(
  BuildContext context,
  AccountExistsError e,
  RegisterPayload payload,
) {
  final l10n = AppLocalizations.of(context)!; // النصوص الاحتياطية

  return showDialog<void>(
    context: context,
    builder: (ctx) => AlertDialog(
      title: Text(e.title ??
          (e.isPhone ? l10n.phoneExistsTitle : l10n.emailExistsTitle)),
      content: Text(e.message ??
          (e.isPhone ? l10n.phoneExistsBody : l10n.emailExistsBody)),
      actions: [
        TextButton(
          onPressed: () {
            Navigator.of(ctx).pop();
            phoneFocusNode.requestFocus(); // يضل بشاشة التسجيل
          },
          child: Text(e.closeLabel ?? l10n.close),
        ),
        FilledButton(
          onPressed: () {
            Navigator.of(ctx).pop();
            Navigator.of(context).pushReplacementNamed(
              '/login',
              arguments: LoginPrefill(
                method: e.isPhone ? 'phone' : 'email',
                identifier: e.isPhone ? payload.phone : payload.email,
              ),
            );
          },
          child: Text(e.loginLabel ?? l10n.signIn),
        ),
      ],
    ),
  );
}
```

> أسماء الـ routes والـ `l10n` أمثلة، بدّلها بالموجود عندك. اتجاه الأزرار (RTL/LTR) بيتظبط تلقائياً من `Directionality`.

---

## انتبه: مكان تاني بيرجع نفس الرمز بس **بدون حوار**

إضافة أو تغيير رقم الهاتف **من داخل الحساب** (`/api/profile/phone/send-otp` و `/api/profile`) **ما تغيّر**. لسّا بيرجع:

```json
422 {
  "message": "...",
  "error_code": "PHONE_ALREADY_EXISTS",
  "errors": { "phone": ["..."] }
}
```

هون المستخدم **داخل أصلاً**، فزر «تسجيل الدخول» ما إلو معنى. اعرض الخطأ تحت حقل الهاتف متل أي خطأ validation. **حوار «الحساب موجود» بس لشاشة التسجيل.**

---

## Checklist للاختبار

- [ ] تسجيل برقم موجود، بلغة `ar` → حوار بالعربي بالنص المطابق للجدول، وزرّين «تسجيل الدخول» / «إغلاق».
- [ ] نفس الشي بـ `de` و `en`.
- [ ] نفس الرقم بصيغة مختلفة (مثلاً `0152…` بدل `+49152…`) → نفس الحوار.
- [ ] «إغلاق» → الحوار بيسكّر، البيانات المكتوبة باقية، والـ focus على حقل الهاتف.
- [ ] «تسجيل الدخول» → شاشة الدخول، تبويب الهاتف، والرقم معبّى.
- [ ] تسجيل بإيميل موجود → نفس الحوار بنصوص الإيميل.
- [ ] ما في انتقال لشاشة OTP بأي حالة من هدول.
- [ ] رقم غير صالح (`not-a-phone`) → لسّا **422** وخطأ تحت الحقل (`error_code: INVALID_PHONE_NUMBER`)، مش الحوار.
- [ ] تغيير الرقم من البروفايل لرقم مستخدم → خطأ تحت الحقل، **مش** الحوار.

</div>
