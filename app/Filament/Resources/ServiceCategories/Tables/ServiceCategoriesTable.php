<?php

namespace App\Filament\Resources\ServiceCategories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ServiceCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // Category Image
                ImageColumn::make('image.path')
                    ->label(__('resources.service_category.image'))
                    ->disk('public')
                    ->circular()
                    ->size(50)
                    ->defaultImageUrl(fn ($record) =>
                        'https://ui-avatars.com/api/?name=' . urlencode($record->name) .
                        '&color=fff&background=3B82F6'
                    )
                    ->url(fn ($record) => $record->image ? $record->image->urlFile() : null)
                    ->openUrlInNewTab()
                    ->toggleable(),

                // Category Name
                TextColumn::make('name')
                    ->label(__('resources.service_category.name'))
                    ->searchable()
                    ->weight(FontWeight::Bold)
                    ->sortable()
                    ->icon('heroicon-o-tag'),

                // Services Count
                TextColumn::make('services_count')
                    ->label(__('resources.service_category.services_count'))
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-o-squares-2x2')
                    ->sortable(),

                // Description
                TextColumn::make('description')
                    ->label(__('resources.service_category.description'))
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 50) {
                            return null;
                        }
                        return $state;
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                // Sort Order
                TextColumn::make('sort_order')
                    ->label(__('resources.service_category.sort_order'))
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                // Status Toggle
                ToggleColumn::make('is_active')
                    ->label(__('resources.service_category.status'))
                    ->beforeStateUpdated(function ($record, $state) {
                        $record->update(['is_active' => $state]);
                    }),

                // Timestamps
                TextColumn::make('created_at')
                    ->label(__('resources.service_category.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->icon('heroicon-o-calendar'),

                TextColumn::make('updated_at')
                    ->label(__('resources.service_category.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->icon('heroicon-o-clock'),
            ])
            ->filters([
                //
            ])
            ->defaultSort('sort_order', 'asc')
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
