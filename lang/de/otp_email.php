<?php

/**
 * E-Mail mit Einmalcode (SendOtpMail). Ein Text pro Zweck (OtpPurpose).
 * :company = Markenname, :date/:time = Ablaufzeitpunkt des Codes.
 */
return [
    'subject' => [
        'account_verification' => ':company – E-Mail-Adresse bestätigen',
        'password_reset'       => ':company – Passwort zurücksetzen',
    ],

    'greeting' => 'Hallo :name,',

    'intro' => [
        'account_verification' => 'Bitte gib den folgenden Bestätigungscode ein, um Deine E-Mail-Adresse zu bestätigen und mit der Registrierung fortzufahren.',
        'password_reset'       => 'Bitte gib den folgenden Bestätigungscode ein, um Dein Passwort zurückzusetzen.',
    ],

    'validity'      => 'Der Code ist bis :date um :time Uhr gültig.',
    'not_requested' => 'Wenn Du diesen Code nicht angefordert hast, kannst Du diese E-Mail einfach ignorieren.',
    'signoff'       => 'Dein :company Team',
];
