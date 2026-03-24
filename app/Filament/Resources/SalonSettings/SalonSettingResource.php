<?php

namespace App\Filament\Resources\SalonSettings;

use App\Filament\Resources\SalonSettings\Pages\CreateSalonSetting;
use App\Filament\Resources\SalonSettings\Pages\EditSalonSetting;
use App\Filament\Resources\SalonSettings\Pages\ListSalonSettings;
use App\Filament\Resources\SalonSettings\Pages\ViewSalonSetting;
use App\Filament\Resources\SalonSettings\Schemas\SalonSettingForm;
use App\Filament\Resources\SalonSettings\Schemas\SalonSettingInfolist;
use App\Filament\Resources\SalonSettings\Tables\SalonSettingsTable;
use App\Models\SalonSetting;
use App\Traits\NavigationDefaultAccess;
use App\Traits\ResourceTranslation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SalonSettingResource extends Resource
{
    use NavigationDefaultAccess, ResourceTranslation;
    protected static ?string $model = SalonSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'settings';

    protected static ?int $navigationSort = 60;

    protected static ?string $recordTitleAttribute = 'key';

    public static function form(Schema $schema): Schema
    {
        return SalonSettingForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SalonSettingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalonSettingsTable::configure($table);
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
            'index' => ListSalonSettings::route('/'),
            'create' => CreateSalonSetting::route('/create'),
            'view' => ViewSalonSetting::route('/{record}'),
            'edit' => EditSalonSetting::route('/{record}/edit'),
        ];
    }
}
