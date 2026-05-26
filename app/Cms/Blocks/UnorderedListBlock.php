<?php

namespace App\Cms\Blocks;

use Filament\Forms\Components\Builder\Block;

class UnorderedListBlock extends AbstractListBlock
{
    public static function type(): string
    {
        return 'unordered_list';
    }

    public static function filamentBlock(): Block
    {
        return Block::make(static::type())
            ->label(__('cms.blocks.unordered_list'))
            ->icon('heroicon-o-list-bullet')
            ->schema(static::commonSchema())
            ->columns(1);
    }
}
