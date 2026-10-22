<?php

namespace App\Services\Auth;

use App\Enum\RegistrationMethod;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The single place a Google sign-in is trusted.
 *
 * The app sends a Google ID token (a JWT), never an access token, so it must be
 * verified here - Socialite::userFromToken() expects an access token and
 * rejects every ID token. The audience check is what stops a token issued to
 * someone else's Google app (or Firebase project) from logging into ours.
 */
class GoogleSignInService
{
    private const FIREBASE_ISSUER_PREFIX = 'https://securetoken.google.com/';

    private const FIREBASE_CERTS_URL = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';

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
     * Verify a token from the app and return the Google profile, or null.
     *
     * Two token kinds are accepted:
     *  - a Google ID token (iss accounts.google.com), checked against allowedAudiences();
     *  - a Firebase ID token (iss securetoken.google.com/<project>) from a Google
     *    sign-in, only when services.google.firebase_project_id is set. The mobile
     *    app falls back to this one when Firebase does not hand it the Google token.
     *
     * @return array{google_id: string, email: string, email_verified: bool, name: string, given_name: string, family_name: string, picture: ?string}|null
     */
    public function verifyIdToken(string $idToken): ?array
    {
        $firebaseProject = (string) config('services.google.firebase_project_id', '');

        // Only used to pick the verifier; the chosen verifier checks iss itself.
        $claimedIssuer = (string) ($this->unverifiedClaims($idToken)['iss'] ?? '');

        if ($firebaseProject !== '' && $claimedIssuer === self::FIREBASE_ISSUER_PREFIX . $firebaseProject) {
            return $this->verifyFirebaseIdToken($idToken, $firebaseProject);
        }

        return $this->verifyGoogleIdToken($idToken);
    }

    private function verifyGoogleIdToken(string $idToken): ?array
    {
        try {
            // Signature, expiry and issuer (accounts.google.com).
            $payload = $this->decode($idToken);
        } catch (\Throwable $e) {
            Log::warning('Google ID token verification failed: ' . $e->getMessage());

            return null;
        }

        if (! is_array($payload)) {
            Log::warning('Google ID token verification failed: bad signature, expired or not a Google token');

            return null;
        }

        $audience = (string) ($payload['aud'] ?? '');

        if (! in_array($audience, $this->allowedAudiences(), true)) {
            Log::warning('Google ID token has an unknown audience', ['aud' => $audience]);

            return null;
        }

        if (! $this->emailIsVerified($payload)) {
            Log::warning('Google ID token rejected: email missing or not verified');

            return null;
        }

        return $this->profile($payload, (string) $payload['sub']);
    }

    private function verifyFirebaseIdToken(string $idToken, string $project): ?array
    {
        try {
            $payload = $this->decodeFirebase($idToken);
        } catch (\Throwable $e) {
            Log::warning('Firebase ID token verification failed: ' . $e->getMessage());

            return null;
        }

        $firebase = (array) ($payload['firebase'] ?? []);
        $googleSub = $firebase['identities']['google.com'][0] ?? null;

        $valid = ($payload['aud'] ?? null) === $project
            && ($payload['iss'] ?? null) === self::FIREBASE_ISSUER_PREFIX . $project
            && ! empty($payload['sub'])
            && (int) ($payload['auth_time'] ?? PHP_INT_MAX) <= time() + 60
            // A Firebase email/password account must not pass as a Google sign-in.
            && ($firebase['sign_in_provider'] ?? null) === 'google.com'
            && is_string($googleSub) && $googleSub !== ''
            && $this->emailIsVerified($payload);

        if (! $valid) {
            Log::warning('Firebase ID token rejected', [
                'aud' => $payload['aud'] ?? null,
                'provider' => $firebase['sign_in_provider'] ?? null,
            ]);

            return null;
        }

        // The Google account id, not the Firebase uid, so both token kinds land
        // on the same users.google_id.
        return $this->profile($payload, $googleSub);
    }

    private function emailIsVerified(array $payload): bool
    {
        // Accounts are linked by email, so an unverified email must never pass.
        return ! empty($payload['email'])
            && filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOL);
    }

    private function profile(array $payload, string $googleId): array
    {
        $name = (string) ($payload['name'] ?? '');

        return [
            'google_id' => $googleId,
            'email' => (string) $payload['email'],
            'email_verified' => true,
            'name' => $name,
            'given_name' => (string) ($payload['given_name'] ?? Str::before($name, ' ')),
            'family_name' => (string) ($payload['family_name'] ?? (str_contains($name, ' ') ? Str::after($name, ' ') : '')),
            'picture' => $payload['picture'] ?? null,
        ];
    }

    private function unverifiedClaims(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return [];
        }

        $claims = json_decode((string) JWT::urlsafeB64Decode($parts[1]), true);

        return is_array($claims) ? $claims : [];
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

    /**
     * Signature (Google's securetoken keys), exp, iat and nbf. The claims are
     * checked by the caller.
     */
    protected function decodeFirebase(string $idToken): array
    {
        $fetch = fn () => Http::timeout(10)->get(self::FIREBASE_CERTS_URL)->throw()->json();

        try {
            $certs = Cache::remember('auth:firebase-securetoken-certs', now()->addHour(), $fetch);
        } catch (\Illuminate\Http\Client\RequestException|\Illuminate\Http\Client\ConnectionException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // A broken cache store must not break sign-in; Google's keys are public.
            Log::warning('Firebase cert cache unavailable, fetching directly: ' . $e->getMessage());
            $certs = $fetch();
        }

        $keys = [];
        foreach ((array) $certs as $kid => $pem) {
            $keys[$kid] = new Key($pem, 'RS256');
        }

        JWT::$leeway = 60;

        return json_decode(json_encode(JWT::decode($idToken, $keys)), true);
    }
}
