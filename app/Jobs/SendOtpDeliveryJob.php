<?php

namespace App\Jobs;

use App\Enum\OtpType;
use App\Models\User;
use App\Services\OtpDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class SendOtpDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $userId,
        public string $otp,
        public OtpType $type,
        public string $expiresAt,
    ) {
    }

    public function handle(OtpDeliveryService $otpDeliveryService): void
    {
        $user = User::query()->find($this->userId);

        if (!$user) {
            return;
        }

        $otpDeliveryService->deliver(
            user: $user,
            otp: $this->otp,
            expiresAt: Carbon::parse($this->expiresAt),
            type: $this->type,
        );
    }
}