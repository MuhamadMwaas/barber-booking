<?php

/**
 * بريد رمز التحقق (SendOtpMail). نص لكل غرض (OtpPurpose).
 * :company = اسم العلامة، :date/:time = موعد انتهاء صلاحية الرمز.
 */
return [
    'subject' => [
        'account_verification' => ':company – تأكيد عنوان البريد الإلكتروني',
        'password_reset'       => ':company – إعادة تعيين كلمة المرور',
    ],

    'greeting' => 'مرحبًا :name،',

    'intro' => [
        'account_verification' => 'أدخل رمز التحقق التالي لتأكيد عنوان بريدك الإلكتروني ومتابعة إنشاء حسابك.',
        'password_reset'       => 'أدخل رمز التحقق التالي لإعادة تعيين كلمة المرور الخاصة بك.',
    ],

    'validity'      => 'الرمز صالح حتى :date الساعة :time.',
    'not_requested' => 'إذا لم تطلب هذا الرمز، يمكنك تجاهل هذا البريد الإلكتروني.',
    'signoff'       => 'فريق :company',
];
