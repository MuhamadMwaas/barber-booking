<?php

namespace App\Providers\Filament;

use App\Filament\Pages\ManageProviderSchedules;
use CraftForge\FilamentLanguageSwitcher\FilamentLanguageSwitcherPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider {
    /**
     * Single source of truth for navigation group colors.
     *
     * The array key MUST match the translation key used in the navigation
     * lang files (and the `$navigationGroup` value on the related resources).
     *
     * Value: [light mode color, dark mode color].
     */
    private const NAVIGATION_GROUP_COLORS = [
        'appointments' => ['#2563eb', '#60a5fa'], // Blue
        'services'     => ['#16a34a', '#4ade80'], // Green
        'staff'        => ['#7c3aed', '#a78bfa'], // Violet
        'reports'      => ['#f59e0b', '#fcd34d'], // Amber
        'billing'      => ['#dc2626', '#f87171'], // Red
        'content'      => ['#ec4899', '#ec4899'], // Pink
        'users'        => ['#06b6d4', '#67e8f9'], // Cyan
        'settings'     => ['#64748b', '#cbd5e1'], // Slate
    ];

    public function panel(Panel $panel): Panel {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandLogo('/image/logo.png')
            ->favicon('/image/logo.png')
            ->login(\App\Filament\Pages\Auth\Login::class)
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                ManageProviderSchedules::class,
            ])->maxContentWidth(Width::Full)
            ->sidebarFullyCollapsibleOnDesktop()
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentLanguageSwitcherPlugin::make()
                    ->locales([
                        ['code' => 'en', 'name' => 'English', 'flag' => 'us'],
                        ['code' => 'ar', 'name' => 'العربية', 'flag' => 'sa'],
                        ['code' => 'de', 'name' => 'Deutsch', 'flag' => 'de'],
                    ]),
            ])
            ->navigationGroups($this->navigationGroups())
            ->navigationItems([
                NavigationItem::make('Staff Dashboard')
                    ->url(fn() => route('staff.dashboard'), shouldOpenInNewTab: true)
                    ->icon(Heroicon::OutlinedComputerDesktop)
                    ->sort(999),
            ])
            ->authMiddleware([
                Authenticate::class,
            ])->databaseNotifications()
            ->renderHook(
                'panels::styles.before',
                fn() => new HtmlString($this->navigationGroupStyles())
            );
    }

    /**
     * Build a colored NavigationGroup for every entry in the color map.
     *
     * The label closure is deferred so it stays in sync with the active locale,
     * and the class injected via extraSidebarAttributes is what the CSS targets.
     * No icon() is set on purpose: Filament throws if a group has an icon while
     * its items also have icons (which all of ours do).
     */
    private function navigationGroups(): array {
        return array_map(
            fn(string $key): NavigationGroup => NavigationGroup::make()
                ->label(fn(): string => __("navigation.{$key}"))
                ->extraSidebarAttributes(['class' => "fi-ng fi-ng-{$key}"]),
            array_keys(self::NAVIGATION_GROUP_COLORS),
        );
    }

    /**
     * Generate the sidebar stylesheet from the color map.
     *
     * Each group only contributes a `--ng` custom property (light + dark);
     * the shared rules below consume that variable, so the look stays
     * consistent and adding a group is a one-line change to the map.
     */
    private function navigationGroupStyles(): string {
        $variables = '';
        foreach (self::NAVIGATION_GROUP_COLORS as $key => [$light, $dark]) {
            $variables .= ".fi-ng-{$key}{--ng:{$light};}";
            $variables .= ".dark .fi-ng-{$key}{--ng:{$dark};}";
        }

        return <<<HTML
        <style>
            /* ===== Per-group color variable (generated from the PHP map) ===== */
            {$variables}

            /* ===== Shared group styling, driven by --ng ===== */

            /* Colored accent bar wrapping the whole group (RTL-safe) */
            .fi-sidebar-group.fi-ng {
                position: relative;
                margin-inline-start: .375rem;
                padding-inline-start: .625rem;
                border-inline-start: 2px solid color-mix(in srgb, var(--ng) 28%, transparent);
                border-radius: .5rem;
            }

            /* Group header label takes the group color */
            .fi-sidebar-group.fi-ng > .fi-sidebar-group-btn .fi-sidebar-group-label {
                color: var(--ng);
                font-weight: 600;
                letter-spacing: .01em;
            }

            /* Item icons softly tinted with the group color */
            .fi-sidebar-group.fi-ng .fi-sidebar-item-icon {
                color: color-mix(in srgb, var(--ng) 68%, currentColor);
            }

            /* Vertical "grouped" connector line uses the group color */
            .fi-sidebar-group.fi-ng .fi-sidebar-item-grouped-border-part,
            .fi-sidebar-group.fi-ng .fi-sidebar-item-grouped-border-part-not-first,
            .fi-sidebar-group.fi-ng .fi-sidebar-item-grouped-border-part-not-last {
                background-color: color-mix(in srgb, var(--ng) 35%, transparent);
            }

            /* Hover feedback */
            .fi-sidebar-group.fi-ng .fi-sidebar-item-btn:hover {
                background-color: color-mix(in srgb, var(--ng) 10%, transparent);
            }

            /* Active item: tint + colored label/icon */
            .fi-sidebar-group.fi-ng .fi-sidebar-item.fi-active > .fi-sidebar-item-btn {
                background-color: color-mix(in srgb, var(--ng) 16%, transparent);
            }
            .fi-sidebar-group.fi-ng .fi-sidebar-item.fi-active > .fi-sidebar-item-btn .fi-sidebar-item-label,
            .fi-sidebar-group.fi-ng .fi-sidebar-item.fi-active > .fi-sidebar-item-btn .fi-sidebar-item-icon {
                color: var(--ng);
                font-weight: 600;
            }

            /* ===== Existing tweak: repeater item header ===== */
            .fi-fo-repeater-item-header {
                background: navajowhite !important;
            }
        </style>
        HTML;
    }
}
