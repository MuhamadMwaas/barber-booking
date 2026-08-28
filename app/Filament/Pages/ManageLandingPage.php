<?php

namespace App\Filament\Pages;

use App\Models\LandingSection;
use App\Models\Language;
use App\Support\LandingIcons;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

/**
 * The single screen that edits the entire public website.
 *
 * One tab per row in `landing_sections`. Form state is nested exactly like the
 * stored payload —
 *
 *     data.<section key>.is_active
 *     data.<section key>.sort_order
 *     data.<section key>.content.<field>[.<locale>]
 *
 * — so mount() and save() are a straight read/write with no mapping layer, and
 * a field added to the schema below needs no migration and no model change.
 *
 * Every visitor-facing string is stored as a locale map. The translatable
 * helpers at the bottom of this class build one input per ACTIVE language, so
 * enabling a fourth language in Languages immediately adds a fourth input here
 * without touching this file.
 */
class ManageLandingPage extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.manage-landing-page';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?int $navigationSort = 30;

    /** @var array<string, mixed> */
    public ?array $data = [];

    /* ==========================================================
     | Navigation
     |========================================================== */

    public static function getNavigationLabel(): string
    {
        return __('landing.navigation_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.content');
    }

    public function getTitle(): string|Htmlable
    {
        return __('landing.page_title');
    }

    public function getHeading(): string|Htmlable
    {
        return __('landing.page_title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('landing.page_subheading');
    }

    /* ==========================================================
     | Lifecycle
     |========================================================== */

    public function mount(): void
    {
        $this->ensureSectionsExist();

        $payload = LandingSection::query()
            ->get()
            ->mapWithKeys(fn (LandingSection $section) => [
                $section->key => [
                    'is_active'  => $section->is_active,
                    'sort_order' => $section->sort_order,
                    'content'    => $section->content ?? [],
                ],
            ])
            ->all();

        // fill() rather than `$this->data = …`: assigning the property skips
        // every component's hydration callback. FileUpload in particular stores
        // a plain path string in the JSON but works internally with an array —
        // without fill() the raw string reaches its validator and the save
        // blows up with "Argument #2 ($value) must be of type array".
        $this->form->fill($payload);
    }

    /**
     * Create any section row that does not exist yet.
     *
     * Keeps the screen usable on a database that was migrated but never seeded,
     * and makes adding a new key to LandingSection::KEYS a one-line change: the
     * row appears on the next page load instead of needing its own migration.
     */
    private function ensureSectionsExist(): void
    {
        $existing = LandingSection::query()->pluck('key')->all();

        foreach (LandingSection::KEYS as $index => $key) {
            if (in_array($key, $existing, true)) {
                continue;
            }

            LandingSection::create([
                'key'        => $key,
                'is_active'  => true,
                'sort_order' => array_search($key, LandingSection::BODY_KEYS, true) === false
                    ? 0
                    : (int) array_search($key, LandingSection::BODY_KEYS, true) * 10,
                'content'    => [],
            ]);
        }
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach (LandingSection::KEYS as $key) {
            if (! isset($state[$key])) {
                continue;
            }

            LandingSection::query()
                ->where('key', $key)
                ->update([
                    'is_active'  => (bool) ($state[$key]['is_active'] ?? true),
                    'sort_order' => (int) ($state[$key]['sort_order'] ?? 0),
                    'content'    => $state[$key]['content'] ?? [],
                    'updated_at' => now(),
                ]);
        }

        // `update()` on a query builder does not fire model events, so the cache
        // has to be busted explicitly here.
        LandingSection::flushCache();

        Notification::make()
            ->success()
            ->title(__('landing.saved_title'))
            ->body(__('landing.saved_body'))
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label(__('landing.save'))
                ->icon('heroicon-o-check')
                ->color('primary')
                ->action('save')
                ->keyBindings(['mod+s']),

            Action::make('preview')
                ->label(__('landing.preview'))
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => route('landing'), shouldOpenInNewTab: true),

            Action::make('previewApp')
                ->label(__('landing.preview_app'))
                ->icon('heroicon-o-device-phone-mobile')
                ->color('gray')
                ->url(fn () => route('landing.app'), shouldOpenInNewTab: true),

            Action::make('clearCache')
                ->label(__('landing.clear_cache'))
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    LandingSection::flushCache();

                    Notification::make()
                        ->success()
                        ->title(__('landing.cache_cleared'))
                        ->send();
                }),
        ];
    }

    /* ==========================================================
     | Schema
     |========================================================== */

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('landing')
                    ->persistTabInQueryString('section')
                    ->tabs([
                        $this->brandTab(),
                        $this->seoTab(),
                        $this->navbarTab(),
                        $this->heroTab(),
                        $this->servicesTab(),
                        $this->featuresTab(),
                        $this->galleryTab(),
                        $this->ctaTab(),
                        $this->infoTab(),
                        $this->footerTab(),
                        $this->appPageTab(),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    /* ── Brand ────────────────────────────────────────────────── */

    private function brandTab(): Tab
    {
        $key = LandingSection::BRAND;

        return $this->tab($key, 'heroicon-o-sparkles', [
            Grid::make(2)->schema([
                $this->image("{$key}.content.logo", __('landing.fields.logo'), 'landing/brand'),
                $this->image("{$key}.content.favicon", __('landing.fields.favicon'), 'landing/brand'),
            ]),

            $this->transText("{$key}.content.name", __('landing.fields.brand_name')),
            $this->transText("{$key}.content.suffix", __('landing.fields.brand_suffix')),
            $this->transText("{$key}.content.tagline", __('landing.fields.tagline')),
        ], showToggle: false);
    }

    /* ── SEO ──────────────────────────────────────────────────── */

    private function seoTab(): Tab
    {
        $key = LandingSection::SEO;

        return $this->tab($key, 'heroicon-o-magnifying-glass', [
            $this->transText("{$key}.content.title", __('landing.fields.seo_title')),
            $this->transTextarea("{$key}.content.description", __('landing.fields.seo_description'), 2, __('landing.hints.seo_description')),
            $this->transText("{$key}.content.keywords", __('landing.fields.seo_keywords')),
            $this->image("{$key}.content.og_image", __('landing.fields.og_image'), 'landing/seo'),
        ], showToggle: false);
    }

    /* ── Navbar ───────────────────────────────────────────────── */

    private function navbarTab(): Tab
    {
        $key = LandingSection::NAVBAR;

        return $this->tab($key, 'heroicon-o-bars-3', [
            Grid::make(2)->schema([
                $this->url("{$key}.content.cta_url", __('landing.fields.cta_url')),
                Toggle::make("{$key}.content.show_language_switcher")
                    ->label(__('landing.fields.show_language_switcher'))
                    ->default(true)
                    ->inline(false),
            ]),

            $this->transText("{$key}.content.cta_label", __('landing.fields.cta_label')),

            Repeater::make("{$key}.content.links")
                ->label(__('landing.fields.links'))
                ->schema([
                    $this->transText('label', __('landing.fields.label')),
                    Grid::make(2)->schema([
                        $this->url('url', __('landing.fields.url'), __('landing.hints.anchor_url')),
                        Toggle::make('new_tab')
                            ->label(__('landing.fields.new_tab'))
                            ->inline(false),
                    ]),
                ])
                ->addActionLabel(__('landing.add.link'))
                ->itemLabel(fn (array $state): ?string => $this->itemLabel($state, 'label'))
                ->collapsible()
                ->collapsed()
                ->reorderable()
                ->cloneable()
                ->defaultItems(0)
                ->columnSpanFull(),
        ], showToggle: false);
    }

    /* ── Hero ─────────────────────────────────────────────────── */

    private function heroTab(): Tab
    {
        $key = LandingSection::HERO;

        return $this->tab($key, 'heroicon-o-photo', [
            $this->transText("{$key}.content.eyebrow", __('landing.fields.eyebrow')),
            $this->transText("{$key}.content.title_gold", __('landing.fields.title_gold')),
            $this->transText("{$key}.content.title_light", __('landing.fields.title_light')),
            $this->transTextarea("{$key}.content.description", __('landing.fields.description'), 3),

            Section::make(__('landing.fields.image'))
                ->icon('heroicon-m-camera')
                ->collapsible()
                ->schema([
                    $this->image("{$key}.content.image", __('landing.fields.image'), 'landing/hero'),
                    $this->transText("{$key}.content.image_alt", __('landing.fields.image_alt')),
                ]),

            Section::make(__('landing.fields.cta_label'))
                ->icon('heroicon-m-cursor-arrow-rays')
                ->collapsible()
                ->schema([
                    $this->transText("{$key}.content.primary_cta_label", __('landing.fields.primary_cta_label')),
                    $this->url("{$key}.content.primary_cta_url", __('landing.fields.primary_cta_url'), __('landing.hints.anchor_url')),
                    $this->transText("{$key}.content.secondary_cta_label", __('landing.fields.secondary_cta_label')),
                    $this->url("{$key}.content.secondary_cta_url", __('landing.fields.secondary_cta_url'), __('landing.hints.anchor_url')),
                ]),

            Repeater::make("{$key}.content.badges")
                ->label(__('landing.fields.badges'))
                ->schema([
                    $this->icon('icon'),
                    $this->transText('label', __('landing.fields.label')),
                ])
                ->addActionLabel(__('landing.add.badge'))
                ->itemLabel(fn (array $state): ?string => $this->itemLabel($state, 'label'))
                ->collapsible()
                ->collapsed()
                ->reorderable()
                ->defaultItems(0)
                ->maxItems(4)
                ->columnSpanFull(),
        ]);
    }

    /* ── Services ─────────────────────────────────────────────── */

    private function servicesTab(): Tab
    {
        $key = LandingSection::SERVICES;

        return $this->tab($key, 'heroicon-o-squares-2x2', [
            $this->transText("{$key}.content.eyebrow", __('landing.fields.eyebrow')),
            $this->transText("{$key}.content.title", __('landing.fields.title')),
            $this->transTextarea("{$key}.content.description", __('landing.fields.description'), 2),
            $this->transText("{$key}.content.link_label", __('landing.fields.link_label')),

            Repeater::make("{$key}.content.items")
                ->label(__('landing.fields.items'))
                ->schema([
                    Grid::make(3)->schema([
                        $this->image('image', __('landing.fields.image'), 'landing/services')->columnSpan(1),
                        Grid::make(1)->schema([
                            $this->icon('icon'),
                            $this->url('url', __('landing.fields.url'), __('landing.hints.anchor_url')),
                            Toggle::make('is_active')
                                ->label(__('landing.fields.is_active'))
                                ->default(true)
                                ->inline(false),
                        ])->columnSpan(2),
                    ]),
                    $this->transText('title', __('landing.fields.title')),
                    $this->transTextarea('description', __('landing.fields.description'), 2),
                ])
                ->addActionLabel(__('landing.add.card'))
                ->itemLabel(fn (array $state): ?string => $this->itemLabel($state, 'title'))
                ->collapsible()
                ->collapsed()
                ->reorderable()
                ->cloneable()
                ->defaultItems(0)
                ->columnSpanFull(),
        ]);
    }

    /* ── Features ─────────────────────────────────────────────── */

    private function featuresTab(): Tab
    {
        $key = LandingSection::FEATURES;

        return $this->tab($key, 'heroicon-o-star', [
            $this->transText("{$key}.content.eyebrow", __('landing.fields.eyebrow')),
            $this->transText("{$key}.content.title", __('landing.fields.title')),
            $this->transTextarea("{$key}.content.description", __('landing.fields.description'), 3),

            Grid::make(2)->schema([
                $this->url("{$key}.content.cta_url", __('landing.fields.cta_url'), __('landing.hints.anchor_url')),
            ]),
            $this->transText("{$key}.content.cta_label", __('landing.fields.cta_label')),

            Repeater::make("{$key}.content.items")
                ->label(__('landing.fields.items'))
                ->schema([
                    $this->icon('icon'),
                    $this->transText('title', __('landing.fields.title')),
                    $this->transTextarea('description', __('landing.fields.description'), 2),
                ])
                ->addActionLabel(__('landing.add.feature'))
                ->itemLabel(fn (array $state): ?string => $this->itemLabel($state, 'title'))
                ->collapsible()
                ->collapsed()
                ->reorderable()
                ->cloneable()
                ->defaultItems(0)
                ->maxItems(6)
                ->columnSpanFull(),
        ]);
    }

    /* ── Gallery ──────────────────────────────────────────────── */

    private function galleryTab(): Tab
    {
        $key = LandingSection::GALLERY;

        return $this->tab($key, 'heroicon-o-rectangle-stack', [
            $this->transText("{$key}.content.eyebrow", __('landing.fields.eyebrow')),
            $this->transText("{$key}.content.title", __('landing.fields.title')),
            $this->transTextarea("{$key}.content.description", __('landing.fields.description'), 2),

            Grid::make(2)->schema([
                $this->url("{$key}.content.cta_url", __('landing.fields.cta_url')),
            ]),
            $this->transText("{$key}.content.cta_label", __('landing.fields.cta_label')),

            Repeater::make("{$key}.content.items")
                ->label(__('landing.fields.items'))
                ->schema([
                    Grid::make(3)->schema([
                        $this->image('image', __('landing.fields.image'), 'landing/gallery')->columnSpan(1),
                        Grid::make(1)->schema([
                            Toggle::make('is_wide')
                                ->label(__('landing.fields.is_wide'))
                                ->helperText(__('landing.hints.is_wide'))
                                ->inline(false),
                            Toggle::make('is_active')
                                ->label(__('landing.fields.is_active'))
                                ->default(true)
                                ->inline(false),
                        ])->columnSpan(2),
                    ]),
                    $this->transText('alt', __('landing.fields.alt')),
                    $this->transText('caption', __('landing.fields.caption')),
                ])
                ->addActionLabel(__('landing.add.image'))
                ->itemLabel(fn (array $state): ?string => $this->itemLabel($state, 'caption'))
                ->collapsible()
                ->collapsed()
                ->reorderable()
                ->cloneable()
                ->defaultItems(0)
                ->columnSpanFull(),
        ]);
    }

    /* ── CTA ──────────────────────────────────────────────────── */

    private function ctaTab(): Tab
    {
        $key = LandingSection::CTA;

        return $this->tab($key, 'heroicon-o-megaphone', [
            $this->icon("{$key}.content.icon"),
            $this->transText("{$key}.content.title", __('landing.fields.title')),
            $this->transTextarea("{$key}.content.description", __('landing.fields.description'), 2),
            $this->transText("{$key}.content.button_label", __('landing.fields.cta_label')),
            $this->url("{$key}.content.button_url", __('landing.fields.cta_url'), __('landing.hints.anchor_url')),
        ]);
    }

    /* ── Info strip ───────────────────────────────────────────── */

    private function infoTab(): Tab
    {
        $key = LandingSection::INFO;

        return $this->tab($key, 'heroicon-o-map-pin', [
            Repeater::make("{$key}.content.items")
                ->label(__('landing.fields.items'))
                ->schema([
                    Grid::make(2)->schema([
                        $this->icon('icon'),
                        $this->url('url', __('landing.fields.url')),
                    ]),
                    $this->transText('label', __('landing.fields.label')),
                    $this->transTextarea('value', __('landing.fields.value'), 2),
                ])
                ->addActionLabel(__('landing.add.item'))
                ->itemLabel(fn (array $state): ?string => $this->itemLabel($state, 'label'))
                ->collapsible()
                ->collapsed()
                ->reorderable()
                ->defaultItems(0)
                ->columnSpanFull(),

            Section::make(__('landing.fields.social_links'))
                ->icon('heroicon-m-share')
                ->collapsible()
                ->schema([
                    $this->transText("{$key}.content.social_label", __('landing.fields.social_label')),

                    Repeater::make("{$key}.content.social_links")
                        ->hiddenLabel()
                        ->schema([
                            Grid::make(2)->schema([
                                Select::make('platform')
                                    ->label(__('landing.fields.platform'))
                                    ->options(LandingIcons::socialOptions())
                                    ->native(false)
                                    ->searchable()
                                    ->required(),
                                $this->url('url', __('landing.fields.url')),
                            ]),
                        ])
                        ->addActionLabel(__('landing.add.social'))
                        ->itemLabel(fn (array $state): ?string => $state['platform'] ?? null)
                        ->reorderable()
                        ->defaultItems(0)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /* ── Footer ───────────────────────────────────────────────── */

    private function footerTab(): Tab
    {
        $key = LandingSection::FOOTER;

        return $this->tab($key, 'heroicon-o-bars-3-bottom-left', [
            $this->transTextarea("{$key}.content.tagline", __('landing.fields.tagline'), 2),
            $this->transText("{$key}.content.closing", __('landing.fields.closing')),

            Section::make(__('landing.fields.nav_links'))
                ->icon('heroicon-m-link')
                ->collapsible()
                ->collapsed()
                ->schema([
                    $this->transText("{$key}.content.nav_title", __('landing.fields.nav_title')),
                    $this->linkRepeater("{$key}.content.nav_links"),
                ]),

            Section::make(__('landing.fields.services_links'))
                ->icon('heroicon-m-link')
                ->collapsible()
                ->collapsed()
                ->schema([
                    $this->transText("{$key}.content.services_title", __('landing.fields.services_title')),
                    $this->linkRepeater("{$key}.content.services_links"),
                ]),

            Section::make(__('landing.fields.newsletter_title'))
                ->icon('heroicon-m-envelope')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Toggle::make("{$key}.content.newsletter_enabled")
                        ->label(__('landing.fields.newsletter_enabled'))
                        ->default(true)
                        ->inline(false),

                    $this->transText("{$key}.content.newsletter_title", __('landing.fields.newsletter_title')),
                    $this->transTextarea("{$key}.content.newsletter_description", __('landing.fields.newsletter_description'), 2),
                    $this->transText("{$key}.content.newsletter_placeholder", __('landing.fields.newsletter_placeholder')),

                    Grid::make(2)->schema([
                        TextInput::make("{$key}.content.newsletter_action_url")
                            ->label(__('landing.fields.newsletter_action_url'))
                            ->url()
                            ->helperText(__('landing.hints.newsletter_action'))
                            ->prefixIcon('heroicon-m-link'),

                        TextInput::make("{$key}.content.newsletter_field_name")
                            ->label(__('landing.fields.newsletter_field_name'))
                            ->placeholder('EMAIL')
                            ->helperText(__('landing.hints.newsletter_field')),
                    ]),

                    Grid::make(2)->schema([
                        TextInput::make("{$key}.content.newsletter_email")
                            ->label(__('landing.fields.newsletter_email'))
                            ->email()
                            ->prefixIcon('heroicon-m-envelope'),
                    ]),

                    $this->transText("{$key}.content.newsletter_button_label", __('landing.fields.newsletter_button_label')),
                ]),

            Section::make(__('landing.fields.legal_links'))
                ->icon('heroicon-m-scale')
                ->collapsible()
                ->collapsed()
                ->schema([
                    $this->transText("{$key}.content.copyright", __('landing.fields.copyright')),
                    $this->linkRepeater("{$key}.content.legal_links"),
                ]),
        ]);
    }

    /* ── App page ─────────────────────────────────────────────── */

    private function appPageTab(): Tab
    {
        $key = LandingSection::APP_PAGE;

        return $this->tab($key, 'heroicon-o-device-phone-mobile', [
            $this->transText("{$key}.content.eyebrow", __('landing.fields.eyebrow')),
            $this->transText("{$key}.content.title", __('landing.fields.title')),
            $this->transTextarea("{$key}.content.description", __('landing.fields.description'), 3),

            Section::make(__('landing.fields.app_store_url'))
                ->icon('heroicon-m-arrow-down-tray')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make("{$key}.content.app_store_url")
                            ->label(__('landing.fields.app_store_url'))
                            ->url()
                            ->helperText(__('landing.hints.store_links'))
                            ->prefixIcon('heroicon-m-link'),

                        TextInput::make("{$key}.content.google_play_url")
                            ->label(__('landing.fields.google_play_url'))
                            ->url()
                            ->helperText(__('landing.hints.store_links'))
                            ->prefixIcon('heroicon-m-link'),
                    ]),

                    $this->transText("{$key}.content.app_store_label", __('landing.fields.app_store_label')),
                    $this->transText("{$key}.content.google_play_label", __('landing.fields.google_play_label')),
                    $this->transTextarea("{$key}.content.coming_soon_note", __('landing.fields.coming_soon_note'), 2),
                ]),

            Section::make(__('landing.fields.mockup_image'))
                ->icon('heroicon-m-photo')
                ->collapsible()
                ->schema([
                    Grid::make(2)->schema([
                        $this->image("{$key}.content.mockup_image", __('landing.fields.mockup_image'), 'landing/app'),
                        $this->image("{$key}.content.qr_image", __('landing.fields.qr_image'), 'landing/app'),
                    ]),
                    $this->transTextarea("{$key}.content.qr_note", __('landing.fields.qr_note'), 2),
                ]),

            Repeater::make("{$key}.content.features")
                ->label(__('landing.fields.features'))
                ->schema([
                    $this->icon('icon'),
                    $this->transText('title', __('landing.fields.title')),
                    $this->transTextarea('description', __('landing.fields.description'), 2),
                ])
                ->addActionLabel(__('landing.add.feature'))
                ->itemLabel(fn (array $state): ?string => $this->itemLabel($state, 'title'))
                ->collapsible()
                ->collapsed()
                ->reorderable()
                ->defaultItems(0)
                ->columnSpanFull(),

            Section::make(__('landing.fields.steps'))
                ->icon('heroicon-m-list-bullet')
                ->collapsible()
                ->collapsed()
                ->schema([
                    $this->transText("{$key}.content.steps_eyebrow", __('landing.fields.steps_eyebrow')),
                    $this->transText("{$key}.content.steps_title", __('landing.fields.steps_title')),

                    Repeater::make("{$key}.content.steps")
                        ->hiddenLabel()
                        ->schema([
                            $this->transText('title', __('landing.fields.title')),
                            $this->transTextarea('description', __('landing.fields.description'), 2),
                        ])
                        ->addActionLabel(__('landing.add.step'))
                        ->itemLabel(fn (array $state): ?string => $this->itemLabel($state, 'title'))
                        ->collapsible()
                        ->collapsed()
                        ->reorderable()
                        ->defaultItems(0)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /* ==========================================================
     | Schema helpers
     |========================================================== */

    /**
     * Wrap a section's fields in a tab, prefixed by its visibility toggle and
     * position control.
     *
     * @param  array<int, mixed>  $schema
     * @param  bool  $showToggle  Structural sections (brand, SEO, navbar) are
     *                            not independently hideable — turning the navbar
     *                            "off" would strand the language switcher.
     */
    private function tab(string $key, string $icon, array $schema, bool $showToggle = true): Tab
    {
        $header = [];

        if ($showToggle) {
            $controls = [
                Toggle::make("{$key}.is_active")
                    ->label(__('landing.section_enabled'))
                    ->helperText(__('landing.section_enabled_hint'))
                    ->default(true)
                    ->onColor('success')
                    ->offColor('danger')
                    ->inline(false),
            ];

            if (in_array($key, LandingSection::BODY_KEYS, true)) {
                $controls[] = TextInput::make("{$key}.sort_order")
                    ->label(__('landing.sort_order'))
                    ->helperText(__('landing.sort_order_hint'))
                    ->numeric()
                    ->default(0);
            }

            $header[] = Section::make()
                ->schema([Grid::make(2)->schema($controls)])
                ->compact();
        }

        return Tab::make(__("landing.tabs.{$key}"))
            ->icon($icon)
            ->badge(fn (): ?string => $this->tabBadge($key))
            ->badgeColor('danger')
            ->schema([
                ...$header,
                Section::make()
                    ->description(__("landing.tab_hints.{$key}"))
                    ->schema($schema),
            ]);
    }

    /** A "hidden" marker on the tab so a switched-off section is obvious. */
    private function tabBadge(string $key): ?string
    {
        if (! in_array($key, LandingSection::BODY_KEYS, true) && $key !== LandingSection::APP_PAGE) {
            return null;
        }

        return ($this->data[$key]['is_active'] ?? true) ? null : '✕';
    }

    /**
     * One TextInput per active language, laid out side by side.
     * The state path becomes "<path>.<locale>", which is exactly how
     * LandingSection::translate() reads it back.
     */
    private function transText(string $path, string $label): Grid
    {
        return Grid::make($this->languages()->count())->schema(
            $this->languages()
                ->map(fn (Language $language) => TextInput::make("{$path}.{$language->code}")
                    ->label($this->localeLabel($label, $language))
                    ->maxLength(255)
                    ->extraInputAttributes($this->inputDirection($language)))
                ->all()
        );
    }

    /** Same as transText(), for multi-line copy. */
    private function transTextarea(string $path, string $label, int $rows = 3, ?string $helper = null): Grid
    {
        return Grid::make($this->languages()->count())->schema(
            $this->languages()
                ->map(fn (Language $language) => Textarea::make("{$path}.{$language->code}")
                    ->label($this->localeLabel($label, $language))
                    ->rows($rows)
                    ->helperText($helper)
                    ->extraInputAttributes($this->inputDirection($language)))
                ->all()
        );
    }

    /**
     * Image upload bound to a single path string in the JSON payload.
     *
     * Left empty on a fresh install: the views then show the artwork shipped
     * with the project (the salon photo, the generated placeholders). Uploading
     * here replaces that default for good. The uploads live on the `public`
     * disk, which is what FileUpload validates against — a path pointing
     * anywhere else is dropped on load, which is why the seeder ships these
     * fields blank rather than pre-filled.
     */
    private function image(string $path, string $label, string $directory): FileUpload
    {
        return FileUpload::make($path)
            ->label($label)
            ->helperText(__('landing.hints.image_default'))
            ->image()
            ->disk('public')
            ->directory($directory)
            ->imagePreviewHeight('110')
            ->maxSize(4096)
            ->openable()
            ->downloadable();
    }

    private function icon(string $path = 'icon'): Select
    {
        return Select::make($path)
            ->label(__('landing.fields.icon'))
            ->helperText(__('landing.hints.icon'))
            ->options(LandingIcons::options())
            ->native(false)
            ->searchable();
    }

    private function url(string $path, string $label, ?string $helper = null): TextInput
    {
        return TextInput::make($path)
            ->label($label)
            ->helperText($helper)
            ->maxLength(500)
            ->prefixIcon('heroicon-m-link');
    }

    /** Label + URL repeater, used by all three footer columns. */
    private function linkRepeater(string $path): Repeater
    {
        return Repeater::make($path)
            ->hiddenLabel()
            ->schema([
                $this->transText('label', __('landing.fields.label')),
                $this->url('url', __('landing.fields.url'), __('landing.hints.anchor_url')),
            ])
            ->addActionLabel(__('landing.add.link'))
            ->itemLabel(fn (array $state): ?string => $this->itemLabel($state, 'label'))
            ->collapsible()
            ->collapsed()
            ->reorderable()
            ->defaultItems(0)
            ->columnSpanFull();
    }

    /**
     * Collapsed-repeater summary: show the row's own text in whichever language
     * the admin panel is currently displayed in, falling back to any language
     * that has been filled in.
     */
    private function itemLabel(array $state, string $field): ?string
    {
        $value = $state[$field] ?? null;

        if (! is_array($value)) {
            return is_string($value) ? $value : null;
        }

        return LandingSection::translate($value) ?: null;
    }

    /* ==========================================================
     | Language helpers
     |========================================================== */

    /** @var Collection<int, Language>|null */
    private ?Collection $languageCache = null;

    /** @return Collection<int, Language> */
    private function languages(): Collection
    {
        return $this->languageCache ??= Language::query()
            ->where('is_active', true)
            ->orderBy('order')
            ->get();
    }

    private function localeLabel(string $label, Language $language): string
    {
        return $label . ' (' . strtoupper($language->code) . ')';
    }

    /** @return array<string, string> */
    private function inputDirection(Language $language): array
    {
        return [
            'dir' => config("cms.supported_languages.{$language->code}.direction", 'ltr'),
        ];
    }
}
