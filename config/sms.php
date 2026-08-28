<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    |
    | When false, no SMS ever leaves the app: every send is logged and skipped,
    | and the OTP endpoints keep returning the code in their JSON response so the
    | flow stays testable. Turning this on flips both behaviours at once — the
    | code goes out by SMS and stops being exposed over the API.
    |
    | VONAGE_SMS_ENABLED is honoured as a fallback so existing .env files that
    | predate the seven.io migration keep working untouched.
    |
    */

    'enabled' => env('SMS_ENABLED', env('VONAGE_SMS_ENABLED', false)),

    /*
    |--------------------------------------------------------------------------
    | Active gateway
    |--------------------------------------------------------------------------
    |
    | Which driver actually delivers the message. Swapping providers — or falling
    | back to the previous one — is a one-line .env change, no code edit.
    |
    | Supported: "seven" (seven.io), "vonage" (legacy, kept as a fallback),
    | "log" (writes to the log channel instead of hitting any network).
    |
    */

    'driver' => env('SMS_DRIVER', 'seven'),

    /*
    |--------------------------------------------------------------------------
    | Number normalisation
    |--------------------------------------------------------------------------
    |
    | Phone numbers in this database are stored for humans, not for gateways —
    | e.g. "+971-50-101-0101". Gateways want bare E.164 digits, so every number
    | is normalised before it is sent.
    |
    | `default_country_code` (digits only, no "+") is used for numbers typed in
    | national format with a single leading zero, e.g. "050-101-0101" with a
    | default of 971 becomes "+971501010101". Leave it empty to send such numbers
    | through untouched and let the gateway account default decide.
    |
    */

    'default_country_code' => env('SMS_DEFAULT_COUNTRY_CODE'),

    /*
    |--------------------------------------------------------------------------
    | Drivers
    |--------------------------------------------------------------------------
    */

    'drivers' => [

        'seven' => [
            // Created at https://dashboard.seven.io/developer/api
            'api_key' => env('SEVEN_API_KEY'),

            // Sender ID. seven.io caps this at 11 alphanumeric characters (or 16
            // numeric ones); anything longer is rejected with code 201. Leave it
            // empty to fall back to the account's default sender number, which is
            // what some countries require anyway for unregistered alpha senders.
            'from' => env('SEVEN_FROM', 'Barber'),

            'base_url' => env('SEVEN_BASE_URL', 'https://gateway.seven.io/api'),

            // Validity period in minutes. Null keeps the gateway default (48h).
            // OTPs are short-lived, so a tight TTL avoids a code landing after it
            // has already expired.
            'ttl' => env('SEVEN_TTL'),

            // Optional statistics label shown in the seven.io dashboard.
            'label' => env('SEVEN_LABEL'),

            // seven.io's dry-run flag: the request is validated and priced but no
            // SMS is sent and no credit is spent. Ideal for a staging deployment.
            'debug' => env('SEVEN_DEBUG', false),

            'timeout' => (int) env('SEVEN_TIMEOUT', 15),
        ],

        'vonage' => [
            'key' => env('VONAGE_KEY'),
            'secret' => env('VONAGE_SECRET'),
            'from' => env('VONAGE_FROM'),
            'base_url' => env('VONAGE_BASE_URL', 'https://rest.nexmo.com'),
            'timeout' => (int) env('VONAGE_TIMEOUT', 15),
        ],

        'log' => [
            'channel' => env('SMS_LOG_CHANNEL'),
            'from' => env('SMS_LOG_FROM', 'Barber'),
        ],

    ],

];
