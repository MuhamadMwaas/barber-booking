<?php

namespace App\Cms\Blocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class LinkBlock extends AbstractCmsBlock
{
    public static function type(): string
    {
        return 'link';
    }

    public static function filamentBlock(): Block
    {
        return Block::make(static::type())
            ->label(__('cms.blocks.link'))
            ->icon('heroicon-o-link')
            ->schema([
                Toggle::make('is_active')
                    ->label(__('cms.fields.is_active'))
                    ->default(true)
                    ->inline(),

                // Dynamic label per language
                static::transTextGrid('label'),

                TextInput::make('url')
                    ->label(__('cms.fields.url'))
                    ->url()
                    ->required()
                    ->columnSpanFull(),

                // Visual options — collapsed
                static::propsSection([
                    Select::make('props.target')
                        ->label(__('cms.fields.target'))
                        ->options(__('cms.target'))
                        ->default('external'),
                ]),
            ])
            ->columns(1);
    }

    public function transform(array $block, string $language, string $fallbackLanguage): ?array
    {
        $content = $this->translation($block, $language, $fallbackLanguage);
        $url     = $block['url'] ?? null;

        if (blank($content['label'] ?? null) || blank($url)) {
            return null;
        }

        return array_merge($this->baseResponse($block, $language), [
            'content' => [
                'label' => $content['label'],
                'url'   => $url,
            ],
        ]);
    }
}
