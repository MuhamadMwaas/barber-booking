# نماذج الجدولة — Scheduling Models

> **الملفات:** `app/Models/ProviderScheduledWork.php:1`، `app/Models/ProviderTimeOff.php:1`، `app/Models/SalonSchedule.php:1`، `app/Models/ReasonLeave.php:1`

---

## 1. ProviderScheduledWork — جدول العمل الأسبوعي

| العمود | النوع | الوصف |
|--------|-------|-------|
| `id` | bigint PK | المعرف |
| `user_id` | FK → users | المزود |
| `day_of_week` | int 0-6 | 0=الأحد، 6=السبت |
| `start_time` | time | بداية الدوام `09:00` |
| `end_time` | time | نهاية الدوام `17:00` |
| `is_work_day` | boolean | هل يعمل هذا اليوم؟ |
| `break_minutes` | integer | استراحة (غير مستخدم حاليًا في توليد الـ slots) |
| `is_active` | boolean | نشط |

```php
// ProviderScheduledWork.php:40 — دوال مساعدة
static::shiftsOverlap($s1, $e1, $s2, $e2) → bool
static::findOverlaps(array $shifts) → array
static::getWeeklySchedule($userId) → Collection grouped by day_of_week
static::timeToMinutes($time) → int
static::minutesToTime($minutes) → string
```

**مثال:**

```
المزود 7 — الأسبوع:
  الإثنين (1): 09:00-17:00 is_work_day=true
  الثلاثاء (2): 09:00-17:00
  الأربعاء (3): off (is_work_day=false)
  ...
```

---

## 2. ProviderTimeOff — إجازات المزود

| العمود | النوع | الوصف |
|--------|-------|-------|
| `id` | bigint PK | المعرف |
| `user_id` | FK → users | المزود |
| `type` | int | `0=TYPE_HOURLY` (ساعية)، `1=TYPE_FULL_DAY` (يوم كامل) |
| `start_date` | date | بداية الإجازة |
| `end_date` | date nullable | نهاية الإجازة (null = يوم واحد) |
| `start_time` | time nullable | بداية الوقت (للساعية فقط) |
| `end_time` | time nullable | نهاية الوقت (للساعية فقط) |
| `reason_id` | FK → reason_leaves | سبب الإجازة |

### الـ Scopes الحرجة

```php
// ProviderTimeOff.php:40
scopeCoveringDate($query, $date) {
    return $query->where('start_date', '<=', $date)
                 ->whereRaw('COALESCE(end_date, start_date) >= ?', [$date]);
}
// COALESCE(end_date, start_date) — إن كان end_date null فهو يوم واحد
// القديم كان end_date >= date — يُسقط null rows بصمت (BOOK-04)

// ProviderTimeOff.php:80
blockedWindowOn($date) → ?array{start: Carbon, end: Carbon}
// للساعية الممتدة: يوم البداية من start_time حتى نهاية اليوم، الوسط كامل، النهاية حتى end_time

// ProviderTimeOff.php:110
blocksWindow($start, $end) → bool
// هل هذه الإجازة تحجب الفترة [start, end)؟ (نصف مفتوحة)
```

### الإجازة الساعية الممتدة = غياب متصل واحد

```
إجازة 10/9 12:00 → 15/9 13:00:
  10/9 (البداية): محجوب من 12:00 حتى نهاية اليوم
  11/9 (وسط):    اليوم كامل محجوب
  12/9 (وسط):    اليوم كامل محجوب
  15/9 (النهاية): محجوب من بداية اليوم حتى 13:00
```

---

## 3. SalonSchedule — ساعات عمل الصالون

| العمود | الوصف |
|--------|-------|
| `branch_id` | FK → branches |
| `day_of_week` | 0-6 |
| `open_time` | وقت الفتح |
| `close_time` | وقت الإغلاق |
| `is_closed` | مغلق هذا اليوم؟ |

- يحدد ساعات عمل الفرع ككل (قد تختلف عن جدول كل مزود).
- يُستخدم في `ServiceAvailabilityService` للتحقق الأولي قبل جدول المزود.

---

## 4. ReasonLeave — أسباب الإجازة

| العمود | الوصف |
|--------|-------|
| `name` | اسم السبب (Vacation, Sick, ...) |
| `is_active` | نشط |

- له ترجمات: `ReasonLeaveTranslation` — `app/Models/Translation/ReasonLeaveTranslation.php`
- `ReasonLeaveSeeder` ينشئ: Vacation, Sick Leave, Personal, Training, ...

---

## 5. كيف تُستخدم معًا — مثال فحص 2026-09-15 10:00

```
1. هل المزود يعمل يوم الإثنين؟ → ProviderScheduledWork where day_of_week=1 and is_work_day=true
   └─ لا → reason_code: not_working_day

2. هل عنده إجازة يوم كامل تغطي 2026-09-15؟ → ProviderTimeOff::coveringDate('2026-09-15') where type=FULL_DAY
   └─ نعم → reason_code: on_leave

3. هل عنده إجازة ساعية تحجب 10:00-10:30؟ → ProviderTimeOff::coveringDate + blocksWindow(10:00, 10:30)
   └─ نعم → هذه الـ slot محذوفة، لكن المزود لا يزال يظهر (slots أخرى قد تكون متاحة)
   └─ إن حجبت كل الـ slots → reason_code: fully_booked

4. هل عنده موعد يحجب 10:00-10:30؟ → Appointment::blocksProviderTime()->overlapping(10:00, 10:30)
   └─ نعم → slot محذوفة
```

كل هذه الفحوصات في `BookingValidationService::validateTimeSlotAvailability()` و `ServiceAvailabilityService::generateTimeSlots()` — نفس المنطق.

---

*التالي: [`template-models.md`](template-models.md)*
