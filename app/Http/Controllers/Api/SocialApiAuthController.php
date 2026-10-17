<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\GoogleSignInService;
use App\Services\AuthTokenService;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Log;

class SocialApiAuthController extends Controller
{
    public function __construct(
        private AuthTokenService $tokenService,
        private GoogleSignInService $google,
    ) {
    }

    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function googleMobile(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string',
            'platform' => 'required|in:android,ios'
        ]);

        $idToken = $request->input('id_token');
        $platform = $request->input('platform');

        try {

            $googleUser = $this->google->verifyIdToken($idToken);

            if (!$googleUser) {
                return response()->json([
                    'message' => 'Invalid Google ID token'
                ], 401);
            }


            $user = $this->google->findOrCreateUser($googleUser);


            $device = $request->header('User-Agent') ?? $platform . '-app';
            $access = $this->tokenService->createAccessToken($user, $device);
            $refresh = $this->tokenService->createRefreshToken($user, $device, $request->ip());

            return response()->json([
                'message' => 'Login successful',
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar_url' => $user->avatar_url,
                    'email_verified_at' => $user->email_verified_at,
                ],
                'access_token' => $access['access_token'],
                'access_expires_at' => $access['expires_at'],
                'refresh_token' => $refresh['refresh_token'],
                'refresh_expires_at' => $refresh['expires_at'],
                'token_type' => 'bearer',
            ]);

        } catch (\Exception $e) {
            Log::error('Google Auth Error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Authentication failed',
                'error' => $e->getMessage()
            ], 422);
        }
    }


    public function googleWebRedirect()
    {
        return Socialite::driver('google')
            ->scopes(['email', 'profile'])
            ->redirect();
    }


    public function googleWebCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            $user = $this->google->findOrCreateUser([
                'google_id' => $googleUser->getId(),
                'email' => $googleUser->getEmail(),
                'email_verified' => true,
                'name' => $googleUser->getName(),
                'given_name' => $googleUser->getName(),
                'family_name' => $googleUser->getNickname(),
                'picture' => $googleUser->getAvatar(),
            ]);

        $access = $this->tokenService->createAccessToken($user, $request->header('User-Agent'));
        $refresh = $this->tokenService->createRefreshToken($user, $request->header('User-Agent'), $request->ip());

            return response()->json([
            'message' => 'Login successful',
            'user' => new UserResource($user),
            'access_token' => $access['access_token'],
            'access_expires_at' => $access['expires_at'],
            'refresh_token' => $refresh['refresh_token'],
            'refresh_expires_at' => $refresh['expires_at'],
            'token_type' => 'bearer',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Authentication failed',
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
