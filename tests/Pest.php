<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Pest bootstrap
|--------------------------------------------------------------------------
|
| Scoped deliberately to the Pest-style directories only. The rest of
| tests/Feature is PHPUnit class-based and already binds its own base class +
| RefreshDatabase; binding globally here would layer traits onto those classes
| for no reason.
|
| `Feature/Money` holds the cross-layer VAT parity suite (MON-01).
| `Feature/Reminders` holds the appointment-reminder channel suite.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature/Booking', 'Feature/Money', 'Feature/Reminders');
