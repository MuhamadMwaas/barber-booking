<?php

namespace Tests\Unit\Mail;

use App\Mail\SendOtpMail;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use PHPUnit\Framework\TestCase;

class SendOtpMailTest extends TestCase
{
    public function test_it_accepts_a_base_carbon_instance_for_expiration(): void
    {
        $expiresAt = Carbon::now()->addMinutes(10);

        $mail = new SendOtpMail(
            otp: '123456',
            userName: 'Test User',
            expiresAt: $expiresAt,
        );

        $this->assertInstanceOf(CarbonInterface::class, $mail->expiresAt);
        $this->assertSame($expiresAt, $mail->expiresAt);
        $this->assertSame($expiresAt->format('Y-m-d H:i'), $mail->expiresAt->format('Y-m-d H:i'));
    }
}