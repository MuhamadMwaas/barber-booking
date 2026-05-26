<?php

namespace App\Cms\Blocks;

use Filament\Forms\Components\Builder\Block;

interface CmsBlockContract
{
    public static function type(): string;

    public static function filamentBlock(): Block;

    public function transform(array $block, string $language, string $fallbackLanguage): ?array;
}
