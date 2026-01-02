<?php

namespace App\Filament\Resources\PageResource\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Support\Enums\FontWeight;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;

class PagesTable
{
    public static function make(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('page_key')
                    ->label(__('resources.page_resource.page_key'))
                    ->badge()
                    ->color('primary')
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('title')
                    ->label(__('resources.page_resource.title'))
                    ->getStateUsing(fn ($record) => $record->title)
                    ->searchable()
                    ->sortable()
                    ->limit(50),

                TextColumn::make('template')
                    ->label(__('resources.page_resource.template'))
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-o-code-bracket')
                    ->sortable(),

                IconColumn::make('is_published')
                    ->label(__('resources.page_resource.published'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),

                TextColumn::make('version')
                    ->label(__('resources.page_resource.version'))
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label(__('resources.page_resource.last_updated'))
                    ->dateTime('Y-m-d H:i')
                    ->icon('heroicon-o-clock')
                    ->sortable()
                    ->since()
                    ->description(fn ($record) => $record->updated_at->format('Y-m-d H:i')),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                TernaryFilter::make('is_published')
                    ->label(__('resources.page_resource.filter_published'))
                    ->placeholder(__('resources.page_resource.all_pages'))
                    ->trueLabel(__('resources.page_resource.published_only'))
                    ->falseLabel(__('resources.page_resource.unpublished_only')),

                SelectFilter::make('template')
                    ->label(__('resources.page_resource.filter_by_template'))
                    ->options(fn () => \App\Models\SamplePage::distinct()->pluck('template', 'template')->toArray()),
            ])
            ->actions([
                ViewAction::make()
                    ->label(__('resources.page_resource.preview'))
                    ->icon('heroicon-o-eye'),
                EditAction::make()
                    ->label(__('resources.page_resource.edit'))
                    ->icon('heroicon-o-pencil'),
            ])
            ->emptyStateHeading(__('resources.page_resource.no_pages_yet'))
            ->emptyStateDescription(__('resources.page_resource.no_pages_description'))
            ->emptyStateIcon('heroicon-o-document-text');
    }
}
