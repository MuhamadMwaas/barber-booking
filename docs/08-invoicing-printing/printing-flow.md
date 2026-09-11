# تدفق الطباعة — Printing Flow

> **الملفات:** `app/Http/Controllers/PrintController.php:1`، `app/Services/Print/PrintService.php:1`، `app/Models/PrintLog.php:1`، `app/Models/PrinterSetting.php:1`

---

## 1. التدفق

```
Staff clicks "Print" (Filament action أو Staff Dashboard)
  │
  ├─ GET /invoice/{invoice}/print (web, auth) أو POST /api/invoice/{invoice}/print (API)
  │
  ├─ PrintController@print / @apiPrint
  │    ├─ load Invoice with appointment, customer, items
  │    ├─ template = invoice->getTemplateOrDefault()
  │    ├─ lines = template->lines()->enabled()->orderBy(order)->groupBy(section)
  │    ├─ render: header lines → body lines → footer lines
  │    │    └─ كل line → Blade partial حسب type + properties
  │    ├─ Invoice::incrementPrintCount() → print_count++, first_printed_at/last_printed_at
  │    ├─ PrintLog::create({invoice_id, printer_id, printed_by, printed_at})
  │    └─ return HTML (browser print dialog) أو JSON {url}
  │
  └─ Browser: window.print() أو API client يعرض HTML
```

## 2. عداد الطباعة

| الحقل | القيمة |
|-------|--------|
| `print_count` | 0 → 1 أول طباعة، 2 ثانية، ... |
| `first_printed_at` | وقت أول طباعة |
| `last_printed_at` | وقت آخر طباعة |
| `getCopyLabel()` | `""` أول مرة، `"(COPY)"` ثانية، `"(COPY 2)"` ثالثة — `Invoice.php:140` |

## 3. الطباعة الدفعية

```
POST /api/invoices/print-batch { invoice_ids: [1,2,3] }
  → PrintController@apiPrintBatch → لكل invoice: نفس التدفق → ZIP أو merged HTML
```

## 4. PrinterSetting

| العمود | الوصف |
|--------|-------|
| `name` | اسم الطابعة |
| `connection` | نوع الاتصال |
| `paper_width` | عرض الورق |
| `is_default` | افتراضية؟ |

`PrinterSettingSeeder` ينشئ طابعة افتراضية.

---

*التالي: [`09-security/README.md`](../09-security/README.md)*
