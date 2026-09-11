# إضافة خدمة لحجز قائم

> **الملفات:** `app/Services/BookingService.php:461` (`addServiceToBooking`)، `app/Services/GapAnalysisService.php:1`، `app/Services/PushBookingsService.php:1`

---

## 1. متى؟

الموظف في Staff Dashboard يريد إضافة خدمة لحجز `PENDING` (DRAFT) لم يُدفع بعد.

## 2. النمطان

| النمط | الشرط | ماذا يحدث |
|-------|-------|-----------|
| `same_provider` | `provider_id` نفس الـ anchor | إضافة `AppointmentService` + تعديل `start/end` + إعادة إجماليات |
| `child_created` | مزود مختلف | إنشاء `Appointment` ابن (`parent_appointment_id = parent.id`) |

## 3. التدفق

```
addServiceToBooking(anchor, {service_id, provider_id, placement:before/after, duration, start_time, apply_push})
  ├─ canAcceptNewService() — PENDING + DRAFT فقط
  ├─ GapAnalysisService@analyzeAddBefore/After أو @analyzeChildAdd
  │    └─ هل توجد فجوة؟ هل المزود متاح؟ هل نحتاج push؟
  ├─ إن requires_push && !apply_push → throw PushRequiredException (confirm)
  └─ DB::transaction
       ├─ lockUsers([newProvider, anchor.provider])
       ├─ assertNoConflictingAppointment (إعادة فحص تحت القفل)
       ├─ executePushPlan (إن طُلب)
       ├─ addServiceSameProvider أو addServiceDifferentProvider
       └─ rebuildAggregatedInvoice(invoiceOwner)
```

---

*التالي: [`07-filament-admin/README.md`](../07-filament-admin/README.md)*
