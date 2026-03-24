<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewRole extends ViewRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [];

        if (!RoleResource::isProtectedRole($this->record->name)) {
            $actions[] = EditAction::make();
        }

        return $actions;
    }

    protected function resolveRecord(int|string $key): Model
    {
        return static::getResource()::resolveRecordRouteBinding($key)
            ->loadCount(['permissions', 'users']);
    }
}
