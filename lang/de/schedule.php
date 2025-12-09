<?php

/**
 * German translations for Schedule Management
 *
 * Deutsche Übersetzungen für die Schichtverwaltung
 */

return [
    // Page titles
    'page_title' => 'Mitarbeiter-Schichtverwaltung',
    'page_heading' => 'Arbeitspläne der Dienstleister verwalten',
    'page_subheading' => 'Wöchentliche Schichten und Arbeitszeiten für Dienstleister konfigurieren',
    'managing_schedule_for' => 'Zeitplan für :name verwalten',
    'navigation_label' => 'Mitarbeiter-Pläne',
    'navigation_group' => 'Mitarbeiterverwaltung',

    // Provider selection
    'select_provider' => 'Mitarbeiter auswählen',
    'choose_provider' => '-- Mitarbeiter wählen --',
    'select_provider_prompt' => 'Wählen Sie einen Mitarbeiter zur Verwaltung',
    'select_provider_desc' => 'Wählen Sie einen Dienstleister aus dem Dropdown-Menü oben, um seinen Wochenplan anzuzeigen und zu verwalten.',

    // Days of week
    'sunday' => 'Sonntag',
    'monday' => 'Montag',
    'tuesday' => 'Dienstag',
    'wednesday' => 'Mittwoch',
    'thursday' => 'Donnerstag',
    'friday' => 'Freitag',
    'saturday' => 'Samstag',

    // Shift management
    'shift' => 'Schicht',
    'shifts' => 'Schichten',
    'add_shift' => 'Schicht hinzufügen',
    'remove_shift' => 'Entfernen',
    'no_shifts' => 'Keine Schichten',
    'start' => 'Start',
    'end' => 'Ende',
    'break_minutes' => 'Pause (Min.)',
    'hours' => 'Stunden',
    'total_hours' => 'Gesamt-Wochenstunden',
    'total_shifts' => 'Gesamt-Schichten',

    // Actions
    'save_schedule' => 'Plan speichern',
    'cancel' => 'Abbrechen',
    'reset' => 'Zurücksetzen',
    'clear_all' => 'Alle löschen',
    'copy' => 'Kopieren',
    'paste' => 'Einfügen',
    'copy_day' => 'Tag kopieren',
    'paste_day' => 'Tag einfügen',
    'copy_week' => 'Woche kopieren',
    'paste_week' => 'Woche einfügen',
    'copy_from_user' => 'Von anderem kopieren',
    'bulk_paste' => 'In mehrere einfügen',
    'clear_day' => 'Tag löschen',
    'apply_to_all_days' => 'Auf alle Tage anwenden',
    'apply_to_selected' => 'Auf ausgewählte anwenden',
    'mark_as_off' => 'Als freien Tag markieren',
    'mark_as_work' => 'Als Arbeitstag markieren',

    // Branch schedule
    'branch' => 'Filiale',
    'branch_hours' => 'Filial-Öffnungszeiten',

    // Legend
    'legend' => 'Legende',
    'shift_block' => 'Arbeitsschicht',
    'day_off_indicator' => 'Freier Tag',
    'view_timeline' => 'Timeline anzeigen',

    // Bulk paste modal
    'bulk_paste_title' => 'Plan für mehrere Mitarbeiter einfügen',
    'bulk_paste_desc' => 'Wählen Sie die Mitarbeiter aus, auf die der kopierte Plan angewendet werden soll:',
    'selected_count' => ':count ausgewählt',

    // Status messages
    'unsaved_changes' => 'Sie haben ungespeicherte Änderungen',
    'clipboard_has_day' => 'Tagesplan in Zwischenablage',
    'clipboard_has_week' => 'Wochenplan in Zwischenablage',

    // Success messages
    'messages' => [
        'saved_successfully' => ':count Schicht(en) erfolgreich gespeichert!',
        'reset_successfully' => 'Plan auf letzten gespeicherten Stand zurückgesetzt',
        'all_cleared' => 'Alle Schichten gelöscht',
        'day_cleared' => ':day Schichten gelöscht',
        'day_copied' => ':day Plan kopiert',
        'day_pasted' => 'Plan in :day eingefügt',
        'week_copied' => 'Wochenplan in Zwischenablage kopiert',
        'week_pasted' => 'Wochenplan erfolgreich eingefügt',
        'copied_from_user' => 'Plan von :name kopiert',
        'day_applied_to_week' => ':day Plan auf alle Tage angewendet',
        'bulk_paste_success' => 'Plan auf :count Mitarbeiter angewendet',
    ],

    // Error messages
    'errors' => [
        'select_provider' => 'Bitte wählen Sie einen Mitarbeiter aus',
        'select_provider_first' => 'Bitte wählen Sie zuerst einen Mitarbeiter aus',
        'start_time_required' => 'Startzeit ist erforderlich',
        'end_time_required' => 'Endzeit ist erforderlich',
        'invalid_time_format' => 'Ungültiges Zeitformat',
        'nothing_to_paste' => 'Nichts zum Einfügen. Kopieren Sie zuerst einen Tag oder eine Woche.',
        'copy_week_first' => 'Bitte kopieren Sie zuerst einen Wochenplan',
        'select_users_to_paste' => 'Bitte wählen Sie mindestens einen Mitarbeiter aus',
        'shift_missing_start' => ':day Schicht #:shift: Startzeit fehlt',
        'shift_missing_end' => ':day Schicht #:shift: Endzeit fehlt',
        'shift_invalid_time_range' => ':day Schicht #:shift: Startzeit (:start) muss vor Endzeit (:end) liegen',
        'shifts_overlap' => ':day: Schicht #:shift1 (:time1) überschneidet sich mit Schicht #:shift2 (:time2)',
        'save_failed' => 'Plan konnte nicht gespeichert werden',
        'bulk_paste_failed' => 'Plan konnte nicht auf ausgewählte Mitarbeiter angewendet werden',
    ],
    'errors_found' => 'Bitte beheben Sie die folgenden Fehler:',

    // Confirmations
    'confirm_reset_title' => 'Plan zurücksetzen?',
    'confirm_reset_message' => 'Dies verwirft alle ungespeicherten Änderungen und lädt den letzten gespeicherten Plan.',
    'confirm_reset' => 'Zurücksetzen',
    'confirm_clear_title' => 'Alle Schichten löschen?',
    'confirm_clear_message' => 'Dies entfernt alle Schichten von allen Tagen. Sie können vor dem Speichern noch abbrechen.',
    'confirm_clear' => 'Alle löschen',
    'confirm_remove_shift' => 'Sind Sie sicher, dass Sie diese Schicht entfernen möchten?',

    // Info section
    'info_title' => 'Planverwaltung',
    'info_description' => 'Arbeitszeiten und Schichten für Ihre Dienstleister verwalten',
    'feature_shifts_title' => 'Mehrere Schichten',
    'feature_shifts_desc' => 'Mehrere Schichten pro Tag mit flexibler Zeitplanung hinzufügen',
    'feature_copy_title' => 'Kopieren & Einfügen',
    'feature_copy_desc' => 'Pläne zwischen Tagen oder Mitarbeitern kopieren',
    'feature_validation_title' => 'Intelligente Validierung',
    'feature_validation_desc' => 'Automatische Überschneidungserkennung verhindert Konflikte',

    // Help section
    'help_title' => 'Hilfe & Tipps',
    'help_how_to_use' => 'Verwendung',
    'help_step_1' => 'Wählen Sie einen Mitarbeiter aus dem Dropdown-Menü',
    'help_step_2' => 'Fügen Sie Schichten zu jedem Tag hinzu, indem Sie auf "Schicht hinzufügen" klicken',
    'help_step_3' => 'Legen Sie Start- und Endzeiten für jede Schicht fest',
    'help_step_4' => 'Optional: Pausenzeit in Minuten hinzufügen',
    'help_step_5' => 'Klicken Sie auf "Plan speichern", um Ihre Änderungen zu speichern',

    'help_tips_title' => 'Tipps',
    'help_tip_1' => 'Verwenden Sie "Auf alle Tage anwenden", um den Tagesplan schnell auf die ganze Woche zu kopieren',
    'help_tip_2' => 'Das System erkennt überschneidende Schichten automatisch',
    'help_tip_3' => 'Sie können Pläne zwischen verschiedenen Mitarbeitern kopieren',
    'help_tip_4' => 'Markieren Sie eine Schicht als "Freier Tag", wenn der Mitarbeiter an dieser Schicht nicht arbeitet',

    'help_shortcuts_title' => 'Schnellaktionen',
    'help_shortcut_copy_day' => 'Schichten von einem Tag kopieren',
    'help_shortcut_apply_all' => 'Tag auf ganze Woche anwenden',
    'help_shortcut_bulk' => 'Auf mehrere Mitarbeiter gleichzeitig anwenden',
];
