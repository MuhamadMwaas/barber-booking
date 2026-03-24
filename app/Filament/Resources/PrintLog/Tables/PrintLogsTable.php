<?php

namespace App\Filament\Resources\PrintLog\Tables;

use App\Models\PrintLog;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
class PrintLogsTable {
    public static function configure(Table $table): Table {
        return $table
            ->columns([
                TextColumn::make('invoice.invoice_number')
                    ->label('Invoice')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->url(
                        fn($record) => $record->invoice
                            ? route('invoice.print', $record->invoice)
                            : null
                    )
                    ->openUrlInNewTab(),

                TextColumn::make('printer.name')
                    ->label('Printer')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('user.first_name')
                    ->label('User')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('print_number')
                    ->label('Print #')
                    ->badge()
                    ->color(fn($state) => match (true) {
                        $state === 1 => 'success',
                        $state === 2 => 'warning',
                        $state >= 3 => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('print_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'original' => 'success',
                        'copy' => 'warning',
                        'reprint' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => ucfirst($state)),

                TextColumn::make('copies')
                    ->label('Copies')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        'printing' => 'warning',
                        'pending' => 'gray',
                        default => 'gray',
                    })
                    ->icon(fn(string $state): string => match ($state) {
                        'success' => 'heroicon-o-check-circle',
                        'failed' => 'heroicon-o-x-circle',
                        'printing' => 'heroicon-o-arrow-path',
                        'pending' => 'heroicon-o-clock',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->sortable(),

                TextColumn::make('duration_ms')
                    ->label('Duration')
                    ->formatStateUsing(
                        fn($state) => $state
                            ? round($state / 1000, 2) . 's'
                            : '-'
                    )
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Printed At')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('error_message')
                    ->label('Error')
                    ->limit(30)
                    ->tooltip(fn($record) => $record->error_message)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('danger'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'success' => 'Success',
                        'failed' => 'Failed',
                        'printing' => 'Printing',
                        'pending' => 'Pending',
                    ]),

                SelectFilter::make('print_type')
                    ->label('Type')
                    ->options([
                        'original' => 'Original',
                        'copy' => 'Copy',
                        'reprint' => 'Reprint',
                    ]),

                SelectFilter::make('printer_id')
                    ->label('Printer')
                    ->relationship('printer', 'name'),

                SelectFilter::make('user_id')
                    ->label('User')
                    ->relationship('user', 'first_name'),

                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')->label('From'),
                        DatePicker::make('created_until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'] ?? null,
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'] ?? null,
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Action::make('reprint')
                    ->label('Reprint')
                    ->icon('heroicon-o-printer')
                    ->color('warning')
                    ->url(fn(PrintLog $record): string => route('invoice.print', $record->invoice_id))
                    ->openUrlInNewTab()
                    ->visible(fn(PrintLog $record) => $record->invoice !== null),

                ViewAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
