<?php

namespace App\Filament\Resources\PrinterSetting\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class PrinterSettingForm {
    public static function configure(Schema $schema): Schema {
        return $schema->components([
            Section::make('Basic Information')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('name')
                                ->label('Printer Name')
                                ->required()
                                ->maxLength(255)
                                ->helperText('Display name for the printer'),

                            TextInput::make('printer_name')
                                ->label('System Printer Name')
                                ->maxLength(255)
                                ->helperText('Actual printer name in Windows/System (e.g., "EPSON TM-T20III")'),
                        ]),

                    Textarea::make('description')
                        ->rows(2)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                ]),

            Section::make('Connection Settings')
                ->schema([
                    Grid::make(3)
                        ->schema([
                            Select::make('connection_type')
                                ->label('Connection Type')
                                ->options([
                                    'usb' => 'USB',
                                    'network' => 'Network/LAN',
                                ])
                                ->default('usb')
                                ->required()
                                ->live() // v4: prefer live() instead of reactive()
                                ->afterStateUpdated(function ($state, Set $set): void {
                                    if ($state === 'usb') {
                                        $set('ip_address', null);
                                        $set('port', null);
                                    } else {
                                        $set('device_path', null);
                                    }
                                }),

                            Select::make('paper_size')
                                ->label('Paper Size')
                                ->options([
                                    '80mm' => '80mm (Standard POS)',
                                    '58mm' => '58mm (Compact POS)',
                                ])
                                ->default('80mm')
                                ->required(),

                            TextInput::make('default_copies')
                                ->label('Default Copies')
                                ->numeric()
                                ->default(1)
                                ->minValue(1)
                                ->maxValue(10)
                                ->required(),
                        ]),

                    // Network Settings
                    Grid::make(2)
                        ->schema([
                            TextInput::make('ip_address')
                                ->label('IP Address')
                                ->helperText('Printer IP address (for Network printers)')
                                ->visible(fn(Get $get): bool => $get('connection_type') === 'network')
                                ->ip()
                                ->required(fn(Get $get): bool => $get('connection_type') === 'network'),

                            TextInput::make('port')
                                ->label('Port')
                                ->helperText('Usually 9100 for network printers')
                                ->visible(fn(Get $get): bool => $get('connection_type') === 'network')
                                ->numeric()
                                ->default(9100)
                                ->minValue(1)
                                ->maxValue(65535),
                        ]),

                    // USB Settings
                    TextInput::make('device_path')
                        ->label('Device Path')
                        ->helperText('USB device path (e.g., /dev/usb/lp0) - Optional')
                        ->visible(fn(Get $get): bool => $get('connection_type') === 'usb')
                        ->maxLength(255),
                ]),

            Section::make('Print Settings')
                ->schema([
                    Select::make('print_method')
                        ->label('Print Method')
                        ->options([
                            'browser' => 'Browser Print (Recommended)',
                            'escpos'  => 'ESC/POS (Advanced)',
                            'raw'     => 'Raw Print (Expert)',
                        ])
                        ->default('browser')
                        ->required()
                        ->helperText('Browser Print works with any printer connected to the client computer'),
                ]),

            Section::make('Status')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            Toggle::make('is_active')
                                ->label('Active')
                                ->default(true)
                                ->inline(false)
                                ->helperText('Enable/disable this printer'),

                            Toggle::make('is_default')
                                ->label('Set as Default Printer')
                                ->inline(false)
                                ->helperText('Use this printer by default for all prints'),
                        ]),
                ]),

            Section::make('Test Results')
                ->schema([
                    Placeholder::make('last_test_at')
                        ->label('Last Test')
                        ->content(fn($record) => $record?->last_test_at
                            ? $record->last_test_at->diffForHumans()
                            : 'Never tested'),

                    Placeholder::make('last_test_status')
                        ->label('Test Status')
                        ->content(fn($record) => $record?->last_test_status
                            ? ucfirst($record->last_test_status)
                            : 'N/A'),

                    Placeholder::make('last_test_message')
                        ->label('Test Message')
                        ->content(fn($record) => $record?->last_test_message ?? 'N/A'),
                ])
                ->visible(fn($record): bool => $record !== null)
                ->collapsible()
                ->collapsed(),
        ]);
    }
}
