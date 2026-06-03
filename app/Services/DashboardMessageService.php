<?php

namespace App\Services;

use App\Models\DashboardMessage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Thin service for the StaffDashboard bulletin board.
 *
 * Behaviour (agreed spec):
 *  - Global board: every active message is visible to everyone.
 *  - Admin messages are pinned to the top automatically.
 *  - Optional auto-expiry hides a message after a chosen time without deleting it.
 *  - Soft deletes keep the full history in the database (who/when added, who/when deleted).
 *  - Add + delete only — no editing, to keep the history clean.
 */
class DashboardMessageService
{
    public const MAX_BODY_LENGTH = 1000;

    /** Allowed expiry presets → resolver returning a Carbon or null. */
    private function resolveExpiry(?string $preset): ?Carbon
    {
        return match ($preset) {
            'end_of_day' => Carbon::today()->endOfDay(),
            'in_24h'     => Carbon::now()->addDay(),
            default      => null, // 'never' or anything unknown
        };
    }

    /**
     * Active messages ready for display, with author eager-loaded.
     *
     * @return Collection<int, DashboardMessage>
     */
    public function listActive(): Collection
    {
        return DashboardMessage::query()
            ->active()
            ->with('user:id,first_name,last_name')
            ->get();
    }

    /**
     * Add a message. Admin authors are pinned automatically.
     *
     * @throws ValidationException when the body is empty/too long.
     */
    public function add(User $author, string $body, ?string $expiryPreset = null): DashboardMessage
    {
        $body = trim($body);

        if ($body === '') {
            throw ValidationException::withMessages([
                'newMessageBody' => __('dashboard.messages.empty_error'),
            ]);
        }

        if (mb_strlen($body) > self::MAX_BODY_LENGTH) {
            throw ValidationException::withMessages([
                'newMessageBody' => __('dashboard.messages.too_long_error', ['max' => self::MAX_BODY_LENGTH]),
            ]);
        }

        return DashboardMessage::create([
            'user_id'    => $author->id,
            'body'       => $body,
            'is_pinned'  => $author->hasRole('admin'),
            'expires_at' => $this->resolveExpiry($expiryPreset),
        ]);
    }

    /**
     * Soft-delete a message. Admins may delete any message; others only their own.
     * Records who performed the deletion for the audit trail.
     *
     * @return bool true when deleted, false when not allowed / already gone.
     */
    public function delete(int $messageId, User $actor): bool
    {
        $message = DashboardMessage::find($messageId);

        if (! $message || ! $this->canDelete($message, $actor)) {
            return false;
        }

        $message->deleted_by = $actor->id;
        $message->save();
        $message->delete();

        return true;
    }

    /** A user may delete a message if they are an admin or the author. */
    public function canDelete(DashboardMessage $message, User $actor): bool
    {
        return $actor->hasRole('admin') || $message->user_id === $actor->id;
    }
}
