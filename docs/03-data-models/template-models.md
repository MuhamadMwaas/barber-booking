# قوالب الفواتير — Invoice Templates

> **الملفات:** `app/Models/InvoiceTemplate.php:1`، `app/Models/TemplateLine.php:1`، `config/invoice-line-types.php:1`، `app/Services/InvoiceTemplate/LineTypeRegistry.php:1`

---

## 1. InvoiceTemplate — `invoice_templates` table

| العمود | النوع | الوصف |
|--------|-------|-------|
| `id` | bigint PK | المعرف |
| `name` | string | اسم القالب |
| `is_default` | boolean | افتراضي؟ (واحد فقط) |
| `is_active` | boolean | نشط |
| `language` | string | لغة القالب |
| `paper_size` | string | حجم الورق (A4, 80mm, ...) |
| `paper_width` | decimal | العرض بالملليمتر |
| `font_family` | string | خط CSS |
| `font_size` | integer | حجم الخط الأساسي |
| `global_styles` | json | ألوان، حشو، حدود |
| `company_info` | json | اسم/عنوان/هاتف/رقم ضريبي/شعار |
| `static_body_html` | text nullable | HTML ثابت للجسم |

```php
// InvoiceTemplate.php:40 — Boot
static::creating(function ($template) {
    if (!$template->company_info) {
        $template->company_info = [
            'name' => get_setting('company_name'),
            'address' => get_setting('company_address'),
            'phone' => get_setting('company_phone'),
            'tax_number' => get_setting('company_tax_number'),
        ];
    }
});
static::saving(function ($template) {
    if ($template->is_default) {
        InvoiceTemplate::where('id', '!=', $template->id)->update(['is_default' => false]);
    }
});
```

---

## 2. TemplateLine — `template_lines` table

| العمود | النوع | الوصف |
|--------|-------|-------|
| `id` | bigint PK | المعرف |
| `template_id` | FK → invoice_templates | القالب |
| `section` | enum | `header` / `body` / `footer` |
| `type` | string | نوع السطر (من LineTypeRegistry) |
| `order` | integer | ترتيب العرض داخل القسم |
| `is_enabled` | boolean | إظهار/إخفاء |
| `properties` | json | إعدادات خاصة بالنوع |

```php
// العلاقة
InvoiceTemplate::lines() → HasMany(TemplateLine)->orderBy('order')
TemplateLine::template() → BelongsTo(InvoiceTemplate)
```

---

## 3. أنواع الأسطر — 16 نوع

| النوع | القسم | الوصف | Blade |
|-------|-------|-------|-------|
| `company_logo` | header | شعار الشركة | `invoices/line-types/image.blade.php` |
| `company_info` | header | اسم/عنوان/هاتف | `customer-info.blade.php` |
| `invoice_number` | body | رقم الفاتورة + COPY label | `invoice-number.blade.php` |
| `invoice_date` | body | تاريخ الفاتورة | `invoice-date.blade.php` |
| `customer_info` | body | بيانات العميل | `customer-info.blade.php` |
| `items_table` | body | جدول البنود | `items-table.blade.php` |
| `totals_summary` | body | الصافي/الضريبة/الإجمالي | `totals-summary.blade.php` |
| `payment_info` | body | طريقة الدفع | `payment-info.blade.php` |
| `tse_info` | body | معلومات TSE (معطل) | `tse-info.blade.php` |
| `colors_used` | body | الألوان المستخدمة | `colors-used.blade.php` |
| `qr_code` | body/footer | رمز QR | `qr-code.blade.php` |
| `barcode` | body/footer | باركود | `barcode.blade.php` |
| `text` | أي | نص حر | `text.blade.php` |
| `separator` | أي | خط فاصل | `separator.blade.php` |
| `spacer` | أي | مسافة | `spacer.blade.php` |
| `two_column` | أي | عمودين | `two-column.blade.php` |

التعريف في `config/invoice-line-types.php:1` والسجل في `LineTypeRegistry.php:1`.

---

## 4. كيف يُبنى القالب للطباعة؟

```
PrintController@print($invoice)
  → $template = $invoice->getTemplateOrDefault()  // Invoice.php:120
  → $lines = $template->lines()->where('is_enabled', true)->orderBy('order')->get()
  → grouped by section: header[], body[], footer[]
  → لكل line: Blade partial حسب type + properties
  → resources/views/invoices/template-builder.blade.php — يجمع الكل
  → HTML للطباعة (browser print dialog)
  → Invoice::incrementPrintCount() + PrintLog::create()
```

انظر [`08-invoicing-printing/template-system.md`](../08-invoicing-printing/template-system.md) للتفصيل.

---

*التالي: [`supporting-models.md`](supporting-models.md)*
