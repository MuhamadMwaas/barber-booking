<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Pest bootstrap
|--------------------------------------------------------------------------
|
| Scoped deliberately to `Feature/Booking` only. The rest of tests/Feature is
| PHPUnit class-based and already binds its own base class + RefreshDatabase;
| binding globally here would layer traits onto those classes for no reason.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature/Booking');
