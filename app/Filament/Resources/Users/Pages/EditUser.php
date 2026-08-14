<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Concerns\HandlesProfileImageUpload;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditUser extends EditRecord
{
    use HandlesProfileImageUpload;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->before(function (DeleteAction $action) {
                    $user = $this->getRecord();

                    if ($user->appointmentsAsProvider()->exists()) {
                        Notification::make()
                            ->danger()
                            ->title(__('resources.provider_resource.cannot_delete_title'))
                            ->body(__('resources.provider_resource.cannot_delete_has_appointments'))
                            ->persistent()
                            ->send();

                        $action->halt();
                        return;
                    }

                    // Clean up operational data that would block the delete
                    $user->scheduledWorks()->delete();
                    $user->timeOffs()->delete();
                    $user->services()->detach();
                    $user->serviceReviews()->delete();
                }),
            RestoreAction::make(),
            ForceDeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Add current role to form data
        $data['role'] = $this->record->roles->first()?->name;
        $data['profile_image_file'] = $this->record->profile_image?->path;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Remove role from data as it will be synced separately
        unset($data['role']);

        return $data;
    }

    protected function afterSave(): void
    {
        $role = $this->data['role'] ?? null;
        // Sync roles
        if ($role) {
            $this->record->syncRoles([$role]);
        }

        // Handle profile image upload
        $this->handleProfileImageUpload();

        // Sync FileUpload component state to current permanent image path.
        // Without this, Livewire re-renders with the deleted temp path → upload box appears empty.
        // The UUID key matches how Filament itself keys FileUpload state, so a
        // following upload replaces this entry instead of sitting next to it.
        $this->record->unsetRelation('profile_image');
        $this->data['profile_image_file'] = $this->record->profile_image
            ? [(string) Str::uuid() => $this->record->profile_image->path]
            : [];
    }
}
