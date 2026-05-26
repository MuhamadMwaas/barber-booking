<?php

namespace App\Cms\Blocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Facades\Storage;

class ImageBlock extends AbstractCmsBlock
{
    public static function type(): string
    {
        return 'image';
    }

    public static function filamentBlock(): Block
    {
        return Block::make(static::type())
            ->label(__('cms.blocks.image'))
            ->icon('heroicon-o-photo')
            ->schema([
                Toggle::make('is_active')
                    ->label(__('cms.fields.is_active'))
                    ->default(true)
                    ->inline(),

                FileUpload::make('image')
                    ->label(__('cms.fields.image'))
                    ->image()
                    ->directory('cms/pages')
                    ->maxSize(2048)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->required()
                    ->columnSpanFull(),

                // Alt text per language — single short field → Grid
                static::transTextGrid('alt'),

                // Visual options — collapsed
                static::propsSection([
                    Select::make('props.alignment')
                        ->label(__('cms.fields.alignment'))
                        ->options(__('cms.alignment'))
                        ->default('auto'),
                ]),
            ])
            ->columns(1);
    }

    public function transform(array $block, string $language, string $fallbackLanguage): ?array
    {
        $content = $this->translation($block, $language, $fallbackLanguage);
        $image   = $block['image'] ?? null;

        if (blank($image)) {
            return null;
        }

        return array_merge($this->baseResponse($block, $language), [
            'content' => [
                'url' => Storage::url($image),
                'alt' => $content['alt'] ?? '',
            ],
        ]);
    }
}
