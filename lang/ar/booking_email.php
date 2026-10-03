<?php

return [
    // Subjects
    'subject_customer' => 'تأكيد الحجز — :number',
    'subject_company'  => 'حجز جديد — :number',

    // Intros
    'greeting'       => 'مرحباً :name،',
    'confirm_intro'  => 'شكراً لحجزك لدى :company. تم استلام حجزك بنجاح.',
    'company_intro'  => 'تم إنشاء حجز أونلاين جديد للتو. التفاصيل أدناه:',

    // Booking fields
    'booking_number' => 'رقم الحجز',
    'date'           => 'التاريخ',
    'time'           => 'الوقت',
    'duration'       => 'المدة',
    'provider'       => 'مقدم الخدمة',
    'payment_method' => 'طريقة الدفع',
    'source'         => 'مصدر الحجز',
    'notes'          => 'الملاحظات',

    // Services table
    'services' => 'الخدمات',
    'service'  => 'الخدمة',
    'price'    => 'السعر',
    'subtotal' => 'المجموع قبل الضريبة',
    'tax'      => 'الضريبة',
    'total'    => 'السعر الإجمالي',

    // Customer block (company email)
    'customer' => 'بيانات الزبون',
    'name'     => 'الاسم',
    'email'    => 'البريد الإلكتروني',
    'phone'    => 'الهاتف',

    // حجز مقسوم (حجز واحد، عدة مواعيد)
    'appointments_heading' => 'مواعيدك (:count)',
    'appointments_intro'   => 'خدماتك في أوقات مختلفة، لذلك حُجزت كمواعيد منفصلة. يمكنك إلغاء كل موعد على حدة.',
    'appointment_n'        => 'الموعد :n',
    'group_total'          => 'المجموع لكل المواعيد',

    // عرض الزبون (التأكيد + التذكير)
    'details_heading'   => 'تفاصيل حجزك',
    'services_customer' => 'الخدمات التي اخترتها',
    'payment_summary'   => 'ملخص الدفع',
    'payment_methods'   => [
        'cash'   => 'نقداً',
        'online' => 'أونلاين',
    ],

    // تذكير الموعد عبر البريد
    'reminder_subject' => 'تذكير بالموعد — :number',
    'reminder_intro'   => 'نودّ تذكيرك بموعدك القادم لدى :company.',
    'reminder_thanks'  => 'نتطلع إلى زيارتك لدى :company!',

    // Footer
    'thanks'   => 'شكراً لاختيارك :company! نتطلع إلى زيارتك.',
    'footer'   => 'هذه رسالة بريد إلكتروني مرسلة تلقائياً. يرجى عدم الرد مباشرةً على هذه الرسالة.',
];
