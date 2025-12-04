<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Add current role to form data
        $data['role'] = $this->record->roles->first()?->name;

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
    }

    protected function handleProfileImageUpload(): void
    {
        try {
            // Get the raw form state to access file uploads
            $formState = $this->form->getRawState();
            $profileImageFile = $formState['profile_image_file'] ?? null;

            if (!$profileImageFile) {
                logger()->info('No profile image file in form state');
                return;
            }

            // If it's an array, get the first element (Filament returns array of file paths)
            if (is_array($profileImageFile)) {
                $profileImageFile = array_shift($profileImageFile);
            }

            if (!$profileImageFile || !is_string($profileImageFile)) {
                logger()->info('Profile image file is not a valid string');
                return;
            }

            logger()->info("Profile image file from form: {$profileImageFile}");

            // Check if this is the existing image (not a new upload)
            $currentImagePath = optional($this->record->profile_image)->path;

            // If it's the same path as current image, no action needed
            if ($profileImageFile === $currentImagePath) {
                logger()->info('Profile image unchanged - same as current image');
                return;
            }

            // Check if this is from temp directory (new upload)
            if (!str_contains($profileImageFile, 'temp/uploads')) {
                logger()->info('Profile image is not from temp directory - appears to be existing image');
                return;
            }

            // This is a new upload from temp directory
            $tempPath = storage_path('app/public/' . $profileImageFile);

            if (!file_exists($tempPath)) {
                logger()->warning("Profile image file not found at: {$tempPath}");
                return;
            }

            logger()->info("Processing new profile image: {$tempPath}");

            // Get file info
            $mimeType = mime_content_type($tempPath);
            $originalName = basename($profileImageFile);

            // Create UploadedFile instance from temporary file
            $uploadedFile = new \Illuminate\Http\UploadedFile(
                $tempPath,
                $originalName,
                $mimeType,
                null,
                true // test mode - don't validate file
            );

            // Use the User model's updateProfileImage method
            $this->record->refresh(); // Refresh the model to get latest data
            $this->record->updateProfileImage($uploadedFile);

            // Clean up temporary file
            @unlink($tempPath);

            logger()->info("Profile image updated successfully for user {$this->record->id}");

        } catch (\Exception $e) {
            // Log error but don't fail the update
            logger()->error('Failed to upload profile image: ' . $e->getMessage(), [
                'user_id' => $this->record->id ?? null,
                'file' => $profileImageFile ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
