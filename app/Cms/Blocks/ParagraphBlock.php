<?php

namespace App\Cms\Blocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;

class ParagraphBlock extends AbstractCmsBlock
{
    public static function type(): string
    {
        return 'paragraph';
    }

    public static function filamentBlock(): Block
    {
        return Block::make(static::type())
            ->label(__('cms.blocks.paragraph'))
            ->icon('heroicon-o-bars-3-bottom-left')
            ->schema([
                Toggle::make('is_active')
                    ->label(__('cms.fields.is_active'))
                    ->default(true)
                    ->inline(),

                // Dynamic language textarea inputs
                static::transTextareaGrid('text', rows: 5),

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
