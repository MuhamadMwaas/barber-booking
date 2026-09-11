<?php

/**
 * Appointment-reminder configuration.
 *
 * The customer picks WHEN to be reminded from a fixed list of lead times, not
 * from a free date picker. Keeping the list here rather than hard-coded in the
 * request rules means the salon can widen or narrow the choice without touching
 * validation, the API resource, or the mobile app: `GET /api/appointments/
 * reminders/options` renders this array straight to the client.
 */
return [

    /*
     * Lead times (in hours before the appointment starts) the customer may pick.
     * Each value MUST have a matching label in the `appointment_reminder` lang
     * files under `options.<value>`, otherwise the options endpoint falls back
     * to a generic "N hours before" string.
     */
    'offset_hours' => [1, 2, 3, 4, 5, 6, 24],

    /*
     * Pre-selected value for the dropdown. Must be one of `offset_hours`.
     */
    'default_offset_hours' => 1,

];
