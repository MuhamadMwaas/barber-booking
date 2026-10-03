<?php

return [

    // Einmalcodes per SMS (OtpDeliveryService). :code = der Code,
    // :validity = die Gültigkeitsdauer aus dem Schlüssel 'minutes'.
    'otp' => [
        'account_verification' => 'Ihr Bestätigungscode lautet: :code. Bitte geben Sie diesen Code in der App ein, um Ihre Telefonnummer zu bestätigen. Der Code ist :validity gültig.',
        'password_reset' => 'Ihr Bestätigungscode lautet: :code. Bitte geben Sie diesen Code in der App ein, um Ihr Passwort zurückzusetzen. Der Code ist :validity gültig.',
    ],

    'minutes' => '{1} 1 Minute|[2,*] :count Minuten',

];
