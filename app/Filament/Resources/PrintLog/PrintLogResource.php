<?php

namespace App\Filament\Resources\PrintLog;

use App\Filament\Resources\PrintLog\Pages;
use App\Filament\Resources\PrintLog\Schemas\PrintLogForm;
use App\Filament\Resources\PrintLog\Tables\PrintLogsTable;
use App\Models\PrintLog;
use App\Traits\NavigationDefaultAccess;
use App\Traits\ResourceTranslation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PrintLogResource extends Resource {
    use NavigationDefaultAccess, ResourceTranslation;
    protected static ?string $model = PrintLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|\UnitEnum|null $navigationGroup = 'billing';

    protected static ?int $navigationSort = 42;

    protected static ?string $label = 'Print Log';

    protected static ?string $pluralLabel = 'Print Logs';


    public static function form(Schema $schema): Schema {
        return PrintLogForm::configure($schema);
    }

    public static function table(Table $table): Table {
        return PrintLogsTable::configure($table);
    }

    public static function getRelations(): array {
        return [];
    }

    public static function getPages(): array {
        return [
            'index' => Pages\ListPrintLogs::route('/'),
            'view'  => Pages\ViewPrintLog::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string {
        return PrintLog::whereDate('created_at', today())->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string {
        return 'info';
    }

    public static function canCreate(): bool {
        return false;
    }
}
