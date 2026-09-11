# الفاتورة والدفع — Invoice & Payment

> **الملفات:** `app/Models/Invoice.php:1` (282 سطر)، `app/Models/InvoiceItem.php:1`، `app/Models/Payment.php:1`، `app/Services/DocumentNumberGenerator.php:1`

---

## 1. Invoice — `invoices` table

| العمود | النوع | الوصف |
|--------|-------|-------|
| `id` | bigint PK | المعرف |
| `appointment_id` | FK → appointments unique | الحجز (واحد لواحد) |
| `customer_id` | FK → users nullable | العميل |
| `invoice_number` | string unique nullable | `INV-2026-000001` — null للمسودة |
| `subtotal` | decimal(10,2) | الصافي |
| `tax_amount` | decimal(10,2) | الضريبة |
| `tax_rate` | decimal(5,2) | نسبة الضريبة (19.00) |
| `total_amount` | decimal(10,2) | الإجمالي GROSS |
| `discount_amount` | decimal(10,2) | مبلغ الخصم (إن وجد) |
| `status` | int (Enum) | `InvoiceStatus` — DRAFT(0) عند الحجز |
| `notes` | text nullable | ملاحظات |
| `invoice_data` | json nullable | بيانات الدفع + TSE (مستقبلًا) |
| `segnture` | text nullable | توقيع TSE (مستقبلًا) |
| `signature_missing_reason` | text nullable | لماذا لا توقيع |
| `print_count` | integer | عدد مرات الطباعة |
| `first_printed_at` | datetime nullable | أول طباعة |
| `last_printed_at` | datetime nullable | آخر طباعة |

### العلاقات

```php
appointment() → BelongsTo(Appointment)
customer()    → BelongsTo(User)
items()       → HasMany(InvoiceItem)
payments()    → MorphMany(Payment)
printLogs()   → HasMany(PrintLog)
```

### الدوال المفتاحية

| الدالة | الملف:السطر | ماذا تفعل |
|--------|-------------|-----------|
| `generateInvoiceNumber()` | `Invoice.php:90` | يستدعي `DocumentNumberGenerator::next('invoice')` داخل transaction |
| `calculateTotals()` | `Invoice.php:80` | يعيد حساب `subtotal/tax/total` من `items` عبر `TaxCalculatorService` |
| `getTemplateOrDefault()` | `Invoice.php:120` | يرجع `InvoiceTemplate` الافتراضي للطباعة |
| `getCopyLabel()` | `Invoice.php:140` | `""` أول طباعة، `"(COPY)"` ثانية، `"(COPY 2)"` ثالثة |
| `incrementPrintCount()` | `Invoice.php:150` | يزيد العداد ويحدّث `first/last_printed_at` |
| `getCoveredAppointments()` | `Invoice.php:180` | يرجع الأب + الأبناء (للفاتورة المجمعة) |
| `isAggregated()` | `Invoice.php:200` | هل الفاتورة مجمعة (لها أبناء)؟ |

---

## 2. InvoiceItem — `invoice_items` table

| العمود | الوصف |
|--------|-------|
| `invoice_id` | FK → invoices |
| `description` | اسم الخدمة (snapshot) |
| `quantity` | الكمية (عادة 1) |
| `unit_price` | سعر الوحدة NET |
| `tax_rate` | نسبة الضريبة لهذا البند |
| `tax_amount` | ضريبة البند |
| `total_amount` | إجمالي البند GROSS |
| `itemable_id/type` | polymorphic → Service |

```php
// InvoiceItem.php:30 — Boot
static::saving(function ($item) {
    $item->total_amount = $item->quantity * ($item->unit_price + $item->tax_amount / $item->quantity);
});
static::saved(function ($item) {
    $item->invoice->calculateTotals(); // إعادة حساب الفاتورة
});
```

---

## 3. Payment — `payments` table (Polymorphic)

| العمود | الوصف |
|--------|-------|
| `id` | PK |
| `payment_method_id` | FK → payment_methods |
| `payment_number` | `PAY-YYYYMMDD-XXXXXX` unique |
| `amount` | المبلغ GROSS |
| `subtotal` | الصافي |
| `tax_amount` | الضريبة |
| `status` | `PaymentStatus` |
| `type` | `full` / `partial` / `deposit` / `refund` |
| `paymentable_id/type` | morph → Invoice أو Appointment |
| `payment_metadata` | json — بيانات البوابة، الاسترداد |

- **حاليًا:** دفعة واحدة فقط `type=full` عند `InvoiceFinalizationService` — لا دفع جزئي.
- **Polymorphic:** `Payment::paymentable()` قد يكون `Invoice` أو `Appointment`.

---

## 4. DocumentNumberGenerator — الترقيم المتسلسل

```php
// app/Services/DocumentNumberGenerator.php:1
public function next(string $type): string {
    if (!DB::transactionLevel()) {
        throw new LogicException('Must be called inside a transaction');
    }
    $counter = DocumentCounter::where('type', $type)->lockForUpdate()->first();
    $counter->increment('counter');
    return sprintf('%s-%s-%06d', $prefix, date('Y'), $counter->counter);
    // INV-2026-000001, PAY-2026-000042
}
```

| النوع | البادئة | الجدول |
|-------|---------|--------|
| `invoice` | `INV-` | `document_counters` type=invoice |
| `payment` | `PAY-` | `document_counters` type=payment |
| `appointment` | `APT-` | لا — `BookingService::generateAppointmentNumber()` عشوائي + حلقة `exists()` |

> **القاعدة:** `DocumentNumberGenerator` يجب أن يُستدعى داخل `DB::transaction` — وإلا يرمي `LogicException`. هذا يضمن عدم وجود فجوات.

---

## 5. PaymentMethod — `payment_methods` table

| id | name | code |
|----|------|------|
| 1 | Cash | `cash` |
| 2 | Card | `card` |
| 3 | Online | `online` (غير مستخدم) |

`database/seeders/PaymentMethodSeeder.php:1` — فقط `cash` و `card` نشطان ويُقبلان في `InvoiceFinalizationService`.

---

*التالي: [`scheduling-models.md`](scheduling-models.md)*
