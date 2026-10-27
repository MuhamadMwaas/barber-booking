<?php

return [
    'new_appointment' => 'موعد جديد',
    'new_appointment_message' => 'تم إنشاء حجز جديد لخدمة :service بتاريخ :date في تمام الساعة :time. طريقة الدفع: :payment_type.',

    // إشعار لما يتم تأجيل حجز العميل بسبب إضافة خدمة لحجز سابق
    'booking_pushed_title' => 'تم تأجيل موعدك',
    'booking_pushed_body' => 'تم تأجيل حجزك رقم #:number إلى الساعة :time (تم تأخيره :minutes دقيقة).',
    // يُرسل عند إلغاء العميل للمرة الثانية فأكثر خلال أسبوع.
    'repeat_cancellation_title' => 'إلغاءات متكررة: :customer',
    'repeat_cancellation_body' => 'ألغى :customer عدد :count حجوزات خلال آخر :days أيام. آخرها: #:number.',
    'repeat_cancellation_view_customer' => 'عرض العميل',
    // يُرسل عند إلغاء حجز لأن العامل في إجازة ولم يتمكن أحد من استلامه.
    'booking_cancelled_provider_unavailable_title' => 'تم إلغاء حجزك',
    'booking_cancelled_provider_unavailable_body' => 'تم إلغاء حجزك رقم #:number بتاريخ :date الساعة :time لأن العامل أصبح غير متوفر. يرجى حجز موعد جديد.',
];
