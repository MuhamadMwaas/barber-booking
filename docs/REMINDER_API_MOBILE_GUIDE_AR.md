<div lang="ar" dir="rtl">

# دليل مطوّر التطبيق: API تذكير الموعد

> **كل الأمثلة في هذا الملف مأخوذة من استجابات حقيقية** للسيرفر، لا مكتوبة يدوياً.
> **آخر تحديث:** 2026-09-11

---

## فهرس

- [١. الصورة الكاملة في دقيقة](#١-الصورة-الكاملة-في-دقيقة)
- [٢. المفاهيم الخمسة (اقرأها أولاً)](#٢-المفاهيم-الخمسة-اقرأها-أولاً)
- [٣. الترتيب الصحيح للنداءات](#٣-الترتيب-الصحيح-للنداءات)
- [٤. مرجع الـAPI — نداءً نداءً](#٤-مرجع-الـapi--نداءً-نداءً)
  - [4.1 جلب خيارات التذكير](#41-جلب-خيارات-التذكير)
  - [4.2 حجز موعد مع تذكير](#42-حجز-موعد-مع-تذكير)
  - [4.3 ضبط أو تغيير التذكير](#43-ضبط-أو-تغيير-التذكير)
  - [4.4 قراءة التذكير الحالي](#44-قراءة-التذكير-الحالي)
  - [4.5 إيقاف التذكير](#45-إيقاف-التذكير)
  - [4.6 قراءة إعدادات القنوات](#46-قراءة-إعدادات-القنوات)
  - [4.7 تغيير قناة](#47-تغيير-قناة)
- [٥. كائن `reminder` — شرح كل حقل](#٥-كائن-reminder--شرح-كل-حقل)
- [٦. كائن `channels` — أهم جزء](#٦-كائن-channels--أهم-جزء)
- [٧. اللغة](#٧-اللغة)
- [٨. كل الأخطاء المحتملة](#٨-كل-الأخطاء-المحتملة)
- [٩. أخطاء شائعة — لا تقع فيها](#٩-أخطاء-شائعة--لا-تقع-فيها)
- [١٠. سيناريوهات كاملة](#١٠-سيناريوهات-كاملة)
- [١١. قائمة تحقق نهائية](#١١-قائمة-تحقق-نهائية)

---

## ١. الصورة الكاملة في دقيقة

### المسارات السبعة

| # | Method | Path | متى تناديه |
|---|--------|------|-----------|
| 1 | `GET` | `/api/appointments/reminders/options` | عند فتح شاشة فيها تذكير — لملء القائمة والنصوص |
| 2 | `POST` | `/api/bookings` | الحجز — مع حقل `reminder_offset_hours` الاختياري |
| 3 | `POST` | `/api/appointments/reminders` | ضبط أو **تغيير** التذكير |
| 4 | `GET` | `/api/appointments/{id}/reminders` | قراءة التذكير الحالي |
| 5 | `DELETE` | `/api/appointments/{id}/reminders` | إيقاف التذكير |
| 6 | `GET` | `/api/settings` | شاشة الإعدادات — القنوات الثلاث |
| 7 | `PATCH` | `/api/settings/{key}` | تفعيل/إطفاء قناة |

### المصادقة

**كل المسارات السبعة** تحتاج:

```http
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

وتحتاج **حساباً مفعّلاً** (`verified.customer`). بدونه ترجع `403`.

### حدود المعدل

| المسار | الحد |
|--------|------|
| `GET options` · `GET reminders` | 60 طلب/دقيقة |
| `POST reminders` · `DELETE reminders` | 30 طلب/دقيقة |

تجاوز الحد ⟵ `429 Too Many Requests`.

---

## ٢. المفاهيم الخمسة (اقرأها أولاً)

هذه الخمسة تختصر عليك ٩٠٪ من الأسئلة.

### ① أرسل عدد الساعات، لا التاريخ

```jsonc
✅ { "offset_hours": 2 }                          // الصحيح
❌ { "remind_at": "2026-09-09 08:00:00" }         // مدعوم لكنه خطر
```

**لماذا؟** منطقة السيرفر الزمنية (`Asia/Baghdad`) تختلف عن منطقة الصالون (برلين) ومنطقة جهاز المستخدم. إن أرسلت تاريخاً مطلقاً بلا offset صريح، يفسّره السيرفر بمنطقته — وقد يصل التذكير بفارق ساعات.

مع `offset_hours` لا توجد مشكلة أصلاً: السيرفر يحسب `remind_at = start_time − N` بمنطقة واحدة. **النتيجة دائماً «ساعتان قبل الموعد» مهما كانت منطقة الجهاز.**

### ② تذكير واحد فقط لكل حجز

لا يمكن أن يحمل الحجز تذكيرين. النداء الثالث **يستبدل** الثاني تلقائياً.

### ③ `POST` عملية استبدال (idempotent) — لا تحذف قبلها

```jsonc
❌ DELETE ثم POST      // خطوتان بلا داعٍ، وقد يفشل الثاني فيبقى بلا تذكير
✅ POST مباشرة         // يستبدل القديم في عملية واحدة
```

استخدم `DELETE` **فقط** حين يطفئ المستخدم التوغل.

### ④ القناة تتبع إعدادات الإشعارات حرفياً

ثلاث قنوات، كل منها مفتاح مستقل:

| القناة | المفتاح | الافتراضي |
|--------|---------|-----------|
| إشعار التطبيق | `reminder_push_enabled` | `true` |
| البريد الإلكتروني | `reminder_email_enabled` | `false` |
| الرسائل النصية | `reminder_sms_enabled` | `false` |

> **فعّل SMS وحدها ⟵ تصله رسالة نصية فقط.** بلا إشعار وبلا إيميل. هذه هي القاعدة، بلا استثناء.

**والإعدادات تُقرأ لحظة إرسال التذكير لا لحظة ضبطه** — فمن يغيّر قناته بعد الحجز، يسري التغيير على التذكير المضبوط سلفاً. لا تحتاج إعادة ضبط التذكير عند تغيير قناة.

### ⑤ التذكير قد يُحفظ ولا يصل شيء

إن كانت كل القنوات مطفأة، يُحفظ التذكير بنجاح لكن لن يصل المستخدم شيء. **مسؤوليتك أن تحذّره** — كل استجابة تحمل `has_active_channel` لهذا الغرض بالضبط. (التفصيل في [القسم ٦](#٦-كائن-channels--أهم-جزء).)

---

## ٣. الترتيب الصحيح للنداءات

### أ. شاشة الحجز (التوغل قبل زر «احجز الآن»)

```
┌─ 1. فتح الشاشة ──────────────────────────────────────────────┐
│   GET /api/appointments/reminders/options                     │
│                                                                │
│   ← data.options            → املأ القائمة المنسدلة          │
│   ← data.default_offset_hours → القيمة المحددة مبدئياً        │
│   ← data.texts              → عناوين الشاشة                   │
│   ← data.channels           → إن كانت كلها effective=false    │
│                                 اعرض تحذيراً                   │
└────────────────────────────────────────────────────────────────┘
                              ↓
┌─ 2. المستخدم يضغط «احجز الآن» ───────────────────────────────┐
│   POST /api/bookings                                           │
│   { ...بيانات الحجز, "reminder_offset_hours": 2 }              │
│                                                                │
│   ← 201 + data.reminder != null  ✅ تم كل شيء                 │
│   ← 201 + data.reminder == null  ⚠️ الحجز نجح والتذكير لا      │
│                                     → أعد المحاولة بالمسار 3   │
│   ← 409                          ❌ الوقت حُجز، حدّث الأوقات   │
└────────────────────────────────────────────────────────────────┘
```

> **إن كان التوغل مطفأ** ⟵ **احذف حقل `reminder_offset_hours` من الطلب** (أو أرسله `null`).

### ب. شاشة تفاصيل الحجز (التعديل لاحقاً)

```
┌─ 1. فتح الشاشة ──────────────────────────────────────────────┐
│   GET /api/appointments/{id}                                   │
│   ← data.reminder == null  → التوغل OFF                       │
│   ← data.reminder != null  → التوغل ON                        │
│                              القائمة على reminder.offset_hours │
│                                                                │
│   (وللخيارات والنصوص: GET .../reminders/options)              │
└────────────────────────────────────────────────────────────────┘
        ↓                              ↓
┌─ يغيّر المدة ────────┐      ┌─ يطفئ التوغل ──────────────────┐
│ POST /appointments/  │      │ DELETE /appointments/{id}/      │
│      reminders       │      │        reminders                │
│ (لا تحذف أولاً)      │      │ ← 200 نجح · 404 لا يوجد أصلاً  │
└──────────────────────┘      └─────────────────────────────────┘
```

### ج. شاشة الإعدادات

```
GET   /api/settings                          → ارسم التوغلات ديناميكياً
PATCH /api/settings/reminder_sms_enabled     { "value": true }
```

> **لا تكتب التوغلات يدوياً.** الشاشة data-driven — أي خيار جديد يضيفه الباك إند يظهر تلقائياً.

---

## ٤. مرجع الـAPI — نداءً نداءً

### 4.1 جلب خيارات التذكير

```http
GET /api/appointments/reminders/options
Authorization: Bearer {token}
```

**بارامترات الاستعلام:**

| Parameter | مطلوب | الوصف |
|-----------|-------|-------|
| `lang` | ❌ | `ar` \| `en` \| `de` — يتجاوز لغة الحساب |

**✅ 200 — استجابة حقيقية (`?lang=ar`):**

```json
{
  "success": true,
  "data": {
    "options": [
      { "offset_hours": 1,  "label": "قبل ساعة" },
      { "offset_hours": 2,  "label": "قبل ساعتين" },
      { "offset_hours": 3,  "label": "قبل ثلاث ساعات" },
      { "offset_hours": 4,  "label": "قبل أربع ساعات" },
      { "offset_hours": 5,  "label": "قبل خمس ساعات" },
      { "offset_hours": 6,  "label": "قبل ست ساعات" },
      { "offset_hours": 24, "label": "قبل 24 ساعة" }
    ],
    "default_offset_hours": 1,
    "texts": {
      "title": "تذكير بالموعد",
      "subtitle": "احصل على تذكير قبل موعدك.",
      "question": "متى تريد أن يصلك التذكير؟"
    },
    "channels": {
      "push":  { "enabled": true,  "deliverable": false, "effective": false },
      "email": { "enabled": false, "deliverable": true,  "effective": false },
      "sms":   { "enabled": true,  "deliverable": false, "effective": false }
    }
  }
}
```

**✅ 200 — نفس النداء بـ`?lang=de`:**

```json
{
  "success": true,
  "data": {
    "options": [
      { "offset_hours": 1,  "label": "1 Stunde vorher" },
      { "offset_hours": 2,  "label": "2 Stunden vorher" },
      { "offset_hours": 24, "label": "24 Stunden vorher" }
    ],
    "default_offset_hours": 1,
    "texts": {
      "title": "Terminerinnerung",
      "subtitle": "Erhalte eine Erinnerung vor deinem Termin.",
      "question": "Wann möchtest du erinnert werden?"
    }
  }
}
```

**كيف تستخدمه:**

| الحقل | استخدمه في |
|-------|-----------|
| `data.options[].offset_hours` | **القيمة** التي سترسلها لاحقاً |
| `data.options[].label` | **النص** الذي تعرضه في القائمة |
| `data.default_offset_hours` | الخيار المحدد مبدئياً |
| `data.texts.title` | عنوان بطاقة التذكير |
| `data.texts.subtitle` | النص تحت العنوان |
| `data.texts.question` | العنوان فوق القائمة المنسدلة |
| `data.channels` | لتحذير المستخدم قبل الضبط |

> ⚠️ **لا تثبّت الخيارات ولا النصوص داخل التطبيق.** لو أراد الصالون تغيير صياغة أو إضافة خيار «قبل 12 ساعة»، يصل التغيير بلا تحديث تطبيق — بشرط أن تقرأه من هنا.

---

### 4.2 حجز موعد مع تذكير

```http
POST /api/bookings
Authorization: Bearer {token}
```

**الحقل الجديد فقط** (بقية حقول الحجز في `API.md`):

| Field | Type | مطلوب | الوصف |
|-------|------|-------|-------|
| `reminder_offset_hours` | integer | ❌ | من `options`. احذفه للحجز بلا تذكير |

**الطلب:**

```json
{
  "date": "2026-09-09",
  "payment_method": "cash",
  "reminder_offset_hours": 2,
  "services": [
    { "service_id": 1, "provider_id": 3, "start_time": "10:00" }
  ]
}
```

**✅ 201 — استجابة حقيقية (مختصرة لحقول التذكير):**

```json
{
  "success": true,
  "message": "Booking created successfully",
  "data": {
    "id": 9,
    "number": "APT-20260907-01D07E",
    "appointment_date": "2026-09-09",
    "start_time": "10:00",
    "end_time": "11:00",
    "total_amount": 100,
    "status": "PENDING",
    "provider": { "id": 3, "full_name": "Available Provider" },
    "services_details": [ "..." ],
    "reminder": {
      "id": 1,
      "appointment_id": 9,
      "remind_at": "2026-09-09T08:00:00+03:00",
      "offset_hours": 2,
      "status": "pending",
      "is_active": true,
      "sent_at": null,
      "cancelled_at": null,
      "delivered_channels": null,
      "channels": {
        "push":  { "enabled": true,  "deliverable": false, "effective": false },
        "email": { "enabled": false, "deliverable": true,  "effective": false },
        "sms":   { "enabled": true,  "deliverable": false, "effective": false }
      },
      "active_channels": [],
      "has_active_channel": false
    },
    "can_cancel": true
  }
}
```

#### 🔴 الحالة التي يجب أن تعالجها: `201` مع `reminder: null`

**معناها: الحجز نجح ✅ والتذكير لم يُنشأ ❌.**

**هذا سلوك مقصود** — فشل التذكير **لا يُلغي الحجز أبداً**، لأن الموعد مورد متنازع عليه لا يمكن استرجاعه بعد ضياعه، بينما التذكير على بُعد ضغطة.

**متى تحدث؟**

1. **مدة التذكير مضت أصلاً** — حجز الساعة 09:00 لموعد 10:00 مع تذكير «قبل 24 ساعة». لا توجد لحظة متبقية للتذكير.
2. خلل تقني في جدولة المهمة.

**ما يجب أن يفعله التطبيق:**

```
إن (201) و (طلب المستخدم تذكيراً) و (data.reminder == null):
    → أظهر الحجز كناجح (لأنه ناجح فعلاً)
    → نادِ POST /api/appointments/reminders لإعادة المحاولة
    → إن رجعت 422 بـ offset_hours.too_late:
         أخبر المستخدم أن المدة مضت واعرض عليه مدة أقصر
```

---

### 4.3 ضبط أو تغيير التذكير

```http
POST /api/appointments/reminders
Authorization: Bearer {token}
```

| Field | Type | مطلوب | الوصف |
|-------|------|-------|-------|
| `appointment_id` | integer | ✅ | يجب أن يكون حجز المستخدم نفسه |
| `offset_hours` | integer | ⭐ | **استخدم هذا** — من `options` |
| `remind_at` | datetime | ⚠️ | قديم — للتوافق فقط |

> ⚠️ **أرسل أحدهما لا كليهما** — إرسال الاثنين يُرفض بـ`422`.

**الطلب:**

```json
{ "appointment_id": 9, "offset_hours": 2 }
```

**✅ 201 — استجابة حقيقية:**

```json
{
  "success": true,
  "message": "تم إنشاء التذكير بنجاح.",
  "data": {
    "id": 1,
    "appointment_id": 9,
    "remind_at": "2026-09-09T08:00:00+03:00",
    "offset_hours": 2,
    "status": "pending",
    "is_active": true,
    "sent_at": null,
    "cancelled_at": null,
    "delivered_channels": null,
    "channels": {
      "push":  { "enabled": true,  "deliverable": false, "effective": false },
      "email": { "enabled": false, "deliverable": true,  "effective": false },
      "sms":   { "enabled": true,  "deliverable": false, "effective": false }
    },
    "active_channels": [],
    "has_active_channel": false
  }
}
```

> 💡 **لتغيير المدة:** نادِ نفس المسار بالمدة الجديدة. **لا تحذف أولاً** — الاستبدال تلقائي.

---

### 4.4 قراءة التذكير الحالي

```http
GET /api/appointments/{id}/reminders
Authorization: Bearer {token}
```

**✅ 200 — يوجد تذكير:**

```json
{
  "success": true,
  "data": {
    "id": 1,
    "appointment_id": 9,
    "remind_at": "2026-09-09T08:00:00+03:00",
    "offset_hours": 2,
    "status": "pending",
    "is_active": true,
    "sent_at": null,
    "cancelled_at": null,
    "delivered_channels": null,
    "channels": { "...": "..." },
    "active_channels": [],
    "has_active_channel": false
  },
  "channels": { "...": "..." }
}
```

**✅ 200 — لا يوجد تذكير (استجابة حقيقية):**

```json
{
  "success": true,
  "data": null,
  "channels": {
    "push":  { "enabled": true,  "deliverable": false, "effective": false },
    "email": { "enabled": false, "deliverable": true,  "effective": false },
    "sms":   { "enabled": true,  "deliverable": false, "effective": false }
  }
}
```

> ⚠️ **`data: null` ليس خطأ.** هو الحالة الطبيعية «التوغل مطفأ». **لا تعالجها كخطأ ولا تتوقع 404 هنا.**
>
> 💡 `channels` موجود **في الحالتين** — فتستطيع تحذير المستخدم قبل ضبط التذكير كما بعده.

**بديل يوفّر نداءً:** `GET /api/appointments/{id}` و`GET /api/bookings/{id}` يحملان كائن `reminder` بنفس الشكل تماماً. لا تحتاج هذا المسار إلا إن أردت تحديث التذكير وحده.

---

### 4.5 إيقاف التذكير

```http
DELETE /api/appointments/{id}/reminders
Authorization: Bearer {token}
```

**✅ 200 — استجابة حقيقية:**

```json
{ "success": true, "message": "تم إيقاف التذكير.", "data": null }
```

**❌ 404 — لا يوجد تذكير نشط (استجابة حقيقية):**

```json
{
  "success": false,
  "message": "لا يوجد تذكير نشط لهذا الموعد.",
  "error_type": "not_found"
}
```

> 💡 **404 هنا غير ضار.** يعني أن التوغل كان مطفأً أصلاً — عامله كنجاح في الواجهة (النتيجة واحدة: لا تذكير).
>
> 💡 **لا تحتاج `DELETE` عند إلغاء الحجز** — إلغاء الحجز يلغي تذكيره تلقائياً في الباك إند.

---

### 4.6 قراءة إعدادات القنوات

```http
GET /api/settings
Authorization: Bearer {token}
```

**✅ 200 — استجابة حقيقية:**

```json
{
  "success": true,
  "data": [
    {
      "key": "reminder_push_enabled",
      "label": "Push appointment reminders",
      "description": "Receive your appointment reminders as an in-app notification.",
      "type": "boolean",
      "group": "notifications",
      "value": true,
      "is_default": true
    },
    {
      "key": "reminder_email_enabled",
      "label": "Email appointment reminders",
      "description": "Also receive your appointment reminders by email.",
      "type": "boolean",
      "group": "notifications",
      "value": false,
      "is_default": true
    },
    {
      "key": "reminder_sms_enabled",
      "label": "SMS appointment reminders",
      "description": "Also receive your appointment reminders by SMS.",
      "type": "boolean",
      "group": "notifications",
      "value": false,
      "is_default": true
    }
  ]
}
```

**شرح الحقول:**

| الحقل | المعنى |
|-------|--------|
| `key` | المفتاح الذي ترسله في `PATCH` |
| `label` | نص التوغل — **اعرضه ولا تكتب نصاً خاصاً بك** |
| `description` | نص توضيحي تحت التوغل |
| `type` | `boolean` حالياً — ارسم توغلاً. عالج `string`/`integer` مستقبلاً بحقل نص/رقم |
| `group` | `notifications` — للتجميع في أقسام |
| `value` | **القيمة السارية الآن** — حالة التوغل |
| `is_default` | `true` = لم يغيّره المستخدم قط |

> ⚠️ **ارسم الشاشة من هذه القائمة ديناميكياً.** لا تفترض ثلاثة عناصر ولا تثبّت مفاتيحها — أي خيار جديد يجب أن يظهر بلا تحديث تطبيق.

---

### 4.7 تغيير قناة

```http
PATCH /api/settings/{key}
Authorization: Bearer {token}
```

`{key}` واحد من: `reminder_push_enabled` · `reminder_email_enabled` · `reminder_sms_enabled`

**الطلب:**

```json
{ "value": true }
```

**✅ 200 — استجابة حقيقية:**

```json
{
  "success": true,
  "message": "Setting updated successfully.",
  "data": { "key": "reminder_sms_enabled", "value": true }
}
```

**❌ 404** — مفتاح غير موجود · **❌ 422** — قيمة غير صالحة.

> 💡 **التغيير يسري فوراً** على كل التذكيرات المجدولة التي لم تُرسل بعد. لا تُعد ضبط أي تذكير.

---

## ٥. كائن `reminder` — شرح كل حقل

هذا الكائن **واحد في كل المسارات**: رد `POST /bookings`، ورد `POST /reminders`، ورد `GET /reminders`، وداخل `GET /appointments/{id}`.

| الحقل | النوع | الوصف | استخدامه في التطبيق |
|-------|------|-------|---------------------|
| `id` | int | معرف التذكير | نادراً ما تحتاجه |
| `appointment_id` | int | معرف الحجز | للربط |
| `remind_at` | string (ISO8601) | لحظة الإرسال مع offset المنطقة | اعرضه إن كان `offset_hours = null` |
| **`offset_hours`** | int \| null | **المدة بالساعات** | ⭐ **الحقل الذي يملأ القائمة المنسدلة** |
| `status` | string | `pending` \| `sent` \| `cancelled` | للعرض إن أردت |
| **`is_active`** | bool | هل التذكير حيّ؟ | ⭐ **حالة التوغل** |
| `sent_at` | string \| null | وقت الإرسال الفعلي | `null` = لم يُرسل بعد |
| `cancelled_at` | string \| null | وقت الإلغاء | — |
| `delivered_channels` | array \| null | القنوات التي وصل عبرها فعلاً | انظر الجدول أدناه |
| `channels` | object | حالة القنوات الثلاث | [القسم ٦](#٦-كائن-channels--أهم-جزء) |
| `active_channels` | array | أسماء القنوات الفعّالة | مثال: `["push"]` |
| **`has_active_channel`** | bool | هل ستصل عبر قناة ما؟ | ⭐ **`false` ⟵ اعرض تحذيراً** |

**`delivered_channels` — ثلاث حالات مختلفة تماماً:**

| القيمة | المعنى |
|--------|--------|
| `null` | لم يحن وقته بعد |
| `["sms"]` | وصل عبر الرسائل النصية |
| `["push","email","sms"]` | وصل عبر الثلاث |
| **`[]`** | **حان وقته ونُفِّذ وكل القنوات كانت مطفأة ⟵ لم يصله شيء** |

**`offset_hours = null` — متى؟**

فقط لتذكير قديم أُنشئ بـ`remind_at` مطلق لا يقع على ساعة كاملة (مثلاً 90 دقيقة قبل الموعد). عندها:
- اعرض `remind_at` منسّقاً
- اترك القائمة المنسدلة بلا اختيار محدد

لن تحدث لأي تذكير تنشئه بـ`offset_hours`.

---

## ٦. كائن `channels` — أهم جزء

```json
"channels": {
  "push":  { "enabled": true,  "deliverable": false, "effective": false },
  "email": { "enabled": false, "deliverable": true,  "effective": false },
  "sms":   { "enabled": true,  "deliverable": false, "effective": false }
}
```

| الحقل | السؤال الذي يجيبه |
|-------|-------------------|
| `enabled` | هل **فعّل المستخدم** هذه القناة في الإعدادات؟ |
| `deliverable` | هل **نستطيع الوصول إليه** عبرها فعلاً؟ |
| `effective` | `enabled && deliverable` ⟵ **هل سيصله التذكير فعلاً؟** |

**متى تكون `deliverable = false`؟**

| القناة | الشرط |
|--------|-------|
| `push` | لا يوجد جهاز مسجّل ⟵ نادِ `POST /api/register-device` |
| `email` | لا يوجد بريد على الحساب |
| `sms` | لا يوجد رقم جوال على الحساب |

### التحذيرات المطلوبة منك

اقرأ المثال أعلاه: المستخدم **فعّل** الإشعارات و**فعّل** SMS — لكن `effective` كلها `false`:
- `push`: مفعّلة لكن لا جهاز مسجّل
- `sms`: مفعّلة لكن لا رقم جوال
- `email`: البريد موجود لكن القناة مطفأة

**لن يصله شيء.** ولن يعرف ذلك إلا إن أخبرته.

```
① has_active_channel == false
   ⟵ «تم حفظ التذكير، لكن لن يصلك شيء.
       فعّل قناة إشعار من الإعدادات.»

② channels.sms.enabled && !channels.sms.deliverable
   ⟵ «الرسائل النصية مفعّلة لكن لا يوجد رقم جوال على حسابك.
       أضف رقمك ليصلك التذكير.»

③ channels.email.enabled && !channels.email.deliverable
   ⟵ «البريد الإلكتروني مفعّل لكن لا يوجد بريد على حسابك.»

④ channels.push.enabled && !channels.push.deliverable
   ⟵ تحقق من أذونات الإشعارات ونادِ POST /api/register-device
```

---

## ٧. اللغة

### أولوية اختيار اللغة

```
1. ?lang=ar          في الرابط           ← الأعلى أولوية
2. Accept-Language   في الترويسة
3. لغة حساب المستخدم (users.locale)
4. لغة الصالون الافتراضية
```

**اللغات المدعومة:** `ar` · `en` · `de`

### التوصية

**أرسل `Accept-Language` في كل نداء** بلغة واجهة التطبيق الحالية:

```http
Accept-Language: de
```

هكذا تتطابق نصوص التذكير مع بقية نصوص التطبيق، حتى لو لم يحدّث المستخدم لغة حسابه.

> ⚠️ **انتبه:** لغة **نص التذكير نفسه** (الذي يصل بالإشعار/الإيميل/SMS) تُؤخذ من **لغة حساب المستخدم لحظة ضبط التذكير** — لا من هذه الترويسة. السبب: التذكير يُرسل لاحقاً في مهمة خلفية بلا طلب HTTP تقرأ منه ترويسة.
>
> **لذا:** إن غيّر المستخدم لغة التطبيق، حدّث لغة حسابه أيضاً عبر `POST /api/profile` ليصله نص التذكير بلغته الجديدة.

---

## ٨. كل الأخطاء المحتملة

### 422 — خطأ تحقق

الشكل موحّد دائماً:

```json
{
  "success": false,
  "message": "Invalid data provided.",
  "errors": { "<field>": ["<الرسالة>"] },
  "error_type": "validation_error"
}
```

**كل الحالات (استجابات حقيقية):**

| الحالة | الحقل | الرسالة | ماذا تفعل |
|--------|-------|---------|-----------|
| مدة خارج القائمة | `offset_hours` | `That reminder lead time is not one of the available options.` | خذ القيم من `options` فقط |
| إرسال الحقلين معاً | `offset_hours` | `Send either offset_hours or remind_at, not both.` | أرسل `offset_hours` فقط |
| لم تُرسل أياً منهما | `offset_hours` | `Choose when you want to be reminded.` | أرسل `offset_hours` |
| المدة مضت | `offset_hours` | `That lead time has already passed for this appointment. Pick a shorter one.` | اعرض مدة أقصر |
| حجز غير موجود أو ليس للمستخدم | `appointment_id` | `Appointment not found or you do not have access.` | تحقق من الـid والـtoken |
| الحجز ملغى | `appointment_id` | `You cannot create a reminder for a cancelled appointment.` | أخفِ التوغل للحجوزات الملغاة |
| الحجز بدأ أو انتهى | `appointment_id` | `You cannot create a reminder for a past or started appointment.` | أخفِ التوغل للحجوزات الماضية |

> 🔒 **ملاحظة أمنية:** «حجز غير موجود» و«حجز شخص آخر» يرجعان **نفس الرسالة عمداً** — لمنع استكشاف أرقام الحجوزات. لا تحاول التمييز بينهما.

### باقي الأكواد

| الكود | متى | الشكل | ماذا تفعل |
|------|-----|-------|-----------|
| `401` | token مفقود/منتهٍ | — | أعد تسجيل الدخول |
| `403` | الحساب غير مفعّل | — | وجّهه لتفعيل الحساب |
| `404` | لا تذكير نشط (في `DELETE`) | `error_type: "not_found"` | عامله كنجاح |
| `404` | الحجز غير موجود (في `GET`) | `error_type: "not_found"` | أخفِ التذكير |
| `429` | تجاوز حد المعدل | — | أوقف التكرار، أعد بعد دقيقة |
| `500` | خلل سيرفر | `error_type: "server_error"` | رسالة عامة + إعادة محاولة |

**مثال 404 حقيقي:**

```json
{
  "success": false,
  "message": "لا يوجد تذكير نشط لهذا الموعد.",
  "error_type": "not_found"
}
```

---

## ٩. أخطاء شائعة — لا تقع فيها

| ❌ الخطأ | ✅ الصحيح | لماذا |
|---------|----------|-------|
| حساب `remind_at` في التطبيق | إرسال `offset_hours` | فرق المنطقة الزمنية يزيح التذكير ساعات |
| تثبيت الخيارات السبعة في الكود | قراءتها من `options` | تغيير الخيارات يحتاج تحديث تطبيق |
| تثبيت نصوص الشاشة | قراءتها من `data.texts` | نفس السبب |
| `DELETE` ثم `POST` للتغيير | `POST` مباشرة | خطوتان، وفشل الثانية يترك بلا تذكير |
| اعتبار `data: null` خطأً | حالة «التوغل مطفأ» | تعطيل الشاشة بلا سبب |
| تجاهل `has_active_channel` | عرض تحذير | المستخدم يظن أن التذكير سيصله |
| رسم توغلات الإعدادات يدوياً | من `GET /api/settings` | خيار جديد لن يظهر |
| إعادة ضبط التذكير بعد تغيير قناة | لا شيء | الإعداد يُقرأ لحظة الإرسال تلقائياً |
| `DELETE` بعد إلغاء الحجز | لا شيء | الباك إند يلغيه تلقائياً |
| اعتبار `201` مع `reminder: null` نجاحاً كاملاً | إعادة محاولة التذكير | المستخدم يظن أنه ضبطه |
| إرسال `offset_hours` و`remind_at` معاً | أحدهما | يُرفض بـ422 |

---

## ١٠. سيناريوهات كاملة

### السيناريو ١ — حجز مع تذكير (المسار السعيد)

```http
① GET /api/appointments/reminders/options?lang=ar
   ← options[7] + default_offset_hours=1 + texts + channels

② المستخدم يفعّل التوغل ويختار «قبل ساعتين»

③ POST /api/bookings
   { "date": "2026-09-09", "payment_method": "cash",
     "reminder_offset_hours": 2,
     "services": [{"service_id":1,"provider_id":3,"start_time":"10:00"}] }

   ← 201 · data.reminder.offset_hours = 2
            data.reminder.has_active_channel = true

④ اعرض «تم الحجز ✅ وسيصلك تذكير قبل ساعتين»
```

### السيناريو ٢ — التذكير محفوظ ولن يصل

```http
③ POST /api/bookings { ..., "reminder_offset_hours": 2 }
   ← 201 · data.reminder.has_active_channel = false
            data.reminder.channels.sms.enabled     = true
            data.reminder.channels.sms.deliverable = false

④ اعرض: «تم الحجز ✅»
        + تحذير: «الرسائل النصية مفعّلة لكن لا يوجد رقم جوال
                  على حسابك — أضف رقمك ليصلك التذكير.»
        + زر «إضافة رقم» → شاشة الملف الشخصي
```

### السيناريو ٣ — تغيير المدة لاحقاً

```http
① GET /api/appointments/9
   ← data.reminder.offset_hours = 2 · is_active = true
   → التوغل ON والقائمة على «قبل ساعتين»

② المستخدم يختار «قبل 6 ساعات»

③ POST /api/appointments/reminders
   { "appointment_id": 9, "offset_hours": 6 }
   ← 201 · data.offset_hours = 6

   ⚠️ لم نستدعِ DELETE — الاستبدال تلقائي
```

### السيناريو ٤ — إيقاف التذكير

```http
① المستخدم يطفئ التوغل

② DELETE /api/appointments/9/reminders
   ← 200 { "message": "تم إيقاف التذكير.", "data": null }
   أو
   ← 404 { "error_type": "not_found" }   ← كان مطفأً أصلاً، عامله كنجاح

③ في الحالتين: التوغل OFF
```

### السيناريو ٥ — المدة مضت

```http
الآن 09:00 · الموعد اليوم 10:00 · المستخدم يختار «قبل 24 ساعة»

POST /api/appointments/reminders { "appointment_id": 9, "offset_hours": 24 }
← 422 errors.offset_hours[0] =
   "That lead time has already passed for this appointment. Pick a shorter one."

→ اعرض الرسالة واقترح مدة أقصر
→ الأفضل: عطّل في القائمة كل خيار تتجاوز مدته الوقت المتبقي حتى الموعد
```

### السيناريو ٦ — تفعيل SMS من الإعدادات

```http
① GET /api/settings
   ← reminder_sms_enabled.value = false

② المستخدم يفعّل التوغل

③ PATCH /api/settings/reminder_sms_enabled { "value": true }
   ← 200 { "data": { "key": "reminder_sms_enabled", "value": true } }

④ لا تفعل شيئاً آخر — كل التذكيرات المجدولة ستحترم الإعداد الجديد تلقائياً
```

---

## ١١. قائمة تحقق نهائية

قبل تسليم الشاشة، تأكد أن تطبيقك:

- [ ] يقرأ الخيارات من `GET .../reminders/options` ولا يثبّتها
- [ ] يقرأ نصوص الشاشة من `data.texts` ولا يثبّتها
- [ ] يرسل `offset_hours` ولا يحسب `remind_at`
- [ ] يرسل `Accept-Language` بلغة واجهة التطبيق
- [ ] يعرض تحذيراً عند `has_active_channel = false`
- [ ] يعرض تحذيراً عند `enabled && !deliverable` لكل قناة
- [ ] يعالج `201` مع `reminder: null` بإعادة محاولة عبر `POST /reminders`
- [ ] يعامل `data: null` في `GET /reminders` كحالة «توغل مطفأ» لا كخطأ
- [ ] يعامل `404` في `DELETE` كنجاح
- [ ] ينادي `POST` مباشرة للتغيير بلا `DELETE` قبله
- [ ] لا ينادي `DELETE` بعد إلغاء الحجز
- [ ] يرسم شاشة الإعدادات ديناميكياً من `GET /api/settings`
- [ ] يخفي التوغل للحجوزات الملغاة والماضية
- [ ] يعالج `429` بإيقاف التكرار لا بإعادة المحاولة فوراً

---

## مراجع

| الملف | المحتوى |
|------|---------|
| [API.md](../API.md) | مرجع الـAPI الكامل لكل النظام |
| [APPOINTMENT_REMINDER_CHANNELS_2026-09-11.md](APPOINTMENT_REMINDER_CHANNELS_2026-09-11.md) | توثيق التنفيذ الداخلي (للباك إند) |
| [app-settings-api-frontend-ar.md](app-settings-api-frontend-ar.md) | دليل شاشة الإعدادات العامة |

**عند أي غموض:** المصدر الأوثق هو الاختبارات في `tests/Feature/Reminders/` — كل سلوك موصوف هنا محروس باختبار هناك.

</div>
