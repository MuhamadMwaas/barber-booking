<?php

/**
 * The reminder WORDING, pinned to the client's approved text (2026-10-16).
 *
 * The channel tests prove a reminder goes out; these prove what it says. The
 * SMS body is asserted character for character because it is billed per
 * segment: a stray non-GSM character or a few extra words silently doubles the
 * cost of every reminder.
 */

use App\Jobs\SendAppointmentReminderJob;
use App\Models\AppointmentReminder;
use App\Models\UserSetting;
use App\Services\AppointmentReminderService;
use App\Services\NotificationService;
use App\Services\SmsService;
use Database\Seeders\AppSettingSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Support\SalonFixture;

beforeEach(function () {
    $this->salon = new SalonFixture;
    $this->seed(AppSettingSeeder::class);

    Mail::fake();
    Notification::fake();

    DB::table('salon_settings')->updateOrInsert(
        ['key' => 'company_name'],
        ['value' => json_encode('Look up'), 'type' => 'string', 'description' => 'test fixture', 'created_at' => now(), 'updated_at' => now()],
    );

    $this->salon->available->update(['first_name' => 'Ahmad', 'last_name' => 'Karimi']);
    $this->salon->customer->update([
        'first_name' => 'Fareed',
        'last_name' => 'Nasser',
        'phone' => '+4915112345678',
    ]);

    // SalonFixture::DATE is a Wednesday.
    $this->appointment = $this->salon->bookSlot($this->salon->available, SalonFixture::DATE, '14:30', '15:00');
    $this->appointment->update(['customer_id' => $this->salon->customer->id]);

    foreach (['reminder_push_enabled' => false, 'reminder_email_enabled' => false, 'reminder_sms_enabled' => true] as $key => $value) {
        UserSetting::updateOrCreate(['user_id' => $this->salon->customer->id, 'key' => $key], ['value' => $value]);
    }
});

afterEach(function () {
    Carbon::setTestNow();
});

/** Fire a reminder in the given locale and return the SMS body that went out. */
function sentSms(string $locale, array $overrides = []): string
{
    $reminder = AppointmentReminder::create($overrides + [
        'appointment_id' => test()->appointment->id,
        'user_id' => test()->salon->customer->id,
        'remind_at' => now()->addMinutes(5),
        'status' => AppointmentReminder::STATUS_PENDING,
        'active_slot' => AppointmentReminder::ACTIVE_SLOT,
        'locale' => $locale,
        'title_key' => AppointmentReminder::TITLE_KEY,
        'message_key' => AppointmentReminder::MESSAGE_KEY,
        'params' => [],
        'data' => [],
    ]);

    $sent = null;
    $sms = Mockery::mock(SmsService::class);
    $sms->shouldReceive('send')->once()->andReturnUsing(function ($phone, $text) use (&$sent) {
        $sent = $text;
    });
    app()->instance(SmsService::class, $sms);

    app()->call([new SendAppointmentReminderJob($reminder->id), 'handle']);

    return $sent;
}

it('sends the client-approved German text', function () {
    expect(sentSms('de'))->toBe(
        'Hallo Fareed, dies ist eine Erinnerung an Deinen Termin bei Look up am Mittwoch, 09.09.2026 um 14:30 Uhr bei Ahmad. Wir freuen uns auf Dich! Dein Look up Team'
    );
});

it('sends the client-approved Arabic text', function () {
    expect(sentSms('ar'))->toBe(
        'مرحبًا Fareed، هذا تذكير بموعدك لدى Look up يوم الأربعاء 09.09.2026 الساعة 14:30 مع Ahmad. نتطلع إلى زيارتك! فريق Look up'
    );
});

it('sends the English text', function () {
    expect(sentSms('en'))->toBe(
        'Hi Fareed, this is a reminder of your appointment at Look up on Wednesday, 09.09.2026 at 14:30 with Ahmad. We look forward to seeing you! Your Look up Team'
    );
});

it('uses the appointment as it is at send time, not as it was when booked', function () {
    // The reminder was scheduled when the appointment was at 14:30 with Ahmad…
    [$params] = app(AppointmentReminderService::class)->buildPayload($this->appointment);

    // …then the salon moved it.
    $this->appointment->update([
        'start_time' => Carbon::parse(SalonFixture::DATE . ' 16:00'),
        'end_time' => Carbon::parse(SalonFixture::DATE . ' 16:30'),
    ]);

    expect(sentSms('de', ['params' => $params]))->toContain('um 16:00 Uhr');
});

it('still renders a reminder queued with the legacy text keys', function () {
    // A row scheduled before the wording change, still pending when it ships.
    expect(sentSms('de', [
        'title_key' => 'appointment_reminder.title',
        'message_key' => 'appointment_reminder.message',
    ]))->toStartWith('Erinnerung: Ihr Termin #');
    // Legacy keys still render (no raw ":name") — the migration then switches
    // such rows to v2; see 2026_10_16_000001_switch_pending_reminders_to_v2_text.
});

it('keeps the German SMS in GSM-7 so it is billed at 160 chars per part', function () {
    $text = sentSms('de');

    // GSM-7 basic set, enough for this text: ASCII letters, digits, punctuation,
    // German umlauts. A single "–" or emoji would switch the whole SMS to UCS-2.
    expect(preg_match('/^[A-Za-z0-9 .,:!?\'"()\-\/&%+@#ÄÖÜäöüß\n]*$/u', $text))->toBe(1)
        ->and(mb_strlen($text))->toBeLessThanOrEqual(160);
});

it('renders the weekday in each reader\'s language from one params array', function () {
    [$params] = app(AppointmentReminderService::class)->buildPayload($this->appointment);
    $notifications = app(NotificationService::class);

    expect($notifications->translateKey(AppointmentReminder::MESSAGE_KEY, $params, 'de'))->toContain('Mittwoch')
        ->and($notifications->translateKey(AppointmentReminder::MESSAGE_KEY, $params, 'ar'))->toContain('الأربعاء')
        ->and($notifications->translateKey(AppointmentReminder::MESSAGE_KEY, $params, 'en'))->toContain('Wednesday');
});
