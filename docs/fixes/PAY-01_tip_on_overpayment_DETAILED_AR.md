# PAY-01 — الشرح التفصيلي: البقشيش عند زيادة مبلغ الدفع + ترجمة رسائل الدفع

> **التاريخ:** 25 سبتمبر 2026
> **الملخص المختصر:** [`PAY-01_tip_on_overpayment.md`](PAY-01_tip_on_overpayment.md)
> **الهدف من هذا الملف:** شرح كل تعديل: ما هو، والكود قبل وبعد، وشرح الكود، ولماذا عُدِّل، وكيف يخدم المهمة.

---

## الفهرس

1. [المهمة كما وصلت](#1-المهمة-كما-وصلت)
2. [كيف كان النظام يعمل قبل التعديل](#2-كيف-كان-النظام-يعمل-قبل-التعديل)
3. [القرارات المتفق عليها](#3-القرارات-المتفق-عليها)
4. [الفكرة المعمارية للحل](#4-الفكرة-المعمارية-للحل)
5. [التعديلات بالتفصيل](#5-التعديلات-بالتفصيل)
   - 5.1 [قاعدة البيانات: عمود `tip_amount`](#51-قاعدة-البيانات-عمود-tip_amount)
   - 5.2 [النماذج `Invoice` و `Payment`](#52-النماذج-invoice-و-payment)
   - 5.3 [قلب الإصلاح: `splitFinalAmount()`](#53-قلب-الإصلاح-splitfinalamount)
   - 5.4 [حفظ البقشيش في الفاتورة والدفعة](#54-حفظ-البقشيش-في-الفاتورة-والدفعة)
   - 5.5 [ترجمة رسائل الدفع](#55-ترجمة-رسائل-الدفع)
   - 5.6 [مودال الدفع في الداشبورد](#56-مودال-الدفع-في-الداشبورد)
   - 5.7 [الإيصال: حقول القالب الديناميكية](#57-الإيصال-حقول-القالب-الديناميكية)
   - 5.8 [سجل الحقول في محرر القوالب](#58-سجل-الحقول-في-محرر-القوالب)
   - 5.9 [Migration بيانات: تعديل القوالب الموجودة](#59-migration-بيانات-تعديل-القوالب-الموجودة)
   - 5.10 [الـ Seeder](#510-الـ-seeder)
   - 5.11 [نوع السطر `totals_summary`](#511-نوع-السطر-totals_summary)
   - 5.12 [تقرير Z: الخدمة `DailyReportService`](#512-تقرير-z-الخدمة-dailyreportservice)
   - 5.13 [تقرير Z: الواجهة والترجمات](#513-تقرير-z-الواجهة-والترجمات)
   - 5.14 [الاختبارات](#514-الاختبارات)
6. [مثال رقمي كامل من البداية للنهاية](#6-مثال-رقمي-كامل-من-البداية-للنهاية)
7. [ما لم يتغير وحدود معروفة](#7-ما-لم-يتغير-وحدود-معروفة)
8. [التحقق والنشر](#8-التحقق-والنشر)

---

## 1. المهمة كما وصلت

> عند تأكيد الدفع قمت بزيادة المبلغ الموجود على الفاتورة.
> بعد الضغط على زر التأكيد والطباعة ظهرت رسالة بعدم إمكانية زيادة السعر المذكور بالفاتورة.
> يجب الانتباه إلى أن عرض الرسائل باللغة المستخدمة.
> زيادة السعر يجب اعتبارها **بقشيش**، وإنقاص السعر يجب اعتباره **حسم**.

في المودال «Zahlung verarbeiten» (الواجهة بالألمانية) رفع الكاشير المبلغ من 20 إلى رقم أكبر، فظهر هذا التنبيه **بالعربي**:

```
مبلغ الدفع لا يمكن أن يتجاوز مجموع خدمات الفاتورة.
```

المهمة إذن تتضمن **ثلاثة مطالب**:

| # | المطلب | الوضع قبل التعديل |
|---|---|---|
| 1 | إنقاص المبلغ = حسم | ✅ كان يعمل |
| 2 | زيادة المبلغ = بقشيش | ❌ ممنوع، والنظام لا يعرف مفهوم البقشيش أصلاً |
| 3 | الرسائل بلغة المستخدم | ❌ الرسائل مكتوبة بالعربي داخل الكود |

---

## 2. كيف كان النظام يعمل قبل التعديل

### 2.1 مسار الدفع

```text
[زر «تأكيد وطباعة» في المودال]
        │  wire:click="processPayment"
        ▼
StaffDashboard::processPayment()                     ← adapter رفيع
        │  يقارن paymentAmount مع paymentBaseline
        │  إن اختلفا يمرر المبلغ الجديد، وإلا null (= السعر الكامل)
        ▼
InvoiceFinalizationService::finalizeAppointmentPayment()   ← المالك الوحيد للدفع
        │  (داخل DB::transaction مع أقفال)
        ├── rebuildAggregatedInvoice()     يعيد بناء بنود الفاتورة من كل مواعيد المجموعة
        ├── assertValidFinalAmount()       ◄── هنا كان الرفض
        ├── applyFinalAmount()             يحسب الحسم ويعيد استخراج الضريبة
        └── finalizeLockedInvoice()        رقم الفاتورة + Payment + إكمال المواعيد
```

نفس الخدمة تستعملها أيضاً شاشتا الدفع في Filament:
- `app/Filament/Resources/Appointments/Tables/AppointmentsTable.php`
- `app/Filament/Resources/Providers/RelationManagers/AppointmentsRelationManager.php`

إذن أي تعديل في الخدمة يسري على **كل** نقاط الدفع تلقائياً، وهذا مقصود من إصلاح MON-05 السابق.

### 2.2 أين كان المنع بالضبط

المنع كان في **مكانين**:

**الأول:** يرفض ويرمي استثناء، وهو سبب الرسالة التي ظهرت:

```php
// app/Services/InvoiceFinalizationService.php  (قبل)
if (bccomp($requested, $itemsGross, 2) === 1) {
    throw new InvalidArgumentException('مبلغ الدفع لا يمكن أن يتجاوز مجموع خدمات الفاتورة.');
}
```

**الثاني:** شبكة أمان تقصّ أي مبلغ زائد وترجعه إلى مجموع البنود:

```php
// app/Services/InvoiceService.php :: applyFinalAmount()
if (bccomp($final, $itemsGross, 2) > 0) {
    $final = $itemsGross;   // overpayment is not a discount
}
```

### 2.3 لماذا ظهرت الرسالة بالعربي على شاشة ألمانية

كل رسائل `InvoiceFinalizationService` (8 رسائل) كانت نصوصاً عربية ثابتة، و`processPayment()` يعرض نص الاستثناء كما هو:

```php
// app/Livewire/StaffDashboard.php
} catch (\Throwable $e) {
    $this->dispatch('notify', type: 'error', message: $e->getMessage());
}
```

لا يوجد أي `__()`، فاللغة لا تتغير أبداً.

### 2.4 كيف كان الحسم يعمل (ويجب أن يبقى كما هو)

`applyFinalAmount()` هو المصدر الوحيد للحسم:

```text
itemsGross      = Σ invoice_items.total_amount        (السعر الكامل شامل الضريبة)
discount_amount = itemsGross − final
subtotal, tax   = استخراج عكسي للضريبة من final         (subtotal + tax == total)
total_amount    = final
```

الإيصال يعرض الحسم عبر سطرين `hide_when_empty`، وتقرير Z يوزعه على الموظفين بالتناسب. **لم نلمس أي شيء من هذا.**

---

## 3. القرارات المتفق عليها

قبل التنفيذ سألنا أربعة أسئلة. هذه الأجوبة وأثر كل منها:

| السؤال | القرار | أثره على التصميم |
|---|---|---|
| لمن يعود البقشيش؟ | **للحلاق الذي خدم** | البقشيش الذي يدفعه الزبون طوعاً للموظف ليس إيراداً للصالون ولا ضريبة عليه، فيُحفظ **خارج** `total_amount` و`tax_amount` |
| كيف يظهر على الإيصال؟ | **سطر منفصل بعد المجموع** | سطر «Trinkgeld» يظهر فقط عند وجود بقشيش، و«Gegeben» = المجموع + البقشيش |
| فاتورة فيها عدة حلاقين؟ | **بالتناسب مع سعر خدمة كل حلاق** | نفس خوارزمية توزيع الحسم في تقرير Z |
| الحماية من خطأ الكتابة؟ | **سطر توضيحي + تأكيد للمبالغ الكبيرة** | سطر حيّ «حسم/بقشيش» تحت الحقل، وإذا تجاوز البقشيش قيمة الفاتورة نفسها يُطلب تأكيد قبل الدفع |

---

## 4. الفكرة المعمارية للحل

**المبدأ:** المبلغ الذي يكتبه الكاشير هو «ما دفعه الزبون فعلاً». نقسمه إلى جزأين:

```text
                ما دفعه الزبون (paid)
                        │
     ┌──────────────────┴──────────────────┐
     ▼                                     ▼
 المبلغ المحصَّل (charged)              البقشيش (tip)
 = min(paid, itemsGross)               = max(0, paid − itemsGross)
     │                                     │
     ▼                                     ▼
 applyFinalAmount()                    invoices.tip_amount
 (حسم + ضريبة كالسابق)                  payments.tip_amount
     │                                 (لا ضريبة، ليس إيراداً)
     ▼
 total / subtotal / tax_amount
```

**لماذا لا نضيف البقشيش إلى `total_amount`؟**

1. عندها تُحسب عليه ضريبة 19%، وهذا خطأ لأنه ليس مبيعاً.
2. سيظهر في تقرير Z كإيراد للصالون وفي عمولة الحلاق كمبيعات.
3. ستنكسر المعادلات التي يعتمد عليها النظام كله:
   `subtotal + tax_amount = total_amount` و `payment.amount = invoice.total_amount`.

لذلك البقشيش **عمود مستقل بجانب الأرقام الخاضعة للضريبة، لا بداخلها.** وبهذا تبقى كل الحسابات القديمة صحيحة كما هي، ونضيف فوقها طبقة جديدة.

---

## 5. التعديلات بالتفصيل

### 5.1 قاعدة البيانات: عمود `tip_amount`

**الملف (جديد):** `database/migrations/2026_09_25_000001_add_tip_amount_to_invoices_and_payments.php`

**ما هو:** عمود `tip_amount` من نوع `decimal(8,2)` بقيمة افتراضية `0` في جدولي `invoices` و`payments`.

**قبل:** لا يوجد مكان لتخزين البقشيش.

```text
invoices: subtotal | tax_amount | tax_rate | total_amount | discount_amount | status ...
payments: amount   | subtotal   | tax_amount | status ...
```

**بعد:**

```php
Schema::table('invoices', function (Blueprint $table) {
    $table->decimal('tip_amount', 8, 2)
        ->default(0)
        ->after('discount_amount')
        ->comment('Tip paid on top of total_amount; not revenue, no VAT');
});

Schema::table('payments', function (Blueprint $table) {
    $table->decimal('tip_amount', 8, 2)
        ->default(0)
        ->after('amount')
        ->comment('Tip collected with this payment; amount stays the invoice total');
});
```

**شرح الكود:**
- `decimal(8,2)`: نفس نوع باقي أعمدة المال (`total_amount`, `discount_amount`) حتى لا تختلف الدقة.
- `default(0)`: كل فاتورة أو دفعة قديمة تُقرأ تلقائياً «بدون بقشيش»، فلا حاجة لتعديل بيانات قديمة ولا قيم `NULL` تحتاج معالجة.
- `after('discount_amount')`: يضع العمود بجانب شقيقه الحسم، للقراءة فقط.
- الـ `down()` يحذف العمودين بالترتيب العكسي.

**لماذا في الجدولين؟**
- `invoices.tip_amount`: الإيصال والتقرير يقرآن من الفاتورة.
- `payments.tip_amount`: الدفعة هي «إثبات استلام المال»، والمال المستلم فعلاً = `amount + tip_amount`. لو حفظناه في الفاتورة فقط لكانت الدفعة ناقصة كسجل محاسبي.

**كيف يخدم المهمة:** بدونه لا مكان للبقشيش إلا داخل `total_amount`، وهذا خطأ ضريبي كما شرحنا في القسم 4.

> **ملاحظة تقنية:** لم نضع DDL داخل `DB::transaction`، لأن MySQL يعمل commit ضمنياً عند `ALTER`.

---

### 5.2 النماذج `Invoice` و `Payment`

**الملفان:** `app/Models/Invoice.php`، `app/Models/Payment.php`

**قبل (`Invoice`):**

```php
protected $fillable = [
    ...
    'total_amount',
    'discount_amount',
    'status',
    ...
];

protected $casts = [
    ...
    'discount_amount' => 'decimal:2',
    'invoice_data' => 'array',
];
```

**بعد:**

```php
protected $fillable = [
    ...
    'total_amount',
    'discount_amount',
    'tip_amount',
    'status',
    ...
];

protected $casts = [
    ...
    'discount_amount' => 'decimal:2',
    'tip_amount' => 'decimal:2',
    'invoice_data' => 'array',
];
```

ونفس الشيء في `Payment`: أضيف `'tip_amount'` بعد `'amount'` في `$fillable`، و`'tip_amount' => 'decimal:2'` في `$casts`.

**شرح الكود ولماذا:**
- `$fillable`: بدونها يتجاهل Eloquent الحقل بصمت عند `update([...])` و`create([...])`. لن يظهر أي خطأ، وسيُحفظ صفر دائماً، وهذا أخطر من خطأ ظاهر.
- `decimal:2`: يرجع القيمة كنص بخانتين (`"5.00"`) مثل باقي حقول المال، فتبقى المقارنات في الاختبارات والعرض متسقة.

---

### 5.3 قلب الإصلاح: `splitFinalAmount()`

**الملف:** `app/Services/InvoiceFinalizationService.php`

**ما هو:** استبدال دالة الرفض `assertValidFinalAmount()` بدالة تقسيم `splitFinalAmount()` ترجع `[المبلغ المحصَّل، البقشيش]`.

**قبل:**

```php
$this->assertValidFinalAmount($invoice, $finalAmount);
$invoice = $invoiceService->applyFinalAmount($invoice, $finalAmount);

// ...

private function assertValidFinalAmount(Invoice $invoice, ?float $finalAmount): void
{
    $itemsGross = (string) $invoice->items()->sum('total_amount');
    $requested = $finalAmount === null
        ? $itemsGross
        : number_format($finalAmount, 2, '.', '');

    if (bccomp($itemsGross, '0.00', 2) <= 0) {
        throw new InvalidArgumentException('لا يمكن تحصيل فاتورة بلا مبلغ موجب.');
    }

    if (bccomp($requested, '0.00', 2) <= 0) {
        throw new InvalidArgumentException('يجب أن يكون مبلغ الدفع أكبر من صفر.');
    }

    if (bccomp($requested, $itemsGross, 2) === 1) {
        throw new InvalidArgumentException('مبلغ الدفع لا يمكن أن يتجاوز مجموع خدمات الفاتورة.');
    }
}
```

**بعد:**

```php
// What the customer handed over splits into the invoice price
// (full or discounted) and, when it exceeds the items total, a tip.
[$chargedAmount, $tipAmount] = $this->splitFinalAmount($invoice, $finalAmount);
$invoice = $invoiceService->applyFinalAmount($invoice, $chargedAmount);

return $this->finalizeLockedInvoice(
    invoice: $invoice,
    tipAmount: $tipAmount,
    // ...
);

// ...

/**
 * Split the amount the customer paid into [invoice price, tip].
 *
 *   paid <  itemsGross  => discount: charge `paid`, no tip
 *   paid == itemsGross  => full price, no tip
 *   paid >  itemsGross  => tip: charge the full items total, tip = the excess
 */
private function splitFinalAmount(Invoice $invoice, ?float $finalAmount): array
{
    $itemsGross = (string) $invoice->items()->sum('total_amount');
    $paid = $finalAmount === null
        ? $itemsGross
        : number_format($finalAmount, 2, '.', '');

    if (bccomp($itemsGross, '0.00', 2) <= 0) {
        throw new InvalidArgumentException(__('payment.errors.invoice_not_positive'));
    }

    if (bccomp($paid, '0.00', 2) <= 0) {
        throw new InvalidArgumentException(__('payment.errors.amount_not_positive'));
    }

    if (bccomp($paid, $itemsGross, 2) === 1) {
        return [null, bcsub($paid, $itemsGross, 2)];
    }

    return [$finalAmount, '0.00'];
}
```

**شرح الكود سطراً سطراً:**

| السطر | ماذا يفعل |
|---|---|
| `$itemsGross = ...sum('total_amount')` | مجموع بنود الفاتورة شامل الضريبة، وهو «السعر الكامل». يُقرأ **بعد** `rebuildAggregatedInvoice()` فهو دائماً محدَّث |
| `$paid = $finalAmount === null ? ...` | `null` تعني «الكاشير لم يغيّر المبلغ»، فنعتبره دفع السعر الكامل |
| `number_format($finalAmount, 2, '.', '')` | يحوّل `float` إلى نص عشري دقيق بخانتين. هذا شرط لـ bcmath، لأن `float` لا يُمثَّل بدقة |
| الشرطان الأولان | نفس فحوص الأمان السابقة: فاتورة بلا قيمة، أو مبلغ صفر أو سالب. بقيا كما هما، لكن برسائل مترجمة |
| `bccomp($paid, $itemsGross, 2) === 1` | «هل المدفوع أكبر من السعر الكامل؟». كان هذا الشرط **يرمي خطأ**، والآن **يرجع بقشيش** |
| `return [null, bcsub($paid, $itemsGross, 2)]` | المحصَّل = `null` أي السعر الكامل بلا حسم، والبقشيش = الفرق بدقة bcmath |
| `return [$finalAmount, '0.00']` | حالة المساواة أو الحسم: نمرر المبلغ كما كان يُمرَّر سابقاً تماماً، والبقشيش صفر |

**لماذا صُمِّم هكذا:**

1. **سلوك الحسم لم يتغير.** في حالة `paid ≤ itemsGross` تمرر الدالة **نفس القيمة** التي كانت تُمرَّر إلى `applyFinalAmount()`، حرفياً. لم نعدّل `applyFinalAmount()` نفسها، وبقي قصّها للمبالغ الزائدة كشبكة أمان.
2. **البقشيش لا يمر أبداً بـ `applyFinalAmount()`.** تلك الدالة تحسب الضريبة، والبقشيش لا ضريبة عليه، فالفصل يحدث **قبلها**.
3. **نقطة قرار واحدة.** لو قرر المحاسب لاحقاً أن البقشيش إيراد للصالون، فالتعديل في هذه الدالة فقط.
4. **bcmath لا float.** لأن `0.1 + 0.2 ≠ 0.3` في `float`، وأي فرق سنت في المال خطأ محاسبي. هذا يتبع قاعدة المشروع في `tax-calculator.md`.

**كيف يخدم المهمة:** هذا هو الإصلاح المباشر للرسالة التي ظهرت. الشرط الذي كان يرفض الزيادة صار يحوّلها إلى بقشيش.

---

### 5.4 حفظ البقشيش في الفاتورة والدفعة

**الملف:** `app/Services/InvoiceFinalizationService.php` :: `finalizeLockedInvoice()`

**ما هو:** الدالة التي تُصدر الفاتورة صارت تستقبل `string $tipAmount` وتكتبه في 4 أماكن.

**قبل:**

```php
private function finalizeLockedInvoice(
    Invoice $invoice,
    $coveredAppointments,
    // ...
): Invoice {
    $invoice->update([
        'invoice_number' => $invoiceNumber,
        'status' => InvoiceStatus::PAID,
        'notes' => $notes,
        'invoice_data' => array_merge($invoice->invoice_data ?? [], [
            // ...
            'amount_paid' => $amountPaid,
            'finalization_method' => $source,
        ]),
    ]);

    $payment = Payment::create([
        'payment_method_id' => $paymentMethod->id,
        'payment_number' => Payment::generatePaymentNumber(),
        'amount' => $amountPaid,
        'subtotal' => $invoice->subtotal,
        // ...
    ]);
```

**بعد:**

```php
private function finalizeLockedInvoice(
    Invoice $invoice,
    string $tipAmount,
    $coveredAppointments,
    // ...
): Invoice {
    $invoice->update([
        'invoice_number' => $invoiceNumber,
        'status' => InvoiceStatus::PAID,
        // Beside total_amount, never inside it: a tip is not revenue and
        // carries no VAT, so subtotal + tax_amount == total_amount still holds.
        'tip_amount' => $tipAmount,                  // ① عمود الفاتورة
        'notes' => $notes,
        'invoice_data' => array_merge($invoice->invoice_data ?? [], [
            // ...
            'amount_paid' => $amountPaid,
            'tip_amount' => $tipAmount,              // ② سجل التدقيق في JSON
            'finalization_method' => $source,
        ]),
    ]);

    $payment = Payment::create([
        'payment_method_id' => $paymentMethod->id,
        'payment_number' => Payment::generatePaymentNumber(),
        'amount' => $amountPaid,
        'tip_amount' => $tipAmount,                  // ③ عمود الدفعة
        'subtotal' => $invoice->subtotal,
        // ...
    ]);

    Log::info('Unified on-site payment finalized', [
        // ...
        'amount_paid' => $amountPaid,
        'tip_amount' => $tipAmount,                  // ④ سجل اللوج
        // ...
    ]);
```

**شرح ولماذا:**
- **`$amountPaid` لم يتغير.** ما زال `= invoice.total_amount`، أي المبيع الخاضع للضريبة فقط، وبقي `payment.amount = invoice.total_amount` كما يتوقع باقي النظام (تقرير Z، الاختبارات القديمة).
- **① و③:** الأعمدة الحقيقية التي يُبنى عليها العرض والتقارير.
- **②:** `invoice_data` هو صندوق التدقيق الذي يسجل «كيف أُنهيت هذه الفاتورة». وضع البقشيش فيه يجعل قراءة الفاتورة كاملة من مكان واحد.
- **④:** لو اشتكى أحد «أين ذهب البقشيش؟»، يظهر الرقم في اللوج بجانب رقم الفاتورة.
- **كل هذا داخل نفس الـ `DB::transaction`** مع الأقفال الموجودة، فإن فشل أي شيء لا يُحفظ بقشيش بلا فاتورة ولا العكس.

---

### 5.5 ترجمة رسائل الدفع

**الملفات:** `lang/en/payment.php`، `lang/de/payment.php`، `lang/ar/payment.php` (جديدة)، و`InvoiceFinalizationService.php`

**ما هو:** نقل الرسائل الثماني من نصوص عربية ثابتة إلى ملفات ترجمة.

**قبل (أمثلة):**

```php
throw new InvalidArgumentException('طريقة الدفع غير موجودة أو غير مفعلة.');
throw new InvalidArgumentException('لا توجد مواعيد مرتبطة بهذه الفاتورة.');
throw new InvalidArgumentException('يجب أن يكون مبلغ الدفع أكبر من صفر.');
```

**بعد:**

```php
throw new InvalidArgumentException(__('payment.errors.method_not_found'));
throw new InvalidArgumentException(__('payment.errors.no_appointments'));
throw new InvalidArgumentException(__('payment.errors.amount_not_positive'));
```

```php
// lang/de/payment.php
return [
    'errors' => [
        'method_not_found' => 'Die Zahlungsart existiert nicht oder ist nicht aktiv.',
        'method_not_on_site' => 'Die Zahlungsart muss Bar oder Karte im Salon sein.',
        'no_appointments' => 'Mit dieser Rechnung sind keine Termine verknüpft.',
        'cancelled_or_no_show' => 'Eine Rechnung mit einem stornierten oder nicht wahrgenommenen Termin kann nicht kassiert werden.',
        'invoice_not_positive' => 'Eine Rechnung ohne positiven Betrag kann nicht kassiert werden.',
        'amount_not_positive' => 'Der Zahlungsbetrag muss größer als null sein.',
        'duration_not_positive' => 'Die angepasste Dauer muss größer als null sein.',
        'appointment_not_in_invoice' => 'Der ausgewählte Termin gehört nicht zu dieser Rechnung.',
    ],
];
```

**جدول المفاتيح الثمانية:**

| المفتاح | متى تظهر |
|---|---|
| `method_not_found` | طريقة الدفع غير موجودة أو معطَّلة |
| `method_not_on_site` | طريقة دفع ليست نقداً أو بطاقة (مثل Stripe) |
| `no_appointments` | فاتورة بلا مواعيد |
| `cancelled_or_no_show` | المجموعة فيها موعد ملغى أو لم يحضر صاحبه |
| `invoice_not_positive` | مجموع البنود صفر |
| `amount_not_positive` | المبلغ المكتوب صفر أو سالب |
| `duration_not_positive` | تعديل مدة غير صالح (شاشة Filament) |
| `appointment_not_in_invoice` | الموعد ليس ضمن الفاتورة الموحدة |

**لماذا ملف جديد `payment.php` وليس `dashboard.php`؟**
الخدمة تُستدعى من الداشبورد **ومن Filament**. لو وضعنا الرسائل في `dashboard.php` لكانت خدمة مشتركة تعتمد على ملف ترجمة شاشة واحدة. `payment.php` يعبّر عن المجال (الدفع)، لا عن الشاشة.

**لماذا تعمل الترجمة؟** `__()` يقرأ `app()->getLocale()` لحظة رمي الاستثناء، والداشبورد يضبط اللغة حسب اختيار المستخدم، فتصل الرسالة إلى `notify` مترجمة دون أي تعديل في `processPayment()`.

**كيف يخدم المهمة:** هذا يحقق المطلب الثالث مباشرة: «عرض الرسائل باللغة المستخدمة». ولم نترجم الرسالة التي ظهرت فقط، بل **كل** رسائل مسار الدفع، حتى لا يظهر نفس الخلل بعد أسبوع برسالة أخرى.

---

### 5.6 مودال الدفع في الداشبورد

**الملفات:** `resources/views/livewire/staff-dashboard.blade.php`، و`lang/{ar,de,en}/dashboard.php`

**ما هو:** ثلاثة تحسينات في المودال:
1. سطر حيّ تحت الحقل يوضح نوع الفرق وقيمته.
2. مربع تأكيد إجباري عند البقشيش الكبير.
3. زر الدفع لا يعمل حتى يُؤكَّد البقشيش الكبير.

**قبل:**

```blade
<div class="bg-white rounded-xl shadow-2xl w-full max-w-sm" @click.stop>
    ...
    <input type="number" wire:model="paymentAmount" step="0.01" min="0" class="...">
    <p class="text-[10px] text-gray-400 mt-1">{{ __('dashboard.payment_modal.discount_note') }}</p>
    ...
    <button wire:click="processPayment"
        class="px-5 py-2 bg-green-500 hover:bg-green-600 text-white text-sm font-medium rounded-lg"
        wire:loading.attr="disabled">
```

**بعد:**

```blade
{{-- Paid < baseline = discount, paid > baseline = tip for the provider.
     A tip larger than the bill itself is almost always a typo (200 for 20),
     so it must be confirmed before the payment can be sent. --}}
<div class="bg-white rounded-xl shadow-2xl w-full max-w-sm" @click.stop
    x-data="{
        baseline: {{ number_format((float) $paymentBaseline, 2, '.', '') }},
        bigTipConfirmed: false,
        diff() {
            const paid = parseFloat($wire.paymentAmount);
            return isNaN(paid) ? 0 : Math.round((paid - this.baseline) * 100) / 100;
        },
        isBigTip() { return this.diff() > this.baseline; },
        canPay() { return !this.isBigTip() || this.bigTipConfirmed; },
    }"
    x-effect="if (!isBigTip()) bigTipConfirmed = false">
    ...
    <input type="number" wire:model="paymentAmount" step="0.01" min="0" class="...">
    <p class="text-[10px] text-gray-400 mt-1">{{ __('dashboard.payment_modal.discount_note') }}</p>

    <div class="mt-2 text-sm font-medium text-center" x-show="diff() !== 0" x-cloak>
        <span x-show="diff() < 0" class="text-red-600">
            {{ __('dashboard.payment_modal.discount_label') }}:
            <span dir="ltr" x-text="'-' + Math.abs(diff()).toFixed(2)"></span>
        </span>
        <span x-show="diff() > 0" class="text-green-600">
            {{ __('dashboard.payment_modal.tip_label') }}:
            <span dir="ltr" x-text="'+' + diff().toFixed(2)"></span>
        </span>
    </div>

    <label x-show="isBigTip()" x-cloak class="... border-amber-300 bg-amber-50 ...">
        <input type="checkbox" x-model="bigTipConfirmed">
        <span>
            {{ __('dashboard.payment_modal.big_tip_confirm') }}
            (<span dir="ltr" x-text="'+' + diff().toFixed(2)"></span>)
        </span>
    </label>
    ...
    <button @click="if (canPay()) $wire.processPayment()"
        :disabled="!canPay()"
        class="... disabled:opacity-50 disabled:cursor-not-allowed"
        wire:loading.attr="disabled" wire:target="processPayment">
```

**شرح الكود:**

| الجزء | الشرح |
|---|---|
| `baseline: {{ ... $paymentBaseline }}` | السعر الكامل الذي حسبه الخادم عند فتح المودال (`openPaymentModal()`)، يُطبع كرقم JS ثابت |
| `$wire.paymentAmount` | في Livewire، `wire:model` يحدّث قيمة `$wire` في المتصفح فوراً مع كل كتابة **دون** طلب للخادم، وAlpine يعيد الحساب تلقائياً |
| `diff()` | الفرق = المكتوب − الكامل، مقرَّب لسنتين بـ `Math.round(x*100)/100` حتى لا يظهر `4.999999` |
| `isBigTip()` | «البقشيش أكبر من قيمة الفاتورة نفسها»، أي المدفوع أكثر من ضعف الفاتورة: 45 على فاتورة 20 تحتاج تأكيداً، و35 لا تحتاج |
| `canPay()` | مسموح إذا لم يكن بقشيشاً كبيراً، أو إذا كان كبيراً وتم تأكيده |
| `x-effect="if (!isBigTip()) bigTipConfirmed = false"` | إن صحّح الكاشير الرقم ثم عاد وأخطأ مرة أخرى، **يُلغى التأكيد السابق**، فلا يبقى تأكيد قديم يمرر رقماً جديداً خاطئاً |
| `dir="ltr"` | بدونه يعرض المتصفح في الواجهة العربية «5.00+» بدل «+5.00» (خوارزمية bidi) |
| `@click="if (canPay()) $wire.processPayment()"` | بدل `wire:click` المباشر، الاستدعاء صار مشروطاً |
| `:disabled="!canPay()"` | الزر يبدو معطَّلاً بصرياً أيضاً، فيفهم الكاشير أن هناك شيئاً ينتظر |

**لماذا مربع تأكيد وليس `confirm()`؟**
نافذة `confirm()` في المتصفح تُحجب في بعض البيئات المضمَّنة، وتبدو «خارج التطبيق». المربع داخل المودال مترجم ومتسق مع التصميم.

**لماذا العتبة «أكبر من الفاتورة نفسها»؟**
هذا ما اتُّفق عليه. أخطاء الكتابة الشائعة (صفر زائد: 200 بدل 20، أو 150 بدل 15) تنتج دائماً مبلغاً يتجاوز ضعف الفاتورة، بينما البقشيش الطبيعي (10–30%) لا يقترب من العتبة، فلا يزعج الكاشير في الحالات العادية.

**الترجمات الجديدة في `dashboard.php`:**

| المفتاح | ar | de | en |
|---|---|---|---|
| `discount_note` (مُعدَّل) | خفّض المبلغ للحسم، أو ارفعه للبقشيش | Betrag senken für Rabatt, erhöhen für Trinkgeld | Lower the amount for a discount, raise it for a tip |
| `discount_label` | حسم | Rabatt | Discount |
| `tip_label` | بقشيش | Trinkgeld | Tip |
| `big_tip_confirm` | البقشيش أكبر من قيمة الفاتورة نفسها. أؤكد أن المبلغ صحيح | Das Trinkgeld ist höher als die Rechnung selbst. Ich bestätige, dass der Betrag stimmt | The tip is larger than the bill itself. I confirm this amount is correct |

**قبل:** كانت الملاحظة تقول «يمكنك تعديل المبلغ للخصومات» فقط، فلا شيء يخبر الكاشير أن الزيادة مسموحة أو ماذا ستعني.

**كيف يخدم المهمة:** الكاشير يرى قبل الضغط ما سيُسجَّل بالضبط (حسم أم بقشيش، وكم)، والخطأ الكارثي (بقشيش 180€ بدل 0) لا يمر بلا انتباه.

> **ملاحظة:** الحماية هنا في الواجهة. الخادم يقبل أي بقشيش موجب، لأن البقشيش الكبير قد يكون حقيقياً فعلاً، والقرار للكاشير بعد التأكيد.

---

### 5.7 الإيصال: حقول القالب الديناميكية

**الملف:** `app/Services/InvoiceTemplate/DynamicFieldResolver.php`

**خلفية:** الإيصال لا يُكتب في Blade ثابت، بل يُبنى من صفوف `template_lines` في قاعدة البيانات. كل سطر `two_column` يحمل `dynamic_field` مثل `invoice.total`، وهذا الـ Resolver يحوّل اسم الحقل إلى قيمة.

**قبل:**

```php
'total' => number_format($this->invoice->total_amount ?? 0, 2),
'paid_amount' => number_format($this->invoice->total_amount ?? 0, 2),
'remaining' => '0.00',
'discount' => $this->resolveDiscountValue(),
'items_total' => $this->resolveItemsTotalValue(),
default => '',
```

**بعد:**

```php
'total' => number_format($this->invoice->total_amount ?? 0, 2),
// What the customer actually handed over: the invoice total plus any
// tip. Equals 'total' whenever there is no tip.
'paid_amount' => number_format((float) ($this->invoice->total_amount ?? 0) + (float) ($this->invoice->tip_amount ?? 0), 2),
'remaining' => '0.00',
'discount' => $this->resolveDiscountValue(),
'items_total' => $this->resolveItemsTotalValue(),
// Tip paid on top of the total (not revenue, no VAT); '' when none.
'tip' => $this->resolveTipValue(),
default => '',

// ...

/**
 * Tip shown with a leading plus (e.g. "+5.00"); '' when no tip.
 */
protected function resolveTipValue(): string
{
    $tip = (float) ($this->invoice->tip_amount ?? 0);

    return $tip > 0 ? '+' . number_format($tip, 2) : '';
}
```

**شرح الكود ولماذا:**
- **`invoice.tip` (جديد):** يرجع `"+5.00"` أو **نصاً فارغاً** عند عدم وجود بقشيش. الفراغ مهم: السطر في القالب عليه `hide_when_empty: true`، فيختفي كاملاً من الإيصال العادي. هذا **نفس نمط** `invoice.discount` الموجود مسبقاً، وعلامة `+` تقابل `-` في الحسم.
- **`invoice.paid_amount` (مُعدَّل):** كان يساوي `total_amount` حرفياً، والآن = المجموع + البقشيش، أي «ما سلّمه الزبون فعلاً». عند عدم وجود بقشيش **يبقى مطابقاً للسابق تماماً** (+0)، فلا يتغير أي إيصال قديم.

---

### 5.8 سجل الحقول في محرر القوالب

**الملف:** `config/invoice-dynamic-fields.php`

**قبل:**

```php
'invoice.discount' => [
    'label' => 'Discount (− amount, blank if none)',
    ...
],

'invoice.paid_amount' => [
    'label' => 'Paid Amount',
    ...
],
```

**بعد:**

```php
'invoice.discount' => [
    'label' => 'Discount (− amount, blank if none)',
    ...
],

'invoice.tip' => [
    'label' => 'Tip (+ amount, blank if none)',
    'category' => 'Totals',
    'example' => '+5.00',
],

'invoice.paid_amount' => [
    'label' => 'Paid Amount (total + tip)',
    ...
],
```

**لماذا:** هذا الملف هو قائمة الحقول التي تظهر للأدمن في محرر القوالب في Filament. بدون التسجيل يعمل الحقل في الطباعة، لكن لا يستطيع الأدمن اختياره لو أراد نقل السطر أو بناء قالب جديد. وتعديل وصف `paid_amount` يوضح معناه الجديد لمن يصمم قالباً.

---

### 5.9 Migration بيانات: تعديل القوالب الموجودة

**الملف (جديد):** `database/migrations/2026_09_25_000002_add_tip_line_to_invoice_templates.php`

**لماذا migration وليس seeder فقط؟**
هذا فخ معروف في المشروع: **القوالب تعيش في قاعدة البيانات.** تعديل `InvoiceTemplateSeeder` لا يغيّر شيئاً في صالون مثبَّت أصلاً، لأن الـ seeder لا يُعاد تشغيله. الطريقة الوحيدة لوصول التعديل إلى كل بيئة (المحلي، السيرفر، أي تثبيت آخر) هي migration تعدّل الصفوف الموجودة.

**قبل (صفوف قاعدة البيانات للقالب الألماني، بعد سطر المجموع):**

| order | label | dynamic_field |
|---|---|---|
| 7 | Summe Eur | `invoice.total` |
| 8 | (خط فاصل) | — |
| 9 | Gegeben Eur | `payment.amount` |
| 10 | Bezahlt per Girocard | (نص ثابت) |

**بعد:**

| order | label | dynamic_field | ملاحظة |
|---|---|---|---|
| 7 | Summe Eur | `invoice.total` | بلا تغيير |
| 8 | (خط فاصل) | — | بلا تغيير |
| **9** | **Trinkgeld** | **`invoice.tip`** | **جديد**، `hide_when_empty` |
| 10 | Gegeben Eur | **`invoice.paid_amount`** | كان `payment.amount` |
| 11 | Bezahlt per Girocard | (نص ثابت) | أُزيح من 10 |

والقالب الإنجليزي بنفس الشكل: «Tip» و«Paid Eur».

**الكود الأساسي:**

```php
foreach ($templates as $template) {
    // 1) idempotent: skip templates that already carry a tip line
    if ($alreadyHasTip) continue;

    // 2) find the "Paid" line by its binding, not by its label or id
    $paidLine = $lines->first(fn ($line) => $line->type === 'two_column'
        && (json_decode($line->properties, true)['dynamic_field'] ?? null) === 'payment.amount');
    if (! $paidLine) continue;

    DB::transaction(function () use ($template, $paidLine) {
        // 3) make room: shift that line and everything below it by one
        DB::table('template_lines')
            ->where('template_id', $template->id)
            ->where('section', $paidLine->section)
            ->where('order', '>=', $paidLine->order)
            ->increment('order');

        // 4) insert the tip line in the freed slot, styled like the paid line
        DB::table('template_lines')->insert([
            'type' => 'two_column',
            'order' => $paidLine->order,
            'properties' => json_encode([
                'label' => self::TIP_LABELS[$template->language] ?? self::TIP_LABELS['en'],
                'dynamic_field' => 'invoice.tip',
                'font_size' => $paidProperties['font_size'] ?? 9,
                'hide_when_empty' => true,
                // ...
            ]),
            // ...
        ]);

        // 5) rebind "Paid" to total + tip
        $paidProperties['dynamic_field'] = 'invoice.paid_amount';
        DB::table('template_lines')->where('id', $paidLine->id)->update([...]);
    });
}
```

**شرح القرارات:**

| القرار | السبب |
|---|---|
| البحث عن السطر بـ `dynamic_field = payment.amount` لا بالـ `id` أو النص | الـ `id` يختلف بين البيئات، والنص يختلف حسب اللغة وقد يكون الأدمن غيّره. الربط بالحقل هو الشيء الثابت |
| `increment('order')` لكل ما تحته | لتفادي ترتيبين متساويين، فيبقى ترتيب الإيصال حتمياً |
| نسخ `font_size` و`label_width` و`alignment` من سطر «المدفوع» | يبدو السطر الجديد جزءاً طبيعياً من الإيصال لا غريباً عنه، حتى لو كان الأدمن عدّل التنسيق |
| `TIP_LABELS` حسب `language` القالب | القالب الألماني «Trinkgeld»، والإنجليزي «Tip»، والعربي «بقشيش» |
| **idempotent** (يتخطى القالب إن كان فيه سطر بقشيش) | تشغيل الـ migration مرتين، أو على قاعدة seeded بالـ seeder الجديد، لا يكرر السطر |
| `DB::transaction` لكل قالب | الإزاحة + الإدراج + إعادة الربط تنجح معاً أو تفشل معاً، فلا يبقى قالب نصف معدَّل. (هذه عمليات بيانات لا DDL، فالـ transaction آمنة) |
| `down()` كامل | يحذف السطر، ويعيد الترتيب، ويعيد الربط إلى `payment.amount` |

**لماذا غيّرنا «Gegeben» من `payment.amount` إلى `invoice.paid_amount`؟**
`payment.amount` = مجموع الفاتورة (بدون بقشيش) كما شرحنا في 5.4. لو بقي الربط كما هو، لكتب الإيصال «Gegeben 20.00» والزبون أعطى 25، فيبدو البقشيش وكأنه ضاع. «Gegeben» تعني حرفياً «المُعطى»، فيجب أن يساوي المجموع + البقشيش.

**تم التحقق بعد التشغيل محلياً:** الصفوف 51 و52 أُضيفت، والترتيب صحيح في القالبين.

---

### 5.10 الـ Seeder

**الملف:** `database/seeders/InvoiceTemplateSeeder.php`

**ما هو:** نفس التغيير الذي فعلته الـ migration، لكن للتثبيتات **الجديدة** (قاعدة بيانات فارغة + `db:seed`).

**قبل:**

```php
// Payment
$template->lines()->create([
    'section' => 'body',
    'type' => 'two_column',
    'order' => 9,
    'properties' => [
        'label' => 'Gegeben Eur',
        'dynamic_field' => 'payment.amount',
        // ...
    ],
]);

$template->lines()->create([
    'type' => 'text',
    'order' => 10,
    'properties' => ['static_value' => 'Bezahlt per Girocard', ...],
]);
```

**بعد:**

```php
// Payment
$template->lines()->create([
    'section' => 'body',
    'type' => 'two_column',
    'order' => 9,
    'properties' => [
        'label' => 'Trinkgeld',
        'dynamic_field' => 'invoice.tip',
        // ...
        // Only printed when the customer left a tip.
        'hide_when_empty' => true,
    ],
]);

$template->lines()->create([
    'section' => 'body',
    'type' => 'two_column',
    'order' => 10,
    'properties' => [
        'label' => 'Gegeben Eur',
        // Total + tip: what the customer actually handed over.
        'dynamic_field' => 'invoice.paid_amount',
        // ...
    ],
]);

$template->lines()->create([
    'type' => 'text',
    'order' => 11,
    'properties' => ['static_value' => 'Bezahlt per Girocard', ...],
]);
```

ونفس الشيء للقالب الإنجليزي («Tip» / «Paid Eur» / «Paid by Girocard»).

**لماذا الاثنان (migration + seeder)؟** الـ migration تصلح **الموجود**، والـ seeder يضمن أن **الجديد** يولد صحيحاً. بدون الـ seeder، أي تثبيت جديد يولد بالقالب القديم ثم تصلحه الـ migration بعد ذلك (وهذا يعمل بفضل idempotency)، لكن المصدر يجب أن يكون صحيحاً بذاته.

---

### 5.11 نوع السطر `totals_summary`

**الملف:** `resources/views/invoices/line-types/totals-summary.blade.php`، و`lang/{en,de}/invoice_template.php`

**خلفية:** قالب الإيصال فيه نوع سطر اسمه `totals_summary` يعرض كتلة المجاميع كاملة دفعة واحدة. قوالبنا الحالية تستعمل `two_column` منفصلة، لكن أي أدمن يصمم قالباً بـ `totals_summary` يجب أن يرى البقشيش أيضاً.

**قبل (نهاية الملف):**

```blade
    @if($showTotal)
        <div class="totals-row ...">
            <span>{{ __('invoice_template.total') }}:</span>
            <span>{{ number_format($invoice->total_amount ?? 0, 2) }}</span>
        </div>
    @endif
</div>
```

**بعد:**

```blade
    @if($showTotal)
        <div class="totals-row ...">
            <span>{{ __('invoice_template.total') }}:</span>
            <span>{{ number_format($invoice->total_amount ?? 0, 2) }}</span>
        </div>
    @endif

    {{-- Tip sits OUTSIDE the taxed total (not revenue, no VAT), so it is shown
         after it, followed by what the customer actually handed over. --}}
    @if((float) ($invoice->tip_amount ?? 0) > 0)
        <div class="totals-row">
            <span>{{ __('invoice_template.tip') }}:</span>
            <span>+{{ number_format((float) $invoice->tip_amount, 2) }}</span>
        </div>
        <div class="totals-row">
            <span>{{ __('invoice_template.amount_paid') }}:</span>
            <span>{{ number_format((float) ($invoice->total_amount ?? 0) + (float) $invoice->tip_amount, 2) }}</span>
        </div>
    @endif
</div>
```

وأضيف المفتاح `'tip'` إلى `lang/en/invoice_template.php` (`Tip`) و`lang/de/invoice_template.php` (`Trinkgeld`). المفتاح `amount_paid` كان موجوداً مسبقاً.

**لماذا بعد المجموع لا قبله؟** سطرا الحسم يأتيان **قبل** المجموع لأن الحسم يدخل في حسابه. البقشيش **خارج** المجموع، فمكانه المنطقي بعده، وهذا بالضبط الشكل الذي اتفقنا عليه: «المجموع 20 ← بقشيش 5 ← المدفوع 25».

---

### 5.12 تقرير Z: الخدمة `DailyReportService`

**الملف:** `app/Services/DailyReportService.php`

**خلفية:** تقرير Z يحسب «كم مالاً دخل الصندوق». وحدة الحساب هي الفاتورة، وكل فاتورة تُقسَّم إلى صفوف `coverage`، واحد لكل موعد (أي لكل حلاق)، حتى يُحسب إيراد كل موظف.

#### أ) توزيع البقشيش على الحلاقين: `coverageOf()`

**قبل:**

```php
$grossTotal = (float) $appointments->sum(fn (Appointment $a) => (float) $a->total_amount);
$discount   = (float) ($invoice->discount_amount ?? 0);
$netTotal   = (float) $invoice->total_amount;

$rows = [];
$allocated = 0.0;

foreach ($appointments->values() as $index => $appointment) {
    $gross = (float) $appointment->total_amount;

    if ($index === $lastIndex) {
        $amount = round($netTotal - $allocated, 2);
    } elseif ($grossTotal > 0) {
        $amount = round($gross * ($netTotal / $grossTotal), 2);
    } else {
        $amount = 0.0;
    }

    $allocated = round($allocated + $amount, 2);

    $rows[] = [
        // ...
        'discount_share' => ...,
    ];
}
```

**بعد:**

```php
$grossTotal = (float) $appointments->sum(fn (Appointment $a) => (float) $a->total_amount);
$discount   = (float) ($invoice->discount_amount ?? 0);
$netTotal   = (float) $invoice->total_amount;
$tipTotal   = (float) ($invoice->tip_amount ?? 0);

$rows = [];
$allocated = 0.0;
$tipAllocated = 0.0;

foreach ($appointments->values() as $index => $appointment) {
    $gross = (float) $appointment->total_amount;

    if ($index === $lastIndex) {
        $amount = round($netTotal - $allocated, 2);
        $tip    = round($tipTotal - $tipAllocated, 2);
    } elseif ($grossTotal > 0) {
        $amount = round($gross * ($netTotal / $grossTotal), 2);
        $tip    = round($gross * ($tipTotal / $grossTotal), 2);
    } else {
        $amount = 0.0;
        $tip    = 0.0;
    }

    $allocated    = round($allocated + $amount, 2);
    $tipAllocated = round($tipAllocated + $tip, 2);

    $rows[] = [
        // ...
        'discount_share' => ...,
        'tip_share'      => $tip,
    ];
}
```

**شرح الخوارزمية:**
- كل موعد يأخذ من البقشيش **بنسبة سعره من إجمالي الفاتورة**: `tip × (سعر موعده ÷ إجمالي الأسعار)`.
- **الصف الأخير لا يُحسب بالنسبة**، بل يأخذ «الباقي» (`tipTotal − ما وُزِّع`)، فيمتص فرق التقريب.

**مثال:** قص عند أحمد 20€ + لحية عند سامي 10€، والبقشيش 10€:

```text
أحمد  (ليس الأخير): round(20 × 10/30, 2) = round(6.666…) = 6.67
سامي  (الأخير):     10.00 − 6.67                  = 3.33
المجموع                                           = 10.00  ✓ بالسنت
```

لو حسبنا الاثنين بالنسبة لكانت النتيجة `6.67 + 3.33 = 10.00` هنا صدفةً، لكن مع 3 حلاقين وبقشيش 10€ بأسعار متساوية: `3.33 × 3 = 9.99`، **سنت ضائع**. امتصاص الصف الأخير يضمن `Σ حصص البقشيش == البقشيش` دائماً.

**لماذا نفس خوارزمية الحسم بالضبط؟** لأنها تحقق شرطاً يحرسه اختبار موجود: جدول الموظفين يطابق ملخص المبيعات بالسنت. استعمال نفس النمط يعني نفس الضمان، ولا منطق توزيع ثانياً يحتاج مراجعة مستقلة.

**أمر مهم:** `$amount` (إيراد الموظف) **لم يتغير**، فالبقشيش لا يدخل الإيراد، وله حقل منفصل `tip_share`.

#### ب) قسم البقشيش الجديد: `tipSummary()`

```php
private function tipSummary(Collection $transactions): array
{
    $byMethod = ['cash' => 0.0, 'card' => 0.0, 'online' => 0.0];
    $byProvider = [];
    $count = 0;

    foreach ($transactions as $t) {
        $tip = $this->tipOf($t);
        if ($tip <= 0) continue;

        $count++;
        $byMethod[$t['method']] = round($byMethod[$t['method']] + $tip, 2);

        foreach ($t['coverage'] as $c) {
            if ($c['tip_share'] <= 0) continue;
            $byProvider[$c['provider_id']] ??= ['provider_id' => ..., 'provider_name' => ..., 'amount' => 0.0];
            $byProvider[$c['provider_id']]['amount'] = round(... + $c['tip_share'], 2);
        }
    }

    // sorted by amount desc
    return [
        'total'     => round(array_sum($byMethod), 2),
        'count'     => $count,
        'cash'      => $byMethod['cash'],
        'card'      => $byMethod['card'],
        'online'    => $byMethod['online'],
        'providers' => $providers,
    ];
}
```

| المخرج | معناه | لماذا نحتاجه |
|---|---|---|
| `total` | إجمالي البقشيش في الفترة | الرقم الرئيسي |
| `count` | عدد الفواتير التي فيها بقشيش | مؤشر تشغيلي |
| `cash` | بقشيش نقدي | **مطابقة الصندوق:** النقد في الدرج = نقد المبيعات + هذا الرقم |
| `card` | بقشيش بالبطاقة | ليس في الدرج، بل في كشف البطاقات، ويجب أن يُسلَّم للحلاق من الصالون |
| `providers` | لكل حلاق كم يستحق | لتسليم كل حلاق بقشيشه في نهاية اليوم |

**مسجَّل في `build()`:**

```php
'discounts'     => $this->discountSummary($transactions),
'tips'          => $this->tipSummary($transactions),   // جديد
```

#### ج) الموظفون وقائمة المعاملات

```php
// employeeBreakdown(): initial row
'total'         => 0.0,
'tips'          => 0.0,                     // جديد

// per coverage row
$rows[$pid]['tips'] = round($rows[$pid]['tips'] + $c['tip_share'], 2);

// totals
'tips'         => round(array_sum(array_column($rows, 'tips')), 2),

// transactionList(): per receipt
'amount'         => $this->amountOf($t),
'tip'            => $this->tipOf($t),       // جديد
```

ومساعد جديد مطابق لـ `amountOf()`:

```php
/** The tip this report counts for a transaction (post provider-filter). */
private function tipOf(array $transaction): float
{
    return round(collect($transaction['coverage'])->sum(fn (array $c) => $c['tip_share']), 2);
}
```

**لماذا `tipOf()` يجمع من `coverage` لا من `invoice.tip_amount` مباشرة؟**
بسبب **فلتر الموظفين**: لو طلب المدير تقرير أحمد فقط، يُقصّ `coverage` ليبقى صف أحمد وحده، فيعطي `tipOf()` حصته فقط لا بقشيش الفاتورة كله. نفس السبب الذي جعل `amountOf()` مكتوباً بهذا الشكل أصلاً.

**كيف يخدم المهمة:** قرار «البقشيش للحلاق» لا قيمة له إن لم يعرف المالك في نهاية اليوم كم يسلّم كل حلاق، وكم يجب أن يكون في الدرج.

---

### 5.13 تقرير Z: الواجهة والترجمات

**الملفات:** `resources/views/reports/daily-report.blade.php`، و`lang/{ar,de,en}/z_report.php`

#### أ) بطاقة «6 · Trinkgeld» الجديدة

**قبل:** بعد بطاقة «5 · Rabatte» مباشرة تأتي «6 · Betrieb» ثم «7 · Leistungen».

**بعد:** «5 · Rabatte» ← **«6 · Trinkgeld» (جديدة)** ← «7 · Betrieb» ← «8 · Leistungen».

```blade
{{-- 6. Tips — outside every sales/VAT figure above (not revenue, no
     VAT). The cash-drawer line is what the till should hold. --}}
<div class="card">
    <div class="card-head">6 · {{ $tr('tip_title') }}</div>
    <div class="card-body">
        <dl style="margin:0">
            <div class="kv"><dt>{{ $tr('tip_count') }}</dt><dd>{{ $tips['count'] }}</dd></div>
            <div class="kv"><dt>{{ $tr('tip_cash') }}</dt><dd class="num-green">{{ $money($tips['cash']) }}</dd></div>
            <div class="kv"><dt>{{ $tr('tip_card') }}</dt><dd class="num-blue">{{ $money($tips['card']) }}</dd></div>
            <div class="kv strong"><dt>{{ $tr('tip_total') }}</dt><dd class="num-green">{{ $money($tips['total']) }}</dd></div>
            @foreach ($tips['providers'] as $tipRow)
                <div class="kv"><dt>{{ $tipRow['provider_name'] }}</dt><dd>{{ $money($tipRow['amount']) }}</dd></div>
            @endforeach
            <div class="kv strong">
                <dt>{{ $tr('cash_drawer') }}</dt>
                <dd class="num-green">{{ $money($sales['buckets']['cash']['amount'] + $tips['cash']) }}</dd>
            </div>
        </dl>
        <p class="section-note">{{ $tr('tip_note') }}</p>
    </div>
</div>
```

**سطر «Bargeld in der Kasse»:** كان التقرير يعرض نقد المبيعات فقط. بعد البقشيش النقدي سيجد الكاشير في الدرج **أكثر** من الرقم المطبوع، ويظن أن هناك خطأ. هذا السطر يعطيه الرقم الذي يجب أن يطابق الدرج فعلاً.

#### ب) سطر البقشيش في بطاقة كل حلاق

**قبل:**

```blade
<div class="kv strong emp-total">
    <dt>{{ $tr('total_revenue') }}</dt>
    <dd class="num-green">{{ $money($row['total']) }}</dd>
</div>
```

**بعد:**

```blade
<div class="kv strong emp-total">
    <dt>{{ $tr('total_revenue') }}</dt>
    <dd class="num-green">{{ $money($row['total']) }}</dd>
</div>
@if ($row['tips'] > 0)
    <div class="kv">
        <dt>{{ $tr('tip_title') }}</dt>
        <dd class="num-amber">{{ $money($row['tips']) }}</dd>
    </div>
@endif
```

**لماذا لم نضف عموداً في جدول الموظفين؟** الجدول فيه 7 أعمدة مالية مضغوطة في 61% من عرض ورقة A4، وسبق أن خرج العمود الأخير خارج الورقة (القسم 34.5 في `STAFF_DASHBOARD.md`). عمود ثامن كان سيكسر الطباعة، لذلك وضعنا البقشيش في البطاقة المنفصلة وفي بطاقة كل حلاق.

#### ج) الترجمات الجديدة (`z_report.php`)

| المفتاح | de | ar | en |
|---|---|---|---|
| `tip_title` | Trinkgeld | البقشيش | Tips |
| `tip_count` | Belege mit Trinkgeld | فواتير فيها بقشيش | Receipts with a tip |
| `tip_cash` | Trinkgeld bar | بقشيش نقداً | Tips in cash |
| `tip_card` | Trinkgeld per Karte | بقشيش بالبطاقة | Tips by card |
| `tip_total` | Trinkgeld gesamt | إجمالي البقشيش | Total tips |
| `cash_drawer` | Bargeld in der Kasse (Umsatz + Trinkgeld) | النقد في الصندوق (مبيعات + بقشيش) | Cash in drawer (sales + tips) |
| `tip_note` | Trinkgeld steht dem Mitarbeiter zu, ist kein Umsatz… | البقشيش للحلاق، ليس إيراداً ولا ضريبة عليه… | Tips belong to the provider, are not revenue… |

---

### 5.14 الاختبارات

#### أ) `tests/Feature/Money/UnifiedPaymentFlowTest.php`

**قبل:** كان هناك اختبار يتأكد أن الزيادة **تُرفض**:

```php
it('rejects zero, overpayment, inactive methods and non-onsite methods without partial writes', ...)
    ...
    'overpayment' => 101.00,   // ← كان يتوقع InvalidArgumentException
})->with(['zero', 'overpayment', 'inactive', 'online']);
```

**بعد:** حُذفت حالة `overpayment` من اختبار الرفض، لأنها لم تعد خطأ، وأُضيفت أربعة اختبارات:

| الاختبار | ماذا يثبت |
|---|---|
| `treats a higher amount as the full price plus a tip outside the taxed totals` | دفع 115 على فاتورة 100 ينتج: `total=100`، `discount=0`، `tip=15`، و`subtotal/tax` **مطابقان تماماً** لحساب 100 بلا بقشيش، و`payment.amount=100` و`payment.tip=15` |
| `records no tip for a full or discounted payment` (3 حالات) | السعر الكامل `null`، وكتابة نفس المبلغ 100، والحسم 90: كلها `tip=0`، والحسم ما زال `10` كما كان |
| `prints the tip and the amount actually handed over on the receipt` | `invoice.tip = "+5.00"` و`paid_amount = "105.00"`، وبدون بقشيش: `tip = ""` (ليختفي السطر) و`paid_amount = "100.00"` |
| `reports payment errors in the language of the current user` (3 لغات) | نفس الخطأ (مبلغ صفر) يظهر بالألمانية والإنجليزية والعربية حسب `setLocale` |

**لماذا حذفنا حالة `overpayment` بدل تركها؟** لأن ذلك الاختبار كان يحرس **السلوك القديم الذي طُلب تغييره**. إبقاؤه يعني اختباراً يفشل دائماً أو، أسوأ، من يأتي بعدنا «يصلح» الكود ليعيد المنع.

#### ب) `tests/Feature/DailyReportTest.php`

| الاختبار | ماذا يثبت |
|---|---|
| `test_a_tip_is_reported_apart_from_sales` | فاتورتان (50 نقداً + بقشيش 5، و30 بطاقة + بقشيش 2): المبيعات والضريبة وإيراد الموظفين = **80** (لا 87)، والبقشيش = 7 (5 نقد + 2 بطاقة) |
| `test_a_tip_on_a_linked_invoice_is_split_pro_rata` | فاتورة واحدة لحلاقين (20 + 10) وبقشيش 10: أحمد **6.67** وسامي **3.33** والمجموع **10.00** بالسنت |

والمساعد `makePaidBooking()` أخذ معاملاً اختيارياً جديداً `float $tip = 0.0`، والقيمة الافتراضية صفر فلا يتأثر أي اختبار قديم.

#### ج) النتيجة

```text
UnifiedPaymentFlowTest + DailyReportTest:  31 passed (135 assertions)
الحزمة كاملة:  529 passed · 18 failed · 3 skipped
```

الـ 18 فشلاً **خارج نطاق هذا العمل**:
- 12 فشلاً معروفاً مسبقاً (Fiskaly، رفع الصورة، حذف الحساب، الصفحة الترحيبية، عرض كلمة المرور، rate limit).
- 6 في `PhoneNumberValidationTest`، مصدرها تعديلات أرقام الهاتف غير المحفوظة التي كانت في الشجرة قبل البدء (`PhoneNumber.php`, `PhoneNumberNormalizer.php`). لم يُلمس أي منها.

---

## 6. مثال رقمي كامل من البداية للنهاية

**السيناريو:** خدمة «Beard Trim & Shape» بسعر 20.00€ شامل ضريبة 19%. الزبون يعطي 25.00€ نقداً.

**1. الكاشير في المودال:**

```text
Zu zahlender Betrag:  [ 25 ]
Trinkgeld: +5.00                     ← أخضر، يظهر أثناء الكتابة
(لا مربع تأكيد: 5 ليست أكبر من 20)
[Bestätigen & Rechnung drucken]      ← مفعّل
```

**2. الخادم (`splitFinalAmount`):**

```text
itemsGross = 20.00
paid       = 25.00   > 20.00
→ charged  = null   (السعر الكامل)
→ tip      = bcsub(25.00, 20.00) = 5.00
```

**3. `applyFinalAmount(null)`**، كما كان دائماً للسعر الكامل:

```text
discount_amount = 0.00
subtotal        = 16.81   (20 ÷ 1.19)
tax_amount      = 3.19
total_amount    = 20.00
```

**4. ما يُحفظ:**

| الجدول | الحقل | القيمة |
|---|---|---|
| invoices | total_amount | 20.00 |
| invoices | subtotal / tax_amount | 16.81 / 3.19 |
| invoices | discount_amount | 0.00 |
| invoices | **tip_amount** | **5.00** |
| invoices | invoice_data.tip_amount | "5.00" |
| payments | amount | 20.00 |
| payments | **tip_amount** | **5.00** |

المعادلات سليمة: `16.81 + 3.19 = 20.00 = payment.amount` ✓

**5. الإيصال:**

```text
Beard Trim & Shape           20.00
- - - - - - - - - - - - - - - - -
Netto                        16.81
+ 19,0% MwSt.                 3.19
═════════════════════════════════
Summe Eur                    20.00
═════════════════════════════════
Trinkgeld                    +5.00     ← سطر جديد
Gegeben Eur                  25.00     ← كان سيطبع 20.00
Bezahlt per Girocard
```

**6. تقرير Z لذلك اليوم:**

```text
Gesamtumsatz (Bar)                       20.00   ← لم يتغير، البقشيش ليس مبيعاً
MwSt. 19%                                 3.19   ← لم يتغير
6 · Trinkgeld
   Belege mit Trinkgeld                      1
   Trinkgeld bar                          5.00
   Trinkgeld gesamt                       5.00
   <اسم الحلاق>                          5.00
   Bargeld in der Kasse (Umsatz+Trinkgeld) 25.00  ← يطابق الدرج
```

**للمقارنة، الحسم بلا تغيير:** لو كتب الكاشير 18:

```text
المودال:   Rabatt: -2.00   (أحمر)
الفاتورة:  total=18.00, discount=2.00, subtotal=15.13, tax=2.87, tip=0.00
الإيصال:   Artikel gesamt 20.00 / Rabatt -2.00 / ... / Summe 18.00 / Gegeben 18.00
           (سطر Trinkgeld مخفي تلقائياً)
```

---

## 7. ما لم يتغير وحدود معروفة

**لم يتغير عمداً:**
- **مسار الحسم كاملاً**: `applyFinalAmount()` لم يُعدَّل سطر واحد فيه.
- **الفواتير المدفوعة سابقاً**: `tip_amount = 0`، وإيصالاتها تُطبع كما كانت بالضبط (`paid_amount = total + 0`).
- **`StaffDashboard::processPayment()`**: لم يحتج تعديلاً. ما زال يمرر `paymentAmount` حين يختلف عن `paymentBaseline`، والخدمة هي التي تقرر المعنى.
- **`pos-receipt.blade.php`**: يخص مسار Fiskaly المعطَّل عمداً (`FISKALY_ENABLED=false`)، فلم يُمَس.

**حدود معروفة:**

| البند | الوضع | لماذا تُرك |
|---|---|---|
| شاشتا الدفع في Filament | صارت الزيادة فيهما بقشيشاً تلقائياً لأنهما تستعملان نفس الخدمة، **لكن** النص المساعد تحت حقل المبلغ ما زال يعرض الضريبة محسوبة على المبلغ المكتوب كله | عرض فقط، ولا يؤثر على ما يُحفظ. يمكن تحسينه لاحقاً |
| تأكيد البقشيش الكبير | في الواجهة فقط | الخادم لا يستطيع التمييز بين بقشيش كبير حقيقي وخطأ كتابة. القرار للكاشير |
| التصنيف الضريبي للبقشيش | مبني على قرار «للحلاق» | **يجب تأكيده مع المحاسب.** لو صار إيراداً للصالون، يتغير `splitFinalAmount()` فقط |
| ملف `lang/ar/invoice_template.php` | غير موجود (مشكلة سابقة) | الإيصالات الحالية ألمانية وإنجليزية فقط. خارج نطاق المهمة |

---

## 8. التحقق والنشر

**على السيرفر:**

```bash
php artisan migrate        # يشغّل 2026_09_25_000001 و 2026_09_25_000002
php artisan view:clear
```

**تحقق يدوي مقترح في المتصفح** (بعد Ctrl+Shift+R):

1. افتح موعداً غير مدفوع ← «دفع» ← اكتب مبلغاً **أكبر** بقليل ← يظهر «Trinkgeld +x» بالأخضر ← أكِّد ← يُطبع الإيصال مع سطر Trinkgeld و«Gegeben» = المجموع + البقشيش.
2. اكتب مبلغاً **أكثر من ضعف** الفاتورة ← الزر معطَّل ويظهر مربع التأكيد ← علّم عليه ← يعمل الزر.
3. اكتب مبلغاً **أقل** ← «Rabatt -x» بالأحمر ← الإيصال كما كان سابقاً.
4. اكتب **0** والواجهة بالألمانية ← الرسالة بالألمانية.
5. افتح تبويب التقارير ← بطاقة «6 · Trinkgeld» و«Bargeld in der Kasse».

**تحقق بالاختبارات:**

```bash
php artisan test --compact tests/Feature/Money/UnifiedPaymentFlowTest.php tests/Feature/DailyReportTest.php
```
