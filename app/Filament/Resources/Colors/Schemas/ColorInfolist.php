<?php

namespace App\Filament\Resources\Colors\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ColorInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('resources.color.section_info'))
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('name')
                            ->label(__('resources.color.name'))
                            ->weight(\Filament\Support\Enums\FontWeight::Bold),

                        TextEntry::make('brand')
                            ->label(__('resources.color.brand'))
                            ->default('—'),

                        TextEntry::make('hex_code')
                            ->label(__('resources.color.hex_code'))
                            ->state(function ($record) {
                                $hex = $record->hex_code;
                                return new HtmlString(
                                    "<span style='display:inline-flex;align-items:center;gap:6px;'>
                                        <span style='display:inline-block;width:18px;height:18px;border-radius:3px;background:{$hex};border:1px solid #e2e8f0;'></span>
                                        <span>{$hex}</span>
                                    </span>"
                                );
                            }),
                    ]),

                    Grid::make(3)->schema([
                        TextEntry::make('unit')
                            ->label(__('resources.color.unit'))
                            ->badge()
                            ->color('info'),

                        TextEntry::make('stock_quantity')
                            ->label(__('resources.color.stock_quantity'))
                            ->state(fn ($record) => $record->stock_quantity
                                ? number_format($record->stock_quantity, 2) . ' ' . $record->unit
                                : '—'),

                        IconEntry::make('is_active')
                            ->label(__('resources.color.is_active'))
                            ->boolean(),
                    ]),
                ]),
        ]);
    }
}
