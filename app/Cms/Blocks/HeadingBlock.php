<?php

namespace App\Cms\Blocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;

class HeadingBlock extends AbstractCmsBlock
{
    public static function type(): string
    {
        return 'heading';
    }

    public static function filamentBlock(): Block
    {
        return Block::make(static::type())
            ->label(__('cms.blocks.heading'))
            ->icon('heroicon-o-hashtag')
            ->schema([
                Toggle::make('is_active')
                    ->label(__('cms.fields.is_active'))
                    ->default(true)
                    ->inline(),

                // Heading level — important, shown at the top
                Select::make('props.level')
                    ->label(__('cms.fields.heading_level'))
                    ->options(['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4'])
                    ->default('h2')
                    ->required(),

                // Dynamic language text inputs — one column per active language
                static::transTextGrid('text'),

                // Visual options — collapsed by default
                static::propsSection([
                    Select::make('props.alignment')
                        ->label(__('cms.fields.alignment'))
                        ->options(__('cms.alignment'))
                        ->default('auto'),

                    Select::make('props.color')
                        ->label(__('cms.fields.color'))
                        ->options(config('cms.colors'))
                        ->default('default'),
                ]),
            ])
            ->columns(1);
    }

    public function transform(array $block, string $language, string $fallbackLanguage): ?array
    {
        $content = $this->translation($block, $language, $fallbackLanguage);

        if (blank($content['text'] ?? null)) {
            return null;
        }

        return array_merge($this->baseResponse($block, $language), [
            'content' => ['text' => $content['text']],
        ]);
    }
}
