<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        // Extract role before creating user
        $role = $data['role'] ?? null;
        unset($data['role']);

        // Create user
        $record = static::getModel()::create($data);

        // Assign role if provided
        if ($role) {
            $record->assignRole($role);
        }

        return $record;
    }

    protected function afterCreate(): void
    {
        // Handle profile image upload
        $this->handleProfileImageUpload();
    }

    protected function handleProfileImageUpload(): void
    {
        try {
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

            logger()->info("Processing profile image: {$profileImageFile}");

            // Get the uploaded file from temporary storage
            $tempPath = storage_path('app/public/' . $profileImageFile);

            if (!file_exists($tempPath)) {
                logger()->warning("Profile image file not found at: {$tempPath}");
                return;
            }

            // Get file info
            $mimeType = mime_content_type($tempPath);
            $originalName = basename($profileImageFile);

            logger()->info("Creating UploadedFile instance", [
                'path' => $tempPath,
                'name' => $originalName,
                'mime' => $mimeType
            ]);

            // Create UploadedFile instance from temporary file
            $uploadedFile = new \Illuminate\Http\UploadedFile(
                $tempPath,
                $originalName,
                $mimeType,
                null,
                true // test mode - don't validate file
            );

            // Use the User model's updateProfileImage method
            $this->record->updateProfileImage($uploadedFile);

            // Clean up temporary file
            @unlink($tempPath);

            logger()->info("Profile image uploaded successfully for user {$this->record->id}");

        } catch (\Exception $e) {
            // Log error but don't fail the user creation
            logger()->error('Failed to upload profile image: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
