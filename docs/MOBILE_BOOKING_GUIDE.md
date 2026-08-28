<div dir="rtl">



---

## فهرس المحتويات

1. [الفكرة في 30 ثانية](#1-الفكرة-في-30-ثانية)
2. [Base URL و Headers](#2-base-url-و-headers)
3. [أهم تحذير في هذا الملف](#3-أهم-تحذير-في-هذا-الملف)
4. [مخطط الشاشات — State Machine](#4-مخطط-الشاشات--state-machine)
5. [الخطوة 0 — تسجيل الدخول](#5-الخطوة-0--تسجيل-الدخول)
6. [الخطوة 1 — عرض الخدمات](#6-الخطوة-1--عرض-الخدمات)
7. [الخطوة 2 — عرض المزوّدين المتاحين](#7-الخطوة-2--عرض-المزوّدين-المتاحين)
8. [الخطوة 3 — عرض المواعيد المتاحة](#8-الخطوة-3--عرض-المواعيد-المتاحة)
9. [التقويم — اختيار اليوم أولاً](#9-التقويم--اختيار-اليوم-أولاً)
10. [الخطوة 4 — إنشاء الحجز](#10-الخطوة-4--إنشاء-الحجز)
11. [حجز عدة خدمات في موعد واحد](#11-حجز-عدة-خدمات-في-موعد-واحد)
12. [إدارة الحجوزات — عرض وإلغاء](#12-إدارة-الحجوزات--عرض-وإلغاء)
13. [مرجع reason_code](#13-مرجع-reason_code)
14. [مرجع الأخطاء الكامل](#14-مرجع-الأخطاء-الكامل)
15. [قواعد العمل والقيود](#15-قواعد-العمل-والقيود)
16. [Rate Limiting](#16-rate-limiting)
17. [حالات حافة يجب التعامل معها](#17-حالات-حافة-يجب-التعامل-معها)
18. [مثال كود كامل](#18-مثال-كود-كامل)
19. [قائمة فحص قبل التسليم](#19-قائمة-فحص-قبل-التسليم)

---

## 1. الفكرة في 30 ثانية

رحلة الحجز **أربع شاشات**، وكل شاشة = طلب واحد:

```
شاشة A: قائمة الخدمات
   GET /api/services
        ↓ المستخدم اختار "قص شعر"
شاشة B: المزوّدون المتاحون + مواعيد كل واحد
   GET /api/availability/service?service_id=1&date=2026-09-09
        ↓ المستخدم اختار المزوّد
شاشة C: مواعيد هذا المزوّد (اختياري — البيانات وصلت في B)
   GET /api/availability/provider?service_id=1&provider_id=3&date=2026-09-09
        ↓ المستخدم اختار الساعة 10:00
شاشة D: تأكيد الحجز
   POST /api/bookings
        ↓
   تم — رقم الحجز APT-20260907-38AEC9
```

**ثلاث نقاط تختصر عليك وقتاً كبيراً:**

1. **`GET /api/availability/service` يعطيك المزوّدين المتاحين ومواعيدهم دفعة واحدة.** لا تحتاج طلباً منفصلاً لكل مزوّد.
2. **الخطوات 1، 2، 3 لا تحتاج `Authorization`** — يستطيع الزائر التصفّح قبل تسجيل الدخول. فقط `POST /api/bookings` يتطلب توكن.
3. **كل موعد يعرضه الـ API قابل للحجز فعلياً** — الطبقتان تطبّقان نفس القيود، فلا يحدث أن تعرض موعداً ثم يرفضه الحجز.

---

## 2. Base URL و Headers

<div dir="ltr">

```
Base URL: http://localhost:8000
```

</div>

| الحالة | الـ Headers المطلوبة |
|---|---|
| كل الطلبات | `Accept: application/json` |
| طلبات فيها body | `Content-Type: application/json` |
| الطلبات المحمية | `Authorization: Bearer {access_token}` |
| لتحديد اللغة | `Accept-Language: ar` أو `?lang=ar` |

**اللغة:** الأولوية للـ query parameter `?lang=ar` ثم الهيدر `Accept-Language`. اللغات المدعومة: `ar`, `en`, `de`.

> **توصية:** اضبط `Accept-Language` في الـ HTTP interceptor العام مرة واحدة، بدل تمريره في كل طلب.

---

## 3. أهم تحذير في هذا الملف

### لا تستخدم `service.providers` لبناء شاشة اختيار المزوّد

الحقل `providers` داخل استجابة `GET /api/services` يعني **"من مُسجَّل على هذه الخدمة إدارياً"** — وليس **"من متاح اليوم"**.

هذه استجابة حقيقية للخدمة `Hair Cut`:

<div dir="ltr">

```json
"providers": [
  { "id": 3,  "full_name": "Available Provider" },
  { "id": 4,  "full_name": "OnLeave Provider" },
  { "id": 5,  "full_name": "NotWorking Provider" },
  { "id": 6,  "full_name": "FullyBooked Provider" },
  { "id": 7,  "full_name": "InactiveUser Provider" },
  { "id": 8,  "full_name": "InactivePivot Provider" },
  { "id": 9,  "full_name": "OtherBranch Provider" },
  { "id": 11, "full_name": "HourlyLeave Provider" }
]
```

</div>

| المزوّد | حالته الحقيقية | هل يقبل الحجز؟ |
|---|---|---|
| `OnLeave` | في إجازة يوم كامل | ❌ |
| `NotWorking` | لا يعمل هذا اليوم من الأسبوع | ❌ |
| `FullyBooked` | كل مواعيده محجوزة | ❌ |
| `InactiveUser` | حسابه معطّل إدارياً | ❌ |
| `InactivePivot` | ارتباطه بهذه الخدمة موقوف | ❌ |
| `OtherBranch` | في فرع آخر | ❌ عند الفلترة بالفرع |

من هؤلاء الثمانية، **اثنان فقط** قابلان للحجز في ذلك اليوم في الفرع الأول. الباقي سيرفض الحجز بـ `422`.

> **الصواب:** استخدم `GET /api/availability/service` لبناء شاشة اختيار المزوّد. هو الوحيد الذي يفلتر حسب الإجازات وجدول الدوام والحجوزات القائمة.
>
> **الخطأ:** عرض `service.providers` كقائمة قابلة للاختيار. المستخدم سيختار مزوّداً في إجازة ثم يُصدم بخطأ عند التأكيد.

استخدم `service.providers` فقط لغرض العرض التسويقي — مثلاً «٨ حلاقين يقدّمون هذه الخدمة».

---

## 4. مخطط الشاشات — State Machine

<div dir="ltr">

```
┌──────────────────────────────────────────────┐
│ شاشة A — الخدمات                              │
│ GET /api/services                            │
│ اعرض: name, display_price, formatted_duration│
└───────────────────┬──────────────────────────┘
                    │ اختار service_id
                    ▼
┌──────────────────────────────────────────────┐
│ منتقي التاريخ (Date Picker)                   │
│ • الحد الأدنى = اليوم                         │
│ • الحد الأعلى = last_bookable_date            │
│   (اقرأه من الاستجابة، لا تكتبه ثابتاً)        │
└───────────────────┬──────────────────────────┘
                    │ اختار date
                    ▼
┌──────────────────────────────────────────────┐
│ شاشة B — المزوّدون المتاحون                   │
│ GET /api/availability/service                │
│      ?service_id=..&date=..&branch_id=..     │
│                                              │
│ total_providers == 0 ؟                       │
│   → "لا يوجد حلاق متاح في هذا اليوم"          │
│   → اقترح اليوم التالي                        │
└───────────────────┬──────────────────────────┘
                    │ اختار provider_id
                    ▼
┌──────────────────────────────────────────────┐
│ شاشة C — المواعيد                             │
│ المواعيد وصلت أصلاً في استجابة B              │
│ (اختياري: أعد الجلب من                        │
│  GET /api/availability/provider للتحديث)      │
└───────────────────┬──────────────────────────┘
                    │ اختار start_time
                    ▼
┌──────────────────────────────────────────────┐
│ مسجّل دخول؟                                   │
│   لا  → شاشة تسجيل الدخول ثم العودة إلى هنا   │
│   نعم → متابعة                                │
└───────────────────┬──────────────────────────┘
                    ▼
┌──────────────────────────────────────────────┐
│ شاشة D — التأكيد                              │
│ POST /api/bookings                           │
│                                              │
│ 201 → شاشة النجاح + رقم الحجز                 │
│ 422 → الموعد حُجز أثناء التردد                │
│       أعد جلب المواعيد وأعد العرض             │
└──────────────────────────────────────────────┘
```

</div>

---

## 5. الخطوة 0 — تسجيل الدخول

مطلوب فقط قبل `POST /api/bookings`. يمكن للمستخدم تصفّح كل شيء قبله.

<div dir="ltr">

```http
POST /api/auth/login
Content-Type: application/json
Accept: application/json

{
  "registration_method": "email",
  "email": "lina.hassan@gmail.com",
  "password": "password"
}
```

</div>

### الاستجابة — `200 OK`

<div dir="ltr">

```json
{
  "user": {
    "id": 1,
    "first_name": "Lina",
    "last_name": "Hassan",
    "full_name": "Lina Hassan",
    "email": "lina.hassan@gmail.com",
    "phone": "+4915634721911",
    "registration_method": "email",
    "is_active": true,
    "is_account_verified": true,
    "requires_otp_verification": false
  },
  "access_token": "1|3n98iPSdHZPWouQRBosw29UnPkPzUSwHJX204SpX9cd266c9",
  "access_expires_at": "2026-09-07 08:15:00",
  "refresh_token": "D9FtTMIod6brbLZg9QqjFgPMSYCQ7EDL9cqZNjVUNidX1ArEK9ZUCiJxBrD7SQgU",
  "refresh_expires_at": "2026-10-07 08:00:00",
  "token_type": "bearer",
  "email_verified": true,
  "is_account_verified": true,
  "requires_otp_verification": false
}
```

</div>

| الحقل | الاستخدام |
|---|---|
| `access_token` | أرسله في `Authorization: Bearer {token}` |
| `access_expires_at` | **قصير المدى.** جدّده بـ `refresh_token` قبل انتهائه |
| `refresh_token` | طويل المدى — `POST /api/auth/refresh` |
| `requires_otp_verification` | إذا `true` فالحساب غير مفعّل — **الحجز سيُرفض بـ 403** |

### الحساب غير المفعّل — `403`

إذا كان الحساب غير مُفعّل، الـ login يرجع `403` **بدون tokens** مع `requires_otp_verification: true`. وجّه المستخدم لشاشة إدخال الـ OTP، ولا تحاول الحجز قبل التفعيل.

---

## 6. الخطوة 1 — عرض الخدمات

<div dir="ltr">

```http
GET /api/services?per_page=15&sort_by=sort_order&sort_direction=asc
Accept: application/json
```

</div>

**Authentication:** غير مطلوب

### Query Parameters

| Parameter | Type | Required | Default | الوصف |
|---|---|---|---|---|
| `per_page` | integer | ❌ | 15 | عدد النتائج في الصفحة |
| `category_id` | integer | ❌ | — | فلترة حسب الفئة |
| `featured` | any | ❌ | — | الخدمات المميّزة فقط |
| `search` | string | ❌ | — | بحث في الاسم والوصف |
| `sort_by` | string | ❌ | `sort_order` | حقل الترتيب |
| `sort_direction` | string | ❌ | `asc` | `asc` أو `desc` |

### الاستجابة — `200 OK`

<div dir="ltr">

```json
{
  "success": true,
  "message": "Services retrieved successfully",
  "data": [
    {
      "id": 1,
      "name": "Hair Cut",
      "description": "Hair Cut",
      "price": "100.00",
      "discount_price": null,
      "display_price": "100.00",
      "duration_minutes": 60,
      "formatted_duration": "1h",
      "is_active": true,
      "image_url": null,
      "color_code": "#000000",
      "icon_url": null,
      "is_featured": false,
      "average_rating": 0,
      "has_discount": false,
      "category": { "id": 1, "name": "Hair" },
      "providers": []
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 2,
    "per_page": 2,
    "total": 3
  }
}
```

</div>

### ماذا تعرض في بطاقة الخدمة

| اعرض | الحقل | ملاحظة |
|---|---|---|
| الاسم | `name` | مترجم تلقائياً حسب لغة الطلب |
| السعر | `display_price` | **استخدم هذا لا `price`** — يراعي الخصم |
| السعر قبل الخصم | `price` | اعرضه مشطوباً فقط إذا `has_discount == true` |
| المدة | `formatted_duration` | جاهز للعرض (`"1h"`, `"30m"`, `"1h 30m"`) |
| اللون | `color_code` | لتلوين البطاقة |
| التقييم | `average_rating` | `0` يعني لا تقييمات بعد |

> **الأسعار شاملة الضريبة (GROSS).** لا تُضِف ضريبة فوقها عند العرض. تفصيل الضريبة يظهر لاحقاً في استجابة الحجز (`subtotal` + `tax_amount`).

---

## 7. الخطوة 2 — عرض المزوّدين المتاحين

**هذه هي النقطة الأهم في الرحلة كلها.**

<div dir="ltr">

```http
GET /api/availability/service?service_id=1&date=2026-09-09&branch_id=1
Accept: application/json
```

</div>

**Authentication:** غير مطلوب

### Query Parameters

| Parameter | Type | Required | الوصف |
|---|---|---|---|
| `service_id` | integer | ✅ | معرّف الخدمة المختارة |
| `date` | string | ✅ | `Y-m-d` — اليوم أو مستقبلاً، **يوم واحد فقط** |
| `branch_id` | integer | ❌ | فلترة حسب الفرع. بدونه تظهر كل الفروع |

### الاستجابة — `200 OK`

<div dir="ltr">

```json
{
  "success": true,
  "message": "Service availability retrieved successfully",
  "data": {
    "service": {
      "id": 1,
      "name": "Hair Cut",
      "duration_minutes": 60,
      "formatted_duration": "1h"
    },
    "date": "2026-09-09",
    "day_name": "Wednesday",
    "formatted_date": "Wednesday, September 09, 2026",
    "is_today": false,
    "is_tomorrow": false,
    "last_bookable_date": "2026-09-17",
    "total_providers": 2,
    "providers": [
      {
        "provider_id": 3,
        "provider_name": "Available Provider",
        "provider_avatar": null,
        "branch": {
          "id": 1,
          "name": "Main Branch",
          "address": null,
          "phone": null,
          "coordinates": { "latitude": null, "longitude": null }
        },
        "service_pricing": {
          "original_price": 100,
          "effective_price": 100,
          "has_discount": false,
          "discount_amount": 0,
          "discount_percentage": 0,
          "currency": "EUR",
          "formatted_price": "100.00 EUR"
        },
        "is_available": true,
        "reason_code": "available",
        "available_slots": [
          {
            "start_time": "09:00",
            "end_time": "10:00",
            "start_time_formatted": "09:00 AM",
            "end_time_formatted": "10:00 AM",
            "display_time": "09:00 AM",
            "duration_minutes": 60
          },
          {
            "start_time": "10:00",
            "end_time": "11:00",
            "start_time_formatted": "10:00 AM",
            "end_time_formatted": "11:00 AM",
            "display_time": "10:00 AM",
            "duration_minutes": 60
          }
        ]
      }
    ]
  }
}
```

</div>

### قواعد التعامل مع هذه الاستجابة

| القاعدة | التفصيل |
|---|---|
| **المتاحون فقط** | كل عنصر في `providers` قابل للحجز فعلياً. المزوّدون في إجازة / خارج الدوام / المحجوزون بالكامل **محذوفون تماماً** ولا يظهرون |
| **`is_available` دائماً `true`** | موجود للاتساق مع باقي الـ endpoints — لا تحتاج فحصه هنا |
| **`total_providers == 0`** | يوم مغلق أو خارج نافذة الحجز. الاستجابة تبقى `200` — اعرض «لا يوجد حلاق متاح» واقترح يوماً آخر |
| **`last_bookable_date`** | آخر تاريخ يقبله النظام. **اضبط به الحد الأعلى لمنتقي التاريخ** |
| **`available_slots` مُرتّبة** | تصاعدياً من الأقدم للأحدث |

### السعر لكل مزوّد

`service_pricing` قد **يختلف من مزوّد لآخر** لنفس الخدمة (المزوّد المخضرم قد يكون أغلى).

<div dir="ltr">

```json
"service_pricing": {
  "original_price": 100,
  "effective_price": 80,
  "has_discount": true,
  "discount_amount": 20,
  "discount_percentage": 20,
  "currency": "EUR",
  "formatted_price": "80.00 EUR"
}
```

</div>

- اعرض `formatted_price` مباشرة (جاهز مع العملة).
- إذا `has_discount == true` اعرض `original_price` مشطوباً وشارة `discount_percentage%`.

### تصميم شاشة B المقترح

<div dir="ltr">

```
┌────────────────────────────────────────────┐
│  قص شعر · 1h                    الأربعاء 9/9│
├────────────────────────────────────────────┤
│  Available Provider                         │
│  Main Branch · 100.00 EUR                   │
│  ┌──────┐┌──────┐┌──────┐┌──────┐           │
│  │09:00 ││10:00 ││11:00 ││12:00 │  …        │
│  └──────┘└──────┘└──────┘└──────┘           │
├────────────────────────────────────────────┤
│  HourlyLeave Provider                       │
│  Main Branch · 100.00 EUR                   │
│  ┌──────┐┌──────┐┌──────┐                   │
│  │09:00 ││10:00 ││14:00 │  …                │
│  └──────┘└──────┘└──────┘                   │
└────────────────────────────────────────────┘
```

</div>

> بهذا التصميم يختار المستخدم **المزوّد والموعد بنقرة واحدة**، لأن المواعيد وصلت مع القائمة — لا حاجة لطلب إضافي.

---

## 8. الخطوة 3 — عرض المواعيد المتاحة

استخدم هذه النقطة عندما يكون المزوّد **معروفاً مسبقاً** — مثلاً «احجز مع حلاقي المفضّل» أو تغيير التاريخ داخل صفحة المزوّد.

<div dir="ltr">

```http
GET /api/availability/provider?service_id=1&provider_id=3&date=2026-09-09&branch_id=1
Accept: application/json
```

</div>

**Authentication:** غير مطلوب

### Query Parameters

| Parameter | Type | Required | الوصف |
|---|---|---|---|
| `service_id` | integer | ✅ | معرّف الخدمة |
| `provider_id` | integer | ✅ | معرّف المزوّد |
| `date` | string | ✅ | `Y-m-d` — اليوم أو مستقبلاً |
| `branch_id` | integer | ❌ | معرّف الفرع |

### الاستجابة — مزوّد متاح

<div dir="ltr">

```json
{
  "success": true,
  "message": "Provider availability retrieved successfully",
  "data": {
    "is_available": true,
    "reason_code": "available",
    "unavailable_reason": null,
    "leave_start_date": null,
    "leave_end_date": null,
    "provider": {
      "id": 3,
      "name": "Available Provider",
      "avatar": null,
      "phone": "+4915962460972",
      "branch": {
        "id": 1,
        "name": "Main Branch",
        "address": null,
        "phone": null,
        "coordinates": { "latitude": null, "longitude": null }
      }
    },
    "service": {
      "id": 1,
      "name": "Hair Cut",
      "duration_minutes": 60,
      "formatted_duration": "1h"
    },
    "pricing": {
      "original_price": 100,
      "effective_price": 100,
      "has_discount": false,
      "currency": "EUR",
      "formatted_price": "100.00 EUR"
    },
    "date": "2026-09-09",
    "day_name": "Wednesday",
    "formatted_date": "Wednesday, September 09, 2026",
    "total_slots": 8,
    "available_slots": [
      {
        "start_time": "09:00",
        "end_time": "10:00",
        "start_time_formatted": "09:00 AM",
        "end_time_formatted": "10:00 AM",
        "display_time": "09:00 AM",
        "duration_minutes": 60
      }
    ]
  }
}
```

</div>

### الاستجابة — مزوّد في إجازة

<div dir="ltr">

```json
{
  "success": true,
  "data": {
    "is_available": false,
    "reason_code": "on_leave",
    "unavailable_reason": "Provider 'OnLeave Provider' is on leave on 2026-09-09",
    "leave_start_date": "2026-09-09",
    "leave_end_date": "2026-09-09",
    "provider": { "id": 4, "name": "OnLeave Provider" },
    "total_slots": 0,
    "available_slots": []
  }
}
```

</div>

### قاعدة حاسمة

> **الاستجابة تكون `200 OK` دائماً — حتى لو كان المزوّد غير متاح تماماً.**
>
> **لا تعتمد على status code وحده. افحص `is_available` دائماً.**

<div dir="ltr">

```dart
if (data['is_available'] == false) {
  showEmptyState(data['reason_code']);   // لا تعرض أي مواعيد
  return;
}
renderSlots(data['available_slots']);
```

</div>

### حقول الإجازة

عندما يكون `reason_code == "on_leave"` تصلك `leave_start_date` و `leave_end_date`. استخدمهما لرسالة مفيدة:

> «الحلاق في إجازة من 9 إلى 15 سبتمبر — أقرب موعد متاح 16 سبتمبر»

بدل رسالة عامة مثل «غير متاح».

---

## 9. التقويم — اختيار اليوم أولاً

إذا كان تصميمك يبدأ بتقويم شهري ثم اختيار الموعد:

<div dir="ltr">

```http
GET /api/availability/calendar?service_id=1&provider_id=3&start_date=2026-09-09&end_date=2026-09-13
```

</div>

| Parameter | Type | Required | الوصف |
|---|---|---|---|
| `service_id` | integer | ✅ | معرّف الخدمة |
| `provider_id` | integer | ❌ | بدونه: توفّر **كل** المزوّدين مجمّعاً |
| `start_date` | string | ✅ | `Y-m-d` |
| `end_date` | string | ✅ | `Y-m-d` — **الحد الأقصى 31 يوماً** |
| `branch_id` | integer | ❌ | معرّف الفرع |

### الاستجابة

<div dir="ltr">

```json
{
  "success": true,
  "message": "Availability calendar retrieved successfully",
  "data": {
    "service_id": 1,
    "provider_id": 3,
    "period": {
      "start_date": "2026-09-09",
      "end_date": "2026-09-13",
      "month_name": "September 2026"
    },
    "calendar": [
      {
        "date": "2026-09-09",
        "day_name": "Wed",
        "day_number": 9,
        "is_today": false,
        "is_available": true,
        "reason_code": "available",
        "available_slots_count": 8
      },
      {
        "date": "2026-09-10",
        "day_name": "Thu",
        "day_number": 10,
        "is_today": false,
        "is_available": false,
        "reason_code": "not_working_day",
        "available_slots_count": 0
      }
    ]
  }
}
```

</div>

**كيف ترسم التقويم:**

| `is_available` | العرض |
|---|---|
| `true` | يوم قابل للنقر + شارة `available_slots_count` |
| `false` | **معطّل (disabled)** — لا تجعله قابلاً للنقر |

> **انتبه للتكلفة:** التقويم **بدون** `provider_id` هو أثقل طلب في الـ API (يضرب تكلفة اليوم في عدد الأيام وعدد المزوّدين). حدّه **30 طلباً/دقيقة** فقط. لا تستدعِه عند كل تمرير للشهر — خبّئ النتيجة.

---

## 10. الخطوة 4 — إنشاء الحجز

<div dir="ltr">

```http
POST /api/bookings
Authorization: Bearer {access_token}
Content-Type: application/json
Accept: application/json

{
  "date": "2026-09-09",
  "payment_method": "cash",
  "notes": "أفضل قصة قصيرة من الجانبين",
  "services": [
    {
      "service_id": 1,
      "provider_id": 3,
      "start_time": "10:00"
    }
  ]
}
```

</div>

**Authentication:** Bearer Token + **حساب مُفعّل**

### حقول الطلب

| الحقل | النوع | مطلوب | الوصف |
|---|---|---|---|
| `date` | string | ✅ | `Y-m-d` — اليوم أو مستقبلاً |
| `payment_method` | string | ✅ | `cash` أو `online` فقط |
| `notes` | string | ❌ | ملاحظات، حد أقصى 1000 حرف |
| `services` | array | ✅ | من 1 إلى 10 خدمات، بلا تكرار |
| `services[].service_id` | integer | ✅ | من الخطوة 1 |
| `services[].provider_id` | integer | ✅ | من الخطوة 2 |
| `services[].start_time` | string | ✅ | **`H:i` بالضبط** — `"10:00"` وليس `"10:00:00"` |

> `start_time` بصيغة `H:i` حصراً. إرسال `"10:00:00"` يُرفض بـ `422`.

### الاستجابة — `201 Created`

<div dir="ltr">

```json
{
  "success": true,
  "message": "Booking created successfully",
  "data": {
    "id": 9,
    "number": "APT-20260907-38AEC9",
    "appointment_date": "2026-09-09",
    "formatted_date": "Sep 09, 2026",
    "start_time": "10:00",
    "end_time": "11:00",
    "time_range": "10:00 AM - 11:00 AM",
    "duration_minutes": 60,
    "formatted_duration": "1h",
    "subtotal": 84.03,
    "tax_amount": 15.97,
    "total_amount": 100,
    "status": "PENDING",
    "status_value": 0,
    "status_label": "Pending",
    "payment_status": "PAID_ONSTIE_CASH",
    "payment_status_value": 2,
    "payment_status_label": "Paid On site Cash",
    "payment_method": "cash",
    "cancellation_reason": null,
    "cancelled_at": null,
    "provider": {
      "id": 3,
      "full_name": "Available Provider",
      "email": "pearl.howell@example.org",
      "phone": "+4915962460972",
      "avatar_url": null,
      "profile_image_url": null
    },
    "services_details": [
      {
        "id": 1,
        "service_id": 1,
        "service_name": "Hair Cut",
        "duration_minutes": 60,
        "formatted_duration": "1h",
        "price": 100,
        "formatted_price": "100.00",
        "sequence_order": 1
      }
    ],
    "booking_source": "online",
    "notes": "أفضل قصة قصيرة من الجانبين",
    "created_at": "2026-09-07 08:00:00",
    "updated_at": "2026-09-07 08:00:00",
    "is_upcoming": true,
    "is_past": false,
    "is_cancelled": false,
    "is_completed": false,
    "can_cancel": true
  }
}
```

</div>

### الحقول المهمة في شاشة النجاح

| الحقل | الاستخدام |
|---|---|
| `number` | **رقم الحجز المرجعي** — اعرضه كبيراً، هو ما يقوله الزبون في الصالون |
| `time_range` | جاهز للعرض: `"10:00 AM - 11:00 AM"` |
| `formatted_date` | جاهز للعرض: `"Sep 09, 2026"` |
| `total_amount` | المبلغ الإجمالي شامل الضريبة |
| `subtotal` / `tax_amount` | تفصيل الضريبة — اعرضه في ملخّص الفاتورة |
| `can_cancel` | فعّل/عطّل زر الإلغاء بناءً عليه |

### تأثير `payment_method`

| القيمة | `payment_status` | المعنى |
|---|---|---|
| `cash` | `PAID_ONSTIE_CASH` (2) | الحجز **مؤكَّد فوراً**، الدفع نقداً عند الحضور |
| `online` | `PENDING` (0) | الحجز **معلّق** بانتظار الدفع |

> **مهم:** حجز `online` بحالة `PENDING` **لا يحجز الوقت بشكل مؤكَّد** في فحص التعارض — قد يحجزه زبون آخر. إذا لم تكن بوّابة الدفع جاهزة في تطبيقك، استخدم `cash`.

---

## 11. حجز عدة خدمات في موعد واحد

يمكن حجز حتى **10 خدمات** في موعد واحد، وكل خدمة لها **مزوّدها ووقتها الخاص**.

<div dir="ltr">

```json
{
  "date": "2026-09-09",
  "payment_method": "online",
  "services": [
    { "service_id": 1, "provider_id": 11, "start_time": "09:00" },
    { "service_id": 2, "provider_id": 3,  "start_time": "14:00" }
  ]
}
```

</div>

### قواعد التسلسل

1. الخدمات تُرتَّب تلقائياً حسب `start_time` — لا يهم ترتيب إرسالها.
2. كل خدمة يجب أن تبدأ **بعد أو مع** نهاية سابقتها. `end_time = start_time + duration_minutes`.
3. أي تداخل → `422`.
4. لا يمكن تكرار نفس `service_id` مرتين في نفس الطلب.
5. **يجب التحقق من توفّر كل خدمة على حدة** عبر `/availability/provider` قبل الإرسال.

### الاستجابة — `201`

<div dir="ltr">

```json
{
  "data": {
    "id": 10,
    "number": "APT-20260907-D21075",
    "start_time": "09:00",
    "end_time": "15:00",
    "time_range": "09:00 AM - 03:00 PM",
    "duration_minutes": 120,
    "subtotal": 126.05,
    "tax_amount": 23.95,
    "total_amount": 150,
    "payment_status": "PENDING",
    "provider": { "id": 11, "full_name": "HourlyLeave Provider" },
    "services_details": [
      {
        "service_id": 1, "service_name": "Hair Cut",
        "duration_minutes": 60, "price": 100, "sequence_order": 1
      },
      {
        "service_id": 2, "service_name": "Beard Trim",
        "duration_minutes": 60, "price": 50, "sequence_order": 2
      }
    ]
  }
}
```

</div>

### مفاجأتان في الحجز متعدد الخدمات

انظر المثال أعلاه بدقة:

**1. `duration_minutes` ليس الفرق بين `start_time` و `end_time`.**

الموعد يمتد من `09:00` إلى `15:00` (ست ساعات)، لكن `duration_minutes = 120` (ساعتان) — لأن `duration_minutes` هو **مجموع مدد الخدمات** لا المدة الزمنية الممتدة. الفجوة بين 10:00 و14:00 وقت فراغ للزبون.

> **للعرض:** استخدم `time_range` لإظهار الامتداد الزمني، و `duration_minutes` لإظهار «وقت الخدمة الفعلي». **لا تحسب المدة بطرح الوقتين.**

**2. `provider` في الجذر هو مزوّد الخدمة الأولى فقط.**

في المثال، الخدمة الثانية مع المزوّد `3` لكن `data.provider.id = 11`. إذا اختلف المزوّدون:

> **اعرض المزوّد من `services_details[]` لكل خدمة على حدة**، ولا تعرض `data.provider` كـ«مزوّد الموعد» — سيكون مضلّلاً.

> **توصية تصميمية:** إن أمكن، اجعل الحجز متعدد الخدمات **بمزوّد واحد ومواعيد متلاصقة**. تجربة أبسط للمستخدم وأوضح في العرض.

---

## 12. إدارة الحجوزات — عرض وإلغاء

جميع هذه النقاط تتطلب `Authorization` + حساب مُفعّل.

### قائمة حجوزاتي

<div dir="ltr">

```http
GET /api/bookings
GET /api/bookings?status=0
Authorization: Bearer {token}
```

</div>

| `status` | المعنى |
|---|---|
| `0` | `PENDING` — قيد الانتظار |
| `1` | `COMPLETED` — مكتمل |
| `-1` | `USER_CANCELLED` — ألغاه العميل |
| `-2` | `ADMIN_CANCELLED` — ألغته الإدارة |
| `-3` | `NO_SHOW` — لم يحضر |
| (بدون) | كل الحجوزات |

النتائج مرتّبة من الأحدث للأقدم، وكل عنصر بنفس شكل الاستجابة في القسم 10.

### تفاصيل حجز واحد

<div dir="ltr">

```http
GET /api/bookings/{id}
Authorization: Bearer {token}
```

</div>

| الحالة | الكود |
|---|---|
| نجاح | `200` |
| الحجز ليس ملك المستخدم | `403` |
| الحجز غير موجود | `500` — *خطأ معروف في الـ backend، يُفترض أن يكون `404`* |

### إلغاء حجز

<div dir="ltr">

```http
POST /api/bookings/{id}/cancel
Authorization: Bearer {token}
Content-Type: application/json

{
  "cancellation_reason": "تغيير في الخطط"
}
```

</div>

الاستجابة — `200 OK`:

<div dir="ltr">

```json
{
  "success": true,
  "message": "Booking cancelled successfully",
  "data": {
    "id": 9,
    "number": "APT-20260907-38AEC9",
    "status": "USER_CANCELLED",
    "status_value": -1,
    "status_label": "User Cancelled",
    "cancellation_reason": "تغيير في الخطط",
    "is_cancelled": true,
    "can_cancel": false
  }
}
```

</div>

**قواعد الإلغاء:**

- فقط الحجوزات بحالة `PENDING (0)` قابلة للإلغاء.
- الحقل `can_cancel` يخبرك مباشرة — **استخدمه لإظهار/إخفاء زر الإلغاء** بدل حساب الشرط بنفسك.
- محاولة إلغاء حجز مُلغى أو مكتمل → `422`.

---

## 13. مرجع reason_code

القيمة الوحيدة التي تعني «متاح» هي `available`. كل ما عداها يعني **لا تعرض أي مواعيد**.

| القيمة | المعنى | ماذا تعرض للمستخدم |
|---|---|---|
| `available` | متاح وهناك مواعيد شاغرة | المواعيد |
| `on_leave` | **إجازة يوم كامل** في هذا التاريخ | «في إجازة حتى {leave_end_date}» |
| `not_working_day` | لا يوجد جدول عمل في هذا اليوم من الأسبوع | «لا يعمل يوم {day_name}» |
| `fully_booked` | يوم عمل عادي لكن كل المواعيد محجوزة | «كل المواعيد محجوزة — جرّب يوماً آخر» |
| `outside_booking_window` | التاريخ أبعد من الحد المسموح | «الحجز متاح حتى {last_bookable_date}» |

### فروق مهمة

**`on_leave` ≠ `fully_booked`:** الأولى إجازة مُعلنة (اعرض تاريخ العودة)، الثانية ازدحام (اقترح يوماً قريباً).

**`outside_booking_window` ليس «محجوز»:** التاريخ ببساطة خارج النافذة المسموحة. يجب أن يظهر **معطّلاً في منتقي التاريخ** لا كيوم ممتلئ. استخدم `last_bookable_date` لضبط حد المنتقي مسبقاً فلا يصل المستخدم لهذه الحالة أصلاً.

**الإجازة الساعية (hourly leave) لا تُنتج `reason_code` خاصاً:** المزوّد يبقى `available` وتُحذف المواعيد المتداخلة فقط. مثال حقيقي — إجازة من 11:00 إلى 14:00 على دوام 09:00–17:00:

<div dir="ltr">

```
09:00 ✅   10:00 ✅   11:00 ❌   12:00 ❌   13:00 ❌   14:00 ✅   15:00 ✅   16:00 ✅
```

</div>

إذا غطّت الإجازة الساعية اليوم كاملاً، يصبح `is_available = false` مع `reason_code = fully_booked`.

---

## 14. مرجع الأخطاء الكامل

### شكل خطأ الـ Validation — `422`

يحدث عند خطأ في **صيغة** البيانات. يميّزه وجود مفتاح `errors`:

<div dir="ltr">

```json
{
  "success": false,
  "message": "بيانات غير صحيحة",
  "errors": {
    "services": ["يجب تحديد خدمة واحدة على الأقل"],
    "date": ["تاريخ الحجز يجب أن يكون بصيغة Y-m-d"],
    "payment_method": ["طريقة الدفع يجب أن تكون cash أو online"]
  },
  "error_type": "validation_error"
}
```

</div>

> اربط كل مفتاح في `errors` بحقل النموذج المقابل. المفاتيح المتداخلة تأتي بالشكل `services.0.start_time`.

### شكل خطأ قواعد العمل — `422`

البيانات صحيحة الصيغة لكنها تخالف قاعدة عمل. **لا يحتوي `errors`** — فقط `message` جاهزة للعرض:

<div dir="ltr">

```json
{
  "success": false,
  "message": "Time slot 10:00 - 11:00 is already booked for provider 'Available Provider'",
  "error_type": "validation_error"
}
```

</div>

### قاعدة التمييز بين النوعين

<div dir="ltr">

```dart
if (status == 422) {
  if (body['errors'] != null) {
    bindFieldErrors(body['errors']);     // خطأ صيغة → تحت الحقول
  } else {
    showBanner(body['message']);         // خطأ قاعدة عمل → رسالة عامة
  }
}
```

</div>

### كل رسائل قواعد العمل المحتملة

| الرسالة | السبب | ماذا تفعل |
|---|---|---|
| `Time slot 10:00 - 11:00 is already booked for provider 'X'` | حُجز الوقت أثناء تردّد المستخدم | **أعد جلب المواعيد** واعرضها |
| `Provider 'X' does not offer service 'Y'` | المزوّد لا يقدّم الخدمة | خطأ برمجي — راجع مصدر `provider_id` |
| `Provider 'X' is not active` | حساب المزوّد معطّل | أعد جلب قائمة المزوّدين |
| `Service 'Y' is not active` | الخدمة أُوقفت | أعد جلب قائمة الخدمات |
| `Provider 'X' does not work on Wednesday` | لا جدول عمل في هذا اليوم | أعد جلب المواعيد |
| `Time slot is outside provider's working hours (09:00 - 17:00)` | الوقت خارج الدوام | خطأ برمجي — أرسل وقتاً من `available_slots` |
| `Provider 'X' is not available on 2026-09-09` | إجازة يوم كامل | أعد جلب المزوّدين |
| `Provider has time off during the requested time slot` | إجازة ساعية | أعد جلب المواعيد |
| `Booking must be at least 60 minutes in advance` | الوقت أقرب من `book_buffer` | أعد جلب المواعيد |
| `Cannot book more than 10 days in advance` | تجاوز `max_booking_days` | اضبط حد منتقي التاريخ |
| `Cannot book in the past` | تاريخ ماضٍ | امنعه في الواجهة |
| `Maximum 10 services per booking` | أكثر من 10 خدمات | امنعه في الواجهة |
| `Duplicate services are not allowed in the same booking` | خدمة مكررة | امنعه في الواجهة |
| `Maximum 10 bookings per day reached` | تجاوز `max_daily_bookings` | «وصلت للحد اليومي» |
| `You already have a booking for the same time and services` | حجز مطابق موجود | وجّهه لحجزه الحالي |
| `Service at position 2 start time must be after previous service end time` | تداخل الخدمات | أعد ترتيب/تعديل الأوقات |

### جدول أكواد HTTP

| الكود | المعنى | ما يفعله التطبيق |
|---|---|---|
| `200` | نجاح | — |
| `201` | أُنشئ الحجز | شاشة النجاح |
| `400` | معامل غير منطقي (مثلاً المزوّد لا يقدّم الخدمة) | اعرض `message` |
| `401` | لا توكن أو انتهت صلاحيته | جدّد بـ refresh أو أعده لتسجيل الدخول |
| `403` | حساب غير مفعّل، أو حجز ليس ملكه | شاشة OTP أو رسالة صلاحية |
| `422` | خطأ صيغة أو قاعدة عمل | راجع أعلاه |
| `429` | تجاوز الـ Rate Limit | انتظر `Retry-After` |
| `500` | خطأ سيرفر | «حدث خطأ، حاول لاحقاً» |

---

## 15. قواعد العمل والقيود

الإعدادات التالية يضبطها مدير الصالون من لوحة التحكم وقد **تتغير دون إصدار تطبيق جديد**:

| الإعداد | الافتراضي | الأثر على التطبيق |
|---|---|---|
| `book_buffer` | 60 دقيقة | أقل مهلة بين الآن وبدء الموعد. المواعيد الأقرب **محذوفة أصلاً** من `available_slots` |
| `max_booking_days` | 10 أيام | أقصى مدى للحجز. متاح لك كـ `last_bookable_date` |
| `max_services_per_booking` | 10 | أقصى عدد خدمات في الطلب الواحد |
| `max_daily_bookings` | 10 | أقصى عدد حجوزات للعميل في اليوم |
| `tax_rate` | 19% | تُستخرج من السعر (السعر شامل الضريبة) |

> **لا تكتب هذه القيم ثابتة في التطبيق.** اقرأ `last_bookable_date` من الاستجابة، واترك الباقي للسيرفر يفرضه.

### ضمانة الاتساق

كل موعد يظهر في `available_slots` **يحترم `book_buffer` و `max_booking_days` مسبقاً** — فما يُعرض يُقبل.

**لكن ثلاث قواعد تخصّ الطلب نفسه ولا يستطيع التوفّر التنبؤ بها:**

1. `max_daily_bookings` — حد العميل اليومي.
2. منع تكرار نفس الخدمة في الطلب.
3. تسلسل الخدمات في الحجز متعدد الخدمات.

> تعامل مع `422` من `POST /bookings` كأمر طبيعي دائماً، حتى لو تحققت من كل شيء.

### الأسعار والضريبة

- كل الأسعار **شاملة الضريبة (GROSS)**.
- الضريبة تُستخرج عكسياً: `net = gross ÷ 1.19` ثم `tax = gross − net`.
- مثال حقيقي: `100.00` → `subtotal: 84.03` + `tax_amount: 15.97`.
- **لا تحسب الضريبة في التطبيق** — اعرض `subtotal` و `tax_amount` كما يرسلهما السيرفر.

---

## 16. Rate Limiting

نقاط التوفّر عامة (بلا مصادقة) ومكلفة على السيرفر، لذلك عليها حد **لكل عنوان IP**:

| Endpoint | الحد |
|---|---|
| `GET /availability/service` | **40** / دقيقة |
| `GET /availability/provider` | **40** / دقيقة |
| `GET /availability/calendar` | **30** / دقيقة |

**الحدود مستقلة لكل مسار** — استهلاك حد التقويم لا يؤثر على المسارين الآخرين.

كل استجابة تحمل:

<div dir="ltr">

```
X-RateLimit-Limit: 40
X-RateLimit-Remaining: 27
```

</div>

عند التجاوز — `429`:

<div dir="ltr">

```json
{ "message": "Too Many Attempts." }
```

</div>

مع هيدر `Retry-After` بالثواني.

### كيف لا تصل للحد

- **debounce** بـ ~300ms على منتقي التاريخ — لا تستدعِ عند كل ضغطة.
- **خبّئ** نتيجة كل يوم محلياً لدقيقة على الأقل (السيرفر يخبّئها دقيقة أيضاً).
- **لا تجلب** التقويم لعدة خدمات بالتوازي عند فتح الشاشة.
- عند `429` اعرض رسالة لطيفة وأعد المحاولة بعد `Retry-After` — **لا تعد المحاولة في حلقة**.

> **مهم:** شركات الاتصالات تضع آلاف المستخدمين خلف عنوان IP واحد (CGNAT). حد الـ 40 قد يُستهلك بمستخدمين آخرين على نفس الشبكة، لا بمستخدمك وحده. **تعامل مع `429` كحالة متوقعة لا كخطأ نادر.**

---

## 17. حالات حافة يجب التعامل معها

### 17.1 المواعيد تتغير بين العرض والتأكيد

بين لحظة عرض المواعيد ولحظة الضغط على «تأكيد»، قد يحجز زبون آخر نفس الموعد.

<div dir="ltr">

```
POST /bookings → 422 "Time slot ... is already booked"
   ↓
1. لا تُظهر الخطأ الخام كنافذة مخيفة
2. أعد جلب المواعيد للمزوّد ونفس التاريخ
3. اعرض: «هذا الموعد حُجز للتو، إليك المواعيد المتاحة الآن»
4. أعد عرض القائمة المحدّثة
```

</div>

> **لا تعتمد على cache قديم عند التأكيد.** إن مرّ أكثر من دقيقة على جلب المواعيد، أعد الجلب قبل عرض شاشة التأكيد.

### 17.2 اليوم الحالي مختلف عن باقي الأيام

عند `date = اليوم`:

- المواعيد التي **مضت** محذوفة تلقائياً.
- المواعيد ضمن `book_buffer` محذوفة أيضاً.
- لذلك قد يكون `total_slots = 0` ليوم عمل عادي — و `reason_code` سيكون `fully_booked`.

> **لا تفترض** أن يوم عمل يعني وجود مواعيد. افحص `available_slots` دائماً.

### 17.3 المستخدم غير مسجّل دخول

الخطوات 1-3 لا تحتاج توكن. **اجعله يتصفّح بحرية**، واطلب تسجيل الدخول **فقط** عند الضغط على «تأكيد الحجز».

> **احفظ اختياره** (`service_id` + `provider_id` + `date` + `start_time`) قبل توجيهه لتسجيل الدخول، وأعده لشاشة التأكيد بعدها — لا تُفقده اختياره.

### 17.4 الحساب غير مفعّل

المستخدم قد يكون مسجّل دخول لكن حسابه غير مُفعّل. `POST /bookings` سيرجع `403` مع `requires_otp_verification: true`.

**تعامل:** افحص `is_account_verified` في استجابة الـ login مسبقاً، ووجّهه لشاشة الـ OTP **قبل** أن يقطع رحلة الحجز كاملة.

### 17.5 صيغة الوقت والتاريخ

| الحقل | الصيغة | خطأ شائع |
|---|---|---|
| `date` | `Y-m-d` → `2026-09-09` | إرسال `09-09-2026` |
| `start_time` | `H:i` → `10:00` | إرسال `10:00:00` أو `10:00 AM` |

> **أرسل دائماً القيمة الخام `start_time` من `available_slots` كما هي.** الحقول `*_formatted` و `display_time` **للعرض فقط** — لا ترسلها للسيرفر أبداً.

### 17.6 المنطقة الزمنية

كل الأوقات بتوقيت **الصالون** لا بتوقيت جهاز المستخدم.

> **لا تحوّل** `start_time` إلى التوقيت المحلي للجهاز. اعرضها كما هي. زبون في السعودية يحجز في صالون بألمانيا يجب أن يرى توقيت الصالون.

### 17.7 حجز `online` لا يحجز الوقت فعلياً

الحجز بـ `payment_method: "online"` يُنشأ بحالة `PENDING`، وهذا **لا يمنع** زبوناً آخر من حجز نفس الوقت.

> إذا لم تكن بوّابة الدفع مُنفَّذة في تطبيقك، **استخدم `cash`** لضمان تثبيت الموعد.

---

## 18. مثال كود كامل

### TypeScript / React Native

<div dir="ltr">

```ts
const API = `${BASE_URL}/api`;

// ─────────────────────────── أنواع البيانات

interface Slot {
  start_time: string;            // "10:00"  ← هذا ما تُرسله
  end_time: string;              // "11:00"
  start_time_formatted: string;  // "10:00 AM"  ← هذا ما تعرضه
  display_time: string;
  duration_minutes: number;
}

interface Pricing {
  original_price: number;
  effective_price: number;
  has_discount: boolean;
  discount_percentage: number;
  currency: string;
  formatted_price: string;       // "100.00 EUR"  ← جاهز للعرض
}

interface AvailableProvider {
  provider_id: number;
  provider_name: string;
  provider_avatar: string | null;
  branch: { id: number; name: string };
  service_pricing: Pricing;
  is_available: boolean;
  reason_code: string;
  available_slots: Slot[];
}

type ReasonCode =
  | 'available'
  | 'on_leave'
  | 'not_working_day'
  | 'fully_booked'
  | 'outside_booking_window';

// ─────────────────────────── طبقة الشبكة

async function api<T>(path: string, init: RequestInit = {}, token?: string): Promise<T> {
  const res = await fetch(`${API}${path}`, {
    ...init,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'Accept-Language': currentLocale,
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...init.headers,
    },
  });

  const body = await res.json();

  if (!res.ok) {
    throw Object.assign(new Error(body.message ?? 'Request failed'), {
      status: res.status,
      body,
      retryAfter: Number(res.headers.get('Retry-After')) || undefined,
    });
  }

  return body as T;
}

// ─────────────────────────── الخطوة 1: الخدمات

const listServices = () => api<{ data: any[] }>('/services?per_page=50');

// ─────────────────────────── الخطوة 2: المزوّدون المتاحون

async function findAvailableProviders(serviceId: number, date: string, branchId?: number) {
  const qs = new URLSearchParams({
    service_id: String(serviceId),
    date,
    ...(branchId ? { branch_id: String(branchId) } : {}),
  });

  const { data } = await api<{ data: {
    total_providers: number;
    last_bookable_date: string;
    providers: AvailableProvider[];
  } }>(`/availability/service?${qs}`);

  // اضبط حد منتقي التاريخ من الاستجابة، لا من قيمة ثابتة
  datePicker.setMaxDate(data.last_bookable_date);

  return data;   // providers فيها المتاحون فقط — اعرضها كما هي
}

// ─────────────────────────── الخطوة 3: مواعيد مزوّد بعينه

async function getProviderSlots(serviceId: number, providerId: number, date: string) {
  const qs = new URLSearchParams({
    service_id: String(serviceId),
    provider_id: String(providerId),
    date,
  });

  const { data } = await api<{ data: {
    is_available: boolean;
    reason_code: ReasonCode;
    leave_end_date: string | null;
    total_slots: number;
    available_slots: Slot[];
  } }>(`/availability/provider?${qs}`);

  // الاستجابة 200 حتى لو كان غير متاح — افحص is_available دائماً
  if (!data.is_available) {
    return { slots: [], reason: data.reason_code, leaveEnd: data.leave_end_date };
  }

  return { slots: data.available_slots, reason: 'available' as const, leaveEnd: null };
}

// ─────────────────────────── الخطوة 4: الحجز

async function createBooking(params: {
  token: string;
  date: string;
  paymentMethod: 'cash' | 'online';
  notes?: string;
  services: Array<{ service_id: number; provider_id: number; start_time: string }>;
}) {
  return api<{ data: any }>(
    '/bookings',
    {
      method: 'POST',
      body: JSON.stringify({
        date: params.date,
        payment_method: params.paymentMethod,
        notes: params.notes ?? null,
        services: params.services,
      }),
    },
    params.token,
  );
}

// ─────────────────────────── معالجة الأخطاء

function handleBookingError(err: any) {
  const { status, body, retryAfter } = err;

  // خطأ صيغة → اربطه بحقول النموذج
  if (status === 422 && body?.errors) {
    return { type: 'fieldErrors' as const, fields: body.errors };
  }

  // خطأ قاعدة عمل → الرسالة جاهزة للعرض
  if (status === 422) {
    const stale = /already booked|not available|time off|does not work|in advance/i
      .test(body.message);

    return stale
      ? { type: 'refetchSlots' as const, message: body.message }
      : { type: 'banner' as const, message: body.message };
  }

  if (status === 403) return { type: 'verifyAccount' as const, message: body.message };
  if (status === 401) return { type: 'reAuth' as const };
  if (status === 429) return { type: 'cooldown' as const, seconds: retryAfter ?? 60 };

  return { type: 'banner' as const, message: 'حدث خطأ، حاول مرة أخرى.' };
}

// ─────────────────────────── الرحلة كاملة

async function bookingJourney() {
  // A — الخدمات
  const { data: services } = await listServices();
  const service = await userPicksService(services);

  // منتقي التاريخ
  const date = await userPicksDate();

  // B — المزوّدون المتاحون
  const availability = await findAvailableProviders(service.id, date, branchId);

  if (availability.total_providers === 0) {
    return showEmptyState('لا يوجد حلاق متاح في هذا اليوم');
  }

  const provider = await userPicksProvider(availability.providers);

  // C — الموعد (المواعيد وصلت أصلاً مع المزوّد)
  const slot = await userPicksSlot(provider.available_slots);

  // تسجيل الدخول عند الحاجة فقط — مع حفظ الاختيار
  let token = auth.getToken();
  if (!token) {
    saveDraft({ service, provider, date, slot });
    token = await goToLoginAndReturn();
  }

  // أعد جلب المواعيد إن مرّ وقت طويل على العرض
  if (Date.now() - availability.fetchedAt > 60_000) {
    const fresh = await getProviderSlots(service.id, provider.provider_id, date);
    if (!fresh.slots.some(s => s.start_time === slot.start_time)) {
      return showSlotTakenAndRefresh(fresh.slots);
    }
  }

  // D — التأكيد
  try {
    const { data: booking } = await createBooking({
      token,
      date,
      paymentMethod: 'cash',
      notes: userNotes,
      services: [{
        service_id: service.id,
        provider_id: provider.provider_id,
        start_time: slot.start_time,   // ← القيمة الخام لا المنسّقة
      }],
    });

    showSuccess(booking.number, booking.time_range, booking.total_amount);
  } catch (err) {
    const action = handleBookingError(err);

    if (action.type === 'refetchSlots') {
      const fresh = await getProviderSlots(service.id, provider.provider_id, date);
      showSlotTakenAndRefresh(fresh.slots, action.message);
    } else {
      applyAction(action);
    }
  }
}
```

</div>

---

## 19. قائمة فحص قبل التسليم

**اختيار المزوّد**
- [ ] شاشة اختيار المزوّد مبنية على `/availability/service` وليس على `service.providers`
- [ ] `total_providers == 0` يعرض حالة فارغة مفيدة مع اقتراح يوم آخر
- [ ] `is_available` مفحوص في **كل** استجابة توفّر (الاستجابة `200` دائماً)
- [ ] `reason_code` يُترجم لرسالة مفهومة، و `on_leave` يعرض `leave_end_date`

**التاريخ والوقت**
- [ ] حد منتقي التاريخ الأعلى = `last_bookable_date` **مقروءاً من الاستجابة**
- [ ] `date` تُرسل بصيغة `Y-m-d`
- [ ] `start_time` تُرسل بصيغة `H:i` من الحقل الخام `start_time`
- [ ] لا يوجد تحويل للمنطقة الزمنية على أوقات الصالون
- [ ] الحقول `*_formatted` و `display_time` مستخدمة **للعرض فقط**

**الحجز**
- [ ] `payment_method` إما `cash` أو `online` فقط
- [ ] التصفّح متاح بلا تسجيل دخول، والتوكن مطلوب عند التأكيد فقط
- [ ] اختيار المستخدم محفوظ قبل توجيهه لتسجيل الدخول
- [ ] `is_account_verified` مفحوص قبل بدء رحلة الحجز
- [ ] رقم الحجز `number` معروض بوضوح في شاشة النجاح
- [ ] `can_cancel` يتحكم في ظهور زر الإلغاء

**الأخطاء**
- [ ] التمييز بين `422` مع `errors` و `422` بلا `errors`
- [ ] `422` بسبب موعد محجوز → إعادة جلب المواعيد لا رسالة خطأ خام
- [ ] `429` معالَج مع `Retry-After` وبلا إعادة محاولة في حلقة
- [ ] `403` يوجّه لشاشة تفعيل الحساب

**الأداء**
- [ ] debounce على منتقي التاريخ (~300ms)
- [ ] تخبئة نتيجة اليوم محلياً لدقيقة على الأقل
- [ ] إعادة جلب المواعيد إن مرّ أكثر من دقيقة قبل شاشة التأكيد
- [ ] لا استدعاءات متوازية للتقويم عند فتح الشاشة
- [ ] `Accept-Language` مُرسل في كل الطلبات عبر interceptor عام

**الحجز متعدد الخدمات (إن كان مدعوماً)**
- [ ] `duration_minutes` **لا** يُحسب بطرح `start_time` من `end_time`
- [ ] المزوّد يُعرض من `services_details[]` لا من `data.provider` عند اختلاف المزوّدين
- [ ] كل خدمة يُتحقق من توفّرها على حدة قبل الإرسال

---

## ملحق — ملخّص النقاط

| # | الطريقة | المسار | مصادقة | Rate Limit |
|---|---|---|---|---|
| 1 | `POST` | `/api/auth/login` | ❌ | — |
| 2 | `GET` | `/api/services` | ❌ | — |
| 3 | `GET` | `/api/services/{id}` | ❌ | — |
| 4 | `GET` | `/api/availability/service` | ❌ | 40/دقيقة |
| 5 | `GET` | `/api/availability/provider` | ❌ | 40/دقيقة |
| 6 | `GET` | `/api/availability/calendar` | ❌ | 30/دقيقة |
| 7 | `POST` | `/api/bookings` | ✅ + مفعّل | — |
| 8 | `GET` | `/api/bookings` | ✅ + مفعّل | — |
| 9 | `GET` | `/api/bookings/{id}` | ✅ + مفعّل | — |
| 10 | `POST` | `/api/bookings/{id}/cancel` | ✅ + مفعّل | — |

**ملفات ذات صلة:**

| الملف | المحتوى |
|---|---|
| `API.md` | مرجع كل نقاط الـ API في المشروع |
| `docs/booking-api.md` | مرجع تقني للحجز بالإنجليزية |
| `docs/BOOKING_FLOW.md` | تدفّق الحجز داخلياً (للـ backend) |
| `docs/API/PASSWORD_RESET_FRONTEND_GUIDE.md` | فلو نسيت كلمة المرور |

</div>
