<?php

namespace App\Filament\Resources\InvoiceTemplates;

use App\Filament\Resources\InvoiceTemplates\Pages\CreateInvoiceTemplate;
use App\Filament\Resources\InvoiceTemplates\Pages\EditInvoiceTemplate;
use App\Filament\Resources\InvoiceTemplates\Pages\ListInvoiceTemplates;
use App\Filament\Resources\InvoiceTemplates\Pages\ViewInvoiceTemplate;
use App\Filament\Resources\InvoiceTemplates\Schemas\InvoiceTemplateForm;
use App\Filament\Resources\InvoiceTemplates\Schemas\InvoiceTemplateInfolist;
use App\Filament\Resources\InvoiceTemplates\Tables\InvoiceTemplatesTable;
use App\Models\InvoiceTemplate;
use App\Traits\NavigationDefaultAccess;
use App\Traits\ResourceTranslation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InvoiceTemplateResource extends Resource
{
    use NavigationDefaultAccess, ResourceTranslation;
    protected static ?string $model = InvoiceTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = 'billing';

    protected static ?int $navigationSort = 40;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return InvoiceTemplateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InvoiceTemplateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InvoiceTemplatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoiceTemplates::route('/'),
            'create' => CreateInvoiceTemplate::route('/create'),
            'view' => ViewInvoiceTemplate::route('/{record}'),
            'edit' => EditInvoiceTemplate::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
