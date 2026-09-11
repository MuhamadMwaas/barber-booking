<?php

namespace App\Http\Controllers\Api;

use App\Enum\RegistrationMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\RefreshToken;
use App\Models\User;
use App\Services\AccountVerificationService;
use App\Services\AuthTokenService;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    public function __construct(
        private AuthTokenService $tokenService,
        private OtpService $otpService,
        private AccountVerificationService $verificationService,
    )
    {
    }

    public function register(RegisterRequest $request)
    {
        $data = $request->validated();
        $registrationMethod = RegistrationMethod::from($data['registration_method']);

        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'registration_method' => $registrationMethod,
            'password' => bcrypt($data['password']),
        ]);

        Role::findOrCreate('customer', 'web');
        $user->assignRole('customer');

        $otpType = $this->verificationService->resolveOtpType($user, $registrationMethod);
        $otp = $this->otpService->generate($user, (int) env('OTP_LENGTH', 6), $otpType);

        $response = array_merge([
            'user' => new UserResource($user),
            'message' => $registrationMethod === RegistrationMethod::EMAIL
                ? 'Registration successful. Please verify your email using the OTP sent to your email.'
                : 'Registration successful. Please verify your phone number using the OTP sent to your phone.',
        ], $this->verificationService->buildVerificationPayload($user, $registrationMethod));

        if (config('app.debug')) {
            $response['otp'] = $otp;
        }

        return response()->json($response, 201);
    }

    public function login(LoginRequest $request)
    {
        $data = $request->validated();
        $registrationMethod = RegistrationMethod::from($data['registration_method']);
        $user = $registrationMethod === RegistrationMethod::PHONE
            ? User::query()->where('phone', $data['phone'])->first()
            : User::query()->where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (!$user->is_active) {
            $this->applyRequestLocale($request);

            return response()->json([
                'success' => false,
                'message' => __('auth.account_disabled'),
            ], 403);
        }

        if ($user->requiresOtpVerification()) {
            return $this->buildVerificationChallengeResponse(
                user: $user,
                request: $request,
                status: 403,
                message: 'Your account is not verified. A new OTP has been sent to your registered contact.'
            );
        }

        return response()->json($this->buildAuthenticatedResponse($user, $request));
    }

    /**
     * Exchange a refresh token for a new access token - and, with rotation on,
     * for a new refresh token as well (AUTH-04).
     *
     * The 15-minute access lifetime only limits an attacker if the refresh
     * token behind it is not itself a permanent credential. Rotation makes each
     * refresh token single-use, and single-use is what turns a silent leak into
     * a detectable event: see handleUnusableRefreshToken() below.
     *
     * NOTE FOR CLIENTS: the response now carries a `refresh_token`. It must be
     * stored and used for the next refresh; the one just sent is dead.
     */
    public function refresh(Request $request)
    {
        $request->validate(['refresh_token' => 'required|string']);
        $plain = $request->input('refresh_token');
        $token = $this->tokenService->findValidRefreshToken($plain);

        if (!$token) {
            return $this->handleUnusableRefreshToken($plain);
        }

        $user = $token->user;

        if (!$user) {
            return response()->json(['message' => 'Invalid or expired refresh token'], 401);
        }

        if ($user->requiresOtpVerification()) {
            // Deliberately BEFORE rotation. This path issues no access token, so
            // spending the refresh token here would cost an unverified user
            // their session for nothing: they would answer the OTP challenge and
            // find the token they still hold already dead.
            return $this->buildVerificationChallengeResponse(
                user: $user,
                request: $request,
                status: 403,
                message: 'Your account is not verified. A new OTP has been sent to your registered contact.'
            );
        }

        $rotated = null;

        if (config('auth_tokens.rotate_refresh_tokens', true)) {
            $rotated = $this->tokenService->rotateRefreshToken(
                $token,
                $request->header('User-Agent'),
                $request->ip()
            );

            if (!$rotated) {
                // Another request spent this same token between our lookup and
                // our write. Exactly one refresh may succeed per token, and it
                // was not this one.
                return response()->json(['message' => 'Invalid or expired refresh token'], 401);
            }
        }

        $access = $this->tokenService->createAccessToken($user, $request->header('User-Agent'));

        $response = [
            'access_token' => $access['access_token'],
            'access_expires_at' => $access['expires_at'],
            'requires_otp_verification' => false,
        ];

        if ($rotated) {
            $response['refresh_token'] = $rotated['refresh_token'];
            $response['refresh_expires_at'] = $rotated['expires_at'];
        }

        return response()->json($response);
    }

    /**
     * Decide whether a rejected refresh token is noise or evidence of theft.
     *
     * Three cases hide behind one 401:
     *
     *   1. We have never issued this string, or it has merely expired.
     *      Nothing to learn - answer and move on.
     *
     *   2. It was revoked on purpose (logout, password change, reset). The
     *      client is stale, not compromised. Also nothing to learn - and this
     *      is why revoked_reason exists: without it, every ordinary logout
     *      followed by a retry would look exactly like an attack.
     *
     *   3. It was ROTATED - spent on a successful refresh - and has come back.
     *      A spent token can only reappear if a second copy of it exists. The
     *      legitimate holder cannot produce it, because they were handed the
     *      replacement. This is the leak signal, and the correct response is
     *      not to reject this one request but to destroy every session for the
     *      account: we cannot tell whether the caller is the thief or the
     *      victim, and both must be forced to re-authenticate.
     *
     * Case 3 is softened only inside the grace window, where an honest client
     * that lost our reply is the likelier explanation than a thief.
     */
    private function handleUnusableRefreshToken(string $plain)
    {
        $rejected = response()->json(['message' => 'Invalid or expired refresh token'], 401);

        $record = $this->tokenService->findRefreshTokenRecord($plain);

        // Case 1 - unknown to us, or live-but-expired.
        if (!$record || !$record->revoked) {
            return $rejected;
        }

        // Case 2 - a deliberate revocation, not a spent token.
        if ($record->revoked_reason !== RefreshToken::REASON_ROTATED) {
            return $rejected;
        }

        // Case 3, softened - a retry of a reply the client never received.
        $graceSeconds = (int) config('auth_tokens.reuse_grace_seconds', 60);

        if ($record->revoked_at && $record->revoked_at->gt(now()->subSeconds($graceSeconds))) {
            return $rejected;
        }

        // Case 3 - a leak. Burn the account's sessions down.
        Log::warning('Refresh token reuse detected: revoking all sessions for the account', [
            'user_id' => $record->user_id,
            'refresh_token_id' => $record->id,
            'rotated_at' => $record->revoked_at?->toDateTimeString(),
            'issued_to_device' => $record->device,
            'issued_to_ip' => $record->ip,
            'replayed_from_ip' => request()->ip(),
            'replayed_by_device' => request()->header('User-Agent'),
        ]);

        if ($user = $record->user) {
            $this->tokenService->revokeAllSessions($user, RefreshToken::REASON_REUSE_DETECTED);
        }

        return $rejected;
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        // Stamped as a deliberate revocation so that a stale client replaying
        // this token later is answered with a plain 401 instead of being
        // mistaken for a leak.
        $this->tokenService->revokeAllSessions($user, RefreshToken::REASON_LOGOUT);

        return response()->json(['message' => 'Logged out']);
    }

    private function buildAuthenticatedResponse(User $user, Request $request): array
    {
        $access = $this->tokenService->createAccessToken($user, $request->header('User-Agent'));
        $refresh = $this->tokenService->createRefreshToken($user, $request->header('User-Agent'), $request->ip());

        return array_merge([
            'user' => new UserResource($user),
            'access_token' => $access['access_token'],
            'access_expires_at' => $access['expires_at'],
            'refresh_token' => $refresh['refresh_token'],
            'refresh_expires_at' => $refresh['expires_at'],
            'token_type' => 'bearer',
        ], $this->verificationService->buildVerificationPayload($user));
    }

    private function applyRequestLocale(Request $request): void
    {
        $supported = ['de', 'ar', 'en'];

        // 1. Explicit query / body param
        $locale = $request->input('locale');

        // 2. Accept-Language header  (e.g. "ar-SA,ar;q=0.9,en;q=0.8")
        if (!$locale) {
            $header = $request->header('Accept-Language', '');
            $primary = strtolower(substr(trim(explode(',', $header)[0]), 0, 2));
            $locale = $primary ?: null;
        }

        if ($locale && in_array($locale, $supported, true)) {
            app()->setLocale($locale);
        }
    }

    private function buildVerificationChallengeResponse(User $user, Request $request, int $status, string $message)
    {
        $otpType = $this->verificationService->resolveOtpType($user);
        $otp = $this->otpService->generate($user, (int) env('OTP_LENGTH', 6), $otpType);

        $response = array_merge([
            'user' => new UserResource($user),
            'message' => $message,
        ], $this->verificationService->buildVerificationPayload($user));

        if (config('app.debug')) {
            $response['otp'] = $otp;
        }

        return response()->json($response, $status);
    }
}
