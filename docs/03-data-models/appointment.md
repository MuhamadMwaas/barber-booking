# نموذج الحجز — Appointment

> **الملفات:** `app/Models/Appointment.php:1` (600 سطر)، `app/Models/AppointmentService.php:1`، `database/migrations/2025_10_10_145021_create_appointments_table.php:1`

---

## 1. الحقول — `appointments` table

| العمود | النوع | الوصف |
|--------|-------|-------|
| `id` | bigint PK | المعرف |
| `number` | string unique | رقم الحجز `APT-YYYYMMDD-XXXXXX` — `BookingService.php:367` |
| `customer_id` | FK → users nullable | العميل (null للضيوف) |
| `provider_id` | FK → users | المزود (من أول خدمة بعد الترتيب) |
| `parent_appointment_id` | FK → appointments nullable | الأب — للحجوزات المرتبطة (linked bookings) |
| `customer_name` | string | اسم العميل (للضيوف أو snapshot) |
| `customer_email` | string | بريد العميل |
| `customer_phone` | string | هاتف العميل |
| `appointment_date` | date | تاريخ الموعد |
| `start_time` | datetime | بداية الموعد |
| `end_time` | datetime | نهاية الموعد |
| `original_start_time` | datetime nullable | البداية الأصلية قبل الدفع (push) |
| `was_pushed` | boolean | هل دُفع؟ |
| `duration_minutes` | integer | مجموع مدد الخدمات |
| `subtotal` | decimal(10,2) | الصافي |
| `tax_amount` | decimal(10,2) | الضريبة |
| `total_amount` | decimal(10,2) | الإجمالي (GROSS) |
| `status` | int (Enum) | `AppointmentStatus` — `PENDING(0)` عند الإنشاء |
| `payment_status` | int (Enum) | `PaymentStatus` — `PENDING(0)` عند الإنشاء |
| `payment_method` | string | `cash` / `online` (نية فقط) |
| `created_status` | int | `1` = مؤكد ويحجب الوقت، `0` = غير مؤكد |
| `booking_source` | string (Enum) | `in_person` / `api` / `web` |
| `is_override` | boolean | هل تجاوز التوفر؟ |
| `override_reason` | text nullable | سبب التجاوز |
| `cancellation_reason` | text nullable | سبب الإلغاء |
| `cancelled_at` | datetime nullable | وقت الإلغاء |
| `notes` | text nullable | ملاحظات العميل |
| `created_at` / `updated_at` | timestamps | |

### الفهرس الحرج

```sql
-- database/migrations/2026_09_07_120000_add_conflict_lookup_index_to_appointments.php
CREATE INDEX appointments_conflict_lookup_idx
ON appointments (provider_id, appointment_date, created_status, status);
-- يُسرّع فحص التعارض في BookingValidationService و ServiceAvailabilityService
```

---

## 2. العلاقات

```php
// app/Models/Appointment.php:90
customer()        → BelongsTo(User, 'customer_id')
provider()        → BelongsTo(User, 'provider_id')
services()        → BelongsToMany(Service, 'appointment_services')
                     withPivot(['service_name', 'duration_minutes', 'price', 'sequence_order'])
services_record() → HasMany(AppointmentService)  // نفس البيانات كـ Model مستقل
invoice()         → HasOne(Invoice)
payments()        → MorphMany(Payment)
reminders()       → HasMany(AppointmentReminder)
activeReminder()  → HasOne(AppointmentReminder)->whereNotNull('active_slot')
colors()          → BelongsToMany(Color, 'appointment_colors')
parent()          → BelongsTo(Appointment, 'parent_appointment_id')
children()        → HasMany(Appointment, 'parent_appointment_id')
```

**لماذا علاقتان للخدمات؟**

| العلاقة | النوع | الاستخدام |
|---------|-------|-----------|
| `services()` | BelongsToMany | وصول سريع `$appointment->services` + pivot |
| `services_record()` | HasMany | وصول لـ Model كامل `AppointmentService` مع accessors (`formatted_duration`) |

---

## 3. الـ Scopes — Single Source of Truth

### `scopeBlocksProviderTime()` — `Appointment.php:150`

```php
public function scopeBlocksProviderTime(Builder $query): Builder {
    return $query->where('created_status', 1)
                 ->whereIn('status', [AppointmentStatus::PENDING, AppointmentStatus::COMPLETED]);
}
```

- **المعنى:** هذا الحجز يحجب وقت المزود ويجب اعتباره عند حساب التوفر والتحقق من التعارض.
- **من يستخدمه:** `ServiceAvailabilityService.php:80` + `BookingValidationService.php:120` — نفس التعريف في المكانين.

### `scopeOverlapping($start, $end)` — `Appointment.php:165`

```php
public function scopeOverlapping(Builder $query, Carbon $start, Carbon $end): Builder {
    return $query->where('start_time', '<', $end)
                 ->where('end_time', '>', $start);
}
```

- **نصف مفتوح (half-open):** `[start, end)` — موعد `10:00-11:00` و `11:00-12:00` لا يتعارضان (back-to-back مسموح).
- **كان Bug BOOK-06:** الفحص القديم كان `start_time = newStart` فقط — يسمح بالتداخل الجزئي.

### `scopePushable()` — للـ PushBookingsService

```php
// المواعيد التي يمكن دفعها (PENDING + غير مدفوعة)
scopePushable() → where('status', PENDING)->where('payment_status', PENDING)
```

---

## 4. الـ Accessors

| Accessor | الوصف | المثال |
|----------|-------|--------|
| `customer_name` | اسم العميل أو "Guest" | `app/Models/Appointment.php:200` |
| `customer_email` | بريد من الحساب أو الحجز | |
| `customer_phone` | هاتف من الحساب أو الحجز | |
| `has_customer_account` | `customer_id !== null` | `bool` |
| `status_label` | اسم الحالة | `"Pending"` |
| `payment_status_label` | اسم حالة الدفع | `"Paid On site Cash"` |
| `formatted_date` | تاريخ منسق | `"Mar 15, 2026"` |
| `time_range` | نطاق الوقت | `"10:00 AM - 11:00 AM"` |
| `formatted_duration` | المدة | `"1h 30m"` |
| `is_upcoming` | `start_time > now()` | `bool` |
| `is_past` | `start_time < now()` | `bool` |
| `is_cancelled` | `status IN (-1,-2)` | `bool` |
| `can_cancel` | `status=PENDING AND start_time > now()` | `bool` (للـ Resource) |

---

## 5. Boot Events — `Appointment.php:80`

```php
static::creating(function ($appointment) {
    if (!$appointment->number) {
        $appointment->number = app(BookingService::class)->generateAppointmentNumber();
    }
});

static::deleting(function ($appointment) {
    $appointment->reminders()->delete(); // إلغاء التذكيرات
});

static::updated(function ($appointment) {
    if ($appointment->wasChanged('status') && $appointment->isCancelled()) {
        // إلغاء التذكيرات عند الإلغاء
    }
});
```

---

## 6. دوال الإلغاء

```php
// Appointment.php:300
public function cancel(?string $reason = null): bool {
    $result = $this->update([
        'status' => AppointmentStatus::USER_CANCELLED, // -1
        'cancellation_reason' => $reason,
        'cancelled_at' => now(),
    ]);
    // → CancellationMonitor::recordCustomerCancellation($this)
    return $result;
}

public function isCancelled(): bool {
    return in_array($this->status, [USER_CANCELLED, ADMIN_CANCELLED]);
}
```

- `cancel()` تكتب `USER_CANCELLED` فقط — إلغاء الإدارة يكتب `ADMIN_CANCELLED` مباشرة بدون المرور بها.
- `CancellationMonitor` يُستدعى من `cancel()` — يعد الإلغاءات في 7 أيام ويرسل إشعارًا من الثاني فصاعدًا.

---

## 7. AppointmentService — `app/Models/AppointmentService.php:1`

| العمود | الوصف |
|--------|-------|
| `appointment_id` | FK → appointments |
| `service_id` | FK → services |
| `service_name` | snapshot من `services.name` وقت الحجز |
| `duration_minutes` | مدة هذه الخدمة |
| `price` | السعر وقت الحجز (GROSS) |
| `sequence_order` | الترتيب (1,2,3...) |

```php
// Boot: يملأ service_name تلقائيًا إن لم يُمرر
static::creating(function ($row) {
    if (!$row->service_name) {
        $row->service_name = Service::find($row->service_id)?->name;
    }
});
```

---

## 8. الحالات — `app/Enum/AppointmentStatus.php:1`

| القيمة | الاسم | المعنى |
|--------|-------|--------|
| `0` | `PENDING` | قيد الانتظار — يحجب الوقت |
| `1` | `COMPLETED` | مكتمل — يحجب الوقت (للسجل) |
| `-1` | `USER_CANCELLED` | ألغاه العميل — لا يحجب |
| `-2` | `ADMIN_CANCELLED` | ألغته الإدارة — لا يحجب |
| `-3` | `NO_SHOW` | لم يحضر — لا يحجب |

فقط `PENDING` و `COMPLETED` مع `created_status=1` يحجبان الوقت (`scopeBlocksProviderTime`).

---

*التالي: [`service.md`](service.md)*
