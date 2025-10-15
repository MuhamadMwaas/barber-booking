<?php
namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController
{
    public function google(Request $request, AuthTokenService $tokenService)
    {
        $request->validate(['token' => 'required|string']);
        $idToken = $request->input('token');

        try {
            $googleUser = Socialite::driver('google')->userFromToken($idToken);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid Google token'], 422);
        }

        $email = $googleUser->getEmail();
        $user = User::firstWhere('email', $email);
        if (!$user) {
            $user = User::create([
                'name' => $googleUser->getName() ?? $googleUser->getNickname() ?? 'GoogleUser',
                'email' => $email,
                'email_verified_at' => now(),
                'google_id' => $googleUser->getId(),
                'password' => bcrypt(Str::random(16)),
                'avatar_url' => $googleUser->getAvatar(),
            ]);
        }
        $device = $request->header('User-Agent');
        if (is_array($device)) {
            $device = $device[0];
        }
        $access = $tokenService->createAccessToken($user, $device);
        $refresh = $tokenService->createRefreshToken($user, $device, $request->ip());

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'avatar_url']),
            'access_token' => $access['access_token'],
            'refresh_token' => $refresh['refresh_token'],
        ]);
    }
}