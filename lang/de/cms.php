<?php

return [

    /* ── General ── */
    'page_not_found' => 'Seite nicht gefunden.',

    /* ── Language labels ── */
    'lang' => [
        'ar' => 'Arabisch',
        'en' => 'Englisch',
        'de' => 'Deutsch',
    ],

    /* ── Block type labels ── */
    'blocks' => [
        'heading'         => 'Überschrift',
        'paragraph'       => 'Absatz',
        'title_paragraph' => 'Titel + Absatz',
        'ordered_list'    => 'Geordnete Liste',
        'unordered_list'  => 'Ungeordnete Liste',
        'divider'         => 'Trennlinie',
        'link'            => 'Link',
        'image'           => 'Bild',
        'warning_box'     => 'Warnbox',
    ],

    /* ── Block section labels ── */
    'block_sections' => [
        'display_options' => 'Anzeigeoptionen',
        'translations'    => 'Übersetzungen',
    ],

    /* ── Shared field labels ── */
    'fields' => [
        'is_active'        => 'Aktiv',
        'text'             => 'Text',
        'title'            => 'Titel',
        'heading_level'    => 'Überschriftenebene',
        'alignment'        => 'Ausrichtung',
        'color'            => 'Farbe',
        'background_color' => 'Hintergrundfarbe',
        'orientation'      => 'Orientierung',
        'size'             => 'Stärke',
        'items'            => 'Elemente',
        'item'             => 'Element',
        'label'            => 'Beschriftung',
        'url'              => 'URL',
        'target'           => 'Öffnen in',
        'image'            => 'Bild',
        'alt'              => 'Alt-Text',
    ],

    /* ── Alignment options ── */
    'alignment' => [
        'auto'    => 'Automatisch (nach Sprache)',
        'left'    => 'Links',
        'right'   => 'Rechts',
        'center'  => 'Zentriert',
        'justify' => 'Blocksatz',
    ],

    /* ── Orientation options ── */
    'orientation' => [
        'horizontal' => 'Horizontal',
        'vertical'   => 'Vertikal',
    ],

    /* ── Size options ── */
    'size' => [
        'sm' => 'Dünn',
        'md' => 'Mittel',
        'lg' => 'Dick',
    ],

    /* ── Link target options ── */
    'target' => [
        'same'     => 'Gleicher Bildschirm / WebView',
        'external' => 'Externer Browser',
    ],

    /* ── Filament Resource ── */
    'resource' => [
        'navigation_label'   => 'App-Seiten',
        'model_label'        => 'Seite',
        'plural_model_label' => 'App-Seiten',

        'section_page_info'      => 'Seitenidentität',
        'section_page_info_desc' => 'Der interne Name und der Slug bestimmen, wie die Seite über die API angefordert wird.',
        'section_blocks'         => 'Seiteninhalt',
        'section_blocks_desc'    => 'Blöcke hinzufügen und in der Reihenfolge sortieren, in der sie in der App erscheinen sollen.',

        'field_name'             => 'Interner Name',
        'field_name_placeholder' => 'z.B. Datenschutzerklärung',
        'field_slug_hint'        => 'Wird verwendet, um diese Seite über die API anzufordern — nicht nach der Veröffentlichung ändern',
        'field_is_active'        => 'Seite aktiv',
        'field_is_active_hint'   => 'Inaktive Seiten geben 404 zurück und sind in der App nicht sichtbar',
        'field_blocks'           => 'Blöcke',
        'add_block'              => '+ Block hinzufügen',

        'api_preview_label'    => 'API-Endpunkt',
        'api_slug_placeholder' => 'ihr-seiten-slug',
        'api_lang_options'     => 'Unterstützte Sprachen',

        'blocks_hint' => 'Blöcke ziehen zum Neuanordnen • Klicken zum Auf-/Zuklappen • Clone-Schaltfläche zum Duplizieren',

        'col_name'       => 'Name',
        'col_is_active'  => 'Aktiv',
        'col_updated_at' => 'Zuletzt geändert',
        'slug_copied'    => 'Slug kopiert',

        'action_preview_api' => 'API-Antwort',
        'action_preview'     => 'Vorschau',

        // Vorschau-Seite
        'preview_label'           => 'App-Vorschau',
        'preview_back'            => 'Zurück zur Admin',
        'preview_edit'            => 'Seite bearbeiten',
        'preview_language'        => 'Sprache',
        'preview_stats'           => 'Statistiken',
        'preview_total_blocks'    => 'Blöcke gesamt',
        'preview_active_blocks'   => 'Aktiv',
        'preview_inactive_blocks' => 'Inaktiv',
        'preview_no_blocks'       => 'Noch keine Blöcke vorhanden',
        'preview_status_active'   => 'Aktiv',
        'preview_status_inactive' => 'Inaktiv',
    ],
];
