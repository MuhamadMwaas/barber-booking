# دليل التوثيق الشامل — BarberBooking / Beauty Salon Management System

> **اللغة:** الشرح بالعربية — المصطلحات التقنية وأسماء الحقول والكود بالإنجليزية كما هي في المشروع  
> **الهدف:** أن يقرأ أي مهندس برمجيات هذا الدليل فيفهم **فكرة النظام وآلية عمله وبنيته المعمارية والكود بعمق** دون الحاجة لقراءة الكود سطرًا سطرًا أولًا  
> **المرجع السريع للـ AI Agents:** [`Agent.md`](../Agent.md) — نسخة مكثفة تقنية موجهة للـ AI  
> **مرجع الـ API للموبايل/الفرونت:** [`API.md`](../API.md)

---

## كيف تستخدم هذا التوثيق؟

هذا المجلد `docs/` هو **المدخل الموحد** لكل التوثيق. التوثيق موزّع على **10 أقسام مرقمة**، كل قسم مجلد مستقل فيه `README.md` مدخل + ملفات تفصيلية متخصصة.

### مسار القراءة المقترح حسب هدفك

| هدفك | ابدأ من | ثم |
|------|---------|-----|
| **فهم سريع للنظام (30 دقيقة)** | [`01-overview/README.md`](01-overview/README.md) | `02-architecture/README.md` → `06-booking-flow/README.md` |
| **تطوير API / موبايل** | `01-overview/README.md` | `05-api/README.md` → `06-booking-flow/README.md` → `09-security/README.md` |
| **تطوير لوحة الإدارة Filament** | `02-architecture/README.md` | `07-filament-admin/README.md` → `03-data-models/README.md` |
| **إصلاح حسابات مالية / ضرائب** | `08-invoicing-printing/README.md` | `04-services/tax-calculator.md` → `Agent.md` قسم 4.5 |
| **تشخيص مشكلة حجز / تزامن** | `06-booking-flow/README.md` | `06-booking-flow/concurrency.md` → `04-services/booking-service.md` |
| **نشر / تشغيل / إعدادات** | `10-operations/README.md` | `02-architecture/tech-stack.md` |

> **اتفاقية الإشارة للكود:** كل إشارة لدالة أو ملف تُكتب على شكل `file_path:line_number` — مثال: `app/Services/BookingService.php:42` — لتقفز إليها مباشرة في المحرر.

---

## خريطة الأقسام — Table of Contents

### [01 — نظرة عامة وفكرة النظام](01-overview/README.md)
| الملف | المحتوى |
|-------|---------|
| [README.md](01-overview/README.md) | فكرة النظام، أصحاب المصلحة، دورة حياة الحجز الكاملة، القصة من وجهة نظر العميل والمزود والإدارة |
| [business-model.md](01-overview/business-model.md) | نموذج العمل: تسعير شامل للضريبة، دفع نقدي في المحل، بدون دفع أونلاين، فاتورة مسودة → مدفوعة |
| [user-roles.md](01-overview/user-roles.md) | الأدوار: `SuperAdmin / admin / manager / provider / customer` — صلاحيات كل دور وأين تُفحص `app/Models/User.php:80` |

### [02 — البنية والمعمارية](02-architecture/README.md)
| الملف | المحتوى |
|-------|---------|
| [README.md](02-architecture/README.md) | نظرة معمارية عامة: طبقات Laravel، فصل الـ API عن Filament، تدفق Request → Middleware → Controller → Service → Model |
| [tech-stack.md](02-architecture/tech-stack.md) | التقنيات: Laravel 12 / PHP 8.2 / Filament 4 / PostgreSQL / Sanctum / Spatie / Vite+Tailwind / OneSignal / Vonage |
| [directory-structure.md](02-architecture/directory-structure.md) | شرح كل مجلد في المشروع سطرًا سطرًا: `app/`, `config/`, `database/migrations/`, `resources/views/` |
| [request-lifecycle.md](02-architecture/request-lifecycle.md) | دورة حياة الطلب بالتفصيل: `bootstrap/app.php` → `routes/api.php:229` → `BookingController.php:29` → `BookingService.php:42` |
| [design-decisions.md](02-architecture/design-decisions.md) | القرارات التصميمية الكبرى: لماذا GROSS pricing، لماذا bcmath، لماذا two-stage invoicing، لماذا قفل على `users` |

### [03 — النماذج والبيانات (Data Models)](03-data-models/README.md)
| الملف | المحتوى |
|-------|---------|
| [README.md](03-data-models/README.md) | خريطة ERD نصية + جدول كل النماذج (46 نموذج) وعلاقاتها |
| [user.md](03-data-models/user.md) | `User` — الحقول، العلاقات، `HasRoles`, `SoftDeletes`, `canAccessPanel()` |
| [appointment.md](03-data-models/appointment.md) | `Appointment` + `AppointmentService` — الحقول، الحالات، الـ Scopes `scopeBlocksProviderTime`, `scopeOverlapping` |
| [service.md](03-data-models/service.md) | `Service` + `ServiceCategory` + `ServiceTranslation` + pivot `provider_service` |
| [invoice-and-payment.md](03-data-models/invoice-and-payment.md) | `Invoice` + `InvoiceItem` + `Payment` + `PaymentMethod` + `DocumentNumberGenerator` |
| [scheduling-models.md](03-data-models/scheduling-models.md) | `ProviderScheduledWork` + `ProviderTimeOff` + `SalonSchedule` + `ReasonLeave` |
| [template-models.md](03-data-models/template-models.md) | `InvoiceTemplate` + `TemplateLine` + `LineTypeRegistry` |
| [supporting-models.md](03-data-models/supporting-models.md) | `Branch`, `Language`, `Otp`, `RefreshToken`, `File`, `AppointmentReminder`, `UserDevice`, ... |
| [enums.md](03-data-models/enums.md) | `AppointmentStatus`, `InvoiceStatus`, `PaymentStatus`, `OtpPurpose`, `RegistrationMethod`, ... |

### [04 — طبقة الخدمات (Services Layer)](04-services/README.md)
| الملف | المحتوى |
|-------|---------|
| [README.md](04-services/README.md) | خريطة 71 Service — ماذا يفعل كل واحد ومتى يُستدعى |
| [booking-service.md](04-services/booking-service.md) | `BookingService.php:42` — المراحل الـ 7 لإنشاء الحجز، `calculateTotals`, `generateAppointmentNumber`, `addServiceToBooking` |
| [validation-service.md](04-services/validation-service.md) | `BookingValidationService` — الـ 6 أنواع تحقق + `assertCustomerIsFree` |
| [availability-service.md](04-services/availability-service.md) | `ServiceAvailabilityService` — خوارزمية توليد الـ Slots + Caching |
| [invoice-service.md](04-services/invoice-service.md) | `InvoiceService` + `InvoiceFinalizationService` — المسودة، التجميع، الدفع الوحيد |
| [tax-calculator.md](04-services/tax-calculator.md) | `TaxCalculatorService` — التنفيذ الوحيد للضريبة، `extractTax` / `calculateBulk`، فخ `bcdiv` |
| [other-services.md](04-services/other-services.md) | `BookingLockService`, `GapAnalysisService`, `PushBookingsService`, `CancellationMonitor`, `OtpService`, ... |

### [05 — واجهات الـ API](05-api/README.md)
| الملف | المحتوى |
|-------|---------|
| [README.md](05-api/README.md) | فهرس كل الـ Routes في `routes/api.php:412` + `routes/web.php:215` |
| [authentication.md](05-api/authentication.md) | `AuthController` + `OtpController` + `SocialAuthController` — التسجيل، الدخول، OTP، Google، Refresh |
| [availability.md](05-api/availability.md) | `AvailabilityController` — `/availability/service` + `/availability/provider` + `/availability/calendar` + Rate Limiting |
| [booking.md](05-api/booking.md) | `BookingController` — `POST /bookings`, `GET /bookings`, `POST /bookings/{id}/cancel` |
| [appointment.md](05-api/appointment.md) | `AppointmentController` — `index/statistics/upcoming/past/search/show/cancel` + `AppointmentReminderController` |
| [profile-and-settings.md](05-api/profile-and-settings.md) | `ProfileController` + `SettingsController` + `DevicesController` + `PhoneVerificationController` |
| [cms-and-landing.md](05-api/cms-and-landing.md) | `CmsPageController` + `SliderController` + `LandingController` |
| [print.md](05-api/print.md) | `PrintController` — طباعة الفواتير `apiPrint` / `printBatch` |

### [06 — تدفق الحجز بعمق (Booking Flow Deep Dive)](06-booking-flow/README.md)
| الملف | المحتوى |
|-------|---------|
| [README.md](06-booking-flow/README.md) | التدفق الكامل من `BookingCreateRequest` حتى `AppointmentResource` — مخطط تسلسلي + أكواد استجابة |
| [concurrency.md](06-booking-flow/concurrency.md) | التزامن ومنع الحجز المزدوج — `BookingLockService::lockUsers()` + `SELECT FOR UPDATE` + `appointments_conflict_lookup_idx` |
| [time-off-logic.md](06-booking-flow/time-off-logic.md) | منطق الإجازات — `scopeCoveringDate` + `blockedWindowOn` + `COALESCE(end_date, start_date)` |
| [customer-free-check.md](06-booking-flow/customer-free-check.md) | فحص العميل الحر — `assertCustomerIsFree` + `PhoneNumber::key()` |
| [add-service-flow.md](06-booking-flow/add-service-flow.md) | إضافة خدمة لحجز قائم — `addServiceToBooking` + `GapAnalysisService` + `PushBookingsService` |

### [07 — لوحة الإدارة Filament](07-filament-admin/README.md)
| الملف | المحتوى |
|-------|---------|
| [README.md](07-filament-admin/README.md) | نظرة عامة: 18 Resource + 4 Roles + Widgets |
| [resources.md](07-filament-admin/resources.md) | كل Resource: `Appointments`, `Providers`, `Services`, `InvoiceTemplates`, ... — Form/Schemas/Tables/Pages |
| [staff-dashboard.md](07-filament-admin/staff-dashboard.md) | `StaffDashboard` Livewire — `app/Livewire/StaffDashboard.php` — التقويم، السحب، الدفع، الحضور |
| [livewire-components.md](07-filament-admin/livewire-components.md) | `ScheduleManager`, `ShiftManager`, `WeeklyScheduleTimeline`, `CustomerLookup` |

### [08 — الفوترة والطباعة](08-invoicing-printing/README.md)
| الملف | المحتوى |
|-------|---------|
| [README.md](08-invoicing-printing/README.md) | دورة حياة الفاتورة: DRAFT → PAID، تسعير GROSS، الترقيم المتسلسل |
| [invoice-lifecycle.md](08-invoicing-printing/invoice-lifecycle.md) | `InvoiceService::createDtaftInvoiceFromAppointment` + `rebuildAggregatedInvoice` + `InvoiceFinalizationService::finalizeAppointmentPayment` |
| [template-system.md](08-invoicing-printing/template-system.md) | `InvoiceTemplate` + `TemplateLine` + `LineTypeRegistry` + `TemplateBuilderService` + أنواع الأسطر الـ 16 |
| [printing-flow.md](08-invoicing-printing/printing-flow.md) | `PrintController` + `PrintService` + `PrintLog` + `PrinterSetting` + عدّاد الطباعة |

### [09 — الأمان والامتثال](09-security/README.md)
| الملف | المحتوى |
|-------|---------|
| [README.md](09-security/README.md) | خريطة الأمان الكاملة |
| [auth-and-otp.md](09-security/auth-and-otp.md) | `OtpService` + `PasswordResetService` + `AuthTokenService` + `RefreshToken` rotation |
| [rate-limiting.md](09-security/rate-limiting.md) | كل الـ Throttles في `routes/api.php` + `config/rate_limits.php` + `AppServiceProvider::registerAuthRateLimiters()` |
| [roles-and-permissions.md](09-security/roles-and-permissions.md) | Spatie Roles: `SuperAdmin/admin/manager/provider/customer` + `canAccessPanel()` + `EnsureStaffDashboardAccess` |
| [tse-and-tax.md](09-security/tse-and-tax.md) | الامتثال الألماني: TSE معطّل عمداً، `app/Services/Fiskaly/` خارج مسار الدفع، `tax_rate` |

### [10 — التشغيل والإعداد](10-operations/README.md)
| الملف | المحتوى |
|-------|---------|
| [README.md](10-operations/README.md) | نظرة تشغيلية: متطلبات التشغيل، البيئات، الأوامر اليومية |
| [configuration.md](10-operations/configuration.md) | `config/` الـ 24 ملف + `.env` + `SalonSetting` + `AppSetting`/`UserSetting` + `get_setting()` |
| [seeding.md](10-operations/seeding.md) | الـ 26 Seeder + ترتيب `DatabaseSeeder` + بيانات افتراضية |
| [deployment.md](10-operations/deployment.md) | النشر: `composer install`, `migrate`, `db:seed`, `storage:link`, `queue:work`, `vite build` |

---

## الوثائق التفصيلية القديمة (محفوظة للمرجع)

هذه الملفات كانت موجودة قبل إعادة التنظيم وبقيت كما هي — يُحيل الدليل الجديد إليها عند الحاجة:

| الملف القديم | موضوعه | يُحيل إليه القسم الجديد |
|--------------|--------|------------------------|
| [`BOOKING_FLOW.md`](BOOKING_FLOW.md) | تدفق الحجز التفصيلي (1191 سطر) | `06-booking-flow/README.md` يلخصه ويحيل إليه |
| [`BOOKING_INTEGRITY_PROBLEMS_DETAILED_AR.md`](BOOKING_INTEGRITY_PROBLEMS_DETAILED_AR.md) | مشاكل التكامل الـ 9 (BOOK-01..09) | `06-booking-flow/concurrency.md` + `09-security/` |
| [`INVOICE_AND_TSS_ENGINEERING_REFERENCE.md`](INVOICE_AND_TSS_ENGINEERING_REFERENCE.md) | الفوترة و TSE | `08-invoicing-printing/` |
| [`STAFF_DASHBOARD.md`](STAFF_DASHBOARD.md) | لوحة الموظفين | `07-filament-admin/staff-dashboard.md` |
| [`API/API_APPOINTMENTS.md`](API/API_APPOINTMENTS.md) | API المواعيد | `05-api/appointment.md` |
| [`fixes/MON-01_vat_calculation_unified.md`](fixes/MON-01_vat_calculation_unified.md) | إصلاح حساب الضريبة | `04-services/tax-calculator.md` |
| [`fixes/MON-03_document_numbering.md`](fixes/MON-03_document_numbering.md) | إصلاح الترقيم | `08-invoicing-printing/invoice-lifecycle.md` |
| [`fixes/MON-05_unified_payment_flow.md`](fixes/MON-05_unified_payment_flow.md) | توحيد الدفع | `08-invoicing-printing/invoice-lifecycle.md` |

---

## كيف تقرأ مرجع `file_path:line_number`؟

> مثال: `app/Services/BookingService.php:42` تعني افتح الملف `app/Services/BookingService.php` واذهب للسطر 42 — هناك تبدأ دالة `createBooking()`.

كل أسماء الملفات في هذا التوثيق مكتوبة كمسارات نسبية من جذر المشروع `D:\Coding\BarberBooking\`.

---

## للمطور الجديد — ابدأ هنا (15 دقيقة)

1. اقرأ [`01-overview/README.md`](01-overview/README.md) — افهم ماذا يفعل النظام ولماذا.
2. اقرأ [`02-architecture/README.md`](02-architecture/README.md) — افهم الطبقات.
3. افتح `routes/api.php:229` و `app/Services/BookingService.php:42` وتتبع حجز واحد من الطلب حتى DB.
4. اقرأ [`06-booking-flow/README.md`](06-booking-flow/README.md) — افهم لماذا الحجز معقد (التزامن، الإجازات، العميل الحر).
5. شغّل `php artisan db:seed` وجرّب `POST /api/bookings` من `API.md`.

---

## المساهمة في التوثيق

- التوثيق يعيش مع الكود: أي تغيير في `BookingService` أو `InvoiceService` يجب أن يُحدَّث في `04-services/` و `06-booking-flow/` و `08-invoicing-printing/`.
- لا تستخدم `bcscale()` — مرّر الدقة صراحة في كل استدعاء `bcmath` — موثق في `04-services/tax-calculator.md`.
- أي مسار كتابة جديد يمس `provider_id/appointment_date/start_time/end_time` يجب أن يأخذ `BookingLockService::lockUsers()` داخل `DB::transaction()` — موثق في `06-booking-flow/concurrency.md`.

---

*آخر تحديث: 2026-09-11 — يغطي Laravel 12 + Filament 4 + 74 migration + 46 model + 71 service*
