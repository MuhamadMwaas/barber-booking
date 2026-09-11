<?php

/**
 * The four endpoints behind the booking screen's reminder control.
 *
 * The screen is a toggle plus a dropdown, which implies four operations the API
 * previously could not all serve: list the choices, save one, read back what was
 * saved when the screen reopens, and switch it off. Only "save" existed.
 *
 * The scheduling tests fake the queue. The test environment runs QUEUE_CONNECTION
 * =sync, where a delayed dispatch executes IMMEDIATELY — the reminder would fire
 * the instant it was created and every "is it still pending?" assertion would be
 * meaningless. Faking keeps the row in the state a real deployment leaves it in.
 */

use App\Models\AppointmentReminder;
use Database\Seeders\AppSettingSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\Support\SalonFixture;

beforeEach(function () {
    $this->salon = new SalonFixture;
    $this->seed(AppSettingSeeder::class);
    $this->token = $this->salon->customerToken();

    Queue::fake();

    $this->appointment = $this->salon->bookSlot(
        $this->salon->available,
        SalonFixture::DATE,
        '10:00',
        '11:00',
    );
    $this->appointment->update(['customer_id' => $this->salon->customer->id]);
});

afterEach(function () {
    Carbon::setTestNow();
});

function setReminder(array $payload): \Illuminate\Testing\TestResponse
{
    return test()->withToken(test()->token)
        ->postJson('/api/appointments/reminders', $payload);
}

// ── The dropdown's contents ──────────────────────────────────────────────────

it('serves the seven lead times with translated labels', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/appointments/reminders/options')
        ->assertOk();

    expect($response->json('data.options.*.offset_hours'))->toBe([1, 2, 3, 4, 5, 6, 24]);
    expect($response->json('data.default_offset_hours'))->toBe(1);

    // The labels come from the backend so the salon can reword them without an
    // app release.
    expect($response->json('data.options.0.label'))->toBe('1 hour before');
    expect($response->json('data.texts.question'))->not->toBeEmpty();
});

it('serves the option labels in the language the client asks for', function () {
    // Accept-Language is how every other endpoint in this API picks a language,
    // and this one must not be the exception. (The test client sends
    // `Accept-Language: en-us` by default, so an explicit header is the only way
    // to exercise a different language here.)
    $response = $this->withToken($this->token)
        ->withHeaders(['Accept-Language' => 'de'])
        ->getJson('/api/appointments/reminders/options')
        ->assertOk();

    expect($response->json('data.options.0.label'))->toBe('1 Stunde vorher')
        ->and($response->json('data.options.6.label'))->toBe('24 Stunden vorher')
        // The exact strings the client specified for the screen.
        ->and($response->json('data.texts.title'))->toBe('Terminerinnerung')
        ->and($response->json('data.texts.subtitle'))->toBe('Erhalte eine Erinnerung vor deinem Termin.')
        ->and($response->json('data.texts.question'))->toBe('Wann möchtest du erinnert werden?');
});

it('lets ?lang= override the saved profile language', function () {
    // An explicit request beats the profile: the app may be displaying a
    // language the customer never saved to their account, and a screen whose
    // labels disagree with the rest of the UI is worse than a stale profile.
    $this->salon->customer->update(['locale' => 'de']);

    $response = $this->withToken($this->token)
        ->getJson('/api/appointments/reminders/options?lang=ar')
        ->assertOk();

    expect($response->json('data.options.0.label'))->toBe('قبل ساعة')
        ->and($response->json('data.texts.question'))->toBe('متى تريد أن يصلك التذكير؟');
});

// ── Saving a choice ──────────────────────────────────────────────────────────

it('schedules the reminder the given number of hours before the appointment', function () {
    $response = setReminder([
        'appointment_id' => $this->appointment->id,
        'offset_hours' => 2,
    ])->assertStatus(201);

    expect($response->json('data.offset_hours'))->toBe(2);

    $reminder = AppointmentReminder::firstWhere('id', $response->json('data.id'));

    // Derived from the appointment's own start_time on the server, which is what
    // makes the result independent of the client's timezone.
    expect($reminder->remind_at->toDateTimeString())
        ->toBe($this->appointment->start_time->copy()->subHours(2)->toDateTimeString());
});

it('rejects a lead time that is not one of the offered options', function () {
    setReminder([
        'appointment_id' => $this->appointment->id,
        'offset_hours' => 7,
    ])->assertStatus(422)->assertJsonPath('error_type', 'validation_error');
});

it('rejects a lead time that would already have passed', function () {
    // Booked for 10:00 on a date two days out; 24h earlier is still ahead of the
    // frozen "now", so move now to inside the window.
    Carbon::setTestNow($this->appointment->start_time->copy()->subHours(3));

    setReminder([
        'appointment_id' => $this->appointment->id,
        'offset_hours' => 24,
    ])->assertStatus(422)
        ->assertJsonPath('errors.offset_hours.0', __('main.appointment.validation.offset_hours.too_late'));
});

it('rejects a request carrying both offset_hours and remind_at', function () {
    setReminder([
        'appointment_id' => $this->appointment->id,
        'offset_hours' => 2,
        'remind_at' => $this->appointment->start_time->copy()->subHour()->toDateTimeString(),
    ])->assertStatus(422)
        ->assertJsonPath('errors.offset_hours.0', __('main.appointment.validation.offset_hours.conflict'));
});

it('rejects a request carrying neither', function () {
    setReminder(['appointment_id' => $this->appointment->id])->assertStatus(422);
});

it('still accepts the legacy absolute remind_at', function () {
    // An already-published build of the app sends this form; it must keep working.
    $response = setReminder([
        'appointment_id' => $this->appointment->id,
        'remind_at' => $this->appointment->start_time->copy()->subHours(3)->toDateTimeString(),
    ])->assertStatus(201);

    expect($response->json('data.offset_hours'))->toBe(3);
});

it('refuses to set a reminder on somebody else\'s booking', function () {
    $other = $this->salon->bookSlot($this->salon->available, SalonFixture::DATE, '12:00', '13:00');

    // Owned by the fixture's filler customer, not the caller.
    setReminder([
        'appointment_id' => $other->id,
        'offset_hours' => 1,
    ])->assertStatus(422);
});

// ── Changing the choice ──────────────────────────────────────────────────────

it('replaces the previous reminder instead of stacking a second one', function () {
    setReminder(['appointment_id' => $this->appointment->id, 'offset_hours' => 1])->assertStatus(201);
    setReminder(['appointment_id' => $this->appointment->id, 'offset_hours' => 3])->assertStatus(201);

    $reminders = AppointmentReminder::where('appointment_id', $this->appointment->id)->get();

    // Both rows survive — the history is deliberate — but only one is live.
    expect($reminders)->toHaveCount(2);
    expect($reminders->whereNotNull('active_slot'))->toHaveCount(1);
    expect($reminders->firstWhere('active_slot', AppointmentReminder::ACTIVE_SLOT)->offsetHours())->toBe(3);
});

/*
 * THE REGRESSION THIS SUITE EXISTS FOR.
 *
 * The old unique index spanned (appointment_id, user_id, remind_at, status).
 * Rescheduling flips a row from `pending` to `cancelled` rather than deleting
 * it, so flipping back and forth eventually tries to create a SECOND cancelled
 * row with the same remind_at — a duplicate-key error on the UPDATE, surfacing
 * as a bare 500 on a completely legal request. A dropdown invites exactly this.
 */
it('survives the customer flipping between two lead times repeatedly', function () {
    foreach ([1, 2, 1, 2, 1, 2, 1] as $hours) {
        setReminder([
            'appointment_id' => $this->appointment->id,
            'offset_hours' => $hours,
        ])->assertStatus(201);
    }

    expect(AppointmentReminder::where('appointment_id', $this->appointment->id)
        ->whereNotNull('active_slot')
        ->count())->toBe(1);
});

// ── Reading it back ──────────────────────────────────────────────────────────

it('returns the live reminder so the screen can restore its state', function () {
    setReminder(['appointment_id' => $this->appointment->id, 'offset_hours' => 6])->assertStatus(201);

    $this->withToken($this->token)
        ->getJson("/api/appointments/{$this->appointment->id}/reminders")
        ->assertOk()
        ->assertJsonPath('data.offset_hours', 6)
        ->assertJsonPath('data.is_active', true);
});

it('returns null rather than 404 when the booking has no reminder', function () {
    // "No reminder" is a normal state the screen renders as an off toggle, not
    // an error the app should have to catch.
    $this->withToken($this->token)
        ->getJson("/api/appointments/{$this->appointment->id}/reminders")
        ->assertOk()
        ->assertJsonPath('data', null);
});

it('reports which channels would actually reach the customer', function () {
    $this->salon->customer->update(['phone' => null]);

    $response = $this->withToken($this->token)
        ->getJson("/api/appointments/{$this->appointment->id}/reminders")
        ->assertOk();

    // Enabled but unreachable is the case the app warns about: the customer
    // switched SMS on and never added a number.
    expect($response->json('channels.sms.deliverable'))->toBeFalse();
    expect($response->json('channels.push.enabled'))->toBeTrue();
});

// ── Switching it off ─────────────────────────────────────────────────────────

it('cancels the reminder when the toggle is switched off', function () {
    setReminder(['appointment_id' => $this->appointment->id, 'offset_hours' => 2])->assertStatus(201);

    $this->withToken($this->token)
        ->deleteJson("/api/appointments/{$this->appointment->id}/reminders")
        ->assertOk();

    $reminder = AppointmentReminder::where('appointment_id', $this->appointment->id)->latest('id')->first();

    expect($reminder->status)->toBe(AppointmentReminder::STATUS_CANCELLED)
        ->and($reminder->active_slot)->toBeNull();
});

it('reports 404 when there is no reminder to switch off', function () {
    $this->withToken($this->token)
        ->deleteJson("/api/appointments/{$this->appointment->id}/reminders")
        ->assertStatus(404);
});

it('lets the customer set a reminder again after switching it off', function () {
    setReminder(['appointment_id' => $this->appointment->id, 'offset_hours' => 2])->assertStatus(201);
    $this->withToken($this->token)->deleteJson("/api/appointments/{$this->appointment->id}/reminders")->assertOk();
    setReminder(['appointment_id' => $this->appointment->id, 'offset_hours' => 2])->assertStatus(201);

    expect(AppointmentReminder::where('appointment_id', $this->appointment->id)
        ->whereNotNull('active_slot')
        ->count())->toBe(1);
});

// ── Cancelling the booking ───────────────────────────────────────────────────

it('retires the reminder when the booking is cancelled', function () {
    setReminder(['appointment_id' => $this->appointment->id, 'offset_hours' => 2])->assertStatus(201);

    $this->withToken($this->token)
        ->postJson("/api/appointments/{$this->appointment->id}/cancel", ['reason' => 'changed my mind'])
        ->assertOk();

    expect(AppointmentReminder::where('appointment_id', $this->appointment->id)
        ->whereNotNull('active_slot')
        ->count())->toBe(0);
});

// ── Setting it during booking ────────────────────────────────────────────────

it('creates the reminder in the same call as the booking', function () {
    $response = $this->withToken($this->token)->postJson('/api/bookings', [
        'date' => SalonFixture::DATE,
        'payment_method' => 'cash',
        'reminder_offset_hours' => 2,
        'services' => [[
            'service_id' => $this->salon->service->id,
            'provider_id' => $this->salon->available->id,
            'start_time' => '14:00',
        ]],
    ])->assertStatus(201);

    // One round trip: no window in which the booking exists and the reminder the
    // customer asked for does not.
    expect($response->json('data.reminder.offset_hours'))->toBe(2);
});

it('books without a reminder when none was requested', function () {
    $response = $this->withToken($this->token)->postJson('/api/bookings', [
        'date' => SalonFixture::DATE,
        'payment_method' => 'cash',
        'services' => [[
            'service_id' => $this->salon->service->id,
            'provider_id' => $this->salon->available->id,
            'start_time' => '15:00',
        ]],
    ])->assertStatus(201);

    expect($response->json('data.reminder'))->toBeNull();
});

it('rejects an unsupported lead time at booking time too', function () {
    // Same list as the dedicated endpoint — one source of truth.
    $this->withToken($this->token)->postJson('/api/bookings', [
        'date' => SalonFixture::DATE,
        'payment_method' => 'cash',
        'reminder_offset_hours' => 9,
        'services' => [[
            'service_id' => $this->salon->service->id,
            'provider_id' => $this->salon->available->id,
            'start_time' => '16:00',
        ]],
    ])->assertStatus(422);
});
