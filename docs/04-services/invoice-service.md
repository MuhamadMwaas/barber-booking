# InvoiceService & InvoiceFinalizationService

> **الملفات:** `app/Services/InvoiceService.php:1`، `app/Services/InvoiceFinalizationService.php:1`

---

## 1. InvoiceService — المسودات

### `createDtaftInvoiceFromAppointment(appointment, method, amount)` — `InvoiceService.php:40`

```php
public function createDtaftInvoiceFromAppointment(Appointment $appointment, string $method, float $amount): Invoice {
    $invoice = Invoice::create([
        'appointment_id' => $appointment->id,
        'customer_id' => $appointment->customer_id,
        'invoice_number' => null, // لا رقم للمسودة
        'status' => InvoiceStatus::DRAFT,
        'subtotal' => $appointment->subtotal,
        'tax_amount' => $appointment->tax_amount,
        'total_amount' => $appointment->total_amount,
        'tax_rate' => get_setting('tax_rate', '19'),
    ]);
    // InvoiceItems لكل service — net/tax عبر TaxCalculatorService::extractTax
    foreach ($appointment->services_record as $s) {
        $split = $this->taxCalculator->extractTax((string)$s->price, $taxRate, 2);
        InvoiceItem::create([... 'unit_price' => $split['net'], 'tax_amount' => $split['tax']]);
    }
    return $invoice;
}
```

- لا Payment، لا رقم، لا TSE.
- يُستدعى داخل transaction الحجز — `BookingService.php:166`.

### `rebuildAggregatedInvoice(invoiceOwner)` — `InvoiceService.php:80`

يعيد بناء الفاتورة المجمعة للأب + كل الأبناء.

```
1. احذف InvoiceItems القديمة
2. لكل Appointment في getCoveredAppointments() (الأب + الأبناء):
     لكل AppointmentService: أنشئ InvoiceItem
3. calculateTotals() من items
4. applyFinalAmount() إن وُجد خصم
```

### `applyFinalAmount(invoice, finalAmount)` — `InvoiceService.php:120`

إن `finalAmount < total_amount` → خصم لعميل خاص (ليس دفعًا جزئيًا) — يعيد حساب `net/tax/gross`.

---

## 2. InvoiceFinalizationService — العملية الوحيدة للدفع

> **كل واجهات الدفع تستدعي هذه العملية الوحيدة** — `Agent.md:470` (MON-05)

### التوقيع

```php
public function finalizeAppointmentPayment(
    Appointment $appointment,
    string|int $paymentMethod, // 'cash'|'card' أو PaymentMethod id
    ?float $finalAmount,       // null = كامل، أقل = سعر خاص
    ?string $notes,
    string $source,            // 'staff_dashboard' | 'filament' | ...
): array
```

### الخطوات داخل `DB::transaction` — `InvoiceFinalizationService.php:40`

```
1. resolve invoiceOwner + lock rows (SELECT FOR UPDATE على كل المواعيد المرتبطة)
2. validate PaymentMethod نشط (cash/card فقط)
3. lock/re-check DRAFT invoice (منع دفع مزدوج)
4. rebuildAggregatedInvoice(invoiceOwner)
5. applyFinalAmount(finalAmount) إن وُجد
6. assign sequential number: DocumentNumberGenerator::next('invoice') → INV-2026-000001
7. update invoice: status=PAID, invoice_number, invoice_data={finalized_at, payment_type, amount_paid, finalized_by, tse_enabled:false}
8. create Payment واحد (payment_method_id, amount, subtotal, tax_amount, status=PAID_ONSTIE_*)
9. mark كل المواعيد المشمولة: status=COMPLETED, payment_status=PAID_ONSTIE_*, payment_method=cash/card
10. لا Fiskaly call — tse_enabled=false
```

**الضمانات:**

- فاتورة واحدة لكل حجز — قيد فريد `appointment_id`.
- دفع واحد فقط — فحص `status=DRAFT` تحت القفل يمنع الثاني.
- رقم بلا فجوات — `DocumentNumberGenerator` داخل transaction.

---

*التالي: [`05-api/README.md`](../05-api/README.md)*
