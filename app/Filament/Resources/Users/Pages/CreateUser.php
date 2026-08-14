<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Concerns\HandlesProfileImageUpload;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    use HandlesProfileImageUpload;

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

}
