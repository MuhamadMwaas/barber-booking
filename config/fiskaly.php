<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Fiskaly API Credentials
    |--------------------------------------------------------------------------
    |
    | Your Fiskaly API key and secret. You can get these from the Fiskaly Dashboard
    | at https://dashboard.fiskaly.com
    |
    */
    'api_key' => env('FISKALY_API_KEY'),
    'api_secret' => env('FISKALY_API_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Fiskaly Environment
    |--------------------------------------------------------------------------
    |
    | The environment you want to use. Options: 'test', 'production'
    | Test: https://kassensichv.fiskaly.com
    | Production: https://kassensichv.fiskaly.com (same, use different org)
    |
    */
    'environment' => env('FISKALY_ENVIRONMENT', 'test'),

    /*
    |--------------------------------------------------------------------------
    | API Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL for Fiskaly API V2
    |
    */
    'base_url' => env('FISKALY_BASE_URL', 'https://kassensichv.fiskaly.com/api/v2'),

    /*
    |--------------------------------------------------------------------------
    | Organization ID
    |--------------------------------------------------------------------------
    |
    | Your organization ID in Fiskaly system
    |
    */
    'organization_id' => env('FISKALY_ORGANIZATION_ID'),

    /*
    |--------------------------------------------------------------------------
    | TSS (Technical Security System) Settings
    |--------------------------------------------------------------------------
    */
    'tss' => [
        'id' => env('FISKALY_TSS_ID'),
        'description' => env('FISKALY_TSS_DESCRIPTION', 'Main TSS for Salon'),
        // Store the PUK securely - it's only shown once!
        'puk' => env('FISKALY_TSS_PUK'),
        // Admin PIN for TSS operations
        'admin_pin' => env('FISKALY_TSS_ADMIN_PIN', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Client (Cash Register) Settings
    |--------------------------------------------------------------------------
    */
    'client' => [
        'id' => env('FISKALY_CLIENT_ID'),
        'serial_number' => env('FISKALY_CLIENT_SERIAL', 'SALON-POS-001'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tax Settings
    |--------------------------------------------------------------------------
    */
    'tax' => [
        // German VAT rates
        'rates' => [
            'standard' => "19", // Standard VAT 19%
            'reduced' => "7",   // Reduced VAT 7%
            'special' => "0",   // Special rate 0%
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Receipt Settings
    |--------------------------------------------------------------------------
    */
    'receipt' => [
        // Business information to print on receipts
        'business_name' => env('FISKALY_BUSINESS_NAME', 'Beauty Salon'),
        'business_address' => env('FISKALY_BUSINESS_ADDRESS', 'Musterstraße 123, 10115 Berlin'),
        'tax_number' => env('FISKALY_TAX_NUMBER', ''),
        'vat_number' => env('FISKALY_VAT_NUMBER', ''),

        // Receipt format
        'include_qr_code' => true,
        'include_signature' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'token_key' => 'fiskaly_jwt_token',
        'token_ttl' => 3600, // 1 hour in seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('FISKALY_LOGGING_ENABLED', true),
        'channel' => env('FISKALY_LOG_CHANNEL', 'daily'),
        'level' => env('FISKALY_LOG_LEVEL', 'info'), // debug, info, warning, error
    ],

    /*
    |--------------------------------------------------------------------------
    | Offline Mode
    |--------------------------------------------------------------------------
    |
    | When Fiskaly is unreachable, transactions can still be processed
    | and will be marked as "Sicherungseinrichtung ausgefallen"
    |
    */
    'offline_mode' => [
        'enabled' => true,
        'max_retry_attempts' => 3,
        'retry_delay' => 2, // seconds
    ],
];
