<?php

return [

    /* ── General ── */
    'page_not_found' => 'الصفحة غير موجودة.',

    /* ── Language labels ── */
    'lang' => [
        'ar' => 'العربية',
        'en' => 'الإنجليزية',
        'de' => 'الألمانية',
    ],

    /* ── Block type labels ── */
    'blocks' => [
        'heading'         => 'عنوان',
        'paragraph'       => 'فقرة',
        'title_paragraph' => 'فقرة مع عنوان',
        'ordered_list'    => 'قائمة مرتبة',
        'unordered_list'  => 'قائمة غير مرتبة',
        'divider'         => 'فاصل',
        'link'            => 'رابط',
        'image'           => 'صورة',
        'warning_box'     => 'صندوق تحذير',
    ],

    /* ── Block section labels (inside each block form) ── */
    'block_sections' => [
        'display_options' => 'خيارات العرض',
        'translations'    => 'الترجمات',
    ],

    /* ── Shared field labels ── */
    'fields' => [
        'is_active'        => 'مفعل',
        'text'             => 'النص',
        'title'            => 'العنوان',
        'heading_level'    => 'مستوى العنوان',
        'alignment'        => 'المحاذاة',
        'color'            => 'اللون',
        'background_color' => 'لون الخلفية',
        'orientation'      => 'الاتجاه',
        'size'             => 'السماكة',
        'items'            => 'العناصر',
        'item'             => 'عنصر',
        'label'            => 'النص',
        'url'              => 'الرابط',
        'target'           => 'طريقة الفتح',
        'image'            => 'الصورة',
        'alt'              => 'وصف الصورة',
    ],

    /* ── Alignment options ── */
    'alignment' => [
        'auto'    => 'تلقائي حسب اللغة',
        'left'    => 'يسار',
        'right'   => 'يمين',
        'center'  => 'وسط',
        'justify' => 'ضبط',
    ],

    /* ── Orientation options ── */
    'orientation' => [
        'horizontal' => 'أفقي',
        'vertical'   => 'عمودي',
    ],

    /* ── Size options ── */
    'size' => [
        'sm' => 'خفيف',
        'md' => 'متوسط',
        'lg' => 'عريض',
    ],

    /* ── Link target options ── */
    'target' => [
        'same'     => 'نفس الشاشة / WebView',
        'external' => 'متصفح خارجي',
    ],

    /* ── Filament Resource ── */
    'resource' => [
        'navigation_label'   => 'صفحات التطبيق',
        'model_label'        => 'صفحة',
        'plural_model_label' => 'صفحات التطبيق',

        /* Sections */
        'section_page_info'      => 'هوية الصفحة',
        'section_page_info_desc' => 'الاسم الداخلي والـ Slug يحددان كيفية طلب الصفحة عبر API.',
        'section_blocks'         => 'محتوى الصفحة',
        'section_blocks_desc'    => 'أضف البلوكات ورتبها بالترتيب الذي تريد ظهورها فيه داخل التطبيق.',

        /* Fields */
        'field_name'             => 'الاسم الداخلي',
        'field_name_placeholder' => 'مثال: سياسة الخصوصية',
        'field_slug_hint'        => 'يُستخدم في طلب الصفحة عبر الـ API — لا تغيّره بعد نشر التطبيق',
        'field_is_active'        => 'الصفحة مفعلة',
        'field_is_active_hint'   => 'الصفحات المعطلة تُرجع 404 ولا تظهر في التطبيق',
        'field_blocks'           => 'البلوكات',
        'add_block'              => '+ إضافة بلوك',

        /* API Preview */
        'api_preview_label'    => 'رابط الـ API',
        'api_slug_placeholder' => 'slug-الصفحة',
        'api_lang_options'     => 'اللغات المدعومة',

        /* Blocks hint */
        'blocks_hint' => 'اسحب البلوكات لإعادة ترتيبها • اضغط على البلوك لتوسيعه أو طيّه • يمكن نسخ أي بلوك بزر Clone',

        /* Table columns */
        'col_name'       => 'الاسم',
        'col_is_active'  => 'مفعلة',
        'col_updated_at' => 'آخر تعديل',
        'slug_copied'    => 'تم نسخ الـ Slug',

        /* Actions */
        'action_preview_api' => 'استجابة API',
        'action_preview'     => 'معاينة',

        /* Preview page */
        'preview_label'           => 'معاينة التطبيق',
        'preview_back'            => 'العودة للإدارة',
        'preview_edit'            => 'تعديل الصفحة',
        'preview_language'        => 'اللغة',
        'preview_stats'           => 'إحصائيات',
        'preview_total_blocks'    => 'مجموع البلوكات',
        'preview_active_blocks'   => 'فعّالة',
        'preview_inactive_blocks' => 'معطّلة',
        'preview_no_blocks'       => 'لا توجد بلوكات بعد',
        'preview_status_active'   => 'فعّالة',
        'preview_status_inactive' => 'معطّلة',
    ],
];
