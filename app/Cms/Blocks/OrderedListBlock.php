<?php

namespace App\Cms\Blocks;

use Filament\Forms\Components\Builder\Block;

class OrderedListBlock extends AbstractListBlock
{
    public static function type(): string
    {
        return 'ordered_list';
    }

    public static function filamentBlock(): Block
    {
        return Block::make(static::type())
            ->label(__('cms.blocks.ordered_list'))
            ->icon('heroicon-o-queue-list')
            ->schema(static::commonSchema())
            ->columns(1);
    }
}
