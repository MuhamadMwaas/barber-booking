<?php

return [
    'user' => [
        'label' => 'مستخدم',
        'plural_label' => 'المستخدمين',
        'navigation_label' => 'المستخدمين',
        'title' => 'إدارة المستخدمين',


        // Table columns
        'avatar' => 'الصورة',
        'full_name' => 'الاسم الكامل',
        'role' => 'الدور',
        'phone' => 'رقم الهاتف',
        'phone_copied' => 'تم نسخ رقم الهاتف!',
        'branch' => 'الفرع',
        'no_branch' => 'لا يوجد فرع',
        'city' => 'المدينة',
        'status' => 'الحالة',
        // 'language' => 'اللغة',  // REMOVED: Duplicate key - conflicts with 'language' section
        'email_verified' => 'البريد محقق',
        'appointments' => 'الحجوزات',
        'completed_services' => 'الخدمات المكتملة',
        'joined_at' => 'تاريخ الانضمام',
        'last_updated' => 'آخر تحديث',

        // Filters
        'filter_by_role' => 'تصفية حسب الدور',
        'filter_by_branch' => 'تصفية حسب الفرع',
        'filter_by_language' => 'تصفية حسب اللغة',
        'all' => 'الكل',
        'all_users' => 'كل المستخدمين',
        'active_only' => 'النشطون فقط',
        'inactive_only' => 'غير النشطين فقط',
        'email_verification' => 'التحقق من البريد',
        'verified_only' => 'المحققون فقط',
        'unverified_only' => 'غير المحققين فقط',

        // Languages
        'arabic' => 'العربية',
        'english' => 'الإنجليزية',
        'german' => 'الألمانية',

        // Empty state
        'no_users_yet' => 'لا يوجد مستخدمون بعد',
        'create_first_user' => 'قم بإنشاء أول مستخدم للبدء.',

        // Form sections
        'personal_information' => 'المعلومات الشخصية',
        'contact_information' => 'معلومات التواصل',
        'account_settings' => 'إعدادات الحساب',
        'additional_information' => 'معلومات إضافية',

        // Form fields
        'profile_image' => 'الصورة الشخصية',
        'first_name' => 'الاسم الأول',
        'last_name' => 'الاسم الأخير',
        'email' => 'البريد الإلكتروني',
        'password' => 'كلمة المرور',
        'password_confirmation' => 'تأكيد كلمة المرور',
        'address' => 'العنوان',
        'locale' => 'اللغة',
        'is_active' => 'نشط',
        'notes' => 'ملاحظات',
        'select_role' => 'اختر الدور',
        'select_branch' => 'اختر الفرع',
        'select_language' => 'اختر اللغة',

        // Roles
        'admin' => 'مدير',
        'customer' => 'عميل',
        'manager' => 'مسؤول',
        'provider' => 'مزود خدمة',

        // Helpers
        'profile_image_helper' => 'صورة بصيغة JPG أو PNG، بحد أقصى 2 ميجابايت',
        'password_helper' => 'يجب أن تحتوي كلمة المرور على 8 أحرف على الأقل',
        'role_helper' => 'حدد دور المستخدم في النظام',
        'branch_helper' => 'اختياري - فقط للمدراء ومزودي الخدمة',
        'locale_helper' => 'لغة واجهة المستخدم المفضلة',
        'status_helper' => 'المستخدمون غير النشطين لا يمكنهم تسجيل الدخول',

        // Infolist additional labels
        'services_offered' => 'الخدمات المقدمة',
        'service_name' => 'اسم الخدمة',
        'duration' => 'المدة',
        'minutes' => 'دقيقة',
        'price' => 'السعر',
        'total_appointments' => 'إجمالي الحجوزات',
        'average_rating' => 'متوسط التقييم',
        'no_reviews_yet' => 'لا توجد تقييمات بعد',
        'total_invoices' => 'إجمالي الفواتير',
        'total_spent' => 'إجمالي المصروفات',
        'provider_information' => 'معلومات مزود الخدمة',
        'customer_information' => 'معلومات العميل',
        'no_notes_available' => 'لا توجد ملاحظات متاحة',
        'not_provided' => 'غير متوفر',
        'not_verified' => 'غير محقق',
        'not_connected' => 'غير متصل',
        'verified_at' => 'تم التحقق في',
        'view_profile' => 'عرض الملف الشخصي',
        'google_connected' => 'متصل بـ Google',

        // Customer Statistics
        'customer_stats' => 'إحصائيات العميل',
        'booking_stats' => 'إحصائيات الحجوزات',
        'services_requested' => 'الخدمات المطلوبة',
        'total_paid' => 'إجمالي المدفوعات',
        'pending_appointments' => 'الحجوزات المعلقة',
        'completed_appointments' => 'الحجوزات المكتملة',
        'cancelled_appointments' => 'الحجوزات الملغاة',
        'upcoming_appointments' => 'الحجوزات القادمة',
        'payment_history' => 'سجل المدفوعات',
        'favorite_services' => 'الخدمات المفضلة',
        'booking_frequency' => 'تكرار الحجز',
        'last_booking' => 'آخر حجز',
        'first_booking' => 'أول حجز',
        'average_booking_value' => 'متوسط قيمة الحجز',
        'lifetime_value' => 'القيمة الإجمالية',

        // Provider Statistics
        'provider_stats' => 'إحصائيات مزود الخدمة',
        'earnings_overview' => 'نظرة عامة على الأرباح',
        'total_earnings' => 'إجمالي الأرباح',
        'monthly_earnings' => 'الأرباح الشهرية',
        'last_month_earnings' => 'أرباح الشهر الماضي',
        'current_month_earnings' => 'أرباح هذا الشهر',
        'services_count' => 'عدد الخدمات المقدمة',
        'services_list' => 'قائمة الخدمات',
        'completed_bookings' => 'الحجوزات المكتملة',
        'total_reviews' => 'إجمالي التقييمات',
        'work_schedule' => 'جدول العمل',
        'day' => 'اليوم',
        'working_hours' => 'ساعات العمل',
        'break_time' => 'وقت الاستراحة',
        'day_off' => 'يوم إجازة',
        'sunday' => 'الأحد',
        'monday' => 'الاثنين',
        'tuesday' => 'الثلاثاء',
        'wednesday' => 'الأربعاء',
        'thursday' => 'الخميس',
        'friday' => 'الجمعة',
        'saturday' => 'السبت',
        'no_schedule' => 'لا يوجد جدول محدد',
        'performance_metrics' => 'مؤشرات الأداء',
        'customer_satisfaction' => 'رضا العملاء',
        'repeat_customers' => 'العملاء المتكررون',
        'no_services' => 'لا توجد خدمات محددة',
        'earnings_period' => 'فترة الأرباح',
        'all_time' => 'كل الأوقات',
        'this_month' => 'هذا الشهر',
        'last_month' => 'الشهر الماضي',
        'last_3_months' => 'آخر 3 أشهر',
        'last_6_months' => 'آخر 6 أشهر',
        'this_year' => 'هذا العام',
        'sar_currency' => 'ريال',

        // Services Statistics
        'customer_services_statistics' => 'إحصائيات خدمات العميل',
        'provider_services' => 'خدمات المزود',
        'total_bookings' => 'إجمالي الحجوزات',
        'pending_bookings' => 'الحجوزات المعلقة',
        'total_spent_on_service' => 'إجمالي المصروف',
        'average_service_price' => 'متوسط السعر',
        'last_booking_date' => 'آخر حجز',
        'never' => 'لم يحجز بعد',
        'no_services_booked' => 'لم يتم حجز أي خدمات بعد',
        'no_services_booked_desc' => 'هذا العميل لم يحجز أي خدمات حتى الآن',
        'no_services_assigned_desc' => 'لا توجد خدمات محددة لهذا المزود',
        'active' => 'نشط',
        'inactive' => 'غير نشط',
        'revenue_from_customer' => 'الدخل المحقق',
        'revenue_from_service' => 'الدخل من الخدمة',
        'from_completed_only' => 'من الحجوزات المكتملة فقط',
        'no_appointments' => 'لا توجد حجوزات بعد',
        'no_appointments_desc' => 'لم يقم هذا العميل بأي حجوزات حتى الآن',
    ],

    'appointment' => [
        'label' => 'حجز',
        'plural_label' => 'الحجوزات',

        // Table columns
        'booking_number' => 'رقم الحجز',
        'number_copied' => 'تم نسخ رقم الحجز!',
        'customer' => 'العميل',
        'customer_name' => 'اسم العميل',
        'provider' => 'مزود الخدمة',
        'provider_name' => 'اسم المزود',
        'services' => 'الخدمات',
        'no_services' => 'لا توجد خدمات',
        'date' => 'التاريخ',
        'time' => 'الوقت',
        'duration' => 'المدة',
        'total_price' => 'السعر الإجمالي',
        'includes_tax' => 'شامل الضريبة',
        'status' => 'حالة الحجز',
        'payment_status' => 'حالة الدفع',
        'created_at' => 'تاريخ الإنشاء',
        'updated_at' => 'آخر تحديث',

        // Status values
        'pending' => 'قيد الانتظار',
        'completed' => 'مكتمل',
        'user_cancelled' => 'ملغي من العميل',
        'admin_cancelled' => 'ملغي من الإدارة',

        // Payment status values
        'payment_pending' => 'في انتظار الدفع',
        'paid_online' => 'مدفوع أونلاين',
        'paid_cash' => 'مدفوع نقداً',
        'paid_card' => 'مدفوع ببطاقة',
        'payment_failed' => 'فشل الدفع',
        'refunded' => 'مسترد',
        'partially_refunded' => 'مسترد جزئياً',

        // Filters
        'filter_status' => 'تصفية حسب حالة الحجز',
        'filter_payment' => 'تصفية حسب حالة الدفع',
        'filter_provider' => 'تصفية حسب المزود',
        'filter_service' => 'تصفية حسب الخدمة',
        'filter_time' => 'تصفية حسب الوقت',
        'filter_today' => 'تصفية حجوزات اليوم',
        'today_only' => 'اليوم فقط',
        'not_today' => 'ليس اليوم',
        'from_date' => 'من تاريخ',
        'to_date' => 'إلى تاريخ',
        'upcoming' => 'القادمة',
        'past' => 'السابقة',

        // Empty state
        'no_appointments' => 'لا توجد حجوزات',
        'no_appointments_desc' => 'لا توجد أي حجوزات في النظام بعد',

        // Actions
        'mark_as_paid' => 'تسجيل الدفع',
        'payment_method' => 'طريقة الدفع',
        'amount_paid' => 'المبلغ المدفوع',
        'amount_paid_helper' => 'أدخل المبلغ الإجمالي شامل الضريبة (19%)',
        'includes_tax_suffix' => '(شامل الضريبة)',
        'start_time' => 'وقت البداية',
        'end_time' => 'وقت النهاية',
        'end_time_helper' => 'اختر وقت النهاية الفعلي للخدمة (سيتم حساب المدة تلقائياً)',
        'breakdown' => 'التفصيل',
        'subtotal' => 'المبلغ قبل الضريبة',
        'tax' => 'الضريبة',
        'payment_notes' => 'ملاحظات الدفع',
        'payment_success' => 'تم تسجيل الدفع بنجاح',
        'payment_error' => 'خطأ في تسجيل الدفع',
        'invoice_created' => 'تم إنشاء الفاتورة',
        'cancel_by_admin' => 'إلغاء الحجز',
        'cancel_confirmation' => 'تأكيد الإلغاء',
        'cancel_description' => 'هل أنت متأكد من إلغاء هذا الحجز؟ لا يمكن التراجع عن هذا الإجراء.',
        'cancellation_reason' => 'سبب الإلغاء',
        'cancelled_successfully' => 'تم إلغاء الحجز بنجاح',

        // Other
        'number' => 'رقم الحجز',
        'total' => 'المبلغ الإجمالي',
        'minutes' => 'دقيقة',
        'booked_at' => 'تاريخ الحجز',
        'ajuste_duration' => 'تعديل المدة',
        'duration_updated' => 'تم تحديث المدة بنجاح',
        'duration_updated_message' => 'تم تعديل مدة الحجز ووقت النهاية بنجاح',

        // Form - Wizard Steps
        'wizard_customer_label' => 'العميل',
        'wizard_customer_desc' => 'اختر أو أضف عميل جديد',
        'wizard_services_label' => 'الخدمات',
        'wizard_services_desc' => 'اختر الخدمات والأوقات',
        'wizard_payment_label' => 'الدفع والتفاصيل',
        'wizard_payment_desc' => 'تفاصيل الدفع والملاحظات',

        // Form - Customer Section
        'customer_label' => 'العميل',
        'add_new_customer' => 'إضافة عميل جديد',
        'add_customer' => 'إضافة',
        'select_customer' => 'اختر العميل',

        // Form - Basic Info Section
        'basic_info' => 'معلومات الحجز الأساسية',
        'appointment_date' => 'تاريخ الموعد',
        'appointment_date_label' => 'تاريخ الموعد',
        'start_time_label' => 'وقت البداية',
        'end_time_label' => 'وقت النهاية',
        'main_provider' => 'مقدم الخدمة الرئيسي',

        // Form - Timeline
        'select_service_first' => 'اختر الخدمات لعرض الأوقات المتاحة',
        'select_service_then_time' => 'اختر الخدمة ثم حدد التاريخ والوقت',
        'date_selection' => 'اختيار التاريخ',
        'select_date_to_view_timeline' => 'اختر تاريخاً لعرض الجدول الزمني لمقدمي الخدمة',
        'providers_availability' => 'الجدول الزمني لتوفر مقدمي الخدمة',
        'providers_timeline' => 'الجدول الزمني للمقدمين',
        'service_duration' => 'إجمالي مدة الخدمة',
        'total_duration' => 'المدة الإجمالية',
        'no_providers_available' => 'لا يوجد مقدمو خدمة متاحون في هذا التاريخ',
        'select_service_and_date' => 'الرجاء اختيار خدمة واحدة على الأقل وتاريخ',
        'available' => 'متاح',
        'selected' => 'محدد',
        'booked' => 'محجوز/غير متاح',
        'selected_slot' => 'تفاصيل الموعد',
        'select_appointment_details' => 'اختر مقدم الخدمة والوقت للموعد',
        'click_timeline_to_select' => 'يمكنك أيضاً النقر على وقت متاح في الجدول أعلاه',
        'click_timeline' => 'انقر على الجدول لاختيار الوقت',
        'click_slot_to_book' => 'انقر على أي وقت متاح لحجز موعد',
        'existing_appointments' => 'المواعيد الموجودة',
        'wizard_schedule_label' => 'الجدولة',
        'wizard_schedule_desc' => 'اختر التاريخ والوقت',
        'duration_helper' => 'يمكنك تخصيص مدة هذه الخدمة',
        'edit_total_duration_helper' => 'يمكنك تعديل المدة الإجمالية يدوياً - سيتم تحديث الجدول الزمني تلقائياً',
        'auto_calculated' => 'محسوب تلقائياً',

        // Form - Time Slots (Old - keeping for compatibility)
        'select_date_provider_service_first' => 'يرجى اختيار التاريخ ومقدم الخدمة وإضافة خدمة واحدة على الأقل أولاً',
        'select_time_slot' => 'اختر الوقت المتاح',
        'time_slots_help' => 'الأوقات المتاحة بناءً على جدول مقدم الخدمة',
        'provider_not_found' => 'مقدم الخدمة غير موجود',
        'provider_not_working_this_day' => 'مقدم الخدمة لا يعمل في هذا اليوم',
        'provider_has_day_off' => 'مقدم الخدمة لديه إجازة في هذا التاريخ',
        'no_available_slots' => 'لا توجد أوقات متاحة للتاريخ والمدة المحددة',

        // Form - Services Section
        'services_section' => 'الخدمات',
        'services_section_desc' => 'أضف الخدمات المطلوبة للحجز',
        'service_label' => 'الخدمة',
        'duration_label' => 'المدة (دقيقة)',
        'duration_suffix' => 'دقيقة',
        'price_label' => 'السعر',
        'add_service' => 'إضافة خدمة',
        'new_service' => 'خدمة جديدة',

        // Form - Payment Section
        'cost_summary' => 'ملخص التكاليف',
        'subtotal_label' => 'المجموع الفرعي',
        'tax_label' => 'الضريبة',
        'total_label' => 'الإجمالي',
        'total_duration_label' => 'المدة الإجمالية',
        'hours' => 'ساعة',
        'minute' => 'دقيقة',

        // Form - Payment Details
        'payment_details' => 'تفاصيل الدفع',
        'payment_method_label' => 'طريقة الدفع',
        'payment_method_cash' => 'نقدي',
        'payment_method_card' => 'بطاقة',
        'payment_method_online' => 'أونلاين',
        'payment_status_label' => 'حالة الدفع',
        'appointment_status_label' => 'حالة الموعد',
        'confirmed' => 'تم التأكيد',

        // Form - Additional Info
        'additional_info_section' => 'معلومات إضافية',
        'notes_label' => 'ملاحظات',
        'notes_placeholder' => 'أضف أي ملاحظات أو تعليمات خاصة بالموعد...',

        // Form - Submit
        'create_appointment' => 'إنشاء الحجز',
        'save_appointment' => 'حفظ الحجز',

    ],

    'service' => [
        'label' => 'خدمة',
        'plural_label' => 'الخدمات',

        // Table columns
        'image' => 'الصورة',
        'name' => 'اسم الخدمة',
        'color' => 'رمز اللون',
        'providers' => 'مزودو الخدمة',
        'price' => 'السعر',
        'discount' => 'سعر الخصم',
        'sar' => 'ريال',
        'duration' => 'المدة',
        'total_bookings' => 'إجمالي الحجوزات',
        'completed_bookings' => 'مكتملة',
        'total_revenue' => 'إجمالي الإيرادات',
        'completed_revenue' => 'الإيرادات المحققة',
        'from_completed_only' => 'من الحجوزات المكتملة فقط',
        'average_price' => 'متوسط السعر',
        'featured' => 'مميزة',
        'status' => 'الحالة',
        'created_at' => 'تاريخ الإنشاء',
        'updated_at' => 'تاريخ التحديث',

        // Form tabs
        'basic_info' => 'المعلومات الأساسية',
        'settings' => 'الإعدادات',

        // Form sections
        'service_details' => 'تفاصيل الخدمة',
        'service_details_desc' => 'أدخل المعلومات الأساسية عن الخدمة',
        'pricing_duration' => 'السعر والمدة',
        'pricing_duration_desc' => 'حدد السعر والمدة الزمنية لهذه الخدمة',
        'visual_branding' => 'المظهر والعلامة التجارية',
        'visual_branding_desc' => 'ارفع صورة وحدد الألوان لهذه الخدمة',
        'assign_providers' => 'تعيين مزودي الخدمة',
        'assign_providers_desc' => 'اختر المزودين القادرين على تقديم هذه الخدمة',
        'visibility_settings' => 'الظهور والعرض',
        'visibility_settings_desc' => 'تحكم في كيفية ومكان ظهور هذه الخدمة',

        // Form fields
        'category' => 'فئة الخدمة',
        'category_name' => 'اسم الفئة',
        'category_description' => 'وصف الفئة',
        'name_placeholder' => 'مثال: قص شعر وتصفيف',
        'name_helper' => 'أدخل اسماً واضحاً ووصفياً للخدمة',
        'description' => 'الوصف',
        'description_placeholder' => 'اوصف ما تتضمنه هذه الخدمة...',
        'description_helper' => 'قدم معلومات تفصيلية عن الخدمة',
        'price_helper' => 'السعر الأساسي لهذه الخدمة',
        'discount_helper' => 'سعر الخصم الاختياري (يجب أن يكون أقل من السعر الأساسي)',
        'minutes' => 'دقيقة',
        'duration_helper' => 'الوقت المقدر لإتمام هذه الخدمة',
        'image_helper' => 'ارفع صورة عالية الجودة (JPG أو PNG، الحد الأقصى 2 ميجابايت)',
        'color_helper' => 'اختر لوناً يمثل هذه الخدمة',
        'icon' => 'أيقونة الخدمة',
        'icon_helper' => 'ارفع صورة أيقونة (PNG أو SVG أو JPG، الحد الأقصى 1 ميجابايت، يُفضل نسبة 1:1)',
        'active' => 'نشط',
        'active_helper' => 'الخدمات غير النشطة لن تظهر في نظام الحجز',
        'featured_helper' => 'الخدمات المميزة تظهر بشكل بارز للعملاء',
        'sort_order' => 'ترتيب العرض',
        'sort_order_helper' => 'الأرقام الأقل تظهر أولاً',

        // Provider fields
        'provider' => 'المزود',
        'custom_price' => 'سعر مخصص',
        'custom_price_helper' => 'اتركه فارغاً لاستخدام السعر الافتراضي للخدمة',
        'use_default_price' => 'استخدم السعر الافتراضي',
        'custom_duration' => 'مدة مخصصة',
        'custom_duration_helper' => 'اتركه فارغاً لاستخدام المدة الافتراضية للخدمة',
        'use_default_duration' => 'استخدم المدة الافتراضية',
        'notes' => 'ملاحظات',
        'provider_notes_placeholder' => 'ملاحظات خاصة لهذا المزود...',
        'add_provider' => 'إضافة مزود',
        'new_provider' => 'مزود جديد',

        // Notifications
        'created_notification' => 'تم إنشاء الخدمة بنجاح',
        'updated_notification' => 'تم تحديث الخدمة بنجاح',
        'deleted_notification' => 'تم حذف الخدمة بنجاح',
        'providers_assigned' => 'تم تعيين المزودين',
        'providers_assigned_message' => 'تم تعيين {count} مزود(ين) لهذه الخدمة',
        'providers_updated' => 'تم تحديث المزودين',
        'providers_count_message' => 'هذه الخدمة لديها الآن {count} مزود(ين)',
        'translations_saved' => 'تم حفظ الترجمات',
        'translations_saved_message' => 'تم حفظ {count} ترجمة بنجاح',
        'translations_updated' => 'تم تحديث الترجمات',
        'translations_updated_message' => 'تم تحديث {count} ترجمة بنجاح',

        // Translations tab
        'translations' => 'الترجمات',
        'translation_for_language' => 'ترجمة {language}',
        'translations_section' => 'ترجمات الخدمة',
        'translations_section_desc' => 'أضف ترجمات للغات مختلفة',
        'language' => 'اللغة',
        'language_code' => 'رمز اللغة',
        'language_code_helper' => 'يتم ملؤه تلقائياً بناءً على اللغة المحددة',
        'translated_name' => 'اسم الخدمة (مترجم)',
        'translated_name_placeholder' => 'أدخل اسم الخدمة المترجم',
        'translated_name_helper' => 'أدخل اسم الخدمة باللغة المحددة',
        'translated_description' => 'الوصف (مترجم)',
        'translated_description_placeholder' => 'أدخل الوصف المترجم',
        'translated_description_helper' => 'قدم معلومات تفصيلية باللغة المحددة',
        'add_translation' => 'إضافة ترجمة',
        'new_translation' => 'ترجمة جديدة',

        // Relation Manager
        'assign_provider' => 'تعيين مزود خدمة',
        'no_providers_assigned' => 'لا يوجد مزودون معينون',
        'active_status' => 'الحالة النشطة',
        'provider_active_desc' => 'حدد ما إذا كان المزود نشطاً لتقديم هذه الخدمة',
        'custom_price_desc' => 'اتركه فارغاً لاستخدام السعر الافتراضي للخدمة',
        'custom_duration_desc' => 'اتركه فارغاً لاستخدام المدة الافتراضية للخدمة',
        'default_if_empty' => 'افتراضي إذا كان فارغاً',
        'assigned_at' => 'تاريخ التعيين',
        'last_booking_date' => 'تاريخ آخر حجز',
        'never' => 'أبداً',
    ],

    'invoice' => [
        'label' => 'فاتورة',
        'plural_label' => 'الفواتير',
        'number' => 'رقم الفاتورة',
        'status' => 'حالة الفاتورة',
        'total' => 'المبلغ الإجمالي',
        'created_at' => 'تاريخ الإنشاء',
    ],

    'language' => [
        'label' => 'اللغة',
        'plural_label' => 'اللغات',
        'navigation_label' => 'اللغات',

        // Table columns
        'name' => 'الاسم',
        'native_name' => 'الاسم الأصلي',
        'code' => 'الرمز',
        'order' => 'الترتيب',
        'status' => 'الحالة',
        'default' => 'افتراضي',
        'created_at' => 'تاريخ الإنشاء',
        'updated_at' => 'تاريخ التحديث',
        'deleted_at' => 'تاريخ الحذف',

        // Form tabs
        'basic_info' => 'المعلومات الأساسية',
        'translations' => 'الترجمات',
        'settings' => 'الإعدادات',

        // Form sections
        'language_details' => 'تفاصيل اللغة',
        'language_details_desc' => 'أدخل المعلومات الأساسية عن اللغة',
        'language_settings' => 'إعدادات اللغة',
        'language_settings_desc' => 'قم بتكوين إعدادات اللغة وترتيب العرض',

        // Form fields
        'name_placeholder' => 'مثال: الإنجليزية',
        'name_helper' => 'اسم اللغة بالإنجليزية',
        'native_name_placeholder' => 'مثال: English',
        'native_name_helper' => 'اسم اللغة بصيغتها الأصلية',
        'code_placeholder' => 'مثال: en',
        'code_helper' => 'رمز اللغة حسب معيار ISO (مثال: en, ar, de)',
        'order_helper' => 'الأرقام الأقل تظهر أولاً في القوائم',
        'active' => 'نشط',
        'active_helper' => 'اللغات غير النشطة لن تكون متاحة للاختيار',
        'default_language' => 'اللغة الافتراضية',
        'default_language_helper' => 'يجب تعيين لغة واحدة فقط كافتراضية',

        // Translations
        'translation_for_language' => 'ترجمة للغة :language',
        'language_id' => 'معرف اللغة',
        'language_code' => 'رمز اللغة',
        'language_code_helper' => 'يتم ملؤه تلقائياً بناءً على اللغة المحددة',
        'translated_name' => 'اسم اللغة (مترجم)',
        'translated_name_placeholder' => 'أدخل اسم اللغة المترجم',
        'translated_name_helper' => 'أدخل اسم اللغة باللغة المحددة',
        'translated_native_name' => 'الاسم الأصلي (مترجم)',
        'translated_native_name_placeholder' => 'أدخل الاسم الأصلي المترجم',
        'translated_native_name_helper' => 'أدخل الاسم الأصلي باللغة المحددة',
    ],

    'service_category' => [
        'label' => 'فئة خدمة',
        'plural_label' => 'فئات الخدمات',
        'navigation_label' => 'فئات الخدمات',

        // Table columns
        'name' => 'اسم الفئة',
        'description' => 'الوصف',
        'services_count' => 'عدد الخدمات',
        'image' => 'صورة الفئة',
        'sort_order' => 'ترتيب العرض',
        'status' => 'الحالة',
        'created_at' => 'تاريخ الإنشاء',
        'updated_at' => 'تاريخ التحديث',

        // Form tabs
        'basic_info' => 'المعلومات الأساسية',
        'translations' => 'الترجمات',
        'settings' => 'الإعدادات',

        // Form sections
        'category_details' => 'تفاصيل الفئة',
        'category_details_desc' => 'أدخل المعلومات الأساسية عن الفئة',
        'visual_settings' => 'الإعدادات البصرية',
        'visual_settings_desc' => 'ارفع صورة لهذه الفئة',
        'visibility_settings' => 'الظهور والعرض',
        'visibility_settings_desc' => 'تحكم في كيفية ومكان ظهور هذه الفئة',

        // Form fields
        'name_placeholder' => 'مثال: خدمات تصفيف الشعر',
        'name_helper' => 'أدخل اسماً واضحاً ووصفياً للفئة',
        'description_placeholder' => 'اوصف ما تتضمنه هذه الفئة...',
        'description_helper' => 'قدم معلومات تفصيلية عن الفئة',
        'image_helper' => 'ارفع صورة عالية الجودة (JPG أو PNG، الحد الأقصى 2 ميجابايت)',
        'sort_order_helper' => 'الأرقام الأقل تظهر أولاً',
        'active' => 'نشط',
        'active_helper' => 'الفئات غير النشطة لن تظهر في النظام',

        // Translations
        'translation_for_language' => 'ترجمة {language}',
        'language_id' => 'معرف اللغة',
        'language_code' => 'رمز اللغة',
        'language_code_helper' => 'يتم ملؤه تلقائياً بناءً على اللغة المحددة',
        'translated_name' => 'اسم الفئة (مترجم)',
        'translated_name_placeholder' => 'أدخل اسم الفئة المترجم',
        'translated_name_helper' => 'أدخل اسم الفئة باللغة المحددة',
        'translated_description' => 'الوصف (مترجم)',
        'translated_description_placeholder' => 'أدخل الوصف المترجم',
        'translated_description_helper' => 'قدم معلومات تفصيلية باللغة المحددة',

        // Notifications
        'created_notification' => 'تم إنشاء فئة الخدمة بنجاح',
        'updated_notification' => 'تم تحديث فئة الخدمة بنجاح',
        'deleted_notification' => 'تم حذف فئة الخدمة بنجاح',
        'translations_saved' => 'تم حفظ الترجمات',
        'translations_saved_message' => 'تم حفظ {count} ترجمة بنجاح',
        'translations_updated' => 'تم تحديث الترجمات',
        'translations_updated_message' => 'تم تحديث {count} ترجمة بنجاح',

        // Filters
        'active_only' => 'النشطة فقط',
        'inactive_only' => 'غير النشطة فقط',
        'all' => 'كل الفئات',

        // Statistics
        'total_services' => 'إجمالي الخدمات',
        'active_services' => 'الخدمات النشطة',
        'featured_services' => 'الخدمات المميزة',
        'services_in_category' => 'الخدمات في هذه الفئة',
    ],

    'salon_setting' => [
        'label' => 'إعداد الصالون',
        'plural_label' => 'إعدادات الصالون',
        'navigation_label' => 'إعدادات الصالون',

        // Table columns
        'key' => 'مفتاح الإعداد',
        'value' => 'القيمة',
        'type' => 'النوع',
        'description' => 'الوصف',
        'branch' => 'الفرع',
        'setting_group' => 'المجموعة',
        'created_at' => 'تاريخ الإنشاء',
        'updated_at' => 'تاريخ التحديث',

        // Form sections
        'setting_information' => 'معلومات الإعداد',
        'setting_information_desc' => 'عرض تفاصيل ومعلومات الإعداد',
        'setting_value' => 'قيمة الإعداد',
        'setting_value_desc' => 'تعديل قيمة هذا الإعداد',

        // Form fields
        'key_helper' => 'المعرّف الفريد لهذا الإعداد (لا يمكن تغييره)',
        'description_helper' => 'ما يتحكم به هذا الإعداد في النظام',
        'branch_helper' => 'اتركه فارغاً للإعدادات العامة أو اختر فرعاً محدداً',
        'global_setting' => 'إعداد عام (جميع الفروع)',
        'type_helper' => 'نوع البيانات لقيمة هذا الإعداد',
        'value_placeholder' => 'أدخل قيمة الإعداد',

        // Setting groups
        'group_general' => 'عام',
        'group_booking' => 'الحجوزات',
        'group_payment' => 'الدفع',
        'group_notifications' => 'الإشعارات',
        'group_loyalty' => 'الولاء',
        'group_contact' => 'التواصل',

        // Types
        'type_string' => 'نص',
        'type_integer' => 'رقم صحيح',
        'type_boolean' => 'نعم/لا',
        'type_json' => 'بيانات JSON',
        'type_decimal' => 'رقم عشري',

        // Type helpers
        'string_helper' => 'أدخل قيمة نصية (مثال: USD، email@example.com)',
        'integer_helper' => 'أدخل رقماً صحيحاً (مثال: 24، 100)',
        'decimal_helper' => 'أدخل رقماً عشرياً للنسب المئوية',
        'boolean_helper' => 'فعّل للحصول على صحيح، عطّل للحصول على خطأ',
        'json_helper' => 'أدخل مصفوفة أو كائن JSON صالح (مثال: ["خيار1", "خيار2"])',

        // Notifications
        'created_notification' => 'تم إنشاء الإعداد بنجاح',
        'updated_notification' => 'تم تحديث الإعداد بنجاح',
        'deleted_notification' => 'تم حذف الإعداد بنجاح',

        // Filters
        'filter_by_group' => 'تصفية حسب المجموعة',
        'filter_by_branch' => 'تصفية حسب الفرع',
        'all_settings' => 'جميع الإعدادات',
        'global_only' => 'الإعدادات العامة فقط',
        'branch_specific' => 'خاص بالفرع',

        // Infolist
        'current_value' => 'القيمة الحالية لهذا الإعداد',
        'value_copied' => 'تم نسخ القيمة!',
        'metadata' => 'البيانات الوصفية',
    ],

    'reason_leave' => [
        'label' => 'سبب الإجازة',
        'plural_label' => 'أسباب الإجازات',
        'navigation_label' => 'أسباب الإجازات',

        // Table columns
        'name' => 'اسم السبب',
        'description' => 'الوصف',
        'translations' => 'الترجمات',
        'usage_count' => 'الاستخدام',
        'times_used' => 'عدد مرات استخدام هذا السبب',
        'created_at' => 'تاريخ الإنشاء',
        'updated_at' => 'تاريخ التحديث',

        // Filters
        'has_translations' => 'لديه ترجمات',
        'frequently_used' => 'كثير الاستخدام (5+)',
        'unused' => 'لم يُستخدم بعد',

        // Actions
        'edit' => 'تعديل',
        'delete_selected' => 'حذف المحدد',

        // Empty state
        'no_reasons' => 'لا توجد أسباب للإجازات',
        'no_reasons_desc' => 'قم بإنشاء أول سبب للإجازة للبدء.',

        // Form sections
        'basic_info' => 'المعلومات الأساسية',
        'basic_info_desc' => 'أدخل المعلومات الأساسية عن سبب الإجازة',
        'translations_section' => 'الترجمات',
        'translations_section_desc' => 'أضف ترجمات للغات مختلفة',

        // Form fields
        'name_placeholder' => 'مثال: إجازة مرضية، إجازة سنوية، إجازة شخصية',
        'name_helper' => 'اسم سبب الإجازة',
        'description_placeholder' => 'أدخل وصفاً تفصيلياً',
        'description_helper' => 'اشرح متى يجب استخدام هذا السبب',
        'translation_for_language' => 'ترجمة لـ :language',

        // Notifications
        'created_notification' => 'تم إنشاء سبب الإجازة بنجاح',
        'updated_notification' => 'تم تحديث سبب الإجازة بنجاح',
        'deleted_notification' => 'تم حذف سبب الإجازة بنجاح',
        'translations_saved' => 'تم حفظ الترجمات بنجاح',

        // Infolist
        'no_translations' => 'لا توجد ترجمات متاحة',
        'usage_statistics' => 'إحصائيات الاستخدام',
        'usage_statistics_desc' => 'عدد المرات التي تم استخدام هذا السبب فيها',
        'total_usage' => 'إجمالي الاستخدام',
        'single_day_leaves' => 'إجازات يوم واحد',
        'multi_day_leaves' => 'إجازات متعددة الأيام',
        'metadata' => 'البيانات الوصفية',
    ],

    'provider_scheduled_work' => [
        'label' => 'جدول دوام',
        'plural_label' => 'جداول الدوام',
        'navigation_label' => 'جداول دوام الموظفين',

        // Table columns
        'provider' => 'مقدم الخدمة',
        'provider_name' => 'اسم المقدم',
        'day_of_week' => 'اليوم',
        'start_time' => 'وقت البداية',
        'end_time' => 'وقت النهاية',
        'working_hours' => 'ساعات العمل',
        'is_work_day' => 'يوم عمل',
        'break_minutes' => 'وقت الاستراحة',
        'break_duration' => 'مدة الاستراحة',
        'is_active' => 'نشط',
        'status' => 'الحالة',
        'created_at' => 'تاريخ الإنشاء',
        'updated_at' => 'آخر تحديث',

        // Days
        'sunday' => 'الأحد',
        'monday' => 'الاثنين',
        'tuesday' => 'الثلاثاء',
        'wednesday' => 'الأربعاء',
        'thursday' => 'الخميس',
        'friday' => 'الجمعة',
        'saturday' => 'السبت',

        // Status
        'work_day' => 'يوم عمل',
        'day_off' => 'يوم إجازة',
        'active' => 'نشط',
        'inactive' => 'غير نشط',

        // Filters
        'filter_by_provider' => 'تصفية حسب المقدم',
        'filter_by_day' => 'تصفية حسب اليوم',
        'work_days_only' => 'أيام العمل فقط',
        'days_off_only' => 'أيام الإجازة فقط',
        'active_only' => 'النشطة فقط',
        'inactive_only' => 'غير النشطة فقط',

        // Helpers
        'minutes' => 'دقيقة',
        'hours' => 'ساعة',
        'no_break' => 'بدون استراحة',
        'total_hours' => 'إجمالي الساعات',
        'effective_hours' => 'الساعات الفعلية',

        // New Summary Fields
        'work_days_count' => 'أيام العمل',
        'off_days_count' => 'أيام الإجازة',
        'weekly_hours' => 'ساعات العمل الأسبوعية',
        'time_offs_count' => 'عدد الإجازات',
        'upcoming_time_offs' => 'الإجازات القادمة',
        'total_time_offs' => 'إجمالي الإجازات',
        'schedule_status' => 'حالة الجدول',

        // Filter Labels
        'filter_by_work_days' => 'تصفية حسب أيام العمل',
        'no_work_days' => 'بدون أيام عمل',
        'days' => 'أيام',
        'has_schedule' => 'حالة الجدول',
        'with_schedule' => 'لديه جدول',
        'without_schedule' => 'بدون جدول',
        'has_time_offs' => 'حالة الإجازات',
        'with_time_offs' => 'لديه إجازات',
        'without_time_offs' => 'بدون إجازات',

        // Actions
        'view_timeline' => 'عرض Timeline',
        'view_timeline_tooltip' => 'عرض الجدول الزمني المرئي للموظف',
        'manage_schedule' => 'تعديل الجدول',
        'manage_schedule_tooltip' => 'تعديل وإدارة جدول دوام هذا الموظف',

        // Form Tabs
        'provider_info' => 'معلومات الموظف',
        'weekly_schedule' => 'الجدول الأسبوعي',
        'instructions' => 'الإرشادات',
        'help' => 'مساعدة',

        // Provider Selection
        'select_provider' => 'اختر الموظف',
        'select_provider_desc' => 'اختر موظفاً لإدارة جدول دوامه الأسبوعي',
        'select_provider_placeholder' => '-- اختر موظفاً --',
        'select_provider_helper' => 'يمكنك البحث باسم الموظف أو البريد الإلكتروني',
        'no_provider_selected' => 'لم يتم اختيار موظف',
        'no_provider_selected_desc' => 'الرجاء اختيار موظفاً من التبويب الأول لعرض الجدول الزمني',
        'go_to_provider_tab' => 'انتقل إلى تبويب معلومات الموظف',
        'select_provider_first' => 'الرجاء اختيار موظف أولاً لعرض الجدول الزمني',

        // Timeline Section
        'schedule_timeline' => 'الجدول الزمني',
        'schedule_timeline_desc' => 'عرض مرئي للجدول الأسبوعي مع الشفتات والساعات',

        // Instructions
        'how_to_use' => 'كيفية الاستخدام',
        'how_to_use_desc' => 'دليل خطوة بخطوة لإدارة جداول الدوام',
        'instructions_intro_title' => 'مرحباً بك في نظام إدارة جداول الدوام',
        'instructions_intro_text' => 'هذه الأداة تساعدك على إدارة جداول عمل الموظفين بشكل مرئي وسهل',

        // Steps
        'step_1' => 'اختر موظفاً من القائمة المنسدلة في التبويب الأول',
        'step_2' => 'انتقل إلى تبويب "الجدول الأسبوعي" لعرض جدول الموظف الحالي',
        'step_3' => 'شاهد الشفتات موضحة على مقياس زمني 24 ساعة لكل يوم',
        'step_4' => 'لتعديل الجدول، استخدم صفحة إدارة الجداول الرئيسية',

        // Timeline Legend
        'timeline_legend' => 'دليل الألوان',
        'shift_block' => 'شفت العمل',
        'branch_hours_bg' => 'ساعات عمل الفرع',
        'day_off_indicator' => 'يوم إجازة',

        // Tips
        'tips_title' => 'نصائح مفيدة',
        'tip_1' => 'يمكنك رؤية تفاصيل كل شفت بالمرور بالماوس فوقه',
        'tip_2' => 'الخلفية الزرقاء تمثل ساعات عمل الفرع الرسمية',
        'tip_3' => 'الألوان المختلفة للشفتات تساعد على التمييز بينها',

        // Important Note
        'important_note' => 'ملاحظة: هذا عرض مرئي فقط. لتعديل الجدول، استخدم صفحة إدارة الجداول أو انقر على زر "إدارة الجدول" في جدول الموظفين.',

        // Form Sections
        'basic_information' => 'المعلومات الأساسية',
        'basic_information_description' => 'معلومات الموظف واليوم والحالة',
        'work_schedule' => 'أوقات العمل',
        'work_schedule_description' => 'تحديد أوقات البداية والنهاية ووقت الاستراحة',
        'additional_notes' => 'ملاحظات إضافية',
        'additional_notes_description' => 'ملاحظات خاصة بهذا الشفت',

        // Form Fields
        'form_select_provider' => 'اختر مزود الخدمة',
        'form_provider_helper' => 'اختر الموظف الذي تريد تعديل شفته',
        'form_provider_required' => 'الموظف مطلوب',
        'select_day' => 'اختر اليوم',
        'day_helper' => 'اختر يوم الأسبوع لهذا الشفت',
        'day_required' => 'اليوم مطلوب',
        'is_active_helper' => 'الشفتات غير النشطة لن تظهر في نظام الحجز',
        'start_time_placeholder' => '09:00',
        'start_time_helper' => 'وقت بداية العمل',
        'start_time_required' => 'وقت البداية مطلوب',
        'end_time_placeholder' => '17:00',
        'end_time_helper' => 'وقت انتهاء العمل',
        'end_time_required' => 'وقت النهاية مطلوب',
        'break_minutes_placeholder' => '30',
        'break_minutes_helper' => 'عدد دقائق الاستراحة خلال الشفت',
        'break_minutes_numeric' => 'دقائق الاستراحة يجب أن تكون رقماً',
        'break_minutes_min' => 'دقائق الاستراحة يجب أن تكون 0 أو أكثر',
        'break_minutes_max' => 'دقائق الاستراحة يجب أن تكون أقل من 480 دقيقة',
        'is_work_day_helper' => 'حدد إذا كان هذا يوم عمل أو يوم إجازة',
        'notes' => 'ملاحظات',
        'notes_placeholder' => 'أضف ملاحظات خاصة بهذا الشفت...',
        'notes_helper' => 'ملاحظات اختيارية (حد أقصى 1000 حرف)',

        // Validation Messages
        'validation' => [
            'end_time_must_differ' => 'وقت النهاية يجب أن يكون مختلفاً عن وقت البداية',
            'break_exceeds_duration' => 'دقائق الاستراحة لا يمكن أن تكون أكبر من أو تساوي مدة الشفت',
            'shift_overlap' => 'يوجد تعارض مع شفت آخر في يوم :day من :start إلى :end',
        ],

        // Weekly Schedule Form
        'shifts' => 'الشفتات',
        'shift' => 'شفت',
        'add_shift' => 'إضافة شفت',
        'summary' => 'الملخص',
        'summary_description' => 'إحصائيات حية عن عبء العمل الأسبوعي.',
        'total_working_minutes' => 'إجمالي وقت العمل الأسبوعي',
        'active_days_count' => 'عدد الأيام التي تحتوي على شفت فعّال',
        'total_shifts_count' => 'إجمالي عدد الشفتات',
        'managing_schedule' => 'إدارة جدول العمل للموظف المختار',
        'weekly_schedule_description' => 'تحديد ساعات العمل لكل يوم من أيام الأسبوع',
        'edit_schedule_for' => 'تعديل جدول :name',
        'edit_weekly_schedule_description' => 'إدارة ساعات العمل الأسبوعية والاستراحات لجميع الأيام',
        'back_to_list' => 'العودة للقائمة',
        'save_schedule' => 'حفظ الجدول',
        'cancel' => 'إلغاء',
        'schedule_saved' => 'تم حفظ الجدول',
        'schedule_saved_successfully' => 'تم حفظ جدول العمل بنجاح',
        'save_error' => 'خطأ في الحفظ',
        'validation_error' => 'خطأ في التحقق',
    ],

    'provider_resource' => [
        'label' => 'موظف',
        'plural_label' => 'الموظفون',
        'navigation_label' => 'الموظفون',
        'title' => 'إدارة الموظفين',

        // Table columns
        'avatar' => 'الصورة',
        'full_name' => 'الاسم الكامل',
        'phone' => 'رقم الهاتف',
        'phone_copied' => 'تم نسخ رقم الهاتف!',
        'branch' => 'الفرع',
        'no_branch' => 'لا يوجد فرع',
        'services' => 'الخدمات',
        'appointments' => 'الحجوزات',
        'upcoming_leaves' => 'الإجازات القادمة',
        'status' => 'الحالة',
        'language' => 'اللغة',
        'joined_at' => 'تاريخ الانضمام',

        // Filters
        'all_providers' => 'كل الموظفين',
        'active_only' => 'النشطون فقط',
        'inactive_only' => 'غير النشطين فقط',
        'filter_by_branch' => 'تصفية حسب الفرع',
        'filter_by_language' => 'تصفية حسب اللغة',
        'has_upcoming_leaves' => 'الإجازات القادمة',
        'with_upcoming_leaves' => 'لديهم إجازات قادمة',
        'without_upcoming_leaves' => 'بدون إجازات قادمة',

        // Languages
        'arabic' => 'العربية',
        'english' => 'الإنجليزية',
        'german' => 'الألمانية',

        // Empty state
        'no_providers_yet' => 'لا يوجد موظفون بعد',
        'create_first_provider' => 'قم بإضافة أول موظف للبدء.',

        // Form sections
        'personal_information' => 'المعلومات الشخصية',
        'contact_information' => 'معلومات التواصل',
        'account_settings' => 'إعدادات الحساب',
        'additional_information' => 'معلومات إضافية',

        // Form fields
        'profile_image' => 'الصورة الشخصية',
        'profile_image_helper' => 'صورة بصيغة JPG أو PNG، بحد أقصى 2 ميجابايت',
        'first_name' => 'الاسم الأول',
        'last_name' => 'الاسم الأخير',
        'email' => 'البريد الإلكتروني',
        'password' => 'كلمة المرور',
        'password_confirmation' => 'تأكيد كلمة المرور',
        'password_helper' => 'يجب أن تحتوي كلمة المرور على 8 أحرف على الأقل',
        'address' => 'العنوان',
        'city' => 'المدينة',
        'locale' => 'اللغة',
        'select_language' => 'اختر اللغة',
        'locale_helper' => 'لغة واجهة المستخدم المفضلة',
        'select_branch' => 'اختر الفرع',
        'branch_helper' => 'الفرع الذي يعمل به الموظف',
        'is_active' => 'نشط',
        'status_helper' => 'الموظفون غير النشطين لا يمكنهم تسجيل الدخول',
        'notes' => 'ملاحظات',

        // View/Infolist
        'view_complete_profile' => 'عرض الملف الشخصي الكامل للموظف',
        'email_verified' => 'البريد محقق',
        'not_verified' => 'غير محقق',
        'not_provided' => 'غير متوفر',
        'verified_at' => 'تم التحقق في',
        'last_updated' => 'آخر تحديث',
        'no_notes_available' => 'لا توجد ملاحظات متاحة',

        // Provider Statistics
        'provider_statistics' => 'إحصائيات الموظف',
        'provider_stats' => 'إحصائيات الأداء والإنجازات',
        'services_count' => 'عدد الخدمات',
        'completed_bookings' => 'الحجوزات المكتملة',
        'average_rating' => 'متوسط التقييم',
        'no_reviews_yet' => 'لا توجد تقييمات بعد',
        'total_reviews' => 'إجمالي التقييمات',
        'total_earnings' => 'إجمالي الأرباح',
        'current_month_earnings' => 'أرباح هذا الشهر',
        'total_appointments' => 'إجمالي المواعيد',
        'upcoming_appointments' => 'المواعيد القادمة',
        'pending_appointments' => 'المواعيد المعلقة',
        'services_offered' => 'الخدمات المقدمة',
        'sar_currency' => 'ريال',
        'all_time' => 'منذ البداية',
        'vs_last_month' => 'مقارنة بالشهر الماضي',
        'total_completed' => 'إجمالي المكتملة',
        'all_statuses' => 'جميع الحالات',
        'scheduled_future' => 'مجدولة للمستقبل',
        'awaiting_completion' => 'بانتظار الإتمام',
        'active_services' => 'الخدمات النشطة',

        // Actions in table
        'add_leave' => 'إضافة إجازة',
        'add_leave_tooltip' => 'إضافة إجازة ساعية أو يومية',

        // Header Actions in View Page
        'add_hourly_leave' => 'إضافة إجازة ساعية',
        'add_hourly_leave_description' => 'إضافة إجازة لساعات محددة في يوم واحد',
        'add_daily_leave' => 'إضافة إجازة يومية',
        'add_daily_leave_description' => 'إضافة إجازة ليوم كامل أو عدة أيام',

        // Leave Management
        'leave_management' => 'إدارة الإجازات',
        'leave_type' => 'نوع الإجازة',
        'hourly_leave' => 'إجازة ساعية',
        'daily_leave' => 'إجازة يومية',
        'leave_date' => 'تاريخ الإجازة',
        'start_date' => 'تاريخ البداية',
        'end_date' => 'تاريخ النهاية',
        'start_time' => 'وقت البداية',
        'end_time' => 'وقت النهاية',
        'duration_hours' => 'المدة بالساعات',
        'duration_days' => 'المدة بالأيام',
        'hours' => 'ساعة',
        'days' => 'يوم',
        'reason' => 'السبب',
        'leave_status' => 'حالة الإجازة',
        'upcoming' => 'قادمة',
        'active' => 'نشطة',
        'past' => 'سابقة',
        'edit_leave' => 'تعديل الإجازة',
        'no_leaves_yet' => 'لا توجد إجازات بعد',
        'add_first_leave' => 'قم بإضافة أول إجازة للموظف',
        'leave_added_successfully' => 'تم إضافة الإجازة بنجاح',

        // Work Schedule
        'work_schedule' => 'جدول العمل',
        'day' => 'اليوم',
        'work_day' => 'يوم عمل',
        'day_off' => 'يوم إجازة',
        'break_time' => 'وقت الاستراحة',
        'break_minutes' => 'دقائق الاستراحة',
        'minutes' => 'دقيقة',
        'working_hours' => 'ساعات العمل',
        'add_schedule' => 'إضافة جدول',
        'edit_schedule' => 'تعديل الجدول',
        'no_schedule_yet' => 'لا يوجد جدول بعد',
        'add_first_schedule' => 'قم بإضافة أول جدول للموظف',

        // Leave Statistics Widget
        'total_leaves_this_year' => 'إجمالي الإجازات هذا العام',
        'all_leave_types' => 'جميع أنواع الإجازات',
        'total_days_used' => 'إجمالي الأيام المستخدمة',
        'full_day_leaves_only' => 'الإجازات اليومية فقط',
        'total_hours_used' => 'إجمالي الساعات المستخدمة',
        'hourly_leaves_only' => 'الإجازات الساعية فقط',
        'scheduled_for_future' => 'المجدولة للمستقبل',
        'current_month_leaves' => 'إجازات هذا الشهر',
        'active_leaves' => 'الإجازات النشطة',
        'currently_on_leave' => 'في إجازة حالياً',
        'not_on_leave' => 'ليس في إجازة',

        // All Provider Leaves Page
        'all_provider_leaves' => 'جميع إجازات الموظفين',
        'provider_management' => 'إدارة الموظفين',
        'provider_name' => 'اسم الموظف',
        'duration' => 'المدة',
        'total_leaves' => 'إجمالي الإجازات',
        'this_month' => 'هذا الشهر',
        'this_year' => 'هذا العام',
        'no_leaves_description' => 'لا توجد إجازات مسجلة في النظام',

        // Appointments Management (RelationManager)
        'appointments_management' => 'إدارة الحجوزات',
        'booking_number' => 'رقم الحجز',
        'number_copied' => 'تم نسخ الرقم!',
        'customer_name' => 'اسم العميل',
        'appointment_date' => 'تاريخ الحجز',
        'appointment_status' => 'حالة الحجز',
        'status_pending' => 'قيد الانتظار',
        'status_completed' => 'مكتمل',
        'status_user_cancelled' => 'ملغي من العميل',
        'status_admin_cancelled' => 'ملغي من الإدارة',
        'payment_pending' => 'بانتظار الدفع',
        'paid_online' => 'مدفوع أونلاين',
        'paid_cash' => 'مدفوع كاش',
        'paid_card' => 'مدفوع بالبطاقة',
        'payment_failed' => 'فشل الدفع',
        'refunded' => 'مسترد',
        'partially_refunded' => 'مسترد جزئياً',
        'total_price' => 'السعر الإجمالي',
        'includes_tax' => 'يشمل الضريبة',
        'no_services' => 'لا توجد خدمات',
        'filter_status' => 'تصفية حسب الحالة',
        'filter_payment' => 'تصفية حسب حالة الدفع',
        'filter_time' => 'تصفية حسب الوقت',
        'past_appointments' => 'الحجوزات السابقة',
        'filter_today' => 'حجوزات اليوم',
        'today_only' => 'اليوم فقط',
        'not_today' => 'ليس اليوم',
        'from_date' => 'من تاريخ',
        'to_date' => 'إلى تاريخ',
        'mark_as_paid' => 'تحديد كـ مدفوع',
        'payment_method' => 'طريقة الدفع',
        'end_time_helper' => 'حدد وقت نهاية الحجز الفعلي',
        'amount_paid' => 'المبلغ المدفوع',
        'includes_tax_suffix' => '(يشمل الضريبة)',
        'breakdown' => 'التفصيل',
        'subtotal' => 'المجموع الفرعي',
        'tax' => 'الضريبة',
        'amount_paid_helper' => 'أدخل المبلغ الإجمالي المدفوع شامل الضريبة',
        'payment_notes' => 'ملاحظات الدفع',
        'payment_success' => 'تم الدفع بنجاح',
        'invoice_created' => 'تم إنشاء الفاتورة',
        'payment_error' => 'خطأ في الدفع',
        'no_appointments_yet' => 'لا توجد حجوزات بعد',
        'no_appointments_desc' => 'لم يتم إضافة أي حجوزات لهذا الموظف بعد',
    ],

    'page_resource' => [
        // Resource Labels
        'label' => 'صفحة',
        'plural_label' => 'الصفحات',
        'navigation_label' => 'إدارة الصفحات',
        'navigation_group' => 'إدارة المحتوى',

        // Page Information Section
        'page_information' => 'معلومات الصفحة',
        'page_information_description' => 'معلومات الصفحة الأساسية (للعرض فقط)',
        'page_key' => 'معرّف الصفحة',
        'template' => 'القالب',
        'version' => 'الإصدار',
        'published' => 'منشورة',
        'published_helper' => 'تحديد ما إذا كانت هذه الصفحة مرئية للعامة',

        // Language Sections
        'default_language_suffix' => '(اللغة الافتراضية)',
        'default_language_description' => 'الترجمة الافتراضية مطلوبة لجميع الحقول',
        'translation_optional' => 'ترجمة اختيارية بلغة %s',

        // Content Fields
        'title' => 'العنوان',
        'title_placeholder' => 'أدخل عنوان الصفحة',
        'content' => 'المحتوى',

        // SEO Fields
        'seo_title' => 'عنوان SEO',
        'seo_title_placeholder' => 'عنوان محرك البحث (60 حرف كحد أقصى)',
        'seo_title_helper' => 'العنوان الذي يظهر في نتائج محركات البحث',
        'seo_description' => 'وصف SEO',
        'seo_description_placeholder' => 'وصف موجز للصفحة',
        'seo_description_helper' => 'الوصف الذي يظهر في نتائج محركات البحث (160 حرف كحد أقصى)',

        // Table Columns
        'last_updated' => 'آخر تحديث',

        // Filters
        'filter_published' => 'حالة النشر',
        'all_pages' => 'كل الصفحات',
        'published_only' => 'المنشورة فقط',
        'unpublished_only' => 'غير المنشورة فقط',
        'filter_by_template' => 'تصفية حسب القالب',

        // Actions
        'preview' => 'معاينة',
        'edit' => 'تعديل',
        'live_preview' => 'معاينة مباشرة',

        // Empty States
        'no_pages_yet' => 'لا توجد صفحات بعد',
        'no_pages_description' => 'الصفحات يتم إنشاؤها مسبقاً عبر Seeders',

        // Messages
        'saved_successfully' => 'تم حفظ الصفحة بنجاح',
        'preview_error' => 'حدث خطأ أثناء عرض الصفحة',

        // Validation
        'default_translation_required' => 'يجب توفير ترجمة للغة الافتراضية',
    ],

    // Common Actions
    'view' => 'عرض',
    'edit' => 'تعديل',
    'delete' => 'حذف',
    'all' => 'الكل',
];