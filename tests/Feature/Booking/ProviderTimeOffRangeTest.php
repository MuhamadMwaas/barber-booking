<?php

/**
 * BOOK-04 — the meaning of a leave's date range, at the unit level.
 *
 * ProviderTimeOff::blockedWindowOn() is the single definition both the
 * availability layer and the booking layer consume, so these cases pin the
 * semantics directly rather than through an HTTP round trip.
 */

use App\Models\ProviderTimeOff;
use Illuminate\Support\Carbon;

function timeOff(array $attributes): ProviderTimeOff
{
    return new ProviderTimeOff($attributes + [
        'type' => ProviderTimeOff::TYPE_HOURLY,
        'start_date' => '2026-09-10',
        'end_date' => '2026-09-10',
        'start_time' => '12:00:00',
        'end_time' => '13:00:00',
    ]);
}

it('confines a single-day hourly leave to its own hours', function () {
    $window = timeOff([])->blockedWindowOn(Carbon::parse('2026-09-10'));

    expect($window['start']->format('Y-m-d H:i'))->toBe('2026-09-10 12:00')
        ->and($window['end']->format('Y-m-d H:i'))->toBe('2026-09-10 13:00');
});

it('runs a multi-day hourly leave from its start time to the end of the start day', function () {
    $window = timeOff(['end_date' => '2026-09-12'])->blockedWindowOn(Carbon::parse('2026-09-10'));

    expect($window['start']->format('Y-m-d H:i'))->toBe('2026-09-10 12:00')
        ->and($window['end']->format('Y-m-d H:i'))->toBe('2026-09-11 00:00');
});

it('blocks a middle day of a multi-day hourly leave completely', function () {
    $window = timeOff(['end_date' => '2026-09-12'])->blockedWindowOn(Carbon::parse('2026-09-11'));

    expect($window['start']->format('Y-m-d H:i'))->toBe('2026-09-11 00:00')
        ->and($window['end']->format('Y-m-d H:i'))->toBe('2026-09-12 00:00');
});

it('releases the end day of a multi-day hourly leave at its end time', function () {
    $window = timeOff(['end_date' => '2026-09-12'])->blockedWindowOn(Carbon::parse('2026-09-12'));

    expect($window['start']->format('Y-m-d H:i'))->toBe('2026-09-12 00:00')
        ->and($window['end']->format('Y-m-d H:i'))->toBe('2026-09-12 13:00');
});

it('treats a null end_date as a single-day leave', function () {
    $subject = timeOff(['end_date' => null]);

    expect($subject->blockedWindowOn(Carbon::parse('2026-09-10')))->not->toBeNull()
        ->and($subject->blockedWindowOn(Carbon::parse('2026-09-11')))->toBeNull();
});

it('occupies every covered day of a full-day leave', function () {
    $subject = timeOff([
        'type' => ProviderTimeOff::TYPE_FULL_DAY,
        'end_date' => '2026-09-12',
        'start_time' => null,
        'end_time' => null,
    ]);

    $window = $subject->blockedWindowOn(Carbon::parse('2026-09-11'));

    expect($window['start']->format('Y-m-d H:i'))->toBe('2026-09-11 00:00')
        ->and($window['end']->format('Y-m-d H:i'))->toBe('2026-09-12 00:00');
});

it('blocks nothing for an hourly leave with a half-filled time pair', function () {
    // Malformed data must not silently close the provider's whole calendar.
    expect(timeOff(['end_time' => null])->blockedWindowOn(Carbon::parse('2026-09-10')))->toBeNull();
});

it('ignores days outside the range', function () {
    $subject = timeOff(['end_date' => '2026-09-12']);

    expect($subject->blockedWindowOn(Carbon::parse('2026-09-09')))->toBeNull()
        ->and($subject->blockedWindowOn(Carbon::parse('2026-09-13')))->toBeNull();
});

it('lets a slot start exactly when the leave ends', function () {
    $subject = timeOff([]);

    expect($subject->blocksWindow(
        Carbon::parse('2026-09-10 13:00'),
        Carbon::parse('2026-09-10 14:00'),
    ))->toBeFalse()
        ->and($subject->blocksWindow(
            Carbon::parse('2026-09-10 12:30'),
            Carbon::parse('2026-09-10 13:30'),
        ))->toBeTrue();
});
