<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Exceptions\AppointmentNotDeletableException;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Models\Appointment;
use App\Services\AppointmentDeletionService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

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
}
