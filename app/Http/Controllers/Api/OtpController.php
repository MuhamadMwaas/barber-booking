<?php

namespace App\Http\Controllers\Api;

use App\Enum\OtpType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class OtpController extends Controller
{
    public function requestOtp(Request $request, OtpService $otpService)
    {
        $request->validate([
            'type' => 'required|integer|in:' . OtpType::EMAIL_OTP->value . ',' . OtpType::SMS_OTP->value,
        ]);

        $type = OtpType::from((int) $request->type);

        if ($type === OtpType::EMAIL_OTP) {
            $request->validate(['email' => 'required|email']);
            $user = User::where('email', $request->email)->firstOrFail();
            $message = $user->email;
        } else {
            $request->validate(['phone' => 'required|string']);
            $user = User::where('phone', $request->phone)->firstOrFail();
            $message = $user->phone;
        }

        $otp = $otpService->generate($user, (int) env('OTP_LENGTH', 6), $type);

        $response = ['message' => 'OTP sent to ' . $message];

        if (config('app.debug')) {
            $response['otp'] = $otp;
        }

        return response()->json($response);
    }

    public function verifyOtp(Request $request, OtpService $otpService)
    {
        $request->validate([
            'type' => 'required|integer|in:' . OtpType::EMAIL_OTP->value . ',' . OtpType::SMS_OTP->value,
            'otp' => 'required|string',
        ]);

        $type = OtpType::from((int) $request->type);

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

        if (!$otpService->validate($identifier, $request->otp, $type)) {
            return response()->json(['error' => 'Invalid or expired OTP'], 422);
        }

        return response()->json(['message' => 'OTP verified']);
    }

    public function resetPassword(Request $request, OtpService $otpService)
    {
        $request->validate([
            'type' => 'required|integer|in:' . OtpType::EMAIL_OTP->value . ',' . OtpType::SMS_OTP->value,
        ]);

        $type = OtpType::from((int) $request->type);

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

        if (!$otpService->validate($identifier, $validated['otp'], $type)) {
            return response()->json(['error' => 'Invalid or expired OTP'], 422);
        }

        $user = $type === OtpType::EMAIL_OTP
            ? User::where('email', $validated['email'])->firstOrFail()
            : User::where('phone', $validated['phone'])->firstOrFail();

        $user->update(['password' => Hash::make($validated['password'])]);

        return response()->json(['message' => 'Password reset successful']);
    }

    public function verifyEmailViaOtp(Request $request, OtpService $otpService)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
        ]);

        if (!$otpService->validate($request->email, $request->otp, OtpType::EMAIL_OTP)) {
            return response()->json(['error' => 'Invalid or expired OTP'], 422);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $user->update([
            'email_verified_at' => now(),
            'email_verified_via_otp_at' => now(),
        ]);

        return response()->json([
            'message' => 'Email verified successfully',
            'email_verified' => true,
        ]);
    }

    public function resendOtpForEmailVerification(Request $request, OtpService $otpService)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->firstOrFail();

        if ($user->email_verified_via_otp_at) {
            return response()->json([
                'message' => 'Email already verified',
            ], 400);
        }

        $otp = $otpService->generate($user, (int) env('OTP_LENGTH', 6), OtpType::EMAIL_OTP);

        $response = [
            'message' => 'OTP sent to your email',
        ];

        if (config('app.debug')) {
            $response['otp'] = $otp;
        }

        return response()->json($response);
    }
}
