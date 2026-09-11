# باقي الخدمات — Other Services

> **المجلد:** `app/Services/` — 60+ Service غير الأساسية

---

## 1. BookingLockService — `app/Services/BookingLockService.php:1`

```php
public function lockUsers(array $userIds): void {
    $ids = array_unique(array_filter($userIds));
    sort($ids); // منع deadlock
    User::whereIn('id', $ids)->lockForUpdate()->get(); // SELECT FOR UPDATE
}
```

- **لماذا `users` لا `appointments`؟** الحالة المحمية هي غياب موعد — لا يمكن قفل صفوف غير موجودة.
- **أين يُستخدم:** `BookingService.php:105`، `BookingService.php:537` (addService)، `StaffDashboard.php` (updateAppointment).
- التفصيل في [`06-booking-flow/concurrency.md`](../06-booking-flow/concurrency.md).

---

## 2. GapAnalysisService — `app/Services/GapAnalysisService.php:1`

يحلل هل يمكن إدراج خدمة جديدة قبل/بعد حجز قائم.

```php
analyzeAddBefore(anchor, service, duration, requestedStart, allowSameDayPast) → array
analyzeAddAfter(anchor, service, duration, requestedStart, allowSameDayPast) → array
analyzeChildAdd(invoiceOwner, newProvider, service, duration, placement, requestedStart, ...) → array
```

**يُرجع:**

```php
[
  'is_possible' => bool,
  'reason' => 'provider_not_working' | 'no_space_before' | ...,
  'requires_push' => bool,
  'push_plan' => [...], // إن احتاج دفع
  'suggested_start_time' => '09:30',
  'suggested_end_time' => '10:00',
  'max_duration_available' => 30,
]
```

---

## 3. PushBookingsService — `app/Services/PushBookingsService.php:1`

ينفذ خطة دفع المواعيد اللاحقة عند إضافة خدمة تضيق الفجوة.

```php
executePushPlan(pushPlan, date) → int[] // ids المدفوعة
```

- كل موعد مدفوع يُحدّث `start_time/end_time` + `original_start_time` + `was_pushed=true`.
- لا يدفع المواعيد المدفوعة (`payment_status != PENDING`) — يرمي `paid_booking_in_chain`.

---

## 4. CancellationMonitor — `app/Services/CancellationMonitor.php:1`

```php
recordCustomerCancellation(Appointment $appointment): void {
    $count = Appointment::where('customer_id', $appointment->customer_id)
        ->where('status', USER_CANCELLED)
        ->where('cancelled_at', '>=', now()->subDays(7))
        ->count();
    if ($count >= 2) {
        // إشعار Filament لكل admin/manager نشط
        // لا يمنع الإلغاء — معلومة فقط
        // يبتلع أخطاءه ويسجل Log::warning
    }
}
```

- يُستدعى من `Appointment::cancel()` — `app/Models/Appointment.php:300`.
- `ADMIN_CANCELLED` لا يُحتسب.

---

## 5. OtpService — `app/Services/OtpService.php:1`

```php
generate(string $destination, OtpType $type, OtpPurpose $purpose): Otp
verify(string $destination, string $code, OtpType $type, OtpPurpose $purpose): bool
```

- عند التوليد: يبطل الأكواد السابقة لنفس `destination + type + purpose` فقط.
- `expires_at` من `config/otp.php` + `attempts` محدودة.

---

## 6. AuthTokenService — `app/Services/AuthTokenService.php:1`

```php
createTokens(User $user) → ['access_token', 'refresh_token']
refresh(string $refreshToken) → new access_token (يبطل القديم)
```

- `access_token` = Sanctum `personal_access_tokens` — TTL من `config/auth_tokens.php`
- `refresh_token` = `refresh_tokens` table — rotation آمن (`2026_08_29_200000_harden_refresh_token_rotation.php`)

---

## 7. AppointmentReminderService — `app/Services/AppointmentReminderService.php:1`

```php
schedule(Appointment $appointment, int $leadMinutes) // cancel-then-create
reschedule(Appointment $appointment, int $leadMinutes)
cancel(Appointment $appointment)
```

- تذكير واحد live لكل موعد — قيد `one_active` — `AppointmentReminder.php:1`
- `ReminderChannelResolver` يحدد القنوات (push/email/SMS) حسب `UserSetting` — `app/Services/Reminders/ReminderChannelResolver.php:1`

---

## 8. DocumentNumberGenerator — `app/Services/DocumentNumberGenerator.php:1`

انظر [`03-data-models/invoice-and-payment.md`](../03-data-models/invoice-and-payment.md).

---

## 9. Sms/* — `app/Services/Sms/`

```
SmsGateway (interface)
SmsManager → يفوّض لـ driver حسب config('sms.driver')
  ├─ SevenSmsDriver → POST api.seven.io
  ├─ VonageSmsDriver → Vonage SDK
  └─ LogSmsDriver → Log فقط (للتطوير)
PhoneNumberNormalizer → يجرد الفواصل
SmsResult → value object
```

---

## 10. خدمات أخرى

| الخدمة | الدور |
|--------|-------|
| `CustomerLookupService` | بحث عن عملاء بالاسم/البريد/الهاتف للـ Staff Dashboard |
| `DashboardMessageService` | رسائل لوحة يومية (pinned, auto-expiry) |
| `AttendanceService` | حضور المزود (multi-session, لا جلستين مفتوحتين) |
| `ReportsService` | إحصائيات الإيرادات بين تاريخين |
| `PageRenderService` | عرض `SamplePage` مع fallback locale |
| `VonageSdkSmsService` | إرسال SMS عبر Vonage SDK مباشرة |

---

*التالي: [`05-api/README.md`](../05-api/README.md)*
