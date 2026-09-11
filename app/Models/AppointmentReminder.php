<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One scheduled reminder for one appointment.
 *
 * Lifecycle: `pending` → `sent` (the job fired) or `cancelled` (the customer
 * turned it off, rescheduled it, or the appointment went away). Rows are never
 * deleted — the history is what lets support answer "did we actually remind
 * them, and on what?".
 *
 * `active_slot` is the live-row marker: it holds {@see self::ACTIVE_SLOT} while
 * the reminder is pending and NULL afterwards. The unique index
 * `appointment_reminders_one_active` spans (appointment_id, user_id, active_slot),
 * and since SQL treats NULLs as distinct, that enforces "at most one LIVE
 * reminder per appointment" while letting any number of history rows pile up.
 * Every status change MUST keep the two in step — use the mark* methods below
 * rather than writing `status` directly.
 */
class AppointmentReminder extends Model
{
    /** Value stored in `active_slot` for the one live reminder. */
    public const ACTIVE_SLOT = 1;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'appointment_id',
        'user_id',
        'remind_at',
        'status',
        'active_slot',
        'cancelled_at',
        'sent_at',
        'delivered_channels',
        'job_uuid',
        'locale',
        'title_key',
        'message_key',
        'params',
        'data',
    ];

    protected $casts = [
        'remind_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'sent_at' => 'datetime',
        'params' => 'array',
        'data' => 'array',
        'delivered_channels' => 'array',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The live reminder(s) — i.e. still waiting to fire.
     *
     * Filters on `active_slot` rather than `status` because that is the column
     * the unique index is built on: querying the same column the constraint
     * guards keeps "what the app calls active" and "what the database calls
     * active" from drifting apart.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotNull('active_slot');
    }

    /**
     * Lead time in whole hours before the appointment, or null when it does not
     * land on an exact hour (a legacy free-form `remind_at` may not).
     *
     * The mobile app needs this to pre-select the dropdown: it sends
     * `offset_hours`, and this gives the same number back on the way out.
     */
    public function offsetHours(): ?int
    {
        $start = $this->appointment?->start_time;
        if (! $start || ! $this->remind_at) {
            return null;
        }

        // Raw timestamps, not diffInMinutes(): Carbon 2 returns that absolute and
        // Carbon 3 returns it signed, so the same call would silently change
        // meaning on an upgrade. Subtraction cannot.
        $seconds = $start->getTimestamp() - $this->remind_at->getTimestamp();

        if ($seconds <= 0 || $seconds % 3600 !== 0) {
            return null;
        }

        return intdiv($seconds, 3600);
    }

    /**
     * Mark the reminder as fired and free its live slot.
     *
     * @param  array<int, string>  $channels  Channels it actually went out on. An
     *                                        EMPTY array is a meaningful result,
     *                                        not a failure: the customer had every
     *                                        channel switched off at send time.
     */
    public function markSent(array $channels = []): void
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
            'active_slot' => null,
            'delivered_channels' => array_values($channels),
        ]);
    }

    /**
     * Record which channels the send actually reached, after the attempt.
     *
     * Separate from markSent() because the reminder is claimed (marked sent)
     * inside a transaction BEFORE the channels are attempted — claiming first is
     * what stops a retried job from sending twice — so the delivery result can
     * only be written once that attempt is over.
     *
     * @param  array<int, string>  $channels
     */
    public function recordDeliveredChannels(array $channels): void
    {
        $this->update(['delivered_channels' => array_values($channels)]);
    }

    public function markCancelled(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'active_slot' => null,
        ]);
    }
}
