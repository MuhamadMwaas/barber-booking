<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Users\Pages\EditUser;

class EditCustomer extends EditUser
{
    protected static string $resource = CustomerResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // parent:: also hydrates the profile picture into the upload field.
        $data = parent::mutateFormDataBeforeFill($data);
        $data['role'] = 'customer';

        return $data;
    }

    protected function afterSave(): void
    {
        // parent:: syncs the role from $data, stores the uploaded picture and
        // refreshes the upload field with its permanent path.
        $this->data['role'] = 'customer';

        parent::afterSave();
    }
}
