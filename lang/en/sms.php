<?php

return [

    // One-time codes sent by SMS (OtpDeliveryService). :code = the code,
    // :validity = the lifetime phrase from the 'minutes' key.
    'otp' => [
        'account_verification' => 'Your verification code is: :code. Please enter this code in the app to confirm your phone number. The code is valid for :validity.',
        'password_reset' => 'Your verification code is: :code. Please enter this code in the app to reset your password. The code is valid for :validity.',
    ],

    'minutes' => '{1} 1 minute|[2,*] :count minutes',

];
