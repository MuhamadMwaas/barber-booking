# 03 — النماذج والبيانات — Data Models

> **المجلد:** `app/Models/` (46 نموذج) — `database/migrations/` (74 ملف) — `app/Enum/` (8 Enums)

---

## 1. خريطة الكيانات — ERD نصي

```
User (المستخدم — 3 أدوار)
 │
 ├───< ProviderScheduledWork (جدول العمل الأسبوعي)
 ├───< ProviderTimeOff (الإجازات)
 ├───< ProviderAttendance (الحضور)
 │
 ├───< Appointment (الحجز) ───< AppointmentService (خدمات الحجز)
 │         │                           │
 │         │                           └──> Service
 │         │                                    │
 │         ├───1 Invoice ───< InvoiceItem       ├──< ServiceCategory
 │         │         │                           ├──< ServiceTranslation
 │         │         └──< Payment (morph)        └──> User (providers) via provider_service pivot
 │         │
 │         ├──< AppointmentReminder
 │         ├──< AppointmentColor
 │         └── parent/children (self-referential — linked bookings)
 │
 ├───< UserDevice (أجهزة Push)
 ├───< RefreshToken
 ├───< PrintLog
 └───< UserSetting (إعدادات per-user)

Service ───< ServiceReview
        ───< File (morph — image/icon)

InvoiceTemplate ───< TemplateLine (header/body/footer)

Branch ───< User (providers)
       ───< SalonSchedule (ساعات عمل الفرع)
       ───< SalonSetting

Language ───< ServiceTranslation, PageTranslation, ...

ReasonLeave ───< ReasonLeaveTranslation
            ───< ProviderTimeOff

PaymentMethod ───< Payment
PrinterSetting ───< PrintLog
```

---

## 2. جدول النماذج الكامل (46 نموذج)

| # | النموذج | الجدول | الغرض | الملف |
|---|---------|--------|-------|-------|
| 1 | `User` | `users` | المستخدم (admin/provider/customer) | `app/Models/User.php:1` |
| 2 | `Appointment` | `appointments` | الحجز — الكيان المركزي | `app/Models/Appointment.php:1` |
| 3 | `AppointmentService` | `appointment_services` | خدمة داخل حجز (pivot model) | `app/Models/AppointmentService.php:1` |
| 4 | `Service` | `services` | خدمة الصالون | `app/Models/Service.php:1` |
| 5 | `ServiceCategory` | `service_categories` | فئة الخدمات | `app/Models/ServiceCategory.php:1` |
| 6 | `Invoice` | `invoices` | الفاتورة | `app/Models/Invoice.php:1` |
| 7 | `InvoiceItem` | `invoice_items` | بند فاتورة | `app/Models/InvoiceItem.php:1` |
| 8 | `Payment` | `payments` | دفعة (morph) | `app/Models/Payment.php:1` |
| 9 | `PaymentMethod` | `payment_methods` | طريقة دفع (Cash/Card/Online) | `app/Models/PaymentMethod.php:1` |
| 10 | `ProviderScheduledWork` | `provider_scheduled_works` | جدول عمل أسبوعي | `app/Models/ProviderScheduledWork.php:1` |
| 11 | `ProviderTimeOff` | `provider_time_offs` | إجازة مزود | `app/Models/ProviderTimeOff.php:1` |
| 12 | `ProviderAttendance` | `provider_attendances` | حضور مزود | `app/Models/ProviderAttendance.php:1` |
| 13 | `SalonSchedule` | `salon_schedules` | ساعات عمل الفرع | `app/Models/SalonSchedule.php:1` |
| 14 | `InvoiceTemplate` | `invoice_templates` | قالب فاتورة | `app/Models/InvoiceTemplate.php:1` |
| 15 | `TemplateLine` | `template_lines` | سطر في قالب | `app/Models/TemplateLine.php:1` |
| 16 | `Branch` | `branches` | فرع | `app/Models/Branch.php:1` |
| 17 | `Language` | `languages` | لغة | `app/Models/Language.php:1` |
| 18 | `Otp` | `otps` | رمز تحقق | `app/Models/Otp.php:1` |
| 19 | `RefreshToken` | `refresh_tokens` | توكن تحديث | `app/Models/RefreshToken.php:1` |
| 20 | `File` | `files` | ملف (morph) | `app/Models/File.php:1` |
| 21 | `AppointmentReminder` | `appointment_reminders` | تذكير موعد | `app/Models/AppointmentReminder.php:1` |
| 22 | `UserDevice` | `user_devices` | جهاز push | `app/Models/UserDevice.php:1` |
| 23 | `ReasonLeave` | `reason_leaves` | سبب إجازة | `app/Models/ReasonLeave.php:1` |
| 24 | `ServiceReview` | `service_reviews` | تقييم خدمة | `app/Models/ServiceReview.php:1` |
| 25 | `SalonSetting` | `salon_settings` | إعداد صالون (key-value) | `app/Models/SalonSetting.php:1` |
| 26 | `AppSetting` | `app_settings` | إعداد تطبيق (catalog) | `app/Models/AppSetting.php:1` |
| 27 | `UserSetting` | `user_settings` | إعداد مستخدم (override) | `app/Models/UserSetting.php:1` |
| 28 | `PrinterSetting` | `printer_settings` | إعداد طابعة | `app/Models/PrinterSetting.php:1` |
| 29 | `PrintLog` | `print_logs` | سجل طباعة | `app/Models/PrintLog.php:1` |
| 30 | `Slider` | `sliders` | سلايدر | `app/Models/Slider.php:1` |
| 31 | `SliderItem` | `slider_items` | عنصر سلايدر | `app/Models/SliderItem.php:1` |
| 32 | `CmsPage` | `cms_pages` | صفحة CMS | `app/Models/CmsPage.php:1` |
| 33 | `SamplePage` | `sample_pages` | صفحة ثابتة | `app/Models/SamplePage.php:1` |
| 34 | `LandingSection` | `landing_sections` | قسم هبوط | `app/Models/LandingSection.php:1` |
| 35 | `Color` | `colors` | لون (للمواعيد) | `app/Models/Color.php:1` |
| 36 | `AppointmentColor` | `appointment_colors` | لون موعد | `app/Models/AppointmentColor.php:1` |
| 37 | `DashboardMessage` | `dashboard_messages` | رسالة لوحة | `app/Models/DashboardMessage.php:1` |
| 38 | `AboutUsPage` | `about_us_pages` | صفحة من نحن | `app/Models/AboutUsPage.php:1` |
| 39 | `PasswordResetGrant` | `password_reset_grants` | منحة reset | `app/Models/PasswordResetGrant.php:1` |
| 40 | `SavePaymentMethod` | `save_payment_methods` | طريقة دفع محفوظة | `app/Models/SavePaymentMethod.php:1` |
| 41-46 | `Translation/*` | `*_translations` | ترجمات | `app/Models/Translation/` |

---

## 3. Pivot Tables

| الجدول | يربط | أعمدة إضافية |
|--------|------|--------------|
| `provider_service` | `User` ↔ `Service` | `is_active`, `custom_price`, `custom_duration`, `notes` |
| `appointment_services` | `Appointment` ↔ `Service` | `service_name`, `duration_minutes`, `price`, `sequence_order` |

---

## 4. الـ Scopes الحرجة (Single Source of Truth)

| Scope | الملف | ماذا يفعل | من يستخدمه |
|-------|-------|-----------|------------|
| `scopeBlocksProviderTime()` | `Appointment.php:150` | `created_status=1 AND status IN (PENDING,COMPLETED)` | التوفر + الحجز |
| `scopeOverlapping($start, $end)` | `Appointment.php:165` | `start < end2 AND end > start2` (نصف مفتوح) | التوفر + الحجز |
| `scopeCoveringDate($date)` | `ProviderTimeOff.php:40` | `start_date <= date AND COALESCE(end_date,start_date) >= date` | التوفر + الحجز |
| `blockedWindowOn($date)` | `ProviderTimeOff.php:80` | نافذة المحجوب في يوم محدد | التوفر + الحجز |
| `blocksWindow($start,$end)` | `ProviderTimeOff.php:110` | هل يحجب هذه الفترة؟ | الحجز |

> **القاعدة:** لا تعِد كتابة هذه الشروط inline — استخدم الـ Scopes دائمًا. كان اختلافها هو Bugs `BOOK-01` و `BOOK-04`.

---

## 5. الملفات التفصيلية

| الملف | المحتوى |
|-------|---------|
| [`user.md`](user.md) | `User` — الحقول، العلاقات، الأدوار، `canAccessPanel` |
| [`appointment.md`](appointment.md) | `Appointment` + `AppointmentService` — كل الحقول والـ Scopes والـ Accessors |
| [`service.md`](service.md) | `Service` + `ServiceCategory` + الترجمة + pivot |
| [`invoice-and-payment.md`](invoice-and-payment.md) | `Invoice` + `InvoiceItem` + `Payment` + الترقيم |
| [`scheduling-models.md`](scheduling-models.md) | `ProviderScheduledWork` + `ProviderTimeOff` + `SalonSchedule` |
| [`template-models.md`](template-models.md) | `InvoiceTemplate` + `TemplateLine` |
| [`supporting-models.md`](supporting-models.md) | باقي النماذج (Branch, Otp, File, ...) |
| [`enums.md`](enums.md) | كل الـ Enums |

---

*التالي: [`user.md`](user.md)*
