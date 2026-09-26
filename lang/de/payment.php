<?php

// Meldungen aus InvoiceFinalizationService — dem einzigen Zahlungsweg im Salon,
// gemeinsam genutzt vom StaffDashboard und den Filament-Zahlungsmasken.
return [
    'errors' => [
        'method_not_found' => 'Die Zahlungsart existiert nicht oder ist nicht aktiv.',
        'method_not_on_site' => 'Die Zahlungsart muss Bar oder Karte im Salon sein.',
        'no_appointments' => 'Mit dieser Rechnung sind keine Termine verknüpft.',
        'cancelled_or_no_show' => 'Eine Rechnung mit einem stornierten oder nicht wahrgenommenen Termin kann nicht kassiert werden.',
        'invoice_not_positive' => 'Eine Rechnung ohne positiven Betrag kann nicht kassiert werden.',
        'amount_not_positive' => 'Der Zahlungsbetrag muss größer als null sein.',
        'duration_not_positive' => 'Die angepasste Dauer muss größer als null sein.',
        'appointment_not_in_invoice' => 'Der ausgewählte Termin gehört nicht zu dieser Rechnung.',
    ],
];
