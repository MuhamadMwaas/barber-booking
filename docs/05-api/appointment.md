# Appointments & Reminders API

> **الملفات:** `app/Http/Controllers/Api/AppointmentController.php:1`، `app/Http/Controllers/Api/AppointmentReminderController.php:1`، `app/Services/AppointmentService.php:1`

---

## 1. `GET /api/appointments` — قائمة المواعيد

```
GET /api/appointments?per_page=15&status=PENDING&payment_status=PAID_ONSTIE_CASH&date_from=2026-09-01&date_to=2026-09-30&type=upcoming&sort_by=appointment_date&sort_direction=asc
→ 200 { success, message, data:{data:[{id,number,appointment_date,start_time,end_time,status,payment_status,can_cancel,...}]}, meta:{current_page,per_page,total,last_page} }
```

- `status`: `PENDING/COMPLETED/USER_CANCELLED/ADMIN_CANCELLED/ALL`
- `type`: `upcoming` / `past`
- `per_page` 1-100.

## 2. `GET /api/appointments/{id}` — تفاصيل

```
GET /api/appointments/42
→ 200 { id, number, appointment_date, formatted_date, start_time, end_time, time_range, duration, subtotal, tax_amount, total_amount, status, payment_status, provider:{...}, services_details:[...], is_upcoming, can_cancel }
→ 403 إن ليس ملك المستخدم
→ 404 غير موجود
```

## 3. `GET /api/appointments/statistics` — إحصائيات

```
GET /api/appointments/statistics
→ 200 { total, pending, completed, cancelled, total_spent, upcoming_count }
```

## 4. `GET /api/appointments/upcoming?days=7` — القادمة

## 5. `GET /api/appointments/past?limit=10` — السابقة

## 6. `GET /api/appointments/search?query=APT` — بحث

- `query` ≥ حرفان — `422` إن أقل.

## 7. `POST /api/appointments/{id}/cancel` — إلغاء

```
POST /api/appointments/42/cancel
→ 200 { message: "Cancelled" }
→ 422 إن ليس PENDING
→ 403 ليس ملكه
```

- يكتب `USER_CANCELLED` — `AppointmentService.php:1` → `Appointment::cancel()` → `CancellationMonitor`.

---

## 8. Reminders — `AppointmentReminderController`

| Method | Path | Throttle | الغرض |
|--------|------|----------|-------|
| GET | `/api/appointments/reminders/options` | 60/min | خيارات lead_minutes + نصوص مترجمة |
| POST | `/api/appointments/reminders` | 30/min | حفظ `{appointment_id, lead_minutes}` |
| GET | `/api/appointments/{id}/reminders` | 60/min | عرض التذكير (200 + data:null إن لا يوجد) |
| DELETE | `/api/appointments/{id}/reminders` | 30/min | إلغاء |

- واحد live لكل موعد — `AppointmentReminderService` cancel-then-create.
- `ReminderChannelResolver` يحدد push/email/SMS حسب `UserSetting`.

---

*التالي: [`profile-and-settings.md`](profile-and-settings.md)*
