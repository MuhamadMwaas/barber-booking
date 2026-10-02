# Print API + فواتير العميل

> **الملفات:** `app/Http/Controllers/PrintController.php:1`، `app/Services/Print/PrintService.php:1`، `app/Policies/InvoicePolicy.php:1`، `app/Policies/AppointmentTicketPolicy.php:1`، `app/Http/Controllers/Api/MyInvoiceController.php:1`، `app/Http/Controllers/InvoiceCopyController.php:1`

---

## 0. قاعدة الصلاحيات (AUTHZ-03)

`auth` يجيب «من أنت؟» فقط. **كل** مسار يستقبل رقم فاتورة/موعد يسأل الـ Policy «هل يحقّ لك؟» **قبل أي عمل** — فالرفض لا يكتب `PrintLog` ولا يزيد `print_count`.

| الفعل | من؟ | القاعدة |
|---|---|---|
| **view** (قراءة) | العميل | فاتورته هو (`invoices.customer_id`) **و** حالتها `PAID`. ملكية لا صلاحية — لا دور يتجاوزها. |
| **print** (طباعة) | الموظف | `isActiveStaff()` **و** `Invoice:print` **و** واحد من: `Invoice:print_others`، أو خدم أي جزء نشط من مجموعة الحجز (parent/children)، أو هو من قبض الدفعة (`invoice_data.finalized_by_id`). SuperAdmin يتجاوز. |
| **تذكرة الموعد** | الموظف | `isActiveStaff()` **و** `StaffDashboard:print_ticket` **و** (غير مزوّد، أو حجزه، أو لديه `StaffDashboard:view_team`). |

الصلاحيات الجديدة (مجموعة `Invoice` في شاشة الأدوار):

| Permission | admin / SuperAdmin | manager | provider |
|---|---|---|---|
| `Invoice:print` | ✅ | ✅ | ✅ |
| `Invoice:print_others` | ✅ | ✅ | ❌ |

> **لماذا ليست `StaffDashboard:edit_others`؟** لأن السماح للمزوّد بتعديل حجز زميله ليس إذناً بقراءة فاتورة زبون الزميل. `edit_others` ممنوح للمزوّدين افتراضياً؛ `print_others` لا.
>
> **لماذا «من قبض الدفعة»؟** مزوّد لديه `take_payment` قد يقبض من زبون زميله، وواجب تسليم الإيصال (Belegausgabepflicht) يقع على من قبض.

تُنشأ الصلاحيتان وتُسندان بالـ migration `2026_09_27_000001_add_invoice_print_permissions` (إضافة فقط — لا تمسح تعديلات الأدوار من الشاشة). لا تُشغّل `RoleSeeder` على الإنتاج: هو **syncPermissions** ويمسح التخصيصات.

---

## 1. فواتير العميل — قراءة فقط (موبايل)

```
GET /api/my/invoices?per_page=15        (auth:sanctum + verified.customer, throttle 60/min)
GET /api/my/invoices/{id}
```

- يعرض فقط فواتير العميل **المدفوعة**. المسودة لا تظهر.
- فاتورة عميل آخر → **404** (كأنها غير موجودة — لا يمكن تخمين الأرقام).
- لا يُرجِع `invoice_data` ولا `notes` ولا عدّادات الطباعة.
- كل عنصر يحمل `view_url` + `view_url_expires_at`:

```json
{
  "id": 42, "invoice_number": "RE-2026-000042", "status": "PAID",
  "subtotal": 84.03, "tax_rate": 19, "tax_amount": 15.97,
  "discount_amount": 0, "total_amount": 100, "tip_amount": 5, "paid_amount": 105,
  "appointment": { "id": 7, "number": "APT-…", "appointment_date": "2026-09-09" },
  "items": [ { "description": "Hair Cut", "quantity": 1, "unit_price": 100, "tax_rate": 19, "tax_amount": 15.97, "total_amount": 100 } ],
  "view_url": "https://…/my/invoices/42/view?customer=5&expires=…&signature=…",
  "view_url_expires_at": "2026-09-27 10:15:00"
}
```

### `view_url` — عرض HTML في WebView

```
GET /my/invoices/{invoice}/view?customer=…&expires=…&signature=…   (signed, throttle 30/min)
```

- الـ WebView لا يحمل Bearer token، لذلك الرابط **موقّع ومؤقت (15 دقيقة)**. التوقيع هو الاعتماد.
- **اطلب رابطاً جديداً في كل مرة** من `GET /api/my/invoices/{id}` — لا تخزّنه.
- التوقيع يربط الفاتورة **و** العميل؛ والسيرفر يعيد فحص `InvoicePolicy::view` عند الفتح، فالرابط يتوقف إن تغيّر مالك الفاتورة أو خرجت من `PAID`.
- العرض **لا يطبع**: لا `PrintLog`، لا `print_count`، لا auto-print، ويُعلَّم دائماً `(Kopie)` / `(COPY)`.
- تعديل الرقم في الرابط → 403 (توقيع غير صالح).

---

## 2. طباعة الموظفين — API (staff only)

```
POST /api/invoice/{invoice}/print        → Gate: print
POST /api/invoices/print-batch           → invoice_ids: 1..50، وكل فاتورة تُفحص؛ واحدة أجنبية = 403 للدفعة كلها
GET  /api/invoice/{invoice}/print-url    → Gate: print
POST /api/printer/{printer}/test         → PrinterSetting:edit
GET  /api/print/statistics               → PrintLog:view
GET  /api/print/logs?limit=1..100        → PrintLog:view
```

توكن العميل يأخذ **403** من كل هذه المسارات — حتى لفاتورته هو. العميل يقرأ عبر `/api/my/invoices`.

## 3. Web Print — `routes/web.php`

```
GET /invoice/{invoice}/print                  (auth) → Gate: print
GET /invoices/print-batch?invoice_ids=1,2,3   (auth) → validation (1..50) + Gate لكل فاتورة
GET /appointment/{appointment}/print          (auth) → Gate: printAppointmentTicket
GET /invoice-template/{template}/preview      (auth + can:InvoiceTemplate:view)
```

- الضيف على أي مسار `auth` يُحوَّل إلى `staff.dashboard.login` (كان 500 لعدم وجود route اسمه `login`).
- الأخطاء لا تُسرِّب `getMessage()` للمتصفح — تُسجَّل بـ `report()`.
- أزرار الطباعة في StaffDashboard و Filament (Appointments، PrintLogs) تُخفى لمن ترفضه الـ Policy.
- `PrintService` يبني HTML عبر `TemplateBuilderService` + `PrinterSetting`.
- `print_count` → `COPY` label: `""` → `"(COPY)"` → `"(COPY 2)"` — `Invoice::getCopyLabel()`.

الاختبارات: `tests/Feature/Authorization/InvoiceIdorTest.php`.

---

*التالي: [`06-booking-flow/README.md`](../06-booking-flow/README.md)*
