<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * The requested time window is occupied by another booking.
 *
 * Separate from every other booking rejection because it means something
 * different to the client: the request was well-formed and was legal when the
 * customer saw the slot — somebody else simply took it first. The API maps this
 * to 409 Conflict (error_type "slot_conflict") so the app can refresh the slot
 * list and let the customer pick again, instead of showing "your input is
 * invalid" for a race the customer did not lose through any fault of their own.
 *
 * It extends InvalidArgumentException so every existing catch site — the
 * controllers, the Filament pages, StaffDashboard — keeps working unchanged.
 */
class SlotUnavailableException extends InvalidArgumentException
{
}
