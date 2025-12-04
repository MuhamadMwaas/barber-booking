<?php

namespace App\Filament\Resources\ReasonLeaves;

use App\Filament\Resources\ReasonLeaves\Pages\CreateReasonLeave;
use App\Filament\Resources\ReasonLeaves\Pages\EditReasonLeave;
use App\Filament\Resources\ReasonLeaves\Pages\ListReasonLeaves;
use App\Filament\Resources\ReasonLeaves\Schemas\ReasonLeaveForm;
use App\Filament\Resources\ReasonLeaves\Schemas\ReasonLeaveInfolist;
use App\Filament\Resources\ReasonLeaves\Tables\ReasonLeavesTable;
use App\Models\ReasonLeave;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ReasonLeaveResource extends Resource
{
    protected static ?string $model = ReasonLeave::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ReasonLeaveForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReasonLeavesTable::configure($table);
    }

        public static function infolist(Schema $schema): Schema
    {
        return ReasonLeaveInfolist::configure($schema);
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
            'index' => ListReasonLeaves::route('/'),
            'create' => CreateReasonLeave::route('/create'),
            'edit' => EditReasonLeave::route('/{record}/edit'),
        ];
    }
}
