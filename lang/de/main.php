<?php

return [
    'validation' => [
        'failed' => 'Es wurden ungueltige Daten uebermittelt.',
    ],
    'cancel' => 'Abbrechen',
    'appointment' => [
        'validation' => [
            'appointment_id' => [
                'required' => 'Ein Termin ist erforderlich.',
                'integer' => 'Die Termin-ID ist ungueltig.',
                'exists' => 'Der Termin wurde nicht gefunden oder Sie haben keinen Zugriff darauf.',
            ],
            'remind_at' => [
                'required' => 'Ein Erinnerungszeitpunkt ist erforderlich.',
                'date' => 'Das Format des Erinnerungszeitpunkts ist ungueltig.',
                'after' => 'Der Erinnerungszeitpunkt muss in der Zukunft liegen.',
                'before_appointment' => 'Der Erinnerungszeitpunkt muss vor dem Termin liegen.',
            ],
            'appointment_cancelled' => 'Fuer einen stornierten Termin kann keine Erinnerung erstellt werden.',
            'appointment_past' => 'Fuer einen vergangenen oder bereits begonnenen Termin kann keine Erinnerung erstellt werden.',
        ],
        'errors' => [
            'cancelled' => 'Fuer einen stornierten Termin kann keine Erinnerung erstellt werden.',
            'past' => 'Fuer einen vergangenen Termin kann keine Erinnerung erstellt werden.',
            'not_found' => 'Der Termin wurde nicht gefunden.',
        ],
        'success' => [
            'reminder_created' => 'Die Erinnerung wurde erfolgreich erstellt.',
        ],
        'server_error' => 'Beim Erstellen der Erinnerung ist ein Fehler aufgetreten.',
    ],
];
