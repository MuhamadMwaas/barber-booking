<?php

return [
    'new_appointment' => 'Neuer Termin',
    'new_appointment_message' => 'Eine neue Buchung fuer :service wurde am :date um :time erstellt. Zahlungsart: :payment_type.',

    // Wird gesendet, wenn ein Termin wegen einer hinzugefuegten Service auf einen frueheren Termin verschoben wurde.
    'booking_pushed_title' => 'Termin verschoben',
    'booking_pushed_body' => 'Ihr Termin #:number wurde auf :time verschoben (um :minutes Minuten spaeter).',
    // Wird ausgelöst, wenn ein Kunde innerhalb einer Woche zum zweiten Mal storniert.
    'repeat_cancellation_title' => 'Wiederholte Stornierungen: :customer',
    'repeat_cancellation_body' => ':customer hat in den letzten :days Tagen :count Buchungen storniert. Zuletzt: #:number.',
    'repeat_cancellation_view_customer' => 'Kunde ansehen',
];
