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

        // Handle profile image upload
        if (isset($data['profile_image_file']) && !empty($data['profile_image_file'])) {
            $file = $data['profile_image_file'][0] ?? null;
            if ($file) {
                $uploadedFile = new \Illuminate\Http\UploadedFile(
                    storage_path('app/public/' . $file),
                    basename($file)
                );
                $record->updateProfileImage($uploadedFile);
            }
        }

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
