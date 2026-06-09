<div dir="rtl">

# خصم الدفع على الفاتورة — وثيقة هندسية عميقة للتعديل

> **التاريخ:** 2026-06-08
> **النطاق:** توحيد منطق الخصم عند الدفع عبر **StaffDashboard** و **Filament** و **طبقة الخدمات** و **موديل الفاتورة** و **قوالب الطباعة**، بحيث يظهر الخصم على الإيصال المطبوع بشكل صحيح ومتسق، بلا منطق متضارب.

---

## 1. المشكلة الأصلية

عند الدفع، يكتب الموظف المبلغ النهائي في حقل `paymentAmount` (مثلاً يخفّض 40€ إلى 30€ كخصم). الكود القديم كان:

```php
// StaffDashboard::processPayment()  (قبل)
if ((float) $this->paymentAmount != (float) $invoice->total_amount) {
    $invoice->update(['total_amount' => $this->paymentAmount]); // يغيّر total فقط
    $invoice->refresh();
}
```

**النتيجة المعطوبة على الطباعة** (ضريبة 19%): تبقى `subtotal`/`tax_amount` محسوبة على 40 بينما `total_amount` = 30:

```
Netto:   33.61   ← صافي 40 (قديم)
MwSt:     6.39   ← ضريبة 40 (قديم)
Summe:   30.00   ← الجديد   →  33.61 + 6.39 = 40 ≠ 30  (رياضيًا مكسور، بلا أي سطر خصم)
```

بالإضافة لذلك كان هناك **ثلاثة مسارات دفع متضاربة** ودالة مجاميع بصيغة ضريبة خاطئة (أمامية) — مفصّلة في القسم 9.

---

## 2. القرار المعماري: مصدر حقيقة واحد

كل المسارات تمر الآن عبر دالة واحدة هي القلب:

```
InvoiceService::applyFinalAmount(Invoice $invoice, ?float $finalGross)
```

وكل الأرقام تُشتق منها وفق **نموذج الأسعار الإجمالية الألماني (GROSS)**:

```
itemsGross = Σ items.total_amount              ← المرجع: مجموع بنود الفاتورة (قبل الخصم)
final      = clamp(finalGross, 0 .. itemsGross) ← null = ادفع الكامل (بلا خصم)
discount   = itemsGross − final                ← لا يكون سالبًا أبدًا (الدفع الزائد ليس خصمًا)
{net,tax}  = reverseTax(final)                 ← استخراج عكسي: net + tax == final تمامًا
```

**تخزين ذكي:** لا نخزّن `items_total`؛ نشتقّه: `items_total = total_amount + discount_amount`. لذلك أُضيف **عمود واحد فقط** `discount_amount` (كما تم الاتفاق).

### النموذج المحاسبي للمثال 40€ → 30€ (ضريبة 19%)

```
Artikel gesamt:    40.00   (= total 30 + discount 10)
Rabatt:           -10.00
Netto:             25.21   (= 30 ÷ 1.19)
MwSt (19%):         4.79
Summe:             30.00   (25.21 + 4.79 = 30.00 ✓)
```

---

## 3. التغييرات ملفًا بملف (قبل / بعد / قلب التعديل)

### 3.1 Migration جديد — عمود `discount_amount`
**الملف:** `database/migrations/2026_06_08_000001_add_discount_amount_to_invoices_table.php` (جديد)

**قلب التعديل:**
```php
$table->decimal('discount_amount', 8, 2)->default(0)->after('total_amount')
      ->comment('Gross discount granted at payment time (items total - amount paid).');
```
**لماذا:** عمود حقيقي = مصدر حقيقة تقرأه القوالب مباشرة، قابل للاستعلام والتدقيق. يحمل الخصم **الإجمالي** (شامل الضريبة) لأن كل الأسعار في النظام gross.

---

### 3.2 `app/Models/Invoice.php`

**(أ) إضافة الحقل للـ fillable/casts:**
```php
// fillable
'total_amount', 'discount_amount', 'status', ...
// casts
'discount_amount' => 'decimal:2',
```

**(ب) accessor جديد `items_total` (قلب اشتقاق "مجموع الأصناف"):**
```php
public function getItemsTotalAttribute(): float
{
    return (float) bcadd((string)($this->total_amount ?? 0), (string)($this->discount_amount ?? 0), 2);
}
```

**(ج) إعادة كتابة `calculateTotals()` — أخطر تعديل في الموديل:**

| قبل | بعد |
|-----|-----|
| تجمع `item->subtotal` (صافي) ثم **ضريبة أمامية** `(net*rate)/100` | تجمع `item->total_amount` (إجمالي)، تطرح الخصم، ثم **ضريبة عكسية** على الناتج |
| غير واعية للخصم → الـ observer يدهس الخصم | واعية للخصم: `total = itemsGross − discount` ثم `extractTax(total)` |

```php
public function calculateTotals(): void
{
    $itemsGross = '0';
    foreach ($this->items as $item) {
        $itemsGross = bcadd($itemsGross, (string) $item->total_amount, 2);
    }
    $total = bcsub($itemsGross, (string)($this->discount_amount ?? 0), 2);
    if (bccomp($total, '0', 2) < 0) { $total = '0.00'; }

    $tax = app(TaxCalculatorService::class)->extractTax($total, (string)($this->tax_rate ?? 0));
    $this->subtotal     = $tax['net'];
    $this->tax_amount   = $tax['tax'];
    $this->total_amount = $tax['gross']; // == total
    $this->save();
}
```
**كيف يخدم المهمة:** هذا يجعل **مراقب `InvoiceItem`** (الذي يستدعي `calculateTotals()` عند كل حفظ/حذف بند) غير قادر على إعادة الإجمالي إلى السعر الكامل — الخصم محفوظ دائمًا. وأيضًا يوحّد صيغة الضريبة مع بقية النظام (عكسية، مطابقة لـ `TaxCalculatorService`).

---

### 3.3 `app/Services/InvoiceService.php`

**(أ) دالة القلب الجديدة `applyFinalAmount()`** (المصدر الوحيد لتطبيق الخصم):
```php
public function applyFinalAmount(Invoice $invoice, ?float $finalGross = null): Invoice
{
    $taxRate    = (string) ($invoice->tax_rate ?: get_setting('tax_rate', 19));
    $itemsGross = (string) ($invoice->items()->sum('total_amount'));
    if (bccomp($itemsGross, '0', 2) <= 0) {
        $itemsGross = bcadd((string)$invoice->total_amount, (string)($invoice->discount_amount ?? 0), 2);
    }
    $final = $finalGross === null ? $itemsGross : number_format($finalGross, 2, '.', '');
    if (bccomp($final, '0', 2) < 0)         { $final = '0.00'; }
    if (bccomp($final, $itemsGross, 2) > 0) { $final = $itemsGross; } // لا خصم سالب
    $discount = bcsub($itemsGross, $final, 2);
    $tax = app(TaxCalculatorService::class)->extractTax($final, $taxRate);
    $invoice->update([
        'discount_amount' => $discount,
        'subtotal'        => $tax['net'],
        'tax_amount'      => $tax['tax'],
        'total_amount'    => $tax['gross'],
        'tax_rate'        => $taxRate,
    ]);
    return $invoice->refresh();
}
```

**(ب) تنظيف `createInvoice()` (private):** حُذفت كتلة الخصم القديمة التي كانت تكتب في `invoice_data` وتقارن ضد `appointment->total_amount` (الذي يدهسه المتصل أولًا → الخصم دائمًا 0 = خطأ كامن). الخصم الآن في العمود عبر `applyFinalAmount`.

**(ج) `createInvoiceFromAppointment()`:** بعد إنشاء البنود بالأسعار الكاملة:
```php
$this->createInvoiceItems($invoice, $appointment);
$invoice = $this->applyFinalAmount($invoice, $amountPaid); // ← يسجّل الخصم ويوفّق المجاميع
$this->updateAppointmentPaymentStatus($appointment, $paymentType);
```

---

### 3.4 `app/Livewire/StaffDashboard.php` (المسار 1)

**(أ) خاصية جديدة** `public float $paymentBaseline = 0;`

**(ب) `openPaymentModal()`** يحفظ المبلغ المقترح:
```php
$this->paymentAmount   = (float) $appointment->total_amount;
$this->paymentBaseline = (float) $appointment->total_amount; // مرجع لكشف الخصم الحقيقي
```

**(ج) `processPayment()` — قلب التعديل:** استُبدلت كتلة دهس `total_amount` بـ:
```php
$invoice = $invoiceService->rebuildAggregatedInvoice($invoiceOwner);

$staffChangedAmount = abs($this->paymentAmount - $this->paymentBaseline) >= 0.005;
$invoice = $invoiceService->applyFinalAmount(
    $invoice,
    $staffChangedAmount ? (float) $this->paymentAmount : null  // null = الكامل، لا خصم
);
// ...
$finalizedInvoice = $finalizationService->finalizeDraftInvoice(
    $invoice, $paymentTypeValue,
    (float) $invoice->total_amount,  // ← الإجمالي الموفَّق (بعد الخصم) وليس المُدخل الخام
    null, true
);
```
**فائدة `paymentBaseline`:** إن لم يغيّر الموظف المبلغ نمرّر `null` (= ادفع كامل مجموع الأصناف). هذا يمنع ظهور خصم وهمي على الفواتير المجمّعة (حيث المبلغ المقترح كان للأب فقط)، ويصلح أيضًا نقص تحصيل قديمًا.

---

### 3.5 `app/Filament/Resources/Appointments/Tables/AppointmentsTable.php` (المسار 2)

داخل ترانزاكشن الدفع، بعد تحويل DRAFT→PENDING:
```php
// Step 2b: نفس مصدر الحقيقة
$invoice = $invoiceService->applyFinalAmount($invoice, (float) $data['amount_paid']);
// Step 4: الدفع على الإجمالي الموفَّق (يتجنّب "exceeds remaining" عند القصّ)
$payment = $invoicePaymentService->createFromInvoice(
    invoice: $invoice, amount: (float) $invoice->total_amount, ...
);
// Step 4b: ضمان رقم فاتورة تسلسلي عند PAID (كان مفقودًا في هذا المسار)
$invoice->refresh();
if ($invoice->status === InvoiceStatus::PAID && empty($invoice->invoice_number)) {
    $invoice->update(['invoice_number' => Invoice::generateInvoiceNumber()]);
}
```
**ملاحظة:** `InvoicePaymentService` كان يعامل الدفع الأقل كـ **PARTIALLY_PAID** (وليس خصمًا) ولا يولّد رقم فاتورة. الآن بعد `applyFinalAmount` يصبح `amount == total` → **PAID كامل** مع خصم مسجّل ورقم تسلسلي.

> **المسار 3** (Providers RelationManager) موحَّد تلقائيًا لأنه يستدعي `createInvoiceFromAppointment()` المعدّلة.

---

### 3.6 `app/Services/InvoiceTemplate/DynamicFieldResolver.php` + `config/invoice-dynamic-fields.php`

حقلان ديناميكيان جديدان يرجعان **فارغًا عند عدم وجود خصم** (ليختفي السطر تلقائيًا):
```php
'discount'    => $this->resolveDiscountValue(),    // "-10.00" أو ""
'items_total' => $this->resolveItemsTotalValue(),  // "40.00" أو ""
```
```php
protected function resolveDiscountValue(): string {
    $d = (float)($this->invoice->discount_amount ?? 0);
    return $d > 0 ? '-' . number_format($d, 2) : '';
}
protected function resolveItemsTotalValue(): string {
    $d = (float)($this->invoice->discount_amount ?? 0);
    return $d <= 0 ? '' : number_format((float)($this->invoice->total_amount ?? 0) + $d, 2);
}
```
وأُضيف `invoice.items_total` و`invoice.discount` لقائمة `config/invoice-dynamic-fields.php` (تظهر في قائمة Filament).

---

### 3.7 `two_column` — خاصية `hide_when_empty`

- **`resources/views/invoices/line-types/two-column.blade.php`:** يُلفّ الصف بـ `@unless($hideWhenEmpty && trim((string)$value) === '') ... @endunless`.
- **`config/invoice-line-types.php`:** أُضيف `'hide_when_empty' => false` لخصائص `two_column` الافتراضية (متوافق رجعيًا).
- **`app/Filament/Resources/InvoiceTemplates/Pages/EditInvoiceTemplate.php`:** أُضيف `Toggle::make('properties.hide_when_empty')` لنوع `two_column` ليكون قابلًا للتحرير ويبقى محفوظًا بعد الحفظ.

---

### 3.8 `resources/views/invoices/line-types/totals-summary.blade.php`

لقوالب نوع `totals_summary` (القالب المضغوط/المستقبلي) أُضيف — مشروطًا بـ `discount_amount > 0` — صفّا «Items total» و«Discount» أعلى الصافي/الضريبة/الإجمالي.

---

### 3.9 صفوف القوالب في القاعدة + البذرة

- **Data migration:** `database/migrations/2026_06_08_000002_add_discount_lines_to_invoice_templates.php` (جديد) — **idempotent** و**حسب اللغة**: يدرج سطرَي `invoice.items_total` و`invoice.discount` (بـ `hide_when_empty=true`) قبل سطر `invoice.subtotal` في كل قالب موجود، مع إزاحة `order`. (القوالب تعيش في DB، فتعديل البذرة وحده لا يكفي للمزروع مسبقًا.)
- **`database/seeders/InvoiceTemplateSeeder.php`:** أُضيف السطران للقالبين الألماني والإنجليزي مع إعادة ترقيم الـ body للتركيبات الجديدة.

**حالة القاعدة بعد التطبيق (تم التحقق):**
```
order 2  two_column  "Artikel gesamt"/"Items total"  → invoice.items_total  (hide=true)
order 3  two_column  "Rabatt"/"Discount"             → invoice.discount      (hide=true)
order 4  two_column  Netto/Net                        → invoice.subtotal
order 5  two_column  + MwSt/VAT                        → invoice.tax_amount
order 7  two_column  Summe/Total                       → invoice.total
```

---

## 4. توحيد المسارات الثلاثة

| المسار | الواجهة | قبل | بعد |
|--------|---------|-----|-----|
| 1 | StaffDashboard | يدهس `total_amount` فقط (مكسور) | `applyFinalAmount()` |
| 2 | Filament AppointmentsTable | دفع أقل = PARTIALLY_PAID، بلا رقم فاتورة | `applyFinalAmount()` → PAID كامل + رقم |
| 3 | Providers RelationManager | خصم في JSON ودائمًا 0 (خطأ) | `createInvoiceFromAppointment()` → `applyFinalAmount()` |

الثلاثة الآن: عمود `discount_amount` واحد + ضريبة عكسية واحدة + ثابت `subtotal + tax == total`. والطباعة موحّدة لأن كل المسارات تطبع عبر نفس القالب/الحقول الديناميكية.

---

## 5. آلية سير العمل الحديثة للدفع (StaffDashboard)

```
1) الموظف يفتح موعدًا ويضغط Pay
2) openPaymentModal() → paymentAmount = paymentBaseline = total المقترح
3) (اختياري) الموظف يخفّض المبلغ (40 → 30) = خصم
4) processPayment():
     a. rebuildAggregatedInvoice()   → بنود مجمّعة، itemsGross = 40
     b. applyFinalAmount(invoice, 30 أو null إن لم يُغيَّر)
            → discount=10, subtotal=25.21, tax=4.79, total=30
     c. finalizeDraftInvoice(invoice, type, total=30)
            → رقم فاتورة + PAID + سجل Payment + تحديث كل الحجوزات المرتبطة
     d. dispatch('printInvoice') → نافذة الطباعة
5) الإيصال يطبع: Artikel 40 / Rabatt -10 / Netto 25.21 / MwSt 4.79 / Summe 30
```

---

## 6. الحالات الحديّة (مُغطّاة ومُختبَرة)

| الحالة | السلوك |
|--------|--------|
| لا خصم (دفع كامل) | `discount_amount=0` → سطرا الخصم يختفيان (الشكل القديم نظيف) |
| دفع زائد عن الإجمالي | يُقصّ إلى الإجمالي، `discount=0` (لا خصم سالب) |
| فاتورة مجمّعة + مبلغ غير مُغيَّر | `null` → تحصيل كامل المجموع المجمّع (يمنع خصمًا وهميًا) |
| إعادة طباعة (COPY) | الأرقام محفوظة بالعمود → تبقى صحيحة |
| تعديل بنود لاحقًا | `calculateTotals()` الواعي يحافظ على الخصم |

---

## 7. التحقق الفعلي

اختبار ضمن ترانزاكشن مُرجَعة (لم تتغير أي بيانات) على فاتورة بمجموع 46€ وخصم 10€:
```
DISCOUNTED  discount=10.00 subtotal=30.25 tax=5.75 total=36.00 items_total=46
  invariant(net+tax==total): OK
  fields: items_total=[46.00] discount=[-10.00] subtotal=[30.25] total=[36.00]
FULL        discount=0.00 total=46.00 fields: items_total=[] discount=[]   (يختفيان)
OVERPAY     discount=0.00 total=46.00 (مقصوص)
```
لينت PHP لكل الملفات: نظيف. الـ migrations: نُفّذت. صفوف القوالب: مؤكَّدة في القاعدة.

---

## 8. التطبيق والتراجع

```bash
php artisan migrate            # العمود + صفوف القوالب (نُفّذ)
php artisan config:clear       # لالتقاط حقول الديناميك الجديدة (نُفّذ)
php artisan view:clear         # لالتقاط تعديلات البليد (نُفّذ)
# للتركيبات الجديدة فقط:
php artisan db:seed --class=Database\\Seeders\\InvoiceTemplateSeeder
```
**تراجع:** `php artisan migrate:rollback` يحذف صفوف القوالب المُضافة (عبر الموديل، فيعيد ترتيب الباقي) ثم يحذف العمود.

---

## 9. ديون تقنية لوحظت (خارج النطاق، لم تُغيَّر)

- `InvoiceService::finalizeDraftInvoice()` (البسيطة) تبدو غير مستخدمة؛ المستخدم فعليًا هو `InvoiceFinalizationService::finalizeDraftInvoice()`.
- `applyTSESignature()` في `InvoiceFinalizationService` ما زال placeholder (تكامل Fiskaly غير منفّذ بعد).
- المسار 2/3 لا يستدعيان `rebuildAggregatedInvoice()`؛ مناسب للمواعيد المستقلة، وملاحظ للحجوزات المجمّعة في Filament.
