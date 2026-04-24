<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Users\Pages\EditUser;

class EditCustomer extends EditUser
{
    protected static string $resource = CustomerResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['role'] = 'customer';

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->syncRoles(['customer']);

        $this->handleProfileImageUpload();
    }
}
