# ServiceAvailabilityService — حساب التوفر

> **الملف:** `app/Services/ServiceAvailabilityService.php:1`

---

## 1. المسؤولية

حساب الأوقات المتاحة للعميل قبل الحجز. يجب أن يتفق مع `BookingValidationService` — وإلا يُعرض slot ثم يُرفض.

---

## 2. الدوال الأساسية

| الدالة | الوصف | Route |
|--------|-------|-------|
| `getAvailableSlotsByDate(serviceId, date, branchId)` | كل المزودين المتاحين لخدمة في يوم | `GET /api/availability/service` |
| `getProviderAvailableSlotsByDate(serviceId, providerId, date)` | Slots مزود محدد | `GET /api/availability/provider` |
| `getAvailabilityCalendar(serviceId, providerId, startDate, endDate, branchId)` | تقويم 31 يوم (قديم) | `GET /api/availability/calendar` |
| `getProviderMonthAvailability(serviceId, providerId, month)` | أيام شهر مزوّد | `GET /api/availability/days` |
| `getServiceMonthAvailability(serviceId, month, branchId)` | أيام شهر خدمة + المزوّدون كل يوم | `GET /api/availability/days/providers` |

### البنية: محرك واحد لليوم والشهر

```
loadCalendarData(providers, from, to)   ← كل ما يُقرأ، دفعة واحدة لنطاق تواريخ
        │
        ▼
getProviderDayAvailability(provider, service, date, calendar)   ← القواعد كلها هنا فقط
        ▲                                   ▲
  المسارات اليومية                      المسارات الشهرية
  (نطاق = يوم واحد)                     (نطاق = الجزء القابل للحجز من الشهر)
```

- `bookableProviders()` = نفس شروط `validateProviderOffersService()` (user نشط، دور `provider`، pivot نشط، خدمة نشطة). كان الدور ونشاط الخدمة غائبين فكان التوفر يعرض ما يرفضه الحجز.
- `monthWindow()` يقصّ الشهر على `[today, last_bookable_date]`؛ `eachMonthDay()` يملأ ما خارجه بـ `past` / `outside_booking_window` دون حساب.
- الإعدادان (`book_buffer`, `max_booking_days`) يُقرآن **مرة** لكل طلب داخل `loadCalendarData()` — `get_setting()` استعلام.

---

## 3. خوارزمية `getProviderDayAvailability()` + `generateTimeSlots()`

```
generateTimeSlots(provider, service, date):
  1. إن date > last_bookable_date → outside_booking_window → []
  2. SalonSchedule::isClosedFor(provider.branch_id, day_of_week) → salon_closed → []
     (صف الفرع أولاً، ثم الصف العام branch_id=NULL، وغياب الصفوف = لا قيد؛ is_open فقط، الساعات للعرض)
  3. schedule = ProviderScheduledWork where user_id=provider && day_of_week && is_work_day && is_active
     └─ لا يوجد → return [] (not_working_day)
  4. إن إجازة FULL_DAY تغطي date (blockedWindowOn ≠ null) → [] (on_leave)
  5. duration = service.duration_minutes
  6. window: shift_start → shift_end
  7. appointments = Appointment::blocksProviderTime()->overlapping في هذا اليوم
  8. hourlyOffs = ProviderTimeOff::coveringDate(date) HOURLY
  9. Loop: current = shift_start
     while current + duration ≤ shift_end:
       slot = [current, current+duration]
       skip if slot.start < now()+book_buffer (اليوم الحالي)
       skip if overlaps appointments
       skip if overlaps hourlyOffs (blocksWindow)
       else add slot {start_time, end_time, display_time, ...}
       current += duration + SLOT_BUFFER (حاليًا 0)
  10. إن لا slots → fully_booked
```

**الشبكة ثابتة:** `shift_start + k × duration` — نفسها كل الأيام بما فيها اليوم الحالي. الفرق في اليوم الحالي أن الماضي + `book_buffer` محذوف.

**Cache:** كل `service+date+provider` مخبأ دقيقة واحدة.

---

## 4. التكافؤ مع الحجز

| المحدد | التوفر | الحجز |
|--------|--------|-------|
| `book_buffer` | يحذف slots قبل `now+buffer` | يرفض `start ≤ now+buffer` |
| `max_booking_days` | `outside_booking_window` | يرفض `date > today+max` |
| `created_status` + `status` | `blocksProviderTime()` | `blocksProviderTime()` |
| `overlapping` | `scopeOverlapping` | `scopeOverlapping` |
| إغلاق الصالون | `SalonSchedule::isClosedFor()` → `salon_closed` | نفس الدالة → `422` (يتجاوزها `force_booking`) |
| نطاق الإجازة | `touchingRange()` + `blockedWindowOn()` | `coveringDate()` (= `touchingRange(d, d)`) |

هذا التكافؤ هو ما يمنع `BOOK-01`.

---

*التالي: [`invoice-service.md`](invoice-service.md)*
