<?php

namespace App\Filament\Resources\InvoiceTemplates\Pages;

use App\Filament\Resources\InvoiceTemplates\InvoiceTemplateResource;
use App\Models\InvoiceTemplate;
use App\Models\TemplateLine;
use App\Services\InvoiceTemplate\DynamicFieldResolver;
use App\Services\InvoiceTemplate\LineTypeRegistry;
use Filament\Actions;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

class EditInvoiceTemplate extends EditRecord
{
    protected static string $resource = InvoiceTemplateResource::class;




    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('preview')
                ->label('Preview Template')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->url(fn (): string => route('invoice-template.preview', $this->record))
                ->openUrlInNewTab(),

            Actions\Action::make('duplicate')
                ->label('Duplicate')
                ->icon('heroicon-o-document-duplicate')
                ->requiresConfirmation()
                ->action(fn () => $this->record->duplicate()),

            Actions\DeleteAction::make(),
        ];
    }

    public function form(Schema $form): Schema
    {
        $baseComponents = static::getResource()::form(Schema::make($this))->getComponents();

        return $form
            ->schema([
                // Basic form from parent resource
                ...$baseComponents,

                // Lines Management Sections
                Tabs::make('Template Lines')
                    ->tabs([
                        // Header Lines Tab
                        Tabs\Tab::make('Header Lines')
                            ->icon('heroicon-o-bookmark-square')
                            ->badge(fn () => $this->record->headerLines()->count())
                            ->schema([
                                $this->getLinesRepeater('header', 'header_lines', 'headerLines'),
                            ]),

                        // Body Lines Tab
                        Tabs\Tab::make('Body Lines')
                            ->icon('heroicon-o-table-cells')
                            ->badge(fn () => $this->record->bodyLines()->count())
                            ->schema([
                                $this->getLinesRepeater('body', 'body_lines', 'bodyLines'),
                            ]),

                        // Footer Lines Tab
                        Tabs\Tab::make('Footer Lines')
                            ->icon('heroicon-o-rectangle-stack')
                            ->badge(fn () => $this->record->footerLines()->count())
                            ->schema([
                                $this->getLinesRepeater('footer', 'footer_lines', 'footerLines'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        $this->authorizeAccess();

        try {
            $this->beginDatabaseTransaction();

            $this->callHook('beforeValidate');
            $this->callHook('afterValidate');
            $this->callHook('beforeSave');

            $data = $this->data ?? [];
            /** @var InvoiceTemplate $record */
            $record = $this->getRecord();

            $templateData = Arr::only($data, [
                'name',
                'description',
                'is_active',
                'is_default',
                'language',
                'paper_size',
                'paper_width',
                'font_family',
                'font_size',
                'global_styles',
                'company_info',
                'metadata',
                'static_body_html',
            ]);

            $templateData = $this->mutateFormDataBeforeSave($templateData);
            $this->handleRecordUpdate($record, $templateData);

            $this->syncTemplateLines($record, $data);

            $this->callHook('afterSave');
        } catch (Halt $exception) {
            $exception->shouldRollbackDatabaseTransaction()
                ? $this->rollBackDatabaseTransaction()
                : $this->commitDatabaseTransaction();

            return;
        } catch (Throwable $exception) {
            $this->rollBackDatabaseTransaction();

            throw $exception;
        }

        $this->commitDatabaseTransaction();

        $this->rememberData();

        if ($shouldSendSavedNotification) {
            $this->getSavedNotification()?->send();
        }

        if ($shouldRedirect && ($redirectUrl = $this->getRedirectUrl())) {
            $this->redirect($redirectUrl);
        }
    }

    protected function syncTemplateLines(InvoiceTemplate $template, array $data): void
    {
        $this->syncSectionLines($template, 'header', $data['header_lines'] ?? []);
        $this->syncSectionLines($template, 'body', $data['body_lines'] ?? []);
        $this->syncSectionLines($template, 'footer', $data['footer_lines'] ?? []);
    }

    protected function syncSectionLines(InvoiceTemplate $template, string $section, array $items): void
    {
        $keptIds = [];
        $order = 1;

        foreach ($items as $itemKey => $item) {
            if (!is_array($item)) {
                continue;
            }

            $lineId = $this->extractLineId($itemKey, $item);
            $properties = $item['properties'] ?? [];
            $properties = is_array($properties) ? $properties : [];

            $attributes = [
                'section' => $section,
                'type' => (string)($item['type'] ?? 'text'),
                'order' => $order++,
                'is_enabled' => (bool)($item['is_enabled'] ?? true),
                'properties' => $properties,
            ];

            if ($lineId) {
                $line = TemplateLine::query()
                    ->where('template_id', $template->id)
                    ->whereKey($lineId)
                    ->first();

                if ($line) {
                    $line->fill($attributes)->save();
                    $keptIds[] = $line->id;
                    continue;
                }
            }

            $line = $template->lines()->create($attributes);
            $keptIds[] = $line->id;
        }

        $deleteQuery = TemplateLine::query()
            ->where('template_id', $template->id)
            ->where('section', $section);

        if (!empty($keptIds)) {
            $deleteQuery->whereNotIn('id', $keptIds);
        }

        $deleteQuery->delete();
    }

    protected function extractLineId(int|string $itemKey, array $item): ?int
    {
        if (isset($item['id']) && is_numeric($item['id'])) {
            return (int)$item['id'];
        }

        if (!is_string($itemKey)) {
            return null;
        }

        if (!str_starts_with($itemKey, 'record-')) {
            return null;
        }

        $id = Str::after($itemKey, 'record-');

        return ctype_digit($id) ? (int)$id : null;
    }

    protected function getLinesRepeater(string $section, string $statePath, string $relationship): Repeater
    {
        return Repeater::make($statePath)
            ->relationship($relationship, fn ($query) => $query->orderBy('order'))
            ->mutateRelationshipDataBeforeCreateUsing(
                fn (array $data): array => [
                    ...$data,
                    'section' => $section,
                ]
            )
            ->mutateRelationshipDataBeforeSaveUsing(
                fn (array $data): array => [
                    ...$data,
                    'section' => $section,
                ]
            )
            ->schema([
                Grid::make(2)
                    ->schema([
                       Select::make('type')
                            ->label('Line Type')
                            ->options(LineTypeRegistry::getGroupedOptionsForSelect($section))
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set) {
                                // Set default properties for the selected line type
                                $defaultProps = LineTypeRegistry::getDefaultProperties($state);
                                $set('properties', $defaultProps);
                            })
                            , // Can't change type after creation

                        Toggle::make('is_enabled')
                            ->label('Enabled')
                            ->default(true)
                            ->inline(false),
                    ]),

                // Dynamic properties based on line type
                Group::make()
                    ->schema(fn (Get $get) => $this->getPropertiesFields($get('type')))
                    ->columnSpanFull(),

                Hidden::make('section')
                    ->default($section),

                Hidden::make('order')
                    ->default(0),
            ])
            ->reorderable('order')
            ->orderColumn('order')
            ->collapsible()
            ->cloneable(false)
            ->itemLabel(fn (array $state): ?string =>
                LineTypeRegistry::getType($state['type'] ?? '')['label'] ?? 'Line'
            )
            ->addActionLabel('Add Line')

            ->deleteAction(
                fn (Actions\Action $action) => $action
                    ->requiresConfirmation()
            )
            ->columnSpanFull();
    }

    protected function getPropertiesFields(?string $lineType): array
    {
        if (!$lineType) {
            return [];
        }

        $fields = [];
        $config = LineTypeRegistry::getType($lineType);

        if (!$config) {
            return [];
        }

        $properties = $config['properties'] ?? [];

        // Common text line properties
        if (in_array($lineType, ['text', 'invoice_number', 'invoice_date', 'two_column'])) {
            if ($lineType === 'text') {
                $fields[] = Select::make('properties.content_type')
                    ->label('Content Type')
                    ->options([
                        'static' => 'Static Text',
                        'dynamic' => 'Dynamic Field',
                    ])
                    ->default('static')
                    ->reactive()
                    ->required();

                $fields[] = TextInput::make('properties.static_value')
                    ->label('Static Value')
                    ->visible(fn (Get $get) => $get('properties.content_type') === 'static');

                $fields[] = Select::make('properties.dynamic_field')
                    ->label('Dynamic Field')
                    ->options(DynamicFieldResolver::getFieldsByCategory())
                    ->searchable()
                    ->visible(fn (Get $get) => $get('properties.content_type') === 'dynamic');

                $fields[] = Grid::make(2)->schema([
                    TextInput::make('properties.prefix')
                        ->label('Prefix'),
                    TextInput::make('properties.suffix')
                        ->label('Suffix'),
                ]);
            }

            if (in_array($lineType, ['invoice_number', 'invoice_date'])) {
                $fields[] = Toggle::make('properties.show_label')
                    ->label('Show Label')
                    ->default(true)
                    ->inline(false);

                $fields[] = TextInput::make('properties.label')
                    ->label('Label Text')
                    ->visible(fn (Get $get) => $get('properties.show_label') ?? true);

                if ($lineType === 'invoice_date') {
                    $fields[] = Toggle::make('properties.show_time')
                        ->label('Show Time')
                        ->default(true)
                        ->inline(false);

                    $fields[] = TextInput::make('properties.format')
                        ->label('Date Format')
                        ->default('d.m.Y H:i')
                        ->helperText('PHP date format');
                }
            }

            $fields[] = Grid::make(3)->schema([
                TextInput::make('properties.font_size')
                    ->label('Font Size')
                    ->numeric()
                    ->default(10)
                    ->minValue(6)
                    ->maxValue(24),

                Select::make('properties.font_weight')
                    ->label('Font Weight')
                    ->options([
                        'normal' => 'Normal',
                        'bold' => 'Bold',
                    ])
                    ->default('normal'),

                Select::make('properties.alignment')
                    ->label('Alignment')
                    ->options([
                        'left' => 'Left',
                        'center' => 'Center',
                        'right' => 'Right',
                    ])
                    ->default('left'),
            ]);
        }

        // Separator line properties
        if ($lineType === 'separator') {
            $fields[] = Grid::make(3)->schema([
                Select::make('properties.style')
                    ->label('Style')
                    ->options([
                        'solid' => 'Solid',
                        'dashed' => 'Dashed',
                        'dotted' => 'Dotted',
                    ])
                    ->default('solid'),

                TextInput::make('properties.width')
                    ->label('Width (px)')
                    ->numeric()
                    ->default(1),

                ColorPicker::make('properties.color')
                    ->label('Color')
                    ->default('#000000'),
            ]);
        }

        // Spacer properties
        if ($lineType === 'spacer') {
            $fields[] = TextInput::make('properties.height')
                ->label('Height (px)')
                ->numeric()
                ->default(10);
        }

        // Image properties
        if ($lineType === 'image') {
            $fields[] = FileUpload::make('properties.image_path')
                ->label('Image')
                ->image()
                ->directory('invoice-templates/images')
                ->visibility('public');

            $fields[] = Grid::make(3)->schema([
                TextInput::make('properties.width')
                    ->label('Width (px)')
                    ->numeric()
                    ->default(80),

                TextInput::make('properties.height')
                    ->label('Height (px)')
                    ->numeric()
                    ->default(80),

                Select::make('properties.alignment')
                    ->label('Alignment')
                    ->options([
                        'left' => 'Left',
                        'center' => 'Center',
                        'right' => 'Right',
                    ])
                    ->default('center'),
            ]);
        }

        // Items table properties
        if ($lineType === 'items_table') {
            $fields[] = Grid::make(2)->schema([
                Toggle::make('properties.show_item_numbers')
                    ->label('Show Item Numbers')
                    ->default(true)
                    ->inline(false),

                Toggle::make('properties.show_quantity')
                    ->label('Show Quantity')
                    ->default(true)
                    ->inline(false),

                Toggle::make('properties.show_unit_price')
                    ->label('Show Unit Price')
                    ->default(true)
                    ->inline(false),

                Toggle::make('properties.show_tax_rate')
                    ->label('Show Tax Rate')
                    ->default(true)
                    ->inline(false),

                Toggle::make('properties.show_total')
                    ->label('Show Total')
                    ->default(true)
                    ->inline(false),

                Toggle::make('properties.table_border')
                    ->label('Table Border')
                    ->default(true)
                    ->inline(false),
            ]);
        }

        // Totals summary properties
        if ($lineType === 'totals_summary') {
            $fields[] = Grid::make(2)->schema([
                Toggle::make('properties.show_subtotal')
                    ->label('Show Subtotal')
                    ->default(true)
                    ->inline(false),

                Toggle::make('properties.show_tax_breakdown')
                    ->label('Show Tax Breakdown')
                    ->default(true)
                    ->inline(false),

                Toggle::make('properties.show_total')
                    ->label('Show Total')
                    ->default(true)
                    ->inline(false),

                Toggle::make('properties.highlight_total')
                    ->label('Highlight Total')
                    ->default(true)
                    ->inline(false),
            ]);
        }

        // Customer info properties
        if ($lineType === 'customer_info') {
            $fields[] = Grid::make(2)->schema([
                Toggle::make('properties.show_name')
                    ->label('Show Name')
                    ->default(true)
                    ->inline(false),

                Toggle::make('properties.show_email')
                    ->label('Show Email')
                    ->default(true)
                    ->inline(false),

                Toggle::make('properties.show_phone')
                    ->label('Show Phone')
                    ->default(true)
                    ->inline(false),

                Toggle::make('properties.show_address')
                    ->label('Show Address')
                    ->default(false)
                    ->inline(false),
            ]);
        }

        // Payment info properties
        if ($lineType === 'payment_info') {
            $fields[] = Grid::make(2)->schema([
                Toggle::make('properties.show_method')
                    ->label('Show Method')
                    ->default(true)
                    ->inline(false),

                Toggle::make('properties.show_amount')
                    ->label('Show Amount')
                    ->default(true)
                    ->inline(false),
            ]);
        }

        // QR Code properties
        if ($lineType === 'qr_code') {
            $fields[] = Grid::make(2)->schema([
                TextInput::make('properties.size')
                    ->label('Size (px)')
                    ->numeric()
                    ->default(150),

                Select::make('properties.error_correction')
                    ->label('Error Correction')
                    ->options([
                        'L' => 'Low',
                        'M' => 'Medium',
                        'Q' => 'Quartile',
                        'H' => 'High',
                    ])
                    ->default('M'),
            ]);
        }

        // Thank you message properties
        if ($lineType === 'thank_you_message') {
            $fields[] = Textarea::make('properties.message')
                ->label('Message')
                ->default('Thank you for your business!')
                ->rows(2);
        }

        // TSE info properties
        if ($lineType === 'tse_info') {
            $fields[] = Grid::make(2)->schema([
                Toggle::make('properties.show_tss_serial')
                    ->label('Show TSS Serial')
                    ->default(true)
                    ->inline(false),

                Toggle::make('properties.show_transaction_number')
                    ->label('Show Transaction Number')
                    ->default(true)
                    ->inline(false),

                Toggle::make('properties.show_signature_counter')
                    ->label('Show Signature Counter')
                    ->default(true)
                    ->inline(false),

                Toggle::make('properties.show_timestamp')
                    ->label('Show Timestamp')
                    ->default(true)
                    ->inline(false),
            ]);
        }

        // Common margin fields for all types
        $fields[] = Grid::make(2)->schema([
            TextInput::make('properties.margin_top')
                ->label('Margin Top (px)')
                ->numeric()
                ->default($properties['margin_top'] ?? 0),

            TextInput::make('properties.margin_bottom')
                ->label('Margin Bottom (px)')
                ->numeric()
                ->default($properties['margin_bottom'] ?? 2),
        ]);

        return $fields;
    }

}
