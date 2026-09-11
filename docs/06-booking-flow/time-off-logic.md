# منطق الإجازات — Time-Off Logic

> **الملفات:** `app/Models/ProviderTimeOff.php:1`، `docs/BOOKING_FLOW.md:1111` (BOOK-04)

---

## 1. المصدر الوحيد

| الدالة | تجيب عن |
|--------|---------|
| `scopeCoveringDate($date)` | هل تنطبق هذه الإجازة على ذلك اليوم؟ |
| `blockedWindowOn($date)` | أي شريحة من ذلك اليوم تشغلها؟ |
| `blocksWindow($start,$end)` | هل تتعارض مع [start,end)؟ |

التوفر والحجز يستدعيان الثلاثة — لا اشتقاق inline.

## 2. `scopeCoveringDate` — `COALESCE`

```php
where('start_date','<=',date)->whereRaw('COALESCE(end_date, start_date) >= ?', [date])
```

- `end_date=null` = يوم واحد — `COALESCE` يحولها لـ `start_date`.
- القديم `end_date >= date` يُسقط null rows بصمت (`NULL >= '2026-09-15'` = UNKNOWN لا FALSE).

## 3. `blockedWindowOn` — الإجازة الساعية الممتدة = غياب متصل واحد

```
10/9 12:00 → 15/9 13:00:
  البداية (10/9): من 12:00 حتى نهاية اليوم
  الوسط (11-14/9): اليوم كامل
  النهاية (15/9): من بداية اليوم حتى 13:00
  يوم واحد: من start_time حتى end_time
```

## 4. التحقق

`StaffDashboard::timeOffValidationError()` — `end_date ≥ start_date`, للساعية `start_time/end_time` مطلوبان و `end_time > start_time`. `22:00→02:00` مرفوض (لا عبور منتصف ليل).

---

*التالي: [`customer-free-check.md`](customer-free-check.md)*
