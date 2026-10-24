# Availability API

> **الملفات:** `app/Http/Controllers/Api/AvailabilityController.php:1`، `app/Services/ServiceAvailabilityService.php:1`، `routes/api.php:206`

---

## 1. `GET /api/availability/service` — كل المزودين المتاحين لخدمة في يوم

```
GET /api/availability/service?service_id=3&date=2026-09-15&branch_id=1
→ 200 {
    service: {id, name, duration_minutes},
    date: "2026-09-15", day_name, formatted_date, last_bookable_date,
    total_providers: 2,
    providers: [
      { provider_id, provider_name, branch, service_pricing:{original_price, effective_price, formatted_price}, is_available:true, reason_code:"available", available_slots:[{start_time,end_time,display_time}] }
    ]
  }
```

- **المتاحون فقط** يظهرون — المزود في إجازة/غير عامل/محجوز كاملًا لا يظهر إطلاقًا.
- `total_providers=0` مع `200` إن اليوم مغلق/خارج النافذة.
- Throttle `40/min` per IP.

## 2. `GET /api/availability/provider` — مزود محدد

```
GET /api/availability/provider?service_id=3&provider_id=7&date=2026-09-15
→ 200 { is_available:true/false, reason_code, unavailable_reason, provider, service, total_slots, available_slots:[] }
→ إن غير متاح: 200 مع is_available=false (لا 404)
```

**`reason_code`:**

| القيمة | المعنى |
|--------|--------|
| `available` | متاح + slots |
| `on_leave` | إجازة يوم كامل |
| `not_working_day` | لا جدول عمل هذا اليوم |
| `fully_booked` | كل slots محجوزة/محجوبة ساعيًا |
| `outside_booking_window` | بعد `today+max_booking_days` |

## 3. `GET /api/availability/calendar` — تقويم 31 يوم

```
GET /api/availability/calendar?service_id=3&provider_id=7&start_date=2026-09-01&end_date=2026-09-30
→ 200 { service_id, provider_id, period:{start_date,end_date,month_name}, calendar:[{date,day_name,is_available,reason_code,available_slots_count}] }
```

- الحد 31 يوم — `400` إن تجاوز.
- Throttle `30/min` (الأثقل — يضرب per-day cost × 31).
- **قديم، باقٍ للتوافق.** الشاشات الجديدة تستخدم `/availability/days*` أدناه.

## 4. `GET /api/availability/days` — أيام توفر مزوّد خلال شهر

```
GET /api/availability/days?service_id=3&provider_id=7&month=2026-11
→ 200 { service, provider, pricing, month, month_name, previous_month, next_month,
        last_bookable_date, available_dates:[…],
        days:[{date, day_name, day_number, is_today, is_available, reason_code,
               available_slots_count, first_available_slot}] }
```

- `month` اختياري (`Y-m`)، الافتراضي الشهر الحالي. `days` = **كل** أيام الشهر.
- أيام قبل اليوم → `past`؛ بعد `last_bookable_date` → `outside_booking_window`؛ البقية تُحسب.
- `422` برسالة **واحدة** لكل مزوّد غير قابل للحجز (غير موجود/غير نشط/بلا دور provider/لا يقدّم الخدمة) — منع التعداد كما في `rejectProviderServicePair()`.
- `422` `booking.month_outside_booking_window` لشهر ماضٍ أو بعد شهر `last_bookable_date`.
- `service_id` يجب أن يكون خدمة **نشطة غير محذوفة** (`Rule::exists` + `whereNull('deleted_at')` — فخ BOOK-09).

## 5. `GET /api/availability/days/providers` — أيام توفر خدمة + من المتاح كل يوم

```
GET /api/availability/days/providers?service_id=3&month=2026-11&branch_id=1
→ 200 { service, branch_id, month, …, available_dates,
        days:[{…, providers_count, providers:[{provider_id, provider_name, provider_avatar,
               service_pricing, available_slots_count, first_available_slot}]}] }
```

- لا تُرسل الفتحات — التطبيق يجلبها لليوم المختار من `/availability/service`.
- سبب اليوم: `available` / `past` / `outside_booking_window` / `salon_closed` (مغلق لكل المزوّدين المعنيين) / `no_availability`.

### الكلفة
المساران الشهريان يحمّلان الشهر **دفعة واحدة** (`loadCalendarData()`): جداول العمل، الإجازات (`touchingRange`)، الحجوزات (`blocksProviderTime`)، `salon_schedules`، والإعدادان — ~10 استعلامات **ثابتة** مهما كان عدد المزوّدين والأيام (اختبار `MonthAvailabilityTest` يثبت ذلك). `/calendar` القديم ≈ 4 استعلامات × مزوّد × يوم.

## 6. ضمانات الاتساق مع الحجز

| المحدد | التوفر | الحجز |
|--------|--------|-------|
| `book_buffer` | يحذف slots قبل `now+buffer` | يرفض `start ≤ now+buffer` |
| `max_booking_days` | `outside_booking_window` | `422` |
| `salon_schedules.is_open` | `salon_closed` | `422` `booking.salon_closed` (يتجاوزه `force_booking`) |
| مزوّد قابل للحجز | `bookableProviders()`: user نشط + دور `provider` + pivot نشط + خدمة نشطة | `validateProviderOffersService()` نفس الشروط |

الشبكة `shift_start + k×duration` ثابتة كل الأيام.

---

*التالي: [`booking.md`](booking.md)*
