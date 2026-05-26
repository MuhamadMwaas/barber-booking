<?php

namespace App\Services\Cms;

use App\Cms\Blocks\CmsBlockContract;
use App\Cms\Blocks\DividerBlock;
use App\Cms\Blocks\HeadingBlock;
use App\Cms\Blocks\HtmlBlock;
use App\Cms\Blocks\ImageBlock;
use App\Cms\Blocks\LinkBlock;
use App\Cms\Blocks\OrderedListBlock;
use App\Cms\Blocks\ParagraphBlock;
use App\Cms\Blocks\TitleParagraphBlock;
use App\Cms\Blocks\UnorderedListBlock;
use App\Cms\Blocks\WarningBoxBlock;

class CmsBlockRegistry
{
    /**
     * All registered block class-strings.
     *
     * @return array<class-string<CmsBlockContract>>
     */
    public function classes(): array
    {
        return [
            HeadingBlock::class,
            ParagraphBlock::class,
            TitleParagraphBlock::class,
            OrderedListBlock::class,
            UnorderedListBlock::class,
            DividerBlock::class,
            LinkBlock::class,
            ImageBlock::class,
            WarningBoxBlock::class,
            HtmlBlock::class,
        ];
    }

    /**
     * Returns Filament Block instances for use inside a Builder component.
     */
    public function filamentBlocks(): array
    {
        return collect($this->classes())
            ->map(fn (string $class) => $class::filamentBlock())
            ->all();
    }

    /**
     * Returns an instantiated transformer for the given block type, or null
     * when the type is not registered.
     */
    public function transformerFor(string $type): ?CmsBlockContract
    {
        foreach ($this->classes() as $class) {
            if ($class::type() === $type) {
                return app($class);
            }
        }

        return null;
    }
}
