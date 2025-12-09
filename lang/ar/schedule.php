<?php

/**
 * Arabic translations for Schedule Management
 *
 * ملف الترجمة العربية لنظام إدارة الشفتات
 */

return [
    // Page titles
    'page_title' => 'إدارة جداول الموظفين',
    'page_heading' => 'إدارة جداول دوام مقدمي الخدمة',
    'page_subheading' => 'إعداد الشفتات الأسبوعية وساعات العمل لمقدمي الخدمة',
    'managing_schedule_for' => 'إدارة جدول :name',
    'navigation_label' => 'جداول الموظفين',
    'navigation_group' => 'إدارة الموظفين',

    // Provider selection
    'select_provider' => 'اختر الموظف',
    'choose_provider' => '-- اختر موظفاً --',
    'select_provider_prompt' => 'اختر موظفاً لإدارة جدوله',
    'select_provider_desc' => 'اختر مقدم خدمة من القائمة المنسدلة أعلاه لعرض وإدارة جدوله الأسبوعي.',

    // Days of week
    'sunday' => 'الأحد',
    'monday' => 'الاثنين',
    'tuesday' => 'الثلاثاء',
    'wednesday' => 'الأربعاء',
    'thursday' => 'الخميس',
    'friday' => 'الجمعة',
    'saturday' => 'السبت',

    // Shift management
    'shift' => 'شفت',
    'shifts' => 'شفتات',
    'add_shift' => 'إضافة شفت',
    'remove_shift' => 'إزالة',
    'no_shifts' => 'لا توجد شفتات',
    'start' => 'البداية',
    'end' => 'النهاية',
    'break_minutes' => 'الاستراحة (دقيقة)',
    'hours' => 'ساعات',
    'total_hours' => 'إجمالي ساعات الأسبوع',
    'total_shifts' => 'إجمالي الشفتات',

    // Actions
    'save_schedule' => 'حفظ الجدول',
    'cancel' => 'إلغاء',
    'reset' => 'إعادة تعيين',
    'clear_all' => 'مسح الكل',
    'copy' => 'نسخ',
    'paste' => 'لصق',
    'copy_day' => 'نسخ اليوم',
    'paste_day' => 'لصق اليوم',
    'copy_week' => 'نسخ الأسبوع',
    'paste_week' => 'لصق الأسبوع',
    'copy_from_user' => 'نسخ من آخر',
    'bulk_paste' => 'لصق لعدة موظفين',
    'clear_day' => 'مسح اليوم',
    'apply_to_all_days' => 'تطبيق على جميع الأيام',
    'apply_to_selected' => 'تطبيق على المحدد',
    'mark_as_off' => 'تحديد كيوم إجازة',
    'mark_as_work' => 'تحديد كيوم عمل',

    // Branch schedule
    'branch' => 'الفرع',
    'branch_hours' => 'ساعات الفرع',

    // Legend
    'legend' => 'دليل التوضيح',
    'shift_block' => 'شفت العمل',
    'day_off_indicator' => 'يوم إجازة',
    'view_timeline' => 'عرض الجدول الزمني',

    // Bulk paste modal
    'bulk_paste_title' => 'لصق الجدول لعدة موظفين',
    'bulk_paste_desc' => 'اختر الموظفين الذين تريد تطبيق الجدول المنسوخ عليهم:',
    'selected_count' => 'تم اختيار :count',

    // Status messages
    'unsaved_changes' => 'لديك تغييرات غير محفوظة',
    'clipboard_has_day' => 'جدول اليوم في الحافظة',
    'clipboard_has_week' => 'جدول الأسبوع في الحافظة',

    // Success messages
    'messages' => [
        'saved_successfully' => 'تم حفظ :count شفت بنجاح!',
        'reset_successfully' => 'تم إعادة تعيين الجدول للحالة الأخيرة المحفوظة',
        'all_cleared' => 'تم مسح جميع الشفتات',
        'day_cleared' => 'تم مسح شفتات :day',
        'day_copied' => 'تم نسخ جدول :day',
        'day_pasted' => 'تم لصق الجدول إلى :day',
        'week_copied' => 'تم نسخ جدول الأسبوع',
        'week_pasted' => 'تم لصق جدول الأسبوع بنجاح',
        'copied_from_user' => 'تم نسخ الجدول من :name',
        'day_applied_to_week' => 'تم تطبيق جدول :day على جميع الأيام',
        'bulk_paste_success' => 'تم تطبيق الجدول على :count موظف/موظفين',
    ],

    // Error messages
    'errors' => [
        'select_provider' => 'الرجاء اختيار موظف',
        'select_provider_first' => 'الرجاء اختيار موظف أولاً',
        'start_time_required' => 'وقت البداية مطلوب',
        'end_time_required' => 'وقت النهاية مطلوب',
        'invalid_time_format' => 'صيغة الوقت غير صحيحة',
        'nothing_to_paste' => 'لا يوجد شيء للصق. انسخ يوماً أو أسبوعاً أولاً.',
        'copy_week_first' => 'الرجاء نسخ جدول أسبوع أولاً',
        'select_users_to_paste' => 'الرجاء اختيار موظف واحد على الأقل',
        'shift_missing_start' => ':day الشفت #:shift: وقت البداية مفقود',
        'shift_missing_end' => ':day الشفت #:shift: وقت النهاية مفقود',
        'shift_invalid_time_range' => ':day الشفت #:shift: وقت البداية (:start) يجب أن يكون قبل وقت النهاية (:end)',
        'shifts_overlap' => ':day: الشفت #:shift1 (:time1) يتداخل مع الشفت #:shift2 (:time2)',
        'save_failed' => 'فشل حفظ الجدول',
        'bulk_paste_failed' => 'فشل تطبيق الجدول على الموظفين المحددين',
    ],
    'errors_found' => 'الرجاء إصلاح الأخطاء التالية:',

    // Confirmations
    'confirm_reset_title' => 'إعادة تعيين الجدول؟',
    'confirm_reset_message' => 'سيؤدي هذا إلى تجاهل جميع التغييرات غير المحفوظة وإعادة تحميل آخر جدول محفوظ.',
    'confirm_reset' => 'إعادة تعيين',
    'confirm_clear_title' => 'مسح جميع الشفتات؟',
    'confirm_clear_message' => 'سيؤدي هذا إلى إزالة جميع الشفتات من جميع الأيام. يمكنك الإلغاء قبل الحفظ.',
    'confirm_clear' => 'مسح الكل',
    'confirm_remove_shift' => 'هل أنت متأكد من إزالة هذا الشفت؟',

    // Info section
    'info_title' => 'إدارة الجداول',
    'info_description' => 'إدارة ساعات العمل والشفتات لمقدمي الخدمة',
    'feature_shifts_title' => 'شفتات متعددة',
    'feature_shifts_desc' => 'إضافة شفتات متعددة في اليوم الواحد مع توقيت مرن',
    'feature_copy_title' => 'نسخ ولصق',
    'feature_copy_desc' => 'نسخ الجداول بين الأيام أو الموظفين',
    'feature_validation_title' => 'تحقق ذكي',
    'feature_validation_desc' => 'كشف التداخل التلقائي يمنع التعارضات',

    // Help section
    'help_title' => 'المساعدة والنصائح',
    'help_how_to_use' => 'كيفية الاستخدام',
    'help_step_1' => 'اختر موظفاً من القائمة المنسدلة',
    'help_step_2' => 'أضف شفتات لكل يوم بالنقر على "إضافة شفت"',
    'help_step_3' => 'حدد أوقات البداية والنهاية لكل شفت',
    'help_step_4' => 'اختيارياً أضف وقت الاستراحة بالدقائق',
    'help_step_5' => 'انقر على "حفظ الجدول" لحفظ تغييراتك',

    'help_tips_title' => 'نصائح',
    'help_tip_1' => 'استخدم "تطبيق على جميع الأيام" لنسخ جدول يوم بسرعة إلى كامل الأسبوع',
    'help_tip_2' => 'سيكتشف النظام تلقائياً الشفتات المتداخلة',
    'help_tip_3' => 'يمكنك نسخ الجداول بين موظفين مختلفين',
    'help_tip_4' => 'حدد الشفت كـ "يوم إجازة" إذا كان الموظف لا يعمل في ذلك الشفت',

    'help_shortcuts_title' => 'إجراءات سريعة',
    'help_shortcut_copy_day' => 'نسخ شفتات من يوم واحد',
    'help_shortcut_apply_all' => 'تطبيق اليوم على كامل الأسبوع',
    'help_shortcut_bulk' => 'التطبيق على عدة موظفين دفعة واحدة',
];
