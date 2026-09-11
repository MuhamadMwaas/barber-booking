# 07 — لوحة الإدارة Filament

> **المجلد:** `app/Filament/` (142 ملف) — `resources/views/filament/` — `resources/views/livewire/`

---

## 1. نظرة عامة

| المكون | المسار | الوصف |
|--------|--------|-------|
| **Filament Panel** | `/admin` | لوحة الإدارة الرئيسية — 18 Resource |
| **Staff Dashboard** | `/` (subdomain) | لوحة الموظفين اليومية — Livewire |
| **Livewire Components** | `app/Livewire/` | 7 Components (ScheduleManager, ...) |

---

## 2. Filament Resources — 18 Resource

| Resource | المسار | العمليات |
|----------|--------|----------|
| **Appointments** | `Filament/Resources/Appointments/` | عرض/إنشاء/تعديل/إلغاء المواعيد |
| **Providers** | `Filament/Resources/Providers/` | إدارة المزودين + خدماتهم + جداولهم + إجازاتهم |
| **Services** | `Filament/Resources/Services/` | CRUD خدمات + ترجمات + تسعير |
| **Service Categories** | `Filament/Resources/ServiceCategories/` | فئات الخدمات |
| **Users** | `Filament/Resources/Users/` | إدارة المستخدمين |
| **Salon Settings** | `Filament/Resources/SalonSettings/` | إعدادات key-value |
| **Invoice Templates** | `Filament/Resources/InvoiceTemplates/` | تصميم القوالب |
| **Languages** | `Filament/Resources/Languages/` | اللغات |
| **Reason Leaves** | `Filament/Resources/ReasonLeaves/` | أسباب الإجازة |
| **Printer Settings** | `Filament/Resources/PrinterSettings/` | إعدادات الطابعة |
| **Print Logs** | `Filament/Resources/PrintLogs/` | سجل الطباعة |
| **Colors** | `Filament/Resources/Colors/` | ألوان المواعيد |
| **Provider Attendances** | `Filament/Resources/ProviderAttendances/` | حضور المزودين |
| **Provider Scheduled Works** | `Filament/Resources/ProviderScheduledWorks/` | جداول العمل |
| **Cms Pages** | `Filament/Resources/CmsPages/` | صفحات CMS |
| **Pages** | `Filament/Resources/Pages/` | صفحات ثابتة |
| **Roles** | `Filament/Resources/Roles/` | الأدوار والصلاحيات |
| **Customers** | `Filament/Resources/Customers/` | العملاء |

### هيكل كل Resource

```
Appointments/
├── AppointmentResource.php        ← تعريف Resource (model, navigation, permissions)
├── Pages/
│   ├── ListAppointments.php       ← جدول + فلاتر + actions
│   ├── CreateAppointment.php
│   ├── EditAppointment.php
│   └── ViewAppointment.php
├── Schemas/
│   ├── AppointmentForm.php        ← حقول النموذج (Forms)
│   └── AppointmentInfolist.php    ← عرض التفاصيل (Infolists)
└── Tables/
    └── AppointmentsTable.php      ← أعمدة الجدول + فلاتر
```

---

## 3. الصفحات المخصصة — Filament Pages

| الصفحة | الملف | الوصف |
|--------|-------|-------|
| **Manage Provider Schedules** | `Filament/Pages/ManageProviderSchedules.php` | إدارة أسبوعية مرئية لجداول المزودين |
| **Manage Provider Leaves** | `Filament/Pages/ManageProviderLeaves.php` | إدارة الإجازات |
| **Manage Salon Schedules** | `Filament/Pages/ManageSalonSchedules.php` | ساعات عمل الصالون |
| **View Provider Schedule Timeline** | `Filament/Pages/ViewProviderScheduleTimeline.php` | Timeline مرئي |

---

## 4. Staff Dashboard — `app/Livewire/StaffDashboard.php`

لوحة يومية للموظفين — **Livewire component واحد كبير** على `/` (subdomain).

**الميزات:**

- تقويم يومي + Timeline لكل مزود
- سحب وإفلات (drag & drop) لنقل المواعيد
- إضافة خدمة لحجز قائم (`addServiceToBooking`)
- معالجة الدفع (`InvoiceFinalizationService::finalizeAppointmentPayment`)
- الحضور (`AttendanceService`)
- البحث عن عملاء (`CustomerLookup`)
- التقارير (`StaffReports`, `StaffStats`)

**المسار:** `routes/web.php:30` — `EnsureStaffDashboardAccess` middleware.

انظر [`staff-dashboard.md`](staff-dashboard.md) للتفصيل.

---

## 5. Livewire Components — `app/Livewire/` + `resources/views/livewire/`

| Component | الوصف |
|-----------|-------|
| `StaffDashboard` | اللوحة الرئيسية |
| `ScheduleManager` | إدارة جدول مزود |
| `ShiftManager` | إدارة مناوبة |
| `WeeklyScheduleTimeline` | Timeline أسبوعي |
| `SalonScheduleManager` | إدارة ساعات الصالون |
| `CustomerLookup` | بحث عملاء |
| `StaffStats` / `StaffReports` | إحصائيات وتقارير |

---

*التالي: [`08-invoicing-printing/README.md`](../08-invoicing-printing/README.md)*
