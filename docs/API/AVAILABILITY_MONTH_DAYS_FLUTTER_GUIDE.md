# دليل مطوّر التطبيق (Flutter) — أيام التوفر الشهرية

> **لمن هذا الملف:** مطوّر تطبيق الموبايل (Flutter) الذي سيبني شاشة «اختر اليوم» في رحلة الحجز.
> **ماذا ستجد هنا:** شرح الـ API الجديدين، شكل كل رد بالتفصيل، كل حالات الخطأ، نماذج Dart جاهزة، وأمثلة لربطها بالتقويم ثم بالحجز.
> **كل أمثلة JSON هنا مأخوذة من ردود حقيقية للسيرفر** (مختصرة فقط في عدد الأيام).

---

## الفهرس

1. [الفكرة باختصار](#1-الفكرة-باختصار)
2. [معلومات عامة لكل الطلبات](#2-معلومات-عامة-لكل-الطلبات)
3. [API 1 — أيام توفر مزوّد محدد](#3-api-1--أيام-توفر-مزوّد-محدد)
4. [API 2 — أيام توفر خدمة + من المتاح كل يوم](#4-api-2--أيام-توفر-خدمة--من-المتاح-كل-يوم)
5. [قيم `reason_code` وكيف تعرضها](#5-قيم-reason_code-وكيف-تعرضها)
6. [التنقل بين الأشهر](#6-التنقل-بين-الأشهر)
7. [الأخطاء — كل الحالات](#7-الأخطاء--كل-الحالات)
8. [نماذج Dart (Models)](#8-نماذج-dart-models)
9. [طبقة الـ API في Dart](#9-طبقة-الـ-api-في-dart)
10. [بناء شاشة التقويم](#10-بناء-شاشة-التقويم)
11. [من اليوم إلى الحجز — الرحلة الكاملة](#11-من-اليوم-إلى-الحجز--الرحلة-الكاملة)
12. [أخطاء شائعة يجب تجنبها](#12-أخطاء-شائعة-يجب-تجنبها)
13. [Checklist قبل التسليم](#13-checklist-قبل-التسليم)

---

## 1. الفكرة باختصار

بدل أن يجرّب العميل يوماً يوماً ليكتشف أين توجد مواعيد، نعرض له **تقويم الشهر** وفيه الأيام المتوفرة فقط مفعّلة.

| | الطلب | تستخدمه عندما |
|---|---|---|
| **API 1** | `GET /api/availability/days` | العميل **اختار خدمة ومزوّداً**، وتريد الأيام التي فيها وقت فارغ عند هذا المزوّد |
| **API 2** | `GET /api/availability/days/providers` | العميل **اختار خدمة فقط**، وتريد الأيام المتوفرة **ومن** المزوّدون المتاحون في كل يوم |

**«يوم متوفر» يعني:** يوجد **وقت شاغر واحد على الأقل** يقبله الحجز فعلاً. السيرفر يفحص قبل أن يقول «متوفر»:

- يوم عمل المزوّد (جدول دوامه الأسبوعي)
- إجازات المزوّد (يوم كامل، أو ساعية، أو ممتدة لعدة أيام)
- إغلاق الصالون في ذلك اليوم من الأسبوع
- الحجوزات الموجودة (هل بقي وقت يتسع للخدمة؟)
- الحد الأدنى للحجز المسبق (لا يُعرض وقت قريب جداً من الآن)
- أقصى مدة حجز مسبق (`last_bookable_date`)
- أن المزوّد نشط ويقدّم هذه الخدمة، وأن الخدمة نشطة

> ✅ **ضمانة:** أي يوم يأتي `is_available: true` يمكن حجزه فعلاً بـ `POST /api/bookings`. هذا مُختبَر آلياً على السيرفر.

---

## 2. معلومات عامة لكل الطلبات

| البند | القيمة |
|---|---|
| Base URL | `{{base_url}}/api` |
| Method | `GET` |
| Authentication | ❌ **غير مطلوب** — يعمل للزائر قبل تسجيل الدخول |
| Rate limit | **30 طلب / دقيقة / IP** لكل endpoint على حدة |
| صيغة الشهر | `YYYY-MM` — مثال: `2026-11` |
| صيغة التاريخ | `YYYY-MM-DD` — مثال: `2026-11-03` |
| صيغة الوقت | `HH:mm` بنظام 24 ساعة — مثال: `09:00`، `14:40` |

### الهيدرز المطلوبة

```
Accept: application/json
Accept-Language: ar        ← أو de أو en
```

> ⚠️ **`Accept: application/json` إلزامي.** بدونه يرد Laravel على أخطاء الـ validation بـ redirect (302) بدل JSON.
>
> **اللغة:** رسائل الخطأ الخاصة بالحجز (مثل «لا يمكن الحجز في هذا الشهر») تأتي بلغة `Accept-Language` (أو `?lang=ar`). أسماء الأيام `day_name` و `month_name` تأتي **دائماً بالإنجليزية**؛ اعرضها أنت مترجمة من التاريخ نفسه (انظر [القسم 12](#12-أخطاء-شائعة-يجب-تجنبها)).

### غلاف الرد (Envelope)

**نجاح:**
```json
{
  "success": true,
  "data": { ... },
  "message": "Availability days retrieved successfully"
}
```

**خطأ منطقي** (مزوّد غير متاح، شهر غير مسموح):
```json
{
  "success": false,
  "message": "..."
}
```

**خطأ validation** (بارامتر ناقص أو بصيغة خاطئة). **انتبه: لا يوجد حقل `success` هنا:**
```json
{
  "message": "...",
  "errors": { "field": ["..."] }
}
```

---

## 3. API 1 — أيام توفر مزوّد محدد

```
GET /api/availability/days
```

### Query Parameters

| Parameter | Type | Required | الوصف |
|---|---|---|---|
| `service_id` | int | ✅ | معرّف الخدمة (يجب أن تكون نشطة) |
| `provider_id` | int | ✅ | معرّف المزوّد |
| `month` | string | ❌ | `YYYY-MM`. **إن لم تُرسله = الشهر الحالي** |

### مثال طلب

```
GET {{base_url}}/api/availability/days?service_id=2&provider_id=3&month=2026-11
Accept: application/json
Accept-Language: ar
```

### رد ناجح — 200

```json
{
  "success": true,
  "data": {
    "service": {
      "id": 2,
      "name": "Beard Trim & Shape",
      "duration_minutes": 20,
      "formatted_duration": "20m"
    },
    "provider": {
      "id": 3,
      "name": "Sarah Johnson",
      "avatar": null,
      "branch": {
        "id": 1,
        "name": "Main Branch - Downtown",
        "address": "123 Main Street, Downtown Dubai",
        "phone": "+971-4-123-4567",
        "coordinates": {
          "latitude": "25.2048000",
          "longitude": "55.2708000"
        }
      }
    },
    "pricing": {
      "original_price": 18,
      "effective_price": 18,
      "has_discount": false,
      "discount_amount": 0,
      "discount_percentage": 0,
      "currency": "EUR",
      "formatted_price": "18.00 EUR"
    },
    "month": "2026-11",
    "month_name": "November 2026",
    "previous_month": "2026-10",
    "next_month": "2026-12",
    "last_bookable_date": "2026-12-23",
    "available_dates": [
      "2026-11-01",
      "2026-11-02",
      "2026-11-03",
      "2026-11-04"
    ],
    "days": [
      {
        "date": "2026-11-01",
        "day_name": "Sun",
        "day_number": 1,
        "is_today": false,
        "is_available": true,
        "reason_code": "available",
        "available_slots_count": 27,
        "first_available_slot": "09:00"
      },
      {
        "date": "2026-11-02",
        "day_name": "Mon",
        "day_number": 2,
        "is_today": false,
        "is_available": true,
        "reason_code": "available",
        "available_slots_count": 27,
        "first_available_slot": "09:00"
      }
    ]
  },
  "message": "Availability days retrieved successfully"
}
```

### شكل الأيام غير المتوفرة داخل `days`

نفس الحقول دائماً، لكن `is_available: false` وعدد الأوقات صفر:

```json
{ "date": "2026-10-20", "day_name": "Tue", "day_number": 20, "is_today": false,
  "is_available": false, "reason_code": "past",
  "available_slots_count": 0, "first_available_slot": null }

{ "date": "2026-11-08", "day_name": "Sun", "day_number": 8, "is_today": false,
  "is_available": false, "reason_code": "not_working_day",
  "available_slots_count": 0, "first_available_slot": null }

{ "date": "2026-11-11", "day_name": "Wed", "day_number": 11, "is_today": false,
  "is_available": false, "reason_code": "on_leave",
  "available_slots_count": 0, "first_available_slot": null }

{ "date": "2026-12-28", "day_name": "Mon", "day_number": 28, "is_today": false,
  "is_available": false, "reason_code": "outside_booking_window",
  "available_slots_count": 0, "first_available_slot": null }
```

### شرح الحقول

#### على مستوى `data`

| الحقل | النوع | Nullable | الوصف |
|---|---|---|---|
| `service.id` | int | ❌ | |
| `service.name` | string | ❌ | |
| `service.duration_minutes` | int | ❌ | مدة الخدمة بالدقائق |
| `service.formatted_duration` | string | ❌ | مثل `"20m"` أو `"1h 30m"` |
| `provider.id` | int | ❌ | |
| `provider.name` | string | ❌ | |
| `provider.avatar` | string | ✅ | رابط الصورة أو `null`. اعرض صورة افتراضية عند `null` |
| `provider.branch` | object | ✅ | فرع المزوّد أو `null` |
| `provider.branch.coordinates.latitude/longitude` | **string** | ✅ | ⚠️ نصّ وليس رقماً. استخدم `double.tryParse` |
| `pricing` | object | ❌ | سعر هذه الخدمة **عند هذا المزوّد**. انظر [جدول السعر](#كائن-السعر-pricing--service_pricing) |
| `month` | string | ❌ | الشهر المعروض `YYYY-MM` |
| `month_name` | string | ❌ | بالإنجليزية دائماً (`"November 2026"`) |
| `previous_month` | string | ✅ | الشهر السابق **المسموح طلبه**، أو `null` = عطّل زر «السابق» |
| `next_month` | string | ✅ | الشهر التالي **المسموح طلبه**، أو `null` = عطّل زر «التالي» |
| `last_bookable_date` | string | ❌ | آخر تاريخ يقبله الحجز |
| `available_dates` | string[] | ❌ | **الأيام المتوفرة فقط** مرتبة. قد تكون فارغة `[]` |
| `days` | object[] | ❌ | **كل أيام الشهر** (28 إلى 31 عنصراً) بالترتيب من اليوم 1 |

#### على مستوى كل عنصر في `days`

| الحقل | النوع | Nullable | الوصف |
|---|---|---|---|
| `date` | string | ❌ | `YYYY-MM-DD` |
| `day_name` | string | ❌ | `Sun`, `Mon`… بالإنجليزية |
| `day_number` | int | ❌ | رقم اليوم في الشهر (1–31) |
| `is_today` | bool | ❌ | هل هو اليوم الحالي |
| `is_available` | bool | ❌ | **هذا ما تعتمد عليه** لتفعيل/تعطيل اليوم |
| `reason_code` | string | ❌ | سبب الحالة. انظر [القسم 5](#5-قيم-reason_code-وكيف-تعرضها) |
| `available_slots_count` | int | ❌ | عدد الأوقات الفارغة في اليوم. `0` إن لم يكن متوفراً |
| `first_available_slot` | string | ✅ | أول وقت فارغ `HH:mm`، أو `null` |

> 💡 `days[i].day_number == i + 1` دائماً، فيمكنك الوصول ليوم معيّن مباشرة: `days[dayNumber - 1]`.

> 💡 `first_available_slot` يصلح مباشرة كـ `start_time` في `POST /api/bookings`. استخدمه مثلاً لعرض «أقرب موعد: 09:00» تحت كل يوم.

---

## 4. API 2 — أيام توفر خدمة + من المتاح كل يوم

```
GET /api/availability/days/providers
```

### Query Parameters

| Parameter | Type | Required | الوصف |
|---|---|---|---|
| `service_id` | int | ✅ | معرّف الخدمة (يجب أن تكون نشطة) |
| `month` | string | ❌ | `YYYY-MM`. **إن لم تُرسله = الشهر الحالي** |
| `branch_id` | int | ❌ | لعرض مزوّدي فرع واحد فقط. يجب أن يكون فرعاً موجوداً |

### مثال طلب

```
GET {{base_url}}/api/availability/days/providers?service_id=2&month=2026-11
Accept: application/json
Accept-Language: ar
```

### رد ناجح — 200

```json
{
  "success": true,
  "data": {
    "service": {
      "id": 2,
      "name": "Beard Trim & Shape",
      "duration_minutes": 20,
      "formatted_duration": "20m"
    },
    "branch_id": null,
    "month": "2026-11",
    "month_name": "November 2026",
    "previous_month": "2026-10",
    "next_month": "2026-12",
    "last_bookable_date": "2026-12-23",
    "available_dates": [
      "2026-11-01",
      "2026-11-02",
      "2026-11-03",
      "2026-11-04"
    ],
    "days": [
      {
        "date": "2026-11-01",
        "day_name": "Sun",
        "day_number": 1,
        "is_today": false,
        "is_available": true,
        "reason_code": "available",
        "providers_count": 4,
        "providers": [
          {
            "provider_id": 3,
            "provider_name": "Sarah Johnson",
            "provider_avatar": null,
            "service_pricing": {
              "original_price": 18,
              "effective_price": 18,
              "has_discount": false,
              "discount_amount": 0,
              "discount_percentage": 0,
              "currency": "EUR",
              "formatted_price": "18.00 EUR"
            },
            "available_slots_count": 27,
            "first_available_slot": "09:00"
          },
          {
            "provider_id": 4,
            "provider_name": "Maria Rodriguez",
            "provider_avatar": null,
            "service_pricing": {
              "original_price": 18,
              "effective_price": 18,
              "has_discount": false,
              "discount_amount": 0,
              "discount_percentage": 0,
              "currency": "EUR",
              "formatted_price": "18.00 EUR"
            },
            "available_slots_count": 27,
            "first_available_slot": "09:00"
          }
        ]
      }
    ]
  },
  "message": "Availability days retrieved successfully"
}
```

### شكل الأيام غير المتوفرة

```json
{ "date": "2026-11-08", "day_name": "Sun", "day_number": 8, "is_today": false,
  "is_available": false, "reason_code": "no_availability",
  "providers_count": 0, "providers": [] }

{ "date": "2026-11-11", "day_name": "Wed", "day_number": 11, "is_today": false,
  "is_available": false, "reason_code": "salon_closed",
  "providers_count": 0, "providers": [] }
```

### شرح الحقول (المختلفة عن API 1)

| الحقل | النوع | Nullable | الوصف |
|---|---|---|---|
| `branch_id` | int | ✅ | الفرع الذي أرسلته، أو `null` = كل الفروع |
| `days[].providers_count` | int | ❌ | عدد المزوّدين المتاحين ذلك اليوم |
| `days[].providers` | object[] | ❌ | المزوّدون **المتاحون فعلاً** فقط. فارغة `[]` لليوم غير المتوفر |
| `providers[].provider_id` | int | ❌ | |
| `providers[].provider_name` | string | ❌ | |
| `providers[].provider_avatar` | string | ✅ | |
| `providers[].service_pricing` | object | ❌ | سعر الخدمة **عند هذا المزوّد** (قد يختلف بين المزوّدين) |
| `providers[].available_slots_count` | int | ❌ | عدد أوقاته الفارغة ذلك اليوم (دائماً ≥ 1) |
| `providers[].first_available_slot` | string | ❌ | أول وقت فارغ عنده `HH:mm` |

> ⚠️ **الأوقات التفصيلية غير موجودة في هذا الرد** (لتخفيف حجمه، فشهر كامل × عدة مزوّدين × عشرات الأوقات ثقيل). عند اختيار يوم اجلب أوقاته كما في [القسم 11](#11-من-اليوم-إلى-الحجز--الرحلة-الكاملة).

> المزوّد في إجازة، أو لا يعمل، أو ممتلئ، **لا يظهر أبداً** في `providers`. لا داعي لفلترته في التطبيق.

### كائن السعر (`pricing` / `service_pricing`)

| الحقل | النوع | الوصف |
|---|---|---|
| `original_price` | **num** | السعر الأصلي للخدمة |
| `effective_price` | **num** | **السعر الذي سيُدفع فعلاً** — اعرض هذا |
| `has_discount` | bool | هل يوجد خصم |
| `discount_amount` | **num** | قيمة الخصم |
| `discount_percentage` | **num** | نسبة الخصم |
| `currency` | string | `"EUR"` |
| `formatted_price` | string | جاهز للعرض `"18.00 EUR"` |

> ⚠️ **مهم جداً في Dart:** الأرقام الصحيحة تصل بلا فاصلة (`18` وليس `18.0`)، فـ `json['effective_price'] as double` **سينهار** بـ `type 'int' is not a subtype of type 'double'`. استخدم دائماً: `(json['effective_price'] as num).toDouble()`.

---

## 5. قيم `reason_code` وكيف تعرضها

| `reason_code` | يظهر في | المعنى | اقتراح العرض |
|---|---|---|---|
| `available` | 1 و 2 | يوجد وقت فارغ | يوم **مفعّل** |
| `past` | 1 و 2 | يوم مضى من الشهر الحالي | معطّل باهت، بدون نص |
| `not_working_day` | 1 | ليس يوم عمل المزوّد | معطّل — «عطلة» |
| `on_leave` | 1 | المزوّد في إجازة يوم كامل | معطّل — «إجازة» |
| `salon_closed` | 1 و 2 | الصالون مغلق ذلك اليوم | معطّل — «مغلق» |
| `fully_booked` | 1 | يوم عمل لكن لا وقت فارغ (حجوزات أو إجازة ساعية أو انتهى اليوم) | معطّل — «محجوز بالكامل» |
| `outside_booking_window` | 1 و 2 | بعد `last_bookable_date` | معطّل — «غير متاح للحجز بعد» |
| `no_availability` | 2 | لا يوجد أي مزوّد متاح ذلك اليوم | معطّل — «لا مواعيد» |

**القاعدة الذهبية:** قرار التفعيل/التعطيل يعتمد على `is_available` **فقط**. أما `reason_code` فهو للنص أو اللون التوضيحي.

> 🔮 **للتوافق المستقبلي:** إن وصلت قيمة `reason_code` لا يعرفها التطبيق، عاملها كيوم غير متوفر ولا ينهار التطبيق. نموذج Dart في القسم 8 يفعل هذا عبر `DayReason.unknown`.

---

## 6. التنقل بين الأشهر

**لا تحسب الشهر التالي بنفسك.** خذه من الرد:

```
الشهر الحالي:  previous_month = null      next_month = "2026-11"
شهر في الوسط:  previous_month = "2026-10" next_month = "2026-12"
آخر شهر:       previous_month = "2026-11" next_month = null
```

- زر «الشهر السابق» مفعّل فقط إن كان `previous_month != null`.
- زر «الشهر التالي» مفعّل فقط إن كان `next_month != null`.
- أول طلب في الشاشة: **بدون `month`**، فيأتيك الشهر الحالي.

**كم شهراً للأمام؟** يحددها إعداد الصالون «أقصى أيام الحجز المسبق»، وهو حالياً **60 يوماً**. لذلك سيرى العميل عادة الشهر الحالي والشهرين التاليين. الإدارة قد تغيّر هذا الرقم، والتطبيق يتكيّف تلقائياً لأنه يعتمد على `next_month` و `last_bookable_date`.

**في الشهر الأخير** تظهر أيامه بعد `last_bookable_date` بـ `outside_booking_window`. هذا طبيعي.

---

## 7. الأخطاء — كل الحالات

### 422 — مزوّد غير قابل للحجز (API 1)

يحدث إن كان المزوّد غير موجود، أو غير نشط، أو لا يقدّم هذه الخدمة. **رسالة واحدة لكل هذه الأسباب** (عمداً لأسباب أمنية).

```json
{
  "success": false,
  "message": "مقدم الخدمة المحدد غير متاح لهذه الخدمة."
}
```
(بالإنجليزية: `The selected provider is unavailable for this service.`)

**ماذا تفعل:** اعرض الرسالة وأعد العميل لاختيار مزوّد. غالباً معناها أن قائمة المزوّدين في التطبيق قديمة.

### 422 — الشهر غير مسموح (API 1 و 2)

شهر مضى، أو شهر أبعد من `last_bookable_date`.

```json
{
  "success": false,
  "message": "لا يمكن الحجز في هذا الشهر."
}
```
(بالإنجليزية: `This month cannot be booked.`)

**ماذا تفعل:** لن يحدث إن اعتمدت على `next_month` و `previous_month`. إن حدث، فاطلب **بدون `month`** لترجع للشهر الحالي.

### 422 — خطأ Validation

خدمة غير موجودة/غير نشطة/محذوفة، أو بارامتر ناقص، أو `month` بصيغة خاطئة (مثل `2026-1`)، أو `branch_id` غير موجود.

```json
{
  "message": "The service id field is required. (and 2 more errors)",
  "errors": {
    "service_id": ["The service id field is required."],
    "provider_id": ["The provider id field is required."],
    "month": ["The month field must match the format Y-m."]
  }
}
```

```json
{
  "message": "The selected service id is invalid.",
  "errors": {
    "service_id": ["The selected service id is invalid."]
  }
}
```

> رسائل الـ validation تأتي **بالإنجليزية**. هذه أخطاء برمجية لا يُفترض أن يراها العميل؛ اعرض رسالة عامة مثل «تعذّر تحميل المواعيد» وسجّل التفاصيل في الـ logs.
> «الخدمة غير موجودة» هنا غالباً تعني أن الخدمة أوقفتها الإدارة: حدّث قائمة الخدمات.

### 429 — تجاوز الحد

```json
{ "message": "Too Many Attempts." }
```
مع هيدر `Retry-After` (بالثواني). **لا تُعد المحاولة فوراً في حلقة.** انتظر `Retry-After` ثانية.

### 500 — خطأ في السيرفر

```json
{
  "success": false,
  "message": "Failed to retrieve availability days"
}
```
اعرض رسالة عامة وزر «إعادة المحاولة».

### ملخص سريع

| Status | `success` موجود؟ | `errors` موجود؟ | المعنى |
|---|---|---|---|
| 200 | ✅ `true` | ❌ | نجاح |
| 422 | ✅ `false` | ❌ | رفض منطقي — اعرض `message` للعميل |
| 422 | ❌ | ✅ | validation — خطأ برمجي، رسالة عامة |
| 429 | ❌ | ❌ | انتظر `Retry-After` |
| 500 | ✅ `false` | ❌ | خطأ سيرفر |

---

## 8. نماذج Dart (Models)

> مكتوبة بـ Dart 3 بدون code generation، لتنسخها مباشرة. إن كنت تستخدم `json_serializable` أو `freezed` فالحقول نفسها.

```dart
// lib/features/availability/models/availability_models.dart

/// سبب حالة اليوم. القيمة غير المعروفة → unknown (لا ينهار التطبيق).
enum DayReason {
  available,
  past,
  notWorkingDay,
  onLeave,
  salonClosed,
  fullyBooked,
  outsideBookingWindow,
  noAvailability,
  unknown;

  static DayReason parse(String? value) => switch (value) {
        'available' => DayReason.available,
        'past' => DayReason.past,
        'not_working_day' => DayReason.notWorkingDay,
        'on_leave' => DayReason.onLeave,
        'salon_closed' => DayReason.salonClosed,
        'fully_booked' => DayReason.fullyBooked,
        'outside_booking_window' => DayReason.outsideBookingWindow,
        'no_availability' => DayReason.noAvailability,
        _ => DayReason.unknown,
      };
}

/// الأرقام قد تصل int (18) أو double (17.5) — لا تستخدم `as double` أبداً.
double _toDouble(Object? v) => (v as num?)?.toDouble() ?? 0;

class ServicePricing {
  final double originalPrice;
  final double effectivePrice;
  final bool hasDiscount;
  final double discountAmount;
  final double discountPercentage;
  final String currency;
  final String formattedPrice;

  const ServicePricing({
    required this.originalPrice,
    required this.effectivePrice,
    required this.hasDiscount,
    required this.discountAmount,
    required this.discountPercentage,
    required this.currency,
    required this.formattedPrice,
  });

  factory ServicePricing.fromJson(Map<String, dynamic> j) => ServicePricing(
        originalPrice: _toDouble(j['original_price']),
        effectivePrice: _toDouble(j['effective_price']),
        hasDiscount: j['has_discount'] as bool? ?? false,
        discountAmount: _toDouble(j['discount_amount']),
        discountPercentage: _toDouble(j['discount_percentage']),
        currency: j['currency'] as String? ?? 'EUR',
        formattedPrice: j['formatted_price'] as String? ?? '',
      );
}

class AvailabilityService {
  final int id;
  final String name;
  final int durationMinutes;
  final String formattedDuration;

  const AvailabilityService({
    required this.id,
    required this.name,
    required this.durationMinutes,
    required this.formattedDuration,
  });

  factory AvailabilityService.fromJson(Map<String, dynamic> j) =>
      AvailabilityService(
        id: j['id'] as int,
        name: j['name'] as String,
        durationMinutes: j['duration_minutes'] as int,
        formattedDuration: j['formatted_duration'] as String,
      );
}

class Branch {
  final int id;
  final String name;
  final String? address;
  final String? phone;
  final double? latitude;
  final double? longitude;

  const Branch({
    required this.id,
    required this.name,
    this.address,
    this.phone,
    this.latitude,
    this.longitude,
  });

  factory Branch.fromJson(Map<String, dynamic> j) {
    final coords = j['coordinates'] as Map<String, dynamic>?;
    return Branch(
      id: j['id'] as int,
      name: j['name'] as String,
      address: j['address'] as String?,
      phone: j['phone'] as String?,
      // الإحداثيات تصل كنص: "25.2048000"
      latitude: double.tryParse('${coords?['latitude'] ?? ''}'),
      longitude: double.tryParse('${coords?['longitude'] ?? ''}'),
    );
  }
}

class AvailabilityProvider {
  final int id;
  final String name;
  final String? avatar;
  final Branch? branch;

  const AvailabilityProvider({
    required this.id,
    required this.name,
    this.avatar,
    this.branch,
  });

  factory AvailabilityProvider.fromJson(Map<String, dynamic> j) =>
      AvailabilityProvider(
        id: j['id'] as int,
        name: j['name'] as String,
        avatar: j['avatar'] as String?,
        branch: j['branch'] == null
            ? null
            : Branch.fromJson(j['branch'] as Map<String, dynamic>),
      );
}

/// الحقول المشتركة بين كل أيام الردّين.
abstract class MonthDayBase {
  String get date; // "2026-11-03"
  int get dayNumber;
  bool get isToday;
  bool get isAvailable;
  DayReason get reason;

  /// التاريخ كـ DateTime محلي (منتصف الليل) — للتقويم فقط، لا تحوّله لـ UTC.
  DateTime get dateTime => DateTime.parse(date);
}

/// يوم في رد API 1.
class ProviderMonthDay extends MonthDayBase {
  @override
  final String date;
  @override
  final int dayNumber;
  @override
  final bool isToday;
  @override
  final bool isAvailable;
  @override
  final DayReason reason;
  final int availableSlotsCount;
  final String? firstAvailableSlot; // "09:00"

  ProviderMonthDay({
    required this.date,
    required this.dayNumber,
    required this.isToday,
    required this.isAvailable,
    required this.reason,
    required this.availableSlotsCount,
    this.firstAvailableSlot,
  });

  factory ProviderMonthDay.fromJson(Map<String, dynamic> j) => ProviderMonthDay(
        date: j['date'] as String,
        dayNumber: j['day_number'] as int,
        isToday: j['is_today'] as bool,
        isAvailable: j['is_available'] as bool,
        reason: DayReason.parse(j['reason_code'] as String?),
        availableSlotsCount: j['available_slots_count'] as int? ?? 0,
        firstAvailableSlot: j['first_available_slot'] as String?,
      );
}

/// مزوّد متاح داخل يوم في رد API 2.
class DayProvider {
  final int providerId;
  final String providerName;
  final String? providerAvatar;
  final ServicePricing pricing;
  final int availableSlotsCount;
  final String firstAvailableSlot;

  const DayProvider({
    required this.providerId,
    required this.providerName,
    this.providerAvatar,
    required this.pricing,
    required this.availableSlotsCount,
    required this.firstAvailableSlot,
  });

  factory DayProvider.fromJson(Map<String, dynamic> j) => DayProvider(
        providerId: j['provider_id'] as int,
        providerName: j['provider_name'] as String,
        providerAvatar: j['provider_avatar'] as String?,
        pricing: ServicePricing.fromJson(
            j['service_pricing'] as Map<String, dynamic>),
        availableSlotsCount: j['available_slots_count'] as int,
        firstAvailableSlot: j['first_available_slot'] as String,
      );
}

/// يوم في رد API 2.
class ServiceMonthDay extends MonthDayBase {
  @override
  final String date;
  @override
  final int dayNumber;
  @override
  final bool isToday;
  @override
  final bool isAvailable;
  @override
  final DayReason reason;
  final List<DayProvider> providers;

  ServiceMonthDay({
    required this.date,
    required this.dayNumber,
    required this.isToday,
    required this.isAvailable,
    required this.reason,
    required this.providers,
  });

  int get providersCount => providers.length;

  factory ServiceMonthDay.fromJson(Map<String, dynamic> j) => ServiceMonthDay(
        date: j['date'] as String,
        dayNumber: j['day_number'] as int,
        isToday: j['is_today'] as bool,
        isAvailable: j['is_available'] as bool,
        reason: DayReason.parse(j['reason_code'] as String?),
        providers: (j['providers'] as List<dynamic>? ?? [])
            .map((e) => DayProvider.fromJson(e as Map<String, dynamic>))
            .toList(),
      );
}

/// معلومات الشهر والتنقل — مشتركة بين الردّين.
class MonthInfo {
  final String month; // "2026-11"
  final String? previousMonth;
  final String? nextMonth;
  final String lastBookableDate;
  final Set<String> availableDates;

  const MonthInfo({
    required this.month,
    this.previousMonth,
    this.nextMonth,
    required this.lastBookableDate,
    required this.availableDates,
  });

  bool get hasPrevious => previousMonth != null;
  bool get hasNext => nextMonth != null;
  bool get isEmpty => availableDates.isEmpty;

  /// أول يوم في الشهر — لـ focusedDay في التقويم.
  DateTime get firstDay => DateTime.parse('$month-01');

  factory MonthInfo.fromJson(Map<String, dynamic> j) => MonthInfo(
        month: j['month'] as String,
        previousMonth: j['previous_month'] as String?,
        nextMonth: j['next_month'] as String?,
        lastBookableDate: j['last_bookable_date'] as String,
        // Set → بحث O(1) عند رسم كل خلية في التقويم
        availableDates:
            (j['available_dates'] as List<dynamic>).cast<String>().toSet(),
      );
}

/// رد API 1 كاملاً.
class ProviderMonthAvailability {
  final AvailabilityService service;
  final AvailabilityProvider provider;
  final ServicePricing pricing;
  final MonthInfo info;
  final List<ProviderMonthDay> days;

  const ProviderMonthAvailability({
    required this.service,
    required this.provider,
    required this.pricing,
    required this.info,
    required this.days,
  });

  factory ProviderMonthAvailability.fromJson(Map<String, dynamic> j) =>
      ProviderMonthAvailability(
        service: AvailabilityService.fromJson(
            j['service'] as Map<String, dynamic>),
        provider: AvailabilityProvider.fromJson(
            j['provider'] as Map<String, dynamic>),
        pricing: ServicePricing.fromJson(j['pricing'] as Map<String, dynamic>),
        info: MonthInfo.fromJson(j),
        days: (j['days'] as List<dynamic>)
            .map((e) => ProviderMonthDay.fromJson(e as Map<String, dynamic>))
            .toList(),
      );

  /// days مرتبة من اليوم 1، فاليوم N في الفهرس N-1.
  ProviderMonthDay dayOf(DateTime d) => days[d.day - 1];
}

/// رد API 2 كاملاً.
class ServiceMonthAvailability {
  final AvailabilityService service;
  final int? branchId;
  final MonthInfo info;
  final List<ServiceMonthDay> days;

  const ServiceMonthAvailability({
    required this.service,
    this.branchId,
    required this.info,
    required this.days,
  });

  factory ServiceMonthAvailability.fromJson(Map<String, dynamic> j) =>
      ServiceMonthAvailability(
        service: AvailabilityService.fromJson(
            j['service'] as Map<String, dynamic>),
        branchId: j['branch_id'] as int?,
        info: MonthInfo.fromJson(j),
        days: (j['days'] as List<dynamic>)
            .map((e) => ServiceMonthDay.fromJson(e as Map<String, dynamic>))
            .toList(),
      );

  ServiceMonthDay dayOf(DateTime d) => days[d.day - 1];
}
```

---

## 9. طبقة الـ API في Dart

المثال بـ `dio`. مع `http` المنطق نفسه.

```dart
// lib/features/availability/data/availability_api.dart
import 'package:dio/dio.dart';
import '../models/availability_models.dart';

/// خطأ موحّد يفهمه الـ UI.
class AvailabilityException implements Exception {
  final int? statusCode;

  /// رسالة صالحة للعرض للعميل (مترجمة من السيرفر) — أو null إن كانت تقنية.
  final String? userMessage;

  /// تفاصيل validation (للـ logs فقط).
  final Map<String, List<String>> fieldErrors;

  /// عند 429: كم ثانية ننتظر.
  final int? retryAfterSeconds;

  const AvailabilityException({
    this.statusCode,
    this.userMessage,
    this.fieldErrors = const {},
    this.retryAfterSeconds,
  });

  bool get isRateLimited => statusCode == 429;

  /// 422 مع success:false → رفض منطقي برسالة مترجمة تُعرض كما هي.
  bool get isBusinessRejection =>
      statusCode == 422 && userMessage != null && fieldErrors.isEmpty;

  factory AvailabilityException.fromDio(DioException e) {
    final res = e.response;
    final status = res?.statusCode;
    final body = res?.data;

    if (status == 429) {
      return AvailabilityException(
        statusCode: 429,
        retryAfterSeconds:
            int.tryParse(res?.headers.value('retry-after') ?? '') ?? 60,
      );
    }

    if (body is Map<String, dynamic>) {
      final errors = (body['errors'] as Map<String, dynamic>?)?.map(
            (k, v) => MapEntry(k, (v as List).cast<String>()),
          ) ??
          const <String, List<String>>{};

      return AvailabilityException(
        statusCode: status,
        // رسالة validation إنجليزية وتقنية → لا نعرضها للعميل.
        userMessage: errors.isEmpty ? body['message'] as String? : null,
        fieldErrors: errors,
      );
    }

    return AvailabilityException(statusCode: status); // انقطاع شبكة/timeout
  }
}

class AvailabilityApi {
  AvailabilityApi(this._dio);

  final Dio _dio;

  /// API 1 — أيام توفر مزوّد. month = null → الشهر الحالي.
  Future<ProviderMonthAvailability> providerMonth({
    required int serviceId,
    required int providerId,
    String? month,
  }) async {
    final data = await _get('/availability/days', {
      'service_id': serviceId,
      'provider_id': providerId,
      if (month != null) 'month': month,
    });
    return ProviderMonthAvailability.fromJson(data);
  }

  /// API 2 — أيام توفر خدمة + المتاحون كل يوم.
  Future<ServiceMonthAvailability> serviceMonth({
    required int serviceId,
    String? month,
    int? branchId,
  }) async {
    final data = await _get('/availability/days/providers', {
      'service_id': serviceId,
      if (month != null) 'month': month,
      if (branchId != null) 'branch_id': branchId,
    });
    return ServiceMonthAvailability.fromJson(data);
  }

  Future<Map<String, dynamic>> _get(
    String path,
    Map<String, dynamic> query,
  ) async {
    try {
      final res = await _dio.get<Map<String, dynamic>>(
        path,
        queryParameters: query,
      );
      return res.data!['data'] as Map<String, dynamic>;
    } on DioException catch (e) {
      throw AvailabilityException.fromDio(e);
    }
  }
}
```

### إعداد Dio

```dart
final dio = Dio(BaseOptions(
  baseUrl: '$baseUrl/api',
  connectTimeout: const Duration(seconds: 10),
  receiveTimeout: const Duration(seconds: 15),
  headers: {
    'Accept': 'application/json', // إلزامي
  },
));

// اللغة من لغة التطبيق الحالية (ar / de / en)
dio.interceptors.add(InterceptorsWrapper(
  onRequest: (options, handler) {
    options.headers['Accept-Language'] = currentLocale.languageCode;
    handler.next(options);
  },
));
```

---

## 10. بناء شاشة التقويم

### 10.1 — Controller للحالة (مثال بـ `ChangeNotifier`)

يمكن تحويله بسهولة لـ Riverpod / Bloc / GetX؛ المنطق هو المهم.

```dart
// lib/features/availability/presentation/provider_month_controller.dart
import 'package:flutter/foundation.dart';
import '../data/availability_api.dart';
import '../models/availability_models.dart';

class ProviderMonthController extends ChangeNotifier {
  ProviderMonthController({
    required this.api,
    required this.serviceId,
    required this.providerId,
  });

  final AvailabilityApi api;
  final int serviceId;
  final int providerId;

  ProviderMonthAvailability? data;
  bool loading = false;
  AvailabilityException? error;

  /// كاش بسيط للأشهر التي فُتحت — الرجوع لشهر سابق لا يعيد الطلب.
  final Map<String, ProviderMonthAvailability> _cache = {};

  /// أول تحميل: بدون month → الشهر الحالي.
  Future<void> loadInitial() => _load(null);

  Future<void> goNext() async {
    final next = data?.info.nextMonth;
    if (next != null && !loading) await _load(next);
  }

  Future<void> goPrevious() async {
    final prev = data?.info.previousMonth;
    if (prev != null && !loading) await _load(prev);
  }

  /// بعد حجز ناجح أو 409: الأيام تغيّرت، امسح الكاش وأعد التحميل.
  Future<void> refresh() async {
    _cache.clear();
    await _load(data?.info.month);
  }

  Future<void> _load(String? month) async {
    if (month != null && _cache.containsKey(month)) {
      data = _cache[month];
      error = null;
      notifyListeners();
      return;
    }

    loading = true;
    error = null;
    notifyListeners();

    try {
      final result = await api.providerMonth(
        serviceId: serviceId,
        providerId: providerId,
        month: month,
      );
      _cache[result.info.month] = result;
      data = result;
    } on AvailabilityException catch (e) {
      error = e;
    } finally {
      loading = false;
      notifyListeners();
    }
  }
}
```

> **مدة الكاش:** المواعيد تتغير كلما حجز أحد. احتفظ بالكاش **طالما الشاشة مفتوحة** فقط، وامسحه عند `refresh()`. لا تخزّنه على القرص.

### 10.2 — الواجهة باستخدام `table_calendar`

```yaml
# pubspec.yaml
dependencies:
  table_calendar: ^3.1.2
  intl: ^0.19.0
```

> `DateFormat` و `TableCalendar(locale: ...)` بلغة غير الإنجليزية يحتاجان تهيئة مرة واحدة في `main()`:
> ```dart
> import 'package:intl/date_symbol_data_local.dart';
> await initializeDateFormatting(); // قبل runApp
> ```

```dart
// lib/features/availability/presentation/provider_month_view.dart
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:table_calendar/table_calendar.dart';
import '../models/availability_models.dart';
import 'provider_month_controller.dart';

final _key = DateFormat('yyyy-MM-dd');

class ProviderMonthView extends StatelessWidget {
  const ProviderMonthView({
    super.key,
    required this.controller,
    required this.onDayPicked,
  });

  final ProviderMonthController controller;
  final void Function(ProviderMonthDay day) onDayPicked;

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: controller,
      builder: (context, _) {
        final data = controller.data;

        if (data == null) {
          if (controller.error != null) {
            return _ErrorView(
              error: controller.error!,
              onRetry: controller.loadInitial,
            );
          }
          return const Center(child: CircularProgressIndicator());
        }

        final info = data.info;
        final locale = Localizations.localeOf(context).toLanguageTag();

        return Column(
          children: [
            // ── رأس الشهر مع أزرار التنقل المبنية على previous/next_month
            Row(
              children: [
                IconButton(
                  icon: const Icon(Icons.chevron_left),
                  onPressed: info.hasPrevious ? controller.goPrevious : null,
                ),
                Expanded(
                  child: Text(
                    // اسم الشهر مترجم محلياً بدل month_name الإنجليزي
                    DateFormat.yMMMM(locale).format(info.firstDay),
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.chevron_right),
                  onPressed: info.hasNext ? controller.goNext : null,
                ),
              ],
            ),
            if (controller.loading) const LinearProgressIndicator(),

            TableCalendar<void>(
              locale: locale,
              headerVisible: false, // نستخدم رأسنا المبني على الـ API
              availableGestures: AvailableGestures.none, // التنقل بالأزرار فقط
              firstDay: info.firstDay,
              lastDay: DateTime(
                  info.firstDay.year, info.firstDay.month + 1, 0), // آخر الشهر
              focusedDay: info.firstDay,
              // أيام الشهر المجاور لا تُرسم — days تخص هذا الشهر فقط
              calendarStyle: const CalendarStyle(outsideDaysVisible: false),
              // ⬅️ هنا جوهر الميزة: فقط الأيام المتوفرة قابلة للضغط
              enabledDayPredicate: (d) =>
                  info.availableDates.contains(_key.format(d)),
              onDaySelected: (selected, _) => onDayPicked(data.dayOf(selected)),
              calendarBuilders: CalendarBuilders(
                defaultBuilder: (context, d, _) => _cell(data, info, d),
                todayBuilder: (context, d, _) => _cell(data, info, d),
                // disabledBuilder يُستدعى لكل يوم غير قابل للضغط
                disabledBuilder: (context, d, _) => _cell(data, info, d),
              ),
            ),

            if (info.isEmpty)
              Padding(
                padding: const EdgeInsets.all(16),
                child: Text(
                  info.hasNext
                      ? 'لا توجد مواعيد متاحة هذا الشهر — جرّب الشهر التالي'
                      : 'لا توجد مواعيد متاحة حالياً',
                  textAlign: TextAlign.center,
                ),
              ),
          ],
        );
      },
    );
  }
}

/// حارس: لا نقرأ days إلا ليوم من نفس الشهر المعروض.
Widget _cell(ProviderMonthAvailability data, MonthInfo info, DateTime d) {
  if (d.year != info.firstDay.year || d.month != info.firstDay.month) {
    return const SizedBox.shrink();
  }
  return _DayCell(day: data.dayOf(d));
}

class _DayCell extends StatelessWidget {
  const _DayCell({required this.day});

  final ProviderMonthDay day;

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    final available = day.isAvailable;
    final isToday = day.isToday;

    return Container(
      margin: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: available ? scheme.primaryContainer : null,
        shape: BoxShape.circle,
        border: isToday ? Border.all(color: scheme.primary) : null,
      ),
      alignment: Alignment.center,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            '${day.dayNumber}',
            style: TextStyle(
              color: available ? scheme.onPrimaryContainer : scheme.outline,
              decoration: day.reason == DayReason.past
                  ? TextDecoration.lineThrough
                  : null,
            ),
          ),
          // أقرب وقت تحت الرقم — اختياري
          if (available && day.firstAvailableSlot != null)
            Text(day.firstAvailableSlot!,
                style: const TextStyle(fontSize: 9)),
        ],
      ),
    );
  }
}
```

### 10.3 — نص توضيحي للسبب (اختياري)

مثلاً عند الضغط المطوّل على يوم معطّل:

```dart
String reasonLabel(DayReason r) => switch (r) {
      DayReason.available => 'متاح',
      DayReason.past => 'انتهى',
      DayReason.notWorkingDay => 'عطلة',
      DayReason.onLeave => 'إجازة',
      DayReason.salonClosed => 'الصالون مغلق',
      DayReason.fullyBooked => 'محجوز بالكامل',
      DayReason.outsideBookingWindow => 'غير متاح للحجز بعد',
      DayReason.noAvailability => 'لا مواعيد',
      DayReason.unknown => 'غير متاح',
    };
```

### 10.4 — عرض الأخطاء

```dart
class _ErrorView extends StatelessWidget {
  const _ErrorView({required this.error, required this.onRetry});

  final AvailabilityException error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final message = error.isRateLimited
        ? 'طلبات كثيرة، حاول بعد ${error.retryAfterSeconds} ثانية'
        : error.isBusinessRejection
            ? error.userMessage! // مترجمة من السيرفر
            : 'تعذّر تحميل المواعيد';

    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 12),
          if (!error.isBusinessRejection)
            FilledButton(onPressed: onRetry, child: const Text('إعادة المحاولة')),
        ],
      ),
    );
  }
}
```

### 10.5 — شاشة API 2 (الخدمة فقط)

نفس الـ Controller والتقويم تماماً، مع استبدال `api.providerMonth(...)` بـ `api.serviceMonth(...)`. الفرق عند الضغط على يوم: اعرض `day.providers` في Bottom Sheet:

```dart
void showProvidersOfDay(BuildContext context, ServiceMonthDay day) {
  showModalBottomSheet(
    context: context,
    builder: (_) => ListView(
      shrinkWrap: true,
      children: [
        for (final p in day.providers)
          ListTile(
            leading: CircleAvatar(
              backgroundImage:
                  p.providerAvatar != null ? NetworkImage(p.providerAvatar!) : null,
              child: p.providerAvatar == null ? const Icon(Icons.person) : null,
            ),
            title: Text(p.providerName),
            subtitle: Text(
                'أقرب موعد ${p.firstAvailableSlot} • ${p.availableSlotsCount} موعد متاح'),
            trailing: Text(p.pricing.formattedPrice),
            onTap: () {
              Navigator.pop(context);
              // → اذهب لاختيار الوقت عند هذا المزوّد في هذا اليوم (القسم 11)
            },
          ),
      ],
    ),
  );
}
```

> 💡 في API 2 يمكنك أيضاً إظهار عدد المزوّدين داخل خلية اليوم (`day.providersCount`) كنقطة أو رقم صغير.

---

## 11. من اليوم إلى الحجز — الرحلة الكاملة

الـ API الجديدان يحددان **اليوم**. أما **الوقت** فيأتي من الـ endpoints الموجودة مسبقاً، ثم الحجز كالمعتاد.

### الرحلة A — العميل اختار المزوّد أولاً (API 1)

```
1. اختيار الخدمة
2. اختيار المزوّد
3. GET /api/availability/days?service_id=S&provider_id=P            ← التقويم
4. ضغط على يوم D متوفر
5. GET /api/availability/provider?service_id=S&provider_id=P&date=D ← أوقات اليوم
6. اختيار وقت T
7. POST /api/bookings                                                 ← الحجز (يتطلب تسجيل دخول)
```

### الرحلة B — العميل اختار الخدمة فقط (API 2)

```
1. اختيار الخدمة
2. GET /api/availability/days/providers?service_id=S                ← التقويم + المزوّدون
3. ضغط على يوم D → قائمة day.providers (موجودة في الرد، بلا طلب جديد)
4. اختيار مزوّد P
5. GET /api/availability/provider?service_id=S&provider_id=P&date=D ← أوقات P في D
   (أو GET /api/availability/service?service_id=S&date=D لكل المزوّدين بأوقاتهم)
6. اختيار وقت T
7. POST /api/bookings
```

### أوقات اليوم — `GET /api/availability/provider` (موجود مسبقاً)

```
GET {{base_url}}/api/availability/provider?service_id=2&provider_id=3&date=2026-11-03
```

العنصر المهم في الرد هو `data.available_slots`:

```json
{
  "success": true,
  "data": {
    "is_available": true,
    "reason_code": "available",
    "total_slots": 27,
    "available_slots": [
      {
        "start_time": "09:00",
        "end_time": "09:20",
        "start_time_formatted": "09:00 AM",
        "end_time_formatted": "09:20 AM",
        "display_time": "09:00 AM",
        "duration_minutes": 20
      }
    ]
  }
}
```

### الحجز — `POST /api/bookings` (موجود مسبقاً، يتطلب `Authorization: Bearer`)

```json
{
  "date": "2026-11-03",
  "payment_method": "cash",
  "services": [
    { "service_id": 2, "provider_id": 3, "start_time": "09:00" }
  ]
}
```

- `date` = `day.date` من التقويم.
- `start_time` = `start_time` من الوقت المختار (أو `first_available_slot` مباشرة لزر «احجز أقرب موعد»).
- نجاح: `201`.

### ⚠️ حالة 409 — شخص آخر سبقك

بين عرض التقويم وضغط «احجز» قد يحجز عميل آخر نفس الوقت:

```json
{
  "success": false,
  "message": "The selected time slot is no longer available.",
  "error_type": "slot_conflict"
}
```

**ماذا تفعل:** ليس خطأ العميل. اعرض «هذا الموعد حُجز للتو، اختر موعداً آخر»، ثم:
1. أعد جلب أوقات اليوم (`/availability/provider`).
2. نادِ `controller.refresh()` حتى يتحدّث التقويم، فقد يكون اليوم امتلأ.

**وبعد حجز ناجح** نادِ `controller.refresh()` أيضاً إن كان التقويم سيُعرض مرة أخرى.

---

## 12. أخطاء شائعة يجب تجنبها

| ❌ الخطأ | ✅ الصحيح |
|---|---|
| `json['effective_price'] as double` | `(json['effective_price'] as num).toDouble()` — الأرقام الصحيحة تصل `int` |
| `json['coordinates']['latitude'] as double` | `double.tryParse(...)` — تصل كنص |
| حساب الشهر التالي بـ `DateTime(y, m + 1)` | استخدم `next_month` من الرد |
| تفعيل اليوم بناءً على `reason_code == 'available'` فقط | استخدم `is_available` |
| تحويل `date` إلى UTC أو `toLocal()` | عامِل `"2026-11-03"` كتاريخ صرف. `DateTime.parse` يعطي منتصف الليل محلياً، وهذا ما تريده. التواريخ والأوقات كلها بتوقيت **الصالون** |
| عرض `day_name` / `month_name` مباشرة | ترجمها من التاريخ: `DateFormat.E(locale)` و `DateFormat.yMMMM(locale)` |
| عرض رسالة validation الإنجليزية للعميل | رسالة عامة. اعرض `message` فقط عندما `success: false` بلا `errors` |
| طلب الشهر عند كل `build()` أو كل سحب | طلب واحد عند فتح الشهر + كاش أثناء الشاشة |
| نسيان `Accept: application/json` | أضفه في `BaseOptions` |
| إعادة المحاولة فوراً عند 429 | انتظر `Retry-After` |
| الاعتماد على التقويم كضمان نهائي | الحجز قد يرجع 409 — تعامل معه كما في القسم 11 |
| افتراض أن `providers` قد تحوي مزوّداً غير متاح | كلهم متاحون، لا حاجة لفلترة |

---

## 13. Checklist قبل التسليم

- [ ] الهيدر `Accept: application/json` موجود في كل الطلبات.
- [ ] `Accept-Language` يتبع لغة التطبيق.
- [ ] أول طلب بدون `month`، والتنقل بـ `previous_month` / `next_month` فقط.
- [ ] الأزرار معطّلة عندما تكون القيمة `null`.
- [ ] الأيام قابلة للضغط فقط عند `is_available == true`.
- [ ] الأسعار تُقرأ بـ `num → toDouble()`.
- [ ] رسالة واضحة عند `available_dates` فارغة.
- [ ] رسائل 422 المنطقية تُعرض كما هي، ورسائل validation تُستبدل برسالة عامة.
- [ ] 429 ينتظر `Retry-After`.
- [ ] 409 من الحجز يعيد جلب أوقات اليوم ويحدّث التقويم.
- [ ] قيمة `reason_code` غير معروفة لا تكسر التطبيق.
- [ ] جرّبت: مزوّد في إجازة، يوم عطلة، الشهر الأخير، شهر بلا مواعيد، انقطاع الإنترنت.

---

### مرجع سريع

```
GET /api/availability/days?service_id=&provider_id=&month=YYYY-MM
GET /api/availability/days/providers?service_id=&month=YYYY-MM&branch_id=

GET /api/availability/provider?service_id=&provider_id=&date=YYYY-MM-DD   ← أوقات يوم لمزوّد
GET /api/availability/service?service_id=&date=YYYY-MM-DD                 ← أوقات يوم لكل المزوّدين
POST /api/bookings                                                        ← الحجز
```

