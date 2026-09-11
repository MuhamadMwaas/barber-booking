<?php
namespace App\Services;

use App\Models\User;
use App\Models\RefreshToken;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AuthTokenService
{
    public function createAccessToken(User $user, ?string $device = null, ?int $minutes = null)
    {
        $minutes ??= (int) config('auth_tokens.access_ttl_minutes', 15);

        $token = $user->createToken($device ?? 'mobile-token', ['*']);
        $accessToken = $token->plainTextToken;

        $expiresAt = Carbon::now()->addMinutes($minutes);

        $tokenModel = $token->accessToken;
        $tokenModel->expires_at = $expiresAt;
        $tokenModel->save();
        return ['access_token' => $accessToken, 'expires_at' => $expiresAt->toDateTimeString()];
    }

    public function createRefreshToken(User $user, ?string $device = null, ?string $ip = null, ?int $days = null)
    {
        $days ??= (int) config('auth_tokens.refresh_ttl_days', 30);

        $plain = Str::random(64);
        $expiresAt = Carbon::now()->addDays($days);

        $rt = RefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => $this->hash($plain),
            'device' => $device,
            'ip' => $ip,
            'expires_at' => $expiresAt,
        ]);

        return [
            'refresh_token' => $plain,
            'expires_at' => $expiresAt->toDateTimeString(),
            'id' => $rt->id,
        ];
    }

    /**
     * Spend a refresh token and issue its successor (AUTH-04).
     *
     * A refresh token is a one-time credential: presenting it destroys it and
     * hands back a replacement. That is what makes a leak detectable — see
     * RefreshToken::REASON_ROTATED.
     *
     * The revocation is written as a CONDITIONAL update (`where revoked = false`)
     * rather than a read-then-write, because two requests can arrive holding the
     * same valid token at the same moment — a genuine client double-tap, or the
     * thief racing the owner. `UPDATE ... WHERE revoked = false` is atomic in the
     * database, so exactly one of them can ever see one affected row. The other
     * gets zero, is told it lost, and is answered with a 401. Without this, both
     * would rotate and the account would end up with two live token families,
     * which is precisely the state rotation exists to prevent.
     *
     * @return array|null the new token, or null if another request spent it first
     */
    public function rotateRefreshToken(RefreshToken $token, ?string $device = null, ?string $ip = null): ?array
    {
        $claimed = RefreshToken::query()
            ->whereKey($token->getKey())
            ->where('revoked', false)
            ->update([
                'revoked' => true,
                'revoked_at' => now(),
                'revoked_reason' => RefreshToken::REASON_ROTATED,
                'last_used_at' => now(),
            ]);

        if ($claimed !== 1) {
            return null;
        }

        $new = $this->createRefreshToken($token->user, $device ?? $token->device, $ip ?? $token->ip);

        // Chain the spent token to its successor so a compromised family can be
        // walked end to end after an alarm.
        RefreshToken::query()
            ->whereKey($token->getKey())
            ->update(['replaced_by_id' => $new['id']]);

        return $new;
    }

    /**
     * Revoke every session for an account: access tokens AND refresh tokens.
     *
     * Deleting access tokens alone leaves the holder able to mint fresh ones;
     * revoking refresh tokens alone leaves the current access token live for up
     * to its remaining lifetime. Ending a session means both.
     */
    public function revokeAllSessions(User $user, string $reason): void
    {
        $user->tokens()->delete();
        RefreshToken::revokeAllFor($user->id, $reason);
    }

    public function revokeRefreshTokenByPlain(User $user, string $plain)
    {
        $token = RefreshToken::query()
            ->where('user_id', $user->id)
            ->whereIn('token_hash', $this->candidateHashes($plain))
            ->first();

        if ($token) {
            $token->update([
                'revoked' => true,
                'revoked_at' => now(),
                'revoked_reason' => RefreshToken::REASON_LOGOUT,
            ]);
        }
    }

    public function validateRefreshToken(User $user, string $plain)
    {
        return RefreshToken::query()
            ->where('user_id', $user->id)
            ->whereIn('token_hash', $this->candidateHashes($plain))
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->first();
    }

    public function findValidRefreshToken(string $plain): ?RefreshToken
    {
        return RefreshToken::query()
            ->whereIn('token_hash', $this->candidateHashes($plain))
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Look a token up in ANY state — revoked, expired, or live.
     *
     * `findValidRefreshToken()` deliberately cannot answer the question reuse
     * detection has to ask: "was this string ever a real token of ours?" A
     * rejected refresh is either a string we have never seen (noise) or a token
     * we issued and already retired (a signal). Telling those apart requires a
     * lookup with no state filter at all.
     */
    public function findRefreshTokenRecord(string $plain): ?RefreshToken
    {
        return RefreshToken::query()
            ->whereIn('token_hash', $this->candidateHashes($plain))
            ->first();
    }

    /**
     * The hash stored for a new token.
     *
     * A bare SHA-256 of the token itself — no APP_KEY mixed in. The token is 64
     * characters from a CSPRNG, so there is no dictionary to protect it from and
     * peppering the digest adds no strength. It does add a failure mode:
     * APP_KEY rotation is a standard response to a suspected key leak, and with
     * the key inside the digest that rotation would silently invalidate every
     * refresh token in the database and sign every customer out with no
     * explanation. A security control should not have to be weighed against
     * mass logout before it can be used.
     */
    public function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * Both hash schemes, so tokens issued before this change keep working.
     *
     * Changing how a token is hashed normally logs out everyone holding an old
     * one. Accepting the legacy digest on lookup avoids that, and rotation
     * retires the debt on its own: the first refresh re-issues the token under
     * the new scheme, so the legacy form drains out of the table naturally as
     * clients come back, and is gone entirely once the old tokens expire.
     *
     * @return string[]
     */
    private function candidateHashes(string $plain): array
    {
        return [
            $this->hash($plain),
            hash('sha256', $plain . config('app.key')), // legacy — pre-AUTH-04
        ];
    }
}
