<?php

namespace App\Cms\Blocks;

use App\Models\Language;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class TitleParagraphBlock extends AbstractCmsBlock
{
    public static function type(): string
    {
        return 'title_paragraph';
    }

    public static function filamentBlock(): Block
    {
        return Block::make(static::type())
            ->label(__('cms.blocks.title_paragraph'))
            ->icon('heroicon-o-document-text')
            ->schema([
                Toggle::make('is_active')
                    ->label(__('cms.fields.is_active'))
                    ->default(true)
                    ->inline(),

                // Tabs — one per language (two fields per language → Tabs are cleaner)
                static::langTabs(fn (Language $lang) => [
                    TextInput::make("translations.{$lang->code}.title")
                        ->label(__('cms.fields.title'))
                        ->required(),

                    Textarea::make("translations.{$lang->code}.text")
                        ->label(__('cms.fields.text'))
                        ->rows(4)
                        ->required(),
                ]),

                // Visual options — collapsed
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

        if (blank($content['title'] ?? null) && blank($content['text'] ?? null)) {
            return null;
        }

        return array_merge($this->baseResponse($block, $language), [
            'content' => [
                'title' => $content['title'] ?? '',
                'text'  => $content['text'] ?? '',
            ],
        ]);
    }
}
