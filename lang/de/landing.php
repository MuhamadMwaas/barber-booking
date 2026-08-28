<?php

/*
|--------------------------------------------------------------------------
| Landingpage — Oberflächentexte
|--------------------------------------------------------------------------
|
| Enthält zwei Gruppen: die wenigen Texte, die die öffentliche Seite selbst
| ausgibt (Barrierefreiheits-Labels, Formular-Fallbacks), und sämtliche
| Beschriftungen des „Landingpage“-Editors im Adminbereich. Alle Inhalte, die
| Besucher wirklich lesen, stammen aus `landing_sections`, nicht von hier.
|
*/

return [

    /* ── Öffentliche Seite ───────────────────────────────────────── */

    'skip_to_content'    => 'Zum Inhalt springen',
    'primary_navigation' => 'Hauptnavigation',
    'footer_navigation'  => 'Fußzeilen-Navigation',
    'footer_services'    => 'Leistungen',
    'open_menu'          => 'Menü öffnen',
    'change_language'    => 'Sprache wechseln',
    'email_address'      => 'E-Mail-Adresse',
    'subscribe'          => 'Abonnieren',
    'qr_alt'             => 'QR-Code zum Herunterladen der App',
    'app_coming_soon'    => 'Die App ist in Kürze verfügbar. Rufen Sie uns bis dahin gerne für einen Termin an.',

    /* ── Admin: Seitenrahmen ─────────────────────────────────────── */

    'navigation_label' => 'Landingpage',
    'page_title'       => 'Landingpage',
    'page_subheading'  => 'Alles, was die öffentliche Website unter / und /app zeigt.',

    'save'          => 'Änderungen speichern',
    'saved_title'   => 'Landingpage aktualisiert',
    'saved_body'    => 'Die öffentliche Seite wurde aktualisiert.',
    'preview'       => 'Seite öffnen',
    'preview_app'   => 'App-Seite öffnen',
    'clear_cache'   => 'Cache leeren',
    'cache_cleared' => 'Zwischengespeicherte Inhalte gelöscht.',

    'section_enabled'      => 'Abschnitt sichtbar',
    'section_enabled_hint' => 'Ausschalten blendet den Abschnitt auf der Website aus, ohne die Inhalte zu löschen.',
    'sort_order'           => 'Position auf der Seite',
    'sort_order_hint'      => 'Kleinere Zahlen erscheinen weiter oben.',

    /* ── Admin: Tabs ─────────────────────────────────────────────── */

    'tabs' => [
        'brand'    => 'Marke',
        'seo'      => 'SEO',
        'navbar'   => 'Navigation',
        'hero'     => 'Hero',
        'services' => 'Leistungen',
        'features' => 'Warum wir',
        'gallery'  => 'Galerie',
        'cta'      => 'Handlungsaufruf',
        'info'     => 'Kontaktleiste',
        'footer'   => 'Fußzeile',
        'app_page' => 'App-Seite',
    ],

    'tab_hints' => [
        'brand'    => 'Logo, Schriftzug und Slogan für die gesamte Website.',
        'seo'      => 'Browsertitel, Beschreibung in Suchergebnissen und Teilen-Bild.',
        'navbar'   => 'Die fixierte obere Leiste: ihre Links und ihr Button.',
        'hero'     => 'Der erste Bildschirm: Überschrift, Foto und die beiden Buttons.',
        'services' => 'Die vier Leistungskarten. Unabhängig von den Buchungsleistungen.',
        'features' => 'Der Block „mehr als ein Friseurbesuch“ und seine vier Boxen.',
        'gallery'  => 'Die Reihe mit Arbeitsfotos.',
        'cta'      => 'Das umrandete Banner im unteren Seitenbereich.',
        'info'     => 'Adresse, Telefon, Öffnungszeiten und soziale Netzwerke.',
        'footer'   => 'Fußzeilenspalten, Newsletter und Rechtslinks.',
        'app_page' => 'Die eigenständige Seite /app, zu der alle Buchungsbuttons führen.',
    ],

    /* ── Admin: Feldbeschriftungen ───────────────────────────────── */

    'fields' => [
        'logo'         => 'Logo',
        'favicon'      => 'Favicon',
        'brand_name'   => 'Markenname',
        'brand_suffix' => 'Zusatz',
        'tagline'      => 'Slogan',

        'seo_title'       => 'Browsertitel',
        'seo_description' => 'Meta-Beschreibung',
        'seo_keywords'    => 'Schlüsselwörter',
        'og_image'        => 'Teilen-Bild',

        'cta_label'  => 'Buttontext',
        'cta_url'    => 'Buttonlink',
        'links'      => 'Links',
        'label'      => 'Text',
        'url'        => 'Link',
        'new_tab'    => 'In neuem Tab öffnen',
        'show_language_switcher' => 'Sprachumschalter anzeigen',

        'eyebrow'      => 'Kleine Beschriftung über der Überschrift',
        'title'        => 'Überschrift',
        'title_gold'   => 'Überschrift — goldene Zeile',
        'title_light'  => 'Überschrift — weiße Zeile',
        'description'  => 'Beschreibung',
        'image'        => 'Bild',
        'image_alt'    => 'Bildbeschreibung (für Screenreader)',
        'icon'         => 'Symbol',

        'primary_cta_label'   => 'Text Hauptbutton',
        'primary_cta_url'     => 'Link Hauptbutton',
        'secondary_cta_label' => 'Text Zweitbutton',
        'secondary_cta_url'   => 'Link Zweitbutton',

        'badges'      => 'Merkmale',
        'items'       => 'Einträge',
        'link_label'  => 'Linktext auf der Karte',
        'is_wide'     => 'Breite Kachel',
        'is_active'   => 'Sichtbar',
        'caption'     => 'Bildunterschrift',
        'alt'         => 'Bildbeschreibung',

        'value'        => 'Wert',
        'social_label' => 'Überschrift soziale Netzwerke',
        'social_links' => 'Soziale Netzwerke',
        'platform'     => 'Plattform',

        'nav_title'      => 'Überschrift Navigationsspalte',
        'nav_links'      => 'Links Navigationsspalte',
        'services_title' => 'Überschrift Leistungsspalte',
        'services_links' => 'Links Leistungsspalte',
        'closing'        => 'Abschlusszeile',
        'copyright'      => 'Copyright-Zeile',
        'legal_links'    => 'Rechtslinks',

        'newsletter_enabled'      => 'Newsletter-Block anzeigen',
        'newsletter_title'        => 'Newsletter-Überschrift',
        'newsletter_description'  => 'Newsletter-Text',
        'newsletter_placeholder'  => 'Platzhalter im Eingabefeld',
        'newsletter_action_url'   => 'Formular-URL des Newsletter-Anbieters',
        'newsletter_field_name'   => 'Name des E-Mail-Felds',
        'newsletter_email'        => 'Ersatz-E-Mail-Adresse',
        'newsletter_button_label' => 'Text des Ersatzbuttons',

        'app_store_url'     => 'App-Store-Link',
        'app_store_label'   => 'App-Store-Buttontext',
        'google_play_url'   => 'Google-Play-Link',
        'google_play_label' => 'Google-Play-Buttontext',
        'mockup_image'      => 'Smartphone-Bild',
        'qr_image'          => 'QR-Code',
        'qr_note'           => 'Text neben dem QR-Code',
        'coming_soon_note'  => 'Text, solange kein Store-Link hinterlegt ist',
        'steps_eyebrow'     => 'Schritte — kleine Beschriftung',
        'steps_title'       => 'Schritte — Überschrift',
        'steps'             => 'Schritte',
        'features'          => 'App-Vorteile',
    ],

    'hints' => [
        'anchor_url'        => 'Mit #hero, #services, #features, #gallery, #cta oder #info zu einem Abschnitt springen — sonst eine vollständige URL eintragen.',
        'icon'              => 'Aus dem mitgelieferten Symbolsatz wählen.',
        'newsletter_action' => 'Die Formular-URL Ihres Newsletter-Anbieters (Mailchimp, Brevo, CleverReach). Leer lassen, um stattdessen einen einfachen E-Mail-Button zu zeigen — diese Anwendung speichert keine Abonnenten.',
        'newsletter_field'  => 'Der Feldname, den Ihr Anbieter erwartet. Mailchimp verwendet EMAIL.',
        'seo_description'   => 'Etwa 150–160 Zeichen werden in Suchergebnissen vollständig angezeigt.',
        'is_wide'           => 'Macht diese Kachel auf großen Bildschirmen doppelt so breit. Im Referenzdesign ist die mittlere Kachel breit.',
        'image_default'     => 'Leer lassen, um das mitgelieferte Bild zu behalten. Ein Upload ersetzt es dauerhaft.',
        'store_links'       => 'Leer lassen, solange die App nicht veröffentlicht ist; die Seite zeigt dann einen Hinweis statt eines toten Buttons.',
    ],

    'add' => [
        'link'    => 'Link hinzufügen',
        'badge'   => 'Merkmal hinzufügen',
        'card'    => 'Karte hinzufügen',
        'feature' => 'Box hinzufügen',
        'image'   => 'Bild hinzufügen',
        'item'    => 'Eintrag hinzufügen',
        'social'  => 'Netzwerk hinzufügen',
        'step'    => 'Schritt hinzufügen',
    ],
];
