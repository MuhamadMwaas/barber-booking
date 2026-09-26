<?php

// Messages raised by InvoiceFinalizationService — the one on-site payment path
// shared by the StaffDashboard and the Filament payment screens.
return [
    'errors' => [
        'method_not_found' => 'The payment method does not exist or is not active.',
        'method_not_on_site' => 'The payment method must be cash or card in the salon.',
        'no_appointments' => 'There are no appointments linked to this invoice.',
        'cancelled_or_no_show' => 'An invoice containing a cancelled or no-show appointment cannot be collected.',
        'invoice_not_positive' => 'An invoice without a positive amount cannot be collected.',
        'amount_not_positive' => 'The payment amount must be greater than zero.',
        'duration_not_positive' => 'The adjusted duration must be greater than zero.',
        'appointment_not_in_invoice' => 'The selected appointment is not part of this invoice.',
    ],
];
