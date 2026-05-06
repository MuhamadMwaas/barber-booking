<?php

namespace App\Services;

use App\Enum\OtpType;
use App\Mail\SendOtpMail;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class OtpDeliveryService
{
    public function deliver(User $user, string $otp, Carbon $expiresAt, OtpType $type): void
    {
        if ($type === OtpType::EMAIL_OTP) {
            Mail::to($user->email)->send(
                new SendOtpMail(
                    otp: $otp,
                    userName: $user->full_name,
                    expiresAt: $expiresAt,
                )
            );

            return;
        }

        if ($type === OtpType::SMS_OTP) {
            $this->sendSmsOtp($user, $otp, $expiresAt);
        }
    }

    protected function sendSmsOtp(User $user, string $otp, Carbon $expiresAt): void
    {
        $phone = $user->phone;

        if (!$phone) {
            throw new RuntimeException('Cannot send SMS OTP without a phone number.');
        }

        $config = config('services.vonage', []);
        $enabled = (bool) ($config['enabled'] ?? false);
        $key = $config['key'] ?? null;
        $secret = $config['secret'] ?? null;
        $from = $config['from'] ?? null;
        $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://rest.nexmo.com'), '/');

        if (!$enabled || !$key || !$secret || !$from) {
            Log::info('SMS OTP delivery skipped because Vonage is not fully configured.', [
                'user_id' => $user->id,
                'phone' => $phone,
                'expires_at' => $expiresAt->toIso8601String(),
            ]);

            return;
        }

        try {
            $response = Http::asForm()->post($baseUrl . '/sms/json', [
                'api_key' => $key,
                'api_secret' => $secret,
                'to' => $phone,
                'from' => $from,
                'text' => sprintf('Your OTP code is %s. It expires in 10 minutes.', $otp),
            ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Failed to connect to the Vonage SMS API.', previous: $exception);
        }

        $message = $response->json('messages.0');

        if (!$response->successful() || !is_array($message) || ($message['status'] ?? '0') !== '0') {
            throw new RuntimeException('Vonage SMS delivery failed.');
        }
    }
}