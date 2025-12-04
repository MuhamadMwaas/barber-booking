<?php

namespace App\Services;

use App\Enum\InvoiceStatus;
use App\Enum\PaymentStatus;
use App\Models\Appointment;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * InvoiceService - خدمة احترافية لإدارة الفواتير
 *
 * هذه الخدمة مصممة لتكون قابلة للتوسع مستقبلاً لدعم:
 * - TSE (Technical Security Environment) للتوقيع الرقمي
 * - الربط مع نظام الضرائب الألماني (ELSTER/DATEV)
 * - معايير الفوترة الإلكترونية الأوروبية
 */
class InvoiceService
{
    /**
     * إنشاء فاتورة من حجز مع جميع البنود والضرائب
     *
     * @param Appointment $appointment الحجز المراد إنشاء فاتورة له
     * @param string $paymentType نوع الدفع (PAID_ONSTIE_CASH, PAID_ONSTIE_CARD, PAID_ONLINE)
     * @param float $amountPaid المبلغ المدفوع (قد يكون أقل من الإجمالي في حالة الخصم)
     * @param string|null $notes ملاحظات إضافية
     * @param int|null $adjustedDuration المدة المعدلة بالدقائق (اختياري)
     * @param bool $amountIncludesTax هل المبلغ يتضمن الضريبة (افتراضياً true)
     * @return Invoice الفاتورة المنشأة
     * @throws \Exception في حالة حدوث خطأ أثناء الإنشاء
     */
    public function createInvoiceFromAppointment(
        Appointment $appointment,
        string $paymentType,
        float $amountPaid,
        ?string $notes = null,
        ?int $adjustedDuration = null,
        bool $amountIncludesTax = true
    ): Invoice {
        try {
            DB::beginTransaction();

            // الحصول على معدل الضريبة من الإعدادات
            $taxRate = SettingsService::get('tax_rate', 19);

            // حساب الضريبة العكسية إذا كان المبلغ يتضمن الضريبة
            if ($amountIncludesTax) {
                $taxCalculation = $this->calculateReverseTax($amountPaid, $taxRate);
                $subtotal = $taxCalculation['subtotal'];
                $taxAmount = $taxCalculation['tax_amount'];
                $total = $taxCalculation['total'];
            } else {
                // حساب عادي (المبلغ لا يتضمن الضريبة)
                $subtotal = $amountPaid;
                $taxAmount = $amountPaid * ($taxRate / 100);
                $total = $amountPaid + $taxAmount;
            }

            // تحديث مدة الحجز إذا تم تعديلها
            if ($adjustedDuration !== null && $adjustedDuration !== $appointment->duration_minutes) {
                $this->updateAppointmentDuration($appointment, $adjustedDuration);
            }

            // إنشاء الفاتورة الرئيسية
            $invoice = $this->createInvoice(
                $appointment,
                $taxRate,
                $paymentType,
                $total,
                $notes,
                $subtotal,
                $taxAmount
            );

            // إنشاء بنود الفاتورة من الخدمات
            $this->createInvoiceItems($invoice, $appointment, $taxRate);

            // تحديث حالة الدفع للحجز
            $this->updateAppointmentPaymentStatus($appointment, $paymentType);

            // TODO: في المستقبل - إضافة التوقيع الرقمي TSE هنا
            // $this->signInvoiceWithTSE($invoice);

            // TODO: في المستقبل - إرسال الفاتورة لنظام الضرائب الألماني
            // $this->submitToGermanTaxAuthority($invoice);

            DB::commit();

            // تسجيل العملية الناجحة
            Log::info('Invoice created successfully', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'appointment_id' => $appointment->id,
                'amount_paid' => $amountPaid,
                'payment_type' => $paymentType,
            ]);

            return $invoice;

        } catch (\Exception $e) {
            DB::rollBack();

            // تسجيل الخطأ
            Log::error('Failed to create invoice', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * إنشاء الفاتورة الرئيسية
     */
    private function createInvoice(
        Appointment $appointment,
        float $taxRate,
        string $paymentType,
        float $totalAmount,
        ?string $notes,
        float $subtotal,
        float $taxAmount
    ): Invoice {
        $invoiceData = [
            'payment_method' => PaymentStatus::from($paymentType)->label(),
            'amount_paid' => $totalAmount,
            'paid_at' => now()->toDateTimeString(),
            'paid_by' => auth()->user()?->full_name ?? 'System',
            'payment_type' => $paymentType,
        ];

        // إضافة معلومات الخصم إذا كان المبلغ المدفوع أقل من الإجمالي الأصلي
        if ($totalAmount < $appointment->total_amount) {
            $discount = $appointment->total_amount - $totalAmount;
            $discountPercentage = ($discount / $appointment->total_amount) * 100;

            $invoiceData['discount_amount'] = round($discount, 2);
            $invoiceData['discount_percentage'] = round($discountPercentage, 2);
            $invoiceData['original_amount'] = $appointment->total_amount;
        }

        return Invoice::create([
            'appointment_id' => $appointment->id,
            'customer_id' => $appointment->customer_id,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'tax_rate' => $taxRate,
            'total_amount' => $totalAmount,
            'status' => InvoiceStatus::PAID,
            'notes' => $notes,
            'invoice_data' => $invoiceData,
        ]);
    }

    /**
     * إنشاء بنود الفاتورة من خدمات الحجز
     */
    private function createInvoiceItems(Invoice $invoice, Appointment $appointment, float $taxRate): void
    {
        foreach ($appointment->services as $service) {
            $unitPrice = $service->pivot->price;
            $taxAmount = $unitPrice * ($taxRate / 100);
            $totalAmount = $unitPrice + $taxAmount;

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => $service->pivot->service_name,
                'quantity' => 1,
                'unit_price' => $unitPrice,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'itemable_id' => $service->id,
                'itemable_type' => get_class($service),
            ]);
        }
    }

    /**
     * تحديث مدة الحجز
     *
     * @param Appointment $appointment
     * @param int $newDurationMinutes المدة الجديدة بالدقائق
     * @return void
     */
    private function updateAppointmentDuration(Appointment $appointment, int $newDurationMinutes): void
    {
        // حساب وقت النهاية الجديد بناءً على المدة المعدلة
        $newEndTime = $appointment->start_time->copy()->addMinutes($newDurationMinutes);

        $appointment->update([
            'duration_minutes' => $newDurationMinutes,
            'end_time' => $newEndTime,
        ]);

        Log::info('Appointment duration updated', [
            'appointment_id' => $appointment->id,
            'old_duration' => $appointment->getOriginal('duration_minutes'),
            'new_duration' => $newDurationMinutes,
            'new_end_time' => $newEndTime->toDateTimeString(),
        ]);
    }

    /**
     * تحديث حالة الدفع للحجز
     */
    private function updateAppointmentPaymentStatus(Appointment $appointment, string $paymentType): void
    {
        $appointment->update([
            'payment_status' => PaymentStatus::from($paymentType),
            'payment_method' => PaymentStatus::from($paymentType)->label(),
        ]);
    }

    /**
     * حساب الضريبة العكسية (المبلغ يتضمن الضريبة)
     *
     * @param float $totalWithTax المبلغ الإجمالي الذي يتضمن الضريبة
     * @param float $taxRate معدل الضريبة (مثلاً 19 للضريبة 19%)
     * @return array ['subtotal' => المبلغ قبل الضريبة, 'tax_amount' => قيمة الضريبة, 'total' => المبلغ الإجمالي]
     */
    public function calculateReverseTax(float $totalWithTax, float $taxRate): array
    {
        // الصيغة: المبلغ قبل الضريبة = المبلغ الإجمالي / (1 + معدل الضريبة/100)
        $subtotal = $totalWithTax / (1 + ($taxRate / 100));
        $taxAmount = $totalWithTax - $subtotal;

        return [
            'subtotal' => round($subtotal, 2),
            'tax_amount' => round($taxAmount, 2),
            'total' => round($totalWithTax, 2),
        ];
    }

    /**
     * التوقيع الرقمي للفاتورة باستخدام TSE
     *
     * سيتم تنفيذ هذه الوظيفة مستقبلاً للامتثال للمعايير الألمانية
     *
     * @param Invoice $invoice
     * @return void
     */
    private function signInvoiceWithTSE(Invoice $invoice): void
    {
        // TODO: تنفيذ التوقيع الرقمي TSE
        // سيتم إضافة:
        // - الاتصال بخادم TSE Cloud
        // - الحصول على التوقيع الرقمي
        // - حفظ معلومات التوقيع في invoice_data
        // - رقم المعاملة الفريد (Transaction Number)
        // - الطابع الزمني المعتمد (Certified Timestamp)
        // - البيانات المشفرة (Signature Data)
    }

    /**
     * إرسال الفاتورة إلى نظام الضرائب الألماني
     *
     * سيتم تنفيذ هذه الوظيفة مستقبلاً للامتثال الضريبي
     *
     * @param Invoice $invoice
     * @return void
     */
    private function submitToGermanTaxAuthority(Invoice $invoice): void
    {
        // TODO: تنفيذ الربط مع نظام الضرائب الألماني
        // سيتم إضافة:
        // - الاتصال بـ ELSTER API أو DATEV
        // - تحويل الفاتورة إلى تنسيق XRechnung أو ZUGFeRD
        // - إرسال البيانات الضريبية
        // - حفظ رقم التأكيد من السلطات الضريبية
    }

    /**
     * التحقق من صحة الفاتورة قبل الإنشاء
     *
     * @param Appointment $appointment
     * @return bool
     * @throws \Exception
     */
    public function validateInvoiceCreation(Appointment $appointment): bool
    {
        // التحقق من عدم وجود فاتورة سابقة
        if ($appointment->invoice()->where('status', InvoiceStatus::PAID)->exists()) {
            throw new \Exception('هذا الحجز لديه فاتورة مدفوعة مسبقاً');
        }

        // التحقق من وجود خدمات
        if ($appointment->services->isEmpty()) {
            throw new \Exception('لا يمكن إنشاء فاتورة لحجز بدون خدمات');
        }

        // التحقق من حالة الحجز
        if ($appointment->status->value === 'admin_cancelled' || $appointment->status->value === 'user_cancelled') {
            throw new \Exception('لا يمكن إنشاء فاتورة لحجز ملغي');
        }

        return true;
    }

    /**
     * الحصول على معلومات الفاتورة بتنسيق مناسب للعرض
     *
     * @param Invoice $invoice
     * @return array
     */
    public function getInvoiceDetails(Invoice $invoice): array
    {
        return [
            'invoice_number' => $invoice->invoice_number,
            'customer' => $invoice->customer->full_name,
            'appointment' => $invoice->appointment->number,
            'subtotal' => $invoice->subtotal,
            'tax_rate' => $invoice->tax_rate,
            'tax_amount' => $invoice->tax_amount,
            'total_amount' => $invoice->total_amount,
            'status' => $invoice->status->label(),
            'items' => $invoice->items->map(fn($item) => [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'tax_amount' => $item->tax_amount,
                'total_amount' => $item->total_amount,
            ]),
            'payment_info' => $invoice->invoice_data,
            'created_at' => $invoice->created_at,
        ];
    }
}
