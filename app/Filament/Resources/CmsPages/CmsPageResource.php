<?php

namespace App\Filament\Resources\CmsPages;

use App\Filament\Resources\CmsPages\Pages\CreateCmsPage;
use App\Filament\Resources\CmsPages\Pages\EditCmsPage;
use App\Filament\Resources\CmsPages\Pages\ListCmsPages;
use App\Filament\Resources\CmsPages\Schemas\CmsPageForm;
use App\Filament\Resources\CmsPages\Tables\CmsPagesTable;
use App\Models\CmsPage;
use App\Traits\NavigationDefaultAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CmsPageResource extends Resource
{
    // use NavigationDefaultAccess;

    protected static ?string $model = CmsPage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 60;

    public static function getModelLabel(): string
    {
        return __('cms.resource.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('cms.resource.plural_model_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('cms.resource.navigation_label');
    }

    public static function form(Schema $schema): Schema
    {
        return CmsPageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CmsPagesTable::make($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListCmsPages::route('/'),
            'create' => CreateCmsPage::route('/create'),
            'edit'   => EditCmsPage::route('/{record}/edit'),
        ];
    }
}
