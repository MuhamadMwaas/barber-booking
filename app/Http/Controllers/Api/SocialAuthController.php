<?php
namespace App\Http\Controllers\Api;

use App\Http\Resources\UserResource;
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

        // Same shape as a password login (AuthController), so the app parses one format.
        return response()->json([
            'user' => new UserResource($user),
            'access_token' => $access['access_token'],
            'access_expires_at' => $access['expires_at'],
            'refresh_token' => $refresh['refresh_token'],
            'refresh_expires_at' => $refresh['expires_at'],
            'token_type' => 'bearer',
        ]);
    }
}
