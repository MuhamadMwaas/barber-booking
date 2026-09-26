<?php

// رسائل InvoiceFinalizationService — مسار الدفع الوحيد داخل الصالون،
// المشترك بين StaffDashboard وشاشات الدفع في Filament.
return [
    'errors' => [
        'method_not_found' => 'طريقة الدفع غير موجودة أو غير مفعلة.',
        'method_not_on_site' => 'طريقة الدفع يجب أن تكون نقداً أو بطاقة داخل الصالون.',
        'no_appointments' => 'لا توجد مواعيد مرتبطة بهذه الفاتورة.',
        'cancelled_or_no_show' => 'لا يمكن تحصيل فاتورة تحتوي على موعد ملغي أو لم يحضر صاحبه.',
        'invoice_not_positive' => 'لا يمكن تحصيل فاتورة بلا مبلغ موجب.',
        'amount_not_positive' => 'يجب أن يكون مبلغ الدفع أكبر من صفر.',
        'duration_not_positive' => 'مدة الموعد المعدلة يجب أن تكون أكبر من صفر.',
        'appointment_not_in_invoice' => 'الموعد المحدد ليس ضمن الفاتورة الموحدة.',
    ],
];
