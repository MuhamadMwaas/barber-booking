# Livewire Components

> **المجلد:** `app/Livewire/` (7) + `resources/views/livewire/` (7) + `resources/views/filament/forms/components/`

---

## 1. القائمة

| Component | الملف | الوصف |
|-----------|-------|-------|
| `StaffDashboard` | `app/Livewire/StaffDashboard.php` | اللوحة الرئيسية — كل شيء |
| `ScheduleManager` | `app/Livewire/ScheduleManager.php` | إدارة جدول مزود (CRUD لـ ProviderScheduledWork) |
| `ShiftManager` | `app/Livewire/ShiftManager.php` | إدارة مناوبة واحدة |
| `WeeklyScheduleTimeline` | `app/Livewire/WeeklyScheduleTimeline.php` | Timeline أسبوعي مرئي |
| `SalonScheduleManager` | `app/Livewire/SalonScheduleManager.php` | ساعات عمل الصالون |
| `CustomerLookup` | `app/Livewire/CustomerLookup.php` | بحث عملاء (Livewire + Blade) |
| `StaffStats` / `StaffReports` | `app/Livewire/StaffStats.php` | إحصائيات |

## 2. Filament Form Components (Blade)

| Component | الوصف |
|-----------|-------|
| `provider-schedule-manager.blade.php` | واجهة إدارة الجدول داخل Filament |
| `weekly-schedule-timeline-field.blade.php` | حقل Timeline أسبوعي |
| `appointment-timeline.blade.php` | Timeline موعد |
| `duration-display.blade.php` | عرض المدة |
| `schedule-instructions.blade.php` | تعليمات |

## 3. كيف يعمل Livewire هنا؟

```
Browser (Alpine.js) ←→ Livewire Server (PHP) via AJAX
  - كل تفاعل (drag, click, input) يرسل request لـ Livewire
  - PHP يحدّث state + يعيد render Blade + يرسل patch
  - لا reload، لا SPA framework منفصل
```

- Filament نفسه مبني على Livewire — نفس المحرك.

---

*التالي: [`08-invoicing-printing/README.md`](../08-invoicing-printing/README.md)*
