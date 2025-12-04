<?php

namespace App\Filament\Resources\ReasonLeaves\Pages;

use App\Filament\Resources\ReasonLeaves\ReasonLeaveResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReasonLeaves extends ListRecords
{
    protected static string $resource = ReasonLeaveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
