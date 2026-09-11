# نظام القوالب — Template System

> **الملفات:** `app/Models/InvoiceTemplate.php:1`، `app/Models/TemplateLine.php:1`، `config/invoice-line-types.php:1`، `app/Services/InvoiceTemplate/LineTypeRegistry.php:1`، `app/Services/InvoiceTemplate/TemplateBuilderService.php:1`

---

## 1. الهيكل

```
InvoiceTemplate (1) ──< TemplateLine (N)
  │  name, is_default, paper_size, font_family, global_styles, company_info
  │
  └── header: [company_logo, company_info, ...]
  └── body:   [invoice_number, invoice_date, customer_info, items_table, totals_summary, ...]
  └── footer: [thank_you_message, tse_info, ...]
       كل line: type, order, is_enabled, properties JSON
```

## 2. أنواع الأسطر — 16 نوع

| النوع | Blade | الوصف |
|-------|-------|-------|
| `company_logo` | `image.blade.php` | شعار |
| `invoice_number` | `invoice-number.blade.php` | رقم + COPY label |
| `items_table` | `items-table.blade.php` | جدول بنود |
| `totals_summary` | `totals-summary.blade.php` | صافي/ضريبة/إجمالي |
| `qr_code` / `barcode` | `qr-code` / `barcode` | رموز |
| `text` / `separator` / `spacer` / `two_column` | `text` / ... | عناصر عامة |
| ... (16 إجمالي) | `resources/views/invoices/line-types/` | |

## 3. البناء للطباعة

```
PrintController@print(invoice)
  → template = invoice->getTemplateOrDefault()
  → lines = template->lines()->enabled()->orderBy(order)->get()->groupBy(section)
  → لكل line: Blade partial حسب type + properties + DynamicFieldResolver
  → resources/views/invoices/template-builder.blade.php يجمع header→body→footer
  → HTML + incrementPrintCount() + PrintLog
```

`LineTypeRegistry` يقرأ `config/invoice-line-types.php` — سجل الأنواع المتاحة.

`TemplateExportImportService` — تصدير/استيراد JSON للقوالب.

---

*التالي: [`printing-flow.md`](printing-flow.md)*
