<?php

namespace App\Filament\Resources\Roles\Tables;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RolesTable
{
    private const PROTECTED_ROLES = ['SuperAdmin', 'admin'];

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('resources.role.name'))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->icon('heroicon-o-shield-check')
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        in_array($state, self::PROTECTED_ROLES) => 'danger',
                        $state === 'manager' => 'warning',
                        $state === 'provider' => 'info',
                        $state === 'customer' => 'gray',
                        default => 'success',
                    }),

                TextColumn::make('guard_name')
                    ->label(__('resources.role.guard'))
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('permissions_count')
                    ->label(__('resources.role.permissions_count'))
                    ->counts('permissions')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('users_count')
                    ->label(__('resources.role.users_count'))
                    ->counts('users')
                    ->badge()
                    ->color('success')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('resources.role.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name', 'asc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->hidden(fn ($record) => in_array($record->name, self::PROTECTED_ROLES)),
                DeleteAction::make()
                    ->hidden(fn ($record) => in_array($record->name, self::PROTECTED_ROLES)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function ($records) {
                            // Filter out protected roles
                            return $records->reject(fn ($record) => in_array($record->name, self::PROTECTED_ROLES));
                        }),
                ]),
            ]);
    }
}
