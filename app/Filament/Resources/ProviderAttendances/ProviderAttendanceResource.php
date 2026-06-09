<?php

namespace App\Filament\Resources\ProviderAttendances;

use App\Filament\Resources\ProviderAttendances\Pages\CreateProviderAttendance;
use App\Filament\Resources\ProviderAttendances\Pages\EditProviderAttendance;
use App\Filament\Resources\ProviderAttendances\Pages\ListProviderAttendances;
use App\Filament\Resources\ProviderAttendances\Pages\ViewProviderAttendance;
use App\Filament\Resources\ProviderAttendances\Schemas\ProviderAttendanceForm;
use App\Filament\Resources\ProviderAttendances\Tables\ProviderAttendancesTable;
use App\Models\ProviderAttendance;
use App\Traits\NavigationDefaultAccess;
use App\Traits\ResourceTranslation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProviderAttendanceResource extends Resource
{
    use NavigationDefaultAccess;
    use ResourceTranslation;
    public static function canAccess(): bool {
        return false; // This resource is only for record-level corrections via the Attendance Board cards, so we hide it from the sidebar and gate access via the board's own permission.
    }

    protected static ?string $model = ProviderAttendance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static string|\UnitEnum|null $navigationGroup = 'staff';

    protected static ?int $navigationSort = 25;

    protected static ?string $recordTitleAttribute = 'work_date';

    public static function form(Schema $schema): Schema
    {
        return ProviderAttendanceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProviderAttendancesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListProviderAttendances::route('/'),
            'create' => CreateProviderAttendance::route('/create'),
            'view'   => ViewProviderAttendance::route('/{record}'),
            'edit'   => EditProviderAttendance::route('/{record}/edit'),
        ];
    }
}
