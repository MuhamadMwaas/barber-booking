<?php

namespace App\Filament\Pages;

use App\Models\AboutUsPage;
use App\Models\AboutUsTeamMember;
use App\Models\File as FileModel;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
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
use Livewire\Attributes\Url;

class ManageAboutUs extends Page implements HasForms
{
    // use NavigationDefaultAccess;
    use InteractsWithForms;

    protected  string $view = 'filament.pages.manage-about-us';
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedInformationCircle;
    protected static ?int $navigationSort = 32;

    #[Url(as: 'locale')]
    public string $activeLocale = 'de';

    public ?AboutUsPage $record = null;

    public static function getNavigationLabel(): string
    {
        return __('about_us.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.content');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('about_us.page_title');
    }

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('about_us.page_title');
    }

    public function mount(): void
    {
        $this->loadRecord();
    }

    public function loadRecord(): void
    {
        $this->record = AboutUsPage::with(['heroImages', 'teamMembers'])->first();

        if (!$this->record) {
            $this->record = $this->createDefaultRecord();
        }
    }

    public function switchLocale(string $locale): void
    {
        $this->activeLocale = $locale;
        // No DB reload needed — same record, just change display language
    }

    protected function createDefaultRecord(): AboutUsPage
    {
        return AboutUsPage::create([
            'hero_title'             => ['de' => '', 'ar' => '', 'en' => ''],
            'hero_subtitle'          => ['de' => '', 'ar' => '', 'en' => ''],
            'hero_description'       => ['de' => '', 'ar' => '', 'en' => ''],
            'contact_phone'          => ['value' => '', 'label' => ['de' => 'Telefon', 'ar' => 'الهاتف', 'en' => 'Phone'], 'icon' => 'heroicon-o-phone'],
            'contact_address'        => ['value' => '', 'label' => ['de' => 'Adresse', 'ar' => 'العنوان', 'en' => 'Address'], 'icon' => 'heroicon-o-map-pin'],
            'contact_email'          => null,
            'opening_hours'          => ['de' => '', 'ar' => '', 'en' => ''],
            'social_title'           => ['de' => 'Folge uns', 'ar' => 'تابعنا', 'en' => 'Follow us'],
            'social_links'           => [],
            'legal_links'            => [],
            'features'               => [],
            'newsletter_title'       => ['de' => '', 'ar' => '', 'en' => ''],
            'newsletter_description' => ['de' => null, 'ar' => null, 'en' => null],
            'newsletter_enabled'     => true,
            'is_active'              => true,
        ]);
    }

    // ── Edit Form Data ──────────────────────────────────────────────────────────

    protected function getEditFormData(): array
    {
        if (!$this->record) {
            return [];
        }

        $data = $this->record->toArray();

        $data['hero_images'] = $this->record->heroImages->pluck('path')->toArray();

        $data['team_members'] = $this->record->teamMembers->map(fn($m) => [
            '_id'         => (string) $m->id,
            'name'        => $m->name,
            'position'    => $m->position,
            'description' => $m->description,
            'image'       => $m->image ? [$m->image] : [],
            'sort_order'  => $m->sort_order,
            'is_active'   => $m->is_active,
        ])->toArray();

        return $data;
    }

    // ── Header Actions ──────────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label(__('about_us.edit_page'))
                ->icon('heroicon-o-pencil-square')
                ->color('primary')
                ->slideOver()
                ->fillForm(fn(): array => $this->getEditFormData())
                ->form($this->buildEditForm())
                ->modalHeading(__('about_us.edit_page'))
                ->modalSubmitActionLabel(__('about_us.save_changes'))
                ->action(fn(array $data) => $this->saveRecord($data)),
        ];
    }

    // ── Form Schema ─────────────────────────────────────────────────────────────

    protected function buildEditForm(): array
    {
        return [

            // ── Hero ─────────────────────────────────────────────────────────
            Section::make(__('about_us.hero_section'))
                ->description(__('about_us.hero_section_desc'))
                ->icon('heroicon-o-photo')
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('hero_title.de')
                            ->label('Titel (DE)')
                            ->maxLength(255)
                            ->prefixIcon('heroicon-m-language'),
                        TextInput::make('hero_title.ar')
                            ->label('العنوان (AR)')
                            ->maxLength(255)
                            ->extraInputAttributes(['dir' => 'rtl']),
                        TextInput::make('hero_title.en')
                            ->label('Title (EN)')
                            ->maxLength(255),
                    ]),
                    Grid::make(3)->schema([
                        TextInput::make('hero_subtitle.de')->label('Untertitel (DE)')->maxLength(255),
                        TextInput::make('hero_subtitle.ar')->label('العنوان الفرعي (AR)')->maxLength(255)->extraInputAttributes(['dir' => 'rtl']),
                        TextInput::make('hero_subtitle.en')->label('Subtitle (EN)')->maxLength(255),
                    ]),
                    Grid::make(3)->schema([
                        Textarea::make('hero_description.de')->label('Beschreibung (DE)')->rows(3),
                        Textarea::make('hero_description.ar')->label('الوصف (AR)')->rows(3)->extraInputAttributes(['dir' => 'rtl']),
                        Textarea::make('hero_description.en')->label('Description (EN)')->rows(3),
                    ]),
                    FileUpload::make('hero_images')
                        ->label(__('about_us.hero_images'))
                        ->image()
                        ->multiple()
                        ->reorderable()
                        ->disk('public')
                        ->directory('about-us/hero')
                        ->imagePreviewHeight('120')
                        ->panelLayout('grid')
                        ->helperText(__('about_us.hero_images_help'))
                        ->columnSpanFull(),
                ]),

            // ── Contact ──────────────────────────────────────────────────────
            Section::make(__('about_us.contact_section'))
                ->description(__('about_us.contact_section_desc'))
                ->icon('heroicon-o-phone')
                ->collapsible()
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('contact_phone.value')
                            ->label(__('about_us.phone_number'))
                            ->tel()
                            ->prefixIcon('heroicon-m-phone'),
                        TextInput::make('contact_phone.icon')
                            ->label(__('about_us.phone_icon'))
                            ->placeholder('heroicon-o-phone')
                            ->helperText(__('about_us.icon_hint')),
                    ]),
                    Grid::make(2)->schema([
                        TextInput::make('contact_phone.label.de')->label('Phone Label (DE)')->placeholder('Telefon'),
                        TextInput::make('contact_phone.label.ar')->label('تسمية الهاتف (AR)')->placeholder('الهاتف')->extraInputAttributes(['dir' => 'rtl']),
                    ]),
                    Grid::make(2)->schema([
                        TextInput::make('contact_address.value')
                            ->label(__('about_us.address'))
                            ->prefixIcon('heroicon-m-map-pin'),
                        TextInput::make('contact_address.icon')
                            ->label(__('about_us.address_icon'))
                            ->placeholder('heroicon-o-map-pin')
                            ->helperText(__('about_us.icon_hint')),
                    ]),
                    Grid::make(2)->schema([
                        TextInput::make('contact_address.label.de')->label('Address Label (DE)')->placeholder('Adresse'),
                        TextInput::make('contact_address.label.ar')->label('تسمية العنوان (AR)')->placeholder('العنوان')->extraInputAttributes(['dir' => 'rtl']),
                    ]),
                    Grid::make(3)->schema([
                        TextInput::make('contact_email')
                            ->label(__('about_us.email'))
                            ->email()
                            ->prefixIcon('heroicon-m-envelope'),
                        TextInput::make('opening_hours.de')
                            ->label(__('about_us.opening_hours') . ' (DE)')
                            ->placeholder('Mo-Fr 9:00–18:00'),
                        TextInput::make('opening_hours.ar')
                            ->label(__('about_us.opening_hours') . ' (AR)')
                            ->placeholder('السبت–الخميس 9:00–18:00')
                            ->extraInputAttributes(['dir' => 'rtl']),
                    ]),
                ]),

            // ── Social & Legal ───────────────────────────────────────────────
            Section::make(__('about_us.social_section'))
                ->description(__('about_us.social_section_desc'))
                ->icon('heroicon-o-share')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('social_title.de')->label('Social Titel (DE)'),
                        TextInput::make('social_title.ar')->label('عنوان التواصل (AR)')->extraInputAttributes(['dir' => 'rtl']),
                        TextInput::make('social_title.en')->label('Social Title (EN)'),
                    ]),
                    Repeater::make('social_links')
                        ->label(__('about_us.social_links'))
                        ->schema([
                            Grid::make(3)->schema([
                                Select::make('platform')
                                    ->label(__('about_us.platform'))
                                    ->options([
                                        'instagram' => 'Instagram',
                                        'facebook'  => 'Facebook',
                                        'twitter'   => 'Twitter / X',
                                        'youtube'   => 'YouTube',
                                        'tiktok'    => 'TikTok',
                                        'linkedin'  => 'LinkedIn',
                                        'whatsapp'  => 'WhatsApp',
                                        'telegram'  => 'Telegram',
                                        'snapchat'  => 'Snapchat',
                                    ])
                                    ->required()
                                    ->searchable(),
                                TextInput::make('url')
                                    ->label('URL')
                                    ->url()
                                    ->required()
                                    ->prefixIcon('heroicon-m-link'),
                                TextInput::make('icon')
                                    ->label('Icon')
                                    ->placeholder('heroicon-o-...')
                                    ->helperText(__('about_us.icon_hint')),
                            ]),
                        ])
                        ->addActionLabel(__('about_us.add_social_link'))
                        ->collapsible()
                        ->reorderable()
                        ->cloneable()
                        ->defaultItems(0)
                        ->grid(1),
                    Repeater::make('legal_links')
                        ->label(__('about_us.legal_links'))
                        ->schema([
                            Grid::make(3)->schema([
                                TextInput::make('key')->label('Key')->required()->placeholder('impressum'),
                                TextInput::make('label.de')->label('Label (DE)')->placeholder('Impressum'),
                                TextInput::make('label.ar')->label('التسمية (AR)')->placeholder('بيانات الناشر')->extraInputAttributes(['dir' => 'rtl']),
                            ]),
                        ])
                        ->addActionLabel(__('about_us.add_legal_link'))
                        ->collapsible()
                        ->reorderable()
                        ->defaultItems(0)
                        ->grid(1),
                ]),

            // ── Features ─────────────────────────────────────────────────────
            Section::make(__('about_us.features_section'))
                ->description(__('about_us.features_section_desc'))
                ->icon('heroicon-o-star')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Repeater::make('features')
                        ->label('')
                        ->schema([
                            TextInput::make('icon')
                                ->label(__('about_us.feature_icon'))
                                ->placeholder('heroicon-o-star')
                                ->helperText(__('about_us.icon_hint'))
                                ->prefixIcon('heroicon-m-sparkles')
                                ->columnSpanFull(),
                            Grid::make(3)->schema([
                                TextInput::make('title.de')->label('Titel (DE)')->required(),
                                TextInput::make('title.ar')->label('العنوان (AR)')->required()->extraInputAttributes(['dir' => 'rtl']),
                                TextInput::make('title.en')->label('Title (EN)'),
                            ]),
                            Grid::make(3)->schema([
                                Textarea::make('description.de')->label('Beschreibung (DE)')->rows(2),
                                Textarea::make('description.ar')->label('الوصف (AR)')->rows(2)->extraInputAttributes(['dir' => 'rtl']),
                                Textarea::make('description.en')->label('Description (EN)')->rows(2),
                            ]),
                        ])
                        ->addActionLabel(__('about_us.add_feature'))
                        ->collapsible()
                        ->reorderable()
                        ->defaultItems(0)
                        ->maxItems(6)
                        ->grid(1),
                ]),

            // ── Newsletter ───────────────────────────────────────────────────
            Section::make(__('about_us.newsletter_section'))
                ->description(__('about_us.newsletter_section_desc'))
                ->icon('heroicon-o-envelope')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Toggle::make('newsletter_enabled')
                        ->label(__('about_us.newsletter_enabled'))
                        ->onColor('success')
                        ->offColor('gray')
                        ->inline(false)
                        ->columnSpanFull(),
                    Grid::make(3)->schema([
                        TextInput::make('newsletter_title.de')->label('Newsletter Titel (DE)'),
                        TextInput::make('newsletter_title.ar')->label('عنوان النشرة (AR)')->extraInputAttributes(['dir' => 'rtl']),
                        TextInput::make('newsletter_title.en')->label('Newsletter Title (EN)'),
                    ]),
                    Grid::make(3)->schema([
                        Textarea::make('newsletter_description.de')->label('Beschreibung (DE)')->rows(2),
                        Textarea::make('newsletter_description.ar')->label('الوصف (AR)')->rows(2)->extraInputAttributes(['dir' => 'rtl']),
                        Textarea::make('newsletter_description.en')->label('Description (EN)')->rows(2),
                    ]),
                ]),

            // ── Team Members ─────────────────────────────────────────────────
            Section::make(__('about_us.team_section'))
                ->description(__('about_us.team_section_desc'))
                ->icon('heroicon-o-user-group')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Repeater::make('team_members')
                        ->label('')
                        ->schema([
                            Grid::make(4)->schema([
                                FileUpload::make('image')
                                    ->label(__('about_us.team_member_image'))
                                    ->image()
                                    ->disk('public')
                                    ->directory('about-us/team')
                                    ->imagePreviewHeight('80')
                                    ->avatar()
                                    ->columnSpan(1),
                                Grid::make(2)->schema([
                                    Toggle::make('is_active')
                                        ->label(__('about_us.active'))
                                        ->onColor('success')
                                        ->inline(false)
                                        ->default(true),
                                    TextInput::make('sort_order')
                                        ->label(__('about_us.sort_order'))
                                        ->numeric()
                                        ->default(0),
                                    TextInput::make('_id')->hidden()->dehydrated(),
                                ])->columnSpan(3),
                            ]),
                            Grid::make(3)->schema([
                                TextInput::make('name.de')->label('Name (DE)')->required(),
                                TextInput::make('name.ar')->label('الاسم (AR)')->required()->extraInputAttributes(['dir' => 'rtl']),
                                TextInput::make('name.en')->label('Name (EN)'),
                            ]),
                            Grid::make(3)->schema([
                                TextInput::make('position.de')->label('Position (DE)'),
                                TextInput::make('position.ar')->label('المنصب (AR)')->extraInputAttributes(['dir' => 'rtl']),
                                TextInput::make('position.en')->label('Position (EN)'),
                            ]),
                            Grid::make(3)->schema([
                                Textarea::make('description.de')->label('Bio (DE)')->rows(2),
                                Textarea::make('description.ar')->label('السيرة (AR)')->rows(2)->extraInputAttributes(['dir' => 'rtl']),
                                Textarea::make('description.en')->label('Bio (EN)')->rows(2),
                            ]),
                        ])
                        ->addActionLabel(__('about_us.add_team_member'))
                        ->collapsible()
                        ->reorderable()
                        ->defaultItems(0)
                        ->grid(1),
                ]),

            // ── Page Status ──────────────────────────────────────────────────
            Section::make(__('about_us.page_settings'))
                ->icon('heroicon-o-cog-6-tooth')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Toggle::make('is_active')
                        ->label(__('about_us.page_active'))
                        ->onColor('success')
                        ->offColor('danger')
                        ->inline(false),
                ]),
        ];
    }

    // ── Save ────────────────────────────────────────────────────────────────────

    protected function saveRecord(array $data): void
    {
        $heroImages    = $data['hero_images']   ?? [];
        $teamMembers   = $data['team_members']  ?? [];
        unset($data['hero_images'], $data['team_members']);

        if ($this->record) {
            $this->record->update($data);
        } else {
            $this->record = AboutUsPage::create($data);
        }

        $this->syncHeroImages($heroImages);
        $this->syncTeamMembers($teamMembers);

        AboutUsPage::clearCache();
        $this->loadRecord();

        Notification::make()
            ->success()
            ->title(__('about_us.saved_successfully'))
            ->body(__('about_us.saved_description'))
            ->send();
    }

    protected function syncHeroImages(array $newPaths): void
    {
        $existingImages = $this->record->heroImages()->get();
        $existingPaths  = $existingImages->pluck('path')->toArray();

        foreach ($existingImages as $image) {
            if (!in_array($image->path, $newPaths)) {
                $image->delete();
            }
        }

        foreach ($newPaths as $sortOrder => $path) {
            $existing = $existingImages->firstWhere('path', $path);
            if ($existing) {
                $existing->update(['sort_order' => $sortOrder]);
            } else {
                FileModel::create([
                    'instance_type' => AboutUsPage::class,
                    'instance_id'   => $this->record->id,
                    'name'          => pathinfo($path, PATHINFO_FILENAME),
                    'path'          => $path,
                    'disk'          => 'public',
                    'type'          => 'image',
                    'key'           => 'hero_image',
                    'extension'     => pathinfo($path, PATHINFO_EXTENSION),
                    'group'         => 'hero_slideshow',
                    'sort_order'    => $sortOrder,
                ]);
            }
        }
    }

    protected function syncTeamMembers(array $membersData): void
    {
        $existingIds = collect($membersData)
            ->pluck('_id')
            ->filter()
            ->map(fn($id) => (int) $id)
            ->toArray();

        $this->record->teamMembers()->whereNotIn('id', $existingIds)->delete();

        foreach ($membersData as $index => $memberData) {
            $memberId = isset($memberData['_id']) && $memberData['_id']
                ? (int) $memberData['_id']
                : null;

            unset($memberData['_id']);

            // FileUpload with avatar returns an array; flatten to string
            if (is_array($memberData['image'] ?? null)) {
                $memberData['image'] = collect($memberData['image'])->first();
            }

            $memberData['sort_order']      = $index;
            $memberData['about_us_page_id'] = $this->record->id;

            if ($memberId) {
                AboutUsTeamMember::where('id', $memberId)
                    ->where('about_us_page_id', $this->record->id)
                    ->update(collect($memberData)->except('about_us_page_id')->toArray());
            } else {
                AboutUsTeamMember::create($memberData);
            }
        }
    }
}
