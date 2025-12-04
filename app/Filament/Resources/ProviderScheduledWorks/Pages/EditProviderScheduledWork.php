<?php

namespace App\Filament\Resources\ProviderScheduledWorks\Pages;

use App\Filament\Resources\ProviderScheduledWorks\ProviderScheduledWorkResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProviderScheduledWork extends EditRecord
{
    protected static string $resource = ProviderScheduledWorkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
