<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Log;

class SocialApiAuthController extends Controller
{
    public function __construct(private AuthTokenService $tokenService)
    {
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

            $googleUser = $this->verifyGoogleIdToken($idToken, $platform);

            if (!$googleUser) {
                return response()->json([
                    'message' => 'Invalid Google ID token'
                ], 401);
            }


            $user = $this->findOrCreateUser($googleUser);


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


    private function verifyGoogleIdToken(string $idToken, string $platform): ?array
    {
        $client = new GoogleClient([
            'client_id' => config('services.google.client_id')
        ]);

        $clientIds = [config('services.google.client_id')];

        if ($platform === 'android') {
            $clientIds[] = config('services.google_mobile.android_client_id');
        } elseif ($platform === 'ios') {
            $clientIds[] = config('services.google_mobile.ios_client_id');
        }

        try {

            $payload = $client->verifyIdToken($idToken);

            if (!$payload) {
                return null;
            }


            if (!in_array($payload['aud'], $clientIds)) {
                Log::error('Invalid audience in ID token', [
                    'expected' => $clientIds,
                    'received' => $payload['aud']
                ]);
                return null;
            }

            return [
                'google_id' => $payload['sub'],
                'email' => $payload['email'],
                'email_verified' => $payload['email_verified'] ?? false,
                'name' => $payload['name'] ?? '',
                'given_name' => $payload['given_name'] ?? '',
                'family_name' => $payload['family_name'] ?? '',
                'picture' => $payload['picture'] ?? null,
            ];

        } catch (\Exception $e) {
            Log::error('Google ID Token Verification Failed: ' . $e->getMessage());
            return null;
        }
    }


    private function findOrCreateUser(array $googleUser): User
    {
        $user = User::where('google_id', $googleUser['google_id'])->first();

        if ($user) {
            if ($googleUser['picture'] && $user->avatar_url !== $googleUser['picture']) {
                $user->update(['avatar_url' => $googleUser['picture']]);
            }
            return $user;
        }

        $user = User::where('email', $googleUser['email'])->first();

        if ($user) {
            $user->update([
                'google_id' => $googleUser['google_id'],
                'avatar_url' => $googleUser['picture'] ?? $user->avatar_url,
                'email_verified_at' => $googleUser['email_verified'] ? now() : $user->email_verified_at,
                'email_verified_via_otp_at' => $googleUser['email_verified'] ? now() : $user->email_verified_via_otp_at,
            ]);
            return $user;
        }

        return User::create([
            'google_id' => $googleUser['google_id'],
            'email' => $googleUser['email'],
            'first_name' => $googleUser['given_name'] ?: 'User',
            'last_name' => $googleUser['family_name'] ?: '',
            'avatar_url' => $googleUser['picture'],
            'password' => bcrypt(Str::random(32)), // كلمة مرور عشوائية
            'email_verified_at' => $googleUser['email_verified'] ? now() : null,
            'email_verified_via_otp_at' => $googleUser['email_verified'] ? now() : null,
        ]);
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

            $user = $this->findOrCreateUser([
                'google_id' => $googleUser->getId(),
                'email' => $googleUser->getEmail(),
                'email_verified' => true,
                'name' => $googleUser->getName(),
                'given_name' => $googleUser->offsetGet('given_name'),
                'family_name' => $googleUser->offsetGet('family_name'),
                'picture' => $googleUser->getAvatar(),
            ]);

            return response()->json([
                'message' => 'Login successful',
                'user' => $user
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Authentication failed',
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
