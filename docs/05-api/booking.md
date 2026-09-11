# Bookings API — الحجوزات

> **الملفات:** `app/Http/Controllers/Api/BookingController.php:1`، `app/Http/Requests/Api/BookingCreateRequest.php:1`، `routes/api.php:338`

---

## 1. `POST /api/bookings` — إنشاء حجز

### Auth

`auth:sanctum` + `verified.customer` — `routes/api.php:229`

### Request Body

| الحقل | النوع | Required | القواعد | الوصف |
|-------|-------|----------|---------|-------|
| `services` | array | ✅ | `min:1, max:10` | الخدمات |
| `services.*.service_id` | int | ✅ | `exists:services,id` + `whereNull(deleted_at)` | الخدمة |
| `services.*.provider_id` | int | ✅ | `exists:users,id` + `whereNull(deleted_at)` | المزود |
| `services.*.start_time` | string | ✅ | `date_format:H:i` | وقت البدء `10:00` |
| `date` | string | ✅ | `date_format:Y-m-d, after_or_equal:today` | التاريخ |
| `payment_method` | string | ✅ | `in:cash,online` | طريقة الدفع (نية فقط) |
| `notes` | string | ❌ | `max:1000` | ملاحظات |

**مثال:**

```json
POST /api/bookings
Authorization: Bearer 1|abc...
{
  "date": "2026-09-15",
  "payment_method": "cash",
  "notes": "أفضل الصباح",
  "services": [
    { "service_id": 3, "provider_id": 7, "start_time": "10:00" },
    { "service_id": 5, "provider_id": 7, "start_time": "10:30" }
  ]
}
```

### Response — `201 Created`

```json
{
  "success": true,
  "message": "Booking created successfully",
  "data": {
    "id": 42,
    "number": "APT-20260915-A1B2C3",
    "appointment_date": "2026-09-15",
    "start_time": "10:00",
    "end_time": "11:00",
    "duration_minutes": 60,
    "subtotal": 63.03,
    "tax_amount": 11.97,
    "total_amount": 75.00,
    "status": "PENDING",
    "payment_status": "PENDING",
    "provider": { "id": 7, "full_name": "أحمد محمد" },
    "services_details": [...]
  }
}
```

### Errors

| Code | `error_type` | السبب |
|------|--------------|-------|
| `422` | `validation_error` | شكل الطلب خاطئ (validation) |
| `422` | — | خارج الدوام، إجازة، حد يومي، تكرار خدمة |
| `409` | `slot_conflict` | الوقت محجوز — سبقك أحد (SlotUnavailableException) |
| `401` | — | غير مسجل |
| `403` | — | غير موثق (OTP) |

> **409 vs 422:** `409` يعني الطلب كان سليمًا والفتحة كانت معروضة — شخص آخر سبقك. التطبيق يجب أن يحدّث قائمة الفتحات.

---

## 2. `GET /api/bookings?status=0` — قائمة الحجوزات

```php
// BookingController@index — BookingService@getCustomerBookings
Appointment::where('customer_id', auth()->id())->with(['services','provider','services_record'])->orderBy('appointment_date','desc')
```

| Query | الوصف |
|-------|-------|
| `status` | `0=PENDING, 1=COMPLETED, -1=USER_CANCELLED, -2=ADMIN_CANCELLED, -3=NO_SHOW` |

---

## 3. `GET /api/bookings/{id}` — تفاصيل حجز

- يتحقق `customer_id === auth()->id()` — `BookingService.php:422` → `403` إن لم يملكه.
- `ModelNotFoundException` → `404` (بعد إصلاح `BOOK-09` يلتقط `Throwable`).

---

## 4. `POST /api/bookings/{id}/cancel` — إلغاء

```json
POST /api/bookings/42/cancel
{ "cancellation_reason": "تغيير خطط" }
```

- يسمح فقط لـ `PENDING` — `BookingService.php:385` → `422` إن مكتمل/ملغى.
- يكتب `USER_CANCELLED` + `cancelled_at=now()` — `Appointment.php:300`.
- يُستدعى `CancellationMonitor` — إشعار من الإلغاء الثاني في 7 أيام.
- **لا حاجز زمني:** الإلغاء مسموح حتى بعد بدء الموعد — `docs/BOOKING_FLOW.md:592` (BOOK-08).

---

*التالي: [`appointment.md`](appointment.md)*
