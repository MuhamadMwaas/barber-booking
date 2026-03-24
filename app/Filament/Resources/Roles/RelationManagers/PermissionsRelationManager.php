<?php

namespace App\Filament\Resources\Roles\RelationManagers;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PermissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'permissions';

    public function table(Table $table): Table
    {
        $isProtected = RoleResource::isProtectedRole($this->getOwnerRecord()->name);

        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('resources.role.permission_name'))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::SemiBold)
                    ->formatStateUsing(function (string $state) {
                        $parts = explode(':', $state, 2);
                        if (count($parts) === 2) {
                            return $parts[0] . ' → ' . ucfirst(str_replace('_', ' ', $parts[1]));
                        }
                        return $state;
                    }),

                TextColumn::make('group')
                    ->label(__('resources.role.group'))
                    ->state(fn ($record) => explode(':', $record->name)[0] ?? '-')
                    ->badge()
                    ->color('info')
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('name', $direction)),

                TextColumn::make('guard_name')
                    ->label(__('resources.role.guard'))
                    ->badge()
                    ->color('gray'),
            ])
            ->defaultSort('name', 'asc')
            ->headerActions(
                $isProtected ? [] : [
                    AttachAction::make()
                        ->label(__('resources.role.attach_permission'))
                        ->preloadRecordSelect()
                        ->multiple(),
                ]
            )
            ->recordActions(
                $isProtected ? [] : [
                    DetachAction::make(),
                ]
            )
            ->toolbarActions(
                $isProtected ? [] : [
                    BulkActionGroup::make([
                        DetachBulkAction::make(),
                    ]),
                ]
            );
    }
}
