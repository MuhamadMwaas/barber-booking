<x-filament-panels::page>
<style>
    /* ── Layout ──────────────────────────────────────────── */
    .abu-stack          { display: flex; flex-direction: column; gap: 1.5rem; }

    /* ── Locale bar ──────────────────────────────────────── */
    .abu-locale-bar     { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
    .abu-locale-btn     {
        display: inline-flex; align-items: center; gap: .4rem;
        padding: .45rem 1.1rem; border-radius: 9999px; font-size: .82rem;
        font-weight: 600; letter-spacing: .02em; cursor: pointer;
        border: 1.5px solid transparent; transition: all .18s;
    }
    .abu-locale-btn.active {
        background: #1e293b;
        color: #f8fafc; border-color: #0f172a;
        box-shadow: 0 2px 8px rgba(0,0,0,.18);
    }
    .abu-locale-btn:not(.active) {
        background: #f1f5f9;
        color: #334155;
        border-color: #e2e8f0;
    }
    .dark .abu-locale-btn.active {
        background: #f8fafc;
        color: #1e293b; border-color: #e2e8f0;
    }
    .dark .abu-locale-btn:not(.active) {
        background: #1e293b;
        color: #cbd5e1;
        border-color: #334155;
    }
    .abu-locale-btn:not(.active):hover {
        background: #e2e8f0;
        color: #0f172a;
        border-color: #cbd5e1;
    }

    /* ── Status badge ────────────────────────────────────── */
    .abu-status-badge {
        display: inline-flex; align-items: center; gap: .35rem;
        padding: .3rem .85rem; border-radius: 9999px;
        font-size: .75rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
    }
    .abu-status-badge.active   { background: #dcfce7; color: #15803d; }
    .abu-status-badge.inactive { background: #fee2e2; color: #b91c1c; }
    .dark .abu-status-badge.active   { background: rgba(21,128,61,.2);  color: #4ade80; }
    .dark .abu-status-badge.inactive { background: rgba(185,28,28,.2); color: #f87171; }
    .abu-status-dot { width: 7px; height: 7px; border-radius: 50%; }
    .abu-status-badge.active   .abu-status-dot { background: #16a34a; }
    .abu-status-badge.inactive .abu-status-dot { background: #dc2626; }

    /* ── Cards ───────────────────────────────────────────── */
    .abu-card {
        background: #fff; border-radius: 1rem; border: 1px solid #e5e7eb;
        box-shadow: 0 1px 6px rgba(15,23,42,.06); overflow: hidden;
    }
    .dark .abu-card { background: rgb(var(--color-gray-900)); border-color: rgba(255,255,255,.08); }

    .abu-card-header {
        display: flex; align-items: center; gap: .65rem;
        padding: 1rem 1.25rem; border-bottom: 1px solid #f1f5f9;
    }
    .dark .abu-card-header { border-color: rgba(255,255,255,.06); }
    .abu-card-icon {
        display: flex; align-items: center; justify-content: center;
        width: 2.1rem; height: 2.1rem; border-radius: .6rem;
        background: rgb(var(--color-primary-50)); color: rgb(var(--color-primary-600));
    }
    .dark .abu-card-icon { background: rgba(var(--color-primary-400),.15); color: rgb(var(--color-primary-400)); }
    .abu-card-title { font-weight: 700; font-size: .92rem; color: #111827; }
    .dark .abu-card-title { color: #f3f4f6; }
    .abu-card-body { padding: 1.1rem 1.25rem; }

    /* ── Hero card ───────────────────────────────────────── */
    .abu-hero-text-grid {
        display: grid; grid-template-columns: 1fr; gap: .75rem;
        margin-bottom: 1.25rem;
    }
    @media(min-width:640px)  { .abu-hero-text-grid { grid-template-columns: 1fr 1fr; } }
    @media(min-width:1024px) { .abu-hero-text-grid { grid-template-columns: 1fr 1fr 1fr; } }

    .abu-locale-chip {
        display: inline-flex; align-items: center; gap: .3rem;
        padding: .2rem .6rem; border-radius: .4rem;
        font-size: .7rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
        background: rgb(var(--color-primary-50)); color: rgb(var(--color-primary-700));
        margin-bottom: .35rem;
    }
    .dark .abu-locale-chip { background: rgba(var(--color-primary-400),.15); color: rgb(var(--color-primary-400)); }

    .abu-lang-block h4 { font-size: 1rem; font-weight: 700; color: #1e293b; margin: 0 0 .2rem; }
    .abu-lang-block p  { font-size: .82rem; color: #64748b; margin: 0; line-height: 1.55; }
    .dark .abu-lang-block h4 { color: #f1f5f9; }
    .dark .abu-lang-block p  { color: #94a3b8; }
    .abu-lang-block .abu-subtitle { font-size: .82rem; font-weight: 600; color: #475569; margin: .15rem 0 .2rem; }
    .dark .abu-lang-block .abu-subtitle { color: #94a3b8; }

    /* ── Image gallery ───────────────────────────────────── */
    .abu-img-gallery {
        display: flex; gap: .65rem; flex-wrap: wrap; margin-top: 1rem;
        padding-top: 1rem; border-top: 1px solid #f1f5f9;
    }
    .dark .abu-img-gallery { border-color: rgba(255,255,255,.06); }
    .abu-img-thumb {
        width: 90px; height: 65px; border-radius: .55rem; object-fit: cover;
        border: 2px solid #e2e8f0; transition: transform .18s;
    }
    .abu-img-thumb:hover { transform: scale(1.06); border-color: rgb(var(--color-primary-400)); }
    .dark .abu-img-thumb { border-color: rgba(255,255,255,.12); }
    .abu-img-count {
        display: flex; align-items: center; justify-content: center;
        width: 90px; height: 65px; border-radius: .55rem;
        background: rgb(var(--color-gray-100)); border: 2px dashed #cbd5e1;
        font-size: .75rem; font-weight: 600; color: #64748b;
    }
    .dark .abu-img-count { background: rgb(var(--color-gray-800)); border-color: rgba(255,255,255,.12); color: #94a3b8; }
    .abu-no-images {
        display: flex; align-items: center; gap: .5rem;
        font-size: .8rem; color: #94a3b8; padding: .5rem 0;
    }

    /* ── Two-column grid ─────────────────────────────────── */
    .abu-two-col { display: grid; grid-template-columns: 1fr; gap: 1.25rem; }
    @media(min-width:768px) { .abu-two-col { grid-template-columns: 1fr 1fr; } }

    /* ── Contact rows ────────────────────────────────────── */
    .abu-contact-row {
        display: flex; align-items: flex-start; gap: .75rem;
        padding: .65rem 0; border-bottom: 1px solid #f8fafc;
    }
    .dark .abu-contact-row { border-color: rgba(255,255,255,.04); }
    .abu-contact-row:last-child { border-bottom: none; padding-bottom: 0; }
    .abu-contact-icon {
        display: flex; align-items: center; justify-content: center;
        width: 2rem; height: 2rem; border-radius: .5rem; flex-shrink: 0;
        background: rgb(var(--color-primary-50)); color: rgb(var(--color-primary-600));
    }
    .dark .abu-contact-icon { background: rgba(var(--color-primary-400),.15); color: rgb(var(--color-primary-400)); }
    .abu-contact-label { font-size: .72rem; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: .06em; }
    .abu-contact-value { font-size: .88rem; font-weight: 500; color: #1e293b; margin-top: .1rem; }
    .dark .abu-contact-value { color: #e2e8f0; }
    .abu-contact-empty { color: #cbd5e1; font-style: italic; }

    /* ── Social chips ────────────────────────────────────── */
    .abu-social-chips { display: flex; flex-wrap: wrap; gap: .5rem; }
    .abu-social-chip {
        display: inline-flex; align-items: center; gap: .4rem;
        padding: .35rem .8rem; border-radius: .5rem; font-size: .8rem; font-weight: 600;
        background: rgb(var(--color-gray-100)); color: #374151;
        border: 1px solid #e5e7eb; text-decoration: none; transition: all .15s;
    }
    .abu-social-chip:hover { background: rgb(var(--color-primary-50)); color: rgb(var(--color-primary-700)); border-color: rgb(var(--color-primary-200)); }
    .dark .abu-social-chip { background: rgb(var(--color-gray-800)); color: #d1d5db; border-color: rgba(255,255,255,.08); }
    .abu-legal-links { display: flex; flex-direction: column; gap: .4rem; margin-top: .85rem; padding-top: .85rem; border-top: 1px solid #f1f5f9; }
    .dark .abu-legal-links { border-color: rgba(255,255,255,.06); }
    .abu-legal-link { font-size: .8rem; color: rgb(var(--color-primary-600)); text-decoration: underline; }

    /* ── Features grid ───────────────────────────────────── */
    .abu-features-grid { display: grid; grid-template-columns: 1fr; gap: .85rem; }
    @media(min-width:640px)  { .abu-features-grid { grid-template-columns: 1fr 1fr; } }
    @media(min-width:1024px) { .abu-features-grid { grid-template-columns: 1fr 1fr 1fr; } }

    .abu-feature-card {
        padding: 1rem; border-radius: .75rem;
        background: rgb(var(--color-gray-50)); border: 1px solid #f1f5f9;
        display: flex; flex-direction: column; gap: .4rem;
    }
    .dark .abu-feature-card { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.06); }
    .abu-feature-icon-wrap {
        display: inline-flex; align-items: center; justify-content: center;
        width: 2.4rem; height: 2.4rem; border-radius: .65rem;
        background: rgb(var(--color-primary-100)); color: rgb(var(--color-primary-700));
        margin-bottom: .2rem;
    }
    .dark .abu-feature-icon-wrap { background: rgba(var(--color-primary-400),.18); color: rgb(var(--color-primary-400)); }
    .abu-feature-title { font-size: .9rem; font-weight: 700; color: #1e293b; }
    .dark .abu-feature-title { color: #f1f5f9; }
    .abu-feature-desc { font-size: .78rem; color: #64748b; line-height: 1.5; }
    .dark .abu-feature-desc { color: #94a3b8; }
    .abu-feature-empty { text-align: center; padding: 2rem; color: #94a3b8; font-size: .85rem; }

    /* ── Newsletter card ─────────────────────────────────── */
    .abu-newsletter-inner {
        display: flex; flex-direction: column; gap: .5rem;
    }
    .abu-nl-enabled {
        display: inline-flex; align-items: center; gap: .4rem;
        padding: .25rem .7rem; border-radius: 9999px;
        font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em;
    }
    .abu-nl-enabled.yes { background: #d1fae5; color: #065f46; }
    .abu-nl-enabled.no  { background: #fee2e2; color: #991b1b; }
    .dark .abu-nl-enabled.yes { background: rgba(6,95,70,.25);  color: #34d399; }
    .dark .abu-nl-enabled.no  { background: rgba(153,27,27,.25); color: #f87171; }
    .abu-nl-title { font-size: .92rem; font-weight: 700; color: #1e293b; }
    .dark .abu-nl-title { color: #f1f5f9; }
    .abu-nl-desc { font-size: .8rem; color: #64748b; }
    .dark .abu-nl-desc { color: #94a3b8; }

    /* ── Team grid ───────────────────────────────────────── */
    .abu-team-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
    @media(min-width:480px)  { .abu-team-grid { grid-template-columns: 1fr 1fr; } }
    @media(min-width:768px)  { .abu-team-grid { grid-template-columns: 1fr 1fr 1fr; } }
    @media(min-width:1200px) { .abu-team-grid { grid-template-columns: 1fr 1fr 1fr 1fr; } }

    .abu-member-card {
        display: flex; flex-direction: column; align-items: center; text-align: center;
        padding: 1.25rem 1rem; border-radius: .9rem;
        background: rgb(var(--color-gray-50)); border: 1px solid #f1f5f9;
        transition: box-shadow .18s;
    }
    .dark .abu-member-card { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.06); }
    .abu-member-card:hover { box-shadow: 0 4px 16px rgba(15,23,42,.1); }
    .abu-member-avatar {
        width: 72px; height: 72px; border-radius: 50%;
        object-fit: cover; border: 3px solid #fff;
        box-shadow: 0 2px 10px rgba(15,23,42,.15); margin-bottom: .75rem;
    }
    .dark .abu-member-avatar { border-color: rgb(var(--color-gray-800)); }
    .abu-member-initials {
        display: flex; align-items: center; justify-content: center;
        width: 72px; height: 72px; border-radius: 50%;
        background: linear-gradient(135deg, rgb(var(--color-primary-500)), rgb(var(--color-primary-700)));
        color: #fff; font-size: 1.5rem; font-weight: 800;
        margin-bottom: .75rem; letter-spacing: .02em;
    }
    .abu-member-name     { font-size: .92rem; font-weight: 700; color: #1e293b; margin-bottom: .15rem; }
    .dark .abu-member-name { color: #f1f5f9; }
    .abu-member-position { font-size: .77rem; color: rgb(var(--color-primary-600)); font-weight: 600; margin-bottom: .3rem; }
    .dark .abu-member-position { color: rgb(var(--color-primary-400)); }
    .abu-member-inactive { font-size: .7rem; color: #ef4444; font-weight: 600; }
    .abu-team-empty { text-align: center; padding: 2.5rem; color: #94a3b8; font-size: .85rem; }

    /* ── Empty state value ───────────────────────────────── */
    .abu-empty-val { color: #cbd5e1; font-style: italic; font-size: .8rem; }
    .dark .abu-empty-val { color: #475569; }
</style>
@vite(['resources/css/app.css', 'resources/js/app.js'])

<div class="abu-stack">

    {{-- ── Locale Switcher + Status ──────────────────────────────── --}}
    <div class="abu-locale-bar">
        @php
            $locales = ['de' => ['flag' => '🇩🇪', 'label' => 'Deutsch'], 'ar' => ['flag' => '🇸🇦', 'label' => 'العربية'], 'en' => ['flag' => '🇬🇧', 'label' => 'English']];
        @endphp
        @foreach($locales as $code => $meta)
            <button
                wire:click="switchLocale('{{ $code }}')"
                type="button"
                class="abu-locale-btn {{ $this->activeLocale === $code ? 'active' : '' }}"
            >
                <span>{{ $meta['flag'] }}</span>
                <span>{{ $meta['label'] }}</span>
            </button>
        @endforeach

        <div style="margin-inline-start: auto;">
            @if($this->record)
                <span class="abu-status-badge {{ $this->record->is_active ? 'active' : 'inactive' }}">
                    <span class="abu-status-dot"></span>
                    {{ $this->record->is_active ? __('about_us.status_active') : __('about_us.status_inactive') }}
                </span>
            @endif
        </div>
    </div>

    @if($this->record)

        {{-- ── Hero Section ──────────────────────────────────────── --}}
        <div class="abu-card">
            <div class="abu-card-header">
                <span class="abu-card-icon">
                    <x-filament::icon icon="heroicon-o-photo" class="h-5 w-5" />
                </span>
                <span class="abu-card-title">{{ __('about_us.hero_section') }}</span>
            </div>
            <div class="abu-card-body">
                <div class="abu-hero-text-grid">
                    @foreach(['de' => '🇩🇪', 'ar' => '🇸🇦', 'en' => '🇬🇧'] as $lang => $flag)
                        @php
                            $title    = $this->record->hero_title[$lang]       ?? '';
                            $subtitle = $this->record->hero_subtitle[$lang]    ?? '';
                            $desc     = $this->record->hero_description[$lang] ?? '';
                        @endphp
                        <div class="abu-lang-block">
                            <div class="abu-locale-chip">{{ $flag }} {{ strtoupper($lang) }}</div>
                            @if($title)
                                <h4>{{ $title }}</h4>
                            @else
                                <h4 class="abu-empty-val">{{ __('about_us.empty_value') }}</h4>
                            @endif
                            @if($subtitle)
                                <div class="abu-subtitle">{{ $subtitle }}</div>
                            @endif
                            @if($desc)
                                <p>{{ Str::limit($desc, 120) }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Images Gallery --}}
                @if($this->record->heroImages->isNotEmpty())
                    <div class="abu-img-gallery">
                        @foreach($this->record->heroImages->take(6) as $img)
                            <img
                                src="{{ Storage::disk($img->disk)->url($img->path) }}"
                                alt="hero"
                                class="abu-img-thumb"
                            />
                        @endforeach
                        @if($this->record->heroImages->count() > 6)
                            <div class="abu-img-count">+{{ $this->record->heroImages->count() - 6 }}</div>
                        @endif
                    </div>
                @else
                    <div class="abu-img-gallery">
                        <div class="abu-no-images">
                            <x-filament::icon icon="heroicon-o-photo" class="h-4 w-4" />
                            {{ __('about_us.no_images') }}
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- ── Contact + Social ──────────────────────────────────── --}}
        <div class="abu-two-col">

            {{-- Contact Info --}}
            <div class="abu-card">
                <div class="abu-card-header">
                    <span class="abu-card-icon">
                        <x-filament::icon icon="heroicon-o-phone" class="h-5 w-5" />
                    </span>
                    <span class="abu-card-title">{{ __('about_us.contact_section') }}</span>
                </div>
                <div class="abu-card-body">
                    @php
                        $phone   = $this->record->contact_phone   ?? [];
                        $address = $this->record->contact_address ?? [];
                        $hours   = $this->record->opening_hours   ?? [];
                    @endphp

                    {{-- Phone --}}
                    <div class="abu-contact-row">
                        <span class="abu-contact-icon">
                            <x-filament::icon icon="heroicon-o-phone" class="h-4 w-4" />
                        </span>
                        <div>
                            <div class="abu-contact-label">{{ __('about_us.phone_number') }}</div>
                            <div class="abu-contact-value">
                                {{ $phone['value'] ?? '' ?: '' }}
                                @if(empty($phone['value']))
                                    <span class="abu-empty-val">{{ __('about_us.empty_value') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Address --}}
                    <div class="abu-contact-row">
                        <span class="abu-contact-icon">
                            <x-filament::icon icon="heroicon-o-map-pin" class="h-4 w-4" />
                        </span>
                        <div>
                            <div class="abu-contact-label">{{ __('about_us.address') }}</div>
                            <div class="abu-contact-value">
                                @if(!empty($address['value']))
                                    {{ $address['value'] }}
                                @else
                                    <span class="abu-empty-val">{{ __('about_us.empty_value') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Email --}}
                    <div class="abu-contact-row">
                        <span class="abu-contact-icon">
                            <x-filament::icon icon="heroicon-o-envelope" class="h-4 w-4" />
                        </span>
                        <div>
                            <div class="abu-contact-label">{{ __('about_us.email') }}</div>
                            <div class="abu-contact-value">
                                @if($this->record->contact_email)
                                    {{ $this->record->contact_email }}
                                @else
                                    <span class="abu-empty-val">{{ __('about_us.empty_value') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Opening Hours --}}
                    <div class="abu-contact-row">
                        <span class="abu-contact-icon">
                            <x-filament::icon icon="heroicon-o-clock" class="h-4 w-4" />
                        </span>
                        <div>
                            <div class="abu-contact-label">{{ __('about_us.opening_hours') }}</div>
                            <div class="abu-contact-value" style="display:flex;flex-direction:column;gap:.1rem;">
                                @forelse(['de' => '🇩🇪', 'ar' => '🇸🇦', 'en' => '🇬🇧'] as $lang => $flag)
                                    @if(!empty($hours[$lang]))
                                        <span>{{ $flag }} {{ $hours[$lang] }}</span>
                                    @endif
                                @empty
                                    <span class="abu-empty-val">{{ __('about_us.empty_value') }}</span>
                                @endforelse
                                @if(collect($hours)->filter()->isEmpty())
                                    <span class="abu-empty-val">{{ __('about_us.empty_value') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Social & Legal --}}
            <div class="abu-card">
                <div class="abu-card-header">
                    <span class="abu-card-icon">
                        <x-filament::icon icon="heroicon-o-share" class="h-5 w-5" />
                    </span>
                    <span class="abu-card-title">{{ __('about_us.social_section') }}</span>
                </div>
                <div class="abu-card-body">
                    @php
                        $socialLinks = $this->record->social_links ?? [];
                        $legalLinks  = $this->record->legal_links  ?? [];
                        $socialTitle = $this->record->social_title[$this->activeLocale] ?? ($this->record->social_title['de'] ?? '');
                    @endphp

                    @if($socialTitle)
                        <p style="font-size:.82rem;font-weight:600;color:#64748b;margin:0 0 .75rem;">{{ $socialTitle }}</p>
                    @endif

                    @if(!empty($socialLinks))
                        <div class="abu-social-chips">
                            @foreach($socialLinks as $link)
                                <a href="{{ $link['url'] ?? '#' }}" target="_blank" class="abu-social-chip">
                                    <x-filament::icon icon="heroicon-o-globe-alt" class="h-3.5 w-3.5" />
                                    {{ ucfirst($link['platform'] ?? '') }}
                                </a>
                            @endforeach
                        </div>
                    @else
                        <p class="abu-empty-val">{{ __('about_us.no_social_links') }}</p>
                    @endif

                    @if(!empty($legalLinks))
                        <div class="abu-legal-links">
                            @foreach($legalLinks as $link)
                                <a href="{{ $link['url'] ?? '#' }}" class="abu-legal-link">
                                    {{ $link['label'][$this->activeLocale] ?? $link['label']['de'] ?? $link['key'] ?? '' }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ── Features ──────────────────────────────────────────── --}}
        @php $features = $this->record->features ?? []; @endphp
        <div class="abu-card">
            <div class="abu-card-header">
                <span class="abu-card-icon">
                    <x-filament::icon icon="heroicon-o-star" class="h-5 w-5" />
                </span>
                <span class="abu-card-title">{{ __('about_us.features_section') }}</span>
                <span style="margin-inline-start:auto;font-size:.75rem;color:#94a3b8;">
                    {{ count($features) }} / 6
                </span>
            </div>
            <div class="abu-card-body">
                @if(!empty($features))
                    <div class="abu-features-grid">
                        @foreach($features as $feature)
                            <div class="abu-feature-card">
                                @if(!empty($feature['icon']))
                                    <div class="abu-feature-icon-wrap">
                                        <x-filament::icon :icon="$feature['icon']" class="h-5 w-5" />
                                    </div>
                                @endif
                                <div class="abu-feature-title">
                                    {{ $feature['title'][$this->activeLocale] ?? $feature['title']['de'] ?? '' }}
                                </div>
                                @if(!empty($feature['description'][$this->activeLocale] ?? $feature['description']['de'] ?? ''))
                                    <div class="abu-feature-desc">
                                        {{ Str::limit($feature['description'][$this->activeLocale] ?? $feature['description']['de'] ?? '', 90) }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="abu-feature-empty">
                        <x-filament::icon icon="heroicon-o-star" class="h-8 w-8 mx-auto mb-2 opacity-30" />
                        <p>{{ __('about_us.no_features') }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- ── Newsletter ─────────────────────────────────────────── --}}
        <div class="abu-card">
            <div class="abu-card-header">
                <span class="abu-card-icon">
                    <x-filament::icon icon="heroicon-o-envelope" class="h-5 w-5" />
                </span>
                <span class="abu-card-title">{{ __('about_us.newsletter_section') }}</span>
                <span style="margin-inline-start:auto;">
                    <span class="abu-nl-enabled {{ $this->record->newsletter_enabled ? 'yes' : 'no' }}">
                        {{ $this->record->newsletter_enabled ? __('about_us.newsletter_on') : __('about_us.newsletter_off') }}
                    </span>
                </span>
            </div>
            <div class="abu-card-body">
                <div class="abu-newsletter-inner">
                    @php
                        $nlTitle = $this->record->newsletter_title[$this->activeLocale] ?? $this->record->newsletter_title['de'] ?? '';
                        $nlDesc  = is_array($this->record->newsletter_description)
                            ? ($this->record->newsletter_description[$this->activeLocale] ?? $this->record->newsletter_description['de'] ?? '')
                            : $this->record->newsletter_description;
                    @endphp
                    @if($nlTitle)
                        <div class="abu-nl-title">{{ $nlTitle }}</div>
                    @else
                        <div class="abu-empty-val">{{ __('about_us.empty_value') }}</div>
                    @endif
                    @if($nlDesc)
                        <div class="abu-nl-desc">{{ $nlDesc }}</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ── Team Members ───────────────────────────────────────── --}}
        <div class="abu-card">
            <div class="abu-card-header">
                <span class="abu-card-icon">
                    <x-filament::icon icon="heroicon-o-user-group" class="h-5 w-5" />
                </span>
                <span class="abu-card-title">{{ __('about_us.team_section') }}</span>
                <span style="margin-inline-start:auto;font-size:.8rem;color:#94a3b8;">
                    {{ $this->record->teamMembers->count() }} {{ __('about_us.members') }}
                </span>
            </div>
            <div class="abu-card-body">
                @if($this->record->teamMembers->isNotEmpty())
                    <div class="abu-team-grid">
                        @foreach($this->record->teamMembers as $member)
                            <div class="abu-member-card">
                                @if($member->image && Storage::disk('public')->exists($member->image))
                                    <img
                                        src="{{ Storage::disk('public')->url($member->image) }}"
                                        alt="{{ $member->name[$this->activeLocale] ?? '' }}"
                                        class="abu-member-avatar"
                                    />
                                @else
                                    <div class="abu-member-initials">
                                        {{ strtoupper(mb_substr($member->name[$this->activeLocale] ?? $member->name['de'] ?? '?', 0, 1)) }}
                                    </div>
                                @endif
                                <div class="abu-member-name">
                                    {{ $member->name[$this->activeLocale] ?? $member->name['de'] ?? '' }}
                                </div>
                                @if(!empty($member->position[$this->activeLocale] ?? $member->position['de'] ?? ''))
                                    <div class="abu-member-position">
                                        {{ $member->position[$this->activeLocale] ?? $member->position['de'] ?? '' }}
                                    </div>
                                @endif
                                @if(!$member->is_active)
                                    <div class="abu-member-inactive">{{ __('about_us.inactive') }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="abu-team-empty">
                        <x-filament::icon icon="heroicon-o-user-group" class="h-8 w-8 mx-auto mb-2 opacity-30" />
                        <p>{{ __('about_us.no_team_members') }}</p>
                    </div>
                @endif
            </div>
        </div>

    @endif {{-- end $this->record --}}
</div>
</x-filament-panels::page>
