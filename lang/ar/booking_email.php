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

    // تذكير الموعد عبر البريد (تصميم خاص، نص معتمد من الصالون)
    'reminder' => [
        'subject'      => ':company – تذكير بموعدك',
        'greeting'     => 'مرحبًا :name،',
        'intro'        => 'نود تذكيرك بأن لديك موعدًا لدى :company.',
        'details_lead' => 'فيما يلي تفاصيل موعدك:',
        'date'         => 'التاريخ',
        'time'         => 'الوقت',
        'provider'     => 'الحلاق',
        'service'      => 'الخدمة',
        'salon'        => 'الصالون',
        'date_format'  => 'l، j F Y',
        'time_value'   => 'الساعة :time',
        'closing'      => 'نتطلع إلى زيارتك!',
        'signoff'      => 'فريق :company',
        'tagline'      => "أكثر من مجرد قصة شعر.\nمظهرك. هدفنا.",
    ],

    // Footer
    'thanks'   => 'شكراً لاختيارك :company! نتطلع إلى زيارتك.',
    'footer'   => 'هذه رسالة بريد إلكتروني مرسلة تلقائياً. يرجى عدم الرد مباشرةً على هذه الرسالة.',
];
