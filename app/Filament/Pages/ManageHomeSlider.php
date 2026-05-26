<?php

namespace App\Filament\Pages;

use App\Models\File as FileModel;
use App\Models\Language;
use App\Models\Slider;
use App\Models\SliderItem;
use App\Models\SliderItemTranslation;
use Filament\Actions\Action;           // ✅ Filament v5 — unified namespace
use Filament\Actions\ActionGroup;      // ✅ Filament v5 — unified namespace
use Filament\Actions\DeleteAction;     // ✅ Filament v5 — unified namespace
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class ManageHomeSlider extends Page implements HasTable, HasForms
{
    use InteractsWithTable;
    use InteractsWithForms;

    protected string $view = 'filament.pages.manage-home-slider';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static ?int $navigationSort = 34;

    /** السلايدر الرئيسي للصفحة الرئيسية */
    public ?Slider $slider = null;

    // ── Navigation ─────────────────────────────────────────────────────────────

    public static function getNavigationLabel(): string
    {
        return __('slider.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.content');
    }

    public function getTitle(): string
    {
        return __('slider.page_title');
    }

    public function getHeading(): string
    {
        return __('slider.page_heading');
    }

    // ── Lifecycle ──────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->slider = Slider::firstOrCreate(
            ['key' => 'home'],
            ['name' => 'Home Page Slider', 'is_active' => true]
        );
    }

    // ── View Data (يُمرَّر للـ Blade) ──────────────────────────────────────────

    protected function getViewData(): array
    {
        if (! $this->slider) {
            return ['stats' => [], 'activeItems' => collect()];
        }

        $allItems = SliderItem::where('slider_id', $this->slider->id)
            ->with(['translations.language', 'image'])
            ->orderBy('sort_order')
            ->get();

        $activeItems = $allItems->filter(fn ($i) => $i->isPublishedNow());

        return [
            'stats' => [
                'total'     => $allItems->count(),
                'active'    => $activeItems->count(),
                'scheduled' => $allItems->filter(
                    fn ($i) => $i->is_active && $i->starts_at?->isFuture()
                )->count(),
                'inactive'  => $allItems->filter(fn ($i) => ! $i->is_active)->count(),
            ],
            'activeItems' => $activeItems,
        ];
    }

    // ── Header Actions ─────────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add_slide')
                ->label(__('slider.action_add'))
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->slideOver()
                ->modalWidth('4xl')
                ->modalHeading(__('slider.modal_add_heading'))
                ->modalSubmitActionLabel(__('slider.modal_submit_label'))
                ->form($this->buildSlideForm())
                ->action(fn (array $data) => $this->createItem($data)),
        ];
    }

    // ── Table ──────────────────────────────────────────────────────────────────

    public function table(Table $table): Table
    {
        $sliderId = $this->slider?->id ?? 0;

        return $table
            ->query(
                SliderItem::query()
                    ->where('slider_id', $sliderId)
                    ->with(['translations.language', 'image'])
            )
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([

                // ── الصورة المصغّرة ───────────────────────────────────────
                ImageColumn::make('thumb')
                    ->label(__('slider.col_image'))
                    ->getStateUsing(fn (SliderItem $record): ?string => $record->image?->urlFile())
                    ->width(100)
                    ->height(65)
                    ->extraImgAttributes([
                        'style' => 'object-fit:cover; border-radius:8px;',
                    ])
                    ->defaultImageUrl(fn () => 'https://placehold.co/100x65/e2e8f0/94a3b8?text=No+Image'),

                // ── العنوان ───────────────────────────────────────────────
                TextColumn::make('title_preview')
                    ->label(__('slider.col_title'))
                    ->getStateUsing(function (SliderItem $record): string {
                        return $record->getTranslation('ar')?->title
                            ?? $record->getTranslation('en')?->title
                            ?? '—';
                    })
                    ->weight('bold')
                    ->wrap()
                    ->description(function (SliderItem $record): ?string {
                        return $record->getTranslation('ar')?->subtitle
                            ?? $record->getTranslation('en')?->subtitle;
                    }),

                // ── الحالة ────────────────────────────────────────────────
                TextColumn::make('status_label')
                    ->label(__('slider.col_status'))
                    ->badge()
                    ->getStateUsing(function (SliderItem $record): string {
                        if (! $record->is_active)              { return __('slider.status_inactive'); }
                        if ($record->starts_at?->isFuture())   { return __('slider.status_scheduled'); }
                        if ($record->ends_at?->isPast())       { return __('slider.status_expired'); }
                        if ($record->isPermanent())            { return __('slider.status_permanent'); }
                        return __('slider.status_active');
                    })
                    ->color(function (SliderItem $record): string {
                        if (! $record->is_active)              { return 'danger'; }
                        if ($record->starts_at?->isFuture())   { return 'info'; }
                        if ($record->ends_at?->isPast())       { return 'gray'; }
                        return 'success';
                    })
                    ->icon(function (SliderItem $record): string {
                        if (! $record->is_active)              { return 'heroicon-o-x-circle'; }
                        if ($record->starts_at?->isFuture())   { return 'heroicon-o-clock'; }
                        if ($record->ends_at?->isPast())       { return 'heroicon-o-archive-box'; }
                        return 'heroicon-o-check-circle';
                    }),

                // ── نافذة النشر ───────────────────────────────────────────
                TextColumn::make('starts_at')
                    ->label(__('slider.col_starts_at'))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder(__('slider.permanent'))
                    ->color('gray')
                    ->icon('heroicon-o-calendar')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('ends_at')
                    ->label(__('slider.col_ends_at'))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder(__('slider.permanent'))
                    ->color('gray')
                    ->icon('heroicon-o-calendar')
                    ->sortable()
                    ->toggleable(),

                // ── الترتيب ───────────────────────────────────────────────
                TextColumn::make('sort_order')
                    ->label(__('slider.col_order'))
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('gray'),
            ])
            ->actions([
                ActionGroup::make([

                    // ── تعديل ─────────────────────────────────────────────
                    Action::make('edit')
                        ->label(__('slider.action_edit'))
                        ->icon('heroicon-o-pencil-square')
                        ->color('primary')
                        ->slideOver()
                        ->modalWidth('4xl')
                        ->modalHeading(__('slider.modal_edit_heading'))
                        ->modalSubmitActionLabel(__('slider.modal_save_changes'))
                        ->form($this->buildSlideForm())
                        ->fillForm(fn (SliderItem $record): array => $this->fillItemForm($record))
                        ->action(fn (array $data, SliderItem $record) => $this->updateItem($data, $record)),

                    // ── تفعيل / تعطيل ─────────────────────────────────────
                    Action::make('toggle_active')
                        ->label(fn (SliderItem $record): string => $record->is_active
                            ? __('slider.action_toggle_off')
                            : __('slider.action_toggle_on'))
                        ->icon(fn (SliderItem $record): string => $record->is_active
                            ? 'heroicon-o-eye-slash'
                            : 'heroicon-o-eye')
                        ->color(fn (SliderItem $record): string => $record->is_active ? 'warning' : 'success')
                        ->requiresConfirmation(fn (SliderItem $record): bool => $record->is_active)
                        ->modalHeading(__('slider.modal_disable_heading'))
                        ->action(function (SliderItem $record): void {
                            $record->update(['is_active' => ! $record->is_active]);
                            Slider::clearCache('home');
                            Notification::make()
                                ->success()
                                ->title($record->is_active
                                    ? __('slider.notif_enabled')
                                    : __('slider.notif_disabled'))
                                ->send();
                        }),

                    // ── حذف ───────────────────────────────────────────────
                    DeleteAction::make()
                        ->label(__('slider.action_delete'))
                        ->modalHeading(__('slider.modal_delete_heading'))
                        ->modalDescription(__('slider.modal_delete_desc'))
                        ->after(fn () => Slider::clearCache('home')),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-photo')
            ->emptyStateHeading(__('slider.empty_heading'))
            ->emptyStateDescription(__('slider.empty_desc'));
    }

    // ── Form Builder ───────────────────────────────────────────────────────────

    protected function buildSlideForm(): array
    {
        return [
            // ── الصورة ─────────────────────────────────────────────────────
            Section::make(__('slider.section_image'))
                ->description(__('slider.section_image_desc'))
                ->icon('heroicon-o-photo')
                ->schema([
                    FileUpload::make('image_upload')
                        ->label(__('slider.field_image'))
                        ->image()
                        ->disk('public')
                        ->directory('sliders')
                        ->imagePreviewHeight('220')
                        ->maxSize(8192)
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                        ->columnSpanFull()
                        ->helperText(__('slider.field_image_help')),
                ]),

            // ── الترجمات ───────────────────────────────────────────────────
            Section::make(__('slider.section_content'))
                ->description(__('slider.section_content_desc'))
                ->icon('heroicon-o-language')
                ->schema([

                    Grid::make(3)->schema([
                        TextInput::make('translations.ar.title')
                            ->label('🇸🇦 العنوان (AR)')
                            ->required()
                            ->maxLength(255)
                            ->extraInputAttributes(['dir' => 'rtl']),
                        TextInput::make('translations.en.title')
                            ->label('🇬🇧 Title (EN)')
                            ->maxLength(255),
                        TextInput::make('translations.de.title')
                            ->label('🇩🇪 Titel (DE)')
                            ->maxLength(255),
                    ]),

                    Grid::make(3)->schema([
                        TextInput::make('translations.ar.subtitle')
                            ->label('🇸🇦 العنوان الفرعي (AR)')
                            ->maxLength(255)
                            ->extraInputAttributes(['dir' => 'rtl']),
                        TextInput::make('translations.en.subtitle')
                            ->label('🇬🇧 Subtitle (EN)')
                            ->maxLength(255),
                        TextInput::make('translations.de.subtitle')
                            ->label('🇩🇪 Untertitel (DE)')
                            ->maxLength(255),
                    ]),

                    Grid::make(3)->schema([
                        Textarea::make('translations.ar.description')
                            ->label('🇸🇦 الوصف (AR)')
                            ->rows(3)
                            ->maxLength(1000)
                            ->extraInputAttributes(['dir' => 'rtl']),
                        Textarea::make('translations.en.description')
                            ->label('🇬🇧 Description (EN)')
                            ->rows(3)
                            ->maxLength(1000),
                        Textarea::make('translations.de.description')
                            ->label('🇩🇪 Beschreibung (DE)')
                            ->rows(3)
                            ->maxLength(1000),
                    ]),
                ]),

            // ── إعدادات النشر ──────────────────────────────────────────────
            Section::make(__('slider.section_publish'))
                ->description(__('slider.section_publish_desc'))
                ->icon('heroicon-o-calendar-days')
                ->schema([
                    Grid::make(2)->schema([
                        Toggle::make('is_active')
                            ->label(__('slider.field_is_active'))
                            ->helperText(__('slider.field_is_active_help'))
                            ->onColor('success')
                            ->offColor('danger')
                            ->inline(false)
                            ->default(true),
                    ]),

                    Grid::make(2)->schema([
                        DateTimePicker::make('starts_at')
                            ->label(__('slider.field_starts_at'))
                            ->helperText(__('slider.field_starts_at_help'))
                            ->nullable()
                            ->seconds(false)
                            ->displayFormat('d/m/Y H:i'),

                        DateTimePicker::make('ends_at')
                            ->label(__('slider.field_ends_at'))
                            ->helperText(__('slider.field_ends_at_help'))
                            ->nullable()
                            ->seconds(false)
                            ->displayFormat('d/m/Y H:i')
                            ->after('starts_at'),
                    ]),
                ]),
        ];
    }

    // ── Form Fill (للتعديل) ────────────────────────────────────────────────────

    protected function fillItemForm(SliderItem $record): array
    {
        $record->load(['translations.language', 'image']);

        $translationsData = [];
        foreach ($record->translations as $translation) {
            $code = $translation->language?->code;
            if (! $code) continue;

            $translationsData[$code] = [
                'title'       => $translation->title,
                'subtitle'    => $translation->subtitle,
                'description' => $translation->description,
            ];
        }

        return [
            'is_active'    => $record->is_active,
            'starts_at'    => $record->starts_at,
            'ends_at'      => $record->ends_at,
            'image_upload' => $record->image ? [$record->image->path] : [],
            'translations' => $translationsData,
        ];
    }

    // ── Save Methods ───────────────────────────────────────────────────────────

    protected function createItem(array $data): void
    {
        $nextOrder = SliderItem::where('slider_id', $this->slider->id)->max('sort_order') + 1;

        $item = SliderItem::create([
            'slider_id'  => $this->slider->id,
            'sort_order' => $nextOrder,
            'is_active'  => $data['is_active'] ?? true,
            'starts_at'  => $data['starts_at'] ?? null,
            'ends_at'    => $data['ends_at'] ?? null,
        ]);

        $this->syncSlideImage($item, $data['image_upload'] ?? null);
        $this->syncTranslations($item, $data['translations'] ?? []);

        Slider::clearCache('home');

        Notification::make()
            ->success()
            ->title(__('slider.notif_created'))
            ->body(__('slider.notif_created_body'))
            ->send();
    }

    protected function updateItem(array $data, SliderItem $record): void
    {
        $record->update([
            'is_active' => $data['is_active'] ?? true,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at'   => $data['ends_at'] ?? null,
        ]);

        $this->syncSlideImage($record, $data['image_upload'] ?? null);
        $this->syncTranslations($record, $data['translations'] ?? []);

        Slider::clearCache('home');

        Notification::make()
            ->success()
            ->title(__('slider.notif_updated'))
            ->body(__('slider.notif_updated_body'))
            ->send();
    }

    // ── Sync Helpers ───────────────────────────────────────────────────────────

    protected function syncSlideImage(SliderItem $item, mixed $upload): void
    {
        if (empty($upload)) {
            return;
        }

        $path = is_array($upload) ? collect($upload)->first() : $upload;

        if (! $path) {
            return;
        }

        // نفس المسار — لا تغيير
        if ($item->image && $item->image->path === $path) {
            return;
        }

        // حذف القديمة
        if ($item->image) {
            $item->image->delete();
        }

        FileModel::create([
            'instance_type' => SliderItem::class,
            'instance_id'   => $item->id,
            'name'          => pathinfo($path, PATHINFO_FILENAME),
            'path'          => $path,
            'disk'          => 'public',
            'type'          => 'slider_image',
            'key'           => 'image',
            'extension'     => pathinfo($path, PATHINFO_EXTENSION),
            'group'         => 'slider',
        ]);
    }

    protected function syncTranslations(SliderItem $item, array $translationsData): void
    {
        if (empty($translationsData)) {
            return;
        }

        $languages = Language::whereIn('code', array_keys($translationsData))
            ->get()
            ->keyBy('code');

        foreach ($translationsData as $code => $fields) {
            if (! isset($languages[$code])) {
                continue;
            }

            if (empty($fields['title']) && empty($fields['subtitle']) && empty($fields['description'])) {
                continue;
            }

            SliderItemTranslation::updateOrCreate(
                [
                    'slider_item_id' => $item->id,
                    'language_id'    => $languages[$code]->id,
                ],
                [
                    'title'       => $fields['title'] ?? '',
                    'subtitle'    => $fields['subtitle'] ?? null,
                    'description' => $fields['description'] ?? null,
                ]
            );
        }
    }
}
