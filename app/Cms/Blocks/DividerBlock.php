<?php

namespace App\Cms\Blocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;

class DividerBlock extends AbstractCmsBlock
{
    public static function type(): string
    {
        return 'divider';
    }

    public static function filamentBlock(): Block
    {
        // Divider has no translations — only props, so all options are shown directly.
        return Block::make(static::type())
            ->label(__('cms.blocks.divider'))
            ->icon('heroicon-o-minus')
            ->schema([
                Toggle::make('is_active')
                    ->label(__('cms.fields.is_active'))
                    ->default(true)
                    ->inline(),

                Grid::make(3)->schema([
                    Select::make('props.orientation')
                        ->label(__('cms.fields.orientation'))
                        ->options(__('cms.orientation'))
                        ->default('horizontal')
                        ->required(),

                    Select::make('props.size')
                        ->label(__('cms.fields.size'))
                        ->options(__('cms.size'))
                        ->default('sm')
                        ->required(),

                    Select::make('props.color')
                        ->label(__('cms.fields.color'))
                        ->options(config('cms.colors'))
                        ->default('default')
                        ->required(),
                ]),
            ])
            ->columns(1);
    }

    public function transform(array $block, string $language, string $fallbackLanguage): ?array
    {
        return array_merge($this->baseResponse($block, $language), [
            'content' => new \stdClass(),
        ]);
    }
}
