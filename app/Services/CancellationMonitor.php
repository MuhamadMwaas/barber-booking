<?php

namespace App\Services;

use App\Enum\AppointmentStatus;
use App\Filament\Resources\Users\UserResource;
use App\Models\Appointment;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Watches customers who keep cancelling.
 *
 * Cancelling is allowed at any time, including after the appointment was due to
 * start — see AppointmentService::cancelAppointment() for why. The deterrent is
 * visibility, not a locked button: a customer who cancels repeatedly costs the
 * salon chair time, and the manager should hear about it while the pattern is
 * still fresh rather than discovering it in a monthly report.
 *
 * The rule: from the SECOND cancellation inside a rolling seven days, every
 * further cancellation raises a Filament database notification carrying the
 * running count, so an escalating pattern is visible as it escalates.
 */
class CancellationMonitor
{
    /** Cancellations at or above this count inside the window raise an alert. */
    private const ALERT_THRESHOLD = 2;

    /** Rolling window, in days, counted backwards from the cancellation. */
    private const WINDOW_DAYS = 7;

    /** Panel roles that should hear about it. */
    private const RECIPIENT_ROLES = ['admin', 'manager'];

    /**
     * Called after a customer cancels one of their own bookings.
     *
     * Never throws: an alerting problem must not undo a cancellation the
     * customer already performed successfully.
     */
    public function recordCustomerCancellation(Appointment $appointment): void
    {
        try {
            $customer = $appointment->customer;

            // Only ADMIN_CANCELLED reaches guest bookings, and this method is only
            // called from the authenticated customer paths, so a missing customer
            // means there is nobody to build a history for.
            if ($customer === null) {
                return;
            }

            $count = $this->recentCancellationCount($customer);

            if ($count < self::ALERT_THRESHOLD) {
                return;
            }

            $this->alert($customer, $appointment, $count);
        } catch (\Throwable $e) {
            Log::warning('Cancellation alert failed', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * How many bookings this customer has cancelled themselves in the window.
     *
     * Counts USER_CANCELLED only. An ADMIN_CANCELLED booking is usually the salon's
     * own doing — a provider called in sick — and holding that against the customer
     * would produce alerts nobody can act on. NO_SHOW is left out for the same
     * reason it is a separate status: it is a different behaviour, recorded by
     * staff, and worth its own decision rather than being folded in silently.
     *
     * The window is measured from `cancelled_at`, i.e. when the customer walked
     * away — not from the appointment date, which may be months out.
     */
    public function recentCancellationCount(User $customer): int
    {
        return Appointment::query()
            ->where('customer_id', $customer->id)
            ->where('status', AppointmentStatus::USER_CANCELLED->value)
            ->where('cancelled_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->count();
    }

    /**
     * Raise the Filament database notification on every panel user who should see it.
     */
    private function alert(User $customer, Appointment $appointment, int $count): void
    {
        $recipients = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', self::RECIPIENT_ROLES))
            ->where('is_active', true)
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::make()
            ->warning()
            ->icon('heroicon-o-exclamation-triangle')
            ->title(__('notification.repeat_cancellation_title', [
                'customer' => $customer->full_name,
            ]))
            ->body(__('notification.repeat_cancellation_body', [
                'count' => $count,
                'days' => self::WINDOW_DAYS,
                'number' => $appointment->number,
            ]))
            ->actions([
                Action::make('view_customer')
                    ->label(__('notification.repeat_cancellation_view_customer'))
                    ->url(UserResource::getUrl('edit', ['record' => $customer->getKey()]))
                    ->markAsRead(),
            ])
            ->sendToDatabase($recipients);
    }
}
