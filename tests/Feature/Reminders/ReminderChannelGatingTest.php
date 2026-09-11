<?php

/**
 * THE CLIENT'S RULE, PINNED DOWN.
 *
 *   "The reminder method follows the app's notification settings.
 *    Example: if the user enabled SMS only, they receive an SMS only."
 *
 * Every test here is a statement of that rule against SendAppointmentReminderJob.
 * They exist because the previous implementation could not satisfy it: push was
 * an unconditional "always-on baseline" sent before the opt-in channels were even
 * consulted, so "SMS only" delivered an SMS *and* a push notification. The
 * SMS-only case below fails outright against that code.
 *
 * Assertions are made on `delivered_channels`, the column the job writes after
 * the send attempt. That is deliberate: it is the same evidence support reads
 * when a customer reports a missing reminder, so a green test and a clean
 * support answer rest on the same fact rather than on a mock's call count.
 */

use App\Jobs\SendAppointmentReminderJob;
use App\Mail\AppointmentReminderMail;
use App\Models\AppointmentReminder;
use App\Models\AppSetting;
use App\Models\UserSetting;
use App\Services\SmsService;
use Database\Seeders\AppSettingSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Support\SalonFixture;

beforeEach(function () {
    $this->salon = new SalonFixture;

    // The channel gate reads app_settings; without the catalog every lookup
    // returns null and every channel would read as "off" for the wrong reason.
    $this->seed(AppSettingSeeder::class);

    Mail::fake();
    Notification::fake();

    $this->appointment = $this->salon->bookSlot(
        $this->salon->available,
        SalonFixture::DATE,
        '10:00',
        '11:00',
    );
    $this->appointment->update(['customer_id' => $this->salon->customer->id]);

    $this->salon->customer->update([
        'email' => 'lina.hassan@gmail.com',
        'phone' => '+4915112345678',
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

/** Switch a channel on or off for the fixture customer. */
function setChannel(string $key, bool $value): void
{
    UserSetting::updateOrCreate(
        ['user_id' => test()->salon->customer->id, 'key' => $key],
        ['value' => $value],
    );
}

/** Turn every reminder channel off, so each test opts back in explicitly. */
function allChannelsOff(): void
{
    setChannel('reminder_push_enabled', false);
    setChannel('reminder_email_enabled', false);
    setChannel('reminder_sms_enabled', false);
}

/** A pending reminder that is due right now. */
function dueReminder(): AppointmentReminder
{
    return AppointmentReminder::create([
        'appointment_id' => test()->appointment->id,
        'user_id' => test()->salon->customer->id,
        'remind_at' => now()->addMinutes(5),
        'status' => AppointmentReminder::STATUS_PENDING,
        'active_slot' => AppointmentReminder::ACTIVE_SLOT,
        'locale' => 'en',
        'title_key' => 'appointment_reminder.title',
        'message_key' => 'appointment_reminder.message',
        'params' => [
            'date' => ['type' => 'value', 'value' => SalonFixture::DATE],
            'time' => ['type' => 'value', 'value' => '10:00'],
            'number' => ['type' => 'value', 'value' => test()->appointment->number],
        ],
        'data' => ['type' => 'appointment_reminder', 'appointment_id' => test()->appointment->id],
    ]);
}

/** Run the job the way the queue worker would, and count SMS sends. */
function fireReminder(AppointmentReminder $reminder): AppointmentReminder
{
    $sms = Mockery::mock(SmsService::class);
    $sms->shouldReceive('send')->andReturnNull();
    app()->instance(SmsService::class, $sms);

    app()->call([new SendAppointmentReminderJob($reminder->id), 'handle']);

    return $reminder->fresh();
}

// ── The client's example, exactly ────────────────────────────────────────────

it('sends ONLY an SMS when the customer enabled SMS only', function () {
    allChannelsOff();
    setChannel('reminder_sms_enabled', true);

    $reminder = fireReminder(dueReminder());

    // The whole point: push is absent. Against the old always-on baseline this
    // array was ['push', 'sms'] and this assertion failed.
    expect($reminder->delivered_channels)->toBe(['sms']);

    Mail::assertNothingSent();
});

it('sends ONLY an email when the customer enabled email only', function () {
    allChannelsOff();
    setChannel('reminder_email_enabled', true);

    $reminder = fireReminder(dueReminder());

    expect($reminder->delivered_channels)->toBe(['email']);
    Mail::assertSent(AppointmentReminderMail::class, 1);
});

it('sends ONLY a push notification when the customer enabled push only', function () {
    allChannelsOff();
    setChannel('reminder_push_enabled', true);

    $reminder = fireReminder(dueReminder());

    expect($reminder->delivered_channels)->toBe(['push']);
    Mail::assertNothingSent();
});

it('sends on every channel the customer enabled', function () {
    setChannel('reminder_push_enabled', true);
    setChannel('reminder_email_enabled', true);
    setChannel('reminder_sms_enabled', true);

    $reminder = fireReminder(dueReminder());

    expect($reminder->delivered_channels)
        ->toContain('push')
        ->toContain('email')
        ->toContain('sms');
});

// ── The edge the settings screen makes reachable ─────────────────────────────

it('sends nothing, and records that it sent nothing, when every channel is off', function () {
    allChannelsOff();

    $reminder = fireReminder(dueReminder());

    // An empty array, not null: the reminder DID come due and DID run. That is
    // the distinction that makes "I set a reminder and got nothing" answerable
    // instead of a guess.
    expect($reminder->delivered_channels)->toBe([]);
    expect($reminder->status)->toBe(AppointmentReminder::STATUS_SENT);
    Mail::assertNothingSent();
});

it('is still marked sent when a channel is enabled but unreachable', function () {
    allChannelsOff();
    setChannel('reminder_sms_enabled', true);

    // Enabled SMS, but no number to send it to.
    $this->salon->customer->update(['phone' => null]);

    $reminder = fireReminder(dueReminder());

    expect($reminder->delivered_channels)->toBe([]);
    expect($reminder->status)->toBe(AppointmentReminder::STATUS_SENT);
});

// ── Timing of the settings read ──────────────────────────────────────────────

it('honours a channel switched on AFTER the reminder was scheduled', function () {
    allChannelsOff();

    $reminder = dueReminder();

    // The customer changes their mind between booking and the reminder firing.
    setChannel('reminder_email_enabled', true);

    expect(fireReminder($reminder)->delivered_channels)->toBe(['email']);
});

it('honours a channel switched off AFTER the reminder was scheduled', function () {
    allChannelsOff();
    setChannel('reminder_email_enabled', true);

    $reminder = dueReminder();

    setChannel('reminder_email_enabled', false);

    expect(fireReminder($reminder)->delivered_channels)->toBe([]);
    Mail::assertNothingSent();
});

// ── Defaults ─────────────────────────────────────────────────────────────────

it('falls back to push for a customer who never opened the settings screen', function () {
    // No UserSetting rows at all — the app_settings defaults decide.
    $reminder = fireReminder(dueReminder());

    // Push defaults to true precisely so this customer keeps the reminders they
    // were already getting before the gate existed. Email and SMS stay opt-in.
    expect($reminder->delivered_channels)->toBe(['push']);
    Mail::assertNothingSent();
});

it('seeds push enabled and the paid channels disabled', function () {
    expect(AppSetting::where('key', 'reminder_push_enabled')->value('default_value'))->toBe(true)
        ->and(AppSetting::where('key', 'reminder_email_enabled')->value('default_value'))->toBe(false)
        ->and(AppSetting::where('key', 'reminder_sms_enabled')->value('default_value'))->toBe(false);
});

// ── Idempotency ──────────────────────────────────────────────────────────────

it('does not send twice when the job runs again', function () {
    allChannelsOff();
    setChannel('reminder_email_enabled', true);

    $reminder = dueReminder();

    fireReminder($reminder);
    Mail::assertSent(AppointmentReminderMail::class, 1);

    // A retried or duplicated job finds the reminder already claimed.
    fireReminder($reminder);
    Mail::assertSent(AppointmentReminderMail::class, 1);
});

it('refuses to fire for a cancelled appointment', function () {
    setChannel('reminder_email_enabled', true);

    $reminder = dueReminder();

    $this->appointment->cancel('changed my mind');

    $reminder = fireReminder($reminder->fresh());

    expect($reminder->status)->toBe(AppointmentReminder::STATUS_CANCELLED);
    Mail::assertNothingSent();
});
