<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Exceptions\AppointmentNotDeletableException;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Models\Appointment;
use App\Services\AppointmentDeletionService;
use App\Services\BookingLockService;
use App\Services\BookingValidationService;
use Carbon\Carbon;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditAppointment extends EditRecord
{
    protected static string $resource = AppointmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            // A bare $record->delete() hits the RESTRICT foreign keys on
            // appointment_services / invoices — deletion goes through the
            // shared service, which also enforces the paid/completed/children rules.
            DeleteAction::make()
                ->using(function (Appointment $record): bool {
                    try {
                        app(AppointmentDeletionService::class)->delete($record);

                        return true;
                    } catch (AppointmentNotDeletableException $e) {
                        Notification::make()
                            ->title(__('resources.appointment.delete_blocked_title'))
                            ->body($e->userMessage())
                            ->danger()
                            ->persistent()
                            ->send();

                        return false;
                    }
                }),
        ];
    }

    /**
     * Saving this form can move an appointment — another provider, another day,
     * another hour, a longer duration — or bring a cancelled one back to
     * PENDING. Each of those writes onto a provider's calendar, so it obeys the
     * same contract as every other booking path: lock the provider, then check,
     * then write, all in one transaction (BOOK-02). This page used to call a
     * bare $record->update() with no conflict check at all.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Appointment $record */
        $data = $this->normaliseSchedule($record, $data);

        return DB::transaction(function () use ($record, $data) {
            $originalProviderId = $record->getRawOriginal('provider_id');
            $wasOccupying = (clone $record)
                ->setRawAttributes($record->getRawOriginal())
                ->occupiesProviderTime();

            $record->fill($data);

            $claimsNewTime = $record->occupiesProviderTime() && (
                ! $wasOccupying
                || $record->isDirty(['provider_id', 'appointment_date', 'start_time', 'end_time'])
            );

            // Notes, prices, payment fields: nothing on the calendar changes,
            // so nothing to check — and an appointment that a force booking
            // deliberately overlaps must stay editable.
            if ($claimsNewTime) {
                // Both providers when it changes hands: the old calendar is
                // being freed and the new one claimed. lockUsers() sorts ids,
                // so this cannot deadlock against another booking.
                app(BookingLockService::class)->lockUsers([
                    $originalProviderId,
                    $record->provider_id,
                    $record->customer_id,
                ]);

                if (! $this->canForceBooking()) {
                    $this->assertSlotIsFree($record);
                }
            }

            $record->save();

            return $record;
        });
    }

    /**
     * The form sends the hour alone ("10:00:00"), and a datetime cast reads a
     * bare hour as TODAY — editing an appointment on the 20th used to save it
     * onto today's date. Rebuild start/end from the appointment's date, the
     * hour and the duration, exactly as CreateAppointment does, so end_time is
     * never left stale either.
     */
    private function normaliseSchedule(Appointment $record, array $data): array
    {
        $date = Carbon::parse($data['appointment_date'] ?? $record->appointment_date)->format('Y-m-d');
        $time = Carbon::parse($data['start_time'] ?? $record->start_time)->format('H:i:s');
        $duration = (int) ($data['duration_minutes'] ?? $record->duration_minutes);

        if ($duration <= 0) {
            $this->rejectAndHalt(__('messages.appointment.duration_greater_than_zero'));
        }

        $start = Carbon::parse("{$date} {$time}");

        $data['appointment_date'] = $date;
        $data['start_time'] = $start;
        $data['end_time'] = $start->copy()->addMinutes($duration);
        $data['duration_minutes'] = $duration;

        return $data;
    }

    /**
     * Called under the lock: the answers here cannot go stale before the
     * write, because every other writer to these calendars is queued behind us.
     */
    private function assertSlotIsFree(Appointment $record): void
    {
        $validation = app(BookingValidationService::class);

        // Queried, not the loaded relations: after fill() a cached `provider`
        // would still be the one the appointment is moving away from.
        $provider = $record->provider()->first();
        $customer = $record->customer_id ? $record->customer()->first() : null;

        try {
            $validation->assertNoConflictingAppointment(
                $provider,
                $record->start_time,
                $record->end_time,
                $record->id,   // an appointment never conflicts with itself
            );

            $validation->assertCustomerIsFree(
                $customer,
                $record->getAttributes()['customer_phone'] ?? null,
                $record->start_time,
                $record->end_time,
                $record->id,
            );
        } catch (\InvalidArgumentException $e) {
            $this->rejectAndHalt($e->getMessage());
        }
    }

    /**
     * Same authorisation StaffDashboard uses to let a move overlap on purpose,
     * so the same person gets the same answer in both places.
     */
    private function canForceBooking(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->hasRole('SuperAdmin') || $user->can('StaffDashboard:force_booking'));
    }

    /**
     * Halt unwinds through DB::transaction, which rolls the save back.
     */
    private function rejectAndHalt(string $message): never
    {
        Notification::make()
            ->danger()
            ->title(__('messages.appointment.validation_error'))
            ->body($message)
            ->persistent()
            ->send();

        $this->halt();
    }
}
