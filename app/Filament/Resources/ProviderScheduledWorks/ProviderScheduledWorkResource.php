<?php

namespace App\Filament\Resources\ProviderScheduledWorks;

use App\Filament\Resources\ProviderScheduledWorks\Pages\CreateProviderScheduledWork;
use App\Filament\Resources\ProviderScheduledWorks\Pages\EditProviderScheduledWork;
use App\Filament\Resources\ProviderScheduledWorks\Pages\ListProviderScheduledWorks;
use App\Filament\Resources\ProviderScheduledWorks\Pages\ViewProviderScheduledWork;
use App\Filament\Resources\ProviderScheduledWorks\Schemas\ProviderScheduledWorkForm;
use App\Filament\Resources\ProviderScheduledWorks\Schemas\ProviderScheduledWorkInfolist;
use App\Filament\Resources\ProviderScheduledWorks\Tables\ProviderScheduledWorksTable;
use App\Models\ProviderScheduledWork;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProviderScheduledWorkResource extends Resource
{
    protected static ?string $model = ProviderScheduledWork::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'day_of_week';

    public static function form(Schema $schema): Schema
    {
        return ProviderScheduledWorkForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProviderScheduledWorkInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProviderScheduledWorksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProviderScheduledWorks::route('/'),
            'create' => CreateProviderScheduledWork::route('/create'),
            'view' => ViewProviderScheduledWork::route('/{record}'),
            'edit' => EditProviderScheduledWork::route('/{record}/edit'),
        ];
    }
}
