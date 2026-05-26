{{-- ============================================================
     CMS Page — Mobile App Preview
     Theme: Dark / Gold  (matches the barber app design)
     ============================================================ --}}
<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ $direction }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview — {{ $page->name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ── Reset & Variables ────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:           #0D0D0D;
            --surface:      #1A1A1A;
            --surface-2:    #222222;
            --surface-3:    #2A2A2A;
            --gold:         #D4A017;
            --gold-light:   #E8B82A;
            --gold-dark:    #B88A0F;
            --text:         #FFFFFF;
            --text-soft:    #CCCCCC;
            --text-muted:   #888888;
            --text-dim:     #444444;
            --border:       #2A2A2A;
            --border-light: #333333;
            --danger:       #E53935;
            --success:      #43A047;
            --warning-bg:   #1C1500;
            --warning-border:#4A3800;
            --warning-text: #F5C518;
        }

        html, body {
            height: 100%;
            background: #060606;
            color: var(--text);
            font-family: @if($direction === 'rtl') 'Cairo', @endif 'Inter', sans-serif;
        }

        body { display: flex; min-height: 100vh; overflow-x: hidden; }

        /* ── Sidebar ─────────────────────────────────────── */
        .sidebar {
            width: 270px;
            min-height: 100vh;
            background: #111111;
            border-right: 1px solid var(--border);
            padding: 28px 20px;
            display: flex;
            flex-direction: column;
            gap: 22px;
            flex-shrink: 0;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: #2a2a2a transparent;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: color .2s;
            padding: 6px 0;
        }
        .back-btn:hover { color: var(--text); }
        .back-btn svg { flex-shrink: 0; }

        .preview-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(212, 160, 23, .12);
            border: 1px solid rgba(212, 160, 23, .3);
            color: var(--gold);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 20px;
        }

        .page-name {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
            margin-top: 4px;
            line-height: 1.3;
        }

        .page-slug {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            color: var(--text-muted);
            background: var(--surface-2);
            padding: 4px 9px;
            border-radius: 6px;
            margin-top: 6px;
            display: inline-block;
            word-break: break-all;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 11px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 8px;
        }
        .badge-on  { background: #052e16; color: #4ade80; border: 1px solid #166534; }
        .badge-off { background: #450a0a; color: #fca5a5; border: 1px solid #991b1b; }

        .s-divider { height: 1px; background: var(--border); flex-shrink: 0; }

        .section-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--text-dim);
            margin-bottom: 10px;
        }

        /* Language switcher */
        .lang-grid { display: flex; flex-wrap: wrap; gap: 6px; }
        .lang-btn {
            padding: 8px 14px;
            border-radius: 9px;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all .2s;
            text-decoration: none;
            display: inline-block;
        }
        .lang-btn:hover { border-color: var(--gold); color: var(--gold); }
        .lang-btn.active {
            background: var(--gold);
            border-color: var(--gold);
            color: #000;
            font-weight: 700;
        }

        /* Stats */
        .stat-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 14px;
            background: var(--surface);
            border-radius: 10px;
            border: 1px solid var(--border);
            margin-bottom: 6px;
        }
        .stat-label { font-size: 12px; color: var(--text-muted); }
        .stat-value { font-size: 15px; font-weight: 700; color: var(--text); }
        .stat-value.gold { color: var(--gold); }

        /* ── Main area ───────────────────────────────────── */
        .main {
            flex: 1;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 44px 30px;
            min-height: 100vh;
        }

        .phone-sticky { position: sticky; top: 44px; }

        /* ── Phone frame ─────────────────────────────────── */
        .phone {
            width: 390px;
            height: 844px;
            background: var(--bg);
            border-radius: 52px;
            border: 2px solid #333;
            box-shadow:
                0 0 0 1px #111,
                inset 0 0 0 1px #2a2a2a,
                0 50px 100px rgba(0,0,0,.85),
                0 0 120px rgba(212,160,23,.04);
            overflow: hidden;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        /* Side buttons */
        .phone::before,
        .phone::after {
            content: '';
            position: absolute;
            background: #252525;
            border-radius: 3px;
        }
        .phone::before {   /* Volume buttons */
            width: 3px;
            height: 72px;
            top: 130px;
            left: -5px;
            box-shadow: 0 44px 0 #252525;
        }
        .phone::after {    /* Power button */
            width: 3px;
            height: 80px;
            top: 160px;
            right: -5px;
        }

        /* Dynamic Island */
        .dynamic-island {
            position: absolute;
            top: 13px;
            left: 50%;
            transform: translateX(-50%);
            width: 126px;
            height: 37px;
            background: #000;
            border-radius: 20px;
            z-index: 10;
        }

        /* Status bar */
        .status-bar {
            height: 58px;
            padding: 0 28px 10px;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            flex-shrink: 0;
            position: relative;
            z-index: 5;
        }

        .status-time { font-size: 15px; font-weight: 700; color: var(--text); }

        .status-icons {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* App header */
        .app-header {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
            background: var(--bg);
            border-bottom: 1px solid var(--border);
            position: relative;
            z-index: 5;
        }

        .app-back-btn {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: var(--surface);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .app-header-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text);
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Scrollable app content */
        .app-content {
            flex: 1;
            overflow-y: auto;
            scrollbar-width: none;
        }
        .app-content::-webkit-scrollbar { display: none; }
        .app-content-inner { padding: 20px 0 12px; }

        /* ── Block wrappers ──────────────────────────────── */
        .block-wrap {
            padding: 0 20px;
            margin-bottom: 18px;
            position: relative;
        }

        .inactive-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,.75);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 8;
            border: 1px dashed var(--danger);
            border-radius: 10px;
            margin: 0 16px;
        }

        .inactive-tag {
            background: var(--danger);
            color: #fff;
            font-size: 9px;
            font-weight: 800;
            padding: 3px 9px;
            border-radius: 4px;
            letter-spacing: .1em;
            text-transform: uppercase;
        }

        /* ── Block: Heading ──────────────────────────────── */
        .b-heading { line-height: 1.25; }
        .b-heading.h1 { font-size: 26px; font-weight: 800; }
        .b-heading.h2 { font-size: 21px; font-weight: 700; }
        .b-heading.h3 { font-size: 17px; font-weight: 700; }
        .b-heading.h4 { font-size: 15px; font-weight: 600; }
        .c-default  { color: var(--text); }
        .c-primary  { color: var(--gold); }
        .c-muted    { color: var(--text-muted); }

        /* ── Block: Paragraph ────────────────────────────── */
        .b-paragraph {
            font-size: 13.5px;
            line-height: 1.75;
            color: #c0c0c0;
        }

        /* ── Block: Title + Paragraph ────────────────────── */
        .b-title-para .tp-title {
            font-size: 17px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 7px;
        }
        .b-title-para .tp-body {
            font-size: 13.5px;
            line-height: 1.75;
            color: #c0c0c0;
        }

        /* ── Block: Lists ────────────────────────────────── */
        .b-list { list-style: none; }
        .b-list li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 9px 0;
            border-bottom: 1px solid var(--border);
            font-size: 13.5px;
            color: #c0c0c0;
            line-height: 1.5;
        }
        .b-list li:last-child { border-bottom: none; }
        .list-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--gold);
            flex-shrink: 0;
            margin-top: 6px;
        }
        .list-num {
            font-size: 12px;
            font-weight: 700;
            color: var(--gold);
            flex-shrink: 0;
            min-width: 18px;
            padding-top: 1px;
        }

        /* ── Block: Divider ──────────────────────────────── */
        .b-divider-h {
            width: 100%;
            border: none;
            border-top: 1px solid var(--border);
        }
        .b-divider-h.sz-md { border-top-width: 2px; }
        .b-divider-h.sz-lg { border-top-width: 3px; }
        .b-divider-h.cl-primary { border-color: var(--gold); }

        .b-divider-v-wrap {
            display: flex;
            justify-content: center;
        }
        .b-divider-v {
            width: 1px;
            height: 50px;
            background: var(--border);
        }
        .b-divider-v.sz-md { width: 2px; }
        .b-divider-v.sz-lg { width: 3px; }
        .b-divider-v.cl-primary { background: var(--gold); }

        /* ── Block: Link ─────────────────────────────────── */
        .b-link-wrap { display: flex; justify-content: center; }
        .b-link-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 12px 28px;
            background: var(--gold);
            color: #000;
            font-size: 14px;
            font-weight: 700;
            border-radius: 12px;
            text-decoration: none;
            cursor: default;
        }

        /* ── Block: Image ────────────────────────────────── */
        .b-image img {
            width: 100%;
            border-radius: 12px;
            object-fit: cover;
            max-height: 200px;
            display: block;
        }
        .b-image .img-alt {
            font-size: 11px;
            color: var(--text-muted);
            text-align: center;
            margin-top: 5px;
        }
        .b-image-placeholder {
            width: 100%;
            height: 140px;
            background: var(--surface);
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            color: var(--text-dim);
            font-size: 12px;
            border: 1px dashed var(--border);
        }

        /* ── Block: Warning Box ──────────────────────────── */
        .b-warning {
            background: var(--warning-bg);
            border: 1px solid var(--warning-border);
            border-radius: 12px;
            padding: 13px 15px;
            display: flex;
            gap: 11px;
            align-items: flex-start;
        }
        .warning-icon { font-size: 16px; flex-shrink: 0; margin-top: 1px; }
        .warning-text {
            font-size: 13px;
            line-height: 1.65;
            color: var(--warning-text);
        }

        /* ── Empty state ─────────────────────────────────── */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 50px 20px;
            color: var(--text-dim);
        }
        .empty-state-icon { font-size: 40px; }
        .empty-state-text { font-size: 13px; }

        /* ── Bottom nav ──────────────────────────────────── */
        .app-bottom-nav {
            height: 78px;
            background: var(--surface);
            border-top: 1px solid var(--border);
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: space-around;
            padding: 0 8px;
        }
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
            padding: 6px 12px;
        }
        .nav-item.active-nav svg { color: var(--gold); }
        .nav-item.active-nav .nav-label { color: var(--gold); }
        .nav-label { font-size: 10px; color: var(--text-dim); }

        /* ── Home indicator ──────────────────────────────── */
        .home-indicator {
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg);
            flex-shrink: 0;
        }
        .home-bar {
            width: 130px;
            height: 4px;
            background: #3a3a3a;
            border-radius: 3px;
        }

        /* ── Text alignment helpers ──────────────────────── */
        .align-left    { text-align: left; }
        .align-right   { text-align: right; }
        .align-center  { text-align: center; }
        .align-justify { text-align: justify; }
    </style>
</head>
<body>

{{-- ═══════════════════════════════════════
     SIDEBAR
════════════════════════════════════════ --}}
<aside class="sidebar">

    {{-- Back to admin --}}
    <a href="{{ url()->previous(route('filament.admin.resources.cms-pages.index')) }}" class="back-btn">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
        </svg>
        {{ __('cms.resource.preview_back') }}
    </a>

    {{-- Page identity --}}
    <div>
        <span class="preview-chip">
            <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            {{ __('cms.resource.preview_label') }}
        </span>
        <div class="page-name">{{ $page->name }}</div>
        <div class="page-slug">/api/pages/{{ $page->slug }}</div>
        <div>
            @if($page->is_active)
                <span class="status-badge badge-on">
                    <svg width="8" height="8" viewBox="0 0 8 8" fill="currentColor"><circle cx="4" cy="4" r="4"/></svg>
                    {{ __('cms.resource.preview_status_active') }}
                </span>
            @else
                <span class="status-badge badge-off">
                    <svg width="8" height="8" viewBox="0 0 8 8" fill="currentColor"><circle cx="4" cy="4" r="4"/></svg>
                    {{ __('cms.resource.preview_status_inactive') }}
                </span>
            @endif
        </div>
    </div>

    <div class="s-divider"></div>

    {{-- Language switcher --}}
    <div>
        <div class="section-label">{{ __('cms.resource.preview_language') }}</div>
        <div class="lang-grid">
            @foreach($supportedLangs as $code => $cfg)
                <a href="?lang={{ $code }}" class="lang-btn {{ $lang === $code ? 'active' : '' }}">
                    {{ $cfg['label'] }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="s-divider"></div>

    {{-- Stats --}}
    <div>
        <div class="section-label">{{ __('cms.resource.preview_stats') }}</div>

        @php
            $totalBlocks    = count($blocks);
            $activeBlocks   = collect($blocks)->where('is_active', true)->count();
            $inactiveBlocks = $totalBlocks - $activeBlocks;
        @endphp

        <div class="stat-row">
            <span class="stat-label">{{ __('cms.resource.preview_total_blocks') }}</span>
            <span class="stat-value gold">{{ $totalBlocks }}</span>
        </div>
        <div class="stat-row">
            <span class="stat-label">{{ __('cms.resource.preview_active_blocks') }}</span>
            <span class="stat-value">{{ $activeBlocks }}</span>
        </div>
        @if($inactiveBlocks > 0)
        <div class="stat-row">
            <span class="stat-label">{{ __('cms.resource.preview_inactive_blocks') }}</span>
            <span class="stat-value" style="color:#fca5a5">{{ $inactiveBlocks }}</span>
        </div>
        @endif
    </div>

    <div class="s-divider"></div>

    {{-- Edit link --}}
    <a href="{{ route('filament.admin.resources.cms-pages.edit', $page) }}" class="back-btn" style="color:var(--gold)">
        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>
        </svg>
        {{ __('cms.resource.preview_edit') }}
    </a>

</aside>

{{-- ═══════════════════════════════════════
     MAIN — Phone preview
════════════════════════════════════════ --}}
<main class="main">
<div class="phone-sticky">
<div class="phone">

    {{-- Dynamic Island --}}
    <div class="dynamic-island"></div>

    {{-- Status bar --}}
    <div class="status-bar">
        <span class="status-time">9:41</span>
        <div class="status-icons">
            {{-- Signal --}}
            <svg width="17" height="12" fill="white" viewBox="0 0 17 12">
                <rect x="0"  y="8" width="3" height="4" rx="1" opacity=".4"/>
                <rect x="4.5" y="5" width="3" height="7" rx="1" opacity=".6"/>
                <rect x="9"  y="2" width="3" height="10" rx="1" opacity=".8"/>
                <rect x="13.5" y="0" width="3" height="12" rx="1"/>
            </svg>
            {{-- WiFi --}}
            <svg width="16" height="12" fill="white" viewBox="0 0 16 12">
                <path d="M8 10a1.5 1.5 0 100 3 1.5 1.5 0 000-3z"/>
                <path d="M4.5 7.5C5.7 6.3 6.8 5.7 8 5.7s2.3.6 3.5 1.8l1.5-1.5C11.4 4.4 9.8 3.7 8 3.7s-3.4.7-5 2.3l1.5 1.5z" opacity=".7"/>
                <path d="M1.5 4.5C3.4 2.6 5.6 1.7 8 1.7s4.6.9 6.5 2.8L16 3C13.7.7 11 0 8 0S2.3.7 0 3l1.5 1.5z" opacity=".4"/>
            </svg>
            {{-- Battery --}}
            <svg width="25" height="12" fill="none" viewBox="0 0 25 12">
                <rect x=".5" y=".5" width="21" height="11" rx="3.5" stroke="white" stroke-opacity=".35"/>
                <rect x="2" y="2" width="17" height="8" rx="2" fill="white"/>
                <path d="M23 4v4a2 2 0 000-4z" fill="white" fill-opacity=".4"/>
            </svg>
        </div>
    </div>

    {{-- App header --}}
    <div class="app-header" dir="{{ $direction }}">
        <div class="app-back-btn">
            @if($direction === 'rtl')
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                </svg>
            @else
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
                </svg>
            @endif
        </div>
        <span class="app-header-title">{{ $page->name }}</span>
    </div>

    {{-- Scrollable content --}}
    <div class="app-content">
    <div class="app-content-inner" dir="{{ $direction }}">

        @php
            $defaultLang = $defaultLang ?? config('cms.default_language', 'ar');

            // Helper: get translated field value with fallback
            $t = function (array $block, string $field) use ($lang, $defaultLang): string {
                $translations = $block['translations'] ?? [];
                $value = $translations[$lang][$field]
                      ?? $translations[$defaultLang][$field]
                      ?? '';
                return is_string($value) ? $value : '';
            };

            // Helper: get list items with fallback
            $items = function (array $block) use ($lang, $defaultLang): array {
                $translations = $block['translations'] ?? [];
                return $translations[$lang]['items']
                    ?? $translations[$defaultLang]['items']
                    ?? [];
            };

            // Helper: alignment CSS class (resolves 'auto')
            $alignClass = function (array $block) use ($lang): string {
                $alignment = $block['props']['alignment'] ?? 'auto';
                if ($alignment === 'auto') {
                    $alignment = config("cms.supported_languages.{$lang}.default_alignment", 'left');
                }
                return 'align-' . $alignment;
            };

            // Helper: color CSS class
            $colorClass = function (array $block): string {
                return match ($block['props']['color'] ?? 'default') {
                    'primary' => 'c-primary',
                    'muted'   => 'c-muted',
                    default   => 'c-default',
                };
            };
        @endphp

        @forelse($blocks as $block)
        @php $isActive = $block['is_active'] ?? true; @endphp

        {{-- ─────────────────── HEADING ─────────────────── --}}
        @if($block['type'] === 'heading')
        <div class="block-wrap">
            @if(!$isActive)<div class="inactive-overlay"><span class="inactive-tag">INACTIVE</span></div>@endif
            @php $level = $block['props']['level'] ?? 'h2'; @endphp
            <div class="b-heading {{ $level }} {{ $alignClass($block) }} {{ $colorClass($block) }}">
                {{ $t($block, 'text') ?: '—' }}
            </div>
        </div>

        {{-- ─────────────────── PARAGRAPH ──────────────── --}}
        @elseif($block['type'] === 'paragraph')
        <div class="block-wrap">
            @if(!$isActive)<div class="inactive-overlay"><span class="inactive-tag">INACTIVE</span></div>@endif
            <p class="b-paragraph {{ $alignClass($block) }}">
                {{ $t($block, 'text') ?: '—' }}
            </p>
        </div>

        {{-- ─────────────────── TITLE + PARAGRAPH ─────── --}}
        @elseif($block['type'] === 'title_paragraph')
        <div class="block-wrap">
            @if(!$isActive)<div class="inactive-overlay"><span class="inactive-tag">INACTIVE</span></div>@endif
            <div class="b-title-para {{ $alignClass($block) }}">
                <div class="tp-title">{{ $t($block, 'title') ?: '—' }}</div>
                <p class="tp-body">{{ $t($block, 'text') ?: '—' }}</p>
            </div>
        </div>

        {{-- ─────────────────── ORDERED LIST ───────────── --}}
        @elseif($block['type'] === 'ordered_list')
        <div class="block-wrap">
            @if(!$isActive)<div class="inactive-overlay"><span class="inactive-tag">INACTIVE</span></div>@endif
            <ol class="b-list">
                @forelse($items($block) as $i => $item)
                    <li>
                        <span class="list-num">{{ $i + 1 }}.</span>
                        <span>{{ $item['value'] ?? $item }}</span>
                    </li>
                @empty
                    <li><span class="list-num">1.</span><span style="color:var(--text-dim)">—</span></li>
                @endforelse
            </ol>
        </div>

        {{-- ─────────────────── UNORDERED LIST ─────────── --}}
        @elseif($block['type'] === 'unordered_list')
        <div class="block-wrap">
            @if(!$isActive)<div class="inactive-overlay"><span class="inactive-tag">INACTIVE</span></div>@endif
            <ul class="b-list">
                @forelse($items($block) as $item)
                    <li>
                        <span class="list-dot"></span>
                        <span>{{ $item['value'] ?? $item }}</span>
                    </li>
                @empty
                    <li><span class="list-dot"></span><span style="color:var(--text-dim)">—</span></li>
                @endforelse
            </ul>
        </div>

        {{-- ─────────────────── DIVIDER ────────────────── --}}
        @elseif($block['type'] === 'divider')
        <div class="block-wrap">
            @if(!$isActive)<div class="inactive-overlay"><span class="inactive-tag">INACTIVE</span></div>@endif
            @php
                $orientation = $block['props']['orientation'] ?? 'horizontal';
                $size        = $block['props']['size']        ?? 'sm';
                $color       = $block['props']['color']       ?? 'default';
                $sizeClass   = 'sz-' . $size;
                $colorCls    = $color === 'primary' ? 'cl-primary' : '';
            @endphp
            @if($orientation === 'vertical')
                <div class="b-divider-v-wrap">
                    <div class="b-divider-v {{ $sizeClass }} {{ $colorCls }}"></div>
                </div>
            @else
                <hr class="b-divider-h {{ $sizeClass }} {{ $colorCls }}">
            @endif
        </div>

        {{-- ─────────────────── LINK ───────────────────── --}}
        @elseif($block['type'] === 'link')
        <div class="block-wrap">
            @if(!$isActive)<div class="inactive-overlay"><span class="inactive-tag">INACTIVE</span></div>@endif
            <div class="b-link-wrap">
                <span class="b-link-btn">
                    {{ $t($block, 'label') ?: ($block['url'] ?? 'Link') }}
                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                    </svg>
                </span>
            </div>
        </div>

        {{-- ─────────────────── IMAGE ───────────────────── --}}
        @elseif($block['type'] === 'image')
        <div class="block-wrap">
            @if(!$isActive)<div class="inactive-overlay"><span class="inactive-tag">INACTIVE</span></div>@endif
            <div class="b-image {{ $alignClass($block) }}">
                @if(!empty($block['image']))
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($block['image']) }}"
                         alt="{{ $t($block, 'alt') }}">
                    @if($t($block, 'alt'))
                        <div class="img-alt">{{ $t($block, 'alt') }}</div>
                    @endif
                @else
                    <div class="b-image-placeholder">
                        <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
                        </svg>
                        No image
                    </div>
                @endif
            </div>
        </div>

        {{-- ─────────────────── WARNING BOX ────────────── --}}
        @elseif($block['type'] === 'warning_box')
        <div class="block-wrap">
            @if(!$isActive)<div class="inactive-overlay"><span class="inactive-tag">INACTIVE</span></div>@endif
            <div class="b-warning">
                <span class="warning-icon">⚠️</span>
                <span class="warning-text">{{ $t($block, 'text') ?: '—' }}</span>
            </div>
        </div>

        @endif
        @empty

        {{-- Empty state --}}
        <div class="empty-state">
            <div class="empty-state-icon">📄</div>
            <div class="empty-state-text">{{ __('cms.resource.preview_no_blocks') }}</div>
        </div>

        @endforelse

        {{-- Bottom padding --}}
        <div style="height: 16px;"></div>

    </div>
    </div>

    {{-- Bottom navigation bar --}}
    <div class="app-bottom-nav">
        <div class="nav-item">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#444" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>
            </svg>
            <span class="nav-label">Start</span>
        </div>
        <div class="nav-item">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#444" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23-.693L5 14.5m14.8.8l1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0112 21a48.25 48.25 0 01-8.135-.687c-1.718-.293-2.3-2.379-1.067-3.61L5 14.5"/>
            </svg>
            <span class="nav-label">Leistungen</span>
        </div>
        <div class="nav-item">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#444" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>
            </svg>
            <span class="nav-label">Buchen</span>
        </div>
        <div class="nav-item active-nav">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
            </svg>
            <span class="nav-label">Konto</span>
        </div>
    </div>

    {{-- Home indicator --}}
    <div class="home-indicator">
        <div class="home-bar"></div>
    </div>

</div>{{-- /phone --}}
</div>{{-- /phone-sticky --}}
</main>

</body>
</html>
