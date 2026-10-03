<?php

return [
    // Subjects
    'subject_customer' => 'Buchungsbestätigung — :number',
    'subject_company'  => 'Neue Buchung erhalten — :number',

    // Intros
    'greeting'       => 'Hallo :name,',
    'confirm_intro'  => 'Vielen Dank für Ihre Buchung bei :company. Ihre Buchung wurde erfolgreich entgegengenommen.',
    'company_intro'  => 'Soeben wurde eine neue Online-Buchung erstellt. Die Details finden Sie unten:',

    // Booking fields
    'booking_number' => 'Buchungsnummer',
    'date'           => 'Datum',
    'time'           => 'Uhrzeit',
    'duration'       => 'Dauer',
    'provider'       => 'Mitarbeiter/in',
    'payment_method' => 'Zahlungsmethode',
    'source'         => 'Buchungsquelle',
    'notes'          => 'Hinweise',

    // Services table
    'services' => 'Leistungen',
    'service'  => 'Leistung',
    'price'    => 'Preis',
    'subtotal' => 'Zwischensumme',
    'tax'      => 'Steuer',
    'total'    => 'Gesamtpreis',

    // Customer block (company email)
    'customer' => 'Kundendaten',
    'name'     => 'Name',
    'email'    => 'E-Mail',
    'phone'    => 'Telefon',

    // Aufgeteilte Buchung (eine Buchung, mehrere Termine)
    'appointments_heading' => 'Ihre :count Termine',
    'appointments_intro'   => 'Ihre Leistungen finden zu verschiedenen Zeiten statt und wurden daher als einzelne Termine gebucht. Jeder Termin kann einzeln storniert werden.',
    'appointment_n'        => 'Termin :n',
    'group_total'          => 'Gesamtbetrag aller Termine',

    // Kundenansicht (Bestätigung + Erinnerung)
    'details_heading'   => 'Ihre Buchungsdetails',
    'services_customer' => 'Ihre Leistungen',
    'payment_summary'   => 'Zahlungsübersicht',
    'payment_methods'   => [
        'cash'   => 'Barzahlung',
        'online' => 'Online',
    ],

    // Terminerinnerung per E-Mail
    'reminder_subject' => 'Terminerinnerung — :number',
    'reminder_intro'   => 'Wir möchten Sie an Ihren bevorstehenden Termin bei :company erinnern.',
    'reminder_thanks'  => 'Wir freuen uns auf Ihren Besuch bei :company!',

    // Footer
    'thanks'   => 'Vielen Dank, dass Sie sich für :company entschieden haben! Wir freuen uns auf Ihren Besuch.',
    'footer'   => 'Dies ist eine automatisch versendete E-Mail. Bitte antworten Sie nicht direkt auf diese Nachricht.',
];
