<?php

namespace App\Filament\Resources\Providers\Pages;

use App\Filament\Concerns\HandlesProfileImageUpload;
use App\Filament\Resources\Providers\ProviderResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProvider extends CreateRecord
{
    use HandlesProfileImageUpload;

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

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
