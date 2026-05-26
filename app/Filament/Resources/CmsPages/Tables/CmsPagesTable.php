<?php

namespace App\Filament\Resources\CmsPages\Tables;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CmsPagesTable
{
    public static function make(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('cms.resource.col_name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->copyable()
                    ->copyMessage(__('cms.resource.slug_copied'))
                    ->fontFamily('mono'),

                IconColumn::make('is_active')
                    ->label(__('cms.resource.col_is_active'))
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label(__('cms.resource.col_updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                Action::make('preview')
                    ->label(__('cms.resource.action_preview'))
                    ->icon('heroicon-o-device-phone-mobile')
                    ->color('warning')
                    ->url(fn ($record) => route('admin.cms-preview', ['page' => $record->id]))
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
