# دورة حياة الفاتورة — Invoice Lifecycle

> **الملفات:** `app/Services/InvoiceService.php:1`، `app/Services/InvoiceFinalizationService.php:1`، `app/Models/Invoice.php:1`، `docs/fixes/MON-03_document_numbering.md:1`، `docs/fixes/MON-05_unified_payment_flow.md:1`

---

## 1. المرحلتان

```
الحجز (BookingService)                    الدفع (InvoiceFinalizationService)
─────────────────────                     ───────────────────────────────
Invoice DRAFT                             Invoice PAID
  invoice_number = NULL                     invoice_number = INV-2026-000001
  status = DRAFT (0)                       status = PAID (2)
  لا Payment                               Payment واحد (cash/card)
  Appointment PENDING                      Appointment COMPLETED
  لا تُعتبر مستندًا                        مستند قانوني — تُطبع
```

## 2. `createDtaftInvoiceFromAppointment` — `InvoiceService.php:40`

داخل transaction الحجز — `BookingService.php:166`. ينسخ `subtotal/tax/total` من `Appointment` وينشئ `InvoiceItem` لكل خدمة عبر `TaxCalculatorService::extractTax`.

## 3. `rebuildAggregatedInvoice(invoiceOwner)` — `InvoiceService.php:80`

للحجوزات المرتبطة (parent+children) — يجمع كل الخدمات في فاتورة واحدة على الأب:

```
1. احذف items القديمة
2. لكل appointment في getCoveredAppointments() (الأب + الأبناء):
     لكل appointment_service: أنشئ InvoiceItem
3. calculateTotals() من items (TaxCalculatorService)
4. applyFinalAmount(finalAmount) إن خصم
```

## 4. `finalizeAppointmentPayment` — `InvoiceFinalizationService.php:40` (العملية الوحيدة للدفع)

```php
DB::transaction → lock invoice owner + كل الأبناء
  → validate PaymentMethod (cash/card نشط)
  → lock DRAFT invoice (منع دفع مزدوج)
  → rebuildAggregatedInvoice
  → applyFinalAmount (إن سعر خاص)
  → DocumentNumberGenerator::next('invoice') → INV-2026-000001
  → Invoice: status=PAID, invoice_number, invoice_data={finalized_at, payment_type, amount_paid, finalized_by, tse_enabled:false}
  → Payment: payment_method_id, amount, status=PAID_ONSTIE_*, type=full, paymentable=Invoice
  → كل appointments: status=COMPLETED, payment_status=PAID_ONSTIE_*
  → لا Fiskaly call
```

**الضمانات:**

- فاتورة واحدة لكل `appointment_id` — قيد فريد.
- دفع واحد — فحص DRAFT تحت القفل.
- رقم بلا فجوات — `DocumentNumberGenerator` داخل transaction.

---

*التالي: [`template-system.md`](template-system.md)*
