<?php

return [
    'reset' => 'Ihr Passwort wurde erfolgreich zurueckgesetzt.',
    'sent' => 'Wir haben Ihnen einen Link zum Zuruecksetzen des Passworts per E-Mail gesendet.',
    'throttled' => 'Bitte warten Sie einen Moment, bevor Sie es erneut versuchen.',
    'token' => 'Dieser Bestaetigungscode ist ungueltig oder abgelaufen.',
    'user' => 'Wir konnten kein Konto mit diesen Angaben finden.',

    // OTP-basierter Zuruecksetzungs-Ablauf (PasswordResetController).
    'sent_email' => 'Wir haben Ihnen einen Bestaetigungscode per E-Mail gesendet.',
    'sent_sms' => 'Wir haben einen Bestaetigungscode an Ihre Telefonnummer gesendet.',
    'otp_verified' => 'Code bestaetigt. Sie koennen jetzt ein neues Passwort waehlen.',
    'cooldown' => 'Bitte warten Sie :seconds Sekunden, bevor Sie einen neuen Code anfordern.',

    'requirements' => [
        'title' => 'Passwortanforderungen',
        'minimum_length' => 'Mindestens :min Zeichen',
        'latin_only' => 'Nur lateinische Zeichen',
        'uppercase' => 'Mindestens ein Grossbuchstabe (A-Z)',
        'number' => 'Mindestens eine Ziffer',
        'edit_helper' => 'Leer lassen, um das aktuelle Passwort beizubehalten.',
        'state' => [
            'met' => 'Anforderung erfüllt',
            'unmet' => 'Anforderung nicht erfüllt',
        ],
        'validation' => [
            'minimum_length' => 'Das Passwort muss mindestens :min Zeichen enthalten.',
            'latin_only' => 'Verwenden Sie nur lateinische Buchstaben, Ziffern und Symbole, ohne Leerzeichen.',
            'uppercase' => 'Das Passwort muss mindestens einen Grossbuchstaben (A-Z) enthalten.',
            'number' => 'Das Passwort muss mindestens eine Ziffer enthalten.',
        ],
    ],
];
