# Staff Dashboard

> **الملفات:** `app/Livewire/StaffDashboard.php:1`، `resources/views/livewire/staff-dashboard.blade.php:1`، `routes/web.php:30`

---

## 1. ما هو؟

لوحة يومية للموظفين على `/` (subdomain منفصل عن `/admin`). **Livewire component واحد كبير** يدير كل شيء بدون reload.

## 2. الميزات

| الميزة | الوصف |
|--------|-------|
| **Timeline يومي** | كل مزود له خط زمني يُظهر المواعيد + الفجوات |
| **العمال غير المتاحين** | غير المداوم / في إجازة يوم كامل: مخفي افتراضياً، يُظهَر ويُخفى من checkbox الفريق أو زر "إظهار غير المتاحين" (`toggleProvider` / `toggleUnavailableProviders`)؛ عموده مخطّط + شارة السبب. الاختيار يرجع للافتراضي عند تغيير التاريخ |
| **إضافة إجازة** | `saveTimeOffFromAlpine()` → إن وُجدت حجوزات مفتوحة يعيد خطة بدل الحفظ؛ المودال: حجوزات ← "محاولة الترحيل" ← جدول الخطة ← `confirmTimeOffReassignment()`. الإلغاء يتطلب `cancel_appointment`. التفاصيل: [`time-off-logic.md` §5](../06-booking-flow/time-off-logic.md) |
| **Drag & Drop** | سحب موعد لنقله — `updateAppointment()` مع `lockUsers` + `assertNoConflictingAppointment` |
| **إضافة خدمة** | `addServiceToBooking()` — same_provider / child_created + `GapAnalysis` + `Push` |
| **معالجة الدفع** | `InvoiceFinalizationService::finalizeAppointmentPayment()` — cash/card + `DocumentNumberGenerator` |
| **الحضور** | `AttendanceService` — check-in/out، multi-session |
| **بحث عملاء** | `CustomerLookup` — بالاسم/البريد/الهاتف |
| **التقارير** | `StaffStats` + `StaffReports` — إحصائيات يومية |

## 3. الحماية

```
routes/web.php:30 — EnsureStaffDashboardAccess
  → hasStaffRole() && is_active
  → وإلا redirect /login
  → /login نفسه خارج الـ middleware لتجنب loop
```

`force_booking` permission → يرفع `bypass_availability` + `allow_customer_overlap` server-side فقط.

## 4. Attendance

```
AttendanceService::checkIn(providerId) → ProviderAttendance (open session)
AttendanceService::checkOut(providerId) → close session
AttendanceBoardService → تجميع للعرض: per-provider status, last 3 days, timeline %
```

- لا جلستين مفتوحتين لنفس المزود في نفس اليوم.

---

*التالي: [`livewire-components.md`](livewire-components.md)*
