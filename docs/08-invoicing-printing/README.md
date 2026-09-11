# 08 — الفوترة والطباعة — Invoicing & Printing

> **الملفات:** `app/Models/Invoice.php:1`، `app/Services/InvoiceService.php:1`، `app/Services/InvoiceFinalizationService.php:1`، `app/Services/Print/PrintService.php:1`

---

## 1. دورة حياة الفاتورة

```
الحجز                          الدفع عند الكاشير              الطباعة
─────                          ────────────────              ───────
BookingService                 InvoiceFinalizationService     PrintController
  │                              │                              │
  ├─ Appointment                 ├─ lock invoice owner         ├─ getTemplateOrDefault()
  ├─ Invoice DRAFT               ├─ validate Cash/Card         ├─ render lines (header→body→footer)
  │   invoice_number = NULL      ├─ rebuildAggregatedInvoice   ├─ incrementPrintCount()
  │   status = DRAFT             ├─ assign INV-2026-000001     ├─ PrintLog::create()
  │   لا Payment                 ├─ create Payment (1)         └─ HTML للطباعة
  │                              ├─ mark COMPLETED             └─ COPY label للطبعات التالية
  │                              └─ tse_enabled=false
  │
  └─ لا تُعتبر مستندًا قانونيًا   └─ تُعتبر مستندًا قانونيًا
     لا تُطبع كأصل                  تُطبع وتُحفظ
     لا تستهلك رقمًا                تستهلك رقمًا متسلسلًا
```

---

## 2. التسعير — GROSS دائمًا

- كل `InvoiceItem.total_amount` هو GROSS (شامل الضريبة).
- `Invoice.subtotal/tax_amount/total_amount` تُحسب عبر `TaxCalculatorService` من `items`.
- `discount_amount` إن وجد يُطرح من `gross` ثم يُعاد حساب `net/tax`.

---

## 3. الترقيم المتسلسل

```
INV-2026-000001
PAY-2026-000001
APT-20260911-A1B2C3 (عشوائي + حلقة exists + قيد فريد)
```

- `DocumentNumberGenerator::next('invoice')` داخل `DB::transaction` + `SELECT FOR UPDATE` على `document_counters`.
- الفجوات ممنوعة قانونيًا — الرقم يُحجز داخل transaction.

---

## 4. نظام القوالب

- `InvoiceTemplate` (header/body/footer) + `TemplateLine` (16 نوع) — `config/invoice-line-types.php`
- `TemplateBuilderService` يجمع الـ lines ويرندر Blade لكل نوع.
- `resources/views/invoices/line-types/` — 16 Blade partial.

---

## 5. الملفات التفصيلية

| الملف | المحتوى |
|-------|---------|
| [`invoice-lifecycle.md`](invoice-lifecycle.md) | DRAFT → PAID + `rebuildAggregatedInvoice` + `finalizeAppointmentPayment` |
| [`template-system.md`](template-system.md) | القوالب + أنواع الأسطر + `TemplateBuilderService` |
| [`printing-flow.md`](printing-flow.md) | `PrintController` + `PrintService` + `PrintLog` + عداد الطباعة |

---

*التالي: [`09-security/README.md`](../09-security/README.md)*
