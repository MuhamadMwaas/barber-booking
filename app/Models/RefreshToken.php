<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RefreshToken extends Model
{
    /**
     * Why a token was revoked.
     *
     * This is not bookkeeping — it is the input to reuse detection (AUTH-04).
     * A revoked token that shows up again means completely different things
     * depending on which of these put it in that state:
     *
     *   ROTATED          the token was spent on a refresh. It should never be
     *                    seen again. If it is, a second copy exists -> LEAK.
     *   LOGOUT           the user deliberately ended the session.
     *   PASSWORD_CHANGE  }  the user deliberately destroyed every session.
     *   PASSWORD_RESET   }
     *   REUSE_DETECTED   collateral of an alarm that already fired.
     *   ACCOUNT_DISABLED the account was deactivated or deleted by an admin
     *                    (AUTHZ-01) — revoked FOR the user, not BY them.
     *   LEGACY           revoked before rotation existed (migration backfill).
     *
     * Only ROTATED is evidence of theft. Everything else is a stale client and
     * gets a quiet 401.
     */
    public const REASON_ROTATED = 'rotated';
    public const REASON_LOGOUT = 'logout';
    public const REASON_PASSWORD_CHANGE = 'password_change';
    public const REASON_PASSWORD_RESET = 'password_reset';
    public const REASON_REUSE_DETECTED = 'reuse_detected';
    public const REASON_ACCOUNT_DISABLED = 'account_disabled';
    public const REASON_LEGACY = 'legacy';

    protected $fillable = [
        'user_id',
        'token_hash',
        'expires_at',
        'device',
        'ip',
        'revoked',
        'revoked_at',
        'revoked_reason',
        'last_used_at',
        'replaced_by_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_used_at' => 'datetime',
        'revoked' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Revoke every live refresh token for one account, stamping the reason.
     *
     * Callers pass a reason from the constants above so that a later reuse of
     * one of these tokens can be read correctly. Already-revoked rows are left
     * untouched: overwriting an earlier reason would erase the record of why
     * the session originally ended.
     *
     * @return int number of tokens revoked
     */
    public static function revokeAllFor(int $userId, string $reason): int
    {
        return static::query()
            ->where('user_id', $userId)
            ->where('revoked', false)
            ->update([
                'revoked' => true,
                'revoked_at' => now(),
                'revoked_reason' => $reason,
            ]);
    }
}
