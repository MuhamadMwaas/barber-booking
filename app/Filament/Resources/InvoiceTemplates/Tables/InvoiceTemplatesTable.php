<?php

namespace App\Filament\Resources\InvoiceTemplates\Tables;


use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Support\Enums\FontWeight;
use App\Models\InvoiceTemplate;

class InvoiceTemplatesTable
{
    public static function configure(Table $table): Table
    {
       return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                TextColumn::make('language')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'en' => 'success',
                        'de' => 'warning',
                        'ar' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'en' => 'EN',
                        'de' => 'DE',
                        'ar' => 'AR',
                        default => strtoupper($state),
                    }),

                TextColumn::make('paper_size')
                    ->badge()
                    ->color('info'),

                TextColumn::make('lines_count')
                    ->counts('lines')
                    ->label('Lines')
                    ->badge()
                    ->color('gray'),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('danger'),

                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->trueColor('warning')
                    ->falseColor('gray'),

                TextColumn::make('created_at')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
                TernaryFilter::make('is_default')->label('Default'),
                SelectFilter::make('language')->options([
                    'en' => 'English',
                    'de' => 'German',
                    'ar' => 'Arabic',
                ]),
            ])

            // v4: actions() -> recordActions()
            ->recordActions([
                Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn (InvoiceTemplate $record): string => route('invoice-template.preview', $record))
                    ->openUrlInNewTab(),

            Action::make('quick_print')
                ->label('Quick Print')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->url(fn (InvoiceTemplate $record): string => route('invoice-template.preview', $record))
                ->openUrlInNewTab(),

                Action::make('set_default')
                    ->label('Set Default')
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (InvoiceTemplate $record) => $record->setAsDefault())
                    ->visible(fn (InvoiceTemplate $record) => ! $record->is_default),

                Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(fn (InvoiceTemplate $record) => $record->duplicate()),

                EditAction::make(),
                DeleteAction::make(),
            ])

            // v4: bulkActions() -> toolbarActions()
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
