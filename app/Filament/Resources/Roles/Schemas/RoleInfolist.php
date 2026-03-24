<?php

namespace App\Filament\Resources\Roles\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RoleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('resources.role.role_details'))
                    ->icon('heroicon-o-shield-check')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('resources.role.name'))
                            ->badge()
                            ->color('primary'),

                        TextEntry::make('guard_name')
                            ->label(__('resources.role.guard'))
                            ->badge()
                            ->color('gray'),

                        TextEntry::make('created_at')
                            ->label(__('resources.role.created_at'))
                            ->dateTime(),
                    ]),

                Section::make(__('resources.role.statistics'))
                    ->icon('heroicon-o-chart-bar')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('permissions_count')
                            ->label(__('resources.role.permissions_count'))
                            ->state(fn ($record) => $record->permissions()->count())
                            ->badge()
                            ->color('info'),

                        TextEntry::make('users_count')
                            ->label(__('resources.role.users_count'))
                            ->state(fn ($record) => $record->users()->count())
                            ->badge()
                            ->color('success'),
                    ]),

                Section::make(__('resources.role.assigned_permissions'))
                    ->icon('heroicon-o-key')
                    ->schema([
                        TextEntry::make('permissions.name')
                            ->label('')
                            ->badge()
                            ->color('info')
                            ->separator(','),
                    ]),
            ]);
    }
}
