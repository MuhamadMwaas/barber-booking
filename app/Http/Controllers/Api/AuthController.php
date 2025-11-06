<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthTokenService;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Http\Request;

class AuthController
{
    public function __construct(private AuthTokenService $tokenService,private OtpService $otpService)
    {
    }

    public function register(RegisterRequest $request)
    {
        $data = $request->validated();
        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name'=> $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => bcrypt($data['password']),

        ]);

        // $user->sendEmailVerificationNotification();
        $otp = $this->otpService->generate($user, env('OTP_LENGTH', 6), \App\Enum\OtpType::EMAIL_OTP);
    // return response()->json([
    //     'message' => 'Registration successful. Please verify your email using the OTP sent to your email.',
    //     'user' => $user->only(['id', 'first_name', 'last_name', 'email', 'phone']),
    //     'email_verified' => false,
    //     'requires_otp_verification' => true,
    //     'otp' => $otp
    // ], 201);
        // اصدار tokens
        $access = $this->tokenService->createAccessToken($user, $request->header('User-Agent'));
        $refresh = $this->tokenService->createRefreshToken($user, $request->header('User-Agent'), $request->ip());

        return response()->json([
            'user' => new UserResource($user),
            'access_token' => $access['access_token'],
            'access_expires_at' => $access['expires_at'],
            'refresh_token' => $refresh['refresh_token'],
            'refresh_expires_at' => $refresh['expires_at'],
            'token_type' => 'bearer',
            'otp' => $otp
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $data = $request->validated();
        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // (اختياري) تحقق من email verified إذا أردت
        $access = $this->tokenService->createAccessToken($user, $request->header('User-Agent'));
        $refresh = $this->tokenService->createRefreshToken($user, $request->header('User-Agent'), $request->ip());

        return response()->json([
            'user' => new UserResource($user),
            'access_token' => $access['access_token'],
            'access_expires_at' => $access['expires_at'],
            'refresh_token' => $refresh['refresh_token'],
            'refresh_expires_at' => $refresh['expires_at'],
            'token_type' => 'bearer',
        ]);
    }

    public function refresh(Request $request)
    {
        $request->validate(['refresh_token' => 'required|string']);
        $plain = $request->input('refresh_token');
        $token = $this->tokenService->findValidRefreshToken($plain);


        // أفضل: request يرسل user_id
        // $user = User::find($request->input('user_id'));
        // if (!$user)
        //     return response()->json(['message' => 'Invalid user'], 401);

        $token = $this->tokenService->findValidRefreshToken($plain);

        if (!$token) {
            return response()->json(['message' => 'Invalid or expired refresh token'], 401);
        }

        $user = $token->user;


        $access = $this->tokenService->createAccessToken($user, $request->header('User-Agent'));
        // optional: rotate refresh token
        // $newRefresh = $this->tokenService->createRefreshToken(...);

        return response()->json([
            'access_token' => $access['access_token'],
            'access_expires_at' => $access['expires_at'],
            // 'refresh_token' => $newRefresh['refresh_token'],
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        $user->tokens()->delete();

        $user->refreshTokens()->update(['revoked' => true]);

        return response()->json(['message' => 'Logged out']);
    }

    // forgot password
    public function forgotPassword(Request $request, OtpService $otpService)
    {
        $request->validate(['email' => 'required|email']);
        $user = User::where('email', $request->email)->firstOrFail();
        $otp=$otpService->generate($user);
        return response()->json(['message' => 'OTP sent to email','otp'=>$otp]);


        // $status = Password::sendResetLink($request->only('email'));

        // return $status == Password::RESET_LINK_SENT
        //     ? response()->json(['message' => 'Reset link sent'])
        //     : response()->json(['message' => 'Unable to send reset link'], 500);
    }

    // reset password
    public function resetPassword(Request $request, OtpService $otpService)
    {
        // $request->validate([
        //     'email' => 'required|email',
        //     'otp' => 'required|string|size:6',
        //     'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        // ]);

        // $status = Password::reset(
        //     $request->only('email', 'password', 'password_confirmation', 'token'),
        //     function ($user, $password) {
        //         $user->forceFill(['password' => bcrypt($password)])->save();
        //         // Revoke tokens after password change:
        //         $user->tokens()->delete();
        //         $user->refreshTokens()->update(['revoked' => true]);
        //     }
        // );

        // return $status == Password::PASSWORD_RESET
        //     ? response()->json(['message' => 'Password reset'])
        //     : response()->json(['message' => 'Failed to reset password'], 400);


        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ]);

        if (!$otpService->validate($request->email, $request->otp)) {
            return response()->json(['error' => 'Invalid or expired OTP'], 422);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $user->update(['password' => Hash::make($request->password)]);

        return response()->json(['message' => 'Password reset successful']);
    }
}
