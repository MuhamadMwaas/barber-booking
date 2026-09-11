<?php

return [
    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'محاولات تسجيل دخول كثيرة. يرجى المحاولة مجدداً بعد :seconds ثانية.',

    // AUTH-01 — تُرجَع مع رمز 429 من كل محدِّد في config/rate_limits.php.
    // لا تذكر أي حدّ تم تجاوزه عمداً: كشف ذلك يؤكد للمهاجم أن الحساب موجود.
    'throttle_generic' => 'طلبات كثيرة جداً. يرجى المحاولة مجدداً بعد :seconds ثانية.',

    'account_disabled_title' => 'الحساب معطّل',
    'account_disabled_body' => 'تم تعطيل حسابك. يرجى التواصل مع الإدارة للمساعدة.',
    'account_disabled' => 'تم تعطيل حسابك. يرجى التواصل مع الإدارة للمساعدة.',

    'customer_not_allowed_title' => 'وصول مرفوض',
    'customer_not_allowed_body' => 'حسابات العملاء لا يمكنها الوصول إلى لوحة التحكم.',

    // Returned (HTTP 409) when registering with an email that already exists.
    'email_exists_title' => 'الحساب موجود بالفعل',
    'email_exists_body' => 'تم إنشاء حساب باستخدام هذا البريد الإلكتروني مسبقًا. سجّل الدخول للمتابعة.',
];
