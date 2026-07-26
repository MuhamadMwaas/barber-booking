<?php

namespace App\Http\Controllers\Api;

use App\Enum\OtpType;
use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\VerifyPasswordResetOtpRequest;
use App\Models\User;
use App\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Forgot-password over email OR SMS.
 *
 * Split out of AuthController/OtpController because account *activation* and
 * password *recovery* are different security domains: they use codes of
 * different purposes (see App\Enum\OtpPurpose), and only this flow is allowed
 * to change a password and invalidate every existing session.
 *
 * Endpoints (all public, all rate-limited in routes/api.php):
 *   POST /auth/forgot-password       → send a code
 *   POST /auth/password/verify-otp   → exchange the code for a reset_token
 *   POST /auth/reset-password        → set the new password
 */
class PasswordResetController extends Controller
{
    public function __construct(
        private PasswordResetService $passwordResetService,
    ) {
    }

    /**
     * Step 1 — deliver a password-reset code to the requested destination.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->applyRequestLocale($request);

        $method = $request->registrationMethod();
        $channel = $this->passwordResetService->channelFor($method);
        $identifier = $request->identifier();

        $user = $this->passwordResetService->findUser($method, $identifier);

        if (!$user) {
            return $this->error('USER_NOT_FOUND', __('passwords.user'), 404);
        }

        if ($guard = $this->guardAccount($user)) {
            return $guard;
        }

        // Per-destination cooldown on top of the route throttle: the route limit
        // is per IP, this one stops a rotating-IP client from burning SMS credit
        // on a single number.
        $retryAfter = $this->passwordResetService->cooldownRemaining($identifier, $channel);

        if ($retryAfter > 0) {
            return response()->json([
                'success' => false,
                'error_code' => 'OTP_COOLDOWN',
                'message' => __('passwords.cooldown', ['seconds' => $retryAfter]),
                'retry_after' => $retryAfter,
            ], 429);
        }

        $result = $this->passwordResetService->sendResetOtp($user, $channel, $identifier);

        $response = [
            'success' => true,
            'message' => $channel === OtpType::SMS_OTP
                ? __('passwords.sent_sms')
                : __('passwords.sent_email'),
            'registration_method' => $method->value,
            'verification_channel' => $method->value,
            'masked_destination' => $result['masked_destination'],
            'expires_in' => $result['expires_in'],
            'resend_after' => $result['resend_after'],
        ];

        if ($this->shouldExposeOtp($channel)) {
            $response['otp'] = $result['otp'];
        }

        return response()->json($response);
    }

    /**
     * Step 2 — trade a correct code for a short-lived, single-use reset token.
     */
    public function verifyOtp(VerifyPasswordResetOtpRequest $request): JsonResponse
    {
        $this->applyRequestLocale($request);

        $method = $request->registrationMethod();
        $channel = $this->passwordResetService->channelFor($method);
        $identifier = $request->identifier();

        $user = $this->passwordResetService->findUser($method, $identifier);

        if (!$user) {
            return $this->error('USER_NOT_FOUND', __('passwords.user'), 404);
        }

        if ($guard = $this->guardAccount($user)) {
            return $guard;
        }

        if (!$this->passwordResetService->verifyOtp($identifier, (string) $request->input('otp'), $channel)) {
            return $this->error('INVALID_OTP', __('passwords.token'), 422);
        }

        $grant = $this->passwordResetService->issueGrant(
            user: $user,
            channel: $channel,
            ip: $request->ip(),
            userAgent: $request->header('User-Agent'),
        );

        return response()->json([
            'success' => true,
            'message' => __('passwords.otp_verified'),
            'reset_token' => $grant['token'],
            'expires_at' => $grant['expires_at']->toIso8601String(),
        ]);
    }

    /**
     * Step 3 — set the new password.
     *
     * Accepts either the `reset_token` from step 2 (preferred) or a raw `otp`
     * plus destination, which is the one-shot shape older clients still send.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->applyRequestLocale($request);

        $password = (string) $request->input('password');

        if ($request->usesGrantToken()) {
            $grant = $this->passwordResetService->findRedeemableGrant((string) $request->input('reset_token'));

            if (!$grant || !$grant->user) {
                return $this->error('INVALID_RESET_TOKEN', __('passwords.token'), 422);
            }

            if ($guard = $this->guardAccount($grant->user)) {
                return $guard;
            }

            $this->passwordResetService->resetPassword($grant->user, $password, $grant->channel, $grant);

            return $this->success(__('passwords.reset'));
        }

        // Legacy single-request path.
        $method = $request->registrationMethod();
        $channel = $this->passwordResetService->channelFor($method);
        $identifier = $request->identifier();

        $user = $this->passwordResetService->findUser($method, $identifier);

        if (!$user) {
            return $this->error('USER_NOT_FOUND', __('passwords.user'), 404);
        }

        if ($guard = $this->guardAccount($user)) {
            return $guard;
        }

        if (!$this->passwordResetService->verifyOtp($identifier, (string) $request->input('otp'), $channel)) {
            return $this->error('INVALID_OTP', __('passwords.token'), 422);
        }

        $this->passwordResetService->resetPassword($user, $password, $channel);

        return $this->success(__('passwords.reset'));
    }

    /**
     * A deactivated account must not be recoverable — that is an admin decision,
     * not something a password reset may undo.
     */
    private function guardAccount(User $user): ?JsonResponse
    {
        if (!$user->is_active) {
            return $this->error('ACCOUNT_DISABLED', __('auth.account_disabled'), 403);
        }

        return null;
    }

    /**
     * Expose the code in the response only where it cannot actually be delivered
     * (SMS gateway switched off) or while debugging locally. Mirrors the
     * behaviour of PhoneVerificationController::sendOtp().
     */
    private function shouldExposeOtp(OtpType $channel): bool
    {
        if (config('app.debug')) {
            return true;
        }

        return $channel === OtpType::SMS_OTP && !config('services.vonage.enabled', false);
    }

    private function success(string $message): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error_code' => $code,
            'message' => $message,
        ], $status);
    }

    /** Honour an explicit `locale` field, then Accept-Language. */
    private function applyRequestLocale(Request $request): void
    {
        $supported = ['de', 'ar', 'en'];

        $locale = $request->input('locale');

        if (!$locale) {
            $header = (string) $request->header('Accept-Language', '');
            $primary = strtolower(substr(trim(explode(',', $header)[0]), 0, 2));
            $locale = $primary ?: null;
        }

        if ($locale && in_array($locale, $supported, true)) {
            app()->setLocale($locale);
        }
    }
}
