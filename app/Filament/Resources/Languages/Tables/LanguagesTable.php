<?php

namespace App\Filament\Resources\Languages\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class LanguagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // Language Name
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->weight(FontWeight::Bold)
                    ->sortable()
                    ->icon('heroicon-o-language'),

                // Native Name
                TextColumn::make('native_name')
                    ->label('Native Name')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-globe-alt'),

                // Language Code
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->badge()
                    ->color('info')
                    ->sortable(),

                // Order
                TextColumn::make('order')
                    ->label('Order')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                // Active Status Toggle
                ToggleColumn::make('is_active')
                    ->label('Active')
                    ->beforeStateUpdated(function ($record, $state) {
                        $record->update(['is_active' => $state]);
                    }),

                // Default Status
                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),

                // Timestamps
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->icon('heroicon-o-calendar'),

                TextColumn::make('updated_at')
                    ->label('Updated At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->icon('heroicon-o-clock'),

                TextColumn::make('deleted_at')
                    ->label('Deleted At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->icon('heroicon-o-trash'),
            ])
            ->filters([
                //
            ])
            ->defaultSort('order', 'asc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
