<?php

namespace App\Filament\Resources\Providers\Pages;

use App\Filament\Resources\Providers\ProviderResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditProvider extends EditRecord
{
    protected static string $resource = ProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->update($data);

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
