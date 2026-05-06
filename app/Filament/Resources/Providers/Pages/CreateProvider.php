<?php

namespace App\Filament\Resources\Providers\Pages;

use App\Filament\Resources\Providers\ProviderResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProvider extends CreateRecord
{
    protected static string $resource = ProviderResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $record = static::getModel()::create($data);

        // Assign provider role
        $record->assignRole('provider');

        return $record;
    }

    protected function afterCreate(): void
    {
        $this->handleProfileImageUpload();
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

            $this->record->updateProfileImage($uploadedFile);

            @unlink($tempPath);

            logger()->info("Provider profile image uploaded for user {$this->record->id}");

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
