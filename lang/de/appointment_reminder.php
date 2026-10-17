<?php

/**
 * Texte für Terminerinnerungen.
 *
 * `title` und `message` sind der Erinnerungstext selbst und werden auf allen
 * drei Kanälen (Push, E-Mail, SMS) identisch verwendet.
 *
 * `screen` und `options` sind Oberflaechentexte, die die Mobile-App über
 * `GET /api/appointments/reminders/options` bezieht — sie müssen dort nicht
 * fest einprogrammiert werden.
 */
return [
    // Aktueller Text (Vorgabe des Kunden). Platzhalter füllt
    // AppointmentReminderService::buildPayload() beim Versand:
    // :name Kunde (Vorname), :salon Firmenname, :weekday Wochentag,
    // :date TT.MM.JJJJ, :time HH:MM, :provider Mitarbeiter (Vorname).
    // Die SMS soll GSM-7 bleiben (kein „–“, keine Emojis), sonst halbiert sich
    // die Zeichenzahl pro SMS-Teil.
    'v2' => [
        'title' => ':salon – Terminerinnerung',
        'message' => 'Hallo :name, dies ist eine Erinnerung an Deinen Termin bei :salon am :weekday, :date um :time Uhr bei :provider. Wir freuen uns auf Dich! Dein :salon Team',
    ],

    // ALT — nicht löschen: bereits gespeicherte In-App-Benachrichtigungen
    // verweisen auf diese Schlüssel und werden bei jedem Abruf neu übersetzt.
    'title' => 'Terminerinnerung',
    'message' => 'Erinnerung: Ihr Termin #:number findet am :date um :time statt.',

    'screen' => [
        'title' => 'Terminerinnerung',
        'subtitle' => 'Erhalte eine Erinnerung vor deinem Termin.',
        'question' => 'Wann möchtest du erinnert werden?',
    ],

    'options' => [
        1 => '1 Stunde vorher',
        2 => '2 Stunden vorher',
        3 => '3 Stunden vorher',
        4 => '4 Stunden vorher',
        5 => '5 Stunden vorher',
        6 => '6 Stunden vorher',
        24 => '24 Stunden vorher',
    ],

    'option_fallback' => ':hours Stunden vorher',
];
