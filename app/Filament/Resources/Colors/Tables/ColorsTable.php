<?php

namespace App\Filament\Resources\Colors\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class ColorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // Color swatch + name
                TextColumn::make('name')
                    ->label(__('resources.color.name'))
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($state, $record) {
                        $hex = e($record->hex_code);
                        $name = e($state);
                        return new HtmlString(
                            "<span style='display:inline-flex;align-items:center;gap:8px;'>
                                <span style='width:18px;height:18px;border-radius:4px;background:{$hex};
                                             border:1px solid #e2e8f0;display:inline-block;flex-shrink:0;'></span>
                                <span>{$name}</span>
                            </span>"
                        );
                    }),

                TextColumn::make('brand')
                    ->label(__('resources.color.brand'))
                    ->default('—')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('hex_code')
                    ->label(__('resources.color.hex_code'))
                    ->badge()
                    ->color('gray'),

                TextColumn::make('unit')
                    ->label(__('resources.color.unit'))
                    ->badge()
                    ->color('info'),

                TextColumn::make('stock_quantity')
                    ->label(__('resources.color.stock_quantity'))
                    ->formatStateUsing(fn ($state, $record) => $state
                        ? number_format($state, 2) . ' ' . $record->unit
                        : '—')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('resources.color.is_active'))
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('resources.color.created_at'))
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('resources.color.is_active'))
                    ->placeholder(__('All'))
                    ->trueLabel(__('Active'))
                    ->falseLabel(__('Inactive')),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make()->slideOver(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }
}
