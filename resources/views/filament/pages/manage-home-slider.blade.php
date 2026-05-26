<x-filament-panels::page>

{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{-- الأنماط                                                                    --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}
<style>
    /* ── Stats Grid ── */
    .hs-stats-grid {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    @media (min-width: 768px) {
        .hs-stats-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    }

    /* ── Stat Card ── */
    .hs-stat-card {
        position: relative;
        overflow: hidden;
        border-radius: 1rem;
        border: 1px solid #e5e7eb;
        background: linear-gradient(160deg, #ffffff 0%, #f8fafc 100%);
        padding: 1.25rem 1.25rem 1rem;
        box-shadow: 0 4px 20px rgba(15,23,42,.05);
        transition: transform .18s, box-shadow .18s;
    }
    .hs-stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(15,23,42,.09); }
    .dark .hs-stat-card {
        border-color: rgba(255,255,255,.07);
        background: linear-gradient(160deg, #111827 0%, #0f172a 100%);
        box-shadow: none;
    }
    .hs-stat-card::after {
        content: '';
        position: absolute;
        inset-inline-end: -1.2rem;
        inset-block-start: -1.2rem;
        width: 4.5rem;
        height: 4.5rem;
        border-radius: 9999px;
        opacity: .12;
    }
    .hs-stat-card[data-tone="blue"]::after   { background: #2563eb; }
    .hs-stat-card[data-tone="green"]::after  { background: #10b981; }
    .hs-stat-card[data-tone="sky"]::after    { background: #0ea5e9; }
    .hs-stat-card[data-tone="red"]::after    { background: #ef4444; }
    .hs-stat-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: .75rem;
    }
    .hs-stat-label {
        margin: 0;
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: #6b7280;
    }
    .dark .hs-stat-label { color: #9ca3af; }
    .hs-stat-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.2rem;
        height: 2.2rem;
        border-radius: .75rem;
        background: rgba(255,255,255,.85);
        box-shadow: inset 0 0 0 1px rgba(148,163,184,.16);
    }
    .dark .hs-stat-icon {
        background: rgba(17,24,39,.9);
        box-shadow: inset 0 0 0 1px rgba(255,255,255,.06);
    }
    .hs-stat-value {
        font-size: 2rem;
        line-height: 1;
        font-weight: 800;
        color: #111827;
        margin: 0;
    }
    .dark .hs-stat-value { color: #f9fafb; }
    .hs-stat-sub {
        margin-top: .4rem;
        font-size: .78rem;
        color: #6b7280;
    }
    .dark .hs-stat-sub { color: #9ca3af; }

    /* ── Section Header ── */
    .hs-section-header {
        display: flex;
        align-items: center;
        gap: .6rem;
        margin-bottom: 1rem;
    }
    .hs-section-title {
        font-size: 1rem;
        font-weight: 700;
        color: #111827;
        margin: 0;
    }
    .dark .hs-section-title { color: #f9fafb; }
    .hs-section-badge {
        display: inline-flex;
        align-items: center;
        padding: .2rem .6rem;
        border-radius: 9999px;
        font-size: .7rem;
        font-weight: 600;
        background: #dcfce7;
        color: #166534;
    }
    .dark .hs-section-badge { background: #14532d; color: #86efac; }

    /* ── Preview Cards Grid ── */
    .hs-cards-grid {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(1, minmax(0, 1fr));
    }
    @media (min-width: 640px)  { .hs-cards-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (min-width: 1024px) { .hs-cards-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }

    /* ── Preview Card ── */
    .hs-card {
        border-radius: 1rem;
        overflow: hidden;
        border: 1px solid #e5e7eb;
        background: #fff;
        box-shadow: 0 2px 12px rgba(15,23,42,.06);
        transition: transform .18s, box-shadow .18s;
    }
    .hs-card:hover { transform: translateY(-3px); box-shadow: 0 8px 28px rgba(15,23,42,.1); }
    .dark .hs-card { background: #1f2937; border-color: rgba(255,255,255,.07); }

    .hs-card-img-wrap {
        position: relative;
        width: 100%;
        aspect-ratio: 16/7;
        overflow: hidden;
        background: #f1f5f9;
    }
    .dark .hs-card-img-wrap { background: #111827; }
    .hs-card-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform .4s;
    }
    .hs-card:hover .hs-card-img { transform: scale(1.04); }
    .hs-card-no-img {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: #94a3b8;
    }

    /* ── Order Badge ── */
    .hs-order-badge {
        position: absolute;
        inset-inline-start: .6rem;
        top: .6rem;
        width: 1.8rem;
        height: 1.8rem;
        border-radius: 9999px;
        background: rgba(0,0,0,.55);
        color: #fff;
        font-size: .72rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(4px);
    }

    /* ── Status Pill ── */
    .hs-pill {
        position: absolute;
        inset-inline-end: .6rem;
        top: .6rem;
        padding: .2rem .65rem;
        border-radius: 9999px;
        font-size: .68rem;
        font-weight: 700;
        backdrop-filter: blur(4px);
    }
    .hs-pill-permanent { background: rgba(16,185,129,.85); color: #fff; }
    .hs-pill-scheduled  { background: rgba(14,165,233,.85); color: #fff; }
    .hs-pill-timed      { background: rgba(245,158,11,.85); color: #fff; }

    /* ── Card Body ── */
    .hs-card-body { padding: .9rem 1rem; }
    .hs-card-title {
        font-weight: 700;
        font-size: .95rem;
        color: #111827;
        margin: 0 0 .25rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .dark .hs-card-title { color: #f9fafb; }
    .hs-card-subtitle {
        font-size: .8rem;
        color: #6b7280;
        margin: 0 0 .5rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .dark .hs-card-subtitle { color: #9ca3af; }
    .hs-card-date {
        font-size: .72rem;
        color: #94a3b8;
        display: flex;
        align-items: center;
        gap: .3rem;
    }

    /* ── Empty Preview ── */
    .hs-empty-preview {
        border-radius: 1rem;
        border: 2px dashed #e5e7eb;
        padding: 2.5rem 1rem;
        text-align: center;
        color: #94a3b8;
    }
    .dark .hs-empty-preview { border-color: rgba(255,255,255,.1); }

    /* ── Page Stack ── */
    .hs-page-stack { display: flex; flex-direction: column; gap: 1.75rem; }

    /* ── Section Box ── */
    .hs-section-box {
        border-radius: 1rem;
        border: 1px solid #e5e7eb;
        background: #fff;
        padding: 1.25rem 1.25rem 1rem;
        box-shadow: 0 2px 12px rgba(15,23,42,.04);
    }
    .dark .hs-section-box {
        background: #1f2937;
        border-color: rgba(255,255,255,.07);
    }
</style>

{{-- ══════════════════════════════════════════════════════════════════════════ --}}
{{-- Page Stack                                                                 --}}
{{-- ══════════════════════════════════════════════════════════════════════════ --}}
<div class="hs-page-stack">

    {{-- ── Stats Row ────────────────────────────────────────────────────────── --}}
    <div class="hs-stats-grid">

        {{-- الإجمالي --}}
        <div class="hs-stat-card" data-tone="blue">
            <div class="hs-stat-head">
                <p class="hs-stat-label">{{ __('slider.stat_total') }}</p>
                <span class="hs-stat-icon">
                    <x-filament::icon icon="heroicon-o-rectangle-stack" class="h-5 w-5 text-blue-500" />
                </span>
            </div>
            <p class="hs-stat-value">{{ $stats['total'] ?? 0 }}</p>
            <div class="hs-stat-sub">{{ __('slider.stat_total_sub') }}</div>
        </div>

        {{-- النشطة --}}
        <div class="hs-stat-card" data-tone="green">
            <div class="hs-stat-head">
                <p class="hs-stat-label">{{ __('slider.stat_active') }}</p>
                <span class="hs-stat-icon">
                    <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5 text-emerald-500" />
                </span>
            </div>
            <p class="hs-stat-value">{{ $stats['active'] ?? 0 }}</p>
            <div class="hs-stat-sub">{{ __('slider.stat_active_sub') }}</div>
        </div>

        {{-- المجدولة --}}
        <div class="hs-stat-card" data-tone="sky">
            <div class="hs-stat-head">
                <p class="hs-stat-label">{{ __('slider.stat_scheduled') }}</p>
                <span class="hs-stat-icon">
                    <x-filament::icon icon="heroicon-o-clock" class="h-5 w-5 text-sky-500" />
                </span>
            </div>
            <p class="hs-stat-value">{{ $stats['scheduled'] ?? 0 }}</p>
            <div class="hs-stat-sub">{{ __('slider.stat_scheduled_sub') }}</div>
        </div>

        {{-- المعطلة --}}
        <div class="hs-stat-card" data-tone="red">
            <div class="hs-stat-head">
                <p class="hs-stat-label">{{ __('slider.stat_inactive') }}</p>
                <span class="hs-stat-icon">
                    <x-filament::icon icon="heroicon-o-x-circle" class="h-5 w-5 text-red-500" />
                </span>
            </div>
            <p class="hs-stat-value">{{ $stats['inactive'] ?? 0 }}</p>
            <div class="hs-stat-sub">{{ __('slider.stat_inactive_sub') }}</div>
        </div>
    </div>

    {{-- ── Preview Section ──────────────────────────────────────────────────── --}}
    <div class="hs-section-box">

        <div class="hs-section-header">
            <x-filament::icon icon="heroicon-o-eye" class="h-5 w-5 text-primary-500" />
            <h2 class="hs-section-title">{{ __('slider.preview_title') }}</h2>
            @if(($activeItems ?? collect())->isNotEmpty())
                <span class="hs-section-badge">{{ __('slider.active_count', ['count' => $activeItems->count()]) }}</span>
            @endif
        </div>

        @if(($activeItems ?? collect())->isEmpty())
            {{-- Empty State --}}
            <div class="hs-empty-preview">
                <x-filament::icon icon="heroicon-o-photo" class="h-12 w-12 mx-auto mb-3 opacity-30" />
                <p class="font-medium text-sm">{{ __('slider.preview_empty') }}</p>
                <p class="text-xs mt-1 opacity-70">{{ __('slider.preview_empty_sub') }}</p>
            </div>
        @else
            {{-- Preview Cards --}}
            <div class="hs-cards-grid">
                @foreach($activeItems as $item)
                    @php
                        $translation = $item->getTranslation('ar') ?? $item->getTranslation('en') ?? $item->translations->first();
                        $imageUrl    = $item->image?->urlFile();
                    @endphp

                    <div class="hs-card">

                        {{-- Image + Badges --}}
                        <div class="hs-card-img-wrap">
                            @if($imageUrl)
                                <img src="{{ $imageUrl }}"
                                     alt="{{ $translation?->title ?? 'Slide' }}"
                                     class="hs-card-img"
                                     loading="lazy" />
                            @else
                                <div class="hs-card-no-img">
                                    <x-filament::icon icon="heroicon-o-photo" class="h-12 w-12 opacity-30" />
                                </div>
                            @endif

                            {{-- ترتيب الشريحة --}}
                            <div class="hs-order-badge">{{ $item->sort_order }}</div>

                            {{-- نوع الجدولة --}}
                            @if($item->isPermanent())
                                <div class="hs-pill hs-pill-permanent">{{ __('slider.pill_permanent') }}</div>
                            @elseif($item->starts_at && $item->ends_at)
                                <div class="hs-pill hs-pill-timed">{{ __('slider.pill_timed') }}</div>
                            @else
                                <div class="hs-pill hs-pill-scheduled">{{ __('slider.pill_scheduled') }}</div>
                            @endif
                        </div>

                        {{-- Card Body --}}
                        <div class="hs-card-body">
                            <p class="hs-card-title" dir="auto">
                                {{ $translation?->title ?? '—' }}
                            </p>

                            @if($translation?->subtitle)
                                <p class="hs-card-subtitle" dir="auto">
                                    {{ $translation->subtitle }}
                                </p>
                            @endif

                            {{-- نافذة النشر --}}
                            @if($item->starts_at || $item->ends_at)
                                <div class="hs-card-date">
                                    <x-filament::icon icon="heroicon-m-calendar-days" class="h-3.5 w-3.5" />
                                    @if($item->starts_at)
                                        <span>{{ $item->starts_at->format('d/m/Y') }}</span>
                                    @endif
                                    @if($item->starts_at && $item->ends_at)
                                        <span>←</span>
                                    @endif
                                    @if($item->ends_at)
                                        <span>{{ $item->ends_at->format('d/m/Y') }}</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ── Management Table ────────────────────────────────────────────────── --}}
    <div class="hs-section-box" style="padding: 0; overflow: hidden;">
        <div style="padding: 1rem 1.25rem .75rem; border-bottom: 1px solid #e5e7eb;" class="dark:border-white/10">
            <div class="hs-section-header" style="margin-bottom: 0;">
                <x-filament::icon icon="heroicon-o-table-cells" class="h-5 w-5 text-primary-500" />
                <h2 class="hs-section-title">{{ __('slider.table_section_title') }}</h2>
                <span style="font-size:.72rem; color:#6b7280;">
                    {{ __('slider.table_section_hint') }}
                </span>
            </div>
        </div>
        {{ $this->table }}
    </div>

</div>

</x-filament-panels::page>
