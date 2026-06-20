<div dir="rtl">

# توثيق API: إعدادات التطبيق (App Settings) — دليل الـ Frontend

> دليل عملي مخصّص لمبرمج التطبيق. يشرح كيف **تعرض** إعدادات المستخدم و**تعدّلها** عبر الـ API. حالياً الإعدادات هي قنوات تذكير الحجز (إيميل / SMS)، لكن **الشاشة لازم تُبنى ديناميكياً** لأن أي خيار جديد بينضاف من الباك إند بيظهر تلقائياً بدون تحديث للتطبيق.

---

## 0. نظرة سريعة (TL;DR)

- عندك **endpointين فقط**:
  1. `GET /api/settings` → يرجّع **قائمة كل الخيارات** + القيمة الحالية لكل خيار للمستخدم.
  2. `PATCH /api/settings/{key}` → **يعدّل خيار واحد** بتمرير قيمته الجديدة.
- **ابنِ الشاشة من القائمة اللي يرجّعها GET** — لا تكتب الخيارات يدوياً بالتطبيق. كل خيار جايي ومعه `label` و`type` و`value`، فتعرف شو تعرض وكيف.
- `type` بيحدّد شكل الـ widget: `boolean` → Switch/Toggle.
- كل الطلبات **تتطلب توكن تسجيل دخول** (المستخدم لازم يكون داخل ومؤكّد).

| العملية | Method | المسار |
|---|---|---|
| جلب كل الإعدادات + قيمها | `GET`   | `/api/settings` |
| تعديل قيمة خيار واحد | `PATCH` | `/api/settings/{key}` |

---

## 1. المصادقة (Authentication)

كل الطلبات محميّة. أرسل دائماً:

<div dir="ltr">

```
Authorization: Bearer <access_token>
Accept: application/json
Content-Type: application/json
```

</div>

> - التوكن هو نفسه `access_token` اللي حصلت عليه بعد تسجيل الدخول. لو انتهت صلاحيته بتاخد `401 Unauthenticated` ولازم تعمل refresh.
> - **اللغة:** الـ `label` و`description` بيرجعو **مترجمين جاهزين** حسب لغة المستخدم المحفوظة (`locale`). يعني ما تحتاج تترجم بالتطبيق — اعرض النص كما هو.

---

## 2. جلب الإعدادات — `GET /api/settings`

استدعِه عند فتح شاشة الإعدادات (أو شاشة الإشعارات).

### الطلب
<div dir="ltr">

```
GET /api/settings
Authorization: Bearer <access_token>
Accept: application/json
```

</div>

### الرد `200`
<div dir="ltr">

```json
{
  "success": true,
  "data": [
    {
      "key": "reminder_email_enabled",
      "label": "تذكير المواعيد عبر الإيميل",
      "description": "استقبل تذكيرات مواعيدك عبر البريد الإلكتروني أيضاً.",
      "type": "boolean",
      "group": "notifications",
      "value": false,
      "is_default": true
    },
    {
      "key": "reminder_sms_enabled",
      "label": "تذكير المواعيد عبر الرسائل النصية",
      "description": "استقبل تذكيرات مواعيدك عبر الرسائل النصية أيضاً.",
      "type": "boolean",
      "group": "notifications",
      "value": false,
      "is_default": true
    }
  ]
}
```

</div>

### شرح الحقول

| الحقل | النوع | الاستخدام بالواجهة |
|---|---|---|
| `key` | string | المعرّف اللي بترسله بالـ`PATCH`. **لا تعرضه للمستخدم.** |
| `label` | string | عنوان الخيار — اعرضه كاسم للـ Switch (مترجم جاهز). |
| `description` | string \| null | شرح صغير تحت العنوان (اختياري). |
| `type` | string | نوع القيمة → يحدّد شكل الـ widget. حالياً `boolean`. |
| `group` | string \| null | لتجميع الخيارات بأقسام (مثلاً كل `notifications` تحت عنوان «الإشعارات»). |
| `value` | حسب `type` | القيمة الحالية للمستخدم. لـ`boolean` بتكون `true/false` → حالة الـ Switch. |
| `is_default` | boolean | `true` يعني المستخدم لسا ما غيّر الخيار (القيمة هي الافتراضية). للمعلومة فقط. |

### كيف تبني الشاشة
- اعمل loop على المصفوفة، وجمّع حسب `group`.
- لكل عنصر: `type === "boolean"` → اعرض Switch، حالته من `value`، عنوانه `label`، ووصفه `description`.
- خزّن `key` مع كل عنصر حتى تبعته وقت التعديل.

---

## 3. تعديل خيار — `PATCH /api/settings/{key}`

استدعِه لما المستخدم يبدّل الـ Switch (يفعّل/يلغّي).

### الطلب
<div dir="ltr">

```
PATCH /api/settings/reminder_email_enabled
Authorization: Bearer <access_token>
Accept: application/json
Content-Type: application/json

{
  "value": true
}
```

</div>

> - `{key}` بالمسار = نفس `key` من الـ GET.
> - `value` لازم يطابق نوع الخيار: لـ`boolean` ابعت `true` أو `false` (Boolean حقيقي، مو string).

### الرد `200` (نجاح)
<div dir="ltr">

```json
{
  "success": true,
  "message": "تم تحديث الإعداد بنجاح.",
  "data": {
    "key": "reminder_email_enabled",
    "value": true
  }
}
```

</div>

> `data.value` هي القيمة بعد الحفظ (بنوعها الصحيح) — استخدمها لتأكيد حالة الـ Switch.

---

## 4. حالات الخطأ

| الكود | المعنى | شو تعمل بالواجهة |
|---|---|---|
| `401` | التوكن غير صالح/منتهي | اعمل refresh أو رجّع المستخدم لتسجيل الدخول. |
| `404` | الخيار (`key`) غير موجود أو غير مفعّل | تأكد إنك بتبعت نفس `key` من الـ GET. |
| `422` | القيمة فشلت بالتحقق (مثلاً ما هي boolean) | تأكد من نوع `value`. شوف `errors`. |

### مثال خطأ `422`
<div dir="ltr">

```json
{
  "success": false,
  "message": "بيانات غير صالحة.",
  "errors": {
    "value": ["The value field must be true or false."]
  },
  "error_type": "validation_error"
}
```

</div>

### مثال خطأ `404`
<div dir="ltr">

```json
{
  "success": false,
  "message": "الإعداد غير موجود.",
  "error_type": "not_found"
}
```

</div>

---

## 5. الخياران الحاليان (للمعلومة)

| `key` | شو بيعمل لما يكون `true` |
|---|---|
| `reminder_email_enabled` | المستخدم بيستقبل تذكير الموعد **عبر الإيميل** كمان (بالإضافة لإشعار الجوال). |
| `reminder_sms_enabled` | المستخدم بيستقبل تذكير الموعد **عبر SMS** كمان (يتطلب وجود رقم جوال بالحساب). |

> **مهم:** إشعار الجوال (Push) دايماً شغّال — هو القناة الأساسية. الإيميل والـSMS قنوات **إضافية اختيارية**. والافتراضي إنهن **مطفيين** (`false`) لكل مستخدم جديد.

---

## 6. كيف ينعكس التغيير؟

- بمجرد ما الـ`PATCH` يرجّع `200`، القيمة تخزّنت.
- التذكير بيتقرأ تفضيلك **لحظة إرساله**، فأي تفعيل/إلغاء **ينعكس فوراً** على أي تذكير قادم — ما في تأخير ولا حاجة لإعادة جدولة.

---

## 7. تدفّق عملي مقترح (Pseudo-code)

<div dir="ltr">

```
// عند فتح شاشة الإعدادات:
const res = await api.get('/api/settings');
const groups = groupBy(res.data, 'group');   // ابنِ الأقسام ديناميكياً
render(groups);                               // boolean → Switch(value, label, description)

// عند تبديل Switch:
async function onToggle(item, newValue) {
  setSwitch(item.key, newValue);              // تحديث متفائل (optimistic)
  try {
    const res = await api.patch(`/api/settings/${item.key}`, { value: newValue });
    setSwitch(item.key, res.data.value);      // ثبّت القيمة المؤكّدة من السيرفر
  } catch (e) {
    setSwitch(item.key, !newValue);           // رجوع (rollback) عند الفشل
    showError(e);
  }
}
```

</div>

---

## 8. ملاحظات أخيرة

- **لا تكتب الخيارات يدوياً.** الشاشة data-driven بالكامل: أي خيار جديد يضيفه الباك إند (مثلاً `reminder_push_enabled` لاحقاً) بيظهر تلقائياً من الـ GET. فقط تعامل مع كل `type` (حالياً `boolean`؛ لو ظهر `string`/`integer` لاحقاً اعرض حقل نص/رقم).
- اعرض `description` لو موجود، وتجاهله لو `null`.
- ما في حاجة لـendpoint منفصل لكل خيار — `PATCH /api/settings/{key}` واحد لكلهم.

</div>
