# 04 — طبقة الخدمات — Services Layer

> **المجلد:** `app/Services/` (71 ملف) — قلب النظام، كل منطق العمل هنا

---

## 1. خريطة الخدمات الكاملة

### الخدمات الأساسية (Core Booking & Invoicing)

| الخدمة | الملف | الدور |
|--------|-------|-------|
| **BookingService** | `BookingService.php:42` | منسق الحجز الرئيسي — 7 مراحل |
| **BookingValidationService** | `BookingValidationService.php:1` | كل قواعد التحقق (6 أنواع) |
| **BookingLockService** | `BookingLockService.php:1` | منع الحجز المزدوج — `SELECT FOR UPDATE` |
| **ServiceAvailabilityService** | `ServiceAvailabilityService.php:1` | حساب الـ Slots المتاحة + Cache |
| **TaxCalculatorService** | `TaxCalculatorService.php:1` | الحساب الوحيد للضريبة |
| **InvoiceService** | `InvoiceService.php:1` | مسودات + تجميع + خصم |
| **InvoiceFinalizationService** | `InvoiceFinalizationService.php:1` | العملية الوحيدة للدفع |

### الخدمات المساندة للحجز

| الخدمة | الدور |
|--------|-------|
| `GapAnalysisService` | هل يمكن إدراج خدمة جديدة قبل/بعد؟ |
| `PushBookingsService` | دفع المواعيد اللاحقة عند الإضافة |
| `AppointmentLinkingService` | ربط parent→children (مستوى واحد فقط) |
| `AppointmentService` | استعلامات العميل (list/details/statistics/cancel/search) |
| `AppointmentReminderService` | جدولة/إلغاء التذكيرات (واحد live لكل موعد) |
| `CancellationMonitor` | مراقبة الإلغاء المتكرر (إشعار من الثاني في 7 أيام) |
| `CustomerLookupService` | بحث عن عملاء مسجلين للـ Staff Dashboard |
| `BookingMailService` | إيميلا تأكيد (عميل + شركة) — queued |

### الفوترة والطباعة

| الخدمة | الدور |
|--------|-------|
| `DocumentNumberGenerator` | ترقيم متسلسل `INV-2026-000001` داخل transaction |
| `InvoiceTemplate/*` (6) | `LineTypeRegistry`, `TemplateBuilderService`, `PdfGeneratorService`, ... |
| `Print/PrintService` | طباعة عبر `PrinterSetting` + `PrintLog` |
| `DailyReportService` | تقرير Z + ملخص موظفين |

### التوثيق والإشعارات

| الخدمة | الدور |
|--------|-------|
| `AuthTokenService` | Sanctum tokens + refresh rotation |
| `OtpService` | توليد/تحقق OTP — purpose-aware |
| `OtpDeliveryService` | إرسال OTP عبر SMS/Mail |
| `PasswordResetService` | forgot-password عبر email/SMS |
| `AccountVerificationService` | حل `RegistrationMethod` |
| `OneSignalService` | Push عبر OneSignal REST |
| `NotificationService` | توزيع الإشعارات |
| `Sms/*` (7) | `SmsManager` + Drivers (Seven/Vonage/Log) |

### الإدارة والتقارير

| الخدمة | الدور |
|--------|-------|
| `ProviderService` | قائمة المزودين مع خدماتهم |
| `ReportsService` | إحصائيات الإيرادات |
| `ProviderReportService` | تقارير المزود |
| `DashboardService` / `DashboardStatsService` | إحصائيات Staff Dashboard |
| `AttendanceService` / `AttendanceBoardService` | حضور المزودين |
| `SettingsService` / `UserSettingService` | قراءة/كتابة الإعدادات |

---

## 2. كيف تتداخل الخدمات — مثال حجز واحد

```
BookingController@store
  → BookingService@createBooking
      ├─ BookingValidationService@validateBasicData (خارج transaction)
      ├─ DB::transaction
      │    ├─ BookingLockService@lockUsers
      │    ├─ BookingValidationService@validateDailyBookingLimit (تحت القفل)
      │    ├─ BookingValidationService@validateProviderOffersService (لكل خدمة)
      │    ├─ BookingValidationService@validateTimeSlotAvailability (لكل خدمة)
      │    ├─ BookingValidationService@assertCustomerIsFree (لكل خدمة)
      │    ├─ TaxCalculatorService@calculateBulk (الإجماليات)
      │    ├─ Appointment::create
      │    ├─ AppointmentService::create × N
      │    └─ InvoiceService@createDtaftInvoiceFromAppointment
      │         └─ TaxCalculatorService@extractTax (لكل بند)
      └─ BookingMailService@sendForNewBooking (queued بعد commit)
```

---

## 3. الملفات التفصيلية

| الملف | المحتوى |
|-------|---------|
| [`booking-service.md`](booking-service.md) | `BookingService` — المراحل الـ 7 + `addServiceToBooking` + `calculateTotals` |
| [`validation-service.md`](validation-service.md) | `BookingValidationService` — الـ 6 تحققات + `assertCustomerIsFree` |
| [`availability-service.md`](availability-service.md) | `ServiceAvailabilityService` — خوارزمية الـ Slots + Cache |
| [`tax-calculator.md`](tax-calculator.md) | `TaxCalculatorService` — `extractTax`/`calculateBulk` + فخ `bcdiv` |
| [`invoice-service.md`](invoice-service.md) | `InvoiceService` + `InvoiceFinalizationService` |
| [`other-services.md`](other-services.md) | باقي الـ 60+ Service |

---

*التالي: [`booking-service.md`](booking-service.md)*
