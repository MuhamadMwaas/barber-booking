<div lang="ar" dir="rtl">

# MON-01 — إصلاح اختلاف حساب ضريبة القيمة المضافة بين طبقتين

> **التاريخ:** 10 سبتمبر 2026
> **الثغرة:** `MON-01` في [`SECURITY_AUDIT_2026-08-29.md`](../../SECURITY_AUDIT_2026-08-29.md) — 🔴 Critical (مانع إطلاق)
> **الحالة:** ✅ **مُصلحة ومُتحقَّق منها بتنفيذ فعلي**
> **الاختبارات:** 32 اختباراً جديداً · 7 اختبارات ضريبة كانت فاشلة صارت ناجحة · **خط الأساس 17 فشلاً ← 10** · صفر تراجعات

---

## فهرس المحتويات

1. [ما كانت المشكلة](#1-ما-كانت-المشكلة)
2. [السبب الجذري بالتفصيل](#2-السبب-الجذري-bcdiv-تقتطع-ولا-تقرّب)
3. [الأعطال الثلاثة](#3-الأعطال-الثلاثة-من-جذر-واحد)
4. [سبع نسخ من معادلة واحدة](#4-سبع-نسخ-من-معادلة-واحدة)
5. [لماذا نجا العطل من الاختبارات](#5-لماذا-نجا-العطل-من-ثلاثة-ملفات-اختبار)
6. [الإصلاح — كل تعديل بالكود](#6-الإصلاح--كل-تعديل-بالكود)
7. [القرارات التصميمية](#7-القرارات-التصميمية)
8. [البيانات القديمة و GoBD](#8-البيانات-القديمة-و-gobd)
9. [الاختبارات](#9-الاختبارات)
10. [قواعد يجب عدم كسرها](#10-قواعد-يجب-عدم-كسرها)

---

## 1. ما كانت المشكلة

كان النظام يحسب الضريبة العكسية في **سبعة أماكن** بثلاث دقّات مختلفة، فتنتج **معاملة واحدة رقمين مختلفين للضريبة** في جدولين مختلفين.

### المثال الملموس: زبون واحد، خدمة واحدة بـ 50.00 €

```
الزبون يحجز «صبغة» بـ 50.00 € — ضريبة 19%
════════════════════════════════════════════════════════════════

الخطوة ١  BookingService::calculateTotals()        [دقة 6 ✅]
          appointments  →  subtotal 42.02   tax_amount 7.98   total 50.00
          📧 إيميل التأكيد للزبون يطبع: ضريبة 7.98 €

الخطوة ٢  createDraftInvoice()  — نسخ حرفي
          invoices      →  subtotal 42.02   tax_amount 7.98   total 50.00

──────────  الزبون يأتي للمحل، الموظف يضغط «تحصيل»  ──────────

الخطوة ٣  rebuildAggregatedInvoice() → extractTax()  [دقة 2 ❌]
          invoice_items →  unit_price 42.01   tax_amount 7.99   total 50.00
          invoices      →  subtotal 42.01   tax_amount 7.99   total 50.00
                                     ▲▲▲▲▲            ▲▲▲▲
                           تغيّرت بصمت — لا خصم، لا تعديل، نفس الـ 50 €

الخطوة ٤  applyFinalAmount(null) → extractTax()      [دقة 2 ❌]
          invoices      →  subtotal 42.01   tax_amount 7.99   discount 0.00

الخطوة ٥  createPaymentRecord() → calculateReverseTax()  [دقة 6 ✅]
          payments      →  subtotal 42.02   tax_amount 7.98
                                     ▲▲▲▲▲            ▲▲▲▲
                                رجعت للقيمة الأولى!

          🧾 الإيصال المطبوع للزبون يطبع: ضريبة 7.99 €
```

### النتيجة

| الجدول | `tax_amount` | من كتبه |
|---|---|---|
| `appointments` | **7.98** | دقة 6 ✅ |
| `invoices` | **7.99** ❌ | دقة 2 ❌ |
| `payments` | **7.98** | دقة 6 ✅ |

**والجدول الوحيد الخاطئ هو `invoices`** — وهو بالضبط الجدول الذي يُبنى عليه الإقرار الضريبي الألماني، ويُطبع منه الإيصال. و`payments` الذي يُفترض أن يوثّق نفس النقود يخالف الفاتورة التي يسدّدها.

### الإثبات — نُفِّذ على الكود فعلياً

```
GROSS     | TaxCalculator(scale 2)   | BookingService(scale 6)  | متطابق؟
19.00     | net 15.96   tax 3.04     | net 15.97   tax 3.03     | ✗
25.00     | net 21.00   tax 4.00     | net 21.01   tax 3.99     | ✗
50.00     | net 42.01   tax 7.99     | net 42.02   tax 7.98     | ✗
35.00     | net 29.41   tax 5.59     | net 29.41   tax 5.59     | ✓
99.99     | net 84.02   tax 15.97    | net 84.03   tax 15.96    | ✗
19.99     | net 16.79   tax 3.20     | net 16.80   tax 3.19     | ✗
29.90     | net 25.12   tax 4.78     | net 25.13   tax 4.77     | ✗
45.00     | net 37.81   tax 7.19     | net 37.82   tax 7.18     | ✗
15.00     | net 12.60   tax 2.40     | net 12.61   tax 2.39     | ✗
12.50     | net 10.50   tax 2.00     | net 10.50   tax 2.00     | ✓
60.00     | net 50.42   tax 9.58     | net 50.42   tax 9.58     | ✓
```

**٨ من ١١ سعراً شائعاً تُنتج ضريبة مختلفة.** وعمود `BookingService` هو الصحيح رياضياً.

---

## 2. السبب الجذري: `bcdiv` تقتطع ولا تقرّب

كل شيء يعود إلى سطر واحد في [`TaxCalculatorService`](../../app/Services/TaxCalculatorService.php):

```php
private int $scale = 2;                              // ← الجريمة
$netHigh = bcdiv($gross, $factor, $this->scale);     // ← القسمة بدقة 2
```

`bcdiv` في PHP **لا تقرّب** — تحذف الخانات الزائدة كما هي:

```
50.00 ÷ 1.19 = 42.016806722689...

bcdiv(..., 2)  →  "42.01"    ← اقتطاع: الخانة .0168 رُميت
التقريب الصحيح →  42.02      ← لأن 42.0168 أقرب إلى 42.02
```

وبعدها `tax = 50.00 − 42.01 = 7.99` بدل `7.98`.

### والمفارقة: الكود كان يحتوي على دالة تقريب سليمة، ويستدعيها متأخراً

```php
$netHigh = bcdiv($gross, $factor, 2);   // 42.01  ← المعلومة فُقدت هنا
$net     = $this->bcRound($netHigh, 2); // 42.01  ← تقريب رقم مقرَّب مسبقاً = بلا فائدة
```

**تقريبُ رقمٍ فَقَد خاناته أصلاً لا يُعيدها.** هذا هو جوهر العطل: التقريب كان يحدث **أثناء** الحساب فينشر الخطأ، بدل أن يحدث في **نهايته** فيحصره.

---

## 3. الأعطال الثلاثة من جذر واحد

### 3.1 اقتطاع ناتج القسمة (العطل المُبلَّغ)

موصوف أعلاه. فرق سنت في 8 من 11 سعراً شائعاً.

### 3.2 النِسَب الكسرية تُدمَّر بالكامل

```php
bcdiv('19.5', '100', 2)  →  '0.19'    // الخانة الثانية اقتُطعت!
```

فيصبح المعامل `1.19` بدلاً من `1.195`:

```
gross 100.00 بنسبة 19.5%
  extractTax  →  net 84.03   tax 15.97    ← يحسب كأن النسبة 19%
  الصحيح      →  net 83.68   tax 16.32
  الفرق: 0.35 € على فاتورة واحدة (~2%، لا سنت واحد)
```

> **تصحيح لما جاء في تقرير التدقيق:** التقرير قال إن النسبة المخفّضة الألمانية (7%) «تعمل صدفةً». هذا غير دقيق — تحققتُ رقمياً: المعامل `1.07` صحيح فعلاً بالصدفة، **لكن اقتطاع ناتج القسمة يبقى قائماً**، فـ 7% تُخطئ أيضاً:
>
> ```
> 19.00 @ 7%  →  extractTax: net 17.75 tax 1.25  |  الصحيح: net 17.76 tax 1.24  ✗
> 50.00 @ 7%  →  extractTax: net 46.72 tax 3.28  |  الصحيح: net 46.73 tax 3.27  ✗
> ```

### 3.3 اقتطاع **المدخل** نفسه — لم يُذكر في التقرير

```php
private function normalizeAmount($amount): string
{
    // ...
    return bcadd($amount, '0', $this->scale);   // ← الدقة 2!
}
```

كل مبلغ يُطبَّع إلى **منزلتين قبل أي حساب**. فالنداء `extractTax('33.333333', ...)` كان يصل إلى المعادلة كـ `'33.33'` — **المدخل يُدمَّر عند الباب**. هذا يفسّر أحد الاختبارات الفاشلة.

### 3.4 لغم إعادة بناء الـ gross — لم يُذكر في التقرير، وهو الأخطر مفهومياً

[`InvoiceItem`](../../app/Models/InvoiceItem.php) كان يخزّن `unit_price` كـ **net**، ثم يعيد بناء الـ gross بـ `addTax` عند كل حفظ:

```php
// InvoiceItem.php — يعمل عند كل static::saving
$result = app(TaxCalculatorService::class)->addTax($netSubtotal, $this->tax_rate, 2);
$this->total_amount = $result['gross'];   // ← يُكتب فوق الـ gross الأصلي
```

والرحلة `extractTax → addTax` تُفقد سنتاً في **كل** سعر:

```
gross 50.00  →  net 42.01  →  رجوعاً للـ gross: 49.99   *** فُقد 0.01 ***
gross 19.00  →  net 15.96  →  رجوعاً للـ gross: 18.99   *** فُقد 0.01 ***
gross 25.00  →  net 21.00  →  رجوعاً للـ gross: 24.99   *** فُقد 0.01 ***
gross 60.00  →  net 50.42  →  رجوعاً للـ gross: 59.99   *** فُقد 0.01 ***
```

**أي حفظ لبند فاتورة كان يُصغّر الفاتورة سنتاً — حتى لو كانت مُنهاة ومطبوعة.**

#### لكنه كان خامداً، لا نازفاً

مساران فقط ينشئان بنود الفواتير، وكلاهما ملفوف بـ `InvoiceItem::withoutEvents()`:
[`InvoiceService.php:174`](../../app/Services/InvoiceService.php#L174) و[`:489`](../../app/Services/InvoiceService.php#L489). ولا يوجد Filament Resource لبنود الفواتير. فهو **لغم**، لا جرح: أول مسار مستقبلي يحفظ بنداً بلا `withoutEvents` يُفعّله.

#### واكتشاف أعمق: الرحلة الدائرية **مستحيلة** رياضياً

الإصلاح المقترح في التقرير (رفع الدقة الداخلية) **لا يصلح** هذا، لأنه غير قابل للإصلاح:

```
=== rate 19% (بعد إصلاح الدقة) ===
  99.99  → net 84.03 → رجوعاً: 100.00   ✗
  45.00  → net 37.82 → رجوعاً:  45.01   ✗
  15.00  → net 12.61 → رجوعاً:  15.01   ✗
```

السبب:

```
45.00 ÷ 1.19 = 37.8151...  →  net يُقرَّب إلى 37.82
37.82 × 1.19 = 45.0058     →  gross يُقرَّب إلى 45.01   ≠ 45.00
```

الاستخراج العكسي **دالة غير عكوسة**: عدة قيم gross تنتج نفس الـ net المقرَّب، فلا يمكن استرجاع الـ gross الأصلي من الـ net **أبداً**، بأي دقة.

> ### 🔑 الخلاصة المعمارية
>
> في نظام أسعاره GROSS، **الـ gross هو الحقيقة ويجب تخزينه**. الـ net مشتقٌّ منه، ولا يجوز أن يُعاد بناء الـ gross من الـ net. رفع الدقة **ضروري لكنه غير كافٍ** — يجب معه إيقاف إعادة الحساب الأمامية.

---

## 4. سبع نسخ من معادلة واحدة

جرد كامل قبل الإصلاح:

| # | الموقع | التقنية | الدقة | حيّ؟ | يكتب في |
|---|---|---|---|---|---|
| 1 | `BookingService::calculateTotals` | bcmath | **6** ✅ | ✅ | `appointments` |
| 2 | `TaxCalculatorService::extractTax` | bcmath | **2** ❌ | ✅ | `invoices`, `invoice_items` |
| 3 | `TaxCalculatorService::addTax` | bcmath | **2** ❌ | 🕰️ خامل | `invoice_items` (observer) |
| 4 | `InvoiceFinalizationService::calculateReverseTax` | bcmath + `bcscale()` عام | 6 ⚠️ | ✅ | `payments` |
| 5 | `InvoiceService::calculateReverseTax` | **float** | — ⚠️ | ✅ | `invoices` (Filament) |
| 6 | `CreateAppointment::calculateTotalsFromServices` | **float** | — ⚠️ | ✅ | `appointments` (Filament) |
| 7 | `BookingService::addServiceDifferentProvider` | **float** | — ⚠️ | ✅ | `appointments` (أبناء) |

وثلاث نسخ **ميتة تحسب الضريبة بالاتجاه الخطأ** — تُضيف 19% *فوق* أسعار تشملها أصلاً:

| الموقع | الخطأ |
|---|---|
| `BookingService::calculateTotalsInverse` | `tax = subtotal × rate/100` على gross |
| `AppointmentCreationService::calculateTotalsFromServices` | نفسه — خدمة بـ 50.00 تخرج 59.50 |
| `BookingService2` (428 سطراً) | ملف كامل لا يُستدعى من أي مكان |

---

## 5. لماذا نجا العطل من ثلاثة ملفات اختبار

كان عندك **٣ ملفات اختبار للضريبة** ومع ذلك مرّ العطل. السببان:

### 5.1 القيمة المُختبَرة تقسم بالضبط

```php
$result = $this->service->extractTax('119.00', '19', 2);
$this->assertSame('100.00', $result['net']);
```

`119.00 ÷ 1.19 = 100` **بالضبط** — بلا كسور، فلا اقتطاع يظهر.

> وبالمناسبة: سعر الخدمة الافتراضي في `SalonFixture` هو `100.00`، و`100 ÷ 1.19 = 84.0336` يقتطع ويقرّب إلى **نفس** `84.03` — فحتى اختبارات الحجز الشاملة لم تكن تستطيع كشف العطل.

### 5.2 بقية الاختبارات تفحص الاتساق الذاتي فقط

```php
$this->assertSame($result['gross'], bcadd($result['net'], $result['tax'], 2));
```

وهذا الشرط **يتحقق حتى مع الرقم الخاطئ**: `42.01 + 7.99 = 50.00` ✓ — الاختبار يمرّ بسعادة بينما الـ net خطأ.

**لم يوجد اختبار واحد يسأل: هل الـ net صحيح رياضياً؟ ولا اختبار واحد يقارن طبقتين.**

### 5.3 وسبعة اختبارات كانت فاشلة أصلاً وأحدها يوثّق العطل بالحرف

```
FAILED  TaxCalculatorService2Test > cross validation with alternative calculations
        الفرق في Net كبير جداً: 0.4735 للقيم 123.45, 13.5
```

`13.5%` — نسبة كسرية، فرق 0.47. **الاختبار كان يصرخ بالعطل ولم يسمعه أحد.**

---

## 6. الإصلاح — كل تعديل بالكود

### 6.1 `app/Services/TaxCalculatorService.php` — الجذر (أُعيدت كتابته)

#### أ) الدقة الداخلية صارت نسبية بالدقة المطلوبة، لا ثابتة

```php
// قبل
private int $scale = 2;                                   // ثابتة، وهي المشكلة

// بعد
private const MIN_INTERNAL_SCALE = 10;
private const MAX_PRECISION = 12;

private function internalScale(int $precision): int
{
    return max(self::MIN_INTERNAL_SCALE, $precision + 8);
}
```

> **لماذا نسبية وليست 10 ثابتة كما اقترح التقرير؟** لأن الخدمة تقبل `$precision` حتى 12. دقة داخلية ثابتة عند 10 تعني أن ناتجاً بدقة 12 يُحسب بدقة **أقل** من دقته المطلوبة — وهو نفس العطل بشكل آخر. القاعدة «احسب أوسع مما تحتاج» يجب أن تصمد لأي دقة مطلوبة، فصارت `precision + 8`.

#### ب) `extractTax` — التقريب مرة واحدة في النهاية

```php
// قبل
$factor  = bcadd('1', bcdiv($rate, '100', $this->scale), $this->scale);  // 2 → '0.19'
$netHigh = bcdiv($gross, $factor, $this->scale);                          // 2 → اقتطاع
$taxHigh = bcsub($gross, $netHigh, $this->scale);
$net = $this->bcRound($netHigh, $precision);   // تقريب رقم مقرَّب أصلاً

// بعد
$scale   = $this->internalScale($precision);        // 10 على الأقل
$factor  = bcadd('1', bcdiv($rate, '100', $scale), $scale);
$netHigh = bcdiv($gross, $factor, $scale);
$taxHigh = bcsub($gross, $netHigh, $scale);

// التقريب في النهاية فقط
$net = $this->bcRound($netHigh, $precision);
$tax = $this->bcRound($taxHigh, $precision);
return $this->reconcile($net, $tax, $this->bcRound($gross, $precision), $precision);
```

#### ج) إزالة كل نداءات `bcscale()` — إصلاح `MON-06` ضمناً

```php
// قبل — في extractTax و addTax و calculateBulk
bcscale($this->scale);
// ... و
bcscale($precision);

// بعد — لا وجود لـ bcscale في الملف كله.
// كل نداء bcmath يمرِّر دقته صريحةً كوسيط ثالث.
```

`bcscale()` **حالة عامة على مستوى الطلب كله**: تغيّر بصمت الدقة الافتراضية لكل عملية bcmath تجري بعدها في نفس الطلب — بما فيها الحسابات المالية في طبقات أخرى لا علاقة لها بهذا النداء.

#### د) `normalizeAmount` لم تعد تقتطع المدخل

```php
// قبل
private function normalizeAmount($amount): string
{
    // ...
    return bcadd($amount, '0', $this->scale);   // ← يقتطع المدخل إلى منزلتين
}

// بعد — الدقة تُمرَّر صريحةً
private function normalizeAmount($amount, int $scale): string
{
    // ...
    return bcadd($this->numericToString($amount), '0', $scale);
}
```

وأُضيفت `numericToString()` لأن `(string) 1.0E-7` تُنتج `"1.0E-7"` وهي **غير صالحة لـ bcmath** — والمبالغ الصغيرة جداً تصل بهذه الصيغة من حسابات float:

```php
private function numericToString($value): string
{
    if (is_string($value) && stripos($value, 'e') === false) { return $value; }
    if (is_int($value)) { return (string) $value; }
    return rtrim(rtrim(number_format((float) $value, 18, '.', ''), '0'), '.') ?: '0';
}
```

#### هـ) التسوية صارت حتمية — على الضريبة دائماً

```php
// قبل — غير حتمي
if (bccomp($net, $tax, $precision) >= 0) {
    $net = bcadd($net, $diff, $precision);      // يعدّل الصافي أحياناً
} else {
    $tax = bcadd($tax, $diff, $precision);      // والضريبة أحياناً
}

// بعد — دالة واحدة مشتركة بين extractTax و addTax و calculateBulk
private function reconcile(string $net, string $tax, string $gross, int $precision): array
{
    $diff = bcsub($gross, bcadd($net, $tax, $precision), $precision);

    if (bccomp($diff, '0', $precision) !== 0) {
        $tax = bcadd($tax, $diff, $precision);   // الضريبة دائماً
    }

    return ['net' => $net, 'tax' => $tax, 'gross' => $gross];
}
```

#### و) `calculateBulk` تحترم الدقة المطلوبة وتتجاهل الصفوف الناقصة

```php
// قبل — تقرّب كل بند بدقة 2 مهما كانت الدقة المطلوبة
$result = $this->extractTax($item['price'], $taxRate, $this->scale);

// بعد
if (! isset($item['price']) || ! $this->isUsableAmount($item['price'])) { continue; }
if (! $this->isUsableAmount($taxRate)) { continue; }
$result = $this->extractTax($item['price'], $taxRate, $precision);
```

`isUsableAmount()` جديدة: الاستدعاء يأتي أحياناً من مصفوفة نموذج Filament فيها صفوف فارغة أو نصف مكتملة، فكانت تُرمى `InvalidArgumentException` على صف فارغ.

---

### 6.2 `app/Models/InvoiceItem.php` — نزع اللغم

```php
// قبل — يعيد بناء الـ gross من الـ net عند كل حفظ
public function calculateTotal(): void
{
    $netSubtotal = bcmul((string) $this->quantity, (string) $this->unit_price, 2);
    $result = app(TaxCalculatorService::class)->addTax($netSubtotal, $this->tax_rate, 2);

    $this->tax_amount   = $result['tax'];
    $this->total_amount = $result['gross'];   // ← يفقد سنتاً في كل سعر
}

// بعد — الـ gross المخزَّن هو الحقيقة
public function calculateTotal(): void
{
    $tax  = app(TaxCalculatorService::class);
    $rate = (string) ($this->tax_rate ?? 0);

    $storedGross = (string) ($this->total_amount ?? 0);

    // الـ gross مخزَّن ⇒ هو المرجع. استخرج الضريبة منه ولا تلمسه.
    if (is_numeric($storedGross) && bccomp($storedGross, '0', 2) > 0) {
        $result = $tax->extractTax($storedGross, $rate, 2);

        $this->tax_amount   = $result['tax'];
        $this->total_amount = $result['gross'];

        return;
    }

    // لا gross بعد: البند بُني من سعر وحدة صافٍ، فنشتق الإجمالي أمامياً.
    $netSubtotal = bcmul((string) ($this->quantity ?? 1), (string) ($this->unit_price ?? 0), 2);
    $result = $tax->addTax($netSubtotal, $rate, 2);

    $this->tax_amount   = $result['tax'];
    $this->total_amount = $result['gross'];
}
```

**السلوك الجديد:**

| الحالة | ما يحدث |
|---|---|
| `total_amount` موجود | يُحفظ كما هو، والضريبة تُستخرج منه عكسياً |
| `total_amount` مفقود | بند بُني من صافٍ فقط ⇒ يُشتق الإجمالي أمامياً |

> ⚠️ **لتغيير سعر بندٍ قائم: اكتب `total_amount` الجديد (الـ gross).** كتابة `unit_price` وحده لن تغيّر الإجمالي — وهذا **مقصود**، وهو ثمن جعل الـ gross مرجعاً.

---

### 6.3 `app/Services/BookingService.php` — حذف 40 سطراً مكرراً

النسخة القديمة كانت **الصحيحة** (دقة 6) لكنها نسخة مستقلة. بعد أن صارت الحاسبة صحيحة، لا مبرر لبقائها:

```php
// قبل — ~55 سطراً: factor، حلقة، bcdiv، bcsub، bcRound لكل بند، تسوية
private function calculateTotals(array $preparedServices): array
{
    $internalScale = 6;
    $taxRate = (string) get_setting('tax_rate', '0');
    $factor = '1';
    if (bccomp($taxRate, '0', 6) === 1) {
        $factor = bcadd('1', bcdiv($taxRate, '100', $internalScale), $internalScale);
    }
    // ... 40 سطراً أخرى
}

// بعد
private function calculateTotals(array $preparedServices): array
{
    $totalDuration = array_sum(array_column($preparedServices, 'duration_minutes'));
    $taxRate = (string) get_setting('tax_rate', '0');

    $totals = $this->taxCalculator->calculateBulk(
        array_map(
            fn (array $service): array => [
                'price'    => (string) ($service['price'] ?? '0'),
                'tax_rate' => $taxRate,
            ],
            $preparedServices
        ),
        2
    );

    return [
        'subtotal'       => $totals['net'],
        'tax_amount'     => $totals['tax'],
        'total_amount'   => $totals['gross'],
        'total_duration' => $totalDuration,
    ];
}
```

**وحُذفت معها:**
- `private function bcRound()` — كانت تُستدعى من `calculateTotals` فقط
- `private function calculateTotalsInverse()` — كود ميت يحسب الضريبة بالاتجاه الخطأ

**وأُضيفت الحاسبة إلى الـ constructor:**

```php
public function __construct(
    protected BookingValidationService $validationService,
    // ...
    protected ?TaxCalculatorService $taxCalculator = null,
) {
    // ...
    $this->taxCalculator = $this->taxCalculator ?? app(TaxCalculatorService::class);
}
```

#### والنسخة السابعة — `addServiceDifferentProvider`

```php
// قبل — float
$taxRate = (float) get_setting('tax_rate', 19);
if ($taxRate > 0) {
    $net = round($price / (1 + ($taxRate / 100)), 2);
    $tax = round($price - $net, 2);
} else {
    $net = round($price, 2);
    $tax = 0.0;
}

// بعد
$taxRate = (string) get_setting('tax_rate', 19);
$split   = $this->taxCalculator->extractTax((string) $price, $taxRate, 2);
$net     = $split['net'];
$tax     = $split['tax'];
$price   = $split['gross'];
```

---

### 6.4 `app/Services/InvoiceService.php`

```php
// قبل — float صريح
public function calculateReverseTax(float $totalWithTax, float $taxRate): array
{
    $subtotal = $totalWithTax / (1 + ($taxRate / 100));
    $taxAmount = $totalWithTax - $subtotal;

    return [
        'subtotal' => round($subtotal, 2),
        'tax_amount' => round($taxAmount, 2),
        'total' => round($totalWithTax, 2),
    ];
}

// بعد — غلاف رقيق (التوقيع محفوظ لأن مسارات Filament تستدعيه)
public function calculateReverseTax(float $totalWithTax, float $taxRate): array
{
    $result = app(TaxCalculatorService::class)
        ->extractTax((string) $totalWithTax, (string) $taxRate, 2);

    return [
        'subtotal'   => $result['net'],
        'tax_amount' => $result['tax'],
        'total'      => $result['gross'],
    ];
}
```

وكان فرع الحساب الأمامي التاريخي في `createInvoiceFromAppointment` قد وُحّد على
الحاسبة كما يلي (ثم حُذفت الدالة بالكامل لاحقاً في MON-05 لصالح الكاتب الموحد):

```php
// قبل
$subtotal = $amountPaid;
$taxAmount = $amountPaid * ($taxRate / 100);
$total = $amountPaid + $taxAmount;

// بعد
$forward = app(TaxCalculatorService::class)->addTax((string) $amountPaid, (string) $taxRate, 2);
$subtotal  = $forward['net'];
$taxAmount = $forward['tax'];
$total     = $forward['gross'];
```

---

### 6.5 `app/Services/InvoiceFinalizationService.php` — صف `payments`

```php
// قبل — bcscale عام + عودة بـ float
private function calculateReverseTax(float $totalWithTax, float $taxRate): array
{
    bcscale(6);                                  // ← حالة عامة تلوّث بقية الطلب

    $factor = bcadd('1', bcdiv((string) $taxRate, '100', 6), 6);
    $subtotal = bcdiv((string) $totalWithTax, $factor, 6);
    $taxAmount = bcsub((string) $totalWithTax, $subtotal, 6);

    return [
        'subtotal' => round((float) $subtotal, 2),      // bcmath → float → decimal
        'tax_amount' => round((float) $taxAmount, 2),
        'total' => $totalWithTax,
    ];
}

// بعد
private function calculateReverseTax(float $totalWithTax, float $taxRate): array
{
    $result = app(TaxCalculatorService::class)
        ->extractTax((string) $totalWithTax, (string) $taxRate, 2);

    return [
        'subtotal'   => $result['net'],
        'tax_amount' => $result['tax'],
        'total'      => $result['gross'],
    ];
}
```

---

### 6.6 `app/Filament/.../CreateAppointment.php`

```php
// قبل — float
$grossTotal = (float) $servicesCollection->sum('price');
$taxRate    = (float) get_setting('tax_rate', 0);

if ($taxRate > 0) {
    $netTotal  = $grossTotal / (1 + $taxRate / 100);
    $taxAmount = $grossTotal - $netTotal;
} else {
    $netTotal  = $grossTotal;
    $taxAmount = 0.0;
}

$data['subtotal']     = round($netTotal,   2);
$data['tax_amount']   = round($taxAmount,  2);
$data['total_amount'] = round($grossTotal, 2);

// بعد
$taxRate = (string) get_setting('tax_rate', 0);

$totals = app(TaxCalculatorService::class)->calculateBulk(
    array_map(
        fn ($item): array => ['price' => (string) ($item['price'] ?? '0'), 'tax_rate' => $taxRate],
        array_values($services)
    ),
    2
);

$data['subtotal']     = $totals['net'];
$data['tax_amount']   = $totals['tax'];
$data['total_amount'] = $totals['gross'];
```

> **فرق مهم:** النسخة القديمة تجمع كل الأسعار **ثم** تستخرج الضريبة مرة واحدة. الجديدة تستخرج الضريبة **لكل بند** ثم تجمع — وهذا ما تقتضيه الممارسة المحاسبية للفواتير (البند المطبوع يجب أن يساوي البند المحسوب)، وهو أيضاً ما يفعله مسار الـ API فصار المسارَان متطابقين.

---

### 6.7 `app/Filament/.../AppointmentsRelationManager.php` — نسبة مزروعة و`subtotal` منسيّ

```php
// قبل
$taxCalculation = $invoiceService->calculateReverseTax($amountPaid, 19);   // ← 19 مزروعة!

$record->update([
    'total_amount' => $amountPaid,
    'tax_amount' => $taxCalculation['tax_amount'],
    // subtotal غائب تماماً ⇒ net + tax != gross على الصف
    'duration_minutes' => $adjustedDuration,
    // ...
]);

// بعد
$taxCalculation = $invoiceService->calculateReverseTax(
    $amountPaid,
    (float) get_setting('tax_rate', 19)
);

$record->update([
    'total_amount' => $amountPaid,
    'subtotal' => $taxCalculation['subtotal'],
    'tax_amount' => $taxCalculation['tax_amount'],
    'duration_minutes' => $adjustedDuration,
    // ...
]);
```

عطلان في نداء واحد: تغيير `tax_rate` في إعدادات الصالون كان يترك **هذا المسار وحده** يحسب بـ 19%؛ و`subtotal` كان يبقى على قيمته قبل الدفع بينما يتحرك `total_amount` و`tax_amount` — فيصير الصف `net + tax ≠ gross`.

---

### 6.8 `app/Services/Appointments/AppointmentCreationService.php` — معادلة بالاتجاه الخطأ

```php
// قبل — يُضيف 19% فوق سعر يشملها أصلاً!
$subtotal = collect($services)->sum('price');       // gross
$taxRate = (float) get_setting('tax_rate', 0);
$taxAmount = $subtotal * ($taxRate / 100);          // 50.00 × 0.19 = 9.50

$data['subtotal'] = round($subtotal, 2);            // 50.00
$data['tax_amount'] = round($taxAmount, 2);         // 9.50
$data['total_amount'] = round($subtotal + $taxAmount, 2);  // 59.50 ← يُطالَب الزبون به!

// بعد — استخراج عكسي عبر الحاسبة
$taxRate = (string) get_setting('tax_rate', 0);

$totals = app(TaxCalculatorService::class)->calculateBulk(
    array_map(
        fn ($item): array => ['price' => (string) ($item['price'] ?? '0'), 'tax_rate' => $taxRate],
        array_values($services)
    ),
    2
);

$data['subtotal']     = $totals['net'];
$data['tax_amount']   = $totals['tax'];
$data['total_amount'] = $totals['gross'];
```

> **الصنف كله لا يُستدعى من أي مكان** (تحققت في `app/`, `tests/`, `routes/`, `config/`, `bootstrap/`, `database/`). أُصلح ولم يُحذف: حذف صنف كامل قرار أوسع من نطاق هذا الإصلاح، وتركُ معادلةٍ بالاتجاه الخطأ فيه فخٌّ لمن يوصّله لاحقاً.

---

### 6.9 `app/Services/BookingService2.php` — **حُذف**

428 سطراً، صفر مستدعين، ويحتوي نسخة ثالثة من الضريبة بالاتجاه الخطأ.

---

### 6.10 `app/Console/Commands/TaxDriftReport.php` — **جديد**

أمر **للقراءة فقط** يعدّ الصفوف المتأثرة. تفاصيله في [القسم 8](#8-البيانات-القديمة-و-gobd).

---

## 7. القرارات التصميمية

### 7.1 لماذا `TaxCalculatorService` هو المصدر الوحيد وليس `BookingService`؟

النسخة الصحيحة كانت في `BookingService`، فقد يبدو منطقياً اعتمادها. رُفض ذلك:

`BookingService` يخدم **الحجز**. أما الضريبة فتُحسب في الفاتورة والدفع والطباعة والتقارير — وكلها لا تعرف شيئاً عن الحجز ولا يجوز أن تعتمد عليه. المكان الطبيعي هو الخدمة المتخصصة، فأُصلحت هي وسُحبت إليها الحسابات.

### 7.2 لماذا تُعدَّل الضريبة دائماً في التسوية، لا «القيمة الأكبر»؟

سببان:

1. **الصافي أساس، والضريبة مشتقة.** سعر الخدمة يُبنى على الصافي، والضريبة تُحسب منه — فتعديل المشتق هو التصرف المحاسبي الصحيح.
2. **الحتمية.** السلوك القديم (`if (net >= tax)`) كان يعدّل الصافي أحياناً والضريبة أحياناً **حسب النسبة**: بنسبة 19% الصافي أكبر فيُعدَّل هو؛ وبنسبة 100% يتساويان فيُعدَّل الصافي أيضاً. نتيجةٌ لا يمكن التنبؤ بها ولا مطابقتها مع طبقة أخرى.

### 7.3 لماذا الدقة الداخلية `precision + 8` وليست 10 ثابتة؟

التقرير اقترح `INTERNAL_SCALE = 10` ثابتة. لكن الخدمة تقبل `$precision` حتى 12 — ودقة داخلية ثابتة عند 10 تعني حساب ناتجٍ بدقة 12 بدقة **أقل** من دقته، أي نفس العطل بشكل آخر. القاعدة «احسب أوسع مما تحتاج» يجب أن تصمد لأي دقة مطلوبة.

### 7.4 لماذا لم يُحوَّل `unit_price` ليخزّن الـ gross؟

كان خياراً مطروحاً وأنظف مفهومياً (يطابق قرار «الأسعار gross»)، لكنه يحتاج migration + backfill لكل الصفوف القائمة + تعديل قوالب الطباعة. والهدف المطلوب — ألّا يتآكل الـ gross — يتحقق كاملاً باحترام الـ gross المخزَّن. أُجِّل التحويل كقرار مستقل.

---

## 8. البيانات القديمة و GoBD

### القرار: لا تُصحَّح الفواتير المُنهاة بأثر رجعي

الفواتير التي أُنهيت قبل هذا الإصلاح تحمل ضريبة محسوبة بالتنفيذ المعطوب. **ولا يجوز تعديلها**، لأن مبدأ **Unveränderbarkeit** (عدم قابلية التعديل) في GoBD الألماني — §146 AO و§14 UStG — يمنع تعديل مستند محاسبي صدر للعميل.

السبب العملي: الفاتورة المطبوعة **بيد الزبون** تحمل الرقم القديم. تغييرُ الصف في قاعدة البيانات يجعل السجل يخالف المستند — وهذا **أسوأ من فرق سنت**، لأنه يحوّل خطأً حسابياً قابلاً للتفسير («كان لدينا عطل في التقريب، وهذه أرقامه») إلى تعارضٍ بين الدفاتر والمستندات لا يمكن تفسيره لمدقّق.

### الأداة: `php artisan tax:drift-report`

```bash
# فحص كل الفواتير
php artisan tax:drift-report

# الفواتير المُنهاة فقط
php artisan tax:drift-report --status=paid

# تقرير كامل إلى CSV لمحاسبك
php artisan tax:drift-report --status=paid --csv=storage/app/vat-drift.csv
```

المخرَج:

```
  MON-01 — تقرير انحراف ضريبة القيمة المضافة
  قراءة فقط: هذا الأمر لا يعدّل أي صف.

  +----+---------------+--------+----------+-------------------+---------+---------+------------+
  | id | رقم الفاتورة  | الحالة | الإجمالي | الضريبة المخزَّنة | الصحيحة | الانحراف | التاريخ   |
  +----+---------------+--------+----------+-------------------+---------+---------+------------+
  | 1  | INV-0001      | PAID   | 50.00    | 7.99              | 7.98    | 0.01    | 2026-09-01 |
  +----+---------------+--------+----------+-------------------+---------+---------+------------+

  فواتير مفحوصة .................... 128
  فواتير منحرفة .................... 97
  مجموع انحراف الضريبة ............. 0.97
  مجموع انحراف الصافي .............. -0.97

  لم يُعدَّل شيء.
```

> ⚠️ الأمر **لا ينفّذ `UPDATE` ولا `INSERT` ولا `DELETE`** — ومحروس بـ اختبار يؤكد أن الصفوف لا تتغير بعد تشغيله ([`tests/Feature/Money/DriftReportTest.php`](../../tests/Feature/Money/DriftReportTest.php)).

الغرض أن **تعرف الحجم** لا أن تُخفيه: كم فاتورة وما مجموع الانحراف، لتُدرجه في بيان تصحيحي إن لزم — بالتشاور مع محاسبك.

### ملاحظة: TSE موقوف حالياً

`FISKALY_ENABLED=false` — لا توقيع TSE على أي فاتورة حالياً، فلا صفوف موقّعة تعارض الأرقام الجديدة. هذا الإصلاح **لا يمسّ طبقة TSE إطلاقاً**، وعندما تُفعَّل ستوقّع على الأرقام الصحيحة.

---

## 9. الاختبارات

### 9.1 الجديد: `tests/Feature/Money/TaxParityTest.php` — 30 اختباراً

يفحص **الشيئين الغائبين** عن كل الاختبارات السابقة:

| المجموعة | ماذا تحرس |
|---|---|
| **الحاسبة تقرّب ولا تقتطع** | 8 أسعار كانت تنحرف، بالأرقام الصحيحة صراحةً |
| مرجع مستقل | 68 تركيبة سعر×نسبة مقابل `round()` من PHP — لا مقابل أي مساعد من المشروع |
| نِسَب كسرية | 19.5% تُحسب 19.5% لا 19% |
| مدخل عالي الدقة | `'33.333333'` لا يصل كـ `'33.33'` |
| `bcscale` | لا تبقى ملوَّثة بعد أي نداء |
| **تطابق الطبقات الأربع** | `appointments` = `invoices` = `invoice_items` = `payments` لكل سعر منحرف |
| مسار الخصم | خصم 45.00 من 50.00 يحفظ `net + tax = 45.00` |
| **لا تآكل للـ gross** | حفظ بند فاتورة مرتين لا يُنقص سنتاً |
| الاشتقاق الأمامي | بند بصافٍ فقط ما زال يحصل على gross |

**والأهم — الحرس له أسنان:** أعدتُ الكود القديم مؤقتاً (`git stash`) وشغّلت الاختبار الجديد:

```
مع الكود القديم:   21 failed, 9 passed
مع الكود المُصلَح:  30 passed (316 assertions)
```

اختبارٌ لا يفشل على العطل ليس حرساً — هذا يفشل.

### 9.2 السبعة الفاشلة أصلاً — كلها أُصلحت

| الاختبار | السبب | العلاج |
|---|---|---|
| `reverse calculation consistency` | العطل نفسه | ✅ صار ناجحاً بإصلاح الجذر |
| `cross validation with alternative calculations` | نسبة 13.5% كسرية → فرق 0.47 | ✅ صار ناجحاً بإصلاح الجذر |
| `bulk calculation with extreme scenarios` | `calculateBulk` ترمي استثناءً على صف سعره `'invalid'` بينما تعليق الاختبار يقول «سيتم رفضها» | ✅ أُصلحت الخدمة: تتجاهل الصفوف غير الصالحة |
| `extract tax with extreme values` | يطالب بـ **هوية مستحيلة**: `net == tax` و`net == gross/2` و`net + tax == gross` معاً — و`16.666666 + 16.666666 = 33.333332 ≠ 33.333333` | ✅ أُصلح الاختبار: يفحص الخاصية الحقيقية (المجموع == gross، والنصفان لا يفترقان أكثر من وحدة واحدة) |
| `precision edge cases` | يؤكّد `'86'` بينما **تعليقه نفسه يقول `→ 87`** — كُتب ليطابق الكود المعطوب | ✅ أُصلح إلى `'87'` + تسامح نسبي بالدقة بدل `0.1` مطلقة + قيمة مطلقة bcmath بدل `abs()` |
| `concurrent calculations` | مولّد الاختبار ينتج نِسَباً > 100 (`if` واحد يقسم على 2 لا يكفي لـ ~700) فترفضها الخدمة | ✅ `if` ← `while` |
| `precision consistency in serial vs bulk` | نفس عطل المولّد | ✅ نفس العلاج |

وواحد كان **ناجحاً وصار فاشلاً** ثم أُصلح: `twenty complex services` — يقارن جمعاً يقرّب كل بند بدقة **10** مع `calculateBulk` الذي يقرّب بدقة **8**، ويطالب بتساوٍ **تام** رغم أن تعليقه يقول «تسامح صغير جداً». كان «ينجح» سابقاً لأن الطرفين كانا مخطئين بشكل تصادم عند الخانة الثامنة. أُعطي تسامحاً حقيقياً مقدَّراً بوحدات الخانة الأخيرة.

> **قاعدة عامة استُخلصت:** الاختبارات التي كُتبت لتطابق كوداً معطوباً تصير حرّاساً للعطل. ثلاثة من هذه السبعة كانت كذلك حرفياً — وأحدها كان تعليقه يخالف تأكيده.

### 9.3 خط الأساس الكامل

```
قبل:  17 failed, 3 skipped, 420 passed
بعد:  10 failed, 3 skipped, 427 passed
```

الفرق **٧ بالضبط** = اختبارات الضريبة السبعة. والعشرة الباقية هي نفس الفئات المعروفة غير المرتبطة:

| الفئة | العدد | السبب |
|---|---|---|
| Fiskaly | 5 | TSE موقوف عمداً (`FISKALY_ENABLED=false`) |
| ProfileImageUpload | 3 | `ModelNotFoundException` — رفع الصور |
| DeleteAccount | 1 | سابق للإصلاح |
| ExampleTest (welcome) | 1 | سابق للإصلاح |

**صفر تراجعات.**

---

## 10. قواعد يجب عدم كسرها

### 🔴 1. تنفيذ واحد فقط لحساب الضريبة

`TaxCalculatorService` هو المصدر الوحيد. أي كود جديد يحتاج تحويلاً بين gross و net **يستدعيها**، ولا يكتب `bcdiv($gross, $factor, ...)` ولا `$gross / (1 + $rate/100)`. كان في المشروع **سبع** نسخ وأنتجت رقمين مختلفين لنفس المعاملة.

### 🔴 2. احسب أوسع مما تحتاج، وقرّب مرة واحدة في النهاية

`bcdiv` **تقتطع ولا تقرّب**. أي قسمة بدقة الناتج المطلوب تفقد المعلومة التي كان يجب تقريبها، وتقريبُها بعد ذلك بلا فائدة. الدقة الداخلية أوسع من المطلوبة بثماني خانات على الأقل.

### 🔴 3. لا `bcscale()` في كود المشروع

حالة عامة على مستوى الطلب كله: تغيّر بصمت سلوك كل عملية bcmath بعدها، في طبقات لا علاقة لها بالنداء. مرِّر الدقة صريحةً كوسيط ثالث لكل نداء bcmath.

### 🔴 4. الـ GROSS هو الحقيقة — ولا يُعاد بناؤه من الـ net أبداً

الاستخراج العكسي **دالة غير عكوسة**. `net → gross` يفقد سنتاً في معظم الأسعار، ولا دقة تُصلح ذلك:

```
45.00 ÷ 1.19 = 37.8151  →  net 37.82
37.82 × 1.19 = 45.0058  →  gross 45.01   ≠ 45.00
```

إن كان الـ gross مخزَّناً فهو المرجع، والضريبة تُستخرج منه.

### 🔴 5. نسبة الضريبة تأتي من الإعدادات، لا رقماً مزروعاً

`get_setting('tax_rate', 19)`. النداء الواحد الذي زُرعت فيه `19` كان يعني أن تغيير النسبة في اللوحة يترك مسار دفعٍ واحداً يحسب بـ 19% إلى الأبد.

### 🔴 6. الاختبار الذي لا يفشل على العطل ليس حرساً

اختبار على `119.00` لا يكشف اقتطاعاً (تقسم بالضبط). واختبار `net + tax == gross` يمرّ مع net خاطئ (`42.01 + 7.99 = 50.00`). كل اختبار ضريبة جديد يجب أن يفحص:
1. أن الرقم **صحيح رياضياً** مقابل مرجع مستقل — لا أن الطبقة متسقة مع نفسها؛
2. أن **طبقتين مختلفتين تتفقان**.

وتحقّق دائماً أن الاختبار الجديد **يفشل** على الكود قبل الإصلاح.

---

## الملفات المتأثرة

| الملف | التغيير |
|---|---|
| [`app/Services/TaxCalculatorService.php`](../../app/Services/TaxCalculatorService.php) | أُعيدت كتابته — الجذر |
| [`app/Models/InvoiceItem.php`](../../app/Models/InvoiceItem.php) | `calculateTotal()` — الـ gross مرجع |
| [`app/Services/BookingService.php`](../../app/Services/BookingService.php) | `calculateTotals()` موحَّدة · `bcRound()` و`calculateTotalsInverse()` محذوفتان · `addServiceDifferentProvider()` موحَّدة · الحاسبة في الـ constructor |
| [`app/Services/InvoiceService.php`](../../app/Services/InvoiceService.php) | `calculateReverseTax()` + الفرع الأمامي موحَّدان |
| [`app/Services/InvoiceFinalizationService.php`](../../app/Services/InvoiceFinalizationService.php) | `calculateReverseTax()` موحَّدة · `bcscale(6)` مُزالة |
| [`app/Filament/.../CreateAppointment.php`](../../app/Filament/Resources/Appointments/Pages/CreateAppointment.php) | `calculateTotalsFromServices()` موحَّدة |
| [`app/Filament/.../AppointmentsRelationManager.php`](../../app/Filament/Resources/Providers/RelationManagers/AppointmentsRelationManager.php) | `19` المزروعة ← إعدادات · `subtotal` أُضيف |
| [`app/Services/Appointments/AppointmentCreationService.php`](../../app/Services/Appointments/AppointmentCreationService.php) | معادلة بالاتجاه الخطأ ← استخراج عكسي |
| `app/Services/BookingService2.php` | **حُذف** (428 سطراً، صفر مستدعين) |
| [`app/Console/Commands/TaxDriftReport.php`](../../app/Console/Commands/TaxDriftReport.php) | **جديد** — تقرير قراءة فقط |
| [`tests/Feature/Money/TaxParityTest.php`](../../tests/Feature/Money/TaxParityTest.php) | **جديد** — 30 اختباراً |
| [`tests/Feature/Money/DriftReportTest.php`](../../tests/Feature/Money/DriftReportTest.php) | **جديد** — يؤكد أن التقرير لا يعدّل شيئاً |
| [`tests/Unit/TaxCalculatorService2Test.php`](../../tests/Unit/TaxCalculatorService2Test.php) | 4 اختبارات أُصلحت |
| [`tests/Unit/AdvancedTaxCalculatorTest.php`](../../tests/Unit/AdvancedTaxCalculatorTest.php) | 3 اختبارات أُصلحت |
| [`tests/Pest.php`](../../tests/Pest.php) | `Feature/Money` مضاف للنطاق |

</div>
