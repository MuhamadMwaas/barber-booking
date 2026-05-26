<?php

namespace App\Cms\Blocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Toggle;

class HtmlBlock extends AbstractCmsBlock
{
    /** Tags permitted in HTML blocks */
    private const ALLOWED_TAGS = '<p><br><strong><b><em><i><u><ul><ol><li><a><span><div>';

    public static function type(): string
    {
        return 'html';
    }

    public static function filamentBlock(): Block
    {
        return Block::make(static::type())
            ->label('HTML')
            ->icon('heroicon-o-code-bracket')
            ->schema([
                Toggle::make('is_active')
                    ->label(__('cms.fields.is_active'))
                    ->default(true)
                    ->inline(),

                // HTML textarea per language
                static::transTextareaGrid('html', rows: 7),
            ])
            ->columns(1);
    }

    public function transform(array $block, string $language, string $fallbackLanguage): ?array
    {
        $content = $this->translation($block, $language, $fallbackLanguage);
        $html    = $content['html'] ?? null;

        if (blank($html)) {
            return null;
        }

        return array_merge($this->baseResponse($block, $language), [
            'content' => ['html' => $this->sanitize($html)],
        ]);
    }

    protected function sanitize(string $html): string
    {
        // Basic tag-strip sanitiser — replace with HTMLPurifier for production
        return strip_tags($html, self::ALLOWED_TAGS);
    }
}
