<?php

namespace App\Filament\Resources\Providers\Pages;

use App\Filament\Resources\Providers\ProviderResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditProvider extends EditRecord
{
    protected static string $resource = ProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->before(function (DeleteAction $action) {
                    $provider = $this->getRecord();

                    // Block deletion if the provider has any appointments (provider_id is NOT nullable)
                    if ($provider->appointmentsAsProvider()->exists()) {
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
                    $provider->scheduledWorks()->delete();
                    $provider->timeOffs()->delete();
                    $provider->services()->detach();
                    $provider->serviceReviews()->delete();
                }),
        ];
    }

    protected function afterSave(): void
    {
        $this->handleProfileImageUpload();

        // After save, sync the FileUpload component state to the current permanent image path.
        // Without this, Livewire re-renders with the deleted temp path → upload box appears empty.
        $this->record->unsetRelation('profile_image');
        $this->data['profile_image_file'] = $this->record->profile_image
            ? [$this->record->profile_image->path]
            : null;
    }

    protected function handleProfileImageUpload(): void
    {
        try {
            // Use getRawState() because the field has dehydrated(false)
            $formState = $this->form->getRawState();
            $profileImageFile = $formState['profile_image_file'] ?? null;

            if (!$profileImageFile) {
                return;
            }

            if (is_array($profileImageFile)) {
                $profileImageFile = array_shift($profileImageFile);
            }

            if (!$profileImageFile || !is_string($profileImageFile)) {
                return;
            }

            // Skip if it's the existing image (not a new upload)
            $currentImagePath = optional($this->record->profile_image)->path;
            if ($profileImageFile === $currentImagePath) {
                return;
            }

            // Only process new uploads from temp directory
            if (!str_contains($profileImageFile, 'temp/uploads')) {
                return;
            }

            $tempPath = storage_path('app/public/' . $profileImageFile);

            if (!file_exists($tempPath)) {
                logger()->warning("Provider profile image file not found at: {$tempPath}");
                return;
            }

            $mimeType = mime_content_type($tempPath);

            // test=true bypasses is_uploaded_file() check for files already on disk
            $uploadedFile = new \Illuminate\Http\UploadedFile(
                $tempPath,
                basename($profileImageFile),
                $mimeType,
                null,
                true
            );

            $this->record->refresh();
            $this->record->updateProfileImage($uploadedFile);

            @unlink($tempPath);

            logger()->info("Provider profile image updated for user {$this->record->id}");

        } catch (\Exception $e) {
            logger()->error('Failed to upload provider profile image: ' . $e->getMessage(), [
                'provider_id' => $this->record->id ?? null,
                'trace'       => $e->getTraceAsString(),
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
