<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\Models\Permission;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    private array $selectedPermissions = [];

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('resources.role.created_notification');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->selectedPermissions = $data['permissions'] ?? [];
        unset($data['permissions']);

        $data['guard_name'] = 'web';

        return $data;
    }

    protected function afterCreate(): void
    {
        if (!empty($this->selectedPermissions)) {
            $permissions = Permission::where('guard_name', 'web')
                ->whereIn('name', $this->selectedPermissions)
                ->get();

            $this->record->syncPermissions($permissions);
        }
    }
}
