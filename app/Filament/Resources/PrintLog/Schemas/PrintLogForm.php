<?php

namespace App\Filament\Resources\PrintLog\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PrintLogForm {
    public static function configure(Schema $schema): Schema {
        return $schema->components([
            Section::make('Print Information')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('invoice.invoice_number')
                                ->label('Invoice Number')
                                ->disabled(),

                            TextInput::make('printer.name')
                                ->label('Printer')
                                ->disabled(),
                        ]),

                    Grid::make(3)
                        ->schema([
                            TextInput::make('print_number')
                                ->label('Print Number')
                                ->disabled(),

                            TextInput::make('copies')
                                ->label('Copies')
                                ->disabled(),

                            TextInput::make('print_type')
                                ->label('Print Type')
                                ->disabled(),
                        ]),
                ]),

            Section::make('Status & Timing')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('status')
                                ->label('Status')
                                ->disabled(),

                            TextInput::make('duration_ms')
                                ->label('Duration (ms)')
                                ->disabled(),
                        ]),

                    Grid::make(2)
                        ->schema([
                            DateTimePicker::make('started_at')
                                ->label('Started At')
                                ->disabled(),

                            DateTimePicker::make('completed_at')
                                ->label('Completed At')
                                ->disabled(),
                        ]),

                    Textarea::make('error_message')
                        ->label('Error Message')
                        ->rows(3)
                        ->disabled()
                        ->visible(fn($record) => $record?->error_message !== null),
                ]),

            Section::make('Additional Information')
                ->schema([
                    KeyValue::make('print_data')
                        ->label('Print Data')
                        ->disabled(),
                ])
                ->collapsible()
                ->collapsed(),
        ]);
    }
}
