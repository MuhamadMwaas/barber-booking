<?php

return [
    // Appointment Creation Validation Messages
    'appointment' => [
        'at_least_one_service' => 'يجب اختيار خدمة واحدة على الأقل',
        'select_customer' => 'يجب اختيار العميل',
        'select_provider' => 'يجب اختيار مقدم الخدمة',
        'select_date' => 'يجب اختيار تاريخ الموعد',
        'select_start_time' => 'يجب اختيار وقت البداية',
        'duration_greater_than_zero' => 'المدة الإجمالية يجب أن تكون أكبر من صفر',
        'service_not_found' => 'الخدمة غير موجودة',

        // Success Messages
        'created_successfully' => 'تم إنشاء الحجز بنجاح',
        'booking_number' => 'رقم الحجز',

        // Error Titles
        'validation_error' => 'خطأ في التحقق',
        'creation_error' => 'خطأ في إنشاء الحجز',
    ],

    // General Validation Messages
    'validation' => [
        'required' => 'هذا الحقل مطلوب',
        'invalid_data' => 'البيانات المدخلة غير صحيحة',
    ],
];
