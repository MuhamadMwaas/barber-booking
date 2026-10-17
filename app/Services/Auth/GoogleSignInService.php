<?php

namespace App\Services\Auth;

use App\Enum\RegistrationMethod;
use App\Models\User;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The single place a Google sign-in is trusted.
 *
 * The app sends a Google ID token (a JWT), never an access token, so it must be
 * verified here - Socialite::userFromToken() expects an access token and
 * rejects every ID token. The audience check is what stops a token issued to
 * someone else's Google app from logging into ours.
 */
class GoogleSignInService
{
    /**
     * Every OAuth client ID whose ID tokens this backend accepts.
     *
     * @return list<string>
     */
    public function allowedAudiences(): array
    {
        $extra = explode(',', (string) config('services.google.allowed_audiences', ''));

        return array_values(array_unique(array_filter(array_map(fn ($id) => trim((string) $id), [
            config('services.google.client_id'),
            config('services.google_mobile.android_client_id'),
            config('services.google_mobile.ios_client_id'),
            ...$extra,
        ]))));
    }

    /**
     * Verify an ID token and return the Google profile, or null when it is not
     * a valid, verified-email token issued to one of our clients.
     *
     * @return array{google_id: string, email: string, email_verified: bool, name: string, given_name: string, family_name: string, picture: ?string}|null
     */
    public function verifyIdToken(string $idToken): ?array
    {
        try {
            // Signature, expiry and issuer (accounts.google.com).
            $payload = $this->decode($idToken);
        } catch (\Throwable $e) {
            Log::warning('Google ID token verification failed: ' . $e->getMessage());

            return null;
        }

        if (! is_array($payload)) {
            return null;
        }

        $audience = (string) ($payload['aud'] ?? '');

        if (! in_array($audience, $this->allowedAudiences(), true)) {
            Log::warning('Google ID token has an unknown audience', ['aud' => $audience]);

            return null;
        }

        // Accounts are linked by email below, so an unverified email must never pass.
        if (empty($payload['email']) || ! filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOL)) {
            return null;
        }

        return [
            'google_id' => (string) $payload['sub'],
            'email' => (string) $payload['email'],
            'email_verified' => true,
            'name' => (string) ($payload['name'] ?? ''),
            'given_name' => (string) ($payload['given_name'] ?? ''),
            'family_name' => (string) ($payload['family_name'] ?? ''),
            'picture' => $payload['picture'] ?? null,
        ];
    }

    public function findOrCreateUser(array $googleUser): User
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
                'email_verified_at' => $googleUser['email_verified'] ? ($user->email_verified_at ?? now()) : $user->email_verified_at,
            ]);

            return $user;
        }

        return User::create([
            'google_id' => $googleUser['google_id'],
            'email' => $googleUser['email'],
            'first_name' => $googleUser['given_name'] ?: ($googleUser['name'] ?: 'User'),
            'last_name' => $googleUser['family_name'] ?: '',
            'avatar_url' => $googleUser['picture'],
            'password' => bcrypt(Str::random(32)),
            'registration_method' => RegistrationMethod::EMAIL,
            'email_verified_at' => $googleUser['email_verified'] ? now() : null,
            'email_verified_via_otp_at' => $googleUser['email_verified'] ? now() : null,
        ]);
    }

    /**
     * No client_id is set on purpose: the library would then accept exactly one
     * audience, and we check against a list instead.
     */
    protected function decode(string $idToken): array|false
    {
        return (new GoogleClient())->verifyIdToken($idToken);
    }
}
