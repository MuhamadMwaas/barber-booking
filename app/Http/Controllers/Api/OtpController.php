<?php

namespace App\Http\Controllers\Api;

use App\Enum\OtpType;
use App\Enum\RegistrationMethod;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AccountVerificationService;
use App\Services\AuthTokenService;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OtpController extends Controller
{
    public function __construct(
        private OtpService $otpService,
        private AccountVerificationService $verificationService,
        private AuthTokenService $tokenService,
    ) {
    }

    public function requestOtp(Request $request)
    {
        $request->validate([
            'registration_method' => ['sometimes', Rule::enum(RegistrationMethod::class)],
            'type' => 'sometimes|integer|in:' . OtpType::EMAIL_OTP->value . ',' . OtpType::SMS_OTP->value,
        ]);

        $registrationMethod = $this->resolveRegistrationMethod($request);
        $type = $registrationMethod === RegistrationMethod::PHONE ? OtpType::SMS_OTP : OtpType::EMAIL_OTP;

        if ($type === OtpType::EMAIL_OTP) {
            $request->validate(['email' => 'required|email']);
            $user = User::query()->where('email', $request->email)->firstOrFail();
        } else {
            $request->validate(['phone' => 'required|string']);
            $user = User::query()->where('phone', $request->phone)->firstOrFail();
        }

        $otp = $this->otpService->generate($user, (int) env('OTP_LENGTH', 6), $type);

        $response = array_merge([
            'message' => 'OTP sent successfully.',
        ], $this->verificationService->buildVerificationPayload($user, $registrationMethod));

        if (config('app.debug')) {
            $response['otp'] = $otp;
        }

        return response()->json($response);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'registration_method' => ['sometimes', Rule::enum(RegistrationMethod::class)],
            'type' => 'sometimes|integer|in:' . OtpType::EMAIL_OTP->value . ',' . OtpType::SMS_OTP->value,
            'otp' => 'required|string',
        ]);

        $registrationMethod = $this->resolveRegistrationMethod($request);
        $type = $registrationMethod === RegistrationMethod::PHONE ? OtpType::SMS_OTP : OtpType::EMAIL_OTP;

        if ($type === OtpType::EMAIL_OTP) {
            $request->validate([
                'email' => 'required|email',
            ]);
            $identifier = $request->email;
        } else {
            $request->validate([
                'phone' => 'required|string',
            ]);
            $identifier = $request->phone;
        }

        if (!$this->otpService->validate($identifier, $request->otp, $type)) {
            return response()->json(['error' => 'Invalid or expired OTP'], 422);
        }

        $user = $type === OtpType::EMAIL_OTP
            ? User::query()->where('email', $identifier)->firstOrFail()
            : User::query()->where('phone', $identifier)->firstOrFail();

        $user = $this->verificationService->markVerified($user, $type);

        $access = $this->tokenService->createAccessToken($user, $request->header('User-Agent'));
        $refresh = $this->tokenService->createRefreshToken($user, $request->header('User-Agent'), $request->ip());

        return response()->json(array_merge([
            'message' => $type === OtpType::EMAIL_OTP
                ? 'Email verified successfully.'
                : 'Phone number verified successfully.',
            'user' => new UserResource($user),
            'access_token' => $access['access_token'],
            'access_expires_at' => $access['expires_at'],
            'refresh_token' => $refresh['refresh_token'],
            'refresh_expires_at' => $refresh['expires_at'],
            'token_type' => 'bearer',
        ], $this->verificationService->buildVerificationPayload($user, $registrationMethod)));
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'registration_method' => ['sometimes', Rule::enum(RegistrationMethod::class)],
            'type' => 'sometimes|integer|in:' . OtpType::EMAIL_OTP->value . ',' . OtpType::SMS_OTP->value,
        ]);

        $registrationMethod = $this->resolveRegistrationMethod($request);
        $type = $registrationMethod === RegistrationMethod::PHONE ? OtpType::SMS_OTP : OtpType::EMAIL_OTP;

        $rules = [
            'otp' => 'required|string',
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ];

        if ($type === OtpType::EMAIL_OTP) {
            $rules['email'] = 'required|email';
        } else {
            $rules['phone'] = 'required|string';
        }

        $validated = $request->validate($rules);

        $identifier = $type === OtpType::EMAIL_OTP
            ? $validated['email']
            : $validated['phone'];

        if (!$this->otpService->validate($identifier, $validated['otp'], $type)) {
            return response()->json(['error' => 'Invalid or expired OTP'], 422);
        }

        $user = $type === OtpType::EMAIL_OTP
            ? User::query()->where('email', $validated['email'])->firstOrFail()
            : User::query()->where('phone', $validated['phone'])->firstOrFail();

        $user->update(['password' => Hash::make($validated['password'])]);

        return response()->json(['message' => 'Password reset successful']);
    }

    public function verifyEmailViaOtp(Request $request)
    {
        $request->merge([
            'registration_method' => RegistrationMethod::EMAIL->value,
        ]);

        return $this->verifyOtp($request);
    }

    public function resendVerificationOtp(Request $request)
    {
        $request->validate([
            'registration_method' => ['sometimes', Rule::enum(RegistrationMethod::class)],
            'type' => 'sometimes|integer|in:' . OtpType::EMAIL_OTP->value . ',' . OtpType::SMS_OTP->value,
        ]);

        $registrationMethod = $this->resolveRegistrationMethod($request);
        $otpType = $registrationMethod === RegistrationMethod::PHONE ? OtpType::SMS_OTP : OtpType::EMAIL_OTP;

        if ($registrationMethod === RegistrationMethod::EMAIL) {
            $request->validate([
                'email' => 'required|email|exists:users,email',
            ]);

            $user = User::query()->where('email', $request->email)->firstOrFail();
        } else {
            $request->validate([
                'phone' => 'required|string|exists:users,phone',
            ]);

            $user = User::query()->where('phone', $request->phone)->firstOrFail();
        }

        if ($user->is_account_verified) {
            return response()->json([
                'message' => 'Account already verified.',
            ], 400);
        }

        $otp = $this->otpService->generate($user, (int) env('OTP_LENGTH', 6), $otpType);

        $response = array_merge([
            'message' => $registrationMethod === RegistrationMethod::EMAIL
                ? 'OTP sent to your email.'
                : 'OTP sent to your phone.',
        ], $this->verificationService->buildVerificationPayload($user, $registrationMethod));

        if (config('app.debug')) {
            $response['otp'] = $otp;
        }

        return response()->json($response);
    }

    public function resendOtpForEmailVerification(Request $request)
    {
        $request->merge([
            'registration_method' => RegistrationMethod::EMAIL->value,
        ]);

        return $this->resendVerificationOtp($request);
    }

    private function resolveRegistrationMethod(Request $request): RegistrationMethod
    {
        if ($request->filled('registration_method')) {
            return RegistrationMethod::from(strtolower((string) $request->input('registration_method')));
        }

        if ($request->filled('type')) {
            return (int) $request->input('type') === OtpType::SMS_OTP->value
                ? RegistrationMethod::PHONE
                : RegistrationMethod::EMAIL;
        }

        throw ValidationException::withMessages([
            'registration_method' => ['The registration_method field is required.'],
        ]);
    }
}
