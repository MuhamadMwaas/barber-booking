<?php

namespace Tests\Feature;

use App\Enum\OtpPurpose;
use App\Enum\OtpType;
use App\Models\Otp;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards AUTH-02 (attempt cap must hold under concurrency) and the
 * `otps.expires_at ON UPDATE CURRENT_TIMESTAMP` defect that hid behind it.
 *
 * The load-bearing case is `attempt cap holds when every caller reads before any
 * caller writes` — that is the exact interleaving the old read-check-write code
 * permitted, and the only one that distinguishes a fused check from a racy one.
 */
class OtpAttemptCapTest extends TestCase
{
    use RefreshDatabase;

    private const TARGET = 'otp-cap@example.test';
    private const CODE = '123456';

    private function seedCode(int $attempts = 0, int $expiresInMinutes = 10): Otp
    {
        Otp::where('email', self::TARGET)->delete();

        return Otp::create([
            'email' => self::TARGET,
            'otp' => self::CODE,
            'type' => OtpType::EMAIL_OTP->value,
            'purpose' => OtpPurpose::ACCOUNT_VERIFICATION->value,
            'attempts' => $attempts,
            'used' => false,
            'expires_at' => now()->addMinutes($expiresInMinutes),
        ]);
    }

    private function service(): OtpService
    {
        return app(OtpService::class);
    }

    private function attempt(string $code): bool
    {
        return $this->service()->validate(self::TARGET, $code, OtpType::EMAIL_OTP);
    }

    /**
     * The regression the expires_at defect caused: one mistyped digit used to
     * expire the code, so the customer could not finish with the right one.
     */
    public function test_a_wrong_guess_does_not_invalidate_the_code(): void
    {
        $record = $this->seedCode();
        $originalExpiry = $record->expires_at->toDateTimeString();

        $this->assertFalse($this->attempt('000000'));

        $record->refresh();

        $this->assertSame(
            $originalExpiry,
            $record->expires_at->toDateTimeString(),
            'expires_at moved after a failed attempt — the ON UPDATE CURRENT_TIMESTAMP defect is back'
        );

        $this->assertTrue($this->attempt(self::CODE), 'the correct code was rejected after one typo');
    }

    public function test_the_attempt_cap_burns_the_code_once_exhausted(): void
    {
        $max = (int) config('otp.max_attempts');

        $this->seedCode();

        for ($i = 1; $i <= $max; $i++) {
            $this->assertFalse($this->attempt(str_pad((string) $i, 6, '0', STR_PAD_LEFT)));
        }

        $record = Otp::where('email', self::TARGET)->first();

        $this->assertSame($max, (int) $record->attempts);
        $this->assertTrue((bool) $record->used, 'the code should be burned once the cap is spent');

        // Even the correct code must now be refused: the cap is what makes a
        // 6-digit secret safe, so exhausting it retires the code outright.
        $this->assertFalse($this->attempt(self::CODE));
    }

    public function test_a_code_can_only_be_redeemed_once(): void
    {
        $this->seedCode();

        $this->assertTrue($this->attempt(self::CODE));
        $this->assertFalse($this->attempt(self::CODE), 'a one-time password was redeemable twice');
    }

    public function test_an_expired_code_is_refused(): void
    {
        $this->seedCode(expiresInMinutes: -1);

        $this->assertFalse($this->attempt(self::CODE));
    }

    /**
     * The AUTH-02 case.
     *
     * Reproduces the interleaving the old code allowed: every caller performs its
     * read while `attempts` is still 0, so an in-PHP `attempts >= max` check
     * passes for all of them. Only a guard fused to the write can cap this.
     *
     * `claimAttempt()` is the unit under test because it is the thing that must
     * be indivisible; driving it directly keeps the test about concurrency rather
     * than about how validate() happens to be structured today.
     */
    public function test_attempt_cap_holds_when_every_caller_reads_before_any_caller_writes(): void
    {
        $max = (int) config('otp.max_attempts');
        $callers = $max + 5;

        $record = $this->seedCode();

        $claim = new \ReflectionMethod(OtpService::class, 'claimAttempt');
        $claim->setAccessible(true);

        $service = $this->service();

        // Phase 1 — every caller reads the pre-increment value, exactly as
        // concurrent HTTP requests would.
        $observed = [];
        for ($i = 0; $i < $callers; $i++) {
            $observed[$i] = (int) Otp::whereKey($record->id)->value('attempts');
        }

        $this->assertSame(
            array_fill(0, $callers, 0),
            $observed,
            'setup failed: callers were meant to observe the same pre-increment value'
        );

        // Phase 2 — each caller now tries to claim a slot.
        $granted = 0;
        for ($i = 0; $i < $callers; $i++) {
            $granted += $claim->invoke($service, $record->id, $max) ? 1 : 0;
        }

        $this->assertSame(
            $max,
            $granted,
            "the cap leaked: {$granted} guesses were evaluated against a cap of {$max}"
        );

        $this->assertSame($max, (int) Otp::whereKey($record->id)->value('attempts'));
    }

    /**
     * The schema half of the fix. A column carrying ON UPDATE CURRENT_TIMESTAMP
     * rewrites itself on every write, which no amount of application code can
     * work around — so assert the column definition itself.
     */
    public function test_expires_at_is_not_rewritten_by_the_database_on_update(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('The ON UPDATE CURRENT_TIMESTAMP defect is MySQL-specific.');
        }

        $extra = DB::selectOne(
            'SELECT EXTRA FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['otps', 'expires_at']
        );

        $this->assertStringNotContainsString(
            'on update',
            strtolower((string) ($extra->EXTRA ?? '')),
            'otps.expires_at auto-updates again — every write silently expires the code'
        );
    }
}
