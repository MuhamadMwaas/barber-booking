<?php
namespace App\Http\Controllers\Api;

use App\Services\Auth\GoogleSignInService;
use App\Services\AuthTokenService;
use Illuminate\Http\Request;

class SocialAuthController
{
    public function google(Request $request, AuthTokenService $tokenService, GoogleSignInService $google)
    {
        $request->validate(['token' => 'required|string']);

        $googleUser = $google->verifyIdToken($request->input('token'));

        if (! $googleUser) {
            return response()->json(['message' => 'Invalid Google token'], 422);
        }

        $user = $google->findOrCreateUser($googleUser);

        $device = $request->header('User-Agent');
        if (is_array($device)) {
            $device = $device[0];
        }
        $access = $tokenService->createAccessToken($user, $device);
        $refresh = $tokenService->createRefreshToken($user, $device, $request->ip());

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->full_name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
            ],
            'access_token' => $access['access_token'],
            'refresh_token' => $refresh['refresh_token'],
        ]);
    }
}
