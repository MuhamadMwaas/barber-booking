<?php

namespace App\Http\Controllers\Api;

use App\Enum\OtpType;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Otp;
use App\Models\User;
use App\Services\AccountVerificationService;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Authenticated, post-login phone-number verification.
 *
 * This is intentionally SEPARATE from the account-activation OTP flow in
 * OtpController. There the user is anonymous and the endpoint mints tokens.
 * Here the user is ALREADY logged in (their account is verified via email),
 * so every action is scoped to $request->user() and NO tokens are issued —
 * we only flip phone_verified_at on success.
 */
class PhoneVerificationController extends Controller
{
    /** Minimum delay between two SMS OTP requests for the same number. */
    private const RESEND_COOLDOWN_SECONDS = 60;

    public function __construct(
        private OtpService $otpService,
        private AccountVerificationService $verificationService,
    ) {
    }

    /**
     * Send an SMS OTP to the account's phone number.
     *
     * The client MAY pass a `phone` to set/correct the number first. Changing
     * the number clears any previous verification so we always verify the
     * number we are actually about to text.
     */
    public function sendOtp(Request $request)
    {
        $request->validate([
            'phone' => ['sometimes', 'string', 'max:20'],
        ]);

        /** @var User $user */
        $user = $request->user();

        // (1) Adopt a new/edited phone number before sending, if provided.
        if ($request->filled('phone')) {
            $newPhone = trim((string) $request->input('phone'));

            if ($newPhone !== (string) $user->phone) {
                $this->ensurePhoneIsAvailable($newPhone, $user);

                $user->forceFill([
                    'phone' => $newPhone,
                    'phone_verified_at' => null,
                ])->save();
            }
        }

        // (2) A target phone is required.
        if (!$user->phone) {
            throw ValidationException::withMessages([
                'phone' => ['No phone number is associated with this account. Please provide one to verify.'],
            ]);
        }

        // (3) Nothing to do if it is already verified.
        if ($user->phone_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Your phone number is already verified.',
                'phone_verified' => true,
            ], 400);
        }

        // (4) Throttle resends per number.
        $remaining = $this->cooldownRemaining($user);
        if ($remaining > 0) {
            return response()->json([
                'success' => false,
                'message' => "Please wait {$remaining} seconds before requesting a new code.",
                'retry_after' => $remaining,
            ], 429);
        }

        $otp = $this->otpService->generate($user, (int) env('OTP_LENGTH', 6), OtpType::SMS_OTP);

        $response = [
            'success' => true,
            'message' => 'A verification code has been sent to your phone number.',
            'masked_destination' => $this->verificationService->maskTarget($user->phone, OtpType::SMS_OTP),
            'phone_verified' => false,
        ];

        // Testing convenience: while there is no real SMS gateway to deliver the
        // code, return it in the response so the flow can be tested. As soon as
        // Vonage is enabled (VONAGE_SMS_ENABLED=true) the code is sent by SMS and
        // is NO LONGER exposed here. `app.debug` keeps it available locally too.
        $smsEnabled = (bool) config('services.vonage.enabled', false);
        if (!$smsEnabled || config('app.debug')) {
            $response['otp'] = $otp;
        }

        return response()->json($response);
    }

    /**
     * Verify the SMS OTP and mark the account's phone as verified.
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp' => ['required', 'string'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (!$user->phone) {
            throw ValidationException::withMessages([
                'phone' => ['No phone number is associated with this account.'],
            ]);
        }

        if ($user->phone_verified_at) {
            return response()->json([
                'success' => true,
                'message' => 'Your phone number is already verified.',
                'data' => new UserResource($user),
            ]);
        }

        if (!$this->otpService->validate($user->phone, (string) $request->input('otp'), OtpType::SMS_OTP)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired verification code.',
            ], 422);
        }

        // Sets phone_verified_at. No tokens — the user is already authenticated.
        $user = $this->verificationService->markVerified($user, OtpType::SMS_OTP);

        return response()->json([
            'success' => true,
            'message' => 'Your phone number has been verified successfully.',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Reject a phone number that is already taken by another account, matching
     * the database-level unique(phone) constraint with a friendly 422.
     */
    private function ensurePhoneIsAvailable(string $phone, User $user): void
    {
        $exists = User::query()
            ->where('phone', $phone)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'phone' => ['This phone number is already in use by another account.'],
            ]);
        }
    }

    /**
     * Seconds the user must still wait before another OTP may be sent, or 0.
     */
    private function cooldownRemaining(User $user): int
    {
        $lastOtp = Otp::query()
            ->where('phone', $user->phone)
            ->where('type', OtpType::SMS_OTP->value)
            ->latest('created_at')
            ->first();

        if (!$lastOtp || !$lastOtp->created_at) {
            return 0;
        }

        $elapsed = now()->getTimestamp() - $lastOtp->created_at->getTimestamp();

        return $elapsed >= self::RESEND_COOLDOWN_SECONDS
            ? 0
            : self::RESEND_COOLDOWN_SECONDS - $elapsed;
    }
}
