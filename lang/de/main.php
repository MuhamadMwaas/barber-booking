<?php

return [
    'validation' => [
        'failed' => 'Es wurden ungueltige Daten uebermittelt.',
    ],
    'cancel' => 'Abbrechen',
    'settings' => [
        'updated' => 'Einstellung erfolgreich aktualisiert.',
        'not_found' => 'Einstellung nicht gefunden.',
    ],
    'appointment' => [
        'validation' => [
            'appointment_id' => [
                'required' => 'Ein Termin ist erforderlich.',
                'integer' => 'Die Termin-ID ist ungueltig.',
                'exists' => 'Der Termin wurde nicht gefunden oder Sie haben keinen Zugriff darauf.',
            ],
            'offset_hours' => [
                'required' => 'Bitte waehlen Sie, wann Sie erinnert werden moechten.',
                'integer' => 'Die Vorlaufzeit muss eine ganze Stundenzahl sein.',
                'in' => 'Diese Vorlaufzeit gehoert nicht zu den verfuegbaren Optionen.',
                'conflict' => 'Senden Sie entweder offset_hours oder remind_at, nicht beides.',
                'too_late' => 'Diese Vorlaufzeit liegt fuer diesen Termin bereits in der Vergangenheit. Bitte waehlen Sie eine kuerzere.',
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
        'success' => [
            'reminder_created' => 'Erinnerung erfolgreich erstellt.',
            'reminder_deleted' => 'Erinnerung deaktiviert.',
        ],
        'errors' => [
            'cancelled' => 'Fuer einen stornierten Termin kann keine Erinnerung erstellt werden.',
            'past' => 'Fuer einen vergangenen Termin kann keine Erinnerung erstellt werden.',
            'not_found' => 'Termin nicht gefunden.',
            'reminder_not_found' => 'Fuer diesen Termin gibt es keine aktive Erinnerung.',
        ],
        'server_error' => 'Beim Erstellen der Erinnerung ist ein Fehler aufgetreten.',
    ],
];
