<?php

namespace App\Filament\Resources\Colors\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ColorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('resources.color.section_info'))
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('name')
                            ->label(__('resources.color.name'))
                            ->required()
                            ->maxLength(100),

                        TextInput::make('brand')
                            ->label(__('resources.color.brand'))
                            ->nullable()
                            ->maxLength(100),
                    ]),

                    Grid::make(2)->schema([
                        ColorPicker::make('hex_code')
                            ->label(__('resources.color.hex_code'))
                            ->required()
                            ->default('#000000'),

                        Select::make('unit')
                            ->label(__('resources.color.unit'))
                            ->required()
                            ->options([
                                'ml'    => 'ml (Milliliter)',
                                'g'     => 'g (Gram)',
                                'piece' => __('resources.color.unit_piece'),
                                'oz'    => 'oz (Ounce)',
                            ])
                            ->default('ml'),
                    ]),

                    TextInput::make('stock_quantity')
                        ->label(__('resources.color.stock_quantity'))
                        ->numeric()
                        ->nullable()
                        ->minValue(0)
                        ->step(0.01)
                        ->suffix(fn ($get) => $get('unit') ?? 'ml')
                        ->helperText(__('resources.color.stock_quantity_hint')),

                    Toggle::make('is_active')
                        ->label(__('resources.color.is_active'))
                        ->default(true)
                        ->inline(false),
                ]),
        ]);
    }
}
