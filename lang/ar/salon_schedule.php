<?php

return [
    // Navigation & Page Titles
    'page_title' => 'إدارة مواعيد الصالون',
    'page_heading' => 'إدارة ساعات عمل الصالون',
    'page_subheading' => 'تحديد أوقات الفتح والإغلاق لكل فرع خلال الأسبوع',
    'navigation_label' => 'مواعيد الصالون',
    'navigation_group' => 'إدارة الصالون',

    // Branch Selection
    'select_branch' => 'اختر الفرع',
    'select_branch_description' => 'اختر فرعاً لإدارة ساعات عمله',
    'choose_branch' => 'اختر فرعاً...',
    'branch' => 'الفرع',
    'branch_info' => 'معلومات الفرع',
    'managing_schedule' => 'إدارة جدول العمل الأسبوعي للفرع المحدد',

    // Weekly Schedule
    'weekly_schedule' => 'الجدول الأسبوعي',
    'weekly_schedule_description' => 'تحديد أوقات الفتح والإغلاق لكل يوم من أيام الأسبوع',
    'manage_opening_hours' => 'إدارة أوقات الفتح والإغلاق لكل يوم',

    // Days of the Week
    'days' => [
        'sunday' => 'الأحد',
        'monday' => 'الإثنين',
        'tuesday' => 'الثلاثاء',
        'wednesday' => 'الأربعاء',
        'thursday' => 'الخميس',
        'friday' => 'الجمعة',
        'saturday' => 'السبت',
    ],

    // Time Fields
    'is_open' => 'مفتوح',
    'open_time' => 'وقت الفتح',
    'close_time' => 'وقت الإغلاق',
    'working_hours' => 'ساعات العمل',
    'open' => 'مفتوح',
    'closed' => 'مغلق',

    // Summary
    'summary' => 'ملخص الأسبوع',
    'summary_description' => 'نظرة عامة على ساعات العمل الأسبوعية',
    'total_weekly_hours' => 'إجمالي ساعات الأسبوع',
    'open_days_count' => 'عدد أيام العمل',
    'average_daily_hours' => 'متوسط ساعات اليوم',
    'days_unit' => 'أيام',

    // Actions
    'save_schedule' => 'حفظ الجدول',
    'schedule_saved' => 'تم حفظ الجدول',
    'schedule_saved_successfully' => 'تم حفظ جدول الصالون بنجاح',
    'save_error' => 'خطأ في حفظ الجدول',
    'please_select_branch' => 'الرجاء اختيار فرع أولاً',

    // Validation
    'validation' => [
        'close_time_must_differ' => 'يجب أن يكون وقت الإغلاق مختلفاً عن وقت الفتح',
    ],
    'open_time_required' => 'وقت الفتح مطلوب لـ :day',
    'close_time_required' => 'وقت الإغلاق مطلوب لـ :day',
];
