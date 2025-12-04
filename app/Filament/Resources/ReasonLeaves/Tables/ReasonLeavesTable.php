<?php

namespace App\Filament\Resources\ReasonLeaves\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReasonLeavesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('resources.reason_leave.name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-m-document-text')
                    ->color('primary')
                    ->description(fn ($record) => $record->description ? \Illuminate\Support\Str::limit($record->description, 50) : null),

                TextColumn::make('description')
                    ->label(__('resources.reason_leave.description'))
                    ->searchable()
                    ->limit(100)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 100) {
                            return null;
                        }
                        return $state;
                    })
                    ->icon('heroicon-m-information-circle')
                    ->color('gray')
                    ->wrap(),

                TextColumn::make('translations_count')
                    ->label(__('resources.reason_leave.translations'))
                    ->counts('translations')
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-m-language')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('timeOffs_count')
                    ->label(__('resources.reason_leave.usage_count'))
                    ->counts('timeOffs')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray')
                    ->icon('heroicon-m-calendar-days')
                    ->sortable()
                    ->alignCenter()
                    ->tooltip(__('resources.reason_leave.times_used')),

                TextColumn::make('created_at')
                    ->label(__('resources.reason_leave.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->icon('heroicon-m-clock')
                    ->since()
                    ->color('gray'),

                TextColumn::make('updated_at')
                    ->label(__('resources.reason_leave.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->icon('heroicon-m-clock')
                    ->since()
                    ->color('gray'),
            ])
            ->filters([
                Filter::make('has_translations')
                    ->label(__('resources.reason_leave.has_translations'))
                    ->query(fn (Builder $query): Builder => $query->has('translations'))
                    ->toggle(),

                Filter::make('frequently_used')
                    ->label(__('resources.reason_leave.frequently_used'))
                    ->query(fn (Builder $query): Builder => $query->has('timeOffs', '>=', 5))
                    ->toggle(),

                Filter::make('unused')
                    ->label(__('resources.reason_leave.unused'))
                    ->query(fn (Builder $query): Builder => $query->doesntHave('timeOffs'))
                    ->toggle(),
            ])
            ->recordActions([
                                ViewAction::make(),

                EditAction::make()
                    ->label(__('resources.reason_leave.edit')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label(__('resources.reason_leave.delete_selected')),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->emptyStateHeading(__('resources.reason_leave.no_reasons'))
            ->emptyStateDescription(__('resources.reason_leave.no_reasons_desc'))
            ->emptyStateIcon('heroicon-o-document-text');
    }
}
