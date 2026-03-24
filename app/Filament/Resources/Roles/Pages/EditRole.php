<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\Models\Permission;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    private array $selectedPermissions = [];

    protected function getHeaderActions(): array
    {
        $actions = [
            ViewAction::make(),
        ];

        if (!RoleResource::isProtectedRole($this->record->name)) {
            $actions[] = DeleteAction::make()
                ->successNotificationTitle(__('resources.role.deleted_notification'));
        }

        return $actions;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return __('resources.role.updated_notification');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['permissions'] = $this->record->permissions->pluck('name')->toArray();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->selectedPermissions = $data['permissions'] ?? [];
        unset($data['permissions']);

        return $data;
    }

    protected function afterSave(): void
    {
        $permissions = Permission::where('guard_name', 'web')
            ->whereIn('name', $this->selectedPermissions)
            ->get();

        $this->record->syncPermissions($permissions);
    }
}
