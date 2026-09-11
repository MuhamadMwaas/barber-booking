<?php

namespace App\Models;

use App\Enum\AppointmentStatus;
use App\Services\CancellationMonitor;
use App\Enum\BookingSource;
use App\Enum\InvoiceStatus;
use App\Enum\PaymentStatus;
use App\Services\AppointmentReminderService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Log;

class Appointment extends Model
{
    use HasFactory;


    protected $fillable = [
        'number',
        'customer_id',
        'provider_id',
        'parent_appointment_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'appointment_date',
        'start_time',
        'end_time',
        'original_start_time',
        'original_end_time',
        'was_pushed',
        'last_pushed_at',
        'duration_minutes',
        'subtotal',
        'tax_amount',
        'total_amount',
        'status',
        'payment_method',
        'cancellation_reason',
        'cancelled_at',
        'notes',
        'provider_notes',
        'payment_status',
        'created_status',
        'booking_source',
        'is_override',
        'override_reason',
    ];


    protected $casts = [
        'appointment_date' => 'datetime',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'original_start_time' => 'datetime',
        'original_end_time' => 'datetime',
        'last_pushed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'was_pushed' => 'boolean',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'status' => AppointmentStatus::class,
        'payment_status' => PaymentStatus::class,
        'duration_minutes' => 'integer',
        'booking_source' => BookingSource::class,
        'is_override' => 'boolean',
    ];


    protected static function boot()
    {
        parent::boot();

        static::creating(function ($appointment) {
            if (empty($appointment->number)) {
                $BookingService=app(\App\Services\BookingService::class);
                $appointment->number = $BookingService->generateAppointmentNumber();
                // $prefix = 'APT';
                // $date = \Carbon\Carbon::now()->format('Ymd');
                // $random = strtoupper(substr(uniqid(), -6));

                // $appointment->number = "{$prefix}-{$date}-{$random}";
            }
        });

        static::deleting(function ($appointment) {

            $reminderService = app(AppointmentReminderService::class);
            $reminderService->cancelRemindersForAppointment($appointment);

        });

        /*
         * Retire the live reminder the moment the booking is cancelled.
         *
         * Hooked on the STATUS COLUMN rather than added inside cancel(), because
         * cancel() is only the customer's path: staff write ADMIN_CANCELLED
         * straight onto the model from the dashboard and Filament, and those
         * paths would each have needed the same line. Watching the column covers
         * every writer, present and future — the same reasoning that keeps the
         * "provider is busy" rule in one scope instead of inline conditions.
         *
         * Nothing wrong was ever DELIVERED without this: SendAppointmentReminderJob
         * already refuses to fire for a cancelled appointment. What it fixes is
         * the record — the row used to sit at `pending` forever, and now that the
         * API exposes the live reminder, a cancelled booking would report a
         * reminder the customer can no longer turn off.
         *
         * Failures are logged, never rethrown: a reminder outliving its booking
         * is untidy, a cancellation that fails to save is a lost slot.
         */
        static::updated(function ($appointment) {
            if (! $appointment->wasChanged('status')) {
                return;
            }

            $status = $appointment->status?->value ?? $appointment->status;

            if (! in_array($status, AppointmentStatus::getCancelledStatuses(), true)) {
                return;
            }

            try {
                app(AppointmentReminderService::class)->cancelRemindersForAppointment($appointment);
            } catch (\Throwable $e) {
                Log::error('Failed to cancel reminders for a cancelled appointment', [
                    'appointment_id' => $appointment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }


    // Relationships

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'appointment_services')
            ->withPivot(['service_name', 'duration_minutes', 'price', 'sequence_order'])
            ->withTimestamps();
    }


    public function services_record(): HasMany
    {
        return $this->hasMany(AppointmentService::class, 'appointment_id');
    }
    public function reminders(): HasMany
    {
        return $this->hasMany(AppointmentReminder::class, 'appointment_id', 'id');
    }

    /**
     * The one reminder still waiting to fire, if any.
     *
     * HasOne rather than HasMany because the unique index
     * `appointment_reminders_one_active` guarantees there is at most one. Exposed
     * as its own relation so the API can eager-load just the live reminder
     * instead of pulling the whole cancelled/sent history on every listing.
     */
    public function activeReminder(): HasOne
    {
        return $this->hasOne(AppointmentReminder::class, 'appointment_id', 'id')
            ->whereNotNull('active_slot');
    }

    /**
     * Colors used in this appointment (documentation / client history).
     */
    public function colors(): BelongsToMany
    {
        return $this->belongsToMany(Color::class, 'appointment_colors')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    /**
     * Color pivot records (AppointmentColor model).
     */
    public function colorRecords(): HasMany
    {
        return $this->hasMany(AppointmentColor::class, 'appointment_id');
    }
    // Accessors

    public function getStatusLabelAttribute()
    {
        return $this->status->getLabel();
    }

    public function getPaymentStatusLabelAttribute()
    {
        return $this->payment_status->label();
    }

    // Scopes

    public function scopeByStatus($query, AppointmentStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', AppointmentStatus::COMPLETED);
    }

    public function scopeByPaymentStatus($query, PaymentStatus $paymentStatus)
    {
        return $query->where('payment_status', $paymentStatus);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_time', '>', now());
    }

    public function scopePast($query)
    {
        return $query->where('start_time', '<', now());
    }


    public function complete(): bool
    {
        return $this->update(['status' => AppointmentStatus::COMPLETED]);
    }

    /**
     * Cancel the appointment on the CUSTOMER's behalf.
     *
     * Hardcodes USER_CANCELLED, so this is the one place a self-cancellation
     * happens — staff cancellations write ADMIN_CANCELLED directly and never come
     * through here. That makes it the natural place to notice a customer who
     * keeps cancelling: both customer-facing cancel endpoints funnel into it.
     *
     * The monitor is called after a successful write and swallows its own errors,
     * so alerting can never undo the cancellation.
     */
    public function cancel(?string $reason = null): bool
    {
        $cancelled = $this->update([
            'status' => AppointmentStatus::USER_CANCELLED,
            'cancellation_reason' => $reason,
            'cancelled_at' => now(),
        ]);

        if ($cancelled) {
            // The reminder is retired by the `updated` hook in boot(), which
            // watches the status column itself — so staff cancellations that
            // write ADMIN_CANCELLED directly are covered by the same rule.
            app(CancellationMonitor::class)->recordCustomerCancellation($this);
        }

        return $cancelled;
    }



    public function getFormattedDateAttribute(): string
    {
        return $this->appointment_date->format('M d, Y');
    }


    public function getTimeRangeAttribute(): string
    {
        return $this->start_time->format('h:i A') . ' - ' . $this->end_time->format('h:i A');
    }


    public function getFormattedDurationAttribute(): string
    {
        $hours = floor($this->duration_minutes / 60);
        $minutes = $this->duration_minutes % 60;

        if ($hours > 0 && $minutes > 0) {
            return "{$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$minutes}m";
        }
    }
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'appointment_id');
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'paymentable');
    }

    public function getCustomerEmailAttribute()
    {
        return $this->customer?->email ?? $this->getRawOriginal('customer_email');
    }

    public function getCustomerPhoneAttribute()
    {
        return $this->customer?->phone ?? $this->getRawOriginal('customer_phone');
    }
    public function getCustomerNameAttribute(): string
    {
        return $this->customer?->full_name
            ?? ($this->getRawOriginal('customer_name') ?: 'Guest');
    }

    public function getHasCustomerAccountAttribute(): bool
    {
        return (bool) $this->customer_id;
    }

    public function canPrintInvoice(): bool
    {
        if (! $this->payment_status?->isSuccessful()) {
            return false;
        }

        // Invoice always lives on parent (or self if standalone)
        $invoiceOwner = $this->parent ?? $this;

        $invoice = $invoiceOwner->relationLoaded('invoice')
            ? $invoiceOwner->invoice
            : $invoiceOwner->invoice()->first();

        return $invoice?->status?->isPaid() ?? false;
    }

    // ================================================================
    // Parent / Child Linking (single-level: parent → many children)
    // ================================================================

    /**
     * Parent appointment (null if standalone or if self is the parent).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_appointment_id');
    }

    /**
     * Child appointments — different providers, same customer, same day.
     * Invoice is NEVER on a child; it lives on the parent.
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_appointment_id');
    }

    /**
     * Query builder for the whole linked group (parent + all children).
     * Works regardless of whether $this is the parent or one of the children.
     */
    public function linkedGroup(): Builder
    {
        $rootId = $this->parent_appointment_id ?? $this->id;
        return self::query()
            ->where(function ($q) use ($rootId) {
                $q->where('id', $rootId)
                  ->orWhere('parent_appointment_id', $rootId);
            });
    }

    /**
     * True only if this appointment is a parent with at least one child.
     * NOTE: Uses children()->exists() — eager load 'children' to avoid N+1.
     */
    public function getIsParentBookingAttribute(): bool
    {
        if ($this->parent_appointment_id !== null) {
            return false;
        }
        if ($this->relationLoaded('children')) {
            return $this->children->isNotEmpty();
        }
        return $this->children()->exists();
    }

    public function getIsChildBookingAttribute(): bool
    {
        return $this->parent_appointment_id !== null;
    }

    public function getIsStandaloneBookingAttribute(): bool
    {
        if ($this->parent_appointment_id !== null) {
            return false;
        }
        if ($this->relationLoaded('children')) {
            return $this->children->isEmpty();
        }
        return ! $this->children()->exists();
    }

    /**
     * The appointment that owns the (unified) invoice for this group.
     * For a parent or standalone → self. For a child → its parent.
     */
    public function getInvoiceOwnerAttribute(): self
    {
        return $this->parent ?? $this;
    }

    // ================================================================
    // Scopes
    // ================================================================

    public function scopeParentsOnly(Builder $query): Builder
    {
        return $query->whereNull('parent_appointment_id');
    }

    public function scopeForProvider(Builder $query, int $providerId): Builder
    {
        return $query->where('provider_id', $providerId);
    }

    /**
     * THE single definition of "this appointment occupies the provider's time".
     *
     * Every layer that asks "is the provider busy?" MUST go through this scope:
     * the availability layer (what the customer is offered), the booking layer
     * (what the server accepts), and the staff dashboard. Before this existed
     * the availability layer and BookingValidationService carried two different
     * hand-written conditions, so a slot could be hidden with nothing booked on
     * it, or offered and then rejected on submit (BOOK-01).
     *
     *  - created_status = 1 → the booking is real. Since there is no online
     *    payment, every booking is created confirmed, so this is effectively
     *    always true today; it stays in the definition so a future deposit /
     *    online-payment flow only has to flip the column.
     *  - PENDING blocks: the service has not happened yet.
     *  - COMPLETED blocks: a booking marked done early still owns the rest of
     *    its scheduled window.
     *  - Cancelled / no-show do NOT block: the chair is genuinely free.
     */
    public function scopeBlocksProviderTime(Builder $query): Builder
    {
        return $query->where('created_status', 1)
            ->whereIn('status', [
                AppointmentStatus::PENDING->value,
                AppointmentStatus::COMPLETED->value,
            ]);
    }

    /**
     * Half-open interval overlap: [start, end) vs [$startTime, $endTime).
     *
     * Strict < and > are deliberate — back-to-back bookings (10:00-10:30 then
     * 10:30-11:00) do not overlap. Paired with scopeBlocksProviderTime() this
     * is the whole conflict rule.
     */
    public function scopeOverlapping(Builder $query, Carbon $startTime, Carbon $endTime): Builder
    {
        return $query->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime);
    }

    /**
     * Bookings eligible to be pushed forward in time:
     *  - same provider
     *  - same date
     *  - created_status = 1 (confirmed)
     *  - not cancelled, not completed
     *  - not paid (any successful payment state blocks push)
     *  - start_time >= $afterTime
     */
    public function scopePushable(Builder $query, int $providerId, Carbon $afterTime, string $date): Builder
    {
        return $query->where('provider_id', $providerId)
            ->whereDate('appointment_date', $date)
            ->where('created_status', 1)
            ->where('start_time', '>=', $afterTime)
            ->whereNotIn('status', [
                AppointmentStatus::USER_CANCELLED->value,
                AppointmentStatus::ADMIN_CANCELLED->value,
                AppointmentStatus::COMPLETED->value,
            ])
            ->whereNotIn('payment_status', [
                PaymentStatus::PAID_ONLINE->value,
                PaymentStatus::PAID_ONSTIE_CASH->value,
                PaymentStatus::PAID_ONSTIE_CARD->value,
            ]);
    }

    // ================================================================
    // Business Guards
    // ================================================================

    /**
     * True only if this appointment is currently allowed to accept a NEW service
     * (either added to same-provider services_record OR via creating a child).
     *
     * Allowed when:
     *  - status = PENDING
     *  - payment_status NOT in successful states
     *  - invoice (if exists) is still DRAFT
     *
     * A child appointment also requires its parent to be acceptable.
     */
    public function canAcceptNewService(): bool
    {
        if ($this->status !== AppointmentStatus::PENDING) {
            return false;
        }

        $blockingPayments = [
            PaymentStatus::PAID_ONLINE,
            PaymentStatus::PAID_ONSTIE_CASH,
            PaymentStatus::PAID_ONSTIE_CARD,
        ];
        if (in_array($this->payment_status, $blockingPayments, true)) {
            return false;
        }

        // Invoice lives on the parent — check it
        $invoiceOwner = $this->parent ?? $this;
        $invoice = $invoiceOwner->relationLoaded('invoice')
            ? $invoiceOwner->invoice
            : $invoiceOwner->invoice()->first();

        if ($invoice && $invoice->status !== InvoiceStatus::DRAFT) {
            return false;
        }

        // If this is a child, also check parent
        if ($this->is_child_booking) {
            $parent = $this->parent;
            if (! $parent) {
                return false;
            }
            // Avoid infinite recursion — manual check here
            if ($parent->status !== AppointmentStatus::PENDING) {
                return false;
            }
            if (in_array($parent->payment_status, $blockingPayments, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns whether this appointment can be cancelled or deleted,
     * accounting for linked children.
     *
     * @return array{allowed: bool, reason?: string, children_numbers?: array}
     */
    public function canBeCancelledOrDeleted(): array
    {
        // Block if has any non-cancelled children
        if ($this->is_parent_booking) {
            $children = $this->relationLoaded('children')
                ? $this->children
                : $this->children()->get();

            $activeChildren = $children->filter(function ($child) {
                return ! in_array($child->status, [
                    AppointmentStatus::USER_CANCELLED,
                    AppointmentStatus::ADMIN_CANCELLED,
                ], true);
            });

            if ($activeChildren->isNotEmpty()) {
                return [
                    'allowed' => false,
                    'reason' => 'has_active_children',
                    'children_numbers' => $activeChildren->pluck('number')->all(),
                ];
            }
        }

        return ['allowed' => true];
    }

}
