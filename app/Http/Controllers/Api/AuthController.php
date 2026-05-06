<?php

namespace App\Http\Controllers\Api;

use App\Enum\RegistrationMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AccountVerificationService;
use App\Services\AuthTokenService;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
            return response()->json(['message' => 'This account is inactive.'], 403);
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

    public function refresh(Request $request)
    {
        $request->validate(['refresh_token' => 'required|string']);
        $plain = $request->input('refresh_token');
        $token = $this->tokenService->findValidRefreshToken($plain);

        if (!$token) {
            return response()->json(['message' => 'Invalid or expired refresh token'], 401);
        }

        $user = $token->user;

        if ($user->requiresOtpVerification()) {
            return $this->buildVerificationChallengeResponse(
                user: $user,
                request: $request,
                status: 403,
                message: 'Your account is not verified. A new OTP has been sent to your registered contact.'
            );
        }

        $access = $this->tokenService->createAccessToken($user, $request->header('User-Agent'));

        return response()->json([
            'access_token' => $access['access_token'],
            'access_expires_at' => $access['expires_at'],
            'requires_otp_verification' => false,
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        $user->tokens()->delete();
        $user->refreshTokens()->update(['revoked' => true]);

        return response()->json(['message' => 'Logged out']);
    }

    public function forgotPassword(Request $request, OtpService $otpService)
    {
        $request->validate(['email' => 'required|email']);
        $user = User::query()->where('email', $request->email)->firstOrFail();
        $otp = $otpService->generate($user);

        $response = ['message' => 'OTP sent to email'];

        if (config('app.debug')) {
            $response['otp'] = $otp;
        }

        return response()->json($response);
    }

    public function resetPassword(Request $request, OtpService $otpService)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ]);

        if (!$otpService->validate($request->email, $request->otp)) {
            return response()->json(['error' => 'Invalid or expired OTP'], 422);
        }

        $user = User::query()->where('email', $request->email)->firstOrFail();
        $user->update(['password' => Hash::make($request->password)]);

        return response()->json(['message' => 'Password reset successful']);
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
