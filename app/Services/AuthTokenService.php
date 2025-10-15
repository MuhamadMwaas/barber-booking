<?php
namespace App\Services;

use App\Models\User;
use App\Models\RefreshToken;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AuthTokenService
{
    public function createAccessToken(User $user, ?string $device = null, int $minutes = 15)
    {
        $token = $user->createToken($device ?? 'mobile-token', ['*']);
        $accessToken = $token->plainTextToken;

        $expiresAt = Carbon::now()->addMinutes($minutes);

        // $tokenModel = $token->accessToken;
        // $tokenModel->expires_at = $expiresAt;
        // $tokenModel->save();
        return ['access_token' => $accessToken, 'expires_at' => $expiresAt->toDateTimeString()];
    }

    public function createRefreshToken(User $user, ?string $device = null, ?string $ip = null, int $days = 30)
    {
        $plain = Str::random(64);
        $hashed = hash('sha256', $plain . config('app.key'));
        $expiresAt = Carbon::now()->addDays($days);

        $rt = RefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => $hashed,
            'device' => $device,
            'ip' => $ip,
            'expires_at' => $expiresAt,
        ]);

        return ['refresh_token' => $plain, 'expires_at' => $expiresAt->toDateTimeString()];
    }

    public function revokeRefreshTokenByPlain(User $user, string $plain)
    {
        $hashed = hash('sha256', $plain . config('app.key'));
        $token = RefreshToken::where('user_id', $user->id)->where('token_hash', $hashed)->first();
        if ($token) {
            $token->update(['revoked' => true]);
        }
    }

    public function validateRefreshToken(User $user, string $plain)
    {
        $hashed = hash('sha256', $plain . config('app.key'));
        $token = RefreshToken::where('user_id', $user->id)
            ->where('token_hash', $hashed)
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->first();
        return $token;
    }

    public function findValidRefreshToken(string $plain): ?\App\Models\RefreshToken
    {
        $hashed = hash('sha256', $plain . config('app.key'));

        return RefreshToken::where('token_hash', $hashed)
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->first();
    }
}