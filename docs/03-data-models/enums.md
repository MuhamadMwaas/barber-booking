# الـ Enums — التعدادات

> **المجلد:** `app/Enum/` (8 ملفات) — PHP 8.1 Backed Enums

---

## 1. AppointmentStatus — `app/Enum/AppointmentStatus.php:1`

```php
enum AppointmentStatus: int {
    case PENDING = 0;          // قيد الانتظار
    case COMPLETED = 1;        // مكتمل
    case USER_CANCELLED = -1;  // ألغاه العميل
    case ADMIN_CANCELLED = -2; // ألغته الإدارة
    case NO_SHOW = -3;         // لم يحضر
}
```

- يُخزن كـ `int` في `appointments.status`
- `PENDING` هو الوحيد القابل للإلغاء — `BookingService.php:385`
- `USER_CANCELLED` فقط يُحتسب في `CancellationMonitor` — `app/Services/CancellationMonitor.php:1`

---

## 2. InvoiceStatus — `app/Enum/InvoiceStatus.php:1`

```php
enum InvoiceStatus: int {
    case DRAFT = 0;          // مسودة — بلا رقم
    case PENDING = 1;        // بانتظار الدفع
    case PAID = 2;           // مدفوعة — مع رقم
    case PARTIALLY_PAID = 3; // مدفوعة جزئيًا
    case CANCELLED = -1;     // ملغاة
    case REFUNDED = -2;      // مسترجعة
    case OVERDUE = 4;        // متأخرة
}
```

- الدورة الحالية: `DRAFT (0)` → `PAID (2)` فقط — `InvoiceFinalizationService.php:1`
- `PENDING` و `PARTIALLY_PAID` موجودان لكن لا يُستخدمان حاليًا (لا دفع جزئي — `Agent.md:366`)

---

## 3. PaymentStatus — `app/Enum/PaymentStatus.php:1`

```php
enum PaymentStatus: int {
    case PENDING = 0;             // لم يُدفع
    case PAID_ONLINE = 1;         // دُفع أونلاين (غير مستخدم حاليًا)
    case PAID_ONSTIE_CASH = 2;    // دُفع نقدًا في المحل
    case PAID_ONSTIE_CARD = 3;    // دُفع ببطاقة في المحل
    case FAILED = 4;              // فشل
    case REFUNDED = 5;            // مسترجع
    case PARTIALLY_REFUNDED = 6;  // مسترجع جزئيًا
}
```

- عند الحجز: دائمًا `PENDING (0)` — `BookingService.php:123`
- عند الدفع: `PAID_ONSTIE_CASH (2)` أو `PAID_ONSTIE_CARD (3)` — `InvoiceFinalizationService.php:80`
- `PAID_ONLINE (1)` لا يُستخدم (لا دفع أونلاين)

---

## 4. RegistrationMethod — `app/Enum/RegistrationMethod.php:1`

```php
enum RegistrationMethod: string {
    case EMAIL = 'email';
    case PHONE = 'phone';
}
```

- يحدد كيف سجل المستخدم — `users.registration_method`
- يحدد قناة OTP — `OtpService.php:1`
- `AccountVerificationService.php:1` يحل الطريقة من `User` model

---

## 5. OtpType — `app/Enum/OtpType.php:1`

```php
enum OtpType: int {
    case EMAIL_OTP = 1;
    case SMS_OTP = 2;
}
```

- `1` للبريد، `2` للهاتف — `app/Models/Otp.php:1`
- الحقل القديم `type` في API لا يزال مدعومًا للتوافق — `API.md:522`

---

## 6. OtpPurpose — `app/Enum/OtpPurpose.php:1`

```php
enum OtpPurpose: string {
    case VERIFICATION = 'verification';
    case PASSWORD_RESET = 'password_reset';
    case PHONE_VERIFICATION = 'phone_verification';
}
```

- يفصل OTP التحقق عن OTP استرجاع كلمة المرور — `database/migrations/2026_07_26_100000_add_purpose_and_attempts_to_otps_table.php`
- `OtpService` يبطل الأكواد السابقة لنفس القناة + الغرض فقط — لا يبطل كل شيء

---

## 7. BookingSource — `app/Enum/BookingSource.php:1`

```php
enum BookingSource: string {
    case API = 'api';
    case WEB = 'web';
    case IN_PERSON = 'in_person';
}
```

- يُخزن في `appointments.booking_source` — `database/migrations/2026_05_26_000001_add_booking_source_to_appointments_table.php`
- افتراضي `in_person` — `BookingService.php:145`

---

## 8. TemplateSectionType — `app/Enum/TemplateSectionType.php:1`

```php
enum TemplateSectionType: string {
    case Header = 'header';
    case Body = 'body';
    case Footer = 'footer';
}
```

- يقسم `TemplateLine` إلى 3 أقسام — `app/Models/TemplateLine.php:15`
- كل قسم له أنواع أسطر مختلفة — `config/invoice-line-types.php`

---

## 9. كيف تُستخدم Enums في Models؟

```php
// app/Models/Appointment.php:45 — Casts
protected $casts = [
    'status' => AppointmentStatus::class,
    'payment_status' => PaymentStatus::class,
    'booking_source' => BookingSource::class,
];

// الاستخدام
$appointment->status === AppointmentStatus::PENDING; // مقارنة كـ Enum
$appointment->status->value; // 0
$appointment->status->name;  // "PENDING"
AppointmentStatus::from(0);  // PENDING
AppointmentStatus::tryFrom(99); // null
```

---

*التالي: [`04-services/README.md`](../04-services/README.md)*
