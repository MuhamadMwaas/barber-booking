<div dir="rtl">

# توثيق API: تأكيد رقم الهاتف (Phone Verification) — دليل الـ Frontend

> دليل عملي مخصّص لمبرمج التطبيق. يشرح الـ flow كامل، كل endpoint، شكل الطلب، شكل الرد، كل حالات الخطأ، وأمثلة جاهزة.

---

## 0. نظرة سريعة (TL;DR)

- صار عندك **3 أشياء**:
  1. تقدر **تعرف هل الرقم مأكّد أو لا** من بيانات البروفايل (حقل `phone_verified`).
  2. endpoint **لإرسال رمز SMS** إلى رقم المستخدم.
  3. endpoint **للتحقق من الرمز** وتفعيل الرقم.
- التأكيد **اختياري**: المستخدم يقدر يستخدم التطبيق عادي حتى لو رقمه غير مأكّد. أنت فقط تعرض تنبيه/شارة وتحثّه على التأكيد.
- كل الـ endpoints الجديدة **تتطلب توكن تسجيل دخول** (المستخدم لازم يكون داخل).

| العملية | Method | المسار |
|---|---|---|
| قراءة حالة التأكيد | `GET`  | `/api/profile` |
| إرسال رمز SMS | `POST` | `/api/profile/phone/send-otp` |
| التحقق من الرمز | `POST` | `/api/profile/phone/verify-otp` |

---

## 1. المصادقة (Authentication)

كل الطلبات هنا محميّة. أرسل دائماً هيدر التوكن:

```
Authorization: Bearer <access_token>
Accept: application/json
Content-Type: application/json
```

> التوكن هو نفسه الـ `access_token` اللي حصلت عليه بعد تسجيل الدخول. لو انتهت صلاحيته بتاخد `401 Unauthenticated` ولازم تعمل refresh.

---

## 2. كيف تعرف هل الرقم مأكّد؟ — `GET /api/profile`

عند جلب البروفايل (سواء عند فتح الشاشة أو بشكل دوري)، صار الرد يحتوي حقول جديدة تخص حالة الهاتف.

### الطلب
```
GET /api/profile
Authorization: Bearer <access_token>
Accept: application/json
```

### الرد `200`
```json
{
  "success": true,
  "message": "Profile retrieved successfully",
  "data": {
    "id": 12,
    "first_name": "Sami",
    "last_name": "Khaled",
    "full_name": "Sami Khaled",
    "email": "sami@example.com",
    "phone": "+9665xxxxxxxx",

    "phone_verified": false,
    "phone_verified_at": null,

    "email_verified_at": "2026-06-01T10:00:00.000000Z",
    "is_account_verified": true,
    "requires_otp_verification": false,

    "is_active": true,
    "created_at": "2026-06-01T10:00:00.000000Z",
    "updated_at": "2026-06-20T08:30:00.000000Z"
  }
}
```

### الحقول التي تهمّك للشاشة

| الحقل | النوع | المعنى وكيف تستخدمه |
|---|---|---|
| `phone` | string \| null | رقم المستخدم. لو `null` يعني ما في رقم على الحساب. |
| **`phone_verified`** | **boolean** | **الحقل الأساسي للشارة:** `true` = مأكّد (✅) ، `false` = غير مأكّد (اعرض تنبيه «أكّد رقمك»). |
| `phone_verified_at` | datetime \| null | وقت التأكيد (للعرض فقط إن أردت). `null` = غير مأكّد. |

> **القاعدة للـ UI:** اعرض شارة «لم يتم تأكيد الرقم» + زر «تأكيد» **عندما** `phone == موجود` و `phone_verified == false`.
> إذا `phone == null` اعرض «أضف رقمك وأكّده».

---

## 3. الـ Flow الكامل

```
┌──────────────────────────────────────────────────────────────────┐
│ 1) GET /api/profile                                              │
│    اقرأ phone_verified                                            │
│        ├─ true  → اعرض ✅ "مؤكّد"  (خلص)                          │
│        └─ false → اعرض زر "تأكيد الرقم"                           │
└──────────────────────────────────────────────────────────────────┘
                       │ المستخدم يضغط "تأكيد"
                       ▼
┌──────────────────────────────────────────────────────────────────┐
│ 2) POST /api/profile/phone/send-otp                              │
│    (اختياري) أرسل phone لو المستخدم بدّو يدخله/يصحّحه             │
│    → يوصل SMS فيه رمز للمستخدم                                    │
│    → الرد يعطيك masked_destination لعرضه ("أرسلنا رمزاً إلى ****") │
└──────────────────────────────────────────────────────────────────┘
                       │ افتح شاشة إدخال الرمز
                       ▼
┌──────────────────────────────────────────────────────────────────┐
│ 3) POST /api/profile/phone/verify-otp                            │
│    أرسل otp اللي أدخله المستخدم                                   │
│        ├─ 200 → نجح! phone_verified صار true                     │
│        └─ 422 → رمز خاطئ/منتهي → اعرض خطأ + زر "إعادة إرسال"      │
└──────────────────────────────────────────────────────────────────┘
```

---

## 4. الـ Endpoint الأول: إرسال الرمز

### `POST /api/profile/phone/send-otp`

يرسل رمز تحقق (OTP) عبر SMS إلى رقم هاتف الحساب.

### الطلب

**Headers:** التوكن (انظر القسم 1).

**Body:** كله اختياري.

| الحقل | النوع | إلزامي؟ | الوصف |
|---|---|---|---|
| `phone` | string (≤ 20) | لا | أرسله **فقط** لو المستخدم يريد إدخال رقم لأول مرة أو تصحيح رقمه. لو لم ترسله، نُرسل الرمز إلى الرقم الموجود على الحساب. |

> **مهم:** إذا أرسلت `phone` مختلفاً عن الرقم الحالي، النظام يحدّث الرقم على الحساب **ويصفّر حالة التأكيد** ثم يرسل الرمز للرقم الجديد.

#### مثال طلب (بدون تغيير الرقم)
```
POST /api/profile/phone/send-otp
Authorization: Bearer <access_token>
Content-Type: application/json

{}
```

#### مثال طلب (مع إدخال/تصحيح الرقم)
```json
{ "phone": "+9665xxxxxxxx" }
```

### الردود

#### ✅ نجاح `200`
```json
{
  "success": true,
  "message": "A verification code has been sent to your phone number.",
  "masked_destination": "********xxxx",
  "phone_verified": false,
  "otp": "123456"
}
```

| الحقل | الوصف |
|---|---|
| `masked_destination` | الرقم مُقنّعاً — اعرضه للمستخدم: «أرسلنا رمزاً إلى ‎********xxxx». |
| `otp` | **يظهر فقط في بيئة التطوير (debug).** في الإنتاج **لن يظهر** — الرمز يصل بالـ SMS فقط. لا تعتمد على وجوده في التطبيق النهائي. |

#### ❌ `422` — لا يوجد رقم / صيغة غير صالحة / رقم مستخدم بحساب آخر
صيغة أخطاء Laravel القياسية:
```json
{
  "message": "This phone number is already in use by another account.",
  "errors": {
    "phone": ["This phone number is already in use by another account."]
  }
}
```
رسائل أخرى محتملة تحت نفس المفتاح `phone`:
- `"No phone number is associated with this account. Please provide one to verify."` (ما في رقم ولا أرسلت واحد).

#### ❌ `400` — الرقم مؤكّد مسبقاً
```json
{
  "success": false,
  "message": "Your phone number is already verified.",
  "phone_verified": true
}
```

#### ❌ `429` — إعادة الإرسال بسرعة (Cooldown)
لا يمكن طلب رمز جديد قبل مرور **60 ثانية** على آخر طلب لنفس الرقم.
```json
{
  "success": false,
  "message": "Please wait 47 seconds before requesting a new code.",
  "retry_after": 47
}
```
> **للـ UI:** عطّل زر «إعادة الإرسال» واعرض عدّاد تنازلي بقيمة `retry_after`.

#### ❌ `429` — تجاوز حد الطلبات (Throttle)
أكثر من 6 طلبات في الدقيقة:
```json
{ "message": "Too Many Attempts." }
```
(يأتي معه هيدر `Retry-After` بالثواني.)

---

## 5. الـ Endpoint الثاني: التحقق من الرمز

### `POST /api/profile/phone/verify-otp`

يتحقق من الرمز ويفعّل الرقم على الحساب. **لا يصدر توكن جديد** — المستخدم يبقى بنفس جلسته.

### الطلب

| الحقل | النوع | إلزامي؟ | الوصف |
|---|---|---|---|
| `otp` | string | **نعم** | الرمز اللي أدخله المستخدم. |

```
POST /api/profile/phone/verify-otp
Authorization: Bearer <access_token>
Content-Type: application/json

{ "otp": "123456" }
```

### الردود

#### ✅ نجاح `200`
```json
{
  "success": true,
  "message": "Your phone number has been verified successfully.",
  "data": {
    "id": 12,
    "phone": "+9665xxxxxxxx",
    "phone_verified": true,
    "phone_verified_at": "2026-06-20T08:45:12.000000Z",
    "is_account_verified": true,
    "...": "بقية حقول UserResource"
  }
}
```
> الـ `data` هو نفس كائن المستخدم — حدّث حالتك المحلية منه مباشرة (`phone_verified` صار `true`).

#### ✅ `200` — الرقم مؤكّد مسبقاً (idempotent)
لو استدعيت التحقق ورقم المستخدم مؤكّد أصلاً:
```json
{
  "success": true,
  "message": "Your phone number is already verified.",
  "data": { "...": "UserResource" }
}
```

#### ❌ `422` — رمز خاطئ أو منتهٍ
```json
{
  "success": false,
  "message": "Invalid or expired verification code."
}
```
> **للـ UI:** اعرض الخطأ تحت حقل الرمز، وأبقِ زر «إعادة الإرسال» متاحاً (مع احترام الـ cooldown). صلاحية الرمز **10 دقائق**.

#### ❌ `422` — لا يوجد رقم على الحساب
```json
{
  "message": "No phone number is associated with this account.",
  "errors": { "phone": ["No phone number is associated with this account."] }
}
```

---

## 6. تغيير الرقم من شاشة تعديل البروفايل

### `POST /api/profile`

إذا غيّر المستخدم رقمه عبر تعديل البروفايل العادي، فإن **حالة التأكيد تُصفّر تلقائياً** (`phone_verified` يرجع `false`).

```
POST /api/profile
Authorization: Bearer <access_token>
Content-Type: application/json

{ "phone": "+9665yyyyyyyy" }
```

نتيجة الرد ستحوي `phone_verified: false`. عندها أعد عرض شارة «غير مؤكّد» واطلب من المستخدم التأكيد من جديد.

> لو حاول إدخال رقم مستخدم بحساب آخر تأخذ `422` (نفس صيغة أخطاء Laravel على المفتاح `phone`).

---

## 7. جدول رموز الحالة (Status Codes)

| الرمز | المعنى | أين يحدث |
|---|---|---|
| `200` | نجاح | إرسال/تحقق/قراءة البروفايل |
| `400` | الرقم مؤكّد مسبقاً | `send-otp` |
| `401` | التوكن غير صالح/منتهٍ | أي endpoint محمي |
| `422` | خطأ تحقق / رمز خاطئ / رقم مكرر / لا يوجد رقم | `send-otp`, `verify-otp`, `profile` |
| `429` | cooldown أو تجاوز حد الطلبات | `send-otp`, `verify-otp` |

---

## 8. سيناريوهات كاملة بالأمثلة

### السيناريو أ — المسار السعيد (Happy Path)
```
1) GET  /api/profile                         → phone_verified=false
2) POST /api/profile/phone/send-otp   {}     → 200 (SMS انبعت)
3) POST /api/profile/phone/verify-otp {otp}  → 200, phone_verified=true ✅
```

### السيناريو ب — مستخدم بلا رقم
```
1) GET  /api/profile                                  → phone=null
2) POST /api/profile/phone/send-otp {phone:"+966.."}  → 200 (تم حفظ الرقم + إرسال SMS)
3) POST /api/profile/phone/verify-otp {otp}           → 200 ✅
```

### السيناريو ج — رمز خاطئ ثم إعادة إرسال
```
1) POST /api/profile/phone/verify-otp {otp:"000000"}  → 422 "Invalid or expired..."
2) (انتظر انتهاء الـ cooldown إن وُجد)
3) POST /api/profile/phone/send-otp {}                → 200 (رمز جديد)
4) POST /api/profile/phone/verify-otp {otp}           → 200 ✅
```

### السيناريو د — ضغط متكرر على الإرسال
```
1) POST /api/profile/phone/send-otp {}  → 200
2) POST /api/profile/phone/send-otp {}  → 429 { retry_after: 53 }
   → عطّل الزر واعرض عدّاد 53 ثانية
```

---

## 9. مثال كود (JavaScript / axios)

```js
const api = axios.create({
  baseURL: 'https://YOUR_DOMAIN/api',
  headers: { Accept: 'application/json' },
});
api.defaults.headers.common['Authorization'] = `Bearer ${accessToken}`;

// 1) إرسال الرمز
async function sendPhoneOtp(phone /* اختياري */) {
  try {
    const { data } = await api.post('/profile/phone/send-otp', phone ? { phone } : {});
    // data.masked_destination → اعرضه للمستخدم
    return data;
  } catch (e) {
    if (e.response?.status === 429) {
      const retry = e.response.data.retry_after; // ثوانٍ
      // عطّل الزر واعرض عدّاد
    } else if (e.response?.status === 422) {
      const msg = e.response.data.errors?.phone?.[0] ?? e.response.data.message;
      // اعرض الخطأ
    }
    throw e;
  }
}

// 2) التحقق
async function verifyPhoneOtp(otp) {
  try {
    const { data } = await api.post('/profile/phone/verify-otp', { otp });
    // data.data.phone_verified === true → حدّث الحالة المحلية
    return data.data;
  } catch (e) {
    if (e.response?.status === 422) {
      // "Invalid or expired verification code."
    }
    throw e;
  }
}
```

---

## 10. ملاحظات مهمة للـ Frontend

1. **اعتمد على `phone_verified` (boolean)** لرسم الشارة — هو الأبسط والأوضح.
2. **`otp` في الرد = بيئة تطوير فقط.** في الإنتاج لن يصلك الرمز في الرد، فقط عبر الـ SMS. لا تبنِ منطقاً يعتمد على وجوده.
3. **احترم الـ cooldown (60 ثانية):** عند `429` مع `retry_after`، عطّل زر الإرسال واعرض عدّاد.
4. **صلاحية الرمز 10 دقائق** — بعدها يلزم رمز جديد.
5. **التأكيد اختياري:** لا تمنع المستخدم من استخدام التطبيق إذا لم يؤكّد. فقط نبّهه.
6. **بعد نجاح التحقق**، حدّث حالة المستخدم محلياً من حقل `data` في الرد (أو أعد نداء `GET /api/profile`).
7. **عند تغيير الرقم** من تعديل البروفايل، توقّع `phone_verified=false` وأعد دورة التأكيد.

</div>
