# TaxCalculatorService — الحساب الوحيد للضريبة

> **الملف:** `app/Services/TaxCalculatorService.php:1` — **التنفيذ الوحيد** لحساب الضريبة في المشروع كله

---

## 1. لماذا service واحد فقط؟

قبل `MON-01` كان هناك **3 تنفيذات** لنفس المعادلة:

| المكان | الدقة الداخلية | النتيجة لـ 50/1.19 |
|--------|---------------|-------------------|
| `BookingService::calculateTotals()` | 6 | 42.02 → tax 7.98 ✅ |
| `TaxCalculatorService::extractTax()` | 2 | 42.01 → tax 7.99 ❌ |
| `BookingService2::calculateTotals()` | float | خطأ مماثل |

نفس المعاملة (50 يورو) تظهر بضريبتين مختلفتين في `appointments` و `invoices` — العميل يرى رقمين.

**الحل:** كل الطبقات تفوّض لـ `TaxCalculatorService` وحده — `BookingService.php:310`، `InvoiceService.php:80`، `Invoice.php:90`.

---

## 2. `extractTax(gross, rate, precision)` — حساب عكسي

```php
// TaxCalculatorService.php:25
public function extractTax(string $grossAmount, string $taxRate, int $precision = 2): array {
    // gross شامل الضريبة، rate مثل "19" أو "19.5"
    // يرجع ['net' => string, 'tax' => string, 'gross' => string]
    // يضمن: net + tax = gross
}
```

### الخوارزمية

```
internalPrecision = max(10, precision + 8)  // دائمًا أوسع من الناتج

factor = 1 + (rate / 100)                  // بدقة internalPrecision
  مثال: rate=19 → factor = 1.19 (بدقة 10)

net = gross / factor                        // بدقة internalPrecision
  مثال: 50.00 / 1.19 = 42.0168067226...

tax = gross - net                           // بدقة internalPrecision

تقريب net و tax إلى precision (2) ← مرة واحدة هنا فقط

تسوية: diff = gross - (net + tax)
  إن diff != 0.00 → tax += diff  // الفرق دائمًا على الضريبة، لا الصافي
```

### لماذا `max(10, precision+8)`؟

`bcdiv` في PHP **تقتطع لا تقرّب**. القسمة بدقة الناتج المطلوب تفقد الخانة التي كان يجب تقريبها:

```
50.00 / 1.19 = 42.016806...

bcdiv('50.00', '1.19', 2)  → "42.01"  ← اقتطاع → tax 7.99 (خطأ)
bcdiv('50.00', '1.19', 10) → "42.0168067226" → تقريب → 42.02 → tax 7.98 (صحيح)

و rate=19.5: bcdiv('19.5','100',2) → "0.19" — يحسب 19.5% كـ 19%!
```

هذا كان `MON-01` — `docs/fixes/MON-01_vat_calculation_unified.md:1`.

---

## 3. `addTax(net, rate, precision)` — حساب أمامي

```php
public function addTax(string $netAmount, string $taxRate, int $precision = 2): array
```

```
tax = net * (rate/100)   // بدقة internalPrecision
gross = net + tax
تقريب tax و gross إلى precision
```

> ⚠️ **ليس عكس `extractTax`**: `extractTax(addTax(net)) ≠ net` بسبب التقريب في الاتجاهين — `Agent.md:497`.

---

## 4. `calculateBulk(items, precision)` — دفعي

```php
public function calculateBulk(array $items, int $precision = 2): array {
    // items: [['price' => "50.00", 'tax_rate' => "19"], ...]
    // يرجع ['net' => "63.03", 'tax' => "11.97", 'gross' => "75.00"]
}
```

```
لكل item:
  split = extractTax(price, tax_rate, precision)  // تقريب كل بند على حدة (ممارسة الفوترة)
جمع: netTotal = Σ net, taxTotal = Σ tax, grossTotal = Σ gross
تسوية واحدة نهائية: diff = grossTotal - (netTotal + taxTotal)
  إن diff != 0 → taxTotal += diff
```

- يتجاهل البنود بلا `price` رقمي — `TaxCalculatorService.php:80`.
- التقريب لكل بند ثم التسوية = ممارسة الفوترة الصحيحة (كل بند يُحسب كسطر فاتورة).

---

## 5. مثال كامل — `tax_rate = 19%`

| الخدمة | GROSS | NET | TAX |
|--------|-------|-----|-----|
| قص شعر | 50.00 | 42.02 | 7.98 |
| لحية | 25.00 | 21.01 | 3.99 |
| **المجموع** | **75.00** | **63.03** | **11.97** |

```
50/1.19 = 42.0168 → 42.02, tax 7.98
25/1.19 = 21.0084 → 21.01, tax 3.99
63.03 + 11.97 = 75.00 ✅
```

---

## 6. القاعدة الذهبية

> **لا تستدع `bcscale()` أبدًا** — `Agent.md:509`. هي حالة global تغير دقة كل `bcmath` اللاحقة في نفس الطلب. مرّر الدقة صراحة كمعامل ثالث في كل استدعاء.

```php
// خطأ
bcscale(2);
$net = bcdiv($gross, $factor); // يستخدم 2 ضمنيًا — خطر!

// صحيح
$net = bcdiv($gross, $factor, 10); // دقة صريحة
```

---

*التالي: [`invoice-service.md`](invoice-service.md)*
