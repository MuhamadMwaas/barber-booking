<?php

namespace App\Filament\Resources\PrinterSetting;

use App\Filament\Resources\PrinterSetting\Pages;
use App\Filament\Resources\PrinterSetting\Schemas\PrinterSettingForm;
use App\Filament\Resources\PrinterSetting\Tables\PrinterSettingsTable;
use App\Models\PrinterSetting;
use App\Traits\NavigationDefaultAccess;
use App\Traits\ResourceTranslation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PrinterSettingResource extends Resource {
    use NavigationDefaultAccess, ResourceTranslation;
    protected static ?string $model = PrinterSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPrinter;

    protected static string|\UnitEnum|null $navigationGroup = 'billing';

    protected static ?int $navigationSort = 41;

    protected static ?string $label = 'Printer';

    protected static ?string $pluralLabel = 'Printers';

    public static function form(Schema $schema): Schema {
        return PrinterSettingForm::configure($schema);
    }

    public static function table(Table $table): Table {
        return PrinterSettingsTable::configure($table);
    }

    public static function getRelations(): array {
        return [];
    }

    public static function getPages(): array {
        return [
            'index'  => Pages\ListPrinterSettings::route('/'),
            'create' => Pages\CreatePrinterSetting::route('/create'),
            'edit'   => Pages\EditPrinterSetting::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string {
        return PrinterSetting::active()->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string {
        return 'success';
    }
}
