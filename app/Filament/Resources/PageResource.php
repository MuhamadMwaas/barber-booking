<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PageResource\Pages;
use App\Filament\Resources\PageResource\Schemas\PageForm;
use App\Filament\Resources\PageResource\Tables\PagesTable;
use App\Models\SamplePage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PageResource extends Resource
{
    protected static ?string $model = SamplePage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static ?int $navigationSort = 50;

    public static function getNavigationLabel(): string
    {
        return __('resources.page_resource.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('resources.page_resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources.page_resource.plural_label');
    }



    public static function form(Schema $schema): Schema
    {
        return $schema->schema(PageForm::make());
    }

    public static function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        return PagesTable::make($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPages::route('/'),
            'edit' => Pages\EditPage::route('/{record}/edit'),
            'view' => Pages\ViewPage::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['translations']);
    }
}
