<?php

return [
    'failed' => 'Diese Zugangsdaten stimmen nicht mit unseren Aufzeichnungen ueberein.',
    'password' => 'Das angegebene Passwort ist nicht korrekt.',
    'throttle' => 'Zu viele Anmeldeversuche. Bitte versuchen Sie es in :seconds Sekunden erneut.',

    // AUTH-01 — wird mit HTTP 429 von jedem Limiter in config/rate_limits.php zurueckgegeben.
    // Nennt bewusst nicht, welches Limit erreicht wurde: das wuerde einem Angreifer
    // bestaetigen, dass das Konto existiert.
    'throttle_generic' => 'Zu viele Anfragen. Bitte versuchen Sie es in :seconds Sekunden erneut.',

    'account_disabled_title' => 'Konto deaktiviert',
    'account_disabled_body' => 'Ihr Konto wurde deaktiviert. Bitte wenden Sie sich an die Verwaltung.',
    'account_disabled' => 'Ihr Konto wurde deaktiviert. Bitte wenden Sie sich an die Verwaltung.',

    'customer_not_allowed_title' => 'Zugriff verweigert',
    'customer_not_allowed_body' => 'Kundenkonten haben keinen Zugriff auf das Admin-Panel.',

    // Returned (HTTP 409) when registering with an email that already exists.
    'email_exists_title' => 'Konto bereits vorhanden',
    'email_exists_body' => 'Mit dieser E-Mail-Adresse wurde bereits ein Konto erstellt. Melden Sie sich an, um fortzufahren.',

    // Returned (HTTP 409) when registering with a phone number that already exists.
    'phone_exists_title' => 'Diese Handynummer ist bereits mit einem anderen Konto verknüpft.',
    'phone_exists_body' => 'Bitte verwende eine andere Handynummer oder melde dich an.',

    // Buttons of the "account already exists" dialog (email and phone).
    'account_exists_login' => 'Anmelden',
    'account_exists_close' => 'Schließen',
];
