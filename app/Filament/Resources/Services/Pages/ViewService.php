<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Services\ServiceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewService extends ViewRecord
{
    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    protected function resolveRecord(int | string $key): Model
    {
        return static::getResource()::resolveRecordRouteBinding($key)
            ->load([
                'category',
                'image',
                'icon',
                'providers' => function ($query) {
                    $query->withPivot(['custom_price', 'custom_duration', 'is_active', 'notes']);
                },
                'appointmentServices.appointment',
                'translations.language',
            ]);
    }
}
