<?php

namespace App\Cms\Blocks;

use App\Models\Language;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class WarningBoxBlock extends AbstractCmsBlock
{
    public static function type(): string
    {
        return 'warning_box';
    }

    public static function filamentBlock(): Block
    {
        return Block::make(static::type())
            ->label(__('cms.blocks.warning_box'))
            ->icon('heroicon-o-exclamation-triangle')
            ->schema([
                Toggle::make('is_active')
                    ->label(__('cms.fields.is_active'))
                    ->default(true)
                    ->inline(),

                // Tabs: title + body per language
                static::langTabs(fn (Language $lang) => [
                    TextInput::make("translations.{$lang->code}.title")
                        ->label(__('cms.fields.title'))
                        ->required(),

                    Textarea::make("translations.{$lang->code}.text")
                        ->label(__('cms.fields.text'))
                        ->rows(3)
                        ->required(),
                ]),

                // Visual options — collapsed
                static::propsSection([
                    Select::make('props.background_color')
                        ->label(__('cms.fields.background_color'))
                        ->options(config('cms.colors'))
                        ->default('warning'),

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
