{{--
    Sticky top navigation.

    Links, their targets and the CTA all come from the `navbar` section, so an
    admin can rename, reorder, add or remove entries without a deploy. Anchor
    links whose target section is switched off are dropped here rather than
    left pointing at nothing.
--}}
@php
    $navEnabled = $landing->enabled('navbar');
    $ctaLabel   = $landing->text('navbar.cta_label');
    $ctaUrl     = $landing->linkUrl($landing->text('navbar.cta_url')) === '#'
        ? route('landing.app')
        : $landing->linkUrl($landing->text('navbar.cta_url'));

    // links() drops anchors whose target section is switched off, so the bar
    // never shows a link that scrolls nowhere.
    $navLinks = $landing->links('navbar.links');
@endphp

@if ($navEnabled)
    <header
        data-lp-navbar
        class="fixed inset-x-0 top-0 z-50 transition-all duration-300
               [&.is-scrolled]:bg-ink/92 [&.is-scrolled]:backdrop-blur-lg
               [&.is-scrolled]:border-b [&.is-scrolled]:border-gold/15
               [&.is-scrolled]:shadow-[0_10px_40px_-24px_rgba(217,169,40,0.5)]"
    >
        <div class="lp-container">
            <nav class="flex h-20 items-center justify-between gap-6" aria-label="{{ __('landing.primary_navigation') }}">

                {{-- Brand --}}
                <a href="{{ route('landing') }}" class="flex shrink-0 items-center gap-3">
                    <img
                        src="{{ $logo }}"
                        alt="{{ $brandLabel }}"
                        class="h-11 w-auto sm:h-12"
                        width="112" height="112"
                    >
                    <span class="lp-display hidden text-lg leading-none sm:block">
                        <span class="lp-text-gold-grad">{{ $brandName }}</span>
                        <span class="block text-[0.6rem] tracking-[0.42em] text-mist">{{ $brandSuffix }}</span>
                    </span>
                </a>

                {{-- Desktop links --}}
                <ul class="hidden items-center gap-8 lg:flex">
                    @foreach ($navLinks as $link)
                        <li>
                            <a
                                href="{{ $landing->linkUrl($link['url'] ?? null) }}"
                                data-lp-nav-link
                                class="lp-nav-link"
                                @if (! empty($link['new_tab'])) target="_blank" rel="noopener" @endif
                            >
                                {{ $landing->t($link['label'] ?? null) }}
                            </a>
                        </li>
                    @endforeach
                </ul>

                {{-- Right cluster --}}
                <div class="flex items-center gap-3">

                    @if ($landing->bool('navbar.show_language_switcher', true))
                        @include('landing.partials.language-switcher')
                    @endif

                    @if ($ctaLabel)
                        <a href="{{ $ctaUrl }}" class="lp-btn lp-btn-outline lp-btn-sm hidden sm:inline-flex">
                            {{ $ctaLabel }}
                        </a>
                    @endif

                    {{-- Mobile menu trigger --}}
                    <button
                        type="button"
                        data-lp-menu-toggle
                        class="lp-badge lp-badge-md lg:hidden"
                        aria-expanded="false"
                        aria-controls="lp-mobile-menu"
                        aria-label="{{ __('landing.open_menu') }}"
                    >
                        <x-landing.icon name="menu" class="size-5" />
                    </button>
                </div>
            </nav>
        </div>

        {{-- Mobile menu overlay --}}
        <div
            id="lp-mobile-menu"
            data-lp-menu
            class="hidden border-t border-gold/15 bg-ink/98 backdrop-blur-lg lg:hidden"
        >
            <div class="lp-container py-6">
                <ul class="flex flex-col gap-1">
                    @foreach ($navLinks as $link)
                        <li>
                            <a
                                href="{{ $landing->linkUrl($link['url'] ?? null) }}"
                                class="block rounded-md px-3 py-3 text-sm font-medium uppercase tracking-[0.14em] text-mist transition-colors hover:bg-gold/10 hover:text-gold"
                                @if (! empty($link['new_tab'])) target="_blank" rel="noopener" @endif
                            >
                                {{ $landing->t($link['label'] ?? null) }}
                            </a>
                        </li>
                    @endforeach
                </ul>

                @if ($ctaLabel)
                    <a href="{{ $ctaUrl }}" class="lp-btn lp-btn-gold mt-5 w-full">
                        {{ $ctaLabel }}
                    </a>
                @endif
            </div>
        </div>
    </header>
@endif
