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

## 4. ضمانات الاتساق مع الحجز

| المحدد | التوفر | الحجز |
|--------|--------|-------|
| `book_buffer` | يحذف slots قبل `now+buffer` | يرفض `start ≤ now+buffer` |
| `max_booking_days` | `outside_booking_window` | `422` |

الشبكة `shift_start + k×duration` ثابتة كل الأيام.

---

*التالي: [`booking.md`](booking.md)*
