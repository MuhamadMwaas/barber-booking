<?php

return [
    'default_language' => env('CMS_DEFAULT_LANGUAGE', 'ar'),

    'supported_languages' => [
        'ar' => [
            'label'             => 'العربية',
            'direction'         => 'rtl',
            'default_alignment' => 'right',
        ],
        'en' => [
            'label'             => 'English',
            'direction'         => 'ltr',
            'default_alignment' => 'left',
        ],
        'de' => [
            'label'             => 'Deutsch',
            'direction'         => 'ltr',
            'default_alignment' => 'left',
        ],
    ],

    'cache' => [
        'ttl'    => env('CMS_PAGE_CACHE_TTL', 86400),
        'prefix' => 'cms_page',
    ],

    'colors' => [
        'default'   => 'Default',
        'primary'   => 'Primary',
        'secondary' => 'Secondary',
        'muted'     => 'Muted',
        'danger'    => 'Danger',
        'warning'   => 'Warning',
        'success'   => 'Success',
    ],
];
