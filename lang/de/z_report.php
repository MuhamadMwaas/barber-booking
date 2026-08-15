<?php

/*
|--------------------------------------------------------------------------
| Tagesbericht (Z-Bericht & Mitarbeiterübersicht)
|--------------------------------------------------------------------------
*/

return [

    // ── Berichte-Tab (Filterbildschirm) ──────────────────────────────────
    'tab'                => 'Berichte',
    'tab_title'          => 'Tagesbericht',
    'tab_subtitle'       => 'Z-Bericht und Mitarbeiterübersicht für einen Tag oder Zeitraum.',

    'mode'               => 'Zeitraum',
    'mode_day'           => 'Einzelner Tag',
    'mode_range'         => 'Zeitraum',
    'date'               => 'Datum',
    'from'               => 'Von',
    'to'                 => 'Bis',
    'quick_ranges'       => 'Schnellauswahl',
    'today'              => 'Heute',
    'yesterday'          => 'Gestern',
    'this_week'          => 'Diese Woche',
    'this_month'         => 'Dieser Monat',
    'last_month'         => 'Letzter Monat',

    'employees'          => 'Mitarbeiter',
    'all_employees'      => 'Alle Mitarbeiter',
    'employees_selected' => ':count ausgewählt',
    'select_all'         => 'Alle auswählen',
    'clear'              => 'Zurücksetzen',

    'generate'           => 'Bericht erstellen',
    'generate_hint'      => 'Öffnet den druckbaren Bericht in einem neuen Tab.',
    'invalid_range'      => 'Das Startdatum muss vor oder gleich dem Enddatum liegen.',
    'range_too_long'     => 'Der Zeitraum darf :days Tage nicht überschreiten.',

    'preview_title'      => 'Vorschau',
    'preview_hint'       => 'Live-Summen für den gewählten Zeitraum.',

    // ── Berichtsdokument ─────────────────────────────────────────────────
    'title'              => 'Tagesbericht',
    'subtitle'           => 'Z-Bericht & Mitarbeiterübersicht',
    'range_label'        => 'Zeitraum',
    'date_label'         => 'Datum',
    'printed'            => 'Gedruckt',
    'generated_by'       => 'Erstellt von',
    'print'              => 'Drucken',
    'close'              => 'Schließen',

    'filtered_notice'    => 'Gefilterter Bericht — umfasst nur die gewählten Mitarbeiter, nicht den gesamten Salon.',

    // Umsatz
    'sales_title'        => 'Umsatzübersicht',
    'method'             => 'Zahlungsart',
    'transactions'       => 'Transaktionen',
    'amount'             => 'Betrag',
    'cash'               => 'Bar',
    'card'               => 'Karte',
    'online'             => 'Online',
    'total_sales'        => 'Gesamtumsatz',

    // Zahlungsaufteilung
    'payment_title'      => 'Zahlungsübersicht',

    // Summen
    'totals_title'       => 'Berichtssummen',
    'total_transactions' => 'Transaktionen gesamt',
    'total_receipts'     => 'Nummerierte Belege',
    'total_customers'    => 'Kunden gesamt',
    'total_services'     => 'Verkaufte Leistungen',
    'avg_ticket'         => 'Durchschnittsbon',

    // USt.
    'vat_title'          => 'Umsatzsteuer',
    'vat_rate'           => 'Satz',
    'vat_net'            => 'Netto',
    'vat_tax'            => 'USt.',
    'vat_gross'          => 'Brutto',
    'vat_note'           => 'Preise sind Bruttopreise; die USt. wird rückwärts herausgerechnet.',

    // Rabatte
    'discount_title'     => 'Rabatte',
    'discount_total'     => 'Gewährte Rabatte gesamt',
    'discount_count'     => 'Rabattierte Belege',
    'discount_before'    => 'Summe vor Rabatt',
    'discount_percent'   => 'Anteil am Brutto',

    // Betrieb
    'operations_title'   => 'Betriebskennzahlen',
    'source_title'       => 'Buchungsquelle',
    'source_app'         => 'App / Online',
    'source_reception'   => 'Empfang',
    'cancelled'          => 'Storniert',
    'no_show'            => 'Nicht erschienen',
    'outstanding'        => 'Offen',
    'outstanding_count'  => 'Unbezahlte Buchungen',
    'scheduled_note'     => 'Nach Termindatum gezählt — unbezahlte Buchungen haben kein Zahlungsdatum.',

    // Leistungen
    'services_title'     => 'Leistungsübersicht',
    'service'            => 'Leistung',
    'qty'                => 'Anz.',
    'revenue'            => 'Umsatz',
    'share'              => 'Anteil',
    'services_empty'     => 'In diesem Zeitraum wurden keine Leistungen erbracht.',

    // Mitarbeiter
    'employee_summary'   => 'Mitarbeiterübersicht',
    'employee_details'   => 'Mitarbeiterdetails',
    'employee'           => 'Mitarbeiter',
    'appointments'       => 'Termine',
    'services'           => 'Leistungen',
    'cash_sales'         => 'Barumsatz',
    'card_sales'         => 'Kartenumsatz',
    'online_sales'       => 'Online-Umsatz',
    'total_revenue'      => 'Gesamtumsatz',
    'customers'          => 'Kunden',
    'working_time'       => 'Arbeitszeit',
    'worked'             => 'Gearbeitet',
    'top_service'        => 'Top-Leistung',
    'sessions'           => ':count Sitzungen',
    'still_clocked_in'   => 'noch eingestempelt',
    'no_attendance'      => 'Nicht eingestempelt',
    'total_row'          => 'GESAMT',
    'employees_empty'    => 'Keine Mitarbeiter vorhanden.',

    // Tagesverlauf
    'daily_title'        => 'Tagesverlauf',
    'day'                => 'Datum',

    // Transaktionsanhang
    'appendix_title'     => 'Transaktionen',
    'invoice_no'         => 'Beleg',
    'time'               => 'Zeit',
    'customer'           => 'Kunde',
    'provider'           => 'Mitarbeiter',
    'appendix_empty'     => 'In diesem Zeitraum wurden keine Zahlungen vereinnahmt.',

    // Fußzeile
    'report_id'          => 'Berichts-ID',
    'footer_note'        => 'Erstellt aus Buchungs- und Rechnungsdaten. Die Zahlen basieren auf dem Datum der Vereinnahmung.',
    'empty_report'       => 'In diesem Zeitraum wurden keine Zahlungen vereinnahmt.',
];
