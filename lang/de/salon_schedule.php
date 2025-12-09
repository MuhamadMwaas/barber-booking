<?php

return [
    // Navigation & Page Titles
    'page_title' => 'Salon-Zeitpläne verwalten',
    'page_heading' => 'Salon-Arbeitszeiten verwalten',
    'page_subheading' => 'Öffnungs- und Schließzeiten für jede Filiale während der Woche festlegen',
    'navigation_label' => 'Salon-Zeitpläne',
    'navigation_group' => 'Salon-Verwaltung',

    // Branch Selection
    'select_branch' => 'Filiale auswählen',
    'select_branch_description' => 'Wählen Sie eine Filiale aus, um ihre Arbeitszeiten zu verwalten',
    'choose_branch' => 'Wählen Sie eine Filiale...',
    'branch' => 'Filiale',
    'branch_info' => 'Filial-Informationen',
    'managing_schedule' => 'Wöchentlichen Zeitplan für die ausgewählte Filiale verwalten',

    // Weekly Schedule
    'weekly_schedule' => 'Wochenplan',
    'weekly_schedule_description' => 'Öffnungs- und Schließzeiten für jeden Wochentag festlegen',
    'manage_opening_hours' => 'Öffnungs- und Schließzeiten für jeden Tag verwalten',

    // Days of the Week
    'days' => [
        'sunday' => 'Sonntag',
        'monday' => 'Montag',
        'tuesday' => 'Dienstag',
        'wednesday' => 'Mittwoch',
        'thursday' => 'Donnerstag',
        'friday' => 'Freitag',
        'saturday' => 'Samstag',
    ],

    // Time Fields
    'is_open' => 'Geöffnet',
    'open_time' => 'Öffnungszeit',
    'close_time' => 'Schließzeit',
    'working_hours' => 'Arbeitszeiten',
    'open' => 'Geöffnet',
    'closed' => 'Geschlossen',

    // Summary
    'summary' => 'Wochenzusammenfassung',
    'summary_description' => 'Übersicht der wöchentlichen Arbeitszeiten',
    'total_weekly_hours' => 'Wöchentliche Gesamtstunden',
    'open_days_count' => 'Öffnungstage',
    'average_daily_hours' => 'Durchschnittliche Tagesstunden',
    'days_unit' => 'Tage',

    // Actions
    'save_schedule' => 'Zeitplan speichern',
    'schedule_saved' => 'Zeitplan gespeichert',
    'schedule_saved_successfully' => 'Salon-Zeitplan wurde erfolgreich gespeichert',
    'save_error' => 'Fehler beim Speichern des Zeitplans',
    'please_select_branch' => 'Bitte wählen Sie zuerst eine Filiale aus',

    // Validation
    'validation' => [
        'close_time_must_differ' => 'Schließzeit muss sich von der Öffnungszeit unterscheiden',
    ],
    'open_time_required' => 'Öffnungszeit ist erforderlich für :day',
    'close_time_required' => 'Schließzeit ist erforderlich für :day',
];
