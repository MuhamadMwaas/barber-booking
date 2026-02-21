<?php

namespace App\Filament\Resources\PrinterSetting\Tables;

use App\Filament\Resources\PrintLogResource;
use App\Models\PrinterSetting;
use App\Services\Print\PrintService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PrinterSettingsTable {
    public static function configure(Table $table): Table {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Printer Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('connection_type')
                    ->label('Connection')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'usb' => 'success',
                        'network' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => strtoupper($state)),

                TextColumn::make('paper_size')
                    ->label('Paper')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('print_method')
                    ->label('Method')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn(string $state): string => ucfirst($state)),

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

                TextColumn::make('printLogs_count')
                    ->counts('printLogs')
                    ->label('Total Prints')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('last_test_at')
                    ->label('Last Test')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('last_test_status')
                    ->label('Test Status')
                    ->icon(fn($state) => match ($state) {
                        'success' => 'heroicon-o-check-circle',
                        'failed' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->color(fn($state) => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
                TernaryFilter::make('is_default')->label('Default'),

                SelectFilter::make('connection_type')
                    ->label('Connection Type')
                    ->options([
                        'usb' => 'USB',
                        'network' => 'Network',
                    ]),

                SelectFilter::make('paper_size')
                    ->label('Paper Size')
                    ->options([
                        '80mm' => '80mm',
                        '58mm' => '58mm',
                    ]),
            ])
            ->recordActions([
                Action::make('test')
                    ->label('Test')
                    ->icon('heroicon-o-play')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Test Printer Connection')
                    ->modalDescription('This will test the printer connection and save the results.')
                    ->action(function (PrinterSetting $record): void {
                        $printService = app(PrintService::class);
                        $result = $printService->testPrinter($record);

                        if ($result['success']) {
                            \Filament\Notifications\Notification::make()
                                ->title('Printer Test Successful')
                                ->success()
                                ->body($result['message'])
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('Printer Test Failed')
                                ->danger()
                                ->body($result['message'])
                                ->send();
                        }
                    }),

                Action::make('set_default')
                    ->label('Set Default')
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn(PrinterSetting $record) => $record->setAsDefault())
                    ->visible(fn(PrinterSetting $record): bool => ! $record->is_default),

                Action::make('view_logs')
                    ->label('Logs')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    // ->url(
                    //     fn(PrinterSetting $record): string =>
                    //     PrintLogResource::getUrl('index', ['printer_id' => $record->id])
                    // )
                    ,

                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function ($records): void {
                            $defaultCount = PrinterSetting::query()
                                ->where('is_default', true)
                                ->whereNotIn('id', $records->pluck('id'))
                                ->count();

                            if ($defaultCount === 0 && $records->count() > 0) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Cannot delete all printers')
                                    ->danger()
                                    ->body('At least one printer must remain active.')
                                    ->send();

                                return;
                            }

                            $records->each->delete();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
