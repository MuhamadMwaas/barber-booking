# Filament Resources بالتفصيل

> **المجلد:** `app/Filament/Resources/` (142 ملف) — 18 Resource

---

## 1. مثال: Appointments

```
Appointments/
├── AppointmentResource.php — getModel(), navigationIcon, navigationGroup, canAccess()
├── Pages/
│   ├── ListAppointments.php — table + filters (status, provider, date)
│   ├── CreateAppointment.php — form + BookingValidationService
│   ├── EditAppointment.php
│   └── ViewAppointment.php — infolist + print action
├── Schemas/
│   ├── AppointmentForm.php — fields: provider, services (repeater), date, times, notes
│   └── AppointmentInfolist.php — entries: status, customer, services, invoice
├── Tables/
│   └── AppointmentsTable.php — columns, filters, actions (cancel, print, finalize)
└── Widgets/ (إن وجد)
```

## 2. Providers — الأغنى

```
Providers/
├── ProviderResource.php
├── Pages/{List,Create,Edit,View}Provider.php
├── Schemas/{ProviderForm,ProviderInfolist}.php
├── Tables/ProvidersTable.php
├── RelationManagers/
│   ├── ScheduledWorksRelationManager.php
│   ├── TimeOffsRelationManager.php
│   ├── AttendancesRelationManager.php
│   └── AppointmentsRelationManager.php
└── Widgets/
    ├── ProviderStatsOverview.php
    └── ProviderLeaveStats.php
```

## 3. InvoiceTemplates — Line Builder

```
InvoiceTemplates/
├── InvoiceTemplateResource.php
├── Schemas/InvoiceTemplateForm.php — repeater لـ TemplateLine (header/body/footer)
└── Tables/InvoiceTemplatesTable.php
```

- كل line: `type` (من `invoice-line-types.php`) + `properties` JSON + `is_enabled`.
- Preview: `InvoiceTemplateController@preview` — `resources/views/invoices/template-builder.blade.php`.

## 4. قائمة كل Resources والـ CRUD

| Resource | Create | Edit | View | List | RelationManagers |
|----------|--------|------|------|------|-----------------|
| Appointments | ✅ | ✅ | ✅ | ✅ | — |
| Providers | ✅ | ✅ | ✅ | ✅ | TimeOffs, ScheduledWorks, Attendances, Appointments |
| Services | ✅ | ✅ | ✅ | ✅ | Providers, Appointments |
| ServiceCategories | ✅ | ✅ | ✅ | ✅ | — |
| Users | ✅ | ✅ | ✅ | ✅ | Services, CustomerAppointments |
| SalonSettings | ✅ | ✅ | ✅ | ✅ | — |
| InvoiceTemplates | ✅ | ✅ | ✅ | ✅ | — |
| Languages | ✅ | ✅ | ✅ | ✅ | — |
| ReasonLeaves | ✅ | ✅ | ✅ | — | — |
| PrinterSettings | ✅ | ✅ | — | ✅ | — |
| PrintLogs | — | — | ✅ | ✅ | — |
| Colors | ✅ | ✅ | ✅ | ✅ | — |
| ProviderAttendances | ✅ | ✅ | ✅ | ✅ | — |
| ProviderScheduledWorks | ✅ | ✅ | ✅ | ✅ | — |
| CmsPages | ✅ | ✅ | — | ✅ | — |
| Pages | — | ✅ | ✅ | ✅ | — |
| Roles | ✅ | ✅ | ✅ | ✅ | Users, Permissions |
| Customers | ✅ | ✅ | ✅ | ✅ | — |

---

*التالي: [`staff-dashboard.md`](staff-dashboard.md)*
