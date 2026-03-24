<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthTokenService;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(private AuthTokenService $tokenService, private OtpService $otpService)
    {
    }

    public function register(RegisterRequest $request)
    {
        $data = $request->validated();
        $user = User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => bcrypt($data['password']),
        ]);

        $otp = $this->otpService->generate($user, (int) env('OTP_LENGTH', 6), \App\Enum\OtpType::EMAIL_OTP);

        $access = $this->tokenService->createAccessToken($user, $request->header('User-Agent'));
        $refresh = $this->tokenService->createRefreshToken($user, $request->header('User-Agent'), $request->ip());

        $response = [
            'user' => new UserResource($user),
            'access_token' => $access['access_token'],
            'access_expires_at' => $access['expires_at'],
            'refresh_token' => $refresh['refresh_token'],
            'refresh_expires_at' => $refresh['expires_at'],
            'token_type' => 'bearer',
            'message' => 'Registration successful. Please verify your email using the OTP sent to your email.',
            'email_verified' => false,
            'requires_otp_verification' => true,
        ];

        if (config('app.debug')) {
            $response['otp'] = $otp;
        }

        return response()->json($response, 201);
    }

    public function login(LoginRequest $request)
    {
        $data = $request->validated();
        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

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

        if (!$token) {
            return response()->json(['message' => 'Invalid or expired refresh token'], 401);
        }

        $user = $token->user;

        $access = $this->tokenService->createAccessToken($user, $request->header('User-Agent'));

        return response()->json([
            'access_token' => $access['access_token'],
            'access_expires_at' => $access['expires_at'],
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
        $user = User::where('email', $request->email)->firstOrFail();
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

        $user = User::where('email', $request->email)->firstOrFail();
        $user->update(['password' => Hash::make($request->password)]);

        return response()->json(['message' => 'Password reset successful']);
    }
}
