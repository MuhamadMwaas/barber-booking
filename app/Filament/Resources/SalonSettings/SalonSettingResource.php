<?php

namespace App\Filament\Resources\SalonSettings;

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
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class SalonSettingResource extends Resource
{
    use NavigationDefaultAccess, ResourceTranslation;

    protected static ?string $model = SalonSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'settings';

    protected static ?int $navigationSort = 60;

    protected static ?string $recordTitleAttribute = 'key';

    /**
     * Settings are defined by the code that reads them, so they are edited here
     * but never added or removed. A hand-made key has no reader, and a deleted
     * one fails quietly: without its row `tax_rate` falls back to 0 on bookings.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record ? SalonSettingCatalog::label($record->key) : null;
    }

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
            'view' => ViewSalonSetting::route('/{record}'),
            'edit' => EditSalonSetting::route('/{record}/edit'),
        ];
    }
}
