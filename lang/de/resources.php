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

    // Common Actions
    'view' => 'Ansehen',
    'edit' => 'Bearbeiten',
    'delete' => 'Löschen',
];