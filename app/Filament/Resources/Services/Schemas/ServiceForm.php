<?php

namespace App\Filament\Resources\Services\Schemas;

use App\Models\Language;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Services\TaxCalculatorService;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Support\HtmlString;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Tabs::make('Service Details')
                    ->columnSpanFull()
                    ->tabs([
                        // Basic Information Tab
                        Tabs\Tab::make(__('resources.service.basic_info'))
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Section::make(__('resources.service.service_details'))
                                    ->description(__('resources.service.service_details_desc'))
                                    ->icon('heroicon-o-scissors')
                                    ->columns(2)
                                    ->schema([
                                        // Service Category
                                        Select::make('category_id')
                                            ->label(__('resources.service.category'))
                                            ->relationship('category', 'name')
                                            ->options(ServiceCategory::active()->pluck('name', 'id'))
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->createOptionForm([
                                                TextInput::make('name')
                                                    ->label(__('resources.service.category_name'))
                                                    ->required()
                                                    ->maxLength(255),
                                                Textarea::make('description')
                                                    ->label(__('resources.service.category_description'))
                                                    ->rows(3),
                                                Toggle::make('is_active')
                                                    ->label(__('resources.service.active'))
                                                    ->default(true),
                                            ])
                                            ->native(false),

                                        // Service Name
                                        TextInput::make('name')
                                            ->label(__('resources.service.name'))
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder(__('resources.service.name_placeholder'))
                                            ->helperText(__('resources.service.name_helper'))
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, callable $set) {
                                                // Auto-generate sort order based on name
                                                if (empty($set)) {
                                                    $set('sort_order', \App\Models\Service::max('sort_order') + 1 ?? 1);
                                                }
                                            }),

                                        // Service Description
                                        Textarea::make('description')
                                            ->label(__('resources.service.description'))
                                            ->required()
                                            ->rows(4)
                                            ->columnSpanFull()
                                            ->placeholder(__('resources.service.description_placeholder'))
                                            ->helperText(__('resources.service.description_helper')),
                                    ]),

                                Section::make(__('resources.service.pricing_duration'))
                                    ->description(__('resources.service.pricing_duration_desc'))
                                    ->icon('heroicon-o-banknotes')
                                    ->columns(3)
                                    ->schema([
                                        // Price
                                        TextInput::make('price')
                                            ->label(__('resources.service.price'))
                                            ->required()
                                            ->numeric()
                                            ->prefix('EUR')
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->placeholder('100.00')
                                            ->helperText(__('resources.service.price_helper'))
                                            ->live(onBlur: true),

                                        // Discount Price
                                        TextInput::make('discount_price')
                                            ->label(__('resources.service.discount'))
                                            ->numeric()
                                            ->prefix('EUR')
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->placeholder('80.00')
                                            ->helperText(__('resources.service.discount_helper'))
                                            ->lte('price')
                                            ->live(onBlur: true),

                                        // Duration
                                        TextInput::make('duration_minutes')
                                            ->label(__('resources.service.duration'))
                                            ->required()
                                            ->numeric()
                                            ->suffix(__('resources.service.minutes'))
                                            ->minValue(5)
                                            ->step(5)
                                            ->placeholder('30')
                                            ->helperText(__('resources.service.duration_helper')),

                                        // Tax breakdown for the base price (shown on blur)
                                        self::taxBreakdownPlaceholder('price'),

                                        // Tax breakdown for the discount price (shown on blur)
                                        self::taxBreakdownPlaceholder('discount_price'),
                                    ]),

                                Section::make(__('resources.service.visual_branding'))
                                    ->description(__('resources.service.visual_branding_desc'))
                                    ->icon('heroicon-o-paint-brush')
                                    ->columns(2)
                                    ->schema([
                                        // Service Image
                                        FileUpload::make('image_url')
                                            ->label(__('resources.service.image'))
                                            ->image()
                                            ->imageEditor()
                                            ->imageEditorAspectRatios([
                                                '16:9',
                                                '1:1',
                                                '4:3',
                                            ])
                                            ->afterStateHydrated(function (FileUpload $component, $state, $record) {

                                                if ($record && $record->image && $record->image->path ) {
                                                    $component->state([$record->image->path]);
                                                }
                                            })
                                            ->maxSize(2048)
                                            ->directory('temp/services/uploads')
                                            ->visibility('public')
                                            ->disk('public')
                                            ->helperText(__('resources.service.image_helper'))
                                            ->saveRelationshipsUsing(null)
                                            ->dehydrated(false)
                                            ->columnSpanFull(),

                                        // Color Code
                                        ColorPicker::make('color_code')
                                            ->label(__('resources.service.color'))
                                            ->required()
                                            ->helperText(__('resources.service.color_helper'))
                                            ->default('#3B82F6'),

                                        // Icon Image Upload
                                        FileUpload::make('icon_url')
                                            ->label(__('resources.service.icon'))
                                            ->image()
                                            ->imageEditor()
                                            ->imageEditorAspectRatios([
                                                '1:1',
                                            ])
                                            ->maxSize(1024)
                                            ->directory('temp/services/uploads')
                                            ->visibility('public')
                                            ->disk('public')
                                            ->afterStateHydrated(function (FileUpload $component, $state, $record) {

                                                if ($record && $record->icon && $record->icon->path ) {
                                                    $component->state([$record->icon->path]);
                                                }
                                            })
                                            ->helperText(__('resources.service.icon_helper'))
                                            ->acceptedFileTypes(['image/png', 'image/svg+xml', 'image/jpeg', 'image/jpg'])
                                            ->saveRelationshipsUsing(null)
                                            ->dehydrated(false)
                                            ->previewable(true),
                                    ]),
                            ]),

                        // Service Providers Tab
                        Tabs\Tab::make(__('resources.service.providers'))
                            ->icon('heroicon-o-user-group')
                            ->badge(fn ($record) => $record?->providers()->count() ?? 0)
                            ->schema([
                                Section::make(__('resources.service.assign_providers'))
                                    ->description(__('resources.service.assign_providers_desc'))
                                    ->icon('heroicon-o-user-plus')
                                    ->schema([
                                        Repeater::make('providers')
                                            ->schema([
                                                Grid::make(4)
                                                    ->schema([
                                                        Select::make('provider_id')
                                                            ->label(__('resources.service.provider'))
                                                            ->options(
                                                                User::role('provider')
                                                                    ->where('is_active', true)
                                                                    ->get()
                                                                    ->pluck('full_name', 'id')
                                                            )
                                                            ->required()
                                                            ->searchable()
                                                            ->preload()
                                                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                                            ->native(false)
                                                            ->columnSpan(2),

                                                        Toggle::make('is_active')
                                                            ->label(__('resources.service.active'))
                                                            ->default(true)
                                                            ->inline(false)
                                                            ->columnSpan(1),

                                                        // Empty column for spacing
                                                        Grid::make(1)->schema([])->columnSpan(1),
                                                    ]),

                                                Grid::make(3)
                                                    ->schema([
                                                        TextInput::make('custom_price')
                                                            ->label(__('resources.service.custom_price'))
                                                            ->numeric()
                                                            ->prefix('EUR')
                                                            ->minValue(0)
                                                            ->step(0.01)
                                                            ->placeholder(__('resources.service.use_default_price'))
                                                            ->helperText(__('resources.service.custom_price_helper'))
                                                            ->live(onBlur: true),

                                                        // Tax breakdown for the custom price (shown on blur)
                                                        self::taxBreakdownPlaceholder('custom_price'),

                                                        TextInput::make('custom_duration')
                                                            ->label(__('resources.service.custom_duration'))
                                                            ->numeric()
                                                            ->suffix(__('resources.service.minutes'))
                                                            ->minValue(5)
                                                            ->step(1)
                                                            ->placeholder(__('resources.service.use_default_duration'))
                                                            ->helperText(__('resources.service.custom_duration_helper')),

                                                        TextInput::make('notes')
                                                            ->label(__('resources.service.notes'))
                                                            ->maxLength(500)
                                                            ->placeholder(__('resources.service.provider_notes_placeholder')),
                                                    ]),
                                            ])
                                            ->columns(1)
                                            ->defaultItems(0)
                                            ->addActionLabel(__('resources.service.add_provider'))
                                            ->reorderable(false)
                                            ->collapsible()
                                            ->itemLabel(fn (array $state): ?string =>
                                                User::find($state['provider_id'])?->full_name ?? __('resources.service.new_provider')
                                            )
                                            ->collapsed(false),
                                    ]),
                            ]),

                        // Translations Tab
                        Tabs\Tab::make(__('resources.service.translations'))
                            ->icon('heroicon-o-language')
                            ->badge(fn () => Language::where('is_active', true)->count())
                            ->schema(function () {
                                $languages = Language::where('is_active', true)->get();
                                $sections = [];

                                foreach ($languages as $language) {
                                    $sections[] = Section::make($language->native_name . ' (' . $language->name . ')')
                                        ->description(__('resources.service.translation_for_language', ['language' => $language->native_name]))
                                        ->icon('heroicon-o-globe-alt')
                                        ->columns(1)
                                        ->schema([
                                            Grid::make(1)
                                                ->schema([
                                                    TextInput::make("translations.{$language->id}.language_id")
                                                        ->label(__('resources.service.language_id'))
                                                        ->default($language->id)
                                                        ->hidden()
                                                        ->dehydrated(),

                                                    TextInput::make("translations.{$language->id}.language_code")
                                                        ->label(__('resources.service.language_code'))
                                                        ->default($language->code)
                                                        ->disabled()
                                                        ->dehydrated()
                                                        ->helperText(__('resources.service.language_code_helper')),

                                                    TextInput::make("translations.{$language->id}.name")
                                                        ->label(__('resources.service.translated_name'))
                                                        ->required()
                                                        ->maxLength(255)
                                                        ->placeholder(__('resources.service.translated_name_placeholder'))
                                                        ->helperText(__('resources.service.translated_name_helper')),

                                                    Textarea::make("translations.{$language->id}.description")
                                                        ->label(__('resources.service.translated_description'))
                                                        ->required()
                                                        ->rows(4)
                                                        ->placeholder(__('resources.service.translated_description_placeholder'))
                                                        ->helperText(__('resources.service.translated_description_helper')),
                                                ]),
                                        ]);
                                }

                                return $sections;
                            }),

                        // Settings Tab
                        Tabs\Tab::make(__('resources.service.settings'))
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Section::make(__('resources.service.visibility_settings'))
                                    ->description(__('resources.service.visibility_settings_desc'))
                                    ->icon('heroicon-o-eye')
                                    ->columns(3)
                                    ->schema([
                                        Toggle::make('is_active')
                                            ->label(__('resources.service.active'))
                                            ->helperText(__('resources.service.active_helper'))
                                            ->default(true)
                                            ->inline(false),

                                        Toggle::make('is_featured')
                                            ->label(__('resources.service.featured'))
                                            ->helperText(__('resources.service.featured_helper'))
                                            ->default(false)
                                            ->inline(false),

                                        TextInput::make('sort_order')
                                            ->label(__('resources.service.sort_order'))
                                            ->required()
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(fn () => \App\Models\Service::max('sort_order') + 1 ?? 1)
                                            ->helperText(__('resources.service.sort_order_helper')),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    /**
     * Reactive box shown beneath a price field that splits the entered
     * gross amount (incl. tax) into net + tax using the salon tax rate.
     * Hidden until a positive numeric value is present; updates on blur.
     */
    protected static function taxBreakdownPlaceholder(string $field): Placeholder
    {
        return Placeholder::make($field . '_tax_breakdown')
            ->hiddenLabel()
            ->columnSpanFull()
            ->visible(fn ($get) => is_numeric($get($field)) && (float) $get($field) > 0)
            ->content(fn ($get) => self::renderTaxBreakdown($get($field)));
    }

    /**
     * Build the HTML breakdown for a gross amount.
     */
    protected static function renderTaxBreakdown($grossAmount): HtmlString
    {
        $taxRate = (float) get_setting('tax_rate', 19);

        try {
            /** @var TaxCalculatorService $calculator */
            $calculator = app(TaxCalculatorService::class);
            $result = $calculator->extractTax($grossAmount, $taxRate);
        } catch (\Throwable $e) {
            return new HtmlString('');
        }

        $net = number_format((float) $result['net'], 2);
        $tax = number_format((float) $result['tax'], 2);
        $gross = number_format((float) $result['gross'], 2);
        $rate = rtrim(rtrim(number_format($taxRate, 2), '0'), '.');

        $title = e(__('resources.service.tax_breakdown_title'));
        $netLabel = e(__('resources.service.tax_net'));
        $taxLabel = e(__('resources.service.tax_amount_label'));
        $finalLabel = e(__('resources.service.tax_final'));

        // Inline styles are used so the colours render regardless of which
        // utility classes the compiled Filament theme ships.
        $row = function (string $dot, string $valueColor, string $label, string $value, bool $strong = false): string {
            $labelWeight = $strong ? '600' : '500';
            $valueSize = $strong ? '0.9375rem' : '0.875rem';
            return <<<ROW
            <div style="display:flex;align-items:center;justify-content:space-between;">
                <span style="display:inline-flex;align-items:center;gap:0.5rem;">
                    <span style="width:0.5rem;height:0.5rem;border-radius:9999px;background:{$dot};flex:none;"></span>
                    <span style="color:#6b7280;font-size:0.8125rem;font-weight:{$labelWeight};">{$label}</span>
                </span>
                <span style="font-weight:700;font-size:{$valueSize};color:{$valueColor};white-space:nowrap;">{$value}&nbsp;&euro;</span>
            </div>
            ROW;
        };

        $netRow = $row('#10b981', '#059669', $netLabel, $net);
        $taxRow = $row('#f59e0b', '#d97706', "{$taxLabel} ({$rate}%)", $tax);
        $finalRow = $row('#6366f1', '#4f46e5', $finalLabel, $gross, true);

        return new HtmlString(<<<HTML
        <div style="border-radius:0.75rem;overflow:hidden;border:1px solid rgba(99,102,241,0.22);box-shadow:0 1px 3px rgba(0,0,0,0.06);max-width:24rem;">
            <div style="display:flex;align-items:center;gap:0.5rem;padding:0.5rem 0.75rem;background:linear-gradient(90deg,rgba(99,102,241,0.16),rgba(99,102,241,0.04));border-bottom:1px solid rgba(99,102,241,0.18);">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#4f46e5" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:1rem;height:1rem;flex:none;">
                    <rect x="4" y="2" width="16" height="20" rx="2"></rect>
                    <line x1="8" y1="6" x2="16" y2="6"></line>
                    <line x1="8" y1="10" x2="8" y2="10"></line>
                    <line x1="12" y1="10" x2="12" y2="10"></line>
                    <line x1="16" y1="10" x2="16" y2="10"></line>
                    <line x1="8" y1="14" x2="8" y2="14"></line>
                    <line x1="12" y1="14" x2="12" y2="14"></line>
                    <line x1="16" y1="14" x2="16" y2="18"></line>
                    <line x1="8" y1="18" x2="12" y2="18"></line>
                </svg>
                <span style="font-weight:600;font-size:0.8125rem;color:#4f46e5;">{$title}</span>
                <span style="margin-inline-start:auto;font-size:0.6875rem;font-weight:700;padding:0.125rem 0.5rem;border-radius:9999px;background:rgba(99,102,241,0.16);color:#4f46e5;">{$rate}%</span>
            </div>
            <div style="display:flex;flex-direction:column;gap:0.5rem;padding:0.625rem 0.75rem;">
                {$netRow}
                {$taxRow}
                <div style="height:1px;background:rgba(99,102,241,0.15);margin:0.125rem 0;"></div>
                {$finalRow}
            </div>
        </div>
        HTML);
    }
}
