<?php

namespace App\Filament\Resources\Colors;

use App\Filament\Resources\Colors\Pages\ListColors;
use App\Filament\Resources\Colors\Pages\ViewColor;
use App\Filament\Resources\Colors\Schemas\ColorForm;
use App\Filament\Resources\Colors\Schemas\ColorInfolist;
use App\Filament\Resources\Colors\Tables\ColorsTable;
use App\Models\Color;
use App\Traits\NavigationDefaultAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ColorResource extends Resource
{
    // use NavigationDefaultAccess;

    protected static ?string $model = Color::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static string|\UnitEnum|null $navigationGroup = 'services';

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.services');
    }

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('resources.color.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.color.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return ColorForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ColorInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ColorsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListColors::route('/'),
            'view'  => ViewColor::route('/{record}'),
        ];
    }
}
