<?php

namespace App\Filament\Resources\InvoiceTemplates\Schemas;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextEntry;

class InvoiceTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255)
                                    ->columnSpan(1),

                                Forms\Components\Select::make('language')
                                    ->options([
                                        'en' => 'English',
                                        'de' => 'German',
                                        'ar' => 'Arabic',
                                    ])
                                    ->default('en')
                                    ->required()
                                    ->columnSpan(1),
                            ]),

                        Forms\Components\Textarea::make('description')
                            ->rows(2)
                            ->maxLength(1000),

                        Grid::make(3)
                            ->schema([
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true)
                                    ->inline(false),

                                Forms\Components\Toggle::make('is_default')
                                    ->label('Set as Default Template')
                                    ->inline(false)
                                    ->helperText('Only one template can be default'),

                                Forms\Components\Select::make('paper_size')
                                    ->options([
                                        '80mm' => '80mm (Standard POS)',
                                        '58mm' => '58mm (Compact POS)',
                                    ])
                                    ->default('80mm')
                                    ->required()
                                    ->live(),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Template Settings')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('paper_width')
                                    ->label('Paper Width (mm)')
                                    ->numeric()
                                    ->default(80)
                                    ->required()
                                    ->minValue(50)
                                    ->maxValue(100),

                                Forms\Components\Select::make('font_family')
                                    ->options([
                                        'Arial' => 'Arial',
                                        'Helvetica' => 'Helvetica',
                                        'Courier' => 'Courier',
                                        'Times New Roman' => 'Times New Roman',
                                    ])
                                    ->default('Arial')
                                    ->required(),

                                Forms\Components\TextInput::make('font_size')
                                    ->label('Base Font Size')
                                    ->numeric()
                                    ->default(10)
                                    ->required()
                                    ->minValue(8)
                                    ->maxValue(16),
                            ]),
                Grid::make(3)
                    ->schema([
                        Forms\Components\ColorPicker::make('global_styles.primary_color')
                            ->label('Primary Color')
                            ->default('#000000'),

                        Forms\Components\ColorPicker::make('global_styles.secondary_color')
                            ->label('Secondary Color')
                            ->default('#666666'),

                        Forms\Components\ColorPicker::make('global_styles.border_color')
                            ->label('Border Color')
                            ->default('#cccccc'),
                    ]),

                Grid::make(3)
                    ->schema([
                        Forms\Components\TextInput::make('global_styles.line_height')
                            ->label('Line Height')
                            ->numeric()
                            ->step(0.1)
                            ->default(1.2),

                        Forms\Components\TextInput::make('global_styles.padding')
                            ->label('Padding (px)')
                            ->numeric()
                            ->default(5),


                    ]),
                    ]),

                Section::make('Company Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('company_info.name')
                                    ->label('Company Name')
                                    ->default(config('app.name')),

                                Forms\Components\TextInput::make('company_info.phone')
                                    ->label('Phone')
                                    ->tel(),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Forms\Components\Textarea::make('company_info.address')
                                    ->label('Address')
                                    ->rows(2),

                                Forms\Components\TextInput::make('company_info.email')
                                    ->label('Email')
                                    ->email(),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('company_info.tax_number')
                                    ->label('Tax Number'),

                                Forms\Components\FileUpload::make('company_info.logo_path')
                                    ->label('Company Logo')
                                    ->image()
                                    ->directory('invoice-templates/logos')
                                    ->visibility('public')
                                    ->maxSize(1024),
                            ]),
                    ])
                    ->collapsible(),

                // Section::make('Global Styles')
                //     ->schema([
                //         Grid::make(3)
                //             ->schema([
                //                 Forms\Components\ColorPicker::make('global_styles.primary_color')
                //                     ->label('Primary Color')
                //                     ->default('#000000'),

                //                 Forms\Components\ColorPicker::make('global_styles.secondary_color')
                //                     ->label('Secondary Color')
                //                     ->default('#666666'),

                //                 Forms\Components\ColorPicker::make('global_styles.border_color')
                //                     ->label('Border Color')
                //                     ->default('#cccccc'),
                //             ]),

                //         Grid::make(3)
                //             ->schema([
                //                 Forms\Components\TextInput::make('global_styles.line_height')
                //                     ->label('Line Height')
                //                     ->numeric()
                //                     ->step(0.1)
                //                     ->default(1.2),

                //                 Forms\Components\TextInput::make('global_styles.padding')
                //                     ->label('Padding (px)')
                //                     ->numeric()
                //                     ->default(5),


                //             ]),
                //     ])
                //     ->collapsible()
                //     ->collapsed(),
            ]);
    }
}
