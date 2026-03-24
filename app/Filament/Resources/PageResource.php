<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PageResource\Pages;
use App\Filament\Resources\PageResource\Schemas\PageForm;
use App\Filament\Resources\PageResource\Tables\PagesTable;
use App\Models\SamplePage;
use App\Traits\NavigationDefaultAccess;
use App\Traits\ResourceTranslation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PageResource extends Resource
{
    use NavigationDefaultAccess, ResourceTranslation;
    protected static ?string $model = SamplePage::class;

    protected static ?string $translationResourceKey = 'page_resource';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string|\UnitEnum|null $navigationGroup = 'content';

    protected static ?int $navigationSort = 70;

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
