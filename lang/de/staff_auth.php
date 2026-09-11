<?php

/*
 * Anmeldeseite für Mitarbeitende (Staff Dashboard).
 * Ablehnungsmeldungen (deaktiviertes Konto / kein Mitarbeitendenkonto) kommen
 * über StaffLoginDenial aus auth.php, damit beide Oberflächen dieselbe
 * Ablehnung nicht unterschiedlich formulieren.
 */

return [
    'title' => 'Mitarbeiter-Anmeldung',
    'subtitle' => 'Mitarbeiter-Dashboard',
    'email' => 'E-Mail',
    'password' => 'Passwort',
    'remember' => 'Angemeldet bleiben',
    'submit' => 'Anmelden',
    'help' => 'Probleme bei der Anmeldung? Wenden Sie sich an die Salonverwaltung.',

    'logout_confirm_title' => 'Sie sind noch eingestempelt',
    'logout_confirm_body' => 'Möchten Sie sich ausstempeln, bevor Sie sich abmelden?',
    'logout_with_check_out' => 'Ausstempeln und abmelden',
    'logout_only' => 'Nur abmelden',
    'cancel' => 'Abbrechen',
];
