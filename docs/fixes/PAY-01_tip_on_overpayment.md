# PAY-01 — زيادة مبلغ الدفع مرفوضة، ورسالة الخطأ بالعربي على شاشة ألمانية

> **التاريخ:** 25 سبتمبر 2026
> **المصدر:** بلاغ: «عند تأكيد الدفع زدتُ المبلغ فظهرت رسالة بعدم إمكانية زيادة السعر، والرسالة ليست بلغة المستخدم»
> **الحالة:** ✅ **مُصلحة ومُتحقَّق منها بالاختبارات** (لم تُجرَّب في المتصفح بعد)
> **الشرح التفصيلي (قبل/بعد لكل تعديل):** [`PAY-01_tip_on_overpayment_DETAILED_AR.md`](PAY-01_tip_on_overpayment_DETAILED_AR.md)
> **الاختبارات:** `tests/Feature/Money/UnifiedPaymentFlowTest.php` (بقشيش، لا بقشيش، الإيصال، اللغات) + `tests/Feature/DailyReportTest.php` (تقرير البقشيش والتوزيع التناسبي)

---

## 1. المشكلة

في مودال «Zahlung verarbeiten» كتب الكاشير مبلغاً أكبر من مجموع الفاتورة ثم ضغط «Bestätigen & Rechnung drucken»، فظهر:

```
مبلغ الدفع لا يمكن أن يتجاوز مجموع خدمات الفاتورة.
```

الخلل هنا خللان:

1. **منع مقصود لم يعد صحيحاً.** `InvoiceFinalizationService::assertValidFinalAmount()` كان يرفض أي مبلغ أكبر من مجموع البنود، و`InvoiceService::applyFinalAmount()` يقصّه للمجموع احتياطاً. المطلوب تجارياً: **التنقيص حسم، والزيادة بقشيش.**
2. **الرسائل مكتوبة بالعربي داخل الكود.** كل رسائل الخدمة الثماني كانت نصوصاً عربية ثابتة، والداشبورد يعرض `$e->getMessage()` كما هي.

## 2. القرار

| ما يكتبه الكاشير | النتيجة |
|---|---|
| = المجموع | دفع كامل |
| < المجموع | **حسم**: السلوك الحالي كما هو بلا تغيير (`discount_amount`) |
| > المجموع | **بقشيش**: الفاتورة تُحصَّل بالمجموع الكامل، والفرق في `tip_amount` |
| ≤ 0 | مرفوض برسالة مترجمة |

**البقشيش للحلاق الذي خدم.** البقشيش الذي يدفعه الزبون طوعاً للموظف ليس إيراداً للصالون ولا ضريبة عليه، لذلك:

- **لا يدخل** في `total_amount` / `subtotal` / `tax_amount`، ولا في أي رقم مبيعات أو ضريبة أو إيراد.
- المعادلات السابقة تبقى صحيحة: `subtotal + tax_amount = total_amount`، و`payment.amount = invoice.total_amount`.
- البقشيش محفوظ **بجانب** هذه الأرقام: `invoices.tip_amount` و`payments.tip_amount` و`invoice_data.tip_amount`.

> ملاحظة: تصنيف البقشيش ضريبياً يُراجَع مع المحاسب. لو تقرّر أنه إيراد للصالون، يكفي تغيير نقطة واحدة هي `splitFinalAmount()`.

## 3. التغييرات

| الملف | التغيير |
|---|---|
| `database/migrations/2026_09_25_000001_add_tip_amount_to_invoices_and_payments.php` | عمود `tip_amount decimal(8,2) default 0` في `invoices` و`payments` |
| `database/migrations/2026_09_25_000002_add_tip_line_to_invoice_templates.php` | **تعديل صفوف القوالب الموجودة**، لأن الإيصال يُبنى من `template_lines` لا من الـ seeder: سطر «Trinkgeld/Tip» (`invoice.tip`, `hide_when_empty`) قبل سطر «Gegeben/Paid»، وربط هذا الأخير بـ `invoice.paid_amount`. idempotent |
| `app/Services/InvoiceFinalizationService.php` | `assertValidFinalAmount()` صارت `splitFinalAmount()` وتُرجع `[المبلغ المحصَّل، البقشيش]`. الجزء المحصَّل وحده يمر عبر `applyFinalAmount()` (حسم + ضريبة). كل الرسائل صارت `__('payment.errors.*')` |
| `lang/{ar,de,en}/payment.php` | ملف جديد لرسائل الدفع |
| `app/Models/Invoice.php`, `Payment.php` | `tip_amount` في fillable وcasts |
| `resources/views/livewire/staff-dashboard.blade.php` | مودال الدفع: سطر حيّ تحت الحقل يعرض «حسم ‎-x» بالأحمر أو «بقشيش ‎+x» بالأخضر. **البقشيش الأكبر من الفاتورة نفسها** (مثل 200 بدل 20) يتطلب تفعيل مربع تأكيد قبل أن يعمل زر الدفع |
| `lang/{ar,de,en}/dashboard.php` | `discount_note` الجديدة + `discount_label` / `tip_label` / `big_tip_confirm` |
| `app/Services/InvoiceTemplate/DynamicFieldResolver.php` | حقل `invoice.tip` (فارغ حين لا يوجد بقشيش)، وصار `invoice.paid_amount` = المجموع + البقشيش |
| `config/invoice-dynamic-fields.php` | تسجيل `invoice.tip` في قائمة حقول محرر القوالب |
| `resources/views/invoices/line-types/totals-summary.blade.php` | سطرا بقشيش ومدفوع بعد المجموع، عند وجود بقشيش فقط |
| `database/seeders/InvoiceTemplateSeeder.php` | نفس سطر البقشيش للتثبيتات الجديدة |
| `app/Services/DailyReportService.php` | `tip_share` لكل موعد بالتناسب مع سعره (الصف الأخير يمتص فرق التقريب)، قسم `tips` (نقد/بطاقة/لكل حلاق)، و`tips` في جدول الموظفين وقائمة المعاملات |
| `resources/views/reports/daily-report.blade.php` + `lang/*/z_report.php` | قسم «6 · Trinkgeld» مع «Bargeld in der Kasse = Umsatz bar + Trinkgeld bar»، وسطر بقشيش في بطاقة كل حلاق |

## 4. ما لم يتغير

- **الحسم**: نفس المسار ونفس الأرقام.
- **الفواتير السابقة**: قيمة `tip_amount` فيها 0.
- **شاشتا الدفع في Filament** (`AppointmentsTable`, `Providers\AppointmentsRelationManager`) تستدعيان الخدمة الموحّدة نفسها، فصارت الزيادة فيهما بقشيشاً تلقائياً. نص المساعدة تحت حقل المبلغ هناك ما زال يحسب الضريبة على المبلغ المكتوب كاملاً، وهذا عرض فقط ولا يؤثر على ما يُحفظ.
- `pos-receipt.blade.php`: مسار Fiskaly المعطَّل، لم يُمَس.
