<?php

namespace App\Http\Controllers\Api;

use App\Enum\OtpType;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class OtpController
{
    public function requestOtp(Request $request, OtpService $otpService)
    {
        if ($request->type == OtpType::EMAIL_OTP->value) {
            $request->validate(['email' => 'required|email']);
            $user = User::where('email', $request->email)->firstOrFail();
            $message = $user->email;


        } elseif ($request->type == OtpType::SMS_OTP->value) {
            $request->validate(['phone' => 'required|string']);
            $user = User::where('phone', $request->phone)->firstOrFail();
            $message = $user->phone;

        }
        $otpService->generate($user, env('OTP_LENGTH', 6), OtpType::EMAIL_OTP);

        return response()->json(['message' => 'OTP sent to ' . $message]);
    }

    public function verifyOtp(Request $request, OtpService $otpService)
    {
        if ($request->type == OtpType::EMAIL_OTP->value) {
            $request->validate([
                'email' => 'required|email',
                'otp' => 'required|string',
            ]);
            if (!$otpService->validate($request->email, $request->otp)) {
                return response()->json(['error' => 'Invalid or expired OTP'], 422);
            }


        } elseif ($request->type == OtpType::SMS_OTP->value) {
            $request->validate([
                'phone' => 'required',
                'otp' => 'required|string',
            ]);
            if (!$otpService->validate($request->phone, $request->otp, OtpType::SMS_OTP)) {
                return response()->json(['error' => 'Invalid or expired OTP'], 422);
            }

        }

        if (!$otpService->validate($request->email, $request->otp)) {
            return response()->json(['error' => 'Invalid or expired OTP'], 422);
        }

        return response()->json(['message' => 'OTP verified']);
    }

    public function resetPassword(Request $request, OtpService $otpService)
    {
        $rules = [
            'otp' => 'required|string',
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ];

        if ($request->type == OtpType::EMAIL_OTP->value) {
            $rules['email'] = 'required|email';
        } elseif ($request->type == OtpType::SMS_OTP->value) {
            $rules['phone'] = 'required|string';
        }

        $validated = $request->validate($rules);

        $identifier = $request->type == OtpType::EMAIL_OTP->value
            ? $validated['email']
            : $validated['phone'];

        if (!$otpService->validate($identifier, $validated['otp'], OtpType::tryFrom($request->type))) {
            return response()->json(['error' => 'Invalid or expired OTP'], 422);
        }

        $user = $request->type == OtpType::EMAIL_OTP->value
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
            'email_verified_via_otp_at' => now()
        ]);

        return response()->json([
            'message' => 'Email verified successfully',
            'email_verified' => true
        ]);
    }

    public function resendOtpForEmailVerification(Request $request, OtpService $otpService)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        $user = User::where('email', $request->email)->firstOrFail();

        if ($user->email_verified_via_otp_at) {
            return response()->json([
                'message' => 'Email already verified'
            ], 400);
        }

        $otp = $otpService->generate($user, env('OTP_LENGTH', 6), \App\Enum\OtpType::EMAIL_OTP);

        return response()->json([
            'message' => 'OTP sent to your email',
            'otp' => $otp
        ]);
    }
}
