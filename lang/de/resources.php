<?php

return [
    'language' => [
        'label' => 'Sprache',
        'plural_label' => 'Sprachen',
        'navigation_label' => 'Sprachen',

        // Table columns
        'name' => 'Name',
        'native_name' => 'Nativer Name',
        'code' => 'Code',
        'order' => 'Reihenfolge',
        'status' => 'Status',
        'default' => 'Standard',
        'created_at' => 'Erstellt am',
        'updated_at' => 'Aktualisiert am',
        'deleted_at' => 'Gelöscht am',

        // Form tabs
        'basic_info' => 'Grundlegende Informationen',
        'translations' => 'Übersetzungen',
        'settings' => 'Einstellungen',

        // Form sections
        'language_details' => 'Sprachdetails',
        'language_details_desc' => 'Geben Sie die Hauptinformationen über die Sprache ein',
        'language_settings' => 'Spracheinstellungen',
        'language_settings_desc' => 'Spracheinstellungen und Anzeigereihenfolge konfigurieren',

        // Form fields
        'name_placeholder' => 'z.B. Englisch',
        'name_helper' => 'Der Name der Sprache in Englisch',
        'native_name_placeholder' => 'z.B. English',
        'native_name_helper' => 'Der Name der Sprache in ihrer nativen Form',
        'code_placeholder' => 'z.B. en',
        'code_helper' => 'ISO-Sprachcode (z.B. en, ar, de)',
        'order_helper' => 'Niedrigere Zahlen erscheinen zuerst in Listen',
        'active' => 'Aktiv',
        'active_helper' => 'Inaktive Sprachen sind nicht zur Auswahl verfügbar',
        'default_language' => 'Standardsprache',
        'default_language_helper' => 'Nur eine Sprache sollte als Standard festgelegt werden',

        // Translations
        'translation_for_language' => 'Übersetzung für :language',
        'language_id' => 'Sprach-ID',
        'language_code' => 'Sprachcode',
        'language_code_helper' => 'Wird automatisch basierend auf der ausgewählten Sprache ausgefüllt',
        'translated_name' => 'Sprachname (übersetzt)',
        'translated_name_placeholder' => 'Übersetzten Sprachnamen eingeben',
        'translated_name_helper' => 'Geben Sie den Sprachnamen in der ausgewählten Sprache ein',
        'translated_native_name' => 'Nativer Name (übersetzt)',
        'translated_native_name_placeholder' => 'Übersetzten nativen Namen eingeben',
        'translated_native_name_helper' => 'Geben Sie den nativen Namen in der ausgewählten Sprache ein',
    ],

    'service_category' => [
        'label' => 'Servicekategorie',
        'plural_label' => 'Servicekategorien',
        'navigation_label' => 'Servicekategorien',

        // Table columns
        'name' => 'Kategoriename',
        'description' => 'Beschreibung',
        'services_count' => 'Anzahl der Services',
        'image' => 'Kategoriebild',
        'sort_order' => 'Sortierreihenfolge',
        'status' => 'Status',
        'created_at' => 'Erstellt am',
        'updated_at' => 'Aktualisiert am',

        // Form tabs
        'basic_info' => 'Grundlegende Informationen',
        'translations' => 'Übersetzungen',
        'settings' => 'Einstellungen',

        // Form sections
        'category_details' => 'Kategoriedetails',
        'category_details_desc' => 'Geben Sie die Hauptinformationen über die Kategorie ein',
        'visual_settings' => 'Visuelle Einstellungen',
        'visual_settings_desc' => 'Bild für diese Kategorie hochladen',
        'visibility_settings' => 'Sichtbarkeit & Anzeige',
        'visibility_settings_desc' => 'Steuern Sie, wie und wo diese Kategorie angezeigt wird',

        // Form fields
        'name_placeholder' => 'z.B. Haarstyling-Services',
        'name_helper' => 'Geben Sie einen klaren und beschreibenden Namen für die Kategorie ein',
        'description_placeholder' => 'Beschreiben Sie, was diese Kategorie beinhaltet...',
        'description_helper' => 'Geben Sie detaillierte Informationen über die Kategorie an',
        'image_helper' => 'Laden Sie ein hochwertiges Bild hoch (JPG oder PNG, max. 2MB)',
        'sort_order_helper' => 'Niedrigere Zahlen erscheinen zuerst',
        'active' => 'Aktiv',
        'active_helper' => 'Inaktive Kategorien werden nicht im System angezeigt',

        // Translations
        'translation_for_language' => 'Übersetzung für :language',
        'language_id' => 'Sprach-ID',
        'language_code' => 'Sprachcode',
        'language_code_helper' => 'Automatisch ausgefüllt basierend auf der ausgewählten Sprache',
        'translated_name' => 'Kategoriename (übersetzt)',
        'translated_name_placeholder' => 'Übersetzten Kategorienamen eingeben',
        'translated_name_helper' => 'Geben Sie den Kategorienamen in der ausgewählten Sprache ein',
        'translated_description' => 'Beschreibung (übersetzt)',
        'translated_description_placeholder' => 'Übersetzte Beschreibung eingeben',
        'translated_description_helper' => 'Geben Sie detaillierte Informationen in der ausgewählten Sprache an',

        // Notifications
        'created_notification' => 'Servicekategorie erfolgreich erstellt',
        'updated_notification' => 'Servicekategorie erfolgreich aktualisiert',
        'deleted_notification' => 'Servicekategorie erfolgreich gelöscht',
        'translations_saved' => 'Übersetzungen gespeichert',
        'translations_saved_message' => ':count Übersetzung(en) erfolgreich gespeichert',
        'translations_updated' => 'Übersetzungen aktualisiert',
        'translations_updated_message' => ':count Übersetzung(en) erfolgreich aktualisiert',

        // Filters
        'active_only' => 'Nur aktive',
        'inactive_only' => 'Nur inaktive',
        'all' => 'Alle Kategorien',

        // Statistics
        'total_services' => 'Gesamtzahl der Services',
        'active_services' => 'Aktive Services',
        'featured_services' => 'Hervorgehobene Services',
        'services_in_category' => 'Services in dieser Kategorie',
    ],

    'salon_setting' => [
        'label' => 'Salon-Einstellung',
        'plural_label' => 'Salon-Einstellungen',
        'navigation_label' => 'Salon-Einstellungen',

        // Table columns
        'key' => 'Einstellungsschlüssel',
        'value' => 'Wert',
        'type' => 'Typ',
        'description' => 'Beschreibung',
        'branch' => 'Filiale',
        'setting_group' => 'Gruppe',
        'created_at' => 'Erstellt am',
        'updated_at' => 'Aktualisiert am',

        // Form sections
        'setting_information' => 'Einstellungsinformationen',
        'setting_information_desc' => 'Einstellungsdetails und Metadaten anzeigen',
        'setting_value' => 'Einstellungswert',
        'setting_value_desc' => 'Wert dieser Einstellung ändern',

        // Form fields
        'key_helper' => 'Eindeutiger Bezeichner für diese Einstellung (kann nicht geändert werden)',
        'description_helper' => 'Was diese Einstellung im System steuert',
        'branch_helper' => 'Leer lassen für globale Einstellungen oder eine bestimmte Filiale auswählen',
        'global_setting' => 'Globale Einstellung (Alle Filialen)',
        'type_helper' => 'Datentyp dieses Einstellungswerts',
        'value_placeholder' => 'Einstellungswert eingeben',

        // Setting groups
        'group_general' => 'Allgemein',
        'group_booking' => 'Buchung',
        'group_payment' => 'Zahlung',
        'group_notifications' => 'Benachrichtigungen',
        'group_loyalty' => 'Treue',
        'group_contact' => 'Kontakt',

        // Types
        'type_string' => 'Text (String)',
        'type_integer' => 'Ganzzahl (Integer)',
        'type_boolean' => 'Ja/Nein (Boolean)',
        'type_json' => 'JSON-Daten',
        'type_decimal' => 'Dezimalzahl',

        // Type helpers
        'string_helper' => 'Textwert eingeben (z.B. USD, email@example.com)',
        'integer_helper' => 'Ganzzahl eingeben (z.B. 24, 100)',
        'decimal_helper' => 'Dezimalzahl für Prozentsätze eingeben',
        'boolean_helper' => 'Ein für wahr, Aus für falsch',
        'json_helper' => 'Gültiges JSON-Array oder -Objekt eingeben (z.B. ["option1", "option2"])',

        // Notifications
        'created_notification' => 'Einstellung erfolgreich erstellt',
        'updated_notification' => 'Einstellung erfolgreich aktualisiert',
        'deleted_notification' => 'Einstellung erfolgreich gelöscht',

        // Filters
        'filter_by_group' => 'Nach Gruppe filtern',
        'filter_by_branch' => 'Nach Filiale filtern',
        'all_settings' => 'Alle Einstellungen',
        'global_only' => 'Nur globale Einstellungen',
        'branch_specific' => 'Filialspezifisch',

        // Infolist
        'current_value' => 'Aktueller Wert dieser Einstellung',
        'value_copied' => 'Wert kopiert!',
        'metadata' => 'Metadaten',
    ],

    'reason_leave' => [
        'label' => 'Abwesenheitsgrund',
        'plural_label' => 'Abwesenheitsgründe',
        'navigation_label' => 'Abwesenheitsgründe',

        // Table columns
        'name' => 'Grundname',
        'description' => 'Beschreibung',
        'translations' => 'Übersetzungen',
        'usage_count' => 'Verwendung',
        'times_used' => 'Wie oft dieser Grund verwendet wurde',
        'created_at' => 'Erstellt am',
        'updated_at' => 'Aktualisiert am',

        // Filters
        'has_translations' => 'Hat Übersetzungen',
        'frequently_used' => 'Häufig verwendet (5+)',
        'unused' => 'Noch nicht verwendet',

        // Actions
        'edit' => 'Bearbeiten',
        'delete_selected' => 'Ausgewählte löschen',

        // Empty state
        'no_reasons' => 'Keine Abwesenheitsgründe',
        'no_reasons_desc' => 'Erstellen Sie Ihren ersten Abwesenheitsgrund, um zu beginnen.',

        // Form sections
        'basic_info' => 'Grundlegende Informationen',
        'basic_info_desc' => 'Geben Sie die Hauptinformationen zum Abwesenheitsgrund ein',
        'translations_section' => 'Übersetzungen',
        'translations_section_desc' => 'Fügen Sie Übersetzungen für verschiedene Sprachen hinzu',

        // Form fields
        'name_placeholder' => 'z.B. Krankenstand, Urlaub, Persönlicher Urlaub',
        'name_helper' => 'Der Name des Abwesenheitsgrundes',
        'description_placeholder' => 'Geben Sie eine detaillierte Beschreibung ein',
        'description_helper' => 'Erklären Sie, wann dieser Grund verwendet werden sollte',
        'translation_for_language' => 'Übersetzung für :language',

        // Notifications
        'created_notification' => 'Abwesenheitsgrund erfolgreich erstellt',
        'updated_notification' => 'Abwesenheitsgrund erfolgreich aktualisiert',
        'deleted_notification' => 'Abwesenheitsgrund erfolgreich gelöscht',
        'translations_saved' => 'Übersetzungen erfolgreich gespeichert',

        // Infolist
        'no_translations' => 'Keine Übersetzungen verfügbar',
        'usage_statistics' => 'Nutzungsstatistiken',
        'usage_statistics_desc' => 'Wie oft dieser Grund verwendet wurde',
        'total_usage' => 'Gesamtnutzung',
        'single_day_leaves' => 'Einzeltagesurlaub',
        'multi_day_leaves' => 'Mehrtägiger Urlaub',
        'metadata' => 'Metadaten',
    ],

    // Provider Scheduled Work
    'provider_scheduled_work' => [
        'label' => 'Arbeitsplan',
        'plural_label' => 'Arbeitspläne',
        'navigation_label' => 'Mitarbeiter-Arbeitspläne',

        // Table columns
        'provider' => 'Dienstleister',
        'provider_name' => 'Name des Dienstleisters',
        'day_of_week' => 'Tag',
        'start_time' => 'Startzeit',
        'end_time' => 'Endzeit',
        'working_hours' => 'Arbeitszeiten',
        'is_work_day' => 'Arbeitstag',
        'break_minutes' => 'Pause',
        'break_duration' => 'Pausendauer',
        'is_active' => 'Aktiv',
        'status' => 'Status',
        'created_at' => 'Erstellt am',
        'updated_at' => 'Zuletzt aktualisiert',

        // Days
        'sunday' => 'Sonntag',
        'monday' => 'Montag',
        'tuesday' => 'Dienstag',
        'wednesday' => 'Mittwoch',
        'thursday' => 'Donnerstag',
        'friday' => 'Freitag',
        'saturday' => 'Samstag',

        // Status
        'work_day' => 'Arbeitstag',
        'day_off' => 'Freier Tag',
        'active' => 'Aktiv',
        'inactive' => 'Inaktiv',

        // Filters
        'filter_by_provider' => 'Nach Dienstleister filtern',
        'filter_by_day' => 'Nach Tag filtern',
        'work_days_only' => 'Nur Arbeitstage',
        'days_off_only' => 'Nur freie Tage',
        'active_only' => 'Nur Aktive',
        'inactive_only' => 'Nur Inaktive',

        // Helpers
        'minutes' => 'Minuten',
        'hours' => 'Stunden',
        'no_break' => 'Keine Pause',
        'total_hours' => 'Gesamtstunden',
        'effective_hours' => 'Effektive Stunden',

        // Summary Fields
        'work_days_count' => 'Arbeitstage',
        'off_days_count' => 'Freie Tage',
        'weekly_hours' => 'Wochenstunden',
        'time_offs_count' => 'Abwesenheiten',
        'upcoming_time_offs' => 'Bevorstehende Abwesenheiten',
        'total_time_offs' => 'Gesamte Abwesenheiten',
        'schedule_status' => 'Plan-Status',

        // Filter Labels
        'filter_by_work_days' => 'Nach Arbeitstagen filtern',
        'no_work_days' => 'Keine Arbeitstage',
        'days' => 'Tage',
        'has_schedule' => 'Plan-Status',
        'with_schedule' => 'Mit Plan',
        'without_schedule' => 'Ohne Plan',
        'has_time_offs' => 'Abwesenheits-Status',
        'with_time_offs' => 'Mit Abwesenheiten',
        'without_time_offs' => 'Ohne Abwesenheiten',

        // Actions
        'view_timeline' => 'Timeline anzeigen',
        'view_timeline_tooltip' => 'Visuelle Zeitplan-Zeitleiste des Mitarbeiters anzeigen',
        'manage_schedule' => 'Plan bearbeiten',
        'manage_schedule_tooltip' => 'Arbeitsplan dieses Mitarbeiters bearbeiten und verwalten',

        // Form Tabs
        'provider_info' => 'Mitarbeiterinformationen',
        'weekly_schedule' => 'Wochenplan',
        'instructions' => 'Anleitungen',
        'help' => 'Hilfe',

        // Provider Selection
        'select_provider' => 'Mitarbeiter auswählen',
        'select_provider_desc' => 'Wählen Sie einen Mitarbeiter aus, um dessen Wochenarbeitsplan zu verwalten',
        'select_provider_placeholder' => '-- Mitarbeiter auswählen --',
        'select_provider_helper' => 'Sie können nach Name oder E-Mail-Adresse suchen',
        'no_provider_selected' => 'Kein Mitarbeiter ausgewählt',
        'no_provider_selected_desc' => 'Bitte wählen Sie einen Mitarbeiter aus dem ersten Tab aus, um die Zeitleiste anzuzeigen',
        'go_to_provider_tab' => 'Gehen Sie zum Tab Mitarbeiterinformationen',
        'select_provider_first' => 'Bitte wählen Sie zuerst einen Mitarbeiter aus, um die Zeitleiste anzuzeigen',

        // Timeline Section
        'schedule_timeline' => 'Zeitplan-Zeitleiste',
        'schedule_timeline_desc' => 'Visuelle Darstellung des Wochenplans mit Schichten und Stunden',

        // Instructions
        'how_to_use' => 'Verwendung',
        'how_to_use_desc' => 'Schritt-für-Schritt-Anleitung zur Verwaltung von Arbeitsplänen',
        'instructions_intro_title' => 'Willkommen im Arbeitsplan-Verwaltungssystem',
        'instructions_intro_text' => 'Dieses Tool hilft Ihnen, Arbeitszeiten visuell und einfach zu verwalten',

        // Steps
        'step_1' => 'Wählen Sie einen Mitarbeiter aus der Dropdown-Liste im ersten Tab',
        'step_2' => 'Gehen Sie zum Tab "Wochenplan", um den aktuellen Plan des Mitarbeiters anzuzeigen',
        'step_3' => 'Sehen Sie sich die Schichten auf einer 24-Stunden-Zeitleiste für jeden Tag an',
        'step_4' => 'Um den Plan zu bearbeiten, verwenden Sie die Hauptseite zur Planverwaltung',

        // Timeline Legend
        'timeline_legend' => 'Farblegende',
        'shift_block' => 'Arbeitsschicht',
        'branch_hours_bg' => 'Filial-Öffnungszeiten',
        'day_off_indicator' => 'Freier Tag',

        // Tips
        'tips_title' => 'Hilfreiche Tipps',
        'tip_1' => 'Sie können Schichtdetails sehen, indem Sie mit der Maus darüber fahren',
        'tip_2' => 'Der blaue Hintergrund stellt die offiziellen Filial-Öffnungszeiten dar',
        'tip_3' => 'Verschiedene Schichtfarben helfen, sie zu unterscheiden',

        // Important Note
        'important_note' => 'Hinweis: Dies ist nur eine visuelle Anzeige. Um den Plan zu bearbeiten, verwenden Sie die Planverwaltungsseite oder klicken Sie auf die Schaltfläche "Plan verwalten" in der Mitarbeitertabelle.',

        // Form Sections
        'basic_information' => 'Grundinformationen',
        'basic_information_description' => 'Mitarbeiter-, Tages- und Statusinformationen',
        'work_schedule' => 'Arbeitsplan',
        'work_schedule_description' => 'Startzeit, Endzeit und Pausendauer festlegen',
        'additional_notes' => 'Zusätzliche Notizen',
        'additional_notes_description' => 'Besondere Notizen für diese Schicht',

        // Form Fields
        'form_select_provider' => 'Anbieter auswählen',
        'form_provider_helper' => 'Wählen Sie den Mitarbeiter aus, dessen Schicht Sie bearbeiten möchten',
        'form_provider_required' => 'Anbieter ist erforderlich',
        'select_day' => 'Tag auswählen',
        'day_helper' => 'Wählen Sie den Wochentag für diese Schicht',
        'day_required' => 'Tag ist erforderlich',
        'is_active_helper' => 'Inaktive Schichten erscheinen nicht im Buchungssystem',
        'start_time_placeholder' => '09:00',
        'start_time_helper' => 'Arbeitsbeginn',
        'start_time_required' => 'Startzeit ist erforderlich',
        'end_time_placeholder' => '17:00',
        'end_time_helper' => 'Arbeitsende',
        'end_time_required' => 'Endzeit ist erforderlich',
        'break_minutes_placeholder' => '30',
        'break_minutes_helper' => 'Anzahl der Pausenminuten während der Schicht',
        'break_minutes_numeric' => 'Pausenminuten müssen eine Zahl sein',
        'break_minutes_min' => 'Pausenminuten müssen 0 oder mehr sein',
        'break_minutes_max' => 'Pausenminuten müssen weniger als 480 Minuten sein',
        'is_work_day_helper' => 'Geben Sie an, ob dies ein Arbeitstag oder ein freier Tag ist',
        'notes' => 'Notizen',
        'notes_placeholder' => 'Besondere Notizen für diese Schicht hinzufügen...',
        'notes_helper' => 'Optionale Notizen (max. 1000 Zeichen)',

        // Validation Messages
        'validation' => [
            'end_time_must_differ' => 'Endzeit muss sich von der Startzeit unterscheiden',
            'break_exceeds_duration' => 'Pausenminuten können nicht größer oder gleich der Schichtdauer sein',
            'shift_overlap' => 'Konflikt mit einer anderen Schicht am :day von :start bis :end',
        ],

        // Weekly Schedule Form
        'shifts' => 'Schichten',
        'shift' => 'Schicht',
        'add_shift' => 'Schicht hinzufügen',
        'summary' => 'Zusammenfassung',
        'summary_description' => 'Live-Überblick über die Wochenlast.',
        'total_working_minutes' => 'Arbeitszeit gesamt (Woche)',
        'active_days_count' => 'Tage mit aktiven Schichten',
        'total_shifts_count' => 'Anzahl Schichten',
        'managing_schedule' => 'Arbeitsplan für den ausgewählten Mitarbeiter verwalten',
        'weekly_schedule_description' => 'Arbeitszeiten für jeden Wochentag festlegen',
        'edit_schedule_for' => 'Plan bearbeiten für :name',
        'edit_weekly_schedule_description' => 'Wöchentliche Arbeitszeiten und Pausen für alle Tage verwalten',
        'back_to_list' => 'Zurück zur Liste',
        'save_schedule' => 'Plan speichern',
        'cancel' => 'Abbrechen',
        'schedule_saved' => 'Plan gespeichert',
        'schedule_saved_successfully' => 'Der Arbeitsplan wurde erfolgreich gespeichert',
        'save_error' => 'Speicherfehler',
        'validation_error' => 'Validierungsfehler',
    ],

    // Common Actions
    'view' => 'Ansehen',
    'edit' => 'Bearbeiten',
    'delete' => 'Löschen',
];
