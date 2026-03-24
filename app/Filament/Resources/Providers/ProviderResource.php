<?php

namespace App\Filament\Resources\Providers;

use App\Filament\Resources\Providers\Pages\CreateProvider;
use App\Filament\Resources\Providers\Pages\EditProvider;
use App\Filament\Resources\Providers\Pages\ListProviders;
use App\Filament\Resources\Providers\Pages\ViewProvider;
use App\Filament\Resources\Providers\RelationManagers\AppointmentsRelationManager;
use App\Filament\Resources\Providers\RelationManagers\ScheduledWorksRelationManager;
use App\Filament\Resources\Providers\RelationManagers\TimeOffsRelationManager;
use App\Filament\Resources\Providers\Schemas\ProviderForm;
use App\Filament\Resources\Providers\Schemas\ProviderInfolist;
use App\Filament\Resources\Providers\Tables\ProvidersTable;
use App\Models\User;
use App\Traits\NavigationDefaultAccess;
use App\Traits\ResourceTranslation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProviderResource extends Resource
{
    use NavigationDefaultAccess, ResourceTranslation;

    protected static ?string $model = User::class;

    protected static ?string $translationResourceKey = 'provider_resource';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'staff';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->role('provider');
    }

    public static function form(Schema $schema): Schema
    {
        return ProviderForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProviderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProvidersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            AppointmentsRelationManager::class,
            ScheduledWorksRelationManager::class,
            TimeOffsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProviders::route('/'),
            'create' => CreateProvider::route('/create'),
            'view' => ViewProvider::route('/{record}'),
            'edit' => EditProvider::route('/{record}/edit'),
        ];
    }
}
